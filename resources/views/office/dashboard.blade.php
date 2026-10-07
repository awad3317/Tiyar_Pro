@extends('office.layouts.app')

@section('title', 'لوحة تحكم المكتب')

@section('shell-class', 'bg-slate-50')

@section('content')
    <!-- المحتوى والبطاقات الإحصائية -->
    <main class="flex-1 p-4 space-y-4">

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

        <!-- بطاقات مخصصة للميزات القادمة مستقبلاً -->
        <h2 class="text-sm font-bold text-slate-800 pr-1 pt-2">خدمات قادمة</h2>

        <div class="space-y-2.5">
            <!-- الخدمة الأولى: إشعارات الواتساب -->
            <div class="bg-white p-3.5 rounded-2xl border border-slate-200/80 flex items-center justify-between shadow-xs">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                        <span class="material-symbols-outlined text-xl">chat</span>
                    </span>
                    <div>
                        <h3 class="font-bold text-xs text-slate-800">إشعارات الواتساب للعملاء</h3>
                        <p class="text-[11px] text-slate-400">إرسال تنبيهات وصول الطرود وروابط الاستلام عبر واتساب</p>
                    </div>
                </div>
                <span class="text-[10px] bg-slate-100 text-slate-500 font-bold px-2 py-1 rounded-md">قريباً</span>
            </div>

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