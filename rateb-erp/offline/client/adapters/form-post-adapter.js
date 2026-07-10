/**
 * RATEB Offline — Form POST adapter stub (Phase 2A — not activated).
 */
(function (root) {
    'use strict';

    root.RatebOfflineFormPostAdapter = {
        isActive: function () { return false; },
        capture: function () {
            return Promise.reject(new Error('form_post_offline_not_implemented'));
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);
