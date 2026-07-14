/**
 * RATEB Offline — Replay scheduler (flush + Background Sync registration).
 * Server-side domain replay remains OfflineReplayEngine; this only drains the client queue.
 */
(function (root) {
    'use strict';

    var timer = null;
    var SYNC_TAG = 'rateb-offline-flush';
    var messageBound = false;

    function requestBackgroundSync() {
        if (!root.navigator || !root.navigator.serviceWorker) {
            return Promise.resolve({ skipped: true, reason: 'no_sw' });
        }
        return root.navigator.serviceWorker.ready.then(function (reg) {
            if (reg && reg.sync && typeof reg.sync.register === 'function') {
                return reg.sync.register(SYNC_TAG).then(function () {
                    return { ok: true, tag: SYNC_TAG };
                }).catch(function (err) {
                    return { ok: false, error: String(err && err.message ? err.message : err) };
                });
            }
            if (reg && reg.active) {
                try {
                    reg.active.postMessage({ type: 'REGISTER_BACKGROUND_SYNC', tag: SYNC_TAG });
                } catch (ePost) { /* ignore */ }
            }
            return { skipped: true, reason: 'sync_manager_unavailable' };
        }).catch(function () {
            return { skipped: true, reason: 'sw_ready_failed' };
        });
    }

    function flushNow() {
        var q = root.RatebOfflineQueue;
        var c = root.RatebOfflineConnectivity;
        if (!q || typeof q.flush !== 'function' || !q.isEnabled()) {
            return Promise.resolve({ skipped: true });
        }
        if (c && typeof c.isOnline === 'function' && !c.isOnline()) {
            return requestBackgroundSync();
        }
        return q.flush().then(function (result) {
            var depth = (result && typeof result.queueDepth === 'number') ? result.queueDepth : null;
            if (depth === null && typeof q.depth === 'function') {
                return q.depth().then(function (d) {
                    if (d > 0) {
                        return requestBackgroundSync().then(function () { return result; });
                    }
                    return result;
                });
            }
            if (depth > 0 || (result && result.offline)) {
                return requestBackgroundSync().then(function () { return result; });
            }
            return result;
        }).catch(function () {
            return requestBackgroundSync();
        });
    }

    function bindFlushMessages() {
        if (messageBound || !root.navigator || !root.navigator.serviceWorker) {
            return;
        }
        messageBound = true;
        root.navigator.serviceWorker.addEventListener('message', function (event) {
            var data = (event && event.data) || {};
            if (data.type === 'RATEB_OFFLINE_FLUSH') {
                flushNow();
            }
        });
    }

    root.RatebOfflineReplayScheduler = {
        start: function (intervalMs) {
            if (typeof setInterval === 'undefined') {
                return;
            }
            this.stop();
            bindFlushMessages();
            timer = setInterval(function () {
                flushNow();
            }, Math.max(5000, intervalMs || 15000));
            flushNow();
        },
        stop: function () {
            if (timer) {
                clearInterval(timer);
                timer = null;
            }
        },
        flushNow: flushNow,
        requestBackgroundSync: requestBackgroundSync
    };
})(typeof window !== 'undefined' ? window : globalThis);
