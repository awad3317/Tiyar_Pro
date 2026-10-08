{{-- مودال عام لتنبيه انتهاء الجلسة وحفظ التعديلات --}}
<div x-show="authModal?.open" 
     x-cloak
     class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-3 sm:p-4">
     
    <!-- خلفية التعتيم الشفافة مع تمويه Glassmorphism -->
    <div class="fixed inset-0 bg-slate-950/65 backdrop-blur-sm transition-opacity"
         x-show="authModal?.open"
         x-transition:enter="ease-out duration-250"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
    </div>

    <!-- بطاقة المودال الرئيسية -->
    <div class="relative w-full max-w-sm bg-white rounded-3xl p-5 shadow-2xl border border-slate-100 z-10 transition-all transform flex flex-col gap-4 text-right"
         x-show="authModal?.open"
         x-transition:enter="ease-out duration-250"
         x-transition:enter-start="opacity-0 translate-y-6 sm:translate-y-0 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-6 sm:translate-y-0 sm:scale-95">

        <!-- الهيدر والأيقونة -->
        <div class="text-center pt-1">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-amber-50 border border-amber-100 text-amber-600 flex items-center justify-center shadow-inner mb-3">
                <span class="material-symbols-outlined text-3xl animate-pulse">lock_clock</span>
            </div>
            <h3 class="text-base font-extrabold text-slate-800">
                انتهت جلسة تسجيل الدخول
            </h3>
            <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                يلزم إعادة تسجيل الدخول لمزامنة وتحديث البيانات مع السيرفر.
            </p>
        </div>

        <!-- بطاقة طمأنة المستخدم بسلامة البيانات محلياً -->
        <div class="bg-emerald-50 border border-emerald-200/80 rounded-2xl p-3 flex items-start gap-2.5 text-xs text-emerald-900 leading-snug">
            <span class="material-symbols-outlined text-base text-emerald-600 shrink-0 mt-0.5">verified_user</span>
            <div>
                <strong class="block font-bold mb-0.5">بياناتك محفوظة بأمان!</strong>
                <span>
                    تم حفظ التعديلات محلياً في جهازك
                    <template x-if="syncQueue && syncQueue.length > 0">
                        <span>(<span class="font-bold font-mono" x-text="syncQueue.length"></span> تعديل معلق)</span>
                    </template>
                    ، ولن تفقد أي عملية قمت بها.
                </span>
            </div>
        </div>

        <!-- أزرار الإجراءات -->
        <div class="flex flex-col gap-2 pt-1">
            <!-- زر التوجيه لصفحة تسجيل الدخول -->
            <a href="{{ route('office.login') }}"
               class="w-full py-3 px-4 bg-primary hover:bg-primary-dark active:scale-[0.98] text-white font-extrabold text-xs rounded-xl shadow-lg shadow-primary/25 flex items-center justify-center gap-2 transition-all">
                <span class="material-symbols-outlined text-base">login</span>
                <span>تسجيل الدخول الآن</span>
            </a>

            <!-- زر البقاء والعمل أوفلاين -->
            <button type="button"
                    @click="authModal.open = false"
                    class="w-full py-2.5 px-4 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs rounded-xl flex items-center justify-center transition-colors">
                <span>البقاء والعمل محلياً مؤقتاً</span>
            </button>
        </div>

    </div>
</div>