/*!
 * RATEB Offline V2 — POS local stock snapshot (Phase 5, read-only)
 *
 * Entity: pos.stock_snapshot on existing entity_row.
 * Optionally overlays read-only inv.item rows (SELECT only) — never loads Inventory module,
 * never writes inv.*, never reserves/deducts stock.
 */
(function (root) {
    'use strict';

    var Business = root.RatebOfflineV2Business;
    if (!Business || typeof Business.createDocStore !== 'function') {
        return;
    }

    var ET = {
        snapshot: 'pos.stock_snapshot',
        meta: 'pos.stock_meta',
        product: 'pos.product'
    };

    function nowIso() {
        return new Date().toISOString();
    }

    function qtyNum(n) {
        var v = Number(n);
        if (!isFinite(v) || v < 0) {
            return 0;
        }
        return Math.round(v * 1000) / 1000;
    }

    function createPosStock(module) {
        var state = {
            store: null,
            seedPromise: null
        };

        function ensureStore() {
            if (state.store) {
                return Promise.resolve(state.store);
            }
            var db = module.ctx && module.ctx.db;
            if (!db) {
                return Promise.reject(new Error('pos_db_missing'));
            }
            /* Stock screen / check opens DB — not register/activate. */
            return db.open().then(function () {
                state.store = Business.createDocStore(db, {
                    ownedPrefix: 'pos.',
                    errorCode: 'pos_forbidden_storage'
                });
                return state.store;
            });
        }

        function readInvItemsReadonly(companyId) {
            var db = module.ctx && module.ctx.db;
            if (!db || typeof db.exec !== 'function') {
                return Promise.resolve([]);
            }
            return db.exec(
                'SELECT entity_id, payload_json FROM entity_row ' +
                'WHERE entity_type=? AND company_id=?',
                ['inv.item', Number(companyId)]
            ).then(function (rows) {
                return (rows || []).map(function (r) {
                    var payload = null;
                    try {
                        payload = JSON.parse(r.payload_json || '{}');
                    } catch (e) {
                        payload = null;
                    }
                    if (!payload) {
                        return null;
                    }
                    return {
                        entity_id: r.entity_id,
                        product_id: payload.product_id || null,
                        sku: payload.sku || payload.item_code || '',
                        available_qty: qtyNum(payload.quantity),
                        warehouse_id: payload.warehouse_id || null,
                        name: payload.item_name || payload.name || r.entity_id
                    };
                }).filter(Boolean);
            }).catch(function () {
                /* Table/column missing or empty — POS snapshot still works. */
                return [];
            });
        }

        function localSeedRows(companyId) {
            /* Demo snapshot for catalog products — POS-owned only. */
            return [
                {
                    id: 'prod-water-500',
                    company_id: companyId,
                    product_id: 'prod-water-500',
                    name: 'Mineral Water 500ml',
                    sku: 'WTR-500',
                    available_qty: 25,
                    warehouse_id: 'wh-pos-local',
                    source: 'local_seed',
                    updated_at: nowIso()
                },
                {
                    id: 'prod-cola-330',
                    company_id: companyId,
                    product_id: 'prod-cola-330',
                    name: 'Cola Can 330ml',
                    sku: 'COLA-330',
                    available_qty: 8,
                    warehouse_id: 'wh-pos-local',
                    source: 'local_seed',
                    updated_at: nowIso()
                },
                {
                    id: 'prod-chips-40',
                    company_id: companyId,
                    product_id: 'prod-chips-40',
                    name: 'Potato Chips 40g',
                    sku: 'CHIPS-40',
                    available_qty: 0,
                    warehouse_id: 'wh-pos-local',
                    source: 'local_seed',
                    updated_at: nowIso()
                },
                {
                    id: 'prod-dates-1kg',
                    company_id: companyId,
                    product_id: 'prod-dates-1kg',
                    name: 'Dates 1kg',
                    sku: 'DATES-1KG',
                    available_qty: 12,
                    warehouse_id: 'wh-pos-local',
                    source: 'local_seed',
                    updated_at: nowIso()
                },
                {
                    id: 'prod-milk-1l',
                    company_id: companyId,
                    product_id: 'prod-milk-1l',
                    name: 'Fresh Milk 1L',
                    sku: 'MILK-1L',
                    available_qty: 3,
                    warehouse_id: 'wh-pos-local',
                    source: 'local_seed',
                    updated_at: nowIso()
                }
            ];
        }

        function ensureSeed(companyId) {
            if (state.seedPromise) {
                return state.seedPromise;
            }
            state.seedPromise = ensureStore().then(function (store) {
                return store.get(ET.meta, 'seed', companyId).then(function (meta) {
                    if (meta && meta.payload && meta.payload.seeded) {
                        return { ok: true, seeded: false };
                    }
                    var chain = Promise.resolve();
                    localSeedRows(companyId).forEach(function (row) {
                        chain = chain.then(function () {
                            return store.put(ET.snapshot, row.id, row, 1);
                        });
                    });
                    return chain.then(function () {
                        return store.put(ET.meta, 'seed', {
                            company_id: companyId,
                            seeded: true,
                            seeded_at: nowIso(),
                            source: 'local_seed'
                        }, 1);
                    }).then(function () {
                        return { ok: true, seeded: true };
                    });
                });
            });
            return state.seedPromise.then(function (r) {
                return r;
            }, function (err) {
                state.seedPromise = null;
                throw err;
            });
        }

        function mapInvToProductId(invRow, productsBySku, productsById) {
            if (invRow.product_id && productsById[String(invRow.product_id)]) {
                return String(invRow.product_id);
            }
            if (invRow.sku && productsBySku[String(invRow.sku).toLowerCase()]) {
                return productsBySku[String(invRow.sku).toLowerCase()].id;
            }
            if (invRow.entity_id && productsById[String(invRow.entity_id)]) {
                return String(invRow.entity_id);
            }
            return null;
        }

        function loadProductIndexes(store, companyId) {
            return store.list(ET.product, companyId).then(function (rows) {
                var bySku = Object.create(null);
                var byId = Object.create(null);
                (rows || []).forEach(function (r) {
                    var p = r.payload;
                    if (!p || !p.id) {
                        return;
                    }
                    byId[String(p.id)] = p;
                    if (p.sku) {
                        bySku[String(p.sku).toLowerCase()] = p;
                    }
                });
                return { bySku: bySku, byId: byId };
            });
        }

        /**
         * Build availability list: POS snapshot + read-only inv.item overlay.
         * Prefer inv.item quantity when a product mapping exists (still no writes to inv).
         */
        function listStock(companyId) {
            return ensureSeed(companyId).then(function () {
                return ensureStore().then(function (store) {
                    return Promise.all([
                        store.list(ET.snapshot, companyId),
                        loadProductIndexes(store, companyId),
                        readInvItemsReadonly(companyId)
                    ]).then(function (parts) {
                        var snapRows = parts[0] || [];
                        var indexes = parts[1];
                        var invRows = parts[2] || [];
                        var byProduct = Object.create(null);

                        snapRows.forEach(function (r) {
                            var p = r.payload;
                            if (!p || !p.product_id) {
                                return;
                            }
                            var available = qtyNum(p.available_qty);
                            byProduct[String(p.product_id)] = {
                                product_id: String(p.product_id),
                                name: p.name || p.product_id,
                                sku: p.sku || '',
                                available_qty: available,
                                warehouse_id: p.warehouse_id || null,
                                available: available > 0,
                                source: p.source || 'pos.stock_snapshot',
                                read_only: true
                            };
                        });

                        invRows.forEach(function (inv) {
                            var pid = mapInvToProductId(inv, indexes.bySku, indexes.byId);
                            if (!pid) {
                                return;
                            }
                            var prev = byProduct[pid];
                            var prod = indexes.byId[pid];
                            byProduct[pid] = {
                                product_id: pid,
                                name: (prod && prod.name) || inv.name || pid,
                                sku: (prod && prod.sku) || inv.sku || '',
                                available_qty: qtyNum(inv.available_qty),
                                warehouse_id: inv.warehouse_id || (prev && prev.warehouse_id) || null,
                                available: qtyNum(inv.available_qty) > 0,
                                source: 'inv.item(read)',
                                read_only: true
                            };
                        });

                        /* Ensure catalog products appear even without snapshot (unavailable). */
                        Object.keys(indexes.byId).forEach(function (pid) {
                            if (byProduct[pid]) {
                                return;
                            }
                            var prod = indexes.byId[pid];
                            byProduct[pid] = {
                                product_id: pid,
                                name: prod.name || pid,
                                sku: prod.sku || '',
                                available_qty: 0,
                                warehouse_id: null,
                                available: false,
                                source: 'missing',
                                read_only: true
                            };
                        });

                        return Object.keys(byProduct).sort().map(function (k) {
                            return byProduct[k];
                        });
                    });
                });
            });
        }

        function getAvailability(companyId, productId) {
            if (!productId) {
                return Promise.reject(new Error('pos_stock_product_required'));
            }
            return listStock(companyId).then(function (rows) {
                for (var i = 0; i < rows.length; i++) {
                    if (String(rows[i].product_id) === String(productId)) {
                        return rows[i];
                    }
                }
                return {
                    product_id: String(productId),
                    name: String(productId),
                    sku: '',
                    available_qty: 0,
                    warehouse_id: null,
                    available: false,
                    source: 'missing',
                    read_only: true
                };
            });
        }

        /**
         * Soft cart check — warns on insufficient qty; never blocks / reserves.
         */
        function checkLines(companyId, lines) {
            return listStock(companyId).then(function (stock) {
                var byId = Object.create(null);
                stock.forEach(function (s) {
                    byId[String(s.product_id)] = s;
                });
                var warnings = [];
                var ok = true;
                (lines || []).forEach(function (line) {
                    var pid = String(line.product_id || '');
                    var need = qtyNum(line.qty);
                    var snap = byId[pid];
                    var have = snap ? qtyNum(snap.available_qty) : 0;
                    if (!snap || have < need) {
                        ok = false;
                        warnings.push({
                            product_id: pid,
                            name: (line.name || (snap && snap.name) || pid),
                            requested_qty: need,
                            available_qty: have,
                            warehouse_id: snap ? snap.warehouse_id : null,
                            code: have <= 0 ? 'pos_stock_unavailable' : 'pos_stock_insufficient',
                            message: have <= 0
                                ? ('Unavailable: ' + (line.name || pid))
                                : ('Insufficient stock for ' + (line.name || pid) +
                                    ' (need ' + need + ', have ' + have + ')')
                        });
                    }
                });
                return {
                    ok: ok,
                    blocked: false,
                    reserved: false,
                    warnings: warnings,
                    warning_count: warnings.length,
                    read_only: true,
                    inventory_module: false
                };
            });
        }

        return {
            ET: ET,
            ensureStore: ensureStore,
            listStock: listStock,
            getAvailability: getAvailability,
            checkLines: checkLines,
            isStoreOpen: function () { return !!state.store; }
        };
    }

    root.RatebOfflineV2PosStock = {
        __locked: true,
        create: createPosStock,
        entityTypes: ET
    };
})(typeof window !== 'undefined' ? window : this);
