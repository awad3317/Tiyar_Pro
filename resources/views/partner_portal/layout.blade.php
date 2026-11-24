@php
    $brandPrimary = $brand['primary_color'] ?? '#041627';
    $brandSecondary = $brand['secondary_color'] ?? '#fe9d20';
    $brandLogo = $brand['logo_url'] ?? null;
    $brandIcon = $brand['icon_url'] ?? asset('icons/icon-192.png');
    $officeDisplayName = $brand['office_name'] ?? 'مكتب مُرسَل';
    $branchDisplayName = trim((string) ($brand['branch_name'] ?? ''));
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'بوابة إرسال الشحنات') - {{ $officeDisplayName }}</title>

    <meta name="theme-color" content="{{ $brandPrimary }}">
    <link rel="icon" type="image/png" href="{{ $brandIcon }}">

    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@400;700;800&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=swap" rel="stylesheet" crossorigin="anonymous">
    <script src="{{ asset('js/tailwind.js') }}"></script>
    <script>
        tailwind.config = {
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
        [x-cloak] { display: none !important; }
        body { font-family: 'Almarai', sans-serif; -webkit-tap-highlight-color: transparent; }
        .dir-ltr { direction: ltr; }
    </style>

    @stack('styles')
</head>
<body class="bg-slate-100 text-slate-900 min-h-screen flex flex-col">

    <div class="w-full max-w-lg mx-auto min-h-screen flex flex-col bg-slate-50 shadow-lg">
        {{-- هيدر هوية المكتب المستلم + شارة المكتب الخارجي --}}
        <header class="bg-primary text-white px-4 py-3.5 shadow-md sticky top-0 z-30">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-10 h-10 rounded-xl bg-white/95 border border-white/20 p-1 flex items-center justify-center shrink-0 overflow-hidden shadow-xs">
                        <img src="{{ $brandLogo ?: $brandIcon }}"
                             alt="{{ $officeDisplayName }}"
                             class="w-full h-full object-contain">
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] text-secondary font-extrabold">بوابة إرسال الشحنات المباشرة إلى</p>
                        <h1 class="text-sm sm:text-base font-extrabold text-white truncate">
                            {{ $officeDisplayName }}
                            @if ($branchDisplayName !== '')
                                <span class="text-xs font-bold text-slate-300">({{ $branchDisplayName }})</span>
                            @endif
                        </h1>
                    </div>
                </div>

                <div class="shrink-0 text-left bg-white/10 border border-white/15 rounded-xl px-2.5 py-1.5 max-w-[155px]">
                    <p class="text-[9px] text-slate-300 font-bold">المكتب المُرسِل</p>
                    <p class="text-[11px] font-extrabold text-white truncate">{{ $link->partner_name }}</p>
                    @if ($link->partner_city)
                        <p class="text-[9px] text-secondary font-bold truncate">{{ $link->partner_city }}</p>
                    @endif
                </div>
            </div>
        </header>

        {{-- محتوى البوابة --}}
        <main class="flex-1 p-3.5 sm:p-4 flex flex-col">
            @yield('content')
        </main>

        <footer class="py-3 px-4 text-center text-[10px] font-bold text-slate-400 border-t border-slate-200/70 bg-white">
            مدعوم عبر نظام مُرسَل لإدارة الشحن والطرود
        </footer>
    </div>

    @stack('scripts')
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
