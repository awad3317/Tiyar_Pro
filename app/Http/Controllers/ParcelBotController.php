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

            // 3. استخراج العقدة الخاصة بالرسالة
            $msgNode = $data['Message'] ?? $data['message'] ?? [];

            // استخراج النص أو الـ Caption المصاحب للصورة
            $messageText = $msgNode['conversation'] 
                ?? $msgNode['extendedTextMessage']['text'] 
                ?? $msgNode['imageMessage']['caption']
                ?? $msgNode['buttonsResponseMessage']['selectedDisplayText']
                ?? $msgNode['templateButtonReplyMessage']['selectedDisplayText']
                ?? '';

            // فحص هل الرسالة تحتوي على صورة
            $isImage = isset($msgNode['imageMessage']);

            if (!$rawPhone || (empty(trim($messageText)) && !$isImage)) {
                return response()->json(['status' => 'ignored_empty', 'data' => null]);
            }

            // تنظيف رقم هاتف الراسل (المكتب/الموظف)
            $cleanJid = explode('@', $rawPhone)[0];
            $cleanJid = explode(':', $cleanJid)[0];
            $senderPhone = preg_replace('/[^0-9]/', '', $cleanJid);

            // =========================================================
            // 🤖 معالجة الصور عبر Gemini 1.5 Flash (بشرط وجود كلمة "طرود")
            // =========================================================
            if ($isImage) {
                // فحص الكلمة المفتاحية لتوفير التوكن
                if (!Str::contains($messageText, ['طرود', 'طرد'])) {
                    Log::info("Image received without 'طرود' keyword, skipped AI.");
                    return response()->json(['status' => 'ignored_image_without_keyword']);
                }

                $this->sendWhatsAppMessage($senderPhone, "⏳ جاري قراءة الكشف واستخراج الطرود بواسطة الذكاء الاصطناعي...");

                // استخراج الصورة من Evolution API وتحويلها لـ Base64
                $imageBase64 = $this->fetchImageBase64($data);

                if (!$imageBase64) {
                    $this->sendWhatsAppMessage($senderPhone, "❌ تعذر تحميل الصورة، يرجى إعادة إرسالها بوضوح.");
                    return response()->json(['status' => 'failed_to_download_image']);
                }

                // تحليل الصورة باستخدام Gemini Flash
                $aiExtractedText = $this->extractParcelsFromImageWithGemini($imageBase64);

                if (empty($aiExtractedText)) {
                    $this->sendWhatsAppMessage($senderPhone, "⚠️ لم يتم العثور على أرقام هواتف أو طرود واضحة في الصورة.");
                    return response()->json(['status' => 'ai_no_parcels_found']);
                }

                // نعتمد النص المستخرج من الصورة لمواصلة المعالجة
                $messageText = $aiExtractedText;
            }

            // =========================================================
            // 📦 استخراج قائمة الطرود (طرد واحد أو كشف متعدد)
            // =========================================================
            $parcels = $this->extractParcelsList($messageText);

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
            // 🚀 إرسال الـ SMS لجميع الطرود دفعة واحدة
            // =========================================================
            $successCount = 0;
            $failedCount  = 0;
            $details      = [];

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

                $smsBody = str_replace(' ()', '', $smsBody);

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

                // تأخير بسيط (0.4 ثانية) لحماية الشريحة وبوابة الأندرويد
                usleep(400000);
            }

            // =========================================================
            // 📝 توليد التقرير والرد في الواتساب
            // =========================================================
            $total = count($parcels);

            if ($total === 1) {
                if ($successCount === 1) {
                    $reply = "✅ تم إرسال رسالة SMS للعميل بنجاح!\n🏢 المكتب: {$office->name}\n📱 الرقم: {$parcels[0]['recipient']}\n📦 الطرد: {$parcels[0]['package_type']}";
                } else {
                    $reply = "❌ فشل إرسال رسالة الـ SMS إلى ({$parcels[0]['recipient']}). تأكد من رصيد شريحة فرع [{$office->name}] أو اتصال الهاتف بالإنترنت.";
                }
            } else {
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
     * استخراج الطرود من الصورة عبر Gemini 1.5 Flash
     */
    protected function extractParcelsFromImageWithGemini(string $imageBase64): ?string
    {
        try {
            $apiKey = config('services.gemini.api_key');
            if (!$apiKey) {
                Log::error("Gemini API Key is missing in config/services.php");
                return null;
            }

            // استخدام موديل Gemini 1.5 Flash
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}";

            $prompt = "قم باستخراج بيانات الشحنات والطرود من هذه الصورة (قد تكون سندات، كشوفات أو فواتير شحن).\n"
                    . "المطلوب حصراً: استخراج (رقم هاتف المستلم) و (نوع الطرد/الوصف).\n"
                    . "أخرج كل طرد في سطر مستقل بالصيغة التالية فقط دون أي كلام جانبي أو مقدمات:\n"
                    . "[رقم الهاتف] [نوع الطرد]\n"
                    . "مثال:\n"
                    . "779525898 كرتون ملابس\n"
                    . "771234567 كيس قطع غيار\n"
                    . "إذا لم تجد أي طرد أو رقم، أرجع كلمة: NONE فقط.";

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                            [
                                'inline_data' => [
                                    'mime_type' => 'image/jpeg',
                                    'data'      => $imageBase64
                                ]
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.1, // دقة عالية لتقليل الهلوسة
                ]
            ]);

            if ($response->successful()) {
                $resultText = trim($response->json('candidates.0.content.parts.0.text') ?? '');
                
                if (Str::upper($resultText) === 'NONE' || empty($resultText)) {
                    return null;
                }

                return $resultText;
            }

            Log::error("Gemini Vision API Error: " . $response->body());
            return null;

        } catch (\Throwable $e) {
            Log::error("Gemini Vision Exception: " . $e->getMessage());
            return null;
        }
    }

    /**
     * جلب وتحويل الصورة إلى Base64 من الـ Payload أو من سيرفر Evolution
     */
    protected function fetchImageBase64(array $data): ?string
    {
        // 1. إذا كانت الصورة ممررة كـ base64 مباشرة داخل الـ payload
        if (!empty($data['base64'])) {
            return preg_replace('#^data:image/\w+;base64,#i', '', $data['base64']);
        }

        // 2. إذا كانت Evolution ترسل رابط وسائط (mediaUrl)
        $msgNode = $data['Message'] ?? $data['message'] ?? [];
        $mediaUrl = $msgNode['imageMessage']['url'] ?? null;

        if ($mediaUrl && filter_var($mediaUrl, FILTER_VALIDATE_URL)) {
            $imgContent = @file_get_contents($mediaUrl);
            if ($imgContent) {
                return base64_encode($imgContent);
            }
        }

        // 3. طلب تحميل الوسائط عبر مسار Evolution API الداخلي
        try {
            $evolutionUrl = rtrim(config('services.evolution.url', env('EVOLUTION_API_URL')), '/');
            $apiKey       = config('services.evolution.api_key', env('EVOLUTION_API_KEY'));
            $instanceName = env('EVOLUTION_INSTANCE_NAME', 'awad');

            $messageId = $data['key']['id'] ?? null;
            if (!$messageId) {
                return null;
            }

            $response = Http::withHeaders([
                'apikey'       => $apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$evolutionUrl}/chat/getBase64FromMediaMessage/{$instanceName}", [
                'message' => [
                    'key' => [
                        'id' => $messageId
                    ]
                ],
                'convertToMp4' => false
            ]);

            if ($response->successful() && !empty($response->json('base64'))) {
                return preg_replace('#^data:image/\w+;base64,#i', '', $response->json('base64'));
            }

        } catch (\Throwable $e) {
            Log::error("Failed to fetch base64 from evolution: " . $e->getMessage());
        }

        return null;
    }

    /**
     * استخراج قائمة الطرود من النص (يدعم طرد واحد أو طرود متعددة في أسطر)
     */
    protected function extractParcelsList(?string $text): array
    {
        if (empty(trim($text))) {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($text));
        $parcels = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            if (preg_match('/(\+?[0-9]{8,14})/', $line, $matches)) {
                $rawPhone = $matches[1];
                $recipientPhone = preg_replace('/[^0-9]/', '', $rawPhone);

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
            $evolutionUrl = rtrim(config('services.evolution.url', env('EVOLUTION_API_URL')), '/');
            $apiKey       = config('services.evolution.api_key', env('EVOLUTION_API_KEY'));
            $url          = "{$evolutionUrl}/send/text";

            $formattedPhone = preg_replace('/[^0-9]/', '', $phone);

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