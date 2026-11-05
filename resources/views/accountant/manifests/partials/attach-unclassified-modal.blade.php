{{-- نافذة ضم طرود إضافية من "الطرود غير المصنفة" إلى هذا الكشف (تتموضع في الأعلى items-start pt-4) --}}
<div x-show="attachModal.open"
     x-cloak
     @keydown.escape.window="closeAttachModal()"
     class="fixed inset-0 z-50 flex items-start justify-center pt-4 px-3 pb-4 overflow-y-auto">

    <!-- خلفية التعتيم -->
    <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs transition-opacity"
         @click="closeAttachModal()"></div>

    <!-- بطاقة المودال -->
    <div class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl border border-slate-200 z-10 overflow-hidden my-auto sm:my-0"
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-4 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 -translate-y-4 scale-95">

        <!-- الهيدر -->
        <div class="bg-primary text-white px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="w-7 h-7 rounded-lg bg-secondary/20 text-secondary flex items-center justify-center">
                    <span class="material-symbols-outlined text-lg">playlist_add</span>
                </span>
                <div>
                    <h3 class="text-sm font-extrabold leading-tight">ضم طرود إضافية للكشف</h3>
                    <p class="text-[10px] text-slate-300">اختر من الطرود غير المصنفة في المكتب لضمها لكشف السائق</p>
                </div>
            </div>
            <button type="button"
                    @click="closeAttachModal()"
                    class="w-7 h-7 rounded-lg bg-white/10 hover:bg-white/20 flex items-center justify-center text-slate-200">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>

        <div class="p-3.5 space-y-3">
            <!-- رسالة خطأ -->
            <div x-show="attachModal.error" x-cloak
                 class="p-2.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold flex items-center gap-2">
                <span class="material-symbols-outlined text-base shrink-0">error</span>
                <span x-text="attachModal.error"></span>
            </div>

            <!-- شريط بحث وتحديد الكل للطرود غير المصنفة -->
            <div x-show="unclassifiedParcels.length > 0" class="flex items-center gap-2">
                <div class="relative flex-1">
                    <input type="text"
                           x-model="attachModal.search"
                           placeholder="ابحث بالاسم، رقم الجوال، أو السند..."
                           class="w-full bg-slate-100 focus:bg-white text-slate-800 placeholder-slate-400 text-xs font-bold rounded-xl pr-8 pl-3 py-2 outline-none border border-slate-200 focus:border-secondary">
                    <span class="material-symbols-outlined absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-base pointer-events-none">search</span>
                </div>
                <button type="button"
                        @click="toggleSelectAllUnclassified()"
                        class="shrink-0 py-2 px-2.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-[11px] font-extrabold text-slate-700">
                    <span x-text="attachModal.selectedIds.length > 0 && attachModal.selectedIds.length === filteredUnclassifiedParcels.length ? 'إلغاء الكل' : 'تحديد الكل'"></span>
                </button>
            </div>

            <!-- قائمة الطرود غير المصنفة -->
            <div class="max-h-72 overflow-y-auto space-y-2 pr-0.5">
                <template x-for="up in filteredUnclassifiedParcels" :key="up.id">
                    <label class="flex items-center justify-between gap-2.5 p-2.5 rounded-xl border cursor-pointer transition-all"
                           :class="attachModal.selectedIds.includes(up.id)
                                ? 'border-secondary bg-amber-50/40 ring-1 ring-secondary/30'
                                : 'border-slate-200 bg-slate-50/60 hover:bg-slate-100/70'">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <input type="checkbox"
                                   :value="up.id"
                                   x-model="attachModal.selectedIds"
                                   class="w-4 h-4 rounded border-slate-300 text-secondary focus:ring-secondary shrink-0">
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <p class="text-xs font-extrabold text-slate-800 truncate"
                                       x-text="up.recipient_name || 'بدون اسم'"></p>
                                    <span class="text-[10px] font-bold text-slate-500"
                                          x-text="'(' + (up.package_type || 'طرد') + ')'"></span>
                                </div>
                                <p class="text-[11px] font-mono font-bold text-slate-500 dir-ltr text-right"
                                   x-text="up.recipient_phone + (up.receipt_number ? ' • #' + up.receipt_number : '')"></p>
                            </div>
                        </div>

                        <div class="shrink-0">
                            <template x-if="up.payment_type === 'postpaid'">
                                <span class="px-2 py-0.5 rounded-lg bg-amber-100 text-amber-900 border border-amber-300 text-[10px] font-extrabold dir-ltr"
                                      x-text="'آجل: ' + formatAmount(up.cod_amount)"></span>
                            </template>
                            <template x-if="up.payment_type !== 'postpaid'">
                                <span class="px-2 py-0.5 rounded-lg bg-slate-200/70 text-slate-700 text-[10px] font-bold">محاسب</span>
                            </template>
                        </div>
                    </label>
                </template>

                <!-- حالة عدم وجود طرود غير مصنفة -->
                <div x-show="filteredUnclassifiedParcels.length === 0" class="py-8 text-center text-slate-400">
                    <span class="material-symbols-outlined text-3xl mb-1 text-slate-300">inventory_2</span>
                    <p class="text-xs font-bold text-slate-500">لا توجد طرود غير مصنفة متاحة حالياً في المكتب</p>
                </div>
            </div>

            <!-- أزرار الضم والإلغاء -->
            <div class="flex items-center gap-2 pt-2 border-t border-slate-100">
                <button type="button"
                        @click="submitAttachParcels()"
                        :disabled="attachModal.saving || attachModal.selectedIds.length === 0"
                        class="flex-1 py-2.5 px-4 rounded-xl bg-secondary hover:brightness-105 text-primary font-extrabold text-xs shadow-md shadow-secondary/20 flex items-center justify-center gap-1.5 active:scale-95 transition-all disabled:opacity-50">
                    <span x-show="!attachModal.saving" class="material-symbols-outlined text-base">add_task</span>
                    <span x-show="attachModal.saving" class="w-4 h-4 border-2 border-primary border-t-transparent rounded-full animate-spin"></span>
                    <span x-text="attachModal.saving ? 'جاري الضم...' : 'ضم (' + attachModal.selectedIds.length + ') طرد للكشف'"></span>
                </button>

                <button type="button"
                        @click="closeAttachModal()"
                        class="py-2.5 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs transition-colors">
                    إلغاء
                </button>
            </div>
        </div>
    </div>
</div>

