/**
 * Mursal Core PWA Engine
 * المحرك الأساسي لإدارة قواعد البيانات المحلية، طابور المزامنة، والاتصال
 * المسار: public/js/mursal-core.js
 */
(() => {
    const DB_NAME = 'MursalDB';
    const DB_VERSION = 4; // ترقية لدعم الجداول العامة

    // قائمة الجداول المطلوبة للنظام حالياً ومستقبلاً
    const STORES = {
        queue: 'syncQueue',       // طابور عام لكل الموديولات
        parcels: 'parcels',       // طرود
        // vouchers: 'vouchers',     // سندات مستقبلاً
        // shipments: 'shipments'    // شحنات مستقبلاً
    };

    const plain = (val) => JSON.parse(JSON.stringify(val));
    const nextSeq = () => performance.timeOrigin + performance.now();

    /* ------------------------------------------------------------------
     | 1. قاعدة البيانات المحلية العامة (IndexedDB)
     * ----------------------------------------------------------------*/
    const MursalDB = {
        db: null,
        async open() {
            if (this.db) return this;
            return new Promise((resolve, reject) => {
                const req = indexedDB.open(DB_NAME, DB_VERSION);

                req.onupgradeneeded = (e) => {
                    const db = req.result;
                    // إنشاء طابور المزامنة الموحد
                    if (!db.objectStoreNames.contains(STORES.queue)) {
                        db.createObjectStore(STORES.queue, { keyPath: 'seq' });
                    }
                    // إنشاء باقي الجداول ديناميكياً
                    Object.values(STORES).forEach((store) => {
                        if (store !== STORES.queue && !db.objectStoreNames.contains(store)) {
                            db.createObjectStore(store, { keyPath: 'id' });
                        }
                    });
                };

                req.onsuccess = () => {
                    this.db = req.result;
                    resolve(this);
                };
                req.onerror = () => reject(req.error);
            });
        },

        tx(storeName, mode, work) {
            return new Promise((resolve, reject) => {
                const transaction = this.db.transaction(storeName, mode);
                const request = work(transaction.objectStore(storeName));
                transaction.oncomplete = () => resolve(request?.result);
                transaction.onerror = () => reject(transaction.error);
                transaction.onabort = () => reject(transaction.error);
            });
        },

        async getAll(storeName) {
            return (await this.tx(storeName, 'readonly', (s) => s.getAll())) || [];
        },

        put(storeName, value) {
            return this.tx(storeName, 'readwrite', (s) => s.put(plain(value)));
        },

        putMany(storeName, values) {
            return this.tx(storeName, 'readwrite', (s) => {
                values.forEach((v) => s.put(plain(v)));
            });
        },

        delete(storeName, key) {
            return this.tx(storeName, 'readwrite', (s) => s.delete(key));
        },

        deleteMany(storeName, keys) {
            return this.tx(storeName, 'readwrite', (s) => {
                keys.forEach((k) => s.delete(k));
            });
        },

        clear(storeName) {
            return this.tx(storeName, 'readwrite', (s) => s.clear());
        }
    };

    /* ------------------------------------------------------------------
     | 2. طابور المزامنة العام (Generic Sync Queue)
     * ----------------------------------------------------------------*/
    const MursalSync = {
        isSyncing: false,

        get csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content;
        },

        // تسجيل عملية لأي موديول في الطابور
        async enqueue(endpoint, payload) {
            const entry = {
                seq: nextSeq(),
                endpoint,
                payload: plain(payload),
                created_at: new Date().toISOString()
            };
            await MursalDB.put(STORES.queue, entry);

            // إشعار الـ Service Worker
            if ('serviceWorker' in navigator && 'SyncManager' in window) {
                navigator.serviceWorker.ready.then((reg) => reg.sync.register('sync-parcels-queue')).catch(() => {});
            }

            // محاولة المزامنة الفورية إذا كان الاتصال متوفراً
            this.process();
            return entry;
        },

        // معالجة الطابور بشكل عام
        async process(onSessionExpired) {
            if (this.isSyncing || !navigator.onLine) return;

            const queue = await MursalDB.getAll(STORES.queue);
            if (queue.length === 0) return;

            this.isSyncing = true;

            for (const item of queue) {
                try {
                    const res = await fetch(item.endpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken
                        },
                        body: JSON.stringify(item.payload)
                    });

                    if (res.status === 401 || res.status === 419) {
                        this.isSyncing = false;
                        if (typeof onSessionExpired === 'function') onSessionExpired();
                        return; // توقف حتى يتم تسجيل الدخول
                    }

                    if (res.ok) {
                        await MursalDB.delete(STORES.queue, item.seq);
                    }
                } catch (err) {
                    console.warn('Network sync paused:', err);
                    break; // توقف عند انقطاع الاتصال
                }
            }
            this.isSyncing = false;
        }
    };

    /* ------------------------------------------------------------------
     | 3. الحالة الأساسية المشتركة لواجهات المكتب (officeShell)
     * ----------------------------------------------------------------*/
    function officeShell() {
        return {
            isOnline: navigator.onLine,
            authRequired: false,
            authModal: {
                open: false,
            },

            init() {
                this.watchConnection(() => this.syncData());

                // استقبال إشعار المزامنة التلقائية من الـ Service Worker
                if ('serviceWorker' in navigator) {
                    navigator.serviceWorker.addEventListener('message', (event) => {
                        if (event.data?.type === 'TRIGGER_SYNC') {
                            this.syncData();
                        }
                    });
                }
            },

            watchConnection(onReconnect = () => {}) {
                window.addEventListener('online', () => {
                    this.isOnline = true;
                    onReconnect();
                });
                window.addEventListener('offline', () => {
                    this.isOnline = false;
                });
            },

            syncData() {
                // يعاد تعريفها داخل كل موديول (مثل parcelApp)، وهنا كحل افتراضي
                window.location.reload();
            },

            handleSessionExpired() {
                this.authRequired = true;
                if (this.isSyncing !== undefined) this.isSyncing = false;
                this.authModal.open = true;
            },
        };
    }

    // إتاحة الأدوات والمكون الأساسي للـ Window
    window.MursalDB = MursalDB;
    window.MursalSync = MursalSync;
    window.MURSAL_STORES = STORES;
    window.officeShell = officeShell;
})();