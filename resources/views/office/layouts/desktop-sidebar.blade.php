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
    class="hidden lg:flex lg:flex-col lg:w-72 xl:w-80 lg:h-screen lg:sticky lg:top-0 bg-primary text-white border-l border-white/10 shrink-0 select-none z-40 print:hidden">

    {{-- 1. هوية المكتب بالأعلى --}}
    <div class="p-5 border-b border-white/10">
        <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-2xl bg-white/95 border border-white/20 p-1.5 flex items-center justify-center shrink-0 shadow-sm overflow-hidden">
                <img src="{{ $sbBrandIcon }}"
                     alt="{{ $sbOffice?->name ?? 'مُرسَل' }}"
                     class="w-full h-full object-contain">
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-1.5">
                    <h2 class="font-extrabold text-sm text-white truncate">{{ $sbOffice?->name ?? 'مكتب مُرسَل' }}</h2>
                </div>
                <p class="text-[11px] text-slate-300/85 truncate mt-0.5">{{ $sbOffice?->branch_name ?? 'المركز الرئيسي' }}</p>
            </div>
        </div>

        {{-- زر تثبيت البرنامج على الكمبيوتر (يظهر تلقائياً عندما يكون المتصفح جاهزاً للتثبيت) --}}
        <button type="button"
                x-show="canInstallPwa && !isStandalonePwa"
                x-cloak
                @click="window.MursalDesktopPWA && window.MursalDesktopPWA.promptInstall()"
                class="mt-3.5 w-full py-2.5 px-3 rounded-xl bg-secondary hover:bg-amber-500 text-primary text-xs font-extrabold flex items-center justify-center gap-2 shadow-md transition-colors">
            <span class="material-symbols-outlined text-lg">install_desktop</span>
            <span>تثبيت البرنامج على الكمبيوتر</span>
        </button>
    </div>

    {{-- 2. أقسام النظام الرئيسية --}}
    <nav class="flex-1 overflow-y-auto no-scrollbar px-3 py-4 space-y-5 text-xs">

        {{-- المجموعة أ: نظرة عامة --}}
        <div class="space-y-1">
            <p class="px-3 text-[10px] font-extrabold text-slate-400 uppercase tracking-wider mb-1.5">نظرة عامة</p>

            <a href="{{ route('office.dashboard') }}"
               data-nav-label="الرئيسية"
               class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold transition-all {{ $sbIsDashboard ? 'bg-secondary text-primary font-extrabold shadow-sm' : 'text-slate-200 hover:bg-white/10 hover:text-white' }}">
                <span class="material-symbols-outlined text-xl {{ $sbIsDashboard ? 'text-primary' : 'text-secondary' }}">dashboard</span>
                <span class="flex-1">لوحة التحكم والبحث</span>
            </a>
        </div>

        {{-- المجموعة ب: حركة الطرود --}}
        <div class="space-y-1">
            <p class="px-3 text-[10px] font-extrabold text-slate-400 uppercase tracking-wider mb-1.5">إدارة الطرود والسندات</p>

            <a href="{{ route('office.parcels.index') }}"
               data-nav-label="الطرود المستلمة"
               class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold transition-all {{ $sbIsIncoming ? 'bg-secondary text-primary font-extrabold shadow-sm' : 'text-slate-200 hover:bg-white/10 hover:text-white' }}">
                <span class="material-symbols-outlined text-xl {{ $sbIsIncoming ? 'text-primary' : 'text-sky-400' }}">move_to_inbox</span>
                <span class="flex-1">الطرود المستلمة</span>
                <span class="text-[9px] px-1.5 py-0.5 rounded {{ $sbIsIncoming ? 'bg-primary/15 text-primary' : 'bg-white/10 text-slate-300' }}">أوفلاين</span>
            </a>

            @if ($sbHasOutgoing)
                <a href="{{ route('office.outgoing-parcels.index') }}"
                   data-nav-label="الطرود المرسلة"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold transition-all {{ $sbIsOutgoing ? 'bg-secondary text-primary font-extrabold shadow-sm' : 'text-slate-200 hover:bg-white/10 hover:text-white' }}">
                    <span class="material-symbols-outlined text-xl {{ $sbIsOutgoing ? 'text-primary' : 'text-amber-400' }}">outbox</span>
                    <span class="flex-1">الطرود المرسلة والسندات</span>
                    <span class="material-symbols-outlined text-sm {{ $sbIsOutgoing ? 'text-primary' : 'text-slate-400' }}" title="طباعة السندات">print</span>
                </a>
            @else
                <button type="button"
                        @click="typeof showFeedback === 'function' && showFeedback('ميزة الطرود المرسلة غير مفعّلة لهذا المكتب، يرجى التواصل مع الإدارة لتفعيلها.', true)"
                        class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-slate-400 hover:bg-white/5 transition-colors text-right">
                    <span class="material-symbols-outlined text-xl text-slate-500">outbox</span>
                    <span class="flex-1">الطرود المرسلة</span>
                    <span class="material-symbols-outlined text-sm text-amber-400">lock</span>
                </button>
            @endif

            <a href="{{ route('office.transit-parcels.index') }}"
               data-nav-label="الطرود في الطريق"
               class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold transition-all {{ $sbIsTransit ? 'bg-secondary text-primary font-extrabold shadow-sm' : 'text-slate-200 hover:bg-white/10 hover:text-white' }}">
                <span class="material-symbols-outlined text-xl {{ $sbIsTransit ? 'text-primary' : 'text-emerald-400' }}">local_shipping</span>
                <span class="flex-1">قادمة في الطريق</span>
                <span x-show="transitCount > 0"
                      x-cloak
                      class="min-w-[22px] h-5 px-1.5 rounded-full text-[10px] font-black flex items-center justify-center {{ $sbIsTransit ? 'bg-primary text-secondary' : 'bg-secondary text-primary' }}"
                      x-text="transitCount > 99 ? '99+' : transitCount"></span>
            </a>
        </div>

        {{-- المجموعة ج: الكشوفات والشحنات --}}
        <div class="space-y-1">
            <p class="px-3 text-[10px] font-extrabold text-slate-400 uppercase tracking-wider mb-1.5">الكشوفات والشحنات</p>

            @if ($sbHasSentShipments)
                <a href="{{ route('office.shipments.sent.index') }}"
                   data-nav-label="الشحنات المرسلة"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold transition-all {{ $sbIsShipmentsSent ? 'bg-secondary text-primary font-extrabold shadow-sm' : 'text-slate-200 hover:bg-white/10 hover:text-white' }}">
                    <span class="material-symbols-outlined text-xl {{ $sbIsShipmentsSent ? 'text-primary' : 'text-amber-400' }}">outbound</span>
                    <span class="flex-1">الشحنات المرسلة (A4)</span>
                    <span class="text-[9px] px-1.5 py-0.5 rounded {{ $sbIsShipmentsSent ? 'bg-primary/15 text-primary' : 'bg-white/10 text-slate-300' }}">أوفلاين</span>
                </a>
            @else
                <button type="button"
                        @click="typeof showFeedback === 'function' && showFeedback('ميزة الشحنات المرسلة غير مفعّلة لهذا المكتب، يرجى التواصل مع الإدارة لتفعيلها.', true)"
                        class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-slate-400 hover:bg-white/5 transition-colors text-right">
                    <span class="material-symbols-outlined text-xl text-slate-500">outbound</span>
                    <span class="flex-1">الشحنات المرسلة</span>
                    <span class="material-symbols-outlined text-sm text-amber-400">lock</span>
                </button>
            @endif

            <a href="{{ route('office.shipments.received.index') }}"
               data-nav-label="الشحنات المستلمة"
               class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold transition-all {{ $sbIsShipmentsReceived ? 'bg-secondary text-primary font-extrabold shadow-sm' : 'text-slate-200 hover:bg-white/10 hover:text-white' }}">
                <span class="material-symbols-outlined text-xl {{ $sbIsShipmentsReceived ? 'text-primary' : 'text-emerald-400' }}">qr_code_scanner</span>
                <span class="flex-1">الشحنات المستلمة</span>
                <span x-show="pendingShipmentsCount > 0"
                      x-cloak
                      class="min-w-[22px] h-5 px-1.5 rounded-full text-[10px] font-black flex items-center justify-center {{ $sbIsShipmentsReceived ? 'bg-primary text-white' : 'bg-rose-500 text-white' }}"
                      x-text="pendingShipmentsCount > 99 ? '99+' : pendingShipmentsCount"></span>
            </a>

            @if ($sbHasExternalLinks)
                <a href="{{ route('office.external-partner-links.index') }}"
                   data-nav-label="روابط المكاتب الخارجية"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold transition-all {{ $sbIsExternalLinks ? 'bg-secondary text-primary font-extrabold shadow-sm' : 'text-slate-200 hover:bg-white/10 hover:text-white' }}">
                    <span class="material-symbols-outlined text-xl {{ $sbIsExternalLinks ? 'text-primary' : 'text-violet-400' }}">add_link</span>
                    <span class="flex-1">روابط المكاتب الخارجية</span>
                </a>
            @endif
        </div>

        {{-- المجموعة د: العملاء والإعدادات --}}
        <div class="space-y-1">
            <p class="px-3 text-[10px] font-extrabold text-slate-400 uppercase tracking-wider mb-1.5">العملاء والإعدادات</p>

            @if ($sbHasVip)
                <a href="{{ route('office.favorite_customers.index') }}"
                   data-nav-label="العملاء المميزين"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold transition-all {{ $sbIsVip ? 'bg-secondary text-primary font-extrabold shadow-sm' : 'text-slate-200 hover:bg-white/10 hover:text-white' }}">
                    <span class="material-symbols-outlined text-xl {{ $sbIsVip ? 'text-primary' : 'text-amber-300' }}">stars</span>
                    <span class="flex-1">العملاء المميزون</span>
                    <span class="text-[9px] px-1.5 py-0.5 rounded {{ $sbIsVip ? 'bg-primary/15 text-primary' : 'bg-white/10 text-slate-300' }}">أوفلاين</span>
                </a>
            @endif

            <a href="{{ route('office.whatsapp.index') }}"
               data-nav-label="إعدادات الواتساب"
               class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold transition-all {{ $sbIsWhatsapp ? 'bg-secondary text-primary font-extrabold shadow-sm' : 'text-slate-200 hover:bg-white/10 hover:text-white' }}">
                <span class="material-symbols-outlined text-xl {{ $sbIsWhatsapp ? 'text-primary' : 'text-emerald-400' }}">chat</span>
                <span class="flex-1">إعدادات الواتساب</span>
            </a>
        </div>
    </nav>

    {{-- 3. شريط حالة الاتصال والمزامنة واختصارات الكيبورد أسفل القائمة --}}
    <div class="p-4 border-t border-white/10 bg-black/15 space-y-2.5">
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2 min-w-0">
                <span class="w-2.5 h-2.5 rounded-full shrink-0"
                      :class="isOnline ? 'bg-emerald-400 animate-pulse' : 'bg-amber-400'"></span>
                <span class="text-[11px] font-extrabold truncate"
                      :class="isOnline ? 'text-emerald-300' : 'text-amber-300'"
                      x-text="isOnline ? 'متصل وجاهز للمزامنة' : 'وضع أوفلاين (حفظ محلي)'"></span>
            </div>

            <button type="button"
                    @click="(typeof syncData === 'function') ? syncData() : window.location.reload()"
                    title="مزامنة وتحديث الآن"
                    class="px-2.5 py-1 rounded-lg bg-white/10 hover:bg-white/20 text-[10px] font-extrabold text-white inline-flex items-center gap-1 transition-colors shrink-0">
                <span class="material-symbols-outlined text-sm"
                      :class="typeof isSyncing !== 'undefined' && isSyncing ? 'animate-spin text-secondary' : ''">sync</span>
                <span>مزامنة</span>
            </button>
        </div>

        <template x-if="typeof syncQueue !== 'undefined' && syncQueue.length > 0">
            <div class="rounded-lg bg-secondary/20 border border-secondary/40 px-2.5 py-1.5 text-[10px] font-bold text-secondary flex items-center justify-between">
                <span>عمليات بانتظار المزامنة:</span>
                <span class="font-mono font-black" x-text="syncQueue.length"></span>
            </div>
        </template>

        <div class="flex items-center justify-between text-[10px] text-slate-400 pt-1 border-t border-white/10">
            <span class="inline-flex items-center gap-1">
                <kbd class="px-1.5 py-0.5 rounded bg-white/10 font-mono text-[9px] text-slate-200">Alt+N</kbd>
                <span>إضافة سريعة</span>
            </span>
            <span class="inline-flex items-center gap-1">
                <kbd class="px-1.5 py-0.5 rounded bg-white/10 font-mono text-[9px] text-slate-200">/</kbd>
                <span>بحث فوري</span>
            </span>
        </div>
    </div>
</aside>
