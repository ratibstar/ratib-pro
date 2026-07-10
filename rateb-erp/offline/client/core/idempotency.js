/**
 * RATEB Offline — Idempotency helpers (Phase 2A).
 */
(function (root) {
    'use strict';

    function randomId() {
        if (root.crypto && typeof root.crypto.randomUUID === 'function') {
            return root.crypto.randomUUID();
        }
        return 'local-' + Date.now() + '-' + Math.random().toString(36).slice(2, 10);
    }

    function buildKey(parts) {
        var raw = (parts || []).map(function (p) {
            return String(p == null ? '' : p);
        }).join('|');
        var hash = 0;
        for (var i = 0; i < raw.length; i += 1) {
            hash = ((hash << 5) - hash) + raw.charCodeAt(i);
            hash |= 0;
        }
        var hex = (hash >>> 0).toString(16);
        return ('idem-' + hex + '-' + raw.replace(/[^a-zA-Z0-9_-]/g, '').slice(0, 40)).slice(0, 64);
    }

    root.RatebOfflineIdempotency = {
        randomId: randomId,
        buildKey: buildKey
    };
})(typeof window !== 'undefined' ? window : globalThis);
