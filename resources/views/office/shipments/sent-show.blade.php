@extends('office.layouts.app')

@section('title', 'كشف شحنة #' . $shipment->shipment_number)

@section('shell-class', 'bg-slate-50')

@section('content')
    @php
        $destList = is_array($shipment->destinations_summary) ? $shipment->destinations_summary : [];
        $canDispatch = $shipment->status === \App\Enums\ShipmentStatus::DRAFT;
        $canCancel =
            in_array(
                $shipment->status,
                [\App\Enums\ShipmentStatus::DRAFT, \App\Enums\ShipmentStatus::IN_TRANSIT],
                true,
            ) &&
            $shipment->parcels->every(
                fn($p) => $p->linked_incoming_parcel_id === null &&
                    !in_array(
                        $p->status,
                        [\App\Enums\OutgoingParcelStatus::RECEIVED, \App\Enums\OutgoingParcelStatus::DELIVERED],
                        true,
                    ),
            );
    @endphp

    <style>
        @media print {

            header,
            footer,
            nav,
            .no-print {
                display: none !important;
            }

            body,
            .max-w-md {
                max-width: 100% !important;
                width: 100% !important;
                background: #fff !important;
                box-shadow: none !important;
            }

            main {
                padding: 0 !important;
            }
        }
    </style>

    <main class="flex-1 p-3.5 space-y-3.5" x-data="{ confirmModal: { open: false, type: '', url: '', submitting: false } }">

        {{-- شريط علوي للرجوع والطباعة --}}
        <div class="flex items-center justify-between gap-2 no-print">
            <a href="{{ route('office.shipments.sent.index') }}"
                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white border border-slate-200 text-xs font-bold text-slate-700 hover:text-primary">
                <span class="material-symbols-outlined text-base">arrow_forward</span>
                <span>الشحنات المرسلة</span>
            </a>

            <button type="button" onclick="window.print()"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-primary text-secondary text-xs font-extrabold hover:bg-slate-800">
                <span class="material-symbols-outlined text-base">print</span>
                <span>طباعة الكشف</span>
            </button>
        </div>

        {{-- بطاقة ملخص الكشف --}}
        <div class="bg-white border border-slate-200 rounded-2xl p-4 space-y-3 shadow-xs">
            <div class="flex items-start justify-between gap-2 border-b border-slate-100 pb-3">
                <div>
                    <p class="text-[10px] font-bold text-slate-400">كشف شحنة رسمي</p>
                    <h2 class="text-base font-black font-mono text-primary mt-0.5">#{{ $shipment->shipment_number }}</h2>
                    <p class="text-[11px] font-bold text-slate-600 mt-0.5">
                        الفرع المرسل: <span class="text-primary">{{ $shipment->senderOfficeLabel() }}</span>
                    </p>
                </div>
                <span
                    class="px-2.5 py-1 rounded-lg border text-[11px] font-extrabold {{ $shipment->status->badgeClass() }}">
                    {{ $shipment->status->label() }}
                </span>
            </div>

            <div class="grid grid-cols-2 gap-2 text-xs bg-slate-50 rounded-xl p-2.5 border border-slate-100">
                <div>
                    <p class="text-[10px] text-slate-400 font-bold">اسم السائق</p>
                    <p class="font-extrabold text-slate-800">{{ $shipment->driver_name }}</p>
                </div>
                <div class="border-r border-slate-200 pr-2">
                    <p class="text-[10px] text-slate-400 font-bold">رقم السائق</p>
                    <a href="tel:{{ $shipment->driver_phone }}" class="font-mono font-bold text-primary"
                        dir="ltr">{{ $shipment->driver_phone }}</a>
                </div>
            </div>

            @if (count($destList) > 0)
                <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="text-[11px] font-bold text-slate-400">الوجهات:</span>
                    @foreach ($destList as $d)
                        <span
                            class="px-2 py-0.5 rounded-md bg-slate-100 border border-slate-200 text-[11px] font-bold text-slate-700">{{ $d }}</span>
                    @endforeach
                </div>
            @endif

            {{-- المجاميع المالية --}}
            <div class="grid grid-cols-3 gap-2 text-center pt-1">
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-2">
                    <p class="text-[10px] font-bold text-amber-700">إجمالي الآجل</p>
                    <p class="text-sm font-mono font-black text-amber-900">
                        {{ number_format((float) $shipment->total_deferred_amount) }} ر.ي</p>
                </div>
                <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-2">
                    <p class="text-[10px] font-bold text-emerald-700">إجمالي المحاسب</p>
                    <p class="text-sm font-mono font-black text-emerald-900">
                        {{ number_format((float) $shipment->total_paid_amount) }} ر.ي</p>
                </div>
                <div class="bg-primary text-white rounded-xl p-2">
                    <p class="text-[10px] font-bold text-slate-300">الإجمالي الكلي</p>
                    <p class="text-sm font-mono font-black text-secondary">
                        {{ number_format((float) $shipment->total_amount) }} ر.ي</p>
                </div>
            </div>

            {{-- أزرار الشحن والإلغاء (إن أمكن) --}}
            @if ($canDispatch || $canCancel)
                <div class="flex items-center gap-2 pt-2 border-t border-slate-100 no-print">
                    @if ($canDispatch)
                        <form method="POST" action="{{ route('office.shipments.sent.dispatch', $shipment) }}"
                            class="flex-1">
                            @csrf
                            <button type="submit"
                                class="w-full py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-xs font-extrabold flex items-center justify-center gap-1">
                                <span class="material-symbols-outlined text-base">local_shipping</span>
                                <span>شحن الكشف الآن</span>
                            </button>
                        </form>
                    @endif
                    @if ($canCancel)
                        <form method="POST" action="{{ route('office.shipments.sent.cancel', $shipment) }}"
                            class="{{ $canDispatch ? '' : 'flex-1' }}">
                            @csrf
                            <button type="submit"
                                class="w-full py-2.5 px-4 rounded-xl bg-rose-50 hover:bg-rose-100 border border-rose-200 text-rose-700 text-xs font-extrabold flex items-center justify-center gap-1">
                                <span class="material-symbols-outlined text-base">cancel</span>
                                <span>إلغاء الكشف</span>
                            </button>
                        </form>
                    @endif
                </div>
            @endif
        </div>

        {{-- جدول/بطاقات الطرود داخل الشحنة --}}
        <div class="space-y-2">
            <h3 class="text-xs font-extrabold text-slate-700 px-1">
                الطرود المضمومة ({{ $shipment->parcels->count() }})
            </h3>

            @foreach ($shipment->parcels as $parcel)
                @php
                    $paymentEnum =
                        $parcel->payment_type instanceof \App\Enums\OutgoingPaymentType
                            ? $parcel->payment_type
                            : \App\Enums\OutgoingPaymentType::tryFrom((string) $parcel->payment_type) ??
                                \App\Enums\OutgoingPaymentType::PAID;
                @endphp
                <div class="bg-white border border-slate-200 rounded-2xl p-3 space-y-1.5 text-xs">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span
                                    class="px-1.5 py-0.5 rounded bg-slate-100 font-mono text-[10px] font-black text-primary">سند
                                    #{{ $parcel->receipt_number }}</span>
                                <span class="font-extrabold text-slate-800">{{ $parcel->package_type }}</span>
                            </div>
                            <p class="text-[10px] text-slate-500 font-bold mt-0.5">الوجهة:
                                {{ $parcel->destinationLabel() }}</p>
                        </div>

                        <div class="flex items-center gap-1.5 shrink-0">
                            <a href="{{ route('office.outgoing-parcels.receipt', $parcel) }}" target="_blank"
                                title="طباعة السند"
                                class="no-print w-7 h-7 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center">
                                <span class="material-symbols-outlined text-base">qr_code_2</span>
                            </a>
                            <span
                                class="px-2 py-0.5 rounded-md border text-[10px] font-extrabold {{ $parcel->status->badgeClass() }}">
                                {{ $parcel->status->label() }}
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-1.5 border-t border-slate-100 text-[11px]">
                        <span class="text-slate-600 font-bold">
                            المستلم: {{ $parcel->recipient_name ?: 'بدون اسم' }}
                            <span class="font-mono text-slate-400" dir="ltr">({{ $parcel->recipient_phone }})</span>
                        </span>
                        <span
                            class="px-2 py-0.5 rounded-md border text-[10px] font-extrabold {{ $paymentEnum->badgeClass() }}">
                            {{ $paymentEnum->label() }}: {{ number_format((float) ($parcel->cod_amount ?? 0)) }} ر.ي
                        </span>
                    </div>
                </div>
            @endforeach
        </div>

    </main>
@endsection
