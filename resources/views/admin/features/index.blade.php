@extends('admin.layouts.app')

@section('title', 'إدارة كتالوج المميزات (Features)')
@section('page-title', 'كتالوج وإدارة المميزات العامة')
@section('page-subtitle', 'إضافة مميزات جديدة ومزامنتها تلقائياً مع كافة المكاتب، ومتابعة معدلات التفعيل')

@section('header-actions')
    <!-- زر المزامنة الشاملة -->
    <form method="POST" action="{{ route('admin.features.sync_all') }}" class="inline"
          onsubmit="return confirm('هل أنت متأكد من رغبتك في مزامنة جميع المميزات مع كافة المكاتب؟ (سيتم ربط أي ميزة ناقصة كـ غير مفعلة ولن تتأثر المميزات المفعلة مسبقاً)');">
        @csrf
        <button type="submit"
                class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 bg-white text-xs font-bold hover:bg-slate-50 transition-colors shadow-xs">
            <span class="material-symbols-outlined text-lg text-emerald-600">sync</span>
            <span>مزامنة المميزات مع جميع المكاتب</span>
        </button>
    </form>

    <!-- زر فتح نافذة إضافة ميزة جديدة -->
    <button type="button" @click="openAddModal()"
            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-secondary text-primary-950 text-xs font-extrabold hover:brightness-105 transition-all shadow-sm">
        <span class="material-symbols-outlined text-lg">add_circle</span>
        <span>إضافة ميزة جديدة</span>
    </button>
@endsection

@section('content')
<div class="space-y-6" x-data="featuresManager()">

    <!-- بطاقات المؤشرات العلوية لحالة وكتالوج المميزات -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- إجمالي المميزات -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-xs flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-1">إجمالي المميزات المسجلة</span>
                <span class="text-2xl font-black text-slate-900">{{ $categoryStats['total'] }}</span>
            </div>
            <div class="w-11 h-11 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                <span class="material-symbols-outlined text-2xl">featured_play_list</span>
            </div>
        </div>

        <!-- تبويبات وعناصر الواجهة -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-xs flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-1">تبويبات الواجهة (UI Tabs)</span>
                <span class="text-2xl font-black text-purple-700">{{ $categoryStats['ui_tab'] }}</span>
            </div>
            <div class="w-11 h-11 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                <span class="material-symbols-outlined text-2xl">tab</span>
            </div>
        </div>

        <!-- الإشعارات والتنبيهات -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-xs flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-1">الإشعارات والتنبيهات</span>
                <span class="text-2xl font-black text-sky-700">{{ $categoryStats['notification'] }}</span>
            </div>
            <div class="w-11 h-11 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center">
                <span class="material-symbols-outlined text-2xl">notifications_active</span>
            </div>
        </div>

        <!-- إجمالي المكاتب المربوطة -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-xs flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-1">المكاتب المتاحة للمزامنة</span>
                <div class="flex items-center gap-2">
                    <span class="text-2xl font-black text-emerald-600">{{ $totalOfficesCount }}</span>
                    <span class="text-xs font-bold text-slate-400">مكتب</span>
                </div>
            </div>
            <div class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                <span class="material-symbols-outlined text-2xl">domain</span>
            </div>
        </div>
    </div>

    <!-- صندوق التوجيه والشرح السريع للربط البرمجي والكاش -->
    <div class="bg-gradient-to-r from-slate-900 to-primary text-white rounded-3xl p-6 shadow-md border border-slate-800 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div class="flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-2xl bg-secondary/20 border border-secondary/30 text-secondary flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-2xl">extension</span>
            </div>
            <div>
                <h3 class="text-sm font-extrabold text-white flex items-center gap-2">
                    <span>نظام المميزات المخصص للمكاتب (Office Features Engine)</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                        مزامنة تلقائية 100%
                    </span>
                </h3>
                <p class="text-xs text-slate-300 mt-1">
                    عند إنشاء ميزة جديدة، يتم ربطها فوراً بكافة المكاتب الموجودة بقيمة <code class="font-mono text-secondary bg-black/40 px-1.5 py-0.5 rounded text-[11px]">is_enabled = false</code> ومسح كاش المكاتب، لتتمكن من تفعيلها يدوياً لكل مكتب من صفحة تعديل ميزاته.
                </p>
            </div>
        </div>

        <div class="shrink-0 flex items-center gap-2">
            <a href="{{ route('admin.offices.index') }}"
               class="px-4 py-2 rounded-xl text-xs font-bold text-slate-200 bg-white/10 hover:bg-white/15 border border-white/15 transition-all flex items-center gap-1.5">
                <span class="material-symbols-outlined text-base">domain</span>
                <span>تفعيل المميزات للمكاتب</span>
            </a>
        </div>
    </div>

    <!-- استعراض المميزات حسب التصنيفات -->
    @php
        $categoryMeta = [
            'ui_tab' => [
                'label' => 'تبويبات وعناصر الواجهة (UI Tabs)',
                'desc'  => 'مميزات تتحكم في إظهار أو إخفاء أقسام وتبويبات في واجهة الـ PWA الخاصة بالمكتب (مثل تبويب العملاء المميزين)',
                'icon'  => 'tab',
                'badge' => 'bg-purple-50 text-purple-700 border-purple-200',
            ],
            'notification' => [
                'label' => 'الإشعارات والتنبيهات التلقائية (Notifications)',
                'desc'  => 'خدمات إرسال الإشعارات والرسائل التلقائية عبر SMS أو WhatsApp عند تغيير حالات الطرود',
                'icon'  => 'notifications_active',
                'badge' => 'bg-sky-50 text-sky-700 border-sky-200',
            ],
            'general' => [
                'label' => 'المميزات والخدمات العامة (General)',
                'desc'  => 'المميزات العامة، الوظائف التشغيلية، والإعدادات المتقدمة للمكاتب',
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
                'desc'  => 'مميزات مسجلة تحت هذا التصنيف',
                'icon'  => 'category',
                'badge' => 'bg-slate-50 text-slate-700 border-slate-200',
            ];
        @endphp

        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/90 shadow-xs space-y-5">
            <!-- رأس التصنيف -->
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
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

            <!-- جدول أو بطاقات المميزات داخل التصنيف -->
            <div class="space-y-3.5">
                @foreach ($categoryFeatures as $feature)
                    @php
                        $percentage = $totalOfficesCount > 0 ? round(($feature->enabled_offices_count / $totalOfficesCount) * 100) : 0;
                    @endphp

                    <div class="p-5 rounded-2xl border border-slate-200/80 bg-slate-50/50 hover:bg-white hover:border-slate-300 transition-all flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                        <!-- بيانات الميزة -->
                        <div class="space-y-1.5 flex-1 pr-1">
                            <div class="flex items-center gap-2.5 flex-wrap">
                                <h4 class="text-sm font-black text-slate-900">{{ $feature->name }}</h4>
                                <code class="px-2.5 py-0.5 rounded-md text-[11px] font-mono font-bold bg-slate-200/70 text-slate-800 border border-slate-300/70" dir="ltr">
                                    {{ $feature->code }}
                                </code>
                            </div>

                            @if ($feature->description)
                                <p class="text-xs text-slate-600 leading-relaxed max-w-3xl">{{ $feature->description }}</p>
                            @else
                                <p class="text-xs text-slate-400 italic">لا يوجد وصف مدخل لهذه الميزة.</p>
                            @endif

                            <div class="text-[11px] text-slate-400 pt-1 flex items-center gap-2">
                                <span>التحقق البرمجي:</span>
                                <code class="font-mono text-slate-600 bg-white px-2 py-0.5 rounded border border-slate-200" dir="ltr">
                                    $office->hasFeature('{{ $feature->code }}')
                                </code>
                            </div>
                        </div>

                        <!-- إحصائية تفعيل الميزة للمكاتب -->
                        <div class="flex items-center gap-4 shrink-0 w-full md:w-auto justify-between md:justify-end pt-3 md:pt-0 border-t md:border-t-0 border-slate-200/60">
                            <div class="text-right">
                                <div class="flex items-center gap-1.5 justify-end">
                                    <span class="text-xs font-black text-slate-900">{{ $feature->enabled_offices_count }}</span>
                                    <span class="text-xs text-slate-400 font-semibold">من أصل {{ $totalOfficesCount }} مكتب</span>
                                </div>
                                <div class="w-32 bg-slate-200 rounded-full h-1.5 mt-1.5 overflow-hidden">
                                    <div class="bg-emerald-500 h-1.5 rounded-full transition-all" style="width: {{ $percentage }}%"></div>
                                </div>
                                <span class="text-[10px] text-slate-400 block mt-0.5 font-bold">{{ $percentage }}% نسبة التفعيل</span>
                            </div>

                            <!-- زر التعديل -->
                            <button type="button"
                                    @click="openEditModal({{ json_encode($feature) }})"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-200 text-slate-700 bg-white text-xs font-bold hover:bg-slate-50 transition-colors shadow-xs">
                                <span class="material-symbols-outlined text-base text-slate-500">edit</span>
                                <span>تعديل</span>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="bg-white rounded-3xl p-12 text-center border border-slate-200">
            <span class="material-symbols-outlined text-4xl text-slate-300 mb-2">extension_off</span>
            <p class="text-sm font-bold text-slate-600">لا توجد أي مميزات مسجلة في النظام حالياً.</p>
            <p class="text-xs text-slate-400 mt-1">اضغط على زر "إضافة ميزة جديدة" بالأعلى لإضافة الميزة الأولى.</p>
        </div>
    @endforelse

    <!-- نافذة إضافة ميزة جديدة (Add Feature Modal) -->
    <div x-show="addModal.show"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        
        <div @click.outside="addModal.show = false"
             class="bg-white rounded-3xl max-w-xl w-full p-6 sm:p-8 shadow-2xl border border-slate-200">
            
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-5">
                <div class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-xl bg-secondary/15 text-secondary flex items-center justify-center">
                        <span class="material-symbols-outlined text-2xl">add_box</span>
                    </div>
                    <div>
                        <h3 class="text-base font-extrabold text-slate-900">إضافة ميزة جديدة ومزامنتها</h3>
                        <p class="text-xs text-slate-500">سيتم ربط الميزة تلقائياً بجميع المكاتب بحالة معطلة (false)</p>
                    </div>
                </div>
                <button type="button" @click="addModal.show = false" class="text-slate-400 hover:text-slate-600">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>

            <form method="POST" action="{{ route('admin.features.store') }}" class="space-y-4">
                @csrf

                <!-- اسم الميزة -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        اسم الميزة التوضيحي <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           placeholder="مثال: إرسال SMS عند تسليم الطرد"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-bold text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                </div>

                <!-- الكود البرمجي (Code) -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        الكود البرمجي (Code) <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="code" value="{{ old('code') }}" required dir="ltr"
                           placeholder="مثال: sms_on_delivery"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-mono font-bold text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                    <p class="text-[11px] text-slate-400 mt-1">
                        أحرف إنجليزية صغيرة مع أرقام أو شرطة سفلية (مثل <code class="text-slate-600 font-mono">vip_customers_tab</code>). يُستخدم في الفحص البرمجي <code class="font-mono text-slate-600">$office->hasFeature('code')</code> ولا يمكن تغييره بعد الإنشاء.
                    </p>
                </div>

                <!-- تصنيف الميزة -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        تصنيف الميزة <span class="text-rose-500">*</span>
                    </label>
                    <select name="category" required
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-bold text-slate-700 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                        <option value="ui_tab" {{ old('category') === 'ui_tab' ? 'selected' : '' }}>تبويب وعنصر واجهة (UI Tab)</option>
                        <option value="notification" {{ old('category') === 'notification' ? 'selected' : '' }}>إشعار ورسائل تلقائية (Notification)</option>
                        <option value="general" {{ old('category', 'general') === 'general' ? 'selected' : '' }}>ميزة عامة (General)</option>
                    </select>
                </div>

                <!-- الوصف -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        وصف الميزة واستخدامها (اختياري)
                    </label>
                    <textarea name="description" rows="3"
                              placeholder="اكتب شرحاً مختصراً يوضح الهدف من هذه الميزة للمسؤولين..."
                              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-medium text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-secondary transition-all">{{ old('description') }}</textarea>
                </div>

                <!-- تنبيه المزامنة والكاش -->
                <div class="p-3.5 rounded-xl bg-amber-50/80 border border-amber-200/80 flex items-start gap-2.5 text-xs text-amber-900">
                    <span class="material-symbols-outlined text-amber-600 text-lg shrink-0">info</span>
                    <p class="leading-relaxed">
                        بمجرد الضغط على الحفظ، سيتم إدراج هذه الميزة في قاعدة البيانات وربطها بجميع المكاتب (<strong>{{ $totalOfficesCount }} مكتب</strong>) مع ضبط قيمتها على <code class="font-mono font-bold">false</code> ومسح الكاش الذكي فوراً.
                    </p>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                    <button type="button" @click="addModal.show = false"
                            class="px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50">
                        إلغاء
                    </button>
                    <button type="submit"
                            class="px-6 py-2.5 rounded-xl bg-secondary text-primary-950 text-xs font-extrabold hover:brightness-105 shadow-sm flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-base">save</span>
                        <span>إضافة ومزامنة مع المكاتب</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- نافذة تعديل بيانات ميزة (Edit Feature Modal) -->
    <div x-show="editModal.show"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        
        <div @click.outside="editModal.show = false"
             class="bg-white rounded-3xl max-w-xl w-full p-6 sm:p-8 shadow-2xl border border-slate-200">
            
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-5">
                <div class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center">
                        <span class="material-symbols-outlined text-2xl">edit_note</span>
                    </div>
                    <div>
                        <h3 class="text-base font-extrabold text-slate-900">تعديل بيانات الميزة</h3>
                        <p class="text-xs text-slate-500">تعديل الاسم والتصنيف والوصف (الكود البرمجي ثابت)</p>
                    </div>
                </div>
                <button type="button" @click="editModal.show = false" class="text-slate-400 hover:text-slate-600">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>

            <form method="POST" :action="editModal.action" class="space-y-4">
                @csrf
                @method('PUT')

                <!-- عرض الكود البرمجي (للقراءة فقط) -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        الكود البرمجي (ثابت غير قابل للتغيير)
                    </label>
                    <div class="bg-slate-100 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-mono font-bold text-slate-600" dir="ltr" x-text="editModal.code">
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">لا يمكن تعديل الكود البرمجي لأنه مرتبط مباشرة بفحوصات الكود في المنظومة.</p>
                </div>

                <!-- اسم الميزة -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        اسم الميزة التوضيحي <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="name" x-model="editModal.name" required
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-bold text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                </div>

                <!-- تصنيف الميزة -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        تصنيف الميزة <span class="text-rose-500">*</span>
                    </label>
                    <select name="category" x-model="editModal.category" required
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-bold text-slate-700 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                        <option value="ui_tab">تبويب وعنصر واجهة (UI Tab)</option>
                        <option value="notification">إشعار ورسائل تلقائية (Notification)</option>
                        <option value="general">ميزة عامة (General)</option>
                    </select>
                </div>

                <!-- الوصف -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        وصف الميزة واستخدامها
                    </label>
                    <textarea name="description" rows="3" x-model="editModal.description"
                              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-medium text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-secondary transition-all"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                    <button type="button" @click="editModal.show = false"
                            class="px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50">
                        إلغاء
                    </button>
                    <button type="submit"
                            class="px-6 py-2.5 rounded-xl bg-secondary text-primary-950 text-xs font-extrabold hover:brightness-105 shadow-sm flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-base">save</span>
                        <span>حفظ التعديلات وتحديث الكاش</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
    function featuresManager() {
        return {
            addModal: {
                show: {{ ($errors->any() && !old('_method')) ? 'true' : 'false' }},
            },
            editModal: {
                show: false,
                action: '',
                name: '',
                code: '',
                category: 'general',
                description: '',
            },

            openAddModal() {
                this.addModal.show = true;
            },

            openEditModal(feature) {
                this.editModal.action = `{{ url('admin/features') }}/${feature.id}`;
                this.editModal.name = feature.name || '';
                this.editModal.code = feature.code || '';
                this.editModal.category = feature.category || 'general';
                this.editModal.description = feature.description || '';
                this.editModal.show = true;
            }
        };
    }
</script>
@endpush

