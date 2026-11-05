{{-- نافذة تأكيد العمليات الحساسة داخل الكشف (إخراج طرد / حذف خصم / اعتماد التصفية) بدلاً من confirm() التقليدي --}}
<div x-show="confirmModal.open"
     x-cloak
     @keydown.escape.window="closeConfirmModal()"
     class="fixed inset-0 z-50 flex items-start justify-center pt-10 px-3 pb-4 overflow-y-auto">

    <!-- خلفية التعتيم -->
    <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs transition-opacity"
         @click="closeConfirmModal()"></div>

    <!-- بطاقة التأكيد -->
    <div class="relative w-full max-w-sm bg-white rounded-2xl shadow-2xl border border-slate-200 z-10 overflow-hidden p-4 space-y-3"
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-4 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100">

        <div class="flex items-start gap-3">
            <span class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                  :class="confirmModal.variant === 'emerald'
                    ? 'bg-emerald-100 text-emerald-700'
                    : (confirmModal.variant === 'amber' ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700')">
                <span class="material-symbols-outlined text-xl" x-text="confirmModal.icon || 'help'"></span>
            </span>

            <div class="min-w-0 flex-1">
                <h3 class="text-sm font-extrabold text-slate-800" x-text="confirmModal.title"></h3>
                <p class="text-xs font-bold text-slate-500 mt-1 leading-relaxed" x-text="confirmModal.message"></p>
            </div>
        </div>

        <div class="flex items-center gap-2 pt-2 border-t border-slate-100">
            <button type="button"
                    @click="executeConfirmedAction()"
                    :disabled="confirmModal.loading"
                    :class="confirmModal.variant === 'emerald'
                        ? 'bg-emerald-600 hover:bg-emerald-700 text-white'
                        : (confirmModal.variant === 'amber' ? 'bg-amber-500 hover:bg-amber-600 text-white' : 'bg-rose-600 hover:bg-rose-700 text-white')"
                    class="flex-1 py-2.5 px-4 rounded-xl font-extrabold text-xs flex items-center justify-center gap-1.5 active:scale-95 transition-all disabled:opacity-60">
                <span x-show="confirmModal.loading" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                <span x-text="confirmModal.confirmText || 'تأكيد'"></span>
            </button>

            <button type="button"
                    @click="closeConfirmModal()"
                    :disabled="confirmModal.loading"
                    class="py-2.5 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors">
                إلغاء
            </button>
        </div>
    </div>
</div>

