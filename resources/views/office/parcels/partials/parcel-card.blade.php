{{-- بطاقة طرد واحد — تُستخدم داخل <template x-for="p in ..."> وتتطلب وجود المتغير p --}}
<article class="border rounded-2xl p-3.5 bg-white shadow-sm flex flex-col gap-2 transition-colors"
         :class="cardBorder(p.status)">

    <!-- الرأس: نوع الطرد والسند وشارة الحالة ومدة بقائه في المكتب -->
    <div class="flex justify-between items-start">
        <div class="flex items-center gap-2">
            <span class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-primary">
                <span class="material-symbols-outlined text-lg">package_2</span>
            </span>
            <div>
                <h3 class="font-bold text-sm text-slate-800" x-text="p.package_type"></h3>
                <p class="text-[11px] text-slate-400 font-mono" x-show="p.receipt_number"
                    x-text="'سند: #' + p.receipt_number"></p>
            </div>
        </div>

        <!-- قسم الحالات والمدد الزمنية -->
        <div class="flex items-center gap-1.5 flex-wrap justify-end">
            <!-- مؤشر المعلق (يظهر فقط إذا كان الطرد في طابور الأوفلاين) -->
            <template x-if="isPending(p.id)">
                <span title="بانتظار توفر الإنترنت للمزامنة"
                    class="flex items-center text-amber-700 bg-amber-50 border border-amber-300 px-1.5 py-0.5 rounded text-[10px] font-bold shadow-xs">
                    <span class="material-symbols-outlined text-[13px] animate-pulse">schedule</span>
                    <span class="mr-1">معلق</span>
                </span>
            </template>

            <!-- بادج كم له الطرد في المكتب (يظهر فقط إذا كان بالمكتب) -->
            <span x-show="p.status === 'in_office' && p.created_at"
                class="flex items-center gap-0.5 text-[10px] font-bold px-2 py-0.5 rounded-md bg-blue-50 text-blue-700 border border-blue-200/70"
                :title="'تاريخ الاستلام: ' + formatDate(p.created_at)">
                <span class="material-symbols-outlined text-[12px]">hourglass_top</span>
                <span x-text="timeAgo(p.created_at)"></span>
            </span>

            <!-- شارة الحالة الأساسية -->
            <span class="text-[10px] font-bold px-2 py-0.5 rounded-md" :class="statusBadge(p.status)"
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
            <button type="button" x-show="p.status === 'in_office'" @click="resendSms(p)"
                :disabled="sendingSmsId === p.id" title="إعادة إرسال إشعار SMS للمستلم"
                class="h-8 px-2.5 rounded-xl bg-blue-50 hover:bg-blue-100 text-blue-600 border border-blue-200/80 flex items-center justify-center gap-1 text-[11px] font-bold shadow-sm transition-all active:scale-95 disabled:opacity-50">
                <span class="material-symbols-outlined text-[15px]"
                    :class="sendingSmsId === p.id ? 'animate-spin' : ''"
                    x-text="sendingSmsId === p.id ? 'sync' : 'sms'"></span>
                <span x-text="sendingSmsId === p.id ? 'جاري...' : 'SMS'"></span>
            </button>

            <!-- زر إرسال إشعار عبر واتساب (يظهر فقط إذا كان الطرد بالمكتب) -->
            <a x-show="p.status === 'in_office'"
               :href="'https://wa.me/967' + String(p.recipient_phone).replace(/\D/g, '').replace(/^0+/, '') + '?text=' + encodeURIComponent('وصل طردك!\nنوع الطرد: ' + (p.package_type || 'طرد') + '\nالموقع: مكتب {{ Auth::guard('office')->user()->name ?? 'الجعيدي' }}' + ('{{ Auth::guard('office')->user()->branch_name ?? '' }}' ? ' ({{ Auth::guard('office')->user()->branch_name ?? '' }})' : '') + '\nتفضل باستلامه في أسرع وقت.\nيوجد لدينا خدمة توصيل إلى مكانك، للتأكيد تواصل مع الرقم: {{ Auth::guard('office')->user()->httpsms_from_phone ?? '778793083' }}')"
               target="_blank" rel="noopener noreferrer"
               title="إرسال إشعار عبر واتساب"
               class="w-8 h-8 rounded-xl bg-green-50 hover:bg-green-100 text-green-600 border border-green-200/80 flex items-center justify-center shadow-sm transition-all active:scale-95">
                <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24">
                    <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86s.274.072.376-.043c.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.045.072.045.419-.1.824zm-3.423-14.416c-6.627 0-12 5.373-12 12 0 2.625.846 5.059 2.284 7.033l-1.492 5.451 5.581-1.464c1.905 1.139 4.129 1.796 6.507 1.796 6.627 0 12-5.373 12-12s-5.373-12-12-12z"/>
                </svg>
            </a>

            <!-- زر الاتصال الهاتفي -->
            <a :href="'tel:' + p.recipient_phone" title="اتصال"
                class="w-8 h-8 rounded-full bg-emerald-500 hover:bg-emerald-600 text-white flex items-center justify-center shadow-sm transition-colors">
                <span class="material-symbols-outlined text-sm">call</span>
            </a>
        </div>
    </div>

    <!-- تاريخ التسليم (يظهر فقط في حال تم التسليم) -->
    <p x-show="p.status === 'delivered' && p.delivered_at"
        class="flex items-center gap-1 text-[11px] text-emerald-700 bg-emerald-50 rounded-lg px-2 py-1">
        <span class="material-symbols-outlined text-sm">event_available</span>
        <span>تم التسليم في:</span>
        <span class="font-bold" x-text="formatDate(p.delivered_at)"></span>
    </p>

    <!-- أزرار الإجراءات (تظهر فقط الانتقالات المسموحة حسب الحالة الحالية) -->
    <div class="flex gap-2 pt-1" x-show="actionsFor(p).length">
        <template x-for="action in actionsFor(p)" :key="action.status">
            <button type="button" @click="
                    action.status === 'returned'  ? openReturnModal(p)
                  : action.status === 'in_office' ? openUndoDeliveryModal(p)
                  : action.status === 'delivered' ? openDeliveryModal(p)
                  : changeStatus(p, action.status)
                " :class="action.classes"
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