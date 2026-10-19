@extends('office.layouts.app')

@section('title', 'إعدادات الواتساب')

@section('shell-class', 'bg-slate-50')

@section('alpine', "whatsappSettings()")

@section('content')
<main class="flex-1 p-4 space-y-4 pb-8">

    {{-- عنوان الصفحة --}}
    <div class="flex items-center gap-3">
        <a href="{{ route('office.dashboard') }}"
           class="w-9 h-9 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-500 hover:text-primary hover:border-primary/30 transition-colors shadow-xs">
            <span class="material-symbols-outlined text-lg">arrow_forward</span>
        </a>
        <div>
            <h2 class="text-sm font-extrabold text-slate-800">إعدادات الواتساب</h2>
        </div>
    </div>

    {{-- بطاقة حالة الاتصال --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="p-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl flex items-center justify-center transition-colors"
                     :class="waState === 'connected' ? 'bg-emerald-50 text-emerald-600' : waState === 'loading' ? 'bg-slate-100 text-slate-400' : 'bg-rose-50 text-rose-500'">
                    <span class="material-symbols-outlined text-2xl" 
                          :class="waState === 'loading' ? 'animate-spin' : ''"
                          x-text="waState === 'connected' ? 'wifi' : waState === 'loading' ? 'sync' : 'wifi_off'"></span>
                </div>
                <div>
                    <p class="text-sm font-extrabold text-slate-800" 
                       x-text="waState === 'connected' ? 'متصل بالواتساب' : waState === 'loading' ? 'جاري الفحص...' : 'غير متصل'"></p>
                    <p class="text-[11px] font-bold mt-0.5"
                       :class="waState === 'connected' ? 'text-emerald-500' : 'text-slate-400'"
                       x-text="waMessage"></p>
                </div>
            </div>

            {{-- زر تحديث الحالة --}}
            <button type="button" @click="checkStatus(true)"
                    :disabled="waState === 'loading'"
                    class="w-8 h-8 rounded-lg bg-slate-50 border border-slate-200 flex items-center justify-center text-slate-400 hover:text-primary hover:border-primary/30 transition-colors disabled:opacity-50"
                    title="تحديث الحالة المباشرة من السيرفر">
                <span class="material-symbols-outlined text-base" :class="waState === 'loading' ? 'animate-spin' : ''">refresh</span>
            </button>
        </div>

        {{-- أزرار الاتصال / القطع --}}
        <div class="border-t border-slate-100 p-3 flex gap-2">
            <template x-if="waState !== 'connected'">
                <button type="button" @click="openConnectPanel()"
                        :disabled="isConnecting || isPairingLoading"
                        class="flex-1 flex items-center justify-center gap-2 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-extrabold transition-colors disabled:opacity-60 shadow-xs">
                    <span class="material-symbols-outlined text-base">link</span>
                    <span>ربط واتساب (QR أو كود الربط)</span>
                </button>
            </template>
            <template x-if="waState === 'connected'">
                <button type="button" @click="requestDisconnect()"
                        :disabled="isDisconnecting"
                        class="flex-1 flex items-center justify-center gap-2 py-2.5 rounded-xl bg-rose-500 hover:bg-rose-600 text-white text-xs font-extrabold transition-colors disabled:opacity-60 shadow-xs">
                    <span class="material-symbols-outlined text-base" :class="isDisconnecting ? 'animate-spin' : ''" x-text="isDisconnecting ? 'sync' : 'link_off'"></span>
                    <span x-text="isDisconnecting ? 'جاري القطع...' : 'قطع الاتصال'"></span>
                </button>
            </template>
        </div>
    </div>

    {{-- منطقة الربط (رمز QR أو كود الربط بالهاتف) --}}
    <div x-show="showConnectPanel && waState !== 'connected'" x-cloak x-transition
         class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        
        {{-- تبويبات طرق الربط --}}
        <div class="p-3 bg-slate-50/70 border-b border-slate-100">
            <div class="flex bg-slate-200/70 p-1 rounded-xl gap-1">
                <button type="button" 
                        @click="setConnectMethod('qr')"
                        :class="connectMethod === 'qr' ? 'bg-white text-emerald-700 shadow-xs font-black' : 'text-slate-500 hover:text-slate-800 font-bold'"
                        class="flex-1 py-2 rounded-lg text-xs flex items-center justify-center gap-1.5 transition-all">
                    <span class="material-symbols-outlined text-base">qr_code_2</span>
                    <span>مسح رمز QR</span>
                </button>
                <button type="button" 
                        @click="setConnectMethod('pairing')"
                        :class="connectMethod === 'pairing' ? 'bg-white text-emerald-700 shadow-xs font-black' : 'text-slate-500 hover:text-slate-800 font-bold'"
                        class="flex-1 py-2 rounded-lg text-xs flex items-center justify-center gap-1.5 transition-all">
                    <span class="material-symbols-outlined text-base">pin</span>
                    <span>كود الربط برقم الهاتف</span>
                </button>
            </div>
        </div>

        {{-- 1. محتوى تبويب QR --}}
        <div x-show="connectMethod === 'qr'" class="p-5 text-center space-y-3.5">
            <div class="flex items-center justify-center gap-2 text-slate-700">
                <span class="material-symbols-outlined text-xl text-emerald-500">qr_code_scanner</span>
                <p class="text-sm font-extrabold">امسح رمز QR من تطبيق الواتساب</p>
            </div>

            {{-- صورة الـ QR --}}
            <template x-if="qrBase64">
                <div class="flex justify-center">
                    <div class="bg-white p-3 rounded-2xl border-2 border-emerald-100 shadow-inner inline-block">
                        <img :src="qrBase64" alt="QR Code" class="w-56 h-56 rounded-lg" />
                    </div>
                </div>
            </template>

            {{-- مؤشر تحميل الـ QR --}}
            <template x-if="!qrBase64 && qrLoading">
                <div class="flex flex-col items-center justify-center py-10 gap-2">
                    <div class="w-10 h-10 border-4 border-emerald-200 border-t-emerald-600 rounded-full animate-spin"></div>
                    <span class="text-xs text-slate-400 font-bold">جاري إنشاء رمز QR...</span>
                </div>
            </template>

            {{-- تعليمات مسح QR --}}
            <div class="text-[11px] text-slate-400 font-bold space-y-1 pt-1">
                <p>1. افتح تطبيق الواتساب على هاتفك</p>
                <p>2. اذهب إلى الإعدادات ← الأجهزة المرتبطة</p>
                <p>3. اضغط على "ربط جهاز" ووجّه الكاميرا نحو الكود</p>
            </div>

            {{-- زر تحديث QR --}}
            <button type="button" @click="fetchQr()"
                    :disabled="qrLoading"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold transition-colors disabled:opacity-50">
                <span class="material-symbols-outlined text-sm" :class="qrLoading ? 'animate-spin' : ''">refresh</span>
                <span>تحديث الكود</span>
            </button>
        </div>

        {{-- 2. محتوى تبويب كود الربط (Pairing Code) --}}
        <div x-show="connectMethod === 'pairing'" class="p-5 space-y-4">
            <div class="text-center space-y-1">
                <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto">
                    <span class="material-symbols-outlined text-xl">dialpad</span>
                </div>
                <h3 class="text-sm font-extrabold text-slate-800">الربط بكود التحقق المباشر</h3>
                <p class="text-[11px] text-slate-400 font-bold">أدخل رقم هاتف الواتساب لتوليد كود الربط المكون من 8 أحرف</p>
            </div>

            {{-- حقل إدخال رقم الهاتف وزر التوليد --}}
            <div class="space-y-1.5">
                <label class="text-[11px] font-extrabold text-slate-600 block">رقم هاتف الواتساب للربط</label>
                <div class="flex gap-2">
                    <input type="tel" x-model="pairingPhone"
                           placeholder="مثال: 771234567 أو 967771234567"
                           class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-800 placeholder-slate-400 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/20 outline-none dir-ltr text-left font-mono">
                    <button type="button" @click="requestPairingCode()"
                            :disabled="isPairingLoading || !pairingPhone"
                            class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-extrabold transition-colors disabled:opacity-50 flex items-center gap-1.5 shrink-0 shadow-xs">
                        <span class="material-symbols-outlined text-sm" :class="isPairingLoading ? 'animate-spin' : ''" x-text="isPairingLoading ? 'sync' : 'key'"></span>
                        <span x-text="isPairingLoading ? 'جاري التوليد...' : 'طلب الكود'"></span>
                    </button>
                </div>
                <p class="text-[10px] text-slate-400 font-bold">يمكنك إدخال الرقم المحلي (يبدأ بـ 7) أو مع رمز الدولة (967...).</p>
            </div>

            {{-- صندوق عرض كود الربط --}}
            <template x-if="pairingCode">
                <div class="bg-emerald-50/80 border border-emerald-200/90 rounded-2xl p-4 text-center space-y-3 animate-fadeIn">
                    <p class="text-xs text-slate-600 font-bold">أدخل هذا الكود في تطبيق الواتساب بهاتفك:</p>
                    <div class="flex items-center justify-center gap-2">
                        <div class="bg-white border-2 border-emerald-500/80 rounded-xl px-5 py-2.5 shadow-sm inline-block">
                            <span class="text-2xl sm:text-3xl font-black text-emerald-800 tracking-widest font-mono select-all" x-text="pairingCode"></span>
                        </div>
                        <button type="button" @click="copyPairingCode()" title="نسخ الكود"
                                class="w-11 h-11 rounded-xl bg-white border border-emerald-300 text-emerald-600 hover:bg-emerald-100/50 flex items-center justify-center transition-colors shadow-xs">
                            <span class="material-symbols-outlined text-xl" x-text="codeCopied ? 'check' : 'content_copy'"></span>
                        </button>
                    </div>

                    <div class="flex items-center justify-center gap-1.5 text-[11px] text-emerald-700 font-bold pt-1">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                        <span>بانتظار إدخال الكود في هاتفك... يتم فحص الاتصال تلقائياً</span>
                    </div>
                </div>
            </template>

            {{-- خطوات الربط بالكود --}}
            <div class="bg-slate-50 rounded-xl p-3.5 border border-slate-100 text-[11px] text-slate-500 font-bold space-y-1.5 text-right leading-relaxed">
                <p class="font-extrabold text-slate-700">خطوات استخدام كود الربط:</p>
                <p>1. افتح تطبيق الواتساب على هاتفك.</p>
                <p>2. اذهب إلى: <span class="text-slate-800">الإعدادات ← الأجهزة المرتبطة</span>.</p>
                <p>3. اضغط على زر <span class="text-slate-800 font-extrabold">"ربط جهاز"</span>.</p>
                <p>4. اضغط بالأسفل على: <span class="text-emerald-700 font-black">"الربط باستخدام رقم الهاتف بدلاً من ذلك"</span>.</p>
                <p>5. اكتب الكود الظاهر أعلاه وسيتم تفعيل الاتصال فوراً!</p>
            </div>
        </div>

    </div>

    {{-- معلومات إضافية --}}
    <div class="bg-amber-50 border border-amber-200/60 rounded-2xl p-3.5 flex items-start gap-2.5">
        <span class="material-symbols-outlined text-amber-500 text-lg mt-0.5">info</span>
        <div class="text-[11px] text-amber-700 font-bold leading-relaxed">
            <p>عند اكتمال الربط بنجاح، سيتم إرسال إشعارات الواتساب تلقائياً للعملاء عبر خدمة Evolution API بدلاً من فتح رابط wa.me اليدوي.</p>
        </div>
    </div>

</main>
@endsection

@push('scripts')
<script>
function whatsappSettings() {
    return {
        ...(typeof officeShell === 'function' ? officeShell() : {}),

        // حالة الاتصال
        waState: 'loading', // 'connected', 'disconnected', 'loading', 'not_configured'
        waMessage: 'جاري فحص الاتصال...',

        // طرق الربط
        showConnectPanel: false,
        connectMethod: 'qr', // 'qr' or 'pairing'
        pairingPhone: @json($office->whatsapp_sender_phone ?? ''),
        isPairingLoading: false,
        codeCopied: false,

        // حالات التحميل
        isConnecting: false,
        isDisconnecting: false,
        isSaving: false,
        qrLoading: false,

        // QR Code & Pairing Code
        qrBase64: null,
        pairingCode: null,
        qrInterval: null,

        // الإعدادات
        settings: {
            whatsapp_base_url: @json($office->whatsapp_base_url ?? ''),
            whatsapp_api_key: @json($office->whatsapp_api_key ?? ''),
            whatsapp_instance_id: @json($office->whatsapp_instance_id ?? ''),
            whatsapp_instance_name: @json($office->whatsapp_instance_name ?? ''),
            whatsapp_webhook_url: @json($office->whatsapp_webhook_url ?? ''),
            whatsapp_template: @json($office->whatsapp_template ?? ''),
        },

        async init() {
            // تهيئة MursalDB وطابور المزامنة من officeShell
            if (window.MursalDB) {
                await window.MursalDB.open();
                this.syncQueue = await window.MursalDB.getAll(window.MURSAL_STORES.queue);
            }
            if (typeof this.watchConnection === 'function') {
                this.watchConnection(() => this.syncData());
            }

            this.checkStatus();
        },

        destroy() {
            if (this.qrInterval) clearInterval(this.qrInterval);
        },

        async checkStatus(force = false) {
            this.waState = 'loading';
            this.waMessage = 'جاري فحص الاتصال...';

            try {
                const url = '{{ route("office.whatsapp.status") }}' + (force ? '?refresh=1' : '');
                const res = await fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                    }
                });

                const data = await res.json();

                if (data.state === 'not_configured') {
                    this.waState = 'not_configured';
                    this.waMessage = 'يرجى إدخال إعدادات الاتصال أولاً';
                } else if (data.connected) {
                    this.waState = 'connected';
                    this.waMessage = data.from_cache ? 'الجلسة نشطة ومتصلة' : 'الجلسة نشطة ومتصلة';
                    this.showConnectPanel = false;
                    if (this.qrInterval) { clearInterval(this.qrInterval); this.qrInterval = null; }
                } else {
                    this.waState = 'disconnected';
                    this.waMessage = data.message || 'غير متصل';
                }
            } catch (e) {
                this.waState = 'disconnected';
                this.waMessage = 'تعذر الوصول للخادم';
            }
        },

        openConnectPanel() {
            this.showConnectPanel = true;
            if (this.connectMethod === 'qr' && !this.qrBase64) {
                this.requestConnect();
            } else if (this.connectMethod === 'pairing' && !this.pairingCode && this.pairingPhone) {
                this.requestPairingCode();
            }
        },

        setConnectMethod(method) {
            this.connectMethod = method;
            if (method === 'qr' && !this.qrBase64) {
                this.requestConnect();
            } else if (method === 'pairing' && !this.pairingCode && this.pairingPhone) {
                this.requestPairingCode();
            }
        },

        async requestConnect() {
            this.isConnecting = true;
            try {
                const res = await fetch('{{ route("office.whatsapp.connect") }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                    }
                });

                const data = await res.json();

                if (data.is_connected || data.connected) {
                    this.waState = 'connected';
                    this.waMessage = 'الجلسة متصلة بالفعل!';
                    this.showConnectPanel = false;
                    this.showFeedback('الجلسة متصلة بنجاح! ✅', false);
                    if (this.qrInterval) { clearInterval(this.qrInterval); this.qrInterval = null; }
                    this.isConnecting = false;
                    return;
                }

                if (data.success !== false) {
                    await this.fetchQr();
                    this.startStatusPolling();
                } else {
                    this.showFeedback(data.message || 'فشل في طلب الاتصال', true);
                }
            } catch (e) {
                this.showFeedback('تعذر الاتصال بالخادم', true);
            }
            this.isConnecting = false;
        },

        async fetchQr() {
            this.qrLoading = true;
            this.qrBase64 = null;

            try {
                const res = await fetch('{{ route("office.whatsapp.qr") }}', {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                    }
                });

                const data = await res.json();

                if (data.is_connected || data.connected) {
                    this.waState = 'connected';
                    this.waMessage = 'الجلسة متصلة بالفعل!';
                    this.showConnectPanel = false;
                    this.showFeedback('الجلسة متصلة بنجاح! ✅', false);
                    if (this.qrInterval) { clearInterval(this.qrInterval); this.qrInterval = null; }
                    this.qrLoading = false;
                    return;
                }

                if (data.qr_base64) {
                    let src = data.qr_base64;
                    if (!src.startsWith('data:')) {
                        src = 'data:image/png;base64,' + src;
                    }
                    this.qrBase64 = src;
                }

                if (data.pairing_code) {
                    this.pairingCode = data.pairing_code;
                }
            } catch (e) {
                console.error('QR fetch error:', e);
            }

            this.qrLoading = false;
        },

        async requestPairingCode() {
            if (!this.pairingPhone) {
                this.showFeedback('يرجى إدخال رقم هاتف الواتساب أولاً', true);
                return;
            }

            this.isPairingLoading = true;
            this.pairingCode = null;

            try {
                const res = await fetch('{{ route("office.whatsapp.pairing_code") }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                    },
                    body: JSON.stringify({ phone: this.pairingPhone })
                });

                const data = await res.json();

                if (data.is_connected || data.connected) {
                    this.waState = 'connected';
                    this.waMessage = 'الجلسة متصلة بالفعل!';
                    this.showConnectPanel = false;
                    this.showFeedback('الجلسة متصلة بنجاح! ✅', false);
                    if (this.qrInterval) { clearInterval(this.qrInterval); this.qrInterval = null; }
                    this.isPairingLoading = false;
                    return;
                }

                if (data.success && data.pairing_code) {
                    this.pairingCode = data.pairing_code;
                    this.showFeedback('تم توليد كود الربط بنجاح! أدخله في هاتفك.', false);
                    this.startStatusPolling();
                } else {
                    this.showFeedback(data.message || 'تعذر الحصول على كود الربط', true);
                }
            } catch (e) {
                this.showFeedback('تعذر الاتصال بالخادم لجلب كود الربط', true);
            }

            this.isPairingLoading = false;
        },

        copyPairingCode() {
            if (!this.pairingCode) return;

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(this.pairingCode).then(() => {
                    this.codeCopied = true;
                    this.showFeedback('تم نسخ كود الربط إلى الحافظة! ✅', false);
                    setTimeout(() => this.codeCopied = false, 2500);
                }).catch(() => {
                    this.fallbackCopyText(this.pairingCode);
                });
            } else {
                this.fallbackCopyText(this.pairingCode);
            }
        },

        fallbackCopyText(text) {
            const ta = document.createElement('textarea');
            ta.value = text;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            this.codeCopied = true;
            this.showFeedback('تم نسخ كود الربط! ✅', false);
            setTimeout(() => this.codeCopied = false, 2500);
        },

        startStatusPolling() {
            if (this.qrInterval) clearInterval(this.qrInterval);

            this.qrInterval = setInterval(async () => {
                try {
                    const res = await fetch('{{ route("office.whatsapp.status") }}?refresh=1', {
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                        }
                    });
                    const data = await res.json();

                    if (data.connected) {
                        this.waState = 'connected';
                        this.waMessage = 'تم الاتصال بنجاح!';
                        this.showConnectPanel = false;
                        clearInterval(this.qrInterval);
                        this.qrInterval = null;
                        this.showFeedback('تم ربط حساب الواتساب بنجاح! 🎉✅', false);
                    }
                } catch (e) { /* ignore */ }
            }, 5000);
        },

        async requestDisconnect() {
            if (!confirm('هل أنت متأكد من قطع الاتصال بالواتساب؟')) return;

            this.isDisconnecting = true;
            try {
                const res = await fetch('{{ route("office.whatsapp.disconnect") }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                    }
                });

                const data = await res.json();

                this.waState = 'disconnected';
                this.waMessage = 'تم قطع الاتصال';
                this.qrBase64 = null;
                this.pairingCode = null;
                this.showConnectPanel = false;
                this.showFeedback(data.message || 'تم قطع الاتصال', false);
            } catch (e) {
                this.showFeedback('حدث خطأ أثناء قطع الاتصال', true);
            }
            this.isDisconnecting = false;
        },

        async saveSettings() {
            this.isSaving = true;
            try {
                const res = await fetch('{{ route("office.whatsapp.save") }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                    },
                    body: JSON.stringify(this.settings)
                });

                const data = await res.json();

                if (data.success) {
                    this.showFeedback('تم حفظ الإعدادات بنجاح ✅', false);
                    setTimeout(() => this.checkStatus(true), 500);
                } else {
                    this.showFeedback(data.message || 'فشل في حفظ الإعدادات', true);
                }
            } catch (e) {
                this.showFeedback('حدث خطأ أثناء الحفظ', true);
            }
            this.isSaving = false;
        },

        showFeedback(message, isError) {
            this.smsFeedback = { show: true, message, isError };
            setTimeout(() => {
                if (this.smsFeedback) this.smsFeedback.show = false;
            }, 4000);
        }
    };
}
</script>
@endpush
