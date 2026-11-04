@php
    /** @var \App\Models\Accountant|null $accountant */
    $accountant = auth('accountant')->user();
    $office = $accountant?->office;
@endphp

<!-- شريط الهيدر المستقل لتطبيق المحاسبين -->
<header class="bg-primary text-white p-4 sticky top-0 z-30 shadow-md">
    <div class="flex items-center justify-between gap-3">
        <!-- الجهة اليمنى: اسم المكتب واسم المحاسب وشارة التطبيق -->
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
                <h1 class="font-extrabold text-base sm:text-lg leading-tight truncate text-white">
                    {{ $office?->name ?? 'المكتب' }} - {{ $office->branch_name }}
                </h1>
            </div>
            <p class="text-xs text-slate-300 truncate mt-1 flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[14px] text-secondary">person</span>
                <strong class="text-white font-bold">{{ $accountant?->name ?? '—' }}</strong>
            </p>
        </div>

        <!-- الجهة اليسرى: حالة الاتصال + زر التحديث + زر تسجيل الخروج -->
        <div class="flex items-center gap-2 shrink-0">
            <!-- شارة حالة الاتصال الحية -->
            <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold"
                 :class="isOnline ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-amber-500/20 text-amber-400 border border-amber-500/30'">
                <span class="w-2 h-2 rounded-full" :class="isOnline ? 'bg-emerald-400 animate-pulse' : 'bg-amber-400'"></span>
                <span x-text="isOnline ? 'متصل' : 'بدون نت'"></span>
            </div>

            <!-- زر تحديث / مزامنة -->
            <button type="button"
                    @click="(typeof refreshData === 'function') ? refreshData() : window.location.reload()"
                    title="تحديث البيانات"
                    class="w-8 h-8 rounded-xl bg-slate-800 border border-slate-700 hover:border-slate-600 flex items-center justify-center text-slate-300 hover:text-white transition-colors">
                <span class="material-symbols-outlined text-[19px]"
                      :class="typeof isSyncing !== 'undefined' && isSyncing ? 'animate-spin text-secondary' : ''">refresh</span>
            </button>

            <!-- زر تسجيل الخروج المستقل لتطبيق المحاسبين -->
            <form method="POST"
                  action="{{ route('accountant.logout') }}"
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

    <!-- عداد التعديلات المالية المعلقة للمزامنة في MursalAccountantDB -->
    <template x-if="typeof syncQueue !== 'undefined' && syncQueue.length > 0">
        <div class="bg-secondary/20 border border-secondary/40 text-secondary text-[11px] px-3 py-1.5 rounded-lg flex justify-between items-center mt-2.5">
            <span>لديك (<b x-text="syncQueue.length"></b>) عمليات مالية غير متزامنة مع السيرفر</span>
            <span class="material-symbols-outlined text-sm animate-spin" x-show="isSyncing">sync</span>
        </div>
    </template>

    <!-- شريط البحث الاختياري (إذا تم تفعيل المتغير $showSearch في صفحات المحاسب مستقبلاً) -->
    @if (!empty($showSearch))
        <div class="mt-3 relative">
            <input type="text"
                   x-model="search"
                   placeholder="{{ $searchPlaceholder ?? 'بحث برقم السند، الهاتف، أو اسم المستلم...' }}"
                   class="w-full bg-slate-800 text-white placeholder-slate-400 text-xs rounded-xl pr-9 pl-4 py-2.5 outline-none border border-slate-700 focus:border-secondary transition-all">
            <span class="material-symbols-outlined absolute right-2.5 top-2.5 text-slate-400 text-lg">search</span>
        </div>
    @endif
</header>

