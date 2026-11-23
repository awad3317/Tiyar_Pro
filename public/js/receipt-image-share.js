/**
 * مُرسَل برو - التقاط سند الطرد الصادر كصورة PNG عالية الدقة ومشاركتها مباشرة من المتصفح
 * - يعمل عبر html-to-image (UMD browser bundle: window.htmlToImage.toBlob) داخل سياق الـ iframe نفسه.
 * - محصّن ضد أخطاء CORS/الخطوط (إعادة محاولة تلقائية مع skipFonts: true).
 * - محصّن ضد انتهاء مهلة التفاعل (NotAllowedError) على Android Chrome عبر مودال «صورة السند جاهزة» للمشاركة الفورية بضغطة جديدة.
 */
(function (global) {
    'use strict';

    const HTML_TO_IMAGE_LOCAL_SRC = '/js/html-to-image.min.js?v=27';
    const HTML_TO_IMAGE_CDN_SRC = 'https://cdn.jsdelivr.net/npm/html-to-image@1.11.13/dist/html-to-image.js';
    const TRANSPARENT_PIXEL = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
    const IFRAME_LOAD_TIMEOUT_MS = 12000;
    const FONT_WAIT_TIMEOUT_MS = 2500;
    const IMAGE_WAIT_TIMEOUT_MS = 2500;
    const QR_SETTLE_DELAY_MS = 250;
    const MODAL_ID = 'mursal-receipt-share-modal';

    let activeCaptureLock = false;
    let activeModalCleanup = null;

    function dispatchToast(message, type) {
        global.dispatchEvent(new CustomEvent('office-toast', {
            detail: { message: message, type: type || 'success' }
        }));
    }

    function notifyError(message) {
        const msg = message || 'تعذر تجهيز صورة السند للمشاركة، يرجى المحاولة مجدداً.';
        if (typeof global.notifyNetworkError === 'function') {
            global.notifyNetworkError(msg);
        } else {
            dispatchToast(msg, 'error');
        }
    }

    function setButtonBusy(btn, busy) {
        if (!btn) return;
        if (busy) {
            if (!btn.dataset.origHtml) {
                btn.dataset.origHtml = btn.innerHTML;
            }
            btn.disabled = true;
            btn.setAttribute('aria-busy', 'true');
            btn.innerHTML = '<span class="material-symbols-outlined text-[15px] animate-spin">progress_activity</span>';
        } else {
            if (btn.dataset.origHtml) {
                btn.innerHTML = btn.dataset.origHtml;
            }
            btn.disabled = false;
            btn.removeAttribute('aria-busy');
        }
    }

    function delay(ms) {
        return new Promise(function (resolve) {
            setTimeout(resolve, ms);
        });
    }

    function sanitizeReceiptNumber(receiptNumber) {
        const raw = String(receiptNumber || '').trim();
        const cleaned = raw.replace(/[^\w\u0600-\u06FF-]+/g, '');
        return cleaned || 'parcel';
    }

    function loadScriptIntoDoc(doc, src) {
        return new Promise(function (resolve, reject) {
            const script = doc.createElement('script');
            script.src = src;
            script.async = true;
            script.onload = function () { resolve(); };
            script.onerror = function () { reject(new Error('Failed to load script: ' + src)); };
            (doc.head || doc.documentElement).appendChild(script);
        });
    }

    async function ensureHtmlToImageInIframe(iframeWin, iframeDoc) {
        if (iframeWin && iframeWin.htmlToImage && typeof iframeWin.htmlToImage.toBlob === 'function') {
            return iframeWin.htmlToImage;
        }

        try {
            await loadScriptIntoDoc(iframeDoc, HTML_TO_IMAGE_LOCAL_SRC);
        } catch (localErr) {
            console.warn('[ReceiptShare] Local html-to-image failed, trying CDN:', localErr);
            await loadScriptIntoDoc(iframeDoc, HTML_TO_IMAGE_CDN_SRC);
        }

        if (!iframeWin || !iframeWin.htmlToImage || typeof iframeWin.htmlToImage.toBlob !== 'function') {
            throw new Error('window.htmlToImage.toBlob is unavailable inside capture iframe');
        }

        return iframeWin.htmlToImage;
    }

    async function waitForImagesInElement(rootEl) {
        const imgs = Array.from(rootEl.querySelectorAll('img'));
        if (!imgs.length) return;

        await Promise.all(imgs.map(function (img) {
            if (img.complete) {
                return Promise.resolve();
            }

            return Promise.race([
                new Promise(function (resolve) {
                    const done = function () {
                        img.removeEventListener('load', done);
                        img.removeEventListener('error', done);
                        resolve();
                    };
                    img.addEventListener('load', done, { once: true });
                    img.addEventListener('error', done, { once: true });
                }),
                delay(IMAGE_WAIT_TIMEOUT_MS),
            ]);
        }));
    }

    async function waitForQrAndFonts(iframeDoc, sheetEl) {
        if (iframeDoc.fonts && iframeDoc.fonts.ready) {
            await Promise.race([
                iframeDoc.fonts.ready.catch(function () {}),
                delay(FONT_WAIT_TIMEOUT_MS),
            ]);
        }

        const qrWrapper = sheetEl.querySelector('.qr-wrapper');
        if (qrWrapper) {
            const deadline = Date.now() + 1000;
            while (Date.now() < deadline && !qrWrapper.querySelector('img, canvas')) {
                await delay(40);
            }
        }

        await waitForImagesInElement(sheetEl);
        await delay(QR_SETTLE_DELAY_MS);
    }

    function createHiddenCaptureHost(receiptUrl) {
        return new Promise(function (resolve, reject) {
            // حاوية 1x1 داخل نطاق الرؤية (خلف الصفحة وغير مرئية) لمنع تجميد requestAnimationFrame في Android Chrome
            // مع الحفاظ على أبعاد مكتبية حقيقية 820x900 داخل الـ iframe دون أي تمرير أفقي
            const host = document.createElement('div');
            host.setAttribute('aria-hidden', 'true');
            host.style.cssText = 'position:fixed;left:0;top:0;width:1px;height:1px;overflow:hidden;opacity:0;pointer-events:none;z-index:-9999;';

            const iframe = document.createElement('iframe');
            iframe.setAttribute('aria-hidden', 'true');
            iframe.setAttribute('tabindex', '-1');
            iframe.style.cssText = 'width:820px;height:900px;border:0;display:block;';

            let settled = false;
            const timer = setTimeout(function () {
                if (settled) return;
                settled = true;
                if (host.parentNode) host.remove();
                reject(new Error('Receipt iframe load timeout'));
            }, IFRAME_LOAD_TIMEOUT_MS);

            iframe.onload = function () {
                if (settled) return;
                settled = true;
                clearTimeout(timer);
                resolve({ host: host, iframe: iframe });
            };

            iframe.onerror = function () {
                if (settled) return;
                settled = true;
                clearTimeout(timer);
                if (host.parentNode) host.remove();
                reject(new Error('Receipt iframe load failed'));
            };

            host.appendChild(iframe);
            document.body.appendChild(host);
            iframe.src = receiptUrl;
        });
    }

    function captureFilter(node) {
        if (!node || node.nodeType !== 1) return true;
        // استبعاد الصور المكسورة (naturalWidth === 0) حتى لا تفشل عملية التحويل
        if (node.tagName === 'IMG' && node.complete && node.naturalWidth === 0) {
            return false;
        }
        return true;
    }

    async function captureReceiptBlob(htmlToImage, sheetEl) {
        const baseOptions = {
            pixelRatio: 2,
            backgroundColor: '#ffffff',
            cacheBust: false,
            preferredFontFormat: 'woff2',
            imagePlaceholder: TRANSPARENT_PIXEL,
            filter: captureFilter,
            style: {
                margin: '0',
                boxShadow: 'none',
                transform: 'none',
            },
        };

        try {
            const primaryBlob = await htmlToImage.toBlob(sheetEl, baseOptions);
            if (primaryBlob && primaryBlob.size > 0) {
                return primaryBlob;
            }
            throw new Error('Primary capture returned empty blob');
        } catch (primaryErr) {
            console.warn('[ReceiptShare] Primary capture failed, retrying with skipFonts:', primaryErr);
        }

        try {
            const noFontsBlob = await htmlToImage.toBlob(sheetEl, Object.assign({}, baseOptions, {
                skipFonts: true,
            }));
            if (noFontsBlob && noFontsBlob.size > 0) {
                return noFontsBlob;
            }
            throw new Error('skipFonts capture returned empty blob');
        } catch (skipFontsErr) {
            console.warn('[ReceiptShare] 2x skipFonts capture failed, retrying at 1.5x:', skipFontsErr);
        }

        const lowMemBlob = await htmlToImage.toBlob(sheetEl, Object.assign({}, baseOptions, {
            pixelRatio: 1.5,
            skipFonts: true,
        }));

        if (!lowMemBlob || lowMemBlob.size === 0) {
            throw new Error('Generated PNG blob is empty');
        }

        return lowMemBlob;
    }

    function triggerPngDownload(blob, fileName) {
        const objectUrl = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = objectUrl;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(function () {
            URL.revokeObjectURL(objectUrl);
        }, 5000);
    }

    function canNativeShareFile(file) {
        try {
            return Boolean(
                typeof navigator !== 'undefined' &&
                typeof navigator.share === 'function' &&
                typeof navigator.canShare === 'function' &&
                navigator.canShare({ files: [file] })
            );
        } catch (_) {
            return false;
        }
    }

    function closeReadyModal() {
        if (typeof activeModalCleanup === 'function') {
            const fn = activeModalCleanup;
            activeModalCleanup = null;
            fn();
        }
    }

    function openReceiptReadyModal(blob, file, safeNum, fileName) {
        closeReadyModal();

        const previewUrl = URL.createObjectURL(blob);
        const supportsShare = canNativeShareFile(file);

        const overlay = document.createElement('div');
        overlay.id = MODAL_ID;
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('dir', 'rtl');
        overlay.className = 'fixed inset-0 z-[75] flex items-end sm:items-center justify-center p-3 sm:p-4';

        overlay.innerHTML =
            '<div data-modal-backdrop class="fixed inset-0 bg-slate-950/65 backdrop-blur-[1px]"></div>' +
            '<div class="relative z-10 w-full max-w-md bg-white rounded-3xl p-4 sm:p-5 shadow-2xl border border-slate-100 flex flex-col gap-3.5 text-right max-h-[90vh]">' +
                '<div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-2.5">' +
                    '<div class="flex items-center gap-2 min-w-0">' +
                        '<span class="w-8 h-8 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 flex items-center justify-center shrink-0">' +
                            '<span class="material-symbols-outlined text-lg">image</span>' +
                        '</span>' +
                        '<div class="min-w-0">' +
                            '<h3 class="text-sm font-extrabold text-slate-800 leading-tight">صورة السند جاهزة</h3>' +
                            '<p class="text-[11px] font-bold text-slate-500 font-mono truncate">سند #' + safeNum + '</p>' +
                        '</div>' +
                    '</div>' +
                    '<button type="button" data-modal-close title="إغلاق" aria-label="إغلاق" ' +
                        'class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center shrink-0 transition-colors">' +
                        '<span class="material-symbols-outlined text-base">close</span>' +
                    '</button>' +
                '</div>' +
                '<div class="rounded-2xl border border-slate-200 bg-slate-50 p-2 overflow-auto max-h-[52vh] flex items-center justify-center">' +
                    '<img data-modal-preview alt="معاينة صورة السند" class="w-full h-auto rounded-xl shadow-xs bg-white block" />' +
                '</div>' +
                '<div class="flex flex-col sm:flex-row items-stretch gap-2 pt-0.5">' +
                    (supportsShare
                        ? '<button type="button" data-modal-share ' +
                              'class="flex-1 py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] text-white text-xs font-extrabold shadow-md shadow-emerald-600/25 flex items-center justify-center gap-1.5 transition-all">' +
                              '<span class="material-symbols-outlined text-base">share</span>' +
                              '<span>مشاركة عبر واتساب</span>' +
                          '</button>'
                        : '') +
                    '<button type="button" data-modal-download ' +
                        'class="' + (supportsShare ? 'sm:w-auto' : 'flex-1') + ' py-2.5 px-4 rounded-xl bg-primary hover:bg-slate-800 active:scale-[0.98] text-white text-xs font-extrabold flex items-center justify-center gap-1.5 transition-all">' +
                        '<span class="material-symbols-outlined text-base text-secondary">download</span>' +
                        '<span>حفظ الصورة</span>' +
                    '</button>' +
                '</div>' +
            '</div>';

        const imgEl = overlay.querySelector('[data-modal-preview]');
        if (imgEl) {
            imgEl.src = previewUrl;
        }

        const onKeyDown = function (e) {
            if (e.key === 'Escape') {
                closeReadyModal();
            }
        };

        activeModalCleanup = function () {
            document.removeEventListener('keydown', onKeyDown);
            if (overlay.parentNode) {
                overlay.remove();
            }
            URL.revokeObjectURL(previewUrl);
        };

        document.addEventListener('keydown', onKeyDown);

        const backdrop = overlay.querySelector('[data-modal-backdrop]');
        if (backdrop) {
            backdrop.addEventListener('click', closeReadyModal);
        }

        const closeBtn = overlay.querySelector('[data-modal-close]');
        if (closeBtn) {
            closeBtn.addEventListener('click', closeReadyModal);
        }

        const shareBtn = overlay.querySelector('[data-modal-share]');
        if (shareBtn) {
            shareBtn.addEventListener('click', async function () {
                try {
                    await navigator.share({
                        files: [file],
                        title: 'سند إرسال #' + safeNum,
                    });
                    closeReadyModal();
                } catch (shareErr) {
                    if (shareErr && shareErr.name === 'AbortError') {
                        return;
                    }
                    console.error('[ReceiptShare] Modal share failed:', shareErr);
                    triggerPngDownload(blob, fileName);
                    dispatchToast('تم تحميل صورة السند بنجاح.', 'success');
                    closeReadyModal();
                }
            });
        }

        const downloadBtn = overlay.querySelector('[data-modal-download]');
        if (downloadBtn) {
            downloadBtn.addEventListener('click', function () {
                triggerPngDownload(blob, fileName);
                dispatchToast('تم تحميل صورة السند بنجاح.', 'success');
                closeReadyModal();
            });
        }

        document.body.appendChild(overlay);
    }

    async function shareReceiptAsImage(receiptUrl, receiptNumber, triggerBtn) {
        if (!receiptUrl) return;

        if (typeof global.requireOnlineAction === 'function' && !global.requireOnlineAction()) {
            return;
        }

        if (activeCaptureLock) return;
        activeCaptureLock = true;
        setButtonBusy(triggerBtn, true);

        let hostEl = null;

        try {
            const created = await createHiddenCaptureHost(receiptUrl);
            hostEl = created.host;
            const iframe = created.iframe;
            const iframeWin = iframe.contentWindow;
            const iframeDoc = iframe.contentDocument;

            if (!iframeWin || !iframeDoc) {
                throw new Error('Unable to access receipt iframe document');
            }

            const sheetEl = iframeDoc.querySelector('#receiptCard1')
                || iframeDoc.querySelector('.receipt-sheet')
                || iframeDoc.querySelector('.receipt-card');

            if (!sheetEl) {
                throw new Error('Receipt sheet element not found');
            }

            const stageEl = iframeDoc.querySelector('.sheet-stage');
            if (stageEl) {
                stageEl.style.transform = 'none';
                stageEl.style.width = '740px';
            }

            const htmlToImage = await ensureHtmlToImageInIframe(iframeWin, iframeDoc);
            await waitForQrAndFonts(iframeDoc, sheetEl);

            const blob = await captureReceiptBlob(htmlToImage, sheetEl);
            const safeNum = sanitizeReceiptNumber(receiptNumber);
            const fileName = 'سند-إرسال-' + safeNum + '.png';
            const file = new File([blob], fileName, { type: 'image/png' });

            if (canNativeShareFile(file)) {
                try {
                    await navigator.share({
                        files: [file],
                        title: 'سند إرسال #' + safeNum,
                    });
                    return;
                } catch (shareErr) {
                    if (shareErr && shareErr.name === 'AbortError') {
                        return;
                    }
                    if (shareErr && shareErr.name === 'NotAllowedError') {
                        console.warn('[ReceiptShare] Direct navigator.share gesture expired (NotAllowedError); opening ready modal.');
                        openReceiptReadyModal(blob, file, safeNum, fileName);
                        return;
                    }
                    console.warn('[ReceiptShare] Direct navigator.share failed; opening ready modal:', shareErr);
                    openReceiptReadyModal(blob, file, safeNum, fileName);
                    return;
                }
            }

            openReceiptReadyModal(blob, file, safeNum, fileName);
        } catch (err) {
            console.error('[ReceiptShare]', err);
            notifyError('تعذر تجهيز صورة السند للمشاركة، يرجى المحاولة مجدداً.');
        } finally {
            if (hostEl && hostEl.parentNode) {
                hostEl.remove();
            }
            activeCaptureLock = false;
            setButtonBusy(triggerBtn, false);
        }
    }

    global.shareReceiptAsImage = shareReceiptAsImage;
})(window);
