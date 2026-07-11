/**
 * RATEB Offline SDK bootstrap (Phase 11).
 * Expects sibling modules already loaded, or use public/assets/offline/rateb-offline.js bundle.
 */
(function (root) {
    'use strict';

    var booted = false;
    var flags = {
        'offline.enabled': false,
        'offline.pos.complete': true,
        'offline.inventory.movements': false,
        'offline.hr.attendance': false,
        'offline.procurement': false,
        'offline.read_cache': false,
        'offline.auth.unlock': false
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
            root.RatebOfflineEvents.emit('sdk:ready', {
                enabled: enabled,
                inventory: !!flags['offline.inventory.movements'],
                hr: !!flags['offline.hr.attendance'],
                procurement: !!flags['offline.procurement'],
                read_cache: !!flags['offline.read_cache'],
                auth_unlock: !!flags['offline.auth.unlock']
            });
        }
        return {
            enabled: enabled,
            inventory: !!flags['offline.inventory.movements'],
            hr: !!flags['offline.hr.attendance'],
            procurement: !!flags['offline.procurement'],
            read_cache: !!flags['offline.read_cache'],
            auth_unlock: !!flags['offline.auth.unlock'],
            version: '11.0.0'
        };
    }

    root.RatebOffline = {
        version: '11.0.0',
        init: init,
        isBooted: function () { return booted; },
        isEnabled: function () { return !!flags['offline.enabled']; },
        isInventoryEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.inventory.movements']);
        },
        isHrEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.hr.attendance']);
        },
        isProcurementEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.procurement']);
        },
        isReadCacheEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.read_cache']);
        },
        isAuthUnlockEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.read_cache'] && flags['offline.auth.unlock']);
        },
        flags: function () { return Object.assign({}, flags); },
        queue: function () { return root.RatebOfflineQueue || null; },
        transport: function () { return root.RatebOfflineTransport || null; },
        connectivity: function () { return root.RatebOfflineConnectivity || null; },
        pos: function () { return root.RatebOfflinePosAdapter || null; },
        inventory: function () { return root.RatebOfflineInventoryAdapter || null; },
        hr: function () { return root.RatebOfflineHrAdapter || null; },
        procurement: function () { return root.RatebOfflineProcurementAdapter || null; },
        shell: function () { return root.RatebOfflineShellAdapter || null; },
        auth: function () { return root.RatebOfflineAuthLock || null; },
        schema: function () { return root.RatebOfflineSchema || null; },
        deltaPull: function () { return root.RatebOfflineDeltaPull || null; }
    };
})(typeof window !== 'undefined' ? window : globalThis);
