@php
    $currentOffice = auth('office')->user();
    $isWaConnected = $currentOffice ? (\Illuminate\Support\Facades\Cache::get('whatsapp_status_office_' . $currentOffice->id) === 'online') : false;
@endphp

{{-- منطق العملاء المميزين (لكل مكتب قائمته الخاصة، تخزين محلي في IndexedDB + مزامنة مع السيرفر) --}}
<script>
    window.mursalFavoritesMixin = () => ({
        hasVipFeature: {{ (auth('office')->check() && auth('office')->user()->hasFeature('vip_customers_tab')) ? 'true' : 'false' }},
        favorites: [],
        favSearchField: null,
        savingFavoritePhone: null,
        favOfficeId: {{ (int) ($currentOffice?->id ?? 0) }},
        favIsSyncing: false,
        favRoutes: {
            list: @json(route('office.api.favorite_customers.index')),
            store: @json(route('office.api.favorite_customers.store')),
        },

        get favStore() {
            return window.MURSAL_STORES?.favorites || 'favoriteCustomers';
        },

        favKey(phone) {
            return this.favOfficeId + ':' + phone;
        },

        async initFavorites() {
            if (!this.hasVipFeature) return;
            await this.loadLocalFavorites();
            this.syncFavorites();
            window.addEventListener('online', () => this.syncFavorites());
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.addEventListener('message', (event) => {
                    if (event.data?.type === 'TRIGGER_SYNC') {
                        this.syncFavorites();
                    }
                });
            }
        },

        async favDb() {
            if (!this.hasVipFeature || !window.MursalDB) return null;
            await window.MursalDB.open();
            return window.MursalDB;
        },

        async loadLocalFavorites() {
            if (!this.hasVipFeature) return;
            try {
                const db = await this.favDb();
                if (!db) return;

                await this.migrateLegacyFavorites(db);

                const rows = await db.getAll(this.favStore);
                this.favorites = rows
                    .filter((r) => r && r.office_id === this.favOfficeId && r.phone && r.name)
                    .sort((a, b) => String(a.name).localeCompare(String(b.name), 'ar'));
            } catch (e) {
                console.warn('Unable to load favorite customers from IndexedDB:', e);
            }
        },

        async migrateLegacyFavorites(db) {
            const legacyKey = 'mursal_favorite_customers_office_' + this.favOfficeId;
            const raw = localStorage.getItem(legacyKey);
            if (!raw) return;
            try {
                const legacy = JSON.parse(raw);
                if (Array.isArray(legacy) && legacy.length) {
                    await db.putMany(this.favStore, legacy
                        .filter((f) => f && f.phone && f.name)
                        .map((f) => this.favRecord(f.phone, f.name, !!f.synced)));
                }
            } catch (e) {
                console.warn('Legacy favorites migration skipped:', e);
            }
            localStorage.removeItem(legacyKey);
        },

        favRecord(phone, name, synced, id = null) {
            return {
                id: id || this.favKey(phone),
                office_id: this.favOfficeId,
                phone: String(phone),
                name: String(name),
                synced: !!synced,
                updated_at: new Date().toISOString(),
            };
        },

        async saveFavoriteLocal(record) {
            const db = await this.favDb();
            if (db) await db.put(this.favStore, record);
            this.favorites = [record, ...this.favorites.filter((f) => f.phone !== record.phone)];
        },

        favDigits(str) {
            return String(str || '')
                .replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d))
                .replace(/[۰-۹]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d))
                .replace(/\D/g, '');
        },

        favNormalizeName(str) {
            return String(str || '')
                .toLowerCase()
                .replace(/[\u200B-\u200F\uFEFF]/g, '')
                .replace(/[\u064B-\u0652\u0640]/g, '')
                .replace(/[أإآ]/g, 'ا')
                .replace(/ى/g, 'ي')
                .replace(/ة/g, 'ه')
                .replace(/\s+/g, ' ')
                .trim();
        },

        async syncFavorites() {
            if (!this.hasVipFeature || !navigator.onLine || this.favIsSyncing) return;
            this.favIsSyncing = true;

            try {
                const unsynced = this.favorites.filter((f) => !f.synced);
                for (const fav of unsynced) {
                    await this.pushFavoriteToServer(fav);
                }

                const res = await fetch(this.favRoutes.list, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) return;
                const data = await res.json().catch(() => ({}));
                if (!Array.isArray(data.customers)) return;

                const merged = new Map();
                data.customers.forEach((c) => {
                    merged.set(String(c.phone), this.favRecord(c.phone, c.name, true, c.id));
                });
                this.favorites.filter((f) => !f.synced).forEach((f) => merged.set(f.phone, f));

                const finalList = Array.from(merged.values());
                const db = await this.favDb();
                if (db) {
                    const staleKeys = this.favorites
                        .filter((f) => !merged.has(f.phone))
                        .map((f) => f.id);
                    if (staleKeys.length) await db.deleteMany(this.favStore, staleKeys);
                    if (finalList.length) await db.putMany(this.favStore, finalList);
                }

                this.favorites = finalList.sort((a, b) => String(a.name).localeCompare(String(b.name), 'ar'));
            } catch (e) {
                console.warn('Favorite customers sync failed:', e);
            } finally {
                this.favIsSyncing = false;
            }
        },

        async pushFavoriteToServer(fav) {
            try {
                const res = await fetch(this.favRoutes.store, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify({ phone: fav.phone, name: fav.name }),
                });

                const db = await this.favDb();

                if (res.ok) {
                    const data = await res.json().catch(() => ({}));
                    const realId = data.customer?.id || fav.id;
                    const synced = { ...fav, id: realId, synced: true };
                    if (db) {
                        if (fav.id && fav.id !== realId) {
                            await db.delete(this.favStore, fav.id);
                        }
                        await db.put(this.favStore, synced);
                    }
                    this.favorites = this.favorites.map((f) => f.phone === fav.phone ? synced : f);
                    return true;
                }

                if (res.status === 422) {
                    if (db) await db.delete(this.favStore, fav.id);
                    this.favorites = this.favorites.filter((f) => f.phone !== fav.phone);
                }
            } catch (e) {
                console.warn('Favorite customer push failed (will retry when online):', e);
            }
            return false;
        },

        isFavoritePhone(phone) {
            const clean = this.favDigits(phone);
            return this.favorites.some((f) => f.phone === clean);
        },

        canAddFavorite(entry) {
            if (!this.hasVipFeature) return false;
            const phone = this.favDigits(entry?.phone);
            const name = String(entry?.recipientName || '').trim();
            return name.length >= 2 && /^7[01378]\d{7}$/.test(phone);
        },

        async addFavoriteFromEntry(entry) {
            if (!this.canAddFavorite(entry)) {
                this.showFeedback('لا يمكن إضافة عميل مميز بدون اسم ورقم صحيح.', true);
                return;
            }

            const phone = this.favDigits(entry.phone);
            const name = entry.recipientName.trim();
            const record = this.favRecord(phone, name, false);

            this.savingFavoritePhone = phone;
            try {
                await this.saveFavoriteLocal(record);
            } catch (e) {
                this.savingFavoritePhone = null;
                this.showFeedback('تعذر حفظ العميل على الجهاز.', true);
                return;
            }

            if (navigator.onLine) {
                await this.pushFavoriteToServer(record);
            } else if (window.MursalSync) {
                await window.MursalSync.enqueue(this.favRoutes.store, { phone, name });
            }

            this.savingFavoritePhone = null;
            this.showFeedback(navigator.onLine
                ? '⭐ تمت إضافة ' + name + ' إلى العملاء المميزين.'
                : '⭐ تم حفظ ' + name + ' على الجهاز وستتم مزامنته عند عودة الإنترنت.', false);
        },

        favoriteSuggestions(field) {
            if (!this.hasVipFeature || this.favSearchField !== field || this.favorites.length === 0) return [];

            const raw = String((field === 'phone' ? this.quickSms?.phone : this.quickSms?.recipientName) || '').trim();
            if (raw.length < 2) return [];

            const digits = this.favDigits(raw);
            const isDigitsOnly = digits.length > 0 && digits.length === raw.replace(/\s/g, '').length;
            const nameTerm = this.favNormalizeName(raw);

            const results = [];
            for (const fav of this.favorites) {
                const matched = isDigitsOnly
                    ? fav.phone.includes(digits)
                    : this.favNormalizeName(fav.name).includes(nameTerm);

                if (!matched) continue;
                if (fav.phone === digits && fav.name === (this.quickSms?.recipientName || '').trim()) continue;

                results.push(fav);
                if (results.length === 3) break;
            }
            return results;
        },

        selectFavorite(fav) {
            this.quickSms.phone = fav.phone;
            this.quickSms.recipientName = fav.name;
            this.favSearchField = null;
        },
    });
</script>

{{-- مودال الإرسال السريع التدريجي للـ SMS (شاشة كاملة تتكيف مع الكيبورد) --}}
<div x-data="{ 
        ...mursalFavoritesMixin(),
        isClosing: false,
        isWaConnected: {{ $isWaConnected ? 'true' : 'false' }},
        waStatusChecked: false,
        checkWhatsAppStatus() {
            fetch('{{ route('office.whatsapp.status') }}?refresh=1', {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(data => {
                this.isWaConnected = !!(data.connected);
                this.waStatusChecked = true;
                // تحديث القناة بناءً على الحالة الحقيقية من السيرفر
                if (this.isWaConnected) {
                    // إذا الواتساب متصل والقناة الحالية SMS فقط، نرقّيها لـ both
                    if (quickSms.channel === 'sms') {
                        quickSms.channel = 'both';
                    }
                } else {
                    // إذا غير متصل، نرجّع القناة لـ sms
                    if (quickSms.channel === 'whatsapp' || quickSms.channel === 'both') {
                        quickSms.channel = 'sms';
                    }
                }
            })
            .catch(() => { this.waStatusChecked = true; });
        }
    }" 
    x-init="quickSms.channel = isWaConnected ? 'both' : 'sms'; checkWhatsAppStatus(); initFavorites()"
    x-show="quickSms.open" 
    x-cloak 
    class="fixed inset-0 z-50 flex flex-col bg-white"
    @open-quick-sms.window="isClosing = false; checkWhatsAppStatus(); loadLocalFavorites(); syncFavorites(); openQuickSmsModal()"
    @keydown.escape.window="if (quickSms.open && !isClosing && streamingPendingCount === 0) resetQuickSms()">

    {{-- خلفية التعتيم للشاشات الكبيرة (Desktop) --}}
    <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm transition-opacity md:block hidden"
        x-show="quickSms.open" @click="if (!isClosing && streamingPendingCount === 0) resetQuickSms()">
    </div>

    {{-- بطاقة المودال: شاشة كاملة على الجوال (h-[100dvh]) وبطاقة مركزية على الديسكتوب --}}
    <div class="relative w-full h-[100dvh] md:h-auto md:max-h-[90vh] md:max-w-md md:rounded-3xl md:m-auto bg-white shadow-2xl z-10 flex flex-col overflow-hidden"
        x-show="quickSms.open" x-transition:enter="ease-out duration-250"
        x-transition:enter-start="opacity-0 translate-y-full md:translate-y-4 md:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 md:scale-100" x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 md:scale-100"
        x-transition:leave-end="opacity-0 translate-y-full md:translate-y-4 md:scale-95">

        {{-- ═══════════════════════════════════
        1. هيدر المودال الثابت بالأعلى
        ═══════════════════════════════════ --}}
        <div
            class="px-5 py-3.5 flex items-center justify-between border-b border-slate-100 bg-white/95 backdrop-blur-sm shrink-0">
            <div class="flex items-center gap-2.5">
                <div
                    class="w-9 h-9 rounded-xl bg-secondary/10 border border-secondary/20 text-secondary flex items-center justify-center">
                    <span class="material-symbols-outlined text-lg">bolt</span>
                </div>
                <div>
                    <h3 class="text-sm font-extrabold text-slate-800 leading-tight">إرسال سريع</h3>
                    <p class="text-[10px] text-slate-400">إدخال مباشر بالخلفية</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                {{-- عداد مصغر --}}
                <template x-if="streamingQueue.length > 0">
                    <div class="flex items-center gap-1 px-2 py-1 rounded-lg bg-emerald-50 border border-emerald-100">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"
                            :class="(streamingPendingCount > 0 || isClosing) && 'animate-pulse bg-amber-500'"></span>
                        <span class="text-[10px] font-extrabold text-emerald-700" x-text="streamingSentCount"></span>
                        <span class="text-[10px] text-slate-400">/</span>
                        <span class="text-[10px] font-bold text-slate-500" x-text="streamingQueue.length"></span>
                    </div>
                </template>

                <button type="button" @click="if (!isClosing) resetQuickSms()" :disabled="isClosing"
                    class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-400 hover:text-slate-600 flex items-center justify-center transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>
        </div>

        {{-- اختيار قناة الإرسال (واتساب / SMS / كلاهما) --}}
        <div class="px-5 pt-2.5 pb-0 bg-white shrink-0">
            <div class="flex p-1 bg-slate-100 rounded-2xl border border-slate-200/80">
                <button type="button" 
                    @click="if (isWaConnected) quickSms.channel = 'whatsapp'"
                    :disabled="!isWaConnected"
                    :class="{
                        'bg-white text-emerald-600 shadow-xs border border-emerald-100 font-extrabold': quickSms.channel === 'whatsapp' && isWaConnected,
                        'text-slate-500 hover:text-slate-700 font-bold': quickSms.channel !== 'whatsapp' && isWaConnected,
                        'opacity-40 cursor-not-allowed text-slate-400': !isWaConnected
                    }"
                    class="flex-1 py-1.5 px-2 rounded-xl text-xs flex items-center justify-center gap-1 transition-all"
                    :title="!isWaConnected ? 'خدمة الواتساب غير متصلة حالياً' : 'إرسال عبر الواتساب فقط'">
                    <span class="material-symbols-outlined text-[15px]">chat</span>
                    <span>واتساب</span>
                    <template x-if="!isWaConnected">
                        <span class="material-symbols-outlined text-[12px] text-slate-400">lock</span>
                    </template>
                </button>
                <button type="button" 
                    @click="quickSms.channel = 'sms'"
                    :class="quickSms.channel === 'sms' ? 'bg-white text-blue-600 shadow-xs border border-blue-100 font-extrabold' : 'text-slate-500 hover:text-slate-700 font-bold'"
                    class="flex-1 py-1.5 px-2 rounded-xl text-xs flex items-center justify-center gap-1 transition-all">
                    <span class="material-symbols-outlined text-[15px]">sms</span>
                    <span>SMS</span>
                </button>
                <button type="button" 
                    @click="if (isWaConnected) quickSms.channel = 'both'"
                    :disabled="!isWaConnected"
                    :class="{
                        'bg-white text-amber-600 shadow-xs border border-amber-100 font-extrabold': quickSms.channel === 'both' && isWaConnected,
                        'text-slate-500 hover:text-slate-700 font-bold': quickSms.channel !== 'both' && isWaConnected,
                        'opacity-40 cursor-not-allowed text-slate-400': !isWaConnected
                    }"
                    class="flex-1 py-1.5 px-2 rounded-xl text-xs flex items-center justify-center gap-1 transition-all"
                    :title="!isWaConnected ? 'خدمة الواتساب غير متصلة حالياً' : 'إرسال عبر الواتساب و SMS معاً'">
                    <span class="material-symbols-outlined text-[15px]">all_inclusive</span>
                    <span>كلاهما</span>
                    <template x-if="!isWaConnected">
                        <span class="material-symbols-outlined text-[12px] text-slate-400">lock</span>
                    </template>
                </button>
            </div>
            <template x-if="!isWaConnected">
                <p class="text-[10px] text-amber-600 font-bold mt-1 px-1 flex items-center gap-1">
                    <span class="material-symbols-outlined text-[12px]">info</span>
                    <span>واتساب غير متصل (متاح SMS فقط). يمكنك ربط الجلسة من الإعدادات.</span>
                </p>
            </template>
        </div>

        {{-- ═══════════════════════════════════
        2. حقول الإدخال السريعة (تبقى واضحة أعلى الكيبورد)
        ═══════════════════════════════════ --}}
        <div class="px-5 pt-2.5 pb-2 space-y-2.5 bg-white shrink-0 border-b border-slate-50">

            {{-- حقل رقم الهاتف --}}
            <div class="relative">
                <label class="block text-[11px] font-bold text-slate-600 mb-1">رقم الهاتف</label>
                <div
                    class="relative rounded-2xl border border-slate-200 bg-slate-50/80 focus-within:bg-white focus-within:border-secondary focus-within:ring-2 focus-within:ring-secondary/20 transition-all">

                    {{-- علم اليمن ومؤشر الحالة معاً في الجهة اليمنى --}}
                    <div
                        class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center gap-1.5 select-none pointer-events-none">
                        <span class="text-xs font-bold">🇾🇪</span>

                        {{-- علامة الصح أو الخطأ بجانب العلم مباشرة --}}
                        <template x-if="quickSms.phone.length === 9 && /^7[01378]/.test(normalizeText(quickSms.phone))">
                            <span
                                class="material-symbols-outlined text-base text-emerald-500 flex items-center">check_circle</span>
                        </template>
                        <template
                            x-if="quickSms.phone.length > 0 && (quickSms.phone.length < 9 || !/^7[01378]/.test(normalizeText(quickSms.phone)))">
                            <span
                                class="material-symbols-outlined text-base text-rose-400 flex items-center">error</span>
                        </template>
                    </div>

                    <input type="tel" id="streaming-phone-input" x-model="quickSms.phone" :disabled="isClosing"
                        @focus="favSearchField = 'phone'"
                        @blur="if (favSearchField === 'phone') favSearchField = null"
                        @input="favSearchField = 'phone'; quickSms.phone = normalizeText(quickSms.phone).replace(/\D/g, '').substring(0, 9)"
                        @keydown.enter.prevent="
                       if (!isClosing && quickSms.phone.length === 9 && /^7[01378]/.test(normalizeText(quickSms.phone))) {
                           favSearchField = null;
                           $refs.streamingDescInput.focus();
                       }
                    " dir="ltr" inputmode="numeric" maxlength="9" autocomplete="off"
                        class="w-full py-2.5 px-4 pr-16 text-sm font-mono font-bold text-slate-800 bg-transparent outline-none placeholder:text-slate-300 placeholder:font-normal disabled:opacity-50"
                        placeholder="7XXXXXXXX" />
                </div>

                @if(auth('office')->check() && auth('office')->user()->hasFeature('vip_customers_tab'))
                {{-- اقتراحات العملاء المميزين عند البحث بالرقم --}}
                <template x-if="favoriteSuggestions('phone').length > 0">
                    <div class="absolute left-0 right-0 top-full mt-1 z-30 bg-white rounded-2xl border border-slate-200 shadow-lg overflow-hidden">
                        <template x-for="fav in favoriteSuggestions('phone')" :key="'fp-' + fav.phone">
                            <button type="button" @mousedown.prevent
                                @click="selectFavorite(fav); $nextTick(() => { $refs.streamingDescInput.focus(); $refs.streamingDescInput.select(); })"
                                class="w-full flex items-center gap-2 px-3 py-2 text-right hover:bg-amber-50 active:bg-amber-100 border-b border-slate-50 last:border-b-0 transition-colors">
                                <span class="material-symbols-outlined text-[16px] text-amber-500">star</span>
                                <span class="flex-1 truncate text-xs font-bold text-slate-700" x-text="fav.name"></span>
                                <span class="text-xs font-mono font-bold text-slate-500" dir="ltr" x-text="fav.phone"></span>
                            </button>
                        </template>
                    </div>
                </template>
                @endif
            </div>

            {{-- حقل نوع الطرد + اسم المستلم (اختياري) في صف واحد للحفاظ على نفس التصميم والارتفاع --}}
            <div class="relative grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">نوع الطرد</label>
                    <input type="text" x-ref="streamingDescInput" x-model="quickSms.description" :disabled="isClosing" @keydown.enter.prevent="
                        if (isClosing) return;
                        const phone = normalizeText(quickSms.phone).replace(/\D/g, '');
                        if (phone.length === 9 && /^7[01378]/.test(phone)) {
                            addToStreamingQueue(phone, quickSms.description || 'طرد', quickSms.recipientName || '', quickSms.codAmount || null);
                            quickSms.phone = '';
                            quickSms.description = '';
                            quickSms.recipientName = '';
                            quickSms.codAmount = '';
                            $nextTick(() => document.getElementById('streaming-phone-input').focus());
                        } else {
                            showFeedback('رقم الهاتف غير صالح', true);
                            document.getElementById('streaming-phone-input').focus();
                        }
                    " dir="rtl" autocomplete="off"
                        class="w-full py-2.5 px-3.5 text-xs font-bold text-slate-700 rounded-2xl border border-slate-200 bg-slate-50/80 focus-within:bg-white outline-none focus:border-secondary focus:ring-2 focus:ring-secondary/20 placeholder:text-slate-300 transition-all disabled:opacity-50"
                        placeholder="طرد" />
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">
                        اسم المستلم <span class="text-[10px] font-normal text-slate-400">(اختياري)</span>
                    </label>
                    <input type="text" x-ref="streamingRecipientInput" x-model="quickSms.recipientName" :disabled="isClosing"
                        @focus="favSearchField = 'name'"
                        @blur="if (favSearchField === 'name') favSearchField = null"
                        @input="favSearchField = 'name'"
                        @keydown.enter.prevent="
                        if (isClosing) return;
                        const phone = normalizeText(quickSms.phone).replace(/\D/g, '');
                        if (phone.length === 9 && /^7[01378]/.test(phone)) {
                            addToStreamingQueue(phone, quickSms.description || 'طرد', quickSms.recipientName || '', quickSms.codAmount || null);
                            quickSms.phone = '';
                            quickSms.description = '';
                            quickSms.recipientName = '';
                            quickSms.codAmount = '';
                            $nextTick(() => document.getElementById('streaming-phone-input').focus());
                        } else {
                            showFeedback('رقم الهاتف غير صالح', true);
                            document.getElementById('streaming-phone-input').focus();
                        }
                    " dir="rtl" autocomplete="off"
                        class="w-full py-2.5 px-3.5 text-xs font-bold text-slate-700 rounded-2xl border border-slate-200 bg-slate-50/80 focus-within:bg-white outline-none focus:border-secondary focus:ring-2 focus:ring-secondary/20 placeholder:text-slate-300 transition-all disabled:opacity-50"
                        placeholder="بدون اسم" />
                </div>

                @if(auth('office')->check() && auth('office')->user()->hasFeature('vip_customers_tab'))
                {{-- اقتراحات العملاء المميزين عند البحث بالاسم --}}
                <template x-if="favoriteSuggestions('name').length > 0">
                    <div class="absolute left-0 right-0 top-full mt-1 z-30 bg-white rounded-2xl border border-slate-200 shadow-lg overflow-hidden">
                        <template x-for="fav in favoriteSuggestions('name')" :key="'fn-' + fav.phone">
                            <button type="button" @mousedown.prevent
                                @click="selectFavorite(fav); $nextTick(() => { $refs.streamingDescInput.focus(); $refs.streamingDescInput.select(); })"
                                class="w-full flex items-center gap-2 px-3 py-2 text-right hover:bg-amber-50 active:bg-amber-100 border-b border-slate-50 last:border-b-0 transition-colors">
                                <span class="material-symbols-outlined text-[16px] text-amber-500">star</span>
                                <span class="flex-1 truncate text-xs font-bold text-slate-700" x-text="fav.name"></span>
                                <span class="text-xs font-mono font-bold text-slate-500" dir="ltr" x-text="fav.phone"></span>
                            </button>
                        </template>
                    </div>
                </template>
                @endif
            </div>

            @if(auth('office')->check() && auth('office')->user()->hasFeature('parcel_cod_amount'))
            {{-- حقل المبلغ المطلوب عند التسليم (اختياري) --}}
            <div class="relative">
                <label class="block text-[11px] font-bold text-amber-700 mb-1 flex items-center justify-between">
                    <span class="flex items-center gap-1">
                        <span class="material-symbols-outlined text-[15px] text-amber-600">payments</span>
                        <span>المبلغ المطلوب عند التسليم <span class="text-[10px] font-normal text-slate-400">(اختياري)</span></span>
                    </span>
                    <span class="text-[10px] font-mono text-amber-700 font-bold" x-show="quickSms.codAmount && Number(quickSms.codAmount) > 0" x-text="Number(quickSms.codAmount).toLocaleString('en-US') + ' ر.ي'"></span>
                </label>
                <div class="relative rounded-2xl border border-slate-200 bg-slate-50/80 focus-within:bg-white focus-within:border-secondary focus-within:ring-2 focus-within:ring-secondary/20 transition-all">
                    <input type="number" step="any" min="0" inputmode="numeric"
                           x-ref="streamingCodInput"
                           x-model="quickSms.codAmount"
                           :disabled="isClosing"
                           @keydown.enter.prevent="
                               if (isClosing) return;
                               const phone = normalizeText(quickSms.phone).replace(/\D/g, '');
                               if (phone.length === 9 && /^7[01378]/.test(phone)) {
                                   addToStreamingQueue(phone, quickSms.description || 'طرد', quickSms.recipientName || '', quickSms.codAmount || null);
                                   quickSms.phone = '';
                                   quickSms.description = '';
                                   quickSms.recipientName = '';
                                   quickSms.codAmount = '';
                                   $nextTick(() => document.getElementById('streaming-phone-input').focus());
                               } else {
                                   showFeedback('أدخل رقم هاتف صالح', true);
                                   document.getElementById('streaming-phone-input').focus();
                               }
                           "
                           dir="ltr"
                           class="w-full py-2.5 px-3.5 pl-10 text-xs font-mono font-bold text-slate-800 bg-transparent outline-none placeholder:text-slate-300 placeholder:font-normal disabled:opacity-50"
                           placeholder="مثال: 5000 (اتركه فارغاً إن لم يوجد)" />
                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[10px] font-bold text-slate-400 select-none pointer-events-none">ر.ي</span>
                </div>
            </div>
            @endif

            {{-- رقائق الوصف السريع الذكية (متدرجة: الفئة -> اللون -> الحجم) --}}
            <div x-data="{
                catalog: {
                    'كيس': ['أبيض', 'أسود', 'أزرق', 'أحمر', 'بني', 'بنفسجي', 'أصفر', 'وردي', 'رمادي', 'شفاف'],
                    'ضرف': ['أبيض', 'كاكي'],
                    'كرتون': ['كاكي', 'أبيض']
                },
                sizes: ['كبير', 'وسط', 'صغير'],
                norm(str) {
                    return (str || '').replace(/[أإآ]/g, 'ا');
                },
                get activeCategory() {
                    const desc = (this.quickSms?.description || '').trim();
                    if (desc === 'ظرف' || desc.startsWith('ظرف ')) return 'ضرف';
                    return Object.keys(this.catalog).find(cat => desc === cat || desc.startsWith(cat + ' ')) || null;
                },
                get activeColor() {
                    if (!this.activeCategory) return null;
                    const parts = (this.quickSms?.description || '').trim().split(/\s+/);
                    if (parts.length < 2) return null;
                    const colors = this.catalog[this.activeCategory] || [];
                    return colors.find(c => this.norm(c) === this.norm(parts[1])) || null;
                },
                get activeSize() {
                    if (!this.activeColor) return null;
                    const parts = (this.quickSms?.description || '').trim().split(/\s+/);
                    if (parts.length < 3) return null;
                    return this.sizes.find(s => s === parts[2]) || null;
                }
            }" class="flex items-center gap-1.5 overflow-x-auto pb-0.5 scrollbar-none">

                {{-- المستوى 1: الفئات الرئيسية (كيس، ضرف، كرتون) --}}
                <template x-if="!activeCategory">
                    <div class="flex items-center gap-1.5 shrink-0">
                        <template x-for="cat in Object.keys(catalog)" :key="cat">
                            <button type="button" :disabled="isClosing" @click="
                                quickSms.description = cat + ' ';
                                $refs.streamingDescInput.focus();
                            " class="px-3 py-1 rounded-xl bg-slate-100 hover:bg-secondary/10 hover:text-secondary text-slate-700 text-[11px] font-bold shrink-0 transition-colors active:scale-95 disabled:opacity-40 flex items-center gap-1">
                                <span x-text="cat"></span>
                                <span class="text-[9px] text-slate-400">◀</span>
                            </button>
                        </template>
                    </div>
                </template>

                {{-- المستوى 2: اختيار اللون للفئة المختارة --}}
                <template x-if="activeCategory && !activeColor">
                    <div class="flex items-center gap-1.5 shrink-0">
                        <button type="button" :disabled="isClosing" @click="
                            quickSms.description = '';
                            $refs.streamingDescInput.focus();
                        " class="px-2 py-1 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 text-[11px] font-bold shrink-0 transition-colors active:scale-95 disabled:opacity-40" title="تغيير النوع">
                            <span x-text="activeCategory + ' ✕'"></span>
                        </button>

                        <template x-for="color in catalog[activeCategory]" :key="color">
                            <button type="button" :disabled="isClosing" @click="
                                quickSms.description = activeCategory + ' ' + color;
                                $refs.streamingDescInput.focus();
                            " class="px-2.5 py-1 rounded-xl bg-slate-100 hover:bg-secondary/10 hover:text-secondary text-slate-700 text-[11px] font-bold shrink-0 transition-colors active:scale-95 disabled:opacity-40 flex items-center gap-1">
                                <span x-text="color"></span>
                                <span class="text-[9px] text-slate-400">◀</span>
                            </button>
                        </template>
                    </div>
                </template>

                {{-- المستوى 3: اختيار الحجم (كبير، وسط، صغير) بعد اختيار اللون --}}
                <template x-if="activeCategory && activeColor">
                    <div class="flex items-center gap-1.5 shrink-0">
                        <button type="button" :disabled="isClosing" @click="
                            quickSms.description = '';
                            $refs.streamingDescInput.focus();
                        " class="px-2 py-1 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 text-[11px] font-bold shrink-0 transition-colors active:scale-95 disabled:opacity-40" title="تغيير الفئة">
                            <span x-text="activeCategory + ' ✕'"></span>
                        </button>

                        <button type="button" :disabled="isClosing" @click="
                            quickSms.description = activeCategory + ' ';
                            $refs.streamingDescInput.focus();
                        " class="px-2 py-1 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-700 text-[11px] font-bold shrink-0 transition-colors active:scale-95 disabled:opacity-40" title="تغيير اللون">
                            <span x-text="activeColor + ' ✕'"></span>
                        </button>

                        <template x-for="size in sizes" :key="size">
                            <button type="button" :disabled="isClosing" @click="
                                quickSms.description = activeCategory + ' ' + activeColor + ' ' + size;
                                $refs.streamingDescInput.focus();
                            " :class="activeSize === size
                                ? 'bg-secondary text-white shadow-sm'
                                : 'bg-slate-100 hover:bg-secondary/10 hover:text-secondary text-slate-700'"
                            class="px-2.5 py-1 rounded-xl text-[11px] font-bold shrink-0 transition-colors active:scale-95 disabled:opacity-40">
                                <span x-text="size"></span>
                            </button>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        {{-- زر الإضافة اليدوي --}}
        <button type="button" @click="
                if (isClosing) return;
                const phone = normalizeText(quickSms.phone).replace(/\D/g, '');
                if (phone.length === 9 && /^7[01378]/.test(phone)) {
                    addToStreamingQueue(phone, quickSms.description || 'طرد', quickSms.recipientName || '', quickSms.codAmount || null);
                    quickSms.phone = '';
                    quickSms.description = '';
                    quickSms.recipientName = '';
                    quickSms.codAmount = '';
                    $nextTick(() => document.getElementById('streaming-phone-input').focus());
                } else {
                    showFeedback('أدخل رقم هاتف صالح', true);
                }
            " :disabled="quickSms.phone.length < 9 || isClosing"
            class="w-full py-2.5 px-4 bg-secondary hover:bg-amber-500 active:scale-[0.98] text-white font-extrabold text-xs rounded-xl shadow-md shadow-secondary/20 flex items-center justify-center gap-1.5 transition-all disabled:opacity-30 disabled:cursor-not-allowed">
            <span class="material-symbols-outlined text-base">add_circle</span>
            <span>إضافة واعتماد الطرد (Enter)</span>
        </button>


        {{-- ═══════════════════════════════════
        3. القائمة الحية (Scrollable Area)
        ═══════════════════════════════════ --}}
        <div class="flex-1 overflow-y-auto px-5 py-3 space-y-1.5 min-h-0 bg-slate-50/50">
            {{-- حالة عدم وجود طرود --}}
            <div x-show="streamingQueue.length === 0"
                class="flex flex-col items-center justify-center h-full min-h-[140px] text-center text-slate-400">
                <span class="material-symbols-outlined text-3xl mb-1 text-slate-300">playlist_add</span>
                <p class="text-xs font-bold text-slate-500">القائمة فارغة حالياً</p>
                <p class="text-[10px] text-slate-400">الطرود التي تدخلها ستظهر هنا وتُرسل مباشرة</p>
            </div>

            {{-- بنود الطرود المضافة --}}
            <template x-for="entry in streamingQueue" :key="entry.id">
                <div class="flex items-center gap-2.5 bg-white rounded-xl px-3 py-2 border shadow-2xs transition-all"
                    :class="{
                         'border-slate-100': entry.status === 'pending',
                         'border-emerald-200 bg-emerald-50/30': entry.status === 'success',
                         'border-rose-200 bg-rose-50/30': entry.status === 'failed',
                     }">

                    {{-- أيقونة الحالة --}}
                    <div class="shrink-0 w-6 h-6 rounded-lg flex items-center justify-center" :class="{
                             'bg-amber-100': entry.status === 'pending',
                             'bg-emerald-100': entry.status === 'success',
                             'bg-rose-100': entry.status === 'failed',
                         }">
                        <template x-if="entry.status === 'pending'">
                            <span
                                class="w-3.5 h-3.5 border-2 border-amber-500 border-t-transparent rounded-full animate-spin"></span>
                        </template>
                        <template x-if="entry.status === 'success'">
                            <span class="material-symbols-outlined text-sm text-emerald-600">check_circle</span>
                        </template>
                        <template x-if="entry.status === 'failed'">
                            <span class="material-symbols-outlined text-sm text-rose-500">error</span>
                        </template>
                    </div>

                    {{-- رقم الهاتف --}}
                    <span class="text-xs font-mono font-bold text-slate-700 dir-ltr shrink-0"
                        x-text="entry.phone"></span>

                    {{-- نوع الطرد واسم المستلم إن وُجد --}}
                    <span class="text-[11px] font-bold text-slate-500 flex-1 truncate"
                        x-text="entry.recipientName ? (entry.description + ' • ' + entry.recipientName) : entry.description"></span>

                    @if(auth('office')->check() && auth('office')->user()->hasFeature('parcel_cod_amount'))
                    {{-- شارة المبلغ المطلوب عند التسليم في القائمة الحية --}}
                    <template x-if="entry.codAmount && Number(entry.codAmount) > 0">
                        <span class="px-1.5 py-0.5 rounded-md bg-amber-50 text-amber-700 border border-amber-200 text-[10px] font-bold font-mono shrink-0 flex items-center gap-0.5" dir="ltr">
                            <span>💰</span>
                            <span x-text="Number(entry.codAmount).toLocaleString('en-US')"></span>
                            <span>ر.ي</span>
                        </span>
                    </template>
                    @endif

                    {{-- زر إعادة المحاولة --}}
                    <template x-if="entry.status === 'failed'">
                        <button type="button" @click="retryStreamingEntry(entry)" :disabled="isClosing"
                            class="shrink-0 px-2 py-1 rounded-lg bg-rose-100 hover:bg-rose-200 text-rose-600 text-[10px] font-bold flex items-center gap-0.5 disabled:opacity-40">
                            <span class="material-symbols-outlined text-xs">refresh</span>
                            <span>إعادة</span>
                        </button>
                    </template>

                    {{-- مؤشرات نجاح الإرسال (واتساب / SMS) --}}
                    <div class="shrink-0 flex items-center gap-1">
                        <template x-if="entry.status === 'success' && entry.whatsappSent">
                            <span class="px-1.5 py-0.5 rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold">واتساب ✓</span>
                        </template>
                        <template x-if="entry.status === 'success' && entry.smsSent">
                            <span class="px-1.5 py-0.5 rounded-md bg-blue-50 text-blue-700 border border-blue-200 text-[10px] font-bold">SMS ✓</span>
                        </template>

                        @if(auth('office')->check() && auth('office')->user()->hasFeature('vip_customers_tab'))
                        {{-- إضافة كعميل مميز (يتطلب اسم + رقم) --}}
                        <template x-if="canAddFavorite(entry) && isFavoritePhone(entry.phone)">
                            <span class="w-6 h-6 rounded-md bg-amber-50 border border-amber-200 text-amber-500 flex items-center justify-center" title="عميل مميز">
                                <span class="material-symbols-outlined text-[14px]" style="font-variation-settings: 'FILL' 1;">star</span>
                            </span>
                        </template>
                        <template x-if="canAddFavorite(entry) && !isFavoritePhone(entry.phone)">
                            <button type="button" @click="addFavoriteFromEntry(entry)"
                                :disabled="savingFavoritePhone === entry.phone"
                                class="w-6 h-6 rounded-md bg-slate-100 hover:bg-amber-100 hover:text-amber-600 text-slate-500 border border-slate-200 flex items-center justify-center transition-colors active:scale-90 disabled:opacity-40"
                                title="إضافة كعميل مميز">
                                <span class="material-symbols-outlined text-[14px]">person_add</span>
                            </button>
                        </template>
                        @endif
                    </div>
                </div>
            </template>
        </div>

        {{-- ═══════════════════════════════════
        4. الفوتر الثابت بالأسفل (إغلاق وإنهاء مع لودنق 5 ثوانٍ)
        ═══════════════════════════════════ --}}
        <div class="p-3 border-t border-slate-100 bg-white shrink-0">
            <button type="button" :disabled="streamingPendingCount > 0 || isClosing" @click="
                        if (streamingPendingCount > 0 || isClosing) {
                            return;
                        }

                        // إذا لم يتم إدخال أي طرد، نغلق المودال فوراً بدون انتظار 5 ثوانٍ
                        if (streamingQueue.length === 0) {
                            resetQuickSms();
                            return;
                        }

                        // تفعيل اللودنق لمدة 5 ثوانٍ لضمان وصول آخر رسالة SMS
                        isClosing = true;

                        setTimeout(() => {
                            // حفظ إحصائيات الإرسال قبل تصفير القائمة
                            const totalSent = streamingSentCount;
                            const totalSms = streamingQueue.filter(e => e.status === 'success' && e.smsSent).length;

                            // تحديث قائمة الطرود الرئيسية بالصفحة
                            if (typeof refreshFromServer === 'function' && isOnline) {
                                refreshFromServer();
                            }

                            // إغلاق المودال وتصفير الكشف وحالة اللودنق
                            isClosing = false;
                            resetQuickSms();
                            streamingQueue = [];
                            streamingIdCounter = 0;

                            // عرض رسالة التأكيد للمستخدم
                            if (totalSent > 0) {
                                showFeedback('✅ تم بنجاح تسجيل ' + totalSent + ' طرد وإرسال ' + totalSms + ' رسالة SMS للمستلمين.', false);
                            }
                        }, 5000);
                    "
                class="w-full py-2.5 px-4 font-extrabold text-xs rounded-xl flex items-center justify-center gap-2 transition-all active:scale-[0.98]"
                :class="(streamingPendingCount > 0 || isClosing)
                        ? 'bg-amber-50 text-amber-700 border border-amber-200 cursor-wait'
                        : 'bg-slate-900 hover:bg-slate-800 text-white shadow-sm'
                    ">

                {{-- أيقونة اللودنق أثناء إرسال الطرود أو أثناء مهلة الـ 5 ثوانٍ --}}
                <template x-if="streamingPendingCount > 0 || isClosing">
                    <span class="w-4 h-4 border-2 border-amber-600 border-t-transparent rounded-full animate-spin"></span>
                </template>

                {{-- أيقونة الإنهاء الطبيعية --}}
                <template x-if="streamingPendingCount === 0 && !isClosing">
                    <span class="material-symbols-outlined text-base">done_all</span>
                </template>

                {{-- النص المتغير حسب الحالة --}}
                <span x-text="
                    streamingPendingCount > 0
                        ? 'جارِ إرسال ' + streamingPendingCount + ' طرد...'
                        : (isClosing ? 'جاري إرسال رسائل SMS للعملاء...' : 'إنهاء الكشف وإغلاق')
                "></span>
            </button>
        </div>

    </div>
</div>