<?php

namespace App\Services;

use App\Models\Office;
use App\Models\Parcel;
use Illuminate\Support\Facades\Log;

class DeliveryNotificationService
{
    public function __construct(
        protected HttpSmsService $smsService,
        protected WhatsAppService $whatsAppService,
        protected WhatsAppMessageComposer $composer
    ) {}

    /**
     * إرسال إشعار تسليم الطرد (SMS و/أو WhatsApp) إذا كانت الميزة مفعلة للمكتب
     */
    public function sendDeliveryNotification(Parcel $parcel): array
    {
        $status = [
            'sms_sent'      => false,
            'whatsapp_sent' => false,
            'skipped'       => false,
            'reason'        => null,
        ];

        try {
            $office = $parcel->office ?? Office::find($parcel->office_id);

            if (! $office) {
                $status['skipped'] = true;
                $status['reason'] = 'Office not found';
                return $status;
            }

            // التحقق من تفعيل ميزات إشعار التسليم بشكل منفصل لكل قناة (SMS / واتساب)
            $canSendSms = $office->hasFeature('sms_on_delivery');
            $canSendWhatsapp = $office->hasFeature('whatsapp_on_delivery');

            if (! $canSendSms && ! $canSendWhatsapp) {
                $status['skipped'] = true;
                $status['reason'] = 'Neither SMS nor WhatsApp delivery notification feature is enabled for this office';
                return $status;
            }

            $phone = trim((string) $parcel->recipient_phone);
            if (empty($phone)) {
                $status['skipped'] = true;
                $status['reason'] = 'Recipient phone is empty';
                return $status;
            }

            // تجهيز قائمة المتغيرات المشتركة
            $variables = [
                '{package_type}'    => $parcel->package_type ?: 'طرد',
                '{package}'         => $parcel->package_type ?: 'طرد',
                '{office_name}'     => $office->name,
                '{office}'          => $office->name,
                '{branch_name}'     => $office->branch_name ?? '',
                '{branch}'          => $office->branch_name ?? '',
                '{recipient_name}'  => $parcel->recipient_name ?: 'العميل',
                '{recipient}'       => $parcel->recipient_name ?: 'العميل',
                '{receipt_number}'  => $parcel->receipt_number ?? '',
                '{receipt}'         => $parcel->receipt_number ?? '',
                '{phone}'           => $phone,
                '{recipient_phone}' => $phone,
            ];

            // 1. إرسال رسالة SMS إذا كانت ميزة sms_on_delivery مفعلة والبوابة مهيأة
            if ($canSendSms && ! empty($office->httpsms_api_key) && ! empty($office->httpsms_from_phone)) {
                try {
                    $smsTemplate = $office->getDeliverySmsTemplate();
                    $smsBody = str_replace(array_keys($variables), array_values($variables), $smsTemplate);
                    $smsBody = str_replace(' ()', '', $smsBody);

                    $smsResult = $this->smsService->send(
                        $phone,
                        $smsBody,
                        $office->httpsms_api_key,
                        $office->httpsms_from_phone
                    );

                    $status['sms_sent'] = ! empty($smsResult['success']);
                    if (! $status['sms_sent']) {
                        Log::warning("Delivery SMS failed for parcel {$parcel->id}: " . ($smsResult['error'] ?? 'Unknown error'));
                    }
                } catch (\Throwable $e) {
                    Log::error("Error sending delivery SMS for parcel {$parcel->id}: " . $e->getMessage());
                }
            }

            // 2. إرسال رسالة WhatsApp إذا كانت ميزة whatsapp_on_delivery مفعلة والواتساب مهيأً للمكتب
            if ($canSendWhatsapp) {
                try {
                    $wa = $this->whatsAppService->forOffice($office);
                    if ($wa->isConfigured()) {
                        $waTemplate = $office->getDeliveryWhatsappTemplate();
                        $waBody = $wa->parseTemplate($waTemplate, $variables);

                        // حماية سريعة من بصمة الـ Spam: محارف مخفية + رمز مرجعي عشوائي + تأخير "يكتب الآن..."
                        $waBody = $this->composer->protect($waBody);

                        $waResult = $wa->sendText($phone, $waBody, random_int(3000, 6000));
                        $status['whatsapp_sent'] = ! empty($waResult['success']) || (empty($waResult['error']) && empty($waResult['message']));

                        if (! $status['whatsapp_sent']) {
                            Log::warning("Delivery WhatsApp failed for parcel {$parcel->id}: " . ($waResult['message'] ?? 'Unknown error'));
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error("Error sending delivery WhatsApp for parcel {$parcel->id}: " . $e->getMessage());
                }
            }

            return $status;
        } catch (\Throwable $e) {
            Log::error("DeliveryNotificationService general error for parcel {$parcel->id}: " . $e->getMessage());
            $status['reason'] = $e->getMessage();
            return $status;
        }
    }
}

