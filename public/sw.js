const CACHE_NAME = 'mursal-pwa-v11';

const ASSETS_TO_CACHE = [
    '/office/dashboard',
    '/office/parcels',
    '/manifest.json',
    '/js/tailwind.js',
    '/js/mursal-core.js',
    'https://fonts.googleapis.com/css2?family=Almarai:wght@400;700;800&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=swap',
    'https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js'
];

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

// 2. التفعيل وحذف النسخ القديمة فوراً
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.map((k) => k !== CACHE_NAME ? caches.delete(k) : null)
            );
        })
    );
    self.clients.claim();
});

// 3. الاستجابة السريعة ودعم الأوفلاين
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // استثناء طلبات غير GET وطلبات الدخول والخروج والـ API والواتساب
    if (
        event.request.method !== 'GET' ||
        url.pathname.includes('/login') ||
        url.pathname.includes('/logout') ||
        url.pathname.includes('/api/') ||
        url.pathname.includes('/whatsapp')
    ) {
        return; // تذهب مباشرة إلى السيرفر
    }

    // لصفحات التنقل (HTML Navigation): Network-First للحصول دائماً على أحدث هيدر وتحديثات، مع الرجوع للكاش عند انقطاع النت
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request)
                .then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200) {
                        const responseClone = networkResponse.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, responseClone));
                    }
                    return networkResponse;
                })
                .catch(() => {
                    return caches.match(event.request).then((cached) => {
                        return cached || caches.match('/office/parcels');
                    });
                })
        );
        return;
    }

    // للأصول الثابتة (Static Assets): Cache-First
    event.respondWith(
        caches.match(event.request).then((cachedResponse) => {
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