<script>
(() => {
    /* ------------------------------------------------------------------
     | 1. الإعدادات والمسارات الخاصة بموديول الطرود
     * ----------------------------------------------------------------*/
    const ROUTES = {
        list: @json(route('office.api.parcels')),
        sync: @json(route('office.api.parcels.sync')),
        login: @json(route('office.login')),
        resendSms: (id) => `{{ url('/office/api/parcels') }}/${id}/resend-sms`,
    };

    // القواعد مستوردة من الباك إند مباشرة
    const STATUS_LABELS = @json(\App\Enums\ParcelStatus::labels());
    const TRANSITIONS   = @json(\App\Enums\ParcelStatus::transitionMap());

    const STATUS_BADGES = {
        in_office: 'bg-amber-100 text-amber-800',
        delivered: 'bg-emerald-100 text-emerald-800',
        returned:  'bg-rose-100 text-rose-800',
    };

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

    const dateFormatter = new Intl.DateTimeFormat('ar-u-nu-latn', { dateStyle: 'medium', timeStyle: 'short' });

    /* ------------------------------------------------------------------
     | 2. طلبات الشبكة المباشرة الخاصة بالطرود
     * ----------------------------------------------------------------*/
    const ParcelApi = {
        get csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content;
        },

        async fetchParcels() {
            const res = await fetch(ROUTES.list, { headers: { Accept: 'application/json' } });
            if (res.status === 401 || res.status === 419) {
                const err = new Error('AUTH_EXPIRED');
                err.status = res.status;
                throw err;
            }
            if (!res.ok) throw new Error(`Fetch parcels failed (${res.status})`);
            return res.json();
        },

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
    };

    /* ------------------------------------------------------------------
     | 3. مكوّن Alpine لصفحة الطرود
     * ----------------------------------------------------------------*/
    window.parcelApp = () => ({
        ...officeShell(),

        parcels: [],
        syncQueue: [],
        search: '',
        filter: 'all',
        filters: FILTERS,
        isSyncing: false,

        // تتبع حالة رسائل SMS
        sendingSmsId: null,
        smsFeedback: { show: false, message: '', isError: false },

        // مودال تأكيد الإرجاع
        returnModal: {
            open: false,
            parcel: null,
        },

        async init() {
            // 1. فتح قاعدة البيانات الموحدة بإصدارها الأحدث
            await window.MursalDB.open();

            // 2. مراقبة الاتصال وبدء المزامنة فور عودة الشبكة
            this.watchConnection(() => this.syncData());

            // 3. تحميل البيانات المخزنة محلياً للعرض الفوري
            await this.loadLocal();

            // 4. مزامنة البيانات وتحديث القائمة في حال توفر اتصال
            if (this.isOnline) {
                this.syncData();
            }

            // 5. الاستماع لأمر الـ Service Worker للمزامنة بالخلفية
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

        // ---------- إرسال رسائل SMS ----------

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
                const response = await ParcelApi.resendSms(parcel.id);
                this.showFeedback(response.message || 'تم إرسال رسالة SMS بنجاح!', false);
            } catch (err) {
                this.showFeedback(err.message || 'تعذر إرسال رسالة SMS.', true);
            } finally {
                this.sendingSmsId = null;
            }
        },

        // ---------- تحميل ومزامنة البيانات ----------

        sortParcels(list) {
            return [...list].sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0));
        },

        async loadLocal() {
            const [localParcels, queue] = await Promise.all([
                window.MursalDB.getAll(window.MURSAL_STORES.parcels),
                window.MursalDB.getAll(window.MURSAL_STORES.queue),
            ]);
            this.parcels = this.sortParcels(localParcels);
            this.syncQueue = queue;
        },

        async syncData() {
            if (this.authRequired || this.isSyncing) return;

            this.isSyncing = true;
            try {
                // مزامنة الطابور
                await window.MursalSync.process(() => this.handleSessionExpired());

                // قراءة الطابور بعد التفريغ مباشرة
                this.syncQueue = await window.MursalDB.getAll(window.MURSAL_STORES.queue);

                // تحديث القائمة من السيرفر
                if (this.isOnline && !this.authRequired) {
                    await this.refreshFromServer();
                }
            } catch (e) {
                console.warn('Sync failed:', e);
            } finally {
                this.isSyncing = false;
                // تأكيد تحديث الطابور بعد كل العمليات
                this.syncQueue = await window.MursalDB.getAll(window.MURSAL_STORES.queue);
            }
        },

        async refreshFromServer() {
            try {
                const serverParcels = await ParcelApi.fetchParcels();
                const pendingUpdates = await window.MursalDB.getAll(window.MURSAL_STORES.queue);
                
                // دمج التعديلات المعلقة محلياً مع بيانات السيرفر لضمان عدم اختفاء التعديلات
                const pendingMap = new Map();
                pendingUpdates.forEach((item) => {
                    const updates = item.payload?.updates || [];
                    updates.forEach((u) => pendingMap.set(u.id, u));
                });

                const merged = serverParcels.map((p) => {
                    const local = pendingMap.get(p.id);
                    return local ? { ...p, status: local.status, delivered_at: local.delivered_at } : p;
                });

                await window.MursalDB.clear(window.MURSAL_STORES.parcels);
                await window.MursalDB.putMany(window.MURSAL_STORES.parcels, merged);
                this.parcels = this.sortParcels(merged);
            } catch (error) {
                if (error.message === 'AUTH_EXPIRED') {
                    this.handleSessionExpired();
                    return;
                }
                console.info('تعذر جلب البيانات من السيرفر، يتم الاعتماد على البيانات المحلية.');
            }
        },

        // ---------- تعديل الحالة والمزامنة ----------

        async changeStatus(parcel, status) {
            if (!this.canTransition(parcel, status)) return;

            if (status === 'returned') {
                this.openReturnModal(parcel);
                return;
            }

            await this.executeStatusChange(parcel, status);
        },

        async executeStatusChange(parcel, status) {
            const delivered_at = status === 'delivered' ? new Date().toISOString() : null;

            // 1. تحديث تفاؤلي فوري في الذاكرة
            Object.assign(parcel, { status, delivered_at });

            // 2. تحديث التخزين المحلي للطرود
            try {
                await window.MursalDB.put(window.MURSAL_STORES.parcels, parcel);
            } catch (error) {
                console.warn('IndexedDB write error:', error);
            }

            // 3. إضافة الحركة لطابور المزامنة
            await window.MursalSync.enqueue(ROUTES.sync, {
                updates: [{ id: parcel.id, status, delivered_at }]
            });

            // 4. قراءة حالة الطابور الحالية
            this.syncQueue = await window.MursalDB.getAll(window.MURSAL_STORES.queue);

            // 5. إذا كان النت متاحاً، ابدأ المزامنة مباشرة وانتظر اكتمالها
            if (this.isOnline) {
                await this.syncData();
            }
        },

        isPending(id) {
            return this.syncQueue.some((item) => {
                const updates = item.payload?.updates || [];
                return updates.some((u) => u.id === id);
            });
        },

        // ---------- القواعد والمساعدات ----------

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