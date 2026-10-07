const CACHE_NAME = 'mursal-pwa-v3';

const ASSETS_TO_CACHE = [
    '/office/parcels',
    '/manifest.json',
    'https://fonts.googleapis.com/css2?family=Almarai:wght@400;700;800&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=swap',
    'https://cdn.tailwindcss.com',
    'https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js'
];

// 1. التثبيت الآمن (لا يتوقف إذا فشل أحد الملفات)
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

// 2. تفعيل وحذف النسخ السابقة
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

// 3. دعم الأوفلاين عند قطع الاتصال
self.addEventListener('fetch', (event) => {
    // عدم اعتراض مسارات الـ API أو الطلبات بخلاف GET
    if (event.request.method !== 'GET' || event.request.url.includes('/api/')) {
        return;
    }

    event.respondWith(
        fetch(event.request)
            .then((networkResponse) => {
                if (networkResponse.status === 200) {
                    const responseClone = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(event.request, responseClone));
                }
                return networkResponse;
            })
            .catch(async () => {
                // محاولة إيجاد الملف المطلوب في الكاش
                const cachedResponse = await caches.match(event.request);
                if (cachedResponse) {
                    return cachedResponse;
                }

                // إذا طلب المتصفح صفحة تنقلية والنت مقطوع، نفتح صفحة الطرود المحفوظة
                if (event.request.mode === 'navigate') {
                    return caches.match('/office/parcels');
                }
            })
    );
});

// 4. مزامنة بالخلفية فور عودة النت
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-parcels-queue') {
        event.waitUntil(
            self.clients.matchAll().then((clients) => {
                clients.forEach((client) => client.postMessage({ type: 'TRIGGER_SYNC' }));
            })
        );
    }
});