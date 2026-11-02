{{-- نافذة منبثقة لإضافة / تعديل عميل مميز (متموضعة في الأعلى Top-Aligned لتجنب تغطية الكيبورد) --}}
<div x-show="modal.open"
     x-cloak
     class="fixed inset-0 z-50 flex items-start justify-center p-3 sm:p-4 pt-3 sm:pt-10 overflow-y-auto"
     @keydown.escape.window="if (!modal.loading) closeModal()">

    <!-- خلفية التعتيم الشفافة مع تمويه Glassmorphism -->
    <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs transition-opacity"
         x-show="modal.open"
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="if (!modal.loading) closeModal()">
    </div>

    <!-- بطاقة النافذة (متموضعة في الأعلى مع حواف دائرية كاملة وأبعاد مريحة فوق الكيبورد) -->
    <div class="relative w-full max-w-sm bg-white rounded-3xl shadow-2xl border border-slate-100 overflow-hidden z-10 flex flex-col text-right my-1 sm:my-auto max-h-[88vh]"
         x-show="modal.open"
         x-transition:enter="ease-out duration-250"
         x-transition:enter-start="opacity-0 -translate-y-4 sm:translate-y-0 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 -translate-y-4 sm:translate-y-0 sm:scale-95">

        <!-- الهيدر -->
        <div class="px-4 py-3 sm:px-5 sm:py-3.5 border-b border-slate-100 flex items-center justify-between bg-slate-50/70 shrink-0">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-secondary/15 text-secondary flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-lg" x-text="modal.mode === 'create' ? 'person_add' : 'edit'"></span>
                </div>
                <div>
                    <h3 class="text-xs sm:text-sm font-extrabold text-slate-800" x-text="modal.mode === 'create' ? 'إضافة عميل مميز جديد' : 'تعديل بيانات العميل'"></h3>
                    <p class="text-[10px] text-slate-400">حفظ محلي فوري ودعم للبحث السريع</p>
                </div>
            </div>

            <button type="button" @click="closeModal()" :disabled="modal.loading"
                    class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-400 hover:text-slate-600 flex items-center justify-center transition-colors">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>

        <!-- الفورم -->
        <form @submit.prevent="saveCustomer()" class="p-3.5 sm:p-5 space-y-2.5 sm:space-y-3.5 overflow-y-auto">
            <!-- حقل رقم الهاتف -->
            <div>
                <label class="block text-[11px] font-bold text-slate-600 mb-1">رقم الهاتف (9 أرقام)</label>
                <div class="relative rounded-2xl border border-slate-200 bg-slate-50/80 focus-within:bg-white focus-within:border-secondary focus-within:ring-2 focus-within:ring-secondary/20 transition-all">
                    
                    <div class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center gap-1.5 select-none pointer-events-none">
                        <span class="text-xs font-bold">🇾🇪</span>
                        <template x-if="isValidPhone(modal.phone)">
                            <span class="material-symbols-outlined text-base text-emerald-500 flex items-center">check_circle</span>
                        </template>
                        <template x-if="modal.phone.length > 0 && !isValidPhone(modal.phone)">
                            <span class="material-symbols-outlined text-base text-rose-400 flex items-center">error</span>
                        </template>
                    </div>

                    <input type="tel"
                           x-ref="customerPhoneInput"
                           x-model="modal.phone"
                           @input="modal.phone = normalizeDigits(modal.phone).substring(0, 9)"
                           dir="ltr"
                           inputmode="numeric"
                           maxlength="9"
                           autocomplete="off"
                           placeholder="7XXXXXXXX"
                           class="w-full py-2 sm:py-2.5 px-4 pr-16 text-sm font-mono font-bold text-slate-800 bg-transparent outline-none placeholder:text-slate-300">
                </div>
            </div>

            <!-- حقل اسم العميل -->
            <div>
                <label class="block text-[11px] font-bold text-slate-600 mb-1">اسم العميل المميز</label>
                <input type="text"
                       x-ref="customerNameInput"
                       x-model="modal.name"
                       dir="rtl"
                       autocomplete="off"
                       placeholder="مثال: محمد عبدالله الصنعاني"
                       class="w-full py-2 sm:py-2.5 px-3.5 text-xs font-bold text-slate-700 rounded-2xl border border-slate-200 bg-slate-50/80 focus:bg-white outline-none focus:border-secondary focus:ring-2 focus:ring-secondary/20 placeholder:text-slate-300 transition-all">
            </div>

            <!-- تنبيه الخطأ إن وُجد -->
            <div x-show="modal.error" x-cloak class="p-2 sm:p-2.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-[11px] font-bold flex items-center gap-1.5">
                <span class="material-symbols-outlined text-base shrink-0">error</span>
                <span x-text="modal.error"></span>
            </div>

            <!-- تنبيه الأوفلاين إن لم يكن متصلاً -->
            <template x-if="!isOnline">
                <div class="p-2 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-[10px] font-bold flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-base text-amber-600 shrink-0">cloud_off</span>
                    <span>بدون إنترنت: سيُحفظ على الجهاز فوراً ومزامنته تلقائياً عند الاتصال.</span>
                </div>
            </template>

            <!-- أزرار الإجراءات -->
            <div class="pt-1 sm:pt-2 flex items-center gap-2">
                <button type="button" @click="closeModal()" :disabled="modal.loading"
                        class="flex-1 py-2 sm:py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 active:scale-95 transition-all">
                    إلغاء
                </button>

                <button type="submit"
                        :disabled="!isValidPhone(modal.phone) || modal.name.trim().length < 2 || modal.loading"
                        class="flex-1 py-2 sm:py-2.5 rounded-xl bg-secondary hover:bg-amber-500 active:scale-95 text-white font-extrabold text-xs shadow-md shadow-secondary/20 flex items-center justify-center gap-1.5 transition-all disabled:opacity-40 disabled:cursor-not-allowed">
                    <template x-if="modal.loading">
                        <span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                    </template>
                    <template x-if="!modal.loading">
                        <span class="material-symbols-outlined text-base">save</span>
                    </template>
                    <span x-text="modal.mode === 'create' ? 'إضافة العميل' : 'حفظ التعديلات'"></span>
                </button>
            </div>
        </form>
    </div>
</div>
