@extends('admin.layouts.app')

@section('title', 'تفاصيل كشف السائق #' . $manifest->id)
@section('page-title', 'تفاصيل كشف السائق #' . $manifest->id . ' — ' . $manifest->driver_name)
@section('page-subtitle', 'مراجعة شاملة لبيانات الكشف، المعادلة المالية، بنود الخصومات، والطرود المدرجة فيه')

@section('header-actions')
    <div class="flex items-center gap-2 print:hidden">
        <button type="button"
                onclick="window.print()"
                class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-secondary text-primary-950 text-xs font-extrabold hover:brightness-105 transition-all shadow-sm">
            <span class="material-symbols-outlined text-lg">print</span>
            <span>طباعة الكشف</span>
        </button>

        <a href="{{ route('admin.manifests.index') }}"
           class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 text-xs font-extrabold transition-all">
            <span class="material-symbols-outlined text-lg">arrow_forward</span>
            <span>العودة للكشوفات</span>
        </a>
    </div>
@endsection

@section('content')
<div class="space-y-6">

    <!-- 1. بطاقة معلومات الكشف الأساسية والمعادلة المالية الثلاثية -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs p-6 space-y-6">

        <!-- صف بيانات الكشف والسائق والمكتب والمحاسب -->
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-5 border-b border-slate-100">
            <div class="flex items-start gap-3.5">
                <div class="w-12 h-12 rounded-2xl bg-primary text-secondary flex items-center justify-center shrink-0 shadow-sm">
                    <span class="material-symbols-outlined text-3xl">local_shipping</span>
                </div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="text-lg font-black text-slate-900">السائق: {{ $manifest->driver_name }}</h2>
                        <span class="px-2.5 py-0.5 rounded-lg bg-slate-100 text-slate-700 border border-slate-200 font-mono text-xs font-bold">
                            كشف #{{ $manifest->id }}
                        </span>
                        @if ($manifest->manifest_number)
                            <span class="px-2.5 py-0.5 rounded-lg bg-amber-50 text-amber-800 border border-amber-200 font-mono text-xs font-bold">
                                رقم ورقي: {{ $manifest->manifest_number }}
                            </span>
                        @endif
                    </div>

                    <div class="flex items-center gap-4 flex-wrap text-xs font-bold text-slate-500 mt-2">
                        @if ($manifest->driver_phone)
                            <span class="inline-flex items-center gap-1 font-mono text-slate-700" dir="ltr">
                                <span class="material-symbols-outlined text-sm text-emerald-600">call</span>
                                {{ $manifest->driver_phone }}
                            </span>
                        @endif

                        <span class="inline-flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm text-primary">domain</span>
                            <span>المكتب المستلم:</span>
                            <strong class="text-slate-800">{{ $manifest->office?->name ?? 'غير محدد' }}</strong>
                        </span>

                        <span class="inline-flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm text-secondary">store</span>
                            <span>قادم من:</span>
                            <strong class="text-slate-800">
                                {{ $manifest->source_office_name ?: 'غير محدد' }}
                                {{ $manifest->source_branch_name ? "({$manifest->source_branch_name})" : '' }}
                            </strong>
                        </span>

                        <span class="inline-flex items-center gap-1 font-mono" dir="ltr">
                            <span class="material-symbols-outlined text-sm text-slate-400">calendar_today</span>
                            {{ $manifest->manifest_date?->format('Y-m-d') }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- حالة الكشف والمحاسب المسؤول -->
            <div class="flex flex-col sm:flex-row lg:flex-col items-start sm:items-center lg:items-end gap-2">
                @if ($manifest->status === 'settled')
                    <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-extrabold bg-emerald-50 text-emerald-800 border border-emerald-300">
                        <span class="material-symbols-outlined text-base text-emerald-600">verified</span>
                        <span>تمت تصفية الكشف ({{ $manifest->settled_at?->format('Y-m-d h:i A') }})</span>
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-extrabold bg-amber-50 text-amber-800 border border-amber-300">
                        <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                        <span>قيد المراجعة (مفتوح)</span>
                    </span>
                @endif

                <div class="text-xs font-bold text-slate-500">
                    <span>المحاسب المسؤول: </span>
                    @if ($manifest->accountant)
                        <a href="{{ route('admin.parcel_edit_logs.index', ['accountant_id' => $manifest->accountant_id]) }}"
                           class="text-primary hover:text-secondary underline font-extrabold">
                            {{ $manifest->accountant->name }}
                        </a>
                    @else
                        <span class="text-slate-400">غير محدد</span>
                    @endif
                </div>
            </div>
        </div>

        <!-- المعادلة المالية الثلاثية للكشف (الآجل - الخصومات = الصافي للسائق) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- 1. إجمالي الآجل -->
            <div class="bg-amber-50/70 border border-amber-200 rounded-2xl p-4 flex items-center justify-between">
                <div>
                    <p class="text-xs font-extrabold text-amber-800">1. إجمالي طرود الآجل</p>
                    <p class="text-2xl font-black text-amber-950 font-mono mt-1" dir="ltr">
                        {{ number_format($manifest->total_postpaid_amount) }} <span class="text-xs">ر.ي</span>
                    </p>
                    <p class="text-[11px] font-bold text-amber-700 mt-1">
                        من إجمالي ({{ $postpaidParcelsCount }}) طرد آجل و ({{ $prepaidParcelsCount }}) طرد محاسب
                    </p>
                </div>
                <div class="w-11 h-11 rounded-2xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-2xl">payments</span>
                </div>
            </div>

            <!-- 2. ناقص إجمالي الخصومات -->
            <div class="bg-rose-50/70 border border-rose-200 rounded-2xl p-4 flex items-center justify-between">
                <div>
                    <p class="text-xs font-extrabold text-rose-800">2. ناقص إجمالي الخصومات (-)</p>
                    <p class="text-2xl font-black text-rose-900 font-mono mt-1" dir="ltr">
                        {{ number_format($manifest->total_deductions) }} <span class="text-xs">ر.ي</span>
                    </p>
                    <p class="text-[11px] font-bold text-rose-700 mt-1">
                        مكتب: {{ number_format($deductionBreakdown['office_commission']) }} | عمال: {{ number_format($deductionBreakdown['workers_unloading']) }} | أخرى: {{ number_format($deductionBreakdown['other']) }}
                    </p>
                </div>
                <div class="w-11 h-11 rounded-2xl bg-rose-100 text-rose-700 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-2xl">remove_circle</span>
                </div>
            </div>

            <!-- 3. الصافي المسلم للسائق -->
            <div class="bg-emerald-600 text-white border border-emerald-700 rounded-2xl p-4 flex items-center justify-between shadow-sm">
                <div>
                    <p class="text-xs font-extrabold text-emerald-100">3. الصافي الواجب تسليمه للسائق (=)</p>
                    <p class="text-2xl font-black text-white font-mono mt-1" dir="ltr">
                        {{ number_format($manifest->net_driver_amount) }} <span class="text-xs text-emerald-100">ر.ي</span>
                    </p>
                    <p class="text-[11px] font-bold text-emerald-100 mt-1">
                        {{ $manifest->status === 'settled' ? 'تم اعتماد التصفية وتسليم السائق' : 'بانتظار اعتماد التصفية' }}
                    </p>
                </div>
                <div class="w-11 h-11 rounded-2xl bg-white/15 text-white flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-2xl">account_balance_wallet</span>
                </div>
            </div>
        </div>

        @if ($manifest->notes)
            <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 text-xs font-bold text-slate-700 flex items-center gap-2">
                <span class="material-symbols-outlined text-base text-secondary">sticky_note_2</span>
                <span>ملاحظات الكشف / التصفية: {{ $manifest->notes }}</span>
            </div>
        @endif
    </div>

    <!-- 2. جدول بنود الخصومات المسجلة على الكشف (manifest_deductions) -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-rose-600 text-2xl">receipt</span>
                <div>
                    <h3 class="text-sm font-black text-slate-900">بنود الخصومات المسجلة على الكشف ({{ $manifest->deductions->count() }})</h3>
                    <p class="text-xs text-slate-500">تفصيل خصومات العمال وعمولة المكتب وأي خصومات أخرى</p>
                </div>
            </div>

            <span class="px-3 py-1 rounded-full text-xs font-black bg-rose-50 text-rose-800 border border-rose-200 font-mono" dir="ltr">
                الإجمالي: {{ number_format($manifest->total_deductions) }} ر.ي
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead>
                    <tr class="bg-slate-100/70 text-slate-600 font-extrabold uppercase border-b border-slate-200 text-[11px]">
                        <th class="py-3.5 px-5 text-center w-12">#</th>
                        <th class="py-3.5 px-4">نوع الخصم</th>
                        <th class="py-3.5 px-4">البيان</th>
                        <th class="py-3.5 px-4">ملاحظات</th>
                        <th class="py-3.5 px-4">المبلغ المخصوم</th>
                        <th class="py-3.5 px-4">تاريخ الإضافة</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                    @forelse ($manifest->deductions as $deduction)
                        <tr class="hover:bg-slate-50/80">
                            <td class="py-3.5 px-5 text-center font-mono text-slate-400">{{ $loop->iteration }}</td>
                            <td class="py-3.5 px-4">
                                @if ($deduction->deduction_type === 'workers_unloading')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-amber-50 text-amber-800 border border-amber-200 text-[11px] font-extrabold">
                                        <span>👷 خصم العمال (تنزيل)</span>
                                    </span>
                                @elseif ($deduction->deduction_type === 'office_commission')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-blue-50 text-blue-800 border border-blue-200 text-[11px] font-extrabold">
                                        <span>🏢 عمولة المكتب</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 border border-slate-200 text-[11px] font-extrabold">
                                        <span>➕ خصم آخر</span>
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 font-extrabold text-slate-900">{{ $deduction->title }}</td>
                            <td class="py-3.5 px-4 text-slate-500">{{ $deduction->notes ?: '—' }}</td>
                            <td class="py-3.5 px-4">
                                <span class="px-2.5 py-1 rounded-lg bg-rose-50 text-rose-800 border border-rose-200 font-mono font-black" dir="ltr">
                                    - {{ number_format($deduction->amount) }} ر.ي
                                </span>
                            </td>
                            <td class="py-3.5 px-4 font-mono text-[11px] text-slate-500" dir="ltr">
                                {{ $deduction->created_at?->format('Y-m-d h:i A') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400 font-bold">
                                لا توجد خصومات مسجلة على هذا الكشف
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- 3. جدول جميع الطرود المربوطة بالكشف -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 bg-slate-50/50">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-primary text-2xl">inventory_2</span>
                <div>
                    <h3 class="text-sm font-black text-slate-900">الطرود المدرجة في الكشف ({{ $totalParcelsCount }})</h3>
                    <p class="text-xs text-slate-500">آجل: {{ $postpaidParcelsCount }} طرد | محاسب: {{ $prepaidParcelsCount }} طرد</p>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead>
                    <tr class="bg-slate-100/70 text-slate-600 font-extrabold uppercase border-b border-slate-200 text-[11px]">
                        <th class="py-3.5 px-5 text-center w-12">#</th>
                        <th class="py-3.5 px-4">اسم المستلم</th>
                        <th class="py-3.5 px-4">رقم الجوال</th>
                        <th class="py-3.5 px-4">نوع الطرد والسند</th>
                        <th class="py-3.5 px-4 text-center">نوع الحساب</th>
                        <th class="py-3.5 px-4">مبلغ الآجل</th>
                        <th class="py-3.5 px-4 text-center">تعديلات المحاسب</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                    @forelse ($parcels as $parcel)
                        <tr class="hover:bg-slate-50/80">
                            <td class="py-3.5 px-5 text-center font-mono text-slate-400">{{ $loop->iteration }}</td>
                            <td class="py-3.5 px-4 font-extrabold text-slate-900">
                                {{ $parcel->recipient_name ?: 'بدون اسم' }}
                            </td>
                            <td class="py-3.5 px-4 font-mono font-bold text-slate-700" dir="ltr">
                                {{ $parcel->recipient_phone }}
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="font-extrabold text-slate-800">{{ $parcel->package_type ?: 'طرد' }}</span>
                                @if ($parcel->receipt_number)
                                    <span class="mr-1 px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200 font-mono text-[10px]">
                                        سند #{{ $parcel->receipt_number }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                @if ($parcel->payment_type === 'postpaid')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-amber-50 text-amber-800 border border-amber-300 text-[11px] font-extrabold">
                                        <span>💰 آجل</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[11px] font-extrabold">
                                        <span>✅ محاسب</span>
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 font-mono font-black" dir="ltr">
                                @if ($parcel->payment_type === 'postpaid')
                                    <span class="text-amber-900">{{ number_format((float) $parcel->cod_amount) }} ر.ي</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                @if ($parcel->edit_logs_count > 0)
                                    <a href="{{ route('admin.parcel_edit_logs.index', ['parcel_id' => $parcel->id]) }}"
                                       class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-sky-50 hover:bg-sky-100 text-sky-700 border border-sky-200 text-[11px] font-extrabold transition-colors">
                                        <span class="material-symbols-outlined text-sm">history</span>
                                        <span>عرض السجل ({{ $parcel->edit_logs_count }})</span>
                                    </a>
                                @else
                                    <span class="text-[11px] text-slate-400">بدون تعديل</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center text-slate-400 font-bold">
                                لا توجد طرود مرتبطة بهذا الكشف حالياً
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

