@extends('admin.layouts.app')

@section('title', 'العملاء المميزين للمكاتب')
@section('page-title', 'العملاء المميزين للمكاتب')
@section('page-subtitle', 'استعراض وتحليل كافة العملاء المفضلين والمميزين لدى شبكة المكاتب مع إمكانية التصفية والبحث')

@section('content')
<div class="space-y-6">

    <!-- 1. شريط الإحصائيات السريعة -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        
        <!-- إجمالي العملاء المميزين كلياً -->
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-slate-400 uppercase">إجمالي العملاء المميزين</p>
                <h4 class="text-2xl font-black text-slate-900 mt-1">{{ number_format($stats['total_all']) }}</h4>
                <p class="text-[10px] text-slate-400 mt-0.5">في كافة مكاتب الشبكة</p>
            </div>
            <div class="w-11 h-11 rounded-2xl bg-amber-50 text-amber-600 border border-amber-200 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-2xl">stars</span>
            </div>
        </div>

        <!-- عدد المكاتب التي تمتلك عملاء -->
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-slate-400 uppercase">مكاتب لديها عملاء</p>
                <h4 class="text-2xl font-black text-blue-600 mt-1">{{ number_format($stats['offices_with_customers']) }}</h4>
                <p class="text-[10px] text-slate-400 mt-0.5">من إجمالي {{ $offices->count() }} مكتب</p>
            </div>
            <div class="w-11 h-11 rounded-2xl bg-blue-50 text-blue-600 border border-blue-200 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-2xl">domain</span>
            </div>
        </div>

        <!-- المكاتب المفعلة لميزة VIP -->
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-slate-400 uppercase">ميزة VIP مفعلة لديهم</p>
                <h4 class="text-2xl font-black text-emerald-600 mt-1">{{ number_format($stats['offices_with_vip_feature']) }}</h4>
                <p class="text-[10px] text-slate-400 mt-0.5">مكتب مفعّل له التبويب</p>
            </div>
            <div class="w-11 h-11 rounded-2xl bg-emerald-50 text-emerald-600 border border-emerald-200 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-2xl">verified</span>
            </div>
        </div>

        <!-- إحصائية الفلتر / المكتب المحدد -->
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-xs flex items-center justify-between">
            @if ($selectedOffice)
                <div class="truncate">
                    <p class="text-[11px] font-bold text-slate-400 uppercase truncate" title="{{ $selectedOffice->name }}">
                        {{ $selectedOffice->name }}
                    </p>
                    <div class="flex items-center gap-2 mt-1">
                        <h4 class="text-2xl font-black text-secondary">{{ number_format($selectedOfficeCount) }}</h4>
                        <span class="text-xs font-bold text-slate-500">عميل</span>
                    </div>
                    <div class="mt-1 flex items-center gap-1.5">
                        @if ($selectedOfficeHasVipFeature)
                            <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                <span class="material-symbols-outlined text-[12px]">check_circle</span>
                                ميزة VIP مفعلة
                            </span>
                        @else
                            <a href="{{ route('admin.offices.features', $selectedOffice) }}" 
                               class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 transition-colors"
                               title="انقر لتفعيل الميزة لهذا المكتب">
                                <span class="material-symbols-outlined text-[12px]">warning</span>
                                ميزة VIP معطلة
                            </a>
                        @endif
                    </div>
                </div>
                <div class="w-11 h-11 rounded-2xl bg-amber-50 text-secondary border border-secondary/30 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-2xl">contact_page</span>
                </div>
            @else
                <div>
                    <p class="text-[11px] font-bold text-slate-400 uppercase">نتائج التصفية الحالية</p>
                    <h4 class="text-2xl font-black text-slate-900 mt-1">{{ number_format($stats['filtered_total']) }}</h4>
                    <p class="text-[10px] text-slate-400 mt-0.5">عميل معروض بالجدول</p>
                </div>
                <div class="w-11 h-11 rounded-2xl bg-slate-100 text-slate-700 border border-slate-200 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-2xl">filter_list</span>
                </div>
            @endif
        </div>
    </div>

    <!-- 2. بطاقة الفلاتر والبحث المتقدم -->
    <div class="bg-white p-5 sm:p-6 rounded-3xl border border-slate-200/90 shadow-xs">
        <form method="GET" action="{{ route('admin.favorite_customers.index') }}" class="space-y-4">
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-4 items-end">
                
                <!-- فلترة المكتب -->
                <div class="lg:col-span-6">
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        تصفية حسب المكتب:
                    </label>
                    <div class="relative">
                        <select name="office_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-bold text-slate-700 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                            <option value="">جميع المكاتب والفروع ({{ $stats['total_all'] }} عميل)</option>
                            @foreach ($offices as $off)
                                <option value="{{ $off->id }}" {{ ($filters['office_id'] ?? '') == $off->id ? 'selected' : '' }}>
                                    {{ $off->name }} {{ $off->branch_name ? "({$off->branch_name})" : '' }} — ({{ $off->favorite_customers_count }} عميل)
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- البحث بالاسم أو الهاتف -->
                <div class="lg:col-span-4">
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        البحث بالاسم أو رقم الهاتف:
                    </label>
                    <div class="relative">
                        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                               placeholder="اكتب اسم العميل أو رقمه (مثال: 77XXXXXXX)..."
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 pr-9 text-xs font-semibold text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                        <span class="material-symbols-outlined text-slate-400 absolute right-2.5 top-1/2 -translate-y-1/2 text-lg pointer-events-none">search</span>
                    </div>
                </div>

                <!-- أزرار الإجراءات -->
                <div class="lg:col-span-2 flex items-center gap-2">
                    <button type="submit" class="flex-1 py-2.5 px-4 rounded-xl bg-primary text-white text-xs font-bold hover:bg-primary-800 transition-colors flex items-center justify-center gap-1.5 shadow-sm">
                        <span class="material-symbols-outlined text-base">filter_alt</span>
                        <span>فلترة</span>
                    </button>
                    
                    @if(!empty($filters['office_id']) || !empty($filters['search']))
                        <a href="{{ route('admin.favorite_customers.index') }}" 
                           class="p-2.5 rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-100 transition-colors shrink-0" 
                           title="إلغاء التصفية وإعادة تعيين">
                            <span class="material-symbols-outlined text-base">restart_alt</span>
                        </a>
                    @endif
                </div>

            </div>

        </form>
    </div>

    <!-- 3. جدول عرض العملاء المميزين -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs overflow-hidden">
        
        <!-- هيدر الجدول -->
        <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 bg-slate-50/50">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-amber-500 text-2xl">star</span>
                <div>
                    <h3 class="text-sm font-black text-slate-900">قائمة العملاء المميزين</h3>
                    <p class="text-xs text-slate-500">
                        @if ($selectedOffice)
                            العملاء المسجلون لدى مكتب: <span class="font-bold text-slate-800">{{ $selectedOffice->name }}</span>
                        @else
                            عرض كافة العملاء المميزين المسجلين في النظام
                        @endif
                    </p>
                </div>
            </div>

            <span class="px-3 py-1 rounded-full text-xs font-extrabold bg-slate-100 text-slate-700 border border-slate-200">
                إجمالي النتائج: {{ $customers->total() }} عميل
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead>
                    <tr class="bg-slate-100/70 text-slate-600 font-extrabold uppercase border-b border-slate-200 text-[11px]">
                        <th class="py-4 px-5 text-center w-14">#</th>
                        <th class="py-4 px-4">اسم العميل المميز</th>
                        <th class="py-4 px-4">رقم الهاتف</th>
                        <th class="py-4 px-4">المكتب والفرع</th>
                        <th class="py-4 px-4">حالة ميزة VIP للمكتب</th>
                        <th class="py-4 px-4">تاريخ الإضافة / التحديث</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                    @forelse ($customers as $customer)
                        <tr class="hover:bg-amber-50/30 transition-colors">
                            
                            <!-- الرقم التسلسلي -->
                            <td class="py-4 px-5 text-center font-mono font-bold text-slate-400">
                                {{ ($customers->currentPage() - 1) * $customers->perPage() + $loop->iteration }}
                            </td>

                            <!-- اسم العميل المميز -->
                            <td class="py-4 px-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 border border-amber-200/80 flex items-center justify-center shrink-0">
                                        <span class="material-symbols-outlined text-base">star</span>
                                    </div>
                                    <span class="font-black text-slate-900 text-sm">
                                        {{ $customer->name }}
                                    </span>
                                </div>
                            </td>

                            <!-- رقم الهاتف وإجراءات سريعة -->
                            <td class="py-4 px-4">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-bold text-slate-800 text-sm tracking-wide" dir="ltr">
                                        {{ $customer->phone }}
                                    </span>
                                    
                                    <!-- زر محادثة واتساب -->
                                    <a href="https://wa.me/967{{ preg_replace('/^0+/', '', preg_replace('/[^0-9]/', '', $customer->phone)) }}"
                                       target="_blank" 
                                       title="مراسلة عبر واتساب"
                                       class="w-7 h-7 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-600 border border-emerald-200 flex items-center justify-center transition-colors">
                                        <span class="material-symbols-outlined text-[15px]">chat</span>
                                    </a>

                                    <!-- زر الاتصال الهاتفي -->
                                    <a href="tel:{{ $customer->phone }}" 
                                       title="اتصال هاتفي"
                                       class="w-7 h-7 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-600 border border-blue-200 flex items-center justify-center transition-colors">
                                        <span class="material-symbols-outlined text-[15px]">call</span>
                                    </a>
                                </div>
                            </td>

                            <!-- المكتب والفرع التابع له -->
                            <td class="py-4 px-4">
                                @if ($customer->office)
                                    <div>
                                        <a href="{{ route('admin.favorite_customers.index', ['office_id' => $customer->office->id]) }}" 
                                           class="font-black text-slate-900 hover:text-secondary underline decoration-dotted transition-colors block"
                                           title="تصفية حسب هذا المكتب">
                                            {{ $customer->office->name }}
                                        </a>
                                        <span class="text-[11px] text-slate-400">
                                            {{ $customer->office->branch_name ?: 'الفرع الرئيسي' }}
                                        </span>
                                    </div>
                                @else
                                    <span class="text-slate-400 italic">مكتب غير محدد</span>
                                @endif
                            </td>

                            <!-- حالة ميزة VIP في هذا المكتب -->
                            <td class="py-4 px-4">
                                @if ($customer->office)
                                    @if ($customer->office->hasFeature('vip_customers_tab'))
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <span class="material-symbols-outlined text-sm">verified</span>
                                            <span>مفعلة بالمكتب</span>
                                        </span>
                                    @else
                                        <a href="{{ route('admin.offices.features', $customer->office) }}"
                                           class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 transition-colors"
                                           title="الميزة غير مفعلة لهذا المكتب حالياً - انقر لإدارتها">
                                            <span class="material-symbols-outlined text-sm">block</span>
                                            <span>معطلة (اضغط للتفعيل)</span>
                                        </a>
                                    @endif
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>

                            <!-- تاريخ الإضافة والتحديث -->
                            <td class="py-4 px-4 font-mono text-slate-500">
                                <div class="text-[11px] font-bold text-slate-700">
                                    {{ $customer->updated_at ? $customer->updated_at->format('Y-m-d H:i') : ($customer->created_at ? $customer->created_at->format('Y-m-d H:i') : '-') }}
                                </div>
                                <div class="text-[10px] text-slate-400">
                                    {{ $customer->updated_at ? $customer->updated_at->diffForHumans() : '' }}
                                </div>
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-16 text-center">
                                <div class="flex flex-col items-center justify-center max-w-sm mx-auto">
                                    <div class="w-16 h-16 rounded-3xl bg-amber-50 text-amber-500 border border-amber-200 flex items-center justify-center mb-3">
                                        <span class="material-symbols-outlined text-3xl">stars</span>
                                    </div>
                                    <h4 class="text-sm font-extrabold text-slate-800">لا يوجد عملاء مميزين مطابقين</h4>
                                    <p class="text-xs text-slate-400 mt-1">
                                        @if (!empty($filters['search']) || !empty($filters['office_id']))
                                            لم نجد أي نتائج مطابقة لكلمات البحث أو الفلتر المحدد. جرب تغيير شروط الفلترة.
                                        @else
                                            لم يقم أي مكتب بإضافة عملاء مميزين حتى الآن.
                                        @endif
                                    </p>
                                    @if (!empty($filters['search']) || !empty($filters['office_id']))
                                        <a href="{{ route('admin.favorite_customers.index') }}" 
                                           class="mt-3 px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors">
                                            إعادة تعيين الفلاتر
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- الترقيم (Pagination) -->
        @if ($customers->hasPages())
            <div class="p-4 border-t border-slate-100 bg-slate-50/50">
                {{ $customers->links() }}
            </div>
        @endif

    </div>

</div>
@endsection

