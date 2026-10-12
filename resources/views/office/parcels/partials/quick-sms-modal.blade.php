{{-- مودال الإرسال السريع للـ SMS (كشف جملة) --}}
<div x-show="quickSms.open"
     x-cloak
     class="fixed inset-0 z-50 flex items-end justify-center"
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
             المرحلة 1: الإدخال (input)
             ═══════════════════════════════════════════════════════════════ --}}
        <div x-show="quickSms.step === 'input'" class="flex flex-col flex-1 overflow-hidden">

            {{-- هيدر المرحلة --}}
            <div class="px-5 pb-3 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-2xl bg-secondary/10 border border-secondary/20 text-secondary flex items-center justify-center">
                        <span class="material-symbols-outlined text-xl">bolt</span>
                    </div>
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-800">إرسال سريع</h3>
                        <p class="text-[11px] text-slate-400">أدخل الأرقام والطرود سطراً بسطر</p>
                    </div>
                </div>
                <button type="button" @click="resetQuickSms()"
                        class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-400 hover:text-slate-600 flex items-center justify-center transition-colors">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>

            {{-- منطقة الإدخال --}}
            <div class="px-5 flex-1 flex flex-col gap-3 overflow-y-auto pb-3">

                {{-- حقل النص الكبير --}}
                <div class="relative flex-1 min-h-[180px]">
                    <textarea
                        x-model="quickSms.rawText"
                        dir="rtl"
                        rows="8"
                        placeholder="مثال:&#10;779030449 كيس برتقالي&#10;771234567 كرتون&#10;733456789"
                        class="w-full h-full bg-slate-50 border border-slate-200 rounded-2xl p-4 text-sm text-slate-800 placeholder-slate-400 resize-none outline-none focus:border-secondary focus:ring-2 focus:ring-secondary/20 transition-all leading-relaxed"
                        style="font-size: 16px;"
                    ></textarea>

                    {{-- زر اللصق من الحافظة --}}
                    <button type="button"
                            @click="pasteFromClipboard()"
                            class="absolute left-3 top-3 flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-white border border-slate-200 text-slate-500 hover:text-secondary hover:border-secondary/40 text-[11px] font-bold shadow-sm transition-all active:scale-95">
                        <span class="material-symbols-outlined text-sm">content_paste</span>
                        <span>لصق</span>
                    </button>
                </div>

                {{-- مؤشر عدد الطرود المرصودة مباشرة --}}
                <div class="flex items-center justify-between px-1">
                    <span class="text-[11px] text-slate-400">
                        يمكن الفصل بسطر أو كتابتها في سطر متصل
                    </span>
                    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold transition-all"
                         :class="detectedParcelsCount > 0 
                            ? 'bg-secondary/15 text-secondary border border-secondary/30 shadow-sm shadow-secondary/10' 
                            : 'bg-slate-100 text-slate-400 border border-slate-200'">
                        <span class="w-2 h-2 rounded-full" 
                              :class="detectedParcelsCount > 0 ? 'bg-secondary animate-pulse' : 'bg-slate-300'"></span>
                        <span x-text="'تم رصد ' + detectedParcelsCount + ' طرد'"></span>
                    </div>
                </div>

                {{-- رقائق الوصف السريع --}}
                <div>
                    <p class="text-[11px] text-slate-400 font-bold mb-1.5">إضافة وصف سريع:</p>
                    <div class="flex flex-wrap gap-1.5">
                        <template x-for="tag in ['كيس اسود', 'كيس ابيض', 'كيس ازرق', 'كرتون']" :key="tag">
                            <button type="button"
                                    @click="appendTag(tag)"
                                    class="px-3 py-1.5 rounded-xl bg-primary/5 border border-primary/10 text-primary text-[11px] font-bold hover:bg-secondary/10 hover:border-secondary/30 hover:text-secondary transition-all active:scale-95">
                                <span x-text="tag"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            {{-- زر المراجعة --}}
            <div class="px-5 py-4 border-t border-slate-100 bg-white">
                <button type="button"
                        @click="parseQuickList()"
                        :disabled="detectedParcelsCount === 0"
                        class="w-full py-3.5 px-4 bg-secondary hover:bg-amber-500 active:scale-[0.98] text-white font-extrabold text-sm rounded-2xl shadow-lg shadow-secondary/25 flex items-center justify-center gap-2 transition-all disabled:opacity-40 disabled:cursor-not-allowed disabled:active:scale-100">
                    <span class="material-symbols-outlined text-lg">checklist</span>
                    <span>مراجعة الكشف</span>
                    <template x-if="detectedParcelsCount > 0">
                        <span class="bg-black/15 text-white text-xs px-2 py-0.5 rounded-full font-mono" x-text="'(' + detectedParcelsCount + ')'"></span>
                    </template>
                </button>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════
             المرحلة 2: المعاينة (preview)
             ═══════════════════════════════════════════════════════════════ --}}
        <div x-show="quickSms.step === 'preview'" class="flex flex-col flex-1 overflow-hidden">

            {{-- هيدر المعاينة --}}
            <div class="px-5 pb-3 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-2xl bg-blue-50 border border-blue-100 text-blue-600 flex items-center justify-center">
                        <span class="material-symbols-outlined text-xl">preview</span>
                    </div>
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-800">مراجعة الكشف</h3>
                        <p class="text-[11px] text-slate-400">
                            إجمالي: <strong class="text-secondary" x-text="quickSms.items.length"></strong> طرد
                        </p>
                    </div>
                </div>
                <button type="button" @click="resetQuickSms()"
                        class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-400 hover:text-slate-600 flex items-center justify-center transition-colors">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>

            {{-- قائمة العناصر المحللة --}}
            <div class="px-5 flex-1 overflow-y-auto pb-3 space-y-1.5" style="max-height: 55vh;">
                <template x-for="(item, idx) in quickSms.items" :key="idx">
                    <div class="flex items-center gap-3 bg-slate-50 rounded-xl px-3.5 py-2.5 border border-slate-100">
                        {{-- رقم التسلسل --}}
                        <span class="w-6 h-6 rounded-lg bg-secondary/10 text-secondary text-[11px] font-extrabold flex items-center justify-center shrink-0"
                              x-text="idx + 1"></span>

                        {{-- رقم الهاتف --}}
                        <div class="flex items-center gap-1.5 min-w-0 flex-1">
                            <span class="material-symbols-outlined text-sm text-slate-400 shrink-0">smartphone</span>
                            <span class="text-xs font-mono font-bold text-slate-700 dir-ltr truncate" x-text="item.phone"></span>
                        </div>

                        {{-- الوصف --}}
                        <div class="flex items-center gap-1.5 min-w-0 flex-1 justify-end">
                            <span class="material-symbols-outlined text-sm text-slate-400 shrink-0">inventory_2</span>
                            <span class="text-xs font-bold text-slate-600 truncate" x-text="item.description"></span>
                        </div>
                    </div>
                </template>

                {{-- حالة فارغة --}}
                <div x-show="quickSms.items.length === 0" class="text-center py-8 text-slate-400">
                    <span class="material-symbols-outlined text-3xl mb-1">warning</span>
                    <p class="text-xs">لم يتم العثور على أرقام صالحة في النص المدخل.</p>
                </div>
            </div>

            {{-- أزرار الإجراءات --}}
            <div class="px-5 py-4 border-t border-slate-100 bg-white flex flex-col gap-2">
                <button type="button"
                        @click="submitQuickSms()"
                        :disabled="quickSms.items.length === 0 || quickSms.isSending"
                        class="w-full py-3.5 px-4 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] text-white font-extrabold text-sm rounded-2xl shadow-lg shadow-emerald-600/25 flex items-center justify-center gap-2 transition-all disabled:opacity-40 disabled:cursor-not-allowed disabled:active:scale-100">
                    <template x-if="!quickSms.isSending">
                        <span class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-lg">send</span>
                            <span>تأكيد وإرسال SMS</span>
                        </span>
                    </template>
                    <template x-if="quickSms.isSending">
                        <span class="flex items-center gap-2">
                            <span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            <span>جارِ الإرسال...</span>
                        </span>
                    </template>
                </button>

                <button type="button"
                        @click="quickSms.step = 'input'"
                        :disabled="quickSms.isSending"
                        class="w-full py-2.5 px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl flex items-center justify-center gap-1.5 transition-colors disabled:opacity-40">
                    <span class="material-symbols-outlined text-sm">edit</span>
                    <span>تعديل الكشف</span>
                </button>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════
             المرحلة 3: التقرير النهائي (report)
             ═══════════════════════════════════════════════════════════════ --}}
        <div x-show="quickSms.step === 'report'" class="flex flex-col flex-1 overflow-hidden">

            {{-- هيدر التقرير --}}
            <div class="px-5 pb-3 text-center">
                <div class="w-16 h-16 mx-auto rounded-2xl bg-emerald-50 border border-emerald-100 text-emerald-600 flex items-center justify-center shadow-inner mb-3">
                    <span class="material-symbols-outlined text-4xl">task_alt</span>
                </div>
                <h3 class="text-base font-extrabold text-slate-800">تم الإرسال بنجاح!</h3>
                <p class="text-xs text-slate-500 mt-1">
                    تم إدراج <strong class="text-emerald-600" x-text="quickSms.successCount"></strong> طرد في قائمة الإرسال
                </p>
            </div>

            {{-- قائمة النتائج --}}
            <div class="px-5 flex-1 overflow-y-auto pb-3 space-y-1.5" style="max-height: 45vh;">
                <template x-for="(item, idx) in quickSms.items" :key="idx">
                    <div class="flex items-center gap-3 bg-emerald-50/60 rounded-xl px-3.5 py-2.5 border border-emerald-100">
                        <span class="material-symbols-outlined text-base text-emerald-600 shrink-0">check_circle</span>
                        <span class="text-xs font-mono font-bold text-slate-700 dir-ltr" x-text="item.phone"></span>
                        <span class="text-[11px] text-slate-400 mx-1">—</span>
                        <span class="text-xs font-bold text-slate-600 flex-1 truncate text-left" x-text="item.description"></span>
                    </div>
                </template>
            </div>

            {{-- تنبيه الاتصال --}}
            <div class="mx-5 mb-3 bg-amber-50 border border-amber-200/80 rounded-xl p-3 flex items-start gap-2 text-[11px] text-amber-800 leading-snug">
                <span class="material-symbols-outlined text-base text-amber-600 shrink-0 mt-0.5">warning</span>
                <span>
                    <strong>تنبيه:</strong> يجب إبقاء هاتف الإرسال متصلاً بالإنترنت وشاشته نشطة حتى اكتمال إرسال جميع الرسائل.
                </span>
            </div>

            {{-- زر الإغلاق --}}
            <div class="px-5 py-4 border-t border-slate-100 bg-white">
                <button type="button"
                        @click="resetQuickSms()"
                        class="w-full py-3 px-4 bg-primary hover:bg-slate-800 active:scale-[0.98] text-white font-extrabold text-sm rounded-2xl flex items-center justify-center gap-2 transition-all">
                    <span class="material-symbols-outlined text-lg">done_all</span>
                    <span>إغلاق</span>
                </button>
            </div>
        </div>

    </div>
</div>
