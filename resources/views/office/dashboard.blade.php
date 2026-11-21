@extends('office.layouts.app')

@section('title', 'لوحة تحكم المكتب')

@section('shell-class', 'bg-slate-50')

@section('content')
    <!-- المحتوى والبطاقات الإحصائية -->
    <main class="flex-1 p-4 space-y-4">

        @php
            $inTransitCount = $inTransitCount ?? (
                auth('office')->check()
                    ? \App\Models\OutgoingParcel::where('destination_office_id', auth('office')->id())
                        ->where('status', \App\Enums\OutgoingParcelStatus::IN_TRANSIT)
                        ->count()
                    : 0
            );
        @endphp

        @if($inTransitCount > 0)
            <a href="{{ route('office.transit-parcels.index') }}"
               class="bg-gradient-to-l from-sky-600 to-indigo-700 text-white p-4 rounded-2xl shadow-md shadow-sky-600/20 flex items-center justify-between gap-3 transition-transform active:scale-[0.99] block">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-white/15 border border-white/25 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-xl animate-pulse">local_shipping</span>
                    </span>
                    <div>
                        <p class="text-xs font-extrabold leading-snug">
                            لديك {{ $inTransitCount }} طرد قادم في الطريق — اضغط للاستلام
                        </p>
                        <p class="text-[11px] text-sky-100 mt-0.5">
                            اضغط هنا لمراجعة الطرود المرسلة إليك وتحويلها لطرد وارد فوراً
                        </p>
                    </div>
                </div>
                <span class="material-symbols-outlined text-lg shrink-0">arrow_back</span>
            </a>
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