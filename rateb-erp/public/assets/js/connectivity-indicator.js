/**
 * RATEB ERP — topbar Online / Offline indicator only.
 * Browser stays on https://rateb.sa/rateb-erp/public/admin/ (same URL online and offline).
 * Offline UX is the PWA / service-worker shell on that origin — no redirect to 127.0.0.1.
 *
 * Do not trust the browser "online" event alone on cloud: with a Service Worker, Chrome often
 * fires "online" after cache hits while Wi‑Fi is still off. "متصل" requires a real probe.
 *
 * Local Branch Appliance (127.0.0.1): badge follows navigator.onLine (internet), not local PHP.
 */
(function () {
    'use strict';

    var verifyTimer = null;
    var verifying = false;

    function el() {
        return document.querySelector('[data-rateb-connection-status]')
            || document.getElementById('rateb-connection-indicator');
    }

    function isLocalAppliance() {
        try {
            var h = String(window.location.hostname || '');
            return h === '127.0.0.1' || h === 'localhost' || h === '[::1]';
        } catch (e) {
            return false;
        }
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

    function probeUrlFallback() {
        try {
            var path = String(window.location.pathname || '');
            var m = path.match(/^(.*\/public\/)/i);
            if (m && m[1]) {
                return m[1] + 'api/v1/offline/status';
            }
            if (/^\/(admin|login|offline-shell\.html|pos)(\/|$)/i.test(path)) {
                return '/api/v1/offline/status';
            }
        } catch (e) { /* ignore */ }
        return '/rateb-erp/public/api/v1/offline/status';
    }

    function networkProbe() {
        var conn = window.RatebOfflineConnectivity;
        if (conn && typeof conn.probe === 'function') {
            return conn.probe().then(function (ok) {
                return !!ok;
            });
        }
        var base = probeUrlFallback();
        var url = base + (base.indexOf('?') >= 0 ? '&' : '?') + '_rateb_probe=' + Date.now();
        var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var timer = setTimeout(function () {
            if (ctrl) {
                try { ctrl.abort(); } catch (e) { /* ignore */ }
            }
        }, 3500);
        return fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json', 'X-Rateb-Connectivity': '1' },
            signal: ctrl ? ctrl.signal : undefined
        }).then(function (res) {
            return !!(res && (res.ok || res.status === 401 || res.status === 403 || res.status === 419));
        }).catch(function () {
            return false;
        }).finally(function () {
            clearTimeout(timer);
        });
    }

    function verifyRealOnline() {
        if (typeof navigator !== 'undefined' && navigator.onLine === false) {
            apply(false);
            var c0 = window.RatebOfflineConnectivity;
            if (c0 && typeof c0.setOnline === 'function') {
                c0.setOnline(false);
            }
            return;
        }
        if (verifying) {
            return;
        }
        verifying = true;
        networkProbe().then(function (ok) {
            if (ok) {
                apply(true);
                return;
            }
            // Soft-fail: a live admin page must not flip to "غير متصل" just because
            // /api/v1/offline/status is missing or slow on production.
            if (typeof navigator !== 'undefined' && navigator.onLine !== false) {
                apply(true);
                return;
            }
            apply(false);
            var c1 = window.RatebOfflineConnectivity;
            if (c1 && typeof c1.setOnline === 'function' && c1.isOnline && c1.isOnline() !== false) {
                c1.setOnline(false);
            }
        }).finally(function () {
            verifying = false;
        });
    }

    function scheduleVerify(delayMs) {
        if (verifyTimer) {
            clearTimeout(verifyTimer);
        }
        verifyTimer = setTimeout(function () {
            verifyTimer = null;
            verifyRealOnline();
        }, delayMs || 0);
    }

    function bootLocalAppliance() {
        // Local PHP works without internet; badge = Wi‑Fi / internet only.
        function syncNav() {
            apply(typeof navigator === 'undefined' || navigator.onLine !== false);
        }
        syncNav();
        window.addEventListener('online', syncNav);
        window.addEventListener('offline', function () { apply(false); });
        document.addEventListener('rateb-offline-connectivity', function (ev) {
            var detail = ev && ev.detail ? ev.detail : null;
            // Prefer real navigator when offline — ignore false "online" from local probe.
            if (typeof navigator !== 'undefined' && navigator.onLine === false) {
                apply(false);
                return;
            }
            if (detail && typeof detail.online === 'boolean') {
                apply(detail.online && navigator.onLine !== false);
            }
        });
    }

    function bootCloud() {
        if (typeof navigator !== 'undefined' && navigator.onLine === false) {
            apply(false);
        } else {
            apply(false);
            scheduleVerify(0);
        }

        window.addEventListener('online', function () {
            scheduleVerify(50);
        });
        window.addEventListener('offline', function () {
            apply(false);
            var c = window.RatebOfflineConnectivity;
            if (c && typeof c.setOnline === 'function') {
                c.setOnline(false);
            }
        });

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

        document.addEventListener('click', function () {
            if (typeof navigator !== 'undefined' && navigator.onLine === false) {
                apply(false);
                var c2 = window.RatebOfflineConnectivity;
                if (c2 && typeof c2.setOnline === 'function') {
                    c2.setOnline(false);
                }
            }
        }, true);
    }

    function boot() {
        if (isLocalAppliance()) {
            bootLocalAppliance();
            return;
        }
        bootCloud();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();
