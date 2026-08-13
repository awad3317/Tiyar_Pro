@extends('layouts.app')

@section('content')
<main class="pt-32 pb-20 container mx-auto px-6">
      <div
        class="flex justify-between items-end border-b border-white/5 pb-8 mb-8"
      >
        <div>
          <h2 class="text-5xl font-bold text-white">أعمال مختارة</h2>
          <p class="text-slate-400 mt-2">تحف فنية رقمية صممت لتبقى.</p>
        </div>
      </div>

      <div id="portfolio-list" class="flex flex-col gap-12 mt-8">
        <!-- Projects injected by assets/js/portfolio.js -->
      </div>
    </main>
@endsection
