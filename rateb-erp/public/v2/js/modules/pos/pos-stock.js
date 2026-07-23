/*!
 * RATEB Offline V2 — POS local stock snapshot + reservation-aware availability (Phase 6)
 *
 * Entities: pos.stock_snapshot, pos.stock_meta on existing entity_row.
 * Available qty = snapshot/on_hand − ACTIVE pos.stock_reservation (calculated only).
 * Optionally overlays read-only inv.item (SELECT). Never writes inv.*, never loads Inventory.
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
            seedPromise: null,
            reservation: null
        };

        function getReservation() {
            if (state.reservation) {
                return state.reservation;
            }
            var api = root.RatebOfflineV2PosReservation;
            if (!api || typeof api.create !== 'function') {
                throw new Error('pos_reservation_missing');
            }
            state.reservation = api.create(module);
            return state.reservation;
        }

        function ensureStore() {
            if (state.store) {
                return Promise.resolve(state.store);
            }
            var db = module.ctx && module.ctx.db;
            if (!db) {
                return Promise.reject(new Error('pos_db_missing'));
            }
            /* Stock screen / check / reserve opens DB — not register/activate. */
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
                return [];
            });
        }

        function localSeedRows(companyId) {
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

        function applyReservations(rows, reservedMap) {
            return (rows || []).map(function (row) {
                var onHand = qtyNum(row.on_hand_qty != null ? row.on_hand_qty : row.available_qty);
                var reserved = qtyNum(reservedMap[String(row.product_id)] || 0);
                var available = Math.max(0, onHand - reserved);
                return Object.assign({}, row, {
                    on_hand_qty: onHand,
                    reserved_qty: reserved,
                    available_qty: available,
                    available_after_reservation: available,
                    available: available > 0
                });
            });
        }

        /**
         * Snapshot on-hand + ACTIVE reservation reduction (POS calculated only).
         */
        function listStock(companyId) {
            return ensureSeed(companyId).then(function () {
                return ensureStore().then(function (store) {
                    return Promise.all([
                        store.list(ET.snapshot, companyId),
                        loadProductIndexes(store, companyId),
                        readInvItemsReadonly(companyId),
                        getReservation().reservedByProduct(companyId)
                    ]).then(function (parts) {
                        var snapRows = parts[0] || [];
                        var indexes = parts[1];
                        var invRows = parts[2] || [];
                        var reservedMap = parts[3] || Object.create(null);
                        var byProduct = Object.create(null);

                        snapRows.forEach(function (r) {
                            var p = r.payload;
                            if (!p || !p.product_id) {
                                return;
                            }
                            var onHand = qtyNum(p.available_qty);
                            byProduct[String(p.product_id)] = {
                                product_id: String(p.product_id),
                                name: p.name || p.product_id,
                                sku: p.sku || '',
                                on_hand_qty: onHand,
                                available_qty: onHand,
                                warehouse_id: p.warehouse_id || null,
                                available: onHand > 0,
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
                            var onHand = qtyNum(inv.available_qty);
                            byProduct[pid] = {
                                product_id: pid,
                                name: (prod && prod.name) || inv.name || pid,
                                sku: (prod && prod.sku) || inv.sku || '',
                                on_hand_qty: onHand,
                                available_qty: onHand,
                                warehouse_id: inv.warehouse_id || (prev && prev.warehouse_id) || null,
                                available: onHand > 0,
                                source: 'inv.item(read)',
                                read_only: true
                            };
                        });

                        Object.keys(indexes.byId).forEach(function (pid) {
                            if (byProduct[pid]) {
                                return;
                            }
                            var prod = indexes.byId[pid];
                            byProduct[pid] = {
                                product_id: pid,
                                name: prod.name || pid,
                                sku: prod.sku || '',
                                on_hand_qty: 0,
                                available_qty: 0,
                                warehouse_id: null,
                                available: false,
                                source: 'missing',
                                read_only: true
                            };
                        });

                        var list = Object.keys(byProduct).sort().map(function (k) {
                            return byProduct[k];
                        });
                        return applyReservations(list, reservedMap);
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
                    on_hand_qty: 0,
                    reserved_qty: 0,
                    available_qty: 0,
                    available_after_reservation: 0,
                    warehouse_id: null,
                    available: false,
                    source: 'missing',
                    read_only: true
                };
            });
        }

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
                            reserved_qty: snap ? qtyNum(snap.reserved_qty) : 0,
                            on_hand_qty: snap ? qtyNum(snap.on_hand_qty) : 0,
                            warehouse_id: snap ? snap.warehouse_id : null,
                            code: have <= 0 ? 'pos_stock_unavailable' : 'pos_stock_insufficient',
                            message: have <= 0
                                ? ('Unavailable: ' + (line.name || pid))
                                : ('Insufficient stock for ' + (line.name || pid) +
                                    ' (need ' + need + ', have ' + have +
                                    ', reserved ' + (snap ? snap.reserved_qty : 0) + ')')
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

        /**
         * Create POS-local ACTIVE reservations for a sale. Snapshot on_hand unchanged;
         * calculated available drops via reserved_qty.
         */
        function reserveForSale(idCtx, spec) {
            return listStock(idCtx.company_id).then(function (stock) {
                var byId = Object.create(null);
                stock.forEach(function (s) {
                    byId[String(s.product_id)] = s;
                });
                var lines = (spec && spec.lines) || [];
                var enriched = lines.map(function (line) {
                    var snap = byId[String(line.product_id)];
                    return {
                        product_id: line.product_id,
                        qty: line.qty,
                        warehouse_id: (snap && snap.warehouse_id) || line.warehouse_id || null,
                        name: line.name
                    };
                });
                return getReservation().reserveForSale(idCtx, {
                    sale_id: spec.sale_id,
                    draft_id: spec.draft_id || null,
                    lines: enriched,
                    warehouse_id: spec.warehouse_id || null
                }).then(function (res) {
                    return listStock(idCtx.company_id).then(function (after) {
                        var afterById = Object.create(null);
                        after.forEach(function (s) {
                            afterById[String(s.product_id)] = s;
                        });
                        var availability = enriched.map(function (line) {
                            var s = afterById[String(line.product_id)];
                            return {
                                product_id: line.product_id,
                                reserved_qty: s ? s.reserved_qty : qtyNum(line.qty),
                                available_after_reservation: s ? s.available_qty : 0,
                                on_hand_qty: s ? s.on_hand_qty : 0,
                                warehouse_id: s ? s.warehouse_id : line.warehouse_id
                            };
                        });
                        return Object.assign({}, res, {
                            availability: availability,
                            inventory_touched: false
                        });
                    });
                });
            });
        }

        return {
            ET: ET,
            ensureStore: ensureStore,
            listStock: listStock,
            getAvailability: getAvailability,
            checkLines: checkLines,
            reserveForSale: reserveForSale,
            getReservation: getReservation,
            isStoreOpen: function () { return !!state.store; }
        };
    }

    root.RatebOfflineV2PosStock = {
        __locked: true,
        create: createPosStock,
        entityTypes: ET
    };
})(typeof window !== 'undefined' ? window : this);
