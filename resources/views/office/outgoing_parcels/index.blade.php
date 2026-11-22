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
                  this._t = setTimeout(() => this.emitSearch(), 320);
              },
              emitSearch() {
                  if (this._t) clearTimeout(this._t);
                  window.dispatchEvent(new CustomEvent('outgoing-search-change', { detail: { q: this.q } }));
              },
              clear() {
                  this.q = '';
                  this.emitSearch();
              }
          }"
          @submit.prevent="emitSearch()"
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

    <div class="flex-1 flex flex-col"
         @open-outgoing-parcel-modal.window="openModal()"
         @outgoing-search-change.window="handleSearchEvent($event.detail.q)"
         x-data="outgoingParcelsPage({
             baseUrl: @js(route('office.outgoing-parcels.index')),
             hasErrors: {{ $errors->any() ? 'true' : 'false' }},
             initialStatus: @js($statusFilter),
             initialSearch: @js($search),
             initialCounts: @js($counts),
             initialDestType: @js(old('destination_type', 'office')),
             initialOfficeId: @js(old('destination_office_id', '')),
             initialExternalName: @js(old('external_office_name', '')),
             initialSenderPhone: @js(old('sender_phone', '')),
             initialRecipientPhone: @js(old('recipient_phone', '')),
             initialPackageType: @js(old('package_type', '')),
             initialPaymentType: @js(old('payment_type', 'paid')),
             offices: @js($officesForJs)
         })">

        {{-- شريط تبويبات التصفية العلوي الموحّد (مطابق تماماً لشاشة الطرود الواردة) --}}
        @php
            $outgoingTabs = [
                ['key' => 'all',        'label' => 'الكل'],
                ['key' => 'pending',    'label' => 'في الانتظار'],
                ['key' => 'in_transit', 'label' => 'في الطريق'],
                ['key' => 'received',   'label' => 'وصل الفرع'],
                ['key' => 'cancelled',  'label' => 'ملغي'],
            ];
        @endphp
        <nav class="flex overflow-x-auto no-scrollbar border-b border-slate-100 bg-white sticky top-[125px] z-20 text-xs font-bold text-center">
            @foreach ($outgoingTabs as $tabItem)
                <button type="button"
                        @click="setStatusFilter('{{ $tabItem['key'] }}')"
                        :class="activeStatus === '{{ $tabItem['key'] }}' ? 'border-b-2 border-secondary text-secondary' : 'text-slate-500'"
                        class="flex-1 shrink-0 whitespace-nowrap px-3 py-3 transition-colors">
                    <span>{{ $tabItem['label'] }}</span>
                    (<span x-text="counts.{{ $tabItem['key'] }}">{{ $counts[$tabItem['key']] ?? 0 }}</span>)
                </button>
            @endforeach
        </nav>

        <main class="flex-1 p-3 space-y-2.5">

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

            {{-- قائمة كروت الطرود الصادرة (تُحدث لحظياً عبر AJAX مع Event Delegation لضمان بقاء التفاعل بعد الاستبدال) --}}
            <div x-ref="cardsContainer"
                 @click="handleCardContainerClick($event)"
                 :class="isFetching ? 'opacity-60 transition-opacity' : ''">
                @include('office.outgoing_parcels.partials.cards-list', ['parcels' => $parcels])
            </div>

        {{-- 4. مودال إضافة طرد صادر جديد (خلفية صلبة سريعة بدون backdrop-blur لمنع التقطيع على أجهزة أندرويد الاقتصادية) --}}
        <div x-show="showModal"
             x-cloak
             :style="{ height: viewportHeight + 'px', top: viewportTop + 'px' }"
             class="fixed left-0 right-0 z-50 bg-slate-950/60 flex items-start justify-center pt-1.5 px-1.5 sm:items-center sm:p-4">
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
                      @submit="if (typeof window.requireOnlineAction === 'function' && !window.requireOnlineAction()) { $event.preventDefault(); return; } if (submitting) { $event.preventDefault(); return; } submitting = true">
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

                            {{-- أزرار الاختصار السريع لنوع الطرد --}}
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

        {{-- 5. مودال التأكيد الموحّد لإجراءات الطرد الصادر (إخراج من الشحنة / تسليم خارجي / إلغاء) --}}
        <div x-show="actionModal.open"
             x-cloak
             class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-3 sm:p-4"
             @keydown.escape.window="closeActionModal()">

            {{-- خلفية التعتيم الشفافة السريعة --}}
            <div class="fixed inset-0 bg-slate-950/60 transition-opacity"
                 x-show="actionModal.open"
                 x-transition:enter="ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="closeActionModal()">
            </div>

            {{-- بطاقة المودال الرئيسية --}}
            <div class="relative w-full max-w-sm bg-white rounded-3xl p-5 shadow-2xl border border-slate-100 z-10 transition-all transform flex flex-col gap-4 text-right"
                 x-show="actionModal.open"
                 x-transition:enter="ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-6 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-6 sm:translate-y-0 sm:scale-95">

                {{-- زر إغلاق علوي --}}
                <button type="button"
                        @click="closeActionModal()"
                        :disabled="actionModal.submitting"
                        title="إغلاق"
                        class="absolute left-4 top-4 w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-400 hover:text-slate-600 flex items-center justify-center transition-colors disabled:opacity-50">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>

                {{-- الهيدر والأيقونة --}}
                <div class="text-center pt-1">
                    <div class="w-14 h-14 mx-auto rounded-2xl border flex items-center justify-center shadow-inner mb-3"
                         :class="actionModalMeta.iconBoxClass">
                        <span class="material-symbols-outlined text-3xl" x-text="actionModalMeta.icon"></span>
                    </div>
                    <h3 class="text-base font-extrabold text-slate-800" x-text="actionModalMeta.title"></h3>
                    <p class="text-xs text-slate-500 mt-1 leading-relaxed" x-text="actionModalMeta.subtitle"></p>
                </div>

                {{-- ملخص بيانات الطرد --}}
                <template x-if="actionModal.parcel">
                    <div class="bg-slate-50 rounded-2xl p-3.5 border border-slate-200/80 space-y-2.5 text-xs">

                        {{-- الوجهة المستلمة --}}
                        <div class="flex items-center justify-between pb-2 border-b border-slate-200/60">
                            <span class="text-slate-400 flex items-center gap-1 font-medium">
                                <span class="material-symbols-outlined text-sm text-slate-400"
                                      x-text="actionModal.parcel.is_external ? 'storefront' : 'domain'"></span>
                                الوجهة:
                            </span>
                            <span class="font-bold truncate max-w-[180px]"
                                  :class="actionModalMeta.destTextClass"
                                  x-text="actionModal.parcel.destination || 'غير محدد'">
                            </span>
                        </div>

                        {{-- نوع الشحنة --}}
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">نوع الشحنة:</span>
                            <span class="font-bold text-slate-700" x-text="actionModal.parcel.package_type"></span>
                        </div>

                        {{-- رقم السند --}}
                        <div class="flex items-center justify-between" x-show="actionModal.parcel.receipt_number">
                            <span class="text-slate-400">رقم السند:</span>
                            <span class="font-mono font-bold text-slate-700" x-text="'#' + actionModal.parcel.receipt_number"></span>
                        </div>

                        {{-- المبلغ وحالة الدفع --}}
                        <div class="flex items-center justify-between" x-show="actionModal.parcel.payment_label">
                            <span class="text-slate-400">الحساب:</span>
                            <span class="font-bold text-slate-700">
                                <span x-text="actionModal.parcel.payment_label"></span>:
                                <span class="font-mono" x-text="(actionModal.parcel.cod_amount || '0') + ' ر.ي'"></span>
                            </span>
                        </div>

                        {{-- بيانات المستلم --}}
                        <div class="flex items-center justify-between pt-1">
                            <span class="text-slate-400">المستلم:</span>
                            <div class="text-left">
                                <span class="font-bold text-slate-700 block" x-text="actionModal.parcel.recipient_name || 'بدون اسم'"></span>
                                <span class="text-[11px] text-slate-400 font-mono dir-ltr block" x-text="actionModal.parcel.recipient_phone"></span>
                            </div>
                        </div>

                    </div>
                </template>

                {{-- صندوق الملاحظة / التنبيه --}}
                <div class="border rounded-xl p-2.5 flex items-start gap-2 text-[11px] leading-snug"
                     :class="actionModalMeta.noticeBoxClass">
                    <span class="material-symbols-outlined text-base shrink-0 mt-0.5"
                          :class="actionModalMeta.noticeIconClass"
                          x-text="actionModalMeta.noticeIcon"></span>
                    <span>
                        <strong x-text="actionModalMeta.noticeLabel"></strong>
                        <span x-text="actionModalMeta.noticeText"></span>
                    </span>
                </div>

                {{-- أزرار الإجراءات (تُرسل عبر AJAX بدون إعادة تحميل الصفحة) --}}
                <form method="POST"
                      :action="actionModal.url"
                      class="flex flex-col gap-2 pt-1"
                      @submit.prevent="submitActionModal()">
                    @csrf
                    <button type="submit"
                            :disabled="actionModal.submitting"
                            :class="actionModalMeta.confirmBtnClass"
                            class="w-full py-3 px-4 active:scale-[0.98] text-white font-extrabold text-xs rounded-xl shadow-lg flex items-center justify-center gap-2 transition-all disabled:opacity-50">
                        <span class="material-symbols-outlined text-base" x-text="actionModalMeta.icon"></span>
                        <span x-text="actionModal.submitting ? actionModalMeta.submittingLabel : actionModalMeta.confirmLabel"></span>
                    </button>

                    <button type="button"
                            @click="closeActionModal()"
                            :disabled="actionModal.submitting"
                            class="w-full py-2.5 px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl flex items-center justify-center transition-colors disabled:opacity-50">
                        <span>تراجع</span>
                    </button>
                </form>

            </div>
        </div>

        </main>
    </div>
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
            baseUrl: config.baseUrl || '/office/outgoing-parcels',
            showModal: Boolean(config.hasErrors),
            activeStatus: String(config.initialStatus || 'all'),
            searchQuery: String(config.initialSearch || ''),
            counts: config.initialCounts || { all: 0, pending: 0, in_transit: 0, received: 0, delivered: 0, cancelled: 0 },
            isFetching: false,
            _abortController: null,
            actionLoading: {},

            actionModal: {
                open: false,
                type: 'cancel', // 'deliver' | 'cancel'
                submitting: false,
                url: '#',
                parcel: null,
            },

            viewportHeight: window.visualViewport ? Math.round(window.visualViewport.height) : window.innerHeight,
            viewportTop: window.visualViewport ? Math.round(window.visualViewport.offsetTop) : 0,
            _vpRafId: null,
            _vpHandler: null,

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

            catalog: {
                'كيس': ['أبيض', 'أسود', 'أزرق', 'أحمر', 'بني', 'بنفسجي', 'أصفر', 'وردي', 'رمادي', 'شفاف'],
                'ضرف': ['أبيض', 'كاكي'],
                'كرتون': ['كاكي', 'أبيض']
            },
            sizes: ['كبير', 'وسط', 'صغير'],

            init() {
                this._vpHandler = () => {
                    if (!this.showModal) return;
                    if (this._vpRafId) return;
                    this._vpRafId = window.requestAnimationFrame(() => {
                        this._vpRafId = null;
                        const nextH = window.visualViewport ? Math.round(window.visualViewport.height) : window.innerHeight;
                        const nextT = window.visualViewport ? Math.round(window.visualViewport.offsetTop) : 0;
                        if (Math.abs(nextH - this.viewportHeight) > 2) {
                            this.viewportHeight = nextH;
                        }
                        if (Math.abs(nextT - this.viewportTop) > 2) {
                            this.viewportTop = nextT;
                        }
                    });
                };

                if (this.showModal) {
                    this.attachViewportListeners();
                }
            },

            attachViewportListeners() {
                if (!this._vpHandler) return;
                this._vpHandler();
                if (window.visualViewport) {
                    window.visualViewport.addEventListener('resize', this._vpHandler, { passive: true });
                    window.visualViewport.addEventListener('scroll', this._vpHandler, { passive: true });
                }
                window.addEventListener('resize', this._vpHandler, { passive: true });
            },

            detachViewportListeners() {
                if (!this._vpHandler) return;
                if (this._vpRafId) {
                    window.cancelAnimationFrame(this._vpRafId);
                    this._vpRafId = null;
                }
                if (window.visualViewport) {
                    window.visualViewport.removeEventListener('resize', this._vpHandler);
                    window.visualViewport.removeEventListener('scroll', this._vpHandler);
                }
                window.removeEventListener('resize', this._vpHandler);
            },

            statusLabel(st) {
                const map = {
                    pending: 'في الانتظار',
                    in_transit: 'في الطريق',
                    received: 'تم الاستلام بالفرع',
                    delivered: 'تم التسليم',
                    cancelled: 'ملغي',
                };
                return map[st] || st;
            },

            notifyToast(message, typeOrIsError = false) {
                const type = typeof typeOrIsError === 'boolean'
                    ? (typeOrIsError ? 'error' : 'success')
                    : (typeOrIsError || 'success');
                window.dispatchEvent(new CustomEvent('office-toast', {
                    detail: { message, type }
                }));
            },

            updateCounts(nextCounts) {
                if (!nextCounts || typeof nextCounts !== 'object') return;
                this.counts = {
                    all: Number(nextCounts.all ?? this.counts.all ?? 0),
                    pending: Number(nextCounts.pending ?? this.counts.pending ?? 0),
                    in_transit: Number(nextCounts.in_transit ?? this.counts.in_transit ?? 0),
                    received: Number(nextCounts.received ?? this.counts.received ?? 0),
                    delivered: Number(nextCounts.delivered ?? this.counts.delivered ?? 0),
                    cancelled: Number(nextCounts.cancelled ?? this.counts.cancelled ?? 0),
                };
            },

            handleCardContainerClick(event) {
                const btn = event.target?.closest('[data-card-action]');
                if (!btn) return;

                const action = btn.getAttribute('data-card-action');
                if (action === 'locked-shipments') {
                    this.notifyToast('ميزة الشحنات المرسلة غير مفعّلة لهذا المكتب، يرجى التواصل مع الإدارة لتفعيلها.', true);
                    return;
                }

                if (action === 'cancel' || action === 'deliver' || action === 'detach') {
                    const rawPayload = btn.getAttribute('data-action-payload');
                    if (!rawPayload) return;
                    try {
                        const parcelData = JSON.parse(rawPayload);
                        if (parcelData?.id && this.actionLoading[parcelData.id]) return;
                        this.openActionModal(action, parcelData);
                    } catch (e) {
                        console.warn('Invalid card action payload:', e);
                    }
                }
            },

            setStatusFilter(targetStatus) {
                this.activeStatus = String(targetStatus || 'all');
                this.fetchFilteredList();
            },

            toggleStatusFilter(targetStatus) {
                const next = (targetStatus === 'all' || this.activeStatus === targetStatus)
                    ? 'all'
                    : targetStatus;
                this.setStatusFilter(next);
            },

            handleSearchEvent(q) {
                this.searchQuery = String(q || '').trim();
                this.fetchFilteredList();
            },

            async fetchFilteredList() {
                if (typeof window.requireOnlineAction === 'function' && !window.requireOnlineAction()) {
                    return;
                }
                if (this._abortController) {
                    this._abortController.abort();
                }
                const controller = new AbortController();
                this._abortController = controller;

                const params = new URLSearchParams();
                if (this.activeStatus && this.activeStatus !== 'all') {
                    params.set('status', this.activeStatus);
                }
                if (this.searchQuery) {
                    params.set('q', this.searchQuery);
                }

                const qs = params.toString();
                const cleanUrl = qs ? `${this.baseUrl}?${qs}` : this.baseUrl;
                const fetchParams = new URLSearchParams(params);
                fetchParams.set('_t', String(Date.now()));
                const fetchUrl = `${this.baseUrl}?${fetchParams.toString()}`;

                this.isFetching = true;
                try {
                    const res = await fetch(fetchUrl, {
                        cache: 'no-store',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        signal: controller.signal,
                    });

                    if (!res.ok) throw new Error('Network error');
                    const data = await res.json();

                    if (this._abortController !== controller) return;

                    if (data && data.ok) {
                        this.updateCounts(data.counts);
                        if (this.$refs.cardsContainer && typeof data.html === 'string') {
                            this.$refs.cardsContainer.innerHTML = data.html;
                            if (window.Alpine) {
                                window.Alpine.initTree(this.$refs.cardsContainer);
                            }
                        }
                        window.history.replaceState({}, '', cleanUrl);
                    }
                } catch (err) {
                    if (err.name !== 'AbortError' && this._abortController === controller) {
                        console.warn('Outgoing filter fetch failed:', err);
                        if (typeof window.notifyNetworkError === 'function') {
                            window.notifyNetworkError('تعذر الاتصال بالخادم لتحديث قائمة الطرود الصادرة، يرجى المحاولة مجدداً.');
                        }
                    }
                } finally {
                    if (this._abortController === controller) {
                        this.isFetching = false;
                    }
                }
            },

            get actionModalMeta() {
                if (this.actionModal.type === 'detach') {
                    const shipNum = this.actionModal.parcel?.shipment_number;
                    return {
                        icon: 'link_off',
                        iconBoxClass: 'bg-amber-50 border-amber-200 text-amber-600',
                        destTextClass: 'text-amber-700',
                        title: 'إخراج الطرد من الشحنة',
                        subtitle: shipNum
                            ? `هل أنت متأكد من إخراج هذا الطرد من الشحنة #${shipNum} وإعادته إلى (في الانتظار)؟`
                            : 'هل أنت متأكد من إخراج هذا الطرد من الشحنة وإعادته إلى (في الانتظار)؟',
                        noticeIcon: 'info',
                        noticeBoxClass: 'bg-amber-50 border-amber-200/80 text-amber-800',
                        noticeIconClass: 'text-amber-600',
                        noticeLabel: 'ملاحظة: ',
                        noticeText: 'سيُفك ارتباط الطرد من الشحنة ويعود إلى قائمة (في الانتظار)، وبعدها يمكنك إلغاؤه أو ضمه إلى شحنة أخرى.',
                        confirmBtnClass: 'bg-amber-600 hover:bg-amber-700 shadow-amber-600/25',
                        confirmLabel: 'تأكيد الإخراج من الشحنة',
                        submittingLabel: 'جاري الإخراج...',
                        successFallback: 'تم إخراج الطرد من الشحنة بنجاح.',
                        errorFallback: 'تعذر إخراج الطرد من الشحنة.',
                    };
                }

                if (this.actionModal.type === 'deliver') {
                    return {
                        icon: 'check_circle',
                        iconBoxClass: 'bg-emerald-50 border-emerald-100 text-emerald-600',
                        destTextClass: 'text-emerald-700',
                        title: 'تأكيد تسليم الطرد',
                        subtitle: 'هل أنت متأكد من تسليم هذا الطرد للمكتب الخارجي؟',
                        noticeIcon: 'verified',
                        noticeBoxClass: 'bg-emerald-50 border-emerald-200/80 text-emerald-800',
                        noticeIconClass: 'text-emerald-600',
                        noticeLabel: 'ملاحظة: ',
                        noticeText: 'سيتم اعتماد الطرد كـ (تم التسليم) بشكل نهائي ولن يمكن تعديله أو إلغاؤه بعد ذلك.',
                        confirmBtnClass: 'bg-emerald-600 hover:bg-emerald-700 shadow-emerald-600/25',
                        confirmLabel: 'تأكيد التسليم للمكتب الخارجي',
                        submittingLabel: 'جاري التأكيد...',
                        successFallback: 'تم تأكيد تسليم الطرد بنجاح.',
                        errorFallback: 'تعذر تأكيد التسليم.',
                    };
                }

                return {
                    icon: 'cancel',
                    iconBoxClass: 'bg-rose-50 border-rose-100 text-rose-600',
                    destTextClass: 'text-rose-600',
                    title: 'إلغاء الطرد الصادر',
                    subtitle: 'هل أنت متأكد من رغبتك في إلغاء هذا الطرد الصادر؟',
                    noticeIcon: 'warning',
                    noticeBoxClass: 'bg-amber-50 border-amber-200/80 text-amber-800',
                    noticeIconClass: 'text-amber-600',
                    noticeLabel: 'تنبيه: ',
                    noticeText: 'سيتم إلغاء الطرد الصادر رسمياً ولن يظهر في قائمة الطرود القادمة لدى الفرع المستلم.',
                    confirmBtnClass: 'bg-rose-600 hover:bg-rose-700 shadow-rose-600/25',
                    confirmLabel: 'تأكيد إلغاء الطرد الصادر',
                    submittingLabel: 'جاري الإلغاء...',
                    successFallback: 'تم إلغاء الطرد الصادر بنجاح.',
                    errorFallback: 'تعذر إلغاء الطرد.',
                };
            },

            openActionModal(type, parcelData) {
                this.actionModal.type = type || 'cancel';
                this.actionModal.parcel = parcelData || null;
                this.actionModal.url = parcelData?.url || parcelData?.cancel_url || '#';
                this.actionModal.submitting = false;
                this.actionModal.open = true;
            },

            openCancelModal(parcelData) {
                this.openActionModal('cancel', parcelData);
            },

            closeActionModal() {
                if (this.actionModal.submitting) return;
                this.actionModal.open = false;
                this.actionModal.parcel = null;
                this.actionModal.url = '#';
            },

            async submitActionModal() {
                if (this.actionModal.submitting || !this.actionModal.parcel) return;
                if (typeof window.requireOnlineAction === 'function' && !window.requireOnlineAction()) return;

                const parcelId = this.actionModal.parcel.id;
                const url = this.actionModal.url;
                const meta = this.actionModalMeta;
                const token = document.querySelector('meta[name="csrf-token"]')?.content || '';

                this.actionModal.submitting = true;
                this.actionLoading[parcelId] = true;

                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        cache: 'no-store',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': token,
                        },
                    });

                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.ok) {
                        this.actionModal.submitting = false;
                        this.notifyToast(data.message || meta.errorFallback, true);
                        return;
                    }

                    const targetParcelId = data.parcel_id || data.parcel?.id || parcelId;
                    const nextStatus = data.status || data.parcel?.status || '';

                    this.updateCounts(data.counts);
                    this.actionModal.submitting = false;
                    this.closeActionModal();
                    this.applyCardDomUpdate(targetParcelId, nextStatus, data.card_html);
                    this.notifyToast(data.message || meta.successFallback, false);
                } catch (err) {
                    this.actionModal.submitting = false;
                    if (typeof window.notifyNetworkError === 'function') {
                        window.notifyNetworkError('تعذر الاتصال بالخادم، يرجى التأكد من الاتصال بالإنترنت والمحاولة مجدداً.');
                    } else {
                        this.notifyToast('تعذر الاتصال بالخادم، يرجى التأكد من الاتصال بالإنترنت والمحاولة مجدداً.', true);
                    }
                } finally {
                    this.actionLoading[parcelId] = false;
                }
            },

            applyCardDomUpdate(parcelId, newStatus, cardHtml) {
                const safeId = String(parcelId || '').replace(/"/g, '\\"');
                const cardEl = document.querySelector(`[data-outgoing-parcel-id="${safeId}"]`)
                    || document.getElementById('outgoing-card-' + parcelId);

                if (!cardEl) {
                    this.fetchFilteredList();
                    return;
                }

                // إذا كان الفلتر النشط مختلفاً عن الحالة الجديدة نزيل البطاقة فوراً
                if (this.activeStatus !== 'all' && newStatus && this.activeStatus !== newStatus) {
                    cardEl.remove();
                    if (this.$refs.cardsContainer && !this.$refs.cardsContainer.querySelector('[data-outgoing-parcel-id]')) {
                        this.fetchFilteredList();
                    }
                    return;
                }

                // إذا كان الفلتر 'all' أو يطابق الحالة الجديدة نستبدل البطاقة في مكانها فوراً
                if (typeof cardHtml === 'string' && cardHtml.trim() !== '') {
                    cardEl.outerHTML = cardHtml.trim();
                    const replacedEl = document.querySelector(`[data-outgoing-parcel-id="${safeId}"]`);
                    if (replacedEl && window.Alpine) {
                        window.Alpine.initTree(replacedEl);
                    }
                }
            },

            norm(str) {
                return String(str || '').toLowerCase().replace(/[أإآ]/g, 'ا').replace(/ة/g, 'ه').replace(/ى/g, 'ي').trim();
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
                this.attachViewportListeners();
            },

            closeModal() {
                this.showModal = false;
                this.detachViewportListeners();
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