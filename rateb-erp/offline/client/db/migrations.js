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
        remove: remove
    };
})(typeof window !== 'undefined' ? window : globalThis);
