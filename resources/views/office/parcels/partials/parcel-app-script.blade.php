<script>
(() => {
    /* ------------------------------------------------------------------
     | الإعدادات
     * ----------------------------------------------------------------*/
    const ROUTES = {
        list: @json(route('office.api.parcels')),
        sync: @json(route('office.api.parcels.sync')),
        resendSms: (id) => `{{ url('/office/api/parcels') }}/${id}/resend-sms`,
    };

    // مصدرها الوحيد App\Enums\ParcelStatus — أي تعديل على القواعد يتم هناك فقط
    const STATUS_LABELS = @json(\App\Enums\ParcelStatus::labels());
    const TRANSITIONS   = @json(\App\Enums\ParcelStatus::transitionMap());

    const STATUS_BADGES = {
        in_office: 'bg-amber-100 text-amber-800',
        delivered: 'bg-emerald-100 text-emerald-800',
        returned:  'bg-rose-100 text-rose-800',
    };

    // أزرار الإجراءات مفهرسة بالحالة الهدف
    const ACTIONS = {
        delivered: {
            label: () => 'تسليم',
            icon: 'check_circle',
            classes: 'flex-1 bg-emerald-600 text-white',
        },
        in_office: {
            label: () => 'إرجاع للمكتب',
            icon: 'undo',
            classes: 'flex-1 border border-amber-300 text-amber-700 bg-amber-50',
        },
        returned: {
            label: (p) => 'إرجاع إلى ' + (p.source_office_name || 'المكتب المرسل'),
            icon: 'assignment_return',
            classes: 'border border-rose-200 text-rose-600',
        },
    };

    const FILTERS = [
        { key: 'all', label: 'الكل' },
        ...Object.entries(STATUS_LABELS).map(([key, label]) => ({ key, label })),
    ];

    const DB_NAME = 'MursalDB';
    const DB_VERSION = 3;
    const STORES = { parcels: 'parcels', queue: 'syncQueue' };

    const dateFormatter = new Intl.DateTimeFormat('ar-u-nu-latn', { dateStyle: 'medium', timeStyle: 'short' });

    // يحوّل كائنات Alpine التفاعلية (Proxy) إلى كائنات عادية قابلة للتخزين في IndexedDB
    const plain = (value) => JSON.parse(JSON.stringify(value));

    // مفتاح تصاعدي يحفظ ترتيب التعديلات داخل الطابور
    const nextSeq = () => performance.timeOrigin + performance.now();

    /* ------------------------------------------------------------------
     | التخزين المحلي (IndexedDB) بواجهة Promise بسيطة
     * ----------------------------------------------------------------*/
    const LocalStore = {
        db: null,

        open() {
            return new Promise((resolve, reject) => {
                const req = indexedDB.open(DB_NAME, DB_VERSION);

                req.onupgradeneeded = (event) => {
                    const db = req.result;

                    if (!db.objectStoreNames.contains(STORES.parcels)) {
                        db.createObjectStore(STORES.parcels, { keyPath: 'id' });
                    }

                    // النسخة 3: الطابور يحفظ كل تعديل بالترتيب (seq) بدلاً من آخر تعديل لكل طرد،
                    // حتى تمر الانتقالات المتتالية (تسليم → مكتب → إرجاع) على السيرفر بنفس الترتيب
                    if (event.oldVersion < 3 && db.objectStoreNames.contains(STORES.queue)) {
                        db.deleteObjectStore(STORES.queue);
                    }
                    if (!db.objectStoreNames.contains(STORES.queue)) {
                        db.createObjectStore(STORES.queue, { keyPath: 'seq' });
                    }
                };

                req.onsuccess = () => {
                    this.db = req.result;
                    resolve(this);
                };
                req.onerror = () => reject(req.error);
            });
        },

        transaction(storeName, mode, work) {
            return new Promise((resolve, reject) => {
                const tx = this.db.transaction(storeName, mode);
                const request = work(tx.objectStore(storeName));

                tx.oncomplete = () => resolve(request?.result);
                tx.onerror = () => reject(tx.error);
                tx.onabort = () => reject(tx.error);
            });
        },

        async getAll(storeName) {
            return (await this.transaction(storeName, 'readonly', (store) => store.getAll())) || [];
        },

        put(storeName, value) {
            return this.putMany(storeName, [value]);
        },

        putMany(storeName, values) {
            return this.transaction(storeName, 'readwrite', (store) => {
                values.forEach((value) => store.put(plain(value)));
            });
        },

        deleteMany(storeName, keys) {
            return this.transaction(storeName, 'readwrite', (store) => {
                keys.forEach((key) => store.delete(key));
            });
        },

        clear(storeName) {
            return this.transaction(storeName, 'readwrite', (store) => store.clear());
        },
    };

    /* ------------------------------------------------------------------
     | التواصل مع السيرفر
     * ----------------------------------------------------------------*/
    const Api = {
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.content,

        async fetchParcels() {
            const res = await fetch(ROUTES.list, { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error(`Fetch parcels failed (${res.status})`);
            return res.json();
        },

        /** إرسال طلب إعادة إرسال SMS للمستلم عبر السيرفر */
        async resendSms(parcelId) {
            const res = await fetch(ROUTES.resendSms(parcelId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                throw new Error(data.message || `تعذر الإرسال (رمز: ${res.status})`);
            }
            return data;
        },

        /** @returns {Promise<{count: number, rejected: string[]}>} */
        async sendUpdates(updates) {
            const res = await fetch(ROUTES.sync, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({ updates: plain(updates) }),
            });
            if (!res.ok) throw new Error(`Sync failed (${res.status})`);
            return res.json();
        },
    };

    /* ------------------------------------------------------------------
     | مكوّن Alpine لصفحة الطرود
     * ----------------------------------------------------------------*/
    window.parcelApp = () => ({
        ...officeShell(),

        parcels: [],
        syncQueue: [],
        search: '',
        filter: 'all',
        filters: FILTERS,
        isSyncing: false,

        // تتبع حالة إعادة إرسال رسائل SMS
        sendingSmsId: null,
        smsFeedback: { show: false, message: '', isError: false },

        // حالة مودال تأكيد الإرجاع
        returnModal: {
            open: false,
            parcel: null,
        },

        async init() {
            this.watchConnection(() => this.syncData());

            await LocalStore.open();
            await this.loadLocal();

            if (this.isOnline) {
                this.syncData();
            }

            // الاستماع لأمر المزامنة الخلفية القادم من الـ Service Worker عند عودة الاتصال
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.addEventListener('message', (event) => {
                    if (event.data?.type === 'TRIGGER_SYNC') {
                        this.syncData();
                    }
                });
            }
        },

        // ---------- إدارة مودال الإرجاع ----------

        openReturnModal(parcel) {
            this.returnModal.parcel = parcel;
            this.returnModal.open = true;
        },

        closeReturnModal() {
            this.returnModal.open = false;
            this.returnModal.parcel = null;
        },

        async confirmReturn() {
            const parcel = this.returnModal.parcel;
            if (!parcel) return;

            this.closeReturnModal();
            await this.executeStatusChange(parcel, 'returned');
        },

        // ---------- إرسال رسائل SMS المستقلة ----------

        showFeedback(message, isError = false) {
            this.smsFeedback = { show: true, message, isError };
            setTimeout(() => {
                this.smsFeedback.show = false;
            }, 4000);
        },

        async resendSms(parcel) {
            if (this.sendingSmsId) return;

            if (!this.isOnline) {
                this.showFeedback('يتطلب إرسال الـ SMS اتصالاً نشطاً بالإنترنت.', true);
                return;
            }

            this.sendingSmsId = parcel.id;
            try {
                const response = await Api.resendSms(parcel.id);
                this.showFeedback(response.message || 'تم إرسال رسالة SMS بنجاح!', false);
            } catch (err) {
                this.showFeedback(err.message || 'تعذر إرسال رسالة SMS.', true);
            } finally {
                this.sendingSmsId = null;
            }
        },

        // ---------- البيانات ----------

        /** ترتيب الطرود بحيث يظهر آخر طرد أضيف أولاً (تنازلياً) */
        sortParcels(list) {
            return [...list].sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0));
        },

        async loadLocal() {
            const [localParcels, queue] = await Promise.all([
                LocalStore.getAll(STORES.parcels),
                LocalStore.getAll(STORES.queue),
            ]);
            this.parcels = this.sortParcels(localParcels);
            this.syncQueue = queue;
        },

        /** يرسل التعديلات المعلقة أولاً ثم يجلب أحدث البيانات من السيرفر */
        async syncData() {
            await this.processSyncQueue();
            if (this.isOnline) {
                await this.refreshFromServer();
            }
        },

        /** دمج أحدث الطرود من السيرفر مع التعديلات المحلية المعلقة وتحديث الذاكرة */
        async refreshFromServer() {
            try {
                const serverParcels = await Api.fetchParcels();
                const pendingMap = new Map(this.syncQueue.map((item) => [item.id, item]));

                // دمج التعديلات المعلقة محلياً مع بيانات السيرفر
                const merged = serverParcels.map((p) => {
                    const local = pendingMap.get(p.id);
                    return local ? { ...p, status: local.status, delivered_at: local.delivered_at } : p;
                });

                // تخزين أحدث الطرود محلياً وفرزها من الأحدث للأقدم
                await LocalStore.clear(STORES.parcels);
                await LocalStore.putMany(STORES.parcels, merged);
                this.parcels = this.sortParcels(merged);
            } catch (error) {
                console.info('تعذر جلب البيانات من السيرفر، يتم الاعتماد على البيانات المحلية.', error);
            }
        },

        // ---------- تغيير الحالة والمزامنة ----------

        /** طلب تغيير الحالة (إذا كانت مرتجع نفتح المودال، وإلا ننفذ فوراً) */
        async changeStatus(parcel, status) {
            if (!this.canTransition(parcel, status)) return;

            if (status === 'returned') {
                this.openReturnModal(parcel);
                return;
            }

            await this.executeStatusChange(parcel, status);
        },

        /** تطبيق التغيير فعلياً على الواجهة وقاعدة البيانات المحلية وطابور المزامنة */
        async executeStatusChange(parcel, status) {
            const update = {
                seq: nextSeq(),
                id: parcel.id,
                status,
                delivered_at: status === 'delivered' ? new Date().toISOString() : null,
            };

            Object.assign(parcel, { status: update.status, delivered_at: update.delivered_at });

            try {
                await LocalStore.put(STORES.parcels, parcel);
            } catch (error) {
                console.warn('IndexedDB write error:', error);
            }

            await this.enqueue(update);

            // تسجيل المزامنة الخلفية مع نظام التشغيل (Service Worker SyncManager) كخدمة خلفية
            if ('serviceWorker' in navigator && 'SyncManager' in window) {
                navigator.serviceWorker.ready.then((reg) => {
                    return reg.sync.register('sync-parcels-queue');
                }).catch(() => {});
            }

            await this.processSyncQueue();
        },

        async enqueue(update) {
            this.syncQueue.push(update);

            try {
                await LocalStore.put(STORES.queue, update);
            } catch (error) {
                console.warn('IndexedDB queue write error:', error);
            }
        },

        async processSyncQueue() {
            if (this.isSyncing || !this.isOnline || this.syncQueue.length === 0) return;

            this.isSyncing = true;
            const batch = plain(this.syncQueue);
            const sentSeqs = new Set(batch.map((item) => item.seq));
            let result = null;

            try {
                result = await Api.sendUpdates(batch);

                // نحذف فقط ما أُرسل فعلاً؛ أي تعديل أُضيف أثناء الإرسال يبقى في الطابور
                await LocalStore.deleteMany(STORES.queue, [...sentSeqs]);
                this.syncQueue = this.syncQueue.filter((item) => !sentSeqs.has(item.seq));
            } catch (error) {
                console.warn('تعذرت المزامنة حالياً، ستتم المحاولة لاحقاً.', error);
            } finally {
                this.isSyncing = false;
            }

            if (!result) return;

            // السيرفر رفض انتقالاً (مثلاً تعارض مع جهاز آخر) → نعيد الحالة الصحيحة من السيرفر
            if (result.rejected?.length) {
                console.warn('رفض السيرفر بعض التعديلات:', result.rejected);
                await this.refreshFromServer();
            }

            if (this.syncQueue.length > 0) {
                await this.processSyncQueue();
            }
        },

        /** فحص هل الطرد يحتوي على تعديلات غير متزامنة مع السيرفر */
        isPending(id) {
            return this.syncQueue.some((item) => item.id === id);
        },

        // ---------- قواعد الحالة ----------

        canTransition(parcel, status) {
            return (TRANSITIONS[parcel.status] || []).includes(status);
        },
        timeAgo(dateStr) {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            const now = new Date();
            const diffSec = Math.floor((now - date) / 1000);

            if (diffSec < 60) return 'الآن';
            
            const diffMin = Math.floor(diffSec / 60);
            if (diffMin < 60) return `منذ ${diffMin} دقيقة`;
            
            const diffHours = Math.floor(diffMin / 60);
            if (diffHours < 24) return `منذ ${diffHours} ساعة`;
            
            const diffDays = Math.floor(diffHours / 24);
            if (diffDays === 1) return 'منذ يوم';
            if (diffDays === 2) return 'منذ يومين';
            if (diffDays >= 3 && diffDays <= 10) return `منذ ${diffDays} أيام`;
            if (diffDays > 10) return `منذ ${diffDays} يوماً`;
    
            return `منذ ${diffDays} يوم`;
        },

        /** الإجراءات المتاحة للطرد حسب حالته الحالية */
        actionsFor(parcel) {
            return (TRANSITIONS[parcel.status] || [])
                .filter((status) => ACTIONS[status])
                .map((status) => ({
                    status,
                    label: ACTIONS[status].label(parcel),
                    icon: ACTIONS[status].icon,
                    classes: ACTIONS[status].classes,
                }));
        },

        // ---------- العرض ----------

        statusLabel(status) {
            return STATUS_LABELS[status] ?? status;
        },

        statusBadge(status) {
            return STATUS_BADGES[status] ?? 'bg-slate-100 text-slate-600';
        },

        formatDate(value) {
            const date = value ? new Date(value) : null;
            return date && !isNaN(date) ? dateFormatter.format(date) : '';
        },

        countFor(filterKey) {
            return filterKey === 'all'
                ? this.parcels.length
                : this.parcels.filter((p) => p.status === filterKey).length;
        },

        matchesSearch(parcel) {
            const term = this.search.trim().toLowerCase();
            if (!term) return true;

            return [parcel.recipient_phone, parcel.package_type, parcel.receipt_number, parcel.recipient_name]
                .some((value) => String(value ?? '').toLowerCase().includes(term));
        },

        get filteredParcels() {
            return this.parcels.filter((p) =>
                (this.filter === 'all' || p.status === this.filter) && this.matchesSearch(p)
            );
        },
    });
})();
</script>