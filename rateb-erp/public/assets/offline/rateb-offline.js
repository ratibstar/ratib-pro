/*! RATEB Enterprise Offline SDK Phase 14.2.0 (includes Phase 5.0.0 + Phases 10-14.2 GRN + Phase 15B Recruitment; flags default OFF). */

/* ---- schema.js ---- */
/**
 * RATEB Offline — IndexedDB schema (Phase 11: DB_VERSION 2 + auth_vault).
 * DB: rateb_erp_offline (separate from rateb_pos_offline / rateb_pos_auth_lock).
 */
(function (root) {
    'use strict';

    var DB_NAME = 'rateb_erp_offline';
    var DB_VERSION = 2;

    var STORES = {
        SYNC_QUEUE: 'sync_queue',
        SYNC_META: 'sync_meta',
        ENTITY_CACHE: 'entity_cache',
        CATALOG_INDEX: 'catalog_index',
        FORM_DRAFTS: 'form_drafts',
        SNAPSHOTS: 'snapshots',
        CONFLICTS: 'conflicts',
        CURSORS: 'cursors',
        AUTH_VAULT: 'auth_vault'
    };

    function keyPathForStore(name) {
        if (name === 'sync_queue') {
            return 'client_id';
        }
        if (name === 'sync_meta' || name === 'cursors') {
            return 'key';
        }
        if (name === 'form_drafts') {
            return 'draft_id';
        }
        if (name === 'conflicts') {
            return 'conflict_id';
        }
        // entity_cache, catalog_index, snapshots, auth_vault
        return 'id';
    }

    function openDatabase() {
        return new Promise(function (resolve, reject) {
            if (!root.indexedDB) {
                reject(new Error('indexeddb_unavailable'));
                return;
            }
            var req = root.indexedDB.open(DB_NAME, DB_VERSION);
            req.onupgradeneeded = function () {
                var db = req.result;
                Object.keys(STORES).forEach(function (key) {
                    var name = STORES[key];
                    if (db.objectStoreNames.contains(name)) {
                        return;
                    }
                    db.createObjectStore(name, { keyPath: keyPathForStore(name) });
                });
            };
            req.onsuccess = function () { resolve(req.result); };
            req.onerror = function () { reject(req.error || new Error('idb_open_failed')); };
        });
    }

    function withStore(storeName, mode, fn) {
        return openDatabase().then(function (db) {
            return new Promise(function (resolve, reject) {
                var tx = db.transaction(storeName, mode);
                var store = tx.objectStore(storeName);
                var out;
                try {
                    out = fn(store);
                } catch (e) {
                    reject(e);
                    return;
                }
                if (out && typeof out.then === 'function') {
                    out.then(resolve).catch(reject);
                } else {
                    tx.oncomplete = function () { resolve(out); };
                }
                tx.onerror = function () { reject(tx.error || new Error('idb_tx_failed')); };
            });
        });
    }

    root.RatebOfflineSchema = {
        DB_NAME: DB_NAME,
        DB_VERSION: DB_VERSION,
        STORES: STORES,
        openDatabase: openDatabase,
        withStore: withStore
    };
})(typeof window !== 'undefined' ? window : globalThis);

/* ---- migrations.js ---- */
/**
 * RATEB Offline — IndexedDB store helpers (Phase 2A).
 */
(function (root) {
    'use strict';

    var Schema = root.RatebOfflineSchema;
    if (!Schema) {
        return;
    }

    function getAll(storeName) {
        return Schema.withStore(storeName, 'readonly', function (store) {
            return new Promise(function (resolve, reject) {
                var req = store.getAll();
                req.onsuccess = function () { resolve(req.result || []); };
                req.onerror = function () { reject(req.error); };
            });
        });
    }

    function put(storeName, record) {
        return Schema.withStore(storeName, 'readwrite', function (store) {
            store.put(record);
            return true;
        });
    }

    function putMany(storeName, records) {
        if (!Array.isArray(records) || !records.length) {
            return Promise.resolve(0);
        }
        return Schema.openDatabase().then(function (db) {
            return new Promise(function (resolve, reject) {
                var tx = db.transaction(storeName, 'readwrite');
                var store = tx.objectStore(storeName);
                var n = 0;
                records.forEach(function (r) {
                    if (r) {
                        store.put(r);
                        n += 1;
                    }
                });
                tx.oncomplete = function () { resolve(n); };
                tx.onerror = function () { reject(tx.error || new Error('idb_put_many_failed')); };
            });
        });
    }

    function clear(storeName) {
        return Schema.withStore(storeName, 'readwrite', function (store) {
            store.clear();
            return true;
        });
    }

    function remove(storeName, key) {
        return Schema.withStore(storeName, 'readwrite', function (store) {
            store.delete(key);
            return true;
        });
    }

    /**
     * Atomically delete many keys in a single IndexedDB transaction.
     * Crash mid-tx rolls back all deletes — remaining rows are never wiped.
     *
     * @param {string} storeName
     * @param {string[]} keys
     * @returns {Promise<number>} number of delete ops issued
     */
    function removeMany(storeName, keys) {
        var list = [];
        (keys || []).forEach(function (k) {
            if (k !== null && k !== undefined && String(k) !== '') {
                list.push(String(k));
            }
        });
        if (!list.length) {
            return Promise.resolve(0);
        }
        return Schema.openDatabase().then(function (db) {
            return new Promise(function (resolve, reject) {
                var tx = db.transaction(storeName, 'readwrite');
                var store = tx.objectStore(storeName);
                list.forEach(function (k) {
                    store.delete(k);
                });
                tx.oncomplete = function () { resolve(list.length); };
                tx.onerror = function () { reject(tx.error || new Error('idb_remove_many_failed')); };
                tx.onabort = function () { reject(tx.error || new Error('idb_remove_many_aborted')); };
            });
        });
    }

    function get(storeName, key) {
        return Schema.withStore(storeName, 'readonly', function (store) {
            return new Promise(function (resolve, reject) {
                var req = store.get(key);
                req.onsuccess = function () { resolve(req.result || null); };
                req.onerror = function () { reject(req.error); };
            });
        });
    }

    root.RatebOfflineStores = {
        getAll: getAll,
        get: get,
        put: put,
        putMany: putMany,
        clear: clear,
        remove: remove,
        removeMany: removeMany
    };
})(typeof window !== 'undefined' ? window : globalThis);

/* ---- idempotency.js ---- */
/**
 * RATEB Offline — Idempotency helpers (Phase 2A).
 */
(function (root) {
    'use strict';

    function randomId() {
        if (root.crypto && typeof root.crypto.randomUUID === 'function') {
            return root.crypto.randomUUID();
        }
        return 'local-' + Date.now() + '-' + Math.random().toString(36).slice(2, 10);
    }

    function buildKey(parts) {
        var raw = (parts || []).map(function (p) {
            return String(p == null ? '' : p);
        }).join('|');
        var hash = 0;
        for (var i = 0; i < raw.length; i += 1) {
            hash = ((hash << 5) - hash) + raw.charCodeAt(i);
            hash |= 0;
        }
        var hex = (hash >>> 0).toString(16);
        return ('idem-' + hex + '-' + raw.replace(/[^a-zA-Z0-9_-]/g, '').slice(0, 40)).slice(0, 64);
    }

    root.RatebOfflineIdempotency = {
        randomId: randomId,
        buildKey: buildKey
    };
})(typeof window !== 'undefined' ? window : globalThis);

/* ---- event-bus.js ---- */
/**
 * RATEB Offline — Event bus (Phase 2A).
 */
(function (root) {
    'use strict';

    var handlers = {};

    root.RatebOfflineEvents = {
        on: function (event, fn) {
            if (!handlers[event]) {
                handlers[event] = [];
            }
            handlers[event].push(fn);
            return function () {
                handlers[event] = (handlers[event] || []).filter(function (x) { return x !== fn; });
            };
        },
        emit: function (event, detail) {
            (handlers[event] || []).forEach(function (fn) {
                try { fn(detail); } catch (e) { /* ignore */ }
            });
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);

/* ---- connectivity.js ---- */
/**
 * RATEB Offline — Connectivity Manager (Phase 2A).
 */
(function (root) {
    'use strict';

    var listeners = [];
    var online = typeof navigator !== 'undefined' ? navigator.onLine !== false : true;
    var probeTimer = null;
    var probing = false;
    var probeUrl = null;
    var intervals = { online: 12000, offline: 20000 };
    var timeoutMs = 3500;

    function emit() {
        listeners.forEach(function (fn) {
            try { fn(online); } catch (e) { /* ignore */ }
        });
        try {
            if (typeof document !== 'undefined') {
                document.dispatchEvent(new CustomEvent('rateb-offline-connectivity', {
                    detail: { online: online }
                }));
            }
        } catch (e2) { /* ignore */ }
    }

    function setOnline(next) {
        next = !!next;
        if (online === next) {
            return;
        }
        online = next;
        emit();
        scheduleProbeLoop();
        if (online && root.RatebOfflineQueue && typeof root.RatebOfflineQueue.flush === 'function') {
            root.RatebOfflineQueue.flush().catch(function () { /* retry later */ });
        }
    }

    function probe() {
        if (probing) {
            return Promise.resolve(online);
        }
        if (typeof navigator !== 'undefined' && navigator.onLine === false) {
            setOnline(false);
            return Promise.resolve(false);
        }
        if (!probeUrl) {
            return Promise.resolve(online);
        }
        probing = true;
        var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var timer = setTimeout(function () {
            if (ctrl) {
                try { ctrl.abort(); } catch (e) { /* ignore */ }
            }
        }, timeoutMs);
        return fetch(probeUrl, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json', 'X-Rateb-Connectivity': '1' },
            signal: ctrl ? ctrl.signal : undefined
        }).then(function (res) {
            if (res && (res.ok || res.status === 401 || res.status === 403 || res.status === 419)) {
                setOnline(true);
                return true;
            }
            return online;
        }).catch(function () {
            setOnline(false);
            return false;
        }).finally(function () {
            clearTimeout(timer);
            probing = false;
        });
    }

    function scheduleProbeLoop() {
        if (typeof setInterval === 'undefined') {
            return;
        }
        if (probeTimer) {
            clearInterval(probeTimer);
            probeTimer = null;
        }
        var every = online ? intervals.online : intervals.offline;
        probeTimer = setInterval(function () {
            if (typeof navigator !== 'undefined' && navigator.onLine === false) {
                setOnline(false);
                return;
            }
            probe();
        }, every);
    }

    root.RatebOfflineConnectivity = {
        isOnline: function () { return online; },
        setOnline: setOnline,
        probe: probe,
        setProbeUrl: function (url) {
            probeUrl = url ? String(url) : null;
        },
        configure: function (opts) {
            opts = opts || {};
            if (opts.probeUrl) {
                probeUrl = String(opts.probeUrl);
            }
            if (opts.onlineIntervalMs) {
                intervals.online = opts.onlineIntervalMs;
            }
            if (opts.offlineIntervalMs) {
                intervals.offline = opts.offlineIntervalMs;
            }
            if (opts.timeoutMs) {
                timeoutMs = opts.timeoutMs;
            }
        },
        subscribe: function (fn) {
            if (typeof fn !== 'function') {
                return function () {};
            }
            listeners.push(fn);
            try { fn(online); } catch (e) { /* ignore */ }
            return function () {
                listeners = listeners.filter(function (x) { return x !== fn; });
            };
        },
        start: function () {
            if (typeof window !== 'undefined') {
                window.addEventListener('online', function () { probe(); });
                window.addEventListener('offline', function () { setOnline(false); });
            }
            scheduleProbeLoop();
            return probe();
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);

/* ---- queue-manager.js ---- */
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


/* ---- replay-scheduler.js ---- */
/**
 * RATEB Offline — Replay scheduler stub (Phase 2A).
 * Full module replay lands in later phases; here we only flush the client queue.
 */
(function (root) {
    'use strict';

    var timer = null;

    root.RatebOfflineReplayScheduler = {
        start: function (intervalMs) {
            if (typeof setInterval === 'undefined') {
                return;
            }
            this.stop();
            timer = setInterval(function () {
                var q = root.RatebOfflineQueue;
                var c = root.RatebOfflineConnectivity;
                if (q && q.isEnabled() && c && c.isOnline()) {
                    q.flush().catch(function () { /* ignore */ });
                }
            }, Math.max(5000, intervalMs || 15000));
        },
        stop: function () {
            if (timer) {
                clearInterval(timer);
                timer = null;
            }
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);

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

/* ---- pos-adapter.js ---- */
/**
 * RATEB Offline — POS adapter (Phase 2A).
 * Delegates to existing RatebPosOffline when present; does not modify POS logic.
 */
(function (root) {
    'use strict';

    root.RatebOfflinePosAdapter = {
        isAvailable: function () {
            return !!(root.RatebPosOffline && typeof root.RatebPosOffline.push === 'function');
        },
        pushCheckout: function (payload, options) {
            if (!this.isAvailable()) {
                return Promise.reject(new Error('pos_offline_unavailable'));
            }
            return root.RatebPosOffline.push({
                action: 'checkout',
                payload: payload || {},
                version: (options && options.version) || 1
            }, options || {});
        },
        sync: function (options) {
            if (!this.isAvailable() || typeof root.RatebPosOffline.sync !== 'function') {
                return Promise.resolve({ skipped: true });
            }
            return root.RatebPosOffline.sync(options || {});
        },
        queueDepth: function () {
            if (!this.isAvailable()) {
                return Promise.resolve(0);
            }
            return Promise.resolve(root.RatebPosOffline.queueDepth || 0);
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);

/* ---- inventory-adapter.js ---- */
/**
 * RATEB Offline — Inventory adapter (Phase 3 / Tier 1).
 * Queues stock movements, stock counts, and warehouse transfers via enterprise offline queue.
 * Activated only when offline.enabled + offline.inventory.movements are true.
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
        return !!(f['offline.enabled'] && f['offline.inventory.movements']);
    }

    function makeClientId(prefix) {
        var rand = Math.random().toString(36).slice(2, 10);
        return String(prefix || 'inv') + '-' + Date.now() + '-' + rand;
    }

    function enqueue(action, payload, options) {
        options = options || {};
        if (!isActive()) {
            return Promise.reject(new Error('inventory_offline_disabled'));
        }
        var q = root.RatebOfflineQueue;
        if (!q || typeof q.enqueue !== 'function') {
            return Promise.reject(new Error('offline_queue_unavailable'));
        }
        var clientId = options.client_id || options.idempotency_key || makeClientId(action);
        return q.enqueue({
            client_id: clientId,
            idempotency_key: clientId,
            module: 'inventory',
            action: action,
            payload: payload || {},
            version: options.version || 1,
            occurred_at: options.occurred_at || new Date().toISOString()
        });
    }

    function pullCatalog(options) {
        options = options || {};
        if (!isActive()) {
            return Promise.resolve({ items: [], stub: true, disabled: true });
        }
        var pull = root.RatebOfflineDeltaPull;
        if (!pull || typeof pull.pull !== 'function') {
            return Promise.reject(new Error('delta_pull_unavailable'));
        }
        return pull.pull('inventory_catalog', options).then(function (res) {
            var delta = (res && res.delta) ? res.delta : res;
            if (delta && Array.isArray(delta.items) && root.RatebOfflineSchema) {
                return root.RatebOfflineSchema.withStore(
                    root.RatebOfflineSchema.STORES.CATALOG_INDEX,
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
                                var id = cid + ':' + bid + ':inv:' + item.id;
                                try { store.delete('inv:' + item.id); } catch (e) { /* legacy */ }
                                store.put({
                                    id: id,
                                    entity: 'inventory_catalog',
                                    company_id: cid,
                                    branch_id: bid,
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

    root.RatebOfflineInventoryAdapter = {
        isActive: isActive,
        enqueueMovement: function (payload, options) {
            return enqueue('stock_movement.create', payload || {}, options);
        },
        enqueueStockCount: function (payload, options) {
            return enqueue('stock_count.create', payload || {}, options);
        },
        enqueueWarehouseTransfer: function (payload, options) {
            return enqueue('warehouse_transfer.create', payload || {}, options);
        },
        enqueueTransferApprove: function (payload, options) {
            return enqueue('warehouse_transfer.approve', payload || {}, options);
        },
        pullCatalog: pullCatalog,
        sync: function (options) {
            options = options || {};
            if (!isActive()) {
                return Promise.resolve({ skipped: true, disabled: true });
            }
            var q = root.RatebOfflineQueue;
            var flush = (q && typeof q.flush === 'function') ? q.flush() : Promise.resolve({ skipped: true });
            return flush.then(function (flushResult) {
                return pullCatalog(options).then(function (catalog) {
                    return { flush: flushResult, catalog: catalog };
                });
            });
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);

/* ---- hr-adapter.js ---- */
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

/* ---- procurement-adapter.js ---- */
/**
 * RATEB Offline — Procurement adapter (Phase 5 / Tier 1 + Phase 14.2 GRN).
 * Queues PR / RFQ / PO drafts via enterprise offline queue.
 * GRN (goods_receipt.receive) requires offline.procurement.goods_receipt.
 * Activated only when offline.enabled + offline.procurement are true.
 * Does NOT enqueue approvals, payments, or accounting posting directly.
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
        return !!(f['offline.enabled'] && f['offline.procurement']);
    }

    function isGoodsReceiptActive() {
        var f = flags();
        return !!(isActive() && f['offline.procurement.goods_receipt']);
    }

    function makeClientId(prefix) {
        var rand = Math.random().toString(36).slice(2, 10);
        return String(prefix || 'proc') + '-' + Date.now() + '-' + rand;
    }

    function enqueue(action, payload, options) {
        options = options || {};
        if (!isActive()) {
            return Promise.reject(new Error('procurement_offline_disabled'));
        }
        if (action === 'goods_receipt.receive' && !isGoodsReceiptActive()) {
            return Promise.reject(new Error('procurement_grn_offline_disabled'));
        }
        var q = root.RatebOfflineQueue;
        if (!q || typeof q.enqueue !== 'function') {
            return Promise.reject(new Error('offline_queue_unavailable'));
        }
        var clientId = options.client_id || options.idempotency_key || makeClientId(action);
        return q.enqueue({
            client_id: clientId,
            idempotency_key: clientId,
            module: 'procurement',
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
        return pull.pull('supplier_directory', options).then(function (res) {
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
                                var id = cid + ':' + bid + ':sup:' + item.id;
                                try { store.delete('sup:' + item.id); } catch (e) { /* legacy */ }
                                if (item.deleted || item.active === false) {
                                    store.delete(id);
                                    return;
                                }
                                store.put({
                                    id: id,
                                    entity: 'supplier_directory',
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

    root.RatebOfflineProcurementAdapter = {
        isActive: isActive,
        isGoodsReceiptActive: isGoodsReceiptActive,
        enqueuePurchaseRequestDraft: function (payload, options) {
            return enqueue('purchase_request.draft', payload || {}, options);
        },
        enqueueRfqDraft: function (payload, options) {
            return enqueue('rfq.draft', payload || {}, options);
        },
        enqueuePurchaseOrderDraft: function (payload, options) {
            return enqueue('purchase_order.draft', payload || {}, options);
        },
        enqueueGoodsReceipt: function (payload, options) {
            return enqueue('goods_receipt.receive', payload || {}, options);
        },
        pullSupplierDirectory: pullDirectory,
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

/* ---- form-post-adapter.js ---- */
/**
 * RATEB Offline — Form POST adapter stub (Phase 2A — not activated).
 */
(function (root) {
    'use strict';

    root.RatebOfflineFormPostAdapter = {
        isActive: function () { return false; },
        capture: function () {
            return Promise.reject(new Error('form_post_offline_not_implemented'));
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);

/* ---- shell-adapter.js ---- */
/**
 * RATEB Offline — ERP shell adapter (Phase 10.1 + Phase 14 ops pages).
 * Tenant-scoped snapshots; strips privileged UI + secrets.
 * Phase 14: allowlisted ops page snapshots (browse) when pilot.ops_pages is on.
 */
(function (root) {
    'use strict';

    var SNAPSHOT_PREFIX = 'erp_shell_chrome';
    var OPS_PAGE_PREFIX = 'erp_ops_page';
    var OPS_CACHE = 'rateb-erp-ops-pages-v14';

    function flags() {
        if (root.RatebOffline && typeof root.RatebOffline.flags === 'function') {
            return root.RatebOffline.flags() || {};
        }
        return {};
    }

    function isActive() {
        var f = flags();
        return !!(f['offline.enabled'] && f['offline.read_cache']);
    }

    function isOpsPagesActive() {
        var f = flags();
        return !!(f['offline.enabled'] && f['offline.read_cache'] && f['offline.pilot.ops_pages']);
    }

    function tenantScope() {
        var cfg = root.__RATEB_ERP_SHELL_OFFLINE__ || {};
        var companyId = parseInt(cfg.company_id, 10) || 0;
        var branchId = parseInt(cfg.branch_id, 10) || 0;
        var userId = parseInt(cfg.user_id, 10) || 0;
        return {
            company_id: companyId,
            branch_id: branchId,
            user_id: userId
        };
    }

    function snapshotId(scope) {
        scope = scope || tenantScope();
        if (!scope.company_id || !scope.user_id) {
            return null;
        }
        return SNAPSHOT_PREFIX
            + ':' + scope.company_id
            + ':' + scope.branch_id
            + ':' + scope.user_id;
    }

    function stripSensitive(html) {
        var out = String(html || '');
        // CSRF / tokens
        out = out.replace(/<meta[^>]*name=["']rateb-csrf["'][^>]*>/gi, '');
        out = out.replace(/name=["']_csrf["'][^>]*>/gi, '>');
        out = out.replace(/\svalue=["'][^"']*["'](?=[^>]*name=["']_csrf["'])/gi, ' value=""');
        // All scripts (theme applied by offline-shell.html itself)
        out = out.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '');
        // Privileged / dynamic chrome
        out = out.replace(/<main\b[^>]*>[\s\S]*?<\/main>/i,
            '<main class="rateb-offline-shell-main" id="rateb-offline-shell-main">'
            + '<div class="container py-4">'
            + '<p class="text-muted">وضع عدم الاتصال — أعد الاتصال لعرض البيانات الحية والتعديل.</p>'
            + '<p class="text-muted small">Offline shell — reconnect for live data and edits.</p>'
            + '</div></main>');
        out = out.replace(/<aside\b[^>]*>[\s\S]*?<\/aside>/gi,
            '<aside class="rateb-offline-shell-nav" aria-label="Offline nav"><p>RATEB ERP</p></aside>');
        out = out.replace(/<nav\b[^>]*>[\s\S]*?<\/nav>/gi, '<nav class="rateb-offline-shell-nav"></nav>');
        // Force connection badge to Offline (never freeze "متصل" / Online into the cache).
        out = out.replace(/rateb-connection-indicator\s+is-online/gi, 'rateb-connection-indicator is-offline');
        out = out.replace(/(\sclass=["'][^"']*rateb-connection-indicator)(?![^"']*is-offline)/gi,
            '$1 is-offline');
        out = out.replace(/data-label-online=["'][^"']*["']/gi, 'data-label-online="Online"');
        out = out.replace(/(rateb-connection-indicator__label">)\s*[^<]*/gi, '$1غير متصل');
        out = out.replace(/(title|aria-label)=["']\s*(متصل|Online)\s*["']/gi, '$1="غير متصل"');
        // data-* that may leak URLs / session context
        out = out.replace(/\sdata-rateb-[a-z0-9_-]+=["'][^"']*["']/gi, '');
        out = out.replace(/\sdata-(csrf|token|session|user|company|branch)[a-z0-9_-]*=["'][^"']*["']/gi, '');
        // Forms / alerts / badges (counts, PII surfaces)
        out = out.replace(/<form\b[^>]*>[\s\S]*?<\/form>/gi, '');
        out = out.replace(/<div[^>]*class=["'][^"']*\balert\b[^"']*["'][^>]*>[\s\S]*?<\/div>/gi, '');
        out = out.replace(/<span[^>]*class=["'][^"']*badge[^"']*["'][^>]*>[\s\S]*?<\/span>/gi, '');
        // Inline event handlers
        out = out.replace(/\son[a-z]+\s*=\s*["'][^"']*["']/gi, '');
        out = out.replace(/\son[a-z]+\s*=\s*[^\s>]+/gi, '');
        // javascript: URLs
        out = out.replace(/\shref=["']\s*javascript:[^"']*["']/gi, ' href="#"');
        return out;
    }

    /** Phase 14 — keep main content for browse; still strip secrets / scripts / CSRF. */
    function stripSensitiveOpsPage(html) {
        var out = String(html || '');
        out = out.replace(/<meta[^>]*name=["']rateb-csrf["'][^>]*>/gi, '');
        out = out.replace(/name=["']_csrf["'][^>]*>/gi, '>');
        out = out.replace(/\svalue=["'][^"']*["'](?=[^>]*name=["']_csrf["'])/gi, ' value=""');
        out = out.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '');
        out = out.replace(/rateb-connection-indicator\s+is-online/gi, 'rateb-connection-indicator is-offline');
        out = out.replace(/(\sclass=["'][^"']*rateb-connection-indicator)(?![^"']*is-offline)/gi,
            '$1 is-offline');
        out = out.replace(/(rateb-connection-indicator__label">)\s*[^<]*/gi, '$1غير متصل');
        out = out.replace(/(title|aria-label)=["']\s*(متصل|Online)\s*["']/gi, '$1="غير متصل"');
        out = out.replace(/\sdata-rateb-[a-z0-9_-]+=["'][^"']*["']/gi, '');
        out = out.replace(/\sdata-(csrf|token|session)[a-z0-9_-]*=["'][^"']*["']/gi, '');
        out = out.replace(/\son[a-z]+\s*=\s*["'][^"']*["']/gi, '');
        out = out.replace(/\son[a-z]+\s*=\s*[^\s>]+/gi, '');
        out = out.replace(/\shref=["']\s*javascript:[^"']*["']/gi, ' href="#"');
        // Disable forms in cached browse snapshots (writes use live-page hooks).
        out = out.replace(/<form\b/gi, '<form data-rateb-offline-browse="1" onsubmit="return false;" ');
        out = out.replace(
            /<main\b([^>]*)>/i,
            '<main$1><div class="alert alert-warning m-3" role="status">'
            + 'وضع عدم الاتصال — صفحة محفوظة للتصفح. التعديل يتطلب اتصال أو نموذج حي قبل انقطاع الشبكة.'
            + '</div>'
        );
        return out;
    }

    function opsAllowlist() {
        var cfg = root.__RATEB_ERP_SHELL_OFFLINE__ || {};
        var paths = cfg.ops_page_paths;
        return Array.isArray(paths) ? paths : [];
    }

    function matchOpsPath(pathname) {
        var p = String(pathname || '').replace(/\/+$/, '').toLowerCase();
        var list = opsAllowlist();
        for (var i = 0; i < list.length; i++) {
            var a = String(list[i] || '').replace(/^\/+|\/+$/g, '').toLowerCase();
            if (!a) {
                continue;
            }
            var re = new RegExp('(^|/)' + a.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '(/|$)', 'i');
            if (re.test(p)) {
                return a;
            }
        }
        return null;
    }

    function opsSnapshotId(pathname, scope) {
        scope = scope || tenantScope();
        var matched = matchOpsPath(pathname);
        if (!matched || !scope.company_id || !scope.user_id) {
            return null;
        }
        return OPS_PAGE_PREFIX
            + ':' + scope.company_id
            + ':' + scope.branch_id
            + ':' + scope.user_id
            + ':' + matched;
    }

    function putSnapshot(record) {
        var Schema = root.RatebOfflineSchema;
        if (!Schema || !Schema.withStore) {
            return Promise.reject(new Error('schema_unavailable'));
        }
        return Schema.withStore(Schema.STORES.SNAPSHOTS, 'readwrite', function (store) {
            store.put(record);
            return true;
        });
    }

    function getSnapshot(scope) {
        var id = snapshotId(scope);
        if (!id) {
            return Promise.resolve(null);
        }
        var Schema = root.RatebOfflineSchema;
        if (!Schema || !Schema.withStore) {
            return Promise.resolve(null);
        }
        return Schema.withStore(Schema.STORES.SNAPSHOTS, 'readonly', function (store) {
            return new Promise(function (resolve, reject) {
                var req = store.get(id);
                req.onsuccess = function () { resolve(req.result || null); };
                req.onerror = function () { reject(req.error); };
            });
        });
    }

    function putOpsPageCache(url, html) {
        if (!root.caches || !root.caches.open) {
            return Promise.resolve(false);
        }
        try {
            var res = new Response(html, {
                status: 200,
                headers: {
                    'Content-Type': 'text/html; charset=utf-8',
                    'X-Rateb-Offline': '1',
                    'X-Rateb-Ops-Page': '1',
                    'Cache-Control': 'no-store'
                }
            });
            return root.caches.open(OPS_CACHE).then(function (cache) {
                return cache.put(url, res).then(function () { return true; });
            }).catch(function () { return false; });
        } catch (e) {
            return Promise.resolve(false);
        }
    }

    function captureChrome() {
        if (!isActive()) {
            return Promise.resolve({ skipped: true, disabled: true });
        }
        var scope = tenantScope();
        var id = snapshotId(scope);
        if (!id) {
            return Promise.resolve({ skipped: true, reason: 'tenant_scope_required' });
        }
        if (!root.document || !root.document.documentElement) {
            return Promise.resolve({ skipped: true, reason: 'no_document' });
        }
        try {
            var html = '<!DOCTYPE html>\n' + root.document.documentElement.outerHTML;
            var safe = stripSensitive(html);
            var record = {
                id: id,
                kind: 'erp_shell_chrome',
                company_id: scope.company_id,
                branch_id: scope.branch_id,
                user_id: scope.user_id,
                captured_at: new Date().toISOString(),
                path: (root.location && root.location.pathname) || '',
                html: safe
            };
            return putSnapshot(record).then(function () {
                return { ok: true, id: id, bytes: safe.length };
            });
        } catch (e) {
            return Promise.reject(e);
        }
    }

    function captureOpsPage() {
        if (!isOpsPagesActive()) {
            return Promise.resolve({ skipped: true, disabled: true });
        }
        var path = (root.location && root.location.pathname) || '';
        if (!matchOpsPath(path)) {
            return Promise.resolve({ skipped: true, reason: 'path_not_allowlisted' });
        }
        var scope = tenantScope();
        var id = opsSnapshotId(path, scope);
        if (!id) {
            return Promise.resolve({ skipped: true, reason: 'tenant_scope_required' });
        }
        if (!root.document || !root.document.documentElement) {
            return Promise.resolve({ skipped: true, reason: 'no_document' });
        }
        try {
            var html = '<!DOCTYPE html>\n' + root.document.documentElement.outerHTML;
            var safe = stripSensitiveOpsPage(html);
            var href = (root.location && root.location.href) || path;
            var record = {
                id: id,
                kind: 'erp_ops_page',
                company_id: scope.company_id,
                branch_id: scope.branch_id,
                user_id: scope.user_id,
                captured_at: new Date().toISOString(),
                path: path,
                url: href,
                html: safe
            };
            return putSnapshot(record).then(function () {
                return putOpsPageCache(href, safe).then(function () {
                    var origin = (root.location && root.location.origin) || '';
                    return putOpsPageCache(origin + path, safe);
                }).then(function () {
                    try {
                        if (root.navigator && root.navigator.serviceWorker
                            && root.navigator.serviceWorker.controller) {
                            root.navigator.serviceWorker.controller.postMessage({
                                type: 'CACHE_ERP_OPS_PAGE',
                                url: href,
                                path: path,
                                html: safe
                            });
                        }
                    } catch (e) { /* ignore */ }
                    return { ok: true, id: id, bytes: safe.length, path: path };
                });
            });
        } catch (e) {
            return Promise.reject(e);
        }
    }

    function startAutoCapture() {
        if (!isActive()) {
            return;
        }
        var run = function () {
            captureChrome().catch(function () { /* ignore */ });
            if (isOpsPagesActive()) {
                captureOpsPage().catch(function () { /* ignore */ });
            }
        };
        if (root.document && root.document.readyState === 'complete') {
            setTimeout(run, 800);
        } else if (root.addEventListener) {
            root.addEventListener('load', function () {
                setTimeout(run, 800);
            }, { once: true });
        }
    }

    root.RatebOfflineShellAdapter = {
        SNAPSHOT_PREFIX: SNAPSHOT_PREFIX,
        OPS_PAGE_PREFIX: OPS_PAGE_PREFIX,
        OPS_CACHE: OPS_CACHE,
        isActive: isActive,
        isOpsPagesActive: isOpsPagesActive,
        tenantScope: tenantScope,
        snapshotId: snapshotId,
        opsSnapshotId: opsSnapshotId,
        matchOpsPath: matchOpsPath,
        captureChrome: captureChrome,
        captureOpsPage: captureOpsPage,
        getSnapshot: getSnapshot,
        startAutoCapture: startAutoCapture,
        stripSensitive: stripSensitive,
        stripSensitiveOpsPage: stripSensitiveOpsPage
    };
})(typeof window !== 'undefined' ? window : globalThis);


/* ---- auth-lock-adapter.js ---- */
/**
 * RATEB Offline — ERP auth lock adapter (Phase 11).
 * Local shell unlock only. Uses rateb_erp_offline / auth_vault (DB_VERSION 2).
 * Never stores passwords / sessions / CSRF / JWT.
 */
(function (root) {
    'use strict';

    var PBKDF2_ITERATIONS = 120000;
    var UNLOCK_TTL_MS = 8 * 60 * 60 * 1000;
    var DEVICE_LS_KEY = 'rateb_erp_device_uuid';
    var UNLOCK_UNTIL_PREFIX = 'rateb_erp_unlock_until:';
    var REAUTH_KEY = 'rateb_erp_session_reauth';
    var DEVICE_META_PREFIX = 'auth_device:';

    function cfg() {
        return root.__RATEB_ERP_SHELL_OFFLINE__ || root.__RATEB_ERP_AUTH_OFFLINE__ || {};
    }

    function flags() {
        if (root.RatebOffline && typeof root.RatebOffline.flags === 'function') {
            return root.RatebOffline.flags() || {};
        }
        return cfg().flags || {};
    }

    function isActive() {
        var f = flags();
        return !!(f['offline.enabled'] && f['offline.read_cache'] && f['offline.auth.unlock']);
    }

    function tenantScope() {
        var c = cfg();
        return {
            company_id: parseInt(c.company_id, 10) || 0,
            branch_id: parseInt(c.branch_id, 10) || 0,
            user_id: parseInt(c.user_id, 10) || 0,
            is_super_admin: !!c.is_super_admin
        };
    }

    function vaultId(scope) {
        scope = scope || tenantScope();
        if (!scope.company_id || !scope.user_id) {
            return null;
        }
        return String(scope.company_id) + ':' + String(scope.branch_id || 0) + ':' + String(scope.user_id);
    }

    function schema() {
        return root.RatebOfflineSchema || null;
    }

    function withVault(mode, fn) {
        var Schema = schema();
        if (!Schema || !Schema.STORES || !Schema.STORES.AUTH_VAULT) {
            return Promise.reject(new Error('auth_vault_unavailable'));
        }
        return Schema.withStore(Schema.STORES.AUTH_VAULT, mode, fn);
    }

    function withMeta(mode, fn) {
        var Schema = schema();
        if (!Schema) {
            return Promise.reject(new Error('schema_unavailable'));
        }
        return Schema.withStore(Schema.STORES.SYNC_META, mode, fn);
    }

    function bufToB64(buf) {
        var bytes = new Uint8Array(buf);
        var bin = '';
        bytes.forEach(function (b) { bin += String.fromCharCode(b); });
        return btoa(bin);
    }

    function b64ToBuf(b64) {
        var bin = atob(String(b64 || '').replace(/-/g, '+').replace(/_/g, '/'));
        var bytes = new Uint8Array(bin.length);
        for (var i = 0; i < bin.length; i++) {
            bytes[i] = bin.charCodeAt(i);
        }
        return bytes.buffer;
    }

    function randomBytes(n) {
        var a = new Uint8Array(n);
        root.crypto.getRandomValues(a);
        return a;
    }

    function hashPin(pin, saltB64) {
        var enc = new TextEncoder();
        var salt = saltB64 ? new Uint8Array(b64ToBuf(saltB64)) : randomBytes(16);
        return root.crypto.subtle.importKey('raw', enc.encode(String(pin)), 'PBKDF2', false, ['deriveBits'])
            .then(function (key) {
                return root.crypto.subtle.deriveBits(
                    { name: 'PBKDF2', salt: salt, iterations: PBKDF2_ITERATIONS, hash: 'SHA-256' },
                    key,
                    256
                );
            })
            .then(function (bits) {
                return { pin_hash: bufToB64(bits), pin_salt: bufToB64(salt.buffer) };
            });
    }

    function getDeviceId() {
        try {
            var id = localStorage.getItem(DEVICE_LS_KEY);
            if (id) {
                return id;
            }
            id = 'erp-' + bufToB64(randomBytes(16).buffer).replace(/[^a-zA-Z0-9]/g, '').slice(0, 32);
            localStorage.setItem(DEVICE_LS_KEY, id);
            return id;
        } catch (e) {
            return 'erp-ephemeral';
        }
    }

    function unlockStorageKey(scope) {
        return UNLOCK_UNTIL_PREFIX + vaultId(scope);
    }

    function unlockUntil(scope) {
        try {
            return parseInt(sessionStorage.getItem(unlockStorageKey(scope)) || '0', 10) || 0;
        } catch (e) {
            return 0;
        }
    }

    function setUnlockUntil(scope, ts) {
        try {
            sessionStorage.setItem(unlockStorageKey(scope), String(ts));
        } catch (e) { /* ignore */ }
    }

    function isUnlocked(scope) {
        return unlockUntil(scope) > Date.now();
    }

    function markUnlocked(scope) {
        setUnlockUntil(scope, Date.now() + UNLOCK_TTL_MS);
    }

    function clearUnlock(scope) {
        setUnlockUntil(scope || tenantScope(), 0);
    }

    function sessionNeedsReauth() {
        try {
            return sessionStorage.getItem(REAUTH_KEY) === '1';
        } catch (e) {
            return false;
        }
    }

    function markSessionNeedsReauth() {
        try {
            sessionStorage.setItem(REAUTH_KEY, '1');
        } catch (e) { /* ignore */ }
    }

    function clearSessionNeedsReauth() {
        try {
            sessionStorage.removeItem(REAUTH_KEY);
        } catch (e) { /* ignore */ }
    }

    function getVault(scope) {
        var id = vaultId(scope);
        if (!id) {
            return Promise.resolve(null);
        }
        return withVault('readonly', function (store) {
            return new Promise(function (resolve, reject) {
                var req = store.get(id);
                req.onsuccess = function () { resolve(req.result || null); };
                req.onerror = function () { reject(req.error); };
            });
        });
    }

    function putVault(record) {
        return withVault('readwrite', function (store) {
            store.put(record);
            return true;
        });
    }

    function deleteVault(scope) {
        var id = vaultId(scope);
        if (!id) {
            return Promise.resolve(false);
        }
        return withVault('readwrite', function (store) {
            store.delete(id);
            return true;
        });
    }

    function cacheDeviceStatus(scope, device) {
        var key = DEVICE_META_PREFIX + (scope.company_id || 0) + ':' + (scope.user_id || 0);
        return withMeta('readwrite', function (store) {
            store.put({
                key: key,
                device_id: device && device.device_id ? String(device.device_id) : '',
                status: device && device.status ? String(device.status) : '',
                is_active: !!(device && device.is_active),
                updated_at: new Date().toISOString()
            });
            return true;
        });
    }

    function readDeviceStatus(scope) {
        var key = DEVICE_META_PREFIX + (scope.company_id || 0) + ':' + (scope.user_id || 0);
        return withMeta('readonly', function (store) {
            return new Promise(function (resolve, reject) {
                var req = store.get(key);
                req.onsuccess = function () { resolve(req.result || null); };
                req.onerror = function () { reject(req.error); };
            });
        }).catch(function () { return null; });
    }

    function assertUnlockAllowed(scope, deviceMeta) {
        if (!isActive()) {
            return { ok: false, error: 'auth_unlock_disabled' };
        }
        if (scope.is_super_admin) {
            return { ok: false, error: 'super_admin_denied' };
        }
        if (!scope.company_id || !scope.user_id) {
            return { ok: false, error: 'tenant_required' };
        }
        if (!deviceMeta || !deviceMeta.status) {
            return { ok: false, error: 'device_unknown' };
        }
        var st = String(deviceMeta.status).toLowerCase();
        if (st !== 'active' || !deviceMeta.is_active) {
            return { ok: false, error: st === 'pending' ? 'device_pending' : (st === 'revoked' ? 'device_revoked' : 'device_denied') };
        }
        return { ok: true };
    }

    function enrollPin(pin, options) {
        options = options || {};
        if (!isActive()) {
            return Promise.resolve({ ok: false, error: 'auth_unlock_disabled' });
        }
        var scope = tenantScope();
        if (scope.is_super_admin) {
            return Promise.resolve({ ok: false, error: 'super_admin_denied' });
        }
        var id = vaultId(scope);
        if (!id) {
            return Promise.resolve({ ok: false, error: 'online_session_required' });
        }
        if (!pin || String(pin).length < 4) {
            return Promise.resolve({ ok: false, error: 'pin_too_short' });
        }
        var now = new Date().toISOString();
        return getVault(scope).then(function (existing) {
            return hashPin(pin, existing && existing.pin_salt ? existing.pin_salt : null).then(function (hashed) {
                var record = {
                    id: id,
                    company_id: scope.company_id,
                    branch_id: scope.branch_id || 0,
                    user_id: scope.user_id,
                    pin_hash: hashed.pin_hash,
                    pin_salt: hashed.pin_salt,
                    webauthn_credential_id: (options.webauthn_credential_id
                        || (existing && existing.webauthn_credential_id)
                        || ''),
                    unlock_ttl_ms: UNLOCK_TTL_MS,
                    created_at: (existing && existing.created_at) ? existing.created_at : now,
                    updated_at: now
                };
                return putVault(record).then(function () {
                    return { ok: true, id: id };
                });
            });
        });
    }

    function unlockWithPin(pin, expectScope) {
        var scope = expectScope || tenantScope();
        return readDeviceStatus(scope).then(function (deviceMeta) {
            var gate = assertUnlockAllowed(scope, deviceMeta);
            if (!gate.ok) {
                return gate;
            }
            return getVault(scope).then(function (record) {
                if (!record || !record.pin_hash || !record.pin_salt) {
                    return { ok: false, error: 'not_enrolled' };
                }
                if ((intOr(record.company_id) !== scope.company_id)
                    || (intOr(record.user_id) !== scope.user_id)
                    || (intOr(record.branch_id) !== (scope.branch_id || 0))) {
                    return { ok: false, error: 'tenant_mismatch' };
                }
                return hashPin(pin, record.pin_salt).then(function (hashed) {
                    if (hashed.pin_hash !== record.pin_hash) {
                        return { ok: false, error: 'pin_denied' };
                    }
                    markUnlocked(scope);
                    return { ok: true };
                });
            });
        });
    }

    function intOr(v) {
        return parseInt(v, 10) || 0;
    }

    function unlockWithWebAuthn() {
        var scope = tenantScope();
        return readDeviceStatus(scope).then(function (deviceMeta) {
            var gate = assertUnlockAllowed(scope, deviceMeta);
            if (!gate.ok) {
                return gate;
            }
            return getVault(scope).then(function (record) {
                if (!record || !record.webauthn_credential_id) {
                    return { ok: false, error: 'webauthn_not_enrolled' };
                }
                if (!root.PublicKeyCredential || !navigator.credentials) {
                    return { ok: false, error: 'webauthn_unavailable' };
                }
                var idBuf = b64ToBuf(record.webauthn_credential_id);
                return navigator.credentials.get({
                    publicKey: {
                        challenge: randomBytes(32),
                        timeout: 60000,
                        userVerification: 'required',
                        allowCredentials: [{ type: 'public-key', id: idBuf }]
                    }
                }).then(function (cred) {
                    if (!cred || !cred.id) {
                        return { ok: false, error: 'webauthn_denied' };
                    }
                    markUnlocked(scope);
                    return { ok: true };
                }).catch(function () {
                    return { ok: false, error: 'webauthn_denied' };
                });
            });
        });
    }

    var overlayEl = null;

    function ensureOverlay() {
        if (overlayEl || !root.document || !root.document.body) {
            return overlayEl;
        }
        overlayEl = root.document.createElement('div');
        overlayEl.setAttribute('data-rateb-erp-auth-lock', '1');
        overlayEl.setAttribute('role', 'dialog');
        overlayEl.setAttribute('aria-modal', 'true');
        overlayEl.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(15,17,23,.92);'
            + 'display:flex;align-items:center;justify-content:center;padding:1.5rem;';
        var box = root.document.createElement('div');
        box.style.cssText = 'background:#1a1d24;color:#e8eaed;padding:1.5rem;border-radius:8px;max-width:22rem;width:100%;';
        var title = root.document.createElement('h2');
        title.textContent = 'ERP Offline Unlock';
        title.style.marginTop = '0';
        var msg = root.document.createElement('p');
        msg.setAttribute('data-lock-msg', '1');
        msg.textContent = 'Enter your offline PIN to unlock the cached shell.';
        var input = root.document.createElement('input');
        input.type = 'password';
        input.autocomplete = 'current-password';
        input.setAttribute('data-lock-pin', '1');
        input.style.cssText = 'width:100%;padding:.5rem;margin:.5rem 0;';
        var btn = root.document.createElement('button');
        btn.type = 'button';
        btn.textContent = 'Unlock';
        btn.style.cssText = 'width:100%;padding:.6rem;cursor:pointer;';
        btn.addEventListener('click', function () {
            unlockWithPin(input.value).then(function (res) {
                if (res && res.ok) {
                    hideOverlay();
                    return;
                }
                msg.textContent = (res && res.error) ? String(res.error) : 'Unlock denied';
            });
        });
        box.appendChild(title);
        box.appendChild(msg);
        box.appendChild(input);
        box.appendChild(btn);
        overlayEl.appendChild(box);
        root.document.body.appendChild(overlayEl);
        return overlayEl;
    }

    function showOverlay() {
        var el = ensureOverlay();
        if (el) {
            el.hidden = false;
            el.style.display = 'flex';
        }
    }

    function hideOverlay() {
        if (overlayEl) {
            overlayEl.hidden = true;
            overlayEl.style.display = 'none';
        }
    }

    function requireUnlockIfNeeded() {
        if (!isActive()) {
            return Promise.resolve({ skipped: true });
        }
        var scope = tenantScope();
        if (scope.is_super_admin) {
            return Promise.resolve({ ok: false, error: 'super_admin_denied' });
        }
        if (isUnlocked(scope)) {
            hideOverlay();
            return Promise.resolve({ ok: true, unlocked: true });
        }
        // Online with live CSRF: still require ACTIVE device before auto-unlock (Phase 13.1).
        var online = root.navigator && root.navigator.onLine !== false;
        var csrf = '';
        try {
            var meta = root.document && root.document.querySelector('meta[name="rateb-csrf"]');
            csrf = meta ? (meta.getAttribute('content') || '') : '';
        } catch (e) { /* ignore */ }
        if (online && csrf) {
            return readDeviceStatus(scope).then(function (device) {
                var status = device && device.status ? String(device.status).toLowerCase() : '';
                if (status === 'active') {
                    clearSessionNeedsReauth();
                    markUnlocked(scope);
                    hideOverlay();
                    return { ok: true, online_session: true, device_active: true };
                }
                markSessionNeedsReauth();
                showOverlay();
                return { ok: false, locked: true, error: 'inactive_device' };
            }).catch(function () {
                markSessionNeedsReauth();
                showOverlay();
                return { ok: false, locked: true, error: 'inactive_device' };
            });
        }
        showOverlay();
        return Promise.resolve({ ok: false, locked: true });
    }

    function handleLogoutClick(ev) {
        var a = ev.target && ev.target.closest ? ev.target.closest('a[href]') : null;
        if (!a) {
            return;
        }
        var href = a.getAttribute('href') || '';
        if (!/\/logout/i.test(href)) {
            return;
        }
        clearUnlock(tenantScope());
        var policy = (cfg().logout_vault_policy || 'keep_vault');
        if (policy === 'clear_vault') {
            deleteVault(tenantScope()).catch(function () { /* ignore */ });
        }
    }

    function start() {
        if (!isActive()) {
            return;
        }
        if (root.document) {
            root.document.addEventListener('click', handleLogoutClick, true);
        }
        requireUnlockIfNeeded();
    }

    root.RatebOfflineAuthLock = {
        isActive: isActive,
        tenantScope: tenantScope,
        vaultId: vaultId,
        getDeviceId: getDeviceId,
        enrollPin: enrollPin,
        unlockWithPin: unlockWithPin,
        unlockWithWebAuthn: unlockWithWebAuthn,
        isUnlocked: function () { return isUnlocked(tenantScope()); },
        clearUnlock: clearUnlock,
        deleteVault: deleteVault,
        cacheDeviceStatus: cacheDeviceStatus,
        readDeviceStatus: readDeviceStatus,
        sessionNeedsReauth: sessionNeedsReauth,
        markSessionNeedsReauth: markSessionNeedsReauth,
        clearSessionNeedsReauth: clearSessionNeedsReauth,
        requireUnlockIfNeeded: requireUnlockIfNeeded,
        showOverlay: showOverlay,
        hideOverlay: hideOverlay,
        start: start,
        PBKDF2_ITERATIONS: PBKDF2_ITERATIONS
    };
})(typeof window !== 'undefined' ? window : globalThis);

/* ---- rbac-cache-adapter.js ---- */
/**
 * RATEB Offline — ERP RBAC/nav cache adapter (Phase 12).
 * Stores structured manifest in snapshots (kind erp_rbac). UI only — never server authz.
 */
(function (root) {
    'use strict';

    var SNAPSHOT_KIND = 'erp_rbac';

    function cfg() {
        return root.__RATEB_ERP_SHELL_OFFLINE__ || {};
    }

    function flags() {
        if (root.RatebOffline && typeof root.RatebOffline.flags === 'function') {
            return root.RatebOffline.flags() || {};
        }
        return cfg().flags || {};
    }

    function isActive() {
        var f = flags();
        return !!(f['offline.enabled']
            && f['offline.read_cache']
            && f['offline.auth.unlock']
            && f['offline.rbac.cache']);
    }

    function tenantScope() {
        var c = cfg();
        return {
            company_id: parseInt(c.company_id, 10) || 0,
            branch_id: parseInt(c.branch_id, 10) || 0,
            user_id: parseInt(c.user_id, 10) || 0,
            is_super_admin: !!c.is_super_admin
        };
    }

    function snapshotId(scope) {
        scope = scope || tenantScope();
        if (!scope.company_id || !scope.user_id) {
            return null;
        }
        return SNAPSHOT_KIND
            + ':' + scope.company_id
            + ':' + (scope.branch_id || 0)
            + ':' + scope.user_id;
    }

    function withSnapshots(mode, fn) {
        var Schema = root.RatebOfflineSchema;
        if (!Schema || !Schema.withStore) {
            return Promise.reject(new Error('schema_unavailable'));
        }
        return Schema.withStore(Schema.STORES.SNAPSHOTS, mode, fn);
    }

    function putManifest(manifest) {
        if (!manifest || !manifest.id) {
            return Promise.reject(new Error('invalid_manifest'));
        }
        return withSnapshots('readwrite', function (store) {
            store.put(manifest);
            return true;
        });
    }

    function getManifest(scope) {
        var id = snapshotId(scope);
        if (!id) {
            return Promise.resolve(null);
        }
        return withSnapshots('readonly', function (store) {
            return new Promise(function (resolve, reject) {
                var req = store.get(id);
                req.onsuccess = function () { resolve(req.result || null); };
                req.onerror = function () { reject(req.error); };
            });
        });
    }

    function deleteManifest(scope) {
        var id = snapshotId(scope);
        if (!id) {
            return Promise.resolve(false);
        }
        return withSnapshots('readwrite', function (store) {
            store.delete(id);
            return true;
        });
    }

    function tenantMatch(row, scope) {
        scope = scope || tenantScope();
        if (!row) {
            return false;
        }
        return parseInt(row.company_id, 10) === scope.company_id
            && parseInt(row.branch_id, 10) === (scope.branch_id || 0)
            && parseInt(row.user_id, 10) === scope.user_id
            && String(row.kind || '') === SNAPSHOT_KIND;
    }

    function isExpired(row) {
        if (!row || !row.expires_at) {
            return true;
        }
        return parseInt(row.expires_at, 10) * 1000 <= Date.now();
    }

    /**
     * Fail-closed validation before UI use.
     * @returns {{ok:boolean, error?:string, manifest?:object}}
     */
    function validateForUse(row, scope, opts) {
        opts = opts || {};
        scope = scope || tenantScope();
        if (scope.is_super_admin) {
            return { ok: false, error: 'super_admin_denied' };
        }
        if (!row) {
            return { ok: false, error: 'missing_snapshot' };
        }
        if (!tenantMatch(row, scope)) {
            return { ok: false, error: 'tenant_mismatch' };
        }
        if (isExpired(row)) {
            return { ok: false, error: 'expired' };
        }
        if (opts.expectVersion && String(row.rbac_version || '') !== String(opts.expectVersion)) {
            return { ok: false, error: 'version_mismatch' };
        }
        if (opts.requireDeviceActive !== false) {
            var lock = root.RatebOfflineAuthLock;
            if (lock && typeof lock.readDeviceStatus === 'function') {
                // Async path handled by validateForUseAsync
            }
        }
        return { ok: true, manifest: row };
    }

    function validateForUseAsync(row, scope, opts) {
        var base = validateForUse(row, scope, opts);
        if (!base.ok) {
            return Promise.resolve(base);
        }
        if (opts && opts.requireDeviceActive === false) {
            return Promise.resolve(base);
        }
        var lock = root.RatebOfflineAuthLock;
        if (!lock || typeof lock.readDeviceStatus !== 'function') {
            return Promise.resolve({ ok: false, error: 'inactive_device' });
        }
        return lock.readDeviceStatus(scope || tenantScope()).then(function (device) {
            var status = device && device.status ? String(device.status).toLowerCase() : '';
            if (status !== 'active') {
                return { ok: false, error: 'inactive_device' };
            }
            return base;
        }).catch(function () {
            return { ok: false, error: 'inactive_device' };
        });
    }

    function can(slug, manifest) {
        if (!manifest || !Array.isArray(manifest.permission_slugs)) {
            return false;
        }
        slug = String(slug || '');
        if (slug === '') {
            return true;
        }
        return manifest.permission_slugs.indexOf(slug) !== -1;
    }

    function navCan(permission, module, manifest) {
        if (!manifest) {
            return false;
        }
        permission = String(permission || '');
        module = String(module || '');
        var disabled = manifest.offline_disabled_modules || [];
        if (module && disabled.indexOf(module) !== -1) {
            return false;
        }
        if (permission !== '' && !can(permission, manifest)) {
            return false;
        }
        if (module === '') {
            return true;
        }
        var mods = manifest.plan_modules || [];
        return mods.indexOf(module) !== -1;
    }

    function clearNavDom() {
        try {
            var nodes = root.document.querySelectorAll('.rateb-offline-shell-nav, aside.rateb-offline-shell-nav');
            nodes.forEach(function (el) {
                if (el.tagName === 'ASIDE' || el.classList.contains('rateb-offline-shell-nav')) {
                    el.innerHTML = '<p>RATEB ERP</p>';
                }
            });
        } catch (e) { /* ignore */ }
    }

    function renderNav(manifest) {
        if (!root.document || !manifest || !manifest.nav || !Array.isArray(manifest.nav.sections)) {
            clearNavDom();
            return false;
        }
        var disabled = manifest.offline_disabled_modules || [];
        var html = '<p class="rateb-offline-rbac-brand">RATEB ERP</p>';
        html += '<nav class="rateb-offline-rbac-nav" aria-label="Offline navigation">';
        manifest.nav.sections.forEach(function (section) {
            var items = (section && section.items) || [];
            var visible = [];
            items.forEach(function (item) {
                if (!item || item.offline_actionable === false) {
                    return;
                }
                var mod = String(item.module || '');
                if (mod && disabled.indexOf(mod) !== -1) {
                    return;
                }
                if (!navCan(item.permission || '', mod, manifest) && (item.permission || mod)) {
                    return;
                }
                visible.push(item);
            });
            if (visible.length === 0) {
                return;
            }
            html += '<div class="rateb-offline-rbac-section">';
            if (section.title_key || section.title) {
                html += '<div class="rateb-offline-rbac-section-title">'
                    + escapeHtml(section.title || section.title_key || '')
                    + '</div>';
            }
            visible.forEach(function (item) {
                var href = safeHref(item.href);
                var label = String(item.label || item.label_key || item.path || '');
                html += '<a class="rateb-offline-rbac-link" href="' + escapeAttr(href) + '">'
                    + '<span>' + escapeHtml(label) + '</span></a>';
            });
            html += '</div>';
        });
        html += '</nav>';
        try {
            var targets = root.document.querySelectorAll('aside.rateb-offline-shell-nav, aside[aria-label="Offline nav"]');
            if (!targets.length) {
                targets = root.document.querySelectorAll('.rateb-offline-shell-nav');
            }
            if (!targets.length) {
                return false;
            }
            targets.forEach(function (el) {
                el.innerHTML = html;
            });
            return true;
        } catch (e) {
            return false;
        }
    }

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function escapeAttr(s) {
        return escapeHtml(s).replace(/'/g, '&#39;');
    }

    /** Phase 13.1 — block javascript:/data:/vbscript: hrefs (IndexedDB poison). */
    function safeHref(raw) {
        var href = String(raw || '#').trim();
        if (href === '') {
            return '#';
        }
        var lower = href.toLowerCase();
        if (lower.indexOf('javascript:') === 0
            || lower.indexOf('data:') === 0
            || lower.indexOf('vbscript:') === 0) {
            return '#';
        }
        return href;
    }

    /**
     * Online: compare version; on mismatch/expiry delete + store fresh.
     */
    function syncFromServer(fetchVersion, fetchManifest) {
        if (!isActive()) {
            return Promise.resolve({ skipped: true, reason: 'rbac_disabled' });
        }
        var scope = tenantScope();
        if (scope.is_super_admin || !scope.company_id || !scope.user_id) {
            return Promise.resolve({ skipped: true, reason: 'denied' });
        }
        return getManifest(scope).then(function (cached) {
            return fetchVersion().then(function (verPayload) {
                var current = verPayload && verPayload.rbac_version
                    ? String(verPayload.rbac_version)
                    : '';
                if (!current) {
                    return { ok: false, error: 'version_unavailable' };
                }
                var needRefresh = !cached
                    || isExpired(cached)
                    || !tenantMatch(cached, scope)
                    || String(cached.rbac_version || '') !== current;
                if (!needRefresh) {
                    return { ok: true, refreshed: false, manifest: cached };
                }
                return deleteManifest(scope).then(function () {
                    return fetchManifest().then(function (manPayload) {
                        var man = manPayload && manPayload.manifest ? manPayload.manifest : null;
                        if (!man || String(man.rbac_version || '') !== current) {
                            // Accept if server returned fresh version even if race
                            if (!man) {
                                return { ok: false, error: 'manifest_unavailable' };
                            }
                        }
                        return putManifest(man).then(function () {
                            return { ok: true, refreshed: true, manifest: man };
                        });
                    });
                });
            });
        });
    }

    function applyCachedNav(opts) {
        if (!isActive()) {
            return Promise.resolve({ ok: false, error: 'rbac_disabled' });
        }
        var scope = tenantScope();
        return getManifest(scope).then(function (row) {
            return validateForUseAsync(row, scope, opts || {}).then(function (v) {
                if (!v.ok) {
                    clearNavDom();
                    return v;
                }
                var rendered = renderNav(v.manifest);
                return { ok: rendered, error: rendered ? undefined : 'render_failed', manifest: v.manifest };
            });
        });
    }

    root.RatebOfflineRbacCache = {
        KIND: SNAPSHOT_KIND,
        isActive: isActive,
        tenantScope: tenantScope,
        snapshotId: snapshotId,
        putManifest: putManifest,
        getManifest: getManifest,
        deleteManifest: deleteManifest,
        isExpired: isExpired,
        tenantMatch: tenantMatch,
        validateForUse: validateForUse,
        validateForUseAsync: validateForUseAsync,
        can: can,
        navCan: navCan,
        renderNav: renderNav,
        clearNavDom: clearNavDom,
        syncFromServer: syncFromServer,
        applyCachedNav: applyCachedNav
    };
})(typeof window !== 'undefined' ? window : globalThis);

/* ---- master-data-adapter.js ---- */
/**
 * RATEB Offline — Master-data delta adapter (Phase 13.1).
 * Read-only delta pull into entity_cache. No write-queue enqueue.
 * Tenant-scoped entity_cache keys; client-owned cursors; debounce + TTL purge.
 */
(function (root) {
    'use strict';

    var ENTITIES = {
        customer_directory: { prefix: 'cus', aliases: ['customers', 'customer'] },
        branch_directory: { prefix: 'br', aliases: ['branches', 'branch'] },
        warehouse_directory: { prefix: 'wh', aliases: ['warehouses', 'warehouse'] },
        employee_directory: { prefix: 'emp', aliases: ['employees', 'hr_employees'] },
        supplier_directory: { prefix: 'sup', aliases: ['suppliers', 'procurement_suppliers'] }
    };
    var SYNC_DEBOUNCE_MS = 5 * 60 * 1000;
    var DEFAULT_TTL_MS = 12 * 60 * 60 * 1000;
    var MAX_PAGES = 50;

    function cfg() {
        return root.__RATEB_ERP_MASTER_DATA__ || root.__RATEB_ERP_SHELL_OFFLINE__ || {};
    }

    function flags() {
        if (root.RatebOffline && typeof root.RatebOffline.flags === 'function') {
            return root.RatebOffline.flags() || {};
        }
        return cfg().flags || {};
    }

    function isActive() {
        var f = flags();
        return !!(f['offline.enabled'] && f['offline.master_data']);
    }

    function tenantScope() {
        var c = cfg();
        return {
            company_id: parseInt(c.company_id, 10) || 0,
            branch_id: parseInt(c.branch_id, 10) || 0,
            user_id: parseInt(c.user_id, 10) || 0
        };
    }

    function resolveEntity(name) {
        name = String(name || '');
        if (ENTITIES[name]) {
            return name;
        }
        var keys = Object.keys(ENTITIES);
        for (var i = 0; i < keys.length; i++) {
            var k = keys[i];
            if ((ENTITIES[k].aliases || []).indexOf(name) !== -1) {
                return k;
            }
        }
        return null;
    }

    /** company:branch:prefix:id — Phase 13.1 tenant isolation */
    function cacheRowId(prefix, itemId, scope) {
        scope = scope || tenantScope();
        return String(scope.company_id)
            + ':' + String(scope.branch_id || 0)
            + ':' + prefix
            + ':' + String(itemId);
    }

    function legacyCacheRowId(prefix, itemId) {
        return prefix + ':' + String(itemId);
    }

    function cursorKey(entity, scope) {
        scope = scope || tenantScope();
        return 'md:' + scope.company_id + ':' + (scope.branch_id || 0) + ':' + entity;
    }

    function syncMetaKey(scope) {
        scope = scope || tenantScope();
        return 'md_sync:' + scope.company_id + ':' + (scope.branch_id || 0);
    }

    function withCursors(mode, fn) {
        var Schema = root.RatebOfflineSchema;
        if (!Schema || !Schema.withStore) {
            return Promise.reject(new Error('schema_unavailable'));
        }
        return Schema.withStore(Schema.STORES.CURSORS, mode, fn);
    }

    function withEntityCache(mode, fn) {
        var Schema = root.RatebOfflineSchema;
        if (!Schema || !Schema.withStore) {
            return Promise.reject(new Error('schema_unavailable'));
        }
        return Schema.withStore(Schema.STORES.ENTITY_CACHE, mode, fn);
    }

    function withMeta(mode, fn) {
        var Schema = root.RatebOfflineSchema;
        if (!Schema || !Schema.withStore) {
            return Promise.reject(new Error('schema_unavailable'));
        }
        return Schema.withStore(Schema.STORES.SYNC_META, mode, fn);
    }

    function readClientCursor(entity, scope) {
        var key = cursorKey(entity, scope);
        return withCursors('readonly', function (store) {
            return new Promise(function (resolve, reject) {
                var req = store.get(key);
                req.onsuccess = function () {
                    var row = req.result || null;
                    resolve(row && row.cursor_token ? String(row.cursor_token) : null);
                };
                req.onerror = function () { reject(req.error); };
            });
        }).catch(function () { return null; });
    }

    function writeClientCursor(entity, token, scope) {
        var key = cursorKey(entity, scope);
        return withCursors('readwrite', function (store) {
            store.put({
                key: key,
                entity: entity,
                company_id: (scope || tenantScope()).company_id,
                branch_id: (scope || tenantScope()).branch_id || 0,
                cursor_token: token || null,
                updated_at: Date.now()
            });
            return true;
        });
    }

    function applyItems(entity, items, scope) {
        var meta = ENTITIES[entity];
        if (!meta || !Array.isArray(items)) {
            return Promise.resolve(0);
        }
        var prefix = meta.prefix;
        scope = scope || tenantScope();
        return withEntityCache('readwrite', function (store) {
            var n = 0;
            items.forEach(function (item) {
                if (!item || !item.id) {
                    return;
                }
                var id = cacheRowId(prefix, item.id, scope);
                var legacy = legacyCacheRowId(prefix, item.id);
                try { store.delete(legacy); } catch (e) { /* ignore */ }
                if (item.deleted || item.active === false) {
                    store.delete(id);
                } else {
                    store.put({
                        id: id,
                        entity: entity,
                        company_id: item.company_id || scope.company_id,
                        branch_id: item.branch_id != null ? item.branch_id : scope.branch_id,
                        payload: item,
                        updated_at: item.updated_at || null,
                        synced_at: Date.now()
                    });
                }
                n += 1;
            });
            return n;
        });
    }

    function purgeExpired(scope) {
        scope = scope || tenantScope();
        var ttl = DEFAULT_TTL_MS;
        var cutoff = Date.now() - ttl;
        var prefix = String(scope.company_id) + ':' + String(scope.branch_id || 0) + ':';
        return withEntityCache('readwrite', function (store) {
            return new Promise(function (resolve, reject) {
                var req = store.openCursor();
                var removed = 0;
                req.onsuccess = function (ev) {
                    var cursor = ev.target.result;
                    if (!cursor) {
                        resolve(removed);
                        return;
                    }
                    var row = cursor.value || {};
                    var id = String(row.id || '');
                    if (id.indexOf(prefix) === 0) {
                        var synced = parseInt(row.synced_at, 10) || 0;
                        if (synced > 0 && synced < cutoff) {
                            cursor.delete();
                            removed += 1;
                        }
                    }
                    cursor.continue();
                };
                req.onerror = function () { reject(req.error); };
            });
        }).catch(function () { return 0; });
    }

    function shouldDebounce(scope) {
        var key = syncMetaKey(scope);
        return withMeta('readonly', function (store) {
            return new Promise(function (resolve) {
                var req = store.get(key);
                req.onsuccess = function () {
                    var row = req.result || null;
                    var last = row && row.last_sync_at ? parseInt(row.last_sync_at, 10) : 0;
                    resolve(last > 0 && (Date.now() - last) < SYNC_DEBOUNCE_MS);
                };
                req.onerror = function () { resolve(false); };
            });
        }).catch(function () { return false; });
    }

    function markSynced(scope, info) {
        var key = syncMetaKey(scope);
        return withMeta('readwrite', function (store) {
            store.put({
                key: key,
                last_sync_at: Date.now(),
                info: info || null
            });
            return true;
        }).catch(function () { return false; });
    }

    function deviceId() {
        var lock = root.RatebOfflineAuthLock;
        if (lock && typeof lock.getDeviceId === 'function') {
            return lock.getDeviceId();
        }
        return '';
    }

    function pullEntity(entityName, options) {
        options = options || {};
        if (!isActive()) {
            return Promise.resolve({ skipped: true, reason: 'master_data_disabled' });
        }
        var entity = resolveEntity(entityName);
        if (!entity) {
            return Promise.resolve({ ok: false, error: 'entity_not_allowed' });
        }
        var scope = options.scope || tenantScope();
        if (!scope.company_id) {
            return Promise.resolve({ ok: false, error: 'company_required' });
        }
        var pull = root.RatebOfflineDeltaPull;
        if (!pull || typeof pull.pull !== 'function') {
            return Promise.reject(new Error('delta_pull_unavailable'));
        }
        var apiBase = options.apiBase || cfg().apiBase || '';
        var pages = 0;
        var total = 0;
        var incomplete = false;
        var dev = options.device_id || deviceId();

        function next(cursor) {
            return pull.pull(entity, {
                apiBase: apiBase,
                cursor: cursor || undefined,
                branch_id: scope.branch_id || undefined,
                device_id: dev || undefined
            }).then(function (res) {
                if (res && res.ok === false) {
                    return {
                        ok: false,
                        error: (res.error && res.error.code) || 'delta_failed',
                        pages: pages,
                        total: total
                    };
                }
                var delta = (res && res.delta) ? res.delta : res;
                if (!delta) {
                    return { ok: false, error: 'empty_delta', pages: pages, total: total };
                }
                if (delta.migration_required || delta.error === 'updated_at_required') {
                    return {
                        ok: false,
                        error: delta.error || 'migration_required',
                        migration_required: true,
                        pages: pages,
                        total: total
                    };
                }
                if (delta.error === 'entity_not_allowed' || delta.disabled) {
                    return {
                        ok: false,
                        error: delta.error || 'disabled',
                        pages: pages,
                        total: total
                    };
                }
                var items = Array.isArray(delta.items) ? delta.items : [];
                pages += 1;
                return applyItems(entity, items, scope).then(function (n) {
                    total += n;
                    var token = delta.cursor_token || cursor || null;
                    return writeClientCursor(entity, token, scope).then(function () {
                        if (delta.has_more && items.length > 0) {
                            if (pages >= MAX_PAGES) {
                                incomplete = true;
                                return {
                                    ok: true,
                                    entity: entity,
                                    pages: pages,
                                    total: total,
                                    cursor_token: token,
                                    has_more: true,
                                    incomplete: true,
                                    warning: 'page_limit_reached'
                                };
                            }
                            return next(token);
                        }
                        return {
                            ok: true,
                            entity: entity,
                            pages: pages,
                            total: total,
                            cursor_token: token,
                            has_more: !!delta.has_more,
                            incomplete: incomplete
                        };
                    });
                });
            });
        }

        return readClientCursor(entity, scope).then(function (stored) {
            return next(options.cursor != null ? options.cursor : stored);
        });
    }

    /**
     * Phase 14 — list cached directory rows for offline pickers.
     * @returns {Promise<{ok: boolean, entity?: string, items: object[], warning?: string}>}
     */
    function listCached(entityName, options) {
        options = options || {};
        var entity = resolveEntity(entityName);
        if (!entity) {
            return Promise.resolve({ ok: false, error: 'entity_not_allowed', items: [] });
        }
        var scope = options.scope || tenantScope();
        var prefix = String(scope.company_id) + ':' + String(scope.branch_id || 0) + ':'
            + (ENTITIES[entity].prefix) + ':';
        var q = String(options.query || options.q || '').toLowerCase().trim();
        var limit = parseInt(options.limit, 10) || 200;
        if (limit < 1) {
            limit = 200;
        }
        return withEntityCache('readonly', function (store) {
            return new Promise(function (resolve, reject) {
                var req = store.openCursor();
                var items = [];
                req.onsuccess = function (ev) {
                    var cursor = ev.target.result;
                    if (!cursor || items.length >= limit) {
                        resolve({ ok: true, entity: entity, items: items });
                        return;
                    }
                    var row = cursor.value || {};
                    var id = String(row.id || '');
                    if (id.indexOf(prefix) === 0 && row.entity === entity) {
                        var payload = row.payload || row.data || {};
                        if (q) {
                            var label = String(
                                payload.name || payload.title || payload.label
                                || payload.code || payload.email || ''
                            ).toLowerCase();
                            if (label.indexOf(q) === -1 && String(payload.id || '').indexOf(q) === -1) {
                                cursor.continue();
                                return;
                            }
                        }
                        items.push(payload);
                    }
                    cursor.continue();
                };
                req.onerror = function () { reject(req.error); };
            });
        }).catch(function () {
            return { ok: false, error: 'entity_cache_unavailable', items: [] };
        });
    }

    /** Map entity_cache rows to {value,label} for <select> hydration. */
    function pickerOptions(entityName, options) {
        return listCached(entityName, options).then(function (res) {
            var items = (res && res.items) ? res.items : [];
            var opts = items.map(function (item) {
                var value = item.id;
                var label = item.name || item.title || item.label || item.code
                    || (item.first_name
                        ? (String(item.first_name) + ' ' + String(item.last_name || '')).trim()
                        : null)
                    || String(item.id);
                return { value: value, label: label, item: item };
            });
            return {
                ok: !!(res && res.ok),
                entity: res && res.entity,
                options: opts,
                warning: res && res.warning,
                error: res && res.error
            };
        });
    }

    function syncAll(options) {
        options = options || {};
        if (!isActive()) {
            return Promise.resolve({ skipped: true });
        }
        var scope = options.scope || tenantScope();
        return shouldDebounce(scope).then(function (skip) {
            if (skip && !options.force) {
                return { ok: true, debounced: true, results: {} };
            }
            return purgeExpired(scope).then(function (purged) {
                var list = Object.keys(ENTITIES);
                var results = {};
                var chain = Promise.resolve();
                list.forEach(function (entity) {
                    chain = chain.then(function () {
                        return pullEntity(entity, options).then(function (r) {
                            results[entity] = r;
                        });
                    });
                });
                return chain.then(function () {
                    return markSynced(scope, { purged: purged, results: results }).then(function () {
                        return { ok: true, purged: purged, results: results };
                    });
                });
            });
        });
    }

    root.RatebOfflineMasterData = {
        isActive: isActive,
        tenantScope: tenantScope,
        resolveEntity: resolveEntity,
        cacheRowId: cacheRowId,
        cursorKey: cursorKey,
        readClientCursor: readClientCursor,
        writeClientCursor: writeClientCursor,
        pullEntity: pullEntity,
        listCached: listCached,
        pickerOptions: pickerOptions,
        syncAll: syncAll,
        purgeExpired: purgeExpired,
        ENTITIES: ENTITIES,
        MAX_PAGES: MAX_PAGES,
        SYNC_DEBOUNCE_MS: SYNC_DEBOUNCE_MS
    };
})(typeof window !== 'undefined' ? window : globalThis);


/* ---- ops-forms-adapter.js ---- */
/**
 * RATEB Offline — Ops forms adapter (Phase 14 / 14.2 / 15B).
 * Per-module hooks: when offline, allowlisted Inv/HR/Proc/Recruitment forms enqueue via existing adapters.
 * Does not finish a generic form-post stub; narrow path matching only.
 * Phase 14.2: purchase-orders/{id}/receive → goods_receipt.receive (flag-gated).
 * Phase 15B: recruitment/candidates create|update|transition (flag-gated).
 */
(function (root) {
    'use strict';

    var DEFAULT_HOOKS = [
        { match: 'stock-movements', module: 'inventory', action: 'stock_movement.create' },
        { match: 'warehouse-transfers', module: 'inventory', action: 'warehouse_transfer.create' },
        { match: 'inventory-audits', module: 'inventory', action: 'stock_count.create' },
        { match: 'hr/attendance/bulk', module: 'hr', action: 'attendance.bulk' },
        { match: 'hr/attendance', module: 'hr', action: 'attendance.create' },
        { match: 'hr/leaves', module: 'hr', action: 'leave_request.draft' },
        { match: 'purchase-requests', module: 'procurement', action: 'purchase_request.draft' },
        { match: 'purchase-orders', module: 'procurement', action: 'purchase_order.draft' },
        { match: 'rfq', module: 'procurement', action: 'rfq.draft' },
        { match: 'recruitment/candidates/create', module: 'recruitment', action: 'candidate.create' },
        { match: 'recruitment/candidates', module: 'recruitment', action: 'candidate.update' }
    ];

    function cfg() {
        return root.__RATEB_ERP_SHELL_OFFLINE__ || root.__RATEB_ERP_MASTER_DATA__ || {};
    }

    function flags() {
        if (root.RatebOffline && typeof root.RatebOffline.flags === 'function') {
            return root.RatebOffline.flags() || {};
        }
        return cfg().flags || {};
    }

    function isOnline() {
        var conn = root.RatebOfflineConnectivity;
        if (conn && typeof conn.isOnline === 'function') {
            return !!conn.isOnline();
        }
        return typeof navigator === 'undefined' || navigator.onLine !== false;
    }

    function moduleEnabled(module, action) {
        var f = flags();
        if (!f['offline.enabled']) {
            return false;
        }
        if (module === 'inventory') {
            return !!f['offline.inventory.movements'];
        }
        if (module === 'hr') {
            return !!f['offline.hr.attendance'];
        }
        if (module === 'procurement') {
            if (!f['offline.procurement']) {
                return false;
            }
            if (action === 'goods_receipt.receive') {
                return !!f['offline.procurement.goods_receipt'];
            }
            return true;
        }
        if (module === 'recruitment') {
            if (!f['offline.recruitment']) {
                return false;
            }
            if (action === 'candidate.create' || action === 'candidate.update' || action === 'note.create') {
                return !!f['offline.recruitment.candidates'];
            }
            if (action === 'workflow.transition') {
                return !!f['offline.recruitment.workflow'];
            }
            if (action === 'assignment.create') {
                return !!f['offline.recruitment.assignment'];
            }
            return true;
        }
        return false;
    }

    function formHooks() {
        var list = cfg().ops_form_hooks;
        if (Array.isArray(list) && list.length) {
            return list;
        }
        return DEFAULT_HOOKS;
    }

    function normalizePath(pathname) {
        return String(pathname || '').replace(/\/+$/, '').toLowerCase();
    }

    function isPurchaseOrderReceivePath(pathname) {
        return /purchase-orders\/\d+\/receive(\/|$|\?)/i.test(String(pathname || ''));
    }

    function extractPoIdFromPath(pathname) {
        var m = String(pathname || '').match(/purchase-orders\/(\d+)\/receive/i);
        return m ? (parseInt(m[1], 10) || 0) : 0;
    }

    function isRecruitmentTransitionPath(pathname) {
        return /recruitment\/candidates\/\d+\/transition(\/|$|\?)/i.test(String(pathname || ''));
    }

    function extractCandidateIdFromPath(pathname) {
        var m = String(pathname || '').match(/recruitment\/candidates\/(\d+)/i);
        return m ? (parseInt(m[1], 10) || 0) : 0;
    }

    function matchHook(pathname) {
        var p = normalizePath(pathname);
        var hooks = formHooks();
        // Longer matches first (bulk before attendance; create before candidates).
        var sorted = hooks.slice().sort(function (a, b) {
            return String(b.match || '').length - String(a.match || '').length;
        });
        for (var i = 0; i < sorted.length; i++) {
            var m = String(sorted[i].match || '').replace(/^\/+|\/+$/g, '').toLowerCase();
            if (!m) {
                continue;
            }
            var re = new RegExp('(^|/)' + m.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '(/|$)', 'i');
            if (re.test(p)) {
                var hook = sorted[i];
                if (String(hook.match).indexOf('purchase-orders') >= 0 && isPurchaseOrderReceivePath(p)) {
                    return {
                        match: hook.match,
                        module: 'procurement',
                        action: 'goods_receipt.receive'
                    };
                }
                if (String(hook.match).indexOf('recruitment/candidates') >= 0 && isRecruitmentTransitionPath(p)) {
                    return {
                        match: hook.match,
                        module: 'recruitment',
                        action: 'workflow.transition'
                    };
                }
                return hook;
            }
        }
        return null;
    }

    function formToObject(form) {
        var fd = new FormData(form);
        var out = {};
        fd.forEach(function (value, key) {
            if (key === '_csrf' || key === '_method') {
                return;
            }
            if (Object.prototype.hasOwnProperty.call(out, key)) {
                if (!Array.isArray(out[key])) {
                    out[key] = [out[key]];
                }
                out[key].push(value);
            } else {
                out[key] = value;
            }
        });
        return out;
    }

    function intOrZero(v) {
        var n = parseInt(v, 10);
        return isNaN(n) ? 0 : n;
    }

    function floatOrZero(v) {
        var n = parseFloat(v);
        return isNaN(n) ? 0 : n;
    }

    function buildBulkAttendance(raw) {
        var date = String(raw.attendance_date || '');
        var present = Array.isArray(raw['present[]']) ? raw['present[]'] : (raw.present || []);
        if (!Array.isArray(present) && present) {
            present = [present];
        }
        var rows = [];
        (present || []).forEach(function (eid) {
            var id = intOrZero(eid);
            if (id < 1) {
                return;
            }
            var checkIn = raw['check_in[' + id + ']'] || raw['check_in'] || '';
            var checkOut = raw['check_out[' + id + ']'] || raw['check_out'] || '';
            rows.push({
                employee_id: id,
                check_in: checkIn || null,
                check_out: checkOut || null,
                status: 'present'
            });
        });
        return { attendance_date: date, rows: rows };
    }

    function buildStockCount(raw) {
        var lines = [];
        if (Array.isArray(raw['line_inventory_id[]']) || raw['line_inventory_id']) {
            var ids = Array.isArray(raw['line_inventory_id[]'])
                ? raw['line_inventory_id[]']
                : [raw['line_inventory_id']];
            var qtys = Array.isArray(raw['line_counted_qty[]'])
                ? raw['line_counted_qty[]']
                : (raw['line_counted_qty'] ? [raw['line_counted_qty']] : []);
            ids.forEach(function (id, idx) {
                lines.push({
                    inventory_id: intOrZero(id),
                    counted_qty: floatOrZero(qtys[idx] != null ? qtys[idx] : 0)
                });
            });
        } else if (raw.inventory_id) {
            lines.push({
                inventory_id: intOrZero(raw.inventory_id),
                counted_qty: floatOrZero(raw.counted_qty != null ? raw.counted_qty : raw.quantity)
            });
        }
        return {
            warehouse_id: intOrZero(raw.warehouse_id) || null,
            notes: raw.notes || null,
            lines: lines
        };
    }

    function buildGoodsReceipt(raw, pathname) {
        var receiveQtys = {};
        Object.keys(raw || {}).forEach(function (key) {
            var m = String(key).match(/^receive_qty\[(\d+)\]$/);
            if (m) {
                receiveQtys[m[1]] = floatOrZero(raw[key]);
            }
        });
        if (raw.receive_qty && typeof raw.receive_qty === 'object' && !Array.isArray(raw.receive_qty)) {
            Object.keys(raw.receive_qty).forEach(function (k) {
                receiveQtys[k] = floatOrZero(raw.receive_qty[k]);
            });
        }
        return {
            purchase_order_id: intOrZero(raw.purchase_order_id || raw.order_id)
                || extractPoIdFromPath(pathname),
            warehouse_id: intOrZero(raw.warehouse_id) || null,
            receive_qty: receiveQtys
        };
    }

    function buildPayload(hook, raw, pathname) {
        var action = String(hook.action || '');
        if (action === 'stock_movement.create') {
            return {
                inventory_id: intOrZero(raw.inventory_id),
                warehouse_id: intOrZero(raw.warehouse_id) || null,
                movement_type: String(raw.movement_type || 'in'),
                quantity: floatOrZero(raw.quantity),
                notes: raw.notes || null
            };
        }
        if (action === 'warehouse_transfer.create') {
            return {
                inventory_id: intOrZero(raw.inventory_id),
                source_warehouse_id: intOrZero(raw.source_warehouse_id),
                destination_warehouse_id: intOrZero(raw.destination_warehouse_id),
                quantity: floatOrZero(raw.quantity),
                notes: raw.notes || null
            };
        }
        if (action === 'stock_count.create') {
            return buildStockCount(raw);
        }
        if (action === 'attendance.create') {
            return {
                employee_id: intOrZero(raw.employee_id),
                attendance_date: String(raw.attendance_date || ''),
                check_in: raw.check_in || null,
                check_out: raw.check_out || null,
                status: raw.status || 'present',
                notes: raw.notes || null
            };
        }
        if (action === 'attendance.bulk') {
            return buildBulkAttendance(raw);
        }
        if (action === 'leave_request.draft') {
            return {
                employee_id: intOrZero(raw.employee_id),
                leave_type_id: intOrZero(raw.leave_type_id) || null,
                start_date: raw.start_date || null,
                end_date: raw.end_date || null,
                days: raw.days != null ? floatOrZero(raw.days) : null,
                notes: raw.notes || null,
                status: 'draft'
            };
        }
        if (action === 'goods_receipt.receive') {
            return buildGoodsReceipt(raw, pathname || '');
        }
        if (action === 'purchase_request.draft'
            || action === 'rfq.draft'
            || action === 'purchase_order.draft') {
            return {
                title: String(raw.title || raw.subject || 'Offline draft'),
                supplier_id: intOrZero(raw.supplier_id) || null,
                department: raw.department || null,
                priority: raw.priority || null,
                notes: raw.notes || null,
                total_estimated: raw.total_estimated != null ? floatOrZero(raw.total_estimated) : null,
                total_amount: raw.total_amount != null ? floatOrZero(raw.total_amount) : null
            };
        }
        if (action === 'candidate.create' || action === 'candidate.update') {
            var cand = {
                full_name: String(raw.full_name || raw.name || ''),
                nationality: raw.nationality || null,
                phone: raw.phone || null,
                email: raw.email || null,
                agency_id: intOrZero(raw.agency_id) || null,
                notes: raw.notes || null,
                expected_status: raw.expected_status || raw.expected_workflow_status || null
            };
            if (action === 'candidate.update') {
                cand.candidate_id = intOrZero(raw.candidate_id || raw.id)
                    || extractCandidateIdFromPath(pathname || '');
            }
            return cand;
        }
        if (action === 'workflow.transition') {
            return {
                candidate_id: intOrZero(raw.candidate_id)
                    || extractCandidateIdFromPath(pathname || ''),
                to_status: String(raw.to_status || raw.workflow_status || ''),
                reason: raw.reason || null
            };
        }
        return raw;
    }

    function enqueueViaAdapter(hook, payload) {
        var module = String(hook.module || '');
        var action = String(hook.action || '');
        if (module === 'inventory') {
            var inv = root.RatebOfflineInventoryAdapter;
            if (!inv) {
                return Promise.reject(new Error('inventory_adapter_unavailable'));
            }
            if (action === 'stock_movement.create') {
                return inv.enqueueMovement(payload);
            }
            if (action === 'warehouse_transfer.create') {
                return inv.enqueueWarehouseTransfer(payload);
            }
            if (action === 'stock_count.create') {
                return inv.enqueueStockCount(payload);
            }
        }
        if (module === 'hr') {
            var hr = root.RatebOfflineHrAdapter;
            if (!hr) {
                return Promise.reject(new Error('hr_adapter_unavailable'));
            }
            if (action === 'attendance.create') {
                return hr.enqueueAttendance(payload);
            }
            if (action === 'attendance.bulk') {
                return hr.enqueueAttendanceBulk(payload);
            }
            if (action === 'leave_request.draft') {
                return hr.enqueueLeaveDraft(payload);
            }
        }
        if (module === 'procurement') {
            var proc = root.RatebOfflineProcurementAdapter;
            if (!proc) {
                return Promise.reject(new Error('procurement_adapter_unavailable'));
            }
            if (action === 'purchase_request.draft') {
                return proc.enqueuePurchaseRequestDraft(payload);
            }
            if (action === 'rfq.draft') {
                return proc.enqueueRfqDraft(payload);
            }
            if (action === 'purchase_order.draft') {
                return proc.enqueuePurchaseOrderDraft(payload);
            }
            if (action === 'goods_receipt.receive') {
                return proc.enqueueGoodsReceipt(payload);
            }
        }
        if (module === 'recruitment') {
            var rec = root.RatebOfflineRecruitmentAdapter;
            if (!rec) {
                return Promise.reject(new Error('recruitment_adapter_unavailable'));
            }
            if (action === 'candidate.create') {
                return rec.enqueueCandidateCreate(payload);
            }
            if (action === 'candidate.update') {
                return rec.enqueueCandidateUpdate(payload);
            }
            if (action === 'workflow.transition') {
                return rec.enqueueWorkflowTransition(payload);
            }
            if (action === 'assignment.create') {
                return rec.enqueueAssignmentCreate(payload);
            }
            if (typeof rec.enqueue === 'function') {
                return rec.enqueue(action, payload);
            }
        }
        return Promise.reject(new Error('ops_form_action_unsupported'));
    }

    function notify(message, isError) {
        try {
            if (root.RatebOfflineEvents) {
                root.RatebOfflineEvents.emit('ops_form:queued', { message: message, error: !!isError });
            }
        } catch (e) { /* ignore */ }
        try {
            var existing = root.document && root.document.getElementById('rateb-offline-ops-toast');
            if (existing && existing.parentNode) {
                existing.parentNode.removeChild(existing);
            }
            if (!root.document || !root.document.body) {
                return;
            }
            var el = root.document.createElement('div');
            el.id = 'rateb-offline-ops-toast';
            el.setAttribute('role', 'status');
            el.style.cssText = 'position:fixed;bottom:1rem;inset-inline-start:1rem;z-index:9999;'
                + 'padding:.75rem 1rem;border-radius:.5rem;max-width:22rem;font-size:.9rem;'
                + (isError
                    ? 'background:#7f1d1d;color:#fecaca;'
                    : 'background:#14532d;color:#bbf7d0;');
            el.textContent = message;
            root.document.body.appendChild(el);
            setTimeout(function () {
                if (el.parentNode) {
                    el.parentNode.removeChild(el);
                }
            }, 4500);
        } catch (e2) { /* ignore */ }
    }

    function handleSubmit(ev) {
        if (isOnline()) {
            return;
        }
        var form = ev.target;
        if (!form || form.tagName !== 'FORM') {
            return;
        }
        var actionUrl = form.getAttribute('action') || (root.location && root.location.pathname) || '';
        var hook = matchHook(actionUrl) || matchHook(root.location && root.location.pathname);
        if (!hook) {
            return;
        }
        if (!moduleEnabled(hook.module, hook.action)) {
            return;
        }
        ev.preventDefault();
        ev.stopPropagation();
        var raw = formToObject(form);
        var payload = buildPayload(hook, raw, actionUrl);
        enqueueViaAdapter(hook, payload).then(function (res) {
            var depth = res && (res.queueDepth != null ? res.queueDepth : null);
            notify(
                'تم حفظ العملية في قائمة المزامنة'
                    + (depth != null ? ' (' + depth + ')' : '')
                    + '. Offline queued — sync when online.',
                false
            );
        }).catch(function (err) {
            var msg = (err && err.message) ? String(err.message) : 'queue_failed';
            if (msg === 'client_queue_full') {
                notify('قائمة المزامنة ممتلئة — أعد الاتصال وزامِن أولاً. Queue full.', true);
            } else {
                notify('تعذر الحفظ أوفلاين: ' + msg, true);
            }
        });
    }

    var bound = false;

    function bind() {
        if (bound || !root.document) {
            return;
        }
        var f = flags();
        if (!f['offline.enabled']) {
            return;
        }
        if (!(f['offline.inventory.movements']
            || f['offline.hr.attendance']
            || f['offline.procurement']
            || f['offline.recruitment'])) {
            return;
        }
        root.document.addEventListener('submit', handleSubmit, true);
        bound = true;
    }

    root.RatebOfflineOpsForms = {
        bind: bind,
        matchHook: matchHook,
        buildPayload: buildPayload,
        formToObject: formToObject,
        isModuleEnabled: moduleEnabled,
        DEFAULT_HOOKS: DEFAULT_HOOKS
    };
})(typeof window !== 'undefined' ? window : globalThis);

/* ---- sdk.js ---- */
/**
 * RATEB Offline SDK bootstrap (Phase 14.2 + Phase 15B recruitment).
 * Flag merge is additive — later bootstraps update flags without a second full boot.
 */
(function (root) {
    'use strict';

    var booted = false;
    var flags = {
        'offline.enabled': false,
        'offline.pos.complete': true,
        'offline.inventory.movements': false,
        'offline.hr.attendance': false,
        'offline.procurement': false,
        'offline.procurement.goods_receipt': false,
        'offline.recruitment': false,
        'offline.recruitment.candidates': false,
        'offline.recruitment.workflow': false,
        'offline.recruitment.assignment': false,
        'offline.read_cache': false,
        'offline.auth.unlock': false,
        'offline.rbac.cache': false,
        'offline.master_data': false,
        'offline.pilot.ops_pages': false
    };

    function mergeFlags(incoming) {
        if (!incoming || typeof incoming !== 'object') {
            return flags;
        }
        Object.keys(incoming).forEach(function (k) {
            flags[k] = !!incoming[k];
        });
        return flags;
    }

    function statusPayload() {
        return {
            enabled: !!flags['offline.enabled'],
            inventory: !!flags['offline.inventory.movements'],
            hr: !!flags['offline.hr.attendance'],
            procurement: !!flags['offline.procurement'],
            procurement_goods_receipt: !!flags['offline.procurement.goods_receipt'],
            recruitment: !!flags['offline.recruitment'],
            recruitment_candidates: !!flags['offline.recruitment.candidates'],
            recruitment_workflow: !!flags['offline.recruitment.workflow'],
            recruitment_assignment: !!flags['offline.recruitment.assignment'],
            read_cache: !!flags['offline.read_cache'],
            auth_unlock: !!flags['offline.auth.unlock'],
            rbac_cache: !!flags['offline.rbac.cache'],
            master_data: !!flags['offline.master_data'],
            pilot_ops_pages: !!flags['offline.pilot.ops_pages'],
            version: '14.2.0'
        };
    }

    function init(options) {
        options = options || {};
        if (options.flags && typeof options.flags === 'object') {
            mergeFlags(options.flags);
        }
        // Already booted: merge flags only (Phase 13.1 — no freeze).
        if (booted) {
            if (root.RatebOfflineEvents) {
                root.RatebOfflineEvents.emit('sdk:flags', statusPayload());
            }
            return statusPayload();
        }
        var enabled = !!flags['offline.enabled'];
        if (root.RatebOfflineQueue) {
            root.RatebOfflineQueue.configure({
                enabled: enabled,
                apiBase: options.apiBase || '',
                clientQueueMax: typeof options.clientQueueMax === 'number'
                    ? options.clientQueueMax
                    : 500
            });
        }
        if (root.RatebOfflineTransport) {
            root.RatebOfflineTransport.configure({ enabled: enabled });
        }
        if (root.RatebOfflineConnectivity) {
            root.RatebOfflineConnectivity.configure({
                probeUrl: options.probeUrl || (options.apiBase ? String(options.apiBase).replace(/\/$/, '') + '/status' : null)
            });
            if (enabled && options.startConnectivity !== false) {
                root.RatebOfflineConnectivity.start();
            }
        }
        if (enabled && root.RatebOfflineReplayScheduler && options.startScheduler !== false) {
            root.RatebOfflineReplayScheduler.start(options.schedulerIntervalMs || 15000);
        }
        booted = true;
        if (root.RatebOfflineEvents) {
            root.RatebOfflineEvents.emit('sdk:ready', statusPayload());
        }
        return statusPayload();
    }

    root.RatebOffline = {
        version: '14.2.0',
        init: init,
        mergeFlags: mergeFlags,
        isBooted: function () { return booted; },
        isEnabled: function () { return !!flags['offline.enabled']; },
        isInventoryEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.inventory.movements']);
        },
        isHrEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.hr.attendance']);
        },
        isProcurementEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.procurement']);
        },
        isProcurementGoodsReceiptEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.procurement']
                && flags['offline.procurement.goods_receipt']);
        },
        isRecruitmentEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.recruitment']);
        },
        isRecruitmentCandidatesEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.recruitment']
                && flags['offline.recruitment.candidates']);
        },
        isRecruitmentWorkflowEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.recruitment']
                && flags['offline.recruitment.workflow']);
        },
        isRecruitmentAssignmentEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.recruitment']
                && flags['offline.recruitment.assignment']);
        },
        isReadCacheEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.read_cache']);
        },
        isAuthUnlockEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.read_cache'] && flags['offline.auth.unlock']);
        },
        isRbacCacheEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.read_cache']
                && flags['offline.auth.unlock']
                && flags['offline.rbac.cache']);
        },
        isMasterDataEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.master_data']);
        },
        isPilotOpsPagesEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.read_cache']
                && flags['offline.pilot.ops_pages']);
        },
        flags: function () { return Object.assign({}, flags); },
        queue: function () { return root.RatebOfflineQueue || null; },
        transport: function () { return root.RatebOfflineTransport || null; },
        connectivity: function () { return root.RatebOfflineConnectivity || null; },
        pos: function () { return root.RatebOfflinePosAdapter || null; },
        inventory: function () { return root.RatebOfflineInventoryAdapter || null; },
        hr: function () { return root.RatebOfflineHrAdapter || null; },
        procurement: function () { return root.RatebOfflineProcurementAdapter || null; },
        recruitment: function () { return root.RatebOfflineRecruitmentAdapter || null; },
        opsForms: function () { return root.RatebOfflineOpsForms || null; },
        shell: function () { return root.RatebOfflineShellAdapter || null; },
        auth: function () { return root.RatebOfflineAuthLock || null; },
        rbac: function () { return root.RatebOfflineRbacCache || null; },
        masterData: function () { return root.RatebOfflineMasterData || null; },
        schema: function () { return root.RatebOfflineSchema || null; },
        deltaPull: function () { return root.RatebOfflineDeltaPull || null; }
    };
})(typeof window !== 'undefined' ? window : globalThis);

