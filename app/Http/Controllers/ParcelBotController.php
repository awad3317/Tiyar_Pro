<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
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

            // تجاهل الرسائل الصادرة من نفس رقم البوت لتجنب التكرار
            if ($isFromMe) {
                return response()->json(['status' => 'ignored_self_message']);
            }

            // 2. استخراج رقم الراسل الحقيقي
            $rawPhone = $data['Info']['Sender'] 
                ?? $data['Info']['Chat'] 
                ?? $data['key']['remoteJid'] 
                ?? null;

            // 3. استخراج العقدة الخاصة بالرسالة
            $msgNode = $data['Message'] ?? $data['message'] ?? [];

            // استخراج النص أو الـ Caption المصاحب للصورة
            $messageText = trim(
                $msgNode['conversation'] 
                ?? $msgNode['extendedTextMessage']['text'] 
                ?? $msgNode['imageMessage']['caption'] 
                ?? $msgNode['buttonsResponseMessage']['selectedDisplayText'] 
                ?? $msgNode['templateButtonReplyMessage']['selectedDisplayText'] 
                ?? ''
            );

            // فحص هل الرسالة تحتوي على صورة
            $isImage = isset($msgNode['imageMessage']);

            if (!$rawPhone || (empty($messageText) && !$isImage)) {
                return response()->json(['status' => 'ignored_empty']);
            }

            // تنظيف رقم هاتف الراسل
            $cleanJid = explode('@', $rawPhone)[0];
            $cleanJid = explode(':', $cleanJid)[0];
            $senderPhone = preg_replace('/[^0-9]/', '', $cleanJid);

            // =========================================================
            // 🏢 1. التحقق من هوية المكتب (إذا كان غريباً نتجاهله بصمت)
            // =========================================================
            $office = Office::where('whatsapp_sender_phone', 'LIKE', "%{$senderPhone}%")
                            ->where('is_active', true)
                            ->first();

            // إذا كان الرقم غير مسجل، يتم التجاهل التام دون إرسال أي رد
            if (!$office) {
                Log::info("Ignored message from unauthorized phone: {$senderPhone}");
                return response()->json(['status' => 'ignored_unauthorized_sender']);
            }

            // =========================================================
            // ✅ 2. فحص أوامر التأكيد والإلغاء للطرود المعلقة
            // =========================================================
            $pendingCacheKey = "pending_parcels_{$senderPhone}";

            // حالة الإلغاء
            if (in_array(mb_strtolower($messageText), ['الغاء', 'إلغاء', 'cancel'])) {
                if (Cache::has($pendingCacheKey)) {
                    Cache::forget($pendingCacheKey);
                    $this->sendWhatsAppMessage($senderPhone, "❌ تم إلغاء العملية ولم يتم إرسال أي رسائل SMS.");
                    return response()->json(['status' => 'cancelled']);
                }
            }

            // حالة التأكيد
            if (in_array(mb_strtolower($messageText), ['نعم', 'تاكيد', 'تأكيد', 'ارسل', 'أرسل', 'ok', 'yes'])) {
                $pendingParcels = Cache::get($pendingCacheKey);

                if (!empty($pendingParcels)) {
                    Cache::forget($pendingCacheKey);
                    return $this->dispatchParcelsSms($senderPhone, $pendingParcels, $office);
                }
            }

            // =========================================================
            // 🤖 3. معالجة الصور عبر الذكاء الاصطناعي (فقط مع كلمة طرود)
            // =========================================================
            if ($isImage) {
                // إذا أرسل صورة بدون كلمة "طرود"، يتم تجاهلها ليتعامل معها الدعم الفني
                if (!Str::contains($messageText, ['طرود', 'طرد'])) {
                    return response()->json(['status' => 'ignored_normal_image_for_support']);
                }

                $usageCacheKey = "ai_usage_{$senderPhone}_" . date('Y-m-d');
                $usageCount = Cache::get($usageCacheKey, 0);

                if ($usageCount >= 2) {
                    $limitMsg = "⚠️ استنفدت الحد اليومي المسموح به لاستخدام الذكاء الاصطناعي (مرتين باليوم).\n💡 يمكنك إرسال الطرود كنص عادي وسيعالجها النظام فوراً.";
                    $this->sendWhatsAppMessage($senderPhone, $limitMsg);
                    return response()->json(['status' => 'ai_limit_reached']);
                }

                $this->sendWhatsAppMessage($senderPhone, "⏳ جاري قراءة الكشف واستخراج الطرود بواسطة الذكاء الاصطناعي (المحاولة " . ($usageCount + 1) . " من 2)...");

                $imageBase64 = $this->fetchImageBase64($data);

                if (!$imageBase64) {
                    $this->sendWhatsAppMessage($senderPhone, "❌ تعذر تحميل الصورة، يرجى إعادة إرسالها بوضوح.");
                    return response()->json(['status' => 'failed_to_download_image']);
                }

                $aiExtractedText = $this->extractParcelsFromImageWithGemini($imageBase64);

                if (empty($aiExtractedText)) {
                    $this->sendWhatsAppMessage($senderPhone, "⚠️ لم يتم العثور على أرقام هواتف أو طرود واضحة في الصورة.");
                    return response()->json(['status' => 'ai_no_parcels_found']);
                }

                // تسجيل الاستخدام حتى نهاية اليوم
                $secondsUntilEndOfDay = now()->diffInSeconds(now()->endOfDay());
                Cache::put($usageCacheKey, $usageCount + 1, $secondsUntilEndOfDay);

                $messageText = $aiExtractedText;
            }

            // =========================================================
            // 📦 4. استخراج بيانات الطرود والتحقق منها
            // =========================================================
            $parcels = $this->extractParcelsList($messageText);

            // إذا كانت الرسالة نصاً عادياً (محادثة عامة مثل "كيف الحال")، يتم التجاهل تماماً ليرد الدعم الفني
            if (empty($parcels)) {
                Log::info("Ignored non-parcel message from {$senderPhone}: '{$messageText}' (Left for human support)");
                return response()->json(['status' => 'ignored_normal_text_for_support']);
            }

            // =========================================================
            // 🛑 5. حفظ البيانات مؤقتاً وإرسال المعاينة للتأكيد
            // =========================================================
            Cache::put($pendingCacheKey, $parcels, now()->addMinutes(5));

            $total = count($parcels);
            $previewList = [];
            foreach ($parcels as $index => $item) {
                $num = $index + 1;
                $previewList[] = "{$num}. 📱 {$item['recipient']} 📦 {$item['package_type']}";
            }

            $confirmMsg = "📋 *مراجعة بيانات الإرسال ({$office->name})*\n"
                        . "━━━━━━━━━━━━━━━\n"
                        . implode("\n", $previewList) . "\n"
                        . "━━━━━━━━━━━━━━━\n"
                        . "📦 إجمالي الطرود: *{$total}*\n\n"
                        . "للإرسال، رد بكلمة: *تأكيد* أو *نعم*\n"
                        . "للإلغاء، رد بكلمة: *إلغاء*";

            $this->sendWhatsAppMessage($senderPhone, $confirmMsg);

            return response()->json([
                'status'  => 'awaiting_confirmation',
                'office'  => $office->name,
                'total'   => $total
            ]);

        } catch (\Exception $e) {
            Log::error("Parcel SMS Webhook Error: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * إرسال رسائل الـ SMS للطرود وتوليد التقرير النهائي
     */
    protected function dispatchParcelsSms(string $senderPhone, array $parcels, Office $office)
    {
        $this->sendWhatsAppMessage($senderPhone, "🚀 تم التأكيد! جاري إرسال رسائل الـ SMS للعملاء...");

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

            usleep(400000); // تأخير 0.4 ثانية لحماية الشريحة
        }

        $total = count($parcels);
        $report = "📊 *تقرير الإرسال النهائي ({$office->name})*\n"
                . "━━━━━━━━━━━━━━━\n"
                . "📦 إجمالي الطرود: {$total}\n"
                . "✅ الناجحة: {$successCount}\n"
                . ($failedCount > 0 ? "⚠️ الفاشلة: {$failedCount}\n" : "")
                . "━━━━━━━━━━━━━━━\n"
                . implode("\n", $details);

        $this->sendWhatsAppMessage($senderPhone, $report);

        return response()->json([
            'status'  => 'bulk_parcels_processed',
            'office'  => $office->name,
            'total'   => $total,
            'success' => $successCount,
            'failed'  => $failedCount
        ]);
    }

    /**
     * استخراج الطرود من الصورة عبر Gemini Flash
     */
    protected function extractParcelsFromImageWithGemini(string $imageBase64): ?string
    {
        try {
            $apiKey = trim(config('services.gemini.api_key') ?: env('GEMINI_API_KEY'));
            if (!$apiKey) {
                Log::error("Gemini API Key is missing");
                return null;
            }

            // استخدام الموديلات المتاحة والنشطة في حسابك
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}";

            $prompt = "You are an OCR expert specializing in handwritten Arabic delivery manifests.\n"
                    . "Look at the table in the image:\n"
                    . "Extract each row's recipient phone number from the column 'رقم المستلم' and package description from 'نوع الطرد'.\n"
                    . "Rules:\n"
                    . "1. Convert any Arabic/Indic numerals (like ٧٧٢٤٥٠١٦٦) to standard English numbers (772450166).\n"
                    . "2. Ensure the phone number starts with 7 and is 9 digits long.\n"
                    . "3. Output each parcel on a separate line in this exact format: PHONE PACKAGE_TYPE\n"
                    . "Example format:\n"
                    . "772450166 ظرف\n"
                    . "773111225 كيس\n"
                    . "Do not write any introductory text, markdown, or explanations. Only output the lines or the word NONE.";

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->timeout(40)->post($url, [
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
                    'temperature' => 0.1,
                ]
            ]);

            // في حال واجه أي بطء نقوم بتجربة الموديل البديل المتاح في حسابك
            if (!$response->successful()) {
                $fallbackUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-lite-latest:generateContent?key={$apiKey}";
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])->timeout(40)->post($fallbackUrl, [
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
                        'temperature' => 0.1,
                    ]
                ]);
            }

            if ($response->successful()) {
                $resultText = trim($response->json('candidates.0.content.parts.0.text') ?? '');
                Log::info("Gemini OCR Extracted Content:\n" . $resultText);

                if (empty($resultText) || Str::contains(Str::upper($resultText), 'NONE')) {
                    return null;
                }

                // تحويل أي أرقام مشرقية إلى إنجليزية
                $easternDigits = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
                $westernDigits = ['0','1','2','3','4','5','6','7','8','9'];
                $resultText = str_replace($easternDigits, $westernDigits, $resultText);

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
     * جلب وتحويل الصورة إلى Base64 من الـ Payload
     */
    protected function fetchImageBase64(array $data): ?string
    {
        $msgNode = $data['Message'] ?? $data['message'] ?? [];
        if (!empty($msgNode['base64'])) {
            return preg_replace('#^data:image/\w+;base64,#i', '', $msgNode['base64']);
        }

        if (!empty($data['base64'])) {
            return preg_replace('#^data:image/\w+;base64,#i', '', $data['base64']);
        }

        if (!empty($msgNode['imageMessage']['base64'])) {
            return preg_replace('#^data:image/\w+;base64,#i', '', $msgNode['imageMessage']['base64']);
        }

        Log::error("fetchImageBase64: Base64 string not found in Message node.");
        return null;
    }

    /**
     * استخراج قائمة الطرود من النص
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

            // البحث عن رقم هاتف صحيح يبدأ بـ 7 ومكون من 9 خانات (أو مع مفتاح الدولة 967)
            if (preg_match('/(967)?(7[0-9]{8})/', $line, $matches)) {
                $recipientPhone = $matches[2]; // أخذ الرقم المحلي (9 خانات تبدأ بـ 7)

                // استخراج نوع الطرد بحذف الرقم والرموز الفاصلة
                $packageType = str_replace($matches[0], '', $line);
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