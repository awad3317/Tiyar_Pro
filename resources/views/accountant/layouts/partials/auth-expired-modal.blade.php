{{-- نافذة منبثقة لتنبيه انتهاء جلسة المحاسب (401) والتوجيه حصرياً إلى تسجيل دخول المحاسب --}}
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
                انتهت جلسة المحاسب
            </h3>
            <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                يرجى إعادة تسجيل الدخول إلى بوابة المحاسب المالي للمتابعة وتحديث البيانات.
            </p>
        </div>

        <!-- أزرار الإجراءات -->
        <div class="flex flex-col gap-2 pt-1">
            <!-- زر التوجيه حصرياً لصفحة تسجيل دخول المحاسب -->
            <a href="{{ route('accountant.login') }}"
               class="w-full py-3 px-4 bg-primary hover:bg-slate-800 active:scale-[0.98] text-white font-extrabold text-xs rounded-xl shadow-lg shadow-primary/25 flex items-center justify-center gap-2 transition-all">
                <span class="material-symbols-outlined text-base">login</span>
                <span>تسجيل دخول المحاسب الآن</span>
            </a>

            <!-- زر إغلاق المودال مؤقتاً -->
            <button type="button"
                    @click="authModal.open = false"
                    class="w-full py-2.5 px-4 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs rounded-xl flex items-center justify-center transition-colors">
                <span>إغلاق مؤقتاً</span>
            </button>
        </div>

    </div>
</div>

