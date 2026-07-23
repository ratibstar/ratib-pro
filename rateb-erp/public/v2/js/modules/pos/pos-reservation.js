/*!
 * RATEB Offline V2 — POS local stock reservation (Phase 6)
 *
 * Entity: pos.stock_reservation on existing entity_row.
 * Reserves qty for completed local sales. Does not touch inv.*, start sync,
 * call APIs, or load Inventory BusinessModule.
 */
(function (root) {
    'use strict';

    var Business = root.RatebOfflineV2Business;
    if (!Business || typeof Business.createDocStore !== 'function') {
        return;
    }

    var ET = {
        reservation: 'pos.stock_reservation'
    };

    var STATUS = {
        ACTIVE: 'ACTIVE',
        RELEASED: 'RELEASED'
    };

    function nowIso() {
        return new Date().toISOString();
    }

    function uid(prefix) {
        return (prefix || 'rsv') + '-' + Date.now().toString(36) + '-' +
            Math.random().toString(36).slice(2, 8);
    }

    function qtyNum(n) {
        var v = Number(n);
        if (!isFinite(v) || v < 0) {
            return 0;
        }
        return Math.round(v * 1000) / 1000;
    }

    function createPosReservation(module) {
        var state = {
            store: null
        };

        function ensureStore() {
            if (state.store) {
                return Promise.resolve(state.store);
            }
            var db = module.ctx && module.ctx.db;
            if (!db) {
                return Promise.reject(new Error('pos_db_missing'));
            }
            return db.open().then(function () {
                state.store = Business.createDocStore(db, {
                    ownedPrefix: 'pos.',
                    errorCode: 'pos_forbidden_storage'
                });
                return state.store;
            });
        }

        function listAll(companyId) {
            return ensureStore().then(function (store) {
                return store.list(ET.reservation, companyId).then(function (rows) {
                    return (rows || []).map(function (r) {
                        return r.payload;
                    }).filter(Boolean);
                });
            });
        }

        function listActive(companyId) {
            return listAll(companyId).then(function (rows) {
                return rows.filter(function (r) {
                    return r && r.status === STATUS.ACTIVE;
                });
            });
        }

        function reservedByProduct(companyId) {
            return listActive(companyId).then(function (rows) {
                var map = Object.create(null);
                rows.forEach(function (r) {
                    var pid = String(r.product_id || '');
                    if (!pid) {
                        return;
                    }
                    map[pid] = qtyNum((map[pid] || 0) + qtyNum(r.qty));
                });
                return map;
            });
        }

        /**
         * Create ACTIVE reservations for sale lines (POS-local only).
         * Does not write inv.*, does not start sync.
         */
        function reserveForSale(idCtx, spec) {
            spec = spec || {};
            var saleId = String(spec.sale_id || '');
            var lines = spec.lines || [];
            if (!saleId) {
                return Promise.reject(new Error('pos_reservation_sale_required'));
            }
            if (!lines.length) {
                return Promise.resolve({
                    ok: true,
                    reservations: [],
                    reserved_count: 0,
                    inventory_touched: false,
                    sync_started: false
                });
            }

            return ensureStore().then(function (store) {
                var created = [];
                var chain = Promise.resolve();
                lines.forEach(function (line) {
                    var qty = qtyNum(line.qty);
                    if (qty <= 0 || !line.product_id) {
                        return;
                    }
                    chain = chain.then(function () {
                        var reservationId = uid('rsv');
                        var row = {
                            id: reservationId,
                            reservation_id: reservationId,
                            company_id: idCtx.company_id,
                            sale_id: saleId,
                            product_id: String(line.product_id),
                            qty: qty,
                            warehouse_id: line.warehouse_id || spec.warehouse_id || null,
                            status: STATUS.ACTIVE,
                            source: 'pos_local',
                            created_at: nowIso(),
                            updated_at: nowIso(),
                            version: 1
                        };
                        return store.put(ET.reservation, reservationId, row, 1).then(function () {
                            created.push(row);
                        });
                    });
                });
                return chain.then(function () {
                    return {
                        ok: true,
                        sale_id: saleId,
                        reservations: created,
                        reserved_count: created.length,
                        inventory_touched: false,
                        sync_started: false,
                        network: false
                    };
                });
            });
        }

        function releaseForSale(idCtx, saleId) {
            if (!saleId) {
                return Promise.reject(new Error('pos_reservation_sale_required'));
            }
            return ensureStore().then(function (store) {
                return listAll(idCtx.company_id).then(function (rows) {
                    var chain = Promise.resolve();
                    var released = [];
                    rows.forEach(function (row) {
                        if (!row || String(row.sale_id) !== String(saleId)) {
                            return;
                        }
                        if (row.status === STATUS.RELEASED) {
                            return;
                        }
                        chain = chain.then(function () {
                            var next = Object.assign({}, row, {
                                status: STATUS.RELEASED,
                                released_at: nowIso(),
                                updated_at: nowIso()
                            });
                            return store.put(ET.reservation, next.id, next, Number(next.version || 1) + 1)
                                .then(function () {
                                    released.push(next);
                                });
                        });
                    });
                    return chain.then(function () {
                        return { ok: true, released: released, released_count: released.length };
                    });
                });
            });
        }

        function listForSale(idCtx, saleId) {
            return listAll(idCtx.company_id).then(function (rows) {
                return rows.filter(function (r) {
                    return r && String(r.sale_id) === String(saleId);
                });
            });
        }

        return {
            ET: ET,
            STATUS: STATUS,
            ensureStore: ensureStore,
            listActive: listActive,
            reservedByProduct: reservedByProduct,
            reserveForSale: reserveForSale,
            releaseForSale: releaseForSale,
            listForSale: listForSale,
            isStoreOpen: function () { return !!state.store; }
        };
    }

    root.RatebOfflineV2PosReservation = {
        __locked: true,
        create: createPosReservation,
        entityTypes: ET,
        STATUS: STATUS
    };
})(typeof window !== 'undefined' ? window : this);
