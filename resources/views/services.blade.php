@extends('layouts.app')

@section('content')
<main class="pt-32 pb-20 container mx-auto px-6">
      <div class="text-right">
        <h2 class="text-4xl md:text-5xl font-bold text-white leading-tight">
          خدماتنا
        </h2>
        <p class="text-slate-400 mt-4 max-w-2xl">
          نقدّم مجموعة متكاملة من الخدمات الرقمية: تصميم واجهات، تطوير مواقع
          وتطبيقات، بنى تحتية سحابية، وتصاميم جرافيك وإنتاج فيديو.
        </p>
      </div>

      <div class="grid md:grid-cols-3 gap-6 mt-8">
        <div class="service-module p-8 rounded-3xl bg-black/60">
          <h3 class="text-xl font-bold text-white">تصميم UX/UI</h3>
          <p class="text-slate-400 mt-3">
            تصاميم مبنية على بحث المستخدم وتجارب قابلة للتحويل.
          </p>
        </div>
        <div class="service-module p-8 rounded-3xl bg-black/60">
          <h3 class="text-xl font-bold text-white">تطوير مواقع وتطبيقات</h3>
          <p class="text-slate-400 mt-3">
            واجهات سريعة ومستقرة مع أنظمة قابلة للتوسع.
          </p>
        </div>
        <div class="service-module p-8 rounded-3xl bg-black/60">
          <h3 class="text-xl font-bold text-white">بنية سحابية وDevOps</h3>
          <p class="text-slate-400 mt-3">
            نشر مؤتمت، مراقبة، واستمرارية أعمال 24/7.
          </p>
        </div>
      </div>
    </main>
@endsection
