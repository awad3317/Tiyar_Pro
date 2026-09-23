<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Services\HttpSmsService;

class ParcelBotController extends Controller
{
    protected $sessionService;
    protected HttpSmsService $smsService;

    public function __construct(HttpSmsService $smsService)
    {
        $this->smsService = $smsService;
        // اربط خدمة الجلسات هنا إن كانت موجودة لديك
        // $this->sessionService = app(\App\Services\SessionService::class);
    }

    /**
     * نقطة الدخول الرئيسية لاستقبال أحداث Webhook من Evolution API
     */
    public function handleWebhook(Request $request)
    {
        try {
            $payload = $request->all();

            // استخراج جذر البيانات لدعم مختلف هياكل الـ Payload
            $data = $payload['data']['data'] ?? $payload['data'] ?? $payload;

            // 1. تحديد ما إذا كانت الرسالة صادرة من الموظف/البوت (IsFromMe)
            $isFromMe = filter_var(
                $data['Info']['IsFromMe'] 
                ?? $data['key']['fromMe'] 
                ?? $data['isFromMe'] 
                ?? false, 
                FILTER_VALIDATE_BOOLEAN
            );

            // 2. استخراج رقم المحادثة / العميل
            if ($isFromMe) {
                $rawPhone = $data['Info']['Chat'] 
                    ?? $data['key']['remoteJid'] 
                    ?? null;
            } else {
                $rawPhone = $data['Info']['Sender'] 
                    ?? $data['Info']['Chat'] 
                    ?? $data['key']['remoteJid'] 
                    ?? null;
            }

            // 3. استخراج نص الرسالة
            $msgNode = $data['Message'] ?? $data['message'] ?? [];
            $messageText = $msgNode['conversation'] 
                ?? $msgNode['extendedTextMessage']['text'] 
                ?? $msgNode['buttonsResponseMessage']['selectedDisplayText']
                ?? $msgNode['templateButtonReplyMessage']['selectedDisplayText']
                ?? null;

            if (!$rawPhone || empty(trim($messageText))) {
                return response()->json(['status' => 'ignored_empty', 'data' => null]);
            }

            // تنظيف رقم الهاتف (JID)
            $cleanJid = explode('@', $rawPhone)[0];
            $cleanJid = explode(':', $cleanJid)[0];
            $senderPhone = preg_replace('/[^0-9]/', '', $cleanJid);

            // =========================================================
            // 📦 استخراج بيانات الطرد (بدون AI)
            // =========================================================
            $parcelData = $this->extractParcelInfo($messageText);

            // إذا لم تحتوِ الرسالة على بيانات الطرد، إرجاع NULL فوراً
            if ($parcelData === null) {
                return response()->json([
                    'status' => 'ignored_not_a_parcel',
                    'data'   => null
                ]);
            }

            // إرسال رسالة SMS عبر البوابة إلى هاتف المستلم
            $smsResult = $this->sendParcelSms($parcelData);

            // إرسال رد تأكيدي في محادثة الواتساب
            $this->sendWhatsAppMessage($senderPhone, $smsResult['reply'], false);

            return response()->json([
                'status'  => 'parcel_sms_processed',
                'success' => $smsResult['success'],
                'data'    => $parcelData
            ]);

        } catch (\Exception $e) {
            Log::error("Parcel SMS Webhook Error: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * استخراج معلومات الطرد من نص الرسالة
     * تُرجع مصفوفة بالبيانات أو NULL إذا لم تكن البيانات مطابقة
     */
    protected function extractParcelInfo(?string $text): ?array
    {
        if (empty($text)) {
            return null;
        }

        // تفكيك النص بناءً على الفواصل والشرطات والأسطر
        $delimiters = [',', '،', '-', "\n", '|'];
        $normalized = str_replace($delimiters, '#', trim($text));
        $parts = array_values(array_filter(array_map('trim', explode('#', $normalized))));

        // يجب أن تحتوي الرسالة على 3 عناصر: (1) رقم المستلم (2) نوع الطرد (3) رقم السند
        if (count($parts) < 3) {
            return null;
        }

        // التحقق من أن الجزء الأول هو رقم هاتف لا يقل عن 8 أرقام
        $recipientPhone = preg_replace('/[^0-9]/', '', $parts[0]);
        if (strlen($recipientPhone) < 8) {
            return null;
        }

        $packageType   = $parts[1];
        $receiptNumber = $parts[2];

        // في حال كان نوع الطرد أو رقم السند فارغاً
        if (empty($packageType) || empty($receiptNumber)) {
            return null;
        }

        return [
            'recipient'      => $recipientPhone,
            'package_type'   => $packageType,
            'receipt_number' => $receiptNumber
        ];
    }

    /**
     * تجهيز القالب واستدعاء خدمة httpSMS للإرسال
     */
    protected function sendParcelSms(array $parcel): array
    {
        $smsBody = "عميلنا العزيز،\n"
                 . "تم استلام طردكم: {$parcel['package_type']}\n"
                 . "رقم السند: {$parcel['receipt_number']}\n"
                 . "يرجى التوجه للاستلام. شكراً لتعاملكم معنا.";

        $result = $this->smsService->send($parcel['recipient'], $smsBody);

        if ($result['success']) {
            return [
                'success' => true,
                'reply'   => "✅ تم إرسال رسالة SMS للعميل بنجاح!\n📱 الرقم: {$parcel['recipient']}\n📦 الطرد: {$parcel['package_type']}\n🧾 السند: {$parcel['receipt_number']}"
            ];
        }

        return [
            'success' => false,
            'reply'   => "❌ فشل إرسال رسالة الـ SMS إلى ({$parcel['recipient']})."
        ];
    }

    /**
     * إرسال رسالة نصية عبر Evolution API للواتساب
     */
    protected function sendWhatsAppMessage(string $phone, string $message, bool $isReply = false): bool
    {
        try {
            $evolutionUrl = env('EVOLUTION_API_URL', 'http://127.0.0.1:8080');
            $instanceName = env('EVOLUTION_INSTANCE_NAME', 'default');
            $apiKey       = env('EVOLUTION_API_KEY', '');

            $formattedPhone = preg_replace('/[^0-9]/', '', $phone);

            $response = Http::withHeaders([
                'apikey'       => $apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$evolutionUrl}/message/sendText/{$instanceName}", [
                'number' => $formattedPhone,
                'text'   => $message,
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error("Failed to send WhatsApp message: " . $e->getMessage());
            return false;
        }
    }
}