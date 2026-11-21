@extends('office.layouts.app')

@section('title', 'الطرود الصادرة')

@section('shell-class', 'bg-slate-50')

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
          x-data="outgoingParcelsPage({
              hasErrors: {{ $errors->any() ? 'true' : 'false' }},
              initialDestType: @js(old('destination_type', 'office')),
              initialOfficeId: @js(old('destination_office_id', '')),
              initialExternalName: @js(old('external_office_name', '')),
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
                <p class="text-[11px] text-slate-400">إرسال ومتابعة الشحنات الموجهة للفروع والمكاتب</p>
            </div>

            <button type="button"
                    @click="openModal()"
                    class="py-2 px-3.5 rounded-xl bg-secondary hover:bg-amber-500 active:scale-95 text-white text-xs font-extrabold shadow-sm shadow-secondary/25 flex items-center gap-1 transition-all shrink-0">
                <span class="material-symbols-outlined text-base">add_circle</span>
                <span>+ إضافة طرد صادر</span>
            </button>
        </div>

        {{-- 1. شرائح الملخص العلوية --}}
        <div class="grid grid-cols-3 gap-2">
            <a href="{{ route('office.outgoing-parcels.index', ['status' => 'pending', 'q' => $search]) }}"
               class="bg-white p-2.5 rounded-2xl border {{ $statusFilter === 'pending' ? 'border-amber-400 ring-1 ring-amber-200' : 'border-slate-200/80' }} shadow-2xs text-center block transition-all">
                <p class="text-[10px] font-bold text-slate-400">في الانتظار</p>
                <p class="text-lg font-black text-amber-600 mt-0.5">{{ $counts['pending'] }}</p>
            </a>

            <a href="{{ route('office.outgoing-parcels.index', ['status' => 'in_transit', 'q' => $search]) }}"
               class="bg-white p-2.5 rounded-2xl border {{ $statusFilter === 'in_transit' ? 'border-sky-400 ring-1 ring-sky-200' : 'border-slate-200/80' }} shadow-2xs text-center block transition-all">
                <p class="text-[10px] font-bold text-slate-400">في الطريق</p>
                <p class="text-lg font-black text-sky-600 mt-0.5">{{ $counts['in_transit'] }}</p>
            </a>

            <a href="{{ route('office.outgoing-parcels.index', ['status' => 'received', 'q' => $search]) }}"
               class="bg-white p-2.5 rounded-2xl border {{ $statusFilter === 'received' ? 'border-indigo-400 ring-1 ring-indigo-200' : 'border-slate-200/80' }} shadow-2xs text-center block transition-all">
                <p class="text-[10px] font-bold text-slate-400">وصل الفرع</p>
                <p class="text-lg font-black text-indigo-600 mt-0.5">{{ $counts['received'] }}</p>
            </a>
        </div>

        {{-- 2. شريط البحث وفلاتر الحالة --}}
        <div class="bg-white p-3 rounded-2xl border border-slate-200/80 shadow-2xs space-y-2.5">
            <form method="GET" action="{{ route('office.outgoing-parcels.index') }}" class="flex items-center gap-2">
                @if($statusFilter !== 'all')
                    <input type="hidden" name="status" value="{{ $statusFilter }}">
                @endif
                <div class="relative flex-1">
                    <input type="text"
                           name="q"
                           value="{{ $search }}"
                           placeholder="بحث بالمرسل، المستلم، الهاتف، رقم السند..."
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl pr-8 pl-3 py-2 text-xs font-bold text-slate-800 placeholder:text-slate-400 placeholder:font-normal outline-none focus:bg-white focus:border-secondary transition-all">
                    <span class="material-symbols-outlined text-base text-slate-400 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none">search</span>
                </div>
                <button type="submit"
                        class="px-3 py-2 rounded-xl bg-primary text-white text-xs font-bold hover:bg-slate-800 transition-colors shrink-0">
                    بحث
                </button>
                @if($search !== '' || $statusFilter !== 'all')
                    <a href="{{ route('office.outgoing-parcels.index') }}"
                       class="px-2.5 py-2 rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-50 text-xs font-bold shrink-0"
                       title="إعادة تعيين">
                        ✕
                    </a>
                @endif
            </form>

            @php
                $filterPills = [
                    'all'        => 'الكل (' . $counts['all'] . ')',
                    'pending'    => 'في الانتظار (' . $counts['pending'] . ')',
                    'in_transit' => 'في الطريق (' . $counts['in_transit'] . ')',
                    'received'   => 'تم الاستلام بالفرع (' . $counts['received'] . ')',
                    'delivered'  => 'تم التسليم (' . $counts['delivered'] . ')',
                    'cancelled'  => 'ملغي (' . $counts['cancelled'] . ')',
                ];
            @endphp
            <div class="flex items-center gap-1.5 overflow-x-auto pb-0.5 scrollbar-none">
                @foreach ($filterPills as $key => $label)
                    <a href="{{ route('office.outgoing-parcels.index', array_filter(['status' => $key !== 'all' ? $key : null, 'q' => $search !== '' ? $search : null])) }}"
                       class="px-2.5 py-1 rounded-xl text-[11px] font-bold shrink-0 border transition-colors {{ $statusFilter === $key ? 'bg-primary text-white border-primary' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>

        {{-- 3. قائمة كروت الطرود الصادرة --}}
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

                            <form method="POST"
                                  action="{{ route('office.outgoing-parcels.cancel', $parcel) }}"
                                  x-data="{ submitting: false }"
                                  @submit="if (submitting) { $event.preventDefault(); return; } if (!confirm('هل أنت متأكد من إلغاء هذا الطرد الصادر؟')) { $event.preventDefault(); return; } submitting = true">
                                @csrf
                                <button type="submit"
                                        :disabled="submitting"
                                        class="py-2 px-3 rounded-xl border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold flex items-center justify-center gap-1 transition-all active:scale-95 disabled:opacity-50">
                                    <span class="material-symbols-outlined text-sm">cancel</span>
                                    <span>إلغاء</span>
                                </button>
                            </form>
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

                            <form method="POST"
                                  action="{{ route('office.outgoing-parcels.cancel', $parcel) }}"
                                  x-data="{ submitting: false }"
                                  @submit="if (submitting) { $event.preventDefault(); return; } if (!confirm('هل أنت متأكد من إلغاء هذا الطرد الصادر؟')) { $event.preventDefault(); return; } submitting = true">
                                @csrf
                                <button type="submit"
                                        :disabled="submitting"
                                        class="py-2 px-3 rounded-xl border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold flex items-center justify-center gap-1 transition-all active:scale-95 disabled:opacity-50">
                                    <span class="material-symbols-outlined text-sm">cancel</span>
                                    <span>إلغاء</span>
                                </button>
                            </form>
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
                            <input type="tel"
                                   name="sender_phone"
                                   value="{{ old('sender_phone') }}"
                                   required
                                   dir="ltr"
                                   inputmode="numeric"
                                   placeholder="7XXXXXXXX"
                                   class="w-full py-2 px-3 text-xs font-mono font-bold text-slate-800 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white outline-none focus:border-secondary">
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
                            <input type="tel"
                                   name="recipient_phone"
                                   value="{{ old('recipient_phone') }}"
                                   required
                                   dir="ltr"
                                   inputmode="numeric"
                                   placeholder="7XXXXXXXX"
                                   class="w-full py-2 px-3 text-xs font-mono font-bold text-slate-800 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white outline-none focus:border-secondary">
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
            viewportHeight: window.visualViewport ? Math.round(window.visualViewport.height) : window.innerHeight,
            viewportTop: window.visualViewport ? Math.round(window.visualViewport.offsetTop) : 0,
            destDropdownOpen: false,
            destType: matchedOffice ? 'office' : (initExternal !== '' ? 'external' : (config.initialDestType || 'office')),
            selectedOfficeId: matchedOffice ? String(matchedOffice.id) : '',
            externalOfficeName: matchedOffice ? '' : initExternal,
            destinationQuery: matchedOffice ? matchedOffice.label : initExternal,
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
