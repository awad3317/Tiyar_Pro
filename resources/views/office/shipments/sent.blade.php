@extends('office.layouts.app')

@section('title', 'الشحنات المرسلة')

@section('shell-class', 'bg-slate-50')

@section('header-search')
    <form method="GET"
          action="{{ route('office.shipments.sent.index') }}"
          x-data="{
              q: @js($search),
              normDigits(str) {
                  const ar = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
                  const fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
                  return String(str || '')
                      .replace(/[٠-٩]/g, d => ar.indexOf(d))
                      .replace(/[۰-۹]/g, d => fa.indexOf(d))
                      .replace(/[\u200B-\u200D\uFEFF\u200E\u200F]/g, '');
              },
              onInput() {
                  this.q = this.normDigits(this.q);
                  this.emitSearch();
              },
              emitSearch() {
                  window.dispatchEvent(new CustomEvent('sent-search-change', { detail: { q: this.q } }));
              },
              clear() {
                  this.q = '';
                  this.emitSearch();
              }
          }"
          @submit.prevent="emitSearch()"
          class="mt-3 relative">
        @if ($statusFilter !== 'all')
            <input type="hidden" name="status" value="{{ $statusFilter }}">
        @endif
        <input type="text"
               name="q"
               x-model="q"
               @input="onInput()"
               dir="rtl"
               autocomplete="off"
               placeholder="بحث برقم الكشف، اسم السائق، أو رقم السائق..."
               class="w-full bg-white/10 text-white placeholder-slate-300/70 text-xs rounded-xl pr-9 pl-8 py-2.5 outline-none border border-white/15 focus:border-secondary transition-all">
        <span
            class="material-symbols-outlined absolute right-2.5 top-2.5 text-slate-300/70 text-lg pointer-events-none">search</span>
        <template x-if="q.length > 0">
            <button type="button"
                    @click="clear()"
                    title="مسح البحث"
                    class="absolute left-2.5 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full bg-white/15 hover:bg-rose-500/20 text-slate-200 hover:text-rose-300 flex items-center justify-center text-xs font-bold transition-colors">
                ✕
            </button>
        </template>
    </form>
@endsection

@section('content')
    @php
        $sentOfficeProfile = $office->getResolvedProfile();
    @endphp
    <div class="flex-1 flex flex-col"
         @open-create-shipment-modal.window="openCreateModal()"
         @sent-search-change.window="handleSearchEvent($event.detail.q)"
         @shipments-synced.window="onShipmentsSynced($event.detail)"
         x-data="sentShipmentsPage({
             officeId: @js((int) $office->id),
             officeInfo: @js([
                 'id'               => (int) $office->id,
                 'name'             => (string) $office->name,
                 'branch_name'      => (string) ($office->branch_name ?? ''),
                 'logo_url'         => $sentOfficeProfile['logo_url'] ?? $sentOfficeProfile['icon_url'] ?? null,
                 'primary_color'    => $sentOfficeProfile['primary_color'] ?? '#041627',
                 'secondary_color'  => $sentOfficeProfile['secondary_color'] ?? '#fe9d20',
                 'branch_address'   => $sentOfficeProfile['branch_address'] ?? '',
                 'complaints_phone' => $sentOfficeProfile['complaints_phone'] ?? '',
                 'dispatch_phone_1' => $sentOfficeProfile['dispatch_phone_1'] ?? '',
             ]),
             baseUrl: @js(route('office.shipments.sent.index')),
             bootstrapUrl: @js(route('office.shipments.sent.bootstrap')),
             syncUrl: @js(route('office.shipments.sent.sync')),
             initialStatus: @js($statusFilter),
             initialSearch: @js($search),
             initialCounts: @js($counts),
             initialShipments: @js($initialOfflineShipments ?? []),
             availableParcels: @js($availableParcels),
             lookupUrl: @js(route('office.shipments.sent.lookup')),
             storeUrl: @js(route('office.shipments.sent.store')),
             senderOfficeLabel: @js($office->branch_name ? $office->name . ' - ' . $office->branch_name : $office->name)
         })">

        {{-- شريط تبويبات التصفية العلوي الموحّد (تصفية محلية 0ms بدون أي طلب شبكة) --}}
        @php
            $sentTabs = [
                ['key' => 'all',                'label' => 'الكل'],
                ['key' => 'draft',              'label' => 'في الانتظار'],
                ['key' => 'in_transit',         'label' => 'في الطريق'],
                ['key' => 'partially_received', 'label' => 'تسليم جزئي'],
                ['key' => 'received',           'label' => 'مكتملة'],
            ];
        @endphp
        {{-- 1. شريط تبويبات الجوال (< 1024px — مطابق للأصل 100%) --}}
        <nav class="flex lg:hidden overflow-x-auto no-scrollbar border-b border-slate-100 bg-white sticky top-[125px] z-20 text-xs font-bold text-center">
            @foreach ($sentTabs as $tabItem)
                <button type="button"
                        @click="setStatusFilter('{{ $tabItem['key'] }}')"
                        :class="activeStatus === '{{ $tabItem['key'] }}' ? 'border-b-2 border-secondary text-secondary' : 'text-slate-500'"
                        class="flex-1 shrink-0 whitespace-nowrap px-3 py-3 transition-colors">
                    <span>{{ $tabItem['label'] }}</span>
                    (<span x-text="counts.{{ $tabItem['key'] }}">{{ $counts[$tabItem['key']] ?? 0 }}</span>)
                </button>
            @endforeach
        </nav>

        {{-- 2. شريط أدوات سطح المكتب الاحترافي (lg: >= 1024px): Segmented Filter Pills + مبدّل (جدول / بطاقات) --}}
        <div class="hidden lg:flex items-center justify-between gap-4 px-6 pt-5 pb-1">
            <div class="inline-flex items-center gap-1 p-1 rounded-xl bg-gray-100 border border-gray-200 dark:bg-[#1C2434] dark:border-slate-700/60">
                @foreach ($sentTabs as $tabItem)
                    <button type="button"
                            @click="setStatusFilter('{{ $tabItem['key'] }}')"
                            :class="activeStatus === '{{ $tabItem['key'] }}'
                                ? 'bg-white text-slate-900 shadow-xs font-extrabold dark:bg-[#24303F] dark:text-slate-100'
                                : 'text-slate-600 hover:text-slate-900 font-bold dark:text-slate-400 dark:hover:text-slate-200'"
                            class="px-3.5 py-1.5 rounded-lg text-xs inline-flex items-center gap-1.5 transition-all">
                        <span>{{ $tabItem['label'] }}</span>
                        <span :class="activeStatus === '{{ $tabItem['key'] }}'
                                  ? 'bg-amber-50 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300'
                                  : 'bg-gray-200/80 text-slate-600 dark:bg-slate-800 dark:text-slate-400'"
                              class="px-1.5 py-0.5 rounded-md font-mono text-[11px] leading-none"
                              x-text="counts.{{ $tabItem['key'] }}">{{ $counts[$tabItem['key']] ?? 0 }}</span>
                    </button>
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
            {{-- 1. العرض الأولي من السيرفر قبل تهيئة Alpine / IndexedDB --}}
            <div x-show="!clientReady" x-ref="cardsContainer">
                @include('office.shipments.partials.sent-cards-list', ['shipments' => $shipments])
            </div>

            {{-- 2-أ. قائمة بطاقات الشحنات الحية من IndexedDB (دائماً على الجوال < 1024px، وعلى الكمبيوتر عند اختيار وضع البطاقات) --}}
            <div x-show="clientReady"
                 x-cloak
                 :class="desktopView === 'table' ? 'lg:hidden' : 'lg:space-y-0 lg:grid lg:grid-cols-2 2xl:grid-cols-3 lg:gap-4'"
                 class="space-y-2.5">
                <template x-for="shipment in filteredShipments" :key="shipment.local_key">
                    <article
                        style="content-visibility: auto; contain-intrinsic-size: 180px;"
                        class="relative bg-white lg:dark:bg-[#24303F] border border-slate-200/90 lg:border-gray-200 lg:dark:border-slate-700/60 lg:hover:shadow-md rounded-2xl p-3.5 lg:p-4 shadow-xs space-y-2.5 transition-all">

                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2.5 min-w-0 flex-1">
                                <span class="w-9 h-9 rounded-xl bg-primary lg:dark:bg-[#1A222C] text-secondary flex items-center justify-center shrink-0">
                                    <span class="material-symbols-outlined text-xl">receipt_long</span>
                                </span>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="font-mono font-black text-sm text-primary lg:dark:text-slate-100"
                                              x-text="'كشف #' + shipment.shipment_number"></span>
                                        <span class="px-1.5 py-0.5 rounded-md bg-slate-100 text-slate-700 lg:dark:bg-slate-800 lg:dark:text-slate-200 text-[10px] font-bold"
                                              x-text="Number(shipment.parcels_count || 0) + ' طرد'"></span>
                                        <template x-if="shipment.pending_sync || shipment.sync_status === 'pending_sync'">
                                            <span class="px-1.5 py-0.5 rounded-md bg-amber-100 text-amber-800 border border-amber-300 lg:dark:bg-amber-500/15 lg:dark:text-amber-300 lg:dark:border-amber-500/30 text-[10px] font-extrabold inline-flex items-center gap-1">
                                                <span class="material-symbols-outlined text-[12px]">cloud_upload</span>
                                                <span>بانتظار المزامنة</span>
                                            </span>
                                        </template>
                                    </div>
                                    <p class="text-[10px] text-slate-400 font-semibold mt-0.5"
                                       x-text="formatShipmentTime(shipment.created_at)"></p>
                                </div>
                            </div>

                            {{-- شارة الحالة + قائمة الثلاث نقاط (⋮) الحصرية لجميع إجراءات الكشف --}}
                            <div class="flex items-center gap-1.5 shrink-0">
                                <span class="px-2 py-0.5 rounded-md border text-[10px] font-extrabold"
                                      :class="statusBadgeClass(shipment.status)"
                                      x-text="statusLabel(shipment)"></span>

                                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                                    <button type="button"
                                        @click="open = !open"
                                        title="خيارات الكشف"
                                        aria-label="خيارات الكشف"
                                        class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 lg:dark:bg-slate-800 lg:dark:hover:bg-slate-700 lg:dark:text-slate-300 flex items-center justify-center transition-colors">
                                        <span class="material-symbols-outlined text-[18px]">more_vert</span>
                                    </button>

                                    <div x-show="open"
                                        x-cloak
                                        x-transition:enter="ease-out duration-150"
                                        x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                                        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                        x-transition:leave="ease-in duration-100"
                                        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                        x-transition:leave-end="opacity-0 -translate-y-1 scale-95"
                                        class="absolute left-0 top-full mt-1.5 w-44 bg-white lg:dark:bg-[#24303F] rounded-2xl shadow-lg border border-slate-200/90 lg:dark:border-slate-700/60 py-1.5 z-30 text-right">
                                        <button type="button"
                                            @click="open = false; openShipmentDetails(shipment)"
                                            class="w-full px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 lg:dark:text-slate-200 lg:dark:hover:bg-slate-700/50 flex items-center gap-2 transition-colors">
                                            <span class="material-symbols-outlined text-base text-primary lg:dark:text-amber-400">description</span>
                                            <span>تفاصيل الكشف</span>
                                        </button>

                                        <button type="button"
                                            :data-shipment-action="'print'"
                                            @click="open = false; printShipmentOffline(shipment)"
                                            class="w-full px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 lg:dark:text-slate-200 lg:dark:hover:bg-slate-700/50 flex items-center gap-2 transition-colors">
                                            <span class="material-symbols-outlined text-base text-slate-500 lg:dark:text-slate-400">print</span>
                                            <span>طباعة الكشف</span>
                                        </button>

                                        <template x-if="canCancelShipment(shipment)">
                                            <div>
                                                <div class="my-1 border-t border-slate-100 lg:dark:border-slate-700/60"></div>
                                                <button type="button"
                                                    :data-shipment-action="'cancel'"
                                                    @click="open = false; openCancelModal(shipment)"
                                                    class="w-full px-3.5 py-2 text-xs font-extrabold text-rose-600 hover:bg-rose-50 lg:dark:text-rose-400 lg:dark:hover:bg-rose-500/15 flex items-center gap-2 transition-colors">
                                                    <span class="material-symbols-outlined text-base text-rose-600 lg:dark:text-rose-400">cancel</span>
                                                    <span>إلغاء الكشف</span>
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2.5">
                            {{-- السائق والوجهات --}}
                            <div class="bg-slate-50 lg:dark:bg-[#1A222C] rounded-xl p-2.5 border border-slate-100 lg:dark:border-slate-700/50 space-y-1.5 text-xs">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-slate-400 text-[11px] font-bold flex items-center gap-1">
                                        <span class="material-symbols-outlined text-sm text-primary lg:dark:text-amber-400">person</span>
                                        السائق:
                                    </span>
                                    <span class="font-extrabold text-slate-800 lg:dark:text-slate-100 truncate">
                                        <span x-text="shipment.driver_name"></span>
                                        <span class="font-mono text-[11px] text-slate-500 lg:dark:text-slate-400 mr-1"
                                              dir="ltr"
                                              x-text="'(' + (shipment.driver_phone || '') + ')'"></span>
                                    </span>
                                </div>

                                <template x-if="Array.isArray(shipment.destinations_summary) && shipment.destinations_summary.length > 0">
                                    <div class="flex items-start justify-between gap-2 pt-1 border-t border-slate-200/60 lg:dark:border-slate-700/60">
                                        <span class="text-slate-400 text-[11px] font-bold shrink-0">الوجهات:</span>
                                        <div class="flex flex-wrap justify-end gap-1">
                                            <template x-for="dest in shipment.destinations_summary.slice(0, 3)" :key="dest">
                                                <span class="px-1.5 py-0.5 rounded-md bg-white border border-slate-200 lg:dark:bg-slate-800 lg:dark:border-slate-700 text-[10px] font-bold text-slate-700 lg:dark:text-slate-200"
                                                      x-text="dest"></span>
                                            </template>
                                            <template x-if="shipment.destinations_summary.length > 3">
                                                <span class="px-1.5 py-0.5 rounded-md bg-secondary/15 text-amber-800 lg:dark:bg-amber-500/20 lg:dark:text-amber-300 text-[10px] font-extrabold"
                                                      x-text="'+' + (shipment.destinations_summary.length - 3)"></span>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            {{-- المجاميع المالية (آجل / محاسب / إجمالي) --}}
                            <div class="grid grid-cols-3 gap-1.5 text-center">
                                <div class="bg-amber-50/70 border border-amber-200/70 lg:dark:bg-amber-500/10 lg:dark:border-amber-500/25 rounded-xl py-1.5 px-1">
                                    <p class="text-[9px] font-bold text-amber-700 lg:dark:text-amber-400">الآجل</p>
                                    <p class="text-xs font-mono font-black text-amber-800 lg:dark:text-amber-300"
                                       x-text="formatMoney(shipment.total_deferred_amount)"></p>
                                </div>
                                <div class="bg-emerald-50/70 border border-emerald-200/70 lg:dark:bg-emerald-500/10 lg:dark:border-emerald-500/25 rounded-xl py-1.5 px-1">
                                    <p class="text-[9px] font-bold text-emerald-700 lg:dark:text-emerald-400">المحاسب</p>
                                    <p class="text-xs font-mono font-black text-emerald-800 lg:dark:text-emerald-300"
                                       x-text="formatMoney(shipment.total_paid_amount)"></p>
                                </div>
                                <div class="bg-primary lg:dark:bg-[#1A222C] lg:dark:border lg:dark:border-slate-700/60 text-white rounded-xl py-1.5 px-1">
                                    <p class="text-[9px] font-bold text-slate-300 lg:dark:text-slate-400">الإجمالي</p>
                                    <p class="text-xs font-mono font-black text-secondary lg:dark:text-amber-400"
                                       x-text="formatMoney(shipment.total_amount)"></p>
                                </div>
                            </div>

                            {{-- مؤشر نسبة الاستلام --}}
                            <div class="flex items-center justify-between text-[11px] pt-0.5 text-slate-500 lg:dark:text-slate-400 font-bold">
                                <span x-text="'تم استلام ' + Number(shipment.received_parcels_count || 0) + ' من ' + Number(shipment.parcels_count || 0)"></span>
                            </div>
                        </div>
                    </article>
                </template>

                {{-- حالة عدم وجود نتائج مطابقة في الذاكرة المحلية --}}
                <div x-show="filteredShipments.length === 0"
                     class="lg:col-span-full bg-white lg:dark:bg-[#24303F] rounded-2xl border border-slate-200/80 lg:border-gray-200 lg:dark:border-slate-700/60 p-10 text-center space-y-2.5">
                    <div class="w-14 h-14 mx-auto rounded-2xl bg-slate-100 lg:dark:bg-slate-800 text-slate-400 flex items-center justify-center">
                        <span class="material-symbols-outlined text-3xl">local_shipping</span>
                    </div>
                    <p class="text-xs font-extrabold text-slate-700 lg:dark:text-slate-200">لا توجد شحنات مرسلة مطابقة</p>
                    <p class="text-[11px] text-slate-400">اضغط على زر (+) أعلى الشاشة لإنشاء كشف شحنة جديد بمسح الباركود.</p>
                    <div class="pt-1">
                        <button type="button"
                            @click="openCreateModal()"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-secondary hover:brightness-105 text-primary text-xs font-extrabold shadow-xs active:scale-95 transition-all">
                            <span class="material-symbols-outlined text-base">qr_code_scanner</span>
                            <span>كشف شحنة جديد</span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- 2-ب. وضع الجدول الاحترافي لسطح المكتب للشحنات المرسلة (lg: >= 1024px عند اختيار وضع الجدول — يعمل 100% أوفلاين) --}}
            <div x-show="clientReady && desktopView === 'table'"
                 x-cloak
                 class="hidden lg:block">
                <div x-show="filteredShipments.length === 0"
                     class="bg-white dark:bg-[#24303F] rounded-2xl border border-gray-200 dark:border-slate-700/60 p-10 text-center space-y-2.5 shadow-2xs">
                    <div class="w-14 h-14 mx-auto rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center">
                        <span class="material-symbols-outlined text-3xl">local_shipping</span>
                    </div>
                    <p class="text-xs font-extrabold text-slate-700 dark:text-slate-200">لا توجد شحنات مرسلة مطابقة</p>
                    <p class="text-[11px] text-slate-400">اضغط على (+ كشف شحنة جديد) أعلى الشاشة لإنشاء كشف بمسح الباركود.</p>
                </div>

                <div x-show="filteredShipments.length > 0"
                     class="bg-white dark:bg-[#24303F] rounded-2xl border border-gray-200 dark:border-slate-700/60 shadow-2xs overflow-visible">
                    <table class="w-full text-right border-collapse">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-slate-700/60 bg-gray-50/80 dark:bg-slate-800/60 text-[11px] font-extrabold text-slate-500 dark:text-slate-400">
                                <th class="py-3 pr-4 pl-3 rounded-tr-2xl">رقم الكشف والوقت</th>
                                <th class="py-3 px-3">السائق</th>
                                <th class="py-3 px-3">الوجهات</th>
                                <th class="py-3 px-3">المجاميع المالية (ر.ي)</th>
                                <th class="py-3 px-3">الحالة والاستلام</th>
                                <th class="py-3 pr-3 pl-4 text-left rounded-tl-2xl">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-slate-700/50 text-xs">
                            <template x-for="shipment in filteredShipments" :key="'row-' + shipment.local_key">
                                <tr x-data="{ open: false }"
                                    :class="open ? 'relative z-30 bg-gray-50/90 dark:bg-slate-800/60' : 'hover:bg-gray-50/70 dark:hover:bg-slate-800/40'"
                                    class="transition-colors">

                                    {{-- 1. رقم الكشف والوقت --}}
                                    <td class="py-3 pr-4 pl-3 align-middle">
                                        <div class="flex items-center gap-2.5">
                                            <span class="w-1.5 h-8 rounded-full shrink-0" :class="rowAccentDot(shipment.status)"></span>
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <span class="font-mono font-black text-slate-900 dark:text-slate-100"
                                                          x-text="'كشف #' + shipment.shipment_number"></span>
                                                    <span class="px-1.5 py-0.5 rounded-md bg-slate-100 text-slate-700 border border-slate-200/80 dark:bg-slate-800 dark:text-slate-200 dark:border-slate-700 text-[10px] font-bold"
                                                          x-text="Number(shipment.parcels_count || 0) + ' طرد'"></span>
                                                    <template x-if="shipment.pending_sync || shipment.sync_status === 'pending_sync'">
                                                        <span class="px-1.5 py-0.5 rounded-md bg-amber-100 text-amber-800 border border-amber-300 dark:bg-amber-500/15 dark:text-amber-300 dark:border-amber-500/30 text-[10px] font-extrabold inline-flex items-center gap-0.5">
                                                            <span class="material-symbols-outlined text-[12px]">cloud_upload</span>
                                                            <span>بانتظار المزامنة</span>
                                                        </span>
                                                    </template>
                                                </div>
                                                <p class="text-[10px] text-slate-400 mt-0.5"
                                                   x-text="formatShipmentTime(shipment.created_at)"></p>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- 2. السائق --}}
                                    <td class="py-3 px-3 align-middle">
                                        <p class="font-bold text-slate-800 dark:text-slate-100 truncate max-w-[155px]" x-text="shipment.driver_name || '—'"></p>
                                        <p class="text-[11px] font-mono text-slate-500 dark:text-slate-400 dir-ltr text-right" x-text="shipment.driver_phone || ''"></p>
                                    </td>

                                    {{-- 3. الوجهات --}}
                                    <td class="py-3 px-3 align-middle">
                                        <div class="flex flex-wrap items-center gap-1">
                                            <template x-if="Array.isArray(shipment.destinations_summary) && shipment.destinations_summary.length > 0">
                                                <div class="flex flex-wrap items-center gap-1">
                                                    <template x-for="dest in shipment.destinations_summary.slice(0, 3)" :key="'d-' + dest">
                                                        <span class="px-2 py-0.5 rounded-md bg-slate-100 border border-slate-200/80 dark:bg-slate-800 dark:border-slate-700 text-[11px] font-bold text-slate-700 dark:text-slate-200"
                                                              x-text="dest"></span>
                                                    </template>
                                                    <template x-if="shipment.destinations_summary.length > 3">
                                                        <span class="px-1.5 py-0.5 rounded-md bg-secondary/15 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300 text-[10px] font-extrabold"
                                                              x-text="'+' + (shipment.destinations_summary.length - 3)"></span>
                                                    </template>
                                                </div>
                                            </template>
                                            <template x-if="!Array.isArray(shipment.destinations_summary) || shipment.destinations_summary.length === 0">
                                                <span class="text-slate-300 dark:text-slate-600 font-bold">—</span>
                                            </template>
                                        </div>
                                    </td>

                                    {{-- 4. المجاميع المالية --}}
                                    <td class="py-3 px-3 align-middle whitespace-nowrap">
                                        <div class="flex items-center gap-1.5 text-[11px] font-mono">
                                            <span class="px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-200/80 dark:bg-amber-500/15 dark:text-amber-300 dark:border-amber-500/30 font-extrabold"
                                                  :title="'الآجل'"
                                                  x-text="'آجل: ' + formatMoney(shipment.total_deferred_amount)"></span>
                                            <span class="px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200/80 dark:bg-emerald-500/15 dark:text-emerald-400 dark:border-emerald-500/30 font-extrabold"
                                                  :title="'المحاسب'"
                                                  x-text="'محاسب: ' + formatMoney(shipment.total_paid_amount)"></span>
                                            <span class="px-2 py-0.5 rounded-md bg-slate-900 dark:bg-slate-800 dark:border dark:border-slate-700 text-secondary font-black"
                                                  :title="'الإجمالي'"
                                                  x-text="'إجمالي: ' + formatMoney(shipment.total_amount)"></span>
                                        </div>
                                    </td>

                                    {{-- 5. الحالة والاستلام --}}
                                    <td class="py-3 px-3 align-middle whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-md border text-[11px] font-extrabold"
                                              :class="statusBadgeClass(shipment.status)"
                                              x-text="statusLabel(shipment)"></span>
                                        <p class="text-[10px] font-bold text-slate-400 mt-0.5"
                                           x-text="'استُلم ' + Number(shipment.received_parcels_count || 0) + ' من ' + Number(shipment.parcels_count || 0)"></p>
                                    </td>

                                    {{-- 6. الإجراءات المباشرة: طباعة الكشف A4 + التفاصيل + قائمة ⋮ للإلغاء --}}
                                    <td class="py-3 pr-3 pl-4 align-middle whitespace-nowrap text-left">
                                        <div class="inline-flex items-center justify-end gap-1.5">
                                            <button type="button"
                                                    @click="printShipmentOffline(shipment)"
                                                    class="h-8 px-3 rounded-lg bg-gray-100 hover:bg-primary text-slate-700 hover:text-secondary border border-gray-200 hover:border-primary dark:bg-[#1A222C] dark:hover:bg-amber-500 dark:text-slate-200 dark:hover:text-slate-950 dark:border-slate-700 dark:hover:border-amber-500 text-xs font-extrabold inline-flex items-center gap-1.5 transition-all">
                                                <span class="material-symbols-outlined text-[16px]">print</span>
                                                <span>طباعة الكشف</span>
                                            </button>

                                            <button type="button"
                                                    @click="openShipmentDetails(shipment)"
                                                    class="h-8 px-2.5 rounded-lg bg-white hover:bg-gray-100 text-slate-700 border border-gray-200 dark:bg-[#1A222C] dark:hover:bg-[#333A48] dark:text-slate-200 dark:border-slate-700 text-xs font-bold inline-flex items-center gap-1 transition-colors">
                                                <span class="material-symbols-outlined text-[16px] text-primary dark:text-amber-400">description</span>
                                                <span>التفاصيل</span>
                                            </button>

                                            <template x-if="canCancelShipment(shipment)">
                                                <div class="relative" @click.outside="open = false" @keydown.escape.window="open = false">
                                                    <button type="button"
                                                            @click="open = !open"
                                                            title="إجراءات إضافية"
                                                            class="w-8 h-8 rounded-lg bg-white hover:bg-gray-100 text-slate-600 border border-gray-200 dark:bg-[#1A222C] dark:hover:bg-[#333A48] dark:text-slate-300 dark:border-slate-700 flex items-center justify-center transition-colors">
                                                        <span class="material-symbols-outlined text-[18px]">more_vert</span>
                                                    </button>

                                                    <div x-show="open"
                                                         x-cloak
                                                         x-transition:enter="ease-out duration-150"
                                                         x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                                                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                                         x-transition:leave="ease-in duration-100"
                                                         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                                         x-transition:leave-end="opacity-0 -translate-y-1 scale-95"
                                                         class="absolute left-0 top-full mt-1.5 w-44 bg-white dark:bg-[#24303F] rounded-2xl shadow-xl border border-gray-200 dark:border-slate-700/60 py-1.5 z-40 text-right">
                                                        <button type="button"
                                                                @click="open = false; openCancelModal(shipment)"
                                                                class="w-full px-3.5 py-2 text-xs font-extrabold text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/15 flex items-center gap-2 transition-colors">
                                                            <span class="material-symbols-outlined text-base text-rose-600 dark:text-rose-400">cancel</span>
                                                            <span>إلغاء الكشف</span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

        {{-- مودال إنشاء كشف شحنة جديد بالمسح بالباركود (محسّن لكيبورد الموبايل بـ visualViewport وبدون backdrop-blur للأداء) --}}
        <div x-show="createModalOpen"
             x-cloak
             @keydown.escape.window="closeCreateModal()"
             @click.self="closeCreateModal()"
             :style="{ height: viewportHeight + 'px', top: viewportTop + 'px' }"
             class="fixed left-0 right-0 z-50 bg-slate-950/60 flex items-start justify-center pt-1.5 px-1.5 sm:items-center sm:p-4">

            <div :style="{ maxHeight: Math.max(260, viewportHeight - 12) + 'px' }"
                 class="bg-white lg:dark:bg-[#24303F] w-full max-w-lg rounded-2xl flex flex-col shadow-2xl border border-slate-200 lg:dark:border-slate-700/60 lg:dark:text-slate-100 overflow-hidden">

                {{-- هيدر المودال المثبّت --}}
                <div class="py-3 px-4 bg-primary lg:dark:bg-[#1C2434] lg:dark:border-b lg:dark:border-slate-700/60 text-white flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-secondary text-xl">qr_code_scanner</span>
                        <div>
                            <h3 class="text-sm font-extrabold leading-tight">إنشاء كشف شحنة جديد</h3>
                            <p class="text-[10px] text-slate-300" x-text="'الفرع المرسل: ' + senderOfficeLabel"></p>
                        </div>
                    </div>
                    <button type="button" @click="closeCreateModal()" class="text-slate-300 hover:text-white p-1">
                        <span class="material-symbols-outlined text-lg">close</span>
                    </button>
                </div>

                {{-- نموذج إنشاء الشحنة بسلسلة تمرير Flex سليمة (min-h-0) --}}
                <form data-offline-capable="true" @submit.prevent="submitShipment()" class="flex flex-col flex-1 min-h-0">

                    {{-- جسم المودال القابل للتمرير فوق الكيبورد --}}
                    <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain touch-pan-y px-4 pt-3 pb-6 space-y-3"
                         style="-webkit-overflow-scrolling: touch;"
                         @focusin="if ($event.target.matches('input, textarea, select')) { setTimeout(() => $event.target.scrollIntoView({ behavior: 'smooth', block: 'center' }), 180); }">

                    {{-- 1. بيانات السائق --}}
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 lg:dark:text-slate-300 mb-1">اسم السائق <span
                                    class="text-rose-500">*</span></label>
                            <input type="text" x-model="driverName" placeholder="اسم السائق"
                                class="w-full py-2 px-3 text-xs font-bold text-slate-800 lg:dark:text-slate-100 rounded-xl border border-slate-200 lg:dark:border-slate-700/60 bg-slate-50 lg:dark:bg-[#1A222C] focus:bg-white lg:dark:focus:bg-[#1A222C] outline-none focus:border-secondary">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 lg:dark:text-slate-300 mb-1">رقم السائق <span
                                    class="text-rose-500">*</span></label>
                            <input type="tel" x-model="driverPhone" @input="driverPhone = sanitizePhone(driverPhone)"
                                dir="ltr" inputmode="numeric" maxlength="9" placeholder="7XXXXXXXX"
                                class="w-full py-2 px-3 text-xs font-mono font-bold text-slate-800 lg:dark:text-slate-100 rounded-xl border bg-slate-50 lg:dark:bg-[#1A222C] focus:bg-white lg:dark:focus:bg-[#1A222C] outline-none focus:border-secondary"
                                :class="driverPhone.length > 0 && !isValidPhone(driverPhone) ? 'border-rose-300 lg:dark:border-rose-500/60' :
                                    'border-slate-200 lg:dark:border-slate-700/60'">
                        </div>
                    </div>

                    {{-- 2. منطقة مسح الباركود (كاميرا + قارئ خارجي / إدخال يدوي — يعمل 100% أوفلاين من IndexedDB) --}}
                    <div class="bg-slate-50 lg:dark:bg-[#1A222C] border border-slate-200 lg:dark:border-slate-700/60 rounded-2xl p-3 space-y-2.5">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-extrabold text-primary lg:dark:text-slate-100 flex items-center gap-1">
                                <span class="material-symbols-outlined text-base text-secondary">barcode_scanner</span>
                                <span>مسح باركود الطرود (مانع للتكرار)</span>
                            </span>
                            <button type="button" @click="toggleCamera()"
                                :class="cameraActive ? 'bg-rose-600 text-white' : 'bg-primary text-secondary'"
                                class="px-2.5 py-1.5 rounded-xl text-[11px] font-extrabold flex items-center gap-1 transition-colors">
                                <span class="material-symbols-outlined text-sm"
                                    x-text="cameraActive ? 'videocam_off' : 'photo_camera'"></span>
                                <span x-text="cameraActive ? 'إيقاف الكاميرا' : 'فتح الكاميرا'"></span>
                            </button>
                        </div>

                        {{-- نافذة الكاميرا --}}
                        <div x-show="cameraActive" x-cloak
                            class="rounded-xl overflow-hidden border border-slate-300 bg-black">
                            <div id="sent-shipment-qr-reader" class="w-full"></div>
                        </div>

                        {{-- حقل قارئ الباركود USB/Bluetooth أو إدخال رقم السند --}}
                        <div class="flex gap-1.5">
                            <input type="text" x-ref="scanInput" x-model="scanCodeInput"
                                @keydown.enter.prevent="handleScan(scanCodeInput)" dir="ltr" autocomplete="off"
                                placeholder="امسح بالباركود أو اكتب رقم السند ثم Enter"
                                class="flex-1 py-2 px-3 text-xs font-mono font-bold text-slate-800 lg:dark:text-slate-100 rounded-xl border border-slate-300 lg:dark:border-slate-700 bg-white lg:dark:bg-[#24303F] outline-none focus:border-secondary">
                            <button type="button" @click="handleScan(scanCodeInput)" :disabled="isLookingUp"
                                class="px-3 py-2 rounded-xl bg-primary text-secondary text-xs font-extrabold hover:opacity-95 disabled:opacity-50 shrink-0">
                                <span x-text="isLookingUp ? '...' : 'إضافة'"></span>
                            </button>
                        </div>

                        {{-- شريط حالة آخر مسح (نجاح / تحذير تكرار / خطأ) --}}
                        <div x-show="scanFeedback.text" x-cloak
                            :class="{
                                'bg-emerald-50 border-emerald-200 text-emerald-800 lg:dark:bg-emerald-500/15 lg:dark:border-emerald-500/30 lg:dark:text-emerald-300': scanFeedback.type === 'success',
                                'bg-amber-50 border-amber-300 text-amber-900 lg:dark:bg-amber-500/15 lg:dark:border-amber-500/30 lg:dark:text-amber-300': scanFeedback.type === 'duplicate',
                                'bg-rose-50 border-rose-200 text-rose-800 lg:dark:bg-rose-500/15 lg:dark:border-rose-500/30 lg:dark:text-rose-300': scanFeedback.type === 'error'
                            }"
                            class="px-2.5 py-2 rounded-xl border text-[11px] font-extrabold flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-base shrink-0"
                                x-text="scanFeedback.type === 'success' ? 'check_circle' : (scanFeedback.type === 'duplicate' ? 'block' : 'error')"></span>
                            <span x-text="scanFeedback.text"></span>
                        </div>
                    </div>

                    {{-- 3. المجاميع الحية والوجهات داخل الشحنة --}}
                    <div class="bg-primary lg:dark:bg-[#1A222C] lg:dark:border lg:dark:border-slate-700/60 text-white rounded-2xl p-3 space-y-2">
                        <div class="grid grid-cols-4 gap-1.5 text-center">
                            <div class="bg-white/10 rounded-xl py-1.5 px-1">
                                <p class="text-[9px] text-slate-300 font-bold">الطرود</p>
                                <p class="text-sm font-black text-white" x-text="selectedParcels.length"></p>
                            </div>
                            <div class="bg-white/10 rounded-xl py-1.5 px-1">
                                <p class="text-[9px] text-amber-300 font-bold">الآجل</p>
                                <p class="text-xs font-mono font-black text-amber-300"
                                    x-text="formatMoney(totals.deferred)"></p>
                            </div>
                            <div class="bg-white/10 rounded-xl py-1.5 px-1">
                                <p class="text-[9px] text-emerald-300 font-bold">المحاسب</p>
                                <p class="text-xs font-mono font-black text-emerald-300"
                                    x-text="formatMoney(totals.paid)"></p>
                            </div>
                            <div class="bg-secondary/20 border border-secondary/40 rounded-xl py-1.5 px-1">
                                <p class="text-[9px] text-secondary font-bold">الإجمالي</p>
                                <p class="text-xs font-mono font-black text-secondary" x-text="formatMoney(totals.grand)">
                                </p>
                            </div>
                        </div>

                        <template x-if="uniqueDestinations.length > 0">
                            <div class="flex items-center gap-1 flex-wrap pt-1 border-t border-white/10">
                                <span class="text-[10px] text-slate-300 font-bold ml-1">الوجهات:</span>
                                <template x-for="dest in uniqueDestinations" :key="dest">
                                    <span class="px-2 py-0.5 rounded-md bg-white/10 text-[10px] font-bold text-white"
                                        x-text="dest"></span>
                                </template>
                            </div>
                        </template>
                    </div>

                    {{-- 4. قائمة الطرود الممسوحة داخل الكشف --}}
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-extrabold text-slate-700 lg:dark:text-slate-200">
                                الطرود المضمومة (<span x-text="selectedParcels.length"></span>)
                            </h4>
                            <button type="button" @click="showManualPicker = !showManualPicker"
                                class="text-[11px] font-extrabold text-sky-700 hover:underline flex items-center gap-0.5">
                                <span class="material-symbols-outlined text-sm">checklist</span>
                                <span
                                    x-text="showManualPicker ? 'إخفاء الاختيار اليدوي' : 'اختيار يدوي من المتاح (' + unaddedAvailable.length + ')'"></span>
                            </button>
                        </div>

                        {{-- اختيار يدوي بديل في حال تلف ملصق الباركود --}}
                        <div x-show="showManualPicker" x-cloak
                            class="bg-sky-50/70 lg:dark:bg-sky-500/10 border border-sky-200 lg:dark:border-sky-500/30 rounded-xl p-2 max-h-40 overflow-y-auto space-y-1">
                            <template x-if="unaddedAvailable.length === 0">
                                <p class="text-[11px] text-slate-500 text-center py-2 font-bold">لا توجد طرود إضافية في
                                    الانتظار.</p>
                            </template>
                            <template x-for="p in unaddedAvailable" :key="p.id">
                                <button type="button" @click="addParcelObject(p)"
                                    class="w-full text-right bg-white lg:dark:bg-[#24303F] hover:bg-sky-100/60 lg:dark:hover:bg-sky-500/15 border border-slate-200 lg:dark:border-slate-700/60 lg:dark:text-slate-200 rounded-lg px-2.5 py-1.5 flex items-center justify-between gap-2 text-[11px]">
                                    <div class="min-w-0">
                                        <span class="font-mono font-black text-primary lg:dark:text-slate-100"
                                            x-text="'#' + p.receipt_number"></span>
                                        <span class="font-bold text-slate-700 lg:dark:text-slate-200 mr-1" x-text="p.package_type"></span>
                                        <span class="text-slate-400 block truncate text-[10px]"
                                            x-text="(p.destination_label || p.destination) + ' • ' + p.recipient_name"></span>
                                    </div>
                                    <span class="px-2 py-0.5 rounded-md bg-sky-600 text-white font-extrabold shrink-0">+
                                        ضم</span>
                                </button>
                            </template>
                        </div>

                        {{-- الطرود الممسوحة فعلياً --}}
                        <template x-if="selectedParcels.length === 0">
                            <div
                                class="border border-dashed border-slate-300 lg:dark:border-slate-700 rounded-xl p-4 text-center text-[11px] text-slate-400 lg:dark:text-slate-500 font-bold">
                                امسح باركود أول طرد لتبدأ بتجهيز الشحنة
                            </div>
                        </template>

                        <div class="space-y-1.5 max-h-48 overflow-y-auto">
                            <template x-for="(p, idx) in selectedParcels" :key="p.id">
                                <div
                                    class="bg-white lg:dark:bg-[#1A222C] border border-slate-200 lg:dark:border-slate-700/60 rounded-xl px-2.5 py-2 flex items-center justify-between gap-2 text-xs">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span
                                                class="px-1.5 py-0.5 rounded bg-slate-100 lg:dark:bg-slate-800 font-mono text-[10px] font-black text-primary lg:dark:text-slate-200"
                                                x-text="'#' + p.receipt_number"></span>
                                            <span class="font-extrabold text-slate-800 lg:dark:text-slate-100 truncate"
                                                x-text="p.package_type"></span>
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-extrabold"
                                                :class="p.payment_type === 'deferred' ? 'bg-amber-50 text-amber-800 lg:dark:bg-amber-500/15 lg:dark:text-amber-300' :
                                                    'bg-emerald-50 text-emerald-700 lg:dark:bg-emerald-500/15 lg:dark:text-emerald-300'"
                                                x-text="p.payment_label + ': ' + formatMoney(p.cod_amount)"></span>
                                        </div>
                                        <p class="text-[10px] text-slate-400 truncate mt-0.5"
                                            x-text="(p.destination_label || p.destination) + ' • ' + p.recipient_name + ' (' + p.recipient_phone + ')'">
                                        </p>
                                    </div>
                                    <button type="button" @click="removeParcel(p.id)" title="إزالة من الشحنة"
                                        class="w-7 h-7 rounded-lg bg-rose-50 hover:bg-rose-100 lg:dark:bg-rose-500/15 lg:dark:hover:bg-rose-500/25 text-rose-600 lg:dark:text-rose-400 flex items-center justify-center shrink-0">
                                        <span class="material-symbols-outlined text-base">delete</span>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- 5. ملاحظات + خيار الشحن الفوري --}}
                    <div class="space-y-2">
                        <textarea x-model="notes" rows="2" placeholder="ملاحظات على الكشف (اختياري)..."
                            class="w-full py-2 px-3 text-xs font-bold text-slate-800 lg:dark:text-slate-100 rounded-xl border border-slate-200 lg:dark:border-slate-700/60 bg-slate-50 lg:dark:bg-[#1A222C] focus:bg-white lg:dark:focus:bg-[#1A222C] outline-none focus:border-secondary"></textarea>

                        <label class="flex items-center gap-2 text-xs font-bold text-slate-700 lg:dark:text-slate-200 cursor-pointer select-none">
                            <input type="checkbox" x-model="dispatchNow" style="accent-color: var(--office-secondary, #fe9d20);" class="w-4 h-4 rounded">
                            <span>شحن الكشف فوراً (تحويل جميع الطرود إلى «في الطريق»)</span>
                        </label>
                    </div>
                </div>

                    {{-- فوتر ثابت لحفظ الكشف (يظل مرئياً دائماً فوق الكيبورد) --}}
                    <div class="shrink-0 border-t border-slate-100 lg:dark:border-slate-700/60 px-4 py-2.5 bg-white lg:dark:bg-[#24303F] flex items-center gap-2">
                        <button type="submit"
                            :disabled="submitting || selectedParcels.length === 0"
                            class="flex-1 py-2.5 px-4 rounded-xl bg-secondary hover:brightness-105 text-primary text-xs font-extrabold shadow-md shadow-secondary/20 flex items-center justify-center gap-1.5 transition-all disabled:opacity-50">
                            <span class="material-symbols-outlined text-base">check_circle</span>
                            <span
                                x-text="submitting ? 'جاري حفظ الكشف...' : (dispatchNow ? 'اعتماد وشحن الكشف' : 'حفظ كمسودة')"></span>
                        </button>
                        <button type="button" @click="closeCreateModal()"
                            class="py-2.5 px-4 rounded-xl border border-slate-200 lg:dark:border-slate-700 text-slate-600 lg:dark:text-slate-300 text-xs font-bold hover:bg-slate-50 lg:dark:hover:bg-slate-800">
                            إغلاق
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- مودال تفاصيل الكشف المحلي (يعمل 100% أوفلاين وللشحنات المحلية قبل المزامنة بدون backdrop-blur) --}}
        <div x-show="detailsModal.open"
             x-cloak
             @keydown.escape.window="closeDetailsModal()"
             class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-2 sm:p-4">
            <div class="fixed inset-0 bg-slate-950/60" @click="closeDetailsModal()"></div>

            <div class="relative w-full max-w-lg max-h-[88vh] bg-white lg:dark:bg-[#24303F] rounded-3xl shadow-2xl border border-slate-200 lg:dark:border-slate-700/60 lg:dark:text-slate-100 z-10 flex flex-col overflow-hidden text-right">
                <div class="py-3 px-4 bg-primary lg:dark:bg-[#1C2434] lg:dark:border-b lg:dark:border-slate-700/60 text-white flex items-center justify-between shrink-0">
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <h3 class="text-sm font-extrabold" x-text="'تفاصيل كشف #' + (detailsModal.shipment?.shipment_number || '')"></h3>
                            <template x-if="detailsModal.shipment?.pending_sync">
                                <span class="px-1.5 py-0.5 rounded bg-amber-400/20 text-amber-300 border border-amber-400/40 text-[10px] font-extrabold">
                                    بانتظار المزامنة
                                </span>
                            </template>
                        </div>
                        <p class="text-[10px] text-slate-300 mt-0.5"
                           x-text="'السائق: ' + (detailsModal.shipment?.driver_name || '—') + ' (' + (detailsModal.shipment?.driver_phone || '') + ')'"></p>
                    </div>
                    <button type="button" @click="closeDetailsModal()" class="text-slate-300 hover:text-white p-1">
                        <span class="material-symbols-outlined text-lg">close</span>
                    </button>
                </div>

                <div class="flex-1 min-h-0 overflow-y-auto p-4 space-y-3">
                    {{-- ملخص مالي وحالة الكشف --}}
                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div class="bg-amber-50 lg:dark:bg-amber-500/15 border border-amber-200 lg:dark:border-amber-500/30 rounded-xl py-2 px-1">
                            <p class="text-[10px] font-bold text-amber-700 lg:dark:text-amber-300">الآجل</p>
                            <p class="text-xs font-mono font-black text-amber-800"
                               x-text="formatMoney(detailsModal.shipment?.total_deferred_amount) + ' ر.ي'"></p>
                        </div>
                        <div class="bg-emerald-50 lg:dark:bg-emerald-500/15 border border-emerald-200 lg:dark:border-emerald-500/30 rounded-xl py-2 px-1">
                            <p class="text-[10px] font-bold text-emerald-700 lg:dark:text-emerald-300">المحاسب</p>
                            <p class="text-xs font-mono font-black text-emerald-800"
                               x-text="formatMoney(detailsModal.shipment?.total_paid_amount) + ' ر.ي'"></p>
                        </div>
                        <div class="bg-primary lg:dark:bg-[#1A222C] lg:dark:border lg:dark:border-slate-700/60 text-white rounded-xl py-2 px-1">
                            <p class="text-[10px] font-bold text-slate-300">الإجمالي</p>
                            <p class="text-xs font-mono font-black text-secondary"
                               x-text="formatMoney(detailsModal.shipment?.total_amount) + ' ر.ي'"></p>
                        </div>
                    </div>

                    <template x-if="detailsModal.shipment?.notes">
                        <div class="bg-slate-50 lg:dark:bg-[#1A222C] border border-slate-200 lg:dark:border-slate-700/60 rounded-xl px-3 py-2 text-xs">
                            <span class="font-extrabold text-slate-700 lg:dark:text-slate-200">ملاحظات: </span>
                            <span class="text-slate-600 lg:dark:text-slate-300" x-text="detailsModal.shipment.notes"></span>
                        </div>
                    </template>

                    {{-- جدول الطرود المضمّنة في الكشف --}}
                    <div class="space-y-1.5">
                        <h4 class="text-xs font-extrabold text-slate-800 lg:dark:text-slate-100">
                            بيان الطرود في الكشف (<span x-text="(detailsModal.shipment?.parcels || []).length"></span>)
                        </h4>

                        <div class="space-y-1.5">
                            <template x-for="(p, idx) in (detailsModal.shipment?.parcels || [])" :key="p.id || idx">
                                <div class="bg-slate-50 lg:dark:bg-[#1A222C] border border-slate-200 lg:dark:border-slate-700/60 rounded-xl p-2.5 text-xs space-y-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="px-1.5 py-0.5 rounded bg-white lg:dark:bg-[#24303F] border border-slate-200 lg:dark:border-slate-700 font-mono text-[10px] font-black text-primary lg:dark:text-slate-100"
                                                  x-text="'سند #' + (p.receipt_number || '—')"></span>
                                            <span class="font-extrabold text-slate-800 lg:dark:text-slate-100" x-text="p.package_type || 'طرد'"></span>
                                        </div>
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-extrabold"
                                              :class="p.payment_type === 'deferred' ? 'bg-amber-100 text-amber-800 lg:dark:bg-amber-500/20 lg:dark:text-amber-300' : 'bg-emerald-100 text-emerald-800 lg:dark:bg-emerald-500/20 lg:dark:text-emerald-300'"
                                              x-text="(p.payment_label || (p.payment_type === 'deferred' ? 'آجل' : 'محاسب')) + ': ' + formatMoney(p.cod_amount) + ' ر.ي'"></span>
                                    </div>
                                    <div class="flex items-center justify-between text-[11px] text-slate-600 lg:dark:text-slate-300">
                                        <span class="font-bold truncate" x-text="(p.recipient_name || 'بدون اسم') + ' (' + (p.recipient_phone || '') + ')'"></span>
                                        <span class="text-[10px] text-slate-500 lg:dark:text-slate-400 font-bold shrink-0" x-text="p.destination_label || p.destination || ''"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="shrink-0 border-t border-slate-100 lg:dark:border-slate-700/60 px-4 py-3 bg-white lg:dark:bg-[#24303F] flex items-center gap-2">
                    <button type="button"
                            @click="printShipmentOffline(detailsModal.shipment)"
                            class="flex-1 py-2.5 px-4 rounded-xl bg-primary hover:opacity-95 text-secondary text-xs font-extrabold flex items-center justify-center gap-1.5 transition-all">
                        <span class="material-symbols-outlined text-base">print</span>
                        <span>طباعة الكشف (A4)</span>
                    </button>
                    <template x-if="detailsModal.shipment && canCancelShipment(detailsModal.shipment)">
                        <button type="button"
                                @click="const s = detailsModal.shipment; closeDetailsModal(); openCancelModal(s);"
                                class="py-2.5 px-3 rounded-xl bg-rose-50 hover:bg-rose-100 lg:dark:bg-rose-500/15 lg:dark:hover:bg-rose-500/25 text-rose-700 lg:dark:text-rose-300 text-xs font-extrabold flex items-center justify-center gap-1 transition-colors">
                            <span class="material-symbols-outlined text-base">cancel</span>
                            <span>إلغاء الكشف</span>
                        </button>
                    </template>
                    <button type="button"
                            @click="closeDetailsModal()"
                            class="py-2.5 px-3 rounded-xl border border-slate-200 lg:dark:border-slate-700 text-slate-600 lg:dark:text-slate-300 text-xs font-bold hover:bg-slate-50 lg:dark:hover:bg-slate-800">
                        إغلاق
                    </button>
                </div>
            </div>
        </div>

        {{-- مودال تأكيد إلغاء كشف الشحنة (يعمل أونلاين وأوفلاين، بدون backdrop-blur للأداء) --}}
        <div x-show="cancelModal.open"
             x-cloak
             @keydown.escape.window="closeCancelModal()"
             class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-3 sm:p-4">
            <div class="fixed inset-0 bg-slate-950/60" @click="closeCancelModal()"></div>

            <div class="relative w-full max-w-sm bg-white lg:dark:bg-[#24303F] rounded-3xl p-5 shadow-2xl border border-slate-100 lg:dark:border-slate-700/60 lg:dark:text-slate-100 z-10 flex flex-col gap-4 text-right">
                <button type="button" @click="closeCancelModal()" :disabled="cancelModal.submitting"
                        title="إغلاق"
                        class="absolute left-4 top-4 w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 lg:dark:bg-slate-800 lg:dark:hover:bg-slate-700 text-slate-400 hover:text-slate-600 lg:dark:hover:text-slate-200 flex items-center justify-center transition-colors disabled:opacity-50">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>

                <div class="text-center pt-1">
                    <div class="w-14 h-14 mx-auto rounded-2xl bg-rose-50 lg:dark:bg-rose-500/15 border border-rose-100 lg:dark:border-rose-500/30 text-rose-600 lg:dark:text-rose-400 flex items-center justify-center shadow-inner mb-3">
                        <span class="material-symbols-outlined text-3xl">cancel</span>
                    </div>
                    <h3 class="text-base font-extrabold text-slate-800 lg:dark:text-slate-100">إلغاء كشف الشحنة</h3>
                    <p class="text-xs text-slate-500 lg:dark:text-slate-400 mt-1 leading-relaxed"
                       x-text="'سيتم إلغاء الكشف #' + (cancelModal.number || '') + ' وتنزيل جميع الطرود بداخله وإرجاعها إلى حالة (في الانتظار).'"></p>
                </div>

                <div class="bg-slate-50 lg:dark:bg-[#1A222C] rounded-2xl p-3 border border-slate-200/80 lg:dark:border-slate-700/60 space-y-1.5 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">رقم الكشف:</span>
                        <span class="font-mono font-black text-rose-600" x-text="'#' + (cancelModal.number || '')"></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">السائق:</span>
                        <span class="font-bold text-slate-700 lg:dark:text-slate-200" x-text="cancelModal.driver_name || '—'"></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">عدد الطرود:</span>
                        <span class="font-mono font-bold text-slate-700 lg:dark:text-slate-200" x-text="(cancelModal.parcels_count || 0) + ' طرد'"></span>
                    </div>
                </div>

                <div class="bg-amber-50 lg:dark:bg-amber-500/10 border border-amber-200/80 lg:dark:border-amber-500/30 text-amber-800 lg:dark:text-amber-300 rounded-xl p-2.5 flex items-start gap-2 text-[11px] leading-snug">
                    <span class="material-symbols-outlined text-base text-amber-600 shrink-0 mt-0.5">warning</span>
                    <span>
                        <strong>تنبيه:</strong> ستختفي جميع طرود هذا الكشف من قائمة (في الطريق) لدى الفروع المستلمة وتعود تلقائياً إلى (في الانتظار).
                    </span>
                </div>

                <form method="POST" :action="cancelModal.url" data-offline-capable="true" @submit.prevent="confirmCancelShipment()" class="flex flex-col gap-2 pt-1">
                    @csrf
                    <button type="submit" :disabled="cancelModal.submitting"
                            class="w-full py-3 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 active:scale-[0.98] text-white font-extrabold text-xs shadow-lg shadow-rose-600/25 flex items-center justify-center gap-1.5 transition-all disabled:opacity-50">
                        <span class="material-symbols-outlined text-base">cancel</span>
                        <span x-text="cancelModal.submitting ? 'جاري إلغاء الكشف...' : 'تأكيد إلغاء الكشف'"></span>
                    </button>
                    <button type="button" @click="closeCancelModal()" :disabled="cancelModal.submitting"
                            class="w-full py-2.5 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 lg:dark:bg-slate-800 lg:dark:hover:bg-slate-700 text-slate-700 lg:dark:text-slate-200 font-bold text-xs transition-colors disabled:opacity-50">
                        تراجع
                    </button>
                </form>
            </div>
        </div>
    </main>
    </div>
@endsection

@push('scripts')
    <script defer src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
        function sentShipmentsPage(config) {
            return {
                officeId: Number(config.officeId || 0),
                officeInfo: config.officeInfo || {},
                baseUrl: config.baseUrl || '/office/shipments/sent',
                bootstrapUrl: config.bootstrapUrl || '/office/shipments/sent/bootstrap',
                syncUrl: config.syncUrl || '/office/shipments/sent/sync',

                clientReady: false,
                shipments: Array.isArray(config.initialShipments) ? config.initialShipments : [],
                activeStatus: String(config.initialStatus || 'all'),
                searchQuery: String(config.initialSearch || ''),
                desktopView: localStorage.getItem('mursal_sent_shipments_desktop_view') === 'cards' ? 'cards' : 'table',
                counts: config.initialCounts || { all: 0, draft: 0, in_transit: 0, partially_received: 0, received: 0, cancelled: 0 },
                isFetching: false,

                availableParcels: Array.isArray(config.availableParcels) ? config.availableParcels : [],
                lookupUrl: config.lookupUrl,
                storeUrl: config.storeUrl,
                senderOfficeLabel: config.senderOfficeLabel || '',

                createModalOpen: new URLSearchParams(window.location.search).get('create') === '1',
                detailsModal: {
                    open: false,
                    shipment: null,
                },
                cancelModal: {
                    open: false,
                    submitting: false,
                    shipment: null,
                    id: '',
                    number: '',
                    driver_name: '',
                    parcels_count: 0,
                    url: '#',
                },
                showManualPicker: false,
                driverName: '',
                driverPhone: '',
                notes: '',
                dispatchNow: true,
                selectedParcels: [],

                scanCodeInput: '',
                isLookingUp: false,
                submitting: false,
                scanFeedback: {
                    type: '',
                    text: ''
                },

                cameraActive: false,
                _qrScanner: null,
                _lastScannedCode: '',
                _lastScannedAt: 0,

                viewportHeight: window.visualViewport ? Math.round(window.visualViewport.height) : window.innerHeight,
                viewportTop: window.visualViewport ? Math.round(window.visualViewport.offsetTop) : 0,
                _vpRafId: null,
                _vpHandler: null,

                async init() {
                    this._vpHandler = () => {
                        if (!this.createModalOpen) return;
                        if (this._vpRafId) return;
                        this._vpRafId = window.requestAnimationFrame(() => {
                            this._vpRafId = null;
                            const nextH = window.visualViewport ? Math.round(window.visualViewport.height) : window.innerHeight;
                            const nextT = window.visualViewport ? Math.round(window.visualViewport.offsetTop) : 0;
                            if (Math.abs(nextH - this.viewportHeight) > 2) {
                                this.viewportHeight = nextH;
                            }
                            if (Math.abs(nextT - this.viewportTop) > 2) {
                                this.viewportTop = nextT;
                            }
                        });
                    };

                    if (this.createModalOpen) {
                        this.attachViewportListeners();
                    }

                    // 1. تفعيل العرض المحلي بـ 0ms فوراً
                    if (this.shipments.length > 0) {
                        this.recomputeCounts();
                    }
                    this.clientReady = true;

                    // 2. قراءة البيانات من IndexedDB أو بذر البيانات الأولية
                    await this.hydrateFromIndexedDB();

                    // 3. مزامنة خلفية صامتة عند توفر الإنترنت
                    if (navigator.onLine) {
                        this.syncWithServer();
                    }

                    window.addEventListener('online', () => {
                        this.syncWithServer();
                    });
                },

                async hydrateFromIndexedDB() {
                    if (!window.MursalShipmentStore) return;
                    try {
                        const cachedShipments = await window.MursalShipmentStore.getLocalShipments(this.officeId);
                        const cachedScannable = await window.MursalShipmentStore.getScannableParcels(this.officeId);

                        if (cachedShipments.length > 0) {
                            this.shipments = cachedShipments;
                            this.recomputeCounts();
                        } else if (this.shipments.length > 0 || this.availableParcels.length > 0) {
                            this.shipments = await window.MursalShipmentStore.saveBootstrapData(this.officeId, {
                                office: this.officeInfo,
                                shipments: this.shipments,
                                scannable_parcels: this.availableParcels,
                            });
                            this.recomputeCounts();
                        }

                        if (cachedScannable.length > 0) {
                            this.availableParcels = cachedScannable;
                        }
                    } catch (err) {
                        console.warn('IndexedDB hydration error:', err);
                    }
                },

                async syncWithServer() {
                    if (!navigator.onLine || !window.MursalShipmentStore) return;
                    try {
                        await window.MursalShipmentStore.flushShipmentQueue();

                        const res = await fetch(`${this.bootstrapUrl}?_t=${Date.now()}`, {
                            cache: 'no-store',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });

                        if (!res.ok) return;
                        const data = await res.json();
                        if (data && data.ok) {
                            if (data.office) {
                                this.officeInfo = data.office;
                            }
                            this.shipments = await window.MursalShipmentStore.saveBootstrapData(this.officeId, data);
                            this.availableParcels = await window.MursalShipmentStore.getScannableParcels(this.officeId);
                            this.recomputeCounts();
                        }
                    } catch (err) {
                        // العمل يستمر أوفلاين من IndexedDB بدون أي انقطاع
                    }
                },

                async onShipmentsSynced(data) {
                    if (!window.MursalShipmentStore) return;
                    this.shipments = await window.MursalShipmentStore.getLocalShipments(this.officeId);
                    this.availableParcels = await window.MursalShipmentStore.getScannableParcels(this.officeId);
                    this.recomputeCounts();
                },

                get filteredShipments() {
                    const status = this.activeStatus || 'all';
                    const q = this.normalizeDigits(this.searchQuery).toLowerCase().trim();

                    return this.shipments.filter((s) => {
                        if (status !== 'all' && String(s.status) !== status) {
                            return false;
                        }
                        if (!q) return true;

                        const num = this.normalizeDigits(s.shipment_number).toLowerCase();
                        const dName = this.normalizeDigits(s.driver_name).toLowerCase();
                        const dPhone = this.normalizeDigits(s.driver_phone).toLowerCase();
                        const dests = Array.isArray(s.destinations_summary)
                            ? this.normalizeDigits(s.destinations_summary.join(' ')).toLowerCase()
                            : '';

                        if (num.includes(q) || dName.includes(q) || dPhone.includes(q) || dests.includes(q)) {
                            return true;
                        }

                        if (Array.isArray(s.parcels)) {
                            return s.parcels.some((p) => {
                                const rNum = this.normalizeDigits(p.receipt_number).toLowerCase();
                                const rName = this.normalizeDigits(p.recipient_name).toLowerCase();
                                const rPhone = this.normalizeDigits(p.recipient_phone).toLowerCase();
                                return rNum.includes(q) || rName.includes(q) || rPhone.includes(q);
                            });
                        }

                        return false;
                    });
                },

                recomputeCounts() {
                    const next = {
                        all: 0,
                        draft: 0,
                        in_transit: 0,
                        partially_received: 0,
                        received: 0,
                        cancelled: 0,
                    };

                    this.shipments.forEach((s) => {
                        const st = String(s?.status || '');
                        if (Object.prototype.hasOwnProperty.call(next, st)) {
                            next[st]++;
                        }
                        next.all++;
                    });

                    this.counts = next;
                },

                setStatusFilter(targetStatus) {
                    this.activeStatus = String(targetStatus || 'all');
                    this.syncUrlHistory();
                },

                handleSearchEvent(q) {
                    this.searchQuery = String(q || '').trim();
                    this.syncUrlHistory();
                },

                syncUrlHistory() {
                    try {
                        const params = new URLSearchParams();
                        if (this.activeStatus && this.activeStatus !== 'all') {
                            params.set('status', this.activeStatus);
                        }
                        if (this.searchQuery) {
                            params.set('q', this.searchQuery);
                        }
                        const qs = params.toString();
                        const cleanUrl = qs ? `${this.baseUrl}?${qs}` : this.baseUrl;
                        window.history.replaceState({}, '', cleanUrl);
                    } catch (e) {}
                },

                statusLabel(shipment) {
                    if (shipment?.status_label) return shipment.status_label;
                    const map = {
                        draft: 'قيد التجهيز',
                        in_transit: 'في الطريق',
                        partially_received: 'استلام جزئي',
                        received: 'تم الاستلام بالكامل',
                        cancelled: 'ملغاة',
                    };
                    return map[shipment?.status] || shipment?.status || '';
                },

                statusBadgeClass(status) {
                    const map = {
                        draft: 'bg-slate-100 text-slate-700 border-slate-300 lg:dark:bg-slate-700/50 lg:dark:text-slate-300 lg:dark:border-slate-600/60',
                        in_transit: 'bg-sky-100 text-sky-800 border-sky-200 lg:dark:bg-sky-500/15 lg:dark:text-sky-400 lg:dark:border-sky-500/30',
                        partially_received: 'bg-amber-100 text-amber-800 border-amber-200 lg:dark:bg-amber-500/15 lg:dark:text-amber-400 lg:dark:border-amber-500/30',
                        received: 'bg-emerald-100 text-emerald-800 border-emerald-200 lg:dark:bg-emerald-500/15 lg:dark:text-emerald-400 lg:dark:border-emerald-500/30',
                        cancelled: 'bg-rose-100 text-rose-800 border-rose-200 lg:dark:bg-rose-500/15 lg:dark:text-rose-400 lg:dark:border-rose-500/30',
                    };
                    return map[status] || 'bg-slate-100 text-slate-700 border-slate-300 lg:dark:bg-slate-700/50 lg:dark:text-slate-300 lg:dark:border-slate-600/60';
                },

                rowAccentDot(status) {
                    const map = {
                        draft: 'bg-slate-400',
                        in_transit: 'bg-sky-500',
                        partially_received: 'bg-amber-400',
                        received: 'bg-emerald-500',
                        cancelled: 'bg-rose-400',
                    };
                    return map[status] || 'bg-slate-300';
                },

                setDesktopView(mode) {
                    const next = mode === 'cards' ? 'cards' : 'table';
                    this.desktopView = next;
                    try {
                        localStorage.setItem('mursal_sent_shipments_desktop_view', next);
                    } catch (_) {}
                },

                canCancelShipment(shipment) {
                    if (!shipment) return false;
                    if (shipment.status === 'cancelled') return false;
                    if (!['draft', 'in_transit'].includes(String(shipment.status))) return false;
                    return Number(shipment.received_parcels_count || 0) === 0;
                },

                formatShipmentTime(isoStr) {
                    if (!isoStr) return 'الآن';
                    const d = new Date(isoStr);
                    if (isNaN(d.getTime())) return String(isoStr);
                    const diffSec = Math.max(0, Math.floor((Date.now() - d.getTime()) / 1000));
                    if (diffSec < 60) return 'الآن';
                    if (diffSec < 3600) return `منذ ${Math.floor(diffSec / 60)} دقيقة`;
                    if (diffSec < 86400) return `منذ ${Math.floor(diffSec / 3600)} ساعة`;
                    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
                },

                openShipmentDetails(shipment) {
                    if (!shipment) return;
                    if (navigator.onLine && shipment.id && !shipment.pending_sync && shipment.show_url) {
                        window.location.href = shipment.show_url;
                        return;
                    }
                    this.detailsModal = {
                        open: true,
                        shipment: shipment,
                    };
                },

                closeDetailsModal() {
                    this.detailsModal.open = false;
                },

                printShipmentOffline(shipment) {
                    if (!shipment) return;
                    if (window.MursalManifestPrint) {
                        window.MursalManifestPrint.print(shipment, this.officeInfo);
                        return;
                    }
                    if (shipment.print_url) {
                        this.printShipment(shipment.print_url);
                    }
                },

                printShipment(url) {
                    if (!url) return;
                    if (typeof url === 'object') {
                        this.printShipmentOffline(url);
                        return;
                    }
                    const matched = this.shipments.find((s) => s.print_url === url || (s.id && url.includes(String(s.id))));
                    if (matched && Array.isArray(matched.parcels) && window.MursalManifestPrint) {
                        window.MursalManifestPrint.print(matched, this.officeInfo);
                        return;
                    }

                    const fallbackUrl = url + (url.includes('?') ? '&' : '?') + 'auto_print=1';
                    try {
                        let iframe = document.getElementById('sent-shipment-print-iframe');
                        if (iframe) {
                            iframe.remove();
                        }
                        iframe = document.createElement('iframe');
                        iframe.id = 'sent-shipment-print-iframe';
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
                            }, 150);
                        };

                        iframe.onerror = () => {
                            window.open(fallbackUrl, '_blank');
                        };

                        document.body.appendChild(iframe);
                        iframe.src = url;
                    } catch (e) {
                        window.open(fallbackUrl, '_blank');
                    }
                },

                attachViewportListeners() {
                    if (!this._vpHandler) return;
                    this._vpHandler();
                    if (window.visualViewport) {
                        window.visualViewport.addEventListener('resize', this._vpHandler, { passive: true });
                        window.visualViewport.addEventListener('scroll', this._vpHandler, { passive: true });
                    }
                    window.addEventListener('resize', this._vpHandler, { passive: true });
                },

                detachViewportListeners() {
                    if (!this._vpHandler) return;
                    if (this._vpRafId) {
                        window.cancelAnimationFrame(this._vpRafId);
                        this._vpRafId = null;
                    }
                    if (window.visualViewport) {
                        window.visualViewport.removeEventListener('resize', this._vpHandler);
                        window.visualViewport.removeEventListener('scroll', this._vpHandler);
                    }
                    window.removeEventListener('resize', this._vpHandler);
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
                            osc.frequency.value = 920;
                            gain.gain.value = 0.14;
                            osc.start();
                            osc.stop(ctx.currentTime + 0.11);
                            if (navigator.vibrate) navigator.vibrate(50);
                        } else {
                            osc.frequency.value = 230;
                            osc.type = 'sawtooth';
                            gain.gain.value = 0.15;
                            osc.start();
                            osc.stop(ctx.currentTime + 0.24);
                            if (navigator.vibrate) navigator.vibrate([90, 40, 90]);
                        }
                    } catch (e) {}
                },

                setFeedback(type, text) {
                    this.scanFeedback = {
                        type,
                        text
                    };
                    if (text) {
                        const toastType = type === 'duplicate' ? 'warning' : (type === 'error' ? 'error' : 'success');
                        window.dispatchEvent(new CustomEvent('office-toast', {
                            detail: { message: text, type: toastType }
                        }));
                    }
                },

                normalizeDigits(str) {
                    const ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
                    const fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
                    return String(str || '')
                        .replace(/[٠-٩]/g, d => ar.indexOf(d))
                        .replace(/[۰-۹]/g, d => fa.indexOf(d))
                        .replace(/[\u200B-\u200D\uFEFF\u200E\u200F]/g, '');
                },

                sanitizePhone(str) {
                    let digits = this.normalizeDigits(str).replace(/\D/g, '');
                    if (digits.length === 12 && digits.startsWith('9677')) digits = digits.substring(3);
                    if (digits.length === 14 && digits.startsWith('009677')) digits = digits.substring(5);
                    return digits.substring(0, 9);
                },

                isValidPhone(phone) {
                    return /^7[01378]\d{7}$/.test(this.sanitizePhone(phone));
                },

                formatMoney(n) {
                    return Number(n || 0).toLocaleString('en-US');
                },

                generateUuid() {
                    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
                        return window.crypto.randomUUID().toLowerCase();
                    }
                    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
                        const r = (Math.random() * 16) | 0;
                        const v = c === 'x' ? r : ((r & 0x3) | 0x8);
                        return v.toString(16);
                    });
                },

                get unaddedAvailable() {
                    const ids = new Set(this.selectedParcels.map(p => p.id));
                    return this.availableParcels.filter(p => !ids.has(p.id));
                },

                get totals() {
                    let paid = 0,
                        deferred = 0;
                    this.selectedParcels.forEach(p => {
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

                get uniqueDestinations() {
                    const set = new Set();
                    this.selectedParcels.forEach(p => {
                        const d = p.destination_label || p.destination;
                        if (d) set.add(d);
                    });
                    return Array.from(set);
                },

                openCreateModal() {
                    this.createModalOpen = true;
                    this.attachViewportListeners();
                    this.scanFeedback = {
                        type: '',
                        text: ''
                    };
                    this.$nextTick(() => this.$refs.scanInput?.focus());
                },

                async closeCreateModal() {
                    await this.stopCamera();
                    this.createModalOpen = false;
                    this.detachViewportListeners();
                },

                openCancelModal(data) {
                    const matched = this.shipments.find((s) =>
                        (data?.local_key && s.local_key === data.local_key) ||
                        (data?.id && String(s.id) === String(data.id))
                    ) || data;

                    this.cancelModal = {
                        open: true,
                        submitting: false,
                        shipment: matched,
                        id: matched?.id || '',
                        number: matched?.shipment_number || data?.number || '',
                        driver_name: matched?.driver_name || data?.driver_name || '',
                        parcels_count: Number(matched?.parcels_count ?? data?.parcels_count ?? 0),
                        url: data?.url || (matched?.id ? `${this.baseUrl}/${matched.id}/cancel` : '#'),
                    };
                },

                closeCancelModal() {
                    if (this.cancelModal.submitting) return;
                    this.cancelModal.open = false;
                },

                async confirmCancelShipment() {
                    if (this.cancelModal.submitting) return;
                    const target = this.cancelModal.shipment;
                    if (!target) return;

                    this.cancelModal.submitting = true;
                    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';

                    // 1. إذا كان متصلاً والشحنة مزامنة على السيرفر نحاول الإلغاء المباشر مع مهلة 8 ثوانٍ
                    if (navigator.onLine && target.id && !target.pending_sync) {
                        const controller = new AbortController();
                        const timer = setTimeout(() => controller.abort(), 8000);
                        try {
                            const cancelUrl = this.cancelModal.url && this.cancelModal.url !== '#'
                                ? this.cancelModal.url
                                : `${this.baseUrl}/${target.id}/cancel`;

                            const res = await fetch(cancelUrl, {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': token,
                                },
                                signal: controller.signal,
                            });
                            clearTimeout(timer);

                            const data = await res.json().catch(() => ({}));
                            if (res.status === 422 || res.status === 403) {
                                this.cancelModal.submitting = false;
                                this.cancelModal.open = false;
                                window.dispatchEvent(new CustomEvent('office-toast', {
                                    detail: { message: data.message || 'تعذر إلغاء الكشف.', type: 'error' }
                                }));
                                this.syncWithServer();
                                return;
                            }

                            if (res.ok && data.ok) {
                                await this.applyLocalCancelResult(target, false);
                                this.cancelModal.submitting = false;
                                this.cancelModal.open = false;
                                window.dispatchEvent(new CustomEvent('office-toast', {
                                    detail: { message: data.message || `تم إلغاء الكشف #${target.shipment_number} بنجاح.`, type: 'success' }
                                }));
                                return;
                            }
                        } catch (err) {
                            clearTimeout(timer);
                            // في حال انقطاع الشبكة أو انتهاء المهلة ننتقل للإلغاء المحلي الأوفلاين
                        }
                    }

                    // 2. الإلغاء الأوفلاين الفوري مع الجدولة في طابور المزامنة
                    await this.applyLocalCancelResult(target, true);
                    this.cancelModal.submitting = false;
                    this.cancelModal.open = false;
                    window.dispatchEvent(new CustomEvent('office-toast', {
                        detail: {
                            message: `تم إلغاء الكشف #${target.shipment_number} محلياً وإعادة طروده للانتظار — ستتم المزامنة عند توفر الإنترنت.`,
                            type: 'info'
                        }
                    }));
                },

                async applyLocalCancelResult(target, enqueueSync = true) {
                    if (window.MursalShipmentStore) {
                        await window.MursalShipmentStore.cancelOfflineShipment(this.officeId, target, enqueueSync);
                        this.shipments = await window.MursalShipmentStore.getLocalShipments(this.officeId);
                        this.availableParcels = await window.MursalShipmentStore.getScannableParcels(this.officeId);
                    } else {
                        target.status = 'cancelled';
                        target.status_label = 'ملغاة';
                        target.can_cancel = false;
                        target.pending_sync = enqueueSync;
                    }
                    this.recomputeCounts();
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
                        this.setFeedback('error', 'تعذر تحميل مكتبة الكاميرا، استخدم حقل الباركود أو الإدخال اليدوي.');
                        return;
                    }
                    this.cameraActive = true;
                    this.$nextTick(async () => {
                        try {
                            this._qrScanner = new Html5Qrcode('sent-shipment-qr-reader');
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
                                    if (decodedText === this._lastScannedCode && (now - this._lastScannedAt) < 2200) {
                                        return;
                                    }
                                    this._lastScannedCode = decodedText;
                                    this._lastScannedAt = now;
                                    this.handleScan(decodedText);
                                },
                                () => {}
                            );
                        } catch (err) {
                            this.cameraActive = false;
                            this.setFeedback('error',
                                'تعذر فتح الكاميرا (تأكد من منح الصلاحية أو استخدم حقل القارئ اليدوي).');
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

                normalizeCode(raw) {
                    let clean = this.normalizeDigits(raw).trim();
                    const m = clean.match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i);
                    return m ? m[0].toLowerCase() : clean.replace(/^#+/, '').trim();
                },

                addParcelObject(parcel) {
                    if (this.selectedParcels.some(p => p.id === parcel.id)) {
                        this.beep('error');
                        this.setFeedback('duplicate', 'تم مسح هذا الطرد مسبقاً (سند #' + parcel.receipt_number +
                            ') — مُنع التكرار.');
                        return;
                    }
                    this.selectedParcels.unshift(parcel);
                    this.beep('success');
                    this.setFeedback('success', 'تمت إضافة الطرد: سند #' + parcel.receipt_number + ' (' + (parcel.destination_label || parcel.destination) + ')');
                },

                removeParcel(id) {
                    this.selectedParcels = this.selectedParcels.filter(p => p.id !== id);
                },

                async handleScan(rawCode) {
                    const code = this.normalizeCode(rawCode);
                    this.scanCodeInput = '';
                    if (!code) return;

                    // 1. فحص التكرار المحلي الفوري داخل الشحنة الحالية
                    const dup = this.selectedParcels.find(p =>
                        String(p.id).toLowerCase() === code ||
                        this.normalizeCode(p.receipt_number) === code
                    );
                    if (dup) {
                        this.beep('error');
                        this.setFeedback('duplicate', 'تم مسح هذا الطرد مسبقاً (سند #' + dup.receipt_number +
                            ') — مُنع التكرار.');
                        return;
                    }

                    // 2. البحث الفوري (<2ms) في IndexedDB (MursalShipmentStore.findScannableParcel) والذاكرة المحلية
                    if (window.MursalShipmentStore) {
                        const dbMatch = await window.MursalShipmentStore.findScannableParcel(this.officeId, code);
                        if (dbMatch) {
                            this.addParcelObject(dbMatch);
                            return;
                        }
                    }

                    const localMatch = this.availableParcels.find(p =>
                        String(p.id).toLowerCase() === code ||
                        this.normalizeCode(p.receipt_number) === code
                    );
                    if (localMatch) {
                        this.addParcelObject(localMatch);
                        return;
                    }

                    // 3. في وضع عدم الاتصال لا نرسل طلباً للشبكة
                    if (!navigator.onLine) {
                        this.beep('error');
                        this.setFeedback('error', 'لم يتم العثور على طرد في الانتظار بهذا الرمز في الذاكرة المحلية.');
                        return;
                    }

                    // 4. التحقق الاحتياطي من السيرفر عند توفر الإنترنت فقط
                    this.isLookingUp = true;
                    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
                    try {
                        const res = await fetch(this.lookupUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': token,
                            },
                            body: JSON.stringify({ code })
                        });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || !data.ok || !data.parcel) {
                            this.beep('error');
                            this.setFeedback('error', data.message || 'لم يتم العثور على طرد مطابق.');
                            return;
                        }
                        this.addParcelObject(data.parcel);
                    } catch (e) {
                        this.beep('error');
                        this.setFeedback('error', 'تعذر الاتصال بالخادم لفحص الباركود.');
                    } finally {
                        this.isLookingUp = false;
                        this.$nextTick(() => this.$refs.scanInput?.focus());
                    }
                },

                resetCreateForm() {
                    this.driverName = '';
                    this.driverPhone = '';
                    this.notes = '';
                    this.dispatchNow = true;
                    this.selectedParcels = [];
                    this.showManualPicker = false;
                    this.scanFeedback = { type: '', text: '' };
                },

                async submitShipment() {
                    if (this.submitting) return;
                    if (!this.driverName.trim()) {
                        this.setFeedback('error', 'يرجى إدخال اسم السائق.');
                        return;
                    }
                    if (!this.isValidPhone(this.driverPhone)) {
                        this.setFeedback('error', 'يرجى إدخال رقم سائق يمني صحيح (9 أرقام).');
                        return;
                    }
                    if (this.selectedParcels.length === 0) {
                        this.setFeedback('error', 'يرجى مسح طرد واحد على الأقل.');
                        return;
                    }

                    this.submitting = true;
                    const clientUuid = this.generateUuid();
                    const cleanDriverName = this.driverName.trim();
                    const cleanDriverPhone = this.sanitizePhone(this.driverPhone);
                    const cleanNotes = this.notes.trim() || null;
                    const selectedSnapshot = [...this.selectedParcels];
                    const selectedIds = selectedSnapshot.map(p => p.id);
                    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';

                    // 1. محاولة الحفظ المباشر على السيرفر إذا كان متصلاً (بمهلة 8 ثوانٍ)
                    if (navigator.onLine) {
                        const controller = new AbortController();
                        const timer = setTimeout(() => controller.abort(), 8000);
                        try {
                            const res = await fetch(this.storeUrl, {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': token,
                                },
                                body: JSON.stringify({
                                    client_uuid: clientUuid,
                                    driver_name: cleanDriverName,
                                    driver_phone: cleanDriverPhone,
                                    parcel_ids: selectedIds,
                                    dispatch_now: this.dispatchNow,
                                    notes: cleanNotes,
                                }),
                                signal: controller.signal,
                            });
                            clearTimeout(timer);

                            const data = await res.json().catch(() => ({}));

                            if (res.status === 422) {
                                this.beep('error');
                                this.setFeedback('error', data.message || 'تعذر حفظ الشحنة.');
                                this.submitting = false;
                                return;
                            }

                            if (res.ok && data.ok && data.shipment) {
                                if (window.MursalShipmentStore) {
                                    await window.MursalShipmentStore.saveSyncedShipment(this.officeId, data.shipment, selectedIds);
                                    this.shipments = await window.MursalShipmentStore.getLocalShipments(this.officeId);
                                    this.availableParcels = await window.MursalShipmentStore.getScannableParcels(this.officeId);
                                } else {
                                    this.shipments.unshift(data.shipment);
                                    const usedSet = new Set(selectedIds);
                                    this.availableParcels = this.availableParcels.filter(p => !usedSet.has(p.id));
                                }

                                this.recomputeCounts();
                                this.resetCreateForm();
                                this.submitting = false;
                                await this.closeCreateModal();

                                window.dispatchEvent(new CustomEvent('office-toast', {
                                    detail: { message: data.message || 'تم إنشاء كشف الشحنة بنجاح.', type: 'success' }
                                }));
                                return;
                            }
                        } catch (err) {
                            clearTimeout(timer);
                            // عند فشل الاتصال أو انتهاء المهلة، نحفظ الشحنة محلياً في IndexedDB بدون ضياع البيانات
                        }
                    }

                    // 2. الحفظ المحلي الفوري في IndexedDB وجدولة المزامنة التلقائية
                    try {
                        if (window.MursalShipmentStore) {
                            await window.MursalShipmentStore.createOfflineShipment(this.officeId, {
                                client_uuid: clientUuid,
                                driver_name: cleanDriverName,
                                driver_phone: cleanDriverPhone,
                                dispatch_now: this.dispatchNow,
                                notes: cleanNotes,
                                parcels: selectedSnapshot,
                            });
                            this.shipments = await window.MursalShipmentStore.getLocalShipments(this.officeId);
                            this.availableParcels = await window.MursalShipmentStore.getScannableParcels(this.officeId);
                        }

                        this.recomputeCounts();
                        this.resetCreateForm();
                        await this.closeCreateModal();

                        window.dispatchEvent(new CustomEvent('office-toast', {
                            detail: {
                                message: 'تم حفظ الكشف محلياً — ستتم المزامنة تلقائياً عند عودة الإنترنت.',
                                type: 'info'
                            }
                        }));
                    } finally {
                        this.submitting = false;
                    }
                }
            };
        }
    </script>
@endpush