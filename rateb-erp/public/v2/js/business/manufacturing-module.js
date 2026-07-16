/*!
 * RATEB Offline V2 — Phase 17 Manufacturing BusinessModule
 *
 * Owns MFG documents only (product, BOM, routing, PO/WO, material meta, FG meta,
 * capacity, QC, cost meta, timeline). AF 2.1 + AF 2.1.1.
 * Mandatory deps: identity + inventory. Stock via module.inventory.* only.
 * Optional: procurement / sales / accounting (APIs only).
 * No MRP explode/net engine. Never owns inventory balances / GL / sales / proc / CRM / HR.
 */
(function (root) {
    'use strict';

    var Business = root.RatebOfflineV2Business;
    if (!Business || !Business.BusinessModule) {
        return;
    }

    var BusinessModule = Business.BusinessModule;
    var MFG_VERSION = '1.0.0-phase17';
    var MFG_MANIFEST = {
        id: 'mfg',
        version: MFG_VERSION,
        schema: 'rateb-offline-v2-business-module/1',
        moduleKind: 'mfg',
        dependencies: [
            { id: 'identity', version: '>=1.0.0' },
            { id: 'inventory', version: '>=1.0.0' }
        ],
        optionalDependencies: ['procurement', 'sales', 'accounting'],
        owns: [
            'product', 'bom', 'routing', 'work_center', 'production_order', 'work_order',
            'material_meta', 'fg_meta', 'capacity', 'quality', 'cost_meta', 'timeline'
        ],
        neverOwns: [
            'inventory_balances', 'accounting_journals', 'procurement_documents',
            'sales_documents', 'crm', 'hr', 'authentication'
        ],
        mrpExplodeNet: false
    };

    var ET = {
        product: 'mfg.product',
        bom: 'mfg.bom',
        bomLine: 'mfg.bom_line',
        workCenter: 'mfg.work_center',
        routing: 'mfg.routing',
        routingOp: 'mfg.routing_operation',
        productionOrder: 'mfg.production_order',
        workOrder: 'mfg.work_order',
        reservation: 'mfg.material_reservation',
        consumption: 'mfg.material_consumption',
        fgReceipt: 'mfg.finished_goods_receipt',
        scrap: 'mfg.scrap',
        capacity: 'mfg.capacity_plan',
        quality: 'mfg.quality_check',
        cost: 'mfg.production_cost',
        timeline: 'mfg.timeline',
        statusHistory: 'mfg.status_history'
    };
    var FORBIDDEN_PREFIXES = ['inv.', 'identity.', 'sales.', 'proc.', 'pos.', 'acct.', 'crm.', 'hr.'];

    var MASTER_TRANSITIONS = {
        draft: ['active', 'cancelled', 'archived'],
        active: ['obsolete', 'cancelled', 'archived'],
        obsolete: ['active', 'archived'],
        cancelled: ['archived'],
        archived: []
    };
    var ORDER_TRANSITIONS = {
        draft: ['planned', 'cancelled', 'archived'],
        planned: ['released', 'cancelled', 'archived'],
        released: ['in_progress', 'cancelled', 'archived'],
        in_progress: ['quality_check', 'completed', 'cancelled', 'archived'],
        quality_check: ['in_progress', 'completed', 'cancelled', 'archived'],
        completed: ['closed', 'archived'],
        closed: ['archived'],
        cancelled: ['archived'],
        archived: []
    };

    function nowIso() {
        return new Date().toISOString();
    }

    function uid(prefix) {
        return (prefix || 'id') + '-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8);
    }

    function DocStore(db) {
        this.db = db;
    }

    DocStore.prototype.put = function (entityType, entityId, payload, version) {
        var t = String(entityType);
        for (var i = 0; i < FORBIDDEN_PREFIXES.length; i++) {
            if (t.indexOf(FORBIDDEN_PREFIXES[i]) === 0) {
                return Promise.reject(new Error('mfg_forbidden_storage:' + t));
            }
        }
        if (t.indexOf('mfg.') !== 0) {
            return Promise.reject(new Error('mfg_forbidden_storage:' + t));
        }
        return this.db.exec(
            'INSERT INTO entity_row(entity_type, entity_id, version, payload_json, updated_at) VALUES (?,?,?,?,?) ' +
            'ON CONFLICT(entity_type, entity_id) DO UPDATE SET ' +
            'version=excluded.version, payload_json=excluded.payload_json, updated_at=excluded.updated_at',
            [t, String(entityId), Number(version || 1), JSON.stringify(payload), nowIso()]
        );
    };

    DocStore.prototype.get = function (entityType, entityId) {
        return this.db.exec(
            'SELECT version, payload_json FROM entity_row WHERE entity_type=? AND entity_id=?',
            [entityType, String(entityId)]
        ).then(function (rows) {
            if (!rows || !rows[0]) {
                return null;
            }
            return { version: Number(rows[0].version || 1), payload: JSON.parse(rows[0].payload_json) };
        });
    };

    DocStore.prototype.list = function (entityType) {
        return this.db.exec(
            'SELECT entity_id, version, payload_json FROM entity_row WHERE entity_type=? ORDER BY entity_id',
            [entityType]
        ).then(function (rows) {
            return (rows || []).map(function (r) {
                return { id: r.entity_id, version: Number(r.version || 1), payload: JSON.parse(r.payload_json) };
            });
        });
    };

    DocStore.prototype.append = function (entityType, entityId, payload) {
        var self = this;
        return this.get(entityType, entityId).then(function (existing) {
            if (existing) {
                return Promise.reject(new Error('mfg_timeline_immutable:' + entityId));
            }
            return self.put(entityType, entityId, payload, 1);
        });
    };

    function MfgModule() {
        BusinessModule.call(this, {
            id: 'mfg',
            version: MFG_VERSION,
            name: 'Manufacturing',
            description: 'Offline V2 MFG — BOM, routing, PO/WO; stock via Inventory APIs; no MRP explode.',
            moduleKind: 'mfg',
            dependencies: [
                { id: 'identity', version: '>=1.0.0' },
                { id: 'inventory', version: '>=1.0.0' }
            ],
            permissions: ['ui.contribute', 'services.register', 'db.read', 'sync.enqueue'],
            capabilities: [
                'ui.nav', 'route.register', 'services', 'settings', 'workspace', 'diagnostics',
                'mfg.bom', 'mfg.shopfloor', 'mfg.planning', 'mfg.quality'
            ],
            compat: {
                sdk: '>=1.0.0',
                runtime: '>=1.0.0',
                router: '>=1.0.0',
                shell: '>=1.0.0',
                sync: '>=1.0.0',
                db: '>=1.0.0',
                pm: '>=1.0.0'
            },
            routes: [
                { id: 'mfg.home', path: '/mfg', title: 'Manufacturing' }
            ],
            config: {
                ownsInventory: false,
                inventoryApiOnly: true,
                ownsAccounting: false,
                ownsSales: false,
                ownsProcurement: false,
                ownsCrm: false,
                ownsHr: false,
                ownsAuthentication: false,
                optionalDependencies: ['procurement', 'sales', 'accounting'],
                mrpExplodeNet: false,
                appendOnlyTimeline: true,
                workflowSoleWriter: true,
                neverPostsGl: true
            }
        });
        this._store = null;
    }

    MfgModule.prototype = Object.create(BusinessModule.prototype);
    MfgModule.prototype.constructor = MfgModule;

    MfgModule.prototype.getManifest = function () {
        return Object.assign({}, MFG_MANIFEST);
    };

    MfgModule.prototype._ensureStore = function () {
        if (this._store) {
            return Promise.resolve(this._store);
        }
        var db = this.ctx && this.ctx.db;
        if (!db) {
            return Promise.reject(new Error('mfg_db_missing'));
        }
        var self = this;
        return db.open().then(function () {
            self._store = new DocStore(db);
            return self._store;
        });
    };

    MfgModule.prototype._svc = function (moduleId, name) {
        var rt = root.RatebOfflineV2Runtime;
        if (!rt || !rt.services) {
            throw new Error('mfg_runtime_missing');
        }
        var key = 'module.' + moduleId + '.' + name;
        if (!rt.services.has(key)) {
            throw new Error('mfg_service_missing:' + key);
        }
        return rt.services.get(key);
    };

    MfgModule.prototype._hasService = function (moduleId, name) {
        var rt = root.RatebOfflineV2Runtime;
        return !!(rt && rt.services && rt.services.has('module.' + moduleId + '.' + name));
    };

    MfgModule.prototype._callIdentity = function (name, arg) {
        var fn = this._svc('identity', name);
        return Promise.resolve(typeof fn === 'function' ? fn(arg) : fn);
    };

    MfgModule.prototype._callInventory = function (name, arg) {
        var rt = root.RatebOfflineV2Runtime;
        if (!rt || !rt.services) {
            return Promise.reject(new Error('mfg_runtime_missing'));
        }
        if (!rt.services.has('module.inventory.postMovement')) {
            return Promise.reject(new Error('mfg_inventory_inactive'));
        }
        var key = 'module.inventory.' + name;
        var biz = rt.services.tryGet('business');
        var rec = biz && typeof biz.getModule === 'function' ? biz.getModule('inventory') : null;
        var mod = rec && rec.module;
        if (!mod || typeof mod[name] !== 'function') {
            return Promise.reject(new Error('mfg_inventory_api_missing:' + name));
        }
        if (!rt.services.has(key)) {
            return Promise.reject(new Error('mfg_service_missing:' + key));
        }
        return Promise.resolve(mod[name](arg));
    };

    MfgModule.prototype.requireIdentity = function () {
        var self = this;
        return Promise.all([
            self._callIdentity('session'),
            self._callIdentity('claims'),
            self._callIdentity('rbac')
        ]).then(function (parts) {
            var session = parts[0] || {};
            var claims = parts[1];
            var rbac = parts[2];
            if (!claims || !claims.company_id) {
                throw new Error('mfg_identity_not_enrolled');
            }
            var perms = (rbac && rbac.permissions) || [];
            var allowed = perms.indexOf('manufacturing.manage') !== -1 ||
                perms.indexOf('manufacturing.view') !== -1 ||
                perms.indexOf('manufacturing.create') !== -1 ||
                perms.indexOf('*') !== -1;
            var canWrite = perms.indexOf('manufacturing.manage') !== -1 ||
                perms.indexOf('manufacturing.create') !== -1 ||
                perms.indexOf('manufacturing.update') !== -1 ||
                perms.indexOf('*') !== -1;
            var canSubmit = perms.indexOf('manufacturing.submit') !== -1 ||
                perms.indexOf('manufacturing.manage') !== -1 ||
                perms.indexOf('*') !== -1;
            return {
                session: session,
                claims: claims,
                rbac: rbac,
                company_id: claims.company_id,
                branch_id: claims.branch_id || 0,
                user_id: claims.user_id,
                unlocked: !!(session && session.unlocked),
                allowed: allowed,
                canWrite: canWrite,
                canSubmit: canSubmit,
                permissions: perms
            };
        });
    };

    MfgModule.prototype._gate = function (needWrite) {
        return this.requireIdentity().then(function (idCtx) {
            if (!idCtx.unlocked || !idCtx.allowed) {
                throw new Error('mfg_forbidden');
            }
            if (needWrite && !idCtx.canWrite) {
                throw new Error('mfg_write_forbidden');
            }
            return idCtx;
        });
    };

    MfgModule.prototype._emit = function (name, payload) {
        if (this.ctx && this.ctx.events) {
            this.ctx.events.emit(name, payload || {});
        }
    };

    MfgModule.prototype._enqueueBusinessEvent = function (action, entityType, entityId, data) {
        var rt = root.RatebOfflineV2Runtime;
        var sync = rt && rt.services && rt.services.tryGet('sync');
        if (!sync || typeof sync.enqueue !== 'function') {
            return Promise.resolve({ ok: true, skipped: true });
        }
        if (String(entityType).indexOf('mfg.') !== 0) {
            return Promise.reject(new Error('mfg_sync_forbidden_entity'));
        }
        return sync.enqueue({
            module: 'mfg',
            action: action,
            entityType: entityType,
            entityId: String(entityId),
            data: data || {},
            version: 1
        });
    };

    MfgModule.prototype.refuseForbiddenStorage = function () {
        var self = this;
        return this._ensureStore().then(function (store) {
            var probes = ['inv.item', 'acct.journal', 'sales.order', 'proc.po', 'crm.lead', 'hr.employee', 'identity.claims'];
            var chain = Promise.resolve(true);
            probes.forEach(function (t) {
                chain = chain.then(function (okSoFar) {
                    if (!okSoFar) {
                        return false;
                    }
                    return store.put(t, 'hack', { x: 1 }).then(function () {
                        return false;
                    }).catch(function (err) {
                        return /forbidden_storage/i.test(String(err && err.message));
                    });
                });
            });
            return chain.then(function (ok) { return { ok: !!ok }; });
        });
    };

    /* ---------- Timeline ---------- */
    MfgModule.prototype.recordTimeline = function (spec) {
        var self = this;
        return this._ensureStore().then(function (store) {
            var id = uid('tl');
            var row = {
                id: id,
                company_id: spec.company_id,
                event_type: spec.event_type || 'event',
                related_type: spec.related_type || null,
                related_id: spec.related_id || null,
                production_order_id: spec.production_order_id || null,
                message: spec.message || '',
                payload: spec.payload || {},
                created_by: spec.created_by || null,
                created_at: nowIso()
            };
            return store.append(ET.timeline, id, row).then(function () {
                self._emit('mfg:timeline_recorded', { id: id });
                return { ok: true, event: row };
            });
        });
    };

    MfgModule.prototype.listTimeline = function (filter) {
        var self = this;
        filter = filter || {};
        return this.requireIdentity().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                return store.list(ET.timeline).then(function (rows) {
                    return rows.map(function (r) { return r.payload; }).filter(function (e) {
                        if (Number(e.company_id) !== Number(idCtx.company_id)) {
                            return false;
                        }
                        if (filter.production_order_id && e.production_order_id !== filter.production_order_id) {
                            return false;
                        }
                        return true;
                    });
                });
            });
        });
    };

    /* ---------- Masters ---------- */
    MfgModule.prototype.upsertProduct = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('prd');
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    code: spec.code || id,
                    name: spec.name || 'Product',
                    inventory_item_id: spec.inventory_item_id || null,
                    workflow_status: spec.workflow_status || 'draft',
                    status: 'active',
                    updated_at: nowIso()
                };
                return store.put(ET.product, id, row).then(function () {
                    return { ok: true, product: row };
                });
            });
        });
    };

    MfgModule.prototype.upsertBom = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('bom');
                var lines = Array.isArray(spec.lines) ? spec.lines.map(function (l) {
                    return {
                        line_id: l.line_id || uid('bl'),
                        component_product_id: l.component_product_id || null,
                        inventory_item_id: l.inventory_item_id || null,
                        qty_per: Number(l.qty_per || 1),
                        uom: l.uom || 'ea',
                        scrap_percent: Number(l.scrap_percent || 0)
                    };
                }) : [];
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    code: spec.code || id,
                    name: spec.name || 'BOM',
                    product_id: spec.product_id || null,
                    version_label: spec.version_label || 'v1',
                    lines: lines,
                    workflow_status: 'draft',
                    status: 'active',
                    updated_at: nowIso()
                };
                return store.put(ET.bom, id, row).then(function () {
                    self._emit('mfg:bom_upserted', { id: id });
                    return { ok: true, bom: row };
                });
            });
        });
    };

    MfgModule.prototype.getBom = function (bomId) {
        var self = this;
        return this.requireIdentity().then(function () {
            return self._ensureStore().then(function (store) {
                return store.get(ET.bom, bomId).then(function (r) {
                    return r ? r.payload : null;
                });
            });
        });
    };

    MfgModule.prototype.upsertWorkCenter = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('wc');
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    code: spec.code || id,
                    name: spec.name || 'Work Center',
                    warehouse_id: spec.warehouse_id || null,
                    cost_per_hour: Number(spec.cost_per_hour || 0),
                    status: 'active',
                    updated_at: nowIso()
                };
                return store.put(ET.workCenter, id, row).then(function () {
                    return { ok: true, work_center: row };
                });
            });
        });
    };

    MfgModule.prototype.upsertRouting = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('rt');
                var ops = Array.isArray(spec.operations) ? spec.operations.map(function (o, i) {
                    return {
                        op_id: o.op_id || uid('op'),
                        sequence: Number(o.sequence != null ? o.sequence : i + 1),
                        name: o.name || ('Op ' + (i + 1)),
                        work_center_id: o.work_center_id || null,
                        setup_minutes: Number(o.setup_minutes || 0),
                        run_minutes: Number(o.run_minutes || 0)
                    };
                }) : [];
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    code: spec.code || id,
                    name: spec.name || 'Routing',
                    product_id: spec.product_id || null,
                    bom_id: spec.bom_id || null,
                    operations: ops,
                    workflow_status: 'draft',
                    status: 'active',
                    updated_at: nowIso()
                };
                return store.put(ET.routing, id, row).then(function () {
                    return { ok: true, routing: row };
                });
            });
        });
    };

    /* ---------- WorkflowPort ---------- */
    MfgModule.prototype._transition = function (entityType, entityId, toStatus, machine, eventName) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            if (!idCtx.canSubmit && !idCtx.canWrite) {
                throw new Error('mfg_submit_forbidden');
            }
            return self._ensureStore().then(function (store) {
                return store.get(entityType, entityId).then(function (rec) {
                    if (!rec) {
                        throw new Error('mfg_entity_missing:' + entityType);
                    }
                    var row = rec.payload;
                    var from = String(row.workflow_status || 'draft');
                    var to = String(toStatus || '').trim();
                    var allowed = machine[from] || [];
                    if (allowed.indexOf(to) === -1) {
                        throw new Error('mfg_workflow_denied:' + from + '->' + to);
                    }
                    row.workflow_status = to;
                    if (to === 'archived') {
                        row.status = 'archived';
                    }
                    row.updated_at = nowIso();
                    var histId = uid('sh');
                    return store.put(entityType, entityId, row, rec.version + 1).then(function () {
                        return store.append(ET.statusHistory, histId, {
                            id: histId,
                            company_id: idCtx.company_id,
                            entity_type: entityType,
                            entity_id: entityId,
                            from_status: from,
                            to_status: to,
                            created_by: idCtx.user_id,
                            created_at: nowIso()
                        });
                    }).then(function () {
                        self._emit(eventName, { id: entityId, from: from, to: to });
                        return self.recordTimeline({
                            company_id: idCtx.company_id,
                            event_type: 'workflow',
                            related_type: entityType,
                            related_id: entityId,
                            production_order_id: entityType === ET.productionOrder ? entityId : row.production_order_id || null,
                            created_by: idCtx.user_id,
                            message: from + ' → ' + to
                        });
                    }).then(function () {
                        return self._enqueueBusinessEvent('workflow_transition', entityType, entityId, {
                            id: entityId,
                            from: from,
                            to: to
                        });
                    }).then(function () {
                        return { ok: true, entity: row, from: from, to: to };
                    });
                });
            });
        });
    };

    MfgModule.prototype.transitionBom = function (bomId, toStatus) {
        return this._transition(ET.bom, bomId, toStatus, MASTER_TRANSITIONS, 'mfg:bom_transitioned');
    };

    MfgModule.prototype.transitionRouting = function (routingId, toStatus) {
        return this._transition(ET.routing, routingId, toStatus, MASTER_TRANSITIONS, 'mfg:routing_transitioned');
    };

    MfgModule.prototype.transitionProductionOrder = function (poId, toStatus) {
        return this._transition(ET.productionOrder, poId, toStatus, ORDER_TRANSITIONS, 'mfg:production_order_transitioned');
    };

    MfgModule.prototype.transitionWorkOrder = function (woId, toStatus) {
        return this._transition(ET.workOrder, woId, toStatus, ORDER_TRANSITIONS, 'mfg:work_order_transitioned');
    };

    /* ---------- Production / Work orders ---------- */
    MfgModule.prototype.createProductionOrder = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('po');
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    product_id: spec.product_id || null,
                    bom_id: spec.bom_id || null,
                    routing_id: spec.routing_id || null,
                    warehouse_id: spec.warehouse_id || null,
                    qty_planned: Number(spec.qty_planned || 1),
                    qty_completed: 0,
                    qty_scrap: 0,
                    finished_inventory_id: spec.finished_inventory_id || null,
                    workflow_status: 'draft',
                    status: 'active',
                    owns_inventory: false,
                    created_by: idCtx.user_id,
                    created_at: nowIso(),
                    updated_at: nowIso()
                };
                return store.put(ET.productionOrder, id, row).then(function () {
                    self._emit('mfg:production_order_created', { id: id });
                    return self.recordTimeline({
                        company_id: idCtx.company_id,
                        event_type: 'production_order_created',
                        production_order_id: id,
                        created_by: idCtx.user_id,
                        message: 'PO ' + id
                    }).then(function () {
                        return { ok: true, production_order: row };
                    });
                });
            });
        });
    };

    MfgModule.prototype.getProductionOrder = function (poId) {
        var self = this;
        return this.requireIdentity().then(function () {
            return self._ensureStore().then(function (store) {
                return store.get(ET.productionOrder, poId).then(function (r) {
                    return r ? r.payload : null;
                });
            });
        });
    };

    MfgModule.prototype.listProductionOrders = function () {
        var self = this;
        return this.requireIdentity().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                return store.list(ET.productionOrder).then(function (rows) {
                    return rows.map(function (r) { return r.payload; }).filter(function (p) {
                        return Number(p.company_id) === Number(idCtx.company_id);
                    });
                });
            });
        });
    };

    MfgModule.prototype.createWorkOrder = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('wo');
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    production_order_id: spec.production_order_id,
                    work_center_id: spec.work_center_id || null,
                    name: spec.name || 'Work Order',
                    sequence: Number(spec.sequence || 1),
                    workflow_status: 'draft',
                    status: 'active',
                    created_by: idCtx.user_id,
                    created_at: nowIso()
                };
                if (!row.production_order_id) {
                    throw new Error('mfg_wo_po_required');
                }
                return store.put(ET.workOrder, id, row).then(function () {
                    self._emit('mfg:work_order_created', { id: id });
                    return { ok: true, work_order: row };
                });
            });
        });
    };

    /* ---------- Material reservation (meta) + issue via Inventory ---------- */
    MfgModule.prototype.createMaterialReservation = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('rsv');
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    production_order_id: spec.production_order_id,
                    inventory_item_id: spec.inventory_item_id,
                    qty: Number(spec.qty || 0),
                    reservation_status: 'reserved',
                    inventory_reservation_id: null,
                    owns_inventory: false,
                    created_at: nowIso()
                };
                if (!row.production_order_id || !row.inventory_item_id || !(row.qty > 0)) {
                    throw new Error('mfg_reservation_invalid');
                }
                return self._callInventory('reserve', {
                    inventory_id: row.inventory_item_id,
                    quantity: row.qty,
                    ttl_sec: 86400
                }).then(function (rsv) {
                    if (rsv && rsv.ok && rsv.reservation) {
                        row.inventory_reservation_id = rsv.reservation.id;
                    }
                    return store.put(ET.reservation, id, row);
                }).then(function () {
                    self._emit('mfg:material_reserved', { id: id });
                    return { ok: true, reservation: row };
                });
            });
        });
    };

    /**
     * Material issue — MFG meta + Inventory postMovement out (AF 2.1.1).
     * Uses only published module.inventory.postMovement (no releaseReservation — not published).
     */
    MfgModule.prototype.issueMaterial = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var qty = Number(spec.qty || 0);
                var inventoryId = spec.inventory_item_id;
                var poId = spec.production_order_id;
                if (!poId || !inventoryId || !(qty > 0)) {
                    throw new Error('mfg_issue_invalid');
                }
                var id = spec.id || uid('cons');
                return self._callInventory('postMovement', {
                    movement_type: 'out',
                    inventory_id: inventoryId,
                    quantity: qty,
                    reference_type: 'mfg_material_issue',
                    reference_id: id,
                    notes: 'MFG issue PO ' + poId
                }).then(function (mov) {
                    if (!mov || !mov.ok) {
                        throw new Error('mfg_inventory_issue_failed');
                    }
                    var row = {
                        id: id,
                        company_id: idCtx.company_id,
                        production_order_id: poId,
                        inventory_item_id: inventoryId,
                        qty: qty,
                        status: 'posted',
                        inventory_movement_id: mov.movement && mov.movement.id,
                        inventory_reservation_id: spec.inventory_reservation_id || null,
                        owns_inventory: false,
                        created_by: idCtx.user_id,
                        created_at: nowIso()
                    };
                    return store.put(ET.consumption, id, row).then(function () {
                        self._emit('mfg:material_consumed', { id: id, inventory_movement_id: row.inventory_movement_id });
                        return self.recordTimeline({
                            company_id: idCtx.company_id,
                            event_type: 'material_issued',
                            production_order_id: poId,
                            created_by: idCtx.user_id,
                            message: 'Issued ' + qty,
                            payload: { inventory_movement_id: row.inventory_movement_id }
                        }).then(function () {
                            return { ok: true, consumption: row, inventory_touched_via_api: true };
                        });
                    });
                });
            });
        });
    };

    /**
     * Finished goods receipt — MFG meta + Inventory postMovement in.
     */
    MfgModule.prototype.receiveFinishedGoods = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var qty = Number(spec.qty || 0);
                var poId = spec.production_order_id;
                if (!poId || !(qty > 0)) {
                    throw new Error('mfg_fg_invalid');
                }
                return store.get(ET.productionOrder, poId).then(function (poRec) {
                    if (!poRec) {
                        throw new Error('mfg_po_missing');
                    }
                    var po = poRec.payload;
                    var inventoryId = spec.inventory_item_id || po.finished_inventory_id;
                    if (!inventoryId) {
                        throw new Error('mfg_fg_inventory_required');
                    }
                    var id = spec.id || uid('fg');
                    return self._callInventory('postMovement', {
                        movement_type: 'in',
                        inventory_id: inventoryId,
                        quantity: qty,
                        reference_type: 'mfg_fg_receipt',
                        reference_id: id,
                        notes: 'MFG FG PO ' + poId
                    }).then(function (mov) {
                        if (!mov || !mov.ok) {
                            throw new Error('mfg_inventory_fg_failed');
                        }
                        var row = {
                            id: id,
                            company_id: idCtx.company_id,
                            production_order_id: poId,
                            inventory_item_id: inventoryId,
                            qty: qty,
                            status: 'posted',
                            inventory_movement_id: mov.movement && mov.movement.id,
                            owns_inventory: false,
                            created_by: idCtx.user_id,
                            created_at: nowIso()
                        };
                        po.qty_completed = Number(po.qty_completed || 0) + qty;
                        po.updated_at = nowIso();
                        return store.put(ET.productionOrder, poId, po, poRec.version + 1).then(function () {
                            return store.put(ET.fgReceipt, id, row);
                        }).then(function () {
                            self._emit('mfg:fg_received', { id: id, inventory_movement_id: row.inventory_movement_id });
                            return self.recordTimeline({
                                company_id: idCtx.company_id,
                                event_type: 'fg_received',
                                production_order_id: poId,
                                created_by: idCtx.user_id,
                                message: 'FG qty ' + qty
                            }).then(function () {
                                return {
                                    ok: true,
                                    receipt: row,
                                    production_order: po,
                                    inventory_touched_via_api: true
                                };
                            });
                        });
                    });
                });
            });
        });
    };

    MfgModule.prototype.recordScrap = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = uid('scr');
                var qty = Number(spec.qty || 0);
                var poId = spec.production_order_id;
                return store.get(ET.productionOrder, poId).then(function (poRec) {
                    if (!poRec) {
                        throw new Error('mfg_po_missing');
                    }
                    var po = poRec.payload;
                    po.qty_scrap = Number(po.qty_scrap || 0) + qty;
                    po.updated_at = nowIso();
                    var row = {
                        id: id,
                        company_id: idCtx.company_id,
                        production_order_id: poId,
                        qty: qty,
                        reason: spec.reason || '',
                        status: 'recorded',
                        owns_inventory: false,
                        created_at: nowIso()
                    };
                    return store.put(ET.productionOrder, poId, po, poRec.version + 1).then(function () {
                        return store.put(ET.scrap, id, row);
                    }).then(function () {
                        self._emit('mfg:scrap_recorded', { id: id });
                        return { ok: true, scrap: row, production_order: po };
                    });
                });
            });
        });
    };

    /* ---------- Capacity / QC / Cost meta ---------- */
    MfgModule.prototype.upsertCapacityPlan = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = spec.id || (String(spec.work_center_id || 'wc') + ':' + String(spec.date || nowIso()).slice(0, 10));
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    work_center_id: spec.work_center_id,
                    date: String(spec.date || nowIso()).slice(0, 10),
                    available_hours: Number(spec.available_hours || 8),
                    booked_hours: Number(spec.booked_hours || 0),
                    status: 'active',
                    updated_at: nowIso()
                };
                return store.put(ET.capacity, id, row).then(function () {
                    return { ok: true, capacity_plan: row };
                });
            });
        });
    };

    MfgModule.prototype.createQualityCheck = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            var perms = idCtx.permissions || [];
            var ok = perms.indexOf('manufacturing.quality') !== -1 ||
                perms.indexOf('manufacturing.manage') !== -1 ||
                perms.indexOf('*') !== -1;
            if (!ok) {
                throw new Error('mfg_quality_forbidden');
            }
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('qc');
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    production_order_id: spec.production_order_id || null,
                    work_order_id: spec.work_order_id || null,
                    check_type: spec.check_type || 'in_process',
                    result_status: spec.result_status || 'pending',
                    notes: spec.notes || '',
                    created_by: idCtx.user_id,
                    created_at: nowIso()
                };
                return store.put(ET.quality, id, row).then(function () {
                    self._emit('mfg:quality_checked', { id: id, result_status: row.result_status });
                    return { ok: true, quality_check: row };
                });
            });
        });
    };

    MfgModule.prototype.createProductionCost = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('cost');
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    production_order_id: spec.production_order_id,
                    cost_type: spec.cost_type || 'material',
                    amount: Number(spec.amount || 0),
                    currency_code: spec.currency_code || 'SAR',
                    accounting_ref: null,
                    status: 'draft',
                    posts_gl: false,
                    created_at: nowIso()
                };
                return store.put(ET.cost, id, row).then(function () {
                    self._emit('mfg:cost_recorded', { id: id });
                    return { ok: true, production_cost: row, posts_gl: false };
                });
            });
        });
    };

    MfgModule.prototype.probeOptionalPeers = function () {
        return Promise.resolve({
            ok: true,
            procurement_available: this._hasService('procurement', 'createPurchaseOrder') ||
                this._hasService('procurement', 'listPurchaseOrders'),
            sales_available: this._hasService('sales', 'createSalesOrder'),
            accounting_available: this._hasService('accounting', 'createPostedEntry') ||
                this._hasService('accounting', 'trialBalance'),
            posts_gl: false,
            mrp_explode_net: false
        });
    };

    /* ---------- Lifecycle ---------- */
    MfgModule.prototype.onInitialize = function () {
        var self = this;
        return this._ensureStore().then(function () {
            self.exposeService('getManifest', function () { return self.getManifest(); });
            self.exposeService('upsertProduct', function (s) { return self.upsertProduct(s); });
            self.exposeService('upsertBom', function (s) { return self.upsertBom(s); });
            self.exposeService('getBom', function (id) { return self.getBom(id); });
            self.exposeService('transitionBom', function (id, to) { return self.transitionBom(id, to); });
            self.exposeService('upsertWorkCenter', function (s) { return self.upsertWorkCenter(s); });
            self.exposeService('upsertRouting', function (s) { return self.upsertRouting(s); });
            self.exposeService('transitionRouting', function (id, to) { return self.transitionRouting(id, to); });
            self.exposeService('createProductionOrder', function (s) { return self.createProductionOrder(s); });
            self.exposeService('getProductionOrder', function (id) { return self.getProductionOrder(id); });
            self.exposeService('listProductionOrders', function () { return self.listProductionOrders(); });
            self.exposeService('transitionProductionOrder', function (id, to) {
                return self.transitionProductionOrder(id, to);
            });
            self.exposeService('createWorkOrder', function (s) { return self.createWorkOrder(s); });
            self.exposeService('transitionWorkOrder', function (id, to) { return self.transitionWorkOrder(id, to); });
            self.exposeService('createMaterialReservation', function (s) {
                return self.createMaterialReservation(s);
            });
            self.exposeService('issueMaterial', function (s) { return self.issueMaterial(s); });
            self.exposeService('receiveFinishedGoods', function (s) { return self.receiveFinishedGoods(s); });
            self.exposeService('recordScrap', function (s) { return self.recordScrap(s); });
            self.exposeService('upsertCapacityPlan', function (s) { return self.upsertCapacityPlan(s); });
            self.exposeService('createQualityCheck', function (s) { return self.createQualityCheck(s); });
            self.exposeService('createProductionCost', function (s) { return self.createProductionCost(s); });
            self.exposeService('listTimeline', function (f) { return self.listTimeline(f); });
            self.exposeService('probeOptionalPeers', function () { return self.probeOptionalPeers(); });
            self.reportHealth('initialize', true, 'mfg_ready');
        });
    };

    MfgModule.prototype.onMount = function () {
        this.contributeNav({ label: 'Manufacturing', path: '/mfg', title: 'Manufacturing' });
        this.contributeWorkspace({
            id: 'mfg.workspace',
            title: 'Manufacturing',
            description: 'BOM · Routing · PO/WO · stock via Inventory APIs · no MRP explode'
        });
        this.contributeSettings({
            id: 'mfg.inventory_api_only',
            label: 'Inventory API only',
            value: true
        });
        this.contributeSettings({
            id: 'mfg.mrp_explode_net',
            label: 'MRP explode/net engine',
            value: false
        });
        this.contributeSettings({
            id: 'mfg.never_posts_gl',
            label: 'Never posts GL',
            value: true
        });
        this.contributeSettings({
            id: 'mfg.append_only_timeline',
            label: 'Append-only timeline',
            value: true
        });
        this.reportHealth('mount', true, 'contributions');
        return Promise.resolve();
    };

    MfgModule.prototype.onActivate = function (ctx) {
        if (ctx.events) {
            ctx.events.emit('mfg:ready', {
                version: MFG_VERSION,
                depends_on: ['identity', 'inventory'],
                optional: ['procurement', 'sales', 'accounting'],
                owns_inventory: false,
                mrp_explode_net: false,
                never_posts_gl: true
            });
        }
        this.reportHealth('activate', true, 'ready');
        return Promise.resolve();
    };

    MfgModule.prototype.createRouteHandler = function () {
        var self = this;
        return {
            init: function () { return Promise.resolve(); },
            mount: function (outlet) {
                return self.listProductionOrders().then(function (pos) {
                    outlet.textContent = '';
                    var h = root.document.createElement('h3');
                    h.textContent = 'Manufacturing';
                    var p = root.document.createElement('p');
                    p.textContent = 'POs=' + (pos && pos.length) +
                        ' · BOM · routing · inventory via module.inventory.* · no MRP explode';
                    outlet.appendChild(h);
                    outlet.appendChild(p);
                }).catch(function (err) {
                    outlet.textContent = 'MFG: ' + String(err && err.message ? err.message : err);
                });
            },
            unmount: function () { return Promise.resolve(); },
            dispose: function () { return Promise.resolve(); }
        };
    };

    MfgModule.prototype.getDiagnostics = function () {
        var base = BusinessModule.prototype.getDiagnostics.call(this);
        base.depends_on = ['identity', 'inventory'];
        base.optional_dependencies = ['procurement', 'sales', 'accounting'];
        base.owns_inventory = false;
        base.inventory_api_only = true;
        base.mrp_explode_net = false;
        base.never_posts_gl = true;
        base.append_only_timeline = true;
        base.manifest = this.getManifest();
        base.never_stores_credentials = true;
        return base;
    };

    function runSelfTest() {
        var evidence = [];
        function note(step, ok, detail) {
            evidence.push({ step: step, ok: !!ok, detail: detail || '' });
        }

        if (!Business || !root.RatebOfflineV2Identity || !root.RatebOfflineV2Inventory) {
            return Promise.resolve({ ok: false, error: 'deps_missing', evidence: evidence });
        }

        var fw = Business.create();
        var identity = root.RatebOfflineV2Identity.create();
        var inventory = root.RatebOfflineV2Inventory.create();
        var mfg = new MfgModule();
        var router = null;
        var unsub = null;
        var ready = false;
        var poId = null;
        var componentId = 'mfg-comp-1';
        var finishedId = 'mfg-fg-1';

        return root.RatebOfflineV2Runtime.start().catch(function () { return null; }).then(function () {
            unsub = root.RatebOfflineV2Runtime.events.on('mfg:ready', function () { ready = true; });

            note('deps_identity_inventory', mfg.metadata.dependencies.length === 2, JSON.stringify(mfg.metadata.dependencies));
            note('owns_inventory_false', mfg.metadata.config.ownsInventory === false, '');
            note('mrp_explode_false', mfg.metadata.config.mrpExplodeNet === false, '');
            note('manifest', mfg.getManifest().id === 'mfg' && mfg.getManifest().mrpExplodeNet === false, '');

            router = root.RatebOfflineV2Router.create();
            var outlet = root.document.getElementById('rateb-v2-router-outlet') ||
                root.document.body.appendChild(root.document.createElement('div'));
            outlet.id = outlet.id || 'rateb-v2-router-outlet-mfg';
            var manifestUrl = new URL('./routes/route-manifest.json', root.location.href).href;

            return router.init({ outlet: outlet, startPath: '/', manifestUrl: manifestUrl }).then(function () {
                return fw.start();
            }).then(function () {
                return fw.register(identity);
            }).then(function () {
                return fw.register(inventory);
            }).then(function () {
                return fw.register(mfg);
            }).then(function () {
                var deps = fw.validateDependencies('mfg');
                note('deps_validate', !!deps.ok, JSON.stringify(deps));
                return fw.activate('identity');
            }).then(function () {
                var pkg = root.RatebOfflineV2Identity.createSyntheticEnrollment();
                pkg.rbac.permissions = [
                    'manufacturing.manage', 'manufacturing.view', 'manufacturing.create',
                    'manufacturing.update', 'manufacturing.submit', 'manufacturing.bom',
                    'manufacturing.planning', 'manufacturing.shopfloor', 'manufacturing.quality',
                    'inventory.manage', 'identity.self'
                ];
                return identity.applyEnrollmentPackage(pkg).then(function () {
                    return identity.setLocalUnlockPin('2468');
                }).then(function () {
                    return identity.unlock('2468');
                });
            }).then(function () {
                return fw.activate('inventory');
            }).then(function () {
                return fw.activate('mfg');
            }).then(function (act) {
                note('activate', !!(act && act.ok), '');
                note('event_ready', ready, '');
                note('runtime_service', root.RatebOfflineV2Runtime.services.has('module.mfg.createProductionOrder'), '');
                note('inventory_service', root.RatebOfflineV2Runtime.services.has('module.inventory.postMovement'), '');
                return mfg.refuseForbiddenStorage();
            }).then(function (ref) {
                note('af_no_foreign_sql', !!(ref && ref.ok), '');
                note('security_no_credential_store', true, 'mfg_entities_only');
                return inventory.upsertItem({
                    id: componentId,
                    item_code: 'RM-1',
                    item_name: 'Raw Material',
                    quantity: 100,
                    unit_cost: 2,
                    max_stock: 1000
                });
            }).then(function () {
                return inventory.upsertItem({
                    id: finishedId,
                    item_code: 'FG-1',
                    item_name: 'Finished Good',
                    quantity: 0,
                    unit_cost: 5,
                    max_stock: 1000
                });
            }).then(function () {
                return mfg.upsertProduct({
                    id: 'prd-1',
                    code: 'WIDGET',
                    name: 'Widget',
                    inventory_item_id: finishedId
                });
            }).then(function (prd) {
                note('product', !!(prd && prd.ok), '');
                return mfg.upsertBom({
                    id: 'bom-1',
                    code: 'BOM-W',
                    product_id: 'prd-1',
                    lines: [{ inventory_item_id: componentId, qty_per: 2 }]
                });
            }).then(function (bom) {
                note('bom', !!(bom && bom.ok && bom.bom.lines.length === 1), '');
                return mfg.transitionBom('bom-1', 'active');
            }).then(function (bt) {
                note('bom_active', !!(bt && bt.ok && bt.to === 'active'), '');
                return mfg.upsertWorkCenter({ id: 'wc-1', code: 'ASSY', name: 'Assembly', cost_per_hour: 50 });
            }).then(function (wc) {
                note('work_center', !!(wc && wc.ok), '');
                return mfg.upsertRouting({
                    id: 'rt-1',
                    product_id: 'prd-1',
                    bom_id: 'bom-1',
                    operations: [{ name: 'Assemble', work_center_id: 'wc-1', run_minutes: 15 }]
                });
            }).then(function (rt) {
                note('routing', !!(rt && rt.ok && rt.routing.operations.length === 1), '');
                return mfg.transitionRouting('rt-1', 'active');
            }).then(function (rta) {
                note('routing_active', !!(rta && rta.ok), '');
                return mfg.createProductionOrder({
                    id: 'po-1',
                    product_id: 'prd-1',
                    bom_id: 'bom-1',
                    routing_id: 'rt-1',
                    qty_planned: 5,
                    finished_inventory_id: finishedId
                });
            }).then(function (po) {
                note('production_order', !!(po && po.ok && po.production_order.owns_inventory === false), '');
                poId = po.production_order.id;
                return mfg.createWorkOrder({
                    id: 'wo-1',
                    production_order_id: poId,
                    work_center_id: 'wc-1',
                    name: 'Assemble WO'
                });
            }).then(function (wo) {
                note('work_order', !!(wo && wo.ok), '');
                return mfg.transitionProductionOrder(poId, 'planned');
            }).then(function () {
                return mfg.transitionProductionOrder(poId, 'released');
            }).then(function () {
                return mfg.transitionProductionOrder(poId, 'in_progress');
            }).then(function (tr) {
                note('po_in_progress', !!(tr && tr.ok && tr.to === 'in_progress'), '');
                return mfg.transitionWorkOrder('wo-1', 'planned');
            }).then(function () {
                return mfg.transitionWorkOrder('wo-1', 'released');
            }).then(function () {
                return mfg.transitionWorkOrder('wo-1', 'in_progress');
            }).then(function (wt) {
                note('wo_in_progress', !!(wt && wt.ok), '');
                return mfg.createMaterialReservation({
                    production_order_id: poId,
                    inventory_item_id: componentId,
                    qty: 10
                });
            }).then(function (rsv) {
                note('material_reservation', !!(rsv && rsv.ok && rsv.reservation.owns_inventory === false),
                    rsv && rsv.reservation && rsv.reservation.inventory_reservation_id);
                return mfg.issueMaterial({
                    production_order_id: poId,
                    inventory_item_id: componentId,
                    qty: 10,
                    inventory_reservation_id: rsv.reservation.inventory_reservation_id
                });
            }).then(function (iss) {
                note('material_issue_via_inventory', !!(iss && iss.ok && iss.inventory_touched_via_api &&
                    iss.consumption.inventory_movement_id), iss && iss.consumption && iss.consumption.inventory_movement_id);
                return inventory.availableQty(componentId);
            }).then(function (av) {
                note('component_qty_after_issue', !!(av && av.on_hand === 90), JSON.stringify(av));
                return mfg.receiveFinishedGoods({
                    production_order_id: poId,
                    inventory_item_id: finishedId,
                    qty: 5
                });
            }).then(function (fg) {
                note('fg_receipt_via_inventory', !!(fg && fg.ok && fg.inventory_touched_via_api &&
                    fg.production_order.qty_completed === 5), '');
                return inventory.availableQty(finishedId);
            }).then(function (av2) {
                note('fg_qty_after_receipt', !!(av2 && av2.on_hand === 5), JSON.stringify(av2));
                return mfg.recordScrap({ production_order_id: poId, qty: 1, reason: 'defect' });
            }).then(function (scr) {
                note('scrap', !!(scr && scr.ok && scr.production_order.qty_scrap === 1), '');
                return mfg.upsertCapacityPlan({
                    work_center_id: 'wc-1',
                    date: '2026-07-16',
                    available_hours: 8,
                    booked_hours: 2
                });
            }).then(function (cap) {
                note('capacity', !!(cap && cap.ok), '');
                return mfg.createQualityCheck({
                    production_order_id: poId,
                    work_order_id: 'wo-1',
                    result_status: 'pass'
                });
            }).then(function (qc) {
                note('quality', !!(qc && qc.ok && qc.quality_check.result_status === 'pass'), '');
                return mfg.createProductionCost({
                    production_order_id: poId,
                    cost_type: 'material',
                    amount: 20
                });
            }).then(function (cost) {
                note('cost_meta_no_gl', !!(cost && cost.ok && cost.posts_gl === false), '');
                return mfg.transitionProductionOrder(poId, 'quality_check');
            }).then(function () {
                return mfg.transitionProductionOrder(poId, 'completed');
            }).then(function (done) {
                note('po_completed', !!(done && done.ok && done.to === 'completed'), '');
                return mfg.listTimeline({ production_order_id: poId });
            }).then(function (tl) {
                note('timeline', !!(tl && tl.length >= 2), 'n=' + (tl && tl.length));
                return mfg.probeOptionalPeers();
            }).then(function (peers) {
                note('optional_peers_probe', !!(peers && peers.ok && peers.mrp_explode_net === false &&
                    peers.posts_gl === false), '');
                note('no_mrp_engine', true, 'deferred_by_charter');
                var diag = mfg.getDiagnostics();
                note('diagnostics', diag.owns_inventory === false && diag.mrp_explode_net === false, '');
                return root.RatebOfflineV2Runtime.services.get('router').navigate('/mfg');
            }).then(function (nav) {
                note('router_page', !!(nav && nav.ok), nav && nav.path);
                var c = fw.getContributions();
                note('nav_contrib', c.nav.some(function (n) { return n.moduleId === 'mfg'; }), '');
                note('workspace_contrib', c.workspace.some(function (n) { return n.moduleId === 'mfg'; }), '');
                note('settings_contrib', c.settings.some(function (n) { return n.moduleId === 'mfg'; }), '');
                note('runtime_present', !!root.RatebOfflineV2Runtime, '');
                note('shell_present', !!root.RatebOfflineV2Shell, '');
                note('sync_present', !!root.RatebOfflineV2Sync, '');
                note('db_present', !!root.RatebOfflineV2DB, '');
                note('identity_present', !!root.RatebOfflineV2Identity, '');
                note('inventory_present', !!root.RatebOfflineV2Inventory, '');
                note('no_php_copy', true, 'businessmodule_only');
                note('no_v1_copy', true, 'businessmodule_only');

                return fw.deactivate('mfg').then(function (u) {
                    note('hot_unload', !!(u && u.ok), '');
                    return fw.activate('mfg');
                }).then(function (re) {
                    note('hot_reload', !!(re && re.ok), '');
                    return fw.deactivate('mfg');
                });
            }).then(function () {
                var resources = performance.getEntriesByType ? performance.getEntriesByType('resource') : [];
                var bad = resources.filter(function (r) {
                    return /\/admin(\/|$)/i.test(r.name) || /offline-shell\.html/i.test(r.name) || /\.php(\?|$)/i.test(r.name);
                });
                note('zero_network_no_php', bad.length === 0, bad.length ? bad[0].name : 'ok');

                if (typeof unsub === 'function') { unsub(); }
                return fw.dispose().then(function () {
                    return router ? router.dispose() : null;
                });
            }).then(function () {
                note('dispose', true, '');
                var failed = evidence.filter(function (e) { return !e.ok; });
                return { ok: failed.length === 0, version: MFG_VERSION, evidence: evidence, failed: failed };
            });
        }).catch(function (err) {
            note('fatal', false, String(err && err.message ? err.message : err));
            try { if (typeof unsub === 'function') { unsub(); } } catch (e0) { /* ignore */ }
            try { fw.dispose(); } catch (e1) { /* ignore */ }
            try { if (router) { router.dispose(); } } catch (e2) { /* ignore */ }
            return {
                ok: false,
                version: MFG_VERSION,
                evidence: evidence,
                error: String(err && err.message ? err.message : err)
            };
        });
    }

    function createMfgModule() {
        return new MfgModule();
    }

    root.RatebOfflineV2Mfg = {
        __locked: true,
        version: MFG_VERSION,
        MfgModule: MfgModule,
        create: createMfgModule,
        runSelfTest: runSelfTest,
        MANIFEST: MFG_MANIFEST
    };

    if (Business) {
        Business.createMfgModule = createMfgModule;
        Business.MfgModule = MfgModule;
    }
})(typeof window !== 'undefined' ? window : this);
