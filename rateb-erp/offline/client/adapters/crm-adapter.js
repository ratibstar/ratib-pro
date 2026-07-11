/**
 * RATEB Offline — CRM adapter (Phase 17B / Tier 1 drafts).
 * Queues lead / workflow / activity / opportunity drafts via enterprise offline queue.
 * Activated only when offline.enabled + offline.crm (sub-flags gate children).
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
        return !!(f['offline.enabled'] && f['offline.crm']);
    }

    function isLeadsActive() {
        var f = flags();
        return !!(isActive() && f['offline.crm.leads']);
    }

    function isWorkflowActive() {
        var f = flags();
        return !!(isActive() && f['offline.crm.workflow']);
    }

    function isActivitiesActive() {
        var f = flags();
        return !!(isActive() && f['offline.crm.activities']);
    }

    function isMasterDataActive() {
        var f = flags();
        return !!(isActive() && f['offline.crm.masterdata']);
    }

    function makeClientId(prefix) {
        var rand = Math.random().toString(36).slice(2, 10);
        return String(prefix || 'crm') + '-' + Date.now() + '-' + rand;
    }

    function enqueue(action, payload, options) {
        options = options || {};
        if (!isActive()) {
            return Promise.reject(new Error('crm_offline_disabled'));
        }
        if ((action === 'lead.create' || action === 'lead.update' || action === 'note.create'
            || action === 'contact.create' || action === 'company.create')
            && !isLeadsActive()) {
            return Promise.reject(new Error('crm_leads_offline_disabled'));
        }
        if (action === 'workflow.transition' && !isWorkflowActive()) {
            return Promise.reject(new Error('crm_workflow_offline_disabled'));
        }
        if ((action === 'meeting.create' || action === 'call.create' || action === 'task.create')
            && !isActivitiesActive()) {
            return Promise.reject(new Error('crm_activities_offline_disabled'));
        }
        var q = root.RatebOfflineQueue;
        if (!q || typeof q.enqueue !== 'function') {
            return Promise.reject(new Error('offline_queue_unavailable'));
        }
        var clientId = options.client_id || options.idempotency_key || makeClientId(action);
        return q.enqueue({
            client_id: clientId,
            idempotency_key: clientId,
            module: 'crm',
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

    root.RatebOfflineCrmAdapter = {
        isActive: isActive,
        isLeadsActive: isLeadsActive,
        isWorkflowActive: isWorkflowActive,
        isActivitiesActive: isActivitiesActive,
        isMasterDataActive: isMasterDataActive,
        enqueue: enqueue,
        enqueueLeadCreate: function (payload, options) {
            return enqueue('lead.create', payload || {}, options);
        },
        enqueueLeadUpdate: function (payload, options) {
            return enqueue('lead.update', payload || {}, options);
        },
        enqueueWorkflowTransition: function (payload, options) {
            return enqueue('workflow.transition', payload || {}, options);
        },
        enqueueOpportunityCreate: function (payload, options) {
            return enqueue('opportunity.create', payload || {}, options);
        },
        enqueueMeetingCreate: function (payload, options) {
            return enqueue('meeting.create', payload || {}, options);
        },
        enqueueCallCreate: function (payload, options) {
            return enqueue('call.create', payload || {}, options);
        },
        enqueueTaskCreate: function (payload, options) {
            return enqueue('task.create', payload || {}, options);
        },
        enqueueNoteCreate: function (payload, options) {
            return enqueue('note.create', payload || {}, options);
        },
        enqueueAssignmentCreate: function (payload, options) {
            return enqueue('assignment.create', payload || {}, options);
        },
        enqueueCampaignCreate: function (payload, options) {
            return enqueue('campaign.create', payload || {}, options);
        },
        enqueueContactCreate: function (payload, options) {
            return enqueue('contact.create', payload || {}, options);
        },
        enqueueCompanyCreate: function (payload, options) {
            return enqueue('company.create', payload || {}, options);
        },
        draft: function (action, payload, options) {
            return enqueue(action, payload || {}, options);
        },
        retry: function () {
            var q = root.RatebOfflineQueue;
            if (q && typeof q.flush === 'function') {
                return q.flush();
            }
            return Promise.resolve({ skipped: true });
        },
        status: function () {
            return {
                active: isActive(),
                leads: isLeadsActive(),
                workflow: isWorkflowActive(),
                activities: isActivitiesActive(),
                masterdata: isMasterDataActive()
            };
        },
        pullLeadSources: function (options) {
            return pullDirectory('crm_lead_source_directory', options);
        },
        pullPipelineStages: function (options) {
            return pullDirectory('crm_pipeline_stage_directory', options);
        },
        pullTags: function (options) {
            return pullDirectory('crm_tag_directory', options);
        },
        pullCompanies: function (options) {
            return pullDirectory('crm_company_directory', options);
        },
        sync: function (options) {
            options = options || {};
            if (!isActive()) {
                return Promise.resolve({ skipped: true, disabled: true });
            }
            var q = root.RatebOfflineQueue;
            var flush = (q && typeof q.flush === 'function') ? q.flush() : Promise.resolve({ skipped: true });
            return flush.then(function (flushResult) {
                return {
                    flush: flushResult,
                    status: root.RatebOfflineCrmAdapter.status()
                };
            });
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);
