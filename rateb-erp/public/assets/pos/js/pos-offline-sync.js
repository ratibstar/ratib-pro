(function () {
    'use strict';

    var DB_NAME = 'rateb_pos_offline';
    var DB_VERSION = 4;
    var QUEUE_STORE = 'queue';
    var CATALOG_STORE = 'catalog';
    var META_STORE = 'meta';
    var SUSPEND_STORE = 'suspended';
    var LEGACY_KEY = 'rateb_pos_offline_queue_v1';
    var CATALOG_META_KEY = 'catalog_meta';
    var dbPromise = null;

    function openDb() {
        if (dbPromise) {
            return dbPromise;
        }
        dbPromise = new Promise(function (resolve, reject) {
            if (!window.indexedDB) {
                reject(new Error('indexeddb_unavailable'));
                return;
            }
            var req = indexedDB.open(DB_NAME, DB_VERSION);
            req.onupgradeneeded = function () {
                var db = req.result;
                if (!db.objectStoreNames.contains(QUEUE_STORE)) {
                    db.createObjectStore(QUEUE_STORE, { keyPath: 'client_id' });
                }
                if (!db.objectStoreNames.contains(CATALOG_STORE)) {
                    db.createObjectStore(CATALOG_STORE, { keyPath: 'id' });
                }
                if (!db.objectStoreNames.contains(META_STORE)) {
                    db.createObjectStore(META_STORE, { keyPath: 'key' });
                }
                if (!db.objectStoreNames.contains(SUSPEND_STORE)) {
                    db.createObjectStore(SUSPEND_STORE, { keyPath: 'client_id' });
                }
            };
            req.onsuccess = function () { resolve(req.result); };
            req.onerror = function () { reject(req.error || new Error('idb_open_failed')); };
        });
        return dbPromise;
    }

    function withStore(storeName, mode, fn) {
        return openDb().then(function (db) {
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
                    tx.oncomplete = function () { /* promise handles */ };
                } else {
                    tx.oncomplete = function () { resolve(out); };
                }
                tx.onerror = function () { reject(tx.error || new Error('idb_tx_failed')); };
            });
        });
    }

    function readAll() {
        return withStore(QUEUE_STORE, 'readonly', function (store) {
            return new Promise(function (resolve, reject) {
                var req = store.getAll();
                req.onsuccess = function () { resolve(req.result || []); };
                req.onerror = function () { reject(req.error); };
            });
        });
    }

    function writeAll(items) {
        return withStore(QUEUE_STORE, 'readwrite', function (store) {
            return new Promise(function (resolve, reject) {
                var clearReq = store.clear();
                clearReq.onsuccess = function () {
                    var pending = items.length;
                    if (!pending) {
                        resolve();
                        return;
                    }
                    items.forEach(function (item) {
                        var putReq = store.put(item);
                        putReq.onsuccess = function () {
                            pending -= 1;
                            if (!pending) {
                                resolve();
                            }
                        };
                        putReq.onerror = function () { reject(putReq.error); };
                    });
                };
                clearReq.onerror = function () { reject(clearReq.error); };
            });
        });
    }

    function removeByKeys(keys) {
        var clearSet = {};
        (keys || []).forEach(function (k) {
            if (k) {
                clearSet[String(k)] = true;
            }
        });
        return readAll().then(function (queue) {
            var remaining = (queue || []).filter(function (item) {
                var key = String(item.client_id || item.idempotency_key || '');
                return !clearSet[key];
            });
            return writeAll(remaining).then(function () {
                return remaining;
            });
        });
    }

    function suspendedPut(entry) {
        if (!entry || !entry.client_id) {
            return Promise.resolve(false);
        }
        return withStore(SUSPEND_STORE, 'readwrite', function (store) {
            store.put(entry);
            return true;
        });
    }

    function suspendedList() {
        return withStore(SUSPEND_STORE, 'readonly', function (store) {
            return new Promise(function (resolve, reject) {
                var req = store.getAll();
                req.onsuccess = function () { resolve(req.result || []); };
                req.onerror = function () { reject(req.error); };
            });
        }).catch(function () {
            return [];
        });
    }

    function suspendedRemove(clientId) {
        return withStore(SUSPEND_STORE, 'readwrite', function (store) {
            store.delete(String(clientId));
            return true;
        });
    }

    function suspendedGet(clientId) {
        return withStore(SUSPEND_STORE, 'readonly', function (store) {
            return new Promise(function (resolve, reject) {
                var req = store.get(String(clientId));
                req.onsuccess = function () { resolve(req.result || null); };
                req.onerror = function () { reject(req.error); };
            });
        }).catch(function () {
            return null;
        });
    }

    /** Resume helper: load suspended draft; if lines were wiped, recover from sync queue payload. */
    function suspendedGetForResume(clientId) {
        var key = String(clientId || '');
        return suspendedGet(key).then(function (entry) {
            if (entry && Array.isArray(entry.lines) && entry.lines.length) {
                return entry;
            }
            return readAll().then(function (queue) {
                var match = null;
                (queue || []).forEach(function (item) {
                    if (!item || item.action !== 'suspend') {
                        return;
                    }
                    var payload = item.payload || {};
                    if (String(item.client_id) === key
                        || String(payload.local_client_id || '') === key) {
                        match = item;
                    }
                });
                if (!match || !match.payload) {
                    return entry;
                }
                var payload = match.payload;
                var recovered = {
                    client_id: key,
                    id: key,
                    order_no: (entry && entry.order_no) || ('OFF-' + key.slice(-6).toUpperCase()),
                    lines: Array.isArray(payload.lines) ? payload.lines : [],
                    customer: payload.customer || (entry && entry.customer) || null,
                    totals: entry && entry.totals ? entry.totals : null,
                    total: entry && entry.total != null ? entry.total : 0,
                    local: true,
                    recovered_from_queue: true
                };
                if (!recovered.lines.length) {
                    return entry;
                }
                return suspendedPut(recovered).then(function () {
                    return recovered;
                });
            });
        });
    }

    function buildScopePayload(options) {
        options = options || {};
        var reg = registerContext(options);
        return Object.assign({}, reg.scope, {
            terminal_id: reg.terminal_id || reg.scope.terminal_id || 0,
            branch_id: reg.branch_id || reg.scope.branch_id || 0
        });
    }

    function newClientId(prefix) {
        return String(prefix || 'local') + '-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8);
    }

    function normalizeProduct(product) {
        if (!product || product.id == null) {
            return null;
        }
        var name = product.item_name || product.name || '';
        return Object.assign({}, product, {
            id: product.id,
            name: name,
            item_name: product.item_name || name,
            sku: product.sku || product.code || product.item_code || '',
            barcode: product.barcode || product.barcode_value || product.sku || product.item_code || '',
            unit_price: product.unit_price != null ? product.unit_price : (product.price || 0),
            category_id: product.category_id != null ? product.category_id : null,
            image_url: product.image_url || product.thumbnail_url || product.image || '',
            availability: product.availability || null
        });
    }

    function catalogPutMany(products) {
        if (!Array.isArray(products) || !products.length) {
            return Promise.resolve(0);
        }
        return openDb().then(function (db) {
            return new Promise(function (resolve, reject) {
                var tx = db.transaction(CATALOG_STORE, 'readwrite');
                var store = tx.objectStore(CATALOG_STORE);
                var saved = 0;
                products.forEach(function (raw) {
                    var product = normalizeProduct(raw);
                    if (!product) {
                        return;
                    }
                    store.put(product);
                    saved += 1;
                });
                tx.oncomplete = function () { resolve(saved); };
                tx.onerror = function () { reject(tx.error || new Error('idb_catalog_put_failed')); };
                tx.onabort = function () { reject(tx.error || new Error('idb_catalog_put_aborted')); };
            });
        });
    }

    function catalogGetAll() {
        return withStore(CATALOG_STORE, 'readonly', function (store) {
            return new Promise(function (resolve, reject) {
                var req = store.getAll();
                req.onsuccess = function () { resolve(req.result || []); };
                req.onerror = function () { reject(req.error); };
            });
        });
    }

    function catalogMetaPut(meta) {
        if (!meta || typeof meta !== 'object') {
            return Promise.resolve(false);
        }
        return openDb().then(function (db) {
            return new Promise(function (resolve, reject) {
                var tx = db.transaction(META_STORE, 'readwrite');
                tx.objectStore(META_STORE).put({
                    key: CATALOG_META_KEY,
                    productIndex: meta.productIndex || {},
                    productImages: meta.productImages || {},
                    categories: Array.isArray(meta.categories) ? meta.categories : [],
                    savedAt: meta.savedAt || Date.now()
                });
                tx.oncomplete = function () { resolve(true); };
                tx.onerror = function () { reject(tx.error || new Error('idb_meta_put_failed')); };
            });
        }).catch(function () {
            return false;
        });
    }

    function catalogMetaGet() {
        return openDb().then(function (db) {
            return new Promise(function (resolve, reject) {
                var tx = db.transaction(META_STORE, 'readonly');
                var req = tx.objectStore(META_STORE).get(CATALOG_META_KEY);
                req.onsuccess = function () { resolve(req.result || null); };
                req.onerror = function () { reject(req.error); };
            });
        }).catch(function () {
            return null;
        });
    }

    function matchesQuery(product, query) {
        var q = String(query || '').trim().toLowerCase();
        if (!q) {
            return true;
        }
        var fields = [
            product.name,
            product.sku,
            product.barcode,
            product.code,
            product.item_code,
            product.id
        ];
        return fields.some(function (field) {
            return field != null && String(field).toLowerCase().indexOf(q) !== -1;
        });
    }

    function catalogSearch(query, limit) {
        var max = Math.max(1, Math.min(100, limit || 40));
        return catalogGetAll().then(function (items) {
            return items.filter(function (item) {
                return matchesQuery(item, query);
            }).slice(0, max);
        });
    }

    function catalogLookupBarcode(code) {
        var needle = String(code || '').trim().toLowerCase();
        if (!needle) {
            return Promise.resolve(null);
        }
        return catalogGetAll().then(function (items) {
            for (var i = 0; i < items.length; i += 1) {
                var item = items[i];
                var barcode = String(item.barcode || item.sku || item.code || '').toLowerCase();
                if (barcode && barcode === needle) {
                    return item;
                }
            }
            return null;
        });
    }

    function migrateLegacy() {
        try {
            var raw = localStorage.getItem(LEGACY_KEY);
            if (!raw) {
                return Promise.resolve();
            }
            var parsed = JSON.parse(raw);
            if (!Array.isArray(parsed) || !parsed.length) {
                localStorage.removeItem(LEGACY_KEY);
                return Promise.resolve();
            }
            return writeAll(parsed).then(function () {
                localStorage.removeItem(LEGACY_KEY);
            });
        } catch (e) {
            return Promise.resolve();
        }
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

    /** Append a path segment before ?query (rateb_app_url adds ?company_id=). */
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
            var u = new URL(base, window.location.href);
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

    function configuredSyncBase() {
        var cfgEl = document.getElementById('rateb-pos-register-config');
        var cfg = {};
        try {
            cfg = JSON.parse((cfgEl && cfgEl.textContent) || '{}');
        } catch (e) {
            cfg = {};
        }
        return (cfg.api && cfg.api.sync) ? String(cfg.api.sync) : defaultApiBase();
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="rateb-csrf"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function registerContext(options) {
        options = options || {};
        var cfgEl = document.getElementById('rateb-pos-register-config');
        var cfg = {};
        try {
            cfg = JSON.parse((cfgEl && cfgEl.textContent) || '{}');
        } catch (e) {
            cfg = {};
        }
        var ctx = cfg.registerScope || cfg.context || {};
        var sess = cfg.session || {};
        return {
            terminal_id: options.terminalId || ctx.terminal_id || (ctx.terminal && ctx.terminal.id) || sess.terminal_id || 0,
            branch_id: options.branchId || ctx.branch_id || (ctx.branch && ctx.branch.id) || sess.branch_id || 0,
            scope: {
                terminal_id: ctx.terminal_id || (ctx.terminal && ctx.terminal.id) || sess.terminal_id || 0,
                shift_id: ctx.shift_id || (ctx.shift && ctx.shift.id) || sess.shift_id || cfg.shiftId || 0,
                branch_id: ctx.branch_id || (ctx.branch && ctx.branch.id) || sess.branch_id || 0,
                warehouse_id: ctx.warehouse_id || sess.warehouse_id || null,
                session_id: sess.db_session_id || null,
                user_id: cfg.userId || 0
            }
        };
    }

    function refreshDepth() {
        return readAll().then(function (items) {
            window.RatebPosOffline.queueDepth = items.length;
            return items.length;
        });
    }

    window.RatebPosOffline = {
        queueDepth: 0,

        init: function () {
            return migrateLegacy().then(refreshDepth);
        },

        catalogPutMany: catalogPutMany,
        catalogSearch: catalogSearch,
        catalogLookupBarcode: catalogLookupBarcode,
        catalogGetAll: catalogGetAll,
        catalogMetaPut: catalogMetaPut,
        catalogMetaGet: catalogMetaGet,
        buildScope: buildScopePayload,
        newClientId: newClientId,
        suspendedPut: suspendedPut,
        suspendedList: suspendedList,
        suspendedRemove: suspendedRemove,
        suspendedGet: suspendedGet,
        suspendedGetForResume: suspendedGetForResume,

        push: function (item, options) {
            options = options || {};
            var payload = item.payload && typeof item.payload === 'object' ? Object.assign({}, item.payload) : {};
            delete payload.url;
            delete payload.method;
            delete payload.headers;
            var entry = {
                client_id: item.client_id || newClientId('local'),
                action: item.action || 'unknown',
                payload: payload,
                occurred_at: item.occurred_at || new Date().toISOString(),
                version: item.version || 1
            };
            return readAll().then(function (queue) {
                queue.push(entry);
                return writeAll(queue);
            }).then(function () {
                return refreshDepth();
            }).then(function () {
                // Require both browser net + POS probe — never auto-sync against a SW soft-offline stub.
                var canSync = navigator.onLine !== false
                    && (!window.RatebPosConnectivity || window.RatebPosConnectivity.isOnline());
                if (canSync) {
                    return window.RatebPosOffline.sync(options).catch(function (err) {
                        if (err && (err.offline || String(err.message || '') === 'sync_offline')) {
                            return { queued: true, queueDepth: window.RatebPosOffline.queueDepth, client_id: entry.client_id };
                        }
                        throw err;
                    });
                }
                return { queued: true, queueDepth: window.RatebPosOffline.queueDepth, client_id: entry.client_id };
            });
        },

        sync: function (options) {
            options = options || {};
            if (window.RatebPosAuthLock && typeof window.RatebPosAuthLock.sessionNeedsReauth === 'function'
                && window.RatebPosAuthLock.sessionNeedsReauth()) {
                return Promise.reject(new Error('session_reauth_required'));
            }
            return readAll().then(function (queue) {
                if (!queue.length) {
                    return { accepted: 0, duplicate: 0, conflict: 0, queueDepth: 0, clearable_keys: [] };
                }
                var reg = registerContext(options);
                var base = options.apiBase || configuredSyncBase();
                return fetch(joinUrlPath(base, '/push'), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': csrfToken()
                    },
                    body: JSON.stringify({
                        terminal_id: reg.terminal_id,
                        branch_id: reg.branch_id,
                        items: queue
                    })
                }).then(function (res) {
                    var softOffline = false;
                    try {
                        softOffline = String(res.headers.get('X-Rateb-Offline') || '') === '1';
                    } catch (eHdr) { /* ignore */ }
                    if (res.status === 401 || res.status === 403 || res.status === 419) {
                        if (window.RatebPosAuthLock && window.RatebPosAuthLock.markSessionNeedsReauth) {
                            window.RatebPosAuthLock.markSessionNeedsReauth();
                        }
                    }
                    return res.json().then(function (payload) {
                        if (softOffline || (payload && payload.offline)) {
                            var offlineErr = new Error('sync_offline');
                            offlineErr.offline = true;
                            throw offlineErr;
                        }
                        var result = (payload && payload.result) ? payload.result : {};
                        var clearable = Array.isArray(result.clearable_keys) ? result.clearable_keys : [];
                        if (!clearable.length && payload && payload.ok) {
                            clearable = [].concat(
                                Array.isArray(result.accepted_keys) ? result.accepted_keys : [],
                                Array.isArray(result.duplicate_keys) ? result.duplicate_keys : []
                            );
                        }
                        return removeByKeys(clearable).then(function (remaining) {
                            window.RatebPosOffline.queueDepth = remaining.length;
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
            });
        },

        status: function (options) {
            options = options || {};
            var base = options.apiBase || configuredSyncBase();
            return fetch(joinUrlPath(base, '/status'), {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            }).then(function (res) {
                return res.json();
            });
        }
    };

    function deferOfflineInit() {
        window.RatebPosOffline.init().catch(function () { /* IndexedDB optional */ });
    }

    /** Shared network helpers for POS scripts (Phase 2B). */
    window.RatebPosNet = {
        isOnline: function () {
            if (navigator.onLine === false) {
                return false;
            }
            if (window.RatebPosConnectivity && typeof window.RatebPosConnectivity.isOnline === 'function') {
                return window.RatebPosConnectivity.isOnline();
            }
            return true;
        },
        markOffline: function () {
            if (window.RatebPosConnectivity && typeof window.RatebPosConnectivity.setOnline === 'function') {
                window.RatebPosConnectivity.setOnline(false);
            } else {
                try {
                    document.dispatchEvent(new CustomEvent('rateb-pos-force-offline'));
                } catch (e) { /* ignore */ }
            }
            // If the PC still has net, re-probe quickly so a blip/timeout doesn't stick Offline.
            if (navigator.onLine !== false && window.RatebPosConnectivity
                && typeof window.RatebPosConnectivity.probe === 'function') {
                setTimeout(function () {
                    try { window.RatebPosConnectivity.probe(); } catch (e2) { /* ignore */ }
                }, 800);
            }
        },
        fetchJson: function (url, options, translate) {
            options = options || {};
            translate = translate || function (k, fb) { return fb || k; };
            if (!this.isOnline() && !options.allowOffline) {
                return Promise.reject(new Error(translate('pos_offline', 'Offline')));
            }
            var headers = options.headers || {};
            headers.Accept = 'application/json';
            if (options.method === 'POST') {
                headers['X-CSRF-Token'] = options.csrf || '';
            }
            return fetch(url, {
                method: options.method || 'GET',
                credentials: 'same-origin',
                headers: headers,
                body: options.body || null
            }).then(function (res) {
                return res.json().then(function (data) {
                    if (!res.ok) {
                        throw new Error((data && data.error) ? data.error : translate('invalid_request', 'Request failed'));
                    }
                    return data;
                });
            }).catch(function (err) {
                var name = String((err && err.name) || '');
                var msg = String((err && err.message) || err || '');
                if (name === 'AbortError' || /abort/i.test(msg)) {
                    throw err;
                }
                if (/Failed to fetch|NetworkError|ERR_INTERNET|ERR_FAILED/i.test(msg) || !navigator.onLine) {
                    window.RatebPosNet.markOffline();
                }
                throw err;
            });
        }
    };

    deferOfflineInit();

    (function setupConnectivity() {
        var listeners = [];
        var online = navigator.onLine !== false;
        var probeTimer = null;
        var probing = false;
        var failStreak = 0;

        function emit() {
            listeners.forEach(function (fn) {
                try { fn(online); } catch (e) { /* ignore */ }
            });
            try {
                document.dispatchEvent(new CustomEvent('rateb-pos-connectivity', { detail: { online: online } }));
            } catch (e2) { /* ignore */ }
        }

        function setOnline(next) {
            next = !!next;
            if (online === next) {
                // Still (re)arm the probe loop — navigator can flip without a state change.
                scheduleProbeLoop();
                return;
            }
            online = next;
            emit();
            if (online && window.RatebPosOffline && window.RatebPosOffline.sync) {
                window.RatebPosOffline.sync().catch(function () { /* retry later */ });
            }
            scheduleProbeLoop();
        }

        function resolveProbeUrl() {
            // Prefer a tiny static marker — fast and not blocked by slow POS bootstrap/API.
            try {
                var origin = window.location.origin || '';
                var path = window.location.pathname || '';
                var pub = path.indexOf('/public/') >= 0
                    ? path.slice(0, path.indexOf('/public/') + '/public'.length)
                    : '';
                if (origin && pub) {
                    return origin + pub + '/connectivity-probe.json?_=' + String(Date.now());
                }
            } catch (e0) { /* ignore */ }
            var cfgEl = document.getElementById('rateb-pos-register-config');
            var cfg = {};
            try {
                cfg = JSON.parse((cfgEl && cfgEl.textContent) || '{}');
            } catch (e) {
                cfg = {};
            }
            var api = cfg.api || {};
            if (api.sync) {
                return joinUrlPath(api.sync, '/status') + (String(api.sync).indexOf('?') >= 0 ? '&' : '?') + '_=' + Date.now();
            }
            if (api.bootstrap) {
                var boot = String(api.bootstrap);
                return boot + (boot.indexOf('?') >= 0 ? '&' : '?') + '_=' + Date.now();
            }
            try {
                var origin2 = window.location.origin || '';
                var path2 = window.location.pathname || '';
                var pub2 = path2.indexOf('/public/') >= 0
                    ? path2.slice(0, path2.indexOf('/public/') + '/public'.length)
                    : '';
                if (origin2 && pub2) {
                    return origin2 + pub2 + '/ratib-erp-build.txt?_=' + String(Date.now());
                }
            } catch (e3) { /* ignore */ }
            return joinUrlPath(defaultApiBase(), '/status') + '?_=' + Date.now();
        }

        function scheduleRecoverySoon(delayMs) {
            setTimeout(function () {
                if (navigator.onLine === false) {
                    return;
                }
                probe({ force: true });
            }, typeof delayMs === 'number' ? delayMs : 1200);
        }

        function probe(options) {
            options = options || {};
            if (probing && !options.force) {
                return Promise.resolve(online);
            }
            // Fully offline (browser flag) — never hit the network (avoids console spam).
            if (navigator.onLine === false) {
                failStreak = 2;
                setOnline(false);
                return Promise.resolve(false);
            }
            // Recovery path: browser reports connectivity. Always probe the origin even if we
            // previously marked ourselves offline (failed fetch / brief blip). Blocking here
            // required a full page refresh to go online again while the PC still had internet.
            probing = true;
            var url = resolveProbeUrl();
            var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
            var timedOut = false;
            var timer = setTimeout(function () {
                timedOut = true;
                if (ctrl) {
                    try { ctrl.abort(); } catch (e) { /* ignore */ }
                }
            }, 4000);
            return fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'application/json', 'X-Rateb-Connectivity': '1' },
                signal: ctrl ? ctrl.signal : undefined
            }).then(function (res) {
                // Any HTTP response means the origin is reachable (internet works).
                // Do not treat 404/5xx as "offline" — that trapped the UI on غير متصل
                // while Wi‑Fi was fine and forced a full refresh to recover.
                if (!res) {
                    if (!online) {
                        scheduleRecoverySoon(1500);
                    }
                    return online;
                }
                if (res.status === 401 || res.status === 403 || res.status === 419) {
                    if (window.RatebPosAuthLock && window.RatebPosAuthLock.markSessionNeedsReauth) {
                        window.RatebPosAuthLock.markSessionNeedsReauth();
                    }
                } else if (res.ok && window.RatebPosAuthLock && window.RatebPosAuthLock.clearSessionNeedsReauth) {
                    window.RatebPosAuthLock.clearSessionNeedsReauth();
                }
                failStreak = 0;
                setOnline(true);
                return true;
            }).catch(function (err) {
                var name = String((err && err.name) || '');
                var msg = String((err && err.message) || err || '');
                // Timeout/abort while browser says online is ambiguous — keep state, retry soon.
                if (timedOut || name === 'AbortError' || /abort/i.test(msg)) {
                    if (!online && navigator.onLine !== false) {
                        scheduleRecoverySoon(2000);
                    }
                    return online;
                }
                failStreak += 1;
                if (failStreak >= 2 || navigator.onLine === false) {
                    setOnline(false);
                    if (navigator.onLine !== false) {
                        scheduleRecoverySoon(2500);
                    }
                    return false;
                }
                scheduleRecoverySoon(1500);
                return online;
            }).finally(function () {
                clearTimeout(timer);
                probing = false;
            });
        }

        function scheduleProbeLoop() {
            if (probeTimer) {
                clearInterval(probeTimer);
                probeTimer = null;
            }
            // Browser fully offline — wait for window 'online' (no fetch spam).
            if (navigator.onLine === false) {
                return;
            }
            // Online: health check every 12s. Soft-offline with net up: recover every 3s.
            var interval = online ? 12000 : 3000;
            probeTimer = setInterval(function () {
                if (navigator.onLine === false) {
                    setOnline(false);
                    scheduleProbeLoop();
                    return;
                }
                probe();
            }, interval);
        }

        window.RatebPosConnectivity = {
            isOnline: function () { return online; },
            probe: probe,
            setOnline: setOnline,
            subscribe: function (fn) {
                if (typeof fn !== 'function') {
                    return function () {};
                }
                listeners.push(fn);
                try { fn(online); } catch (e) { /* ignore */ }
                return function () {
                    listeners = listeners.filter(function (x) { return x !== fn; });
                };
            }
        };

        function onBrowserOnline() {
            // Always re-arm the loop — it was stopped while navigator.onLine was false.
            scheduleProbeLoop();
            probe({ force: true });
            scheduleRecoverySoon(800);
            scheduleRecoverySoon(2500);
        }

        window.addEventListener('online', onBrowserOnline);
        window.addEventListener('offline', function () { setOnline(false); });
        document.addEventListener('rateb-pos-force-offline', function () {
            setOnline(false);
            if (navigator.onLine !== false) {
                scheduleProbeLoop();
                scheduleRecoverySoon(1000);
            }
        });
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible' && navigator.onLine !== false) {
                probe({ force: true });
                scheduleProbeLoop();
            }
        });
        window.addEventListener('focus', function () {
            if (navigator.onLine !== false && !online) {
                probe({ force: true });
            }
        });
        setTimeout(function () { probe({ force: true }); }, 200);
        scheduleProbeLoop();
        window.addEventListener('beforeunload', function () {
            if (probeTimer) {
                clearInterval(probeTimer);
            }
        });
    })();
})();
