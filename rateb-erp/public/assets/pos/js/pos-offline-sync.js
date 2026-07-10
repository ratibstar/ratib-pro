(function () {
    'use strict';

    var DB_NAME = 'rateb_pos_offline';
    var DB_VERSION = 3;
    var QUEUE_STORE = 'queue';
    var CATALOG_STORE = 'catalog';
    var META_STORE = 'meta';
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

        push: function (item, options) {
            options = options || {};
            var entry = {
                client_id: item.client_id || ('local-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8)),
                action: item.action || 'unknown',
                payload: item.payload || {},
                occurred_at: item.occurred_at || new Date().toISOString(),
                version: item.version || 1
            };
            return readAll().then(function (queue) {
                queue.push(entry);
                return writeAll(queue);
            }).then(function () {
                return refreshDepth();
            }).then(function () {
                if (window.RatebPosConnectivity ? window.RatebPosConnectivity.isOnline() : navigator.onLine) {
                    return window.RatebPosOffline.sync(options);
                }
                return { queued: true, queueDepth: window.RatebPosOffline.queueDepth };
            });
        },

        sync: function (options) {
            options = options || {};
            return readAll().then(function (queue) {
                if (!queue.length) {
                    return { accepted: 0, duplicate: 0, conflict: 0, queueDepth: 0 };
                }
                var reg = registerContext(options);
                var base = options.apiBase || defaultApiBase();
                return fetch(base + '/push', {
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
                    return res.json().then(function (payload) {
                        if (!res.ok || !payload.ok) {
                            throw new Error((payload && payload.error && payload.error.message) || 'sync_failed');
                        }
                        return writeAll([]).then(function () {
                            window.RatebPosOffline.queueDepth = 0;
                            return Object.assign({ queueDepth: 0 }, payload.result || {});
                        });
                    });
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

    function deferOfflineInit() {
        window.RatebPosOffline.init().catch(function () { /* IndexedDB optional */ });
    }

    deferOfflineInit();

    (function setupConnectivity() {
        var listeners = [];
        var online = navigator.onLine !== false;
        var probeTimer = null;
        var probing = false;

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
                return;
            }
            online = next;
            emit();
            if (online && window.RatebPosOffline && window.RatebPosOffline.sync) {
                window.RatebPosOffline.sync().catch(function () { /* retry later */ });
            }
        }

        function resolveProbeUrl() {
            var cfgEl = document.getElementById('rateb-pos-register-config');
            var cfg = {};
            try {
                cfg = JSON.parse((cfgEl && cfgEl.textContent) || '{}');
            } catch (e) {
                cfg = {};
            }
            var api = cfg.api || {};
            if (api.sync) {
                return String(api.sync).replace(/\/$/, '') + '/status';
            }
            if (api.bootstrap) {
                return String(api.bootstrap);
            }
            return defaultApiBase() + '/status';
        }

        function probe() {
            if (probing) {
                return Promise.resolve(online);
            }
            if (navigator.onLine === false) {
                setOnline(false);
                return Promise.resolve(false);
            }
            probing = true;
            var url = resolveProbeUrl();
            var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
            var timer = setTimeout(function () {
                if (ctrl) {
                    try { ctrl.abort(); } catch (e) { /* ignore */ }
                }
            }, 3500);
            return fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'application/json', 'X-Rateb-Connectivity': '1' },
                signal: ctrl ? ctrl.signal : undefined
            }).then(function () {
                // Any HTTP response means the POS origin is reachable.
                setOnline(true);
                return true;
            }).catch(function () {
                setOnline(false);
                return false;
            }).finally(function () {
                clearTimeout(timer);
                probing = false;
            });
        }

        window.RatebPosConnectivity = {
            isOnline: function () { return online; },
            probe: probe,
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

        window.addEventListener('online', function () { probe(); });
        window.addEventListener('offline', function () { setOnline(false); });
        setTimeout(probe, 200);
        probeTimer = setInterval(probe, 12000);
        window.addEventListener('beforeunload', function () {
            if (probeTimer) {
                clearInterval(probeTimer);
            }
        });
    })();
})();
