@php
    $paymentEnum = $parcel->payment_type instanceof \App\Enums\OutgoingPaymentType
        ? $parcel->payment_type
        : (\App\Enums\OutgoingPaymentType::tryFrom((string) $parcel->payment_type) ?? \App\Enums\OutgoingPaymentType::PAID);

    $officeUser = auth('office')->user();
    $hasSentShipments = $officeUser && $officeUser->hasFeature('sent_shipments');
    $createdAgo = $parcel->created_at?->copy()->locale('ar')->diffForHumans();
    $shipment = $parcel->shipment;
    $isAttachedToShipment = $parcel->shipment_id !== null;
    $receiptUrl = route('office.outgoing-parcels.receipt', $parcel);

    $desktopRightAccent = match ($parcel->status) {
        \App\Enums\OutgoingParcelStatus::PENDING    => 'lg:border-r-amber-400',
        \App\Enums\OutgoingParcelStatus::IN_TRANSIT => 'lg:border-r-sky-500',
        \App\Enums\OutgoingParcelStatus::RECEIVED,
        \App\Enums\OutgoingParcelStatus::DELIVERED  => 'lg:border-r-emerald-500',
        \App\Enums\OutgoingParcelStatus::CANCELLED  => 'lg:border-r-rose-400',
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

    $hasDesktopSecondaryActions = $canEditParcel
        || in_array($parcel->status, [\App\Enums\OutgoingParcelStatus::PENDING, \App\Enums\OutgoingParcelStatus::IN_TRANSIT], true);
@endphp

<article id="outgoing-card-{{ $parcel->id }}"
         data-outgoing-parcel-id="{{ $parcel->id }}"
         data-parcel-status="{{ $parcel->status->value }}"
         x-data="{ menuOpen: false }"
         :class="menuOpen ? 'relative z-30' : 'relative'"
         class="bg-white lg:dark:bg-[#24303F] border {{ $parcel->status->cardBorderClass() }} lg:border-gray-200 lg:border-t-gray-200 lg:border-b-gray-200 lg:border-l-gray-200 lg:dark:border-slate-700/60 lg:dark:border-t-slate-700/60 lg:dark:border-b-slate-700/60 lg:dark:border-l-slate-700/60 lg:border-r-4 {{ $desktopRightAccent }} rounded-2xl p-4 shadow-sm lg:shadow-2xs lg:hover:shadow-md lg:flex lg:flex-col lg:justify-between lg:h-full transition-all">

    {{-- الجزء العلوي والأوسط من البطاقة --}}
    <div>
        {{-- الرأس: على الجوال مطابق للأصل 100% | على الكمبيوتر شارات هادئة ونظيفة --}}
        <div class="flex items-start justify-between gap-2.5">
            <div class="flex items-start gap-2.5 min-w-0 flex-1">
                <span class="w-10 h-10 lg:w-9 lg:h-9 rounded-xl bg-slate-100 lg:dark:bg-slate-800 text-primary lg:dark:text-amber-400 shrink-0 flex items-center justify-center">
                    <span class="material-symbols-outlined text-xl lg:text-lg">outbox</span>
                </span>

                <div class="min-w-0 flex-1">
                    <h3 class="text-base lg:text-sm font-bold lg:font-extrabold text-slate-900 lg:dark:text-slate-100 truncate leading-snug">
                        {{ $parcel->package_type }}
                    </h3>

                    {{-- شارات الجوال الأصلية (< 1024px فقط) --}}
                    <div class="flex lg:hidden flex-wrap items-center gap-1.5 mt-1.5">
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

                    {{-- السطر التعريفي الهادئ للكمبيوتر (lg: فقط — نص نظيف بدون زحمة ألوان) --}}
                    <div class="hidden lg:flex items-center gap-2 text-[11px] text-slate-500 dark:text-slate-400 font-semibold mt-0.5 truncate">
                        @if($parcel->receipt_number)
                            <span class="font-mono font-bold text-slate-700 dark:text-slate-200">سند #{{ $parcel->receipt_number }}</span>
                            <span class="text-slate-300 dark:text-slate-600">•</span>
                        @endif
                        <span class="truncate">{{ $parcel->isExternal() ? 'خارجي: ' : 'فرع: ' }}{{ $parcel->destinationLabel() }}</span>
                    </div>
                </div>
            </div>

            {{-- الجانب الأيسر: شارة الحالة + زر الثلاث نقاط (⋮) --}}
            <div class="shrink-0 flex flex-col items-end gap-1.5">
                <div class="flex items-center gap-1.5">
                    <span class="px-2 py-0.5 rounded-md border text-[10px] font-bold whitespace-nowrap {{ $parcel->status->badgeClass() }}">
                        {{ $parcel->status->label() }}
                    </span>

                    <div class="relative {{ ! $hasDesktopSecondaryActions ? 'lg:hidden' : '' }}"
                         @click.outside="menuOpen = false"
                         @keydown.escape.window="menuOpen = false">
                        <button type="button"
                                @click="menuOpen = !menuOpen"
                                title="إجراءات الطرد"
                                aria-label="إجراءات الطرد"
                                :aria-expanded="menuOpen ? 'true' : 'false'"
                                class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 lg:dark:bg-slate-800 lg:dark:hover:bg-slate-700 lg:dark:text-slate-300 flex items-center justify-center transition-colors shrink-0">
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
                             class="absolute left-0 top-full mt-1.5 w-48 bg-white lg:dark:bg-[#24303F] rounded-2xl shadow-xl border border-slate-200/90 lg:dark:border-slate-700/60 py-1.5 z-40 text-right">
                            @include('office.outgoing_parcels.partials.parcel-actions-menu', [
                                'parcel'             => $parcel,
                                'canEditParcel'      => $canEditParcel,
                                'editPayloadJson'    => $editPayloadJson,
                                'isAttachedToShipment' => $isAttachedToShipment,
                                'hasSentShipments'   => $hasSentShipments,
                                'shipment'           => $shipment,
                                'cancelPayloadJson'  => $cancelPayloadJson,
                                'detachPayloadJson'  => $detachPayloadJson,
                                'deliverPayloadJson' => $deliverPayloadJson,
                                'includePrintInMenu' => true,
                            ])
                        </div>
                    </div>
                </div>

                {{-- شارة الوقت الملونة للجوال فقط --}}
                @if($createdAgo)
                    <span class="lg:hidden inline-flex items-center gap-0.5 text-[10px] font-bold px-1.5 py-0.5 rounded-md bg-blue-50 text-blue-700 border border-blue-200/70 whitespace-nowrap"
                          title="{{ $parcel->created_at?->format('Y-m-d H:i') }}">
                        <span class="material-symbols-outlined text-[12px]">hourglass_top</span>
                        <span>{{ $createdAgo }}</span>
                    </span>
                @endif
            </div>
        </div>

        {{-- صندوق بيانات المرسل والمستلم --}}
        <div class="bg-slate-50 lg:dark:bg-[#1A222C] rounded-2xl lg:rounded-xl p-3 mt-3 border border-slate-100 lg:dark:border-slate-700/50">
            <div class="grid grid-cols-2 divide-x divide-x-reverse divide-slate-200 lg:dark:divide-slate-700/60 text-xs">
                <div class="min-w-0 pl-2.5">
                    <p class="text-[10px] text-slate-400 font-bold mb-0.5">المرسل</p>
                    <p class="font-bold text-slate-800 lg:dark:text-slate-100 truncate">{{ $parcel->sender_name }}</p>
                    <p class="text-[11px] text-slate-500 lg:dark:text-slate-400 font-mono dir-ltr text-right mt-0.5">{{ $parcel->sender_phone }}</p>
                </div>
                <div class="min-w-0 pr-2.5">
                    <p class="text-[10px] text-slate-400 font-bold mb-0.5">المستلم</p>
                    <p class="font-bold text-slate-800 lg:dark:text-slate-100 truncate">{{ $parcel->recipient_name ?: 'بدون اسم' }}</p>
                    <p class="text-[11px] text-slate-500 lg:dark:text-slate-400 font-mono dir-ltr text-right mt-0.5">{{ $parcel->recipient_phone }}</p>
                </div>
            </div>

            @if($parcel->notes)
                <p class="mt-2.5 pt-2 border-t border-slate-200/70 lg:dark:border-slate-700/60 text-[11px] text-slate-600 lg:dark:text-slate-300 leading-relaxed truncate" title="{{ $parcel->notes }}">
                    <span class="font-extrabold text-amber-800 lg:dark:text-amber-400">ملاحظة:</span> {{ $parcel->notes }}
                </p>
            @endif
        </div>

        {{-- الشريط المعلوماتي السفلي للجوال فقط (< 1024px) --}}
        <div class="lg:hidden">
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
        </div>
    </div>

    {{-- الشريط السفلي الثابت الموحّد للكمبيوتر (lg: فقط): المبلغ البارز + سياق الشحنة/الوقت + زر طباعة السند المباشر بضغطة واحدة --}}
    <div class="hidden lg:flex items-center justify-between gap-2 pt-3 mt-3.5 border-t border-gray-100 dark:border-slate-700/60">
        <div class="flex items-center gap-2 min-w-0">
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md border text-[11px] font-extrabold whitespace-nowrap {{ $paymentEnum->badgeClass() }}">
                <span>{{ $paymentEnum->label() }}:</span>
                <span class="font-mono">{{ number_format((float) ($parcel->cod_amount ?? 0)) }} ر.ي</span>
            </span>

            @if($isAttachedToShipment && $shipment)
                <span class="text-[11px] font-mono font-bold text-slate-500 dark:text-slate-400 truncate" title="ضمن كشف شحنة #{{ $shipment->shipment_number }}">
                    #{{ $shipment->shipment_number }}
                </span>
            @elseif($createdAgo)
                <span class="text-[11px] text-slate-400 font-medium truncate" title="{{ $parcel->created_at?->format('Y-m-d H:i') }}">
                    {{ $createdAgo }}
                </span>
            @endif
        </div>

        <button type="button"
                data-card-action="print-receipt"
                data-receipt-url="{{ $receiptUrl }}"
                class="group shrink-0 h-8 px-3 rounded-lg bg-slate-100 hover:bg-teal-50 text-slate-700 hover:text-teal-700 border border-slate-200 hover:border-teal-300 dark:bg-[#1A222C] dark:hover:bg-teal-500/15 dark:text-slate-200 dark:hover:text-teal-300 dark:border-slate-700 dark:hover:border-teal-500/40 text-xs font-extrabold inline-flex items-center gap-1.5 transition-all">
            <span class="material-symbols-outlined text-[16px] text-slate-500 group-hover:text-teal-600 dark:text-slate-400 dark:group-hover:text-teal-300 transition-colors">print</span>
            <span>طباعة السند</span>
        </button>
    </div>
</article>
