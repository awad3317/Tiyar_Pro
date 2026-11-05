{{-- نافذة تعديل الطرد للمحاسب (تتموضع في الأعلى items-start pt-4 حتى لا يغطي كيبورد الجوال زر الحفظ) --}}
<div x-show="editModal.open"
     x-cloak
     @keydown.escape.window="closeEditModal()"
     class="fixed inset-0 z-50 flex items-start justify-center pt-4 px-3 pb-4 overflow-y-auto">

    <!-- خلفية التعتيم -->
    <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs transition-opacity"
         @click="closeEditModal()"></div>

    <!-- بطاقة المودال -->
    <div class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl border border-slate-200 z-10 overflow-hidden my-auto sm:my-0"
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-4 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 -translate-y-4 scale-95">

        <!-- الهيدر المدمج -->
        <div class="bg-primary text-white px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="w-7 h-7 rounded-lg bg-secondary/20 text-secondary flex items-center justify-center">
                    <span class="material-symbols-outlined text-lg">edit_note</span>
                </span>
                <div>
                    <h3 class="text-sm font-extrabold leading-tight">تعديل بيانات الطرد </h3>
                </div>
            </div>
            <button type="button"
                    @click="closeEditModal()"
                    class="w-7 h-7 rounded-lg bg-white/10 hover:bg-white/20 flex items-center justify-center text-slate-200">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>

        <!-- النموذج -->
        <form @submit.prevent="submitParcelEdit()" class="p-3.5 space-y-3">

            <!-- رسالة خطأ إن وجدت -->
            <div x-show="editModal.error" x-cloak
                 class="p-2.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold flex items-center gap-2">
                <span class="material-symbols-outlined text-base shrink-0">error</span>
                <span x-text="editModal.error"></span>
            </div>

            <!-- 1. مفتاح التبديل السريع: آجل (عليه مبلغ) / محاسب (خالص) -->
            <div>
                <label class="block text-[11px] font-extrabold text-slate-600 mb-1">نوع الحساب المالي للطرد:</label>
                <div class="grid grid-cols-2 gap-2 p-1 bg-slate-100 rounded-xl border border-slate-200">
                    <button type="button"
                            @click="editModal.form.payment_type = 'postpaid'; $nextTick(() => $refs.editCodInput?.focus())"
                            :class="editModal.form.payment_type === 'postpaid'
                                ? 'bg-amber-500 text-white shadow-sm font-extrabold'
                                : 'text-slate-600 hover:bg-white/60 font-bold'"
                            class="py-2 px-3 rounded-lg text-xs flex items-center justify-center gap-1.5 transition-all">
                        <span>💰</span>
                        <span>آجل (عليه مبلغ)</span>
                    </button>

                    <button type="button"
                            @click="editModal.form.payment_type = 'prepaid'; editModal.form.cod_amount = ''"
                            :class="editModal.form.payment_type === 'prepaid'
                                ? 'bg-emerald-600 text-white shadow-sm font-extrabold'
                                : 'text-slate-600 hover:bg-white/60 font-bold'"
                            class="py-2 px-3 rounded-lg text-xs flex items-center justify-center gap-1.5 transition-all">
                        <span>✅</span>
                        <span>محاسب (خالص)</span>
                    </button>
                </div>
            </div>

            <!-- 2. حقل مبلغ الآجل (يظهر ويُفعّل عندما يكون الخيار "آجل") -->
            <div x-show="editModal.form.payment_type === 'postpaid'"
                 x-transition
                 class="bg-amber-50/80 border border-amber-200 rounded-xl p-2.5">
                <label class="block text-[11px] font-extrabold text-amber-900 mb-1">
                    المبلغ المطلوب عند التسليم (الآجل بالريال اليمني):
                </label>
                <div class="relative">
                    <input type="number"
                           x-ref="editCodInput"
                           x-model="editModal.form.cod_amount"
                           inputmode="decimal"
                           min="0"
                           step="any"
                           placeholder="أدخل مبلغ الآجل (مثال: 3000)"
                           class="w-full bg-white border border-amber-300 focus:border-amber-500 rounded-xl py-2 pr-3 pl-12 text-sm font-extrabold text-slate-900 outline-none dir-ltr text-right">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-extrabold text-amber-700">ر.ي</span>
                </div>
            </div>

            <!-- 3. اسم المستلم + رقم الجوال -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">اسم المستلم:</label>
                    <input type="text"
                           x-ref="editRecipientNameInput"
                           x-model="editModal.form.recipient_name"
                           maxlength="100"
                           placeholder="اكتب اسم المستلم الكامل..."
                           class="w-full bg-slate-50 focus:bg-white border border-slate-200 focus:border-secondary rounded-xl px-3 py-2 text-xs font-bold text-slate-800 outline-none">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">رقم جوال المستلم <span class="text-rose-500">*</span>:</label>
                    <input type="tel"
                           x-model="editModal.form.recipient_phone"
                           inputmode="numeric"
                           maxlength="9"
                           required
                           placeholder="7XXXXXXXX"
                           class="w-full bg-slate-50 focus:bg-white border border-slate-200 focus:border-secondary rounded-xl px-3 py-2 text-xs font-mono font-bold text-slate-800 outline-none dir-ltr text-right">
                </div>
            </div>

            <!-- 4. نوع الطرد مع أزرار اختصار سريعة -->
            <div>
                <label class="block text-[11px] font-bold text-slate-600 mb-1">نوع الطرد <span class="text-rose-500">*</span>:</label>
                <input type="text"
                       x-model="editModal.form.package_type"
                       maxlength="100"
                       required
                       placeholder="طرد، كيس، ضرف، كرتون..."
                       class="w-full bg-slate-50 focus:bg-white border border-slate-200 focus:border-secondary rounded-xl px-3 py-2 text-xs font-bold text-slate-800 outline-none">

                <!-- أزرار الاختصار السريع لنوع الطرد -->
                <div class="flex items-center gap-1.5 mt-1.5 flex-wrap">
                    <template x-for="quickType in ['طرد', 'كيس', 'ضرف', 'كرتون']" :key="quickType">
                        <button type="button"
                                @click="editModal.form.package_type = quickType"
                                :class="editModal.form.package_type === quickType
                                    ? 'bg-primary text-white border-primary'
                                    : 'bg-slate-100 text-slate-600 border-slate-200 hover:bg-slate-200'"
                                class="px-2.5 py-1 rounded-lg text-[11px] font-bold border transition-colors"
                                x-text="quickType"></button>
                    </template>
                </div>
            </div>

            <!-- 5. رقم السند + المكتب المرسل -->
            {{-- <div class="grid grid-cols-2 gap-2.5">
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">رقم السند:</label>
                    <input type="text"
                           x-model="editModal.form.receipt_number"
                           maxlength="50"
                           placeholder="اختياري..."
                           class="w-full bg-slate-50 focus:bg-white border border-slate-200 focus:border-secondary rounded-xl px-3 py-2 text-xs font-mono font-bold text-slate-800 outline-none">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">المكتب المرسل:</label>
                    <input type="text"
                           x-model="editModal.form.source_office_name"
                           maxlength="100"
                           placeholder="اسم المكتب المرسل..."
                           class="w-full bg-slate-50 focus:bg-white border border-slate-200 focus:border-secondary rounded-xl px-3 py-2 text-xs font-bold text-slate-800 outline-none">
                </div>
            </div> --}}

            <!-- أزرار الحفظ والإلغاء -->
            <div class="flex items-center gap-2 pt-2 border-t border-slate-100">
                <button type="submit"
                        :disabled="editModal.saving"
                        class="flex-1 py-2.5 px-4 rounded-xl bg-secondary hover:brightness-105 text-primary font-extrabold text-xs shadow-md shadow-secondary/20 flex items-center justify-center gap-1.5 active:scale-95 transition-all disabled:opacity-60">
                    <span x-show="!editModal.saving" class="material-symbols-outlined text-base">save</span>
                    <span x-show="editModal.saving" class="w-4 h-4 border-2 border-primary border-t-transparent rounded-full animate-spin"></span>
                    <span x-text="editModal.saving ? 'جاري حفظ التعديلات...' : 'حفظ التعديلات'"></span>
                </button>

                {{-- <button type="button"
                        @click="closeEditModal()"
                        class="py-2.5 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs transition-colors">
                    إلغاء
                </button> --}}
            </div>
        </form>
    </div>
</div>

