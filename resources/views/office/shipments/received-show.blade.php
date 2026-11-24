@extends('office.layouts.app')

@section('title', 'استلام كشف #' . $shipment->shipment_number)

@section('shell-class', 'bg-slate-50')

@section('content')
    @php
        $parcelsForJs = $myParcels
            ->map(function ($p) {
                $paymentEnum =
                    $p->payment_type instanceof \App\Enums\OutgoingPaymentType
                        ? $p->payment_type
                        : \App\Enums\OutgoingPaymentType::tryFrom((string) $p->payment_type) ??
                            \App\Enums\OutgoingPaymentType::PAID;

                return [
                    'id' => (string) $p->id,
                    'receipt_number' => (string) ($p->receipt_number ?? ''),
                    'package_type' => (string) $p->package_type,
                    'sender_name' => (string) $p->sender_name,
                    'sender_phone' => (string) $p->sender_phone,
                    'recipient_name' => (string) ($p->recipient_name ?: 'بدون اسم'),
                    'recipient_phone' => (string) $p->recipient_phone,
                    'payment_type' => $paymentEnum->value,
                    'payment_label' => $paymentEnum->label(),
                    'cod_amount' => (float) ($p->cod_amount ?? 0),
                    'notes' => (string) ($p->notes ?? ''),
                    'status' => $p->status->value,
                    'received' => $p->status === \App\Enums\OutgoingParcelStatus::RECEIVED,
                ];
            })
            ->values();
    @endphp

    <main class="flex-1 p-3.5 space-y-3.5" x-data="receiveShipmentPage({
        parcels: @js($parcelsForJs),
        receiveUrl: @js(route('office.shipments.received.receive-parcel', $shipment)),
        pendingShipmentsCount: {{ (int) $pendingReceivedShipmentsCount }},
        canRejectExternal: {{ !empty($canRejectExternal) ? 'true' : 'false' }}
    })">

        {{-- شريط علوي --}}
        <div class="flex items-center justify-between gap-2">
            <a href="{{ route('office.shipments.received.index') }}"
                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white border border-slate-200 text-xs font-bold text-slate-700 hover:text-primary">
                <span class="material-symbols-outlined text-base">arrow_forward</span>
                <span>الشحنات المستلمة</span>
            </a>

            <div class="flex items-center gap-1.5">
                <button type="button"
                        x-show="canRejectExternal && receivedParcels.length === 0"
                        x-cloak
                        @click="rejectModalOpen = true"
                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-extrabold transition-colors">
                    <span class="material-symbols-outlined text-sm">cancel</span>
                    <span>رفض الكشف</span>
                </button>

                <span class="px-3 py-1.5 rounded-xl text-xs font-extrabold"
                    :class="remainingParcels.length === 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-primary text-secondary'"
                    x-text="remainingParcels.length === 0 ? 'مكتمل الاستلام ✓' : ('متبقي للفحص: ' + remainingParcels.length)">
                </span>
            </div>
        </div>

        {{-- بطاقة ملخص الكشف + شريط التقدم --}}
        <div class="bg-white border border-slate-200 rounded-2xl p-3.5 shadow-xs space-y-3">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <p class="text-[10px] font-bold text-slate-400">كشف شحنة وارد من فرع شريك</p>
                        @if ($shipment->office_id === null && $shipment->external_partner_link_id !== null)
                            <span class="px-1.5 py-0.5 rounded-md bg-violet-50 text-violet-700 border border-violet-200 text-[9px] font-extrabold">
                                مكتب خارجي
                            </span>
                        @endif
                    </div>
                    <h2 class="text-base font-black font-mono text-primary">#{{ $shipment->shipment_number }}</h2>
                    <p class="text-[11px] font-bold text-slate-600 truncate">
                        المرسل: <span class="text-primary">{{ $shipment->senderOfficeLabel() }}</span>
                    </p>
                </div>
                <div class="text-left shrink-0 bg-slate-50 border border-slate-200/80 rounded-xl px-2.5 py-1.5">
                    <p class="text-[9px] text-slate-400 font-bold">السائق</p>
                    <p class="text-xs font-extrabold text-slate-800">{{ $shipment->driver_name }}</p>
                    <a href="tel:{{ $shipment->driver_phone }}" class="text-[10px] font-mono font-bold text-primary block"
                        dir="ltr">
                        {{ $shipment->driver_phone }}
                    </a>
                </div>
            </div>

            {{-- شريط عداد الفحص وكشف النقص --}}
            <div class="bg-slate-50 border border-slate-200/70 rounded-xl p-2.5 space-y-1.5">
                <div class="flex items-center justify-between text-xs font-extrabold">
                    <span class="text-emerald-700">تم استلام: <span class="font-mono"
                            x-text="receivedParcels.length"></span></span>
                    <span :class="remainingParcels.length > 0 ? 'text-rose-600' : 'text-emerald-600'">
                        المتبقي / النقص: <span class="font-mono" x-text="remainingParcels.length"></span>
                    </span>
                </div>
                <div class="w-full h-2 rounded-full bg-slate-200 overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-300"
                        :class="remainingParcels.length === 0 ? 'bg-emerald-500' : 'bg-secondary'"
                        :style="{ width: progressPercent + '%' }"></div>
                </div>
            </div>

            {{-- إجماليات طرود فرعك --}}
            <div class="grid grid-cols-3 gap-1.5 text-center">
                <div class="bg-amber-50/70 border border-amber-200/70 rounded-xl py-1.5 px-1">
                    <p class="text-[9px] font-bold text-amber-700">الآجل (للتحصيل)</p>
                    <p class="text-xs font-mono font-black text-amber-900" x-text="formatMoney(totals.deferred) + ' ر.ي'">
                    </p>
                </div>
                <div class="bg-emerald-50/70 border border-emerald-200/70 rounded-xl py-1.5 px-1">
                    <p class="text-[9px] font-bold text-emerald-700">المحاسب</p>
                    <p class="text-xs font-mono font-black text-emerald-900" x-text="formatMoney(totals.paid) + ' ر.ي'"></p>
                </div>
                <div class="bg-primary text-white rounded-xl py-1.5 px-1">
                    <p class="text-[9px] font-bold text-slate-300">الإجمالي</p>
                    <p class="text-xs font-mono font-black text-secondary" x-text="formatMoney(totals.grand) + ' ر.ي'"></p>
                </div>
            </div>
        </div>

        {{-- لوحة الاستلام السريع بالباركود + تحديد قناة الإشعار --}}
        <div x-show="remainingParcels.length > 0"
            class="bg-white border-2 border-primary/15 rounded-2xl p-3.5 shadow-sm space-y-3">

            {{-- 1. اختيار قناة إشعار العميل عند كل مسح --}}
            <div class="space-y-1.5">
                <label class="text-[11px] font-extrabold text-primary flex items-center gap-1">
                    <span class="material-symbols-outlined text-base text-secondary">notifications_active</span>
                    <span>قناة إشعار المستلم فور المسح والاستلام:</span>
                </label>

                <div class="grid grid-cols-3 gap-1.5 bg-slate-100 p-1 rounded-xl">
                    <button type="button" @click="setChannel('both')"
                        :class="notifyChannel === 'both' ? 'bg-primary text-secondary shadow-xs font-extrabold' :
                            'text-slate-600 hover:text-primary font-bold'"
                        class="py-2 rounded-lg text-[11px] flex items-center justify-center gap-1 transition-all">
                        <span class="material-symbols-outlined text-sm">forum</span>
                        <span>معاً</span>
                    </button>
                    <button type="button" @click="setChannel('sms')"
                        :class="notifyChannel === 'sms' ? 'bg-sky-600 text-white shadow-xs font-extrabold' :
                            'text-slate-600 hover:text-primary font-bold'"
                        class="py-2 rounded-lg text-[11px] flex items-center justify-center gap-1 transition-all">
                        <span class="material-symbols-outlined text-sm">sms</span>
                        <span>SMS فقط</span>
                    </button>
                    <button type="button" @click="setChannel('whatsapp')"
                        :class="notifyChannel === 'whatsapp' ? 'bg-emerald-600 text-white shadow-xs font-extrabold' :
                            'text-slate-600 hover:text-primary font-bold'"
                        class="py-2 rounded-lg text-[11px] flex items-center justify-center gap-1 transition-all">
                        <span class="material-symbols-outlined text-sm">chat</span>
                        <span>واتساب فقط</span>
                    </button>
                </div>
            </div>

            {{-- 2. الكاميرا وقارئ الباركود --}}
            <div class="space-y-2 pt-1 border-t border-slate-100">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs font-extrabold text-slate-700 flex items-center gap-1">
                        <span class="material-symbols-outlined text-base text-secondary">qr_code_scanner</span>
                        <span>امسح باركود كل طرد للاستلام المباشر</span>
                    </span>
                    <button type="button" @click="toggleCamera()"
                        :class="cameraActive ? 'bg-rose-600 text-white' : 'bg-primary text-secondary'"
                        class="px-2.5 py-1.5 rounded-xl text-[11px] font-extrabold flex items-center gap-1 transition-colors">
                        <span class="material-symbols-outlined text-sm"
                            x-text="cameraActive ? 'videocam_off' : 'photo_camera'"></span>
                        <span x-text="cameraActive ? 'إيقاف الكاميرا' : 'فتح الكاميرا'"></span>
                    </button>
                </div>

                <div x-show="cameraActive" x-cloak class="rounded-xl overflow-hidden border border-slate-300 bg-black">
                    <div id="receive-shipment-qr-reader" class="w-full"></div>
                </div>

                <div class="flex gap-1.5">
                    <input type="text" x-ref="scanInput" x-model="scanInput"
                        @keydown.enter.prevent="scanAndReceive(scanInput)" dir="ltr" autocomplete="off"
                        placeholder="امسح بالباركود أو اكتب رقم السند ثم Enter"
                        class="flex-1 py-2.5 px-3 text-xs font-mono font-bold text-slate-800 rounded-xl border border-slate-300 bg-slate-50 focus:bg-white outline-none focus:border-secondary">
                    <button type="button" @click="scanAndReceive(scanInput)" :disabled="isProcessing"
                        class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-extrabold disabled:opacity-50 shrink-0">
                        <span x-text="isProcessing ? '...' : 'استلام'"></span>
                    </button>
                </div>

                {{-- شريط نتيجة المسح الفوري --}}
                <div x-show="scanFeedback.text" x-cloak
                    :class="{
                        'bg-emerald-50 border-emerald-200 text-emerald-800': scanFeedback.type === 'success',
                        'bg-amber-50 border-amber-300 text-amber-900': scanFeedback.type === 'duplicate',
                        'bg-rose-50 border-rose-200 text-rose-800': scanFeedback.type === 'error'
                    }"
                    class="px-3 py-2 rounded-xl border text-[11px] font-extrabold flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-base shrink-0"
                        x-text="scanFeedback.type === 'success' ? 'check_circle' : (scanFeedback.type === 'duplicate' ? 'block' : 'error')"></span>
                    <span x-text="scanFeedback.text"></span>
                </div>
            </div>
        </div>

        {{-- رسالة اكتمال الاستلام بدون أي نقص --}}
        <div x-show="remainingParcels.length === 0" x-cloak
            class="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 text-center space-y-2">
            <span class="material-symbols-outlined text-4xl text-emerald-600">verified</span>
            <h3 class="text-sm font-extrabold text-emerald-900">تم استلام جميع طرود هذه الشحنة بالكامل بدون نقص</h3>
            <p class="text-[11px] text-emerald-700">تم تحويل كافة الطرود إلى قائمة «الطرود المستلمة» وإطلاق إشعارات
                العملاء.</p>
            <div class="pt-1">
                <a href="{{ route('office.parcels.index') }}"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-primary text-secondary text-xs font-extrabold">
                    <span class="material-symbols-outlined text-sm">inventory_2</span>
                    <span>الانتقال إلى الطرود المستلمة</span>
                </a>
            </div>
        </div>

        {{-- قسم 1: الطرود المتبقية (لم تُمسح بعد — لكشف النقص بوضوح) --}}
        <div x-show="remainingParcels.length > 0" class="space-y-2">
            <div class="flex items-center justify-between px-1">
                <h3 class="text-xs font-extrabold text-rose-700 flex items-center gap-1">
                    <span class="material-symbols-outlined text-base">pending_actions</span>
                    <span>طرود بانتظار المسح / فحص النقص (<span x-text="remainingParcels.length"></span>)</span>
                </h3>
            </div>

            <template x-for="p in remainingParcels" :key="p.id">
                <article class="bg-white border border-rose-200/90 rounded-2xl p-3 shadow-xs space-y-2 text-xs">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span
                                    class="px-1.5 py-0.5 rounded bg-slate-100 border border-slate-200 font-mono text-[10px] font-black text-primary"
                                    x-text="'سند #' + p.receipt_number"></span>
                                <span class="font-extrabold text-slate-800" x-text="p.package_type"></span>
                            </div>
                            <p class="text-[11px] text-slate-600 font-bold mt-1">
                                المستلم: <span x-text="p.recipient_name"></span>
                                <span class="font-mono text-slate-400 mr-1" dir="ltr"
                                    x-text="'(' + p.recipient_phone + ')'"></span>
                            </p>
                        </div>

                        <span class="px-2 py-0.5 rounded-md border text-[10px] font-extrabold shrink-0"
                            :class="p.payment_type === 'deferred' ? 'bg-amber-50 text-amber-800 border-amber-200' :
                                'bg-emerald-50 text-emerald-700 border-emerald-200'"
                            x-text="p.payment_label + ': ' + formatMoney(p.cod_amount) + ' ر.ي'"></span>
                    </div>

                    <template x-if="p.notes">
                        <p class="text-[10px] text-amber-900 bg-amber-50/70 border border-amber-100 rounded-lg px-2 py-1"
                            x-text="'ملاحظة: ' + p.notes"></p>
                    </template>

                    <div class="flex items-center justify-between gap-2 pt-1.5 border-t border-slate-100">
                        <span class="text-[10px] font-bold text-rose-500 flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">qr_code_2</span>
                            <span>بانتظار مسح الملصق</span>
                        </span>

                        <button type="button" @click="openManualModal(p)" :disabled="isProcessing"
                            class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-emerald-600 hover:text-white text-slate-700 text-[11px] font-extrabold flex items-center gap-1 transition-colors disabled:opacity-50">
                            <span class="material-symbols-outlined text-sm">download_done</span>
                            <span>استلام يدوي</span>
                        </button>
                    </div>
                </article>
            </template>
        </div>

        {{-- قسم 2: الطرود التي تم مسحها واستلامها بنجاح --}}
        <div x-show="receivedParcels.length > 0" class="space-y-2 pt-1">
            <h3 class="text-xs font-extrabold text-emerald-700 px-1 flex items-center gap-1">
                <span class="material-symbols-outlined text-base">check_circle</span>
                <span>طرود تم استلامها (<span x-text="receivedParcels.length"></span>)</span>
            </h3>

            <template x-for="p in receivedParcels" :key="p.id">
                <article
                    class="bg-emerald-50/40 border border-emerald-200/80 rounded-2xl p-3 text-xs flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span
                                class="px-1.5 py-0.5 rounded bg-white border border-emerald-200 font-mono text-[10px] font-black text-emerald-800"
                                x-text="'سند #' + p.receipt_number"></span>
                            <span class="font-bold text-slate-700 truncate" x-text="p.package_type"></span>
                        </div>
                        <p class="text-[10px] text-slate-500 font-semibold mt-0.5 truncate"
                            x-text="p.recipient_name + ' • ' + p.recipient_phone + ' • ' + p.payment_label + ': ' + formatMoney(p.cod_amount) + ' ر.ي'">
                        </p>
                    </div>
                    <span class="px-2 py-0.5 rounded-md bg-emerald-600 text-white text-[10px] font-extrabold shrink-0">
                        تم الاستلام ✓
                    </span>
                </article>
            </template>
        </div>

        {{-- مودال تأكيد الاستلام اليدوي (في حال تلف الباركود — بدون backdrop-blur للأداء) --}}
        <div x-show="manualModal.open" x-cloak @keydown.escape.window="closeManualModal()"
            class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-3 sm:p-4">
            <div class="fixed inset-0 bg-slate-950/60" @click="closeManualModal()"></div>

            <div
                class="relative w-full max-w-sm bg-white rounded-3xl p-5 shadow-2xl border border-slate-100 z-10 flex flex-col gap-3.5 text-right">
                <div class="text-center">
                    <div
                        class="w-12 h-12 mx-auto rounded-2xl bg-emerald-50 border border-emerald-100 text-emerald-600 flex items-center justify-center mb-2">
                        <span class="material-symbols-outlined text-2xl">download_done</span>
                    </div>
                    <h3 class="text-sm font-extrabold text-slate-800">تأكيد الاستلام اليدوي</h3>
                    <p class="text-[11px] text-slate-500 mt-0.5">في حال تعذر قراءة الباركود، يمكنك تأكيد استلام هذا الطرد
                        يدوياً.</p>
                </div>

                <template x-if="manualModal.parcel">
                    <div class="bg-slate-50 rounded-2xl p-3 border border-slate-200/80 space-y-1.5 text-xs">
                        <div class="flex justify-between">
                            <span class="text-slate-400">رقم السند:</span>
                            <span class="font-mono font-black text-primary"
                                x-text="'#' + manualModal.parcel.receipt_number"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">نوع الطرد:</span>
                            <span class="font-bold text-slate-700" x-text="manualModal.parcel.package_type"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">المستلم:</span>
                            <span class="font-bold text-slate-700"
                                x-text="manualModal.parcel.recipient_name + ' (' + manualModal.parcel.recipient_phone + ')'"></span>
                        </div>
                        <div class="flex justify-between pt-1 border-t border-slate-200/60">
                            <span class="text-slate-400">قناة الإشعار:</span>
                            <span class="font-extrabold text-emerald-700" x-text="channelLabel(notifyChannel)"></span>
                        </div>
                    </div>
                </template>

                <div class="flex flex-col gap-2">
                    <button type="button" @click="confirmManualReceive()" :disabled="isProcessing"
                        class="w-full py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-extrabold flex items-center justify-center gap-1.5 disabled:opacity-50">
                        <span class="material-symbols-outlined text-base">check_circle</span>
                        <span x-text="isProcessing ? 'جاري الاستلام...' : 'تأكيد الاستلام وإشعار العميل'"></span>
                    </button>
                    <button type="button" @click="closeManualModal()" :disabled="isProcessing"
                        class="w-full py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold">
                        تراجع
                    </button>
                </div>
            </div>
        </div>

        {{-- نافذة تأكيد رفض الكشف الخارجي --}}
        @if (!empty($canRejectExternal))
            <div x-show="rejectModalOpen && receivedParcels.length === 0"
                 x-cloak
                 @keydown.escape.window="rejectModalOpen = false"
                 class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-3 sm:p-4">
                <div class="fixed inset-0 bg-slate-950/60" @click="rejectModalOpen = false"></div>

                <div class="relative w-full max-w-sm bg-white rounded-3xl p-5 shadow-2xl border border-slate-100 z-10 space-y-4 text-right">
                    <div class="text-center space-y-1">
                        <div class="w-12 h-12 mx-auto rounded-2xl bg-rose-50 border border-rose-200 text-rose-600 flex items-center justify-center">
                            <span class="material-symbols-outlined text-2xl">cancel</span>
                        </div>
                        <h3 class="text-sm font-extrabold text-slate-800">رفض الكشف الخارجي #{{ $shipment->shipment_number }}؟</h3>
                        <p class="text-[11px] font-bold text-slate-500">المرسل: {{ $shipment->senderOfficeLabel() }}</p>
                    </div>

                    <form method="POST" action="{{ route('office.shipments.received.reject', $shipment) }}" class="space-y-3 m-0">
                        @csrf
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-700 mb-1">سبب الرفض (اختياري)</label>
                            <input type="text"
                                   name="rejection_reason"
                                   maxlength="255"
                                   placeholder="مثال: كشف مكرر أو أُرسل بالخطأ..."
                                   class="w-full rounded-xl border border-slate-300 bg-slate-50 focus:bg-white focus:border-rose-400 px-3 py-2 text-xs font-bold text-slate-800 outline-none">
                        </div>

                        <div class="flex flex-col gap-2">
                            <button type="submit"
                                    class="w-full py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-extrabold flex items-center justify-center gap-1.5">
                                <span class="material-symbols-outlined text-base">cancel</span>
                                <span>تأكيد رفض الكشف واستبعاده</span>
                            </button>
                            <button type="button"
                                    @click="rejectModalOpen = false"
                                    class="w-full py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold">
                                تراجع
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </main>
@endsection

@push('scripts')
    <script defer src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
        function receiveShipmentPage(config) {
            const savedChannel = localStorage.getItem('mursal_shipment_receive_channel');
            const initialChannel = ['sms', 'whatsapp', 'both'].includes(savedChannel) ? savedChannel : 'both';

            return {
                parcels: Array.isArray(config.parcels) ? config.parcels : [],
                receiveUrl: config.receiveUrl,
                notifyChannel: initialChannel,

                scanInput: '',
                isProcessing: false,
                scanFeedback: {
                    type: '',
                    text: ''
                },

                cameraActive: false,
                _qrScanner: null,
                _lastScannedCode: '',
                _lastScannedAt: 0,

                manualModal: {
                    open: false,
                    parcel: null
                },
                canRejectExternal: Boolean(config.canRejectExternal),
                rejectModalOpen: false,

                init() {
                    window.dispatchEvent(new CustomEvent('shipments-pending-updated', {
                        detail: {
                            count: Number(config.pendingShipmentsCount || 0)
                        }
                    }));
                    this.$nextTick(() => this.$refs.scanInput?.focus());
                },

                setChannel(ch) {
                    this.notifyChannel = ch;
                    localStorage.setItem('mursal_shipment_receive_channel', ch);
                },

                channelLabel(ch) {
                    if (ch === 'sms') return 'SMS فقط';
                    if (ch === 'whatsapp') return 'واتساب فقط';
                    return 'SMS + واتساب';
                },

                formatMoney(n) {
                    return Number(n || 0).toLocaleString('en-US');
                },

                get remainingParcels() {
                    return this.parcels.filter(p => !p.received);
                },

                get receivedParcels() {
                    return this.parcels.filter(p => p.received);
                },

                get progressPercent() {
                    if (this.parcels.length === 0) return 100;
                    return Math.round((this.receivedParcels.length / this.parcels.length) * 100);
                },

                get totals() {
                    let paid = 0,
                        deferred = 0;
                    this.parcels.forEach(p => {
                        const amt = Number(p.cod_amount || 0);
                        if (p.payment_type === 'deferred') deferred += amt;
                        else paid += amt;
                    });
                    return {
                        paid,
                        deferred,
                        grand: paid + deferred
                    };
                },

                notifyToast(message, type = 'success') {
                    if (!message) return;
                    window.dispatchEvent(new CustomEvent('office-toast', {
                        detail: { message, type }
                    }));
                },

                beep(type) {
                    try {
                        const AudioCtx = window.AudioContext || window.webkitAudioContext;
                        if (!AudioCtx) return;
                        const ctx = new AudioCtx();
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        osc.connect(gain);
                        gain.connect(ctx.destination);

                        if (type === 'success') {
                            osc.frequency.value = 960;
                            gain.gain.value = 0.14;
                            osc.start();
                            osc.stop(ctx.currentTime + 0.12);
                            if (navigator.vibrate) navigator.vibrate(55);
                        } else {
                            osc.frequency.value = 220;
                            osc.type = 'sawtooth';
                            gain.gain.value = 0.15;
                            osc.start();
                            osc.stop(ctx.currentTime + 0.25);
                            if (navigator.vibrate) navigator.vibrate([90, 40, 90]);
                        }
                    } catch (e) {}
                },

                normalizeCode(raw) {
                    const ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
                    const fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
                    let clean = String(raw || '')
                        .replace(/[٠-٩]/g, d => ar.indexOf(d))
                        .replace(/[۰-۹]/g, d => fa.indexOf(d))
                        .trim();
                    const m = clean.match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i);
                    return m ? m[0].toLowerCase() : clean.replace(/^#/, '');
                },

                async toggleCamera() {
                    if (this.cameraActive) {
                        await this.stopCamera();
                    } else {
                        await this.startCamera();
                    }
                },

                async startCamera() {
                    if (typeof Html5Qrcode === 'undefined') {
                        this.scanFeedback = {
                            type: 'error',
                            text: 'تعذر تحميل مكتبة الكاميرا، تحقق من الاتصال بالإنترنت.'
                        };
                        return;
                    }
                    this.cameraActive = true;
                    this.$nextTick(async () => {
                        try {
                            this._qrScanner = new Html5Qrcode('receive-shipment-qr-reader');
                            await this._qrScanner.start({
                                    facingMode: 'environment'
                                }, {
                                    fps: 10,
                                    qrbox: {
                                        width: 230,
                                        height: 150
                                    }
                                },
                                (decodedText) => {
                                    const now = Date.now();
                                    if (decodedText === this._lastScannedCode && (now - this
                                            ._lastScannedAt) < 2200) {
                                        return;
                                    }
                                    this._lastScannedCode = decodedText;
                                    this._lastScannedAt = now;
                                    this.scanAndReceive(decodedText);
                                },
                                () => {}
                            );
                        } catch (err) {
                            this.cameraActive = false;
                            this.scanFeedback = {
                                type: 'error',
                                text: 'تعذر فتح الكاميرا، يمكنك استخدام قارئ الباركود أو الإدخال اليدوي.'
                            };
                        }
                    });
                },

                async stopCamera() {
                    if (this._qrScanner) {
                        try {
                            await this._qrScanner.stop();
                            await this._qrScanner.clear();
                        } catch (e) {}
                        this._qrScanner = null;
                    }
                    this.cameraActive = false;
                },

                openManualModal(parcel) {
                    this.manualModal.parcel = parcel;
                    this.manualModal.open = true;
                },

                closeManualModal() {
                    if (this.isProcessing) return;
                    this.manualModal.open = false;
                    this.manualModal.parcel = null;
                },

                async confirmManualReceive() {
                    if (!this.manualModal.parcel) return;
                    const id = this.manualModal.parcel.id;
                    const ok = await this.scanAndReceive(id);
                    if (ok) {
                        this.closeManualModal();
                    }
                },

                async scanAndReceive(rawCode) {
                    if (this.isProcessing) return false;
                    const code = this.normalizeCode(rawCode);
                    this.scanInput = '';
                    if (!code) return false;

                    // فحص محلي فوري لمنع تكرار المسح
                    const already = this.receivedParcels.find(
                        p => p.id.toLowerCase() === code || String(p.receipt_number) === code
                    );
                    if (already) {
                        this.beep('error');
                        const dupMsg = 'تم مسح واستلام هذا الطرد مسبقاً (سند #' + already.receipt_number + ') — مُنع التكرار.';
                        this.scanFeedback = {
                            type: 'duplicate',
                            text: dupMsg
                        };
                        this.notifyToast(dupMsg, 'warning');
                        return false;
                    }

                    if (typeof window.requireOnlineAction === 'function' && !window.requireOnlineAction()) {
                        this.beep('error');
                        this.scanFeedback = {
                            type: 'error',
                            text: 'لا يوجد اتصال بالإنترنت، يرجى الاتصال بالإنترنت والمحاولة مجدداً.'
                        };
                        return false;
                    }

                    this.isProcessing = true;
                    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';

                    try {
                        const res = await fetch(this.receiveUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': token,
                            },
                            body: JSON.stringify({
                                code: code,
                                channel: this.notifyChannel,
                            })
                        });

                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || !data.ok) {
                            this.beep('error');
                            const errMsg = data.message || 'تعذر استلام الطرد.';
                            this.scanFeedback = {
                                type: 'error',
                                text: errMsg
                            };
                            this.notifyToast(errMsg, 'error');
                            return false;
                        }

                        // تحديث حالة الطرد لحظياً في الواجهة
                        const target = this.parcels.find(p => p.id === data.parcel_id);
                        if (target) {
                            target.received = true;
                            target.status = 'received';
                        }

                        this.beep('success');
                        const okMsg = data.message || 'تم الاستلام وإرسال الإشعار بنجاح.';
                        this.scanFeedback = {
                            type: 'success',
                            text: okMsg
                        };
                        this.notifyToast(okMsg, 'success');

                        if (typeof data.pending_shipments_count === 'number') {
                            window.dispatchEvent(new CustomEvent('shipments-pending-updated', {
                                detail: {
                                    count: data.pending_shipments_count
                                }
                            }));
                        }

                        if (this.remainingParcels.length === 0) {
                            await this.stopCamera();
                        }

                        return true;
                    } catch (err) {
                        this.beep('error');
                        const netErr = !navigator.onLine
                            ? 'لا يوجد اتصال بالإنترنت، يرجى المحاولة مجدداً.'
                            : 'تعذر الاتصال بالخادم، يرجى التأكد من الاتصال بالإنترنت والمحاولة مجدداً.';
                        this.scanFeedback = {
                            type: 'error',
                            text: netErr
                        };
                        if (typeof window.notifyNetworkError === 'function') {
                            window.notifyNetworkError('تعذر الاتصال بالخادم، يرجى التأكد من الاتصال بالإنترنت والمحاولة مجدداً.');
                        } else {
                            this.notifyToast(netErr, 'error');
                        }
                        return false;
                    } finally {
                        this.isProcessing = false;
                        this.$nextTick(() => this.$refs.scanInput?.focus());
                    }
                }
            };
        }
    </script>
@endpush
