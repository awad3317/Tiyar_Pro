{{-- نافذة إنشاء / تعديل كشف سائق (تتموضع في الأعلى items-start pt-4 لعدم تغطية الكيبورد للحقول) --}}
<div x-show="manifestModal.open"
     x-cloak
     @keydown.escape.window="closeManifestModal()"
     class="fixed inset-0 z-50 flex items-start justify-center pt-4 px-3 pb-4 overflow-y-auto">

    <!-- خلفية التعتيم -->
    <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs transition-opacity"
         @click="closeManifestModal()"></div>

    <!-- بطاقة المودال -->
    <div class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl border border-slate-200 z-10 overflow-hidden my-auto sm:my-0"
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-4 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 -translate-y-4 scale-95">

        <!-- الهيدر -->
        <div class="bg-primary text-white px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="w-7 h-7 rounded-lg bg-secondary/20 text-secondary flex items-center justify-center">
                    <span class="material-symbols-outlined text-lg" x-text="manifestModal.mode === 'edit' ? 'edit_document' : 'post_add'"></span>
                </span>
                <div>
                    <h3 class="text-sm font-extrabold leading-tight"
                        x-text="manifestModal.mode === 'edit' ? 'تعديل بيانات كشف السائق' : 'إنشاء كشف سائق جديد'"></h3>
                    <p class="text-[10px] text-slate-300">يتم حفظ السائق الجديد تلقائياً في سجل سائقي المكتب</p>
                </div>
            </div>
            <button type="button"
                    @click="closeManifestModal()"
                    class="w-7 h-7 rounded-lg bg-white/10 hover:bg-white/20 flex items-center justify-center text-slate-200">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>

        <!-- النموذج -->
        <form @submit.prevent="submitManifestForm()" class="p-3.5 space-y-3">

            <!-- تنبيه عدد الطرود المحددة التي سيتم ضمها للكشف الجديد -->
            <template x-if="manifestModal.attachParcelsCount && manifestModal.attachParcelsCount > 0">
                <div class="p-2.5 rounded-xl bg-amber-50 border border-amber-300 text-amber-900 text-xs font-extrabold flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-base text-amber-600">inventory_2</span>
                        <span>سيتم ضم (<span x-text="manifestModal.attachParcelsCount"></span>) طرد محدد إلى هذا الكشف</span>
                    </div>
                    <span x-show="manifestModal.attachPostpaidAmount > 0"
                          class="px-2 py-0.5 rounded-lg bg-amber-200/70 text-amber-950 text-[11px] dir-ltr"
                          x-text="formatAmount(manifestModal.attachPostpaidAmount) + ' ر.ي'"></span>
                </div>
            </template>

            <!-- رسالة خطأ إن وجدت -->
            <div x-show="manifestModal.error" x-cloak
                 class="p-2.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold flex items-center gap-2">
                <span class="material-symbols-outlined text-base shrink-0">error</span>
                <span x-text="manifestModal.error"></span>
            </div>

            <!-- 1. الاختيار السريع من السائقين المسجلين للمكتب (Autocomplete / Quick Select) -->
            <div x-show="drivers && drivers.length > 0" class="bg-slate-50 p-2.5 rounded-xl border border-slate-200/80">
                <label class="block text-[11px] font-extrabold text-slate-700 mb-1 flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm text-secondary">badge</span>
                    <span>اختيار سريع من السائقين المسجلين للمكتب:</span>
                </label>
                <select @change="selectSavedDriver($event.target.value)"
                        :value="manifestModal.form.driver_id || ''"
                        class="w-full bg-white border border-slate-200 focus:border-secondary rounded-xl px-3 py-2 text-xs font-bold text-slate-800 outline-none">
                    <option value="">-- سائق جديد (اكتب الاسم والرقم بالأسفل) --</option>
                    <template x-for="drv in drivers" :key="drv.id">
                        <option :value="drv.id" x-text="drv.name + (drv.phone ? ' (' + drv.phone + ')' : '')"></option>
                    </template>
                </select>
            </div>

            <!-- 2. اسم السائق + رقم جوال السائق -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                <div class="relative">
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">
                        اسم السائق <span class="text-rose-500">*</span>:
                    </label>
                    <input type="text"
                           x-ref="manifestDriverNameInput"
                           x-model="manifestModal.form.driver_name"
                           @focus="showDriverSuggestions = true"
                           @input="onDriverNameTyped()"
                           autocomplete="off"
                           maxlength="100"
                           required
                           placeholder="اكتب اسم السائق..."
                           class="w-full bg-slate-50 focus:bg-white border border-slate-200 focus:border-secondary rounded-xl px-3 py-2 text-xs font-bold text-slate-800 outline-none">

                    <!-- اقتراحات الإكمال التلقائي أثناء كتابة اسم السائق -->
                    <div x-show="driverSuggestions.length > 0 && showDriverSuggestions"
                         x-cloak
                         @click.outside="showDriverSuggestions = false"
                         class="absolute left-0 right-0 top-full mt-1 bg-white border border-slate-200 rounded-xl shadow-lg z-30 max-h-36 overflow-y-auto divide-y divide-slate-100">
                        <template x-for="sug in driverSuggestions" :key="sug.id">
                            <button type="button"
                                    @click="applyDriverSuggestion(sug)"
                                    class="w-full px-3 py-2 text-right hover:bg-amber-50/60 flex items-center justify-between text-xs">
                                <span class="font-extrabold text-slate-800" x-text="sug.name"></span>
                                <span class="font-mono text-[11px] text-slate-500 dir-ltr" x-text="sug.phone || 'بدون رقم'"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">
                        رقم جوال السائق (اختياري):
                    </label>
                    <input type="tel"
                           x-model="manifestModal.form.driver_phone"
                           inputmode="numeric"
                           maxlength="9"
                           placeholder="7XXXXXXXX"
                           class="w-full bg-slate-50 focus:bg-white border border-slate-200 focus:border-secondary rounded-xl px-3 py-2 text-xs font-mono font-bold text-slate-800 outline-none dir-ltr text-right">
                </div>
            </div>

            <!-- 3. المكتب المرسل + الفرع المرسل -->
            <div class="grid grid-cols-2 gap-2.5">
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">
                        المكتب المرسل <span class="text-rose-500">*</span>:
                    </label>
                    <input type="text"
                           x-model="manifestModal.form.source_office_name"
                           maxlength="100"
                           required
                           placeholder="مثال: مكتب عدن"
                           class="w-full bg-slate-50 focus:bg-white border border-slate-200 focus:border-secondary rounded-xl px-3 py-2 text-xs font-bold text-slate-800 outline-none">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">
                        الفرع المرسل (اختياري):
                    </label>
                    <input type="text"
                           x-model="manifestModal.form.source_branch_name"
                           maxlength="100"
                           placeholder="مثال: الشيخ عثمان"
                           class="w-full bg-slate-50 focus:bg-white border border-slate-200 focus:border-secondary rounded-xl px-3 py-2 text-xs font-bold text-slate-800 outline-none">
                </div>
            </div>

            <!-- 4. تاريخ الكشف + رقم الكشف الورقي -->
            <div class="grid grid-cols-2 gap-2.5">
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">
                        تاريخ الكشف:
                    </label>
                    <input type="date"
                           x-model="manifestModal.form.manifest_date"
                           class="w-full bg-slate-50 focus:bg-white border border-slate-200 focus:border-secondary rounded-xl px-3 py-2 text-xs font-mono font-bold text-slate-800 outline-none dir-ltr text-right">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">
                        رقم الكشف الورقي (اختياري):
                    </label>
                    <input type="text"
                           x-model="manifestModal.form.manifest_number"
                           maxlength="50"
                           placeholder="رقم الكشف..."
                           class="w-full bg-slate-50 focus:bg-white border border-slate-200 focus:border-secondary rounded-xl px-3 py-2 text-xs font-mono font-bold text-slate-800 outline-none">
                </div>
            </div>

            <!-- 5. ملاحظات الكشف -->
            <div>
                <label class="block text-[11px] font-bold text-slate-600 mb-1">ملاحظات (اختياري):</label>
                <input type="text"
                       x-model="manifestModal.form.notes"
                       maxlength="500"
                       placeholder="أي ملاحظات إضافية على الكشف أو الرحلة..."
                       class="w-full bg-slate-50 focus:bg-white border border-slate-200 focus:border-secondary rounded-xl px-3 py-2 text-xs font-bold text-slate-800 outline-none">
            </div>

            <!-- أزرار الحفظ والإلغاء -->
            <div class="flex items-center gap-2 pt-2 border-t border-slate-100">
                <button type="submit"
                        :disabled="manifestModal.saving"
                        class="flex-1 py-2.5 px-4 rounded-xl bg-secondary hover:brightness-105 text-primary font-extrabold text-xs shadow-md shadow-secondary/20 flex items-center justify-center gap-1.5 active:scale-95 transition-all disabled:opacity-60">
                    <span x-show="!manifestModal.saving" class="material-symbols-outlined text-base">save</span>
                    <span x-show="manifestModal.saving" class="w-4 h-4 border-2 border-primary border-t-transparent rounded-full animate-spin"></span>
                    <span x-text="manifestModal.saving
                        ? 'جاري الحفظ...'
                        : (manifestModal.mode === 'edit'
                            ? 'حفظ تعديلات الكشف'
                            : (manifestModal.attachParcelsCount > 0
                                ? 'إنشاء الكشف وضم (' + manifestModal.attachParcelsCount + ') طرد'
                                : 'إنشاء الكشف'))"></span>
                </button>

                <button type="button"
                        @click="closeManifestModal()"
                        class="py-2.5 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs transition-colors">
                    إلغاء
                </button>
            </div>
        </form>
    </div>
</div>

