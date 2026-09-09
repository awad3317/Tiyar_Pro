@extends('layouts.app')

@section('content')
<section id="index">
      <header
        class="relative min-h-screen flex items-center pt-32 pb-20 overflow-hidden"
      >
        <div
          class="container mx-auto px-6 grid grid-cols-1 lg:grid-cols-2 gap-12 items-center"
        >
          <div class="order-1 lg:order-1 text-start space-y-8 z-10">
            <div
              class="hero-float-entrance inline-flex items-center gap-3 px-4 py-2 rounded-full bg-white/5 border border-white/10 backdrop-blur-md"
            >
              <span
                class="w-1.5 h-1.5 rounded-full bg-sky-400 shadow-[0_0_10px_#38bdf8] animate-pulse"
              ></span>
              <span
                id="hero-badge"
                class="text-xs font-bold uppercase tracking-[0.2em] text-slate-300"
                >مستقبل الرقمنة</span
              >
            </div>

            <h1
              class="text-6xl lg:text-8xl font-black leading-[1.1] font-['IBM_Plex_Sans_Arabic']"
            >
              <span class="hero-text-mask">
                <span
                  id="hero-title-1"
                  class="hero-text-reveal block text-metallic"
                  >تيار.. نصنع</span
                >
              </span>
              <span class="hero-text-mask">
                <span
                  id="hero-title-2"
                  class="hero-text-reveal block delay-100 text-transparent bg-clip-text bg-gradient-to-l from-purple-400 via-teal-200 to-white"
                  >المستحيل.</span
                >
              </span>
            </h1>

            <div class="hero-text-mask">
              <p
                id="hero-desc"
                class="hero-text-reveal delay-200 text-lg text-slate-400 font-light leading-relaxed max-w-xl border-s-2 border-slate-800 ps-6"
              >
                حيث تلتقي التكنولوجيا بالفن. نحن نصمم تجارب رقمية غامرة تعيد
                تعريف حدود الويب وتضع علامتك التجارية في الصدارة.
              </p>
            </div>

            <div class="hero-float-entrance flex items-center gap-6 pt-6">
              <a href="https://wa.me/967776023837" target="_blank">
                <button
                  id="hero-btn-main"
                  class="magnetic-btn px-8 py-4 rounded-xl bg-white text-black font-bold text-lg shadow-[0_0_30px_rgba(255,255,255,0.2)]"
                >
                  ابدأ مشروعك
                </button>
              </a>
            </div>
          </div>

          <div
            class="order-2 lg:order-2 flex justify-center relative perspective-1000"
          >
            <div
              class="relative w-full max-w-[700px] aspect-square flex items-center justify-center hero-float-entrance"
            >
              <div
                class="absolute inset-0 bg-sky-500/10 blur-[120px] rounded-full animate-pulse"
              ></div>
              <div
                class="relative z-10 w-full h-full flex items-center justify-center"
              >
                <img
                  alt="Tiyar Digital Art"
                  class="w-full h-full object-contain animate-[float_6s_ease-in-out_infinite]"
                  src="{{ asset('assets/images/tiyar2.png') }}"
                />

                <div
                  class="magnetic-btn absolute top-[20%] right-[10%] p-4 rounded-xl bg-black/40 border border-cyan-500/30 backdrop-blur-md"
                >
                  <div class="flex items-center gap-3">
                    <span
                      class="material-symbols-outlined text-cyan-400 text-lg"
                      >code</span
                    >
                    <div
                      class="h-1.5 w-12 bg-cyan-500/20 rounded-full overflow-hidden"
                    >
                      <div
                        class="h-full w-2/3 bg-cyan-400 rounded-full animate-[loading_2s_ease-in-out_infinite]"
                      ></div>
                    </div>
                  </div>
                </div>

                <div
                  class="magnetic-btn absolute bottom-[25%] left-[5%] p-4 rounded-xl bg-black/40 border border-purple-500/30 backdrop-blur-md"
                >
                  <div class="flex items-center gap-3">
                    <span
                      class="material-symbols-outlined text-purple-400 text-lg"
                      >view_in_ar</span
                    >
                    <span class="text-xs text-purple-200">
                      <span id="hero-stat-label">معالجة</span>:
                      <span class="font-mono">98%</span>
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </header>
    </section>
    <section class="py-24 relative" id="services">
      <div class="container mx-auto px-6">
        <div
          class="stagger-child flex flex-col md:flex-row justify-between items-end mb-20 gap-8"
          style="animation-delay: 0.2s"
        >
          <div class="space-y-4 text-start">
            <span
              id="ser-tag"
              class="text-sky-500 font-bold tracking-widest text-sm uppercase"
              >خدماتنا</span
            >
            <h2 class="text-4xl md:text-5xl font-bold text-white leading-tight">
              <span id="ser-title-main">حلول رقمية</span>
              <span id="ser-title-sub" class="text-slate-500">من بعد آخر</span>
            </h2>
          </div>
          <p
            id="ser-desc"
            class="text-slate-400 max-w-md text-lg leading-relaxed text-start"
          >
            نحول الأفكار المعقدة إلى واجهات زجاجية بسيطة وأنظمة برمجية صلبة.
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div
            class="service-module p-8 rounded-3xl group relative overflow-hidden stagger-item"
            style="animation-delay: 0.3s"
          >
            <div
              class="absolute top-0 right-0 w-32 h-32 bg-sky-500/10 blur-[50px] group-hover:bg-sky-500/20 transition-all duration-700"
            ></div>
            <div class="relative z-10 text-start">
              <div
                class="w-16 h-16 mb-8 relative group-hover:scale-110 transition-transform duration-500"
              >
                <div
                  class="absolute inset-0 bg-sky-500/20 blur-xl rounded-full"
                ></div>
                <span
                  class="material-symbols-outlined text-5xl text-sky-200 relative z-10"
                  >terminal</span
                >
              </div>
              <h3
                id="ser-1-title"
                class="text-2xl font-bold mb-3 text-white group-hover:text-sky-300"
              >
                هندسة البرمجيات
              </h3>
              <p id="ser-1-desc" class="text-slate-400 leading-relaxed text-sm">
                بناء أنظمة عالية الأداء باستخدام أحدث التقنيات لضمان الاستقرار
                والقابلية للتوسع.
              </p>
            </div>
          </div>

          <div
            class="service-module p-8 rounded-3xl group relative overflow-hidden stagger-item"
            style="animation-delay: 0.45s"
          >
            <div
              class="absolute top-0 right-0 w-32 h-32 bg-purple-500/10 blur-[50px] group-hover:bg-purple-500/20 transition-all duration-700"
            ></div>
            <div class="relative z-10 text-start">
              <div
                class="w-16 h-16 mb-8 relative group-hover:scale-110 transition-transform duration-500"
              >
                <div
                  class="absolute inset-0 bg-purple-500/20 blur-xl rounded-full"
                ></div>
                <span
                  class="material-symbols-outlined text-5xl text-purple-200 relative z-10"
                  >brush</span
                >
              </div>
              <h3
                id="ser-2-title"
                class="text-2xl font-bold mb-3 text-white group-hover:text-purple-300"
              >
                تصميم التجربة
              </h3>
              <p id="ser-2-desc" class="text-slate-400 leading-relaxed text-sm">
                واجهات مستخدم تدمج الجمالية بالوظيفة، مصممة بدقة البكسل لتأسر
                المستخدمين.
              </p>
            </div>
          </div>

          <div
            class="service-module p-8 rounded-3xl group relative overflow-hidden stagger-item"
            style="animation-delay: 0.6s"
          >
            <div
              class="absolute top-0 right-0 w-32 h-32 bg-teal-500/10 blur-[50px] group-hover:bg-teal-500/20 transition-all duration-700"
            ></div>
            <div class="relative z-10 text-start">
              <div
                class="w-16 h-16 mb-8 relative group-hover:scale-110 transition-transform duration-500"
              >
                <div
                  class="absolute inset-0 bg-teal-500/20 blur-xl rounded-full"
                ></div>
                <span
                  class="material-symbols-outlined text-5xl text-teal-200 relative z-10"
                  >cloud_circle</span
                >
              </div>
              <h3
                id="ser-3-title"
                class="text-2xl font-bold mb-3 text-white group-hover:text-teal-300"
              >
                البنية السحابية
              </h3>
              <p id="ser-3-desc" class="text-slate-400 leading-relaxed text-sm">
                حلول سحابية آمنة ومرنة تمكن أعمالك من النمو دون قيود البنية
                التحتية.
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>
    <section class="py-24 relative overflow-hidden" id="about">
      <div
        class="absolute top-0 left-1/2 -translate-x-1/2 w-[1px] h-full opacity-30"
      ></div>
      <div class="container mx-auto px-6 relative z-10">
        <div class="text-center mb-20 stagger-item">
          <span
            id="about-tag"
            class="text-purple-400 font-bold tracking-[0.2em] text-sm uppercase"
            >منهجية العمل</span
          >
          <h2
            class="text-4xl md:text-5xl font-bold text-white mt-4 font-['IBM_Plex_Sans_Arabic']"
          >
            <span id="about-title-main">رحلتنا نحو</span>
            <span
              id="about-title-sub"
              class="text-transparent bg-clip-text bg-gradient-to-r from-purple-400 to-sky-400"
              >الابتكار</span
            >
          </h2>
        </div>

        <div class="relative grid grid-cols-1 md:grid-cols-4 gap-12 text-start">
          <div
            class="process-node p-8 rounded-2xl relative group hover:-translate-y-4 transition-transform duration-500 stagger-item"
            style="animation-delay: 0.15s"
          >
            <div
              class="absolute -top-4 inset-inline-end-[-1rem] w-12 h-12 bg-slate-800 rounded-full border border-sky-500/50 flex items-center justify-center text-white font-bold z-20"
            >
              01
            </div>
            <div
              class="mb-6 w-14 h-14 bg-sky-500/10 rounded-lg flex items-center justify-center text-sky-400"
            >
              <span class="material-symbols-outlined text-3xl">lightbulb</span>
            </div>
            <h3 id="step-1-title" class="text-xl font-bold text-white mb-3">
              الاستراتيجية
            </h3>
            <p id="step-1-desc" class="text-slate-400 text-sm leading-relaxed">
              نحلل السوق ونفهم أهدافك لنرسم خارطة طريق دقيقة تضمن النجاح.
            </p>
          </div>

          <div
            class="process-node p-8 rounded-2xl relative group hover:-translate-y-4 transition-transform duration-500 mt-0 md:mt-12 stagger-item"
            style="animation-delay: 0.3s"
          >
            <div
              class="absolute -top-4 inset-inline-end-[-1rem] w-12 h-12 bg-slate-800 rounded-full border border-purple-500/50 flex items-center justify-center text-white font-bold z-20"
            >
              02
            </div>
            <div
              class="mb-6 w-14 h-14 bg-purple-500/10 rounded-lg flex items-center justify-center text-purple-400"
            >
              <span class="material-symbols-outlined text-3xl">palette</span>
            </div>
            <h3 id="step-2-title" class="text-xl font-bold text-white mb-3">
              التصميم
            </h3>
            <p id="step-2-desc" class="text-slate-400 text-sm leading-relaxed">
              نصمم تجارب بصرية مذهلة تركز على المستخدم وتعزز هوية علامتك.
            </p>
          </div>

          <div
            class="process-node p-8 rounded-2xl relative group hover:-translate-y-4 transition-transform duration-500 mt-0 md:mt-24 stagger-item"
            style="animation-delay: 0.45s"
          >
            <div
              class="absolute -top-4 inset-inline-end-[-1rem] w-12 h-12 bg-slate-800 rounded-full border border-teal-500/50 flex items-center justify-center text-white font-bold z-20"
            >
              03
            </div>
            <div
              class="mb-6 w-14 h-14 bg-teal-500/10 rounded-lg flex items-center justify-center text-teal-400"
            >
              <span class="material-symbols-outlined text-3xl"
                >code_blocks</span
              >
            </div>
            <h3 id="step-3-title" class="text-xl font-bold text-white mb-3">
              التطوير
            </h3>
            <p id="step-3-desc" class="text-slate-400 text-sm leading-relaxed">
              نحول التصاميم إلى واقع باستخدام أحدث تقنيات البرمجة النظيفة
              والسريعة.
            </p>
          </div>

          <div
            class="process-node p-8 rounded-2xl relative group hover:-translate-y-4 transition-transform duration-500 mt-0 md:mt-36 stagger-item"
            style="animation-delay: 0.6s"
          >
            <div
              class="absolute -top-4 inset-inline-end-[-1rem] w-12 h-12 bg-slate-800 rounded-full border border-amber-500/50 flex items-center justify-center text-white font-bold z-20"
            >
              04
            </div>
            <div
              class="mb-6 w-14 h-14 bg-amber-500/10 rounded-lg flex items-center justify-center text-amber-400"
            >
              <span class="material-symbols-outlined text-3xl"
                >rocket_launch</span
              >
            </div>
            <h3 id="step-4-title" class="text-xl font-bold text-white mb-3">
              الإطلاق
            </h3>
            <p id="step-4-desc" class="text-slate-400 text-sm leading-relaxed">
              نطلق مشروعك للعالم ونضمن تشغيله بكفاءة عالية مع دعم مستمر.
            </p>
          </div>
        </div>
      </div>
    </section>
    <section class="py-32 relative overflow-hidden" id="portfolio">
      <div
        class="absolute inset-0 bg-gradient-to-b from-transparent via-slate-900/50 to-transparent pointer-events-none"
      ></div>

      <div class="container mx-auto px-6 mb-16 relative z-10 stagger-item">
        <div
          class="flex justify-between items-end border-b border-white/5 pb-8"
        >
          <div class="text-start">
            <h2
              id="port-title"
              class="text-5xl font-bold text-white mb-2 font-['IBM_Plex_Sans_Arabic']"
            >
              أعمال مختارة
            </h2>
            <p id="port-subtitle" class="text-slate-400 text-lg">
              تحف فنية رقمية صممت لتبقى.
            </p>
          </div>
        </div>
      </div>

      <div class="container mx-auto px-6 relative z-10">
        <div class="flex flex-col gap-32">
          <div
            class="project-card-container group relative grid grid-cols-1 lg:grid-cols-12 gap-8 items-center stagger-item"
          >
            <div
              class="lg:col-span-4 order-2 lg:order-1 text-start relative lg:translate-x-12 rtl:lg:-translate-x-12 z-20"
            >
              <div
                class="bg-black/80 backdrop-blur-xl border border-white/10 p-8 rounded-2xl group-hover:border-purple-500/30 transition-all duration-500"
              >
                <span
                  id="p1-tag"
                  class="text-purple-400 font-mono text-sm tracking-widest mb-4 block"
                  >01 — ويب</span
                >
                <h3 id="p1-title" class="text-4xl font-bold text-white mb-4">
                  الزاجل – نظام توصيل طرود
                </h3>
                <p id="p1-desc" class="text-slate-400 leading-relaxed mb-8">
                  منصة لوجستية ذكية تجمع بين السرعة والدقة والأمان لإدارة عمليات
                  الشحن وتتبع الطرود في الوقت الحقيقي.
                </p>
                <div class="flex gap-3 flex-wrap mb-8">
                  <span
                    class="px-3 py-1 bg-white/5 rounded-full text-xs text-slate-300 border border-white/5"
                    >WebGL</span
                  >
                  <span
                    class="px-3 py-1 bg-white/5 rounded-full text-xs text-slate-300 border border-white/5"
                    >Three.js</span
                  >
                  <span
                    class="px-3 py-1 bg-white/5 rounded-full text-xs text-slate-300 border border-white/5"
                    >UX Design</span
                  >
                </div>
                <button
                  class="magnetic-btn flex items-center gap-3 text-white font-bold group-hover:gap-5 transition-all"
                >
                  <span class="project-view-btn">عرض المشروع</span>
                  <span
                    class="material-symbols-outlined text-purple-400 rtl:rotate-0 ltr:rotate-180"
                    >arrow_back</span
                  >
                </button>
              </div>
            </div>
            <div class="lg:col-span-8 order-1 lg:order-2 relative">
              <div
                class="project-glow-bg absolute -inset-4 bg-purple-500/20 blur-2xl rounded-[40px]"
              ></div>
              <div
                class="relative rounded-[32px] overflow-hidden aspect-[16/10] border border-white/10 shadow-2xl"
              >
                <img
                  alt="Al-Zajel Project"
                  class="project-card-image w-full h-full object-cover"
                  src="{{ asset('assets/images/projectes/trsport/transport.png') }}"
                />
              </div>
            </div>
          </div>

          <div
            class="project-card-container group relative grid grid-cols-1 lg:grid-cols-12 gap-8 items-center stagger-item"
          >
            <div class="lg:col-span-8 relative">
              <div
                class="project-glow-bg absolute -inset-4 bg-teal-500/20 blur-2xl rounded-[40px]"
              ></div>
              <div
                class="relative rounded-[32px] overflow-hidden aspect-[16/10] border border-white/10 shadow-2xl"
              >
                <img
                  alt="Happiness Store"
                  class="project-card-image w-full h-full object-cover"
                  src="{{ asset('assets/images/projectes/shop/shop.png') }}"
                />
              </div>
            </div>
            <div
              class="lg:col-span-4 text-start relative lg:-translate-x-12 rtl:lg:translate-x-12 z-20"
            >
              <div
                class="bg-black/80 backdrop-blur-xl border border-white/10 p-8 rounded-2xl group-hover:border-teal-500/30 transition-all duration-500"
              >
                <span
                  id="p2-tag"
                  class="text-teal-400 font-mono text-sm tracking-widest mb-4 block"
                  >02 — ويب</span
                >
                <h3 id="p2-title" class="text-4xl font-bold text-white mb-4">
                  متجر السعادة
                </h3>
                <p id="p2-desc" class="text-slate-400 leading-relaxed mb-8">
                  وجهتك لكل ما تحتاجه لصناعة الحلويات باحتراف. مستلزمات عالية
                  الجودة، وأدوات تزيين، لإبداع أشهى الحلويات.
                </p>
                <div class="flex gap-3 flex-wrap mb-8">
                  <span
                    class="px-3 py-1 bg-white/5 rounded-full text-xs text-slate-300 border border-white/5"
                    >Laravel</span
                  >
                  <span
                    class="px-3 py-1 bg-white/5 rounded-full text-xs text-slate-300 border border-white/5"
                    >Tailwind</span
                  >
                </div>
                <a
                  href="https://shop.tiyar.cc/"
                  target="_blank"
                  class="magnetic-btn flex items-center gap-3 text-white font-bold group-hover:gap-5 transition-all"
                >
                  <span class="project-view-btn">عرض المشروع</span>
                  <span
                    class="material-symbols-outlined text-teal-400 rtl:rotate-0 ltr:rotate-180"
                    >arrow_back</span
                  >
                </a>
              </div>
            </div>
          </div>

          <div
            class="project-card-container group relative grid grid-cols-1 lg:grid-cols-12 gap-8 items-center stagger-item"
          >
            <div class="lg:col-span-8 relative">
              <div
                class="project-glow-bg absolute -inset-4 bg-amber-500/20 blur-2xl rounded-[40px]"
              ></div>
              <div
                class="relative rounded-[32px] overflow-hidden aspect-[16/10] border border-white/10 shadow-2xl"
              >
                <img
                  alt="Wajhah App"
                  class="project-card-image w-full h-full object-cover"
                  src="{{ asset('assets/images/projectes/wajhah/wajha.png') }}"
                />
              </div>
            </div>
            <div
              class="lg:col-span-4 text-start relative lg:-translate-x-12 rtl:lg:translate-x-12 z-20"
            >
              <div
                class="bg-black/80 backdrop-blur-xl border border-white/10 p-8 rounded-2xl group-hover:border-amber-500/30 transition-all duration-500"
              >
                <span
                  id="p3-tag"
                  class="text-amber-400 font-mono text-sm tracking-widest mb-4 block"
                  >03 — تطبيق</span
                >
                <h3 id="p3-title" class="text-4xl font-bold text-white mb-4">
                  وجهه
                </h3>
                <p id="p3-desc" class="text-slate-400 leading-relaxed mb-8">
                  تطبيق وجهة لعرض الأماكن والفعاليات مع تجربة مستخدم مخصّصة
                  وواجهة أنيقة جذابة.
                </p>
                <div class="flex gap-3 flex-wrap mb-8">
                  <span
                    class="px-3 py-1 bg-white/5 rounded-full text-xs text-slate-300 border border-white/5"
                    >Flutter</span
                  >
                  <span
                    class="px-3 py-1 bg-white/5 rounded-full text-xs text-slate-300 border border-white/5"
                    >UI/UX</span
                  >
                </div>
                <button
                  class="magnetic-btn flex items-center gap-3 text-white font-bold group-hover:gap-5 transition-all"
                >
                  <span class="project-view-btn">عرض المشروع</span>
                  <span
                    class="material-symbols-outlined text-amber-400 rtl:rotate-0 ltr:rotate-180"
                    >arrow_back</span
                  >
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
    <section class="py-24 relative bg-black/50" id="stats">
      <div
        class="absolute inset-0 bg-gradient-to-r from-sky-900/5 via-transparent to-purple-900/5"
      ></div>
      <div class="container mx-auto px-6 relative z-10">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
          <div
            class="relative p-6 border-e border-white/5 text-center group stagger-item"
            style="animation-delay: 0.2s"
          >
            <div
              class="stat-number text-6xl font-bold mb-2 group-hover:scale-110 transition-transform duration-500 text-white ltr:font-sans"
            >
              45+
            </div>
            <div
              id="stat-1-label"
              class="text-sky-400 text-sm font-bold uppercase tracking-widest mb-2"
            >
              مشروعاً متكاملاً
            </div>
            <div
              class="w-12 h-1 bg-gradient-to-r from-transparent via-sky-500 to-transparent mx-auto opacity-50 group-hover:w-24 transition-all duration-500"
            ></div>
          </div>

          <div
            class="relative p-6 border-e border-white/5 text-center group stagger-item"
            style="animation-delay: 0.35s"
          >
            <div
              class="stat-number text-6xl font-bold mb-2 group-hover:scale-110 transition-transform duration-500 text-white ltr:font-sans"
            >
              6+
            </div>
            <div
              id="stat-2-label"
              class="text-purple-400 text-sm font-bold uppercase tracking-widest mb-2"
            >
              سنوات خبرة
            </div>
            <div
              class="w-12 h-1 bg-gradient-to-r from-transparent via-purple-500 to-transparent mx-auto opacity-50 group-hover:w-24 transition-all duration-500"
            ></div>
          </div>

          <div
            class="relative p-6 border-e border-white/5 text-center group stagger-item"
            style="animation-delay: 0.5s"
          >
            <div
              class="stat-number text-6xl font-bold mb-2 group-hover:scale-110 transition-transform duration-500 text-white ltr:font-sans"
            >
              12+
            </div>
            <div
              id="stat-3-label"
              class="text-teal-400 text-sm font-bold uppercase tracking-widest mb-2"
            >
              شريك نجاح دائم
            </div>
            <div
              class="w-12 h-1 bg-gradient-to-r from-transparent via-teal-500 to-transparent mx-auto opacity-50 group-hover:w-24 transition-all duration-500"
            ></div>
          </div>

          <div
            class="relative p-6 text-center group stagger-item"
            style="animation-delay: 0.65s"
          >
            <div
              class="stat-number text-6xl font-bold mb-2 group-hover:scale-110 transition-transform duration-500 text-white ltr:font-sans"
            >
              100%
            </div>
            <div
              id="stat-4-label"
              class="text-amber-400 text-sm font-bold uppercase tracking-widest mb-2"
            >
              التزام بالمواعيد
            </div>
            <div
              class="w-12 h-1 bg-gradient-to-r from-transparent via-amber-500 to-transparent mx-auto opacity-50 group-hover:w-24 transition-all duration-500"
            ></div>
          </div>
        </div>
      </div>
    </section>
@endsection
