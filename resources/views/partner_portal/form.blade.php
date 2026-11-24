@extends('partner_portal.layout')

@section('title', 'إرسال كشف طرود جديد')

@section('content')
    <div class="space-y-3.5"
         x-data="externalPortalShipmentForm({
             token: @js($link->token),
             prefix: @js($link->receipt_prefix),
             submitUrl: @js(route('partner.portal.shipments.store', ['token' => $link->token])),
             partnerName: @js($link->partner_name),
             officeName: @js($brand['office_name'] ?? 'المكتب المستلم')
         })">

        {{-- شريط التنبيهات الفوري --}}
        <div x-show="alert.message"
             x-cloak
             :class="alert.type === 'error'
                 ? 'bg-rose-50 border-rose-200 text-rose-800'
                 : 'bg-emerald-50 border-emerald-200 text-emerald-800'"
             class="rounded-2xl border p-3 text-xs font-extrabold flex items-start gap-2">
            <span class="material-symbols-outlined text-base shrink-0"
                  x-text="alert.type === 'error' ? 'error' : 'check_circle'"></span>
            <span class="flex-1 leading-relaxed" x-text="alert.message"></span>
            <button type="button" @click="alert.message = ''" class="text-slate-400 hover:text-slate-700">
                <span class="material-symbols-outlined text-sm">close</span>
            </button>
        </div>

        @if ($errors->any())
            <div class="bg-rose-50 border border-rose-200 rounded-2xl p-3 text-xs font-bold text-rose-800 space-y-1">
                @foreach ($errors->all() as $err)
                    <div class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-sm text-rose-600 shrink-0">error</span>
                        <span>{{ $err }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- 1. بطاقة إضافة / تعديل طرد سريع --}}
        <section class="bg-white border border-slate-200 rounded-2xl p-4 shadow-2xs space-y-3">
            <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-2.5">
                <div class="flex items-center gap-2">
                    <span class="w-8 h-8 rounded-xl bg-primary text-secondary flex items-center justify-center">
                        <span class="material-symbols-outlined text-lg" x-text="editingIndex !== null ? 'edit' : 'add_box'"></span>
                    </span>
                    <div>
                        <h2 class="text-xs sm:text-sm font-extrabold text-slate-900"
                            x-text="editingIndex !== null ? 'تعديل بيانات الطرد #' + (editingIndex + 1) : 'إضافة طرد إلى الكشف'"></h2>
                        <p class="text-[10px] text-slate-400">تُحفظ المسودة تلقائياً على جهازك حتى إرسال الكشف</p>
                    </div>
                </div>

                <span class="px-2 py-0.5 rounded-lg bg-slate-100 border border-slate-200 font-mono text-[10px] font-black text-primary dir-ltr">
                    {{ $link->receipt_prefix }}*
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                <div>
                    <label class="block text-[11px] font-extrabold text-slate-700 mb-1">
                        اسم المستلم <span class="text-rose-600">*</span>
                    </label>
                    <input type="text"
                           x-ref="recipientNameInput"
                           x-model="draftParcel.recipient_name"
                           maxlength="255"
                           placeholder="الاسم الثلاثي للمستلم"
                           class="w-full rounded-xl border border-slate-300 bg-slate-50 focus:bg-white focus:border-secondary px-3 py-2.5 text-xs font-bold text-slate-800 outline-none">
                </div>

                <div>
                    <label class="block text-[11px] font-extrabold text-slate-700 mb-1">
                        رقم جوال المستلم <span class="text-rose-600">*</span>
                    </label>
                    <input type="tel"
                           x-model="draftParcel.recipient_phone"
                           @input="draftParcel.recipient_phone = sanitizePhoneInput(draftParcel.recipient_phone)"
                           inputmode="numeric"
                           dir="ltr"
                           maxlength="9"
                           placeholder="77XXXXXXX"
                           :class="draftParcel.recipient_phone.length > 0 && !isValidYemeniPhone(draftParcel.recipient_phone)
                               ? 'border-rose-400 bg-rose-50/40'
                               : 'border-slate-300 bg-slate-50'"
                           class="w-full rounded-xl border focus:bg-white focus:border-secondary px-3 py-2.5 text-xs font-mono font-black text-slate-800 outline-none text-left">
                    <p x-show="draftParcel.recipient_phone.length > 0 && !isValidYemeniPhone(draftParcel.recipient_phone)"
                       x-cloak
                       class="text-[10px] font-bold text-rose-600 mt-0.5">
                        يجب أن يتكون من 9 أرقام ويبدأ بـ 70، 71، 73، 77، أو 78
                    </p>
                </div>
            </div>

            {{-- حالة الدفع + المبلغ --}}
            <div class="grid grid-cols-2 gap-2.5">
                <div>
                    <label class="block text-[11px] font-extrabold text-slate-700 mb-1">حالة الدفع <span class="text-rose-600">*</span></label>
                    <div class="grid grid-cols-2 gap-1 bg-slate-100 p-1 rounded-xl">
                        <button type="button"
                                @click="draftParcel.payment_type = 'paid'"
                                :class="draftParcel.payment_type === 'paid'
                                    ? 'bg-emerald-600 text-white shadow-2xs font-extrabold'
                                    : 'text-slate-600 font-bold'"
                                class="py-2 rounded-lg text-xs transition-all">
                            محاسب
                        </button>
                        <button type="button"
                                @click="draftParcel.payment_type = 'deferred'"
                                :class="draftParcel.payment_type === 'deferred'
                                    ? 'bg-amber-500 text-white shadow-2xs font-extrabold'
                                    : 'text-slate-600 font-bold'"
                                class="py-2 rounded-lg text-xs transition-all">
                            آجل
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-extrabold text-slate-700 mb-1">
                        <span>المبلغ (ر.ي)</span>
                        <span x-show="draftParcel.payment_type === 'deferred'" class="text-rose-600">*</span>
                        <span x-show="draftParcel.payment_type === 'paid'" x-cloak class="text-[10px] font-bold text-slate-400">(اختياري)</span>
                    </label>
                    <input type="text"
                           x-model="draftParcel.cod_amount"
                           @input="draftParcel.cod_amount = normalizeNumberInput(draftParcel.cod_amount)"
                           inputmode="decimal"
                           dir="ltr"
                           :placeholder="draftParcel.payment_type === 'deferred' ? 'أدخل مبلغ الآجل (إلزامي)' : 'اختياري للمحاسب'"
                           :class="draftParcel.payment_type === 'deferred' ? 'border-amber-300 focus:border-amber-500' : 'border-slate-300 focus:border-secondary'"
                           class="w-full rounded-xl border bg-slate-50 focus:bg-white px-3 py-2.5 text-xs font-mono font-black text-slate-800 outline-none text-left">
                </div>
            </div>

            {{-- نوع الطرد + رقم السند الدفتري الاختياري --}}
            <div class="grid grid-cols-2 gap-2.5">
                <div>
                    <label class="block text-[11px] font-extrabold text-slate-700 mb-1">نوع الطرد / الوصف</label>
                    <input type="text"
                           x-model="draftParcel.package_type"
                           maxlength="255"
                           placeholder="كرتون، أمانة، ظرف..."
                           class="w-full rounded-xl border border-slate-300 bg-slate-50 focus:bg-white focus:border-secondary px-3 py-2 text-xs font-bold text-slate-800 outline-none">
                </div>

                <div>
                    <label class="block text-[11px] font-extrabold text-slate-700 mb-1">رقم السند الدفتري (اختياري)</label>
                    <div class="flex items-center rounded-xl border border-slate-300 bg-slate-50 focus-within:bg-white focus-within:border-secondary overflow-hidden dir-ltr">
                        <span class="px-2 py-2 bg-slate-200/70 text-[11px] font-mono font-black text-primary select-none">{{ $link->receipt_prefix }}</span>
                        <input type="text"
                               x-model="draftParcel.receipt_number"
                               @input="draftParcel.receipt_number = normalizeReceiptSuffix(draftParcel.receipt_number)"
                               maxlength="25"
                               placeholder="تلقائي"
                               class="w-full px-2 py-2 text-xs font-mono font-bold text-slate-800 bg-transparent outline-none text-left">
                    </div>
                </div>
            </div>

            {{-- حقول إضافية اختيارية (المرسل / الملاحظات) --}}
            <div x-data="{ moreOpen: false }" class="pt-0.5">
                <button type="button"
                        @click="moreOpen = !moreOpen"
                        class="text-[11px] font-bold text-slate-500 hover:text-primary inline-flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm" x-text="moreOpen ? 'expand_less' : 'expand_more'"></span>
                    <span>بيانات المرسل وملاحظات إضافية (اختياري)</span>
                </button>

                <div x-show="moreOpen || draftParcel.sender_name || draftParcel.sender_phone || draftParcel.notes"
                     x-cloak
                     class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 mt-2 pt-2 border-t border-slate-100">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-600 mb-1">اسم المرسل (اختياري)</label>
                        <input type="text"
                               x-model="draftParcel.sender_name"
                               maxlength="255"
                               placeholder="اسم المرسل"
                               class="w-full rounded-xl border border-slate-300 bg-slate-50 focus:bg-white px-3 py-2 text-xs font-bold text-slate-800 outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-600 mb-1">جوال المرسل (اختياري)</label>
                        <input type="tel"
                               x-model="draftParcel.sender_phone"
                               @input="draftParcel.sender_phone = sanitizePhoneInput(draftParcel.sender_phone)"
                               inputmode="numeric"
                               dir="ltr"
                               maxlength="9"
                               placeholder="77XXXXXXX"
                               class="w-full rounded-xl border border-slate-300 bg-slate-50 focus:bg-white px-3 py-2 text-xs font-mono font-bold text-slate-800 outline-none text-left">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-[10px] font-bold text-slate-600 mb-1">ملاحظات على الطرد (اختياري)</label>
                        <input type="text"
                               x-model="draftParcel.notes"
                               maxlength="1000"
                               placeholder="قابل للكسر، يُسلّم يداً بيد..."
                               class="w-full rounded-xl border border-slate-300 bg-slate-50 focus:bg-white px-3 py-2 text-xs font-bold text-slate-800 outline-none">
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2 pt-1">
                <button type="button"
                        @click="saveParcelToList()"
                        class="flex-1 py-2.5 rounded-xl bg-primary hover:bg-slate-800 text-secondary text-xs font-extrabold inline-flex items-center justify-center gap-1.5 shadow-xs transition-colors">
                    <span class="material-symbols-outlined text-base" x-text="editingIndex !== null ? 'check' : 'add_circle'"></span>
                    <span x-text="editingIndex !== null ? 'تحديث الطرد في القائمة' : 'إضافة الطرد إلى القائمة'"></span>
                </button>

                <button type="button"
                        x-show="editingIndex !== null"
                        x-cloak
                        @click="cancelEditParcel()"
                        class="px-3.5 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold">
                    إلغاء
                </button>
            </div>
        </section>

        {{-- 2. قائمة الطرود المضافة حالياً في المسودة --}}
        <section class="bg-white border border-slate-200 rounded-2xl p-4 shadow-2xs space-y-3">
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-lg text-primary">inventory_2</span>
                    <h3 class="text-xs sm:text-sm font-extrabold text-slate-900">
                        طرود الكشف الحالي (<span class="font-mono text-primary" x-text="parcels.length"></span>)
                    </h3>
                </div>

                <button type="button"
                        x-show="parcels.length > 0"
                        x-cloak
                        @click="clearDraftConfirm()"
                        class="text-[11px] font-bold text-rose-600 hover:text-rose-700 inline-flex items-center gap-0.5">
                    <span class="material-symbols-outlined text-sm">delete_sweep</span>
                    <span>تفريغ المسودة</span>
                </button>
            </div>

            <template x-if="parcels.length === 0">
                <div class="rounded-2xl bg-slate-50 border border-dashed border-slate-200 p-6 text-center space-y-1">
                    <span class="material-symbols-outlined text-2xl text-slate-400">inbox</span>
                    <p class="text-xs font-bold text-slate-500">لم تُضف أي طرود بعد. أضف الطرود أعلاه لتكوين الكشف.</p>
                </div>
            </template>

            <div x-show="parcels.length > 0" class="space-y-2">
                <template x-for="(p, idx) in parcels" :key="p._uid">
                    <div class="rounded-xl border border-slate-200/90 bg-slate-50/60 p-3 flex items-start justify-between gap-2 text-xs">
                        <div class="min-w-0 space-y-1">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="px-1.5 py-0.5 rounded bg-white border border-slate-200 font-mono text-[10px] font-black text-primary dir-ltr"
                                      x-text="p.receipt_number ? (prefix + p.receipt_number) : (prefix + 'تلقائي')"></span>
                                <span class="font-extrabold text-slate-900" x-text="p.recipient_name"></span>
                                <span class="font-mono text-[11px] text-slate-500 dir-ltr" x-text="p.recipient_phone"></span>
                            </div>
                            <div class="flex items-center gap-2 flex-wrap text-[11px]">
                                <span class="text-slate-600 font-bold" x-text="p.package_type || 'طرد'"></span>
                                <span class="px-2 py-0.5 rounded-md border text-[10px] font-extrabold"
                                      :class="p.payment_type === 'deferred'
                                          ? 'bg-amber-50 text-amber-800 border-amber-200'
                                          : 'bg-emerald-50 text-emerald-700 border-emerald-200'"
                                      x-text="p.payment_type === 'deferred'
                                          ? ('آجل: ' + formatMoney(p.cod_amount) + ' ر.ي')
                                          : (Number(p.cod_amount) > 0 ? ('محاسب: ' + formatMoney(p.cod_amount) + ' ر.ي') : 'محاسب')"></span>
                            </div>
                            <template x-if="p.notes">
                                <p class="text-[10px] text-slate-500" x-text="'ملاحظة: ' + p.notes"></p>
                            </template>
                        </div>

                        <div class="flex items-center gap-1 shrink-0">
                            <button type="button"
                                    @click="editParcelAt(idx)"
                                    title="تعديل"
                                    class="w-7 h-7 rounded-lg bg-white border border-slate-200 hover:border-primary text-slate-600 hover:text-primary flex items-center justify-center">
                                <span class="material-symbols-outlined text-sm">edit</span>
                            </button>
                            <button type="button"
                                    @click="removeParcelAt(idx)"
                                    title="حذف"
                                    class="w-7 h-7 rounded-lg bg-rose-50 border border-rose-200 hover:bg-rose-100 text-rose-600 flex items-center justify-center">
                                <span class="material-symbols-outlined text-sm">delete</span>
                            </button>
                        </div>
                    </div>
                </template>

                {{-- ملخص مجاميع المسودة --}}
                <div class="grid grid-cols-3 gap-1.5 pt-2 text-center">
                    <div class="bg-emerald-50 border border-emerald-200/70 rounded-xl py-1.5 px-1">
                        <p class="text-[9px] font-bold text-emerald-700">المحاسب</p>
                        <p class="text-xs font-mono font-black text-emerald-800" x-text="formatMoney(totals.paid)"></p>
                    </div>
                    <div class="bg-amber-50 border border-amber-200/70 rounded-xl py-1.5 px-1">
                        <p class="text-[9px] font-bold text-amber-700">الآجل</p>
                        <p class="text-xs font-mono font-black text-amber-800" x-text="formatMoney(totals.deferred)"></p>
                    </div>
                    <div class="bg-primary text-white rounded-xl py-1.5 px-1">
                        <p class="text-[9px] font-bold text-slate-300">الإجمالي</p>
                        <p class="text-xs font-mono font-black text-secondary" x-text="formatMoney(totals.grand)"></p>
                    </div>
                </div>
            </div>
        </section>

        {{-- 3. بيانات السائق واعتماد الإرسال --}}
        <section class="bg-white border-2 border-primary/15 rounded-2xl p-4 shadow-xs space-y-3">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-xl text-secondary">local_shipping</span>
                <h3 class="text-xs sm:text-sm font-extrabold text-slate-900">بيانات السائق واعتماد الكشف</h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                <div>
                    <label class="block text-[11px] font-extrabold text-slate-700 mb-1">
                        اسم السائق <span class="text-rose-600">*</span>
                    </label>
                    <input type="text"
                           x-model="driverName"
                           @input="persistDraft()"
                           maxlength="120"
                           placeholder="اسم السائق الناقل للكشف"
                           class="w-full rounded-xl border border-slate-300 bg-slate-50 focus:bg-white focus:border-secondary px-3 py-2.5 text-xs font-bold text-slate-800 outline-none">
                </div>

                <div>
                    <label class="block text-[11px] font-extrabold text-slate-700 mb-1">
                        رقم جوال السائق <span class="text-rose-600">*</span>
                    </label>
                    <input type="tel"
                           x-model="driverPhone"
                           @input="driverPhone = sanitizePhoneInput(driverPhone); persistDraft()"
                           inputmode="numeric"
                           dir="ltr"
                           maxlength="9"
                           placeholder="77XXXXXXX"
                           :class="driverPhone.length > 0 && !isValidYemeniPhone(driverPhone)
                               ? 'border-rose-400 bg-rose-50/40'
                               : 'border-slate-300 bg-slate-50'"
                           class="w-full rounded-xl border focus:bg-white focus:border-secondary px-3 py-2.5 text-xs font-mono font-black text-slate-800 outline-none text-left">
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-600 mb-1">ملاحظة عامة على الكشف (اختياري)</label>
                <input type="text"
                       x-model="shipmentNotes"
                       @input="persistDraft()"
                       maxlength="1000"
                       placeholder="رقم اللوحة، وقت التحرك..."
                       class="w-full rounded-xl border border-slate-300 bg-slate-50 focus:bg-white focus:border-secondary px-3 py-2 text-xs font-bold text-slate-800 outline-none">
            </div>

            <button type="button"
                    @click="submitShipment()"
                    :disabled="isSubmitting || parcels.length === 0"
                    class="w-full py-3.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-extrabold flex items-center justify-center gap-2 shadow-sm disabled:opacity-50 transition-colors">
                <span x-show="!isSubmitting" class="material-symbols-outlined text-lg">send</span>
                <span x-show="isSubmitting" x-cloak class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                <span x-text="isSubmitting ? 'جاري اعتماد وإرسال الكشف...' : ('اعتماد وإرسال الكشف (' + parcels.length + ' طرد)')"></span>
            </button>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        function externalPortalShipmentForm(config) {
            const storageKey = 'mursal_partner_draft_' + config.token;

            function makeUuid() {
                if (window.crypto && typeof window.crypto.randomUUID === 'function') {
                    return window.crypto.randomUUID();
                }
                return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                    const r = Math.random() * 16 | 0;
                    const v = c === 'x' ? r : (r & 0x3 | 0x8);
                    return v.toString(16);
                });
            }

            function emptyParcel() {
                return {
                    recipient_name: '',
                    recipient_phone: '',
                    sender_name: '',
                    sender_phone: '',
                    payment_type: 'paid',
                    cod_amount: '',
                    package_type: 'طرد',
                    receipt_number: '',
                    notes: '',
                };
            }

            return {
                prefix: config.prefix || '',
                idempotencyKey: makeUuid(),
                driverName: '',
                driverPhone: '',
                shipmentNotes: '',
                parcels: [],
                draftParcel: emptyParcel(),
                editingIndex: null,
                isSubmitting: false,
                alert: { type: 'error', message: '' },

                init() {
                    try {
                        const raw = localStorage.getItem(storageKey);
                        if (raw) {
                            const parsed = JSON.parse(raw);
                            if (parsed && typeof parsed === 'object') {
                                this.idempotencyKey = parsed.idempotency_key || makeUuid();
                                this.driverName = parsed.driver_name || '';
                                this.driverPhone = parsed.driver_phone || '';
                                this.shipmentNotes = parsed.notes || '';
                                if (Array.isArray(parsed.parcels)) {
                                    this.parcels = parsed.parcels.map(p => ({
                                        ...p,
                                        _uid: p._uid || makeUuid(),
                                    }));
                                }
                            }
                        }
                    } catch (e) {}
                },

                persistDraft() {
                    try {
                        localStorage.setItem(storageKey, JSON.stringify({
                            idempotency_key: this.idempotencyKey,
                            driver_name: this.driverName,
                            driver_phone: this.driverPhone,
                            notes: this.shipmentNotes,
                            parcels: this.parcels,
                        }));
                    } catch (e) {}
                },

                clearDraftStorage() {
                    try {
                        localStorage.removeItem(storageKey);
                    } catch (e) {}
                },

                clearDraftConfirm() {
                    if (!confirm('هل تريد تفريغ جميع الطرود من المسودة الحالية؟')) return;
                    this.parcels = [];
                    this.editingIndex = null;
                    this.draftParcel = emptyParcel();
                    this.idempotencyKey = makeUuid();
                    this.persistDraft();
                },

                normalizeDigits(val) {
                    const ar = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
                    const fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
                    return String(val || '')
                        .replace(/[٠-٩]/g, d => ar.indexOf(d))
                        .replace(/[۰-۹]/g, d => fa.indexOf(d));
                },

                sanitizePhoneInput(val) {
                    let digits = this.normalizeDigits(val).replace(/\D/g, '');
                    if (digits.length === 12 && digits.startsWith('9677')) {
                        digits = digits.slice(3);
                    } else if (digits.length === 14 && digits.startsWith('009677')) {
                        digits = digits.slice(5);
                    }
                    return digits.slice(0, 9);
                },

                isValidYemeniPhone(phone) {
                    return /^7[01378]\d{7}$/.test(String(phone || ''));
                },

                normalizeNumberInput(val) {
                    return this.normalizeDigits(val).replace(/[^0-9.]/g, '');
                },

                normalizeReceiptSuffix(val) {
                    let clean = this.normalizeDigits(val).trim().replace(/^#/, '').replace(/\s+/g, '');
                    const upPrefix = String(this.prefix || '').toUpperCase();
                    if (upPrefix && clean.toUpperCase().startsWith(upPrefix)) {
                        clean = clean.slice(upPrefix.length);
                    }
                    return clean;
                },

                formatMoney(n) {
                    return Number(n || 0).toLocaleString('en-US');
                },

                get totals() {
                    let paid = 0, deferred = 0;
                    this.parcels.forEach(p => {
                        const amt = Number(p.cod_amount || 0);
                        if (p.payment_type === 'deferred') deferred += amt;
                        else paid += amt;
                    });
                    return { paid, deferred, grand: paid + deferred };
                },

                showError(msg) {
                    this.alert = { type: 'error', message: msg };
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },

                saveParcelToList() {
                    this.alert.message = '';
                    const recName = String(this.draftParcel.recipient_name || '').trim();
                    const recPhone = this.sanitizePhoneInput(this.draftParcel.recipient_phone);
                    const sPhone = this.sanitizePhoneInput(this.draftParcel.sender_phone);
                    const isDeferred = this.draftParcel.payment_type === 'deferred';
                    const rawCodStr = String(this.draftParcel.cod_amount ?? '').trim();
                    const codNum = rawCodStr === '' ? 0 : Number(rawCodStr);

                    if (!recName) {
                        this.showError('يرجى إدخال اسم المستلم.');
                        return;
                    }
                    if (!this.isValidYemeniPhone(recPhone)) {
                        this.showError('رقم جوال المستلم غير صالح. يجب أن يتكون من 9 أرقام ويبدأ بـ 70، 71، 73، 77، أو 78.');
                        return;
                    }
                    if (sPhone !== '' && !this.isValidYemeniPhone(sPhone)) {
                        this.showError('رقم جوال المرسل غير صالح. يجب أن يتكون من 9 أرقام ويبدأ بـ 70، 71، 73، 77، أو 78.');
                        return;
                    }
                    if (isDeferred && (rawCodStr === '' || Number.isNaN(codNum) || codNum <= 0)) {
                        this.showError('يرجى إدخال مبلغ الطرد لأن حالة الدفع (آجل).');
                        return;
                    }
                    if (!isDeferred && (Number.isNaN(codNum) || codNum < 0)) {
                        this.showError('يرجى إدخال مبلغ صحيح أو ترك الحقل فارغاً للمحاسب.');
                        return;
                    }
                    if (this.editingIndex === null && this.parcels.length >= 100) {
                        this.showError('الحد الأقصى للكشف الواحد هو 100 طرد.');
                        return;
                    }

                    const entry = {
                        _uid: this.editingIndex !== null ? this.parcels[this.editingIndex]._uid : makeUuid(),
                        recipient_name: recName,
                        recipient_phone: recPhone,
                        sender_name: String(this.draftParcel.sender_name || '').trim(),
                        sender_phone: sPhone,
                        payment_type: isDeferred ? 'deferred' : 'paid',
                        cod_amount: codNum,
                        package_type: String(this.draftParcel.package_type || '').trim() || 'طرد',
                        receipt_number: this.normalizeReceiptSuffix(this.draftParcel.receipt_number),
                        notes: String(this.draftParcel.notes || '').trim(),
                    };

                    if (this.editingIndex !== null) {
                        this.parcels.splice(this.editingIndex, 1, entry);
                        this.editingIndex = null;
                    } else {
                        this.parcels.push(entry);
                    }

                    this.draftParcel = emptyParcel();
                    this.persistDraft();
                    this.$nextTick(() => this.$refs.recipientNameInput?.focus());
                },

                editParcelAt(index) {
                    const p = this.parcels[index];
                    if (!p) return;
                    this.editingIndex = index;
                    const hasPositiveAmount = Number(p.cod_amount || 0) > 0;
                    this.draftParcel = {
                        recipient_name: p.recipient_name || '',
                        recipient_phone: p.recipient_phone || '',
                        sender_name: p.sender_name || '',
                        sender_phone: p.sender_phone || '',
                        payment_type: p.payment_type || 'paid',
                        cod_amount: (p.payment_type === 'deferred' || hasPositiveAmount) ? String(p.cod_amount ?? '') : '',
                        package_type: p.package_type || 'طرد',
                        receipt_number: p.receipt_number || '',
                        notes: p.notes || '',
                    };
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },

                cancelEditParcel() {
                    this.editingIndex = null;
                    this.draftParcel = emptyParcel();
                },

                removeParcelAt(index) {
                    this.parcels.splice(index, 1);
                    if (this.editingIndex === index) {
                        this.cancelEditParcel();
                    } else if (this.editingIndex !== null && this.editingIndex > index) {
                        this.editingIndex--;
                    }
                    this.persistDraft();
                },

                async submitShipment() {
                    if (this.isSubmitting) return;
                    this.alert.message = '';

                    if (this.parcels.length === 0) {
                        this.showError('يرجى إضافة طرد واحد على الأقل قبل إرسال الكشف.');
                        return;
                    }

                    const dName = String(this.driverName || '').trim();
                    const dPhone = this.sanitizePhoneInput(this.driverPhone);

                    if (!dName) {
                        this.showError('يرجى إدخال اسم السائق.');
                        return;
                    }
                    if (!this.isValidYemeniPhone(dPhone)) {
                        this.showError('رقم جوال السائق غير صالح. يجب أن يتكون من 9 أرقام ويبدأ بـ 70، 71، 73، 77، أو 78.');
                        return;
                    }

                    this.isSubmitting = true;
                    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

                    try {
                        const res = await fetch(config.submitUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': csrf,
                            },
                            body: JSON.stringify({
                                idempotency_key: this.idempotencyKey,
                                driver_name: dName,
                                driver_phone: dPhone,
                                notes: String(this.shipmentNotes || '').trim(),
                                parcels: this.parcels.map(p => ({
                                    recipient_name: p.recipient_name,
                                    recipient_phone: p.recipient_phone,
                                    sender_name: p.sender_name,
                                    sender_phone: p.sender_phone,
                                    payment_type: p.payment_type,
                                    cod_amount: p.cod_amount,
                                    package_type: p.package_type,
                                    receipt_number: p.receipt_number,
                                    notes: p.notes,
                                })),
                            }),
                        });

                        const data = await res.json().catch(() => ({}));

                        if (!res.ok || !data.ok) {
                            this.showError(data.message || 'تعذر إرسال الكشف، يرجى مراجعة البيانات والمحاولة مجدداً.');
                            this.isSubmitting = false;
                            return;
                        }

                        // مسح المسودة فقط بعد تأكيد حفظ الكشف بنجاح في الخادم
                        this.clearDraftStorage();
                        window.location.href = data.redirect_url;
                    } catch (err) {
                        this.showError('تعذر الاتصال بالخادم، بيانات المسودة محفوظة على جهازك—تحقق من الإنترنت وأعد المحاولة.');
                        this.isSubmitting = false;
                    }
                }
            };
        }
    </script>
@endpush
