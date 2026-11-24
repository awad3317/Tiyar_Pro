{{-- شاشة التحميل التفاعلية لهوية مُرسَل (نقل الطرود السريع عبر الأيقونة الرسمية) --}}
<style>
    @keyframes mursalFloatCube {
        0%, 100% { transform: translate3d(0, 0, 0) scale(1); }
        50%      { transform: translate3d(2px, -7px, 0) scale(1.045); }
    }

    @keyframes mursalOrbitCw {
        0%   { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    @keyframes mursalOrbitCcw {
        0%   { transform: rotate(360deg); }
        100% { transform: rotate(0deg); }
    }

    @keyframes mursalPulseAura {
        0%, 100% { transform: scale(0.92); opacity: 0.35; }
        50%      { transform: scale(1.16); opacity: 0.75; }
    }

    @keyframes mursalSpeedStreak {
        0%   { transform: rotate(-42deg) translate3d(-68px, 0, 0) scaleX(0.35); opacity: 0; }
        30%  { opacity: 0.95; }
        100% { transform: rotate(-42deg) translate3d(78px, 0, 0) scaleX(1.15); opacity: 0; }
    }

    @keyframes mursalLaserScan {
        0%, 100% { transform: translate3d(0, -34px, 0); opacity: 0; }
        18%      { opacity: 0.9; }
        50%      { transform: translate3d(0, 34px, 0); opacity: 0.95; }
        82%      { opacity: 0.9; }
    }

    @keyframes mursalConveyorBox {
        0%   { transform: translate3d(62px, 0, 0) scale(0.72); opacity: 0; }
        18%  { transform: translate3d(38px, 0, 0) scale(1); opacity: 1; }
        82%  { transform: translate3d(-38px, 0, 0) scale(1); opacity: 1; }
        100% { transform: translate3d(-62px, 0, 0) scale(0.72); opacity: 0; }
    }

    @keyframes mursalTopBar {
        0%   { transform: translate3d(100%, 0, 0) scaleX(0.25); }
        50%  { transform: translate3d(0%, 0, 0) scaleX(0.65); }
        100% { transform: translate3d(-110%, 0, 0) scaleX(0.3); }
    }

    @keyframes mursalShadowBreath {
        0%, 100% { transform: scaleX(1); opacity: 0.45; }
        50%      { transform: scaleX(0.78); opacity: 0.22; }
    }

    .mursal-cube-float     { animation: mursalFloatCube 1.75s cubic-bezier(0.45, 0.05, 0.55, 0.95) infinite; will-change: transform; }
    .mursal-orbit-cw       { animation: mursalOrbitCw 5.5s linear infinite; will-change: transform; }
    .mursal-orbit-ccw      { animation: mursalOrbitCcw 2.1s cubic-bezier(0.55, 0.15, 0.45, 0.85) infinite; will-change: transform; }
    .mursal-aura-pulse     { animation: mursalPulseAura 1.75s ease-in-out infinite; will-change: transform, opacity; }
    .mursal-laser-line     { animation: mursalLaserScan 1.9s ease-in-out infinite; will-change: transform, opacity; }
    .mursal-shadow-breath  { animation: mursalShadowBreath 1.75s ease-in-out infinite; will-change: transform, opacity; }
    .mursal-top-ribbon     { animation: mursalTopBar 1.25s cubic-bezier(0.4, 0, 0.2, 1) infinite; will-change: transform; }

    .mursal-streak-1 { animation: mursalSpeedStreak 1.05s cubic-bezier(0.22, 1, 0.36, 1) infinite; will-change: transform, opacity; }
    .mursal-streak-2 { animation: mursalSpeedStreak 1.05s cubic-bezier(0.22, 1, 0.36, 1) 0.32s infinite; will-change: transform, opacity; }
    .mursal-streak-3 { animation: mursalSpeedStreak 1.05s cubic-bezier(0.22, 1, 0.36, 1) 0.64s infinite; will-change: transform, opacity; }

    .mursal-parcel-hop-1 { animation: mursalConveyorBox 1.8s linear infinite; will-change: transform, opacity; }
    .mursal-parcel-hop-2 { animation: mursalConveyorBox 1.8s linear 0.6s infinite; will-change: transform, opacity; }
    .mursal-parcel-hop-3 { animation: mursalConveyorBox 1.8s linear 1.2s infinite; will-change: transform, opacity; }

    @media (prefers-reduced-motion: reduce) {
        .mursal-cube-float,
        .mursal-orbit-cw,
        .mursal-orbit-ccw,
        .mursal-aura-pulse,
        .mursal-laser-line,
        .mursal-shadow-breath,
        .mursal-top-ribbon,
        .mursal-streak-1,
        .mursal-streak-2,
        .mursal-streak-3,
        .mursal-parcel-hop-1,
        .mursal-parcel-hop-2,
        .mursal-parcel-hop-3 {
            animation: none !important;
        }
    }
</style>

<div id="mursal-nav-loader"
     role="status"
     aria-live="polite"
     aria-hidden="true"
     style="display: none; background: radial-gradient(circle at 50% 38%, #0b2948 0%, #041627 62%, #020c17 100%);"
     class="fixed inset-0 max-w-md mx-auto z-[70] flex-col items-center justify-center overflow-hidden select-none print:hidden transition-opacity duration-150 opacity-0">

    {{-- شريط الليزر العلوي المتوهج --}}
    <div class="absolute top-0 left-0 right-0 h-1 bg-white/5 overflow-hidden">
        <div class="mursal-top-ribbon h-full w-full rounded-full"
             style="background: linear-gradient(90deg, transparent 0%, #fe9d20 35%, #fde68a 55%, #fe9d20 75%, transparent 100%);"></div>
    </div>

    {{-- شبكة رادار هندسية خفيفة بالخلفية تعطي عمق مراكز الفرز والشحن --}}
    <div class="absolute inset-0 opacity-[0.07] pointer-events-none"
         style="background-image: linear-gradient(to right, #94a3b8 1px, transparent 1px), linear-gradient(to bottom, #94a3b8 1px, transparent 1px); background-size: 28px 28px;"></div>

    {{-- المسرح المركزي: مدار الشحن + خطوط السرعة + الأيقونة الرسمية ثلاثية الأبعاد --}}
    <div class="relative flex flex-col items-center px-6">

        <div class="relative w-36 h-36 flex items-center justify-center">
            {{-- هالة الطاقة الذهبية النابضة خلف المكعب --}}
            <div class="mursal-aura-pulse absolute w-24 h-24 rounded-full pointer-events-none"
                 style="background: radial-gradient(circle, rgba(254,157,32,0.42) 0%, rgba(37,99,235,0.18) 55%, transparent 75%);"></div>

            {{-- المدار الخارجي المتقطع (مسار ربط المحافظات والفروع) --}}
            <div class="mursal-orbit-cw absolute inset-1 rounded-full border border-dashed border-sky-300/25 pointer-events-none">
                <span class="absolute -top-1.5 left-1/2 -translate-x-1/2 w-3 h-3 rounded-full bg-[#fe9d20] shadow-[0_0_10px_#fe9d20]"></span>
                <span class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-2 h-2 rounded-full bg-sky-400 shadow-[0_0_8px_#38bdf8]"></span>
            </div>

            {{-- القوس السريع المعاكس بلون هوية المكتب الثانوي --}}
            <div class="mursal-orbit-ccw absolute inset-3.5 rounded-full border-2 border-transparent border-t-[#fe9d20] border-r-[#fe9d20]/50 pointer-events-none"></div>

            {{-- خطوط اندفاع الشحن السريع بنفس زاوية سهم الأيقونة الرسمية (من الأسفل يسار إلى الأعلى يمين) --}}
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none overflow-hidden rounded-full">
                <span class="mursal-streak-1 absolute w-16 h-[2.5px] rounded-full -translate-y-4"
                      style="background: linear-gradient(90deg, transparent, #fe9d20, #fef08a);"></span>
                <span class="mursal-streak-2 absolute w-20 h-[2px] rounded-full translate-y-1"
                      style="background: linear-gradient(90deg, transparent, #38bdf8, #ffffff);"></span>
                <span class="mursal-streak-3 absolute w-14 h-[2.5px] rounded-full translate-y-5"
                      style="background: linear-gradient(90deg, transparent, #fe9d20, #fde68a);"></span>
            </div>

            {{-- الأيقونة الرسمية لتطبيق مُرسَل + خط مسح الباركود الليزري --}}
            <div class="mursal-cube-float relative z-10 w-20 h-20 flex items-center justify-center">
                <img src="{{ asset('icons/icon-192.png') }}"
                     alt="مُرسَل"
                     width="80"
                     height="80"
                     decoding="sync"
                     class="w-20 h-20 object-contain drop-shadow-[0_10px_18px_rgba(0,0,0,0.55)]">

                {{-- شعاع مسح الباركود الذهبي العابر فوق الطرد --}}
                <div class="mursal-laser-line absolute left-1 right-1 h-[2px] rounded-full pointer-events-none"
                     style="background: linear-gradient(90deg, transparent 0%, #fde047 25%, #fe9d20 50%, #fde047 75%, transparent 100%); box-shadow: 0 0 10px #fe9d20;"></div>
            </div>
        </div>

        {{-- ظل الطرد العائم أسفل المكعب --}}
        <div class="mursal-shadow-breath w-14 h-2.5 rounded-full bg-black/50 -mt-1 mb-4"></div>

        {{-- شريط مسار الطرود المتحركة (يمثل انتقال الطرود بين المكاتب) --}}
        <div class="relative w-44 h-7 rounded-full bg-white/[0.06] border border-white/10 flex items-center justify-between px-2.5 overflow-hidden mb-3.5">
            {{-- نقطة الانطلاق --}}
            <span class="relative z-10 w-2 h-2 rounded-full bg-emerald-400 shadow-[0_0_8px_#34d399] shrink-0"></span>

            {{-- خط المسار المنقط --}}
            <div class="absolute left-6 right-6 top-1/2 -translate-y-1/2 border-t border-dashed border-white/20"></div>

            {{-- صناديق الطرود الصغيرة المندفعة على المسار --}}
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                <span class="mursal-parcel-hop-1 absolute inline-flex items-center justify-center text-[#fe9d20]">
                    <svg viewBox="0 0 24 24" class="w-4 h-4 fill-current"><path d="M21 16.5c0 .38-.21.71-.53.88l-7.9 4.44c-.16.12-.36.18-.57.18s-.41-.06-.57-.18l-7.9-4.44A.991.991 0 0 1 3 16.5v-9c0-.38.21-.71.53-.88l7.9-4.44c.16-.12.36-.18.57-.18s.41.06.57.18l7.9 4.44c.32.17.53.5.53.88v9M12 4.15 6.04 7.5 12 10.85l5.96-3.35L12 4.15M5 15.91l6 3.38v-6.71L5 9.21v6.7m14 0v-6.7l-6 3.37v6.71l6-3.38Z"/></svg>
                </span>
                <span class="mursal-parcel-hop-2 absolute inline-flex items-center justify-center text-sky-300">
                    <svg viewBox="0 0 24 24" class="w-3.5 h-3.5 fill-current"><path d="M21 16.5c0 .38-.21.71-.53.88l-7.9 4.44c-.16.12-.36.18-.57.18s-.41-.06-.57-.18l-7.9-4.44A.991.991 0 0 1 3 16.5v-9c0-.38.21-.71.53-.88l7.9-4.44c.16-.12.36-.18.57-.18s.41.06.57.18l7.9 4.44c.32.17.53.5.53.88v9M12 4.15 6.04 7.5 12 10.85l5.96-3.35L12 4.15Z"/></svg>
                </span>
                <span class="mursal-parcel-hop-3 absolute inline-flex items-center justify-center text-amber-300">
                    <svg viewBox="0 0 24 24" class="w-4 h-4 fill-current"><path d="M21 16.5c0 .38-.21.71-.53.88l-7.9 4.44c-.16.12-.36.18-.57.18s-.41-.06-.57-.18l-7.9-4.44A.991.991 0 0 1 3 16.5v-9c0-.38.21-.71.53-.88l7.9-4.44c.16-.12.36-.18.57-.18s.41.06.57.18l7.9 4.44c.32.17.53.5.53.88v9M12 4.15 6.04 7.5 12 10.85l5.96-3.35L12 4.15M5 15.91l6 3.38v-6.71L5 9.21v6.7m14 0v-6.7l-6 3.37v6.71l6-3.38Z"/></svg>
                </span>
            </div>

            {{-- نقطة الوصول --}}
            <span class="relative z-10 w-2.5 h-2.5 rounded-full bg-[#fe9d20] shadow-[0_0_10px_#fe9d20] shrink-0"></span>
        </div>

        {{-- عنوان القسم الجاري الانتقال إليه ديناميكياً --}}
        <div class="text-center space-y-1">
            <div class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full bg-white/[0.08] border border-white/10">
                <span class="w-1.5 h-1.5 rounded-full bg-[#fe9d20] animate-ping"></span>
                <span id="mursal-nav-loader-label" class="text-xs font-extrabold text-white tracking-wide">
                    جاري الانتقال...
                </span>
            </div>
            <p class="text-[10px] font-bold text-slate-400">
                مُرسَل لإدارة ونقل الطرود السريع
            </p>
        </div>
    </div>
</div>

<script>
    (function (global) {
        'use strict';

        const OFFLINE_CAPABLE_PATHS = new Set([
            '/office/dashboard',
            '/office/parcels',
            '/office/shipments/sent',
            '/office/favorite-customers',
        ]);

        const ROUTE_LABELS = [
            { match: '/office/dashboard',          label: 'جاري فتح الرئيسية...' },
            { match: '/office/outgoing-parcels',   label: 'جاري تجهيز الطرود المرسلة...' },
            { match: '/office/transit-parcels',    label: 'جاري فحص الطرود في الطريق...' },
            { match: '/office/parcels',            label: 'جاري فتح الطرود المستلمة...' },
            { match: '/office/shipments/sent',     label: 'جاري فتح الشحنات المرسلة...' },
            { match: '/office/shipments/received', label: 'جاري فحص الشحنات المستلمة...' },
            { match: '/office/favorite-customers', label: 'جاري فتح العملاء المميزين...' },
            { match: '/office/whatsapp',           label: 'جاري فتح إعدادات الواتساب...' },
        ];

        let safetyTimer = null;

        function getElements() {
            return {
                root: document.getElementById('mursal-nav-loader'),
                label: document.getElementById('mursal-nav-loader-label'),
            };
        }

        function resolveLabelForPath(pathname, customLabel) {
            if (customLabel && String(customLabel).trim() !== '') {
                const clean = String(customLabel).trim();
                return clean.startsWith('جاري') ? clean : ('جاري فتح ' + clean + '...');
            }

            for (let i = 0; i < ROUTE_LABELS.length; i++) {
                if (pathname.startsWith(ROUTE_LABELS[i].match)) {
                    return ROUTE_LABELS[i].label;
                }
            }

            return 'جاري الانتقال...';
        }

        function showNavLoader(customLabel, targetPath) {
            const els = getElements();
            if (!els.root) return;

            if (els.label) {
                els.label.textContent = resolveLabelForPath(targetPath || '', customLabel);
            }

            els.root.style.display = 'flex';
            els.root.setAttribute('aria-hidden', 'false');
            global.requestAnimationFrame(function () {
                if (els.root) {
                    els.root.classList.remove('opacity-0');
                    els.root.classList.add('opacity-100');
                }
            });

            if (safetyTimer) clearTimeout(safetyTimer);
            safetyTimer = setTimeout(hideNavLoader, 8000);
        }

        function hideNavLoader() {
            if (safetyTimer) {
                clearTimeout(safetyTimer);
                safetyTimer = null;
            }
            const els = getElements();
            if (!els.root) return;

            els.root.classList.remove('opacity-100');
            els.root.classList.add('opacity-0');
            els.root.setAttribute('aria-hidden', 'true');
            els.root.style.display = 'none';
        }

        // إخفاء فوري عند الرجوع بزر الخلف في أندرويد (BFCache)
        global.addEventListener('pageshow', function () {
            hideNavLoader();
        });

        // اعتراض ذكي لكافة نقرات التنقل الداخلية داخل تطبيق المكتب
        document.addEventListener('click', function (event) {
            if (event.defaultPrevented) return;
            if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

            const anchor = event.target && event.target.closest ? event.target.closest('a[href]') : null;
            if (!anchor) return;

            if (
                anchor.hasAttribute('download') ||
                anchor.getAttribute('data-no-nav-loader') !== null ||
                (anchor.target && anchor.target !== '_self')
            ) {
                return;
            }

            const rawHref = anchor.getAttribute('href') || '';
            if (
                rawHref === '' ||
                rawHref.startsWith('#') ||
                rawHref.startsWith('javascript:') ||
                rawHref.startsWith('tel:') ||
                rawHref.startsWith('mailto:')
            ) {
                return;
            }

            let targetUrl;
            try {
                targetUrl = new URL(anchor.href, global.location.origin);
            } catch (_) {
                return;
            }

            if (targetUrl.origin !== global.location.origin) return;
            if (!targetUrl.pathname.startsWith('/office')) return;

            // إذا ضغط المستخدم على نفس التبويب المفتوح حالياً لا نُظهر شاشة التحميل بل نغلق القوائم المنبثقة بهدوء
            if (
                targetUrl.pathname === global.location.pathname &&
                targetUrl.search === global.location.search
            ) {
                event.preventDefault();
                global.dispatchEvent(new CustomEvent('mursal-close-nav-menus'));
                return;
            }

            // حماية الأوفلاين: الصفحات التي تتطلب إنترنت تعرض إشعار عدم الاتصال فوراً بدلاً من تعليق اللودر
            if (global.navigator && global.navigator.onLine === false && !OFFLINE_CAPABLE_PATHS.has(targetUrl.pathname)) {
                if (typeof global.requireOnlineAction === 'function') {
                    event.preventDefault();
                    global.dispatchEvent(new CustomEvent('mursal-close-nav-menus'));
                    global.requireOnlineAction();
                    return;
                }
            }

            global.dispatchEvent(new CustomEvent('mursal-close-nav-menus'));
            const explicitLabel = anchor.getAttribute('data-nav-label') || '';
            showNavLoader(explicitLabel, targetUrl.pathname);
        }, false);

        global.MursalNavLoader = {
            show: showNavLoader,
            hide: hideNavLoader,
        };
    })(window);
</script>
