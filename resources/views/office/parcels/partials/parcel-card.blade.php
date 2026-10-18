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

                @php
                    $currentOffice = Auth::guard('office')->user();
                    $defaultTpl = "عميلنا العزيز،\nمكتب: {office} ({branch})\nتم استلام طردكم: {package}\nيرجى التوجه للفرع للاستلام. شكراً لتعاملكم معنا.";
                    $rawTemplate = !empty($currentOffice?->sms_template) ? $currentOffice->sms_template : $defaultTpl;

                    // تعويض بيانات المكتب الثابتة مسبقاً من السيرفر
                    $preparedTemplate = str_replace(
                        ['{office}', '{branch}'],
                        [$currentOffice?->name ?? '', $currentOffice?->branch_name ?? ''],
                        $rawTemplate
                    );
                    $preparedTemplate = str_replace(' ()', '', $preparedTemplate);
                @endphp

                <!-- زر إرسال إشعار عبر واتساب بالشعار الرسمي (يظهر فقط إذا كان الطرد بالمكتب) -->
                <a x-show="p.status === 'in_office'" :href="'https://wa.me/967' + String(p.recipient_phone).replace(/\D/g, '').replace(/^0+/, '') + '?text=' + encodeURIComponent(
       {{ Js::from($preparedTemplate) }}
           .replace('{package}', p.package_type || 'طرد')
           .replace('{receipt}', p.receipt_number || '')
   )" target="_blank" rel="noopener noreferrer" title="إرسال إشعار عبر واتساب"
                    class="w-8 h-8 rounded-full bg-[#25D366] hover:bg-[#20bd5a] text-white flex items-center justify-center shadow-sm transition-all active:scale-95">
                    <svg class="w-4 h-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
                        <path
                            d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L0 480l117.7-30.9c32.4 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-69.8 18.3L72 359.2l-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z" />
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