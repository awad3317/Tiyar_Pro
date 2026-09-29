<?php

namespace App\Http\Controllers;

use App\Models\Office;
use App\Services\HttpSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
        $event = $request->input('event');
        $data  = $request->input('data', []);

        Log::info("🔔 [STEP 1] Webhook Hit: Event received [{$event}]", [
            'owner'   => $data['owner'] ?? null,
            'contact' => $data['contact'] ?? null,
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
     * منطق إعادة المحاولة (مرتين كحد أقصى)
     */
    protected function handleFailedOrExpiredMessage(array $data, string $event)
    {
        $contact = $data['contact'] ?? null;
        $owner   = $data['owner'] ?? null;
        $content = $data['content'] ?? null;

        Log::info("🔍 [STEP 3] Extracting message payload: Contact=[{$contact}], Owner=[{$owner}]");

        if (!$contact || !$owner || empty($content)) {
            Log::warning("⚠️ [STEP 3] Aborted: Missing required fields (contact, owner, or content).");
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

            // الشرط: يحاول مرتين فقط (المحاولة 1 ثم المحاولة 2)
            if ($attemptCount < 2) {
                $newAttempt = $attemptCount + 1;
                Cache::put($retryKey, $newAttempt, now()->addHours(1));
                Log::info("📈 [STEP 8] Counter incremented to [{$newAttempt}]. Stored in cache for 1 hour.");

                Log::warning("🔄 [STEP 9] Triggering SMS Retry to +{$cleanRecipient} via office {$cleanOwner} (Attempt #{$newAttempt})");

                // جلب المكتب لاستخراج الـ API Key
                Log::info("🏢 [STEP 10] Resolving office from database for sender [{$cleanOwner}]...");
                $office = $this->resolveOfficeBySender($cleanOwner);

                if ($office) {
                    Log::info("🏢 [STEP 10] Office found: [{$office->name}] (ID: {$office->id})");
                } else {
                    Log::error("❌ [STEP 10] Office NOT found in DB matching sender phone [{$cleanOwner}].");
                }

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
                // استنفد المحاولتين وفشلت الثانية -> إهمال تام مع نفسه
                Log::error("🛑 [DROP] SMS to +{$cleanRecipient} reached maximum attempts ({$attemptCount}). Permanently dropping message.");
                
                // مسح العداد لتنظيف الذاكرة
                Cache::forget($retryKey);
                Log::info("🧹 [DROP] Retry counter key [{$retryKey}] cleared from cache.");
            }
        } finally {
            // تحرير القفل الذري
            Cache::forget($lockKey);
            Log::info("🔓 [FINALLY] Atomic lock [{$lockKey}] released.");
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