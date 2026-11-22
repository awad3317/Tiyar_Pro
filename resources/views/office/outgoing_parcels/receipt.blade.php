@php
    $parcel = $outgoingParcel ?? $parcel ?? null;
    $office = $office ?? $parcel?->office ?? auth('office')->user() ?? rescue(fn () => \App\Models\Office::first(), null, false);
    $destOffice = $parcel?->destinationOffice;

    // جلب هوية المكتب المرسل وهوية الفرع المستلم الشريك (من الكاش الدائم)
    $senderProfile = $office ? $office->getResolvedProfile() : [
        'logo_url'          => null,
        'icon_url'          => null,
        'primary_color'     => \App\Models\OfficeProfile::DEFAULT_PRIMARY_COLOR,
        'secondary_color'   => \App\Models\OfficeProfile::DEFAULT_SECONDARY_COLOR,
        'tagline_primary'   => \App\Models\OfficeProfile::DEFAULT_TAGLINE_1,
        'tagline_secondary' => \App\Models\OfficeProfile::DEFAULT_TAGLINE_2,
        'branch_address'    => '',
        'complaints_phone'  => '',
        'dispatch_phone_1'  => '',
        'dispatch_phone_2'  => '',
        'delivery_phone'    => '',
        'receipt_title'     => \App\Models\OfficeProfile::DEFAULT_RECEIPT_TITLE,
        'slogan'            => \App\Models\OfficeProfile::DEFAULT_SLOGAN,
        'receipt_terms'     => \App\Models\OfficeProfile::DEFAULT_TERMS,
        'footer_text'       => \App\Models\OfficeProfile::DEFAULT_FOOTER_TEXT,
    ];
    $destProfile = $destOffice ? $destOffice->getResolvedProfile() : null;

    // ألوان وشعار هوية المكتب المرسل
    $brandPrimary   = $senderProfile['primary_color'] ?? '#041627';
    $brandSecondary = $senderProfile['secondary_color'] ?? '#fe9d20';
    $brandLogoUrl   = $senderProfile['logo_url'] ?? $senderProfile['icon_url'] ?? null;
    $brandIconUrl   = $senderProfile['icon_url'] ?? $senderProfile['logo_url'] ?? asset('icons/icon-192.png');

    // بيانات المكتب المرسل
    $officeName     = $office_name ?? request('office_name', $office?->name ?? 'مكتب مُرسَل');
    $tagline1       = $tagline1 ?? request('tagline1', $senderProfile['tagline_primary']);
    $tagline2       = $tagline2 ?? request('tagline2', $senderProfile['tagline_secondary']);
    $managerPhone   = $manager_phone ?? request('manager_phone', $senderProfile['complaints_phone']);
    $dispatchPhone1 = $dispatch_phone1 ?? request('dispatch_phone1', $senderProfile['dispatch_phone_1']);
    $dispatchPhone2 = $dispatch_phone2 ?? request('dispatch_phone2', $senderProfile['dispatch_phone_2']);
    $officeAddress  = $address ?? request('address', $senderProfile['branch_address']);

    // بيانات الطرد الصادر والسند
    $parcelUuid     = (string) ($parcel?->id ?? '');
    $receiptNumber  = (string) ($receipt_number ?? request('receipt_number', $parcel?->receipt_number ?? '0000'));
    $createdTime    = $parcel?->created_at ?? now();
    $dateStr        = $date ?? request('date', $createdTime->format('Y/m/d'));
    $timeStr        = $time ?? request('time', $createdTime->format('H:i'));

    $destinationLabel = ($parcel && method_exists($parcel, 'destinationLabel'))
        ? $parcel->destinationLabel()
        : ($parcel?->destination ?? '');
    $destination    = $destination ?? request('destination', $destOffice?->branch_name ?: $destinationLabel ?: '—');

    $paymentEnum = $parcel?->payment_type instanceof \App\Enums\OutgoingPaymentType
        ? $parcel->payment_type
        : (\App\Enums\OutgoingPaymentType::tryFrom((string) ($parcel?->payment_type ?? '')) ?? \App\Enums\OutgoingPaymentType::PAID);

    $isDeferred   = $paymentEnum === \App\Enums\OutgoingPaymentType::DEFERRED;
    $rawCodAmount = (float) ($parcel?->cod_amount ?? 0);
    $formattedCod = number_format($rawCodAmount);

    $defaultAmountText = $isDeferred
        ? ($rawCodAmount > 0 ? "آجل ({$formattedCod} ر.ي)" : 'آجل')
        : ($rawCodAmount > 0 ? "محاسب ({$formattedCod} ر.ي)" : 'محاسب نقداً');
    $amountDisplay  = $amount ?? request('amount', $defaultAmountText);

    $recipientName  = $recipient_name ?? request('recipient_name', $parcel?->recipient_name ?: 'بدون اسم');
    $recipientPhone = $recipient_phone ?? request('recipient_phone', $parcel?->recipient_phone ?? '');
    $senderName     = $sender_name ?? request('sender_name', $parcel?->sender_name ?? '—');
    $senderPhone    = $sender_phone ?? request('sender_phone', $parcel?->sender_phone ?? '');
    $packageType    = $package_type ?? request('package_type', $parcel?->package_type ?? 'طرد');
    $parcelNotes    = trim((string) ($parcel?->notes ?? ''));

    // موقع ورقم التسليم: يُسحب تلقائياً من ملف هوية الفرع المستلم الشريك إن وُجد
    $resolvedDeliveryLoc = $destinationLabel ?: $destination;
    if ($destOffice && $destProfile && trim((string) ($destProfile['branch_address'] ?? '')) !== '') {
        $destAddr = trim((string) $destProfile['branch_address']);
        $resolvedDeliveryLoc = str_contains($destAddr, (string) $destOffice->name)
            ? $destAddr
            : ($destOffice->name . ' - ' . $destAddr);
    }

    $deliveryLoc    = $delivery_location ?? request('delivery_location', $resolvedDeliveryLoc);
    $deliveryPhone  = $delivery_phone ?? request('delivery_phone', $destProfile['delivery_phone'] ?? '');

    // نوع السند والنسخة والتذييل والشروط
    $receiptTitle   = $receipt_title ?? request('title', $senderProfile['receipt_title']);
    $copyType       = $copy_type ?? request('copy', 'خاص بالمكتب');
    $employeeName   = $employee_name ?? request('employee', $office?->name ?? 'المكتب الرئيسي');
    $slogan         = $slogan ?? request('slogan', $senderProfile['slogan']);
    $footerText     = $footer_text ?? request('footer_text', $senderProfile['footer_text']);
    $terms          = ! empty($senderProfile['receipt_terms'])
        ? $senderProfile['receipt_terms']
        : \App\Models\OfficeProfile::DEFAULT_TERMS;

    // تعريف النسختين داخل نفس الصفحة دون الحاجة لأي ملف جزئي
    $copies = [
        [
            'card_id'    => 'receiptCard1',
            'receipt_id' => 'receipt-1',
            'label'      => $copyType,
            'hidden'     => false,
        ],
        [
            'card_id'    => 'receiptCard2',
            'receipt_id' => 'receipt-2',
            'label'      => ($copyType === 'خاص بالمكتب' ? 'نسخة العميل' : 'خاص بالمكتب'),
            'hidden'     => true,
        ],
    ];

    // مولد باركود خطي 1D قياسي (Code 128) كمتجه SVG نقي بدون أي مكتبات خارجية
    if (!function_exists('renderCode128Svg')) {
        function renderCode128Svg(string $text, int $height = 28, float $scale = 1.25): string {
            $patterns = [
                '212222','222122','222221','121223','121322','131222','122213','122312','132212','221213',
                '221312','231212','112232','122132','122231','113222','123122','123221','223211','221132',
                '221231','213212','223112','312131','311222','321122','321221','312212','322112','322211',
                '212123','212321','232121','111323','131123','131321','112313','132113','132311','211313',
                '231113','231311','112133','112331','132131','113123','113321','133121','313121','211331',
                '231131','213113','213311','213131','311123','311321','331121','312113','312311','332111',
                '314111','221411','431111','111224','111422','121124','121421','141122','141221','112214',
                '112412','122114','122411','142112','142211','241211','221114','413111','241112','134111',
                '111242','121142','121241','114212','124112','124211','411212','421112','421211','212141',
                '214121','412121','111143','111341','131141','114113','114311','411113','411311','113141',
                '114131','311141','411131','211412','211214','211232','2331112'
            ];

            $text = trim($text) !== '' ? trim($text) : '0000';
            $startB = 104;
            $stop = 106;
            $checksum = $startB;
            $encoded = [$patterns[$startB]];
            $len = strlen($text);

            for ($i = 0; $i < $len; $i++) {
                $val = ord($text[$i]) - 32;
                if ($val < 0 || $val > 95) $val = 0;
                $encoded[] = $patterns[$val];
                $checksum += $val * ($i + 1);
            }

            $encoded[] = $patterns[$checksum % 103];
            $encoded[] = $patterns[$stop];

            $modules = '';
            foreach ($encoded as $p) {
                $isBar = true;
                for ($k = 0; $k < strlen($p); $k++) {
                    $w = (int)$p[$k];
                    $modules .= str_repeat($isBar ? '1' : '0', $w);
                    $isBar = !$isBar;
                }
            }

            $totalWidth = round(strlen($modules) * $scale, 1);
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" class="barcode-svg" width="' . $totalWidth . '" height="' . $height . '" viewBox="0 0 ' . $totalWidth . ' ' . $height . '" preserveAspectRatio="none">';
            $x = 0;
            $modLen = strlen($modules);
            for ($m = 0; $m < $modLen; $m++) {
                if ($modules[$m] === '1') {
                    $svg .= '<rect x="' . round($x, 2) . '" y="0" width="' . $scale . '" height="' . $height . '" fill="#0f172a" />';
                }
                $x += $scale;
            }
            $svg .= '</svg>';

            return $svg;
        }
    }

    $barcodeSvg = renderCode128Svg($receiptNumber, 26, 1.25);
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="{{ $brandPrimary }}">
    <link rel="icon" type="image/png" href="{{ $brandIconUrl }}">
    <title>{{ $receiptTitle }} #{{ $receiptNumber }} - {{ $officeName }}</title>

    <!-- خطوط عربية وتنسيق Tailwind المحلي -->
    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@400;700;800&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">
    <script src="{{ asset('js/tailwind.js') }}"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: @json($brandPrimary),
                        secondary: @json($brandSecondary),
                    }
                }
            }
        };
    </script>

    <style>
        :root {
            --office-primary: {{ $brandPrimary }};
            --office-secondary: {{ $brandSecondary }};
        }

        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-adjust: exact !important;
            box-sizing: border-box;
        }

        body {
            font-family: 'Almarai', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f1f5f9;
            color: #0f172a;
            margin: 0;
            padding: 0;
        }

        .dir-ltr { direction: ltr !important; }
        .dir-rtl { direction: rtl !important; }

        /* حاوية السند الأساسية */
        .receipt-card {
            background: #ffffff;
            border: 2px solid var(--office-primary);
            border-radius: 14px;
            padding: 16px 20px;
            position: relative;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            width: 100%;
            max-width: 860px;
            margin: 0 auto;
            overflow: hidden;
        }

        /* صفوف بيانات الفاتورة */
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
        }

        .invoice-table tr {
            border-bottom: 1.5px solid #cbd5e1;
        }

        .invoice-table tr:last-child {
            border-bottom: none;
        }

        .invoice-table td {
            padding: 7px 4px;
            vertical-align: middle;
        }

        /* كبسولات أرقام الهواتف */
        .phone-badge {
            border: 1.5px solid #475569;
            border-radius: 9px;
            padding: 2px 10px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-weight: 800;
            font-size: 13px;
            background: #f8fafc;
            color: #0f172a;
            direction: ltr;
        }

        /* شارة هاتف موقع التسليم */
        .delivery-phone-badge {
            border: 1.5px solid #fecdd3;
            border-radius: 8px;
            padding: 3px 12px;
            display: inline-block;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-weight: 800;
            font-size: 13px;
            background: #fff1f2;
            color: #be123c;
            direction: ltr;
        }

        /* كبسولة نوع السند */
        .voucher-badge {
            border: 2px solid var(--office-primary);
            border-radius: 10px;
            padding: 4px 22px;
            text-align: center;
            display: inline-block;
            background: #ffffff;
        }

        /* العلامة المائية للوجو بالخلفية */
        .watermark-bg {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -45%);
            opacity: 0.06;
            pointer-events: none;
            z-index: 0;
            width: 280px;
            height: 280px;
        }

        /* وضع التعديل المباشر */
        [contenteditable="true"] {
            outline: 2px dashed var(--office-secondary) !important;
            outline-offset: 2px;
            background-color: rgba(254, 243, 199, 0.45);
            border-radius: 4px;
            padding: 1px 4px;
        }

        /* خط القص بين النسختين */
        .cut-line {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: #64748b;
            font-size: 11px;
            font-weight: bold;
            margin: 16px auto;
            max-width: 860px;
            position: relative;
        }

        .cut-line::before, .cut-line::after {
            content: "";
            flex: 1;
            border-bottom: 2px dashed #94a3b8;
        }

        /* معاينة وطباعة الوضع الحراري (Thermal 80mm) */
        body.thermal-mode .print-container {
            max-width: 340px !important;
            padding-left: 8px !important;
            padding-right: 8px !important;
        }
        body.thermal-mode .receipt-card {
            max-width: 320px !important;
            padding: 10px 12px !important;
        }
        body.thermal-mode .receipt-header {
            grid-template-columns: 1fr !important;
            gap: 8px !important;
        }
        body.thermal-mode .receipt-header .header-col {
            grid-column: span 12 / span 12 !important;
            text-align: center !important;
        }
        body.thermal-mode .invoice-table td {
            display: block;
            width: 100% !important;
            text-align: right !important;
            padding: 3px 0 !important;
        }

        /* تحسينات الطباعة الفعلية */
        @media print {
            @page {
                size: A4 portrait;
                margin: 6mm 8mm;
            }

            body {
                background: #ffffff !important;
                padding: 0 !important;
            }

            .no-print {
                display: none !important;
            }

            .print-container {
                padding: 0 !important;
                margin: 0 !important;
                max-width: 100% !important;
            }

            .receipt-card {
                box-shadow: none !important;
                border: 2px solid var(--office-primary) !important;
                border-radius: 8px !important;
                padding: 12px 16px !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                margin-bottom: 10px !important;
            }

            .watermark-bg {
                opacity: 0.06 !important;
            }

            body.thermal-mode .print-container,
            body.thermal-mode .receipt-card {
                max-width: 78mm !important;
                width: 78mm !important;
                margin: 0 auto !important;
                border: 1.5px solid #000000 !important;
                padding: 6px 8px !important;
            }
        }
    </style>
</head>
<body class="min-h-screen">

    <!-- شريط التحكم العلوي (يختفي تماماً عند الطباعة) -->
    <header class="no-print sticky top-0 z-50 bg-primary text-white border-b border-white/10 shadow-md">
        <div class="max-w-5xl mx-auto px-4 py-3 flex flex-wrap items-center justify-between gap-3">

            <!-- أزرار الإجراءات الرئيسية -->
            <div class="flex items-center gap-2">
                <button type="button" onclick="window.print()"
                    class="bg-secondary hover:brightness-105 text-primary font-black text-xs sm:text-sm px-4 py-2 rounded-xl flex items-center gap-1.5 shadow-md active:scale-95 transition-all">
                    <span class="material-symbols-outlined text-lg">print</span>
                    <span>طباعة السند</span>
                </button>

                <button type="button" id="toggleLiveEditBtn" onclick="toggleLiveEdit()"
                    class="bg-white/10 hover:bg-white/20 text-white font-bold text-xs px-3 py-2 rounded-xl flex items-center gap-1 border border-white/15 transition-colors">
                    <span class="material-symbols-outlined text-base text-secondary">edit_note</span>
                    <span id="liveEditText">تعديل مباشر</span>
                </button>
            </div>

            <!-- خيارات النسخ والورق -->
            <div class="flex items-center flex-wrap gap-2 text-xs font-bold">
                <span class="text-slate-300">النسخ:</span>
                <div class="bg-white/10 p-1 rounded-xl flex items-center gap-1 border border-white/15">
                    <button type="button" onclick="setCopiesMode('single-office')" id="btn-single-office"
                        class="copy-btn px-2.5 py-1 rounded-lg bg-secondary text-primary font-extrabold">خاص بالمكتب</button>
                    <button type="button" onclick="setCopiesMode('single-client')" id="btn-single-client"
                        class="copy-btn px-2.5 py-1 rounded-lg text-slate-200 hover:text-white">نسخة العميل</button>
                    <button type="button" onclick="setCopiesMode('double')" id="btn-double"
                        class="copy-btn px-2.5 py-1 rounded-lg text-slate-200 hover:text-white">نسختين معاً</button>
                </div>

                <button type="button" onclick="togglePaperSize()" id="paperSizeBtn"
                    class="bg-white/10 hover:bg-white/20 px-3 py-1.5 rounded-xl border border-white/15 text-white flex items-center gap-1 transition-colors">
                    <span class="material-symbols-outlined text-sm text-secondary">receipt_long</span>
                    <span id="paperSizeLabel">قياس A4 / A5</span>
                </button>
            </div>

            <!-- زر العودة -->
            <div class="flex items-center gap-2">
                @if (auth('office')->check())
                    <a href="{{ route('office.outgoing-parcels.index') }}"
                        class="text-slate-200 hover:text-secondary text-xs font-bold flex items-center gap-1 transition-colors">
                        <span class="material-symbols-outlined text-base">arrow_forward</span>
                        <span>الطرود الصادرة</span>
                    </a>
                @elseif (auth('web')->check())
                    <a href="{{ route('admin.parcels.index') }}"
                        class="text-slate-200 hover:text-secondary text-xs font-bold flex items-center gap-1 transition-colors">
                        <span class="material-symbols-outlined text-base">arrow_forward</span>
                        <span>مراقبة الطرود</span>
                    </a>
                @endif
            </div>

        </div>
    </header>

    <!-- مساحة عرض السند -->
    <main class="print-container max-w-4xl mx-auto py-6 px-3 sm:px-6">
        <div id="printArea" class="space-y-4">

            @foreach ($copies as $copy)
                @if ($loop->index === 1)
                    <!-- خط فاصل للقص (يظهر فقط في وضع النسختين) -->
                    <div id="cutLineDivider" class="cut-line hidden">
                        <span class="material-symbols-outlined text-sm transform -rotate-90">content_cut</span>
                        <span>قص هنا (فصل نسخة العميل عن نسخة المكتب)</span>
                        <span class="material-symbols-outlined text-sm transform rotate-90">content_cut</span>
                    </div>
                @endif

                <div class="receipt-card {{ $copy['hidden'] ? 'hidden' : '' }}" id="{{ $copy['card_id'] }}">
                    <div id="{{ $copy['receipt_id'] }}" class="relative z-10 text-slate-900">
                        {{-- العلامة المائية بالخلفية (شعار المكتب إن وُجد أو الشعار الافتراضي) --}}
                        <div class="watermark-bg flex items-center justify-center">
                            @if ($brandLogoUrl)
                                <img src="{{ $brandLogoUrl }}" alt="" class="w-full h-full object-contain">
                            @else
                                <svg viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full h-full">
                                    <path d="M60 12L106 36V84L60 108L14 84V36L60 12Z" fill="{{ $brandPrimary }}" />
                                    <path d="M60 12L106 36L60 60L14 36L60 12Z" fill="{{ $brandSecondary }}" />
                                    <path d="M64 32L44 64H58L54 90L76 56H62L64 32Z" fill="#ffffff" />
                                </svg>
                            @endif
                        </div>

                        {{-- 1. الترويسة العلوية --}}
                        <div class="receipt-header grid grid-cols-12 items-start gap-3 pb-3 border-b border-slate-200 relative z-10">

                            {{-- اليمين: اسم المكتب والوصف --}}
                            <div class="header-col col-span-4 text-right space-y-0.5 pt-1">
                                <h1 class="editable-field text-xl sm:text-2xl font-black text-primary leading-tight">
                                    {{ $officeName }}
                                </h1>
                                <p class="editable-field text-xs font-extrabold text-slate-600 leading-snug">
                                    {{ $tagline1 }}
                                </p>
                                <p class="editable-field text-[11px] font-bold text-slate-500 leading-snug">
                                    {{ $tagline2 }}
                                </p>
                            </div>

                            {{-- الوسط: الشعار وكبسولة نوع السند والنسخة --}}
                            <div class="header-col col-span-4 flex flex-col items-center justify-center text-center gap-1.5">
                                <div class="w-12 h-12 flex items-center justify-center">
                                    @if ($brandLogoUrl)
                                        <img src="{{ $brandLogoUrl }}" alt="{{ $officeName }}" class="max-w-full max-h-full object-contain">
                                    @else
                                        <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-10 h-10">
                                            <path d="M32 6L56 19V45L32 58L8 45V19L32 6Z" fill="{{ $brandPrimary }}" stroke="#0f172a" stroke-width="2" />
                                            <path d="M32 6L56 19L32 32L8 19L32 6Z" fill="#2563eb" />
                                            <path d="M56 19V45L32 58V32L56 19Z" fill="#ef4444" />
                                            <path d="M34 15L22 35H31L29 49L42 29H33L34 15Z" fill="{{ $brandSecondary }}" stroke="{{ $brandPrimary }}" stroke-width="1.5" stroke-linejoin="round" />
                                        </svg>
                                    @endif
                                </div>

                                <div class="voucher-badge shadow-2xs">
                                    <div class="editable-field text-base font-black text-primary leading-tight">
                                        {{ $receiptTitle }}
                                    </div>
                                    <div class="copy-badge-label text-[11px] font-bold text-slate-600 mt-0.5">
                                        {{ $copy['label'] }}
                                    </div>
                                </div>
                            </div>

                            {{-- اليسار: أرقام التواصل، العنوان، التاريخ والوقت، رقم السند والباركود --}}
                            <div class="header-col col-span-4 text-left space-y-1">
                                @if ($managerPhone)
                                    <div class="flex items-center justify-between gap-2 text-[11px]">
                                        <span class="text-slate-500 font-bold shrink-0">المدير والشكاوى:</span>
                                        <span class="editable-field font-mono font-extrabold text-slate-900 inline-flex items-center gap-1 dir-ltr">
                                            <span>{{ $managerPhone }}</span>
                                            <span class="material-symbols-outlined text-[13px] text-rose-800">call</span>
                                        </span>
                                    </div>
                                @endif

                                @if ($dispatchPhone1)
                                    <div class="flex items-center justify-between gap-2 text-[11px]">
                                        <span class="text-slate-500 font-bold shrink-0">خدمة الإرسال:</span>
                                        <span class="editable-field font-mono font-extrabold text-slate-900 inline-flex items-center gap-1 dir-ltr">
                                            <span>{{ $dispatchPhone1 }}</span>
                                            <span class="material-symbols-outlined text-[13px] text-rose-800">call</span>
                                        </span>
                                    </div>
                                @endif

                                @if ($dispatchPhone2)
                                    <div class="text-left">
                                        <span class="editable-field font-mono font-bold text-[10.5px] text-slate-600 dir-ltr inline-block">
                                            {{ $dispatchPhone2 }}
                                        </span>
                                    </div>
                                @endif

                                @if ($officeAddress)
                                    <p class="editable-field text-[11px] font-extrabold text-slate-700 text-right border-t border-slate-100 pt-1">
                                        {{ $officeAddress }}
                                    </p>
                                @endif

                                {{-- التاريخ والوقت --}}
                                <div class="flex items-center justify-between gap-2 pt-0.5 text-[11px] font-mono font-bold text-slate-700">
                                    <span class="inline-flex items-center gap-1 dir-ltr">
                                        <span class="material-symbols-outlined text-[13px] text-rose-700">calendar_month</span>
                                        <span class="editable-field">{{ $dateStr }}</span>
                                    </span>
                                    <span class="inline-flex items-center gap-1 dir-ltr">
                                        <span class="editable-field">{{ $timeStr }}</span>
                                        <span class="material-symbols-outlined text-[13px] text-slate-500">schedule</span>
                                    </span>
                                </div>

                                {{-- كبسولة رقم السند بلون الهوية الأساسي + الباركود الخطي --}}
                                <div class="flex items-center justify-between gap-2 pt-1">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md bg-primary text-white font-mono font-black text-xs shrink-0 dir-ltr">
                                        <span class="material-symbols-outlined text-[13px] text-secondary">tag</span>
                                        <span class="editable-field receipt-number-input">{{ $receiptNumber }}</span>
                                    </span>

                                    <div class="barcode-wrapper overflow-hidden flex items-center justify-end" title="باركود رقم السند">
                                        {!! $barcodeSvg !!}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- 2. جدول تفاصيل الشحنة --}}
                        <div class="relative z-10 mt-1">
                            <table class="invoice-table">
                                <tbody>
                                    <tr>
                                        <td class="w-7/12 text-right">
                                            <span class="text-xs font-bold text-slate-500 ml-1">المحافظة / الوجهة:</span>
                                            <span class="editable-field text-sm font-black text-slate-900">{{ $destination }}</span>
                                        </td>
                                        <td class="w-5/12 text-left">
                                            <span class="text-xs font-bold text-slate-500 ml-1">المبلغ:</span>
                                            <span class="editable-field text-sm font-black {{ $isDeferred ? 'text-amber-800' : 'text-slate-900' }}">
                                                {{ $amountDisplay }}
                                            </span>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td class="text-right">
                                            <span class="text-xs font-bold text-slate-500 ml-1">اسم المستلم:</span>
                                            <span class="editable-field text-sm font-black text-slate-900">{{ $recipientName }}</span>
                                        </td>
                                        <td class="text-left">
                                            <span class="phone-badge">
                                                <span class="editable-field">{{ $recipientPhone }}</span>
                                                <span class="material-symbols-outlined text-[15px] text-rose-800">call</span>
                                            </span>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td class="text-right">
                                            <span class="text-xs font-bold text-slate-500 ml-1">اسم المرسل:</span>
                                            <span class="editable-field text-sm font-black text-slate-900">{{ $senderName }}</span>
                                        </td>
                                        <td class="text-left">
                                            <span class="phone-badge">
                                                <span class="editable-field">{{ $senderPhone }}</span>
                                                <span class="material-symbols-outlined text-[15px] text-rose-800">call</span>
                                            </span>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td colspan="2" class="text-right">
                                            <div class="flex items-center justify-between gap-2 flex-wrap">
                                                <div>
                                                    <span class="text-xs font-bold text-slate-500 ml-1">نوع الرسالة:</span>
                                                    <span class="editable-field text-sm font-black text-slate-900">{{ $packageType }}</span>
                                                </div>
                                                @if ($parcelNotes)
                                                    <div class="text-xs font-bold text-slate-600">
                                                        <span class="text-slate-400">ملاحظة:</span>
                                                        <span class="editable-field">{{ $parcelNotes }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td colspan="2" class="pt-2 pb-2">
                                            <div class="flex items-center justify-between gap-3 flex-wrap">
                                                <div class="text-right min-w-0 flex-1">
                                                    <span class="block text-[11px] font-bold text-slate-500">موقع التسليم:</span>
                                                    <span class="editable-field text-xs sm:text-sm font-black text-slate-900 leading-snug block mt-0.5">
                                                        {{ $deliveryLoc }}
                                                    </span>
                                                </div>

                                                @if ($deliveryPhone)
                                                    <div class="shrink-0">
                                                        <span class="delivery-phone-badge editable-field">{{ $deliveryPhone }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        {{-- 3. الشروط والأحكام + رمز QR السريع (UUID) --}}
                        <div class="relative z-10 mt-2 pt-2 border-t-2 border-primary flex items-center justify-between gap-4">
                            <ul class="space-y-0.5 text-[11px] font-bold text-slate-600 leading-relaxed flex-1">
                                @foreach ($terms as $term)
                                    <li class="editable-field">{{ $term }}</li>
                                @endforeach
                            </ul>

                            @if ($parcelUuid)
                                <div class="qr-box shrink-0 flex flex-col items-center justify-center p-1.5 border border-slate-300 rounded-lg bg-white">
                                    <div class="qr-wrapper" data-uuid="{{ $parcelUuid }}"></div>
                                    <span class="text-[8.5px] font-mono text-slate-500 mt-1 leading-none">مسح سريع</span>
                                </div>
                            @endif
                        </div>

                        {{-- 4. التذييل السفلي --}}
                        <div class="relative z-10 mt-2.5 pt-2 border-t border-slate-200 flex items-center justify-between gap-2 text-xs">
                            <div class="text-right">
                                <span class="text-slate-500 font-bold">الموظف:</span>
                                <span class="editable-field font-black text-slate-900 mr-0.5">{{ $employeeName }}</span>
                            </div>

                            <div class="editable-field text-center font-black text-primary text-xs sm:text-sm">
                                "{{ $slogan }}"
                            </div>

                            <div class="editable-field text-left text-[11px] font-bold text-slate-400">
                                {{ $footerText }}
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach

        </div>
    </main>

    <!-- مكتبة QR Code لتوليد رمز الـ UUID للمسح السريع -->
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script>
        let isLiveEdit = false;
        let isThermal = false;
        const brandPrimaryHex = @json($brandPrimary);

        // توليد رموز QR (UUID) في كل النسخ بلون الهوية الأساسي
        (function renderAllQrCodes() {
            const wrappers = document.querySelectorAll('.qr-wrapper');
            if (!window.QRCode) {
                document.querySelectorAll('.qr-box').forEach(box => box.classList.add('hidden'));
                return;
            }
            wrappers.forEach(el => {
                const uuid = el.getAttribute('data-uuid');
                if (!uuid) return;
                el.innerHTML = '';
                new QRCode(el, {
                    text: uuid,
                    width: 68,
                    height: 68,
                    colorDark: brandPrimaryHex || '#041627',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.M
                });
            });
        })();

        function setCopiesMode(mode) {
            const card2 = document.getElementById('receiptCard2');
            const divider = document.getElementById('cutLineDivider');
            const btns = document.querySelectorAll('.copy-btn');

            btns.forEach(b => {
                b.classList.remove('bg-secondary', 'text-primary', 'font-extrabold');
                b.classList.add('text-slate-200');
            });

            if (mode === 'double') {
                const btn = document.getElementById('btn-double');
                btn.classList.remove('text-slate-200');
                btn.classList.add('bg-secondary', 'text-primary', 'font-extrabold');
                card2.classList.remove('hidden');
                divider.classList.remove('hidden');
                updateBadgeText('receipt-1', 'خاص بالمكتب');
                updateBadgeText('receipt-2', 'نسخة العميل');
            } else if (mode === 'single-client') {
                const btn = document.getElementById('btn-single-client');
                btn.classList.remove('text-slate-200');
                btn.classList.add('bg-secondary', 'text-primary', 'font-extrabold');
                card2.classList.add('hidden');
                divider.classList.add('hidden');
                updateBadgeText('receipt-1', 'نسخة العميل');
            } else {
                const btn = document.getElementById('btn-single-office');
                btn.classList.remove('text-slate-200');
                btn.classList.add('bg-secondary', 'text-primary', 'font-extrabold');
                card2.classList.add('hidden');
                divider.classList.add('hidden');
                updateBadgeText('receipt-1', 'خاص بالمكتب');
            }
        }

        function updateBadgeText(receiptId, text) {
            const el = document.querySelector(`#${receiptId} .copy-badge-label`);
            if (el) el.innerText = text;
        }

        function toggleLiveEdit() {
            isLiveEdit = !isLiveEdit;
            const editables = document.querySelectorAll('.editable-field');
            const btn = document.getElementById('toggleLiveEditBtn');
            const btnText = document.getElementById('liveEditText');

            editables.forEach(el => {
                el.setAttribute('contenteditable', isLiveEdit ? 'true' : 'false');
            });

            if (isLiveEdit) {
                btn.classList.add('bg-secondary/20', 'border-secondary', 'text-secondary');
                btnText.innerText = 'إنهاء التعديل';
            } else {
                btn.classList.remove('bg-secondary/20', 'border-secondary', 'text-secondary');
                btnText.innerText = 'تعديل مباشر';
            }
        }

        function togglePaperSize() {
            isThermal = !isThermal;
            const body = document.body;
            const label = document.getElementById('paperSizeLabel');
            if (isThermal) {
                body.classList.add('thermal-mode');
                label.innerText = 'قياس حراري (80mm)';
            } else {
                body.classList.remove('thermal-mode');
                label.innerText = 'قياس A4 / A5';
            }
        }

        document.addEventListener('input', function (e) {
            if (e.target && e.target.classList.contains('receipt-number-input')) {
                const newCode = e.target.innerText.trim();
                if (newCode) {
                    generateCode128Js(newCode);
                }
            }
        });

        function generateCode128Js(code) {
            const patterns = [
                '212222','222122','222221','121223','121322','131222','122213','122312','132212','221213',
                '221312','231212','112232','122132','122231','113222','123122','123221','223211','221132',
                '221231','213212','223112','312131','311222','321122','321221','312212','322112','322211',
                '212123','212321','232121','111323','131123','131321','112313','132113','132311','211313',
                '231113','231311','112133','112331','132131','113123','113321','133121','313121','211331',
                '231131','213113','213311','213131','311123','311321','331121','312113','312311','332111',
                '314111','221411','431111','111224','111422','121124','121421','141122','141221','112214',
                '112412','122114','122411','142112','142211','241211','221114','413111','241112','134111',
                '111242','121142','121241','114212','124112','124211','411212','421112','421211','212141',
                '214121','412121','111143','111341','131141','114113','114311','411113','411311','113141',
                '114131','311141','411131','211412','211214','211232','2331112'
            ];

            const startB = 104;
            const stop = 106;
            let checksum = startB;
            const encoded = [patterns[startB]];

            for (let i = 0; i < code.length; i++) {
                let val = code.charCodeAt(i) - 32;
                if (val < 0 || val > 95) val = 0;
                encoded.push(patterns[val]);
                checksum += val * (i + 1);
            }

            encoded.push(patterns[checksum % 103]);
            encoded.push(patterns[stop]);

            let modules = '';
            encoded.forEach(p => {
                let isBar = true;
                for (let k = 0; k < p.length; k++) {
                    const w = parseInt(p[k], 10);
                    modules += (isBar ? '1' : '0').repeat(w);
                    isBar = !isBar;
                }
            });

            const scale = 1.25;
            const height = 26;
            const totalWidth = (modules.length * scale).toFixed(1);

            let rects = '';
            let x = 0;
            for (let m = 0; m < modules.length; m++) {
                if (modules[m] === '1') {
                    rects += `<rect x="${x.toFixed(2)}" y="0" width="${scale}" height="${height}" fill="#0f172a" />`;
                }
                x += scale;
            }

            const svgHtml = `<svg xmlns="http://www.w3.org/2000/svg" class="barcode-svg" width="${totalWidth}" height="${height}" viewBox="0 0 ${totalWidth} ${height}" preserveAspectRatio="none">${rects}</svg>`;
            document.querySelectorAll('.barcode-wrapper').forEach(wrapper => {
                wrapper.innerHTML = svgHtml;
            });
        }
    </script>
</body>
</html>