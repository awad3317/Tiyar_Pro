@php
    $sbOffice = auth('office')->user();
    $sbOfficeId = (int) ($sbOffice?->id ?? 0);
    $sbProfile = $sbOffice ? $sbOffice->getResolvedProfile() : [];
    $sbBrandIcon = $sbProfile['icon_url'] ?? $sbProfile['logo_url'] ?? asset('icons/icon-192.png');

    $sbHasOutgoing = $sbOffice && $sbOffice->hasFeature('outgoing_parcels');
    $sbHasSentShipments = $sbOffice && $sbOffice->hasFeature('sent_shipments');
    $sbHasExternalLinks = $sbOffice && $sbOffice->hasFeature('external_partner_links');
    $sbHasVip = $sbOffice && $sbOffice->hasFeature('vip_customers_tab');

    $sbIsDashboard = request()->routeIs('office.dashboard');
    $sbIsIncoming = request()->routeIs('office.parcels.*');
    $sbIsOutgoing = request()->routeIs('office.outgoing-parcels.*');
    $sbIsTransit = request()->routeIs('office.transit-parcels.*') || request()->is('office/transit-parcels*');
    $sbIsShipmentsSent = request()->routeIs('office.shipments.sent.*');
    $sbIsShipmentsReceived = request()->routeIs('office.shipments.received.*');
    $sbIsExternalLinks = request()->routeIs('office.external-partner-links.*');
    $sbIsVip = request()->routeIs('office.favorite_customers.*');
    $sbIsWhatsapp = request()->routeIs('office.whatsapp.*');

    $sbTransitCount = null;
    if (isset($inTransitCount)) {
        $sbTransitCount = (int) $inTransitCount;
    } elseif (isset($transitParcels) && is_countable($transitParcels)) {
        $sbTransitCount = count($transitParcels);
    }

    $sbPendingShipmentsCount = null;
    if (isset($pendingReceivedShipmentsCount)) {
        $sbPendingShipmentsCount = (int) $pendingReceivedShipmentsCount;
    } elseif ($sbOffice && class_exists(\App\Services\ShipmentService::class)) {
        try {
            $sbPendingShipmentsCount = \App\Services\ShipmentService::countPendingReceivedShipments($sbOffice);
        } catch (\Throwable $e) {
            $sbPendingShipmentsCount = null;
        }
    }
@endphp

{{-- القائمة الجانبية الثابتة لنسخة سطح المكتب فقط (لا تظهر إطلاقاً على الجوال < 1024px) --}}
<aside
    x-data="{
        officeId: {{ $sbOfficeId }},
        transitCount: {{ $sbTransitCount !== null ? $sbTransitCount : 0 }},
        pendingShipmentsCount: {{ $sbPendingShipmentsCount !== null ? $sbPendingShipmentsCount : 0 }},
        canInstallPwa: false,
        isStandalonePwa: false,
        sidebarCollapsed: document.documentElement.classList.contains('mursal-sidebar-collapsed'),

        toggleSidebar() {
            this.sidebarCollapsed = !this.sidebarCollapsed;
            document.documentElement.classList.toggle('mursal-sidebar-collapsed', this.sidebarCollapsed);
            try {
                localStorage.setItem('mursal_sidebar_collapsed', this.sidebarCollapsed ? '1' : '0');
            } catch (e) {}
        },

        init() {
            if ({{ $sbTransitCount === null ? 'true' : 'false' }}) {
                const st = parseInt(localStorage.getItem('mursal_transit_count_' + this.officeId) || '0', 10);
                this.transitCount = Number.isNaN(st) ? 0 : Math.max(0, st);
            }
            if ({{ $sbPendingShipmentsCount === null ? 'true' : 'false' }}) {
                const ss = parseInt(localStorage.getItem('mursal_shipments_pending_' + this.officeId) || '0', 10);
                this.pendingShipmentsCount = Number.isNaN(ss) ? 0 : Math.max(0, ss);
            }
            if (window.MursalDesktopPWA) {
                this.canInstallPwa = window.MursalDesktopPWA.canInstall();
                this.isStandalonePwa = window.MursalDesktopPWA.isStandalone();
            }
        }
    }"
    @transit-count-updated.window="transitCount = Math.max(0, parseInt($event.detail?.count || 0, 10) || 0)"
    @shipments-pending-updated.window="pendingShipmentsCount = Math.max(0, parseInt($event.detail?.count || 0, 10) || 0)"
    @mursal-pwa-state.window="canInstallPwa = Boolean($event.detail?.canInstall); isStandalonePwa = Boolean($event.detail?.isStandalone)"
    class="mursal-desktop-sidebar hidden lg:flex lg:flex-col lg:h-screen lg:sticky lg:top-0 bg-white dark:bg-[#1C2434] text-slate-700 dark:text-slate-200 border-l border-gray-200 dark:border-slate-700/60 shrink-0 select-none z-40 transition-[width] duration-200 print:hidden">

    {{-- 1. هوية المكتب بالأعلى + زر طي/توسيع القائمة --}}
    <div class="p-4 border-b border-gray-200/80 dark:border-slate-700/60">
        <div class="sb-header-row flex items-center justify-between gap-2.5">
            <div class="flex items-center gap-3 min-w-0 flex-1 sb-brand-wrap">
                <div class="w-10 h-10 rounded-xl bg-gray-50 dark:bg-[#24303F] border border-gray-200 dark:border-slate-700/60 p-1.5 flex items-center justify-center shrink-0 overflow-hidden">
                    <img src="{{ $sbBrandIcon }}"
                         alt="{{ $sbOffice?->name ?? 'مُرسَل' }}"
                         class="w-full h-full object-contain">
                </div>
                <div class="sb-expanded-only min-w-0 flex-1">
                    <h2 class="font-extrabold text-sm text-slate-900 dark:text-slate-100 truncate">{{ $sbOffice?->name ?? 'مكتب مُرسَل' }}</h2>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate mt-0.5">{{ $sbOffice?->branch_name ?? 'المركز الرئيسي' }}</p>
                </div>
            </div>

            <button type="button"
                    @click="toggleSidebar()"
                    :title="sidebarCollapsed ? 'توسيع القائمة الجانبية' : 'طي القائمة الجانبية لتوسيع الجداول'"
                    aria-label="طي أو توسيع القائمة الجانبية"
                    class="w-8 h-8 rounded-lg bg-gray-100 hover:bg-gray-200/70 text-slate-500 hover:text-slate-800 border border-gray-200/80 dark:bg-[#24303F] dark:hover:bg-[#333A48] dark:text-slate-400 dark:hover:text-slate-100 dark:border-slate-700/60 flex items-center justify-center shrink-0 transition-colors">
                <span class="material-symbols-outlined text-[18px]"
                      x-text="sidebarCollapsed ? 'keyboard_double_arrow_left' : 'keyboard_double_arrow_right'">keyboard_double_arrow_right</span>
            </button>
        </div>

        {{-- زر تثبيت البرنامج على الكمبيوتر (يظهر تلقائياً عندما يكون المتصفح جاهزاً للتثبيت) --}}
        <button type="button"
                x-show="canInstallPwa && !isStandalonePwa"
                x-cloak
                @click="window.MursalDesktopPWA && window.MursalDesktopPWA.promptInstall()"
                title="تثبيت البرنامج على الكمبيوتر"
                class="sb-nav-link mt-3 w-full py-2 px-3 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200/80 dark:bg-amber-500/15 dark:hover:bg-amber-500/25 dark:text-amber-300 dark:border-amber-500/30 text-xs font-extrabold flex items-center justify-center gap-2 transition-colors">
            <span class="material-symbols-outlined text-lg shrink-0">install_desktop</span>
            <span class="sb-expanded-only truncate">تثبيت البرنامج على الكمبيوتر</span>
        </button>
    </div>

    {{-- 2. أقسام النظام الرئيسية --}}
    <nav class="flex-1 overflow-y-auto no-scrollbar px-3 py-4 space-y-5 text-xs">

        {{-- المجموعة أ: نظرة عامة --}}
        <div class="space-y-1">
            <p class="sb-expanded-only px-3 text-[10px] font-extrabold text-gray-400 dark:text-slate-400/80 uppercase tracking-wider mb-1.5">نظرة عامة</p>

            <a href="{{ route('office.dashboard') }}"
               data-nav-label="الرئيسية"
               title="لوحة التحكم والبحث"
               class="sb-nav-link flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all {{ $sbIsDashboard ? 'bg-amber-50/90 text-amber-900 border border-amber-200/70 font-extrabold dark:bg-[#333A48] dark:text-white dark:border-slate-600/40' : 'text-slate-600 hover:bg-gray-100/80 hover:text-slate-900 font-bold dark:text-slate-300 dark:hover:bg-[#333A48]/60 dark:hover:text-white border border-transparent' }}">
                <span class="material-symbols-outlined text-xl shrink-0 {{ $sbIsDashboard ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400 dark:text-slate-400' }}">dashboard</span>
                <span class="sb-expanded-only flex-1 truncate">لوحة التحكم والبحث</span>
            </a>
        </div>

        {{-- المجموعة ب: حركة الطرود --}}
        <div class="space-y-1">
            <p class="sb-expanded-only px-3 text-[10px] font-extrabold text-gray-400 dark:text-slate-400/80 uppercase tracking-wider mb-1.5">إدارة الطرود والسندات</p>
            <div class="sb-collapsed-only hidden border-t border-gray-200/80 dark:border-slate-700/60 mx-2 my-2"></div>

            <a href="{{ route('office.parcels.index') }}"
               data-nav-label="الطرود المستلمة"
               title="الطرود المستلمة"
               class="sb-nav-link flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all {{ $sbIsIncoming ? 'bg-amber-50/90 text-amber-900 border border-amber-200/70 font-extrabold dark:bg-[#333A48] dark:text-white dark:border-slate-600/40' : 'text-slate-600 hover:bg-gray-100/80 hover:text-slate-900 font-bold dark:text-slate-300 dark:hover:bg-[#333A48]/60 dark:hover:text-white border border-transparent' }}">
                <span class="material-symbols-outlined text-xl shrink-0 {{ $sbIsIncoming ? 'text-amber-600 dark:text-amber-400' : 'text-sky-600 dark:text-sky-400' }}">move_to_inbox</span>
                <span class="sb-expanded-only flex-1 truncate">الطرود المستلمة</span>
                <span class="sb-expanded-only text-[9px] px-1.5 py-0.5 rounded {{ $sbIsIncoming ? 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300' : 'bg-gray-100 text-gray-600 dark:bg-slate-700/70 dark:text-slate-300' }}">أوفلاين</span>
            </a>

            @if ($sbHasOutgoing)
                <a href="{{ route('office.outgoing-parcels.index') }}"
                   data-nav-label="الطرود المرسلة"
                   title="الطرود المرسلة والسندات"
                   class="sb-nav-link flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all {{ $sbIsOutgoing ? 'bg-amber-50/90 text-amber-900 border border-amber-200/70 font-extrabold dark:bg-[#333A48] dark:text-white dark:border-slate-600/40' : 'text-slate-600 hover:bg-gray-100/80 hover:text-slate-900 font-bold dark:text-slate-300 dark:hover:bg-[#333A48]/60 dark:hover:text-white border border-transparent' }}">
                    <span class="material-symbols-outlined text-xl shrink-0 {{ $sbIsOutgoing ? 'text-amber-600 dark:text-amber-400' : 'text-amber-500 dark:text-amber-400' }}">outbox</span>
                    <span class="sb-expanded-only flex-1 truncate">الطرود المرسلة والسندات</span>
                    <span class="sb-expanded-only material-symbols-outlined text-sm {{ $sbIsOutgoing ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400' }}" title="طباعة السندات">print</span>
                </a>
            @else
                <button type="button"
                        title="الطرود المرسلة (غير مفعّلة)"
                        @click="typeof showFeedback === 'function' && showFeedback('ميزة الطرود المرسلة غير مفعّلة لهذا المكتب، يرجى التواصل مع الإدارة لتفعيلها.', true)"
                        class="sb-nav-link w-full flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-slate-400 dark:text-slate-500 hover:bg-gray-100/60 dark:hover:bg-[#333A48]/30 transition-colors text-right border border-transparent">
                    <span class="material-symbols-outlined text-xl shrink-0 text-slate-400 dark:text-slate-500">outbox</span>
                    <span class="sb-expanded-only flex-1 truncate">الطرود المرسلة</span>
                    <span class="sb-expanded-only material-symbols-outlined text-sm text-amber-500/80">lock</span>
                </button>
            @endif

            <a href="{{ route('office.transit-parcels.index') }}"
               data-nav-label="الطرود في الطريق"
               title="قادمة في الطريق"
               class="sb-nav-link relative flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all {{ $sbIsTransit ? 'bg-amber-50/90 text-amber-900 border border-amber-200/70 font-extrabold dark:bg-[#333A48] dark:text-white dark:border-slate-600/40' : 'text-slate-600 hover:bg-gray-100/80 hover:text-slate-900 font-bold dark:text-slate-300 dark:hover:bg-[#333A48]/60 dark:hover:text-white border border-transparent' }}">
                <span class="material-symbols-outlined text-xl shrink-0 {{ $sbIsTransit ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">local_shipping</span>
                <span class="sb-expanded-only flex-1 truncate">قادمة في الطريق</span>
                <span x-show="transitCount > 0"
                      x-cloak
                      class="sb-badge min-w-[22px] h-5 px-1.5 rounded-full text-[10px] font-black flex items-center justify-center {{ $sbIsTransit ? 'bg-amber-500 text-white' : 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300' }}"
                      x-text="transitCount > 99 ? '99+' : transitCount"></span>
            </a>
        </div>

        {{-- المجموعة ج: الكشوفات والشحنات --}}
        <div class="space-y-1">
            <p class="sb-expanded-only px-3 text-[10px] font-extrabold text-gray-400 dark:text-slate-400/80 uppercase tracking-wider mb-1.5">الكشوفات والشحنات</p>
            <div class="sb-collapsed-only hidden border-t border-gray-200/80 dark:border-slate-700/60 mx-2 my-2"></div>

            @if ($sbHasSentShipments)
                <a href="{{ route('office.shipments.sent.index') }}"
                   data-nav-label="الشحنات المرسلة"
                   title="الشحنات المرسلة (A4)"
                   class="sb-nav-link flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all {{ $sbIsShipmentsSent ? 'bg-amber-50/90 text-amber-900 border border-amber-200/70 font-extrabold dark:bg-[#333A48] dark:text-white dark:border-slate-600/40' : 'text-slate-600 hover:bg-gray-100/80 hover:text-slate-900 font-bold dark:text-slate-300 dark:hover:bg-[#333A48]/60 dark:hover:text-white border border-transparent' }}">
                    <span class="material-symbols-outlined text-xl shrink-0 {{ $sbIsShipmentsSent ? 'text-amber-600 dark:text-amber-400' : 'text-amber-500 dark:text-amber-400' }}">outbound</span>
                    <span class="sb-expanded-only flex-1 truncate">الشحنات المرسلة (A4)</span>
                    <span class="sb-expanded-only text-[9px] px-1.5 py-0.5 rounded {{ $sbIsShipmentsSent ? 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300' : 'bg-gray-100 text-gray-600 dark:bg-slate-700/70 dark:text-slate-300' }}">أوفلاين</span>
                </a>
            @else
                <button type="button"
                        title="الشحنات المرسلة (غير مفعّلة)"
                        @click="typeof showFeedback === 'function' && showFeedback('ميزة الشحنات المرسلة غير مفعّلة لهذا المكتب، يرجى التواصل مع الإدارة لتفعيلها.', true)"
                        class="sb-nav-link w-full flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-slate-400 dark:text-slate-500 hover:bg-gray-100/60 dark:hover:bg-[#333A48]/30 transition-colors text-right border border-transparent">
                    <span class="material-symbols-outlined text-xl shrink-0 text-slate-400 dark:text-slate-500">outbound</span>
                    <span class="sb-expanded-only flex-1 truncate">الشحنات المرسلة</span>
                    <span class="sb-expanded-only material-symbols-outlined text-sm text-amber-500/80">lock</span>
                </button>
            @endif

            <a href="{{ route('office.shipments.received.index') }}"
               data-nav-label="الشحنات المستلمة"
               title="الشحنات المستلمة"
               class="sb-nav-link relative flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all {{ $sbIsShipmentsReceived ? 'bg-amber-50/90 text-amber-900 border border-amber-200/70 font-extrabold dark:bg-[#333A48] dark:text-white dark:border-slate-600/40' : 'text-slate-600 hover:bg-gray-100/80 hover:text-slate-900 font-bold dark:text-slate-300 dark:hover:bg-[#333A48]/60 dark:hover:text-white border border-transparent' }}">
                <span class="material-symbols-outlined text-xl shrink-0 {{ $sbIsShipmentsReceived ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">qr_code_scanner</span>
                <span class="sb-expanded-only flex-1 truncate">الشحنات المستلمة</span>
                <span x-show="pendingShipmentsCount > 0"
                      x-cloak
                      class="sb-badge min-w-[22px] h-5 px-1.5 rounded-full text-[10px] font-black flex items-center justify-center {{ $sbIsShipmentsReceived ? 'bg-rose-600 text-white' : 'bg-rose-500 text-white' }}"
                      x-text="pendingShipmentsCount > 99 ? '99+' : pendingShipmentsCount"></span>
            </a>

            @if ($sbHasExternalLinks)
                <a href="{{ route('office.external-partner-links.index') }}"
                   data-nav-label="روابط المكاتب الخارجية"
                   title="روابط المكاتب الخارجية"
                   class="sb-nav-link flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all {{ $sbIsExternalLinks ? 'bg-amber-50/90 text-amber-900 border border-amber-200/70 font-extrabold dark:bg-[#333A48] dark:text-white dark:border-slate-600/40' : 'text-slate-600 hover:bg-gray-100/80 hover:text-slate-900 font-bold dark:text-slate-300 dark:hover:bg-[#333A48]/60 dark:hover:text-white border border-transparent' }}">
                    <span class="material-symbols-outlined text-xl shrink-0 {{ $sbIsExternalLinks ? 'text-amber-600 dark:text-amber-400' : 'text-violet-600 dark:text-violet-400' }}">add_link</span>
                    <span class="sb-expanded-only flex-1 truncate">روابط المكاتب الخارجية</span>
                </a>
            @endif
        </div>

        {{-- المجموعة د: العملاء والإعدادات --}}
        <div class="space-y-1">
            <p class="sb-expanded-only px-3 text-[10px] font-extrabold text-gray-400 dark:text-slate-400/80 uppercase tracking-wider mb-1.5">العملاء والإعدادات</p>
            <div class="sb-collapsed-only hidden border-t border-gray-200/80 dark:border-slate-700/60 mx-2 my-2"></div>

            @if ($sbHasVip)
                <a href="{{ route('office.favorite_customers.index') }}"
                   data-nav-label="العملاء المميزين"
                   title="العملاء المميزون"
                   class="sb-nav-link flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all {{ $sbIsVip ? 'bg-amber-50/90 text-amber-900 border border-amber-200/70 font-extrabold dark:bg-[#333A48] dark:text-white dark:border-slate-600/40' : 'text-slate-600 hover:bg-gray-100/80 hover:text-slate-900 font-bold dark:text-slate-300 dark:hover:bg-[#333A48]/60 dark:hover:text-white border border-transparent' }}">
                    <span class="material-symbols-outlined text-xl shrink-0 {{ $sbIsVip ? 'text-amber-600 dark:text-amber-400' : 'text-amber-500 dark:text-amber-300' }}">stars</span>
                    <span class="sb-expanded-only flex-1 truncate">العملاء المميزون</span>
                    <span class="sb-expanded-only text-[9px] px-1.5 py-0.5 rounded {{ $sbIsVip ? 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300' : 'bg-gray-100 text-gray-600 dark:bg-slate-700/70 dark:text-slate-300' }}">أوفلاين</span>
                </a>
            @endif

            <a href="{{ route('office.whatsapp.index') }}"
               data-nav-label="إعدادات الواتساب"
               title="إعدادات الواتساب"
               class="sb-nav-link flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all {{ $sbIsWhatsapp ? 'bg-amber-50/90 text-amber-900 border border-amber-200/70 font-extrabold dark:bg-[#333A48] dark:text-white dark:border-slate-600/40' : 'text-slate-600 hover:bg-gray-100/80 hover:text-slate-900 font-bold dark:text-slate-300 dark:hover:bg-[#333A48]/60 dark:hover:text-white border border-transparent' }}">
                <span class="material-symbols-outlined text-xl shrink-0 {{ $sbIsWhatsapp ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">chat</span>
                <span class="sb-expanded-only flex-1 truncate">إعدادات الواتساب</span>
            </a>
        </div>
    </nav>

    {{-- 3. شريط حالة الاتصال والمزامنة واختصارات الكيبورد أسفل القائمة --}}
    <div class="sb-footer p-4 border-t border-gray-200/80 dark:border-slate-700/60 bg-gray-50/80 dark:bg-[#161D2A] space-y-2.5">
        <div class="sb-status-row flex items-center justify-between gap-2">
            <div class="flex items-center gap-2 min-w-0">
                <span class="w-2.5 h-2.5 rounded-full shrink-0"
                      :title="isOnline ? 'متصل وجاهز للمزامنة' : 'وضع أوفلاين (حفظ محلي)'"
                      :class="isOnline ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500'"></span>
                <span class="sb-expanded-only text-[11px] font-extrabold truncate"
                      :class="isOnline ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400'"
                      x-text="isOnline ? 'متصل وجاهز للمزامنة' : 'وضع أوفلاين (حفظ محلي)'"></span>
            </div>

            <button type="button"
                    @click="(typeof syncData === 'function') ? syncData() : window.location.reload()"
                    title="مزامنة وتحديث الآن"
                    class="px-2.5 py-1 rounded-lg bg-white hover:bg-gray-100 text-[10px] font-extrabold text-slate-700 border border-gray-200 dark:bg-[#24303F] dark:hover:bg-[#333A48] dark:text-slate-200 dark:border-slate-700/60 inline-flex items-center gap-1 transition-colors shrink-0">
                <span class="material-symbols-outlined text-sm"
                      :class="typeof isSyncing !== 'undefined' && isSyncing ? 'animate-spin text-amber-500' : ''">sync</span>
                <span class="sb-expanded-only">مزامنة</span>
            </button>
        </div>

        <template x-if="typeof syncQueue !== 'undefined' && syncQueue.length > 0">
            <div class="sb-expanded-only rounded-lg bg-amber-50 border border-amber-200 px-2.5 py-1.5 text-[10px] font-bold text-amber-800 dark:bg-amber-500/15 dark:border-amber-500/30 dark:text-amber-300 flex items-center justify-between">
                <span>عمليات بانتظار المزامنة:</span>
                <span class="font-mono font-black" x-text="syncQueue.length"></span>
            </div>
        </template>

        <div class="sb-expanded-only flex items-center justify-between text-[10px] text-slate-500 dark:text-slate-400 pt-1 border-t border-gray-200/80 dark:border-slate-700/60">
            <span class="inline-flex items-center gap-1">
                <kbd class="px-1.5 py-0.5 rounded bg-white border border-gray-200 font-mono text-[9px] text-slate-600 dark:bg-[#24303F] dark:border-slate-700 dark:text-slate-300">Alt+N</kbd>
                <span>إضافة سريعة</span>
            </span>
            <span class="inline-flex items-center gap-1">
                <kbd class="px-1.5 py-0.5 rounded bg-white border border-gray-200 font-mono text-[9px] text-slate-600 dark:bg-[#24303F] dark:border-slate-700 dark:text-slate-300">/</kbd>
                <span>بحث فوري</span>
            </span>
        </div>
    </div>
</aside>
