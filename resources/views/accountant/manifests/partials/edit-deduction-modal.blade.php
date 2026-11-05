{{-- نافذة تعديل بند خصم على الكشف (تتموضع في الأعلى items-start pt-4 لعدم تغطية الكيبورد للأزرار) --}}
<div x-show="editDeductionModal.open"
     x-cloak
     @keydown.escape.window="closeEditDeductionModal()"
     class="fixed inset-0 z-50 flex items-start justify-center pt-4 px-3 pb-4 overflow-y-auto">

    <!-- خلفية التعتيم -->
    <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs transition-opacity"
         @click="closeEditDeductionModal()"></div>

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
                <span class="w-7 h-7 rounded-lg bg-rose-500/20 text-rose-300 flex items-center justify-center">
                    <span class="material-symbols-outlined text-lg">edit_note</span>
                </span>
                <div>
                    <h3 class="text-sm font-extrabold leading-tight">تعديل بند الخصم</h3>
                </div>
            </div>
            <button type="button"
                    @click="closeEditDeductionModal()"
                    class="w-7 h-7 rounded-lg bg-white/10 hover:bg-white/20 flex items-center justify-center text-slate-200">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>

        <form @submit.prevent="submitEditDeduction()" class="p-3.5 space-y-3">
            <!-- رسالة خطأ -->
            <div x-show="editDeductionModal.error" x-cloak
                 class="p-2.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold flex items-center gap-2">
                <span class="material-symbols-outlined text-base shrink-0">error</span>
                <span x-text="editDeductionModal.error"></span>
            </div>

            <!-- نوع الخصم -->
            <div class="grid grid-cols-3 gap-1.5">
                <button type="button"
                        @click="editDeductionModal.form.deduction_type = 'workers_unloading'; if(!editDeductionModal.form.title) editDeductionModal.form.title = 'خصم العمال (تنزيل الطرود)'"
                        :class="editDeductionModal.form.deduction_type === 'workers_unloading'
                            ? 'bg-primary text-white border-primary'
                            : 'bg-slate-100 text-slate-700 border-slate-200'"
                        class="py-2 px-2 rounded-xl border text-[11px] font-extrabold text-center transition-all">
                    👷 خصم العمال
                </button>
                <button type="button"
                        @click="editDeductionModal.form.deduction_type = 'office_commission'; if(!editDeductionModal.form.title) editDeductionModal.form.title = 'خصم عمولة المكتب'"
                        :class="editDeductionModal.form.deduction_type === 'office_commission'
                            ? 'bg-primary text-white border-primary'
                            : 'bg-slate-100 text-slate-700 border-slate-200'"
                        class="py-2 px-2 rounded-xl border text-[11px] font-extrabold text-center transition-all">
                    🏢 عمولة المكتب
                </button>
                <button type="button"
                        @click="editDeductionModal.form.deduction_type = 'other'"
                        :class="editDeductionModal.form.deduction_type === 'other'
                            ? 'bg-primary text-white border-primary'
                            : 'bg-slate-100 text-slate-700 border-slate-200'"
                        class="py-2 px-2 rounded-xl border text-[11px] font-extrabold text-center transition-all">
                    ➕ خصم آخر
                </button>
            </div>

            <!-- بيان الخصم + المبلغ -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">بيان الخصم <span class="text-rose-500">*</span>:</label>
                    <input type="text"
                           x-model="editDeductionModal.form.title"
                           maxlength="150"
                           required
                           class="w-full bg-slate-50 focus:bg-white border border-slate-200 focus:border-secondary rounded-xl px-3 py-2 text-xs font-bold text-slate-800 outline-none">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">مبلغ الخصم (ر.ي) <span class="text-rose-500">*</span>:</label>
                    <input type="number"
                           x-model="editDeductionModal.form.amount"
                           inputmode="decimal"
                           min="0.01"
                           step="any"
                           required
                           class="w-full bg-slate-50 focus:bg-white border border-slate-200 focus:border-secondary rounded-xl px-3 py-2 text-xs font-mono font-extrabold text-rose-700 outline-none dir-ltr text-right">
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-600 mb-1">ملاحظة (اختياري):</label>
                <input type="text"
                       x-model="editDeductionModal.form.notes"
                       maxlength="255"
                       placeholder="أي توضيح إضافي..."
                       class="w-full bg-slate-50 focus:bg-white border border-slate-200 focus:border-secondary rounded-xl px-3 py-2 text-xs font-bold text-slate-800 outline-none">
            </div>

            <div class="flex items-center gap-2 pt-2 border-t border-slate-100">
                <button type="submit"
                        :disabled="editDeductionModal.saving"
                        class="flex-1 py-2.5 px-4 rounded-xl bg-secondary hover:brightness-105 text-primary font-extrabold text-xs shadow-md shadow-secondary/20 flex items-center justify-center gap-1.5 active:scale-95 transition-all disabled:opacity-60">
                    <span x-show="!editDeductionModal.saving" class="material-symbols-outlined text-base">save</span>
                    <span x-show="editDeductionModal.saving" class="w-4 h-4 border-2 border-primary border-t-transparent rounded-full animate-spin"></span>
                    <span x-text="editDeductionModal.saving ? 'جاري الحفظ...' : 'حفظ التعديلات'"></span>
                </button>

                <button type="button"
                        @click="closeEditDeductionModal()"
                        class="py-2.5 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs transition-colors">
                    إلغاء
                </button>
            </div>
        </form>
    </div>
</div>

