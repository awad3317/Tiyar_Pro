{{-- بطاقة الطرد الخاصة بالمحاسب — تُستخدم داخل <template x-for="p in filteredParcels" :key="p.id"> --}}
<article class="border rounded-2xl p-3.5 bg-white shadow-sm flex flex-col gap-2.5 transition-all"
         :class="isSelected(p.id)
            ? 'border-secondary ring-2 ring-secondary/20 bg-amber-50/15'
            : (p.payment_type === 'postpaid' ? 'border-amber-200/90' : 'border-slate-200/80')">

    <!-- الصف العلوي: مربع التحديد + نوع الطرد ورقم السند + شارة نوع الدفع والمبلغ -->
    <div class="flex items-start justify-between gap-2">
        <div class="flex items-center gap-2.5 min-w-0">
            <!-- مربع تحديد الطرد (لتجهيز الكشف) -->
            <label class="inline-flex items-center cursor-pointer shrink-0 p-0.5">
                <input type="checkbox"
                       :value="p.id"
                       :checked="isSelected(p.id)"
                       @change="toggleSelect(p.id)"
                       class="w-4 h-4 rounded border-slate-300 text-secondary focus:ring-secondary cursor-pointer">
            </label>

            <span class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0"
                  :class="p.payment_type === 'postpaid' ? 'bg-amber-50 text-amber-600' : 'bg-slate-100 text-primary'">
                <span class="material-symbols-outlined text-lg">package_2</span>
            </span>

            <div class="min-w-0">
                <div class="flex items-center gap-1.5 flex-wrap">
                    <h3 class="font-extrabold text-sm text-slate-800 truncate" x-text="p.package_type || 'طرد'"></h3>
                    <span x-show="p.receipt_number"
                          class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200"
                          x-text="'سند #' + p.receipt_number"></span>
                </div>
                <p x-show="p.source_office_name"
                   class="text-[11px] text-slate-400 truncate mt-0.5 flex items-center gap-1">
                    <span class="material-symbols-outlined text-[12px]">local_shipping</span>
                    <span x-text="'من: ' + p.source_office_name"></span>
                </p>
            </div>
        </div>

        <!-- شارة نوع الدفع والمبلغ (آجل / محاسب) -->
        <div class="shrink-0 flex flex-col items-end gap-1">
            <template x-if="p.payment_type === 'postpaid'">
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-xs font-extrabold bg-amber-100 text-amber-900 border border-amber-300 shadow-2xs">
                    <span class="material-symbols-outlined text-[14px] text-amber-700">payments</span>
                    <span x-text="'آجل: ' + formatAmount(p.cod_amount) + ' ر.ي'"></span>
                </span>
            </template>

            <template x-if="p.payment_type !== 'postpaid'">
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-[11px] font-bold bg-slate-100 text-slate-600 border border-slate-200">
                    <span class="material-symbols-outlined text-[13px] text-emerald-600">verified</span>
                    <span>محاسب</span>
                </span>
            </template>
        </div>
    </div>

    <!-- بيانات المستلم (الاسم + رقم الجوال) -->
    <div class="bg-slate-50 rounded-xl p-2.5 flex items-center justify-between gap-2 text-xs border border-slate-100">
        <div class="min-w-0 flex-1">
            <!-- إذا كان الاسم موجوداً -->
            <template x-if="p.recipient_name && p.recipient_name.trim() !== ''">
                <p class="font-extrabold text-slate-800 truncate flex items-center gap-1">
                    <span class="material-symbols-outlined text-[15px] text-slate-400">person</span>
                    <span x-text="p.recipient_name"></span>
                </p>
            </template>

            <!-- إذا كان الاسم فارغاً: شارة تنبيهية قابلة للنقر لإضافة الاسم بسرعة -->
            <template x-if="!p.recipient_name || p.recipient_name.trim() === ''">
                <button type="button"
                        @click="openEditModal(p, 'recipient_name')"
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-300/80 text-[11px] font-extrabold transition-colors">
                    <span class="material-symbols-outlined text-[14px]">person_add</span>
                    <span>بدون اسم (اضغط لإضافة الاسم)</span>
                </button>
            </template>

            <p class="text-slate-600 font-mono font-bold dir-ltr text-right mt-1 flex items-center justify-end gap-1">
                <span x-text="p.recipient_phone"></span>
                <span class="material-symbols-outlined text-[13px] text-slate-400">call</span>
            </p>
        </div>

        <div class="flex items-center gap-1.5 shrink-0">
            <!-- زر اتصال سريع -->
            <a :href="'tel:' + p.recipient_phone"
               title="اتصال بالمستلم"
               class="w-8 h-8 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-600 border border-emerald-200 flex items-center justify-center transition-colors">
                <span class="material-symbols-outlined text-base">call</span>
            </a>
        </div>
    </div>

    <!-- شريط الأزرار السفلي: تعديل الطرد + سجل التعديلات + وقت التسجيل -->
    <div class="flex items-center justify-between gap-2 pt-0.5">
        <div class="flex items-center gap-1.5">
            <!-- زر تعديل الطرد -->
            <button type="button"
                    @click="openEditModal(p)"
                    class="py-1.5 px-3 rounded-xl bg-primary hover:bg-slate-800 text-white text-xs font-bold flex items-center gap-1.5 shadow-2xs active:scale-95 transition-all">
                <span class="material-symbols-outlined text-[15px] text-secondary">edit_square</span>
                <span>تعديل الطرد</span>
            </button>

            <!-- زر سجل التعديلات (يظهر إذا كان للطرد تعديلات سابقة) -->
            <button type="button"
                    x-show="p.edit_logs_count > 0"
                    @click="openLogsModal(p)"
                    class="py-1.5 px-2.5 rounded-xl bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 text-[11px] font-extrabold flex items-center gap-1 active:scale-95 transition-all">
                <span class="material-symbols-outlined text-[14px]">history</span>
                <span>سجل التعديلات (<span x-text="p.edit_logs_count"></span>)</span>
            </button>
        </div>

        <!-- وقت وصول الطرد -->
        <span class="text-[10px] font-bold text-slate-400" x-text="formatDateShort(p.created_at)"></span>
    </div>

</article>

