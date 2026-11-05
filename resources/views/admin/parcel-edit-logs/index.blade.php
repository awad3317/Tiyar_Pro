@extends('admin.layouts.app')

@section('title', 'سجل تتبع تعديلات المحاسبين')
@section('page-title', 'سجل الرقابة وتتبع تعديلات المحاسبين على الطرود')
@section('page-subtitle', 'متابعة دقيقة لكل تعديل أجراه أي محاسب على الطرود (البيانات قبل التعديل ⬅️ البيانات الجديدة بعد التعديل)')

@section('header-actions')
    <a href="{{ route('admin.manifests.index') }}"
       class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-200 text-xs font-extrabold transition-all">
        <span class="material-symbols-outlined text-lg text-secondary">receipt_long</span>
        <span>رقابة كشوفات السائقين</span>
    </a>
@endsection

@section('content')
<div class="space-y-6">

    <!-- 1. بطاقات الإحصائيات السريعة لحركات التعديل -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

        <!-- إجمالي الحركات -->
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-slate-400 uppercase">إجمالي حركات السجل</p>
                <h4 class="text-2xl font-black text-slate-900 mt-1 font-mono">{{ number_format($stats['total_logs']) }}</h4>
                <p class="text-[10px] text-slate-400 mt-0.5">عملية موثقة بالنظام</p>
            </div>
            <div class="w-11 h-11 rounded-2xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-2xl">manage_history</span>
            </div>
        </div>

        <!-- تعديل بيانات الطرود -->
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-sky-200/90 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-sky-700 uppercase">تعديل بيانات الطرود</p>
                <h4 class="text-2xl font-black text-sky-900 mt-1 font-mono">{{ number_format($stats['update_details_count']) }}</h4>
                <p class="text-[10px] text-slate-400 mt-0.5">تعديل اسم / رقم / آجل / مبلغ</p>
            </div>
            <div class="w-11 h-11 rounded-2xl bg-sky-50 text-sky-600 border border-sky-200 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-2xl">edit_note</span>
            </div>
        </div>

        <!-- ضم طرود للكشوفات -->
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-emerald-200/90 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-emerald-700 uppercase">ضم طرود لكشوفات</p>
                <h4 class="text-2xl font-black text-emerald-800 mt-1 font-mono">{{ number_format($stats['assign_manifest_count']) }}</h4>
                <p class="text-[10px] text-slate-400 mt-0.5">ربط طرد بكشف سائق</p>
            </div>
            <div class="w-11 h-11 rounded-2xl bg-emerald-50 text-emerald-600 border border-emerald-200 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-2xl">playlist_add_check</span>
            </div>
        </div>

        <!-- إخراج طرود من الكشوفات -->
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-rose-200/90 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-rose-700 uppercase">إخراج من كشوفات</p>
                <h4 class="text-2xl font-black text-rose-800 mt-1 font-mono">{{ number_format($stats['remove_manifest_count']) }}</h4>
                <p class="text-[10px] text-slate-400 mt-0.5">إعادة طرد لغير المصنفة</p>
            </div>
            <div class="w-11 h-11 rounded-2xl bg-rose-50 text-rose-600 border border-rose-200 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-2xl">output</span>
            </div>
        </div>

    </div>

    <!-- 2. بطاقة الفلاتر والبحث المتقدم -->
    <div class="bg-white p-5 sm:p-6 rounded-3xl border border-slate-200/90 shadow-xs">
        <form method="GET" action="{{ route('admin.parcel_edit_logs.index') }}" class="space-y-4">
            @if (!empty($filters['parcel_id']))
                <input type="hidden" name="parcel_id" value="{{ $filters['parcel_id'] }}">
                <div class="p-3 rounded-2xl bg-amber-50 border border-amber-200 flex items-center justify-between text-xs font-extrabold text-amber-900">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-base text-amber-600">filter_alt</span>
                        <span>يتم حالياً عرض سجل التعديلات لطرد محدد فقط ({{ $filters['parcel_id'] }})</span>
                    </div>
                    <a href="{{ route('admin.parcel_edit_logs.index', collect($filters)->except('parcel_id')->filter()->all()) }}"
                       class="px-2.5 py-1 rounded-lg bg-white hover:bg-amber-100 text-amber-800 border border-amber-300 text-[11px]">
                        إلغاء فلتر الطرد وعرض الكل
                    </a>
                </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3.5 items-end">

                <!-- 1. فلتر المكتب -->
                <div class="lg:col-span-3">
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">المكتب:</label>
                    <select name="office_id"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-bold text-slate-700 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                        <option value="">جميع المكاتب</option>
                        @foreach ($offices as $off)
                            <option value="{{ $off->id }}" {{ ($filters['office_id'] ?? '') == $off->id ? 'selected' : '' }}>
                                {{ $off->name }} {{ $off->branch_name ? "({$off->branch_name})" : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- 2. فلتر المحاسب -->
                <div class="lg:col-span-3">
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">المحاسب:</label>
                    <select name="accountant_id"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-bold text-slate-700 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                        <option value="">جميع المحاسبين</option>
                        @foreach ($accountants as $acc)
                            <option value="{{ $acc->id }}" {{ ($filters['accountant_id'] ?? '') == $acc->id ? 'selected' : '' }}>
                                {{ $acc->name }} {{ $acc->office ? "— ({$acc->office->name})" : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- 3. فلتر نوع العملية -->
                <div class="lg:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">نوع العملية:</label>
                    <select name="action"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-bold text-slate-700 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                        <option value="">الكل</option>
                        <option value="update_details" {{ ($filters['action'] ?? '') === 'update_details' ? 'selected' : '' }}>تعديل بيانات الطرد</option>
                        <option value="assign_manifest" {{ ($filters['action'] ?? '') === 'assign_manifest' ? 'selected' : '' }}>ضم لكشف سائق</option>
                        <option value="remove_from_manifest" {{ ($filters['action'] ?? '') === 'remove_from_manifest' ? 'selected' : '' }}>إخراج من كشف</option>
                    </select>
                </div>

                <!-- 4. من تاريخ -->
                <div class="lg:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">من تاريخ:</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-mono font-bold text-slate-700 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                </div>

                <!-- 5. إلى تاريخ -->
                <div class="lg:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">إلى تاريخ:</label>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-mono font-bold text-slate-700 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                </div>

                <!-- 6. البحث برقم جوال المستلم أو اسم المستلم أو رقم السند -->
                <div class="lg:col-span-9">
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">بحث باسم المستلم، رقم جوال المستلم، أو رقم السند:</label>
                    <div class="relative">
                        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                               placeholder="ابحث برقم جوال المستلم (7XXXXXXXX)، اسم المستلم، أو رقم السند..."
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 pr-9 text-xs font-semibold text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                        <span class="material-symbols-outlined text-slate-400 absolute right-2.5 top-1/2 -translate-y-1/2 text-lg pointer-events-none">search</span>
                    </div>
                </div>

                <!-- 7. أزرار التطبيق والإلغاء -->
                <div class="lg:col-span-3 flex items-center gap-2">
                    <button type="submit"
                            class="flex-1 py-2.5 px-4 rounded-xl bg-primary text-white text-xs font-bold hover:bg-primary-800 transition-colors flex items-center justify-center gap-1.5 shadow-sm">
                        <span class="material-symbols-outlined text-base">filter_alt</span>
                        <span>تطبيق الفلتر</span>
                    </button>

                    @if(!empty($filters['office_id']) || !empty($filters['accountant_id']) || !empty($filters['action']) || !empty($filters['date_from']) || !empty($filters['date_to']) || !empty($filters['parcel_id']) || !empty($filters['search']))
                        <a href="{{ route('admin.parcel_edit_logs.index') }}"
                           class="py-2.5 px-3.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 text-xs font-bold transition-colors flex items-center gap-1 shrink-0"
                           title="إعادة ضبط الفلاتر">
                            <span class="material-symbols-outlined text-base">restart_alt</span>
                            <span>إلغاء</span>
                        </a>
                    @endif
                </div>

            </div>
        </form>
    </div>

    <!-- 3. جدول سجل التعديلات (before -> after) -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs overflow-hidden">

        <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 bg-slate-50/50">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-primary text-2xl">history</span>
                <div>
                    <h3 class="text-sm font-black text-slate-900">سجل تتبع التعديلات التفصيلي (قبل ⬅️ بعد)</h3>
                    <p class="text-xs text-slate-500">توثيق كامل لكل حركة تعديل أجراها المحاسبون على الطرود</p>
                </div>
            </div>

            <span class="px-3 py-1 rounded-full text-xs font-extrabold bg-slate-100 text-slate-700 border border-slate-200">
                إجمالي الحركات: {{ number_format($logs->total()) }}
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead>
                    <tr class="bg-slate-100/70 text-slate-600 font-extrabold uppercase border-b border-slate-200 text-[11px]">
                        <th class="py-4 px-4">التاريخ والوقت</th>
                        <th class="py-4 px-4">المحاسب والمكتب</th>
                        <th class="py-4 px-4">بيانات الطرد المعدّل</th>
                        <th class="py-4 px-4 text-center">نوع الإجراء</th>
                        <th class="py-4 px-5">تفاصيل التغييرات (قبل التعديل ⬅️ بعد التعديل)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                    @forelse ($logs as $log)
                        @php
                            $changedFields = is_array($log->changed_fields) ? $log->changed_fields : [];
                            $oldValues     = is_array($log->old_values) ? $log->old_values : [];
                            $newValues     = is_array($log->new_values) ? $log->new_values : [];
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition-colors align-top">

                            <!-- 1. التاريخ والوقت الدقيق + منذ متى -->
                            <td class="py-4 px-4 whitespace-nowrap">
                                <div class="font-mono font-extrabold text-slate-800 text-xs" dir="ltr">
                                    {{ $log->created_at?->format('Y-m-d h:i A') }}
                                </div>
                                <div class="text-[11px] text-slate-400 mt-0.5">
                                    {{ $log->created_at?->diffForHumans() }}
                                </div>
                                @if ($log->ip_address)
                                    <div class="font-mono text-[10px] text-slate-400 mt-0.5" dir="ltr">
                                        IP: {{ $log->ip_address }}
                                    </div>
                                @endif
                            </td>

                            <!-- 2. المحاسب والمكتب -->
                            <td class="py-4 px-4">
                                @if ($log->accountant)
                                    <a href="{{ route('admin.parcel_edit_logs.index', array_merge(request()->query(), ['accountant_id' => $log->accountant_id])) }}"
                                       class="font-black text-slate-900 hover:text-secondary underline decoration-dotted text-sm block">
                                        {{ $log->accountant->name }}
                                    </a>
                                @else
                                    <span class="font-bold text-slate-500 block">محاسب محذوف</span>
                                @endif

                                @if ($log->office)
                                    <a href="{{ route('admin.parcel_edit_logs.index', array_merge(request()->query(), ['office_id' => $log->office_id])) }}"
                                       class="inline-flex items-center gap-1 text-[11px] text-slate-500 hover:text-primary mt-1">
                                        <span class="material-symbols-outlined text-[13px] text-secondary">domain</span>
                                        <span>{{ $log->office->name }}</span>
                                    </a>
                                @endif
                            </td>

                            <!-- 3. بيانات الطرد المعدّل -->
                            <td class="py-4 px-4">
                                @if ($log->parcel)
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-200 font-extrabold text-[11px]">
                                                {{ $log->parcel->package_type ?: 'طرد' }}
                                            </span>
                                            <span class="font-extrabold text-slate-900">
                                                {{ $log->parcel->recipient_name ?: 'بدون اسم' }}
                                            </span>
                                        </div>

                                        <div class="flex items-center gap-2 text-[11px]">
                                            <span class="font-mono font-bold text-slate-600" dir="ltr">
                                                {{ $log->parcel->recipient_phone }}
                                            </span>
                                            @if ($log->parcel->receipt_number)
                                                <span class="text-slate-400">•</span>
                                                <span class="font-mono text-slate-500">سند #{{ $log->parcel->receipt_number }}</span>
                                            @endif
                                        </div>

                                        @if ($log->parcel->manifest_id)
                                            <a href="{{ route('admin.manifests.show', $log->parcel->manifest_id) }}"
                                               class="inline-flex items-center gap-1 text-[10px] font-extrabold text-primary hover:text-secondary underline">
                                                <span class="material-symbols-outlined text-[12px]">receipt_long</span>
                                                <span>مدرج في كشف #{{ $log->parcel->manifest_id }}</span>
                                            </a>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-slate-400 italic">طرد محذوف ({{ $log->parcel_id }})</span>
                                @endif
                            </td>

                            <!-- 4. نوع الإجراء (action) -->
                            <td class="py-4 px-4 text-center whitespace-nowrap">
                                @if ($log->action === 'assign_manifest')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-extrabold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="material-symbols-outlined text-[13px]">playlist_add_check</span>
                                        <span>ضم إلى كشف</span>
                                    </span>
                                @elseif ($log->action === 'remove_from_manifest')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-extrabold bg-rose-50 text-rose-700 border border-rose-200">
                                        <span class="material-symbols-outlined text-[13px]">output</span>
                                        <span>إخراج من كشف</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-extrabold bg-sky-50 text-sky-700 border border-sky-200">
                                        <span class="material-symbols-outlined text-[13px]">edit_note</span>
                                        <span>تعديل بيانات طرد</span>
                                    </span>
                                @endif
                            </td>

                            <!-- 5. تفاصيل التغييرات (البيانات قبل التعديل ⬅️ البيانات الجديدة بعد التعديل) -->
                            <td class="py-4 px-5">
                                <div class="space-y-1.5 min-w-[280px]">
                                    @forelse ($changedFields as $field)
                                        @php
                                            $label   = \App\Models\ParcelEditLog::fieldLabel($field);
                                            $oldText = \App\Models\ParcelEditLog::formatValue($field, $oldValues[$field] ?? null);
                                            $newText = \App\Models\ParcelEditLog::formatValue($field, $newValues[$field] ?? null);
                                        @endphp
                                        <div class="bg-slate-50 rounded-xl p-2 border border-slate-200/80 flex items-center justify-between gap-2 flex-wrap">
                                            <span class="font-extrabold text-slate-700 text-[11px]">{{ $label }}:</span>

                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <!-- القيمة السابقة (قبل التعديل) -->
                                                <span class="px-2 py-0.5 rounded-lg bg-rose-50 text-rose-700 border border-rose-200 line-through font-bold text-[11px]">
                                                    {{ $oldText }}
                                                </span>

                                                <!-- سهم انتقال -->
                                                <span class="text-slate-400 font-black">⬅️</span>

                                                <!-- القيمة الجديدة (بعد التعديل) -->
                                                <span class="px-2 py-0.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 font-extrabold text-[11px]">
                                                    {{ $newText }}
                                                </span>
                                            </div>
                                        </div>
                                    @empty
                                        <span class="text-slate-400 text-[11px]">لا توجد تفاصيل حقول</span>
                                    @endforelse
                                </div>
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-16 text-center">
                                <div class="flex flex-col items-center justify-center max-w-sm mx-auto">
                                    <div class="w-16 h-16 rounded-3xl bg-slate-100 text-slate-400 border border-slate-200 flex items-center justify-center mb-3">
                                        <span class="material-symbols-outlined text-3xl">history_toggle_off</span>
                                    </div>
                                    <h4 class="text-sm font-extrabold text-slate-800">لا توجد سجلات تعديل مطابقة</h4>
                                    <p class="text-xs text-slate-400 mt-1">لم يتم العثور على أي حركات تعديل تطابق الفلاتر المختارة.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div class="p-4 border-t border-slate-100 bg-slate-50/50">
                {{ $logs->links() }}
            </div>
        @endif

    </div>

</div>
@endsection

