{{-- بطاقة كشف السائق — تُستخدم داخل <template x-for="m in visibleManifests" :key="m.id"> --}}
<article class="border rounded-2xl p-3.5 bg-white shadow-sm flex flex-col gap-3 transition-all"
         :class="m.status === 'settled' ? 'border-emerald-200/90' : 'border-amber-200/90'">

    <!-- 1. رأس البطاقة: اسم السائق ورقمه + شارة حالة الكشف -->
    <div class="flex items-start justify-between gap-2">
        <div class="flex items-center gap-2.5 min-w-0">
            <span class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 shadow-2xs"
                  :class="m.status === 'settled' ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-primary text-secondary'">
                <span class="material-symbols-outlined text-xl">local_shipping</span>
            </span>

            <div class="min-w-0">
                <div class="flex items-center gap-1.5 flex-wrap">
                    <h3 class="font-extrabold text-sm text-slate-800 truncate" x-text="m.driver_name"></h3>
                    <span x-show="m.manifest_number"
                          class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200"
                          x-text="'كشف #' + m.manifest_number"></span>
                </div>

                <div class="flex items-center gap-2 mt-0.5 text-[11px] text-slate-500">
                    <template x-if="m.driver_phone">
                        <a :href="'tel:' + m.driver_phone"
                           class="font-mono font-bold text-slate-600 hover:text-primary dir-ltr inline-flex items-center gap-0.5">
                            <span class="material-symbols-outlined text-[13px] text-emerald-600">call</span>
                            <span x-text="m.driver_phone"></span>
                        </a>
                    </template>
                    <template x-if="!m.driver_phone">
                        <span class="text-slate-400">بدون رقم جوال</span>
                    </template>
                </div>
            </div>
        </div>

        <!-- شارة حالة الكشف -->
        <div class="shrink-0">
            <template x-if="m.status === 'open'">
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-[11px] font-extrabold bg-amber-50 text-amber-800 border border-amber-300">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                    <span>قيد المراجعة</span>
                </span>
            </template>

            <template x-if="m.status === 'settled'">
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-[11px] font-extrabold bg-emerald-50 text-emerald-800 border border-emerald-300">
                    <span class="material-symbols-outlined text-[13px] text-emerald-600">check_circle</span>
                    <span>تمت التصفية</span>
                </span>
            </template>
        </div>
    </div>

    <!-- 2. معلومات المصدر والتاريخ وإحصائية الطرود داخل الكشف -->
    <div class="bg-slate-50 rounded-xl p-2.5 border border-slate-100 space-y-2 text-xs">
        <div class="flex items-center justify-between gap-2 flex-wrap">
            <div class="flex items-center gap-1.5 text-slate-700 font-bold min-w-0">
                <span class="material-symbols-outlined text-[15px] text-secondary shrink-0">store</span>
                <span class="text-slate-400 font-normal">من:</span>
                <span class="truncate" x-text="m.source_office_name || 'غير محدد'"></span>
                <span x-show="m.source_branch_name"
                      class="text-slate-500 text-[11px]"
                      x-text="'(' + m.source_branch_name + ')'"></span>
            </div>

            <div class="flex items-center gap-1 text-[11px] font-mono font-bold text-slate-500 shrink-0 dir-ltr">
                <span x-text="m.manifest_date"></span>
                <span class="material-symbols-outlined text-[14px] text-slate-400">calendar_today</span>
            </div>
        </div>

        <!-- إحصائية الطرود داخل الكشف -->
        <div class="pt-1.5 border-t border-slate-200/70 flex items-center justify-between text-[11px]">
            <div class="flex items-center gap-1 font-extrabold text-slate-700">
                <span class="material-symbols-outlined text-[15px] text-primary">inventory_2</span>
                <span>إجمالي الطرود (<span x-text="m.parcels_count">0</span>)</span>
            </div>
            <div class="flex items-center gap-2 font-bold">
                <span class="px-2 py-0.5 rounded-md bg-amber-100/80 text-amber-900 border border-amber-200">
                    آجل: <strong x-text="m.postpaid_parcels_count">0</strong>
                </span>
                <span class="px-2 py-0.5 rounded-md bg-slate-200/70 text-slate-700">
                    محاسب: <strong x-text="m.prepaid_parcels_count">0</strong>
                </span>
            </div>
        </div>
    </div>

    <!-- 3. شريط الملخص المالي للكشف (إجمالي الآجل | الخصومات | الصافي للسائق) -->
    <div class="grid grid-cols-3 gap-1.5 text-center">
        <div class="bg-amber-50/70 border border-amber-200/80 rounded-xl p-2">
            <p class="text-[10px] font-bold text-amber-800">إجمالي الآجل</p>
            <p class="text-xs font-black text-amber-950 mt-0.5 dir-ltr">
                <span x-text="formatAmount(m.total_postpaid_amount)">0</span> ر.ي
            </p>
        </div>

        <div class="bg-rose-50/70 border border-rose-200/80 rounded-xl p-2">
            <p class="text-[10px] font-bold text-rose-800">الخصومات</p>
            <p class="text-xs font-black text-rose-900 mt-0.5 dir-ltr">
                <span x-text="formatAmount(m.total_deductions)">0</span> ر.ي
            </p>
        </div>

        <div class="bg-emerald-50/80 border border-emerald-200/90 rounded-xl p-2">
            <p class="text-[10px] font-bold text-emerald-800">الصافي للسائق</p>
            <p class="text-xs font-black text-emerald-950 mt-0.5 dir-ltr">
                <span x-text="formatAmount(m.net_driver_amount)">0</span> ر.ي
            </p>
        </div>
    </div>

    <!-- ملاحظات الكشف إن وجدت -->
    <p x-show="m.notes"
       class="text-[11px] text-slate-500 bg-slate-50 px-2.5 py-1.5 rounded-lg border border-slate-100 truncate"
       x-text="'ملاحظة: ' + m.notes"></p>

    <!-- 4. أزرار التحكم -->
    <div class="flex items-center gap-2 pt-0.5">
        <a :href="'/accountant/manifests/' + m.id"
           class="flex-1 py-2 px-3 rounded-xl bg-primary hover:bg-slate-800 text-white text-xs font-extrabold flex items-center justify-center gap-1.5 shadow-2xs active:scale-95 transition-all">
            <span class="material-symbols-outlined text-[16px] text-secondary">calculate</span>
            <span>فتح الكشف والخصومات</span>
        </a>

        <button type="button"
                x-show="m.status === 'open'"
                @click="openEditManifestModal(m)"
                class="py-2 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 text-xs font-bold flex items-center justify-center gap-1 active:scale-95 transition-all">
            <span class="material-symbols-outlined text-[16px]">edit</span>
            <span>تعديل</span>
        </button>
    </div>

</article>

