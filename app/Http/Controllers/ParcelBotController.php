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
                return response()->json(['status' => 'ignored_empty', 'data' => null]);
            }

            // تنظيف رقم هاتف الراسل (المكتب/الموظف)
            $cleanJid = explode('@', $rawPhone)[0];
            $cleanJid = explode(':', $cleanJid)[0];
            $senderPhone = preg_replace('/[^0-9]/', '', $cleanJid);

            // =========================================================
            // 🏢 التحقق أولاً من هوية المكتب وصلاحيته من قاعدة البيانات
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
            // ✅ فحص أوامر التأكيد والإلغاء للطرود المعلقة
            // =========================================================
            $pendingCacheKey = "pending_parcels_{$senderPhone}";

            // 1. حالة الإلغاء
            if (in_array(mb_strtolower($messageText), ['الغاء', 'إلغاء', 'cancel'])) {
                if (Cache::has($pendingCacheKey)) {
                    Cache::forget($pendingCacheKey);
                    $this->sendWhatsAppMessage($senderPhone, "❌ تم إلغاء العملية ولم يتم إرسال أي رسائل SMS.");
                    return response()->json(['status' => 'cancelled']);
                }
            }

            // 2. حالة الموافقة والتأكيد على الإرسال
            if (in_array(mb_strtolower($messageText), ['نعم', 'تاكيد', 'تأكيد', 'ارسل', 'أرسل', 'ok', 'yes'])) {
                $pendingParcels = Cache::get($pendingCacheKey);

                if (!empty($pendingParcels)) {
                    Cache::forget($pendingCacheKey); // حذفها فوراً لتجنب التكرار
                    return $this->dispatchParcelsSms($senderPhone, $pendingParcels, $office);
                }
            }

            // =========================================================
            // 🤖 معالجة الصور عبر Gemini 1.5 Flash (بشرط وجود كلمة "طرود" والحد اليومي 2 مرات)
            // =========================================================
            if ($isImage) {
                if (!Str::contains($messageText, ['طرود', 'طرد'])) {
                    Log::info("Image received without 'طرود' keyword, skipped AI.");
                    return response()->json(['status' => 'ignored_image_without_keyword']);
                }

                $usageCacheKey = "ai_usage_{$senderPhone}_" . date('Y-m-d');
                $usageCount = Cache::get($usageCacheKey, 0);

                if ($usageCount >= 2) {
                    $limitMsg = "⚠️ عذراً، لقد استنفدت الحد اليومي المسموح به لاستخدام الذكاء الاصطناعي لقراءة الصور (مرتين في اليوم).\n\n💡 يمكنك إرسال الطرود كنص عادي وسيتم تجهيزها فوراً.";
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

                // زيادة عداد الذكاء الاصطناعي حتى نهاية اليوم
                $secondsUntilEndOfDay = now()->diffInSeconds(now()->endOfDay());
                Cache::put($usageCacheKey, $usageCount + 1, $secondsUntilEndOfDay);

                $messageText = $aiExtractedText;
            }

            // =========================================================
            // 📦 استخراج قائمة الطرود
            // =========================================================
            $parcels = $this->extractParcelsList($messageText);

            if (empty($parcels)) {
                return response()->json([
                    'status' => 'ignored_not_a_parcel',
                    'data'   => null
                ]);
            }

            // =========================================================
            // 🛑 حفظ البيانات في الكاش وإرسال رسالة المعاينة والتأكيد للموظف
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

            usleep(400000); // 0.4 ثانية لحماية الشريحة
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
     * استخراج الطرود من الصورة عبر Gemini 1.5 Flash
     */
    protected function extractParcelsFromImageWithGemini(string $imageBase64): ?string
    {
        try {
            $apiKey = 'AQ.Ab8RN6Lj1UIeo93cacr7m-Wlcmxr4bCP9qluA2DyKH7k9p9_gg';
            if (!$apiKey) {
                Log::error("Gemini API Key is missing");
                return null;
            }

            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}";

            // برومبت دقيق مخصص لكشوفات واستمارات مكاتب النقل اليمنية المكتوبة بخط اليد
            $prompt = "هذه صورة استمارة كشف طرود\/شحنات يومي لمكتب نقل (مكتوبة بخط اليد).\n"
                    . "المطلوب منك استخراج بيانات الطرود المسجلة في الجدول سطر بسطر:\n"
                    . "1. ابحث عن عمود (رقم المستلم) واستخرج رقم الهاتف المكتوب فيه (أرقام يمنية تبدأ بـ 7 وتتكون من 9 أرقام، قد تكون مكتوبة بالأرقام العربية أو الهندية مثل ٧٧٢٤٥٠١٦٦).\n"
                    . "2. ابحث عن عمود (نوع الطرد) في نفس السطر (مثل: ظرف، كيس، كرتون، عسل، قطيار، إلخ).\n"
                    . "3. أخرج النتيجة فقط بالشكل التالي لكل سطر دون أي شرح أو مقدمات:\n"
                    . "[رقم الهاتف بالأرقام الإنجليزية] [نوع الطرد]\n\n"
                    . "أمثلة للشكل المطلوب:\n"
                    . "772450166 ظرف\n"
                    . "773111225 كيس\n"
                    . "771401107 كرتون\n\n"
                    . "إذا لم تجد أي أرقام هواتف واضحة في الجدول، أرجع كلمة: NONE فقط.";

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->timeout(35)->post($url, [
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
                    'temperature' => 0.2,
                ]
            ]);

            if ($response->successful()) {
                $resultText = trim($response->json('candidates.0.content.parts.0.text') ?? '');
                Log::info("Gemini Raw Extraction Output:\n" . $resultText);
                
                if (Str::upper($resultText) === 'NONE' || empty($resultText)) {
                    return null;
                }

                // تحويل الأرقام العربية/الهندية (١٢٣...) إلى إنجليزية (123...) لضمان قبولها
                $arabicDigits = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
                $englishDigits = ['0','1','2','3','4','5','6','7','8','9'];
                $resultText = str_replace($arabicDigits, $englishDigits, $resultText);

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
        // 1. الموقع الدقيق للـ Base64 في Evolution GO: $data['Message']['base64']
        $msgNode = $data['Message'] ?? $data['message'] ?? [];
        if (!empty($msgNode['base64'])) {
            return preg_replace('#^data:image/\w+;base64,#i', '', $msgNode['base64']);
        }

        // 2. إذا كانت في المسارات البديلة
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