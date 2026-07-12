(function () {
    'use strict';

    function safeName(raw) {
        var name = (raw || 'label').replace(/[^\w\.\-\u0600-\u06FF]+/g, '_');
        return name !== '' ? name : 'label';
    }

    function downloadDataUrl(dataUrl, filename) {
        var link = document.createElement('a');
        link.href = dataUrl;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function downloadBlob(blob, filename) {
        var url = URL.createObjectURL(blob);
        downloadDataUrl(url, filename);
        setTimeout(function () {
            URL.revokeObjectURL(url);
        }, 2000);
    }

    function drawQrOnCanvas(ctx, qrNode, x, y, size, done) {
        if (!qrNode) {
            if (done) {
                done();
            }
            return;
        }
        if (qrNode.tagName === 'CANVAS') {
            try {
                ctx.drawImage(qrNode, x, y, size, size);
            } catch (e) {}
            if (done) {
                done();
            }
            return;
        }
        if (qrNode.tagName === 'IMG' && qrNode.src) {
            var img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = function () {
                try {
                    ctx.drawImage(img, x, y, size, size);
                } catch (e) {}
                if (done) {
                    done();
                }
            };
            img.onerror = function () {
                if (done) {
                    done();
                }
            };
            img.src = qrNode.src;
            return;
        }
        if (done) {
            done();
        }
    }

    function canvasApi() {
        return window.RatebBarcodeCanvas || null;
    }

    function buildLabelCanvas(root, onReady) {
        var area = root.querySelector('.rateb-doc-barcode-print-area');
        if (!area) {
            if (onReady) {
                onReady(null);
            }
            return;
        }
        var api = canvasApi();
        var rtl = api ? api.isRtlNode(area) : false;
        var canvas = document.createElement('canvas');
        var ctx = canvas.getContext('2d');
        if (!ctx) {
            if (onReady) {
                onReady(null);
            }
            return;
        }
        var width = 420;
        var height = 520;
        canvas.width = width;
        canvas.height = height;
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, width, height);

        var y = 32;
        var brand = area.querySelector('.rateb-doc-barcode-brand');
        if (brand) {
            if (api) {
                api.drawCenteredText(ctx, brand.textContent || '', width / 2, y, rtl, '13px Tahoma, Arial, sans-serif', '#6c757d');
            } else {
                ctx.fillStyle = '#6c757d';
                ctx.font = '13px Tahoma, Arial, sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText(brand.textContent || '', width / 2, y);
            }
            y += 28;
        }

        var title = area.querySelector('.rateb-doc-barcode-title');
        if (title) {
            if (api) {
                api.drawCenteredText(ctx, title.textContent || '', width / 2, y, rtl, 'bold 18px Tahoma, Arial, sans-serif', '#212529');
            } else {
                ctx.fillStyle = '#212529';
                ctx.font = 'bold 18px Tahoma, Arial, sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText(title.textContent || '', width / 2, y);
            }
            y += 24;
        }

        var subtitle = area.querySelector('.rateb-doc-barcode-subtitle');
        if (subtitle && subtitle.textContent) {
            if (api) {
                api.drawCenteredText(ctx, subtitle.textContent, width / 2, y, rtl, '14px Tahoma, Arial, sans-serif', '#6c757d');
            } else {
                ctx.font = '14px Tahoma, Arial, sans-serif';
                ctx.fillStyle = '#6c757d';
                ctx.textAlign = 'center';
                ctx.fillText(subtitle.textContent, width / 2, y);
            }
            y += 22;
        }

        var qrNode = area.querySelector('[data-qr-img]') || area.querySelector('[data-qr-canvas]');
        var qrY = y + 12;
        var qrSize = 200;

        function finish() {
            var code = area.querySelector('.rateb-doc-barcode-code');
            if (code) {
                if (api) {
                    api.drawCenteredText(ctx, code.textContent || '', width / 2, height - 28, rtl, '15px monospace', '#212529');
                } else {
                    ctx.fillStyle = '#212529';
                    ctx.font = '15px monospace';
                    ctx.textAlign = 'center';
                    ctx.fillText(code.textContent || '', width / 2, height - 28);
                }
            }
            if (onReady) {
                onReady(canvas);
            }
        }

        if (qrNode) {
            drawQrOnCanvas(ctx, qrNode, (width / 2) - (qrSize / 2), qrY, qrSize, finish);
        } else {
            finish();
        }
    }

    function printArea(root) {
        var area = root.querySelector('.rateb-doc-barcode-print-area');
        if (!area) {
            window.print();
            return;
        }
        document.body.classList.add('rateb-printing-doc-barcode');
        area.classList.add('rateb-print-target');
        window.print();
        setTimeout(function () {
            document.body.classList.remove('rateb-printing-doc-barcode');
            area.classList.remove('rateb-print-target');
        }, 800);
    }

    function downloadLabel(root) {
        var filename = safeName(root.getAttribute('data-label-title')) + '.png';
        var qrImg = root.querySelector('[data-qr-img]');

        buildLabelCanvas(root, function (canvas) {
            if (!canvas) {
                if (qrImg && qrImg.src) {
                    fetch(qrImg.src, { credentials: 'same-origin' })
                        .then(function (r) { return r.blob(); })
                        .then(function (blob) { downloadBlob(blob, filename); })
                        .catch(function () { window.open(qrImg.src, '_blank'); });
                }
                return;
            }
            try {
                downloadDataUrl(canvas.toDataURL('image/png'), filename);
            } catch (e) {
                if (qrImg && qrImg.src) {
                    fetch(qrImg.src, { credentials: 'same-origin' })
                        .then(function (r) { return r.blob(); })
                        .then(function (blob) { downloadBlob(blob, filename); })
                        .catch(function () { window.open(qrImg.src, '_blank'); });
                }
            }
        });
    }

    function loadQrLib(done) {
        if (window.QRCode && typeof window.QRCode === 'function') {
            done();
            return;
        }
        var s = document.createElement('script');
        s.src = (window.RATEB_QRCODE_JS || '').trim();
        if (!s.src) {
            return done();
        }
        s.onload = function () { done(); };
        s.onerror = function () { done(); };
        document.head.appendChild(s);
    }

    function replaceImgWithCanvas(img, qrValue) {
        if (!img || !img.parentNode) {
            return;
        }
        var canvas = document.createElement('canvas');
        canvas.width = 200;
        canvas.height = 200;
        canvas.setAttribute('data-qr-canvas', '');
        canvas.className = img.className || '';
        img.parentNode.replaceChild(canvas, img);
        loadQrLib(function () {
            renderClientQr(canvas, qrValue);
        });
    }

    function bindQrImg(img, qrValue) {
        if (!img) {
            return;
        }
        var triedFallback = false;
        img.addEventListener('error', function () {
            var fallback = img.getAttribute('data-qr-fallback') || '';
            if (!triedFallback && fallback !== '' && img.src !== fallback) {
                triedFallback = true;
                img.src = fallback;
                return;
            }
            replaceImgWithCanvas(img, qrValue);
        });
    }

    function renderClientQr(qrEl, qrValue) {
        if (!qrEl || !qrValue || !window.QRCode || typeof window.QRCode !== 'function') {
            return;
        }
        qrEl.innerHTML = '';
        new window.QRCode(qrEl, {
            text: qrValue,
            width: 200,
            height: 200,
            correctLevel: window.QRCode.CorrectLevel ? window.QRCode.CorrectLevel.H : 2
        });
    }

    function initRoot(root) {
        if (root.getAttribute('data-rateb-init') === '1') {
            return;
        }
        root.setAttribute('data-rateb-init', '1');

        var qrValue = root.getAttribute('data-qr') || '';
        var qrCanvas = root.querySelector('[data-qr-canvas]');
        var qrImg = root.querySelector('[data-qr-img]');

        if (qrImg && qrValue) {
            bindQrImg(qrImg, qrValue);
        } else if (!qrImg && qrCanvas && qrValue) {
            loadQrLib(function () {
                renderClientQr(qrCanvas, qrValue);
            });
        }

        var printBtn = root.querySelector('[data-doc-print]');
        if (printBtn) {
            printBtn.addEventListener('click', function (ev) {
                ev.preventDefault();
                printArea(root);
            });
        }

        var downloadBtn = root.querySelector('[data-doc-download]');
        if (downloadBtn) {
            downloadBtn.addEventListener('click', function (ev) {
                ev.preventDefault();
                downloadLabel(root);
            });
        }
    }

    function initBarcodes() {
        var roots = document.querySelectorAll('[data-rateb-barcodes]');
        for (var i = 0; i < roots.length; i += 1) {
            initRoot(roots[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBarcodes);
    } else {
        initBarcodes();
    }
})();
