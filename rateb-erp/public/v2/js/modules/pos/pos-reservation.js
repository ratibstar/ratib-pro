/*!
 * RATEB Offline V2 — POS local stock reservation lifecycle (Phase 7)
 *
 * Entity: pos.stock_reservation on existing entity_row.
 * Create ACTIVE · Release ACTIVE→RELEASED. No inv.*, sync.start(), API, or Inventory module.
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

        function softAudit(idCtx, eventType, entityType, entityId, metadata) {
            try {
                if (module && typeof module._auditEvent === 'function') {
                    return module._auditEvent(idCtx, eventType, entityType, entityId, metadata)
                        .catch(function () { return null; });
                }
            } catch (e) { /* ignore */ }
            return Promise.resolve(null);
        }

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

        function markReleased(store, row, reason, idCtx) {
            if (!row || row.status === STATUS.RELEASED) {
                return Promise.resolve(null);
            }
            var next = Object.assign({}, row, {
                status: STATUS.RELEASED,
                released_at: nowIso(),
                release_reason: reason || 'released',
                updated_at: nowIso()
            });
            return store.put(ET.reservation, next.id, next, Number(next.version || 1) + 1)
                .then(function () {
                    if (!idCtx) {
                        return next;
                    }
                    return softAudit(idCtx, 'RESERVATION_RELEASED', ET.reservation, next.id, {
                        sale_id: next.sale_id,
                        reason: reason || 'released'
                    }).then(function () {
                        return next;
                    });
                });
        }

        /**
         * Create ACTIVE reservations for sale lines (POS-local only).
         */
        function reserveForSale(idCtx, spec) {
            spec = spec || {};
            var saleId = String(spec.sale_id || '');
            var draftId = spec.draft_id ? String(spec.draft_id) : null;
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
                            draft_id: draftId,
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
                            return softAudit(idCtx, 'RESERVATION_CREATED', ET.reservation, reservationId, {
                                sale_id: saleId,
                                product_id: row.product_id,
                                qty: row.qty
                            });
                        });
                    });
                });
                return chain.then(function () {
                    return {
                        ok: true,
                        sale_id: saleId,
                        draft_id: draftId,
                        reservations: created,
                        reserved_count: created.length,
                        inventory_touched: false,
                        sync_started: false,
                        network: false
                    };
                });
            });
        }

        function releaseReservation(idCtx, reservationId, reason) {
            if (!reservationId) {
                return Promise.reject(new Error('pos_reservation_id_required'));
            }
            return ensureStore().then(function (store) {
                return store.get(ET.reservation, String(reservationId), idCtx.company_id).then(function (row) {
                    if (!row || !row.payload) {
                        return Promise.reject(new Error('pos_reservation_not_found'));
                    }
                    return markReleased(store, row.payload, reason || 'manual_release', idCtx)
                        .then(function (released) {
                            return {
                                ok: true,
                                released: released ? [released] : [],
                                released_count: released ? 1 : 0,
                                already_released: !released
                            };
                        });
                });
            });
        }

        function releaseForSale(idCtx, saleId, reason) {
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
                        chain = chain.then(function () {
                            return markReleased(store, row, reason || 'sale_release', idCtx).then(function (r) {
                                if (r) {
                                    released.push(r);
                                }
                            });
                        });
                    });
                    return chain.then(function () {
                        return {
                            ok: true,
                            sale_id: String(saleId),
                            released: released,
                            released_count: released.length
                        };
                    });
                });
            });
        }

        function releaseForDraft(idCtx, draftId, reason) {
            if (!draftId) {
                return Promise.reject(new Error('pos_reservation_draft_required'));
            }
            return ensureStore().then(function (store) {
                return listAll(idCtx.company_id).then(function (rows) {
                    var chain = Promise.resolve();
                    var released = [];
                    rows.forEach(function (row) {
                        if (!row || String(row.draft_id || '') !== String(draftId)) {
                            return;
                        }
                        chain = chain.then(function () {
                            return markReleased(store, row, reason || 'cart_cancel', idCtx).then(function (r) {
                                if (r) {
                                    released.push(r);
                                }
                            });
                        });
                    });
                    return chain.then(function () {
                        return {
                            ok: true,
                            draft_id: String(draftId),
                            released: released,
                            released_count: released.length
                        };
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

        /**
         * Find ACTIVE reservations that do not match a valid COMPLETED sale.
         * saleById: map sale_id → sale payload (or null).
         */
        function findOrphanActive(companyId, saleById) {
            saleById = saleById || Object.create(null);
            return listActive(companyId).then(function (rows) {
                return rows.filter(function (r) {
                    var sale = saleById[String(r.sale_id)];
                    if (!sale) {
                        return true;
                    }
                    if (sale.status === 'CANCELLED') {
                        return true;
                    }
                    if (sale.status !== 'COMPLETED') {
                        return true;
                    }
                    return false;
                });
            });
        }

        function releaseMany(idCtx, reservationIds, reason) {
            var ids = reservationIds || [];
            var chain = Promise.resolve();
            var released = [];
            ids.forEach(function (id) {
                chain = chain.then(function () {
                    return releaseReservation(idCtx, id, reason).then(function (res) {
                        if (res && res.released) {
                            released = released.concat(res.released);
                        }
                    }).catch(function () { /* skip missing */ });
                });
            });
            return chain.then(function () {
                return { ok: true, released: released, released_count: released.length };
            });
        }

        return {
            ET: ET,
            STATUS: STATUS,
            ensureStore: ensureStore,
            listAll: listAll,
            listActive: listActive,
            reservedByProduct: reservedByProduct,
            reserveForSale: reserveForSale,
            releaseReservation: releaseReservation,
            releaseForSale: releaseForSale,
            releaseForDraft: releaseForDraft,
            releaseMany: releaseMany,
            listForSale: listForSale,
            findOrphanActive: findOrphanActive,
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
