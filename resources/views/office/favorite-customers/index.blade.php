@php
    $showSearch = true;
    $searchPlaceholder = 'بحث بالاسم أو برقم الهاتف...';
@endphp

@extends('office.layouts.app')

@section('title', 'العملاء المميزون')

@section('alpine', 'favoriteCustomersApp()')

@section('content')
    <!-- 1. شريط العداد الفرعي وزر الإضافة السريع (للجوال < 1024px فقط — مطابق للأصل 100%) -->
    <div @open-vip-customer-modal.window="openCreateModal()"
         class="lg:hidden bg-white border-b border-slate-100 px-4 py-3 flex items-center justify-between sticky top-[125px] z-20 shadow-2xs">
        <div class="flex items-center gap-2">
            <span class="w-7 h-7 rounded-lg bg-amber-50 text-secondary flex items-center justify-center font-bold text-xs">
                <span class="material-symbols-outlined text-base" style="font-variation-settings: 'FILL' 1;">stars</span>
            </span>
            <div>
                <h2 class="text-xs font-black text-slate-800 leading-tight">
                    العملاء المميزين
                    <span class="text-secondary font-mono mr-1" x-text="'(' + filteredCustomers.length + ')'"></span>
                </h2>
                <template x-if="search.trim().length > 0">
                    <p class="text-[10px] text-slate-400 font-bold">
                        تصفية البحث من إجمالي <span x-text="customers.length"></span> عميل
                    </p>
                </template>
            </div>
        </div>

        <button type="button"
                @click="openCreateModal()"
                class="px-3 py-1.5 rounded-xl bg-secondary hover:bg-amber-500 active:scale-95 text-white text-xs font-extrabold shadow-sm shadow-secondary/20 flex items-center gap-1.5 transition-all">
            <span class="material-symbols-outlined text-sm font-bold">person_add</span>
            <span>إضافة عميل</span>
        </button>
    </div>

    <!-- 2. شريط أدوات سطح المكتب الاحترافي (lg: >= 1024px): ملخص العداد + مبدّل (جدول / بطاقات) -->
    <div class="hidden lg:flex items-center justify-between gap-4 px-6 pt-5 pb-1">
        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-white border border-slate-200 dark:bg-[#24303F] dark:border-slate-700/60 shadow-2xs text-xs">
            <span class="material-symbols-outlined text-[16px] text-[#F59E0B]" style="font-variation-settings: 'FILL' 1;">stars</span>
            <span class="font-extrabold text-slate-800 dark:text-slate-100">قائمة العملاء المميزين</span>
            <span class="px-1.5 py-0.5 rounded-md bg-teal-50 text-teal-700 border border-teal-200/80 dark:bg-teal-500/15 dark:text-teal-300 dark:border-teal-500/30 font-mono font-extrabold text-[11px] leading-none"
                  x-text="filteredCustomers.length"></span>
            <template x-if="search.trim().length > 0">
                <span class="text-[11px] text-slate-400 font-bold">
                    من أصل <span class="font-mono" x-text="customers.length"></span>
                </span>
            </template>
        </div>

        <div class="inline-flex items-center gap-1 p-1 rounded-xl bg-white border border-slate-200 dark:bg-[#24303F] dark:border-slate-700/60 shadow-2xs">
            <button type="button"
                    @click="setDesktopView('table')"
                    :class="desktopView === 'table'
                        ? 'bg-[#1C2434] text-[#F59E0B] dark:bg-amber-500/20 dark:text-amber-300 shadow-2xs font-extrabold'
                        : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 font-bold'"
                    class="px-3 py-1.5 rounded-lg text-xs inline-flex items-center gap-1.5 transition-all">
                <span class="material-symbols-outlined text-[16px]">table_rows</span>
                <span>جدول</span>
            </button>
            <button type="button"
                    @click="setDesktopView('cards')"
                    :class="desktopView === 'cards'
                        ? 'bg-[#1C2434] text-[#F59E0B] dark:bg-amber-500/20 dark:text-amber-300 shadow-2xs font-extrabold'
                        : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 font-bold'"
                    class="px-3 py-1.5 rounded-lg text-xs inline-flex items-center gap-1.5 transition-all">
                <span class="material-symbols-outlined text-[16px]">grid_view</span>
                <span>بطاقات</span>
            </button>
        </div>
    </div>

    <!-- قائمة العملاء المميزين -->
    <main class="flex-1 p-3 lg:px-6 lg:py-4 overflow-y-auto">

        <!-- أ. عرض البطاقات (دائماً على الجوال < 1024px، وعلى الكمبيوتر عند اختيار وضع البطاقات) -->
        <div :class="desktopView === 'table' ? 'lg:hidden' : 'lg:space-y-0 lg:grid lg:grid-cols-2 2xl:grid-cols-3 lg:gap-4 lg:content-start'"
             class="mursal-desktop-cards space-y-2.5">
            <template x-for="c in filteredCustomers" :key="c.id">
                @include('office.favorite-customers.partials.customer-card')
            </template>
        </div>

        <!-- ب. وضع الجدول الاحترافي لسطح المكتب (lg: >= 1024px عند اختيار وضع الجدول) -->
        <div x-show="desktopView === 'table'"
             x-cloak
             class="mursal-desktop-table hidden lg:block">
            <div x-show="(isLoading || !clientReady) && filteredCustomers.length === 0"
                 class="bg-white dark:bg-[#24303F] rounded-2xl border border-slate-200/90 dark:border-slate-700/60 shadow-2xs overflow-hidden">
                <table class="w-full text-right border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-700/60 bg-slate-100/90 dark:bg-slate-800/60 text-[11px] font-extrabold text-slate-700 dark:text-slate-300">
                            <th class="py-3 pr-4 pl-3">اسم العميل المميز</th>
                            <th class="py-3 px-3">رقم الهاتف</th>
                            <th class="py-3 px-3">حالة المزامنة</th>
                            <th class="py-3 px-3">التواصل السريع</th>
                            <th class="py-3 pr-3 pl-4 text-left">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-slate-700/50 animate-pulse">
                        @for ($i = 0; $i < 4; $i++)
                            <tr>
                                <td class="py-3.5 pr-4 pl-3"><div class="h-3.5 w-32 rounded bg-slate-200/80 dark:bg-slate-700/60"></div></td>
                                <td class="py-3.5 px-3"><div class="h-3.5 w-24 rounded bg-slate-200/80 dark:bg-slate-700/60"></div></td>
                                <td class="py-3.5 px-3"><div class="h-5 w-16 rounded-md bg-slate-200/70 dark:bg-slate-700/50"></div></td>
                                <td class="py-3.5 px-3"><div class="h-7 w-24 rounded-lg bg-slate-200/70 dark:bg-slate-700/50"></div></td>
                                <td class="py-3.5 pr-3 pl-4"><div class="h-7 w-20 rounded-lg bg-slate-200/70 dark:bg-slate-700/50 mr-auto"></div></td>
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </div>

            <div x-show="filteredCustomers.length > 0"
                 x-cloak
                 class="bg-white dark:bg-[#24303F] rounded-2xl border border-slate-200/90 dark:border-slate-700/60 shadow-2xs overflow-hidden">
                <table class="w-full text-right border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-700/60 bg-slate-100/90 dark:bg-slate-800/60 text-[11px] font-extrabold text-slate-700 dark:text-slate-300">
                            <th class="py-3 pr-4 pl-3">اسم العميل المميز</th>
                            <th class="py-3 px-3">رقم الهاتف</th>
                            <th class="py-3 px-3">حالة المزامنة</th>
                            <th class="py-3 px-3">التواصل السريع</th>
                            <th class="py-3 pr-3 pl-4 text-left">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-slate-700/50 text-xs">
                        <template x-for="c in filteredCustomers" :key="'row-' + c.id">
                            <tr class="hover:bg-gray-50/70 dark:hover:bg-slate-800/40 transition-colors">
                                <!-- 1. اسم العميل -->
                                <td class="py-3 pr-4 pl-3 align-middle">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-amber-50 to-amber-100 border border-amber-200/70 dark:from-amber-500/20 dark:to-amber-500/10 dark:border-amber-500/30 text-secondary flex items-center justify-center shrink-0">
                                            <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">star</span>
                                        </div>
                                        <span class="font-extrabold text-slate-900 dark:text-slate-100 truncate max-w-[220px]" x-text="c.name"></span>
                                    </div>
                                </td>

                                <!-- 2. رقم الهاتف -->
                                <td class="py-3 px-3 align-middle whitespace-nowrap">
                                    <span class="font-mono font-bold text-slate-700 dark:text-slate-300 dir-ltr inline-block" x-text="c.phone"></span>
                                </td>

                                <!-- 3. حالة المزامنة -->
                                <td class="py-3 px-3 align-middle whitespace-nowrap">
                                    <template x-if="c.pending_sync">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:border-amber-500/30 text-[11px] font-extrabold">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-ping"></span>
                                            <span>بانتظار المزامنة</span>
                                        </span>
                                    </template>
                                    <template x-if="!c.pending_sync">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200/80 dark:bg-emerald-500/15 dark:text-emerald-400 dark:border-emerald-500/30 text-[11px] font-bold">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            <span>متزامن</span>
                                        </span>
                                    </template>
                                </td>

                                <!-- 4. التواصل السريع -->
                                <td class="py-3 px-3 align-middle whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5">
                                        <a :href="'tel:' + c.phone"
                                           title="اتصال هاتفي"
                                           class="h-8 px-2.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200/80 dark:bg-emerald-500/15 dark:hover:bg-emerald-500/25 dark:text-emerald-400 dark:border-emerald-500/30 inline-flex items-center gap-1 text-[11px] font-bold transition-colors">
                                            <span class="material-symbols-outlined text-[15px]">call</span>
                                            <span>اتصال</span>
                                        </a>

                                        <a :href="'https://wa.me/967' + String(c.phone).replace(/^0+/, '').replace(/^967/, '')"
                                           target="_blank"
                                           title="مراسلة واتساب"
                                           class="h-8 px-2.5 rounded-lg bg-[#25D366]/10 hover:bg-[#25D366]/20 text-[#128C7E] dark:text-[#4ade80] border border-[#25D366]/30 inline-flex items-center gap-1.5 text-[11px] font-bold transition-colors">
                                            <svg class="w-3.5 h-3.5 fill-[#25D366]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
                                                <path d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L0 480l117.7-30.9c32.4 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-69.8 18.3L72 359.2l-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z" />
                                            </svg>
                                            <span>واتساب</span>
                                        </a>
                                    </div>
                                </td>

                                <!-- 5. الإجراءات -->
                                <td class="py-3 pr-3 pl-4 align-middle whitespace-nowrap text-left">
                                    <div class="inline-flex items-center justify-end gap-1.5">
                                        <button type="button"
                                                @click="openEditModal(c)"
                                                class="h-8 px-2.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-slate-700 border border-gray-200/80 dark:bg-[#1A222C] dark:hover:bg-[#333A48] dark:text-slate-200 dark:border-slate-700 text-xs font-bold inline-flex items-center gap-1 transition-colors">
                                            <span class="material-symbols-outlined text-[15px] text-amber-600 dark:text-amber-400">edit</span>
                                            <span>تعديل</span>
                                        </button>

                                        <button type="button"
                                                @click="openDeleteModal(c)"
                                                class="h-8 px-2.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 border border-rose-200/80 dark:bg-rose-500/15 dark:hover:bg-rose-500/25 dark:text-rose-400 dark:border-rose-500/30 text-xs font-bold inline-flex items-center gap-1 transition-colors">
                                            <span class="material-symbols-outlined text-[15px]">delete</span>
                                            <span>حذف</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- حالة عدم وجود نتائج مطابقة للبحث -->
        <div x-show="!isLoading && clientReady && filteredCustomers.length === 0 && search.trim().length > 0" x-cloak class="text-center py-16 text-slate-400">
            <div class="w-12 h-12 rounded-2xl bg-slate-100 lg:dark:bg-slate-800 text-slate-400 flex items-center justify-center mx-auto mb-2">
                <span class="material-symbols-outlined text-2xl">search_off</span>
            </div>
            <p class="text-xs font-bold text-slate-600 lg:dark:text-slate-300 mb-0.5">لا توجد نتائج مطابقة</p>
            <p class="text-[11px] text-slate-400">جرّب البحث باسم آخر أو برقم هاتف مختلف</p>
        </div>

        <!-- حالة القائمة فارغة تماماً بدون بحث -->
        <div x-show="!isLoading && clientReady && customers.length === 0 && search.trim().length === 0" x-cloak class="text-center py-16 text-slate-400">
            <div class="w-14 h-14 rounded-2xl bg-amber-50 lg:dark:bg-amber-500/15 text-secondary flex items-center justify-center mx-auto mb-3 shadow-2xs">
                <span class="material-symbols-outlined text-3xl" style="font-variation-settings: 'FILL' 1;">stars</span>
            </div>
            <h3 class="text-sm font-black text-slate-800 lg:dark:text-slate-100 mb-1">لا يوجد عملاء مميزين بعد</h3>
            <p class="text-xs text-slate-500 lg:dark:text-slate-400 mb-4 max-w-xs mx-auto">
                يمكنك إضافة أرقام عملائك الدائمين لظهورهم التلقائي والفوري في شاشة الإرسال السريع.
            </p>
            <button type="button"
                    @click="openCreateModal()"
                    class="px-5 py-2.5 rounded-xl bg-secondary text-white text-xs font-extrabold shadow-md shadow-secondary/20 inline-flex items-center gap-1.5 active:scale-95 transition-all">
                <span class="material-symbols-outlined text-base">person_add</span>
                <span>إضافة أول عميل مميز</span>
            </button>
        </div>
    </main>

    <!-- النوافذ المنبثقة -->
    @include('office.favorite-customers.partials.customer-modal')
    @include('office.favorite-customers.partials.delete-modal')
@endsection

@push('scripts')
    @include('office.favorite-customers.partials.favorite-customers-script')
@endpush
