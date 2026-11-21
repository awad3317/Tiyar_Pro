<?php

namespace App\Services;

use App\Models\Office;
use App\Models\Parcel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * خدمة موحدة لإرسال إشعارات وصول الطرد الوارد (SMS + جدولة واتساب)
 * تُستخدم عند تسجيل طرد وارد مباشر من المكتب أو عند تحويل طرد صادر إلى وارد في الفرع المستلم.
 */
class ParcelArrivalNotificationService
{
    public const DEFAULT_SMS_TEMPLATE = "عميلنا العزيز،\nمكتب: {office} ({branch})\nتم استلام طردكم: {package}\nيرجى التوجه للفرع للاستلام. شكراً لتعاملكم معنا.";

    public function __construct(
        protected HttpSmsService $smsService,
        protected WhatsAppQueueScheduler $whatsAppQueueScheduler
    ) {}

    /**
     * تنفيذ مسار إشعارات الوصول المعتمد (SMS عبر httpSMS + جدولة واتساب عبر WhatsAppQueueScheduler).
     *
     * @return array{
     *     sms_sent: bool,
     *     sms_out_of_balance: bool,
     *     whatsapp_sent: bool,
     *     whatsapp_queued: bool,
     *     whatsapp_scheduled_at: ?Carbon
     * }
     */
    public function notifyArrival(
        Parcel $parcel,
        Office $office,
        string $channel = 'both',
        ?HttpSmsService $smsServiceOverride = null
    ): array {
        $smsService = $smsServiceOverride ?? $this->smsService;
        $isSmsOutOfBalance = Cache::has("sms_out_of_balance_office_{$office->id}");
        $smsSent = false;
        $whatsappSent = false;

        // 1. إرسال رسالة SMS إذا طُلب
        $hasSmsGateway = ! empty($office->httpsms_api_key) && ! empty($office->httpsms_from_phone);
        if (in_array($channel, ['sms', 'both'], true)) {
            if ($isSmsOutOfBalance) {
                $parcel->update([
                    'sms_status'         => 'failed_balance',
                    'sms_failure_reason' => 'انتهت باقة الرسائل أو نفد الرصيد في شريحة المكتب. يرجى شحن الباقة ثم الضغط على زر (إعادة إرسال).',
                ]);
                $smsSent = false;
            } elseif ($hasSmsGateway) {
                $template = ! empty($office->sms_template) ? $office->sms_template : self::DEFAULT_SMS_TEMPLATE;

                $smsBody = str_replace(
                    ['{office}', '{branch}', '{package}', '{receipt}', '{recipient}', '{region}', '{source_region}', '{slot}', '{slot_number}'],
                    [
                        $office->name,
                        $office->branch_name ?? '',
                        $parcel->package_type,
                        $parcel->receipt_number ?? '',
                        $parcel->recipient_name ?? '',
                        $parcel->source_region ?? '',
                        $parcel->source_region ?? '',
                        $parcel->slot_number ?? '',
                        $parcel->slot_number ?? '',
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

                    $smsSent = ! empty($result['success']) || (is_bool($result) && $result);

                    if ($smsSent) {
                        $parcel->update([
                            'sms_status'         => 'pending',
                            'sms_failure_reason' => null,
                        ]);
                    } else {
                        $parcel->update([
                            'sms_status'         => 'failed',
                            'sms_failure_reason' => 'تعذر الاتصال بخدمة الرسائل مؤقتاً. يرجى إعادة المحاولة بعد قليل، أو إرسال الرسالة يدوياً.',
                        ]);
                        Log::warning("ArrivalNotification - البوابة لم تؤكد النجاح للرقم: {$parcel->recipient_phone}");
                    }
                } catch (\Throwable $e) {
                    $parcel->update([
                        'sms_status'         => 'failed',
                        'sms_failure_reason' => 'تعذر الاتصال بخدمة الرسائل مؤقتاً. يرجى إعادة المحاولة بعد قليل، أو إرسال الرسالة يدوياً.',
                    ]);
                    Log::error("ArrivalNotification - خطأ SMS للرقم {$parcel->recipient_phone}: " . $e->getMessage());
                }
            }
        }

        // 2. جدولة إشعار الواتساب في الطابور الذكي بدلاً من الإرسال الفوري (حماية رقم المكتب من الحظر)
        $waQueue = $this->whatsAppQueueScheduler->scheduleForParcel(
            $parcel,
            $office,
            in_array($channel, ['whatsapp', 'both'], true)
        );

        return [
            'sms_sent'              => $smsSent,
            'sms_out_of_balance'    => $isSmsOutOfBalance,
            'whatsapp_sent'         => $whatsappSent,
            'whatsapp_queued'       => (bool) $waQueue['queued'],
            'whatsapp_scheduled_at' => $waQueue['scheduled_at'],
        ];
    }
}
