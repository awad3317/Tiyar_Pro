<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Office;

class HttpSmsWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->all();
        $event = $payload['event'] ?? ($payload['type'] ?? null);
        $data = $payload['data'] ?? $payload;

        Log::info("httpSMS Universal Webhook Received: {$event}", ['data' => $data]);

        // استخراج رقم هاتف المرسل (المكتب) وتنظيفه
        $senderPhone = $data['phone_number'] ?? ($data['owner'] ?? ($data['from'] ?? null));
        $cleanSender = $senderPhone ? preg_replace('/[^0-9]/', '', $senderPhone) : null;

        // =========================================================
        // 1. مراقبة حالة اتصال الهاتف (Online / Offline)
        // =========================================================
        if ($event === 'phone.heartbeat.online') {
            if ($cleanSender) {
                Cache::put("httpsms_status_{$cleanSender}", 'online', now()->addHours(24));
                Log::info("httpSMS: Office Phone {$cleanSender} is ONLINE.");
            }
            return response()->json(['status' => 'acknowledged', 'state' => 'online']);
        }

        if ($event === 'phone.heartbeat.offline') {
            if ($cleanSender) {
                Cache::put("httpsms_status_{$cleanSender}", 'offline', now()->addHours(24));
                Log::warning("httpSMS: Office Phone {$cleanSender} is OFFLINE.");
            }
            return response()->json(['status' => 'acknowledged', 'state' => 'offline']);
        }

        // =========================================================
        // 2. إذا نجح الإرسال أو تم التسليم: تسجيل النجاح وتصفير المحاولات
        // =========================================================
        if (in_array($event, ['message.phone.sent', 'message.phone.delivered'])) {
            $recipient = $data['contact'] ?? ($data['to'] ?? null);
            if ($recipient && $cleanSender) {
                $cleanRecipient = preg_replace('/[^0-9]/', '', $recipient);

                // وسم النجاح: يمنع أي محاولة إعادة لاحقة لهذا الرقم خلال 12 ساعة
                Cache::put("sms_delivered_{$cleanSender}_{$cleanRecipient}", true, now()->addHours(12));

                // تصفير عداد المحاولات وقفل الأمان
                Cache::forget("sms_retry_{$cleanSender}_{$cleanRecipient}");
                Cache::forget("sms_lock_{$cleanSender}_{$cleanRecipient}");

                Log::info("SMS successfully sent/delivered to {$recipient}. Cleaned retry state.");
            }
            return response()->json(['status' => 'acknowledged', 'state' => 'delivered']);
        }

        // =========================================================
        // 3. معالجة الرسائل الفاشلة أو منتهية الصلاحية بأمان قطعي
        // =========================================================
        if (in_array($event, ['message.send.failed', 'message.send.expired'])) {
            $recipient = $data['contact'] ?? ($data['to'] ?? null);
            $content = $data['content'] ?? null;
            $sender = $senderPhone;

            if ($recipient && $sender && $content) {
                $cleanRecipient = preg_replace('/[^0-9]/', '', $recipient);

                // الفحص 1: هل الرسالة وصلت بالفعل مسبقاً؟
                if (Cache::has("sms_delivered_{$cleanSender}_{$cleanRecipient}")) {
                    Log::info("Ignoring retry for {$recipient}: Already marked as delivered.");
                    return response()->json(['status' => 'ignored_already_delivered']);
                }

                // الفحص 2: قفل ذري (Atomic Lock) لمنع التكرار اللحظي المتزامن
                $lockKey = "sms_lock_{$cleanSender}_{$cleanRecipient}";
                $lockAcquired = Cache::add($lockKey, true, 30); // قفل لمدة 30 ثانية
                if (!$lockAcquired) {
                    Log::warning("Retry request for {$recipient} is already being processed. Skipped duplicate trigger.");
                    return response()->json(['status' => 'in_progress']);
                }

                $retryKey = "sms_retry_{$cleanSender}_{$cleanRecipient}";
                $retryCount = (int) Cache::get($retryKey, 0);

                $office = $this->resolveOfficeBySender($cleanSender);

                // السماح بمحاولتين كحد أقصى (1 ثم 2 فقط)
                if ($retryCount < 2) {
                    $newCount = $retryCount + 1;
                    Cache::put($retryKey, $newCount, now()->addHours(2));

                    Log::warning("Retrying SMS to {$recipient} via office {$cleanSender} (Attempt #{$newCount})");

                    $this->dispatchSms($office, $sender, $recipient, $content);

                    // تحرير القفل بعد الإرسال
                    Cache::forget($lockKey);

                    return response()->json(['status' => 'retrying', 'attempt' => $newCount]);
                }

                // بعد فشل محاولتين نهائياً:
                Cache::forget($retryKey);
                Cache::forget($lockKey);
                Log::error("SMS to {$recipient} permanently failed on office {$cleanSender}. Marking as unreachable.");

                if ($office) {
                    $this->markParcelAsUnreachable($office->id, $cleanRecipient);
                }
            }
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * البحث عن المكتب حسب رقم شريحة الإرسال
     */
    protected function resolveOfficeBySender($cleanSender)
    {
        if (!$cleanSender) return null;

        return Office::where('phone', 'LIKE', "%{$cleanSender}%")
            ->orWhere('whatsapp_sender_phone', 'LIKE', "%{$cleanSender}%")
            ->orWhere('httpsms_phone', 'LIKE', "%{$cleanSender}%")
            ->first();
    }

    /**
     * إرسال الرسالة باستخدام API Key الخاص بالمكتب
     */
    protected function dispatchSms($office, $from, $to, $content)
    {
        try {
            $apiKey = $office->httpsms_api_key ?? config('services.httpsms.key');
            $apiUrl = config('services.httpsms.url', 'https://api.httpsms.com');

            Http::withHeaders([
                'x-api-key'    => $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(10)->post(rtrim($apiUrl, '/') . '/v1/messages/send', [
                'content' => $content,
                'from'    => $from,
                'to'      => $to,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to re-dispatch SMS for Office {$from}: " . $e->getMessage());
        }
    }

    /**
     * تحديث حالة الطرد في النظام كـ خارج التغطية
     */
    protected function markParcelAsUnreachable($officeId, $cleanRecipient)
    {
        // تحديث جدول الطرود لديك لتثبيت عدم وصول الإشعار
    }
}