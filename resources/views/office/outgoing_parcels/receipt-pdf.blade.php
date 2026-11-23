<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: xbriyaz, dejavusans, sans-serif;
            direction: rtl;
            color: #0f172a;
            font-size: 9.5pt;
            line-height: 1.45;
            margin: 0;
            padding: 0;
            background: #ffffff;
        }

        .receipt-card {
            border: 2px solid {{ $brandPrimary }};
            border-radius: 10px;
            padding: 10px 14px;
            background: #ffffff;
        }

        table {
            border-collapse: collapse;
        }

        .header-table {
            width: 100%;
            border-bottom: 1.2px solid #cbd5e1;
            margin-bottom: 6px;
        }

        .header-table td {
            vertical-align: top;
            padding-bottom: 7px;
        }

        .office-name {
            font-size: 14.5pt;
            font-weight: bold;
            color: {{ $brandPrimary }};
            line-height: 1.3;
        }

        .office-branch {
            font-size: 8.8pt;
            font-weight: bold;
            color: {{ $brandSecondary }};
            margin-top: 1px;
        }

        .tagline-1 {
            font-size: 8.8pt;
            font-weight: bold;
            color: #334155;
            margin-top: 3px;
        }

        .tagline-2 {
            font-size: 8pt;
            color: #64748b;
            margin-top: 1px;
        }

        .voucher-badge {
            border: 1.8px solid {{ $brandPrimary }};
            border-radius: 8px;
            padding: 4px 12px;
            background: #ffffff;
            text-align: center;
            margin-top: 4px;
        }

        .voucher-title {
            font-size: 12pt;
            font-weight: bold;
            color: {{ $brandPrimary }};
            line-height: 1.25;
        }

        .voucher-copy {
            font-size: 8pt;
            font-weight: bold;
            color: #475569;
            margin-top: 1px;
        }

        .meta-table {
            width: 100%;
        }

        .meta-table td {
            padding: 1.2px 0;
            vertical-align: middle;
        }

        .meta-label {
            font-size: 7.8pt;
            color: #64748b;
            font-weight: bold;
            text-align: right;
            white-space: nowrap;
        }

        .meta-value {
            font-size: 8.4pt;
            color: #0f172a;
            font-weight: bold;
            text-align: left;
            font-family: dejavusansmono, xbriyaz, sans-serif;
            direction: ltr;
            white-space: nowrap;
        }

        .office-address {
            font-size: 8pt;
            font-weight: bold;
            color: #334155;
            border-top: 0.7px solid #e2e8f0;
            padding-top: 3px;
            margin-top: 2px;
            text-align: right;
        }

        .receipt-pill {
            background-color: {{ $brandPrimary }};
            color: #ffffff;
            border-radius: 5px;
            padding: 2.5px 8px;
            font-family: dejavusansmono, sans-serif;
            font-size: 9pt;
            font-weight: bold;
            direction: ltr;
            text-align: center;
            white-space: nowrap;
        }

        .receipt-pill-accent {
            color: {{ $brandSecondary }};
        }

        .invoice-table {
            width: 100%;
            margin-top: 2px;
        }

        .invoice-table td {
            padding: 5.5px 3px;
            vertical-align: middle;
            border-bottom: 1px solid #cbd5e1;
        }

        .invoice-table tr.last-row td {
            border-bottom: none;
            padding-bottom: 4px;
        }

        .field-label {
            font-size: 8.5pt;
            font-weight: bold;
            color: #64748b;
        }

        .field-value {
            font-size: 10pt;
            font-weight: bold;
            color: #0f172a;
        }

        .amount-deferred {
            color: #92400e;
        }

        .phone-pill {
            border: 1.2px solid #475569;
            border-radius: 6px;
            padding: 2px 9px;
            background-color: #f8fafc;
            color: #0f172a;
            font-family: dejavusansmono, sans-serif;
            font-size: 9.2pt;
            font-weight: bold;
            direction: ltr;
            text-align: center;
            white-space: nowrap;
        }

        .delivery-phone-pill {
            border: 1.2px solid #fecdd3;
            border-radius: 6px;
            padding: 2.5px 10px;
            background-color: #fff1f2;
            color: #be123c;
            font-family: dejavusansmono, sans-serif;
            font-size: 9pt;
            font-weight: bold;
            direction: ltr;
            text-align: center;
            white-space: nowrap;
        }

        .terms-table {
            width: 100%;
            border-top: 1.8px solid {{ $brandPrimary }};
            margin-top: 4px;
        }

        .terms-table td {
            padding-top: 6px;
            vertical-align: middle;
        }

        .terms-list {
            font-size: 7.7pt;
            font-weight: bold;
            color: #475569;
            line-height: 1.5;
        }

        .qr-card {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 4px;
            background: #ffffff;
            text-align: center;
        }

        .qr-caption {
            font-size: 6.8pt;
            color: #64748b;
            margin-top: 2px;
        }

        .footer-table {
            width: 100%;
            border-top: 1px solid #e2e8f0;
            margin-top: 6px;
        }

        .footer-table td {
            padding-top: 5px;
            vertical-align: middle;
            font-size: 8.3pt;
        }

        .slogan-text {
            font-size: 9.2pt;
            font-weight: bold;
            color: {{ $brandPrimary }};
            text-align: center;
        }

        .footer-brand {
            font-size: 7.8pt;
            font-weight: bold;
            color: #64748b;
            text-align: left;
            white-space: nowrap;
        }

        .ltr {
            direction: ltr;
            unicode-bidi: embed;
        }
    </style>
</head>
<body>
    <div class="receipt-card">

        {{-- 1. الترويسة العلوية (3 أعمدة متوازنة ومحصنة ضد القص) --}}
        <table class="header-table">
            <tr>
                {{-- اليمين: اسم المكتب والفرع والوصف --}}
                <td style="width: 34%; text-align: right; padding-left: 8px;">
                    <div class="office-name">{{ $officeName }}</div>
                    @if ($branchName !== '' && ! str_contains($officeName, $branchName))
                        <div class="office-branch">فرع {{ $branchName }}</div>
                    @endif
                    @if ($tagline1 !== '')
                        <div class="tagline-1">{{ $tagline1 }}</div>
                    @endif
                    @if ($tagline2 !== '')
                        <div class="tagline-2">{{ $tagline2 }}</div>
                    @endif
                </td>

                {{-- الوسط: الشعار وكبسولة نوع السند --}}
                <td style="width: 24%; text-align: center; padding-left: 6px; padding-right: 6px;">
                    @if ($logoLocalPath)
                        <div style="margin-bottom: 3px;">
                            <img src="{{ $logoLocalPath }}" alt="{{ $officeName }}" style="max-height: 34px; max-width: 72px;">
                        </div>
                    @else
                        <div style="margin-bottom: 3px;">
                            <svg width="32" height="32" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg">
                                <polygon points="32,6 56,19 56,45 32,58 8,45 8,19" fill="{{ $brandPrimary }}" stroke="#0f172a" stroke-width="2" />
                                <polygon points="32,6 56,19 32,32 8,19" fill="#2563eb" />
                                <polygon points="56,19 56,45 32,58 32,32" fill="#ef4444" />
                                <polygon points="34,15 22,35 31,35 29,49 42,29 33,29" fill="{{ $brandSecondary }}" />
                            </svg>
                        </div>
                    @endif

                    <div class="voucher-badge">
                        <div class="voucher-title">{{ $receiptTitle }}</div>
                        <div class="voucher-copy">{{ $copyLabel }}</div>
                    </div>
                </td>

                {{-- اليسار: أرقام التواصل والعنوان والتاريخ ورقم السند والباركود --}}
                <td style="width: 42%; text-align: left; padding-right: 8px;">
                    <table class="meta-table">
                        @if ($managerPhone !== '')
                            <tr>
                                <td class="meta-label">المدير والشكاوى:</td>
                                <td class="meta-value"><span class="ltr">{{ $managerPhone }}</span></td>
                            </tr>
                        @endif

                        @if ($dispatchPhone1 !== '')
                            <tr>
                                <td class="meta-label">خدمة الإرسال:</td>
                                <td class="meta-value"><span class="ltr">{{ $dispatchPhone1 }}</span></td>
                            </tr>
                        @endif

                        @if ($dispatchPhone2 !== '')
                            <tr>
                                <td class="meta-label"></td>
                                <td class="meta-value" style="color: #475569; font-size: 7.8pt;"><span class="ltr">{{ $dispatchPhone2 }}</span></td>
                            </tr>
                        @endif
                    </table>

                    @if ($officeAddress !== '')
                        <div class="office-address">{{ $officeAddress }}</div>
                    @endif

                    <table class="meta-table" style="margin-top: 3px;">
                        <tr>
                            <td style="text-align: right; font-size: 7.9pt; font-weight: bold; color: #334155;">
                                التاريخ: <span class="ltr" style="font-family: dejavusansmono, sans-serif;">{{ $dateStr }}</span>
                            </td>
                            <td style="text-align: left; font-size: 7.9pt; font-weight: bold; color: #334155;">
                                الوقت: <span class="ltr" style="font-family: dejavusansmono, sans-serif;">{{ $timeStr }}</span>
                            </td>
                        </tr>
                    </table>

                    <table class="meta-table" style="margin-top: 4px;">
                        <tr>
                            <td style="text-align: right; vertical-align: middle; width: 42%;">
                                <div class="receipt-pill">
                                    <span class="receipt-pill-accent">#</span>{{ $receiptNumber }}
                                </div>
                            </td>
                            <td style="text-align: left; vertical-align: middle; width: 58%;">
                                <barcode code="{{ $barcodeCode }}" type="C128B" size="0.68" height="0.58" />
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        {{-- 2. جدول تفاصيل الطرد --}}
        <table class="invoice-table">
            <tr>
                <td style="width: 60%; text-align: right;">
                    <span class="field-label">المحافظة / الوجهة: </span>
                    <span class="field-value">{{ $destination }}</span>
                </td>
                <td style="width: 40%; text-align: left; white-space: nowrap;">
                    <span class="field-label">المبلغ: </span>
                    <span class="field-value {{ $isDeferred ? 'amount-deferred' : '' }}">{{ $amountDisplay }}</span>
                </td>
            </tr>

            <tr>
                <td style="text-align: right;">
                    <span class="field-label">اسم المستلم: </span>
                    <span class="field-value">{{ $recipientName }}</span>
                </td>
                <td style="text-align: left;">
                    @if ($recipientPhone !== '')
                        <table style="margin-right: auto;">
                            <tr>
                                <td class="phone-pill"><span class="ltr">{{ $recipientPhone }}</span></td>
                            </tr>
                        </table>
                    @endif
                </td>
            </tr>

            <tr>
                <td style="text-align: right;">
                    <span class="field-label">اسم المرسل: </span>
                    <span class="field-value">{{ $senderName }}</span>
                </td>
                <td style="text-align: left;">
                    @if ($senderPhone !== '')
                        <table style="margin-right: auto;">
                            <tr>
                                <td class="phone-pill"><span class="ltr">{{ $senderPhone }}</span></td>
                            </tr>
                        </table>
                    @endif
                </td>
            </tr>

            <tr>
                <td style="text-align: right;">
                    <span class="field-label">نوع الرسالة: </span>
                    <span class="field-value">{{ $packageType }}</span>
                </td>
                <td style="text-align: left;">
                    @if ($parcelNotes !== '')
                        <span class="field-label">ملاحظة: </span>
                        <span style="font-size: 8.5pt; font-weight: bold; color: #334155;">{{ $parcelNotes }}</span>
                    @endif
                </td>
            </tr>

            <tr class="last-row">
                <td style="text-align: right; {{ $deliveryPhone === '' ? '' : 'width: 68%;' }}">
                    <div class="field-label" style="font-size: 7.8pt;">موقع التسليم:</div>
                    <div class="field-value" style="font-size: 9.2pt; margin-top: 1px;">{{ $deliveryLoc }}</div>
                </td>
                <td style="text-align: left; vertical-align: middle;">
                    @if ($deliveryPhone !== '')
                        <table style="margin-right: auto;">
                            <tr>
                                <td class="delivery-phone-pill"><span class="ltr">{{ $deliveryPhone }}</span></td>
                            </tr>
                        </table>
                    @endif
                </td>
            </tr>
        </table>

        {{-- 3. الشروط والأحكام + رمز QR السريع --}}
        <table class="terms-table">
            <tr>
                <td style="width: {{ $parcelUuid !== '' ? '80%' : '100%' }}; text-align: right; padding-left: 10px;">
                    <div class="terms-list">
                        @foreach ($terms as $term)
                            <div>{{ $term }}</div>
                        @endforeach
                    </div>
                </td>

                @if ($parcelUuid !== '')
                    <td style="width: 20%; text-align: left; vertical-align: middle;">
                        <table style="margin-right: auto;">
                            <tr>
                                <td class="qr-card">
                                    <barcode code="{{ $parcelUuid }}" type="QR" size="0.68" error="M" disableborder="1" />
                                    <div class="qr-caption">مسح سريع</div>
                                </td>
                            </tr>
                        </table>
                    </td>
                @endif
            </tr>
        </table>

        {{-- 4. التذييل السفلي --}}
        <table class="footer-table">
            <tr>
                <td style="width: 34%; text-align: right; white-space: nowrap;">
                    <span style="color: #64748b; font-weight: bold;">الموظف: </span>
                    <span style="font-weight: bold; color: #0f172a;">{{ $employeeName }}</span>
                </td>
                <td style="width: 36%;" class="slogan-text">
                    "{{ $slogan }}"
                </td>
                <td style="width: 30%;" class="footer-brand">
                    {{ $footerText }}
                </td>
            </tr>
        </table>

    </div>
</body>
</html>
