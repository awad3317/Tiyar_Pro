<?php

namespace App\Http\Controllers;

use App\Models\Office;
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
     * تسجيل الرسالة كمسلّمة لمنع أي Retry لاحق لنفس الطرد
     */
    protected function markMessageAsDelivered(array $data, string $event): void
    {
        $contact = $data['contact'] ?? null;
        $owner   = $data['owner'] ?? null;
        $content = $data['content'] ?? '';

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
    }

    /**
     * منطق إعادة المحاولة الذكي مع الحماية من التكرار وفترة التهدئة
     */
    protected function handleFailedOrExpiredMessage(array $data, string $event): void
    {
        $contact = $data['contact'] ?? null;
        $owner   = $data['owner'] ?? null;
        $content = $data['content'] ?? null;

        Log::info("🔍 [STEP 3] Extracting message payload: Contact=[{$contact}], Owner=[{$owner}]");

        if (!$contact || !$owner || empty($content)) {
            Log::warning("⚠ [STEP 3] Aborted: Missing required fields (contact, owner, or content).");
            return;
        }

        $cleanRecipient = preg_replace('/[^0-9]/', '', $contact);
        $cleanOwner     = preg_replace('/[^0-9]/', '', $owner);
        $contentHash    = md5(trim($content));

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
        ]);

        // 1. التحقق هل وصلت الرسالة أو خرجت مسبقاً
        if (Cache::has($deliveredKey)) {
            Log::info("🛑 [STEP 5] Skipped: Message was already delivered/sent for +{$cleanRecipient}.");
            return;
        }

        // 2. التحقق من فترة التهدئة لمنع التكرار المتزامن
        if (Cache::has($cooldownKey)) {
            Log::warning("⏳ [COOLDOWN] Skipped: Retry cooldown is active for +{$cleanRecipient}. Awaiting delivery report.");
            return;
        }

        Log::info("✅ [STEP 5] Passed delivery and cooldown check.");

        // 3. قفل ذري يمنع تضارب الويب هوك المتزامن في أجزاء الثانية
        $lockAcquired = Cache::add($lockKey, true, now()->addSeconds(30));
        if (!$lockAcquired) {
            Log::warning("🔒 [STEP 6] Lock Active: Another retry request for +{$cleanRecipient} is currently running.");
            return;
        }
        Log::info("🔓 [STEP 6] Atomic lock successfully acquired for 30s.");

        try {
            $attemptCount = (int) Cache::get($retryKey, 0);
            Log::info("📊 [STEP 7] Current retry count for +{$cleanRecipient} is [{$attemptCount}] out of max 2.");

            Log::info("🏢 [STEP 10] Resolving office from database for sender [{$cleanOwner}]...");
            $office = $this->resolveOfficeBySender($cleanOwner);

            if ($office) {
                Log::info("🏢 [STEP 10] Office found: [{$office->name}] (ID: {$office->id})");
            } else {
                Log::error("❌ [STEP 10] Office NOT found in DB matching sender phone [{$cleanOwner}].");
            }

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
     * البحث عن المكتب حسب رقم شريحة الإرسال
     */
    protected function resolveOfficeBySender(string $cleanSender)
    {
        return Office::where('whatsapp_sender_phone', 'LIKE', "%{$cleanSender}%")
            ->where('is_active', true)
            ->first();
    }
}