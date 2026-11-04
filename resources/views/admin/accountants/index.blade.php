@extends('admin.layouts.app')

@section('title', 'إدارة المحاسبين')
@section('page-title', 'إدارة حسابات المحاسبين')
@section('page-subtitle', 'إضافة وتعديل حسابات المحاسبين وربطهم بالمكاتب وإدارة صلاحيات الدخول والرموز السرية')

@section('header-actions')
    <button type="button"
            @click="$dispatch('open-add-accountant')"
            onclick="window.dispatchEvent(new CustomEvent('open-add-accountant'))"
            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-secondary text-primary-950 text-xs font-extrabold hover:brightness-105 transition-all shadow-sm">
        <span class="material-symbols-outlined text-lg">person_add</span>
        <span>إضافة محاسب جديد</span>
    </button>
@endsection

@section('content')
<div class="space-y-6"
     x-data="accountantsManager()"
     @open-add-accountant.window="openAddModal()">

    <!-- 1. بطاقات الإحصائيات السريعة -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        
        <!-- إجمالي المحاسبين -->
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-slate-400 uppercase">إجمالي المحاسبين</p>
                <h4 class="text-2xl font-black text-slate-900 mt-1">{{ number_format($stats['total']) }}</h4>
                <p class="text-[10px] text-slate-400 mt-0.5">حساب مسجل بالنظام</p>
            </div>
            <div class="w-11 h-11 rounded-2xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-2xl">manage_accounts</span>
            </div>
        </div>

        <!-- المحاسبين النشطين -->
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-slate-400 uppercase">حسابات نشطة</p>
                <h4 class="text-2xl font-black text-emerald-600 mt-1">{{ number_format($stats['active']) }}</h4>
                <p class="text-[10px] text-slate-400 mt-0.5">يمكنهم تسجيل الدخول</p>
            </div>
            <div class="w-11 h-11 rounded-2xl bg-emerald-50 text-emerald-600 border border-emerald-200 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-2xl">verified_user</span>
            </div>
        </div>

        <!-- المحاسبين الموقوفين -->
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-slate-400 uppercase">حسابات موقوفة</p>
                <h4 class="text-2xl font-black text-rose-600 mt-1">{{ number_format($stats['inactive']) }}</h4>
                <p class="text-[10px] text-slate-400 mt-0.5">موقوفة عن العمل مؤقتاً</p>
            </div>
            <div class="w-11 h-11 rounded-2xl bg-rose-50 text-rose-600 border border-rose-200 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-2xl">person_off</span>
            </div>
        </div>

        <!-- المكاتب التي لديها محاسبون -->
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-slate-400 uppercase">مكاتب لديها محاسبون</p>
                <h4 class="text-2xl font-black text-blue-600 mt-1">{{ number_format($stats['offices_with_accountant']) }}</h4>
                <p class="text-[10px] text-slate-400 mt-0.5">من إجمالي {{ $offices->count() }} مكتب</p>
            </div>
            <div class="w-11 h-11 rounded-2xl bg-blue-50 text-blue-600 border border-blue-200 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-2xl">domain</span>
            </div>
        </div>
    </div>

    <!-- 2. بطاقة البحث والفلترة -->
    <div class="bg-white p-5 sm:p-6 rounded-3xl border border-slate-200/90 shadow-xs">
        <form method="GET" action="{{ route('admin.accountants.index') }}" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-4 items-end">
                
                <!-- فلترة حسب المكتب -->
                <div class="lg:col-span-5">
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        تصفية حسب المكتب:
                    </label>
                    <select name="office_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-bold text-slate-700 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                        <option value="">جميع المكاتب والفروع ({{ $stats['total'] }} محاسب)</option>
                        @foreach ($offices as $off)
                            <option value="{{ $off->id }}" {{ ($filters['office_id'] ?? '') == $off->id ? 'selected' : '' }}>
                                {{ $off->name }} {{ $off->branch_name ? "({$off->branch_name})" : '' }} — ({{ $off->accountants_count }} محاسب)
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- البحث بالاسم أو رقم الجوال -->
                <div class="lg:col-span-5">
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        البحث بالاسم أو رقم الجوال:
                    </label>
                    <div class="relative">
                        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                               placeholder="ابحث باسم المحاسب أو رقم جواله (7XXXXXXXX)..."
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 pr-9 text-xs font-semibold text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                        <span class="material-symbols-outlined text-slate-400 absolute right-2.5 top-1/2 -translate-y-1/2 text-lg pointer-events-none">search</span>
                    </div>
                </div>

                <!-- أزرار الفلترة -->
                <div class="lg:col-span-2 flex items-center gap-2">
                    <button type="submit" class="flex-1 py-2.5 px-4 rounded-xl bg-primary text-white text-xs font-bold hover:bg-primary-800 transition-colors flex items-center justify-center gap-1.5 shadow-sm">
                        <span class="material-symbols-outlined text-base">filter_alt</span>
                        <span>فلترة</span>
                    </button>
                    @if(!empty($filters['office_id']) || !empty($filters['search']))
                        <a href="{{ route('admin.accountants.index') }}"
                           class="p-2.5 rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-100 transition-colors shrink-0"
                           title="إلغاء الفلترة">
                            <span class="material-symbols-outlined text-base">restart_alt</span>
                        </a>
                    @endif
                </div>

            </div>
        </form>
    </div>

    <!-- 3. جدول عرض المحاسبين -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs overflow-hidden">
        
        <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 bg-slate-50/50">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-primary text-2xl">badge</span>
                <div>
                    <h3 class="text-sm font-black text-slate-900">قائمة حسابات المحاسبين</h3>
                    <p class="text-xs text-slate-500">إدارة بيانات الدخول وحالة التفعيل لكل محاسب ومكتبه</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <span class="px-3 py-1 rounded-full text-xs font-extrabold bg-slate-100 text-slate-700 border border-slate-200">
                    إجمالي النتائج: {{ $accountants->total() }}
                </span>
                <button type="button"
                        @click="openAddModal()"
                        class="px-3.5 py-1.5 rounded-xl bg-secondary text-primary-950 text-xs font-extrabold hover:brightness-105 transition-all flex items-center gap-1 shadow-2xs">
                    <span class="material-symbols-outlined text-sm">add</span>
                    <span>إضافة محاسب</span>
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead>
                    <tr class="bg-slate-100/70 text-slate-600 font-extrabold uppercase border-b border-slate-200 text-[11px]">
                        <th class="py-4 px-5 text-center w-12">#</th>
                        <th class="py-4 px-4">اسم المحاسب</th>
                        <th class="py-4 px-4">رقم الجوال (الدخول)</th>
                        <th class="py-4 px-4">المكتب التابع له وحالة الميزة</th>
                        <th class="py-4 px-4 text-center">حالة الحساب</th>
                        <th class="py-4 px-4">آخر تسجيل دخول</th>
                        <th class="py-4 px-5 text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                    @forelse ($accountants as $accountant)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            
                            <!-- الرقم التسلسلي -->
                            <td class="py-4 px-5 text-center font-mono font-bold text-slate-400">
                                {{ ($accountants->currentPage() - 1) * $accountants->perPage() + $loop->iteration }}
                            </td>

                            <!-- اسم المحاسب -->
                            <td class="py-4 px-4">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-primary to-slate-800 text-white flex items-center justify-center font-black text-sm shadow-2xs shrink-0">
                                        {{ mb_substr($accountant->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <span class="font-extrabold text-slate-900 text-sm block">{{ $accountant->name }}</span>
                                        <span class="text-[10px] text-slate-400">أضيف: {{ $accountant->created_at?->format('Y-m-d') }}</span>
                                    </div>
                                </div>
                            </td>

                            <!-- رقم الجوال -->
                            <td class="py-4 px-4">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-bold text-slate-800 text-sm tracking-wide" dir="ltr">
                                        {{ $accountant->phone }}
                                    </span>
                                    <a href="https://wa.me/967{{ $accountant->phone }}"
                                       target="_blank"
                                       title="مراسلة عبر واتساب"
                                       class="w-7 h-7 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-600 border border-emerald-200 flex items-center justify-center transition-colors">
                                        <span class="material-symbols-outlined text-[15px]">chat</span>
                                    </a>
                                    <a href="tel:{{ $accountant->phone }}"
                                       title="اتصال هاتفي"
                                       class="w-7 h-7 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-600 border border-blue-200 flex items-center justify-center transition-colors">
                                        <span class="material-symbols-outlined text-[15px]">call</span>
                                    </a>
                                </div>
                            </td>

                            <!-- المكتب التابع له وحالة ميزة المحاسبين للمكتب -->
                            <td class="py-4 px-4">
                                @if ($accountant->office)
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-1.5">
                                            <a href="{{ route('admin.accountants.index', ['office_id' => $accountant->office->id]) }}"
                                               class="font-black text-slate-900 hover:text-secondary underline decoration-dotted transition-colors text-xs"
                                               title="فلترة محاسبي هذا المكتب">
                                                {{ $accountant->office->name }}
                                            </a>
                                            <span class="text-[11px] text-slate-400">
                                                ({{ $accountant->office->branch_name ?: 'الفرع الرئيسي' }})
                                            </span>
                                        </div>

                                        @if ($accountant->office->hasFeature('office_accountants'))
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                <span class="material-symbols-outlined text-[12px]">check_circle</span>
                                                <span>ميزة المحاسبين مفعلة للمكتب</span>
                                            </span>
                                        @else
                                            <a href="{{ route('admin.offices.features', $accountant->office) }}"
                                               class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-300 hover:bg-amber-100 transition-colors"
                                               title="انقر لتفعيل ميزة نظام المحاسبين لهذا المكتب">
                                                <span class="material-symbols-outlined text-[12px] text-amber-600">warning</span>
                                                <span>ميزة المحاسبين غير مفعلة للمكتب بعد (تفعيل)</span>
                                            </a>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-slate-400 italic">مكتب غير محدد</span>
                                @endif
                            </td>

                            <!-- حالة حساب المحاسب -->
                            <td class="py-4 px-4 text-center">
                                @if ($accountant->is_active)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-extrabold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        <span>نشط</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-extrabold bg-rose-50 text-rose-700 border border-rose-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                        <span>موقوف</span>
                                    </span>
                                @endif
                            </td>

                            <!-- آخر تسجيل دخول -->
                            <td class="py-4 px-4">
                                @if ($accountant->last_login_at)
                                    <div class="font-mono text-[11px] font-bold text-slate-700">
                                        {{ $accountant->last_login_at->format('Y-m-d H:i') }}
                                    </div>
                                    <div class="text-[10px] text-slate-400">
                                        {{ $accountant->last_login_at->diffForHumans() }}
                                    </div>
                                @else
                                    <span class="text-[11px] text-slate-400 italic">لم يسجل دخول بعد</span>
                                @endif
                            </td>

                            <!-- أزرار الإجراءات -->
                            <td class="py-4 px-5 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    
                                    <!-- زر التعديل وتغيير الرمز -->
                                    <button type="button"
                                            @click="openEditModal({{ Js::from([
                                                'id'        => $accountant->id,
                                                'office_id' => $accountant->office_id,
                                                'name'      => $accountant->name,
                                                'phone'     => $accountant->phone,
                                                'is_active' => $accountant->is_active ? 1 : 0,
                                            ]) }})"
                                            title="تعديل بيانات المحاسب أو تغيير الرمز السري"
                                            class="px-2.5 py-1.5 rounded-xl bg-sky-50 hover:bg-sky-100 text-sky-700 border border-sky-200 text-[11px] font-bold flex items-center gap-1 transition-colors">
                                        <span class="material-symbols-outlined text-sm">edit</span>
                                        <span>تعديل / الرمز</span>
                                    </button>

                                    <!-- تبديل الحالة (نشط / موقوف) -->
                                    <form method="POST" action="{{ route('admin.accountants.toggle_status', $accountant) }}" class="inline">
                                        @csrf
                                        <button type="submit"
                                                title="{{ $accountant->is_active ? 'إيقاف الحساب مؤقتاً' : 'إعادة تفعيل الحساب' }}"
                                                class="p-1.5 rounded-xl border transition-colors {{ $accountant->is_active ? 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100' : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                                            <span class="material-symbols-outlined text-base">
                                                {{ $accountant->is_active ? 'block' : 'check_circle' }}
                                            </span>
                                        </button>
                                    </form>

                                    <!-- حذف الحساب -->
                                    <form method="POST" action="{{ route('admin.accountants.destroy', $accountant) }}" class="inline"
                                          onsubmit="return confirm('هل أنت متأكد من حذف حساب المحاسب ({{ $accountant->name }}) نهائياً؟');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                title="حذف الحساب نهائياً"
                                                class="p-1.5 rounded-xl border border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100 transition-colors">
                                            <span class="material-symbols-outlined text-base">delete</span>
                                        </button>
                                    </form>

                                </div>
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-16 text-center">
                                <div class="flex flex-col items-center justify-center max-w-sm mx-auto">
                                    <div class="w-16 h-16 rounded-3xl bg-slate-100 text-slate-400 border border-slate-200 flex items-center justify-center mb-3">
                                        <span class="material-symbols-outlined text-3xl">manage_accounts</span>
                                    </div>
                                    <h4 class="text-sm font-extrabold text-slate-800">لا توجد حسابات محاسبين</h4>
                                    <p class="text-xs text-slate-400 mt-1 mb-4">
                                        @if(!empty($filters['office_id']) || !empty($filters['search']))
                                            لم يتم العثور على أي محاسب يطابق معايير البحث أو الفلترة الحالية.
                                        @else
                                            لم تقم بإضافة أي محاسب حتى الآن. اضغط على الزر أدناه لإنشاء أول حساب محاسب وربطه بمكتبه.
                                        @endif
                                    </p>
                                    <button type="button"
                                            @click="openAddModal()"
                                            class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl bg-secondary text-primary-950 text-xs font-extrabold hover:brightness-105 transition-all shadow-sm">
                                        <span class="material-symbols-outlined text-base">person_add</span>
                                        <span>إضافة محاسب جديد</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($accountants->hasPages())
            <div class="p-4 border-t border-slate-100 bg-slate-50/50">
                {{ $accountants->links() }}
            </div>
        @endif

    </div>

    <!-- =========================================================
         نافذة إضافة محاسب جديد (Add Accountant Modal)
    ========================================================== -->
    <div x-show="addModal.show"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4"
         @keydown.escape.window="addModal.show = false">
        
        <div @click.outside="addModal.show = false"
             class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-200">
            
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-5">
                <div class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-xl bg-secondary/15 text-secondary flex items-center justify-center">
                        <span class="material-symbols-outlined text-2xl">person_add</span>
                    </div>
                    <div>
                        <h3 class="text-base font-extrabold text-slate-900">إضافة محاسب جديد</h3>
                        <p class="text-xs text-slate-500">ربط المحاسب بالمكتب وتعيين رقم الجوال والرمز السري للدخول</p>
                    </div>
                </div>
                <button type="button" @click="addModal.show = false" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>

            <form method="POST" action="{{ route('admin.accountants.store') }}" class="space-y-4">
                @csrf

                <!-- اختيار المكتب -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        المكتب / الفرع التابع له المحاسب <span class="text-rose-500">*</span>
                    </label>
                    <select name="office_id" required
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-bold text-slate-800 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                        <option value="">— اختر المكتب —</option>
                        @foreach ($offices as $off)
                            <option value="{{ $off->id }}" {{ old('office_id', $filters['office_id'] ?? '') == $off->id ? 'selected' : '' }}>
                                {{ $off->name }} {{ $off->branch_name ? "({$off->branch_name})" : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- اسم المحاسب -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        اسم المحاسب <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           placeholder="مثال: أحمد محمد الصالح"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-bold text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                </div>

                <!-- رقم الجوال (لتسجيل الدخول) -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        رقم الجوال (لتسجيل الدخول) <span class="text-rose-500">*</span>
                    </label>
                    <input type="tel" name="phone" value="{{ old('phone') }}" required dir="ltr"
                           inputmode="numeric" maxlength="9"
                           placeholder="7XXXXXXXX"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-mono font-bold text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-secondary transition-all text-left">
                    <p class="text-[11px] text-slate-400 mt-1">
                        رقم يمني مكون من 9 أرقام يبدأ بـ 7 (يُستخدم كاسم مستخدم لتسجيل دخول المحاسب).
                    </p>
                </div>

                <!-- الرمز السري / كلمة المرور -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        الرمز السري / كلمة المرور <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="password" required minlength="4" maxlength="50" dir="ltr"
                           placeholder="أدخل رمز الدخول (4 خانات على الأقل)"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-mono font-bold text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-secondary transition-all text-left">
                </div>

                <!-- حالة الحساب -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        حالة الحساب
                    </label>
                    <select name="is_active"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-bold text-slate-700 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                        <option value="1" {{ old('is_active', '1') == '1' ? 'selected' : '' }}>نشط (مسموح له بتسجيل الدخول)</option>
                        <option value="0" {{ old('is_active') === '0' ? 'selected' : '' }}>موقوف (غير مسموح له بالدخول حالياً)</option>
                    </select>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                    <button type="button" @click="addModal.show = false"
                            class="px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50">
                        إلغاء
                    </button>
                    <button type="submit"
                            class="px-6 py-2.5 rounded-xl bg-secondary text-primary-950 text-xs font-extrabold hover:brightness-105 shadow-sm flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-base">save</span>
                        <span>إنشاء حساب المحاسب</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- =========================================================
         نافذة تعديل بيانات محاسب أو تغيير رمزه السري (Edit Modal)
    ========================================================== -->
    <div x-show="editModal.show"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4"
         @keydown.escape.window="editModal.show = false">
        
        <div @click.outside="editModal.show = false"
             class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-200">
            
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-5">
                <div class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center">
                        <span class="material-symbols-outlined text-2xl">manage_accounts</span>
                    </div>
                    <div>
                        <h3 class="text-base font-extrabold text-slate-900">تعديل بيانات المحاسب</h3>
                        <p class="text-xs text-slate-500">تحديث المكتب، الاسم، رقم الجوال، أو تعيين رمز سري جديد</p>
                    </div>
                </div>
                <button type="button" @click="editModal.show = false" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>

            <form method="POST" :action="editModal.action" class="space-y-4">
                @csrf
                @method('PUT')

                <!-- اختيار المكتب -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        المكتب / الفرع التابع له المحاسب <span class="text-rose-500">*</span>
                    </label>
                    <select name="office_id" x-model="editModal.office_id" required
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-bold text-slate-800 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                        @foreach ($offices as $off)
                            <option value="{{ $off->id }}">
                                {{ $off->name }} {{ $off->branch_name ? "({$off->branch_name})" : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- اسم المحاسب -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        اسم المحاسب <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="name" x-model="editModal.name" required
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-bold text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                </div>

                <!-- رقم الجوال -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        رقم الجوال (لتسجيل الدخول) <span class="text-rose-500">*</span>
                    </label>
                    <input type="tel" name="phone" x-model="editModal.phone" required dir="ltr"
                           inputmode="numeric" maxlength="9"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-mono font-bold text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-secondary transition-all text-left">
                </div>

                <!-- الرمز السري الجديد (اختياري) -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        الرمز السري الجديد <span class="text-[10px] font-normal text-slate-400">(اختياري عند التعديل)</span>
                    </label>
                    <input type="text" name="password" x-model="editModal.password" minlength="4" maxlength="50" dir="ltr"
                           placeholder="اتركه فارغاً للاحتفاظ بالرمز السري الحالي دون تغيير"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-mono font-bold text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-secondary transition-all text-left">
                    <p class="text-[11px] text-amber-700 mt-1">
                        ملاحظة: إذا تركت هذا الحقل فارغاً فستبقى كلمة المرور القديمة كما هي.
                    </p>
                </div>

                <!-- حالة الحساب -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        حالة الحساب
                    </label>
                    <select name="is_active" x-model="editModal.is_active"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-bold text-slate-700 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                        <option value="1">نشط (مسموح له بتسجيل الدخول)</option>
                        <option value="0">موقوف (غير مسموح له بالدخول حالياً)</option>
                    </select>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                    <button type="button" @click="editModal.show = false"
                            class="px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50">
                        إلغاء
                    </button>
                    <button type="submit"
                            class="px-6 py-2.5 rounded-xl bg-secondary text-primary-950 text-xs font-extrabold hover:brightness-105 shadow-sm flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-base">save</span>
                        <span>حفظ التعديلات</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
    function accountantsManager() {
        return {
            addModal: {
                show: {{ (isset($errors) && $errors->any() && !old('_method')) ? 'true' : 'false' }},
            },
            editModal: {
                show: false,
                action: '',
                office_id: '',
                name: '',
                phone: '',
                password: '',
                is_active: 1,
            },

            openAddModal() {
                this.addModal.show = true;
            },

            openEditModal(accountant) {
                this.editModal.action = `{{ url('admin/accountants') }}/${accountant.id}`;
                this.editModal.office_id = String(accountant.office_id || '');
                this.editModal.name = accountant.name || '';
                this.editModal.phone = accountant.phone || '';
                this.editModal.password = '';
                this.editModal.is_active = accountant.is_active ? 1 : 0;
                this.editModal.show = true;
            }
        };
    }
</script>
@endpush

