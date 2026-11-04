/**
 * Mursal Accountant Core PWA Engine
 * المحرك المستقل لتطبيق المحاسبين: قاعدة بيانات محلية معزولة (MursalAccountantDB) وطابور مزامنة وحالة الاتصال
 * المسار: public/js/mursal-accountant-core.js
 */
(() => {
    const DB_NAME = 'MursalAccountantDB';
    const DB_VERSION = 1;

    // الجداول المحلية الخاصة بتطبيق المحاسب فقط (معزولة تماماً عن MursalDB الخاص بالمكاتب)
    const STORES = {
        queue: 'accountantSyncQueue', // طابور مزامنة العمليات المالية للمحاسب
        transactions: 'transactions', // السجلات والعمليات المالية
        summary: 'summary',           // ملخصات وإحصائيات المحاسب المخزنة محلياً
    };

    const plain = (val) => JSON.parse(JSON.stringify(val));
    const nextSeq = () => performance.timeOrigin + performance.now();

    /* ------------------------------------------------------------------
     | 1. قاعدة البيانات المحلية المعزولة للمحاسب (MursalAccountantDB)
     * ----------------------------------------------------------------*/
    const MursalAccountantDB = {
        db: null,
        async open() {
            if (this.db) return this;
            return new Promise((resolve, reject) => {
                const req = indexedDB.open(DB_NAME, DB_VERSION);

                req.onupgradeneeded = (e) => {
                    const db = req.result;
                    if (!db.objectStoreNames.contains(STORES.queue)) {
                        db.createObjectStore(STORES.queue, { keyPath: 'seq' });
                    }
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
            await this.open();
            return (await this.tx(storeName, 'readonly', (s) => s.getAll())) || [];
        },

        async get(storeName, key) {
            await this.open();
            return await this.tx(storeName, 'readonly', (s) => s.get(key));
        },

        async put(storeName, value) {
            await this.open();
            return this.tx(storeName, 'readwrite', (s) => s.put(plain(value)));
        },

        async putMany(storeName, values) {
            await this.open();
            return this.tx(storeName, 'readwrite', (s) => {
                values.forEach((v) => s.put(plain(v)));
            });
        },

        async delete(storeName, key) {
            await this.open();
            return this.tx(storeName, 'readwrite', (s) => s.delete(key));
        },

        async deleteMany(storeName, keys) {
            await this.open();
            return this.tx(storeName, 'readwrite', (s) => {
                keys.forEach((k) => s.delete(k));
            });
        },

        async clear(storeName) {
            await this.open();
            return this.tx(storeName, 'readwrite', (s) => s.clear());
        }
    };

    /* ------------------------------------------------------------------
     | 2. طابور المزامنة المستقل للمحاسب (MursalAccountantSync)
     * ----------------------------------------------------------------*/
    const MursalAccountantSync = {
        isSyncing: false,

        async enqueue(endpoint, method = 'POST', payload = {}) {
            await MursalAccountantDB.open();
            const item = {
                seq: nextSeq(),
                endpoint,
                method: method.toUpperCase(),
                payload: plain(payload),
                created_at: new Date().toISOString()
            };
            await MursalAccountantDB.put(STORES.queue, item);

            if ('serviceWorker' in navigator && 'SyncManager' in window) {
                navigator.serviceWorker.ready
                    .then((sw) => sw.sync.register('sync-accountant-queue'))
                    .catch(() => {});
            }
            return item;
        },

        async getQueue() {
            await MursalAccountantDB.open();
            return await MursalAccountantDB.getAll(STORES.queue);
        },

        async processQueue(onSessionExpired) {
            if (this.isSyncing || !navigator.onLine) return;
            await MursalAccountantDB.open();

            const queue = await MursalAccountantDB.getAll(STORES.queue);
            if (!queue.length) return;

            this.isSyncing = true;
            try {
                for (const item of queue) {
                    try {
                        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                        const method = (item.method || 'POST').toUpperCase();
                        const fetchOptions = {
                            method,
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': token || ''
                            }
                        };

                        if (method !== 'GET' && method !== 'HEAD' && method !== 'DELETE' && item.payload !== null && item.payload !== undefined) {
                            fetchOptions.body = JSON.stringify(item.payload);
                        }

                        const res = await fetch(item.endpoint, fetchOptions);

                        if (res.status === 401 || res.status === 419) {
                            if (typeof onSessionExpired === 'function') onSessionExpired();
                            return;
                        }

                        const isNonRetryable = (res.status === 400 || res.status === 422 || res.status === 404);
                        if (res.ok || isNonRetryable) {
                            await MursalAccountantDB.delete(STORES.queue, item.seq);
                        } else {
                            break;
                        }
                    } catch (err) {
                        console.warn('[MursalAccountantSync] توقفت المزامنة مؤقتاً:', err);
                        break;
                    }
                }
            } finally {
                this.isSyncing = false;
            }
        }
    };

    /* ------------------------------------------------------------------
     | 3. الغلاف التفاعلي لواجهات المحاسب (accountantShell)
     * ----------------------------------------------------------------*/
    function accountantShell() {
        return {
            isOnline: navigator.onLine,
            isSyncing: false,
            search: '',
            syncQueue: [],
            authModal: {
                open: false,
            },
            toast: {
                show: false,
                message: '',
                isError: false,
            },

            async init() {
                try {
                    await MursalAccountantDB.open();
                    this.syncQueue = await MursalAccountantSync.getQueue();
                } catch (e) {
                    console.warn('[MursalAccountantDB] تعذر فتح قاعدة البيانات المحلية:', e);
                }

                window.addEventListener('online', async () => {
                    this.isOnline = true;
                    this.showToast('تم استعادة الاتصال بالإنترنت.', false);
                    await this.syncData();
                });

                window.addEventListener('offline', () => {
                    this.isOnline = false;
                    this.showToast('انقطع الاتصال بالإنترنت، تعمل الآن في وضع عدم الاتصال.', true);
                });

                if ('serviceWorker' in navigator) {
                    navigator.serviceWorker.addEventListener('message', (event) => {
                        if (event.data && event.data.type === 'TRIGGER_ACCOUNTANT_SYNC') {
                            this.syncData();
                        }
                    });
                }
            },

            showToast(message, isError = false) {
                this.toast = { show: true, message, isError };
                setTimeout(() => {
                    if (this.toast) this.toast.show = false;
                }, 4000);
            },

            showFeedback(message, isError = false) {
                this.showToast(message, isError);
            },

            async syncData() {
                if (!navigator.onLine) {
                    this.showToast('أنت غير متصل بالإنترنت حالياً.', true);
                    return;
                }
                this.isSyncing = true;
                try {
                    await MursalAccountantSync.processQueue(() => {
                        this.authModal.open = true;
                    });
                    this.syncQueue = await MursalAccountantSync.getQueue();
                } finally {
                    this.isSyncing = false;
                }
            },

            async refreshData() {
                await this.syncData();
                if (!this.authModal.open) {
                    this.isSyncing = true;
                    window.location.reload();
                }
            }
        };
    }

    // تصدير الكائنات للنطاق العام لتطبيق المحاسب فقط
    window.MursalAccountantDB = MursalAccountantDB;
    window.MursalAccountantSync = MursalAccountantSync;
    window.MursalAccountantStores = STORES;
    window.accountantShell = accountantShell;
})();

