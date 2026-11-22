@extends('admin.layouts.app')

@section('title', 'إدارة وتعديل ' . $office->name)
@section('page-title', 'إدارة وتعديل: ' . $office->name)
@section('page-subtitle', 'التحكم بكافة إعدادات المكتب، الهوية والسندات، قوالب الإشعار، وحالة جلسة الواتساب المباشرة')

@section('header-actions')
    <a href="{{ route('admin.favorite_customers.index', ['office_id' => $office->id]) }}"
       class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-amber-300 text-amber-800 bg-amber-50 text-xs font-bold hover:bg-amber-100 transition-colors shadow-2xs">
        <span class="material-symbols-outlined text-base text-amber-600">stars</span>
        <span>العملاء المميزين ({{ $office->favoriteCustomers()->count() }})</span>
    </a>
    <a href="{{ route('admin.offices.index') }}"
       class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 bg-white text-xs font-bold hover:bg-slate-50 transition-colors">
        <span class="material-symbols-outlined text-lg">arrow_forward</span>
        <span>العودة للمكاتب</span>
    </a>
@endsection

@section('content')
<div class="max-w-5xl mx-auto space-y-6" x-data="officeEditor({{ $office->id }})">

    <!-- بطاقة الهيدر العلوية للمكتب وحالته -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/90 shadow-xs flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
        <div class="flex items-center gap-4">
            <div class="w-16 h-16 rounded-2xl text-white flex items-center justify-center font-black text-2xl shadow-md shrink-0 overflow-hidden border border-slate-200"
                 style="background-color: {{ $resolvedProfile['primary_color'] ?? '#041627' }};">
                @if (!empty($resolvedProfile['icon_url'] ?? $resolvedProfile['logo_url']))
                    <img src="{{ $resolvedProfile['icon_url'] ?? $resolvedProfile['logo_url'] }}"
                         alt="{{ $office->name }}"
                         class="w-full h-full object-contain p-1.5 bg-white/95">
                @else
                    {{ mb_substr($office->name, 0, 1) }}
                @endif
            </div>
            <div>
                <div class="flex items-center gap-2.5 flex-wrap">
                    <h2 class="text-xl font-black text-slate-900">{{ $office->name }}</h2>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold {{ $office->is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200' }}">
                        {{ $office->is_active ? 'حساب نشط' : 'حساب محظور' }}
                    </span>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold"
                          :class="liveStatus.connected ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'"
                          x-text="liveStatus.connected ? 'واتساب متصل Online' : 'واتساب غير متصل Offline'"></span>
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

    <!-- شريط التبويبات (Tabs Bar) -->
    <div class="flex items-center gap-2 border-b border-slate-200 bg-white px-4 rounded-2xl shadow-xs overflow-x-auto">
        <button type="button" @click="activeTab = 'general'"
                :class="activeTab === 'general' ? 'border-secondary text-primary font-black' : 'border-transparent text-slate-500 font-bold hover:text-slate-700'"
                class="flex items-center gap-2 py-4 px-4 border-b-2 text-xs transition-colors shrink-0">
            <span class="material-symbols-outlined text-lg">info</span>
            <span>المعلومات والطرود</span>
        </button>

        <button type="button" @click="activeTab = 'branding'"
                :class="activeTab === 'branding' ? 'border-secondary text-primary font-black' : 'border-transparent text-slate-500 font-bold hover:text-slate-700'"
                class="flex items-center gap-2 py-4 px-4 border-b-2 text-xs transition-colors shrink-0">
            <span class="material-symbols-outlined text-lg">palette</span>
            <span>الهوية والسندات</span>
        </button>

        <button type="button" @click="activeTab = 'sms'"
                :class="activeTab === 'sms' ? 'border-secondary text-primary font-black' : 'border-transparent text-slate-500 font-bold hover:text-slate-700'"
                class="flex items-center gap-2 py-4 px-4 border-b-2 text-xs transition-colors shrink-0">
            <span class="material-symbols-outlined text-lg">sms</span>
            <span>إعدادات وقالب SMS</span>
        </button>

        <button type="button" @click="activeTab = 'whatsapp'"
                :class="activeTab === 'whatsapp' ? 'border-secondary text-primary font-black' : 'border-transparent text-slate-500 font-bold hover:text-slate-700'"
                class="flex items-center gap-2 py-4 px-4 border-b-2 text-xs transition-colors shrink-0">
            <span class="material-symbols-outlined text-lg">chat</span>
            <span>إعدادات وجلسة الواتساب</span>
        </button>

        <a href="{{ route('admin.offices.features', $office) }}"
           class="flex items-center gap-2 py-4 px-4 border-b-2 border-transparent text-xs font-bold text-slate-500 hover:text-secondary hover:border-secondary transition-colors shrink-0">
            <span class="material-symbols-outlined text-lg text-secondary">featured_play_list</span>
            <span>إدارة المميزات والخدمات</span>
            <span class="px-1.5 py-0.5 rounded text-[10px] font-extrabold bg-secondary/15 text-secondary border border-secondary/30">جديد</span>
        </a>

        <button type="button" @click="activeTab = 'security'"
                :class="activeTab === 'security' ? 'border-secondary text-primary font-black' : 'border-transparent text-slate-500 font-bold hover:text-slate-700'"
                class="flex items-center gap-2 py-4 px-4 border-b-2 text-xs transition-colors shrink-0">
            <span class="material-symbols-outlined text-lg">security</span>
            <span>الأمان وكلمة المرور</span>
        </button>
    </div>

    <!-- نموذج التعديل الشامل -->
    <form method="POST" action="{{ route('admin.offices.update', $office) }}" id="officeForm" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')
        <input type="hidden" name="active_tab" :value="activeTab">

        <!-- ========================================== -->
        <!-- التبويب 1: المعلومات الأساسية وحركة الطرود -->
        <!-- ========================================== -->
        <div x-show="activeTab === 'general'" x-cloak class="space-y-6">
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/90 shadow-xs space-y-6">
                <h3 class="text-base font-extrabold text-slate-900 pb-3 border-b border-slate-100">البيانات الإدارية للمكتب</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">اسم المكتب</label>
                        <input type="text" name="name" value="{{ old('name', $office->name) }}" required
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-semibold text-slate-800 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">الفرع / المدينة</label>
                        <input type="text" name="branch_name" value="{{ old('branch_name', $office->branch_name) }}"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-semibold text-slate-800 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">رقم هاتف الواتساب المعتمد (تسجيل دخول المكتب)</label>
                        <input type="tel" name="whatsapp_sender_phone" value="{{ old('whatsapp_sender_phone', $office->whatsapp_sender_phone) }}" required dir="ltr"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-mono font-semibold text-slate-800 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">رقم شريحة الـ SMS المسجلة (+967...)</label>
                        <input type="text" name="httpsms_from_phone" value="{{ old('httpsms_from_phone', $office->httpsms_from_phone) }}" required dir="ltr"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-mono font-semibold text-slate-800 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                    </div>
                </div>
            </div>

            <!-- بطاقة التقرير اليومي التلقائي لمدير الفرع عبر الواتساب (10:00 مساءً) -->
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/90 shadow-xs space-y-6"
                 x-data="{
                     dailyReportActive: {{ $office->send_daily_report ? 'true' : 'false' }},
                     testLoading: false,
                     testFeedback: null,
                     testSuccess: null,
                     sendTestReport() {
                         const phoneInput = document.getElementById('manager_phone_input');
                         if (!phoneInput || !phoneInput.value.trim()) {
                             alert('يرجى إدخال رقم هاتف مدير الفرع أولاً وحفظ البيانات.');
                             return;
                         }
                         this.testLoading = true;
                         this.testFeedback = null;
                         fetch('{{ route('admin.offices.send_test_daily_report', $office) }}', {
                             method: 'POST',
                             headers: {
                                 'Content-Type': 'application/json',
                                 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                 'Accept': 'application/json'
                             }
                         })
                         .then(r => r.json())
                         .then(data => {
                             this.testLoading = false;
                             this.testSuccess = data.success;
                             this.testFeedback = data.message;
                         })
                         .catch(err => {
                             this.testLoading = false;
                             this.testSuccess = false;
                             this.testFeedback = 'حدث خطأ في الاتصال بالخادم أثناء الإرسال التجريبي.';
                         });
                     }
                 }">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 flex-wrap gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
                            <span class="material-symbols-outlined text-xl">analytics</span>
                        </div>
                        <div>
                            <h3 class="text-base font-extrabold text-slate-900">التقرير اليومي لمدير الفرع (الساعة 10:00 مساءً)</h3>
                            <p class="text-xs text-slate-500">إرسال تقرير ملخص لحركة الطرود (المستلمة، المسلمة، والمرجعة) كل ليلة عبر الواتساب</p>
                        </div>
                    </div>

                    <!-- التوقل Toggle -->
                    <div class="flex items-center gap-3 bg-slate-50 px-4 py-2 rounded-2xl border border-slate-200/80">
                        <span class="text-xs font-extrabold text-slate-700" x-text="dailyReportActive ? 'التقرير اليومي: مفعّل ✅' : 'التقرير اليومي: معطّل ❌'"></span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="send_daily_report" value="1"
                                   x-model="dailyReportActive"
                                   class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                        </label>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 items-end">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">رقم هاتف مدير الفرع (الواتساب)</label>
                        <input type="tel" name="manager_phone" id="manager_phone_input"
                               value="{{ old('manager_phone', $office->manager_phone) }}" dir="ltr"
                               placeholder="مثال: 770000000 أو 967770000000"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-mono font-semibold text-slate-800 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                        <p class="text-[11px] text-slate-400 mt-1">يُشترط تفعيل التوقل أعلاه وإدخال رقم المدير لإرسال التقرير تلقائياً الساعة 10 مساءً.</p>
                    </div>

                    <div>
                        <button type="button" @click="sendTestReport()" :disabled="testLoading"
                                class="w-full sm:w-auto px-5 py-3 rounded-xl bg-slate-800 hover:bg-slate-900 text-white text-xs font-extrabold flex items-center justify-center gap-2 transition-all disabled:opacity-50 shadow-sm">
                            <span class="material-symbols-outlined text-base" :class="{'animate-spin': testLoading}">send</span>
                            <span x-text="testLoading ? 'جاري إرسال التقرير التجريبي...' : 'إرسال تقرير تجريبي لمدير الفرع الآن'"></span>
                        </button>
                    </div>
                </div>

                <!-- إشعار نتيجة الإرسال التجريبي -->
                <div x-show="testFeedback" x-cloak
                     class="p-4 rounded-2xl text-xs font-bold flex items-center gap-2 transition-all"
                     :class="testSuccess ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'">
                    <span class="material-symbols-outlined text-lg" x-text="testSuccess ? 'check_circle' : 'error'"></span>
                    <span x-text="testFeedback"></span>
                </div>
            </div>

            <!-- بطاقة الفروع والمكاتب المرتبطة (للطرود الصادرة - ربط ثنائي الاتجاه) -->
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/90 shadow-xs space-y-5"
                 x-data="{
                     partnerSearch: '',
                     selectedPartnerIds: @js(array_map('strval', $linkedPartnerIds ?? [])),
                     matchesPartner(label) {
                         const q = (this.partnerSearch || '').trim().toLowerCase();
                         if (!q) return true;
                         return String(label || '').toLowerCase().includes(q);
                     }
                 }">
                <input type="hidden" name="partner_links_submitted" value="1">

                <div class="flex items-center justify-between pb-3 border-b border-slate-100 flex-wrap gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
                            <span class="material-symbols-outlined text-xl">hub</span>
                        </div>
                        <div>
                            <h3 class="text-base font-extrabold text-slate-900">الفروع والمكاتب المرتبطة (للطرود الصادرة)</h3>
                            <p class="text-xs text-slate-500">ربط تبادلي ثنائي الاتجاه: أي مكتب تحدده هنا يظهر لهذا المكتب في وجهة الطرود الصادرة، ويظهر هذا المكتب لديهم تلقائياً</p>
                        </div>
                    </div>

                    <span class="px-3 py-1 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200 text-xs font-extrabold">
                        <span x-text="selectedPartnerIds.length"></span> مكتب مرتبط
                    </span>
                </div>

                @if(isset($allOtherOffices) && $allOtherOffices->isNotEmpty())
                    <div class="relative">
                        <input type="text"
                               x-model="partnerSearch"
                               placeholder="ابحث عن مكتب أو فرع للربط السريع..."
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl pr-9 pl-4 py-2.5 text-xs font-bold text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                        <span class="material-symbols-outlined text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 text-base pointer-events-none">search</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 max-h-72 overflow-y-auto p-1">
                        @foreach ($allOtherOffices as $otherOffice)
                            @php
                                $otherLabel = $otherOffice->branch_name
                                    ? ($otherOffice->name . ' - ' . $otherOffice->branch_name)
                                    : $otherOffice->name;
                            @endphp
                            <label x-show="matchesPartner(@js($otherLabel))"
                                   class="flex items-center justify-between gap-2.5 p-3 rounded-2xl border cursor-pointer transition-all"
                                   :class="selectedPartnerIds.includes('{{ $otherOffice->id }}')
                                       ? 'bg-indigo-50/70 border-indigo-300 text-indigo-950 shadow-2xs'
                                       : 'bg-slate-50/70 border-slate-200/80 text-slate-700 hover:bg-slate-100/70'">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <input type="checkbox"
                                           name="partner_office_ids[]"
                                           value="{{ $otherOffice->id }}"
                                           x-model="selectedPartnerIds"
                                           class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300 shrink-0">
                                    <span class="text-xs font-extrabold truncate">{{ $otherLabel }}</span>
                                </div>

                                @if (! $otherOffice->is_active)
                                    <span class="px-1.5 py-0.5 rounded bg-rose-50 text-rose-600 border border-rose-200 text-[10px] font-bold shrink-0">
                                        غير نشط
                                    </span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-slate-400 font-bold text-center py-4">لا توجد مكاتب أخرى مسجلة في النظام حالياً للربط معها.</p>
                @endif
            </div>

            <!-- أحدث طرود هذا المكتب -->
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/90 shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-base font-extrabold text-slate-900">أحدث طرود المكتب (آخر 6 طرود)</h3>
                    <a href="{{ route('admin.parcels.index', ['office_id' => $office->id]) }}" class="text-xs font-bold text-secondary hover:underline flex items-center gap-1">
                        <span>عرض كافة طرود المكتب ({{ $parcelsSummary['total'] }})</span>
                        <span class="material-symbols-outlined text-sm">arrow_left</span>
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead>
                            <tr class="text-slate-400 font-extrabold border-b border-slate-100">
                                <th class="py-2.5 px-3">رقم السند</th>
                                <th class="py-2.5 px-3">المستلم</th>
                                <th class="py-2.5 px-3">نوع الطرد</th>
                                <th class="py-2.5 px-3">المنطقة / المصدر</th>
                                <th class="py-2.5 px-3 text-center">الحالة</th>
                                <th class="py-2.5 px-3">تاريخ الوصول</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-semibold">
                            @forelse ($recentParcels as $parcel)
                                <tr class="hover:bg-slate-50">
                                    <td class="py-2.5 px-3 font-mono font-bold text-slate-900">
                                        <div>{{ $parcel->receipt_number ?: '-' }}</div>
                                        @if($parcel->slot_number)
                                            <span class="inline-flex items-center gap-0.5 mt-0.5 px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 border border-indigo-200 text-[10px] font-bold">خانة: {{ $parcel->slot_number }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <div class="text-slate-800">{{ $parcel->recipient_name ?: 'بدون اسم' }}</div>
                                        <div class="text-[11px] font-mono text-slate-400" dir="ltr">{{ $parcel->recipient_phone }}</div>
                                    </td>
                                    <td class="py-2.5 px-3 text-slate-600">{{ $parcel->package_type }}</td>
                                    <td class="py-2.5 px-3 text-slate-600">{{ $parcel->source_region ?: '-' }}</td>
                                    <td class="py-2.5 px-3 text-center">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $parcel->status->value === 'delivered' ? 'bg-emerald-50 text-emerald-700' : ($parcel->status->value === 'returned' ? 'bg-rose-50 text-rose-700' : 'bg-blue-50 text-blue-700') }}">
                                            {{ $parcel->status->label() }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3 text-slate-500 font-mono">{{ $parcel->created_at->format('Y-m-d H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-6 text-center text-slate-400 font-bold">لا توجد طرود مسجلة لهذا المكتب بعد.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- التبويب 2: الهوية البصرية، الألوان، وإعدادات السندات -->
        <!-- ========================================== -->
        @php
            $pModel = $officeProfile ?? null;
            $pPrimary = old('primary_color', $pModel?->primary_color ?? \App\Models\OfficeProfile::DEFAULT_PRIMARY_COLOR);
            $pSecondary = old('secondary_color', $pModel?->secondary_color ?? \App\Models\OfficeProfile::DEFAULT_SECONDARY_COLOR);
            $pTerms = old('receipt_terms', $pModel?->receipt_terms ?? \App\Models\OfficeProfile::DEFAULT_TERMS);
            if (!is_array($pTerms) || empty($pTerms)) {
                $pTerms = \App\Models\OfficeProfile::DEFAULT_TERMS;
            }
        @endphp
        <div x-show="activeTab === 'branding'" x-cloak class="space-y-6"
             x-data="{
                 primaryColor: @js($pPrimary),
                 secondaryColor: @js($pSecondary),
                 logoPreview: @js($resolvedProfile['logo_url'] ?? ''),
                 iconPreview: @js($resolvedProfile['icon_url'] ?? ''),
                 removeLogo: false,
                 removeIcon: false,
                 terms: @js(array_values($pTerms)),
                 defaultTerms: @js(\App\Models\OfficeProfile::DEFAULT_TERMS),
                 presets: [
                     { name: 'مُرسَل الافتراضي', primary: '#041627', secondary: '#fe9d20' },
                     { name: 'كحلي وذهبي',      primary: '#0f172a', secondary: '#eab308' },
                     { name: 'زمردي وبرتقالي',   primary: '#064e3b', secondary: '#f97316' },
                     { name: 'أزرق ملكي',        primary: '#1e3a8a', secondary: '#38bdf8' },
                     { name: 'عنابي وذهبي',      primary: '#4c0519', secondary: '#f59e0b' },
                 ],
                 previewFile(event, target) {
                     const file = event.target.files?.[0];
                     if (!file) return;
                     const reader = new FileReader();
                     reader.onload = (e) => {
                         if (target === 'logo') { this.logoPreview = e.target.result; this.removeLogo = false; }
                         if (target === 'icon') { this.iconPreview = e.target.result; this.removeIcon = false; }
                     };
                     reader.readAsDataURL(file);
                 },
                 addTerm() {
                     if (this.terms.length >= 12) return;
                     this.terms.push((this.terms.length + 1) + '- ');
                 },
                 removeTerm(idx) {
                     this.terms.splice(idx, 1);
                 },
                 resetTerms() {
                     this.terms = [...this.defaultTerms];
                 }
             }">
            <input type="hidden" name="branding_submitted" value="1">
            <input type="hidden" name="remove_logo" :value="removeLogo ? 1 : 0">
            <input type="hidden" name="remove_icon" :value="removeIcon ? 1 : 0">

            <!-- 1. بطاقة الشعار والأيقونة وألوان الهوية -->
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/90 shadow-xs space-y-6">
                <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold">
                        <span class="material-symbols-outlined text-xl">palette</span>
                    </div>
                    <div>
                        <h3 class="text-base font-extrabold text-slate-900">الشعار والأيقونة وألوان هوية المكتب</h3>
                        <p class="text-xs text-slate-500">تُطبّق تلقائياً على واجهة تطبيق المكتب، الترويسة، سندات الإرسال، وكشوفات الشحنات</p>
                    </div>
                </div>

                <!-- رفع الشعار والأيقونة -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <!-- الشعار الرسمي -->
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-extrabold text-slate-800">شعار المكتب (Logo)</p>
                                <p class="text-[11px] text-slate-400">يظهر في السندات والعلامة المائية وكشوفات A4 (PNG/WEBP يفضّل شفاف)</p>
                            </div>
                            <button type="button" x-show="logoPreview" x-cloak
                                    @click="logoPreview = ''; removeLogo = true; $refs.logoInput.value = ''"
                                    class="text-[11px] font-bold text-rose-600 hover:underline">حذف</button>
                        </div>

                        <div class="flex items-center gap-4">
                            <div class="w-20 h-20 rounded-2xl bg-white border border-slate-200 flex items-center justify-center overflow-hidden shrink-0">
                                <template x-if="logoPreview">
                                    <img :src="logoPreview" alt="Logo Preview" class="w-full h-full object-contain p-1.5">
                                </template>
                                <template x-if="!logoPreview">
                                    <span class="material-symbols-outlined text-3xl text-slate-300">image</span>
                                </template>
                            </div>
                            <div class="flex-1">
                                <input type="file" name="logo" x-ref="logoInput" accept="image/png,image/jpeg,image/webp"
                                       @change="previewFile($event, 'logo')"
                                       class="block w-full text-xs text-slate-600 file:ml-3 file:py-2 file:px-3.5 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-primary file:text-white hover:file:bg-slate-800 cursor-pointer">
                                <p class="text-[10px] text-slate-400 mt-1.5">الحد الأقصى 2MB — الصيغ: PNG, JPG, WEBP</p>
                            </div>
                        </div>
                    </div>

                    <!-- الأيقونة المصغرة -->
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-extrabold text-slate-800">أيقونة المكتب (Icon / Favicon)</p>
                                <p class="text-[11px] text-slate-400">تظهر في شريط التطبيق العلوي وأيقونة التبويب بالمتصفح (مربعة 1:1)</p>
                            </div>
                            <button type="button" x-show="iconPreview" x-cloak
                                    @click="iconPreview = ''; removeIcon = true; $refs.iconInput.value = ''"
                                    class="text-[11px] font-bold text-rose-600 hover:underline">حذف</button>
                        </div>

                        <div class="flex items-center gap-4">
                            <div class="w-20 h-20 rounded-2xl bg-white border border-slate-200 flex items-center justify-center overflow-hidden shrink-0">
                                <template x-if="iconPreview">
                                    <img :src="iconPreview" alt="Icon Preview" class="w-full h-full object-contain p-1.5">
                                </template>
                                <template x-if="!iconPreview">
                                    <span class="material-symbols-outlined text-3xl text-slate-300">apps</span>
                                </template>
                            </div>
                            <div class="flex-1">
                                <input type="file" name="icon" x-ref="iconInput" accept="image/png,image/jpeg,image/webp,image/x-icon"
                                       @change="previewFile($event, 'icon')"
                                       class="block w-full text-xs text-slate-600 file:ml-3 file:py-2 file:px-3.5 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-primary file:text-white hover:file:bg-slate-800 cursor-pointer">
                                <p class="text-[10px] text-slate-400 mt-1.5">الحد الأقصى 1MB — الصيغ: PNG, JPG, WEBP, ICO</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ألوان الهوية + معاينة حية -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5 items-center pt-2 border-t border-slate-100">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">اللون الأساسي (Primary)</label>
                        <div class="flex items-center gap-2">
                            <input type="color" x-model="primaryColor"
                                   class="w-11 h-11 rounded-xl border border-slate-200 cursor-pointer bg-white p-1 shrink-0">
                            <input type="text" name="primary_color" x-model="primaryColor" dir="ltr" maxlength="7"
                                   class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-mono font-bold text-slate-800 focus:bg-white focus:border-secondary outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">اللون الثانوي (Secondary)</label>
                        <div class="flex items-center gap-2">
                            <input type="color" x-model="secondaryColor"
                                   class="w-11 h-11 rounded-xl border border-slate-200 cursor-pointer bg-white p-1 shrink-0">
                            <input type="text" name="secondary_color" x-model="secondaryColor" dir="ltr" maxlength="7"
                                   class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-mono font-bold text-slate-800 focus:bg-white focus:border-secondary outline-none">
                        </div>
                    </div>

                    <!-- معاينة حية لشريط التطبيق -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">معاينة حية للتطبيق</label>
                        <div class="rounded-2xl p-3 flex items-center justify-between gap-2 shadow-sm border border-slate-200"
                             :style="{ backgroundColor: primaryColor }">
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="w-8 h-8 rounded-lg bg-white/15 flex items-center justify-center overflow-hidden shrink-0">
                                    <template x-if="iconPreview || logoPreview">
                                        <img :src="iconPreview || logoPreview" class="w-full h-full object-contain p-0.5 bg-white rounded-lg">
                                    </template>
                                    <template x-if="!iconPreview && !logoPreview">
                                        <span class="material-symbols-outlined text-base" :style="{ color: secondaryColor }">local_shipping</span>
                                    </template>
                                </div>
                                <span class="text-xs font-extrabold text-white truncate">{{ $office->name }}</span>
                            </div>
                            <span class="px-2.5 py-1 rounded-lg text-[10px] font-black shrink-0"
                                  :style="{ backgroundColor: secondaryColor, color: primaryColor }">زر أساسي</span>
                        </div>
                    </div>
                </div>

                <!-- قوالب ألوان جاهزة -->
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-[11px] font-bold text-slate-500 ml-1">ألوان جاهزة:</span>
                    <template x-for="pr in presets" :key="pr.name">
                        <button type="button"
                                @click="primaryColor = pr.primary; secondaryColor = pr.secondary"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-200 text-[11px] font-bold text-slate-700 transition-colors">
                            <span class="w-3.5 h-3.5 rounded-full border border-white shadow-xs" :style="{ backgroundColor: pr.primary }"></span>
                            <span class="w-3.5 h-3.5 rounded-full border border-white shadow-xs -mr-1.5" :style="{ backgroundColor: pr.secondary }"></span>
                            <span x-text="pr.name"></span>
                        </button>
                    </template>
                </div>
            </div>

            <!-- 2. بطاقة موقع الفرع وأرقام التواصل -->
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/90 shadow-xs space-y-5">
                <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                    <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center font-bold">
                        <span class="material-symbols-outlined text-xl">location_on</span>
                    </div>
                    <div>
                        <h3 class="text-base font-extrabold text-slate-900">موقع الفرع وأرقام التواصل (تظهر في السندات)</h3>
                        <p class="text-xs text-slate-500">يُستخدم موقع الفرع ورقم الاستلام تلقائياً كـ «موقع التسليم» عندما ترسل الفروع الشريكة طروداً إلى هذا المكتب</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">الوصف التعريفي (السطر الأول تحت اسم المكتب)</label>
                        <input type="text" name="tagline_primary"
                               value="{{ old('tagline_primary', $pModel?->tagline_primary ?? \App\Models\OfficeProfile::DEFAULT_TAGLINE_1) }}"
                               placeholder="لإرسال واستقبال الطرود والرسائل"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-semibold text-slate-800 focus:bg-white focus:border-secondary outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">الوصف التعريفي (السطر الثاني)</label>
                        <input type="text" name="tagline_secondary"
                               value="{{ old('tagline_secondary', $pModel?->tagline_secondary ?? \App\Models\OfficeProfile::DEFAULT_TAGLINE_2) }}"
                               placeholder="من وإلى مختلف المحافظات"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-semibold text-slate-800 focus:bg-white focus:border-secondary outline-none">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 mb-2">عنوان وموقع الفرع التفصيلي</label>
                        <input type="text" name="branch_address"
                               value="{{ old('branch_address', $pModel?->branch_address) }}"
                               placeholder="مثال: صنعاء - باب اليمن أمام فرزة تعز جوار الكريمي"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-semibold text-slate-800 focus:bg-white focus:border-secondary outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">رقم المدير والشكاوى (في السند)</label>
                        <input type="text" name="complaints_phone" dir="ltr"
                               value="{{ old('complaints_phone', $pModel?->complaints_phone) }}"
                               placeholder="{{ $office->manager_phone ?: $office->whatsapp_sender_phone }}"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-mono font-semibold text-slate-800 focus:bg-white focus:border-secondary outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">رقم التواصل لموقع التسليم (للفروع الشريكة)</label>
                        <input type="text" name="delivery_phone" dir="ltr"
                               value="{{ old('delivery_phone', $pModel?->delivery_phone) }}"
                               placeholder="{{ $office->whatsapp_sender_phone }}"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-mono font-semibold text-slate-800 focus:bg-white focus:border-secondary outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">أرقام خدمة الإرسال (السطر الأول)</label>
                        <input type="text" name="dispatch_phone_1" dir="ltr"
                               value="{{ old('dispatch_phone_1', $pModel?->dispatch_phone_1) }}"
                               placeholder="مثال: 771961061 - 777950051"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-mono font-semibold text-slate-800 focus:bg-white focus:border-secondary outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">أرقام خدمة الإرسال الإضافية (السطر الثاني - اختياري)</label>
                        <input type="text" name="dispatch_phone_2" dir="ltr"
                               value="{{ old('dispatch_phone_2', $pModel?->dispatch_phone_2) }}"
                               placeholder="مثال: 777880006 - 777880005"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-mono font-semibold text-slate-800 focus:bg-white focus:border-secondary outline-none">
                    </div>
                </div>
            </div>

            <!-- 3. بطاقة إعدادات السند والشروط والأحكام -->
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/90 shadow-xs space-y-5">
                <div class="flex items-center justify-between gap-3 pb-3 border-b border-slate-100 flex-wrap">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
                            <span class="material-symbols-outlined text-xl">gavel</span>
                        </div>
                        <div>
                            <h3 class="text-base font-extrabold text-slate-900">عناوين السند والشروط والأحكام</h3>
                            <p class="text-xs text-slate-500">تخصيص عنوان السند والعبارة الدعائية وبنود الشروط التي تُطبع أسفل كل سند</p>
                        </div>
                    </div>

                    <button type="button" @click="resetTerms()"
                            class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] font-bold transition-colors">
                        استعادة الشروط الافتراضية
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">عنوان السند</label>
                        <input type="text" name="receipt_title"
                               value="{{ old('receipt_title', $pModel?->receipt_title ?? \App\Models\OfficeProfile::DEFAULT_RECEIPT_TITLE) }}"
                               placeholder="سند إرسال"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-semibold text-slate-800 focus:bg-white focus:border-secondary outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">العبارة الدعائية (الشعار النصي)</label>
                        <input type="text" name="slogan"
                               value="{{ old('slogan', $pModel?->slogan ?? \App\Models\OfficeProfile::DEFAULT_SLOGAN) }}"
                               placeholder="رسالتك في أيدٍ أمينة"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-semibold text-slate-800 focus:bg-white focus:border-secondary outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">نص التذييل (أسفل اليسار)</label>
                        <input type="text" name="footer_text"
                               value="{{ old('footer_text', $pModel?->footer_text ?? \App\Models\OfficeProfile::DEFAULT_FOOTER_TEXT) }}"
                               placeholder="نظام مُرسَل للطرود"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-semibold text-slate-800 focus:bg-white focus:border-secondary outline-none">
                    </div>
                </div>

                <!-- قائمة الشروط والأحكام الديناميكية -->
                <div class="space-y-2.5 pt-2">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-extrabold text-slate-700">بنود الشروط والأحكام (تُطبع في أسفل السند):</label>
                        <button type="button" @click="addTerm()"
                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-primary text-white text-[11px] font-bold hover:bg-slate-800 transition-colors">
                            <span class="material-symbols-outlined text-sm">add</span>
                            <span>إضافة شرط</span>
                        </button>
                    </div>

                    <div class="space-y-2">
                        <template x-for="(term, idx) in terms" :key="idx">
                            <div class="flex items-center gap-2">
                                <input type="text" name="receipt_terms[]" x-model="terms[idx]"
                                       placeholder="اكتب نص الشرط..."
                                       class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-semibold text-slate-800 focus:bg-white focus:border-secondary outline-none">
                                <button type="button" @click="removeTerm(idx)" title="حذف البند"
                                        class="w-9 h-9 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 flex items-center justify-center shrink-0 transition-colors">
                                    <span class="material-symbols-outlined text-base">delete</span>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- التبويب 3: إعدادات وقالب الـ SMS          -->
        <!-- ========================================== -->
        <div x-show="activeTab === 'sms'" x-cloak class="space-y-6">
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/90 shadow-xs space-y-6">
                <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                    <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center font-bold">
                        <span class="material-symbols-outlined text-xl">sms</span>
                    </div>
                    <div>
                        <h3 class="text-base font-extrabold text-slate-900">إعدادات بوابة httpSMS وقالب الإشعار</h3>
                        <p class="text-xs text-slate-500">التحكم بالشريحة والمفتاح البرمجي وصيغة الرسائل النصية</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">رقم الشريحة الراسلة (مع مفتاح الدولة)</label>
                        <input type="text" name="httpsms_from_phone" value="{{ old('httpsms_from_phone', $office->httpsms_from_phone) }}" required dir="ltr"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-mono font-semibold text-slate-800 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">مفتاح API الخاص ببوابة httpSMS</label>
                        <input type="text" name="httpsms_api_key" value="{{ old('httpsms_api_key', $office->httpsms_api_key) }}" dir="ltr"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-mono font-semibold text-slate-800 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 mb-2">قالب رسالة الـ SMS (عند وصول/إرسال الطرد)</label>
                        <textarea name="sms_template" id="sms_template_input" rows="4"
                                  class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-xs font-medium text-slate-800 leading-relaxed focus:bg-white focus:outline-none focus:border-secondary transition-all">{{ old('sms_template', $office->sms_template) }}</textarea>
                        
                        <!-- أزرار إدراج المتغيرات الجاهزة بنقرة واحدة -->
                        <div class="mt-3 p-3 bg-slate-50 rounded-xl border border-slate-100">
                            <span class="text-xs font-bold text-slate-600 block mb-2">انقر لإدراج متغير ديناميكي في نص الرسالة:</span>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" @click="insertTag('sms_template_input', '{recipient}')" class="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-xs font-mono text-slate-700 hover:bg-slate-100 transition-colors">+ {recipient} (اسم المستلم)</button>
                                <button type="button" @click="insertTag('sms_template_input', '{office}')" class="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-xs font-mono text-slate-700 hover:bg-slate-100 transition-colors">+ {office} (اسم المكتب)</button>
                                <button type="button" @click="insertTag('sms_template_input', '{branch}')" class="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-xs font-mono text-slate-700 hover:bg-slate-100 transition-colors">+ {branch} (اسم الفرع)</button>
                                <button type="button" @click="insertTag('sms_template_input', '{package}')" class="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-xs font-mono text-slate-700 hover:bg-slate-100 transition-colors">+ {package} (نوع الطرد)</button>
                                <button type="button" @click="insertTag('sms_template_input', '{receipt}')" class="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-xs font-mono text-slate-700 hover:bg-slate-100 transition-colors">+ {receipt} (رقم السند)</button>
                                <button type="button" @click="insertTag('sms_template_input', '{region}')" class="px-2.5 py-1 rounded-lg bg-purple-50 border border-purple-200 text-xs font-mono text-purple-700 hover:bg-purple-100 transition-colors">+ {region} (منطقة قدوم الطرد)</button>
                                <button type="button" @click="insertTag('sms_template_input', '{slot}')" class="px-2.5 py-1 rounded-lg bg-indigo-50 border border-indigo-200 text-xs font-mono text-indigo-700 hover:bg-indigo-100 transition-colors">+ {slot} (رقم الخانة)</button>
                            </div>
                        </div>
                    </div>

                    <div class="md:col-span-2 pt-4 border-t border-slate-200/60">
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-xs font-bold text-slate-700">قالب رسالة الـ SMS عند تسليم الطرد للمستلم (Delivery SMS)</label>
                            <span class="text-[11px] text-slate-400 font-normal">(يُترك فارغاً لاستخدام القالب الافتراضي)</span>
                        </div>
                        <textarea name="delivery_sms_template" id="delivery_sms_template_input" rows="3"
                                  placeholder="تم تسليم طردك: {package_type}&#10;مكتب {office_name}&#10;شكراً لتعاملكم معنا."
                                  class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-xs font-medium text-slate-800 leading-relaxed focus:bg-white focus:outline-none focus:border-secondary transition-all">{{ old('delivery_sms_template', $office->delivery_sms_template) }}</textarea>
                        
                        <div class="mt-3 p-3 bg-slate-50 rounded-xl border border-slate-100">
                            <span class="text-xs font-bold text-slate-600 block mb-2">انقر لإدراج متغير ديناميكي في رسالة تسليم الـ SMS:</span>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" @click="insertTag('delivery_sms_template_input', '{package_type}')" class="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-xs font-mono text-slate-700 hover:bg-slate-100 transition-colors">+ {package_type} (نوع الطرد)</button>
                                <button type="button" @click="insertTag('delivery_sms_template_input', '{office_name}')" class="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-xs font-mono text-slate-700 hover:bg-slate-100 transition-colors">+ {office_name} (اسم المكتب)</button>
                                <button type="button" @click="insertTag('delivery_sms_template_input', '{branch_name}')" class="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-xs font-mono text-slate-700 hover:bg-slate-100 transition-colors">+ {branch_name} (اسم الفرع)</button>
                                <button type="button" @click="insertTag('delivery_sms_template_input', '{recipient_name}')" class="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-xs font-mono text-slate-700 hover:bg-slate-100 transition-colors">+ {recipient_name} (اسم المستلم)</button>
                                <button type="button" @click="insertTag('delivery_sms_template_input', '{receipt_number}')" class="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-xs font-mono text-slate-700 hover:bg-slate-100 transition-colors">+ {receipt_number} (رقم السند)</button>
                                <button type="button" @click="insertTag('delivery_sms_template_input', '{region}')" class="px-2.5 py-1 rounded-lg bg-purple-50 border border-purple-200 text-xs font-mono text-purple-700 hover:bg-purple-100 transition-colors">+ {region} (منطقة القدوم)</button>
                                <button type="button" @click="insertTag('delivery_sms_template_input', '{slot}')" class="px-2.5 py-1 rounded-lg bg-indigo-50 border border-indigo-200 text-xs font-mono text-indigo-700 hover:bg-indigo-100 transition-colors">+ {slot} (رقم الخانة)</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- التبويب 4: إعدادات وجلسة الواتساب المباشرة -->
        <!-- ========================================== -->
        <div x-show="activeTab === 'whatsapp'" x-cloak class="space-y-6">
            
            <!-- أداة التحكم المباشر بالجلسة (Live WhatsApp Session Controller) -->
            <div class="bg-gradient-to-br from-primary-950 to-primary-900 rounded-3xl p-6 sm:p-8 text-white shadow-xl border border-slate-800">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-6 border-b border-white/10">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 border border-emerald-500/40 text-emerald-400 flex items-center justify-center font-bold">
                            <span class="material-symbols-outlined text-2xl">sensors</span>
                        </div>
                        <div>
                            <h3 class="text-base font-extrabold text-white flex items-center gap-2">
                                <span>جلسة الواتساب المباشرة للمكتب</span>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold"
                                      :class="liveStatus.connected ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' : 'bg-rose-500/20 text-rose-300 border border-rose-500/40'"
                                      x-text="liveStatus.connected ? 'متصل بنجاح ✅' : 'غير متصل ❌'"></span>
                            </h3>
                            <p class="text-xs text-slate-400 mt-0.5" x-text="liveStatus.message || 'فحص الحالة والتحكم برمز QR والاتصال'"></p>
                        </div>
                    </div>

                    <!-- أزرار الإجراءات الفورية -->
                    <div class="flex items-center gap-2 flex-wrap">
                        <button type="button" @click="fetchStatus(true)" :disabled="liveStatus.loading"
                                class="px-3.5 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-bold transition-colors flex items-center gap-1.5 disabled:opacity-50">
                            <span class="material-symbols-outlined text-base" :class="{'animate-spin': liveStatus.loading}">sync</span>
                            <span>فحص الحالة الآن</span>
                        </button>

                        <button type="button" @click="requestQr()" :disabled="liveStatus.loading || liveStatus.connected"
                                class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-extrabold transition-colors flex items-center gap-1.5 disabled:opacity-40 disabled:cursor-not-allowed">
                            <span class="material-symbols-outlined text-base">qr_code_2</span>
                            <span>جلب رمز QR</span>
                        </button>

                        <button type="button" @click="disconnectSession()" :disabled="liveStatus.loading || !liveStatus.connected"
                                class="px-3.5 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold transition-colors flex items-center gap-1.5 disabled:opacity-40 disabled:cursor-not-allowed">
                            <span class="material-symbols-outlined text-base">power_settings_new</span>
                            <span>قطع الاتصال</span>
                        </button>
                    </div>
                </div>

                <!-- مساحة عرض QR Code أو كود الربط Pairing Code -->
                <div class="pt-6 grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
                    
                    <!-- عرض QR Code -->
                    <div class="bg-black/30 p-5 rounded-2xl border border-white/10 text-center">
                        <h4 class="text-xs font-bold text-slate-300 mb-3">رمز الاستجابة السريعة (QR Code)</h4>
                        
                        <div class="w-56 h-56 mx-auto bg-white rounded-2xl p-3 flex items-center justify-center shadow-lg">
                            <template x-if="qrCode">
                                <img :src="qrCode" alt="WhatsApp QR Code" class="w-full h-full object-contain">
                            </template>
                            <template x-if="!qrCode && liveStatus.connected">
                                <div class="text-center p-4">
                                    <span class="material-symbols-outlined text-emerald-600 text-5xl mb-2">check_circle</span>
                                    <p class="text-xs font-bold text-slate-700">الجلسة نشطة ومسجلة الدخول بالفعل</p>
                                </div>
                            </template>
                            <template x-if="!qrCode && !liveStatus.connected">
                                <div class="text-center p-4 text-slate-400">
                                    <span class="material-symbols-outlined text-slate-300 text-5xl mb-2">qr_code_scanner</span>
                                    <p class="text-xs font-bold text-slate-500">انقر على "جلب رمز QR" لبدء المسح</p>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- كود الربط برقم الهاتف (Pairing Code) -->
                    <div class="bg-black/30 p-5 rounded-2xl border border-white/10 space-y-4">
                        <div>
                            <h4 class="text-xs font-bold text-slate-300">الربط برقم الهاتف (Pairing Code)</h4>
                            <p class="text-[11px] text-slate-400 mt-1">بديل مسح QR Code: استخرج كود مكون من 8 أرقام وأدخله في تطبيق واتساب مباشرة.</p>
                        </div>

                        <div class="flex gap-2">
                            <input type="text" x-model="pairingPhone" placeholder="770000000" dir="ltr"
                                   class="flex-1 bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-xs font-mono text-white placeholder-slate-400 focus:outline-none focus:border-secondary">
                            <button type="button" @click="requestPairingCode()" :disabled="liveStatus.loading"
                                    class="px-4 py-2 rounded-xl bg-secondary text-primary-950 font-extrabold text-xs hover:brightness-105 disabled:opacity-50">
                                توليد الكود
                            </button>
                        </div>

                        <div x-show="pairingCodeResult" x-cloak class="p-4 rounded-xl bg-emerald-500/20 border border-emerald-500/40 text-center">
                            <span class="text-[11px] text-emerald-300 font-bold block mb-1">أدخل هذا الكود في واتساب الهاتف:</span>
                            <span class="text-2xl font-mono font-black tracking-widest text-white" x-text="pairingCodeResult"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- إعدادات خادم الواتساب (URL, Instance ID, Token) -->
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/90 shadow-xs space-y-6">
                <h3 class="text-base font-extrabold text-slate-900 pb-3 border-b border-slate-100">إعدادات الاتصال بالسيرفر (API Credentials)</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">رابط الخادم الأساسي (Base URL)</label>
                        <input type="url" name="whatsapp_base_url" value="{{ old('whatsapp_base_url', $office->whatsapp_base_url) }}" dir="ltr"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-mono font-semibold text-slate-800 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">معرف الـ Instance (ID)</label>
                        <input type="text" name="whatsapp_instance_id" value="{{ old('whatsapp_instance_id', $office->whatsapp_instance_id) }}" dir="ltr"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-mono font-semibold text-slate-800 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">اسم الـ Instance</label>
                        <input type="text" name="whatsapp_instance_name" value="{{ old('whatsapp_instance_name', $office->whatsapp_instance_name) }}"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-semibold text-slate-800 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">مفتاح API الخاص بالواتساب (API Key / Token)</label>
                        <input type="text" name="whatsapp_api_key" value="{{ old('whatsapp_api_key', $office->whatsapp_api_key) }}" dir="ltr"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-mono font-semibold text-slate-800 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 mb-2">قالب رسالة الواتساب (عند وصول/إرسال الطرد)</label>
                        <textarea name="whatsapp_template" id="wa_template_input" rows="4"
                                  class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-xs font-medium text-slate-800 leading-relaxed focus:bg-white focus:outline-none focus:border-secondary transition-all">{{ old('whatsapp_template', $office->whatsapp_template) }}</textarea>
                        <div class="mt-3 p-3 bg-slate-50 rounded-xl border border-slate-100">
                            <span class="text-xs font-bold text-slate-600 block mb-2">انقر لإدراج متغير ديناميكي في نص الواتساب:</span>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" @click="insertTag('wa_template_input', '{recipient}')" class="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-xs font-mono text-slate-700 hover:bg-slate-100 transition-colors">+ {recipient}</button>
                                <button type="button" @click="insertTag('wa_template_input', '{office}')" class="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-xs font-mono text-slate-700 hover:bg-slate-100 transition-colors">+ {office}</button>
                                <button type="button" @click="insertTag('wa_template_input', '{branch}')" class="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-xs font-mono text-slate-700 hover:bg-slate-100 transition-colors">+ {branch}</button>
                                <button type="button" @click="insertTag('wa_template_input', '{package}')" class="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-xs font-mono text-slate-700 hover:bg-slate-100 transition-colors">+ {package}</button>
                                <button type="button" @click="insertTag('wa_template_input', '{receipt}')" class="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-xs font-mono text-slate-700 hover:bg-slate-100 transition-colors">+ {receipt}</button>
                                <button type="button" @click="insertTag('wa_template_input', '{region}')" class="px-2.5 py-1 rounded-lg bg-purple-50 border border-purple-200 text-xs font-mono text-purple-700 hover:bg-purple-100 transition-colors">+ {region} (المنطقة)</button>
                                <button type="button" @click="insertTag('wa_template_input', '{slot}')" class="px-2.5 py-1 rounded-lg bg-indigo-50 border border-indigo-200 text-xs font-mono text-indigo-700 hover:bg-indigo-100 transition-colors">+ {slot} (رقم الخانة)</button>
                            </div>
                        </div>
                    </div>

                    <div class="md:col-span-2 pt-4 border-t border-slate-200/60">
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-xs font-bold text-slate-700">قالب رسالة الواتساب عند تسليم الطرد للمستلم (Delivery WhatsApp)</label>
                            <span class="text-[11px] text-slate-400 font-normal">(يُترك فارغاً لاستخدام القالب الافتراضي)</span>
                        </div>
                        <textarea name="delivery_whatsapp_template" id="delivery_wa_template_input" rows="4"
                                  placeholder="✅ *إشعار تسليم طرد*&#10;&#10;عميلنا العزيز، تم بحمد الله تسليم طردك: *{package_type}*&#10;من مكتب: *{office_name}*&#10;&#10;نسعد بخدمتكم دائماً."
                                  class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-xs font-medium text-slate-800 leading-relaxed focus:bg-white focus:outline-none focus:border-secondary transition-all">{{ old('delivery_whatsapp_template', $office->delivery_whatsapp_template) }}</textarea>
                        <div class="mt-3 p-3 bg-slate-50 rounded-xl border border-slate-100">
                            <span class="text-xs font-bold text-slate-600 block mb-2">انقر لإدراج متغير ديناميكي في رسالة تسليم الواتساب:</span>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" @click="insertTag('delivery_wa_template_input', '{package_type}')" class="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-xs font-mono text-slate-700 hover:bg-slate-100 transition-colors">+ {package_type}</button>
                                <button type="button" @click="insertTag('delivery_wa_template_input', '{office_name}')" class="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-xs font-mono text-slate-700 hover:bg-slate-100 transition-colors">+ {office_name}</button>
                                <button type="button" @click="insertTag('delivery_wa_template_input', '{branch_name}')" class="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-xs font-mono text-slate-700 hover:bg-slate-100 transition-colors">+ {branch_name}</button>
                                <button type="button" @click="insertTag('delivery_wa_template_input', '{recipient_name}')" class="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-xs font-mono text-slate-700 hover:bg-slate-100 transition-colors">+ {recipient_name}</button>
                                <button type="button" @click="insertTag('delivery_wa_template_input', '{receipt_number}')" class="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-xs font-mono text-slate-700 hover:bg-slate-100 transition-colors">+ {receipt_number}</button>
                                <button type="button" @click="insertTag('delivery_wa_template_input', '{region}')" class="px-2.5 py-1 rounded-lg bg-purple-50 border border-purple-200 text-xs font-mono text-purple-700 hover:bg-purple-100 transition-colors">+ {region} (المنطقة)</button>
                                <button type="button" @click="insertTag('delivery_wa_template_input', '{slot}')" class="px-2.5 py-1 rounded-lg bg-indigo-50 border border-indigo-200 text-xs font-mono text-indigo-700 hover:bg-indigo-100 transition-colors">+ {slot} (رقم الخانة)</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- التبويب 5: الأمان وكلمة المرور وحالة الحساب -->
        <!-- ========================================== -->
        <div x-show="activeTab === 'security'" x-cloak class="space-y-6">
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/90 shadow-xs space-y-6">
                <h3 class="text-base font-extrabold text-slate-900 pb-3 border-b border-slate-100">حالة نشاط حساب المكتب</h3>
                
                <div class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-100">
                    <div>
                        <span class="text-sm font-extrabold text-slate-800 block">حالة تفعيل حساب المكتب</span>
                        <span class="text-xs text-slate-500">إذا تم حظر الحساب، لن يتمكن موظفو المكتب من تسجيل الدخول.</span>
                    </div>

                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" {{ $office->is_active ? 'checked' : '' }} class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                    </label>
                </div>
            </div>

            <!-- تحديث كلمة المرور المباشر -->
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/90 shadow-xs space-y-6"
                 x-data="{
                     directPassword: '',
                     passLoading: false,
                     passMessage: '',
                     updateOfficePassword() {
                         if (this.directPassword.length < 6) {
                             alert('كلمة المرور يجب أن لا تقل عن 6 أحرف.');
                             return;
                         }
                         this.passLoading = true;
                         var self = this;
                         fetch('{{ route('admin.offices.update_password', $office) }}', {
                             method: 'POST',
                             headers: {
                                 'Content-Type': 'application/json',
                                 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                 'Accept': 'application/json'
                             },
                             body: JSON.stringify({ password: self.directPassword })
                         })
                         .then(function(r) { return r.json(); })
                         .then(function(d) {
                             self.passLoading = false;
                             self.passMessage = d.message;
                             self.directPassword = '';
                         })
                         .catch(function() {
                             self.passLoading = false;
                             alert('حدث خطأ أثناء تحديث كلمة المرور.');
                         });
                     }
                 }">
                <h3 class="text-base font-extrabold text-slate-900 pb-3 border-b border-slate-100">تحديث كلمة المرور للمكتب</h3>
                
                <div class="max-w-md space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">كلمة المرور الجديدة</label>
                        <input type="password" x-model="directPassword" minlength="6" placeholder="اكتب كلمة مرور جديدة..."
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-mono font-semibold text-slate-800 focus:bg-white focus:outline-none focus:border-secondary transition-all">
                    </div>

                    <div x-show="passMessage" x-text="passMessage" class="text-xs font-bold text-emerald-600"></div>

                    <button type="button"
                            @click="updateOfficePassword()"
                            :disabled="passLoading"
                            class="px-5 py-2.5 rounded-xl bg-primary text-white text-xs font-bold hover:bg-primary-800 transition-colors disabled:opacity-50">
                        <span x-show="!passLoading">حفظ كلمة المرور الجديدة</span>
                        <span x-show="passLoading" x-cloak>جاري التحديث...</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- زر الحفظ العائم أو السفلي العام لجميع التبويبات -->
        <div class="sticky bottom-4 z-20 bg-white/95 backdrop-blur-md p-4 rounded-2xl border border-slate-200 shadow-xl flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500">تأكد من مراجعة الإعدادات قبل الحفظ</span>

            <div class="flex items-center gap-3">
                <a href="{{ route('admin.offices.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50">إلغاء</a>
                <button type="submit" class="px-8 py-2.5 rounded-xl bg-secondary text-primary-950 font-extrabold text-xs hover:brightness-105 shadow-md shadow-secondary/20 flex items-center gap-2">
                    <span class="material-symbols-outlined text-lg">save</span>
                    <span>حفظ جميع التعديلات</span>
                </button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    function officeEditor(officeId) {
        return {
            activeTab: '{{ request()->input('tab', 'general') }}',
            pairingPhone: '{{ $office->whatsapp_sender_phone }}',
            pairingCodeResult: null,
            qrCode: null,
            liveStatus: {
                connected: {{ $whatsappStatus['is_online'] ? 'true' : 'false' }},
                message: '{{ $whatsappStatus['is_online'] ? 'متصل بنجاح' : 'الجلسة غير متصلة' }}',
                loading: false
            },

            init() {
                // فحص مبدئي للحالة
                this.fetchStatus(false);
            },

            insertTag(inputId, tag) {
                const el = document.getElementById(inputId);
                if (el) {
                    const start = el.selectionStart;
                    const end = el.selectionEnd;
                    const text = el.value;
                    el.value = text.substring(0, start) + tag + text.substring(end);
                    el.focus();
                }
            },

            async fetchStatus(refresh = true) {
                this.liveStatus.loading = true;
                try {
                    const res = await fetch(`/admin/offices/${officeId}/whatsapp/status?refresh=${refresh ? 1 : 0}`, {
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await res.json();
                    this.liveStatus.connected = data.connected;
                    this.liveStatus.message = data.message;
                    if (data.connected) {
                        this.qrCode = null;
                        this.pairingCodeResult = null;
                    }
                } catch (e) {
                    console.error(e);
                } finally {
                    this.liveStatus.loading = false;
                }
            },

            async requestQr() {
                this.liveStatus.loading = true;
                try {
                    const res = await fetch(`/admin/offices/${officeId}/whatsapp/qr`, {
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await res.json();
                    if (data.success && data.qr_base64) {
                        this.qrCode = data.qr_base64;
                        this.liveStatus.message = 'امسح رمز QR بواسطة واتساب هاتفك الآن';
                    } else if (data.connected) {
                        this.liveStatus.connected = true;
                        this.liveStatus.message = data.message;
                        this.qrCode = null;
                    } else {
                        alert(data.message || 'تعذر جلب رمز QR.');
                    }
                } catch (e) {
                    alert('فشل في جلب رمز QR.');
                } finally {
                    this.liveStatus.loading = false;
                }
            },

            async requestPairingCode() {
                if (!this.pairingPhone) {
                    alert('يرجى إدخال رقم الهاتف أولاً.');
                    return;
                }
                this.liveStatus.loading = true;
                try {
                    const res = await fetch(`/admin/offices/${officeId}/whatsapp/pairing-code`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ phone: this.pairingPhone })
                    });
                    const data = await res.json();
                    if (data.success && data.pairing_code) {
                        this.pairingCodeResult = data.pairing_code;
                        this.liveStatus.message = 'تم إنشاء كود الربط بنجاح';
                    } else {
                        alert(data.message || 'تعذر توليد كود الربط.');
                    }
                } catch (e) {
                    alert('فشل في توليد كود الربط.');
                } finally {
                    this.liveStatus.loading = false;
                }
            },

            async disconnectSession() {
                if (!confirm('هل أنت متأكد من رغبتك بقطع اتصال جلسة الواتساب وتسجيل الخروج؟')) return;

                this.liveStatus.loading = true;
                try {
                    const res = await fetch(`/admin/offices/${officeId}/whatsapp/disconnect`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        }
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.liveStatus.connected = false;
                        this.liveStatus.message = data.message;
                        this.qrCode = null;
                        this.pairingCodeResult = null;
                    }
                } catch (e) {
                    alert('حدث خطأ أثناء قطع الاتصال.');
                } finally {
                    this.liveStatus.loading = false;
                }
            }
        };
    }
</script>
@endpush