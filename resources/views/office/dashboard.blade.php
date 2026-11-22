@extends('office.layouts.app')

@section('title', 'لوحة تحكم المكتب')

@section('shell-class', 'bg-slate-50')

@section('header-search')
    <div x-data="{
            q: '',
            searching: false,
            _t: null,
            normDigits(str) {
                const ar = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
                const fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
                return String(str || '')
                    .replace(/[٠-٩]/g, d => ar.indexOf(d))
                    .replace(/[۰-۹]/g, d => fa.indexOf(d))
                    .replace(/[\u200B-\u200D\uFEFF\u200E\u200F]/g, '');
            },
            onInput() {
                this.q = this.normDigits(this.q);
                if (this._t) clearTimeout(this._t);
                if (this.q.trim() === '') {
                    this.emit();
                    return;
                }
                this._t = setTimeout(() => this.emit(), 350);
            },
            emit() {
                if (this._t) clearTimeout(this._t);
                window.dispatchEvent(new CustomEvent('dashboard-global-search', { detail: { q: this.q } }));
            },
            clear() {
                this.q = '';
                this.emit();
            }
        }"
        @dashboard-global-search-clear.window="clear()"
        @dashboard-global-search-loading.window="searching = Boolean($event.detail?.loading)"
        class="mt-3 relative">
        <input type="text"
               x-model="q"
               @input="onInput()"
               @keydown.enter.prevent="emit()"
               dir="rtl"
               autocomplete="off"
               placeholder="بحث شامل: رقم السند، اسم أو رقم المرسل والمستلم..."
               class="w-full bg-slate-800 text-white placeholder-slate-400 text-xs rounded-xl pr-9 pl-9 py-2.5 outline-none border border-slate-700 focus:border-secondary transition-all">
        <span class="material-symbols-outlined absolute right-2.5 top-2.5 text-slate-400 text-lg pointer-events-none">search</span>

        <div class="absolute left-2.5 top-1/2 -translate-y-1/2 flex items-center gap-1">
            <span x-show="searching" x-cloak class="w-3.5 h-3.5 border-2 border-secondary border-t-transparent rounded-full animate-spin"></span>
            <template x-if="q.length > 0 && !searching">
                <button type="button"
                        @click="clear()"
                        title="مسح البحث"
                        class="w-5 h-5 rounded-full bg-slate-700 hover:bg-rose-500/20 text-slate-300 hover:text-rose-400 flex items-center justify-center text-xs font-bold transition-colors">
                    ✕
                </button>
            </template>
        </div>
    </div>
@endsection

@section('content')
    <!-- المحتوى والبطاقات الإحصائية + نتائج البحث الشامل -->
    <main class="flex-1 p-4 space-y-4"
          x-data="dashboardGlobalSearch({ searchUrl: '{{ route('office.global-search') }}' })"
          @dashboard-global-search.window="runSearch($event.detail?.q || '')">

        {{-- لوحة نتائج البحث الشامل الحية (تظهر فقط عند كتابة بحث) --}}
        <section x-show="isActive" x-cloak class="space-y-3.5">
            <div class="flex items-center justify-between gap-2 bg-white border border-slate-200/90 rounded-2xl px-3.5 py-2.5 shadow-2xs">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="material-symbols-outlined text-lg text-secondary shrink-0">manage_search</span>
                    <div class="min-w-0">
                        <p class="text-xs font-extrabold text-slate-800 truncate">
                            نتائج البحث الشامل
                            <span x-show="!isLoading" class="text-secondary font-mono mr-1" x-text="'(' + results.total + ')'"></span>
                        </p>
                        <p class="text-[10px] text-slate-400 truncate" x-text="'عبارة البحث: ' + query"></p>
                    </div>
                </div>
                <button type="button"
                        @click="clearSearch()"
                        class="shrink-0 inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 text-[11px] font-extrabold transition-colors">
                    <span class="material-symbols-outlined text-sm">close</span>
                    <span>مسح البحث</span>
                </button>
            </div>

            {{-- مؤشر التحميل --}}
            <div x-show="isLoading" class="bg-white rounded-2xl border border-slate-200/80 p-8 text-center space-y-2">
                <span class="w-6 h-6 border-2 border-secondary border-t-transparent rounded-full animate-spin inline-block"></span>
                <p class="text-xs font-bold text-slate-500">جاري البحث في جميع طرود المكتب...</p>
            </div>

            {{-- حالة عدم وجود نتائج --}}
            <div x-show="!isLoading && hasSearched && results.total === 0"
                 class="bg-white rounded-2xl border border-slate-200/80 p-8 text-center space-y-2">
                <div class="w-12 h-12 mx-auto rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center">
                    <span class="material-symbols-outlined text-2xl">search_off</span>
                </div>
                <p class="text-xs font-extrabold text-slate-700" x-text="'لا توجد طرود مطابقة لـ &quot;' + query + '&quot;'"></p>
                <p class="text-[11px] text-slate-400">جرّب رقم السند أو اسم أو جوال المرسل أو المستلم.</p>
            </div>

            {{-- المجموعة 1: قادم في الطريق إلينا --}}
            <div x-show="!isLoading && results.in_transit.length > 0" class="space-y-2">
                <div class="flex items-center justify-between px-1">
                    <h3 class="text-xs font-extrabold text-sky-800 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-base text-sky-600">local_shipping</span>
                        <span>قادم في الطريق إلينا</span>
                    </h3>
                    <span class="px-2 py-0.5 rounded-full bg-sky-100 text-sky-800 text-[10px] font-mono font-black"
                          x-text="results.in_transit.length"></span>
                </div>

                <template x-for="item in results.in_transit" :key="'tr-' + item.id">
                    <div class="bg-white border border-sky-200 rounded-2xl p-3 shadow-2xs space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="text-xs font-extrabold text-slate-900" x-text="item.package_type"></span>
                                    <span x-show="item.receipt_number"
                                          class="px-1.5 py-0.5 rounded bg-slate-100 border border-slate-200 font-mono text-[10px] font-bold text-slate-700"
                                          x-text="'سند #' + item.receipt_number"></span>
                                </div>
                                <p class="text-[10px] text-sky-700 font-bold mt-0.5 truncate" x-text="'قادم من: ' + item.from_office"></p>
                            </div>
                            <span class="px-2 py-0.5 rounded-md bg-sky-100 text-sky-800 border border-sky-200 text-[10px] font-extrabold shrink-0">في الطريق</span>
                        </div>

                        <div class="grid grid-cols-2 gap-2 bg-slate-50 rounded-xl p-2 text-[11px]">
                            <div class="min-w-0">
                                <span class="text-[9px] text-slate-400 font-bold block">المرسل</span>
                                <span class="font-bold text-slate-700 truncate block" x-text="item.sender_name || '—'"></span>
                                <span class="font-mono text-[10px] text-slate-500 dir-ltr block text-right" x-text="item.sender_phone"></span>
                            </div>
                            <div class="min-w-0 border-r border-slate-200 pr-2">
                                <span class="text-[9px] text-slate-400 font-bold block">المستلم</span>
                                <span class="font-bold text-slate-800 truncate block" x-text="item.recipient_name"></span>
                                <span class="font-mono text-[10px] text-slate-500 dir-ltr block text-right" x-text="item.recipient_phone"></span>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-2 pt-0.5">
                            <span class="text-[11px] font-extrabold px-2 py-0.5 rounded-md border"
                                  :class="item.payment_type === 'deferred' ? 'bg-amber-50 text-amber-800 border-amber-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'"
                                  x-text="item.payment_label + ': ' + item.cod_formatted + ' ر.ي'"></span>
                            <a :href="item.url"
                               class="px-3 py-1.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-[11px] font-extrabold inline-flex items-center gap-1 transition-colors">
                                <span class="material-symbols-outlined text-sm">download_done</span>
                                <span>استلام</span>
                            </a>
                        </div>
                    </div>
                </template>
            </div>

            {{-- المجموعة 2: الطرود الواردة بالمكتب --}}
            <div x-show="!isLoading && results.incoming.length > 0" class="space-y-2">
                <div class="flex items-center justify-between px-1">
                    <h3 class="text-xs font-extrabold text-emerald-800 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-base text-emerald-600">move_to_inbox</span>
                        <span>الطرود الواردة بالمكتب</span>
                    </h3>
                    <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-mono font-black"
                          x-text="results.incoming.length"></span>
                </div>

                <template x-for="item in results.incoming" :key="'in-' + item.id">
                    <a :href="item.url"
                       class="block bg-white border border-slate-200/90 hover:border-secondary rounded-2xl p-3 shadow-2xs space-y-1.5 transition-colors">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="text-xs font-extrabold text-slate-900" x-text="item.package_type"></span>
                                    <span x-show="item.receipt_number"
                                          class="px-1.5 py-0.5 rounded bg-slate-100 border border-slate-200 font-mono text-[10px] font-bold text-slate-700"
                                          x-text="'سند #' + item.receipt_number"></span>
                                </div>
                                <p x-show="item.source_name" class="text-[10px] text-slate-500 font-bold mt-0.5 truncate" x-text="'المصدر: ' + item.source_name"></p>
                            </div>
                            <span class="px-2 py-0.5 rounded-md border text-[10px] font-extrabold shrink-0"
                                  :class="item.status === 'delivered'
                                      ? 'bg-emerald-100 text-emerald-800 border-emerald-200'
                                      : (item.status === 'returned'
                                          ? 'bg-rose-100 text-rose-800 border-rose-200'
                                          : 'bg-amber-100 text-amber-800 border-amber-200')"
                                  x-text="item.status_label"></span>
                        </div>
                        <div class="flex items-center justify-between text-[11px] bg-slate-50 rounded-xl px-2.5 py-1.5">
                            <div class="min-w-0 truncate">
                                <span class="font-bold text-slate-800" x-text="item.recipient_name"></span>
                                <span class="font-mono text-slate-500 mr-1.5" dir="ltr" x-text="item.recipient_phone"></span>
                            </div>
                            <span class="text-[10px] text-slate-400 font-semibold shrink-0" x-text="item.created_at_human"></span>
                        </div>
                    </a>
                </template>
            </div>

            {{-- المجموعة 3: الطرود الصادرة --}}
            <div x-show="!isLoading && results.outgoing.length > 0" class="space-y-2">
                <div class="flex items-center justify-between px-1">
                    <h3 class="text-xs font-extrabold text-indigo-800 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-base text-indigo-600">outbox</span>
                        <span>الطرود الصادرة</span>
                    </h3>
                    <span class="px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800 text-[10px] font-mono font-black"
                          x-text="results.outgoing.length"></span>
                </div>

                <template x-for="item in results.outgoing" :key="'out-' + item.id">
                    <a :href="item.url"
                       class="block bg-white border border-slate-200/90 hover:border-secondary rounded-2xl p-3 shadow-2xs space-y-2 transition-colors">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="text-xs font-extrabold text-slate-900" x-text="item.package_type"></span>
                                    <span x-show="item.receipt_number"
                                          class="px-1.5 py-0.5 rounded bg-slate-100 border border-slate-200 font-mono text-[10px] font-bold text-slate-700"
                                          x-text="'سند #' + item.receipt_number"></span>
                                </div>
                                <p class="text-[10px] text-indigo-700 font-bold mt-0.5 truncate"
                                   x-text="(item.is_external ? 'خارجي: ' : 'فرع: ') + item.destination"></p>
                            </div>
                            <div class="flex flex-col items-end gap-1 shrink-0">
                                <span class="px-2 py-0.5 rounded-md border text-[10px] font-extrabold"
                                      :class="item.status === 'pending'
                                          ? 'bg-amber-100 text-amber-800 border-amber-200'
                                          : (item.status === 'in_transit'
                                              ? 'bg-sky-100 text-sky-800 border-sky-200'
                                              : (item.status === 'cancelled'
                                                  ? 'bg-rose-100 text-rose-800 border-rose-200'
                                                  : 'bg-emerald-100 text-emerald-800 border-emerald-200'))"
                                      x-text="item.status_label"></span>
                                <span x-show="item.linked_status_label"
                                      class="text-[9px] font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 px-1.5 py-0.5 rounded"
                                      x-text="item.linked_status_label"></span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 bg-slate-50 rounded-xl p-2 text-[11px]">
                            <div class="min-w-0">
                                <span class="text-[9px] text-slate-400 font-bold block">المرسل</span>
                                <span class="font-bold text-slate-700 truncate block" x-text="item.sender_name || '—'"></span>
                                <span class="font-mono text-[10px] text-slate-500 dir-ltr block text-right" x-text="item.sender_phone"></span>
                            </div>
                            <div class="min-w-0 border-r border-slate-200 pr-2">
                                <span class="text-[9px] text-slate-400 font-bold block">المستلم</span>
                                <span class="font-bold text-slate-800 truncate block" x-text="item.recipient_name"></span>
                                <span class="font-mono text-[10px] text-slate-500 dir-ltr block text-right" x-text="item.recipient_phone"></span>
                            </div>
                        </div>
                    </a>
                </template>
            </div>
        </section>

        {{-- المحتوى الاعتيادي للوحة التحكم (يظهر عندما يكون حقل البحث فارغاً) --}}
        <div x-show="!isActive" class="space-y-4">

      @php
    $transitCountInt = (int) ($inTransitCount ?? 0);
    $dashboardTransitUrl = \Illuminate\Support\Facades\Route::has('office.transit-parcels.index')
        ? route('office.transit-parcels.index')
        : url('/office/transit-parcels');
@endphp

@if($transitCountInt > 0)
    <div
        x-data="{
            count: {{ $transitCountInt }},
            officeId: {{ (int) ($office->id ?? auth('office')->id() ?? 0) }},
            collapsed: false,
            dismissedKey() { return 'mursal_transit_dismissed_' + this.officeId; },
            init() {
                const dismissedCount = parseInt(localStorage.getItem(this.dismissedKey()) || '0', 10) || 0;
                // إذا زاد العدد عن آخر مرة طوى فيها الموظف التنبيه، يفتح البانر تلقائياً
                this.collapsed = dismissedCount >= this.count;
            },
            remindLater() {
                localStorage.setItem(this.dismissedKey(), String(this.count));
                this.collapsed = true;
            },
            expand() {
                this.collapsed = false;
            },
            headline() {
                if (this.count === 1) return 'طرد واحد قادم في الطريق';
                if (this.count === 2) return 'طردان قادمان في الطريق';
                if (this.count >= 3 && this.count <= 10) return this.count + ' طرود قادمة في الطريق';
                return this.count + ' طرداً قادماً في الطريق';
            }
        }"
        class="mb-4"
    >
        {{-- الحالة 1: البانر الكامل (عند وصول شحنة جديدة) --}}
        <div
            x-show="!collapsed"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            class="bg-primary text-white rounded-2xl p-4 shadow-md border border-slate-800 relative overflow-hidden"
        >
            {{-- شريط لمسة لونية جانبية بهوية التطبيق --}}
            <div class="absolute top-0 right-0 bottom-0 w-1 bg-secondary"></div>

            <div class="flex items-start gap-3">
                <div class="w-11 h-11 rounded-xl bg-secondary/15 border border-secondary/30 text-secondary flex items-center justify-center shrink-0 relative">
                    <span class="material-symbols-outlined text-2xl">local_shipping</span>
                    <span class="absolute -top-1 -left-1 w-2.5 h-2.5 rounded-full bg-secondary ring-2 ring-primary"></span>
                </div>

                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-1.5">
                        <span class="text-[10px] font-extrabold text-secondary bg-secondary/15 px-2 py-0.5 rounded-md">
                            شحنات قادمة
                        </span>
                    </div>
                    <h3 class="text-sm font-extrabold text-white mt-1" x-text="headline()"></h3>
                    <p class="text-[11px] text-slate-300 mt-0.5 leading-relaxed">
                        مرسلة إليك من الفروع الشريكة وبانتظار تأكيد الاستلام عند وصولها.
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2 mt-3.5 pt-3 border-t border-white/10">
                <a
                    href="{{ $dashboardTransitUrl }}"
                    class="flex-1 py-2 px-3 rounded-xl bg-secondary hover:bg-amber-500 active:scale-[0.98] text-primary text-xs font-extrabold flex items-center justify-center gap-1.5 transition-all"
                >
                    <span class="material-symbols-outlined text-base">inventory</span>
                    <span>مراجعة واستلام</span>
                </a>
                <button
                    type="button"
                    @click="remindLater()"
                    class="py-2 px-3 rounded-xl bg-white/10 hover:bg-white/15 text-slate-200 text-xs font-bold flex items-center justify-center gap-1 transition-colors"
                >
                    <span class="material-symbols-outlined text-sm">schedule</span>
                    <span>لاحقاً</span>
                </button>
            </div>
        </div>

        {{-- الحالة 2: الشريط المطوي الرفيع (بعد الضغط على لاحقاً) --}}
        <div
            x-show="collapsed"
            style="display: none;"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            class="bg-amber-50/90 border border-amber-200/90 rounded-xl px-3 py-2 flex items-center justify-between gap-2 shadow-2xs"
        >
            <button
                type="button"
                @click="expand()"
                class="flex items-center gap-2 min-w-0 text-right flex-1"
            >
                <span class="w-7 h-7 rounded-lg bg-secondary/20 text-amber-800 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-base">local_shipping</span>
                </span>
                <span class="text-xs font-extrabold text-primary truncate" x-text="headline()"></span>
            </button>

            <a
                href="{{ $dashboardTransitUrl }}"
                class="px-2.5 py-1 rounded-lg bg-primary text-secondary text-[11px] font-extrabold shrink-0 hover:bg-slate-800 transition-colors flex items-center gap-0.5"
            >
                <span>عرض</span>
                <span class="material-symbols-outlined text-sm">chevron_left</span>
            </a>
        </div>
    </div>
@endif
        <h2 class="text-sm font-bold text-slate-800 pr-1">إحصائيات الطرود</h2>

        <div class="grid grid-cols-2 gap-3">
            <!-- إجمالي الطرود -->
            <a href="{{ route('office.parcels.index') }}"
                class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm hover:border-blue-300 transition-all block">
                <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-2">
                    <span class="material-symbols-outlined text-lg">inventory_2</span>
                </div>
                <p class="text-xs text-slate-400 font-bold">إجمالي الطرود</p>
                <p class="text-xl font-black text-slate-800 mt-1">{{ $stats['total'] }}</p>
            </a>

            <!-- الطرود التي بالمكتب -->
            <a href="{{ route('office.parcels.index') }}"
                class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm hover:border-amber-300 transition-all block">
                <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center mb-2">
                    <span class="material-symbols-outlined text-lg">store</span>
                </div>
                <p class="text-xs text-slate-400 font-bold">الطرود بالمكتب</p>
                <p class="text-xl font-black text-amber-600 mt-1">{{ $stats['in_office'] }}</p>
            </a>

            <!-- تم التسليم -->
            <a href="{{ route('office.parcels.index') }}"
                class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm hover:border-emerald-300 transition-all block">
                <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-2">
                    <span class="material-symbols-outlined text-lg">check_circle</span>
                </div>
                <p class="text-xs text-slate-400 font-bold">تم التسليم</p>
                <p class="text-xl font-black text-emerald-600 mt-1">{{ $stats['delivered'] }}</p>
            </a>

            <!-- المرتجع -->
            <a href="{{ route('office.parcels.index') }}"
                class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm hover:border-rose-300 transition-all block">
                <div class="w-8 h-8 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center mb-2">
                    <span class="material-symbols-outlined text-lg">assignment_return</span>
                </div>
                <p class="text-xs text-slate-400 font-bold">الطرود المرتجعة</p>
                <p class="text-xl font-black text-rose-600 mt-1">{{ $stats['returned'] }}</p>
            </a>
        </div>

        <!-- بطاقات مخصصة للميزات والخدمات -->
        <h2 class="text-sm font-bold text-slate-800 pr-1 pt-2">الخدمات والميزات</h2>

        <div class="space-y-2.5">
            @if(auth('office')->check() && auth('office')->user()->hasFeature('outgoing_parcels'))
                <!-- خدمة الطرود الصادرة (تظهر فقط عند تفعيل الميزة للمكتب) -->
                <a href="{{ route('office.outgoing-parcels.index') }}"
                   class="bg-white p-3.5 rounded-2xl border border-slate-200/80 hover:border-secondary flex items-center justify-between shadow-xs transition-all group block">
                    <div class="flex items-center gap-3">
                        <span class="w-10 h-10 rounded-xl bg-amber-50 text-secondary flex items-center justify-center group-hover:scale-105 transition-transform">
                            <span class="material-symbols-outlined text-xl">outbox</span>
                        </span>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-bold text-xs text-slate-800 group-hover:text-primary transition-colors">الطرود الصادرة (الصادر)</h3>
                                <span class="text-[10px] bg-amber-50 text-amber-700 border border-amber-200 font-extrabold px-2 py-0.5 rounded-md">مفعّل</span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-0.5">إرسال الشحنات إلى الفروع والمكاتب الأخرى ومتابعة استلامها</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-1 text-slate-400 group-hover:text-secondary transition-colors">
                        <span class="text-[11px] font-bold hidden sm:inline">فتح الصادر</span>
                        <span class="material-symbols-outlined text-base">arrow_back</span>
                    </div>
                </a>
            @endif

            @if(auth('office')->check() && auth('office')->user()->hasFeature('sent_shipments'))
                <!-- خدمة الشحنات المرسلة (تظهر فقط عند تفعيل الميزة للمكتب) -->
                <a href="{{ route('office.shipments.sent.index') }}"
                   class="bg-white p-3.5 rounded-2xl border border-slate-200/80 hover:border-secondary flex items-center justify-between shadow-xs transition-all group block">
                    <div class="flex items-center gap-3">
                        <span class="w-10 h-10 rounded-xl bg-amber-50 text-secondary flex items-center justify-center group-hover:scale-105 transition-transform">
                            <span class="material-symbols-outlined text-xl">outbound</span>
                        </span>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-bold text-xs text-slate-800 group-hover:text-primary transition-colors">الشحنات المرسلة (الكشوفات)</h3>
                                <span class="text-[10px] bg-amber-50 text-amber-700 border border-amber-200 font-extrabold px-2 py-0.5 rounded-md">مفعّل</span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-0.5">إنشاء كشوفات الشحنات بمسح الباركود وإرسالها مع السائق ومتابعة استلامها</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-1 text-slate-400 group-hover:text-secondary transition-colors">
                        <span class="text-[11px] font-bold hidden sm:inline">فتح الشحنات</span>
                        <span class="material-symbols-outlined text-base">arrow_back</span>
                    </div>
                </a>
            @endif

            <!-- الخدمة الأولى: إشعارات الواتساب (تم تفعيلها) -->
            <a href="{{ route('office.whatsapp.index') }}" 
               class="bg-white p-3.5 rounded-2xl border border-slate-200/80 hover:border-emerald-300 flex items-center justify-between shadow-xs transition-all group block">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center group-hover:scale-105 transition-transform">
                        <span class="material-symbols-outlined text-xl">chat</span>
                    </span>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-bold text-xs text-slate-800 group-hover:text-emerald-700 transition-colors">إشعارات الواتساب للعملاء</h3>
                            <span class="text-[10px] bg-emerald-50 text-emerald-600 border border-emerald-200 font-extrabold px-2 py-0.5 rounded-md flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                <span>تم التفعيل</span>
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-0.5">إرسال تنبيهات وصول الطرود وروابط الاستلام عبر واتساب تلقائياً</p>
                    </div>
                </div>
                <div class="flex items-center gap-1 text-slate-400 group-hover:text-emerald-600 transition-colors">
                    <span class="text-[11px] font-bold hidden sm:inline">الإعدادات</span>
                    <span class="material-symbols-outlined text-base">arrow_back</span>
                </div>
            </a>

            <!-- الخدمة الثانية: سندات الاستلام -->
            <div class="bg-white p-3.5 rounded-2xl border border-slate-200/80 flex items-center justify-between shadow-xs">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                        <span class="material-symbols-outlined text-xl">receipt_long</span>
                    </span>
                    <div>
                        <h3 class="font-bold text-xs text-slate-800">سندات استلام الشحنات</h3>
                        <p class="text-[11px] text-slate-400">إصدار وتوليد سند استلام رسمي وتوقيع إلكتروني للعميل</p>
                    </div>
                </div>
                <span class="text-[10px] bg-slate-100 text-slate-500 font-bold px-2 py-1 rounded-md">قريباً</span>
            </div>

            <!-- الخدمة الثالثة: المالية والصندوق -->
            <div class="bg-white p-3.5 rounded-2xl border border-slate-200/80 flex items-center justify-between shadow-xs">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                        <span class="material-symbols-outlined text-xl">account_balance_wallet</span>
                    </span>
                    <div>
                        <h3 class="font-bold text-xs text-slate-800">المالية وصندوق المكتب</h3>
                        <p class="text-[11px] text-slate-400">حساب المبالغ المستلمة من كل طرد  </p>
                    </div>
                </div>
                <span class="text-[10px] bg-slate-100 text-slate-500 font-bold px-2 py-1 rounded-md">قريباً</span>
            </div>
        </div>
        </div>

    </main>
@endsection

@push('scripts')
<script>
    function dashboardGlobalSearch(config) {
        return {
            searchUrl: config.searchUrl || '/office/global-search',
            query: '',
            isLoading: false,
            hasSearched: false,
            _abortController: null,
            results: {
                total: 0,
                incoming: [],
                in_transit: [],
                outgoing: [],
            },

            get isActive() {
                return this.query.trim() !== '';
            },

            clearSearch() {
                if (this._abortController) {
                    this._abortController.abort();
                }
                this.query = '';
                this.isLoading = false;
                this.hasSearched = false;
                this.results = { total: 0, incoming: [], in_transit: [], outgoing: [] };
                window.dispatchEvent(new CustomEvent('dashboard-global-search-loading', { detail: { loading: false } }));
                window.dispatchEvent(new CustomEvent('dashboard-global-search-clear'));
            },

            async runSearch(rawQ) {
                const trimmed = String(rawQ || '').trim();
                this.query = trimmed;

                if (trimmed === '') {
                    if (this._abortController) this._abortController.abort();
                    this.isLoading = false;
                    this.hasSearched = false;
                    this.results = { total: 0, incoming: [], in_transit: [], outgoing: [] };
                    window.dispatchEvent(new CustomEvent('dashboard-global-search-loading', { detail: { loading: false } }));
                    return;
                }

                if (this._abortController) {
                    this._abortController.abort();
                }
                const controller = new AbortController();
                this._abortController = controller;

                const params = new URLSearchParams({
                    q: trimmed,
                    _t: String(Date.now()),
                });

                this.isLoading = true;
                window.dispatchEvent(new CustomEvent('dashboard-global-search-loading', { detail: { loading: true } }));

                try {
                    const res = await fetch(`${this.searchUrl}?${params.toString()}`, {
                        cache: 'no-store',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        signal: controller.signal,
                    });

                    if (!res.ok) throw new Error('Search request failed');
                    const data = await res.json();

                    if (this._abortController !== controller) return;

                    if (data && data.ok) {
                        this.results = {
                            total: Number(data.total || 0),
                            incoming: Array.isArray(data.incoming) ? data.incoming : [],
                            in_transit: Array.isArray(data.in_transit) ? data.in_transit : [],
                            outgoing: Array.isArray(data.outgoing) ? data.outgoing : [],
                        };
                        this.hasSearched = true;
                    }
                } catch (err) {
                    if (err.name !== 'AbortError' && this._abortController === controller) {
                        window.dispatchEvent(new CustomEvent('office-toast', {
                            detail: { message: 'تعذر إتمام البحث الشامل، حاول مجدداً.', type: 'error' }
                        }));
                    }
                } finally {
                    if (this._abortController === controller) {
                        this.isLoading = false;
                        window.dispatchEvent(new CustomEvent('dashboard-global-search-loading', { detail: { loading: false } }));
                    }
                }
            }
        };
    }
</script>
@endpush