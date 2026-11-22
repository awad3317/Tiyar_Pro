<div id="{{ $receiptId }}" class="relative z-10 text-slate-900">
    {{-- العلامة المائية بالخلفية --}}
    <div class="watermark-bg flex items-center justify-center">
        <svg viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full h-full">
            <path d="M60 12L106 36V84L60 108L14 84V36L60 12Z" fill="#041627" />
            <path d="M60 12L106 36L60 60L14 36L60 12Z" fill="#fe9d20" />
            <path d="M64 32L44 64H58L54 90L76 56H62L64 32Z" fill="#ffffff" />
        </svg>
    </div>

    {{-- 1. الترويسة العلوية (3 أعمدة: بيانات المكتب | شارة السند | الأرقام والتاريخ والباركود) --}}
    <div class="receipt-header grid grid-cols-12 items-start gap-3 pb-3 border-b border-slate-200 relative z-10">

        {{-- اليمين: اسم المكتب والوصف --}}
        <div class="header-col col-span-4 text-right space-y-0.5 pt-1">
            <h1 class="editable-field text-xl sm:text-2xl font-black text-slate-900 leading-tight">
                {{ $officeName }}
            </h1>
            <p class="editable-field text-xs font-extrabold text-slate-600 leading-snug">
                {{ $tagline1 }}
            </p>
            <p class="editable-field text-[11px] font-bold text-slate-500 leading-snug">
                {{ $tagline2 }}
            </p>
        </div>

        {{-- الوسط: الأيقونة وكبسولة نوع السند والنسخة --}}
        <div class="header-col col-span-4 flex flex-col items-center justify-center text-center gap-1.5">
            <div class="w-10 h-10 flex items-center justify-center">
                <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-9 h-9">
                    <path d="M32 6L56 19V45L32 58L8 45V19L32 6Z" fill="#041627" stroke="#0f172a" stroke-width="2" />
                    <path d="M32 6L56 19L32 32L8 19L32 6Z" fill="#2563eb" />
                    <path d="M56 19V45L32 58V32L56 19Z" fill="#ef4444" />
                    <path d="M34 15L22 35H31L29 49L42 29H33L34 15Z" fill="#fe9d20" stroke="#041627" stroke-width="1.5" stroke-linejoin="round" />
                </svg>
            </div>

            <div class="voucher-badge shadow-2xs">
                <div class="editable-field text-base font-black text-slate-900 leading-tight">
                    {{ $receiptTitle }}
                </div>
                <div class="copy-badge-label text-[11px] font-bold text-slate-600 mt-0.5">
                    {{ $currentCopy }}
                </div>
            </div>
        </div>

        {{-- اليسار: أرقام التواصل، العنوان، التاريخ والوقت، رقم السند والباركود الخطي --}}
        <div class="header-col col-span-4 text-left space-y-1">
            @if ($managerPhone)
                <div class="flex items-center justify-between gap-2 text-[11px]">
                    <span class="text-slate-500 font-bold shrink-0">المدير والشكاوى:</span>
                    <span class="editable-field font-mono font-extrabold text-slate-900 inline-flex items-center gap-1 dir-ltr">
                        <span>{{ $managerPhone }}</span>
                        <span class="material-symbols-outlined text-[13px] text-rose-800">call</span>
                    </span>
                </div>
            @endif

            @if ($dispatchPhone1)
                <div class="flex items-center justify-between gap-2 text-[11px]">
                    <span class="text-slate-500 font-bold shrink-0">خدمة الإرسال:</span>
                    <span class="editable-field font-mono font-extrabold text-slate-900 inline-flex items-center gap-1 dir-ltr">
                        <span>{{ $dispatchPhone1 }}</span>
                        <span class="material-symbols-outlined text-[13px] text-rose-800">call</span>
                    </span>
                </div>
            @endif

            @if ($dispatchPhone2)
                <div class="text-left">
                    <span class="editable-field font-mono font-bold text-[10.5px] text-slate-600 dir-ltr inline-block">
                        {{ $dispatchPhone2 }}
                    </span>
                </div>
            @endif

            <p class="editable-field text-[11px] font-extrabold text-slate-700 text-right border-t border-slate-100 pt-1">
                {{ $officeAddress }}
            </p>

            {{-- التاريخ والوقت --}}
            <div class="flex items-center justify-between gap-2 pt-0.5 text-[11px] font-mono font-bold text-slate-700">
                <span class="inline-flex items-center gap-1 dir-ltr">
                    <span class="material-symbols-outlined text-[13px] text-rose-700">calendar_month</span>
                    <span class="editable-field">{{ $dateStr }}</span>
                </span>
                <span class="inline-flex items-center gap-1 dir-ltr">
                    <span class="editable-field">{{ $timeStr }}</span>
                    <span class="material-symbols-outlined text-[13px] text-slate-500">schedule</span>
                </span>
            </div>

            {{-- كبسولة رقم السند الداكنة + الباركود الخطي --}}
            <div class="flex items-center justify-between gap-2 pt-1">
                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md bg-slate-900 text-white font-mono font-black text-xs shrink-0 dir-ltr">
                    <span class="material-symbols-outlined text-[13px] text-slate-300">tag</span>
                    <span class="editable-field receipt-number-input">{{ $receiptNumber }}</span>
                </span>

                <div class="barcode-wrapper overflow-hidden flex items-center justify-end" title="باركود رقم السند">
                    {!! $barcodeSvg !!}
                </div>
            </div>
        </div>
    </div>

    {{-- 2. جدول تفاصيل الشحنة --}}
    <div class="relative z-10 mt-1">
        <table class="invoice-table">
            <tbody>
                {{-- الصف 1: المحافظة/الوجهة + المبلغ --}}
                <tr>
                    <td class="w-7/12 text-right">
                        <span class="text-xs font-bold text-slate-500 ml-1">المحافظة / الوجهة:</span>
                        <span class="editable-field text-sm font-black text-slate-900">{{ $destination }}</span>
                    </td>
                    <td class="w-5/12 text-left">
                        <span class="text-xs font-bold text-slate-500 ml-1">المبلغ:</span>
                        <span class="editable-field text-sm font-black {{ $isDeferred ? 'text-amber-800' : 'text-slate-900' }}">
                            {{ $amountDisplay }}
                        </span>
                    </td>
                </tr>

                {{-- الصف 2: اسم المستلم + جواله --}}
                <tr>
                    <td class="text-right">
                        <span class="text-xs font-bold text-slate-500 ml-1">اسم المستلم:</span>
                        <span class="editable-field text-sm font-black text-slate-900">{{ $recipientName }}</span>
                    </td>
                    <td class="text-left">
                        <span class="phone-badge">
                            <span class="editable-field">{{ $recipientPhone }}</span>
                            <span class="material-symbols-outlined text-[15px] text-rose-800">call</span>
                        </span>
                    </td>
                </tr>

                {{-- الصف 3: اسم المرسل + جواله --}}
                <tr>
                    <td class="text-right">
                        <span class="text-xs font-bold text-slate-500 ml-1">اسم المرسل:</span>
                        <span class="editable-field text-sm font-black text-slate-900">{{ $senderName }}</span>
                    </td>
                    <td class="text-left">
                        <span class="phone-badge">
                            <span class="editable-field">{{ $senderPhone }}</span>
                            <span class="material-symbols-outlined text-[15px] text-rose-800">call</span>
                        </span>
                    </td>
                </tr>

                {{-- الصف 4: نوع الرسالة / الطرد (+ الملاحظات إن وجدت) --}}
                <tr>
                    <td colspan="2" class="text-right">
                        <div class="flex items-center justify-between gap-2 flex-wrap">
                            <div>
                                <span class="text-xs font-bold text-slate-500 ml-1">نوع الرسالة:</span>
                                <span class="editable-field text-sm font-black text-slate-900">{{ $packageType }}</span>
                            </div>
                            @if ($parcelNotes)
                                <div class="text-xs font-bold text-slate-600">
                                    <span class="text-slate-400">ملاحظة:</span>
                                    <span class="editable-field">{{ $parcelNotes }}</span>
                                </div>
                            @endif
                        </div>
                    </td>
                </tr>

                {{-- الصف 5: موقع التسليم + رقم فرع الاستلام --}}
                <tr>
                    <td colspan="2" class="pt-2 pb-2">
                        <div class="flex items-center justify-between gap-3 flex-wrap">
                            <div class="text-right min-w-0 flex-1">
                                <span class="block text-[11px] font-bold text-slate-500">موقع التسليم:</span>
                                <span class="editable-field text-xs sm:text-sm font-black text-slate-900 leading-snug block mt-0.5">
                                    {{ $deliveryLoc }}
                                </span>
                            </div>

                            @if ($deliveryPhone)
                                <div class="shrink-0">
                                    <span class="delivery-phone-badge editable-field">{{ $deliveryPhone }}</span>
                                </div>
                            @endif
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- 3. الشروط والأحكام + رمز QR السريع (UUID) --}}
    <div class="relative z-10 mt-2 pt-2 border-t-2 border-slate-700 flex items-center justify-between gap-4">
        <ul class="space-y-0.5 text-[11px] font-bold text-slate-600 leading-relaxed flex-1">
            @foreach ($terms as $term)
                <li class="editable-field">{{ $term }}</li>
            @endforeach
        </ul>

        {{-- رمز QR يحمل الـ UUID الكامل للمسح الفوري في الشحنات وفي الطريق --}}
        @if ($parcelUuid)
            <div class="qr-box shrink-0 flex flex-col items-center justify-center p-1.5 border border-slate-300 rounded-lg bg-white">
                <div class="qr-wrapper" data-uuid="{{ $parcelUuid }}"></div>
                <span class="text-[8.5px] font-mono text-slate-500 mt-1 leading-none">مسح سريع</span>
            </div>
        @endif
    </div>

    {{-- 4. التذييل السفلي --}}
    <div class="relative z-10 mt-2.5 pt-2 border-t border-slate-200 flex items-center justify-between gap-2 text-xs">
        <div class="text-right">
            <span class="text-slate-500 font-bold">الموظف:</span>
            <span class="editable-field font-black text-slate-900 mr-0.5">{{ $employeeName }}</span>
        </div>

        <div class="editable-field text-center font-black text-slate-800 text-xs sm:text-sm">
            "{{ $slogan }}"
        </div>

        <div class="text-left text-[11px] font-bold text-slate-400">
            نظام مُرسَل للطرود
        </div>
    </div>
</div>