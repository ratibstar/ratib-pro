/*! RATEB Enterprise Offline SDK Phase 14.2.0 (includes Phases 10-14.2 + 15B + 16B + 17B CRM + 18B Projects + 19B Assets + 20B Approval + 21B EPROC + 22B MFG + 24B Payroll + 25B Quality + 26B Documents + 27B BI; flags default OFF). */

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
    // Never optimistic-online: Chrome + Service Worker often report onLine while Wi‑Fi is off.
    var online = false;
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
        try {
            if (next && typeof navigator !== 'undefined' && navigator.onLine === false) {
                next = false;
            }
        } catch (eNav) { /* ignore */ }
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
        // Bust caches; SW must not treat this as a navigable page hit.
        var url = String(probeUrl);
        url += (url.indexOf('?') >= 0 ? '&' : '?') + '_rateb_probe=' + Date.now();
        return fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json', 'X-Rateb-Connectivity': '1' },
            signal: ctrl ? ctrl.signal : undefined
        }).then(function (res) {
            // Local PHP status must not mark "cloud online" when Wi‑Fi is off.
            try {
                var h = String((self.location && self.location.hostname) || '');
                if ((h === '127.0.0.1' || h === 'localhost' || h === '[::1]')
                    && typeof navigator !== 'undefined' && navigator.onLine === false) {
                    setOnline(false);
                    return false;
                }
            } catch (eLocal) { /* ignore */ }
            try {
                if (typeof navigator !== 'undefined' && navigator.onLine === false) {
                    setOnline(false);
                    return false;
                }
            } catch (eOff) { /* ignore */ }
            if (res && res.headers && String(res.headers.get('X-Rateb-Connectivity-Echo') || '') === '1') {
                setOnline(true);
                return true;
            }
            // Any HTTP response (incl. 404 on status) proves the origin is reachable.
            // Reject SW/cache ghost responses: probes must not be served from Cache API.
            try {
                if (res && res.headers && String(res.headers.get('X-Rateb-Offline') || '') === '1') {
                    setOnline(false);
                    return false;
                }
            } catch (eHdr) { /* ignore */ }
            if (res) {
                setOnline(true);
                return true;
            }
            setOnline(false);
            return false;
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
                // Do NOT optimistic-flip to online: Chrome + Service Worker often fires
                // "online" after cache navigation while Wi‑Fi is still off.
                window.addEventListener('online', function () {
                    probe();
                });
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

    function resolveFlushDeviceId(options) {
        options = options || {};
        if (options.deviceId) {
            return String(options.deviceId);
        }
        if (options.device_id) {
            return String(options.device_id);
        }
        var authLock = root.RatebOfflineAuthLock;
        if (authLock && typeof authLock.getDeviceId === 'function') {
            try {
                var fromLock = authLock.getDeviceId();
                if (fromLock) {
                    return String(fromLock);
                }
            } catch (e0) { /* ignore */ }
        }
        try {
            var fromLs = root.localStorage && root.localStorage.getItem('rateb_erp_device_uuid');
            if (fromLs) {
                return String(fromLs);
            }
        } catch (e1) { /* ignore */ }
        return '';
    }

    function resolveFlushBranchId(options) {
        options = options || {};
        if (options.branchId != null && options.branchId !== '') {
            return parseInt(options.branchId, 10) || 0;
        }
        if (options.branch_id != null && options.branch_id !== '') {
            return parseInt(options.branch_id, 10) || 0;
        }
        try {
            var cfg = root.__RATEB_ERP_SHELL_OFFLINE__ || {};
            return parseInt(cfg.branch_id, 10) || 0;
        } catch (e) {
            return 0;
        }
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
        var deviceId = resolveFlushDeviceId(options);
        var branchId = resolveFlushBranchId(options);
        return listFifo().then(function (queue) {
            if (!queue.length) {
                return { accepted: 0, queueDepth: 0 };
            }
            if (!base) {
                return { error: 'api_base_missing', queueDepth: queue.length };
            }
            if (!deviceId) {
                var missing = new Error('Device not allowed');
                missing.code = 'device_unknown';
                throw missing;
            }
            return fetch(joinUrlPath(base, '/push'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-Token': csrfToken(),
                    'X-Rateb-Device-Id': deviceId
                },
                body: JSON.stringify({
                    device_id: deviceId,
                    branch_id: branchId,
                    items: queue
                }),
                signal: (function () {
                    if (typeof AbortController === 'undefined') {
                        return undefined;
                    }
                    var ctrl = new AbortController();
                    setTimeout(function () {
                        try { ctrl.abort(); } catch (eAbort) { /* ignore */ }
                    }, 8000);
                    return ctrl.signal;
                })()
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
 * RATEB Offline — HR adapter (Phase 4 / Tier 1 + Phase 23B Enterprise HRMS).
 * Phase 4: queues attendance, bulk attendance, and leave drafts when offline.hr.attendance.
 * Phase 23B: queues enterprise HRMS drafts when offline.enabled + offline.hr (+ sub-flags).
 * Module remains `hr`. Does NOT enqueue delete, payroll, payments, approvals, or financial posting.
 */
(function (root) {
    'use strict';

    function flags() {
        if (root.RatebOffline && typeof root.RatebOffline.flags === 'function') {
            return root.RatebOffline.flags() || {};
        }
        return {};
    }

    /** Phase 4 attendance/leave gate. */
    function isAttendanceActive() {
        var f = flags();
        return !!(f['offline.enabled'] && f['offline.hr.attendance']);
    }

    /** Phase 23B enterprise parent gate (offline.hr). */
    function isEnterpriseActive() {
        var f = flags();
        return !!(f['offline.enabled'] && f['offline.hr']);
    }

    /**
     * Adapter status: true when attendance OR enterprise parent is on.
     * Enqueue paths still enforce their own flags (attendance vs enterprise).
     */
    function isActive() {
        return isAttendanceActive() || isEnterpriseActive();
    }

    function isEmployeeActive() {
        var f = flags();
        return !!(isEnterpriseActive() && f['offline.hr.employee']);
    }

    function isTrainingActive() {
        var f = flags();
        return !!(isEnterpriseActive() && f['offline.hr.training']);
    }

    function isPerformanceActive() {
        var f = flags();
        return !!(isEnterpriseActive() && f['offline.hr.performance']);
    }

    function isWorkflowActive() {
        var f = flags();
        return !!(isEnterpriseActive() && f['offline.hr.workflow']);
    }

    function isMasterDataActive() {
        var f = flags();
        return !!(isEnterpriseActive() && f['offline.hr.masterdata']);
    }

    function makeClientId(prefix) {
        var rand = Math.random().toString(36).slice(2, 10);
        return String(prefix || 'hr') + '-' + Date.now() + '-' + rand;
    }

    /** Phase 4 enqueue — requires offline.hr.attendance only. */
    function enqueueAttendanceAction(action, payload, options) {
        options = options || {};
        if (!isAttendanceActive()) {
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

    /** Phase 23B enterprise enqueue — requires offline.hr (+ sub-flags). Never attendance/leave. */
    function enqueueEnterprise(action, payload, options) {
        options = options || {};
        if (!isEnterpriseActive()) {
            return Promise.reject(new Error('hrm_offline_disabled'));
        }
        if ((action === 'employee.create' || action === 'employee.update'
            || action === 'department.create' || action === 'position.create'
            || action === 'organization.create') && !isEmployeeActive()) {
            return Promise.reject(new Error('hrm_employee_offline_disabled'));
        }
        if (action === 'training.create' && !isTrainingActive()) {
            return Promise.reject(new Error('hrm_training_offline_disabled'));
        }
        if ((action === 'performance.create' || action === 'goal.create'
            || action === 'competency.create') && !isPerformanceActive()) {
            return Promise.reject(new Error('hrm_performance_offline_disabled'));
        }
        if (action === 'workflow.transition' && !isWorkflowActive()) {
            return Promise.reject(new Error('hrm_workflow_offline_disabled'));
        }
        // Reject delete / payroll / payments (and related) — never enqueue.
        var lower = String(action || '').toLowerCase();
        if (lower.indexOf('delete') !== -1 || lower.indexOf('payroll') !== -1
            || lower.indexOf('payment') !== -1 || lower.indexOf('attendance') !== -1
            || lower.indexOf('leave') !== -1) {
            return Promise.reject(new Error('hrm_action_not_allowed'));
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
        if (!isAttendanceActive()) {
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

    function pullHrmDirectory(entity, options) {
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

    root.RatebOfflineHrAdapter = {
        isActive: isActive,
        isAttendanceActive: isAttendanceActive,
        isEnterpriseActive: isEnterpriseActive,
        isEmployeeActive: isEmployeeActive,
        isTrainingActive: isTrainingActive,
        isPerformanceActive: isPerformanceActive,
        isWorkflowActive: isWorkflowActive,
        isMasterDataActive: isMasterDataActive,

        // Phase 4 — attendance / leave (offline.hr.attendance only)
        enqueueAttendance: function (payload, options) {
            return enqueueAttendanceAction('attendance.create', payload || {}, options);
        },
        enqueueAttendanceBulk: function (payload, options) {
            return enqueueAttendanceAction('attendance.bulk', payload || {}, options);
        },
        enqueueLeaveDraft: function (payload, options) {
            return enqueueAttendanceAction('leave_request.draft', payload || {}, options);
        },
        pullEmployeeDirectory: pullDirectory,

        // Phase 23B — enterprise HRMS (offline.hr + sub-flags). No delete/payroll/payments.
        enqueueEmployeeCreate: function (payload, options) {
            return enqueueEnterprise('employee.create', payload || {}, options);
        },
        enqueueEmployeeUpdate: function (payload, options) {
            return enqueueEnterprise('employee.update', payload || {}, options);
        },
        enqueueDepartmentCreate: function (payload, options) {
            return enqueueEnterprise('department.create', payload || {}, options);
        },
        enqueuePositionCreate: function (payload, options) {
            return enqueueEnterprise('position.create', payload || {}, options);
        },
        enqueueOrganizationCreate: function (payload, options) {
            return enqueueEnterprise('organization.create', payload || {}, options);
        },
        enqueueTrainingCreate: function (payload, options) {
            return enqueueEnterprise('training.create', payload || {}, options);
        },
        enqueuePerformanceCreate: function (payload, options) {
            return enqueueEnterprise('performance.create', payload || {}, options);
        },
        enqueueGoalCreate: function (payload, options) {
            return enqueueEnterprise('goal.create', payload || {}, options);
        },
        enqueueCompetencyCreate: function (payload, options) {
            return enqueueEnterprise('competency.create', payload || {}, options);
        },
        enqueuePromotionCreate: function (payload, options) {
            return enqueueEnterprise('promotion.create', payload || {}, options);
        },
        enqueueTransferCreate: function (payload, options) {
            return enqueueEnterprise('transfer.create', payload || {}, options);
        },
        enqueueAssignmentCreate: function (payload, options) {
            return enqueueEnterprise('assignment.create', payload || {}, options);
        },
        enqueueWorkflowTransition: function (payload, options) {
            return enqueueEnterprise('workflow.transition', payload || {}, options);
        },
        enqueueCommentCreate: function (payload, options) {
            return enqueueEnterprise('comment.create', payload || {}, options);
        },
        enqueueNoteCreate: function (payload, options) {
            return enqueueEnterprise('note.create', payload || {}, options);
        },
        pullDepartments: function (options) {
            return pullHrmDirectory('hrm_department_directory', options);
        },
        pullPositions: function (options) {
            return pullHrmDirectory('hrm_position_directory', options);
        },

        sync: function (options) {
            options = options || {};
            if (!isActive()) {
                return Promise.resolve({ skipped: true, disabled: true });
            }
            var q = root.RatebOfflineQueue;
            var flush = (q && typeof q.flush === 'function') ? q.flush() : Promise.resolve({ skipped: true });
            return flush.then(function (flushResult) {
                var directoryPromise = isAttendanceActive()
                    ? pullDirectory(options)
                    : Promise.resolve({ items: [], stub: true, skipped: true });
                var deptsPromise = isMasterDataActive()
                    ? pullHrmDirectory('hrm_department_directory', options)
                    : Promise.resolve({ items: [], stub: true, skipped: true });
                var posPromise = isMasterDataActive()
                    ? pullHrmDirectory('hrm_position_directory', options)
                    : Promise.resolve({ items: [], stub: true, skipped: true });
                return Promise.all([directoryPromise, deptsPromise, posPromise]).then(function (parts) {
                    return {
                        flush: flushResult,
                        directory: parts[0],
                        departments: parts[1],
                        positions: parts[2]
                    };
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

/* ---- accounting-adapter.js ---- */
/**
 * RATEB Offline — Accounting adapter (Phase 16B / Tier 1 drafts).
 * Queues journal / workflow / recurring / opening-balance / note drafts via enterprise offline queue.
 * Activated only when offline.enabled + offline.accounting (sub-flags gate children).
 * Does NOT enqueue posting, reverse, period close, payments, bank recon, or ZATCA.
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
        return !!(f['offline.enabled'] && f['offline.accounting']);
    }

    function isJournalsActive() {
        var f = flags();
        return !!(isActive() && f['offline.accounting.journals']);
    }

    function isWorkflowActive() {
        var f = flags();
        return !!(isActive() && f['offline.accounting.workflow']);
    }

    function isMasterDataActive() {
        var f = flags();
        return !!(isActive() && f['offline.accounting.masterdata']);
    }

    function makeClientId(prefix) {
        var rand = Math.random().toString(36).slice(2, 10);
        return String(prefix || 'acc') + '-' + Date.now() + '-' + rand;
    }

    function enqueue(action, payload, options) {
        options = options || {};
        if (!isActive()) {
            return Promise.reject(new Error('accounting_offline_disabled'));
        }
        if ((action === 'journal.create' || action === 'journal.update' || action === 'note.create'
            || action === 'recurring.create' || action === 'opening_balance.create')
            && !isJournalsActive()) {
            return Promise.reject(new Error('accounting_journals_offline_disabled'));
        }
        if (action === 'workflow.transition' && !isWorkflowActive()) {
            return Promise.reject(new Error('accounting_workflow_offline_disabled'));
        }
        var q = root.RatebOfflineQueue;
        if (!q || typeof q.enqueue !== 'function') {
            return Promise.reject(new Error('offline_queue_unavailable'));
        }
        var clientId = options.client_id || options.idempotency_key || makeClientId(action);
        return q.enqueue({
            client_id: clientId,
            idempotency_key: clientId,
            module: 'accounting',
            action: action,
            payload: payload || {},
            version: options.version || 1,
            occurred_at: options.occurred_at || new Date().toISOString()
        });
    }

    var DIRECTORY_PREFIX = {
        chart_of_accounts_directory: 'coa',
        accounting_currency_directory: 'cur',
        accounting_exchange_rate_directory: 'fx',
        accounting_tax_code_directory: 'tax',
        accounting_cost_center_directory: 'cc',
        accounting_profit_center_directory: 'pc',
        accounting_fiscal_period_directory: 'fp'
    };

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
            var delta = (res && res.delta) ? res.delta : res;
            if (delta && Array.isArray(delta.items) && root.RatebOfflineSchema) {
                var prefix = DIRECTORY_PREFIX[entity] || 'acc';
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

    root.RatebOfflineAccountingAdapter = {
        isActive: isActive,
        isJournalsActive: isJournalsActive,
        isWorkflowActive: isWorkflowActive,
        isMasterDataActive: isMasterDataActive,
        enqueue: enqueue,
        enqueueJournalCreate: function (payload, options) {
            return enqueue('journal.create', payload || {}, options);
        },
        enqueueJournalUpdate: function (payload, options) {
            return enqueue('journal.update', payload || {}, options);
        },
        enqueueWorkflowTransition: function (payload, options) {
            return enqueue('workflow.transition', payload || {}, options);
        },
        enqueueRecurringCreate: function (payload, options) {
            return enqueue('recurring.create', payload || {}, options);
        },
        enqueueOpeningBalanceCreate: function (payload, options) {
            return enqueue('opening_balance.create', payload || {}, options);
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
                journals: isJournalsActive(),
                workflow: isWorkflowActive(),
                masterdata: isMasterDataActive()
            };
        },
        pullChartOfAccounts: function (options) {
            return pullDirectory('chart_of_accounts_directory', options);
        },
        pullCurrencies: function (options) {
            return pullDirectory('accounting_currency_directory', options);
        },
        pullExchangeRates: function (options) {
            return pullDirectory('accounting_exchange_rate_directory', options);
        },
        pullTaxCodes: function (options) {
            return pullDirectory('accounting_tax_code_directory', options);
        },
        pullCostCenters: function (options) {
            return pullDirectory('accounting_cost_center_directory', options);
        },
        pullProfitCenters: function (options) {
            return pullDirectory('accounting_profit_center_directory', options);
        },
        pullFiscalPeriods: function (options) {
            return pullDirectory('accounting_fiscal_period_directory', options);
        },
        sync: function (options) {
            options = options || {};
            if (!isActive()) {
                return Promise.resolve({ skipped: true, disabled: true });
            }
            var q = root.RatebOfflineQueue;
            var flush = (q && typeof q.flush === 'function') ? q.flush() : Promise.resolve({ skipped: true });
            return flush.then(function (flushResult) {
                if (!isMasterDataActive()) {
                    return { flush: flushResult, directory: { stub: true }, status: root.RatebOfflineAccountingAdapter.status() };
                }
                return pullDirectory('chart_of_accounts_directory', options).then(function (directory) {
                    return { flush: flushResult, directory: directory, status: root.RatebOfflineAccountingAdapter.status() };
                });
            });
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);

/* ---- crm-adapter.js ---- */
/**
 * RATEB Offline — CRM adapter (Phase 17B / Tier 1 drafts).
 * Queues lead / workflow / activity / opportunity drafts via enterprise offline queue.
 * Activated only when offline.enabled + offline.crm (sub-flags gate children).
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
        return !!(f['offline.enabled'] && f['offline.crm']);
    }

    function isLeadsActive() {
        var f = flags();
        return !!(isActive() && f['offline.crm.leads']);
    }

    function isWorkflowActive() {
        var f = flags();
        return !!(isActive() && f['offline.crm.workflow']);
    }

    function isActivitiesActive() {
        var f = flags();
        return !!(isActive() && f['offline.crm.activities']);
    }

    function isMasterDataActive() {
        var f = flags();
        return !!(isActive() && f['offline.crm.masterdata']);
    }

    function makeClientId(prefix) {
        var rand = Math.random().toString(36).slice(2, 10);
        return String(prefix || 'crm') + '-' + Date.now() + '-' + rand;
    }

    function enqueue(action, payload, options) {
        options = options || {};
        if (!isActive()) {
            return Promise.reject(new Error('crm_offline_disabled'));
        }
        if ((action === 'lead.create' || action === 'lead.update' || action === 'note.create'
            || action === 'contact.create' || action === 'company.create')
            && !isLeadsActive()) {
            return Promise.reject(new Error('crm_leads_offline_disabled'));
        }
        if (action === 'workflow.transition' && !isWorkflowActive()) {
            return Promise.reject(new Error('crm_workflow_offline_disabled'));
        }
        if ((action === 'meeting.create' || action === 'call.create' || action === 'task.create')
            && !isActivitiesActive()) {
            return Promise.reject(new Error('crm_activities_offline_disabled'));
        }
        var q = root.RatebOfflineQueue;
        if (!q || typeof q.enqueue !== 'function') {
            return Promise.reject(new Error('offline_queue_unavailable'));
        }
        var clientId = options.client_id || options.idempotency_key || makeClientId(action);
        return q.enqueue({
            client_id: clientId,
            idempotency_key: clientId,
            module: 'crm',
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

    root.RatebOfflineCrmAdapter = {
        isActive: isActive,
        isLeadsActive: isLeadsActive,
        isWorkflowActive: isWorkflowActive,
        isActivitiesActive: isActivitiesActive,
        isMasterDataActive: isMasterDataActive,
        enqueue: enqueue,
        enqueueLeadCreate: function (payload, options) {
            return enqueue('lead.create', payload || {}, options);
        },
        enqueueLeadUpdate: function (payload, options) {
            return enqueue('lead.update', payload || {}, options);
        },
        enqueueWorkflowTransition: function (payload, options) {
            return enqueue('workflow.transition', payload || {}, options);
        },
        enqueueOpportunityCreate: function (payload, options) {
            return enqueue('opportunity.create', payload || {}, options);
        },
        enqueueMeetingCreate: function (payload, options) {
            return enqueue('meeting.create', payload || {}, options);
        },
        enqueueCallCreate: function (payload, options) {
            return enqueue('call.create', payload || {}, options);
        },
        enqueueTaskCreate: function (payload, options) {
            return enqueue('task.create', payload || {}, options);
        },
        enqueueNoteCreate: function (payload, options) {
            return enqueue('note.create', payload || {}, options);
        },
        enqueueAssignmentCreate: function (payload, options) {
            return enqueue('assignment.create', payload || {}, options);
        },
        enqueueCampaignCreate: function (payload, options) {
            return enqueue('campaign.create', payload || {}, options);
        },
        enqueueContactCreate: function (payload, options) {
            return enqueue('contact.create', payload || {}, options);
        },
        enqueueCompanyCreate: function (payload, options) {
            return enqueue('company.create', payload || {}, options);
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
                leads: isLeadsActive(),
                workflow: isWorkflowActive(),
                activities: isActivitiesActive(),
                masterdata: isMasterDataActive()
            };
        },
        pullLeadSources: function (options) {
            return pullDirectory('crm_lead_source_directory', options);
        },
        pullPipelineStages: function (options) {
            return pullDirectory('crm_pipeline_stage_directory', options);
        },
        pullTags: function (options) {
            return pullDirectory('crm_tag_directory', options);
        },
        pullCompanies: function (options) {
            return pullDirectory('crm_company_directory', options);
        },
        sync: function (options) {
            options = options || {};
            if (!isActive()) {
                return Promise.resolve({ skipped: true, disabled: true });
            }
            var q = root.RatebOfflineQueue;
            var flush = (q && typeof q.flush === 'function') ? q.flush() : Promise.resolve({ skipped: true });
            return flush.then(function (flushResult) {
                return {
                    flush: flushResult,
                    status: root.RatebOfflineCrmAdapter.status()
                };
            });
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);

/* ---- projects-adapter.js ---- */
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

/* ---- assets-adapter.js ---- */
/**
 * RATEB Offline — Assets adapter (Phase 19B / Tier 1 drafts).
 * Queues asset / maintenance / workflow / inspection drafts via enterprise offline queue.
 * Activated only when offline.enabled + offline.assets (sub-flags gate children).
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
        return !!(f['offline.enabled'] && f['offline.assets']);
    }

    function isMaintenanceActive() {
        var f = flags();
        return !!(isActive() && f['offline.assets.maintenance']);
    }

    function isWorkflowActive() {
        var f = flags();
        return !!(isActive() && f['offline.assets.workflow']);
    }

    function isInspectionsActive() {
        var f = flags();
        return !!(isActive() && f['offline.assets.inspections']);
    }

    function isMasterDataActive() {
        var f = flags();
        return !!(isActive() && f['offline.assets.masterdata']);
    }

    function makeClientId(prefix) {
        var rand = Math.random().toString(36).slice(2, 10);
        return String(prefix || 'eam') + '-' + Date.now() + '-' + rand;
    }

    function enqueue(action, payload, options) {
        options = options || {};
        if (!isActive()) {
            return Promise.reject(new Error('assets_offline_disabled'));
        }
        if ((action === 'maintenance_request.create'
            || action === 'maintenance_plan.create'
            || action === 'work_order.create') && !isMaintenanceActive()) {
            return Promise.reject(new Error('assets_maintenance_offline_disabled'));
        }
        if (action === 'workflow.transition' && !isWorkflowActive()) {
            return Promise.reject(new Error('assets_workflow_offline_disabled'));
        }
        if ((action === 'inspection.create'
            || action === 'checklist.create'
            || action === 'meter_reading.create') && !isInspectionsActive()) {
            return Promise.reject(new Error('assets_inspections_offline_disabled'));
        }
        var q = root.RatebOfflineQueue;
        if (!q || typeof q.enqueue !== 'function') {
            return Promise.reject(new Error('offline_queue_unavailable'));
        }
        var clientId = options.client_id || options.idempotency_key || makeClientId(action);
        return q.enqueue({
            client_id: clientId,
            idempotency_key: clientId,
            module: 'assets',
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

    root.RatebOfflineAssetsAdapter = {
        isActive: isActive,
        isMaintenanceActive: isMaintenanceActive,
        isWorkflowActive: isWorkflowActive,
        isInspectionsActive: isInspectionsActive,
        isMasterDataActive: isMasterDataActive,
        enqueue: enqueue,
        enqueueAssetCreate: function (payload, options) {
            return enqueue('asset.create', payload || {}, options);
        },
        enqueueAssetUpdate: function (payload, options) {
            return enqueue('asset.update', payload || {}, options);
        },
        enqueueWorkflowTransition: function (payload, options) {
            return enqueue('workflow.transition', payload || {}, options);
        },
        enqueueAssignmentCreate: function (payload, options) {
            return enqueue('assignment.create', payload || {}, options);
        },
        enqueueTransferCreate: function (payload, options) {
            return enqueue('transfer.create', payload || {}, options);
        },
        enqueueMaintenanceRequestCreate: function (payload, options) {
            return enqueue('maintenance_request.create', payload || {}, options);
        },
        enqueueMaintenancePlanCreate: function (payload, options) {
            return enqueue('maintenance_plan.create', payload || {}, options);
        },
        enqueueWorkOrderCreate: function (payload, options) {
            return enqueue('work_order.create', payload || {}, options);
        },
        enqueueInspectionCreate: function (payload, options) {
            return enqueue('inspection.create', payload || {}, options);
        },
        enqueueChecklistCreate: function (payload, options) {
            return enqueue('checklist.create', payload || {}, options);
        },
        enqueueMeterReadingCreate: function (payload, options) {
            return enqueue('meter_reading.create', payload || {}, options);
        },
        enqueueCommentCreate: function (payload, options) {
            return enqueue('comment.create', payload || {}, options);
        },
        enqueueActivityCreate: function (payload, options) {
            return enqueue('activity.create', payload || {}, options);
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
            return q.retryFailed({ module: 'assets' });
        },
        status: function () {
            var q = root.RatebOfflineQueue;
            if (!q || typeof q.status !== 'function') {
                return Promise.resolve({ pending: 0, failed: 0, module: 'assets' });
            }
            return q.status({ module: 'assets' });
        },
        sync: function () {
            var transport = root.RatebOfflineTransport;
            if (!transport || typeof transport.flush !== 'function') {
                return Promise.reject(new Error('offline_transport_unavailable'));
            }
            return transport.flush({ module: 'assets' });
        },
        pullCategories: function (options) {
            return pullDirectory('asset_category_directory', options);
        },
        pullManufacturers: function (options) {
            return pullDirectory('asset_manufacturer_directory', options);
        },
        pullLocations: function (options) {
            return pullDirectory('asset_location_directory', options);
        },
        pullModels: function (options) {
            return pullDirectory('asset_model_directory', options);
        },
        pullMaintenancePlans: function (options) {
            return pullDirectory('maintenance_plan_directory', options);
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);

/* ---- approval-adapter.js ---- */
/**
 * RATEB Offline — Approval adapter (Phase 20B / Tier 1 drafts).
 * Queues approval request / workflow / comment / delegation drafts via enterprise offline queue.
 * Activated only when offline.enabled + offline.approval (sub-flags gate children).
 * Does NOT enqueue decision actions, escalate, notifications, attachments, email/SMS, payments, or government APIs.
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
        return !!(f['offline.enabled'] && f['offline.approval']);
    }

    function isRequestsActive() {
        var f = flags();
        return !!(isActive() && f['offline.approval.requests']);
    }

    function isWorkflowActive() {
        var f = flags();
        return !!(isActive() && f['offline.approval.workflow']);
    }

    function isMasterDataActive() {
        var f = flags();
        return !!(isActive() && f['offline.approval.masterdata']);
    }

    function makeClientId(prefix) {
        var rand = Math.random().toString(36).slice(2, 10);
        return String(prefix || 'eap') + '-' + Date.now() + '-' + rand;
    }

    function enqueue(action, payload, options) {
        options = options || {};
        if (!isActive()) {
            return Promise.reject(new Error('approval_offline_disabled'));
        }
        if ((action === 'approval_request.create' || action === 'approval_request.update')
            && !isRequestsActive()) {
            return Promise.reject(new Error('approval_requests_offline_disabled'));
        }
        if (action === 'workflow.transition' && !isWorkflowActive()) {
            return Promise.reject(new Error('approval_workflow_offline_disabled'));
        }
        var q = root.RatebOfflineQueue;
        if (!q || typeof q.enqueue !== 'function') {
            return Promise.reject(new Error('offline_queue_unavailable'));
        }
        var clientId = options.client_id || options.idempotency_key || makeClientId(action);
        return q.enqueue({
            client_id: clientId,
            idempotency_key: clientId,
            module: 'approval',
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

    root.RatebOfflineApprovalAdapter = {
        isActive: isActive,
        isRequestsActive: isRequestsActive,
        isWorkflowActive: isWorkflowActive,
        isMasterDataActive: isMasterDataActive,
        enqueue: enqueue,
        enqueueRequestCreate: function (payload, options) {
            return enqueue('approval_request.create', payload || {}, options);
        },
        enqueueRequestUpdate: function (payload, options) {
            return enqueue('approval_request.update', payload || {}, options);
        },
        enqueueWorkflowTransition: function (payload, options) {
            return enqueue('workflow.transition', payload || {}, options);
        },
        enqueueCommentCreate: function (payload, options) {
            return enqueue('comment.create', payload || {}, options);
        },
        enqueueDelegationCreate: function (payload, options) {
            return enqueue('delegation.create', payload || {}, options);
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
            return q.retryFailed({ module: 'approval' });
        },
        status: function () {
            var q = root.RatebOfflineQueue;
            if (!q || typeof q.status !== 'function') {
                return Promise.resolve({ pending: 0, failed: 0, module: 'approval' });
            }
            return q.status({ module: 'approval' });
        },
        sync: function () {
            var transport = root.RatebOfflineTransport;
            if (!transport || typeof transport.flush !== 'function') {
                return Promise.reject(new Error('offline_transport_unavailable'));
            }
            return transport.flush({ module: 'approval' });
        },
        pullTemplates: function (options) {
            return pullDirectory('approval_template_directory', options);
        },
        pullChains: function (options) {
            return pullDirectory('approval_chain_directory', options);
        },
        pullStages: function (options) {
            return pullDirectory('approval_stage_directory', options);
        },
        pullRules: function (options) {
            return pullDirectory('approval_rule_directory', options);
        },
        pullDelegations: function (options) {
            return pullDirectory('approval_delegation_directory', options);
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);

/* ---- procurement-enterprise-adapter.js ---- */
/**
 * RATEB Offline — Enterprise Procurement adapter (Phase 21B / Tier 1 drafts).
 * Queues EPROC supplier / tender / contract / workflow drafts via enterprise offline queue.
 * Activated only when offline.enabled + offline.procurement_enterprise (sub-flags gate children).
 * Distinct from legacy RatebOfflineProcurementAdapter (PR/PO/RFQ).
 * Does NOT enqueue delete, payments, approvals, notifications, email/SMS, attachments, or government APIs.
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
        return !!(f['offline.enabled'] && f['offline.procurement_enterprise']);
    }

    function isSuppliersActive() {
        var f = flags();
        return !!(isActive() && f['offline.procurement_enterprise.suppliers']);
    }

    function isTendersActive() {
        var f = flags();
        return !!(isActive() && f['offline.procurement_enterprise.tenders']);
    }

    function isContractsActive() {
        var f = flags();
        return !!(isActive() && f['offline.procurement_enterprise.contracts']);
    }

    function isWorkflowActive() {
        var f = flags();
        return !!(isActive() && f['offline.procurement_enterprise.workflow']);
    }

    function isMasterDataActive() {
        var f = flags();
        return !!(isActive() && f['offline.procurement_enterprise.masterdata']);
    }

    function makeClientId(prefix) {
        var rand = Math.random().toString(36).slice(2, 10);
        return String(prefix || 'eproc') + '-' + Date.now() + '-' + rand;
    }

    function enqueue(action, payload, options) {
        options = options || {};
        if (!isActive()) {
            return Promise.reject(new Error('procurement_enterprise_offline_disabled'));
        }
        if ((action === 'supplier_profile.create'
            || action === 'supplier_profile.update'
            || action === 'qualification.create'
            || action === 'qualification.update'
            || action === 'risk.create'
            || action === 'scorecard.create'
            || action === 'portal_invite.create'
            || action === 'collaboration.create') && !isSuppliersActive()) {
            return Promise.reject(new Error('procurement_enterprise_suppliers_offline_disabled'));
        }
        if ((action === 'tender.create'
            || action === 'bid.create'
            || action === 'bid_compare.create') && !isTendersActive()) {
            return Promise.reject(new Error('procurement_enterprise_tenders_offline_disabled'));
        }
        if (action === 'contract.create' && !isContractsActive()) {
            return Promise.reject(new Error('procurement_enterprise_contracts_offline_disabled'));
        }
        if (action === 'workflow.transition' && !isWorkflowActive()) {
            return Promise.reject(new Error('procurement_enterprise_workflow_offline_disabled'));
        }
        var q = root.RatebOfflineQueue;
        if (!q || typeof q.enqueue !== 'function') {
            return Promise.reject(new Error('offline_queue_unavailable'));
        }
        var clientId = options.client_id || options.idempotency_key || makeClientId(action);
        return q.enqueue({
            client_id: clientId,
            idempotency_key: clientId,
            module: 'procurement_enterprise',
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

    root.RatebOfflineProcurementEnterpriseAdapter = {
        isActive: isActive,
        isSuppliersActive: isSuppliersActive,
        isTendersActive: isTendersActive,
        isContractsActive: isContractsActive,
        isWorkflowActive: isWorkflowActive,
        isMasterDataActive: isMasterDataActive,
        enqueue: enqueue,
        enqueueSupplierProfileCreate: function (payload, options) {
            return enqueue('supplier_profile.create', payload || {}, options);
        },
        enqueueSupplierProfileUpdate: function (payload, options) {
            return enqueue('supplier_profile.update', payload || {}, options);
        },
        enqueueQualificationCreate: function (payload, options) {
            return enqueue('qualification.create', payload || {}, options);
        },
        enqueueQualificationUpdate: function (payload, options) {
            return enqueue('qualification.update', payload || {}, options);
        },
        enqueueRiskCreate: function (payload, options) {
            return enqueue('risk.create', payload || {}, options);
        },
        enqueueScorecardCreate: function (payload, options) {
            return enqueue('scorecard.create', payload || {}, options);
        },
        enqueuePortalInviteCreate: function (payload, options) {
            return enqueue('portal_invite.create', payload || {}, options);
        },
        enqueueTenderCreate: function (payload, options) {
            return enqueue('tender.create', payload || {}, options);
        },
        enqueueBidCreate: function (payload, options) {
            return enqueue('bid.create', payload || {}, options);
        },
        enqueueBidCompareCreate: function (payload, options) {
            return enqueue('bid_compare.create', payload || {}, options);
        },
        enqueueContractCreate: function (payload, options) {
            return enqueue('contract.create', payload || {}, options);
        },
        enqueueCollaborationCreate: function (payload, options) {
            return enqueue('collaboration.create', payload || {}, options);
        },
        enqueueAssignmentCreate: function (payload, options) {
            return enqueue('assignment.create', payload || {}, options);
        },
        enqueueCommentCreate: function (payload, options) {
            return enqueue('comment.create', payload || {}, options);
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
            return q.retryFailed({ module: 'procurement_enterprise' });
        },
        status: function () {
            var q = root.RatebOfflineQueue;
            if (!q || typeof q.status !== 'function') {
                return Promise.resolve({ pending: 0, failed: 0, module: 'procurement_enterprise' });
            }
            return q.status({ module: 'procurement_enterprise' });
        },
        sync: function () {
            var transport = root.RatebOfflineTransport;
            if (!transport || typeof transport.flush !== 'function') {
                return Promise.reject(new Error('offline_transport_unavailable'));
            }
            return transport.flush({ module: 'procurement_enterprise' });
        },
        pullCategories: function (options) {
            return pullDirectory('eproc_supplier_category_directory', options);
        },
        pullRfqTemplates: function (options) {
            return pullDirectory('eproc_rfq_template_directory', options);
        },
        pullTags: function (options) {
            return pullDirectory('eproc_tag_directory', options);
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);

/* ---- manufacturing-adapter.js ---- */
/**
 * RATEB Offline — Enterprise Manufacturing adapter (Phase 22B / Tier 1 drafts).
 * Queues MFG BOM / routing / production / work order / material / quality drafts.
 * Activated only when offline.enabled + offline.manufacturing (sub-flags gate children).
 * Does NOT enqueue delete, inventory posting, GL, payments, approvals, email/SMS, or binary uploads.
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
        return !!(f['offline.enabled'] && f['offline.manufacturing']);
    }

    function isProductionActive() {
        var f = flags();
        return !!(isActive() && f['offline.manufacturing.production']);
    }

    function isWorkflowActive() {
        var f = flags();
        return !!(isActive() && f['offline.manufacturing.workflow']);
    }

    function isQualityActive() {
        var f = flags();
        return !!(isActive() && f['offline.manufacturing.quality']);
    }

    function isMasterDataActive() {
        var f = flags();
        return !!(isActive() && f['offline.manufacturing.masterdata']);
    }

    function makeClientId(prefix) {
        var rand = Math.random().toString(36).slice(2, 10);
        return String(prefix || 'mfg') + '-' + Date.now() + '-' + rand;
    }

    function enqueue(action, payload, options) {
        options = options || {};
        if (!isActive()) {
            return Promise.reject(new Error('manufacturing_offline_disabled'));
        }
        if ((action === 'bom.create' || action === 'bom.update'
            || action === 'routing.create' || action === 'routing.update'
            || action === 'production_order.create' || action === 'production_order.update'
            || action === 'work_order.create' || action === 'work_order.update'
            || action === 'material_reservation.create' || action === 'material_consumption.create'
            || action === 'finished_goods.create' || action === 'scrap.create') && !isProductionActive()) {
            return Promise.reject(new Error('manufacturing_production_offline_disabled'));
        }
        if (action === 'workflow.transition' && !isWorkflowActive()) {
            return Promise.reject(new Error('manufacturing_workflow_offline_disabled'));
        }
        if (action === 'quality_check.create' && !isQualityActive()) {
            return Promise.reject(new Error('manufacturing_quality_offline_disabled'));
        }
        var q = root.RatebOfflineQueue;
        if (!q || typeof q.enqueue !== 'function') {
            return Promise.reject(new Error('offline_queue_unavailable'));
        }
        var clientId = options.client_id || options.idempotency_key || makeClientId(action);
        return q.enqueue({
            client_id: clientId,
            idempotency_key: clientId,
            module: 'manufacturing',
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

    root.RatebOfflineManufacturingAdapter = {
        isActive: isActive,
        isProductionActive: isProductionActive,
        isWorkflowActive: isWorkflowActive,
        isQualityActive: isQualityActive,
        isMasterDataActive: isMasterDataActive,
        enqueue: enqueue,
        enqueueBomCreate: function (payload, options) {
            return enqueue('bom.create', payload || {}, options);
        },
        enqueueBomUpdate: function (payload, options) {
            return enqueue('bom.update', payload || {}, options);
        },
        enqueueRoutingCreate: function (payload, options) {
            return enqueue('routing.create', payload || {}, options);
        },
        enqueueRoutingUpdate: function (payload, options) {
            return enqueue('routing.update', payload || {}, options);
        },
        enqueueProductionOrderCreate: function (payload, options) {
            return enqueue('production_order.create', payload || {}, options);
        },
        enqueueProductionOrderUpdate: function (payload, options) {
            return enqueue('production_order.update', payload || {}, options);
        },
        enqueueWorkOrderCreate: function (payload, options) {
            return enqueue('work_order.create', payload || {}, options);
        },
        enqueueWorkOrderUpdate: function (payload, options) {
            return enqueue('work_order.update', payload || {}, options);
        },
        enqueueWorkflowTransition: function (payload, options) {
            return enqueue('workflow.transition', payload || {}, options);
        },
        enqueueMaterialReservationCreate: function (payload, options) {
            return enqueue('material_reservation.create', payload || {}, options);
        },
        enqueueMaterialConsumptionCreate: function (payload, options) {
            return enqueue('material_consumption.create', payload || {}, options);
        },
        enqueueFinishedGoodsCreate: function (payload, options) {
            return enqueue('finished_goods.create', payload || {}, options);
        },
        enqueueScrapCreate: function (payload, options) {
            return enqueue('scrap.create', payload || {}, options);
        },
        enqueueQualityCheckCreate: function (payload, options) {
            return enqueue('quality_check.create', payload || {}, options);
        },
        enqueueCostCreate: function (payload, options) {
            return enqueue('cost.create', payload || {}, options);
        },
        enqueueAssignmentCreate: function (payload, options) {
            return enqueue('assignment.create', payload || {}, options);
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
            return q.retryFailed({ module: 'manufacturing' });
        },
        status: function () {
            var q = root.RatebOfflineQueue;
            if (!q || typeof q.status !== 'function') {
                return Promise.resolve({ pending: 0, failed: 0, module: 'manufacturing' });
            }
            return q.status({ module: 'manufacturing' });
        },
        sync: function () {
            var transport = root.RatebOfflineTransport;
            if (!transport || typeof transport.flush !== 'function') {
                return Promise.reject(new Error('offline_transport_unavailable'));
            }
            return transport.flush({ module: 'manufacturing' });
        },
        pullProducts: function (options) {
            return pullDirectory('mfg_product_directory', options);
        },
        pullWorkCenters: function (options) {
            return pullDirectory('mfg_work_center_directory', options);
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);

/* ---- payroll-adapter.js ---- */
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

/* ---- quality-adapter.js ---- */
/**
 * RATEB Offline — Enterprise Quality (QMS) adapter (Phase 25B / Tier 1 drafts).
 * Queues inspection / checklist / audit / defect / CAPA / complaint drafts.
 * Activated only when offline.enabled + offline.quality (sub-flags gate children).
 * Does NOT enqueue delete, attachments, binary uploads, notifications, email/SMS,
 * payments, government, approvals, inventory posting, or GL posting.
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
        return !!(f['offline.enabled'] && f['offline.quality']);
    }

    function isInspectionsActive() {
        var f = flags();
        return !!(isActive() && f['offline.quality.inspections']);
    }

    function isAuditActive() {
        var f = flags();
        return !!(isActive() && f['offline.quality.audit']);
    }

    function isWorkflowActive() {
        var f = flags();
        return !!(isActive() && f['offline.quality.workflow']);
    }

    function isMasterDataActive() {
        var f = flags();
        return !!(isActive() && f['offline.quality.masterdata']);
    }

    function makeClientId(prefix) {
        var rand = Math.random().toString(36).slice(2, 10);
        return String(prefix || 'qms') + '-' + Date.now() + '-' + rand;
    }

    function enqueue(action, payload, options) {
        options = options || {};
        if (!isActive()) {
            return Promise.reject(new Error('quality_offline_disabled'));
        }
        if ((action === 'inspection.create' || action === 'inspection.update'
            || action === 'checklist.create') && !isInspectionsActive()) {
            return Promise.reject(new Error('quality_inspections_offline_disabled'));
        }
        if (action === 'audit.create' && !isAuditActive()) {
            return Promise.reject(new Error('quality_audit_offline_disabled'));
        }
        if (action === 'workflow.transition' && !isWorkflowActive()) {
            return Promise.reject(new Error('quality_workflow_offline_disabled'));
        }
        if (action === 'delete' || action === 'attachment.create' || action === 'upload'
            || action === 'payment.create' || action === 'accounting.post') {
            return Promise.reject(new Error('quality_action_rejected'));
        }
        var q = root.RatebOfflineQueue;
        if (!q || typeof q.enqueue !== 'function') {
            return Promise.reject(new Error('offline_queue_unavailable'));
        }
        var clientId = options.client_id || options.idempotency_key || makeClientId(action);
        return q.enqueue({
            client_id: clientId,
            idempotency_key: clientId,
            module: 'quality',
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

    root.RatebOfflineQualityAdapter = {
        isActive: isActive,
        isInspectionsActive: isInspectionsActive,
        isAuditActive: isAuditActive,
        isWorkflowActive: isWorkflowActive,
        isMasterDataActive: isMasterDataActive,
        enqueue: enqueue,
        enqueueInspectionCreate: function (payload, options) {
            return enqueue('inspection.create', payload || {}, options);
        },
        enqueueInspectionUpdate: function (payload, options) {
            return enqueue('inspection.update', payload || {}, options);
        },
        enqueueChecklistCreate: function (payload, options) {
            return enqueue('checklist.create', payload || {}, options);
        },
        enqueueAuditCreate: function (payload, options) {
            return enqueue('audit.create', payload || {}, options);
        },
        enqueueDefectCreate: function (payload, options) {
            return enqueue('defect.create', payload || {}, options);
        },
        enqueueNonconformityCreate: function (payload, options) {
            return enqueue('nonconformity.create', payload || {}, options);
        },
        enqueueCorrectiveActionCreate: function (payload, options) {
            return enqueue('corrective_action.create', payload || {}, options);
        },
        enqueuePreventiveActionCreate: function (payload, options) {
            return enqueue('preventive_action.create', payload || {}, options);
        },
        enqueueSupplierQualityCreate: function (payload, options) {
            return enqueue('supplier_quality.create', payload || {}, options);
        },
        enqueueComplaintCreate: function (payload, options) {
            return enqueue('complaint.create', payload || {}, options);
        },
        enqueueAssignmentCreate: function (payload, options) {
            return enqueue('assignment.create', payload || {}, options);
        },
        enqueueCommentCreate: function (payload, options) {
            return enqueue('comment.create', payload || {}, options);
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
            return q.retryFailed({ module: 'quality' });
        },
        status: function () {
            var q = root.RatebOfflineQueue;
            if (!q || typeof q.status !== 'function') {
                return Promise.resolve({ pending: 0, failed: 0, module: 'quality' });
            }
            return q.status({ module: 'quality' });
        },
        sync: function () {
            var transport = root.RatebOfflineTransport;
            if (!transport || typeof transport.flush !== 'function') {
                return Promise.reject(new Error('offline_transport_unavailable'));
            }
            return transport.flush({ module: 'quality' });
        },
        pullPlans: function (options) {
            return pullDirectory('quality_plan_directory', options);
        },
        pullChecklists: function (options) {
            return pullDirectory('quality_checklist_directory', options);
        },
        pullStandards: function (options) {
            return pullDirectory('quality_standard_directory', options);
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);

/* ---- documents-adapter.js ---- */
/**
 * RATEB Offline — Enterprise Documents (DMS) adapter (Phase 26B / Tier 1 drafts).
 * Queues repository / folder / document / version / checkout / share / permission drafts.
 * Activated only when offline.enabled + offline.documents (sub-flags gate children).
 * Does NOT enqueue delete, upload, attachments, binary, notifications, email/SMS,
 * payments, signature, publish, approve, or download.
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
        return !!(f['offline.enabled'] && f['offline.documents']);
    }

    function isRepositoriesActive() {
        var f = flags();
        return !!(isActive() && f['offline.documents.repositories']);
    }

    function isWorkflowActive() {
        var f = flags();
        return !!(isActive() && f['offline.documents.workflow']);
    }

    function isMasterDataActive() {
        var f = flags();
        return !!(isActive() && f['offline.documents.masterdata']);
    }

    function makeClientId(prefix) {
        var rand = Math.random().toString(36).slice(2, 10);
        return String(prefix || 'dms') + '-' + Date.now() + '-' + rand;
    }

    function enqueue(action, payload, options) {
        options = options || {};
        if (!isActive()) {
            return Promise.reject(new Error('documents_offline_disabled'));
        }
        if ((action === 'repository.create' || action === 'repository.update'
            || action === 'folder.create' || action === 'folder.update') && !isRepositoriesActive()) {
            return Promise.reject(new Error('documents_repositories_offline_disabled'));
        }
        if (action === 'workflow.transition' && !isWorkflowActive()) {
            return Promise.reject(new Error('documents_workflow_offline_disabled'));
        }
        if (action === 'delete' || action === 'attachment.create' || action === 'upload'
            || action === 'payment.create' || action === 'accounting.post'
            || action === 'signature.create' || action === 'publish' || action === 'approve'
            || action === 'download' || action === 'email.send' || action === 'sms.send') {
            return Promise.reject(new Error('documents_action_rejected'));
        }
        var q = root.RatebOfflineQueue;
        if (!q || typeof q.enqueue !== 'function') {
            return Promise.reject(new Error('offline_queue_unavailable'));
        }
        var clientId = options.client_id || options.idempotency_key || makeClientId(action);
        return q.enqueue({
            client_id: clientId,
            idempotency_key: clientId,
            module: 'documents',
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

    root.RatebOfflineDocumentsAdapter = {
        isActive: isActive,
        isRepositoriesActive: isRepositoriesActive,
        isWorkflowActive: isWorkflowActive,
        isMasterDataActive: isMasterDataActive,
        enqueue: enqueue,
        enqueueRepositoryCreate: function (payload, options) {
            return enqueue('repository.create', payload || {}, options);
        },
        enqueueRepositoryUpdate: function (payload, options) {
            return enqueue('repository.update', payload || {}, options);
        },
        enqueueFolderCreate: function (payload, options) {
            return enqueue('folder.create', payload || {}, options);
        },
        enqueueFolderUpdate: function (payload, options) {
            return enqueue('folder.update', payload || {}, options);
        },
        enqueueDocumentCreate: function (payload, options) {
            return enqueue('document.create', payload || {}, options);
        },
        enqueueDocumentUpdate: function (payload, options) {
            return enqueue('document.update', payload || {}, options);
        },
        enqueueVersionCreate: function (payload, options) {
            return enqueue('version.create', payload || {}, options);
        },
        enqueueCheckoutCreate: function (payload, options) {
            return enqueue('checkout.create', payload || {}, options);
        },
        enqueueShareCreate: function (payload, options) {
            return enqueue('share.create', payload || {}, options);
        },
        enqueuePermissionCreate: function (payload, options) {
            return enqueue('permission.create', payload || {}, options);
        },
        enqueueCommentCreate: function (payload, options) {
            return enqueue('comment.create', payload || {}, options);
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
            return q.retryFailed({ module: 'documents' });
        },
        status: function () {
            var q = root.RatebOfflineQueue;
            if (!q || typeof q.status !== 'function') {
                return Promise.resolve({ pending: 0, failed: 0, module: 'documents' });
            }
            return q.status({ module: 'documents' });
        },
        sync: function () {
            var transport = root.RatebOfflineTransport;
            if (!transport || typeof transport.flush !== 'function') {
                return Promise.reject(new Error('offline_transport_unavailable'));
            }
            return transport.flush({ module: 'documents' });
        },
        pullRepositories: function (options) {
            return pullDirectory('documents_repository_directory', options);
        },
        pullCategories: function (options) {
            return pullDirectory('documents_category_directory', options);
        },
        pullWorkflowStatuses: function (options) {
            return pullDirectory('documents_workflow_status_directory', options);
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);

/* ---- bi-adapter.js ---- */
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

/* ---- form-post-adapter.js ---- */
/**
 * RATEB Offline — Safe generic form-post enqueue (Phase 4 expansion).
 * Maps unmatched allowlisted forms to known replay actions via ops form_hooks.
 * Deny-list blocks money / posting / final-approve / payroll calculate.
 * Prefer RatebOfflineOpsForms when it already handles the path.
 */
(function (root) {
    'use strict';

    var DENY_RE = /(?:post|reverse|close[-_]?period|approve|final[-_]?approve|decide|escalate|pay(?:ment)?|payroll[-_]?calc|calculate|transfer[-_]?funds|void[-_]?payment|gl[-_]?post|journal[-_]?post|delete[-_]?permanent)/i;

    function cfg() {
        return root.__RATEB_ERP_SHELL_OFFLINE__ || root.__RATEB_ERP_MASTER_DATA__ || {};
    }

    function flags() {
        if (root.RatebOffline && typeof root.RatebOffline.flags === 'function') {
            return root.RatebOffline.flags() || {};
        }
        return cfg().flags || {};
    }

    function isActive() {
        var f = flags();
        return !!(f['offline.enabled'] && (
            f['offline.pilot.ops_pages']
            || f['offline.inventory.movements']
            || f['offline.hr.attendance']
            || f['offline.procurement']
        ));
    }

    function isOnline() {
        var conn = root.RatebOfflineConnectivity;
        if (conn && typeof conn.isOnline === 'function') {
            return !!conn.isOnline();
        }
        return typeof navigator === 'undefined' || navigator.onLine !== false;
    }

    function hooks() {
        var list = cfg().ops_form_hooks;
        return Array.isArray(list) && list.length ? list : [];
    }

    function matchHook(pathname) {
        var p = String(pathname || '').toLowerCase();
        var list = hooks().slice().sort(function (a, b) {
            return String(b.match || '').length - String(a.match || '').length;
        });
        for (var i = 0; i < list.length; i++) {
            var m = String(list[i].match || '').toLowerCase();
            if (!m) {
                continue;
            }
            if (p.indexOf(m) !== -1) {
                return list[i];
            }
        }
        return null;
    }

    function formDenied(form) {
        if (!form) {
            return true;
        }
        if (form.getAttribute('data-rateb-offline-online-only') === '1') {
            return true;
        }
        var blob = [
            form.getAttribute('action') || '',
            form.getAttribute('id') || '',
            form.getAttribute('name') || '',
            form.getAttribute('data-action') || '',
            form.className || ''
        ].join(' ');
        return DENY_RE.test(blob);
    }

    function serializeForm(form) {
        var data = {};
        if (!form || !form.elements) {
            return data;
        }
        Array.prototype.forEach.call(form.elements, function (el) {
            if (!el || !el.name || el.disabled) {
                return;
            }
            var name = String(el.name);
            if (/^_csrf$/i.test(name) || /token/i.test(name)) {
                return;
            }
            var type = String(el.type || '').toLowerCase();
            if (type === 'submit' || type === 'button' || type === 'file' || type === 'password') {
                return;
            }
            if ((type === 'checkbox' || type === 'radio') && !el.checked) {
                return;
            }
            data[name] = el.value;
        });
        return data;
    }

    function enqueueGeneric(hook, payload) {
        var q = root.RatebOfflineQueue;
        if (!q || typeof q.enqueue !== 'function') {
            return Promise.reject(new Error('queue_unavailable'));
        }
        var Idem = root.RatebOfflineIdempotency;
        var key = Idem && typeof Idem.createKey === 'function'
            ? Idem.createKey(hook.module, hook.action)
            : ('offline:' + hook.module + ':' + hook.action + ':' + Date.now());
        return q.enqueue({
            module: String(hook.module || 'ops'),
            action: String(hook.action || 'form.draft'),
            payload: payload || {},
            idempotency_key: key,
            client_uuid: key
        });
    }

    function handleSubmit(ev) {
        if (!isActive() || isOnline()) {
            return;
        }
        var form = ev.target && ev.target.closest ? ev.target.closest('form') : null;
        if (!form) {
            return;
        }
        if (form.getAttribute('data-rateb-offline-writable') !== '1'
            && form.getAttribute('data-rateb-form-post') !== '1') {
            return;
        }
        if (formDenied(form)) {
            try {
                ev.preventDefault();
                ev.stopPropagation();
            } catch (e0) { /* ignore */ }
            try {
                root.alert('هذا الإجراء يتطلب اتصالاً (ترحيل / اعتماد / دفع).');
            } catch (e1) { /* ignore */ }
            return;
        }
        // Prefer dedicated ops-forms adapter when present.
        if (root.RatebOfflineOpsForms && form.getAttribute('data-rateb-ops-forms-handled') === '1') {
            return;
        }
        var path = (root.location && root.location.pathname) || '';
        var hook = matchHook(path);
        if (!hook) {
            return;
        }
        try {
            ev.preventDefault();
            ev.stopPropagation();
        } catch (e2) { /* ignore */ }
        var payload = serializeForm(form);
        payload._offline_path = path;
        payload._offline_generic = true;
        enqueueGeneric(hook, payload).then(function () {
            try {
                var Events = root.RatebOfflineEvents;
                if (Events && typeof Events.emit === 'function') {
                    Events.emit('queue:enqueued', { module: hook.module, action: hook.action });
                }
            } catch (e3) { /* ignore */ }
            try {
                root.alert('تم حفظ المسودة أوفلاين — ستُزامَن عند عودة الاتصال.');
            } catch (e4) { /* ignore */ }
        }).catch(function (err) {
            try {
                root.alert('تعذر وضع العملية في قائمة الانتظار: ' + String(err && err.message ? err.message : err));
            } catch (e5) { /* ignore */ }
        });
    }

    function bind() {
        if (!isActive() || !root.document) {
            return;
        }
        if (root.document.documentElement.getAttribute('data-rateb-form-post-bound') === '1') {
            return;
        }
        root.document.documentElement.setAttribute('data-rateb-form-post-bound', '1');
        root.document.addEventListener('submit', handleSubmit, true);
    }

    root.RatebOfflineFormPostAdapter = {
        isActive: isActive,
        bind: bind,
        matchHook: matchHook,
        formDenied: formDenied,
        capture: function (form) {
            if (!isActive()) {
                return Promise.reject(new Error('form_post_offline_disabled'));
            }
            if (formDenied(form)) {
                return Promise.reject(new Error('form_post_online_only'));
            }
            var path = (root.location && root.location.pathname) || '';
            var hook = matchHook(path);
            if (!hook) {
                return Promise.reject(new Error('form_post_no_hook'));
            }
            return enqueueGeneric(hook, serializeForm(form));
        }
    };

    if (root.document) {
        if (root.document.readyState === 'loading') {
            root.document.addEventListener('DOMContentLoaded', bind, { once: true });
        } else {
            setTimeout(bind, 0);
        }
    }
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
    var OPS_CACHE = 'rateb-erp-ops-pages-v33';

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
        // Drop live-only overlays that otherwise leak into the warm shell
        out = out.replace(/<div[^>]*(id|class)=["'][^"']*(rateb-modal|rateb-confirm|rateb-loading|rateb-attachments|modal)[^"']*["'][^>]*>[\s\S]*?<\/div>/gi, '');
        // Privileged / dynamic chrome — keep sidebar class so ERP CSS still applies offline
        out = out.replace(/<main\b[^>]*>[\s\S]*?<\/main>/i,
            '<main class="rateb-content rateb-offline-shell-main" id="rateb-offline-shell-main">'
            + '<div class="container py-4 rateb-offline-home">'
            + '<h2 class="h4 mb-2">وضع عدم الاتصال</h2>'
            + '<p class="text-muted mb-3">القائمة والصفحات المحفوظة متاحة للتصفح. البيانات الحية والتعديل يحتاجان اتصالاً.</p>'
            + '<div id="rateb-offline-module-links" class="rateb-offline-module-links"></div>'
            + '<p class="text-muted small mt-3">Offline shell — browse cached modules; reconnect for live data and edits.</p>'
            + '</div></main>');
        // Keep live sidebar structure for offline browse (sanitize dangerous bits only).
        // RBAC may later replace inner HTML when a cached manifest exists.
        out = out.replace(/<aside\b([^>]*)>/gi, function (m, attrs) {
            var a = String(attrs || '');
            if (!/\bclass=/i.test(a)) {
                a += ' class="rateb-sidebar rateb-offline-shell-nav"';
            } else if (!/rateb-offline-shell-nav/i.test(a)) {
                a = a.replace(/\bclass=(["'])([^"']*)\1/i, function (_mm, q, cls) {
                    return 'class=' + q + cls + ' rateb-offline-shell-nav' + q;
                });
            }
            if (!/\bid=/i.test(a)) {
                a += ' id="rateb-sidebar"';
            }
            if (!/aria-label=/i.test(a)) {
                a += ' aria-label="Offline nav"';
            }
            return '<aside' + a + '>';
        });
        // Do NOT wipe nested nav inside sidebar — only clear CSRF/forms via global form strip.
        // Force connection badge to Offline (never freeze "متصل" / Online into the cache).
        out = out.replace(/rateb-connection-indicator\s+is-online/gi, 'rateb-connection-indicator is-offline');
        out = out.replace(/(\sclass=["'][^"']*rateb-connection-indicator)(?![^"']*is-offline)/gi,
            '$1 is-offline');
        out = out.replace(/data-label-online=["'][^"']*["']/gi, 'data-label-online="Online"');
        out = out.replace(/(rateb-connection-indicator__label[^>]*>)\s*[^<]*/gi, '$1غير متصل');
        out = out.replace(/(title|aria-label)=["']\s*(متصل|Online)\s*["']/gi, '$1="غير متصل"');
        out = out.replace(/>\s*متصل\s*</g, '>غير متصل<');
        out = out.replace(/>\s*Online\s*</g, '>Offline<');
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

    /** Money / posting / final-approve — stay online-only even on cached ops pages. */
    var ONLINE_ONLY_FORM_RE = /(?:post|reverse|close[-_]?period|approve|final[-_]?approve|decide|escalate|pay(?:ment)?|payroll[-_]?calc|calculate|transfer[-_]?funds|void[-_]?payment|gl[-_]?post|journal[-_]?post)/i;

    function isOnlineOnlyFormMarkup(formTag) {
        var s = String(formTag || '');
        if (ONLINE_ONLY_FORM_RE.test(s)) {
            return true;
        }
        if (/\b(?:action|data-action|name|id)=["'][^"']*(?:approve|post|pay|decide|escalate|payroll)[^"']*["']/i.test(s)) {
            return true;
        }
        return false;
    }

    function buildOpsOfflineBootScripts() {
        var cfg = root.__RATEB_ERP_SHELL_OFFLINE__ || {};
        var flags = {};
        try {
            flags = (cfg.flags && typeof cfg.flags === 'object') ? cfg.flags : {};
        } catch (e0) {
            flags = {};
        }
        var safe = {
            apiBase: cfg.apiBase || '',
            probeUrl: cfg.probeUrl || '',
            flags: flags,
            startConnectivity: true,
            company_id: parseInt(cfg.company_id, 10) || 0,
            tenant_id: parseInt(cfg.tenant_id || cfg.company_id, 10) || 0,
            branch_id: parseInt(cfg.branch_id, 10) || 0,
            user_id: parseInt(cfg.user_id, 10) || 0,
            is_super_admin: !!cfg.is_super_admin,
            logout_vault_policy: cfg.logout_vault_policy || 'keep_vault',
            session_policy: (cfg.session_policy && typeof cfg.session_policy === 'object') ? cfg.session_policy : {},
            client_queue_max: parseInt(cfg.client_queue_max, 10) || 500,
            ops_page_paths: Array.isArray(cfg.ops_page_paths) ? cfg.ops_page_paths : [],
            ops_page_routes: (cfg.ops_page_routes && typeof cfg.ops_page_routes === 'object') ? cfg.ops_page_routes : {},
            ops_form_hooks: Array.isArray(cfg.ops_form_hooks) ? cfg.ops_form_hooks : [],
            pilot_ops_pages: !!cfg.pilot_ops_pages,
            offline_ops_snapshot: true
        };
        var json;
        try {
            json = JSON.stringify(safe);
        } catch (e1) {
            json = '{}';
        }
        var base = '/rateb-erp/public/';
        try {
            var p = String((root.location && root.location.pathname) || '');
            var m = p.match(/^(.*\/public\/)/i);
            if (m && m[1]) {
                base = m[1];
            }
        } catch (e2) { /* ignore */ }
        return '<script>(function(){try{if(navigator.onLine===false)return;var m=document.querySelector(".rateb-offline-home,#rateb-offline-shell-main,[data-rateb-offline-ops-banner]");if(!m)return;var base=(location.pathname.match(/^(.*\\/public\\/)/i)||[])[1]||"/rateb-erp/public/";fetch(base+"connectivity-probe.json?_rateb_probe="+Date.now(),{credentials:"same-origin",cache:"no-store",headers:{"Accept":"application/json","X-Rateb-Connectivity":"1"}}).then(function(res){if(!res||!res.ok)return;var u=new URL(location.href);u.searchParams.set("rateb_live",String(Date.now()));location.replace(u.href)}).catch(function(){})}catch(e){}})();</script>\n'
            + '<script>window.__RATEB_ERP_SHELL_OFFLINE__=' + json
            + ';window.__RATEB_ERP_MASTER_DATA__=window.__RATEB_ERP_SHELL_OFFLINE__;</script>\n'
            + '<script src="' + base + 'assets/offline/rateb-offline.js" defer></script>\n'
            + '<script src="' + base + 'assets/offline/erp-shell-bootstrap.js" defer></script>\n'
            + '<script src="' + base + 'assets/offline/erp-ops-forms-bootstrap.js" defer></script>\n';
    }

    /** Phase 14+ — keep main; mark writable Tier-1 forms; reinject offline hooks. */
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
        // Writable drafts offline; money/posting/final-approve stay hard-disabled.
        out = out.replace(/<form\b([^>]*)>/gi, function (_m, attrs) {
            var a = String(attrs || '');
            if (isOnlineOnlyFormMarkup(a)) {
                return '<form data-rateb-offline-online-only="1" onsubmit="return false;" ' + a + '>';
            }
            return '<form data-rateb-offline-writable="1" ' + a + '>';
        });
        out = out.replace(
            /<main\b([^>]*)>/i,
            '<main$1><div class="alert alert-info m-3" role="status" data-rateb-offline-ops-banner="1">'
            + 'وضع عدم الاتصال — يمكنك إنشاء مسودات؛ الترحيل/الاعتماد النهائي/المدفوعات تتطلب اتصالاً.'
            + '</div>'
        );
        var boot = buildOpsOfflineBootScripts();
        if (/<\/body>/i.test(out)) {
            out = out.replace(/<\/body>/i, boot + '</body>');
        } else {
            out += boot;
        }
        return out;
    }

    var allowlistLoadPromise = null;

    function opsAllowlist() {
        var cfg = root.__RATEB_ERP_SHELL_OFFLINE__ || {};
        var paths = cfg.ops_page_paths;
        return Array.isArray(paths) ? paths : [];
    }

    /** Logical key → canonical route from rateb_app_route() (injected as ops_page_routes). */
    function opsRouteMap() {
        var cfg = root.__RATEB_ERP_SHELL_OFFLINE__ || {};
        var routes = cfg.ops_page_routes;
        if (!routes || typeof routes !== 'object') {
            return {};
        }
        return routes;
    }

    /** Load paths/routes from allowlist JSON when not inlined in HTML (keeps pages lean). */
    function ensureOpsAllowlist() {
        var cfg = root.__RATEB_ERP_SHELL_OFFLINE__ || {};
        var paths = cfg.ops_page_paths;
        var routes = cfg.ops_page_routes;
        var hasPaths = Array.isArray(paths) && paths.length > 0;
        var hasRoutes = routes && typeof routes === 'object' && Object.keys(routes).length > 0;
        if (hasPaths && hasRoutes) {
            return Promise.resolve(cfg);
        }
        if (allowlistLoadPromise) {
            return allowlistLoadPromise;
        }
        var url = cfg.allowlistUrl;
        if (!url && root.location) {
            try {
                var path = String(root.location.pathname || '');
                var m = path.match(/^(.*?\/rateb-erp\/public)(?:\/|$)/i);
                var prefix = (m && m[1]) ? m[1] : '/rateb-erp/public';
                url = root.location.origin + prefix + '/assets/offline/ops-page-allowlist.json';
            } catch (eUrl) {
                url = '';
            }
        }
        if (!url || !root.fetch) {
            return Promise.resolve(cfg);
        }
        allowlistLoadPromise = root.fetch(String(url), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' }
        }).then(function (res) {
            if (!res || !res.ok) {
                return null;
            }
            return res.json();
        }).then(function (data) {
            if (!data || typeof data !== 'object') {
                return cfg;
            }
            var next = root.__RATEB_ERP_SHELL_OFFLINE__ || cfg;
            if (Array.isArray(data.paths)) {
                next.ops_page_paths = data.paths.map(function (p) {
                    return String(p || '').replace(/^\/+|\/+$/g, '');
                }).filter(Boolean);
            }
            if (data.routes && typeof data.routes === 'object') {
                next.ops_page_routes = data.routes;
            }
            if (Array.isArray(data.form_hooks) && !(next.ops_form_hooks && next.ops_form_hooks.length)) {
                next.ops_form_hooks = data.form_hooks;
            }
            root.__RATEB_ERP_SHELL_OFFLINE__ = next;
            return next;
        }).catch(function () {
            return cfg;
        });
        return allowlistLoadPromise;
    }

    /**
     * Build absolute URL for an allowlist logical key using canonical route only.
     * Never prefixes /admin/ops/ manually.
     */
    function canonicalUrlForLogical(logical) {
        logical = String(logical || '').replace(/^\/+|\/+$/g, '');
        if (!logical) {
            return null;
        }
        var map = opsRouteMap();
        var route = map[logical] ? String(map[logical]).replace(/^\/+|\/+$/g, '') : '';
        if (!route) {
            return null;
        }
        try {
            var origin = (root.location && root.location.origin) || '';
            var prefix = '';
            try {
                var path = String((root.location && root.location.pathname) || '');
                var m = path.match(/^(.*?\/rateb-erp\/public)(?:\/|$)/i);
                if (m && m[1]) {
                    prefix = m[1];
                } else if (/\/rateb-erp\/public/i.test(String((root.location && root.location.href) || ''))) {
                    prefix = '/rateb-erp/public';
                }
            } catch (ePref) { /* ignore */ }
            var href = origin + prefix + '/' + route;
            var companyId = parseInt((root.__RATEB_ERP_SHELL_OFFLINE__ || {}).company_id, 10) || 0;
            if (companyId > 0 && href.indexOf('company_id=') === -1) {
                href += (href.indexOf('?') === -1 ? '?' : '&') + 'company_id=' + companyId;
            }
            return href;
        } catch (e) {
            return null;
        }
    }

    function isHttpErrorDocument() {
        try {
            var title = String((root.document && root.document.title) || '');
            var text = '';
            try {
                text = String((root.document.body && root.document.body.innerText) || '').slice(0, 400);
            } catch (eT) { /* ignore */ }
            if (/^\s*404\b/i.test(title) || /\b404\s*\|/i.test(title)) {
                return true;
            }
            if (/page not found/i.test(text) && /\b404\b/.test(text)) {
                return true;
            }
            var statusMeta = root.document.querySelector('meta[name="rateb-http-status"]');
            if (statusMeta) {
                var st = parseInt(statusMeta.getAttribute('content') || '0', 10) || 0;
                if (st > 0 && st !== 200) {
                    return true;
                }
            }
        } catch (e) { /* ignore */ }
        return false;
    }

    function matchOpsPath(pathname) {
        var p = String(pathname || '').replace(/\/+$/, '').toLowerCase();
        // Exact لوحة التحكم — never treat all /admin/* as dashboard.
        if (/(^|\/)admin$/.test(p)) {
            return 'admin';
        }
        var list = opsAllowlist();
        for (var i = 0; i < list.length; i++) {
            var a = String(list[i] || '').replace(/^\/+|\/+$/g, '').toLowerCase();
            if (!a || a === 'admin') {
                continue;
            }
            var re = new RegExp('(^|/)' + a.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '(/|$)', 'i');
            if (re.test(p)) {
                return a;
            }
        }
        // Also match canonical routes from rateb_app_route() (e.g. admin/hr/attendance).
        var map = opsRouteMap();
        var keys = Object.keys(map || {});
        for (var j = 0; j < keys.length; j++) {
            var logical = keys[j];
            var route = String(map[logical] || '').replace(/^\/+|\/+$/g, '').toLowerCase();
            if (!route) {
                continue;
            }
            var re2 = new RegExp('(^|/)' + route.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '(/|$)', 'i');
            if (re2.test(p)) {
                return logical;
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
        var pathEarly = (root.location && root.location.pathname) || '';
        var isAdmin = /\/admin(\/|$)/i.test(String(pathEarly));
        // Capture any Admin page when visited online (not only allowlisted ops).
        if (!isOpsPagesActive() && !isAdmin) {
            return Promise.resolve({ skipped: true, disabled: true });
        }
        return ensureOpsAllowlist().then(function () {
        var path = (root.location && root.location.pathname) || '';
        if (!matchOpsPath(path) && !/\/admin(\/|$)/i.test(path)) {
            return Promise.resolve({ skipped: true, reason: 'path_not_allowlisted' });
        }
        if (isHttpErrorDocument()) {
            try {
                console.warn('[RATIB OFFLINE] INVALID ROUTE', path, 'document looks like HTTP error; skip capture');
            } catch (eInv) { /* ignore */ }
            return Promise.resolve({ skipped: true, reason: 'invalid_route_http_error' });
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
                    return { ok: true, id: id, bytes: safe.length, path: path };
                });
            });
        } catch (e) {
            return Promise.reject(e);
        }
        });
    }

    function cacheFetchedOpsHtml(href, path, html) {
        var safe = stripSensitiveOpsPage(html);
        var originPath = '';
        try {
            var u = new URL(href, root.location.origin);
            originPath = u.origin + u.pathname;
        } catch (eU) {
            originPath = href;
        }
        return putOpsPageCache(href, safe).then(function () {
            return putOpsPageCache(originPath, safe);
        }).then(function () {
            // Also mirror into SW-managed keys without posting huge HTML bodies.
            return { ok: true, bytes: safe.length, path: path, url: href };
        });
    }

    function prefetchAllowlistedLinks() {
        if (!root.document || !root.fetch) {
            return;
        }
        if (typeof navigator !== 'undefined' && navigator.onLine === false) {
            return;
        }
        // Prefer the dedicated full-warm engine when present.
        try {
            if (root.RatebOfflineFullWarm && typeof root.RatebOfflineFullWarm.start === 'function') {
                root.RatebOfflineFullWarm.start({ force: false });
                return;
            }
        } catch (eFull) { /* fall through */ }
        // Once per browser tab/session — never re-warm 40 ERP pages on every navigation
        // (that saturates PHP/MySQL and makes the whole product feel extremely slow).
        try {
            var warmAt = parseInt(root.sessionStorage.getItem('rateb_erp_ops_warm_at') || '0', 10) || 0;
            if (warmAt > 0 && (Date.now() - warmAt) < (30 * 60 * 1000)) {
                return;
            }
            root.sessionStorage.setItem('rateb_erp_ops_warm_at', String(Date.now()));
        } catch (eGate) { /* ignore and continue once */ }

        ensureOpsAllowlist().then(function () {
        var seen = {};
        var urls = [];

        // Prefer a small priority set first (production UX), then a few more from the map.
        var priority = ['purchase-requests', 'inventory', 'hr/attendance', 'warehouses', 'purchase-orders'];
        var map = opsRouteMap();
        priority.forEach(function (logical) {
            var href = canonicalUrlForLogical(logical);
            if (!href || seen[href]) {
                return;
            }
            seen[href] = true;
            urls.push({ href: href, logical: logical, path: String((map && map[logical]) || '') });
        });

        Object.keys(map || {}).forEach(function (logical) {
            if (urls.length >= 120) {
                return;
            }
            var href = canonicalUrlForLogical(logical);
            if (!href || seen[href]) {
                return;
            }
            seen[href] = true;
            urls.push({ href: href, logical: logical, path: String(map[logical] || '') });
        });

        // Live sidebar links that already match allowlist (fill remaining slots only).
        var links = root.document.querySelectorAll(
            'aside.rateb-sidebar a[href], #rateb-sidebar a[href], .rateb-offline-rbac-link[href]'
        );
        Array.prototype.forEach.call(links, function (a) {
            if (urls.length >= 120) {
                return;
            }
            var href = (a.getAttribute('href') || '').trim();
            if (!href || href === '#' || /^javascript:/i.test(href) || seen[href]) {
                return;
            }
            try {
                var u = new URL(href, root.location.origin);
                if (u.origin !== root.location.origin) {
                    return;
                }
                if (!matchOpsPath(u.pathname)) {
                    return;
                }
                seen[href] = true;
                urls.push({ href: u.href, logical: matchOpsPath(u.pathname), path: u.pathname });
            } catch (e) { /* ignore */ }
        });

        var i = 0;
        var tick = function () {
            if (i >= urls.length) {
                return;
            }
            var next = urls[i++];
            root.fetch(next.href, {
                credentials: 'same-origin',
                headers: { Accept: 'text/html', 'X-Rateb-Shell-Warm': '1' }
            }).then(function (res) {
                var status = res ? res.status : 0;
                if (!res || status !== 200) {
                    try {
                        console.warn('[RATIB OFFLINE] INVALID ROUTE', next.logical || next.path, next.href, 'HTTP', status);
                    } catch (eLog) { /* ignore */ }
                    return null;
                }
                return res.text().then(function (html) {
                    if (!html || /page not found/i.test(String(html).slice(0, 800)) && /\b404\b/.test(String(html).slice(0, 800))) {
                        try {
                            console.warn('[RATIB OFFLINE] INVALID ROUTE', next.logical || next.path, next.href, 'body looks like 404');
                        } catch (e404) { /* ignore */ }
                        return null;
                    }
                    return cacheFetchedOpsHtml(next.href, next.path || next.logical, html);
                });
            }).catch(function () { /* ignore */ }).then(function () {
                // Slow, idle-only warm — never compete with the user's current navigation.
                if (typeof root.requestIdleCallback === 'function') {
                    root.requestIdleCallback(tick, { timeout: 12000 });
                } else {
                    setTimeout(tick, 2500);
                }
            });
        };
        if (typeof root.requestIdleCallback === 'function') {
            root.requestIdleCallback(tick, { timeout: 15000 });
        } else {
            setTimeout(tick, 5000);
        }
        }).catch(function () { /* allowlist fetch failed — skip warm */ });
    }

    function startAutoCapture() {
        if (!isActive()) {
            return;
        }
        var run = function () {
            captureChrome().then(function (res) {
                try {
                    console.info('[RATIB OFFLINE] captureChrome', res || {});
                } catch (e0) { /* ignore */ }
            }).catch(function () { /* ignore */ });
            // Always cache current Admin page when visiting (every module, not only ops pilot).
            var pathNow = (root.location && root.location.pathname) || '';
            if (/\/admin(\/|$)/i.test(pathNow) || isOpsPagesActive()) {
                captureOpsPage().then(function (res) {
                    try {
                        console.info('[RATIB OFFLINE] captureOpsPage', res || {});
                    } catch (e1) { /* ignore */ }
                }).catch(function () { /* ignore */ });
            }
            if (isOpsPagesActive()) {
                prefetchAllowlistedLinks();
            }
            // Full-program warm: every sidebar + allowlist route (no visit required).
            try {
                if (root.RatebOfflineFullWarm && typeof root.RatebOfflineFullWarm.start === 'function') {
                    root.RatebOfflineFullWarm.start({ force: false });
                }
            } catch (eWarm) { /* ignore */ }
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
        prefetchAllowlistedLinks: prefetchAllowlistedLinks,
        canonicalUrlForLogical: canonicalUrlForLogical,
        opsRouteMap: opsRouteMap,
        ensureOpsAllowlist: ensureOpsAllowlist,
        stripSensitive: stripSensitive,
        stripSensitiveOpsPage: stripSensitiveOpsPage
    };
})(typeof window !== 'undefined' ? window : globalThis);


/* ---- auth-lock-adapter.js ---- */
/**
 * RATEB Offline — ERP auth lock adapter (Phase 11 + Phase P1 Warm Identity).
 * Local shell unlock only. Uses rateb_erp_offline / auth_vault (DB_VERSION 2).
 * Never stores passwords / PHP sessions / CSRF / JWT.
 * PIN decrypts sealed warm identity; server remains authoritative for replay.
 */
(function (root) {
    'use strict';

    var PBKDF2_ITERATIONS = 120000;
    var DEFAULT_UNLOCK_TTL_MS = 8 * 60 * 60 * 1000;
    var DEFAULT_IDLE_TIMEOUT_MS = 15 * 60 * 1000;
    var DEFAULT_MAX_OFFLINE_SESSION_MS = 72 * 60 * 60 * 1000;
    var DEFAULT_CLOCK_SKEW_SECONDS = 300;
    var DEVICE_LS_KEY = 'rateb_erp_device_uuid';
    var UNLOCK_UNTIL_PREFIX = 'rateb_erp_unlock_until:';
    var UNLOCK_STARTED_PREFIX = 'rateb_erp_unlock_started:';
    var IDLE_AT_PREFIX = 'rateb_erp_idle_at:';
    var REAUTH_KEY = 'rateb_erp_session_reauth';
    var DEVICE_META_PREFIX = 'auth_device:';
    var SCOPE_LS_KEY = 'rateb_erp_offline_scope';
    var IDENTITY_CLAIMS_SESSION = 'rateb_erp_warm_identity:';
    var SHELL_SNAPSHOT_KIND = 'erp_shell_chrome';
    var RBAC_SNAPSHOT_KIND = 'erp_rbac';

    function cfg() {
        return root.__RATEB_ERP_SHELL_OFFLINE__ || root.__RATEB_ERP_AUTH_OFFLINE__ || {};
    }

    /** Phase P2 — session policy from server policy() snapshot (fail-closed defaults). */
    function sessionPolicy() {
        var p = cfg().session_policy || {};
        return {
            unlock_ttl_ms: parseInt(p.unlock_ttl_ms, 10) > 0 ? parseInt(p.unlock_ttl_ms, 10) : DEFAULT_UNLOCK_TTL_MS,
            idle_timeout_ms: parseInt(p.idle_timeout_ms, 10) > 0 ? parseInt(p.idle_timeout_ms, 10) : DEFAULT_IDLE_TIMEOUT_MS,
            max_offline_session_ms: parseInt(p.max_offline_session_ms, 10) > 0
                ? parseInt(p.max_offline_session_ms, 10)
                : DEFAULT_MAX_OFFLINE_SESSION_MS,
            clock_skew_seconds: parseInt(p.clock_skew_seconds, 10) > 0
                ? parseInt(p.clock_skew_seconds, 10)
                : DEFAULT_CLOCK_SKEW_SECONDS,
            renew_before_seconds: parseInt(p.renew_before_seconds, 10) > 0
                ? parseInt(p.renew_before_seconds, 10)
                : (3 * 24 * 60 * 60)
        };
    }

    function unlockTtlMs() {
        return sessionPolicy().unlock_ttl_ms;
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

    function withSnapshots(mode, fn) {
        var Schema = schema();
        if (!Schema || !Schema.STORES || !Schema.STORES.SNAPSHOTS) {
            return Promise.reject(new Error('snapshots_unavailable'));
        }
        return Schema.withStore(Schema.STORES.SNAPSHOTS, mode, fn);
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
                return { pin_hash: bufToB64(bits), pin_salt: bufToB64(salt.buffer), bits: bits };
            });
    }

    function deriveAesKey(pin, saltB64) {
        return hashPin(pin, saltB64).then(function (hashed) {
            return root.crypto.subtle.importKey('raw', hashed.bits, { name: 'AES-GCM' }, false, ['encrypt', 'decrypt'])
                .then(function (key) {
                    return { key: key, pin_hash: hashed.pin_hash, pin_salt: hashed.pin_salt };
                });
        });
    }

    function sealIdentityPackage(pin, saltB64, identityPackage) {
        var iv = randomBytes(12);
        var plain = new TextEncoder().encode(JSON.stringify(identityPackage || {}));
        return deriveAesKey(pin, saltB64).then(function (derived) {
            return root.crypto.subtle.encrypt({ name: 'AES-GCM', iv: iv }, derived.key, plain).then(function (cipher) {
                return {
                    pin_hash: derived.pin_hash,
                    pin_salt: derived.pin_salt,
                    identity_iv: bufToB64(iv.buffer),
                    identity_cipher: bufToB64(cipher),
                    identity_alg: 'AES-GCM',
                    identity_expires_at: identityPackage && identityPackage.claims
                        ? (parseInt(identityPackage.claims.expires_at, 10) || 0)
                        : 0
                };
            });
        });
    }

    function unsealIdentityPackage(pin, record) {
        if (!record || !record.identity_cipher || !record.identity_iv || !record.pin_salt) {
            return Promise.resolve({ ok: false, error: 'identity_missing' });
        }
        return deriveAesKey(pin, record.pin_salt).then(function (derived) {
            if (derived.pin_hash !== record.pin_hash) {
                return { ok: false, error: 'pin_denied' };
            }
            return root.crypto.subtle.decrypt(
                { name: 'AES-GCM', iv: new Uint8Array(b64ToBuf(record.identity_iv)) },
                derived.key,
                b64ToBuf(record.identity_cipher)
            ).then(function (plainBuf) {
                var json = new TextDecoder().decode(plainBuf);
                var pkg = JSON.parse(json);
                return verifyIdentityLocal(pkg, {
                    company_id: intOr(record.company_id),
                    branch_id: intOr(record.branch_id),
                    user_id: intOr(record.user_id),
                    device_id: String(record.device_id || '')
                }).then(function (v) {
                    if (!v.ok) {
                        return v;
                    }
                    return { ok: true, identity: pkg, claims: v.claims };
                });
            }).catch(function () {
                return { ok: false, error: 'pin_denied' };
            });
        });
    }

    function canonicalClaims(claims) {
        var keys = Object.keys(claims || {}).sort();
        var ordered = {};
        keys.forEach(function (k) { ordered[k] = claims[k]; });
        return JSON.stringify(ordered);
    }

    function hmacSha256(keyBuf, message) {
        return root.crypto.subtle.importKey('raw', keyBuf, { name: 'HMAC', hash: 'SHA-256' }, false, ['sign'])
            .then(function (key) {
                return root.crypto.subtle.sign('HMAC', key, new TextEncoder().encode(message));
            })
            .then(function (sig) {
                return bufToB64(sig);
            });
    }

    function verifyIdentityLocal(pkg, expect) {
        expect = expect || {};
        if (!pkg || !pkg.claims || !pkg.signature || !pkg.identity_key) {
            return Promise.resolve({ ok: false, error: 'identity_incomplete' });
        }
        var claims = pkg.claims;
        if (String(claims.purpose || '') !== 'erp_offline_warm') {
            return Promise.resolve({ ok: false, error: 'identity_purpose' });
        }
        var expiresAt = parseInt(claims.expires_at, 10) || 0;
        if (expiresAt < 1 || expiresAt * 1000 <= Date.now()) {
            return Promise.resolve({ ok: false, error: 'identity_expired' });
        }
        var skew = sessionPolicy().clock_skew_seconds;
        var issuedAt = parseInt(claims.issued_at, 10) || 0;
        var nowSec = Math.floor(Date.now() / 1000);
        if (issuedAt > nowSec + skew) {
            return Promise.resolve({ ok: false, error: 'clock_rollback' });
        }
        var notBefore = parseInt(claims.not_before, 10) || issuedAt;
        if (notBefore > nowSec + skew) {
            return Promise.resolve({ ok: false, error: 'identity_not_before' });
        }
        var antiRollback = parseInt(claims.anti_rollback, 10) || issuedAt;
        if (expect.min_anti_rollback && antiRollback < parseInt(expect.min_anti_rollback, 10)) {
            return Promise.resolve({ ok: false, error: 'anti_rollback' });
        }
        if (expect.min_identity_version
            && (parseInt(claims.identity_version, 10) || 1) < parseInt(expect.min_identity_version, 10)) {
            return Promise.resolve({ ok: false, error: 'identity_version' });
        }
        if (expect.company_id && intOr(claims.company_id) !== expect.company_id) {
            return Promise.resolve({ ok: false, error: 'tenant_mismatch' });
        }
        if (expect.user_id && intOr(claims.user_id) !== expect.user_id) {
            return Promise.resolve({ ok: false, error: 'tenant_mismatch' });
        }
        if (Object.prototype.hasOwnProperty.call(expect, 'branch_id')
            && intOr(claims.branch_id) !== intOr(expect.branch_id)) {
            return Promise.resolve({ ok: false, error: 'branch_mismatch' });
        }
        if (expect.device_id && String(claims.device_id || '') !== String(expect.device_id)) {
            return Promise.resolve({ ok: false, error: 'device_mismatch' });
        }
        var canonical = (typeof pkg.canonical === 'string' && pkg.canonical !== '')
            ? pkg.canonical
            : canonicalClaims(claims);
        return hmacSha256(b64ToBuf(pkg.identity_key), canonical).then(function (sigB64) {
            if (sigB64 !== String(pkg.signature || '')) {
                return { ok: false, error: 'identity_signature' };
            }
            return { ok: true, claims: claims };
        }).catch(function () {
            return { ok: false, error: 'identity_signature' };
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

    function identitySessionKey(scope) {
        return IDENTITY_CLAIMS_SESSION + vaultId(scope);
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

    function unlockStartedKey(scope) {
        return UNLOCK_STARTED_PREFIX + vaultId(scope);
    }

    function idleAtKey(scope) {
        return IDLE_AT_PREFIX + vaultId(scope);
    }

    function markUnlocked(scope, claims) {
        var now = Date.now();
        var policy = sessionPolicy();
        setUnlockUntil(scope, now + policy.unlock_ttl_ms);
        try {
            sessionStorage.setItem(unlockStartedKey(scope), String(now));
            sessionStorage.setItem(idleAtKey(scope), String(now));
            if (claims) {
                sessionStorage.setItem(identitySessionKey(scope), JSON.stringify({
                    company_id: claims.company_id,
                    branch_id: claims.branch_id,
                    user_id: claims.user_id,
                    device_id: claims.device_id,
                    expires_at: claims.expires_at,
                    issued_at: claims.issued_at || 0,
                    identity_version: claims.identity_version || 1,
                    anti_rollback: claims.anti_rollback || claims.issued_at || 0,
                    jti: claims.jti || ''
                }));
            }
        } catch (e) { /* ignore */ }
    }

    function touchIdle(scope) {
        scope = scope || tenantScope();
        try {
            sessionStorage.setItem(idleAtKey(scope), String(Date.now()));
        } catch (e) { /* ignore */ }
    }

    function assertSessionTtl(scope) {
        scope = scope || tenantScope();
        var policy = sessionPolicy();
        var until = unlockUntil(scope);
        if (until <= Date.now()) {
            return { ok: false, error: 'unlock_ttl_expired' };
        }
        try {
            var started = parseInt(sessionStorage.getItem(unlockStartedKey(scope)) || '0', 10) || 0;
            if (started > 0 && (Date.now() - started) > policy.max_offline_session_ms) {
                clearUnlock(scope);
                return { ok: false, error: 'max_offline_session' };
            }
            var idleAt = parseInt(sessionStorage.getItem(idleAtKey(scope)) || '0', 10) || 0;
            if (idleAt > 0 && (Date.now() - idleAt) > policy.idle_timeout_ms) {
                clearUnlock(scope);
                return { ok: false, error: 'idle_timeout' };
            }
        } catch (e) {
            return { ok: false, error: 'session_ttl_unavailable' };
        }
        return { ok: true };
    }

    function isUnlocked(scope) {
        if (unlockUntil(scope) <= Date.now()) {
            return false;
        }
        return assertSessionTtl(scope).ok === true;
    }

    function clearUnlock(scope) {
        scope = scope || tenantScope();
        setUnlockUntil(scope, 0);
        try {
            sessionStorage.removeItem(identitySessionKey(scope));
            sessionStorage.removeItem(unlockStartedKey(scope));
            sessionStorage.removeItem(idleAtKey(scope));
        } catch (e) { /* ignore */ }
    }

    function vaultIntegrityHash(record) {
        var parts = [
            String(record.company_id || 0),
            String(record.branch_id || 0),
            String(record.user_id || 0),
            String(record.device_id || ''),
            String(record.pin_hash || ''),
            String(record.identity_cipher || ''),
            String(record.identity_expires_at || 0),
            String(record.identity_version || 1)
        ];
        return root.crypto.subtle.digest('SHA-256', new TextEncoder().encode(parts.join('|'))).then(function (buf) {
            return bufToB64(buf);
        });
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
                label: device && device.label ? String(device.label) : 'ERP shell',
                company_id: scope.company_id || 0,
                user_id: scope.user_id || 0,
                updated_at: new Date().toISOString(),
                created_at: (device && device.created_at) ? String(device.created_at) : new Date().toISOString()
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

    function deleteDeviceStatus(scope) {
        var key = DEVICE_META_PREFIX + (scope.company_id || 0) + ':' + (scope.user_id || 0);
        return withMeta('readwrite', function (store) {
            store.delete(key);
            return true;
        }).catch(function () { return false; });
    }

    function deleteSnapshot(kind, scope) {
        scope = scope || tenantScope();
        if (!scope.company_id || !scope.user_id) {
            return Promise.resolve(false);
        }
        var id = kind + ':' + scope.company_id + ':' + (scope.branch_id || 0) + ':' + scope.user_id;
        return withSnapshots('readwrite', function (store) {
            store.delete(id);
            return true;
        }).catch(function () { return false; });
    }

    function clearPersistedScope() {
        try {
            localStorage.removeItem(SCOPE_LS_KEY);
        } catch (e) { /* ignore */ }
    }

    function logoutVaultPolicy() {
        var p = String((cfg().logout_vault_policy || '')).toLowerCase().trim();
        if (p === 'keep_vault' || p === 'keep') {
            return 'keep_vault';
        }
        // Default: keep PIN vault so offline unlock still works after logout.
        // Opt into wipe via logout_vault_policy=clear_vault (or RATEB_OFFLINE_AUTH_LOGOUT_VAULT).
        if (p === 'clear_vault' || p === 'clear') {
            return 'clear_vault';
        }
        return 'keep_vault';
    }

    /**
     * Logout ends the warm unlock session.
     * keep_vault (default): clear unlock TTL + local session; PIN vault + device meta remain for offline unlock.
     * clear_vault: also wipe PIN vault, device meta, RBAC/shell snapshots, and persisted scope.
     */
    function destroyWarmSession(scope) {
        scope = scope || tenantScope();
        var policy = logoutVaultPolicy();
        clearUnlock(scope);
        markSessionNeedsReauth();
        var local = root.RatebOfflineLocalSession;
        if (local && typeof local.destroy === 'function') {
            local.destroy(scope);
        }
        var rbac = root.RatebOfflineRbacCache;
        if (rbac && typeof rbac.clearNavDom === 'function') {
            rbac.clearNavDom();
        }
        if (policy === 'keep_vault') {
            return Promise.resolve({ ok: true, destroyed: true, logout_vault_policy: policy, vault_cleared: false });
        }
        clearPersistedScope();
        return Promise.all([
            deleteVault(scope),
            deleteDeviceStatus(scope),
            deleteSnapshot(RBAC_SNAPSHOT_KIND, scope),
            deleteSnapshot(SHELL_SNAPSHOT_KIND, scope),
            rbac && typeof rbac.deleteManifest === 'function' ? rbac.deleteManifest(scope) : Promise.resolve(false)
        ]).then(function () {
            return { ok: true, destroyed: true, logout_vault_policy: policy, vault_cleared: true };
        }).catch(function () {
            return { ok: true, destroyed: true, logout_vault_policy: policy, vault_cleared: true, partial: true };
        });
    }

    function assertUnlockAllowed(scope, deviceMeta) {
        if (!isActive()) {
            return { ok: false, error: 'auth_unlock_disabled' };
        }
        // Unbound platform super-admin only — company-bound SA may unlock warm identity.
        if (scope.is_super_admin && !scope.company_id) {
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
        if (scope.is_super_admin && !scope.company_id) {
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
        var deviceId = getDeviceId();
        return getVault(scope).then(function (existing) {
            var salt = existing && existing.pin_salt ? existing.pin_salt : null;
            var sealPromise = options.identity
                ? sealIdentityPackage(pin, salt, options.identity)
                : hashPin(pin, salt).then(function (hashed) {
                    return {
                        pin_hash: hashed.pin_hash,
                        pin_salt: hashed.pin_salt,
                        identity_iv: '',
                        identity_cipher: '',
                        identity_alg: '',
                        identity_expires_at: 0
                    };
                });
            return sealPromise.then(function (sealed) {
                var record = {
                    id: id,
                    company_id: scope.company_id,
                    branch_id: scope.branch_id || 0,
                    user_id: scope.user_id,
                    device_id: deviceId,
                    pin_hash: sealed.pin_hash,
                    pin_salt: sealed.pin_salt,
                    identity_iv: sealed.identity_iv || '',
                    identity_cipher: sealed.identity_cipher || '',
                    identity_alg: sealed.identity_alg || '',
                    identity_expires_at: sealed.identity_expires_at || 0,
                    webauthn_credential_id: (options.webauthn_credential_id
                        || (existing && existing.webauthn_credential_id)
                        || ''),
                    unlock_ttl_ms: unlockTtlMs(),
                    identity_version: options.identity && options.identity.claims
                        ? (parseInt(options.identity.claims.identity_version, 10) || 1)
                        : 1,
                    vault_integrity: '',
                    created_at: (existing && existing.created_at) ? existing.created_at : now,
                    updated_at: now
                };
                return vaultIntegrityHash(record).then(function (hash) {
                    record.vault_integrity = hash;
                    return putVault(record).then(function () {
                        try {
                            localStorage.setItem(SCOPE_LS_KEY, JSON.stringify({
                                company_id: scope.company_id,
                                branch_id: scope.branch_id || 0,
                                user_id: scope.user_id,
                                auth_unlock: true,
                                flags: flags(),
                                saved_at: now
                            }));
                        } catch (eScope) { /* ignore */ }
                        return { ok: true, id: id, has_identity: !!(sealed.identity_cipher), vault_integrity: hash };
                    });
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
                    || (intOr(record.user_id) !== scope.user_id)) {
                    return { ok: false, error: 'tenant_mismatch' };
                }
                if (intOr(record.branch_id) !== (scope.branch_id || 0)) {
                    return { ok: false, error: 'branch_mismatch' };
                }
                if (record.identity_cipher) {
                    return unsealIdentityPackage(pin, record).then(function (opened) {
                        if (!opened.ok) {
                            return opened;
                        }
                        return vaultIntegrityHash(record).then(function (hash) {
                            if (record.vault_integrity && hash !== record.vault_integrity) {
                                return { ok: false, error: 'vault_tamper' };
                            }
                            clearSessionNeedsReauth();
                            markUnlocked(scope, opened.claims);
                            return {
                                ok: true,
                                identity: opened.claims,
                                warm: true,
                                cold: !!(opened.claims && opened.claims.cold_capable)
                            };
                        });
                    });
                }
                return hashPin(pin, record.pin_salt).then(function (hashed) {
                    if (hashed.pin_hash !== record.pin_hash) {
                        return { ok: false, error: 'pin_denied' };
                    }
                    if (record.identity_expires_at && (record.identity_expires_at * 1000) <= Date.now()) {
                        return { ok: false, error: 'identity_expired' };
                    }
                    clearSessionNeedsReauth();
                    markUnlocked(scope, null);
                    return { ok: true, warm: false };
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
                    markUnlocked(scope, null);
                    return { ok: true };
                }).catch(function () {
                    return { ok: false, error: 'webauthn_denied' };
                });
            });
        });
    }

    var overlayEl = null;
    var unlockWaiters = [];

    function notifyUnlocked(result) {
        var waiters = unlockWaiters.slice();
        unlockWaiters = [];
        waiters.forEach(function (fn) {
            try { fn(result); } catch (e) { /* ignore */ }
        });
        try {
            root.dispatchEvent(new CustomEvent('rateb:offline-unlocked', { detail: result || { ok: true } }));
        } catch (e2) { /* ignore */ }
    }

    function ensureOverlay() {
        if (overlayEl || !root.document || !root.document.body) {
            return overlayEl;
        }
        overlayEl = root.document.createElement('div');
        overlayEl.setAttribute('data-rateb-erp-auth-lock', '1');
        overlayEl.setAttribute('role', 'dialog');
        overlayEl.setAttribute('aria-modal', 'true');
        overlayEl.setAttribute('aria-label', 'RATEB ERP Offline Unlock');
        overlayEl.style.cssText = 'position:fixed;inset:0;z-index:99999;'
            + 'background:radial-gradient(ellipse at 30% 20%,#1e3a5f 0%,#0f1117 55%,#0a0c10 100%);'
            + 'display:flex;align-items:center;justify-content:center;padding:1.5rem;';
        var box = root.document.createElement('div');
        box.style.cssText = 'background:rgba(26,29,36,.96);color:#e8eaed;padding:1.75rem 1.5rem;'
            + 'border-radius:12px;max-width:22rem;width:100%;border:1px solid #2a3344;'
            + 'box-shadow:0 18px 48px rgba(0,0,0,.45);text-align:center;';
        var brand = root.document.createElement('div');
        brand.textContent = 'RATEB ERP';
        brand.style.cssText = 'font:700 1.35rem/1.2 system-ui,Segoe UI,sans-serif;letter-spacing:.04em;'
            + 'margin:0 0 .35rem;color:#8ab4ff;';
        var title = root.document.createElement('h2');
        title.textContent = 'فتح أوفلاين';
        title.style.cssText = 'margin:.25rem 0 .75rem;font-size:1.05rem;font-weight:600;';
        var msg = root.document.createElement('p');
        msg.setAttribute('data-lock-msg', '1');
        msg.style.cssText = 'opacity:.9;font-size:.92rem;line-height:1.45;margin:0 0 .75rem;';
        msg.textContent = 'أدخل رمز PIN لفتح الهوية الدافئة والعمل بنفس واجهة النظام.';
        var input = root.document.createElement('input');
        input.type = 'password';
        input.autocomplete = 'current-password';
        input.setAttribute('data-lock-pin', '1');
        input.setAttribute('placeholder', 'PIN');
        input.setAttribute('aria-label', 'Offline PIN');
        input.style.cssText = 'width:100%;padding:.65rem .75rem;margin:.35rem 0 .75rem;'
            + 'border-radius:8px;border:1px solid #3a4558;background:#12151c;color:#e8eaed;box-sizing:border-box;';
        var btn = root.document.createElement('button');
        btn.type = 'button';
        btn.textContent = 'فتح';
        btn.style.cssText = 'width:100%;padding:.7rem;cursor:pointer;border:0;border-radius:8px;'
            + 'background:#3b82f6;color:#fff;font-weight:600;';
        btn.addEventListener('click', function () {
            var pinVal = String(input.value || '');
            unlockWithPin(pinVal).then(function (res) {
                if (res && res.ok) {
                    hideOverlay();
                    notifyUnlocked(res);
                    landAfterUnlock();
                    return;
                }
                var err = (res && res.error) ? String(res.error) : 'Unlock denied';
                if (err === 'not_enrolled' && pinVal.length >= 4) {
                    var pending = null;
                    try {
                        var raw = root.localStorage.getItem('rateb_erp_pending_identity');
                        pending = raw ? JSON.parse(raw) : null;
                    } catch (ePend) { pending = null; }
                    if (pending && pending.identity) {
                        return enrollPin(pinVal, { identity: pending.identity }).then(function (enrolled) {
                            if (!(enrolled && enrolled.ok)) {
                                msg.textContent = 'تعذر حفظ رمز PIN. افتح الإدارة وأنت متصل مرة واحدة ثم أعد المحاولة.';
                                return;
                            }
                            try { root.localStorage.removeItem('rateb_erp_pending_identity'); } catch (eClr) { /* ignore */ }
                            return unlockWithPin(pinVal).then(function (res2) {
                                if (res2 && res2.ok) {
                                    hideOverlay();
                                    notifyUnlocked(res2);
                                    landAfterUnlock();
                                    return;
                                }
                                msg.textContent = (res2 && res2.error) ? String(res2.error) : 'رفض الفتح';
                            });
                        });
                    }
                }
                if (err === 'device_unknown') {
                    msg.textContent = 'الجهاز غير مسجّل. افتح الإدارة وأنت متصل مرة واحدة لتسجيل هذا الجهاز.';
                } else if (err === 'not_enrolled') {
                    msg.textContent = 'عيّن رمز PIN جديداً (4 أحرف على الأقل). يجب تسجيل الجهاز وأنت متصل أولاً.';
                } else {
                    msg.textContent = err;
                }
            });
        });
        input.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') {
                btn.click();
            }
        });
        box.appendChild(brand);
        box.appendChild(title);
        box.appendChild(msg);
        box.appendChild(input);
        box.appendChild(btn);
        overlayEl.appendChild(box);
        root.document.body.appendChild(overlayEl);
        return overlayEl;
    }

    var LAST_URL_KEY = 'rateb_erp_offline_last_url';

    function rememberLastUrl() {
        try {
            if (!root.location || !root.location.href) {
                return;
            }
            var href = String(root.location.href);
            if (/offline-shell\.html/i.test(href) || /\/login/i.test(href)) {
                return;
            }
            root.localStorage.setItem(LAST_URL_KEY, href);
        } catch (e) { /* ignore */ }
    }

    function landAfterUnlock() {
        try {
            var path = String((root.location && root.location.pathname) || '');
            // Already on a real module page (SW-served ops HTML) — stay.
            if (!/offline-shell\.html$/i.test(path)) {
                rememberLastUrl();
                return;
            }
            var last = '';
            try {
                last = String(root.localStorage.getItem(LAST_URL_KEY) || '');
            } catch (e0) {
                last = '';
            }
            if (last && !/offline-shell\.html/i.test(last) && !/\/login/i.test(last)) {
                root.location.href = last;
                return;
            }
            var base = '/rateb-erp/public/admin/';
            try {
                var p = String(root.location.pathname || '');
                var m = p.match(/^(.*\/public\/)/i);
                if (m && m[1]) {
                    base = m[1] + 'admin/';
                }
            } catch (e1) { /* ignore */ }
            root.location.href = base;
        } catch (e2) { /* ignore */ }
    }

    function showOverlay() {
        var el = ensureOverlay();
        if (el) {
            el.hidden = false;
            el.style.display = 'flex';
            try {
                var pin = el.querySelector('[data-lock-pin]');
                var msg = el.querySelector('[data-lock-msg]');
                if (pin) {
                    pin.focus();
                }
                readDeviceStatus(tenantScope()).then(function (device) {
                    if (!msg) {
                        return;
                    }
                    if (device && device.status && String(device.status).toLowerCase() === 'active') {
                        var label = device.label ? String(device.label) : 'ERP shell';
                        var shortId = device.device_id
                            ? String(device.device_id).slice(0, 12) + (String(device.device_id).length > 12 ? '…' : '')
                            : '';
                        msg.textContent = label + (shortId ? ' (' + shortId + ')' : '')
                            + ' — enter your offline PIN.';
                    } else {
                        msg.textContent = 'device_unknown — open Admin online to enroll this device first.';
                    }
                }).catch(function () { /* ignore */ });
            } catch (e) { /* ignore */ }
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
        if (scope.is_super_admin && !scope.company_id) {
            return Promise.resolve({ ok: false, error: 'super_admin_denied' });
        }
        if (isUnlocked(scope)) {
            hideOverlay();
            return Promise.resolve({ ok: true, unlocked: true });
        }
        var online = root.navigator && root.navigator.onLine !== false;
        var csrf = '';
        try {
            var meta = root.document && root.document.querySelector('meta[name="rateb-csrf"]');
            csrf = meta ? (meta.getAttribute('content') || '') : '';
        } catch (e) { /* ignore */ }
        if (online && csrf) {
            // Live ERP session: never show unlock/PIN overlay while online.
            return readDeviceStatus(scope).then(function (device) {
                var status = device && device.status ? String(device.status).toLowerCase() : '';
                clearSessionNeedsReauth();
                markUnlocked(scope, null);
                hideOverlay();
                // Do not dispatch offline-unlocked — that restores cold/warm chrome onto live DOM.
                return {
                    ok: true,
                    online_session: true,
                    live_ui: true,
                    device_active: status === 'active'
                };
            }).catch(function () {
                clearSessionNeedsReauth();
                markUnlocked(scope, null);
                hideOverlay();
                return { ok: true, online_session: true, live_ui: true, device_active: false };
            });
        }
        showOverlay();
        return new Promise(function (resolve) {
            unlockWaiters.push(resolve);
        });
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
        destroyWarmSession(tenantScope());
        try {
            root.localStorage.removeItem(LAST_URL_KEY);
        } catch (eClr) { /* ignore */ }
        var offline = false;
        try {
            var conn = root.RatebOfflineConnectivity;
            if (conn && typeof conn.isOnline === 'function') {
                offline = !conn.isOnline();
            } else {
                offline = typeof navigator !== 'undefined' && navigator.onLine === false;
            }
        } catch (eOff) {
            offline = typeof navigator !== 'undefined' && navigator.onLine === false;
        }
        if (offline) {
            try {
                ev.preventDefault();
                ev.stopPropagation();
            } catch (ePrev) { /* ignore */ }
            var shell = '/rateb-erp/public/offline-shell.html';
            try {
                var p = String((root.location && root.location.pathname) || '');
                var m = p.match(/^(.*\/public\/)/i);
                if (m && m[1]) {
                    shell = m[1] + 'offline-shell.html';
                }
            } catch (eShell) { /* ignore */ }
            try {
                root.location.href = shell;
            } catch (eNav) { /* ignore */ }
        }
    }

    function start(options) {
        options = options || {};
        if (!isActive()) {
            return;
        }
        rememberLastUrl();
        if (root.document) {
            root.document.addEventListener('click', handleLogoutClick, true);
        }
        if (!options.deferUnlock) {
            requireUnlockIfNeeded();
        }
    }

    root.RatebOfflineAuthLock = {
        isActive: isActive,
        tenantScope: tenantScope,
        vaultId: vaultId,
        getDeviceId: getDeviceId,
        enrollPin: enrollPin,
        unlockWithPin: unlockWithPin,
        unlockWithWebAuthn: unlockWithWebAuthn,
        sealIdentityPackage: sealIdentityPackage,
        verifyIdentityLocal: verifyIdentityLocal,
        destroyWarmSession: destroyWarmSession,
        sessionPolicy: sessionPolicy,
        touchIdle: touchIdle,
        assertSessionTtl: assertSessionTtl,
        vaultIntegrityHash: vaultIntegrityHash,
        needsIdentityRenewal: function (claims) {
            claims = claims || {};
            var exp = parseInt(claims.expires_at, 10) || 0;
            if (exp < 1) {
                return false;
            }
            var before = sessionPolicy().renew_before_seconds;
            return (exp - Math.floor(Date.now() / 1000)) <= before;
        },
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
        landAfterUnlock: landAfterUnlock,
        rememberLastUrl: rememberLastUrl,
        start: start,
        PBKDF2_ITERATIONS: PBKDF2_ITERATIONS
    };
})(typeof window !== 'undefined' ? window : globalThis);

/* ---- offline-local-session-adapter.js ---- */
/**
 * RATEB Offline — Local session only (Cold Offline Identity).
 * Never creates PHP sessions / CSRF / server auth. UI restoration only.
 * SDK version and IndexedDB schema unchanged.
 */
(function (root) {
    'use strict';

    var SESSION_KEY_PREFIX = 'rateb_erp_local_session:';
    var BANNER_ATTR = 'data-rateb-offline-banner';

    function cfg() {
        return root.__RATEB_ERP_SHELL_OFFLINE__ || {};
    }

    function flags() {
        if (root.RatebOffline && typeof root.RatebOffline.flags === 'function') {
            return root.RatebOffline.flags() || {};
        }
        return cfg().flags || {};
    }

    function isColdEnabled() {
        var f = flags();
        return !!(f['offline.enabled']
            && f['offline.read_cache']
            && f['offline.auth.unlock']
            && f['offline.auth.cold']);
    }

    function sessionPolicy() {
        var lock = root.RatebOfflineAuthLock;
        if (lock && typeof lock.sessionPolicy === 'function') {
            return lock.sessionPolicy();
        }
        var p = cfg().session_policy || {};
        return {
            unlock_ttl_ms: parseInt(p.unlock_ttl_ms, 10) || (8 * 60 * 60 * 1000),
            idle_timeout_ms: parseInt(p.idle_timeout_ms, 10) || (15 * 60 * 1000),
            max_offline_session_ms: parseInt(p.max_offline_session_ms, 10) || (72 * 60 * 60 * 1000),
            clock_skew_seconds: parseInt(p.clock_skew_seconds, 10) || 300
        };
    }

    function scopeKey(scope) {
        scope = scope || (root.RatebOfflineAuthLock && root.RatebOfflineAuthLock.tenantScope
            ? root.RatebOfflineAuthLock.tenantScope()
            : {});
        return String(scope.company_id || 0) + ':' + String(scope.branch_id || 0) + ':' + String(scope.user_id || 0);
    }

    function storageKey(scope) {
        return SESSION_KEY_PREFIX + scopeKey(scope);
    }

    function read(scope) {
        try {
            var raw = sessionStorage.getItem(storageKey(scope));
            if (!raw) {
                return null;
            }
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    }

    function write(scope, session) {
        try {
            sessionStorage.setItem(storageKey(scope), JSON.stringify(session));
            return true;
        } catch (e) {
            return false;
        }
    }

    function destroy(scope) {
        try {
            sessionStorage.removeItem(storageKey(scope));
        } catch (e) { /* ignore */ }
        hideBanner();
        return { ok: true, destroyed: true, local_only: true };
    }

    function validateSession(session) {
        if (!session || session.kind !== 'erp_local_offline') {
            return { ok: false, error: 'session_missing' };
        }
        if (session.server_authz_bypass === true) {
            return { ok: false, error: 'authz_bypass_forbidden' };
        }
        var now = Date.now();
        if ((parseInt(session.expires_at_ms, 10) || 0) <= now) {
            return { ok: false, error: 'session_expired' };
        }
        if ((parseInt(session.absolute_expires_at_ms, 10) || 0) <= now) {
            return { ok: false, error: 'absolute_timeout' };
        }
        var idleMs = sessionPolicy().idle_timeout_ms;
        var last = parseInt(session.last_activity_ms, 10) || 0;
        if (last > 0 && (now - last) > idleMs) {
            return { ok: false, error: 'idle_timeout' };
        }
        return { ok: true, session: session };
    }

    /**
     * Create local-only session from decrypted identity claims (after PIN unlock).
     */
    function createFromClaims(claims, opts) {
        opts = opts || {};
        if (!claims || !claims.company_id || !claims.user_id) {
            return { ok: false, error: 'claims_required' };
        }
        var policy = sessionPolicy();
        var now = Date.now();
        var offlinePolicy = claims.offline_policy || {};
        if (offlinePolicy.server_authz_bypass === true) {
            return { ok: false, error: 'authz_bypass_forbidden' };
        }
        var session = {
            kind: 'erp_local_offline',
            mode: opts.mode || (claims.cold_capable ? 'cold' : 'warm'),
            company_id: parseInt(claims.company_id, 10) || 0,
            branch_id: parseInt(claims.branch_id, 10) || 0,
            user_id: parseInt(claims.user_id, 10) || 0,
            user_uuid: String(claims.user_uuid || claims.user_id || ''),
            device_uuid: String(claims.device_id || ''),
            roles: Array.isArray(claims.roles) ? claims.roles : [],
            permissions: Array.isArray(claims.permissions) ? claims.permissions : [],
            plan_modules: Array.isArray(claims.plan_modules) ? claims.plan_modules : [],
            identity_version: parseInt(claims.identity_version, 10) || 1,
            jti: String(claims.jti || ''),
            locale: claims.locale || '',
            theme: claims.theme || '',
            ui_only: true,
            server_authz_bypass: false,
            cold_capable: !!claims.cold_capable,
            issued_at_ms: now,
            last_activity_ms: now,
            expires_at_ms: now + policy.unlock_ttl_ms,
            absolute_expires_at_ms: now + policy.max_offline_session_ms,
            identity_expires_at: parseInt(claims.expires_at, 10) || 0
        };
        var scope = {
            company_id: session.company_id,
            branch_id: session.branch_id,
            user_id: session.user_id
        };
        write(scope, session);
        return { ok: true, session: session, local_only: true };
    }

    function touch(scope) {
        var session = read(scope);
        var v = validateSession(session);
        if (!v.ok) {
            destroy(scope);
            return v;
        }
        session.last_activity_ms = Date.now();
        write(scope, session);
        return { ok: true, session: session };
    }

    function getActive(scope) {
        var session = read(scope);
        var v = validateSession(session);
        if (!v.ok) {
            if (session) {
                destroy(scope);
            }
            return v;
        }
        return v;
    }

    function showBanner() {
        if (!root.document || !root.document.body) {
            return;
        }
        if (root.document.querySelector('[' + BANNER_ATTR + ']')) {
            return;
        }
        var el = root.document.createElement('div');
        el.setAttribute(BANNER_ATTR, '1');
        el.className = 'rateb-offline-local-session-banner';
        el.setAttribute('role', 'status');
        el.textContent = 'RATEB ERP — Offline session (local only)';
        root.document.body.insertBefore(el, root.document.body.firstChild);
    }

    function hideBanner() {
        try {
            var nodes = root.document.querySelectorAll('[' + BANNER_ATTR + ']');
            nodes.forEach(function (n) {
                if (n.parentNode) {
                    n.parentNode.removeChild(n);
                }
            });
        } catch (e) { /* ignore */ }
    }

    function applyThemeAndLocale(session) {
        try {
            if (session.theme) {
                root.localStorage.setItem('rateb_erp_theme', String(session.theme));
                root.document.documentElement.setAttribute('data-theme', String(session.theme));
            }
            if (session.locale) {
                root.localStorage.setItem('rateb_erp_locale', String(session.locale));
            }
        } catch (e) { /* ignore */ }
    }

    root.RatebOfflineLocalSession = {
        isColdEnabled: isColdEnabled,
        createFromClaims: createFromClaims,
        getActive: getActive,
        touch: touch,
        destroy: destroy,
        showBanner: showBanner,
        hideBanner: hideBanner,
        applyThemeAndLocale: applyThemeAndLocale,
        sessionPolicy: sessionPolicy
    };
})(typeof window !== 'undefined' ? window : globalThis);

/* ---- offline-cold-bootstrap-adapter.js ---- */
/**
 * RATEB Offline — Cold bootstrap manager (client).
 * Restores cached ERP chrome after local PIN unlock without server calls.
 * Does not create PHP sessions. Does not alter Queue/Replay/SDK contracts.
 */
(function (root) {
    'use strict';

    var SCOPE_KEY = 'rateb_erp_offline_scope';

    function cfg() {
        return root.__RATEB_ERP_SHELL_OFFLINE__ || {};
    }

    function persistColdScope(session) {
        try {
            var prev = {};
            try {
                prev = JSON.parse(root.localStorage.getItem(SCOPE_KEY) || '{}') || {};
            } catch (e) { /* ignore */ }
            var flags = Object.assign({}, prev.flags || cfg().flags || {}, {
                'offline.enabled': true,
                'offline.read_cache': true,
                'offline.auth.unlock': true,
                'offline.auth.cold': true,
                'offline.rbac.cache': true
            });
            root.localStorage.setItem(SCOPE_KEY, JSON.stringify({
                company_id: session.company_id,
                branch_id: session.branch_id || 0,
                user_id: session.user_id,
                auth_unlock: true,
                cold_capable: !!session.cold_capable,
                flags: flags,
                saved_at: new Date().toISOString()
            }));
            root.__RATEB_ERP_SHELL_OFFLINE__ = root.__RATEB_ERP_SHELL_OFFLINE__ || {};
            root.__RATEB_ERP_SHELL_OFFLINE__.company_id = session.company_id;
            root.__RATEB_ERP_SHELL_OFFLINE__.branch_id = session.branch_id || 0;
            root.__RATEB_ERP_SHELL_OFFLINE__.user_id = session.user_id;
            root.__RATEB_ERP_SHELL_OFFLINE__.flags = flags;
        } catch (e) { /* ignore */ }
    }

    function restoreAfterUnlock(detail) {
        var local = root.RatebOfflineLocalSession;
        var lock = root.RatebOfflineAuthLock;
        var claims = (detail && detail.identity) ? detail.identity : null;
        if (!local) {
            return Promise.resolve({ ok: false, error: 'local_session_unavailable' });
        }
        if (!claims && detail && detail.warm === false && !detail.ok) {
            return Promise.resolve({ ok: false, error: 'no_claims' });
        }
        // Warm unlock without sealed cold claims: still allow local warm session marker.
        if (!claims) {
            var scope = lock && lock.tenantScope ? lock.tenantScope() : {};
            if (!scope.company_id || !scope.user_id) {
                return Promise.resolve({ ok: false, error: 'scope_required' });
            }
            if (!local.isColdEnabled()) {
                return Promise.resolve({ ok: true, mode: 'warm', skipped_cold: true });
            }
            return Promise.resolve({ ok: true, mode: 'warm', skipped_cold: true });
        }

        var created = local.createFromClaims(claims, {
            mode: claims.cold_capable ? 'cold' : 'warm'
        });
        if (!created.ok) {
            return Promise.resolve(created);
        }
        persistColdScope(created.session);
        local.applyThemeAndLocale(created.session);
        local.showBanner();
        if (lock && typeof lock.touchIdle === 'function') {
            lock.touchIdle();
        }

        var rbac = root.RatebOfflineRbacCache;
        if (rbac && typeof rbac.applyCachedNav === 'function') {
            return rbac.applyCachedNav({ requireDeviceActive: !!lock }).then(function (nav) {
                return {
                    ok: true,
                    mode: created.session.mode,
                    local_only: true,
                    nav: nav,
                    session: created.session
                };
            });
        }
        return Promise.resolve({
            ok: true,
            mode: created.session.mode,
            local_only: true,
            session: created.session
        });
    }

    function onUnlocked(ev) {
        var detail = (ev && ev.detail) ? ev.detail : {};
        // Live online session must keep full ERP DOM — never restore offline chrome/nav.
        if (detail.online_session || detail.live_ui) {
            return;
        }
        if (root.navigator && root.navigator.onLine !== false) {
            var path = (root.location && root.location.pathname) || '';
            if (!/offline-shell\.html/i.test(path)) {
                return;
            }
        }
        restoreAfterUnlock(detail).catch(function () { /* ignore */ });
    }

    function bindActivity() {
        if (!root.document) {
            return;
        }
        var handler = function () {
            var local = root.RatebOfflineLocalSession;
            var lock = root.RatebOfflineAuthLock;
            if (!local || !lock) {
                return;
            }
            var scope = lock.tenantScope ? lock.tenantScope() : {};
            local.touch(scope);
            if (typeof lock.touchIdle === 'function') {
                lock.touchIdle(scope);
            }
        };
        ['mousemove', 'keydown', 'touchstart', 'click'].forEach(function (ev) {
            root.document.addEventListener(ev, handler, { passive: true });
        });
    }

    function destroyOnLogout() {
        if (!root.document) {
            return;
        }
        root.document.addEventListener('click', function (ev) {
            var a = ev.target && ev.target.closest ? ev.target.closest('a[href]') : null;
            if (!a) {
                return;
            }
            var href = a.getAttribute('href') || '';
            if (!/\/logout/i.test(href)) {
                return;
            }
            var local = root.RatebOfflineLocalSession;
            var lock = root.RatebOfflineAuthLock;
            if (local && lock) {
                local.destroy(lock.tenantScope());
            }
        }, true);
    }

    function start() {
        if (root.addEventListener) {
            root.addEventListener('rateb:offline-unlocked', onUnlocked);
        }
        bindActivity();
        destroyOnLogout();
        var local = root.RatebOfflineLocalSession;
        var lock = root.RatebOfflineAuthLock;
        if (local && lock && lock.isUnlocked && lock.isUnlocked()) {
            var active = local.getActive(lock.tenantScope());
            if (active.ok) {
                local.showBanner();
                local.applyThemeAndLocale(active.session);
            }
        }
    }

    root.RatebOfflineBootstrapManager = {
        start: start,
        restoreAfterUnlock: restoreAfterUnlock
    };

    if (root.document && root.document.readyState === 'loading') {
        root.document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
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
        // Platform SA without tenant cannot use company RBAC cache.
        // Company-bound SA (shell company_id > 0) may use warm offline nav.
        if (scope.is_super_admin && !(scope.company_id > 0)) {
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
        // Company-bound super-admin: warm nav is UI-only; do not hide catalog by empty slug lists.
        var c = cfg();
        if (c.is_super_admin && (parseInt(c.company_id, 10) > 0)) {
            return true;
        }
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
        var c = cfg();
        if (c.is_super_admin && (parseInt(c.company_id, 10) > 0)) {
            return true;
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

    function sectionTitle(section) {
        section = section || {};
        var titled = String(section.title || '').trim();
        if (titled && titled !== String(section.title_key || '')) {
            return titled;
        }
        var key = String(section.title_key || section.title || '').trim();
        var map = {
            dashboard: 'لوحة التحكم',
            procurement: 'المشتريات',
            inventory: 'المخزون',
            hr: 'الموارد البشرية',
            suppliers: 'الموردون',
            account: 'الحساب'
        };
        return map[key] || titled || key;
    }

    function ensureOfflineNavStyles() {
        if (!root.document || !root.document.head) {
            return;
        }
        if (root.document.getElementById('rateb-offline-rbac-nav-css')) {
            return;
        }
        var css = root.document.createElement('style');
        css.id = 'rateb-offline-rbac-nav-css';
        css.textContent = ''
            + 'aside.rateb-sidebar.rateb-offline-shell-nav,'
            + 'aside.rateb-offline-shell-nav{'
            + 'display:block;min-width:16rem;max-width:18rem;padding:0;overflow:auto;'
            + 'background:var(--rateb-sidebar,#070d18);color:var(--rateb-sidebar-text,#cbd5e1);}'
            + 'aside.rateb-sidebar .rateb-sidebar-brand{padding:1rem 1.15rem;font-weight:700;'
            + 'border-bottom:1px solid rgba(255,255,255,.08);}'
            + 'aside.rateb-sidebar .rateb-nav-section{padding:.85rem 1.1rem .25rem;font-size:.7rem;'
            + 'opacity:.6;font-weight:600;}'
            + 'aside.rateb-sidebar a.rateb-nav-link{display:flex;align-items:center;gap:.55rem;'
            + 'padding:.5rem .85rem;margin:.1rem .45rem;border-radius:8px;color:inherit;'
            + 'text-decoration:none;font-size:.86rem;}'
            + 'aside.rateb-sidebar a.rateb-nav-link:hover{background:rgba(255,255,255,.06);color:#fff;}'
            + '.rateb-offline-home .list-group-item{display:block;padding:.65rem .85rem;margin:.25rem 0;'
            + 'border-radius:8px;background:#1a1d24;color:#e8eaed;text-decoration:none;border:1px solid #2a2f3a;}'
            + '.rateb-offline-home .list-group-item:hover{border-color:#3d4654;}';
        root.document.head.appendChild(css);
    }

    function renderNav(manifest) {
        if (!root.document || !manifest || !manifest.nav || !Array.isArray(manifest.nav.sections)) {
            clearNavDom();
            return false;
        }
        ensureOfflineNavStyles();
        var disabled = manifest.offline_disabled_modules || [];
        var html = '<div class="rateb-sidebar-brand"><span>RATEB ERP</span></div>';
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
            html += '<div class="rateb-offline-rbac-section rateb-nav-group is-open">';
            html += '<div class="rateb-nav-section">' + escapeHtml(sectionTitle(section)) + '</div>';
            visible.forEach(function (item) {
                var href = safeHref(item.href);
                var label = String(item.label || item.label_key || item.path || '');
                var icon = String(item.icon || 'fa-circle');
                html += '<a class="rateb-nav-link rateb-offline-rbac-link" href="' + escapeAttr(href) + '">'
                    + '<i class="fas ' + escapeAttr(icon) + ' rateb-nav-group-icon" aria-hidden="true"></i>'
                    + '<span>' + escapeHtml(label) + '</span></a>';
            });
            html += '</div>';
        });
        html += '</nav>';
        try {
            var targets = root.document.querySelectorAll(
                'aside.rateb-offline-shell-nav, aside[aria-label="Offline nav"]'
            );
            // Do not wipe live Admin sidebars (aside.rateb-sidebar) — only the offline-shell host.
            if (!targets.length) {
                var path = String((root.location && root.location.pathname) || '');
                if (/offline-shell\.html$/i.test(path) || root.document.getElementById('rateb-offline-shell-main')) {
                    targets = root.document.querySelectorAll('#rateb-sidebar, aside.rateb-sidebar');
                }
            }
            if (!targets.length) {
                return false;
            }
            targets.forEach(function (el) {
                el.classList.add('rateb-sidebar', 'rateb-offline-shell-nav');
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
        // Deny only unbound platform SA; company-bound SA may sync warm nav.
        if ((scope.is_super_admin && !(scope.company_id > 0)) || !scope.company_id || !scope.user_id) {
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
        // Captured ops pages already have the live Admin sidebar — never replace with
        // a simplified RBAC tree (that caused offline nav mismatch vs online).
        try {
            var cfgSnap = root.__RATEB_ERP_SHELL_OFFLINE__ || {};
            if (cfgSnap.offline_ops_snapshot
                || (root.document && root.document.querySelector('[data-rateb-offline-ops-banner]'))) {
                return Promise.resolve({ ok: true, skipped: true, reason: 'keep_captured_live_nav' });
            }
        } catch (eSkip) { /* ignore */ }
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
 * RATEB Offline — Ops forms adapter (Phase 14 / 14.2 / 15B / 16B / 17B).
 * Per-module hooks: when offline, allowlisted Inv/HR/Proc/Recruitment/Accounting/CRM-draft forms enqueue via existing adapters.
 * Does not finish a generic form-post stub; narrow path matching only.
 * Phase 14.2: purchase-orders/{id}/receive → goods_receipt.receive (flag-gated).
 * Phase 15B: recruitment/candidates create|update|transition (flag-gated).
 * Phase 16B: journal-entries draft create|update + recurring/opening drafts (flag-gated; never post).
 * Phase 17B: crm leads/tasks/meetings/campaigns/contacts/companies drafts (flag-gated).
 * Phase 18B: projects create/update/tasks/timesheets drafts (flag-gated).
 * Phase 19B: eam assets/maintenance/work-orders/inspections drafts (flag-gated).
 * Phase 20B: approvals requests/comments drafts (flag-gated).
 * Phase 21B: eproc suppliers/tenders/contracts drafts (flag-gated).
 * Phase 22B: mfg boms/routings/production/work orders/quality drafts (flag-gated).
 * Phase 24B: payroll salary/batch/loan/advance/overtime drafts (flag-gated).
 * Phase 25B: qms inspections/checklists/audits drafts (flag-gated).
 * Phase 26B: dms repositories/folders/documents drafts (flag-gated).
 * Phase 27B: bi dashboards/kpis/reports drafts (flag-gated).
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
        { match: 'recruitment/candidates', module: 'recruitment', action: 'candidate.update' },
        { match: 'journal-entries/create', module: 'accounting', action: 'journal.create' },
        { match: 'journal-entries', module: 'accounting', action: 'journal.update' },
        { match: 'accounting/recurring/create', module: 'accounting', action: 'recurring.create' },
        { match: 'accounting/opening-balances', module: 'accounting', action: 'opening_balance.create' },
        { match: 'crm/leads/create', module: 'crm', action: 'lead.create' },
        { match: 'crm/leads', module: 'crm', action: 'lead.update' },
        { match: 'crm/tasks', module: 'crm', action: 'task.create' },
        { match: 'crm/meetings', module: 'crm', action: 'meeting.create' },
        { match: 'crm/campaigns', module: 'crm', action: 'campaign.create' },
        { match: 'crm/contacts', module: 'crm', action: 'contact.create' },
        { match: 'crm/companies', module: 'crm', action: 'company.create' },
        { match: 'projects/create', module: 'projects', action: 'project.create' },
        { match: 'projects/tasks', module: 'projects', action: 'task.create' },
        { match: 'projects/milestones', module: 'projects', action: 'milestone.create' },
        { match: 'projects/issues', module: 'projects', action: 'issue.create' },
        { match: 'projects/risks', module: 'projects', action: 'risk.create' },
        { match: 'projects/timesheets', module: 'projects', action: 'timesheet.create' },
        { match: 'eam/assets/create', module: 'assets', action: 'asset.create' },
        { match: 'eam/assets', module: 'assets', action: 'asset.update' },
        { match: 'eam/requests', module: 'assets', action: 'maintenance_request.create' },
        { match: 'eam/work-orders', module: 'assets', action: 'work_order.create' },
        { match: 'eam/maintenance', module: 'assets', action: 'maintenance_plan.create' },
        { match: 'eam/inspections', module: 'assets', action: 'inspection.create' },
        { match: 'approvals/requests/create', module: 'approval', action: 'approval_request.create' },
        { match: 'approvals/requests', module: 'approval', action: 'approval_request.update' },
        { match: 'eproc/suppliers/create', module: 'procurement_enterprise', action: 'supplier_profile.create' },
        { match: 'eproc/suppliers', module: 'procurement_enterprise', action: 'supplier_profile.update' },
        { match: 'eproc/tenders/create', module: 'procurement_enterprise', action: 'tender.create' },
        { match: 'eproc/contracts/create', module: 'procurement_enterprise', action: 'contract.create' },
        { match: 'eproc/qualification', module: 'procurement_enterprise', action: 'qualification.create' },
        { match: 'eproc/scorecards', module: 'procurement_enterprise', action: 'scorecard.create' },
        { match: 'eproc/portal', module: 'procurement_enterprise', action: 'portal_invite.create' },
        { match: 'mfg/boms/create', module: 'manufacturing', action: 'bom.create' },
        { match: 'mfg/boms', module: 'manufacturing', action: 'bom.update' },
        { match: 'mfg/production-orders/create', module: 'manufacturing', action: 'production_order.create' },
        { match: 'mfg/production-orders', module: 'manufacturing', action: 'production_order.update' },
        { match: 'mfg/work-orders/create', module: 'manufacturing', action: 'work_order.create' },
        { match: 'mfg/work-orders', module: 'manufacturing', action: 'work_order.update' },
        { match: 'mfg/routings', module: 'manufacturing', action: 'routing.create' },
        { match: 'mfg/quality', module: 'manufacturing', action: 'quality_check.create' },
        { match: 'payroll/salary-structures', module: 'payroll', action: 'salary_structure.create' },
        { match: 'payroll/batches/create', module: 'payroll', action: 'payroll_batch.create' },
        { match: 'payroll/batches', module: 'payroll', action: 'payroll_batch.update' },
        { match: 'payroll/loans', module: 'payroll', action: 'loan.create' },
        { match: 'payroll/advances', module: 'payroll', action: 'advance.create' },
        { match: 'payroll/overtime', module: 'payroll', action: 'overtime.create' },
        { match: 'qms/inspections', module: 'quality', action: 'inspection.create' },
        { match: 'qms/checklists', module: 'quality', action: 'checklist.create' },
        { match: 'qms/audits', module: 'quality', action: 'audit.create' },
        { match: 'qms/defects', module: 'quality', action: 'defect.create' },
        { match: 'qms/nonconformities', module: 'quality', action: 'nonconformity.create' },
        { match: 'qms/corrective-actions', module: 'quality', action: 'corrective_action.create' },
        { match: 'qms/preventive-actions', module: 'quality', action: 'preventive_action.create' },
        { match: 'qms/complaints', module: 'quality', action: 'complaint.create' },
        { match: 'qms/supplier-quality', module: 'quality', action: 'supplier_quality.create' },
        { match: 'dms/repositories', module: 'documents', action: 'repository.create' },
        { match: 'dms/folders', module: 'documents', action: 'folder.create' },
        { match: 'dms/documents', module: 'documents', action: 'document.create' },
        { match: 'dms/shares', module: 'documents', action: 'share.create' },
        { match: 'dms/permissions', module: 'documents', action: 'permission.create' },
        { match: 'bi/dashboards', module: 'bi', action: 'dashboard.create' },
        { match: 'bi/kpis', module: 'bi', action: 'kpi.create' },
        { match: 'bi/reports', module: 'bi', action: 'report.create' },
        { match: 'bi/widgets', module: 'bi', action: 'widget.create' },
        { match: 'bi/datasets', module: 'bi', action: 'dataset.create' },
        { match: 'bi/alerts', module: 'bi', action: 'alert.create' },
        { match: 'bi/schedules', module: 'bi', action: 'schedule.create' },
        { match: 'bi/exports', module: 'bi', action: 'export.create' },
        { match: 'bi/trends', module: 'bi', action: 'trend.create' },
        { match: 'bi/forecasts', module: 'bi', action: 'forecast.create' },
        { match: 'bi/scopes', module: 'bi', action: 'scope.create' },
        { match: 'hrm/employees/create', module: 'hr', action: 'employee.create' },
        { match: 'hrm/employees', module: 'hr', action: 'employee.update' },
        { match: 'hrm/departments', module: 'hr', action: 'department.create' },
        { match: 'hrm/positions', module: 'hr', action: 'position.create' },
        { match: 'hrm/organization', module: 'hr', action: 'organization.create' },
        { match: 'hrm/training/create', module: 'hr', action: 'training.create' },
        { match: 'hrm/performance/create', module: 'hr', action: 'performance.create' },
        { match: 'hrm/goals', module: 'hr', action: 'goal.create' },
        { match: 'hrm/competencies', module: 'hr', action: 'competency.create' },
        { match: 'hrm/promotions', module: 'hr', action: 'promotion.create' },
        { match: 'hrm/transfers', module: 'hr', action: 'transfer.create' }
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
            if (action === 'attendance.create' || action === 'attendance.bulk' || action === 'leave_request.draft') {
                return !!f['offline.hr.attendance'];
            }
            if (!f['offline.hr']) {
                return false;
            }
            if (action === 'employee.create' || action === 'employee.update'
                || action === 'department.create' || action === 'position.create'
                || action === 'organization.create') {
                return !!f['offline.hr.employee'];
            }
            if (action === 'training.create') {
                return !!f['offline.hr.training'];
            }
            if (action === 'performance.create' || action === 'goal.create' || action === 'competency.create') {
                return !!f['offline.hr.performance'];
            }
            if (action === 'workflow.transition') {
                return !!f['offline.hr.workflow'];
            }
            return true;
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
        if (module === 'accounting') {
            if (!f['offline.accounting']) {
                return false;
            }
            if (action === 'journal.create' || action === 'journal.update' || action === 'note.create'
                || action === 'recurring.create' || action === 'opening_balance.create') {
                return !!f['offline.accounting.journals'];
            }
            if (action === 'workflow.transition') {
                return !!f['offline.accounting.workflow'];
            }
            return false;
        }
        if (module === 'crm') {
            if (!f['offline.crm']) {
                return false;
            }
            if (action === 'lead.create' || action === 'lead.update' || action === 'note.create'
                || action === 'contact.create' || action === 'company.create') {
                return !!f['offline.crm.leads'];
            }
            if (action === 'workflow.transition') {
                return !!f['offline.crm.workflow'];
            }
            if (action === 'meeting.create' || action === 'call.create' || action === 'task.create') {
                return !!f['offline.crm.activities'];
            }
            return true;
        }
        if (module === 'projects') {
            if (!f['offline.projects']) {
                return false;
            }
            if (action === 'task.create' || action === 'task.update') {
                return !!f['offline.projects.tasks'];
            }
            if (action === 'workflow.transition') {
                return !!f['offline.projects.workflow'];
            }
            if (action === 'timesheet.create') {
                return !!f['offline.projects.timesheets'];
            }
            return true;
        }
        if (module === 'assets') {
            if (!f['offline.assets']) {
                return false;
            }
            if (action === 'maintenance_request.create'
                || action === 'maintenance_plan.create'
                || action === 'work_order.create') {
                return !!f['offline.assets.maintenance'];
            }
            if (action === 'workflow.transition') {
                return !!f['offline.assets.workflow'];
            }
            if (action === 'inspection.create'
                || action === 'checklist.create'
                || action === 'meter_reading.create') {
                return !!f['offline.assets.inspections'];
            }
            return true;
        }
        if (module === 'approval') {
            if (!f['offline.approval']) {
                return false;
            }
            if (action === 'approval_request.create' || action === 'approval_request.update') {
                return !!f['offline.approval.requests'];
            }
            if (action === 'workflow.transition') {
                return !!f['offline.approval.workflow'];
            }
            return true;
        }
        if (module === 'procurement_enterprise') {
            if (!f['offline.procurement_enterprise']) {
                return false;
            }
            if (action === 'supplier_profile.create'
                || action === 'supplier_profile.update'
                || action === 'qualification.create'
                || action === 'qualification.update'
                || action === 'risk.create'
                || action === 'scorecard.create'
                || action === 'portal_invite.create'
                || action === 'collaboration.create') {
                return !!f['offline.procurement_enterprise.suppliers'];
            }
            if (action === 'tender.create' || action === 'bid.create' || action === 'bid_compare.create') {
                return !!f['offline.procurement_enterprise.tenders'];
            }
            if (action === 'contract.create') {
                return !!f['offline.procurement_enterprise.contracts'];
            }
            if (action === 'workflow.transition') {
                return !!f['offline.procurement_enterprise.workflow'];
            }
            return true;
        }
        if (module === 'manufacturing') {
            if (!f['offline.manufacturing']) {
                return false;
            }
            if (action === 'bom.create' || action === 'bom.update'
                || action === 'routing.create' || action === 'routing.update'
                || action === 'production_order.create' || action === 'production_order.update'
                || action === 'work_order.create' || action === 'work_order.update'
                || action === 'material_reservation.create' || action === 'material_consumption.create'
                || action === 'finished_goods.create' || action === 'scrap.create') {
                return !!f['offline.manufacturing.production'];
            }
            if (action === 'workflow.transition') {
                return !!f['offline.manufacturing.workflow'];
            }
            if (action === 'quality_check.create') {
                return !!f['offline.manufacturing.quality'];
            }
            return true;
        }
        if (module === 'payroll') {
            if (!f['offline.payroll']) {
                return false;
            }
            if (action === 'salary_structure.create' || action === 'salary_structure.update'
                || action === 'employee_salary.create' || action === 'employee_salary.update') {
                return !!f['offline.payroll.employee'];
            }
            if (action === 'payroll_batch.create' || action === 'payroll_batch.update') {
                return !!f['offline.payroll.batch'];
            }
            if (action === 'workflow.transition') {
                return !!f['offline.payroll.workflow'];
            }
            return true;
        }
        if (module === 'quality') {
            if (!f['offline.quality']) {
                return false;
            }
            if (action === 'inspection.create' || action === 'inspection.update'
                || action === 'checklist.create') {
                return !!f['offline.quality.inspections'];
            }
            if (action === 'audit.create') {
                return !!f['offline.quality.audit'];
            }
            if (action === 'workflow.transition') {
                return !!f['offline.quality.workflow'];
            }
            return true;
        }
        if (module === 'documents') {
            if (!f['offline.documents']) {
                return false;
            }
            if (action === 'repository.create' || action === 'repository.update'
                || action === 'folder.create' || action === 'folder.update') {
                return !!f['offline.documents.repositories'];
            }
            if (action === 'workflow.transition') {
                return !!f['offline.documents.workflow'];
            }
            return true;
        }
        if (module === 'bi') {
            if (!f['offline.bi']) {
                return false;
            }
            if (action === 'workflow.transition') {
                return !!f['offline.bi.workflow'];
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

    function isAccountingLifecyclePath(pathname) {
        return /journal-entries\/\d+\/lifecycle(\/|$|\?)/i.test(String(pathname || ''));
    }

    function isAccountingPostPath(pathname) {
        return /journal-entries\/\d+\/(post|void|reject|submit-approval)(\/|$|\?)/i.test(String(pathname || ''))
            || /journal-entries\/bulk-(approve|reject|void)/i.test(String(pathname || ''));
    }

    function extractJournalIdFromPath(pathname) {
        var m = String(pathname || '').match(/journal-entries\/(\d+)/i);
        return m ? (parseInt(m[1], 10) || 0) : 0;
    }

    function matchHook(pathname) {
        var p = normalizePath(pathname);
        if (isAccountingPostPath(p)) {
            return null;
        }
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
                if (String(hook.match).indexOf('journal-entries') >= 0 && isAccountingLifecyclePath(p)) {
                    return {
                        match: hook.match,
                        module: 'accounting',
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
            if (String(hook.module || '') === 'accounting') {
                return {
                    journal_entry_id: intOrZero(raw.journal_entry_id || raw.entry_id || raw.id)
                        || extractJournalIdFromPath(pathname || ''),
                    to_status: String(raw.to_status || raw.target_status || raw.workflow_status || ''),
                    reason: raw.reason || null,
                    expected_status: raw.expected_status || null
                };
            }
            return {
                candidate_id: intOrZero(raw.candidate_id)
                    || extractCandidateIdFromPath(pathname || ''),
                to_status: String(raw.to_status || raw.workflow_status || ''),
                reason: raw.reason || null
            };
        }
        if (action === 'journal.create' || action === 'journal.update') {
            var journal = {
                entry_date: raw.entry_date || null,
                description: raw.description || null,
                description_ar: raw.description_ar || null,
                currency_code: raw.currency_code || null,
                notes: raw.notes || null,
                lines: raw.lines || null,
                expected_status: raw.expected_status || 'draft'
            };
            if (action === 'journal.update') {
                journal.journal_entry_id = intOrZero(raw.journal_entry_id || raw.entry_id || raw.id)
                    || extractJournalIdFromPath(pathname || '');
            }
            return journal;
        }
        if (action === 'recurring.create') {
            return {
                name: String(raw.name || raw.title || 'Offline recurring'),
                frequency: raw.frequency || null,
                start_date: raw.start_date || null,
                notes: raw.notes || null
            };
        }
        if (action === 'opening_balance.create') {
            return {
                fiscal_period_id: intOrZero(raw.fiscal_period_id) || null,
                account_id: intOrZero(raw.account_id) || null,
                debit: raw.debit != null ? floatOrZero(raw.debit) : null,
                credit: raw.credit != null ? floatOrZero(raw.credit) : null,
                notes: raw.notes || null
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
            if (action === 'employee.create' && typeof hr.enqueueEmployeeCreate === 'function') {
                return hr.enqueueEmployeeCreate(payload);
            }
            if (action === 'employee.update' && typeof hr.enqueueEmployeeUpdate === 'function') {
                return hr.enqueueEmployeeUpdate(payload);
            }
            if (action === 'department.create' && typeof hr.enqueueDepartmentCreate === 'function') {
                return hr.enqueueDepartmentCreate(payload);
            }
            if (action === 'position.create' && typeof hr.enqueuePositionCreate === 'function') {
                return hr.enqueuePositionCreate(payload);
            }
            if (action === 'organization.create' && typeof hr.enqueueOrganizationCreate === 'function') {
                return hr.enqueueOrganizationCreate(payload);
            }
            if (action === 'training.create' && typeof hr.enqueueTrainingCreate === 'function') {
                return hr.enqueueTrainingCreate(payload);
            }
            if (action === 'performance.create' && typeof hr.enqueuePerformanceCreate === 'function') {
                return hr.enqueuePerformanceCreate(payload);
            }
            if (action === 'goal.create' && typeof hr.enqueueGoalCreate === 'function') {
                return hr.enqueueGoalCreate(payload);
            }
            if (action === 'competency.create' && typeof hr.enqueueCompetencyCreate === 'function') {
                return hr.enqueueCompetencyCreate(payload);
            }
            if (action === 'promotion.create' && typeof hr.enqueuePromotionCreate === 'function') {
                return hr.enqueuePromotionCreate(payload);
            }
            if (action === 'transfer.create' && typeof hr.enqueueTransferCreate === 'function') {
                return hr.enqueueTransferCreate(payload);
            }
            if (action === 'workflow.transition' && typeof hr.enqueueWorkflowTransition === 'function') {
                return hr.enqueueWorkflowTransition(payload);
            }
            if (typeof hr.enqueue === 'function') {
                return hr.enqueue(action, payload);
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
        if (module === 'accounting') {
            var acc = root.RatebOfflineAccountingAdapter;
            if (!acc) {
                return Promise.reject(new Error('accounting_adapter_unavailable'));
            }
            if (action === 'journal.create') {
                return acc.enqueueJournalCreate(payload);
            }
            if (action === 'journal.update') {
                return acc.enqueueJournalUpdate(payload);
            }
            if (action === 'workflow.transition') {
                return acc.enqueueWorkflowTransition(payload);
            }
            if (action === 'recurring.create') {
                return acc.enqueueRecurringCreate(payload);
            }
            if (action === 'opening_balance.create') {
                return acc.enqueueOpeningBalanceCreate(payload);
            }
            if (action === 'note.create') {
                return acc.enqueueNoteCreate(payload);
            }
            if (typeof acc.enqueue === 'function') {
                return acc.enqueue(action, payload);
            }
        }
        if (module === 'crm') {
            var crm = root.RatebOfflineCrmAdapter;
            if (!crm) {
                return Promise.reject(new Error('crm_adapter_unavailable'));
            }
            if (action === 'lead.create') {
                return crm.enqueueLeadCreate(payload);
            }
            if (action === 'lead.update') {
                return crm.enqueueLeadUpdate(payload);
            }
            if (action === 'workflow.transition') {
                return crm.enqueueWorkflowTransition(payload);
            }
            if (action === 'meeting.create') {
                return crm.enqueueMeetingCreate(payload);
            }
            if (action === 'task.create') {
                return crm.enqueueTaskCreate(payload);
            }
            if (action === 'campaign.create') {
                return crm.enqueueCampaignCreate(payload);
            }
            if (action === 'contact.create') {
                return crm.enqueueContactCreate(payload);
            }
            if (action === 'company.create') {
                return crm.enqueueCompanyCreate(payload);
            }
            if (typeof crm.enqueue === 'function') {
                return crm.enqueue(action, payload);
            }
        }
        if (module === 'projects') {
            var prj = root.RatebOfflineProjectsAdapter;
            if (!prj) {
                return Promise.reject(new Error('projects_adapter_unavailable'));
            }
            if (action === 'project.create') {
                return prj.enqueueProjectCreate(payload);
            }
            if (action === 'project.update') {
                return prj.enqueueProjectUpdate(payload);
            }
            if (action === 'task.create') {
                return prj.enqueueTaskCreate(payload);
            }
            if (action === 'task.update') {
                return prj.enqueueTaskUpdate(payload);
            }
            if (action === 'workflow.transition') {
                return prj.enqueueWorkflowTransition(payload);
            }
            if (action === 'milestone.create') {
                return prj.enqueueMilestoneCreate(payload);
            }
            if (action === 'timesheet.create') {
                return prj.enqueueTimesheetCreate(payload);
            }
            if (action === 'issue.create') {
                return prj.enqueueIssueCreate(payload);
            }
            if (action === 'risk.create') {
                return prj.enqueueRiskCreate(payload);
            }
            if (typeof prj.enqueue === 'function') {
                return prj.enqueue(action, payload);
            }
        }
        if (module === 'assets') {
            var eam = root.RatebOfflineAssetsAdapter;
            if (!eam) {
                return Promise.reject(new Error('assets_adapter_unavailable'));
            }
            if (action === 'asset.create') {
                return eam.enqueueAssetCreate(payload);
            }
            if (action === 'asset.update') {
                return eam.enqueueAssetUpdate(payload);
            }
            if (action === 'workflow.transition') {
                return eam.enqueueWorkflowTransition(payload);
            }
            if (action === 'maintenance_request.create') {
                return eam.enqueueMaintenanceRequestCreate(payload);
            }
            if (action === 'maintenance_plan.create') {
                return eam.enqueueMaintenancePlanCreate(payload);
            }
            if (action === 'work_order.create') {
                return eam.enqueueWorkOrderCreate(payload);
            }
            if (action === 'inspection.create') {
                return eam.enqueueInspectionCreate(payload);
            }
            if (typeof eam.enqueue === 'function') {
                return eam.enqueue(action, payload);
            }
        }
        if (module === 'approval') {
            var eap = root.RatebOfflineApprovalAdapter;
            if (!eap) {
                return Promise.reject(new Error('approval_adapter_unavailable'));
            }
            if (action === 'approval_request.create') {
                return eap.enqueueRequestCreate(payload);
            }
            if (action === 'approval_request.update') {
                return eap.enqueueRequestUpdate(payload);
            }
            if (action === 'workflow.transition') {
                return eap.enqueueWorkflowTransition(payload);
            }
            if (action === 'comment.create') {
                return eap.enqueueCommentCreate(payload);
            }
            if (action === 'delegation.create') {
                return eap.enqueueDelegationCreate(payload);
            }
            if (typeof eap.enqueue === 'function') {
                return eap.enqueue(action, payload);
            }
        }
        if (module === 'procurement_enterprise') {
            var eproc = root.RatebOfflineProcurementEnterpriseAdapter;
            if (!eproc) {
                return Promise.reject(new Error('procurement_enterprise_adapter_unavailable'));
            }
            if (action === 'supplier_profile.create') {
                return eproc.enqueueSupplierProfileCreate(payload);
            }
            if (action === 'supplier_profile.update') {
                return eproc.enqueueSupplierProfileUpdate(payload);
            }
            if (action === 'qualification.create') {
                return eproc.enqueueQualificationCreate(payload);
            }
            if (action === 'tender.create') {
                return eproc.enqueueTenderCreate(payload);
            }
            if (action === 'contract.create') {
                return eproc.enqueueContractCreate(payload);
            }
            if (action === 'scorecard.create') {
                return eproc.enqueueScorecardCreate(payload);
            }
            if (action === 'portal_invite.create') {
                return eproc.enqueuePortalInviteCreate(payload);
            }
            if (action === 'workflow.transition') {
                return eproc.enqueueWorkflowTransition(payload);
            }
            if (typeof eproc.enqueue === 'function') {
                return eproc.enqueue(action, payload);
            }
        }
        if (module === 'manufacturing') {
            var mfg = root.RatebOfflineManufacturingAdapter;
            if (!mfg) {
                return Promise.reject(new Error('manufacturing_adapter_unavailable'));
            }
            if (action === 'bom.create') {
                return mfg.enqueueBomCreate(payload);
            }
            if (action === 'bom.update') {
                return mfg.enqueueBomUpdate(payload);
            }
            if (action === 'routing.create') {
                return mfg.enqueueRoutingCreate(payload);
            }
            if (action === 'routing.update') {
                return mfg.enqueueRoutingUpdate(payload);
            }
            if (action === 'production_order.create') {
                return mfg.enqueueProductionOrderCreate(payload);
            }
            if (action === 'production_order.update') {
                return mfg.enqueueProductionOrderUpdate(payload);
            }
            if (action === 'work_order.create') {
                return mfg.enqueueWorkOrderCreate(payload);
            }
            if (action === 'work_order.update') {
                return mfg.enqueueWorkOrderUpdate(payload);
            }
            if (action === 'quality_check.create') {
                return mfg.enqueueQualityCheckCreate(payload);
            }
            if (action === 'workflow.transition') {
                return mfg.enqueueWorkflowTransition(payload);
            }
            if (typeof mfg.enqueue === 'function') {
                return mfg.enqueue(action, payload);
            }
        }
        if (module === 'payroll') {
            var pay = root.RatebOfflinePayrollAdapter;
            if (!pay) {
                return Promise.reject(new Error('payroll_adapter_unavailable'));
            }
            if (action === 'salary_structure.create') {
                return pay.enqueueSalaryStructureCreate(payload);
            }
            if (action === 'salary_structure.update') {
                return pay.enqueueSalaryStructureUpdate(payload);
            }
            if (action === 'employee_salary.create') {
                return pay.enqueueEmployeeSalaryCreate(payload);
            }
            if (action === 'employee_salary.update') {
                return pay.enqueueEmployeeSalaryUpdate(payload);
            }
            if (action === 'payroll_batch.create') {
                return pay.enqueuePayrollBatchCreate(payload);
            }
            if (action === 'payroll_batch.update') {
                return pay.enqueuePayrollBatchUpdate(payload);
            }
            if (action === 'loan.create') {
                return pay.enqueueLoanCreate(payload);
            }
            if (action === 'advance.create') {
                return pay.enqueueAdvanceCreate(payload);
            }
            if (action === 'overtime.create') {
                return pay.enqueueOvertimeCreate(payload);
            }
            if (action === 'workflow.transition') {
                return pay.enqueueWorkflowTransition(payload);
            }
            if (typeof pay.enqueue === 'function') {
                return pay.enqueue(action, payload);
            }
        }
        if (module === 'quality') {
            var qms = root.RatebOfflineQualityAdapter;
            if (!qms) {
                return Promise.reject(new Error('quality_adapter_unavailable'));
            }
            if (action === 'inspection.create') {
                return qms.enqueueInspectionCreate(payload);
            }
            if (action === 'inspection.update') {
                return qms.enqueueInspectionUpdate(payload);
            }
            if (action === 'checklist.create') {
                return qms.enqueueChecklistCreate(payload);
            }
            if (action === 'audit.create') {
                return qms.enqueueAuditCreate(payload);
            }
            if (action === 'defect.create') {
                return qms.enqueueDefectCreate(payload);
            }
            if (action === 'nonconformity.create') {
                return qms.enqueueNonconformityCreate(payload);
            }
            if (action === 'corrective_action.create') {
                return qms.enqueueCorrectiveActionCreate(payload);
            }
            if (action === 'preventive_action.create') {
                return qms.enqueuePreventiveActionCreate(payload);
            }
            if (action === 'complaint.create') {
                return qms.enqueueComplaintCreate(payload);
            }
            if (action === 'supplier_quality.create') {
                return qms.enqueueSupplierQualityCreate(payload);
            }
            if (action === 'workflow.transition') {
                return qms.enqueueWorkflowTransition(payload);
            }
            if (typeof qms.enqueue === 'function') {
                return qms.enqueue(action, payload);
            }
        }
        if (module === 'documents') {
            var dms = root.RatebOfflineDocumentsAdapter;
            if (!dms) {
                return Promise.reject(new Error('documents_adapter_unavailable'));
            }
            if (action === 'repository.create') {
                return dms.enqueueRepositoryCreate(payload);
            }
            if (action === 'repository.update') {
                return dms.enqueueRepositoryUpdate(payload);
            }
            if (action === 'folder.create') {
                return dms.enqueueFolderCreate(payload);
            }
            if (action === 'folder.update') {
                return dms.enqueueFolderUpdate(payload);
            }
            if (action === 'document.create') {
                return dms.enqueueDocumentCreate(payload);
            }
            if (action === 'document.update') {
                return dms.enqueueDocumentUpdate(payload);
            }
            if (action === 'share.create') {
                return dms.enqueueShareCreate(payload);
            }
            if (action === 'permission.create') {
                return dms.enqueuePermissionCreate(payload);
            }
            if (action === 'workflow.transition') {
                return dms.enqueueWorkflowTransition(payload);
            }
            if (typeof dms.enqueue === 'function') {
                return dms.enqueue(action, payload);
            }
        }
        if (module === 'bi') {
            var bi = root.RatebOfflineBiAdapter;
            if (!bi) {
                return Promise.reject(new Error('bi_adapter_unavailable'));
            }
            if (action === 'dashboard.create') {
                return bi.enqueueDashboardCreate(payload);
            }
            if (action === 'kpi.create') {
                return bi.enqueueKpiCreate(payload);
            }
            if (action === 'report.create') {
                return bi.enqueueReportCreate(payload);
            }
            if (action === 'widget.create') {
                return bi.enqueueWidgetCreate(payload);
            }
            if (action === 'dataset.create') {
                return bi.enqueueDatasetCreate(payload);
            }
            if (action === 'alert.create') {
                return bi.enqueueAlertCreate(payload);
            }
            if (action === 'schedule.create') {
                return bi.enqueueScheduleCreate(payload);
            }
            if (action === 'export.create') {
                return bi.enqueueExportCreate(payload);
            }
            if (action === 'trend.create') {
                return bi.enqueueTrendCreate(payload);
            }
            if (action === 'forecast.create') {
                return bi.enqueueForecastCreate(payload);
            }
            if (action === 'scope.create') {
                return bi.enqueueScopeCreate(payload);
            }
            if (action === 'workflow.transition') {
                return bi.enqueueWorkflowTransition(payload);
            }
            if (typeof bi.enqueue === 'function') {
                return bi.enqueue(action, payload);
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
            || f['offline.hr']
            || f['offline.procurement']
            || f['offline.recruitment']
            || f['offline.accounting']
            || f['offline.crm']
            || f['offline.projects']
            || f['offline.assets']
            || f['offline.approval']
            || f['offline.procurement_enterprise']
            || f['offline.manufacturing']
            || f['offline.payroll']
            || f['offline.quality']
            || f['offline.documents']
            || f['offline.bi'])) {
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
 * RATEB Offline SDK bootstrap (Phase 14.2 + 15B + 16B + 17B CRM).
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
        'offline.hr': false,
        'offline.hr.employee': false,
        'offline.hr.training': false,
        'offline.hr.performance': false,
        'offline.hr.workflow': false,
        'offline.hr.masterdata': false,
        'offline.procurement': false,
        'offline.procurement.goods_receipt': false,
        'offline.recruitment': false,
        'offline.recruitment.candidates': false,
        'offline.recruitment.workflow': false,
        'offline.recruitment.assignment': false,
        'offline.accounting': false,
        'offline.accounting.journals': false,
        'offline.accounting.workflow': false,
        'offline.accounting.masterdata': false,
        'offline.crm': false,
        'offline.crm.leads': false,
        'offline.crm.workflow': false,
        'offline.crm.activities': false,
        'offline.crm.masterdata': false,
        'offline.projects': false,
        'offline.projects.tasks': false,
        'offline.projects.workflow': false,
        'offline.projects.timesheets': false,
        'offline.projects.masterdata': false,
        'offline.assets': false,
        'offline.assets.maintenance': false,
        'offline.assets.workflow': false,
        'offline.assets.inspections': false,
        'offline.assets.masterdata': false,
        'offline.approval': false,
        'offline.approval.requests': false,
        'offline.approval.workflow': false,
        'offline.approval.masterdata': false,
        'offline.procurement_enterprise': false,
        'offline.procurement_enterprise.suppliers': false,
        'offline.procurement_enterprise.tenders': false,
        'offline.procurement_enterprise.contracts': false,
        'offline.procurement_enterprise.workflow': false,
        'offline.procurement_enterprise.masterdata': false,
        'offline.manufacturing': false,
        'offline.manufacturing.production': false,
        'offline.manufacturing.workflow': false,
        'offline.manufacturing.quality': false,
        'offline.manufacturing.masterdata': false,
        'offline.payroll': false,
        'offline.payroll.employee': false,
        'offline.payroll.batch': false,
        'offline.payroll.workflow': false,
        'offline.payroll.masterdata': false,
        'offline.quality': false,
        'offline.quality.inspections': false,
        'offline.quality.audit': false,
        'offline.quality.workflow': false,
        'offline.quality.masterdata': false,
        'offline.documents': false,
        'offline.documents.repositories': false,
        'offline.documents.workflow': false,
        'offline.documents.masterdata': false,
        'offline.bi': false,
        'offline.bi.dashboards': false,
        'offline.bi.reports': false,
        'offline.bi.workflow': false,
        'offline.bi.masterdata': false,
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
            hr_enterprise: !!flags['offline.hr'],
            hr_employee: !!flags['offline.hr.employee'],
            hr_training: !!flags['offline.hr.training'],
            hr_performance: !!flags['offline.hr.performance'],
            hr_workflow: !!flags['offline.hr.workflow'],
            hr_masterdata: !!flags['offline.hr.masterdata'],
            procurement: !!flags['offline.procurement'],
            procurement_goods_receipt: !!flags['offline.procurement.goods_receipt'],
            recruitment: !!flags['offline.recruitment'],
            recruitment_candidates: !!flags['offline.recruitment.candidates'],
            recruitment_workflow: !!flags['offline.recruitment.workflow'],
            recruitment_assignment: !!flags['offline.recruitment.assignment'],
            accounting: !!flags['offline.accounting'],
            accounting_journals: !!flags['offline.accounting.journals'],
            accounting_workflow: !!flags['offline.accounting.workflow'],
            accounting_masterdata: !!flags['offline.accounting.masterdata'],
            crm: !!flags['offline.crm'],
            crm_leads: !!flags['offline.crm.leads'],
            crm_workflow: !!flags['offline.crm.workflow'],
            crm_activities: !!flags['offline.crm.activities'],
            crm_masterdata: !!flags['offline.crm.masterdata'],
            projects: !!flags['offline.projects'],
            projects_tasks: !!flags['offline.projects.tasks'],
            projects_workflow: !!flags['offline.projects.workflow'],
            projects_timesheets: !!flags['offline.projects.timesheets'],
            projects_masterdata: !!flags['offline.projects.masterdata'],
            assets: !!flags['offline.assets'],
            assets_maintenance: !!flags['offline.assets.maintenance'],
            assets_workflow: !!flags['offline.assets.workflow'],
            assets_inspections: !!flags['offline.assets.inspections'],
            assets_masterdata: !!flags['offline.assets.masterdata'],
            approval: !!flags['offline.approval'],
            approval_requests: !!flags['offline.approval.requests'],
            approval_workflow: !!flags['offline.approval.workflow'],
            approval_masterdata: !!flags['offline.approval.masterdata'],
            procurement_enterprise: !!flags['offline.procurement_enterprise'],
            procurement_enterprise_suppliers: !!flags['offline.procurement_enterprise.suppliers'],
            procurement_enterprise_tenders: !!flags['offline.procurement_enterprise.tenders'],
            procurement_enterprise_contracts: !!flags['offline.procurement_enterprise.contracts'],
            procurement_enterprise_workflow: !!flags['offline.procurement_enterprise.workflow'],
            procurement_enterprise_masterdata: !!flags['offline.procurement_enterprise.masterdata'],
            manufacturing: !!flags['offline.manufacturing'],
            manufacturing_production: !!flags['offline.manufacturing.production'],
            manufacturing_workflow: !!flags['offline.manufacturing.workflow'],
            manufacturing_quality: !!flags['offline.manufacturing.quality'],
            manufacturing_masterdata: !!flags['offline.manufacturing.masterdata'],
            payroll: !!flags['offline.payroll'],
            payroll_employee: !!flags['offline.payroll.employee'],
            payroll_batch: !!flags['offline.payroll.batch'],
            payroll_workflow: !!flags['offline.payroll.workflow'],
            payroll_masterdata: !!flags['offline.payroll.masterdata'],
            quality: !!flags['offline.quality'],
            quality_inspections: !!flags['offline.quality.inspections'],
            quality_audit: !!flags['offline.quality.audit'],
            quality_workflow: !!flags['offline.quality.workflow'],
            quality_masterdata: !!flags['offline.quality.masterdata'],
            documents: !!flags['offline.documents'],
            documents_repositories: !!flags['offline.documents.repositories'],
            documents_workflow: !!flags['offline.documents.workflow'],
            documents_masterdata: !!flags['offline.documents.masterdata'],
            bi: !!flags['offline.bi'],
            bi_dashboards: !!flags['offline.bi.dashboards'],
            bi_reports: !!flags['offline.bi.reports'],
            bi_workflow: !!flags['offline.bi.workflow'],
            bi_masterdata: !!flags['offline.bi.masterdata'],
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
        isHumanResourcesEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.hr']);
        },
        isHumanResourcesEmployeeEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.hr']
                && flags['offline.hr.employee']);
        },
        isHumanResourcesTrainingEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.hr']
                && flags['offline.hr.training']);
        },
        isHumanResourcesPerformanceEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.hr']
                && flags['offline.hr.performance']);
        },
        isHumanResourcesWorkflowEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.hr']
                && flags['offline.hr.workflow']);
        },
        isHumanResourcesMasterDataEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.hr']
                && flags['offline.hr.masterdata']);
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
        isAccountingEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.accounting']);
        },
        isAccountingJournalsEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.accounting']
                && flags['offline.accounting.journals']);
        },
        isAccountingWorkflowEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.accounting']
                && flags['offline.accounting.workflow']);
        },
        isAccountingMasterDataEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.accounting']
                && flags['offline.accounting.masterdata']);
        },
        isCrmEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.crm']);
        },
        isCrmLeadsEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.crm']
                && flags['offline.crm.leads']);
        },
        isCrmWorkflowEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.crm']
                && flags['offline.crm.workflow']);
        },
        isCrmActivitiesEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.crm']
                && flags['offline.crm.activities']);
        },
        isCrmMasterDataEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.crm']
                && flags['offline.crm.masterdata']);
        },
        isProjectsEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.projects']);
        },
        isProjectsTasksEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.projects']
                && flags['offline.projects.tasks']);
        },
        isProjectsWorkflowEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.projects']
                && flags['offline.projects.workflow']);
        },
        isProjectsTimesheetsEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.projects']
                && flags['offline.projects.timesheets']);
        },
        isProjectsMasterDataEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.projects']
                && flags['offline.projects.masterdata']);
        },
        isAssetsEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.assets']);
        },
        isAssetsMaintenanceEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.assets']
                && flags['offline.assets.maintenance']);
        },
        isAssetsWorkflowEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.assets']
                && flags['offline.assets.workflow']);
        },
        isAssetsInspectionsEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.assets']
                && flags['offline.assets.inspections']);
        },
        isAssetsMasterDataEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.assets']
                && flags['offline.assets.masterdata']);
        },
        isApprovalEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.approval']);
        },
        isApprovalRequestsEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.approval']
                && flags['offline.approval.requests']);
        },
        isApprovalWorkflowEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.approval']
                && flags['offline.approval.workflow']);
        },
        isApprovalMasterDataEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.approval']
                && flags['offline.approval.masterdata']);
        },
        isProcurementEnterpriseEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.procurement_enterprise']);
        },
        isProcurementEnterpriseSuppliersEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.procurement_enterprise']
                && flags['offline.procurement_enterprise.suppliers']);
        },
        isProcurementEnterpriseTendersEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.procurement_enterprise']
                && flags['offline.procurement_enterprise.tenders']);
        },
        isProcurementEnterpriseContractsEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.procurement_enterprise']
                && flags['offline.procurement_enterprise.contracts']);
        },
        isProcurementEnterpriseWorkflowEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.procurement_enterprise']
                && flags['offline.procurement_enterprise.workflow']);
        },
        isProcurementEnterpriseMasterDataEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.procurement_enterprise']
                && flags['offline.procurement_enterprise.masterdata']);
        },
        isManufacturingEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.manufacturing']);
        },
        isManufacturingProductionEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.manufacturing']
                && flags['offline.manufacturing.production']);
        },
        isManufacturingWorkflowEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.manufacturing']
                && flags['offline.manufacturing.workflow']);
        },
        isManufacturingQualityEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.manufacturing']
                && flags['offline.manufacturing.quality']);
        },
        isManufacturingMasterDataEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.manufacturing']
                && flags['offline.manufacturing.masterdata']);
        },
        isPayrollEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.payroll']);
        },
        isPayrollEmployeeEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.payroll']
                && flags['offline.payroll.employee']);
        },
        isPayrollBatchEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.payroll']
                && flags['offline.payroll.batch']);
        },
        isPayrollWorkflowEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.payroll']
                && flags['offline.payroll.workflow']);
        },
        isPayrollMasterDataEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.payroll']
                && flags['offline.payroll.masterdata']);
        },
        isQualityEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.quality']);
        },
        isQualityInspectionsEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.quality']
                && flags['offline.quality.inspections']);
        },
        isQualityAuditEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.quality']
                && flags['offline.quality.audit']);
        },
        isQualityWorkflowEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.quality']
                && flags['offline.quality.workflow']);
        },
        isQualityMasterDataEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.quality']
                && flags['offline.quality.masterdata']);
        },
        isDocumentsEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.documents']);
        },
        isDocumentsRepositoriesEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.documents']
                && flags['offline.documents.repositories']);
        },
        isDocumentsWorkflowEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.documents']
                && flags['offline.documents.workflow']);
        },
        isDocumentsMasterDataEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.documents']
                && flags['offline.documents.masterdata']);
        },
        isBiEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.bi']);
        },
        isBiDashboardsEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.bi']
                && flags['offline.bi.dashboards']);
        },
        isBiReportsEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.bi']
                && flags['offline.bi.reports']);
        },
        isBiWorkflowEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.bi']
                && flags['offline.bi.workflow']);
        },
        isBiMasterDataEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.bi']
                && flags['offline.bi.masterdata']);
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
        accounting: function () { return root.RatebOfflineAccountingAdapter || null; },
        crm: function () { return root.RatebOfflineCrmAdapter || null; },
        projects: function () { return root.RatebOfflineProjectsAdapter || null; },
        assets: function () { return root.RatebOfflineAssetsAdapter || null; },
        approvals: function () { return root.RatebOfflineApprovalAdapter || null; },
        procurementEnterprise: function () { return root.RatebOfflineProcurementEnterpriseAdapter || null; },
        manufacturing: function () { return root.RatebOfflineManufacturingAdapter || null; },
        payroll: function () { return root.RatebOfflinePayrollAdapter || null; },
        quality: function () { return root.RatebOfflineQualityAdapter || null; },
        documents: function () { return root.RatebOfflineDocumentsAdapter || null; },
        bi: function () { return root.RatebOfflineBiAdapter || null; },
        opsForms: function () { return root.RatebOfflineOpsForms || null; },
        shell: function () { return root.RatebOfflineShellAdapter || null; },
        auth: function () { return root.RatebOfflineAuthLock || null; },
        rbac: function () { return root.RatebOfflineRbacCache || null; },
        masterData: function () { return root.RatebOfflineMasterData || null; },
        schema: function () { return root.RatebOfflineSchema || null; },
        deltaPull: function () { return root.RatebOfflineDeltaPull || null; }
    };
})(typeof window !== 'undefined' ? window : globalThis);

