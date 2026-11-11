<?php

namespace App\Http\Controllers;

use App\Models\Office;
use App\Models\Parcel;
use App\Services\HttpSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HttpSmsWebhookController extends Controller
{
    protected HttpSmsService $smsService;

    public function __construct(HttpSmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * معالجة أحداث الويب هوك من httpSMS
     */
    public function handle(Request $request)
    {
        $payload = $request->all();

        // فحص مختلف الأماكن التي قد يرسل فيها httpSMS نوع الحدث
        $event = $request->input('event')
            ?? $request->input('type')
            ?? $request->input('event_type')
            ?? $payload['data']['event'] 
            ?? $request->header('x-event-type')
            ?? 'unknown';

        $data = $payload['data'] ?? $payload;

        Log::info("🔔 [STEP 1] Webhook Hit: Event received [{$event}]", [
            'raw_payload' => $payload,
        ]);

        // 1. التقاط أحداث التسليم والإرسال الناجح لتسجيلها في الكاش ومنع التكرار
        if (in_array($event, [
            'message.phone.delivered', 
            'message.phone.sent',
            'message.send.delivered', 
            'message.send.sent'
            ])) {
            $this->markMessageAsDelivered($data, $event);
            return response()->json(['status' => 'success']);
        }

        // 2. معالجة الرسائل التي فشلت أو انتهت مهلتها فقط
        if (in_array($event, ['message.send.failed', 'message.send.expired'])) {
            Log::info("⚙️ [STEP 2] Event [{$event}] matches retry policy. Starting evaluation...");
            $this->handleFailedOrExpiredMessage($data, $event);
        } else {
            Log::info("ℹ️ [STEP 2] Event [{$event}] ignored.");
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * تسجيل الرسالة كمسلّمة لمنع أي Retry لاحق لنفس الطرد وتحديث حالة الطرد
     */
    protected function markMessageAsDelivered(array $data, string $event): void
    {
        $contact = $data['contact'] ?? $data['recipient'] ?? null;
        $owner   = $data['owner'] ?? $data['sender'] ?? null;
        $content = $data['content'] ?? $data['message'] ?? '';

        if (!$contact || !$owner) {
            return;
        }

        $cleanRecipient = preg_replace('/[^0-9]/', '', $contact);
        $cleanOwner     = preg_replace('/[^0-9]/', '', $owner);
        $contentHash    = md5(trim($content));

        $deliveredKey = "sms_delivered_{$cleanOwner}_{$cleanRecipient}_{$contentHash}";
        
        // حفظ القفل في الكاش لمدة 6 ساعات
        Cache::put($deliveredKey, true, now()->addHours(6));
        Log::info("✅ [DELIVERED] Event [{$event}]: Saved to cache for +{$cleanRecipient} (Hash: {$contentHash}).");

        // البحث عن المكتب لتصفير العداد وتحديث حالة الطرد
        $office = $this->resolveOfficeBySender($cleanOwner);
        if ($office) {
            Cache::forget("sms_108_streak_office_{$office->id}");
            Cache::forget("sms_out_of_balance_office_{$office->id}");
            Cache::forget("sms_retrying_after_recharge_office_{$office->id}");

            $shortRecipient = substr($cleanRecipient, -9);
            $parcel = Parcel::where('office_id', $office->id)
                ->where('recipient_phone', 'LIKE', "%{$shortRecipient}%")
                ->whereDate('created_at', today())
                ->latest('created_at')
                ->first();

            if ($parcel) {
                $isDelivered = in_array($event, ['message.phone.delivered', 'message.send.delivered'], true);
                $parcel->update([
                    'sms_status'         => $isDelivered ? 'delivered' : 'sent',
                    'sms_failure_reason' => null,
                ]);
                Log::info("📦 [PARCEL UPDATED] Parcel ID [{$parcel->id}] sms_status set to [{$parcel->sms_status}].");
            }
        }
    }

    /**
     * منطق إعادة المحاولة الذكي مع الحماية من التكرار ورصد خطأ 108 لنفاد الباقة
     */
    protected function handleFailedOrExpiredMessage(array $data, string $event): void
    {
        $contact = $data['contact'] ?? $data['recipient'] ?? null;
        $owner   = $data['owner'] ?? $data['sender'] ?? null;
        $content = $data['content'] ?? $data['message'] ?? '';

        Log::info("🔍 [STEP 3] Extracting message payload: Contact=[{$contact}], Owner=[{$owner}]");

        if (!$contact || !$owner) {
            Log::warning("⚠ [STEP 3] Aborted: Missing required fields (contact or owner).");
            return;
        }

        // منع معالجة نفس معرّف الرسالة مرتين
        $messageId = $data['id'] ?? null;
        if ($messageId) {
            $msgProcessedKey = "sms_processed_msg_id_{$messageId}";
            if (!Cache::add($msgProcessedKey, true, now()->addHour())) {
                Log::info("🛑 [STEP 3] Message ID [{$messageId}] already processed, skipping duplicate event.");
                return;
            }
        }

        $cleanRecipient = preg_replace('/[^0-9]/', '', $contact);
        $cleanOwner     = preg_replace('/[^0-9]/', '', $owner);
        $contentHash    = md5(trim($content));
        $errorMessage   = strtoupper(trim((string) ($data['error_message'] ?? '')));
        $simSlot        = $data['sim'] ?? 'SIM1';
        $is108Error     = str_contains($errorMessage, '108');

        // مفاتيح الكاش معزولة برقم العميل + بصمة المحتوى
        $retryKey     = "sms_retry_count_{$cleanOwner}_{$cleanRecipient}_{$contentHash}";
        $lockKey      = "sms_lock_{$cleanOwner}_{$cleanRecipient}_{$contentHash}";
        $deliveredKey = "sms_delivered_{$cleanOwner}_{$cleanRecipient}_{$contentHash}";
        $cooldownKey  = "sms_cooldown_{$cleanOwner}_{$cleanRecipient}_{$contentHash}";

        Log::info("🔑 [STEP 4] Cache Keys initialized:", [
            'retryKey'     => $retryKey,
            'lockKey'      => $lockKey,
            'deliveredKey' => $deliveredKey,
            'cooldownKey'  => $cooldownKey,
            'errorMessage' => $errorMessage,
            'simSlot'      => $simSlot,
            'is108Error'   => $is108Error,
        ]);

        // 1. التحقق هل وصلت الرسالة أو خرجت مسبقاً
        if (Cache::has($deliveredKey)) {
            Log::info("🛑 [STEP 5] Skipped: Message was already delivered/sent for +{$cleanRecipient}.");
            return;
        }

        // 2. قفل ذري يمنع تضارب الويب هوك المتزامن في أجزاء الثانية
        $lockAcquired = Cache::add($lockKey, true, now()->addSeconds(30));
        if (!$lockAcquired) {
            Log::warning("🔒 [STEP 6] Lock Active: Another retry request for +{$cleanRecipient} is currently running.");
            return;
        }
        Log::info("🔓 [STEP 6] Atomic lock successfully acquired for 30s.");

        try {
            Log::info("🏢 [STEP 10] Resolving office from database for sender [{$cleanOwner}]...");
            $office = $this->resolveOfficeBySender($cleanOwner);

            if ($office) {
                Log::info("🏢 [STEP 10] Office found: [{$office->name}] (ID: {$office->id})");
            } else {
                Log::error("❌ [STEP 10] Office NOT found in DB matching sender phone [{$cleanOwner}].");
            }

            // ========================================================
            // حالة خطأ 108: نفاد الرصيد أو الباقة في الشريحة
            // ========================================================
            if ($is108Error) {
                Log::warning("🚫 [ERROR 108] Out of balance detected on sender [{$cleanOwner}], sim [{$simSlot}]: {$errorMessage}");

                if ($office) {
                    // 1. تحديث أحدث طرد مسجل اليوم للمكتب يطابق رقم العميل إلى failed_balance
                    $shortRecipient = substr($cleanRecipient, -9);
                    $parcel = Parcel::where('office_id', $office->id)
                        ->where('recipient_phone', 'LIKE', "%{$shortRecipient}%")
                        ->whereDate('created_at', today())
                        ->latest('created_at')
                        ->first();

                    if ($parcel) {
                        $parcel->update([
                            'sms_status'         => 'failed_balance',
                            'sms_failure_reason' => $this->formatSmsFailureReason($errorMessage, $event),
                        ]);
                        Log::info("📦 [PARCEL UPDATED] Parcel ID [{$parcel->id}] sms_status set to 'failed_balance'.");
                    }

                    // 2. حساب العداد المتتالي لفشل 108 وتفعيل حالة نفاد الباقة
                    $streakKey = "sms_108_streak_office_{$office->id}";
                    $streak = (int) Cache::get($streakKey, 0) + 1;
                    Cache::put($streakKey, $streak, now()->addHours(6));

                    $isRetryingAfterRecharge = Cache::has("sms_retrying_after_recharge_office_{$office->id}");
                    Log::info("📊 [STREAK 108] Office [{$office->name}] streak=[{$streak}], isRetryingAfterRecharge=" . ($isRetryingAfterRecharge ? 'YES' : 'NO'));

                    if ($streak >= 3 || $isRetryingAfterRecharge) {
                        Cache::put("sms_out_of_balance_office_{$office->id}", [
                            'out_of_balance' => true,
                            'error_code'     => $errorMessage,
                            'sim'            => $simSlot,
                            'streak'         => $streak,
                            'triggered_at'   => now()->toIso8601String(),
                            'message'        => "توقف إرسال الرسائل النصية (SMS) بسبب انتهاء الباقة أو نفاد الرصيد في الشريحة ({$simSlot} - خطأ 108).",
                        ], now()->addHours(12));

                        Log::error("🚨 [OUT OF BALANCE ACTIVE] Office [{$office->name}] locked in out_of_balance state for 12 hours.");

                        // إرسال إشعار واتساب للمسؤول والمكتب (مع قفل منع التكرار كل ساعتين)
                        $waAlertKey = "sms_108_wa_alert_office_{$office->id}";
                        if (!Cache::has($waAlertKey)) {
                            Cache::put($waAlertKey, true, now()->addHours(2));
                            $this->notifyOutOfBalance($office, $errorMessage, $simSlot, $streak);
                        }
                    }
                }

                // لا نقوم بأي Retry فوري لخطأ 108 لأن الشريحة بلا رصيد
                return;
            }

            // ========================================================
            // حالات الفشل الأخرى (غير 108): منطق إعادة المحاولة التقليدي
            // ========================================================
            // التحقق من فترة التهدئة لمنع التكرار المتزامن
            if (Cache::has($cooldownKey)) {
                Log::warning("⏳ [COOLDOWN] Skipped: Retry cooldown is active for +{$cleanRecipient}. Awaiting delivery report.");
                return;
            }

            if (empty($content)) {
                Log::warning("⚠ [STEP 7] Cannot retry: message content is empty.");
                return;
            }

            $attemptCount = (int) Cache::get($retryKey, 0);
            Log::info("📊 [STEP 7] Current retry count for +{$cleanRecipient} is [{$attemptCount}] out of max 2.");

            if ($attemptCount < 2) {
                $newAttempt = $attemptCount + 1;
                Cache::put($retryKey, $newAttempt, now()->addHours(2));

                // تفعيل فترة تهدئة لمدة 3 دقائق لمنع أي سبايك أو حدث مكرر
                Cache::put($cooldownKey, true, now()->addMinutes(3));
                Log::info("📈 [STEP 8] Counter incremented to [{$newAttempt}]. Cooldown activated for 3 mins.");

                Log::warning("🔄 [STEP 9] Triggering SMS Retry to +{$cleanRecipient} via office {$cleanOwner} (Attempt #{$newAttempt})");

                if ($office && !empty($office->httpsms_api_key)) {
                    Log::info("🚀 [STEP 11] Invoking HttpSmsService->send() to {$cleanRecipient}...");

                    $result = $this->smsService->send(
                        $cleanRecipient,
                        $content,
                        $office->httpsms_api_key,
                        $owner
                    );

                    if ($result['success'] ?? false) {
                        Log::info("🎉 [STEP 12] HttpSmsService returned SUCCESS for attempt #{$newAttempt}.");
                    } else {
                        Log::error("❌ [STEP 12] HttpSmsService returned FAILURE: " . ($result['error'] ?? 'Unknown error'));
                    }
                } else {
                    Log::error("🚫 [STEP 11] Aborted dispatch: Office missing or httpsms_api_key is empty.");
                }
            } else {
                Log::error("🛑 [DROP] SMS to +{$cleanRecipient} reached maximum attempts ({$attemptCount}). Permanently dropping.");
                
                Cache::put($retryKey, 99, now()->addDay());
                Log::info("🔒 [DROP] Retry counter key [{$retryKey}] locked for 24 hours.");

                if ($office) {
                    $shortRecipient = substr($cleanRecipient, -9);
                    $parcel = Parcel::where('office_id', $office->id)
                        ->where('recipient_phone', 'LIKE', "%{$shortRecipient}%")
                        ->whereDate('created_at', today())
                        ->latest('created_at')
                        ->first();

                    if ($parcel) {
                        $parcel->update([
                            'sms_status'         => 'failed',
                            'sms_failure_reason' => $this->formatSmsFailureReason($errorMessage, $event),
                        ]);
                        Log::info("📦 [PARCEL UPDATED] Parcel ID [{$parcel->id}] sms_status set to 'failed'.");
                    }
                }

                $this->notifyAdminFailedSms($office, $cleanRecipient, $cleanOwner, $content, $event);
            }
        } finally {
            Cache::forget($lockKey);
            Log::info("🔓 [FINALLY] Atomic lock [{$lockKey}] released.");
        }
    }

    /**
     * إرسال تنبيه فوري عبر الواتساب للمسؤول عند الفشل النهائي
     */
    protected function notifyAdminFailedSms(?Office $office, string $recipient, string $senderPhone, string $content, string $reason): void
    {
        $adminPhone = '967781152674';
        $officeName = $office ? $office->name : 'غير معروف';
        $timeNow = now()->format('Y-m-d h:i A');

        $alertMessage = "🚨 *تقرير فشل إرسال SMS نهائياً* 🚨\n"
            . "━━━━━━━━━━━━━━━\n"
            . "🏢 *المكتب:* {$officeName}\n"
            . "📱 *هاتف المكتب (المرسل):* {$senderPhone}\n"
            . "👤 *رقم المستلم (العميل):* {$recipient}\n"
            . "⚠️ *حالة الفشل:* {$reason} (بعد محاولتين)\n"
            . "🕒 *الوقت:* {$timeNow}\n"
            . "━━━━━━━━━━━━━━━\n"
            . "📝 *نص الرسالة:*\n"
            . "{$content}\n\n"
            . "💡 *ملاحظة:* يرجى فحص هاتف الفرع (الإنترنت أو وضع توفير الطاقة أو شريحة الإرسال).";

        $this->sendWhatsAppAlert($adminPhone, $alertMessage);
    }

    /**
     * إرسال رسالة التنبيه عبر Evolution API
     */
    protected function sendWhatsAppAlert(string $phone, string $message): bool
    {
        try {
            $evolutionUrl = rtrim(config('services.evolution.url', env('EVOLUTION_API_URL')), '/');
            $apiKey       = config('services.evolution.api_key', env('EVOLUTION_API_KEY'));
            $url          = "{$evolutionUrl}/send/text";

            $formattedPhone = preg_replace('/[^0-9]/', '', $phone);

            if (!str_starts_with($formattedPhone, '967') && strlen($formattedPhone) == 9) {
                $formattedPhone = '967' . $formattedPhone;
            }

            $response = Http::withHeaders([
                'apikey'       => $apiKey,
                'Content-Type' => 'application/json',
            ])->post($url, [
                'number'  => $formattedPhone,
                'text'    => $message,
                'delay'   => 1200,
                'options' => [
                    'presence' => 'composing',
                ],
            ]);

            if ($response->successful()) {
                Log::info("📲 Admin failure alert sent successfully to WhatsApp: {$formattedPhone}");
                return true;
            }

            Log::error("❌ Admin WhatsApp Alert Failed: " . $response->body());
            return false;
        } catch (\Throwable $e) {
            Log::error("❌ Admin WhatsApp Alert Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * إرسال تنبيه واتساب للمسؤول والمكتب عند نفاد باقة/رصيد SMS
     */
    protected function notifyOutOfBalance(Office $office, string $errorCode, string $simSlot, int $streak): void
    {
        $adminPhone = '967781152674';
        $officeName = $office->name;
        $branch     = $office->branch_name ? " ({$office->branch_name})" : '';
        $senderPhone= $office->httpsms_from_phone ?: $office->whatsapp_sender_phone ?: 'غير محدد';
        $timeNow    = now()->format('Y-m-d h:i A');

        $alertMessage = "⚠️ *تنبيه: نفاد باقة/رصيد رسائل SMS في المكتب* ⚠️\n"
            . "━━━━━━━━━━━━━━━\n"
            . "🏢 *المكتب:* {$officeName}{$branch}\n"
            . "📱 *هاتف الشريحة:* {$senderPhone}\n"
            . "💳 *الشريحة:* {$simSlot}\n"
            . "❌ *رمز الخطأ:* {$errorCode}\n"
            . "🔢 *تكرار الفشل:* {$streak} رسائل متتالية\n"
            . "🕒 *الوقت:* {$timeNow}\n"
            . "━━━━━━━━━━━━━━━\n"
            . "⚠️ تم إيقاف إرسال رسائل الـ SMS تلقائياً لهذا المكتب لحين تجديد الباقة لتجنب تراكم الرسائل.";

        $this->sendWhatsAppAlert($adminPhone, $alertMessage);

        if (!empty($office->manager_phone)) {
            $this->sendWhatsAppAlert($office->manager_phone, $alertMessage);
        }
    }

    /**
     * البحث عن المكتب حسب رقم شريحة الإرسال
     */
    protected function resolveOfficeBySender(string $cleanSender): ?Office
    {
        $digits = preg_replace('/[^0-9]/', '', $cleanSender);
        if (empty($digits)) {
            return null;
        }

        $shortSender = substr($digits, -9);

        return Office::where('is_active', true)
            ->where(function ($q) use ($shortSender) {
                $q->where('httpsms_from_phone', 'LIKE', "%{$shortSender}%")
                  ->orWhere('whatsapp_sender_phone', 'LIKE', "%{$shortSender}%");
            })
            ->first();
    }

    /**
     * تحديد نص وصفي لسبب فشل إرسال الرسالة النصية
     */
    public function formatSmsFailureReason(string $errorCode, string $event = ''): string
    {
        $codeUpper = strtoupper(trim($errorCode));
        $eventUpper = strtoupper(trim($event));

        if (str_contains($codeUpper, '108')) {
            return "UNKNOWN:108 - غير معروف بسبب نفاد الرصيد أو انتهاء الباقة";
        }

        if ($eventUpper === 'MESSAGE.SEND.EXPIRED' || str_contains($codeUpper, 'EXPIRED_ON_PHONE') || str_contains($codeUpper, 'EXPIRED')) {
            return "EXPIRED - انتهت المهلة (هاتف المكتب غير متصل أو تطبيق httpSMS متوقف)";
        }

        if (str_contains($codeUpper, 'GENERIC_FAILURE')) {
            return "GENERIC_FAILURE - فشل عام من الشبكة أو رقم المستلم غير صالح";
        }

        if (str_contains($codeUpper, 'NO_SERVICE') || str_contains($codeUpper, 'RADIO_OFF')) {
            $matched = str_contains($codeUpper, 'NO_SERVICE') ? 'NO_SERVICE' : 'RADIO_OFF';
            return "{$matched} - لا توجد تغطية شبكة في شريحة المكتب";
        }

        $codeDisplay = $errorCode !== '' ? $errorCode : ($event !== '' ? $event : 'UNKNOWN_ERROR');
        return "{$codeDisplay} - فشل إرسال الرسالة النصية";
    }
}