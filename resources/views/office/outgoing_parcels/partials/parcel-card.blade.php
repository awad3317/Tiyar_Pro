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
@endphp

<article id="outgoing-card-{{ $parcel->id }}"
         data-outgoing-parcel-id="{{ $parcel->id }}"
         style="content-visibility: auto; contain-intrinsic-size: 165px;"
         class="bg-white border {{ $parcel->status->cardBorderClass() }} rounded-2xl p-4 shadow-sm transition-colors">

    {{-- الرأس: عمودان نظيفان (يمين: أيقونة + عنوان + شارات أفقية | يسار: شارة حالة واحدة + وقت + QR) --}}
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

        {{-- الجانب الأيسر (نهاية RTL): شارة حالة واحدة فقط + الوقت + QR --}}
        <div class="shrink-0 flex flex-col items-end gap-1.5">
            <span class="px-2 py-0.5 rounded-md border text-[10px] font-bold whitespace-nowrap {{ $parcel->status->badgeClass() }}">
                {{ $parcel->status->label() }}
            </span>

            <div class="flex items-center gap-1">
                @if($createdAgo)
                    <span class="inline-flex items-center gap-0.5 text-[10px] font-bold px-1.5 py-0.5 rounded-md bg-blue-50 text-blue-700 border border-blue-200/70 whitespace-nowrap"
                          title="{{ $parcel->created_at?->format('Y-m-d H:i') }}">
                        <span class="material-symbols-outlined text-[12px]">hourglass_top</span>
                        <span>{{ $createdAgo }}</span>
                    </span>
                @endif

                <button type="button"
                        data-card-action="print-receipt"
                        data-receipt-url="{{ route('office.outgoing-parcels.receipt', $parcel) }}"
                        title="طباعة فورية للسند"
                        aria-label="طباعة فورية للسند"
                        class="w-7 h-7 rounded-lg bg-primary hover:bg-slate-800 text-secondary border border-primary flex items-center justify-center transition-colors shrink-0">
                    <span class="material-symbols-outlined text-[15px]">print</span>
                </button>

                <button type="button"
                        data-card-action="send-receipt-whatsapp"
                        data-whatsapp-url="{{ route('office.outgoing-parcels.send-receipt-whatsapp', $parcel) }}"
                        data-parcel-id="{{ $parcel->id }}"
                        title="إرسال السند PDF للمستلم عبر واتساب"
                        aria-label="إرسال السند PDF عبر واتساب"
                        class="w-7 h-7 rounded-lg bg-emerald-50 hover:bg-emerald-600 text-emerald-700 hover:text-white border border-emerald-200/90 flex items-center justify-center transition-colors shrink-0 disabled:opacity-50">
                    <svg viewBox="0 0 24 24" class="w-3.5 h-3.5 fill-current" aria-hidden="true">
                        <path d="M12.03 2C6.51 2 2.04 6.47 2.04 11.99c0 1.76.46 3.48 1.34 4.99L2 22l5.16-1.35a9.95 9.95 0 0 0 4.87 1.24h.01c5.52 0 9.99-4.47 9.99-9.99A9.93 9.93 0 0 0 19.1 4.9 9.92 9.92 0 0 0 12.03 2Zm5.82 14.17c-.24.68-1.41 1.3-1.95 1.38-.5.08-1.13.11-1.82-.11-.42-.14-.96-.31-1.65-.61-2.9-1.25-4.79-4.18-4.94-4.38-.14-.19-1.18-1.57-1.18-2.99 0-1.43.75-2.13 1.01-2.42.27-.29.58-.36.77-.36.19 0 .39 0 .56.01.18.01.42-.07.66.5.24.58.82 2 .9 2.15.07.15.12.32.02.51-.1.19-.15.32-.29.49-.14.17-.3.38-.43.51-.15.14-.3.3-.13.59.17.29.76 1.25 1.63 2.02 1.12 1 2.06 1.31 2.35 1.45.29.15.46.12.63-.07.17-.19.73-.85.92-1.14.19-.29.39-.24.66-.14.27.1 1.7.8 1.99.95.29.14.48.21.55.33.08.12.08.7-.16 1.38Z"/>
                    </svg>
                </button>

                <button type="button"
                        data-card-action="share-receipt-pdf"
                        data-pdf-url="{{ route('office.outgoing-parcels.receipt-pdf', $parcel) }}"
                        data-receipt-number="{{ $parcel->receipt_number }}"
                        data-parcel-id="{{ $parcel->id }}"
                        title="مشاركة أو حفظ السند PDF"
                        aria-label="مشاركة السند PDF"
                        class="w-7 h-7 rounded-lg bg-sky-50 hover:bg-sky-600 text-sky-700 hover:text-white border border-sky-200/90 flex items-center justify-center transition-colors shrink-0 disabled:opacity-50">
                    <span class="material-symbols-outlined text-[15px]">share</span>
                </button>
            </div>
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

    {{-- شريط الإجراءات السفلي (إلغاء الطرد محصور بالطرود الحرة غير المرتبطة بشحنة، ويجب الإخراج أولاً) --}}
    @if ($parcel->status === \App\Enums\OutgoingParcelStatus::PENDING)
        <div class="mt-3 flex items-center gap-2">
            @if (! $isAttachedToShipment)
                @if ($hasSentShipments)
                    <a href="{{ route('office.shipments.sent.index') }}"
                       class="flex-1 py-2 px-3 rounded-xl bg-primary hover:bg-slate-800 text-white text-xs font-bold flex items-center justify-center gap-1.5 transition-all active:scale-95">
                        <span class="material-symbols-outlined text-sm text-secondary">local_shipping</span>
                        <span>ضم إلى شحنة</span>
                    </a>
                @else
                    <button type="button"
                            data-card-action="locked-shipments"
                            class="flex-1 py-2 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 border border-slate-200 text-slate-600 text-xs font-bold flex items-center justify-center gap-1.5 transition-all active:scale-95">
                        <span class="material-symbols-outlined text-sm text-amber-600">lock</span>
                        <span>ضم إلى شحنة</span>
                    </button>
                @endif

                <button type="button"
                        data-card-action="cancel"
                        data-action-payload="{{ $cancelPayloadJson }}"
                        class="py-2 px-3 rounded-xl border border-rose-200 text-rose-600 hover:bg-rose-50 text-xs font-bold flex items-center justify-center gap-1 transition-all active:scale-95 disabled:opacity-50 shrink-0">
                    <span class="material-symbols-outlined text-sm">cancel</span>
                    <span>إلغاء</span>
                </button>
            @else
                @if ($hasSentShipments)
                    <a href="{{ route('office.shipments.sent.show', $parcel->shipment_id) }}"
                       class="flex-1 py-2 px-3 rounded-xl bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 text-indigo-700 text-xs font-bold flex items-center justify-center gap-1.5 transition-all active:scale-95 truncate">
                        <span class="material-symbols-outlined text-sm shrink-0">inventory_2</span>
                        <span class="truncate">مُضاف للشحنة #{{ $shipment?->shipment_number ?? '...' }} (بانتظار الإرسال)</span>
                    </a>
                @else
                    <div class="flex-1 py-2 px-3 rounded-xl bg-indigo-50 border border-indigo-200 text-indigo-700 text-xs font-bold flex items-center justify-center gap-1.5 truncate">
                        <span class="material-symbols-outlined text-sm shrink-0">inventory_2</span>
                        <span class="truncate">مُضاف للشحنة #{{ $shipment?->shipment_number ?? '...' }} (بانتظار الإرسال)</span>
                    </div>
                @endif

                <button type="button"
                        data-card-action="detach"
                        data-action-payload="{{ $detachPayloadJson }}"
                        class="py-2 px-3 rounded-xl border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-800 text-xs font-bold flex items-center justify-center gap-1 transition-all active:scale-95 disabled:opacity-50 shrink-0">
                    <span class="material-symbols-outlined text-sm">link_off</span>
                    <span>إخراج من الشحنة</span>
                </button>
            @endif
        </div>

    @elseif ($parcel->status === \App\Enums\OutgoingParcelStatus::IN_TRANSIT)
        <div class="mt-3 flex items-center gap-2">
            @if ($parcel->isExternal())
                <button type="button"
                        data-card-action="deliver"
                        data-action-payload="{{ $deliverPayloadJson }}"
                        class="flex-1 py-2 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold flex items-center justify-center gap-1 transition-all active:scale-95 disabled:opacity-50">
                    <span class="material-symbols-outlined text-sm">check_circle</span>
                    <span>تم التسليم</span>
                </button>
            @else
                <div class="flex-1 py-2 px-3 rounded-xl bg-sky-50 border border-sky-200 text-sky-800 text-xs font-bold flex items-center justify-center gap-1">
                    <span class="material-symbols-outlined text-sm">hourglass_top</span>
                    <span>بانتظار استلام الفرع</span>
                </div>
            @endif

            @if ($isAttachedToShipment)
                <button type="button"
                        data-card-action="detach"
                        data-action-payload="{{ $detachPayloadJson }}"
                        class="py-2 px-3 rounded-xl border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-800 text-xs font-bold flex items-center justify-center gap-1 transition-all active:scale-95 disabled:opacity-50 shrink-0">
                    <span class="material-symbols-outlined text-sm">link_off</span>
                    <span>إخراج من الشحنة</span>
                </button>
            @else
                <button type="button"
                        data-card-action="cancel"
                        data-action-payload="{{ $cancelPayloadJson }}"
                        class="py-2 px-3 rounded-xl border border-rose-200 text-rose-600 hover:bg-rose-50 text-xs font-bold flex items-center justify-center gap-1 transition-all active:scale-95 disabled:opacity-50 shrink-0">
                    <span class="material-symbols-outlined text-sm">cancel</span>
                    <span>إلغاء</span>
                </button>
            @endif
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
