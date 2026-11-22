@extends('office.layouts.app')

@section('title', 'لوحة تحكم المكتب')

@section('shell-class', 'bg-slate-50')

@section('content')
    <!-- المحتوى والبطاقات الإحصائية -->
    <main class="flex-1 p-4 space-y-4">

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

    </main>
@endsection