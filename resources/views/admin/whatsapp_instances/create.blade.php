@extends('admin.layouts.app')

@section('title', 'إضافة Instance جديدة للواتساب')
@section('page-title', 'إضافة Instance جديدة')
@section('page-subtitle', 'تسجيل معرف خادم واتساب جديد وربطه بمكتب محدد')

@section('header-actions')
    <a href="{{ route('admin.whatsapp_instances.index') }}"
       class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 bg-white text-xs font-bold hover:bg-slate-50 transition-colors">
        <span class="material-symbols-outlined text-lg">arrow_forward</span>
        <span>العودة للـ Instances</span>
    </a>
@endsection

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/90 shadow-xs">
        
        <form method="POST" action="{{ route('admin.whatsapp_instances.store') }}" class="space-y-6">
            @csrf

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">اسم صاحب المعرف أو الفرع <span class="text-rose-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           placeholder="مثال: instance_awad أو فرع عدن"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-semibold text-slate-800 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">معرف الـ Instance في السيرفر (ID) <span class="text-rose-500">*</span></label>
                    <input type="text" name="instance_id" value="{{ old('instance_id') }}" required dir="ltr"
                           placeholder="مثال: awad أو 7ce97a2f-8204-..."
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-mono font-semibold text-slate-800 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                    <p class="text-[11px] text-slate-400 mt-1">يجب أن يتطابق مع اسم الـ Instance في سيرفر Evolution API.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">الرمز السري المخصص للـ Instance (PIN Code) <span class="text-rose-500">*</span></label>
                    <input type="text" name="pin_code" value="{{ old('pin_code') }}" required dir="ltr"
                           placeholder="مثال: 123456"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-mono font-semibold text-slate-800 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">رقم الهاتف المسجل (اختياري)</label>
                    <input type="text" name="phone_number" value="{{ old('phone_number') }}" dir="ltr"
                           placeholder="770000000"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-mono font-semibold text-slate-800 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">ربط هذه الـ Instance بمكتب فور الحفظ (اختياري)</label>
                    <select name="assign_to_office_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-3 text-xs font-bold text-slate-800 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                        <option value="">-- بدون ربط حالياً --</option>
                        @foreach ($offices as $off)
                            <option value="{{ $off->id }}" {{ old('assign_to_office_id') == $off->id ? 'selected' : '' }}>
                                {{ $off->name }} {{ $off->branch_name ? "({$off->branch_name})" : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="pt-2">
                    <label class="flex items-center gap-2.5 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" checked class="w-4 h-4 rounded text-secondary focus:ring-secondary">
                        <span class="text-xs font-bold text-slate-700">تفعيل هذه الـ Instance</span>
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <a href="{{ route('admin.whatsapp_instances.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50">
                    إلغاء
                </a>
                <button type="submit" class="px-7 py-2.5 rounded-xl bg-secondary text-primary-950 text-xs font-extrabold hover:brightness-105 shadow-md shadow-secondary/20 flex items-center gap-2">
                    <span class="material-symbols-outlined text-lg">check</span>
                    <span>حفظ الـ Instance</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
