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
