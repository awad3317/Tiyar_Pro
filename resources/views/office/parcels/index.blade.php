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