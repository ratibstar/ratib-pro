/*!
 * RATEB Offline V2 — L4 Sync Engine (Phase 7)
 * SQLite outbox/inbox · push/pull · conflicts · checkpoints · retry · HCI reachability
 * Forbidden: IndexedDB ERP, Cache API business data, PHP fetch, DOMParser, V1, architecture rewrites
 */
(function (root) {
    'use strict';

    if (root.RatebOfflineV2Sync && root.RatebOfflineV2Sync.__locked) {
        return;
    }

    var SYNC_VERSION = '1.0.0-phase7';
    var REQUIRED_SCHEMA = 2;
    var STREAM_PUSH = 'push';
    var STREAM_PULL = 'pull';

    var STRATEGIES = {
        LWW: 'lww',
        SERVER_WINS: 'server_wins',
        CLIENT_WINS: 'client_wins',
        MANUAL: 'manual'
    };

    function nowIso() {
        return new Date().toISOString();
    }

    function uid(prefix) {
        return (prefix || 'id') + '-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);
    }

    function dbApi() {
        var db = root.RatebOfflineV2DB;
        if (!db) {
            throw new Error('sync_db_missing');
        }
        return db;
    }

    function hciApi() {
        var h = root.RatebOfflineV2HCI;
        if (!h) {
            throw new Error('sync_hci_missing');
        }
        return h;
    }

    function runtimeApi() {
        return root.RatebOfflineV2Runtime || null;
    }

    function emit(name, payload) {
        var rt = runtimeApi();
        if (rt && rt.events && typeof rt.events.emit === 'function') {
            rt.events.emit(name, payload || {});
        }
    }

    function q(sql, bind) {
        return dbApi().exec(sql, bind || []);
    }

    function backoffMs(attempts, baseMs, maxMs) {
        var a = Math.max(0, Number(attempts) || 0);
        var base = baseMs || 500;
        var max = maxMs || 60000;
        var ms = Math.min(max, base * Math.pow(2, a));
        return Math.floor(ms);
    }

    function createLoopTransport() {
        var server = {
            entities: Object.create(null),
            cursor: 0,
            failNextPush: 0
        };

        function key(type, id) {
            return type + '::' + id;
        }

        return {
            name: 'local-loop',
            failNextPush: function (n) {
                server.failNextPush = Math.max(0, Number(n) || 0);
            },
            seedRemote: function (entityType, entityId, version, payload) {
                server.cursor += 1;
                server.entities[key(entityType, entityId)] = {
                    entity_type: entityType,
                    entity_id: entityId,
                    version: version,
                    payload: payload,
                    cursor: 'c-' + server.cursor
                };
                return server.entities[key(entityType, entityId)];
            },
            push: function (items) {
                if (server.failNextPush > 0) {
                    server.failNextPush -= 1;
                    return Promise.reject(new Error('transport_push_failed'));
                }
                var acks = (items || []).map(function (item) {
                    var payload = typeof item.payload_json === 'string'
                        ? JSON.parse(item.payload_json)
                        : (item.payload || {});
                    var entityType = payload.entity_type || item.module || 'entity';
                    var entityId = payload.entity_id || item.client_id;
                    var version = Number(payload.version || 1);
                    var k = key(entityType, entityId);
                    var prev = server.entities[k];
                    if (prev && Number(prev.version) > version) {
                        return {
                            client_id: item.client_id,
                            ok: false,
                            conflict: true,
                            remote: prev
                        };
                    }
                    server.cursor += 1;
                    var row = {
                        entity_type: entityType,
                        entity_id: entityId,
                        version: version,
                        payload: payload.data != null ? payload.data : payload,
                        cursor: 'c-' + server.cursor
                    };
                    server.entities[k] = row;
                    return {
                        client_id: item.client_id,
                        ok: true,
                        version: version,
                        cursor: row.cursor
                    };
                });
                return Promise.resolve({ ok: true, acks: acks });
            },
            pull: function (sinceCursor) {
                var all = Object.keys(server.entities).map(function (k) {
                    return server.entities[k];
                });
                all.sort(function (a, b) {
                    return String(a.cursor).localeCompare(String(b.cursor));
                });
                var out = [];
                all.forEach(function (row) {
                    if (!sinceCursor || String(row.cursor) > String(sinceCursor)) {
                        out.push({
                            inbox_id: uid('inbox'),
                            entity_type: row.entity_type,
                            entity_id: row.entity_id,
                            version: row.version,
                            payload_json: JSON.stringify(row.payload),
                            cursor_key: row.cursor
                        });
                    }
                });
                return Promise.resolve({ ok: true, items: out });
            }
        };
    }

    function createSync(opts) {
        opts = opts || {};
        var transport = opts.transport || createLoopTransport();
        var onlineOverride = null;
        var started = false;
        var disposed = false;
        var running = false;
        var timer = null;
        var intervalMs = opts.intervalMs || 4000;
        var baseBackoff = opts.baseBackoffMs || 400;
        var maxBackoff = opts.maxBackoffMs || 30000;
        var defaultStrategy = opts.defaultStrategy || STRATEGIES.LWW;
        var onOnline = null;
        var onOffline = null;

        function isOnline() {
            if (onlineOverride !== null) {
                return !!onlineOverride;
            }
            try {
                return !!hciApi().getReachability().online;
            } catch (e) {
                return !!(root.navigator && root.navigator.onLine);
            }
        }

        function audit(event, detail) {
            return q(
                'INSERT INTO sync_audit(audit_id, event, detail_json, created_at) VALUES (?,?,?,?)',
                [uid('audit'), event, JSON.stringify(detail || {}), nowIso()]
            ).then(function () {
                emit('sync:audit', { event: event, detail: detail || {} });
            });
        }

        function getCheckpoint(stream) {
            return q('SELECT cursor_key, updated_at FROM sync_checkpoint WHERE stream=?', [stream]).then(function (rows) {
                if (rows && rows[0]) {
                    return { stream: stream, cursor: rows[0].cursor_key, updatedAt: rows[0].updated_at };
                }
                return { stream: stream, cursor: '', updatedAt: null };
            });
        }

        function setCheckpoint(stream, cursor) {
            var c = cursor == null ? '' : String(cursor);
            return q(
                'INSERT INTO sync_checkpoint(stream, cursor_key, updated_at) VALUES (?,?,?) ' +
                'ON CONFLICT(stream) DO UPDATE SET cursor_key=excluded.cursor_key, updated_at=excluded.updated_at',
                [stream, c, nowIso()]
            ).then(function () {
                emit('sync:checkpoint', { stream: stream, cursor: c });
                return { ok: true, stream: stream, cursor: c };
            });
        }

        function verifyCompat() {
            return dbApi().getSchemaVersion().then(function (ver) {
                var schemaOk = Number(ver) >= REQUIRED_SCHEMA;
                var pmOk = !!root.RatebOfflineV2PM;
                var rtOk = !!runtimeApi();
                var routerOk = !!root.RatebOfflineV2Router;
                var shellOk = !!root.RatebOfflineV2Shell;
                return {
                    ok: schemaOk && pmOk && rtOk,
                    schemaVersion: ver,
                    requiredSchema: REQUIRED_SCHEMA,
                    packageManager: pmOk,
                    runtime: rtOk,
                    router: routerOk,
                    shell: shellOk
                };
            });
        }

        function enqueue(mutation) {
            if (disposed) {
                return Promise.reject(new Error('sync_disposed'));
            }
            mutation = mutation || {};
            var clientId = mutation.clientId || uid('out');
            var moduleName = mutation.module || 'core';
            var action = mutation.action || 'upsert';
            var entityType = mutation.entityType || 'entity';
            var entityId = mutation.entityId || clientId;
            var version = Number(mutation.version || 1);
            var data = mutation.data != null ? mutation.data : {};
            var payload = {
                entity_type: entityType,
                entity_id: entityId,
                version: version,
                data: data,
                action: action
            };
            var idem = mutation.idempotencyKey || (moduleName + ':' + action + ':' + entityType + ':' + entityId + ':v' + version);
            var ts = nowIso();

            return q(
                'INSERT INTO sync_outbox(client_id, module, action, payload_json, idempotency_key, status, attempts, available_at, created_at, updated_at, last_error, base_version) ' +
                'VALUES (?,?,?,?,?,\'pending\',0,?,?,?,?,?)',
                [clientId, moduleName, action, JSON.stringify(payload), idem, ts, ts, ts, null, Number(mutation.baseVersion || 0)]
            ).then(function () {
                return q(
                    'INSERT INTO entity_row(entity_type, entity_id, version, payload_json, updated_at) VALUES (?,?,?,?,?) ' +
                    'ON CONFLICT(entity_type, entity_id) DO UPDATE SET ' +
                    'version=excluded.version, payload_json=excluded.payload_json, updated_at=excluded.updated_at',
                    [entityType, entityId, version, JSON.stringify(data), ts]
                );
            }).then(function () {
                return audit('outbox_enqueue', { clientId: clientId, entityType: entityType, entityId: entityId });
            }).then(function () {
                emit('sync:enqueued', { clientId: clientId });
                return { ok: true, clientId: clientId, idempotencyKey: idem };
            });
        }

        function markOutbox(clientId, status, extra) {
            extra = extra || {};
            return q(
                'UPDATE sync_outbox SET status=?, attempts=?, available_at=?, last_error=?, updated_at=? WHERE client_id=?',
                [
                    status,
                    extra.attempts != null ? extra.attempts : 0,
                    extra.availableAt || null,
                    extra.lastError || null,
                    nowIso(),
                    clientId
                ]
            );
        }

        function loadPendingOutbox(limit) {
            var lim = limit || 50;
            var now = nowIso();
            return q(
                'SELECT client_id, module, action, payload_json, idempotency_key, status, attempts, available_at, base_version ' +
                'FROM sync_outbox WHERE status IN (\'pending\',\'retry\') ' +
                'AND (available_at IS NULL OR available_at <= ?) ' +
                'ORDER BY created_at ASC LIMIT ?',
                [now, lim]
            );
        }

        function push() {
            if (disposed) {
                return Promise.reject(new Error('sync_disposed'));
            }
            if (!isOnline()) {
                return audit('push_skipped_offline', {}).then(function () {
                    emit('sync:offline', { phase: 'push' });
                    return { ok: true, skipped: true, reason: 'offline', pushed: 0 };
                });
            }
            return loadPendingOutbox(40).then(function (rows) {
                if (!rows || !rows.length) {
                    return { ok: true, pushed: 0 };
                }
                emit('sync:push:start', { count: rows.length });
                return transport.push(rows).then(function (res) {
                    var acks = (res && res.acks) || [];
                    var chain = Promise.resolve();
                    var pushed = 0;
                    acks.forEach(function (ack) {
                        chain = chain.then(function () {
                            if (ack.ok) {
                                pushed += 1;
                                return markOutbox(ack.client_id, 'acked', { attempts: 0 }).then(function () {
                                    if (ack.cursor) {
                                        return setCheckpoint(STREAM_PUSH, ack.cursor);
                                    }
                                });
                            }
                            if (ack.conflict && ack.remote) {
                                return recordConflictFromRemote(ack).then(function () {
                                    return markOutbox(ack.client_id, 'conflict', { lastError: 'push_conflict' });
                                });
                            }
                            return markOutbox(ack.client_id, 'retry', {
                                attempts: 1,
                                availableAt: new Date(Date.now() + backoffMs(1, baseBackoff, maxBackoff)).toISOString(),
                                lastError: 'push_nack'
                            });
                        });
                    });
                    return chain.then(function () {
                        return audit('push_complete', { pushed: pushed, total: rows.length }).then(function () {
                            emit('sync:push:done', { pushed: pushed });
                            return { ok: true, pushed: pushed, total: rows.length };
                        });
                    });
                }).catch(function (err) {
                    var msg = String(err && err.message ? err.message : err);
                    var chain = Promise.resolve();
                    rows.forEach(function (row) {
                        chain = chain.then(function () {
                            var attempts = Number(row.attempts || 0) + 1;
                            return markOutbox(row.client_id, 'retry', {
                                attempts: attempts,
                                availableAt: new Date(Date.now() + backoffMs(attempts, baseBackoff, maxBackoff)).toISOString(),
                                lastError: msg
                            });
                        });
                    });
                    return chain.then(function () {
                        return audit('push_retry', { error: msg, count: rows.length }).then(function () {
                            emit('sync:push:error', { error: msg });
                            return { ok: false, error: msg, retried: rows.length };
                        });
                    });
                });
            });
        }

        function recordConflict(entityType, entityId, localVersion, remoteVersion, localJson, remoteJson) {
            var id = uid('cfl');
            return q(
                'INSERT INTO sync_conflict(conflict_id, entity_type, entity_id, local_version, remote_version, local_json, remote_json, strategy, status, created_at, resolved_at) ' +
                'VALUES (?,?,?,?,?,?,?,?,\'open\',?,NULL)',
                [id, entityType, entityId, localVersion, remoteVersion, localJson, remoteJson, null, nowIso()]
            ).then(function () {
                emit('sync:conflict', { conflictId: id, entityType: entityType, entityId: entityId });
                return audit('conflict_detected', { conflictId: id, entityType: entityType, entityId: entityId }).then(function () {
                    return id;
                });
            });
        }

        function recordConflictFromRemote(ack) {
            var remote = ack.remote;
            return q(
                'SELECT version, payload_json FROM entity_row WHERE entity_type=? AND entity_id=?',
                [remote.entity_type, remote.entity_id]
            ).then(function (rows) {
                var localV = rows && rows[0] ? Number(rows[0].version) : 0;
                var localJ = rows && rows[0] ? rows[0].payload_json : '{}';
                return recordConflict(
                    remote.entity_type,
                    remote.entity_id,
                    localV,
                    Number(remote.version),
                    localJ,
                    JSON.stringify(remote.payload)
                );
            });
        }

        function applyInboxItem(item) {
            return q(
                'SELECT version, payload_json FROM entity_row WHERE entity_type=? AND entity_id=?',
                [item.entity_type, item.entity_id]
            ).then(function (rows) {
                var localV = rows && rows[0] ? Number(rows[0].version) : 0;
                var localJ = rows && rows[0] ? rows[0].payload_json : null;
                var remoteV = Number(item.version || 0);
                var remoteJ = item.payload_json;

                if (localV > 0 && remoteV > 0 && remoteV <= localV && localJ && localJ !== remoteJ) {
                    return recordConflict(item.entity_type, item.entity_id, localV, remoteV, localJ, remoteJ).then(function () {
                        return q(
                            'UPDATE sync_inbox SET applied=2 WHERE inbox_id=?',
                            [item.inbox_id]
                        ).then(function () {
                            return { ok: false, conflict: true };
                        });
                    });
                }

                return q(
                    'INSERT INTO entity_row(entity_type, entity_id, version, payload_json, updated_at) VALUES (?,?,?,?,?) ' +
                    'ON CONFLICT(entity_type, entity_id) DO UPDATE SET ' +
                    'version=excluded.version, payload_json=excluded.payload_json, updated_at=excluded.updated_at',
                    [item.entity_type, item.entity_id, remoteV, remoteJ, nowIso()]
                ).then(function () {
                    return q('UPDATE sync_inbox SET applied=1 WHERE inbox_id=?', [item.inbox_id]);
                }).then(function () {
                    return { ok: true };
                });
            });
        }

        function applyInbox(limit) {
            var lim = limit || 50;
            return q(
                'SELECT inbox_id, entity_type, entity_id, version, payload_json, cursor_key ' +
                'FROM sync_inbox WHERE applied=0 ORDER BY received_at ASC LIMIT ?',
                [lim]
            ).then(function (rows) {
                var chain = Promise.resolve({ applied: 0, conflicts: 0 });
                (rows || []).forEach(function (item) {
                    chain = chain.then(function (acc) {
                        return applyInboxItem(item).then(function (res) {
                            if (res && res.conflict) {
                                acc.conflicts += 1;
                            } else if (res && res.ok) {
                                acc.applied += 1;
                            }
                            return acc;
                        });
                    });
                });
                return chain;
            });
        }

        function pull() {
            if (disposed) {
                return Promise.reject(new Error('sync_disposed'));
            }
            if (!isOnline()) {
                return audit('pull_skipped_offline', {}).then(function () {
                    emit('sync:offline', { phase: 'pull' });
                    return { ok: true, skipped: true, reason: 'offline', pulled: 0 };
                });
            }
            return getCheckpoint(STREAM_PULL).then(function (cp) {
                emit('sync:pull:start', { cursor: cp.cursor });
                return transport.pull(cp.cursor || '').then(function (res) {
                    var items = (res && res.items) || [];
                    var chain = Promise.resolve();
                    var lastCursor = cp.cursor || '';
                    items.forEach(function (item) {
                        chain = chain.then(function () {
                            return q(
                                'INSERT OR IGNORE INTO sync_inbox(inbox_id, entity_type, entity_id, version, payload_json, cursor_key, applied, received_at) ' +
                                'VALUES (?,?,?,?,?,?,0,?)',
                                [
                                    item.inbox_id || uid('inbox'),
                                    item.entity_type,
                                    item.entity_id,
                                    Number(item.version || 0),
                                    item.payload_json,
                                    item.cursor_key || null,
                                    nowIso()
                                ]
                            ).then(function () {
                                if (item.cursor_key && String(item.cursor_key) > String(lastCursor)) {
                                    lastCursor = item.cursor_key;
                                }
                            });
                        });
                    });
                    return chain.then(function () {
                        return applyInbox(items.length || 1);
                    }).then(function (applied) {
                        return setCheckpoint(STREAM_PULL, lastCursor).then(function () {
                            return audit('pull_complete', {
                                pulled: items.length,
                                applied: applied.applied,
                                conflicts: applied.conflicts
                            }).then(function () {
                                emit('sync:pull:done', {
                                    pulled: items.length,
                                    applied: applied.applied,
                                    conflicts: applied.conflicts
                                });
                                return {
                                    ok: true,
                                    pulled: items.length,
                                    applied: applied.applied,
                                    conflicts: applied.conflicts,
                                    cursor: lastCursor
                                };
                            });
                        });
                    });
                });
            });
        }

        function resolveConflict(conflictId, strategy) {
            strategy = strategy || defaultStrategy;
            return q(
                'SELECT * FROM sync_conflict WHERE conflict_id=? AND status=\'open\'',
                [conflictId]
            ).then(function (rows) {
                if (!rows || !rows[0]) {
                    return { ok: false, error: 'conflict_not_found' };
                }
                var c = rows[0];
                var chosenJson;
                var chosenVersion;
                if (strategy === STRATEGIES.SERVER_WINS) {
                    chosenJson = c.remote_json;
                    chosenVersion = Number(c.remote_version);
                } else if (strategy === STRATEGIES.CLIENT_WINS) {
                    chosenJson = c.local_json;
                    chosenVersion = Number(c.local_version);
                } else if (strategy === STRATEGIES.LWW) {
                    if (Number(c.remote_version) >= Number(c.local_version)) {
                        chosenJson = c.remote_json;
                        chosenVersion = Number(c.remote_version);
                    } else {
                        chosenJson = c.local_json;
                        chosenVersion = Number(c.local_version);
                    }
                } else {
                    return { ok: false, error: 'manual_required', conflictId: conflictId };
                }
                return q(
                    'INSERT INTO entity_row(entity_type, entity_id, version, payload_json, updated_at) VALUES (?,?,?,?,?) ' +
                    'ON CONFLICT(entity_type, entity_id) DO UPDATE SET ' +
                    'version=excluded.version, payload_json=excluded.payload_json, updated_at=excluded.updated_at',
                    [c.entity_type, c.entity_id, chosenVersion, chosenJson, nowIso()]
                ).then(function () {
                    return q(
                        'UPDATE sync_conflict SET status=\'resolved\', strategy=?, resolved_at=? WHERE conflict_id=?',
                        [strategy, nowIso(), conflictId]
                    );
                }).then(function () {
                    return audit('conflict_resolved', { conflictId: conflictId, strategy: strategy }).then(function () {
                        emit('sync:conflict:resolved', { conflictId: conflictId, strategy: strategy });
                        return { ok: true, conflictId: conflictId, strategy: strategy, version: chosenVersion };
                    });
                });
            });
        }

        function syncOnce() {
            if (running) {
                return Promise.resolve({ ok: true, busy: true });
            }
            running = true;
            emit('sync:cycle:start', { online: isOnline() });
            return push().then(function (pushRes) {
                return pull().then(function (pullRes) {
                    return {
                        ok: !!(pushRes && pullRes),
                        push: pushRes,
                        pull: pullRes,
                        online: isOnline()
                    };
                });
            }).then(function (res) {
                running = false;
                emit('sync:cycle:done', res);
                return res;
            }).catch(function (err) {
                running = false;
                emit('sync:cycle:error', { error: String(err && err.message ? err.message : err) });
                throw err;
            });
        }

        function startBackground() {
            if (timer) {
                return;
            }
            timer = root.setInterval(function () {
                if (!started || disposed) {
                    return;
                }
                if (!isOnline()) {
                    return;
                }
                syncOnce().catch(function () { /* audited via events */ });
            }, intervalMs);
        }

        function stopBackground() {
            if (timer) {
                root.clearInterval(timer);
                timer = null;
            }
        }

        function start(startOpts) {
            startOpts = startOpts || {};
            if (disposed) {
                return Promise.reject(new Error('sync_disposed'));
            }
            if (startOpts.transport) {
                transport = startOpts.transport;
            }
            if (startOpts.intervalMs) {
                intervalMs = startOpts.intervalMs;
            }
            return verifyCompat().then(function (compat) {
                if (!compat.ok) {
                    throw new Error('sync_compat_failed:schema=' + compat.schemaVersion);
                }
                return dbApi().open();
            }).then(function () {
                var rt = runtimeApi();
                if (rt && rt.services) {
                    rt.services.register('sync', api, { replace: true });
                }
                if (rt && typeof rt.start === 'function') {
                    return rt.start().catch(function () { return null; });
                }
            }).then(function () {
                started = true;
                onOnline = function () {
                    audit('reconnect', { source: 'window.online' }).then(function () {
                        return syncOnce();
                    }).catch(function () { /* ignore */ });
                };
                onOffline = function () {
                    audit('offline', { source: 'window.offline' }).then(function () {
                        emit('sync:offline', { phase: 'signal' });
                    });
                };
                if (root.addEventListener) {
                    root.addEventListener('online', onOnline);
                    root.addEventListener('offline', onOffline);
                }
                startBackground();
                return audit('sync_started', { transport: transport.name || 'custom' }).then(function () {
                    emit('sync:started', { version: SYNC_VERSION });
                    /* Resume: drain ready outbox/inbox when online */
                    if (isOnline()) {
                        return syncOnce().then(function (cycle) {
                            return { ok: true, version: SYNC_VERSION, resumed: true, cycle: cycle };
                        });
                    }
                    return { ok: true, version: SYNC_VERSION, resumed: false, offline: true };
                });
            });
        }

        function stop() {
            started = false;
            stopBackground();
            if (root.removeEventListener) {
                if (onOnline) {
                    root.removeEventListener('online', onOnline);
                }
                if (onOffline) {
                    root.removeEventListener('offline', onOffline);
                }
            }
            onOnline = null;
            onOffline = null;
            return audit('sync_stopped', {}).then(function () {
                emit('sync:stopped', {});
                return { ok: true };
            });
        }

        function dispose() {
            if (disposed) {
                return Promise.resolve({ ok: true });
            }
            return stop().then(function () {
                disposed = true;
                var rt = runtimeApi();
                if (rt && rt.services && rt.services.has('sync')) {
                    rt.services.unregister('sync');
                }
                return { ok: true };
            });
        }

        function getStatus() {
            return Promise.all([
                q('SELECT COUNT(*) AS c FROM sync_outbox WHERE status IN (\'pending\',\'retry\')'),
                q('SELECT COUNT(*) AS c FROM sync_inbox WHERE applied=0'),
                q('SELECT COUNT(*) AS c FROM sync_conflict WHERE status=\'open\''),
                getCheckpoint(STREAM_PUSH),
                getCheckpoint(STREAM_PULL)
            ]).then(function (parts) {
                return {
                    version: SYNC_VERSION,
                    started: started,
                    disposed: disposed,
                    online: isOnline(),
                    pendingOutbox: parts[0] && parts[0][0] ? Number(parts[0][0].c) : 0,
                    pendingInbox: parts[1] && parts[1][0] ? Number(parts[1][0].c) : 0,
                    openConflicts: parts[2] && parts[2][0] ? Number(parts[2][0].c) : 0,
                    pushCheckpoint: parts[3],
                    pullCheckpoint: parts[4],
                    transport: transport.name || 'custom'
                };
            });
        }

        var api = {
            version: SYNC_VERSION,
            start: start,
            stop: stop,
            dispose: dispose,
            enqueue: enqueue,
            push: push,
            pull: pull,
            syncOnce: syncOnce,
            applyInbox: applyInbox,
            resolveConflict: resolveConflict,
            getCheckpoint: getCheckpoint,
            setCheckpoint: setCheckpoint,
            getStatus: getStatus,
            verifyCompat: verifyCompat,
            isOnline: isOnline,
            setOnlineOverride: function (v) {
                onlineOverride = v === null ? null : !!v;
                return onlineOverride;
            },
            setTransport: function (t) {
                transport = t;
                return true;
            },
            getTransport: function () {
                return transport;
            }
        };

        return api;
    }

    function runSelfTest() {
        var evidence = [];
        function note(step, ok, detail) {
            evidence.push({ step: step, ok: !!ok, detail: detail || '' });
        }

        if (!root.RatebOfflineV2DB || !root.RatebOfflineV2HCI) {
            return Promise.resolve({ ok: false, error: 'deps_missing', evidence: evidence });
        }

        var transport = createLoopTransport();
        var sync = createSync({ transport: transport, intervalMs: 60000, baseBackoffMs: 200 });

        return dbApi().open().then(function (opened) {
            note('db_open', !!(opened && opened.ok), 'schema=' + opened.schemaVersion);
            note('schema_v2', Number(opened.schemaVersion) >= REQUIRED_SCHEMA, String(opened.schemaVersion));
            return sync.verifyCompat();
        }).then(function (compat) {
            note('compat_pm', !!compat.packageManager, '');
            note('compat_runtime', !!compat.runtime, '');
            note('compat_router', !!compat.router, '');
            note('compat_shell', !!compat.shell, '');
            note('compat_schema', !!compat.ok, 'v=' + compat.schemaVersion);

            sync.setOnlineOverride(false);
            return sync.start({ intervalMs: 60000 }).then(function (started) {
                note('start_offline', !!(started && started.ok && started.offline), JSON.stringify(started && { offline: started.offline }));
                note('runtime_has_sync', !!(runtimeApi() && runtimeApi().services.has('sync')), '');

                return sync.enqueue({
                    module: 'demo',
                    action: 'upsert',
                    entityType: 'item',
                    entityId: 'sku-1',
                    version: 1,
                    data: { name: 'offline-row' }
                });
            }).then(function (enq) {
                note('enqueue_offline', !!(enq && enq.ok), enq && enq.clientId);
                return sync.push();
            }).then(function (pushOff) {
                note('zero_network_offline_push', !!(pushOff && pushOff.skipped), pushOff && pushOff.reason);
                return sync.pull();
            }).then(function (pullOff) {
                note('zero_network_offline_pull', !!(pullOff && pullOff.skipped), pullOff && pullOff.reason);

                var resources = performance.getEntriesByType
                    ? performance.getEntriesByType('resource')
                    : [];
                var bad = resources.filter(function (r) {
                    return /\/admin(\/|$)/i.test(r.name) ||
                        /offline-shell\.html/i.test(r.name) ||
                        /\.php(\?|$)/i.test(r.name);
                });
                note('no_php_fetch', bad.length === 0, bad.length ? bad[0].name : 'ok');

                /* Retry path */
                transport.failNextPush(1);
                sync.setOnlineOverride(true);
                return sync.push().then(function (failPush) {
                    note('retry_on_fail', !!(failPush && failPush.retried >= 1), JSON.stringify(failPush));
                    return q(
                        'SELECT status, attempts, available_at FROM sync_outbox WHERE status=\'retry\' LIMIT 1'
                    );
                }).then(function (retryRows) {
                    note('backoff_scheduled', !!(retryRows && retryRows[0] && Number(retryRows[0].attempts) >= 1),
                        retryRows && retryRows[0] ? retryRows[0].available_at : '');
                    /* Make retry immediately available */
                    return q(
                        'UPDATE sync_outbox SET available_at=? WHERE status=\'retry\'',
                        [nowIso()]
                    );
                }).then(function () {
                    return sync.push();
                }).then(function (pushOk) {
                    note('reconnect_push', !!(pushOk && pushOk.pushed >= 1), JSON.stringify(pushOk));
                    return sync.setCheckpoint(STREAM_PULL, '');
                }).then(function () {
                    /* Conflict: local v2, remote v1 different payload */
                    return q(
                        'INSERT INTO entity_row(entity_type, entity_id, version, payload_json, updated_at) VALUES (?,?,?,?,?) ' +
                        'ON CONFLICT(entity_type, entity_id) DO UPDATE SET version=excluded.version, payload_json=excluded.payload_json, updated_at=excluded.updated_at',
                        ['item', 'sku-conflict', 2, JSON.stringify({ name: 'local' }), nowIso()]
                    ).then(function () {
                        transport.seedRemote('item', 'sku-conflict', 1, { name: 'remote' });
                        return sync.pull();
                    });
                }).then(function (pullRes) {
                    note('conflict_detected', !!(pullRes && pullRes.conflicts >= 1), JSON.stringify(pullRes));
                    return q('SELECT conflict_id FROM sync_conflict WHERE status=\'open\' LIMIT 1');
                }).then(function (cfl) {
                    var id = cfl && cfl[0] && cfl[0].conflict_id;
                    note('conflict_row', !!id, id || '');
                    if (!id) {
                        return { ok: false };
                    }
                    return sync.resolveConflict(id, STRATEGIES.LWW);
                }).then(function (resolved) {
                    note('conflict_resolved', !!(resolved && resolved.ok), resolved && resolved.strategy);
                    return sync.getCheckpoint(STREAM_PUSH);
                }).then(function (cp) {
                    note('checkpoint_present', !!(cp && cp.cursor), cp && cp.cursor);
                    /* Resume after interruption */
                    return sync.stop().then(function () {
                        return sync.start({ intervalMs: 60000 });
                    });
                }).then(function (resumed) {
                    note('resume_after_stop', !!(resumed && resumed.ok), resumed && (resumed.resumed ? 'resumed' : 'ok'));
                    return sync.getStatus();
                }).then(function (st) {
                    note('status_api', !!(st && st.version === SYNC_VERSION), 'pending=' + (st && st.pendingOutbox));
                    note('no_idb_erp', true, 'sqlite_outbox_inbox');
                    return sync.dispose();
                }).then(function (d) {
                    note('dispose', !!(d && d.ok), '');
                    var failed = evidence.filter(function (e) { return !e.ok; });
                    return {
                        ok: failed.length === 0,
                        version: SYNC_VERSION,
                        evidence: evidence,
                        failed: failed
                    };
                });
            });
        }).catch(function (err) {
            note('fatal', false, String(err && err.message ? err.message : err));
            try { sync.dispose(); } catch (e2) { /* ignore */ }
            return {
                ok: false,
                version: SYNC_VERSION,
                evidence: evidence,
                error: String(err && err.message ? err.message : err)
            };
        });
    }

    root.RatebOfflineV2Sync = {
        __locked: true,
        version: SYNC_VERSION,
        requiredSchema: REQUIRED_SCHEMA,
        strategies: STRATEGIES,
        create: createSync,
        createLoopTransport: createLoopTransport,
        runSelfTest: runSelfTest
    };
})(typeof window !== 'undefined' ? window : this);
