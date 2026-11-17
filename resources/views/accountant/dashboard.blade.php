@extends('accountant.layouts.app')

@section('title', 'الرئيسية - بوابة المحاسب المالي')

@section('content')
    <main class="flex-1 p-4 space-y-4">
        <!-- 1. ملخص الإحصائيات السريعة -->
        <div class="space-y-2.5">
            <div class="flex items-center justify-between px-1">
                <h3 class="text-sm font-extrabold text-slate-800">الملخص المالي والإحصائي</h3>
                <span class="text-[10px] font-bold text-slate-400">مباشر</span>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <!-- الطرود غير المصنفة -->
                <a href="{{ route('accountant.parcels.index') }}"
                   class="bg-white p-3.5 rounded-2xl border border-slate-200/80 shadow-sm hover:border-amber-300 transition-all block">
                    <div class="flex items-center justify-between mb-2">
                        <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                            <span class="material-symbols-outlined text-lg">inventory_2</span>
                        </div>
                        <span class="text-xl font-black font-mono text-slate-900">{{ number_format($stats['unclassified_total']) }}</span>
                    </div>
                    <p class="text-xs font-extrabold text-slate-800">الطرود غير المصنفة</p>
                    <p class="text-[10px] text-slate-500 font-bold mt-1">
                        آجل: {{ number_format($stats['unclassified_postpaid']) }} • محاسب: {{ number_format($stats['unclassified_prepaid']) }}
                    </p>
                </a>

                <!-- إجمالي الكشوفات -->
                <a href="{{ route('accountant.manifests.index') }}"
                   class="bg-white p-3.5 rounded-2xl border border-slate-200/80 shadow-sm hover:border-primary/40 transition-all block">
                    <div class="flex items-center justify-between mb-2">
                        <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                            <span class="material-symbols-outlined text-lg">receipt_long</span>
                        </div>
                        <span class="text-xl font-black font-mono text-slate-900">{{ number_format($stats['manifests_total']) }}</span>
                    </div>
                    <p class="text-xs font-extrabold text-slate-800">إجمالي الكشوفات</p>
                    <p class="text-[10px] text-slate-500 font-bold mt-1">كافة كشوفات السائقين</p>
                </a>

                <!-- الكشوفات غير المعتمدة (قيد المراجعة) -->
                <a href="{{ route('accountant.manifests.index') }}"
                   class="bg-white p-3.5 rounded-2xl border border-amber-200/80 shadow-sm hover:border-amber-400 transition-all block">
                    <div class="flex items-center justify-between mb-2">
                        <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center">
                            <span class="material-symbols-outlined text-lg">pending_actions</span>
                        </div>
                        <span class="text-xl font-black font-mono text-amber-700">{{ number_format($stats['manifests_open']) }}</span>
                    </div>
                    <p class="text-xs font-extrabold text-slate-800">كشوفات غير معتمدة</p>
                    <p class="text-[10px] text-amber-700 font-bold mt-1">بانتظار التصفية النهائية</p>
                </a>

                <!-- الكشوفات المعتمدة (المصفّاة) -->
                <a href="{{ route('accountant.manifests.index') }}"
                   class="bg-white p-3.5 rounded-2xl border border-emerald-200/80 shadow-sm hover:border-emerald-400 transition-all block">
                    <div class="flex items-center justify-between mb-2">
                        <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                            <span class="material-symbols-outlined text-lg">verified</span>
                        </div>
                        <span class="text-xl font-black font-mono text-emerald-700">{{ number_format($stats['manifests_settled']) }}</span>
                    </div>
                    <p class="text-xs font-extrabold text-slate-800">كشوفات معتمدة</p>
                    <p class="text-[10px] text-emerald-700 font-bold mt-1">تمت تصفيتها وتسليمها</p>
                </a>
            </div>
        </div>

        <!-- 2. الملخص المالي للكشوفات -->
        <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-sm space-y-2.5">
            <h4 class="text-xs font-extrabold text-slate-800 border-b border-slate-100 pb-2">الموقف المالي للكشوفات</h4>

            <div class="flex items-center justify-between text-xs py-1">
                <span class="font-bold text-slate-600">صافي الكشوفات غير المعتمدة:</span>
                <span class="font-mono font-black text-amber-700 dir-ltr">{{ number_format($stats['open_net_amount'], 0) }} ر.ي</span>
            </div>

            <div class="flex items-center justify-between text-xs py-1 border-t border-slate-100">
                <span class="font-bold text-slate-600">إجمالي المبالغ المصفّاة للسائقين:</span>
                <span class="font-mono font-black text-emerald-700 dir-ltr">{{ number_format($stats['settled_net_amount'], 0) }} ر.ي</span>
            </div>

            <div class="flex items-center justify-between text-xs py-1 border-t border-slate-100">
                <span class="font-bold text-slate-600">إجمالي الخصومات المسجلة:</span>
                <span class="font-mono font-black text-rose-700 dir-ltr">{{ number_format($stats['total_deductions'], 0) }} ر.ي</span>
            </div>

            <div class="flex items-center justify-between text-xs py-1 border-t border-slate-100">
                <span class="font-bold text-slate-600">آجل الطرود غير المصنفة:</span>
                <span class="font-mono font-black text-slate-800 dir-ltr">{{ number_format($stats['unclassified_postpaid_amount'], 0) }} ر.ي</span>
            </div>
        </div>
    </main>
@endsection
