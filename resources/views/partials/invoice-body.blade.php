{{-- قالب سند الإرسال / الفاتورة المطابق تماماً للنموذج المرفق --}}
<div id="{{ $receiptId ?? 'receipt-1' }}" class="relative select-text">

    <!-- العلامة المائية للوجو بالخلفية -->
    <div class="watermark-bg">
        <svg viewBox="0 0 160 140" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full h-full">
            <polygon points="80,10 145,45 80,80 15,45" fill="#1e3a8a" opacity="0.3"/>
            <polygon points="15,45 80,80 80,130 15,95" fill="#2563eb" opacity="0.4"/>
            <polygon points="145,45 80,80 80,130 145,95" fill="#dc2626" opacity="0.4"/>
            <path d="M40 35 L75 35 L55 60 L85 60 L45 105 L60 70 L30 70 Z" fill="#b91c1c" opacity="0.5"/>
        </svg>
    </div>

    <!-- الترويسة الرئيسية (3 أعمدة متناسقة) -->
    <div class="relative z-10 grid grid-cols-12 gap-2 items-center border-b-2 border-slate-700 pb-2 mb-2">
        
        <!-- العمود الأيمن: اسم المكتب ومجال عمله -->
        <div class="col-span-4 text-right">
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight leading-tight editable-field">{{ $officeName }}</h1>
            <p class="text-[11px] sm:text-xs font-bold text-slate-700 mt-0.5 leading-snug editable-field">{{ $tagline1 }}</p>
            <p class="text-[10.5px] sm:text-[11.5px] font-semibold text-slate-600 leading-snug editable-field">{{ $tagline2 }}</p>
        </div>

        <!-- العمود الأوسط: الشعار وكبسولة نوع السند -->
        <div class="col-span-4 flex flex-col items-center justify-center text-center">
            
            <!-- اللوجو الفيكتور الدقيق لشعار الشحن والنقل المتطابق مع الصورة -->
            <div class="w-14 h-12 flex items-center justify-center">
                <svg viewBox="0 0 160 140" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full h-full drop-shadow-sm">
                    <!-- المجسم السداسي / صندوق الطرد السريع -->
                    <polygon points="80,12 142,46 80,80 18,46" fill="#1e40af" stroke="#0f172a" stroke-width="4" stroke-linejoin="round"/>
                    <polygon points="18,46 80,80 80,128 18,94" fill="#3b82f6" stroke="#0f172a" stroke-width="4" stroke-linejoin="round"/>
                    <polygon points="142,46 80,80 80,128 142,94" fill="#ef4444" stroke="#0f172a" stroke-width="4" stroke-linejoin="round"/>
                    <!-- حركة السرعة على شكل حرف S بلونين أحمر وأزرق داكن -->
                    <path d="M45 38 L82 38 L58 65 L88 65 L48 112 L64 74 L34 74 Z" fill="#b91c1c" stroke="#ffffff" stroke-width="2"/>
                </svg>
            </div>

            <!-- كبسولة السند (سند إرسال / خاص بالمكتب) -->
            <div class="voucher-badge mt-0.5 shadow-xs">
                <span class="text-base sm:text-lg font-black text-slate-900 block leading-tight tracking-wide editable-field">{{ $receiptTitle }}</span>
                <span class="text-[10px] sm:text-[11px] font-bold text-slate-700 block copy-badge-label editable-field">{{ $currentCopy ?? $copyType }}</span>
            </div>

        </div>

        <!-- العمود الأيسر: أرقام التواصل والعنوان وتاريخ السند والباركود -->
        <div class="col-span-4 flex flex-col items-end text-left dir-rtl">
            
            <div class="text-[10.5px] sm:text-[11px] font-bold text-slate-800 space-y-0.5 w-full">
                <div class="flex items-center justify-between gap-1">
                    <span class="text-slate-500">المدير والشكاوى:</span>
                    <span class="font-mono text-slate-900 font-extrabold editable-field">{{ $managerPhone }}</span>
                    <span>📞</span>
                </div>
                <div class="flex items-center justify-between gap-1">
                    <span class="text-slate-500">خدمة الإرسال:</span>
                    <span class="font-mono text-slate-900 font-extrabold editable-field">{{ $dispatchPhone1 }}</span>
                    <span>📞</span>
                </div>
                <div class="text-left dir-ltr font-mono text-[10px] text-slate-700 font-bold editable-field">
                    {{ $dispatchPhone2 }}
                </div>
                <div class="text-right text-[10px] text-slate-700 font-semibold truncate max-w-full editable-field" title="{{ $officeAddress }}">
                    {{ $officeAddress }}
                </div>
            </div>

            <!-- صف التاريخ والوقت مع الباركود ورقم السند -->
            <div class="w-full mt-1.5 pt-1 border-t border-slate-200">
                <div class="flex items-center justify-between text-[10.5px] font-mono font-bold text-slate-800">
                    <div class="flex items-center gap-1">
                        <span class="editable-field">{{ $dateStr }}</span>
                        <span>📅</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="editable-field">{{ $timeStr }}</span>
                        <span>🕒</span>
                    </div>
                </div>

                <!-- رقم السند والباركود الخطي الدقيق -->
                <div class="flex items-center justify-between gap-1.5 mt-1">
                    <div class="bg-slate-900 text-white font-mono font-bold text-xs px-2 py-0.5 rounded flex items-center gap-1 shrink-0">
                        <span class="receipt-number-input editable-field">{{ $receiptNumber }}</span>
                        <span class="material-symbols-outlined text-xs">package_2</span>
                    </div>
                    <div class="barcode-wrapper flex-1 overflow-hidden flex justify-end">
                        {!! $barcodeSvg !!}
                    </div>
                </div>
            </div>

        </div>

    </div>

    <!-- شبكة تفاصيل الشحنة (تطابق 100% الجدول والصفوف في الصورة) -->
    <div class="relative z-10">
        <table class="invoice-table text-slate-900">
            <tbody>

                <!-- الصف الأول: المحافظة والمبلغ -->
                <tr>
                    <td class="w-7/12 font-bold text-xs sm:text-sm">
                        <span class="text-slate-600 font-semibold">المحافظة:</span>
                        <span class="text-sm sm:text-base font-black text-slate-900 mr-1 editable-field">{{ $destination }}</span>
                    </td>
                    <td class="w-5/12 text-left font-bold text-xs sm:text-sm">
                        <span class="text-slate-600 font-semibold">المبلغ:</span>
                        <span class="text-sm sm:text-base font-black text-slate-900 mr-1 editable-field">{{ $amount }}</span>
                    </td>
                </tr>

                <!-- الصف الثاني: اسم المستلم ورقم هاتفه -->
                <tr>
                    <td class="font-bold text-xs sm:text-sm py-1.5">
                        <span class="text-slate-600 font-semibold">اسم المستلم:</span>
                        <span class="text-sm sm:text-base font-black text-slate-900 mr-1 editable-field">{{ $recipientName }}</span>
                    </td>
                    <td class="text-left py-1.5">
                        <div class="phone-badge shadow-xs">
                            <span class="text-emerald-600">📞</span>
                            <span class="editable-field">{{ $recipientPhone }}</span>
                        </div>
                    </td>
                </tr>

                <!-- الصف الثالث: اسم المرسل ورقم هاتفه -->
                <tr>
                    <td class="font-bold text-xs sm:text-sm py-1.5">
                        <span class="text-slate-600 font-semibold">اسم المرسل:</span>
                        <span class="text-sm sm:text-base font-black text-slate-900 mr-1 editable-field">{{ $senderName }}</span>
                    </td>
                    <td class="text-left py-1.5">
                        <div class="phone-badge shadow-xs">
                            <span class="text-emerald-600">📞</span>
                            <span class="editable-field">{{ $senderPhone }}</span>
                        </div>
                    </td>
                </tr>

                <!-- الصف الرابع: نوع الرسالة / الطرد -->
                <tr>
                    <td colspan="2" class="font-bold text-xs sm:text-sm py-1">
                        <span class="text-slate-600 font-semibold">نوع الرسالة:</span>
                        <span class="text-sm sm:text-base font-black text-slate-900 mr-1 editable-field">{{ $packageType }}</span>
                    </td>
                </tr>

                <!-- الصف الخامس: موقع التسليم ورقم هاتف الفرع/المكتب المستلم -->
                <tr class="border-t-2 border-b-2 border-slate-700/80 bg-slate-50/70">
                    <td colspan="2" class="py-2 px-1">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1">
                                <span class="text-[11px] font-bold text-slate-600 block">موقع التسليم:</span>
                                <span class="text-xs sm:text-sm font-black text-slate-900 leading-snug editable-field">{{ $deliveryLoc }}</span>
                            </div>
                            <div class="shrink-0 self-center text-left">
                                <span class="text-xs sm:text-sm font-black font-mono text-red-700 bg-red-50 border border-red-200 px-2 py-0.5 rounded editable-field">{{ $deliveryPhone }}</span>
                            </div>
                        </div>
                    </td>
                </tr>

            </tbody>
        </table>
    </div>

    <!-- شروط وأحكام الإرسال المعتمدة (الـ 4 بنود كما في الصورة) -->
    <div class="relative z-10 mt-2 pt-1.5 border-t border-slate-300">
        <ol class="text-[10px] sm:text-[11px] font-bold text-slate-700 leading-tight space-y-0.5">
            @foreach($terms as $term)
                <li class="editable-field">{{ $term }}</li>
            @endforeach
        </ol>
    </div>

    <!-- تذييل الفاتورة: الشعار واسم الموظف -->
    <div class="relative z-10 mt-2 pt-1 border-t border-slate-200 flex items-center justify-between text-xs">
        
        <div class="w-1/3 text-right">
            <span class="text-[11px] font-bold text-slate-500">الموظف: </span>
            <span class="text-xs font-black text-slate-900 editable-field">{{ $employeeName }}</span>
        </div>

        <div class="w-1/3 text-center">
            <span class="font-black text-xs sm:text-sm text-slate-800 tracking-wide editable-field">"{{ $slogan }}"</span>
        </div>

        <div class="w-1/3 text-left">
            <span class="text-[9.5px] font-mono text-slate-400">نظام مُرسَل للطرود</span>
        </div>

    </div>

</div>
