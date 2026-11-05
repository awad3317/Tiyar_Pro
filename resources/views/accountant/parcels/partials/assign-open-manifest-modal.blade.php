{{-- نافذة ضم الطرود المحددة إلى كشف سائق مفتوح حالياً (items-start pt-4) --}}
<div x-show="assignModal.open"
     x-cloak
     @keydown.escape.window="closeAssignModal()"
     class="fixed inset-0 z-50 flex items-start justify-center pt-4 px-3 pb-4 overflow-y-auto">

    <!-- خلفية التعتيم -->
    <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs transition-opacity"
         @click="closeAssignModal()"></div>

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
                    <span class="material-symbols-outlined text-lg">folder_open</span>
                </span>
                <div>
                    <h3 class="text-sm font-extrabold leading-tight">ضم الطرود إلى كشف مفتوح</h3>
                    <p class="text-[10px] text-slate-300">اختر أحد الكشوفات المفتوحة حالياً لضم الطرود المحددة إليه</p>
                </div>
            </div>
            <button type="button"
                    @click="closeAssignModal()"
                    class="w-7 h-7 rounded-lg bg-white/10 hover:bg-white/20 flex items-center justify-center text-slate-200">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>

        <!-- المحتوى -->
        <form @submit.prevent="submitAssignToOpenManifest()" class="p-3.5 space-y-3">

            <!-- ملخص الطرود المحددة -->
            <div class="p-2.5 rounded-xl bg-amber-50 border border-amber-300 text-amber-900 text-xs font-extrabold flex items-center justify-between">
                <div class="flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-base text-amber-600">inventory_2</span>
                    <span>الطرود المحددة: (<span x-text="selectedIds.length"></span>) طرد</span>
                </div>
                <span class="px-2 py-0.5 rounded-lg bg-amber-200/70 text-amber-950 text-[11px] dir-ltr"
                      x-text="'آجل: ' + formatAmount(selectedPostpaidAmount) + ' ر.ي'"></span>
            </div>

            <!-- رسالة خطأ إن وجدت -->
            <div x-show="assignModal.error" x-cloak
                 class="p-2.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold flex items-center gap-2">
                <span class="material-symbols-outlined text-base shrink-0">error</span>
                <span x-text="assignModal.error"></span>
            </div>

            <!-- قائمة الكشوفات المفتوحة للاختيار -->
            <template x-if="openManifests.length > 0">
                <div class="space-y-2 max-h-[50vh] overflow-y-auto pr-0.5">
                    <label class="block text-[11px] font-extrabold text-slate-700">اختر الكشف المفتوح:</label>

                    <template x-for="om in openManifests" :key="om.id">
                        <label class="flex items-start gap-2.5 p-3 rounded-xl border cursor-pointer transition-all"
                               :class="String(assignModal.manifestId) === String(om.id)
                                    ? 'border-secondary bg-amber-50/40 ring-2 ring-secondary/20'
                                    : 'border-slate-200 hover:bg-slate-50'">
                            <input type="radio"
                                   name="target_open_manifest_id"
                                   :value="om.id"
                                   x-model="assignModal.manifestId"
                                   class="mt-1 w-4 h-4 text-secondary focus:ring-secondary border-slate-300">

                            <div class="min-w-0 flex-1 text-xs">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-extrabold text-slate-800 truncate" x-text="om.driver_name"></span>
                                    <span class="font-mono text-[10px] font-bold text-slate-500 dir-ltr" x-text="om.manifest_date"></span>
                                </div>

                                <div class="flex items-center justify-between gap-2 mt-1 text-[11px] text-slate-500">
                                    <span class="truncate"
                                          x-text="'من: ' + (om.source_office_name || '—') + (om.source_branch_name ? ' (' + om.source_branch_name + ')' : '')"></span>
                                    <span class="shrink-0 font-bold text-amber-800"
                                          x-text="'طروده: ' + (om.parcels_count || 0)"></span>
                                </div>
                            </div>
                        </label>
                    </template>
                </div>
            </template>

            <!-- في حال عدم وجود أي كشف مفتوح حالياً -->
            <template x-if="openManifests.length === 0">
                <div class="py-6 text-center space-y-2.5 bg-slate-50 rounded-xl border border-slate-200/80 p-4">
                    <span class="material-symbols-outlined text-3xl text-slate-400">folder_off</span>
                    <p class="text-xs font-bold text-slate-600">لا توجد كشوفات مفتوحة حالياً لهذا المكتب</p>
                    <button type="button"
                            @click="closeAssignModal(); openCreateManifestWithParcelsModal()"
                            class="inline-flex items-center gap-1.5 py-2 px-3.5 rounded-xl bg-secondary text-primary font-extrabold text-xs shadow-2xs">
                        <span class="material-symbols-outlined text-sm">add</span>
                        <span>إنشاء كشف سائق جديد وضم الطرود إليه</span>
                    </button>
                </div>
            </template>

            <!-- أزرار التأكيد والإلغاء -->
            <div class="flex items-center gap-2 pt-2 border-t border-slate-100">
                <button type="submit"
                        x-show="openManifests.length > 0"
                        :disabled="assignModal.saving || !assignModal.manifestId"
                        class="flex-1 py-2.5 px-4 rounded-xl bg-primary hover:bg-slate-800 text-white font-extrabold text-xs shadow-md flex items-center justify-center gap-1.5 active:scale-95 transition-all disabled:opacity-60">
                    <span x-show="!assignModal.saving" class="material-symbols-outlined text-base text-secondary">drive_file_move</span>
                    <span x-show="assignModal.saving" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                    <span x-text="assignModal.saving ? 'جاري ضم الطرود...' : 'تأكيد ضم (' + selectedIds.length + ') طرد للكشف'"></span>
                </button>

                <button type="button"
                        @click="closeAssignModal()"
                        class="py-2.5 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs transition-colors">
                    إلغاء
                </button>
            </div>
        </form>
    </div>
</div>

