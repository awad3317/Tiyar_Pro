<script>
    (() => {
        const STORE = 'favoriteCustomers';
        const OFFICE_ID = {{ (int) (auth('office')->id() ?? 0) }};

        window.favoriteCustomersApp = () => ({
            ...officeShell(),

            officeId: OFFICE_ID,
            customers: [],
            search: '',
            modal: {
                open: false,
                mode: 'create',
                id: null,
                name: '',
                phone: '',
                loading: false,
                error: '',
            },
            deleteModal: {
                open: false,
                customer: null,
                loading: false,
            },

            routes: {
                list: @json(route('office.api.favorite_customers.index')),
                store: @json(route('office.api.favorite_customers.store')),
                update: (id) => `{{ url('/office/api/favorite-customers') }}/${id}`,
                destroy: (id) => `{{ url('/office/api/favorite-customers') }}/${id}`,
            },

            async init() {
                if (window.MursalDB) {
                    await window.MursalDB.open();
                }

                // 1. تنظيف ذاتي فوري لأي مهام تالفة في الطابور وأي سجلات مكررة محلياً
                await this.sanitizeSyncQueueAndLocalStore();

                // 2. تحميل البيانات المحلية النظيفة
                await this.loadLocal();

                // 3. مراقبة الاتصال ومحاولة المزامنة
                this.watchConnection(() => this.syncData());

                if (this.isOnline) {
                    this.syncData();
                }

                if ('serviceWorker' in navigator) {
                    navigator.serviceWorker.addEventListener('message', (event) => {
                        if (event.data?.type === 'TRIGGER_SYNC') {
                            this.syncData();
                        }
                    });
                }

                window.addEventListener('open-create-customer', () => this.openCreateModal());
            },

            normalizeDigits(str) {
                if (!str) return '';
                const arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
                const persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
                return String(str)
                    .replace(/[٠-٩]/g, (d) => arabic.indexOf(d))
                    .replace(/[۰-۹]/g, (d) => persian.indexOf(d))
                    .replace(/\D/g, '');
            },

            normalizeArabic(str) {
                if (!str) return '';
                return String(str)
                    .toLowerCase()
                    .replace(/[\u200B-\u200F\uFEFF]/g, '')
                    .replace(/[\u064B-\u0652\u0640]/g, '')
                    .replace(/[أإآ]/g, 'ا')
                    .replace(/ى/g, 'ي')
                    .replace(/ة/g, 'ه')
                    .replace(/\s+/g, ' ')
                    .trim();
            },

            isValidPhone(phone) {
                const clean = this.normalizeDigits(phone);
                return clean.length === 9 && /^7[01378]/.test(clean);
            },

            isTempId(id) {
                if (!id) return true;
                if (!Number.isInteger(Number(id)) || Number(id) <= 0) return true;
                const str = String(id);
                return str.startsWith('temp_') || str.includes(':') || str.includes('undefined');
            },

            get filteredCustomers() {
                const term = (this.search || '').trim();
                if (!term) return this.customers;

                const digits = this.normalizeDigits(term);
                const nameTerm = this.normalizeArabic(term);

                return this.customers.filter((c) => {
                    const matchPhone = digits.length > 0 && String(c.phone || '').includes(digits);
                    const matchName = this.normalizeArabic(c.name || '').includes(nameTerm);
                    return matchPhone || matchName;
                });
            },

            /**
             * توحيد السجلات برقم الهاتف وحذف السجلات المؤقتة عند وجود سجل حقيقي من السيرفر
             */
            async deduplicateCustomers(list) {
                if (!Array.isArray(list) || list.length === 0) return [];

                const mapByPhone = new Map();
                const idsToDeleteFromDb = [];

                for (const item of list) {
                    if (!item || !item.phone) continue;
                    const cleanPhone = this.normalizeDigits(item.phone);
                    if (!cleanPhone) continue;

                    const isRealNumeric = !this.isTempId(item.id);

                    if (!mapByPhone.has(cleanPhone)) {
                        mapByPhone.set(cleanPhone, { ...item, phone: cleanPhone });
                    } else {
                        const existing = mapByPhone.get(cleanPhone);
                        const existingIsReal = !this.isTempId(existing.id);

                        if (isRealNumeric && !existingIsReal) {
                            // السجل الحقيقي من السيرفر يحل محل السجل المؤقت
                            idsToDeleteFromDb.push(existing.id);
                            mapByPhone.set(cleanPhone, { ...item, phone: cleanPhone });
                        } else if (!isRealNumeric && existingIsReal) {
                            // السجل الموجود بالفعل حقيقي، نحذف هذا السجل المؤقت
                            idsToDeleteFromDb.push(item.id);
                        } else {
                            // كلاهما حقيقي أو كلاهما مؤقت: نحتفظ بالأحدث ونحذف الآخر
                            const existingTime = new Date(existing.updated_at || 0).getTime();
                            const itemTime = new Date(item.updated_at || 0).getTime();
                            if (itemTime >= existingTime) {
                                idsToDeleteFromDb.push(existing.id);
                                mapByPhone.set(cleanPhone, { ...item, phone: cleanPhone });
                            } else {
                                idsToDeleteFromDb.push(item.id);
                            }
                        }
                    }
                }

                // تنظيف نهائي للسجلات المؤقتة/المكررة من مخزن IndexedDB
                if (idsToDeleteFromDb.length > 0 && window.MursalDB) {
                    try {
                        for (const badId of idsToDeleteFromDb) {
                            if (badId !== undefined && badId !== null) {
                                await window.MursalDB.delete(STORE, badId);
                            }
                        }
                    } catch (e) {
                        console.warn('Deduplication cleanup from IndexedDB failed:', e);
                    }
                }

                return Array.from(mapByPhone.values());
            },

            /**
             * تطهير ذاتي فوري لطابور المزامنة ومخزن IndexedDB عند بدء التطبيق
             */
            async sanitizeSyncQueueAndLocalStore() {
                try {
                    if (!window.MursalDB) return;

                    // 1. تنظيف أي مهمة فاسدة أو مؤقتة عالقة في طابور المزامنة
                    const queue = await window.MursalDB.getAll(window.MURSAL_STORES.queue);
                    for (const q of (queue || [])) {
                        const endpoint = String(q?.endpoint || '').trim();
                        const pathname = window.MursalSync?.extractPathname
                            ? window.MursalSync.extractPathname(endpoint)
                            : endpoint.replace(/^https?:\/\/[^/]+/i, '');
                        const isFavTask = pathname.includes('favorite-customers') || (q?.payload && (q.payload.phone || q.payload.name));
                        if (!isFavTask) continue;

                        const hasInvalidId = pathname.includes('temp_') || pathname.includes('undefined') || pathname.includes('null') || pathname.includes(':');
                        const isCorruptDelete = q.method === 'DELETE' && (hasInvalidId || !pathname.match(/\/\d+$/));
                        const isCorruptPut = (q.method === 'PUT' || q.method === 'PATCH') && (hasInvalidId || !pathname.match(/\/\d+$/));

                        if (hasInvalidId || isCorruptDelete || isCorruptPut) {
                            console.warn('Purging corrupt queue task:', q);
                            await window.MursalDB.delete(window.MURSAL_STORES.queue, q.seq);
                        }
                    }

                    // 2. تنظيف مخزن العملاء المميزين المحلي من التكرار
                    const allLocal = await window.MursalDB.getAll(STORE);
                    const officeItems = (allLocal || []).filter((c) => c && c.office_id === this.officeId);
                    if (officeItems.length > 0) {
                        await this.deduplicateCustomers(officeItems);
                    }

                    // 3. تحديث مصفوفة الطابور والعداد في الهيدر
                    this.syncQueue = await window.MursalDB.getAll(window.MURSAL_STORES.queue);
                } catch (e) {
                    console.warn('sanitizeSyncQueueAndLocalStore error:', e);
                }
            },

            async loadLocal() {
                try {
                    if (!window.MursalDB) return;
                    const items = await window.MursalDB.getAll(STORE);
                    const officeItems = (items || [])
                        .filter((c) => c && c.office_id === this.officeId && c.sync_action !== 'delete');

                    const cleanItems = await this.deduplicateCustomers(officeItems);
                    this.customers = cleanItems.sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), 'ar'));

                    if (window.MursalDB) {
                        this.syncQueue = await window.MursalDB.getAll(window.MURSAL_STORES.queue);
                    }
                } catch (e) {
                    console.warn('Load local favorite customers failed:', e);
                }
            },

            async syncData() {
                if (this.isSyncing || !this.isOnline || this.authRequired) return;

                this.isSyncing = true;
                try {
                    if (window.MursalSync) {
                        await window.MursalSync.process(() => this.handleSessionExpired());
                    }

                    if (window.MursalDB) {
                        this.syncQueue = await window.MursalDB.getAll(window.MURSAL_STORES.queue);
                    }

                    const res = await fetch(this.routes.list, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (res.status === 401 || res.status === 419) {
                        this.handleSessionExpired();
                        return;
                    }

                    if (res.ok) {
                        const data = await res.json().catch(() => ({}));
                        if (Array.isArray(data.customers)) {
                            const localItems = await window.MursalDB.getAll(STORE);
                            const serverPhones = new Set(data.customers.map((c) => this.normalizeDigits(c.phone)));

                            // حذف أي سجلات محلية مؤقتة (temp_) وصل أصحابها للسيرفر
                            const obsoleteTemps = localItems.filter((i) =>
                                i.office_id === this.officeId &&
                                this.isTempId(i.id) &&
                                serverPhones.has(this.normalizeDigits(i.phone))
                            );
                            for (const obs of obsoleteTemps) {
                                await window.MursalDB.delete(STORE, obs.id);
                            }

                            // العمليات المعلقة فقط التي لم تصل للسيرفر بعد
                            const pendingCreates = (await window.MursalDB.getAll(STORE)).filter(
                                (i) => i.office_id === this.officeId && i.pending_sync && i.sync_action === 'create' && !serverPhones.has(this.normalizeDigits(i.phone))
                            );
                            const pendingUpdates = new Map(
                                localItems
                                    .filter((i) => i.office_id === this.officeId && i.pending_sync && i.sync_action === 'update')
                                    .map((i) => [i.id, i])
                            );
                            const pendingDeletes = new Set(
                                localItems
                                    .filter((i) => i.office_id === this.officeId && i.pending_sync && i.sync_action === 'delete')
                                    .map((i) => i.id)
                            );

                            const serverItems = data.customers
                                .filter((c) => !pendingDeletes.has(c.id))
                                .map((c) => {
                                    if (pendingUpdates.has(c.id)) {
                                        return pendingUpdates.get(c.id);
                                    }
                                    return {
                                        id: c.id,
                                        office_id: this.officeId,
                                        name: c.name,
                                        phone: this.normalizeDigits(c.phone),
                                        pending_sync: false,
                                        sync_action: null,
                                        updated_at: c.updated_at,
                                    };
                                });

                            const mergedRaw = [...pendingCreates, ...serverItems];
                            const mergedClean = await this.deduplicateCustomers(mergedRaw);

                            // تنظيف وإعادة كتابة المخزن لهذا المكتب بالبيانات النظيفة
                            const otherOfficesItems = (await window.MursalDB.getAll(STORE) || []).filter((i) => i.office_id !== this.officeId);
                            await window.MursalDB.clear(STORE);
                            await window.MursalDB.putMany(STORE, [...otherOfficesItems, ...mergedClean]);

                            this.customers = mergedClean.sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), 'ar'));
                        }
                    }
                } catch (e) {
                    console.warn('Sync favorite customers failed:', e);
                } finally {
                    this.isSyncing = false;
                    if (window.MursalDB) {
                        this.syncQueue = await window.MursalDB.getAll(window.MURSAL_STORES.queue);
                    }
                }
            },

            openCreateModal() {
                this.modal = {
                    open: true,
                    mode: 'create',
                    id: null,
                    name: '',
                    phone: '',
                    loading: false,
                    error: '',
                };
                this.$nextTick(() => this.$refs.customerPhoneInput?.focus());
            },

            openEditModal(customer) {
                this.modal = {
                    open: true,
                    mode: 'edit',
                    id: customer.id,
                    name: customer.name || '',
                    phone: customer.phone || '',
                    loading: false,
                    error: '',
                };
                this.$nextTick(() => this.$refs.customerNameInput?.focus());
            },

            closeModal() {
                this.modal.open = false;
                this.modal.error = '';
            },

            openDeleteModal(customer) {
                this.deleteModal = {
                    open: true,
                    customer,
                    loading: false,
                };
            },

            closeDeleteModal() {
                this.deleteModal.open = false;
                this.deleteModal.customer = null;
                this.deleteModal.loading = false;
            },

            async saveCustomer() {
                const phone = this.normalizeDigits(this.modal.phone);
                const name = (this.modal.name || '').trim();

                if (!this.isValidPhone(phone)) {
                    this.modal.error = 'يرجى إدخال رقم هاتف يمني صالح يبدأ بـ 7 ومكون من 9 أرقام.';
                    return;
                }

                if (name.length < 2) {
                    this.modal.error = 'اسم العميل يجب أن يتكون من حرفين على الأقل.';
                    return;
                }

                // فحص تكرار الرقم محلياً:
                // إذا كان الرقم مسجلاً لعميل مسبقاً، نحول العملية لتحديث السجل الموجود نفسه بدلاً من إضافة بطاقة ثانية مكررة
                let isCreate = this.modal.mode === 'create';
                let targetId = this.modal.id;

                const existingWithPhone = this.customers.find((c) => this.normalizeDigits(c.phone) === phone);
                if (isCreate && existingWithPhone) {
                    isCreate = false;
                    targetId = existingWithPhone.id;
                }

                this.modal.loading = true;
                this.modal.error = '';

                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                try {
                    if (this.isOnline) {
                        // إرسال مباشر أونلاين
                        const url = isCreate ? this.routes.store : this.routes.update(targetId);
                        const method = isCreate ? 'POST' : 'PUT';

                        const res = await fetch(url, {
                            method,
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': token || '',
                            },
                            body: JSON.stringify({ name, phone }),
                        });

                        const data = await res.json().catch(() => ({}));

                        if (!res.ok) {
                            this.modal.error = data.message || 'تعذر حفظ العميل على السيرفر.';
                            this.modal.loading = false;
                            return;
                        }

                        const realId = data.customer?.id || targetId;
                        const savedCustomer = {
                            id: realId,
                            office_id: this.officeId,
                            name,
                            phone,
                            pending_sync: false,
                            sync_action: null,
                            updated_at: data.customer?.updated_at || new Date().toISOString(),
                        };

                        if (window.MursalDB) {
                            // إذا كان targetId مؤقتاً والآن لدينا ID حقيقي من السيرفر، نحذف المعرف المؤقت القديم
                            if (targetId && targetId !== realId) {
                                await window.MursalDB.delete(STORE, targetId);
                            }
                            await window.MursalDB.put(STORE, savedCustomer);
                        }

                        // تحديث المصفوفة المحلية وإزالة أي نسخة سابقة بنفس الرقم أو المعرف
                        this.customers = [
                            savedCustomer,
                            ...this.customers.filter((c) => this.normalizeDigits(c.phone) !== phone && c.id !== targetId && c.id !== realId)
                        ];

                        this.customers = await this.deduplicateCustomers(this.customers);
                        this.customers.sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), 'ar'));
                        this.closeModal();
                        this.showFeedback(isCreate ? '⭐ تمت إضافة العميل المميز بنجاح.' : '✅ تم تحديث بيانات العميل بنجاح.', false);
                    } else {
                        // حفظ أوفلاين بالكامل
                        if (isCreate) {
                            const tempId = 'temp_' + Date.now();
                            const offlineCustomer = {
                                id: tempId,
                                office_id: this.officeId,
                                name,
                                phone,
                                pending_sync: true,
                                sync_action: 'create',
                                updated_at: new Date().toISOString(),
                            };

                            if (window.MursalDB) {
                                await window.MursalDB.put(STORE, offlineCustomer);
                            }

                            if (window.MursalSync) {
                                await window.MursalSync.enqueue(this.routes.store, { name, phone }, 'POST');
                                this.syncQueue = await window.MursalDB.getAll(window.MURSAL_STORES.queue);
                            }

                            this.customers = [
                                offlineCustomer,
                                ...this.customers.filter((c) => this.normalizeDigits(c.phone) !== phone)
                            ];
                            this.customers.sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), 'ar'));
                            this.closeModal();
                            this.showFeedback('⭐ تم حفظ العميل محلياً على الجهاز، وستتم مزامنته تلقائياً عند عودة الإنترنت.', false);
                        } else {
                            const customerId = targetId;
                            const isTemp = this.isTempId(customerId);
                            const existing = this.customers.find((c) => c.id === customerId || this.normalizeDigits(c.phone) === phone);

                            const updatedCustomer = {
                                ...(existing || {}),
                                id: customerId,
                                office_id: this.officeId,
                                name,
                                phone,
                                pending_sync: true,
                                sync_action: isTemp ? 'create' : 'update',
                                updated_at: new Date().toISOString(),
                            };

                            if (window.MursalDB) {
                                await window.MursalDB.put(STORE, updatedCustomer);
                            }

                            if (window.MursalSync) {
                                if (isTemp) {
                                    // إذا كان مؤقتاً، نعدل بيانات طلب الإضافة في الطابور مباشرة
                                    const queue = await window.MursalDB.getAll(window.MURSAL_STORES.queue);
                                    const match = queue.find((q) => q.endpoint === this.routes.store && this.normalizeDigits(q.payload?.phone) === phone);
                                    if (match) {
                                        match.payload = { name, phone };
                                        await window.MursalDB.put(window.MURSAL_STORES.queue, match);
                                    } else {
                                        await window.MursalSync.enqueue(this.routes.store, { name, phone }, 'POST');
                                    }
                                } else {
                                    // عميل حقيقي بسيرفر: نسجل طلب PUT
                                    await window.MursalSync.enqueue(this.routes.update(customerId), { name, phone }, 'PUT');
                                }
                                this.syncQueue = await window.MursalDB.getAll(window.MURSAL_STORES.queue);
                            }

                            this.customers = this.customers.map((c) => (c.id === customerId ? updatedCustomer : c));
                            this.customers = await this.deduplicateCustomers(this.customers);
                            this.customers.sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), 'ar'));
                            this.closeModal();
                            this.showFeedback('✅ تم تحديث بيانات العميل محلياً على الجهاز، وستتم مزامنتها تلقائياً عند الاتصال.', false);
                        }
                    }
                } catch (e) {
                    console.error('Save favorite customer failed:', e);
                    this.modal.error = 'حدث خطأ غير متوقع أثناء الحفظ.';
                } finally {
                    this.modal.loading = false;
                }
            },

            async confirmDelete() {
                const customer = this.deleteModal.customer;
                if (!customer) return;

                this.deleteModal.loading = true;
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const isTemp = this.isTempId(customer.id);
                const cleanPhone = this.normalizeDigits(customer.phone);

                try {
                    if (this.isOnline) {
                        if (!isTemp) {
                            try {
                                const res = await fetch(this.routes.destroy(customer.id), {
                                    method: 'DELETE',
                                    headers: {
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': token || '',
                                    },
                                });
                                // 200 أو 404 كلاهما نجاح للحذف
                                if (!res.ok && res.status !== 404) {
                                    const data = await res.json().catch(() => ({}));
                                    this.showFeedback(data.message || 'تعذر حذف العميل من السيرفر.', true);
                                    this.deleteModal.loading = false;
                                    return;
                                }
                            } catch (netErr) {
                                console.warn('Online delete error, will cleanup locally:', netErr);
                            }
                        }

                        // حذف السجل وكافة النسخ المكررة لنفس الهاتف من IndexedDB
                        if (window.MursalDB) {
                            await window.MursalDB.delete(STORE, customer.id);
                            const allLocal = await window.MursalDB.getAll(STORE);
                            for (const c of (allLocal || [])) {
                                if (c.office_id === this.officeId && (this.normalizeDigits(c.phone) === cleanPhone || c.id === customer.id)) {
                                    await window.MursalDB.delete(STORE, c.id);
                                }
                            }

                            // تنظيف أي مهمة متعلقة بهذا الرقم أو المعرف من الطابور
                            const queue = await window.MursalDB.getAll(window.MURSAL_STORES.queue);
                            for (const q of (queue || [])) {
                                if (this.normalizeDigits(q.payload?.phone) === cleanPhone || (q.endpoint && q.endpoint.includes(String(customer.id)))) {
                                    await window.MursalDB.delete(window.MURSAL_STORES.queue, q.seq);
                                }
                            }
                            this.syncQueue = await window.MursalDB.getAll(window.MURSAL_STORES.queue);
                        }

                        this.customers = this.customers.filter((c) => this.normalizeDigits(c.phone) !== cleanPhone && c.id !== customer.id);
                        this.closeDeleteModal();
                        this.showFeedback('🗑️ تم حذف العميل من القائمة بنجاح.', false);
                    } else {
                        // حذف أوفلاين
                        if (isTemp) {
                            // سجل مؤقت لم يرفع للسيرفر بعد:
                            // نحذفه من IndexedDB ومن الطابور مباشرة ولا نرسل DELETE إطلاقاً
                            if (window.MursalDB) {
                                await window.MursalDB.delete(STORE, customer.id);
                                const allLocal = await window.MursalDB.getAll(STORE);
                                for (const c of (allLocal || [])) {
                                    if (c.office_id === this.officeId && (this.normalizeDigits(c.phone) === cleanPhone || c.id === customer.id)) {
                                        await window.MursalDB.delete(STORE, c.id);
                                    }
                                }

                                const queue = await window.MursalDB.getAll(window.MURSAL_STORES.queue);
                                for (const q of (queue || [])) {
                                    if (this.normalizeDigits(q.payload?.phone) === cleanPhone || (q.endpoint && q.endpoint.includes(String(customer.id)))) {
                                        await window.MursalDB.delete(window.MURSAL_STORES.queue, q.seq);
                                    }
                                }
                                this.syncQueue = await window.MursalDB.getAll(window.MURSAL_STORES.queue);
                            }
                        } else {
                            // سجل حقيقي مسجل بالسيرفر:
                            // نحذف محلياً، وننظف أي مهام تعديل/إضافة سابقة لنفس الهاتف من الطابور
                            if (window.MursalDB) {
                                await window.MursalDB.delete(STORE, customer.id);
                                const allLocal = await window.MursalDB.getAll(STORE);
                                for (const c of (allLocal || [])) {
                                    if (c.office_id === this.officeId && (this.normalizeDigits(c.phone) === cleanPhone || c.id === customer.id)) {
                                        await window.MursalDB.delete(STORE, c.id);
                                    }
                                }

                                const queue = await window.MursalDB.getAll(window.MURSAL_STORES.queue);
                                for (const q of (queue || [])) {
                                    if (this.normalizeDigits(q.payload?.phone) === cleanPhone || (q.endpoint && q.endpoint.includes(String(customer.id)))) {
                                        await window.MursalDB.delete(window.MURSAL_STORES.queue, q.seq);
                                    }
                                }
                            }

                            // إضافة مهمة DELETE نقية بدون payload إلى طابور المزامنة
                            if (window.MursalSync) {
                                await window.MursalSync.enqueue(this.routes.destroy(customer.id), null, 'DELETE');
                            }

                            if (window.MursalDB) {
                                this.syncQueue = await window.MursalDB.getAll(window.MURSAL_STORES.queue);
                            }
                        }

                        this.customers = this.customers.filter((c) => this.normalizeDigits(c.phone) !== cleanPhone && c.id !== customer.id);
                        this.closeDeleteModal();
                        this.showFeedback('🗑️ تم حذف العميل محلياً وسيتم تأكيد الحذف بالسيرفر عند عودة الإنترنت.', false);
                    }
                } catch (e) {
                    console.error('Delete customer error:', e);
                    this.showFeedback('حدث خطأ أثناء محاولة الحذف.', true);
                } finally {
                    this.deleteModal.loading = false;
                }
            },
        });
    })();
</script>
