/**
 * RATEB Offline — HR adapter stub (Phase 2A — not activated).
 */
(function (root) {
    'use strict';

    root.RatebOfflineHrAdapter = {
        isActive: function () { return false; },
        enqueueAttendanceBulk: function () {
            return Promise.reject(new Error('hr_offline_not_implemented'));
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);
