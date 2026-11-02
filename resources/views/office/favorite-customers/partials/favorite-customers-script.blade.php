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

                await this.loadLocal();

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

            async loadLocal() {
                try {
                    if (!window.MursalDB) return;
                    const items = await window.MursalDB.getAll(STORE);
                    this.customers = (items || [])
                        .filter((c) => c && c.office_id === this.officeId && c.sync_action !== 'delete')
                        .sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), 'ar'));
                    
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
                            const pendingCreates = localItems.filter((i) => i.office_id === this.officeId && i.pending_sync && i.sync_action === 'create');
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
                                        phone: c.phone,
                                        pending_sync: false,
                                        sync_action: null,
                                        updated_at: c.updated_at,
                                    };
                                });

                            const merged = [...pendingCreates, ...serverItems];

                            // تنظيف وإعادة كتابة المخزن لهذا المكتب
                            const otherOfficesItems = (localItems || []).filter((i) => i.office_id !== this.officeId);
                            await window.MursalDB.clear(STORE);
                            await window.MursalDB.putMany(STORE, [...otherOfficesItems, ...merged]);

                            this.customers = merged.sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), 'ar'));
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

                // فحص تكرار الرقم محلياً
                const duplicate = this.customers.find((c) => c.phone === phone && c.id !== this.modal.id);
                if (duplicate) {
                    this.modal.error = 'رقم الهاتف هذا مسجل مسبقاً للعميل: ' + duplicate.name;
                    return;
                }

                this.modal.loading = true;
                this.modal.error = '';

                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                try {
                    if (this.isOnline) {
                        // إرسال مباشر أونلاين
                        const isCreate = this.modal.mode === 'create';
                        const url = isCreate ? this.routes.store : this.routes.update(this.modal.id);
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

                        const savedCustomer = {
                            id: data.customer?.id || this.modal.id,
                            office_id: this.officeId,
                            name,
                            phone,
                            pending_sync: false,
                            sync_action: null,
                            updated_at: data.customer?.updated_at || new Date().toISOString(),
                        };

                        if (window.MursalDB) {
                            await window.MursalDB.put(STORE, savedCustomer);
                        }

                        if (isCreate) {
                            this.customers = [savedCustomer, ...this.customers.filter((c) => c.id !== savedCustomer.id)];
                        } else {
                            this.customers = this.customers.map((c) => (c.id === savedCustomer.id ? savedCustomer : c));
                        }

                        this.customers.sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), 'ar'));
                        this.closeModal();
                        this.showFeedback(isCreate ? '⭐ تمت إضافة العميل المميز بنجاح.' : '✅ تم تحديث بيانات العميل بنجاح.', false);
                    } else {
                        // حفظ أوفلاين بالكامل
                        const isCreate = this.modal.mode === 'create';

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

                            this.customers = [offlineCustomer, ...this.customers];
                            this.customers.sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), 'ar'));
                            this.closeModal();
                            this.showFeedback('⭐ تم حفظ العميل محلياً على الجهاز، وستتم مزامنته تلقائياً عند عودة الإنترنت.', false);
                        } else {
                            const customerId = this.modal.id;
                            const existing = this.customers.find((c) => c.id === customerId);
                            const updatedCustomer = {
                                ...(existing || {}),
                                id: customerId,
                                office_id: this.officeId,
                                name,
                                phone,
                                pending_sync: true,
                                sync_action: String(customerId).startsWith('temp_') ? 'create' : 'update',
                                updated_at: new Date().toISOString(),
                            };

                            if (window.MursalDB) {
                                await window.MursalDB.put(STORE, updatedCustomer);
                            }

                            if (window.MursalSync) {
                                if (String(customerId).startsWith('temp_')) {
                                    // تحديث بيانات الطلب المعلق في طابور المزامنة مباشرة
                                    const queue = await window.MursalDB.getAll(window.MURSAL_STORES.queue);
                                    const match = queue.find((q) => q.endpoint === this.routes.store && q.payload?.phone === existing?.phone);
                                    if (match) {
                                        match.payload = { name, phone };
                                        await window.MursalDB.put(window.MURSAL_STORES.queue, match);
                                    }
                                } else {
                                    await window.MursalSync.enqueue(this.routes.update(customerId), { name, phone }, 'PUT');
                                }
                                this.syncQueue = await window.MursalDB.getAll(window.MURSAL_STORES.queue);
                            }

                            this.customers = this.customers.map((c) => (c.id === customerId ? updatedCustomer : c));
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

                try {
                    if (this.isOnline) {
                        if (!String(customer.id).startsWith('temp_')) {
                            const res = await fetch(this.routes.destroy(customer.id), {
                                method: 'DELETE',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': token || '',
                                },
                            });

                            if (!res.ok && res.status !== 404) {
                                const data = await res.json().catch(() => ({}));
                                this.showFeedback(data.message || 'تعذر حذف العميل من السيرفر.', true);
                                this.deleteModal.loading = false;
                                return;
                            }
                        }

                        if (window.MursalDB) {
                            await window.MursalDB.delete(STORE, customer.id);
                        }

                        this.customers = this.customers.filter((c) => c.id !== customer.id);
                        this.closeDeleteModal();
                        this.showFeedback('🗑️ تم حذف العميل من القائمة بنجاح.', false);
                    } else {
                        // حذف أوفلاين
                        if (String(customer.id).startsWith('temp_')) {
                            // إذا أُضيف أوفلاين فقط ولم يرفع، نحذفه من الطابور ومن الـ IndexedDB مباشرة
                            if (window.MursalDB) {
                                await window.MursalDB.delete(STORE, customer.id);
                                const queue = await window.MursalDB.getAll(window.MURSAL_STORES.queue);
                                const match = queue.find((q) => q.endpoint === this.routes.store && q.payload?.phone === customer.phone);
                                if (match) {
                                    await window.MursalDB.delete(window.MURSAL_STORES.queue, match.seq);
                                }
                                this.syncQueue = await window.MursalDB.getAll(window.MURSAL_STORES.queue);
                            }
                        } else {
                            // عميل مسجل بالسيرفر، نسجل عملية حذف في طابور المزامنة ونحذفه محلياً
                            if (window.MursalDB) {
                                await window.MursalDB.delete(STORE, customer.id);
                            }
                            if (window.MursalSync) {
                                await window.MursalSync.enqueue(this.routes.destroy(customer.id), null, 'DELETE');
                                this.syncQueue = await window.MursalDB.getAll(window.MURSAL_STORES.queue);
                            }
                        }

                        this.customers = this.customers.filter((c) => c.id !== customer.id);
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

