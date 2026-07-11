/**
 * RATEB Offline — Procurement adapter (Phase 5 / Tier 1).
 * Queues PR / RFQ / PO drafts via enterprise offline queue.
 * Activated only when offline.enabled + offline.procurement are true.
 * Does NOT enqueue approvals, payments, or accounting posting.
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
        return !!(f['offline.enabled'] && f['offline.procurement']);
    }

    function makeClientId(prefix) {
        var rand = Math.random().toString(36).slice(2, 10);
        return String(prefix || 'proc') + '-' + Date.now() + '-' + rand;
    }

    function enqueue(action, payload, options) {
        options = options || {};
        if (!isActive()) {
            return Promise.reject(new Error('procurement_offline_disabled'));
        }
        var q = root.RatebOfflineQueue;
        if (!q || typeof q.enqueue !== 'function') {
            return Promise.reject(new Error('offline_queue_unavailable'));
        }
        var clientId = options.client_id || options.idempotency_key || makeClientId(action);
        return q.enqueue({
            client_id: clientId,
            idempotency_key: clientId,
            module: 'procurement',
            action: action,
            payload: payload || {},
            version: options.version || 1,
            occurred_at: options.occurred_at || new Date().toISOString()
        });
    }

    function pullDirectory(options) {
        options = options || {};
        if (!isActive()) {
            return Promise.resolve({ items: [], stub: true, disabled: true });
        }
        var pull = root.RatebOfflineDeltaPull;
        if (!pull || typeof pull.pull !== 'function') {
            return Promise.reject(new Error('delta_pull_unavailable'));
        }
        return pull.pull('supplier_directory', options).then(function (res) {
            var delta = (res && res.delta) ? res.delta : res;
            if (delta && Array.isArray(delta.items) && root.RatebOfflineSchema) {
                return root.RatebOfflineSchema.withStore(
                    root.RatebOfflineSchema.STORES.ENTITY_CACHE,
                    'readwrite',
                    function (store) {
                        delta.items.forEach(function (item) {
                            if (item && item.id) {
                                store.put({
                                    id: 'sup:' + item.id,
                                    entity: 'supplier_directory',
                                    data: item,
                                    updated_at: item.updated_at || null
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

    root.RatebOfflineProcurementAdapter = {
        isActive: isActive,
        enqueuePurchaseRequestDraft: function (payload, options) {
            return enqueue('purchase_request.draft', payload || {}, options);
        },
        enqueueRfqDraft: function (payload, options) {
            return enqueue('rfq.draft', payload || {}, options);
        },
        enqueuePurchaseOrderDraft: function (payload, options) {
            return enqueue('purchase_order.draft', payload || {}, options);
        },
        pullSupplierDirectory: pullDirectory,
        sync: function (options) {
            options = options || {};
            if (!isActive()) {
                return Promise.resolve({ skipped: true, disabled: true });
            }
            var q = root.RatebOfflineQueue;
            var flush = (q && typeof q.flush === 'function') ? q.flush() : Promise.resolve({ skipped: true });
            return flush.then(function (flushResult) {
                return pullDirectory(options).then(function (directory) {
                    return { flush: flushResult, directory: directory };
                });
            });
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);
