@extends('office.layouts.app')

@section('title', 'روابط المكاتب الخارجية')

@section('shell-class', 'bg-slate-50')

@section('content')
    @php
        $totalLinksCount = $links->count();
        $activeLinksCount = $links->where('is_active', true)->count();
        $totalReceivedShipments = (int) $links->sum('shipments_count');

        $hasValidationErrors = $errors->partnerLink->any();
        $initialFormMode = old('_form_mode', 'create');
        $initialEditAction = old('_edit_action', '');
    @endphp

    <main class="flex-1 p-3.5 lg:p-6 space-y-3.5"
          x-data="externalPartnerLinksPage({
              officeName: @js($office->name . ($office->branch_name ? ' (' . $office->branch_name . ')' : '')),
              storeUrl: @js(route('office.external-partner-links.store')),
              hasErrors: {{ $hasValidationErrors ? 'true' : 'false' }},
              initialMode: @js($initialFormMode),
              initialEditAction: @js($initialEditAction),
              oldInput: {
                  partner_name: @js(old('partner_name', '')),
                  partner_city: @js(old('partner_city', '')),
                  partner_phone: @js(old('partner_phone', '')),
                  receipt_prefix: @js(old('receipt_prefix', '')),
                  access_pin: @js(old('access_pin', '')),
              }
          })"
          @open-partner-link-modal.window="openCreateModal()">

        {{-- شريط أدوات سطح المكتب الاحترافي (lg: >= 1024px): ملخص سريع + مبدّل (جدول / بطاقات) --}}
        <div class="hidden lg:flex items-center justify-between gap-4">
            <div class="inline-flex items-center gap-2 p-1 rounded-xl bg-slate-200/70 border border-slate-200/90 text-xs">
                <span class="px-3 py-1.5 rounded-lg bg-white text-slate-900 shadow-xs font-extrabold inline-flex items-center gap-1.5">
                    <span>إجمالي الروابط</span>
                    <span class="px-1.5 py-0.5 rounded-md bg-secondary/20 text-amber-900 font-mono text-[11px] leading-none">{{ $totalLinksCount }}</span>
                </span>
                <span class="px-3 py-1.5 text-slate-600 font-bold inline-flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    <span>النشطة: <strong class="font-mono text-slate-800">{{ $activeLinksCount }}</strong></span>
                </span>
                <span class="px-3 py-1.5 text-slate-600 font-bold inline-flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[15px] text-slate-400">local_shipping</span>
                    <span>الشحنات المستلمة: <strong class="font-mono text-slate-800">{{ number_format($totalReceivedShipments) }}</strong></span>
                </span>
            </div>

            <div class="inline-flex items-center gap-1 p-1 rounded-xl bg-white border border-slate-200 shadow-2xs">
                <button type="button"
                        @click="setDesktopView('table')"
                        :class="desktopView === 'table'
                            ? 'bg-primary text-secondary shadow-2xs font-extrabold'
                            : 'text-slate-500 hover:text-slate-800 font-bold'"
                        class="px-3 py-1.5 rounded-lg text-xs inline-flex items-center gap-1.5 transition-all">
                    <span class="material-symbols-outlined text-[16px]">table_rows</span>
                    <span>جدول</span>
                </button>
                <button type="button"
                        @click="setDesktopView('cards')"
                        :class="desktopView === 'cards'
                            ? 'bg-primary text-secondary shadow-2xs font-extrabold'
                            : 'text-slate-500 hover:text-slate-800 font-bold'"
                        class="px-3 py-1.5 rounded-lg text-xs inline-flex items-center gap-1.5 transition-all">
                    <span class="material-symbols-outlined text-[16px]">grid_view</span>
                    <span>بطاقات</span>
                </button>
            </div>
        </div>

        {{-- أ. قائمة البطاقات (دائماً على الجوال < 1024px، وعلى الكمبيوتر عند اختيار وضع البطاقات) --}}
        <section :class="desktopView === 'table' ? 'lg:hidden' : 'lg:space-y-0 lg:grid lg:grid-cols-2 2xl:grid-cols-3 lg:gap-4'"
                 class="space-y-3">
            @forelse ($links as $link)
                @php
                    $portalUrl = url('/p/' . $link->token);
                    $lastUsedHuman = $link->last_used_at
                        ? $link->last_used_at->copy()->locale('ar')->diffForHumans()
                        : 'لم يُستخدم بعد';

                    $linkPayload = [
                        'id'             => $link->id,
                        'partner_name'   => $link->partner_name,
                        'partner_city'   => $link->partner_city ?? '',
                        'partner_phone'  => $link->partner_phone ?? '',
                        'receipt_prefix' => $link->receipt_prefix,
                        'access_pin'     => $link->access_pin ?? '',
                        'is_active'      => (bool) $link->is_active,
                        'portal_url'     => $portalUrl,
                        'update_url'     => route('office.external-partner-links.update', $link),
                        'regenerate_url' => route('office.external-partner-links.regenerate-token', $link),
                        'destroy_url'    => route('office.external-partner-links.destroy', $link),
                    ];
                @endphp

                <article x-data="{ menuOpen: false }"
                         :class="menuOpen ? 'relative z-30' : 'relative'"
                         class="bg-white border {{ $link->is_active ? 'border-slate-200/90' : 'border-rose-200/80 bg-rose-50/20' }} rounded-2xl p-3.5 shadow-2xs space-y-3 transition-colors">
                    {{-- رأس البطاقة: الاسم + المدينة | شارة الحالة + قائمة الثلاث نقاط (⋮) لجميع الإجراءات --}}
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-start gap-2.5 min-w-0 flex-1">
                            <span class="w-10 h-10 rounded-xl {{ $link->is_active ? 'bg-primary text-secondary' : 'bg-slate-200 text-slate-500' }} flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-xl">{{ $link->is_active ? 'link' : 'link_off' }}</span>
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <h3 class="text-xs sm:text-sm font-extrabold text-slate-900 truncate">{{ $link->partner_name }}</h3>
                                    @if ($link->partner_city)
                                        <span class="px-2 py-0.5 rounded-md bg-sky-50 text-sky-700 border border-sky-200 text-[10px] font-bold">
                                            {{ $link->partner_city }}
                                        </span>
                                    @endif
                                </div>
                                @if ($link->partner_phone)
                                    <p class="text-[11px] font-mono font-bold text-slate-500 mt-0.5 dir-ltr text-right">
                                        {{ $link->partner_phone }}
                                    </p>
                                @endif
                            </div>
                        </div>

                        <div class="shrink-0 flex items-center gap-1.5">
                            @if ($link->is_active)
                                <span class="px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-extrabold inline-flex items-center gap-1 whitespace-nowrap">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    نشط
                                </span>
                            @else
                                <span class="px-2.5 py-0.5 rounded-full bg-rose-50 text-rose-700 border border-rose-200 text-[10px] font-extrabold inline-flex items-center gap-1 whitespace-nowrap">
                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                    موقوف
                                </span>
                            @endif

                            {{-- قائمة الثلاث نقاط (⋮) الحصرية لجميع إجراءات الرابط --}}
                            <div class="relative" @click.outside="menuOpen = false" @keydown.escape.window="menuOpen = false">
                                <button type="button"
                                        @click="menuOpen = !menuOpen"
                                        title="إجراءات الرابط"
                                        aria-label="إجراءات الرابط"
                                        :aria-expanded="menuOpen ? 'true' : 'false'"
                                        class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center transition-colors shrink-0">
                                    <span class="material-symbols-outlined text-[18px]">more_vert</span>
                                </button>

                                <div x-show="menuOpen"
                                     x-cloak
                                     x-transition:enter="ease-out duration-150"
                                     x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                     x-transition:leave="ease-in duration-100"
                                     x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                     x-transition:leave-end="opacity-0 -translate-y-1 scale-95"
                                     class="absolute left-0 top-full mt-1.5 w-48 bg-white rounded-2xl shadow-xl border border-slate-200/90 py-1.5 z-40 text-right">

                                    {{-- 1. نسخ الرابط --}}
                                    <button type="button"
                                            @click="menuOpen = false; copyPortalLink(@js($portalUrl))"
                                            class="w-full px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 flex items-center gap-2 transition-colors">
                                        <span class="material-symbols-outlined text-base text-primary">content_copy</span>
                                        <span>نسخ الرابط</span>
                                    </button>

                                    {{-- 2. مشاركة عبر واتساب --}}
                                    <a :href="buildWhatsappShareUrl(@js($linkPayload))"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       @click="menuOpen = false"
                                       class="w-full px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 flex items-center gap-2 transition-colors">
                                        <span class="material-symbols-outlined text-base text-emerald-600">chat</span>
                                        <span>مشاركة عبر واتساب</span>
                                    </a>

                                    <div class="my-1 border-t border-slate-100"></div>

                                    {{-- 3. تعديل البيانات --}}
                                    <button type="button"
                                            @click="menuOpen = false; openEditModal(@js($linkPayload))"
                                            class="w-full px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 flex items-center gap-2 transition-colors">
                                        <span class="material-symbols-outlined text-base text-amber-600">edit</span>
                                        <span>تعديل البيانات</span>
                                    </button>

                                    {{-- 4. تغيير الرابط (توليد توكن جديد) --}}
                                    <button type="button"
                                            @click="menuOpen = false; openRegenerateModal(@js($linkPayload))"
                                            class="w-full px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 flex items-center gap-2 transition-colors">
                                        <span class="material-symbols-outlined text-base text-sky-600">autorenew</span>
                                        <span>تغيير الرابط</span>
                                    </button>

                                    {{-- 5. إيقاف / تفعيل الرابط --}}
                                    <form method="POST" action="{{ route('office.external-partner-links.toggle-active', $link) }}" class="m-0">
                                        @csrf
                                        <button type="submit"
                                                @click="menuOpen = false"
                                                class="w-full px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 flex items-center gap-2 transition-colors">
                                            <span class="material-symbols-outlined text-base {{ $link->is_active ? 'text-amber-600' : 'text-emerald-600' }}">
                                                {{ $link->is_active ? 'pause_circle' : 'play_circle' }}
                                            </span>
                                            <span>{{ $link->is_active ? 'إيقاف الرابط مؤقتاً' : 'تفعيل الرابط' }}</span>
                                        </button>
                                    </form>

                                    <div class="my-1 border-t border-slate-100"></div>

                                    {{-- 6. حذف الرابط --}}
                                    <button type="button"
                                            @click="menuOpen = false; openDeleteModal(@js($linkPayload))"
                                            class="w-full px-3.5 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50 flex items-center gap-2 transition-colors">
                                        <span class="material-symbols-outlined text-base text-rose-600">delete</span>
                                        <span>حذف الرابط</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- شارات البادئة + رمز PIN + الإحصائيات --}}
                    <div class="grid grid-cols-2 gap-2 bg-slate-50 rounded-xl p-2.5 border border-slate-100 text-[11px]">
                        <div class="space-y-1">
                            <div class="flex items-center justify-between gap-1">
                                <span class="text-slate-400 font-bold">بادئة السند:</span>
                                <span class="px-1.5 py-0.5 rounded bg-white border border-slate-200 font-mono font-black text-primary dir-ltr">
                                    {{ $link->receipt_prefix }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between gap-1">
                                <span class="text-slate-400 font-bold">رمز PIN:</span>
                                @if ($link->access_pin)
                                    <span class="px-1.5 py-0.5 rounded bg-amber-50 border border-amber-200 font-mono font-black text-amber-900 dir-ltr">
                                        {{ $link->access_pin }}
                                    </span>
                                @else
                                    <span class="text-[10px] font-bold text-slate-400">بدون رمز</span>
                                @endif
                            </div>
                        </div>

                        <div class="space-y-1 border-r border-slate-200 pr-2.5">
                            <div class="flex items-center justify-between gap-1">
                                <span class="text-slate-400 font-bold">الشحنات:</span>
                                <span class="font-mono font-black text-slate-800">{{ number_format((int) $link->shipments_count) }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-1">
                                <span class="text-slate-400 font-bold">آخر استخدام:</span>
                                <span class="text-[10px] font-bold text-slate-600 truncate" title="{{ $link->last_used_at?->format('Y-m-d H:i') }}">
                                    {{ $lastUsedHuman }}
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- عرض عنوان الرابط بشكل نظيف ومضغوط --}}
                    <div class="flex items-center gap-2 bg-slate-100/80 border border-slate-200/80 rounded-xl px-2.5 py-1.5">
                        <span class="material-symbols-outlined text-sm text-slate-400 shrink-0">public</span>
                        <span class="flex-1 font-mono text-[11px] text-slate-600 truncate dir-ltr text-left select-all">{{ $portalUrl }}</span>
                    </div>
                </article>
            @empty
                <div class="lg:col-span-full bg-white rounded-2xl border border-slate-200/80 p-8 text-center space-y-3">
                    <div class="w-14 h-14 mx-auto rounded-2xl bg-amber-50 border border-amber-100 text-amber-600 flex items-center justify-center">
                        <span class="material-symbols-outlined text-3xl">add_link</span>
                    </div>
                    <div class="space-y-1">
                        <p class="text-xs font-extrabold text-slate-800">لا توجد روابط لمكاتب خارجية بعد</p>
                        <p class="text-[11px] text-slate-400 leading-relaxed">
                            أضف أول مكتب خارجي شريك لإنشاء رابط بوابة خاص به لإرسال كشوفات الطرود مباشرة إليك.
                        </p>
                    </div>
                    <button type="button"
                            @click="openCreateModal()"
                            class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-primary text-secondary text-xs font-extrabold shadow-xs">
                        <span class="material-symbols-outlined text-base">add</span>
                        <span>إضافة مكتب خارجي الآن</span>
                    </button>
                </div>
            @endforelse
        </section>

        {{-- ب. وضع الجدول الاحترافي لسطح المكتب (lg: >= 1024px عند اختيار وضع الجدول) --}}
        <section x-show="desktopView === 'table'"
                 x-cloak
                 class="hidden lg:block">
            @if ($links->isEmpty())
                <div class="bg-white rounded-2xl border border-slate-200 p-10 text-center space-y-3 shadow-2xs">
                    <div class="w-14 h-14 mx-auto rounded-2xl bg-amber-50 border border-amber-100 text-amber-600 flex items-center justify-center">
                        <span class="material-symbols-outlined text-3xl">add_link</span>
                    </div>
                    <div class="space-y-1">
                        <p class="text-xs font-extrabold text-slate-800">لا توجد روابط لمكاتب خارجية بعد</p>
                        <p class="text-[11px] text-slate-400">اضغط على (+ إضافة مكتب خارجي) أعلى الشاشة لإنشاء رابط بوابة خاص بشريك خارجي.</p>
                    </div>
                </div>
            @else
                <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs overflow-visible">
                    <table class="w-full text-right border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/80 text-[11px] font-extrabold text-slate-500">
                                <th class="py-3 pr-4 pl-3 rounded-tr-2xl">المكتب الشريك والمدينة</th>
                                <th class="py-3 px-3">بادئة السند ورمز PIN</th>
                                <th class="py-3 px-3">الشحنات وآخر استخدام</th>
                                <th class="py-3 px-3">رابط البوابة</th>
                                <th class="py-3 px-3">الحالة</th>
                                <th class="py-3 pr-3 pl-4 text-left rounded-tl-2xl">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs">
                            @foreach ($links as $link)
                                @php
                                    $portalUrl = url('/p/' . $link->token);
                                    $lastUsedHuman = $link->last_used_at
                                        ? $link->last_used_at->copy()->locale('ar')->diffForHumans()
                                        : 'لم يُستخدم بعد';

                                    $linkPayload = [
                                        'id'             => $link->id,
                                        'partner_name'   => $link->partner_name,
                                        'partner_city'   => $link->partner_city ?? '',
                                        'partner_phone'  => $link->partner_phone ?? '',
                                        'receipt_prefix' => $link->receipt_prefix,
                                        'access_pin'     => $link->access_pin ?? '',
                                        'is_active'      => (bool) $link->is_active,
                                        'portal_url'     => $portalUrl,
                                        'update_url'     => route('office.external-partner-links.update', $link),
                                        'regenerate_url' => route('office.external-partner-links.regenerate-token', $link),
                                        'destroy_url'    => route('office.external-partner-links.destroy', $link),
                                    ];
                                @endphp
                                <tr x-data="{ menuOpen: false }"
                                    :class="menuOpen ? 'relative z-30 bg-slate-50/90' : 'hover:bg-slate-50/70'"
                                    class="transition-colors">

                                    {{-- 1. المكتب الشريك والمدينة --}}
                                    <td class="py-3 pr-4 pl-3 align-middle">
                                        <div class="flex items-center gap-2.5">
                                            <span class="w-1.5 h-8 rounded-full shrink-0 {{ $link->is_active ? 'bg-emerald-500' : 'bg-rose-400' }}"></span>
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <span class="font-extrabold text-slate-900 truncate max-w-[180px]">{{ $link->partner_name }}</span>
                                                    @if ($link->partner_city)
                                                        <span class="px-2 py-0.5 rounded-md bg-sky-50 text-sky-700 border border-sky-200 text-[10px] font-bold">
                                                            {{ $link->partner_city }}
                                                        </span>
                                                    @endif
                                                </div>
                                                @if ($link->partner_phone)
                                                    <p class="text-[11px] font-mono text-slate-500 dir-ltr text-right mt-0.5">{{ $link->partner_phone }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    {{-- 2. بادئة السند ورمز PIN --}}
                                    <td class="py-3 px-3 align-middle whitespace-nowrap">
                                        <div class="flex items-center gap-1.5">
                                            <span class="px-2 py-0.5 rounded-md bg-slate-100 border border-slate-200 font-mono font-black text-primary text-[11px] dir-ltr">
                                                {{ $link->receipt_prefix }}
                                            </span>
                                            @if ($link->access_pin)
                                                <span class="px-2 py-0.5 rounded-md bg-amber-50 border border-amber-200 font-mono font-black text-amber-900 text-[11px] dir-ltr" title="رمز الدخول PIN">
                                                    PIN: {{ $link->access_pin }}
                                                </span>
                                            @else
                                                <span class="text-[11px] text-slate-400 font-semibold">بدون PIN</span>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- 3. الشحنات وآخر استخدام --}}
                                    <td class="py-3 px-3 align-middle whitespace-nowrap">
                                        <p class="font-bold text-slate-800">
                                            <span class="font-mono font-black">{{ number_format((int) $link->shipments_count) }}</span>
                                            <span class="text-[11px] text-slate-500">شحنة</span>
                                        </p>
                                        <p class="text-[10px] text-slate-400 mt-0.5" title="{{ $link->last_used_at?->format('Y-m-d H:i') }}">
                                            {{ $lastUsedHuman }}
                                        </p>
                                    </td>

                                    {{-- 4. رابط البوابة --}}
                                    <td class="py-3 px-3 align-middle">
                                        <div class="inline-flex items-center gap-1.5 bg-slate-100/80 border border-slate-200/80 rounded-lg px-2.5 py-1 max-w-[230px]">
                                            <span class="material-symbols-outlined text-[14px] text-slate-400 shrink-0">public</span>
                                            <span class="font-mono text-[11px] text-slate-600 truncate dir-ltr select-all">{{ $portalUrl }}</span>
                                        </div>
                                    </td>

                                    {{-- 5. الحالة --}}
                                    <td class="py-3 px-3 align-middle whitespace-nowrap">
                                        @if ($link->is_active)
                                            <span class="px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-extrabold inline-flex items-center gap-1">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                نشط
                                            </span>
                                        @else
                                            <span class="px-2.5 py-0.5 rounded-full bg-rose-50 text-rose-700 border border-rose-200 text-[10px] font-extrabold inline-flex items-center gap-1">
                                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                                موقوف
                                            </span>
                                        @endif
                                    </td>

                                    {{-- 6. الإجراءات المباشرة: نسخ الرابط + واتساب + قائمة ⋮ --}}
                                    <td class="py-3 pr-3 pl-4 align-middle whitespace-nowrap text-left">
                                        <div class="inline-flex items-center justify-end gap-1.5">
                                            <button type="button"
                                                    @click="copyPortalLink(@js($portalUrl))"
                                                    class="h-8 px-3 rounded-lg bg-slate-100 hover:bg-primary text-slate-700 hover:text-secondary border border-slate-200/90 hover:border-primary text-xs font-extrabold inline-flex items-center gap-1.5 transition-all">
                                                <span class="material-symbols-outlined text-[15px]">content_copy</span>
                                                <span>نسخ الرابط</span>
                                            </button>

                                            <a :href="buildWhatsappShareUrl(@js($linkPayload))"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               title="مشاركة عبر واتساب"
                                               class="w-8 h-8 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200/80 flex items-center justify-center transition-colors">
                                                <span class="material-symbols-outlined text-[16px]">chat</span>
                                            </a>

                                            <div class="relative" @click.outside="menuOpen = false" @keydown.escape.window="menuOpen = false">
                                                <button type="button"
                                                        @click="menuOpen = !menuOpen"
                                                        title="إجراءات إضافية"
                                                        class="w-8 h-8 rounded-lg bg-white hover:bg-slate-100 text-slate-600 border border-slate-200 flex items-center justify-center transition-colors">
                                                    <span class="material-symbols-outlined text-[18px]">more_vert</span>
                                                </button>

                                                <div x-show="menuOpen"
                                                     x-cloak
                                                     x-transition:enter="ease-out duration-150"
                                                     x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                                                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                                     x-transition:leave="ease-in duration-100"
                                                     x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                                     x-transition:leave-end="opacity-0 -translate-y-1 scale-95"
                                                     class="absolute left-0 top-full mt-1.5 w-48 bg-white rounded-2xl shadow-xl border border-slate-200/90 py-1.5 z-40 text-right">
                                                    <button type="button"
                                                            @click="menuOpen = false; openEditModal(@js($linkPayload))"
                                                            class="w-full px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 flex items-center gap-2 transition-colors">
                                                        <span class="material-symbols-outlined text-base text-amber-600">edit</span>
                                                        <span>تعديل البيانات</span>
                                                    </button>

                                                    <button type="button"
                                                            @click="menuOpen = false; openRegenerateModal(@js($linkPayload))"
                                                            class="w-full px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 flex items-center gap-2 transition-colors">
                                                        <span class="material-symbols-outlined text-base text-sky-600">autorenew</span>
                                                        <span>تغيير الرابط</span>
                                                    </button>

                                                    <form method="POST" action="{{ route('office.external-partner-links.toggle-active', $link) }}" class="m-0">
                                                        @csrf
                                                        <button type="submit"
                                                                @click="menuOpen = false"
                                                                class="w-full px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 flex items-center gap-2 transition-colors">
                                                            <span class="material-symbols-outlined text-base {{ $link->is_active ? 'text-amber-600' : 'text-emerald-600' }}">
                                                                {{ $link->is_active ? 'pause_circle' : 'play_circle' }}
                                                            </span>
                                                            <span>{{ $link->is_active ? 'إيقاف الرابط مؤقتاً' : 'تفعيل الرابط' }}</span>
                                                        </button>
                                                    </form>

                                                    <div class="my-1 border-t border-slate-100"></div>

                                                    <button type="button"
                                                            @click="menuOpen = false; openDeleteModal(@js($linkPayload))"
                                                            class="w-full px-3.5 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50 flex items-center gap-2 transition-colors">
                                                        <span class="material-symbols-outlined text-base text-rose-600">delete</span>
                                                        <span>حذف الرابط</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- 1. نافذة إضافة / تعديل مكتب خارجي --}}
        <div x-show="formModal.open"
             x-cloak
             @keydown.escape.window="closeFormModal()"
             class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-3 sm:p-4">
            <div class="fixed inset-0 bg-slate-950/60" @click="closeFormModal()"></div>

            <div class="relative w-full max-w-md bg-white rounded-3xl p-5 shadow-2xl border border-slate-100 z-10 max-h-[90vh] overflow-y-auto space-y-4">
                <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="w-9 h-9 rounded-xl bg-primary text-secondary flex items-center justify-center">
                            <span class="material-symbols-outlined text-lg" x-text="formModal.mode === 'edit' ? 'edit_square' : 'add_link'"></span>
                        </span>
                        <div>
                            <h3 class="text-sm font-extrabold text-slate-800"
                                x-text="formModal.mode === 'edit' ? 'تعديل بيانات المكتب الخارجي' : 'إضافة مكتب خارجي جديد'"></h3>
                            <p class="text-[10px] text-slate-400">سيُنشأ رابط دائم خاص بهذا المكتب لإرسال الشحنات إليك</p>
                        </div>
                    </div>
                    <button type="button" @click="closeFormModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center">
                        <span class="material-symbols-outlined text-base">close</span>
                    </button>
                </div>

                @if ($hasValidationErrors)
                    <div class="bg-rose-50 border border-rose-200 rounded-2xl p-3 text-xs font-bold text-rose-800 space-y-1">
                        @foreach ($errors->partnerLink->all() as $error)
                            <div class="flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-sm text-rose-600 shrink-0">error</span>
                                <span>{{ $error }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                <form method="POST" :action="formModal.actionUrl" class="space-y-3.5">
                    @csrf
                    <template x-if="formModal.mode === 'edit'">
                        <input type="hidden" name="_method" value="PUT">
                    </template>
                    <input type="hidden" name="_form_mode" :value="formModal.mode">
                    <input type="hidden" name="_edit_action" :value="formModal.actionUrl">

                    <div>
                        <label class="block text-xs font-extrabold text-slate-700 mb-1">
                            اسم المكتب الخارجي <span class="text-rose-600">*</span>
                        </label>
                        <input type="text"
                               name="partner_name"
                               x-model="form.partner_name"
                               required
                               maxlength="150"
                               placeholder="مثال: مكتب البراق للنقل السريع"
                               class="w-full rounded-xl border border-slate-300 bg-slate-50 focus:bg-white focus:border-secondary px-3 py-2.5 text-xs font-bold text-slate-800 outline-none">
                    </div>

                    <div class="grid grid-cols-2 gap-2.5">
                        <div>
                            <label class="block text-xs font-extrabold text-slate-700 mb-1">المدينة / المنطقة</label>
                            <input type="text"
                                   name="partner_city"
                                   x-model="form.partner_city"
                                   maxlength="100"
                                   placeholder="مثال: مأرب"
                                   class="w-full rounded-xl border border-slate-300 bg-slate-50 focus:bg-white focus:border-secondary px-3 py-2.5 text-xs font-bold text-slate-800 outline-none">
                        </div>

                        <div>
                            <label class="block text-xs font-extrabold text-slate-700 mb-1">رقم جوال المكتب</label>
                            <input type="tel"
                                   name="partner_phone"
                                   x-model="form.partner_phone"
                                   dir="ltr"
                                   maxlength="20"
                                   placeholder="77XXXXXXX"
                                   class="w-full rounded-xl border border-slate-300 bg-slate-50 focus:bg-white focus:border-secondary px-3 py-2.5 text-xs font-mono font-bold text-slate-800 outline-none text-left">
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-xs font-extrabold text-slate-700">
                                بادئة أرقام السندات <span class="text-rose-600">*</span>
                            </label>
                            <button type="button"
                                    @click="suggestPrefix()"
                                    class="text-[11px] font-extrabold text-secondary hover:text-amber-600 inline-flex items-center gap-0.5">
                                <span class="material-symbols-outlined text-sm">auto_awesome</span>
                                <span>اقتراح تلقائي</span>
                            </button>
                        </div>
                        <input type="text"
                               name="receipt_prefix"
                               x-model="form.receipt_prefix"
                               @input="form.receipt_prefix = String(form.receipt_prefix || '').toUpperCase().replace(/\s+/g, '')"
                               required
                               dir="ltr"
                               maxlength="12"
                               placeholder="MRB-"
                               class="w-full rounded-xl border border-slate-300 bg-slate-50 focus:bg-white focus:border-secondary px-3 py-2.5 text-xs font-mono font-black text-primary outline-none text-left uppercase">
                        <p class="text-[10px] text-slate-400 mt-1">
                            تُضاف تلقائياً قبل أرقام سندات المكتب الخارجي لمنع تشابه الأرقام (مثال: <span class="font-mono font-bold">MRB-105</span>).
                        </p>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-xs font-extrabold text-slate-700">رمز حماية إضافي PIN (اختياري - 4 أرقام)</label>
                            <div class="flex items-center gap-2">
                                <button type="button"
                                        @click="generatePin()"
                                        class="text-[11px] font-extrabold text-sky-600 hover:text-sky-700">
                                    توليد رمز
                                </button>
                                <button type="button"
                                        x-show="form.access_pin"
                                        @click="form.access_pin = ''"
                                        class="text-[11px] font-bold text-rose-600 hover:text-rose-700">
                                    مسح
                                </button>
                            </div>
                        </div>
                        <input type="text"
                               name="access_pin"
                               x-model="form.access_pin"
                               @input="form.access_pin = normalizePin(form.access_pin)"
                               inputmode="numeric"
                               dir="ltr"
                               maxlength="4"
                               placeholder="اتركه فارغاً للدخول المباشر عبر الرابط"
                               class="w-full rounded-xl border border-slate-300 bg-slate-50 focus:bg-white focus:border-secondary px-3 py-2.5 text-xs font-mono font-black text-slate-800 outline-none text-left tracking-widest">
                    </div>

                    <div class="flex flex-col gap-2 pt-2">
                        <button type="submit"
                                class="w-full py-2.5 rounded-xl bg-primary hover:bg-slate-800 text-secondary text-xs font-extrabold flex items-center justify-center gap-1.5 shadow-xs transition-colors">
                            <span class="material-symbols-outlined text-base" x-text="formModal.mode === 'edit' ? 'save' : 'add_link'"></span>
                            <span x-text="formModal.mode === 'edit' ? 'حفظ التعديلات' : 'إنشاء الرابط الآن'"></span>
                        </button>
                        <button type="button"
                                @click="closeFormModal()"
                                class="w-full py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold">
                            إلغاء
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- 2. نافذة تأكيد تغيير الرابط (Regenerate Token) --}}
        <div x-show="regenModal.open"
             x-cloak
             @keydown.escape.window="regenModal.open = false"
             class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-3 sm:p-4">
            <div class="fixed inset-0 bg-slate-950/60" @click="regenModal.open = false"></div>

            <div class="relative w-full max-w-sm bg-white rounded-3xl p-5 shadow-2xl border border-slate-100 z-10 space-y-4 text-right">
                <div class="text-center space-y-1.5">
                    <div class="w-12 h-12 mx-auto rounded-2xl bg-amber-50 border border-amber-200 text-amber-600 flex items-center justify-center">
                        <span class="material-symbols-outlined text-2xl">autorenew</span>
                    </div>
                    <h3 class="text-sm font-extrabold text-slate-800">تغيير رابط البوابة فوراً؟</h3>
                    <p class="text-xs font-bold text-primary" x-text="regenModal.partnerName"></p>
                </div>

                <div class="bg-amber-50 border border-amber-200 rounded-2xl p-3 text-[11px] font-bold text-amber-900 leading-relaxed">
                    تنبيه مهم: سيتم إلغاء الرابط القديم فوراً وسيتوقف عن العمل نهائياً، وسيتوجب عليك إرسال الرابط الجديد للمكتب الخارجي.
                </div>

                <form method="POST" :action="regenModal.actionUrl" class="flex flex-col gap-2 m-0">
                    @csrf
                    <button type="submit"
                            class="w-full py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-extrabold flex items-center justify-center gap-1.5">
                        <span class="material-symbols-outlined text-base">autorenew</span>
                        <span>نعم، إلغاء القديم وتوليد رابط جديد</span>
                    </button>
                    <button type="button"
                            @click="regenModal.open = false"
                            class="w-full py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold">
                        تراجع
                    </button>
                </form>
            </div>
        </div>

        {{-- 3. نافذة تأكيد الحذف الناعم --}}
        <div x-show="deleteModal.open"
             x-cloak
             @keydown.escape.window="deleteModal.open = false"
             class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-3 sm:p-4">
            <div class="fixed inset-0 bg-slate-950/60" @click="deleteModal.open = false"></div>

            <div class="relative w-full max-w-sm bg-white rounded-3xl p-5 shadow-2xl border border-slate-100 z-10 space-y-4 text-right">
                <div class="text-center space-y-1.5">
                    <div class="w-12 h-12 mx-auto rounded-2xl bg-rose-50 border border-rose-200 text-rose-600 flex items-center justify-center">
                        <span class="material-symbols-outlined text-2xl">delete_forever</span>
                    </div>
                    <h3 class="text-sm font-extrabold text-slate-800">حذف رابط المكتب الخارجي؟</h3>
                    <p class="text-xs font-bold text-rose-700" x-text="deleteModal.partnerName"></p>
                </div>

                <p class="text-[11px] text-slate-500 leading-relaxed text-center">
                    سيتوقف هذا الرابط فوراً عن استقبال أي شحنات جديدة، مع بقاء كافة الشحنات والطرود السابقة محفوظة في أرشيف مكتبك.
                </p>

                <form method="POST" :action="deleteModal.actionUrl" class="flex flex-col gap-2 m-0">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="w-full py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-extrabold flex items-center justify-center gap-1.5">
                        <span class="material-symbols-outlined text-base">delete</span>
                        <span>تأكيد حذف الرابط</span>
                    </button>
                    <button type="button"
                            @click="deleteModal.open = false"
                            class="w-full py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold">
                        تراجع
                    </button>
                </form>
            </div>
        </div>
    </main>
@endsection

@push('scripts')
    <script>
        function externalPartnerLinksPage(config) {
            const cityPrefixMap = {
                'مارب': 'MRB-', 'مأرب': 'MRB-',
                'عتق': 'ATQ-', 'شبوة': 'SHB-', 'بيحان': 'BHN-',
                'عدن': 'ADN-', 'صنعاء': 'SAN-', 'تعز': 'TAZ-',
                'المكلا': 'MKL-', 'سيئون': 'SYN-', 'حضرموت': 'HDR-',
                'الحديدة': 'HOD-', 'إب': 'IBB-', 'اب': 'IBB-',
                'ذمار': 'DHM-', 'الغيضة': 'GHD-', 'المهرة': 'MHR-',
                'سقطرى': 'SOC-', 'رداع': 'RDA-', 'البيضاء': 'BYD-',
                'الضالع': 'DHL-', 'لحج': 'LHJ-', 'أبين': 'ABY-', 'ابين': 'ABY-',
                'حجة': 'HJH-', 'صعدة': 'SAD-', 'عمران': 'AMR-', 'الجوف': 'JWF-'
            };

            return {
                desktopView: localStorage.getItem('mursal_partner_links_desktop_view') === 'cards' ? 'cards' : 'table',
                setDesktopView(mode) {
                    const next = mode === 'cards' ? 'cards' : 'table';
                    this.desktopView = next;
                    try {
                        localStorage.setItem('mursal_partner_links_desktop_view', next);
                    } catch (_) {}
                },

                formModal: {
                    open: Boolean(config.hasErrors),
                    mode: config.initialMode === 'edit' ? 'edit' : 'create',
                    actionUrl: config.initialMode === 'edit' && config.initialEditAction
                        ? config.initialEditAction
                        : config.storeUrl,
                },

                form: {
                    partner_name: config.oldInput?.partner_name || '',
                    partner_city: config.oldInput?.partner_city || '',
                    partner_phone: config.oldInput?.partner_phone || '',
                    receipt_prefix: config.oldInput?.receipt_prefix || '',
                    access_pin: config.oldInput?.access_pin || '',
                },

                regenModal: {
                    open: false,
                    partnerName: '',
                    actionUrl: '',
                },

                deleteModal: {
                    open: false,
                    partnerName: '',
                    actionUrl: '',
                },

                notify(message, type = 'success') {
                    window.dispatchEvent(new CustomEvent('office-toast', {
                        detail: { message, type }
                    }));
                },

                openCreateModal() {
                    this.formModal.mode = 'create';
                    this.formModal.actionUrl = config.storeUrl;
                    this.form = {
                        partner_name: '',
                        partner_city: '',
                        partner_phone: '',
                        receipt_prefix: '',
                        access_pin: '',
                    };
                    this.formModal.open = true;
                },

                openEditModal(link) {
                    this.formModal.mode = 'edit';
                    this.formModal.actionUrl = link.update_url;
                    this.form = {
                        partner_name: link.partner_name || '',
                        partner_city: link.partner_city || '',
                        partner_phone: link.partner_phone || '',
                        receipt_prefix: link.receipt_prefix || '',
                        access_pin: link.access_pin || '',
                    };
                    this.formModal.open = true;
                },

                closeFormModal() {
                    this.formModal.open = false;
                },

                openRegenerateModal(link) {
                    this.regenModal.partnerName = link.partner_name;
                    this.regenModal.actionUrl = link.regenerate_url;
                    this.regenModal.open = true;
                },

                openDeleteModal(link) {
                    this.deleteModal.partnerName = link.partner_name;
                    this.deleteModal.actionUrl = link.destroy_url;
                    this.deleteModal.open = true;
                },

                normalizePin(val) {
                    const ar = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
                    const fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
                    return String(val || '')
                        .replace(/[٠-٩]/g, d => ar.indexOf(d))
                        .replace(/[۰-۹]/g, d => fa.indexOf(d))
                        .replace(/\D/g, '')
                        .slice(0, 4);
                },

                generatePin() {
                    this.form.access_pin = String(Math.floor(1000 + Math.random() * 9000));
                },

                suggestPrefix() {
                    const city = String(this.form.partner_city || '').trim();
                    if (city && cityPrefixMap[city]) {
                        this.form.receipt_prefix = cityPrefixMap[city];
                        return;
                    }
                    const latin = String(this.form.partner_name || '')
                        .toUpperCase()
                        .replace(/[^A-Z0-9]/g, '')
                        .slice(0, 4);
                    if (latin.length >= 2) {
                        this.form.receipt_prefix = latin + '-';
                        return;
                    }
                    const num = Math.floor(10 + Math.random() * 90);
                    this.form.receipt_prefix = 'EXT' + num + '-';
                },

                async copyPortalLink(url) {
                    try {
                        if (navigator.clipboard && window.isSecureContext) {
                            await navigator.clipboard.writeText(url);
                        } else {
                            const ta = document.createElement('textarea');
                            ta.value = url;
                            ta.style.position = 'fixed';
                            ta.style.left = '-9999px';
                            document.body.appendChild(ta);
                            ta.select();
                            document.execCommand('copy');
                            document.body.removeChild(ta);
                        }
                        this.notify('تم نسخ رابط البوابة بنجاح.', 'success');
                    } catch (e) {
                        this.notify('تعذر النسخ التلقائي، يمكنك تحديد الرابط ونسخه يدوياً.', 'warning');
                    }
                },

                buildWhatsappShareUrl(link) {
                    let lines = [
                        'مرحباً بكم (' + link.partner_name + ')،',
                        'هذا رابط البوابة المخصص لإرسال كشوفات الطرود مباشرة إلى ' + config.officeName + ':',
                        link.portal_url
                    ];

                    if (link.access_pin) {
                        lines.push('رمز الدخول السري (PIN): ' + link.access_pin);
                    }

                    const text = encodeURIComponent(lines.join('\n'));
                    let digits = String(link.partner_phone || '').replace(/\D/g, '');
                    if (digits.length === 9 && digits.startsWith('7')) {
                        digits = '967' + digits;
                    } else if (digits.startsWith('00')) {
                        digits = digits.slice(2);
                    }

                    return digits
                        ? ('https://wa.me/' + digits + '?text=' + text)
                        : ('https://wa.me/?text=' + text);
                }
            };
        }
    </script>
@endpush
