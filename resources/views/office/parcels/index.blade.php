<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#041627">
    <title>إدارة الطرود - مُرسَل</title>
    
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@400;700;800&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=swap" rel="stylesheet">
    
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#041627',
                        secondary: '#fe9d20',
                    }
                }
            }
        }
    </script>

    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Almarai', sans-serif; -webkit-tap-highlight-color: transparent; }
    </style>
</head>
<body class="bg-slate-100 text-slate-900 pb-20 select-none">

<div x-data="parcelApp()" x-init="initApp()" class="max-w-md mx-auto min-h-screen bg-white flex flex-col shadow-lg">

    <!-- شريط الحالة العلوي -->
    <header class="bg-primary text-white p-4 sticky top-0 z-30 shadow-md">
        <div class="flex items-center justify-between mb-2">
            <div>
                <h1 class="font-extrabold text-lg leading-tight">{{ $office->name }}</h1>
                <p class="text-xs text-slate-400">{{ $office->branch_name ?? 'الفرع الرئيسي' }}</p>
            </div>
            
            <!-- مؤشر حالة الاتصال والمزامنة -->
            <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold"
                 :class="isOnline ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-amber-500/20 text-amber-400 border border-amber-500/30'">
                <span class="w-2 h-2 rounded-full" :class="isOnline ? 'bg-emerald-400 animate-pulse' : 'bg-amber-400'"></span>
                <span x-text="isOnline ? 'متصل' : 'بدون نت'"></span>
            </div>
        </div>

        <!-- عداد المعلق للمزامنة -->
        <template x-if="syncQueue.length > 0">
            <div class="bg-secondary/20 border border-secondary/40 text-secondary text-[11px] px-3 py-1.5 rounded-lg flex justify-between items-center mt-2">
                <span>لديك (<b x-text="syncQueue.length"></b>) تعديلات غير متزامنة</span>
                <span class="material-symbols-outlined text-sm animate-spin" x-show="isSyncing">sync</span>
            </div>
        </template>

        <!-- شريط البحث السريع -->
        <div class="mt-3 relative">
            <input type="text" x-model="search" placeholder="بحث برقم الهاتف، السند، نوع الطرد..." 
                   class="w-full bg-slate-800 text-white placeholder-slate-400 text-xs rounded-xl pr-9 pl-4 py-2.5 outline-none border border-slate-700 focus:border-secondary">
            <span class="material-symbols-outlined absolute right-2.5 top-2.5 text-slate-400 text-lg">search</span>
        </div>
    </header>

    <!-- تبويبات التصفية -->
    <nav class="flex border-b border-slate-100 bg-white sticky top-[125px] z-20 text-xs font-bold text-center">
        <button @click="filter = 'all'" :class="filter === 'all' ? 'border-b-2 border-secondary text-secondary' : 'text-slate-500'" class="flex-1 py-3">الكل (<span x-text="parcels.length"></span>)</button>
        <button @click="filter = 'in_office'" :class="filter === 'in_office' ? 'border-b-2 border-secondary text-secondary' : 'text-slate-500'" class="flex-1 py-3">بالمكتب (<span x-text="countStatus('in_office')"></span>)</button>
        <button @click="filter = 'delivered'" :class="filter === 'delivered' ? 'border-b-2 border-secondary text-secondary' : 'text-slate-500'" class="flex-1 py-3">تم التسليم (<span x-text="countStatus('delivered')"></span>)</button>
    </nav>

    <!-- قائمة الطرود (Offline Ready Cards) -->
    <main class="flex-1 p-3 space-y-2.5 overflow-y-auto">
        <template x-for="p in filteredParcels" :key="p.id">
            <div class="border border-slate-200/80 rounded-2xl p-3.5 bg-white shadow-sm flex flex-col gap-2 relative">
                
                <!-- الرأس: نوع الطرد والسند -->
                <div class="flex justify-between items-start">
                    <div class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-primary">
                            <span class="material-symbols-outlined text-lg">package_2</span>
                        </span>
                        <div>
                            <h3 class="font-bold text-sm text-slate-800" x-text="p.package_type"></h3>
                            <p class="text-[11px] text-slate-400 font-mono" x-show="p.receipt_number" x-text="'سند: #' + p.receipt_number"></p>
                        </div>
                    </div>

                    <!-- شارة الحالة -->
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-md"
                          :class="{
                              'bg-amber-100 text-amber-800': p.status === 'in_office',
                              'bg-emerald-100 text-emerald-800': p.status === 'delivered',
                              'bg-rose-100 text-rose-800': p.status === 'returned'
                          }"
                          x-text="statusLabel(p.status)">
                    </span>
                </div>

                <!-- تفاصيل المستلم -->
                <div class="bg-slate-50 rounded-xl p-2.5 flex justify-between items-center text-xs">
                    <div>
                        <p class="font-bold text-slate-700" x-text="p.recipient_name || 'بدون اسم'"></p>
                        <p class="text-slate-500 font-mono dir-ltr text-right" x-text="p.recipient_phone"></p>
                    </div>
                    <a :href="'tel:' + p.recipient_phone" class="w-8 h-8 rounded-full bg-emerald-500 text-white flex items-center justify-center shadow-sm">
                        <span class="material-symbols-outlined text-sm">call</span>
                    </a>
                </div>

                <!-- أزرار الإجراء السريع (تغيير الحالة محلياً فوراً) -->
                <div class="flex gap-2 pt-1">
                    <button @click="changeStatus(p.id, 'delivered')" 
                            :disabled="p.status === 'delivered'"
                            class="flex-1 py-2 bg-emerald-600 disabled:bg-slate-200 disabled:text-slate-400 text-white text-xs font-bold rounded-xl flex items-center justify-center gap-1 transition-all active:scale-95">
                        <span class="material-symbols-outlined text-sm">check_circle</span>
                        <span>تسليم</span>
                    </button>

                    <button @click="changeStatus(p.id, 'returned')"
                            :disabled="p.status === 'returned'"
                            class="py-2 px-3 border border-rose-200 text-rose-600 disabled:opacity-40 text-xs font-bold rounded-xl flex items-center justify-center gap-1">
                        <span class="material-symbols-outlined text-sm">assignment_return</span>
                        <span>إرجاع</span>
                    </button>
                </div>

            </div>
        </template>

        <div x-show="filteredParcels.length === 0" class="text-center py-12 text-slate-400">
            <span class="material-symbols-outlined text-4xl mb-1">inventory_2</span>
            <p class="text-xs">لا توجد طرود مطابقة</p>
        </div>
    </main>

    <!-- شريط تنقل سفلي PWA -->
    <footer class="fixed bottom-0 left-0 right-0 max-w-md mx-auto bg-white border-t border-slate-200 px-6 py-2 flex justify-around items-center z-30">
        <button class="flex flex-col items-center text-primary font-bold text-[10px]">
            <span class="material-symbols-outlined text-xl">inventory_2</span>
            <span>الطرود</span>
        </button>
        <button @click="syncData()" class="flex flex-col items-center text-slate-400 font-bold text-[10px]">
            <span class="material-symbols-outlined text-xl" :class="isSyncing ? 'animate-spin text-secondary' : ''">sync</span>
            <span>مزامنة</span>
        </button>
        <form method="POST" action="{{ route('office.logout') }}">
            @csrf
            <button type="submit" class="flex flex-col items-center text-rose-500 font-bold text-[10px]">
                <span class="material-symbols-outlined text-xl">logout</span>
                <span>خروج</span>
            </button>
        </form>
    </footer>

</div>

<script>
// تسجيل Service Worker
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js');
}

function parcelApp() {
    return {
        parcels: [],
        syncQueue: [],
        search: '',
        filter: 'all',
        isOnline: navigator.onLine,
        isSyncing: false,
        db: null,

        async initApp() {
            window.addEventListener('online', () => { 
                this.isOnline = true; 
                this.processSyncQueue(); 
            });
            window.addEventListener('offline', () => { 
                this.isOnline = false; 
            });

            await this.initDB();
            await this.loadLocalParcels();

            if (this.isOnline) {
                await this.fetchServerParcels();
            }
        },

        // 1. ترقية نسخة IndexedDB وضبط المفتاح الأساسي لطابور المزامنة
        initDB() {
            return new Promise((resolve, reject) => {
                // رفع النسخة إلى 2 لضمان تطبيق التغييرات على المتصفح
                const req = indexedDB.open('MursalDB', 2);
                
                req.onupgradeneeded = (e) => {
                    const db = e.target.result;
                    if (!db.objectStoreNames.contains('parcels')) {
                        db.createObjectStore('parcels', { keyPath: 'id' });
                    }
                    
                    // حذف القديم وإعادة إنشائه بـ keyPath لمنع التكرار
                    if (db.objectStoreNames.contains('syncQueue')) {
                        db.deleteObjectStore('syncQueue');
                    }
                    db.createObjectStore('syncQueue', { keyPath: 'id' });
                };
                
                req.onsuccess = (e) => {
                    this.db = e.target.result;
                    resolve();
                };
                req.onerror = reject;
            });
        },

        async loadLocalParcels() {
            const tx = this.db.transaction('parcels', 'readonly');
            const store = tx.objectStore('parcels');
            const req = store.getAll();
            req.onsuccess = () => {
                this.parcels = req.result || [];
            };
            await this.loadSyncQueue();
        },

        async loadSyncQueue() {
            const tx = this.db.transaction('syncQueue', 'readonly');
            const store = tx.objectStore('syncQueue');
            const req = store.getAll();
            req.onsuccess = () => {
                this.syncQueue = req.result || [];
            };
        },

        // 2. دمج ذكي لبيانات السيرفر بدون مسح التعديلات المحلية غير المتزامنة
        async fetchServerParcels() {
            try {
                const res = await fetch('{{ route("office.api.parcels") }}');
                if (res.ok) {
                    const serverData = await res.json();
                    
                    // جلب معرفات الطرود المعلقة محلياً لعدم استبدالها ببيانات قديمة من السيرفر
                    const pendingIds = new Set(this.syncQueue.map(item => item.id));

                    const tx = this.db.transaction('parcels', 'readwrite');
                    const store = tx.objectStore('parcels');

                    serverData.forEach(p => {
                        if (!pendingIds.has(p.id)) {
                            store.put(p);
                        }
                    });

                    // تحديث الحالة في الذاكرة
                    this.loadLocalParcels();
                }
            } catch (err) {
                console.log('وضع عدم الاتصال، يتم الاعتماد على البيانات المحلية.');
            }
        },

        // 3. تصحيح حفظ الحالة ومنع تكرار نفس الطرد في الـ Queue
        async changeStatus(id, newStatus) {
            const index = this.parcels.findIndex(p => p.id === id);
            if (index === -1) return;

            // تحديث فوري على الواجهة
            this.parcels[index].status = newStatus;
            this.parcels[index].delivered_at = (newStatus === 'delivered') 
                ? new Date().toISOString() 
                : null;

            // حفظ في IndexedDB
            const tx = this.db.transaction(['parcels', 'syncQueue'], 'readwrite');
            tx.objectStore('parcels').put(this.parcels[index]);

            // إدراج أو تحديث (Upsert) في طابور المزامنة باستخدام put
            const syncItem = { 
                id: id, 
                status: newStatus, 
                delivered_at: this.parcels[index].delivered_at 
            };
            tx.objectStore('syncQueue').put(syncItem);

            // تحديث مصفوفة الـ AlpineJS دون تكرار
            const qIndex = this.syncQueue.findIndex(q => q.id === id);
            if (qIndex !== -1) {
                this.syncQueue[qIndex] = syncItem;
            } else {
                this.syncQueue.push(syncItem);
            }

            // محاولة المزامنة الفورية إن كان متصلاً
            if (this.isOnline) {
                this.processSyncQueue();
            }
        },

        // 4. إرسال طابور التعديلات بأمان
        async processSyncQueue() {
            if (this.isSyncing || this.syncQueue.length === 0 || !this.isOnline) return;

            this.isSyncing = true;
            try {
                const res = await fetch('{{ route("office.api.parcels.sync") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ updates: this.syncQueue })
                });

                if (res.ok) {
                    const tx = this.db.transaction('syncQueue', 'readwrite');
                    tx.objectStore('syncQueue').clear();
                    this.syncQueue = [];
                }
            } catch (e) {
                console.error('تعذر المزامنة حالياً، ستتم المحاولة لاحقاً', e);
            } finally {
                this.isSyncing = false;
            }
        },

        async syncData() {
            await this.processSyncQueue();
            if (this.isOnline) {
                await this.fetchServerParcels();
            }
        },

        statusLabel(s) {
            return { in_office: 'بالمكتب', delivered: 'تم التسليم', returned: 'راجع' }[s] || s;
        },

        countStatus(s) {
            return this.parcels.filter(p => p.status === s).length;
        },

        get filteredParcels() {
            return this.parcels.filter(p => {
                const matchFilter = this.filter === 'all' || p.status === this.filter;
                const matchSearch = !this.search || 
                    p.recipient_phone.includes(this.search) || 
                    p.package_type.includes(this.search) || 
                    (p.receipt_number && p.receipt_number.includes(this.search)) ||
                    (p.recipient_name && p.recipient_name.includes(this.search));
                return matchFilter && matchSearch;
            });
        }
    }
}
</script>
</body>
</html>