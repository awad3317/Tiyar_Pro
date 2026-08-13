@extends('layouts.app')

@section('content')
<main class="pt-32 pb-20 container mx-auto px-6">
      <div class="text-right">
        <h2 class="text-4xl md:text-5xl font-bold text-white leading-tight">
          أرقامنا
        </h2>
      </div>
      <div class="grid md:grid-cols-4 gap-6 mt-8">
        <div class="p-6 bg-black/60 rounded-2xl text-center">
          <div class="text-4xl font-bold text-white">50+</div>
          <div class="text-slate-400 mt-2">مشروع ناجح</div>
        </div>
        <div class="p-6 bg-black/60 rounded-2xl text-center">
          <div class="text-4xl font-bold text-white">10M+</div>
          <div class="text-slate-400 mt-2">مستخدم نشط</div>
        </div>
        <div class="p-6 bg-black/60 rounded-2xl text-center">
          <div class="text-4xl font-bold text-white">98%</div>
          <div class="text-slate-400 mt-2">نسبة الرضا</div>
        </div>
        <div class="p-6 bg-black/60 rounded-2xl text-center">
          <div class="text-4xl font-bold text-white">24/7</div>
          <div class="text-slate-400 mt-2">دعم فني</div>
        </div>
      </div>
    </main>
@endsection
