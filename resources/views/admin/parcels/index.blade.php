@extends('admin.layouts.app')

@section('title', 'مراقبة الطرود الشاملة')
@section('page-title', 'مراقبة وتتبع الطرود')
@section('page-subtitle', 'استعراض وتحليل كافة شحنات وطرود شبكة المكاتب مع تحكم مباشر بالحالات')

@section('content')
<div class="space-y-6" x-data="parcelsMonitor()">

    <!-- 1. شريط إحصائيات سريعة للنتائج المفلترة -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
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
    </div>

    <!-- 2. بطاقة الفلاتر المتقدمة -->
    <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-xs">
        <form method="GET" action="{{ route('admin.parcels.index') }}" class="space-y-4">
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                
                <!-- البحث بالنص -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">البحث (اسم، هاتف، أو رقم سند)</label>
                    <div class="relative">
                        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                               placeholder="اكتب للبحث..."
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 pr-9 text-xs font-semibold text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                        <span class="material-symbols-outlined text-slate-400 absolute right-2.5 top-1/2 -translate-y-1/2 text-lg pointer-events-none">search</span>
                    </div>
                </div>

                <!-- فلترة المكتب -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">المكتب المتواجد به الطرد</label>
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
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">حالة الشحنة</label>
                    <select name="status" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-bold text-slate-700 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                        <option value="">كافة الحالات</option>
                        @foreach ($statuses as $st)
                            <option value="{{ $st->value }}" {{ ($filters['status'] ?? '') === $st->value ? 'selected' : '' }}>
                                {{ $st->label() }}
                            </option>
                        @endforeach
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

            <!-- صف التواريخ المحددة والزرار -->
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
                    <a href="{{ route('admin.parcels.index') }}" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs font-bold transition-colors">
                        إعادة تعيين
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- 3. جدول مراقبة الطرود الشامل -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead>
                    <tr class="bg-slate-100/70 text-slate-600 font-extrabold uppercase border-b border-slate-200">
                        <th class="py-4 px-5">رقم السند</th>
                        <th class="py-4 px-4">المكتب الحالي</th>
                        <th class="py-4 px-4">المستلم ورقم الهاتف</th>
                        <th class="py-4 px-4">نوع الطرد والشحنة</th>
                        <th class="py-4 px-4">المكتب المصدر</th>
                        <th class="py-4 px-4 text-center">الحالة الحالية</th>
                        <th class="py-4 px-4">تاريخ الوصول والتسليم</th>
                        <th class="py-4 px-5 text-center">التحكم</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                    @forelse ($parcels as $parcel)
                        <tr class="hover:bg-slate-50/80 transition-colors" id="parcel-row-{{ $parcel->id }}">
                            
                            <!-- رقم السند -->
                            <td class="py-4 px-5 font-mono font-black text-slate-900 text-sm">
                                {{ $parcel->receipt_number ?: '-' }}
                            </td>

                            <!-- المكتب -->
                            <td class="py-4 px-4">
                                <span class="font-extrabold text-slate-900 block">{{ $parcel->office->name ?? 'مكتب غير معروف' }}</span>
                                <span class="text-[11px] text-slate-400">{{ $parcel->office->branch_name ?? '' }}</span>
                            </td>

                            <!-- المستلم والهاتف مع رابط واتساب -->
                            <td class="py-4 px-4">
                                <div class="font-bold text-slate-900">{{ $parcel->recipient_name ?: 'بدون اسم' }}</div>
                                <div class="flex items-center gap-1.5 mt-0.5" dir="ltr">
                                    <span class="font-mono text-slate-600">{{ $parcel->recipient_phone }}</span>
                                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $parcel->recipient_phone) }}"
                                       target="_blank" title="محادثة واتساب مباشرة"
                                       class="text-emerald-600 hover:text-emerald-700 p-0.5">
                                        <span class="material-symbols-outlined text-[16px]">chat</span>
                                    </a>
                                </div>
                            </td>

                            <!-- نوع الطرد -->
                            <td class="py-4 px-4 text-slate-800">
                                {{ $parcel->package_type }}
                            </td>

                            <!-- المكتب المصدر -->
                            <td class="py-4 px-4 text-slate-500">
                                {{ $parcel->source_office_name ?: '-' }}
                            </td>

                            <!-- شارة الحالة الحالية -->
                            <td class="py-4 px-4 text-center">
                                <span id="status-badge-{{ $parcel->id }}"
                                      class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-extrabold {{ $parcel->status->value === 'delivered' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : ($parcel->status->value === 'returned' ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-blue-50 text-blue-700 border border-blue-200') }}">
                                    @if ($parcel->status->value === 'delivered')
                                        <span class="material-symbols-outlined text-sm">check_circle</span>
                                    @elseif ($parcel->status->value === 'returned')
                                        <span class="material-symbols-outlined text-sm">cancel</span>
                                    @else
                                        <span class="material-symbols-outlined text-sm">schedule</span>
                                    @endif
                                    <span>{{ $parcel->status->label() }}</span>
                                </span>
                            </td>

                            <!-- التواريخ -->
                            <td class="py-4 px-4 text-[11px] font-mono text-slate-500">
                                <div><span class="text-slate-400">وصول:</span> {{ $parcel->created_at->format('Y-m-d H:i') }}</div>
                                @if($parcel->delivered_at)
                                    <div class="text-emerald-600 font-bold"><span class="text-emerald-500">تسليم:</span> {{ $parcel->delivered_at->format('Y-m-d H:i') }}</div>
                                @endif
                            </td>

                            <!-- أزرار التحكم: تعديل الحالة وحذف -->
                            <td class="py-4 px-5 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    
                                    <!-- تغيير الحالة عبر Modal -->
                                    <button type="button"
                                            @click="openStatusModal('{{ $parcel->id }}', '{{ $parcel->receipt_number }}', '{{ $parcel->status->value }}')"
                                            title="تعديل حالة الطرد"
                                            class="p-2 rounded-xl text-slate-500 hover:text-primary hover:bg-slate-100 transition-colors">
                                        <span class="material-symbols-outlined text-lg">edit_note</span>
                                    </button>

                                    <!-- حذف الطرد -->
                                    <button type="button"
                                            @click="deleteParcel('{{ $parcel->id }}', '{{ $parcel->receipt_number }}')"
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

    <!-- نافذة تعديل حالة الطرد (Modal) -->
    <div x-show="statusModal.show"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        
        <div @click.outside="statusModal.show = false"
             class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-slate-200">
            
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-secondary text-2xl">published_with_changes</span>
                    <h3 class="text-sm font-extrabold text-slate-900">تعديل حالة الطرد</h3>
                </div>
                <button type="button" @click="statusModal.show = false" class="text-slate-400 hover:text-slate-600">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>

            <p class="text-xs text-slate-500 mb-4">
                تعديل حالة السند: <span class="font-mono font-bold text-slate-800" x-text="statusModal.receiptNumber"></span>
            </p>

            <div class="space-y-2 mb-6">
                <label class="block text-xs font-bold text-slate-700">اختر الحالة الجديدة:</label>
                <select x-model="statusModal.newStatus" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs font-bold text-slate-800 focus:bg-white focus:outline-none focus:border-secondary">
                    @foreach ($statuses as $st)
                        <option value="{{ $st->value }}">{{ $st->label() }}</option>
                    @endforeach
                </select>
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
                parcelId: null,
                receiptNumber: '',
                newStatus: '',
                loading: false
            },

            openStatusModal(id, receipt, currentStatus) {
                this.statusModal.parcelId = id;
                this.statusModal.receiptNumber = receipt || id;
                this.statusModal.newStatus = currentStatus;
                this.statusModal.loading = false;
                this.statusModal.show = true;
            },

            async submitStatusChange() {
                this.statusModal.loading = true;
                try {
                    const res = await fetch(`/admin/parcels/${this.statusModal.parcelId}/status`, {
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
                        const badge = document.getElementById(`status-badge-${this.statusModal.parcelId}`);
                        if (badge) {
                            let badgeClass = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-extrabold ';
                            let icon = 'schedule';
                            if (data.status === 'delivered') {
                                badgeClass += 'bg-emerald-50 text-emerald-700 border border-emerald-200';
                                icon = 'check_circle';
                            } else if (data.status === 'returned') {
                                badgeClass += 'bg-rose-50 text-rose-700 border border-rose-200';
                                icon = 'cancel';
                            } else {
                                badgeClass += 'bg-blue-50 text-blue-700 border border-blue-200';
                            }
                            badge.className = badgeClass;
                            badge.innerHTML = `<span class="material-symbols-outlined text-sm">${icon}</span><span>${data.label}</span>`;
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

            async deleteParcel(id, receipt) {
                if (!confirm(`هل أنت متأكد من رغبتك بحذف الطرد (${receipt || id}) نهائياً؟`)) return;

                try {
                    const res = await fetch(`/admin/parcels/${id}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        }
                    });
                    const data = await res.json();
                    if (data.success) {
                        const row = document.getElementById(`parcel-row-${id}`);
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
