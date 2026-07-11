/**
 * RATEB Offline SDK bootstrap (Phase 14.2).
 * Flag merge is additive — later bootstraps update flags without a second full boot.
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
        'offline.procurement.goods_receipt': false,
        'offline.read_cache': false,
        'offline.auth.unlock': false,
        'offline.rbac.cache': false,
        'offline.master_data': false,
        'offline.pilot.ops_pages': false
    };

    function mergeFlags(incoming) {
        if (!incoming || typeof incoming !== 'object') {
            return flags;
        }
        Object.keys(incoming).forEach(function (k) {
            flags[k] = !!incoming[k];
        });
        return flags;
    }

    function statusPayload() {
        return {
            enabled: !!flags['offline.enabled'],
            inventory: !!flags['offline.inventory.movements'],
            hr: !!flags['offline.hr.attendance'],
            procurement: !!flags['offline.procurement'],
            procurement_goods_receipt: !!flags['offline.procurement.goods_receipt'],
            read_cache: !!flags['offline.read_cache'],
            auth_unlock: !!flags['offline.auth.unlock'],
            rbac_cache: !!flags['offline.rbac.cache'],
            master_data: !!flags['offline.master_data'],
            pilot_ops_pages: !!flags['offline.pilot.ops_pages'],
            version: '14.2.0'
        };
    }

    function init(options) {
        options = options || {};
        if (options.flags && typeof options.flags === 'object') {
            mergeFlags(options.flags);
        }
        // Already booted: merge flags only (Phase 13.1 — no freeze).
        if (booted) {
            if (root.RatebOfflineEvents) {
                root.RatebOfflineEvents.emit('sdk:flags', statusPayload());
            }
            return statusPayload();
        }
        var enabled = !!flags['offline.enabled'];
        if (root.RatebOfflineQueue) {
            root.RatebOfflineQueue.configure({
                enabled: enabled,
                apiBase: options.apiBase || '',
                clientQueueMax: typeof options.clientQueueMax === 'number'
                    ? options.clientQueueMax
                    : 500
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
            root.RatebOfflineEvents.emit('sdk:ready', statusPayload());
        }
        return statusPayload();
    }

    root.RatebOffline = {
        version: '14.2.0',
        init: init,
        mergeFlags: mergeFlags,
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
        isProcurementGoodsReceiptEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.procurement']
                && flags['offline.procurement.goods_receipt']);
        },
        isReadCacheEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.read_cache']);
        },
        isAuthUnlockEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.read_cache'] && flags['offline.auth.unlock']);
        },
        isRbacCacheEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.read_cache']
                && flags['offline.auth.unlock']
                && flags['offline.rbac.cache']);
        },
        isMasterDataEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.master_data']);
        },
        isPilotOpsPagesEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.read_cache']
                && flags['offline.pilot.ops_pages']);
        },
        flags: function () { return Object.assign({}, flags); },
        queue: function () { return root.RatebOfflineQueue || null; },
        transport: function () { return root.RatebOfflineTransport || null; },
        connectivity: function () { return root.RatebOfflineConnectivity || null; },
        pos: function () { return root.RatebOfflinePosAdapter || null; },
        inventory: function () { return root.RatebOfflineInventoryAdapter || null; },
        hr: function () { return root.RatebOfflineHrAdapter || null; },
        procurement: function () { return root.RatebOfflineProcurementAdapter || null; },
        opsForms: function () { return root.RatebOfflineOpsForms || null; },
        shell: function () { return root.RatebOfflineShellAdapter || null; },
        auth: function () { return root.RatebOfflineAuthLock || null; },
        rbac: function () { return root.RatebOfflineRbacCache || null; },
        masterData: function () { return root.RatebOfflineMasterData || null; },
        schema: function () { return root.RatebOfflineSchema || null; },
        deltaPull: function () { return root.RatebOfflineDeltaPull || null; }
    };
})(typeof window !== 'undefined' ? window : globalThis);

