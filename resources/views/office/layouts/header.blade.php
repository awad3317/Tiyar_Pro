@php
    $office = auth('office')->user();
@endphp

<!-- شريط الهيدر الموحد -->
<header class="bg-primary text-white p-4 sticky top-0 z-30 shadow-md">
    <div class="flex items-center justify-between gap-3">
        <!-- اسم المكتب والفرع (قائمة منسدلة) -->
        <div class="min-w-0 flex-1 relative" x-data="{ menuOpen: false }">
            <button type="button" @click="menuOpen = !menuOpen" 
                    class="w-full text-right flex items-center gap-1.5 group">
                <div class="min-w-0 flex-1">
                    <h1 class="font-extrabold text-base sm:text-lg leading-tight truncate text-white">
                        {{ $office->name ?? 'المكتب' }}
                    </h1>
                    <p class="text-xs text-slate-400 truncate mt-0.5">
                        {{ $office->branch_name ?? 'المركز الرئيسي' }}
                    </p>
                </div>
                <span class="material-symbols-outlined text-slate-400 text-sm transition-transform shrink-0"
                      :class="menuOpen ? 'rotate-180' : ''">expand_more</span>
            </button>

            <!-- القائمة المنسدلة -->
            <div x-show="menuOpen" x-cloak 
                 @click.outside="menuOpen = false"
                 x-transition:enter="ease-out duration-150"
                 x-transition:enter-start="opacity-0 -translate-y-2 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="ease-in duration-100"
                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                 x-transition:leave-end="opacity-0 -translate-y-2 scale-95"
                 class="absolute top-full right-0 mt-2 w-56 bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden z-50">
                
                <!-- رابط إعدادات الواتساب -->
                <a href="{{ route('office.whatsapp.index') }}" 
                   class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition-colors group/item">
                    <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center group-hover/item:bg-emerald-100 transition-colors">
                        <span class="material-symbols-outlined text-base">chat</span>
                    </div>
                    <div>
                        <p class="text-xs font-extrabold text-slate-700">إعدادات الواتساب</p>
                    </div>
                </a>

                <div class="border-t border-slate-100"></div>

            </div>
        </div>

        <!-- أزرار الإجراءات وحالة الاتصال -->
        <div class="flex items-center gap-2 shrink-0">
            <!-- مؤشر حالة الاتصال (PWA Online/Offline) -->
            <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold"
                 :class="isOnline ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-amber-500/20 text-amber-400 border border-amber-500/30'">
                <span class="w-2 h-2 rounded-full" :class="isOnline ? 'bg-emerald-400 animate-pulse' : 'bg-amber-400'"></span>
                <span x-text="isOnline ? 'متصل' : 'بدون نت'"></span>
            </div>

            <!-- زر الإرسال السريع الموحد (+) -->
            <button type="button"
                    @click="$dispatch('open-quick-sms')"
                    title="إرسال كشف سريع SMS"
                    class="w-8 h-8 rounded-xl bg-secondary/15 border border-secondary/30 hover:bg-secondary/25 flex items-center justify-center text-secondary hover:text-white transition-colors">
                <span class="material-symbols-outlined text-[19px] font-bold">add</span>
            </button>

            <!-- زر المزامنة اليدوية السريعة -->
            <button type="button" 
                    @click="(typeof syncData === 'function') ? syncData() : window.location.reload()" 
                    title="مزامنة وتحديث البيانات"
                    class="w-8 h-8 rounded-xl bg-slate-800 border border-slate-700 hover:border-slate-600 flex items-center justify-center text-slate-300 hover:text-white transition-colors">
                <span class="material-symbols-outlined text-[19px]" :class="typeof isSyncing !== 'undefined' && isSyncing ? 'animate-spin text-secondary' : ''">sync</span>
            </button>

            <!-- زر تسجيل الخروج المباشر -->
            <form method="POST" 
                  action="{{ route('office.logout') }}" 
                  class="m-0"
                  x-data="{ isLoggingOut: false }"
                  @submit="isLoggingOut = true">
                @csrf
                <button type="submit" 
                        :disabled="isLoggingOut"
                        title="تسجيل الخروج"
                        class="w-8 h-8 rounded-xl bg-rose-500/10 border border-rose-500/30 hover:bg-rose-500/20 flex items-center justify-center text-rose-400 transition-colors disabled:opacity-75">
                    <span x-show="!isLoggingOut" class="material-symbols-outlined text-[19px]">logout</span>
                    <span x-show="isLoggingOut" x-cloak class="w-4 h-4 border-2 border-rose-500 border-t-transparent rounded-full animate-spin"></span>
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

    <!-- شريط البحث (يظهر تلقائياً في صفحة الطرود أو إذا تم تفعيل المتغير $showSearch) -->
    @if (!empty($showSearch) || request()->routeIs('office.parcels.*'))
        <div class="mt-3 relative">
            <input type="text" 
                   x-model="search" 
                   placeholder="بحث برقم الهاتف، السند، نوع الطرد..." 
                   class="w-full bg-slate-800 text-white placeholder-slate-400 text-xs rounded-xl pr-9 pl-4 py-2.5 outline-none border border-slate-700 focus:border-secondary transition-all">
            <span class="material-symbols-outlined absolute right-2.5 top-2.5 text-slate-400 text-lg">search</span>
        </div>
    @endif
</header>