/*! RATEB Offline module offline-adapter-manufacturing.js (Phase OA — sourced from offline/client). */

/* ---- manufacturing-adapter.js ---- */
/**
 * RATEB Offline — Enterprise Manufacturing adapter (Phase 22B / Tier 1 drafts).
 * Queues MFG BOM / routing / production / work order / material / quality drafts.
 * Activated only when offline.enabled + offline.manufacturing (sub-flags gate children).
 * Does NOT enqueue delete, inventory posting, GL, payments, approvals, email/SMS, or binary uploads.
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
        return !!(f['offline.enabled'] && f['offline.manufacturing']);
    }

    function isProductionActive() {
        var f = flags();
        return !!(isActive() && f['offline.manufacturing.production']);
    }

    function isWorkflowActive() {
        var f = flags();
        return !!(isActive() && f['offline.manufacturing.workflow']);
    }

    function isQualityActive() {
        var f = flags();
        return !!(isActive() && f['offline.manufacturing.quality']);
    }

    function isMasterDataActive() {
        var f = flags();
        return !!(isActive() && f['offline.manufacturing.masterdata']);
    }

    function makeClientId(prefix) {
        var rand = Math.random().toString(36).slice(2, 10);
        return String(prefix || 'mfg') + '-' + Date.now() + '-' + rand;
    }

    function enqueue(action, payload, options) {
        options = options || {};
        if (!isActive()) {
            return Promise.reject(new Error('manufacturing_offline_disabled'));
        }
        if ((action === 'bom.create' || action === 'bom.update'
            || action === 'routing.create' || action === 'routing.update'
            || action === 'production_order.create' || action === 'production_order.update'
            || action === 'work_order.create' || action === 'work_order.update'
            || action === 'material_reservation.create' || action === 'material_consumption.create'
            || action === 'finished_goods.create' || action === 'scrap.create') && !isProductionActive()) {
            return Promise.reject(new Error('manufacturing_production_offline_disabled'));
        }
        if (action === 'workflow.transition' && !isWorkflowActive()) {
            return Promise.reject(new Error('manufacturing_workflow_offline_disabled'));
        }
        if (action === 'quality_check.create' && !isQualityActive()) {
            return Promise.reject(new Error('manufacturing_quality_offline_disabled'));
        }
        var q = root.RatebOfflineQueue;
        if (!q || typeof q.enqueue !== 'function') {
            return Promise.reject(new Error('offline_queue_unavailable'));
        }
        var clientId = options.client_id || options.idempotency_key || makeClientId(action);
        return q.enqueue({
            client_id: clientId,
            idempotency_key: clientId,
            module: 'manufacturing',
            action: action,
            payload: payload || {},
            version: options.version || 1,
            occurred_at: options.occurred_at || new Date().toISOString()
        });
    }

    function pullDirectory(entity, options) {
        options = options || {};
        if (!isMasterDataActive()) {
            return Promise.resolve({ items: [], stub: true, disabled: true });
        }
        var pull = root.RatebOfflineDeltaPull;
        if (!pull || typeof pull.pull !== 'function') {
            return Promise.reject(new Error('delta_pull_unavailable'));
        }
        return pull.pull(entity, options).then(function (res) {
            return (res && res.delta) ? res.delta : (res || { items: [] });
        });
    }

    root.RatebOfflineManufacturingAdapter = {
        isActive: isActive,
        isProductionActive: isProductionActive,
        isWorkflowActive: isWorkflowActive,
        isQualityActive: isQualityActive,
        isMasterDataActive: isMasterDataActive,
        enqueue: enqueue,
        enqueueBomCreate: function (payload, options) {
            return enqueue('bom.create', payload || {}, options);
        },
        enqueueBomUpdate: function (payload, options) {
            return enqueue('bom.update', payload || {}, options);
        },
        enqueueRoutingCreate: function (payload, options) {
            return enqueue('routing.create', payload || {}, options);
        },
        enqueueRoutingUpdate: function (payload, options) {
            return enqueue('routing.update', payload || {}, options);
        },
        enqueueProductionOrderCreate: function (payload, options) {
            return enqueue('production_order.create', payload || {}, options);
        },
        enqueueProductionOrderUpdate: function (payload, options) {
            return enqueue('production_order.update', payload || {}, options);
        },
        enqueueWorkOrderCreate: function (payload, options) {
            return enqueue('work_order.create', payload || {}, options);
        },
        enqueueWorkOrderUpdate: function (payload, options) {
            return enqueue('work_order.update', payload || {}, options);
        },
        enqueueWorkflowTransition: function (payload, options) {
            return enqueue('workflow.transition', payload || {}, options);
        },
        enqueueMaterialReservationCreate: function (payload, options) {
            return enqueue('material_reservation.create', payload || {}, options);
        },
        enqueueMaterialConsumptionCreate: function (payload, options) {
            return enqueue('material_consumption.create', payload || {}, options);
        },
        enqueueFinishedGoodsCreate: function (payload, options) {
            return enqueue('finished_goods.create', payload || {}, options);
        },
        enqueueScrapCreate: function (payload, options) {
            return enqueue('scrap.create', payload || {}, options);
        },
        enqueueQualityCheckCreate: function (payload, options) {
            return enqueue('quality_check.create', payload || {}, options);
        },
        enqueueCostCreate: function (payload, options) {
            return enqueue('cost.create', payload || {}, options);
        },
        enqueueAssignmentCreate: function (payload, options) {
            return enqueue('assignment.create', payload || {}, options);
        },
        enqueueCommentCreate: function (payload, options) {
            return enqueue('comment.create', payload || {}, options);
        },
        enqueueNoteCreate: function (payload, options) {
            return enqueue('note.create', payload || {}, options);
        },
        draft: function (action, payload, options) {
            return enqueue(action, payload || {}, options);
        },
        retry: function () {
            var q = root.RatebOfflineQueue;
            if (!q || typeof q.retryFailed !== 'function') {
                return Promise.reject(new Error('offline_queue_unavailable'));
            }
            return q.retryFailed({ module: 'manufacturing' });
        },
        status: function () {
            var q = root.RatebOfflineQueue;
            if (!q || typeof q.status !== 'function') {
                return Promise.resolve({ pending: 0, failed: 0, module: 'manufacturing' });
            }
            return q.status({ module: 'manufacturing' });
        },
        sync: function () {
            var transport = root.RatebOfflineTransport;
            if (!transport || typeof transport.flush !== 'function') {
                return Promise.reject(new Error('offline_transport_unavailable'));
            }
            return transport.flush({ module: 'manufacturing' });
        },
        pullProducts: function (options) {
            return pullDirectory('mfg_product_directory', options);
        },
        pullWorkCenters: function (options) {
            return pullDirectory('mfg_work_center_directory', options);
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);

