/*!
 * RATEB Offline V2 — POS sync contract preparation (Phase 8–10)
 *
 * Builds/validates outgoing POS sale + reservation payloads locally.
 * Does NOT call APIs, start sync, push outbox, or load Inventory.
 */
(function (root) {
    'use strict';

    var Business = root.RatebOfflineV2Business;
    if (!Business || typeof Business.createDocStore !== 'function') {
        return;
    }

    var ET = {
        prep: 'pos.sync_prep',
        sale: 'pos.sale',
        product: 'pos.product',
        reservation: 'pos.stock_reservation'
    };

    var SYNC_STATUS = {
        PENDING: 'PENDING',
        READY: 'READY',
        INVALID: 'INVALID',
        BLOCKED: 'BLOCKED'
    };

    function nowIso() {
        return new Date().toISOString();
    }

    function qtyNum(n) {
        var v = Number(n);
        if (!isFinite(v)) {
            return NaN;
        }
        return Math.round(v * 1000) / 1000;
    }

    function createPosSyncAdapter(module) {
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
            /* Sync-preview opens DB lazily — not register/activate. */
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

        function readPendingOutbox() {
            var db = module.ctx && module.ctx.db;
            if (!db || typeof db.exec !== 'function') {
                return Promise.resolve([]);
            }
            return db.exec(
                "SELECT client_id, module, action, status, payload_json, created_at " +
                "FROM sync_outbox WHERE module='pos' AND status IN ('pending','retry') " +
                "ORDER BY created_at ASC"
            ).then(function (rows) {
                return (rows || []).map(function (row) {
                    var payload = null;
                    try {
                        payload = JSON.parse(row.payload_json || '{}');
                    } catch (e) {
                        payload = null;
                    }
                    return {
                        client_id: row.client_id,
                        module: row.module,
                        operation: row.action,
                        status: row.status,
                        payload: payload,
                        created_at: row.created_at
                    };
                });
            }).catch(function () {
                return [];
            });
        }

        function resolveDeviceIdentity(idCtx) {
            if (module && typeof module._getDevice === 'function') {
                return module._getDevice().ensureIdentity(idCtx).catch(function () {
                    return { device_uuid: null, installation_id: null };
                });
            }
            return Promise.resolve({ device_uuid: null, installation_id: null });
        }

        function softAudit(idCtx, eventType, entityType, entityId, metadata) {
            try {
                if (module && typeof module._auditEvent === 'function') {
                    return module._auditEvent(idCtx, eventType, entityType, entityId, metadata)
                        .catch(function () { return null; });
                }
            } catch (e) { /* ignore */ }
            return Promise.resolve(null);
        }

        function buildReservationContract(rsv) {
            return {
                reservation_id: rsv.reservation_id || rsv.id,
                sale_id: rsv.sale_id || null,
                product_id: rsv.product_id,
                status: rsv.status,
                quantities: {
                    qty: qtyNum(rsv.qty),
                    reserved_qty: qtyNum(rsv.qty)
                },
                warehouse_id: rsv.warehouse_id || null
            };
        }

        function buildSaleContract(sale, reservations) {
            var lines = (sale.lines || []).map(function (line) {
                return {
                    line_id: line.id,
                    product_id: line.product_id,
                    sku: line.sku || '',
                    name: line.name || '',
                    qty: qtyNum(line.qty),
                    unit_price: Number(line.unit_price || 0),
                    line_total: Number(line.line_total || 0),
                    currency: line.currency || sale.currency || 'SAR'
                };
            });
            var warehouseId = null;
            (reservations || []).forEach(function (r) {
                if (!warehouseId && r.warehouse_id) {
                    warehouseId = r.warehouse_id;
                }
            });
            if (!warehouseId && sale.warehouse_id) {
                warehouseId = sale.warehouse_id;
            }
            return {
                entity: 'pos.sale',
                sale_id: sale.id,
                local_txn_no: sale.local_txn_no || null,
                sync_key: sale.sync_key || null,
                status: sale.status,
                sync_status: sale.sync_status || 'SYNC_PENDING',
                lines: lines,
                totals: {
                    line_count: sale.line_count != null ? sale.line_count : lines.length,
                    subtotal: Number(sale.subtotal || 0),
                    total: Number(sale.total || 0),
                    currency: sale.currency || 'SAR'
                },
                warehouse_id: warehouseId,
                reservations: (reservations || []).map(buildReservationContract),
                company_id: sale.company_id,
                completed_at: sale.completed_at || null,
                device_id: sale.device_id || null
            };
        }

        function conflictMetadata(sale, deviceId, syncStatus) {
            return {
                local_id: String(sale.id),
                created_at: sale.created_at || sale.completed_at || nowIso(),
                device_id: deviceId || sale.device_id || null,
                sync_key: sale.sync_key || null,
                sync_status: syncStatus
            };
        }

        function validateSaleContract(sale, contract, productById, txnIndex, reservations) {
            var errors = [];
            if (!sale || sale.status !== 'COMPLETED') {
                errors.push({ code: 'pos_sync_sale_not_completed', message: 'Sale must be COMPLETED' });
            }
            if (!contract.lines || !contract.lines.length) {
                errors.push({ code: 'pos_sync_sale_no_lines', message: 'Sale has no lines' });
            }
            (contract.lines || []).forEach(function (line, idx) {
                if (!line.product_id) {
                    errors.push({ code: 'pos_sync_line_product_missing', message: 'Line ' + idx + ' missing product_id' });
                    return;
                }
                if (!productById[String(line.product_id)]) {
                    errors.push({
                        code: 'pos_sync_product_not_local',
                        message: 'Product not found locally: ' + line.product_id
                    });
                }
                var q = qtyNum(line.qty);
                if (!isFinite(q) || q <= 0) {
                    errors.push({
                        code: 'pos_sync_qty_invalid',
                        message: 'Invalid quantity for ' + line.product_id
                    });
                }
            });
            var txn = sale.local_txn_no || contract.local_txn_no;
            if (txn && txnIndex[String(txn)] && txnIndex[String(txn)] !== String(sale.id)) {
                errors.push({
                    code: 'pos_sync_duplicate_txn',
                    message: 'Duplicate local transaction id: ' + txn
                });
            }
            (reservations || []).forEach(function (r) {
                if (!r) {
                    return;
                }
                if (r.status !== 'ACTIVE' && r.status !== 'RELEASED') {
                    errors.push({
                        code: 'pos_sync_reservation_state_invalid',
                        message: 'Invalid reservation status for ' + (r.reservation_id || r.id)
                    });
                }
                var rq = qtyNum(r.qty);
                if (!isFinite(rq) || rq <= 0) {
                    errors.push({
                        code: 'pos_sync_reservation_qty_invalid',
                        message: 'Invalid reservation qty for ' + (r.reservation_id || r.id)
                    });
                }
                if (String(r.sale_id) !== String(sale.id)) {
                    errors.push({
                        code: 'pos_sync_reservation_sale_mismatch',
                        message: 'Reservation sale_id mismatch'
                    });
                }
            });
            /* Prefer ACTIVE reservations for outgoing; RELEASED-only is warned not blocking if sale cancel. */
            if (sale.status === 'COMPLETED') {
                var active = (reservations || []).filter(function (r) { return r.status === 'ACTIVE'; });
                if (!active.length && (sale.stock_reserved || (sale.reservation_ids || []).length)) {
                    errors.push({
                        code: 'pos_sync_reservation_missing_active',
                        message: 'Completed reserved sale has no ACTIVE reservations'
                    });
                }
            }
            return errors;
        }

        function buildTxnIndex(sales) {
            var idx = Object.create(null);
            (sales || []).forEach(function (s) {
                if (!s || !s.local_txn_no) {
                    return;
                }
                var key = String(s.local_txn_no);
                if (!idx[key]) {
                    idx[key] = String(s.id);
                } else if (idx[key] !== String(s.id)) {
                    idx[key + '::__dup__' + s.id] = String(s.id);
                    /* mark collision by pointing first to a sentinel list via second pass in validate */
                }
            });
            /* Rebuild with collision detection map saleId -> conflicting */
            var counts = Object.create(null);
            (sales || []).forEach(function (s) {
                if (!s || !s.local_txn_no) {
                    return;
                }
                var key = String(s.local_txn_no);
                if (!counts[key]) {
                    counts[key] = [];
                }
                counts[key].push(String(s.id));
            });
            var map = Object.create(null);
            Object.keys(counts).forEach(function (key) {
                if (counts[key].length === 1) {
                    map[key] = counts[key][0];
                } else {
                    /* Any sale with this txn will see another id */
                    counts[key].forEach(function (sid) {
                        var other = counts[key].filter(function (x) { return x !== sid; })[0];
                        map[key + '::' + sid] = other;
                    });
                    map[key] = counts[key][0];
                    map[key + '::__collision__'] = true;
                }
            });
            return { map: map, collisions: counts };
        }

        function validateWithTxn(sale, contract, productById, txnInfo, reservations) {
            var errors = validateSaleContract(sale, contract, productById, txnInfo.map, reservations);
            var txn = sale.local_txn_no;
            if (txn && txnInfo.collisions[String(txn)] && txnInfo.collisions[String(txn)].length > 1) {
                var already = errors.some(function (e) { return e.code === 'pos_sync_duplicate_txn'; });
                if (!already) {
                    errors.push({
                        code: 'pos_sync_duplicate_txn',
                        message: 'Duplicate local transaction id: ' + txn
                    });
                }
            }
            return errors;
        }

        /**
         * Prepare local sync preview — no network, no sync.start(), no outbox push.
         */
        function preparePreview(idCtx) {
            return ensureStore().then(function (store) {
                return Promise.all([
                    listEntity(store, ET.sale, idCtx.company_id),
                    listEntity(store, ET.product, idCtx.company_id),
                    listEntity(store, ET.reservation, idCtx.company_id),
                    readPendingOutbox(),
                    resolveDeviceIdentity(idCtx)
                ]).then(function (parts) {
                    var sales = parts[0] || [];
                    var products = parts[1] || [];
                    var reservations = parts[2] || [];
                    var outbox = parts[3] || [];
                    var device = parts[4] || {};
                    var productById = Object.create(null);
                    products.forEach(function (p) {
                        if (p && p.id) {
                            productById[String(p.id)] = p;
                        }
                    });
                    var rsvBySale = Object.create(null);
                    reservations.forEach(function (r) {
                        if (!r || !r.sale_id) {
                            return;
                        }
                        var key = String(r.sale_id);
                        if (!rsvBySale[key]) {
                            rsvBySale[key] = [];
                        }
                        rsvBySale[key].push(r);
                    });
                    var pendingSales = sales.filter(function (s) {
                        return s && s.status === 'COMPLETED' &&
                            s.synced !== true &&
                            s.sync_status !== 'VALIDATED';
                    });
                    var txnInfo = buildTxnIndex(pendingSales);
                    var items = [];
                    var chain = Promise.resolve();
                    var deviceId = device.device_uuid || null;

                    pendingSales.forEach(function (sale) {
                        chain = chain.then(function () {
                            var saleRsv = rsvBySale[String(sale.id)] || [];
                            var contract = buildSaleContract(sale, saleRsv);
                            if (!contract.device_id && deviceId) {
                                contract.device_id = deviceId;
                            }
                            var errors = validateWithTxn(sale, contract, productById, txnInfo, saleRsv);
                            if (!sale.sync_key) {
                                errors.push({
                                    code: 'pos_sync_key_missing',
                                    message: 'Sale missing sync_key'
                                });
                            }
                            var syncStatus = errors.length ? SYNC_STATUS.INVALID : SYNC_STATUS.READY;
                            var meta = conflictMetadata(sale, deviceId, syncStatus);
                            var prep = {
                                id: String(sale.id),
                                company_id: idCtx.company_id,
                                sale_id: String(sale.id),
                                local_id: meta.local_id,
                                sync_key: sale.sync_key || null,
                                created_at: meta.created_at,
                                device_id: meta.device_id,
                                sync_status: syncStatus,
                                sale_sync_status: sale.sync_status || 'SYNC_PENDING',
                                validation_errors: errors,
                                outgoing: {
                                    sale: contract,
                                    reservations: contract.reservations
                                },
                                outbox_pending: outbox.some(function (o) {
                                    var eid = o.payload && (o.payload.entity_id || (o.payload.data && o.payload.data.id));
                                    return String(eid) === String(sale.id) ||
                                        (o.payload && o.payload.data && o.payload.data.local_txn_no === sale.local_txn_no);
                                }),
                                prepared_at: nowIso(),
                                network: false,
                                sync_started: false,
                                version: 1
                            };
                            items.push({
                                sale_id: sale.id,
                                local_txn_no: sale.local_txn_no,
                                sync_key: sale.sync_key || null,
                                sync_status: syncStatus,
                                validation_errors: errors,
                                outgoing: prep.outgoing,
                                conflict_metadata: meta,
                                outbox_pending: prep.outbox_pending
                            });
                            return store.put(ET.prep, prep.id, prep, 1).then(function () {
                                return softAudit(idCtx, 'SYNC_PREPARED', ET.prep, prep.id, {
                                    sale_id: sale.id,
                                    sync_key: sale.sync_key || null,
                                    sync_status: syncStatus
                                });
                            });
                        });
                    });

                    return chain.then(function () {
                        var invalid = items.filter(function (i) { return i.sync_status === SYNC_STATUS.INVALID; });
                        var ready = items.filter(function (i) { return i.sync_status === SYNC_STATUS.READY; });
                        return {
                            ok: true,
                            prepared_at: nowIso(),
                            pending_sales_count: pendingSales.length,
                            ready_count: ready.length,
                            invalid_count: invalid.length,
                            outbox_pending_count: outbox.length,
                            device_id: deviceId,
                            outbox: outbox.map(function (o) {
                                return {
                                    client_id: o.client_id,
                                    operation: o.operation,
                                    status: o.status
                                };
                            }),
                            items: items,
                            sync_started: false,
                            network: false,
                            pushed: false,
                            inventory_module: false
                        };
                    });
                });
            });
        }

        function getPrep(idCtx, saleId) {
            return ensureStore().then(function (store) {
                return store.get(ET.prep, String(saleId), idCtx.company_id).then(function (row) {
                    return row && row.payload ? row.payload : null;
                });
            });
        }

        return {
            ET: ET,
            SYNC_STATUS: SYNC_STATUS,
            ensureStore: ensureStore,
            preparePreview: preparePreview,
            getPrep: getPrep,
            buildSaleContract: buildSaleContract,
            buildReservationContract: buildReservationContract,
            isStoreOpen: function () { return !!state.store; }
        };
    }

    root.RatebOfflineV2PosSyncAdapter = {
        __locked: true,
        create: createPosSyncAdapter,
        entityTypes: ET,
        SYNC_STATUS: SYNC_STATUS
    };
})(typeof window !== 'undefined' ? window : this);
