<!DOCTYPE html>
<html dir="rtl" lang="ar">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" name="viewport" />
    <meta name="theme-color" content="#041627" />
    <meta name="mobile-web-app-capable" content="yes" />
    <link rel="manifest" href="{{ asset('accountant-manifest.json') }}" />
    <title>تطبيق المحاسبين - بوابة المحاسب المالي | مُرسَل</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&family=Manrope:wght@400;600;700;800&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet" />

    <!-- مكتبات Tailwind المحلية و AlpineJS لسرعة الأداء في جميع الدول -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="{{ asset('js/tailwind.js') }}"></script>

    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "secondary-fixed-dim": "#ffb86e",
                        "on-tertiary-container": "#a88c69",
                        "on-surface-variant": "#44474c",
                        "tertiary-container": "#38260b",
                        "surface-container-high": "#d9eaff",
                        "secondary-fixed": "#ffdcbd",
                        "on-primary-fixed-variant": "#38485a",
                        "on-error": "#ffffff",
                        "on-primary-container": "#8192a7",
                        "surface-container": "#e4efff",
                        "inverse-on-surface": "#e9f1ff",
                        "on-primary-fixed": "#0b1d2d",
                        "on-background": "#0b1d2d",
                        "primary-fixed-dim": "#b7c8de",
                        "tertiary": "#211200",
                        "inverse-primary": "#b7c8de",
                        "on-secondary": "#ffffff",
                        "surface-bright": "#f7f9ff",
                        "surface": "#f7f9ff",
                        "inverse-surface": "#213243",
                        "surface-container-highest": "#d2e4fb",
                        "secondary": "#8a5100",
                        "surface-container-low": "#eef4ff",
                        "outline-variant": "#c4c6cd",
                        "background": "#f7f9ff",
                        "surface-variant": "#d2e4fb",
                        "error-container": "#ffdad6",
                        "on-secondary-container": "#673b00",
                        "secondary-container": "#fe9d20",
                        "on-tertiary-fixed": "#281802",
                        "on-primary": "#ffffff",
                        "on-surface": "#0b1d2d",
                        "on-tertiary": "#ffffff",
                        "tertiary-fixed": "#feddb5",
                        "primary": "#041627",
                        "surface-dim": "#cadcf2",
                        "on-error-container": "#93000a",
                        "surface-container-lowest": "#ffffff",
                        "tertiary-fixed-dim": "#e1c29b",
                        "on-tertiary-fixed-variant": "#584326",
                        "on-secondary-fixed-variant": "#693c00",
                        "outline": "#74777d",
                        "on-secondary-fixed": "#2c1600",
                        "primary-container": "#1a2b3c",
                        "surface-tint": "#4f6073",
                        "primary-fixed": "#d2e4fb",
                        "error": "#ba1a1a"
                    },
                    fontFamily: {
                        "headline": ["Almarai", "Manrope"],
                        "body": ["Almarai", "Manrope"],
                        "label": ["Almarai", "Manrope"]
                    }
                },
            },
        }
    </script>
    <style>
        [x-cloak] {
            display: none !important;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 500, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }

        body {
            font-family: 'Almarai', sans-serif;
            background-color: #f7f9ff;
        }

        .accountant-gradient {
            background: linear-gradient(135deg, #041627 0%, #0f2942 60%, #1a3654 100%);
        }
    </style>
</head>

<body class="flex overflow-x-hidden flex-col justify-center items-center min-h-screen px-4 py-6">

    <!-- خلفية ضوئية ناعمة -->
    <div class="overflow-hidden fixed inset-0 z-0 opacity-15 pointer-events-none">
        <div class="absolute top-[-10%] right-[-10%] w-[60%] h-[60%] rounded-full bg-emerald-500 blur-[120px]"></div>
        <div class="absolute bottom-[-10%] left-[-10%] w-[50%] h-[50%] rounded-full bg-primary blur-[120px]"></div>
    </div>

    <main
        class="relative z-10 w-full max-w-5xl flex flex-col md:flex-row-reverse items-stretch overflow-hidden rounded-3xl shadow-[0_20px_60px_rgba(4,22,39,0.12)] bg-white border border-slate-100">

        <!-- الجانب الأيسر (التعريفي - يظهر في الشاشات المتوسطة والكبيرة) -->
        <section
            class="hidden overflow-hidden relative flex-col justify-between items-start p-10 lg:p-12 text-white md:flex md:w-5/12 accountant-gradient">
            <div class="relative z-20 space-y-6">
                <div class="flex gap-3 items-center">
                    <div
                        class="flex justify-center items-center w-12 h-12 rounded-2xl shadow-lg bg-emerald-500/20 border border-emerald-400/30 text-emerald-300">
                        <span class="material-symbols-outlined text-3xl">account_balance_wallet</span>
                    </div>
                    <div>
                        <span class="block text-xl font-black tracking-tight font-headline">مُرسَل</span>
                        <span class="block text-xs font-bold text-emerald-300">النظام المالي للمكاتب</span>
                    </div>
                </div>

                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/10 border border-white/15 text-xs font-bold text-emerald-200">
                    <span class="material-symbols-outlined text-base">calculate</span>
                    <span>تطبيق المحاسبين</span>
                </div>

                <h1 class="text-3xl lg:text-4xl font-extrabold leading-snug font-headline">
                    إدارة مالية دقيقة <span class="text-secondary-fixed-dim">ومتابعة فورية</span> لتحصيل الطرود.
                </h1>
                <p class="text-sm leading-relaxed text-slate-300">
                    بوابة مخصصة لمحاسبي المكاتب لمراجعة مبالغ التحصيل عند التسليم، تسوية الحسابات، ومتابعة التدفقات المالية بكل سهولة وأمان.
                </p>
            </div>

            <div class="relative z-20 mt-auto pt-8">
                <div class="flex gap-2.5 items-center text-xs font-bold text-slate-300">
                    <span class="material-symbols-outlined text-emerald-400 text-lg">verified_user</span>
                    <span>جلسة محاسبية مستقلة ومحمية بالكامل</span>
                </div>
            </div>

            <div class="absolute -bottom-16 -left-16 opacity-5 pointer-events-none">
                <span class="material-symbols-outlined text-[240px]">account_balance_wallet</span>
            </div>
        </section>

        <!-- الجانب الأيمن (نموذج تسجيل الدخول - Mobile First) -->
        <section class="flex flex-col flex-1 justify-center p-6 sm:p-10 md:p-14 bg-white">
            <div class="mx-auto w-full max-w-md">

                <!-- الهيدر المخصص للجوال والشاشات -->
                <div class="mb-8 text-center md:text-right">
                    <div class="inline-flex md:hidden items-center justify-center w-16 h-16 rounded-2xl accountant-gradient text-emerald-300 shadow-lg mb-4">
                        <span class="material-symbols-outlined text-4xl">account_balance_wallet</span>
                    </div>

                    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-extrabold mb-3">
                        <span class="material-symbols-outlined text-sm">calculate</span>
                        <span>تطبيق المحاسبين </span>
                    </div>

                    <h2 class="text-2xl sm:text-3xl font-extrabold text-primary font-headline">
                        تسجيل دخول المحاسب
                    </h2>
                    <p class="mt-1.5 text-sm text-slate-500 font-body">
                        أدخل رقم الجوال والرمز السري الخاص بحسابك 
                    </p>
                </div>

                <!-- رسائل التنبيه والأخطاء -->
                @if (session('status'))
                    <div class="mb-5 p-3.5 bg-emerald-50 border-r-4 border-emerald-600 rounded-xl flex items-center gap-2.5 text-emerald-800 text-xs font-bold">
                        <span class="material-symbols-outlined text-lg">check_circle</span>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                @if (session('error') && !$errors->has('phone'))
                    <div class="mb-5 p-3.5 bg-red-50 border-r-4 border-error rounded-xl flex items-center gap-2.5 text-error text-xs font-bold">
                        <span class="material-symbols-outlined text-lg">error</span>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-5 p-3.5 bg-red-50 border-r-4 border-error rounded-xl flex items-start gap-2.5 text-error text-xs font-bold text-right">
                        <span class="material-symbols-outlined text-lg shrink-0 mt-0.5">error</span>
                        <div class="space-y-1">
                            @foreach ($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('accountant.login.submit') }}" class="space-y-5">
                    @csrf

                    <!-- حقل رقم الجوال -->
                    <div class="space-y-1.5">
                        <label for="phone" class="block pr-1 text-sm font-bold text-on-background/80 text-right">
                            رقم الجوال
                        </label>
                        <div class="relative group" dir="ltr">
                            <div
                                class="flex overflow-hidden items-center rounded-xl transition-all bg-surface-container-low border border-slate-200/80 focus-within:border-secondary-container focus-within:ring-2 focus-within:ring-secondary-container/30">
                                <span
                                    class="flex shrink-0 items-center gap-1.5 px-3.5 h-[52px] bg-slate-100 border-r border-slate-200 text-xs font-extrabold text-slate-600 select-none">
                                    <span>+967</span>
                                </span>
                                <input id="phone" name="phone" type="tel" inputmode="numeric" dir="ltr"
                                    maxlength="9" required autofocus
                                    value="{{ old('phone') }}"
                                    placeholder="7XXXXXXXX"
                                    class="flex-1 min-w-0 w-full px-4 py-3.5 pr-11 text-left bg-transparent border-0 text-on-background placeholder:text-slate-400 focus:outline-none focus:ring-0 font-bold tracking-wider text-base" />
                                <div
                                    class="absolute right-3.5 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400 group-focus-within:text-secondary">
                                    <span class="material-symbols-outlined">smartphone</span>
                                </div>
                            </div>
                        </div>
                        <p class="text-[11px] text-slate-400 pr-1">أدخل رقم الجوال المكون من 9 أرقام (مثال: 771234567)</p>
                    </div>

                    <!-- حقل كلمة المرور / الرمز السري -->
                    <div class="space-y-1.5" x-data="{ showPassword: false }">
                        <label for="password" class="block pr-1 text-sm font-bold text-on-background/80 text-right">
                            كلمة المرور
                        </label>
                        <div class="relative group">
                            <input id="password" :type="showPassword ? 'text' : 'password'" name="password" required
                                autocomplete="current-password" dir="ltr"
                                class="px-4 py-3.5 pr-11 pl-11 w-full text-left rounded-xl border border-slate-200/80 transition-all bg-surface-container-low text-on-background placeholder:text-slate-400 focus:outline-none focus:border-secondary-container focus:ring-2 focus:ring-secondary-container/30 font-bold tracking-wider text-base"
                                placeholder="••••••••" />
                            <div
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400 group-focus-within:text-secondary">
                                <span class="material-symbols-outlined">lock</span>
                            </div>
                            <button type="button" @click="showPassword = !showPassword"
                                class="absolute left-3.5 top-1/2 transition-colors -translate-y-1/2 text-slate-400 hover:text-primary focus:outline-none"
                                title="إظهار / إخفاء الرمز السري">
                                <span class="material-symbols-outlined"
                                    x-text="showPassword ? 'visibility_off' : 'visibility'">visibility</span>
                            </button>
                        </div>
                    </div>

                    <!-- خيار تذكرني على هذا الجهاز -->
                    <div class="flex justify-between items-center pt-1">
                        <label for="remember_me" class="inline-flex items-center cursor-pointer select-none">
                            <input id="remember_me" type="checkbox" name="remember" value="1"
                                {{ old('remember', true) ? 'checked' : '' }}
                                class="w-4 h-4 rounded border-slate-300 text-secondary focus:ring-secondary-container">
                            <span class="text-sm font-bold ms-2 text-slate-700">تذكرني على هذا الجهاز</span>
                        </label>
                    </div>

                    <!-- زر تسجيل الدخول -->
                    <div class="pt-3" x-data="{ isSubmitting: false }">
                        <button type="submit"
                            @click.prevent="
                                if ($el.closest('form').checkValidity()) {
                                    isSubmitting = true;
                                    $el.closest('form').submit();
                                } else {
                                    $el.closest('form').reportValidity();
                                }
                            "
                            :disabled="isSubmitting"
                            :class="{ 'opacity-75 cursor-not-allowed active:scale-100': isSubmitting }"
                            class="w-full bg-secondary-container text-on-secondary-container font-extrabold py-4 rounded-xl shadow-[0_8px_20px_rgba(254,157,32,0.3)] hover:shadow-[0_12px_25px_rgba(254,157,32,0.4)] active:scale-95 transition-all duration-300 flex items-center justify-center gap-3 group">

                            <div x-show="!isSubmitting" class="flex gap-2.5 items-center">
                                <span class="text-base sm:text-lg">تسجيل الدخول</span>
                                <span class="transition-transform transform material-symbols-outlined group-hover:-translate-x-1">login</span>
                            </div>

                            <div x-show="isSubmitting" x-cloak class="flex gap-2.5 items-center">
                                <span class="text-base sm:text-lg">جاري التحقق والدخول...</span>
                                <span class="animate-spin material-symbols-outlined">autorenew</span>
                            </div>
                        </button>
                    </div>
                </form>

            </div>
        </section>
    </main>

    <footer class="mt-6 text-xs font-bold text-slate-400 text-center">
        <span>نظام مُرسَل </span>
    </footer>

    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/accountant-sw.js', { scope: '/accountant/' });
        }
    </script>

</body>

</html>

