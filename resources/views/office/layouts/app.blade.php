<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'بوابة المكاتب') - مُرسَل</title>

    <!-- إعدادات PWA وشريط الحالة -->
    <meta name="theme-color" content="#041627">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="{{ asset('manifest.json') }}">

    <!-- ملاحظة: روابط الخطوط والمكتبات مطابقة لما يخزّنه sw.js لضمان العمل بدون اتصال -->
    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@400;700;800&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>

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
    </div>

    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js');
        }

        /**
         * الحالة الأساسية المشتركة لكل صفحات المكتب (حالة الاتصال + زر المزامنة في الهيدر).
         * يمكن لأي صفحة توسيعها عبر: return { ...officeShell(), ... }
         */
        function officeShell() {
            return {
                isOnline: navigator.onLine,

                init() {
                    this.watchConnection();
                },

                watchConnection(onReconnect = () => {}) {
                    window.addEventListener('online', () => {
                        this.isOnline = true;
                        onReconnect();
                    });
                    window.addEventListener('offline', () => {
                        this.isOnline = false;
                    });
                },

                syncData() {
                    window.location.reload();
                },
            };
        }
    </script>

    @stack('scripts')
</body>
</html>