/**
 * RATEB Offline — Queue Manager (Phase 2A).
 */
(function (root) {
    'use strict';

    var Schema = root.RatebOfflineSchema;
    var Stores = root.RatebOfflineStores;
    var Idem = root.RatebOfflineIdempotency;
    var Events = root.RatebOfflineEvents;
    var QUEUE = Schema ? Schema.STORES.SYNC_QUEUE : 'sync_queue';
    var flushInFlight = false;
    var apiBase = null;
    var enabled = false;

    function csrfToken() {
        if (typeof document === 'undefined') {
            return '';
        }
        var meta = document.querySelector('meta[name="rateb-csrf"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function joinUrlPath(base, pathSuffix) {
        base = String(base || '');
        pathSuffix = String(pathSuffix || '');
        if (!pathSuffix) {
            return base;
        }
        if (pathSuffix.charAt(0) !== '/') {
            pathSuffix = '/' + pathSuffix;
        }
        try {
            var u = new URL(base, typeof location !== 'undefined' ? location.href : 'http://localhost');
            u.pathname = u.pathname.replace(/\/$/, '') + pathSuffix;
            return u.toString();
        } catch (e) {
            var q = base.indexOf('?');
            if (q >= 0) {
                return base.slice(0, q).replace(/\/$/, '') + pathSuffix + base.slice(q);
            }
            return base.replace(/\/$/, '') + pathSuffix;
        }
    }

    function normalizeEntry(item, options) {
        options = options || {};
        var clientId = item.client_id || (Idem ? Idem.randomId() : ('local-' + Date.now()));
        var payload = item.payload || {};
        if (payload && typeof payload === 'object') {
            payload = Object.assign({}, payload);
            delete payload.url;
            delete payload.method;
            delete payload.headers;
        }
        return {
            client_id: clientId,
            idempotency_key: item.idempotency_key || clientId,
            module: item.module || options.module || 'offline_meta',
            action: item.action || 'offline.ack',
            payload: payload,
            occurred_at: item.occurred_at || new Date().toISOString(),
            version: item.version || 1,
            status: 'pending',
            retry_count: 0,
            depends_on: Array.isArray(item.depends_on) ? item.depends_on : []
        };
    }

    function depth() {
        if (!Stores) {
            return Promise.resolve(0);
        }
        return Stores.getAll(QUEUE).then(function (items) {
            return items.length;
        });
    }

    function enqueue(item, options) {
        if (!enabled) {
            return Promise.reject(new Error('offline_disabled'));
        }
        if (!Stores) {
            return Promise.reject(new Error('stores_unavailable'));
        }
        var entry = normalizeEntry(item, options);
        return Stores.put(QUEUE, entry).then(function () {
            if (Events) {
                Events.emit('queue:enqueued', entry);
            }
            var conn = root.RatebOfflineConnectivity;
            if (conn && conn.isOnline()) {
                return flush(options).then(function (result) {
                    return Object.assign({ queued: true, entry: entry }, result || {});
                });
            }
            return depth().then(function (d) {
                return { queued: true, queueDepth: d, entry: entry };
            });
        });
    }

    function flush(options) {
        options = options || {};
        if (!enabled || flushInFlight) {
            return Promise.resolve({ skipped: true });
        }
        if (!Stores) {
            return Promise.resolve({ accepted: 0 });
        }
        var conn = root.RatebOfflineConnectivity;
        if (conn && !conn.isOnline()) {
            return Promise.resolve({ offline: true });
        }
        flushInFlight = true;
        var base = options.apiBase || apiBase;
        return Stores.getAll(QUEUE).then(function (queue) {
            if (!queue.length) {
                return { accepted: 0, queueDepth: 0 };
            }
            if (!base) {
                return { error: 'api_base_missing', queueDepth: queue.length };
            }
            return fetch(joinUrlPath(base, '/push'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-Token': csrfToken()
                },
                body: JSON.stringify({
                    device_id: options.deviceId || '',
                    branch_id: options.branchId || 0,
                    items: queue
                })
            }).then(function (res) {
                return res.json().then(function (payload) {
                    var result = (payload && payload.result) ? payload.result : {};
                    var clearable = Array.isArray(result.clearable_keys) ? result.clearable_keys : [];
                    if (!clearable.length && payload && payload.ok) {
                        // Backward-compatible fallback: only accepted + duplicate keys.
                        clearable = [].concat(
                            Array.isArray(result.accepted_keys) ? result.accepted_keys : [],
                            Array.isArray(result.duplicate_keys) ? result.duplicate_keys : []
                        );
                    }
                    // Never clear rejected or conflicted items — even when HTTP ok.
                    var clearSet = {};
                    clearable.forEach(function (k) {
                        if (k) {
                            clearSet[String(k)] = true;
                        }
                    });
                    return Stores.getAll(QUEUE).then(function (current) {
                        var remaining = (current || []).filter(function (item) {
                            var key = String(item.client_id || item.idempotency_key || '');
                            return !clearSet[key];
                        });
                        return Stores.clear(QUEUE).then(function () {
                            return Stores.putMany ? Stores.putMany(QUEUE, remaining) : writeRemaining(remaining);
                        }).then(function () {
                            if (Events) {
                                Events.emit('queue:flushed', {
                                    result: result,
                                    cleared: Object.keys(clearSet).length,
                                    remaining: remaining.length,
                                    ok: !!(payload && payload.ok)
                                });
                            }
                            if (!payload || !payload.ok) {
                                var err = new Error(
                                    (payload && payload.error && payload.error.message) || 'sync_failed'
                                );
                                err.result = result;
                                err.remaining = remaining.length;
                                throw err;
                            }
                            return Object.assign({
                                queueDepth: remaining.length,
                                clearable_keys: Object.keys(clearSet)
                            }, result);
                        });
                    });
                });
            });
        }).finally(function () {
            flushInFlight = false;
        });
    }

    function writeRemaining(items) {
        if (!Stores || !Schema) {
            return Promise.resolve();
        }
        return Schema.openDatabase().then(function (db) {
            return new Promise(function (resolve, reject) {
                var tx = db.transaction(QUEUE, 'readwrite');
                var store = tx.objectStore(QUEUE);
                (items || []).forEach(function (item) {
                    store.put(item);
                });
                tx.oncomplete = function () { resolve(true); };
                tx.onerror = function () { reject(tx.error || new Error('idb_rewrite_failed')); };
            });
        });
    }

    root.RatebOfflineQueue = {
        configure: function (opts) {
            opts = opts || {};
            if (typeof opts.enabled === 'boolean') {
                enabled = opts.enabled;
            }
            if (opts.apiBase) {
                apiBase = String(opts.apiBase);
            }
        },
        isEnabled: function () { return enabled; },
        enqueue: enqueue,
        flush: flush,
        depth: depth,
        list: function () {
            return Stores ? Stores.getAll(QUEUE) : Promise.resolve([]);
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);
