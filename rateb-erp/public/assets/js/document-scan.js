(function () {
    'use strict';

    function safeName(raw) {
        var name = (raw || 'document').replace(/[^\w\.\-\u0600-\u06FF]+/g, '_');
        return name !== '' ? name : 'document';
    }

    function downloadDataUrl(dataUrl, filename) {
        var link = document.createElement('a');
        link.href = dataUrl;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function drawQr(ctx, img, x, y, size, done) {
        if (!img || !img.src) {
            if (done) {
                done();
            }
            return;
        }
        var loader = new Image();
        loader.onload = function () {
            try {
                ctx.drawImage(loader, x, y, size, size);
            } catch (e) {}
            if (done) {
                done();
            }
        };
        loader.onerror = function () {
            if (done) {
                done();
            }
        };
        loader.src = img.src;
    }

    function buildCanvas(root, onReady) {
        var area = root.querySelector('.rateb-scan-print-area');
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
        var width = 420;
        var height = 560;
        canvas.width = width;
        canvas.height = height;
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, width, height);
        ctx.textAlign = 'center';
        ctx.fillStyle = '#212529';

        var y = 28;
        var brand = area.querySelector('.rateb-doc-barcode-brand');
        if (brand) {
            ctx.font = '13px Tahoma, Arial, sans-serif';
            ctx.fillStyle = '#6c757d';
            ctx.fillText(brand.textContent || '', width / 2, y);
            y += 24;
        }

        var qrImg = area.querySelector('[data-qr-img]');
        if (qrImg) {
            drawQr(ctx, qrImg, (width / 2) - 90, y, 180, function () {
                y += 200;
                drawText(area, ctx, width, y, onReady, canvas);
            });
        } else {
            drawText(area, ctx, width, y, onReady, canvas);
        }
    }

    function drawText(area, ctx, width, y, onReady, canvas) {
        var title = area.querySelector('.rateb-doc-barcode-title');
        if (title) {
            ctx.fillStyle = '#212529';
            ctx.font = 'bold 17px Tahoma, Arial, sans-serif';
            ctx.fillText(title.textContent || '', width / 2, y);
            y += 22;
        }
        var subtitle = area.querySelector('.rateb-doc-barcode-subtitle');
        if (subtitle && subtitle.textContent) {
            ctx.font = '14px Tahoma, Arial, sans-serif';
            ctx.fillStyle = '#6c757d';
            ctx.fillText(subtitle.textContent, width / 2, y);
            y += 20;
        }
        var code = area.querySelector('.rateb-doc-barcode-code');
        if (code) {
            ctx.font = '14px monospace';
            ctx.fillStyle = '#495057';
            ctx.fillText(code.textContent || '', width / 2, y);
            y += 24;
        }

        ctx.textAlign = 'right';
        ctx.font = '13px Tahoma, Arial, sans-serif';
        var rows = area.querySelectorAll('dl dt');
        var vals = area.querySelectorAll('dl dd');
        for (var i = 0; i < rows.length; i += 1) {
            var label = rows[i].textContent || '';
            var value = vals[i] ? (vals[i].textContent || '') : '';
            ctx.fillStyle = '#6c757d';
            ctx.fillText(label, width - 30, y);
            ctx.textAlign = 'left';
            ctx.fillStyle = '#212529';
            ctx.fillText(value, 30, y);
            ctx.textAlign = 'right';
            y += 20;
        }

        if (onReady) {
            onReady(canvas);
        }
    }

    function printView(root) {
        var area = root.querySelector('.rateb-scan-print-area');
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

    function downloadView(root) {
        var filename = safeName(root.getAttribute('data-label-title')) + '.png';
        buildCanvas(root, function (canvas) {
            if (!canvas) {
                return;
            }
            try {
                downloadDataUrl(canvas.toDataURL('image/png'), filename);
            } catch (e) {}
        });
    }

    function initRoot(root) {
        var printBtn = root.querySelector('[data-scan-print]');
        if (printBtn) {
            printBtn.addEventListener('click', function (e) {
                e.preventDefault();
                printView(root);
            });
        }
        var downloadBtn = root.querySelector('[data-scan-download]');
        if (downloadBtn) {
            downloadBtn.addEventListener('click', function (e) {
                e.preventDefault();
                downloadView(root);
            });
        }
    }

    function init() {
        var roots = document.querySelectorAll('[data-rateb-scan-view]');
        for (var i = 0; i < roots.length; i += 1) {
            initRoot(roots[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
