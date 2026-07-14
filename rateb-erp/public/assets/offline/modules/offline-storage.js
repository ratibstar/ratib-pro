/*! RATEB Offline module offline-storage.js (Phase OA — sourced from offline/client). */

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

    /** Phase OA — shared singleton connection (open once; reopen only if closed). */
    var dbPromise = null;
    var dbInstance = null;

    function openDatabase() {
        if (dbInstance) {
            try {
                // Detect closed connection (Chrome throws on transaction after close).
                dbInstance.transaction(STORES.SYNC_META, 'readonly');
                return Promise.resolve(dbInstance);
            } catch (eClosed) {
                dbInstance = null;
                dbPromise = null;
            }
        }
        if (dbPromise) {
            return dbPromise;
        }
        dbPromise = new Promise(function (resolve, reject) {
            if (!root.indexedDB) {
                dbPromise = null;
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
            req.onsuccess = function () {
                dbInstance = req.result;
                try {
                    dbInstance.onclose = function () {
                        dbInstance = null;
                        dbPromise = null;
                    };
                } catch (eOnClose) { /* ignore */ }
                resolve(dbInstance);
            };
            req.onerror = function () {
                dbPromise = null;
                reject(req.error || new Error('idb_open_failed'));
            };
        });
        return dbPromise;
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

