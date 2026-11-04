@extends('accountant.layouts.app')

@section('title', 'الرئيسية - بوابة المحاسب المالي')

@section('content')
    <main class="flex-1 p-4 space-y-4">

        <!-- البطاقة الترحيبية للمحاسب -->
        <div class="bg-white rounded-2xl border border-slate-200/80 p-4 shadow-sm relative overflow-hidden">
            <div class="absolute -left-6 -bottom-6 text-slate-100 pointer-events-none">
                <span class="material-symbols-outlined text-[110px]">account_balance_wallet</span>
            </div>

            <div class="relative z-10 flex items-start gap-3.5">
                <div class="w-12 h-12 rounded-2xl bg-primary text-emerald-400 flex items-center justify-center shrink-0 shadow-md">
                    <span class="material-symbols-outlined text-2xl">account_balance_wallet</span>
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-[11px] font-bold text-slate-400">مرحباً بك في البوابة المالية،</span>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-50 border border-emerald-200 text-emerald-700 text-[10px] font-extrabold">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            <span>حساب نشط</span>
                        </span>
                    </div>

                    <h2 class="text-base font-extrabold text-slate-800 mt-0.5 truncate">
                        {{ $accountant->name }}
                    </h2>

                    <div class="mt-2.5 pt-2.5 border-t border-slate-100 grid grid-cols-1 gap-1.5 text-xs">
                        <div class="flex items-center gap-1.5 text-slate-600">
                            <span class="material-symbols-outlined text-sm text-secondary">store</span>
                            <span class="text-slate-400">المكتب / الفرع:</span>
                            <strong class="font-bold text-slate-800 truncate">
                                {{ $office?->name ?? '—' }}
                                @if (!empty($office?->branch_name))
                                    <span class="text-slate-400 font-normal">({{ $office->branch_name }})</span>
                                @endif
                            </strong>
                        </div>

                        <div class="flex items-center gap-1.5 text-slate-600">
                            <span class="material-symbols-outlined text-sm text-emerald-600">schedule</span>
                            <span class="text-slate-400">آخر تسجيل دخول:</span>
                            <span class="font-bold text-slate-700 dir-ltr">
                                {{ $accountant->last_login_at ? $accountant->last_login_at->format('Y-m-d h:i A') : 'الآن' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- هيكل مبدئي للإحصائيات والعمليات المالية (يُستكمل في المهمة القادمة) -->
        <div class="space-y-2.5">
            <div class="flex items-center justify-between px-1">
                <h3 class="text-sm font-extrabold text-slate-800">الملخص المالي والتحصيل</h3>
                <span class="text-[10px] font-bold text-slate-400">مباشر</span>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
                    <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-2">
                        <span class="material-symbols-outlined text-lg">payments</span>
                    </div>
                    <p class="text-xs text-slate-400 font-bold">مبالغ التحصيل عند التسليم</p>
                    <p class="text-xs font-extrabold text-slate-500 mt-1">جاهز للربط بالمرحلة القادمة</p>
                </div>

                <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
                    <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center mb-2">
                        <span class="material-symbols-outlined text-lg">receipt_long</span>
                    </div>
                    <p class="text-xs text-slate-400 font-bold">سجل العمليات المالية</p>
                    <p class="text-xs font-extrabold text-slate-500 mt-1">جاهز للربط بالمرحلة القادمة</p>
                </div>
            </div>
        </div>

    </main>
@endsection
