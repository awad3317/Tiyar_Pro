@extends('admin.layouts.app')

@section('title', 'إدارة مميزات ' . $office->name)
@section('page-title', 'إدارة مميزات وخدمات: ' . $office->name)
@section('page-subtitle', 'التحكم في تفعيل أو إيقاف الخدمات والمميزات التفاعلية للمكتب وتحديث الكاش الذكي فورياً')

@section('header-actions')
    <a href="{{ route('admin.features.index') }}"
       class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-secondary text-primary-950 text-xs font-extrabold hover:brightness-105 transition-all shadow-sm">
        <span class="material-symbols-outlined text-lg">featured_play_list</span>
        <span>إدارة كتالوج المميزات</span>
    </a>
    <a href="{{ route('admin.offices.edit', $office) }}"
       class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 bg-white text-xs font-bold hover:bg-slate-50 transition-colors">
        <span class="material-symbols-outlined text-lg">tune</span>
        <span>إعدادات المكتب</span>
    </a>
    <a href="{{ route('admin.offices.index') }}"
       class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 bg-white text-xs font-bold hover:bg-slate-50 transition-colors">
        <span class="material-symbols-outlined text-lg">arrow_forward</span>
        <span>العودة للمكاتب</span>
    </a>
@endsection

@section('content')
<div class="max-w-5xl mx-auto space-y-6">

    <!-- بطاقة الهيدر العلوية للمكتب وحالته -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/90 shadow-xs flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
        <div class="flex items-center gap-4">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-primary to-slate-800 text-white flex items-center justify-center font-black text-2xl shadow-md shrink-0">
                {{ mb_substr($office->name, 0, 1) }}
            </div>
            <div>
                <div class="flex items-center gap-2.5 flex-wrap">
                    <h2 class="text-xl font-black text-slate-900">{{ $office->name }}</h2>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold {{ $office->is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200' }}">
                        {{ $office->is_active ? 'حساب نشط' : 'حساب محظور' }}
                    </span>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold {{ ($whatsappStatus['is_online'] ?? false) ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200' }}">
                        {{ ($whatsappStatus['is_online'] ?? false) ? 'واتساب متصل Online' : 'واتساب غير متصل Offline' }}
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-1">
                    {{ $office->branch_name ?: 'الفرع الرئيسي' }} &bull; هاتف الدخول: <span class="font-mono font-bold text-slate-700" dir="ltr">{{ $office->whatsapp_sender_phone }}</span>
                </p>
            </div>
        </div>

        <!-- إحصائية طرود المكتب السريعة -->
        <div class="flex items-center gap-3 bg-slate-50 p-2.5 rounded-2xl border border-slate-200/70">
            <div class="px-3 text-center border-l border-slate-200">
                <span class="block text-lg font-black text-slate-900">{{ $parcelsSummary['total'] }}</span>
                <span class="text-[11px] font-bold text-slate-400">إجمالي الطرود</span>
            </div>
            <div class="px-3 text-center border-l border-slate-200">
                <span class="block text-lg font-black text-secondary">{{ $parcelsSummary['today'] }}</span>
                <span class="text-[11px] font-bold text-slate-400">طرود اليوم</span>
            </div>
            <div class="px-3 text-center">
                <span class="block text-lg font-black text-emerald-600">{{ $parcelsSummary['delivered'] }}</span>
                <span class="text-[11px] font-bold text-slate-400">تم التسليم</span>
            </div>
        </div>
    </div>

    <!-- شريط التبويبات المتناسق مع صفحة تعديل المكتب -->
    <div class="flex items-center gap-2 border-b border-slate-200 bg-white px-4 rounded-2xl shadow-xs overflow-x-auto">
        <a href="{{ route('admin.offices.edit', ['office' => $office, 'tab' => 'general']) }}"
           class="flex items-center gap-2 py-4 px-4 border-b-2 border-transparent text-xs font-bold text-slate-500 hover:text-slate-700 transition-colors shrink-0">
            <span class="material-symbols-outlined text-lg">info</span>
            <span>المعلومات والطرود</span>
        </a>

        <a href="{{ route('admin.offices.edit', ['office' => $office, 'tab' => 'sms']) }}"
           class="flex items-center gap-2 py-4 px-4 border-b-2 border-transparent text-xs font-bold text-slate-500 hover:text-slate-700 transition-colors shrink-0">
            <span class="material-symbols-outlined text-lg">sms</span>
            <span>إعدادات وقالب SMS</span>
        </a>

        <a href="{{ route('admin.offices.edit', ['office' => $office, 'tab' => 'whatsapp']) }}"
           class="flex items-center gap-2 py-4 px-4 border-b-2 border-transparent text-xs font-bold text-slate-500 hover:text-slate-700 transition-colors shrink-0">
            <span class="material-symbols-outlined text-lg">chat</span>
            <span>إعدادات وجلسة الواتساب</span>
        </a>

        <a href="{{ route('admin.offices.features', $office) }}"
           class="flex items-center gap-2 py-4 px-4 border-b-2 border-secondary text-primary font-black text-xs transition-colors shrink-0">
            <span class="material-symbols-outlined text-lg text-secondary">featured_play_list</span>
            <span>إدارة المميزات والخدمات</span>
            <span class="px-1.5 py-0.5 rounded text-[10px] font-extrabold bg-secondary/15 text-secondary border border-secondary/30">جديد</span>
        </a>

        <a href="{{ route('admin.offices.edit', ['office' => $office, 'tab' => 'security']) }}"
           class="flex items-center gap-2 py-4 px-4 border-b-2 border-transparent text-xs font-bold text-slate-500 hover:text-slate-700 transition-colors shrink-0">
            <span class="material-symbols-outlined text-lg">security</span>
            <span>الأمان وكلمة المرور</span>
        </a>
    </div>

    <!-- صندوق معلومات الكاش الذكي -->
    <div class="bg-gradient-to-r from-slate-900 to-primary text-white rounded-3xl p-6 shadow-md border border-slate-800 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div class="flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-2xl bg-secondary/20 border border-secondary/30 text-secondary flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-2xl">bolt</span>
            </div>
            <div>
                <h3 class="text-sm font-extrabold text-white flex items-center gap-2">
                    <span>طبقة الكاش الذكي (Smart Cache Layer)</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                        مفعلة وتعمل
                    </span>
                </h3>
                <p class="text-xs text-slate-300 mt-0.5">
                    مفتاح كاش هذا المكتب: <code class="font-mono text-secondary bg-black/30 px-2 py-0.5 rounded text-[11px]">{{ $office->getFeaturesCacheKey() }}</code> &bull; يتم تحديثه لحظياً وبدون أي تأخير عند الضغط على "حفظ التعديلات".
                </p>
            </div>
        </div>

        <div class="shrink-0 flex items-center gap-2 text-xs font-bold text-slate-300 bg-white/5 px-3.5 py-2 rounded-xl border border-white/10">
            <span class="material-symbols-outlined text-emerald-400 text-base">cached</span>
            <span>Zero DB Queries بالواجهات</span>
        </div>
    </div>

    <!-- نموذج حفظ التعديلات لجميع المميزات -->
    <form method="POST" action="{{ route('admin.offices.features.update', $office) }}" class="space-y-6">
        @csrf
        @method('PUT')

        @php
            $categoryMeta = [
                'ui_tab' => [
                    'label' => 'تبويبات وعناصر الواجهة (UI Tabs)',
                    'desc'  => 'التحكم في ظهور أو إخفاء التبويبات والأقسام في واجهة الـ PWA الخاصة بفرع المكتب',
                    'icon'  => 'tab',
                    'badge' => 'bg-purple-50 text-purple-700 border-purple-200',
                ],
                'notification' => [
                    'label' => 'الإشعارات والتنبيهات التلقائية (Notifications)',
                    'desc'  => 'التحكم في خدمات إرسال الإشعارات والرسائل التلقائية عبر SMS أو القنوات الأخرى',
                    'icon'  => 'notifications_active',
                    'badge' => 'bg-sky-50 text-sky-700 border-sky-200',
                ],
                'general' => [
                    'label' => 'المميزات والخدمات العامة (General)',
                    'desc'  => 'الخدمات العامة والإعدادات المتقدمة لتشغيل المكتب',
                    'icon'  => 'tune',
                    'badge' => 'bg-amber-50 text-amber-700 border-amber-200',
                ],
            ];

            $groupedFeatures = $features->groupBy('category');
        @endphp

        @forelse ($groupedFeatures as $category => $categoryFeatures)
            @php
                $meta = $categoryMeta[$category] ?? [
                    'label' => $category,
                    'desc'  => 'مميزات مصنفة تحت هذا القسم',
                    'icon'  => 'category',
                    'badge' => 'bg-slate-50 text-slate-700 border-slate-200',
                ];
            @endphp

            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/90 shadow-xs space-y-6">
                <!-- رأس التصنيف -->
                <div class="flex items-start justify-between pb-4 border-b border-slate-100 gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-xl">{{ $meta['icon'] }}</span>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-extrabold text-slate-900">{{ $meta['label'] }}</h3>
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold border {{ $meta['badge'] }}">
                                    {{ $categoryFeatures->count() }} ميزة
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5">{{ $meta['desc'] }}</p>
                        </div>
                    </div>
                </div>

                <!-- قائمة بطاقات المميزات داخل التصنيف -->
                <div class="space-y-4">
                    @foreach ($categoryFeatures as $feature)
                        @php
                            $isEnabled = (bool) ($currentDictionary[$feature->code]['is_enabled'] ?? false);
                            $featureConfig = $currentDictionary[$feature->code]['config'] ?? null;
                        @endphp

                        <div class="p-5 rounded-2xl border transition-all duration-200 {{ $isEnabled ? 'bg-emerald-50/20 border-emerald-200/80 shadow-xs' : 'bg-slate-50/60 border-slate-200/80' }} flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                            <div class="space-y-1.5 flex-1 pr-1">
                                <div class="flex items-center gap-2.5 flex-wrap">
                                    <h4 class="text-sm font-black text-slate-900">{{ $feature->name }}</h4>
                                    <code class="px-2 py-0.5 rounded-md text-[11px] font-mono font-bold bg-slate-200/70 text-slate-700 border border-slate-300/60" dir="ltr">
                                        {{ $feature->code }}
                                    </code>

                                    @if ($isEnabled)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                                            <span>مفعلة حالياً للمكتب</span>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-200 text-slate-600">
                                            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                            <span>معطلة للمكتب</span>
                                        </span>
                                    @endif
                                </div>

                                @if ($feature->description)
                                    <p class="text-xs text-slate-600 leading-relaxed">{{ $feature->description }}</p>
                                @endif

                                @if ($featureConfig && is_array($featureConfig))
                                    <div class="mt-2 text-[11px] font-mono text-slate-500 bg-white/70 p-2 rounded-lg border border-slate-200/60 inline-block" dir="ltr">
                                        config: {{ json_encode($featureConfig, JSON_UNESCAPED_UNICODE) }}
                                    </div>
                                @endif
                            </div>

                            <!-- مفتاح الاختيار التفاعلي (Toggle Switch) -->
                            <div class="flex items-center gap-3 self-end sm:self-center shrink-0">
                                <span class="text-xs font-bold {{ $isEnabled ? 'text-emerald-700' : 'text-slate-400' }}">
                                    {{ $isEnabled ? 'مفعلة' : 'معطلة' }}
                                </span>

                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox"
                                           name="features[{{ $feature->code }}]"
                                           value="1"
                                           {{ $isEnabled ? 'checked' : '' }}
                                           class="sr-only peer">
                                    <div class="w-12 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500 shadow-inner"></div>
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="bg-white rounded-3xl p-12 text-center border border-slate-200">
                <span class="material-symbols-outlined text-4xl text-slate-300 mb-2">extension_off</span>
                <p class="text-sm font-bold text-slate-600">لا توجد أي مميزات مسجلة في النظام حالياً.</p>
                <p class="text-xs text-slate-400 mt-1">يرجى تشغيل الـ FeatureSeeder لإضافة المميزات الأساسية.</p>
            </div>
        @endforelse

        <!-- شريط حفظ التعديلات العائم بالأسفل -->
        <div class="sticky bottom-4 z-20 bg-white/95 backdrop-blur-md p-4 rounded-2xl border border-slate-200 shadow-xl flex items-center justify-between">
            <div class="flex items-center gap-2 text-xs font-bold text-slate-500">
                <span class="material-symbols-outlined text-amber-500 text-lg">info</span>
                <span>اختر المميزات المراد تفعيلها أو تعطيلها ثم اضغط حفظ التعديلات لتحديث الكاش فورياً.</span>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('admin.offices.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition-colors">
                    إلغاء
                </a>
                <button type="submit" class="px-8 py-2.5 rounded-xl bg-secondary text-primary-950 font-extrabold text-xs hover:brightness-105 shadow-md shadow-secondary/20 flex items-center gap-2 transition-all">
                    <span class="material-symbols-outlined text-lg">save</span>
                    <span>حفظ التعديلات</span>
                </button>
            </div>
        </div>
    </form>

</div>
@endsection

