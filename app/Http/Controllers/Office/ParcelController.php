<?php

namespace App\Http\Controllers\Office;

use App\Enums\ParcelStatus;
use App\Http\Controllers\Controller;
use App\Models\Parcel;
use App\Services\HttpSmsService;
use App\Services\WhatsAppMessageComposer;
use App\Services\WhatsAppQueueScheduler;
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
        $deliveredParcels = [];

        foreach ($updates as $item) {
            $parcel = $parcels->get($item['id']);
            $status = ParcelStatus::tryFrom($item['status']);

            if ($parcel && $status) {
                $previousStatus = $parcel->status;
                if ($parcel->transitionTo($status, $this->parseDate($item['delivered_at'] ?? null))) {
                    $synced++;
                    // رصد الطرود التي تم تسليمها حديثاً لتسليم إشعاراتها
                    if ($status === ParcelStatus::Delivered && $previousStatus !== ParcelStatus::Delivered) {
                        $deliveredParcels[] = $parcel;
                    }
                } else {
                    $rejected[] = $item['id'];
                }
            } else {
                $rejected[] = $item['id'] ?? null;
            }
        }

        // إرسال إشعارات التسليم بشكل غير متزامن بعد استجابة السيرفر لحماية أداء الـ PWA وسرعة الاستجابة
        if (! empty($deliveredParcels)) {
            dispatch(function () use ($deliveredParcels) {
                $deliveryService = app(\App\Services\DeliveryNotificationService::class);
                foreach ($deliveredParcels as $deliveredParcel) {
                    $deliveryService->sendDeliveryNotification($deliveredParcel);
                }
            })->afterResponse();
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

        // التحقق من حالة رصيد/باقة الـ SMS في الكاش
        $outOfBalanceKey = "sms_out_of_balance_office_{$office->id}";
        if (Cache::has($outOfBalanceKey)) {
            $parcel->update([
                'sms_status'         => 'failed_balance',
                'sms_failure_reason' => 'UNKNOWN:108 - غير معروف بسبب نفاد الرصيد أو انتهاء الباقة',
            ]);

            return response()->json([
                'success'            => false,
                'sms_out_of_balance' => true,
                'sms_status_data'    => Cache::get($outOfBalanceKey),
                'message'            => 'باقة الرسائل (SMS) منتهية في هاتف المكتب (خطأ 108). يرجى شحن الرصيد أولاً.',
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

        if (!empty($result['success']) || (is_bool($result) && $result)) {
            $parcel->update([
                'sms_status'         => 'pending',
                'sms_failure_reason' => null,
            ]);

            return response()->json([
                'success'    => true,
                'sms_status' => 'pending',
                'message'    => 'تم إعادة إرسال رسالة الـ SMS للمستلم بنجاح.'
            ]);
        }

        $failureReason = !empty($result['error']) ? $result['error'] : 'تعذر الاتصال ببوابة الرسائل httpSMS';
        $parcel->update([
            'sms_status'         => 'failed',
            'sms_failure_reason' => $failureReason,
        ]);

        return response()->json([
            'success' => false,
            'message' => 'تعذر إرسال رسالة SMS: ' . $failureReason
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

        // 5. تجهيز نص محمي من بصمة الـ Spam (ترحيب متغير + سطر رد + محارف مخفية + رمز مرجعي)
        $message = app(WhatsAppMessageComposer::class)->composeArrival($office, $parcel, $wa);

        try {
            // delay عشوائي ليظهر للمستلم "يكتب الآن..." قبل وصول الرسالة
            $result = $wa->sendText($parcel->recipient_phone, $message, random_int(4000, 9000));

            if (!empty($result['success'])) {
                Cache::put('whatsapp_status_office_' . $office->id, 'online', now()->addMinutes(15));
                $parcel->forceFill([
                    'whatsapp_status'  => 'sent',
                    'whatsapp_sent_at' => now(),
                ])->save();

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
        $whatsappQueuedCount   = 0;

        // تجهيز قوالب الرسائل
        $defaultTemplate = "عميلنا العزيز،\nمكتب: {office} ({branch})\nتم استلام طردكم: {package}\nيرجى التوجه للفرع للاستلام. شكراً لتعاملكم معنا.";
        $smsTemplate = !empty($office->sms_template) ? $office->sms_template : $defaultTemplate;
        $hasSmsGatewayConfigured = !empty($office->httpsms_api_key) && !empty($office->httpsms_from_phone);
        $isSmsOutOfBalance = Cache::has("sms_out_of_balance_office_{$office->id}");

        $waScheduler = app(WhatsAppQueueScheduler::class);
        $hasCodFeature = $office->hasFeature('parcel_cod_amount');

        foreach ($items as $item) {
            $phone = trim($item['phone'] ?? '');
            $desc  = trim($item['description'] ?? 'طرد');

            if (empty($phone)) {
                continue;
            }

            $codAmount = null;
            if ($hasCodFeature && isset($item['cod_amount']) && $item['cod_amount'] !== null && $item['cod_amount'] !== '') {
                $val = floatval($item['cod_amount']);
                $codAmount = $val > 0 ? $val : null;
            }

            // 1. إنشاء الطرد في قاعدة البيانات
            try {
                $parcel = Parcel::create([
                    'office_id'          => $office->id,
                    'recipient_phone'    => $phone,
                    'package_type'       => $desc ?: 'طرد',
                    'cod_amount'         => $codAmount,
                    'status'             => defined(ParcelStatus::class . '::InOffice') 
                                            ? ParcelStatus::InOffice 
                                            : 'in_office',
                    'sms_status'         => (in_array($channel, ['sms', 'both']) && $isSmsOutOfBalance) ? 'failed_balance' : null,
                    'sms_failure_reason' => (in_array($channel, ['sms', 'both']) && $isSmsOutOfBalance) ? 'UNKNOWN:108 - غير معروف بسبب نفاد الرصيد أو انتهاء الباقة' : null,
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
            if (in_array($channel, ['sms', 'both'])) {
                if ($isSmsOutOfBalance) {
                    $smsFailedCount++;
                    $parcel->update([
                        'sms_status'         => 'failed_balance',
                        'sms_failure_reason' => 'UNKNOWN:108 - غير معروف بسبب نفاد الرصيد أو انتهاء الباقة',
                    ]);
                } elseif ($hasSmsGatewayConfigured) {
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
                            $parcel->update([
                                'sms_status'         => 'pending',
                                'sms_failure_reason' => null,
                            ]);
                            $smsSuccessCount++;
                        } else {
                            $smsFailedCount++;
                            $failureReason = !empty($result['error']) ? $result['error'] : 'تعذر الاتصال ببوابة الرسائل httpSMS';
                            $parcel->update([
                                'sms_status'         => 'failed',
                                'sms_failure_reason' => $failureReason,
                            ]);
                            Log::warning("Quick Send - SMS لم تؤكد نجاح الإرسال للرقم: {$parcel->recipient_phone}");
                        }
                    } catch (\Throwable $e) {
                        $smsFailedCount++;
                        $parcel->update([
                            'sms_status'         => 'failed',
                            'sms_failure_reason' => $e->getMessage() ?: 'تعذر الاتصال ببوابة الرسائل httpSMS',
                        ]);
                        Log::error("Quick Send - خطأ أثناء إرسال SMS للرقم {$parcel->recipient_phone}: " . $e->getMessage());
                    }

                    sleep(1);
                }
            }

            // 3. جدولة إشعار الواتساب في الطابور الذكي (لا إرسال فوري لحماية رقم المكتب من الحظر)
            $waQueue = $waScheduler->scheduleForParcel(
                $parcel,
                $office,
                in_array($channel, ['whatsapp', 'both'], true)
            );
            if ($waQueue['queued']) {
                $whatsappQueuedCount++;
            }
        }

        $totalSaved = count($createdParcels);

        return response()->json([
            'success'            => true,
            'count'              => $totalSaved,
            'sms_sent'           => $smsSuccessCount,
            'sms_failed'         => $smsFailedCount,
            'sms_out_of_balance' => (bool) $isSmsOutOfBalance,
            'sms_status_data'    => $isSmsOutOfBalance ? Cache::get("sms_out_of_balance_office_{$office->id}") : null,
            'whatsapp_sent'      => 0,
            'whatsapp_failed'    => 0,
            'whatsapp_queued'    => $whatsappQueuedCount,
            'message'            => "تم تسجيل {$totalSaved} طرد بنجاح (SMS: {$smsSuccessCount} ، واتساب مجدول: {$whatsappQueuedCount}).",
            'parcels'            => $createdParcels,
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

        $phone         = trim($this->normalizeEasternDigits($request->input('phone', '')));
        $desc          = trim($request->input('description', 'طرد'));
        $recipientName = trim((string) $request->input('recipient_name', '')) ?: null;
        $channel       = $request->input('channel', 'sms'); // 'sms', 'whatsapp', 'both'

        // التحقق من صحة رقم الهاتف اليمني (9 أرقام يبدأ بـ 7)
        if (!preg_match('/^7[01378]\d{7}$/', $phone)) {
            return response()->json([
                'success' => false,
                'message' => 'رقم الهاتف غير صالح. يجب أن يبدأ بـ 7 ويتكون من 9 أرقام.'
            ], 422);
        }

        // التحقق من ميزة المبلغ المطلوب عند التسليم من الكاش
        $codAmount = null;
        if ($office->hasFeature('parcel_cod_amount')) {
            $rawCod = $request->input('cod_amount');
            if ($rawCod !== null && $rawCod !== '') {
                $validatedCod = $request->validate([
                    'cod_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
                ], [
                    'cod_amount.numeric' => 'المبلغ المطلوب يجب أن يكون رقماً صالحاً.',
                    'cod_amount.min'     => 'المبلغ المطلوب يجب ألا يقل عن صفر.',
                    'cod_amount.max'     => 'المبلغ المطلوب يجب ألا يتجاوز 99,999,999.',
                ]);
                $val = floatval($validatedCod['cod_amount'] ?? 0);
                $codAmount = $val > 0 ? $val : null;
            }
        }

        // 1. إنشاء الطرد في قاعدة البيانات
        try {
            $parcel = Parcel::create([
                'office_id'       => $office->id,
                'recipient_name'  => $recipientName,
                'recipient_phone' => $phone,
                'package_type'    => $desc ?: 'طرد',
                'cod_amount'      => $codAmount,
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

        $isSmsOutOfBalance = Cache::has("sms_out_of_balance_office_{$office->id}");
        $smsSent      = false;
        $whatsappSent = false;

        // 2. إرسال رسالة SMS إذا طُلب
        $hasSmsGateway = !empty($office->httpsms_api_key) && !empty($office->httpsms_from_phone);
        if (in_array($channel, ['sms', 'both'])) {
            if ($isSmsOutOfBalance) {
                $parcel->update([
                    'sms_status'         => 'failed_balance',
                    'sms_failure_reason' => 'UNKNOWN:108 - غير معروف بسبب نفاد الرصيد أو انتهاء الباقة',
                ]);
                $smsSent = false;
            } elseif ($hasSmsGateway) {
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

                    if ($smsSent) {
                        $parcel->update([
                            'sms_status'         => 'pending',
                            'sms_failure_reason' => null,
                        ]);
                    } else {
                        $failureReason = !empty($result['error']) ? $result['error'] : 'تعذر الاتصال ببوابة الرسائل httpSMS';
                        $parcel->update([
                            'sms_status'         => 'failed',
                            'sms_failure_reason' => $failureReason,
                        ]);
                        Log::warning("QuickSendSingle - البوابة لم تؤكد النجاح للرقم: {$parcel->recipient_phone}");
                    }
                } catch (\Throwable $e) {
                    $parcel->update([
                        'sms_status'         => 'failed',
                        'sms_failure_reason' => $e->getMessage() ?: 'تعذر الاتصال ببوابة الرسائل httpSMS',
                    ]);
                    Log::error("QuickSendSingle - خطأ SMS للرقم {$parcel->recipient_phone}: " . $e->getMessage());
                }
            }
        }

        // 3. جدولة إشعار الواتساب في الطابور الذكي بدلاً من الإرسال الفوري (حماية رقم المكتب من الحظر)
        $waQueue = app(WhatsAppQueueScheduler::class)->scheduleForParcel(
            $parcel,
            $office,
            in_array($channel, ['whatsapp', 'both'], true)
        );
        $whatsappQueued = $waQueue['queued'];

        $sentChannels = [];
        if ($smsSent) $sentChannels[] = 'SMS';

        $feedbackMsg = !empty($sentChannels)
            ? 'تم تسجيل الطرد وإرسال (' . implode(' + ', $sentChannels) . ') بنجاح.'
            : 'تم تسجيل الطرد بنجاح.';

        if ($whatsappQueued) {
            $feedbackMsg .= ' وتمت جدولة إشعار الواتساب.';
        }

        return response()->json([
            'success'               => true,
            'sms_sent'              => $smsSent,
            'sms_out_of_balance'    => (bool) $isSmsOutOfBalance,
            'sms_status_data'       => $isSmsOutOfBalance ? Cache::get("sms_out_of_balance_office_{$office->id}") : null,
            'whatsapp_sent'         => $whatsappSent,
            'whatsapp_queued'       => $whatsappQueued,
            'whatsapp_scheduled_at' => $waQueue['scheduled_at']?->toIso8601String(),
            'message'               => $feedbackMsg,
            'parcel'                => $parcel,
        ]);
    }

    /**
     * حفظ طرد جديد (نقطة دخول قياسية للمكتب).
     */
    public function store(Request $request, HttpSmsService $smsService, WhatsAppService $whatsAppService)
    {
        return $this->quickSendSingle($request, $smsService, $whatsAppService);
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

    /**
     * استرجاع حالة بوابة الـ SMS ورصيد الشريحة لدايلوق المكتب.
     */
    public function getSmsGatewayStatus()
    {
        $office = Auth::guard('office')->user();
        if (!$office) {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 401);
        }

        $outOfBalanceKey = "sms_out_of_balance_office_{$office->id}";
        $isOutOfBalance  = Cache::has($outOfBalanceKey);
        $cacheData       = $isOutOfBalance ? Cache::get($outOfBalanceKey) : null;

        $inOfficeStatus = defined(ParcelStatus::class . '::InOffice') ? ParcelStatus::InOffice : 'in_office';

        $failedTodayCount = Parcel::where('office_id', $office->id)
            ->where('status', $inOfficeStatus)
            ->where('sms_status', 'failed_balance')
            ->whereDate('created_at', today())
            ->count();

        return response()->json([
            'success'            => true,
            'out_of_balance'     => $isOutOfBalance,
            'failed_today_count' => $failedTodayCount,
            'data'               => $cacheData,
        ]);
    }

    /**
     * تأكيد تجديد باقة/رصيد SMS وإعادة إرسال رسائل اليوم الفاشلة في الخلفية.
     */
    public function confirmRechargeAndRetryToday(HttpSmsService $smsService)
    {
        $office = Auth::guard('office')->user();
        if (!$office) {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 401);
        }

        // 1. مسح قفل التوقف والعداد
        Cache::forget("sms_out_of_balance_office_{$office->id}");
        Cache::forget("sms_108_streak_office_{$office->id}");

        // 2. وضع علامة المراقبة الفورية لمدة 10 دقائق
        Cache::put("sms_retrying_after_recharge_office_{$office->id}", true, now()->addMinutes(10));

        // 3. جلب فقط طرود نفس اليوم التي فشلت بسبب الرصيد ولا تزال في المكتب (لم تُسلّم بعد)
        $inOfficeStatus = defined(ParcelStatus::class . '::InOffice') ? ParcelStatus::InOffice : 'in_office';

        $parcelsToRetry = Parcel::where('office_id', $office->id)
            ->where('status', $inOfficeStatus)
            ->where('sms_status', 'failed_balance')
            ->whereDate('created_at', today())
            ->orderBy('created_at', 'asc')
            ->get();

        $count = $parcelsToRetry->count();

        if ($count > 0) {
            // 4. تحديث حالة الطرود إلى pending وتصفير سبب الفشل
            $ids = $parcelsToRetry->pluck('id')->all();
            Parcel::whereIn('id', $ids)->update([
                'sms_status'         => 'pending',
                'sms_failure_reason' => null,
            ]);

            // 5. إرسال الرسائل في الخلفية عبر dispatch(...)->afterResponse()
            dispatch(function () use ($parcelsToRetry, $office) {
                if (empty($office->httpsms_api_key) || empty($office->httpsms_from_phone)) {
                    return;
                }

                $smsService = app(HttpSmsService::class);
                $defaultTemplate = "عميلنا العزيز،\nمكتب: {office} ({branch})\nتم استلام طردكم: {package}\nيرجى التوجه للفرع للاستلام. شكراً لتعاملكم معنا.";
                $template = !empty($office->sms_template) ? $office->sms_template : $defaultTemplate;

                foreach ($parcelsToRetry as $p) {
                    // إذا وصل حدث 108 وتفعّل قفل الرصيد أثناء الإرسال نوقف فوراً
                    if (Cache::has("sms_out_of_balance_office_{$office->id}")) {
                        Log::warning("confirmRechargeAndRetryToday - توقف إعادة الإرسال للمكتب {$office->id} بسبب تجدد نفاد الرصيد (خطأ 108).");
                        break;
                    }

                    $smsBody = str_replace(
                        ['{office}', '{branch}', '{package}', '{receipt}'],
                        [
                            $office->name,
                            $office->branch_name ?? '',
                            $p->package_type,
                            $p->receipt_number ?? ''
                        ],
                        $template
                    );
                    $smsBody = str_replace(' ()', '', $smsBody);

                    try {
                        $smsService->send(
                            $p->recipient_phone,
                            $smsBody,
                            $office->httpsms_api_key,
                            $office->httpsms_from_phone
                        );
                    } catch (\Throwable $e) {
                        Log::error("confirmRecharge retry error for parcel #{$p->id}: " . $e->getMessage());
                    }

                    sleep(1);
                }
            })->afterResponse();
        }

        return response()->json([
            'success' => true,
            'count'   => $count,
            'message' => $count > 0
                ? "تم تأكيد الشحن، وجاري إعادة إرسال رسائل {$count} طرد في الخلفية."
                : "تم تأكيد الشحن بنجاح (لا توجد طرود فاشلة معلقة لليوم).",
        ]);
    }
}