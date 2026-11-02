{{-- نافذة منبثقة لتأكيد حذف عميل مميز --}}
<div x-show="deleteModal.open"
     x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs"
     @keydown.escape.window="if (!deleteModal.loading) closeDeleteModal()">

    <div class="relative w-full max-w-xs bg-white rounded-3xl shadow-2xl border border-slate-200 p-6 text-center overflow-hidden"
         @click.outside="if (!deleteModal.loading) closeDeleteModal()"
         x-show="deleteModal.open"
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95">

        <div class="w-12 h-12 rounded-2xl bg-rose-50 border border-rose-100 text-rose-600 flex items-center justify-center mx-auto mb-3 shadow-2xs">
            <span class="material-symbols-outlined text-2xl">delete_forever</span>
        </div>

        <h3 class="text-sm font-black text-slate-900 mb-1">حذف عميل مميز</h3>
        <p class="text-xs text-slate-500 mb-4 leading-relaxed">
            هل أنت متأكد من حذف العميل المميز
            <span class="font-bold text-slate-800" x-text="deleteModal.customer?.name"></span>
            (<span class="font-mono text-slate-700" dir="ltr" x-text="deleteModal.customer?.phone"></span>)؟
        </p>

        <div class="flex items-center gap-2">
            <button type="button" @click="closeDeleteModal()" :disabled="deleteModal.loading"
                    class="flex-1 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition-colors">
                إلغاء
            </button>

            <button type="button" @click="confirmDelete()" :disabled="deleteModal.loading"
                    class="flex-1 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 active:scale-95 text-white font-extrabold text-xs shadow-md shadow-rose-600/20 flex items-center justify-center gap-1.5 transition-all disabled:opacity-50">
                <template x-if="deleteModal.loading">
                    <span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                </template>
                <template x-if="!deleteModal.loading">
                    <span class="material-symbols-outlined text-base">delete</span>
                </template>
                <span>تأكيد الحذف</span>
            </button>
        </div>
    </div>
</div>

