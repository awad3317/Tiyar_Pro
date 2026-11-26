/**
 * Mursal Desktop PWA Helper
 * يدير تثبيت تطبيق المكتب كبرنامج سطح مكتب مستقل (Windows / macOS / Chrome / Edge)
 * ويضيف اختصارات لوحة المفاتيح السريعة لموظف الكمبيوتر دون التأثير على الجوال.
 */
(function (global) {
    'use strict';

    let deferredInstallPrompt = null;

    function checkStandalone() {
        try {
            return Boolean(
                (global.matchMedia && global.matchMedia('(display-mode: standalone)').matches) ||
                (global.matchMedia && global.matchMedia('(display-mode: window-controls-overlay)').matches) ||
                global.navigator.standalone === true
            );
        } catch (_) {
            return false;
        }
    }

    function broadcastState() {
        global.dispatchEvent(new CustomEvent('mursal-pwa-state', {
            detail: {
                canInstall: Boolean(deferredInstallPrompt) && !checkStandalone(),
                isStandalone: checkStandalone(),
            }
        }));
    }

    global.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        deferredInstallPrompt = event;
        broadcastState();
    });

    global.addEventListener('appinstalled', function () {
        deferredInstallPrompt = null;
        broadcastState();
        global.dispatchEvent(new CustomEvent('office-toast', {
            detail: {
                message: 'تم تثبيت برنامج مُرسَل على جهازك بنجاح! يمكنك فتحه الآن من سطح المكتب أو شريط المهام.',
                type: 'success'
            }
        }));
    });

    async function promptInstall() {
        if (!deferredInstallPrompt) {
            if (checkStandalone()) {
                global.dispatchEvent(new CustomEvent('office-toast', {
                    detail: { message: 'أنت تستخدم بالفعل نسخة سطح المكتب المثبّتة من برنامج مُرسَل.', type: 'info' }
                }));
            } else {
                global.dispatchEvent(new CustomEvent('office-toast', {
                    detail: { message: 'لتثبيت البرنامج: اضغط على أيقونة التثبيت في شريط عنوان المتصفح (Chrome / Edge).', type: 'info' }
                }));
            }
            return false;
        }

        try {
            deferredInstallPrompt.prompt();
            const choice = await deferredInstallPrompt.userChoice;
            if (choice && choice.outcome === 'accepted') {
                deferredInstallPrompt = null;
            }
            broadcastState();
            return Boolean(choice && choice.outcome === 'accepted');
        } catch (_) {
            return false;
        }
    }

    function isEditableTarget(el) {
        if (!el || !el.tagName) return false;
        const tag = el.tagName.toUpperCase();
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return true;
        return el.isContentEditable === true;
    }

    function triggerPrimaryQuickAction() {
        const path = global.location.pathname || '';
        if (path.startsWith('/office/outgoing-parcels')) {
            global.dispatchEvent(new CustomEvent('open-outgoing-parcel-modal'));
            return;
        }
        if (path === '/office/shipments/sent') {
            global.dispatchEvent(new CustomEvent('open-create-shipment-modal'));
            return;
        }
        if (path.startsWith('/office/favorite-customers')) {
            global.dispatchEvent(new CustomEvent('open-vip-customer-modal'));
            return;
        }
        if (path.startsWith('/office/external-partner-links')) {
            global.dispatchEvent(new CustomEvent('open-partner-link-modal'));
            return;
        }
        // الافتراضي في الرئيسية والطرود الواردة: نافذة الإرسال السريع
        const headerQuickBtn = document.querySelector('[data-desktop-primary-add]');
        if (headerQuickBtn) {
            headerQuickBtn.click();
        }
    }

    function focusSearchInput() {
        const searchEl =
            document.querySelector('header input[type="text"], header input[type="search"]') ||
            document.querySelector('main input[placeholder*="باركود"], main input[placeholder*="بحث"]');
        if (searchEl && typeof searchEl.focus === 'function') {
            searchEl.focus();
            if (typeof searchEl.select === 'function') {
                searchEl.select();
            }
            return true;
        }
        return false;
    }

    // اختصارات الكيبورد السريعة لسطح المكتب (تعمل مع اللغتين العربية والإنجليزية عبر event.code)
    document.addEventListener('keydown', function (event) {
        if (event.defaultPrevented) return;

        // Alt + N: فتح نافذة الإضافة السريعة الخاصة بالقسم الحالي
        if (event.altKey && !event.ctrlKey && !event.metaKey && event.code === 'KeyN') {
            event.preventDefault();
            triggerPrimaryQuickAction();
            return;
        }

        // زر (/) خارج حقول الكتابة: تركيز فوري في حقل البحث أو مسح الباركود
        if (!event.altKey && !event.ctrlKey && !event.metaKey && event.key === '/' && !isEditableTarget(event.target)) {
            if (focusSearchInput()) {
                event.preventDefault();
            }
        }
    });

    global.MursalDesktopPWA = {
        canInstall: function () {
            return Boolean(deferredInstallPrompt) && !checkStandalone();
        },
        isStandalone: checkStandalone,
        promptInstall: promptInstall,
    };
})(window);
