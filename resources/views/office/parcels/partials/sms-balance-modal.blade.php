<!-- شريط تنبيه علوي أحمر ثابت عند انتهاء باقة / رصيد الـ SMS مع زر إعادة إرسال مباشر لطرود اليوم فقط -->
<div x-show="smsGateway && smsGateway.outOfBalance"
     x-cloak
     x-transition:enter="ease-out duration-200"
     x-transition:enter-start="opacity-0 -translate-y-2"
     x-transition:enter-end="opacity-100 translate-y-0"
     class="bg-rose-600 text-white px-3.5 py-2 text-xs font-bold flex items-center justify-between gap-2 sticky top-[125px] lg:top-16 z-30 shadow-md border-b border-rose-700">
    <div class="flex items-center gap-1.5 min-w-0">
        <span class="material-symbols-outlined text-base animate-pulse shrink-0">error</span>
        <span class="truncate">
            انتهت باقة الـ SMS
            <template x-if="smsGateway.failedTodayCount > 0">
                <span>(<span class="font-black underline" x-text="smsGateway.failedTodayCount"></span> طرد اليوم لم يُرسل)</span>
            </template>
        </span>
    </div>

    <button type="button"
            @click="confirmRecharge()"
            :disabled="smsGateway.isRecharging"
            class="shrink-0 px-3 py-1 bg-white hover:bg-rose-50 text-rose-700 rounded-lg text-[11px] font-extrabold shadow-xs active:scale-95 transition-all flex items-center gap-1 disabled:opacity-60">
        <template x-if="smsGateway.isRecharging">
            <span class="w-3.5 h-3.5 border-2 border-rose-600 border-t-transparent rounded-full animate-spin"></span>
        </template>
        <template x-if="!smsGateway.isRecharging">
            <span class="material-symbols-outlined text-[14px]">refresh</span>
        </template>
        <span x-text="smsGateway.isRecharging ? 'جاري الإرسال...' : 'إعادة إرسال'"></span>
    </button>
</div>
