/**
 * RATEB Offline — Connectivity Manager (Phase 2A).
 */
(function (root) {
    'use strict';

    var listeners = [];
    var online = typeof navigator !== 'undefined' ? navigator.onLine !== false : true;
    var probeTimer = null;
    var probing = false;
    var probeUrl = null;
    var intervals = { online: 12000, offline: 20000 };
    var timeoutMs = 3500;

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
        var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var timer = setTimeout(function () {
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
                    setOnline(false);
                    return false;
                }
            } catch (eLocal) { /* ignore */ }
            if (res && res.headers && String(res.headers.get('X-Rateb-Connectivity-Echo') || '') === '1') {
                setOnline(true);
                return true;
            }
            if (res && (res.ok || res.status === 401 || res.status === 403 || res.status === 419)) {
                setOnline(true);
                return true;
            }
            // Soft-fail on cloud: do not force offline when Wi‑Fi is up (status API may 404).
            if (typeof navigator !== 'undefined' && navigator.onLine !== false) {
                return online;
            }
            setOnline(false);
            return false;
        }).catch(function () {
            setOnline(false);
            return false;
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
            try { fn(online); } catch (e) { /* ignore */ }
            return function () {
                listeners = listeners.filter(function (x) { return x !== fn; });
            };
        },
        start: function () {
            if (typeof window !== 'undefined') {
                // Do NOT optimistic-flip to online: Chrome + Service Worker often fires
                // "online" after cache navigation while Wi‑Fi is still off.
                window.addEventListener('online', function () {
                    probe();
                });
                window.addEventListener('offline', function () { setOnline(false); });
            }
            scheduleProbeLoop();
            return probe();
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);
