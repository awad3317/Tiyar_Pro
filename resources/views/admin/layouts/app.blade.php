<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'لوحة تحكم الإدارة المركزية') - مُرسَل</title>

    <link rel="icon" type="image/png" href="{{ asset('assets/icons/icon-192.png') }}">
    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&family=Manrope:wght@400;600;800&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            DEFAULT: '#041627',
                            50: '#f0f4f9',
                            100: '#d9e4f0',
                            200: '#b3cbe1',
                            800: '#061e35',
                            900: '#041627',
                            950: '#020b14',
                        },
                        secondary: {
                            DEFAULT: '#fe9d20',
                            50: '#fffaf0',
                            100: '#ffefd2',
                            400: '#ffb347',
                            500: '#fe9d20',
                            600: '#e58509',
                        },
                    },
                    fontFamily: {
                        sans: ['Almarai', 'sans-serif'],
                        mono: ['Manrope', 'monospace'],
                    },
                },
            },
        };
    </script>

    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Almarai', sans-serif; }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        .custom-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px; }
    </style>

    @stack('styles')
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col font-sans" x-data="{ sidebarOpen: false }">

    <div class="flex-1 flex overflow-hidden">

        <!-- الستار للشاشات الصغيرة (Mobile Overlay) -->
        <div x-show="sidebarOpen"
             x-cloak
             @click="sidebarOpen = false"
             x-transition:enter="transition-opacity ease-linear duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-40 bg-slate-900/60 backdrop-blur-sm lg:hidden"></div>

        <!-- الشريط الجانبي (Sidebar) -->
        <aside :class="sidebarOpen ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'"
               class="fixed inset-y-0 right-0 z-50 w-72 bg-primary-950 text-white flex flex-col transition-transform duration-300 ease-in-out lg:static lg:translate-x-0 shadow-2xl lg:shadow-none border-l border-slate-800/80">
            
            <!-- الشعار وهوية الإدارة -->
            <div class="h-20 flex items-center justify-between px-6 border-b border-white/10 bg-primary-900/60">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 group">
                    <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-secondary to-amber-500 flex items-center justify-center shadow-lg shadow-secondary/20">
                        <img src="{{ asset('assets/icons/icon-192.png') }}" alt="Mursal" class="w-7 h-7 object-contain">
                    </div>
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-xl font-extrabold tracking-tight text-white">مُرسَـل</span>
                            <span class="px-1.5 py-0.5 text-[10px] font-bold bg-secondary/20 text-secondary border border-secondary/30 rounded">Super Admin</span>
                        </div>
                        <p class="text-[11px] text-slate-400 font-medium">لوحة الإدارة المركزية</p>
                    </div>
                </a>

                <!-- زر الإغلاق في الجوال -->
                <button type="button" @click="sidebarOpen = false" class="lg:hidden text-slate-400 hover:text-white p-1 rounded-lg">
                    <span class="material-symbols-outlined text-2xl">close</span>
                </button>
            </div>

            <!-- قائمة التصفح الرئيسية -->
            <nav class="flex-1 px-4 py-6 space-y-1.5 overflow-y-auto custom-scrollbar">
                
                <div class="px-3 pb-2 text-[11px] font-bold tracking-wider text-slate-400 uppercase">الرئيسية</div>

                <!-- لوحة التحكم -->
                <a href="{{ route('admin.dashboard') }}"
                   class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-sm font-bold transition-all duration-200 {{ request()->routeIs('admin.dashboard') ? 'bg-secondary text-primary-950 font-extrabold shadow-lg shadow-secondary/25' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                    <span class="material-symbols-outlined text-[22px]">dashboard</span>
                    <span>لوحة التحكم والإحصائيات</span>
                </a>

                <div class="pt-5 px-3 pb-2 text-[11px] font-bold tracking-wider text-slate-400 uppercase">إدارة العمليات</div>

                <!-- إدارة المكاتب -->
                <a href="{{ route('admin.offices.index') }}"
                   class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-sm font-bold transition-all duration-200 {{ request()->routeIs('admin.offices.*') ? 'bg-secondary text-primary-950 font-extrabold shadow-lg shadow-secondary/25' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                    <span class="material-symbols-outlined text-[22px]">domain</span>
                    <span>إدارة المكاتب</span>
                </a>

                <!-- مراقبة الطرود -->
                <a href="{{ route('admin.parcels.index') }}"
                   class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-sm font-bold transition-all duration-200 {{ request()->routeIs('admin.parcels.*') ? 'bg-secondary text-primary-950 font-extrabold shadow-lg shadow-secondary/25' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                    <span class="material-symbols-outlined text-[22px]">package_2</span>
                    <span>مراقبة الطرود الشاملة</span>
                </a>

                <div class="pt-5 px-3 pb-2 text-[11px] font-bold tracking-wider text-slate-400 uppercase">خدمات الربط والاتصال</div>

                <!-- إدارة الـ Instances -->
                <a href="{{ route('admin.whatsapp_instances.index') }}"
                   class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-sm font-bold transition-all duration-200 {{ request()->routeIs('admin.whatsapp_instances.*') ? 'bg-secondary text-primary-950 font-extrabold shadow-lg shadow-secondary/25' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                    <span class="material-symbols-outlined text-[22px]">hub</span>
                    <span>إدارة سيرفرات الواتساب (Instances)</span>
                </a>

                <!-- انتقال سريع لبوابة المكاتب -->
                <div class="pt-6">
                    <a href="{{ route('office.dashboard') }}" target="_blank"
                       class="flex items-center justify-between px-4 py-3 rounded-xl text-xs font-bold text-slate-400 bg-white/5 hover:bg-white/10 hover:text-slate-200 border border-white/5 transition-all">
                        <span class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-lg">open_in_new</span>
                            <span>معاينة بوابة المكاتب</span>
                        </span>
                        <span class="material-symbols-outlined text-sm">arrow_left</span>
                    </a>
                </div>
            </nav>

            <!-- معلومات المسؤول وتسجيل الخروج -->
            <div class="p-4 border-t border-white/10 bg-primary-900/40">
                <div class="flex items-center justify-between p-2 rounded-xl bg-white/5">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-10 h-10 rounded-full bg-secondary/20 border border-secondary/40 text-secondary flex items-center justify-center font-bold text-sm shrink-0">
                            {{ mb_substr(auth()->user()->name ?? 'A', 0, 1) }}
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-bold text-white truncate">{{ auth()->user()->name ?? 'المدير' }}</p>
                            <p class="text-[11px] text-slate-400 truncate">{{ auth()->user()->email ?? '' }}</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" title="تسجيل الخروج"
                                class="p-2 text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-lg transition-colors">
                            <span class="material-symbols-outlined text-xl">logout</span>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <!-- المحتوى الرئيسي (Main Container) -->
        <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">

            <!-- الشريط العلوي (Top Header) -->
            <header class="h-20 bg-white border-b border-slate-200/80 sticky top-0 z-30 flex items-center justify-between px-4 lg:px-8 shadow-xs">
                
                <div class="flex items-center gap-3">
                    <button type="button" @click="sidebarOpen = true" class="lg:hidden p-2 rounded-lg text-slate-600 hover:bg-slate-100">
                        <span class="material-symbols-outlined text-2xl">menu</span>
                    </button>

                    <div>
                        <h1 class="text-lg lg:text-xl font-extrabold text-slate-900">@yield('page-title', 'لوحة التحكم')</h1>
                        <p class="text-xs text-slate-500 hidden sm:block">@yield('page-subtitle', 'إدارة ومراقبة المنظومة المركزية')</p>
                    </div>
                </div>

                <!-- الإجراءات السريعة في الهيدر -->
                <div class="flex items-center gap-3">
                    @yield('header-actions')

                    <div class="h-7 w-[1px] bg-slate-200 mx-1 hidden sm:block"></div>

                    <!-- توقيت النظام وحالة الجلسة -->
                    <div class="hidden md:flex items-center gap-2 px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200/60 text-xs font-bold">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>النظام متصل</span>
                    </div>
                </div>
            </header>

            <!-- محتوى الصفحات والرسائل التنبيهية -->
            <main class="flex-1 p-4 lg:p-8">

                <!-- التنبيهات الفورية (Flash Messages) -->
                @if (session('success'))
                    <div x-data="{ show: true }" x-show="show" x-transition
                         class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-900 flex items-start gap-3 shadow-sm">
                        <span class="material-symbols-outlined text-emerald-600 text-2xl shrink-0">check_circle</span>
                        <div class="flex-1 text-sm font-bold pt-0.5">{{ session('success') }}</div>
                        <button type="button" @click="show = false" class="text-emerald-500 hover:text-emerald-800">
                            <span class="material-symbols-outlined text-lg">close</span>
                        </button>
                    </div>
                @endif

                @if (session('error'))
                    <div x-data="{ show: true }" x-show="show" x-transition
                         class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-900 flex items-start gap-3 shadow-sm">
                        <span class="material-symbols-outlined text-rose-600 text-2xl shrink-0">error</span>
                        <div class="flex-1 text-sm font-bold pt-0.5">{{ session('error') }}</div>
                        <button type="button" @click="show = false" class="text-rose-500 hover:text-rose-800">
                            <span class="material-symbols-outlined text-lg">close</span>
                        </button>
                    </div>
                @endif

                @if (session('info'))
                    <div x-data="{ show: true }" x-show="show" x-transition
                         class="mb-6 p-4 rounded-2xl bg-sky-50 border border-sky-200 text-sky-900 flex items-start gap-3 shadow-sm">
                        <span class="material-symbols-outlined text-sky-600 text-2xl shrink-0">info</span>
                        <div class="flex-1 text-sm font-bold pt-0.5">{{ session('info') }}</div>
                        <button type="button" @click="show = false" class="text-sky-500 hover:text-sky-800">
                            <span class="material-symbols-outlined text-lg">close</span>
                        </button>
                    </div>
                @endif

                @if (isset($errors) && $errors->any())
                    <div x-data="{ show: true }" x-show="show" x-transition
                         class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-900 shadow-sm">
                        <div class="flex items-center gap-2 font-extrabold text-sm mb-2 text-rose-700">
                            <span class="material-symbols-outlined text-xl">warning</span>
                            <span>يرجى تصحيح الأخطاء التالية:</span>
                        </div>
                        <ul class="list-disc list-inside text-xs font-semibold space-y-1 text-rose-800 pr-2">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>

            <!-- التذييل (Footer) -->
            <footer class="py-4 px-6 border-t border-slate-200 text-center text-xs text-slate-400 bg-white">
                منظومة مُرسَل لإدارة الشحنات والطرود &copy; {{ date('Y') }} - لوحة التحكم المركزية (Super Admin)
            </footer>
        </div>
    </div>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @stack('scripts')
</body>
</html>
