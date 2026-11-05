{{-- نافذة عرض سجل تعديلات المحاسبين على الطرد (Audit Log Modal) --}}
<div x-show="logsModal.open"
     x-cloak
     @keydown.escape.window="logsModal.open = false"
     class="fixed inset-0 z-50 flex items-start justify-center pt-6 px-3 pb-4 overflow-y-auto">

    <!-- خلفية التعتيم -->
    <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs transition-opacity"
         @click="logsModal.open = false"></div>

    <!-- بطاقة السجل -->
    <div class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl border border-slate-200 z-10 overflow-hidden">

        <!-- الهيدر -->
        <div class="bg-primary text-white px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="w-7 h-7 rounded-lg bg-blue-500/20 text-blue-300 flex items-center justify-center">
                    <span class="material-symbols-outlined text-lg">history</span>
                </span>
                <div>
                    <h3 class="text-sm font-extrabold leading-tight">سجل تتبع تعديلات الطرد</h3>
                    <p class="text-[10px] text-slate-300" x-text="logsModal.parcel ? (logsModal.parcel.recipient_name || 'بدون اسم') + ' — ' + logsModal.parcel.recipient_phone : ''"></p>
                </div>
            </div>
            <button type="button"
                    @click="logsModal.open = false"
                    class="w-7 h-7 rounded-lg bg-white/10 hover:bg-white/20 flex items-center justify-center text-slate-200">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>

        <!-- محتوى السجل -->
        <div class="p-3.5 max-h-[70vh] overflow-y-auto space-y-3">
            <!-- جاري التحميل -->
            <div x-show="logsModal.loading" class="py-8 text-center text-slate-400">
                <span class="w-6 h-6 border-2 border-primary border-t-transparent rounded-full animate-spin inline-block mb-2"></span>
                <p class="text-xs font-bold">جاري تحميل سجل التعديلات...</p>
            </div>

            <!-- قائمة السجلات -->
            <template x-if="!logsModal.loading && logsModal.logs.length > 0">
                <div class="space-y-2.5">
                    <template x-for="log in logsModal.logs" :key="log.id">
                        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3 space-y-2">
                            <!-- رأس الحركة: اسم المحاسب والتاريخ -->
                            <div class="flex items-center justify-between gap-2 pb-2 border-b border-slate-200/80">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-6 h-6 rounded-lg bg-primary text-secondary flex items-center justify-center">
                                        <span class="material-symbols-outlined text-sm">person</span>
                                    </span>
                                    <div>
                                        <p class="text-xs font-extrabold text-slate-800" x-text="log.accountant_name"></p>
                                    </div>
                                </div>
                                <div class="text-left">
                                    <span class="block text-[10px] font-mono font-bold text-slate-600 dir-ltr" x-text="log.created_at_formatted"></span>
                                    <span class="block text-[10px] text-slate-400" x-text="log.created_at_human"></span>
                                </div>
                            </div>

                            <!-- الحقول التي تغيرت (قبل -> بعد) -->
                            <div class="space-y-1.5">
                                <template x-for="change in log.changes" :key="change.field">
                                    <div class="bg-white rounded-lg p-2 border border-slate-200/60 text-[11px]">
                                        <span class="font-extrabold text-slate-700 block mb-1" x-text="change.label + ':'"></span>
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="px-2 py-0.5 rounded bg-rose-50 text-rose-700 border border-rose-200 line-through font-bold"
                                                  x-text="'قبل: ' + change.old_text"></span>
                                            <span class="material-symbols-outlined text-xs text-slate-400">arrow_back</span>
                                            <span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 font-extrabold"
                                                  x-text="'بعد: ' + change.new_text"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <!-- لا توجد سجلات -->
            <div x-show="!logsModal.loading && logsModal.logs.length === 0" class="py-8 text-center text-slate-400">
                <span class="material-symbols-outlined text-3xl mb-1">history_toggle_off</span>
                <p class="text-xs font-bold">لا توجد تعديلات مسجلة لهذا الطرد بعد</p>
            </div>
        </div>

        <div class="p-3 bg-slate-50 border-t border-slate-200 flex justify-end">
            <button type="button"
                    @click="logsModal.open = false"
                    class="py-2 px-4 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold text-xs transition-colors">
                إغلاق
            </button>
        </div>
    </div>
</div>

