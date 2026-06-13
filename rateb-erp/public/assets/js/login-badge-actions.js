(function () {
    'use strict';

    function initBadgeCard() {
        var card = document.querySelector('[data-login-badge-card]');
        if (!card) {
            return;
        }
        var printBtn = card.querySelector('[data-badge-print]');
        var downloadBtn = card.querySelector('[data-badge-download]');
        var printArea = card.querySelector('[data-badge-print-area]');
        var qrImg = card.querySelector('.rateb-badge-scan-qr');

        if (printBtn && printArea) {
            printBtn.addEventListener('click', function () {
                var win = window.open('', '_blank', 'width=420,height=560');
                if (!win) {
                    window.print();
                    return;
                }
                win.document.write('<html><head><title>' + (card.getAttribute('data-badge-title') || 'Badge') + '</title>');
                win.document.write('<style>body{font-family:system-ui,sans-serif;text-align:center;padding:24px}img{max-width:280px}.code{font-family:monospace;border:1px solid #dee2e6;padding:12px;margin-top:16px}</style></head><body>');
                win.document.write(printArea.innerHTML);
                win.document.write('</body></html>');
                win.document.close();
                win.focus();
                setTimeout(function () {
                    win.print();
                    win.close();
                }, 400);
            });
        }

        if (downloadBtn && qrImg && qrImg.src) {
            downloadBtn.addEventListener('click', function () {
                var link = document.createElement('a');
                link.href = qrImg.src;
                link.download = (card.getAttribute('data-badge-title') || 'login-badge') + '.png';
                link.target = '_blank';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBadgeCard);
    } else {
        initBadgeCard();
    }
})();
