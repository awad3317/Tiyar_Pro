<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سند شحنة #{{ $outgoingParcel->receipt_number }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@400;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Almarai', sans-serif;
            background: #f1f5f9;
            color: #041627;
            padding: 16px;
        }

        .toolbar {
            max-width: 340px;
            margin: 0 auto 14px;
            display: flex;
            gap: 8px;
        }

        .btn {
            flex: 1;
            border: none;
            border-radius: 12px;
            padding: 10px 14px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
        }

        .btn-print {
            background: #041627;
            color: #fe9d20;
        }

        .btn-back {
            background: #e2e8f0;
            color: #334155;
            flex: 0.6;
        }

        .receipt {
            width: 100%;
            max-width: 340px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            padding: 14px;
        }

        .header {
            text-align: center;
            border-bottom: 2px dashed #cbd5e1;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }

        .office-name {
            font-size: 17px;
            font-weight: 800;
            color: #041627;
        }

        .office-branch {
            font-size: 12px;
            color: #475569;
            margin-top: 2px;
        }

        .receipt-badge {
            display: inline-block;
            margin-top: 8px;
            padding: 4px 12px;
            background: #041627;
            color: #fff;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 800;
        }

        .meta-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            padding: 5px 0;
            border-bottom: 1px solid #f1f5f9;
            gap: 8px;
        }

        .meta-label {
            color: #64748b;
            font-weight: 700;
        }

        .meta-val {
            font-weight: 800;
            color: #041627;
            text-align: left;
        }

        .box {
            margin-top: 8px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 8px 10px;
            background: #f8fafc;
        }

        .box-title {
            font-size: 10px;
            color: #64748b;
            font-weight: 800;
            margin-bottom: 2px;
        }

        .box-name {
            font-size: 13px;
            font-weight: 800;
        }

        .box-phone {
            font-size: 12px;
            font-family: monospace;
            direction: ltr;
            text-align: right;
            color: #334155;
        }

        .amount-box {
            margin-top: 10px;
            padding: 8px 10px;
            border-radius: 10px;
            border: 1.5px solid #041627;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            font-weight: 800;
        }

        .codes-section {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 2px dashed #cbd5e1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }

        #qr-container {
            padding: 6px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #fff;
        }

        .uuid-text {
            font-family: monospace;
            font-size: 9px;
            color: #475569;
            direction: ltr;
            word-break: break-all;
            text-align: center;
        }

        .footer-note {
            margin-top: 10px;
            text-align: center;
            font-size: 10px;
            color: #64748b;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            .toolbar {
                display: none !important;
            }

            .receipt {
                border: none;
                border-radius: 0;
                max-width: 78mm;
                padding: 4mm 3mm;
            }
        }
    </style>
</head>

<body>
    @php
        $paymentEnum =
            $outgoingParcel->payment_type instanceof \App\Enums\OutgoingPaymentType
                ? $outgoingParcel->payment_type
                : \App\Enums\OutgoingPaymentType::tryFrom((string) $outgoingParcel->payment_type) ??
                    \App\Enums\OutgoingPaymentType::PAID;
    @endphp

    <div class="toolbar">
        <button type="button" class="btn btn-print" onclick="window.print()">طباعة السند والباركود</button>
        <a href="{{ route('office.outgoing-parcels.index') }}" class="btn btn-back">رجوع</a>
    </div>

    <div class="receipt">
        <div class="header">
            <div class="office-name">{{ $office->name }}</div>
            @if ($office->branch_name)
                <div class="office-branch">فرع: {{ $office->branch_name }}</div>
            @endif
            <div class="receipt-badge">سند طرد #{{ $outgoingParcel->receipt_number }}</div>
        </div>

        <div class="meta-row">
            <span class="meta-label">التاريخ:</span>
            <span class="meta-val" dir="ltr">{{ $outgoingParcel->created_at?->format('Y/m/d h:i A') }}</span>
        </div>
        <div class="meta-row">
            <span class="meta-label">الوجهة:</span>
            <span class="meta-val">{{ $outgoingParcel->destinationLabel() }}</span>
        </div>
        <div class="meta-row">
            <span class="meta-label">نوع الطرد:</span>
            <span class="meta-val">{{ $outgoingParcel->package_type }}</span>
        </div>

        <div class="box">
            <div class="box-title">المستلم</div>
            <div class="box-name">{{ $outgoingParcel->recipient_name ?: 'بدون اسم' }}</div>
            <div class="box-phone">{{ $outgoingParcel->recipient_phone }}</div>
        </div>

        <div class="box">
            <div class="box-title">المرسل</div>
            <div class="box-name">{{ $outgoingParcel->sender_name }}</div>
            <div class="box-phone">{{ $outgoingParcel->sender_phone }}</div>
        </div>

        <div class="amount-box">
            <span>الحساب ({{ $paymentEnum->label() }})</span>
            <span dir="ltr">{{ number_format((float) ($outgoingParcel->cod_amount ?? 0)) }} ر.ي</span>
        </div>

        @if ($outgoingParcel->notes)
            <div class="box" style="margin-top:8px;">
                <div class="box-title">ملاحظات</div>
                <div style="font-size:11px;font-weight:700;">{{ $outgoingParcel->notes }}</div>
            </div>
        @endif

        <div class="codes-section">
            <div id="qr-container"></div>
            <svg id="barcode-1d"></svg>
            <div class="uuid-text">{{ $outgoingParcel->id }}</div>
        </div>

        <div class="footer-note">
            يُمسح الباركود عند تجهيز الشحنة وعند الاستلام بالفرع المستلم
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <script>
        (function() {
            var parcelUuid = @json((string) $outgoingParcel->id);
            var receiptNum = @json((string) $outgoingParcel->receipt_number);

            if (window.QRCode) {
                new QRCode(document.getElementById('qr-container'), {
                    text: parcelUuid,
                    width: 128,
                    height: 128,
                    colorDark: '#041627',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.M
                });
            }

            if (window.JsBarcode && receiptNum) {
                JsBarcode('#barcode-1d', receiptNum, {
                    format: 'CODE128',
                    width: 1.8,
                    height: 36,
                    displayValue: true,
                    fontSize: 12,
                    margin: 2,
                    lineColor: '#041627'
                });
            }
        })();
    </script>
</body>

</html>
