(function () {
    'use strict';

    function initBarcodes() {
        var root = document.querySelector('[data-rateb-barcodes]');
        if (!root) {
            return;
        }
        var barcodeValue = root.getAttribute('data-barcode') || '';
        var qrValue = root.getAttribute('data-qr') || '';
        var barcodeEl = root.querySelector('[data-barcode-svg]');
        var qrEl = root.querySelector('[data-qr-canvas]');

        if (barcodeEl && barcodeValue && window.JsBarcode) {
            try {
                JsBarcode(barcodeEl, barcodeValue, {
                    format: 'CODE128',
                    displayValue: true,
                    fontSize: 14,
                    height: 60,
                    margin: 10
                });
            } catch (e) {}
        }

        if (qrEl && qrValue && window.QRCode) {
            try {
                QRCode.toCanvas(qrEl, qrValue, { width: 180, margin: 2 });
            } catch (e) {}
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBarcodes);
    } else {
        initBarcodes();
    }
})();
