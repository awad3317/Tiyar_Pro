<div class="space-y-2.5">
    @forelse ($shipments as $shipment)
        @php
            $destList = is_array($shipment->destinations_summary) ? $shipment->destinations_summary : [];
            $createdAgo = $shipment->created_at?->copy()->locale('ar')->diffForHumans();
            $canCancelShipment =
                $shipment->status !== \App\Enums\ShipmentStatus::CANCELLED
                && in_array($shipment->status, [\App\Enums\ShipmentStatus::DRAFT, \App\Enums\ShipmentStatus::IN_TRANSIT], true)
                && (int) $shipment->received_parcels_count === 0;
            $cancelCardPayload = [
                'id'            => (string) $shipment->id,
                'number'        => (string) $shipment->shipment_number,
                'driver_name'   => (string) $shipment->driver_name,
                'parcels_count' => (int) $shipment->parcels_count,
                'url'           => route('office.shipments.sent.cancel', $shipment),
            ];
        @endphp
        <article
            class="relative bg-white border border-slate-200/90 hover:border-secondary rounded-2xl p-3.5 shadow-xs space-y-2.5 transition-colors">

            <div class="flex items-start justify-between gap-2">
                <a href="{{ route('office.shipments.sent.show', $shipment) }}"
                    class="flex items-center gap-2.5 min-w-0 flex-1">
                    <span
                        class="w-9 h-9 rounded-xl bg-primary text-secondary flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-xl">receipt_long</span>
                    </span>
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="font-mono font-black text-sm text-primary">كشف
                                #{{ $shipment->shipment_number }}</span>
                            <span
                                class="px-1.5 py-0.5 rounded-md bg-slate-100 text-slate-700 text-[10px] font-bold">
                                {{ $shipment->parcels_count }} طرد
                            </span>
                        </div>
                        <p class="text-[10px] text-slate-400 font-semibold mt-0.5">{{ $createdAgo }}</p>
                    </div>
                </a>

                {{-- شارة الحالة + قائمة الثلاث نقاط (⋮) --}}
                <div class="flex items-center gap-1.5 shrink-0">
                    <span
                        class="px-2 py-0.5 rounded-md border text-[10px] font-extrabold {{ $shipment->status->badgeClass() }}">
                        {{ $shipment->status->label() }}
                    </span>

                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button"
                            @click="open = !open"
                            title="خيارات الكشف"
                            aria-label="خيارات الكشف"
                            class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center transition-colors">
                            <span class="material-symbols-outlined text-[18px]">more_vert</span>
                        </button>

                        <div x-show="open"
                            x-cloak
                            x-transition:enter="ease-out duration-150"
                            x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                            x-transition:leave="ease-in duration-100"
                            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                            x-transition:leave-end="opacity-0 -translate-y-1 scale-95"
                            class="absolute left-0 top-full mt-1.5 w-44 bg-white rounded-2xl shadow-lg border border-slate-200/90 py-1.5 z-30 text-right">
                            <a href="{{ route('office.shipments.sent.show', $shipment) }}"
                                class="w-full px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 flex items-center gap-2 transition-colors">
                                <span class="material-symbols-outlined text-base text-primary">description</span>
                                <span>تفاصيل الكشف</span>
                            </a>

                            <a href="{{ route('office.shipments.sent.print', $shipment) }}"
                                target="_blank"
                                class="w-full px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 flex items-center gap-2 transition-colors">
                                <span class="material-symbols-outlined text-base text-slate-500">print</span>
                                <span>طباعة الكشف</span>
                            </a>

                            @if ($canCancelShipment)
                                <div class="my-1 border-t border-slate-100"></div>
                                <button type="button"
                                    data-shipment-action="cancel"
                                    @click="open = false; openCancelModal(@js($cancelCardPayload))"
                                    class="w-full px-3.5 py-2 text-xs font-extrabold text-rose-600 hover:bg-rose-50 flex items-center gap-2 transition-colors">
                                    <span class="material-symbols-outlined text-base text-rose-600">cancel</span>
                                    <span>إلغاء الكشف</span>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <a href="{{ route('office.shipments.sent.show', $shipment) }}" class="block space-y-2.5">
                {{-- السائق والوجهات --}}
                <div class="bg-slate-50 rounded-xl p-2.5 border border-slate-100 space-y-1.5 text-xs">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-slate-400 text-[11px] font-bold flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm text-primary">person</span>
                            السائق:
                        </span>
                        <span class="font-extrabold text-slate-800 truncate">
                            {{ $shipment->driver_name }}
                            <span class="font-mono text-[11px] text-slate-500 mr-1"
                                dir="ltr">({{ $shipment->driver_phone }})</span>
                        </span>
                    </div>

                    @if (count($destList) > 0)
                        <div class="flex items-start justify-between gap-2 pt-1 border-t border-slate-200/60">
                            <span class="text-slate-400 text-[11px] font-bold shrink-0">الوجهات:</span>
                            <div class="flex flex-wrap justify-end gap-1">
                                @foreach (array_slice($destList, 0, 3) as $dest)
                                    <span
                                        class="px-1.5 py-0.5 rounded-md bg-white border border-slate-200 text-[10px] font-bold text-slate-700">{{ $dest }}</span>
                                @endforeach
                                @if (count($destList) > 3)
                                    <span
                                        class="px-1.5 py-0.5 rounded-md bg-secondary/15 text-amber-800 text-[10px] font-extrabold">+{{ count($destList) - 3 }}</span>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

                {{-- المجاميع المالية (آجل / محاسب / إجمالي) --}}
                <div class="grid grid-cols-3 gap-1.5 text-center">
                    <div class="bg-amber-50/70 border border-amber-200/70 rounded-xl py-1.5 px-1">
                        <p class="text-[9px] font-bold text-amber-700">الآجل</p>
                        <p class="text-xs font-mono font-black text-amber-800">
                            {{ number_format((float) $shipment->total_deferred_amount) }}</p>
                    </div>
                    <div class="bg-emerald-50/70 border border-emerald-200/70 rounded-xl py-1.5 px-1">
                        <p class="text-[9px] font-bold text-emerald-700">المحاسب</p>
                        <p class="text-xs font-mono font-black text-emerald-800">
                            {{ number_format((float) $shipment->total_paid_amount) }}</p>
                    </div>
                    <div class="bg-primary text-white rounded-xl py-1.5 px-1">
                        <p class="text-[9px] font-bold text-slate-300">الإجمالي</p>
                        <p class="text-xs font-mono font-black text-secondary">
                            {{ number_format((float) $shipment->total_amount) }}</p>
                    </div>
                </div>

                {{-- نسبة الاستلام --}}
                <div class="flex items-center justify-between text-[11px] pt-0.5 text-slate-500 font-bold">
                    <span>تم استلام {{ $shipment->received_parcels_count }} من {{ $shipment->parcels_count }}</span>
                    <span class="text-primary font-extrabold inline-flex items-center gap-0.5">
                        <span>تفاصيل الكشف</span>
                        <span class="material-symbols-outlined text-sm text-secondary">chevron_left</span>
                    </span>
                </div>
            </a>
        </article>
    @empty
        <div class="bg-white rounded-2xl border border-slate-200/80 p-10 text-center space-y-2.5">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center">
                <span class="material-symbols-outlined text-3xl">local_shipping</span>
            </div>
            <p class="text-xs font-extrabold text-slate-700">لا توجد شحنات مرسلة مطابقة</p>
            <p class="text-[11px] text-slate-400">اضغط على زر (+) أعلى الشاشة لإنشاء كشف شحنة جديد بمسح الباركود.</p>
            <div class="pt-1">
                <button type="button"
                    @click="openCreateModal()"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-secondary hover:bg-amber-500 text-primary text-xs font-extrabold shadow-xs active:scale-95 transition-all">
                    <span class="material-symbols-outlined text-base">qr_code_scanner</span>
                    <span>كشف شحنة جديد</span>
                </button>
            </div>
        </div>
    @endforelse
</div>

@if ($shipments->hasPages())
    <div class="pt-2">{{ $shipments->links() }}</div>
@endif
