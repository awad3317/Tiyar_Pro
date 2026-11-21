@extends('office.layouts.app')

@section('title', 'طرود قادمة في الطريق')

@section('shell-class', 'bg-slate-50')

@section('content')
    <main class="flex-1 p-3.5 space-y-3.5"
          x-data="transitParcelsPage({ initialCount: {{ $transitParcels->count() }} })">

        {{-- رسائل النجاح أو الخطأ --}}
        @if (session('success'))
            <div class="p-3 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2 shadow-2xs">
                <span class="material-symbols-outlined text-base text-emerald-600">check_circle</span>
                <span class="flex-1">{{ session('success') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div class="p-3 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold flex items-center gap-2 shadow-2xs">
                <span class="material-symbols-outlined text-base text-rose-600">error</span>
                <span class="flex-1">{{ session('error') }}</span>
            </div>
        @endif

        {{-- رأس الصفحة --}}
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2.5">
                <a href="{{ route('office.dashboard') }}"
                   class="w-9 h-9 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-600 hover:text-primary transition-colors shadow-2xs">
                    <span class="material-symbols-outlined text-lg">arrow_forward</span>
                </a>
                <div>
                    <h2 class="text-sm font-extrabold text-slate-800 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-lg text-sky-600">local_shipping</span>
                        <span>طرود قادمة في الطريق</span>
                    </h2>
                    <p class="text-[11px] text-slate-400">استلام الطرود المرسلة إليك وتحويلها لطرد وارد مع إشعار العميل</p>
                </div>
            </div>

            <span class="px-2.5 py-1 rounded-xl bg-sky-100 text-sky-800 border border-sky-200 text-xs font-black shrink-0">
                <span x-text="remainingCount">{{ $transitParcels->count() }}</span> طرد
            </span>
        </div>

        {{-- قائمة الطرود القادمة --}}
        <div class="space-y-2.5" x-ref="transitList">
            @foreach ($transitParcels as $parcel)
                @php
                    $senderOfficeLabel = $parcel->office
                        ? ($parcel->office->branch_name
                            ? ($parcel->office->name . ' - ' . $parcel->office->branch_name)
                            : $parcel->office->name)
                        : 'مكتب غير معروف';
                    $paymentEnum = $parcel->payment_type instanceof \App\Enums\OutgoingPaymentType
                        ? $parcel->payment_type
                        : (\App\Enums\OutgoingPaymentType::tryFrom((string) $parcel->payment_type) ?? \App\Enums\OutgoingPaymentType::PAID);
                @endphp
                <article id="transit-card-{{ $parcel->id }}"
                         style="content-visibility: auto; contain-intrinsic-size: 160px;"
                         class="bg-white border border-sky-200/90 rounded-2xl p-3.5 shadow-xs space-y-2.5">
                    {{-- الرأس: المكتب المرسل ونوع الطرد --}}
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-lg">package_2</span>
                            </span>
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <h3 class="font-extrabold text-sm text-slate-800">{{ $parcel->package_type }}</h3>
                                    @if($parcel->receipt_number)
                                        <span class="px-1.5 py-0.5 rounded-md bg-slate-100 text-slate-700 border border-slate-200 text-[10px] font-mono font-extrabold">
                                            سند #{{ $parcel->receipt_number }}
                                        </span>
                                    @endif
                                </div>

                                <div class="flex items-center gap-1.5 flex-wrap mt-1">
                                    <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-md bg-purple-50 text-purple-800 border border-purple-200 text-[10px] font-extrabold">
                                        <span class="material-symbols-outlined text-[12px]">domain</span>
                                        <span>قادم من: {{ $senderOfficeLabel }}</span>
                                    </span>

                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md border text-[10px] font-extrabold {{ $paymentEnum->badgeClass() }}">
                                        <span>{{ $paymentEnum->label() }}:</span>
                                        <span class="font-mono">{{ number_format((float) ($parcel->cod_amount ?? 0)) }} ر.ي</span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        @if($parcel->dispatched_at)
                            <span class="text-[10px] font-bold text-sky-700 bg-sky-50 border border-sky-200/80 px-2 py-0.5 rounded-md shrink-0">
                                {{ $parcel->dispatched_at->diffForHumans() }}
                            </span>
                        @endif
                    </div>

                    {{-- تفاصيل المستلم والمرسل --}}
                    <div class="grid grid-cols-2 gap-2 bg-slate-50 rounded-xl p-2.5 text-xs border border-slate-100">
                        <div class="min-w-0">
                            <p class="text-[10px] text-slate-400 font-bold">المستلم</p>
                            <p class="font-bold text-slate-800 truncate">{{ $parcel->recipient_name ?: 'بدون اسم' }}</p>
                            <p class="text-[11px] text-slate-500 font-mono dir-ltr text-right">{{ $parcel->recipient_phone }}</p>
                        </div>
                        <div class="min-w-0 border-r border-slate-200 pr-2">
                            <p class="text-[10px] text-slate-400 font-bold">المرسل</p>
                            <p class="font-bold text-slate-800 truncate">{{ $parcel->sender_name }}</p>
                            <p class="text-[11px] text-slate-500 font-mono dir-ltr text-right">{{ $parcel->sender_phone }}</p>
                        </div>
                    </div>

                    @if($parcel->notes)
                        <p class="text-[11px] text-slate-600 bg-amber-50/60 border border-amber-100 rounded-xl px-2.5 py-1.5">
                            <span class="font-extrabold text-amber-800">ملاحظة:</span> {{ $parcel->notes }}
                        </p>
                    @endif

                    {{-- زر تأكيد الاستلام الفوري عبر AJAX وتحويله لطرد وارد بدون إعادة تحميل الصفحة --}}
                    <form method="POST"
                          action="{{ route('office.transit-parcels.receive', $parcel) }}"
                          class="pt-1"
                          @submit.prevent="receiveTransitParcel('{{ route('office.transit-parcels.receive', $parcel) }}', '{{ $parcel->id }}')">
                        @csrf
                        <button type="submit"
                                :disabled="Boolean(receivingIds['{{ $parcel->id }}'])"
                                class="w-full py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:scale-[0.99] text-white text-xs font-extrabold shadow-sm shadow-emerald-600/20 flex items-center justify-center gap-1.5 transition-all disabled:opacity-50">
                            <span class="material-symbols-outlined text-base">download_done</span>
                            <span x-text="receivingIds['{{ $parcel->id }}'] ? 'جاري الاستلام وإشعار العميل...' : 'تم الاستلام'"></span>
                        </button>
                    </form>
                </article>
            @endforeach

            {{-- حالة عدم وجود طرود قادمة --}}
            <div x-show="remainingCount === 0"
                 @if($transitParcels->count() > 0) x-cloak @endif
                 class="bg-white rounded-2xl border border-slate-200/80 p-10 text-center text-slate-400 space-y-2">
                <span class="material-symbols-outlined text-4xl text-emerald-400">task_alt</span>
                <p class="text-xs font-extrabold text-slate-700">لا توجد طرود قادمة في الطريق حالياً</p>
                <p class="text-[11px] text-slate-400">تم استلام جميع الطرود المرسلة إلى مكتبكم.</p>
                <div class="pt-2">
                    <a href="{{ route('office.parcels.index') }}"
                       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-primary text-white text-xs font-bold hover:bg-slate-800 transition-colors">
                        <span class="material-symbols-outlined text-sm">inventory_2</span>
                        <span>الذهاب إلى قائمة الطرود</span>
                    </a>
                </div>
            </div>
        </div>

    </main>
@endsection

@push('scripts')
<script>
    function transitParcelsPage(config) {
        return {
            remainingCount: Number(config.initialCount || 0),
            receivingIds: {},

            notifyToast(message, isError = false) {
                if (typeof this.showFeedback === 'function') {
                    this.showFeedback(message, isError);
                }
            },

            async receiveTransitParcel(url, parcelId) {
                if (this.receivingIds[parcelId]) return;
                this.receivingIds[parcelId] = true;

                const token = document.querySelector('meta[name="csrf-token"]')?.content || '';

                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': token,
                        },
                    });

                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.ok) {
                        this.receivingIds[parcelId] = false;
                        this.notifyToast(data.message || 'تعذر استلام الطرد.', true);
                        return;
                    }

                    const cardEl = document.getElementById('transit-card-' + parcelId);
                    if (cardEl) {
                        cardEl.remove();
                    }

                    if (typeof data.remaining_count === 'number') {
                        this.remainingCount = data.remaining_count;
                    } else {
                        this.remainingCount = Math.max(0, this.remainingCount - 1);
                    }

                    this.notifyToast(data.message || 'تم استلام الطرد بنجاح.', false);
                } catch (err) {
                    this.receivingIds[parcelId] = false;
                    this.notifyToast('تعذر الاتصال بالخادم، يرجى المحاولة مجدداً.', true);
                }
            }
        };
    }
</script>
@endpush
