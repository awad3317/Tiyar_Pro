@php
    $paymentEnum = $parcel->payment_type instanceof \App\Enums\OutgoingPaymentType
        ? $parcel->payment_type
        : (\App\Enums\OutgoingPaymentType::tryFrom((string) $parcel->payment_type) ?? \App\Enums\OutgoingPaymentType::PAID);

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
    ];
@endphp
<article id="outgoing-card-{{ $parcel->id }}"
         style="content-visibility: auto; contain-intrinsic-size: 160px;"
         class="bg-white border border-slate-200/90 rounded-2xl p-3.5 shadow-xs space-y-2.5">
    {{-- رأس البطاقة: نوع الطرد، السند، وحالة الطرد --}}
    <div class="flex items-start justify-between gap-2">
        <div class="flex items-center gap-2 min-w-0">
            <span class="w-8 h-8 rounded-lg bg-slate-100 text-primary flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-lg">outbox</span>
            </span>
            <div class="min-w-0">
                <div class="flex items-center gap-1.5 flex-wrap">
                    <h3 class="font-extrabold text-sm text-slate-800">{{ $parcel->package_type }}</h3>
                    @if($parcel->receipt_number)
                        <span class="px-1.5 py-0.5 rounded-md bg-slate-100 text-slate-700 border border-slate-200 text-[10px] font-mono font-extrabold">
                            سند #{{ $parcel->receipt_number }}
                        </span>
                    @endif
                </div>

                {{-- شارة الوجهة وحالة الدفع والمبلغ --}}
                <div class="flex items-center gap-1.5 flex-wrap mt-1">
                    @if($parcel->isExternal())
                        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-200 text-[10px] font-bold">
                            <span class="material-symbols-outlined text-[12px]">storefront</span>
                            <span>مكتب خارجي: {{ $parcel->destinationLabel() }}</span>
                        </span>
                    @else
                        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-md bg-sky-50 text-sky-800 border border-sky-200 text-[10px] font-bold">
                            <span class="material-symbols-outlined text-[12px]">domain</span>
                            <span>فرع: {{ $parcel->destinationLabel() }}</span>
                        </span>
                    @endif

                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md border text-[10px] font-extrabold {{ $paymentEnum->badgeClass() }}">
                        <span>{{ $paymentEnum->label() }}:</span>
                        <span class="font-mono">{{ number_format((float) ($parcel->cod_amount ?? 0)) }} ر.ي</span>
                    </span>
                </div>
            </div>
        </div>

        {{-- شارة الحالة الأساسية --}}
        <span class="px-2 py-0.5 rounded-md border text-[10px] font-extrabold shrink-0 {{ $parcel->status->badgeClass() }}">
            {{ $parcel->status->label() }}
        </span>
    </div>

    {{-- بيانات المرسل والمستلم --}}
    <div class="grid grid-cols-2 gap-2 bg-slate-50 rounded-xl p-2.5 text-xs border border-slate-100">
        <div class="min-w-0">
            <p class="text-[10px] text-slate-400 font-bold">المرسل</p>
            <p class="font-bold text-slate-800 truncate">{{ $parcel->sender_name }}</p>
            <p class="text-[11px] text-slate-500 font-mono dir-ltr text-right">{{ $parcel->sender_phone }}</p>
        </div>
        <div class="min-w-0 border-r border-slate-200 pr-2">
            <p class="text-[10px] text-slate-400 font-bold">المستلم</p>
            <p class="font-bold text-slate-800 truncate">{{ $parcel->recipient_name ?: 'بدون اسم' }}</p>
            <p class="text-[11px] text-slate-500 font-mono dir-ltr text-right">{{ $parcel->recipient_phone }}</p>
        </div>
    </div>

    @if($parcel->notes)
        <p class="text-[11px] text-slate-600 bg-amber-50/60 border border-amber-100 rounded-xl px-2.5 py-1.5">
            <span class="font-extrabold text-amber-800">ملاحظة:</span> {{ $parcel->notes }}
        </p>
    @endif

    {{-- أزرار الإجراءات حسب الحالة --}}
    @if ($parcel->status === \App\Enums\OutgoingParcelStatus::PENDING)
        <div class="flex items-center gap-2 pt-1">
            <button type="button"
                    :disabled="Boolean(actionLoading['{{ $parcel->id }}'])"
                    @click="openActionModal('dispatch', @js(array_merge($modalPayload, ['url' => route('office.outgoing-parcels.dispatch', $parcel)])))"
                    class="flex-1 py-2 px-3 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-xs font-extrabold flex items-center justify-center gap-1 transition-all active:scale-95 disabled:opacity-50">
                <span class="material-symbols-outlined text-sm">local_shipping</span>
                <span x-text="actionLoading['{{ $parcel->id }}'] ? 'جاري الشحن...' : 'شحن (في الطريق)'"></span>
            </button>

            <button type="button"
                    :disabled="Boolean(actionLoading['{{ $parcel->id }}'])"
                    @click="openActionModal('cancel', @js(array_merge($modalPayload, ['url' => route('office.outgoing-parcels.cancel', $parcel)])))"
                    class="py-2 px-3 rounded-xl border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold flex items-center justify-center gap-1 transition-all active:scale-95 disabled:opacity-50">
                <span class="material-symbols-outlined text-sm">cancel</span>
                <span>إلغاء</span>
            </button>
        </div>

    @elseif ($parcel->status === \App\Enums\OutgoingParcelStatus::IN_TRANSIT)
        <div class="flex items-center gap-2 pt-1">
            @if ($parcel->isExternal())
                <button type="button"
                        :disabled="Boolean(actionLoading['{{ $parcel->id }}'])"
                        @click="openActionModal('deliver', @js(array_merge($modalPayload, ['url' => route('office.outgoing-parcels.deliver-external', $parcel)])))"
                        class="flex-1 py-2 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-extrabold flex items-center justify-center gap-1 transition-all active:scale-95 disabled:opacity-50">
                    <span class="material-symbols-outlined text-sm">check_circle</span>
                    <span x-text="actionLoading['{{ $parcel->id }}'] ? 'جاري التأكيد...' : 'تم التسليم'"></span>
                </button>
            @else
                <div class="flex-1 py-2 px-3 rounded-xl bg-sky-50 border border-sky-200 text-sky-800 text-xs font-bold flex items-center justify-center gap-1">
                    <span class="material-symbols-outlined text-sm">hourglass_top</span>
                    <span>بانتظار استلام الفرع</span>
                </div>
            @endif

            <button type="button"
                    :disabled="Boolean(actionLoading['{{ $parcel->id }}'])"
                    @click="openActionModal('cancel', @js(array_merge($modalPayload, ['url' => route('office.outgoing-parcels.cancel', $parcel)])))"
                    class="py-2 px-3 rounded-xl border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold flex items-center justify-center gap-1 transition-all active:scale-95 disabled:opacity-50">
                <span class="material-symbols-outlined text-sm">cancel</span>
                <span>إلغاء</span>
            </button>
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
        <div class="pt-1 flex items-center justify-between text-[11px] bg-indigo-50/50 border border-indigo-100 rounded-xl px-3 py-2">
            <span class="font-bold text-slate-600">حالة الطرد لدى الفرع المستلم:</span>
            <span class="px-2 py-0.5 rounded-md border font-extrabold {{ $liveBadge['class'] }}">
                {{ $liveBadge['label'] }}
            </span>
        </div>
    @endif
</article>