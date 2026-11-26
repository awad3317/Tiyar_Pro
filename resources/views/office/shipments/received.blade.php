@extends('office.layouts.app')

@section('title', 'الشحنات المستلمة')

@section('shell-class', 'bg-slate-50')

@section('content')
    <div class="flex-1 flex flex-col" x-data="{
        rejectModal: {
            open: false,
            shipmentNumber: '',
            senderLabel: '',
            actionUrl: '',
            reason: ''
        },
        openRejectModal(number, sender, url) {
            this.rejectModal.shipmentNumber = number;
            this.rejectModal.senderLabel = sender;
            this.rejectModal.actionUrl = url;
            this.rejectModal.reason = '';
            this.rejectModal.open = true;
        },
        init() {
            window.dispatchEvent(new CustomEvent('shipments-pending-updated', {
                detail: { count: {{ (int) $pendingReceivedShipmentsCount }} }
            }));
        }
    }">
        {{-- شريط تبويبات التصفية العلوي الموحّد (مطابق تماماً لشاشة الطرود الواردة) --}}
        @php
            $receivedTabs = [
                ['key' => 'all',       'label' => 'الكل'],
                ['key' => 'pending',   'label' => 'بانتظار الاستلام'],
                ['key' => 'completed', 'label' => 'مكتملة'],
            ];
        @endphp
        <nav class="flex overflow-x-auto no-scrollbar border-b border-slate-100 bg-white sticky top-[68px] z-20 text-xs font-bold text-center">
            @foreach ($receivedTabs as $tabItem)
                <a href="{{ route('office.shipments.received.index', ['tab' => $tabItem['key']]) }}"
                   @click="if (typeof window.requireOnlineAction === 'function' && !window.requireOnlineAction()) $event.preventDefault()"
                   class="flex-1 shrink-0 whitespace-nowrap px-3 py-3 transition-colors {{ $tab === $tabItem['key'] ? 'border-b-2 border-secondary text-secondary' : 'text-slate-500' }}">
                    <span>{{ $tabItem['label'] }}</span>
                    (<span>{{ $receivedCounts[$tabItem['key']] ?? 0 }}</span>)
                </a>
            @endforeach
        </nav>

        <main class="flex-1 p-3 lg:p-6 space-y-2.5">
            {{-- قائمة الشحنات القادمة --}}
            <div class="space-y-2.5 lg:space-y-0 lg:grid lg:grid-cols-2 2xl:grid-cols-3 lg:gap-4">
            @forelse ($filteredShipments as $shipment)
                @php
                    $total = (int) $shipment->office_parcels_total;
                    $received = (int) $shipment->office_parcels_received;
                    $pending = (int) $shipment->office_parcels_pending;
                    $percent = $total > 0 ? (int) round(($received / $total) * 100) : 0;
                    $dispatchedAgo = $shipment->dispatched_at?->copy()->locale('ar')->diffForHumans();
                @endphp

                <a href="{{ route('office.shipments.received.show', $shipment) }}"
                    @click="if (typeof window.requireOnlineAction === 'function' && !window.requireOnlineAction()) $event.preventDefault()"
                    style="content-visibility: auto; contain-intrinsic-size: 185px;"
                    class="block bg-white border {{ $pending > 0 ? 'border-secondary/60' : 'border-slate-200/90' }} hover:border-secondary rounded-2xl p-3.5 shadow-xs space-y-2.5 transition-colors">

                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span
                                class="w-9 h-9 rounded-xl {{ $pending > 0 ? 'bg-primary text-secondary' : 'bg-emerald-50 text-emerald-600' }} flex items-center justify-center shrink-0">
                                <span
                                    class="material-symbols-outlined text-xl">{{ $pending > 0 ? 'qr_code_scanner' : 'task_alt' }}</span>
                            </span>
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="font-mono font-black text-sm text-primary">كشف
                                        #{{ $shipment->shipment_number }}</span>
                                    <span
                                        class="px-1.5 py-0.5 rounded-md bg-slate-100 text-slate-700 text-[10px] font-bold">
                                        {{ $total }} طرد لفرعك
                                    </span>
                                    @if ($shipment->office_id === null && $shipment->external_partner_link_id !== null)
                                        <span class="px-1.5 py-0.5 rounded-md bg-violet-50 text-violet-700 border border-violet-200 text-[10px] font-extrabold">
                                            مكتب خارجي
                                        </span>
                                    @endif
                                </div>
                                <p class="text-[10px] text-slate-400 font-semibold mt-0.5 truncate">
                                    من: <span class="text-slate-600 font-bold">{{ $shipment->senderOfficeLabel() }}</span>
                                    @if ($dispatchedAgo)
                                        • {{ $dispatchedAgo }}
                                    @endif
                                </p>
                            </div>
                        </div>

                        @if ($pending > 0)
                            <span
                                class="px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-200 text-[10px] font-extrabold shrink-0">
                                متبقي {{ $pending }}
                            </span>
                        @else
                            <span
                                class="px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-extrabold shrink-0">
                                مكتملة ✓
                            </span>
                        @endif
                    </div>

                    {{-- السائق --}}
                    <div
                        class="flex items-center justify-between bg-slate-50 rounded-xl px-3 py-2 border border-slate-100 text-xs">
                        <span class="text-slate-400 text-[11px] font-bold flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm text-primary">person</span>
                            السائق: <strong class="text-slate-800">{{ $shipment->driver_name }}</strong>
                        </span>
                        <span class="font-mono text-[11px] font-bold text-slate-600"
                            dir="ltr">{{ $shipment->driver_phone }}</span>
                    </div>

                    {{-- شريط التقدم (المستلم مقابل المتبقي) --}}
                    <div class="space-y-1">
                        <div class="flex items-center justify-between text-[10px] font-bold">
                            <span class="text-slate-500">حالة الفحص والاستلام: {{ $received }} من
                                {{ $total }}</span>
                            <span
                                class="{{ $pending > 0 ? 'text-amber-700' : 'text-emerald-600' }} font-mono">{{ $percent }}%</span>
                        </div>
                        <div class="w-full h-1.5 rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full rounded-full transition-all {{ $pending > 0 ? 'bg-secondary' : 'bg-emerald-500' }}"
                                style="width: {{ $percent }}%"></div>
                        </div>
                    </div>

                    {{-- المجاميع الخاصة بطرود هذا الفرع فقط --}}
                    <div class="grid grid-cols-3 gap-1.5 text-center">
                        <div class="bg-amber-50/70 border border-amber-200/70 rounded-xl py-1.5 px-1">
                            <p class="text-[9px] font-bold text-amber-700">الآجل</p>
                            <p class="text-xs font-mono font-black text-amber-800">
                                {{ number_format((float) $shipment->office_deferred_total) }}</p>
                        </div>
                        <div class="bg-emerald-50/70 border border-emerald-200/70 rounded-xl py-1.5 px-1">
                            <p class="text-[9px] font-bold text-emerald-700">المحاسب</p>
                            <p class="text-xs font-mono font-black text-emerald-800">
                                {{ number_format((float) $shipment->office_paid_total) }}</p>
                        </div>
                        <div class="bg-primary text-white rounded-xl py-1.5 px-1">
                            <p class="text-[9px] font-bold text-slate-300">الإجمالي</p>
                            <p class="text-xs font-mono font-black text-secondary">
                                {{ number_format((float) $shipment->office_grand_total) }}</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-2 pt-0.5">
                        @if (!empty($shipment->can_reject_external))
                            <button type="button"
                                    data-reject-shipment="{{ $shipment->id }}"
                                    @click.prevent.stop="openRejectModal(@js($shipment->shipment_number), @js($shipment->senderOfficeLabel()), @js(route('office.shipments.received.reject', $shipment)))"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-[11px] font-extrabold transition-colors">
                                <span class="material-symbols-outlined text-sm">cancel</span>
                                <span>رفض الكشف</span>
                            </button>
                        @else
                            <span></span>
                        @endif

                        <div class="flex items-center text-[11px] font-extrabold text-primary">
                            <span>{{ $pending > 0 ? 'فتح الفحص والمسح بالباركود' : 'عرض تفاصيل الكشف' }}</span>
                            <span class="material-symbols-outlined text-base text-secondary">chevron_left</span>
                        </div>
                    </div>
                </a>
            @empty
                <div class="lg:col-span-full bg-white rounded-2xl border border-slate-200/80 p-10 text-center space-y-2">
                    <div
                        class="w-14 h-14 mx-auto rounded-2xl bg-emerald-50 border border-emerald-100 text-emerald-500 flex items-center justify-center">
                        <span class="material-symbols-outlined text-3xl">task_alt</span>
                    </div>
                    <p class="text-xs font-extrabold text-slate-700">
                        {{ $tab === 'pending' ? 'لا توجد شحنات بانتظار الاستلام حالياً' : 'لا توجد شحنات مسجلة في هذا القسم' }}
                    </p>
                    <p class="text-[11px] text-slate-400">عند شحن أي كشف من الفروع الشريكة سيظهر هنا مباشرة مع إشعار تنبيهي.
                    </p>
                </div>
            @endforelse
            </div>
        </main>

        {{-- نافذة تأكيد رفض الكشف الخارجي --}}
        <div x-show="rejectModal.open"
             x-cloak
             @keydown.escape.window="rejectModal.open = false"
             class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-3 sm:p-4">
            <div class="fixed inset-0 bg-slate-950/60" @click="rejectModal.open = false"></div>

            <div class="relative w-full max-w-sm bg-white rounded-3xl p-5 shadow-2xl border border-slate-100 z-10 space-y-4 text-right">
                <div class="text-center space-y-1">
                    <div class="w-12 h-12 mx-auto rounded-2xl bg-rose-50 border border-rose-200 text-rose-600 flex items-center justify-center">
                        <span class="material-symbols-outlined text-2xl">cancel</span>
                    </div>
                    <h3 class="text-sm font-extrabold text-slate-800">رفض الكشف الخارجي #<span class="font-mono" x-text="rejectModal.shipmentNumber"></span>؟</h3>
                    <p class="text-[11px] font-bold text-slate-500" x-text="'المرسل: ' + rejectModal.senderLabel"></p>
                </div>

                <form method="POST" :action="rejectModal.actionUrl" class="space-y-3 m-0">
                    @csrf
                    <div>
                        <label class="block text-[11px] font-extrabold text-slate-700 mb-1">سبب الرفض (اختياري)</label>
                        <input type="text"
                               name="rejection_reason"
                               x-model="rejectModal.reason"
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
                                @click="rejectModal.open = false"
                                class="w-full py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold">
                            تراجع
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
