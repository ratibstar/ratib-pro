/*! RATEB Offline module offline-adapter-inventory.js (Phase OA — sourced from offline/client). */

/* ---- inventory-adapter.js ---- */
/**
 * RATEB Offline — Inventory adapter (Phase 3 / Tier 1).
 * Queues stock movements, stock counts, and warehouse transfers via enterprise offline queue.
 * Activated only when offline.enabled + offline.inventory.movements are true.
 */
(function (root) {
    'use strict';

    function flags() {
        if (root.RatebOffline && typeof root.RatebOffline.flags === 'function') {
            return root.RatebOffline.flags() || {};
        }
        return {};
    }

    function isActive() {
        var f = flags();
        return !!(f['offline.enabled'] && f['offline.inventory.movements']);
    }

    function makeClientId(prefix) {
        var rand = Math.random().toString(36).slice(2, 10);
        return String(prefix || 'inv') + '-' + Date.now() + '-' + rand;
    }

    function enqueue(action, payload, options) {
        options = options || {};
        if (!isActive()) {
            return Promise.reject(new Error('inventory_offline_disabled'));
        }
        var q = root.RatebOfflineQueue;
        if (!q || typeof q.enqueue !== 'function') {
            return Promise.reject(new Error('offline_queue_unavailable'));
        }
        var clientId = options.client_id || options.idempotency_key || makeClientId(action);
        return q.enqueue({
            client_id: clientId,
            idempotency_key: clientId,
            module: 'inventory',
            action: action,
            payload: payload || {},
            version: options.version || 1,
            occurred_at: options.occurred_at || new Date().toISOString()
        });
    }

    function pullCatalog(options) {
        options = options || {};
        if (!isActive()) {
            return Promise.resolve({ items: [], stub: true, disabled: true });
        }
        var pull = root.RatebOfflineDeltaPull;
        if (!pull || typeof pull.pull !== 'function') {
            return Promise.reject(new Error('delta_pull_unavailable'));
        }
        return pull.pull('inventory_catalog', options).then(function (res) {
            var delta = (res && res.delta) ? res.delta : res;
            if (delta && Array.isArray(delta.items) && root.RatebOfflineSchema) {
                return root.RatebOfflineSchema.withStore(
                    root.RatebOfflineSchema.STORES.CATALOG_INDEX,
                    'readwrite',
                    function (store) {
                        delta.items.forEach(function (item) {
                            if (item && item.id) {
                                var cfg = root.__RATEB_ERP_SHELL_OFFLINE__ || root.__RATEB_ERP_MASTER_DATA__ || {};
                                var cid = parseInt(item.company_id || cfg.company_id, 10) || 0;
                                var bid = parseInt(
                                    item.branch_id != null ? item.branch_id : (cfg.branch_id || 0),
                                    10
                                ) || 0;
                                var id = cid + ':' + bid + ':inv:' + item.id;
                                try { store.delete('inv:' + item.id); } catch (e) { /* legacy */ }
                                store.put({
                                    id: id,
                                    entity: 'inventory_catalog',
                                    company_id: cid,
                                    branch_id: bid,
                                    data: item,
                                    updated_at: item.updated_at || null,
                                    synced_at: Date.now()
                                });
                            }
                        });
                        return delta;
                    }
                ).then(function () { return delta; }).catch(function () { return delta; });
            }
            return delta || { items: [] };
        });
    }

    root.RatebOfflineInventoryAdapter = {
        isActive: isActive,
        enqueueMovement: function (payload, options) {
            return enqueue('stock_movement.create', payload || {}, options);
        },
        enqueueStockCount: function (payload, options) {
            return enqueue('stock_count.create', payload || {}, options);
        },
        enqueueWarehouseTransfer: function (payload, options) {
            return enqueue('warehouse_transfer.create', payload || {}, options);
        },
        enqueueTransferApprove: function (payload, options) {
            return enqueue('warehouse_transfer.approve', payload || {}, options);
        },
        pullCatalog: pullCatalog,
        sync: function (options) {
            options = options || {};
            if (!isActive()) {
                return Promise.resolve({ skipped: true, disabled: true });
            }
            var q = root.RatebOfflineQueue;
            var flush = (q && typeof q.flush === 'function') ? q.flush() : Promise.resolve({ skipped: true });
            return flush.then(function (flushResult) {
                return pullCatalog(options).then(function (catalog) {
                    return { flush: flushResult, catalog: catalog };
                });
            });
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);

