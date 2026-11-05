@extends('admin.layouts.app')

@section('title', 'رقابة كشوفات السائقين والتصفيات')
@section('page-title', 'رقابة كشوفات السائقين والتصفيات المالية')
@section('page-subtitle', 'متابعة شاملة لجميع كشوفات السائقين، مبالغ الآجل، خصومات العمال والمكاتب، والصافي المسلم للسائقين')

@section('header-actions')
    <a href="{{ route('admin.parcel_edit_logs.index') }}"
       class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-200 text-xs font-extrabold transition-all">
        <span class="material-symbols-outlined text-lg text-secondary">manage_history</span>
        <span>سجل تعديلات المحاسبين</span>
    </a>
@endsection

@section('content')
<div class="space-y-6">

    <!-- 1. بطاقات الملخص المالي (تُحسب ديناميكياً حسب الفلتر المحدد) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

        <!-- البطاقة 1: إجمالي الكشوفات -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-xs flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-[11px] font-bold text-slate-400 uppercase">إجمالي الكشوفات</p>
                    <h4 class="text-2xl font-black text-slate-900 mt-1 font-mono">{{ number_format($stats['total_count']) }}</h4>
                </div>
                <div class="w-11 h-11 rounded-2xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-2xl">receipt_long</span>
                </div>
            </div>

            <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-[11px] font-bold">
                <span class="inline-flex items-center gap-1 text-amber-700">
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                    <span>قيد المراجعة: {{ number_format($stats['open_count']) }}</span>
                </span>
                <span class="inline-flex items-center gap-1 text-emerald-700">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span>مصفاة: {{ number_format($stats['settled_count']) }}</span>
                </span>
            </div>
        </div>

        <!-- البطاقة 2: إجمالي مبالغ الآجل -->
        <div class="bg-white p-5 rounded-2xl border border-amber-200/90 shadow-xs flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-[11px] font-bold text-amber-700 uppercase">إجمالي مبالغ الآجل</p>
                    <h4 class="text-2xl font-black text-amber-900 mt-1 font-mono" dir="ltr">
                        {{ number_format($stats['total_postpaid_amount']) }} <span class="text-xs font-extrabold">ر.ي</span>
                    </h4>
                </div>
                <div class="w-11 h-11 rounded-2xl bg-amber-50 text-amber-600 border border-amber-200 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-2xl">payments</span>
                </div>
            </div>

            <div class="mt-3 pt-3 border-t border-amber-100 text-[11px] font-bold text-slate-500">
                مجموع المبالغ الآجلة المسجلة في الكشوفات المحددة
            </div>
        </div>

        <!-- البطاقة 3: إجمالي الخصومات (مع التفصيل الدقيق تحتها) -->
        <div class="bg-white p-5 rounded-2xl border border-rose-200/90 shadow-xs flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-[11px] font-bold text-rose-700 uppercase">إجمالي الخصومات (-)</p>
                    <h4 class="text-2xl font-black text-rose-800 mt-1 font-mono" dir="ltr">
                        {{ number_format($stats['total_deductions']) }} <span class="text-xs font-extrabold">ر.ي</span>
                    </h4>
                </div>
                <div class="w-11 h-11 rounded-2xl bg-rose-50 text-rose-600 border border-rose-200 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-2xl">remove_circle</span>
                </div>
            </div>

            <div class="mt-3 pt-2.5 border-t border-rose-100 space-y-1 text-[11px] font-bold">
                <div class="flex items-center justify-between text-slate-600">
                    <span>🏢 عمولة المكاتب:</span>
                    <span class="font-mono font-extrabold text-rose-700" dir="ltr">{{ number_format($stats['office_commission_total']) }} ر.ي</span>
                </div>
                <div class="flex items-center justify-between text-slate-600">
                    <span>👷 تنزيل العمال:</span>
                    <span class="font-mono font-extrabold text-rose-700" dir="ltr">{{ number_format($stats['workers_unloading_total']) }} ر.ي</span>
                </div>
                <div class="flex items-center justify-between text-slate-500">
                    <span>➕ خصومات أخرى:</span>
                    <span class="font-mono font-extrabold text-slate-700" dir="ltr">{{ number_format($stats['other_deductions_total']) }} ر.ي</span>
                </div>
            </div>
        </div>

        <!-- البطاقة 4: إجمالي الصافي المسلم للسائقين -->
        <div class="bg-gradient-to-br from-emerald-600 to-emerald-700 text-white p-5 rounded-2xl border border-emerald-700 shadow-sm flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-[11px] font-extrabold text-emerald-100 uppercase">إجمالي الصافي للسائقين (=)</p>
                    <h4 class="text-2xl font-black text-white mt-1 font-mono" dir="ltr">
                        {{ number_format($stats['net_driver_amount']) }} <span class="text-xs font-extrabold text-emerald-100">ر.ي</span>
                    </h4>
                </div>
                <div class="w-11 h-11 rounded-2xl bg-white/15 text-white flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-2xl">account_balance_wallet</span>
                </div>
            </div>

            <div class="mt-3 pt-3 border-t border-white/15 text-[11px] font-bold text-emerald-100">
                صافي مستحقات السائقين بعد طرح كافة الخصومات
            </div>
        </div>

    </div>

    <!-- 2. بطاقة الفلاتر والبحث المتقدم -->
    <div class="bg-white p-5 sm:p-6 rounded-3xl border border-slate-200/90 shadow-xs">
        <form method="GET" action="{{ route('admin.manifests.index') }}" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3.5 items-end">

                <!-- 1. فلتر حسب المكتب -->
                <div class="lg:col-span-3">
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">المكتب:</label>
                    <select name="office_id"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-bold text-slate-700 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                        <option value="">جميع المكاتب والفروع</option>
                        @foreach ($offices as $off)
                            <option value="{{ $off->id }}" {{ ($filters['office_id'] ?? '') == $off->id ? 'selected' : '' }}>
                                {{ $off->name }} {{ $off->branch_name ? "({$off->branch_name})" : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- 2. فلتر حسب المحاسب -->
                <div class="lg:col-span-3">
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">المحاسب:</label>
                    <select name="accountant_id"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-bold text-slate-700 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                        <option value="">جميع المحاسبين</option>
                        @foreach ($accountants as $acc)
                            <option value="{{ $acc->id }}" {{ ($filters['accountant_id'] ?? '') == $acc->id ? 'selected' : '' }}>
                                {{ $acc->name }} {{ $acc->office ? "— ({$acc->office->name})" : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- 3. فلتر حسب حالة الكشف -->
                <div class="lg:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">حالة الكشف:</label>
                    <select name="status"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-bold text-slate-700 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                        <option value="">الكل</option>
                        <option value="open" {{ ($filters['status'] ?? '') === 'open' ? 'selected' : '' }}>قيد المراجعة (مفتوح)</option>
                        <option value="settled" {{ ($filters['status'] ?? '') === 'settled' ? 'selected' : '' }}>تمت التصفية</option>
                    </select>
                </div>

                <!-- 4. من تاريخ -->
                <div class="lg:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">من تاريخ:</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-mono font-bold text-slate-700 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                </div>

                <!-- 5. إلى تاريخ -->
                <div class="lg:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">إلى تاريخ:</label>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-mono font-bold text-slate-700 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                </div>

                <!-- 6. حقل البحث المتقدم -->
                <div class="lg:col-span-9">
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">بحث بالسائق، جوال السائق، المكتب المرسل، الفرع، أو رقم الكشف:</label>
                    <div class="relative">
                        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                               placeholder="اكتب اسم السائق، رقم جواله، اسم المكتب المرسل، أو رقم الكشف..."
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 pr-9 text-xs font-semibold text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                        <span class="material-symbols-outlined text-slate-400 absolute right-2.5 top-1/2 -translate-y-1/2 text-lg pointer-events-none">search</span>
                    </div>
                </div>

                <!-- 7. أزرار التطبيق والإلغاء -->
                <div class="lg:col-span-3 flex items-center gap-2">
                    <button type="submit"
                            class="flex-1 py-2.5 px-4 rounded-xl bg-primary text-white text-xs font-bold hover:bg-primary-800 transition-colors flex items-center justify-center gap-1.5 shadow-sm">
                        <span class="material-symbols-outlined text-base">filter_alt</span>
                        <span>تطبيق الفلتر</span>
                    </button>

                    @if(!empty($filters['office_id']) || !empty($filters['accountant_id']) || !empty($filters['status']) || !empty($filters['date_from']) || !empty($filters['date_to']) || !empty($filters['search']))
                        <a href="{{ route('admin.manifests.index') }}"
                           class="py-2.5 px-3.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 text-xs font-bold transition-colors flex items-center gap-1 shrink-0"
                           title="إعادة ضبط الفلاتر">
                            <span class="material-symbols-outlined text-base">restart_alt</span>
                            <span>إلغاء</span>
                        </a>
                    @endif
                </div>

            </div>
        </form>
    </div>

    <!-- 3. جدول الكشوفات -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs overflow-hidden">

        <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 bg-slate-50/50">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-primary text-2xl">receipt_long</span>
                <div>
                    <h3 class="text-sm font-black text-slate-900">سجل كشوفات السائقين والتصفيات</h3>
                    <p class="text-xs text-slate-500">عرض تفصيلي لكل كشف سائق، طروده، ومعادلته المالية</p>
                </div>
            </div>

            <span class="px-3 py-1 rounded-full text-xs font-extrabold bg-slate-100 text-slate-700 border border-slate-200">
                إجمالي النتائج: {{ number_format($manifests->total()) }}
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead>
                    <tr class="bg-slate-100/70 text-slate-600 font-extrabold uppercase border-b border-slate-200 text-[11px]">
                        <th class="py-4 px-4">رقم الكشف والتاريخ</th>
                        <th class="py-4 px-4">المكتب والمحاسب</th>
                        <th class="py-4 px-4">السائق</th>
                        <th class="py-4 px-4">جهة القدوم (المرسل)</th>
                        <th class="py-4 px-4 text-center">عدد الطرود</th>
                        <th class="py-4 px-4">المعادلة المالية للكشف</th>
                        <th class="py-4 px-4 text-center">حالة الكشف</th>
                        <th class="py-4 px-4 text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                    @forelse ($manifests as $manifest)
                        <tr class="hover:bg-slate-50/80 transition-colors">

                            <!-- 1. رقم الكشف والتاريخ -->
                            <td class="py-4 px-4">
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-1 rounded-lg bg-primary/10 text-primary font-mono font-black text-xs">
                                        #{{ $manifest->id }}
                                    </span>
                                    @if ($manifest->manifest_number)
                                        <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200 font-mono text-[11px]">
                                            ورقي: {{ $manifest->manifest_number }}
                                        </span>
                                    @endif
                                </div>
                                <div class="font-mono text-[11px] font-bold text-slate-500 mt-1" dir="ltr">
                                    {{ $manifest->manifest_date?->format('Y-m-d') }}
                                </div>
                            </td>

                            <!-- 2. المكتب والمحاسب المسؤول -->
                            <td class="py-4 px-4">
                                @if ($manifest->office)
                                    <a href="{{ route('admin.manifests.index', array_merge(request()->query(), ['office_id' => $manifest->office_id])) }}"
                                       class="font-black text-slate-900 hover:text-secondary underline decoration-dotted block">
                                        {{ $manifest->office->name }}
                                    </a>
                                @else
                                    <span class="text-slate-400">مكتب غير محدد</span>
                                @endif

                                @if ($manifest->accountant)
                                    <a href="{{ route('admin.manifests.index', array_merge(request()->query(), ['accountant_id' => $manifest->accountant_id])) }}"
                                       class="inline-flex items-center gap-1 text-[11px] text-slate-500 hover:text-primary mt-0.5">
                                        <span class="material-symbols-outlined text-[13px] text-secondary">person</span>
                                        <span>{{ $manifest->accountant->name }}</span>
                                    </a>
                                @else
                                    <span class="text-[11px] text-slate-400 block mt-0.5">بدون محاسب محدد</span>
                                @endif
                            </td>

                            <!-- 3. السائق -->
                            <td class="py-4 px-4">
                                <div class="font-extrabold text-slate-900 text-sm">{{ $manifest->driver_name }}</div>
                                @if ($manifest->driver_phone)
                                    <div class="flex items-center gap-1.5 mt-1">
                                        <span class="font-mono text-xs font-bold text-slate-600" dir="ltr">{{ $manifest->driver_phone }}</span>
                                        <a href="https://wa.me/967{{ $manifest->driver_phone }}" target="_blank"
                                           title="واتساب السائق"
                                           class="w-6 h-6 rounded-md bg-emerald-50 text-emerald-600 border border-emerald-200 flex items-center justify-center hover:bg-emerald-100">
                                            <span class="material-symbols-outlined text-[13px]">chat</span>
                                        </a>
                                    </div>
                                @else
                                    <span class="text-[11px] text-slate-400">بدون رقم جوال</span>
                                @endif
                            </td>

                            <!-- 4. جهة القدوم -->
                            <td class="py-4 px-4">
                                <span class="font-extrabold text-slate-800 block">{{ $manifest->source_office_name ?: 'غير محدد' }}</span>
                                @if ($manifest->source_branch_name)
                                    <span class="text-[11px] text-slate-500 block mt-0.5">فرع: {{ $manifest->source_branch_name }}</span>
                                @endif
                            </td>

                            <!-- 5. عدد الطرود -->
                            <td class="py-4 px-4 text-center">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-slate-100 text-slate-800 font-mono font-black text-xs border border-slate-200">
                                    <span class="material-symbols-outlined text-sm text-primary">inventory_2</span>
                                    <span>{{ $manifest->parcels_count }}</span>
                                </span>
                                <div class="flex items-center justify-center gap-1.5 mt-1.5 text-[10px] font-extrabold">
                                    <span class="px-1.5 py-0.5 rounded bg-amber-50 text-amber-800 border border-amber-200">
                                        آجل: {{ $manifest->postpaid_parcels_count }}
                                    </span>
                                    <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200">
                                        محاسب: {{ $manifest->prepaid_parcels_count }}
                                    </span>
                                </div>
                            </td>

                            <!-- 6. المعادلة المالية للكشف -->
                            <td class="py-4 px-4">
                                <div class="space-y-1 text-[11px] min-w-[170px]">
                                    <div class="flex items-center justify-between">
                                        <span class="text-slate-500">مجموع الآجل:</span>
                                        <span class="font-mono font-extrabold text-amber-800" dir="ltr">{{ number_format($manifest->total_postpaid_amount) }} ر.ي</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-slate-500">الخصومات (-):</span>
                                        <span class="font-mono font-extrabold text-rose-700" dir="ltr">- {{ number_format($manifest->total_deductions) }} ر.ي</span>
                                    </div>
                                    <div class="flex items-center justify-between pt-1 border-t border-slate-100">
                                        <span class="font-extrabold text-emerald-800">الصافي للسائق:</span>
                                        <span class="px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200 font-mono font-black" dir="ltr">
                                            {{ number_format($manifest->net_driver_amount) }} ر.ي
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <!-- 7. حالة الكشف -->
                            <td class="py-4 px-4 text-center">
                                @if ($manifest->status === 'settled')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-extrabold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="material-symbols-outlined text-[13px]">check_circle</span>
                                        <span>تمت التصفية</span>
                                    </span>
                                    @if ($manifest->settled_at)
                                        <div class="font-mono text-[10px] font-bold text-slate-400 mt-1" dir="ltr">
                                            {{ $manifest->settled_at->format('Y-m-d h:i A') }}
                                        </div>
                                    @endif
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-extrabold bg-amber-50 text-amber-800 border border-amber-300">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                        <span>قيد المراجعة</span>
                                    </span>
                                @endif
                            </td>

                            <!-- 8. زر عرض التفاصيل -->
                            <td class="py-4 px-4 text-center">
                                <a href="{{ route('admin.manifests.show', $manifest->id) }}"
                                   class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-primary hover:bg-primary-800 text-white text-xs font-extrabold transition-colors shadow-2xs">
                                    <span class="material-symbols-outlined text-sm text-secondary">visibility</span>
                                    <span>عرض التفاصيل</span>
                                </a>
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-16 text-center">
                                <div class="flex flex-col items-center justify-center max-w-sm mx-auto">
                                    <div class="w-16 h-16 rounded-3xl bg-slate-100 text-slate-400 border border-slate-200 flex items-center justify-center mb-3">
                                        <span class="material-symbols-outlined text-3xl">receipt_long</span>
                                    </div>
                                    <h4 class="text-sm font-extrabold text-slate-800">لا توجد كشوفات سائقين مطابقة</h4>
                                    <p class="text-xs text-slate-400 mt-1">لم يتم العثور على أي كشف سائق يطابق معايير الفلترة أو البحث الحالية.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($manifests->hasPages())
            <div class="p-4 border-t border-slate-100 bg-slate-50/50">
                {{ $manifests->links() }}
            </div>
        @endif

    </div>

</div>
@endsection

