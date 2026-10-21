@extends('admin.layouts.app')

@section('title', 'إدارة سيرفرات الواتساب (Instances)')
@section('page-title', 'سيرفرات وحسابات الواتساب (Instances)')
@section('page-subtitle', 'إدارة المعرفات والـ instances المتاحة على الخادم وربطها بالمكاتب والفروع')

@section('header-actions')
    <a href="{{ route('admin.whatsapp_instances.create') }}"
       class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-secondary text-primary-950 text-xs font-extrabold hover:brightness-105 transition-all shadow-sm">
        <span class="material-symbols-outlined text-lg">add_circle</span>
        <span>إضافة Instance جديدة</span>
    </a>
@endsection

@section('content')
<div class="space-y-6" x-data="instancesManager()">

    <!-- شريط البحث والفلترة -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-xs">
        <form method="GET" action="{{ route('admin.whatsapp_instances.index') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            
            <div class="md:col-span-2 relative">
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                       placeholder="ابحث بالاسم، معرف الـ Instance (ID)، أو رقم الهاتف..."
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 pr-10 text-xs font-semibold text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                <span class="material-symbols-outlined text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 text-xl pointer-events-none">search</span>
            </div>

            <div class="flex items-center gap-2">
                <select name="status" class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-bold text-slate-700 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                    <option value="">كافة الحالات</option>
                    <option value="active" {{ ($filters['status'] ?? '') === 'active' ? 'selected' : '' }}>النشطة فقط</option>
                    <option value="inactive" {{ ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' }}>المعطلة</option>
                </select>
                <button type="submit" class="py-2.5 px-4 rounded-xl bg-primary text-white text-xs font-bold hover:bg-primary-800 transition-colors">
                    تصفية
                </button>
            </div>
        </form>
    </div>

    <!-- جدول الـ Instances -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead>
                    <tr class="bg-slate-100/70 text-slate-600 text-xs font-extrabold uppercase border-b border-slate-200">
                        <th class="py-4 px-6">اسم الـ Instance</th>
                        <th class="py-4 px-4">معرف السيرفر (ID)</th>
                        <th class="py-4 px-4">رقم الهاتف المسجل</th>
                        <th class="py-4 px-4">الرمز السري (PIN)</th>
                        <th class="py-4 px-4">المكتب المرتبط به</th>
                        <th class="py-4 px-4 text-center">الحالة</th>
                        <th class="py-4 px-6 text-center">التحكم</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($instances as $inst)
                        <tr class="hover:bg-slate-50/80 transition-colors" id="inst-row-{{ $inst->id }}">
                            
                            <!-- الاسم -->
                            <td class="py-4 px-6 font-extrabold text-slate-900">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-black text-sm shrink-0">
                                        <span class="material-symbols-outlined text-xl">hub</span>
                                    </div>
                                    <div>
                                        <a href="{{ route('admin.whatsapp_instances.edit', $inst) }}" class="hover:text-secondary transition-colors">
                                            {{ $inst->name }}
                                        </a>
                                        <span class="text-[11px] text-slate-400 block font-normal">{{ $inst->created_at->format('Y-m-d') }}</span>
                                    </div>
                                </div>
                            </td>

                            <!-- المعرف -->
                            <td class="py-4 px-4 font-mono font-bold text-xs text-slate-700" dir="ltr">
                                {{ $inst->instance_id }}
                            </td>

                            <!-- رقم الهاتف -->
                            <td class="py-4 px-4 font-mono text-xs text-slate-600" dir="ltr">
                                {{ $inst->phone_number ?: 'غير محدد' }}
                            </td>

                            <!-- الرمز السري PIN -->
                            <td class="py-4 px-4 font-mono text-xs font-bold text-slate-500">
                                {{ $inst->pin_code }}
                            </td>

                            <!-- المكتب المرتبط -->
                            <td class="py-4 px-4 text-xs font-bold">
                                @if ($inst->linked_offices && $inst->linked_offices->count() > 0)
                                    @foreach ($inst->linked_offices as $linkOffice)
                                        <a href="{{ route('admin.offices.edit', $linkOffice) }}" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-sky-50 text-sky-700 hover:bg-sky-100 transition-colors">
                                            <span class="material-symbols-outlined text-sm">domain</span>
                                            <span>{{ $linkOffice->name }}</span>
                                        </a>
                                    @endforeach
                                @else
                                    <button type="button" @click="openAssignModal({{ $inst->id }}, '{{ addslashes($inst->name) }}')"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100 text-xs transition-colors">
                                        <span class="material-symbols-outlined text-sm">link</span>
                                        <span>ربط بمكتب الآن</span>
                                    </button>
                                @endif
                            </td>

                            <!-- الحالة -->
                            <td class="py-4 px-4 text-center">
                                <button type="button"
                                        @click="toggleStatus({{ $inst->id }})"
                                        id="inst-status-{{ $inst->id }}"
                                        class="px-2.5 py-1 rounded-full text-xs font-bold border transition-all cursor-pointer {{ $inst->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100' : 'bg-rose-50 text-rose-700 border-rose-200 hover:bg-rose-100' }}">
                                    {{ $inst->is_active ? 'مفعل' : 'معطل' }}
                                </button>
                            </td>

                            <!-- التحكم -->
                            <td class="py-4 px-6 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button type="button"
                                            @click="openAssignModal({{ $inst->id }}, '{{ addslashes($inst->name) }}')"
                                            title="ربط بمكتب"
                                            class="p-2 rounded-xl text-slate-500 hover:text-sky-600 hover:bg-sky-50 transition-colors">
                                        <span class="material-symbols-outlined text-lg">link</span>
                                    </button>

                                    <a href="{{ route('admin.whatsapp_instances.edit', $inst) }}"
                                       title="تعديل الـ Instance"
                                       class="p-2 rounded-xl text-slate-500 hover:text-primary hover:bg-slate-100 transition-colors">
                                        <span class="material-symbols-outlined text-lg">edit</span>
                                    </a>

                                    <form method="POST" action="{{ route('admin.whatsapp_instances.destroy', $inst) }}" onsubmit="return confirm('هل أنت متأكد من رغبتك بحذف هذه الـ Instance نهائياً؟');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                title="حذف الـ Instance"
                                                class="p-2 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors">
                                            <span class="material-symbols-outlined text-lg">delete</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400 font-bold">
                                لا توجد سيرفرات WhatsApp Instances معرفة حتى الآن.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($instances->hasPages())
            <div class="p-4 border-t border-slate-100 bg-slate-50/50">
                {{ $instances->links() }}
            </div>
        @endif
    </div>

    <!-- نافذة ربط الـ Instance بمكتب (Assign Modal) -->
    <div x-show="assignModal.show"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        
        <div @click.outside="assignModal.show = false"
             class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl border border-slate-200">
            
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-secondary text-2xl">link</span>
                    <h3 class="text-sm font-extrabold text-slate-900">ربط الـ Instance بمكتب</h3>
                </div>
                <button type="button" @click="assignModal.show = false" class="text-slate-400 hover:text-slate-600">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>

            <p class="text-xs text-slate-500 mb-4">
                سيتم ضبط معرف الـ Instance واسمها تلقائياً لدى المكتب المختار: <span class="font-bold text-slate-800" x-text="assignModal.instName"></span>
            </p>

            <div class="space-y-4 mb-6">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">اختر المكتب المراد ربطه:</label>
                    <select x-model="assignModal.officeId" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs font-bold text-slate-800 focus:bg-white focus:outline-none focus:border-secondary">
                        <option value="">-- اختر المكتب --</option>
                        @foreach ($offices as $off)
                            <option value="{{ $off->id }}">{{ $off->name }} {{ $off->branch_name ? "({$off->branch_name})" : '' }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2">
                <button type="button" @click="assignModal.show = false" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-600">
                    إلغاء
                </button>
                <button type="button" @click="submitAssign()" :disabled="assignModal.loading"
                        class="px-5 py-2 rounded-xl bg-secondary text-primary-950 text-xs font-extrabold hover:brightness-105 disabled:opacity-50">
                    <span x-show="!assignModal.loading">تأكيد الربط</span>
                    <span x-show="assignModal.loading" x-cloak>جاري الربط...</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function instancesManager() {
        return {
            assignModal: {
                show: false,
                instId: null,
                instName: '',
                officeId: '',
                loading: false
            },

            openAssignModal(id, name) {
                this.assignModal.instId = id;
                this.assignModal.instName = name;
                this.assignModal.officeId = '';
                this.assignModal.loading = false;
                this.assignModal.show = true;
            },

            async submitAssign() {
                if (!this.assignModal.officeId) {
                    alert('يرجى اختيار المكتب أولاً.');
                    return;
                }

                this.assignModal.loading = true;
                try {
                    const res = await fetch(`/admin/whatsapp-instances/${this.assignModal.instId}/assign-office`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ office_id: this.assignModal.officeId })
                    });
                    const data = await res.json();
                    if (data.success) {
                        alert(data.message);
                        window.location.reload();
                    } else {
                        alert(data.message || 'فشل في ربط الـ Instance.');
                    }
                } catch (e) {
                    alert('حدث خطأ أثناء الربط.');
                } finally {
                    this.assignModal.loading = false;
                }
            },

            async toggleStatus(id) {
                try {
                    const res = await fetch(`/admin/whatsapp-instances/${id}/toggle-status`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        }
                    });
                    const data = await res.json();
                    if (data.success) {
                        const tag = document.getElementById(`inst-status-${id}`);
                        if (data.is_active) {
                            tag.className = 'px-2.5 py-1 rounded-full text-xs font-bold border transition-all cursor-pointer bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100';
                            tag.textContent = 'مفعل';
                        } else {
                            tag.className = 'px-2.5 py-1 rounded-full text-xs font-bold border transition-all cursor-pointer bg-rose-50 text-rose-700 border-rose-200 hover:bg-rose-100';
                            tag.textContent = 'معطل';
                        }
                    }
                } catch (e) {
                    alert('تعذر تغيير الحالة.');
                }
            }
        };
    }
</script>
@endpush
