@extends('admin.layouts.app')

@section('title', 'إدارة المكاتب والفروع')
@section('page-title', 'دليل وإدارة المكاتب')
@section('page-subtitle', 'التحكم الشامل ببيانات المكاتب، وحسابات الدخول، وقنوات الـ SMS والواتساب')

@section('header-actions')
    <a href="{{ route('admin.offices.create') }}"
       class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-secondary text-primary-950 text-xs font-extrabold hover:brightness-105 transition-all shadow-sm">
        <span class="material-symbols-outlined text-lg">add_business</span>
        <span>إضافة مكتب جديد</span>
    </a>
@endsection

@section('content')
<div class="space-y-6" x-data="officesManagement()">

    <!-- بطاقات المؤشرات العلوية لحالة المكاتب والواتساب -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-xs flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-1">إجمالي المكاتب</span>
                <span class="text-2xl font-black text-slate-900" x-text="stats.total">{{ $whatsappStats['total'] ?? $offices->total() }}</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center">
                <span class="material-symbols-outlined text-xl">domain</span>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-xs flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-1">واتساب متصل Online</span>
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span class="text-2xl font-black text-emerald-600" x-text="stats.online">{{ $whatsappStats['online'] ?? 0 }}</span>
                </div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                <span class="material-symbols-outlined text-xl">chat</span>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-xs flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-1">واتساب غير متصل Offline</span>
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                    <span class="text-2xl font-black text-slate-700" x-text="stats.offline">{{ $whatsappStats['offline'] ?? 0 }}</span>
                </div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-500 flex items-center justify-center">
                <span class="material-symbols-outlined text-xl">chat_error</span>
            </div>
        </div>
    </div>

    <!-- شريط البحث والفلترة المتقدمة -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-xs">
        <form method="GET" action="{{ route('admin.offices.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            
            <!-- البحث بالنص -->
            <div class="md:col-span-2 relative">
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                       placeholder="ابحث باسم المكتب، الفرع، أو رقم الهاتف..."
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 pr-10 text-xs font-semibold text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                <span class="material-symbols-outlined text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 text-xl pointer-events-none">search</span>
            </div>

            <!-- فلتر حالة الحساب -->
            <div>
                <select name="status" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-bold text-slate-700 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                    <option value="">جميع الحالات (النشط والمحظور)</option>
                    <option value="active" {{ ($filters['status'] ?? '') === 'active' ? 'selected' : '' }}>المكاتب النشطة فقط</option>
                    <option value="inactive" {{ ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' }}>المكاتب المحظورة / المعطلة</option>
                </select>
            </div>

            <!-- زر التطبيق والإعادة -->
            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 py-2.5 px-4 rounded-xl bg-primary text-white text-xs font-bold hover:bg-primary-800 transition-colors flex items-center justify-center gap-1.5">
                    <span class="material-symbols-outlined text-base">filter_list</span>
                    <span>فلترة</span>
                </button>
                @if(!empty($filters['search']) || !empty($filters['status']) || !empty($filters['whatsapp_status']))
                    <a href="{{ route('admin.offices.index') }}" class="p-2.5 rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-100 transition-colors" title="إعادة تعيين">
                        <span class="material-symbols-outlined text-base">restart_alt</span>
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- جدول المكاتب الرئيسي -->
    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead>
                    <tr class="bg-slate-100/70 text-slate-600 text-xs font-extrabold uppercase border-b border-slate-200">
                        <th class="py-4 px-6">المكتب والفرع</th>
                        <th class="py-4 px-4">بيانات تسجيل الدخول والاتصال</th>
                        <th class="py-4 px-4 text-center">حالة جلسة الواتساب</th>
                        <th class="py-4 px-4 text-center">إجمالي الطرود</th>
                        <th class="py-4 px-4 text-center">طرود اليوم</th>
                        <th class="py-4 px-4 text-center">الحالة</th>
                        <th class="py-4 px-6 text-center">إجراءات التحكم</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($offices as $office)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            
                            <!-- اسم المكتب والفرع -->
                            <td class="py-4 px-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-primary to-slate-800 text-white flex items-center justify-center font-black text-base shadow-sm shrink-0">
                                        {{ mb_substr($office->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <a href="{{ route('admin.offices.edit', $office) }}" class="font-extrabold text-slate-900 hover:text-secondary transition-colors text-base block">
                                            {{ $office->name }}
                                        </a>
                                        <p class="text-xs text-slate-500 mt-0.5">{{ $office->branch_name ?: 'الفرع الرئيسي' }}</p>
                                    </div>
                                </div>
                            </td>

                            <!-- هواتف المكتب -->
                            <td class="py-4 px-4 font-mono text-xs">
                                <div class="text-slate-900 font-bold flex items-center gap-1.5" dir="ltr">
                                    <span class="material-symbols-outlined text-[16px] text-emerald-600">phone</span>
                                    <span>{{ $office->whatsapp_sender_phone }}</span>
                                </div>
                                <div class="text-slate-500 mt-1 flex items-center gap-1.5" dir="ltr">
                                    <span class="material-symbols-outlined text-[16px] text-sky-600">sms</span>
                                    <span>{{ $office->httpsms_from_phone }}</span>
                                </div>
                            </td>

                            <!-- حالة كاش الواتساب -->
                            <td class="py-4 px-4 text-center">
                                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold"
                                     id="wa-pill-{{ $office->id }}">
                                    @if ($office->whatsapp_live_status === 'online')
                                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                        <span class="text-emerald-700">متصل بنجاح</span>
                                    @elseif ($office->whatsapp_live_status === 'not_configured')
                                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                        <span class="text-amber-700">غير مهيأ</span>
                                    @else
                                        <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                                        <span class="text-rose-700">غير متصل</span>
                                    @endif
                                </div>
                            </td>

                            <!-- إجمالي الطرود -->
                            <td class="py-4 px-4 text-center">
                                <a href="{{ route('admin.parcels.index', ['office_id' => $office->id]) }}" class="font-extrabold text-slate-900 hover:text-secondary underline decoration-dotted transition-colors">
                                    {{ $office->parcels_count }}
                                </a>
                            </td>

                            <!-- طرود اليوم -->
                            <td class="py-4 px-4 text-center">
                                <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-lg text-xs font-extrabold {{ $office->today_parcels_count > 0 ? 'bg-amber-50 text-secondary border border-amber-200' : 'bg-slate-100 text-slate-400' }}">
                                    {{ $office->today_parcels_count }}
                                </span>
                            </td>

                            <!-- زر تفعيل / حظر فوري -->
                            <td class="py-4 px-4 text-center">
                                <button type="button"
                                        @click="toggleStatus({{ $office->id }})"
                                        id="status-tag-{{ $office->id }}"
                                        class="px-3 py-1 rounded-full text-xs font-bold border transition-all cursor-pointer {{ $office->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100' : 'bg-rose-50 text-rose-700 border-rose-200 hover:bg-rose-100' }}">
                                    {{ $office->is_active ? 'نشط' : 'محظور' }}
                                </button>
                            </td>

                            <!-- أدوات الإدارة السريعة -->
                            <td class="py-4 px-6 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    
                                    <!-- تعديل كافة الإعدادات والتبويبات -->
                                    <a href="{{ route('admin.offices.edit', $office) }}"
                                       title="إعدادات وتعديل المكتب"
                                       class="p-2 rounded-xl text-slate-600 hover:text-primary hover:bg-slate-100 transition-colors">
                                        <span class="material-symbols-outlined text-xl">tune</span>
                                    </a>

                                    <!-- تغيير كلمة المرور فورياً عبر Modal -->
                                    <button type="button"
                                            @click="openPasswordModal({{ $office->id }}, '{{ addslashes($office->name) }}')"
                                            title="تغيير كلمة مرور المكتب"
                                            class="p-2 rounded-xl text-slate-600 hover:text-amber-600 hover:bg-amber-50 transition-colors">
                                        <span class="material-symbols-outlined text-xl">key</span>
                                    </button>

                                    <!-- حذف المكتب -->
                                    <form method="POST" action="{{ route('admin.offices.destroy', $office) }}" onsubmit="return confirm('هل أنت متأكد من رغبتك بحذف هذا المكتب نهائياً وجميع الطرود التابعة له؟');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                title="حذف المكتب"
                                                class="p-2 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors">
                                            <span class="material-symbols-outlined text-xl">delete</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-500 font-bold">
                                لم يتم العثور على أي مكاتب مطابقة لمعايير البحث.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($offices->hasPages())
            <div class="p-4 border-t border-slate-100 bg-slate-50/50">
                {{ $offices->links() }}
            </div>
        @endif
    </div>

    <!-- نافذة تعديل كلمة المرور الفورية (Password Modal) -->
    <div x-show="passwordModal.show"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        
        <div @click.outside="passwordModal.show = false"
             class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl border border-slate-200">
            
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-5">
                <div class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-secondary flex items-center justify-center">
                        <span class="material-symbols-outlined text-2xl">lock_reset</span>
                    </div>
                    <div>
                        <h3 class="text-base font-extrabold text-slate-900">تغيير كلمة مرور المكتب</h3>
                        <p class="text-xs text-slate-500" x-text="passwordModal.officeName"></p>
                    </div>
                </div>
                <button type="button" @click="passwordModal.show = false" class="text-slate-400 hover:text-slate-600">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>

            <form @submit.prevent="submitPasswordUpdate()">
                <div class="space-y-4 mb-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">كلمة المرور الجديدة</label>
                        <input type="password" x-model="passwordModal.newPassword" required minlength="6"
                               placeholder="أدخل 6 خانات على الأقل..."
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm focus:bg-white focus:outline-none focus:border-secondary">
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <button type="button" @click="passwordModal.show = false"
                            class="px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50">
                        إلغاء
                    </button>
                    <button type="submit" :disabled="passwordModal.loading"
                            class="px-6 py-2.5 rounded-xl bg-secondary text-primary-950 text-xs font-extrabold hover:brightness-105 disabled:opacity-50">
                        <span x-show="!passwordModal.loading">حفظ كلمة المرور</span>
                        <span x-show="passwordModal.loading" x-cloak>جاري الحفظ...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function officesManagement() {
        return {
            stats: {
                online: {{ $whatsappStats['online'] ?? 0 }},
                offline: {{ $whatsappStats['offline'] ?? 0 }},
                total: {{ $whatsappStats['total'] ?? $offices->total() }}
            },
            officeStatuses: {
                @foreach($offices as $office)
                    {{ $office->id }}: '{{ $office->whatsapp_live_status }}',
                @endforeach
            },
            officesToCheck: @json($offices->getCollection()->filter(fn($o) => $o->hasWhatsAppConfigured() && ($o->needs_status_check || $o->whatsapp_live_status !== 'online'))->pluck('id')->values()),

            init() {
                // فحص تلقائي في الخلفية عند فتح الصفحة لجميع المكاتب المهيأة وغير المتصلة
                this.autoCheckPending();
            },

            async autoCheckPending() {
                if (!this.officesToCheck || !this.officesToCheck.length) return;

                for (const officeId of this.officesToCheck) {
                    this.checkOfficeLiveStatus(officeId, true);
                }
            },

            async checkOfficeLiveStatus(officeId, isAuto = false) {
                const pill = document.getElementById(`wa-pill-${officeId}`);

                if (pill && isAuto) {
                    pill.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-sky-50 text-sky-700 border border-sky-200';
                    pill.innerHTML = '<span class="w-2 h-2 rounded-full bg-sky-500 animate-ping"></span><span class="text-sky-700">جاري التحقق...</span>';
                }

                try {
                    const res = await fetch(`/admin/offices/${officeId}/whatsapp/status?refresh=1`, {
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await res.json();

                    if (pill) {
                        if (data.connected) {
                            pill.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200';
                            pill.innerHTML = '<span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span><span class="text-emerald-700">متصل بنجاح</span>';

                            if (this.officeStatuses[officeId] !== 'online') {
                                this.officeStatuses[officeId] = 'online';
                                this.stats.online++;
                                if (this.stats.offline > 0) this.stats.offline--;
                            }
                        } else if (data.state === 'not_configured') {
                            pill.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200';
                            pill.innerHTML = '<span class="w-2 h-2 rounded-full bg-amber-500"></span><span class="text-amber-700">غير مهيأ</span>';
                        } else {
                            pill.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200';
                            pill.innerHTML = '<span class="w-2 h-2 rounded-full bg-rose-500"></span><span class="text-rose-700">غير متصل</span>';

                            if (this.officeStatuses[officeId] === 'online') {
                                this.officeStatuses[officeId] = 'offline';
                                if (this.stats.online > 0) this.stats.online--;
                                this.stats.offline++;
                            }
                        }
                    }
                } catch (e) {
                    console.error('Failed checking WhatsApp status for office ' + officeId, e);
                    if (pill && isAuto) {
                        pill.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200';
                        pill.innerHTML = '<span class="w-2 h-2 rounded-full bg-rose-500"></span><span class="text-rose-700">غير متصل</span>';
                    }
                }
            },

            passwordModal: {
                show: false,
                officeId: null,
                officeName: '',
                newPassword: '',
                loading: false
            },

            openPasswordModal(id, name) {
                this.passwordModal.officeId = id;
                this.passwordModal.officeName = name;
                this.passwordModal.newPassword = '';
                this.passwordModal.loading = false;
                this.passwordModal.show = true;
            },

            async submitPasswordUpdate() {
                if (this.passwordModal.newPassword.length < 6) {
                    alert('كلمة المرور يجب أن تكون 6 أحرف على الأقل.');
                    return;
                }

                this.passwordModal.loading = true;
                try {
                    const res = await fetch(`/admin/offices/${this.passwordModal.officeId}/update-password`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ password: this.passwordModal.newPassword })
                    });
                    const data = await res.json();
                    if (data.success) {
                        alert(data.message);
                        this.passwordModal.show = false;
                    } else {
                        alert(data.message || 'حدث خطأ أثناء التحديث.');
                    }
                } catch (e) {
                    alert('تعذر الاتصال بالخادم لتحديث كلمة المرور.');
                } finally {
                    this.passwordModal.loading = false;
                }
            },

            async toggleStatus(officeId) {
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
                        const tag = document.getElementById(`status-tag-${officeId}`);
                        if (data.is_active) {
                            tag.className = 'px-3 py-1 rounded-full text-xs font-bold border transition-all cursor-pointer bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100';
                            tag.textContent = 'نشط';
                        } else {
                            tag.className = 'px-3 py-1 rounded-full text-xs font-bold border transition-all cursor-pointer bg-rose-50 text-rose-700 border-rose-200 hover:bg-rose-100';
                            tag.textContent = 'محظور';
                        }
                    }
                } catch (e) {
                    alert('تعذر تغيير حالة المكتب.');
                }
            }
        };
    }
</script>
@endpush
