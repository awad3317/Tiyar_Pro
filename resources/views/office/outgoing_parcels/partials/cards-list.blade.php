@php
    $officeUser = auth('office')->user();
    $hasSentShipments = $officeUser && $officeUser->hasFeature('sent_shipments');
@endphp

{{-- 1. عرض البطاقات (دائماً على الجوال < 1024px، وعلى الكمبيوتر عند اختيار وضع البطاقات) --}}
<div :class="desktopView === 'table' ? 'lg:hidden' : 'lg:space-y-0 lg:grid lg:grid-cols-2 2xl:grid-cols-3 lg:gap-4'"
     class="mursal-desktop-cards space-y-2.5">
    @forelse ($parcels as $parcel)
        @include('office.outgoing_parcels.partials.parcel-card', ['parcel' => $parcel])
    @empty
        <div class="lg:col-span-full bg-white lg:dark:bg-[#24303F] rounded-2xl border border-slate-200/80 lg:border-slate-200 lg:dark:border-slate-700/60 p-8 text-center text-slate-400">
            <span class="material-symbols-outlined text-4xl mb-1 text-slate-300 lg:dark:text-slate-600">outbox</span>
            <p class="text-xs font-bold text-slate-500 lg:dark:text-slate-300">لا توجد طرود صادرة مطابقة</p>
            <p class="text-[11px] text-slate-400 mt-0.5">اضغط على (+ إضافة طرد صادر) لتسجيل إرسالية جديدة</p>
        </div>
    @endforelse
</div>

{{-- 2. وضع الجدول الاحترافي لسطح المكتب (lg: >= 1024px فقط عند اختيار وضع الجدول) --}}
<div x-show="desktopView === 'table'"
     x-cloak
     class="mursal-desktop-table hidden lg:block">
    @if ($parcels->isEmpty())
        <div class="bg-white dark:bg-[#24303F] rounded-2xl border border-slate-200/90 dark:border-slate-700/60 p-10 text-center text-slate-400 shadow-2xs">
            <span class="material-symbols-outlined text-4xl mb-1 text-slate-300 dark:text-slate-600">outbox</span>
            <p class="text-xs font-bold text-slate-500 dark:text-slate-300">لا توجد طرود صادرة مطابقة</p>
            <p class="text-[11px] text-slate-400 mt-0.5">اضغط على (+ إضافة طرد صادر) أعلى الشاشة لتسجيل إرسالية جديدة</p>
        </div>
    @else
        <div class="bg-white dark:bg-[#24303F] rounded-2xl border border-slate-200/90 dark:border-slate-700/60 shadow-2xs overflow-visible">
            <table class="w-full text-right border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-700/60 bg-slate-100/90 dark:bg-slate-800/60 text-[11px] font-extrabold text-slate-700 dark:text-slate-300">
                        <th class="py-3 pr-4 pl-3 rounded-tr-2xl">رقم السند والنوع</th>
                        <th class="py-3 px-3">المرسل</th>
                        <th class="py-3 px-3">المستلم</th>
                        <th class="py-3 px-3">الفرع / الشحنة</th>
                        <th class="py-3 px-3">المبلغ والحالة المالية</th>
                        <th class="py-3 px-3">حالة الطرد</th>
                        <th class="py-3 pr-3 pl-4 text-left rounded-tl-2xl">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-700/50 text-xs">
                    @foreach ($parcels as $parcel)
                        @php
                            $paymentEnum = $parcel->payment_type instanceof \App\Enums\OutgoingPaymentType
                                ? $parcel->payment_type
                                : (\App\Enums\OutgoingPaymentType::tryFrom((string) $parcel->payment_type) ?? \App\Enums\OutgoingPaymentType::PAID);

                            $createdAgo = $parcel->created_at?->copy()->locale('ar')->diffForHumans();
                            $shipment = $parcel->shipment;
                            $isAttachedToShipment = $parcel->shipment_id !== null;
                            $receiptUrl = route('office.outgoing-parcels.receipt', $parcel);

                            $rowAccentDot = match ($parcel->status) {
                                \App\Enums\OutgoingParcelStatus::PENDING    => 'bg-amber-400',
                                \App\Enums\OutgoingParcelStatus::IN_TRANSIT => 'bg-sky-500',
                                \App\Enums\OutgoingParcelStatus::RECEIVED,
                                \App\Enums\OutgoingParcelStatus::DELIVERED  => 'bg-emerald-500',
                                \App\Enums\OutgoingParcelStatus::CANCELLED  => 'bg-rose-400',
                            };

                            $modalPayload = [
                                'id'              => $parcel->id,
                                'receipt_number'  => $parcel->receipt_number,
                                'package_type'    => $parcel->package_type,
                                'destination'     => $parcel->destinationLabel(),
                                'is_external'     => $parcel->isExternal(),
                                'payment_label'   => $paymentEnum->label(),
                                'cod_amount'      => number_format((float) ($parcel->cod_amount ?? 0)),
                                'recipient_name'  => $parcel->recipient_name,
                                'recipient_phone' => $parcel->recipient_phone,
                                'shipment_number' => $shipment?->shipment_number,
                            ];

                            $cancelPayloadJson = json_encode(
                                array_merge($modalPayload, ['url' => route('office.outgoing-parcels.cancel', $parcel)]),
                                JSON_UNESCAPED_UNICODE
                            );
                            $deliverPayloadJson = json_encode(
                                array_merge($modalPayload, ['url' => route('office.outgoing-parcels.deliver-external', $parcel)]),
                                JSON_UNESCAPED_UNICODE
                            );
                            $detachPayloadJson = json_encode(
                                array_merge($modalPayload, ['url' => route('office.outgoing-parcels.detach-shipment', $parcel)]),
                                JSON_UNESCAPED_UNICODE
                            );

                            $canEditParcel = $parcel->linked_incoming_parcel_id === null
                                && in_array(
                                    $parcel->status,
                                    [\App\Enums\OutgoingParcelStatus::PENDING, \App\Enums\OutgoingParcelStatus::IN_TRANSIT],
                                    true
                                );

                            $editPayloadJson = $canEditParcel
                                ? json_encode([
                                    'id'                    => $parcel->id,
                                    'url'                   => route('office.outgoing-parcels.update', $parcel),
                                    'receipt_number'        => (string) ($parcel->receipt_number ?? ''),
                                    'destination_type'      => $parcel->isExternal() ? 'external' : 'office',
                                    'destination_office_id' => $parcel->destination_office_id ? (string) $parcel->destination_office_id : '',
                                    'external_office_name'  => (string) ($parcel->external_office_name ?? ''),
                                    'destination_label'     => $parcel->destinationLabel(),
                                    'sender_name'           => (string) ($parcel->sender_name ?? ''),
                                    'sender_phone'          => (string) ($parcel->sender_phone ?? ''),
                                    'recipient_name'        => (string) ($parcel->recipient_name ?? ''),
                                    'recipient_phone'       => (string) ($parcel->recipient_phone ?? ''),
                                    'package_type'          => (string) ($parcel->package_type ?? ''),
                                    'cod_amount'            => (float) ($parcel->cod_amount ?? 0),
                                    'payment_type'          => $paymentEnum->value,
                                    'notes'                 => (string) ($parcel->notes ?? ''),
                                ], JSON_UNESCAPED_UNICODE)
                                : '';

                            $hasSecondaryMenu = $canEditParcel
                                || in_array($parcel->status, [\App\Enums\OutgoingParcelStatus::PENDING, \App\Enums\OutgoingParcelStatus::IN_TRANSIT], true);
                        @endphp

                        <tr data-outgoing-table-row="{{ $parcel->id }}"
                            data-parcel-status="{{ $parcel->status->value }}"
                            x-data="{ menuOpen: false }"
                            :class="menuOpen ? 'relative z-30 bg-gray-50/90 dark:bg-slate-800/60' : 'hover:bg-gray-50/70 dark:hover:bg-slate-800/40'"
                            class="transition-colors">

                            {{-- 1. رقم السند والنوع --}}
                            <td class="py-3 pr-4 pl-3 align-middle">
                                <div class="flex items-center gap-2.5">
                                    <span class="w-1.5 h-8 rounded-full shrink-0 {{ $rowAccentDot }}"></span>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-mono font-black text-slate-900 dark:text-slate-100">#{{ $parcel->receipt_number ?: '—' }}</span>
                                            <span class="font-bold text-slate-800 dark:text-slate-200 truncate max-w-[160px]" title="{{ $parcel->package_type }}">{{ $parcel->package_type }}</span>
                                        </div>
                                        @if($createdAgo)
                                            <p class="text-[10px] text-slate-400 mt-0.5" title="{{ $parcel->created_at?->format('Y-m-d H:i') }}">{{ $createdAgo }}</p>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            {{-- 2. المرسل --}}
                            <td class="py-3 px-3 align-middle">
                                <p class="font-bold text-slate-800 dark:text-slate-100 truncate max-w-[140px]">{{ $parcel->sender_name }}</p>
                                <p class="text-[11px] font-mono text-slate-500 dark:text-slate-400 dir-ltr text-right">{{ $parcel->sender_phone }}</p>
                            </td>

                            {{-- 3. المستلم --}}
                            <td class="py-3 px-3 align-middle">
                                <p class="font-bold text-slate-800 dark:text-slate-100 truncate max-w-[140px]">{{ $parcel->recipient_name ?: 'بدون اسم' }}</p>
                                <p class="text-[11px] font-mono text-slate-500 dark:text-slate-400 dir-ltr text-right">{{ $parcel->recipient_phone }}</p>
                            </td>

                            {{-- 4. الفرع / الشحنة --}}
                            <td class="py-3 px-3 align-middle">
                                <p class="font-semibold text-slate-700 dark:text-slate-200 truncate max-w-[170px]">
                                    <span class="text-slate-400 text-[11px]">{{ $parcel->isExternal() ? 'خارجي:' : 'فرع:' }}</span>
                                    {{ $parcel->destinationLabel() }}
                                </p>
                                @if($isAttachedToShipment && $shipment)
                                    <p class="text-[11px] font-mono font-bold text-indigo-600 dark:text-indigo-400 mt-0.5">شحنة #{{ $shipment->shipment_number }}</p>
                                @elseif($parcel->notes)
                                    <p class="text-[10px] text-slate-400 truncate max-w-[170px] mt-0.5" title="{{ $parcel->notes }}">{{ $parcel->notes }}</p>
                                @endif
                            </td>

                            {{-- 5. المبلغ والحالة المالية --}}
                            <td class="py-3 px-3 align-middle whitespace-nowrap">
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md border text-[11px] font-extrabold {{ $paymentEnum->badgeClass() }}">
                                    <span>{{ $paymentEnum->label() }}:</span>
                                    <span class="font-mono">{{ number_format((float) ($parcel->cod_amount ?? 0)) }} ر.ي</span>
                                </span>
                            </td>

                            {{-- 6. حالة الطرد --}}
                            <td class="py-3 px-3 align-middle whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-md border text-[11px] font-bold {{ $parcel->status->badgeClass() }}">
                                    {{ $parcel->status->label() }}
                                </span>
                            </td>

                            {{-- 7. الإجراءات السريعة: طباعة السند بضغطة واحدة + قائمة ⋮ الثانوية --}}
                            <td class="py-3 pr-3 pl-4 align-middle whitespace-nowrap text-left">
                                <div class="inline-flex items-center justify-end gap-1.5">
                                    <button type="button"
                                            data-card-action="print-receipt"
                                            data-receipt-url="{{ $receiptUrl }}"
                                            class="group h-8 px-3 rounded-lg bg-slate-100 hover:bg-teal-50 text-slate-700 hover:text-teal-700 border border-slate-200 hover:border-teal-300 dark:bg-[#1A222C] dark:hover:bg-teal-500/15 dark:text-slate-200 dark:hover:text-teal-300 dark:border-slate-700 dark:hover:border-teal-500/40 text-xs font-extrabold inline-flex items-center gap-1.5 transition-all">
                                        <span class="material-symbols-outlined text-[16px] text-slate-500 group-hover:text-teal-600 dark:text-slate-400 dark:group-hover:text-teal-300 transition-colors">print</span>
                                        <span>طباعة السند</span>
                                    </button>

                                    @if ($hasSecondaryMenu)
                                        <div class="relative" @click.outside="menuOpen = false" @keydown.escape.window="menuOpen = false">
                                            <button type="button"
                                                    @click="menuOpen = !menuOpen"
                                                    title="إجراءات إضافية"
                                                    aria-label="إجراءات إضافية"
                                                    class="w-8 h-8 rounded-lg bg-white hover:bg-gray-100 text-slate-600 border border-gray-200 dark:bg-[#1A222C] dark:hover:bg-[#333A48] dark:text-slate-300 dark:border-slate-700 flex items-center justify-center transition-colors">
                                                <span class="material-symbols-outlined text-[18px]">more_vert</span>
                                            </button>

                                            <div x-show="menuOpen"
                                                 x-cloak
                                                 x-transition:enter="ease-out duration-150"
                                                 x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                                                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                                 x-transition:leave="ease-in duration-100"
                                                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                                 x-transition:leave-end="opacity-0 -translate-y-1 scale-95"
                                                 class="absolute left-0 top-full mt-1.5 w-48 bg-white dark:bg-[#24303F] rounded-2xl shadow-xl border border-gray-200 dark:border-slate-700/60 py-1.5 z-40 text-right">
                                                @include('office.outgoing_parcels.partials.parcel-actions-menu', [
                                                    'parcel'               => $parcel,
                                                    'canEditParcel'        => $canEditParcel,
                                                    'editPayloadJson'      => $editPayloadJson,
                                                    'isAttachedToShipment' => $isAttachedToShipment,
                                                    'hasSentShipments'     => $hasSentShipments,
                                                    'shipment'             => $shipment,
                                                    'cancelPayloadJson'    => $cancelPayloadJson,
                                                    'detachPayloadJson'    => $detachPayloadJson,
                                                    'deliverPayloadJson'   => $deliverPayloadJson,
                                                    'includePrintInMenu'   => false,
                                                ])
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

@if ($parcels->hasPages())
    <div class="pt-3">
        {{ $parcels->links() }}
    </div>
@endif
