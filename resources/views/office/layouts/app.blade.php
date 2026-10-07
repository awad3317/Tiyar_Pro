<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>@yield('title', 'بوابة المكاتب')</title>
    
    <!-- إعدادات PWA وشريط الحالة -->
    <meta name="theme-color" content="#0f172a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="{{ asset('manifest.json') }}">

    <!-- Tailwind CSS للتنسيق السريع والنظيف -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Cairo', sans-serif; -webkit-tap-highlight-color: transparent; }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen flex flex-col justify-between selection:bg-indigo-500 selection:text-white">
    
    <main class="flex-grow flex items-center justify-center p-4">
        @yield('content')
    </main>

    <footer class="text-center py-4 text-xs text-slate-500">
        نظام إدارة وإشعار الطرود &copy; {{ date('Y') }}
    </footer>

    @stack('scripts')
</body>
</html>