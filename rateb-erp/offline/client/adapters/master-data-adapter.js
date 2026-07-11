/**
 * RATEB Offline — Master-data delta adapter (Phase 13).
 * Client-owned cursors in IndexedDB `cursors`; rows in `entity_cache`.
 * Read-only — never enqueues writes or conflicts.
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

    function cursorKey(entity, scope) {
        scope = scope || tenantScope();
        return 'md:' + scope.company_id + ':' + (scope.branch_id || 0) + ':' + entity;
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
                var id = prefix + ':' + item.id;
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

        function next(cursor) {
            return pull.pull(entity, {
                apiBase: apiBase,
                cursor: cursor || undefined,
                branch_id: scope.branch_id || undefined
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
                        if (delta.has_more && items.length > 0 && pages < 50) {
                            return next(token);
                        }
                        return {
                            ok: true,
                            entity: entity,
                            pages: pages,
                            total: total,
                            cursor_token: token,
                            has_more: !!delta.has_more
                        };
                    });
                });
            });
        }

        return readClientCursor(entity, scope).then(function (stored) {
            // Client-owned cursor preferred; never rely solely on server-stored cursor.
            return next(options.cursor != null ? options.cursor : stored);
        });
    }

    function syncAll(options) {
        if (!isActive()) {
            return Promise.resolve({ skipped: true });
        }
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
            return { ok: true, results: results };
        });
    }

    root.RatebOfflineMasterData = {
        isActive: isActive,
        tenantScope: tenantScope,
        resolveEntity: resolveEntity,
        cursorKey: cursorKey,
        readClientCursor: readClientCursor,
        writeClientCursor: writeClientCursor,
        pullEntity: pullEntity,
        syncAll: syncAll,
        ENTITIES: ENTITIES
    };
})(typeof window !== 'undefined' ? window : globalThis);
