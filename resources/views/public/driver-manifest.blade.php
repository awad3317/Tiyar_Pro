<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>كشف سائق #{{ $manifest->manifest_number ?: $manifest->id }} - {{ $manifest->office?->name ?? 'مُرسَل' }}</title>
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
        body { font-family: 'Almarai', sans-serif; }
        .dir-ltr { direction: ltr; }
        @media print {
            @page { size: A4 portrait; margin: 10mm; }
            body { background: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-900 min-h-screen py-4 px-3 print:p-0">
    <div class="max-w-2xl mx-auto space-y-3 print:max-w-none print:w-full">

        <!-- ترويسة الكشف -->
        <header class="bg-primary text-white rounded-2xl p-4 shadow-md print:bg-white print:text-slate-900 print:border-b-2 print:border-slate-800 print:rounded-none print:shadow-none">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-[11px] font-bold text-slate-300 print:text-slate-600">
                        {{ $manifest->office?->name ?? 'المكتب' }}
                        @if($manifest->office?->branch_name)
                            - {{ $manifest->office->branch_name }}
                        @endif
                    </p>
                    <h1 class="text-base sm:text-lg font-black mt-0.5">
                        كشف حساب سائق {{ $manifest->manifest_number ? '#' . $manifest->manifest_number : '#' . $manifest->id }}
                    </h1>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <span class="px-2.5 py-1 rounded-xl text-[11px] font-extrabold {{ $manifest->status === 'settled' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-400/30 print:bg-emerald-50 print:text-emerald-800 print:border-emerald-300' : 'bg-amber-500/20 text-amber-300 border border-amber-400/30 print:bg-amber-50 print:text-amber-800 print:border-amber-300' }}">
                        {{ $manifest->status === 'settled' ? 'تمت التصفية' : 'قيد المراجعة' }}
                    </span>

                    <button type="button"
                            onclick="window.print()"
                            class="w-8 h-8 rounded-xl bg-white/10 hover:bg-white/20 text-white flex items-center justify-center print:hidden"
                            title="طباعة الكشف">
                        <span class="material-symbols-outlined text-lg">print</span>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mt-3 pt-3 border-t border-white/15 print:border-slate-300 text-xs">
                <div>
                    <span class="text-slate-300 print:text-slate-500 text-[10px] block">السائق</span>
                    <strong class="font-extrabold">{{ $manifest->driver_name }}</strong>
                </div>
                <div>
                    <span class="text-slate-300 print:text-slate-500 text-[10px] block">الجهة المرسلة</span>
                    <strong class="font-bold">{{ $manifest->source_office_name ?: '—' }}@if($manifest->source_branch_name) ({{ $manifest->source_branch_name }})@endif</strong>
                </div>
                <div>
                    <span class="text-slate-300 print:text-slate-500 text-[10px] block">تاريخ الكشف</span>
                    <strong class="font-mono dir-ltr inline-block">{{ $manifest->manifest_date?->format('Y-m-d') ?? '—' }}</strong>
                </div>
            </div>
        </header>

        <!-- الملخص المالي -->
        <section class="grid grid-cols-3 gap-2">
            <div class="bg-white rounded-2xl p-3 border border-amber-200 text-center shadow-xs">
                <p class="text-[10px] font-bold text-amber-800">إجمالي الآجل</p>
                <p class="text-sm font-black font-mono text-amber-950 mt-0.5 dir-ltr">{{ number_format((float) $manifest->total_postpaid_amount, 0) }} ر.ي</p>
            </div>
            <div class="bg-white rounded-2xl p-3 border border-rose-200 text-center shadow-xs">
                <p class="text-[10px] font-bold text-rose-800">الخصومات</p>
                <p class="text-sm font-black font-mono text-rose-900 mt-0.5 dir-ltr">- {{ number_format((float) $manifest->total_deductions, 0) }} ر.ي</p>
            </div>
            <div class="bg-emerald-600 text-white rounded-2xl p-3 text-center shadow-sm">
                <p class="text-[10px] font-bold text-emerald-100">الصافي للسائق</p>
                <p class="text-sm font-black font-mono mt-0.5 dir-ltr">{{ number_format((float) $manifest->net_driver_amount, 0) }} ر.ي</p>
            </div>
        </section>

        <!-- جدول الطرود -->
        <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden print:rounded-none">
            <div class="px-3.5 py-2.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between text-xs font-extrabold text-slate-800">
                <span>الطرود المدرجة ({{ $manifest->parcels->count() }})</span>
                <span class="text-[11px] font-bold text-slate-500">آجل: {{ $postpaidCount }} • محاسب: {{ $prepaidCount }}</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-[11px]">
                    <thead>
                        <tr class="bg-slate-100 text-slate-700 font-extrabold border-b border-slate-200">
                            <th class="px-2 py-2 text-center w-8">#</th>
                            <th class="px-2 py-2 text-right">السند</th>
                            <th class="px-2 py-2 text-right">المستلم</th>
                            <th class="px-2 py-2 text-right">الجوال</th>
                            <th class="px-2 py-2 text-right">النوع</th>
                            <th class="px-2 py-2 text-center">الدفع</th>
                            <th class="px-2 py-2 text-left">المبلغ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($manifest->parcels as $index => $parcel)
                            @php
                                $isPostpaid = $parcel->payment_type === 'postpaid';
                            @endphp
                            <tr class="{{ $isPostpaid ? 'bg-amber-50/30' : '' }}">
                                <td class="px-2 py-2 text-center font-mono text-slate-500">{{ $index + 1 }}</td>
                                <td class="px-2 py-2 font-mono text-slate-600">{{ $parcel->receipt_number ?: '—' }}</td>
                                <td class="px-2 py-2 font-bold text-slate-800">{{ $parcel->recipient_name ?: 'بدون اسم' }}</td>
                                <td class="px-2 py-2 font-mono dir-ltr text-right text-slate-700">{{ $parcel->recipient_phone ?: '—' }}</td>
                                <td class="px-2 py-2 text-slate-700">{{ $parcel->package_type ?: 'طرد' }}</td>
                                <td class="px-2 py-2 text-center">
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-extrabold {{ $isPostpaid ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600' }}">
                                        {{ $isPostpaid ? 'آجل' : 'محاسب' }}
                                    </span>
                                </td>
                                <td class="px-2 py-2 font-mono font-extrabold text-left dir-ltr {{ $isPostpaid ? 'text-amber-900' : 'text-slate-400' }}">
                                    {{ $isPostpaid ? number_format((float) $parcel->cod_amount, 0) . ' ر.ي' : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-6 text-center text-slate-400 font-bold">لا توجد طرود في هذا الكشف</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <!-- جدول الخصومات -->
        @if($manifest->deductions->isNotEmpty())
            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden print:rounded-none">
                <div class="px-3.5 py-2.5 bg-rose-50/70 border-b border-rose-200 flex items-center justify-between text-xs font-extrabold text-rose-900">
                    <span>بنود الخصومات ({{ $manifest->deductions->count() }})</span>
                    <span class="font-mono dir-ltr">- {{ number_format((float) $manifest->total_deductions, 0) }} ر.ي</span>
                </div>

                <div class="divide-y divide-slate-100 text-xs">
                    @foreach($manifest->deductions as $deduction)
                        <div class="px-3.5 py-2.5 flex items-center justify-between gap-2">
                            <span class="font-bold text-slate-800">{{ $deduction->title }}</span>
                            <span class="font-mono font-black text-rose-700 dir-ltr">- {{ number_format((float) $deduction->amount, 0) }} ر.ي</span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if($manifest->notes)
            <section class="bg-white rounded-2xl p-3 border border-slate-200 text-xs text-slate-600 font-bold">
                ملاحظات: {{ $manifest->notes }}
            </section>
        @endif

        <footer class="text-center text-[10px] text-slate-400 font-bold py-2">
            بوابة المحاسب • {{ $manifest->office?->name ?? 'مُرسَل' }}
        </footer>
    </div>
</body>
</html>
