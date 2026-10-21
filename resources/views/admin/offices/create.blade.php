@extends('admin.layouts.app')

@section('title', 'إضافة مكتب جديد')
@section('page-title', 'إضافة مكتب جديد')
@section('page-subtitle', 'إنشاء مكتب وشريحة اتصال جديدة وتجهيز قنوات الإشعار')

@section('header-actions')
    <a href="{{ route('admin.offices.index') }}"
       class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 bg-white text-xs font-bold hover:bg-slate-50 transition-colors">
        <span class="material-symbols-outlined text-lg">arrow_forward</span>
        <span>العودة لدليل المكاتب</span>
    </a>
@endsection

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <form method="POST" action="{{ route('admin.offices.store') }}" class="space-y-6">
        @csrf

        <!-- 1. المعلومات الأساسية للمكتب وحساب الدخول -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/90 shadow-xs space-y-6">
            <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                <div class="w-10 h-10 rounded-xl bg-primary-50 text-primary flex items-center justify-center font-bold">
                    <span class="material-symbols-outlined text-xl">domain</span>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900">المعلومات الأساسية وحساب تسجيل الدخول</h3>
                    <p class="text-xs text-slate-500">اسم المكتب ورقم الهاتف المستخدم لتسجيل دخول موظفي الفرع</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <!-- اسم المكتب -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">اسم المكتب / الشركة <span class="text-rose-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           placeholder="مثال: مكتب النورس للشحن"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-semibold text-slate-800 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                </div>

                <!-- اسم الفرع أو المدينة -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">اسم الفرع / المدينة</label>
                    <input type="text" name="branch_name" value="{{ old('branch_name') }}"
                           placeholder="مثال: فرع صنعاء - الدائري"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-semibold text-slate-800 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                </div>

                <!-- رقم هاتف الواتساب (للدخول) -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">رقم هاتف الواتساب (اسم الدخول) <span class="text-rose-500">*</span></label>
                    <input type="tel" name="whatsapp_sender_phone" value="{{ old('whatsapp_sender_phone') }}" required dir="ltr"
                           placeholder="770000000"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-mono font-semibold text-slate-800 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                    <p class="text-[11px] text-slate-400 mt-1">يُستخدم هذا الرقم من قِبل موظفي المكتب لتسجيل الدخول.</p>
                </div>

                <!-- كلمة المرور -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">كلمة المرور الابتدائية <span class="text-rose-500">*</span></label>
                    <input type="password" name="password" required minlength="6" dir="ltr"
                           placeholder="••••••••"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-mono font-semibold text-slate-800 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                    <p class="text-[11px] text-slate-400 mt-1">6 أحرف/أرقام كحد أدنى.</p>
                </div>
            </div>
        </div>

        <!-- 2. إعدادات وقالب رسائل الـ SMS (httpSMS) -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/90 shadow-xs space-y-6">
            <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center font-bold">
                    <span class="material-symbols-outlined text-xl">sms</span>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900">إعدادات بوابة رسائل الـ SMS (httpSMS)</h3>
                    <p class="text-xs text-slate-500">رقم شريحة الإرسال والـ API Key الخاص ببوابة httpSMS</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">رقم الشريحة الراسلة (مع مفتاح الدولة) <span class="text-rose-500">*</span></label>
                    <input type="text" name="httpsms_from_phone" value="{{ old('httpsms_from_phone', '+967') }}" required dir="ltr"
                           placeholder="+967770000000"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-mono font-semibold text-slate-800 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">مفتاح API الخاص بـ httpSMS</label>
                    <input type="text" name="httpsms_api_key" value="{{ old('httpsms_api_key') }}" dir="ltr"
                           placeholder="httpSMS API Key..."
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-mono font-semibold text-slate-800 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 mb-2">قالب رسالة الـ SMS (اختياري، يوجد قالب افتراضي)</label>
                    <textarea name="sms_template" rows="3"
                              class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3.5 text-xs text-slate-800 leading-relaxed focus:bg-white focus:outline-none focus:border-secondary transition-all"
                              placeholder="عميلنا العزيز،&#10;مكتب: {office} ({branch})&#10;تم استلام طردكم: {package}&#10;رقم السند: {receipt}&#10;يرجى التوجه للاستلام.">{{ old('sms_template') }}</textarea>
                    <div class="flex flex-wrap gap-1.5 mt-2 text-[11px] text-slate-500">
                        <span>المتغيرات المتاحة:</span>
                        <span class="px-1.5 py-0.5 rounded bg-slate-100 font-mono">{office}</span>
                        <span class="px-1.5 py-0.5 rounded bg-slate-100 font-mono">{branch}</span>
                        <span class="px-1.5 py-0.5 rounded bg-slate-100 font-mono">{recipient}</span>
                        <span class="px-1.5 py-0.5 rounded bg-slate-100 font-mono">{package}</span>
                        <span class="px-1.5 py-0.5 rounded bg-slate-100 font-mono">{receipt}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. إعدادات واتساب (Evolution API / WhatsApp Server) -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/90 shadow-xs space-y-6" x-data="{
            instances: {{ Js::from($instances) }},
            selectInstance(e) {
                const id = e.target.value;
                const inst = this.instances.find(i => i.instance_id === id);
                if (inst) {
                    document.getElementById('wa_instance_name').value = inst.name;
                }
            }
        }">
            <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
                    <span class="material-symbols-outlined text-xl">chat</span>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900">إعدادات خادم الواتساب (WhatsApp Instance)</h3>
                    <p class="text-xs text-slate-500">ربط المكتب بسيرفر الواتساب والـ Instance الخاصة به</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                
                <!-- اختيار Instance موجودة مسبقاً للتسهيل -->
                @if($instances->count() > 0)
                    <div class="md:col-span-2 p-3.5 rounded-xl bg-amber-50/70 border border-amber-200/60">
                        <label class="block text-xs font-extrabold text-amber-900 mb-1.5">اختر من الـ Instances المعرفة مسبقاً (اختياري)</label>
                        <select @change="selectInstance($event)" class="w-full bg-white border border-amber-300 rounded-lg px-3 py-2 text-xs font-bold text-slate-800">
                            <option value="">-- تخصيص إعدادات يدوية أو اختر instance جاهزة --</option>
                            @foreach ($instances as $inst)
                                <option value="{{ $inst->instance_id }}">{{ $inst->name }} ({{ $inst->instance_id }})</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">رابط الخادم (Base URL)</label>
                    <input type="url" name="whatsapp_base_url" value="{{ old('whatsapp_base_url', config('whatsapp.base_url', 'http://195.35.24.73:4000')) }}" dir="ltr"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-mono font-semibold text-slate-800 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">معرف الـ Instance (ID)</label>
                    <input type="text" name="whatsapp_instance_id" id="wa_instance_id" value="{{ old('whatsapp_instance_id') }}" dir="ltr"
                           placeholder="awad أو UUID..."
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-mono font-semibold text-slate-800 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">اسم الـ Instance التوضيحي</label>
                    <input type="text" name="whatsapp_instance_name" id="wa_instance_name" value="{{ old('whatsapp_instance_name') }}"
                           placeholder="مثال: instance_aden"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-semibold text-slate-800 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">مفتاح API الخاص بالواتساب (API Key / Token)</label>
                    <input type="text" name="whatsapp_api_key" value="{{ old('whatsapp_api_key', config('whatsapp.global_api_key')) }}" dir="ltr"
                           placeholder="Global API Key أو Instance Token..."
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-mono font-semibold text-slate-800 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                </div>
            </div>
        </div>

        <!-- حالة التفعيل وأزرار الحفظ -->
        <div class="bg-white rounded-3xl p-6 border border-slate-200/90 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-4">
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="is_active" value="1" checked
                       class="w-5 h-5 rounded-md border-slate-300 text-secondary focus:ring-secondary">
                <div>
                    <span class="text-sm font-extrabold text-slate-800 block">تفعيل المكتب فور الإنشاء</span>
                    <span class="text-xs text-slate-400">يمكن للمكتب تسجيل الدخول فوراً بمجرد الحفظ</span>
                </div>
            </label>

            <div class="flex items-center gap-3 w-full sm:w-auto">
                <a href="{{ route('admin.offices.index') }}"
                   class="flex-1 sm:flex-none text-center px-6 py-3 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition-colors">
                    إلغاء
                </a>
                <button type="submit"
                        class="flex-1 sm:flex-none px-8 py-3 rounded-xl bg-secondary text-primary-950 text-xs font-extrabold hover:brightness-105 shadow-lg shadow-secondary/20 transition-all flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-lg">check</span>
                    <span>إنشاء وحفظ المكتب</span>
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
