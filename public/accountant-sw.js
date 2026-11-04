const CACHE_NAME = 'mursal-accountant-pwa-v1';
const CACHE_PREFIX = 'mursal-accountant-pwa-';

const ASSETS_TO_CACHE = [
    '/accountant/dashboard',
    '/accountant/login',
    '/accountant-manifest.json',
    '/js/tailwind.js',
    '/js/mursal-accountant-core.js',
    'https://fonts.googleapis.com/css2?family=Almarai:wght@400;700;800&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=swap',
    'https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js'
];

// 1. تثبيت Service Worker الخاص بتطبيق المحاسب وحفظ الأصول الأساسية
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(async (cache) => {
            for (const url of ASSETS_TO_CACHE) {
                try {
                    await cache.add(url);
                } catch (err) {
                    console.warn('[Accountant SW] تعذر كاش الرابط مؤقتاً:', url);
                }
            }
        })
    );
    self.skipWaiting();
});

// 2. التفعيل وحذف نسخ كاش المحاسب القديمة فقط (دون المساس بكاش تطبيق المكاتب)
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.map((k) => (k.startsWith(CACHE_PREFIX) && k !== CACHE_NAME) ? caches.delete(k) : null)
            );
        })
    );
    self.clients.claim();
});

// 3. الاستجابة السريعة ودعم الأوفلاين المعزول لنطاق /accountant/
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // عدم اعتراض أي مسار يخص المكاتب أو الأدمن
    if (url.pathname.startsWith('/office') || url.pathname.startsWith('/admin')) {
        return;
    }

    // استثناء طلبات غير GET وطلبات الدخول والخروج والـ API
    if (
        event.request.method !== 'GET' ||
        url.pathname.includes('/login') ||
        url.pathname.includes('/logout') ||
        url.pathname.includes('/api/')
    ) {
        return;
    }

    // لصفحات التنقل داخل تطبيق المحاسب: Network-First مع الرجوع للكاش عند انقطاع الإنترنت
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
                        return cached || caches.match('/accountant/dashboard');
                    });
                })
        );
        return;
    }

    // للأصول الثابتة (Static Assets): Cache-First مع تحديث بالخلفية
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
                    // تجاهل أخطاء الشبكة في الأوفلاين للأصول الثابتة
                });

            return cachedResponse || fetchPromise;
        })
    );
});

// 4. المزامنة بالخلفية الخاصة بتطبيق المحاسب
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-accountant-queue') {
        event.waitUntil(
            self.clients.matchAll().then((clients) => {
                clients.forEach((client) => client.postMessage({ type: 'TRIGGER_ACCOUNTANT_SYNC' }));
            })
        );
    }
});

