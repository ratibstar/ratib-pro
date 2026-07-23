/*!
 * RATEB Offline V2 — POS local conflict rules engine (Phase 9–10)
 *
 * Detects sync-prep conflicts locally. Entity: pos.sync_conflict.
 * No API calls, outbox push, Inventory writes, or sync.start().
 */
(function (root) {
    'use strict';

    var Business = root.RatebOfflineV2Business;
    if (!Business || typeof Business.createDocStore !== 'function') {
        return;
    }

    var ET = {
        conflict: 'pos.sync_conflict',
        sale: 'pos.sale',
        product: 'pos.product',
        reservation: 'pos.stock_reservation'
    };

    var STATUS = {
        OPEN: 'OPEN',
        RESOLVED: 'RESOLVED'
    };

    var SEVERITY = {
        ERROR: 'ERROR',
        WARN: 'WARN'
    };

    var TYPE = {
        DUPLICATE_SALE_ID: 'duplicate_sale_local_id',
        DUPLICATE_TXN: 'duplicate_transaction_number',
        DUPLICATE_SYNC_KEY: 'duplicate_sync_key',
        MISSING_PRODUCT: 'missing_product_reference',
        INVALID_QTY: 'invalid_quantity',
        RESERVATION_MISMATCH: 'reservation_mismatch',
        CANCELLED_ACTIVE_RSV: 'cancelled_sale_active_reservation',
        INVALID_TOTALS: 'invalid_totals'
    };

    function nowIso() {
        return new Date().toISOString();
    }

    function uid(prefix) {
        return (prefix || 'cfl') + '-' + Date.now().toString(36) + '-' +
            Math.random().toString(36).slice(2, 8);
    }

    function qtyNum(n) {
        var v = Number(n);
        if (!isFinite(v)) {
            return NaN;
        }
        return Math.round(v * 1000) / 1000;
    }

    function money(n) {
        var v = Math.round(Number(n || 0) * 100) / 100;
        return isFinite(v) ? v : NaN;
    }

    function createPosConflict(module) {
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

        function listEntity(store, type, companyId) {
            return store.list(type, companyId).then(function (rows) {
                return (rows || []).map(function (r) { return r.payload; }).filter(Boolean);
            });
        }

        function conflictKey(entityType, entityId, conflictType) {
            return String(entityType) + '::' + String(entityId) + '::' + String(conflictType);
        }

        function makeConflict(spec) {
            var conflictId = spec.conflict_id || uid('cfl');
            return {
                id: conflictId,
                conflict_id: conflictId,
                company_id: spec.company_id,
                entity_type: spec.entity_type,
                entity_id: String(spec.entity_id),
                conflict_type: spec.conflict_type,
                severity: spec.severity || SEVERITY.ERROR,
                status: STATUS.OPEN,
                message: spec.message || '',
                details: spec.details || null,
                created_at: nowIso(),
                updated_at: nowIso(),
                resolved_at: null,
                resolution: null,
                audit_reason: null,
                version: 1
            };
        }

        function collectSaleConflicts(sale, ctx) {
            var found = [];
            var saleId = String(sale.id || sale.sale_id || '');
            var lines = sale.lines || [];
            var productById = ctx.productById;
            var txnCounts = ctx.txnCounts;
            var idCounts = ctx.idCounts;
            var syncKeyCounts = ctx.syncKeyCounts;
            var rsvList = ctx.rsvBySale[saleId] || [];

            if (idCounts[saleId] && idCounts[saleId] > 1) {
                found.push(makeConflict({
                    company_id: ctx.companyId,
                    entity_type: 'pos.sale',
                    entity_id: saleId,
                    conflict_type: TYPE.DUPLICATE_SALE_ID,
                    severity: SEVERITY.ERROR,
                    message: 'Duplicate sale local_id: ' + saleId
                }));
            }

            var txn = sale.local_txn_no;
            if (txn && txnCounts[String(txn)] && txnCounts[String(txn)].length > 1) {
                found.push(makeConflict({
                    company_id: ctx.companyId,
                    entity_type: 'pos.sale',
                    entity_id: saleId,
                    conflict_type: TYPE.DUPLICATE_TXN,
                    severity: SEVERITY.ERROR,
                    message: 'Duplicate transaction number: ' + txn,
                    details: { local_txn_no: txn, sale_ids: txnCounts[String(txn)] }
                }));
            }

            var syncKey = sale.sync_key;
            if (syncKey && syncKeyCounts[String(syncKey)] && syncKeyCounts[String(syncKey)].length > 1) {
                found.push(makeConflict({
                    company_id: ctx.companyId,
                    entity_type: 'pos.sale',
                    entity_id: saleId,
                    conflict_type: TYPE.DUPLICATE_SYNC_KEY,
                    severity: SEVERITY.ERROR,
                    message: 'Duplicate sync_key: ' + syncKey,
                    details: { sync_key: syncKey, sale_ids: syncKeyCounts[String(syncKey)] }
                }));
            }

            lines.forEach(function (line, idx) {
                if (!line || !line.product_id) {
                    found.push(makeConflict({
                        company_id: ctx.companyId,
                        entity_type: 'pos.sale',
                        entity_id: saleId,
                        conflict_type: TYPE.MISSING_PRODUCT,
                        severity: SEVERITY.ERROR,
                        message: 'Missing product reference on line ' + idx
                    }));
                    return;
                }
                if (!productById[String(line.product_id)]) {
                    found.push(makeConflict({
                        company_id: ctx.companyId,
                        entity_type: 'pos.sale',
                        entity_id: saleId,
                        conflict_type: TYPE.MISSING_PRODUCT,
                        severity: SEVERITY.ERROR,
                        message: 'Product not found locally: ' + line.product_id,
                        details: { product_id: line.product_id }
                    }));
                }
                var q = qtyNum(line.qty);
                if (!isFinite(q) || q <= 0) {
                    found.push(makeConflict({
                        company_id: ctx.companyId,
                        entity_type: 'pos.sale',
                        entity_id: saleId,
                        conflict_type: TYPE.INVALID_QTY,
                        severity: SEVERITY.ERROR,
                        message: 'Invalid quantity for ' + line.product_id,
                        details: { product_id: line.product_id, qty: line.qty }
                    }));
                }
            });

            var lineSum = 0;
            var qtyOk = true;
            lines.forEach(function (line) {
                var lt = money(line && line.line_total);
                if (!isFinite(lt)) {
                    qtyOk = false;
                } else {
                    lineSum = money(lineSum + lt);
                }
            });
            var saleTotal = money(sale.total);
            if (!isFinite(saleTotal) || !qtyOk || (lines.length && Math.abs(saleTotal - lineSum) > 0.009)) {
                found.push(makeConflict({
                    company_id: ctx.companyId,
                    entity_type: 'pos.sale',
                    entity_id: saleId,
                    conflict_type: TYPE.INVALID_TOTALS,
                    severity: SEVERITY.ERROR,
                    message: 'Invalid totals (sale.total=' + sale.total + ', lines_sum=' + lineSum + ')',
                    details: { sale_total: sale.total, lines_sum: lineSum }
                }));
            }

            rsvList.forEach(function (r) {
                if (String(r.sale_id) !== saleId) {
                    found.push(makeConflict({
                        company_id: ctx.companyId,
                        entity_type: 'pos.stock_reservation',
                        entity_id: r.reservation_id || r.id,
                        conflict_type: TYPE.RESERVATION_MISMATCH,
                        severity: SEVERITY.ERROR,
                        message: 'Reservation sale_id mismatch',
                        details: { expected_sale_id: saleId, reservation_sale_id: r.sale_id }
                    }));
                }
                var rq = qtyNum(r.qty);
                if (!isFinite(rq) || rq <= 0) {
                    found.push(makeConflict({
                        company_id: ctx.companyId,
                        entity_type: 'pos.stock_reservation',
                        entity_id: r.reservation_id || r.id,
                        conflict_type: TYPE.RESERVATION_MISMATCH,
                        severity: SEVERITY.ERROR,
                        message: 'Reservation quantity invalid',
                        details: { qty: r.qty }
                    }));
                }
            });

            if (sale.status === 'CANCELLED') {
                var active = rsvList.filter(function (r) { return r.status === 'ACTIVE'; });
                active.forEach(function (r) {
                    found.push(makeConflict({
                        company_id: ctx.companyId,
                        entity_type: 'pos.sale',
                        entity_id: saleId,
                        conflict_type: TYPE.CANCELLED_ACTIVE_RSV,
                        severity: SEVERITY.ERROR,
                        message: 'Cancelled sale has ACTIVE reservation ' + (r.reservation_id || r.id),
                        details: { reservation_id: r.reservation_id || r.id }
                    }));
                });
            }

            if (sale.status === 'COMPLETED' && (sale.stock_reserved || (sale.reservation_ids || []).length)) {
                var activeDone = rsvList.filter(function (r) { return r.status === 'ACTIVE'; });
                var linkedOk = true;
                (sale.reservation_ids || []).forEach(function (rid) {
                    var hit = rsvList.some(function (r) {
                        return String(r.reservation_id || r.id) === String(rid);
                    });
                    if (!hit) {
                        linkedOk = false;
                    }
                });
                if (!activeDone.length || !linkedOk) {
                    found.push(makeConflict({
                        company_id: ctx.companyId,
                        entity_type: 'pos.sale',
                        entity_id: saleId,
                        conflict_type: TYPE.RESERVATION_MISMATCH,
                        severity: SEVERITY.WARN,
                        message: 'Reservation mismatch for completed sale',
                        details: {
                            active_count: activeDone.length,
                            reservation_ids: sale.reservation_ids || []
                        }
                    }));
                }
            }

            return found;
        }

        function buildContext(companyId, sales, products, reservations) {
            var productById = Object.create(null);
            (products || []).forEach(function (p) {
                if (p && p.id) {
                    productById[String(p.id)] = p;
                }
            });
            var txnCounts = Object.create(null);
            var idCounts = Object.create(null);
            var syncKeyCounts = Object.create(null);
            (sales || []).forEach(function (s) {
                if (!s || !s.id) {
                    return;
                }
                var sid = String(s.id);
                idCounts[sid] = (idCounts[sid] || 0) + 1;
                if (s.local_txn_no) {
                    var t = String(s.local_txn_no);
                    if (!txnCounts[t]) {
                        txnCounts[t] = [];
                    }
                    txnCounts[t].push(sid);
                }
                if (s.sync_key) {
                    var sk = String(s.sync_key);
                    if (!syncKeyCounts[sk]) {
                        syncKeyCounts[sk] = [];
                    }
                    syncKeyCounts[sk].push(sid);
                }
            });
            var rsvBySale = Object.create(null);
            (reservations || []).forEach(function (r) {
                if (!r || !r.sale_id) {
                    return;
                }
                var key = String(r.sale_id);
                if (!rsvBySale[key]) {
                    rsvBySale[key] = [];
                }
                rsvBySale[key].push(r);
            });
            return {
                companyId: companyId,
                productById: productById,
                txnCounts: txnCounts,
                idCounts: idCounts,
                syncKeyCounts: syncKeyCounts,
                rsvBySale: rsvBySale
            };
        }

        function listConflicts(idCtx, filters) {
            filters = filters || {};
            return ensureStore().then(function (store) {
                return listEntity(store, ET.conflict, idCtx.company_id).then(function (rows) {
                    return rows.filter(function (c) {
                        if (filters.status && c.status !== filters.status) {
                            return false;
                        }
                        if (filters.severity && c.severity !== filters.severity) {
                            return false;
                        }
                        return true;
                    }).sort(function (a, b) {
                        return String(b.created_at || '').localeCompare(String(a.created_at || ''));
                    });
                });
            });
        }

        function upsertOpenConflicts(idCtx, detected) {
            return ensureStore().then(function (store) {
                return listEntity(store, ET.conflict, idCtx.company_id).then(function (existing) {
                    var openByKey = Object.create(null);
                    existing.forEach(function (c) {
                        if (c && c.status === STATUS.OPEN) {
                            openByKey[conflictKey(c.entity_type, c.entity_id, c.conflict_type)] = c;
                        }
                    });
                    var chain = Promise.resolve();
                    var written = [];
                    var seen = Object.create(null);

                    detected.forEach(function (c) {
                        var key = conflictKey(c.entity_type, c.entity_id, c.conflict_type);
                        seen[key] = true;
                        var prev = openByKey[key];
                        if (prev) {
                            var next = Object.assign({}, prev, {
                                message: c.message,
                                details: c.details,
                                severity: c.severity,
                                updated_at: nowIso()
                            });
                            chain = chain.then(function () {
                                return store.put(ET.conflict, next.id, next, Number(next.version || 1) + 1)
                                    .then(function () { written.push(next); });
                            });
                        } else {
                            chain = chain.then(function () {
                                return store.put(ET.conflict, c.id, c, 1)
                                    .then(function () {
                                        written.push(c);
                                        return softAudit(idCtx, 'CONFLICT_CREATED', ET.conflict, c.id, {
                                            conflict_type: c.conflict_type,
                                            entity_type: c.entity_type,
                                            entity_id: c.entity_id
                                        });
                                    });
                            });
                        }
                    });

                    return chain.then(function () {
                        return {
                            ok: true,
                            conflicts: written,
                            conflict_count: written.length,
                            open_keys: Object.keys(seen)
                        };
                    });
                });
            });
        }

        /**
         * Scan local sales/reservations and record OPEN conflicts.
         */
        function scanConflicts(idCtx) {
            return ensureStore().then(function (store) {
                return Promise.all([
                    listEntity(store, ET.sale, idCtx.company_id),
                    listEntity(store, ET.product, idCtx.company_id),
                    listEntity(store, ET.reservation, idCtx.company_id)
                ]).then(function (parts) {
                    var sales = parts[0] || [];
                    var products = parts[1] || [];
                    var reservations = parts[2] || [];
                    var ctx = buildContext(idCtx.company_id, sales, products, reservations);
                    var detected = [];
                    sales.forEach(function (sale) {
                        detected = detected.concat(collectSaleConflicts(sale, ctx));
                    });
                    /* Orphan ACTIVE reservations with no sale row */
                    reservations.forEach(function (r) {
                        if (!r || r.status !== 'ACTIVE') {
                            return;
                        }
                        var sale = sales.find(function (s) {
                            return s && String(s.id) === String(r.sale_id);
                        });
                        if (!sale) {
                            detected.push(makeConflict({
                                company_id: idCtx.company_id,
                                entity_type: 'pos.stock_reservation',
                                entity_id: r.reservation_id || r.id,
                                conflict_type: TYPE.RESERVATION_MISMATCH,
                                severity: SEVERITY.ERROR,
                                message: 'ACTIVE reservation without sale: ' + r.sale_id
                            }));
                        }
                    });
                    return upsertOpenConflicts(idCtx, detected).then(function (res) {
                        return listConflicts(idCtx, { status: STATUS.OPEN }).then(function (open) {
                            return {
                                ok: true,
                                detected_count: detected.length,
                                open_count: open.length,
                                conflicts: open,
                                sync_started: false,
                                network: false,
                                inventory_module: false
                            };
                        });
                    });
                });
            });
        }

        /**
         * Evaluate an arbitrary sale-like payload (test/invalid inject) and record conflicts.
         * Does not mutate existing sales.
         */
        function evaluatePayload(idCtx, salePayload, extraReservations) {
            return ensureStore().then(function (store) {
                return Promise.all([
                    listEntity(store, ET.sale, idCtx.company_id),
                    listEntity(store, ET.product, idCtx.company_id),
                    listEntity(store, ET.reservation, idCtx.company_id)
                ]).then(function (parts) {
                    var sales = (parts[0] || []).slice();
                    var products = parts[1] || [];
                    var reservations = (parts[2] || []).concat(extraReservations || []);
                    var probe = Object.assign({}, salePayload, {
                        id: salePayload.id || salePayload.sale_id || uid('sale-probe'),
                        company_id: idCtx.company_id
                    });
                    sales.push(probe);
                    /* extras already concatenated into reservations before buildContext */
                    var ctx = buildContext(idCtx.company_id, sales, products, reservations);
                    var detected = collectSaleConflicts(probe, ctx);
                    return upsertOpenConflicts(idCtx, detected).then(function (res) {
                        return {
                            ok: true,
                            sale_id: probe.id,
                            conflict_count: res.conflict_count,
                            conflicts: res.conflicts,
                            sync_started: false,
                            network: false
                        };
                    });
                });
            });
        }

        function getConflict(idCtx, conflictId) {
            return ensureStore().then(function (store) {
                return store.get(ET.conflict, String(conflictId), idCtx.company_id).then(function (row) {
                    return row && row.payload ? row.payload : null;
                });
            });
        }

        function markReviewed(idCtx, conflictId) {
            return ensureStore().then(function (store) {
                return getConflict(idCtx, conflictId).then(function (c) {
                    if (!c) {
                        return Promise.reject(new Error('pos_conflict_not_found'));
                    }
                    var next = Object.assign({}, c, {
                        status: STATUS.RESOLVED,
                        resolution: 'reviewed',
                        resolved_at: nowIso(),
                        updated_at: nowIso()
                    });
                    return store.put(ET.conflict, next.id, next, Number(next.version || 1) + 1)
                        .then(function () {
                            return softAudit(idCtx, 'CONFLICT_RESOLVED', ET.conflict, next.id, {
                                resolution: 'reviewed'
                            }).then(function () {
                                return { ok: true, conflict: next };
                            });
                        });
                });
            });
        }

        function ignoreConflict(idCtx, conflictId, reason) {
            if (!reason) {
                return Promise.reject(new Error('pos_conflict_audit_reason_required'));
            }
            return ensureStore().then(function (store) {
                return getConflict(idCtx, conflictId).then(function (c) {
                    if (!c) {
                        return Promise.reject(new Error('pos_conflict_not_found'));
                    }
                    var next = Object.assign({}, c, {
                        status: STATUS.RESOLVED,
                        resolution: 'ignored',
                        audit_reason: String(reason),
                        resolved_at: nowIso(),
                        updated_at: nowIso()
                    });
                    return store.put(ET.conflict, next.id, next, Number(next.version || 1) + 1)
                        .then(function () {
                            return softAudit(idCtx, 'CONFLICT_RESOLVED', ET.conflict, next.id, {
                                resolution: 'ignored',
                                audit_reason: String(reason)
                            }).then(function () {
                                return { ok: true, conflict: next };
                            });
                        });
                });
            });
        }

        /**
         * Re-run validation; auto-resolve OPEN conflicts that no longer apply.
         */
        function retryValidation(idCtx) {
            return scanConflicts(idCtx).then(function (scan) {
                return ensureStore().then(function (store) {
                    return listConflicts(idCtx, {}).then(function (all) {
                        var openKeys = Object.create(null);
                        (scan.conflicts || []).forEach(function (c) {
                            openKeys[conflictKey(c.entity_type, c.entity_id, c.conflict_type)] = true;
                        });
                        var chain = Promise.resolve();
                        var autoResolved = [];
                        all.forEach(function (c) {
                            if (!c || c.status !== STATUS.OPEN) {
                                return;
                            }
                            var key = conflictKey(c.entity_type, c.entity_id, c.conflict_type);
                            if (openKeys[key]) {
                                return;
                            }
                            chain = chain.then(function () {
                                var next = Object.assign({}, c, {
                                    status: STATUS.RESOLVED,
                                    resolution: 'retry_cleared',
                                    resolved_at: nowIso(),
                                    updated_at: nowIso()
                                });
                                return store.put(ET.conflict, next.id, next, Number(next.version || 1) + 1)
                                    .then(function () { autoResolved.push(next); });
                            });
                        });
                        return chain.then(function () {
                            return listConflicts(idCtx, { status: STATUS.OPEN }).then(function (open) {
                                return {
                                    ok: true,
                                    open_count: open.length,
                                    auto_resolved_count: autoResolved.length,
                                    auto_resolved: autoResolved,
                                    conflicts: open,
                                    sync_started: false
                                };
                            });
                        });
                    });
                });
            });
        }

        return {
            ET: ET,
            STATUS: STATUS,
            SEVERITY: SEVERITY,
            TYPE: TYPE,
            ensureStore: ensureStore,
            scanConflicts: scanConflicts,
            evaluatePayload: evaluatePayload,
            listConflicts: listConflicts,
            getConflict: getConflict,
            markReviewed: markReviewed,
            ignoreConflict: ignoreConflict,
            retryValidation: retryValidation,
            isStoreOpen: function () { return !!state.store; }
        };
    }

    root.RatebOfflineV2PosConflict = {
        __locked: true,
        create: createPosConflict,
        entityTypes: ET,
        STATUS: STATUS,
        SEVERITY: SEVERITY,
        TYPE: TYPE
    };
})(typeof window !== 'undefined' ? window : this);
