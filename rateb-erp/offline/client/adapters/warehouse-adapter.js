/**
 * RATEB Offline — Warehouse facade (no duplicate ReplayService).
 * Writes: delegates to InventoryOfflineAdapter (warehouse transfers).
 * Reads: warehouse_directory via MasterData adapter.
 */
(function (root) {
    'use strict';

    function flags() {
        if (root.RatebOffline && typeof root.RatebOffline.flags === 'function') {
            return root.RatebOffline.flags() || {};
        }
        return {};
    }

    function isWriteActive() {
        var f = flags();
        return !!(f['offline.enabled'] && f['offline.inventory.movements']);
    }

    function isReadActive() {
        var f = flags();
        return !!(f['offline.enabled'] && f['offline.master_data']);
    }

    function inventory() {
        return root.RatebOfflineInventoryAdapter || null;
    }

    function masterData() {
        return root.RatebOfflineMasterData || null;
    }

    root.RatebOfflineWarehouseAdapter = {
        isActive: function () {
            return isWriteActive() || isReadActive();
        },
        isWriteActive: isWriteActive,
        isReadActive: isReadActive,
        enqueueTransfer: function (payload, options) {
            var inv = inventory();
            if (!inv || typeof inv.enqueueWarehouseTransfer !== 'function') {
                return Promise.reject(new Error('warehouse_inventory_adapter_unavailable'));
            }
            return inv.enqueueWarehouseTransfer(payload || {}, options || {});
        },
        enqueueTransferApprove: function (payload, options) {
            var inv = inventory();
            if (!inv || typeof inv.enqueueTransferApprove !== 'function') {
                return Promise.reject(new Error('warehouse_inventory_adapter_unavailable'));
            }
            return inv.enqueueTransferApprove(payload || {}, options || {});
        },
        pullDirectory: function (options) {
            if (!isReadActive()) {
                return Promise.resolve({ items: [], stub: true, disabled: true });
            }
            var md = masterData();
            if (!md || typeof md.pullEntity !== 'function') {
                if (md && typeof md.sync === 'function') {
                    return md.sync(Object.assign({}, options || {}, { entities: ['warehouse_directory'] }));
                }
                return Promise.reject(new Error('master_data_adapter_unavailable'));
            }
            return md.pullEntity('warehouse_directory', options || {});
        },
        sync: function (options) {
            options = options || {};
            var inv = inventory();
            var write = (inv && typeof inv.sync === 'function' && isWriteActive())
                ? inv.sync(options)
                : Promise.resolve({ skipped: true, disabled: !isWriteActive() });
            return write.then(function (flushResult) {
                return root.RatebOfflineWarehouseAdapter.pullDirectory(options).then(function (directory) {
                    return { flush: flushResult, directory: directory };
                });
            });
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);
