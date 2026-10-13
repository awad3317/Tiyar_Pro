/**
 * Mursal Core PWA Engine
 * المحرك الأساسي لإدارة قواعد البيانات المحلية، طابور المزامنة، والاتصال
 * المسار: public/js/mursal-core.js
 */
(() => {
    const DB_NAME = 'MursalDB';
    const DB_VERSION = 5; // ترقية لدعم الجداول العامة

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
   /* ------------------------------------------------------------------
     | 2. طابور المزامنة العام (Generic Sync Queue)
     * ----------------------------------------------------------------*/
    const MursalSync = {
        isSyncing: false,

        get csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
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

            // إشعار الـ Service Worker إن وجد
            if ('serviceWorker' in navigator && 'SyncManager' in window) {
                navigator.serviceWorker.ready.then((reg) => reg.sync.register('sync-parcels-queue')).catch(() => {});
            }

            return entry;
        },

        // معالجة الطابور بشكل عام
        async process(onSessionExpired) {
            if (this.isSyncing || !navigator.onLine) return;

            const queue = await MursalDB.getAll(STORES.queue);
            if (queue.length === 0) return;

            this.isSyncing = true;

            try {
                for (const item of queue) {
                    try {
                        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                        const res = await fetch(item.endpoint, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': token || ''
                            },
                            body: JSON.stringify(item.payload)
                        });

                        if (res.status === 401 || res.status === 419) {
                            if (typeof onSessionExpired === 'function') onSessionExpired();
                            return;
                        }

                        if (res.ok) {
                            await MursalDB.delete(STORES.queue, item.seq);
                        } else {
                            console.error('خطأ مزامنة السيرفر:', await res.json().catch(() => ({})));
                            break;
                        }
                    } catch (err) {
                        console.warn('Network sync paused:', err);
                        break;
                    }
                }
            } finally {
                this.isSyncing = false;
            }
        }
    };
    /* ------------------------------------------------------------------
     | 3. الحالة الأساسية المشتركة لواجهات المكتب (officeShell)
     * ----------------------------------------------------------------*/
    function officeShell() {
        return {
            isOnline: navigator.onLine,
            authRequired: false,
            syncQueue: [], // 👈 إضافة هذا المتغير ليكون متاحاً في الهيدر بكل الصفحات
            authModal: {
                open: false,
            },

            // حالة الإرسال السريع لرسائل SMS (كشف جملة)
            quickSms: {
                open: false,
                step: 'input',       // input | streaming
                rawText: '',
                items: [],
                isSending: false,
                successCount: 0,
                // حقول الإدخال التدريجي الجديدة
                phone: '',
                description: 'طرد',
            },

            // إشعار التنبيهات
            smsFeedback: {
                show: false,
                message: '',
                isError: false,
            },

            openQuickSmsModal() {
                this.quickSms.open = true;
                this.quickSms.step = 'input';
                this.quickSms.phone = '';
                this.quickSms.description = 'طرد';
                // autofocus على حقل الهاتف بعد فتح المودال
                this.$nextTick(() => {
                    const phoneInput = document.getElementById('streaming-phone-input');
                    if (phoneInput) phoneInput.focus();
                });
            },

            openQuickSms() {
                this.openQuickSmsModal();
            },

            resetQuickSms() {
                this.quickSms.open = false;
                this.quickSms.step = 'input';
                this.quickSms.rawText = '';
                this.quickSms.items = [];
                this.quickSms.isSending = false;
                this.quickSms.successCount = 0;
                this.quickSms.phone = '';
                this.quickSms.description = 'طرد';
            },

            showFeedback(message, isError = false) {
                this.smsFeedback = { show: true, message, isError };
                setTimeout(() => {
                    if (this.smsFeedback) this.smsFeedback.show = false;
                }, 4000);
            },

            async pasteFromClipboard() {
                try {
                    if (navigator.clipboard && navigator.clipboard.readText) {
                        const text = await navigator.clipboard.readText();
                        if (text) {
                            const current = (this.quickSms.rawText || '').trimEnd();
                            const combined = current ? current + '\n' + text.trim() : text.trim();
                            const matches = [...this.normalizeText(combined).matchAll(/(7[01378]\d{7})/g)];
                            if (matches.length > 15) {
                                const cutoffIndex = matches[15].index;
                                this.quickSms.rawText = combined.substring(0, cutoffIndex).trimEnd();
                                this.showFeedback('تم أخذ أول 15 طرداً فقط (الحد الأقصى لكل إرسالية).', true);
                            } else {
                                this.quickSms.rawText = combined;
                            }
                        }
                    } else {
                        this.showFeedback('خاصية اللصق التلقائي غير مدعومة في المتصفح، يرجى اللصق يدوياً.', true);
                    }
                } catch (err) {
                    console.warn('Clipboard read error:', err);
                    this.showFeedback('يرجى منح إذن الوصول للحافظة أو اللصق يدوياً في الحقل.', true);
                }
            },

            handleTextInput(event) {
                if (event && event.target && event.target.value !== undefined) {
                    this.quickSms.rawText = event.target.value;
                }
                const raw = this.quickSms?.rawText || '';
                const matches = [...this.normalizeText(raw).matchAll(/(7[01378]\d{7})/g)];
                if (matches.length > 15) {
                    const cutoffIndex = matches[15].index;
                    this.quickSms.rawText = raw.substring(0, cutoffIndex).trimEnd();
                    if (event && event.target) {
                        event.target.value = this.quickSms.rawText;
                    }
                    this.showFeedback('تم الوصول للحد الأقصى (15 طرداً) لكل إرسالية. يرجى إرسال الباقي في كشف منفصل.', true);
                }
            },

            appendTag(tag) {
                if (this.detectedParcelsCount >= 15) {
                    this.showFeedback('تم الوصول للحد الأقصى (15 طرداً) لكل إرسالية.', true);
                    return;
                }
                if (!this.quickSms.rawText || this.quickSms.rawText.trim() === '') {
                    this.quickSms.rawText = tag + '\n';
                    return;
                }
                const raw = this.quickSms.rawText;
                if (raw.endsWith('\n')) {
                    this.quickSms.rawText += tag + '\n';
                } else if (raw.endsWith(' ')) {
                    this.quickSms.rawText += tag + '\n';
                } else {
                    this.quickSms.rawText += ' ' + tag + '\n';
                }
                this.handleTextInput();
            },

            normalizeText(str) {
                if (!str) return '';
                const arabicDigits = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
                const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
                let normalized = String(str)
                    .replace(/[٠-٩]/g, d => arabicDigits.indexOf(d))
                    .replace(/[۰-۹]/g, d => persianDigits.indexOf(d))
                    .replace(/[\u200B-\u200D\uFEFF\u200E\u200F]/g, '');
                return normalized;
            },

            cleanDescription(raw) {
                if (!raw) return 'طرد';
                let cleaned = raw
                    .replace(/[\r\n]+/g, ' ')
                    .replace(/^[\s\-_,:،؛.\/\\#*~=+]+/g, '')
                    .replace(/[\s\-_,:،؛.\/\\#*~=+]+$/g, '')
                    .replace(/\s+/g, ' ')
                    .trim();
                return cleaned || 'طرد';
            },

            get detectedParcelsCount() {
                const text = this.normalizeText(this.quickSms?.rawText || '');
                return (text.match(/(7[01378]\d{7})/g) || []).length;
            },

            parseQuickList() {
                const raw = this.normalizeText(this.quickSms?.rawText || '');
                const phoneRegex = /(7[01378]\d{7})/g;
                const matches = [];
                let match;

                while ((match = phoneRegex.exec(raw)) !== null) {
                    matches.push({
                        phone: match[1],
                        start: match.index,
                        end: match.index + match[1].length
                    });
                }

                if (matches.length === 0) {
                    this.showFeedback('لم يتم العثور على أرقام هواتف يمنية صالحة (مثال: 779030449).', true);
                    return;
                }

                if (matches.length > 15) {
                    this.showFeedback('الحد الأقصى المسموح به هو 15 طرداً لكل إرسالية.', true);
                    return;
                }

                const items = [];
                for (let i = 0; i < matches.length; i++) {
                    const currentMatch = matches[i];
                    const nextMatch = matches[i + 1];

                    // استخراج الوصف بين نهاية الرقم الحالي وبداية الرقم التالي (أو نهاية النص)
                    const sliceStart = currentMatch.end;
                    const sliceEnd = nextMatch ? nextMatch.start : raw.length;
                    let desc = this.cleanDescription(raw.substring(sliceStart, sliceEnd));

                    // إذا كان طرد واحد فقط والوصف مكتوب قبل الرقم (مثال: "عسل 781152674")
                    if (matches.length === 1 && desc === 'طرد') {
                        const beforeDesc = this.cleanDescription(raw.substring(0, currentMatch.start));
                        if (beforeDesc && beforeDesc !== 'طرد') {
                            desc = beforeDesc;
                        }
                    }

                    items.push({
                        phone: currentMatch.phone,
                        description: desc || 'طرد',
                    });
                }

                this.quickSms.items = items;
                this.quickSms.step = 'preview';
            },

            async submitQuickSms() {
                if (this.quickSms.items.length === 0 || this.quickSms.items.length > 15 || this.quickSms.isSending) return;

                this.quickSms.isSending = true;
                const payload = { items: this.quickSms.items };
                const count = this.quickSms.items.length;
                const endpoint = '/office/parcels/quick-send-sms';

                try {
                    if (this.isOnline) {
                        try {
                            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                            const res = await fetch(endpoint, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': token || ''
                                },
                                body: JSON.stringify(payload)
                            });
                            if (!res.ok) {
                                const data = await res.json().catch(() => ({}));
                                throw new Error(data.message || `تعذر الإرسال (رمز: ${res.status})`);
                            }
                            if (typeof this.refreshFromServer === 'function') {
                                await this.refreshFromServer();
                            }
                        } catch (err) {
                            console.warn('Online quick send failed, enqueuing to offline sync queue:', err);
                            if (window.MursalSync) {
                                await window.MursalSync.enqueue(endpoint, payload);
                                if (window.MursalDB) {
                                    this.syncQueue = await window.MursalDB.getAll(window.MURSAL_STORES.queue);
                                }
                            }
                        }
                    } else {
                        if (window.MursalSync) {
                            await window.MursalSync.enqueue(endpoint, payload);
                            if (window.MursalDB) {
                                this.syncQueue = await window.MursalDB.getAll(window.MURSAL_STORES.queue);
                            }
                        }
                    }

                    this.quickSms.successCount = count;
                    this.quickSms.step = 'report';
                    this.showFeedback(`تم تسجيل وجدولة ${count} طرد بنجاح!`, false);
                } catch (error) {
                    console.error('Submit quick SMS error:', error);
                    this.showFeedback('حدث خطأ أثناء معالجة الرسائل السريعة.', true);
                } finally {
                    this.quickSms.isSending = false;
                }
            },

            async init() {
                // فتح قاعدة البيانات أولاً
                if (window.MursalDB) {
                    await window.MursalDB.open();
                    // قراءة حالة الطابور فور فتح أي صفحة
                    this.syncQueue = await window.MursalDB.getAll(window.MURSAL_STORES.queue);
                }

                this.watchConnection(() => this.syncData());

                // استقبال إشعار المزامنة التلقائية من الـ Service Worker
                if ('serviceWorker' in navigator) {
                    navigator.serviceWorker.addEventListener('message', async (event) => {
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

            async syncData() {
                // مزامنة الطابور وتحديث العداد في أي صفحة
                if (window.MursalSync && window.MursalDB) {
                    await window.MursalSync.process(() => this.handleSessionExpired());
                    this.syncQueue = await window.MursalDB.getAll(window.MURSAL_STORES.queue);
                }
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