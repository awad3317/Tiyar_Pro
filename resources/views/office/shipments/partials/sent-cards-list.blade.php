<div :class="typeof desktopView !== 'undefined' && desktopView === 'table' ? 'lg:hidden' : 'lg:space-y-0 lg:grid lg:grid-cols-2 2xl:grid-cols-3 lg:gap-4'"
     class="mursal-desktop-cards space-y-2.5">
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
            style="content-visibility: auto; contain-intrinsic-size: 180px;"
            class="relative bg-white lg:dark:bg-[#24303F] border border-slate-200/90 lg:border-gray-200 lg:dark:border-slate-700/60 rounded-2xl p-3.5 shadow-xs space-y-2.5">

            <div class="flex items-start justify-between gap-2">
                <div class="flex items-center gap-2.5 min-w-0 flex-1">
                    <span
                        class="w-9 h-9 rounded-xl bg-primary lg:dark:bg-[#1A222C] text-secondary flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-xl">receipt_long</span>
                    </span>
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="font-mono font-black text-sm text-primary lg:dark:text-slate-100">كشف
                                #{{ $shipment->shipment_number }}</span>
                            <span
                                class="px-1.5 py-0.5 rounded-md bg-slate-100 text-slate-700 lg:dark:bg-slate-800 lg:dark:text-slate-200 text-[10px] font-bold">
                                {{ $shipment->parcels_count }} طرد
                            </span>
                        </div>
                        <p class="text-[10px] text-slate-400 font-semibold mt-0.5">{{ $createdAgo }}</p>
                    </div>
                </div>

                {{-- شارة الحالة + قائمة الثلاث نقاط (⋮) الحصرية لجميع إجراءات الكشف --}}
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
                            class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 lg:dark:bg-slate-800 lg:dark:hover:bg-slate-700 lg:dark:text-slate-300 flex items-center justify-center transition-colors">
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
                            class="absolute left-0 top-full mt-1.5 w-44 bg-white lg:dark:bg-[#24303F] rounded-2xl shadow-lg border border-slate-200/90 lg:dark:border-slate-700/60 py-1.5 z-30 text-right">
                            <a href="{{ route('office.shipments.sent.show', $shipment) }}"
                                class="w-full px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 lg:dark:text-slate-200 lg:dark:hover:bg-slate-700/50 flex items-center gap-2 transition-colors">
                                <span class="material-symbols-outlined text-base text-primary lg:dark:text-amber-400">description</span>
                                <span>تفاصيل الكشف</span>
                            </a>

                            <button type="button"
                                data-shipment-action="print"
                                @click="open = false; printShipment('{{ route('office.shipments.sent.print', $shipment) }}')"
                                class="w-full px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 lg:dark:text-slate-200 lg:dark:hover:bg-slate-700/50 flex items-center gap-2 transition-colors">
                                <span class="material-symbols-outlined text-base text-slate-500 lg:dark:text-slate-400">print</span>
                                <span>طباعة الكشف</span>
                            </button>

                            @if ($canCancelShipment)
                                <div class="my-1 border-t border-slate-100 lg:dark:border-slate-700/60"></div>
                                <button type="button"
                                    data-shipment-action="cancel"
                                    @click="open = false; openCancelModal(@js($cancelCardPayload))"
                                    class="w-full px-3.5 py-2 text-xs font-extrabold text-rose-600 hover:bg-rose-50 lg:dark:text-rose-400 lg:dark:hover:bg-rose-500/15 flex items-center gap-2 transition-colors">
                                    <span class="material-symbols-outlined text-base text-rose-600 lg:dark:text-rose-400">cancel</span>
                                    <span>إلغاء الكشف</span>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-2.5">
                {{-- السائق والوجهات --}}
                <div class="bg-slate-50 lg:dark:bg-[#1A222C] rounded-xl p-2.5 border border-slate-100 lg:dark:border-slate-700/50 space-y-1.5 text-xs">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-slate-400 text-[11px] font-bold flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm text-primary lg:dark:text-amber-400">person</span>
                            السائق:
                        </span>
                        <span class="font-extrabold text-slate-800 lg:dark:text-slate-100 truncate">
                            {{ $shipment->driver_name }}
                            <span class="font-mono text-[11px] text-slate-500 lg:dark:text-slate-400 mr-1"
                                dir="ltr">({{ $shipment->driver_phone }})</span>
                        </span>
                    </div>

                    @if (count($destList) > 0)
                        <div class="flex items-start justify-between gap-2 pt-1 border-t border-slate-200/60 lg:dark:border-slate-700/60">
                            <span class="text-slate-400 text-[11px] font-bold shrink-0">الوجهات:</span>
                            <div class="flex flex-wrap justify-end gap-1">
                                @foreach (array_slice($destList, 0, 3) as $dest)
                                    <span
                                        class="px-1.5 py-0.5 rounded-md bg-white border border-slate-200 lg:dark:bg-slate-800 lg:dark:border-slate-700 text-[10px] font-bold text-slate-700 lg:dark:text-slate-200">{{ $dest }}</span>
                                @endforeach
                                @if (count($destList) > 3)
                                    <span
                                        class="px-1.5 py-0.5 rounded-md bg-secondary/15 text-amber-800 lg:dark:bg-amber-500/20 lg:dark:text-amber-300 text-[10px] font-extrabold">+{{ count($destList) - 3 }}</span>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

                {{-- المجاميع المالية (آجل / محاسب / إجمالي) --}}
                <div class="grid grid-cols-3 gap-1.5 text-center">
                    <div class="bg-amber-50/70 border border-amber-200/70 lg:dark:bg-amber-500/10 lg:dark:border-amber-500/25 rounded-xl py-1.5 px-1">
                        <p class="text-[9px] font-bold text-amber-700 lg:dark:text-amber-400">الآجل</p>
                        <p class="text-xs font-mono font-black text-amber-800 lg:dark:text-amber-300">
                            {{ number_format((float) $shipment->total_deferred_amount) }}</p>
                    </div>
                    <div class="bg-emerald-50/70 border border-emerald-200/70 lg:dark:bg-emerald-500/10 lg:dark:border-emerald-500/25 rounded-xl py-1.5 px-1">
                        <p class="text-[9px] font-bold text-emerald-700 lg:dark:text-emerald-400">المحاسب</p>
                        <p class="text-xs font-mono font-black text-emerald-800 lg:dark:text-emerald-300">
                            {{ number_format((float) $shipment->total_paid_amount) }}</p>
                    </div>
                    <div class="bg-primary lg:dark:bg-[#1A222C] lg:dark:border lg:dark:border-slate-700/60 text-white rounded-xl py-1.5 px-1">
                        <p class="text-[9px] font-bold text-slate-300 lg:dark:text-slate-400">الإجمالي</p>
                        <p class="text-xs font-mono font-black text-secondary lg:dark:text-amber-400">
                            {{ number_format((float) $shipment->total_amount) }}</p>
                    </div>
                </div>

                {{-- مؤشر نسبة الاستلام (عرض فقط بدون رابط قابل للنقر) --}}
                <div class="flex items-center justify-between text-[11px] pt-0.5 text-slate-500 lg:dark:text-slate-400 font-bold">
                    <span>تم استلام {{ $shipment->received_parcels_count }} من {{ $shipment->parcels_count }}</span>
                </div>
            </div>
        </article>
    @empty
        <div class="lg:col-span-full bg-white lg:dark:bg-[#24303F] rounded-2xl border border-slate-200/80 lg:border-gray-200 lg:dark:border-slate-700/60 p-10 text-center space-y-2.5">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-slate-100 lg:dark:bg-slate-800 text-slate-400 flex items-center justify-center">
                <span class="material-symbols-outlined text-3xl">local_shipping</span>
            </div>
            <p class="text-xs font-extrabold text-slate-700 lg:dark:text-slate-200">لا توجد شحنات مرسلة مطابقة</p>
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

{{-- 2. عرض الجدول الأولي من السيرفر قبل تهيئة Alpine / IndexedDB (يمنع وميض البطاقات ← الجدول) --}}
<div class="mursal-desktop-table hidden lg:block">
    @if ($shipments->isEmpty())
        <div class="bg-white dark:bg-[#24303F] rounded-2xl border border-slate-200/90 dark:border-slate-700/60 p-10 text-center space-y-2.5 shadow-2xs">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center">
                <span class="material-symbols-outlined text-3xl">local_shipping</span>
            </div>
            <p class="text-xs font-extrabold text-slate-700 dark:text-slate-200">لا توجد شحنات مرسلة مطابقة</p>
            <p class="text-[11px] text-slate-400">اضغط على (+ كشف شحنة جديد) أعلى الشاشة لإنشاء كشف بمسح الباركود.</p>
        </div>
    @else
        <div class="bg-white dark:bg-[#24303F] rounded-2xl border border-slate-200/90 dark:border-slate-700/60 shadow-2xs overflow-visible">
            <table class="w-full text-right border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-700/60 bg-slate-100/90 dark:bg-slate-800/60 text-[11px] font-extrabold text-slate-700 dark:text-slate-300">
                        <th class="py-3 pr-4 pl-3 rounded-tr-2xl">رقم الكشف والوقت</th>
                        <th class="py-3 px-3">السائق</th>
                        <th class="py-3 px-3">الوجهات</th>
                        <th class="py-3 px-3">المجاميع المالية (ر.ي)</th>
                        <th class="py-3 px-3">الحالة والاستلام</th>
                        <th class="py-3 pr-3 pl-4 text-left rounded-tl-2xl">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-700/50 text-xs">
                    @foreach ($shipments as $shipment)
                        @php
                            $destList = is_array($shipment->destinations_summary) ? $shipment->destinations_summary : [];
                            $createdAgo = $shipment->created_at?->copy()->locale('ar')->diffForHumans();
                            $statusVal = $shipment->status instanceof \App\Enums\ShipmentStatus
                                ? $shipment->status->value
                                : (string) $shipment->status;
                            $rowDotClass = match ($statusVal) {
                                'draft' => 'bg-slate-400',
                                'in_transit' => 'bg-sky-500',
                                'partially_received' => 'bg-amber-400',
                                'received' => 'bg-emerald-500',
                                'cancelled' => 'bg-rose-400',
                                default => 'bg-slate-300',
                            };
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
                        <tr x-data="{ open: false }"
                            :class="open ? 'relative z-30 bg-slate-50/90 dark:bg-slate-800/60' : 'hover:bg-slate-50/70 dark:hover:bg-slate-800/40'"
                            class="transition-colors">
                            <td class="py-3 pr-4 pl-3 align-middle">
                                <div class="flex items-center gap-2.5">
                                    <span class="w-1.5 h-8 rounded-full shrink-0 {{ $rowDotClass }}"></span>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="font-mono font-black text-slate-900 dark:text-slate-100">كشف #{{ $shipment->shipment_number }}</span>
                                            <span class="px-1.5 py-0.5 rounded-md bg-slate-100 text-slate-700 border border-slate-200/80 dark:bg-slate-800 dark:text-slate-200 dark:border-slate-700 text-[10px] font-bold">
                                                {{ $shipment->parcels_count }} طرد
                                            </span>
                                        </div>
                                        <p class="text-[10px] text-slate-400 mt-0.5">{{ $createdAgo }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-3 align-middle">
                                <p class="font-bold text-slate-800 dark:text-slate-100 truncate max-w-[155px]">{{ $shipment->driver_name ?: '—' }}</p>
                                <p class="text-[11px] font-mono text-slate-500 dark:text-slate-400 dir-ltr text-right">{{ $shipment->driver_phone ?: '' }}</p>
                            </td>
                            <td class="py-3 px-3 align-middle">
                                <div class="flex flex-wrap items-center gap-1">
                                    @forelse (array_slice($destList, 0, 3) as $dest)
                                        <span class="px-2 py-0.5 rounded-md bg-slate-100 border border-slate-200/80 dark:bg-slate-800 dark:border-slate-700 text-[11px] font-bold text-slate-700 dark:text-slate-200">{{ $dest }}</span>
                                    @empty
                                        <span class="text-slate-300 dark:text-slate-600 font-bold">—</span>
                                    @endforelse
                                    @if (count($destList) > 3)
                                        <span class="px-1.5 py-0.5 rounded-md bg-secondary/15 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300 text-[10px] font-extrabold">+{{ count($destList) - 3 }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3 px-3 align-middle whitespace-nowrap">
                                <div class="flex items-center gap-1.5 text-[11px] font-mono">
                                    <span class="px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-200/80 dark:bg-amber-500/15 dark:text-amber-300 dark:border-amber-500/30 font-extrabold">
                                        آجل: {{ number_format((float) $shipment->total_deferred_amount) }}
                                    </span>
                                    <span class="px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200/80 dark:bg-emerald-500/15 dark:text-emerald-400 dark:border-emerald-500/30 font-extrabold">
                                        محاسب: {{ number_format((float) $shipment->total_paid_amount) }}
                                    </span>
                                    <span class="px-2 py-0.5 rounded-md bg-slate-900 dark:bg-slate-800 dark:border dark:border-slate-700 text-secondary font-black">
                                        إجمالي: {{ number_format((float) $shipment->total_amount) }}
                                    </span>
                                </div>
                            </td>
                            <td class="py-3 px-3 align-middle whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-md border text-[11px] font-extrabold {{ $shipment->status->badgeClass() }}">
                                    {{ $shipment->status->label() }}
                                </span>
                                <p class="text-[10px] font-bold text-slate-400 mt-0.5">
                                    استُلم {{ $shipment->received_parcels_count }} من {{ $shipment->parcels_count }}
                                </p>
                            </td>
                            <td class="py-3 pr-3 pl-4 align-middle whitespace-nowrap text-left">
                                <div class="inline-flex items-center justify-end gap-1.5">
                                    <button type="button"
                                            @click="printShipment('{{ route('office.shipments.sent.print', $shipment) }}')"
                                            class="group h-8 px-3 rounded-lg bg-slate-100 hover:bg-teal-50 text-slate-700 hover:text-teal-700 border border-slate-200 hover:border-teal-300 dark:bg-[#1A222C] dark:hover:bg-teal-500/15 dark:text-slate-200 dark:hover:text-teal-300 dark:border-slate-700 dark:hover:border-teal-500/40 text-xs font-extrabold inline-flex items-center gap-1.5 transition-all">
                                        <span class="material-symbols-outlined text-[16px] text-slate-500 group-hover:text-teal-600 dark:text-slate-400 dark:group-hover:text-teal-300 transition-colors">print</span>
                                        <span>طباعة الكشف</span>
                                    </button>
                                    <button type="button"
                                            @click="window.location.assign('{{ route('office.shipments.sent.show', $shipment) }}')"
                                            class="h-8 px-2.5 rounded-lg bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 dark:bg-[#1A222C] dark:hover:bg-[#333A48] dark:text-slate-200 dark:border-slate-700 text-xs font-bold inline-flex items-center gap-1 transition-colors">
                                        <span class="material-symbols-outlined text-[16px] text-primary dark:text-amber-400">description</span>
                                        <span>التفاصيل</span>
                                    </button>
                                    @if ($canCancelShipment)
                                        <div class="relative" @click.outside="open = false" @keydown.escape.window="open = false">
                                            <button type="button"
                                                    @click="open = !open"
                                                    title="إجراءات إضافية"
                                                    class="w-8 h-8 rounded-lg bg-white hover:bg-slate-100 text-slate-600 border border-slate-200 dark:bg-[#1A222C] dark:hover:bg-[#333A48] dark:text-slate-300 dark:border-slate-700 flex items-center justify-center transition-colors">
                                                <span class="material-symbols-outlined text-[18px]">more_vert</span>
                                            </button>
                                            <div x-show="open"
                                                 x-cloak
                                                 class="absolute left-0 top-full mt-1.5 w-44 bg-white dark:bg-[#24303F] rounded-2xl shadow-xl border border-slate-200 dark:border-slate-700/60 py-1.5 z-40 text-right">
                                                <button type="button"
                                                        @click="open = false; openCancelModal(@js($cancelCardPayload))"
                                                        class="w-full px-3.5 py-2 text-xs font-extrabold text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/15 flex items-center gap-2 transition-colors">
                                                    <span class="material-symbols-outlined text-base text-rose-600 dark:text-rose-400">cancel</span>
                                                    <span>إلغاء الكشف</span>
                                                </button>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@if ($shipments->hasPages())
    <div class="pt-2">{{ $shipments->links() }}</div>
@endif
