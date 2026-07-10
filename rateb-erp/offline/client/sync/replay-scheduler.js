/**
 * RATEB Offline — Replay scheduler stub (Phase 2A).
 * Full module replay lands in later phases; here we only flush the client queue.
 */
(function (root) {
    'use strict';

    var timer = null;

    root.RatebOfflineReplayScheduler = {
        start: function (intervalMs) {
            if (typeof setInterval === 'undefined') {
                return;
            }
            this.stop();
            timer = setInterval(function () {
                var q = root.RatebOfflineQueue;
                var c = root.RatebOfflineConnectivity;
                if (q && q.isEnabled() && c && c.isOnline()) {
                    q.flush().catch(function () { /* ignore */ });
                }
            }, Math.max(5000, intervalMs || 15000));
        },
        stop: function () {
            if (timer) {
                clearInterval(timer);
                timer = null;
            }
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);
