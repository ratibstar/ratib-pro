/**
 * RATEB POS — Offline print queue (browser print; durable via POS IndexedDB meta).
 * Extends existing RatebPosOffline stores — does not add ESC/POS hardware drivers.
 */
(function (root) {
    'use strict';

    var META_KEY = 'offline_print_queue_v1';
    var PRINT_SYNC_TAG = 'rateb-pos-print';
    var messageBound = false;

    function withPosMeta(mode, fn) {
        if (!root.RatebPosOffline || typeof root.RatebPosOffline.withMeta !== 'function') {
            // Fallback: open rateb_pos_offline META store directly if helper missing.
            return openMetaStore(mode, fn);
        }
        return root.RatebPosOffline.withMeta(mode, fn);
    }

    function openMetaStore(mode, fn) {
        return new Promise(function (resolve, reject) {
            if (!root.indexedDB) {
                reject(new Error('indexeddb_unavailable'));
                return;
            }
            var req = root.indexedDB.open('rateb_pos_offline');
            req.onerror = function () { reject(req.error || new Error('idb_open_failed')); };
            req.onsuccess = function () {
                var db = req.result;
                if (!db.objectStoreNames.contains('meta')) {
                    reject(new Error('meta_store_missing'));
                    return;
                }
                var tx = db.transaction('meta', mode);
                var store = tx.objectStore('meta');
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
                    tx.onerror = function () { reject(tx.error); };
                }
            };
        });
    }

    function readQueue() {
        return withPosMeta('readonly', function (store) {
            return new Promise(function (resolve, reject) {
                var req = store.get(META_KEY);
                req.onsuccess = function () {
                    var row = req.result;
                    var items = (row && Array.isArray(row.value)) ? row.value : [];
                    resolve(items);
                };
                req.onerror = function () { reject(req.error); };
            });
        });
    }

    function writeQueue(items) {
        return withPosMeta('readwrite', function (store) {
            return new Promise(function (resolve, reject) {
                var req = store.put({ key: META_KEY, value: items || [], updated_at: Date.now() });
                req.onsuccess = function () { resolve(items || []); };
                req.onerror = function () { reject(req.error); };
            });
        });
    }

    function requestPrintSync() {
        if (!root.navigator || !root.navigator.serviceWorker) {
            return Promise.resolve({ skipped: true });
        }
        return root.navigator.serviceWorker.ready.then(function (reg) {
            if (reg && reg.sync && typeof reg.sync.register === 'function') {
                return reg.sync.register(PRINT_SYNC_TAG).then(function () {
                    return { ok: true };
                }).catch(function () {
                    return { ok: false };
                });
            }
            if (reg && reg.active) {
                try {
                    reg.active.postMessage({ type: 'REGISTER_BACKGROUND_SYNC', tag: PRINT_SYNC_TAG });
                } catch (e) { /* ignore */ }
            }
            return { skipped: true };
        }).catch(function () {
            return { skipped: true };
        });
    }

    function renderAndPrint(job) {
        return new Promise(function (resolve) {
            try {
                var html = (job && job.html) ? String(job.html) : '';
                if (!html && job && job.receipt) {
                    html = '<pre>' + JSON.stringify(job.receipt, null, 2) + '</pre>';
                }
                if (!html) {
                    resolve({ ok: false, error: 'empty_print_payload' });
                    return;
                }
                var w = root.open('', '_blank', 'width=400,height=600');
                if (!w) {
                    resolve({ ok: false, error: 'popup_blocked' });
                    return;
                }
                w.document.write(
                    '<html><head><title>Receipt</title>'
                    + '<style>body{font-family:monospace;padding:12px}</style></head><body>'
                    + html
                    + '</body></html>'
                );
                w.document.close();
                w.focus();
                w.print();
                resolve({ ok: true });
            } catch (e) {
                resolve({ ok: false, error: String(e && e.message ? e.message : e) });
            }
        });
    }

    function enqueue(job) {
        var entry = {
            id: (job && job.id) || ('print-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8)),
            html: (job && job.html) || '',
            receipt: (job && job.receipt) || null,
            created_at: new Date().toISOString(),
            retry_count: 0,
            status: 'pending'
        };
        return readQueue().then(function (items) {
            items.push(entry);
            return writeQueue(items).then(function () {
                return requestPrintSync().then(function () {
                    return entry;
                });
            });
        });
    }

    function flush() {
        return readQueue().then(function (items) {
            if (!items.length) {
                return { printed: 0, remaining: 0 };
            }
            var remaining = [];
            var chain = Promise.resolve({ printed: 0 });
            items.forEach(function (job) {
                chain = chain.then(function (acc) {
                    return renderAndPrint(job).then(function (res) {
                        if (res && res.ok) {
                            acc.printed += 1;
                            return acc;
                        }
                        job.retry_count = (job.retry_count || 0) + 1;
                        job.last_error = (res && res.error) || 'print_failed';
                        if (job.retry_count < 5) {
                            remaining.push(job);
                        }
                        return acc;
                    });
                });
            });
            return chain.then(function (acc) {
                return writeQueue(remaining).then(function () {
                    if (remaining.length) {
                        return requestPrintSync().then(function () {
                            return { printed: acc.printed, remaining: remaining.length };
                        });
                    }
                    return { printed: acc.printed, remaining: 0 };
                });
            });
        });
    }

    function bindMessages() {
        if (messageBound || !root.navigator || !root.navigator.serviceWorker) {
            return;
        }
        messageBound = true;
        root.navigator.serviceWorker.addEventListener('message', function (event) {
            var data = (event && event.data) || {};
            if (data.type === 'RATEB_POS_PRINT_FLUSH') {
                flush().catch(function () { /* ignore */ });
            }
        });
    }

    root.RatebPosOfflinePrint = {
        enqueue: enqueue,
        flush: flush,
        depth: function () {
            return readQueue().then(function (items) { return items.length; });
        },
        bind: bindMessages
    };

    bindMessages();
})(typeof window !== 'undefined' ? window : globalThis);
