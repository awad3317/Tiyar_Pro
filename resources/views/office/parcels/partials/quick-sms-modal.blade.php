{{-- مودال الإرسال السريع التدريجي للـ SMS (Streaming Dispatch) --}}
<div x-show="quickSms.open"
     x-cloak
     class="fixed inset-0 z-50 flex items-end justify-center"
     @open-quick-sms.window="openQuickSmsModal()"
     @keydown.escape.window="quickSms.open && resetQuickSms()">

    {{-- خلفية التعتيم --}}
    <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm transition-opacity"
         x-show="quickSms.open"
         x-transition:enter="ease-out duration-250"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="resetQuickSms()">
    </div>

    {{-- بطاقة المودال (Bottom Sheet) --}}
    <div class="relative w-full max-w-md bg-white rounded-t-3xl shadow-2xl border-t border-slate-100 z-10 transition-all transform flex flex-col overflow-hidden"
         style="max-height: 92vh;"
         x-show="quickSms.open"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-full"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-full">

        {{-- مقبض السحب --}}
        <div class="flex justify-center pt-3 pb-1">
            <div class="w-10 h-1 rounded-full bg-slate-300"></div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════
             الواجهة الرئيسية: إدخال تدريجي + قائمة حية
             ═══════════════════════════════════════════════════════════════ --}}

        {{-- هيدر المودال --}}
        <div class="px-5 pb-3 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-2xl bg-secondary/10 border border-secondary/20 text-secondary flex items-center justify-center">
                    <span class="material-symbols-outlined text-xl">bolt</span>
                </div>
                <div>
                    <h3 class="text-sm font-extrabold text-slate-800">إرسال سريع</h3>
                    <p class="text-[11px] text-slate-400">أدخل الطرود واحداً تلو الآخر</p>
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
             حقول الإدخال السريع
             ═══════════════════════════════════ --}}
        <div class="px-5 pb-3 space-y-3">

            {{-- حقل رقم الهاتف --}}
            <div class="relative">
                <label class="block text-[11px] font-bold text-slate-500 mb-1">رقم الهاتف</label>
                <div class="relative rounded-2xl border border-slate-200 bg-slate-50/80 focus-within:border-secondary focus-within:ring-2 focus-within:ring-secondary/20 transition-all">
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
                           class="w-full py-3 px-4 pr-10 text-sm font-mono font-bold text-slate-800 bg-transparent outline-none placeholder:text-slate-300 placeholder:font-normal"
                           placeholder="7XXXXXXXX" />
                </div>
                {{-- مؤشر الصلاحية --}}
                <div class="absolute left-3 top-[calc(50%+8px)] -translate-y-1/2">
                    <template x-if="quickSms.phone.length === 9 && /^7[01378]/.test(normalizeText(quickSms.phone))">
                        <span class="material-symbols-outlined text-base text-emerald-500">check_circle</span>
                    </template>
                    <template x-if="quickSms.phone.length > 0 && (quickSms.phone.length < 9 || !/^7[01378]/.test(normalizeText(quickSms.phone)))">
                        <span class="material-symbols-outlined text-base text-rose-400">error</span>
                    </template>
                </div>
            </div>

            {{-- حقل نوع الطرد --}}
            <div>
                <label class="block text-[11px] font-bold text-slate-500 mb-1">نوع الطرد</label>
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
                       class="w-full py-3 px-4 text-sm font-bold text-slate-700 rounded-2xl border border-slate-200 bg-slate-50/80 outline-none focus:border-secondary focus:ring-2 focus:ring-secondary/20 placeholder:text-slate-300 placeholder:font-normal transition-all"
                       placeholder="" />
            </div>

            {{-- أزرار الوصف السريع (النقر يعتمد الطرد فوراً) --}}
            {{-- <div>
                <p class="text-[11px] text-slate-400 font-bold mb-1.5">نوع سريع <span class="text-[10px] text-slate-300">(انقر لاعتماد الطرد فوراً)</span></p>
                <div class="flex flex-wrap gap-1.5">
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
                                class="px-3 py-1.5 rounded-xl bg-primary/5 border border-primary/10 text-primary text-[11px] font-bold hover:bg-secondary/10 hover:border-secondary/30 hover:text-secondary transition-all active:scale-95">
                            <span x-text="tag"></span>
                        </button>
                    </template>
                </div>
            </div> --}}

            {{-- زر إضافة يدوي (بديل عن Enter) --}}
            <button type="button"
                    @click="
                        const phone = normalizeText(quickSms.phone).replace(/\D/g, '');
                        if (phone.length === 9 && /^7[01378]/.test(phone)) {
                            addToStreamingQueue(phone, quickSms.description || 'طرد');
                            quickSms.phone = '';
                            quickSms.description = 'طرد';
                            $nextTick(() => document.getElementById('streaming-phone-input').focus());
                        } else {
                            showFeedback('أدخل رقم هاتف يمني صالح (يبدأ بـ 7 ومكون من 9 أرقام)', true);
                        }
                    "
                    :disabled="quickSms.phone.length < 9"
                    class="w-full py-3 px-4 bg-secondary hover:bg-amber-500 active:scale-[0.98] text-white font-extrabold text-sm rounded-2xl shadow-lg shadow-secondary/25 flex items-center justify-center gap-2 transition-all disabled:opacity-30 disabled:cursor-not-allowed disabled:active:scale-100">
                <span class="material-symbols-outlined text-lg">add_circle</span>
                <span>إضافة واعتماد الطرد</span>
            </button>
        </div>

        {{-- ═══════════════════════════════════
             القائمة الحية (Live Streaming Queue)
             ═══════════════════════════════════ --}}
        <div class="flex-1 overflow-hidden flex flex-col border-t border-slate-100"
             x-show="streamingQueue.length > 0"
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100">

            {{-- عنوان القائمة الحية --}}
            <div class="px-5 py-2.5 flex items-center justify-between bg-slate-50/80">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm text-slate-400">list_alt</span>
                    <span class="text-[11px] font-extrabold text-slate-600">كشف الإرسال الحي</span>
                </div>
                <div class="flex items-center gap-2.5 text-[10px] font-bold">
                    <template x-if="streamingSentCount > 0">
                        <span class="text-emerald-600">✅ <span x-text="streamingSentCount"></span></span>
                    </template>
                    <template x-if="streamingPendingCount > 0">
                        <span class="text-amber-600">⏳ <span x-text="streamingPendingCount"></span></span>
                    </template>
                    <template x-if="streamingFailedCount > 0">
                        <span class="text-rose-600">❌ <span x-text="streamingFailedCount"></span></span>
                    </template>
                </div>
            </div>

            {{-- عناصر القائمة مع التمرير --}}
            <div class="flex-1 overflow-y-auto px-5 pb-3 space-y-1.5" style="max-height: 40vh;">
                <template x-for="entry in streamingQueue" :key="entry.id">
                    <div class="flex items-center gap-2.5 rounded-xl px-3 py-2.5 border transition-all duration-300"
                         :class="{
                             'bg-slate-50 border-slate-100': entry.status === 'pending',
                             'bg-emerald-50/60 border-emerald-100': entry.status === 'success',
                             'bg-rose-50/60 border-rose-200': entry.status === 'failed',
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

                        {{-- الوصف --}}
                        <span class="text-[11px] font-bold text-slate-500 flex-1 truncate" x-text="entry.description"></span>

                        {{-- زر إعادة المحاولة --}}
                        <template x-if="entry.status === 'failed'">
                            <button type="button"
                                    @click="retryStreamingEntry(entry)"
                                    class="shrink-0 px-2 py-1 rounded-lg bg-rose-100 hover:bg-rose-200 text-rose-600 text-[10px] font-bold transition-colors flex items-center gap-0.5">
                                <span class="material-symbols-outlined text-xs">refresh</span>
                                <span>إعادة</span>
                            </button>
                        </template>

                        {{-- مؤشر SMS --}}
                        <template x-if="entry.status === 'success' && entry.smsSent">
                            <span class="shrink-0 text-[10px] text-emerald-500 font-bold">SMS ✓</span>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        {{-- ═══════════════════════════════════
             زر إنهاء الكشف
             ═══════════════════════════════════ --}}
        <div class="px-5 py-4 border-t border-slate-100 bg-white" x-show="streamingQueue.length > 0">
            {{-- تنبيه الاتصال --}}
            <div class="mb-3 bg-amber-50 border border-amber-200/80 rounded-xl p-2.5 flex items-start gap-2 text-[10px] text-amber-800 leading-snug"
                 x-show="streamingPendingCount > 0 || streamingFailedCount > 0">
                <span class="material-symbols-outlined text-sm text-amber-600 shrink-0 mt-0.5">warning</span>
                <span>
                    <strong>تنبيه:</strong> يجب إبقاء هاتف الإرسال متصلاً بالإنترنت حتى اكتمال إرسال جميع الرسائل.
                </span>
            </div>

            <button type="button"
                    @click="
                        if (streamingPendingCount > 0) {
                            showFeedback('لا يزال هناك طرود قيد الإرسال...', true);
                            return;
                        }
                        // تحديث القائمة المحلية ثم إغلاق المودال
                        if (typeof refreshFromServer === 'function' && isOnline) {
                            refreshFromServer();
                        }
                        resetQuickSms();
                        streamingQueue = [];
                        streamingIdCounter = 0;
                    "
                    class="w-full py-3 px-4 active:scale-[0.98] font-extrabold text-sm rounded-2xl flex items-center justify-center gap-2 transition-all"
                    :class="streamingPendingCount > 0
                        ? 'bg-slate-200 text-slate-400 cursor-not-allowed'
                        : 'bg-primary hover:bg-slate-800 text-white'
                    ">
                <span class="material-symbols-outlined text-lg">done_all</span>
                <span x-text="streamingPendingCount > 0 ? 'جارِ إرسال ' + streamingPendingCount + ' طرد...' : 'إنهاء الكشف ✓'"></span>
            </button>
        </div>

    </div>
</div>
