{{-- مودال تأكيد إرجاع الطرد إلى المكتب المرسل --}}
<div x-show="returnModal.open" 
     x-cloak
     class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-3 sm:p-4"
     @keydown.escape.window="closeReturnModal()">
     
    <!-- خلفية التعتيم الشفافة مع تمويه Glassmorphism -->
    <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm transition-opacity"
         x-show="returnModal.open"
         x-transition:enter="ease-out duration-250"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="closeReturnModal()">
    </div>

    <!-- بطاقة المودال الرئيسية -->
    <div class="relative w-full max-w-sm bg-white rounded-3xl p-5 shadow-2xl border border-slate-100 z-10 transition-all transform flex flex-col gap-4 text-right"
         x-show="returnModal.open"
         x-transition:enter="ease-out duration-250"
         x-transition:enter-start="opacity-0 translate-y-6 sm:translate-y-0 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-6 sm:translate-y-0 sm:scale-95">

        <!-- زر إغلاق علوي -->
        <button type="button"
                @click="closeReturnModal()"
                title="إغلاق"
                class="absolute left-4 top-4 w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-400 hover:text-slate-600 flex items-center justify-center transition-colors">
            <span class="material-symbols-outlined text-base">close</span>
        </button>

        <!-- الهيدر والأيقونة -->
        <div class="text-center pt-1">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-rose-50 border border-rose-100 text-rose-600 flex items-center justify-center shadow-inner mb-3">
                <span class="material-symbols-outlined text-3xl">assignment_return</span>
            </div>
            <h3 class="text-base font-extrabold text-slate-800">
                تأكيد إرجاع الطرد
            </h3>
            <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                هل أنت متأكد من رغبتك في إرجاع هذا الطرد إلى المكتب المُرسِل؟
            </p>
        </div>

        <!-- ملخص بيانات الطرد المراد إرجاعه -->
        <template x-if="returnModal.parcel">
            <div class="bg-slate-50 rounded-2xl p-3.5 border border-slate-200/80 space-y-2.5 text-xs">
                
                <!-- المكتب المرسل (المصدر) -->
                <div class="flex items-center justify-between pb-2 border-b border-slate-200/60">
                    <span class="text-slate-400 flex items-center gap-1 font-medium">
                        <span class="material-symbols-outlined text-sm text-slate-400">store</span>
                        المكتب المرسل:
                    </span>
                    <span class="font-bold text-rose-600 truncate max-w-[180px]"
                          x-text="returnModal.parcel.source_office_name || 'المكتب المصدر'">
                    </span>
                </div>

                <!-- رقم السند ونوع الشحنة -->
                <div class="flex items-center justify-between">
                    <span class="text-slate-400">نوع الشحنة:</span>
                    <span class="font-bold text-slate-700" x-text="returnModal.parcel.package_type"></span>
                </div>

                <div class="flex items-center justify-between" x-show="returnModal.parcel.receipt_number">
                    <span class="text-slate-400">رقم السند:</span>
                    <span class="font-mono font-bold text-slate-700" x-text="'#' + returnModal.parcel.receipt_number"></span>
                </div>

                <!-- بيانات المستلم -->
                <div class="flex items-center justify-between pt-1">
                    <span class="text-slate-400">المستلم:</span>
                    <div class="text-left">
                        <span class="font-bold text-slate-700 block" x-text="returnModal.parcel.recipient_name || 'بدون اسم'"></span>
                        <span class="text-[11px] text-slate-400 font-mono dir-ltr block" x-text="returnModal.parcel.recipient_phone"></span>
                    </div>
                </div>

            </div>
        </template>

        <!-- تنبيه تحذيري -->
        <div class="bg-amber-50 border border-amber-200/80 rounded-xl p-2.5 flex items-start gap-2 text-[11px] text-amber-800 leading-snug">
            <span class="material-symbols-outlined text-base text-amber-600 shrink-0 mt-0.5">warning</span>
            <span>
                <strong>تنبيه:</strong> سيتم تسجيل الطرد كمرتجع رسمي ولن يتمكن المستلم من استلامه من هذا الفرع.
            </span>
        </div>

        <!-- أزرار الإجراءات -->
        <div class="flex flex-col gap-2 pt-1">
            <button type="button"
                    @click="confirmReturn()"
                    class="w-full py-3 px-4 bg-rose-600 hover:bg-rose-700 active:scale-[0.98] text-white font-extrabold text-xs rounded-xl shadow-lg shadow-rose-600/25 flex items-center justify-center gap-2 transition-all">
                <span class="material-symbols-outlined text-base">assignment_return</span>
                <span>تأكيد الإرجاع للمكتب المرسل</span>
            </button>

            <button type="button"
                    @click="closeReturnModal()"
                    class="w-full py-2.5 px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl flex items-center justify-center transition-colors">
                <span>إلغاء وتراجع</span>
            </button>
        </div>

    </div>
</div>
