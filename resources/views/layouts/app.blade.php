<!doctype html>
<html class="scroll-smooth" dir="rtl" lang="ar">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>تيار للحلول والابتكار | تصميم مواقع وتطبيقات احترافية</title>

    <meta
      name="description"
      content="شركة تيار للحلول والابتكار متخصصة في تصميم وتطوير المواقع والتطبيقات والتصاميم الإبداعية. نقدم حلول رقمية احترافية تساعد أعمالك على النمو والتميز."
    />
    <meta
      name="keywords"
      content="تصميم مواقع, تطوير تطبيقات, شركة تقنية, تيار, حلول رقمية, برمجة, تصميم, UX UI"
    />
    <meta name="author" content="Tiyar Solutions" />
    <meta name="robots" content="index, follow" />

    <link rel="canonical" href="https://tiyar.cc/" />

    <!-- Open Graph (WhatsApp / Facebook / LinkedIn) -->

    <meta property="og:locale" content="ar_AR" />
    <meta property="og:site_name" content="تيار للحلول والابتكار" />
    <meta property="og:type" content="website" />
    <meta
      property="og:title"
      content="تيار للحلول والابتكار | تصميم مواقع وتطبيقات احترافية"
    />
    <meta
      property="og:description"
      content="حلول رقمية احترافية في تصميم المواقع والتطبيقات والتصاميم الإبداعية لمساعدة أعمالك على النمو والتميز."
    />
    <meta property="og:url" content="https://tiyar.cc/" />

    <meta
      property="og:image"
      content="https://tiyar.cc/assets/images/preview.png"
    />
    <meta
      property="og:image:secure_url"
      content="https://tiyar.cc/assets/images/preview.png"
    />
    <meta property="og:image:type" content="image/png" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta
      property="og:image:alt"
      content="تيار للحلول والابتكار - تصميم مواقع وتطبيقات"
    />

    <!-- Twitter Card -->

    <meta name="twitter:card" content="summary_large_image" />
    <meta
      name="twitter:title"
      content="تيار للحلول والابتكار | تصميم مواقع وتطبيقات احترافية"
    />
    <meta
      name="twitter:description"
      content="شركة تقنية متخصصة في تصميم وتطوير المواقع والتطبيقات والتصاميم الإبداعية وتقديم حلول رقمية مبتكرة."
    />
    <meta
      name="twitter:image"
      content="https://tiyar.cc/assets/images/preview.png"
    />

    <!-- Favicon -->

    <link rel="icon" type="image/x-icon" href="{{ asset('assets/favicons/favicon.ico') }}" />
    <link
      rel="icon"
      type="image/png"
      sizes="32x32"
      href="{{ asset('assets/favicons/favicon-96x96.png') }}"
    />
    <link
      rel="icon"
      type="image/png"
      sizes="96x96"
      href="{{ asset('assets/favicons/favicon-96x96.png') }}"
    />
    <link
      rel="icon"
      type="image/png"
      sizes="192x192"
      href="{{ asset('assets/favicons/web-app-manifest-192x192.png') }}"
    />
    <link rel="apple-touch-icon" href="{{ asset('assets/favicons/apple-touch-icon.png') }}" />
    <link rel="manifest" href="{{ asset('assets/favicons/site.webmanifest') }}" />

    <meta
      name="msapplication-TileImage"
      content="assets/favicons/favicon-96x96.png"
    />
    <meta name="msapplication-TileColor" content="#ffffff" />

    <!-- Styles -->

    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="{{ asset('assets/css/style.css') }}" rel="stylesheet" />
    <style>
      [dir="ltr"] .lg\:-translate-x-12 {
        transform: translateX(-3rem);
      }
      [dir="rtl"] .rtl\:lg\:translate-x-12 {
        transform: translateX(3rem);
      }
    </style>

    <!-- Google Analytics -->

    <script
      async
      src="https://www.googletagmanager.com/gtag/js?id=G-NX3RP16W7Q"
    ></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag() {
        dataLayer.push(arguments);
      }
      gtag("js", new Date());
      gtag("config", "G-NX3RP16W7Q");
    </script>

    <!-- Google Search Console -->

    <meta
      name="google-site-verification"
      content="leQEJX4FXoNBgBQp4HQ1CsLcML_tw7CUwICRShoWgx4"
    />
      @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
  </head>
  <body class="antialiased selection:bg-sky-500 selection:text-white">
    <div class="bg-grain"></div>
    <div class="fixed inset-0 pointer-events-none -z-20 overflow-hidden">
      <div
        class="nebula-glow parallax-bg absolute w-[800px] h-[800px] top-[-200px] right-[-200px] opacity-60"
      ></div>
      <div
        class="teal-glow parallax-bg absolute w-[600px] h-[600px] bottom-[-100px] left-[-100px] opacity-40"
        style="animation-duration: 25s; animation-direction: alternate-reverse"
      ></div>
      <div
        class="nebula-glow parallax-bg absolute w-[500px] h-[500px] top-[40%] left-[20%] opacity-30"
        style="animation-duration: 30s"
      ></div>
    </div>
    <nav class="fixed top-8 left-0 right-0 z-50 flex justify-center px-4">
      <div
        class="glass-nav rounded-2xl px-8 py-4 flex items-center justify-between w-full max-w-[1200px] transition-all duration-500"
      >
        <div class="flex items-center gap-4">
          <div
            class="size-10 rounded-lg bg-gradient-to-br from-slate-800 to-black border border-white/10 flex items-center justify-center animate-[spin_10s_linear_infinite]"
          >
            <img src="{{ asset('assets/images/tiyar1.png') }}" alt="Tiyar" class="size-10" />
          </div>
          <h1
            id="brand-name"
            class="text-2xl font-bold tracking-wide text-white font-['IBM_Plex_Sans_Arabic']"
          >
            تيار
          </h1>
        </div>

        <div class="hidden md:flex items-center gap-12">
          <a
            id="nav-home"
            class="nav-link text-slate-400 hover:text-white font-medium text-sm transition-colors"
            href="#index"
            >الرئيسية</a
          >
          <a
            id="nav-services"
            class="nav-link text-slate-400 hover:text-white font-medium text-sm transition-colors"
            href="#services"
            >الخدمات</a
          >
          <a
            id="nav-about"
            class="nav-link text-slate-400 hover:text-white font-medium text-sm transition-colors"
            href="#about"
            >رحلتنا</a
          >
          <a
            id="nav-portfolio"
            class="nav-link text-slate-400 hover:text-white font-medium text-sm transition-colors"
            href="#portfolio"
            >الأعمال</a
          >
        </div>

        <div class="flex items-center gap-4">
          <button
            id="lang-switch"
            class="text-sky-400 border border-sky-400/30 px-3 py-1.5 mx-2 rounded-lg text-xs font-bold hover:bg-sky-400/10 transition-all"
          >
            English
          </button>
          <a href="https://wa.me/967780261952" target="_blank">
            <button
              class="magnetic-btn relative group overflow-hidden bg-white/5 border border-white/10 text-white px-6 py-2.5 rounded-xl font-bold text-sm"
            >
              <span id="btn-start-nav" class="relative z-10">ابدأ </span>
            </button>
          </a>
        </div>
      </div>
    </nav>
    <main>
        @yield('content')
    </main>
<footer
      class="pt-32 pb-12 relative overflow-hidden bg-gradient-to-t from-black to-[#0a0a0a]"
    >
      <div
        class="absolute top-0 left-0 right-0 h-[1px] bg-gradient-to-r from-transparent via-white/10 to-transparent"
      ></div>
      <div class="container mx-auto px-6 relative z-10">
        <div class="text-center mb-24 stagger-item">
          <h2
            class="text-5xl md:text-7xl font-extrabold mb-8 tracking-tight font-['IBM_Plex_Sans_Arabic']"
          >
            <span id="footer-cta-title" class="text-gold"
              >لنبني المستقبل معاً</span
            >
          </h2>
          <p
            id="footer-cta-desc"
            class="text-slate-400 mb-12 text-xl max-w-2xl mx-auto"
          >
            نحن جاهزون لتحويل رؤيتك إلى واقع رقمي استثنائي.
          </p>
          <a
            class="magnetic-btn inline-flex items-center gap-4 bg-white text-black px-8 py-4 rounded-full font-bold text-lg hover:scale-105 transition-transform"
            href="https://wa.me/967781152674"
          >
            <span id="footer-btn-contact">تواصل معنا</span>
            <span class="material-symbols-outlined rtl:rotate-0 ltr:rotate-180"
              >arrow_back</span
            >
          </a>
        </div>

        <div
          class="grid grid-cols-2 md:grid-cols-4 gap-12 text-start border-t border-white/5 pt-16 stagger-item"
        >
          <div>
            <h5
              id="f-col1-title"
              class="font-bold text-white mb-6 uppercase tracking-widest text-sm"
            >
              الشركة
            </h5>
            <ul class="space-y-4 text-slate-500 text-sm">
              <li>
                <a
                  id="f-link-about"
                  class="hover:text-sky-400 transition-colors"
                  href="#"
                  >من نحن</a
                >
              </li>
              <li>
                <a
                  id="f-link-jobs"
                  class="hover:text-sky-400 transition-colors"
                  href="#"
                  >الوظائف</a
                >
              </li>
              <li>
                <a
                  id="f-link-blog"
                  class="hover:text-sky-400 transition-colors"
                  href="#"
                  >المدونة</a
                >
              </li>
            </ul>
          </div>
          <div>
            <h5
              id="f-col2-title"
              class="font-bold text-white mb-6 uppercase tracking-widest text-sm"
            >
              الخدمات
            </h5>
            <ul class="space-y-4 text-slate-500 text-sm">
              <li>
                <a
                  id="f-link-web"
                  class="hover:text-sky-400 transition-colors"
                  href="#"
                  >تطوير المواقع</a
                >
              </li>
              <li>
                <a
                  id="f-link-mobile"
                  class="hover:text-sky-400 transition-colors"
                  href="#"
                  >تطبيقات الجوال</a
                >
              </li>
              <li>
                <a
                  id="f-link-ai"
                  class="hover:text-sky-400 transition-colors"
                  href="#"
                  >حلول الذكاء الاصطناعي</a
                >
              </li>
            </ul>
          </div>
          <div>
            <h5
              id="f-col3-title"
              class="font-bold text-white mb-6 uppercase tracking-widest text-sm"
            >
              تواصل
            </h5>
            <ul class="space-y-4 text-slate-500 text-sm">
              <li class="ltr:font-sans">hello@tayyar.com</li>
              <li class="ltr:font-sans">+967 781 152 674</li>
              <li id="f-location">حضرموت، اليمن</li>
            </ul>
          </div>
          <div>
            <h5
              id="f-col4-title"
              class="font-bold text-white mb-6 uppercase tracking-widest text-sm"
            >
              اجتماعي
            </h5>
            <div class="flex gap-4">
              <a
                class="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center text-slate-400 hover:bg-white hover:text-black transition-colors"
                href="#"
              >
                <span class="material-symbols-outlined text-sm"
                  >alternate_email</span
                >
              </a>
              <a
                class="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center text-slate-400 hover:bg-white hover:text-black transition-colors"
                href="#"
              >
                <span class="material-symbols-outlined text-sm">share</span>
              </a>
            </div>
          </div>
        </div>

        <div
          class="mt-20 pt-8 border-t border-white/5 flex flex-col md:flex-row items-center justify-between gap-4 text-xs text-slate-600"
        >
          <p id="f-copyright">
            © 2026 تيار للحلول التقنية. جميع الحقوق محفوظة.
          </p>
          <div class="flex items-center gap-2">
            <span id="f-made-with">صنع بشغف في حضرموت</span>
            <span class="material-symbols-outlined text-red-500 text-xs"
              >favorite</span
            >
          </div>
        </div>
      </div>
    </footer>

    <script src="{{ asset('assets/js/main.js') }}"></script>
    @livewireScripts
  </body>
</html>
