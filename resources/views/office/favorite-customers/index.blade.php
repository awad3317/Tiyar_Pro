@php
    $showSearch = true;
    $searchPlaceholder = 'بحث بالاسم أو برقم الهاتف...';
@endphp

@extends('office.layouts.app')

@section('title', 'العملاء المميزون')

@section('alpine', 'favoriteCustomersApp()')

@section('content')
    <!-- شريط العداد الفرعي وزر الإضافة السريع -->
    <div class="bg-white border-b border-slate-100 px-4 py-3 flex items-center justify-between sticky top-[125px] z-20 shadow-2xs">
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

    <!-- قائمة بطاقات العملاء المميزين -->
    <main class="flex-1 p-3 space-y-2.5 overflow-y-auto">
        <template x-for="c in filteredCustomers" :key="c.id">
            @include('office.favorite-customers.partials.customer-card')
        </template>

        <!-- حالة عدم وجود نتائج مطابقة للبحث -->
        <div x-show="filteredCustomers.length === 0 && search.trim().length > 0" x-cloak class="text-center py-16 text-slate-400">
            <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-2">
                <span class="material-symbols-outlined text-2xl">search_off</span>
            </div>
            <p class="text-xs font-bold text-slate-600 mb-0.5">لا توجد نتائج مطابقة</p>
            <p class="text-[11px] text-slate-400">جرّب البحث باسم آخر أو برقم هاتف مختلف</p>
        </div>

        <!-- حالة القائمة فارغة تماماً بدون بحث -->
        <div x-show="customers.length === 0 && search.trim().length === 0" x-cloak class="text-center py-16 text-slate-400">
            <div class="w-14 h-14 rounded-2xl bg-amber-50 text-secondary flex items-center justify-center mx-auto mb-3 shadow-2xs">
                <span class="material-symbols-outlined text-3xl" style="font-variation-settings: 'FILL' 1;">stars</span>
            </div>
            <h3 class="text-sm font-black text-slate-800 mb-1">لا يوجد عملاء مميزين بعد</h3>
            <p class="text-xs text-slate-500 mb-4 max-w-xs mx-auto">
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

