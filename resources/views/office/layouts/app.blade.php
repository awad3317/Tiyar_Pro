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
    <script src="{{ asset('js/mursal-core.js') }}?v=6"></script>

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
    </style>

    @stack('styles')
</head>
<body class="bg-slate-100 text-slate-900 pb-20 select-none">

    <div x-data="@yield('alpine', 'officeShell()')"
         class="max-w-md mx-auto min-h-screen flex flex-col shadow-lg @yield('shell-class', 'bg-white')">

        @section('header')
            @include('office.layouts.header')
        @show

        @yield('content')

        @include('office.layouts.bottom-nav')

        @include('office.parcels.partials.quick-sms-modal')

        @include('office.layouts.partials.auth-expired-modal')

        <!-- إشعار حالة إرسال الـ SMS العام -->
        <div x-show="smsFeedback && smsFeedback.show"
             x-cloak
             x-transition:enter="ease-out duration-250"
             x-transition:enter-start="opacity-0 -translate-y-4"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-4"
             class="fixed top-4 left-4 right-4 max-w-sm mx-auto z-50 p-3.5 rounded-2xl shadow-xl flex items-center gap-2.5 text-xs font-bold transition-all border"
             :class="smsFeedback && smsFeedback.isError 
                ? 'bg-rose-600 text-white border-rose-500 shadow-rose-600/30' 
                : 'bg-emerald-600 text-white border-emerald-500 shadow-emerald-600/30'">
            <span class="material-symbols-outlined text-lg" x-text="smsFeedback && smsFeedback.isError ? 'error' : 'check_circle'"></span>
            <span class="flex-1 leading-snug" x-text="smsFeedback ? smsFeedback.message : ''"></span>
            <button type="button" @click="smsFeedback.show = false" class="text-white/80 hover:text-white p-1">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>
    </div>

    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js');
        }
    </script>

    @stack('scripts')

    <!-- Alpine.js في الأخير لضمان جاهزية المكونات -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>