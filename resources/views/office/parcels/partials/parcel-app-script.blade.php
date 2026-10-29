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
            resendWhatsapp: (id) => `{{ url('/office/api/parcels') }}/${id}/resend-whatsapp`,
            quickSendSms: @json(route('office.parcels.quick_send_sms')),
            quickSendSingle: @json(route('office.parcels.quick_send_single')),
        };

        // القواعد مستوردة من الباك إند مباشرة
        const STATUS_LABELS = @json(\App\Enums\ParcelStatus::labels());
        const TRANSITIONS = @json(\App\Enums\ParcelStatus::transitionMap());

        const STATUS_BADGES = {
            in_office: 'bg-amber-100 text-amber-800',
            delivered: 'bg-emerald-100 text-emerald-800',
            returned: 'bg-rose-100 text-rose-800',
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

            async resendWhatsapp(parcelId) {
                const res = await fetch(ROUTES.resendWhatsapp(parcelId), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    throw new Error(data.message || `تعذر إرسال الواتساب (رمز: ${res.status})`);
                }
                return data;
            },

            async quickSendSms(items) {
                const res = await fetch(ROUTES.quickSendSms, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify({ items }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    throw new Error(data.message || `تعذر إرسال الدفعة (رمز: ${res.status})`);
                }
                return data;
            },

            async quickSendSingle(phone, description, channel = 'whatsapp') {
                const res = await fetch(ROUTES.quickSendSingle, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify({ phone, description, channel }),
                });
                const data = await res.json().catch(() => ({}));
                if (res.status === 401 || res.status === 419) {
                    const err = new Error('AUTH_EXPIRED');
                    err.status = res.status;
                    throw err;
                }
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

            // إعدادات العرض التدريجي الذكي في الشاشة (Capped DOM Rendering)
            pageSize: 25,
            prefetchBeforeEnd: 5, // عند الوصول للطرد 20 (قبل نهاية الـ 25 بـ 5 طرود) يتم جلب الـ 25 التالية تلقائياً
            visibleLimit: 25,
            _scrollObserver: null,

            // كاش محسوب في دورة واحدة لعدادات التبويبات وحالة الانتظار (O(1) Lookup)
            statusCounts: { all: 0, in_office: 0, delivered: 0, returned: 0 },
            pendingIdsMap: {},

            // قائمة الإرسال التدريجي الحية
            streamingQueue: [],
            streamingIdCounter: 0,

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

            get detectedParcelsCount() {
                const text = this.normalizeText(this.quickSms?.rawText || '');
                return (text.match(/(7[01378]\d{7})/g) || []).length;
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

            // تتبع حالة رسائل SMS و WhatsApp
            sendingSmsId: null,
            sendingWhatsappId: null,

            // مودال تأكيد الإرجاع للمكتب المرسل
            returnModal: {
                open: false,
                parcel: null,
            },

            // مودال تأكيد إلغاء التسليم (إرجاع للمكتب الحالي)
            undoDeliveryModal: {
                open: false,
                parcel: null,
            },

            // مودال تأكيد التسليم
            deliveryModal: {
                open: false,
                parcel: null,
            },

            async init() {
                // 1. فتح قاعدة البيانات الموحدة بإصدارها الأحدث
                await window.MursalDB.open();

                // 2. إعادة ضبط نافذة العرض عند الكتابة في البحث لنتائج فورية
                this.$watch('search', () => {
                    this.resetVisibleWindow();
                });

                // مراقبة أي تغير في الطابور أو الطرود لتحديث الكاش بدورة واحدة فقط
                this.$watch('syncQueue', () => {
                    this.recomputePendingIds();
                });
                this.$watch('parcels', () => {
                    this.recomputeStatusCounts();
                });

                // 3. تهيئة المراقب الذكي لتحميل الـ 25 طرد التالية عند الوصول للطرد 20
                this.initInfiniteScroll();

                // 4. مراقبة الاتصال وبدء المزامنة فور عودة الشبكة
                this.watchConnection(() => this.syncData());

                // 5. تحميل البيانات المخزنة محلياً للعرض الفوري
                await this.loadLocal();

                // 6. مزامنة البيانات وتحديث القائمة في حال توفر اتصال
                if (this.isOnline) {
                    this.syncData();
                }

                // 7. الاستماع لأمر الـ Service Worker للمزامنة بالخلفية
                if ('serviceWorker' in navigator) {
                    navigator.serviceWorker.addEventListener('message', (event) => {
                        if (event.data?.type === 'TRIGGER_SYNC') {
                            this.syncData();
                        }
                    });
                }
            },

            // ---------- العرض التدريجي الذكي (Capped DOM Rendering) ----------

            initInfiniteScroll() {
                if ('IntersectionObserver' in window) {
                    this._scrollObserver = new IntersectionObserver((entries) => {
                        if (entries.some((entry) => entry.isIntersecting)) {
                            this.loadMoreParcels();
                        }
                    }, {
                        root: null,
                        rootMargin: '0px 0px 450px 0px',
                        threshold: 0,
                    });
                }

                // مستمع تمرير خفيف كطبقة أمان إضافية لجميع متصفحات الجوال
                const onScrollCheck = () => {
                    if (!this.hasMoreParcels) return;
                    const container = this.$refs?.parcelsContainer;
                    if (container) {
                        const cards = container.querySelectorAll('article');
                        const triggerIndex = Math.max(0, cards.length - (this.prefetchBeforeEnd + 1));
                        const triggerCard = cards[triggerIndex];
                        if (triggerCard) {
                            const rect = triggerCard.getBoundingClientRect();
                            if (rect.top <= window.innerHeight + 150) {
                                this.loadMoreParcels();
                                return;
                            }
                        }
                    }
                    if ((window.innerHeight + window.scrollY) >= (document.documentElement.scrollHeight - 650)) {
                        this.loadMoreParcels();
                    }
                };

                window.addEventListener('scroll', onScrollCheck, { passive: true });
                this.$nextTick(() => this.refreshScrollObserver());
            },

            refreshScrollObserver() {
                if (!this._scrollObserver) return;
                this._scrollObserver.disconnect();

                if (!this.hasMoreParcels) return;

                const container = this.$refs?.parcelsContainer;
                if (container) {
                    const cards = container.querySelectorAll('article');
                    if (cards.length > 0) {
                        // مراقبة الطرد رقم 20 (أي قبل نهاية الدفعة الحالية بـ 5 طرود)
                        const triggerIndex = Math.max(0, cards.length - (this.prefetchBeforeEnd + 1));
                        const triggerCard = cards[triggerIndex];
                        if (triggerCard) {
                            this._scrollObserver.observe(triggerCard);
                        }
                    }
                }

                if (this.$refs?.loadMoreSentinel) {
                    this._scrollObserver.observe(this.$refs.loadMoreSentinel);
                }
            },

            loadMoreParcels() {
                const total = this.filteredParcels.length;
                if (this.visibleLimit >= total) return;

                this.visibleLimit = Math.min(this.visibleLimit + this.pageSize, total);
                this.$nextTick(() => this.refreshScrollObserver());
            },

            setFilter(filterKey) {
                if (this.filter === filterKey) return;
                this.filter = filterKey;
                this.resetVisibleWindow();
            },

            resetVisibleWindow() {
                this.visibleLimit = this.pageSize;
                if (window.scrollY > 180) {
                    window.scrollTo({ top: 0, behavior: 'instant' });
                }
                this.$nextTick(() => this.refreshScrollObserver());
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

            // ---------- إدارة مودال إلغاء التسليم ----------

            openUndoDeliveryModal(parcel) {
                this.undoDeliveryModal.parcel = parcel;
                this.undoDeliveryModal.open = true;
            },

            closeUndoDeliveryModal() {
                this.undoDeliveryModal.open = false;
                this.undoDeliveryModal.parcel = null;
            },

            async confirmUndoDelivery() {
                const parcel = this.undoDeliveryModal.parcel;
                if (!parcel) return;

                this.closeUndoDeliveryModal();
                await this.executeStatusChange(parcel, 'in_office');
            },

            // ---------- إدارة مودال تأكيد التسليم ----------

            openDeliveryModal(parcel) {
                this.deliveryModal.parcel = parcel;
                this.deliveryModal.open = true;
            },

            closeDeliveryModal() {
                this.deliveryModal.open = false;
                this.deliveryModal.parcel = null;
            },

            async confirmDelivery() {
                const parcel = this.deliveryModal.parcel;
                if (!parcel) return;

                this.closeDeliveryModal();
                await this.executeStatusChange(parcel, 'delivered');
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

            async resendWhatsapp(parcel) {
                if (this.sendingWhatsappId) return;

                if (!this.isOnline) {
                    this.showFeedback('يتطلب إرسال الواتساب اتصالاً نشطاً بالإنترنت.', true);
                    return;
                }

                this.sendingWhatsappId = parcel.id;
                try {
                    const response = await ParcelApi.resendWhatsapp(parcel.id);
                    this.showFeedback(response.message || 'تم إرسال إشعار الواتساب بنجاح!', false);
                } catch (err) {
                    this.showFeedback(err.message || 'تعذر إرسال إشعار الواتساب.', true);
                } finally {
                    this.sendingWhatsappId = null;
                }
            },

            getWhatsAppDirectUrl(parcel, preparedTemplate) {
                const text = (preparedTemplate || '')
                    .replace('{package}', parcel.package_type || 'طرد')
                    .replace('{receipt}', parcel.receipt_number || '')
                    .replace('{recipient}', parcel.recipient_name || 'العميل');

                let cleanPhone = String(parcel.recipient_phone || '').replace(/\D/g, '').replace(/^0+/, '');
                if (!cleanPhone.startsWith('967')) {
                    cleanPhone = '967' + cleanPhone;
                }

                return `https://wa.me/${cleanPhone}?text=${encodeURIComponent(text)}`;
            },

            openWhatsAppDirect(url) {
                const win = window.open(url, '_blank');
                if (!win || win.closed || typeof win.closed === 'undefined') {
                    window.location.href = url;
                }
            },

            async handleWhatsAppClick(parcel, preparedTemplate, hasConfiguredGateway = true) {
                if (this.sendingWhatsappId) return;

                const directUrl = this.getWhatsAppDirectUrl(parcel, preparedTemplate);

                // إذا كان الجهاز غير متصل بالإنترنت أو لم يتم ضبط بوابة واتساب للمكتب، نفتح واتساب فوراً (الطريقة السابقة)
                if (!this.isOnline || !hasConfiguredGateway) {
                    this.openWhatsAppDirect(directUrl);
                    return;
                }

                // محاولة الإرسال التلقائي عبر السيرفر
                this.sendingWhatsappId = parcel.id;
                try {
                    const response = await ParcelApi.resendWhatsapp(parcel.id);
                    if (response && response.success) {
                        this.showFeedback(response.message || 'تم إرسال إشعار الواتساب تلقائياً بنجاح!', false);
                    } else {
                        // في حال فشل الإرسال التلقائي، التحويل الفوري لواتساب بالطريقة القديمة
                        this.openWhatsAppDirect(directUrl);
                        this.showFeedback('تعذر الإرسال التلقائي، تم فتح واتساب للمراسلة المباشرة.', false);
                    }
                } catch (err) {
                    // في حال أي خطأ بالسيرفر أو انقطاع، التحويل لواتساب بالطريقة السابقة فوراً
                    console.warn('WhatsApp API not reachable, falling back to direct link:', err);
                    this.openWhatsAppDirect(directUrl);
                    this.showFeedback('تعذر الإرسال عبر السيرفر، تم فتح واتساب للمراسلة المباشرة.', false);
                } finally {
                    this.sendingWhatsappId = null;
                }
            },

            // ---------- الإرسال التدريجي الفردي (Streaming Dispatch) ----------

            addToStreamingQueue(phone, description) {
                const id = ++this.streamingIdCounter;
                const entry = {
                    id,
                    phone,
                    description,
                    channel: this.quickSms?.channel || 'whatsapp',
                    status: 'pending',   // pending | success | failed
                    message: '',
                    smsSent: false,
                    whatsappSent: false,
                };
                this.streamingQueue.unshift(entry);
                this.dispatchSingle(entry);
                return entry;
            },

            async dispatchSingle(entry) {
    try {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        // استخدام الرابط المباشر كـ Fallback لو كان ROUTES غير معرف
        const url = (typeof ROUTES !== 'undefined' && ROUTES.quickSendSingle) 
                    ? ROUTES.quickSendSingle 
                    : '/office/parcels/quick-send-single';

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token || ''
            },
            body: JSON.stringify({
                phone: entry.phone,
                description: entry.description,
                channel: entry.channel || this.quickSms?.channel || 'whatsapp'
            })
        });

        const data = await response.json().catch(() => ({}));

        // البحث عن العنصر في المصفوفة بواسطة الـ id
        const targetIndex = this.streamingQueue.findIndex(item => item.id === entry.id);

        if (targetIndex !== -1) {
            if (response.ok && (data.success === true || data.status === 'success')) {
                // تحديث مباشر داخل المصفوفة
                this.streamingQueue[targetIndex].status = 'success';
                this.streamingQueue[targetIndex].smsSent = Boolean(data.sms_sent);
                this.streamingQueue[targetIndex].whatsappSent = Boolean(data.whatsapp_sent);
            } else {
                this.streamingQueue[targetIndex].status = 'failed';
                this.streamingQueue[targetIndex].error = data.message || 'فشل الإرسال';
            }
            
            // 👈 السطر السحري لإجبار Alpine على تحديث الشاشة والعدادات فوراً
            this.streamingQueue = [...this.streamingQueue];
        }

        // تحديث جدول الطرود بالصفحة فوراً بدون إعادة تحميل كامل القائمة من السيرفر
        if (response.ok) {
            if (data.parcel && window.MursalDB) {
                await window.MursalDB.put(window.MURSAL_STORES.parcels, data.parcel);
                const exists = this.parcels.some((p) => p.id === data.parcel.id);
                if (!exists) {
                    this.parcels = [data.parcel, ...this.parcels];
                    this.$nextTick(() => this.refreshScrollObserver());
                }
            } else if (typeof this.refreshFromServer === 'function') {
                this.refreshFromServer();
            }
        }

    } catch (err) {
        console.error('Streaming dispatch error:', err);
        const targetIndex = this.streamingQueue.findIndex(item => item.id === entry.id);
        if (targetIndex !== -1) {
            this.streamingQueue[targetIndex].status = 'failed';
            this.streamingQueue = [...this.streamingQueue];
        }
    }
},

            async retryStreamingEntry(entry) {
                entry.status = 'pending';
                entry.message = '';
                await this.dispatchSingle(entry);
            },

            get streamingSentCount() {
                return (this.streamingQueue || []).filter(e => e.status === 'success').length;
            },
            get streamingPendingCount() {
                return (this.streamingQueue || []).filter(e => e.status === 'pending').length;
            },
            get streamingFailedCount() {
                return (this.streamingQueue || []).filter(e => e.status === 'failed').length;
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
                this.recomputeStatusCounts();
                this.recomputePendingIds();
                this.$nextTick(() => this.refreshScrollObserver());
            },

            async syncData() {
                if (this.authRequired || this.isSyncing) return;

                this.isSyncing = true;
                try {
                    // مزامنة الطابور
                    await window.MursalSync.process(() => this.handleSessionExpired());

                    // قراءة الطابور بعد التفريغ مباشرة
                    this.syncQueue = await window.MursalDB.getAll(window.MURSAL_STORES.queue);
                    this.recomputePendingIds();

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
                    this.recomputePendingIds();
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
                    this.recomputeStatusCounts();
                    this.$nextTick(() => this.refreshScrollObserver());
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

                if (status === 'in_office') {
                    this.openUndoDeliveryModal(parcel);
                    return;
                }

                if (status === 'delivered') {
                    this.openDeliveryModal(parcel);
                    return;
                }

                await this.executeStatusChange(parcel, status);
            },

            async executeStatusChange(parcel, status) {
                const delivered_at = status === 'delivered' ? new Date().toISOString() : null;

                // 1. تحديث تفاؤلي فوري في الذاكرة وتحديث العدادات بدورة واحدة
                Object.assign(parcel, { status, delivered_at });
                this.recomputeStatusCounts();

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

                // 4. قراءة حالة الطابور الحالية وتحديث كاش المعلقات
                this.syncQueue = await window.MursalDB.getAll(window.MURSAL_STORES.queue);
                this.recomputePendingIds();
                this.$nextTick(() => this.refreshScrollObserver());

                // 5. إذا كان النت متاحاً، ابدأ المزامنة مباشرة وانتظر اكتمالها
                if (this.isOnline) {
                    await this.syncData();
                }
            },

            recomputePendingIds() {
                const map = {};
                const queue = this.syncQueue || [];
                for (let i = 0; i < queue.length; i++) {
                    const updates = queue[i].payload?.updates || [];
                    for (let j = 0; j < updates.length; j++) {
                        const id = updates[j].id;
                        if (id) map[id] = true;
                    }
                }
                this.pendingIdsMap = map;
            },

            recomputeStatusCounts() {
                const counts = { all: this.parcels.length, in_office: 0, delivered: 0, returned: 0 };
                for (let i = 0; i < this.parcels.length; i++) {
                    const st = this.parcels[i].status;
                    counts[st] = (counts[st] || 0) + 1;
                }
                this.statusCounts = counts;
            },

            isPending(id) {
                return Boolean(this.pendingIdsMap[id]);
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

            cardBorder(status) {
                const borders = {
                    in_office: 'border-amber-300',
                    delivered: 'border-emerald-300',
                    returned:  'border-rose-300',
                };
                return borders[status] ?? 'border-slate-200/80';
            },

            formatDate(value) {
                const date = value ? new Date(value) : null;
                return date && !isNaN(date) ? dateFormatter.format(date) : '';
            },

            countFor(filterKey) {
                return this.statusCounts[filterKey] ?? 0;
            },

            matchesSearch(parcel, normalizedTerm = null) {
                const term = normalizedTerm !== null
                    ? normalizedTerm
                    : this.normalizeText(this.search).trim().toLowerCase();
                if (!term) return true;

                return [parcel.recipient_phone, parcel.package_type, parcel.receipt_number, parcel.recipient_name]
                    .some((value) => String(value ?? '').toLowerCase().includes(term));
            },

            get filteredParcels() {
                const term = this.normalizeText(this.search).trim().toLowerCase();
                const activeFilter = this.filter;

                if (activeFilter === 'all' && !term) {
                    return this.parcels;
                }

                return this.parcels.filter((p) =>
                    (activeFilter === 'all' || p.status === activeFilter) && this.matchesSearch(p, term)
                );
            },

            get visibleParcels() {
                return this.filteredParcels.slice(0, this.visibleLimit);
            },

            get hasMoreParcels() {
                return this.visibleLimit < this.filteredParcels.length;
            },
        });
    })();
</script>