/**
 * High-contrast QR images (same engine as login pairing QR — api.qrserver.com).
 */
(function (global) {
    'use strict';

    /**
     * @param {string} data
     * @param {number} size
     * @param {{ecc?:string, margin?:number}} [opts] error-correction level (L/M/Q/H) and
     *        quiet-zone margin. Lower ecc + smaller margin = fewer/larger modules = easier
     *        to scan dense payloads (e.g. workforce badge token) off a screen.
     */
    function ratibQrImageUrl(data, size, opts) {
        var px = Math.max(120, Math.min(512, parseInt(size, 10) || 280));
        var o = opts || {};
        var ecc = /^[LMQH]$/.test(String(o.ecc || '').toUpperCase()) ? String(o.ecc).toUpperCase() : 'H';
        var margin = (o.margin === 0 || o.margin) ? Math.max(0, Math.min(50, parseInt(o.margin, 10) || 0)) : 18;
        return 'https://api.qrserver.com/v1/create-qr-code/?'
            + 'size=' + px + 'x' + px
            + '&margin=' + margin
            + '&ecc=' + ecc
            + '&color=000000'
            + '&bgcolor=ffffff'
            + '&format=png'
            + '&data=' + encodeURIComponent(String(data || ''));
    }

    /**
     * @param {HTMLElement} host
     * @param {string} data
     * @param {number} size
     * @param {{ecc?:string, margin?:number}} [opts]
     */
    function ratibRenderQrImage(host, data, size, opts) {
        if (!host || !data) {
            return;
        }
        var px = Math.max(120, Math.min(512, parseInt(size, 10) || 280));
        host.innerHTML = '';
        var wrap = document.createElement('div');
        wrap.className = 'ratib-qr-image-wrap';
        var img = document.createElement('img');
        img.className = 'ratib-qr-image';
        img.src = ratibQrImageUrl(data, px, opts);
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
