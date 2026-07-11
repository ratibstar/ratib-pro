/**
 * RATEB Offline — Enterprise Business Intelligence adapter (Phase 27B / Tier 1 drafts).
 * Queues dashboard / KPI / report / widget / dataset / alert / schedule drafts.
 * Activated only when offline.enabled + offline.bi (sub-flags gate children).
 * Does NOT enqueue delete, binary uploads, notifications, email/SMS, payments, or publish.
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
        return !!(f['offline.enabled'] && f['offline.bi']);
    }

    function isWorkflowActive() {
        var f = flags();
        return !!(isActive() && f['offline.bi.workflow']);
    }

    function isMasterDataActive() {
        var f = flags();
        return !!(isActive() && f['offline.bi.masterdata']);
    }

    function makeClientId(prefix) {
        var rand = Math.random().toString(36).slice(2, 10);
        return String(prefix || 'bi') + '-' + Date.now() + '-' + rand;
    }

    function enqueue(action, payload, options) {
        options = options || {};
        if (!isActive()) {
            return Promise.reject(new Error('bi_offline_disabled'));
        }
        if (action === 'workflow.transition' && !isWorkflowActive()) {
            return Promise.reject(new Error('bi_workflow_offline_disabled'));
        }
        if (action === 'delete' || action === 'attachment.create' || action === 'upload'
            || action === 'payment.create' || action === 'accounting.post'
            || action === 'publish' || action === 'download' || action === 'binary.upload'
            || action === 'email.send' || action === 'sms.send') {
            return Promise.reject(new Error('bi_action_rejected'));
        }
        var q = root.RatebOfflineQueue;
        if (!q || typeof q.enqueue !== 'function') {
            return Promise.reject(new Error('offline_queue_unavailable'));
        }
        var clientId = options.client_id || options.idempotency_key || makeClientId(action);
        return q.enqueue({
            client_id: clientId,
            idempotency_key: clientId,
            module: 'bi',
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

    root.RatebOfflineBiAdapter = {
        isActive: isActive,
        isWorkflowActive: isWorkflowActive,
        isMasterDataActive: isMasterDataActive,
        enqueue: enqueue,
        enqueueDashboardCreate: function (payload, options) {
            return enqueue('dashboard.create', payload || {}, options);
        },
        enqueueKpiCreate: function (payload, options) {
            return enqueue('kpi.create', payload || {}, options);
        },
        enqueueReportCreate: function (payload, options) {
            return enqueue('report.create', payload || {}, options);
        },
        enqueueWidgetCreate: function (payload, options) {
            return enqueue('widget.create', payload || {}, options);
        },
        enqueueDatasetCreate: function (payload, options) {
            return enqueue('dataset.create', payload || {}, options);
        },
        enqueueAlertCreate: function (payload, options) {
            return enqueue('alert.create', payload || {}, options);
        },
        enqueueScheduleCreate: function (payload, options) {
            return enqueue('schedule.create', payload || {}, options);
        },
        enqueueExportCreate: function (payload, options) {
            return enqueue('export.create', payload || {}, options);
        },
        enqueueTrendCreate: function (payload, options) {
            return enqueue('trend.create', payload || {}, options);
        },
        enqueueForecastCreate: function (payload, options) {
            return enqueue('forecast.create', payload || {}, options);
        },
        enqueueScopeCreate: function (payload, options) {
            return enqueue('scope.create', payload || {}, options);
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
            return q.retryFailed({ module: 'bi' });
        },
        status: function () {
            var q = root.RatebOfflineQueue;
            if (!q || typeof q.status !== 'function') {
                return Promise.resolve({ pending: 0, failed: 0, module: 'bi' });
            }
            return q.status({ module: 'bi' });
        },
        sync: function () {
            var transport = root.RatebOfflineTransport;
            if (!transport || typeof transport.flush !== 'function') {
                return Promise.reject(new Error('offline_transport_unavailable'));
            }
            return transport.flush({ module: 'bi' });
        },
        pullDashboards: function (options) {
            return pullDirectory('bi_dashboard_directory', options);
        },
        pullKpis: function (options) {
            return pullDirectory('bi_kpi_directory', options);
        },
        pullWorkflowStatuses: function (options) {
            return pullDirectory('bi_workflow_status_directory', options);
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);
