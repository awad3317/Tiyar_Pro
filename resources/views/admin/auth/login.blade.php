<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>تسجيل دخول الإدارة المركزية - مُرسَل</title>

    <link rel="icon" type="image/png" href="{{ asset('assets/icons/icon-192.png') }}">
    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&family=Manrope:wght@400;600;800&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#041627',
                        secondary: '#fe9d20',
                    },
                    fontFamily: {
                        sans: ['Almarai', 'sans-serif'],
                        headline: ['Manrope', 'Almarai', 'sans-serif'],
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
        .kinetic-gradient {
            background: linear-gradient(135deg, #020b14 0%, #041627 50%, #092844 100%);
        }
    </style>
</head>
<body class="bg-slate-900 min-h-screen flex items-center justify-center p-4 relative overflow-hidden">

    <!-- خلفية مضيئة ناعمة -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none opacity-20">
        <div class="absolute top-[-10%] right-[-10%] w-[500px] h-[500px] rounded-full bg-secondary blur-[140px]"></div>
        <div class="absolute bottom-[-10%] left-[-10%] w-[600px] h-[600px] rounded-full bg-sky-600 blur-[160px]"></div>
    </div>

    <div class="relative z-10 w-full max-w-md" x-data="{ showPassword: false, isSubmitting: false }">
        
        <!-- بطاقة تسجيل الدخول -->
        <div class="bg-slate-950/80 backdrop-blur-xl border border-slate-800 rounded-3xl p-8 sm:p-10 shadow-2xl shadow-black/80">
            
            <!-- الهوية والشعار -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-secondary to-amber-500 shadow-xl shadow-secondary/20 mb-4">
                    <img src="{{ asset('assets/icons/icon-192.png') }}" alt="Mursal" class="w-10 h-10 object-contain">
                </div>
                <div class="flex items-center justify-center gap-2 mb-1">
                    <h1 class="text-2xl font-black text-white">مُرسَـل</h1>
                    <span class="px-2 py-0.5 text-[11px] font-extrabold bg-secondary text-primary rounded-md">SUPER ADMIN</span>
                </div>
                <p class="text-xs text-slate-400 font-medium">بوابة الإدارة المركزية والتحكم العام بالنظام</p>
            </div>

            <!-- رسائل التنبيه والخطأ -->
            @if (session('info'))
                <div class="mb-5 p-3.5 rounded-xl bg-sky-500/10 border border-sky-500/30 text-sky-300 text-xs font-bold text-center">
                    {{ session('info') }}
                </div>
            @endif

            @if (isset($errors) && $errors->any())
                <div class="mb-5 p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs font-bold flex items-center gap-2">
                    <span class="material-symbols-outlined text-lg">error</span>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login.submit') }}" @submit="isSubmitting = true" class="space-y-5">
                @csrf

                <!-- البريد الإلكتروني -->
                <div class="space-y-2">
                    <label for="email" class="block text-xs font-bold text-slate-300">البريد الإلكتروني للمسؤول</label>
                    <div class="relative">
                        <input id="email" type="email" name="email" value="{{ old('email', 'admin@mursal.pro') }}" required autofocus
                               class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-3.5 pl-11 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-secondary focus:ring-1 focus:ring-secondary transition-all font-sans"
                               placeholder="admin@mursal.pro" dir="ltr">
                        <div class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                            <span class="material-symbols-outlined text-xl">mail</span>
                        </div>
                    </div>
                </div>

                <!-- كلمة المرور -->
                <div class="space-y-2">
                    <label for="password" class="block text-xs font-bold text-slate-300">كلمة المرور</label>
                    <div class="relative">
                        <input id="password" :type="showPassword ? 'text' : 'password'" name="password" required
                               class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-3.5 pl-11 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-secondary focus:ring-1 focus:ring-secondary transition-all font-sans"
                               placeholder="••••••••" dir="ltr">
                        <button type="button" @click="showPassword = !showPassword"
                                class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white transition-colors focus:outline-none">
                            <span class="material-symbols-outlined text-xl" x-text="showPassword ? 'visibility_off' : 'visibility'"></span>
                        </button>
                    </div>
                </div>

                <!-- تذكرني -->
                <div class="flex items-center justify-between text-xs pt-1">
                    <label class="flex items-center gap-2 cursor-pointer text-slate-400 hover:text-slate-200">
                        <input type="checkbox" name="remember" value="1" checked
                               class="w-4 h-4 rounded border-slate-700 bg-slate-900 text-secondary focus:ring-secondary focus:ring-offset-slate-900">
                        <span>تذكر جلسة تسجيل الدخول</span>
                    </label>
                </div>

                <!-- زر تسجيل الدخول -->
                <div class="pt-2">
                    <button type="submit" :disabled="isSubmitting"
                            class="w-full py-4 px-6 rounded-xl bg-gradient-to-r from-secondary to-amber-500 text-slate-950 font-extrabold text-sm shadow-lg shadow-secondary/30 hover:shadow-secondary/50 hover:brightness-105 active:scale-[0.98] transition-all flex items-center justify-center gap-2 disabled:opacity-75 disabled:pointer-events-none">
                        <span x-show="!isSubmitting" class="flex items-center gap-2">
                            <span>تسجيل الدخول للنظام</span>
                            <span class="material-symbols-outlined text-lg">arrow_back</span>
                        </span>
                        <span x-show="isSubmitting" x-cloak class="flex items-center gap-2">
                            <span class="w-4 h-4 border-2 border-slate-950 border-t-transparent rounded-full animate-spin"></span>
                            <span>جاري التحقق والاتصال...</span>
                        </span>
                    </button>
                </div>
            </form>

            <!-- التبديل إلى بوابة المكاتب -->
            <div class="mt-8 pt-6 border-t border-slate-800/80 text-center">
                <a href="{{ route('office.login') }}" class="text-xs text-slate-400 hover:text-secondary inline-flex items-center gap-1.5 transition-colors">
                    <span class="material-symbols-outlined text-base">storefront</span>
                    <span>هل أنت مدير فرع/مكتب؟ انتقل لتسجيل دخول المكاتب</span>
                </a>
            </div>
        </div>

        <p class="text-center text-[11px] text-slate-500 mt-6">
            منظومة مُرسَل &copy; {{ date('Y') }} - حماية مشفرة ومصادقة معزولة
        </p>
    </div>

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
