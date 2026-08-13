@extends('layouts.app')

@section('content')
<main class="pt-32 pb-20 container mx-auto px-6">
      <div class="text-right">
        <h2 class="text-4xl md:text-5xl font-bold text-white leading-tight">
          من نحن
        </h2>
        <p class="text-slate-400 mt-4 max-w-2xl">
          تيار هي شركة حلول رقمية متخصصة في تصميم وبناء مواقع، تطبيقات، سيرفرات،
          وتصاميم جرافيك. نعمل بشغف لنقل أفكارك إلى تجارب رقمية ناجحة.
        </p>
      </div>

      <section class="mt-8 grid md:grid-cols-2 gap-6">
        <div class="p-6 bg-black/60 rounded-2xl">
          <h3 class="text-xl font-bold text-white">رؤيتنا</h3>
          <p class="text-slate-400 mt-2">
            تمكين الشركات من الوصول إلى جمهور أوسع بتجارب رقمية متميزة.
          </p>
        </div>
        <div class="p-6 bg-black/60 rounded-2xl">
          <h3 class="text-xl font-bold text-white">قيمنا</h3>
          <p class="text-slate-400 mt-2">
            الابتكار، الجودة، والتعاون الوثيق مع العملاء.
          </p>
        </div>
      </section>
    </main>
@endsection
