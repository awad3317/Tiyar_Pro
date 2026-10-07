@php
    $office = auth('office')->user();
@endphp

<!-- شريط الهيدر الموحد -->
<header class="bg-primary text-white p-4 sticky top-0 z-30 shadow-md">
    <div class="flex items-center justify-between gap-3">
        <!-- اسم المكتب والفرع -->
        <div class="min-w-0 flex-1">
            <h1 class="font-extrabold text-base sm:text-lg leading-tight truncate text-white">
                {{ $office->name ?? 'المكتب' }}
            </h1>
            <p class="text-xs text-slate-400 truncate mt-0.5">
                {{ $office->branch_name ?? 'المركز الرئيسي' }}
            </p>
        </div>

        <!-- أزرار الإجراءات وحالة الاتصال -->
        <div class="flex items-center gap-2 shrink-0">
            <!-- مؤشر حالة الاتصال (PWA Online/Offline) -->
            <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold"
                 :class="isOnline ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-amber-500/20 text-amber-400 border border-amber-500/30'">
                <span class="w-2 h-2 rounded-full" :class="isOnline ? 'bg-emerald-400 animate-pulse' : 'bg-amber-400'"></span>
                <span x-text="isOnline ? 'متصل' : 'بدون نت'"></span>
            </div>

            <!-- زر المزامنة اليدوية السريعة -->
            <button type="button" 
                    @click="syncData ? syncData() : window.location.reload()" 
                    title="مزامنة وتحديث البيانات"
                    class="w-8 h-8 rounded-xl bg-slate-800 border border-slate-700 hover:border-slate-600 flex items-center justify-center text-slate-300 hover:text-white transition-colors">
                <span class="material-symbols-outlined text-[19px]" :class="typeof isSyncing !== 'undefined' && isSyncing ? 'animate-spin text-secondary' : ''">sync</span>
            </button>

            <!-- زر تسجيل الخروج المباشر -->
            <form method="POST" action="{{ route('office.logout') }}" class="m-0">
                @csrf
                <button type="submit" 
                        title="تسجيل الخروج"
                        class="w-8 h-8 rounded-xl bg-rose-500/10 border border-rose-500/30 hover:bg-rose-500/20 flex items-center justify-center text-rose-400 transition-colors">
                    <span class="material-symbols-outlined text-[19px]">logout</span>
                </button>
            </form>
        </div>
    </div>

    <!-- عداد التعديلات المعلقة للمزامنة (يظهر تلقائياً عند وجود طابور) -->
    <template x-if="typeof syncQueue !== 'undefined' && syncQueue.length > 0">
        <div class="bg-secondary/20 border border-secondary/40 text-secondary text-[11px] px-3 py-1.5 rounded-lg flex justify-between items-center mt-2.5">
            <span>لديك (<b x-text="syncQueue.length"></b>) تعديلات غير متزامنة مع السيرفر</span>
            <span class="material-symbols-outlined text-sm animate-spin" x-show="isSyncing">sync</span>
        </div>
    </template>

    <!-- شريط البحث (يظهر اختيارياً فقط إذا تم تفعيل المتغير $showSearch) -->
    @if (!empty($showSearch))
        <div class="mt-3 relative">
            <input type="text" 
                   x-model="search" 
                   placeholder="بحث برقم الهاتف، السند، نوع الطرد..." 
                   class="w-full bg-slate-800 text-white placeholder-slate-400 text-xs rounded-xl pr-9 pl-4 py-2.5 outline-none border border-slate-700 focus:border-secondary transition-all">
            <span class="material-symbols-outlined absolute right-2.5 top-2.5 text-slate-400 text-lg">search</span>
        </div>
    @endif
</header>