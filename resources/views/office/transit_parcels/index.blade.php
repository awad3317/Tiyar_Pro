@extends('office.layouts.app')

@section('title', 'طرود قادمة في الطريق')

@section('shell-class', 'bg-slate-50')

@section('header-search')
    <div x-data="{
            q: '',
            normDigits(str) {
                const ar = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
                const fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
                return String(str || '')
                    .replace(/[٠-٩]/g, d => ar.indexOf(d))
                    .replace(/[۰-۹]/g, d => fa.indexOf(d))
                    .replace(/[\u200B-\u200D\uFEFF\u200E\u200F]/g, '');
            },
            emit() {
                this.q = this.normDigits(this.q);
                window.dispatchEvent(new CustomEvent('transit-search-change', { detail: { q: this.q } }));
            },
            clear() {
                this.q = '';
                this.emit();
            }
        }"
        @transit-search-clear.window="clear()"
        class="mt-3 relative">
        <input type="text"
               x-model="q"
               @input="emit()"
               dir="rtl"
               autocomplete="off"
               placeholder="بحث بالهاتف، السند، المستلم، المرسل، الفرع..."
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
    </div>
@endsection

@section('content')
    @php
        $transitIndexForJs = $transitParcels->map(function ($p) {
            $officeLabel = $p->office
                ? ($p->office->branch_name
                    ? $p->office->name . ' - ' . $p->office->branch_name
                    : $p->office->name)
                : '';

            return [
                'id'   => (string) $p->id,
                'text' => implode(' ', array_filter([
                    $p->package_type,
                    $p->receipt_number,
                    $p->recipient_name,
                    $p->recipient_phone,
                    $p->sender_name,
                    $p->sender_phone,
                    $officeLabel,
                    $p->notes,
                ])),
            ];
        })->values();
    @endphp

    <main class="flex-1 p-3.5 space-y-3.5"
          @transit-search-change.window="searchQuery = $event.detail.q || ''"
          x-data="transitParcelsPage({
              initialCount: {{ $transitParcels->count() }},
              items: @js($transitIndexForJs)
          })">

        {{-- رأس الصفحة بنفس هوية الطرود الصادرة --}}
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2.5 min-w-0">
                <a href="{{ route('office.dashboard') }}"
                   class="w-9 h-9 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-600 hover:text-primary hover:border-slate-300 transition-colors shadow-2xs shrink-0">
                    <span class="material-symbols-outlined text-lg">arrow_forward</span>
                </a>
                <div class="min-w-0">
                    <h2 class="text-sm font-extrabold text-slate-800 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-lg text-secondary">local_shipping</span>
                        <span class="truncate">طرود قادمة في الطريق</span>
                    </h2>
                    <p class="text-[11px] text-slate-400 truncate">مراجعة الشحنات الواردة من الفروع وتأكيد استلامها</p>
                </div>
            </div>

            <span class="px-2.5 py-1 rounded-xl bg-primary text-secondary text-[11px] font-extrabold shadow-xs shrink-0"
                  x-text="countLabel(remainingCount)">
                {{ $transitParcels->count() === 1 ? 'طرد واحد' : ($transitParcels->count() === 2 ? 'طردان' : $transitParcels->count() . ' طرود') }}
            </span>
        </div>

        {{-- قائمة الطرود القادمة --}}
        <div class="space-y-3" x-ref="transitList">
            @foreach ($transitParcels as $parcel)
                @php
                    $senderOfficeLabel = $parcel->office
                        ? ($parcel->office->branch_name
                            ? $parcel->office->name . ' - ' . $parcel->office->branch_name
                            : $parcel->office->name)
                        : 'مكتب غير معروف';

                    $paymentEnum = $parcel->payment_type instanceof \App\Enums\OutgoingPaymentType
                        ? $parcel->payment_type
                        : (\App\Enums\OutgoingPaymentType::tryFrom((string) $parcel->payment_type) ?? \App\Enums\OutgoingPaymentType::PAID);

                    $isDeferred = $paymentEnum->value === 'deferred';
                    $formattedAmount = number_format((float) ($parcel->cod_amount ?? 0));

                    // تنسيق الوقت والتاريخ بالعربي السليم
                    $dispatchedAt = $parcel->dispatched_at;
                    $dispatchedAgo = $dispatchedAt ? $dispatchedAt->copy()->locale('ar')->diffForHumans() : null;
                    $dispatchedClock = $dispatchedAt
                        ? $dispatchedAt->format('g:i') . ' ' . ($dispatchedAt->format('A') === 'AM' ? 'ص' : 'م')
                        : null;
                    $dispatchedDate = $dispatchedAt && !$dispatchedAt->isToday()
                        ? $dispatchedAt->format('Y/m/d')
                        : null;

                    $modalPayload = [
                        'id'               => (string) $parcel->id,
                        'url'              => route('office.transit-parcels.receive', $parcel),
                        'package_type'     => $parcel->package_type,
                        'receipt_number'   => $parcel->receipt_number,
                        'source_office'    => $senderOfficeLabel,
                        'payment_label'    => $paymentEnum->label(),
                        'is_deferred'      => $isDeferred,
                        'cod_amount'       => $formattedAmount,
                        'recipient_name'   => $parcel->recipient_name,
                        'recipient_phone'  => $parcel->recipient_phone,
                        'sender_name'      => $parcel->sender_name,
                        'sender_phone'     => $parcel->sender_phone,
                        'dispatched_ago'   => $dispatchedAgo,
                        'dispatched_clock' => $dispatchedClock,
                    ];
                @endphp

                <article id="transit-card-{{ $parcel->id }}"
                         x-show="!receivedIds['{{ $parcel->id }}'] && isItemVisible('{{ $parcel->id }}')"
                         style="content-visibility: auto; contain-intrinsic-size: 185px;"
                         class="bg-white border border-slate-200/90 rounded-2xl p-3.5 shadow-xs space-y-3">

                    {{-- 1. رأس البطاقة: نوع الطرد + السند + وقت الشحن بالعربي --}}
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-9 h-9 rounded-xl bg-slate-100 text-primary flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-xl">package_2</span>
                            </span>
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <h3 class="font-extrabold text-sm text-slate-800 leading-tight">{{ $parcel->package_type }}</h3>
                                    @if ($parcel->receipt_number)
                                        <span class="px-1.5 py-0.5 rounded-md bg-slate-100 text-slate-700 border border-slate-200 text-[10px] font-mono font-extrabold">
                                            سند #{{ $parcel->receipt_number }}
                                        </span>
                                    @endif
                                </div>

                                @if ($dispatchedClock)
                                    <p class="text-[10px] text-slate-400 font-semibold mt-0.5 flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[12px]">schedule</span>
                                        <span>شُحن {{ $dispatchedDate ? $dispatchedDate . ' • ' : 'الساعة ' }}{{ $dispatchedClock }}</span>
                                    </p>
                                @endif
                            </div>
                        </div>

                        @if ($dispatchedAgo)
                            <span dir="rtl"
                                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-200/80 text-[10px] font-extrabold shrink-0">
                                <span>{{ $dispatchedAgo }}</span>
                            </span>
                        @endif
                    </div>

                    {{-- 2. شريط المصدر والحساب --}}
                    <div class="flex items-center justify-between gap-2 flex-wrap pt-0.5">
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-100 text-primary border border-slate-200/80 text-[11px] font-extrabold max-w-full">
                            <span class="material-symbols-outlined text-[14px] text-secondary shrink-0">domain</span>
                            <span class="truncate">قادم من: {{ $senderOfficeLabel }}</span>
                        </span>

                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border text-[11px] font-extrabold shrink-0 {{ $paymentEnum->badgeClass() }}">
                            <span>{{ $paymentEnum->label() }}:</span>
                            <span class="font-mono">{{ $formattedAmount }} ر.ي</span>
                        </span>
                    </div>

                    {{-- 3. بطاقة المستلم والمرسل مع زر اتصال سريع --}}
                    <div class="grid grid-cols-2 gap-2.5 bg-slate-50 rounded-xl p-2.5 text-xs border border-slate-100">
                        <div class="min-w-0">
                            <p class="text-[10px] text-slate-400 font-bold mb-0.5">المستلم</p>
                            <p class="font-extrabold text-slate-800 truncate">{{ $parcel->recipient_name ?: 'بدون اسم' }}</p>
                            <a href="tel:{{ $parcel->recipient_phone }}"
                               class="inline-flex items-center gap-1 text-[11px] text-slate-600 hover:text-primary font-mono font-bold mt-0.5">
                                <span class="material-symbols-outlined text-[13px] text-emerald-600">call</span>
                                <span dir="ltr">{{ $parcel->recipient_phone }}</span>
                            </a>
                        </div>

                        <div class="min-w-0 border-r border-slate-200 pr-2.5">
                            <p class="text-[10px] text-slate-400 font-bold mb-0.5">المرسل</p>
                            <p class="font-bold text-slate-700 truncate">{{ $parcel->sender_name }}</p>
                            <a href="tel:{{ $parcel->sender_phone }}"
                               class="inline-flex items-center gap-1 text-[11px] text-slate-500 hover:text-primary font-mono font-semibold mt-0.5">
                                <span class="material-symbols-outlined text-[13px] text-slate-400">call</span>
                                <span dir="ltr">{{ $parcel->sender_phone }}</span>
                            </a>
                        </div>
                    </div>

                    {{-- 4. الملاحظات (إن وجدت) --}}
                    @if ($parcel->notes)
                        <div class="flex items-start gap-1.5 text-[11px] text-slate-700 bg-amber-50/70 border border-amber-200/70 rounded-xl px-2.5 py-2 leading-relaxed">
                            <span class="material-symbols-outlined text-sm text-amber-600 shrink-0 mt-0.5">sticky_note_2</span>
                            <p class="min-w-0 break-words">
                                <span class="font-extrabold text-amber-900">ملاحظة:</span> {{ $parcel->notes }}
                            </p>
                        </div>
                    @endif

                    {{-- 5. زر تأكيد الاستلام (يفتح مودال التأكيد الموحّد) --}}
                    <div class="pt-0.5">
                        <button type="button"
                                :disabled="Boolean(receivingIds['{{ $parcel->id }}'])"
                                @click="openReceiveModal(@js($modalPayload))"
                                class="w-full py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:scale-[0.99] text-white text-xs font-extrabold shadow-sm shadow-emerald-600/20 flex items-center justify-center gap-1.5 transition-all disabled:opacity-50">
                            <span class="material-symbols-outlined text-base">download_done</span>
                            <span x-text="receivingIds['{{ $parcel->id }}'] ? 'جاري الاستلام وإشعار العميل...' : 'تم الاستلام'"></span>
                        </button>
                    </div>
                </article>
            @endforeach

            {{-- حالة عدم تطابق البحث --}}
            <div x-show="remainingCount > 0 && visibleCount === 0"
                 x-cloak
                 class="bg-white rounded-2xl border border-slate-200/80 p-8 text-center text-slate-400 space-y-2">
                <span class="material-symbols-outlined text-4xl text-slate-300">search_off</span>
                <p class="text-xs font-extrabold text-slate-700">لا توجد طرود مطابقة لبحثك</p>
                <p class="text-[11px] text-slate-400">جرّب البحث برقم جوال آخر أو رقم سند أو اسم الفرع.</p>
                <div class="pt-1.5">
                    <button type="button"
                            @click="window.dispatchEvent(new CustomEvent('transit-search-clear'))"
                            class="inline-flex items-center gap-1 px-3.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors">
                        <span class="material-symbols-outlined text-sm">close</span>
                        <span>مسح البحث</span>
                    </button>
                </div>
            </div>

            {{-- حالة عدم وجود طرود قادمة --}}
            <div x-show="remainingCount === 0"
                 @if ($transitParcels->count() > 0) x-cloak @endif
                 class="bg-white rounded-2xl border border-slate-200/80 p-10 text-center text-slate-400 space-y-2">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-emerald-50 border border-emerald-100 text-emerald-500 flex items-center justify-center">
                    <span class="material-symbols-outlined text-3xl">task_alt</span>
                </div>
                <p class="text-xs font-extrabold text-slate-700 pt-1">لا توجد طرود قادمة في الطريق حالياً</p>
                <p class="text-[11px] text-slate-400">تم استلام جميع الطرود المرسلة إلى مكتبكم.</p>
                <div class="pt-2">
                    <a href="{{ route('office.parcels.index') }}"
                       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-primary text-white text-xs font-bold hover:bg-slate-800 transition-colors">
                        <span class="material-symbols-outlined text-sm text-secondary">inventory_2</span>
                        <span>الذهاب إلى الطرود المستلمة</span>
                    </a>
                </div>
            </div>
        </div>

        {{-- مودال تأكيد استلام الطرد القادم (بنفس التصميم الموحّد للنظام وبدون backdrop-blur للأداء) --}}
        <div x-show="receiveModal.open"
             x-cloak
             class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-3 sm:p-4"
             @keydown.escape.window="closeReceiveModal()">

            {{-- خلفية التعتيم السريعة --}}
            <div class="fixed inset-0 bg-slate-950/60 transition-opacity"
                 x-show="receiveModal.open"
                 x-transition:enter="ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="closeReceiveModal()">
            </div>

            {{-- بطاقة المودال --}}
            <div class="relative w-full max-w-sm bg-white rounded-3xl p-5 shadow-2xl border border-slate-100 z-10 transition-all transform flex flex-col gap-4 text-right"
                 x-show="receiveModal.open"
                 x-transition:enter="ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-6 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-6 sm:translate-y-0 sm:scale-95">

                {{-- زر إغلاق علوي --}}
                <button type="button"
                        @click="closeReceiveModal()"
                        :disabled="receiveModal.submitting"
                        title="إغلاق"
                        class="absolute left-4 top-4 w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-400 hover:text-slate-600 flex items-center justify-center transition-colors disabled:opacity-50">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>

                {{-- الأيقونة والعنوان --}}
                <div class="text-center pt-1">
                    <div class="w-14 h-14 mx-auto rounded-2xl bg-emerald-50 border border-emerald-100 text-emerald-600 flex items-center justify-center shadow-inner mb-3">
                        <span class="material-symbols-outlined text-3xl">download_done</span>
                    </div>
                    <h3 class="text-base font-extrabold text-slate-800">تأكيد استلام الطرد</h3>
                    <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                        هل وصل هذا الطرد إلى مكتبك فعلياً وتريد تحويله للطرود الواردة؟
                    </p>
                </div>

                {{-- ملخص بيانات الطرد --}}
                <template x-if="receiveModal.parcel">
                    <div class="bg-slate-50 rounded-2xl p-3.5 border border-slate-200/80 space-y-2.5 text-xs">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-200/60">
                            <span class="text-slate-400 flex items-center gap-1 font-medium">
                                <span class="material-symbols-outlined text-sm text-secondary">domain</span>
                                قادم من:
                            </span>
                            <span class="font-extrabold text-primary truncate max-w-[180px]"
                                  x-text="receiveModal.parcel.source_office"></span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">نوع الشحنة:</span>
                            <span class="font-bold text-slate-700" x-text="receiveModal.parcel.package_type"></span>
                        </div>

                        <div class="flex items-center justify-between" x-show="receiveModal.parcel.receipt_number">
                            <span class="text-slate-400">رقم السند:</span>
                            <span class="font-mono font-bold text-slate-700" x-text="'#' + receiveModal.parcel.receipt_number"></span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">الحساب:</span>
                            <span class="font-extrabold"
                                  :class="receiveModal.parcel.is_deferred ? 'text-amber-700' : 'text-emerald-700'">
                                <span x-text="receiveModal.parcel.payment_label"></span>:
                                <span class="font-mono" x-text="(receiveModal.parcel.cod_amount || '0') + ' ر.ي'"></span>
                            </span>
                        </div>

                        <div class="flex items-center justify-between pt-1">
                            <span class="text-slate-400">المستلم:</span>
                            <div class="text-left">
                                <span class="font-bold text-slate-700 block" x-text="receiveModal.parcel.recipient_name || 'بدون اسم'"></span>
                                <span class="text-[11px] text-slate-400 font-mono dir-ltr block" x-text="receiveModal.parcel.recipient_phone"></span>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- تنبيه إشعار العميل وتحصيل الآجل --}}
                <div class="space-y-2">
                    <template x-if="receiveModal.parcel && receiveModal.parcel.is_deferred">
                        <div class="bg-amber-50 border border-amber-200/80 rounded-xl p-2.5 flex items-start gap-2 text-[11px] text-amber-800 leading-snug">
                            <span class="material-symbols-outlined text-base text-amber-600 shrink-0 mt-0.5">payments</span>
                            <span>
                                <strong>طرد آجل:</strong> سيتم تسجيل مبلغ
                                <strong class="font-mono" x-text="receiveModal.parcel.cod_amount + ' ر.ي'"></strong>
                                لتحصيله من العميل عند التسليم.
                            </span>
                        </div>
                    </template>

                    <div class="bg-emerald-50 border border-emerald-200/80 rounded-xl p-2.5 flex items-start gap-2 text-[11px] text-emerald-800 leading-snug">
                        <span class="material-symbols-outlined text-base text-emerald-600 shrink-0 mt-0.5">notifications_active</span>
                        <span>
                            <strong>إشعار تلقائي:</strong> سيتم تحويل الطرد إلى (الطرود المستلمة) وإشعار العميل بوصول طرده فوراً.
                        </span>
                    </div>
                </div>

                {{-- أزرار التنفيذ --}}
                <form method="POST"
                      :action="receiveModal.url"
                      class="flex flex-col gap-2 pt-1"
                      @submit.prevent="confirmReceiveModal()">
                    @csrf
                    <button type="submit"
                            :disabled="receiveModal.submitting"
                            class="w-full py-3 px-4 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] text-white font-extrabold text-xs rounded-xl shadow-lg shadow-emerald-600/25 flex items-center justify-center gap-2 transition-all disabled:opacity-50">
                        <span class="material-symbols-outlined text-base">download_done</span>
                        <span x-text="receiveModal.submitting ? 'جاري الاستلام وإشعار العميل...' : 'تأكيد الاستلام وإشعار العميل'"></span>
                    </button>

                    <button type="button"
                            @click="closeReceiveModal()"
                            :disabled="receiveModal.submitting"
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
        function transitParcelsPage(config) {
            const rawItems = Array.isArray(config.items) ? config.items : [];
            const itemsMap = {};
            rawItems.forEach(item => {
                itemsMap[String(item.id)] = String(item.text || '');
            });

            return {
                remainingCount: Number(config.initialCount || 0),
                items: rawItems,
                itemsMap: itemsMap,
                searchQuery: '',
                receivingIds: {},
                receivedIds: {},

                receiveModal: {
                    open: false,
                    submitting: false,
                    url: '#',
                    parcel: null,
                },

                norm(str) {
                    return String(str || '')
                        .toLowerCase()
                        .replace(/[أإآ]/g, 'ا')
                        .replace(/ة/g, 'ه')
                        .replace(/ى/g, 'ي')
                        .trim();
                },

                isItemVisible(id) {
                    const q = this.norm(this.searchQuery);
                    if (!q) return true;
                    const haystack = this.norm(this.itemsMap[String(id)] || '');
                    return haystack.includes(q);
                },

                get visibleCount() {
                    let count = 0;
                    for (let i = 0; i < this.items.length; i++) {
                        const id = String(this.items[i].id);
                        if (!this.receivedIds[id] && this.isItemVisible(id)) {
                            count++;
                        }
                    }
                    return count;
                },

                countLabel(n) {
                    n = Number(n) || 0;
                    if (n === 0) return 'لا توجد طرود';
                    if (n === 1) return 'طرد واحد';
                    if (n === 2) return 'طردان';
                    if (n >= 3 && n <= 10) return n + ' طرود';
                    return n + ' طرداً';
                },

                notifyToast(message, typeOrIsError = false) {
                    const type = typeof typeOrIsError === 'boolean'
                        ? (typeOrIsError ? 'error' : 'success')
                        : (typeOrIsError || 'success');
                    window.dispatchEvent(new CustomEvent('office-toast', {
                        detail: { message, type }
                    }));
                },

                openReceiveModal(parcelData) {
                    this.receiveModal.parcel = parcelData || null;
                    this.receiveModal.url = parcelData?.url || '#';
                    this.receiveModal.submitting = false;
                    this.receiveModal.open = true;
                },

                closeReceiveModal() {
                    if (this.receiveModal.submitting) return;
                    this.receiveModal.open = false;
                    this.receiveModal.parcel = null;
                    this.receiveModal.url = '#';
                },

                async confirmReceiveModal() {
                    if (this.receiveModal.submitting || !this.receiveModal.parcel) return;
                    if (typeof window.requireOnlineAction === 'function' && !window.requireOnlineAction()) return;

                    const parcelId = String(this.receiveModal.parcel.id);
                    const url = this.receiveModal.url;
                    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';

                    this.receiveModal.submitting = true;
                    this.receivingIds[parcelId] = true;

                    try {
                        const res = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': token,
                            },
                        });

                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || !data.ok) {
                            this.receiveModal.submitting = false;
                            this.notifyToast(data.message || 'تعذر استلام الطرد.', true);
                            return;
                        }

                        this.receivedIds[parcelId] = true;

                        if (typeof data.remaining_count === 'number') {
                            this.remainingCount = data.remaining_count;
                        } else {
                            this.remainingCount = Math.max(0, this.remainingCount - 1);
                        }

                        window.dispatchEvent(new CustomEvent('transit-count-updated', {
                            detail: { count: this.remainingCount }
                        }));

                        this.receiveModal.submitting = false;
                        this.closeReceiveModal();
                        this.notifyToast(data.message || 'تم استلام الطرد وإشعار العميل بنجاح.', false);
                    } catch (err) {
                        this.receiveModal.submitting = false;
                        if (typeof window.notifyNetworkError === 'function') {
                            window.notifyNetworkError('تعذر الاتصال بالخادم، يرجى التأكد من الاتصال بالإنترنت والمحاولة مجدداً.');
                        } else {
                            this.notifyToast('تعذر الاتصال بالخادم، يرجى التأكد من الاتصال بالإنترنت والمحاولة مجدداً.', true);
                        }
                    } finally {
                        this.receivingIds[parcelId] = false;
                    }
                }
            };
        }
    </script>
@endpush