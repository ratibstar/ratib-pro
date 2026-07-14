/*! RATEB Offline module offline-adapter-assets.js (Phase OA — sourced from offline/client). */

/* ---- assets-adapter.js ---- */
/**
 * RATEB Offline — Assets adapter (Phase 19B / Tier 1 drafts).
 * Queues asset / maintenance / workflow / inspection drafts via enterprise offline queue.
 * Activated only when offline.enabled + offline.assets (sub-flags gate children).
 * Does NOT enqueue delete, payments, approvals, email/SMS, attachments, or government APIs.
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
        return !!(f['offline.enabled'] && f['offline.assets']);
    }

    function isMaintenanceActive() {
        var f = flags();
        return !!(isActive() && f['offline.assets.maintenance']);
    }

    function isWorkflowActive() {
        var f = flags();
        return !!(isActive() && f['offline.assets.workflow']);
    }

    function isInspectionsActive() {
        var f = flags();
        return !!(isActive() && f['offline.assets.inspections']);
    }

    function isMasterDataActive() {
        var f = flags();
        return !!(isActive() && f['offline.assets.masterdata']);
    }

    function makeClientId(prefix) {
        var rand = Math.random().toString(36).slice(2, 10);
        return String(prefix || 'eam') + '-' + Date.now() + '-' + rand;
    }

    function enqueue(action, payload, options) {
        options = options || {};
        if (!isActive()) {
            return Promise.reject(new Error('assets_offline_disabled'));
        }
        if ((action === 'maintenance_request.create'
            || action === 'maintenance_plan.create'
            || action === 'work_order.create') && !isMaintenanceActive()) {
            return Promise.reject(new Error('assets_maintenance_offline_disabled'));
        }
        if (action === 'workflow.transition' && !isWorkflowActive()) {
            return Promise.reject(new Error('assets_workflow_offline_disabled'));
        }
        if ((action === 'inspection.create'
            || action === 'checklist.create'
            || action === 'meter_reading.create') && !isInspectionsActive()) {
            return Promise.reject(new Error('assets_inspections_offline_disabled'));
        }
        var q = root.RatebOfflineQueue;
        if (!q || typeof q.enqueue !== 'function') {
            return Promise.reject(new Error('offline_queue_unavailable'));
        }
        var clientId = options.client_id || options.idempotency_key || makeClientId(action);
        return q.enqueue({
            client_id: clientId,
            idempotency_key: clientId,
            module: 'assets',
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

    root.RatebOfflineAssetsAdapter = {
        isActive: isActive,
        isMaintenanceActive: isMaintenanceActive,
        isWorkflowActive: isWorkflowActive,
        isInspectionsActive: isInspectionsActive,
        isMasterDataActive: isMasterDataActive,
        enqueue: enqueue,
        enqueueAssetCreate: function (payload, options) {
            return enqueue('asset.create', payload || {}, options);
        },
        enqueueAssetUpdate: function (payload, options) {
            return enqueue('asset.update', payload || {}, options);
        },
        enqueueWorkflowTransition: function (payload, options) {
            return enqueue('workflow.transition', payload || {}, options);
        },
        enqueueAssignmentCreate: function (payload, options) {
            return enqueue('assignment.create', payload || {}, options);
        },
        enqueueTransferCreate: function (payload, options) {
            return enqueue('transfer.create', payload || {}, options);
        },
        enqueueMaintenanceRequestCreate: function (payload, options) {
            return enqueue('maintenance_request.create', payload || {}, options);
        },
        enqueueMaintenancePlanCreate: function (payload, options) {
            return enqueue('maintenance_plan.create', payload || {}, options);
        },
        enqueueWorkOrderCreate: function (payload, options) {
            return enqueue('work_order.create', payload || {}, options);
        },
        enqueueInspectionCreate: function (payload, options) {
            return enqueue('inspection.create', payload || {}, options);
        },
        enqueueChecklistCreate: function (payload, options) {
            return enqueue('checklist.create', payload || {}, options);
        },
        enqueueMeterReadingCreate: function (payload, options) {
            return enqueue('meter_reading.create', payload || {}, options);
        },
        enqueueCommentCreate: function (payload, options) {
            return enqueue('comment.create', payload || {}, options);
        },
        enqueueActivityCreate: function (payload, options) {
            return enqueue('activity.create', payload || {}, options);
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
            return q.retryFailed({ module: 'assets' });
        },
        status: function () {
            var q = root.RatebOfflineQueue;
            if (!q || typeof q.status !== 'function') {
                return Promise.resolve({ pending: 0, failed: 0, module: 'assets' });
            }
            return q.status({ module: 'assets' });
        },
        sync: function () {
            var transport = root.RatebOfflineTransport;
            if (!transport || typeof transport.flush !== 'function') {
                return Promise.reject(new Error('offline_transport_unavailable'));
            }
            return transport.flush({ module: 'assets' });
        },
        pullCategories: function (options) {
            return pullDirectory('asset_category_directory', options);
        },
        pullManufacturers: function (options) {
            return pullDirectory('asset_manufacturer_directory', options);
        },
        pullLocations: function (options) {
            return pullDirectory('asset_location_directory', options);
        },
        pullModels: function (options) {
            return pullDirectory('asset_model_directory', options);
        },
        pullMaintenancePlans: function (options) {
            return pullDirectory('maintenance_plan_directory', options);
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);

