{{-- بطاقة طرد واحد — تُستخدم داخل <template x-for="p in ..."> وتتطلب وجود المتغير p --}}
<article class="border border-slate-200/80 rounded-2xl p-3.5 bg-white shadow-sm flex flex-col gap-2">

    <!-- الرأس: نوع الطرد والسند وشارة الحالة مع مؤشر المزامنة المعلقة -->
    <div class="flex justify-between items-start">
        <div class="flex items-center gap-2">
            <span class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-primary">
                <span class="material-symbols-outlined text-lg">package_2</span>
            </span>
            <div>
                <h3 class="font-bold text-sm text-slate-800" x-text="p.package_type"></h3>
                <p class="text-[11px] text-slate-400 font-mono"
                   x-show="p.receipt_number"
                   x-text="'سند: #' + p.receipt_number"></p>
            </div>
        </div>

        <!-- قسم الحالة مع شارة المزامنة الشبيهة بالواتساب -->
        <div class="flex items-center gap-1.5">
            <!-- مؤشر المعلق (يظهر فقط إذا كان الطرد في طابور الأوفلاين بانتظار النت) -->
            <template x-if="isPending(p.id)">
                <span title="بانتظار توفر الإنترنت للمزامنة" 
                      class="flex items-center text-amber-700 bg-amber-50 border border-amber-300 px-1.5 py-0.5 rounded text-[10px] font-bold shadow-xs">
                    <span class="material-symbols-outlined text-[13px] animate-pulse">schedule</span>
                    <span class="mr-1">معلق</span>
                </span>
            </template>

            <!-- شارة الحالة الأساسية -->
            <span class="text-[10px] font-bold px-2 py-0.5 rounded-md"
                  :class="statusBadge(p.status)"
                  x-text="statusLabel(p.status)"></span>
        </div>
    </div>

    <!-- تفاصيل المستلم -->
    <div class="bg-slate-50 rounded-xl p-2.5 flex justify-between items-center text-xs">
        <div>
            <p class="font-bold text-slate-700" x-text="p.recipient_name || 'بدون اسم'"></p>
            <p class="text-slate-500 font-mono dir-ltr text-right" x-text="p.recipient_phone"></p>
        </div>
        <div class="flex items-center gap-1.5">
            <!-- زر إعادة إرسال SMS (يظهر فقط إذا كان الطرد بالمكتب) -->
            <button type="button"
                    x-show="p.status === 'in_office'"
                    @click="resendSms(p)"
                    :disabled="sendingSmsId === p.id"
                    title="إعادة إرسال إشعار SMS للمستلم"
                    class="h-8 px-2.5 rounded-xl bg-blue-50 hover:bg-blue-100 text-blue-600 border border-blue-200/80 flex items-center justify-center gap-1 text-[11px] font-bold shadow-sm transition-all active:scale-95 disabled:opacity-50">
                <span class="material-symbols-outlined text-[15px]" 
                      :class="sendingSmsId === p.id ? 'animate-spin' : ''"
                      x-text="sendingSmsId === p.id ? 'sync' : 'sms'"></span>
                <span x-text="sendingSmsId === p.id ? 'جاري...' : 'SMS'"></span>
            </button>

            <!-- زر الاتصال الهاتفي -->
            <a :href="'tel:' + p.recipient_phone"
               title="اتصال"
               class="w-8 h-8 rounded-full bg-emerald-500 hover:bg-emerald-600 text-white flex items-center justify-center shadow-sm transition-colors">
                <span class="material-symbols-outlined text-sm">call</span>
            </a>
        </div>
    </div>

    <!-- تاريخ التسليم -->
    <p x-show="p.status === 'delivered' && p.delivered_at"
       class="flex items-center gap-1 text-[11px] text-emerald-700 bg-emerald-50 rounded-lg px-2 py-1">
        <span class="material-symbols-outlined text-sm">event_available</span>
        <span>تم التسليم في:</span>
        <span class="font-bold" x-text="formatDate(p.delivered_at)"></span>
    </p>

    <!-- أزرار الإجراءات (تظهر فقط الانتقالات المسموحة حسب الحالة الحالية) -->
    <div class="flex gap-2 pt-1" x-show="actionsFor(p).length">
        <template x-for="action in actionsFor(p)" :key="action.status">
            <button type="button"
                    @click="changeStatus(p, action.status)"
                    :class="action.classes"
                    class="py-2 px-3 text-xs font-bold rounded-xl flex items-center justify-center gap-1 transition-all active:scale-95">
                <span class="material-symbols-outlined text-sm" x-text="action.icon"></span>
                <span x-text="action.label"></span>
            </button>
        </template>
    </div>

    <p x-show="p.status === 'returned'" class="text-[11px] text-rose-500 text-center pt-1">
        تم إرجاع الطرد للمكتب المرسل
    </p>

</article>