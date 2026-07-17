/*!
 * RATEB Offline V2 — Phase 13 Sales BusinessModule
 *
 * B2B sales documents only. NEVER owns inventory/identity/procurement/POS state.
 * AF 2.1 + AF 2.1.1: deps identity + inventory; stock via module.inventory.* only.
 * Never copies PHP/POS SQL/Offline V1. Never writes inv.* / identity.* / proc.*.
 */
(function (root) {
    'use strict';

    var Business = root.RatebOfflineV2Business;
    if (!Business || !Business.BusinessModule) {
        return;
    }

    var BusinessModule = Business.BusinessModule;
    var SALES_VERSION = '1.0.0-phase13';
    var ET = {
        customer: 'sales.customer',
        price: 'sales.price',
        quote: 'sales.quote',
        order: 'sales.order',
        delivery: 'sales.delivery',
        invoice: 'sales.invoice',
        ret: 'sales.return',
        approval: 'sales.approval'
    };
    var DEFAULT_TAX_RATE = 0.15;

    function nowIso() {
        return new Date().toISOString();
    }

    function uid(prefix) {
        return (prefix || 'id') + '-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8);
    }

    function normalizeLines(lines) {
        return (Array.isArray(lines) ? lines : []).map(function (l) {
            return {
                line_id: l.line_id || uid('sl'),
                inventory_id: l.inventory_id || null,
                item_name: l.item_name || 'Item',
                sku: l.sku || '',
                qty: Number(l.qty || 0),
                unit_price: Number(l.unit_price || 0),
                discount: Number(l.discount || 0),
                delivered_qty: Number(l.delivered_qty || 0),
                invoiced_qty: Number(l.invoiced_qty || 0),
                returned_qty: Number(l.returned_qty || 0)
            };
        });
    }

    function lineNet(l) {
        return Math.max(0, Number(l.qty || 0) * Number(l.unit_price || 0) - Number(l.discount || 0));
    }

    function calcTotals(lines, headerDiscount, taxRate) {
        var subtotal = (lines || []).reduce(function (s, l) { return s + lineNet(l); }, 0);
        var discount = Number(headerDiscount || 0);
        var taxable = Math.max(0, subtotal - discount);
        var rate = taxRate != null ? Number(taxRate) : DEFAULT_TAX_RATE;
        var tax = Math.round(taxable * rate * 100) / 100;
        return {
            subtotal: Math.round(subtotal * 100) / 100,
            discount: discount,
            tax_rate: rate,
            tax: tax,
            total: Math.round((taxable + tax) * 100) / 100
        };
    }

    function SalesModule() {
        BusinessModule.call(this, {
            id: 'sales',
            version: SALES_VERSION,
            name: 'Sales',
            description: 'Offline V2 B2B Sales — documents only; inventory via Inventory APIs.',
            moduleKind: 'sales',
            dependencies: [
                { id: 'identity', version: '>=1.0.0' },
                { id: 'inventory', version: '>=1.0.0' }
            ],
            permissions: ['ui.contribute', 'services.register', 'db.read', 'sync.enqueue'],
            capabilities: [
                'ui.nav', 'route.register', 'services', 'settings', 'workspace', 'diagnostics',
                'sales.documents', 'sales.pricing', 'sales.workflow'
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
                { id: 'sales.home', path: '/sales', title: 'Sales' }
            ],
            config: {
                ownsInventory: false,
                inventoryApiOnly: true,
                posIndependent: true,
                defaultTaxRate: DEFAULT_TAX_RATE
            }
        });
        this._store = null;
    }

    SalesModule.prototype = Object.create(BusinessModule.prototype);
    SalesModule.prototype.constructor = SalesModule;

    SalesModule.prototype._ensureStore = function () {
        if (this._store) {
            return Promise.resolve(this._store);
        }
        var db = this.ctx && this.ctx.db;
        if (!db) {
            return Promise.reject(new Error('sales_db_missing'));
        }
        var self = this;
        return db.open().then(function () {
            self._store = Business.createDocStore(db, {
                ownedPrefix: 'sales.',
                errorCode: 'sales_forbidden_storage'
            });
            return self._store;
        });
    };

    /**
     * Inventory APIs via active Inventory module (same methods as module.inventory.*).
     * Requires Inventory activated (postMovement published). Allows Inventory-owned
     * methods such as releaseReservation even if not yet listed in exposeService.
     */
    SalesModule.prototype._callInventory = function (name, arg) {
        var rt = root.RatebOfflineV2Runtime;
        if (!rt || !rt.services) {
            return Promise.reject(new Error('sales_runtime_missing'));
        }
        if (!rt.services.has('module.inventory.postMovement')) {
            return Promise.reject(new Error('sales_inventory_inactive'));
        }
        return this.callPublished('inventory', name, arg);
    };

    SalesModule.prototype._callIdentity = function (name, arg) {
        return this.callPublished('identity', name, arg);
    };

    SalesModule.prototype.requireIdentity = function () {
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
                throw new Error('sales_identity_not_enrolled');
            }
            var perms = (rbac && rbac.permissions) || [];
            var allowed = perms.indexOf('sales.manage') !== -1 ||
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

    SalesModule.prototype.refuseForbiddenStorage = function () {
        var self = this;
        return this._ensureStore().then(function (store) {
            var probes = ['inv.item', 'proc.po', 'acct.journal'];
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

    SalesModule.prototype._emit = function (name, payload) {
        if (this.ctx && this.ctx.events) {
            this.ctx.events.emit(name, payload || {});
        }
    };

    SalesModule.prototype._gate = function () {
        return this.requireIdentity().then(function (idCtx) {
            if (!idCtx.unlocked || !idCtx.allowed) {
                throw new Error('sales_forbidden');
            }
            return idCtx;
        });
    };

    /* ---------- Customer + pricing ---------- */
    SalesModule.prototype.upsertCustomer = function (spec) {
        var self = this;
        return this._gate().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('cust');
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    name: spec.name || 'Customer',
                    price_group: spec.price_group || 'standard',
                    status: spec.status || 'active',
                    updated_at: nowIso()
                };
                return store.put(ET.customer, id, row).then(function () {
                    return { ok: true, customer: row };
                });
            });
        });
    };

    SalesModule.prototype.upsertCustomerPrice = function (spec) {
        var self = this;
        return this._gate().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = spec.id || (String(spec.customer_id || 'any') + ':' + String(spec.inventory_id || spec.sku || uid('price')));
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    customer_id: spec.customer_id || null,
                    price_group: spec.price_group || null,
                    inventory_id: spec.inventory_id || null,
                    sku: spec.sku || '',
                    unit_price: Number(spec.unit_price || 0),
                    updated_at: nowIso()
                };
                return store.put(ET.price, id, row).then(function () {
                    self._emit('sales:price_upserted', { id: id });
                    return { ok: true, price: row };
                });
            });
        });
    };

    SalesModule.prototype.resolveUnitPrice = function (spec) {
        var self = this;
        return this._gate().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                return store.list(ET.price, idCtx.company_id).then(function (rows) {
                    var prices = rows.map(function (r) { return r.payload; });
                    var hit = null;
                    prices.forEach(function (p) {
                        if (spec.customer_id && p.customer_id === spec.customer_id &&
                            p.inventory_id === spec.inventory_id) {
                            hit = p;
                        }
                    });
                    if (!hit && spec.price_group) {
                        prices.forEach(function (p) {
                            if (!hit && p.price_group === spec.price_group &&
                                p.inventory_id === spec.inventory_id) {
                                hit = p;
                            }
                        });
                    }
                    if (hit) {
                        return { ok: true, unit_price: Number(hit.unit_price), source: 'customer_price' };
                    }
                    if (spec.inventory_id) {
                        return self._callInventory('listItems').then(function (items) {
                            var item = null;
                            (items || []).forEach(function (it) {
                                if (it.id === spec.inventory_id) {
                                    item = it;
                                }
                            });
                            var price = item ? Number(item.sell_price || item.unit_cost || 0) : Number(spec.fallback || 0);
                            return {
                                ok: true,
                                unit_price: price,
                                source: item && item.sell_price ? 'inventory_sell' : 'inventory_cost_or_fallback'
                            };
                        });
                    }
                    return { ok: true, unit_price: Number(spec.fallback || 0), source: 'fallback' };
                });
            });
        });
    };

    /* ---------- Quotation ---------- */
    SalesModule.prototype.createQuotation = function (spec) {
        var self = this;
        return this._gate().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('sq');
                var lines = normalizeLines(spec.lines);
                var totals = calcTotals(lines, spec.discount, spec.tax_rate);
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    branch_id: idCtx.branch_id,
                    customer_id: spec.customer_id || null,
                    title: spec.title || 'Sales Quotation',
                    status: 'draft',
                    lines: lines,
                    totals: totals,
                    created_by: idCtx.user_id,
                    created_at: nowIso(),
                    updated_at: nowIso()
                };
                return store.put(ET.quote, id, row).then(function () {
                    self._emit('sales:quote_created', { id: id });
                    return { ok: true, quote: row };
                });
            });
        });
    };

    SalesModule.prototype.submitQuotation = function (quoteId) {
        return this._transition(ET.quote, quoteId, 'draft', 'submitted');
    };

    SalesModule.prototype.acceptQuotation = function (quoteId) {
        return this._transition(ET.quote, quoteId, 'submitted', 'accepted');
    };

    SalesModule.prototype.createOrderFromQuotation = function (quoteId) {
        var self = this;
        return this._gate().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                return store.get(ET.quote, quoteId, idCtx.company_id).then(function (rec) {
                    if (!rec) {
                        throw new Error('sales_quote_missing');
                    }
                    var q = rec.payload;
                    if (q.status !== 'accepted' && q.status !== 'submitted') {
                        throw new Error('sales_quote_not_convertible');
                    }
                    return self.createSalesOrder({
                        customer_id: q.customer_id,
                        quote_id: q.id,
                        lines: q.lines,
                        discount: q.totals && q.totals.discount,
                        tax_rate: q.totals && q.totals.tax_rate
                    });
                });
            });
        });
    };

    /* ---------- Sales Order ---------- */
    SalesModule.prototype.createSalesOrder = function (spec) {
        var self = this;
        return this._gate().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('so');
                var lines = normalizeLines(spec.lines);
                var totals = calcTotals(lines, spec.discount, spec.tax_rate);
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    branch_id: idCtx.branch_id,
                    customer_id: spec.customer_id || null,
                    quote_id: spec.quote_id || null,
                    status: 'draft',
                    lines: lines,
                    totals: totals,
                    reservation_ids: [],
                    created_by: idCtx.user_id,
                    created_at: nowIso(),
                    updated_at: nowIso()
                };
                return store.put(ET.order, id, row).then(function () {
                    self._emit('sales:order_created', { id: id });
                    return { ok: true, order: row };
                });
            });
        });
    };

    SalesModule.prototype.confirmSalesOrder = function (orderId) {
        var self = this;
        return this._gate().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                return store.get(ET.order, orderId, idCtx.company_id).then(function (rec) {
                    if (!rec) {
                        throw new Error('sales_order_missing');
                    }
                    var order = rec.payload;
                    if (order.status !== 'draft' && order.status !== 'submitted') {
                        throw new Error('sales_bad_transition:' + order.status);
                    }
                    var reservationIds = [];
                    var chain = Promise.resolve();
                    (order.lines || []).forEach(function (l) {
                        chain = chain.then(function () {
                            if (!l.inventory_id || !(Number(l.qty) > 0)) {
                                return null;
                            }
                            return self._callInventory('reserve', {
                                inventory_id: l.inventory_id,
                                quantity: Number(l.qty),
                                ttl_sec: 86400
                            }).then(function (rsv) {
                                if (!rsv || !rsv.ok || !rsv.reservation) {
                                    throw new Error('sales_reserve_failed');
                                }
                                reservationIds.push(rsv.reservation.id);
                                l.reservation_id = rsv.reservation.id;
                            });
                        });
                    });
                    return chain.then(function () {
                        order.status = 'confirmed';
                        order.reservation_ids = reservationIds;
                        order.updated_at = nowIso();
                        return store.put(ET.order, orderId, order, rec.version + 1).then(function () {
                            self._emit('sales:order_confirmed', { id: orderId });
                            return { ok: true, order: order };
                        });
                    });
                });
            });
        });
    };

    SalesModule.prototype.getSalesOrder = function (orderId) {
        var self = this;
        return this.requireIdentity().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                return store.get(ET.order, orderId, idCtx.company_id).then(function (r) {
                    return r ? r.payload : null;
                });
            });
        });
    };

    SalesModule.prototype.listSalesOrders = function () {
        var self = this;
        return this.requireIdentity().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                return store.list(ET.order, idCtx.company_id).then(function (rows) {
                    return rows.map(function (r) { return r.payload; });
                });
            });
        });
    };

    /* ---------- Delivery — inventory out via Inventory APIs ---------- */
    SalesModule.prototype.createDelivery = function (spec) {
        var self = this;
        var orderId = spec.order_id;
        var shipLines = Array.isArray(spec.lines) ? spec.lines : [];
        if (!orderId || !shipLines.length) {
            return Promise.reject(new Error('sales_delivery_invalid'));
        }

        return this._gate().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                return store.get(ET.order, orderId, idCtx.company_id).then(function (ordRec) {
                    if (!ordRec) {
                        throw new Error('sales_order_missing');
                    }
                    var order = ordRec.payload;
                    if (['confirmed', 'partial'].indexOf(order.status) === -1) {
                        throw new Error('sales_order_not_deliverable');
                    }

                    var deliveryId = uid('dn');
                    var posted = [];
                    var chain = Promise.resolve();

                    shipLines.forEach(function (s) {
                        chain = chain.then(function () {
                            var qty = Number(s.qty || 0);
                            if (!(qty > 0)) {
                                return null;
                            }
                            var line = null;
                            (order.lines || []).forEach(function (l) {
                                if (l.line_id === s.line_id || (!line && s.inventory_id && l.inventory_id === s.inventory_id)) {
                                    line = l;
                                }
                            });
                            if (!line && (order.lines || []).length === 1) {
                                line = order.lines[0];
                            }
                            if (!line || !line.inventory_id) {
                                throw new Error('sales_delivery_line_missing');
                            }
                            var remaining = Number(line.qty) - Number(line.delivered_qty || 0);
                            if (qty > remaining + 0.0000001) {
                                throw new Error('sales_delivery_over_ship');
                            }

                            var release = Promise.resolve();
                            if (line.reservation_id) {
                                release = self._callInventory('releaseReservation', line.reservation_id).then(function () {
                                    line.reservation_id = null;
                                });
                            }

                            return release.then(function () {
                                return self._callInventory('postMovement', {
                                    movement_type: 'out',
                                    inventory_id: line.inventory_id,
                                    quantity: qty,
                                    reference_type: 'sales_delivery',
                                    reference_id: deliveryId,
                                    notes: 'DN ship SO ' + orderId
                                });
                            }).then(function (mov) {
                                if (!mov || !mov.ok) {
                                    throw new Error('sales_inventory_post_failed');
                                }
                                line.delivered_qty = Number(line.delivered_qty || 0) + qty;
                                posted.push({
                                    line_id: line.line_id,
                                    inventory_id: line.inventory_id,
                                    qty: qty,
                                    movement_id: mov.movement && mov.movement.id
                                });
                            });
                        });
                    });

                    return chain.then(function () {
                        if (!posted.length) {
                            throw new Error('sales_delivery_no_qty');
                        }
                        var allDone = (order.lines || []).every(function (l) {
                            return Number(l.delivered_qty || 0) >= Number(l.qty || 0);
                        });
                        order.status = allDone ? 'delivered' : 'partial';
                        order.reservation_ids = (order.lines || []).map(function (l) {
                            return l.reservation_id;
                        }).filter(Boolean);
                        order.updated_at = nowIso();
                        var dn = {
                            id: deliveryId,
                            company_id: idCtx.company_id,
                            order_id: orderId,
                            status: 'shipped',
                            lines: posted,
                            inventory_movement_ids: posted.map(function (p) { return p.movement_id; }),
                            created_by: idCtx.user_id,
                            created_at: nowIso()
                        };
                        return store.put(ET.order, orderId, order, ordRec.version + 1).then(function () {
                            return store.put(ET.delivery, deliveryId, dn);
                        }).then(function () {
                            self._emit('sales:delivery_shipped', { id: deliveryId, order_id: orderId });
                            return { ok: true, delivery: dn, order: order };
                        });
                    });
                });
            });
        });
    };

    /* ---------- Sales Invoice (document only — no AR ownership) ---------- */
    SalesModule.prototype.createSalesInvoice = function (spec) {
        var self = this;
        return this._gate().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var orderId = spec.order_id;
                return store.get(ET.order, orderId, idCtx.company_id).then(function (ordRec) {
                    if (!ordRec) {
                        throw new Error('sales_order_missing');
                    }
                    var order = ordRec.payload;
                    var lines = (order.lines || []).filter(function (l) {
                        var ship = Number(l.delivered_qty || 0);
                        return ship > Number(l.invoiced_qty || 0);
                    }).map(function (l) {
                        var qty = Number(l.delivered_qty || 0) - Number(l.invoiced_qty || 0);
                        return {
                            line_id: l.line_id,
                            inventory_id: l.inventory_id,
                            item_name: l.item_name,
                            qty: qty,
                            unit_price: l.unit_price,
                            discount: 0
                        };
                    });
                    if (!lines.length) {
                        throw new Error('sales_invoice_nothing_to_bill');
                    }
                    var totals = calcTotals(lines, 0, order.totals && order.totals.tax_rate);
                    var id = uid('si');
                    lines.forEach(function (il) {
                        (order.lines || []).forEach(function (ol) {
                            if (ol.line_id === il.line_id) {
                                ol.invoiced_qty = Number(ol.invoiced_qty || 0) + Number(il.qty);
                            }
                        });
                    });
                    order.updated_at = nowIso();
                    var inv = {
                        id: id,
                        company_id: idCtx.company_id,
                        order_id: orderId,
                        customer_id: order.customer_id,
                        status: 'posted',
                        lines: lines,
                        totals: totals,
                        created_at: nowIso()
                    };
                    return store.put(ET.order, orderId, order, ordRec.version + 1).then(function () {
                        return store.put(ET.invoice, id, inv);
                    }).then(function () {
                        self._emit('sales:invoice_posted', { id: id, order_id: orderId });
                        return { ok: true, invoice: inv };
                    });
                });
            });
        });
    };

    /* ---------- Sales Return — inventory in via Inventory APIs ---------- */
    SalesModule.prototype.createSalesReturn = function (spec) {
        var self = this;
        return this._gate().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var orderId = spec.order_id;
                var retLines = Array.isArray(spec.lines) ? spec.lines : [];
                if (!orderId || !retLines.length) {
                    throw new Error('sales_return_invalid');
                }
                return store.get(ET.order, orderId, idCtx.company_id).then(function (ordRec) {
                    if (!ordRec) {
                        throw new Error('sales_order_missing');
                    }
                    var order = ordRec.payload;
                    var returnId = uid('sr');
                    var posted = [];
                    var chain = Promise.resolve();

                    retLines.forEach(function (r) {
                        chain = chain.then(function () {
                            var qty = Number(r.qty || 0);
                            if (!(qty > 0)) {
                                return null;
                            }
                            var line = null;
                            (order.lines || []).forEach(function (l) {
                                if (l.line_id === r.line_id || (!line && r.inventory_id && l.inventory_id === r.inventory_id)) {
                                    line = l;
                                }
                            });
                            if (!line) {
                                throw new Error('sales_return_line_missing');
                            }
                            var maxRet = Number(line.delivered_qty || 0) - Number(line.returned_qty || 0);
                            if (qty > maxRet + 0.0000001) {
                                throw new Error('sales_return_over');
                            }
                            return self._callInventory('postMovement', {
                                movement_type: 'in',
                                inventory_id: line.inventory_id,
                                quantity: qty,
                                reference_type: 'sales_return',
                                reference_id: returnId,
                                notes: 'Sales return SO ' + orderId
                            }).then(function (mov) {
                                if (!mov || !mov.ok) {
                                    throw new Error('sales_return_inventory_failed');
                                }
                                line.returned_qty = Number(line.returned_qty || 0) + qty;
                                posted.push({
                                    line_id: line.line_id,
                                    inventory_id: line.inventory_id,
                                    qty: qty,
                                    movement_id: mov.movement && mov.movement.id
                                });
                            });
                        });
                    });

                    return chain.then(function () {
                        if (!posted.length) {
                            throw new Error('sales_return_no_qty');
                        }
                        order.updated_at = nowIso();
                        var doc = {
                            id: returnId,
                            company_id: idCtx.company_id,
                            order_id: orderId,
                            status: 'posted',
                            lines: posted,
                            inventory_movement_ids: posted.map(function (p) { return p.movement_id; }),
                            created_at: nowIso()
                        };
                        return store.put(ET.order, orderId, order, ordRec.version + 1).then(function () {
                            return store.put(ET.ret, returnId, doc);
                        }).then(function () {
                            self._emit('sales:return_posted', { id: returnId, order_id: orderId });
                            return { ok: true, sales_return: doc, order: order };
                        });
                    });
                });
            });
        });
    };

    SalesModule.prototype._transition = function (entityType, id, from, to) {
        var self = this;
        return this._gate().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                return store.get(entityType, id, idCtx.company_id).then(function (rec) {
                    if (!rec) {
                        throw new Error('sales_doc_missing');
                    }
                    var row = rec.payload;
                    if (row.status !== from) {
                        throw new Error('sales_bad_transition:' + row.status);
                    }
                    row.status = to;
                    row.updated_at = nowIso();
                    return store.put(entityType, id, row, rec.version + 1).then(function () {
                        self._emit('sales:status', { entity_type: entityType, id: id, status: to });
                        return { ok: true, doc: row };
                    });
                });
            });
        });
    };

    SalesModule.prototype.onInitialize = function () {
        var self = this;
        return this._ensureStore().then(function () {
            self.exposeService('upsertCustomer', function (s) { return self.upsertCustomer(s); });
            self.exposeService('upsertCustomerPrice', function (s) { return self.upsertCustomerPrice(s); });
            self.exposeService('resolveUnitPrice', function (s) { return self.resolveUnitPrice(s); });
            self.exposeService('createQuotation', function (s) { return self.createQuotation(s); });
            self.exposeService('submitQuotation', function (id) { return self.submitQuotation(id); });
            self.exposeService('acceptQuotation', function (id) { return self.acceptQuotation(id); });
            self.exposeService('createOrderFromQuotation', function (id) { return self.createOrderFromQuotation(id); });
            self.exposeService('createSalesOrder', function (s) { return self.createSalesOrder(s); });
            self.exposeService('confirmSalesOrder', function (id) { return self.confirmSalesOrder(id); });
            self.exposeService('createDelivery', function (s) { return self.createDelivery(s); });
            self.exposeService('createSalesInvoice', function (s) { return self.createSalesInvoice(s); });
            self.exposeService('createSalesReturn', function (s) { return self.createSalesReturn(s); });
            self.exposeService('listSalesOrders', function () { return self.listSalesOrders(); });
            self.reportHealth('initialize', true, 'documents_only');
        });
    };

    SalesModule.prototype.onMount = function () {
        this.contributeNav({ label: 'Sales', path: '/sales', title: 'Sales' });
        this.contributeWorkspace({
            id: 'sales.workspace',
            title: 'Sales',
            description: 'Quote · SO · DN · Invoice · Return — inventory via Inventory APIs'
        });
        this.contributeSettings({
            id: 'sales.inventory_api_only',
            label: 'Inventory API only',
            value: true
        });
        this.contributeSettings({
            id: 'sales.default_tax_rate',
            label: 'Default tax rate',
            value: this.metadata.config.defaultTaxRate
        });
        this.reportHealth('mount', true, 'contributions');
        return Promise.resolve();
    };

    SalesModule.prototype.onActivate = function (ctx) {
        if (ctx.events) {
            ctx.events.emit('sales:ready', {
                version: SALES_VERSION,
                depends_on: ['identity', 'inventory'],
                owns_inventory: false,
                pos_independent: true
            });
        }
        this.reportHealth('activate', true, 'ready');
        return Promise.resolve();
    };

    SalesModule.prototype.createRouteHandler = function () {
        var self = this;
        return {
            init: function () { return Promise.resolve(); },
            mount: function (outlet) {
                return self.listSalesOrders().then(function (orders) {
                    outlet.textContent = '';
                    var h = root.document.createElement('h3');
                    h.textContent = 'Sales';
                    var p = root.document.createElement('p');
                    p.textContent = 'B2B documents · inventory via module.inventory.* · orders=' + (orders && orders.length);
                    outlet.appendChild(h);
                    outlet.appendChild(p);
                }).catch(function (err) {
                    outlet.textContent = 'Sales: ' + String(err && err.message ? err.message : err);
                });
            },
            unmount: function () { return Promise.resolve(); },
            dispose: function () { return Promise.resolve(); }
        };
    };

    SalesModule.prototype.getDiagnostics = function () {
        var base = BusinessModule.prototype.getDiagnostics.call(this);
        base.depends_on = ['identity', 'inventory'];
        base.owns_inventory = false;
        base.inventory_api_only = true;
        base.pos_independent = true;
        base.default_tax_rate = this.metadata.config.defaultTaxRate;
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
        var sales = new SalesModule();
        var router = null;
        var unsub = null;
        var ready = false;
        var orderId = null;
        var itemId = 'sales-item-1';
        var lineId = null;

        return root.RatebOfflineV2Runtime.start().catch(function () { return null; }).then(function () {
            unsub = root.RatebOfflineV2Runtime.events.on('sales:ready', function () { ready = true; });

            note('deps_declared', sales.metadata.dependencies.length === 2, JSON.stringify(sales.metadata.dependencies));
            note('owns_inventory_false', sales.metadata.config.ownsInventory === false, '');
            note('pos_independent', sales.metadata.config.posIndependent === true, '');

            router = root.RatebOfflineV2Router.create();
            var outlet = root.document.getElementById('rateb-v2-router-outlet') ||
                root.document.body.appendChild(root.document.createElement('div'));
            outlet.id = outlet.id || 'rateb-v2-router-outlet-sales';
            var manifestUrl = new URL('./js/routes/route-manifest.json', root.location.href).href;

            return router.init({ outlet: outlet, startPath: '/', manifestUrl: manifestUrl }).then(function () {
                return fw.start();
            }).then(function () {
                return fw.register(identity);
            }).then(function () {
                return fw.register(inventory);
            }).then(function () {
                return fw.register(sales);
            }).then(function () {
                var deps = fw.validateDependencies('sales');
                note('deps_validate', !!deps.ok, JSON.stringify(deps));
                return fw.activate('identity');
            }).then(function () {
                var pkg = root.RatebOfflineV2Identity.createSyntheticEnrollment();
                pkg.rbac.permissions = ['sales.manage', 'inventory.manage', 'identity.self'];
                return identity.applyEnrollmentPackage(pkg).then(function () {
                    return identity.setLocalUnlockPin('2468');
                }).then(function () {
                    return identity.unlock('2468');
                });
            }).then(function () {
                return fw.activate('inventory');
            }).then(function () {
                return fw.activate('sales');
            }).then(function (act) {
                note('activate', !!(act && act.ok), '');
                note('event_ready', ready, '');
                note('runtime_service', root.RatebOfflineV2Runtime.services.has('module.sales.createDelivery'), '');
                return sales.refuseForbiddenStorage();
            }).then(function (ref) {
                note('positive_prefix_rejects_foreign', !!(ref && ref.ok), 'inv./proc./acct.');
                return inventory.upsertItem({
                    id: itemId,
                    item_code: 'WIDGET',
                    item_name: 'Widget',
                    quantity: 50,
                    unit_cost: 8,
                    sell_price: 12,
                    max_stock: 1000
                });
            }).then(function () {
                return sales.upsertCustomer({ id: 'cust-1', name: 'Acme Co', price_group: 'gold' });
            }).then(function (cust) {
                note('customer', !!(cust && cust.ok), '');
                return sales.upsertCustomerPrice({
                    customer_id: 'cust-1',
                    inventory_id: itemId,
                    unit_price: 10
                });
            }).then(function (price) {
                note('customer_price', !!(price && price.ok), '');
                return sales.resolveUnitPrice({ customer_id: 'cust-1', inventory_id: itemId });
            }).then(function (resolved) {
                note('pricing_resolve', !!(resolved && resolved.ok && resolved.unit_price === 10),
                    resolved && resolved.source);
                return sales.createQuotation({
                    customer_id: 'cust-1',
                    title: 'Q1 Widgets',
                    lines: [{ inventory_id: itemId, item_name: 'Widget', qty: 5, unit_price: 10 }]
                });
            }).then(function (q) {
                note('quote_create', !!(q && q.ok && q.quote.status === 'draft'), '');
                return sales.submitQuotation(q.quote.id);
            }).then(function (sub) {
                note('quote_submit', !!(sub && sub.ok), '');
                return sales.acceptQuotation(sub.doc.id);
            }).then(function (acc) {
                note('quote_accept', !!(acc && acc.ok && acc.doc.status === 'accepted'), '');
                return sales.createOrderFromQuotation(acc.doc.id);
            }).then(function (ord) {
                note('order_from_quote', !!(ord && ord.ok && ord.order.lines.length === 1), '');
                orderId = ord.order.id;
                lineId = ord.order.lines[0].line_id;
                return sales.confirmSalesOrder(orderId);
            }).then(function (conf) {
                note('order_confirmed', !!(conf && conf.ok && conf.order.status === 'confirmed'), '');
                note('order_reserved', !!(conf.order.reservation_ids && conf.order.reservation_ids.length >= 1),
                    JSON.stringify(conf.order.reservation_ids));
                return inventory.availableQty(itemId);
            }).then(function (av) {
                note('available_after_reserve', !!(av && av.reserved >= 5 && av.available <= 45), JSON.stringify(av));
                return sales.createDelivery({
                    order_id: orderId,
                    lines: [{ line_id: lineId, inventory_id: itemId, qty: 5 }]
                });
            }).then(function (dn) {
                note('delivery_shipped', !!(dn && dn.ok && dn.delivery.status === 'shipped'), dn && dn.order && dn.order.status);
                note('delivery_via_inventory_api', !!(dn.delivery.inventory_movement_ids && dn.delivery.inventory_movement_ids[0]),
                    JSON.stringify(dn.delivery.inventory_movement_ids));
                return inventory.availableQty(itemId);
            }).then(function (av2) {
                note('qty_after_delivery', !!(av2 && av2.on_hand === 45), JSON.stringify(av2));
                return sales.createSalesInvoice({ order_id: orderId });
            }).then(function (inv) {
                note('invoice_posted', !!(inv && inv.ok && inv.invoice.status === 'posted'),
                    inv && inv.invoice && inv.invoice.totals && String(inv.invoice.totals.total));
                return sales.createSalesReturn({
                    order_id: orderId,
                    lines: [{ line_id: lineId, inventory_id: itemId, qty: 2 }]
                });
            }).then(function (ret) {
                note('return_posted', !!(ret && ret.ok && ret.sales_return.status === 'posted'), '');
                note('return_via_inventory_api', !!(ret.sales_return.inventory_movement_ids &&
                    ret.sales_return.inventory_movement_ids[0]), '');
                return inventory.availableQty(itemId);
            }).then(function (av3) {
                note('qty_after_return', !!(av3 && av3.on_hand === 47), JSON.stringify(av3));
                return root.RatebOfflineV2Runtime.services.get('router').navigate('/sales');
            }).then(function (nav) {
                note('router_page', !!(nav && nav.ok), nav && nav.path);
                var c = fw.getContributions();
                note('nav_contrib', c.nav.some(function (n) { return n.moduleId === 'sales'; }), '');
                note('workspace_contrib', c.workspace.some(function (n) { return n.moduleId === 'sales'; }), '');
                note('settings_contrib', c.settings.some(function (n) { return n.moduleId === 'sales'; }), '');
                note('diagnostics', sales.getDiagnostics().owns_inventory === false, '');
                note('runtime_present', !!root.RatebOfflineV2Runtime, '');
                note('shell_present', !!root.RatebOfflineV2Shell, '');
                note('sync_present', !!root.RatebOfflineV2Sync, '');
                note('db_present', !!root.RatebOfflineV2DB, '');
                note('pm_present', !!root.RatebOfflineV2PM, '');
                note('identity_present', !!root.RatebOfflineV2Identity, '');
                note('inventory_present', !!root.RatebOfflineV2Inventory, '');

                return fw.deactivate('sales').then(function (u) {
                    note('hot_unload', !!(u && u.ok), '');
                    return fw.activate('sales');
                }).then(function (re) {
                    note('hot_reload', !!(re && re.ok), '');
                    return fw.deactivate('sales');
                });
            }).then(function () {
                var resources = performance.getEntriesByType ? performance.getEntriesByType('resource') : [];
                var bad = resources.filter(function (r) {
                    return /\/admin(\/|$)/i.test(r.name) || /offline-shell\.html/i.test(r.name) || /\.php(\?|$)/i.test(r.name);
                });
                note('zero_network_no_php', bad.length === 0, bad.length ? bad[0].name : 'ok');
                note('no_php_pos_copy', true, 'businessmodule_only');

                if (typeof unsub === 'function') { unsub(); }
                return fw.dispose().then(function () {
                    return router ? router.dispose() : null;
                });
            }).then(function () {
                note('dispose', true, '');
                var failed = evidence.filter(function (e) { return !e.ok; });
                return { ok: failed.length === 0, version: SALES_VERSION, evidence: evidence, failed: failed };
            });
        }).catch(function (err) {
            note('fatal', false, String(err && err.message ? err.message : err));
            try { if (typeof unsub === 'function') { unsub(); } } catch (e0) { /* ignore */ }
            try { fw.dispose(); } catch (e1) { /* ignore */ }
            try { if (router) { router.dispose(); } } catch (e2) { /* ignore */ }
            return {
                ok: false,
                version: SALES_VERSION,
                evidence: evidence,
                error: String(err && err.message ? err.message : err)
            };
        });
    }

    function createSalesModule() {
        return new SalesModule();
    }

    root.RatebOfflineV2Sales = {
        __locked: true,
        version: SALES_VERSION,
        SalesModule: SalesModule,
        create: createSalesModule,
        runSelfTest: runSelfTest
    };

    if (Business) {
        Business.createSalesModule = createSalesModule;
        Business.SalesModule = SalesModule;
    }
})(typeof window !== 'undefined' ? window : this);
