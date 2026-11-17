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

        <!-- 2. خدمات وميزات مستقبلية -->
        <div class="space-y-2.5">
            <div class="flex items-center justify-between px-1">
                <h3 class="text-sm font-extrabold text-slate-800">خدمات وميزات قادمة</h3>
                <span class="text-[10px] font-bold text-slate-400">قيد التطوير</span>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="bg-white/70 p-3.5 rounded-2xl border border-dashed border-slate-200 flex flex-col gap-1.5 opacity-80">
                    <div class="flex items-center justify-between">
                        <span class="w-8 h-8 rounded-xl bg-slate-100 text-slate-500 flex items-center justify-center">
                            <span class="material-symbols-outlined text-lg">summarize</span>
                        </span>
                        <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">قريباً</span>
                    </div>
                    <p class="text-xs font-extrabold text-slate-700 mt-1">التقارير المالية</p>
                    <p class="text-[10px] text-slate-400 font-bold">ملخصات يومية وشهرية</p>
                </div>

                <div class="bg-white/70 p-3.5 rounded-2xl border border-dashed border-slate-200 flex flex-col gap-1.5 opacity-80">
                    <div class="flex items-center justify-between">
                        <span class="w-8 h-8 rounded-xl bg-slate-100 text-slate-500 flex items-center justify-center">
                            <span class="material-symbols-outlined text-lg">account_balance</span>
                        </span>
                        <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">قريباً</span>
                    </div>
                    <p class="text-xs font-extrabold text-slate-700 mt-1">حسابات السائقين</p>
                    <p class="text-[10px] text-slate-400 font-bold">كشف حساب وسلف كل سائق</p>
                </div>

                <div class="bg-white/70 p-3.5 rounded-2xl border border-dashed border-slate-200 flex flex-col gap-1.5 opacity-80">
                    <div class="flex items-center justify-between">
                        <span class="w-8 h-8 rounded-xl bg-slate-100 text-slate-500 flex items-center justify-center">
                            <span class="material-symbols-outlined text-lg">payments</span>
                        </span>
                        <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">قريباً</span>
                    </div>
                    <p class="text-xs font-extrabold text-slate-700 mt-1">المصروفات والسندات</p>
                    <p class="text-[10px] text-slate-400 font-bold">سندات القبض والصرف</p>
                </div>
            </div>
        </div>
    </main>
@endsection
