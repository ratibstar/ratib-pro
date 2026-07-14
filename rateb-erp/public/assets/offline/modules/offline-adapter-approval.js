/*! RATEB Offline module offline-adapter-approval.js (Phase OA — sourced from offline/client). */

/* ---- approval-adapter.js ---- */
/**
 * RATEB Offline — Approval adapter (Phase 20B / Tier 1 drafts).
 * Queues approval request / workflow / comment / delegation drafts via enterprise offline queue.
 * Activated only when offline.enabled + offline.approval (sub-flags gate children).
 * Does NOT enqueue decision actions, escalate, notifications, attachments, email/SMS, payments, or government APIs.
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
        return !!(f['offline.enabled'] && f['offline.approval']);
    }

    function isRequestsActive() {
        var f = flags();
        return !!(isActive() && f['offline.approval.requests']);
    }

    function isWorkflowActive() {
        var f = flags();
        return !!(isActive() && f['offline.approval.workflow']);
    }

    function isMasterDataActive() {
        var f = flags();
        return !!(isActive() && f['offline.approval.masterdata']);
    }

    function makeClientId(prefix) {
        var rand = Math.random().toString(36).slice(2, 10);
        return String(prefix || 'eap') + '-' + Date.now() + '-' + rand;
    }

    function enqueue(action, payload, options) {
        options = options || {};
        if (!isActive()) {
            return Promise.reject(new Error('approval_offline_disabled'));
        }
        if ((action === 'approval_request.create' || action === 'approval_request.update')
            && !isRequestsActive()) {
            return Promise.reject(new Error('approval_requests_offline_disabled'));
        }
        if (action === 'workflow.transition' && !isWorkflowActive()) {
            return Promise.reject(new Error('approval_workflow_offline_disabled'));
        }
        var q = root.RatebOfflineQueue;
        if (!q || typeof q.enqueue !== 'function') {
            return Promise.reject(new Error('offline_queue_unavailable'));
        }
        var clientId = options.client_id || options.idempotency_key || makeClientId(action);
        return q.enqueue({
            client_id: clientId,
            idempotency_key: clientId,
            module: 'approval',
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

    root.RatebOfflineApprovalAdapter = {
        isActive: isActive,
        isRequestsActive: isRequestsActive,
        isWorkflowActive: isWorkflowActive,
        isMasterDataActive: isMasterDataActive,
        enqueue: enqueue,
        enqueueRequestCreate: function (payload, options) {
            return enqueue('approval_request.create', payload || {}, options);
        },
        enqueueRequestUpdate: function (payload, options) {
            return enqueue('approval_request.update', payload || {}, options);
        },
        enqueueWorkflowTransition: function (payload, options) {
            return enqueue('workflow.transition', payload || {}, options);
        },
        enqueueCommentCreate: function (payload, options) {
            return enqueue('comment.create', payload || {}, options);
        },
        enqueueDelegationCreate: function (payload, options) {
            return enqueue('delegation.create', payload || {}, options);
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
            return q.retryFailed({ module: 'approval' });
        },
        status: function () {
            var q = root.RatebOfflineQueue;
            if (!q || typeof q.status !== 'function') {
                return Promise.resolve({ pending: 0, failed: 0, module: 'approval' });
            }
            return q.status({ module: 'approval' });
        },
        sync: function () {
            var transport = root.RatebOfflineTransport;
            if (!transport || typeof transport.flush !== 'function') {
                return Promise.reject(new Error('offline_transport_unavailable'));
            }
            return transport.flush({ module: 'approval' });
        },
        pullTemplates: function (options) {
            return pullDirectory('approval_template_directory', options);
        },
        pullChains: function (options) {
            return pullDirectory('approval_chain_directory', options);
        },
        pullStages: function (options) {
            return pullDirectory('approval_stage_directory', options);
        },
        pullRules: function (options) {
            return pullDirectory('approval_rule_directory', options);
        },
        pullDelegations: function (options) {
            return pullDirectory('approval_delegation_directory', options);
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);

