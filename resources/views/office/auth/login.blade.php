<!DOCTYPE html>
<html dir="rtl" lang="ar">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" name="viewport" />
    <title>تسجيل دخول المكتب - مُرسَل</title>

    <!-- إعدادات تطبيق الويب PWA -->
    <meta name="theme-color" content="#041627">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="{{ asset('manifest.json') }}">

    <!-- الخطوط والأيقونات -->
    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&family=Manrope:wght@400;600;800&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />

    <!-- مكتبات Tailwind و AlpineJS -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        "primary": "#041627",
                        "primary-container": "#1a2b3c",
                        "on-primary-container": "#8192a7",
                        "secondary-container": "#fe9d20",
                        "on-secondary-container": "#673b00",
                        "secondary-fixed-dim": "#ffb86e",
                        "surface": "#f7f9ff",
                        "surface-container-low": "#eef4ff",
                        "surface-container-lowest": "#ffffff",
                        "on-background": "#0b1d2d",
                        "outline": "#74777d",
                        "error": "#ba1a1a",
                    },
                    fontFamily: {
                        "headline": ["Manrope", "Almarai", "sans-serif"],
                        "body": ["Almarai", "sans-serif"],
                    },
                },
            },
        }
    </script>

    <style>
        [x-cloak] { display: none !important; }
        body {
            font-family: 'Almarai', sans-serif;
            background-color: #f7f9ff;
            -webkit-tap-highlight-color: transparent;
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        .kinetic-gradient {
            background: linear-gradient(135deg, #041627 0%, #1a2b3c 100%);
        }
        .dir-ltr { direction: ltr; }
    </style>
</head>

<body class="flex flex-col justify-center items-center min-h-screen overflow-x-hidden selection:bg-secondary-container selection:text-white">

    <!-- دوائر التوهج الخلفية -->
    <div class="fixed inset-0 z-0 pointer-events-none opacity-20 overflow-hidden">
        <div class="absolute top-[-10%] right-[-10%] w-[60%] h-[60%] rounded-full bg-secondary-container blur-[130px]"></div>
        <div class="absolute bottom-[-10%] left-[-10%] w-[50%] h-[50%] rounded-full bg-primary blur-[130px]"></div>
    </div>

    <!-- البطاقة الرئيسية بتنسيق متجاوب (للجوال وشاشات الحاسوب) -->
    <main class="relative z-10 w-full max-w-5xl flex flex-col md:flex-row-reverse items-stretch min-h-[620px] m-4 md:m-8 overflow-hidden rounded-2xl shadow-[0_20px_60px_rgba(11,29,45,0.08)] bg-white">

        <!-- القسم الجانبي الترويجي (يظهر في الشاشات الكبيرة) -->
        <section class="hidden md:flex md:w-5/12 overflow-hidden relative flex-col justify-between items-start p-10 text-white kinetic-gradient">
            <div class="relative z-20 space-y-5">
                <div class="flex gap-3 items-center">
                    <div class="flex justify-center items-center w-12 h-12 rounded-xl shadow-lg bg-secondary-container text-white">
                        <span class="material-symbols-outlined text-2xl text-on-secondary-container">inventory_2</span>
                    </div>
                    <span class="text-2xl font-black tracking-tight font-headline">مُرسَل</span>
                </div>
                <h1 class="text-4xl font-extrabold leading-[1.2] font-headline">
                    بوابة المكاتب <br><span class="text-secondary-fixed-dim">وإدارة الطرود</span> الذكية.
                </h1>
                <p class="text-sm leading-relaxed text-on-primary-container/90">
                    أدر شحنات مكتبك، وتابع حركة التسليم، وأشعر عملائك عبر الرسائل القصيرة فور وصول طرودهم.
                </p>
            </div>

            <div class="relative z-20 mt-auto pt-6 border-t border-slate-700/50 w-full">
                <div class="flex gap-2 items-center text-xs font-medium text-on-primary-container/80">
                    <span class="w-6 h-[2px] bg-secondary-container"></span>
                    <span>النظام المعتمد لمكاتب الشحن البري</span>
                </div>
            </div>

            <!-- تأثير زخرفي -->
            <div class="absolute -bottom-10 -left-10 w-56 h-56 rounded-full bg-secondary-container/10 blur-3xl pointer-events-none"></div>
        </section>

        <!-- قسم فورم تسجيل الدخول -->
        <section class="flex flex-col flex-1 justify-center p-6 sm:p-12 md:p-14 bg-white">
            <div class="mx-auto w-full max-w-sm sm:max-w-md">

                <!-- شعار للجوال -->
                <div class="flex justify-center mb-6 md:hidden">
                    <div class="flex gap-2.5 items-center">
                        <div class="flex justify-center items-center w-11 h-11 rounded-xl shadow bg-primary text-secondary-fixed-dim">
                            <span class="material-symbols-outlined text-2xl">inventory_2</span>
                        </div>
                        <span class="text-2xl font-black font-headline text-primary">مُرسَل</span>
                    </div>
                </div>

                <div class="mb-8 text-right">
                    <h2 class="text-2xl sm:text-3xl font-bold text-on-background font-headline">تسجيل دخول المكتب</h2>
                    <p class="text-sm text-slate-500 mt-1">أدخل بيانات الاعتماد للوصول لكشوفات الطرود</p>
                </div>

                <!-- رسالة الخطأ العامة إن وجدت -->
                @error('phone')
                    <div class="mb-5 p-3.5 bg-red-50 border-r-4 border-error rounded-lg flex items-center gap-2.5 text-error text-xs font-semibold">
                        <span class="material-symbols-outlined text-lg">error</span>
                        <span>{{ $message }}</span>
                    </div>
                @enderror

                <form method="POST" action="{{ route('office.login.submit') }}" class="space-y-5">
                    @csrf

                    <!-- حقل رقم الجوال -->
                    <div class="space-y-1.5">
                        <label for="phone" class="block pr-1 text-xs font-bold text-on-background/80">رقم الهاتف المسجل</label>
                        <div class="relative flex items-center rounded-xl bg-surface-container-low transition-all group focus-within:ring-2 focus-within:ring-secondary-container focus-within:bg-white border border-transparent focus-within:border-transparent">
                            <input 
                                id="phone" 
                                type="tel" 
                                name="phone" 
                                value="{{ old('phone') }}" 
                                required 
                                autofocus
                                inputmode="numeric"
                                class="flex-1 w-full px-4 py-3.5 pr-11 text-left bg-transparent border-0 text-on-background placeholder:text-outline/60 focus:ring-0 text-sm font-headline dir-ltr"
                                placeholder="7XXXXXXXX أو 967..." 
                            />
                            <div class="absolute right-3.5 top-1/2 -translate-y-1/2 pointer-events-none text-outline group-focus-within:text-secondary-container transition-colors">
                                <span class="material-symbols-outlined text-[20px]">call</span>
                            </div>
                        </div>
                    </div>

                    <!-- حقل كلمة المرور مع إمكانية الإظهار والإخفاء -->
                    <div class="space-y-1.5" x-data="{ showPassword: false }">
                        <label for="password" class="block pr-1 text-xs font-bold text-on-background/80">كلمة المرور</label>
                        <div class="relative flex items-center rounded-xl bg-surface-container-low transition-all group focus-within:ring-2 focus-within:ring-secondary-container focus-within:bg-white border border-transparent focus-within:border-transparent">
                            <input 
                                id="password" 
                                :type="showPassword ? 'text' : 'password'" 
                                name="password" 
                                required
                                class="flex-1 w-full px-4 py-3.5 pr-11 pl-11 bg-transparent border-0 text-on-background placeholder:text-outline/60 focus:ring-0 text-sm font-headline"
                                placeholder="••••••••" 
                            />
                            <div class="absolute right-3.5 top-1/2 -translate-y-1/2 pointer-events-none text-outline group-focus-within:text-secondary-container transition-colors">
                                <span class="material-symbols-outlined text-[20px]">lock</span>
                            </div>
                            <button 
                                type="button" 
                                @click="showPassword = !showPassword"
                                class="absolute left-3.5 top-1/2 -translate-y-1/2 text-outline hover:text-primary transition-colors focus:outline-none"
                            >
                                <span class="material-symbols-outlined text-[20px]" x-text="showPassword ? 'visibility_off' : 'visibility'">visibility</span>
                            </button>
                        </div>
                        @error('password')
                            <p class="text-xs text-error mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- خيار تذكر هذا الجهاز -->
                    <div class="flex justify-between items-center pt-1">
                        <label for="remember" class="inline-flex items-center gap-2 cursor-pointer select-none">
                            <input 
                                id="remember" 
                                type="checkbox" 
                                name="remember"
                                class="w-4 h-4 rounded border-slate-300 text-secondary-container focus:ring-secondary-container" 
                            />
                            <span class="text-xs text-on-background/70 font-body">تذكر هذا الجهاز</span>
                        </label>
                    </div>

                    <!-- زر الدخول المتفاعل مع التحميل -->
                    <div class="pt-3" x-data="{ isSubmitting: false }">
                        <button 
                            type="submit"
                            @click="if($el.closest('form').checkValidity()) { isSubmitting = true; $el.closest('form').submit(); }"
                            :disabled="isSubmitting"
                            :class="{ 'opacity-80 cursor-wait': isSubmitting }"
                            class="w-full bg-secondary-container text-on-secondary-container font-extrabold py-3.5 sm:py-4 rounded-xl shadow-[0_8px_20px_rgba(254,157,32,0.25)] hover:shadow-[0_10px_25px_rgba(254,157,32,0.35)] active:scale-[0.98] transition-all duration-200 flex items-center justify-center gap-2 group"
                        >
                            <template x-if="!isSubmitting">
                                <div class="flex items-center gap-2">
                                    <span class="text-base font-bold">دخول للوحة الطرود</span>
                                    <span class="material-symbols-outlined text-xl transition-transform group-hover:-translate-x-1">arrow_back</span>
                                </div>
                            </template>

                            <template x-if="isSubmitting">
                                <div class="flex items-center gap-2">
                                    <span class="text-base font-bold">جاري التحقق...</span>
                                    <span class="material-symbols-outlined text-xl animate-spin">autorenew</span>
                                </div>
                            </template>
                        </button>
                    </div>

                </form>
            </div>
        </section>

    </main>

    <!-- الفوتر -->
    <footer class="mt-2 mb-6 text-center text-xs text-slate-400">
        نظام إدارة وإشعار الطرود &copy; 2026 مُرسَل
    </footer>

</body>
</html>