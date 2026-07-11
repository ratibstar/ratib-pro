/**
 * RATEB Offline — Enterprise Procurement adapter (Phase 21B / Tier 1 drafts).
 * Queues EPROC supplier / tender / contract / workflow drafts via enterprise offline queue.
 * Activated only when offline.enabled + offline.procurement_enterprise (sub-flags gate children).
 * Distinct from legacy RatebOfflineProcurementAdapter (PR/PO/RFQ).
 * Does NOT enqueue delete, payments, approvals, notifications, email/SMS, attachments, or government APIs.
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
        return !!(f['offline.enabled'] && f['offline.procurement_enterprise']);
    }

    function isSuppliersActive() {
        var f = flags();
        return !!(isActive() && f['offline.procurement_enterprise.suppliers']);
    }

    function isTendersActive() {
        var f = flags();
        return !!(isActive() && f['offline.procurement_enterprise.tenders']);
    }

    function isContractsActive() {
        var f = flags();
        return !!(isActive() && f['offline.procurement_enterprise.contracts']);
    }

    function isWorkflowActive() {
        var f = flags();
        return !!(isActive() && f['offline.procurement_enterprise.workflow']);
    }

    function isMasterDataActive() {
        var f = flags();
        return !!(isActive() && f['offline.procurement_enterprise.masterdata']);
    }

    function makeClientId(prefix) {
        var rand = Math.random().toString(36).slice(2, 10);
        return String(prefix || 'eproc') + '-' + Date.now() + '-' + rand;
    }

    function enqueue(action, payload, options) {
        options = options || {};
        if (!isActive()) {
            return Promise.reject(new Error('procurement_enterprise_offline_disabled'));
        }
        if ((action === 'supplier_profile.create'
            || action === 'supplier_profile.update'
            || action === 'qualification.create'
            || action === 'qualification.update'
            || action === 'risk.create'
            || action === 'scorecard.create'
            || action === 'portal_invite.create'
            || action === 'collaboration.create') && !isSuppliersActive()) {
            return Promise.reject(new Error('procurement_enterprise_suppliers_offline_disabled'));
        }
        if ((action === 'tender.create'
            || action === 'bid.create'
            || action === 'bid_compare.create') && !isTendersActive()) {
            return Promise.reject(new Error('procurement_enterprise_tenders_offline_disabled'));
        }
        if (action === 'contract.create' && !isContractsActive()) {
            return Promise.reject(new Error('procurement_enterprise_contracts_offline_disabled'));
        }
        if (action === 'workflow.transition' && !isWorkflowActive()) {
            return Promise.reject(new Error('procurement_enterprise_workflow_offline_disabled'));
        }
        var q = root.RatebOfflineQueue;
        if (!q || typeof q.enqueue !== 'function') {
            return Promise.reject(new Error('offline_queue_unavailable'));
        }
        var clientId = options.client_id || options.idempotency_key || makeClientId(action);
        return q.enqueue({
            client_id: clientId,
            idempotency_key: clientId,
            module: 'procurement_enterprise',
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

    root.RatebOfflineProcurementEnterpriseAdapter = {
        isActive: isActive,
        isSuppliersActive: isSuppliersActive,
        isTendersActive: isTendersActive,
        isContractsActive: isContractsActive,
        isWorkflowActive: isWorkflowActive,
        isMasterDataActive: isMasterDataActive,
        enqueue: enqueue,
        enqueueSupplierProfileCreate: function (payload, options) {
            return enqueue('supplier_profile.create', payload || {}, options);
        },
        enqueueSupplierProfileUpdate: function (payload, options) {
            return enqueue('supplier_profile.update', payload || {}, options);
        },
        enqueueQualificationCreate: function (payload, options) {
            return enqueue('qualification.create', payload || {}, options);
        },
        enqueueQualificationUpdate: function (payload, options) {
            return enqueue('qualification.update', payload || {}, options);
        },
        enqueueRiskCreate: function (payload, options) {
            return enqueue('risk.create', payload || {}, options);
        },
        enqueueScorecardCreate: function (payload, options) {
            return enqueue('scorecard.create', payload || {}, options);
        },
        enqueuePortalInviteCreate: function (payload, options) {
            return enqueue('portal_invite.create', payload || {}, options);
        },
        enqueueTenderCreate: function (payload, options) {
            return enqueue('tender.create', payload || {}, options);
        },
        enqueueBidCreate: function (payload, options) {
            return enqueue('bid.create', payload || {}, options);
        },
        enqueueBidCompareCreate: function (payload, options) {
            return enqueue('bid_compare.create', payload || {}, options);
        },
        enqueueContractCreate: function (payload, options) {
            return enqueue('contract.create', payload || {}, options);
        },
        enqueueCollaborationCreate: function (payload, options) {
            return enqueue('collaboration.create', payload || {}, options);
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
            return q.retryFailed({ module: 'procurement_enterprise' });
        },
        status: function () {
            var q = root.RatebOfflineQueue;
            if (!q || typeof q.status !== 'function') {
                return Promise.resolve({ pending: 0, failed: 0, module: 'procurement_enterprise' });
            }
            return q.status({ module: 'procurement_enterprise' });
        },
        sync: function () {
            var transport = root.RatebOfflineTransport;
            if (!transport || typeof transport.flush !== 'function') {
                return Promise.reject(new Error('offline_transport_unavailable'));
            }
            return transport.flush({ module: 'procurement_enterprise' });
        },
        pullCategories: function (options) {
            return pullDirectory('eproc_supplier_category_directory', options);
        },
        pullRfqTemplates: function (options) {
            return pullDirectory('eproc_rfq_template_directory', options);
        },
        pullTags: function (options) {
            return pullDirectory('eproc_tag_directory', options);
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);
