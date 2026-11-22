@extends('office.layouts.app')

@section('title', 'الشحنات المرسلة')

@section('shell-class', 'bg-slate-50')

@section('header-search')
    <form method="GET"
          action="{{ route('office.shipments.sent.index') }}"
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
                  window.dispatchEvent(new CustomEvent('sent-search-change', { detail: { q: this.q } }));
              },
              clear() {
                  this.q = '';
                  this.emitSearch();
              }
          }"
          @submit.prevent="emitSearch()"
          class="mt-3 relative">
        @if ($statusFilter !== 'all')
            <input type="hidden" name="status" value="{{ $statusFilter }}">
        @endif
        <input type="text"
               name="q"
               x-model="q"
               @input="onInput()"
               dir="rtl"
               autocomplete="off"
               placeholder="بحث برقم الكشف، اسم السائق، أو رقم السائق..."
               class="w-full bg-slate-800 text-white placeholder-slate-400 text-xs rounded-xl pr-9 pl-8 py-2.5 outline-none border border-slate-700 focus:border-secondary transition-all">
        <span
            class="material-symbols-outlined absolute right-2.5 top-2.5 text-slate-400 text-lg pointer-events-none">search</span>
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
    <div class="flex-1 flex flex-col"
         @open-create-shipment-modal.window="openCreateModal()"
         @sent-search-change.window="handleSearchEvent($event.detail.q)"
         x-data="sentShipmentsPage({
             baseUrl: @js(route('office.shipments.sent.index')),
             initialStatus: @js($statusFilter),
             initialSearch: @js($search),
             initialCounts: @js($counts),
             availableParcels: @js($availableParcels),
             lookupUrl: @js(route('office.shipments.sent.lookup')),
             storeUrl: @js(route('office.shipments.sent.store')),
             senderOfficeLabel: @js($office->branch_name ? $office->name . ' - ' . $office->branch_name : $office->name)
         })">

        {{-- شريط تبويبات التصفية العلوي الموحّد (مطابق تماماً لشاشة الطرود الواردة) --}}
        @php
            $sentTabs = [
                ['key' => 'all',                'label' => 'الكل'],
                ['key' => 'draft',              'label' => 'في الانتظار'],
                ['key' => 'in_transit',         'label' => 'في الطريق'],
                ['key' => 'partially_received', 'label' => 'تسليم جزئي'],
                ['key' => 'received',           'label' => 'مكتملة'],
            ];
        @endphp
        <nav class="flex overflow-x-auto no-scrollbar border-b border-slate-100 bg-white sticky top-[125px] z-20 text-xs font-bold text-center">
            @foreach ($sentTabs as $tabItem)
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
            {{-- قائمة الشحنات المرسلة (تُحدث لحظياً عبر AJAX) --}}
            <div x-ref="cardsContainer" :class="isFetching ? 'opacity-60 transition-opacity' : ''">
                @include('office.shipments.partials.sent-cards-list', ['shipments' => $shipments])
            </div>

        {{-- مودال إنشاء كشف شحنة جديد بالمسح بالباركود (محسّن لكيبورد الموبايل بـ visualViewport وبدون backdrop-blur للأداء) --}}
        <div x-show="createModalOpen"
             x-cloak
             @keydown.escape.window="closeCreateModal()"
             @click.self="closeCreateModal()"
             :style="{ height: viewportHeight + 'px', top: viewportTop + 'px' }"
             class="fixed left-0 right-0 z-50 bg-slate-950/60 flex items-start justify-center pt-1.5 px-1.5 sm:items-center sm:p-4">

            <div :style="{ maxHeight: Math.max(260, viewportHeight - 12) + 'px' }"
                 class="bg-white w-full max-w-lg rounded-2xl flex flex-col shadow-2xl border border-slate-200 overflow-hidden">

                {{-- هيدر المودال المثبّت --}}
                <div class="py-3 px-4 bg-primary text-white flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-secondary text-xl">qr_code_scanner</span>
                        <div>
                            <h3 class="text-sm font-extrabold leading-tight">إنشاء كشف شحنة جديد</h3>
                            <p class="text-[10px] text-slate-300" x-text="'الفرع المرسل: ' + senderOfficeLabel"></p>
                        </div>
                    </div>
                    <button type="button" @click="closeCreateModal()" class="text-slate-300 hover:text-white p-1">
                        <span class="material-symbols-outlined text-lg">close</span>
                    </button>
                </div>

                {{-- نموذج إنشاء الشحنة بسلسلة تمرير Flex سليمة (min-h-0) --}}
                <form @submit.prevent="submitShipment()" class="flex flex-col flex-1 min-h-0">

                    {{-- جسم المودال القابل للتمرير فوق الكيبورد --}}
                    <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain touch-pan-y px-4 pt-3 pb-6 space-y-3"
                         style="-webkit-overflow-scrolling: touch;"
                         @focusin="if ($event.target.matches('input, textarea, select')) { setTimeout(() => $event.target.scrollIntoView({ behavior: 'smooth', block: 'center' }), 180); }">

                    {{-- 1. بيانات السائق --}}
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">اسم السائق <span
                                    class="text-rose-500">*</span></label>
                            <input type="text" x-model="driverName" placeholder="اسم السائق"
                                class="w-full py-2 px-3 text-xs font-bold text-slate-800 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white outline-none focus:border-secondary">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">رقم السائق <span
                                    class="text-rose-500">*</span></label>
                            <input type="tel" x-model="driverPhone" @input="driverPhone = sanitizePhone(driverPhone)"
                                dir="ltr" inputmode="numeric" maxlength="9" placeholder="7XXXXXXXX"
                                class="w-full py-2 px-3 text-xs font-mono font-bold text-slate-800 rounded-xl border bg-slate-50 focus:bg-white outline-none focus:border-secondary"
                                :class="driverPhone.length > 0 && !isValidPhone(driverPhone) ? 'border-rose-300' :
                                    'border-slate-200'">
                        </div>
                    </div>

                    {{-- 2. منطقة مسح الباركود (كاميرا + قارئ خارجي / إدخال يدوي) --}}
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-3 space-y-2.5">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-extrabold text-primary flex items-center gap-1">
                                <span class="material-symbols-outlined text-base text-secondary">barcode_scanner</span>
                                <span>مسح باركود الطرود (مانع للتكرار)</span>
                            </span>
                            <button type="button" @click="toggleCamera()"
                                :class="cameraActive ? 'bg-rose-600 text-white' : 'bg-primary text-secondary'"
                                class="px-2.5 py-1.5 rounded-xl text-[11px] font-extrabold flex items-center gap-1 transition-colors">
                                <span class="material-symbols-outlined text-sm"
                                    x-text="cameraActive ? 'videocam_off' : 'photo_camera'"></span>
                                <span x-text="cameraActive ? 'إيقاف الكاميرا' : 'فتح الكاميرا'"></span>
                            </button>
                        </div>

                        {{-- نافذة الكاميرا --}}
                        <div x-show="cameraActive" x-cloak
                            class="rounded-xl overflow-hidden border border-slate-300 bg-black">
                            <div id="sent-shipment-qr-reader" class="w-full"></div>
                        </div>

                        {{-- حقل قارئ الباركود USB/Bluetooth أو إدخال رقم السند --}}
                        <div class="flex gap-1.5">
                            <input type="text" x-ref="scanInput" x-model="scanCodeInput"
                                @keydown.enter.prevent="handleScan(scanCodeInput)" dir="ltr" autocomplete="off"
                                placeholder="امسح بالباركود أو اكتب رقم السند ثم Enter"
                                class="flex-1 py-2 px-3 text-xs font-mono font-bold text-slate-800 rounded-xl border border-slate-300 bg-white outline-none focus:border-secondary">
                            <button type="button" @click="handleScan(scanCodeInput)" :disabled="isLookingUp"
                                class="px-3 py-2 rounded-xl bg-primary text-secondary text-xs font-extrabold hover:bg-slate-800 disabled:opacity-50 shrink-0">
                                <span x-text="isLookingUp ? '...' : 'إضافة'"></span>
                            </button>
                        </div>

                        {{-- شريط حالة آخر مسح (نجاح / تحذير تكرار / خطأ) --}}
                        <div x-show="scanFeedback.text" x-cloak
                            :class="{
                                'bg-emerald-50 border-emerald-200 text-emerald-800': scanFeedback.type === 'success',
                                'bg-amber-50 border-amber-300 text-amber-900': scanFeedback.type === 'duplicate',
                                'bg-rose-50 border-rose-200 text-rose-800': scanFeedback.type === 'error'
                            }"
                            class="px-2.5 py-2 rounded-xl border text-[11px] font-extrabold flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-base shrink-0"
                                x-text="scanFeedback.type === 'success' ? 'check_circle' : (scanFeedback.type === 'duplicate' ? 'block' : 'error')"></span>
                            <span x-text="scanFeedback.text"></span>
                        </div>
                    </div>

                    {{-- 3. المجاميع الحية والوجهات داخل الشحنة --}}
                    <div class="bg-primary text-white rounded-2xl p-3 space-y-2">
                        <div class="grid grid-cols-4 gap-1.5 text-center">
                            <div class="bg-white/10 rounded-xl py-1.5 px-1">
                                <p class="text-[9px] text-slate-300 font-bold">الطرود</p>
                                <p class="text-sm font-black text-white" x-text="selectedParcels.length"></p>
                            </div>
                            <div class="bg-white/10 rounded-xl py-1.5 px-1">
                                <p class="text-[9px] text-amber-300 font-bold">الآجل</p>
                                <p class="text-xs font-mono font-black text-amber-300"
                                    x-text="formatMoney(totals.deferred)"></p>
                            </div>
                            <div class="bg-white/10 rounded-xl py-1.5 px-1">
                                <p class="text-[9px] text-emerald-300 font-bold">المحاسب</p>
                                <p class="text-xs font-mono font-black text-emerald-300"
                                    x-text="formatMoney(totals.paid)"></p>
                            </div>
                            <div class="bg-secondary/20 border border-secondary/40 rounded-xl py-1.5 px-1">
                                <p class="text-[9px] text-secondary font-bold">الإجمالي</p>
                                <p class="text-xs font-mono font-black text-secondary" x-text="formatMoney(totals.grand)">
                                </p>
                            </div>
                        </div>

                        <template x-if="uniqueDestinations.length > 0">
                            <div class="flex items-center gap-1 flex-wrap pt-1 border-t border-white/10">
                                <span class="text-[10px] text-slate-300 font-bold ml-1">الوجهات:</span>
                                <template x-for="dest in uniqueDestinations" :key="dest">
                                    <span class="px-2 py-0.5 rounded-md bg-white/10 text-[10px] font-bold text-white"
                                        x-text="dest"></span>
                                </template>
                            </div>
                        </template>
                    </div>

                    {{-- 4. قائمة الطرود الممسوحة داخل الكشف --}}
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-extrabold text-slate-700">
                                الطرود المضمومة (<span x-text="selectedParcels.length"></span>)
                            </h4>
                            <button type="button" @click="showManualPicker = !showManualPicker"
                                class="text-[11px] font-extrabold text-sky-700 hover:underline flex items-center gap-0.5">
                                <span class="material-symbols-outlined text-sm">checklist</span>
                                <span
                                    x-text="showManualPicker ? 'إخفاء الاختيار اليدوي' : 'اختيار يدوي من المتاح (' + unaddedAvailable.length + ')'"></span>
                            </button>
                        </div>

                        {{-- اختيار يدوي بديل في حال تلف ملصق الباركود --}}
                        <div x-show="showManualPicker" x-cloak
                            class="bg-sky-50/70 border border-sky-200 rounded-xl p-2 max-h-40 overflow-y-auto space-y-1">
                            <template x-if="unaddedAvailable.length === 0">
                                <p class="text-[11px] text-slate-500 text-center py-2 font-bold">لا توجد طرود إضافية في
                                    الانتظار.</p>
                            </template>
                            <template x-for="p in unaddedAvailable" :key="p.id">
                                <button type="button" @click="addParcelObject(p)"
                                    class="w-full text-right bg-white hover:bg-sky-100/60 border border-slate-200 rounded-lg px-2.5 py-1.5 flex items-center justify-between gap-2 text-[11px]">
                                    <div class="min-w-0">
                                        <span class="font-mono font-black text-primary"
                                            x-text="'#' + p.receipt_number"></span>
                                        <span class="font-bold text-slate-700 mr-1" x-text="p.package_type"></span>
                                        <span class="text-slate-400 block truncate text-[10px]"
                                            x-text="p.destination + ' • ' + p.recipient_name"></span>
                                    </div>
                                    <span class="px-2 py-0.5 rounded-md bg-sky-600 text-white font-extrabold shrink-0">+
                                        ضم</span>
                                </button>
                            </template>
                        </div>

                        {{-- الطرود الممسوحة فعلياً --}}
                        <template x-if="selectedParcels.length === 0">
                            <div
                                class="border border-dashed border-slate-300 rounded-xl p-4 text-center text-[11px] text-slate-400 font-bold">
                                امسح باركود أول طرد لتبدأ بتجهيز الشحنة
                            </div>
                        </template>

                        <div class="space-y-1.5 max-h-48 overflow-y-auto">
                            <template x-for="(p, idx) in selectedParcels" :key="p.id">
                                <div
                                    class="bg-white border border-slate-200 rounded-xl px-2.5 py-2 flex items-center justify-between gap-2 text-xs">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span
                                                class="px-1.5 py-0.5 rounded bg-slate-100 font-mono text-[10px] font-black text-primary"
                                                x-text="'#' + p.receipt_number"></span>
                                            <span class="font-extrabold text-slate-800 truncate"
                                                x-text="p.package_type"></span>
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-extrabold"
                                                :class="p.payment_type === 'deferred' ? 'bg-amber-50 text-amber-800' :
                                                    'bg-emerald-50 text-emerald-700'"
                                                x-text="p.payment_label + ': ' + formatMoney(p.cod_amount)"></span>
                                        </div>
                                        <p class="text-[10px] text-slate-400 truncate mt-0.5"
                                            x-text="p.destination + ' • ' + p.recipient_name + ' (' + p.recipient_phone + ')'">
                                        </p>
                                    </div>
                                    <button type="button" @click="removeParcel(p.id)" title="إزالة من الشحنة"
                                        class="w-7 h-7 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
                                        <span class="material-symbols-outlined text-base">delete</span>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- 5. ملاحظات + خيار الشحن الفوري --}}
                    <div class="space-y-2">
                        <textarea x-model="notes" rows="2" placeholder="ملاحظات على الكشف (اختياري)..."
                            class="w-full py-2 px-3 text-xs font-bold text-slate-800 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white outline-none focus:border-secondary"></textarea>

                        <label class="flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer select-none">
                            <input type="checkbox" x-model="dispatchNow" class="w-4 h-4 accent-[#fe9d20] rounded">
                            <span>شحن الكشف فوراً (تحويل جميع الطرود إلى «في الطريق»)</span>
                        </label>
                    </div>
                </div>

                    {{-- فوتر ثابت لحفظ الكشف (يظل مرئياً دائماً فوق الكيبورد) --}}
                    <div class="shrink-0 border-t border-slate-100 px-4 py-2.5 bg-white flex items-center gap-2">
                        <button type="submit"
                            :disabled="submitting || selectedParcels.length === 0"
                            class="flex-1 py-2.5 px-4 rounded-xl bg-secondary hover:bg-amber-500 text-primary text-xs font-extrabold shadow-md shadow-secondary/20 flex items-center justify-center gap-1.5 transition-all disabled:opacity-50">
                            <span class="material-symbols-outlined text-base">check_circle</span>
                            <span
                                x-text="submitting ? 'جاري حفظ الكشف...' : (dispatchNow ? 'اعتماد وشحن الكشف' : 'حفظ كمسودة')"></span>
                        </button>
                        <button type="button" @click="closeCreateModal()"
                            class="py-2.5 px-4 rounded-xl border border-slate-200 text-slate-600 text-xs font-bold hover:bg-slate-50">
                            إغلاق
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- مودال تأكيد إلغاء كشف الشحنة (بدون backdrop-blur للأداء) --}}
        <div x-show="cancelModal.open"
             x-cloak
             @keydown.escape.window="closeCancelModal()"
             class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-3 sm:p-4">
            <div class="fixed inset-0 bg-slate-950/60" @click="closeCancelModal()"></div>

            <div class="relative w-full max-w-sm bg-white rounded-3xl p-5 shadow-2xl border border-slate-100 z-10 flex flex-col gap-4 text-right">
                <button type="button" @click="closeCancelModal()" :disabled="cancelModal.submitting"
                        title="إغلاق"
                        class="absolute left-4 top-4 w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-400 hover:text-slate-600 flex items-center justify-center transition-colors disabled:opacity-50">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>

                <div class="text-center pt-1">
                    <div class="w-14 h-14 mx-auto rounded-2xl bg-rose-50 border border-rose-100 text-rose-600 flex items-center justify-center shadow-inner mb-3">
                        <span class="material-symbols-outlined text-3xl">cancel</span>
                    </div>
                    <h3 class="text-base font-extrabold text-slate-800">إلغاء كشف الشحنة</h3>
                    <p class="text-xs text-slate-500 mt-1 leading-relaxed"
                       x-text="'سيتم إلغاء الكشف #' + (cancelModal.number || '') + ' وتنزيل جميع الطرود بداخله وإرجاعها إلى حالة (في الانتظار).'"></p>
                </div>

                <div class="bg-slate-50 rounded-2xl p-3 border border-slate-200/80 space-y-1.5 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">رقم الكشف:</span>
                        <span class="font-mono font-black text-rose-600" x-text="'#' + (cancelModal.number || '')"></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">السائق:</span>
                        <span class="font-bold text-slate-700" x-text="cancelModal.driver_name || '—'"></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">عدد الطرود:</span>
                        <span class="font-mono font-bold text-slate-700" x-text="(cancelModal.parcels_count || 0) + ' طرد'"></span>
                    </div>
                </div>

                <div class="bg-amber-50 border border-amber-200/80 text-amber-800 rounded-xl p-2.5 flex items-start gap-2 text-[11px] leading-snug">
                    <span class="material-symbols-outlined text-base text-amber-600 shrink-0 mt-0.5">warning</span>
                    <span>
                        <strong>تنبيه:</strong> ستختفي جميع طرود هذا الكشف من قائمة (في الطريق) لدى الفروع المستلمة وتعود تلقائياً إلى (في الانتظار).
                    </span>
                </div>

                <form method="POST" :action="cancelModal.url" @submit="cancelModal.submitting = true" class="flex flex-col gap-2 pt-1">
                    @csrf
                    <button type="submit" :disabled="cancelModal.submitting"
                            class="w-full py-3 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 active:scale-[0.98] text-white font-extrabold text-xs shadow-lg shadow-rose-600/25 flex items-center justify-center gap-1.5 transition-all disabled:opacity-50">
                        <span class="material-symbols-outlined text-base">cancel</span>
                        <span x-text="cancelModal.submitting ? 'جاري إلغاء الكشف...' : 'تأكيد إلغاء الكشف'"></span>
                    </button>
                    <button type="button" @click="closeCancelModal()" :disabled="cancelModal.submitting"
                            class="w-full py-2.5 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors disabled:opacity-50">
                        تراجع
                    </button>
                </form>
            </div>
        </div>
    </main>
    </div>
@endsection

@push('scripts')
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
        function sentShipmentsPage(config) {
            return {
                baseUrl: config.baseUrl || '/office/shipments/sent',
                activeStatus: String(config.initialStatus || 'all'),
                searchQuery: String(config.initialSearch || ''),
                counts: config.initialCounts || { all: 0, draft: 0, in_transit: 0, partially_received: 0, received: 0, cancelled: 0 },
                isFetching: false,
                _abortController: null,

                availableParcels: Array.isArray(config.availableParcels) ? config.availableParcels : [],
                lookupUrl: config.lookupUrl,
                storeUrl: config.storeUrl,
                senderOfficeLabel: config.senderOfficeLabel || '',

                createModalOpen: new URLSearchParams(window.location.search).get('create') === '1',
                cancelModal: {
                    open: false,
                    submitting: false,
                    number: '',
                    driver_name: '',
                    parcels_count: 0,
                    url: '#',
                },
                showManualPicker: false,
                driverName: '',
                driverPhone: '',
                notes: '',
                dispatchNow: true,
                selectedParcels: [],

                scanCodeInput: '',
                isLookingUp: false,
                submitting: false,
                scanFeedback: {
                    type: '',
                    text: ''
                },

                cameraActive: false,
                _qrScanner: null,
                _lastScannedCode: '',
                _lastScannedAt: 0,

                viewportHeight: window.visualViewport ? Math.round(window.visualViewport.height) : window.innerHeight,
                viewportTop: window.visualViewport ? Math.round(window.visualViewport.offsetTop) : 0,
                _vpRafId: null,
                _vpHandler: null,

                init() {
                    this._vpHandler = () => {
                        if (!this.createModalOpen) return;
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

                    if (this.createModalOpen) {
                        this.attachViewportListeners();
                    }
                },

                updateCounts(nextCounts) {
                    if (!nextCounts || typeof nextCounts !== 'object') return;
                    this.counts = {
                        all: Number(nextCounts.all ?? this.counts.all ?? 0),
                        draft: Number(nextCounts.draft ?? this.counts.draft ?? 0),
                        in_transit: Number(nextCounts.in_transit ?? this.counts.in_transit ?? 0),
                        partially_received: Number(nextCounts.partially_received ?? this.counts.partially_received ?? 0),
                        received: Number(nextCounts.received ?? this.counts.received ?? 0),
                        cancelled: Number(nextCounts.cancelled ?? this.counts.cancelled ?? 0),
                    };
                },

                setStatusFilter(targetStatus) {
                    this.activeStatus = String(targetStatus || 'all');
                    this.fetchFilteredList();
                },

                handleSearchEvent(q) {
                    this.searchQuery = String(q || '').trim();
                    this.fetchFilteredList();
                },

                async fetchFilteredList() {
                    if (this._abortController) {
                        this._abortController.abort();
                    }
                    this._abortController = new AbortController();

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
                            signal: this._abortController.signal,
                        });

                        if (!res.ok) throw new Error('Network error');
                        const data = await res.json();

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
                        if (err.name !== 'AbortError') {
                            console.warn('Sent shipments filter fetch failed:', err);
                        }
                    } finally {
                        this.isFetching = false;
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

                beep(type) {
                    try {
                        const AudioCtx = window.AudioContext || window.webkitAudioContext;
                        if (!AudioCtx) return;
                        const ctx = new AudioCtx();
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        osc.connect(gain);
                        gain.connect(ctx.destination);

                        if (type === 'success') {
                            osc.frequency.value = 920;
                            gain.gain.value = 0.14;
                            osc.start();
                            osc.stop(ctx.currentTime + 0.11);
                            if (navigator.vibrate) navigator.vibrate(50);
                        } else {
                            osc.frequency.value = 230;
                            osc.type = 'sawtooth';
                            gain.gain.value = 0.15;
                            osc.start();
                            osc.stop(ctx.currentTime + 0.24);
                            if (navigator.vibrate) navigator.vibrate([90, 40, 90]);
                        }
                    } catch (e) {}
                },

                setFeedback(type, text) {
                    this.scanFeedback = {
                        type,
                        text
                    };
                    if (text) {
                        const toastType = type === 'duplicate' ? 'warning' : (type === 'error' ? 'error' : 'success');
                        window.dispatchEvent(new CustomEvent('office-toast', {
                            detail: { message: text, type: toastType }
                        }));
                    }
                },

                sanitizePhone(str) {
                    const ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
                    const fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
                    let digits = String(str || '')
                        .replace(/[٠-٩]/g, d => ar.indexOf(d))
                        .replace(/[۰-۹]/g, d => fa.indexOf(d))
                        .replace(/\D/g, '');
                    if (digits.length === 12 && digits.startsWith('9677')) digits = digits.substring(3);
                    if (digits.length === 14 && digits.startsWith('009677')) digits = digits.substring(5);
                    return digits.substring(0, 9);
                },

                isValidPhone(phone) {
                    return /^7[01378]\d{7}$/.test(this.sanitizePhone(phone));
                },

                formatMoney(n) {
                    return Number(n || 0).toLocaleString('en-US');
                },

                get unaddedAvailable() {
                    const ids = new Set(this.selectedParcels.map(p => p.id));
                    return this.availableParcels.filter(p => !ids.has(p.id));
                },

                get totals() {
                    let paid = 0,
                        deferred = 0;
                    this.selectedParcels.forEach(p => {
                        const amt = Number(p.cod_amount || 0);
                        if (p.payment_type === 'deferred') deferred += amt;
                        else paid += amt;
                    });
                    return {
                        paid,
                        deferred,
                        grand: paid + deferred
                    };
                },

                get uniqueDestinations() {
                    const set = new Set();
                    this.selectedParcels.forEach(p => {
                        if (p.destination) set.add(p.destination);
                    });
                    return Array.from(set);
                },

                openCreateModal() {
                    this.createModalOpen = true;
                    this.attachViewportListeners();
                    this.scanFeedback = {
                        type: '',
                        text: ''
                    };
                    this.$nextTick(() => this.$refs.scanInput?.focus());
                },

                async closeCreateModal() {
                    await this.stopCamera();
                    this.createModalOpen = false;
                    this.detachViewportListeners();
                },

                openCancelModal(data) {
                    this.cancelModal = {
                        open: true,
                        submitting: false,
                        number: data?.number || '',
                        driver_name: data?.driver_name || '',
                        parcels_count: Number(data?.parcels_count || 0),
                        url: data?.url || '#',
                    };
                },

                closeCancelModal() {
                    if (this.cancelModal.submitting) return;
                    this.cancelModal.open = false;
                },

                printShipment(url) {
                    if (!url) return;
                    const fallbackUrl = url + (url.includes('?') ? '&' : '?') + 'auto_print=1';
                    try {
                        let iframe = document.getElementById('sent-shipment-print-iframe');
                        if (iframe) {
                            iframe.remove();
                        }
                        iframe = document.createElement('iframe');
                        iframe.id = 'sent-shipment-print-iframe';
                        iframe.setAttribute('aria-hidden', 'true');
                        iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden;';

                        iframe.onload = () => {
                            setTimeout(() => {
                                try {
                                    if (iframe.contentWindow) {
                                        iframe.contentWindow.focus();
                                        iframe.contentWindow.print();
                                    } else {
                                        window.open(fallbackUrl, '_blank');
                                    }
                                } catch (e) {
                                    window.open(fallbackUrl, '_blank');
                                }
                            }, 150);
                        };

                        iframe.onerror = () => {
                            window.open(fallbackUrl, '_blank');
                        };

                        document.body.appendChild(iframe);
                        iframe.src = url;
                    } catch (e) {
                        window.open(fallbackUrl, '_blank');
                    }
                },

                async toggleCamera() {
                    if (this.cameraActive) {
                        await this.stopCamera();
                    } else {
                        await this.startCamera();
                    }
                },

                async startCamera() {
                    if (typeof Html5Qrcode === 'undefined') {
                        this.setFeedback('error', 'تعذر تحميل مكتبة الكاميرا، تحقق من الاتصال بالإنترنت.');
                        return;
                    }
                    this.cameraActive = true;
                    this.$nextTick(async () => {
                        try {
                            this._qrScanner = new Html5Qrcode('sent-shipment-qr-reader');
                            await this._qrScanner.start({
                                    facingMode: 'environment'
                                }, {
                                    fps: 10,
                                    qrbox: {
                                        width: 230,
                                        height: 150
                                    }
                                },
                                (decodedText) => {
                                    const now = Date.now();
                                    if (decodedText === this._lastScannedCode && (now - this
                                            ._lastScannedAt) < 2200) {
                                        return;
                                    }
                                    this._lastScannedCode = decodedText;
                                    this._lastScannedAt = now;
                                    this.handleScan(decodedText);
                                },
                                () => {}
                            );
                        } catch (err) {
                            this.cameraActive = false;
                            this.setFeedback('error',
                                'تعذر فتح الكاميرا (تأكد من منح الصلاحية أو استخدم حقل القارئ اليدوي).');
                        }
                    });
                },

                async stopCamera() {
                    if (this._qrScanner) {
                        try {
                            await this._qrScanner.stop();
                            await this._qrScanner.clear();
                        } catch (e) {}
                        this._qrScanner = null;
                    }
                    this.cameraActive = false;
                },

                normalizeCode(raw) {
                    const ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
                    const fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
                    let clean = String(raw || '')
                        .replace(/[٠-٩]/g, d => ar.indexOf(d))
                        .replace(/[۰-۹]/g, d => fa.indexOf(d))
                        .trim();
                    const m = clean.match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i);
                    return m ? m[0].toLowerCase() : clean.replace(/^#/, '');
                },

                addParcelObject(parcel) {
                    if (this.selectedParcels.some(p => p.id === parcel.id)) {
                        this.beep('error');
                        this.setFeedback('duplicate', 'تم مسح هذا الطرد مسبقاً (سند #' + parcel.receipt_number +
                            ') — مُنع التكرار.');
                        return;
                    }
                    this.selectedParcels.unshift(parcel);
                    this.beep('success');
                    this.setFeedback('success', 'تمت إضافة الطرد: سند #' + parcel.receipt_number + ' (' + parcel
                        .destination + ')');
                },

                removeParcel(id) {
                    this.selectedParcels = this.selectedParcels.filter(p => p.id !== id);
                },

                async handleScan(rawCode) {
                    const code = this.normalizeCode(rawCode);
                    this.scanCodeInput = '';
                    if (!code) return;

                    // 1. فحص التكرار المحلي الفوري
                    const dup = this.selectedParcels.find(p => p.id.toLowerCase() === code || String(p
                        .receipt_number) === code);
                    if (dup) {
                        this.beep('error');
                        this.setFeedback('duplicate', 'تم مسح هذا الطرد مسبقاً (سند #' + dup.receipt_number +
                            ') — مُنع التكرار.');
                        return;
                    }

                    // 2. البحث الفوري في القائمة المحمّلة محلياً (صفر تأخير)
                    const localMatch = this.availableParcels.find(p => p.id.toLowerCase() === code || String(p
                        .receipt_number) === code);
                    if (localMatch) {
                        this.addParcelObject(localMatch);
                        return;
                    }

                    // 3. التحقق من السيرفر في حال لم يكن ضمن آخر 200 طرد
                    this.isLookingUp = true;
                    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
                    try {
                        const res = await fetch(this.lookupUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': token,
                            },
                            body: JSON.stringify({
                                code
                            })
                        });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || !data.ok || !data.parcel) {
                            this.beep('error');
                            this.setFeedback('error', data.message || 'لم يتم العثور على طرد مطابق.');
                            return;
                        }
                        this.addParcelObject(data.parcel);
                    } catch (e) {
                        this.beep('error');
                        this.setFeedback('error', 'تعذر الاتصال بالخادم لفحص الباركود.');
                    } finally {
                        this.isLookingUp = false;
                        this.$nextTick(() => this.$refs.scanInput?.focus());
                    }
                },

                async submitShipment() {
                    if (this.submitting) return;
                    if (!this.driverName.trim()) {
                        this.setFeedback('error', 'يرجى إدخال اسم السائق.');
                        return;
                    }
                    if (!this.isValidPhone(this.driverPhone)) {
                        this.setFeedback('error', 'يرجى إدخال رقم سائق يمني صحيح (9 أرقام).');
                        return;
                    }
                    if (this.selectedParcels.length === 0) {
                        this.setFeedback('error', 'يرجى مسح طرد واحد على الأقل.');
                        return;
                    }

                    this.submitting = true;
                    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';

                    try {
                        const res = await fetch(this.storeUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': token,
                            },
                            body: JSON.stringify({
                                driver_name: this.driverName.trim(),
                                driver_phone: this.sanitizePhone(this.driverPhone),
                                parcel_ids: this.selectedParcels.map(p => p.id),
                                dispatch_now: this.dispatchNow,
                                notes: this.notes.trim() || null,
                            })
                        });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || !data.ok) {
                            this.beep('error');
                            this.setFeedback('error', data.message || 'تعذر حفظ الشحنة.');
                            return;
                        }
                        await this.stopCamera();
                        if (data.redirect_url) {
                            window.location.href = data.redirect_url;
                        } else {
                            window.location.reload();
                        }
                    } catch (e) {
                        this.setFeedback('error', 'تعذر الاتصال بالخادم، يرجى المحاولة مجدداً.');
                    } finally {
                        this.submitting = false;
                    }
                }
            };
        }
    </script>
@endpush
