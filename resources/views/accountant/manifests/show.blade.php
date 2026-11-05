@extends('accountant.layouts.app')

@section('title', 'تفاصيل الكشف والخصومات #' . $manifest->id . ' - بوابة المحاسب')

@section('alpine', 'accountantManifestShowApp(' . $manifest->id . ')')

@section('content')
    <!-- 1. بطاقة رأس الكشف والمعادلة المالية الحية + تبويبات التنقل -->
    <div class="bg-white border-b border-slate-200 sticky top-[68px] z-20 shadow-2xs print:static print:shadow-none">

        <!-- صف العنوان وبيانات السائق والمصدر + حالة الكشف -->
        <div class="px-3.5 pt-3 pb-2 flex items-start justify-between gap-2">
            <div class="flex items-start gap-2 min-w-0">
                <!-- زر رجوع سريع لقائمة الكشوفات -->
                <a href="{{ route('accountant.manifests.index') }}"
                   title="العودة لقائمة الكشوفات"
                   class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 flex items-center justify-center shrink-0 transition-colors print:hidden">
                    <span class="material-symbols-outlined text-lg">arrow_forward</span>
                </a>

                <div class="min-w-0">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <h2 class="text-sm font-black text-slate-800 truncate"
                            x-text="manifest.driver_name || '{{ addslashes($manifest->driver_name) }}'"></h2>
                        <span x-show="manifest.manifest_number"
                              class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200"
                              x-text="'#' + manifest.manifest_number"></span>
                    </div>

                    <div class="flex items-center gap-2 flex-wrap text-[11px] text-slate-500 mt-0.5">
                        <span class="font-bold text-slate-600 flex items-center gap-0.5">
                            <span class="material-symbols-outlined text-[13px] text-secondary">store</span>
                            <span x-text="manifest.source_office_name || 'غير محدد'"></span>
                            <span x-show="manifest.source_branch_name"
                                  x-text="'(' + manifest.source_branch_name + ')'"></span>
                        </span>

                        <span class="text-slate-300">•</span>

                        <span class="font-mono font-bold dir-ltr" x-text="manifest.manifest_date"></span>

                        <template x-if="manifest.driver_phone">
                            <a :href="'tel:' + manifest.driver_phone"
                               class="font-mono font-bold text-emerald-700 hover:underline dir-ltr inline-flex items-center gap-0.5 print:hidden">
                                <span class="material-symbols-outlined text-[12px]">call</span>
                                <span x-text="manifest.driver_phone"></span>
                            </a>
                        </template>
                    </div>
                </div>
            </div>

            <!-- شارة الحالة + زر تعديل بيانات الكشف -->
            <div class="shrink-0 flex items-center gap-1.5">
                <button type="button"
                        x-show="manifest.status === 'open'"
                        @click="openEditManifestModal()"
                        title="تعديل بيانات الكشف"
                        class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center print:hidden">
                    <span class="material-symbols-outlined text-[15px]">edit</span>
                </button>

                <template x-if="manifest.status === 'open'">
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-[11px] font-extrabold bg-amber-50 text-amber-800 border border-amber-300">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                        <span>قيد المراجعة</span>
                    </span>
                </template>

                <template x-if="manifest.status === 'settled'">
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-[11px] font-extrabold bg-emerald-50 text-emerald-800 border border-emerald-300">
                        <span class="material-symbols-outlined text-[13px] text-emerald-600">check_circle</span>
                        <span>تمت التصفية</span>
                    </span>
                </template>
            </div>
        </div>

        <!-- شريط المعادلة المالية البارز (ثلاثي واضح يتحدث لحظياً) -->
        <div class="px-3.5 pb-2.5 grid grid-cols-3 gap-1.5 text-center">
            <!-- 1. إجمالي الآجل -->
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-2">
                <p class="text-[10px] font-extrabold text-amber-800 flex items-center justify-center gap-0.5">
                    <span>إجمالي الآجل</span>
                    <span class="font-mono text-[9px] text-amber-700">(<span x-text="postpaidParcelsCount">0</span>)</span>
                </p>
                <p class="text-xs font-black text-amber-950 mt-0.5 dir-ltr">
                    <span x-text="formatAmount(manifest.total_postpaid_amount)">0</span> ر.ي
                </p>
            </div>

            <!-- 2. إجمالي الخصومات (-) -->
            <div class="bg-rose-50 border border-rose-200 rounded-xl p-2">
                <p class="text-[10px] font-extrabold text-rose-800">الخصومات (-)</p>
                <p class="text-xs font-black text-rose-900 mt-0.5 dir-ltr">
                    <span x-text="formatAmount(manifest.total_deductions)">0</span> ر.ي
                </p>
            </div>

            <!-- 3. الصافي المسلم للسائق (=) -->
            <div class="bg-emerald-600 text-white border border-emerald-700 rounded-xl p-2 shadow-xs">
                <p class="text-[10px] font-extrabold text-emerald-100">الصافي للسائق (=)</p>
                <p class="text-xs font-black text-white mt-0.5 dir-ltr">
                    <span x-text="formatAmount(manifest.net_driver_amount)">0</span> ر.ي
                </p>
            </div>
        </div>

        <!-- نظام التبويبين داخل صفحة الكشف للتنقل السلس -->
        <nav class="flex border-t border-slate-100 text-xs font-extrabold text-center print:hidden">
            <button type="button"
                    @click="activeTab = 'parcels'"
                    :class="activeTab === 'parcels'
                        ? 'border-b-2 border-secondary text-primary bg-amber-50/30'
                        : 'text-slate-500 hover:text-slate-700'"
                    class="flex-1 py-2.5 flex items-center justify-center gap-1.5 transition-colors">
                <span>📦 طرود الكشف</span>
                <span class="px-1.5 py-0.5 rounded-md text-[10px] font-mono"
                      :class="activeTab === 'parcels' ? 'bg-primary text-secondary' : 'bg-slate-100 text-slate-600'"
                      x-text="parcels.length">0</span>
            </button>

            <button type="button"
                    @click="activeTab = 'settlement'"
                    :class="activeTab === 'settlement'
                        ? 'border-b-2 border-emerald-600 text-emerald-800 bg-emerald-50/40'
                        : 'text-slate-500 hover:text-slate-700'"
                    class="flex-1 py-2.5 flex items-center justify-center gap-1.5 transition-colors">
                <span>💸 الخصومات وتصفية السائق</span>
                <span x-show="deductions.length > 0"
                      class="px-1.5 py-0.5 rounded-md text-[10px] font-mono bg-rose-100 text-rose-800"
                      x-text="deductions.length">0</span>
            </button>
        </nav>
    </div>

    <!-- 2. محتوى الصفحة حسب التبويب المختار -->
    <main class="flex-1 p-3 space-y-3">

        <!-- حالة التحميل المبدئية -->
        <div x-show="isLoading" class="py-12 text-center text-slate-400 space-y-2">
            <span class="w-7 h-7 border-2 border-primary border-t-transparent rounded-full animate-spin inline-block"></span>
            <p class="text-xs font-bold">جاري تحميل بيانات الكشف والطرود والخصومات...</p>
        </div>

        <!-- ========================================================= -->
        <!-- التبويب الأول: 📦 طرود الكشف                              -->
        <!-- ========================================================= -->
        <div x-show="!isLoading && activeTab === 'parcels'" class="space-y-2.5 print:hidden">

            <!-- شريط البحث + زر ضم طرود إضافية للكشف + فلاتر (الكل | آجل | محاسب) -->
            <div class="bg-white rounded-2xl p-3 border border-slate-200/80 shadow-2xs space-y-2.5">
                <div class="flex items-center gap-2">
                    <div class="relative flex-1">
                        <input type="text"
                               x-model="parcelSearch"
                               @input="visibleLimit = 30"
                               placeholder="ابحث داخل الكشف بالاسم، الرقم، أو السند..."
                               class="w-full bg-slate-100 focus:bg-white text-slate-800 placeholder-slate-400 text-xs font-bold rounded-xl pr-8 pl-7 py-2 outline-none border border-slate-200 focus:border-secondary">
                        <span class="material-symbols-outlined absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-base pointer-events-none">search</span>
                        <button type="button"
                                x-show="parcelSearch !== ''"
                                x-cloak
                                @click="parcelSearch = ''; visibleLimit = 30"
                                class="absolute left-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                            <span class="material-symbols-outlined text-sm">close</span>
                        </button>
                    </div>

                    <!-- زر + ضم طرود للكشف -->
                    <button type="button"
                            x-show="manifest.status === 'open'"
                            @click="openAttachModal()"
                            class="shrink-0 py-2 px-3 rounded-xl bg-secondary hover:brightness-105 text-primary font-extrabold text-xs flex items-center gap-1 shadow-2xs active:scale-95 transition-all">
                        <span class="material-symbols-outlined text-base">add</span>
                        <span>ضم طرود للكشف</span>
                        <span x-show="unclassifiedParcels.length > 0"
                              class="px-1.5 py-0.2 rounded-md bg-primary text-secondary text-[10px] font-mono"
                              x-text="unclassifiedParcels.length"></span>
                    </button>
                </div>

                <!-- أزرار الفلترة السريعة داخل الكشف: الكل (X) | آجل (Y) | محاسب (Z) -->
                <div class="grid grid-cols-3 gap-1.5 text-[11px] font-extrabold">
                    <button type="button"
                            @click="parcelFilter = 'all'; visibleLimit = 30"
                            :class="parcelFilter === 'all'
                                ? 'bg-primary text-white shadow-2xs'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                            class="py-1.5 px-2 rounded-xl transition-all">
                        الكل (<span x-text="parcels.length">0</span>)
                    </button>

                    <button type="button"
                            @click="parcelFilter = 'postpaid'; visibleLimit = 30"
                            :class="parcelFilter === 'postpaid'
                                ? 'bg-amber-500 text-white shadow-2xs'
                                : 'bg-amber-50 text-amber-800 border border-amber-200 hover:bg-amber-100'"
                            class="py-1.5 px-2 rounded-xl transition-all">
                        آجل (<span x-text="postpaidParcelsCount">0</span>)
                    </button>

                    <button type="button"
                            @click="parcelFilter = 'prepaid'; visibleLimit = 30"
                            :class="parcelFilter === 'prepaid'
                                ? 'bg-emerald-600 text-white shadow-2xs'
                                : 'bg-emerald-50 text-emerald-800 border border-emerald-200 hover:bg-emerald-100'"
                            class="py-1.5 px-2 rounded-xl transition-all">
                        محاسب (<span x-text="prepaidParcelsCount">0</span>)
                    </button>
                </div>
            </div>

            <!-- قائمة بطاقات الطرود داخل الكشف -->
            <div class="space-y-2.5">
                <template x-for="p in visibleParcels" :key="p.id">
                    @include('accountant.manifests.partials.manifest-parcel-card')
                </template>

                <!-- زر عرض المزيد -->
                <div x-show="filteredParcels.length > visibleLimit" x-cloak class="pt-1 text-center">
                    <button type="button"
                            @click="visibleLimit += 30"
                            class="w-full py-2.5 px-4 rounded-xl bg-white border border-slate-200 hover:border-secondary text-xs font-extrabold text-primary shadow-2xs transition-all">
                        عرض المزيد من الطرود (<span x-text="filteredParcels.length - visibleLimit"></span> متبقية)
                    </button>
                </div>

                <!-- حالة عدم وجود طرود في الكشف -->
                <div x-show="filteredParcels.length === 0" x-cloak class="bg-white rounded-2xl border border-slate-200/80 p-8 text-center space-y-3">
                    <span class="material-symbols-outlined text-4xl text-slate-300">inventory_2</span>
                    <p class="text-xs font-bold text-slate-500"
                       x-text="parcels.length === 0 ? 'لا توجد طرود مضافة داخل هذا الكشف حتى الآن' : 'لا توجد طرود مطابقة للبحث أو الفلتر'"></p>
                    <button type="button"
                            x-show="manifest.status === 'open' && unclassifiedParcels.length > 0"
                            @click="openAttachModal()"
                            class="inline-flex items-center gap-1.5 py-2 px-4 rounded-xl bg-secondary text-primary text-xs font-extrabold shadow-sm">
                        <span class="material-symbols-outlined text-base">playlist_add</span>
                        <span>ضم طرود من القائمة غير المصنفة (<span x-text="unclassifiedParcels.length"></span>)</span>
                    </button>
                </div>
            </div>

            <!-- زر انتقال سريع إلى تبويب الخصومات والتصفية -->
            <div x-show="parcels.length > 0" class="pt-1">
                <button type="button"
                        @click="activeTab = 'settlement'"
                        class="w-full py-3 px-4 rounded-2xl bg-primary hover:bg-slate-800 text-white font-extrabold text-xs flex items-center justify-between shadow-md active:scale-98 transition-all">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-lg text-secondary">calculate</span>
                        <span>الانتقال لحساب الخصومات وتصفية السائق</span>
                    </div>
                    <span class="font-mono font-black text-emerald-300 dir-ltr"
                          x-text="'الصافي: ' + formatAmount(manifest.net_driver_amount) + ' ر.ي'"></span>
                </button>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- التبويب الثاني: 💸 الخصومات وتصفية السائق                  -->
        <!-- ========================================================= -->
        <div x-show="!isLoading && activeTab === 'settlement'" class="space-y-3 print:block">

            <!-- أ. قسم إضافة خصم سريع على الكشف (يظهر عندما يكون الكشف مفتوحاً) -->
            <section x-show="manifest.status === 'open'"
                     class="bg-white rounded-2xl p-3.5 border border-slate-200/90 shadow-sm space-y-3 print:hidden">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-extrabold text-slate-800 flex items-center gap-1.5">
                        <span class="w-6 h-6 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center">
                            <span class="material-symbols-outlined text-base">remove_circle</span>
                        </span>
                        <span>إضافة خصم على الكشف (يُخصم من إجمالي الآجل)</span>
                    </h3>
                </div>

                <!-- 3 أزرار اختصار سريعة بضغطة واحدة لتعبئة نوع وعنوان الخصم تلقائياً -->
                <div>
                    <p class="text-[10px] font-bold text-slate-400 mb-1.5">اختيار سريع لنوع الخصم بضغطة واحدة:</p>
                    <div class="grid grid-cols-3 gap-1.5">
                        <button type="button"
                                @click="selectQuickDeduction('workers_unloading', 'خصم العمال (تنزيل الطرود)')"
                                :class="newDeduction.deduction_type === 'workers_unloading'
                                    ? 'bg-primary text-white border-primary ring-2 ring-primary/15'
                                    : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'"
                                class="py-2 px-2 rounded-xl border text-[11px] font-extrabold flex flex-col items-center gap-0.5 transition-all active:scale-95">
                            <span>👷 خصم العمال</span>
                            <span class="text-[9px] opacity-75">تنزيل الطرود</span>
                        </button>

                        <button type="button"
                                @click="selectQuickDeduction('office_commission', 'خصم عمولة المكتب')"
                                :class="newDeduction.deduction_type === 'office_commission'
                                    ? 'bg-primary text-white border-primary ring-2 ring-primary/15'
                                    : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'"
                                class="py-2 px-2 rounded-xl border text-[11px] font-extrabold flex flex-col items-center gap-0.5 transition-all active:scale-95">
                            <span>🏢 خصم المكتب</span>
                            <span class="text-[9px] opacity-75">عمولة المكتب</span>
                        </button>

                        <button type="button"
                                @click="selectQuickDeduction('other', '')"
                                :class="newDeduction.deduction_type === 'other'
                                    ? 'bg-primary text-white border-primary ring-2 ring-primary/15'
                                    : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'"
                                class="py-2 px-2 rounded-xl border text-[11px] font-extrabold flex flex-col items-center gap-0.5 transition-all active:scale-95">
                            <span>➕ خصم آخر</span>
                            <span class="text-[9px] opacity-75">مصاريف / سلفة</span>
                        </button>
                    </div>
                </div>

                <!-- رسالة خطأ إن وجدت -->
                <div x-show="newDeduction.error" x-cloak
                     class="p-2.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold flex items-center gap-2">
                    <span class="material-symbols-outlined text-base shrink-0">error</span>
                    <span x-text="newDeduction.error"></span>
                </div>

                <!-- نموذج إضافة الخصم -->
                <form @submit.prevent="submitNewDeduction()" class="space-y-2.5">
                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-2">
                        <div class="sm:col-span-7">
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">بيان الخصم <span class="text-rose-500">*</span>:</label>
                            <input type="text"
                                   x-ref="newDeductionTitleInput"
                                   x-model="newDeduction.title"
                                   maxlength="150"
                                   required
                                   placeholder="مثال: خصم العمال (تنزيل الطرود)..."
                                   class="w-full bg-slate-50 focus:bg-white border border-slate-200 focus:border-secondary rounded-xl px-3 py-2 text-xs font-bold text-slate-800 outline-none">
                        </div>

                        <div class="sm:col-span-5">
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">مبلغ الخصم (ر.ي) <span class="text-rose-500">*</span>:</label>
                            <div class="relative">
                                <input type="number"
                                       x-ref="newDeductionAmountInput"
                                       x-model="newDeduction.amount"
                                       inputmode="decimal"
                                       min="0.01"
                                       step="any"
                                       required
                                       placeholder="مثال: 2000"
                                       class="w-full bg-rose-50/40 focus:bg-white border border-rose-200 focus:border-rose-500 rounded-xl py-2 pr-3 pl-10 text-sm font-mono font-black text-rose-800 outline-none dir-ltr text-right">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[11px] font-extrabold text-rose-600">ر.ي</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="text"
                               x-model="newDeduction.notes"
                               maxlength="255"
                               placeholder="ملاحظة إضافية على الخصم (اختياري)..."
                               class="flex-1 bg-slate-50 focus:bg-white border border-slate-200 focus:border-secondary rounded-xl px-3 py-2 text-xs font-bold text-slate-800 outline-none">

                        <button type="submit"
                                :disabled="newDeduction.saving"
                                class="shrink-0 py-2 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-extrabold text-xs shadow-sm flex items-center gap-1.5 active:scale-95 transition-all disabled:opacity-60">
                            <span x-show="!newDeduction.saving" class="material-symbols-outlined text-base">add_circle</span>
                            <span x-show="newDeduction.saving" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            <span x-text="newDeduction.saving ? 'جاري الإضافة...' : 'إضافة الخصم للكشف'"></span>
                        </button>
                    </div>
                </form>
            </section>

            <!-- ب. قائمة الخصومات المضافة على هذا الكشف -->
            <section class="bg-white rounded-2xl p-3.5 border border-slate-200/90 shadow-sm space-y-2.5">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-extrabold text-slate-800 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-base text-rose-600">receipt</span>
                        <span>بنود الخصومات المسجلة على الكشف (<span x-text="deductions.length">0</span>)</span>
                    </h3>

                    <span class="text-xs font-black text-rose-700 dir-ltr"
                          x-text="formatAmount(manifest.total_deductions) + ' ر.ي'"></span>
                </div>

                <!-- بنود الخصومات -->
                <template x-if="deductions.length > 0">
                    <div class="divide-y divide-slate-100 border border-slate-200/80 rounded-xl overflow-hidden">
                        <template x-for="d in deductions" :key="d.id">
                            <div class="p-2.5 bg-slate-50/50 hover:bg-slate-50 flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="w-7 h-7 rounded-lg bg-rose-100 text-rose-700 flex items-center justify-center shrink-0 text-xs"
                                          x-text="deductionIcon(d.deduction_type)"></span>
                                    <div class="min-w-0">
                                        <p class="text-xs font-extrabold text-slate-800 truncate" x-text="d.title"></p>
                                        <p x-show="d.notes" class="text-[10px] text-slate-500 truncate" x-text="d.notes"></p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-1.5 shrink-0">
                                    <span class="px-2.5 py-1 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 font-mono text-xs font-black dir-ltr"
                                          x-text="'- ' + formatAmount(d.amount) + ' ر.ي'"></span>

                                    <!-- أزرار التعديل والحذف لبند الخصم (عندما يكون الكشف مفتوحاً) -->
                                    <button type="button"
                                            x-show="manifest.status === 'open'"
                                            @click="openEditDeductionModal(d)"
                                            title="تعديل بند الخصم"
                                            class="w-7 h-7 rounded-lg bg-white hover:bg-slate-100 border border-slate-200 text-slate-600 flex items-center justify-center print:hidden">
                                        <span class="material-symbols-outlined text-[15px]">edit</span>
                                    </button>

                                    <button type="button"
                                            x-show="manifest.status === 'open'"
                                            @click="promptDeleteDeduction(d)"
                                            title="حذف بند الخصم"
                                            class="w-7 h-7 rounded-lg bg-rose-50 hover:bg-rose-100 border border-rose-200 text-rose-600 flex items-center justify-center print:hidden">
                                        <span class="material-symbols-outlined text-[15px]">delete</span>
                                    </button>
                                </div>
                            </div>
                        </template>

                        <!-- إجمالي الخصومات في أسفل القائمة -->
                        <div class="p-2.5 bg-rose-50/70 flex items-center justify-between text-xs font-extrabold text-rose-900">
                            <span>مجموع الخصومات الكلي:</span>
                            <span class="font-mono font-black dir-ltr" x-text="formatAmount(manifest.total_deductions) + ' ر.ي'"></span>
                        </div>
                    </div>
                </template>

                <!-- حالة عدم وجود خصومات -->
                <div x-show="deductions.length === 0" class="py-5 text-center text-slate-400 bg-slate-50/60 rounded-xl border border-dashed border-slate-200">
                    <p class="text-xs font-bold text-slate-500">لا توجد خصومات مضافة على هذا الكشف حتى الآن</p>
                </div>
            </section>

            <!-- ج. بطاقة التصفية النهائية لحساب السائق (Settlement Box) -->
            <section class="bg-white rounded-2xl p-4 border-2 shadow-sm space-y-3.5"
                     :class="manifest.status === 'settled' ? 'border-emerald-300' : 'border-primary/20'">

                <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                    <div class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-xl bg-primary text-secondary flex items-center justify-center">
                            <span class="material-symbols-outlined text-lg">account_balance_wallet</span>
                        </span>
                        <div>
                            <h3 class="text-sm font-black text-slate-800">بطاقة التصفية النهائية لحساب السائق</h3>
                            <p class="text-[10px] text-slate-500">ملخص الحساب الصافي الواجب تسليمه للسائق</p>
                        </div>
                    </div>
                </div>

                <!-- جدول المعادلة النهائية -->
                <div class="space-y-2 text-xs">
                    <!-- 1. مجموع طرود الآجل -->
                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-amber-50/70 border border-amber-200/80">
                        <div class="flex items-center gap-1.5 font-extrabold text-amber-950">
                            <span class="material-symbols-outlined text-base text-amber-600">payments</span>
                            <span>مجموع طرود الآجل (<span x-text="postpaidParcelsCount">0</span> طرد آجل من أصل <span x-text="parcels.length">0</span>):</span>
                        </div>
                        <span class="font-mono font-black text-sm text-amber-950 dir-ltr"
                              x-text="formatAmount(manifest.total_postpaid_amount) + ' ر.ي'"></span>
                    </div>

                    <!-- 2. ناقص مجموع الخصومات -->
                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-rose-50/70 border border-rose-200/80">
                        <div class="flex items-center gap-1.5 font-extrabold text-rose-900">
                            <span class="material-symbols-outlined text-base text-rose-600">remove_circle</span>
                            <span>ناقص مجموع الخصومات (<span x-text="deductions.length">0</span> بند):</span>
                        </div>
                        <span class="font-mono font-black text-sm text-rose-800 dir-ltr"
                              x-text="'- ' + formatAmount(manifest.total_deductions) + ' ر.ي'"></span>
                    </div>

                    <!-- 3. المبلغ الصافي الواجب تسليمه للسائق -->
                    <div class="flex items-center justify-between p-3.5 rounded-2xl bg-emerald-600 text-white shadow-md">
                        <div class="flex items-center gap-2 font-black text-xs sm:text-sm">
                            <span class="material-symbols-outlined text-xl text-emerald-200">verified</span>
                            <span>الصافي الواجب تسليمه للسائق:</span>
                        </div>
                        <span class="font-mono font-black text-base sm:text-lg dir-ltr"
                              x-text="formatAmount(manifest.net_driver_amount) + ' ر.ي'"></span>
                    </div>
                </div>

                <!-- ملاحظات الكشف / التصفية -->
                <div x-show="manifest.status === 'open'" class="print:hidden">
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">ملاحظات التصفية / الاستلام (اختياري):</label>
                    <input type="text"
                           x-model="manifest.notes"
                           maxlength="500"
                           placeholder="مثال: تم تسليم المبلغ نقداً للسائق يداً بيد..."
                           class="w-full bg-slate-50 focus:bg-white border border-slate-200 focus:border-secondary rounded-xl px-3 py-2 text-xs font-bold text-slate-800 outline-none">
                </div>
                <p x-show="manifest.notes && manifest.status === 'settled'"
                   class="text-xs font-bold text-slate-600 bg-slate-50 p-2.5 rounded-xl border border-slate-200"
                   x-text="'ملاحظة التصفية: ' + manifest.notes"></p>

                <!-- الزر الرئيسي الكبير لاعتماد التصفية أو شارة التصفية المعتمدة -->
                <div class="pt-1 space-y-2.5 print:hidden">
                    <template x-if="manifest.status === 'open'">
                        <button type="button"
                                @click="promptToggleSettlement()"
                                :disabled="settling"
                                class="w-full py-3.5 px-4 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs sm:text-sm shadow-lg shadow-emerald-600/25 flex items-center justify-center gap-2 active:scale-98 transition-all disabled:opacity-60">
                            <span x-show="!settling" class="material-symbols-outlined text-xl">check_circle</span>
                            <span x-show="settling" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            <span x-text="settling
                                ? 'جاري اعتماد تصفية الكشف...'
                                : '✅ اعتماد تصفية الكشف وتسليم السائق (' + formatAmount(manifest.net_driver_amount) + ' ر.ي)'"></span>
                        </button>
                    </template>

                    <template x-if="manifest.status === 'settled'">
                        <div class="p-3 rounded-2xl bg-emerald-50 border border-emerald-300 flex items-center justify-between gap-2 flex-wrap">
                            <div class="flex items-center gap-2 text-emerald-900">
                                <span class="material-symbols-outlined text-xl text-emerald-600">verified</span>
                                <div>
                                    <p class="text-xs font-black">تمت تصفية الكشف وتسليم السائق بنجاح</p>
                                    <p class="text-[10px] font-bold text-emerald-700 mt-0.5">
                                        <span>بتاريخ: </span>
                                        <span class="font-mono dir-ltr" x-text="manifest.settled_at || 'الآن'"></span>
                                        <span x-show="manifest.accountant_name" x-text="' • بواسطة: ' + manifest.accountant_name"></span>
                                    </p>
                                </div>
                            </div>

                            <button type="button"
                                    @click="promptToggleSettlement()"
                                    :disabled="settling"
                                    class="py-1.5 px-3 rounded-xl bg-white hover:bg-amber-50 text-amber-800 border border-amber-300 text-[11px] font-extrabold flex items-center gap-1 transition-colors">
                                <span class="material-symbols-outlined text-sm">lock_open</span>
                                <span>إعادة فتح الكشف</span>
                            </button>
                        </div>
                    </template>

                    <!-- أزرار مشاركة كشف التصفية عبر واتساب أو النسخ أو الطباعة -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 pt-1">
                        <button type="button"
                                @click="shareSettlementViaWhatsApp()"
                                class="col-span-2 sm:col-span-1 py-2.5 px-3 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-300 font-extrabold text-xs flex items-center justify-center gap-1.5 active:scale-95 transition-all">
                            <span class="material-symbols-outlined text-base text-emerald-600">share</span>
                            <span>📲 مشاركة التصفية واتساب</span>
                        </button>

                        <button type="button"
                                @click="copySettlementSummary()"
                                class="py-2.5 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 font-extrabold text-xs flex items-center justify-center gap-1.5 active:scale-95 transition-all">
                            <span class="material-symbols-outlined text-base text-slate-600">content_copy</span>
                            <span>نسخ الملخص</span>
                        </button>

                        <button type="button"
                                @click="window.print()"
                                class="py-2.5 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 font-extrabold text-xs flex items-center justify-center gap-1.5 active:scale-95 transition-all">
                            <span class="material-symbols-outlined text-base text-slate-600">print</span>
                            <span>🖨️ طباعة الكشف</span>
                        </button>
                    </div>
                </div>
            </section>

        </div>

    </main>

    <!-- النوافذ المنبثقة (Modals) -->
    <!-- 1. نافذة تعديل الطرد للمحاسب -->
    @include('accountant.parcels.partials.edit-parcel-modal')

    <!-- 2. نافذة سجل تعديلات الطرد -->
    @include('accountant.parcels.partials.edit-logs-modal')

    <!-- 3. نافذة ضم طرود إضافية من غير المصنفة -->
    @include('accountant.manifests.partials.attach-unclassified-modal')

    <!-- 4. نافذة تعديل بند الخصم -->
    @include('accountant.manifests.partials.edit-deduction-modal')

    <!-- 5. نافذة تعديل بيانات الكشف (السائق / المكتب المرسل / التاريخ) -->
    @include('accountant.manifests.partials.manifest-modal')

    <!-- 6. نافذة التأكيد للعمليات الحساسة -->
    @include('accountant.manifests.partials.confirm-action-modal')
@endsection

@push('scripts')
<script>
    function accountantManifestShowApp(manifestId) {
        const baseShell = typeof window.accountantShell === 'function' ? window.accountantShell() : {};

        return {
            ...baseShell,

            manifestId: manifestId,
            isLoading: true,
            activeTab: 'parcels', // parcels | settlement
            parcelFilter: 'all', // all | postpaid | prepaid
            parcelSearch: '',
            visibleLimit: 30,
            settling: false,
            showDriverSuggestions: false,

            manifest: {
                id: manifestId,
                driver_id: {{ $manifest->driver_id ?? 'null' }},
                driver_name: @json($manifest->driver_name),
                driver_phone: @json($manifest->driver_phone),
                source_office_name: @json($manifest->source_office_name),
                source_branch_name: @json($manifest->source_branch_name),
                manifest_number: @json($manifest->manifest_number),
                manifest_date: @json($manifest->manifest_date?->format('Y-m-d')),
                total_postpaid_amount: {{ (float) ($manifest->total_postpaid_amount ?? 0) }},
                total_deductions: {{ (float) ($manifest->total_deductions ?? 0) }},
                net_driver_amount: {{ (float) ($manifest->net_driver_amount ?? 0) }},
                status: @json($manifest->status ?: 'open'),
                settled_at: @json($manifest->settled_at?->format('Y-m-d h:i A')),
                notes: @json($manifest->notes),
                accountant_name: null,
            },

            parcels: [],
            deductions: [],
            unclassifiedParcels: [],
            drivers: [],

            newDeduction: {
                deduction_type: 'workers_unloading',
                title: 'خصم العمال (تنزيل الطرود)',
                amount: '',
                notes: '',
                saving: false,
                error: '',
            },

            editDeductionModal: {
                open: false,
                saving: false,
                error: '',
                deductionId: null,
                form: {
                    deduction_type: 'other',
                    title: '',
                    amount: '',
                    notes: '',
                },
            },

            editModal: {
                open: false,
                saving: false,
                error: '',
                parcelId: null,
                form: {
                    recipient_name: '',
                    recipient_phone: '',
                    package_type: 'طرد',
                    receipt_number: '',
                    source_office_name: '',
                    payment_type: 'prepaid',
                    cod_amount: '',
                },
            },

            logsModal: {
                open: false,
                loading: false,
                parcel: null,
                logs: [],
            },

            attachModal: {
                open: false,
                saving: false,
                error: '',
                search: '',
                selectedIds: [],
            },

            manifestModal: {
                open: false,
                mode: 'edit',
                saving: false,
                error: '',
                manifestId: manifestId,
                attachParcelsCount: 0,
                attachPostpaidAmount: 0,
                form: {
                    driver_id: '',
                    driver_name: '',
                    driver_phone: '',
                    source_office_name: '',
                    source_branch_name: '',
                    manifest_number: '',
                    manifest_date: '',
                    notes: '',
                },
            },

            confirmModal: {
                open: false,
                loading: false,
                title: '',
                message: '',
                confirmText: 'تأكيد',
                variant: 'rose', // rose | emerald | amber
                icon: 'help',
                onConfirm: null,
            },

            async init() {
                if (typeof baseShell.init === 'function') {
                    await baseShell.init.call(this);
                }
                await this.fetchManifestDetails();
            },

            async refreshData() {
                await this.fetchManifestDetails();
            },

            async fetchManifestDetails() {
                this.isLoading = true;
                this.isSyncing = true;
                try {
                    const res = await fetch(`/accountant/api/manifests/${encodeURIComponent(this.manifestId)}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (res.status === 401 || res.status === 419) {
                        this.authModal.open = true;
                        return;
                    }

                    const data = await res.json();
                    if (data && data.success) {
                        if (data.manifest) {
                            this.manifest = data.manifest;
                        }
                        this.parcels = Array.isArray(data.parcels) ? data.parcels : [];
                        this.deductions = Array.isArray(data.deductions) ? data.deductions : [];
                        this.unclassifiedParcels = Array.isArray(data.unclassified_parcels) ? data.unclassified_parcels : [];
                        this.drivers = Array.isArray(data.drivers) ? data.drivers : [];
                    }
                } catch (e) {
                    this.showToast('تعذر الاتصال بالسيرفر لجلب تفاصيل الكشف.', true);
                } finally {
                    this.isLoading = false;
                    this.isSyncing = false;
                }
            },

            get postpaidParcelsCount() {
                return this.parcels.filter((p) => p.payment_type === 'postpaid').length;
            },

            get prepaidParcelsCount() {
                return this.parcels.length - this.postpaidParcelsCount;
            },

            get filteredParcels() {
                const q = this.normalizeSearch(this.parcelSearch);

                return this.parcels.filter((p) => {
                    if (this.parcelFilter === 'postpaid' && p.payment_type !== 'postpaid') return false;
                    if (this.parcelFilter === 'prepaid' && p.payment_type !== 'prepaid') return false;

                    if (!q) return true;

                    const haystack = [
                        p.recipient_phone || '',
                        p.recipient_name || '',
                        p.receipt_number || '',
                        p.package_type || '',
                        p.source_office_name || '',
                    ].join(' ').toLowerCase();

                    return this.normalizeSearch(haystack).includes(q);
                });
            },

            get visibleParcels() {
                return this.filteredParcels.slice(0, this.visibleLimit);
            },

            get filteredUnclassifiedParcels() {
                const q = this.normalizeSearch(this.attachModal.search);
                if (!q) return this.unclassifiedParcels;

                return this.unclassifiedParcels.filter((p) => {
                    const haystack = [
                        p.recipient_phone || '',
                        p.recipient_name || '',
                        p.receipt_number || '',
                        p.package_type || '',
                        p.source_office_name || '',
                    ].join(' ').toLowerCase();

                    return this.normalizeSearch(haystack).includes(q);
                });
            },

            get driverSuggestions() {
                const typed = (this.manifestModal.form.driver_name || '').trim().toLowerCase();
                if (!typed) return this.drivers.slice(0, 6);
                return this.drivers.filter((d) =>
                    (d.name || '').toLowerCase().includes(typed) ||
                    (d.phone || '').includes(typed)
                ).slice(0, 6);
            },

            recalculateLocalTotals() {
                let totalPostpaid = 0;
                for (const p of this.parcels) {
                    if (p.payment_type === 'postpaid') {
                        totalPostpaid += Number(p.cod_amount || 0);
                    }
                }

                let totalDeductions = 0;
                for (const d of this.deductions) {
                    totalDeductions += Number(d.amount || 0);
                }

                this.manifest.total_postpaid_amount = Math.round(totalPostpaid * 100) / 100;
                this.manifest.total_deductions = Math.round(totalDeductions * 100) / 100;
                this.manifest.net_driver_amount = Math.round((totalPostpaid - totalDeductions) * 100) / 100;
            },

            /* =========================================================
             * 1. تعديل الطرد وسجل التعديلات وإخراج الطرد من الكشف
             * ========================================================= */
            openEditModal(parcel, focusField = null) {
                this.editModal.error = '';
                this.editModal.parcelId = parcel.id;
                this.editModal.form = {
                    recipient_name: parcel.recipient_name || '',
                    recipient_phone: parcel.recipient_phone || '',
                    package_type: parcel.package_type || 'طرد',
                    receipt_number: parcel.receipt_number || '',
                    source_office_name: parcel.source_office_name || '',
                    payment_type: parcel.payment_type === 'postpaid' ? 'postpaid' : 'prepaid',
                    cod_amount: parcel.payment_type === 'postpaid' && parcel.cod_amount !== null ? parcel.cod_amount : '',
                };
                this.editModal.open = true;

                this.$nextTick(() => {
                    if (focusField === 'recipient_name' && this.$refs.editRecipientNameInput) {
                        this.$refs.editRecipientNameInput.focus();
                    }
                });
            },

            closeEditModal() {
                if (this.editModal.saving) return;
                this.editModal.open = false;
                this.editModal.error = '';
            },

            async submitParcelEdit() {
                if (this.editModal.saving || !this.editModal.parcelId) return;

                this.editModal.error = '';
                this.editModal.saving = true;

                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const url = `/accountant/api/parcels/${encodeURIComponent(this.editModal.parcelId)}`;

                try {
                    const res = await fetch(url, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token || '',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify(this.editModal.form),
                    });

                    if (res.status === 401 || res.status === 419) {
                        this.editModal.open = false;
                        this.authModal.open = true;
                        return;
                    }

                    const data = await res.json();

                    if (!res.ok || !data.success) {
                        if (data.errors) {
                            const firstError = Object.values(data.errors).flat()[0];
                            this.editModal.error = firstError || 'يرجى التأكد من صحة البيانات المدخلة.';
                        } else {
                            this.editModal.error = data.message || 'تعذر حفظ التعديلات.';
                        }
                        return;
                    }

                    const idx = this.parcels.findIndex((item) => item.id === this.editModal.parcelId);
                    if (idx !== -1 && data.parcel) {
                        this.parcels.splice(idx, 1, data.parcel);
                    }

                    if (data.manifest) {
                        this.manifest.total_postpaid_amount = data.manifest.total_postpaid_amount;
                        this.manifest.total_deductions = data.manifest.total_deductions;
                        this.manifest.net_driver_amount = data.manifest.net_driver_amount;
                    } else {
                        this.recalculateLocalTotals();
                    }

                    this.editModal.open = false;
                    this.showToast(data.message || 'تم حفظ تعديلات الطرد وتحديث مجاميع الكشف.', false);
                } catch (err) {
                    this.editModal.error = 'تعذر الاتصال بالسيرفر، يرجى التحقق من اتصال الإنترنت.';
                } finally {
                    this.editModal.saving = false;
                }
            },

            async openLogsModal(parcel) {
                this.logsModal.parcel = parcel;
                this.logsModal.logs = [];
                this.logsModal.loading = true;
                this.logsModal.open = true;

                try {
                    const res = await fetch(`/accountant/api/parcels/${encodeURIComponent(parcel.id)}/logs`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (res.status === 401 || res.status === 419) {
                        this.logsModal.open = false;
                        this.authModal.open = true;
                        return;
                    }

                    const data = await res.json();
                    if (data && data.success) {
                        this.logsModal.logs = Array.isArray(data.logs) ? data.logs : [];
                    }
                } catch (e) {
                    this.showToast('تعذر تحميل سجل التعديلات.', true);
                } finally {
                    this.logsModal.loading = false;
                }
            },

            promptRemoveParcel(parcel) {
                if (this.manifest.status === 'settled') return;
                const label = parcel.recipient_name ? `${parcel.recipient_name} (${parcel.recipient_phone})` : parcel.recipient_phone;
                this.openConfirmModal({
                    title: 'إخراج الطرد من الكشف',
                    message: `هل تريد إخراج طرد المستلم (${label}) من هذا الكشف وإعادته إلى قائمة الطرود غير المصنفة؟`,
                    confirmText: 'نعم، إخراج من الكشف',
                    variant: 'rose',
                    icon: 'output',
                    onConfirm: async () => {
                        await this.removeParcelFromManifest(parcel.id);
                    },
                });
            },

            async removeParcelFromManifest(parcelId) {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const url = `/accountant/api/manifests/${encodeURIComponent(this.manifestId)}/parcels/${encodeURIComponent(parcelId)}`;

                const res = await fetch(url, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token || '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (res.status === 401 || res.status === 419) {
                    this.authModal.open = true;
                    return;
                }

                const data = await res.json();
                if (!res.ok || !data.success) {
                    this.showToast(data.message || 'تعذر إخراج الطرد من الكشف.', true);
                    return;
                }

                this.parcels = this.parcels.filter((p) => p.id !== parcelId);
                if (data.removed_parcel) {
                    this.unclassifiedParcels.unshift(data.removed_parcel);
                }
                if (data.manifest) {
                    this.manifest = data.manifest;
                } else {
                    this.recalculateLocalTotals();
                }

                this.showToast(data.message || 'تم إخراج الطرد من الكشف بنجاح.', false);
            },

            /* =========================================================
             * 2. ضم طرود إضافية من غير المصنفة إلى هذا الكشف
             * ========================================================= */
            openAttachModal() {
                if (this.manifest.status === 'settled') return;
                this.attachModal.error = '';
                this.attachModal.search = '';
                this.attachModal.selectedIds = [];
                this.attachModal.open = true;
            },

            closeAttachModal() {
                if (this.attachModal.saving) return;
                this.attachModal.open = false;
                this.attachModal.error = '';
            },

            toggleSelectAllUnclassified() {
                const filteredIds = this.filteredUnclassifiedParcels.map((p) => p.id);
                if (this.attachModal.selectedIds.length === filteredIds.length) {
                    this.attachModal.selectedIds = [];
                } else {
                    this.attachModal.selectedIds = filteredIds;
                }
            },

            async submitAttachParcels() {
                if (this.attachModal.saving || this.attachModal.selectedIds.length === 0) return;

                this.attachModal.error = '';
                this.attachModal.saving = true;

                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const url = `/accountant/api/manifests/${encodeURIComponent(this.manifestId)}/assign-parcels`;

                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token || '',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            parcel_ids: this.attachModal.selectedIds,
                        }),
                    });

                    if (res.status === 401 || res.status === 419) {
                        this.attachModal.open = false;
                        this.authModal.open = true;
                        return;
                    }

                    const data = await res.json();
                    if (!res.ok || !data.success) {
                        this.attachModal.error = data.message || 'تعذر ضم الطرود المحددة.';
                        return;
                    }

                    // نقل الطرود المضمومة محلياً فوراً دون الحاجة لطلب شبكة إضافي
                    const attachedSet = new Set(Array.isArray(data.attached_ids) ? data.attached_ids : this.attachModal.selectedIds);
                    const movedParcels = [];
                    const remainingUnclassified = [];

                    const defaultSource = this.manifest.source_branch_name
                        ? `${this.manifest.source_office_name} - ${this.manifest.source_branch_name}`
                        : (this.manifest.source_office_name || '');

                    for (const up of this.unclassifiedParcels) {
                        if (attachedSet.has(up.id)) {
                            movedParcels.push({
                                ...up,
                                manifest_id: this.manifestId,
                                source_office_name: up.source_office_name || defaultSource,
                                edit_logs_count: Number(up.edit_logs_count || 0) + 1,
                            });
                        } else {
                            remainingUnclassified.push(up);
                        }
                    }

                    this.unclassifiedParcels = remainingUnclassified;
                    this.parcels = [...movedParcels, ...this.parcels];

                    if (data.manifest) {
                        this.manifest = data.manifest;
                    } else {
                        this.recalculateLocalTotals();
                    }

                    this.attachModal.open = false;
                    this.showToast(data.message || 'تم ضم الطرود إلى الكشف بنجاح.', false);
                } catch (err) {
                    this.attachModal.error = 'تعذر الاتصال بالسيرفر، يرجى التحقق من اتصال الإنترنت.';
                } finally {
                    this.attachModal.saving = false;
                }
            },

            /* =========================================================
             * 3. إدارة الخصومات (إضافة / تعديل / حذف)
             * ========================================================= */
            selectQuickDeduction(type, defaultTitle) {
                this.newDeduction.deduction_type = type;
                this.newDeduction.title = defaultTitle;
                this.newDeduction.error = '';

                this.$nextTick(() => {
                    if (defaultTitle) {
                        this.$refs.newDeductionAmountInput?.focus();
                    } else {
                        this.$refs.newDeductionTitleInput?.focus();
                    }
                });
            },

            deductionIcon(type) {
                if (type === 'workers_unloading') return '👷';
                if (type === 'office_commission') return '🏢';
                return '➖';
            },

            async submitNewDeduction() {
                if (this.newDeduction.saving) return;

                this.newDeduction.error = '';
                this.newDeduction.saving = true;

                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const url = `/accountant/api/manifests/${encodeURIComponent(this.manifestId)}/deductions`;

                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token || '',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            deduction_type: this.newDeduction.deduction_type,
                            title: this.newDeduction.title,
                            amount: this.newDeduction.amount,
                            notes: this.newDeduction.notes,
                        }),
                    });

                    if (res.status === 401 || res.status === 419) {
                        this.authModal.open = true;
                        return;
                    }

                    const data = await res.json();
                    if (!res.ok || !data.success) {
                        if (data.errors) {
                            this.newDeduction.error = Object.values(data.errors).flat()[0] || 'يرجى التحقق من بيانات الخصم.';
                        } else {
                            this.newDeduction.error = data.message || 'تعذر إضافة بند الخصم.';
                        }
                        return;
                    }

                    if (data.deduction) {
                        this.deductions.push(data.deduction);
                    }
                    if (data.manifest) {
                        this.manifest = data.manifest;
                    } else {
                        this.recalculateLocalTotals();
                    }

                    // تفريغ حقل المبلغ والملاحظة للاستعداد لإضافة خصم تالٍ بسرعة
                    this.newDeduction.amount = '';
                    this.newDeduction.notes = '';
                    this.showToast(data.message || 'تم إضافة بند الخصم بنجاح.', false);
                } catch (err) {
                    this.newDeduction.error = 'تعذر الاتصال بالسيرفر، يرجى التحقق من اتصال الإنترنت.';
                } finally {
                    this.newDeduction.saving = false;
                }
            },

            openEditDeductionModal(deduction) {
                if (this.manifest.status === 'settled') return;
                this.editDeductionModal.error = '';
                this.editDeductionModal.deductionId = deduction.id;
                this.editDeductionModal.form = {
                    deduction_type: deduction.deduction_type || 'other',
                    title: deduction.title || '',
                    amount: deduction.amount || '',
                    notes: deduction.notes || '',
                };
                this.editDeductionModal.open = true;
            },

            closeEditDeductionModal() {
                if (this.editDeductionModal.saving) return;
                this.editDeductionModal.open = false;
                this.editDeductionModal.error = '';
            },

            async submitEditDeduction() {
                if (this.editDeductionModal.saving || !this.editDeductionModal.deductionId) return;

                this.editDeductionModal.error = '';
                this.editDeductionModal.saving = true;

                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const url = `/accountant/api/manifests/${encodeURIComponent(this.manifestId)}/deductions/${encodeURIComponent(this.editDeductionModal.deductionId)}`;

                try {
                    const res = await fetch(url, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token || '',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify(this.editDeductionModal.form),
                    });

                    if (res.status === 401 || res.status === 419) {
                        this.editDeductionModal.open = false;
                        this.authModal.open = true;
                        return;
                    }

                    const data = await res.json();
                    if (!res.ok || !data.success) {
                        if (data.errors) {
                            this.editDeductionModal.error = Object.values(data.errors).flat()[0] || 'يرجى التحقق من بيانات الخصم.';
                        } else {
                            this.editDeductionModal.error = data.message || 'تعذر تحديث بند الخصم.';
                        }
                        return;
                    }

                    const idx = this.deductions.findIndex((item) => item.id === this.editDeductionModal.deductionId);
                    if (idx !== -1 && data.deduction) {
                        this.deductions.splice(idx, 1, data.deduction);
                    }
                    if (data.manifest) {
                        this.manifest = data.manifest;
                    } else {
                        this.recalculateLocalTotals();
                    }

                    this.editDeductionModal.open = false;
                    this.showToast(data.message || 'تم تحديث بند الخصم بنجاح.', false);
                } catch (err) {
                    this.editDeductionModal.error = 'تعذر الاتصال بالسيرفر، يرجى التحقق من اتصال الإنترنت.';
                } finally {
                    this.editDeductionModal.saving = false;
                }
            },

            promptDeleteDeduction(deduction) {
                if (this.manifest.status === 'settled') return;
                this.openConfirmModal({
                    title: 'حذف بند الخصم',
                    message: `هل أنت متأكد من حذف بند الخصم (${deduction.title} — ${this.formatAmount(deduction.amount)} ر.ي)؟`,
                    confirmText: 'نعم، حذف الخصم',
                    variant: 'rose',
                    icon: 'delete',
                    onConfirm: async () => {
                        await this.deleteDeduction(deduction.id);
                    },
                });
            },

            async deleteDeduction(deductionId) {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const url = `/accountant/api/manifests/${encodeURIComponent(this.manifestId)}/deductions/${encodeURIComponent(deductionId)}`;

                const res = await fetch(url, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token || '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (res.status === 401 || res.status === 419) {
                    this.authModal.open = true;
                    return;
                }

                const data = await res.json();
                if (!res.ok || !data.success) {
                    this.showToast(data.message || 'تعذر حذف بند الخصم.', true);
                    return;
                }

                this.deductions = this.deductions.filter((d) => d.id !== deductionId);
                if (data.manifest) {
                    this.manifest = data.manifest;
                } else {
                    this.recalculateLocalTotals();
                }

                this.showToast(data.message || 'تم حذف بند الخصم بنجاح.', false);
            },

            /* =========================================================
             * 4. اعتماد تصفية الكشف وتسليم السائق / إعادة فتح الكشف
             * ========================================================= */
            promptToggleSettlement() {
                if (this.manifest.status === 'open') {
                    this.openConfirmModal({
                        title: 'اعتماد تصفية الكشف وتسليم السائق',
                        message: `سيتم اعتماد تصفية كشف السائق (${this.manifest.driver_name}) بصافي مستحق قدره (${this.formatAmount(this.manifest.net_driver_amount)} ر.ي) وإغلاق الكشف. هل تود المتابعة؟`,
                        confirmText: '✅ نعم، اعتماد التصفية الآن',
                        variant: 'emerald',
                        icon: 'verified',
                        onConfirm: async () => {
                            await this.toggleSettlement();
                        },
                    });
                } else {
                    this.openConfirmModal({
                        title: 'إعادة فتح الكشف للمراجعة',
                        message: `هل تريد إعادة فتح كشف السائق (${this.manifest.driver_name}) للسماح بتعديل الطرود أو الخصومات؟`,
                        confirmText: 'نعم، إعادة فتح الكشف',
                        variant: 'amber',
                        icon: 'lock_open',
                        onConfirm: async () => {
                            await this.toggleSettlement();
                        },
                    });
                }
            },

            async toggleSettlement() {
                this.settling = true;
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const url = `/accountant/api/manifests/${encodeURIComponent(this.manifestId)}/toggle-settlement`;

                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token || '',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            notes: this.manifest.notes,
                        }),
                    });

                    if (res.status === 401 || res.status === 419) {
                        this.authModal.open = true;
                        return;
                    }

                    const data = await res.json();
                    if (!res.ok || !data.success) {
                        this.showToast(data.message || 'تعذر تغيير حالة تصفية الكشف.', true);
                        return;
                    }

                    if (data.manifest) {
                        this.manifest = data.manifest;
                    }
                    this.showToast(data.message || 'تم تحديث حالة التصفية بنجاح.', false);
                } catch (err) {
                    this.showToast('تعذر الاتصال بالسيرفر، يرجى التحقق من اتصال الإنترنت.', true);
                } finally {
                    this.settling = false;
                }
            },

            /* =========================================================
             * 5. مشاركة ملخص التصفية عبر واتساب أو النسخ
             * ========================================================= */
            buildSettlementSummaryText() {
                const lines = [];
                lines.push(`*📋 كشف تصفية حساب سائق — #${this.manifest.manifest_number || this.manifest.id}*`);
                lines.push(`━━━━━━━━━━━━━━━━━━━━`);
                lines.push(`👨‍✈️ *السائق:* ${this.manifest.driver_name || '-'}`);
                if (this.manifest.driver_phone) {
                    lines.push(`📞 *جوال السائق:* ${this.manifest.driver_phone}`);
                }
                const sourceText = this.manifest.source_branch_name
                    ? `${this.manifest.source_office_name} (${this.manifest.source_branch_name})`
                    : (this.manifest.source_office_name || '-');
                lines.push(`🏢 *المكتب المرسل:* ${sourceText}`);
                lines.push(`📅 *التاريخ:* ${this.manifest.manifest_date || '-'}`);
                lines.push(`📦 *إجمالي الطرود:* ${this.parcels.length} طرد (آجل: ${this.postpaidParcelsCount} | محاسب: ${this.prepaidParcelsCount})`);
                lines.push(`━━━━━━━━━━━━━━━━━━━━`);
                lines.push(`💰 *مجموع طرود الآجل:* ${this.formatAmount(this.manifest.total_postpaid_amount)} ر.ي`);

                if (this.deductions.length > 0) {
                    lines.push(`➖ *الخصومات:*`);
                    for (const d of this.deductions) {
                        lines.push(`  • ${d.title}: ${this.formatAmount(d.amount)} ر.ي`);
                    }
                    lines.push(`📉 *إجمالي الخصومات:* ${this.formatAmount(this.manifest.total_deductions)} ر.ي`);
                } else {
                    lines.push(`➖ *إجمالي الخصومات:* 0 ر.ي`);
                }

                lines.push(`━━━━━━━━━━━━━━━━━━━━`);
                lines.push(`✅ *الصافي المسلم للسائق:* *${this.formatAmount(this.manifest.net_driver_amount)} ر.ي*`);
                if (this.manifest.notes) {
                    lines.push(`📝 *ملاحظات:* ${this.manifest.notes}`);
                }
                lines.push(`حالة الكشف: ${this.manifest.status === 'settled' ? 'تمت التصفية ✔️' : 'قيد المراجعة ⏳'}`);

                return lines.join('\n');
            },

            shareSettlementViaWhatsApp() {
                const text = this.buildSettlementSummaryText();
                const encoded = encodeURIComponent(text);
                const phone = (this.manifest.driver_phone || '').replace(/\D+/g, '');
                const intlPhone = phone.length === 9 && phone.startsWith('7') ? `967${phone}` : phone;

                const waUrl = intlPhone
                    ? `https://wa.me/${intlPhone}?text=${encoded}`
                    : `https://wa.me/?text=${encoded}`;

                window.open(waUrl, '_blank', 'noopener,noreferrer');
            },

            async copySettlementSummary() {
                const text = this.buildSettlementSummaryText();
                try {
                    await navigator.clipboard.writeText(text);
                    this.showToast('تم نسخ ملخص تصفية الكشف إلى الحافظة.', false);
                } catch (e) {
                    this.showToast('تعذر نسخ النص تلقائياً.', true);
                }
            },

            /* =========================================================
             * 6. تعديل بيانات الكشف الأساسية (السائق / المصدر / التاريخ)
             * ========================================================= */
            selectSavedDriver(driverId) {
                if (!driverId) {
                    this.manifestModal.form.driver_id = '';
                    this.manifestModal.form.driver_name = '';
                    this.manifestModal.form.driver_phone = '';
                    this.$nextTick(() => this.$refs.manifestDriverNameInput?.focus());
                    return;
                }
                const found = this.drivers.find((d) => String(d.id) === String(driverId));
                if (found) {
                    this.manifestModal.form.driver_id = found.id;
                    this.manifestModal.form.driver_name = found.name || '';
                    this.manifestModal.form.driver_phone = found.phone || '';
                    this.showDriverSuggestions = false;
                }
            },

            onDriverNameTyped() {
                this.manifestModal.form.driver_id = '';
                this.showDriverSuggestions = true;
            },

            applyDriverSuggestion(drv) {
                this.manifestModal.form.driver_id = drv.id;
                this.manifestModal.form.driver_name = drv.name || '';
                this.manifestModal.form.driver_phone = drv.phone || '';
                this.showDriverSuggestions = false;
            },

            openEditManifestModal() {
                if (this.manifest.status === 'settled') return;
                this.manifestModal.mode = 'edit';
                this.manifestModal.manifestId = this.manifest.id;
                this.manifestModal.error = '';
                this.manifestModal.form = {
                    driver_id: this.manifest.driver_id || '',
                    driver_name: this.manifest.driver_name || '',
                    driver_phone: this.manifest.driver_phone || '',
                    source_office_name: this.manifest.source_office_name || '',
                    source_branch_name: this.manifest.source_branch_name || '',
                    manifest_number: this.manifest.manifest_number || '',
                    manifest_date: this.manifest.manifest_date || new Date().toISOString().slice(0, 10),
                    notes: this.manifest.notes || '',
                };
                this.showDriverSuggestions = false;
                this.manifestModal.open = true;
            },

            closeManifestModal() {
                if (this.manifestModal.saving) return;
                this.manifestModal.open = false;
                this.manifestModal.error = '';
                this.showDriverSuggestions = false;
            },

            async submitManifestForm() {
                if (this.manifestModal.saving) return;

                this.manifestModal.error = '';
                this.manifestModal.saving = true;

                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const url = `/accountant/api/manifests/${encodeURIComponent(this.manifestId)}`;

                try {
                    const res = await fetch(url, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token || '',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify(this.manifestModal.form),
                    });

                    if (res.status === 401 || res.status === 419) {
                        this.manifestModal.open = false;
                        this.authModal.open = true;
                        return;
                    }

                    const data = await res.json();
                    if (!res.ok || !data.success) {
                        if (data.errors) {
                            this.manifestModal.error = Object.values(data.errors).flat()[0] || 'يرجى التأكد من صحة البيانات.';
                        } else {
                            this.manifestModal.error = data.message || 'تعذر حفظ تعديلات الكشف.';
                        }
                        return;
                    }

                    if (data.manifest) {
                        this.manifest = data.manifest;
                    }
                    if (data.driver && data.driver.id) {
                        const dIdx = this.drivers.findIndex((d) => d.id === data.driver.id);
                        if (dIdx !== -1) {
                            this.drivers.splice(dIdx, 1, data.driver);
                        } else {
                            this.drivers.push(data.driver);
                        }
                    }

                    this.manifestModal.open = false;
                    this.showToast(data.message || 'تم تحديث بيانات الكشف بنجاح.', false);
                } catch (err) {
                    this.manifestModal.error = 'تعذر الاتصال بالسيرفر، يرجى التحقق من اتصال الإنترنت.';
                } finally {
                    this.manifestModal.saving = false;
                }
            },

            /* =========================================================
             * 7. نافذة التأكيد الموحدة والدوال المساعدة
             * ========================================================= */
            openConfirmModal({ title, message, confirmText, variant, icon, onConfirm }) {
                this.confirmModal.title = title;
                this.confirmModal.message = message;
                this.confirmModal.confirmText = confirmText || 'تأكيد';
                this.confirmModal.variant = variant || 'rose';
                this.confirmModal.icon = icon || 'help';
                this.confirmModal.onConfirm = onConfirm;
                this.confirmModal.loading = false;
                this.confirmModal.open = true;
            },

            closeConfirmModal() {
                if (this.confirmModal.loading) return;
                this.confirmModal.open = false;
                this.confirmModal.onConfirm = null;
            },

            async executeConfirmedAction() {
                if (this.confirmModal.loading || typeof this.confirmModal.onConfirm !== 'function') return;
                this.confirmModal.loading = true;
                try {
                    await this.confirmModal.onConfirm();
                    this.confirmModal.open = false;
                } finally {
                    this.confirmModal.loading = false;
                    this.confirmModal.onConfirm = null;
                }
            },

            normalizeSearch(str) {
                if (!str) return '';
                const arabic = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩','۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
                const english = ['0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9'];
                let s = String(str).toLowerCase().trim();
                for (let i = 0; i < arabic.length; i++) {
                    s = s.replaceAll(arabic[i], english[i]);
                }
                return s;
            },

            formatAmount(val) {
                const num = Number(val || 0);
                return num.toLocaleString('en-US', { maximumFractionDigits: 2 });
            },

            formatDateShort(iso) {
                if (!iso) return '';
                try {
                    const d = new Date(iso);
                    return d.toLocaleDateString('ar-YE', {
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                    });
                } catch (e) {
                    return '';
                }
            },
        };
    }
</script>
@endpush

