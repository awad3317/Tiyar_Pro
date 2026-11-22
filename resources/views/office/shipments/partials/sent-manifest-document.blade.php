@php
    $allParcels = $shipment->parcels ?? collect();
    $activeParcels = $allParcels
        ->reject(fn ($p) => $p->status === \App\Enums\OutgoingParcelStatus::CANCELLED)
        ->values();

    $cancelledCount = $allParcels->count() - $activeParcels->count();

    $paidParcels = $activeParcels->filter(function ($p) {
        $paymentEnum = $p->payment_type instanceof \App\Enums\OutgoingPaymentType
            ? $p->payment_type
            : (\App\Enums\OutgoingPaymentType::tryFrom((string) $p->payment_type) ?? \App\Enums\OutgoingPaymentType::PAID);

        return $paymentEnum !== \App\Enums\OutgoingPaymentType::DEFERRED;
    });

    $deferredParcels = $activeParcels->filter(function ($p) {
        $paymentEnum = $p->payment_type instanceof \App\Enums\OutgoingPaymentType
            ? $p->payment_type
            : (\App\Enums\OutgoingPaymentType::tryFrom((string) $p->payment_type) ?? \App\Enums\OutgoingPaymentType::PAID);

        return $paymentEnum === \App\Enums\OutgoingPaymentType::DEFERRED;
    });

    $activeCount = $activeParcels->count();
    $paidCount = $paidParcels->count();
    $deferredCount = $deferredParcels->count();

    $totalPaidAmount = round((float) $paidParcels->sum(fn ($p) => (float) ($p->cod_amount ?? 0)), 2);
    $totalDeferredAmount = round((float) $deferredParcels->sum(fn ($p) => (float) ($p->cod_amount ?? 0)), 2);
    $totalGrandAmount = round($totalPaidAmount + $totalDeferredAmount, 2);

    $activeDestinations = $activeParcels
        ->map(fn ($p) => $p->destinationLabel())
        ->filter(fn ($label) => trim((string) $label) !== '')
        ->unique()
        ->values()
        ->all();

    if (empty($activeDestinations) && is_array($shipment->destinations_summary)) {
        $activeDestinations = $shipment->destinations_summary;
    }

    $manifestDate = ($shipment->dispatched_at ?? $shipment->created_at)?->format('Y-m-d H:i') ?? '—';
    $senderOfficeName = $shipment->office?->name ?? ($office->name ?? 'المكتب');
    $senderBranchName = $shipment->office?->branch_name ?? ($office->branch_name ?? null);
@endphp

<section class="w-full bg-white text-slate-900 text-[11px] leading-snug">
    <!-- 1. ترويسة الكشف الرسمية (مطابقة لقالب كشف المحاسب A4) -->
    <div class="border-b-2 border-slate-800 pb-3 mb-3 flex items-start justify-between gap-4">
        <div>
            <p class="text-[11px] font-bold text-slate-600">
                {{ $senderOfficeName }}
                @if($senderBranchName)
                    - {{ $senderBranchName }}
                @endif
            </p>
            <h1 class="text-lg font-black text-slate-900 mt-0.5">كشف شحنة صادرة</h1>
        </div>

        <div class="text-left space-y-0.5">
            <p class="font-bold">
                <span>رقم الشحنة: </span>
                <span class="font-mono font-black">#{{ $shipment->shipment_number }}</span>
            </p>
            <p>
                <span>التاريخ والوقت: </span>
                <span class="font-mono font-bold dir-ltr inline-block">{{ $manifestDate }}</span>
            </p>
            <p>
                <span>الحالة: </span>
                <span class="font-extrabold">{{ $shipment->status->label() }}</span>
            </p>
        </div>
    </div>

    <!-- 2. صندوق بيانات السائق والفرع المرسل والوجهات -->
    <div class="grid grid-cols-3 gap-2 mb-3 p-2.5 border border-slate-300 rounded-lg bg-slate-50/70">
        <div>
            <span class="text-slate-500">السائق: </span>
            <strong class="font-extrabold text-slate-900">{{ $shipment->driver_name ?: '—' }}</strong>
        </div>
        <div>
            <span class="text-slate-500">جوال السائق: </span>
            <strong class="font-mono font-bold dir-ltr inline-block">{{ $shipment->driver_phone ?: '—' }}</strong>
        </div>
        <div>
            <span class="text-slate-500">عدد الطرود الفعّالة: </span>
            <strong class="font-mono font-black">{{ $activeCount }}</strong>
            <span class="text-[10px] text-slate-600">
                ({{ $paidCount }} محاسب / {{ $deferredCount }} آجل)
            </span>
        </div>

        <div>
            <span class="text-slate-500">الفرع المرسل: </span>
            <strong class="font-bold">{{ $shipment->senderOfficeLabel() }}</strong>
        </div>
        <div>
            <span class="text-slate-500">الوجهات: </span>
            <strong class="font-bold">{{ count($activeDestinations) > 0 ? implode('، ', $activeDestinations) : '—' }}</strong>
        </div>
        <div>
            <span class="text-slate-500">تاريخ الطباعة: </span>
            <strong class="font-mono dir-ltr inline-block">{{ now()->format('Y-m-d H:i') }}</strong>
        </div>
    </div>

    <!-- 3. جدول الطرود المدرجة في الشحنة (RTL رسمي مطابق لكشف المحاسب) -->
    <div class="mb-3">
        <div class="flex items-center justify-between mb-1.5">
            <h2 class="text-xs font-black text-slate-900">أولاً: بيان الطرود المدرجة في الشحنة</h2>
            @if($cancelledCount > 0)
                <span class="text-[10px] font-bold text-rose-700">
                    * الطرود الملغاة ({{ $cancelledCount }}) مستبعدة من المجاميع المالية
                </span>
            @endif
        </div>

        <table class="print-table w-full border-collapse border border-slate-400 text-[10px]">
            <thead>
                <tr class="bg-slate-200 text-slate-900 font-extrabold">
                    <th class="border border-slate-400 px-1.5 py-1.5 text-center w-7">#</th>
                    <th class="border border-slate-400 px-1.5 py-1.5 text-right w-14">رقم السند</th>
                    <th class="border border-slate-400 px-2 py-1.5 text-right">الوجهة / الفرع</th>
                    <th class="border border-slate-400 px-2 py-1.5 text-right">المرسل</th>
                    <th class="border border-slate-400 px-2 py-1.5 text-right">المستلم ورقم الهاتف</th>
                    <th class="border border-slate-400 px-2 py-1.5 text-right">نوع الطرد</th>
                    <th class="border border-slate-400 px-1.5 py-1.5 text-center w-14">الدفع</th>
                    <th class="border border-slate-400 px-2 py-1.5 text-left w-20">المبلغ (ر.ي)</th>
                    <th class="border border-slate-400 px-2 py-1.5 text-right">ملاحظات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($allParcels as $idx => $parcel)
                    @php
                        $isCancelled = $parcel->status === \App\Enums\OutgoingParcelStatus::CANCELLED;
                        $paymentEnum = $parcel->payment_type instanceof \App\Enums\OutgoingPaymentType
                            ? $parcel->payment_type
                            : (\App\Enums\OutgoingPaymentType::tryFrom((string) $parcel->payment_type) ?? \App\Enums\OutgoingPaymentType::PAID);
                        $isDeferred = $paymentEnum === \App\Enums\OutgoingPaymentType::DEFERRED;
                    @endphp
                    <tr class="{{ $isCancelled ? 'bg-rose-50/50 text-slate-400 line-through' : 'odd:bg-white even:bg-slate-50/50' }}">
                        <td class="border border-slate-300 px-1.5 py-1 text-center font-mono">{{ $idx + 1 }}</td>
                        <td class="border border-slate-300 px-1.5 py-1 font-mono font-bold">
                            {{ $parcel->receipt_number ? '#' . $parcel->receipt_number : '—' }}
                        </td>
                        <td class="border border-slate-300 px-2 py-1 font-bold">{{ $parcel->destinationLabel() ?: '—' }}</td>
                        <td class="border border-slate-300 px-2 py-1">
                            <span class="font-bold block">{{ $parcel->sender_name ?: '—' }}</span>
                            @if($parcel->sender_phone)
                                <span class="font-mono text-[9px] text-slate-500 dir-ltr inline-block">{{ $parcel->sender_phone }}</span>
                            @endif
                        </td>
                        <td class="border border-slate-300 px-2 py-1">
                            <span class="font-bold block">{{ $parcel->recipient_name ?: 'بدون اسم' }}</span>
                            <span class="font-mono text-[9px] text-slate-600 dir-ltr inline-block">{{ $parcel->recipient_phone ?: '—' }}</span>
                        </td>
                        <td class="border border-slate-300 px-2 py-1">{{ $parcel->package_type ?: 'طرد' }}</td>
                        <td class="border border-slate-300 px-1.5 py-1 text-center font-extrabold">
                            @if($isCancelled)
                                <span class="text-rose-700 no-underline inline-block">ملغي</span>
                            @else
                                <span class="{{ $isDeferred ? 'text-amber-800' : 'text-emerald-800' }}">
                                    {{ $paymentEnum->label() }}
                                </span>
                            @endif
                        </td>
                        <td class="border border-slate-300 px-2 py-1 font-mono font-bold text-left dir-ltr">
                            {{ $isCancelled ? '—' : number_format((float) ($parcel->cod_amount ?? 0)) }}
                        </td>
                        <td class="border border-slate-300 px-2 py-1 text-[9px] text-slate-600">
                            @if($isCancelled)
                                ملغي (مستبعد من الإجمالي)
                            @else
                                {{ $parcel->notes ?: '—' }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="border border-slate-300 px-2 py-3 text-center text-slate-500">
                            لا توجد طرود في هذه الشحنة
                        </td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="bg-slate-100 font-black text-slate-900">
                    <td colspan="7" class="border border-slate-400 px-2 py-1.5 text-left">
                        الإجمالي الكلي للطرود الفعّالة ({{ $activeCount }} طرد):
                    </td>
                    <td class="border border-slate-400 px-2 py-1.5 font-mono text-left dir-ltr">
                        {{ number_format($totalGrandAmount) }} ر.ي
                    </td>
                    <td class="border border-slate-400 px-2 py-1.5"></td>
                </tr>
            </tfoot>
        </table>
    </div>

    @if($shipment->notes)
        <div class="mb-3 px-2.5 py-1.5 border border-slate-300 rounded-lg bg-slate-50/70 text-[10px] print-avoid-break">
            <span class="font-extrabold text-slate-700">ملاحظات الكشف: </span>
            <span class="text-slate-700">{{ $shipment->notes }}</span>
        </div>
    @endif

    <!-- 4. شريط الملخص المالي والتوقيعات (محمي ضد تقسيم الصفحة) -->
    <div class="border-t-2 border-slate-800 pt-3 mt-3 space-y-4 print-avoid-break">
        <div class="grid grid-cols-4 gap-2.5 text-center">
            <div class="border border-slate-300 rounded-lg p-2 bg-slate-50">
                <p class="text-[10px] text-slate-600 font-bold">عدد الطرود</p>
                <p class="text-xs font-mono font-black mt-0.5 text-slate-900">{{ $activeCount }} طرد</p>
            </div>
            <div class="border border-slate-300 rounded-lg p-2 bg-slate-50">
                <p class="text-[10px] text-slate-600 font-bold">إجمالي المحاسب</p>
                <p class="text-xs font-mono font-black mt-0.5 dir-ltr text-slate-900">{{ number_format($totalPaidAmount) }} ر.ي</p>
            </div>
            <div class="border border-slate-300 rounded-lg p-2 bg-slate-50">
                <p class="text-[10px] text-slate-600 font-bold">إجمالي الآجل</p>
                <p class="text-xs font-mono font-black mt-0.5 dir-ltr text-slate-900">{{ number_format($totalDeferredAmount) }} ر.ي</p>
            </div>
            <div class="border-2 border-slate-800 rounded-lg p-2 bg-slate-100">
                <p class="text-[10px] text-slate-800 font-extrabold">الإجمالي الكلي</p>
                <p class="text-sm font-mono font-black mt-0.5 dir-ltr text-slate-950">{{ number_format($totalGrandAmount) }} ر.ي</p>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-6 pt-3 text-center text-[11px] font-bold text-slate-700">
            <div>
                <p class="mb-7">توقيع السائق</p>
                <p class="border-t border-slate-400 w-36 mx-auto pt-1 text-[10px] text-slate-500">
                    {{ $shipment->driver_name ?: '—' }}
                </p>
            </div>
            <div>
                <p class="mb-7">توقيع الموظف</p>
                <p class="border-t border-slate-400 w-36 mx-auto pt-1 text-[10px] text-slate-500">
                    {{ $senderOfficeName }}
                </p>
            </div>
            <div>
                <p class="mb-2">الختم الرسمي</p>
                <div class="border border-dashed border-slate-300 rounded-lg h-11 w-32 mx-auto"></div>
            </div>
        </div>
    </div>
</section>
