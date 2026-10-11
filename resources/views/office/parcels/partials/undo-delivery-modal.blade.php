{{-- مودال تأكيد إلغاء التسليم وإرجاع الطرد إلى المكتب الحالي --}}
<div x-show="undoDeliveryModal.open" 
     x-cloak
     class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-3 sm:p-4"
     @keydown.escape.window="closeUndoDeliveryModal()">
     
    <!-- خلفية التعتيم الشفافة مع تمويه Glassmorphism -->
    <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm transition-opacity"
         x-show="undoDeliveryModal.open"
         x-transition:enter="ease-out duration-250"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="closeUndoDeliveryModal()">
    </div>

    <!-- بطاقة المودال الرئيسية -->
    <div class="relative w-full max-w-sm bg-white rounded-3xl p-5 shadow-2xl border border-slate-100 z-10 transition-all transform flex flex-col gap-4 text-right"
         x-show="undoDeliveryModal.open"
         x-transition:enter="ease-out duration-250"
         x-transition:enter-start="opacity-0 translate-y-6 sm:translate-y-0 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-6 sm:translate-y-0 sm:scale-95">

        <!-- زر إغلاق علوي -->
        <button type="button"
                @click="closeUndoDeliveryModal()"
                title="إغلاق"
                class="absolute left-4 top-4 w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-400 hover:text-slate-600 flex items-center justify-center transition-colors">
            <span class="material-symbols-outlined text-base">close</span>
        </button>

        <!-- الهيدر والأيقونة -->
        <div class="text-center pt-1">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-amber-50 border border-amber-100 text-amber-600 flex items-center justify-center shadow-inner mb-3">
                <span class="material-symbols-outlined text-3xl">undo</span>
            </div>
            <h3 class="text-base font-extrabold text-slate-800">
                تأكيد التراجع عن التسليم
            </h3>
            <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                هل أنت متأكد من رغبتك في التراجع عن تسليم هذا الطرد وإعادته كطرد متاح في المكتب؟
            </p>
        </div>

        <!-- ملخص بيانات الطرد المراد إرجاعه -->
        <template x-if="undoDeliveryModal.parcel">
            <div class="bg-slate-50 rounded-2xl p-3.5 border border-slate-200/80 space-y-2.5 text-xs">
                
                <!-- نوع الشحنة -->
                <div class="flex items-center justify-between">
                    <span class="text-slate-400">نوع الشحنة:</span>
                    <span class="font-bold text-slate-700" x-text="undoDeliveryModal.parcel.package_type"></span>
                </div>

                <!-- رقم السند -->
                <div class="flex items-center justify-between" x-show="undoDeliveryModal.parcel.receipt_number">
                    <span class="text-slate-400">رقم السند:</span>
                    <span class="font-mono font-bold text-slate-700" x-text="'#' + undoDeliveryModal.parcel.receipt_number"></span>
                </div>

                <!-- بيانات المستلم -->
                <div class="flex items-center justify-between pt-1">
                    <span class="text-slate-400">المستلم:</span>
                    <div class="text-left">
                        <span class="font-bold text-slate-700 block" x-text="undoDeliveryModal.parcel.recipient_name || 'بدون اسم'"></span>
                        <span class="text-[11px] text-slate-400 font-mono dir-ltr block" x-text="undoDeliveryModal.parcel.recipient_phone"></span>
                    </div>
                </div>

                <!-- تاريخ التسليم السابق -->
                <div class="flex items-center justify-between pt-1 border-t border-slate-200/60" x-show="undoDeliveryModal.parcel.delivered_at">
                    <span class="text-slate-400 flex items-center gap-1 font-medium">
                        <span class="material-symbols-outlined text-sm text-slate-400">event_available</span>
                        تاريخ التسليم:
                    </span>
                    <span class="font-bold text-emerald-600" x-text="formatDate(undoDeliveryModal.parcel.delivered_at)"></span>
                </div>

            </div>
        </template>

        <!-- تنبيه تحذيري -->
        <div class="bg-amber-50 border border-amber-200/80 rounded-xl p-2.5 flex items-start gap-2 text-[11px] text-amber-800 leading-snug">
            <span class="material-symbols-outlined text-base text-amber-600 shrink-0 mt-0.5">info</span>
            <span>
                <strong>ملاحظة:</strong> سيتم إلغاء تسجيل التسليم وسيعود الطرد إلى قائمة الطرود المتاحة في المكتب الحالي.
            </span>
        </div>

        <!-- أزرار الإجراءات -->
        <div class="flex flex-col gap-2 pt-1">
            <button type="button"
                    @click="confirmUndoDelivery()"
                    class="w-full py-3 px-4 bg-amber-600 hover:bg-amber-700 active:scale-[0.98] text-white font-extrabold text-xs rounded-xl shadow-lg shadow-amber-600/25 flex items-center justify-center gap-2 transition-all">
                <span class="material-symbols-outlined text-base">undo</span>
                <span>تأكيد التراجع وإرجاع للمكتب</span>
            </button>

            <button type="button"
                    @click="closeUndoDeliveryModal()"
                    class="w-full py-2.5 px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl flex items-center justify-center transition-colors">
                <span>إلغاء وتراجع</span>
            </button>
        </div>

    </div>
</div>
