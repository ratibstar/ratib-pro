(function () {
    'use strict';

    var DB_NAME = 'rateb_pos_offline';
    var DB_VERSION = 2;
    var QUEUE_STORE = 'queue';
    var CATALOG_STORE = 'catalog';
    var LEGACY_KEY = 'rateb_pos_offline_queue_v1';
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
        return Object.assign({}, product, {
            id: product.id,
            name: product.name || product.item_name || '',
            sku: product.sku || product.code || product.item_code || '',
            barcode: product.barcode || product.barcode_value || '',
            unit_price: product.unit_price != null ? product.unit_price : (product.price || 0),
            category_id: product.category_id || null
        });
    }

    function catalogPutMany(products) {
        if (!Array.isArray(products) || !products.length) {
            return Promise.resolve(0);
        }
        return withStore(CATALOG_STORE, 'readwrite', function (store) {
            return new Promise(function (resolve, reject) {
                var saved = 0;
                products.forEach(function (raw) {
                    var product = normalizeProduct(raw);
                    if (!product) {
                        return;
                    }
                    var req = store.put(product);
                    req.onsuccess = function () { saved += 1; };
                    req.onerror = function () { reject(req.error); };
                });
                resolve(saved);
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
        var ctx = cfg.context || {};
        var sess = cfg.session || {};
        return {
            terminal_id: options.terminalId || (ctx.terminal && ctx.terminal.id) || sess.terminal_id || 0,
            branch_id: options.branchId || (ctx.branch && ctx.branch.id) || sess.branch_id || 0,
            scope: {
                terminal_id: (ctx.terminal && ctx.terminal.id) || sess.terminal_id || 0,
                shift_id: (ctx.shift && ctx.shift.id) || sess.shift_id || 0,
                branch_id: (ctx.branch && ctx.branch.id) || sess.branch_id || 0,
                warehouse_id: sess.warehouse_id || null,
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
                if (navigator.onLine) {
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

    window.RatebPosOffline.init().catch(function () { /* IndexedDB optional */ });

    window.addEventListener('online', function () {
        window.RatebPosOffline.sync().catch(function () { /* retry later */ });
    });
})();
