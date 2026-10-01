<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>بوابة ربط الواتساب</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Tajawal', sans-serif; }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-md bg-slate-800 border border-slate-700 rounded-2xl shadow-2xl p-6 sm:p-8">
        
        <!-- الهيدر -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-14 h-14 bg-emerald-500/10 text-emerald-400 rounded-2xl mb-3 border border-emerald-500/20">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-white tracking-wide">بوابة ربط الواتساب</h1>
            <p class="text-sm text-slate-400 mt-1">إدارة اتصال رقمك بسهولة وأمان</p>
        </div>

        <!-- تنبيه عام -->
        <div id="alertBox" class="hidden mb-4 p-3 rounded-xl text-sm font-medium border text-center transition-all"></div>

        <!-- 1. شاشة الدخول بالرقم السري -->
        <div id="loginView">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">أدخل الرقم السري المخصص لك:</label>
                    <input type="password" id="pinInput" maxlength="10" placeholder="••••" 
                           class="w-full text-center text-2xl tracking-[0.5em] px-4 py-3 bg-slate-900 border border-slate-700 rounded-xl text-white focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition">
                </div>
                <button onclick="handleLogin()" id="loginBtn" 
                        class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-semibold py-3 rounded-xl shadow-lg shadow-emerald-900/30 transition duration-150 flex items-center justify-center gap-2">
                    <span>دخول</span>
                </button>
            </div>
        </div>

        <!-- 2. لوحة التحكم بالربط -->
        <div id="portalView" class="hidden space-y-6">
            
            <!-- معلومات الرقم والحالة -->
            <div class="bg-slate-900/70 border border-slate-700/60 rounded-xl p-4 flex items-center justify-between">
                <div>
                    <div id="clientName" class="font-bold text-white text-base">الاسم</div>
                    <div id="instanceTag" class="text-xs text-slate-400 font-mono mt-0.5">Instance</div>
                </div>
                <div class="text-left">
                    <span id="statusBadge" class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-slate-800 text-slate-300 border border-slate-600">
                        جاري الفحص...
                    </span>
                </div>
            </div>

            <!-- حاوية الـ QR أو كود الربط -->
            <div id="connectionBox" class="bg-slate-900/40 border border-dashed border-slate-700 rounded-2xl p-6 text-center">
                
                <!-- في حال متصل -->
                <div id="connectedState" class="hidden py-6 space-y-3">
                    <div class="w-16 h-16 bg-emerald-500/20 text-emerald-400 rounded-full flex items-center justify-center mx-auto">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <h3 class="text-lg font-bold text-emerald-400">الرقم متصل ويعمل بنجاح</h3>
                    <p class="text-xs text-slate-400">جلسة الواتساب نشطة ومربوطة بالنظام.</p>
                </div>

                <!-- في حال مفصول وعرض الـ QR -->
                <div id="qrState" class="hidden space-y-4">
                    <div id="qrImageWrapper" class="flex justify-center items-center min-h-[200px]">
                        <div class="animate-spin w-8 h-8 border-4 border-emerald-500 border-t-transparent rounded-full"></div>
                    </div>
                    <p class="text-xs text-slate-400">افتح واتساب في هاتفك > الأجهزة المرتبطة > ربط جهاز وامسح الكود</p>
                </div>

                <!-- عرض Pairing Code إن تم طلبه -->
                <div id="pairCodeDisplay" class="hidden my-4 p-4 bg-slate-800 border border-emerald-500/30 rounded-xl">
                    <div class="text-xs text-slate-400 mb-1">كود الربط الخاص بك:</div>
                    <div id="pairCodeValue" class="text-2xl font-mono font-bold text-emerald-400 tracking-wider">----</div>
                    <p class="text-[11px] text-slate-400 mt-2">أدخل هذا الكود في إشعار الواتساب الواصل إلى هاتفك.</p>
                </div>
            </div>

            <!-- خيار كود الربط النصي (Pairing Code) بدلاً من QR -->
            <div id="pairMethodBox" class="border-t border-slate-700/60 pt-4">
                <button onclick="togglePairingInput()" class="text-xs text-emerald-400 hover:text-emerald-300 font-medium underline block mx-auto mb-3">
                    أو اطلب كود الربط المباشر برقم الهاتف (بدون كاميرا)
                </button>
                <div id="pairInputContainer" class="hidden space-y-3">
                    <div class="flex gap-2">
                        <input type="text" id="phoneInput" placeholder="967770000000" dir="ltr" 
                               class="flex-1 bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:border-emerald-500">
                        <button onclick="requestPairing()" class="bg-slate-700 hover:bg-slate-600 text-white px-4 py-2 rounded-xl text-sm font-medium transition">
                            طلب الكود
                        </button>
                    </div>
                </div>
            </div>

            <!-- أزرار الإجراءات -->
            <div class="grid grid-cols-2 gap-3 pt-2">
                <button onclick="checkStatus(true)" class="w-full bg-slate-700 hover:bg-slate-600 text-white py-2.5 rounded-xl text-sm font-medium transition flex items-center justify-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    <span>تحديث الرمز</span>
                </button>
                <button onclick="handleLogout()" class="w-full bg-rose-600/20 hover:bg-rose-600/30 text-rose-400 border border-rose-600/30 py-2.5 rounded-xl text-sm font-medium transition">
                    فصل الرقم
                </button>
            </div>

            <div class="text-center pt-2">
                <button onclick="exitPortal()" class="text-xs text-slate-500 hover:text-slate-400 transition">
                    تسجيل الخروج من البوابة
                </button>
            </div>

        </div>

    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        let checkTimer = null;

        function showAlert(msg, isError = false) {
            const el = document.getElementById('alertBox');
            el.className = isError 
                ? 'mb-4 p-3 rounded-xl text-sm font-medium border text-center bg-rose-500/10 border-rose-500/20 text-rose-400' 
                : 'mb-4 p-3 rounded-xl text-sm font-medium border text-center bg-emerald-500/10 border-emerald-500/20 text-emerald-400';
            el.textContent = msg;
            el.classList.remove('hidden');
        }

        function hideAlert() {
            document.getElementById('alertBox').classList.add('hidden');
        }

        async function handleLogin() {
            hideAlert();
            const pin = document.getElementById('pinInput').value.trim();
            if (!pin) {
                showAlert('يرجى إدخال الرقم السري', true);
                return;
            }

            const btn = document.getElementById('loginBtn');
            btn.disabled = true;
            btn.innerHTML = 'جاري التحقق...';

            try {
                const res = await fetch("{{ route('whatsapp.login') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ pin_code: pin })
                });

                const data = await res.json();

                if (res.ok && data.success) {
                    document.getElementById('clientName').textContent = data.instance.name;
                    document.getElementById('instanceTag').textContent = data.instance.instance_id;
                    if(data.instance.phone_number) {
                        document.getElementById('phoneInput').value = data.instance.phone_number;
                    }
                    document.getElementById('loginView').classList.add('hidden');
                    document.getElementById('portalView').classList.remove('hidden');
                    checkStatus();
                    startPolling();
                } else {
                    showAlert(data.message || 'الرمز السري غير صحيح', true);
                }
            } catch (err) {
                showAlert('تعذر الاتصال بالخادم', true);
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<span>دخول</span>';
            }
        }

        async function checkStatus(forceQr = false) {
            try {
                const res = await fetch("{{ route('whatsapp.status') }}");
                const result = await res.json();

                const badge = document.getElementById('statusBadge');
                const connState = document.getElementById('connectedState');
                const qrState = document.getElementById('qrState');

                // قراءة الحقل بدقة مهما كان مستوى التغليف
                const isConnected = 
                    result.data?.data?.connected === true ||
                    result.data?.connected === true ||
                    result.data?.data?.state === 'open' ||
                    result.data?.state === 'open';

                if (isConnected) {
                    badge.className = 'inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20';
                    badge.textContent = 'متصل';
                    connState.classList.remove('hidden');
                    qrState.classList.add('hidden');
                    document.getElementById('pairCodeDisplay').classList.add('hidden');
                } else {
                    badge.className = 'inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20';
                    badge.textContent = 'مفصول / غير متصل';
                    connState.classList.add('hidden');
                    qrState.classList.remove('hidden');
                    
                    fetchQr();
                }
            } catch (e) {
                console.error('Error fetching status', e);
            }
        }
        async function fetchQr() {
            const wrapper = document.getElementById('qrImageWrapper');
            try {
                const res = await fetch("{{ route('whatsapp.qr') }}");
                const result = await res.json();

                const base64 = result.data?.base64 || result.data?.qr || result.data?.qrcode;
                if (base64) {
                    const src = base64.startsWith('data:') ? base64 : `data:image/png;base64,${base64}`;
                    wrapper.innerHTML = `<img src="${src}" class="w-56 h-56 mx-auto rounded-xl bg-white p-2 shadow-lg" alt="QR Code" />`;
                } else if (result.data?.message) {
                    wrapper.innerHTML = `<p class="text-sm text-slate-400">${result.data.message}</p>`;
                }
            } catch (err) {
                wrapper.innerHTML = `<p class="text-sm text-rose-400">تعذر جلب كود الـ QR</p>`;
            }
        }

        function togglePairingInput() {
            document.getElementById('pairInputContainer').classList.toggle('hidden');
        }

        async function requestPairing() {
            const phone = document.getElementById('phoneInput').value.trim();
            if (!phone) {
                showAlert('يرجى كتابة رقم الهاتف مع مفتاح الدولة', true);
                return;
            }

            try {
                const res = await fetch("{{ route('whatsapp.pair') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ phone: phone })
                });

                const result = await res.json();
                const code = result.data?.code || result.data?.pairingCode || result.data?.pairCode;

                if (code) {
                    document.getElementById('pairCodeValue').textContent = code;
                    document.getElementById('pairCodeDisplay').classList.remove('hidden');
                    showAlert('تم توليد كود الربط بنجاح');
                } else {
                    showAlert(result.message || 'تعذر استخراج كود الربط', true);
                }
            } catch (err) {
                showAlert('خطأ أثناء طلب كود الربط', true);
            }
        }

        async function handleLogout() {
            if (!confirm('هل أنت متأكد من رغبتك في تسجيل الخروج وفصل هذا الرقم؟')) return;
            try {
                await fetch("{{ route('whatsapp.logout') }}", {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken }
                });
                checkStatus(true);
            } catch (e) {
                showAlert('فشل فصل الرقم', true);
            }
        }

        async function exitPortal() {
            await fetch("{{ route('whatsapp.exit') }}", {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken }
            });
            clearInterval(checkTimer);
            window.location.reload();
        }

        function startPolling() {
            if (checkTimer) clearInterval(checkTimer);
            checkTimer = setInterval(() => {
                checkStatus();
            }, 6000); // فحص كل 6 ثوانٍ تلقائياً
        }
    </script>
</body>
</html>