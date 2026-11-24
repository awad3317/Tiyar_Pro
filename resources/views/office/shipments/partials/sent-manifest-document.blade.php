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

    $manifestDate = ($shipment->dispatched_at ?? $shipment->created_at)?->format('Y-m-d') ?? '—';
    $senderOfficeName = $shipment->office?->name
        ?? $shipment->externalPartnerLink?->partner_name
        ?? ($office?->name ?? 'المكتب');
    $senderBranchName = $shipment->office?->branch_name
        ?? $shipment->externalPartnerLink?->partner_city
        ?? ($office?->branch_name ?? null);
@endphp

<!-- قالب الطباعة الرسمي الشامل (توأم مطابق لقالب كشف المحاسب A4) -->
<section class="w-full bg-white text-slate-900 text-[11px] leading-snug">
    <!-- 1. ترويسة الكشف -->
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
                <span>رقم الكشف: </span>
                <span class="font-mono font-black">#{{ $shipment->shipment_number }}</span>
            </p>
            <p>
                <span>تاريخ الكشف: </span>
                <span class="font-mono font-bold dir-ltr inline-block">{{ $manifestDate }}</span>
            </p>
            <p>
                <span>الحالة: </span>
                <span class="font-extrabold">{{ $shipment->status->label() }}</span>
            </p>
        </div>
    </div>

    <!-- 2. بيانات السائق والمصدر -->
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
            <span class="text-slate-500">إجمالي الطرود: </span>
            <strong class="font-mono font-black">{{ $activeCount }}</strong>
            <span class="text-[10px] text-slate-600">
                ({{ $deferredCount }} آجل / {{ $paidCount }} محاسب)
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

    <!-- 3. جدول الطرود الشامل (8 أعمدة مضغوطة مطابقة لكشف المحاسب تماماً) -->
    <div class="mb-4">
        <div class="flex items-center justify-between mb-1.5">
            <h2 class="text-xs font-black text-slate-900">أولاً: بيان الطرود المدرجة في الكشف</h2>
            @if($cancelledCount > 0)
                <span class="text-[10px] font-bold text-slate-500">
                    * تم استبعاد ({{ $cancelledCount }}) طرد ملغي من الكشف والمجاميع
                </span>
            @endif
        </div>

        <div class="overflow-x-auto print:overflow-visible">
            <table class="print-table w-full border-collapse border border-slate-400 text-[10px]">
                <thead>
                    <tr class="bg-slate-200 text-slate-900 font-extrabold">
                        <th class="border border-slate-400 px-1.5 py-1.5 text-center w-8">#</th>
                        <th class="border border-slate-400 px-2 py-1.5 text-right">رقم السند</th>
                        <th class="border border-slate-400 px-2 py-1.5 text-right">اسم المستلم</th>
                        <th class="border border-slate-400 px-2 py-1.5 text-right">رقم الجوال</th>
                        <th class="border border-slate-400 px-2 py-1.5 text-right">النوع</th>
                        <th class="border border-slate-400 px-2 py-1.5 text-right">الوجهة</th>
                        <th class="border border-slate-400 px-2 py-1.5 text-center w-16">الدفع</th>
                        <th class="border border-slate-400 px-2 py-1.5 text-left w-24">المبلغ (ر.ي)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($activeParcels as $idx => $parcel)
                        @php
                            $paymentEnum = $parcel->payment_type instanceof \App\Enums\OutgoingPaymentType
                                ? $parcel->payment_type
                                : (\App\Enums\OutgoingPaymentType::tryFrom((string) $parcel->payment_type) ?? \App\Enums\OutgoingPaymentType::PAID);
                        @endphp
                        <tr class="odd:bg-white even:bg-slate-50/50">
                            <td class="border border-slate-300 px-1.5 py-1 text-center font-mono">{{ $idx + 1 }}</td>
                            <td class="border border-slate-300 px-2 py-1 font-mono">{{ $parcel->receipt_number ?: '—' }}</td>
                            <td class="border border-slate-300 px-2 py-1 font-bold">{{ $parcel->recipient_name ?: 'بدون اسم' }}</td>
                            <td class="border border-slate-300 px-2 py-1 font-mono dir-ltr text-right">{{ $parcel->recipient_phone ?: '—' }}</td>
                            <td class="border border-slate-300 px-2 py-1">{{ $parcel->package_type ?: 'طرد' }}</td>
                            <td class="border border-slate-300 px-2 py-1">{{ $parcel->destinationLabel() ?: '—' }}</td>
                            <td class="border border-slate-300 px-2 py-1 text-center font-bold">{{ $paymentEnum->label() }}</td>
                            <td class="border border-slate-300 px-2 py-1 font-mono font-bold text-left dir-ltr">{{ number_format((float) ($parcel->cod_amount ?? 0)) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="border border-slate-300 px-2 py-3 text-center text-slate-500">لا توجد طرود في هذا الكشف</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="bg-slate-100 font-black text-slate-900">
                        <td colspan="7" class="border border-slate-400 px-2 py-1.5 text-left">الإجمالي الكلي للطرود:</td>
                        <td class="border border-slate-400 px-2 py-1.5 font-mono text-left dir-ltr">{{ number_format($totalGrandAmount) }} ر.ي</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    @if($shipment->notes)
        <div class="mb-3 px-2.5 py-1.5 border border-slate-300 rounded-lg bg-slate-50/70 text-[10px]" style="break-inside: avoid;">
            <span class="font-extrabold text-slate-700">ملاحظات: </span>
            <span class="text-slate-700">{{ $shipment->notes }}</span>
        </div>
    @endif

    <!-- 4. الملخص النهائي والتوقيعات (توأم كشف المحاسب: 3 مربعات مالية + عمودان للتوقيع) -->
    <div class="border-t-2 border-slate-800 pt-3 mt-3 space-y-4" style="break-inside: avoid;">
        <div class="grid grid-cols-3 gap-3 text-center">
            <div class="border border-slate-300 rounded-lg p-2 bg-slate-50">
                <p class="text-[10px] text-slate-600 font-bold">إجمالي طرود الآجل</p>
                <p class="text-xs font-mono font-black mt-0.5 dir-ltr">{{ number_format($totalDeferredAmount) }} ر.ي</p>
            </div>
            <div class="border border-slate-300 rounded-lg p-2 bg-slate-50">
                <p class="text-[10px] text-slate-600 font-bold">إجمالي طرود المحاسب</p>
                <p class="text-xs font-mono font-black mt-0.5 dir-ltr">{{ number_format($totalPaidAmount) }} ر.ي</p>
            </div>
            <div class="border-2 border-slate-800 rounded-lg p-2 bg-slate-100">
                <p class="text-[10px] text-slate-800 font-extrabold">الإجمالي الكلي</p>
                <p class="text-sm font-mono font-black mt-0.5 dir-ltr">{{ number_format($totalGrandAmount) }} ر.ي</p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-8 pt-4 text-center text-[11px] font-bold text-slate-700">
            <div>
                <p class="mb-6">توقيع الموظف</p>
                <p class="border-t border-slate-400 w-44 mx-auto pt-1 text-[10px] text-slate-500">
                    {{ $senderOfficeName }}
                </p>
            </div>
            <div>
                <p class="mb-6">توقيع السائق / المستلم</p>
                <p class="border-t border-slate-400 w-44 mx-auto pt-1 text-[10px] text-slate-500">
                    {{ $shipment->driver_name ?: '' }}
                </p>
            </div>
        </div>
    </div>
</section>
