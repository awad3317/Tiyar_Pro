@extends('office.layouts.app')

@section('title', 'إدارة الطرود')

@section('alpine', 'parcelApp()')

@section('header')
    @include('office.layouts.header', ['showSearch' => true])
@endsection

@section('content')
    <!-- تبويبات التصفية -->
    <nav class="flex border-b border-slate-100 bg-white sticky top-[125px] z-20 text-xs font-bold text-center">
        <template x-for="tab in filters" :key="tab.key">
            <button type="button"
                    @click="filter = tab.key"
                    :class="filter === tab.key ? 'border-b-2 border-secondary text-secondary' : 'text-slate-500'"
                    class="flex-1 py-3">
                <span x-text="tab.label"></span>
                (<span x-text="countFor(tab.key)"></span>)
            </button>
        </template>
    </nav>

    <!-- قائمة الطرود -->
    <main class="flex-1 p-3 space-y-2.5 overflow-y-auto">
        <template x-for="p in filteredParcels" :key="p.id">
            @include('office.parcels.partials.parcel-card')
        </template>

        <div x-show="filteredParcels.length === 0" x-cloak class="text-center py-12 text-slate-400">
            <span class="material-symbols-outlined text-4xl mb-1">inventory_2</span>
            <p class="text-xs">لا توجد طرود مطابقة</p>
        </div>
    </main>

    <!-- مودال تأكيد الإرجاع -->
    @include('office.parcels.partials.return-confirm-modal')

    <!-- إشعار حالة إرسال الـ SMS -->
    <div x-show="smsFeedback.show"
         x-cloak
         x-transition:enter="ease-out duration-250"
         x-transition:enter-start="opacity-0 -translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-4"
         class="fixed top-4 left-4 right-4 max-w-sm mx-auto z-50 p-3.5 rounded-2xl shadow-xl flex items-center gap-2.5 text-xs font-bold transition-all border"
         :class="smsFeedback.isError 
            ? 'bg-rose-600 text-white border-rose-500 shadow-rose-600/30' 
            : 'bg-emerald-600 text-white border-emerald-500 shadow-emerald-600/30'">
        <span class="material-symbols-outlined text-lg" x-text="smsFeedback.isError ? 'error' : 'check_circle'"></span>
        <span class="flex-1 leading-snug" x-text="smsFeedback.message"></span>
        <button type="button" @click="smsFeedback.show = false" class="text-white/80 hover:text-white p-1">
            <span class="material-symbols-outlined text-base">close</span>
        </button>
    </div>
@endsection

@push('scripts')
    @include('office.parcels.partials.parcel-app-script')
@endpush