@php
    $paymentEnum = $parcel->payment_type instanceof \App\Enums\OutgoingPaymentType
        ? $parcel->payment_type
        : (\App\Enums\OutgoingPaymentType::tryFrom((string) $parcel->payment_type) ?? \App\Enums\OutgoingPaymentType::PAID);

    $officeUser = auth('office')->user();
    $hasSentShipments = $officeUser && $officeUser->hasFeature('sent_shipments');
    $createdAgo = $parcel->created_at?->copy()->locale('ar')->diffForHumans();
    $shipment = $parcel->shipment;
    $isAttachedToShipment = $parcel->shipment_id !== null;

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

    // يُسمح بتعديل بيانات الطرد فقط قبل استلامه ونسخ بياناته لجدول الطرود المستلمة (في الانتظار أو في الطريق فقط)
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
@endphp

<article id="outgoing-card-{{ $parcel->id }}"
         data-outgoing-parcel-id="{{ $parcel->id }}"
         x-data="{ menuOpen: false }"
         :class="menuOpen ? 'relative z-30' : 'relative'"
         class="bg-white border {{ $parcel->status->cardBorderClass() }} rounded-2xl p-4 shadow-sm transition-colors">

    {{-- الرأس: عمودان نظيفان (يمين: أيقونة + عنوان + شارات أفقية | يسار: شارة الحالة + قائمة الثلاث نقاط ⋮ لجميع الإجراءات) --}}
    <div class="flex items-start justify-between gap-2.5">
        {{-- الجانب الأيمن (بداية RTL) --}}
        <div class="flex items-start gap-2.5 min-w-0 flex-1">
            <span class="w-10 h-10 rounded-xl bg-slate-100 text-primary shrink-0 flex items-center justify-center">
                <span class="material-symbols-outlined text-xl">outbox</span>
            </span>

            <div class="min-w-0 flex-1">
                <h3 class="text-base font-bold text-slate-900 truncate leading-snug">
                    {{ $parcel->package_type }}
                </h3>

                <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                    @if($parcel->receipt_number)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 border border-slate-200/80 text-[11px] font-mono font-bold whitespace-nowrap">
                            سند #{{ $parcel->receipt_number }}
                        </span>
                    @endif

                    @if($parcel->isExternal())
                        <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-200/80 text-[10px] font-bold whitespace-nowrap">
                            <span class="material-symbols-outlined text-[12px]">storefront</span>
                            <span>خارجي: {{ $parcel->destinationLabel() }}</span>
                        </span>
                    @else
                        <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-md bg-sky-50 text-sky-800 border border-sky-200/80 text-[10px] font-bold whitespace-nowrap">
                            <span class="material-symbols-outlined text-[12px]">domain</span>
                            <span>فرع: {{ $parcel->destinationLabel() }}</span>
                        </span>
                    @endif

                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md border text-[10px] font-extrabold whitespace-nowrap {{ $paymentEnum->badgeClass() }}">
                        <span>{{ $paymentEnum->label() }}:</span>
                        <span class="font-mono">{{ number_format((float) ($parcel->cod_amount ?? 0)) }} ر.ي</span>
                    </span>

                    @if($isAttachedToShipment && $shipment)
                        <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200/80 text-[10px] font-mono font-bold whitespace-nowrap">
                            <span class="material-symbols-outlined text-[12px]">local_shipping</span>
                            <span>شحنة #{{ $shipment->shipment_number }}</span>
                        </span>
                    @endif
                </div>
            </div>
        </div>

        {{-- الجانب الأيسر (نهاية RTL): شارة الحالة + زر الثلاث نقاط (⋮) الحصري لجميع الإجراءات + الوقت --}}
        <div class="shrink-0 flex flex-col items-end gap-1.5">
            <div class="flex items-center gap-1.5">
                <span class="px-2 py-0.5 rounded-md border text-[10px] font-bold whitespace-nowrap {{ $parcel->status->badgeClass() }}">
                    {{ $parcel->status->label() }}
                </span>

                <div class="relative" @click.outside="menuOpen = false" @keydown.escape.window="menuOpen = false">
                    <button type="button"
                            @click="menuOpen = !menuOpen"
                            title="إجراءات الطرد"
                            aria-label="إجراءات الطرد"
                            :aria-expanded="menuOpen ? 'true' : 'false'"
                            class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center transition-colors shrink-0">
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
                         class="absolute left-0 top-full mt-1.5 w-48 bg-white rounded-2xl shadow-xl border border-slate-200/90 py-1.5 z-40 text-right">

                        {{-- 1. طباعة السند --}}
                        <button type="button"
                                data-card-action="print-receipt"
                                data-receipt-url="{{ route('office.outgoing-parcels.receipt', $parcel) }}"
                                @click="menuOpen = false"
                                class="w-full px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 flex items-center gap-2 transition-colors">
                            <span class="material-symbols-outlined text-base text-primary">print</span>
                            <span>طباعة السند</span>
                        </button>

                        {{-- 2. تعديل البيانات (متاح فقط في الانتظار أو في الطريق وقبل استلام الفرع) --}}
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

                        {{-- 3. إجراءات حالة في الانتظار --}}
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

                        {{-- 4. إجراءات حالة في الطريق --}}
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
                    </div>
                </div>
            </div>

            @if($createdAgo)
                <span class="inline-flex items-center gap-0.5 text-[10px] font-bold px-1.5 py-0.5 rounded-md bg-blue-50 text-blue-700 border border-blue-200/70 whitespace-nowrap"
                      title="{{ $parcel->created_at?->format('Y-m-d H:i') }}">
                    <span class="material-symbols-outlined text-[12px]">hourglass_top</span>
                    <span>{{ $createdAgo }}</span>
                </span>
            @endif
        </div>
    </div>

    {{-- صندوق بيانات المرسل والمستلم --}}
    <div class="bg-slate-50 rounded-2xl p-3 mt-3 border border-slate-100">
        <div class="grid grid-cols-2 divide-x divide-x-reverse divide-slate-200 text-xs">
            <div class="min-w-0 pl-2.5">
                <p class="text-[10px] text-slate-400 font-bold mb-0.5">المرسل</p>
                <p class="font-bold text-slate-800 truncate">{{ $parcel->sender_name }}</p>
                <p class="text-[11px] text-slate-500 font-mono dir-ltr text-right mt-0.5">{{ $parcel->sender_phone }}</p>
            </div>
            <div class="min-w-0 pr-2.5">
                <p class="text-[10px] text-slate-400 font-bold mb-0.5">المستلم</p>
                <p class="font-bold text-slate-800 truncate">{{ $parcel->recipient_name ?: 'بدون اسم' }}</p>
                <p class="text-[11px] text-slate-500 font-mono dir-ltr text-right mt-0.5">{{ $parcel->recipient_phone }}</p>
            </div>
        </div>

        @if($parcel->notes)
            <p class="mt-2.5 pt-2 border-t border-slate-200/70 text-[11px] text-slate-600 leading-relaxed">
                <span class="font-extrabold text-amber-800">ملاحظة:</span> {{ $parcel->notes }}
            </p>
        @endif
    </div>

    {{-- شريط معلوماتي سفلي للقراءة فقط (جميع الأزرار انتقلت إلى قائمة الثلاث نقاط ⋮) --}}
    @if ($parcel->status === \App\Enums\OutgoingParcelStatus::PENDING && $isAttachedToShipment)
        <div class="mt-3 py-1.5 px-3 rounded-xl bg-indigo-50/70 border border-indigo-200/80 text-indigo-700 text-[11px] font-bold flex items-center justify-center gap-1.5 truncate">
            <span class="material-symbols-outlined text-sm shrink-0">inventory_2</span>
            <span class="truncate">مُضاف للشحنة #{{ $shipment?->shipment_number ?? '...' }} (بانتظار الإرسال)</span>
        </div>

    @elseif ($parcel->status === \App\Enums\OutgoingParcelStatus::IN_TRANSIT && ! $parcel->isExternal())
        <div class="mt-3 py-1.5 px-3 rounded-xl bg-sky-50/70 border border-sky-200/80 text-sky-800 text-[11px] font-bold flex items-center justify-center gap-1">
            <span class="material-symbols-outlined text-sm">hourglass_top</span>
            <span>بانتظار استلام الفرع</span>
        </div>

    @elseif ($parcel->status === \App\Enums\OutgoingParcelStatus::RECEIVED)
        @php
            $linked = $parcel->linkedIncomingParcel;
            $linkedStatusValue = $linked?->status instanceof \App\Enums\ParcelStatus
                ? $linked->status->value
                : (string) ($linked?->status ?? '');
            $liveBadge = match ($linkedStatusValue) {
                'delivered' => ['label' => 'تم التسليم للعميل', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
                'returned'  => ['label' => 'مُرتجع', 'class' => 'bg-rose-50 text-rose-700 border-rose-200'],
                default     => ['label' => 'بالمكتب المستلم', 'class' => 'bg-indigo-50 text-indigo-700 border-indigo-200'],
            };
        @endphp
        <div class="mt-3 flex items-center justify-between text-[11px] bg-emerald-50/60 border border-emerald-200/70 rounded-xl px-3 py-2">
            <span class="font-bold text-slate-600">حالة الطرد لدى الفرع المستلم:</span>
            <span class="px-2 py-0.5 rounded-md border font-extrabold whitespace-nowrap {{ $liveBadge['class'] }}">
                {{ $liveBadge['label'] }}
            </span>
        </div>

    @elseif ($parcel->status === \App\Enums\OutgoingParcelStatus::DELIVERED)
        <p class="mt-3 flex items-center justify-center gap-1 text-[11px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200/70 rounded-xl px-3 py-1.5">
            <span class="material-symbols-outlined text-sm">event_available</span>
            <span>تم التسليم للمكتب الخارجي</span>
        </p>

    @elseif ($parcel->status === \App\Enums\OutgoingParcelStatus::CANCELLED)
        <p class="mt-2.5 text-[11px] font-medium text-rose-500 text-center">
            تم إلغاء الطرد الصادر
        </p>
    @endif
</article>
