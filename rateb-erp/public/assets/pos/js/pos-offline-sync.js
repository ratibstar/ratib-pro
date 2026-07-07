(function () {
    'use strict';

    var STORAGE_KEY = 'rateb_pos_offline_queue_v1';

    function readQueue() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            var parsed = raw ? JSON.parse(raw) : [];
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }

    function writeQueue(items) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
        window.RatebPosOffline.queueDepth = items.length;
    }

    function defaultApiBase() {
        var path = window.location.pathname || '';
        var marker = '/rateb-erp/public/';
        var idx = path.indexOf(marker);
        if (idx >= 0) {
            return path.slice(0, idx + marker.length) + 'admin/ops/pos/api/sync';
        }
        return '/rateb-erp/public/admin/ops/pos/api/sync';
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="rateb-csrf"]');
        return meta ? meta.getAttribute('content') : '';
    }

    window.RatebPosOffline = {
        queueDepth: readQueue().length,

        push: function (item) {
            var queue = readQueue();
            queue.push({
                client_id: item.client_id || ('local-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8)),
                action: item.action || 'unknown',
                payload: item.payload || {},
                occurred_at: item.occurred_at || new Date().toISOString(),
                version: item.version || 1
            });
            writeQueue(queue);
            if (navigator.onLine) {
                return window.RatebPosOffline.sync();
            }
            return Promise.resolve({ queued: true, queueDepth: queue.length });
        },

        sync: function (options) {
            options = options || {};
            var queue = readQueue();
            if (!queue.length) {
                return Promise.resolve({ accepted: 0, duplicate: 0, conflict: 0, queueDepth: 0 });
            }

            var base = options.apiBase || defaultApiBase();
            var body = {
                terminal_id: options.terminalId || 0,
                branch_id: options.branchId || 0,
                items: queue
            };

            return fetch(base + '/push', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken()
                },
                body: JSON.stringify(body)
            }).then(function (res) {
                return res.json().then(function (payload) {
                    if (!res.ok || !payload.ok) {
                        throw new Error((payload && payload.error && payload.error.message) || 'sync_failed');
                    }
                    writeQueue([]);
                    return Object.assign({ queueDepth: 0 }, payload.result || {});
                });
            });
        },

        status: function (options) {
            options = options || {};
            var base = options.apiBase || defaultApiBase();
            return fetch(base + '/status', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            }).then(function (res) {
                return res.json();
            });
        }
    };

    window.addEventListener('online', function () {
        window.RatebPosOffline.sync().catch(function () { /* retry on next online */ });
    });
})();
