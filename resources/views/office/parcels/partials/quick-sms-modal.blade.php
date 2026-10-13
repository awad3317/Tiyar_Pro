{{-- مودال الإرسال السريع التدريجي للـ SMS (شاشة كاملة تتكيف مع الكيبورد) --}}
<div x-show="quickSms.open"
     x-cloak
     class="fixed inset-0 z-50 flex flex-col bg-white"
     @open-quick-sms.window="openQuickSmsModal()"
     @keydown.escape.window="quickSms.open && resetQuickSms()">

    {{-- خلفية التعتيم للشاشات الكبيرة (Desktop) --}}
    <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm transition-opacity md:block hidden"
         x-show="quickSms.open"
         @click="resetQuickSms()">
    </div>

    {{-- بطاقة المودال: شاشة كاملة على الجوال (h-[100dvh]) وبطاقة مركزية على الديسكتوب --}}
    <div class="relative w-full h-[100dvh] md:h-auto md:max-h-[90vh] md:max-w-md md:rounded-3xl md:m-auto bg-white shadow-2xl z-10 flex flex-col overflow-hidden"
         x-show="quickSms.open"
         x-transition:enter="ease-out duration-250"
         x-transition:enter-start="opacity-0 translate-y-full md:translate-y-4 md:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 md:scale-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 md:scale-100"
         x-transition:leave-end="opacity-0 translate-y-full md:translate-y-4 md:scale-95">

        {{-- ═══════════════════════════════════
             1. هيدر المودال الثابت بالأعلى
             ═══════════════════════════════════ --}}
        <div class="px-5 py-3.5 flex items-center justify-between border-b border-slate-100 bg-white/95 backdrop-blur-sm shrink-0">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-secondary/10 border border-secondary/20 text-secondary flex items-center justify-center">
                    <span class="material-symbols-outlined text-lg">bolt</span>
                </div>
                <div>
                    <h3 class="text-sm font-extrabold text-slate-800 leading-tight">إرسال سريع</h3>
                    <p class="text-[10px] text-slate-400">إدخال مباشر بالخلفية</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                {{-- عداد مصغر --}}
                <template x-if="streamingQueue.length > 0">
                    <div class="flex items-center gap-1 px-2 py-1 rounded-lg bg-emerald-50 border border-emerald-100">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500" :class="streamingPendingCount > 0 && 'animate-pulse bg-amber-500'"></span>
                        <span class="text-[10px] font-extrabold text-emerald-700" x-text="streamingSentCount"></span>
                        <span class="text-[10px] text-slate-400">/</span>
                        <span class="text-[10px] font-bold text-slate-500" x-text="streamingQueue.length"></span>
                    </div>
                </template>

                <button type="button" @click="resetQuickSms()"
                        class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-400 hover:text-slate-600 flex items-center justify-center transition-colors">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>
        </div>

        {{-- ═══════════════════════════════════
             2. حقول الإدخال السريعة (تبقى واضحة أعلى الكيبورد)
             ═══════════════════════════════════ --}}
        <div class="px-5 pt-3 pb-2 space-y-2.5 bg-white shrink-0 border-b border-slate-50">

            {{-- حقل رقم الهاتف --}}
            <div>
                <label class="block text-[11px] font-bold text-slate-600 mb-1">رقم الهاتف</label>
                <div class="relative rounded-2xl border border-slate-200 bg-slate-50/80 focus-within:bg-white focus-within:border-secondary focus-within:ring-2 focus-within:ring-secondary/20 transition-all">
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs font-bold select-none">🇾🇪</span>
                    <input type="tel"
                           id="streaming-phone-input"
                           x-model="quickSms.phone"
                           @input="quickSms.phone = normalizeText(quickSms.phone).replace(/\D/g, '').substring(0, 9)"
                           @keydown.enter.prevent="
                               if (quickSms.phone.length === 9 && /^7[01378]/.test(normalizeText(quickSms.phone))) {
                                   $refs.streamingDescInput.focus();
                               }
                           "
                           dir="ltr"
                           inputmode="numeric"
                           maxlength="9"
                           autocomplete="off"
                           class="w-full py-2.5 px-4 pr-10 text-sm font-mono font-bold text-slate-800 bg-transparent outline-none placeholder:text-slate-300 placeholder:font-normal"
                           placeholder="7XXXXXXXX" />
                    
                    {{-- مؤشر صحة الرقم --}}
                    <div class="absolute left-3 top-1/2 -translate-y-1/2">
                        <template x-if="quickSms.phone.length === 9 && /^7[01378]/.test(normalizeText(quickSms.phone))">
                            <span class="material-symbols-outlined text-base text-emerald-500">check_circle</span>
                        </template>
                        <template x-if="quickSms.phone.length > 0 && (quickSms.phone.length < 9 || !/^7[01378]/.test(normalizeText(quickSms.phone)))">
                            <span class="material-symbols-outlined text-base text-rose-400">error</span>
                        </template>
                    </div>
                </div>
            </div>

            {{-- حقل نوع الطرد --}}
            <div>
                <label class="block text-[11px] font-bold text-slate-600 mb-1">نوع الطرد</label>
                <input type="text"
                       x-ref="streamingDescInput"
                       x-model="quickSms.description"
                       @keydown.enter.prevent="
                           const phone = normalizeText(quickSms.phone).replace(/\D/g, '');
                           if (phone.length === 9 && /^7[01378]/.test(phone)) {
                               addToStreamingQueue(phone, quickSms.description || '');
                               quickSms.phone = '';
                               quickSms.description = '';
                               $nextTick(() => document.getElementById('streaming-phone-input').focus());
                           } else {
                               showFeedback('رقم الهاتف غير صالح', true);
                               document.getElementById('streaming-phone-input').focus();
                           }
                       "
                       dir="rtl"
                       autocomplete="off"
                       class="w-full py-2.5 px-4 text-xs font-bold text-slate-700 rounded-2xl border border-slate-200 bg-slate-50/80 focus-within:bg-white outline-none focus:border-secondary focus:ring-2 focus:ring-secondary/20 placeholder:text-slate-300 transition-all"
                       placeholder="" />
            </div>

            {{-- رقائق الوصف السريع --}}
            {{-- <div class="flex items-center gap-1.5 overflow-x-auto pb-0.5 scrollbar-none">
                <template x-for="tag in ['طرد', 'كيس', 'كيس أسود', 'كرتون', 'ظرف']" :key="tag">
                    <button type="button"
                            @click="
                                const phone = normalizeText(quickSms.phone).replace(/\D/g, '');
                                if (phone.length === 9 && /^7[01378]/.test(phone)) {
                                    addToStreamingQueue(phone, tag);
                                    quickSms.phone = '';
                                    quickSms.description = 'طرد';
                                    $nextTick(() => document.getElementById('streaming-phone-input').focus());
                                } else {
                                    showFeedback('أدخل رقم هاتف صالح أولاً', true);
                                    document.getElementById('streaming-phone-input').focus();
                                }
                            "
                            class="px-2.5 py-1 rounded-xl bg-slate-100 hover:bg-secondary/10 hover:text-secondary text-slate-600 text-[11px] font-bold shrink-0 transition-colors active:scale-95">
                        <span x-text="tag"></span>
                    </button>
                </template>
            </div> --}}

            {{-- زر الإضافة اليدوي --}}
            <button type="button"
                    @click="
                        const phone = normalizeText(quickSms.phone).replace(/\D/g, '');
                        if (phone.length === 9 && /^7[01378]/.test(phone)) {
                            addToStreamingQueue(phone, quickSms.description || 'طرد');
                            quickSms.phone = '';
                            quickSms.description = 'طرد';
                            $nextTick(() => document.getElementById('streaming-phone-input').focus());
                        } else {
                            showFeedback('أدخل رقم هاتف صالح', true);
                        }
                    "
                    :disabled="quickSms.phone.length < 9"
                    class="w-full py-2.5 px-4 bg-secondary hover:bg-amber-500 active:scale-[0.98] text-white font-extrabold text-xs rounded-xl shadow-md shadow-secondary/20 flex items-center justify-center gap-1.5 transition-all disabled:opacity-30 disabled:cursor-not-allowed">
                <span class="material-symbols-outlined text-base">add_circle</span>
                <span>إضافة واعتماد الطرد (Enter)</span>
            </button>
        </div>

        {{-- ═══════════════════════════════════
             3. القائمة الحية (Scrollable Area)
             ═══════════════════════════════════ --}}
        <div class="flex-1 overflow-y-auto px-5 py-3 space-y-1.5 min-h-0 bg-slate-50/50">
            {{-- حالة عدم وجود طرود --}}
            <div x-show="streamingQueue.length === 0" class="flex flex-col items-center justify-center h-full min-h-[140px] text-center text-slate-400">
                <span class="material-symbols-outlined text-3xl mb-1 text-slate-300">playlist_add</span>
                <p class="text-xs font-bold text-slate-500">القائمة فارغة حالياً</p>
                <p class="text-[10px] text-slate-400">الطرود التي تدخلها ستظهر هنا وتُرسل مباشرة</p>
            </div>

            {{-- بنود الطرود المضافة --}}
            <template x-for="entry in streamingQueue" :key="entry.id">
                <div class="flex items-center gap-2.5 bg-white rounded-xl px-3 py-2 border shadow-2xs transition-all"
                     :class="{
                         'border-slate-100': entry.status === 'pending',
                         'border-emerald-200 bg-emerald-50/30': entry.status === 'success',
                         'border-rose-200 bg-rose-50/30': entry.status === 'failed',
                     }">

                    {{-- أيقونة الحالة --}}
                    <div class="shrink-0 w-6 h-6 rounded-lg flex items-center justify-center"
                         :class="{
                             'bg-amber-100': entry.status === 'pending',
                             'bg-emerald-100': entry.status === 'success',
                             'bg-rose-100': entry.status === 'failed',
                         }">
                        <template x-if="entry.status === 'pending'">
                            <span class="w-3.5 h-3.5 border-2 border-amber-500 border-t-transparent rounded-full animate-spin"></span>
                        </template>
                        <template x-if="entry.status === 'success'">
                            <span class="material-symbols-outlined text-sm text-emerald-600">check_circle</span>
                        </template>
                        <template x-if="entry.status === 'failed'">
                            <span class="material-symbols-outlined text-sm text-rose-500">error</span>
                        </template>
                    </div>

                    {{-- رقم الهاتف --}}
                    <span class="text-xs font-mono font-bold text-slate-700 dir-ltr shrink-0" x-text="entry.phone"></span>

                    {{-- نوع الطرد --}}
                    <span class="text-[11px] font-bold text-slate-500 flex-1 truncate" x-text="entry.description"></span>

                    {{-- زر إعادة المحاولة --}}
                    <template x-if="entry.status === 'failed'">
                        <button type="button"
                                @click="retryStreamingEntry(entry)"
                                class="shrink-0 px-2 py-1 rounded-lg bg-rose-100 hover:bg-rose-200 text-rose-600 text-[10px] font-bold flex items-center gap-0.5">
                            <span class="material-symbols-outlined text-xs">refresh</span>
                            <span>إعادة</span>
                        </button>
                    </template>

                    {{-- مؤشر SMS --}}
                    <template x-if="entry.status === 'success' && entry.smsSent">
                        <span class="shrink-0 text-[10px] text-emerald-600 font-bold">SMS ✓</span>
                    </template>
                </div>
            </template>
        </div>

        {{-- ═══════════════════════════════════
             4. الفوتر الثابت بالأسفل (إغلاق وإنهاء)
             ═══════════════════════════════════ --}}
        <div class="p-3 border-t border-slate-100 bg-white shrink-0">
            <button type="button"
                    @click="
                        if (streamingPendingCount > 0) {
                            showFeedback('لا يزال هناك طرود قيد الإرسال...', true);
                            return;
                        }
                        if (typeof refreshFromServer === 'function' && isOnline) {
                            refreshFromServer();
                        }
                        resetQuickSms();
                        streamingQueue = [];
                        streamingIdCounter = 0;
                    "
                    class="w-full py-2.5 px-4 font-extrabold text-xs rounded-xl flex items-center justify-center gap-2 transition-all active:scale-[0.98]"
                    :class="streamingPendingCount > 0
                        ? 'bg-slate-100 text-slate-400 cursor-not-allowed'
                        : 'bg-slate-900 hover:bg-slate-800 text-white shadow-sm'
                    ">
                <span class="material-symbols-outlined text-base">done_all</span>
                <span x-text="streamingPendingCount > 0 ? 'جارِ إرسال ' + streamingPendingCount + ' طرد...' : 'إنهاء الكشف وإغلاق'"></span>
            </button>
        </div>

    </div>
</div>