/*!
 * RATEB Offline V2 — POS controlled sync gateway (Phase 11–14.1)
 *
 * Manual pipeline: Prepare → Validate → Accept → Commit → COMMITTED.
 * Never starts sync engine, SW push, or boot sync.
 */
(function (root) {
    'use strict';

    var Business = root.RatebOfflineV2Business;
    if (!Business || typeof Business.createDocStore !== 'function') {
        return;
    }

    var ET = {
        sale: 'pos.sale',
        reservation: 'pos.stock_reservation',
        meta: 'pos.sync_center_meta'
    };

    var MODE = 'ACCEPT_COMMIT';
    var VALIDATE_URL = '/rateb-erp/api/v1/pos/sync/validate';
    var ACCEPT_URL = '/rateb-erp/api/v1/pos/sync/accept';
    var COMMIT_URL = '/rateb-erp/api/v1/pos/sync/commit';

    var SYNC_STATUS = {
        SYNC_PENDING: 'SYNC_PENDING',
        VALIDATING: 'VALIDATING',
        VALIDATED: 'VALIDATED',
        SERVER_ACCEPTED: 'SERVER_ACCEPTED',
        COMMITTED: 'COMMITTED',
        COMMIT_FAILED: 'COMMIT_FAILED',
        REJECTED: 'REJECTED'
    };

    var ALLOWED = {
        SYNC_PENDING: { VALIDATING: true },
        VALIDATING: {
            VALIDATED: true,
            REJECTED: true,
            SYNC_PENDING: true,
            SERVER_ACCEPTED: true
        },
        VALIDATED: { VALIDATING: true, SERVER_ACCEPTED: true },
        SERVER_ACCEPTED: { COMMITTED: true, COMMIT_FAILED: true },
        COMMIT_FAILED: { COMMITTED: true, COMMIT_FAILED: true },
        COMMITTED: {},
        REJECTED: { SYNC_PENDING: true, VALIDATING: true }
    };

    function nowIso() {
        return new Date().toISOString();
    }

    function createPosSyncGateway(module) {
        var state = {
            store: null,
            apiHits: [],
            transport: null,
            commitTransport: null,
            bearerToken: null,
            forceOffline: false,
            lastValidate: null
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

        function softAudit(idCtx, eventType, entityType, entityId, metadata) {
            try {
                if (module && typeof module._auditEvent === 'function') {
                    return module._auditEvent(idCtx, eventType, entityType, entityId, metadata)
                        .catch(function () { return null; });
                }
            } catch (e) { /* ignore */ }
            return Promise.resolve(null);
        }

        function assertSyncTransition(from, to) {
            var allowed = ALLOWED[from] || {};
            if (!allowed[to]) {
                return Promise.reject(new Error('pos_invalid_sync_transition:' + from + '->' + to));
            }
            return Promise.resolve();
        }

        function isOnline() {
            if (state.forceOffline) {
                return false;
            }
            if (typeof root.navigator !== 'undefined' && root.navigator.onLine === false) {
                return false;
            }
            return true;
        }

        function setBearerToken(token) {
            /* Memory-only — never persisted to Identity storage. */
            state.bearerToken = token ? String(token) : null;
        }

        function setForceOffline(flag) {
            state.forceOffline = !!flag;
        }

        function setTransport(fn) {
            state.transport = typeof fn === 'function' ? fn : null;
        }

        function getApiHits() {
            return state.apiHits.slice();
        }

        function clearApiHits() {
            state.apiHits = [];
        }

        function readMeta(idCtx) {
            return ensureStore().then(function (store) {
                return store.get(ET.meta, 'center', idCtx.company_id).then(function (row) {
                    return row && row.payload ? row.payload : {
                        id: 'center',
                        company_id: idCtx.company_id,
                        last_sync_at: null,
                        last_prepare_at: null,
                        last_validate_at: null,
                        last_validate_ok: null,
                        mode: MODE,
                        version: 1
                    };
                });
            });
        }

        function writeMeta(idCtx, patch) {
            return ensureStore().then(function (store) {
                return readMeta(idCtx).then(function (meta) {
                    var next = Object.assign({}, meta, patch, {
                        id: 'center',
                        company_id: idCtx.company_id,
                        updated_at: nowIso()
                    });
                    return store.put(ET.meta, 'center', next, Number(next.version || 1) + 1)
                        .then(function () { return next; });
                });
            });
        }

        function getSale(idCtx, saleId) {
            return ensureStore().then(function (store) {
                return store.get(ET.sale, String(saleId), idCtx.company_id).then(function (row) {
                    return row && row.payload ? row.payload : null;
                });
            });
        }

        function listSales(idCtx) {
            return ensureStore().then(function (store) {
                return store.list(ET.sale, idCtx.company_id).then(function (rows) {
                    return (rows || []).map(function (r) { return r.payload; }).filter(Boolean);
                });
            });
        }

        function listReservationsForSale(idCtx, saleId) {
            return ensureStore().then(function (store) {
                return store.list(ET.reservation, idCtx.company_id).then(function (rows) {
                    return (rows || []).map(function (r) { return r.payload; }).filter(function (r) {
                        return r && String(r.sale_id) === String(saleId);
                    });
                });
            });
        }

        function updateSaleSyncStatus(idCtx, saleId, toStatus, extra) {
            return getSale(idCtx, saleId).then(function (sale) {
                if (!sale) {
                    return Promise.reject(new Error('pos_sale_not_found'));
                }
                var from = sale.sync_status || SYNC_STATUS.SYNC_PENDING;
                return assertSyncTransition(from, toStatus).then(function () {
                    return ensureStore().then(function (store) {
                        var patch = extra || {};
                        var synced = patch.synced != null
                            ? !!patch.synced
                            : (toStatus === SYNC_STATUS.COMMITTED);
                        var next = Object.assign({}, sale, patch, {
                            sync_status: toStatus,
                            synced: synced,
                            updated_at: nowIso()
                        });
                        return store.put(ET.sale, next.id, next, Number(next.version || 1) + 1)
                            .then(function () { return next; });
                    });
                });
            });
        }

        /**
         * POS warehouse resolution — sale / identity / line stock warehouse.
         * Does NOT infer from reservations.
         */
        function resolveWarehouseId(sale) {
            var candidates = [
                sale && sale.warehouse_id,
                sale && sale.metadata && sale.metadata.warehouse_id
            ];
            var lines = (sale && sale.lines) || [];
            for (var i = 0; i < lines.length; i++) {
                candidates.push(lines[i] && lines[i].warehouse_id);
            }
            for (var j = 0; j < candidates.length; j++) {
                var n = Number(candidates[j] || 0);
                if (n > 0) {
                    return n;
                }
            }
            return null;
        }

        function resolveTerminalId(sale) {
            var n = Number((sale && sale.terminal_id) ||
                (sale && sale.metadata && sale.metadata.terminal_id) || 0);
            return n > 0 ? n : null;
        }

        function resolveShiftId(sale) {
            var n = Number((sale && sale.shift_id) ||
                (sale && sale.metadata && sale.metadata.shift_id) || 0);
            return n > 0 ? n : null;
        }

        function buildPayload(sale, device, reservations) {
            var lines = (sale.lines || []).map(function (line) {
                return {
                    line_id: line.id,
                    product_id: line.product_id,
                    sku: line.sku || '',
                    name: line.name || '',
                    qty: Number(line.qty || 0),
                    unit_price: Number(line.unit_price || 0),
                    line_total: Number(line.line_total || 0),
                    currency: line.currency || sale.currency || 'SAR'
                };
            });
            var warehouseId = resolveWarehouseId(sale);
            var terminalId = resolveTerminalId(sale);
            var shiftId = resolveShiftId(sale);
            var branchId = Number(sale.branch_id || 0) || 0;
            var payload = {
                device_id: (device && device.device_uuid) || sale.device_id || null,
                installation_id: (device && device.installation_id) || sale.installation_id || null,
                sync_key: sale.sync_key || null,
                sale_id: sale.id,
                created_at: sale.created_at || sale.completed_at || nowIso(),
                customer: sale.customer || null,
                branch_id: branchId || null,
                warehouse_id: warehouseId,
                terminal_id: terminalId,
                shift_id: shiftId,
                lines: lines,
                totals: {
                    line_count: sale.line_count != null ? sale.line_count : lines.length,
                    subtotal: Number(sale.subtotal || 0),
                    total: Number(sale.total || 0),
                    currency: sale.currency || 'SAR'
                },
                reservations: (reservations || []).map(function (r) {
                    return {
                        reservation_id: r.reservation_id || r.id,
                        product_id: r.product_id,
                        qty: Number(r.qty || 0),
                        status: r.status,
                        warehouse_id: r.warehouse_id || null
                    };
                }),
                metadata: {
                    local_txn_no: sale.local_txn_no || null,
                    company_id: sale.company_id,
                    branch_id: branchId,
                    warehouse_id: warehouseId,
                    terminal_id: terminalId,
                    shift_id: shiftId,
                    mode: MODE,
                    source: 'pos_offline_v2'
                }
            };
            return payload;
        }

        function validateLocalContract(payload) {
            var errors = [];
            if (!payload.device_id) {
                errors.push({ code: 'missing_device_id', message: 'device_id required' });
            }
            if (!payload.sync_key) {
                errors.push({ code: 'missing_sync_key', message: 'sync_key required' });
            }
            if (!payload.sale_id) {
                errors.push({ code: 'missing_sale_id', message: 'sale_id required' });
            }
            if (!payload.created_at) {
                errors.push({ code: 'missing_created_at', message: 'created_at required' });
            }
            if (!payload.lines || !payload.lines.length) {
                errors.push({ code: 'missing_lines', message: 'lines required' });
            } else {
                payload.lines.forEach(function (line, idx) {
                    if (!line.product_id) {
                        errors.push({ code: 'missing_product_id', message: 'line ' + idx });
                    }
                    if (!(Number(line.qty) > 0)) {
                        errors.push({ code: 'invalid_qty', message: 'line ' + idx });
                    }
                });
            }
            if (!payload.totals || !isFinite(Number(payload.totals.total))) {
                errors.push({ code: 'invalid_totals', message: 'totals.total required' });
            }
            if (!(Number(payload.warehouse_id || 0) > 0) &&
                !(Number(payload.metadata && payload.metadata.warehouse_id || 0) > 0)) {
                errors.push({ code: 'missing_warehouse_id', message: 'warehouse_id required' });
            }
            if (!(Number(payload.branch_id || 0) > 0) &&
                !(Number(payload.metadata && payload.metadata.branch_id || 0) > 0)) {
                errors.push({ code: 'missing_branch_id', message: 'branch_id required' });
            }
            return errors;
        }

        function defaultTransport(url, payload, bearer) {
            var headers = {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            };
            if (bearer) {
                headers.Authorization = 'Bearer ' + bearer;
            }
            return fetch(url, {
                method: 'POST',
                headers: headers,
                credentials: 'omit',
                body: JSON.stringify({ payload: payload })
            }).then(function (res) {
                return res.json().catch(function () {
                    return { ok: false, accepted: false, conflicts: [{ code: 'bad_json', message: 'Invalid JSON' }] };
                }).then(function (body) {
                    return {
                        http_status: res.status,
                        body: body
                    };
                });
            });
        }

        function defaultCommitTransport(url, body, bearer) {
            var headers = {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            };
            if (bearer) {
                headers.Authorization = 'Bearer ' + bearer;
            }
            return fetch(url, {
                method: 'POST',
                headers: headers,
                credentials: 'omit',
                body: JSON.stringify(body || {})
            }).then(function (res) {
                return res.json().catch(function () {
                    return { ok: false, accepted: false, error: 'bad_json' };
                }).then(function (parsed) {
                    return {
                        http_status: res.status,
                        body: parsed
                    };
                });
            });
        }

        function callApi(url, payload) {
            var hit = {
                at: nowIso(),
                url: url,
                method: 'POST',
                mode: MODE
            };
            state.apiHits.push(hit);
            var transport = state.transport || defaultTransport;
            return Promise.resolve()
                .then(function () {
                    return transport(url, payload, state.bearerToken);
                })
                .then(function (res) {
                    hit.http_status = res && res.http_status;
                    hit.ok = !!(res && res.body && (res.body.accepted === true || res.body.ok === true));
                    return res;
                });
        }

        function callValidateApi(payload) {
            return callApi(VALIDATE_URL, payload);
        }

        function callAcceptApi(payload) {
            return callApi(ACCEPT_URL, payload);
        }

        function callCommitApi(body) {
            var hit = {
                at: nowIso(),
                url: COMMIT_URL,
                method: 'POST',
                mode: MODE,
                action: 'commit'
            };
            state.apiHits.push(hit);
            /* Commit body is not {payload:...} — do not reuse accept transport. */
            var transport = state.commitTransport || defaultCommitTransport;
            return Promise.resolve()
                .then(function () {
                    return transport(COMMIT_URL, body, state.bearerToken);
                })
                .then(function (res) {
                    hit.http_status = res && res.http_status;
                    hit.ok = !!(res && res.body && (res.body.ok === true || res.body.accepted === true));
                    return res;
                });
        }

        /**
         * Manual Prepare Sync — local only, no network.
         */
        function prepareSync(idCtx) {
            return module._getSyncAdapter().preparePreview(idCtx).then(function (preview) {
                return writeMeta(idCtx, {
                    last_prepare_at: nowIso(),
                    pending_sales_count: preview.pending_sales_count || 0
                }).then(function (meta) {
                    return softAudit(idCtx, 'SYNC_PREPARED', ET.meta, 'center', {
                        pending_sales_count: preview.pending_sales_count || 0,
                        mode: MODE
                    }).then(function () {
                        return {
                            ok: true,
                            prepared: true,
                            preview: preview,
                            meta: meta,
                            network: false,
                            sync_started: false,
                            mode: MODE
                        };
                    });
                });
            });
        }

        /**
         * Manual Validate Online — conflict gate → local contract → dry-run API.
         */
        function validateOnline(idCtx, saleId, opts) {
            opts = opts || {};
            if (!saleId) {
                return Promise.reject(new Error('pos_sale_id_required'));
            }
            if (!isOnline() && !state.transport) {
                return softAudit(idCtx, 'SYNC_VALIDATION_FAILED', ET.sale, saleId, {
                    reason: 'offline',
                    mode: MODE
                }).then(function () {
                    return {
                        ok: false,
                        offline: true,
                        accepted: false,
                        sync_status: SYNC_STATUS.SYNC_PENDING,
                        api_called: false,
                        sync_started: false,
                        mode: MODE
                    };
                });
            }

            return softAudit(idCtx, 'SYNC_VALIDATION_STARTED', ET.sale, saleId, {
                mode: MODE
            }).then(function () {
                return Promise.all([
                    module._getConflict().scanConflicts(idCtx),
                    getSale(idCtx, saleId),
                    module._getDevice().ensureIdentity(idCtx),
                    listReservationsForSale(idCtx, saleId)
                ]);
            }).then(function (parts) {
                var scan = parts[0];
                var sale = parts[1];
                var device = parts[2];
                var reservations = parts[3] || [];
                if (!sale) {
                    return Promise.reject(new Error('pos_sale_not_found'));
                }
                if (sale.status !== 'COMPLETED') {
                    return Promise.reject(new Error('pos_sale_not_completed'));
                }
                var saleSyncKey = sale.sync_key ? String(sale.sync_key) : '';
                var blocking = (scan.conflicts || []).filter(function (c) {
                    if (!c || c.severity !== 'ERROR') {
                        return false;
                    }
                    if (String(c.entity_id) === String(saleId)) {
                        return true;
                    }
                    if (c.conflict_type === 'duplicate_sync_key' && saleSyncKey &&
                        c.details && String(c.details.sync_key || '') === saleSyncKey) {
                        return true;
                    }
                    if (c.details && Array.isArray(c.details.sale_ids) &&
                        c.details.sale_ids.map(String).indexOf(String(saleId)) !== -1) {
                        return true;
                    }
                    return false;
                });
                if (blocking.length) {
                    return softAudit(idCtx, 'SYNC_VALIDATION_FAILED', ET.sale, saleId, {
                        reason: 'local_conflicts',
                        conflict_count: blocking.length,
                        mode: MODE
                    }).then(function () {
                        return {
                            ok: false,
                            accepted: false,
                            stopped: true,
                            reason: 'local_conflicts',
                            conflicts: blocking,
                            api_called: false,
                            sync_started: false,
                            mode: MODE
                        };
                    });
                }

                return (function () {
                    var from = sale.sync_status || SYNC_STATUS.SYNC_PENDING;
                    if (from === SYNC_STATUS.VALIDATED) {
                        return {
                            ok: true,
                            accepted: true,
                            already_validated: true,
                            sale: sale,
                            sync_status: SYNC_STATUS.VALIDATED,
                            api_called: false,
                            sync_started: false,
                            mode: MODE
                        };
                    }
                    var payload = buildPayload(sale, device, reservations);
                    var localErrors = validateLocalContract(payload);
                    if (localErrors.length) {
                        var rejectP = from === SYNC_STATUS.REJECTED
                            ? Promise.resolve(sale)
                            : (from === SYNC_STATUS.VALIDATING
                                ? updateSaleSyncStatus(idCtx, saleId, SYNC_STATUS.REJECTED, {
                                    last_validate_errors: localErrors,
                                    last_validate_at: nowIso()
                                })
                                : updateSaleSyncStatus(idCtx, saleId, SYNC_STATUS.VALIDATING, {
                                    last_validate_started_at: nowIso()
                                }).then(function () {
                                    return updateSaleSyncStatus(idCtx, saleId, SYNC_STATUS.REJECTED, {
                                        last_validate_errors: localErrors,
                                        last_validate_at: nowIso()
                                    });
                                }));
                        return rejectP.then(function (updated) {
                            return softAudit(idCtx, 'SYNC_VALIDATION_FAILED', ET.sale, saleId, {
                                reason: 'local_contract',
                                errors: localErrors,
                                mode: MODE
                            }).then(function () {
                                return {
                                    ok: false,
                                    accepted: false,
                                    reason: 'local_contract',
                                    conflicts: localErrors,
                                    sale: updated,
                                    sync_status: SYNC_STATUS.REJECTED,
                                    api_called: false,
                                    sync_started: false,
                                    mode: MODE
                                };
                            });
                        });
                    }

                    var setValidating;
                    if (from === SYNC_STATUS.VALIDATING) {
                        setValidating = Promise.resolve(sale);
                    } else if (from === SYNC_STATUS.SYNC_PENDING || from === SYNC_STATUS.REJECTED) {
                        setValidating = updateSaleSyncStatus(idCtx, saleId, SYNC_STATUS.VALIDATING, {
                            last_validate_started_at: nowIso()
                        });
                    } else {
                        return Promise.reject(new Error('pos_invalid_sync_transition:' + from + '->VALIDATING'));
                    }

                    return setValidating.then(function () {
                        return callValidateApi(payload).then(function (res) {
                            var body = (res && res.body) || {};
                            var accepted = body.accepted === true;
                            var httpOk = res && res.http_status >= 200 && res.http_status < 300;
                            var nextStatus = accepted && httpOk
                                ? SYNC_STATUS.VALIDATED
                                : SYNC_STATUS.REJECTED;
                            /* Auth/network failures stay SYNC_PENDING (retryable). */
                            if (!httpOk && (res.http_status === 0 || res.http_status === 401 ||
                                res.http_status === 408 || res.http_status >= 500 ||
                                body.network_error)) {
                                nextStatus = SYNC_STATUS.SYNC_PENDING;
                            }
                            return updateSaleSyncStatus(idCtx, saleId, nextStatus, {
                                last_validate_at: nowIso(),
                                last_validate_ok: accepted && httpOk,
                                last_validate_response: {
                                    accepted: accepted,
                                    conflicts: body.conflicts || [],
                                    warnings: body.warnings || [],
                                    http_status: res.http_status,
                                    dry_run: true
                                }
                            }).then(function (updated) {
                                var auditType = (accepted && httpOk)
                                    ? 'SYNC_VALIDATION_SUCCESS'
                                    : 'SYNC_VALIDATION_FAILED';
                                return softAudit(idCtx, auditType, ET.sale, saleId, {
                                    accepted: accepted,
                                    http_status: res.http_status,
                                    sync_status: nextStatus,
                                    mode: MODE
                                }).then(function () {
                                    return writeMeta(idCtx, {
                                        last_validate_at: nowIso(),
                                        last_validate_ok: accepted && httpOk,
                                        last_sync_at: nowIso()
                                    }).then(function (meta) {
                                        state.lastValidate = {
                                            sale_id: saleId,
                                            accepted: accepted && httpOk,
                                            sync_status: nextStatus,
                                            at: nowIso()
                                        };
                                        return {
                                            ok: accepted && httpOk,
                                            accepted: accepted && httpOk,
                                            conflicts: body.conflicts || [],
                                            warnings: body.warnings || [],
                                            sale: updated,
                                            sync_status: nextStatus,
                                            payload: payload,
                                            api_called: true,
                                            http_status: res.http_status,
                                            dry_run: true,
                                            sync_started: false,
                                            inventory_deducted: false,
                                            accounting_posted: false,
                                            mode: MODE,
                                            meta: meta
                                        };
                                    });
                                });
                            });
                        }, function (err) {
                            /* Network failure → remain SYNC_PENDING */
                            return updateSaleSyncStatus(idCtx, saleId, SYNC_STATUS.SYNC_PENDING, {
                                last_validate_error: String(err && err.message ? err.message : err),
                                last_validate_at: nowIso()
                            }).then(function (updated) {
                                return softAudit(idCtx, 'SYNC_VALIDATION_FAILED', ET.sale, saleId, {
                                    reason: 'network',
                                    error: String(err && err.message ? err.message : err),
                                    mode: MODE
                                }).then(function () {
                                    return {
                                        ok: false,
                                        accepted: false,
                                        reason: 'network',
                                        sale: updated,
                                        sync_status: SYNC_STATUS.SYNC_PENDING,
                                        api_called: true,
                                        network_error: true,
                                        sync_started: false,
                                        mode: MODE
                                    };
                                });
                            });
                        });
                    });
                })();
            });
        }

        /**
         * Manual Accept Sync — POST /sync/accept → SERVER_ACCEPTED / WAITING_COMMIT.
         * Network/5xx/validation failures return to SYNC_PENDING (no data loss).
         */
        function acceptOnline(idCtx, saleId) {
            if (!saleId) {
                return Promise.reject(new Error('pos_sale_id_required'));
            }
            if (!isOnline() && !state.transport) {
                return softAudit(idCtx, 'SYNC_VALIDATION_FAILED', ET.sale, saleId, {
                    reason: 'offline',
                    action: 'accept',
                    mode: MODE
                }).then(function () {
                    return {
                        ok: false,
                        offline: true,
                        accepted: false,
                        waiting_commit: false,
                        sync_status: SYNC_STATUS.SYNC_PENDING,
                        api_called: false,
                        sync_started: false,
                        mode: MODE
                    };
                });
            }

            return softAudit(idCtx, 'SYNC_VALIDATION_STARTED', ET.sale, saleId, {
                action: 'accept',
                mode: MODE
            }).then(function () {
                return Promise.all([
                    module._getConflict().scanConflicts(idCtx),
                    getSale(idCtx, saleId),
                    module._getDevice().ensureIdentity(idCtx),
                    listReservationsForSale(idCtx, saleId)
                ]);
            }).then(function (parts) {
                var scan = parts[0];
                var sale = parts[1];
                var device = parts[2];
                var reservations = parts[3] || [];
                if (!sale) {
                    return Promise.reject(new Error('pos_sale_not_found'));
                }
                if (sale.status !== 'COMPLETED') {
                    return Promise.reject(new Error('pos_sale_not_completed'));
                }
                if (sale.sync_status === SYNC_STATUS.SERVER_ACCEPTED && sale.server_sync_id) {
                    return {
                        ok: true,
                        accepted: true,
                        already_processed: true,
                        server_sync_id: sale.server_sync_id,
                        waiting_commit: true,
                        sync_status: SYNC_STATUS.SERVER_ACCEPTED,
                        api_called: false,
                        sync_started: false,
                        mode: MODE
                    };
                }
                var saleSyncKey = sale.sync_key ? String(sale.sync_key) : '';
                var blocking = (scan.conflicts || []).filter(function (c) {
                    if (!c || c.severity !== 'ERROR') {
                        return false;
                    }
                    if (String(c.entity_id) === String(saleId)) {
                        return true;
                    }
                    if (c.conflict_type === 'duplicate_sync_key' && saleSyncKey &&
                        c.details && String(c.details.sync_key || '') === saleSyncKey) {
                        return true;
                    }
                    return false;
                });
                if (blocking.length) {
                    return softAudit(idCtx, 'SYNC_VALIDATION_FAILED', ET.sale, saleId, {
                        reason: 'local_conflicts',
                        action: 'accept',
                        mode: MODE
                    }).then(function () {
                        return updateSaleSyncStatus(idCtx, saleId, SYNC_STATUS.SYNC_PENDING, {
                            last_accept_error: 'local_conflicts'
                        }).catch(function () {
                            return sale;
                        }).then(function (updated) {
                            return {
                                ok: false,
                                accepted: false,
                                stopped: true,
                                reason: 'local_conflicts',
                                conflicts: blocking,
                                sale: updated,
                                sync_status: SYNC_STATUS.SYNC_PENDING,
                                waiting_commit: false,
                                api_called: false,
                                sync_started: false,
                                mode: MODE
                            };
                        });
                    });
                }

                var payload = buildPayload(sale, device, reservations);
                var localErrors = validateLocalContract(payload);
                if (localErrors.length) {
                    return softAudit(idCtx, 'SYNC_VALIDATION_FAILED', ET.sale, saleId, {
                        reason: 'local_contract',
                        action: 'accept',
                        errors: localErrors,
                        mode: MODE
                    }).then(function () {
                        return ensurePending(idCtx, saleId).then(function (updated) {
                            return {
                                ok: false,
                                accepted: false,
                                reason: 'local_contract',
                                conflicts: localErrors,
                                sale: updated,
                                sync_status: SYNC_STATUS.SYNC_PENDING,
                                waiting_commit: false,
                                api_called: false,
                                sync_started: false,
                                mode: MODE
                            };
                        });
                    });
                }

                return enterValidating(idCtx, sale).then(function () {
                    return callAcceptApi(payload).then(function (res) {
                        var body = (res && res.body) || {};
                        var http = res && res.http_status;
                        var accepted = body.accepted === true;
                        var httpOk = http >= 200 && http < 300;

                        if (!httpOk && (http === 0 || http === 401 || http === 403 ||
                            http === 408 || http >= 500 || body.network_error)) {
                            return ensurePending(idCtx, saleId).then(function (updated) {
                                return softAudit(idCtx, 'SYNC_VALIDATION_FAILED', ET.sale, saleId, {
                                    reason: 'network_or_auth',
                                    http_status: http,
                                    action: 'accept',
                                    mode: MODE
                                }).then(function () {
                                    return {
                                        ok: false,
                                        accepted: false,
                                        reason: http === 403 ? 'permission_denied' : 'network',
                                        sale: updated,
                                        sync_status: SYNC_STATUS.SYNC_PENDING,
                                        waiting_commit: false,
                                        api_called: true,
                                        http_status: http,
                                        network_error: http !== 403,
                                        permission_denied: http === 403,
                                        sync_started: false,
                                        mode: MODE
                                    };
                                });
                            });
                        }

                        if (!accepted || !httpOk) {
                            return ensurePending(idCtx, saleId).then(function (updated) {
                                return softAudit(idCtx, 'SYNC_VALIDATION_FAILED', ET.sale, saleId, {
                                    reason: 'server_rejected',
                                    http_status: http,
                                    conflicts: body.conflicts || [],
                                    action: 'accept',
                                    mode: MODE
                                }).then(function () {
                                    return {
                                        ok: false,
                                        accepted: false,
                                        reason: 'server_rejected',
                                        conflicts: body.conflicts || [],
                                        warnings: body.warnings || [],
                                        sale: updated,
                                        sync_status: SYNC_STATUS.SYNC_PENDING,
                                        waiting_commit: false,
                                        api_called: true,
                                        http_status: http,
                                        sync_started: false,
                                        mode: MODE
                                    };
                                });
                            });
                        }

                        return updateSaleSyncStatus(idCtx, saleId, SYNC_STATUS.SERVER_ACCEPTED, {
                            server_sync_id: body.server_sync_id || null,
                            waiting_commit: true,
                            already_processed: !!body.already_processed,
                            last_accept_at: nowIso(),
                            synced: false
                        }).then(function (updated) {
                            return softAudit(idCtx, 'SYNC_VALIDATION_SUCCESS', ET.sale, saleId, {
                                action: 'accept',
                                server_sync_id: body.server_sync_id,
                                already_processed: !!body.already_processed,
                                waiting_commit: true,
                                mode: MODE
                            }).then(function () {
                                return writeMeta(idCtx, {
                                    last_sync_at: nowIso(),
                                    last_accept_at: nowIso(),
                                    last_server_sync_id: body.server_sync_id || null
                                }).then(function (meta) {
                                    return {
                                        ok: true,
                                        accepted: true,
                                        already_processed: !!body.already_processed,
                                        server_sync_id: body.server_sync_id || null,
                                        warnings: body.warnings || [],
                                        conflicts: body.conflicts || [],
                                        waiting_commit: true,
                                        sale: updated,
                                        sync_status: SYNC_STATUS.SERVER_ACCEPTED,
                                        payload: payload,
                                        api_called: true,
                                        http_status: http,
                                        inventory_deducted: false,
                                        accounting_posted: false,
                                        invoice_created: false,
                                        sync_started: false,
                                        mode: MODE,
                                        meta: meta
                                    };
                                });
                            });
                        });
                    }, function (err) {
                        return ensurePending(idCtx, saleId).then(function (updated) {
                            return softAudit(idCtx, 'SYNC_VALIDATION_FAILED', ET.sale, saleId, {
                                reason: 'network',
                                error: String(err && err.message ? err.message : err),
                                action: 'accept',
                                mode: MODE
                            }).then(function () {
                                return {
                                    ok: false,
                                    accepted: false,
                                    reason: 'network',
                                    sale: updated,
                                    sync_status: SYNC_STATUS.SYNC_PENDING,
                                    waiting_commit: false,
                                    api_called: true,
                                    network_error: true,
                                    sync_started: false,
                                    mode: MODE
                                };
                            });
                        });
                    });
                });
            });
        }

        function enterValidating(idCtx, sale) {
            var from = sale.sync_status || SYNC_STATUS.SYNC_PENDING;
            if (from === SYNC_STATUS.VALIDATING) {
                return Promise.resolve(sale);
            }
            if (from === SYNC_STATUS.SYNC_PENDING || from === SYNC_STATUS.REJECTED ||
                from === SYNC_STATUS.VALIDATED) {
                return updateSaleSyncStatus(idCtx, sale.id, SYNC_STATUS.VALIDATING, {
                    last_accept_started_at: nowIso()
                });
            }
            return Promise.reject(new Error('pos_invalid_sync_transition:' + from + '->VALIDATING'));
        }

        function ensurePending(idCtx, saleId) {
            return getSale(idCtx, saleId).then(function (sale) {
                var from = (sale && sale.sync_status) || SYNC_STATUS.SYNC_PENDING;
                if (!sale || from === SYNC_STATUS.SYNC_PENDING) {
                    return sale;
                }
                if (from === SYNC_STATUS.VALIDATING || from === SYNC_STATUS.REJECTED) {
                    return updateSaleSyncStatus(idCtx, saleId, SYNC_STATUS.SYNC_PENDING, {
                        last_accept_at: nowIso()
                    });
                }
                if (from === SYNC_STATUS.VALIDATED) {
                    return updateSaleSyncStatus(idCtx, saleId, SYNC_STATUS.VALIDATING, {})
                        .then(function () {
                            return updateSaleSyncStatus(idCtx, saleId, SYNC_STATUS.SYNC_PENDING, {
                                last_accept_at: nowIso()
                            });
                        });
                }
                return sale;
            });
        }

        /**
         * Manual Commit Sync — POST /sync/commit → COMMITTED (or retryable COMMIT_FAILED).
         */
        function commitSync(idCtx, saleId) {
            if (!saleId) {
                return Promise.reject(new Error('pos_sale_id_required'));
            }
            if (!isOnline() && !state.commitTransport) {
                return softAudit(idCtx, 'SYNC_COMMIT_FAILED', ET.sale, saleId, {
                    reason: 'offline',
                    mode: MODE
                }).then(function () {
                    return {
                        ok: false,
                        offline: true,
                        committed: false,
                        sync_status: SYNC_STATUS.COMMIT_FAILED,
                        api_called: false,
                        sync_started: false,
                        mode: MODE
                    };
                });
            }

            return getSale(idCtx, saleId).then(function (sale) {
                if (!sale) {
                    return Promise.reject(new Error('pos_sale_not_found'));
                }
                if (sale.status !== 'COMPLETED') {
                    return Promise.reject(new Error('pos_sale_not_completed'));
                }
                if (sale.sync_status === SYNC_STATUS.COMMITTED && (sale.order_id || sale.synced)) {
                    return {
                        ok: true,
                        committed: true,
                        already_committed: true,
                        order_id: sale.order_id || null,
                        server_sync_id: sale.server_sync_id || null,
                        sync_status: SYNC_STATUS.COMMITTED,
                        api_called: false,
                        sync_started: false,
                        mode: MODE,
                        sale: sale
                    };
                }
                var from = sale.sync_status || SYNC_STATUS.SYNC_PENDING;
                if (from !== SYNC_STATUS.SERVER_ACCEPTED && from !== SYNC_STATUS.COMMIT_FAILED) {
                    return Promise.reject(new Error(
                        'pos_invalid_sync_transition:' + from + '->COMMIT'
                    ));
                }
                if (!sale.sync_key && !sale.server_sync_id) {
                    return Promise.reject(new Error('pos_commit_selector_required'));
                }

                var body = {
                    sync_key: sale.sync_key || null,
                    server_sync_id: sale.server_sync_id || null,
                    branch_id: Number(sale.branch_id || 0) || 0,
                    device_id: sale.device_id || null,
                    terminal_id: Number(sale.terminal_id || 0) || 0
                };

                return softAudit(idCtx, 'SYNC_COMMIT_STARTED', ET.sale, saleId, {
                    sync_key: sale.sync_key,
                    server_sync_id: sale.server_sync_id,
                    mode: MODE
                }).then(function () {
                    return callCommitApi(body).then(function (res) {
                        var resp = (res && res.body) || {};
                        var http = res && res.http_status;
                        var httpOk = http >= 200 && http < 300;
                        var ok = resp.ok === true || resp.accepted === true;
                        var status = String(resp.status || '');
                        var already = !!resp.already_committed || status === 'COMMITTED';

                        if (ok && httpOk && (status === 'COMMITTED' || already || resp.order_id)) {
                            return updateSaleSyncStatus(idCtx, saleId, SYNC_STATUS.COMMITTED, {
                                order_id: resp.order_id || null,
                                order_no: resp.order_no || null,
                                server_sync_id: resp.server_sync_id || sale.server_sync_id || null,
                                waiting_commit: false,
                                last_commit_at: nowIso(),
                                last_commit_error: null,
                                synced: true
                            }).then(function (updated) {
                                return softAudit(idCtx, 'SYNC_COMMIT_SUCCESS', ET.sale, saleId, {
                                    order_id: resp.order_id,
                                    already_committed: already,
                                    mode: MODE
                                }).then(function () {
                                    return writeMeta(idCtx, {
                                        last_sync_at: nowIso(),
                                        last_commit_at: nowIso(),
                                        last_order_id: resp.order_id || null
                                    }).then(function (meta) {
                                        return {
                                            ok: true,
                                            committed: true,
                                            already_committed: already,
                                            order_id: resp.order_id || null,
                                            order_no: resp.order_no || null,
                                            server_sync_id: resp.server_sync_id || null,
                                            sale: updated,
                                            sync_status: SYNC_STATUS.COMMITTED,
                                            api_called: true,
                                            http_status: http,
                                            sync_started: false,
                                            mode: MODE,
                                            meta: meta
                                        };
                                    });
                                });
                            });
                        }

                        var errCode = resp.error_code || resp.error || 'commit_failed';
                        var retryable = http === 0 || http === 408 || http === 409 ||
                            http >= 500 || !!resp.network_error ||
                            errCode === 'in_progress' || errCode === 'checkout_failed';

                        return updateSaleSyncStatus(idCtx, saleId, SYNC_STATUS.COMMIT_FAILED, {
                            last_commit_error: String(errCode),
                            last_commit_at: nowIso(),
                            waiting_commit: true,
                            synced: false
                        }).then(function (updated) {
                            return softAudit(idCtx, 'SYNC_COMMIT_FAILED', ET.sale, saleId, {
                                error_code: errCode,
                                http_status: http,
                                retryable: retryable,
                                mode: MODE
                            }).then(function () {
                                return {
                                    ok: false,
                                    committed: false,
                                    retryable: retryable,
                                    reason: errCode,
                                    error_code: errCode,
                                    sale: updated,
                                    sync_status: SYNC_STATUS.COMMIT_FAILED,
                                    api_called: true,
                                    http_status: http,
                                    sync_started: false,
                                    mode: MODE
                                };
                            });
                        });
                    }, function (err) {
                        return updateSaleSyncStatus(idCtx, saleId, SYNC_STATUS.COMMIT_FAILED, {
                            last_commit_error: String(err && err.message ? err.message : err),
                            last_commit_at: nowIso(),
                            waiting_commit: true,
                            synced: false
                        }).then(function (updated) {
                            return {
                                ok: false,
                                committed: false,
                                retryable: true,
                                reason: 'network',
                                sale: updated,
                                sync_status: SYNC_STATUS.COMMIT_FAILED,
                                api_called: true,
                                network_error: true,
                                sync_started: false,
                                mode: MODE
                            };
                        });
                    });
                });
            });
        }

        function getSyncCenterStatus(idCtx) {
            return Promise.all([
                module._getDevice().ensureIdentity(idCtx),
                listSales(idCtx),
                module._getConflict().listConflicts(idCtx, { status: 'OPEN' }),
                readMeta(idCtx),
                module._getSyncAdapter().preparePreview(idCtx).catch(function () {
                    return { pending_sales_count: 0, items: [] };
                })
            ]).then(function (parts) {
                var device = parts[0];
                var sales = parts[1] || [];
                var conflicts = parts[2] || [];
                var meta = parts[3];
                var preview = parts[4] || {};
                var pending = sales.filter(function (s) {
                    return s && s.status === 'COMPLETED' &&
                        s.sync_status !== SYNC_STATUS.COMMITTED &&
                        s.synced !== true;
                });
                return {
                    ok: true,
                    mode: MODE,
                    commit_enabled: true,
                    device: {
                        device_uuid: device.device_uuid,
                        installation_id: device.installation_id,
                        last_activity_at: device.last_activity_at,
                        registered_server: false
                    },
                    pending_sales_count: pending.length,
                    conflicts_count: conflicts.length,
                    last_sync_at: meta.last_sync_at || null,
                    last_prepare_at: meta.last_prepare_at || null,
                    last_validate_at: meta.last_validate_at || null,
                    last_validate_ok: meta.last_validate_ok,
                    pending_sales: pending.map(function (s) {
                        return {
                            sale_id: s.id,
                            local_txn_no: s.local_txn_no,
                            sync_key: s.sync_key,
                            sync_status: s.sync_status || SYNC_STATUS.SYNC_PENDING,
                            total: s.total,
                            server_sync_id: s.server_sync_id || null,
                            order_id: s.order_id || null
                        };
                    }),
                    preview_ready_count: preview.ready_count || 0,
                    online: isOnline(),
                    api_hits: getApiHits(),
                    sync_started: false,
                    inventory_module: false,
                    automatic_sync: false
                };
            });
        }

        function setCommitTransport(fn) {
            state.commitTransport = typeof fn === 'function' ? fn : null;
        }

        return {
            ET: ET,
            MODE: MODE,
            SYNC_STATUS: SYNC_STATUS,
            VALIDATE_URL: VALIDATE_URL,
            ensureStore: ensureStore,
            prepareSync: prepareSync,
            validateOnline: validateOnline,
            acceptOnline: acceptOnline,
            commitSync: commitSync,
            buildPayload: buildPayload,
            validateLocalContract: validateLocalContract,
            resolveWarehouseId: resolveWarehouseId,
            getSyncCenterStatus: getSyncCenterStatus,
            setBearerToken: setBearerToken,
            setForceOffline: setForceOffline,
            setTransport: setTransport,
            setCommitTransport: setCommitTransport,
            getApiHits: getApiHits,
            clearApiHits: clearApiHits,
            updateSaleSyncStatus: updateSaleSyncStatus,
            ACCEPT_URL: ACCEPT_URL,
            COMMIT_URL: COMMIT_URL,
            isStoreOpen: function () { return !!state.store; }
        };
    }

    root.RatebOfflineV2PosSyncGateway = {
        __locked: true,
        create: createPosSyncGateway,
        MODE: MODE,
        SYNC_STATUS: SYNC_STATUS,
        ACCEPT_URL: ACCEPT_URL,
        COMMIT_URL: COMMIT_URL
    };
})(typeof window !== 'undefined' ? window : this);
