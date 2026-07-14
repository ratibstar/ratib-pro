/*! RATEB Offline module offline-adapter-recruitment.js (Phase OA — sourced from offline/client). */

/* ---- recruitment-adapter.js ---- */
/**
 * RATEB Offline — Recruitment adapter (Phase 15B / Tier 1).
 * Queues candidate / workflow / assignment / metadata drafts via enterprise offline queue.
 * Activated only when offline.enabled + offline.recruitment (sub-flags gate children).
 * Does NOT enqueue approvals, payments, government submission, or binary uploads.
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
        return !!(f['offline.enabled'] && f['offline.recruitment']);
    }

    function isCandidatesActive() {
        var f = flags();
        return !!(isActive() && f['offline.recruitment.candidates']);
    }

    function isWorkflowActive() {
        var f = flags();
        return !!(isActive() && f['offline.recruitment.workflow']);
    }

    function isAssignmentActive() {
        var f = flags();
        return !!(isActive() && f['offline.recruitment.assignment']);
    }

    function makeClientId(prefix) {
        var rand = Math.random().toString(36).slice(2, 10);
        return String(prefix || 'rec') + '-' + Date.now() + '-' + rand;
    }

    function enqueue(action, payload, options) {
        options = options || {};
        if (!isActive()) {
            return Promise.reject(new Error('recruitment_offline_disabled'));
        }
        if ((action === 'candidate.create' || action === 'candidate.update' || action === 'note.create')
            && !isCandidatesActive()) {
            return Promise.reject(new Error('recruitment_candidates_offline_disabled'));
        }
        if (action === 'workflow.transition' && !isWorkflowActive()) {
            return Promise.reject(new Error('recruitment_workflow_offline_disabled'));
        }
        if (action === 'assignment.create' && !isAssignmentActive()) {
            return Promise.reject(new Error('recruitment_assignment_offline_disabled'));
        }
        var q = root.RatebOfflineQueue;
        if (!q || typeof q.enqueue !== 'function') {
            return Promise.reject(new Error('offline_queue_unavailable'));
        }
        var clientId = options.client_id || options.idempotency_key || makeClientId(action);
        return q.enqueue({
            client_id: clientId,
            idempotency_key: clientId,
            module: 'recruitment',
            action: action,
            payload: payload || {},
            version: options.version || 1,
            occurred_at: options.occurred_at || new Date().toISOString()
        });
    }

    function pullDirectory(entity, options) {
        options = options || {};
        if (!isActive()) {
            return Promise.resolve({ items: [], stub: true, disabled: true });
        }
        var pull = root.RatebOfflineDeltaPull;
        if (!pull || typeof pull.pull !== 'function') {
            return Promise.reject(new Error('delta_pull_unavailable'));
        }
        return pull.pull(entity, options).then(function (res) {
            var delta = (res && res.delta) ? res.delta : res;
            if (delta && Array.isArray(delta.items) && root.RatebOfflineSchema) {
                var prefix = entity === 'recruitment_agency_directory' ? 'rag'
                    : (entity === 'recruitment_skill_directory' ? 'rsk' : 'rlg');
                return root.RatebOfflineSchema.withStore(
                    root.RatebOfflineSchema.STORES.ENTITY_CACHE,
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
                                var id = cid + ':' + bid + ':' + prefix + ':' + item.id;
                                if (item.deleted || item.active === false) {
                                    store.delete(id);
                                    return;
                                }
                                store.put({
                                    id: id,
                                    entity: entity,
                                    company_id: cid,
                                    branch_id: bid,
                                    payload: item,
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

    root.RatebOfflineRecruitmentAdapter = {
        isActive: isActive,
        isCandidatesActive: isCandidatesActive,
        isWorkflowActive: isWorkflowActive,
        isAssignmentActive: isAssignmentActive,
        enqueue: enqueue,
        enqueueCandidateCreate: function (payload, options) {
            return enqueue('candidate.create', payload || {}, options);
        },
        enqueueCandidateUpdate: function (payload, options) {
            return enqueue('candidate.update', payload || {}, options);
        },
        enqueueWorkflowTransition: function (payload, options) {
            return enqueue('workflow.transition', payload || {}, options);
        },
        enqueueAssignmentCreate: function (payload, options) {
            return enqueue('assignment.create', payload || {}, options);
        },
        enqueueInterviewCreate: function (payload, options) {
            return enqueue('interview.create', payload || {}, options);
        },
        enqueueVisaCreate: function (payload, options) {
            return enqueue('visa.create', payload || {}, options);
        },
        enqueueMedicalCreate: function (payload, options) {
            return enqueue('medical.create', payload || {}, options);
        },
        enqueuePassportUpdate: function (payload, options) {
            return enqueue('passport.update', payload || {}, options);
        },
        enqueueContractCreate: function (payload, options) {
            return enqueue('contract.create', payload || {}, options);
        },
        enqueueNoteCreate: function (payload, options) {
            return enqueue('note.create', payload || {}, options);
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
                candidates: isCandidatesActive(),
                workflow: isWorkflowActive(),
                assignment: isAssignmentActive()
            };
        },
        pullAgencyDirectory: function (options) {
            return pullDirectory('recruitment_agency_directory', options);
        },
        pullSkillDirectory: function (options) {
            return pullDirectory('recruitment_skill_directory', options);
        },
        pullLanguageDirectory: function (options) {
            return pullDirectory('recruitment_language_directory', options);
        },
        sync: function (options) {
            options = options || {};
            if (!isActive()) {
                return Promise.resolve({ skipped: true, disabled: true });
            }
            var q = root.RatebOfflineQueue;
            var flush = (q && typeof q.flush === 'function') ? q.flush() : Promise.resolve({ skipped: true });
            return flush.then(function (flushResult) {
                return pullDirectory('recruitment_agency_directory', options).then(function (directory) {
                    return { flush: flushResult, directory: directory, status: root.RatebOfflineRecruitmentAdapter.status() };
                });
            });
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);

