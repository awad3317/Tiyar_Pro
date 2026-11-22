<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'بوابة المكاتب') - مُرسَل</title>

    <meta name="theme-color" content="#041627">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="{{ asset('manifest.json') }}">

    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@400;700;800&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=swap" rel="stylesheet">
    <script src="{{ asset('js/tailwind.js') }}"></script>
    <script src="{{ asset('js/mursal-core.js') }}?v=21"></script>
    <script src="{{ asset('js/shipment-manifest-print.js') }}?v=21"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#041627',
                        secondary: '#fe9d20',
                    },
                },
            },
        };
    </script>

    <style>
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
<body class="bg-slate-100 text-slate-900 pb-20 select-none print:bg-white print:pb-0">

    <div x-data="@yield('alpine', 'officeShell()')"
         @office-toast.window="showFeedback($event.detail?.message, $event.detail?.type ?? ($event.detail?.isError ? 'error' : 'success'))"
         @if ($flashToast)
             x-init="$nextTick(() => showFeedback(@js($flashToast['message']), @js($flashToast['type'])))"
         @endif
         class="max-w-md mx-auto min-h-screen flex flex-col shadow-lg print:max-w-none print:w-full print:min-h-0 print:bg-white print:shadow-none @yield('shell-class', 'bg-white')">

        @section('header')
            @include('office.layouts.header')
        @show

        @yield('content')

        @include('office.layouts.bottom-nav')

        @include('office.parcels.partials.quick-sms-modal')

        @include('office.layouts.partials.auth-expired-modal')

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
             class="fixed top-4 left-4 right-4 max-w-sm mx-auto z-[60] p-3.5 rounded-2xl shadow-xl flex items-center gap-2.5 text-xs font-bold transition-all border print:hidden"
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

    @stack('scripts')

    <!-- Alpine.js في الأخير لضمان جاهزية المكونات -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>