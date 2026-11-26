{{-- مودال تعديل بيانات الطرد المستلم (متاح فقط عندما تكون حالة الطرد "في المكتب") --}}
@php
    $editOffice = Auth::guard('office')->user();
    $canEditCod = $editOffice && $editOffice->hasFeature('parcel_cod_amount');
    $canEditRegion = $editOffice && $editOffice->hasFeature('parcel_source_region');
    $canEditSlot = $editOffice && $editOffice->hasFeature('parcel_slot_number');
@endphp
<div x-show="editModal.open"
     x-cloak
     class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-3 sm:p-4"
     @keydown.escape.window="closeEditModal()">

    <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm transition-opacity"
         x-show="editModal.open"
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="closeEditModal()">
    </div>

    <div class="relative w-full max-w-md max-h-[90vh] overflow-y-auto bg-white lg:dark:bg-[#24303F] rounded-3xl p-5 shadow-2xl border border-slate-100 lg:dark:border-slate-700/60 z-10 transition-all transform flex flex-col gap-3.5 text-right"
         x-show="editModal.open"
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-6 sm:translate-y-0 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-6 sm:translate-y-0 sm:scale-95">

        <button type="button"
                @click="closeEditModal()"
                :disabled="editModal.submitting"
                title="إغلاق"
                class="absolute left-4 top-4 w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-400 hover:text-slate-600 lg:dark:bg-slate-800 lg:dark:hover:bg-slate-700 lg:dark:text-slate-300 flex items-center justify-center transition-colors disabled:opacity-50">
            <span class="material-symbols-outlined text-base">close</span>
        </button>

        <div class="flex items-center gap-3 pt-0.5">
            <div class="w-11 h-11 rounded-2xl bg-amber-50 border border-amber-200/80 lg:dark:bg-amber-500/15 lg:dark:border-amber-500/30 text-amber-600 lg:dark:text-amber-400 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-2xl">edit_note</span>
            </div>
            <div class="min-w-0">
                <h3 class="text-sm font-extrabold text-slate-800 lg:dark:text-slate-100">تعديل بيانات الطرد</h3>
                <p class="text-[11px] text-slate-500 lg:dark:text-slate-400 mt-0.5">متاح فقط للطرود المتواجدة حالياً «في المكتب»</p>
            </div>
        </div>

        <div x-show="editModal.error"
             x-cloak
             class="px-3 py-2 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 lg:dark:bg-rose-500/15 lg:dark:border-rose-500/30 lg:dark:text-rose-300 text-xs font-bold flex items-center gap-1.5">
            <span class="material-symbols-outlined text-base shrink-0">error</span>
            <span x-text="editModal.error"></span>
        </div>

        <form data-offline-capable="true"
              @submit.prevent="submitEditParcel()"
              class="space-y-3">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 lg:dark:text-slate-300 mb-1">
                        نوع الطرد <span class="text-rose-500">*</span>
                    </label>
                    <input type="text"
                           x-model="editModal.form.package_type"
                           required
                           maxlength="150"
                           placeholder="مثال: كرتون، كيس، ظرف..."
                           class="w-full py-2.5 px-3 text-xs font-bold text-slate-800 lg:dark:text-slate-100 rounded-xl border border-slate-200 lg:dark:border-slate-700 bg-slate-50 lg:dark:bg-[#1A222C] focus:bg-white lg:dark:focus:bg-[#1A222C] focus:border-secondary outline-none transition-colors">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 lg:dark:text-slate-300 mb-1">
                        رقم السند <span class="text-[10px] font-normal text-slate-400">(اختياري)</span>
                    </label>
                    <input type="text"
                           x-model="editModal.form.receipt_number"
                           maxlength="50"
                           dir="ltr"
                           placeholder="مثال: 105"
                           class="w-full py-2.5 px-3 text-xs font-mono font-bold text-slate-800 lg:dark:text-slate-100 rounded-xl border border-slate-200 lg:dark:border-slate-700 bg-slate-50 lg:dark:bg-[#1A222C] focus:bg-white lg:dark:focus:bg-[#1A222C] focus:border-secondary outline-none transition-colors text-right">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 lg:dark:text-slate-300 mb-1">
                        اسم المستلم <span class="text-[10px] font-normal text-slate-400">(اختياري)</span>
                    </label>
                    <input type="text"
                           x-model="editModal.form.recipient_name"
                           maxlength="120"
                           placeholder="اسم المستلم"
                           class="w-full py-2.5 px-3 text-xs font-bold text-slate-800 lg:dark:text-slate-100 rounded-xl border border-slate-200 lg:dark:border-slate-700 bg-slate-50 lg:dark:bg-[#1A222C] focus:bg-white lg:dark:focus:bg-[#1A222C] focus:border-secondary outline-none transition-colors">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 lg:dark:text-slate-300 mb-1">
                        رقم جوال المستلم <span class="text-rose-500">*</span>
                    </label>
                    <input type="tel"
                           x-model="editModal.form.recipient_phone"
                           required
                           dir="ltr"
                           maxlength="20"
                           placeholder="77XXXXXXX"
                           class="w-full py-2.5 px-3 text-xs font-mono font-bold text-slate-800 lg:dark:text-slate-100 rounded-xl border border-slate-200 lg:dark:border-slate-700 bg-slate-50 lg:dark:bg-[#1A222C] focus:bg-white lg:dark:focus:bg-[#1A222C] focus:border-secondary outline-none transition-colors text-right">
                </div>
            </div>

            @if($canEditCod || $canEditRegion || $canEditSlot)
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @if($canEditCod)
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 lg:dark:text-slate-300 mb-1">
                                المبلغ المطلوب (آجل - ر.ي)
                            </label>
                            <input type="number"
                                   step="any"
                                   min="0"
                                   x-model="editModal.form.cod_amount"
                                   dir="ltr"
                                   placeholder="0 = خالص / محاسب"
                                   class="w-full py-2.5 px-3 text-xs font-mono font-bold text-slate-800 lg:dark:text-slate-100 rounded-xl border border-slate-200 lg:dark:border-slate-700 bg-slate-50 lg:dark:bg-[#1A222C] focus:bg-white lg:dark:focus:bg-[#1A222C] focus:border-secondary outline-none transition-colors text-right">
                        </div>
                    @endif

                    @if($canEditSlot)
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 lg:dark:text-slate-300 mb-1">
                                رقم الخانة (الرف)
                            </label>
                            <input type="text"
                                   x-model="editModal.form.slot_number"
                                   maxlength="50"
                                   placeholder="مثال: 12"
                                   class="w-full py-2.5 px-3 text-xs font-bold text-slate-800 lg:dark:text-slate-100 rounded-xl border border-slate-200 lg:dark:border-slate-700 bg-slate-50 lg:dark:bg-[#1A222C] focus:bg-white lg:dark:focus:bg-[#1A222C] focus:border-secondary outline-none transition-colors">
                        </div>
                    @endif

                    @if($canEditRegion)
                        <div class="{{ ($canEditCod && $canEditSlot) ? 'sm:col-span-2' : '' }}">
                            <label class="block text-[11px] font-bold text-slate-600 lg:dark:text-slate-300 mb-1">
                                منطقة القدوم
                            </label>
                            <input type="text"
                                   x-model="editModal.form.source_region"
                                   maxlength="100"
                                   placeholder="مثال: صنعاء، المكلا..."
                                   class="w-full py-2.5 px-3 text-xs font-bold text-slate-800 lg:dark:text-slate-100 rounded-xl border border-slate-200 lg:dark:border-slate-700 bg-slate-50 lg:dark:bg-[#1A222C] focus:bg-white lg:dark:focus:bg-[#1A222C] focus:border-secondary outline-none transition-colors">
                        </div>
                    @endif
                </div>
            @endif

            <div class="flex flex-col sm:flex-row gap-2 pt-2">
                <button type="submit"
                        :disabled="editModal.submitting"
                        class="flex-1 py-2.5 px-4 bg-amber-500 hover:bg-amber-600 active:scale-[0.98] text-slate-950 font-extrabold text-xs rounded-xl shadow-md shadow-amber-500/20 flex items-center justify-center gap-1.5 transition-all disabled:opacity-50">
                    <span class="material-symbols-outlined text-base" x-text="editModal.submitting ? 'sync' : 'save'" :class="editModal.submitting ? 'animate-spin' : ''"></span>
                    <span x-text="editModal.submitting ? 'جاري حفظ التعديلات...' : 'حفظ التعديلات'"></span>
                </button>

                <button type="button"
                        @click="closeEditModal()"
                        :disabled="editModal.submitting"
                        class="py-2.5 px-4 bg-slate-100 hover:bg-slate-200 lg:dark:bg-slate-800 lg:dark:hover:bg-slate-700 text-slate-700 lg:dark:text-slate-200 font-bold text-xs rounded-xl flex items-center justify-center transition-colors disabled:opacity-50">
                    إلغاء
                </button>
            </div>
        </form>
    </div>
</div>
