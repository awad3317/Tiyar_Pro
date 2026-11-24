@extends('admin.layouts.app')

@section('title', 'مراقبة الطرود الشاملة')
@section('page-title', 'مراقبة وتتبع الطرود (المستلمة والمرسلة)')
@section('page-subtitle', 'استعراض وتحليل كافة الطرود المستلمة والمرسلة عبر شبكة المكاتب مع تحكم مباشر بالحالات')

@section('content')
@php
    $activeType = $activeType ?? ($filters['type'] ?? 'all');
    $todayFailedSmsCount = (int) ($todayFailedSmsCount ?? ($filteredStats['today_failed_sms'] ?? 0));
    $isSmsFailedFilterActive = ! empty($filters['sms_failed']);
    $baseFilterParams = array_filter([
        'office_id'   => $filters['office_id'] ?? null,
        'date_from'   => $filters['date_from'] ?? null,
        'date_to'     => $filters['date_to'] ?? null,
        'date_preset' => $filters['date_preset'] ?? null,
        'search'      => $filters['search'] ?? null,
    ], fn ($v) => $v !== null && $v !== '');
    $todayFailedSmsUrl = route('admin.parcels.index', array_filter([
        'type'        => 'incoming',
        'office_id'   => $filters['office_id'] ?? null,
        'date_preset' => 'today',
        'sms_failed'  => 1,
    ], fn ($v) => $v !== null && $v !== ''));
@endphp
<div class="space-y-6" x-data="parcelsMonitor()">

    <!-- 0. شريط التبديل السريع بين (الكل / الطرود المستلمة / الطرود المرسلة) -->
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="inline-flex items-center p-1.5 rounded-2xl bg-white border border-slate-200/90 shadow-xs gap-1">
            <a href="{{ route('admin.parcels.index', array_merge($baseFilterParams, ['type' => 'all'])) }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-extrabold transition-all {{ $activeType === 'all' ? 'bg-primary text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}">
                <span class="material-symbols-outlined text-[18px]">sync_alt</span>
                <span>الكل</span>
                <span class="px-2 py-0.5 rounded-lg text-[11px] font-mono {{ $activeType === 'all' ? 'bg-white/15 text-secondary' : 'bg-slate-100 text-slate-600' }}">
                    {{ $tabCounts['all'] ?? $filteredStats['total'] }}
                </span>
            </a>

            <a href="{{ route('admin.parcels.index', array_merge($baseFilterParams, ['type' => 'incoming'])) }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-extrabold transition-all {{ $activeType === 'incoming' ? 'bg-primary text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}">
                <span class="material-symbols-outlined text-[18px]">move_to_inbox</span>
                <span>الطرود المستلمة (الواردة)</span>
                <span class="px-2 py-0.5 rounded-lg text-[11px] font-mono {{ $activeType === 'incoming' ? 'bg-white/15 text-secondary' : 'bg-blue-50 text-blue-700' }}">
                    {{ $tabCounts['incoming'] ?? ($filteredStats['incoming_total'] ?? 0) }}
                </span>
            </a>

            <a href="{{ route('admin.parcels.index', array_merge($baseFilterParams, ['type' => 'outgoing'])) }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-extrabold transition-all {{ $activeType === 'outgoing' ? 'bg-primary text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}">
                <span class="material-symbols-outlined text-[18px]">outbox</span>
                <span>الطرود المرسلة (الصادرة)</span>
                <span class="px-2 py-0.5 rounded-lg text-[11px] font-mono {{ $activeType === 'outgoing' ? 'bg-white/15 text-secondary' : 'bg-indigo-50 text-indigo-700' }}">
                    {{ $tabCounts['outgoing'] ?? ($filteredStats['outgoing_total'] ?? 0) }}
                </span>
            </a>
        </div>
    </div>

    <!-- 1. شريط إحصائيات سريعة للنتائج المفلترة + كارد فشل إرسال SMS لليوم فقط -->
    @if ($activeType === 'incoming')
        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-4">
            <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-bold text-slate-400 uppercase">إجمالي المستلمة</p>
                    <h4 class="text-2xl font-black text-slate-900">{{ $filteredStats['incoming_total'] }}</h4>
                </div>
                <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center">
                    <span class="material-symbols-outlined text-xl">move_to_inbox</span>
                </div>
            </div>

            <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-bold text-slate-400 uppercase">بالمكتب (قيد الانتظار)</p>
                    <h4 class="text-2xl font-black text-blue-600">{{ $filteredStats['in_office'] }}</h4>
                </div>
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-xl">inventory_2</span>
                </div>
            </div>

            <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-bold text-slate-400 uppercase">تم التسليم للعميل</p>
                    <h4 class="text-2xl font-black text-emerald-600">{{ $filteredStats['delivered'] }}</h4>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-xl">task_alt</span>
                </div>
            </div>

            <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-bold text-slate-400 uppercase">مرتجع للمرسل</p>
                    <h4 class="text-2xl font-black text-rose-500">{{ $filteredStats['returned'] }}</h4>
                </div>
                <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-500 flex items-center justify-center">
                    <span class="material-symbols-outlined text-xl">assignment_return</span>
                </div>
            </div>

            <a href="{{ $todayFailedSmsUrl }}"
               title="اضغط لعرض الطرود التي فشل إرسال SMS لها اليوم"
               class="p-4 rounded-2xl border shadow-xs flex items-center justify-between transition-all hover:shadow-md {{ $isSmsFailedFilterActive ? 'bg-rose-50 border-rose-400 ring-2 ring-rose-200' : ($todayFailedSmsCount > 0 ? 'bg-rose-50/40 border-rose-200 hover:bg-rose-50/70' : 'bg-white border-slate-200/90 hover:bg-slate-50') }}">
                <div>
                    <p class="text-[11px] font-bold {{ $todayFailedSmsCount > 0 ? 'text-rose-600' : 'text-slate-400' }} uppercase">فشل إرسال SMS اليوم</p>
                    <h4 class="text-2xl font-black {{ $todayFailedSmsCount > 0 ? 'text-rose-600' : 'text-slate-800' }}">{{ $todayFailedSmsCount }}</h4>
                    <p class="text-[10px] font-bold {{ $todayFailedSmsCount > 0 ? 'text-rose-500' : 'text-slate-400' }} mt-0.5">طرود اليوم فقط · اضغط للعرض</p>
                </div>
                <div class="w-10 h-10 rounded-xl {{ $todayFailedSmsCount > 0 ? 'bg-rose-100 text-rose-600' : 'bg-slate-100 text-slate-500' }} flex items-center justify-center">
                    <span class="material-symbols-outlined text-xl">sms_failed</span>
                </div>
            </a>
        </div>
    @elseif ($activeType === 'outgoing')
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
            <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-bold text-slate-400 uppercase">إجمالي المرسلة</p>
                    <h4 class="text-2xl font-black text-indigo-900">{{ $filteredStats['outgoing_total'] }}</h4>
                </div>
                <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-xl">outbox</span>
                </div>
            </div>

            <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-bold text-slate-400 uppercase">في الانتظار</p>
                    <h4 class="text-2xl font-black text-amber-600">{{ $filteredStats['outgoing_pending'] }}</h4>
                </div>
                <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-xl">hourglass_top</span>
                </div>
            </div>

            <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-bold text-slate-400 uppercase">في الطريق</p>
                    <h4 class="text-2xl font-black text-sky-600">{{ $filteredStats['outgoing_in_transit'] }}</h4>
                </div>
                <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-xl">local_shipping</span>
                </div>
            </div>

            <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-bold text-slate-400 uppercase">وصل / تم التسليم</p>
                    <h4 class="text-2xl font-black text-emerald-600">{{ $filteredStats['outgoing_completed'] }}</h4>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-xl">task_alt</span>
                </div>
            </div>

            <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-bold text-slate-400 uppercase">ملغي</p>
                    <h4 class="text-2xl font-black text-rose-500">{{ $filteredStats['outgoing_cancelled'] }}</h4>
                </div>
                <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-500 flex items-center justify-center">
                    <span class="material-symbols-outlined text-xl">cancel</span>
                </div>
            </div>
        </div>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-4">
            <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-bold text-slate-400 uppercase">إجمالي النتائج</p>
                    <h4 class="text-2xl font-black text-slate-900">{{ $filteredStats['total'] }}</h4>
                </div>
                <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center">
                    <span class="material-symbols-outlined text-xl">all_inbox</span>
                </div>
            </div>

            <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-bold text-slate-400 uppercase">الطرود المستلمة</p>
                    <h4 class="text-2xl font-black text-blue-700">{{ $filteredStats['incoming_total'] }}</h4>
                    <p class="text-[10px] font-bold text-slate-400 mt-0.5">{{ $filteredStats['in_office'] }} بالمكتب · {{ $filteredStats['delivered'] }} مسلّم</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-xl">move_to_inbox</span>
                </div>
            </div>

            <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-bold text-slate-400 uppercase">الطرود المرسلة</p>
                    <h4 class="text-2xl font-black text-indigo-700">{{ $filteredStats['outgoing_total'] }}</h4>
                    <p class="text-[10px] font-bold text-slate-400 mt-0.5">{{ $filteredStats['outgoing_pending'] }} انتظار · {{ $filteredStats['outgoing_in_transit'] }} بالطريق</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-xl">outbox</span>
                </div>
            </div>

            <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-bold text-slate-400 uppercase">تم الاستلام / التسليم</p>
                    <h4 class="text-2xl font-black text-emerald-600">{{ $filteredStats['delivered'] + $filteredStats['outgoing_completed'] }}</h4>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-xl">task_alt</span>
                </div>
            </div>

            <a href="{{ $todayFailedSmsUrl }}"
               title="اضغط لعرض الطرود التي فشل إرسال SMS لها اليوم"
               class="p-4 rounded-2xl border shadow-xs flex items-center justify-between transition-all hover:shadow-md {{ $isSmsFailedFilterActive ? 'bg-rose-50 border-rose-400 ring-2 ring-rose-200' : ($todayFailedSmsCount > 0 ? 'bg-rose-50/40 border-rose-200 hover:bg-rose-50/70' : 'bg-white border-slate-200/90 hover:bg-slate-50') }}">
                <div>
                    <p class="text-[11px] font-bold {{ $todayFailedSmsCount > 0 ? 'text-rose-600' : 'text-slate-400' }} uppercase">فشل إرسال SMS اليوم</p>
                    <h4 class="text-2xl font-black {{ $todayFailedSmsCount > 0 ? 'text-rose-600' : 'text-slate-800' }}">{{ $todayFailedSmsCount }}</h4>
                    <p class="text-[10px] font-bold {{ $todayFailedSmsCount > 0 ? 'text-rose-500' : 'text-slate-400' }} mt-0.5">طرود اليوم فقط · اضغط للعرض</p>
                </div>
                <div class="w-10 h-10 rounded-xl {{ $todayFailedSmsCount > 0 ? 'bg-rose-100 text-rose-600' : 'bg-slate-100 text-slate-500' }} flex items-center justify-center">
                    <span class="material-symbols-outlined text-xl">sms_failed</span>
                </div>
            </a>
        </div>
    @endif

    @if ($isSmsFailedFilterActive)
        <div class="bg-rose-50 border border-rose-200 rounded-2xl px-4 py-3 flex items-center justify-between gap-3 text-xs font-bold text-rose-800">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-lg text-rose-600">sms_failed</span>
                <span>يتم حالياً عرض الطرود التي فشل إرسال رسالة SMS لها فقط مع أسباب الفشل.</span>
            </div>
            <a href="{{ route('admin.parcels.index', $baseFilterParams) }}"
               class="px-3 py-1.5 rounded-xl bg-white border border-rose-200 text-rose-700 hover:bg-rose-100 transition-colors shrink-0">
                إلغاء فلتر فشل SMS
            </a>
        </div>
    @endif

    <!-- 2. بطاقة الفلاتر المتقدمة -->
    <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-xs">
        <form method="GET" action="{{ route('admin.parcels.index') }}" class="space-y-4">
            <input type="hidden" name="type" value="{{ $activeType }}">

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

                <!-- البحث بالنص -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">البحث (اسم مستلم/مرسل، هاتف، رقم سند، أو خانة)</label>
                    <div class="relative">
                        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                               placeholder="اكتب للبحث..."
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 pr-9 text-xs font-semibold text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                        <span class="material-symbols-outlined text-slate-400 absolute right-2.5 top-1/2 -translate-y-1/2 text-lg pointer-events-none">search</span>
                    </div>
                </div>

                <!-- فلترة المكتب -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">المكتب (المستلم أو المرسل)</label>
                    <select name="office_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-bold text-slate-700 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                        <option value="">جميع المكاتب والفروع</option>
                        @foreach ($offices as $off)
                            <option value="{{ $off->id }}" {{ ($filters['office_id'] ?? '') == $off->id ? 'selected' : '' }}>
                                {{ $off->name }} {{ $off->branch_name ? "({$off->branch_name})" : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- فلترة الحالة -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">حالة الطرد</label>
                    <select name="status" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-bold text-slate-700 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                        <option value="">كافة الحالات</option>
                        @if ($activeType === 'incoming')
                            @foreach ($statuses as $st)
                                <option value="{{ $st->value }}" {{ ($filters['status'] ?? '') === $st->value ? 'selected' : '' }}>
                                    {{ $st->label() }}
                                </option>
                            @endforeach
                        @elseif ($activeType === 'outgoing')
                            @foreach ($outgoingStatuses as $ost)
                                <option value="{{ $ost->value }}" {{ ($filters['status'] ?? '') === $ost->value ? 'selected' : '' }}>
                                    {{ $ost->label() }}
                                </option>
                            @endforeach
                        @else
                            <optgroup label="حالات الطرود المستلمة (الواردة)">
                                @foreach ($statuses as $st)
                                    <option value="incoming:{{ $st->value }}" {{ in_array(($filters['status'] ?? ''), ['incoming:' . $st->value, $st->value], true) ? 'selected' : '' }}>
                                        مستلم — {{ $st->label() }}
                                    </option>
                                @endforeach
                            </optgroup>
                            <optgroup label="حالات الطرود المرسلة (الصادرة)">
                                @foreach ($outgoingStatuses as $ost)
                                    <option value="outgoing:{{ $ost->value }}" {{ ($filters['status'] ?? '') === 'outgoing:' . $ost->value ? 'selected' : '' }}>
                                        مرسل — {{ $ost->label() }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endif
                    </select>
                </div>

                <!-- الفترات الجاهزة (Preset) -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">الفترة الزمنية السريعة</label>
                    <select name="date_preset" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-bold text-slate-700 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                        <option value="">تاريخ مفتوح</option>
                        <option value="today" {{ ($filters['date_preset'] ?? '') === 'today' ? 'selected' : '' }}>طرود اليوم فقط</option>
                        <option value="yesterday" {{ ($filters['date_preset'] ?? '') === 'yesterday' ? 'selected' : '' }}>طرود الأمس</option>
                        <option value="this_week" {{ ($filters['date_preset'] ?? '') === 'this_week' ? 'selected' : '' }}>طرود هذا الأسبوع</option>
                        <option value="this_month" {{ ($filters['date_preset'] ?? '') === 'this_month' ? 'selected' : '' }}>طرود هذا الشهر</option>
                    </select>
                </div>
            </div>

            <!-- صف التواريخ المحددة والأزرار -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-2 border-t border-slate-100">
                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <span class="text-xs font-bold text-slate-500 whitespace-nowrap">من تاريخ:</span>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-mono">
                    <span class="text-xs font-bold text-slate-500 whitespace-nowrap">إلى:</span>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-mono">
                </div>

                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <button type="submit" class="flex-1 sm:flex-none px-6 py-2 rounded-xl bg-primary text-white text-xs font-bold hover:bg-primary-800 transition-colors flex items-center justify-center gap-1.5">
                        <span class="material-symbols-outlined text-base">filter_alt</span>
                        <span>تطبيق الفلترة</span>
                    </button>
                    <a href="{{ route('admin.parcels.index', $activeType !== 'all' ? ['type' => $activeType] : []) }}" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs font-bold transition-colors">
                        إعادة تعيين
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- 3. جدول مراقبة الطرود الشامل (المستلمة والمرسلة) -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead>
                    <tr class="bg-slate-100/70 text-slate-600 font-extrabold uppercase border-b border-slate-200">
                        <th class="py-4 px-5">النوع / رقم السند</th>
                        <th class="py-4 px-4">المكتب التابع</th>
                        <th class="py-4 px-4">المستلم / المرسل</th>
                        <th class="py-4 px-4">نوع الطرد والحساب</th>
                        <th class="py-4 px-4">المصدر / الوجهة</th>
                        <th class="py-4 px-4 text-center">الحالة الحالية</th>
                        <th class="py-4 px-4">التواريخ</th>
                        <th class="py-4 px-5 text-center">التحكم</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                    @forelse ($parcels as $parcel)
                        @php
                            $isOutgoing = ($parcel->parcel_direction ?? ($parcel instanceof \App\Models\OutgoingParcel ? 'outgoing' : 'incoming')) === 'outgoing';
                            $rowKey = ($isOutgoing ? 'outgoing-' : 'incoming-') . $parcel->id;
                            $statusVal = $parcel->status->value;
                            $statusLabel = $parcel->status->label();
                            $smsStatus = ! $isOutgoing ? (string) ($parcel->sms_status ?? '') : '';
                            $isSmsFailed = ! $isOutgoing && in_array($smsStatus, ['failed', 'failed_balance'], true);
                            $smsFailureReason = $isSmsFailed
                                ? (trim((string) ($parcel->sms_failure_reason ?? '')) ?: 'تعذر إرسال رسالة SMS تلقائياً.')
                                : null;

                            if ($isOutgoing) {
                                $paymentEnum = $parcel->payment_type instanceof \App\Enums\OutgoingPaymentType
                                    ? $parcel->payment_type
                                    : (\App\Enums\OutgoingPaymentType::tryFrom((string) $parcel->payment_type) ?? \App\Enums\OutgoingPaymentType::PAID);

                                [$statusBadgeCls, $statusIcon] = match ($statusVal) {
                                    'received', 'delivered' => ['bg-emerald-50 text-emerald-700 border border-emerald-200', 'check_circle'],
                                    'in_transit'            => ['bg-sky-50 text-sky-700 border border-sky-200', 'local_shipping'],
                                    'cancelled'             => ['bg-rose-50 text-rose-700 border border-rose-200', 'cancel'],
                                    default                 => ['bg-amber-50 text-amber-800 border border-amber-200', 'hourglass_top'],
                                };
                            } else {
                                [$statusBadgeCls, $statusIcon] = match ($statusVal) {
                                    'delivered' => ['bg-emerald-50 text-emerald-700 border border-emerald-200', 'check_circle'],
                                    'returned'  => ['bg-rose-50 text-rose-700 border border-rose-200', 'cancel'],
                                    default     => ['bg-blue-50 text-blue-700 border border-blue-200', 'schedule'],
                                };
                            }
                        @endphp
                        <tr class="{{ $isSmsFailed ? 'bg-rose-50/30 hover:bg-rose-50/60' : 'hover:bg-slate-50/80' }} transition-colors" id="parcel-row-{{ $rowKey }}">

                            <!-- النوع ورقم السند والخانة/الشحنة -->
                            <td class="py-4 px-5">
                                <div class="flex items-center gap-1.5 mb-1">
                                    @if ($isOutgoing)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200 text-[10px] font-extrabold">
                                            <span class="material-symbols-outlined text-[13px]">outbox</span>
                                            <span>مرسل</span>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-blue-50 text-blue-700 border border-blue-200 text-[10px] font-extrabold">
                                            <span class="material-symbols-outlined text-[13px]">move_to_inbox</span>
                                            <span>مستلم</span>
                                        </span>
                                    @endif
                                    <span class="font-mono font-black text-slate-900 text-sm">#{{ $parcel->receipt_number ?: '-' }}</span>
                                </div>

                                @if (! $isOutgoing && $parcel->slot_number)
                                    <span class="inline-flex items-center gap-0.5 mt-0.5 px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 border border-slate-200 text-[10px] font-bold font-mono">
                                        <span class="material-symbols-outlined text-[12px]">grid_view</span>
                                        <span>خانة: {{ $parcel->slot_number }}</span>
                                    </span>
                                @elseif ($isOutgoing && $parcel->shipment)
                                    <span class="inline-flex items-center gap-0.5 mt-0.5 px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-200 text-[10px] font-bold font-mono">
                                        <span class="material-symbols-outlined text-[12px]">local_shipping</span>
                                        <span>كشف #{{ $parcel->shipment->shipment_number }}</span>
                                    </span>
                                @endif
                            </td>

                            <!-- المكتب -->
                            <td class="py-4 px-4">
                                @if ($isOutgoing && ! $parcel->office && $parcel->externalPartnerLink)
                                    <span class="font-extrabold text-slate-900 block">{{ $parcel->externalPartnerLink->partner_name }}</span>
                                    <span class="text-[11px] text-amber-600 font-bold">{{ $parcel->externalPartnerLink->partner_city ?: 'شريك خارجي' }}</span>
                                @else
                                    <span class="font-extrabold text-slate-900 block">{{ $parcel->office?->name ?? 'مكتب غير معروف' }}</span>
                                    <span class="text-[11px] text-slate-400">{{ $parcel->office?->branch_name ?? '' }}</span>
                                @endif
                            </td>

                            <!-- المستلم والمرسل والهاتف -->
                            <td class="py-4 px-4">
                                <div class="font-bold text-slate-900">{{ $parcel->recipient_name ?: 'بدون اسم' }}</div>
                                <div class="flex items-center gap-1.5 mt-0.5" dir="ltr">
                                    <span class="font-mono text-slate-600">{{ $parcel->recipient_phone }}</span>
                                    @if ($parcel->recipient_phone)
                                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $parcel->recipient_phone) }}"
                                           target="_blank" title="محادثة واتساب مباشرة"
                                           class="text-emerald-600 hover:text-emerald-700 p-0.5">
                                            <span class="material-symbols-outlined text-[16px]">chat</span>
                                        </a>
                                    @endif
                                </div>

                                @if ($isOutgoing && ($parcel->sender_name || $parcel->sender_phone))
                                    <div class="mt-1 pt-1 border-t border-slate-100 text-[11px] text-slate-500">
                                        <span>المرسل: <strong class="text-slate-700">{{ $parcel->sender_name ?: '-' }}</strong></span>
                                        @if ($parcel->sender_phone)
                                            <span class="font-mono text-slate-400 mr-1" dir="ltr">({{ $parcel->sender_phone }})</span>
                                        @endif
                                    </div>
                                @endif
                            </td>

                            <!-- نوع الطرد والحساب -->
                            <td class="py-4 px-4">
                                <div class="text-slate-800 font-bold">{{ $parcel->package_type }}</div>
                                @if ($isOutgoing)
                                    <span class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded-md border text-[10px] font-extrabold {{ $paymentEnum->badgeClass() }}">
                                        <span>{{ $paymentEnum->label() }}:</span>
                                        <span class="font-mono">{{ number_format((float) ($parcel->cod_amount ?? 0)) }} ر.ي</span>
                                    </span>
                                @elseif ((float) ($parcel->cod_amount ?? 0) > 0)
                                    <span class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-200 text-[10px] font-extrabold font-mono">
                                        {{ number_format((float) $parcel->cod_amount) }} ر.ي
                                    </span>
                                @endif
                            </td>

                            <!-- المصدر / الوجهة -->
                            <td class="py-4 px-4">
                                @if ($isOutgoing)
                                    @if ($parcel->isExternal())
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-amber-50 text-amber-800 border border-amber-200 text-[11px] font-bold">
                                            <span class="material-symbols-outlined text-[14px]">storefront</span>
                                            <span>إلى: {{ $parcel->destinationLabel() ?: 'خارجي' }}</span>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-sky-50 text-sky-800 border border-sky-200 text-[11px] font-bold">
                                            <span class="material-symbols-outlined text-[14px]">domain</span>
                                            <span>إلى: {{ $parcel->destinationLabel() ?: '-' }}</span>
                                        </span>
                                    @endif
                                @else
                                    <div class="space-y-1">
                                        @if ($parcel->source_region)
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-purple-50 text-purple-700 border border-purple-200 text-[11px] font-bold">
                                                <span class="material-symbols-outlined text-[14px]">location_on</span>
                                                <span>من: {{ $parcel->source_region }}</span>
                                            </span>
                                        @endif
                                        @if ($parcel->source_office_name)
                                            <div class="text-[11px] text-slate-500">{{ $parcel->source_office_name }}</div>
                                        @endif
                                        @if (! $parcel->source_region && ! $parcel->source_office_name)
                                            <span class="text-slate-400">-</span>
                                        @endif
                                    </div>
                                @endif
                            </td>

                            <!-- شارة الحالة الحالية + حالة وسبب فشل الـ SMS إن وُجد -->
                            <td class="py-4 px-4 text-center">
                                <div class="flex flex-col items-center gap-1.5">
                                    <span id="status-badge-{{ $rowKey }}"
                                          class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-extrabold {{ $statusBadgeCls }}">
                                        <span class="material-symbols-outlined text-sm">{{ $statusIcon }}</span>
                                        <span>{{ $statusLabel }}</span>
                                    </span>

                                    @if ($isSmsFailed)
                                        <div class="max-w-[230px] text-right rounded-xl border px-2.5 py-1.5 {{ $smsStatus === 'failed_balance' ? 'bg-amber-50 border-amber-300 text-amber-900' : 'bg-rose-50 border-rose-200 text-rose-800' }}">
                                            <div class="inline-flex items-center gap-1 text-[10px] font-black mb-0.5">
                                                <span class="material-symbols-outlined text-[14px]">sms_failed</span>
                                                <span>{{ $smsStatus === 'failed_balance' ? 'فشل SMS (نفاد الرصيد)' : 'فشل إرسال SMS' }}</span>
                                            </div>
                                            <p class="text-[10.5px] font-bold leading-snug">
                                                {{ $smsFailureReason }}
                                            </p>
                                        </div>
                                    @elseif (! $isOutgoing && $smsStatus !== '')
                                        @php
                                            [$smsBadgeCls, $smsBadgeText] = match ($smsStatus) {
                                                'delivered' => ['bg-emerald-50 text-emerald-700 border-emerald-200', 'SMS وصلت'],
                                                'sent'      => ['bg-sky-50 text-sky-700 border-sky-200', 'SMS أُرسلت'],
                                                'pending'   => ['bg-slate-100 text-slate-600 border-slate-200', 'SMS قيد الإرسال'],
                                                default     => ['', ''],
                                            };
                                        @endphp
                                        @if ($smsBadgeText !== '')
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md border text-[10px] font-bold {{ $smsBadgeCls }}">
                                                <span class="material-symbols-outlined text-[12px]">sms</span>
                                                <span>{{ $smsBadgeText }}</span>
                                            </span>
                                        @endif
                                    @endif
                                </div>
                            </td>

                            <!-- التواريخ -->
                            <td class="py-4 px-4 text-[11px] font-mono text-slate-500">
                                @if ($isOutgoing)
                                    <div><span class="text-slate-400">إصدار:</span> {{ $parcel->created_at?->format('Y-m-d H:i') }}</div>
                                    @if ($parcel->dispatched_at)
                                        <div class="text-sky-600"><span class="text-sky-500">شحن:</span> {{ $parcel->dispatched_at->format('Y-m-d H:i') }}</div>
                                    @endif
                                    @if ($parcel->received_at)
                                        <div class="text-emerald-600 font-bold"><span class="text-emerald-500">استلام:</span> {{ $parcel->received_at->format('Y-m-d H:i') }}</div>
                                    @endif
                                @else
                                    <div><span class="text-slate-400">وصول:</span> {{ $parcel->created_at?->format('Y-m-d H:i') }}</div>
                                    @if ($parcel->delivered_at)
                                        <div class="text-emerald-600 font-bold"><span class="text-emerald-500">تسليم:</span> {{ $parcel->delivered_at->format('Y-m-d H:i') }}</div>
                                    @endif
                                @endif
                            </td>

                            <!-- أزرار التحكم: تعديل الحالة وحذف -->
                            <td class="py-4 px-5 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button type="button"
                                            @click="openStatusModal('{{ $isOutgoing ? 'outgoing' : 'incoming' }}', '{{ $parcel->id }}', @js((string) ($parcel->receipt_number ?: $parcel->id)), '{{ $statusVal }}')"
                                            title="تعديل حالة الطرد"
                                            class="p-2 rounded-xl text-slate-500 hover:text-primary hover:bg-slate-100 transition-colors">
                                        <span class="material-symbols-outlined text-lg">edit_note</span>
                                    </button>

                                    <button type="button"
                                            @click="deleteParcel('{{ $isOutgoing ? 'outgoing' : 'incoming' }}', '{{ $parcel->id }}', @js((string) ($parcel->receipt_number ?: $parcel->id)))"
                                            title="حذف الطرد نهائياً"
                                            class="p-2 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors">
                                        <span class="material-symbols-outlined text-lg">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-slate-400 font-bold text-sm">
                                لا توجد أي طرود مطابقة لشروط البحث والفلترة.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($parcels->hasPages())
            <div class="p-4 border-t border-slate-100 bg-slate-50/50">
                {{ $parcels->links() }}
            </div>
        @endif
    </div>

    <!-- نافذة تعديل حالة الطرد (Modal) تدعم المستلم والمرسل -->
    <div x-show="statusModal.show"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">

        <div @click.outside="statusModal.show = false"
             class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-slate-200">

            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-secondary text-2xl">published_with_changes</span>
                    <h3 class="text-sm font-extrabold text-slate-900"
                        x-text="statusModal.direction === 'outgoing' ? 'تعديل حالة طرد مرسل' : 'تعديل حالة طرد مستلم'"></h3>
                </div>
                <button type="button" @click="statusModal.show = false" class="text-slate-400 hover:text-slate-600">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>

            <p class="text-xs text-slate-500 mb-4">
                تعديل حالة السند: <span class="font-mono font-bold text-slate-800" x-text="'#' + statusModal.receiptNumber"></span>
            </p>

            <div class="space-y-2 mb-6">
                <label class="block text-xs font-bold text-slate-700">اختر الحالة الجديدة:</label>

                <template x-if="statusModal.direction === 'incoming'">
                    <select x-model="statusModal.newStatus" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs font-bold text-slate-800 focus:bg-white focus:outline-none focus:border-secondary">
                        @foreach ($statuses as $st)
                            <option value="{{ $st->value }}">{{ $st->label() }}</option>
                        @endforeach
                    </select>
                </template>

                <template x-if="statusModal.direction === 'outgoing'">
                    <select x-model="statusModal.newStatus" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs font-bold text-slate-800 focus:bg-white focus:outline-none focus:border-secondary">
                        @foreach ($outgoingStatuses as $ost)
                            <option value="{{ $ost->value }}">{{ $ost->label() }}</option>
                        @endforeach
                    </select>
                </template>
            </div>

            <div class="flex items-center justify-end gap-2">
                <button type="button" @click="statusModal.show = false" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-600">
                    إلغاء
                </button>
                <button type="button" @click="submitStatusChange()" :disabled="statusModal.loading"
                        class="px-5 py-2 rounded-xl bg-secondary text-primary-950 text-xs font-extrabold hover:brightness-105 disabled:opacity-50">
                    <span x-show="!statusModal.loading">حفظ الحالة</span>
                    <span x-show="statusModal.loading" x-cloak>جاري التحديث...</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function parcelsMonitor() {
        return {
            statusModal: {
                show: false,
                direction: 'incoming',
                parcelId: null,
                receiptNumber: '',
                newStatus: '',
                loading: false
            },

            openStatusModal(direction, id, receipt, currentStatus) {
                this.statusModal.direction = direction === 'outgoing' ? 'outgoing' : 'incoming';
                this.statusModal.parcelId = id;
                this.statusModal.receiptNumber = receipt || id;
                this.statusModal.newStatus = currentStatus;
                this.statusModal.loading = false;
                this.statusModal.show = true;
            },

            resolveBadgeVisuals(direction, status) {
                if (direction === 'outgoing') {
                    if (status === 'received' || status === 'delivered') {
                        return { cls: 'bg-emerald-50 text-emerald-700 border border-emerald-200', icon: 'check_circle' };
                    }
                    if (status === 'in_transit') {
                        return { cls: 'bg-sky-50 text-sky-700 border border-sky-200', icon: 'local_shipping' };
                    }
                    if (status === 'cancelled') {
                        return { cls: 'bg-rose-50 text-rose-700 border border-rose-200', icon: 'cancel' };
                    }
                    return { cls: 'bg-amber-50 text-amber-800 border border-amber-200', icon: 'hourglass_top' };
                }

                if (status === 'delivered') {
                    return { cls: 'bg-emerald-50 text-emerald-700 border border-emerald-200', icon: 'check_circle' };
                }
                if (status === 'returned') {
                    return { cls: 'bg-rose-50 text-rose-700 border border-rose-200', icon: 'cancel' };
                }
                return { cls: 'bg-blue-50 text-blue-700 border border-blue-200', icon: 'schedule' };
            },

            async submitStatusChange() {
                this.statusModal.loading = true;
                const isOut = this.statusModal.direction === 'outgoing';
                const endpoint = isOut
                    ? `/admin/outgoing-parcels/${this.statusModal.parcelId}/status`
                    : `/admin/parcels/${this.statusModal.parcelId}/status`;

                try {
                    const res = await fetch(endpoint, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ status: this.statusModal.newStatus })
                    });
                    const data = await res.json();
                    if (data.success) {
                        const rowKey = `${this.statusModal.direction}-${this.statusModal.parcelId}`;
                        const badge = document.getElementById(`status-badge-${rowKey}`);
                        if (badge) {
                            const vis = this.resolveBadgeVisuals(this.statusModal.direction, data.status);
                            badge.className = `inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-extrabold ${vis.cls}`;
                            badge.innerHTML = `<span class="material-symbols-outlined text-sm">${vis.icon}</span><span>${data.label}</span>`;
                        }
                        this.statusModal.show = false;
                    } else {
                        alert(data.message || 'فشل تحديث الحالة.');
                    }
                } catch (e) {
                    alert('تعذر الاتصال بالخادم لتحديث حالة الطرد.');
                } finally {
                    this.statusModal.loading = false;
                }
            },

            async deleteParcel(direction, id, receipt) {
                const labelType = direction === 'outgoing' ? 'المرسل' : 'المستلم';
                if (!confirm(`هل أنت متأكد من رغبتك بحذف الطرد ${labelType} (#${receipt || id}) نهائياً؟`)) return;

                const endpoint = direction === 'outgoing'
                    ? `/admin/outgoing-parcels/${id}`
                    : `/admin/parcels/${id}`;

                try {
                    const res = await fetch(endpoint, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        }
                    });
                    const data = await res.json();
                    if (data.success) {
                        const row = document.getElementById(`parcel-row-${direction}-${id}`);
                        if (row) row.remove();
                    } else {
                        alert(data.message || 'تعذر حذف الطرد.');
                    }
                } catch (e) {
                    alert('حدث خطأ أثناء الحذف.');
                }
            }
        };
    }
</script>
@endpush
