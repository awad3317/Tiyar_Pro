@extends('office.layouts.app')

@section('title', 'الشحنات المستلمة')

@section('shell-class', 'bg-slate-50')

@section('content')
    <main class="flex-1 p-3.5 space-y-3.5" x-data="{
        init() {
            window.dispatchEvent(new CustomEvent('shipments-pending-updated', {
                detail: { count: {{ (int) $pendingReceivedShipmentsCount }} }
            }));
        }
    }">

        {{-- رأس الصفحة --}}
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2.5 min-w-0">
                <a href="{{ route('office.dashboard') }}"
                    class="w-9 h-9 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-600 hover:text-primary transition-colors shadow-2xs shrink-0">
                    <span class="material-symbols-outlined text-lg">arrow_forward</span>
                </a>
                <div class="min-w-0">
                    <h2 class="text-sm font-extrabold text-slate-800 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-lg text-secondary">move_to_inbox</span>
                        <span class="truncate">الشحنات المستلمة</span>
                    </h2>
                    <p class="text-[11px] text-slate-400 truncate">فحص طرود الشحنات بالباركود لكشف النقص وإشعار العملاء</p>
                </div>
            </div>

            @if ($pendingReceivedShipmentsCount > 0)
                <span class="px-2.5 py-1 rounded-xl bg-primary text-secondary text-[11px] font-extrabold shrink-0">
                    {{ $pendingReceivedShipmentsCount === 1 ? 'شحنة بانتظارك' : ($pendingReceivedShipmentsCount === 2 ? 'شحنتان بانتظارك' : $pendingReceivedShipmentsCount . ' شحنات') }}
                </span>
            @endif
        </div>

        {{-- تبويبات الفلترة --}}
        <div class="grid grid-cols-3 gap-1.5 bg-white p-1 rounded-2xl border border-slate-200 shadow-2xs">
            <a href="{{ route('office.shipments.received.index', ['tab' => 'pending']) }}"
                class="py-2 rounded-xl text-center text-xs font-extrabold transition-all {{ $tab === 'pending' ? 'bg-primary text-secondary shadow-xs' : 'text-slate-500 hover:text-primary' }}">
                بانتظار الاستلام
                @if ($pendingReceivedShipmentsCount > 0)
                    <span
                        class="mr-1 px-1.5 py-0.5 rounded-full text-[10px] {{ $tab === 'pending' ? 'bg-secondary text-primary' : 'bg-amber-100 text-amber-800' }}">
                        {{ $pendingReceivedShipmentsCount }}
                    </span>
                @endif
            </a>
            <a href="{{ route('office.shipments.received.index', ['tab' => 'completed']) }}"
                class="py-2 rounded-xl text-center text-xs font-extrabold transition-all {{ $tab === 'completed' ? 'bg-primary text-secondary shadow-xs' : 'text-slate-500 hover:text-primary' }}">
                مكتملة
            </a>
            <a href="{{ route('office.shipments.received.index', ['tab' => 'all']) }}"
                class="py-2 rounded-xl text-center text-xs font-extrabold transition-all {{ $tab === 'all' ? 'bg-primary text-secondary shadow-xs' : 'text-slate-500 hover:text-primary' }}">
                الكل
            </a>
        </div>

        {{-- قائمة الشحنات القادمة --}}
        <div class="space-y-3">
            @forelse ($filteredShipments as $shipment)
                @php
                    $total = (int) $shipment->office_parcels_total;
                    $received = (int) $shipment->office_parcels_received;
                    $pending = (int) $shipment->office_parcels_pending;
                    $percent = $total > 0 ? (int) round(($received / $total) * 100) : 0;
                    $dispatchedAgo = $shipment->dispatched_at?->copy()->locale('ar')->diffForHumans();
                @endphp

                <a href="{{ route('office.shipments.received.show', $shipment) }}"
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

                    <div class="flex items-center justify-end text-[11px] font-extrabold text-primary pt-0.5">
                        <span>{{ $pending > 0 ? 'فتح الفحص والمسح بالباركود' : 'عرض تفاصيل الكشف' }}</span>
                        <span class="material-symbols-outlined text-base text-secondary">chevron_left</span>
                    </div>
                </a>
            @empty
                <div class="bg-white rounded-2xl border border-slate-200/80 p-10 text-center space-y-2">
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
@endsection
