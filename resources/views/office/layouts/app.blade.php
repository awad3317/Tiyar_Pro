@php
    $brandOffice = auth('office')->user();
    $officeProfile = $brandOffice
        ? $brandOffice->getResolvedProfile()
        : [
            'primary_color'   => \App\Models\OfficeProfile::DEFAULT_PRIMARY_COLOR,
            'secondary_color' => \App\Models\OfficeProfile::DEFAULT_SECONDARY_COLOR,
            'logo_url'        => null,
            'icon_url'        => null,
        ];
    $brandPrimary   = $officeProfile['primary_color'] ?? '#041627';
    $brandSecondary = $officeProfile['secondary_color'] ?? '#fe9d20';
    $brandIconUrl   = $officeProfile['icon_url'] ?? $officeProfile['logo_url'] ?? asset('icons/icon-192.png');

    if (! isset($pendingReceivedShipmentsCount) && $brandOffice && class_exists(\App\Services\ShipmentService::class)) {
        try {
            $pendingReceivedShipmentsCount = \App\Services\ShipmentService::countPendingReceivedShipments($brandOffice);
        } catch (\Throwable $e) {
            $pendingReceivedShipmentsCount = null;
        }
    }
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'بوابة المكاتب') - {{ $brandOffice?->name ?? 'مُرسَل' }}</title>

    <meta name="theme-color" content="{{ $brandPrimary }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="icon" type="image/png" href="{{ $brandIconUrl }}">
    <link rel="apple-touch-icon" href="{{ $brandIconUrl }}">
    <link rel="preload" as="image" href="{{ asset('icons/icon-192.png') }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">

    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@400;700;800&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=swap" rel="stylesheet" crossorigin="anonymous">
    <script>
        (function () {
            try {
                if (localStorage.getItem('mursal_office_theme') === 'dark') {
                    document.documentElement.classList.add('dark');
                }
                if (localStorage.getItem('mursal_sidebar_collapsed') === '1') {
                    document.documentElement.classList.add('mursal-sidebar-collapsed');
                }
                var path = window.location.pathname || '';
                var viewKey = null;
                if (path.indexOf('/office/outgoing-parcels') === 0) viewKey = 'mursal_outgoing_desktop_view';
                else if (path.indexOf('/office/transit-parcels') === 0) viewKey = 'mursal_transit_desktop_view';
                else if (path.indexOf('/office/parcels') === 0) viewKey = 'mursal_incoming_desktop_view';
                else if (path.indexOf('/office/shipments/sent') === 0) viewKey = 'mursal_sent_shipments_desktop_view';
                else if (path.indexOf('/office/shipments/received') === 0) viewKey = 'mursal_received_shipments_desktop_view';
                else if (path.indexOf('/office/favorite-customers') === 0) viewKey = 'mursal_vip_desktop_view';
                else if (path.indexOf('/office/external-partner-links') === 0) viewKey = 'mursal_partner_links_desktop_view';

                var desktopView = (viewKey && localStorage.getItem(viewKey) === 'cards') ? 'cards' : 'table';
                document.documentElement.setAttribute('data-desktop-view', desktopView);
            } catch (e) {
                try {
                    document.documentElement.setAttribute('data-desktop-view', 'table');
                } catch (_) {}
            }
        })();
    </script>
    <script src="{{ asset('js/tailwind.js') }}"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: @json($brandPrimary),
                        secondary: @json($brandSecondary),
                    },
                },
            },
        };
    </script>
    <style>
        @view-transition {
            navigation: auto;
        }
        @keyframes mursalMainEnter {
            from { opacity: 0.92; }
            to { opacity: 1; }
        }
        @media screen and (max-width: 1023.98px) {
            ::view-transition-group(*),
            ::view-transition-old(*),
            ::view-transition-new(*) {
                animation-duration: 0s !important;
                animation: none !important;
            }
        }
        @media screen and (min-width: 1024px) {
            html.dark {
                color-scheme: dark;
            }
            /* تثبيت القائمة الجانبية والشريط العلوي وإلغاء أي تداخل شفاف أثناء الانتقال */
            .mursal-desktop-sidebar {
                width: 270px;
                view-transition-name: desktop-sidebar;
            }
            .mursal-desktop-header {
                view-transition-name: desktop-header;
            }
            .mursal-desktop-workspace main {
                view-transition-name: desktop-main;
            }
            ::view-transition-group(desktop-sidebar),
            ::view-transition-group(desktop-header) {
                animation-duration: 0s;
                z-index: 50;
            }
            ::view-transition-old(desktop-sidebar),
            ::view-transition-new(desktop-sidebar),
            ::view-transition-old(desktop-header),
            ::view-transition-new(desktop-header) {
                animation: none;
                mix-blend-mode: normal;
            }
            ::view-transition-old(root),
            ::view-transition-old(desktop-main) {
                display: none;
                animation: none;
                opacity: 0;
            }
            ::view-transition-new(root) {
                animation: none;
                mix-blend-mode: normal;
            }
            ::view-transition-new(desktop-main) {
                animation: mursalMainEnter 100ms ease-out both;
                mix-blend-mode: normal;
            }
            html.mursal-sidebar-collapsed .mursal-desktop-sidebar {
                width: 80px;
            }
            /* إخفاء شاشة التحميل السوداء على الكمبيوتر واستبدالها بشريط التقدم العلوي */
            #mursal-nav-loader {
                display: none !important;
            }
            #mursal-desktop-top-progress {
                view-transition-name: none !important;
            }
            /* إلغاء وميض (البطاقات <- الجدول) على الكمبيوتر قبل تهيئة Alpine */
            html[data-desktop-view="table"] .mursal-desktop-cards {
                display: none !important;
            }
            html[data-desktop-view="cards"] .mursal-desktop-cards {
                display: grid !important;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 1rem;
                align-content: start;
            }
            html[data-desktop-view="table"] .mursal-desktop-table,
            html[data-desktop-view="table"] .mursal-desktop-table[x-cloak] {
                display: block !important;
            }
            html[data-desktop-view="cards"] .mursal-desktop-table {
                display: none !important;
            }
            html.mursal-sidebar-collapsed .mursal-desktop-sidebar .sb-expanded-only {
                display: none !important;
            }
            html.mursal-sidebar-collapsed .mursal-desktop-sidebar .sb-collapsed-only {
                display: block !important;
            }
            html.mursal-sidebar-collapsed .mursal-desktop-sidebar .sb-header-row {
                flex-direction: column;
                gap: 0.625rem;
            }
            html.mursal-sidebar-collapsed .mursal-desktop-sidebar .sb-brand-wrap {
                justify-content: center;
                flex: 0 0 auto;
            }
            html.mursal-sidebar-collapsed .mursal-desktop-sidebar .sb-nav-link {
                justify-content: center;
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }
            html.mursal-sidebar-collapsed .mursal-desktop-sidebar .sb-nav-link .sb-badge {
                position: absolute;
                top: 4px;
                left: 8px;
                min-width: 18px;
                height: 18px;
                font-size: 9px;
                padding: 0 4px;
            }
            html.mursal-sidebar-collapsed .mursal-desktop-sidebar .sb-status-row {
                flex-direction: column;
                gap: 0.5rem;
            }
            html.mursal-sidebar-collapsed .mursal-desktop-sidebar .sb-footer {
                padding: 0.75rem 0.5rem;
            }
            html.dark span.bg-emerald-50:not([class*="dark:bg-"]),
            html.dark span.bg-emerald-100:not([class*="dark:bg-"]) {
                background-color: rgba(16, 185, 129, 0.15) !important;
                color: #34d399 !important;
                border-color: rgba(16, 185, 129, 0.3) !important;
            }
            html.dark span.bg-amber-50:not([class*="dark:bg-"]),
            html.dark span.bg-amber-100:not([class*="dark:bg-"]) {
                background-color: rgba(245, 158, 11, 0.15) !important;
                color: #fbbf24 !important;
                border-color: rgba(245, 158, 11, 0.3) !important;
            }
            html.dark span.bg-sky-50:not([class*="dark:bg-"]),
            html.dark span.bg-sky-100:not([class*="dark:bg-"]) {
                background-color: rgba(14, 165, 233, 0.15) !important;
                color: #38bdf8 !important;
                border-color: rgba(14, 165, 233, 0.3) !important;
            }
            html.dark span.bg-rose-50:not([class*="dark:bg-"]),
            html.dark span.bg-rose-100:not([class*="dark:bg-"]),
            html.dark span.bg-red-50:not([class*="dark:bg-"]),
            html.dark span.bg-red-100:not([class*="dark:bg-"]) {
                background-color: rgba(244, 63, 94, 0.15) !important;
                color: #fb7185 !important;
                border-color: rgba(244, 63, 94, 0.3) !important;
            }
            html.dark span.bg-indigo-50:not([class*="dark:bg-"]),
            html.dark span.bg-indigo-100:not([class*="dark:bg-"]),
            html.dark span.bg-blue-50:not([class*="dark:bg-"]),
            html.dark span.bg-blue-100:not([class*="dark:bg-"]) {
                background-color: rgba(99, 102, 241, 0.15) !important;
                color: #818cf8 !important;
                border-color: rgba(99, 102, 241, 0.3) !important;
            }
            html.dark span.bg-slate-100:not([class*="dark:bg-"]),
            html.dark span.bg-slate-50:not([class*="dark:bg-"]),
            html.dark span.bg-gray-100:not([class*="dark:bg-"]) {
                background-color: rgba(148, 163, 184, 0.15) !important;
                color: #cbd5e1 !important;
                border-color: rgba(148, 163, 184, 0.25) !important;
            }
        }
        @media screen and (min-width: 1536px) {
            html[data-desktop-view="cards"] .mursal-desktop-cards {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }
        @media print {
            html,
            html.dark,
            body {
                background-color: #ffffff !important;
                color: #0f172a !important;
                color-scheme: light !important;
            }
            html.dark .print\:bg-white {
                background-color: #ffffff !important;
            }
        }
    </style>
    <script src="{{ asset('js/mursal-core.js') }}?v=22"></script>
    <script src="{{ asset('js/shipment-manifest-print.js') }}?v=23"></script>
    <script src="{{ asset('js/receipt-image-share.js') }}?v=27"></script>
    <script src="{{ asset('js/desktop-pwa.js') }}?v=31"></script>

    <style>
        :root {
            --office-primary: {{ $brandPrimary }};
            --office-secondary: {{ $brandSecondary }};
        }
        [x-cloak] { display: none !important; }
        body { font-family: 'Almarai', sans-serif; -webkit-tap-highlight-color: transparent; }
        .dir-ltr { direction: ltr; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>

    @stack('styles')
</head>
@php
    $flashToast = null;
    if (session()->has('success')) {
        $flashToast = ['message' => (string) session('success'), 'type' => 'success'];
    } elseif (session()->has('error')) {
        $flashToast = ['message' => (string) session('error'), 'type' => 'error'];
    } elseif (session()->has('warning')) {
        $flashToast = ['message' => (string) session('warning'), 'type' => 'warning'];
    } elseif (session()->has('info')) {
        $flashToast = ['message' => (string) session('info'), 'type' => 'info'];
    } elseif (session()->has('status')) {
        $flashToast = ['message' => (string) session('status'), 'type' => 'success'];
    }
@endphp
<body class="bg-slate-100 lg:bg-[#F1F5F9] lg:dark:bg-[#1A222C] text-slate-900 lg:text-slate-800 lg:dark:text-slate-100 pb-20 lg:pb-0 select-none print:!bg-white print:!text-slate-900 print:pb-0">

    <div x-data="@yield('alpine', 'officeShell()')"
         @office-toast.window="showFeedback($event.detail?.message, $event.detail?.type ?? ($event.detail?.isError ? 'error' : 'success'))"
         @if ($flashToast)
             x-init="$nextTick(() => showFeedback(@js($flashToast['message']), @js($flashToast['type'])))"
         @endif
         class="max-w-md mx-auto min-h-screen flex flex-col shadow-lg lg:max-w-none lg:w-full lg:mx-0 lg:flex-row lg:shadow-none print:max-w-none print:w-full print:min-h-0 print:!bg-white print:shadow-none @yield('shell-class', 'bg-white') lg:bg-[#F1F5F9] lg:dark:bg-[#1A222C]">

        @include('office.layouts.desktop-sidebar')

        <div class="mursal-desktop-workspace flex-1 min-w-0 flex flex-col min-h-screen lg:bg-[#F1F5F9] lg:dark:bg-[#1A222C] print:!bg-white">
            @section('header')
                @include('office.layouts.header')
            @show

            @yield('content')
        </div>

        @include('office.layouts.bottom-nav')

        @include('office.parcels.partials.quick-sms-modal')

        @include('office.layouts.partials.auth-expired-modal')

        @include('office.layouts.partials.nav-loader')

        <!-- الإشعار العائم الموحّد لجميع شاشات المكتب (يختفي تلقائياً خلال 3.5 ثانية) -->
        <div x-show="smsFeedback && smsFeedback.show"
             x-cloak
             role="status"
             aria-live="polite"
             x-transition:enter="ease-out duration-250"
             x-transition:enter-start="opacity-0 -translate-y-4"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-4"
             class="fixed top-4 left-4 right-4 max-w-sm mx-auto lg:left-6 lg:right-auto lg:mx-0 z-[80] p-3.5 rounded-2xl shadow-xl flex items-center gap-2.5 text-xs font-bold transition-all border print:hidden"
             :class="{
                'bg-rose-600 text-white border-rose-500 shadow-rose-600/30': smsFeedback && (smsFeedback.type === 'error' || smsFeedback.isError),
                'bg-amber-500 text-white border-amber-400 shadow-amber-500/30': smsFeedback && smsFeedback.type === 'warning',
                'bg-sky-600 text-white border-sky-500 shadow-sky-600/30': smsFeedback && smsFeedback.type === 'info',
                'bg-emerald-600 text-white border-emerald-500 shadow-emerald-600/30': !smsFeedback || (!smsFeedback.isError && (!smsFeedback.type || smsFeedback.type === 'success'))
             }">
            <span class="material-symbols-outlined text-lg shrink-0"
                  x-text="smsFeedback && (smsFeedback.type === 'error' || smsFeedback.isError)
                    ? 'error'
                    : (smsFeedback && smsFeedback.type === 'warning'
                        ? 'warning'
                        : (smsFeedback && smsFeedback.type === 'info' ? 'info' : 'check_circle'))"></span>
            <span class="flex-1 leading-snug" x-text="smsFeedback ? smsFeedback.message : ''"></span>
            <button type="button"
                    @click="typeof dismissFeedback === 'function' ? dismissFeedback() : (smsFeedback.show = false)"
                    title="إغلاق"
                    class="text-white/80 hover:text-white p-1 shrink-0">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>
    </div>

    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js', { scope: '/office/' });
        }
    </script>

    {{-- التحميل المسبق الذكي لروابط القائمة الجانبية على متصفحات سطح المكتب (Chrome / Edge) لانتقال لحظي --}}
    <script type="speculationrules">
    {
        "prefetch": [
            {
                "where": { "selector_matches": ".mursal-desktop-sidebar a[href]" },
                "eagerness": "moderate"
            }
        ]
    }
    </script>

    @stack('scripts')

    <!-- Alpine.js في الأخير لضمان جاهزية المكونات -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>