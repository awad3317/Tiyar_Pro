{{-- قائمة الإجراءات الثانوية المشتركة بين البطاقة وصف الجدول --}}
@php
    $includePrintInMenu = $includePrintInMenu ?? true;
@endphp

@if ($includePrintInMenu)
    {{-- طباعة السند داخل القائمة للجوال فقط (على الكمبيوتر يظهر زر مباشر بضغطة واحدة) --}}
    <button type="button"
            data-card-action="print-receipt"
            data-receipt-url="{{ route('office.outgoing-parcels.receipt', $parcel) }}"
            @click="menuOpen = false"
            class="lg:hidden w-full px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 flex items-center gap-2 transition-colors">
        <span class="material-symbols-outlined text-base text-primary">print</span>
        <span>طباعة السند</span>
    </button>
@endif

{{-- تعديل البيانات (متاح فقط في الانتظار أو في الطريق وقبل استلام الفرع) --}}
@if ($canEditParcel)
    <button type="button"
            data-card-action="edit"
            data-edit-payload="{{ $editPayloadJson }}"
            @click="menuOpen = false"
            class="w-full px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 flex items-center gap-2 transition-colors">
        <span class="material-symbols-outlined text-base text-amber-600">edit</span>
        <span>تعديل البيانات</span>
    </button>
@endif

{{-- إجراءات حالة في الانتظار --}}
@if ($parcel->status === \App\Enums\OutgoingParcelStatus::PENDING)
    @if (! $isAttachedToShipment)
        @if ($hasSentShipments)
            <a href="{{ route('office.shipments.sent.index') }}"
               @click="menuOpen = false"
               class="w-full px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 flex items-center gap-2 transition-colors">
                <span class="material-symbols-outlined text-base text-indigo-600">local_shipping</span>
                <span>ضم إلى شحنة</span>
            </a>
        @else
            <button type="button"
                    data-card-action="locked-shipments"
                    @click="menuOpen = false"
                    class="w-full px-3.5 py-2 text-xs font-bold text-slate-500 hover:bg-slate-50 flex items-center gap-2 transition-colors">
                <span class="material-symbols-outlined text-base text-amber-600">lock</span>
                <span>ضم إلى شحنة</span>
            </button>
        @endif

        <div class="my-1 border-t border-slate-100"></div>
        <button type="button"
                data-card-action="cancel"
                data-action-payload="{{ $cancelPayloadJson }}"
                @click="menuOpen = false"
                class="w-full px-3.5 py-2 text-xs font-extrabold text-rose-600 hover:bg-rose-50 flex items-center gap-2 transition-colors">
            <span class="material-symbols-outlined text-base text-rose-600">cancel</span>
            <span>إلغاء الطرد</span>
        </button>
    @else
        @if ($hasSentShipments)
            <a href="{{ route('office.shipments.sent.show', $parcel->shipment_id) }}"
               @click="menuOpen = false"
               class="w-full px-3.5 py-2 text-xs font-bold text-indigo-700 hover:bg-indigo-50/70 flex items-center gap-2 transition-colors">
                <span class="material-symbols-outlined text-base text-indigo-600">inventory_2</span>
                <span class="truncate">عرض الشحنة #{{ $shipment?->shipment_number ?? '...' }}</span>
            </a>
        @endif

        <div class="my-1 border-t border-slate-100"></div>
        <button type="button"
                data-card-action="detach"
                data-action-payload="{{ $detachPayloadJson }}"
                @click="menuOpen = false"
                class="w-full px-3.5 py-2 text-xs font-extrabold text-amber-800 hover:bg-amber-50 flex items-center gap-2 transition-colors">
            <span class="material-symbols-outlined text-base text-amber-600">link_off</span>
            <span>إخراج من الشحنة</span>
        </button>
    @endif

{{-- إجراءات حالة في الطريق --}}
@elseif ($parcel->status === \App\Enums\OutgoingParcelStatus::IN_TRANSIT)
    @if ($parcel->isExternal())
        <button type="button"
                data-card-action="deliver"
                data-action-payload="{{ $deliverPayloadJson }}"
                @click="menuOpen = false"
                class="w-full px-3.5 py-2 text-xs font-extrabold text-emerald-700 hover:bg-emerald-50 flex items-center gap-2 transition-colors">
            <span class="material-symbols-outlined text-base text-emerald-600">check_circle</span>
            <span>تم التسليم</span>
        </button>
    @endif

    @if ($isAttachedToShipment)
        @if ($hasSentShipments)
            <a href="{{ route('office.shipments.sent.show', $parcel->shipment_id) }}"
               @click="menuOpen = false"
               class="w-full px-3.5 py-2 text-xs font-bold text-indigo-700 hover:bg-indigo-50/70 flex items-center gap-2 transition-colors">
                <span class="material-symbols-outlined text-base text-indigo-600">inventory_2</span>
                <span class="truncate">عرض الشحنة #{{ $shipment?->shipment_number ?? '...' }}</span>
            </a>
        @endif

        <div class="my-1 border-t border-slate-100"></div>
        <button type="button"
                data-card-action="detach"
                data-action-payload="{{ $detachPayloadJson }}"
                @click="menuOpen = false"
                class="w-full px-3.5 py-2 text-xs font-extrabold text-amber-800 hover:bg-amber-50 flex items-center gap-2 transition-colors">
            <span class="material-symbols-outlined text-base text-amber-600">link_off</span>
            <span>إخراج من الشحنة</span>
        </button>
    @else
        <div class="my-1 border-t border-slate-100"></div>
        <button type="button"
                data-card-action="cancel"
                data-action-payload="{{ $cancelPayloadJson }}"
                @click="menuOpen = false"
                class="w-full px-3.5 py-2 text-xs font-extrabold text-rose-600 hover:bg-rose-50 flex items-center gap-2 transition-colors">
            <span class="material-symbols-outlined text-base text-rose-600">cancel</span>
            <span>إلغاء الطرد</span>
        </button>
    @endif
@endif
