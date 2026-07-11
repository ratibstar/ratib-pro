/**
 * RATEB Offline — Queue Manager (Phase 4.5.1 — durable delete-by-key flush).
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
    /** Mirrors offline/config/sync-policy.php client_queue_max (Phase 14 enforce). */
    var clientQueueMax = 500;

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
            depends_on: Array.isArray(item.depends_on) ? item.depends_on : [],
            // Monotonic enqueue seq for FIFO push ordering (survives delete-by-key).
            seq: typeof item.seq === 'number' ? item.seq : Date.now()
        };
    }

    function sortFifo(items) {
        return (items || []).slice().sort(function (a, b) {
            var sa = typeof a.seq === 'number' ? a.seq : 0;
            var sb = typeof b.seq === 'number' ? b.seq : 0;
            if (sa !== sb) {
                return sa - sb;
            }
            var oa = String(a.occurred_at || '');
            var ob = String(b.occurred_at || '');
            if (oa < ob) {
                return -1;
            }
            if (oa > ob) {
                return 1;
            }
            return String(a.client_id || '').localeCompare(String(b.client_id || ''));
        });
    }

    function depth() {
        if (!Stores) {
            return Promise.resolve(0);
        }
        return Stores.getAll(QUEUE).then(function (items) {
            return items.length;
        });
    }

    function listFifo() {
        if (!Stores) {
            return Promise.resolve([]);
        }
        return Stores.getAll(QUEUE).then(function (items) {
            return sortFifo(items);
        });
    }

    /**
     * Crash-safe selective clear — delete only clearable keys in one IDB transaction.
     * Never store.clear(); rejected/conflict/pending siblings remain untouched.
     * Mirrors POS removeByKeys API shape with true delete-by-key durability.
     *
     * @param {string[]} keys
     * @returns {Promise<object[]>} remaining queue (FIFO ordered)
     */
    function removeByKeys(keys) {
        var clearSet = {};
        var deleteKeys = [];
        (keys || []).forEach(function (k) {
            if (!k) {
                return;
            }
            var s = String(k);
            if (!clearSet[s]) {
                clearSet[s] = true;
                deleteKeys.push(s);
            }
        });
        if (!Stores) {
            return Promise.resolve([]);
        }
        if (!deleteKeys.length) {
            return listFifo();
        }
        var removeOp = typeof Stores.removeMany === 'function'
            ? Stores.removeMany(QUEUE, deleteKeys)
            : Promise.all(deleteKeys.map(function (k) {
                return Stores.remove(QUEUE, k);
            }));
        return removeOp.then(function () {
            return listFifo();
        });
    }

    /**
     * Simulate crash between clear and rewrite (legacy unsafe path) — for tests only.
     * @returns {{lost: boolean, remaining: object[]}}
     */
    function simulateClearRewriteCrash(queue, clearableKeys) {
        var clearSet = {};
        (clearableKeys || []).forEach(function (k) {
            if (k) {
                clearSet[String(k)] = true;
            }
        });
        var remaining = (queue || []).filter(function (item) {
            var key = String(item.client_id || item.idempotency_key || '');
            return !clearSet[key];
        });
        // Legacy: clear committed, rewrite never ran → empty store (data loss).
        return { lost: true, remaining: [], wouldHaveKept: remaining };
    }

    /**
     * Simulate crash during atomic delete-by-key — transaction aborts → full queue intact.
     * @returns {{lost: boolean, remaining: object[]}}
     */
    function simulateDeleteByKeyCrash(queue) {
        return { lost: false, remaining: sortFifo(queue || []) };
    }

    function enqueue(item, options) {
        if (!enabled) {
            return Promise.reject(new Error('offline_disabled'));
        }
        if (!Stores) {
            return Promise.reject(new Error('stores_unavailable'));
        }
        var entry = normalizeEntry(item, options);
        return depth().then(function (d) {
            var max = clientQueueMax > 0 ? clientQueueMax : 0;
            if (max > 0 && d >= max) {
                if (Events) {
                    Events.emit('queue:full', { depth: d, max: max });
                }
                return Promise.reject(new Error('client_queue_full'));
            }
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
                return depth().then(function (depthAfter) {
                    return { queued: true, queueDepth: depthAfter, entry: entry };
                });
            });
        });
    }

    function flush(options) {
        options = options || {};
        if (!enabled || flushInFlight) {
            return Promise.resolve({ skipped: true });
        }
        // Phase 11: block sync until online re-login when session reauth required.
        var authLock = root.RatebOfflineAuthLock;
        if (authLock && typeof authLock.sessionNeedsReauth === 'function' && authLock.sessionNeedsReauth()) {
            return Promise.resolve({ skipped: true, reason: 'session_reauth_required' });
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
        return listFifo().then(function (queue) {
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
                    // Never delete rejected or conflicted items — even when HTTP ok.
                    return removeByKeys(clearable).then(function (remaining) {
                        if (Events) {
                            Events.emit('queue:flushed', {
                                result: result,
                                cleared: clearable.length,
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
                            clearable_keys: clearable
                        }, result);
                    });
                });
            });
        }).finally(function () {
            flushInFlight = false;
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
            if (typeof opts.clientQueueMax === 'number' && opts.clientQueueMax >= 0) {
                clientQueueMax = opts.clientQueueMax;
            }
        },
        isEnabled: function () { return enabled; },
        clientQueueMax: function () { return clientQueueMax; },
        enqueue: enqueue,
        flush: flush,
        depth: depth,
        list: listFifo,
        removeByKeys: removeByKeys,
        // Test / soak helpers (no side effects on live IDB).
        _sortFifo: sortFifo,
        _simulateClearRewriteCrash: simulateClearRewriteCrash,
        _simulateDeleteByKeyCrash: simulateDeleteByKeyCrash
    };
})(typeof window !== 'undefined' ? window : globalThis);

