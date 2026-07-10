/**
 * RATEB Offline SDK bootstrap (Phase 2A).
 * Expects sibling modules already loaded, or use public/assets/offline/rateb-offline.js bundle.
 */
(function (root) {
    'use strict';

    var booted = false;
    var flags = {
        'offline.enabled': false,
        'offline.pos.complete': true
    };

    function init(options) {
        options = options || {};
        if (options.flags && typeof options.flags === 'object') {
            Object.keys(options.flags).forEach(function (k) {
                flags[k] = !!options.flags[k];
            });
        }
        var enabled = !!flags['offline.enabled'];
        if (root.RatebOfflineQueue) {
            root.RatebOfflineQueue.configure({
                enabled: enabled,
                apiBase: options.apiBase || ''
            });
        }
        if (root.RatebOfflineTransport) {
            root.RatebOfflineTransport.configure({ enabled: enabled });
        }
        if (root.RatebOfflineConnectivity) {
            root.RatebOfflineConnectivity.configure({
                probeUrl: options.probeUrl || (options.apiBase ? String(options.apiBase).replace(/\/$/, '') + '/status' : null)
            });
            if (enabled && options.startConnectivity !== false) {
                root.RatebOfflineConnectivity.start();
            }
        }
        if (enabled && root.RatebOfflineReplayScheduler && options.startScheduler !== false) {
            root.RatebOfflineReplayScheduler.start(options.schedulerIntervalMs || 15000);
        }
        booted = true;
        if (root.RatebOfflineEvents) {
            root.RatebOfflineEvents.emit('sdk:ready', { enabled: enabled });
        }
        return {
            enabled: enabled,
            version: '2A.1.0'
        };
    }

    root.RatebOffline = {
        version: '2A.1.0',
        init: init,
        isBooted: function () { return booted; },
        isEnabled: function () { return !!flags['offline.enabled']; },
        flags: function () { return Object.assign({}, flags); },
        queue: function () { return root.RatebOfflineQueue || null; },
        transport: function () { return root.RatebOfflineTransport || null; },
        connectivity: function () { return root.RatebOfflineConnectivity || null; },
        pos: function () { return root.RatebOfflinePosAdapter || null; },
        schema: function () { return root.RatebOfflineSchema || null; }
    };
})(typeof window !== 'undefined' ? window : globalThis);
