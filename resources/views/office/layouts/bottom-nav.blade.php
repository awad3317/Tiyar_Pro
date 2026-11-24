@php
    $officeUser = auth('office')->user();
    $officeId = (int) ($officeUser?->id ?? 0);
    $hasOutgoing = $officeUser && $officeUser->hasFeature('outgoing_parcels');
    $hasSentShipments = $officeUser && $officeUser->hasFeature('sent_shipments');
    $hasVip = $officeUser && $officeUser->hasFeature('vip_customers_tab');

    $isDashboard = request()->routeIs('office.dashboard');
    $isIncoming = request()->routeIs('office.parcels.*');
    $isOutgoing = request()->routeIs('office.outgoing-parcels.*');
    $isTransit = request()->routeIs('office.transit-parcels.*') || request()->is('office/transit-parcels*');
    $isAnyParcels = $isIncoming || $isOutgoing || $isTransit;

    $isShipmentsSent = request()->routeIs('office.shipments.sent.*');
    $isShipmentsReceived = request()->routeIs('office.shipments.received.*');
    $isAnyShipments = $isShipmentsSent || $isShipmentsReceived;

    $isFavoriteCustomers = request()->routeIs('office.favorite_customers.*');

    $transitUrl = \Illuminate\Support\Facades\Route::has('office.transit-parcels.index')
        ? route('office.transit-parcels.index')
        : url('/office/transit-parcels');

    $serverTransitCount = null;
    if (isset($inTransitCount)) {
        $serverTransitCount = (int) $inTransitCount;
    } elseif (isset($transitParcels) && is_countable($transitParcels)) {
        $serverTransitCount = count($transitParcels);
    }

    // عداد حي للشحنات القادمة بانتظار الاستلام في كل الصفحات
    $serverPendingShipmentsCount = null;
    if (isset($pendingReceivedShipmentsCount)) {
        $serverPendingShipmentsCount = (int) $pendingReceivedShipmentsCount;
    } elseif ($officeUser && class_exists(\App\Services\ShipmentService::class)) {
        try {
            $serverPendingShipmentsCount = \App\Services\ShipmentService::countPendingReceivedShipments($officeUser);
        } catch (\Throwable $e) {
            $serverPendingShipmentsCount = null;
        }
    }
@endphp

<div
    x-data="{
        officeId: {{ $officeId }},
        serverCount: {{ $serverTransitCount !== null ? $serverTransitCount : 'null' }},
        serverShipmentsCount: {{ $serverPendingShipmentsCount !== null ? $serverPendingShipmentsCount : 'null' }},
        isIncomingPage: {{ $isIncoming ? 'true' : 'false' }},
        isShipmentsReceivedPage: {{ $isShipmentsReceived ? 'true' : 'false' }},
        parcelsMenuOpen: false,
        shipmentsMenuOpen: false,
        lockedNotice: false,
        transitCount: 0,
        pendingShipmentsCount: 0,
        showTransitToast: false,
        showShipmentsToast: false,
        _toastTimer: null,

        countKey() { return 'mursal_transit_count_' + this.officeId; },
        dismissedKey() { return 'mursal_transit_dismissed_' + this.officeId; },
        toastedKey() { return 'mursal_transit_toasted_' + this.officeId; },

        shipmentsCountKey() { return 'mursal_shipments_pending_' + this.officeId; },
        shipmentsToastedKey() { return 'mursal_shipments_toasted_' + this.officeId; },

        init() {
            // 1. تزامن عداد الطرود في الطريق
            if (this.serverCount !== null) {
                this.syncCount(this.serverCount);
            } else {
                const stored = parseInt(localStorage.getItem(this.countKey()) || '0', 10);
                this.transitCount = Number.isNaN(stored) ? 0 : Math.max(0, stored);
            }

            // 2. تزامن عداد الشحنات المستلمة بانتظار الفحص
            if (this.serverShipmentsCount !== null) {
                this.syncShipmentsCount(this.serverShipmentsCount);
            } else {
                const storedS = parseInt(localStorage.getItem(this.shipmentsCountKey()) || '0', 10);
                this.pendingShipmentsCount = Number.isNaN(storedS) ? 0 : Math.max(0, storedS);
            }

            // 3. إشعار لطيف عند وجود شحنة جديدة بانتظار الاستلام (أولوية للشحنات)
            if (!this.isShipmentsReceivedPage && this.pendingShipmentsCount > 0) {
                const lastToastedS = parseInt(sessionStorage.getItem(this.shipmentsToastedKey()) || '0', 10) || 0;
                if (this.pendingShipmentsCount > lastToastedS) {
                    sessionStorage.setItem(this.shipmentsToastedKey(), String(this.pendingShipmentsCount));
                    this.showShipmentsToast = true;
                    this._toastTimer = setTimeout(() => { this.showShipmentsToast = false; }, 7500);
                    return;
                }
            }

            // 4. إشعار الطرود في الطريق بصفحة الطرود الواردة
            if (this.isIncomingPage && this.transitCount > 0) {
                const lastToasted = parseInt(sessionStorage.getItem(this.toastedKey()) || '0', 10) || 0;
                if (this.transitCount > lastToasted) {
                    sessionStorage.setItem(this.toastedKey(), String(this.transitCount));
                    this.showTransitToast = true;
                    this._toastTimer = setTimeout(() => { this.showTransitToast = false; }, 7000);
                }
            }
        },

        closeAllMenus() {
            this.parcelsMenuOpen = false;
            this.shipmentsMenuOpen = false;
            this.lockedNotice = false;
        },

        toggleParcelsMenu() {
            const next = !this.parcelsMenuOpen;
            this.closeAllMenus();
            this.dismissToasts();
            this.parcelsMenuOpen = next;
        },

        toggleShipmentsMenu() {
            const next = !this.shipmentsMenuOpen;
            this.closeAllMenus();
            this.dismissToasts();
            this.shipmentsMenuOpen = next;
        },

        syncCount(val) {
            const n = Math.max(0, parseInt(val || 0, 10) || 0);
            this.transitCount = n;
            localStorage.setItem(this.countKey(), String(n));
            if (n === 0) {
                localStorage.removeItem(this.dismissedKey());
                sessionStorage.removeItem(this.toastedKey());
                this.showTransitToast = false;
            }
        },

        syncShipmentsCount(val) {
            const n = Math.max(0, parseInt(val || 0, 10) || 0);
            this.pendingShipmentsCount = n;
            localStorage.setItem(this.shipmentsCountKey(), String(n));
            if (n === 0) {
                sessionStorage.removeItem(this.shipmentsToastedKey());
                this.showShipmentsToast = false;
            }
        },

        dismissToasts() {
            this.showTransitToast = false;
            this.showShipmentsToast = false;
            if (this._toastTimer) clearTimeout(this._toastTimer);
        },

        transitText(n) {
            n = Number(n) || 0;
            if (n === 1) return 'لديك طرد واحد قادم في الطريق';
            if (n === 2) return 'لديك طردان قادمان في الطريق';
            if (n >= 3 && n <= 10) return 'لديك ' + n + ' طرود قادمة في الطريق';
            return 'لديك ' + n + ' طرداً قادماً في الطريق';
        },

        shipmentsText(n) {
            n = Number(n) || 0;
            if (n === 1) return 'وصلتك شحنة جديدة بانتظار الفحص والاستلام';
            if (n === 2) return 'وصلتك شحنتان جديدتان بانتظار الاستلام';
            return 'لديك ' + n + ' شحنات قادمة بانتظار الاستلام';
        }
    }"
    @transit-count-updated.window="syncCount($event.detail?.count)"
    @shipments-pending-updated.window="syncShipmentsCount($event.detail?.count)"
    @mursal-close-nav-menus.window="closeAllMenus()"
    @keydown.escape.window="closeAllMenus()"
>

    <!-- إشعار عائم للشحنات القادمة -->
    <div
        x-show="showShipmentsToast && !parcelsMenuOpen && !shipmentsMenuOpen"
        style="display: none;"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-3"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-3"
        class="fixed bottom-[72px] left-0 right-0 max-w-md mx-auto px-3 z-[27] pointer-events-none"
    >
        <div class="pointer-events-auto bg-primary text-white rounded-2xl px-3.5 py-2.5 shadow-xl border border-secondary/40 flex items-center gap-2.5">
            <span class="w-8 h-8 rounded-xl bg-secondary/20 text-secondary flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-lg">qr_code_scanner</span>
            </span>
            <p class="flex-1 text-xs font-bold leading-snug" x-text="shipmentsText(pendingShipmentsCount)"></p>
            <a href="{{ route('office.shipments.received.index') }}"
               data-nav-label="الشحنات المستلمة"
               class="px-3 py-1.5 rounded-xl bg-secondary hover:bg-amber-500 text-primary text-[11px] font-extrabold shrink-0 transition-colors">
                استلام
            </a>
            <button type="button" @click="dismissToasts()" class="w-6 h-6 rounded-full text-slate-400 hover:text-white flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>
    </div>

    <!-- إشعار عائم للطرود في الطريق -->
    <div
        x-show="showTransitToast && !showShipmentsToast && !parcelsMenuOpen && !shipmentsMenuOpen"
        style="display: none;"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-3"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-3"
        class="fixed bottom-[72px] left-0 right-0 max-w-md mx-auto px-3 z-[27] pointer-events-none"
    >
        <div class="pointer-events-auto bg-primary text-white rounded-2xl px-3.5 py-2.5 shadow-xl border border-slate-700 flex items-center gap-2.5">
            <span class="w-8 h-8 rounded-xl bg-secondary/15 text-secondary flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-lg">local_shipping</span>
            </span>
            <p class="flex-1 text-xs font-bold leading-snug" x-text="transitText(transitCount)"></p>
            <a href="{{ $transitUrl }}"
               data-nav-label="الطرود في الطريق"
               class="px-3 py-1.5 rounded-xl bg-secondary hover:bg-amber-500 text-primary text-[11px] font-extrabold shrink-0 transition-colors">
                عرض
            </a>
            <button type="button" @click="dismissToasts()" class="w-6 h-6 rounded-full text-slate-400 hover:text-white flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>
    </div>

    <!-- خلفية التعتيم (بدون backdrop-blur للأداء على أندرويد) -->
    <div
        x-show="parcelsMenuOpen || shipmentsMenuOpen"
        style="display: none;"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="closeAllMenus()"
        class="fixed inset-0 max-w-md mx-auto bg-slate-900/40 z-[28]"
    ></div>

    <!-- القائمة المنبثقة 1: أقسام الطرود -->
    <div
        x-show="parcelsMenuOpen"
        style="display: none;"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-3 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-3 scale-95"
        class="fixed bottom-[64px] left-0 right-0 max-w-md mx-auto px-3 pb-2 z-[29]"
    >
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xl overflow-hidden">
            <div class="flex items-center justify-between px-4 py-2.5 bg-slate-50 border-b border-slate-100">
                <span class="text-xs font-bold text-primary">أقسام الطرود</span>
                <button type="button" @click="closeAllMenus()" class="w-6 h-6 rounded-full flex items-center justify-center text-slate-400 hover:bg-slate-200 hover:text-slate-600">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>

            <div class="p-2 space-y-1.5">
                <a href="{{ route('office.parcels.index') }}"
                   data-nav-label="الطرود المستلمة"
                   class="flex items-center gap-3 p-2.5 rounded-xl transition-colors {{ $isIncoming ? 'bg-primary text-white' : 'text-slate-700 hover:bg-slate-50' }}">
                    <span class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 {{ $isIncoming ? 'bg-white/15 text-secondary' : 'bg-blue-50 text-blue-600' }}">
                        <span class="material-symbols-outlined text-xl">move_to_inbox</span>
                    </span>
                    <div class="flex-1 min-w-0">
                        <div class="text-xs font-bold">الطرود المستلمة</div>
                        <div class="text-[10px] {{ $isIncoming ? 'text-white/75' : 'text-slate-400' }}">الطرود الواردة للمكتب والتسليم</div>
                    </div>
                    <span class="material-symbols-outlined text-lg {{ $isIncoming ? 'text-secondary' : 'text-slate-300' }}">chevron_left</span>
                </a>

                @if($hasOutgoing)
                    <a href="{{ route('office.outgoing-parcels.index') }}"
                       data-nav-label="الطرود المرسلة"
                       class="flex items-center gap-3 p-2.5 rounded-xl transition-colors {{ $isOutgoing ? 'bg-primary text-white' : 'text-slate-700 hover:bg-slate-50' }}">
                        <span class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 {{ $isOutgoing ? 'bg-white/15 text-secondary' : 'bg-amber-50 text-amber-600' }}">
                            <span class="material-symbols-outlined text-xl">outbox</span>
                        </span>
                        <div class="flex-1 min-w-0">
                            <div class="text-xs font-bold">الطرود المرسلة</div>
                            <div class="text-[10px] {{ $isOutgoing ? 'text-white/75' : 'text-slate-400' }}">الشحنات الصادرة للفروع والمكاتب</div>
                        </div>
                        <span class="material-symbols-outlined text-lg {{ $isOutgoing ? 'text-secondary' : 'text-slate-300' }}">chevron_left</span>
                    </a>
                @else
                    <button type="button"
                            @click="if (typeof showFeedback === 'function') { closeAllMenus(); showFeedback('ميزة الطرود المرسلة غير مفعّلة لهذا المكتب، يرجى التواصل مع الإدارة لتفعيلها.', true); } else { lockedNotice = true; }"
                            class="w-full text-right flex items-center gap-3 p-2.5 rounded-xl bg-slate-50/90 border border-dashed border-slate-200 text-slate-500 hover:bg-slate-100/80 transition-colors">
                        <span class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 bg-slate-200/70 text-slate-400">
                            <span class="material-symbols-outlined text-xl">outbox</span>
                        </span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span class="text-xs font-bold text-slate-500">الطرود المرسلة</span>
                                <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-md bg-amber-100 text-amber-700 text-[9px] font-bold">
                                    <span class="material-symbols-outlined text-[11px]">lock</span> مقفلة
                                </span>
                            </div>
                            <div class="text-[10px] text-slate-400">تتطلب تفعيل الميزة من الإدارة</div>
                        </div>
                        <span class="material-symbols-outlined text-lg text-slate-400">lock</span>
                    </button>
                @endif

                <a href="{{ $transitUrl }}"
                   data-nav-label="الطرود في الطريق"
                   class="flex items-center gap-3 p-2.5 rounded-xl transition-colors {{ $isTransit ? 'bg-primary text-white' : 'text-slate-700 hover:bg-slate-50' }}">
                    <span class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 {{ $isTransit ? 'bg-white/15 text-secondary' : 'bg-emerald-50 text-emerald-600' }}">
                        <span class="material-symbols-outlined text-xl">local_shipping</span>
                    </span>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs font-bold">في الطريق</span>
                            <span x-show="transitCount > 0"
                                  style="display: none;"
                                  class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full text-[10px] font-black {{ $isTransit ? 'bg-secondary text-primary' : 'bg-secondary/15 text-amber-700 border border-secondary/30' }}"
                                  x-text="transitCount > 99 ? '99+' : transitCount"></span>
                        </div>
                        <div class="text-[10px] {{ $isTransit ? 'text-white/75' : 'text-slate-400' }}">طرود قادمة من الفروع بانتظار الاستلام</div>
                    </div>
                    <span class="material-symbols-outlined text-lg {{ $isTransit ? 'text-secondary' : 'text-slate-300' }}">chevron_left</span>
                </a>

                <div x-show="lockedNotice" style="display: none;"
                     class="mt-1 px-3 py-2 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-[11px] font-medium flex items-center gap-2">
                    <span class="material-symbols-outlined text-base text-amber-600 shrink-0">info</span>
                    <span>هذه الميزة غير مفعّلة لمكتبك حالياً، يرجى التواصل مع الإدارة لتفعيلها.</span>
                </div>
            </div>
        </div>
    </div>

    <!-- القائمة المنبثقة 2: أقسام الشحنات (مرسلة / مستلمة) -->
    <div
        x-show="shipmentsMenuOpen"
        style="display: none;"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-3 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-3 scale-95"
        class="fixed bottom-[64px] left-0 right-0 max-w-md mx-auto px-3 pb-2 z-[29]"
    >
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xl overflow-hidden">
            <div class="flex items-center justify-between px-4 py-2.5 bg-slate-50 border-b border-slate-100">
                <span class="text-xs font-bold text-primary">أقسام الشحنات (الكشوفات)</span>
                <button type="button" @click="closeAllMenus()" class="w-6 h-6 rounded-full flex items-center justify-center text-slate-400 hover:bg-slate-200 hover:text-slate-600">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>

            <div class="p-2 space-y-1.5">
                <!-- 1. شحنات مرسلة -->
                @if($hasSentShipments)
                    <a href="{{ route('office.shipments.sent.index') }}"
                       data-nav-label="الشحنات المرسلة"
                       class="flex items-center gap-3 p-2.5 rounded-xl transition-colors {{ $isShipmentsSent ? 'bg-primary text-white' : 'text-slate-700 hover:bg-slate-50' }}">
                        <span class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 {{ $isShipmentsSent ? 'bg-white/15 text-secondary' : 'bg-amber-50 text-amber-600' }}">
                            <span class="material-symbols-outlined text-xl">outbound</span>
                        </span>
                        <div class="flex-1 min-w-0">
                            <div class="text-xs font-bold">شحنات مرسلة</div>
                            <div class="text-[10px] {{ $isShipmentsSent ? 'text-white/75' : 'text-slate-400' }}">إنشاء كشف شحنة بمسح الباركود وإرساله مع السائق</div>
                        </div>
                        <span class="material-symbols-outlined text-lg {{ $isShipmentsSent ? 'text-secondary' : 'text-slate-300' }}">chevron_left</span>
                    </a>
                @else
                    <button type="button"
                            @click="if (typeof showFeedback === 'function') { closeAllMenus(); showFeedback('ميزة الشحنات المرسلة غير مفعّلة لهذا المكتب، يرجى التواصل مع الإدارة لتفعيلها.', true); } else { lockedNotice = true; }"
                            class="w-full text-right flex items-center gap-3 p-2.5 rounded-xl bg-slate-50/90 border border-dashed border-slate-200 text-slate-500 hover:bg-slate-100/80 transition-colors">
                        <span class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 bg-slate-200/70 text-slate-400">
                            <span class="material-symbols-outlined text-xl">outbound</span>
                        </span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span class="text-xs font-bold text-slate-500">شحنات مرسلة</span>
                                <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-md bg-amber-100 text-amber-700 text-[9px] font-bold">
                                    <span class="material-symbols-outlined text-[11px]">lock</span> مقفلة
                                </span>
                            </div>
                            <div class="text-[10px] text-slate-400">تتطلب تفعيل الميزة من الإدارة</div>
                        </div>
                        <span class="material-symbols-outlined text-lg text-slate-400">lock</span>
                    </button>
                @endif

                <!-- 2. شحنات مستلمة -->
                <a href="{{ route('office.shipments.received.index') }}"
                   data-nav-label="الشحنات المستلمة"
                   class="flex items-center gap-3 p-2.5 rounded-xl transition-colors {{ $isShipmentsReceived ? 'bg-primary text-white' : 'text-slate-700 hover:bg-slate-50' }}">
                    <span class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 {{ $isShipmentsReceived ? 'bg-white/15 text-secondary' : 'bg-emerald-50 text-emerald-600' }}">
                        <span class="material-symbols-outlined text-xl">qr_code_scanner</span>
                    </span>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs font-bold">شحنات مستلمة</span>
                            <span x-show="pendingShipmentsCount > 0"
                                  style="display: none;"
                                  class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full text-[10px] font-black {{ $isShipmentsReceived ? 'bg-secondary text-primary' : 'bg-secondary/15 text-amber-700 border border-secondary/30' }}"
                                  x-text="pendingShipmentsCount > 99 ? '99+' : pendingShipmentsCount"></span>
                        </div>
                        <div class="text-[10px] {{ $isShipmentsReceived ? 'text-white/75' : 'text-slate-400' }}">فحص الشحنات القادمة بالباركود وإشعار العملاء</div>
                    </div>
                    <span class="material-symbols-outlined text-lg {{ $isShipmentsReceived ? 'text-secondary' : 'text-slate-300' }}">chevron_left</span>
                </a>

                <div x-show="lockedNotice" style="display: none;"
                     class="mt-1 px-3 py-2 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-[11px] font-medium flex items-center gap-2">
                    <span class="material-symbols-outlined text-base text-amber-600 shrink-0">info</span>
                    <span>هذه الميزة غير مفعّلة لمكتبك حالياً، يرجى التواصل مع الإدارة لتفعيلها.</span>
                </div>
            </div>
        </div>
    </div>

    <!-- الشريط السفلي الثابت -->
    <footer class="fixed bottom-0 left-0 right-0 max-w-md mx-auto bg-white/95 backdrop-blur-md border-t border-slate-200 px-3 py-2 flex justify-around items-center z-30 shadow-[0_-4px_10px_rgba(0,0,0,0.03)]">

        <!-- 1. الرئيسية -->
        <a href="{{ route('office.dashboard') }}" data-nav-label="الرئيسية" class="flex flex-col items-center font-bold text-[10px] transition-colors {{ $isDashboard ? 'text-primary' : 'text-slate-400 hover:text-primary' }}">
            <span class="material-symbols-outlined text-2xl {{ $isDashboard ? 'text-secondary' : '' }}">home</span>
            <span class="mt-0.5">الرئيسية</span>
        </a>

        <!-- 2. الطرود (قائمة ثلاثية) -->
        <button type="button"
                @click="toggleParcelsMenu()"
                class="flex flex-col items-center font-bold text-[10px] transition-colors"
                :class="(parcelsMenuOpen || {{ $isAnyParcels ? 'true' : 'false' }}) ? 'text-primary' : 'text-slate-400 hover:text-primary'">
            <span class="relative inline-flex">
                <span class="material-symbols-outlined text-2xl"
                      :class="(parcelsMenuOpen || {{ $isAnyParcels ? 'true' : 'false' }}) ? 'text-secondary' : ''">inventory_2</span>
                <span x-show="transitCount > 0"
                      style="display: none;"
                      class="absolute -top-1 -left-2.5 min-w-[16px] h-4 px-1 rounded-full bg-secondary text-primary text-[9px] font-black flex items-center justify-center ring-2 ring-white shadow-xs"
                      x-text="transitCount > 9 ? '9+' : transitCount"></span>
            </span>
            <span class="mt-0.5 flex items-center gap-0.5">
                الطرود
                <span class="material-symbols-outlined text-[12px] transition-transform duration-150"
                      :class="parcelsMenuOpen ? 'rotate-180' : ''">expand_less</span>
            </span>
        </button>

        <!-- 3. الشحنات (قائمة ثنائية: مرسلة / مستلمة + إشعار حي) -->
        <button type="button"
                @click="toggleShipmentsMenu()"
                class="flex flex-col items-center font-bold text-[10px] transition-colors"
                :class="(shipmentsMenuOpen || {{ $isAnyShipments ? 'true' : 'false' }}) ? 'text-primary' : 'text-slate-400 hover:text-primary'">
            <span class="relative inline-flex">
                <span class="material-symbols-outlined text-2xl"
                      :class="(shipmentsMenuOpen || {{ $isAnyShipments ? 'true' : 'false' }}) ? 'text-secondary' : ''">local_shipping</span>
                <span x-show="pendingShipmentsCount > 0"
                      style="display: none;"
                      class="absolute -top-1 -left-2.5 min-w-[16px] h-4 px-1 rounded-full bg-rose-600 text-white text-[9px] font-black flex items-center justify-center ring-2 ring-white shadow-xs"
                      x-text="pendingShipmentsCount > 9 ? '9+' : pendingShipmentsCount"></span>
            </span>
            <span class="mt-0.5 flex items-center gap-0.5">
                الشحنات
                <span class="material-symbols-outlined text-[12px] transition-transform duration-150"
                      :class="shipmentsMenuOpen ? 'rotate-180' : ''">expand_less</span>
            </span>
        </button>

        @if($hasVip)
            <!-- 4. العملاء المميزين -->
            <a href="{{ route('office.favorite_customers.index') }}" data-nav-label="العملاء المميزين" class="flex flex-col items-center font-bold text-[10px] transition-colors {{ $isFavoriteCustomers ? 'text-primary' : 'text-slate-400 hover:text-primary' }}">
                <span class="material-symbols-outlined text-2xl {{ $isFavoriteCustomers ? 'text-secondary' : '' }}">stars</span>
                <span class="mt-0.5">المميزون</span>
            </a>
        @endif

    </footer>
</div>