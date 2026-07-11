/**
 * RATEB Offline — HR adapter (Phase 4 / Tier 1).
 * Queues attendance, bulk attendance, and leave drafts via enterprise offline queue.
 * Activated only when offline.enabled + offline.hr.attendance are true.
 * Does NOT enqueue payroll, approvals, or financial posting.
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
        return !!(f['offline.enabled'] && f['offline.hr.attendance']);
    }

    function makeClientId(prefix) {
        var rand = Math.random().toString(36).slice(2, 10);
        return String(prefix || 'hr') + '-' + Date.now() + '-' + rand;
    }

    function enqueue(action, payload, options) {
        options = options || {};
        if (!isActive()) {
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

    function pullDirectory(options) {
        options = options || {};
        if (!isActive()) {
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

    root.RatebOfflineHrAdapter = {
        isActive: isActive,
        enqueueAttendance: function (payload, options) {
            return enqueue('attendance.create', payload || {}, options);
        },
        enqueueAttendanceBulk: function (payload, options) {
            return enqueue('attendance.bulk', payload || {}, options);
        },
        enqueueLeaveDraft: function (payload, options) {
            return enqueue('leave_request.draft', payload || {}, options);
        },
        pullEmployeeDirectory: pullDirectory,
        sync: function (options) {
            options = options || {};
            if (!isActive()) {
                return Promise.resolve({ skipped: true, disabled: true });
            }
            var q = root.RatebOfflineQueue;
            var flush = (q && typeof q.flush === 'function') ? q.flush() : Promise.resolve({ skipped: true });
            return flush.then(function (flushResult) {
                return pullDirectory(options).then(function (directory) {
                    return { flush: flushResult, directory: directory };
                });
            });
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);
