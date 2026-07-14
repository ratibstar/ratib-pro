/*! RATEB Offline module offline-pos.js (Phase OA — sourced from offline/client). */

/* ---- pos-adapter.js ---- */
/**
 * RATEB Offline — POS adapter (Phase 2A).
 * Delegates to existing RatebPosOffline when present; does not modify POS logic.
 */
(function (root) {
    'use strict';

    root.RatebOfflinePosAdapter = {
        isAvailable: function () {
            return !!(root.RatebPosOffline && typeof root.RatebPosOffline.push === 'function');
        },
        pushCheckout: function (payload, options) {
            if (!this.isAvailable()) {
                return Promise.reject(new Error('pos_offline_unavailable'));
            }
            return root.RatebPosOffline.push({
                action: 'checkout',
                payload: payload || {},
                version: (options && options.version) || 1
            }, options || {});
        },
        sync: function (options) {
            if (!this.isAvailable() || typeof root.RatebPosOffline.sync !== 'function') {
                return Promise.resolve({ skipped: true });
            }
            return root.RatebPosOffline.sync(options || {});
        },
        queueDepth: function () {
            if (!this.isAvailable()) {
                return Promise.resolve(0);
            }
            return Promise.resolve(root.RatebPosOffline.queueDepth || 0);
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);

