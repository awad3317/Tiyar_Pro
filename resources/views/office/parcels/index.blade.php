@php
    $showSearch = true;

    $currentOffice = Auth::guard('office')->user();
    $defaultTpl = "عميلنا العزيز،\nمكتب: {office} ({branch})\nتم استلام طردكم: {package}\nيرجى التوجه للفرع للاستلام. شكراً لتعاملكم معنا.";
    $rawTemplate = !empty($currentOffice?->whatsapp_template)
        ? $currentOffice->whatsapp_template
        : (!empty($currentOffice?->sms_template) ? $currentOffice->sms_template : $defaultTpl);

    $preparedTemplate = str_replace(
        ['{office}', '{branch}'],
        [$currentOffice?->name ?? '', $currentOffice?->branch_name ?? ''],
        $rawTemplate
    );
    $preparedTemplate = str_replace(' ()', '', $preparedTemplate);
    $hasOfficeWhatsApp = (!empty($currentOffice?->whatsapp_api_key) || !empty(config('whatsapp.global_api_key')))
        && \Illuminate\Support\Facades\Cache::get('whatsapp_status_office_' . ($currentOffice?->id ?? 0)) !== 'offline';

    $rawSmsTemplate = !empty($currentOffice?->sms_template) ? $currentOffice->sms_template : $defaultTpl;
    $preparedSmsTemplate = str_replace(
        ['{office}', '{branch}'],
        [$currentOffice?->name ?? '', $currentOffice?->branch_name ?? ''],
        $rawSmsTemplate
    );
    $preparedSmsTemplate = str_replace(' ()', '', $preparedSmsTemplate);
@endphp
@extends('office.layouts.app')

@section('title', 'إدارة الطرود')

@section('alpine', 'parcelApp()')


@section('content')
    <!-- تنبيه نفاد باقة الـ SMS والشريط التذكيري -->
    @include('office.parcels.partials.sms-balance-modal')

    <!-- 1. تبويبات التصفية للجوال (< 1024px — مطابقة للأصل 100%) -->
    <nav class="flex lg:hidden border-b border-slate-100 bg-white sticky top-[125px] z-20 text-xs font-bold text-center">
        <template x-for="tab in filters" :key="tab.key">
            <button type="button"
                    @click="setFilter(tab.key)"
                    :class="filter === tab.key ? 'border-b-2 border-secondary text-secondary' : 'text-slate-500'"
                    class="flex-1 py-3">
                <span x-text="tab.label"></span>
                (<span x-text="countFor(tab.key)"></span>)
            </button>
        </template>
    </nav>

    <!-- 2. شريط أدوات سطح المكتب الاحترافي (lg: >= 1024px): Segmented Filter Pills + مبدّل (جدول / بطاقات) -->
    <div class="hidden lg:flex items-center justify-between gap-4 px-6 pt-5 pb-1">
        <div class="inline-flex items-center gap-1 p-1 rounded-xl bg-white/90 border border-slate-200 dark:bg-[#1C2434] dark:border-slate-700/60 shadow-2xs">
            <template x-for="tab in filters" :key="'desk-' + tab.key">
                <button type="button"
                        @click="setFilter(tab.key)"
                        :class="filter === tab.key
                            ? 'bg-teal-600 text-white shadow-xs font-extrabold dark:bg-teal-500/20 dark:text-teal-300 dark:border dark:border-teal-500/30'
                            : 'text-slate-600 hover:text-slate-900 font-bold border border-transparent dark:text-slate-400 dark:hover:text-slate-200'"
                        class="px-3.5 py-1.5 rounded-lg text-xs inline-flex items-center gap-1.5 transition-all">
                    <span x-text="tab.label"></span>
                    <span :class="filter === tab.key
                              ? 'bg-white/20 text-white dark:bg-teal-500/30 dark:text-teal-200'
                              : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'"
                          class="px-1.5 py-0.5 rounded-md font-mono text-[11px] leading-none"
                          x-text="countFor(tab.key)"></span>
                </button>
            </template>
        </div>

        <div class="inline-flex items-center gap-1 p-1 rounded-xl bg-white border border-slate-200 dark:bg-[#24303F] dark:border-slate-700/60 shadow-2xs">
            <button type="button"
                    @click="setDesktopView('table')"
                    :class="desktopView === 'table'
                        ? 'bg-[#1C2434] text-[#F59E0B] dark:bg-amber-500/20 dark:text-amber-300 shadow-2xs font-extrabold'
                        : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 font-bold'"
                    class="px-3 py-1.5 rounded-lg text-xs inline-flex items-center gap-1.5 transition-all">
                <span class="material-symbols-outlined text-[16px]">table_rows</span>
                <span>جدول</span>
            </button>
            <button type="button"
                    @click="setDesktopView('cards')"
                    :class="desktopView === 'cards'
                        ? 'bg-[#1C2434] text-[#F59E0B] dark:bg-amber-500/20 dark:text-amber-300 shadow-2xs font-extrabold'
                        : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 font-bold'"
                    class="px-3 py-1.5 rounded-lg text-xs inline-flex items-center gap-1.5 transition-all">
                <span class="material-symbols-outlined text-[16px]">grid_view</span>
                <span>بطاقات</span>
            </button>
        </div>
    </div>

    <!-- قائمة الطرود -->
    <main x-ref="parcelsContainer" class="flex-1 p-3 lg:px-6 lg:py-4 overflow-y-auto">

        <!-- أ. عرض البطاقات (دائماً على الجوال < 1024px، وعلى الكمبيوتر عند اختيار وضع البطاقات — لا يُرسم في DOM عند تفعيل جدول الكمبيوتر لمضاعفة السرعة) -->
        <div :class="desktopView === 'table' ? 'lg:hidden' : 'lg:space-y-0 lg:grid lg:grid-cols-2 2xl:grid-cols-3 lg:gap-4 lg:content-start'"
             class="mursal-desktop-cards space-y-2.5">
            <template x-for="p in ((!isDesktopViewport || desktopView === 'cards') ? visibleParcels : [])" :key="p.id">
                @include('office.parcels.partials.parcel-card')
            </template>

            <div x-show="!isLoading && clientReady && filteredParcels.length === 0" x-cloak class="lg:col-span-full text-center py-12 text-slate-400">
                <span class="material-symbols-outlined text-4xl mb-1">inventory_2</span>
                <p class="text-xs">لا توجد طرود مطابقة</p>
            </div>
        </div>

        <!-- ب. وضع الجدول الاحترافي لسطح المكتب (lg: >= 1024px فقط عند اختيار وضع الجدول) -->
        <div x-show="desktopView === 'table'"
             x-cloak
             class="mursal-desktop-table hidden lg:block">
            <div x-show="!isLoading && clientReady && filteredParcels.length === 0"
                 x-cloak
                 class="bg-white dark:bg-[#24303F] rounded-2xl border border-slate-200/90 dark:border-slate-700/60 p-10 text-center text-slate-400 shadow-2xs">
                <span class="material-symbols-outlined text-4xl mb-1 text-slate-300 dark:text-slate-600">inventory_2</span>
                <p class="text-xs font-bold text-slate-500 dark:text-slate-300">لا توجد طرود مطابقة</p>
                <p class="text-[11px] text-slate-400 mt-0.5">اضغط على (+ إرسال سريع SMS) أعلى الشاشة لتسجيل طرد وارد جديد</p>
            </div>

            {{-- صفوف هيكلية خفيفة أثناء قراءة البيانات من IndexedDB لمنع وميض رسالة الفراغ --}}
            <div x-show="(isLoading || !clientReady) && filteredParcels.length === 0"
                 class="bg-white dark:bg-[#24303F] rounded-2xl border border-slate-200/90 dark:border-slate-700/60 shadow-2xs overflow-hidden">
                <table class="w-full text-right border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-700/60 bg-slate-100/90 dark:bg-slate-800/60 text-[11px] font-extrabold text-slate-700 dark:text-slate-300">
                            <th class="py-3 pr-4 pl-3">النوع ورقم السند</th>
                            <th class="py-3 px-3">المستلم والهاتف</th>
                            <th class="py-3 px-3">المصدر / الخانة</th>
                            <th class="py-3 px-3">الحالة</th>
                            <th class="py-3 px-3">التواصل</th>
                            <th class="py-3 pr-3 pl-4 text-left">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-slate-700/50 animate-pulse">
                        @for ($i = 0; $i < 4; $i++)
                            <tr>
                                <td class="py-3.5 pr-4 pl-3"><div class="h-3.5 w-28 rounded bg-slate-200/80 dark:bg-slate-700/60"></div></td>
                                <td class="py-3.5 px-3"><div class="h-3.5 w-32 rounded bg-slate-200/80 dark:bg-slate-700/60"></div></td>
                                <td class="py-3.5 px-3"><div class="h-3.5 w-24 rounded bg-slate-200/70 dark:bg-slate-700/50"></div></td>
                                <td class="py-3.5 px-3"><div class="h-5 w-16 rounded-md bg-slate-200/80 dark:bg-slate-700/60"></div></td>
                                <td class="py-3.5 px-3"><div class="h-7 w-24 rounded-lg bg-slate-200/70 dark:bg-slate-700/50"></div></td>
                                <td class="py-3.5 pr-3 pl-4"><div class="h-7 w-20 rounded-lg bg-slate-200/70 dark:bg-slate-700/50 mr-auto"></div></td>
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </div>

            <div x-show="filteredParcels.length > 0"
                 x-cloak
                 class="bg-white dark:bg-[#24303F] rounded-2xl border border-slate-200/90 dark:border-slate-700/60 shadow-2xs overflow-hidden">
                <table class="w-full text-right border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-700/60 bg-slate-100/90 dark:bg-slate-800/60 text-[11px] font-extrabold text-slate-700 dark:text-slate-300">
                            <th class="py-3 pr-4 pl-3">النوع ورقم السند</th>
                            <th class="py-3 px-3">المستلم والهاتف</th>
                            <th class="py-3 px-3">المصدر / الخانة</th>
                            <th class="py-3 px-3">الحالة</th>
                            <th class="py-3 px-3">التواصل</th>
                            <th class="py-3 pr-3 pl-4 text-left">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-slate-700/50 text-xs">
                        <template x-for="p in ((isDesktopViewport && desktopView === 'table') ? visibleParcels : [])" :key="'row-' + p.id">
                            <tr data-incoming-row="true"
                                style="content-visibility: auto; contain-intrinsic-size: auto 52px;"
                                class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">

                                <!-- 1. النوع ورقم السند -->
                                <td class="py-3 pr-4 pl-3 align-middle">
                                    <div class="flex items-center gap-2.5">
                                        <span class="w-1.5 h-8 rounded-full shrink-0" :class="rowAccentDot(p.status)"></span>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-1.5">
                                                <span x-show="p.receipt_number"
                                                      class="font-mono font-black text-slate-900 dark:text-slate-100"
                                                      x-text="'#' + p.receipt_number"></span>
                                                <span class="font-bold text-slate-800 dark:text-slate-200 truncate max-w-[180px]"
                                                      :title="p.package_type"
                                                      x-text="p.package_type"></span>
                                            </div>
                                            <p x-show="p.status === 'in_office' && p.created_at"
                                               class="text-[10px] text-slate-400 mt-0.5"
                                               :title="'تاريخ الاستلام: ' + formatDate(p.created_at)"
                                               x-text="timeAgo(p.created_at)"></p>
                                            <p x-show="p.status === 'delivered' && p.delivered_at"
                                               class="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold mt-0.5"
                                               x-text="'سُلّم: ' + formatDate(p.delivered_at)"></p>
                                        </div>
                                    </div>
                                </td>

                                <!-- 2. المستلم والهاتف -->
                                <td class="py-3 px-3 align-middle">
                                    <p class="font-bold text-slate-800 dark:text-slate-100 truncate max-w-[155px]" x-text="p.recipient_name || 'بدون اسم'"></p>
                                    <p class="text-[11px] font-mono text-slate-500 dark:text-slate-400 dir-ltr text-right" x-text="p.recipient_phone"></p>
                                </td>

                                <!-- 3. المصدر / الخانة -->
                                <td class="py-3 px-3 align-middle">
                                    <div class="flex items-center flex-wrap gap-1.5">
                                        <span x-show="p.slot_number"
                                              class="inline-flex items-center gap-0.5 text-[10px] font-bold font-mono px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200/80 dark:bg-indigo-500/15 dark:text-indigo-300 dark:border-indigo-500/30">
                                            <span class="material-symbols-outlined text-[12px]">grid_view</span>
                                            <span x-text="'خانة: ' + p.slot_number"></span>
                                        </span>
                                        <span x-show="p.source_region || p.source_office_name"
                                              class="inline-flex items-center gap-0.5 text-[10px] font-bold px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 border border-slate-200/80 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700 truncate max-w-[160px]">
                                            <span class="material-symbols-outlined text-[12px] text-slate-400">location_on</span>
                                            <span class="truncate" x-text="p.source_region || p.source_office_name"></span>
                                        </span>
                                        <span x-show="p.cod_amount && Number(p.cod_amount) > 0"
                                              class="inline-flex items-center gap-0.5 text-[10px] font-extrabold font-mono px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-200/80 dark:bg-amber-500/15 dark:text-amber-300 dark:border-amber-500/30"
                                              x-text="Number(p.cod_amount).toLocaleString('en-US') + ' ر.ي'"></span>
                                        <span x-show="!p.slot_number && !p.source_region && !p.source_office_name && !(p.cod_amount && Number(p.cod_amount) > 0)"
                                              class="text-slate-300 dark:text-slate-600 font-bold">—</span>
                                    </div>
                                </td>

                                <!-- 4. الحالة -->
                                <td class="py-3 px-3 align-middle">
                                    <div class="space-y-1 max-w-[240px]">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-[11px] font-bold"
                                                  :class="statusBadge(p.status)"
                                                  x-text="statusLabel(p.status)"></span>

                                            <template x-if="isPending(p.id)">
                                                <span title="بانتظار توفر الإنترنت للمزامنة"
                                                      class="inline-flex items-center gap-0.5 text-amber-700 bg-amber-50 border border-amber-300 dark:bg-amber-500/15 dark:text-amber-300 dark:border-amber-500/30 px-1.5 py-0.5 rounded-md text-[10px] font-bold">
                                                    <span class="material-symbols-outlined text-[12px] animate-pulse">schedule</span>
                                                    <span>معلق</span>
                                                </span>
                                            </template>

                                            <template x-if="isTodayFailedSms(p)">
                                                <span :title="p.sms_failure_reason || 'فشل إرسال الرسالة تلقائياً'"
                                                      class="inline-flex items-center gap-0.5 text-rose-700 bg-rose-50 border border-rose-200 dark:bg-rose-500/15 dark:text-rose-400 dark:border-rose-500/30 px-1.5 py-0.5 rounded-md text-[10px] font-extrabold">
                                                    <span class="material-symbols-outlined text-[12px]">sms_failed</span>
                                                    <span>فشل SMS</span>
                                                </span>
                                            </template>
                                        </div>

                                        <template x-if="isTodayFailedSms(p)">
                                            <p class="text-[10px] font-bold text-rose-600 dark:text-rose-400 leading-snug"
                                               x-text="p.sms_failure_reason || (p.sms_status === 'failed_balance'
                                                   ? 'انتهت باقة الرسائل أو نفد الرصيد في شريحة المكتب.'
                                                   : 'تعذر إرسال الرسالة تلقائياً — اضغط (إعادة SMS) للمحاولة.')"></p>
                                        </template>
                                    </div>
                                </td>

                                <!-- 5. التواصل (واتساب / SMS — بدون زر اتصال هاتفي في الكمبيوتر) -->
                                <td class="py-3 px-3 align-middle whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5">
                                        <span x-show="p.status !== 'in_office'"
                                              class="text-slate-300 dark:text-slate-600 font-bold">—</span>

                                        <button type="button"
                                                x-show="p.status === 'in_office'"
                                                @click="handleWhatsAppClick(p, {{ Js::from($preparedTemplate) }}, {{ Js::from($hasOfficeWhatsApp) }})"
                                                :disabled="isSendingWhatsapp(p.id)"
                                                :title="isSendingWhatsapp(p.id) ? 'جاري إرسال إشعار الواتساب...' : 'إرسال إشعار عبر واتساب'"
                                                class="w-8 h-8 rounded-lg bg-[#25D366]/10 hover:bg-[#25D366]/20 text-[#128C7E] dark:text-[#4ade80] border border-[#25D366]/30 flex items-center justify-center transition-colors disabled:opacity-60">
                                            <template x-if="isSendingWhatsapp(p.id)">
                                                <span class="w-3.5 h-3.5 border-2 border-[#25D366] border-t-transparent rounded-full animate-spin"></span>
                                            </template>
                                            <template x-if="!isSendingWhatsapp(p.id)">
                                                <svg class="w-4 h-4 fill-[#25D366]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
                                                    <path d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L0 480l117.7-30.9c32.4 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-69.8 18.3L72 359.2l-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z" />
                                                </svg>
                                            </template>
                                        </button>

                                        <button type="button"
                                                x-show="p.status === 'in_office'"
                                                @click="resendSms(p)"
                                                :disabled="sendingSmsId === p.id"
                                                :title="isTodayFailedSms(p) ? ((p.sms_failure_reason || 'فشل إرسال الـ SMS') + ' — انقر لإعادة الإرسال') : 'إعادة إرسال إشعار SMS للمستلم'"
                                                :class="isTodayFailedSms(p)
                                                    ? 'bg-rose-50 hover:bg-rose-100 text-rose-700 border-rose-300 dark:bg-rose-500/15 dark:hover:bg-rose-500/25 dark:text-rose-400 dark:border-rose-500/30'
                                                    : 'bg-blue-50 hover:bg-blue-100 text-blue-600 border-blue-200/80 dark:bg-blue-500/15 dark:hover:bg-blue-500/25 dark:text-blue-400 dark:border-blue-500/30'"
                                                class="h-8 px-2.5 rounded-lg border inline-flex items-center gap-1 text-[11px] font-bold transition-colors disabled:opacity-50">
                                            <span class="material-symbols-outlined text-[15px]"
                                                  :class="sendingSmsId === p.id ? 'animate-spin' : ''"
                                                  x-text="sendingSmsId === p.id ? 'sync' : (isTodayFailedSms(p) ? 'priority_high' : 'sms')"></span>
                                            <span x-text="sendingSmsId === p.id ? 'جاري...' : (isTodayFailedSms(p) ? 'إعادة SMS' : 'SMS')"></span>
                                        </button>
                                    </div>
                                </td>

                                <!-- 6. الإجراءات المباشرة (تعديل / تسليم / إرجاع) -->
                                <td class="py-3 pr-3 pl-4 align-middle whitespace-nowrap text-left">
                                    <div class="inline-flex items-center justify-end gap-1.5">
                                        <button type="button"
                                                x-show="p.status === 'in_office'"
                                                @click="openEditModal(p)"
                                                title="تعديل بيانات الطرد"
                                                class="h-8 px-2.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-amber-50 hover:border-amber-300 text-slate-700 hover:text-amber-800 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-amber-500/15 dark:hover:border-amber-500/30 dark:hover:text-amber-300 text-xs font-extrabold inline-flex items-center gap-1 transition-all active:scale-95">
                                            <span class="material-symbols-outlined text-[15px]">edit</span>
                                            <span>تعديل</span>
                                        </button>

                                        <template x-for="action in actionsFor(p)" :key="'act-' + action.status">
                                            <button type="button"
                                                    @click="
                                                        action.status === 'returned'  ? openReturnModal(p)
                                                      : action.status === 'in_office' ? openUndoDeliveryModal(p)
                                                      : action.status === 'delivered' ? openDeliveryModal(p)
                                                      : changeStatus(p, action.status)
                                                    "
                                                    :class="action.status === 'delivered'
                                                        ? 'bg-emerald-600 hover:bg-emerald-700 text-white border-emerald-600'
                                                        : (action.status === 'in_office'
                                                            ? 'bg-amber-50 hover:bg-amber-100 text-amber-800 border-amber-300 dark:bg-amber-500/15 dark:hover:bg-amber-500/25 dark:text-amber-300 dark:border-amber-500/30'
                                                            : 'bg-white hover:bg-rose-50 text-rose-600 border-rose-200 dark:bg-rose-500/10 dark:hover:bg-rose-500/20 dark:text-rose-400 dark:border-rose-500/30')"
                                                    class="h-8 px-3 rounded-lg border text-xs font-extrabold inline-flex items-center gap-1 transition-all active:scale-95">
                                                <span class="material-symbols-outlined text-[15px]" x-text="action.icon"></span>
                                                <span x-text="action.status === 'returned' ? 'إرجاع' : action.label"></span>
                                            </button>
                                        </template>

                                        <span x-show="p.status === 'returned' && actionsFor(p).length === 0"
                                              class="text-[11px] font-bold text-rose-500 dark:text-rose-400">
                                            تم الإرجاع للمكتب المرسل
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- نقطة مراقبة التمرير التلقائي لتحميل الـ 25 طرد التالية مسبقاً -->
        <div x-ref="loadMoreSentinel" x-show="hasMoreParcels" class="h-1 w-full pointer-events-none" aria-hidden="true"></div>
    </main>

    <!-- مودال تأكيد الإرجاع للمكتب المرسل -->
    @include('office.parcels.partials.return-confirm-modal')

    <!-- مودال تأكيد إلغاء التسليم وإرجاع الطرد للمكتب الحالي -->
    @include('office.parcels.partials.undo-delivery-modal')

    <!-- مودال تأكيد التسليم -->
    @include('office.parcels.partials.delivery-confirm-modal')

    <!-- مودال تعديل بيانات الطرد (في المكتب فقط) -->
    @include('office.parcels.partials.edit-parcel-modal')
@endsection

@push('scripts')
    @include('office.parcels.partials.parcel-app-script')
@endpush
