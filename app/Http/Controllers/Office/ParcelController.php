<?php

namespace App\Http\Controllers\Office;

use App\Enums\ParcelStatus;
use App\Http\Controllers\Controller;
use App\Models\Parcel;
use App\Services\HttpSmsService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ParcelController extends Controller
{
    public function index()
    {
        $office = Auth::guard('office')->user();
        return view('office.parcels.index', compact('office'));
    }

    public function getParcelsData()
    {
        $officeId = Auth::guard('office')->id();
        
        $parcels = Parcel::where('office_id', $officeId)
            ->latest('created_at')
            ->take(500)
            ->get();

        return response()->json($parcels);
    }

    /**
     * استقبال طابور التعديلات من الواجهة (أونلاين/أوفلاين) وتطبيقها بالترتيب.
     * أي انتقال غير مسموح (مثل: تم التسليم → مرتجع) يُرفض ويُعاد معرّفه في rejected.
     */
    public function syncUpdates(Request $request)
    {
        $updates = collect($request->input('updates', []))
            ->filter(fn ($item) => is_array($item) && ! empty($item['id']) && ! empty($item['status']));

        $parcels = Parcel::where('office_id', Auth::guard('office')->id())
            ->whereIn('id', $updates->pluck('id')->unique())
            ->get()
            ->keyBy('id');

        $synced = 0;
        $rejected = [];

        foreach ($updates as $item) {
            $parcel = $parcels->get($item['id']);
            $status = ParcelStatus::tryFrom($item['status']);

            if ($parcel && $status && $parcel->transitionTo($status, $this->parseDate($item['delivered_at'] ?? null))) {
                $synced++;
            } else {
                $rejected[] = $item['id'];
            }
        }

        return response()->json([
            'status'   => 'synced',
            'count'    => $synced,
            'rejected' => array_values(array_unique($rejected)),
        ]);
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->setTimezone(config('app.timezone', 'UTC'));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * إعادة إرسال إشعار SMS للمستلم (مخصص للطرود المتواجدة في المكتب فقط عبر httpSMS).
     */
    public function resendSms(Parcel $parcel, HttpSmsService $smsService)
    {
        $office = Auth::guard('office')->user();

        // التحقق من تبعية الطرد لنفس المكتب
        if ($parcel->office_id !== $office->id) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بإدارة هذا الطرد.'
            ], 403);
        }

        // التحقق من أن حالة الطرد هي في المكتب فقط
        if ($parcel->status !== ParcelStatus::InOffice) {
            return response()->json([
                'success' => false,
                'message' => 'يمكن إعادة إرسال رسائل SMS للطرود المتواجدة بالمكتب فقط.'
            ], 422);
        }

        // التحقق من وجود إعدادات httpSMS للمكتب
        if (empty($office->httpsms_api_key) || empty($office->httpsms_from_phone)) {
            return response()->json([
                'success' => false,
                'message' => 'بيانات بوابة الرسائل (httpSMS) غير مكتملة في إعدادات المكتب.'
            ], 422);
        }

        // تجهيز نص الرسالة بالاعتماد على قالب المكتب أو القالب الافتراضي
        $defaultTemplate = "عميلنا العزيز،\nمكتب: {office} ({branch})\nتم استلام طردكم: {package}\nيرجى التوجه للفرع للاستلام. شكراً لتعاملكم معنا.";
        $template = !empty($office->sms_template) ? $office->sms_template : $defaultTemplate;

        $smsBody = str_replace(
            ['{office}', '{branch}', '{package}', '{receipt}'],
            [
                $office->name,
                $office->branch_name ?? '',
                $parcel->package_type,
                $parcel->receipt_number ?? ''
            ],
            $template
        );

        $smsBody = str_replace(' ()', '', $smsBody);

        // إرسال الرسالة عبر خدمة SMS المستقلة
        $result = $smsService->send(
            $parcel->recipient_phone,
            $smsBody,
            $office->httpsms_api_key,
            $office->httpsms_from_phone
        );

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'تم إعادة إرسال رسالة الـ SMS للمستلم بنجاح.'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'تعذر إرسال رسالة SMS: ' . ($result['error'] ?? 'خطأ في بوابة الرسائل')
        ], 500);
    }

    /**
     * إعادة / إرسال إشعار WhatsApp للمستلم (مخصص للطرود المتواجدة في المكتب فقط عبر WhatsAppService).
     */
    public function resendWhatsapp(Parcel $parcel, WhatsAppService $whatsAppService)
    {
        $office = Auth::guard('office')->user();

        // 1. التحقق من تبعية الطرد لنفس المكتب
        if ($parcel->office_id !== $office->id) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بإدارة هذا الطرد.'
            ], 403);
        }

        // 2. التحقق من أن حالة الطرد هي في المكتب فقط
        if ($parcel->status !== ParcelStatus::InOffice) {
            return response()->json([
                'success' => false,
                'message' => 'يمكن إرسال رسائل الواتساب للطرود المتواجدة بالمكتب فقط.'
            ], 422);
        }

        // 3. التحقق من كاش الاتصال: إذا كانت الخدمة مسجلة كـ offline في الكاش نرجع فوراً لتوفير الوقت
        $cacheStatus = Cache::get('whatsapp_status_office_' . $office->id);
        if ($cacheStatus === 'offline') {
            return response()->json([
                'success' => false,
                'message' => 'خدمة WhatsApp غير متصلة حالياً (حسب الكاش). يمكنك المراسلة المباشرة عبر الرابط.'
            ], 503);
        }

        // 4. تهيئة خدمة الواتساب للمكتب الحالي
        $wa = $whatsAppService->forOffice($office);

        if (!$wa->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'بيانات بوابة الواتساب غير مكتملة في إعدادات المكتب (الرابط أو مفتاح الـ API غير معرّف).'
            ], 422);
        }

        // 5. تجهيز متغيرات القالب
        $variables = [
            '{package}'      => $parcel->package_type ?? 'طرد',
            '{receipt}'      => $parcel->receipt_number ?? '',
            '{recipient}'    => $parcel->recipient_name ?? 'العميل',
        ];

        try {
            $result = $wa->sendOfficeTemplate($parcel->recipient_phone, $variables);

            if (!empty($result['success']) || (empty($result['error']) && empty($result['message']))) {
                Cache::put('whatsapp_status_office_' . $office->id, 'online', now()->addMinutes(15));
                return response()->json([
                    'success' => true,
                    'message' => 'تم إرسال إشعار الواتساب للمستلم بنجاح.'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'تعذر إرسال رسالة الواتساب: ' . ($result['message'] ?? 'خطأ في خادم الواتساب')
            ], 500);
        } catch (\Throwable $e) {
            Log::error("resendWhatsapp Error for parcel #{$parcel->id}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إرسال الواتساب: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * إدراج وإرسال دفعة طرود سريعة من خلال الكشف السريع للـ SMS.
     */
    public function quickSendSms(Request $request, HttpSmsService $smsService, WhatsAppService $whatsAppService)
    {
        $office = Auth::guard('office')->user();

        if (!$office) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح بالوصول، يرجى تسجيل الدخول مجدداً.'
            ], 401);
        }

        $items   = $request->input('items', []);
        $channel = $request->input('channel', 'sms'); // 'sms', 'whatsapp', 'both'

        if (empty($items) || !is_array($items)) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم استلام أي بيانات صالحة للإرسال.'
            ], 422);
        }

        if (count($items) > 15) {
            return response()->json([
                'success' => false,
                'message' => 'الحد الأقصى المسموح به هو 15 طرداً لكل إرسالية لضمان سرعة واستقرار الإرسال.'
            ], 422);
        }

        // تمديد مهلة تنفيذ السكربت لتفادي timeout بسبب الـ sleep أثناء إرسال دفعات كبيرة
        set_time_limit(300);

        $createdParcels        = [];
        $smsSuccessCount       = 0;
        $smsFailedCount        = 0;
        $whatsappSuccessCount  = 0;
        $whatsappFailedCount   = 0;

        // تجهيز قوالب الرسائل
        $defaultTemplate = "عميلنا العزيز،\nمكتب: {office} ({branch})\nتم استلام طردكم: {package}\nيرجى التوجه للفرع للاستلام. شكراً لتعاملكم معنا.";
        $smsTemplate = !empty($office->sms_template) ? $office->sms_template : $defaultTemplate;
        $hasSmsGatewayConfigured = !empty($office->httpsms_api_key) && !empty($office->httpsms_from_phone);

        $wa = $whatsAppService->forOffice($office);
        $hasWhatsAppConfigured = $wa->isConfigured();

        foreach ($items as $item) {
            $phone = trim($item['phone'] ?? '');
            $desc  = trim($item['description'] ?? 'طرد');

            if (empty($phone)) {
                continue;
            }

            // 1. إنشاء الطرد في قاعدة البيانات
            try {
                $parcel = Parcel::create([
                    'office_id'       => $office->id,
                    'recipient_phone' => $phone,
                    'package_type'    => $desc ?: 'طرد',
                    'status'          => defined(ParcelStatus::class . '::InOffice') 
                                            ? ParcelStatus::InOffice 
                                            : 'in_office',
                ]);

                $createdParcels[] = $parcel;
            } catch (\Throwable $e) {
                Log::error("Quick Send - فشل حفظ الطرد في قاعدة البيانات: " . $e->getMessage(), [
                    'phone' => $phone,
                    'desc'  => $desc
                ]);
                continue;
            }

            // 2. إرسال رسالة SMS عبر البوابة
            if (in_array($channel, ['sms', 'both']) && $hasSmsGatewayConfigured) {
                $smsBody = str_replace(
                    ['{office}', '{branch}', '{package}', '{receipt}'],
                    [
                        $office->name,
                        $office->branch_name ?? '',
                        $parcel->package_type,
                        $parcel->receipt_number ?? ''
                    ],
                    $smsTemplate
                );

                $smsBody = str_replace(' ()', '', $smsBody);

                try {
                    $result = $smsService->send(
                        $parcel->recipient_phone,
                        $smsBody,
                        $office->httpsms_api_key,
                        $office->httpsms_from_phone
                    );

                    if (!empty($result['success']) || (is_bool($result) && $result)) {
                        $smsSuccessCount++;
                    } else {
                        $smsFailedCount++;
                        Log::warning("Quick Send - SMS لم تؤكد نجاح الإرسال للرقم: {$parcel->recipient_phone}");
                    }
                } catch (\Throwable $e) {
                    $smsFailedCount++;
                    Log::error("Quick Send - خطأ أثناء إرسال SMS للرقم {$parcel->recipient_phone}: " . $e->getMessage());
                }

                sleep(1);
            }

            // 3. إرسال رسالة WhatsApp عبر WhatsAppService
            if (in_array($channel, ['whatsapp', 'both']) && $hasWhatsAppConfigured) {
                $variables = [
                    '{package}'   => $parcel->package_type ?? 'طرد',
                    '{receipt}'   => $parcel->receipt_number ?? '',
                    '{recipient}' => $parcel->recipient_name ?? 'العميل',
                ];

                try {
                    $waResult = $wa->sendOfficeTemplate($parcel->recipient_phone, $variables);

                    if (!empty($waResult['success']) || (empty($waResult['error']) && empty($waResult['message']))) {
                        $whatsappSuccessCount++;
                    } else {
                        $whatsappFailedCount++;
                        Log::warning("Quick Send - تعذر إرسال الواتساب للرقم {$parcel->recipient_phone}: " . ($waResult['message'] ?? 'خطأ في خادم الواتساب'));
                    }
                } catch (\Throwable $e) {
                    $whatsappFailedCount++;
                    Log::error("Quick Send - خطأ واتساب للرقم {$parcel->recipient_phone}: " . $e->getMessage());
                }

                sleep(1);
            }
        }

        $totalSaved = count($createdParcels);

        return response()->json([
            'success'          => true,
            'count'            => $totalSaved,
            'sms_sent'         => $smsSuccessCount,
            'sms_failed'       => $smsFailedCount,
            'whatsapp_sent'    => $whatsappSuccessCount,
            'whatsapp_failed'  => $whatsappFailedCount,
            'message'          => "تم تسجيل {$totalSaved} طرد بنجاح (واتساب: {$whatsappSuccessCount} ، SMS: {$smsSuccessCount}).",
            'parcels'          => $createdParcels,
        ]);
    }

    /**
     * إرسال فردي فوري لطرد واحد (Streaming Dispatch).
     * يُستقبل من الواجهة بشكل غير متزامن لكل طرد على حدة بدون أي تأخير.
     */
    public function quickSendSingle(Request $request, HttpSmsService $smsService, WhatsAppService $whatsAppService)
    {
        $office = Auth::guard('office')->user();

        if (!$office) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح بالوصول، يرجى تسجيل الدخول مجدداً.'
            ], 401);
        }

        $phone   = trim($this->normalizeEasternDigits($request->input('phone', '')));
        $desc    = trim($request->input('description', 'طرد'));
        $channel = $request->input('channel', 'sms'); // 'sms', 'whatsapp', 'both'

        // التحقق من صحة رقم الهاتف اليمني (9 أرقام يبدأ بـ 7)
        if (!preg_match('/^7[01378]\d{7}$/', $phone)) {
            return response()->json([
                'success' => false,
                'message' => 'رقم الهاتف غير صالح. يجب أن يبدأ بـ 7 ويتكون من 9 أرقام.'
            ], 422);
        }

        // 1. إنشاء الطرد في قاعدة البيانات
        try {
            $parcel = Parcel::create([
                'office_id'       => $office->id,
                'recipient_phone' => $phone,
                'package_type'    => $desc ?: 'طرد',
                'status'          => defined(ParcelStatus::class . '::InOffice')
                                        ? ParcelStatus::InOffice
                                        : 'in_office',
            ]);
        } catch (\Throwable $e) {
            Log::error("QuickSendSingle - فشل حفظ الطرد: " . $e->getMessage(), [
                'phone' => $phone,
                'desc'  => $desc
            ]);
            return response()->json([
                'success' => false,
                'message' => 'تعذر حفظ الطرد في قاعدة البيانات.'
            ], 500);
        }

        $smsSent      = false;
        $whatsappSent = false;

        // 2. إرسال رسالة SMS إذا طُلب
        $hasSmsGateway = !empty($office->httpsms_api_key) && !empty($office->httpsms_from_phone);
        if (in_array($channel, ['sms', 'both']) && $hasSmsGateway) {
            $defaultTemplate = "عميلنا العزيز،\nمكتب: {office} ({branch})\nتم استلام طردكم: {package}\nيرجى التوجه للفرع للاستلام. شكراً لتعاملكم معنا.";
            $template = !empty($office->sms_template) ? $office->sms_template : $defaultTemplate;

            $smsBody = str_replace(
                ['{office}', '{branch}', '{package}', '{receipt}'],
                [
                    $office->name,
                    $office->branch_name ?? '',
                    $parcel->package_type,
                    $parcel->receipt_number ?? ''
                ],
                $template
            );
            $smsBody = str_replace(' ()', '', $smsBody);

            try {
                $result = $smsService->send(
                    $parcel->recipient_phone,
                    $smsBody,
                    $office->httpsms_api_key,
                    $office->httpsms_from_phone
                );

                $smsSent = !empty($result['success']) || (is_bool($result) && $result);

                if (!$smsSent) {
                    Log::warning("QuickSendSingle - البوابة لم تؤكد النجاح للرقم: {$parcel->recipient_phone}");
                }
            } catch (\Throwable $e) {
                Log::error("QuickSendSingle - خطأ SMS للرقم {$parcel->recipient_phone}: " . $e->getMessage());
            }
        }

        // 3. إرسال رسالة WhatsApp عبر WhatsAppService
        $wa = $whatsAppService->forOffice($office);
        if (in_array($channel, ['whatsapp', 'both']) && $wa->isConfigured()) {
            $variables = [
                '{package}'   => $parcel->package_type ?? 'طرد',
                '{receipt}'   => $parcel->receipt_number ?? '',
                '{recipient}' => $parcel->recipient_name ?? 'العميل',
            ];

            try {
                $waResult = $wa->sendOfficeTemplate($parcel->recipient_phone, $variables);
                $whatsappSent = !empty($waResult['success']) || (empty($waResult['error']) && empty($waResult['message']));

                if (!$whatsappSent) {
                    Log::warning("QuickSendSingle - تعذر إرسال الواتساب للرقم {$parcel->recipient_phone}: " . ($waResult['message'] ?? 'خطأ في بوابة الواتساب'));
                }
            } catch (\Throwable $e) {
                Log::error("QuickSendSingle - خطأ واتساب للرقم {$parcel->recipient_phone}: " . $e->getMessage());
            }
        }

        $sentChannels = [];
        if ($whatsappSent) $sentChannels[] = 'واتساب';
        if ($smsSent) $sentChannels[] = 'SMS';

        $feedbackMsg = !empty($sentChannels)
            ? 'تم تسجيل الطرد وإرسال (' . implode(' + ', $sentChannels) . ') بنجاح.'
            : 'تم تسجيل الطرد بنجاح.';

        return response()->json([
            'success'       => true,
            'sms_sent'      => $smsSent,
            'whatsapp_sent' => $whatsappSent,
            'message'       => $feedbackMsg,
            'parcel'        => $parcel,
        ]);
    }

    /**
     * تحويل الأرقام المشرقية/الفارسية إلى أرقام إنجليزية.
     */
    private function normalizeEasternDigits(string $str): string
    {
        $eastern = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩','۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
        $western = ['0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9'];
        return str_replace($eastern, $western, $str);
    }
}