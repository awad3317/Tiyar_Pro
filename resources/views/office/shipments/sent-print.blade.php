<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>كشف شحنة صادرة #{{ $shipment->shipment_number }} - {{ $office->name ?? 'مُرسَل' }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@400;700;800&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=swap" rel="stylesheet">
    <script src="{{ asset('js/tailwind.js') }}"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#041627',
                        secondary: '#fe9d20',
                    },
                },
            },
        };
    </script>
    <style>
        body {
            font-family: 'Almarai', sans-serif;
        }
        .dir-ltr {
            direction: ltr;
        }
        @page {
            size: A4 portrait;
            margin: 10mm;
        }
        @media print {
            html, body {
                height: auto !important;
                min-height: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                background: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print {
                display: none !important;
            }
            .print-sheet {
                max-width: none !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                border-radius: 0 !important;
                box-shadow: none !important;
            }
            table.print-table thead {
                display: table-header-group;
            }
            table.print-table tfoot {
                display: table-footer-group;
            }
            table.print-table tr,
            .print-avoid-break {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-900 py-4 px-3 print:p-0 print:bg-white">

    <!-- شريط إجراءات الطباعة (يُخفى تماماً عند الطباعة) -->
    <div class="no-print max-w-[210mm] mx-auto mb-3 flex items-center justify-between gap-2 bg-white border border-slate-200 rounded-2xl px-4 py-2.5 shadow-xs">
        <a href="{{ route('office.shipments.sent.show', $shipment) }}"
           class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-extrabold transition-colors">
            <span class="material-symbols-outlined text-base">arrow_forward</span>
            <span>عودة لتفاصيل الشحنة</span>
        </a>

        <div class="flex items-center gap-2">
            <span class="hidden sm:inline text-[11px] font-bold text-slate-500">
                معاينة الطباعة الرسمية (A4)
            </span>
            <button type="button"
                    onclick="window.print()"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-primary hover:bg-slate-800 text-secondary text-xs font-extrabold shadow-sm transition-colors">
                <span class="material-symbols-outlined text-base">print</span>
                <span>طباعة الكشف</span>
            </button>
        </div>
    </div>

    <!-- ورقة الكشف الرسمية A4 -->
    <div class="print-sheet max-w-[210mm] mx-auto bg-white border border-slate-200 rounded-2xl p-6 shadow-md">
        @include('office.shipments.partials.sent-manifest-document', [
            'office'   => $office,
            'shipment' => $shipment,
        ])
    </div>

</body>
</html>
