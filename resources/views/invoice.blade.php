@php
    // تحديد بيانات المكتب والطلب بمرونة لتناسب أي مكتب وحسب طلبه
    $office = $office ?? $parcel?->office ?? auth('office')->user() ?? rescue(fn () => \App\Models\Office::first(), null, false);
    
    // بيانات المكتب
    $officeName     = $office_name ?? request('office_name', $office?->name ?? 'مكتب السلمي');
    $tagline1       = $tagline1 ?? request('tagline1', 'لإرسال واستقبال الطرود والرسائل');
    $tagline2       = $tagline2 ?? request('tagline2', 'من وإلى مختلف المحافظات');
    $managerPhone   = $manager_phone ?? request('manager_phone', $office?->manager_phone ?? '777561061');
    $dispatchPhone1 = $dispatch_phone1 ?? request('dispatch_phone1', $office?->whatsapp_sender_phone ?? '771961061 - 777950051');
    $dispatchPhone2 = $dispatch_phone2 ?? request('dispatch_phone2', '777880006 - 777880005');
    $officeAddress  = $address ?? request('address', $office?->branch_name ? ($office->name . ' - ' . $office->branch_name) : 'صنعاء - باب اليمن أمام فرزة تعز جوار الكريمي');
    
    // بيانات السند والطرد
    $receiptNumber  = $receipt_number ?? request('receipt_number', $parcel?->receipt_number ?? '9-5524');
    $createdTime    = $parcel?->created_at ?? now();
    $dateStr        = $date ?? request('date', $createdTime->format('Y/m/d'));
    $timeStr        = $time ?? request('time', $createdTime->format('H:i'));
    $destination    = $destination ?? request('destination', $parcel?->destination ?? 'المكلاء');
    $amount         = $amount ?? request('amount', $parcel?->amount ?? 'محاسب نقداً');
    $recipientName  = $recipient_name ?? request('recipient_name', $parcel?->recipient_name ?? 'امينه عمر علي بن الشيخ ابوبكر');
    $recipientPhone = $recipient_phone ?? request('recipient_phone', $parcel?->recipient_phone ?? '777933505');
    $senderName     = $sender_name ?? request('sender_name', $parcel?->source_office_name ?? 'اطياب الماجد');
    $senderPhone    = $sender_phone ?? request('sender_phone', $parcel?->sender_phone ?? '783600013');
    $packageType    = $package_type ?? request('package_type', $parcel?->package_type ?? 'طرد عطر');
    $deliveryLoc    = $delivery_location ?? request('delivery_location', $parcel?->delivery_location ?? 'مكتب الجعيدي المكلاء - المكلاء الديس مقابل الاوس ميقا ماركت بجانب دكتور البطاريات');
    $deliveryPhone  = $delivery_phone ?? request('delivery_phone', '776310898');
    
    // نوع السند والنسخة
    $receiptTitle   = $receipt_title ?? request('title', 'سند إرسال');
    $copyType       = $copy_type ?? request('copy', 'خاص بالمكتب');
    $employeeName   = $employee_name ?? request('employee', auth('office')->user()?->name ?? auth('web')->user()?->name ?? 'خليل السلمي');
    $slogan         = $slogan ?? request('slogan', 'رسالتك في أيدٍ أمينة');

    // الشروط والأحكام
    $terms = [
        '1- المكتب غير مسؤول عن الرسالة ما لم يتم المراجعة خلال خمسة أيام',
        '2- المكتب غير مسؤول عن الرسائل القابلة للكسر والتلف',
        '3- المكتب غير مسؤول عن أي تأخير بسبب حجز جمركي أو نقاط أمنية',
        '4- عزيزنا العميل أجور الإرسال لا يشمل حق الأمانات',
    ];

    // مولد باركود خطي 1D قياسي (Code 128) كمتجه SVG نقي وبدقة متناهية وبدون أي مكتبات خارجية
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
    <title>{{ $receiptTitle }} - {{ $receiptNumber }} - {{ $officeName }}</title>
    
    <!-- خطوط عربية وتنسيق Tailwind المحلي -->
    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@400;600;700;800;900&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">
    <script src="{{ asset('js/tailwind.js') }}"></script>

    <style>
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

        /* حاوية الفاتورة الأساسية */
        .receipt-card {
            background: #ffffff;
            border: 2px solid #334155;
            border-radius: 12px;
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
            padding: 6px 4px;
            vertical-align: middle;
        }

        /* كبسولات أرقام الهواتف */
        .phone-badge {
            border: 1.5px solid #475569;
            border-radius: 8px;
            padding: 2px 10px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: monospace, sans-serif;
            font-weight: 700;
            font-size: 13.5px;
            background: #f8fafc;
            color: #0f172a;
            direction: ltr;
        }

        /* كبسولة نوع السند */
        .voucher-badge {
            border: 2px solid #1e293b;
            border-radius: 10px;
            padding: 2px 20px;
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
            width: 320px;
            height: 320px;
        }

        /* وضع التعديل المباشر */
        [contenteditable="true"] {
            outline: 2px dashed #3b82f6 !important;
            outline-offset: 2px;
            background-color: rgba(239, 246, 255, 0.7);
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
            margin: 20px 0;
            position: relative;
        }

        .cut-line::before, .cut-line::after {
            content: "";
            flex: 1;
            border-bottom: 2px dashed #94a3b8;
        }

        /* تحسينات وضع الطباعة الفعلي */
        @media print {
            @page {
                size: A4 portrait;
                margin: 6mm 8mm;
            }

            body {
                background: transparent !important;
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
                border: 2px solid #000000 !important;
                border-radius: 8px !important;
                padding: 12px 16px !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                margin-bottom: 12px !important;
            }

            .watermark-bg {
                opacity: 0.08 !important;
            }

            /* وضع الورق الحراري Thermal (80mm / 100mm) */
            body.thermal-mode {
                width: 100% !important;
            }
            body.thermal-mode .receipt-card {
                max-width: 100% !important;
                border: 1.5px solid #000000 !important;
                padding: 8px 10px !important;
            }
        }
    </style>
</head>
<body class="min-h-screen">

    <!-- شريط التحكم العلوي السريع (يختفي تلقائياً عند الطباعة) -->
    <header class="no-print sticky top-0 z-50 bg-slate-900/95 backdrop-blur text-white border-b border-slate-800 shadow-md">
        <div class="max-w-5xl mx-auto px-4 py-3 flex flex-wrap items-center justify-between gap-3">
            
            <!-- أزرار الإجراءات الرئيسية -->
            <div class="flex items-center gap-2">
                <button type="button" onclick="window.print()" class="bg-blue-600 hover:bg-blue-500 text-white font-black text-sm px-4 py-2 rounded-xl flex items-center gap-1.5 shadow-md active:scale-95 transition-all">
                    <span class="material-symbols-outlined text-lg">print</span>
                    <span>طباعة السند (Ctrl + P)</span>
                </button>

                <button type="button" id="toggleLiveEditBtn" onclick="toggleLiveEdit()" class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs px-3 py-2 rounded-xl flex items-center gap-1 border border-slate-700 transition-colors">
                    <span class="material-symbols-outlined text-base text-amber-400">edit_note</span>
                    <span id="liveEditText">تعديل مباشر على الفاتورة</span>
                </button>
            </div>

            <!-- خيارات النسخ والتخطيط السريعة -->
            <div class="flex items-center gap-2 text-xs font-bold">
                <span class="text-slate-400">النسخ:</span>
                <div class="bg-slate-800 p-1 rounded-xl flex items-center gap-1 border border-slate-700">
                    <button type="button" onclick="setCopiesMode('single-office')" id="btn-single-office" class="copy-btn px-2.5 py-1 rounded-lg bg-blue-600 text-white">خاص بالمكتب</button>
                    <button type="button" onclick="setCopiesMode('single-client')" id="btn-single-client" class="copy-btn px-2.5 py-1 rounded-lg text-slate-300 hover:text-white">نسخة العميل</button>
                    <button type="button" onclick="setCopiesMode('double')" id="btn-double" class="copy-btn px-2.5 py-1 rounded-lg text-slate-300 hover:text-white">نسختين معاً (قص)</button>
                </div>

                <!-- خيار الطابعة الحرارية أو العادية -->
                <button type="button" onclick="togglePaperSize()" id="paperSizeBtn" class="bg-slate-800 hover:bg-slate-700 px-3 py-1.5 rounded-xl border border-slate-700 text-slate-300 flex items-center gap-1 transition-colors">
                    <span class="material-symbols-outlined text-sm">receipt_long</span>
                    <span id="paperSizeLabel">قياس A4 / A5</span>
                </button>
            </div>

            <!-- زر العودة -->
            <div class="flex items-center gap-2">
                @if(auth('office')->check())
                    <a href="{{ route('office.parcels.index') }}" class="text-slate-400 hover:text-white text-xs font-bold flex items-center gap-1">
                        <span class="material-symbols-outlined text-base">arrow_forward</span>
                        <span>لوحة الطرود</span>
                    </a>
                @elseif(auth('web')->check())
                    <a href="{{ route('admin.parcels.index') }}" class="text-slate-400 hover:text-white text-xs font-bold flex items-center gap-1">
                        <span class="material-symbols-outlined text-base">arrow_forward</span>
                        <span>مراقبة الطرود</span>
                    </a>
                @endif
            </div>

        </div>
    </header>

    <!-- مساحة عرض الفاتورة -->
    <main class="print-container max-w-4xl mx-auto py-8 px-4 sm:px-6">

        <!-- الحاوية القابلة للطباعة (تحتوي على نسخة أو نسختين حسب الاختيار) -->
        <div id="printArea" class="space-y-6">

            <!-- النسخة الأولى (الافتراضية: خاص بالمكتب أو نسخة العميل) -->
            <div class="receipt-card" id="receiptCard1">
                @include('partials.invoice-body', [
                    'currentCopy' => $copyType,
                    'receiptId'   => 'receipt-1'
                ])
            </div>

            <!-- خط فاصل للقص (يظهر فقط في وضع النسختين) -->
            <div id="cutLineDivider" class="cut-line hidden">
                <span class="material-symbols-outlined text-sm transform -rotate-90">content_cut</span>
                <span>قص هنا (فصل نسخة العميل عن نسخة المكتب)</span>
                <span class="material-symbols-outlined text-sm transform rotate-90">content_cut</span>
            </div>

            <!-- النسخة الثانية (تظهر فقط عند اختيار نسختين معاً) -->
            <div class="receipt-card hidden" id="receiptCard2">
                @include('partials.invoice-body', [
                    'currentCopy' => ($copyType === 'خاص بالمكتب' ? 'نسخة العميل' : 'خاص بالمكتب'),
                    'receiptId'   => 'receipt-2'
                ])
            </div>

        </div>

    </main>

    <!-- سكريبت التحكم التفاعلي بالطباعة والنسخ والباركود -->
    <script>
        let isLiveEdit = false;
        let isThermal = false;

        // تبديل وضع النسخ (نسخة واحدة أو نسختين معاً)
        function setCopiesMode(mode) {
            const card2 = document.getElementById('receiptCard2');
            const divider = document.getElementById('cutLineDivider');
            const btns = document.querySelectorAll('.copy-btn');
            
            btns.forEach(b => {
                b.classList.remove('bg-blue-600', 'text-white');
                b.classList.add('text-slate-300');
            });

            if (mode === 'double') {
                document.getElementById('btn-double').classList.add('bg-blue-600', 'text-white');
                card2.classList.remove('hidden');
                divider.classList.remove('hidden');
                updateBadgeText('receipt-1', 'خاص بالمكتب');
                updateBadgeText('receipt-2', 'نسخة العميل');
            } else if (mode === 'single-client') {
                document.getElementById('btn-single-client').classList.add('bg-blue-600', 'text-white');
                card2.classList.add('hidden');
                divider.classList.add('hidden');
                updateBadgeText('receipt-1', 'نسخة العميل');
            } else {
                document.getElementById('btn-single-office').classList.add('bg-blue-600', 'text-white');
                card2.classList.add('hidden');
                divider.classList.add('hidden');
                updateBadgeText('receipt-1', 'خاص بالمكتب');
            }
        }

        function updateBadgeText(receiptId, text) {
            const el = document.querySelector(`#${receiptId} .copy-badge-label`);
            if (el) el.innerText = text;
        }

        // تفعيل / تعطيل وضع التعديل المباشر على الحقول
        function toggleLiveEdit() {
            isLiveEdit = !isLiveEdit;
            const editables = document.querySelectorAll('.editable-field');
            const btn = document.getElementById('toggleLiveEditBtn');
            const btnText = document.getElementById('liveEditText');

            editables.forEach(el => {
                el.setAttribute('contenteditable', isLiveEdit ? 'true' : 'false');
            });

            if (isLiveEdit) {
                btn.classList.add('bg-amber-500/20', 'border-amber-400');
                btnText.innerText = 'إنهاء التعديل المباشر';
            } else {
                btn.classList.remove('bg-amber-500/20', 'border-amber-400');
                btnText.innerText = 'تعديل مباشر على الفاتورة';
            }
        }

        // التبديل بين مقاس A4 وورق الطابعة الحرارية Thermal Roll
        function togglePaperSize() {
            isThermal = !isThermal;
            const body = document.body;
            const label = document.getElementById('paperSizeLabel');
            if (isThermal) {
                body.classList.add('thermal-mode');
                label.innerText = 'قياس حراري (Thermal 80mm)';
            } else {
                body.classList.remove('thermal-mode');
                label.innerText = 'قياس A4 / A5';
            }
        }

        // تحديث الباركود تلقائياً إذا قام المستخدم بتعديل رقم السند مباشرة في الواجهة
        document.addEventListener('input', function(e) {
            if (e.target && e.target.classList.contains('receipt-number-input')) {
                const newCode = e.target.innerText.trim();
                if (newCode) {
                    generateCode128Js(newCode);
                }
            }
        });

        // مولد باركود Code 128 بالـ JavaScript للتحديث الحي الفوري
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
