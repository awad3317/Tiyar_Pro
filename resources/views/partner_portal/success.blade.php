@extends('partner_portal.layout')

@section('title', 'تم إرسال الكشف #' . $shipment->shipment_number)

@section('content')
    <div class="space-y-4">
        {{-- بطاقة تأكيد الإرسال بنجاح --}}
        <section class="bg-emerald-50 border border-emerald-200 rounded-3xl p-5 text-center space-y-2.5 shadow-2xs">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-emerald-600 text-white flex items-center justify-center shadow-xs">
                <span class="material-symbols-outlined text-3xl">verified</span>
            </div>

            <div class="space-y-1">
                <p class="text-[11px] font-extrabold text-emerald-700">تم اعتماد وإرسال الكشف بنجاح</p>
                <h2 class="text-lg font-black font-mono text-primary dir-ltr">#{{ $shipment->shipment_number }}</h2>
                <p class="text-xs text-slate-600">
                    وصل الكشف مباشرة إلى نظام <strong class="text-primary">{{ $brand['office_name'] ?? 'المكتب المستلم' }}</strong> وبانتظار الفحص والاستلام عند وصول السائق.
                </p>
            </div>

            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white border border-emerald-200 text-[10px] font-bold text-slate-600">
                <span class="material-symbols-outlined text-xs text-emerald-600">lock</span>
                <span>نسخة معتمدة للقراءة فقط — وقت الإرسال: {{ $shipment->dispatched_at?->format('Y/m/d H:i') }}</span>
            </div>
        </section>

        {{-- ملخص السائق والمجاميع --}}
        <section class="bg-white border border-slate-200 rounded-2xl p-4 shadow-2xs space-y-3">
            <div class="flex items-center justify-between bg-slate-50 border border-slate-100 rounded-xl px-3 py-2.5 text-xs">
                <div class="flex items-center gap-1.5 font-bold text-slate-600">
                    <span class="material-symbols-outlined text-base text-primary">person</span>
                    <span>السائق: <strong class="text-slate-900">{{ $shipment->driver_name }}</strong></span>
                </div>
                <span class="font-mono font-black text-primary dir-ltr">{{ $shipment->driver_phone }}</span>
            </div>

            @if ($shipment->notes)
                <p class="text-[11px] bg-amber-50/70 border border-amber-200/70 text-amber-900 rounded-xl px-3 py-2 font-bold">
                    ملاحظة الكشف: {{ $shipment->notes }}
                </p>
            @endif

            <div class="grid grid-cols-4 gap-1.5 text-center">
                <div class="bg-slate-50 border border-slate-200 rounded-xl py-2 px-1">
                    <p class="text-[9px] font-bold text-slate-500">الطرود</p>
                    <p class="text-xs font-mono font-black text-primary">{{ (int) $shipment->parcels_count }}</p>
                </div>
                <div class="bg-emerald-50 border border-emerald-200/70 rounded-xl py-2 px-1">
                    <p class="text-[9px] font-bold text-emerald-700">المحاسب</p>
                    <p class="text-xs font-mono font-black text-emerald-800">{{ number_format((float) $shipment->total_paid_amount) }}</p>
                </div>
                <div class="bg-amber-50 border border-amber-200/70 rounded-xl py-2 px-1">
                    <p class="text-[9px] font-bold text-amber-700">الآجل</p>
                    <p class="text-xs font-mono font-black text-amber-800">{{ number_format((float) $shipment->total_deferred_amount) }}</p>
                </div>
                <div class="bg-primary text-white rounded-xl py-2 px-1">
                    <p class="text-[9px] font-bold text-slate-300">الإجمالي</p>
                    <p class="text-xs font-mono font-black text-secondary">{{ number_format((float) $shipment->total_amount) }}</p>
                </div>
            </div>
        </section>

        {{-- جدول/قائمة الطرود المُرسلة (مقفلة للعرض فقط) --}}
        <section class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-2xs">
            <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                <h3 class="text-xs font-extrabold text-slate-800 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-base text-primary">list_alt</span>
                    <span>تفاصيل الطرود المُرسلة ({{ $parcels->count() }})</span>
                </h3>
                <span class="text-[10px] font-bold text-slate-400">مقفل ضد التعديل</span>
            </div>

            <div class="divide-y divide-slate-100">
                @foreach ($parcels as $index => $parcel)
                    @php
                        $isDeferred = $parcel->payment_type === \App\Enums\OutgoingPaymentType::DEFERRED;
                    @endphp
                    <div class="p-3 text-xs flex items-start justify-between gap-2">
                        <div class="min-w-0 space-y-0.5">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="text-[10px] font-mono font-bold text-slate-400">{{ $index + 1 }}.</span>
                                <span class="px-1.5 py-0.5 rounded bg-slate-100 border border-slate-200 font-mono text-[10px] font-black text-primary dir-ltr">
                                    {{ $parcel->receipt_number }}
                                </span>
                                <span class="font-extrabold text-slate-900">{{ $parcel->recipient_name }}</span>
                                <span class="font-mono text-[11px] text-slate-500 dir-ltr">({{ $parcel->recipient_phone }})</span>
                            </div>
                            <p class="text-[11px] text-slate-500 font-bold">
                                {{ $parcel->package_type }}
                                @if ($parcel->notes)
                                    • <span class="text-amber-800">{{ $parcel->notes }}</span>
                                @endif
                            </p>
                        </div>

                        <span class="px-2 py-0.5 rounded-md border text-[10px] font-extrabold shrink-0 {{ $isDeferred ? 'bg-amber-50 text-amber-800 border-amber-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200' }}">
                            @if ($isDeferred || (float) $parcel->cod_amount > 0)
                                {{ $isDeferred ? 'آجل' : 'محاسب' }}: {{ number_format((float) $parcel->cod_amount) }} ر.ي
                            @else
                                محاسب
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- زر إرسال كشف جديد --}}
        <div class="pt-1">
            <a href="{{ route('partner.portal.show', ['token' => $link->token]) }}"
               class="w-full py-3.5 rounded-2xl bg-primary hover:bg-slate-800 text-secondary text-xs sm:text-sm font-extrabold flex items-center justify-center gap-2 shadow-sm transition-colors">
                <span class="material-symbols-outlined text-lg">add_circle</span>
                <span>إرسال كشف جديد</span>
            </a>
        </div>
    </div>
@endsection
