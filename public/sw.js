const CACHE_NAME = 'mursal-pwa-v23';

const ASSETS_TO_CACHE = [
    '/office/dashboard',
    '/office/parcels',
    '/office/favorite-customers',
    '/office/shipments/sent',
    '/manifest.json',
    '/js/tailwind.js',
    '/js/mursal-core.js?v=22',
    '/js/shipment-manifest-print.js?v=22',
    'https://fonts.googleapis.com/css2?family=Almarai:wght@400;700;800&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=swap',
    'https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js'
];

const NAV_TIMEOUT_MS = 1500;

async function matchCachedNavigation(request, pathname) {
    const exact = await caches.match(request);
    if (exact) return exact;
    if (pathname) {
        const byPath = await caches.match(pathname);
        if (byPath) return byPath;
    }
    return null;
}

function storeCachedNavigation(request, pathname, networkResponse) {
    if (!networkResponse || networkResponse.status !== 200) return;
    const cloneForReq = networkResponse.clone();
    const cloneForPath = pathname ? networkResponse.clone() : null;
    caches.open(CACHE_NAME).then((cache) => {
        cache.put(request, cloneForReq);
        if (pathname && cloneForPath) {
            cache.put(pathname, cloneForPath);
        }
    }).catch(() => {});
}

// 1. التثبيت وحفظ الأصول الأساسية
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(async (cache) => {
            for (const url of ASSETS_TO_CACHE) {
                try {
                    await cache.add(url);
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

// 3. الاستجابة السريعة ودعم الأوفلاين الفوري بدون تأخير أول نقرة
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // عزل تام عن مسارات تطبيق المحاسب المستقل (/accountant) ولوحة الأدمن (/admin)
    if (
        url.pathname.startsWith('/accountant') ||
        url.pathname.startsWith('/admin')
    ) {
        return;
    }

    // استثناء طلبات غير GET وطلبات الدخول والخروج والـ API والواتساب وطلبات AJAX/JSON الحية ومسارات الصادر والشحنات (Online-First)
    const acceptHeader = event.request.headers.get('Accept') || '';
    const isAjaxJson = acceptHeader.includes('application/json') ||
        event.request.headers.get('X-Requested-With') === 'XMLHttpRequest';

    // السماح بكاش App Shell لصفحة الشحنات المرسلة (/office/shipments/sent) فقط عند طلب تنقل HTML
    const isSentShipmentsAppShell =
        event.request.method === 'GET' &&
        !isAjaxJson &&
        event.request.mode === 'navigate' &&
        url.pathname === '/office/shipments/sent';

    if (
        event.request.method !== 'GET' ||
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

    // لصفحات التنقل (HTML Navigation): مسار أوفلاين فوري (<5ms) + سباق شبكة بمهلة 1.5 ثانية لمنع تعليق أول تنقل بعد فصل الإنترنت
    if (event.request.mode === 'navigate') {
        event.respondWith((async () => {
            const cachedShell = await matchCachedNavigation(event.request, url.pathname);

            // أ) إذا كان المتصفح يقرأ حالة عدم الاتصال صراحةً: إرجاع فوري من الكاش بدون استدعاء fetch()
            if (self.navigator && self.navigator.onLine === false) {
                if (cachedShell) {
                    return cachedShell;
                }
                return (await caches.match('/office/parcels')) || fetch(event.request);
            }

            // ب) إذا توفرت نسخة مخبأة (App Shell): سباق الشبكة مع مهلة 1500ms لمنع تعليق أول نقرة عند انقطاع النت الفعلي (Lie-Fi)
            if (cachedShell) {
                const networkPromise = fetch(event.request)
                    .then((networkResponse) => {
                        if (networkResponse && networkResponse.status === 200) {
                            storeCachedNavigation(event.request, url.pathname, networkResponse);
                        }
                        return networkResponse;
                    });

                const timeoutPromise = new Promise((resolve) => {
                    setTimeout(() => resolve(null), NAV_TIMEOUT_MS);
                });

                try {
                    const winner = await Promise.race([
                        networkPromise.catch(() => null),
                        timeoutPromise,
                    ]);

                    if (winner) {
                        return winner;
                    }

                    // تجاوزت الشبكة 1.5 ثانية أو فشلت: تقديم الكاش فوراً مع استمرار تحديث الكاش بالخلفية إن اكتمل الطلب لاحقاً
                    networkPromise.catch(() => {});
                    return cachedShell;
                } catch (_) {
                    return cachedShell;
                }
            }

            // ج) لا توجد نسخة في الكاش بعد: جلب طبيعي من الشبكة مع حفظها
            try {
                const networkResponse = await fetch(event.request);
                if (networkResponse && networkResponse.status === 200) {
                    storeCachedNavigation(event.request, url.pathname, networkResponse);
                }
                return networkResponse;
            } catch (_) {
                return (await matchCachedNavigation(event.request, url.pathname))
                    || (await caches.match('/office/parcels'));
            }
        })());
        return;
    }

    // للأصول الثابتة (Static Assets): Cache-First
    event.respondWith(
        caches.match(event.request).then((cachedResponse) => {
            if (self.navigator && self.navigator.onLine === false && cachedResponse) {
                return cachedResponse;
            }

            const fetchPromise = fetch(event.request)
                .then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200) {
                        const responseClone = networkResponse.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, responseClone));
                    }
                    return networkResponse;
                })
                .catch(() => {
                    // ignore
                });

            return cachedResponse || fetchPromise;
        })
    );
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
