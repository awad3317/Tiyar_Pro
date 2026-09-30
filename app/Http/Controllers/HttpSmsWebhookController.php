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

        // معالجة الرسائل التي فشلت أو انتهت صلاحيتها فقط
        if (in_array($event, ['message.send.failed', 'message.send.expired'])) {
            Log::info("⚙️ [STEP 2] Event [{$event}] matches retry policy. Starting evaluation...");
            $this->handleFailedOrExpiredMessage($data, $event);
        } else {
            Log::info("ℹ️ [STEP 2] Event [{$event}] ignored (not failed/expired).");
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * منطق إعادة المحاولة (مرتين كحد أقصى) مع التنبيه الفوري للمسؤول عند الفشل النهائي
     */
    protected function handleFailedOrExpiredMessage(array $data, string $event)
    {
        $contact = $data['contact'] ?? null;
        $owner   = $data['owner'] ?? null;
        $content = $data['content'] ?? null;

        Log::info("🔍 [STEP 3] Extracting message payload: Contact=[{$contact}], Owner=[{$owner}]");

        if (!$contact || !$owner || empty($content)) {
            Log::warning("⚠️️ [STEP 3] Aborted: Missing required fields (contact, owner, or content).");
            return;
        }

        $cleanRecipient = preg_replace('/[^0-9]/', '', $contact);
        $cleanOwner     = preg_replace('/[^0-9]/', '', $owner);

        // مفاتيح الكاش لعزل الحالة لكل رسالة ومستلم
        $retryKey     = "sms_retry_count_{$cleanOwner}_{$cleanRecipient}";
        $lockKey      = "sms_lock_{$cleanOwner}_{$cleanRecipient}";
        $deliveredKey = "sms_delivered_{$cleanOwner}_{$cleanRecipient}";

        Log::info("🔑 [STEP 4] Cache Keys initialized:", [
            'retryKey'     => $retryKey,
            'lockKey'      => $lockKey,
            'deliveredKey' => $deliveredKey,
        ]);

        // 1. إذا كانت الرسالة قد وصلت بالفعل مسبقاً، نتجاهل أي إشعار متأخر
        if (Cache::has($deliveredKey)) {
            Log::info("🛑 [STEP 5] Skipped: Message was previously marked as delivered for +{$cleanRecipient}.");
            return;
        }
        Log::info("✅ [STEP 5] Passed delivery check (Message not yet delivered).");

        // 2. قفل ذري (Atomic Lock) لمنع التكرار في حال أرسل السيرفر طلبين في نفس الثانية
        $lockAcquired = Cache::add($lockKey, true, now()->addSeconds(30));
        if (!$lockAcquired) {
            Log::warning("🔒 [STEP 6] Lock Active: Another retry request for +{$cleanRecipient} is currently running. Skipped duplicate trigger.");
            return;
        }
        Log::info("🔓 [STEP 6] Atomic lock successfully acquired for 30s.");

        try {
            $attemptCount = Cache::get($retryKey, 0);
            Log::info("📊 [STEP 7] Current retry count for +{$cleanRecipient} is [{$attemptCount}] out of max 2.");

            // البحث عن المكتب أولاً ليكون متاحاً سواء في المحاولات أو في تقرير الإسقاط
            Log::info("🏢 [STEP 10] Resolving office from database for sender [{$cleanOwner}]...");
            $office = $this->resolveOfficeBySender($cleanOwner);

            if ($office) {
                Log::info("🏢 [STEP 10] Office found: [{$office->name}] (ID: {$office->id})");
            } else {
                Log::error("❌ [STEP 10] Office NOT found in DB matching sender phone [{$cleanOwner}].");
            }

            // الشرط: يحاول مرتين فقط (المحاولة 1 ثم المحاولة 2)
            if ($attemptCount < 2) {
                $newAttempt = $attemptCount + 1;
                Cache::put($retryKey, $newAttempt, now()->addHours(1));
                Log::info("📈 [STEP 8] Counter incremented to [{$newAttempt}]. Stored in cache for 1 hour.");

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
                // استنفد المحاولتين وفشلت الثانية -> إهمال تام وإرسال تقرير على واتسابك
                Log::error("🛑 [DROP] SMS to +{$cleanRecipient} reached maximum attempts ({$attemptCount}). Permanently dropping message.");
                
                // مسح العداد لتنظيف الذاكرة
                Cache::forget($retryKey);
                Log::info("🧹 [DROP] Retry counter key [{$retryKey}] cleared from cache.");

                // إرسال تقرير المراقبة الفوري لرقمك الشخصي
                $this->notifyAdminFailedSms($office, $cleanRecipient, $cleanOwner, $content, $event);
            }
        } finally {
            // تحرير القفل الذري
            Cache::forget($lockKey);
            Log::info("🔓 [FINALLY] Atomic lock [{$lockKey}] released.");
        }
    }

    /**
     * إرسال تنبيه فوري عبر الواتساب للمسؤول بتفاصيل الرسالة الفاشلة نهائياً
     */
    protected function notifyAdminFailedSms(?Office $office, string $recipient, string $senderPhone, string $content, string $reason)
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