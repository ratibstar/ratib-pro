/**
 * RATEB Offline — Projects adapter (Phase 18B / Tier 1 drafts).
 * Queues project / task / workflow / timesheet drafts via enterprise offline queue.
 * Activated only when offline.enabled + offline.projects (sub-flags gate children).
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
        return !!(f['offline.enabled'] && f['offline.projects']);
    }

    function isTasksActive() {
        var f = flags();
        return !!(isActive() && f['offline.projects.tasks']);
    }

    function isWorkflowActive() {
        var f = flags();
        return !!(isActive() && f['offline.projects.workflow']);
    }

    function isTimesheetsActive() {
        var f = flags();
        return !!(isActive() && f['offline.projects.timesheets']);
    }

    function isMasterDataActive() {
        var f = flags();
        return !!(isActive() && f['offline.projects.masterdata']);
    }

    function makeClientId(prefix) {
        var rand = Math.random().toString(36).slice(2, 10);
        return String(prefix || 'prj') + '-' + Date.now() + '-' + rand;
    }

    function enqueue(action, payload, options) {
        options = options || {};
        if (!isActive()) {
            return Promise.reject(new Error('projects_offline_disabled'));
        }
        if ((action === 'task.create' || action === 'task.update') && !isTasksActive()) {
            return Promise.reject(new Error('projects_tasks_offline_disabled'));
        }
        if (action === 'workflow.transition' && !isWorkflowActive()) {
            return Promise.reject(new Error('projects_workflow_offline_disabled'));
        }
        if (action === 'timesheet.create' && !isTimesheetsActive()) {
            return Promise.reject(new Error('projects_timesheets_offline_disabled'));
        }
        var q = root.RatebOfflineQueue;
        if (!q || typeof q.enqueue !== 'function') {
            return Promise.reject(new Error('offline_queue_unavailable'));
        }
        var clientId = options.client_id || options.idempotency_key || makeClientId(action);
        return q.enqueue({
            client_id: clientId,
            idempotency_key: clientId,
            module: 'projects',
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

    root.RatebOfflineProjectsAdapter = {
        isActive: isActive,
        isTasksActive: isTasksActive,
        isWorkflowActive: isWorkflowActive,
        isTimesheetsActive: isTimesheetsActive,
        isMasterDataActive: isMasterDataActive,
        enqueue: enqueue,
        enqueueProjectCreate: function (payload, options) {
            return enqueue('project.create', payload || {}, options);
        },
        enqueueProjectUpdate: function (payload, options) {
            return enqueue('project.update', payload || {}, options);
        },
        enqueueTaskCreate: function (payload, options) {
            return enqueue('task.create', payload || {}, options);
        },
        enqueueTaskUpdate: function (payload, options) {
            return enqueue('task.update', payload || {}, options);
        },
        enqueueWorkflowTransition: function (payload, options) {
            return enqueue('workflow.transition', payload || {}, options);
        },
        enqueueMilestoneCreate: function (payload, options) {
            return enqueue('milestone.create', payload || {}, options);
        },
        enqueuePhaseCreate: function (payload, options) {
            return enqueue('phase.create', payload || {}, options);
        },
        enqueueCommentCreate: function (payload, options) {
            return enqueue('comment.create', payload || {}, options);
        },
        enqueueAssignmentCreate: function (payload, options) {
            return enqueue('assignment.create', payload || {}, options);
        },
        enqueueTimesheetCreate: function (payload, options) {
            return enqueue('timesheet.create', payload || {}, options);
        },
        enqueueIssueCreate: function (payload, options) {
            return enqueue('issue.create', payload || {}, options);
        },
        enqueueRiskCreate: function (payload, options) {
            return enqueue('risk.create', payload || {}, options);
        },
        enqueueBudgetCreate: function (payload, options) {
            return enqueue('budget.create', payload || {}, options);
        },
        enqueueActivityCreate: function (payload, options) {
            return enqueue('activity.create', payload || {}, options);
        },
        draft: function (action, payload, options) {
            return enqueue(action, payload || {}, options);
        },
        retry: function () {
            var q = root.RatebOfflineQueue;
            if (!q || typeof q.retryFailed !== 'function') {
                return Promise.reject(new Error('offline_queue_unavailable'));
            }
            return q.retryFailed({ module: 'projects' });
        },
        status: function () {
            var q = root.RatebOfflineQueue;
            if (!q || typeof q.status !== 'function') {
                return Promise.resolve({ pending: 0, failed: 0, module: 'projects' });
            }
            return q.status({ module: 'projects' });
        },
        sync: function () {
            var transport = root.RatebOfflineTransport;
            if (!transport || typeof transport.flush !== 'function') {
                return Promise.reject(new Error('offline_transport_unavailable'));
            }
            return transport.flush({ module: 'projects' });
        },
        pullTags: function (options) {
            return pullDirectory('project_tag_directory', options);
        },
        pullRoles: function (options) {
            return pullDirectory('project_role_directory', options);
        },
        pullTaskStatuses: function (options) {
            return pullDirectory('task_status_directory', options);
        },
        pullRiskLevels: function (options) {
            return pullDirectory('risk_level_directory', options);
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);
