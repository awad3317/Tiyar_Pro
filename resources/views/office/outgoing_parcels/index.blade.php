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
              initialPackageType: @js(old('package_type', '')),
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
                    {{-- رأس البطاقة: نوع الطرد، العدد، السند، وحالة الطرد --}}
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="w-8 h-8 rounded-lg bg-slate-100 text-primary flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-lg">outbox</span>
                            </span>
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <h3 class="font-extrabold text-sm text-slate-800">{{ $parcel->package_type }}</h3>
                                    <span class="px-1.5 py-0.5 rounded-md bg-slate-100 text-slate-700 text-[10px] font-bold">
                                        العدد: {{ $parcel->quantity }}
                                    </span>
                                    @if($parcel->receipt_number)
                                        <span class="text-[11px] text-slate-400 font-mono font-bold">
                                            سند: #{{ $parcel->receipt_number }}
                                        </span>
                                    @endif
                                </div>

                                {{-- شارة الوجهة --}}
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

                                    @if($parcel->cod_amount && (float) $parcel->cod_amount > 0)
                                        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold font-mono">
                                            <span>تحصيل: {{ number_format((float) $parcel->cod_amount) }} ر.ي</span>
                                        </span>
                                    @endif
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
             class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-end sm:items-center justify-center p-0 sm:p-4">
            <div @click.outside="showModal = false"
                 class="bg-white w-full max-w-md rounded-t-3xl sm:rounded-3xl max-h-[92vh] flex flex-col shadow-2xl border border-slate-200 overflow-hidden">

                {{-- هيدر المودال --}}
                <div class="px-4 py-3.5 bg-primary text-white flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-secondary text-xl">outbox</span>
                        <h3 class="text-sm font-extrabold">إضافة طرد صادر جديد</h3>
                    </div>
                    <button type="button" @click="showModal = false" class="text-slate-300 hover:text-white p-1">
                        <span class="material-symbols-outlined text-lg">close</span>
                    </button>
                </div>

                {{-- نموذج الإضافة --}}
                <form method="POST"
                      action="{{ route('office.outgoing-parcels.store') }}"
                      class="p-4 space-y-3 overflow-y-auto flex-1"
                      x-data="{ submitting: false }"
                      @submit="if (submitting) { $event.preventDefault(); return; } submitting = true">
                    @csrf

                    {{-- أ. اختيار نوع الوجهة --}}
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">الوجهة المستلمة</label>
                        <input type="hidden" name="destination_type" :value="destType">
                        <div class="grid grid-cols-2 gap-2 bg-slate-100 p-1 rounded-xl">
                            <button type="button"
                                    @click="destType = 'office'"
                                    :class="destType === 'office' ? 'bg-white text-primary shadow-xs font-extrabold' : 'text-slate-500 font-bold'"
                                    class="py-2 rounded-lg text-xs flex items-center justify-center gap-1 transition-all">
                                <span class="material-symbols-outlined text-sm">domain</span>
                                <span>فرع في النظام</span>
                            </button>
                            <button type="button"
                                    @click="destType = 'external'"
                                    :class="destType === 'external' ? 'bg-white text-primary shadow-xs font-extrabold' : 'text-slate-500 font-bold'"
                                    class="py-2 rounded-lg text-xs flex items-center justify-center gap-1 transition-all">
                                <span class="material-symbols-outlined text-sm">storefront</span>
                                <span>مكتب خارجي</span>
                            </button>
                        </div>
                    </div>

                    {{-- اختيار الفرع في النظام (مع بحث سريع) --}}
                    <div x-show="destType === 'office'" class="space-y-1.5">
                        <label class="block text-[11px] font-bold text-slate-600">اختر الفرع المستلم <span class="text-rose-500">*</span></label>
                        <input type="text"
                               x-model="officeSearch"
                               placeholder="ابحث عن اسم الفرع أو المدينة..."
                               class="w-full py-2 px-3 text-xs font-bold text-slate-700 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white outline-none focus:border-secondary">
                        <select name="destination_office_id"
                                x-model="selectedOfficeId"
                                :required="destType === 'office'"
                                class="w-full py-2.5 px-3 text-xs font-bold text-slate-800 rounded-xl border border-slate-200 bg-white outline-none focus:border-secondary">
                            <option value="">-- اختر الفرع من القائمة --</option>
                            <template x-for="off in filteredOffices" :key="off.id">
                                <option :value="String(off.id)" x-text="off.label"></option>
                            </template>
                        </select>
                    </div>

                    {{-- اسم المكتب الخارجي --}}
                    <div x-show="destType === 'external'" x-cloak>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">اسم المكتب الخارجي <span class="text-rose-500">*</span></label>
                        <input type="text"
                               name="external_office_name"
                               value="{{ old('external_office_name') }}"
                               :required="destType === 'external'"
                               placeholder="مثال: مكتب الصقر - مأرب"
                               class="w-full py-2 px-3 text-xs font-bold text-slate-800 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white outline-none focus:border-secondary">
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

                    {{-- د. نوع الطرد + أزرار الاختصار السريع + العدد --}}
                    <div class="grid grid-cols-12 gap-2">
                        <div class="col-span-8">
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">نوع الطرد <span class="text-rose-500">*</span></label>
                            <input type="text"
                                   name="package_type"
                                   x-model="packageType"
                                   required
                                   placeholder="مثال: كرتون، كيس، ظرف..."
                                   class="w-full py-2 px-3 text-xs font-bold text-slate-800 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white outline-none focus:border-secondary">
                        </div>
                        <div class="col-span-4">
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">العدد</label>
                            <input type="number"
                                   name="quantity"
                                   value="{{ old('quantity', 1) }}"
                                   min="1"
                                   max="999"
                                   dir="ltr"
                                   class="w-full py-2 px-2.5 text-xs font-mono font-bold text-center text-slate-800 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white outline-none focus:border-secondary">
                        </div>
                    </div>

                    {{-- شرائح الاختيار السريع لنوع الطرد --}}
                    <div class="flex items-center gap-1.5 overflow-x-auto pb-0.5 scrollbar-none">
                        <template x-for="preset in ['كيس', 'ضرف', 'كرتون', 'كيس أبيض', 'كرتون كاكي', 'ضرف أبيض']" :key="preset">
                            <button type="button"
                                    @click="packageType = preset"
                                    class="py-1 px-2.5 rounded-xl bg-slate-100 hover:bg-secondary/15 hover:text-secondary text-slate-700 text-[11px] font-bold shrink-0 transition-colors">
                                <span x-text="preset"></span>
                            </button>
                        </template>
                    </div>

                    {{-- هـ. رقم السند ومبلغ التحصيل --}}
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">رقم السند <span class="text-[10px] font-normal text-slate-400">(اختياري)</span></label>
                            <input type="text"
                                   name="receipt_number"
                                   value="{{ old('receipt_number') }}"
                                   dir="ltr"
                                   placeholder="مثال: 1050"
                                   class="w-full py-2 px-3 text-xs font-mono font-bold text-slate-800 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white outline-none focus:border-secondary">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">مبلغ التحصيل (ر.ي) <span class="text-[10px] font-normal text-slate-400">(اختياري)</span></label>
                            <input type="number"
                                   step="any"
                                   min="0"
                                   name="cod_amount"
                                   value="{{ old('cod_amount') }}"
                                   dir="ltr"
                                   placeholder="مثال: 5000"
                                   class="w-full py-2 px-3 text-xs font-mono font-bold text-slate-800 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white outline-none focus:border-secondary">
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

                    {{-- أزرار الحفظ والإلغاء --}}
                    <div class="pt-2 flex items-center gap-2">
                        <button type="submit"
                                :disabled="submitting"
                                class="flex-1 py-2.5 px-4 rounded-xl bg-secondary hover:bg-amber-500 text-white text-xs font-extrabold shadow-md shadow-secondary/20 flex items-center justify-center gap-1.5 transition-all disabled:opacity-50">
                            <span class="material-symbols-outlined text-base">check_circle</span>
                            <span x-text="submitting ? 'جاري الحفظ...' : 'حفظ الطرد الصادر'"></span>
                        </button>
                        <button type="button"
                                @click="showModal = false"
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
        return {
            showModal: Boolean(config.hasErrors),
            destType: config.initialDestType || 'office',
            selectedOfficeId: String(config.initialOfficeId || ''),
            officeSearch: '',
            packageType: config.initialPackageType || 'طرد',
            offices: config.offices || [],

            openModal() {
                this.showModal = true;
            },

            get filteredOffices() {
                const q = (this.officeSearch || '').trim().toLowerCase();
                if (!q) return this.offices;
                return this.offices.filter(o => (o.label || '').toLowerCase().includes(q));
            }
        };
    }
</script>
@endpush
