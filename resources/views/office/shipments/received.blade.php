@extends('office.layouts.app')

@section('title', 'الشحنات المستلمة')

@section('shell-class', 'bg-slate-50')

@section('content')
    <div class="flex-1 flex flex-col" x-data="{
        desktopView: localStorage.getItem('mursal_received_shipments_desktop_view') === 'cards' ? 'cards' : 'table',
        setDesktopView(mode) {
            const next = mode === 'cards' ? 'cards' : 'table';
            this.desktopView = next;
            try {
                localStorage.setItem('mursal_received_shipments_desktop_view', next);
            } catch (_) {}
        },
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
        @php
            $receivedTabs = [
                ['key' => 'all',       'label' => 'الكل'],
                ['key' => 'pending',   'label' => 'بانتظار الاستلام'],
                ['key' => 'completed', 'label' => 'مكتملة'],
            ];
        @endphp

        {{-- 1. شريط تبويبات الجوال (< 1024px — مطابق للأصل 100%) --}}
        <nav class="flex lg:hidden overflow-x-auto no-scrollbar border-b border-slate-100 bg-white sticky top-[68px] z-20 text-xs font-bold text-center">
            @foreach ($receivedTabs as $tabItem)
                <a href="{{ route('office.shipments.received.index', ['tab' => $tabItem['key']]) }}"
                   @click="if (typeof window.requireOnlineAction === 'function' && !window.requireOnlineAction()) $event.preventDefault()"
                   class="flex-1 shrink-0 whitespace-nowrap px-3 py-3 transition-colors {{ $tab === $tabItem['key'] ? 'border-b-2 border-secondary text-secondary' : 'text-slate-500' }}">
                    <span>{{ $tabItem['label'] }}</span>
                    (<span>{{ $receivedCounts[$tabItem['key']] ?? 0 }}</span>)
                </a>
            @endforeach
        </nav>

        {{-- 2. شريط أدوات سطح المكتب الاحترافي (lg: >= 1024px): Segmented Filter Pills + مبدّل (جدول / بطاقات) --}}
        <div class="hidden lg:flex items-center justify-between gap-4 px-6 pt-5 pb-1">
            <div class="inline-flex items-center gap-1 p-1 rounded-xl bg-gray-100 border border-gray-200 dark:bg-[#1C2434] dark:border-slate-700/60">
                @foreach ($receivedTabs as $tabItem)
                    @php
                        $isActiveRecTab = $tab === $tabItem['key'];
                    @endphp
                    <a href="{{ route('office.shipments.received.index', ['tab' => $tabItem['key']]) }}"
                       @click="if (typeof window.requireOnlineAction === 'function' && !window.requireOnlineAction()) $event.preventDefault()"
                       class="px-3.5 py-1.5 rounded-lg text-xs inline-flex items-center gap-1.5 transition-all {{ $isActiveRecTab ? 'bg-white text-slate-900 shadow-xs font-extrabold dark:bg-[#24303F] dark:text-slate-100' : 'text-slate-600 hover:text-slate-900 font-bold dark:text-slate-400 dark:hover:text-slate-200' }}">
                        <span>{{ $tabItem['label'] }}</span>
                        <span class="px-1.5 py-0.5 rounded-md font-mono text-[11px] leading-none {{ $isActiveRecTab ? 'bg-amber-50 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300' : 'bg-gray-200/80 text-slate-600 dark:bg-slate-800 dark:text-slate-400' }}">
                            {{ $receivedCounts[$tabItem['key']] ?? 0 }}
                        </span>
                    </a>
                @endforeach
            </div>

            <div class="inline-flex items-center gap-1 p-1 rounded-xl bg-white border border-gray-200 dark:bg-[#24303F] dark:border-slate-700/60 shadow-2xs">
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

        <main class="flex-1 p-3 lg:px-6 lg:py-4 space-y-2.5">
            {{-- أ. قائمة البطاقات (دائماً على الجوال < 1024px، وعلى الكمبيوتر عند اختيار وضع البطاقات) --}}
            <div :class="desktopView === 'table' ? 'lg:hidden' : 'lg:space-y-0 lg:grid lg:grid-cols-2 2xl:grid-cols-3 lg:gap-4'"
                 class="space-y-2.5">
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
                        class="block bg-white lg:dark:bg-[#24303F] border {{ $pending > 0 ? 'border-secondary/60 lg:border-gray-200 lg:dark:border-slate-700/60 lg:border-r-4 lg:border-r-amber-400 lg:dark:border-r-amber-400' : 'border-slate-200/90 lg:border-gray-200 lg:dark:border-slate-700/60 lg:border-r-4 lg:border-r-emerald-500 lg:dark:border-r-emerald-500' }} hover:border-secondary lg:hover:shadow-md rounded-2xl p-3.5 lg:p-4 shadow-xs space-y-2.5 transition-all">

                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <span
                                    class="w-9 h-9 rounded-xl {{ $pending > 0 ? 'bg-primary lg:dark:bg-[#1A222C] text-secondary' : 'bg-emerald-50 text-emerald-600 lg:dark:bg-emerald-500/15 lg:dark:text-emerald-400' }} flex items-center justify-center shrink-0">
                                    <span
                                        class="material-symbols-outlined text-xl">{{ $pending > 0 ? 'qr_code_scanner' : 'task_alt' }}</span>
                                </span>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="font-mono font-black text-sm text-primary lg:dark:text-slate-100">كشف
                                            #{{ $shipment->shipment_number }}</span>
                                        <span
                                            class="px-1.5 py-0.5 rounded-md bg-slate-100 text-slate-700 lg:dark:bg-slate-800 lg:dark:text-slate-200 text-[10px] font-bold">
                                            {{ $total }} طرد لفرعك
                                        </span>
                                        @if ($shipment->office_id === null && $shipment->external_partner_link_id !== null)
                                            <span class="px-1.5 py-0.5 rounded-md bg-violet-50 text-violet-700 border border-violet-200 lg:dark:bg-violet-500/15 lg:dark:text-violet-300 lg:dark:border-violet-500/30 text-[10px] font-extrabold">
                                                مكتب خارجي
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-[10px] text-slate-400 font-semibold mt-0.5 truncate">
                                        من: <span class="text-slate-600 lg:dark:text-slate-300 font-bold">{{ $shipment->senderOfficeLabel() }}</span>
                                        @if ($dispatchedAgo)
                                            • {{ $dispatchedAgo }}
                                        @endif
                                    </p>
                                </div>
                            </div>

                            @if ($pending > 0)
                                <span
                                    class="px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-200 lg:dark:bg-amber-500/15 lg:dark:text-amber-300 lg:dark:border-amber-500/30 text-[10px] font-extrabold shrink-0">
                                    متبقي {{ $pending }}
                                </span>
                            @else
                                <span
                                    class="px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200 lg:dark:bg-emerald-500/15 lg:dark:text-emerald-400 lg:dark:border-emerald-500/30 text-[10px] font-extrabold shrink-0">
                                    مكتملة ✓
                                </span>
                            @endif
                        </div>

                        {{-- السائق --}}
                        <div
                            class="flex items-center justify-between bg-slate-50 lg:dark:bg-[#1A222C] rounded-xl px-3 py-2 border border-slate-100 lg:dark:border-slate-700/50 text-xs">
                            <span class="text-slate-400 text-[11px] font-bold flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm text-primary lg:dark:text-amber-400">person</span>
                                السائق: <strong class="text-slate-800 lg:dark:text-slate-100">{{ $shipment->driver_name }}</strong>
                            </span>
                            <span class="font-mono text-[11px] font-bold text-slate-600 lg:dark:text-slate-400"
                                dir="ltr">{{ $shipment->driver_phone }}</span>
                        </div>

                        {{-- شريط التقدم (المستلم مقابل المتبقي) --}}
                        <div class="space-y-1">
                            <div class="flex items-center justify-between text-[10px] font-bold">
                                <span class="text-slate-500 lg:dark:text-slate-400">حالة الفحص والاستلام: {{ $received }} من
                                    {{ $total }}</span>
                                <span
                                    class="{{ $pending > 0 ? 'text-amber-700 lg:dark:text-amber-400' : 'text-emerald-600 lg:dark:text-emerald-400' }} font-mono">{{ $percent }}%</span>
                            </div>
                            <div class="w-full h-1.5 rounded-full bg-slate-100 lg:dark:bg-slate-800 overflow-hidden">
                                <div class="h-full rounded-full transition-all {{ $pending > 0 ? 'bg-secondary' : 'bg-emerald-500' }}"
                                    style="width: {{ $percent }}%"></div>
                            </div>
                        </div>

                        {{-- المجاميع الخاصة بطرود هذا الفرع فقط --}}
                        <div class="grid grid-cols-3 gap-1.5 text-center">
                            <div class="bg-amber-50/70 border border-amber-200/70 lg:dark:bg-amber-500/10 lg:dark:border-amber-500/25 rounded-xl py-1.5 px-1">
                                <p class="text-[9px] font-bold text-amber-700 lg:dark:text-amber-400">الآجل</p>
                                <p class="text-xs font-mono font-black text-amber-800 lg:dark:text-amber-300">
                                    {{ number_format((float) $shipment->office_deferred_total) }}</p>
                            </div>
                            <div class="bg-emerald-50/70 border border-emerald-200/70 lg:dark:bg-emerald-500/10 lg:dark:border-emerald-500/25 rounded-xl py-1.5 px-1">
                                <p class="text-[9px] font-bold text-emerald-700 lg:dark:text-emerald-400">المحاسب</p>
                                <p class="text-xs font-mono font-black text-emerald-800 lg:dark:text-emerald-300">
                                    {{ number_format((float) $shipment->office_paid_total) }}</p>
                            </div>
                            <div class="bg-primary lg:dark:bg-[#1A222C] lg:dark:border lg:dark:border-slate-700/60 text-white rounded-xl py-1.5 px-1">
                                <p class="text-[9px] font-bold text-slate-300 lg:dark:text-slate-400">الإجمالي</p>
                                <p class="text-xs font-mono font-black text-secondary lg:dark:text-amber-400">
                                    {{ number_format((float) $shipment->office_grand_total) }}</p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-2 pt-0.5">
                            @if (!empty($shipment->can_reject_external))
                                <button type="button"
                                        data-reject-shipment="{{ $shipment->id }}"
                                        @click.prevent.stop="openRejectModal(@js($shipment->shipment_number), @js($shipment->senderOfficeLabel()), @js(route('office.shipments.received.reject', $shipment)))"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 lg:dark:bg-rose-500/15 lg:dark:hover:bg-rose-500/25 lg:dark:text-rose-400 lg:dark:border-rose-500/30 text-[11px] font-extrabold transition-colors">
                                    <span class="material-symbols-outlined text-sm">cancel</span>
                                    <span>رفض الكشف</span>
                                </button>
                            @else
                                <span></span>
                            @endif

                            <div class="flex items-center text-[11px] font-extrabold text-primary lg:dark:text-amber-300">
                                <span>{{ $pending > 0 ? 'فتح الفحص والمسح بالباركود' : 'عرض تفاصيل الكشف' }}</span>
                                <span class="material-symbols-outlined text-base text-secondary lg:dark:text-amber-400">chevron_left</span>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="lg:col-span-full bg-white lg:dark:bg-[#24303F] rounded-2xl border border-slate-200/80 lg:border-gray-200 lg:dark:border-slate-700/60 p-10 text-center space-y-2">
                        <div
                            class="w-14 h-14 mx-auto rounded-2xl bg-emerald-50 border border-emerald-100 lg:dark:bg-emerald-500/15 lg:dark:border-emerald-500/30 text-emerald-500 lg:dark:text-emerald-400 flex items-center justify-center">
                            <span class="material-symbols-outlined text-3xl">task_alt</span>
                        </div>
                        <p class="text-xs font-extrabold text-slate-700 lg:dark:text-slate-200">
                            {{ $tab === 'pending' ? 'لا توجد شحنات بانتظار الاستلام حالياً' : 'لا توجد شحنات مسجلة في هذا القسم' }}
                        </p>
                        <p class="text-[11px] text-slate-400">عند شحن أي كشف من الفروع الشريكة سيظهر هنا مباشرة مع إشعار تنبيهي.
                        </p>
                    </div>
                @endforelse
            </div>

            {{-- ب. وضع الجدول الاحترافي لسطح المكتب (lg: >= 1024px عند اختيار وضع الجدول) --}}
            <div x-show="desktopView === 'table'"
                 x-cloak
                 class="hidden lg:block">
                @if ($filteredShipments->isEmpty())
                    <div class="bg-white dark:bg-[#24303F] rounded-2xl border border-gray-200 dark:border-slate-700/60 p-10 text-center space-y-2 shadow-2xs">
                        <div class="w-14 h-14 mx-auto rounded-2xl bg-emerald-50 border border-emerald-100 dark:bg-emerald-500/15 dark:border-emerald-500/30 text-emerald-500 dark:text-emerald-400 flex items-center justify-center">
                            <span class="material-symbols-outlined text-3xl">task_alt</span>
                        </div>
                        <p class="text-xs font-extrabold text-slate-700 dark:text-slate-200">
                            {{ $tab === 'pending' ? 'لا توجد شحنات بانتظار الاستلام حالياً' : 'لا توجد شحنات مسجلة في هذا القسم' }}
                        </p>
                        <p class="text-[11px] text-slate-400">عند شحن أي كشف من الفروع الشريكة سيظهر هنا مباشرة مع إشعار تنبيهي.</p>
                    </div>
                @else
                    <div class="bg-white dark:bg-[#24303F] rounded-2xl border border-gray-200 dark:border-slate-700/60 shadow-2xs overflow-hidden">
                        <table class="w-full text-right border-collapse">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-slate-700/60 bg-gray-50/80 dark:bg-slate-800/60 text-[11px] font-extrabold text-slate-500 dark:text-slate-400">
                                    <th class="py-3 pr-4 pl-3">رقم الكشف والمرسل</th>
                                    <th class="py-3 px-3">السائق</th>
                                    <th class="py-3 px-3">الفحص والاستلام</th>
                                    <th class="py-3 px-3">المجاميع المالية (ر.ي)</th>
                                    <th class="py-3 px-3">الحالة</th>
                                    <th class="py-3 pr-3 pl-4 text-left">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-slate-700/50 text-xs">
                                @foreach ($filteredShipments as $shipment)
                                    @php
                                        $total = (int) $shipment->office_parcels_total;
                                        $received = (int) $shipment->office_parcels_received;
                                        $pending = (int) $shipment->office_parcels_pending;
                                        $percent = $total > 0 ? (int) round(($received / $total) * 100) : 0;
                                        $dispatchedAgo = $shipment->dispatched_at?->copy()->locale('ar')->diffForHumans();
                                    @endphp
                                    <tr class="hover:bg-gray-50/70 dark:hover:bg-slate-800/40 transition-colors">
                                        {{-- 1. رقم الكشف والمرسل --}}
                                        <td class="py-3 pr-4 pl-3 align-middle">
                                            <div class="flex items-center gap-2.5">
                                                <span class="w-1.5 h-8 rounded-full shrink-0 {{ $pending > 0 ? 'bg-amber-400' : 'bg-emerald-500' }}"></span>
                                                <div class="min-w-0">
                                                    <div class="flex items-center gap-1.5 flex-wrap">
                                                        <span class="font-mono font-black text-slate-900 dark:text-slate-100">كشف #{{ $shipment->shipment_number }}</span>
                                                        <span class="px-1.5 py-0.5 rounded-md bg-slate-100 text-slate-700 border border-slate-200/80 dark:bg-slate-800 dark:text-slate-200 dark:border-slate-700 text-[10px] font-bold">
                                                            {{ $total }} طرد
                                                        </span>
                                                        @if ($shipment->office_id === null && $shipment->external_partner_link_id !== null)
                                                            <span class="px-1.5 py-0.5 rounded-md bg-violet-50 text-violet-700 border border-violet-200 dark:bg-violet-500/15 dark:text-violet-300 dark:border-violet-500/30 text-[10px] font-extrabold">
                                                                مكتب خارجي
                                                            </span>
                                                        @endif
                                                    </div>
                                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 truncate max-w-[200px]">
                                                        من: <strong class="text-slate-700 dark:text-slate-200">{{ $shipment->senderOfficeLabel() }}</strong>
                                                        @if ($dispatchedAgo)
                                                            <span class="text-slate-400">• {{ $dispatchedAgo }}</span>
                                                        @endif
                                                    </p>
                                                </div>
                                            </div>
                                        </td>

                                        {{-- 2. السائق --}}
                                        <td class="py-3 px-3 align-middle">
                                            <p class="font-bold text-slate-800 dark:text-slate-100 truncate max-w-[150px]">{{ $shipment->driver_name }}</p>
                                            <p class="text-[11px] font-mono text-slate-500 dark:text-slate-400 dir-ltr text-right">{{ $shipment->driver_phone }}</p>
                                        </td>

                                        {{-- 3. تقدم الفحص والاستلام --}}
                                        <td class="py-3 px-3 align-middle">
                                            <div class="w-36 space-y-1">
                                                <div class="flex items-center justify-between text-[10px] font-bold">
                                                    <span class="text-slate-600 dark:text-slate-300">{{ $received }} من {{ $total }}</span>
                                                    <span class="font-mono {{ $pending > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">{{ $percent }}%</span>
                                                </div>
                                                <div class="w-full h-1.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                                                    <div class="h-full rounded-full {{ $pending > 0 ? 'bg-secondary' : 'bg-emerald-500' }}"
                                                         style="width: {{ $percent }}%"></div>
                                                </div>
                                            </div>
                                        </td>

                                        {{-- 4. المجاميع المالية --}}
                                        <td class="py-3 px-3 align-middle whitespace-nowrap">
                                            <div class="flex items-center gap-1.5 text-[11px] font-mono">
                                                <span class="px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-200/80 dark:bg-amber-500/15 dark:text-amber-300 dark:border-amber-500/30 font-extrabold">
                                                    آجل: {{ number_format((float) $shipment->office_deferred_total) }}
                                                </span>
                                                <span class="px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200/80 dark:bg-emerald-500/15 dark:text-emerald-400 dark:border-emerald-500/30 font-extrabold">
                                                    محاسب: {{ number_format((float) $shipment->office_paid_total) }}
                                                </span>
                                                <span class="px-2 py-0.5 rounded-md bg-slate-900 dark:bg-slate-800 dark:border dark:border-slate-700 text-secondary font-black">
                                                    إجمالي: {{ number_format((float) $shipment->office_grand_total) }}
                                                </span>
                                            </div>
                                        </td>

                                        {{-- 5. الحالة --}}
                                        <td class="py-3 px-3 align-middle whitespace-nowrap">
                                            @if ($pending > 0)
                                                <span class="px-2.5 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:border-amber-500/30 text-[11px] font-extrabold">
                                                    متبقي {{ $pending }}
                                                </span>
                                            @else
                                                <span class="px-2.5 py-0.5 rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-400 dark:border-emerald-500/30 text-[11px] font-extrabold">
                                                    مكتملة ✓
                                                </span>
                                            @endif
                                        </td>

                                        {{-- 6. الإجراءات --}}
                                        <td class="py-3 pr-3 pl-4 align-middle whitespace-nowrap text-left">
                                            <div class="inline-flex items-center justify-end gap-1.5">
                                                <a href="{{ route('office.shipments.received.show', $shipment) }}"
                                                   @click="if (typeof window.requireOnlineAction === 'function' && !window.requireOnlineAction()) $event.preventDefault()"
                                                   class="h-8 px-3 rounded-lg {{ $pending > 0 ? 'bg-primary hover:bg-slate-800 text-secondary dark:bg-amber-500 dark:hover:bg-amber-400 dark:text-slate-950' : 'bg-gray-100 hover:bg-gray-200 text-slate-700 border border-gray-200 dark:bg-[#1A222C] dark:hover:bg-[#333A48] dark:text-slate-200 dark:border-slate-700' }} text-xs font-extrabold inline-flex items-center gap-1.5 transition-all">
                                                    <span class="material-symbols-outlined text-[16px]">{{ $pending > 0 ? 'qr_code_scanner' : 'visibility' }}</span>
                                                    <span>{{ $pending > 0 ? 'فحص واستلام' : 'عرض الكشف' }}</span>
                                                </a>

                                                @if (!empty($shipment->can_reject_external))
                                                    <button type="button"
                                                            @click="openRejectModal(@js($shipment->shipment_number), @js($shipment->senderOfficeLabel()), @js(route('office.shipments.received.reject', $shipment)))"
                                                            class="h-8 px-2.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 dark:bg-rose-500/15 dark:hover:bg-rose-500/25 dark:text-rose-400 dark:border-rose-500/30 text-xs font-extrabold inline-flex items-center gap-1 transition-colors">
                                                        <span class="material-symbols-outlined text-[15px]">cancel</span>
                                                        <span>رفض</span>
                                                    </button>
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
        </main>

        {{-- نافذة تأكيد رفض الكشف الخارجي --}}
        <div x-show="rejectModal.open"
             x-cloak
             @keydown.escape.window="rejectModal.open = false"
             class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-3 sm:p-4">
            <div class="fixed inset-0 bg-slate-950/60" @click="rejectModal.open = false"></div>

            <div class="relative w-full max-w-sm bg-white lg:dark:bg-[#24303F] rounded-3xl p-5 shadow-2xl border border-slate-100 lg:dark:border-slate-700/60 z-10 space-y-4 text-right">
                <div class="text-center space-y-1">
                    <div class="w-12 h-12 mx-auto rounded-2xl bg-rose-50 border border-rose-200 lg:dark:bg-rose-500/15 lg:dark:border-rose-500/30 text-rose-600 lg:dark:text-rose-400 flex items-center justify-center">
                        <span class="material-symbols-outlined text-2xl">cancel</span>
                    </div>
                    <h3 class="text-sm font-extrabold text-slate-800 lg:dark:text-slate-100">رفض الكشف الخارجي #<span class="font-mono" x-text="rejectModal.shipmentNumber"></span>؟</h3>
                    <p class="text-[11px] font-bold text-slate-500 lg:dark:text-slate-400" x-text="'المرسل: ' + rejectModal.senderLabel"></p>
                </div>

                <form method="POST" :action="rejectModal.actionUrl" class="space-y-3 m-0">
                    @csrf
                    <div>
                        <label class="block text-[11px] font-extrabold text-slate-700 lg:dark:text-slate-300 mb-1">سبب الرفض (اختياري)</label>
                        <input type="text"
                               name="rejection_reason"
                               x-model="rejectModal.reason"
                               maxlength="255"
                               placeholder="مثال: كشف مكرر أو أُرسل بالخطأ..."
                               class="w-full rounded-xl border border-slate-300 bg-slate-50 focus:bg-white focus:border-rose-400 lg:dark:bg-[#1A222C] lg:dark:focus:bg-[#1A222C] lg:dark:border-slate-700 lg:dark:text-slate-100 lg:dark:placeholder-slate-400 px-3 py-2 text-xs font-bold text-slate-800 outline-none">
                    </div>

                    <div class="flex flex-col gap-2">
                        <button type="submit"
                                class="w-full py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-extrabold flex items-center justify-center gap-1.5">
                            <span class="material-symbols-outlined text-base">cancel</span>
                            <span>تأكيد رفض الكشف واستبعاده</span>
                        </button>
                        <button type="button"
                                @click="rejectModal.open = false"
                                class="w-full py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 lg:dark:bg-slate-800 lg:dark:hover:bg-slate-700 lg:dark:text-slate-200 text-xs font-bold">
                            تراجع
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
