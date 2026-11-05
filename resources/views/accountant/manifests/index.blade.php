@extends('accountant.layouts.app')

@section('title', 'كشوفات السائقين - بوابة المحاسب')

@section('alpine', 'accountantManifestsApp()')

@section('content')
    <!-- 1. شريط الإحصائيات العلوي + زر إنشاء كشف + البحث الفوري + تبويبات الفلترة -->
    <div class="bg-white border-b border-slate-200 sticky top-[68px] z-20 shadow-2xs">

        <!-- شريط الإحصائيات + زر إنشاء كشف جديد -->
        <div class="px-3.5 pt-3 pb-2.5 flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <span class="w-9 h-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-xl">receipt_long</span>
                </span>
                <div>
                    <h2 class="text-xs font-extrabold text-slate-800 leading-tight">
                        كشوفات السائقين
                        <span class="text-slate-400 font-mono">(<span x-text="stats.total_count">0</span>)</span>
                    </h2>
                    <p class="text-[10px] text-slate-500 mt-0.5">
                        صافي السائقين: <strong class="text-emerald-700 font-black dir-ltr" x-text="formatAmount(stats.total_net_driver_amount) + ' ر.ي'">0 ر.ي</strong>
                    </p>
                </div>
            </div>

            <!-- زر بارز لإنشاء كشف سائق جديد -->
            <button type="button"
                    @click="openCreateManifestModal()"
                    class="py-2 px-3 rounded-xl bg-secondary hover:brightness-105 text-primary font-extrabold text-xs shadow-sm shadow-secondary/25 flex items-center gap-1 active:scale-95 transition-all shrink-0">
                <span class="material-symbols-outlined text-base">add</span>
                <span>إنشاء كشف جديد</span>
            </button>
        </div>

        <!-- حقل البحث الفوري -->
        <div class="px-3.5 pb-2.5">
            <div class="relative">
                <input type="text"
                       x-model="search"
                       @input="visibleLimit = 25"
                       placeholder="ابحث باسم السائق، رقم السائق، المكتب المرسل، أو الفرع..."
                       class="w-full bg-slate-100 focus:bg-white text-slate-800 placeholder-slate-400 text-xs font-bold rounded-xl pr-9 pl-8 py-2 outline-none border border-slate-200 focus:border-secondary transition-all">
                <span class="material-symbols-outlined absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-lg pointer-events-none">search</span>
                <button type="button"
                        x-show="search !== ''"
                        x-cloak
                        @click="search = ''; visibleLimit = 25"
                        class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>
        </div>

        <!-- تبويبات الفلترة: الكل | مفتوحة (قيد المراجعة) | تمت التصفية -->
        <nav class="flex border-t border-slate-100 text-xs font-extrabold text-center">
            <button type="button"
                    @click="filter = 'all'; visibleLimit = 25"
                    :class="filter === 'all' ? 'border-b-2 border-secondary text-primary bg-amber-50/30' : 'text-slate-500 hover:text-slate-700'"
                    class="flex-1 py-2.5 transition-colors">
                <span>الكل</span>
                (<span x-text="stats.total_count">0</span>)
            </button>

            <button type="button"
                    @click="filter = 'open'; visibleLimit = 25"
                    :class="filter === 'open' ? 'border-b-2 border-amber-500 text-amber-800 bg-amber-50/40' : 'text-slate-500 hover:text-slate-700'"
                    class="flex-1 py-2.5 transition-colors">
                <span>مفتوحة (قيد المراجعة)</span>
                (<span x-text="stats.open_count">0</span>)
            </button>

            <button type="button"
                    @click="filter = 'settled'; visibleLimit = 25"
                    :class="filter === 'settled' ? 'border-b-2 border-emerald-600 text-emerald-700 bg-emerald-50/40' : 'text-slate-500 hover:text-slate-700'"
                    class="flex-1 py-2.5 transition-colors">
                <span>تمت التصفية</span>
                (<span x-text="stats.settled_count">0</span>)
            </button>
        </nav>
    </div>

    <!-- 2. قائمة بطاقات الكشوفات -->
    <main class="flex-1 p-3 space-y-2.5">

        <!-- حالة التحميل -->
        <div x-show="isLoading" class="py-12 text-center text-slate-400 space-y-2">
            <span class="w-7 h-7 border-2 border-primary border-t-transparent rounded-full animate-spin inline-block"></span>
            <p class="text-xs font-bold">جاري تحميل كشوفات السائقين...</p>
        </div>

        <!-- عرض البطاقات -->
        <template x-if="!isLoading">
            <div class="space-y-2.5">
                <template x-for="m in visibleManifests" :key="m.id">
                    @include('accountant.manifests.partials.manifest-card')
                </template>

                <!-- زر عرض المزيد -->
                <div x-show="filteredManifests.length > visibleLimit" x-cloak class="pt-2 text-center">
                    <button type="button"
                            @click="visibleLimit += 25"
                            class="w-full py-2.5 px-4 rounded-xl bg-white border border-slate-200 hover:border-secondary text-xs font-extrabold text-primary shadow-2xs transition-all">
                        عرض المزيد من الكشوفات (<span x-text="filteredManifests.length - visibleLimit"></span> متبقية)
                    </button>
                </div>

                <!-- حالة عدم وجود كشوفات -->
                <div x-show="filteredManifests.length === 0" x-cloak class="text-center py-12 text-slate-400 space-y-3">
                    <span class="material-symbols-outlined text-4xl text-slate-300">receipt_long</span>
                    <p class="text-xs font-bold text-slate-500">لا توجد كشوفات سائقين مطابقة للبحث أو الفلتر</p>
                    <button type="button"
                            @click="openCreateManifestModal()"
                            class="inline-flex items-center gap-1.5 py-2 px-4 rounded-xl bg-primary text-white text-xs font-extrabold shadow-sm">
                        <span class="material-symbols-outlined text-sm text-secondary">add</span>
                        <span>إنشاء أول كشف سائق</span>
                    </button>
                </div>
            </div>
        </template>

    </main>

    <!-- 3. نافذة إنشاء / تعديل الكشف -->
    @include('accountant.manifests.partials.manifest-modal')
@endsection

@push('scripts')
<script>
    function accountantManifestsApp() {
        const baseShell = typeof window.accountantShell === 'function' ? window.accountantShell() : {};

        return {
            ...baseShell,

            isLoading: true,
            manifests: [],
            drivers: [],
            filter: 'all', // all | open | settled
            search: '',
            visibleLimit: 25,
            showDriverSuggestions: false,

            stats: {
                total_count: 0,
                open_count: 0,
                settled_count: 0,
                total_postpaid_amount: 0,
                total_deductions_amount: 0,
                total_net_driver_amount: 0,
            },

            manifestModal: {
                open: false,
                mode: 'create', // create | edit
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

            async init() {
                if (typeof baseShell.init === 'function') {
                    await baseShell.init.call(this);
                }
                await this.fetchManifests();
            },

            async refreshData() {
                await this.fetchManifests();
            },

            async fetchManifests() {
                this.isLoading = true;
                this.isSyncing = true;
                try {
                    const res = await fetch('{{ route("accountant.api.manifests.list") }}', {
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
                        this.manifests = Array.isArray(data.manifests) ? data.manifests : [];
                        this.drivers = Array.isArray(data.drivers) ? data.drivers : [];
                        this.recalculateStats();
                    }
                } catch (e) {
                    this.showToast('تعذر الاتصال بالسيرفر لجلب الكشوفات.', true);
                } finally {
                    this.isLoading = false;
                    this.isSyncing = false;
                }
            },

            recalculateStats() {
                const totalCount = this.manifests.length;
                let openCount = 0;
                let settledCount = 0;
                let totalPostpaid = 0;
                let totalDeductions = 0;
                let totalNet = 0;

                for (const m of this.manifests) {
                    if (m.status === 'settled') {
                        settledCount++;
                    } else {
                        openCount++;
                    }
                    totalPostpaid += Number(m.total_postpaid_amount || 0);
                    totalDeductions += Number(m.total_deductions || 0);
                    totalNet += Number(m.net_driver_amount || 0);
                }

                this.stats = {
                    total_count: totalCount,
                    open_count: openCount,
                    settled_count: settledCount,
                    total_postpaid_amount: totalPostpaid,
                    total_deductions_amount: totalDeductions,
                    total_net_driver_amount: totalNet,
                };
            },

            get filteredManifests() {
                const q = this.normalizeSearch(this.search);

                return this.manifests.filter((m) => {
                    if (this.filter === 'open' && m.status !== 'open') return false;
                    if (this.filter === 'settled' && m.status !== 'settled') return false;

                    if (!q) return true;

                    const haystack = [
                        m.driver_name || '',
                        m.driver_phone || '',
                        m.source_office_name || '',
                        m.source_branch_name || '',
                        m.manifest_number || '',
                        m.manifest_date || '',
                    ].join(' ').toLowerCase();

                    return this.normalizeSearch(haystack).includes(q);
                });
            },

            get visibleManifests() {
                return this.filteredManifests.slice(0, this.visibleLimit);
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

            openCreateManifestModal() {
                this.manifestModal.mode = 'create';
                this.manifestModal.manifestId = null;
                this.manifestModal.error = '';
                this.manifestModal.attachParcelsCount = 0;
                this.manifestModal.attachPostpaidAmount = 0;
                this.manifestModal.form = {
                    driver_id: '',
                    driver_name: '',
                    driver_phone: '',
                    source_office_name: '',
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

            openEditManifestModal(manifest) {
                this.manifestModal.mode = 'edit';
                this.manifestModal.manifestId = manifest.id;
                this.manifestModal.error = '';
                this.manifestModal.attachParcelsCount = 0;
                this.manifestModal.attachPostpaidAmount = 0;
                this.manifestModal.form = {
                    driver_id: manifest.driver_id || '',
                    driver_name: manifest.driver_name || '',
                    driver_phone: manifest.driver_phone || '',
                    source_office_name: manifest.source_office_name || '',
                    source_branch_name: manifest.source_branch_name || '',
                    manifest_number: manifest.manifest_number || '',
                    manifest_date: manifest.manifest_date || new Date().toISOString().slice(0, 10),
                    notes: manifest.notes || '',
                };
                this.showDriverSuggestions = false;
                this.manifestModal.open = true;
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

                const isEdit = this.manifestModal.mode === 'edit' && this.manifestModal.manifestId;
                const url = isEdit
                    ? `/accountant/api/manifests/${encodeURIComponent(this.manifestModal.manifestId)}`
                    : '{{ route("accountant.api.manifests.store") }}';
                const method = isEdit ? 'PUT' : 'POST';
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                try {
                    const res = await fetch(url, {
                        method,
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token || '',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify(this.manifestModal.form),
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
                            this.manifestModal.error = data.message || 'تعذر حفظ الكشف.';
                        }
                        return;
                    }

                    if (isEdit && data.manifest) {
                        const idx = this.manifests.findIndex((item) => item.id === this.manifestModal.manifestId);
                        if (idx !== -1) {
                            this.manifests.splice(idx, 1, data.manifest);
                        }
                    } else if (data.manifest) {
                        this.manifests.unshift(data.manifest);
                    }

                    // تحديث قائمة السائقين المحلية إذا أُنشئ أو حُدث سائق
                    if (data.driver && data.driver.id) {
                        const dIdx = this.drivers.findIndex((d) => d.id === data.driver.id);
                        if (dIdx !== -1) {
                            this.drivers.splice(dIdx, 1, data.driver);
                        } else {
                            this.drivers.push(data.driver);
                            this.drivers.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
                        }
                    }

                    this.recalculateStats();
                    this.manifestModal.open = false;
                    this.showToast(data.message || 'تم حفظ الكشف بنجاح.', false);
                } catch (err) {
                    this.manifestModal.error = 'تعذر الاتصال بالسيرفر، يرجى التحقق من اتصال الإنترنت.';
                } finally {
                    this.manifestModal.saving = false;
                }
            },

            openManifestDetails(manifest) {
                window.location.href = `/accountant/manifests/${encodeURIComponent(manifest.id)}`;
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

            formatAmount(val) {
                const num = Number(val || 0);
                return num.toLocaleString('en-US', { maximumFractionDigits: 2 });
            },
        };
    }
</script>
@endpush

