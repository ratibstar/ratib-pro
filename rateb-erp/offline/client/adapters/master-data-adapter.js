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

