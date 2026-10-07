<!DOCTYPE html>
<html dir="rtl" lang="ar">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>تسجيل دخول المكتب - مُرسَل</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&family=Manrope:wght@200;400;600;800&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet" />

    <!-- مكتبات Tailwind و AlpineJS -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>

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
                        "headline": ["Manrope", "Almarai"],
                        "body": ["Almarai", "Manrope"],
                        "label": ["Almarai", "Manrope"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.125rem",
                        "lg": "0.25rem",
                        "xl": "0.5rem",
                        "full": "0.75rem"
                    },
                },
            },
        }
    </script>
    <style>
        [x-cloak] {
            display: none !important;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }

        body {
            font-family: 'Almarai', sans-serif;
            background-color: #f7f9ff;
        }

        .kinetic-gradient {
            background: linear-gradient(135deg, #041627 0%, #1a2b3c 100%);
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background-color: #d2e4fb;
            border-radius: 10px;
        }
    </style>
</head>

<body class="flex overflow-x-hidden flex-col justify-center items-center min-h-screen">

    <div class="overflow-hidden fixed inset-0 z-0 opacity-10 pointer-events-none">
        <div class="absolute top-[-10%] right-[-10%] w-[60%] h-[60%] rounded-full bg-secondary-container blur-[120px]">
        </div>
        <div class="absolute bottom-[-10%] left-[-10%] w-[50%] h-[50%] rounded-full bg-primary blur-[120px]"></div>
    </div>

    <main
        class="relative z-10 w-full max-w-6xl flex flex-col md:flex-row-reverse items-stretch min-h-[750px] m-4 md:m-8 overflow-hidden rounded-xl shadow-[0_20px_60px_rgba(11,29,45,0.08)] bg-surface-container-lowest">

        <section
            class="hidden overflow-hidden relative flex-col justify-between items-start p-12 text-white md:flex md:w-5/12 kinetic-gradient">
            <div class="relative z-20 space-y-6">
                <div class="flex gap-3 items-center">
                    <div
                        class="flex overflow-hidden justify-center items-center w-12 h-12 rounded-lg shadow-lg bg-secondary-container">
                        <img src="{{ asset('assets/icons/icon-192.png') }}" alt="Mursal Logo"
                            class="object-contain w-10 h-10">
                    </div>
                    <span class="text-2xl font-black tracking-tighter uppercase font-headline">مُرسَل</span>
                </div>
                <h1 class="text-5xl font-extrabold leading-[1.1] font-headline tracking-tight">
                    مستقبل <span class="text-secondary-fixed-dim">الخدمات</span> اللوجستية يبدأ من هنا.
                </h1>
                <p class="max-w-xs text-lg leading-relaxed opacity-75 text-on-primary-container">
                    انضم إلى شبكة مرسل العالمية وأدر شحناتك بدقة معمارية وتقنية متطورة.
                </p>
            </div>

            <div class="relative z-20 mt-auto">
                <div class="flex gap-2 items-center mb-4 text-sm font-medium text-on-primary-container/80">
                    <span class="w-8 h-[2px] bg-secondary-container"></span>
                    <span>موثوق به من قبل أكثر من 20 مكتب في اليمن</span>
                </div>
            </div>

            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[150%] h-[150%] opacity-10">
                <img class="object-cover w-full h-full mix-blend-overlay"
                    src="{{ asset('assets/icons/icon-19s2.png') }}" alt="Background Pattern" />
            </div>
        </section>

        <section class="flex flex-col flex-1 justify-center p-8 bg-white md:p-16">
            <div class="mx-auto w-full max-w-md">

                <div class="flex justify-center mb-10 md:hidden">
                    <div class="flex gap-3 items-center">
                        <div class="flex justify-center items-center w-10 h-10 rounded bg-primary">
                            <img src="{{ asset('assets/icons/icon-192.png') }}" alt="Mursal Logo"
                                class="object-contain w-10 h-10">
                        </div>
                        <span class="text-2xl font-black tracking-tighter uppercase font-headline">مُرسَل</span>
                    </div>
                </div>

                <div class="mb-10 text-right">
                    <h2 class="mb-2 text-3xl font-bold text-on-background font-headline">تسجيل الدخول إلى حسابك</h2>
                    <p class="text-base text-slate-500 font-body">أدخل رقم الجوال وكلمة المرور للمتابعة</p>
                </div>

                @if (session('status'))
                    <div class="mb-4 font-medium text-sm text-green-600">
                        {{ session('status') }}
                    </div>
                @endif

                @error('phone')
                    <div
                        class="mb-4 p-3.5 bg-red-50 border-r-4 border-error rounded-lg flex items-center gap-2.5 text-error text-xs font-semibold text-right">
                        <span class="material-symbols-outlined text-lg">error</span>
                        <span>{{ $message }}</span>
                    </div>
                @enderror

                <form method="POST" action="{{ route('office.login.submit') }}" class="space-y-5">
                    @csrf

                    <div class="space-y-1.5" x-data="phoneInput">
                        <label for="phone_display"
                            class="block pr-1 text-sm font-bold text-on-background/80 text-right">رقم
                            الجوال</label>

                        <input type="hidden" name="phone" :value="fullPhone">

                        <div class="relative" dir="ltr">
                            <div
                                class="flex overflow-hidden relative items-center rounded-lg transition-all group bg-surface-container-low focus-within:ring-2 focus-within:ring-secondary-container">

                                <button type="button" @click="open = !open"
                                    class="flex shrink-0 items-center gap-2 px-3 h-[52px] bg-slate-100 border-r border-slate-200 hover:bg-slate-200 transition-colors focus:outline">
                                    <template x-if="selectedCountry?.svg">
                                        <svg class="w-6 h-auto rounded-sm shadow-sm" viewBox="0 0 36 24" fill="none"
                                            xmlns="http://www.w3.org/2000/svg" x-html="selectedCountry.svg"></svg>
                                    </template>
                                    <span class="text-sm font-bold text-on-surface font-headline"
                                        x-text="selectedCountry?.dial_code"></span>
                                    <span
                                        class="material-symbols-outlined text-[18px] text-slate-400">expand_more</span>
                                </button>

                                <input id="phone_display" type="tel" x-model="localPhoneNumber" required autofocus
                                    inputmode="numeric" {{-- 💡 التقييد الذكي: 9 أرقام لليمن، و 15 كحد أقصى للبقية --}}
                                    :maxlength="selectedCountry?.code === 'YE' ? 9 : 15"
                                    class="flex-1 min-w-0 w-full px-4 py-3.5 pr-11 text-left bg-transparent border-0 text-on-background placeholder:text-outline focus:ring-0 font-headline tracking-wider"
                                    placeholder="7XXXXXXXX" />

                                <div
                                    class="absolute right-3.5 top-1/2 -translate-y-1/2 pointer-events-none text-outline group-focus-within:text-secondary">
                                    <span class="material-symbols-outlined">call</span>
                                </div>
                            </div>

                            <div x-show="open" @click.outside="open = false" x-transition x-cloak
                                class="absolute top-[calc(100%+6px)] left-0 z-50 w-full sm:w-[320px] bg-white rounded-2xl border border-slate-100 shadow-2xl overflow-hidden"
                                dir="rtl">
                                <div class="p-2 border-b border-slate-50">
                                    <input type="text" x-model="search" placeholder="ابحث عن الدولة أو الرمز..."
                                        class="px-4 py-2.5 w-full text-sm rounded-xl transition-colors outline-none bg-slate-50 hover:bg-slate-100 focus:bg-slate-100 font-headline">
                                </div>
                                <div class="overflow-y-auto max-h-48 custom-scrollbar">
                                    <template x-for="country in filteredCountries" :key="country.code">
                                        <div @click="selectedCountry = country; open = false; search = ''"
                                            class="flex gap-3 items-center p-3 px-4 transition-colors cursor-pointer hover:bg-primary/5">
                                            <svg class="w-5 h-auto rounded-sm shadow-sm shrink-0" viewBox="0 0 36 24"
                                                fill="none" xmlns="http://www.w3.org/2000/svg"
                                                x-html="country.svg"></svg>
                                            <span
                                                class="flex-grow text-sm font-medium truncate text-slate-700 font-headline"
                                                x-text="country.name"></span>
                                            <span class="font-mono text-xs font-bold text-slate-500 shrink-0"
                                                dir="ltr" x-text="country.dial_code"></span>
                                        </div>
                                    </template>
                                    <div x-show="filteredCountries.length === 0"
                                        class="p-4 text-sm font-medium text-center text-slate-500">
                                        لا توجد نتائج مطابقة
                                    </div>
                                </div>
                            </div>
                        </div>
                        @error('phone')
                            <p class="mt-1 text-xs text-error text-right">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-1.5" x-data="{ showPassword: false }">
                        <label for="password"
                            class="block pr-1 text-sm font-bold text-on-background/80 text-right">كلمة
                            المرور</label>
                        <div class="relative group">
                            <input id="password" :type="showPassword ? 'text' : 'password'" name="password" required
                                autocomplete="current-password"
                                class="px-4 py-3.5 pr-11 w-full rounded-lg border-0 transition-all bg-surface-container-low text-on-background placeholder:text-outline/60 focus:ring-2 focus:ring-secondary-container font-headline"
                                placeholder="••••••••" />
                            <div
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-outline group-focus-within:text-secondary">
                                <span class="material-symbols-outlined">lock</span>
                            </div>
                            <button type="button" @click="showPassword = !showPassword"
                                class="absolute left-3.5 top-1/2 transition-colors -translate-y-1/2 text-outline hover:text-primary focus:outline-none">
                                <span class="material-symbols-outlined"
                                    x-text="showPassword ? 'visibility_off' : 'visibility'">visibility</span>
                            </button>
                        </div>
                        @error('password')
                            <p class="mt-1 text-xs text-error text-right">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex justify-between items-center mt-4">
                        <label for="remember_me" class="inline-flex items-center cursor-pointer">
                            <input id="remember_me" type="checkbox" name="remember" value="1"
                                class="rounded shadow-sm border-slate-300 text-secondary focus:ring-secondary-container">
                            <span class="text-sm ms-2 text-on-background/70 font-body">{{ __('تذكرني') }}</span>
                        </label>
                    </div>

                    <div class="pt-4" x-data="{ isSubmitting: false }">
                        <button type="submit"
                            @click.prevent="
            if($el.closest('form').checkValidity()) { 
                isSubmitting = true; 
                $el.closest('form').submit(); 
            } else { 
                $el.closest('form').reportValidity(); 
            }
        "
                            :disabled="isSubmitting"
                            :class="{ 'opacity-75 cursor-not-allowed active:scale-100 hover:shadow-none': isSubmitting }"
                            class="w-full bg-secondary-container text-on-secondary-container font-extrabold py-4 rounded-lg shadow-[0_8px_20px_rgba(254,157,32,0.3)] hover:shadow-[0_12px_25px_rgba(254,157,32,0.4)] active:scale-95 transition-all duration-300 flex items-center justify-center gap-3 group">


                            <div x-show="!isSubmitting" class="flex gap-3 items-center">
                                <span class="text-lg">تسجيل الدخول</span>
                                <span
                                    class="transition-transform transform material-symbols-outlined group-hover:-translate-x-1">arrow_back</span>
                            </div>

                            <div x-show="isSubmitting" x-cloak class="flex gap-3 items-center">
                                <span class="text-lg">جاري الدخول...</span>
                                <span class="animate-spin material-symbols-outlined">autorenew</span>
                            </div>

                        </button>
                    </div>
                </form>

            </div>
        </section>
    </main>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('phoneInput', () => ({
                open: false,
                search: '',
                countries: [{
                        name: 'اليمن',
                        code: 'YE',
                        dial_code: '+967',
                        svg: '<rect width="36" height="24" fill="#fff"/><rect width="36" height="8" fill="#ce1126"/><rect y="16" width="36" height="8" fill="#000"/>'
                    },
                    {
                        name: 'المملكة العربية السعودية',
                        code: 'SA',
                        dial_code: '+966',
                        svg: '<rect width="36" height="24" fill="#006a4e"/><path fill="#fff" d="M10 10h16v1.5H10zM12 14h12v1H12z"/>'
                    },
                    {
                        name: 'عمان',
                        code: 'OM',
                        dial_code: '+968',
                        svg: '<rect width="36" height="24" fill="#fff"/><rect width="10" height="24" fill="#c8102e"/><rect y="8" width="36" height="8" fill="#c8102e"/><rect y="16" width="36" height="8" fill="#009b77"/>'
                    },
                    {
                        name: 'الإمارات',
                        code: 'AE',
                        dial_code: '+971',
                        svg: '<rect width="36" height="24" fill="#fff"/><rect width="36" height="8" fill="#00732f"/><rect y="16" width="36" height="8" fill="#000"/><rect width="10" height="24" fill="#ff0000"/>'
                    },
                    {
                        name: 'الكويت',
                        code: 'KW',
                        dial_code: '+965',
                        svg: '<rect width="36" height="24" fill="#fff"/><rect width="36" height="8" fill="#007a3d"/><rect y="16" width="36" height="8" fill="#ce1126"/><polygon points="0,0 12,8 12,16 0,24" fill="#000"/>'
                    },
                    {
                        name: 'البحرين',
                        code: 'BH',
                        dial_code: '+973',
                        svg: '<rect width="36" height="24" fill="#ce1126"/><path fill="#fff" d="M0,0 h10 v24 h-10 z"/><polygon points="10,0 14,3 10,6 14,9 10,12 14,15 10,18 14,21 10,24" fill="#fff"/>'
                    }
                ],
                selectedCountry: null,
                localPhoneNumber: '',
                fullPhone: '',
                init() {
                    this.selectedCountry = this.countries.find(c => c.code === 'YE') || this.countries[
                        0] || {
                        name: 'Yemen',
                        code: 'YE',
                        dial_code: '+967',
                        svg: ''
                    };

                    let oldVal = '{{ old('phone') }}';
                    if (oldVal) {
                        let dCode = this.selectedCountry?.dial_code.replace('+', '') || '967';
                        if (oldVal.startsWith(dCode)) {
                            this.localPhoneNumber = oldVal.substring(dCode.length);
                        } else {
                            this.localPhoneNumber = oldVal;
                        }
                    }

                    this.updateFullPhone();
                    this.$watch('localPhoneNumber', () => this.updateFullPhone());
                    this.$watch('selectedCountry', () => this.updateFullPhone());
                },
                updateFullPhone() {
                    this.fullPhone = this.localPhoneNumber ? (this.selectedCountry?.dial_code.replace(
                        '+', '') || '') + this.localPhoneNumber : '';
                },
                get filteredCountries() {
                    if (this.search === '') return this.countries;
                    return this.countries.filter(c => c.name.toLowerCase().includes(this.search
                        .toLowerCase()) || c.dial_code.includes(this.search));
                }
            }));
        });
    </script>

    <footer
        class="flex flex-col md:flex-row justify-center items-center gap-4 mt-4 mb-8 text-xs font-medium text-slate-400 text-center">
        <span class="tracking-widest font-headline">MURSAL LOGISTICS</span>
        <span>© 2026 جميع الحقوق محفوظة</span>
        <div class="flex gap-4">
            <a class="transition-colors hover:text-primary" href="#">سياسة الخصوصية</a>
            <a class="transition-colors hover:text-primary" href="#">الشروط والأحكام</a>
        </div>
    </footer>
</body>

</html>
