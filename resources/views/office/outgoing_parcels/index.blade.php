@extends('office.layouts.app')

@section('title', 'الطرود الصادرة')

@section('shell-class', 'bg-slate-50')

@section('header-search')
    <form method="GET"
          action="{{ route('office.outgoing-parcels.index') }}"
          x-ref="headerSearchForm"
          x-data="{
              q: @js($search),
              _t: null,
              normDigits(str) {
                  const ar = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
                  const fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
                  return String(str || '')
                      .replace(/[٠-٩]/g, d => ar.indexOf(d))
                      .replace(/[۰-۹]/g, d => fa.indexOf(d))
                      .replace(/[\u200B-\u200D\uFEFF\u200E\u200F]/g, '');
              },
              onInput() {
                  this.q = this.normDigits(this.q);
                  if (this._t) clearTimeout(this._t);
                  this._t = setTimeout(() => this.submitNow(), 380);
              },
              submitNow() {
                  if (this._t) clearTimeout(this._t);
                  this.$refs.headerSearchForm.submit();
              },
              clear() {
                  this.q = '';
                  this.$nextTick(() => this.submitNow());
              }
          }"
          @submit.prevent="submitNow()"
          class="mt-3 relative">
        @if($statusFilter !== 'all')
            <input type="hidden" name="status" value="{{ $statusFilter }}">
        @endif
        <input type="text"
               name="q"
               x-model="q"
               @input="onInput()"
               dir="rtl"
               autocomplete="off"
               placeholder="بحث برقم الهاتف، السند، المرسل، المستلم، نوع الطرد..."
               class="w-full bg-slate-800 text-white placeholder-slate-400 text-xs rounded-xl pr-9 pl-8 py-2.5 outline-none border border-slate-700 focus:border-secondary transition-all">
        <span class="material-symbols-outlined absolute right-2.5 top-2.5 text-slate-400 text-lg pointer-events-none">search</span>

        <template x-if="q.length > 0">
            <button type="button"
                    @click="clear()"
                    title="مسح البحث"
                    class="absolute left-2.5 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full bg-slate-700 hover:bg-rose-500/20 text-slate-300 hover:text-rose-400 flex items-center justify-center text-xs font-bold transition-colors">
                ✕
            </button>
        </template>
    </form>
@endsection

@section('content')
    @php
        $officesForJs = $destinationOffices->map(fn ($o) => [
            'id'    => $o->id,
            'name'  => $o->name,
            'branch'=> $o->branch_name ?? '',
            'label' => $o->branch_name ? ($o->name . ' - ' . $o->branch_name) : $o->name,
        ])->values();
    @endphp

    <main class="flex-1 p-3.5 space-y-3.5"
          @open-outgoing-parcel-modal.window="openModal()"
          x-data="outgoingParcelsPage({
              hasErrors: {{ $errors->any() ? 'true' : 'false' }},
              initialSearch: @js($search),
              initialDestType: @js(old('destination_type', 'office')),
              initialOfficeId: @js(old('destination_office_id', '')),
              initialExternalName: @js(old('external_office_name', '')),
              initialSenderPhone: @js(old('sender_phone', '')),
              initialRecipientPhone: @js(old('recipient_phone', '')),
              initialPackageType: @js(old('package_type', '')),
              initialPaymentType: @js(old('payment_type', 'paid')),
              offices: @js($officesForJs)
          })">

        {{-- رسائل التنبيه والنجاح --}}
        @if (session('success'))
            <div class="p-3 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2 shadow-2xs">
                <span class="material-symbols-outlined text-base text-emerald-600">check_circle</span>
                <span class="flex-1">{{ session('success') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div class="p-3 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold flex items-center gap-2 shadow-2xs">
                <span class="material-symbols-outlined text-base text-rose-600">error</span>
                <span class="flex-1">{{ session('error') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="p-3 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold space-y-1 shadow-2xs">
                <div class="flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-base text-rose-600">warning</span>
                    <span>يرجى مراجعة الحقول التالية:</span>
                </div>
                <ul class="list-disc list-inside text-[11px] font-semibold pr-2 space-y-0.5">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- رأس الصفحة وزر إضافة طرد صادر --}}
        <div class="flex items-center justify-between gap-2">
            <div>
                <h2 class="text-sm font-extrabold text-slate-800 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-lg text-secondary">outbox</span>
                    <span>الطرود الصادرة</span>
                </h2>
            </div>
        </div>

        {{-- 1. بطاقات الملخص العلوية التفاعلية (تعمل كفلاتر بالضغط، والضغط مجدداً يلغي الفلتر ويعرض الكل) --}}
        <div class="space-y-1.5">
            <div class="grid grid-cols-3 gap-2">
                @php
                    $pendingUrl = route('office.outgoing-parcels.index', array_filter([
                        'status' => $statusFilter === 'pending' ? null : 'pending',
                        'q'      => $search !== '' ? $search : null,
                    ]));
                    $inTransitUrl = route('office.outgoing-parcels.index', array_filter([
                        'status' => $statusFilter === 'in_transit' ? null : 'in_transit',
                        'q'      => $search !== '' ? $search : null,
                    ]));
                    $receivedUrl = route('office.outgoing-parcels.index', array_filter([
                        'status' => $statusFilter === 'received' ? null : 'received',
                        'q'      => $search !== '' ? $search : null,
                    ]));
                @endphp

                <a href="{{ $pendingUrl }}"
                   class="p-2.5 rounded-2xl border shadow-2xs text-center block transition-all active:scale-95 {{ $statusFilter === 'pending' ? 'bg-amber-50/80 border-amber-400 ring-2 ring-amber-200/80' : 'bg-white border-slate-200/80 hover:border-amber-300' }}">
                    <p class="text-[10px] font-bold {{ $statusFilter === 'pending' ? 'text-amber-800' : 'text-slate-400' }}">في الانتظار</p>
                    <p class="text-lg font-black text-amber-600 mt-0.5">{{ $counts['pending'] }}</p>
                </a>

                <a href="{{ $inTransitUrl }}"
                   class="p-2.5 rounded-2xl border shadow-2xs text-center block transition-all active:scale-95 {{ $statusFilter === 'in_transit' ? 'bg-sky-50/80 border-sky-400 ring-2 ring-sky-200/80' : 'bg-white border-slate-200/80 hover:border-sky-300' }}">
                    <p class="text-[10px] font-bold {{ $statusFilter === 'in_transit' ? 'text-sky-800' : 'text-slate-400' }}">في الطريق</p>
                    <p class="text-lg font-black text-sky-600 mt-0.5">{{ $counts['in_transit'] }}</p>
                </a>

                <a href="{{ $receivedUrl }}"
                   class="p-2.5 rounded-2xl border shadow-2xs text-center block transition-all active:scale-95 {{ $statusFilter === 'received' ? 'bg-indigo-50/80 border-indigo-400 ring-2 ring-indigo-200/80' : 'bg-white border-slate-200/80 hover:border-indigo-300' }}">
                    <p class="text-[10px] font-bold {{ $statusFilter === 'received' ? 'text-indigo-800' : 'text-slate-400' }}">وصل الفرع</p>
                    <p class="text-lg font-black text-indigo-600 mt-0.5">{{ $counts['received'] }}</p>
                </a>
            </div>

            @if($statusFilter !== 'all')
                <div class="flex items-center justify-between px-1 pt-0.5 text-[11px]">
                    <span class="text-slate-500 font-bold">
                        التصفية النشطة:
                        <span class="text-primary font-extrabold">{{ \App\Enums\OutgoingParcelStatus::tryFrom($statusFilter)?->label() ?? $statusFilter }}</span>
                    </span>
                    <a href="{{ route('office.outgoing-parcels.index', array_filter(['q' => $search !== '' ? $search : null])) }}"
                       class="text-rose-600 hover:text-rose-700 font-extrabold inline-flex items-center gap-0.5">
                        <span class="material-symbols-outlined text-xs">close</span>
                        <span>إلغاء الفلتر / عرض الكل ({{ $counts['all'] }})</span>
                    </a>
                </div>
            @endif
        </div>

        {{-- 2. قائمة كروت الطرود الصادرة --}}
        <div class="space-y-2.5">
            @forelse ($parcels as $parcel)
                <article class="bg-white border border-slate-200/90 rounded-2xl p-3.5 shadow-xs space-y-2.5">
                    {{-- رأس البطاقة: نوع الطرد، السند، وحالة الطرد --}}
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="w-8 h-8 rounded-lg bg-slate-100 text-primary flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-lg">outbox</span>
                            </span>
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <h3 class="font-extrabold text-sm text-slate-800">{{ $parcel->package_type }}</h3>
                                    @if($parcel->receipt_number)
                                        <span class="px-1.5 py-0.5 rounded-md bg-slate-100 text-slate-700 border border-slate-200 text-[10px] font-mono font-extrabold">
                                            سند #{{ $parcel->receipt_number }}
                                        </span>
                                    @endif
                                </div>

                                {{-- شارة الوجهة وحالة الدفع والمبلغ --}}
                                <div class="flex items-center gap-1.5 flex-wrap mt-1">
                                    @if($parcel->isExternal())
                                        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-200 text-[10px] font-bold">
                                            <span class="material-symbols-outlined text-[12px]">storefront</span>
                                            <span>مكتب خارجي: {{ $parcel->destinationLabel() }}</span>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-md bg-sky-50 text-sky-800 border border-sky-200 text-[10px] font-bold">
                                            <span class="material-symbols-outlined text-[12px]">domain</span>
                                            <span>فرع: {{ $parcel->destinationLabel() }}</span>
                                        </span>
                                    @endif

                                    @php
                                        $paymentEnum = $parcel->payment_type instanceof \App\Enums\OutgoingPaymentType
                                            ? $parcel->payment_type
                                            : (\App\Enums\OutgoingPaymentType::tryFrom((string) $parcel->payment_type) ?? \App\Enums\OutgoingPaymentType::PAID);
                                    @endphp
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md border text-[10px] font-extrabold {{ $paymentEnum->badgeClass() }}">
                                        <span>{{ $paymentEnum->label() }}:</span>
                                        <span class="font-mono">{{ number_format((float) ($parcel->cod_amount ?? 0)) }} ر.ي</span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- شارة الحالة الأساسية --}}
                        <span class="px-2 py-0.5 rounded-md border text-[10px] font-extrabold shrink-0 {{ $parcel->status->badgeClass() }}">
                            {{ $parcel->status->label() }}
                        </span>
                    </div>

                    {{-- بيانات المرسل والمستلم --}}
                    <div class="grid grid-cols-2 gap-2 bg-slate-50 rounded-xl p-2.5 text-xs border border-slate-100">
                        <div class="min-w-0">
                            <p class="text-[10px] text-slate-400 font-bold">المرسل</p>
                            <p class="font-bold text-slate-800 truncate">{{ $parcel->sender_name }}</p>
                            <p class="text-[11px] text-slate-500 font-mono dir-ltr text-right">{{ $parcel->sender_phone }}</p>
                        </div>
                        <div class="min-w-0 border-r border-slate-200 pr-2">
                            <p class="text-[10px] text-slate-400 font-bold">المستلم</p>
                            <p class="font-bold text-slate-800 truncate">{{ $parcel->recipient_name ?: 'بدون اسم' }}</p>
                            <p class="text-[11px] text-slate-500 font-mono dir-ltr text-right">{{ $parcel->recipient_phone }}</p>
                        </div>
                    </div>

                    @if($parcel->notes)
                        <p class="text-[11px] text-slate-600 bg-amber-50/60 border border-amber-100 rounded-xl px-2.5 py-1.5">
                            <span class="font-extrabold text-amber-800">ملاحظة:</span> {{ $parcel->notes }}
                        </p>
                    @endif

                    {{-- أزرار الإجراءات حسب الحالة --}}
                    @if ($parcel->status === \App\Enums\OutgoingParcelStatus::PENDING)
                        <div class="flex items-center gap-2 pt-1">
                            <form method="POST"
                                  action="{{ route('office.outgoing-parcels.dispatch', $parcel) }}"
                                  class="flex-1"
                                  x-data="{ submitting: false }"
                                  @submit="if (submitting) { $event.preventDefault(); return; } if (!confirm('تأكيد شحن الطرد وتحويل حالته إلى (في الطريق)؟')) { $event.preventDefault(); return; } submitting = true">
                                @csrf
                                <button type="submit"
                                        :disabled="submitting"
                                        class="w-full py-2 px-3 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-xs font-extrabold flex items-center justify-center gap-1 transition-all active:scale-95 disabled:opacity-50">
                                    <span class="material-symbols-outlined text-sm">local_shipping</span>
                                    <span x-text="submitting ? 'جاري الشحن...' : 'شحن (في الطريق)'"></span>
                                </button>
                            </form>

                            <button type="button"
                                    @click="openCancelModal(@js([
                                        'id'              => $parcel->id,
                                        'receipt_number'  => $parcel->receipt_number,
                                        'package_type'    => $parcel->package_type,
                                        'destination'     => $parcel->destinationLabel(),
                                        'recipient_name'  => $parcel->recipient_name,
                                        'recipient_phone' => $parcel->recipient_phone,
                                        'cancel_url'      => route('office.outgoing-parcels.cancel', $parcel),
                                    ]))"
                                    class="py-2 px-3 rounded-xl border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold flex items-center justify-center gap-1 transition-all active:scale-95">
                                <span class="material-symbols-outlined text-sm">cancel</span>
                                <span>إلغاء</span>
                            </button>
                        </div>

                    @elseif ($parcel->status === \App\Enums\OutgoingParcelStatus::IN_TRANSIT)
                        <div class="flex items-center gap-2 pt-1">
                            @if ($parcel->isExternal())
                                <form method="POST"
                                      action="{{ route('office.outgoing-parcels.deliver-external', $parcel) }}"
                                      class="flex-1"
                                      x-data="{ submitting: false }"
                                      @submit="if (submitting) { $event.preventDefault(); return; } if (!confirm('تأكيد تسليم الطرد للمكتب الخارجي؟')) { $event.preventDefault(); return; } submitting = true">
                                    @csrf
                                    <button type="submit"
                                            :disabled="submitting"
                                            class="w-full py-2 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-extrabold flex items-center justify-center gap-1 transition-all active:scale-95 disabled:opacity-50">
                                        <span class="material-symbols-outlined text-sm">check_circle</span>
                                        <span x-text="submitting ? 'جاري التأكيد...' : 'تم التسليم'"></span>
                                    </button>
                                </form>
                            @else
                                <div class="flex-1 py-2 px-3 rounded-xl bg-sky-50 border border-sky-200 text-sky-800 text-xs font-bold flex items-center justify-center gap-1">
                                    <span class="material-symbols-outlined text-sm">hourglass_top</span>
                                    <span>بانتظار استلام الفرع</span>
                                </div>
                            @endif

                            <button type="button"
                                    @click="openCancelModal(@js([
                                        'id'              => $parcel->id,
                                        'receipt_number'  => $parcel->receipt_number,
                                        'package_type'    => $parcel->package_type,
                                        'destination'     => $parcel->destinationLabel(),
                                        'recipient_name'  => $parcel->recipient_name,
                                        'recipient_phone' => $parcel->recipient_phone,
                                        'cancel_url'      => route('office.outgoing-parcels.cancel', $parcel),
                                    ]))"
                                    class="py-2 px-3 rounded-xl border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold flex items-center justify-center gap-1 transition-all active:scale-95">
                                <span class="material-symbols-outlined text-sm">cancel</span>
                                <span>إلغاء</span>
                            </button>
                        </div>

                    @elseif ($parcel->status === \App\Enums\OutgoingParcelStatus::RECEIVED)
                        @php
                            $linked = $parcel->linkedIncomingParcel;
                            $linkedStatusValue = $linked?->status instanceof \App\Enums\ParcelStatus
                                ? $linked->status->value
                                : (string) ($linked?->status ?? '');
                            $liveBadge = match ($linkedStatusValue) {
                                'delivered' => ['label' => 'تم التسليم للعميل', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
                                'returned'  => ['label' => 'مُرتجع', 'class' => 'bg-rose-50 text-rose-700 border-rose-200'],
                                default     => ['label' => 'بالمكتب المستلم', 'class' => 'bg-indigo-50 text-indigo-700 border-indigo-200'],
                            };
                        @endphp
                        <div class="pt-1 flex items-center justify-between text-[11px] bg-indigo-50/50 border border-indigo-100 rounded-xl px-3 py-2">
                            <span class="font-bold text-slate-600">حالة الطرد لدى الفرع المستلم:</span>
                            <span class="px-2 py-0.5 rounded-md border font-extrabold {{ $liveBadge['class'] }}">
                                {{ $liveBadge['label'] }}
                            </span>
                        </div>
                    @endif
                </article>
            @empty
                <div class="bg-white rounded-2xl border border-slate-200/80 p-8 text-center text-slate-400">
                    <span class="material-symbols-outlined text-4xl mb-1 text-slate-300">outbox</span>
                    <p class="text-xs font-bold text-slate-500">لا توجد طرود صادرة مطابقة</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">اضغط على (+ إضافة طرد صادر) لتسجيل إرسالية جديدة</p>
                </div>
            @endforelse
        </div>

        @if ($parcels->hasPages())
            <div class="pt-2">
                {{ $parcels->links() }}
            </div>
        @endif

        {{-- 4. مودال إضافة طرد صادر جديد --}}
        <div x-show="showModal"
             x-cloak
             :style="{ height: viewportHeight + 'px', top: viewportTop + 'px' }"
             class="fixed left-0 right-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-start justify-center pt-1.5 px-1.5 sm:items-center sm:p-4">
            <div @click.outside="closeModal()"
                 :style="{ maxHeight: Math.max(260, viewportHeight - 12) + 'px' }"
                 class="bg-white w-full max-w-lg rounded-2xl flex flex-col shadow-2xl border border-slate-200 overflow-hidden">

                {{-- هيدر المودال --}}
                <div class="py-3 px-4 bg-primary text-white flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-secondary text-xl">outbox</span>
                        <h3 class="text-sm font-extrabold">إضافة طرد صادر جديد</h3>
                    </div>
                    <button type="button" @click="closeModal()" class="text-slate-300 hover:text-white p-1">
                        <span class="material-symbols-outlined text-lg">close</span>
                    </button>
                </div>

                {{-- نموذج الإضافة --}}
                <form method="POST"
                      action="{{ route('office.outgoing-parcels.store') }}"
                      class="flex flex-col flex-1 min-h-0"
                      x-data="{ submitting: false }"
                      @submit="if (submitting) { $event.preventDefault(); return; } submitting = true">
                    @csrf

                    <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain touch-pan-y px-4 pt-3 pb-8 space-y-3"
                         style="-webkit-overflow-scrolling: touch;"
                         @focusin="if ($event.target.matches('input, textarea, select')) { setTimeout(() => $event.target.scrollIntoView({ behavior: 'smooth', block: 'center' }), 180); }">

                    {{-- أ. حقل الوجهة الموحد الذكي (Searchable Combobox: فرع في النظام أو مكتب خارجي) --}}
                    <div class="relative" @click.outside="destDropdownOpen = false">
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <label class="block text-[11px] font-bold text-slate-600">
                                الوجهة المستلمة <span class="text-rose-500">*</span>
                            </label>

                            {{-- شارة توضيحية لنوع الوجهة الحالية --}}
                            <template x-if="selectedOfficeId !== ''">
                                <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-extrabold">
                                    <span class="material-symbols-outlined text-[12px]">domain</span>
                                    <span>فرع في النظام</span>
                                </span>
                            </template>
                            <template x-if="selectedOfficeId === '' && destinationQuery.trim() !== ''">
                                <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-200 text-[10px] font-extrabold">
                                    <span class="material-symbols-outlined text-[12px]">storefront</span>
                                    <span>مكتب خارجي</span>
                                </span>
                            </template>
                        </div>

                        {{-- الحقول المخفية المتوافقة مع الباك إند --}}
                        <input type="hidden" name="destination_type" :value="destType">
                        <input type="hidden" name="destination_office_id" :value="selectedOfficeId">
                        <input type="hidden" name="external_office_name" :value="externalOfficeName">

                        <div class="relative rounded-xl border bg-slate-50 focus-within:bg-white focus-within:border-secondary transition-all"
                             :class="selectedOfficeId !== '' ? 'border-emerald-300 bg-emerald-50/20' : 'border-slate-200'">
                            <input type="text"
                                   x-ref="destinationComboInput"
                                   x-model="destinationQuery"
                                   @focus="destDropdownOpen = true"
                                   @input="onDestinationInput()"
                                   @keydown.escape="destDropdownOpen = false"
                                   @keydown.enter.prevent="destDropdownOpen = false"
                                   required
                                   dir="rtl"
                                   autocomplete="off"
                                   placeholder="اختر الفرع أو اكتب اسم مكتب خارجي..."
                                   class="w-full py-2.5 pr-3 pl-14 text-xs font-bold text-slate-800 bg-transparent outline-none placeholder:text-slate-400 placeholder:font-normal" />

                            <div class="absolute left-2 top-1/2 -translate-y-1/2 flex items-center gap-1">
                                <template x-if="destinationQuery !== ''">
                                    <button type="button"
                                            @click="clearDestination(); $nextTick(() => $refs.destinationComboInput.focus())"
                                            class="w-5 h-5 rounded-full text-slate-400 hover:text-rose-500 hover:bg-rose-50 flex items-center justify-center text-xs font-bold transition-colors"
                                            title="مسح الوجهة">
                                        ✕
                                    </button>
                                </template>
                                <button type="button"
                                        @click="destDropdownOpen = !destDropdownOpen"
                                        class="text-slate-400 hover:text-slate-600 flex items-center p-0.5">
                                    <span class="material-symbols-outlined text-base" x-text="destDropdownOpen ? 'expand_less' : 'expand_more'"></span>
                                </button>
                            </div>
                        </div>

                        {{-- القائمة المنسدلة للفروع مع خيار اعتماد كمكتب خارجي --}}
                        <div x-show="destDropdownOpen"
                             x-cloak
                             x-transition
                             class="absolute left-0 right-0 top-full mt-1 z-30 max-h-48 overflow-y-auto bg-white rounded-2xl border border-slate-200 shadow-xl divide-y divide-slate-50">
                            <template x-for="off in filteredOffices" :key="off.id">
                                <button type="button"
                                        @mousedown.prevent
                                        @click="selectInternalOffice(off)"
                                        :class="String(selectedOfficeId) === String(off.id)
                                            ? 'bg-emerald-50 text-emerald-800 font-extrabold'
                                            : 'text-slate-700 hover:bg-slate-50 font-bold'"
                                        class="w-full px-3 py-2.5 text-right text-xs flex items-center justify-between gap-2 transition-colors">
                                    <span class="flex items-center gap-1.5 min-w-0">
                                        <span class="material-symbols-outlined text-sm text-sky-600 shrink-0">domain</span>
                                        <span x-text="off.label" class="truncate"></span>
                                    </span>
                                    <template x-if="String(selectedOfficeId) === String(off.id)">
                                        <span class="material-symbols-outlined text-sm text-emerald-600 shrink-0">check_circle</span>
                                    </template>
                                </button>
                            </template>

                            {{-- خيار إضافة النص المكتوب كمكتب خارجي --}}
                            <template x-if="destinationQuery.trim() !== ''">
                                <button type="button"
                                        @mousedown.prevent
                                        @click="confirmExternalOffice()"
                                        class="w-full px-3 py-2.5 text-right text-xs font-extrabold text-amber-800 bg-amber-50/70 hover:bg-amber-100/80 flex items-center gap-1.5 transition-colors">
                                    <span class="material-symbols-outlined text-sm text-amber-600 shrink-0">storefront</span>
                                    <span class="truncate" x-text="'إضافة &quot;' + destinationQuery.trim() + '&quot; كمكتب خارجي'"></span>
                                </button>
                            </template>

                            <template x-if="filteredOffices.length === 0 && destinationQuery.trim() === ''">
                                <div class="px-3 py-3 text-center text-[11px] text-slate-400 font-bold">
                                    لا توجد فروع أخرى مسجلة، يمكنك كتابة اسم مكتب خارجي مباشرة.
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- ب. بيانات المرسل --}}
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">اسم المرسل <span class="text-rose-500">*</span></label>
                            <input type="text"
                                   name="sender_name"
                                   value="{{ old('sender_name') }}"
                                   required
                                   placeholder="اسم المرسل"
                                   class="w-full py-2 px-3 text-xs font-bold text-slate-800 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white outline-none focus:border-secondary">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">هاتف المرسل <span class="text-rose-500">*</span></label>
                            <div class="relative rounded-xl border bg-slate-50 focus-within:bg-white focus-within:border-secondary focus-within:ring-2 focus-within:ring-secondary/20 transition-all"
                                 :class="senderPhone.length > 0 && !isValidPhone(senderPhone) ? 'border-rose-300' : (isValidPhone(senderPhone) ? 'border-emerald-300' : 'border-slate-200')">
                                <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1 select-none pointer-events-none">
                                    <span class="text-[11px]">🇾🇪</span>
                                    <template x-if="isValidPhone(senderPhone)">
                                        <span class="material-symbols-outlined text-[15px] text-emerald-500 flex items-center">check_circle</span>
                                    </template>
                                    <template x-if="senderPhone.length > 0 && !isValidPhone(senderPhone)">
                                        <span class="material-symbols-outlined text-[15px] text-rose-400 flex items-center">error</span>
                                    </template>
                                </div>
                                <input type="tel"
                                       name="sender_phone"
                                       x-model="senderPhone"
                                       @input="senderPhone = sanitizePhone(senderPhone)"
                                       required
                                       dir="ltr"
                                       inputmode="numeric"
                                       maxlength="9"
                                       pattern="7[01378][0-9]{7}"
                                       title="يجب أن يتكون الرقم من 9 أرقام ويبدأ بـ 77، 78، 73، 71، أو 70"
                                       autocomplete="off"
                                       placeholder="7XXXXXXXX"
                                       class="w-full py-2 px-2.5 pr-12 text-xs font-mono font-bold text-slate-800 bg-transparent outline-none placeholder:text-slate-300 placeholder:font-normal">
                            </div>
                            <template x-if="senderPhone.length > 0 && !isValidPhone(senderPhone)">
                                <p class="text-[10px] font-bold text-rose-500 mt-0.5">9 أرقام تبدأ بـ 70/71/73/77/78</p>
                            </template>
                            @error('sender_phone')
                                <p class="text-[10px] font-bold text-rose-600 mt-0.5">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- ج. بيانات المستلم --}}
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">اسم المستلم <span class="text-[10px] font-normal text-slate-400">(اختياري)</span></label>
                            <input type="text"
                                   name="recipient_name"
                                   value="{{ old('recipient_name') }}"
                                   placeholder="اسم المستلم"
                                   class="w-full py-2 px-3 text-xs font-bold text-slate-800 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white outline-none focus:border-secondary">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">هاتف المستلم <span class="text-rose-500">*</span></label>
                            <div class="relative rounded-xl border bg-slate-50 focus-within:bg-white focus-within:border-secondary focus-within:ring-2 focus-within:ring-secondary/20 transition-all"
                                 :class="recipientPhone.length > 0 && !isValidPhone(recipientPhone) ? 'border-rose-300' : (isValidPhone(recipientPhone) ? 'border-emerald-300' : 'border-slate-200')">
                                <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1 select-none pointer-events-none">
                                    <span class="text-[11px]">🇾🇪</span>
                                    <template x-if="isValidPhone(recipientPhone)">
                                        <span class="material-symbols-outlined text-[15px] text-emerald-500 flex items-center">check_circle</span>
                                    </template>
                                    <template x-if="recipientPhone.length > 0 && !isValidPhone(recipientPhone)">
                                        <span class="material-symbols-outlined text-[15px] text-rose-400 flex items-center">error</span>
                                    </template>
                                </div>
                                <input type="tel"
                                       name="recipient_phone"
                                       x-model="recipientPhone"
                                       @input="recipientPhone = sanitizePhone(recipientPhone)"
                                       required
                                       dir="ltr"
                                       inputmode="numeric"
                                       maxlength="9"
                                       pattern="7[01378][0-9]{7}"
                                       title="يجب أن يتكون الرقم من 9 أرقام ويبدأ بـ 77، 78، 73، 71، أو 70"
                                       autocomplete="off"
                                       placeholder="7XXXXXXXX"
                                       class="w-full py-2 px-2.5 pr-12 text-xs font-mono font-bold text-slate-800 bg-transparent outline-none placeholder:text-slate-300 placeholder:font-normal">
                            </div>
                            <template x-if="recipientPhone.length > 0 && !isValidPhone(recipientPhone)">
                                <p class="text-[10px] font-bold text-rose-500 mt-0.5">9 أرقام تبدأ بـ 70/71/73/77/78</p>
                            </template>
                            @error('recipient_phone')
                                <p class="text-[10px] font-bold text-rose-600 mt-0.5">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- د. نوع الطرد + أزرار الاختصار التفاعلية المتدرجة (النوع ← اللون ← المقاس) --}}
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">نوع الطرد <span class="text-rose-500">*</span></label>
                        <input type="text"
                               name="package_type"
                               x-ref="outgoingPackageInput"
                               x-model="packageType"
                               required
                               dir="rtl"
                               autocomplete="off"
                               placeholder="طرد"
                               class="w-full py-2 px-3 text-xs font-bold text-slate-800 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white outline-none focus:border-secondary">

                        {{-- أزرار الاختصار السريع لنوع الطرد (نفس آلية الإرسال السريع: النوع ← اللون ← المقاس) --}}
                        <div class="mt-1.5 flex items-center gap-1.5 overflow-x-auto pb-0.5 scrollbar-none">
                            {{-- المستوى 1: الفئات الرئيسية (كيس، ضرف، كرتون) --}}
                            <template x-if="!activeCategory">
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <template x-for="cat in Object.keys(catalog)" :key="cat">
                                        <button type="button"
                                                @mousedown.prevent
                                                @click="
                                                    packageType = cat + ' ';
                                                    document.activeElement?.blur();
                                                "
                                                class="py-1 px-2.5 rounded-xl bg-slate-100 hover:bg-secondary/10 hover:text-secondary text-slate-700 text-[11px] font-bold shrink-0 transition-colors active:scale-95 flex items-center gap-1">
                                            <span x-text="cat"></span>
                                            <span class="text-[9px] text-slate-400">◀</span>
                                        </button>
                                    </template>
                                </div>
                            </template>

                            {{-- المستوى 2: اختيار اللون للفئة المختارة --}}
                            <template x-if="activeCategory && !activeColor">
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <button type="button"
                                            @mousedown.prevent
                                            @click="
                                                packageType = '';
                                                document.activeElement?.blur();
                                            "
                                            class="py-1 px-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 text-[11px] font-bold shrink-0 transition-colors active:scale-95"
                                            title="تغيير النوع">
                                        <span x-text="activeCategory + ' ✕'"></span>
                                    </button>

                                    <template x-for="color in catalog[activeCategory]" :key="color">
                                        <button type="button"
                                                @mousedown.prevent
                                                @click="
                                                    packageType = activeCategory + ' ' + color;
                                                    document.activeElement?.blur();
                                                "
                                                class="py-1 px-2.5 rounded-xl bg-slate-100 hover:bg-secondary/10 hover:text-secondary text-slate-700 text-[11px] font-bold shrink-0 transition-colors active:scale-95 flex items-center gap-1">
                                            <span x-text="color"></span>
                                            <span class="text-[9px] text-slate-400">◀</span>
                                        </button>
                                    </template>
                                </div>
                            </template>

                            {{-- المستوى 3: اختيار الحجم (كبير، وسط، صغير) بعد اختيار اللون --}}
                            <template x-if="activeCategory && activeColor">
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <button type="button"
                                            @mousedown.prevent
                                            @click="
                                                packageType = '';
                                                document.activeElement?.blur();
                                            "
                                            class="py-1 px-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 text-[11px] font-bold shrink-0 transition-colors active:scale-95"
                                            title="تغيير الفئة">
                                        <span x-text="activeCategory + ' ✕'"></span>
                                    </button>

                                    <button type="button"
                                            @mousedown.prevent
                                            @click="
                                                packageType = activeCategory + ' ';
                                                document.activeElement?.blur();
                                            "
                                            class="py-1 px-2 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-700 text-[11px] font-bold shrink-0 transition-colors active:scale-95"
                                            title="تغيير اللون">
                                        <span x-text="activeColor + ' ✕'"></span>
                                    </button>

                                    <template x-for="size in sizes" :key="size">
                                        <button type="button"
                                                @mousedown.prevent
                                                @click="
                                                    packageType = activeCategory + ' ' + activeColor + ' ' + size;
                                                    document.activeElement?.blur();
                                                "
                                                :class="activeSize === size
                                                    ? 'bg-secondary text-white shadow-sm'
                                                    : 'bg-slate-100 hover:bg-secondary/10 hover:text-secondary text-slate-700'"
                                                class="py-1 px-2.5 rounded-xl text-[11px] font-bold shrink-0 transition-colors active:scale-95">
                                            <span x-text="size"></span>
                                        </button>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- هـ. المبلغ وحالة الدفع (محاسب / آجل) --}}
                    <div class="grid grid-cols-12 gap-2 items-end">
                        <div class="col-span-6">
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">المبلغ (ر.ي) <span class="text-rose-500">*</span></label>
                            <input type="number"
                                   step="any"
                                   min="0"
                                   name="cod_amount"
                                   value="{{ old('cod_amount') }}"
                                   required
                                   dir="ltr"
                                   placeholder="مثال: 5000"
                                   class="w-full py-2 px-3 text-xs font-mono font-bold text-slate-800 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white outline-none focus:border-secondary">
                        </div>

                        <div class="col-span-6">
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">حالة الدفع <span class="text-rose-500">*</span></label>
                            <input type="hidden" name="payment_type" :value="paymentType">
                            <div class="grid grid-cols-2 gap-1 bg-slate-100 p-1 rounded-xl">
                                <button type="button"
                                        @click="paymentType = 'paid'"
                                        :class="paymentType === 'paid'
                                            ? 'bg-emerald-600 text-white shadow-xs font-extrabold'
                                            : 'text-slate-600 hover:text-slate-800 font-bold'"
                                        class="py-1.5 rounded-lg text-xs flex items-center justify-center gap-1 transition-all">
                                    <span>محاسب</span>
                                </button>
                                <button type="button"
                                        @click="paymentType = 'deferred'"
                                        :class="paymentType === 'deferred'
                                            ? 'bg-amber-500 text-white shadow-xs font-extrabold'
                                            : 'text-slate-600 hover:text-slate-800 font-bold'"
                                        class="py-1.5 rounded-lg text-xs flex items-center justify-center gap-1 transition-all">
                                    <span>آجل</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- و. ملاحظات --}}
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">ملاحظات <span class="text-[10px] font-normal text-slate-400">(اختياري)</span></label>
                        <textarea name="notes"
                                  rows="2"
                                  placeholder="أي تفاصيل إضافية..."
                                  class="w-full py-2 px-3 text-xs font-bold text-slate-800 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white outline-none focus:border-secondary">{{ old('notes') }}</textarea>
                    </div>
                    </div>

                    {{-- أزرار الحفظ والإلغاء (فوتر ثابت أسفل المودال وفوق الكيبورد) --}}
                    <div class="shrink-0 border-t border-slate-100 px-4 py-2.5 bg-white flex items-center gap-2">
                        <button type="submit"
                                :disabled="submitting"
                                class="flex-1 py-2.5 px-4 rounded-xl bg-secondary hover:bg-amber-500 text-white text-xs font-extrabold shadow-md shadow-secondary/20 flex items-center justify-center gap-1.5 transition-all disabled:opacity-50">
                            <span class="material-symbols-outlined text-base">check_circle</span>
                            <span x-text="submitting ? 'جاري الحفظ...' : 'حفظ الطرد الصادر'"></span>
                        </button>
                        <button type="button"
                                @click="closeModal()"
                                class="py-2.5 px-4 rounded-xl border border-slate-200 text-slate-600 text-xs font-bold hover:bg-slate-50">
                            إلغاء
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- 5. مودال تأكيد إلغاء الطرد الصادر (مطابق لتصميم مودال إرجاع الطرد للمكتب المرسل) --}}
        <div x-show="cancelModal.open"
             x-cloak
             class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-3 sm:p-4"
             @keydown.escape.window="closeCancelModal()">

            {{-- خلفية التعتيم الشفافة مع تمويه Glassmorphism --}}
            <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm transition-opacity"
                 x-show="cancelModal.open"
                 x-transition:enter="ease-out duration-250"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="closeCancelModal()">
            </div>

            {{-- بطاقة المودال الرئيسية --}}
            <div class="relative w-full max-w-sm bg-white rounded-3xl p-5 shadow-2xl border border-slate-100 z-10 transition-all transform flex flex-col gap-4 text-right"
                 x-show="cancelModal.open"
                 x-transition:enter="ease-out duration-250"
                 x-transition:enter-start="opacity-0 translate-y-6 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-6 sm:translate-y-0 sm:scale-95">

                {{-- زر إغلاق علوي --}}
                <button type="button"
                        @click="closeCancelModal()"
                        :disabled="cancelModal.submitting"
                        title="إغلاق"
                        class="absolute left-4 top-4 w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-400 hover:text-slate-600 flex items-center justify-center transition-colors disabled:opacity-50">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>

                {{-- الهيدر والأيقونة --}}
                <div class="text-center pt-1">
                    <div class="w-14 h-14 mx-auto rounded-2xl bg-rose-50 border border-rose-100 text-rose-600 flex items-center justify-center shadow-inner mb-3">
                        <span class="material-symbols-outlined text-3xl">cancel</span>
                    </div>
                    <h3 class="text-base font-extrabold text-slate-800">
                        إلغاء الطرد الصادر
                    </h3>
                    <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                        هل أنت متأكد من رغبتك في إلغاء هذا الطرد الصادر؟
                    </p>
                </div>

                {{-- ملخص بيانات الطرد المراد إلغاؤه --}}
                <template x-if="cancelModal.parcel">
                    <div class="bg-slate-50 rounded-2xl p-3.5 border border-slate-200/80 space-y-2.5 text-xs">

                        {{-- الوجهة المستلمة --}}
                        <div class="flex items-center justify-between pb-2 border-b border-slate-200/60">
                            <span class="text-slate-400 flex items-center gap-1 font-medium">
                                <span class="material-symbols-outlined text-sm text-slate-400">domain</span>
                                الوجهة:
                            </span>
                            <span class="font-bold text-rose-600 truncate max-w-[180px]"
                                  x-text="cancelModal.parcel.destination || 'غير محدد'">
                            </span>
                        </div>

                        {{-- رقم السند ونوع الشحنة --}}
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">نوع الشحنة:</span>
                            <span class="font-bold text-slate-700" x-text="cancelModal.parcel.package_type"></span>
                        </div>

                        <div class="flex items-center justify-between" x-show="cancelModal.parcel.receipt_number">
                            <span class="text-slate-400">رقم السند:</span>
                            <span class="font-mono font-bold text-slate-700" x-text="'#' + cancelModal.parcel.receipt_number"></span>
                        </div>

                        {{-- بيانات المستلم --}}
                        <div class="flex items-center justify-between pt-1">
                            <span class="text-slate-400">المستلم:</span>
                            <div class="text-left">
                                <span class="font-bold text-slate-700 block" x-text="cancelModal.parcel.recipient_name || 'بدون اسم'"></span>
                                <span class="text-[11px] text-slate-400 font-mono dir-ltr block" x-text="cancelModal.parcel.recipient_phone"></span>
                            </div>
                        </div>

                    </div>
                </template>

                {{-- تنبيه تحذيري --}}
                <div class="bg-amber-50 border border-amber-200/80 rounded-xl p-2.5 flex items-start gap-2 text-[11px] text-amber-800 leading-snug">
                    <span class="material-symbols-outlined text-base text-amber-600 shrink-0 mt-0.5">warning</span>
                    <span>
                        <strong>تنبيه:</strong> سيتم إلغاء الطرد الصادر رسمياً ولن يظهر في قائمة الطرود القادمة لدى الفرع المستلم.
                    </span>
                </div>

                {{-- أزرار الإجراءات --}}
                <form method="POST"
                      :action="cancelModal.url"
                      class="flex flex-col gap-2 pt-1"
                      @submit="if (cancelModal.submitting) { $event.preventDefault(); return; } cancelModal.submitting = true">
                    @csrf
                    <button type="submit"
                            :disabled="cancelModal.submitting"
                            class="w-full py-3 px-4 bg-rose-600 hover:bg-rose-700 active:scale-[0.98] text-white font-extrabold text-xs rounded-xl shadow-lg shadow-rose-600/25 flex items-center justify-center gap-2 transition-all disabled:opacity-50">
                        <span class="material-symbols-outlined text-base">cancel</span>
                        <span x-text="cancelModal.submitting ? 'جاري الإلغاء...' : 'تأكيد إلغاء الطرد الصادر'"></span>
                    </button>

                    <button type="button"
                            @click="closeCancelModal()"
                            :disabled="cancelModal.submitting"
                            class="w-full py-2.5 px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl flex items-center justify-center transition-colors disabled:opacity-50">
                        <span>تراجع</span>
                    </button>
                </form>

            </div>
        </div>

    </main>
@endsection

@push('scripts')
<script>
    function outgoingParcelsPage(config) {
        const officesList = config.offices || [];
        const initOfficeId = String(config.initialOfficeId || '');
        const initExternal = String(config.initialExternalName || '').trim();
        const matchedOffice = initOfficeId
            ? officesList.find(o => String(o.id) === initOfficeId)
            : null;

        return {
            showModal: Boolean(config.hasErrors),
            searchQuery: String(config.initialSearch || ''),
            _searchTimer: null,
            cancelModal: {
                open: false,
                submitting: false,
                url: '#',
                parcel: null,
            },
            viewportHeight: window.visualViewport ? Math.round(window.visualViewport.height) : window.innerHeight,
            viewportTop: window.visualViewport ? Math.round(window.visualViewport.offsetTop) : 0,
            destDropdownOpen: false,
            destType: matchedOffice ? 'office' : (initExternal !== '' ? 'external' : (config.initialDestType || 'office')),
            selectedOfficeId: matchedOffice ? String(matchedOffice.id) : '',
            externalOfficeName: matchedOffice ? '' : initExternal,
            destinationQuery: matchedOffice ? matchedOffice.label : initExternal,
            senderPhone: String(config.initialSenderPhone || ''),
            recipientPhone: String(config.initialRecipientPhone || ''),
            packageType: config.initialPackageType || '',
            paymentType: config.initialPaymentType || 'paid',
            offices: officesList,

            init() {
                const updateViewport = () => {
                    if (window.visualViewport) {
                        this.viewportHeight = Math.round(window.visualViewport.height);
                        this.viewportTop = Math.round(window.visualViewport.offsetTop);
                    } else {
                        this.viewportHeight = window.innerHeight;
                        this.viewportTop = 0;
                    }
                };
                updateViewport();
                if (window.visualViewport) {
                    window.visualViewport.addEventListener('resize', updateViewport);
                    window.visualViewport.addEventListener('scroll', updateViewport);
                }
                window.addEventListener('resize', updateViewport);
            },

            catalog: {
                'كيس': ['أبيض', 'أسود', 'أزرق', 'أحمر', 'بني', 'بنفسجي', 'أصفر', 'وردي', 'رمادي', 'شفاف'],
                'ضرف': ['أبيض', 'كاكي'],
                'كرتون': ['كاكي', 'أبيض']
            },
            sizes: ['كبير', 'وسط', 'صغير'],

            norm(str) {
                return String(str || '').toLowerCase().replace(/[أإآ]/g, 'ا').replace(/ة/g, 'ه').replace(/ى/g, 'ي').trim();
            },

            normalizeDigitsText(str) {
                const arabicDigits = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
                const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
                return String(str || '')
                    .replace(/[٠-٩]/g, d => arabicDigits.indexOf(d))
                    .replace(/[۰-۹]/g, d => persianDigits.indexOf(d))
                    .replace(/[\u200B-\u200D\uFEFF\u200E\u200F]/g, '');
            },

            onSearchInput() {
                this.searchQuery = this.normalizeDigitsText(this.searchQuery);
                if (this._searchTimer) {
                    clearTimeout(this._searchTimer);
                }
                this._searchTimer = setTimeout(() => {
                    this.submitSearchNow();
                }, 380);
            },

            submitSearchNow() {
                if (this._searchTimer) {
                    clearTimeout(this._searchTimer);
                }
                this.$refs.searchForm.submit();
            },

            clearSearch() {
                this.searchQuery = '';
                this.$nextTick(() => {
                    this.submitSearchNow();
                });
            },

            sanitizePhone(str) {
                const arabicDigits = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
                const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
                let digits = String(str || '')
                    .replace(/[٠-٩]/g, d => arabicDigits.indexOf(d))
                    .replace(/[۰-۹]/g, d => persianDigits.indexOf(d))
                    .replace(/[\u200B-\u200D\uFEFF\u200E\u200F]/g, '')
                    .replace(/\D/g, '');

                if (digits.length === 12 && digits.startsWith('9677')) {
                    digits = digits.substring(3);
                } else if (digits.length === 14 && digits.startsWith('009677')) {
                    digits = digits.substring(5);
                }

                return digits.substring(0, 9);
            },

            isValidPhone(phone) {
                const clean = this.sanitizePhone(phone);
                return clean.length === 9 && /^7[01378]\d{7}$/.test(clean);
            },

            onDestinationInput() {
                this.destDropdownOpen = true;
                this.selectedOfficeId = '';
                this.destType = 'external';
                this.externalOfficeName = (this.destinationQuery || '').trim();
            },

            selectInternalOffice(off) {
                this.selectedOfficeId = String(off.id);
                this.destType = 'office';
                this.destinationQuery = off.label;
                this.externalOfficeName = '';
                this.destDropdownOpen = false;
            },

            confirmExternalOffice() {
                this.selectedOfficeId = '';
                this.destType = 'external';
                this.externalOfficeName = (this.destinationQuery || '').trim();
                this.destDropdownOpen = false;
            },

            clearDestination() {
                this.selectedOfficeId = '';
                this.destType = 'office';
                this.destinationQuery = '';
                this.externalOfficeName = '';
                this.destDropdownOpen = true;
            },

            get activeCategory() {
                const desc = (this.packageType || '').trim();
                if (desc === 'ظرف' || desc.startsWith('ظرف ')) return 'ضرف';
                return Object.keys(this.catalog).find(cat => desc === cat || desc.startsWith(cat + ' ')) || null;
            },

            get activeColor() {
                if (!this.activeCategory) return null;
                const parts = (this.packageType || '').trim().split(/\s+/);
                if (parts.length < 2) return null;
                const colors = this.catalog[this.activeCategory] || [];
                return colors.find(c => this.norm(c) === this.norm(parts[1])) || null;
            },

            get activeSize() {
                if (!this.activeColor) return null;
                const parts = (this.packageType || '').trim().split(/\s+/);
                if (parts.length < 3) return null;
                return this.sizes.find(s => s === parts[2]) || null;
            },

            openModal() {
                this.senderPhone = '';
                this.recipientPhone = '';
                this.packageType = '';
                this.paymentType = 'paid';
                this.destDropdownOpen = false;
                this.selectedOfficeId = '';
                this.destType = 'office';
                this.destinationQuery = '';
                this.externalOfficeName = '';
                this.showModal = true;
            },

            closeModal() {
                this.showModal = false;
                this.destDropdownOpen = false;
                this.senderPhone = '';
                this.recipientPhone = '';
                this.packageType = '';
                this.paymentType = 'paid';
                this.selectedOfficeId = '';
                this.destType = 'office';
                this.destinationQuery = '';
                this.externalOfficeName = '';
            },

            openCancelModal(parcelData) {
                this.cancelModal.parcel = parcelData;
                this.cancelModal.url = parcelData?.cancel_url || '#';
                this.cancelModal.submitting = false;
                this.cancelModal.open = true;
            },

            closeCancelModal() {
                if (this.cancelModal.submitting) return;
                this.cancelModal.open = false;
                this.cancelModal.parcel = null;
                this.cancelModal.url = '#';
            },

            get filteredOffices() {
                if (this.selectedOfficeId !== '') {
                    return this.offices;
                }
                const q = this.norm(this.destinationQuery);
                if (!q) return this.offices;
                return this.offices.filter(o => this.norm(o.label).includes(q));
            }
        };
    }
</script>
@endpush
