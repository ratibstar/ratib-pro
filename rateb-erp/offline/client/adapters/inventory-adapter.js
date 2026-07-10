/**
 * RATEB Offline — Inventory adapter stub (Phase 2A — not activated).
 */
(function (root) {
    'use strict';

    root.RatebOfflineInventoryAdapter = {
        isActive: function () { return false; },
        enqueueMovement: function () {
            return Promise.reject(new Error('inventory_offline_not_implemented'));
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);
