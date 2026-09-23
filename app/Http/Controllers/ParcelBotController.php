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
            // 📦 استخراج قائمة الطرود (يدعم طرد واحد أو عدة طرود)
            // =========================================================
            $parcels = $this->extractParcelsList($messageText);

            // إذا لم تحتوِ الرسالة على أي بيانات طرد صالحة
            if (empty($parcels)) {
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

            // =========================================================
            // 🚀 معالجة وإرسال الـ SMS لجميع الطرود دفعة واحدة
            // =========================================================
            $successCount = 0;
            $failedCount  = 0;
            $details      = [];

            // قالب المكتب أو القالب الافتراضي
            $defaultTemplate = "عميلنا العزيز،\nمكتب: {office} ({branch})\nتم استلام طردكم: {package}\nيرجى التوجه للفرع للاستلام. شكراً لتعاملكم معنا.";
            $template = !empty($office->sms_template) ? $office->sms_template : $defaultTemplate;

            foreach ($parcels as $parcel) {
                $smsBody = str_replace(
                    ['{office}', '{branch}', '{package}'],
                    [
                        $office->name,
                        $office->branch_name ?? '',
                        $parcel['package_type']
                    ],
                    $template
                );

                // تنظيف أي أقواس فارغة في حال عدم وجود اسم للفرع
                $smsBody = str_replace(' ()', '', $smsBody);

                // إرسال الـ SMS عبر البوابة
                $result = $this->smsService->send(
                    $parcel['recipient'],
                    $smsBody,
                    $office->httpsms_api_key,
                    $office->httpsms_from_phone
                );

                if ($result['success']) {
                    $successCount++;
                    $details[] = "✅ {$parcel['recipient']} ({$parcel['package_type']})";
                } else {
                    $failedCount++;
                    $details[] = "❌ {$parcel['recipient']} (فشل الإرسال)";
                }

                // تأخير بسيط (0.4 ثانية) لحماية الشريحة وبوابة الأندرويد من الحظر والضغط
                usleep(400000);
            }

            // =========================================================
            // 📝 توليد التقرير والرد في الواتساب
            // =========================================================
            $total = count($parcels);

            if ($total === 1) {
                // إذا كان طرداً واحداً، إرسال رسالة تأكيد مختصرة
                if ($successCount === 1) {
                    $reply = "✅ تم إرسال رسالة SMS للعميل بنجاح!\n🏢 المكتب: {$office->name}\n📱 الرقم: {$parcels[0]['recipient']}\n📦 الطرد: {$parcels[0]['package_type']}";
                } else {
                    $reply = "❌ فشل إرسال رسالة الـ SMS إلى ({$parcels[0]['recipient']}). تأكد من رصيد شريحة فرع [{$office->name}] أو اتصال الهاتف بالإنترنت.";
                }
            } else {
                // إذا كانت طرود متعددة، إرسال تقرير إحصائي مفصل
                $reply = "📊 *تقرير إرسال الإشعارات ({$office->name})*\n"
                       . "━━━━━━━━━━━━━━━\n"
                       . "📦 إجمالي الطرود: {$total}\n"
                       . "✅ الناجحة: {$successCount}\n"
                       . ($failedCount > 0 ? "⚠️ الفاشلة: {$failedCount}\n" : "")
                       . "━━━━━━━━━━━━━━━\n"
                       . implode("\n", $details);
            }

            $this->sendWhatsAppMessage($senderPhone, $reply);

            return response()->json([
                'status'  => 'bulk_parcels_processed',
                'office'  => $office->name,
                'total'   => $total,
                'success' => $successCount,
                'failed'  => $failedCount
            ]);

        } catch (\Exception $e) {
            Log::error("Parcel SMS Webhook Error: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * استخراج قائمة الطرود من النص (يدعم طرد واحد أو طرود متعددة في أسطر)
     */
    protected function extractParcelsList(?string $text): array
    {
        if (empty(trim($text))) {
            return [];
        }

        // تقسيم النص بناءً على الأسطر (Enter)
        $lines = preg_split('/\r\n|\r|\n/', trim($text));
        $parcels = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // البحث عن رقم الهاتف (من 8 إلى 14 رقماً) داخل السطر
            if (preg_match('/(\+?[0-9]{8,14})/', $line, $matches)) {
                $rawPhone = $matches[1];
                $recipientPhone = preg_replace('/[^0-9]/', '', $rawPhone);

                // استخراج نوع الطرد بحذف رقم الهاتف وتنظيف الرموز والفواصل
                $packageType = str_replace($rawPhone, '', $line);
                $packageType = trim(preg_replace('/^[\s\-\,\،\|]+|[\s\-\,\،\|]+$/u', '', $packageType));

                if (!empty($packageType)) {
                    $parcels[] = [
                        'recipient'    => $recipientPhone,
                        'package_type' => $packageType,
                    ];
                }
            }
        }

        return $parcels;
    }

    /**
     * إرسال رسالة نصية عبر Evolution API للواتساب
     */
    protected function sendWhatsAppMessage(string $phone, string $message): bool
    {
        try {
            $evolutionUrl = rtrim(config('services.evolution.url'), '/');
            $apiKey       = config('services.evolution.api_key');
            $url          = "{$evolutionUrl}/send/text";

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