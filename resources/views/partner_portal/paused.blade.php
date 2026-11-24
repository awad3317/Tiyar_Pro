@extends('partner_portal.layout')

@section('title', 'الرابط متوقف مؤقتاً')

@section('content')
    <div class="flex-1 flex items-center justify-center py-8">
        <div class="w-full bg-white border border-slate-200 rounded-3xl p-6 text-center shadow-xs space-y-4">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-amber-50 border border-amber-200 text-amber-600 flex items-center justify-center">
                <span class="material-symbols-outlined text-4xl">pause_circle</span>
            </div>

            <div class="space-y-1.5">
                <h2 class="text-base font-extrabold text-slate-900">
                    هذا الرابط متوقف مؤقتاً من قِبل إدارة المكتب المستلم
                </h2>
                <p class="text-xs text-slate-500 leading-relaxed">
                    لا يمكن إرسال كشوفات شحن جديدة عبر هذا الرابط في الوقت الحالي. يرجى التواصل مع إدارة
                    <strong class="text-primary">{{ $brand['office_name'] ?? 'المكتب المستلم' }}</strong>
                    لإعادة التفعيل أو تزويدكم برابط محدث.
                </p>
            </div>

            <div class="bg-slate-50 border border-slate-200/80 rounded-2xl p-3 text-[11px] font-bold text-slate-600">
                المكتب الخارجي المسجل: <span class="text-slate-900 font-extrabold">{{ $link->partner_name }}</span>
                @if ($link->partner_city)
                    — <span>{{ $link->partner_city }}</span>
                @endif
            </div>
        </div>
    </div>
@endsection
