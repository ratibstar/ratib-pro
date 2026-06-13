(function () {
    'use strict';

    function svgToDataUrl(svg) {
        if (!svg) {
            return '';
        }
        var xml = new XMLSerializer().serializeToString(svg);
        return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(xml);
    }

    function downloadDataUrl(dataUrl, filename) {
        var link = document.createElement('a');
        link.href = dataUrl;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
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

    function buildLabelCanvas(root, onReady) {
        var area = root.querySelector('.rateb-doc-barcode-print-area');
        if (!area) {
            if (onReady) {
                onReady(null);
            }
            return;
        }
        var canvas = document.createElement('canvas');
        var ctx = canvas.getContext('2d');
        if (!ctx) {
            if (onReady) {
                onReady(null);
            }
            return;
        }
        var width = 640;
        var height = 420;
        canvas.width = width;
        canvas.height = height;
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, width, height);
        ctx.fillStyle = '#212529';
        ctx.textAlign = 'center';

        var y = 36;
        var brand = area.querySelector('.rateb-doc-barcode-brand');
        if (brand) {
            ctx.font = '12px sans-serif';
            ctx.fillStyle = '#6c757d';
            ctx.fillText(brand.textContent || '', width / 2, y);
            y += 28;
        }

        var title = area.querySelector('.rateb-doc-barcode-title');
        if (title) {
            ctx.fillStyle = '#212529';
            ctx.font = 'bold 20px sans-serif';
            ctx.fillText(title.textContent || '', width / 2, y);
            y += 26;
        }

        var subtitle = area.querySelector('.rateb-doc-barcode-subtitle');
        if (subtitle && subtitle.textContent) {
            ctx.font = '14px sans-serif';
            ctx.fillStyle = '#6c757d';
            ctx.fillText(subtitle.textContent, width / 2, y);
            y += 24;
        }

        var svg = area.querySelector('[data-barcode-svg]');
        var qrNode = area.querySelector('[data-qr-img]') || area.querySelector('[data-qr-canvas]');
        var barcodeY = y + 10;
        var pending = 0;

        function finish() {
            var code = area.querySelector('.rateb-doc-barcode-code');
            if (code) {
                ctx.fillStyle = '#212529';
                ctx.font = '16px monospace';
                ctx.fillText(code.textContent || '', width / 2, height - 24);
            }
            if (onReady) {
                onReady(canvas);
            }
        }

        function done() {
            pending -= 1;
            if (pending <= 0) {
                finish();
            }
        }

        if (svg && svg.childNodes.length) {
            pending += 1;
            var barcodeImg = new Image();
            barcodeImg.onload = function () {
                ctx.drawImage(barcodeImg, (width / 2) - 140, barcodeY, 280, 70);
                done();
            };
            barcodeImg.onerror = done;
            barcodeImg.src = svgToDataUrl(svg);
        }

        if (qrNode) {
            pending += 1;
            drawQrOnCanvas(ctx, qrNode, (width / 2) - 90, barcodeY + 90, 180, done);
        }

        if (pending === 0) {
            finish();
        }
    }

    function printArea(root) {
        var area = root.querySelector('.rateb-doc-barcode-print-area');
        if (!area) {
            window.print();
            return;
        }
        var win = window.open('', '_blank', 'width=720,height=560');
        if (!win) {
            window.print();
            return;
        }
        var styles = ''
            + 'body{font-family:system-ui,sans-serif;margin:24px;color:#212529}'
            + '.text-center{text-align:center}'
            + '.text-muted{color:#6c757d}'
            + '.small{font-size:.875rem}'
            + '.mb-1{margin-bottom:.25rem}.mb-2{margin-bottom:.5rem}.mb-3{margin-bottom:1rem}.mt-3{margin-top:1rem}'
            + '.font-monospace{font-family:monospace}'
            + 'svg{max-width:100%}.rateb-doc-qr-img,canvas{max-width:180px;height:auto}';
        win.document.write('<html><head><title>' + (root.getAttribute('data-label-title') || 'Barcode') + '</title>');
        win.document.write('<style>' + styles + '</style></head><body>');
        win.document.write(area.innerHTML);
        win.document.write('</body></html>');
        win.document.close();
        win.focus();
        setTimeout(function () {
            win.print();
            win.close();
        }, 500);
    }

    function renderClientQr(qrEl, qrValue) {
        if (!qrEl || !qrValue) {
            return;
        }
        if (window.QRCode && typeof window.QRCode.toCanvas === 'function') {
            window.QRCode.toCanvas(qrEl, qrValue, { width: 180, margin: 2 }, function () {});
            return;
        }
        if (window.QRCode && typeof window.QRCode === 'function') {
            qrEl.innerHTML = '';
            new window.QRCode(qrEl, {
                text: qrValue,
                width: 180,
                height: 180,
                correctLevel: window.QRCode.CorrectLevel ? window.QRCode.CorrectLevel.H : 2
            });
        }
    }

    function initRoot(root) {
        var barcodeValue = root.getAttribute('data-barcode') || '';
        var qrValue = root.getAttribute('data-qr') || '';
        var barcodeEl = root.querySelector('[data-barcode-svg]');
        var qrCanvas = root.querySelector('[data-qr-canvas]');
        var qrImg = root.querySelector('[data-qr-img]');

        if (barcodeEl && barcodeValue && window.JsBarcode) {
            try {
                JsBarcode(barcodeEl, barcodeValue, {
                    format: 'CODE128',
                    displayValue: false,
                    fontSize: 14,
                    height: 60,
                    margin: 10
                });
            } catch (e) {}
        }

        if (!qrImg && qrCanvas && qrValue) {
            renderClientQr(qrCanvas, qrValue);
        }

        var printBtn = root.querySelector('[data-barcode-print]');
        if (printBtn) {
            printBtn.addEventListener('click', function () {
                printArea(root);
            });
        }

        var downloadBtn = root.querySelector('[data-barcode-download]');
        if (downloadBtn) {
            downloadBtn.addEventListener('click', function () {
                buildLabelCanvas(root, function (canvas) {
                    if (canvas) {
                        downloadDataUrl(canvas.toDataURL('image/png'), (root.getAttribute('data-label-title') || 'barcode') + '.png');
                    }
                });
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
