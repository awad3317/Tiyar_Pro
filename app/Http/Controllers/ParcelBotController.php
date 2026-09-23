<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Services\HttpSmsService;
use App\Models\Office;

class ParcelBotController extends Controller
{
    protected HttpSmsService $smsService;

    public function __construct(HttpSmsService $smsService)
    {
        $this->smsService = $smsService;
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

            // 1. تحديد ما إذا كانت الرسالة صادرة من صاحب الحساب (IsFromMe)
            $isFromMe = filter_var(
                $data['Info']['IsFromMe'] 
                ?? $data['key']['fromMe'] 
                ?? $data['isFromMe'] 
                ?? false, 
                FILTER_VALIDATE_BOOLEAN
            );

            // 2. استخراج رقم المحادثة / الراسل الحقيقي
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

            // تنظيف رقم هاتف الراسل (المكتب/الموظف)
            $cleanJid = explode('@', $rawPhone)[0];
            $cleanJid = explode(':', $cleanJid)[0];
            $senderPhone = preg_replace('/[^0-9]/', '', $cleanJid);

            // =========================================================
            // 📦 استخراج بيانات الطرد (بدون AI)
            // =========================================================
            $parcelData = $this->extractParcelInfo($messageText);

            // إذا لم تحتوِ الرسالة على بيانات الطرد، يتم تجاهلها
            if ($parcelData === null) {
                return response()->json([
                    'status' => 'ignored_not_a_parcel',
                    'data'   => null
                ]);
            }

            // =========================================================
            // 🏢 التحقق من هوية المكتب وصلاحيته من قاعدة البيانات
            // =========================================================
            $office = Office::where('whatsapp_sender_phone', 'LIKE', "%{$senderPhone}%")
                            ->where('is_active', true)
                            ->first();

            if (!$office) {
                Log::warning("Unauthorized office sender: {$senderPhone}");
                $this->sendWhatsAppMessage($senderPhone, "⚠️ عذراً، رقمك غير مسجل ضمن المكاتب المصرح لها بإرسال الرسائل عبر النظام.");
                return response()->json([
                    'status'  => 'unauthorized_sender',
                    'message' => 'Office not found or inactive'
                ], 403);
            }

            // إرسال رسالة SMS عبر البوابة ببيانات المكتب الخاصة به
            $smsResult = $this->sendParcelSms($parcelData, $office);

            // إرسال رد تأكيدي في محادثة الواتساب
            $this->sendWhatsAppMessage($senderPhone, $smsResult['reply']);

            return response()->json([
                'status'  => 'parcel_sms_processed',
                'success' => $smsResult['success'],
                'office'  => $office->name,
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
    /**
     * استخراج رقم المستلم ونوع الطرد فقط
     */
    protected function extractParcelInfo(?string $text): ?array
    {
        if (empty($text)) {
            return null;
        }

        $delimiters = [',', '،', '-', "\n", '|'];
        $normalized = str_replace($delimiters, '#', trim($text));
        $parts = array_values(array_filter(array_map('trim', explode('#', $normalized))));

        // يجب أن تحتوي الرسالة على عنصرين على الأقل: (الرقم ونوع الطرد)
        if (count($parts) < 2) {
            return null;
        }

        $recipientPhone = preg_replace('/[^0-9]/', '', $parts[0]);
        if (strlen($recipientPhone) < 8) {
            return null;
        }

        $packageType = $parts[1];

        if (empty($packageType)) {
            return null;
        }

        return [
            'recipient'    => $recipientPhone,
            'package_type' => $packageType,
        ];
    }

    
    protected function sendParcelSms(array $parcel, Office $office): array
    {
        // القالب الافتراضي في حال لم يحدد المكتب قالباً خاصاً به
        $defaultTemplate = "عميلنا العزيز،\nمكتب: {office} ({branch})\nتم استلام طردكم: {package}\nيرجى التوجه للفرع للاستلام. شكراً لتعاملكم معنا.";

        $template = !empty($office->sms_template) ? $office->sms_template : $defaultTemplate;

        // استبدال المتغيرات بالبيانات الفعلية
        $smsBody = str_replace(
            ['{office}', '{branch}', '{package}'],
            [
                $office->name,
                $office->branch_name ?? '',
                $parcel['package_type']
            ],
            $template
        );

        // تنظيف أي أقواس فارغة إذا لم يكن هناك اسم فرع
        $smsBody = str_replace(' ()', '', $smsBody);

        $result = $this->smsService->send(
            $parcel['recipient'],
            $smsBody,
            $office->httpsms_api_key,
            $office->httpsms_from_phone
        );

        if ($result['success']) {
            return [
                'success' => true,
                'reply'   => "✅ تم إرسال رسالة SMS للعميل بنجاح!\n🏢 المكتب: {$office->name}\n📱 الرقم: {$parcel['recipient']}\n📦 الطرد: {$parcel['package_type']}"
            ];
        }

        return [
            'success' => false,
            'reply'   => "❌ فشل إرسال رسالة الـ SMS إلى ({$parcel['recipient']}). تأكد من رصيد شريحة فرع [{$office->name}] أو اتصال الهاتف بالإنترنت."
        ];
    }

    /**
     * إرسال رسالة نصية عبر Evolution API للواتساب
     */
    protected function sendWhatsAppMessage(string $phone, string $message): bool
    {
        try {
            $evolutionUrl = rtrim(config('services.evolution.url'), '/');
            $apiKey       = config('services.evolution.api_key');
            $url = "{$evolutionUrl}/send/text";

            $formattedPhone = preg_replace('/[^0-9]/', '', $phone);

            // التأكد من إضافة رمز الدولة لليمن إذا كان الرقم 9 خانات
            if (!str_starts_with($formattedPhone, '967') && strlen($formattedPhone) == 9) {
                $formattedPhone = '967' . $formattedPhone;
            }

            $randomDelay = rand(1500, 2000);

            $response = Http::withHeaders([
                'apikey'       => $apiKey,
                'Content-Type' => 'application/json',
            ])->post($url, [
                'number'  => $formattedPhone,
                'text'    => $message,
                'delay'   => $randomDelay,
                'options' => [
                    'presence' => 'composing',
                ],
            ]);

            if ($response->successful()) {
                Log::info("WhatsApp reply sent successfully to {$formattedPhone}");
                return true;
            }

            Log::error("WhatsApp SendText Error: " . $response->body());
            return false;

        } catch (\Exception $e) {
            Log::error("WhatsApp SendText Exception: " . $e->getMessage());
            return false;
        }
    }
}