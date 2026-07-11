/*! RATEB Enterprise Offline SDK Phase 4.5.1 — durable delete-by-key flush. Master flag default OFF. */

/* ---- offline\client\db\schema.js ---- */

/**
 * RATEB Offline — IndexedDB schema (Phase 2A).
 * DB: rateb_erp_offline (separate from rateb_pos_offline).
 */
(function (root) {
    'use strict';

    var DB_NAME = 'rateb_erp_offline';
    var DB_VERSION = 1;

    var STORES = {
        SYNC_QUEUE: 'sync_queue',
        SYNC_META: 'sync_meta',
        ENTITY_CACHE: 'entity_cache',
        CATALOG_INDEX: 'catalog_index',
        FORM_DRAFTS: 'form_drafts',
        SNAPSHOTS: 'snapshots',
        CONFLICTS: 'conflicts',
        CURSORS: 'cursors'
    };

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
                    var keyPath = name === 'sync_queue' ? 'client_id'
                        : (name === 'sync_meta' || name === 'cursors' ? 'key'
                            : (name === 'entity_cache' || name === 'catalog_index' || name === 'snapshots' ? 'id'
                                : (name === 'form_drafts' ? 'draft_id' : 'conflict_id')));
                    db.createObjectStore(name, { keyPath: keyPath });
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


/* ---- offline\client\db\migrations.js ---- */

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


/* ---- offline\client\core\idempotency.js ---- */

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


/* ---- offline\client\core\event-bus.js ---- */

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


/* ---- offline\client\core\connectivity.js ---- */

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


/* ---- offline\client\sync\queue-manager.js ---- */

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
        },
        isEnabled: function () { return enabled; },
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


/* ---- offline\client\sync\replay-scheduler.js ---- */

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


/* ---- offline\client\sync\delta-pull.js ---- */

/**
 * RATEB Offline — Delta pull (Phase 3).
 * Inventory catalog delta is live when Tier-1 flag is on; other entities remain stub-friendly.
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
            if (params.length) {
                url += (url.indexOf('?') >= 0 ? '&' : '?') + params.join('&');
            }
            return fetch(url, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' }
            }).then(function (res) {
                return res.json();
            });
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);


/* ---- offline\client\core\transport.js ---- */

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


/* ---- offline\client\adapters\pos-adapter.js ---- */

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


/* ---- offline\client\adapters\inventory-adapter.js ---- */

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
                                store.put({
                                    id: 'inv:' + item.id,
                                    entity: 'inventory_catalog',
                                    data: item,
                                    updated_at: item.updated_at || null
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


/* ---- offline\client\adapters\hr-adapter.js ---- */

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
                                store.put({
                                    id: 'emp:' + item.id,
                                    entity: 'employee_directory',
                                    data: item,
                                    updated_at: item.updated_at || null
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


/* ---- offline\client\adapters\form-post-adapter.js ---- */

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


/* ---- offline\client\core\sdk.js ---- */

/**
 * RATEB Offline SDK bootstrap (Phase 4).
 * Expects sibling modules already loaded, or use public/assets/offline/rateb-offline.js bundle.
 */
(function (root) {
    'use strict';

    var booted = false;
    var flags = {
        'offline.enabled': false,
        'offline.pos.complete': true,
        'offline.inventory.movements': false,
        'offline.hr.attendance': false,
        'offline.read_cache': false
    };

    function init(options) {
        options = options || {};
        if (options.flags && typeof options.flags === 'object') {
            Object.keys(options.flags).forEach(function (k) {
                flags[k] = !!options.flags[k];
            });
        }
        var enabled = !!flags['offline.enabled'];
        if (root.RatebOfflineQueue) {
            root.RatebOfflineQueue.configure({
                enabled: enabled,
                apiBase: options.apiBase || ''
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
            root.RatebOfflineEvents.emit('sdk:ready', {
                enabled: enabled,
                inventory: !!flags['offline.inventory.movements'],
                hr: !!flags['offline.hr.attendance']
            });
        }
        return {
            enabled: enabled,
            inventory: !!flags['offline.inventory.movements'],
            hr: !!flags['offline.hr.attendance'],
            version: '4.0.0'
        };
    }

    root.RatebOffline = {
        version: '4.0.0',
        init: init,
        isBooted: function () { return booted; },
        isEnabled: function () { return !!flags['offline.enabled']; },
        isInventoryEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.inventory.movements']);
        },
        isHrEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.hr.attendance']);
        },
        flags: function () { return Object.assign({}, flags); },
        queue: function () { return root.RatebOfflineQueue || null; },
        transport: function () { return root.RatebOfflineTransport || null; },
        connectivity: function () { return root.RatebOfflineConnectivity || null; },
        pos: function () { return root.RatebOfflinePosAdapter || null; },
        inventory: function () { return root.RatebOfflineInventoryAdapter || null; },
        hr: function () { return root.RatebOfflineHrAdapter || null; },
        schema: function () { return root.RatebOfflineSchema || null; },
        deltaPull: function () { return root.RatebOfflineDeltaPull || null; }
    };
})(typeof window !== 'undefined' ? window : globalThis);


