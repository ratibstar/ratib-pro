/**
 * High-contrast QR images (same engine as login pairing QR — api.qrserver.com).
 */
(function (global) {
    'use strict';

    function ratibQrImageUrl(data, size) {
        var px = Math.max(120, Math.min(512, parseInt(size, 10) || 280));
        return 'https://api.qrserver.com/v1/create-qr-code/?'
            + 'size=' + px + 'x' + px
            + '&margin=18'
            + '&ecc=H'
            + '&color=000000'
            + '&bgcolor=ffffff'
            + '&format=png'
            + '&data=' + encodeURIComponent(String(data || ''));
    }

    /**
     * @param {HTMLElement} host
     * @param {string} data
     * @param {number} size
     */
    function ratibRenderQrImage(host, data, size) {
        if (!host || !data) {
            return;
        }
        var px = Math.max(120, Math.min(512, parseInt(size, 10) || 280));
        host.innerHTML = '';
        var wrap = document.createElement('div');
        wrap.className = 'ratib-qr-image-wrap';
        var img = document.createElement('img');
        img.className = 'ratib-qr-image';
        img.src = ratibQrImageUrl(data, px);
        img.width = px;
        img.height = px;
        img.alt = 'Scan QR code';
        img.setAttribute('decoding', 'async');
        wrap.appendChild(img);
        host.appendChild(wrap);
    }

    global.ratibQrImageUrl = ratibQrImageUrl;
    global.ratibRenderQrImage = ratibRenderQrImage;
})(typeof window !== 'undefined' ? window : this);
