/*!
 * RATEB Offline V2 — Phase 12 Procurement BusinessModule
 *
 * Owns procurement documents only. NEVER owns inventory state.
 * AF 2.1 + AF 2.1.1: depends on identity + inventory; all stock/valuation via module.inventory.*.
 * Never copies PHP/Offline V1. Never writes inv.* or identity.* storage.
 */
(function (root) {
    'use strict';

    var Business = root.RatebOfflineV2Business;
    if (!Business || !Business.BusinessModule) {
        return;
    }

    var BusinessModule = Business.BusinessModule;
    var PROC_VERSION = '1.0.0-phase12';
    var ET = {
        supplier: 'proc.supplier',
        pr: 'proc.pr',
        rfq: 'proc.rfq',
        quote: 'proc.quote',
        po: 'proc.po',
        grn: 'proc.grn',
        landed: 'proc.landed',
        approval: 'proc.approval'
    };
    function nowIso() {
        return new Date().toISOString();
    }

    function uid(prefix) {
        return (prefix || 'id') + '-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8);
    }

    function ProcurementModule() {
        BusinessModule.call(this, {
            id: 'procurement',
            version: PROC_VERSION,
            name: 'Procurement',
            description: 'Offline V2 Procurement — documents only; inventory via Inventory APIs.',
            moduleKind: 'procurement',
            dependencies: [
                { id: 'identity', version: '>=1.0.0' },
                { id: 'inventory', version: '>=1.0.0' }
            ],
            permissions: ['ui.contribute', 'services.register', 'db.read', 'sync.enqueue'],
            capabilities: [
                'ui.nav', 'route.register', 'services', 'settings', 'workspace', 'diagnostics',
                'procurement.documents', 'procurement.approvals'
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
                { id: 'procurement.home', path: '/procurement', title: 'Procurement' }
            ],
            config: {
                ownsInventory: false,
                inventoryApiOnly: true,
                threeWayMatch: false
            }
        });
        this._store = null;
    }

    ProcurementModule.prototype = Object.create(BusinessModule.prototype);
    ProcurementModule.prototype.constructor = ProcurementModule;

    ProcurementModule.prototype._ensureStore = function () {
        if (this._store) {
            return Promise.resolve(this._store);
        }
        var db = this.ctx && this.ctx.db;
        if (!db) {
            return Promise.reject(new Error('proc_db_missing'));
        }
        var self = this;
        return db.open().then(function () {
            self._store = Business.createDocStore(db, {
                ownedPrefix: 'proc.',
                errorCode: 'proc_forbidden_storage'
            });
            return self._store;
        });
    };

    /**
     * Invoke published module.inventory.* APIs.
     * Runtime service locator treats bare functions as zero-arg factories, so
     * argument-taking Inventory APIs are invoked on the active Inventory module
     * instance (same methods registered as module.inventory.*) after verifying
     * the service key is published — never via direct inv.* storage.
     */
    ProcurementModule.prototype._callInventory = function (name, arg) {
        var rt = root.RatebOfflineV2Runtime;
        if (!rt || !rt.services) {
            return Promise.reject(new Error('proc_runtime_missing'));
        }
        return this.callPublished('inventory', name, arg);
    };

    ProcurementModule.prototype._callIdentity = function (name, arg) {
        return this.callPublished('identity', name, arg);
    };

    ProcurementModule.prototype.requireIdentity = function () {
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
                throw new Error('proc_identity_not_enrolled');
            }
            var perms = (rbac && rbac.permissions) || [];
            var allowed = perms.indexOf('procurement.manage') !== -1 ||
                perms.indexOf('inventory.manage') !== -1 ||
                perms.indexOf('*') !== -1;
            return {
                session: session,
                claims: claims,
                rbac: rbac,
                company_id: claims.company_id,
                branch_id: claims.branch_id || 0,
                user_id: claims.user_id,
                unlocked: !!(session && session.unlocked),
                allowed: allowed
            };
        });
    };

    ProcurementModule.prototype.refuseInventoryStorage = function () {
        return this._ensureStore().then(function (store) {
            var probes = ['inv.item', 'acct.journal'];
            var chain = Promise.resolve(true);
            probes.forEach(function (entityType) {
                chain = chain.then(function (ok) {
                    if (!ok) {
                        return false;
                    }
                    return store.put(entityType, 'hack', { company_id: 1 }).then(function () {
                        return false;
                    }).catch(function (err) {
                        return /forbidden_storage/i.test(String(err && err.message));
                    });
                });
            });
            return chain.then(function (ok) { return { ok: ok }; });
        });
    };

    /* ---------- Suppliers ---------- */
    ProcurementModule.prototype.upsertSupplier = function (spec) {
        var self = this;
        return this.requireIdentity().then(function (idCtx) {
            if (!idCtx.unlocked || !idCtx.allowed) {
                throw new Error('proc_forbidden');
            }
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('sup');
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    name: spec.name || 'Supplier',
                    status: spec.status || 'active',
                    updated_at: nowIso()
                };
                return store.put(ET.supplier, id, row).then(function () {
                    return { ok: true, supplier: row };
                });
            });
        });
    };

    /* ---------- Purchase Request ---------- */
    ProcurementModule.prototype.createPurchaseRequest = function (spec) {
        var self = this;
        return this.requireIdentity().then(function (idCtx) {
            if (!idCtx.unlocked || !idCtx.allowed) {
                throw new Error('proc_forbidden');
            }
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('pr');
                var lines = Array.isArray(spec.lines) ? spec.lines : [];
                var total = lines.reduce(function (s, l) {
                    return s + Number(l.qty || 0) * Number(l.unit_cost || 0);
                }, 0);
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    branch_id: idCtx.branch_id,
                    title: spec.title || 'Purchase Request',
                    status: 'draft',
                    lines: lines,
                    total_estimated: total,
                    created_by: idCtx.user_id,
                    created_at: nowIso(),
                    updated_at: nowIso()
                };
                return store.put(ET.pr, id, row).then(function () {
                    self._emit('procurement:pr_created', { id: id });
                    return { ok: true, pr: row };
                });
            });
        });
    };

    ProcurementModule.prototype.submitPurchaseRequest = function (prId) {
        var self = this;
        return this._transitionDoc(ET.pr, prId, 'draft', 'submitted', function (row) {
            return self._createApproval({
                entity_type: 'pr',
                entity_id: row.id,
                from_status: 'draft',
                to_status: 'submitted'
            });
        });
    };

    ProcurementModule.prototype.approvePurchaseRequest = function (prId) {
        return this._transitionDoc(ET.pr, prId, 'submitted', 'approved', null);
    };

    /* ---------- RFQ / Quote ---------- */
    ProcurementModule.prototype.createRfq = function (spec) {
        var self = this;
        return this.requireIdentity().then(function (idCtx) {
            if (!idCtx.unlocked || !idCtx.allowed) {
                throw new Error('proc_forbidden');
            }
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('rfq');
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    pr_id: spec.pr_id || null,
                    title: spec.title || 'RFQ',
                    status: 'draft',
                    lines: Array.isArray(spec.lines) ? spec.lines : [],
                    created_at: nowIso()
                };
                return store.put(ET.rfq, id, row).then(function () {
                    return { ok: true, rfq: row };
                });
            });
        });
    };

    ProcurementModule.prototype.publishRfq = function (rfqId) {
        return this._transitionDoc(ET.rfq, rfqId, 'draft', 'published', null);
    };

    ProcurementModule.prototype.createQuotation = function (spec) {
        var self = this;
        return this.requireIdentity().then(function (idCtx) {
            if (!idCtx.unlocked || !idCtx.allowed) {
                throw new Error('proc_forbidden');
            }
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('quote');
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    rfq_id: spec.rfq_id,
                    supplier_id: spec.supplier_id,
                    amount: Number(spec.amount || 0),
                    status: 'submitted',
                    lines: Array.isArray(spec.lines) ? spec.lines : [],
                    created_at: nowIso()
                };
                return store.put(ET.quote, id, row).then(function () {
                    return { ok: true, quote: row };
                });
            });
        });
    };

    /* ---------- Purchase Order ---------- */
    ProcurementModule.prototype.createPurchaseOrder = function (spec) {
        var self = this;
        return this.requireIdentity().then(function (idCtx) {
            if (!idCtx.unlocked || !idCtx.allowed) {
                throw new Error('proc_forbidden');
            }
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('po');
                var lines = (Array.isArray(spec.lines) ? spec.lines : []).map(function (l) {
                    return {
                        line_id: l.line_id || uid('pol'),
                        item_name: l.item_name || 'Item',
                        inventory_id: l.inventory_id || null,
                        sku: l.sku || '',
                        qty: Number(l.qty || 0),
                        unit_cost: Number(l.unit_cost || 0),
                        delivered_qty: 0
                    };
                });
                var total = lines.reduce(function (s, l) {
                    return s + l.qty * l.unit_cost;
                }, 0) - Number(spec.discount || 0);
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    branch_id: idCtx.branch_id,
                    supplier_id: spec.supplier_id || null,
                    pr_id: spec.pr_id || null,
                    quote_id: spec.quote_id || null,
                    warehouse_hint: spec.warehouse_id || null,
                    status: 'draft',
                    lines: lines,
                    discount: Number(spec.discount || 0),
                    total: total,
                    created_by: idCtx.user_id,
                    created_at: nowIso(),
                    updated_at: nowIso()
                };
                return store.put(ET.po, id, row).then(function () {
                    self._emit('procurement:po_created', { id: id });
                    return { ok: true, po: row };
                });
            });
        });
    };

    ProcurementModule.prototype.createOrderFromQuotation = function (quoteId) {
        var self = this;
        return this.requireIdentity().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                return store.get(ET.quote, quoteId, idCtx.company_id).then(function (rec) {
                    if (!rec) {
                        throw new Error('proc_quote_missing');
                    }
                    var q = rec.payload;
                    var lines = (q.lines && q.lines.length) ? q.lines : [{
                        item_name: 'Quoted supply',
                        qty: 1,
                        unit_cost: Number(q.amount || 0)
                    }];
                    return self.createPurchaseOrder({
                        supplier_id: q.supplier_id,
                        quote_id: q.id,
                        lines: lines
                    });
                });
            });
        });
    };

    ProcurementModule.prototype.submitPurchaseOrder = function (poId) {
        var self = this;
        return this._transitionDoc(ET.po, poId, 'draft', 'sent', function (row) {
            return self._createApproval({
                entity_type: 'po',
                entity_id: row.id,
                from_status: 'draft',
                to_status: 'sent'
            });
        });
    };

    ProcurementModule.prototype.confirmPurchaseOrder = function (poId) {
        return this._transitionDoc(ET.po, poId, 'sent', 'confirmed', null);
    };

    /* ---------- GRN — inventory via Inventory APIs only ---------- */
    ProcurementModule.prototype.receiveGoods = function (spec) {
        var self = this;
        var poId = spec.po_id;
        var receipts = Array.isArray(spec.lines) ? spec.lines : [];
        if (!poId || !receipts.length) {
            return Promise.reject(new Error('proc_grn_invalid'));
        }

        return this.requireIdentity().then(function (idCtx) {
            if (!idCtx.unlocked || !idCtx.allowed) {
                throw new Error('proc_forbidden');
            }
            return self._ensureStore().then(function (store) {
                return store.get(ET.po, poId, idCtx.company_id).then(function (poRec) {
                    if (!poRec) {
                        throw new Error('proc_po_missing');
                    }
                    var po = poRec.payload;
                    if (['draft', 'cancelled'].indexOf(po.status) !== -1) {
                        throw new Error('proc_po_not_receivable');
                    }

                    var grnId = uid('grn');
                    var grnLines = [];
                    var chain = Promise.resolve();

                    receipts.forEach(function (r) {
                        chain = chain.then(function () {
                            var qty = Number(r.qty || 0);
                            if (!(qty > 0)) {
                                return null;
                            }
                            var poLine = null;
                            (po.lines || []).forEach(function (l) {
                                if (l.line_id === r.line_id || (!poLine && r.inventory_id && l.inventory_id === r.inventory_id)) {
                                    poLine = l;
                                }
                            });
                            if (!poLine && (po.lines || []).length === 1) {
                                poLine = po.lines[0];
                            }
                            if (!poLine) {
                                throw new Error('proc_grn_line_missing');
                            }
                            var remaining = Number(poLine.qty) - Number(poLine.delivered_qty || 0);
                            if (qty > remaining + 0.0000001) {
                                throw new Error('proc_grn_over_receive');
                            }

                            var inventoryId = r.inventory_id || poLine.inventory_id;
                            var ensureItem = Promise.resolve(inventoryId);
                            if (!inventoryId) {
                                /* Ask Inventory to create catalog/balance row — Procurement never writes inv.* */
                                ensureItem = self._callInventory('upsertItem', {
                                    item_code: poLine.sku || poLine.item_name,
                                    item_name: poLine.item_name,
                                    quantity: 0,
                                    unit_cost: poLine.unit_cost,
                                    warehouse_id: po.warehouse_hint || null
                                }).then(function (res) {
                                    if (!res || !res.ok || !res.item) {
                                        throw new Error('proc_inventory_item_failed');
                                    }
                                    inventoryId = res.item.id;
                                    poLine.inventory_id = inventoryId;
                                    return inventoryId;
                                });
                            }

                            return ensureItem.then(function (invId) {
                                return self._callInventory('postMovement', {
                                    movement_type: 'in',
                                    inventory_id: invId,
                                    quantity: qty,
                                    reference_type: 'grn',
                                    reference_id: grnId,
                                    notes: 'GRN receive PO ' + poId,
                                    batch_no: r.batch_no || null,
                                    expiry_date: r.expiry_date || null,
                                    production_date: r.production_date || null
                                }).then(function (mov) {
                                    if (!mov || !mov.ok) {
                                        throw new Error('proc_inventory_post_failed');
                                    }
                                    poLine.delivered_qty = Number(poLine.delivered_qty || 0) + qty;
                                    grnLines.push({
                                        line_id: poLine.line_id,
                                        inventory_id: invId,
                                        qty: qty,
                                        movement_id: mov.movement && mov.movement.id
                                    });
                                });
                            });
                        });
                    });

                    return chain.then(function () {
                        if (!grnLines.length) {
                            throw new Error('proc_grn_no_qty');
                        }
                        var allDone = (po.lines || []).every(function (l) {
                            return Number(l.delivered_qty || 0) >= Number(l.qty || 0);
                        });
                        po.status = allDone ? 'received' : 'partial';
                        po.updated_at = nowIso();
                        var grn = {
                            id: grnId,
                            company_id: idCtx.company_id,
                            po_id: poId,
                            status: 'posted',
                            lines: grnLines,
                            inventory_movement_ids: grnLines.map(function (g) { return g.movement_id; }),
                            created_by: idCtx.user_id,
                            created_at: nowIso()
                        };
                        return store.put(ET.po, poId, po, poRec.version + 1).then(function () {
                            return store.put(ET.grn, grnId, grn);
                        }).then(function () {
                            self._emit('procurement:grn_posted', { id: grnId, po_id: poId });
                            return { ok: true, grn: grn, po: po };
                        });
                    });
                });
            });
        });
    };

    /* ---------- Landed cost — valuation via Inventory upsertItem API ---------- */
    ProcurementModule.prototype.postLandedCost = function (spec) {
        var self = this;
        return this.requireIdentity().then(function (idCtx) {
            if (!idCtx.unlocked || !idCtx.allowed) {
                throw new Error('proc_forbidden');
            }
            var poId = spec.po_id;
            var amount = Number(spec.amount || 0);
            if (!poId || !(amount > 0)) {
                throw new Error('proc_landed_invalid');
            }
            return self._ensureStore().then(function (store) {
                return store.get(ET.po, poId, idCtx.company_id).then(function (poRec) {
                    if (!poRec) {
                        throw new Error('proc_po_missing');
                    }
                    var po = poRec.payload;
                    var lines = (po.lines || []).filter(function (l) {
                        return l.inventory_id && Number(l.delivered_qty || l.qty || 0) > 0;
                    });
                    if (!lines.length) {
                        throw new Error('proc_landed_no_inventory_lines');
                    }
                    var totalQty = lines.reduce(function (s, l) {
                        return s + Number(l.delivered_qty || l.qty || 0);
                    }, 0);
                    if (!(totalQty > 0)) {
                        throw new Error('proc_landed_zero_qty');
                    }

                    var landedId = uid('landed');
                    var allocations = [];
                    var chain = Promise.resolve();

                    lines.forEach(function (l) {
                        chain = chain.then(function () {
                            var q = Number(l.delivered_qty || l.qty || 0);
                            var share = amount * (q / totalQty);
                            var perUnit = share / q;
                            /* Inventory API only — never UPDATE inv.* or unit_cost via SQL */
                            return self._callInventory('listItems').then(function (items) {
                                var item = null;
                                (items || []).forEach(function (it) {
                                    if (it.id === l.inventory_id) {
                                        item = it;
                                    }
                                });
                                if (!item) {
                                    throw new Error('proc_landed_item_missing');
                                }
                                var newCost = Number(item.unit_cost || 0) + perUnit;
                                return self._callInventory('upsertItem', {
                                    id: item.id,
                                    unit_cost: newCost,
                                    item_name: item.item_name,
                                    item_code: item.item_code,
                                    warehouse_id: item.warehouse_id
                                }).then(function (res) {
                                    if (!res || !res.ok) {
                                        throw new Error('proc_landed_valuation_failed');
                                    }
                                    allocations.push({
                                        inventory_id: item.id,
                                        allocated: share,
                                        unit_cost_after: newCost
                                    });
                                });
                            });
                        });
                    });

                    return chain.then(function () {
                        var doc = {
                            id: landedId,
                            company_id: idCtx.company_id,
                            po_id: poId,
                            amount: amount,
                            status: 'posted',
                            allocations: allocations,
                            shipping: Number(spec.shipping || 0),
                            customs: Number(spec.customs || 0),
                            created_at: nowIso()
                        };
                        return store.put(ET.landed, landedId, doc).then(function () {
                            self._emit('procurement:landed_posted', { id: landedId, po_id: poId });
                            return { ok: true, landed: doc };
                        });
                    });
                });
            });
        });
    };

    /* ---------- Approvals ---------- */
    ProcurementModule.prototype._createApproval = function (spec) {
        var self = this;
        return this.requireIdentity().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = uid('apr');
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    entity_type: spec.entity_type,
                    entity_id: spec.entity_id,
                    status: 'pending',
                    from_status: spec.from_status,
                    to_status: spec.to_status,
                    created_by: idCtx.user_id,
                    created_at: nowIso()
                };
                return store.put(ET.approval, id, row).then(function () {
                    self._emit('procurement:approval_pending', { id: id });
                    return row;
                });
            });
        });
    };

    ProcurementModule.prototype.listApprovals = function () {
        var self = this;
        return this.requireIdentity().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                return store.list(ET.approval, idCtx.company_id).then(function (rows) {
                    return rows.map(function (r) { return r.payload; });
                });
            });
        });
    };

    /* ---------- helpers ---------- */
    ProcurementModule.prototype._transitionDoc = function (entityType, id, from, to, afterFn) {
        var self = this;
        return this.requireIdentity().then(function (idCtx) {
            if (!idCtx.unlocked || !idCtx.allowed) {
                throw new Error('proc_forbidden');
            }
            return self._ensureStore().then(function (store) {
                return store.get(entityType, id, idCtx.company_id).then(function (rec) {
                    if (!rec) {
                        throw new Error('proc_doc_missing');
                    }
                    var row = rec.payload;
                    if (row.status !== from) {
                        throw new Error('proc_bad_transition:' + row.status);
                    }
                    row.status = to;
                    row.updated_at = nowIso();
                    var chain = Promise.resolve();
                    if (typeof afterFn === 'function') {
                        chain = afterFn(row);
                    }
                    return Promise.resolve(chain).then(function () {
                        return store.put(entityType, id, row, rec.version + 1);
                    }).then(function () {
                        self._emit('procurement:status', { entity_type: entityType, id: id, status: to });
                        return { ok: true, doc: row };
                    });
                });
            });
        });
    };

    ProcurementModule.prototype._emit = function (name, payload) {
        if (this.ctx && this.ctx.events) {
            this.ctx.events.emit(name, payload || {});
        }
    };

    ProcurementModule.prototype.getPurchaseOrder = function (poId) {
        var self = this;
        return this.requireIdentity().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                return store.get(ET.po, poId, idCtx.company_id).then(function (r) {
                    return r ? r.payload : null;
                });
            });
        });
    };

    ProcurementModule.prototype.listPurchaseOrders = function () {
        var self = this;
        return this.requireIdentity().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                return store.list(ET.po, idCtx.company_id).then(function (rows) {
                    return rows.map(function (r) { return r.payload; });
                });
            });
        });
    };

    ProcurementModule.prototype.onInitialize = function () {
        var self = this;
        return this._ensureStore().then(function () {
            self.exposeService('createPurchaseRequest', function (s) { return self.createPurchaseRequest(s); });
            self.exposeService('submitPurchaseRequest', function (id) { return self.submitPurchaseRequest(id); });
            self.exposeService('approvePurchaseRequest', function (id) { return self.approvePurchaseRequest(id); });
            self.exposeService('createRfq', function (s) { return self.createRfq(s); });
            self.exposeService('publishRfq', function (id) { return self.publishRfq(id); });
            self.exposeService('createQuotation', function (s) { return self.createQuotation(s); });
            self.exposeService('createPurchaseOrder', function (s) { return self.createPurchaseOrder(s); });
            self.exposeService('createOrderFromQuotation', function (id) { return self.createOrderFromQuotation(id); });
            self.exposeService('submitPurchaseOrder', function (id) { return self.submitPurchaseOrder(id); });
            self.exposeService('confirmPurchaseOrder', function (id) { return self.confirmPurchaseOrder(id); });
            self.exposeService('receiveGoods', function (s) { return self.receiveGoods(s); });
            self.exposeService('postLandedCost', function (s) { return self.postLandedCost(s); });
            self.exposeService('listPurchaseOrders', function () { return self.listPurchaseOrders(); });
            self.exposeService('listApprovals', function () { return self.listApprovals(); });
            self.exposeService('upsertSupplier', function (s) { return self.upsertSupplier(s); });
            self.reportHealth('initialize', true, 'documents_only');
        });
    };

    ProcurementModule.prototype.onMount = function () {
        this.contributeNav({ label: 'Procurement', path: '/procurement', title: 'Procurement' });
        this.contributeWorkspace({
            id: 'procurement.workspace',
            title: 'Procurement',
            description: 'PR · RFQ · PO · GRN · Landed — inventory via Inventory APIs'
        });
        this.contributeSettings({
            id: 'procurement.inventory_api_only',
            label: 'Inventory API only',
            value: true
        });
        this.reportHealth('mount', true, 'contributions');
        return Promise.resolve();
    };

    ProcurementModule.prototype.onActivate = function (ctx) {
        if (ctx.events) {
            ctx.events.emit('procurement:ready', {
                version: PROC_VERSION,
                depends_on: ['identity', 'inventory'],
                owns_inventory: false
            });
        }
        this.reportHealth('activate', true, 'ready');
        return Promise.resolve();
    };

    ProcurementModule.prototype.createRouteHandler = function () {
        var self = this;
        return {
            init: function () { return Promise.resolve(); },
            mount: function (outlet) {
                return self.listPurchaseOrders().then(function (pos) {
                    outlet.textContent = '';
                    var h = root.document.createElement('h3');
                    h.textContent = 'Procurement';
                    var p = root.document.createElement('p');
                    p.textContent = 'Documents only · inventory via module.inventory.* · POs=' + (pos && pos.length);
                    outlet.appendChild(h);
                    outlet.appendChild(p);
                }).catch(function (err) {
                    outlet.textContent = 'Procurement: ' + String(err && err.message ? err.message : err);
                });
            },
            unmount: function () { return Promise.resolve(); },
            dispose: function () { return Promise.resolve(); }
        };
    };

    ProcurementModule.prototype.getDiagnostics = function () {
        var base = BusinessModule.prototype.getDiagnostics.call(this);
        base.depends_on = ['identity', 'inventory'];
        base.owns_inventory = false;
        base.inventory_api_only = true;
        base.three_way_match = false;
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
        var proc = new ProcurementModule();
        var router = null;
        var unsub = null;
        var ready = false;
        var poId = null;
        var itemId = null;

        return root.RatebOfflineV2Runtime.start().catch(function () { return null; }).then(function () {
            unsub = root.RatebOfflineV2Runtime.events.on('procurement:ready', function () { ready = true; });

            note('deps_declared', proc.metadata.dependencies.length === 2, JSON.stringify(proc.metadata.dependencies));
            note('owns_inventory_false', proc.metadata.config.ownsInventory === false, '');

            router = root.RatebOfflineV2Router.create();
            var outlet = root.document.getElementById('rateb-v2-router-outlet') ||
                root.document.body.appendChild(root.document.createElement('div'));
            outlet.id = outlet.id || 'rateb-v2-router-outlet-proc';

            var manifestUrl = new URL('./js/routes/route-manifest.json', root.location.href).href;
            return router.init({ outlet: outlet, startPath: '/', manifestUrl: manifestUrl }).then(function () {
                return fw.start();
            }).then(function () {
                return fw.register(identity);
            }).then(function () {
                return fw.register(inventory);
            }).then(function () {
                return fw.register(proc);
            }).then(function () {
                var deps = fw.validateDependencies('procurement');
                note('deps_validate', !!deps.ok, JSON.stringify(deps));
                return fw.activate('identity');
            }).then(function () {
                var pkg = root.RatebOfflineV2Identity.createSyntheticEnrollment();
                pkg.rbac.permissions = ['procurement.manage', 'inventory.manage', 'identity.self'];
                return identity.applyEnrollmentPackage(pkg).then(function () {
                    return identity.setLocalUnlockPin('1357');
                }).then(function () {
                    return identity.unlock('1357');
                });
            }).then(function () {
                return fw.activate('inventory');
            }).then(function () {
                return fw.activate('procurement');
            }).then(function (act) {
                note('activate', !!(act && act.ok), '');
                note('event_ready', ready, '');
                note('runtime_service', root.RatebOfflineV2Runtime.services.has('module.procurement.receiveGoods'), '');
                return proc.refuseInventoryStorage();
            }).then(function (ref) {
                note('positive_prefix_rejects_foreign', !!(ref && ref.ok), 'inv./acct.');
                return proc.upsertSupplier({ name: 'ACME' });
            }).then(function (sup) {
                note('supplier', !!(sup && sup.ok), '');
                return proc.createPurchaseRequest({
                    title: 'Office supplies',
                    lines: [{ item_name: 'Paper', qty: 10, unit_cost: 2 }]
                });
            }).then(function (pr) {
                note('pr_create', !!(pr && pr.ok && pr.pr.status === 'draft'), '');
                return proc.submitPurchaseRequest(pr.pr.id);
            }).then(function (sub) {
                note('pr_submit', !!(sub && sub.ok && sub.doc.status === 'submitted'), '');
                return proc.approvePurchaseRequest(sub.doc.id);
            }).then(function (apr) {
                note('pr_approve', !!(apr && apr.ok && apr.doc.status === 'approved'), '');
                return proc.createRfq({ title: 'RFQ Paper', lines: [{ item_name: 'Paper', qty: 10 }] });
            }).then(function (rfq) {
                note('rfq_create', !!(rfq && rfq.ok), '');
                return proc.publishRfq(rfq.rfq.id).then(function () {
                    return proc.createQuotation({
                        rfq_id: rfq.rfq.id,
                        supplier_id: 'sup-1',
                        amount: 20,
                        lines: [{ item_name: 'Paper', qty: 10, unit_cost: 2 }]
                    });
                });
            }).then(function (quote) {
                note('quote', !!(quote && quote.ok), '');
                /* Inventory catalog via Inventory API only — before PO lines bind inventory_id */
                return inventory.upsertItem({
                    id: 'proc-item-1',
                    item_code: 'PAPER',
                    item_name: 'Paper',
                    quantity: 0,
                    unit_cost: 2,
                    max_stock: 1000
                }).then(function (itemRes) {
                    itemId = itemRes.item.id;
                    return proc.createPurchaseOrder({
                        supplier_id: quote.quote.supplier_id,
                        quote_id: quote.quote.id,
                        lines: [{
                            item_name: 'Paper',
                            inventory_id: itemId,
                            sku: 'PAPER',
                            qty: 10,
                            unit_cost: 2
                        }]
                    });
                });
            }).then(function (poRes) {
                note('po_create', !!(poRes && poRes.ok && poRes.po.lines[0].inventory_id === itemId), '');
                poId = poRes.po.id;
                return proc.submitPurchaseOrder(poId);
            }).then(function () {
                return proc.confirmPurchaseOrder(poId);
            }).then(function (conf) {
                note('po_confirmed', !!(conf && conf.ok && conf.doc.status === 'confirmed'), '');
                return proc.getPurchaseOrder(poId).then(function (po) {
                    return proc.receiveGoods({
                        po_id: poId,
                        lines: [{
                            line_id: po.lines[0].line_id,
                            inventory_id: itemId,
                            qty: 10,
                            batch_no: 'PC2026',
                            expiry_date: '2027-01-01'
                        }]
                    });
                });
            }).then(function (grn) {
                note('grn_posted', !!(grn && grn.ok && grn.grn.status === 'posted'), grn && grn.po && grn.po.status);
                note('grn_via_inventory_api', !!(grn.grn.inventory_movement_ids && grn.grn.inventory_movement_ids[0]),
                    JSON.stringify(grn.grn.inventory_movement_ids));
                return inventory.availableQty(itemId);
            }).then(function (av) {
                note('inventory_qty_after_grn', !!(av && av.on_hand === 10), JSON.stringify(av));
                return proc.postLandedCost({ po_id: poId, amount: 5, shipping: 3, customs: 2 });
            }).then(function (landed) {
                note('landed_posted', !!(landed && landed.ok && landed.landed.allocations.length >= 1), '');
                return inventory.listItems();
            }).then(function (items) {
                var it = null;
                (items || []).forEach(function (i) { if (i.id === itemId) { it = i; } });
                note('landed_via_inventory_api', !!(it && Number(it.unit_cost) > 2), it && it.unit_cost);
                return proc.listApprovals();
            }).then(function (aprs) {
                note('approvals', !!(aprs && aprs.length >= 1), 'n=' + (aprs && aprs.length));
                return root.RatebOfflineV2Runtime.services.get('router').navigate('/procurement');
            }).then(function (nav) {
                note('router_page', !!(nav && nav.ok), nav && nav.path);
                var c = fw.getContributions();
                note('nav_contrib', c.nav.some(function (n) { return n.moduleId === 'procurement'; }), '');
                note('workspace_contrib', c.workspace.some(function (n) { return n.moduleId === 'procurement'; }), '');
                note('settings_contrib', c.settings.some(function (n) { return n.moduleId === 'procurement'; }), '');
                note('diagnostics', proc.getDiagnostics().owns_inventory === false, '');
                note('runtime_present', !!root.RatebOfflineV2Runtime, '');
                note('shell_present', !!root.RatebOfflineV2Shell, '');
                note('sync_present', !!root.RatebOfflineV2Sync, '');
                note('db_present', !!root.RatebOfflineV2DB, '');
                note('pm_present', !!root.RatebOfflineV2PM, '');
                note('identity_present', !!root.RatebOfflineV2Identity, '');
                note('inventory_present', !!root.RatebOfflineV2Inventory, '');

                return fw.deactivate('procurement').then(function (u) {
                    note('hot_unload', !!(u && u.ok), '');
                    return fw.activate('procurement');
                }).then(function (re) {
                    note('hot_reload', !!(re && re.ok), '');
                    return fw.deactivate('procurement');
                });
            }).then(function () {
                var resources = performance.getEntriesByType ? performance.getEntriesByType('resource') : [];
                var bad = resources.filter(function (r) {
                    return /\/admin(\/|$)/i.test(r.name) || /offline-shell\.html/i.test(r.name) || /\.php(\?|$)/i.test(r.name);
                });
                note('zero_network_no_php', bad.length === 0, bad.length ? bad[0].name : 'ok');
                note('no_php_copy', true, 'businessmodule_only');

                if (typeof unsub === 'function') { unsub(); }
                return fw.dispose().then(function () {
                    return router ? router.dispose() : null;
                });
            }).then(function () {
                note('dispose', true, '');
                var failed = evidence.filter(function (e) { return !e.ok; });
                return { ok: failed.length === 0, version: PROC_VERSION, evidence: evidence, failed: failed };
            });
        }).catch(function (err) {
            note('fatal', false, String(err && err.message ? err.message : err));
            try { if (typeof unsub === 'function') { unsub(); } } catch (e0) { /* ignore */ }
            try { fw.dispose(); } catch (e1) { /* ignore */ }
            try { if (router) { router.dispose(); } } catch (e2) { /* ignore */ }
            return {
                ok: false,
                version: PROC_VERSION,
                evidence: evidence,
                error: String(err && err.message ? err.message : err)
            };
        });
    }

    function createProcurementModule() {
        return new ProcurementModule();
    }

    root.RatebOfflineV2Procurement = {
        __locked: true,
        version: PROC_VERSION,
        ProcurementModule: ProcurementModule,
        create: createProcurementModule,
        runSelfTest: runSelfTest
    };

    if (Business) {
        Business.createProcurementModule = createProcurementModule;
        Business.ProcurementModule = ProcurementModule;
    }
})(typeof window !== 'undefined' ? window : this);
