/**
 * RATEB ERP — topbar Online / Offline indicator only.
 * Browser stays on https://rateb.sa/rateb-erp/public/admin/ (same URL online and offline).
 * Offline UX is the PWA / service-worker shell on that origin — no redirect to 127.0.0.1.
 *
 * Do not trust the browser "online" event alone on cloud: with a Service Worker, Chrome often
 * fires "online" after cache hits while Wi‑Fi is still off. "متصل" requires a real probe.
 *
 * Local Branch Appliance (127.0.0.1): badge follows navigator.onLine (internet), not local PHP.
 *
 * FIX 2026-07-16 — Do NOT force "غير متصل" on boot when navigator.onLine is true.
 * PERF-P0.3-A had applied(false) for 8s+ before first probe; under idlePrefetch storms the
 * probe timed out and the badge stayed offline while Wi‑Fi worked and the page was live.
 */
(function () {
    'use strict';

    var verifyTimer = null;
    var verifying = false;
    var failStreak = 0;
    var FAIL_NEED = 2;

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

    function browserSaysOffline() {
        try {
            return typeof navigator !== 'undefined' && navigator.onLine === false;
        } catch (e) {
            return false;
        }
    }

    function notifySwCloudState(online) {
        try {
            if (!navigator.serviceWorker || !navigator.serviceWorker.controller) {
                return;
            }
            navigator.serviceWorker.controller.postMessage({
                type: online ? 'RATEB_CLOUD_ONLINE' : 'RATEB_CLOUD_OFFLINE'
            });
        } catch (eSw) { /* ignore */ }
    }

    function apply(online) {
        var node = el();
        if (!node) {
            return;
        }
        var on = !!online;
        if (on && browserSaysOffline()) {
            on = false;
        }
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
        notifySwCloudState(on);
        try {
            document.dispatchEvent(new CustomEvent('rateb-connection-badge', {
                detail: { online: on }
            }));
        } catch (eEmit) { /* ignore */ }
    }

    function probeUrlFallback() {
        try {
            var path = String(window.location.pathname || '');
            var m = path.match(/^(.*\/public\/)/i);
            if (m && m[1]) {
                return m[1] + 'connectivity-probe.json';
            }
            if (/^\/(admin|login|offline-shell\.html|pos)(\/|$)/i.test(path)) {
                return '/connectivity-probe.json';
            }
        } catch (e) { /* ignore */ }
        return '/rateb-erp/public/connectivity-probe.json';
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
        if (browserSaysOffline()) {
            return Promise.resolve(false);
        }
        var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
        // Keep probe short — 8s hung probes starved every offline/soft-offline click.
        var timer = setTimeout(function () {
            if (ctrl) {
                try { ctrl.abort(); } catch (e) { /* ignore */ }
            }
        }, 1200);
        return fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json', 'X-Rateb-Connectivity': '1' },
            signal: ctrl ? ctrl.signal : undefined
        }).then(function (res) {
            if (browserSaysOffline()) {
                return false;
            }
            try {
                if (res && res.headers && String(res.headers.get('X-Rateb-Offline') || '') === '1') {
                    return false;
                }
            } catch (eHdr) { /* ignore */ }
            // Any HTTP response from origin means the network path works (status may 404).
            return !!res;
        }).catch(function () {
            return false;
        }).finally(function () {
            clearTimeout(timer);
        });
    }

    function markOfflineConfirmed() {
        apply(false);
        var c1 = window.RatebOfflineConnectivity;
        if (c1 && typeof c1.setOnline === 'function') {
            c1.setOnline(false);
        }
    }

    function verifyRealOnline() {
        if (browserSaysOffline()) {
            failStreak = FAIL_NEED;
            markOfflineConfirmed();
            return;
        }
        if (verifying) {
            return;
        }
        verifying = true;
        networkProbe().then(function (ok) {
            if (ok) {
                failStreak = 0;
                apply(true);
                return;
            }
            // Ambiguous: page may be live while probe times out under prefetch congestion.
            failStreak += 1;
            if (failStreak >= FAIL_NEED) {
                markOfflineConfirmed();
            } else if (!browserSaysOffline()) {
                // Keep current optimistic online; retry soon.
                scheduleVerify(2000);
            }
        }).finally(function () {
            verifying = false;
        });
    }

    function scheduleVerify(delayMs) {
        if (browserSaysOffline()) {
            markOfflineConfirmed();
            return;
        }
        if (verifyTimer) {
            clearTimeout(verifyTimer);
        }
        verifyTimer = setTimeout(function () {
            verifyTimer = null;
            verifyRealOnline();
        }, delayMs || 0);
    }

    /** First probe soon after paint — not 8s later (false "غير متصل" while net works). */
    function scheduleVerifyAfterPaint() {
        var run = function () {
            scheduleVerify(0);
        };
        var start = function () {
            setTimeout(function () {
                try {
                    if (typeof window.requestIdleCallback === 'function') {
                        window.requestIdleCallback(function () { run(); }, { timeout: 1200 });
                        return;
                    }
                } catch (eIdle) { /* ignore */ }
                run();
            }, 600);
        };
        if (document.readyState === 'complete') {
            start();
        } else {
            window.addEventListener('load', start, { once: true });
        }
    }

    function bootLocalAppliance() {
        function syncNav() {
            apply(!browserSaysOffline());
        }
        syncNav();
        window.addEventListener('online', syncNav);
        window.addEventListener('offline', function () { apply(false); });
        document.addEventListener('rateb-offline-connectivity', function (ev) {
            var detail = ev && ev.detail ? ev.detail : null;
            if (browserSaysOffline()) {
                apply(false);
                return;
            }
            if (detail && typeof detail.online === 'boolean') {
                apply(detail.online && !browserSaysOffline());
            }
        });
    }

    function onConnectivityEvent(online) {
        if (browserSaysOffline()) {
            failStreak = FAIL_NEED;
            apply(false);
            return;
        }
        if (online) {
            failStreak = 0;
            scheduleVerify(0);
            return;
        }
        // Soft "offline" from SDK while browser still has net → verify, do not flip badge yet.
        failStreak += 1;
        if (failStreak >= FAIL_NEED) {
            apply(false);
            return;
        }
        scheduleVerify(500);
    }

    function bootCloud() {
        if (browserSaysOffline()) {
            apply(false);
        } else {
            // Optimistic online (matches HTML default is-online). Probe confirms shortly.
            apply(true);
            scheduleVerifyAfterPaint();
        }

        window.addEventListener('online', function () {
            failStreak = 0;
            apply(true);
            scheduleVerify(50);
        });
        window.addEventListener('offline', function () {
            failStreak = FAIL_NEED;
            apply(false);
            var c = window.RatebOfflineConnectivity;
            if (c && typeof c.setOnline === 'function') {
                c.setOnline(false);
            }
        });

        document.addEventListener('rateb-offline-connectivity', function (ev) {
            var detail = ev && ev.detail ? ev.detail : null;
            if (!detail || typeof detail.online !== 'boolean') {
                return;
            }
            onConnectivityEvent(detail.online);
        });

        var conn = window.RatebOfflineConnectivity;
        if (conn && typeof conn.subscribe === 'function') {
            conn.subscribe(function (online) {
                // Initial subscribe often emits false before first probe — ignore if browser is online.
                onConnectivityEvent(!!online);
            });
        }

        document.addEventListener('click', function () {
            if (browserSaysOffline()) {
                apply(false);
                var c2 = window.RatebOfflineConnectivity;
                if (c2 && typeof c2.setOnline === 'function') {
                    c2.setOnline(false);
                }
            }
        }, true);

        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible' && !browserSaysOffline()) {
                scheduleVerify(100);
            }
        });

        // Keep SW soft-offline latch alive while badge is offline — F5 unloads JS;
        // without a living latch the next document wait hangs ~8s on a dead network.
        try {
            setInterval(function () {
                var node = el();
                if (node && node.classList.contains('is-offline')) {
                    notifySwCloudState(false);
                }
            }, 3000);
        } catch (eHb) { /* ignore */ }
        try {
            window.addEventListener('pagehide', function () {
                var node = el();
                if ((node && node.classList.contains('is-offline')) || browserSaysOffline()) {
                    notifySwCloudState(false);
                }
            });
        } catch (ePh) { /* ignore */ }
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
