/*! RATEB Offline module offline-network.js (Phase OA — sourced from offline/client). */

/* ---- connectivity.js ---- */
/**
 * RATEB Offline — Connectivity Manager (Phase 2A).
 */
(function (root) {
    'use strict';

    var listeners = [];
    // Start false until probe; do not emit false to UI on timeout while navigator.onLine
    // (prefetch storms abort the probe and falsely show «غير متصل» while Wi‑Fi works).
    var online = false;
    var probeTimer = null;
    var probing = false;
    var probeUrl = null;
    var failStreak = 0;
    var intervals = { online: 30000, offline: 60000 };
    // Short probe — long hangs starved every soft-offline click.
    var timeoutMs = 1200;

    function emit() {
        listeners.forEach(function (fn) {
            try { fn(online); } catch (e) { /* ignore */ }
        });
        try {
            if (typeof document !== 'undefined') {
                document.dispatchEvent(new CustomEvent('rateb-offline-connectivity', {
                    detail: { online: online }
                }));
            }
        } catch (e2) { /* ignore */ }
    }

    function setOnline(next) {
        next = !!next;
        try {
            if (next && typeof navigator !== 'undefined' && navigator.onLine === false) {
                next = false;
            }
        } catch (eNav) { /* ignore */ }
        if (online === next) {
            return;
        }
        online = next;
        emit();
        scheduleProbeLoop();
        if (online && root.RatebOfflineQueue && typeof root.RatebOfflineQueue.flush === 'function') {
            root.RatebOfflineQueue.flush().catch(function () { /* retry later */ });
        }
    }

    function probe() {
        if (probing) {
            return Promise.resolve(online);
        }
        if (typeof navigator !== 'undefined' && navigator.onLine === false) {
            setOnline(false);
            return Promise.resolve(false);
        }
        if (!probeUrl) {
            return Promise.resolve(online);
        }
        probing = true;
        var timedOut = false;
        var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var timer = setTimeout(function () {
            timedOut = true;
            if (ctrl) {
                try { ctrl.abort(); } catch (e) { /* ignore */ }
            }
        }, timeoutMs);
        // Bust caches; SW must not treat this as a navigable page hit.
        var url = String(probeUrl);
        url += (url.indexOf('?') >= 0 ? '&' : '?') + '_rateb_probe=' + Date.now();
        return fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json', 'X-Rateb-Connectivity': '1' },
            signal: ctrl ? ctrl.signal : undefined
        }).then(function (res) {
            // Local PHP status must not mark "cloud online" when Wi‑Fi is off.
            try {
                var h = String((self.location && self.location.hostname) || '');
                if ((h === '127.0.0.1' || h === 'localhost' || h === '[::1]')
                    && typeof navigator !== 'undefined' && navigator.onLine === false) {
                    failStreak = 2;
                    setOnline(false);
                    return false;
                }
            } catch (eLocal) { /* ignore */ }
            try {
                if (typeof navigator !== 'undefined' && navigator.onLine === false) {
                    failStreak = 2;
                    setOnline(false);
                    return false;
                }
            } catch (eOff) { /* ignore */ }
            if (res && res.headers && String(res.headers.get('X-Rateb-Connectivity-Echo') || '') === '1') {
                failStreak = 0;
                setOnline(true);
                return true;
            }
            // Any HTTP response (incl. 404 on status) proves the origin is reachable.
            // Reject SW/cache ghost responses: probes must not be served from Cache API.
            try {
                if (res && res.headers && String(res.headers.get('X-Rateb-Offline') || '') === '1') {
                    failStreak = 2;
                    setOnline(false);
                    return false;
                }
            } catch (eHdr) { /* ignore */ }
            if (res) {
                failStreak = 0;
                setOnline(true);
                return true;
            }
            failStreak += 1;
            if (failStreak >= 2) {
                setOnline(false);
            }
            return false;
        }).catch(function (err) {
            var name = String((err && err.name) || '');
            var msg = String((err && err.message) || err || '');
            // Timeout/abort under load is ambiguous — do not flip to offline while browser says online.
            if (timedOut || name === 'AbortError' || /abort/i.test(msg)) {
                if (typeof navigator !== 'undefined' && navigator.onLine !== false) {
                    return online;
                }
            }
            failStreak += 1;
            if (failStreak >= 2 || (typeof navigator !== 'undefined' && navigator.onLine === false)) {
                setOnline(false);
                return false;
            }
            return online;
        }).finally(function () {
            clearTimeout(timer);
            probing = false;
        });
    }

    function scheduleProbeLoop() {
        if (typeof setInterval === 'undefined') {
            return;
        }
        if (probeTimer) {
            clearInterval(probeTimer);
            probeTimer = null;
        }
        var every = online ? intervals.online : intervals.offline;
        probeTimer = setInterval(function () {
            if (typeof navigator !== 'undefined' && navigator.onLine === false) {
                setOnline(false);
                return;
            }
            probe();
        }, every);
    }

    root.RatebOfflineConnectivity = {
        isOnline: function () { return online; },
        setOnline: setOnline,
        probe: probe,
        setProbeUrl: function (url) {
            probeUrl = url ? String(url) : null;
        },
        configure: function (opts) {
            opts = opts || {};
            if (opts.probeUrl) {
                probeUrl = String(opts.probeUrl);
            }
            if (opts.onlineIntervalMs) {
                intervals.online = opts.onlineIntervalMs;
            }
            if (opts.offlineIntervalMs) {
                intervals.offline = opts.offlineIntervalMs;
            }
            if (opts.timeoutMs) {
                timeoutMs = opts.timeoutMs;
            }
        },
        subscribe: function (fn) {
            if (typeof fn !== 'function') {
                return function () {};
            }
            listeners.push(fn);
            // Do not push initial false — that forced the topbar to «غير متصل» before any probe.
            return function () {
                listeners = listeners.filter(function (x) { return x !== fn; });
            };
        },
        start: function () {
            if (typeof window !== 'undefined') {
                // Do NOT optimistic-flip to online: Chrome + Service Worker often fires
                // "online" after cache navigation while Wi‑Fi is still off.
                window.addEventListener('online', function () {
                    failStreak = 0;
                    probe();
                });
                window.addEventListener('offline', function () {
                    failStreak = 2;
                    setOnline(false);
                });
            }
            scheduleProbeLoop();
            return probe();
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);

