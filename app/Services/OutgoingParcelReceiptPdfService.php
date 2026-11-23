<?php

namespace App\Services;

use App\Enums\OutgoingPaymentType;
use App\Models\Office;
use App\Models\OfficeProfile;
use App\Models\OutgoingParcel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class OutgoingParcelReceiptPdfService
{
    /**
     * توليد ملف PDF عربي متكامل (RTL) لسند إرسال الطرد الصادر وإرجاع البايتات الخام (%PDF-...).
     */
    public function generate(OutgoingParcel $parcel, ?string $copyLabel = null): string
    {
        $parcel->loadMissing([
            'office:id,name,branch_name,whatsapp_sender_phone,manager_phone,httpsms_from_phone',
            'destinationOffice:id,name,branch_name,whatsapp_sender_phone,manager_phone',
        ]);

        $viewData = $this->buildViewData($parcel, $copyLabel ?? 'نسخة العميل');
        $html = view('office.outgoing_parcels.receipt-pdf', $viewData)->render();

        // نبدأ بقياس A5 أفقي (عرض A4 الكامل 210mm بارتفاع سند مثالي 148mm مناسب لعرض الجوال والواتساب)
        $mpdf = $this->makeMpdf('A5-L', 6, 6, 6, 6, $viewData);
        $mpdf->WriteHTML($html);

        // ضمان صفحة واحدة 1/1 دائماً حتى لو كانت الملاحظات أو الشروط طويلة جداً
        if ($mpdf->page > 1) {
            $mpdf = $this->makeMpdf('A4', 8, 8, 8, 8, $viewData);
            $mpdf->WriteHTML($html);
        }

        return (string) $mpdf->Output($this->filename($parcel), Destination::STRING_RETURN);
    }

    /**
     * اسم الملف الآمن لتنزيل أو إرسال السند.
     */
    public function filename(OutgoingParcel $parcel): string
    {
        $normalized = $this->normalizeDigits((string) ($parcel->receipt_number ?: $parcel->id));
        $safe = preg_replace('/[^A-Za-z0-9_-]/', '', $normalized) ?: 'parcel';

        return "receipt-{$safe}.pdf";
    }

    /**
     * تجهيز مثيل mPDF بإعدادات RTL والخط العربي المتصل (XB Riyaz) ومجلد مؤقت مضمون.
     *
     * @param  array<string, mixed>  $viewData
     */
    protected function makeMpdf(
        string $format,
        float $marginLeft,
        float $marginRight,
        float $marginTop,
        float $marginBottom,
        array $viewData
    ): Mpdf {
        $tempDir = storage_path('framework/cache/mpdf');
        File::ensureDirectoryExists($tempDir);

        $mpdf = new Mpdf([
            'mode'              => 'utf-8',
            'format'            => $format,
            'margin_left'       => $marginLeft,
            'margin_right'      => $marginRight,
            'margin_top'        => $marginTop,
            'margin_bottom'     => $marginBottom,
            'margin_header'     => 0,
            'margin_footer'     => 0,
            'default_font'      => 'xbriyaz',
            'default_font_size' => 9.5,
            'autoScriptToLang'  => true,
            'autoLangToFont'    => true,
            'tempDir'           => $tempDir,
        ]);

        $mpdf->SetDirectionality('rtl');
        $mpdf->SetDisplayMode('fullpage');
        $mpdf->SetTitle(trim("{$viewData['receiptTitle']} #{$viewData['receiptNumber']} - {$viewData['officeName']}"));
        $mpdf->SetAuthor((string) $viewData['officeName']);
        $mpdf->SetCreator('Mursal Pro');

        return $mpdf;
    }

    /**
     * تجميع بيانات السند المطابقة لقالب السند المعتمد في الواجهة.
     *
     * @return array<string, mixed>
     */
    protected function buildViewData(OutgoingParcel $parcel, string $copyLabel): array
    {
        /** @var Office|null $office */
        $office = $parcel->office;
        /** @var Office|null $destOffice */
        $destOffice = $parcel->destinationOffice;

        $senderProfile = $office ? $office->getResolvedProfile() : [
            'logo_path'         => null,
            'icon_path'         => null,
            'logo_url'          => null,
            'icon_url'          => null,
            'primary_color'     => OfficeProfile::DEFAULT_PRIMARY_COLOR,
            'secondary_color'   => OfficeProfile::DEFAULT_SECONDARY_COLOR,
            'tagline_primary'   => OfficeProfile::DEFAULT_TAGLINE_1,
            'tagline_secondary' => OfficeProfile::DEFAULT_TAGLINE_2,
            'branch_address'    => '',
            'complaints_phone'  => '',
            'dispatch_phone_1'  => '',
            'dispatch_phone_2'  => '',
            'delivery_phone'    => '',
            'receipt_title'     => OfficeProfile::DEFAULT_RECEIPT_TITLE,
            'slogan'            => OfficeProfile::DEFAULT_SLOGAN,
            'receipt_terms'     => OfficeProfile::DEFAULT_TERMS,
            'footer_text'       => OfficeProfile::DEFAULT_FOOTER_TEXT,
        ];
        $destProfile = $destOffice ? $destOffice->getResolvedProfile() : null;

        $brandPrimary = OfficeProfile::sanitizeHex($senderProfile['primary_color'] ?? null, OfficeProfile::DEFAULT_PRIMARY_COLOR);
        $brandSecondary = OfficeProfile::sanitizeHex($senderProfile['secondary_color'] ?? null, OfficeProfile::DEFAULT_SECONDARY_COLOR);

        $officeName = trim((string) ($office?->name ?? 'مكتب مُرسَل'));
        $branchName = trim((string) ($office?->branch_name ?? ''));
        $tagline1 = trim((string) ($senderProfile['tagline_primary'] ?? OfficeProfile::DEFAULT_TAGLINE_1));
        $tagline2 = trim((string) ($senderProfile['tagline_secondary'] ?? OfficeProfile::DEFAULT_TAGLINE_2));
        $managerPhone = trim((string) ($senderProfile['complaints_phone'] ?? ''));
        $dispatchPhone1 = trim((string) ($senderProfile['dispatch_phone_1'] ?? ''));
        $dispatchPhone2 = trim((string) ($senderProfile['dispatch_phone_2'] ?? ''));
        $officeAddress = trim((string) ($senderProfile['branch_address'] ?? ''));

        $parcelUuid = trim((string) ($parcel->id ?? ''));
        $receiptNumber = trim((string) ($parcel->receipt_number ?? '0000')) ?: '0000';
        $barcodeCode = $this->sanitizeCode128($receiptNumber);

        $createdTime = $parcel->created_at ?? now();
        $dateStr = $createdTime->format('Y/m/d');
        $timeStr = $createdTime->format('H:i');

        $destinationLabel = method_exists($parcel, 'destinationLabel')
            ? trim((string) $parcel->destinationLabel())
            : '';
        $destination = trim((string) ($destOffice?->branch_name ?: $destinationLabel)) ?: '—';

        $paymentEnum = $parcel->payment_type instanceof OutgoingPaymentType
            ? $parcel->payment_type
            : (OutgoingPaymentType::tryFrom((string) ($parcel->payment_type ?? '')) ?? OutgoingPaymentType::PAID);

        $isDeferred = $paymentEnum === OutgoingPaymentType::DEFERRED;
        $rawCodAmount = (float) ($parcel->cod_amount ?? 0);
        $formattedCod = number_format($rawCodAmount);

        $amountDisplay = $isDeferred
            ? ($rawCodAmount > 0 ? "آجل ({$formattedCod} ر.ي)" : 'آجل')
            : ($rawCodAmount > 0 ? "محاسب ({$formattedCod} ر.ي)" : 'محاسب نقداً');

        $recipientName = trim((string) ($parcel->recipient_name ?? '')) ?: 'بدون اسم';
        $recipientPhone = trim((string) ($parcel->recipient_phone ?? ''));
        $senderName = trim((string) ($parcel->sender_name ?? '')) ?: '—';
        $senderPhone = trim((string) ($parcel->sender_phone ?? ''));
        $packageType = trim((string) ($parcel->package_type ?? '')) ?: 'طرد';
        $parcelNotes = trim((string) ($parcel->notes ?? ''));

        $resolvedDeliveryLoc = $destinationLabel !== '' ? $destinationLabel : $destination;
        if ($destOffice && $destProfile && trim((string) ($destProfile['branch_address'] ?? '')) !== '') {
            $destAddr = trim((string) $destProfile['branch_address']);
            $resolvedDeliveryLoc = str_contains($destAddr, (string) $destOffice->name)
                ? $destAddr
                : ($destOffice->name . ' - ' . $destAddr);
        }

        $deliveryPhone = trim((string) ($destProfile['delivery_phone'] ?? ''));
        $receiptTitle = trim((string) ($senderProfile['receipt_title'] ?? OfficeProfile::DEFAULT_RECEIPT_TITLE))
            ?: OfficeProfile::DEFAULT_RECEIPT_TITLE;
        $employeeName = $officeName !== '' ? $officeName : 'المكتب الرئيسي';
        $slogan = trim((string) ($senderProfile['slogan'] ?? OfficeProfile::DEFAULT_SLOGAN))
            ?: OfficeProfile::DEFAULT_SLOGAN;
        $footerText = trim((string) ($senderProfile['footer_text'] ?? OfficeProfile::DEFAULT_FOOTER_TEXT))
            ?: OfficeProfile::DEFAULT_FOOTER_TEXT;
        $terms = ! empty($senderProfile['receipt_terms'])
            ? array_values($senderProfile['receipt_terms'])
            : OfficeProfile::DEFAULT_TERMS;

        $logoLocalPath = $this->resolveLocalLogoPath($senderProfile);

        return [
            'brandPrimary'   => $brandPrimary,
            'brandSecondary' => $brandSecondary,
            'logoLocalPath'  => $logoLocalPath,
            'officeName'     => $officeName,
            'branchName'     => $branchName,
            'tagline1'       => $tagline1,
            'tagline2'       => $tagline2,
            'managerPhone'   => $managerPhone,
            'dispatchPhone1' => $dispatchPhone1,
            'dispatchPhone2' => $dispatchPhone2,
            'officeAddress'  => $officeAddress,
            'parcelUuid'     => $parcelUuid,
            'receiptNumber'  => $receiptNumber,
            'barcodeCode'    => $barcodeCode,
            'dateStr'        => $dateStr,
            'timeStr'        => $timeStr,
            'destination'    => $destination,
            'isDeferred'     => $isDeferred,
            'amountDisplay'  => $amountDisplay,
            'recipientName'  => $recipientName,
            'recipientPhone' => $recipientPhone,
            'senderName'     => $senderName,
            'senderPhone'    => $senderPhone,
            'packageType'    => $packageType,
            'parcelNotes'    => $parcelNotes,
            'deliveryLoc'    => $resolvedDeliveryLoc,
            'deliveryPhone'  => $deliveryPhone,
            'receiptTitle'   => $receiptTitle,
            'copyLabel'      => $copyLabel,
            'employeeName'   => $employeeName,
            'slogan'         => $slogan,
            'footerText'     => $footerText,
            'terms'          => $terms,
        ];
    }

    /**
     * حل مسار الشعار على القرص المحلي مباشرة (دون طلب HTTP خارجي أو loopback).
     *
     * @param  array<string, mixed>  $profile
     */
    protected function resolveLocalLogoPath(array $profile): ?string
    {
        $candidates = [
            $profile['logo_path'] ?? null,
            $profile['icon_path'] ?? null,
        ];

        $allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'];

        foreach ($candidates as $candidate) {
            $clean = trim((string) $candidate);
            if ($clean === '' || str_starts_with($clean, 'http://') || str_starts_with($clean, 'https://')) {
                continue;
            }

            $pathsToTry = [
                Storage::disk('public')->path(ltrim($clean, '/')),
                public_path(ltrim($clean, '/')),
            ];

            foreach ($pathsToTry as $fullPath) {
                if (! is_file($fullPath) || ! is_readable($fullPath)) {
                    continue;
                }

                $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
                if (in_array($ext, $allowedExtensions, true)) {
                    return $fullPath;
                }
            }
        }

        return null;
    }

    protected function sanitizeCode128(string $value): string
    {
        $normalized = $this->normalizeDigits(trim($value));
        $asciiOnly = preg_replace('/[^\x20-\x7E]/', '', $normalized) ?? '';

        return $asciiOnly !== '' ? $asciiOnly : '0000';
    }

    protected function normalizeDigits(string $value): string
    {
        return strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
    }
}
