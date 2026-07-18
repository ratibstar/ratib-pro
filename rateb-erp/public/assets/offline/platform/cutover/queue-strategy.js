/*!
 * RATEB Platform Cutover Prep — Queue Strategy (B1-Prep G4)
 * drain-first OR idempotent mapper. Replay/rollback safe. Zero silent loss.
 */
(function (root) {
    'use strict';

    if (root.RatebPlatformQueueStrategy && root.RatebPlatformQueueStrategy.__locked) {
        return;
    }

    function nowIso() {
        return new Date().toISOString();
    }

    function drainFirst(opts) {
        opts = opts || {};
        var readPending = opts.readV1Pending;
        var maxWaitMs = Number(opts.maxWaitMs || 0);
        var pollMs = Number(opts.pollMs || 50);
        if (typeof readPending !== 'function') {
            return Promise.reject(new Error('queue_drain_reader_required'));
        }
        var started = Date.now();
        function tick() {
            return Promise.resolve(readPending()).then(function (n) {
                var pending = Number(n || 0);
                if (pending <= 0) {
                    return {
                        ok: true,
                        strategy: 'drain-first',
                        pending: 0,
                        migrated: 0,
                        at: nowIso()
                    };
                }
                if (maxWaitMs > 0 && (Date.now() - started) >= maxWaitMs) {
                    return {
                        ok: false,
                        strategy: 'drain-first',
                        pending: pending,
                        reason: 'drain_timeout',
                        at: nowIso()
                    };
                }
                if (maxWaitMs <= 0) {
                    return {
                        ok: false,
                        strategy: 'drain-first',
                        pending: pending,
                        reason: 'queue_not_empty',
                        at: nowIso()
                    };
                }
                return new Promise(function (resolve) {
                    root.setTimeout(function () {
                        resolve(tick());
                    }, pollMs);
                });
            });
        }
        return tick();
    }

    function mapRecord(rec) {
        var r = rec || {};
        var clientId = String(r.client_id || r.id || r.idempotency_key || '');
        var idem = String(r.idempotency_key || clientId);
        if (!idem) {
            throw new Error('queue_map_missing_idempotency');
        }
        return {
            clientId: clientId || idem,
            module: String(r.module || r.entity_type || 'legacy_v1'),
            action: String(r.action || r.op || 'upsert'),
            entityType: String(r.entity_type || r.module || 'legacy_v1'),
            entityId: String(r.entity_id || r.record_id || idem),
            payload: r.payload || r.body || r,
            idempotencyKey: idem,
            baseVersion: Number(r.base_version || r.version || 0)
        };
    }

    /**
     * Idempotent mapper: each V1 row maps once by idempotency_key.
     * opts.readV1Records() → array
     * opts.enqueuePlatform(mapped) → Promise
     * opts.markV1Migrated(id) optional
     * opts.seenStore — Set or {has,add} for idempotency across retries
     */
    function idempotentMapper(opts) {
        opts = opts || {};
        if (typeof opts.readV1Records !== 'function' || typeof opts.enqueuePlatform !== 'function') {
            return Promise.reject(new Error('queue_mapper_deps_required'));
        }
        var seen = opts.seenStore || new Set();
        function has(key) {
            return typeof seen.has === 'function' ? seen.has(key) : !!seen[key];
        }
        function add(key) {
            if (typeof seen.add === 'function') {
                seen.add(key);
            } else {
                seen[key] = true;
            }
        }

        return Promise.resolve(opts.readV1Records()).then(function (rows) {
            var list = Array.isArray(rows) ? rows : [];
            var migrated = [];
            var skipped = [];
            var errors = [];
            var chain = Promise.resolve();
            list.forEach(function (row) {
                chain = chain.then(function () {
                    var mapped;
                    try {
                        mapped = mapRecord(row);
                    } catch (eMap) {
                        errors.push({ row: row, error: String(eMap && eMap.message ? eMap.message : eMap) });
                        return null;
                    }
                    if (has(mapped.idempotencyKey)) {
                        skipped.push(mapped.idempotencyKey);
                        return null;
                    }
                    return Promise.resolve(opts.enqueuePlatform(mapped)).then(function (res) {
                        if (res && res.ok === false) {
                            errors.push({ key: mapped.idempotencyKey, error: res.error || 'enqueue_failed' });
                            return null;
                        }
                        add(mapped.idempotencyKey);
                        migrated.push(mapped.idempotencyKey);
                        if (typeof opts.markV1Migrated === 'function') {
                            return opts.markV1Migrated(row, mapped);
                        }
                        return null;
                    });
                });
            });
            return chain.then(function () {
                return {
                    ok: errors.length === 0,
                    strategy: 'idempotent-mapper',
                    total: list.length,
                    migrated: migrated.length,
                    skipped: skipped.length,
                    errors: errors,
                    pending: Math.max(0, list.length - migrated.length - skipped.length),
                    at: nowIso()
                };
            });
        });
    }

    function run(opts) {
        opts = opts || {};
        var strategy = String(opts.strategy || 'drain-first');
        if (strategy === 'idempotent-mapper') {
            return idempotentMapper(opts);
        }
        return drainFirst(opts);
    }

    root.RatebPlatformQueueStrategy = {
        __locked: true,
        version: '1.0.0-b1-prep',
        drainFirst: drainFirst,
        idempotentMapper: idempotentMapper,
        mapRecord: mapRecord,
        run: run
    };
})(typeof window !== 'undefined' ? window : this);
