/*! RATEB Offline module offline-sync.js (Phase OA — sourced from offline/client). */

/* ---- delta-pull.js ---- */
/**
 * RATEB Offline — Delta pull (Phase 13.1).
 * Supports client cursor, branch_id, and optional device_id for master-data gates.
 */
(function (root) {
    'use strict';

    root.RatebOfflineDeltaPull = {
        pull: function (entity, options) {
            options = options || {};
            var base = options.apiBase || '';
            if (!base || !entity) {
                return Promise.resolve({ entity: entity || '', items: [], cursor: null, stub: true });
            }
            var url = String(base).replace(/\/$/, '') + '/delta/' + encodeURIComponent(entity);
            var params = [];
            if (options.cursor) {
                params.push('cursor=' + encodeURIComponent(options.cursor));
            }
            if (options.branch_id) {
                params.push('branch_id=' + encodeURIComponent(String(options.branch_id)));
            }
            if (options.device_id) {
                params.push('device_id=' + encodeURIComponent(String(options.device_id)));
            }
            if (params.length) {
                url += (url.indexOf('?') >= 0 ? '&' : '?') + params.join('&');
            }
            var headers = { Accept: 'application/json' };
            if (options.device_id) {
                headers['X-Rateb-Device-Id'] = String(options.device_id);
            }
            return fetch(url, {
                credentials: 'same-origin',
                headers: headers
            }).then(function (res) {
                return res.json();
            });
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);

/* ---- transport.js ---- */
/**
 * RATEB Offline — Transport Layer (Phase 2A).
 * Wraps fetch: online → passthrough; offline + RS → queue. Never alters business payloads.
 */
(function (root) {
    'use strict';

    var enabled = false;
    var rsActions = {
        'offline.ack': true,
        checkout: true,
        complete_sale: true,
        process_return: true,
        process_exchange: true
    };

    function isOnline() {
        var c = root.RatebOfflineConnectivity;
        return c ? c.isOnline() : (typeof navigator === 'undefined' || navigator.onLine !== false);
    }

    function shouldQueue(options) {
        if (!enabled) {
            return false;
        }
        if (isOnline()) {
            return false;
        }
        var action = options && options.action;
        return !!(action && rsActions[action]);
    }

    /**
     * @param {string} url
     * @param {RequestInit & { action?: string, module?: string, payload?: object, offline?: object }} init
     */
    function request(url, init) {
        init = init || {};
        var offlineMeta = init.offline || {};
        var action = offlineMeta.action || init.action || '';
        var moduleName = offlineMeta.module || init.module || 'offline_meta';
        var payload = offlineMeta.payload != null ? offlineMeta.payload : (init.body && typeof init.body === 'string'
            ? (function () {
                try { return JSON.parse(init.body); } catch (e) { return {}; }
            })()
            : {});

        var fetchInit = Object.assign({}, init);
        delete fetchInit.offline;
        delete fetchInit.action;
        delete fetchInit.module;
        delete fetchInit.payload;

        if (shouldQueue({ action: action })) {
            var q = root.RatebOfflineQueue;
            if (!q) {
                return Promise.reject(new Error('queue_unavailable'));
            }
            var safePayload = payload && typeof payload === 'object' ? Object.assign({}, payload) : {};
            if (safePayload && typeof safePayload === 'object') {
                delete safePayload.url;
                delete safePayload.method;
                delete safePayload.headers;
            }
            return q.enqueue({
                action: action,
                module: moduleName,
                payload: safePayload
            }).then(function (result) {
                return {
                    ok: true,
                    offline: true,
                    queued: true,
                    status: 202,
                    json: function () {
                        return Promise.resolve({
                            ok: true,
                            offline: true,
                            queued: true,
                            result: result
                        });
                    }
                };
            });
        }

        return fetch(url, fetchInit);
    }

    root.RatebOfflineTransport = {
        configure: function (opts) {
            opts = opts || {};
            if (typeof opts.enabled === 'boolean') {
                enabled = opts.enabled;
            }
            if (opts.rsActions && typeof opts.rsActions === 'object') {
                Object.keys(opts.rsActions).forEach(function (k) {
                    rsActions[k] = !!opts.rsActions[k];
                });
            }
        },
        isEnabled: function () { return enabled; },
        shouldQueue: shouldQueue,
        request: request,
        fetch: request
    };
})(typeof window !== 'undefined' ? window : globalThis);

