@extends('office.layouts.app')

@section('title', 'تسجيل دخول المكتب')

@section('content')
<div class="w-full max-w-md">
    <!-- شعار أو رأس الواجهة -->
    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-indigo-600 text-white shadow-lg shadow-indigo-500/30 mb-4">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
        </div>
        <h1 class="text-2xl font-bold text-white tracking-wide">تسجيل دخول المكتب</h1>
        <p class="text-sm text-slate-400 mt-1">أدخل بيانات الاعتماد للوصول لكشوفات الطرود</p>
    </div>

    <!-- بطاقة تسجيل الدخول -->
    <div class="bg-slate-800/80 backdrop-blur-md border border-slate-700/60 rounded-3xl p-6 sm:p-8 shadow-2xl">
        <form method="POST" action="{{ route('office.login.submit') }}" class="space-y-5">
            @csrf

            <!-- حقل رقم الهاتف -->
            <div>
                <label for="phone" class="block text-xs font-semibold text-slate-300 mb-2">رقم الهاتف المسجل</label>
                <div class="relative">
                    <input 
                        type="text" 
                        id="phone" 
                        name="phone" 
                        value="{{ old('phone') }}" 
                        placeholder="77XXXXXXX أو 967..." 
                        required 
                        autofocus
                        dir="ltr"
                        class="w-full bg-slate-900/90 border @error('phone') border-rose-500 @else border-slate-700 @enderror rounded-xl px-4 py-3.5 text-white placeholder-slate-500 text-left focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                    >
                    <span class="absolute right-3.5 top-3.5 text-slate-500">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                        </svg>
                    </span>
                </div>
                @error('phone')
                    <p class="text-xs text-rose-400 mt-2 flex items-center gap-1 font-medium">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <!-- حقل كلمة المرور -->
            <div>
                <label for="password" class="block text-xs font-semibold text-slate-300 mb-2">كلمة المرور</label>
                <div class="relative">
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="••••••••" 
                        required
                        class="w-full bg-slate-900/90 border border-slate-700 rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                    >
                    <span class="absolute left-3.5 top-3.5 text-slate-500">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                    </span>
                </div>
            </div>

            <!-- تذكرني -->
            <div class="flex items-center justify-between text-xs text-slate-400 pt-1">
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input type="checkbox" name="remember" class="w-4 h-4 rounded bg-slate-900 border-slate-700 text-indigo-600 focus:ring-indigo-500">
                    <span>تذكر هذا الجهاز</span>
                </label>
            </div>

            <!-- زر الدخول -->
            <button 
                type="submit" 
                class="w-full py-3.5 px-4 bg-indigo-600 hover:bg-indigo-500 active:scale-[0.98] text-white font-bold rounded-xl shadow-lg shadow-indigo-600/30 transition-all text-sm mt-2 flex items-center justify-center gap-2"
            >
                <span>دخول للوحة الطرود</span>
                <svg class="w-4 h-4 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                </svg>
            </button>
        </form>
    </div>
</div>
@endsection