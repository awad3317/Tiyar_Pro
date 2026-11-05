@extends('accountant.layouts.app')

@section('title', 'الطرود غير المصنفة - بوابة المحاسب')

@section('alpine', 'accountantParcelsApp()')

@section('content')
    <!-- 1. شريط الإحصائيات العلوي المدمج + البحث الفوري + تبويبات الفلترة -->
    <div class="bg-white border-b border-slate-200 sticky top-[68px] z-20 shadow-2xs">

        <!-- شريط الإحصائيات المدمج -->
        <div class="px-3.5 pt-3 pb-2 flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <span class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-lg">inventory_2</span>
                </span>
                <div>
                    <h2 class="text-xs font-extrabold text-slate-800 leading-tight">
                        الطرود غير المصنفة
                        <span class="text-slate-400 font-mono">(<span x-text="stats.total_count">0</span>)</span>
                    </h2>
                    <p class="text-[10px] text-slate-400">حدد الطرود بعلامة (✓) لضمها إلى كشف سائق</p>
                </div>
            </div>

            <!-- ملخص الآجل السريع -->
            <div class="bg-amber-50 border border-amber-200/90 rounded-xl px-2.5 py-1.5 text-left shrink-0">
                <div class="flex items-center gap-1 justify-end text-[10px] font-bold text-amber-800">
                    <span class="material-symbols-outlined text-[13px] text-amber-600">payments</span>
                    <span>إجمالي الآجل (<span x-text="stats.postpaid_count">0</span>):</span>
                </div>
                <p class="text-xs font-black text-amber-900 dir-ltr">
                    <span x-text="formatAmount(stats.postpaid_amount)">0</span> ر.ي
                </p>
            </div>
        </div>

        <!-- حقل البحث الفوري + زر تحديد الكل السريع -->
        <div class="px-3.5 pb-2.5 flex items-center gap-2">
            <div class="relative flex-1">
                <input type="text"
                       x-model="search"
                       @input="visibleLimit = 30"
                       placeholder="ابحث برقم الجوال، اسم المستلم، رقم السند، أو نوع الطرد..."
                       class="w-full bg-slate-100 focus:bg-white text-slate-800 placeholder-slate-400 text-xs font-bold rounded-xl pr-9 pl-8 py-2 outline-none border border-slate-200 focus:border-secondary transition-all">
                <span class="material-symbols-outlined absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-lg pointer-events-none">search</span>
                <button type="button"
                        x-show="search !== ''"
                        x-cloak
                        @click="search = ''; visibleLimit = 30"
                        class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>

            <button type="button"
                    x-show="filteredParcels.length > 0"
                    @click="selectedIds.length === filteredParcels.length ? selectedIds = [] : selectAllFiltered()"
                    class="shrink-0 py-2 px-2.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-[11px] font-extrabold text-slate-700 transition-colors">
                <span x-text="selectedIds.length > 0 && selectedIds.length === filteredParcels.length ? 'إلغاء الكل' : 'تحديد الكل'"></span>
            </button>
        </div>

        <!-- شريط تبويبات الفلترة السريع: الكل (X) | آجل (X) | محاسب (X) -->
        <nav class="flex border-t border-slate-100 text-xs font-extrabold text-center">
            <button type="button"
                    @click="filter = 'all'; visibleLimit = 30"
                    :class="filter === 'all' ? 'border-b-2 border-secondary text-primary bg-amber-50/30' : 'text-slate-500 hover:text-slate-700'"
                    class="flex-1 py-2.5 transition-colors">
                <span>الكل</span>
                (<span x-text="stats.total_count">0</span>)
            </button>

            <button type="button"
                    @click="filter = 'postpaid'; visibleLimit = 30"
                    :class="filter === 'postpaid' ? 'border-b-2 border-amber-500 text-amber-700 bg-amber-50/40' : 'text-slate-500 hover:text-slate-700'"
                    class="flex-1 py-2.5 transition-colors">
                <span>آجل</span>
                (<span x-text="stats.postpaid_count">0</span>)
            </button>

            <button type="button"
                    @click="filter = 'prepaid'; visibleLimit = 30"
                    :class="filter === 'prepaid' ? 'border-b-2 border-emerald-600 text-emerald-700 bg-emerald-50/40' : 'text-slate-500 hover:text-slate-700'"
                    class="flex-1 py-2.5 transition-colors">
                <span>محاسب</span>
                (<span x-text="stats.prepaid_count">0</span>)
            </button>
        </nav>
    </div>

    <!-- 2. قائمة البطاقات -->
    <main class="flex-1 p-3 space-y-2.5" :class="selectedIds.length > 0 ? 'pb-28' : ''">

        <!-- حالة التحميل المبدئية -->
        <div x-show="isLoading" class="py-12 text-center text-slate-400 space-y-2">
            <span class="w-7 h-7 border-2 border-primary border-t-transparent rounded-full animate-spin inline-block"></span>
            <p class="text-xs font-bold">جاري تحميل الطرود غير المصنفة...</p>
        </div>

        <!-- عرض بطاقات الطرود -->
        <template x-if="!isLoading">
            <div class="space-y-2.5">
                <template x-for="p in visibleParcels" :key="p.id">
                    @include('accountant.parcels.partials.parcel-card')
                </template>

                <!-- زر عرض المزيد عند وجود عدد كبير من الطرود لتسريع أداء الجوال -->
                <div x-show="filteredParcels.length > visibleLimit" x-cloak class="pt-2 text-center">
                    <button type="button"
                            @click="visibleLimit += 30"
                            class="w-full py-2.5 px-4 rounded-xl bg-white border border-slate-200 hover:border-secondary text-xs font-extrabold text-primary shadow-2xs transition-all">
                        عرض المزيد من الطرود (<span x-text="filteredParcels.length - visibleLimit"></span> متبقية)
                    </button>
                </div>

                <!-- حالة عدم وجود نتائج مطابقة -->
                <div x-show="filteredParcels.length === 0" x-cloak class="text-center py-12 text-slate-400">
                    <span class="material-symbols-outlined text-4xl mb-1 text-slate-300">inventory_2</span>
                    <p class="text-xs font-bold text-slate-500">لا توجد طرود غير مصنفة مطابقة للبحث أو الفلتر</p>
                </div>
            </div>
        </template>

    </main>

    <!-- 3. شريط الإجراءات السفلي البارز عند تحديد طرد أو أكثر (فوق الفوتر مباشرة) -->
    <div x-show="selectedIds.length > 0"
         x-cloak
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-6"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-6"
         class="fixed bottom-[60px] left-0 right-0 max-w-md mx-auto px-3 pb-2 z-30">
        <div class="bg-primary text-white rounded-2xl p-3 shadow-2xl border border-slate-700 space-y-2.5">
            <div class="flex items-center justify-between text-xs">
                <div class="flex items-center gap-1.5 font-extrabold">
                    <span class="w-5 h-5 rounded-md bg-secondary text-primary flex items-center justify-center text-[11px] font-black"
                          x-text="selectedIds.length"></span>
                    <span>طرد محدد</span>
                    <span class="text-slate-400">•</span>
                    <span class="text-amber-300 font-bold dir-ltr"
                          x-text="'الآجل: ' + formatAmount(selectedPostpaidAmount) + ' ر.ي'"></span>
                </div>

                <button type="button"
                        @click="selectedIds = []"
                        class="text-[11px] font-bold text-slate-300 hover:text-white flex items-center gap-0.5">
                    <span>إلغاء</span>
                    <span class="material-symbols-outlined text-sm">close</span>
                </button>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <!-- زر ضم لكشف جديد -->
                <button type="button"
                        @click="openCreateManifestWithParcelsModal()"
                        class="py-2 px-3 rounded-xl bg-secondary hover:brightness-105 text-primary font-extrabold text-xs flex items-center justify-center gap-1.5 shadow-sm active:scale-95 transition-all">
                    <span class="material-symbols-outlined text-base">add_circle</span>
                    <span>+ ضم لكشف جديد</span>
                </button>

                <!-- زر ضم لكشف مفتوح -->
                <button type="button"
                        @click="openAssignToOpenManifestModal()"
                        class="py-2 px-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-white border border-slate-600 font-extrabold text-xs flex items-center justify-center gap-1.5 active:scale-95 transition-all">
                    <span class="material-symbols-outlined text-base text-amber-400">folder_open</span>
                    <span>ضم لكشف مفتوح</span>
                </button>
            </div>
        </div>
    </div>

    <!-- 4. نافذة تعديل الطرد للمحاسب -->
    @include('accountant.parcels.partials.edit-parcel-modal')

    <!-- 5. نافذة سجل تعديلات الطرد -->
    @include('accountant.parcels.partials.edit-logs-modal')

    <!-- 6. نافذة إنشاء كشف جديد وضم الطرود المحددة إليه -->
    @include('accountant.manifests.partials.manifest-modal')

    <!-- 7. نافذة ضم الطرود المحددة إلى كشف مفتوح حالياً -->
    @include('accountant.parcels.partials.assign-open-manifest-modal')
@endsection

@push('scripts')
<script>
    function accountantParcelsApp() {
        const baseShell = typeof window.accountantShell === 'function' ? window.accountantShell() : {};

        return {
            ...baseShell,

            isLoading: true,
            parcels: [],
            drivers: [],
            openManifests: [],
            filter: 'all', // all | postpaid | prepaid
            search: '',
            visibleLimit: 30,
            selectedIds: [],
            showDriverSuggestions: false,

            stats: {
                total_count: 0,
                postpaid_count: 0,
                postpaid_amount: 0,
                prepaid_count: 0,
            },

            editModal: {
                open: false,
                saving: false,
                error: '',
                parcelId: null,
                form: {
                    recipient_name: '',
                    recipient_phone: '',
                    package_type: 'طرد',
                    receipt_number: '',
                    source_office_name: '',
                    payment_type: 'prepaid',
                    cod_amount: '',
                },
            },

            logsModal: {
                open: false,
                loading: false,
                parcel: null,
                logs: [],
            },

            manifestModal: {
                open: false,
                mode: 'create',
                saving: false,
                error: '',
                manifestId: null,
                attachParcelsCount: 0,
                attachPostpaidAmount: 0,
                form: {
                    driver_id: '',
                    driver_name: '',
                    driver_phone: '',
                    source_office_name: '',
                    source_branch_name: '',
                    manifest_number: '',
                    manifest_date: new Date().toISOString().slice(0, 10),
                    notes: '',
                },
            },

            assignModal: {
                open: false,
                saving: false,
                error: '',
                manifestId: '',
            },

            async init() {
                if (typeof baseShell.init === 'function') {
                    await baseShell.init.call(this);
                }
                await Promise.all([
                    this.fetchParcels(),
                    this.fetchDriversAndOpenManifests(),
                ]);
            },

            async refreshData() {
                await Promise.all([
                    this.fetchParcels(),
                    this.fetchDriversAndOpenManifests(),
                ]);
            },

            async fetchParcels() {
                this.isLoading = true;
                this.isSyncing = true;
                try {
                    const res = await fetch('{{ route("accountant.api.parcels.list") }}', {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (res.status === 401 || res.status === 419) {
                        this.authModal.open = true;
                        return;
                    }

                    const data = await res.json();
                    if (data && data.success) {
                        this.parcels = Array.isArray(data.parcels) ? data.parcels : [];
                        const validIds = new Set(this.parcels.map((p) => p.id));
                        this.selectedIds = this.selectedIds.filter((id) => validIds.has(id));
                        this.recalculateStats();
                    }
                } catch (e) {
                    this.showToast('تعذر الاتصال بالسيرفر لجلب الطرود.', true);
                } finally {
                    this.isLoading = false;
                    this.isSyncing = false;
                }
            },

            async fetchDriversAndOpenManifests() {
                try {
                    const res = await fetch('{{ route("accountant.api.drivers.list") }}', {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (res.ok) {
                        const data = await res.json();
                        if (data && data.success) {
                            this.drivers = Array.isArray(data.drivers) ? data.drivers : [];
                            this.openManifests = Array.isArray(data.open_manifests) ? data.open_manifests : [];
                        }
                    }
                } catch (e) {
                    // ignore background fetch error
                }
            },

            recalculateStats() {
                const totalCount = this.parcels.length;
                let postpaidCount = 0;
                let postpaidAmount = 0;

                for (const p of this.parcels) {
                    if (p.payment_type === 'postpaid') {
                        postpaidCount++;
                        postpaidAmount += Number(p.cod_amount || 0);
                    }
                }

                this.stats = {
                    total_count: totalCount,
                    postpaid_count: postpaidCount,
                    postpaid_amount: postpaidAmount,
                    prepaid_count: totalCount - postpaidCount,
                };
            },

            get selectedPostpaidAmount() {
                const selectedSet = new Set(this.selectedIds);
                let sum = 0;
                for (const p of this.parcels) {
                    if (selectedSet.has(p.id) && p.payment_type === 'postpaid') {
                        sum += Number(p.cod_amount || 0);
                    }
                }
                return sum;
            },

            get filteredParcels() {
                const q = this.normalizeSearch(this.search);

                return this.parcels.filter((p) => {
                    if (this.filter === 'postpaid' && p.payment_type !== 'postpaid') return false;
                    if (this.filter === 'prepaid' && p.payment_type !== 'prepaid') return false;

                    if (!q) return true;

                    const haystack = [
                        p.recipient_phone || '',
                        p.recipient_name || '',
                        p.receipt_number || '',
                        p.package_type || '',
                        p.source_office_name || '',
                    ].join(' ').toLowerCase();

                    return this.normalizeSearch(haystack).includes(q);
                });
            },

            get visibleParcels() {
                return this.filteredParcels.slice(0, this.visibleLimit);
            },

            get driverSuggestions() {
                const typed = (this.manifestModal.form.driver_name || '').trim().toLowerCase();
                if (!typed) return this.drivers.slice(0, 6);
                return this.drivers.filter((d) =>
                    (d.name || '').toLowerCase().includes(typed) ||
                    (d.phone || '').includes(typed)
                ).slice(0, 6);
            },

            selectSavedDriver(driverId) {
                if (!driverId) {
                    this.manifestModal.form.driver_id = '';
                    this.manifestModal.form.driver_name = '';
                    this.manifestModal.form.driver_phone = '';
                    this.$nextTick(() => this.$refs.manifestDriverNameInput?.focus());
                    return;
                }
                const found = this.drivers.find((d) => String(d.id) === String(driverId));
                if (found) {
                    this.manifestModal.form.driver_id = found.id;
                    this.manifestModal.form.driver_name = found.name || '';
                    this.manifestModal.form.driver_phone = found.phone || '';
                    this.showDriverSuggestions = false;
                }
            },

            onDriverNameTyped() {
                this.manifestModal.form.driver_id = '';
                this.showDriverSuggestions = true;
            },

            applyDriverSuggestion(drv) {
                this.manifestModal.form.driver_id = drv.id;
                this.manifestModal.form.driver_name = drv.name || '';
                this.manifestModal.form.driver_phone = drv.phone || '';
                this.showDriverSuggestions = false;
            },

            openCreateManifestWithParcelsModal() {
                if (this.selectedIds.length === 0) return;

                // استنتاج اسم المكتب المرسل تلقائياً إن كان محدداً في أحد الطرود المختارة
                const selectedSet = new Set(this.selectedIds);
                const firstWithSource = this.parcels.find((p) => selectedSet.has(p.id) && p.source_office_name);

                this.manifestModal.mode = 'create';
                this.manifestModal.manifestId = null;
                this.manifestModal.error = '';
                this.manifestModal.attachParcelsCount = this.selectedIds.length;
                this.manifestModal.attachPostpaidAmount = this.selectedPostpaidAmount;
                this.manifestModal.form = {
                    driver_id: '',
                    driver_name: '',
                    driver_phone: '',
                    source_office_name: firstWithSource ? firstWithSource.source_office_name : '',
                    source_branch_name: '',
                    manifest_number: '',
                    manifest_date: new Date().toISOString().slice(0, 10),
                    notes: '',
                };
                this.showDriverSuggestions = false;
                this.manifestModal.open = true;

                this.$nextTick(() => {
                    this.$refs.manifestDriverNameInput?.focus();
                });
            },

            closeManifestModal() {
                if (this.manifestModal.saving) return;
                this.manifestModal.open = false;
                this.manifestModal.error = '';
                this.showDriverSuggestions = false;
            },

            async submitManifestForm() {
                if (this.manifestModal.saving) return;

                this.manifestModal.error = '';
                this.manifestModal.saving = true;

                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const payload = {
                    ...this.manifestModal.form,
                    parcel_ids: this.selectedIds,
                };

                try {
                    const res = await fetch('{{ route("accountant.api.manifests.store") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token || '',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify(payload),
                    });

                    if (res.status === 401 || res.status === 419) {
                        this.manifestModal.open = false;
                        this.authModal.open = true;
                        return;
                    }

                    const data = await res.json();

                    if (!res.ok || !data.success) {
                        if (data.errors) {
                            const firstError = Object.values(data.errors).flat()[0];
                            this.manifestModal.error = firstError || 'يرجى التأكد من صحة البيانات المدخلة.';
                        } else {
                            this.manifestModal.error = data.message || 'تعذر إنشاء الكشف.';
                        }
                        return;
                    }

                    // إزالة الطرود المصنفة من قائمة "غير المصنفة" فوراً
                    const assignedSet = new Set(this.selectedIds);
                    this.parcels = this.parcels.filter((p) => !assignedSet.has(p.id));
                    this.selectedIds = [];
                    this.recalculateStats();

                    // تحديث قائمة السائقين والكشوفات المفتوحة محلياً
                    if (data.driver && data.driver.id) {
                        const dIdx = this.drivers.findIndex((d) => d.id === data.driver.id);
                        if (dIdx !== -1) {
                            this.drivers.splice(dIdx, 1, data.driver);
                        } else {
                            this.drivers.push(data.driver);
                        }
                    }
                    if (data.manifest) {
                        this.openManifests.unshift(data.manifest);
                    }

                    this.manifestModal.open = false;
                    this.showToast(data.message || 'تم إنشاء الكشف وضم الطرود بنجاح.', false);
                } catch (err) {
                    this.manifestModal.error = 'تعذر الاتصال بالسيرفر، يرجى التحقق من اتصال الإنترنت.';
                } finally {
                    this.manifestModal.saving = false;
                }
            },

            async openAssignToOpenManifestModal() {
                if (this.selectedIds.length === 0) return;
                this.assignModal.error = '';
                await this.fetchDriversAndOpenManifests();
                this.assignModal.manifestId = this.openManifests.length > 0 ? this.openManifests[0].id : '';
                this.assignModal.open = true;
            },

            closeAssignModal() {
                if (this.assignModal.saving) return;
                this.assignModal.open = false;
                this.assignModal.error = '';
            },

            async submitAssignToOpenManifest() {
                if (this.assignModal.saving || !this.assignModal.manifestId || this.selectedIds.length === 0) return;

                this.assignModal.error = '';
                this.assignModal.saving = true;

                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const url = `/accountant/api/manifests/${encodeURIComponent(this.assignModal.manifestId)}/assign-parcels`;

                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token || '',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            parcel_ids: this.selectedIds,
                        }),
                    });

                    if (res.status === 401 || res.status === 419) {
                        this.assignModal.open = false;
                        this.authModal.open = true;
                        return;
                    }

                    const data = await res.json();

                    if (!res.ok || !data.success) {
                        if (data.errors) {
                            const firstError = Object.values(data.errors).flat()[0];
                            this.assignModal.error = firstError || 'يرجى التأكد من اختيار الكشف.';
                        } else {
                            this.assignModal.error = data.message || 'تعذر ضم الطرود للكشف.';
                        }
                        return;
                    }

                    // إزالة الطرود المصنفة من قائمة "غير المصنفة" فوراً
                    const assignedSet = new Set(this.selectedIds);
                    this.parcels = this.parcels.filter((p) => !assignedSet.has(p.id));
                    this.selectedIds = [];
                    this.recalculateStats();

                    this.assignModal.open = false;
                    this.showToast(data.message || 'تم ضم الطرود إلى الكشف بنجاح.', false);
                } catch (err) {
                    this.assignModal.error = 'تعذر الاتصال بالسيرفر، يرجى التحقق من اتصال الإنترنت.';
                } finally {
                    this.assignModal.saving = false;
                }
            },

            normalizeSearch(str) {
                if (!str) return '';
                const arabic = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩','۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
                const english = ['0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9'];
                let s = String(str).toLowerCase().trim();
                for (let i = 0; i < arabic.length; i++) {
                    s = s.replaceAll(arabic[i], english[i]);
                }
                return s;
            },

            isSelected(id) {
                return this.selectedIds.includes(id);
            },

            toggleSelect(id) {
                if (this.isSelected(id)) {
                    this.selectedIds = this.selectedIds.filter((item) => item !== id);
                } else {
                    this.selectedIds.push(id);
                }
            },

            selectAllFiltered() {
                const ids = this.filteredParcels.map((p) => p.id);
                this.selectedIds = Array.from(new Set([...this.selectedIds, ...ids]));
            },

            openEditModal(parcel, focusField = null) {
                this.editModal.error = '';
                this.editModal.parcelId = parcel.id;
                this.editModal.form = {
                    recipient_name: parcel.recipient_name || '',
                    recipient_phone: parcel.recipient_phone || '',
                    package_type: parcel.package_type || 'طرد',
                    receipt_number: parcel.receipt_number || '',
                    source_office_name: parcel.source_office_name || '',
                    payment_type: parcel.payment_type === 'postpaid' ? 'postpaid' : 'prepaid',
                    cod_amount: parcel.payment_type === 'postpaid' && parcel.cod_amount !== null ? parcel.cod_amount : '',
                };
                this.editModal.open = true;

                this.$nextTick(() => {
                    if (focusField === 'recipient_name' && this.$refs.editRecipientNameInput) {
                        this.$refs.editRecipientNameInput.focus();
                    }
                });
            },

            closeEditModal() {
                if (this.editModal.saving) return;
                this.editModal.open = false;
                this.editModal.error = '';
            },

            async submitParcelEdit() {
                if (this.editModal.saving || !this.editModal.parcelId) return;

                this.editModal.error = '';
                this.editModal.saving = true;

                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const url = `/accountant/api/parcels/${encodeURIComponent(this.editModal.parcelId)}`;

                try {
                    const res = await fetch(url, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token || '',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify(this.editModal.form),
                    });

                    if (res.status === 401 || res.status === 419) {
                        this.editModal.open = false;
                        this.authModal.open = true;
                        return;
                    }

                    const data = await res.json();

                    if (!res.ok || !data.success) {
                        if (data.errors) {
                            const firstError = Object.values(data.errors).flat()[0];
                            this.editModal.error = firstError || 'يرجى التأكد من صحة البيانات المدخلة.';
                        } else {
                            this.editModal.error = data.message || 'تعذر حفظ التعديلات.';
                        }
                        return;
                    }

                    const idx = this.parcels.findIndex((item) => item.id === this.editModal.parcelId);
                    if (idx !== -1 && data.parcel) {
                        this.parcels.splice(idx, 1, data.parcel);
                    }
                    this.recalculateStats();

                    this.editModal.open = false;
                    this.showToast(data.message || 'تم حفظ التعديلات بنجاح.', false);
                } catch (err) {
                    this.editModal.error = 'تعذر الاتصال بالسيرفر، يرجى التحقق من اتصال الإنترنت.';
                } finally {
                    this.editModal.saving = false;
                }
            },

            async openLogsModal(parcel) {
                this.logsModal.parcel = parcel;
                this.logsModal.logs = [];
                this.logsModal.loading = true;
                this.logsModal.open = true;

                try {
                    const res = await fetch(`/accountant/api/parcels/${encodeURIComponent(parcel.id)}/logs`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (res.status === 401 || res.status === 419) {
                        this.logsModal.open = false;
                        this.authModal.open = true;
                        return;
                    }

                    const data = await res.json();
                    if (data && data.success) {
                        this.logsModal.logs = Array.isArray(data.logs) ? data.logs : [];
                    }
                } catch (e) {
                    this.showToast('تعذر تحميل سجل التعديلات.', true);
                } finally {
                    this.logsModal.loading = false;
                }
            },

            formatAmount(val) {
                const num = Number(val || 0);
                return num.toLocaleString('en-US', { maximumFractionDigits: 2 });
            },

            formatDateShort(iso) {
                if (!iso) return '';
                try {
                    const d = new Date(iso);
                    return d.toLocaleDateString('ar-YE', {
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                    });
                } catch (e) {
                    return '';
                }
            },
        };
    }
</script>
@endpush
