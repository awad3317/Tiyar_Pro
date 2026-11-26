@extends('office.layouts.app')

@section('title', 'طرود قادمة في الطريق')

@section('shell-class', 'bg-slate-50')

@section('header-search')
    <div x-data="{
            q: '',
            normDigits(str) {
                const ar = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
                const fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
                return String(str || '')
                    .replace(/[٠-٩]/g, d => ar.indexOf(d))
                    .replace(/[۰-۹]/g, d => fa.indexOf(d))
                    .replace(/[\u200B-\u200D\uFEFF\u200E\u200F]/g, '');
            },
            emit() {
                this.q = this.normDigits(this.q);
                window.dispatchEvent(new CustomEvent('transit-search-change', { detail: { q: this.q } }));
            },
            clear() {
                this.q = '';
                this.emit();
            }
        }"
        @transit-search-clear.window="clear()"
        class="mt-3 relative">
        <input type="text"
               x-model="q"
               @input="emit()"
               dir="rtl"
               autocomplete="off"
               placeholder="بحث بالهاتف، السند، رقم الكشف، السائق، الفرع..."
               class="w-full bg-slate-800 text-white placeholder-slate-400 text-xs rounded-xl pr-9 pl-8 py-2.5 outline-none border border-slate-700 focus:border-secondary transition-all">
        <span class="material-symbols-outlined absolute right-2.5 top-2.5 text-slate-400 text-lg pointer-events-none">search</span>

        <template x-if="q.length > 0">
            <button type="button"
                    @click="clear()"
                    title="مسح البحث"
                    class="absolute left-2.5 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full bg-slate-700 hover:bg-rose-500/20 text-slate-300 hover:text-rose-400 flex items-center justify-center text-xs font-bold transition-colors">
                ✕
            </button>
        </template>
    </div>
@endsection

@section('content')
    @php
        $officeUser = $office ?? auth('office')->user();
        $hasSlotFeature = $officeUser && $officeUser->hasFeature('parcel_slot_number');

        $transitPayloads = [];
        foreach ($transitParcels as $p) {
            $officeLabel = $p->senderOfficeLabel();

            $paymentEnum = $p->payment_type instanceof \App\Enums\OutgoingPaymentType
                ? $p->payment_type
                : (\App\Enums\OutgoingPaymentType::tryFrom((string) $p->payment_type) ?? \App\Enums\OutgoingPaymentType::PAID);

            $dispatchedAt = $p->dispatched_at;
            $dispatchedAgo = $dispatchedAt ? $dispatchedAt->copy()->locale('ar')->diffForHumans() : null;
            $dispatchedClock = $dispatchedAt
                ? $dispatchedAt->format('g:i') . ' ' . ($dispatchedAt->format('A') === 'AM' ? 'ص' : 'م')
                : null;

            // $shipment = $p->shipment;

            // $transitPayloads[(string) $p->id] = [
            //     'id'               => (string) $p->id,
            //     'url'              => route('office.transit-parcels.receive', $p),
            //     'package_type'     => (string) $p->package_type,
            //     'receipt_number'   => (string) ($p->receipt_number ?? ''),
            //     'source_office'    => $officeLabel,
            //     'shipment_id'      => $shipment ? (string) $shipment->id : '',
            //     'shipment_number'  => $shipment ? (string) $shipment->shipment_number : '',
            //     'driver_name'      => $shipment ? (string) $shipment->driver_name : '',
            //     'driver_phone'     => $shipment ? (string) $shipment->driver_phone : '',
            //     'payment_label'    => $paymentEnum->label(),
            //     'is_deferred'      => $paymentEnum->value === 'deferred',
            //     'cod_amount'       => number_format((float) ($p->cod_amount ?? 0)),
            //     'recipient_name'   => (string) ($p->recipient_name ?: 'بدون اسم'),
            //     'recipient_phone'  => (string) $p->recipient_phone,
            //     'sender_name'      => (string) $p->sender_name,
            //     'sender_phone'     => (string) $p->sender_phone,
            //     'notes'            => (string) ($p->notes ?? ''),
            //     'dispatched_ago'   => $dispatchedAgo,
            //     'dispatched_clock' => $dispatchedClock,
            //     'search_text'      => implode(' ', array_filter([
            //         $p->package_type,
            //         $p->receipt_number,
            //         $p->recipient_name,
            //         $p->recipient_phone,
            //         $p->sender_name,
            //         $p->sender_phone,
            //         $officeLabel,
            //         $shipment?->shipment_number,
            //         $shipment?->driver_name,
            //         $shipment?->driver_phone,
            //         $p->notes,
            //     ])),
            // ];
            $shipmentId     = (string) ($p->shipment_id ?? '');
            $shipmentNumber = (string) ($p->shipment_number ?? '');
            $driverName     = (string) ($p->shipment_driver_name ?? '');
            $driverPhone    = (string) ($p->shipment_driver_phone ?? '');

            $transitPayloads[(string) $p->id] = [
                'id'               => (string) $p->id,
                'url'              => route('office.transit-parcels.receive', $p),
                'package_type'     => (string) $p->package_type,
                'receipt_number'   => (string) ($p->receipt_number ?? ''),
                'source_office'    => $officeLabel,
                'shipment_id'      => $shipmentId,
                'shipment_number'  => $shipmentNumber,
                'driver_name'      => $driverName,
                'driver_phone'     => $driverPhone,
                'payment_label'    => $paymentEnum->label(),
                'is_deferred'      => $paymentEnum->value === 'deferred',
                'cod_amount'       => number_format((float) ($p->cod_amount ?? 0)),
                'recipient_name'   => (string) ($p->recipient_name ?: 'بدون اسم'),
                'recipient_phone'  => (string) $p->recipient_phone,
                'sender_name'      => (string) $p->sender_name,
                'sender_phone'     => (string) $p->sender_phone,
                'notes'            => (string) ($p->notes ?? ''),
                'dispatched_ago'   => $dispatchedAgo,
                'dispatched_clock' => $dispatchedClock,
                'search_text'      => implode(' ', array_filter([
                    $p->package_type,
                    $p->receipt_number,
                    $p->recipient_name,
                    $p->recipient_phone,
                    $p->sender_name,
                    $p->sender_phone,
                    $officeLabel,
                    $shipmentNumber,
                    $driverName,
                    $driverPhone,
                    $p->notes,
                ])),
            ];
        }
    @endphp

    <main class="flex-1 p-3.5 lg:p-6 space-y-3.5"
          @transit-search-change.window="searchQuery = $event.detail.q || ''"
          x-data="transitParcelsPage({
              initialCount: {{ $transitParcels->count() }},
              parcels: @js(array_values($transitPayloads))
          })">

        {{-- رأس الصفحة --}}
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2.5 min-w-0">
                <a href="{{ route('office.dashboard') }}"
                   class="w-9 h-9 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-600 hover:text-primary hover:border-slate-300 transition-colors shadow-2xs shrink-0">
                    <span class="material-symbols-outlined text-lg">arrow_forward</span>
                </a>
                <div class="min-w-0">
                    <h2 class="text-sm font-extrabold text-slate-800 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-lg text-secondary">local_shipping</span>
                        <span class="truncate">طرود قادمة في الطريق</span>
                    </h2>
                    <p class="text-[11px] text-slate-400 truncate">امسح أي طرد ليتعرّف تلقائياً على شحنته ويستلمه فوراً</p>
                </div>
            </div>

            <span class="px-2.5 py-1 rounded-xl bg-primary text-secondary text-[11px] font-extrabold shadow-xs shrink-0"
                  x-text="countLabel(remainingCount)">
                {{ $transitParcels->count() === 1 ? 'طرد واحد' : ($transitParcels->count() === 2 ? 'طردان' : $transitParcels->count() . ' طرود') }}
            </span>
        </div>

        {{-- شريط مسح الباركود الشامل لكل الشحنات الواصلة معاً --}}
        <div x-show="remainingCount > 0"
             @if ($transitParcels->count() === 0) x-cloak @endif
             class="bg-white lg:dark:bg-[#24303F] border border-slate-200/90 lg:border-gray-200 lg:dark:border-slate-700/60 rounded-2xl p-3 lg:p-4 shadow-xs space-y-2.5">

            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-1.5 lg:gap-2.5 min-w-0">
                    <span class="w-8 h-8 rounded-xl bg-primary lg:dark:bg-[#1A222C] text-secondary flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-lg">qr_code_scanner</span>
                    </span>
                    <div class="min-w-0">
                        <h3 class="text-xs font-extrabold text-primary lg:dark:text-slate-100">استلام سريع بالباركود</h3>
                        <p class="text-[10px] text-slate-400 truncate">يمسح من جميع الشحنات الواصلة ويميز رقم الكشف تلقائياً</p>
                    </div>
                </div>

                <button type="button"
                        @click="toggleCamera()"
                        :class="cameraActive ? 'bg-rose-600 text-white' : 'bg-primary text-secondary hover:bg-slate-800 lg:dark:bg-amber-500 lg:dark:text-slate-950 lg:dark:hover:bg-amber-400'"
                        class="px-3 py-2 rounded-xl text-[11px] font-extrabold flex items-center gap-1 transition-colors shrink-0 shadow-xs">
                    <span class="material-symbols-outlined text-base" x-text="cameraActive ? 'videocam_off' : 'photo_camera'"></span>
                    <span x-text="cameraActive ? 'إيقاف' : 'مسح بالكاميرا'"></span>
                </button>
            </div>

            {{-- نافذة كاميرا المسح --}}
            <div x-show="cameraActive" x-cloak class="rounded-xl overflow-hidden border border-slate-300 lg:dark:border-slate-700 bg-black">
                <div id="transit-qr-reader" class="w-full"></div>
            </div>

            {{-- حقل قارئ الباركود الخارجي (USB/Bluetooth) أو رقم السند --}}
            <div class="flex gap-1.5">
                <input type="text"
                       x-ref="scanInput"
                       x-model="scanInput"
                       @keydown.enter.prevent="handleBarcodeScan(scanInput)"
                       dir="ltr"
                       autocomplete="off"
                       placeholder="امسح الباركود أو اكتب رقم السند ثم Enter"
                       class="flex-1 py-2 px-3 text-xs font-mono font-bold text-slate-800 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white lg:dark:bg-[#1A222C] lg:dark:focus:bg-[#1A222C] lg:dark:text-slate-100 lg:dark:placeholder-slate-400 lg:dark:border-slate-700 outline-none focus:border-secondary transition-colors">
                <button type="button"
                        @click="handleBarcodeScan(scanInput)"
                        class="px-3.5 py-2 rounded-xl bg-secondary hover:bg-amber-500 text-primary text-xs font-extrabold shrink-0 transition-colors">
                    تحقق
                </button>
            </div>

            {{-- تنبيه حالة المسح (خطأ أو تكرار) --}}
            <div x-show="scanAlert.text"
                 x-cloak
                 :class="scanAlert.type === 'duplicate'
                     ? 'bg-amber-50 border-amber-300 text-amber-900 lg:dark:bg-amber-500/15 lg:dark:border-amber-500/30 lg:dark:text-amber-300'
                     : 'bg-rose-50 border-rose-200 text-rose-800 lg:dark:bg-rose-500/15 lg:dark:border-rose-500/30 lg:dark:text-rose-300'"
                 class="px-2.5 py-2 rounded-xl border text-[11px] font-extrabold flex items-center gap-1.5">
                <span class="material-symbols-outlined text-base shrink-0"
                      x-text="scanAlert.type === 'duplicate' ? 'block' : 'error'"></span>
                <span x-text="scanAlert.text"></span>
            </div>
        </div>

        {{-- 1. شريط تصفية الشحنات الذكي للجوال (< 1024px — مطابق للأصل 100%) --}}
        <div x-show="remainingCount > 0 && shipmentGroups.length > 0"
             x-cloak
             class="flex lg:hidden items-center gap-1.5 overflow-x-auto pb-0.5 no-scrollbar">
            <button type="button"
                    @click="activeShipmentFilter = 'all'"
                    :class="activeShipmentFilter === 'all'
                        ? 'bg-primary text-secondary border-primary'
                        : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'"
                    class="px-3 py-1.5 rounded-xl border text-[11px] font-extrabold shrink-0 transition-colors flex items-center gap-1">
                <span>الكل</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px]"
                      :class="activeShipmentFilter === 'all' ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-700'"
                      x-text="remainingCount"></span>
            </button>

            <template x-for="grp in shipmentGroups" :key="grp.key">
                <button type="button"
                        @click="activeShipmentFilter = grp.key"
                        :class="activeShipmentFilter === grp.key
                            ? 'bg-primary text-secondary border-primary'
                            : 'bg-white text-slate-700 border-slate-200 hover:border-secondary'"
                        class="px-3 py-1.5 rounded-xl border text-[11px] font-extrabold shrink-0 transition-colors flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[14px]"
                          x-text="grp.key === 'standalone' ? 'package_2' : 'receipt_long'"></span>
                    <span x-text="grp.label"></span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono"
                          :class="activeShipmentFilter === grp.key ? 'bg-secondary text-primary' : 'bg-amber-100 text-amber-800'"
                          x-text="grp.count"></span>
                </button>
            </template>
        </div>

        {{-- 2. شريط أدوات سطح المكتب الاحترافي (lg: >= 1024px): Segmented Filter Pills + مبدّل (جدول / بطاقات) --}}
        <div x-show="remainingCount > 0"
             @if ($transitParcels->count() === 0) x-cloak @endif
             class="hidden lg:flex items-center justify-between gap-4">
            <div class="inline-flex items-center flex-wrap gap-1 p-1 rounded-xl bg-gray-100 border border-gray-200 dark:bg-[#1C2434] dark:border-slate-700/60">
                <button type="button"
                        @click="activeShipmentFilter = 'all'"
                        :class="activeShipmentFilter === 'all'
                            ? 'bg-white text-slate-900 shadow-xs font-extrabold dark:bg-[#24303F] dark:text-slate-100'
                            : 'text-slate-600 hover:text-slate-900 font-bold dark:text-slate-400 dark:hover:text-slate-200'"
                        class="px-3.5 py-1.5 rounded-lg text-xs inline-flex items-center gap-1.5 transition-all">
                    <span>الكل</span>
                    <span :class="activeShipmentFilter === 'all'
                              ? 'bg-amber-50 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300'
                              : 'bg-gray-200/80 text-slate-600 dark:bg-slate-800 dark:text-slate-400'"
                          class="px-1.5 py-0.5 rounded-md font-mono text-[11px] leading-none"
                          x-text="remainingCount"></span>
                </button>

                <template x-for="grp in shipmentGroups" :key="'desk-' + grp.key">
                    <button type="button"
                            @click="activeShipmentFilter = grp.key"
                            :class="activeShipmentFilter === grp.key
                                ? 'bg-white text-slate-900 shadow-xs font-extrabold dark:bg-[#24303F] dark:text-slate-100'
                                : 'text-slate-600 hover:text-slate-900 font-bold dark:text-slate-400 dark:hover:text-slate-200'"
                            class="px-3.5 py-1.5 rounded-lg text-xs inline-flex items-center gap-1.5 transition-all">
                        <span class="material-symbols-outlined text-[14px]"
                              x-text="grp.key === 'standalone' ? 'package_2' : 'receipt_long'"></span>
                        <span x-text="grp.label"></span>
                        <span :class="activeShipmentFilter === grp.key
                                  ? 'bg-amber-50 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300'
                                  : 'bg-gray-200/80 text-slate-600 dark:bg-slate-800 dark:text-slate-400'"
                              class="px-1.5 py-0.5 rounded-md font-mono text-[11px] leading-none"
                              x-text="grp.count"></span>
                    </button>
                </template>
            </div>

            <div class="inline-flex items-center gap-1 p-1 rounded-xl bg-white border border-gray-200 dark:bg-[#24303F] dark:border-slate-700/60 shadow-2xs shrink-0">
                <button type="button"
                        @click="setDesktopView('table')"
                        :class="desktopView === 'table'
                            ? 'bg-slate-900 text-secondary dark:bg-amber-500/20 dark:text-amber-300 shadow-2xs font-extrabold'
                            : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 font-bold'"
                        class="px-3 py-1.5 rounded-lg text-xs inline-flex items-center gap-1.5 transition-all">
                    <span class="material-symbols-outlined text-[16px]">table_rows</span>
                    <span>جدول</span>
                </button>
                <button type="button"
                        @click="setDesktopView('cards')"
                        :class="desktopView === 'cards'
                            ? 'bg-slate-900 text-secondary dark:bg-amber-500/20 dark:text-amber-300 shadow-2xs font-extrabold'
                            : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 font-bold'"
                        class="px-3 py-1.5 rounded-lg text-xs inline-flex items-center gap-1.5 transition-all">
                    <span class="material-symbols-outlined text-[16px]">grid_view</span>
                    <span>بطاقات</span>
                </button>
            </div>
        </div>

        {{-- أ. قائمة بطاقات الطرود القادمة (دائماً على الجوال < 1024px، وعلى الكمبيوتر عند اختيار وضع البطاقات) --}}
        <div :class="desktopView === 'table' ? 'lg:hidden' : 'lg:space-y-0 lg:grid lg:grid-cols-2 2xl:grid-cols-3 lg:gap-4'"
             class="space-y-3"
             x-ref="transitList">
            @foreach ($transitParcels as $parcel)
                @php
                    $payload = $transitPayloads[(string) $parcel->id];
                    $paymentEnum = $parcel->payment_type instanceof \App\Enums\OutgoingPaymentType
                        ? $parcel->payment_type
                        : (\App\Enums\OutgoingPaymentType::tryFrom((string) $parcel->payment_type) ?? \App\Enums\OutgoingPaymentType::PAID);
                    $dispatchedAt = $parcel->dispatched_at;
                    $dispatchedDate = $dispatchedAt && !$dispatchedAt->isToday() ? $dispatchedAt->format('Y/m/d') : null;
                @endphp

                <article id="transit-card-{{ $parcel->id }}"
                         x-show="!receivedIds['{{ $parcel->id }}'] && isItemVisible('{{ $parcel->id }}')"
                         style="content-visibility: auto; contain-intrinsic-size: 195px;"
                         class="bg-white lg:dark:bg-[#24303F] border border-slate-200/90 lg:border-gray-200 lg:dark:border-slate-700/60 lg:border-r-4 lg:border-r-sky-500 lg:dark:border-r-sky-400 rounded-2xl p-3.5 lg:p-4 shadow-xs lg:hover:shadow-md space-y-3 lg:flex lg:flex-col lg:justify-between transition-all">

                    {{-- شريط تعريف الكشف والسائق (إن كان الطرد ضمن شحنة) --}}
                    @if ($payload['shipment_number'])
                        <div class="flex items-center justify-between gap-2 bg-primary/5 border border-primary/10 lg:dark:bg-slate-800/70 lg:dark:border-slate-700/60 rounded-xl px-2.5 py-1.5 text-[11px]">
                            <span class="inline-flex items-center gap-1 font-extrabold text-primary lg:dark:text-slate-100 min-w-0">
                                <span class="material-symbols-outlined text-sm text-secondary shrink-0">receipt_long</span>
                                <span class="font-mono shrink-0">كشف #{{ $payload['shipment_number'] }}</span>
                            </span>
                            <span class="text-slate-600 lg:dark:text-slate-300 font-bold truncate">
                                السائق: {{ $payload['driver_name'] }}
                            </span>
                        </div>
                    @endif

                    {{-- 1. رأس البطاقة: نوع الطرد + السند + وقت الشحن بالعربي --}}
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-9 h-9 rounded-xl bg-slate-100 lg:dark:bg-slate-800 text-primary lg:dark:text-amber-400 flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-xl">package_2</span>
                            </span>
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <h3 class="font-extrabold text-sm text-slate-800 lg:dark:text-slate-100 leading-tight">{{ $parcel->package_type }}</h3>
                                    @if ($parcel->receipt_number)
                                        <span class="px-1.5 py-0.5 rounded-md bg-slate-100 text-slate-700 border border-slate-200 lg:dark:bg-slate-800 lg:dark:text-slate-200 lg:dark:border-slate-700 text-[10px] font-mono font-extrabold">
                                            سند #{{ $parcel->receipt_number }}
                                        </span>
                                    @endif
                                </div>

                                @if ($payload['dispatched_clock'])
                                    <p class="text-[10px] text-slate-400 font-semibold mt-0.5 flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[12px]">schedule</span>
                                        <span>شُحن {{ $dispatchedDate ? $dispatchedDate . ' • ' : 'الساعة ' }}{{ $payload['dispatched_clock'] }}</span>
                                    </p>
                                @endif
                            </div>
                        </div>

                        @if ($payload['dispatched_ago'])
                            <span dir="rtl"
                                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-200/80 lg:dark:bg-amber-500/15 lg:dark:text-amber-300 lg:dark:border-amber-500/30 text-[10px] font-extrabold shrink-0">
                                <span>{{ $payload['dispatched_ago'] }}</span>
                            </span>
                        @endif
                    </div>

                    {{-- 2. شريط المصدر والحساب --}}
                    <div class="flex items-center justify-between gap-2 flex-wrap pt-0.5">
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-100 text-primary border border-slate-200/80 lg:dark:bg-slate-800 lg:dark:text-slate-200 lg:dark:border-slate-700 text-[11px] font-extrabold max-w-full">
                            <span class="material-symbols-outlined text-[14px] text-secondary shrink-0">domain</span>
                            <span class="truncate">قادم من: {{ $payload['source_office'] }}</span>
                        </span>

                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border text-[11px] font-extrabold shrink-0 {{ $paymentEnum->badgeClass() }}">
                            <span>{{ $paymentEnum->label() }}:</span>
                            <span class="font-mono">{{ $payload['cod_amount'] }} ر.ي</span>
                        </span>
                    </div>

                    {{-- 3. بطاقة المستلم والمرسل --}}
                    <div class="grid grid-cols-2 gap-2.5 bg-slate-50 lg:dark:bg-[#1A222C] rounded-xl p-2.5 text-xs border border-slate-100 lg:dark:border-slate-700/50">
                        <div class="min-w-0">
                            <p class="text-[10px] text-slate-400 font-bold mb-0.5">المستلم</p>
                            <p class="font-extrabold text-slate-800 lg:dark:text-slate-100 truncate">{{ $payload['recipient_name'] }}</p>
                            <a href="tel:{{ $parcel->recipient_phone }}"
                               class="inline-flex items-center gap-1 text-[11px] text-slate-600 lg:dark:text-slate-300 hover:text-primary lg:dark:hover:text-amber-400 font-mono font-bold mt-0.5">
                                <span class="material-symbols-outlined text-[13px] text-emerald-600 lg:dark:text-emerald-400">call</span>
                                <span dir="ltr">{{ $parcel->recipient_phone }}</span>
                            </a>
                        </div>

                        <div class="min-w-0 border-r border-slate-200 lg:dark:border-slate-700/60 pr-2.5">
                            <p class="text-[10px] text-slate-400 font-bold mb-0.5">المرسل</p>
                            <p class="font-bold text-slate-700 lg:dark:text-slate-200 truncate">{{ $parcel->sender_name }}</p>
                            <a href="tel:{{ $parcel->sender_phone }}"
                               class="inline-flex items-center gap-1 text-[11px] text-slate-500 lg:dark:text-slate-400 hover:text-primary lg:dark:hover:text-amber-400 font-mono font-semibold mt-0.5">
                                <span class="material-symbols-outlined text-[13px] text-slate-400">call</span>
                                <span dir="ltr">{{ $parcel->sender_phone }}</span>
                            </a>
                        </div>
                    </div>

                    @if ($parcel->notes)
                        <div class="flex items-start gap-1.5 text-[11px] text-slate-700 lg:dark:text-amber-200 bg-amber-50/70 border border-amber-200/70 lg:dark:bg-amber-500/10 lg:dark:border-amber-500/30 rounded-xl px-2.5 py-2 leading-relaxed">
                            <span class="material-symbols-outlined text-sm text-amber-600 lg:dark:text-amber-400 shrink-0 mt-0.5">sticky_note_2</span>
                            <p class="min-w-0 break-words">
                                <span class="font-extrabold text-amber-900 lg:dark:text-amber-300">ملاحظة:</span> {{ $parcel->notes }}
                            </p>
                        </div>
                    @endif

                    {{-- 5. زر تأكيد الاستلام اليدوي --}}
                    <div class="pt-0.5">
                        <button type="button"
                                :disabled="Boolean(receivingIds['{{ $parcel->id }}'])"
                                @click="openReceiveModalById('{{ $parcel->id }}')"
                                class="w-full py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:scale-[0.99] text-white text-xs font-extrabold shadow-sm shadow-emerald-600/20 flex items-center justify-center gap-1.5 transition-all disabled:opacity-50">
                            <span class="material-symbols-outlined text-base">download_done</span>
                            <span x-text="receivingIds['{{ $parcel->id }}'] ? 'جاري الاستلام وإشعار العميل...' : 'تم الاستلام'"></span>
                        </button>
                    </div>
                </article>
            @endforeach
        </div>

        {{-- ب. وضع الجدول الاحترافي لسطح المكتب (lg: >= 1024px عند اختيار وضع الجدول) --}}
        <div x-show="desktopView === 'table' && remainingCount > 0 && visibleCount > 0"
             x-cloak
             class="hidden lg:block">
            <div class="bg-white dark:bg-[#24303F] rounded-2xl border border-gray-200 dark:border-slate-700/60 shadow-2xs overflow-hidden">
                <table class="w-full text-right border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-slate-700/60 bg-gray-50/80 dark:bg-slate-800/60 text-[11px] font-extrabold text-slate-500 dark:text-slate-400">
                            <th class="py-3 pr-4 pl-3">رقم السند والنوع</th>
                            <th class="py-3 px-3">المستلم</th>
                            <th class="py-3 px-3">المرسل</th>
                            <th class="py-3 px-3">المصدر / الكشف</th>
                            <th class="py-3 px-3">المبلغ والحالة المالية</th>
                            <th class="py-3 pr-3 pl-4 text-left">الإجراء</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-slate-700/50 text-xs">
                        @foreach ($transitParcels as $parcel)
                            @php
                                $payload = $transitPayloads[(string) $parcel->id];
                                $paymentEnum = $parcel->payment_type instanceof \App\Enums\OutgoingPaymentType
                                    ? $parcel->payment_type
                                    : (\App\Enums\OutgoingPaymentType::tryFrom((string) $parcel->payment_type) ?? \App\Enums\OutgoingPaymentType::PAID);
                                $dispatchedAt = $parcel->dispatched_at;
                                $dispatchedDate = $dispatchedAt && !$dispatchedAt->isToday() ? $dispatchedAt->format('Y/m/d') : null;
                            @endphp
                            <tr x-show="!receivedIds['{{ $parcel->id }}'] && isItemVisible('{{ $parcel->id }}')"
                                class="hover:bg-gray-50/70 dark:hover:bg-slate-800/40 transition-colors">

                                {{-- 1. رقم السند والنوع --}}
                                <td class="py-3 pr-4 pl-3 align-middle">
                                    <div class="flex items-center gap-2.5">
                                        <span class="w-1.5 h-8 rounded-full bg-sky-500 shrink-0"></span>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-1.5">
                                                @if ($parcel->receipt_number)
                                                    <span class="font-mono font-black text-slate-900 dark:text-slate-100">#{{ $parcel->receipt_number }}</span>
                                                @endif
                                                <span class="font-bold text-slate-800 dark:text-slate-200 truncate max-w-[165px]" title="{{ $parcel->package_type }}">{{ $parcel->package_type }}</span>
                                            </div>
                                            @if ($payload['dispatched_ago'] || $payload['dispatched_clock'])
                                                <p class="text-[10px] text-slate-400 mt-0.5">
                                                    {{ $payload['dispatched_ago'] ?? '' }}
                                                    @if ($payload['dispatched_clock'])
                                                        • {{ $dispatchedDate ? $dispatchedDate . ' ' : '' }}{{ $payload['dispatched_clock'] }}
                                                    @endif
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                {{-- 2. المستلم --}}
                                <td class="py-3 px-3 align-middle">
                                    <p class="font-bold text-slate-800 dark:text-slate-100 truncate max-w-[145px]">{{ $payload['recipient_name'] }}</p>
                                    <a href="tel:{{ $parcel->recipient_phone }}"
                                       class="text-[11px] font-mono text-slate-500 dark:text-slate-400 hover:text-primary dark:hover:text-amber-400 dir-ltr inline-block">{{ $parcel->recipient_phone }}</a>
                                </td>

                                {{-- 3. المرسل --}}
                                <td class="py-3 px-3 align-middle">
                                    <p class="font-bold text-slate-700 dark:text-slate-200 truncate max-w-[145px]">{{ $parcel->sender_name }}</p>
                                    <a href="tel:{{ $parcel->sender_phone }}"
                                       class="text-[11px] font-mono text-slate-500 dark:text-slate-400 hover:text-primary dark:hover:text-amber-400 dir-ltr inline-block">{{ $parcel->sender_phone }}</a>
                                </td>

                                {{-- 4. المصدر / الكشف --}}
                                <td class="py-3 px-3 align-middle">
                                    <p class="font-semibold text-slate-700 dark:text-slate-200 truncate max-w-[185px]">
                                        <span class="text-slate-400 text-[11px]">قادم من:</span>
                                        {{ $payload['source_office'] }}
                                    </p>
                                    @if ($payload['shipment_number'])
                                        <p class="text-[11px] font-mono font-bold text-indigo-600 dark:text-indigo-400 mt-0.5 truncate max-w-[185px]">
                                            كشف #{{ $payload['shipment_number'] }}
                                            @if ($payload['driver_name'])
                                                <span class="font-sans text-slate-500 dark:text-slate-400 font-semibold">• {{ $payload['driver_name'] }}</span>
                                            @endif
                                        </p>
                                    @elseif ($parcel->notes)
                                        <p class="text-[10px] text-amber-800 dark:text-amber-400 truncate max-w-[185px] mt-0.5" title="{{ $parcel->notes }}">ملاحظة: {{ $parcel->notes }}</p>
                                    @endif
                                </td>

                                {{-- 5. المبلغ والحالة المالية --}}
                                <td class="py-3 px-3 align-middle whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md border text-[11px] font-extrabold {{ $paymentEnum->badgeClass() }}">
                                        <span>{{ $paymentEnum->label() }}:</span>
                                        <span class="font-mono">{{ $payload['cod_amount'] }} ر.ي</span>
                                    </span>
                                </td>

                                {{-- 6. الإجراء --}}
                                <td class="py-3 pr-3 pl-4 align-middle whitespace-nowrap text-left">
                                    <button type="button"
                                            :disabled="Boolean(receivingIds['{{ $parcel->id }}'])"
                                            @click="openReceiveModalById('{{ $parcel->id }}')"
                                            class="h-8 px-3.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-extrabold inline-flex items-center gap-1.5 shadow-2xs transition-all active:scale-95 disabled:opacity-50">
                                        <span class="material-symbols-outlined text-[16px]">download_done</span>
                                        <span x-text="receivingIds['{{ $parcel->id }}'] ? 'جاري الاستلام...' : 'تم الاستلام'"></span>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- حالات القائمة الفارغة أو عدم تطابق البحث (مشتركة بين الجدول والبطاقات) --}}
        <div>
            {{-- حالة عدم تطابق البحث --}}
            <div x-show="remainingCount > 0 && visibleCount === 0"
                 x-cloak
                 class="bg-white lg:dark:bg-[#24303F] rounded-2xl border border-slate-200/80 lg:border-gray-200 lg:dark:border-slate-700/60 p-8 text-center text-slate-400 space-y-2">
                <span class="material-symbols-outlined text-4xl text-slate-300 lg:dark:text-slate-600">search_off</span>
                <p class="text-xs font-extrabold text-slate-700 lg:dark:text-slate-200">لا توجد طرود مطابقة لبحثك</p>
                <div class="pt-1.5">
                    <button type="button"
                            @click="activeShipmentFilter = 'all'; window.dispatchEvent(new CustomEvent('transit-search-clear'))"
                            class="inline-flex items-center gap-1 px-3.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 lg:dark:bg-slate-800 lg:dark:hover:bg-slate-700 lg:dark:text-slate-200 text-xs font-bold transition-colors">
                        <span class="material-symbols-outlined text-sm">close</span>
                        <span>عرض جميع الطرود</span>
                    </button>
                </div>
            </div>

            {{-- حالة عدم وجود طرود قادمة --}}
            <div x-show="remainingCount === 0"
                 @if ($transitParcels->count() > 0) x-cloak @endif
                 class="bg-white lg:dark:bg-[#24303F] rounded-2xl border border-slate-200/80 lg:border-gray-200 lg:dark:border-slate-700/60 p-10 text-center text-slate-400 space-y-2">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-emerald-50 border border-emerald-100 lg:dark:bg-emerald-500/15 lg:dark:border-emerald-500/30 text-emerald-500 lg:dark:text-emerald-400 flex items-center justify-center">
                    <span class="material-symbols-outlined text-3xl">task_alt</span>
                </div>
                <p class="text-xs font-extrabold text-slate-700 lg:dark:text-slate-200 pt-1">لا توجد طرود قادمة في الطريق حالياً</p>
                <p class="text-[11px] text-slate-400">تم استلام جميع الطرود المرسلة إلى مكتبكم.</p>
                <div class="pt-2">
                    <a href="{{ route('office.parcels.index') }}"
                       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-primary lg:dark:bg-amber-500 text-white lg:dark:text-slate-950 text-xs font-bold hover:bg-slate-800 lg:dark:hover:bg-amber-400 transition-colors">
                        <span class="material-symbols-outlined text-sm text-secondary lg:dark:text-slate-950">inventory_2</span>
                        <span>الذهاب إلى الطرود المستلمة</span>
                    </a>
                </div>
            </div>
        </div>

        {{-- مودال فضّ التشابه (يظهر فقط إذا تكرر نفس رقم السند في أكثر من فرع/شحنة) --}}
        <div x-show="disambiguateModal.open"
             x-cloak
             @keydown.escape.window="disambiguateModal.open = false"
             class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-3 sm:p-4">
            <div class="fixed inset-0 bg-slate-950/60" @click="disambiguateModal.open = false"></div>
            <div class="relative w-full max-w-sm bg-white lg:dark:bg-[#24303F] rounded-3xl p-4 shadow-2xl border border-slate-100 lg:dark:border-slate-700/60 lg:dark:text-slate-100 z-10 space-y-3 text-right">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-extrabold text-primary lg:dark:text-slate-100">يوجد أكثر من طرد بنفس رقم السند</h3>
                    <button type="button" @click="disambiguateModal.open = false" class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 lg:dark:bg-slate-800 lg:dark:hover:bg-slate-700 text-slate-500 lg:dark:text-slate-400 flex items-center justify-center transition-colors">
                        <span class="material-symbols-outlined text-base">close</span>
                    </button>
                </div>
                <p class="text-[11px] text-slate-500 lg:dark:text-slate-400">اختر الطرد الصحيح حسب الفرع المرسل ورقم الكشف:</p>
                <div class="space-y-2 max-h-64 overflow-y-auto">
                    <template x-for="cand in disambiguateModal.matches" :key="cand.id">
                        <button type="button"
                                @click="disambiguateModal.open = false; openReceiveModal(cand)"
                                class="w-full text-right p-3 rounded-2xl border border-slate-200 lg:dark:border-slate-700/60 lg:dark:bg-[#1A222C]/50 hover:border-secondary hover:bg-amber-50/40 lg:dark:hover:border-secondary lg:dark:hover:bg-amber-500/10 transition-colors space-y-1">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-extrabold text-primary lg:dark:text-slate-100" x-text="cand.package_type"></span>
                                <span class="px-1.5 py-0.5 rounded bg-slate-100 lg:dark:bg-slate-800 font-mono text-[10px] font-black text-slate-700 lg:dark:text-slate-200"
                                      x-text="cand.shipment_number ? ('كشف #' + cand.shipment_number) : 'طرد منفرد'"></span>
                            </div>
                            <p class="text-[11px] font-bold text-slate-600 lg:dark:text-slate-300" x-text="'من: ' + cand.source_office"></p>
                            <p class="text-[10px] text-slate-400" x-text="'المستلم: ' + cand.recipient_name + ' (' + cand.recipient_phone + ')'"></p>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        {{-- مودال التحقق وتأكيد الاستلام (يعرض الكشف + السائق + نوع الطرد + واتساب / SMS / كلاهما) --}}
        <div x-show="receiveModal.open"
             x-cloak
             class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-3 sm:p-4"
             @keydown.escape.window="closeReceiveModal()">

            <div class="fixed inset-0 bg-slate-950/60 transition-opacity"
                 x-show="receiveModal.open"
                 x-transition:enter="ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="closeReceiveModal()">
            </div>

            <div class="relative w-full max-w-sm bg-white lg:dark:bg-[#24303F] rounded-3xl p-5 shadow-2xl border border-slate-100 lg:dark:border-slate-700/60 lg:dark:text-slate-100 z-10 transition-all transform flex flex-col gap-3.5 text-right"
                 x-show="receiveModal.open"
                 x-transition:enter="ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-6 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-6 sm:translate-y-0 sm:scale-95">

                <button type="button"
                        @click="closeReceiveModal()"
                        :disabled="receiveModal.submitting"
                        title="إغلاق"
                        class="absolute left-4 top-4 w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 lg:dark:bg-slate-800 lg:dark:hover:bg-slate-700 text-slate-400 hover:text-slate-600 lg:dark:hover:text-slate-200 flex items-center justify-center transition-colors disabled:opacity-50">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>

                <template x-if="receiveModal.parcel">
                    <div class="space-y-3">
                        <div class="text-center pt-1">
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700 lg:dark:bg-emerald-500/15 lg:dark:border-emerald-500/30 lg:dark:text-emerald-400 text-[10px] font-extrabold mb-2">
                                <span class="material-symbols-outlined text-sm">verified</span>
                                <span>تأكد من مطابقة الطرد قبل الاستلام</span>
                            </span>

                            {{-- بطاقة بارزة لنوع الطرد + رقم الكشف الذي ينتمي له تلقائياً --}}
                            <div class="bg-primary lg:dark:bg-[#1A222C] lg:dark:border lg:dark:border-slate-700/60 text-white rounded-2xl p-3.5 shadow-sm space-y-2">
                                <div>
                                    <p class="text-[10px] text-slate-300 font-bold">نوع الطرد</p>
                                    <h3 class="text-lg font-black text-secondary mt-0.5" x-text="receiveModal.parcel.package_type"></h3>
                                </div>

                                <div class="flex items-center justify-center gap-1.5 flex-wrap text-[11px]">
                                    <span x-show="receiveModal.parcel.receipt_number"
                                          class="px-2 py-0.5 rounded-md bg-white/10 font-mono font-extrabold text-white"
                                          x-text="'سند #' + receiveModal.parcel.receipt_number"></span>
                                    <span class="px-2 py-0.5 rounded-md bg-white/10 font-bold text-slate-200 truncate max-w-[180px]"
                                          x-text="receiveModal.parcel.source_office"></span>
                                </div>

                                {{-- تعريف الشحنة (الكشف) التلقائي --}}
                                <template x-if="receiveModal.parcel.shipment_number">
                                    <div class="pt-2 border-t border-white/10 flex items-center justify-between gap-2 text-[11px]">
                                        <span class="inline-flex items-center gap-1 font-extrabold text-secondary">
                                            <span class="material-symbols-outlined text-sm">receipt_long</span>
                                            <span class="font-mono" x-text="'كشف #' + receiveModal.parcel.shipment_number"></span>
                                        </span>
                                        <span class="text-slate-200 font-bold truncate"
                                              x-text="'السائق: ' + receiveModal.parcel.driver_name"></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- شريط اختيار قناة إشعار العميل (واتساب | SMS | كلاهما) --}}
                        <div class="space-y-1.5">
                            <label class="block text-[11px] font-extrabold text-slate-600 lg:dark:text-slate-300">
                                طريقة إشعار المستلم بوصول الطرد:
                            </label>
                            <div class="grid grid-cols-3 gap-1 p-1 rounded-full bg-slate-100 lg:dark:bg-[#1A222C] border border-slate-200/70 lg:dark:border-slate-700/60">
                                <button type="button"
                                        @click="setChannel('whatsapp')"
                                        :class="notifyChannel === 'whatsapp'
                                            ? 'bg-white lg:dark:bg-[#24303F] text-secondary shadow-xs border border-amber-100 lg:dark:border-amber-500/30 font-extrabold'
                                            : 'text-slate-600 lg:dark:text-slate-400 hover:text-primary lg:dark:hover:text-slate-200 font-bold border border-transparent'"
                                        class="py-2 px-2 rounded-full text-xs flex items-center justify-center gap-1.5 transition-all">
                                    <span class="material-symbols-outlined text-[16px]">chat</span>
                                    <span>واتساب</span>
                                </button>

                                <button type="button"
                                        @click="setChannel('sms')"
                                        :class="notifyChannel === 'sms'
                                            ? 'bg-white lg:dark:bg-[#24303F] text-secondary shadow-xs border border-amber-100 lg:dark:border-amber-500/30 font-extrabold'
                                            : 'text-slate-600 lg:dark:text-slate-400 hover:text-primary lg:dark:hover:text-slate-200 font-bold border border-transparent'"
                                        class="py-2 px-2 rounded-full text-xs flex items-center justify-center gap-1.5 transition-all">
                                    <span class="material-symbols-outlined text-[16px]">sms</span>
                                    <span>SMS</span>
                                </button>

                                <button type="button"
                                        @click="setChannel('both')"
                                        :class="notifyChannel === 'both'
                                            ? 'bg-white lg:dark:bg-[#24303F] text-secondary shadow-xs border border-amber-100 lg:dark:border-amber-500/30 font-extrabold'
                                            : 'text-slate-600 lg:dark:text-slate-400 hover:text-primary lg:dark:hover:text-slate-200 font-bold border border-transparent'"
                                        class="py-2 px-2 rounded-full text-xs flex items-center justify-center gap-1.5 transition-all">
                                    <span class="material-symbols-outlined text-[16px]">all_inclusive</span>
                                    <span>كلاهما</span>
                                </button>
                            </div>
                        </div>

                        {{-- حقل رقم الخانة (عند تفعيل ميزة parcel_slot_number) --}}
                        @if($hasSlotFeature)
                            <div>
                                <label class="flex items-center gap-1 text-[11px] font-bold text-slate-600 lg:dark:text-slate-300 mb-1">
                                    <span class="material-symbols-outlined text-sm text-indigo-500">grid_view</span>
                                    <span>رقم الخانة <span class="text-[10px] font-normal text-slate-400">(اختياري)</span></span>
                                </label>
                                <input type="text"
                                       x-model="slotNumber"
                                       maxlength="50"
                                       placeholder="مثال : 12"
                                       class="w-full py-2 px-3.5 text-xs font-bold text-slate-800 lg:dark:text-slate-100 rounded-full border border-slate-200 lg:dark:border-slate-700/60 bg-slate-50 lg:dark:bg-[#1A222C] focus:bg-white lg:dark:focus:bg-[#1A222C] focus:border-secondary outline-none transition-colors">
                            </div>
                        @endif

                        {{-- ملخص المستلم والحساب والمتبقي في الكشف --}}
                        <div class="bg-slate-50 lg:dark:bg-[#1A222C] rounded-2xl p-3 border border-slate-200/80 lg:dark:border-slate-700/60 space-y-2 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">المستلم:</span>
                                <div class="text-left">
                                    <span class="font-bold text-slate-800 lg:dark:text-slate-100 block" x-text="receiveModal.parcel.recipient_name"></span>
                                    <span class="text-[11px] text-slate-500 lg:dark:text-slate-400 font-mono dir-ltr block" x-text="receiveModal.parcel.recipient_phone"></span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between pt-2 border-t border-slate-200/60 lg:dark:border-slate-700/60">
                                <span class="text-slate-400">الحساب:</span>
                                <span class="font-extrabold"
                                      :class="receiveModal.parcel.is_deferred ? 'text-amber-700 lg:dark:text-amber-400' : 'text-emerald-700 lg:dark:text-emerald-400'">
                                    <span x-text="receiveModal.parcel.payment_label"></span>:
                                    <span class="font-mono" x-text="receiveModal.parcel.cod_amount + ' ر.ي'"></span>
                                </span>
                            </div>

                            <template x-if="receiveModal.parcel.shipment_id">
                                <div class="flex items-center justify-between pt-2 border-t border-slate-200/60 lg:dark:border-slate-700/60">
                                    <span class="text-slate-400">المتبقي في هذا الكشف:</span>
                                    <span class="font-extrabold text-primary lg:dark:text-slate-100"
                                          x-text="remainingInShipment(receiveModal.parcel.shipment_id) + ' طرد (شامل هذا الطرد)'"></span>
                                </div>
                            </template>
                        </div>

                        <template x-if="receiveModal.parcel.is_deferred">
                            <div class="bg-amber-50 lg:dark:bg-amber-500/10 border border-amber-200/80 lg:dark:border-amber-500/30 rounded-xl p-2.5 flex items-start gap-2 text-[11px] text-amber-800 lg:dark:text-amber-300 leading-snug">
                                <span class="material-symbols-outlined text-base text-amber-600 shrink-0 mt-0.5">payments</span>
                                <span>
                                    <strong>طرد آجل:</strong> سيُسجَّل مبلغ
                                    <strong class="font-mono" x-text="receiveModal.parcel.cod_amount + ' ر.ي'"></strong>
                                    للتحصيل من العميل عند التسليم.
                                </span>
                            </div>
                        </template>
                    </div>
                </template>

                {{-- أزرار الموافقة والتراجع --}}
                <form method="POST"
                      :action="receiveModal.url"
                      class="flex flex-col gap-2 pt-0.5"
                      @submit.prevent="confirmReceiveModal()">
                    @csrf
                    <button type="submit"
                            :disabled="receiveModal.submitting"
                            class="w-full py-3 px-4 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] text-white font-extrabold text-xs rounded-xl shadow-lg shadow-emerald-600/25 flex items-center justify-center gap-2 transition-all disabled:opacity-50">
                        <span class="material-symbols-outlined text-base">download_done</span>
                        <span x-text="receiveModal.submitting ? 'جاري التحويل وإرسال الإشعار...' : ('موافق — استلام وإشعار (' + channelLabel(notifyChannel) + ')')"></span>
                    </button>

                    <button type="button"
                            @click="closeReceiveModal()"
                            :disabled="receiveModal.submitting"
                            class="w-full py-2.5 px-4 bg-slate-100 hover:bg-slate-200 lg:dark:bg-slate-800 lg:dark:hover:bg-slate-700 text-slate-700 lg:dark:text-slate-200 font-bold text-xs rounded-xl flex items-center justify-center transition-colors disabled:opacity-50">
                        <span>تراجع</span>
                    </button>
                </form>
            </div>
        </div>

    </main>
@endsection

@push('scripts')
    <script defer src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
        function transitParcelsPage(config) {
            const rawParcels = Array.isArray(config.parcels) ? config.parcels : [];
            const parcelsById = {};
            rawParcels.forEach(p => {
                parcelsById[String(p.id)] = p;
            });

            const savedChannel = localStorage.getItem('mursal_transit_notify_channel');
            const initialChannel = ['sms', 'whatsapp', 'both'].includes(savedChannel) ? savedChannel : 'both';

            return {
                remainingCount: Number(config.initialCount || 0),
                parcels: rawParcels,
                parcelsById: parcelsById,
                searchQuery: '',
                activeShipmentFilter: 'all',
                desktopView: localStorage.getItem('mursal_transit_desktop_view') === 'cards' ? 'cards' : 'table',
                receivingIds: {},
                receivedIds: {},

                notifyChannel: initialChannel,
                slotNumber: '',

                scanInput: '',
                scanAlert: { type: '', text: '' },
                cameraActive: false,
                _qrScanner: null,
                _lastScannedCode: '',
                _lastScannedAt: 0,

                disambiguateModal: {
                    open: false,
                    matches: [],
                },

                receiveModal: {
                    open: false,
                    submitting: false,
                    url: '#',
                    parcel: null,
                },

                setChannel(ch) {
                    this.notifyChannel = ch;
                    localStorage.setItem('mursal_transit_notify_channel', ch);
                },

                setDesktopView(mode) {
                    const next = mode === 'cards' ? 'cards' : 'table';
                    this.desktopView = next;
                    try {
                        localStorage.setItem('mursal_transit_desktop_view', next);
                    } catch (_) {}
                },

                channelLabel(ch) {
                    if (ch === 'sms') return 'SMS';
                    if (ch === 'whatsapp') return 'واتساب';
                    return 'كلاهما';
                },

                remainingInShipment(shipmentId) {
                    if (!shipmentId) return 0;
                    return this.parcels.filter(
                        p => !this.receivedIds[p.id] && p.shipment_id === shipmentId
                    ).length;
                },

                get shipmentGroups() {
                    const map = new Map();
                    let standaloneCount = 0;

                    for (let i = 0; i < this.parcels.length; i++) {
                        const p = this.parcels[i];
                        if (this.receivedIds[p.id]) continue;

                        if (p.shipment_id) {
                            if (!map.has(p.shipment_id)) {
                                map.set(p.shipment_id, {
                                    key: p.shipment_id,
                                    label: 'كشف #' + p.shipment_number,
                                    count: 0,
                                });
                            }
                            map.get(p.shipment_id).count++;
                        } else {
                            standaloneCount++;
                        }
                    }

                    const list = Array.from(map.values());
                    if (standaloneCount > 0 && list.length > 0) {
                        list.push({
                            key: 'standalone',
                            label: 'طرود منفردة',
                            count: standaloneCount,
                        });
                    }
                    return list;
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

                        if (type === 'scan') {
                            osc.frequency.value = 880;
                            gain.gain.value = 0.12;
                            osc.start();
                            osc.stop(ctx.currentTime + 0.09);
                            if (navigator.vibrate) navigator.vibrate(40);
                        } else if (type === 'success') {
                            osc.frequency.value = 1040;
                            gain.gain.value = 0.14;
                            osc.start();
                            osc.stop(ctx.currentTime + 0.13);
                            if (navigator.vibrate) navigator.vibrate(60);
                        } else {
                            osc.frequency.value = 220;
                            osc.type = 'sawtooth';
                            gain.gain.value = 0.15;
                            osc.start();
                            osc.stop(ctx.currentTime + 0.24);
                            if (navigator.vibrate) navigator.vibrate([90, 40, 90]);
                        }
                    } catch (e) {}
                },

                norm(str) {
                    return String(str || '')
                        .toLowerCase()
                        .replace(/[أإآ]/g, 'ا')
                        .replace(/ة/g, 'ه')
                        .replace(/ى/g, 'ي')
                        .trim();
                },

                normalizeCode(raw) {
                    const ar = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
                    const fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
                    let clean = String(raw || '')
                        .replace(/[٠-٩]/g, d => ar.indexOf(d))
                        .replace(/[۰-۹]/g, d => fa.indexOf(d))
                        .trim();
                    const m = clean.match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i);
                    return m ? m[0].toLowerCase() : clean.replace(/^#/, '');
                },

                isItemVisible(id) {
                    const p = this.parcelsById[String(id)];
                    if (!p) return false;

                    if (this.activeShipmentFilter !== 'all') {
                        if (this.activeShipmentFilter === 'standalone') {
                            if (p.shipment_id !== '') return false;
                        } else if (p.shipment_id !== this.activeShipmentFilter) {
                            return false;
                        }
                    }

                    const q = this.norm(this.searchQuery);
                    if (!q) return true;
                    return this.norm(p.search_text).includes(q);
                },

                get visibleCount() {
                    let count = 0;
                    for (let i = 0; i < this.parcels.length; i++) {
                        const id = String(this.parcels[i].id);
                        if (!this.receivedIds[id] && this.isItemVisible(id)) {
                            count++;
                        }
                    }
                    return count;
                },

                countLabel(n) {
                    n = Number(n) || 0;
                    if (n === 0) return 'لا توجد طرود';
                    if (n === 1) return 'طرد واحد';
                    if (n === 2) return 'طردان';
                    if (n >= 3 && n <= 10) return n + ' طرود';
                    return n + ' طرداً';
                },

                notifyToast(message, typeOrIsError = false) {
                    if (!message) return;
                    const type = typeof typeOrIsError === 'boolean'
                        ? (typeOrIsError ? 'error' : 'success')
                        : (typeOrIsError || 'success');
                    window.dispatchEvent(new CustomEvent('office-toast', {
                        detail: { message, type }
                    }));
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
                        this.scanAlert = { type: 'error', text: 'تعذر تحميل مكتبة الكاميرا، تحقق من الاتصال بالإنترنت.' };
                        return;
                    }
                    this.scanAlert = { type: '', text: '' };
                    this.cameraActive = true;
                    this.$nextTick(async () => {
                        try {
                            this._qrScanner = new Html5Qrcode('transit-qr-reader');
                            await this._qrScanner.start(
                                { facingMode: 'environment' },
                                { fps: 10, qrbox: { width: 230, height: 150 } },
                                (decodedText) => {
                                    if (this.receiveModal.open || this.disambiguateModal.open) return;
                                    const now = Date.now();
                                    if (decodedText === this._lastScannedCode && (now - this._lastScannedAt) < 2200) {
                                        return;
                                    }
                                    this._lastScannedCode = decodedText;
                                    this._lastScannedAt = now;
                                    this.handleBarcodeScan(decodedText);
                                },
                                () => {}
                            );
                        } catch (err) {
                            this.cameraActive = false;
                            this.scanAlert = { type: 'error', text: 'تعذر فتح الكاميرا، تأكد من السماح بالصلاحية أو استخدم حقل القارئ.' };
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

                handleBarcodeScan(rawCode) {
                    const code = this.normalizeCode(rawCode);
                    this.scanInput = '';
                    this.scanAlert = { type: '', text: '' };
                    if (!code) return;

                    // 1. تطابق دقيق بالـ UUID (لا يتكرر بين الشحنات أبداً)
                    const byUuid = this.parcels.find(p => p.id.toLowerCase() === code);
                    if (byUuid) {
                        if (this.receivedIds[byUuid.id]) {
                            this.beep('error');
                            const dupMsg = 'تم استلام هذا الطرد مسبقاً (سند #' + byUuid.receipt_number + ').';
                            this.scanAlert = { type: 'duplicate', text: dupMsg };
                            this.notifyToast(dupMsg, 'warning');
                            return;
                        }
                        this.beep('scan');
                        this.openReceiveModal(byUuid);
                        return;
                    }

                    // 2. البحث برقم السند عبر جميع الشحنات الواصلة
                    const unreceivedMatches = this.parcels.filter(
                        p => !this.receivedIds[p.id] && String(p.receipt_number) === code
                    );

                    if (unreceivedMatches.length === 1) {
                        this.beep('scan');
                        this.openReceiveModal(unreceivedMatches[0]);
                        return;
                    }

                    if (unreceivedMatches.length > 1) {
                        this.beep('scan');
                        this.disambiguateModal.matches = unreceivedMatches;
                        this.disambiguateModal.open = true;
                        return;
                    }

                    // 3. هل تم استلامه مسبقاً في نفس الجلسة؟
                    const already = this.parcels.find(
                        p => this.receivedIds[p.id] && String(p.receipt_number) === code
                    );
                    if (already) {
                        this.beep('error');
                        const dupMsg = 'تم استلام هذا الطرد مسبقاً (سند #' + already.receipt_number + ').';
                        this.scanAlert = { type: 'duplicate', text: dupMsg };
                        this.notifyToast(dupMsg, 'warning');
                        return;
                    }

                    this.beep('error');
                    this.scanAlert = {
                        type: 'error',
                        text: 'هذا الطرد غير موجود ضمن أي شحنة قادمة لمكتبك حالياً.'
                    };
                },

                openReceiveModalById(id) {
                    const found = this.parcelsById[String(id)];
                    if (found) {
                        this.openReceiveModal(found);
                    }
                },

                openReceiveModal(parcelData) {
                    this.scanAlert = { type: '', text: '' };
                    this.slotNumber = '';
                    this.receiveModal.parcel = parcelData || null;
                    this.receiveModal.url = parcelData?.url || '#';
                    this.receiveModal.submitting = false;
                    this.receiveModal.open = true;
                },

                closeReceiveModal() {
                    if (this.receiveModal.submitting) return;
                    this.receiveModal.open = false;
                    this.receiveModal.parcel = null;
                    this.receiveModal.url = '#';
                    this.slotNumber = '';
                    this.$nextTick(() => this.$refs.scanInput?.focus());
                },

                async confirmReceiveModal() {
                    if (this.receiveModal.submitting || !this.receiveModal.parcel) return;
                    if (typeof window.requireOnlineAction === 'function' && !window.requireOnlineAction()) return;

                    const parcelId = String(this.receiveModal.parcel.id);
                    const receiptNum = this.receiveModal.parcel.receipt_number;
                    const shipmentId = this.receiveModal.parcel.shipment_id;
                    const url = this.receiveModal.url;
                    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';

                    this.receiveModal.submitting = true;
                    this.receivingIds[parcelId] = true;

                    try {
                        const res = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': token,
                            },
                            body: JSON.stringify({
                                channel: this.notifyChannel,
                                slot_number: this.slotNumber.trim() || null,
                            }),
                        });

                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || !data.ok) {
                            this.beep('error');
                            this.receiveModal.submitting = false;
                            this.notifyToast(data.message || 'تعذر استلام الطرد.', 'error');
                            return;
                        }

                        this.receivedIds[parcelId] = true;

                        if (typeof data.remaining_count === 'number') {
                            this.remainingCount = data.remaining_count;
                        } else {
                            this.remainingCount = Math.max(0, this.remainingCount - 1);
                        }

                        // إذا اكتمل الكشف المفلتر حالياً نعود تلقائياً لعرض الكل
                        if (this.activeShipmentFilter !== 'all' && shipmentId && this.activeShipmentFilter === shipmentId && this.remainingInShipment(shipmentId) === 0) {
                            this.activeShipmentFilter = 'all';
                        }

                        // تحديث عداد "الطرود" وعداد "الشحنات" معاً لحظياً في الشريط السفلي
                        window.dispatchEvent(new CustomEvent('transit-count-updated', {
                            detail: { count: this.remainingCount }
                        }));
                        if (typeof data.pending_shipments_count === 'number') {
                            window.dispatchEvent(new CustomEvent('shipments-pending-updated', {
                                detail: { count: data.pending_shipments_count }
                            }));
                        }

                        this.beep('success');
                        this.receiveModal.submitting = false;
                        this.closeReceiveModal();

                        if (this.remainingCount === 0) {
                            await this.stopCamera();
                        }

                        const label = this.channelLabel(this.notifyChannel);
                        this.notifyToast(
                            data.message || ('تم استلام سند #' + receiptNum + ' وتحويله للطرود المستلمة (' + label + ').'),
                            'success'
                        );
                    } catch (err) {
                        this.beep('error');
                        this.receiveModal.submitting = false;
                        if (typeof window.notifyNetworkError === 'function') {
                            window.notifyNetworkError('تعذر الاتصال بالخادم، يرجى التأكد من الاتصال بالإنترنت والمحاولة مجدداً.');
                        } else {
                            this.notifyToast('تعذر الاتصال بالخادم، يرجى التأكد من الاتصال بالإنترنت والمحاولة مجدداً.', 'error');
                        }
                    } finally {
                        this.receivingIds[parcelId] = false;
                    }
                }
            };
        }
    </script>
@endpush