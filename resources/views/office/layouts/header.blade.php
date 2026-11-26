@php
    $office = auth('office')->user();
    $headerProfile = $office ? $office->getResolvedProfile() : [];
    $headerBrandIcon = $headerProfile['icon_url'] ?? $headerProfile['logo_url'] ?? null;

    // إعدادات الشريط العلوي النحيف لسطح المكتب (العنوان + العداد + نوع البحث)
    $desktopPageTitle = 'بوابة المكتب';
    $desktopInitialCount = null;
    $desktopSearchMode = null; // alpine-search | outgoing | sent | transit | dashboard | null
    $desktopSearchPlaceholder = $searchPlaceholder ?? 'بحث سريع...';
    $desktopInitialQuery = isset($search) ? (string) $search : '';

    if (request()->routeIs('office.outgoing-parcels.*')) {
        $desktopPageTitle = 'الطرود المرسلة والسندات';
        $desktopInitialCount = isset($counts['all']) ? (int) $counts['all'] : (isset($parcels) && method_exists($parcels, 'total') ? (int) $parcels->total() : null);
        $desktopSearchMode = 'outgoing';
        $desktopSearchPlaceholder = 'بحث برقم الهاتف، السند، المرسل، المستلم، نوع الطرد...';
    } elseif (request()->routeIs('office.parcels.*')) {
        $desktopPageTitle = 'الطرود المستلمة';
        $desktopSearchMode = 'alpine-search';
        $desktopSearchPlaceholder = $searchPlaceholder ?? 'بحث برقم الهاتف، السند، نوع الطرد...';
    } elseif (request()->routeIs('office.transit-parcels.*')) {
        $desktopPageTitle = 'طرود قادمة في الطريق';
        $desktopInitialCount = isset($transitParcels) && is_countable($transitParcels) ? count($transitParcels) : null;
        $desktopSearchMode = 'transit';
        $desktopSearchPlaceholder = 'بحث بالهاتف، السند، رقم الكشف، السائق، الفرع...';
    } elseif (request()->routeIs('office.shipments.sent.index')) {
        $desktopPageTitle = 'الشحنات المرسلة (الكشوفات)';
        $desktopInitialCount = isset($counts['all']) ? (int) $counts['all'] : null;
        $desktopSearchMode = 'sent';
        $desktopSearchPlaceholder = 'بحث برقم الكشف، اسم السائق، أو رقم السائق...';
    } elseif (request()->routeIs('office.shipments.sent.*')) {
        $desktopPageTitle = 'تفاصيل الشحنة المرسلة';
    } elseif (request()->routeIs('office.shipments.received.index')) {
        $desktopPageTitle = 'الشحنات المستلمة';
        $desktopInitialCount = isset($receivedCounts['all']) ? (int) $receivedCounts['all'] : null;
    } elseif (request()->routeIs('office.shipments.received.*')) {
        $desktopPageTitle = 'فحص واستلام الشحنة';
    } elseif (request()->routeIs('office.favorite_customers.*')) {
        $desktopPageTitle = 'العملاء المميزون';
        $desktopSearchMode = 'alpine-search';
        $desktopSearchPlaceholder = $searchPlaceholder ?? 'بحث بالاسم أو برقم الهاتف...';
    } elseif (request()->routeIs('office.external-partner-links.*')) {
        $desktopPageTitle = 'روابط المكاتب الخارجية';
        $desktopInitialCount = isset($links) && is_countable($links) ? count($links) : null;
    } elseif (request()->routeIs('office.whatsapp.*')) {
        $desktopPageTitle = 'إعدادات الواتساب';
    } elseif (request()->routeIs('office.dashboard')) {
        $desktopPageTitle = 'لوحة التحكم الرئيسية';
        $desktopInitialCount = isset($stats['total']) ? (int) $stats['total'] : null;
        $desktopSearchMode = 'dashboard';
        $desktopSearchPlaceholder = 'بحث شامل: رقم السند، اسم أو رقم المرسل والمستلم...';
    }
@endphp

{{-- =========================================================================
     1. هيدر الجوال (< 1024px): مطابق للتصميم الأصلي 100% دون أي تغيير
   ========================================================================= --}}
<header class="lg:hidden bg-primary text-white p-4 sticky top-0 z-30 shadow-md print:hidden">
    <div class="flex items-center justify-between gap-3">
        <!-- شعار واسم المكتب والفرع (قائمة منسدلة) -->
        <div class="min-w-0 flex-1 relative"
             x-data="{
                 menuOpen: false,
                 canInstallPwa: window.MursalDesktopPWA ? window.MursalDesktopPWA.canInstall() : false
             }"
             @mursal-pwa-state.window="canInstallPwa = Boolean($event.detail?.canInstall)">
            <button type="button" @click="menuOpen = !menuOpen"
                    class="w-full text-right flex items-center gap-2 group">
                @if ($headerBrandIcon)
                    <div class="w-9 h-9 rounded-xl bg-white/95 border border-white/20 p-1 flex items-center justify-center shrink-0 overflow-hidden shadow-xs">
                        <img src="{{ $headerBrandIcon }}"
                             alt="{{ $office->name ?? 'المكتب' }}"
                             class="w-full h-full object-contain">
                    </div>
                @endif

                <div class="min-w-0 flex-1">
                    <h1 class="font-extrabold text-base sm:text-lg leading-tight truncate text-white">
                        {{ $office->name ?? 'المكتب' }}
                    </h1>
                    <p class="text-xs text-slate-300/80 truncate mt-0.5">
                        {{ $office->branch_name ?? 'المركز الرئيسي' }}
                    </p>
                </div>
                <span class="material-symbols-outlined text-slate-300/80 text-sm transition-transform shrink-0"
                      :class="menuOpen ? 'rotate-180' : ''">expand_more</span>
            </button>

            <!-- القائمة المنسدلة -->
            <div x-show="menuOpen" x-cloak
                 @click.outside="menuOpen = false"
                 x-transition:enter="ease-out duration-150"
                 x-transition:enter-start="opacity-0 -translate-y-2 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="ease-in duration-100"
                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                 x-transition:leave-end="opacity-0 -translate-y-2 scale-95"
                 class="absolute top-full right-0 mt-2 w-56 bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden z-50">

                <!-- رابط إعدادات الواتساب -->
                <a href="{{ route('office.whatsapp.index') }}"
                   class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition-colors group/item">
                    <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center group-hover/item:bg-emerald-100 transition-colors">
                        <span class="material-symbols-outlined text-base">chat</span>
                    </div>
                    <div>
                        <p class="text-xs font-extrabold text-slate-700">إعدادات الواتساب</p>
                    </div>
                </a>

                <!-- زر تثبيت البرنامج على الجهاز -->
                <button type="button"
                        x-show="canInstallPwa"
                        x-cloak
                        @click="menuOpen = false; window.MursalDesktopPWA && window.MursalDesktopPWA.promptInstall()"
                        class="w-full text-right flex items-center gap-3 px-4 py-3 hover:bg-amber-50/70 border-t border-slate-100 transition-colors group/item">
                    <div class="w-8 h-8 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center group-hover/item:bg-amber-200 transition-colors">
                        <span class="material-symbols-outlined text-base">install_desktop</span>
                    </div>
                    <div>
                        <p class="text-xs font-extrabold text-slate-800">تثبيت البرنامج على الجهاز</p>
                    </div>
                </button>

                @if ($office && $office->hasFeature('external_partner_links'))
                    <div class="border-t border-slate-100"></div>

                    <!-- رابط روابط المكاتب الخارجية -->
                    <a href="{{ route('office.external-partner-links.index') }}"
                       data-nav-label="روابط المكاتب الخارجية"
                       class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition-colors group/item {{ request()->routeIs('office.external-partner-links.*') ? 'bg-amber-50/60' : '' }}">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center group-hover/item:bg-amber-100 transition-colors">
                            <span class="material-symbols-outlined text-base">add_link</span>
                        </div>
                        <div>
                            <p class="text-xs font-extrabold text-slate-700">روابط المكاتب الخارجية</p>
                        </div>
                    </a>
                @endif

                <div class="border-t border-slate-100"></div>
            </div>
        </div>

        <!-- أزرار الإجراءات وحالة الاتصال (للجوال) -->
        <div class="flex items-center gap-2 shrink-0">
            <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold"
                 :class="isOnline ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-amber-500/20 text-amber-400 border border-amber-500/30'">
                <span class="w-2 h-2 rounded-full" :class="isOnline ? 'bg-emerald-400 animate-pulse' : 'bg-amber-400'"></span>
                <span x-text="isOnline ? 'متصل' : 'بدون نت'"></span>
            </div>

            @if (request()->routeIs('office.outgoing-parcels.*'))
                <button type="button"
                        @click="window.dispatchEvent(new CustomEvent('open-outgoing-parcel-modal'))"
                        title="إضافة طرد صادر"
                        class="w-8 h-8 rounded-xl bg-secondary/15 border border-secondary/30 hover:bg-secondary/25 flex items-center justify-center text-secondary hover:text-white transition-colors">
                    <span class="material-symbols-outlined text-[19px] font-bold">add</span>
                </button>
            @elseif (request()->routeIs('office.shipments.sent.index'))
                <button type="button"
                        @click="window.dispatchEvent(new CustomEvent('open-create-shipment-modal'))"
                        title="إنشاء كشف شحنة جديد"
                        class="w-8 h-8 rounded-xl bg-secondary/15 border border-secondary/30 hover:bg-secondary/25 flex items-center justify-center text-secondary hover:text-white transition-colors">
                    <span class="material-symbols-outlined text-[19px] font-bold">add</span>
                </button>
            @elseif (request()->routeIs('office.shipments.sent.*'))
                <a href="{{ route('office.shipments.sent.index', ['create' => 1]) }}"
                   title="إنشاء كشف شحنة جديد"
                   class="w-8 h-8 rounded-xl bg-secondary/15 border border-secondary/30 hover:bg-secondary/25 flex items-center justify-center text-secondary hover:text-white transition-colors">
                    <span class="material-symbols-outlined text-[19px] font-bold">add</span>
                </a>
            @elseif (request()->routeIs('office.favorite_customers.*'))
                <button type="button"
                        @click="window.dispatchEvent(new CustomEvent('open-vip-customer-modal'))"
                        title="إضافة عميل مميز"
                        class="w-8 h-8 rounded-xl bg-secondary/15 border border-secondary/30 hover:bg-secondary/25 flex items-center justify-center text-secondary hover:text-white transition-colors">
                    <span class="material-symbols-outlined text-[19px] font-bold">add</span>
                </button>
            @elseif (request()->routeIs('office.external-partner-links.*'))
                <button type="button"
                        @click="window.dispatchEvent(new CustomEvent('open-partner-link-modal'))"
                        title="إضافة مكتب خارجي"
                        class="w-8 h-8 rounded-xl bg-secondary/15 border border-secondary/30 hover:bg-secondary/25 flex items-center justify-center text-secondary hover:text-white transition-colors">
                    <span class="material-symbols-outlined text-[19px] font-bold">add</span>
                </button>
            @else
                <button type="button"
                        @click="$dispatch('open-quick-sms')"
                        title="إرسال كشف سريع SMS"
                        class="w-8 h-8 rounded-xl bg-secondary/15 border border-secondary/30 hover:bg-secondary/25 flex items-center justify-center text-secondary hover:text-white transition-colors">
                    <span class="material-symbols-outlined text-[19px] font-bold">add</span>
                </button>
            @endif

            <button type="button"
                    @click="(typeof syncData === 'function') ? syncData() : window.location.reload()"
                    title="مزامنة وتحديث البيانات"
                    class="w-8 h-8 rounded-xl bg-white/10 border border-white/15 hover:bg-white/20 flex items-center justify-center text-slate-200 hover:text-white transition-colors">
                <span class="material-symbols-outlined text-[19px]" :class="typeof isSyncing !== 'undefined' && isSyncing ? 'animate-spin text-secondary' : ''">sync</span>
            </button>

            <form method="POST"
                  action="{{ route('office.logout') }}"
                  class="m-0"
                  x-data="{ isLoggingOut: false }"
                  @submit="isLoggingOut = true">
                @csrf
                <button type="submit"
                        :disabled="isLoggingOut"
                        title="تسجيل الخروج"
                        class="w-8 h-8 rounded-xl bg-rose-500/15 border border-rose-500/30 hover:bg-rose-500/25 flex items-center justify-center text-rose-300 transition-colors disabled:opacity-75">
                    <span x-show="!isLoggingOut" class="material-symbols-outlined text-[19px]">logout</span>
                    <span x-show="isLoggingOut" x-cloak class="w-4 h-4 border-2 border-rose-400 border-t-transparent rounded-full animate-spin"></span>
                </button>
            </form>
        </div>
    </div>

    <template x-if="typeof syncQueue !== 'undefined' && syncQueue.length > 0">
        <div class="bg-secondary/20 border border-secondary/40 text-secondary text-[11px] px-3 py-1.5 rounded-lg flex justify-between items-center mt-2.5">
            <span>لديك (<b x-text="syncQueue.length"></b>) تعديلات غير متزامنة مع السيرفر</span>
            <span class="material-symbols-outlined text-sm animate-spin" x-show="isSyncing">sync</span>
        </div>
    </template>

    @hasSection('header-search')
        @yield('header-search')
    @elseif (!empty($showSearch) || request()->routeIs('office.parcels.*') || request()->routeIs('office.favorite_customers.*'))
        <div class="mt-3 relative">
            <input type="text"
                   x-model="search"
                   placeholder="{{ $searchPlaceholder ?? 'بحث برقم الهاتف، السند، نوع الطرد...' }}"
                   class="w-full bg-white/10 text-white placeholder-slate-300/70 text-xs rounded-xl pr-9 pl-4 py-2.5 outline-none border border-white/15 focus:border-secondary transition-all">
            <span class="material-symbols-outlined absolute right-2.5 top-2.5 text-slate-300/70 text-lg">search</span>
        </div>
    @endif
</header>

{{-- =========================================================================
     2. الشريط العلوي الأبيض النحيف لسطح المكتب (lg: >= 1024px فقط — سطر واحد نظيف)
   ========================================================================= --}}
<header class="hidden lg:flex h-16 bg-white dark:bg-[#24303F] border-b border-gray-200 dark:border-slate-700/60 px-6 items-center justify-between gap-4 sticky top-0 z-30 transition-colors print:hidden"
        x-data="{
            headerCount: {{ $desktopInitialCount !== null ? $desktopInitialCount : 'null' }},
            isDark: document.documentElement.classList.contains('dark'),
            toggleTheme() {
                this.isDark = !this.isDark;
                document.documentElement.classList.toggle('dark', this.isDark);
                try {
                    localStorage.setItem('mursal_office_theme', this.isDark ? 'dark' : 'light');
                } catch (e) {}
            }
        }"
        @office-header-count.window="headerCount = $event.detail?.count ?? headerCount">

    {{-- يميناً: عنوان الصفحة الحالي + عداد بسيط --}}
    <div class="flex items-center gap-2.5 min-w-0 shrink-0">
        <h1 class="text-base font-extrabold text-slate-900 dark:text-slate-100 truncate">{{ $desktopPageTitle }}</h1>

        @if (request()->routeIs('office.parcels.*'))
            <span class="px-2.5 py-0.5 rounded-full bg-gray-100 text-slate-600 border border-gray-200/80 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700 text-xs font-mono font-bold"
                  x-show="typeof parcels !== 'undefined'"
                  x-text="typeof parcels !== 'undefined' ? parcels.length : ''"></span>
        @elseif (request()->routeIs('office.favorite_customers.*'))
            <span class="px-2.5 py-0.5 rounded-full bg-gray-100 text-slate-600 border border-gray-200/80 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700 text-xs font-mono font-bold"
                  x-show="typeof customers !== 'undefined'"
                  x-text="typeof customers !== 'undefined' ? customers.length : ''"></span>
        @else
            <span x-show="headerCount !== null"
                  x-cloak
                  class="px-2.5 py-0.5 rounded-full bg-gray-100 text-slate-600 border border-gray-200/80 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700 text-xs font-mono font-bold"
                  x-text="headerCount"></span>
        @endif
    </div>

    {{-- في المنتصف: حقل البحث الموحد الأنيق بأسلوب TailAdmin وتلميح الاختصار / --}}
    <div class="flex-1 flex justify-center px-2">
        @if ($desktopSearchMode === 'alpine-search')
            <div class="relative w-full max-w-md">
                <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-[18px] pointer-events-none">search</span>
                <input type="text"
                       data-desktop-search-input="true"
                       x-model="search"
                       dir="rtl"
                       autocomplete="off"
                       placeholder="{{ $desktopSearchPlaceholder }}"
                       class="w-full h-10 bg-gray-50 hover:bg-gray-100/80 focus:bg-white text-slate-800 placeholder-slate-400 border border-gray-200 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/15 dark:bg-[#1A222C] dark:hover:bg-[#1D2A39] dark:focus:bg-[#1A222C] dark:text-slate-100 dark:placeholder-slate-400 dark:border-slate-700/80 dark:focus:border-amber-400 text-xs font-bold rounded-xl pr-9 pl-12 outline-none transition-all">
                <div class="absolute left-2.5 top-1/2 -translate-y-1/2 flex items-center gap-1">
                    <template x-if="typeof search !== 'undefined' && search.length > 0">
                        <button type="button"
                                @click="search = ''"
                                title="مسح البحث"
                                class="w-5 h-5 rounded-md hover:bg-gray-200 dark:hover:bg-slate-700 text-slate-400 hover:text-rose-500 flex items-center justify-center text-xs font-bold">✕</button>
                    </template>
                    <kbd x-show="typeof search === 'undefined' || search.length === 0"
                         class="px-1.5 py-0.5 rounded-md bg-white border border-gray-200 dark:bg-[#24303F] dark:border-slate-700 text-[10px] font-mono text-slate-400 shadow-2xs pointer-events-none">/</kbd>
                </div>
            </div>
        @elseif ($desktopSearchMode !== null)
            <div class="relative w-full max-w-md"
                 x-data="{
                     dq: @js($desktopInitialQuery),
                     _dt: null,
                     mode: @js($desktopSearchMode),
                     normDigits(str) {
                         const ar = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
                         const fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
                         return String(str || '')
                             .replace(/[٠-٩]/g, d => ar.indexOf(d))
                             .replace(/[۰-۹]/g, d => fa.indexOf(d))
                             .replace(/[\u200B-\u200D\uFEFF\u200E\u200F]/g, '');
                     },
                     onDesktopInput() {
                         this.dq = this.normDigits(this.dq);
                         if (this._dt) clearTimeout(this._dt);
                         if (this.mode === 'sent' || this.mode === 'transit') {
                             this.emitDesktopSearch();
                             return;
                         }
                         this._dt = setTimeout(() => this.emitDesktopSearch(), 300);
                     },
                     emitDesktopSearch() {
                         if (this._dt) clearTimeout(this._dt);
                         const evMap = {
                             outgoing: 'outgoing-search-change',
                             sent: 'sent-search-change',
                             transit: 'transit-search-change',
                             dashboard: 'dashboard-global-search'
                         };
                         const evName = evMap[this.mode];
                         if (evName) {
                             window.dispatchEvent(new CustomEvent(evName, { detail: { q: this.dq } }));
                         }
                     },
                     clearDesktopSearch() {
                         this.dq = '';
                         this.emitDesktopSearch();
                     }
                 }"
                 @dashboard-global-search-clear.window="if (mode === 'dashboard') { dq = ''; }"
                 @transit-search-clear.window="if (mode === 'transit') { dq = ''; }">
                <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-[18px] pointer-events-none">search</span>
                <input type="text"
                       data-desktop-search-input="true"
                       x-model="dq"
                       @input="onDesktopInput()"
                       @keydown.enter.prevent="emitDesktopSearch()"
                       dir="rtl"
                       autocomplete="off"
                       placeholder="{{ $desktopSearchPlaceholder }}"
                       class="w-full h-10 bg-gray-50 hover:bg-gray-100/80 focus:bg-white text-slate-800 placeholder-slate-400 border border-gray-200 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/15 dark:bg-[#1A222C] dark:hover:bg-[#1D2A39] dark:focus:bg-[#1A222C] dark:text-slate-100 dark:placeholder-slate-400 dark:border-slate-700/80 dark:focus:border-amber-400 text-xs font-bold rounded-xl pr-9 pl-12 outline-none transition-all">
                <div class="absolute left-2.5 top-1/2 -translate-y-1/2 flex items-center gap-1">
                    <template x-if="dq.length > 0">
                        <button type="button"
                                @click="clearDesktopSearch()"
                                title="مسح البحث"
                                class="w-5 h-5 rounded-md hover:bg-gray-200 dark:hover:bg-slate-700 text-slate-400 hover:text-rose-500 flex items-center justify-center text-xs font-bold">✕</button>
                    </template>
                    <kbd x-show="dq.length === 0"
                         class="px-1.5 py-0.5 rounded-md bg-white border border-gray-200 dark:bg-[#24303F] dark:border-slate-700 text-[10px] font-mono text-slate-400 shadow-2xs pointer-events-none">/</kbd>
                </div>
            </div>
        @endif
    </div>

    {{-- يساراً: زر الوضع الليلي/النهاري + زر الإجراء الرئيسي + زر تسجيل الخروج --}}
    <div class="flex items-center gap-2.5 shrink-0">
        <button type="button"
                @click="toggleTheme()"
                :title="isDark ? 'التبديل للوضع النهاري' : 'التبديل للوضع الليلي'"
                :aria-label="isDark ? 'التبديل للوضع النهاري' : 'التبديل للوضع الليلي'"
                class="w-10 h-10 rounded-xl bg-gray-100 hover:bg-gray-200/70 text-slate-600 border border-gray-200/80 dark:bg-[#1A222C] dark:hover:bg-[#333A48] dark:text-amber-300 dark:border-slate-700/70 flex items-center justify-center transition-colors shrink-0">
            <span class="material-symbols-outlined text-[20px]"
                  x-text="isDark ? 'light_mode' : 'dark_mode'">dark_mode</span>
        </button>

        @if (request()->routeIs('office.outgoing-parcels.*'))
            <button type="button"
                    data-desktop-primary-add="true"
                    @click="window.dispatchEvent(new CustomEvent('open-outgoing-parcel-modal'))"
                    title="اختصار: Alt + N"
                    class="h-10 px-4 rounded-xl bg-secondary hover:bg-amber-500 active:scale-[0.99] text-primary text-xs font-extrabold shadow-xs flex items-center gap-1.5 transition-all">
                <span class="material-symbols-outlined text-[18px] font-bold">add</span>
                <span>إضافة طرد صادر</span>
            </button>
        @elseif (request()->routeIs('office.shipments.sent.index'))
            <button type="button"
                    data-desktop-primary-add="true"
                    @click="window.dispatchEvent(new CustomEvent('open-create-shipment-modal'))"
                    title="اختصار: Alt + N"
                    class="h-10 px-4 rounded-xl bg-secondary hover:bg-amber-500 active:scale-[0.99] text-primary text-xs font-extrabold shadow-xs flex items-center gap-1.5 transition-all">
                <span class="material-symbols-outlined text-[18px] font-bold">add</span>
                <span>كشف شحنة جديد</span>
            </button>
        @elseif (request()->routeIs('office.shipments.sent.*'))
            <a href="{{ route('office.shipments.sent.index', ['create' => 1]) }}"
               data-desktop-primary-add="true"
               class="h-10 px-4 rounded-xl bg-secondary hover:bg-amber-500 text-primary text-xs font-extrabold shadow-xs flex items-center gap-1.5 transition-all">
                <span class="material-symbols-outlined text-[18px] font-bold">add</span>
                <span>كشف شحنة جديد</span>
            </a>
        @elseif (request()->routeIs('office.favorite_customers.*'))
            <button type="button"
                    data-desktop-primary-add="true"
                    @click="window.dispatchEvent(new CustomEvent('open-vip-customer-modal'))"
                    title="اختصار: Alt + N"
                    class="h-10 px-4 rounded-xl bg-secondary hover:bg-amber-500 active:scale-[0.99] text-primary text-xs font-extrabold shadow-xs flex items-center gap-1.5 transition-all">
                <span class="material-symbols-outlined text-[18px] font-bold">add</span>
                <span>إضافة عميل مميز</span>
            </button>
        @elseif (request()->routeIs('office.external-partner-links.*'))
            <button type="button"
                    data-desktop-primary-add="true"
                    @click="window.dispatchEvent(new CustomEvent('open-partner-link-modal'))"
                    title="اختصار: Alt + N"
                    class="h-10 px-4 rounded-xl bg-secondary hover:bg-amber-500 active:scale-[0.99] text-primary text-xs font-extrabold shadow-xs flex items-center gap-1.5 transition-all">
                <span class="material-symbols-outlined text-[18px] font-bold">add</span>
                <span>إضافة مكتب خارجي</span>
            </button>
        @else
            <button type="button"
                    data-desktop-primary-add="true"
                    @click="$dispatch('open-quick-sms')"
                    title="اختصار: Alt + N"
                    class="h-10 px-4 rounded-xl bg-secondary hover:bg-amber-500 active:scale-[0.99] text-primary text-xs font-extrabold shadow-xs flex items-center gap-1.5 transition-all">
                <span class="material-symbols-outlined text-[18px] font-bold">add</span>
                <span>إرسال سريع SMS</span>
            </button>
        @endif

        <form method="POST"
              action="{{ route('office.logout') }}"
              class="m-0"
              x-data="{ isLoggingOut: false }"
              @submit="isLoggingOut = true">
            @csrf
            <button type="submit"
                    :disabled="isLoggingOut"
                    title="تسجيل الخروج"
                    class="h-10 px-3 rounded-xl bg-gray-100 hover:bg-rose-50 text-slate-600 hover:text-rose-600 border border-gray-200/80 hover:border-rose-200 dark:bg-[#1A222C] dark:hover:bg-rose-500/15 dark:text-slate-300 dark:hover:text-rose-400 dark:border-slate-700/70 dark:hover:border-rose-500/30 text-xs font-bold flex items-center gap-1.5 transition-colors disabled:opacity-60">
                <span x-show="!isLoggingOut" class="material-symbols-outlined text-[18px]">logout</span>
                <span x-show="isLoggingOut" x-cloak class="w-4 h-4 border-2 border-rose-500 border-t-transparent rounded-full animate-spin"></span>
                <span>خروج</span>
            </button>
        </form>
    </div>
</header>
