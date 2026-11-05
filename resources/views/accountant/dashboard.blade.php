@extends('accountant.layouts.app')

@section('title', 'الرئيسية - بوابة المحاسب المالي')

@section('content')
    <main class="flex-1 p-4 space-y-4">
         <!-- هيكل مبدئي للإحصائيات والعمليات المالية (يُستكمل في المهمة القادمة) -->
        <div class="space-y-2.5">
            <div class="flex items-center justify-between px-1">
                <h3 class="text-sm font-extrabold text-slate-800">الملخص المالي والتحصيل</h3>
                <span class="text-[10px] font-bold text-slate-400">مباشر</span>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <a href="{{ route('accountant.parcels.index') }}"
                   class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm hover:border-amber-300 transition-all block">
                    <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center mb-2">
                        <span class="material-symbols-outlined text-lg">inventory_2</span>
                    </div>
                    <p class="text-xs text-slate-400 font-bold">الطرود غير المصنفة</p>
                    <p class="text-xs font-extrabold text-primary mt-1 flex items-center gap-1">
                        <span>مراجعة وتصنيف الطرود</span>
                        <span class="material-symbols-outlined text-xs text-secondary">arrow_back</span>
                    </p>
                </a>

                <a href="{{ route('accountant.manifests.index') }}"
                   class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm hover:border-emerald-300 transition-all block">
                    <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-2">
                        <span class="material-symbols-outlined text-lg">receipt_long</span>
                    </div>
                    <p class="text-xs text-slate-400 font-bold">كشوفات السائقين</p>
                    <p class="text-xs font-extrabold text-primary mt-1 flex items-center gap-1">
                        <span>إدارة الكشوفات والتصفية</span>
                        <span class="material-symbols-outlined text-xs text-emerald-600">arrow_back</span>
                    </p>
                </a>
            </div>
        </div>

    </main>
@endsection
