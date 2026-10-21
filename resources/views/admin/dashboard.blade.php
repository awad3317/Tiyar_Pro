@extends('admin.layouts.app')

@section('title', 'لوحة التحكم والإحصائيات المركزية')
@section('page-title', 'لوحة التحكم والإحصائيات')
@section('page-subtitle', 'نظرة شاملة ومباشرة على أداء المكاتب، وحالة جلسات الواتساب، وحركة الطرود')

@section('header-actions')
    <a href="{{ route('admin.offices.create') }}"
       class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-primary text-white text-xs font-extrabold hover:bg-primary-800 transition-all shadow-sm">
        <span class="material-symbols-outlined text-lg">add_business</span>
        <span>إضافة مكتب جديد</span>
    </a>
    <a href="{{ route('admin.parcels.index') }}"
       class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-secondary text-primary-950 text-xs font-extrabold hover:brightness-105 transition-all shadow-sm">
        <span class="material-symbols-outlined text-lg">manage_search</span>
        <span>مراقبة الطرود</span>
    </a>
@endsection

@section('content')
<div class="space-y-8" x-data="dashboardLiveMonitor()">

    <!-- 1. بطاقات المؤشرات الرئيسية (KPI Cards) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">
        
        <!-- إجمالي المكاتب -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/90 shadow-xs hover:shadow-md transition-shadow relative overflow-hidden group">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">شبكة المكاتب والفروع</p>
                    <h3 class="text-3xl font-black text-slate-900">{{ $stats['total_offices'] }}</h3>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-sky-50 text-sky-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                    <span class="material-symbols-outlined text-2xl">domain</span>
                </div>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-bold">
                <span class="text-emerald-600 flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    {{ $stats['active_offices'] }} مكتب نشط
                </span>
                <span class="text-rose-500 flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                    {{ $stats['inactive_offices'] }} معطل/محظور
                </span>
            </div>
        </div>

        <!-- حالة اتصال الواتساب لجميع المكاتب -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/90 shadow-xs hover:shadow-md transition-shadow relative overflow-hidden group">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">جلسات واتساب المكاتب</p>
                    <div class="flex items-baseline gap-2">
                        <h3 class="text-3xl font-black text-slate-900" x-text="stats.online_count">{{ $stats['whatsapp_online_count'] }}</h3>
                        <span class="text-xs font-bold text-slate-400">من أصل {{ $stats['total_offices'] }} متصل</span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                    <span class="material-symbols-outlined text-2xl">chat</span>
                </div>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-bold">
                <span class="text-emerald-600 flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span x-text="stats.online_count">{{ $stats['whatsapp_online_count'] }}</span>
                    <span>أونلاين Online</span>
                </span>
                <span class="text-slate-500 flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                    <span x-text="stats.offline_count">{{ $stats['whatsapp_offline_count'] }}</span>
                    <span>أوفلاين Offline</span>
                </span>
            </div>
        </div>

        <!-- عداد طرود اليوم (مؤشر الرسائل والإشعارات اليومية) -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/90 shadow-xs hover:shadow-md transition-shadow relative overflow-hidden group">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">إشعارات وطرود اليوم</p>
                    <div class="flex items-baseline gap-2">
                        <h3 class="text-3xl font-black text-secondary">{{ $stats['today_parcels'] }}</h3>
                        <span class="text-xs font-bold text-slate-500">طرد تم إشعاره</span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-amber-50 text-secondary flex items-center justify-center group-hover:scale-110 transition-transform">
                    <span class="material-symbols-outlined text-2xl">send_to_mobile</span>
                </div>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-bold text-slate-500">
                <span>إجمالي حركة الإدخال اليومية</span>
                <span class="text-secondary font-mono">{{ now()->format('Y/m/d') }}</span>
            </div>
        </div>

        <!-- إجمالي الطرود وتوزيعها -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/90 shadow-xs hover:shadow-md transition-shadow relative overflow-hidden group">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">إجمالي الطرود التراكمية</p>
                    <h3 class="text-3xl font-black text-slate-900">{{ $stats['total_parcels'] }}</h3>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-primary-50 text-primary flex items-center justify-center group-hover:scale-110 transition-transform">
                    <span class="material-symbols-outlined text-2xl">inventory_2</span>
                </div>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-[11px] font-bold">
                <span class="text-blue-600">{{ $stats['in_office_parcels'] }} بالمكتب</span>
                <span class="text-emerald-600">{{ $stats['delivered_parcels'] }} مستلم</span>
                <span class="text-rose-500">{{ $stats['returned_parcels'] }} مرتجع</span>
            </div>
        </div>
    </div>

    <!-- 2. جدول مصغر ومباشر: حالة المكاتب الحية وحالة الواتساب -->
    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
        
        <div class="p-5 lg:p-6 border-b border-slate-200/80 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-50/50">
            <div>
                <h2 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                    <span class="material-symbols-outlined text-secondary text-2xl">cell_tower</span>
                    <span>المراقبة المباشرة للمكاتب وجلسات الواتساب</span>
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">متابعة فورية لاتصال السيرفر، كاش الاتصال، وحركة الطرود لكل مكتب</p>
            </div>

            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold text-slate-500">تم التحديث: <span class="font-mono text-slate-700">{{ now()->format('h:i A') }}</span></span>
                <a href="{{ route('admin.offices.index') }}" class="text-xs font-bold text-primary hover:text-secondary inline-flex items-center gap-1 transition-colors pr-2">
                    <span>عرض كافة المكاتب ({{ $stats['total_offices'] }})</span>
                    <span class="material-symbols-outlined text-base">arrow_left</span>
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead>
                    <tr class="bg-slate-100/70 text-slate-600 text-xs font-extrabold uppercase border-b border-slate-200">
                        <th class="py-3.5 px-6">المكتب / الفرع</th>
                        <th class="py-3.5 px-4">رقم الواتساب / الشريحة</th>
                        <th class="py-3.5 px-4 text-center">حالة الجلسة (WhatsApp)</th>
                        <th class="py-3.5 px-4 text-center">طرود اليوم</th>
                        <th class="py-3.5 px-4 text-center">إجمالي الطرود</th>
                        <th class="py-3.5 px-4 text-center">حالة الحساب</th>
                        <th class="py-3.5 px-6 text-center">تحكم سريع</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($offices as $office)
                        <tr class="hover:bg-slate-50/80 transition-colors" id="office-row-{{ $office->id }}">
                            
                            <!-- اسم المكتب -->
                            <td class="py-4 px-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-primary-50 text-primary flex items-center justify-center font-black text-sm shrink-0">
                                        {{ mb_substr($office->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <a href="{{ route('admin.offices.edit', $office) }}" class="font-extrabold text-slate-900 hover:text-secondary transition-colors block">
                                            {{ $office->name }}
                                        </a>
                                        <span class="text-xs text-slate-500 font-medium">{{ $office->branch_name ?: 'الفرع الرئيسي' }}</span>
                                    </div>
                                </div>
                            </td>

                            <!-- أرقام الاتصال -->
                            <td class="py-4 px-4 font-mono text-xs text-slate-600">
                                <div class="flex items-center gap-1.5 text-slate-900 font-semibold" dir="ltr">
                                    <span class="material-symbols-outlined text-[16px] text-emerald-600">chat</span>
                                    <span>{{ $office->whatsapp_sender_phone }}</span>
                                </div>
                                <div class="flex items-center gap-1.5 text-slate-500 mt-1" dir="ltr">
                                    <span class="material-symbols-outlined text-[16px] text-sky-600">sms</span>
                                    <span>{{ $office->httpsms_from_phone }}</span>
                                </div>
                            </td>

                            <!-- حالة الواتساب -->
                            <td class="py-4 px-4 text-center">
                                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold"
                                     id="wa-badge-{{ $office->id }}"
                                     class="{{ $office->whatsapp_live_status === 'online' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : ($office->whatsapp_live_status === 'not_configured' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-rose-50 text-rose-700 border border-rose-200') }}">
                                    @if ($office->whatsapp_live_status === 'online')
                                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                        <span>متصل Online</span>
                                    @elseif ($office->whatsapp_live_status === 'not_configured')
                                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                        <span>غير مهيأ</span>
                                    @else
                                        <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                                        <span>غير متصل Offline</span>
                                    @endif
                                </div>
                            </td>

                            <!-- طرود اليوم -->
                            <td class="py-4 px-4 text-center">
                                <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-lg text-xs font-extrabold {{ $office->today_parcels_count > 0 ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-slate-100 text-slate-500' }}">
                                    {{ $office->today_parcels_count }}
                                </span>
                            </td>

                            <!-- إجمالي الطرود -->
                            <td class="py-4 px-4 text-center font-bold text-slate-700">
                                {{ $office->parcels_count }}
                            </td>

                            <!-- حالة المكتب (نشط / محظور) -->
                            <td class="py-4 px-4 text-center">
                                <button type="button"
                                        @click="toggleOfficeStatus({{ $office->id }})"
                                        class="px-2.5 py-1 rounded-full text-xs font-bold transition-all border"
                                        id="status-btn-{{ $office->id }}"
                                        :class="{{ $office->is_active ? 'true' : 'false' }} ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100' : 'bg-rose-50 text-rose-700 border-rose-200 hover:bg-rose-100'">
                                    {{ $office->is_active ? 'نشط' : 'محظور' }}
                                </button>
                            </td>

                            <!-- إجراءات سريعة -->
                            <td class="py-4 px-6 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    
                                    <!-- فحص حي للواتساب -->
                                    <button type="button"
                                            @click="checkLiveStatus({{ $office->id }})"
                                            title="فحص مباشر لسيرفر الواتساب الآن"
                                            class="p-2 rounded-lg text-slate-500 hover:text-primary hover:bg-slate-100 transition-colors">
                                        <span class="material-symbols-outlined text-lg" :id="'refresh-icon-' + {{ $office->id }}">sync</span>
                                    </button>

                                    <!-- استعراض وتعديل المكتب والتبويبات -->
                                    <a href="{{ route('admin.offices.edit', $office) }}"
                                       title="إدارة وتعديل المكتب"
                                       class="p-2 rounded-lg text-slate-500 hover:text-secondary hover:bg-amber-50 transition-colors">
                                        <span class="material-symbols-outlined text-lg">settings</span>
                                    </a>

                                    <!-- الانتقال لطرود هذا المكتب -->
                                    <a href="{{ route('admin.parcels.index', ['office_id' => $office->id]) }}"
                                       title="استعراض طرود هذا المكتب"
                                       class="p-2 rounded-lg text-slate-500 hover:text-sky-600 hover:bg-sky-50 transition-colors">
                                        <span class="material-symbols-outlined text-lg">local_shipping</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-500 font-bold">
                                لا توجد مكاتب مسجلة في النظام حتى الآن.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function dashboardLiveMonitor() {
        return {
            stats: {
                online_count: {{ $stats['whatsapp_online_count'] }},
                offline_count: {{ $stats['whatsapp_offline_count'] }}
            },
            officeStatuses: {
                @foreach($offices as $office)
                    {{ $office->id }}: '{{ $office->whatsapp_live_status }}',
                @endforeach
            },
            officesToCheck: @json($offices->filter(fn($o) => $o->hasWhatsAppConfigured() && ($o->needs_status_check || $o->whatsapp_live_status !== 'online'))->pluck('id')->values()),

            init() {
                // الفحص التلقائي بالخلفية لجميع المكاتب المهيأة وغير المتصلة
                this.autoCheckPending();
            },

            async autoCheckPending() {
                if (!this.officesToCheck || !this.officesToCheck.length) return;

                for (const officeId of this.officesToCheck) {
                    this.checkLiveStatus(officeId, true);
                }
            },

            async toggleOfficeStatus(officeId) {
                try {
                    const res = await fetch(`/admin/offices/${officeId}/toggle-status`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        }
                    });
                    const data = await res.json();
                    if (data.success) {
                        const btn = document.getElementById(`status-btn-${officeId}`);
                        if (data.is_active) {
                            btn.className = 'px-2.5 py-1 rounded-full text-xs font-bold transition-all border bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100';
                            btn.textContent = 'نشط';
                        } else {
                            btn.className = 'px-2.5 py-1 rounded-full text-xs font-bold transition-all border bg-rose-50 text-rose-700 border-rose-200 hover:bg-rose-100';
                            btn.textContent = 'محظور';
                        }
                    }
                } catch (e) {
                    alert('تعذر تحديث حالة المكتب.');
                }
            },

            async checkLiveStatus(officeId, isAuto = false) {
                const icon = document.getElementById(`refresh-icon-${officeId}`);
                const badge = document.getElementById(`wa-badge-${officeId}`);

                if (icon) icon.classList.add('animate-spin');

                if (badge && isAuto) {
                    badge.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-sky-50 text-sky-700 border border-sky-200';
                    badge.innerHTML = '<span class="w-2 h-2 rounded-full bg-sky-500 animate-ping"></span><span>جاري التحقق...</span>';
                }

                try {
                    const res = await fetch(`/admin/offices/${officeId}/whatsapp/status?refresh=1`, {
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await res.json();

                    if (badge) {
                        if (data.connected) {
                            badge.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200';
                            badge.innerHTML = '<span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span><span>متصل Online</span>';

                            // تحديث عدادات الكروت العلوية فورياً
                            if (this.officeStatuses[officeId] !== 'online') {
                                this.officeStatuses[officeId] = 'online';
                                this.stats.online_count++;
                                if (this.stats.offline_count > 0) {
                                    this.stats.offline_count--;
                                }
                            }
                        } else if (data.state === 'not_configured') {
                            badge.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200';
                            badge.innerHTML = '<span class="w-2 h-2 rounded-full bg-amber-500"></span><span>غير مهيأ</span>';
                        } else {
                            badge.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200';
                            badge.innerHTML = '<span class="w-2 h-2 rounded-full bg-rose-500"></span><span>غير متصل Offline</span>';

                            if (this.officeStatuses[officeId] === 'online') {
                                this.officeStatuses[officeId] = 'offline';
                                if (this.stats.online_count > 0) this.stats.online_count--;
                                this.stats.offline_count++;
                            }
                        }
                    }
                } catch (e) {
                    console.error('WhatsApp status check error for office ' + officeId, e);
                    if (badge && isAuto) {
                        badge.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200';
                        badge.innerHTML = '<span class="w-2 h-2 rounded-full bg-rose-500"></span><span>غير متصل Offline</span>';
                    }
                } finally {
                    if (icon) icon.classList.remove('animate-spin');
                }
            }
        };
    }
</script>
@endpush
