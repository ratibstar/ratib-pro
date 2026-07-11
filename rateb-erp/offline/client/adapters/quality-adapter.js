/**
 * RATEB Offline — Enterprise Quality (QMS) adapter (Phase 25B / Tier 1 drafts).
 * Queues inspection / checklist / audit / defect / CAPA / complaint drafts.
 * Activated only when offline.enabled + offline.quality (sub-flags gate children).
 * Does NOT enqueue delete, attachments, binary uploads, notifications, email/SMS,
 * payments, government, approvals, inventory posting, or GL posting.
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
        return !!(f['offline.enabled'] && f['offline.quality']);
    }

    function isInspectionsActive() {
        var f = flags();
        return !!(isActive() && f['offline.quality.inspections']);
    }

    function isAuditActive() {
        var f = flags();
        return !!(isActive() && f['offline.quality.audit']);
    }

    function isWorkflowActive() {
        var f = flags();
        return !!(isActive() && f['offline.quality.workflow']);
    }

    function isMasterDataActive() {
        var f = flags();
        return !!(isActive() && f['offline.quality.masterdata']);
    }

    function makeClientId(prefix) {
        var rand = Math.random().toString(36).slice(2, 10);
        return String(prefix || 'qms') + '-' + Date.now() + '-' + rand;
    }

    function enqueue(action, payload, options) {
        options = options || {};
        if (!isActive()) {
            return Promise.reject(new Error('quality_offline_disabled'));
        }
        if ((action === 'inspection.create' || action === 'inspection.update'
            || action === 'checklist.create') && !isInspectionsActive()) {
            return Promise.reject(new Error('quality_inspections_offline_disabled'));
        }
        if (action === 'audit.create' && !isAuditActive()) {
            return Promise.reject(new Error('quality_audit_offline_disabled'));
        }
        if (action === 'workflow.transition' && !isWorkflowActive()) {
            return Promise.reject(new Error('quality_workflow_offline_disabled'));
        }
        if (action === 'delete' || action === 'attachment.create' || action === 'upload'
            || action === 'payment.create' || action === 'accounting.post') {
            return Promise.reject(new Error('quality_action_rejected'));
        }
        var q = root.RatebOfflineQueue;
        if (!q || typeof q.enqueue !== 'function') {
            return Promise.reject(new Error('offline_queue_unavailable'));
        }
        var clientId = options.client_id || options.idempotency_key || makeClientId(action);
        return q.enqueue({
            client_id: clientId,
            idempotency_key: clientId,
            module: 'quality',
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

    root.RatebOfflineQualityAdapter = {
        isActive: isActive,
        isInspectionsActive: isInspectionsActive,
        isAuditActive: isAuditActive,
        isWorkflowActive: isWorkflowActive,
        isMasterDataActive: isMasterDataActive,
        enqueue: enqueue,
        enqueueInspectionCreate: function (payload, options) {
            return enqueue('inspection.create', payload || {}, options);
        },
        enqueueInspectionUpdate: function (payload, options) {
            return enqueue('inspection.update', payload || {}, options);
        },
        enqueueChecklistCreate: function (payload, options) {
            return enqueue('checklist.create', payload || {}, options);
        },
        enqueueAuditCreate: function (payload, options) {
            return enqueue('audit.create', payload || {}, options);
        },
        enqueueDefectCreate: function (payload, options) {
            return enqueue('defect.create', payload || {}, options);
        },
        enqueueNonconformityCreate: function (payload, options) {
            return enqueue('nonconformity.create', payload || {}, options);
        },
        enqueueCorrectiveActionCreate: function (payload, options) {
            return enqueue('corrective_action.create', payload || {}, options);
        },
        enqueuePreventiveActionCreate: function (payload, options) {
            return enqueue('preventive_action.create', payload || {}, options);
        },
        enqueueSupplierQualityCreate: function (payload, options) {
            return enqueue('supplier_quality.create', payload || {}, options);
        },
        enqueueComplaintCreate: function (payload, options) {
            return enqueue('complaint.create', payload || {}, options);
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
        enqueueWorkflowTransition: function (payload, options) {
            return enqueue('workflow.transition', payload || {}, options);
        },
        draft: function (action, payload, options) {
            return enqueue(action, payload || {}, options);
        },
        retry: function () {
            var q = root.RatebOfflineQueue;
            if (!q || typeof q.retryFailed !== 'function') {
                return Promise.reject(new Error('offline_queue_unavailable'));
            }
            return q.retryFailed({ module: 'quality' });
        },
        status: function () {
            var q = root.RatebOfflineQueue;
            if (!q || typeof q.status !== 'function') {
                return Promise.resolve({ pending: 0, failed: 0, module: 'quality' });
            }
            return q.status({ module: 'quality' });
        },
        sync: function () {
            var transport = root.RatebOfflineTransport;
            if (!transport || typeof transport.flush !== 'function') {
                return Promise.reject(new Error('offline_transport_unavailable'));
            }
            return transport.flush({ module: 'quality' });
        },
        pullPlans: function (options) {
            return pullDirectory('quality_plan_directory', options);
        },
        pullChecklists: function (options) {
            return pullDirectory('quality_checklist_directory', options);
        },
        pullStandards: function (options) {
            return pullDirectory('quality_standard_directory', options);
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);
