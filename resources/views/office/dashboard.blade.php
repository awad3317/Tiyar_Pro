@extends('office.layouts.app')

@section('title', 'لوحة تحكم المكتب')

@section('shell-class', 'bg-slate-50')

@section('content')
    <!-- المحتوى والبطاقات الإحصائية -->
    <main class="flex-1 p-4 space-y-4">

        <h2 class="text-sm font-bold text-slate-800 pr-1">إحصائيات الشحنات</h2>

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
            <div class="bg-white p-3.5 rounded-2xl border border-slate-200/80 flex items-center justify-between opacity-80">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-600">
                        <span class="material-symbols-outlined">qr_code_scanner</span>
                    </span>
                    <div>
                        <h3 class="font-bold text-xs text-slate-800">ماسح الباركود السريع</h3>
                        <p class="text-[11px] text-slate-400">تسليم الشحنات بقراءة الكود مباشرة</p>
                    </div>
                </div>
                <span class="text-[10px] bg-slate-100 text-slate-500 font-bold px-2 py-1 rounded-md">قريباً</span>
            </div>

            <div class="bg-white p-3.5 rounded-2xl border border-slate-200/80 flex items-center justify-between opacity-80">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-600">
                        <span class="material-symbols-outlined">receipt_long</span>
                    </span>
                    <div>
                        <h3 class="font-bold text-xs text-slate-800">سندات القبض والشحن</h3>
                        <p class="text-[11px] text-slate-400">إصدار وطباعة بوالص الشحن الإلكترونية</p>
                    </div>
                </div>
                <span class="text-[10px] bg-slate-100 text-slate-500 font-bold px-2 py-1 rounded-md">قريباً</span>
            </div>
        </div>

    </main>
@endsection