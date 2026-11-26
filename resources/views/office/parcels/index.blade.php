@php
    $showSearch = true;
@endphp
@extends('office.layouts.app')

@section('title', 'إدارة الطرود')

@section('alpine', 'parcelApp()')


@section('content')
    <!-- تنبيه نفاد باقة الـ SMS والشريط التذكيري -->
    @include('office.parcels.partials.sms-balance-modal')

    <!-- 1. تبويبات التصفية للجوال (< 1024px — مطابقة للأصل 100%) -->
    <nav class="flex lg:hidden border-b border-slate-100 bg-white sticky top-[125px] z-20 text-xs font-bold text-center">
        <template x-for="tab in filters" :key="tab.key">
            <button type="button"
                    @click="setFilter(tab.key)"
                    :class="filter === tab.key ? 'border-b-2 border-secondary text-secondary' : 'text-slate-500'"
                    class="flex-1 py-3">
                <span x-text="tab.label"></span>
                (<span x-text="countFor(tab.key)"></span>)
            </button>
        </template>
    </nav>

    <!-- 2. شريط التبويبات المتراصة لسطح المكتب (lg: >= 1024px — Segmented Filter Pills) -->
    <div class="hidden lg:flex items-center justify-between gap-4 px-6 pt-5 pb-1">
        <div class="inline-flex items-center gap-1 p-1 rounded-xl bg-slate-200/70 border border-slate-200/90">
            <template x-for="tab in filters" :key="'desk-' + tab.key">
                <button type="button"
                        @click="setFilter(tab.key)"
                        :class="filter === tab.key
                            ? 'bg-white text-slate-900 shadow-xs font-extrabold'
                            : 'text-slate-600 hover:text-slate-900 font-bold'"
                        class="px-3.5 py-1.5 rounded-lg text-xs inline-flex items-center gap-1.5 transition-all">
                    <span x-text="tab.label"></span>
                    <span :class="filter === tab.key
                              ? 'bg-secondary/20 text-amber-900'
                              : 'bg-slate-300/60 text-slate-600'"
                          class="px-1.5 py-0.5 rounded-md font-mono text-[11px] leading-none"
                          x-text="countFor(tab.key)"></span>
                </button>
            </template>
        </div>
    </div>

    <!-- قائمة الطرود -->
    <main x-ref="parcelsContainer" class="flex-1 p-3 lg:px-6 lg:py-4 space-y-2.5 lg:space-y-0 lg:grid lg:grid-cols-2 2xl:grid-cols-3 lg:gap-4 lg:content-start overflow-y-auto">
        <template x-for="p in visibleParcels" :key="p.id">
            @include('office.parcels.partials.parcel-card')
        </template>

        <!-- نقطة مراقبة التمرير التلقائي لتحميل الـ 25 طرد التالية مسبقاً -->
        <div x-ref="loadMoreSentinel" x-show="hasMoreParcels" class="lg:col-span-full h-1 w-full pointer-events-none" aria-hidden="true"></div>

        <div x-show="filteredParcels.length === 0" x-cloak class="lg:col-span-full text-center py-12 text-slate-400">
            <span class="material-symbols-outlined text-4xl mb-1">inventory_2</span>
            <p class="text-xs">لا توجد طرود مطابقة</p>
        </div>
    </main>

    <!-- مودال تأكيد الإرجاع للمكتب المرسل -->
    @include('office.parcels.partials.return-confirm-modal')

    <!-- مودال تأكيد إلغاء التسليم وإرجاع الطرد للمكتب الحالي -->
    @include('office.parcels.partials.undo-delivery-modal')

    <!-- مودال تأكيد التسليم -->
    @include('office.parcels.partials.delivery-confirm-modal')
@endsection

@push('scripts')
    @include('office.parcels.partials.parcel-app-script')
@endpush