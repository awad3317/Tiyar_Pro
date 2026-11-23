/**
 * مُرسَل برو - التقاط سند الطرد الصادر كصورة PNG عالية الدقة ومشاركتها مباشرة من المتصفح
 * يعتمد على html-to-image (محرك Chrome/SVG الأصلي) للحفاظ على تواصل الحروف العربية RTL
 * وتخطيط CSS Grid والباركود ورمز QR بدقة 2x بدون أي حزم خادم.
 */
(function (global) {
    'use strict';

    const HTML_TO_IMAGE_LOCAL_SRC = '/js/html-to-image.min.js?v=1';
    const HTML_TO_IMAGE_CDN_SRC = 'https://cdn.jsdelivr.net/npm/html-to-image@1.11.13/dist/html-to-image.js';
    const IFRAME_LOAD_TIMEOUT_MS = 12000;
    const FONT_WAIT_TIMEOUT_MS = 2500;
    const IMAGE_WAIT_TIMEOUT_MS = 2500;
    const QR_SETTLE_DELAY_MS = 240;

    let activeCaptureLock = false;

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
        } catch (_) {
            await loadScriptIntoDoc(iframeDoc, HTML_TO_IMAGE_CDN_SRC);
        }

        if (!iframeWin || !iframeWin.htmlToImage || typeof iframeWin.htmlToImage.toBlob !== 'function') {
            throw new Error('htmlToImage library unavailable inside capture iframe');
        }

        return iframeWin.htmlToImage;
    }

    async function waitForImagesInElement(rootEl) {
        const imgs = Array.from(rootEl.querySelectorAll('img'));
        if (!imgs.length) return;

        await Promise.all(imgs.map(function (img) {
            if (img.complete && img.naturalWidth > 0) {
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

        // انتظار اكتمال توليد رمز QR (qrcodejs ينشئ <img> أو <canvas> داخل .qr-wrapper)
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

    function createOffscreenIframe(receiptUrl) {
        return new Promise(function (resolve, reject) {
            const iframe = document.createElement('iframe');
            iframe.setAttribute('aria-hidden', 'true');
            iframe.setAttribute('tabindex', '-1');
            iframe.style.cssText = 'position:fixed;left:-9999px;top:0;width:820px;height:900px;border:0;opacity:0;pointer-events:none;';

            let settled = false;
            const timer = setTimeout(function () {
                if (settled) return;
                settled = true;
                reject(new Error('Receipt iframe load timeout'));
            }, IFRAME_LOAD_TIMEOUT_MS);

            iframe.onload = function () {
                if (settled) return;
                settled = true;
                clearTimeout(timer);
                resolve(iframe);
            };

            iframe.onerror = function () {
                if (settled) return;
                settled = true;
                clearTimeout(timer);
                reject(new Error('Receipt iframe load failed'));
            };

            document.body.appendChild(iframe);
            iframe.src = receiptUrl;
        });
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

    async function shareReceiptAsImage(receiptUrl, receiptNumber, triggerBtn) {
        if (!receiptUrl) return;

        if (typeof global.requireOnlineAction === 'function' && !global.requireOnlineAction()) {
            return;
        }

        if (activeCaptureLock) return;
        activeCaptureLock = true;
        setButtonBusy(triggerBtn, true);

        let iframe = null;

        try {
            iframe = await createOffscreenIframe(receiptUrl);
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

            // ضمان إلغاء أي تصغير نسبي قبل الالتقاط
            const stageEl = iframeDoc.querySelector('.sheet-stage');
            if (stageEl) {
                stageEl.style.transform = 'none';
                stageEl.style.width = '740px';
            }

            const htmlToImage = await ensureHtmlToImageInIframe(iframeWin, iframeDoc);
            await waitForQrAndFonts(iframeDoc, sheetEl);

            const captureOptions = {
                pixelRatio: 2,
                backgroundColor: '#ffffff',
                cacheBust: true,
                style: {
                    margin: '0',
                    boxShadow: 'none',
                    transform: 'none',
                },
            };

            let blob = null;
            try {
                blob = await htmlToImage.toBlob(sheetEl, captureOptions);
            } catch (_) {
                // محاولة احتياطية بدون تضمين الخطوط الخارجية إذا تعذر جلب CSS الخطوط
                blob = await htmlToImage.toBlob(sheetEl, Object.assign({}, captureOptions, {
                    skipFonts: true,
                }));
            }

            if (!blob || blob.size === 0) {
                throw new Error('Generated PNG blob is empty');
            }

            const safeNum = sanitizeReceiptNumber(receiptNumber);
            const fileName = 'سند-إرسال-' + safeNum + '.png';
            const file = new File([blob], fileName, { type: 'image/png' });

            if (
                typeof navigator !== 'undefined' &&
                typeof navigator.share === 'function' &&
                typeof navigator.canShare === 'function' &&
                navigator.canShare({ files: [file] })
            ) {
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
                }
            }

            triggerPngDownload(blob, fileName);
            dispatchToast('تم تحميل صورة السند بنجاح.', 'success');
        } catch (err) {
            console.error('Receipt PNG capture/share failed:', err);
            notifyError('تعذر تجهيز صورة السند للمشاركة، يرجى المحاولة مجدداً.');
        } finally {
            if (iframe && iframe.parentNode) {
                iframe.remove();
            }
            activeCaptureLock = false;
            setButtonBusy(triggerBtn, false);
        }
    }

    global.shareReceiptAsImage = shareReceiptAsImage;
})(window);
