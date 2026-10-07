@extends('office.layouts.app')

@section('title', 'لوحة تحكم المكتب')

@section('content')
<div class="w-full max-w-lg">
    <div class="bg-slate-800 border border-slate-700 rounded-3xl p-6 shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-700/80 pb-4 mb-6">
            <div>
                <h2 class="text-lg font-bold text-white">{{ $office->name }}</h2>
                <p class="text-xs text-slate-400">الفرع: {{ $office->branch_name ?? 'المركز الرئيسي' }}</p>
            </div>
            
            <form method="POST" action="{{ route('office.logout') }}">
                @csrf
                <button type="submit" class="text-xs bg-rose-500/20 text-rose-400 border border-rose-500/30 px-3 py-1.5 rounded-lg hover:bg-rose-500/30 transition-all">
                    تسجيل الخروج
                </button>
            </form>
        </div>

        <div class="p-4 bg-slate-900/50 rounded-2xl border border-slate-700/50 text-center">
            <p class="text-sm text-emerald-400 font-semibold mb-1">✅ تم تسجيل الدخول بنجاح</p>
            <p class="text-xs text-slate-400">نظام الجلسة معزول بحارس مستقل (`office guard`).</p>
        </div>
    </div>
</div>
@endsection