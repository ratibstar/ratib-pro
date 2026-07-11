/**
 * RATEB Offline — Enterprise Payroll adapter (Phase 24B / Tier 1 drafts).
 * Queues payroll structure / employee salary / batch / loan / advance drafts.
 * Activated only when offline.enabled + offline.payroll (sub-flags gate children).
 * Does NOT enqueue delete, calculate, approve, post, payments, GL, attendance import, leave, email/SMS, or binary uploads.
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
        return !!(f['offline.enabled'] && f['offline.payroll']);
    }

    function isEmployeeActive() {
        var f = flags();
        return !!(isActive() && f['offline.payroll.employee']);
    }

    function isBatchActive() {
        var f = flags();
        return !!(isActive() && f['offline.payroll.batch']);
    }

    function isWorkflowActive() {
        var f = flags();
        return !!(isActive() && f['offline.payroll.workflow']);
    }

    function isMasterDataActive() {
        var f = flags();
        return !!(isActive() && f['offline.payroll.masterdata']);
    }

    function makeClientId(prefix) {
        var rand = Math.random().toString(36).slice(2, 10);
        return String(prefix || 'pay') + '-' + Date.now() + '-' + rand;
    }

    function enqueue(action, payload, options) {
        options = options || {};
        if (!isActive()) {
            return Promise.reject(new Error('payroll_offline_disabled'));
        }
        if ((action === 'salary_structure.create' || action === 'salary_structure.update'
            || action === 'employee_salary.create' || action === 'employee_salary.update') && !isEmployeeActive()) {
            return Promise.reject(new Error('payroll_employee_offline_disabled'));
        }
        if ((action === 'payroll_batch.create' || action === 'payroll_batch.update') && !isBatchActive()) {
            return Promise.reject(new Error('payroll_batch_offline_disabled'));
        }
        if (action === 'workflow.transition' && !isWorkflowActive()) {
            return Promise.reject(new Error('payroll_workflow_offline_disabled'));
        }
        if (action === 'calculate' || action === 'approve' || action === 'post' || action === 'delete') {
            return Promise.reject(new Error('payroll_action_rejected'));
        }
        var q = root.RatebOfflineQueue;
        if (!q || typeof q.enqueue !== 'function') {
            return Promise.reject(new Error('offline_queue_unavailable'));
        }
        var clientId = options.client_id || options.idempotency_key || makeClientId(action);
        return q.enqueue({
            client_id: clientId,
            idempotency_key: clientId,
            module: 'payroll',
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

    root.RatebOfflinePayrollAdapter = {
        isActive: isActive,
        isEmployeeActive: isEmployeeActive,
        isBatchActive: isBatchActive,
        isWorkflowActive: isWorkflowActive,
        isMasterDataActive: isMasterDataActive,
        enqueue: enqueue,
        enqueueSalaryStructureCreate: function (payload, options) {
            return enqueue('salary_structure.create', payload || {}, options);
        },
        enqueueSalaryStructureUpdate: function (payload, options) {
            return enqueue('salary_structure.update', payload || {}, options);
        },
        enqueueEmployeeSalaryCreate: function (payload, options) {
            return enqueue('employee_salary.create', payload || {}, options);
        },
        enqueueEmployeeSalaryUpdate: function (payload, options) {
            return enqueue('employee_salary.update', payload || {}, options);
        },
        enqueuePayrollBatchCreate: function (payload, options) {
            return enqueue('payroll_batch.create', payload || {}, options);
        },
        enqueuePayrollBatchUpdate: function (payload, options) {
            return enqueue('payroll_batch.update', payload || {}, options);
        },
        enqueueWorkflowTransition: function (payload, options) {
            return enqueue('workflow.transition', payload || {}, options);
        },
        enqueueLoanCreate: function (payload, options) {
            return enqueue('loan.create', payload || {}, options);
        },
        enqueueAdvanceCreate: function (payload, options) {
            return enqueue('advance.create', payload || {}, options);
        },
        enqueueBonusCreate: function (payload, options) {
            return enqueue('bonus.create', payload || {}, options);
        },
        enqueueOvertimeCreate: function (payload, options) {
            return enqueue('overtime.create', payload || {}, options);
        },
        enqueueSettlementCreate: function (payload, options) {
            return enqueue('settlement.create', payload || {}, options);
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
            return q.retryFailed({ module: 'payroll' });
        },
        status: function () {
            var q = root.RatebOfflineQueue;
            if (!q || typeof q.status !== 'function') {
                return Promise.resolve({ pending: 0, failed: 0, module: 'payroll' });
            }
            return q.status({ module: 'payroll' });
        },
        sync: function () {
            var transport = root.RatebOfflineTransport;
            if (!transport || typeof transport.flush !== 'function') {
                return Promise.reject(new Error('offline_transport_unavailable'));
            }
            return transport.flush({ module: 'payroll' });
        },
        pullStructures: function (options) {
            return pullDirectory('payroll_structure_directory', options);
        },
        pullCycles: function (options) {
            return pullDirectory('payroll_cycle_directory', options);
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);
