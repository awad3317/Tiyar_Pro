const CACHE_NAME = 'mursal-pwa-v27';

const GOOGLE_FONTS_CSS = 'https://fonts.googleapis.com/css2?family=Almarai:wght@400;700;800&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=swap';

const ASSETS_TO_CACHE = [
    '/office/dashboard',
    '/office/parcels',
    '/office/favorite-customers',
    '/office/shipments/sent',
    '/manifest.json',
    '/js/tailwind.js',
    '/js/mursal-core.js?v=22',
    '/js/shipment-manifest-print.js?v=23',
    '/js/html-to-image.min.js?v=27',
    '/js/receipt-image-share.js?v=27',
    GOOGLE_FONTS_CSS,
    'https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js',
    'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js'
];

// الصفحات التي تعتمد كلياً على IndexedDB (MursalDB) + مزامنة خلفية: نقدم الـ App Shell من الكاش فوراً (<5ms) ونحدّثه بالخلفية
const SWR_OFFLINE_FIRST_TABS = new Set([
    '/office/parcels',
    '/office/shipments/sent',
    '/office/favorite-customers',
]);

// نطاقات الـ CDN الموثوقة (الخطوط والأيقونات و Alpine و QR): Cache-First مع فشل سريع عند انقطاع الإنترنت
const TRUSTED_CDN_ORIGINS = new Set([
    'https://fonts.googleapis.com',
    'https://fonts.gstatic.com',
    'https://cdn.jsdelivr.net',
    'https://unpkg.com',
]);

const STATIC_EXT_RE = /\.(?:js|mjs|css|woff2?|ttf|otf|eot|png|jpe?g|gif|webp|svg|ico|json)$/i;
const NAV_TIMEOUT_MS = 700;
const CDN_TIMEOUT_MS = 2500;
const BG_REFRESH_TIMEOUT_MS = 4000;

function isOfflineNow() {
    return Boolean(self.navigator && self.navigator.onLine === false);
}

function isCacheableResponse(response) {
    return Boolean(response && (response.status === 200 || response.type === 'opaque'));
}

function isSameOriginStaticAsset(url) {
    if (url.origin !== self.location.origin) return false;
    return (
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/js/') ||
        url.pathname.startsWith('/css/') ||
        url.pathname.startsWith('/assets/') ||
        url.pathname.startsWith('/icons/') ||
        url.pathname === '/manifest.json' ||
        STATIC_EXT_RE.test(url.pathname)
    );
}

function fetchWithTimeout(request, timeoutMs) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);
    return fetch(request, { signal: controller.signal })
        .finally(() => clearTimeout(timer));
}

async function matchCachedNavigation(request, pathname) {
    const exact = await caches.match(request, { ignoreVary: true });
    if (exact) return exact;
    if (pathname) {
        const byPath = await caches.match(pathname, { ignoreVary: true });
        if (byPath) return byPath;
    }
    return null;
}

function storeCachedNavigation(request, pathname, networkResponse) {
    if (!networkResponse || networkResponse.status !== 200) return Promise.resolve();
    const cloneForReq = networkResponse.clone();
    const cloneForPath = pathname ? networkResponse.clone() : null;
    return caches.open(CACHE_NAME).then((cache) => {
        const tasks = [cache.put(request, cloneForReq)];
        if (pathname && cloneForPath) {
            tasks.push(cache.put(pathname, cloneForPath));
        }
        return Promise.all(tasks);
    }).catch(() => {});
}

function storeCachedAsset(request, networkResponse) {
    if (!isCacheableResponse(networkResponse)) return Promise.resolve();
    const responseClone = networkResponse.clone();
    return caches.open(CACHE_NAME)
        .then((cache) => cache.put(request, responseClone))
        .catch(() => {});
}

// 1. التثبيت وحفظ الأصول الأساسية + ملفات الخطوط والأيقونات (.woff2) بشكل فردي آمن
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(async (cache) => {
            for (const url of ASSETS_TO_CACHE) {
                try {
                    const res = await fetchWithTimeout(url, 6000);
                    if (!isCacheableResponse(res)) continue;

                    await cache.put(url, res.clone());

                    // كاش ملفات الخطوط والأيقونات الفعلية (fonts.gstatic.com/*.woff2) لضمان عرض الأيقونات والخط العربي فوراً أوفلاين
                    if (url === GOOGLE_FONTS_CSS && res.status === 200 && res.type !== 'opaque') {
                        const cssText = await res.text();
                        const fontUrls = Array.from(
                            new Set(cssText.match(/https:\/\/fonts\.gstatic\.com\/[^)"'\s]+/g) || [])
                        );
                        for (const fontUrl of fontUrls) {
                            try {
                                const fontRes = await fetchWithTimeout(fontUrl, 5000);
                                if (isCacheableResponse(fontRes)) {
                                    await cache.put(fontUrl, fontRes);
                                }
                            } catch (_) {
                                // تجاهل فشل أي ملف خط فردي حتى لا يتعطل التثبيت
                            }
                        }
                    }
                } catch (err) {
                    console.warn('تعذر كاش الرابط مؤقتاً:', url);
                }
            }
        })
    );
    self.skipWaiting();
});

// 2. التفعيل وحذف النسخ القديمة فوراً (دون المساس بكاش تطبيق المحاسبين المستقل)
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.map((k) => (k.startsWith('mursal-pwa-') && k !== CACHE_NAME) ? caches.delete(k) : null)
            );
        })
    );
    self.clients.claim();
});

// 3. الاستجابة الفورية للأصول والخطوط والـ App Shell دون أي تعليق عند انقطاع الإنترنت
self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    const url = new URL(event.request.url);
    const isSameOrigin = url.origin === self.location.origin;

    // أ) نطاقات الـ CDN الموثوقة (Google Fonts, Material Symbols, Alpine, html5-qrcode): Cache-First فوري (<2ms)
    if (!isSameOrigin) {
        if (!TRUSTED_CDN_ORIGINS.has(url.origin)) {
            return;
        }

        event.respondWith((async () => {
            const cached = await caches.match(event.request, { ignoreVary: true });
            if (cached) {
                // عند توفر الاتصال فقط نحدّث النسخة بالخلفية لملفات CSS/JS غير المثبّتة بإصدار دون تأخير الرد الفوري
                if (!isOfflineNow() && url.origin === 'https://fonts.googleapis.com') {
                    event.waitUntil(
                        fetchWithTimeout(event.request, BG_REFRESH_TIMEOUT_MS)
                            .then((res) => storeCachedAsset(event.request, res))
                            .catch(() => {})
                    );
                }
                return cached;
            }

            // فشل سريع فوري عند عدم الاتصال حتى لا يعلق المتصفح 5-15 ثانية على مهلة TCP
            if (isOfflineNow()) {
                return new Response('', { status: 504, statusText: 'Offline' });
            }

            try {
                const netRes = await fetchWithTimeout(event.request, CDN_TIMEOUT_MS);
                if (isCacheableResponse(netRes)) {
                    event.waitUntil(storeCachedAsset(event.request, netRes.clone()));
                }
                return netRes;
            } catch (_) {
                return new Response('', { status: 504, statusText: 'CDN Timeout' });
            }
        })());
        return;
    }

    // ب) عزل تام عن مسارات تطبيق المحاسب المستقل (/accountant) ولوحة الأدمن (/admin)
    if (
        url.pathname.startsWith('/accountant') ||
        url.pathname.startsWith('/admin')
    ) {
        return;
    }

    // ج) الأصول المحلية الثابتة (JS/CSS/Fonts/Icons/Images/Manifest): Cache-First صارم (<2ms)
    if (isSameOriginStaticAsset(url)) {
        event.respondWith((async () => {
            const cached = await caches.match(event.request, { ignoreVary: true });
            if (cached) {
                if (!isOfflineNow() && !url.searchParams.has('v')) {
                    event.waitUntil(
                        fetchWithTimeout(event.request, BG_REFRESH_TIMEOUT_MS)
                            .then((res) => storeCachedAsset(event.request, res))
                            .catch(() => {})
                    );
                }
                return cached;
            }

            if (isOfflineNow()) {
                return new Response('', { status: 504, statusText: 'Offline' });
            }

            try {
                const netRes = await fetchWithTimeout(event.request, CDN_TIMEOUT_MS);
                if (isCacheableResponse(netRes)) {
                    event.waitUntil(storeCachedAsset(event.request, netRes.clone()));
                }
                return netRes;
            } catch (_) {
                return new Response('', { status: 504, statusText: 'Asset Timeout' });
            }
        })());
        return;
    }

    // د) استثناء صارم لجميع طلبات JSON/AJAX والـ API ومسارات الأونلاين الحية (Network-Only بدون أي كاش)
    const acceptHeader = event.request.headers.get('Accept') || '';
    const isAjaxJson = acceptHeader.includes('application/json') ||
        event.request.headers.get('X-Requested-With') === 'XMLHttpRequest';

    const isSentShipmentsAppShell =
        !isAjaxJson &&
        event.request.mode === 'navigate' &&
        url.pathname === '/office/shipments/sent';

    if (
        isAjaxJson ||
        url.pathname.startsWith('/office/outgoing-parcels') ||
        url.pathname.startsWith('/office/transit-parcels') ||
        (url.pathname.startsWith('/office/shipments') && !isSentShipmentsAppShell) ||
        url.pathname.startsWith('/office/global-search') ||
        url.pathname.includes('/login') ||
        url.pathname.includes('/logout') ||
        url.pathname.includes('/api/') ||
        url.pathname.includes('/whatsapp')
    ) {
        return; // تذهب مباشرة إلى السيرفر بدون كاش
    }

    // هـ) صفحات التنقل (HTML App Shell)
    if (event.request.mode === 'navigate') {
        event.respondWith((async () => {
            const cachedShell = await matchCachedNavigation(event.request, url.pathname);

            // 1. مسار أوفلاين فوري (<5ms): بدون أي استدعاء fetch()
            if (isOfflineNow()) {
                if (cachedShell) return cachedShell;
                return (await caches.match('/office/parcels', { ignoreVary: true })) || fetch(event.request);
            }

            // 2. تبويبات Offline-First المعتمدة على IndexedDB (الطرود، الشحنات المرسلة، المميزون):
            //    Stale-While-Revalidate = فتح فوري (<5ms) من الكاش + تحديث صامت للكاش بالخلفية مع AbortController
            if (cachedShell && SWR_OFFLINE_FIRST_TABS.has(url.pathname)) {
                event.waitUntil(
                    fetchWithTimeout(event.request, BG_REFRESH_TIMEOUT_MS)
                        .then((netRes) => storeCachedNavigation(event.request, url.pathname, netRes))
                        .catch(() => {})
                );
                return cachedShell;
            }

            // 3. باقي الصفحات المخبأة (مثل /office/dashboard):
            //    Network-First بمهلة صارمة 700ms عبر AbortController لتفادي أي تعليق في Lie-Fi مع إلغاء الطلب المعلّق فوراً
            if (cachedShell) {
                try {
                    const netRes = await fetchWithTimeout(event.request, NAV_TIMEOUT_MS);
                    if (netRes && netRes.status === 200) {
                        event.waitUntil(storeCachedNavigation(event.request, url.pathname, netRes.clone()));
                        return netRes;
                    }
                    return cachedShell;
                } catch (_) {
                    return cachedShell;
                }
            }

            // 4. أول زيارة لصفحة غير مخبأة بعد
            try {
                const netRes = await fetch(event.request);
                if (netRes && netRes.status === 200) {
                    event.waitUntil(storeCachedNavigation(event.request, url.pathname, netRes.clone()));
                }
                return netRes;
            } catch (_) {
                return (await matchCachedNavigation(event.request, url.pathname))
                    || (await caches.match('/office/parcels', { ignoreVary: true }));
            }
        })());
        return;
    }

    // و) أي أصول متبقية من نفس النطاق: Cache-First
    event.respondWith((async () => {
        const cached = await caches.match(event.request, { ignoreVary: true });
        if (cached) return cached;
        if (isOfflineNow()) {
            return new Response('', { status: 504, statusText: 'Offline' });
        }
        try {
            const netRes = await fetchWithTimeout(event.request, CDN_TIMEOUT_MS);
            if (isCacheableResponse(netRes)) {
                event.waitUntil(storeCachedAsset(event.request, netRes.clone()));
            }
            return netRes;
        } catch (_) {
            return new Response('', { status: 504, statusText: 'Timeout' });
        }
    })());
});

// 4. المزامنة بالخلفية
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-parcels-queue') {
        event.waitUntil(
            self.clients.matchAll().then((clients) => {
                clients.forEach((client) => client.postMessage({ type: 'TRIGGER_SYNC' }));
            })
        );
    }
});
