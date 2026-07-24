/*!
 * RATEB Offline V2 — POS controlled sync gateway (Phase 11)
 *
 * Manual-only pipeline: Prepare → Validate Contract → Dry-Run API.
 * Commit disabled. Never starts sync engine, SW push, or boot sync.
 * Mode: DRY_RUN_ONLY — no server-side mutations expected.
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

    var MODE = 'DRY_RUN_ONLY';
    var VALIDATE_URL = '/rateb-erp/api/v1/pos/sync/validate';

    var SYNC_STATUS = {
        SYNC_PENDING: 'SYNC_PENDING',
        VALIDATING: 'VALIDATING',
        VALIDATED: 'VALIDATED',
        REJECTED: 'REJECTED'
    };

    var ALLOWED = {
        SYNC_PENDING: { VALIDATING: true },
        VALIDATING: { VALIDATED: true, REJECTED: true, SYNC_PENDING: true },
        VALIDATED: {},
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
                        var next = Object.assign({}, sale, extra || {}, {
                            sync_status: toStatus,
                            synced: false,
                            updated_at: nowIso()
                        });
                        return store.put(ET.sale, next.id, next, Number(next.version || 1) + 1)
                            .then(function () { return next; });
                    });
                });
            });
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
            return {
                device_id: (device && device.device_uuid) || sale.device_id || null,
                installation_id: (device && device.installation_id) || sale.installation_id || null,
                sync_key: sale.sync_key || null,
                sale_id: sale.id,
                created_at: sale.created_at || sale.completed_at || nowIso(),
                customer: sale.customer || null,
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
                    branch_id: sale.branch_id || 0,
                    mode: MODE,
                    source: 'pos_offline_v2'
                }
            };
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

        function callValidateApi(payload) {
            var hit = {
                at: nowIso(),
                url: VALIDATE_URL,
                method: 'POST',
                mode: MODE
            };
            state.apiHits.push(hit);
            var transport = state.transport || defaultTransport;
            return Promise.resolve()
                .then(function () {
                    return transport(VALIDATE_URL, payload, state.bearerToken);
                })
                .then(function (res) {
                    hit.http_status = res && res.http_status;
                    hit.ok = !!(res && res.body && (res.body.accepted === true || res.body.ok === true));
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

        /** Commit Sync — disabled until a future phase. */
        function commitSync() {
            return Promise.reject(new Error('pos_sync_commit_disabled'));
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
                        s.sync_status !== SYNC_STATUS.VALIDATED &&
                        s.synced !== true;
                });
                return {
                    ok: true,
                    mode: MODE,
                    commit_enabled: false,
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
                            total: s.total
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

        return {
            ET: ET,
            MODE: MODE,
            SYNC_STATUS: SYNC_STATUS,
            VALIDATE_URL: VALIDATE_URL,
            ensureStore: ensureStore,
            prepareSync: prepareSync,
            validateOnline: validateOnline,
            commitSync: commitSync,
            buildPayload: buildPayload,
            validateLocalContract: validateLocalContract,
            getSyncCenterStatus: getSyncCenterStatus,
            setBearerToken: setBearerToken,
            setForceOffline: setForceOffline,
            setTransport: setTransport,
            getApiHits: getApiHits,
            clearApiHits: clearApiHits,
            updateSaleSyncStatus: updateSaleSyncStatus,
            isStoreOpen: function () { return !!state.store; }
        };
    }

    root.RatebOfflineV2PosSyncGateway = {
        __locked: true,
        create: createPosSyncGateway,
        MODE: MODE,
        SYNC_STATUS: SYNC_STATUS
    };
})(typeof window !== 'undefined' ? window : this);
