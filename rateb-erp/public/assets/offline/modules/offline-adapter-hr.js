/*! RATEB Offline module offline-adapter-hr.js (Phase OA — sourced from offline/client). */

/* ---- hr-adapter.js ---- */
/**
 * RATEB Offline — HR adapter (Phase 4 / Tier 1 + Phase 23B Enterprise HRMS).
 * Phase 4: queues attendance, bulk attendance, and leave drafts when offline.hr.attendance.
 * Phase 23B: queues enterprise HRMS drafts when offline.enabled + offline.hr (+ sub-flags).
 * Module remains `hr`. Does NOT enqueue delete, payroll, payments, approvals, or financial posting.
 */
(function (root) {
    'use strict';

    function flags() {
        if (root.RatebOffline && typeof root.RatebOffline.flags === 'function') {
            return root.RatebOffline.flags() || {};
        }
        return {};
    }

    /** Phase 4 attendance/leave gate. */
    function isAttendanceActive() {
        var f = flags();
        return !!(f['offline.enabled'] && f['offline.hr.attendance']);
    }

    /** Phase 23B enterprise parent gate (offline.hr). */
    function isEnterpriseActive() {
        var f = flags();
        return !!(f['offline.enabled'] && f['offline.hr']);
    }

    /**
     * Adapter status: true when attendance OR enterprise parent is on.
     * Enqueue paths still enforce their own flags (attendance vs enterprise).
     */
    function isActive() {
        return isAttendanceActive() || isEnterpriseActive();
    }

    function isEmployeeActive() {
        var f = flags();
        return !!(isEnterpriseActive() && f['offline.hr.employee']);
    }

    function isTrainingActive() {
        var f = flags();
        return !!(isEnterpriseActive() && f['offline.hr.training']);
    }

    function isPerformanceActive() {
        var f = flags();
        return !!(isEnterpriseActive() && f['offline.hr.performance']);
    }

    function isWorkflowActive() {
        var f = flags();
        return !!(isEnterpriseActive() && f['offline.hr.workflow']);
    }

    function isMasterDataActive() {
        var f = flags();
        return !!(isEnterpriseActive() && f['offline.hr.masterdata']);
    }

    function makeClientId(prefix) {
        var rand = Math.random().toString(36).slice(2, 10);
        return String(prefix || 'hr') + '-' + Date.now() + '-' + rand;
    }

    /** Phase 4 enqueue — requires offline.hr.attendance only. */
    function enqueueAttendanceAction(action, payload, options) {
        options = options || {};
        if (!isAttendanceActive()) {
            return Promise.reject(new Error('hr_offline_disabled'));
        }
        var q = root.RatebOfflineQueue;
        if (!q || typeof q.enqueue !== 'function') {
            return Promise.reject(new Error('offline_queue_unavailable'));
        }
        var clientId = options.client_id || options.idempotency_key || makeClientId(action);
        return q.enqueue({
            client_id: clientId,
            idempotency_key: clientId,
            module: 'hr',
            action: action,
            payload: payload || {},
            version: options.version || 1,
            occurred_at: options.occurred_at || new Date().toISOString()
        });
    }

    /** Phase 23B enterprise enqueue — requires offline.hr (+ sub-flags). Never attendance/leave. */
    function enqueueEnterprise(action, payload, options) {
        options = options || {};
        if (!isEnterpriseActive()) {
            return Promise.reject(new Error('hrm_offline_disabled'));
        }
        if ((action === 'employee.create' || action === 'employee.update'
            || action === 'department.create' || action === 'position.create'
            || action === 'organization.create') && !isEmployeeActive()) {
            return Promise.reject(new Error('hrm_employee_offline_disabled'));
        }
        if (action === 'training.create' && !isTrainingActive()) {
            return Promise.reject(new Error('hrm_training_offline_disabled'));
        }
        if ((action === 'performance.create' || action === 'goal.create'
            || action === 'competency.create') && !isPerformanceActive()) {
            return Promise.reject(new Error('hrm_performance_offline_disabled'));
        }
        if (action === 'workflow.transition' && !isWorkflowActive()) {
            return Promise.reject(new Error('hrm_workflow_offline_disabled'));
        }
        // Reject delete / payroll / payments (and related) — never enqueue.
        var lower = String(action || '').toLowerCase();
        if (lower.indexOf('delete') !== -1 || lower.indexOf('payroll') !== -1
            || lower.indexOf('payment') !== -1 || lower.indexOf('attendance') !== -1
            || lower.indexOf('leave') !== -1) {
            return Promise.reject(new Error('hrm_action_not_allowed'));
        }
        var q = root.RatebOfflineQueue;
        if (!q || typeof q.enqueue !== 'function') {
            return Promise.reject(new Error('offline_queue_unavailable'));
        }
        var clientId = options.client_id || options.idempotency_key || makeClientId(action);
        return q.enqueue({
            client_id: clientId,
            idempotency_key: clientId,
            module: 'hr',
            action: action,
            payload: payload || {},
            version: options.version || 1,
            occurred_at: options.occurred_at || new Date().toISOString()
        });
    }

    function pullDirectory(options) {
        options = options || {};
        if (!isAttendanceActive()) {
            return Promise.resolve({ items: [], stub: true, disabled: true });
        }
        var pull = root.RatebOfflineDeltaPull;
        if (!pull || typeof pull.pull !== 'function') {
            return Promise.reject(new Error('delta_pull_unavailable'));
        }
        return pull.pull('employee_directory', options).then(function (res) {
            var delta = (res && res.delta) ? res.delta : res;
            if (delta && Array.isArray(delta.items) && root.RatebOfflineSchema) {
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
                                var id = cid + ':' + bid + ':emp:' + item.id;
                                try { store.delete('emp:' + item.id); } catch (e) { /* legacy */ }
                                if (item.deleted || item.active === false) {
                                    store.delete(id);
                                    return;
                                }
                                store.put({
                                    id: id,
                                    entity: 'employee_directory',
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

    function pullHrmDirectory(entity, options) {
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

    root.RatebOfflineHrAdapter = {
        isActive: isActive,
        isAttendanceActive: isAttendanceActive,
        isEnterpriseActive: isEnterpriseActive,
        isEmployeeActive: isEmployeeActive,
        isTrainingActive: isTrainingActive,
        isPerformanceActive: isPerformanceActive,
        isWorkflowActive: isWorkflowActive,
        isMasterDataActive: isMasterDataActive,

        // Phase 4 — attendance / leave (offline.hr.attendance only)
        enqueueAttendance: function (payload, options) {
            return enqueueAttendanceAction('attendance.create', payload || {}, options);
        },
        enqueueAttendanceBulk: function (payload, options) {
            return enqueueAttendanceAction('attendance.bulk', payload || {}, options);
        },
        enqueueLeaveDraft: function (payload, options) {
            return enqueueAttendanceAction('leave_request.draft', payload || {}, options);
        },
        pullEmployeeDirectory: pullDirectory,

        // Phase 23B — enterprise HRMS (offline.hr + sub-flags). No delete/payroll/payments.
        enqueueEmployeeCreate: function (payload, options) {
            return enqueueEnterprise('employee.create', payload || {}, options);
        },
        enqueueEmployeeUpdate: function (payload, options) {
            return enqueueEnterprise('employee.update', payload || {}, options);
        },
        enqueueDepartmentCreate: function (payload, options) {
            return enqueueEnterprise('department.create', payload || {}, options);
        },
        enqueuePositionCreate: function (payload, options) {
            return enqueueEnterprise('position.create', payload || {}, options);
        },
        enqueueOrganizationCreate: function (payload, options) {
            return enqueueEnterprise('organization.create', payload || {}, options);
        },
        enqueueTrainingCreate: function (payload, options) {
            return enqueueEnterprise('training.create', payload || {}, options);
        },
        enqueuePerformanceCreate: function (payload, options) {
            return enqueueEnterprise('performance.create', payload || {}, options);
        },
        enqueueGoalCreate: function (payload, options) {
            return enqueueEnterprise('goal.create', payload || {}, options);
        },
        enqueueCompetencyCreate: function (payload, options) {
            return enqueueEnterprise('competency.create', payload || {}, options);
        },
        enqueuePromotionCreate: function (payload, options) {
            return enqueueEnterprise('promotion.create', payload || {}, options);
        },
        enqueueTransferCreate: function (payload, options) {
            return enqueueEnterprise('transfer.create', payload || {}, options);
        },
        enqueueAssignmentCreate: function (payload, options) {
            return enqueueEnterprise('assignment.create', payload || {}, options);
        },
        enqueueWorkflowTransition: function (payload, options) {
            return enqueueEnterprise('workflow.transition', payload || {}, options);
        },
        enqueueCommentCreate: function (payload, options) {
            return enqueueEnterprise('comment.create', payload || {}, options);
        },
        enqueueNoteCreate: function (payload, options) {
            return enqueueEnterprise('note.create', payload || {}, options);
        },
        pullDepartments: function (options) {
            return pullHrmDirectory('hrm_department_directory', options);
        },
        pullPositions: function (options) {
            return pullHrmDirectory('hrm_position_directory', options);
        },

        sync: function (options) {
            options = options || {};
            if (!isActive()) {
                return Promise.resolve({ skipped: true, disabled: true });
            }
            var q = root.RatebOfflineQueue;
            var flush = (q && typeof q.flush === 'function') ? q.flush() : Promise.resolve({ skipped: true });
            return flush.then(function (flushResult) {
                var directoryPromise = isAttendanceActive()
                    ? pullDirectory(options)
                    : Promise.resolve({ items: [], stub: true, skipped: true });
                var deptsPromise = isMasterDataActive()
                    ? pullHrmDirectory('hrm_department_directory', options)
                    : Promise.resolve({ items: [], stub: true, skipped: true });
                var posPromise = isMasterDataActive()
                    ? pullHrmDirectory('hrm_position_directory', options)
                    : Promise.resolve({ items: [], stub: true, skipped: true });
                return Promise.all([directoryPromise, deptsPromise, posPromise]).then(function (parts) {
                    return {
                        flush: flushResult,
                        directory: parts[0],
                        departments: parts[1],
                        positions: parts[2]
                    };
                });
            });
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);

