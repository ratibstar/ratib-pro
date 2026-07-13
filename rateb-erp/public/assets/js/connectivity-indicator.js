/**
 * RATEB ERP — topbar Online / Offline indicator only.
 * Browser stays on https://rateb.sa/rateb-erp/public/admin/ (same URL online and offline).
 * Offline UX is the PWA / service-worker shell on that origin — no redirect to 127.0.0.1.
 */
(function () {
    'use strict';

    function el() {
        return document.querySelector('[data-rateb-connection-status]')
            || document.getElementById('rateb-connection-indicator');
    }

    function apply(online) {
        var node = el();
        if (!node) {
            return;
        }
        var on = !!online;
        var labelOn = node.getAttribute('data-label-online') || 'Online';
        var labelOff = node.getAttribute('data-label-offline') || 'Offline';
        var label = on ? labelOn : labelOff;
        node.classList.toggle('is-online', on);
        node.classList.toggle('is-offline', !on);
        node.setAttribute('title', label);
        node.setAttribute('aria-label', label);
        var text = node.querySelector('.rateb-connection-indicator__label');
        if (text) {
            text.textContent = label;
        }
    }

    function fromNavigator() {
        return typeof navigator === 'undefined' || navigator.onLine !== false;
    }

    function boot() {
        apply(fromNavigator());

        window.addEventListener('online', function () { apply(true); });
        window.addEventListener('offline', function () { apply(false); });

        document.addEventListener('rateb-offline-connectivity', function (ev) {
            var detail = ev && ev.detail ? ev.detail : null;
            if (detail && typeof detail.online === 'boolean') {
                apply(detail.online);
            }
        });

        var conn = window.RatebOfflineConnectivity;
        if (conn && typeof conn.subscribe === 'function') {
            conn.subscribe(function (online) { apply(online); });
        } else if (conn && typeof conn.isOnline === 'function') {
            apply(conn.isOnline());
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();
