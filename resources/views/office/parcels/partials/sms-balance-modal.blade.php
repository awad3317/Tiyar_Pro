<!-- شريط تذكير مصغر أعلى الشاشة عند إغلاق الدايلوق مؤقتاً -->
<div x-show="smsGateway && smsGateway.outOfBalance && !smsGateway.showDialog"
     x-cloak
     class="bg-gradient-to-r from-rose-600 to-amber-600 text-white px-3 py-2 text-xs font-bold flex items-center justify-between sticky top-[125px] z-30 shadow-md">
    <div class="flex items-center gap-1.5 cursor-pointer" @click="openSmsDialog()">
        <span class="material-symbols-outlined text-base animate-pulse">warning</span>
        <span>باقة SMS منتهية (يوجد <span class="underline font-black" x-text="smsGateway.failedTodayCount"></span> طرد لم تصلهم رسائل)</span>
    </div>
    <button type="button" @click="openSmsDialog()" class="px-2 py-0.5 bg-white text-rose-700 rounded-md text-[11px] font-extrabold hover:bg-rose-50 shadow-xs active:scale-95 transition-transform">
        تجديد الباقة
    </button>
</div>

<!-- دايلوق تنبيه نفاد باقة الـ SMS العلوي -->
<div x-show="smsGateway && smsGateway.outOfBalance && smsGateway.showDialog"
     x-cloak
     x-transition:enter="ease-out duration-300"
     x-transition:enter-start="opacity-0 -translate-y-6"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="ease-in duration-200"
     x-transition:leave-start="opacity-100 translate-y-0"
     x-transition:leave-end="opacity-0 -translate-y-6"
     class="fixed inset-x-0 top-3 z-50 px-3 max-w-md mx-auto pointer-events-none">
     
    <div class="pointer-events-auto bg-white border-2 border-rose-500 rounded-2xl shadow-2xl overflow-hidden text-slate-800">
        <!-- شريط الرأس التحذيري -->
        <div class="bg-gradient-to-r from-rose-600 to-amber-600 text-white px-4 py-2.5 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-xl animate-bounce">warning</span>
                <span class="font-bold text-sm">تنبيه: نفاد باقة / رصيد الرسائل (SMS)</span>
            </div>
            <button type="button" @click="dismissSmsDialog()" class="text-white/80 hover:text-white p-1 rounded-lg">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>

        <!-- محتوى التنبيه -->
        <div class="p-4 space-y-3">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-xl bg-rose-50 border border-rose-200 text-rose-600 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-2xl">sms_failed</span>
                </div>
                <div class="text-xs text-slate-700 leading-relaxed">
                    <p class="font-bold text-rose-700 mb-1">
                        رصيد أو باقة الرسائل (SMS) في هاتف المكتب منتهية (خطأ 108).
                    </p>
                    <p>
                        يوجد لديك (<span class="font-black text-rose-600 text-sm" x-text="smsGateway.failedTodayCount">0</span>) طرد مسجل اليوم لم تصلهم رسائل نصية. هل قمت بشحن الرصيد أو تجديد الباقة؟
                    </p>
                    <template x-if="smsGateway.data && smsGateway.data.sim">
                        <p class="text-[11px] text-slate-400 mt-1">
                            الشريحة المتأثرة: <span class="font-mono font-bold text-slate-600" x-text="smsGateway.data.sim"></span>
                        </p>
                    </template>
                </div>
            </div>

            <!-- أزرار الإجراءات -->
            <div class="flex flex-col gap-2 pt-1">
                <!-- الزر الرئيسي الأخضر -->
                <button type="button"
                        @click="confirmRecharge()"
                        :disabled="smsGateway.isRecharging"
                        class="w-full py-2.5 px-4 bg-emerald-600 hover:bg-emerald-700 active:scale-98 text-white rounded-xl font-bold text-xs flex items-center justify-center gap-2 shadow-md shadow-emerald-600/20 transition-all disabled:opacity-50 disabled:cursor-wait">
                    <template x-if="smsGateway.isRecharging">
                        <span class="inline-flex items-center gap-2">
                            <span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            <span>جاري إعادة إرسال رسائل اليوم...</span>
                        </span>
                    </template>
                    <template x-if="!smsGateway.isRecharging">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-base">published_with_changes</span>
                            <span>نعم، جددت الباقة (إعادة إرسال رسائل اليوم)</span>
                        </span>
                    </template>
                </button>

                <!-- الزر الثانوي -->
                <button type="button"
                        @click="dismissSmsDialog()"
                        class="w-full py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl font-bold text-xs transition-colors">
                    لاحقاً / إغلاق مؤقت
                </button>
            </div>
        </div>
    </div>
</div>

