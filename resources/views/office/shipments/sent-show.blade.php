@extends('office.layouts.app')

@section('title', 'كشف شحنة #' . $shipment->shipment_number)

@section('shell-class', 'bg-slate-50')

@section('content')
    @php
        $destList = is_array($shipment->destinations_summary) ? $shipment->destinations_summary : [];
        $activeParcels = $shipment->parcels->reject(fn($p) => $p->status === \App\Enums\OutgoingParcelStatus::CANCELLED);
        $activePaidTotal = round((float) $activeParcels->filter(fn($p) => ($p->payment_type instanceof \App\Enums\OutgoingPaymentType ? $p->payment_type : \App\Enums\OutgoingPaymentType::tryFrom((string) $p->payment_type)) !== \App\Enums\OutgoingPaymentType::DEFERRED)->sum('cod_amount'), 2);
        $activeDeferredTotal = round((float) $activeParcels->filter(fn($p) => ($p->payment_type instanceof \App\Enums\OutgoingPaymentType ? $p->payment_type : \App\Enums\OutgoingPaymentType::tryFrom((string) $p->payment_type)) === \App\Enums\OutgoingPaymentType::DEFERRED)->sum('cod_amount'), 2);
        $activeGrandTotal = round($activePaidTotal + $activeDeferredTotal, 2);

        $shipmentAllowsDetach = in_array(
            $shipment->status,
            [
                \App\Enums\ShipmentStatus::DRAFT,
                \App\Enums\ShipmentStatus::IN_TRANSIT,
                \App\Enums\ShipmentStatus::PARTIALLY_RECEIVED,
            ],
            true,
        );

        $hasAnyReceived = $shipment->parcels->contains(
            fn($p) => $p->linked_incoming_parcel_id !== null
                || in_array(
                    $p->status,
                    [\App\Enums\OutgoingParcelStatus::RECEIVED, \App\Enums\OutgoingParcelStatus::DELIVERED],
                    true,
                ),
        );

        $canDispatch = $shipment->status === \App\Enums\ShipmentStatus::DRAFT && $activeParcels->isNotEmpty();
        $canCancel =
            $shipment->status !== \App\Enums\ShipmentStatus::CANCELLED
            && in_array(
                $shipment->status,
                [\App\Enums\ShipmentStatus::DRAFT, \App\Enums\ShipmentStatus::IN_TRANSIT],
                true,
            )
            && !$hasAnyReceived;
    @endphp

    @push('styles')
        <style>
            @media print {
                @page {
                    size: A4 portrait;
                    margin: 10mm;
                }

                html,
                body {
                    height: auto !important;
                    min-height: 0 !important;
                    margin: 0 !important;
                    padding: 0 !important;
                    background: #fff !important;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }

                header,
                footer,
                nav,
                .no-print {
                    display: none !important;
                }

                table.print-table thead {
                    display: table-header-group;
                }

                table.print-table tfoot {
                    display: table-footer-group;
                }

                table.print-table tr {
                    break-inside: avoid;
                    page-break-inside: avoid;
                }
            }
        </style>
    @endpush

    <main class="flex-1 p-3.5 space-y-3.5 print:hidden" x-data="sentShipmentShowPage()"
        @keydown.escape.window="closeDetachModal(); closeCancelModal()">

        {{-- شريط علوي للرجوع والطباعة المباشرة (مطابق لتطبيق المحاسب) --}}
        <div class="flex items-center justify-between gap-2 no-print">
            <a href="{{ route('office.shipments.sent.index') }}"
                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white border border-slate-200 text-xs font-bold text-slate-700 hover:text-primary">
                <span class="material-symbols-outlined text-base">arrow_forward</span>
                <span>الشحنات المرسلة</span>
            </a>

            <button type="button" onclick="window.print()"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-primary text-secondary text-xs font-extrabold hover:bg-slate-800 active:scale-95 transition-all">
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

            {{-- المجاميع المالية (الطرود الفعّالة فقط) --}}
            <div class="grid grid-cols-3 gap-2 text-center pt-1">
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-2">
                    <p class="text-[10px] font-bold text-amber-700">إجمالي الآجل</p>
                    <p class="text-sm font-mono font-black text-amber-900">
                        {{ number_format($activeDeferredTotal) }} ر.ي
                    </p>
                </div>
                <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-2">
                    <p class="text-[10px] font-bold text-emerald-700">إجمالي المحاسب</p>
                    <p class="text-sm font-mono font-black text-emerald-900">
                        {{ number_format($activePaidTotal) }} ر.ي
                    </p>
                </div>
                <div class="bg-primary text-white rounded-xl p-2">
                    <p class="text-[10px] font-bold text-slate-300">الإجمالي الكلي</p>
                    <p class="text-sm font-mono font-black text-secondary">
                        {{ number_format($activeGrandTotal) }} ر.ي
                    </p>
                </div>
            </div>

            {{-- أزرار الشحن والإلغاء (إن أمكن) --}}
            @if ($canDispatch || $canCancel)
                <div class="flex items-center gap-2 pt-2 border-t border-slate-100 no-print">
                    @if ($canDispatch)
                        <form method="POST" action="{{ route('office.shipments.sent.dispatch', $shipment) }}"
                            @submit="if (typeof window.requireOnlineAction === 'function' && !window.requireOnlineAction()) { $event.preventDefault(); return; }"
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
                        <button type="button" @click="cancelModal.open = true"
                            class="{{ $canDispatch ? '' : 'flex-1' }} py-2.5 px-4 rounded-xl bg-rose-50 hover:bg-rose-100 border border-rose-200 text-rose-700 text-xs font-extrabold flex items-center justify-center gap-1 transition-colors">
                            <span class="material-symbols-outlined text-base">cancel</span>
                            <span>إلغاء الكشف</span>
                        </button>
                    @endif
                </div>
            @endif
        </div>

        {{-- جدول/بطاقات الطرود داخل الشحنة --}}
        <div class="space-y-2" @click="handleParcelListClick($event)">
            <h3 class="text-xs font-extrabold text-slate-700 px-1">
                الطرود المضمومة ({{ $activeParcels->count() }})
            </h3>

            @forelse ($activeParcels as $parcel)
                @php
                    $paymentEnum =
                        $parcel->payment_type instanceof \App\Enums\OutgoingPaymentType
                        ? $parcel->payment_type
                        : \App\Enums\OutgoingPaymentType::tryFrom((string) $parcel->payment_type) ??
                        \App\Enums\OutgoingPaymentType::PAID;
                    $isReceived =
                        $parcel->status === \App\Enums\OutgoingParcelStatus::RECEIVED
                        || $parcel->linked_incoming_parcel_id !== null;
                    $canDetachParcel =
                        $shipmentAllowsDetach
                        && !$isReceived
                        && $parcel->status !== \App\Enums\OutgoingParcelStatus::DELIVERED;
                @endphp
                <div class="bg-white border {{ $parcel->status->cardBorderClass() }} rounded-2xl p-3 space-y-2 text-xs">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span
                                    class="px-1.5 py-0.5 rounded bg-slate-100 font-mono text-[10px] font-black text-primary">سند
                                    #{{ $parcel->receipt_number }}</span>
                                <span class="font-extrabold text-slate-800">{{ $parcel->package_type }}</span>
                            </div>
                            <p class="text-[10px] text-slate-500 font-bold mt-0.5">الوجهة:
                                {{ $parcel->destinationLabel() }}
                            </p>
                        </div>

                        <div class="flex items-center gap-1.5 shrink-0">
                            @if ($office->hasFeature('outgoing_parcels'))
                                <button type="button" @click="printReceipt(@js(route('office.outgoing-parcels.receipt', $parcel)))"
                                    title="طباعة فورية للسند" aria-label="طباعة فورية للسند"
                                    class="no-print w-7 h-7 rounded-lg bg-primary hover:bg-slate-800 text-secondary border border-primary flex items-center justify-center transition-colors">
                                    <span class="material-symbols-outlined text-[15px]">print</span>
                                </button>

                                <button type="button"
                                    data-card-action="share-receipt-image"
                                    data-receipt-url="{{ route('office.outgoing-parcels.receipt', $parcel) }}"
                                    data-receipt-number="{{ $parcel->receipt_number }}"
                                    title="مشاركة صورة السند" aria-label="مشاركة صورة السند"
                                    class="no-print w-7 h-7 rounded-lg bg-sky-50 hover:bg-sky-600 text-sky-700 hover:text-white border border-sky-200/90 flex items-center justify-center transition-colors disabled:opacity-50">
                                    <span class="material-symbols-outlined text-[15px]">share</span>
                                </button>
                            @endif
                            <span
                                class="px-2 py-0.5 rounded-md border text-[10px] font-extrabold {{ $parcel->status->badgeClass() }}">
                                {{ $isReceived ? 'تم الاستلام' : $parcel->status->label() }}
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-1.5 border-t border-slate-100 text-[11px]">
                        <span class="text-slate-600 font-bold">
                            المستلم: {{ $parcel->recipient_name ?: 'بدون اسم' }}
                            <span class="font-mono text-slate-400" dir="ltr">({{ $parcel->recipient_phone }})</span>
                        </span>
                        <span class="px-2 py-0.5 rounded-md border text-[10px] font-extrabold {{ $paymentEnum->badgeClass() }}">
                            {{ $paymentEnum->label() }}: {{ number_format((float) ($parcel->cod_amount ?? 0)) }} ر.ي
                        </span>
                    </div>

                    @if ($canDetachParcel)
                        <div class="pt-1 border-t border-slate-100 flex justify-end no-print">
                            <button type="button" @click="openDetachModal(@js([
                                'url' => route('office.shipments.sent.detach-parcel', [$shipment, $parcel]),
                                'receipt' => $parcel->receipt_number,
                                'packageType' => $parcel->package_type,
                                'destination' => $parcel->destinationLabel(),
                            ]))"
                                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-amber-50 hover:bg-amber-100 border border-amber-300 text-amber-800 text-[11px] font-bold transition-colors active:scale-95">
                                <span class="material-symbols-outlined text-sm">link_off</span>
                                <span>إخراج من الشحنة</span>
                            </button>
                        </div>
                    @endif
                </div>
            @empty
                <div
                    class="bg-white border border-dashed border-slate-200 rounded-2xl p-6 text-center text-xs font-bold text-slate-400">
                    لا توجد طرود فعّالة متبقية داخل هذه الشحنة.
                </div>
            @endforelse
        </div>

        {{-- مودال تأكيد إلغاء الكشف بالكامل --}}
        @if ($canCancel)
            <div x-show="cancelModal.open" x-cloak
                class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-3 sm:p-4 no-print">
                <div class="fixed inset-0 bg-slate-950/60" @click="closeCancelModal()"></div>

                <div
                    class="relative w-full max-w-sm bg-white rounded-3xl p-5 shadow-2xl border border-slate-100 z-10 flex flex-col gap-4 text-right">
                    <button type="button" @click="closeCancelModal()" :disabled="cancelModal.submitting"
                        class="absolute left-4 top-4 w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-400 flex items-center justify-center">
                        <span class="material-symbols-outlined text-base">close</span>
                    </button>

                    <div class="text-center pt-1">
                        <div
                            class="w-14 h-14 mx-auto rounded-2xl bg-rose-50 border border-rose-100 text-rose-600 flex items-center justify-center mb-3">
                            <span class="material-symbols-outlined text-3xl">cancel</span>
                        </div>
                        <h3 class="text-base font-extrabold text-slate-800">إلغاء كشف الشحنة</h3>
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                            سيتم إلغاء الكشف #{{ $shipment->shipment_number }} وتنزيل جميع الطرود بداخله وإرجاعها إلى حالة (في
                            الانتظار).
                        </p>
                    </div>

                    <form method="POST" action="{{ route('office.shipments.sent.cancel', $shipment) }}"
                        @submit="if (typeof window.requireOnlineAction === 'function' && !window.requireOnlineAction()) { $event.preventDefault(); return; } cancelModal.submitting = true"
                        class="flex flex-col gap-2">
                        @csrf
                        <button type="submit" :disabled="cancelModal.submitting"
                            class="w-full py-3 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-extrabold text-xs shadow-lg shadow-rose-600/25 flex items-center justify-center gap-1.5 disabled:opacity-50">
                            <span class="material-symbols-outlined text-base">cancel</span>
                            <span x-text="cancelModal.submitting ? 'جاري إلغاء الكشف...' : 'تأكيد إلغاء الكشف'"></span>
                        </button>
                        <button type="button" @click="closeCancelModal()" :disabled="cancelModal.submitting"
                            class="w-full py-2.5 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs">
                            تراجع
                        </button>
                    </form>
                </div>
            </div>
        @endif

        {{-- مودال تأكيد إخراج طرد من الشحنة --}}
        <div x-show="detachModal.open" x-cloak
            class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-3 sm:p-4 no-print">
            <div class="fixed inset-0 bg-slate-950/60" @click="closeDetachModal()"></div>

            <div
                class="relative w-full max-w-sm bg-white rounded-3xl p-5 shadow-2xl border border-slate-100 z-10 flex flex-col gap-4 text-right">
                <button type="button" @click="closeDetachModal()" :disabled="detachModal.submitting"
                    class="absolute left-4 top-4 w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-400 flex items-center justify-center">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>

                <div class="text-center pt-1">
                    <div
                        class="w-14 h-14 mx-auto rounded-2xl bg-amber-50 border border-amber-200 text-amber-600 flex items-center justify-center mb-3">
                        <span class="material-symbols-outlined text-3xl">link_off</span>
                    </div>
                    <h3 class="text-base font-extrabold text-slate-800">إخراج الطرد من الشحنة</h3>
                    <p class="text-xs text-slate-500 mt-1">سيتم فك ارتباط الطرد وإعادته إلى قائمة (في الانتظار).</p>
                </div>

                <div class="bg-slate-50 rounded-2xl p-3 border border-slate-200/80 space-y-1.5 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">رقم السند:</span>
                        <span class="font-mono font-bold text-slate-800" x-text="'#' + detachModal.receipt"></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">النوع:</span>
                        <span class="font-bold text-slate-700" x-text="detachModal.packageType"></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">الوجهة:</span>
                        <span class="font-bold text-amber-700 truncate max-w-[180px]"
                            x-text="detachModal.destination"></span>
                    </div>
                </div>

                <form method="POST" :action="detachModal.url"
                    @submit="if (typeof window.requireOnlineAction === 'function' && !window.requireOnlineAction()) { $event.preventDefault(); return; } detachModal.submitting = true"
                    class="flex flex-col gap-2">
                    @csrf
                    <button type="submit" :disabled="detachModal.submitting"
                        class="w-full py-3 px-4 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-extrabold text-xs shadow-lg shadow-amber-600/25 flex items-center justify-center gap-1.5 disabled:opacity-50">
                        <span class="material-symbols-outlined text-base">link_off</span>
                        <span x-text="detachModal.submitting ? 'جاري الإخراج...' : 'تأكيد الإخراج من الشحنة'"></span>
                    </button>
                    <button type="button" @click="closeDetachModal()" :disabled="detachModal.submitting"
                        class="w-full py-2.5 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs">
                        تراجع
                    </button>
                </form>
            </div>
        </div>

    </main>

    {{-- قالب الطباعة الرسمي A4 (يظهر فقط عند الطباعة المباشرة — توأم كشف المحاسب تماماً) --}}
    <div class="hidden print:block w-full bg-white">
        @include('office.shipments.partials.sent-manifest-document', [
            'office' => $office,
            'shipment' => $shipment,
        ])
    </div>
@endsection
@push('scripts')
    <script>
        function sentShipmentShowPage() {
            return {
                cancelModal: { open: false, submitting: false },
                detachModal: { open: false, url: '#', receipt: '', packageType: '', destination: '', submitting: false },

                handleParcelListClick(event) {
                    const btn = event.target?.closest('[data-card-action="share-receipt-image"]');
                    if (!btn) return;
                    const receiptUrl = btn.getAttribute('data-receipt-url');
                    const receiptNumber = btn.getAttribute('data-receipt-number') || '';
                    if (receiptUrl && typeof window.shareReceiptAsImage === 'function') {
                        window.shareReceiptAsImage(receiptUrl, receiptNumber, btn);
                    }
                },

                printReceipt(url) {
                    if (!url) return;
                    if (typeof window.requireOnlineAction === 'function' && !window.requireOnlineAction()) return;
                    const fallbackUrl = url + (url.includes('?') ? '&' : '?') + 'auto_print=1';
                    try {
                        let iframe = document.getElementById('outgoing-receipt-print-iframe');
                        if (iframe) iframe.remove();
                        iframe = document.createElement('iframe');
                        iframe.id = 'outgoing-receipt-print-iframe';
                        iframe.setAttribute('aria-hidden', 'true');
                        iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden;';
                        iframe.onload = () => {
                            setTimeout(() => {
                                try {
                                    if (iframe.contentWindow) {
                                        iframe.contentWindow.focus();
                                        iframe.contentWindow.print();
                                    } else {
                                        window.open(fallbackUrl, '_blank');
                                    }
                                } catch (e) {
                                    window.open(fallbackUrl, '_blank');
                                }
                            }, 220);
                        };
                        iframe.onerror = () => window.open(fallbackUrl, '_blank');
                        document.body.appendChild(iframe);
                        iframe.src = url;
                    } catch (e) {
                        window.open(fallbackUrl, '_blank');
                    }
                },

                openDetachModal(data) {
                    this.detachModal = {
                        open: true,
                        url: data.url,
                        receipt: data.receipt || '',
                        packageType: data.packageType || '',
                        destination: data.destination || '',
                        submitting: false,
                    };
                },

                closeDetachModal() {
                    if (this.detachModal.submitting) return;
                    this.detachModal.open = false;
                },

                closeCancelModal() {
                    if (this.cancelModal.submitting) return;
                    this.cancelModal.open = false;
                }
            };
        }
    </script>
@endpush