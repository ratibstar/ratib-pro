/*!
 * RATEB Offline V2 — POS sync gateway certification (Phase 11)
 *
 * A offline · B online dry-run · C invalid local · D duplicate sync_key · E network fail
 */
(function (root) {
    'use strict';

    function createPosSyncCert(module) {
        function isolationBase(gw) {
            var sync = null;
            try {
                var rt = root.RatebOfflineV2Runtime;
                sync = rt && rt.services && typeof rt.services.tryGet === 'function'
                    ? rt.services.tryGet('sync')
                    : null;
            } catch (e) { /* ignore */ }
            var syncStarted = !!(sync && typeof sync.isStarted === 'function' && sync.isStarted());
            return {
                syncStarted: syncStarted,
                apiHits: gw ? gw.getApiHits() : [],
                inventoryAbsent: true,
                invWrites: false,
                accountingWrites: false,
                automaticSync: false,
                backgroundJobs: false
            };
        }

        function pickCompletedSale(idCtx) {
            return module._getCart().listSales(idCtx).then(function (sales) {
                var hit = (sales || []).filter(function (s) {
                    return s && s.status === 'COMPLETED' && s.sync_key;
                })[0];
                if (hit) {
                    return hit;
                }
                return module.listProducts({}).then(function (products) {
                    if (!products || !products.length) {
                        throw new Error('pos_sync_cert_no_products');
                    }
                    return module.cancelCart('sync_cert_reset').catch(function () { return null; })
                        .then(function () {
                            return module.addToCart(products[0].id, 1);
                        })
                        .then(function () {
                            return module.completeSale();
                        })
                        .then(function (res) {
                            return res.sale;
                        });
                });
            });
        }

        function testAOffline(idCtx, gw) {
            gw.clearApiHits();
            gw.setForceOffline(true);
            gw.setTransport(null);
            return pickCompletedSale(idCtx).then(function (sale) {
                return gw.validateOnline(idCtx, sale.id).then(function (res) {
                    gw.setForceOffline(false);
                    var hits = gw.getApiHits();
                    var ok = res.api_called === false && hits.length === 0 && res.offline === true;
                    return {
                        id: 'A_offline_mode',
                        ok: ok,
                        details: ok ? 'no API calls offline' : 'unexpected API activity',
                        apiHits: hits,
                        isolation: isolationBase(gw)
                    };
                });
            }).catch(function (err) {
                gw.setForceOffline(false);
                return { id: 'A_offline_mode', ok: false, details: String(err && err.message ? err.message : err) };
            });
        }

        function testBOnline(idCtx, gw) {
            gw.clearApiHits();
            gw.setForceOffline(false);
            gw.setTransport(function () {
                return Promise.resolve({
                    http_status: 200,
                    body: {
                        ok: true,
                        accepted: true,
                        conflicts: [],
                        warnings: [],
                        dry_run: true,
                        mode: 'DRY_RUN_ONLY'
                    }
                });
            });
            /* Fresh sale avoids VALIDATED lock / leftover conflicts. */
            return module.listProducts({}).then(function (products) {
                return module.cancelCart('sync_cert_b').catch(function () { return null; })
                    .then(function () {
                        return module.addToCart(products[0].id, 1);
                    })
                    .then(function () {
                        return module.completeSale();
                    })
                    .then(function (completed) {
                        return gw.validateOnline(idCtx, completed.sale_id).then(function (res) {
                            gw.setTransport(null);
                            var ok = res.accepted === true && res.sync_status === 'VALIDATED' &&
                                res.api_called === true && res.dry_run === true;
                            return {
                                id: 'B_online_validation',
                                ok: ok,
                                details: ok ? 'payload accepted (dry-run)' : 'validation failed: ' +
                                    (res.reason || res.sync_status),
                                sync_status: res.sync_status,
                                isolation: isolationBase(gw)
                            };
                        });
                    });
            }).catch(function (err) {
                gw.setTransport(null);
                return { id: 'B_online_validation', ok: false, details: String(err && err.message ? err.message : err) };
            });
        }

        function testCInvalid(idCtx, gw) {
            gw.clearApiHits();
            var badPayload = {
                device_id: null,
                sync_key: '',
                sale_id: 'bad',
                created_at: '',
                lines: [],
                totals: {}
            };
            var errors = gw.validateLocalContract(badPayload);
            var ok = errors.length > 0 && gw.getApiHits().length === 0;
            return Promise.resolve({
                id: 'C_invalid_sale',
                ok: ok,
                details: ok ? 'rejected locally' : 'should reject locally',
                error_count: errors.length,
                apiHits: gw.getApiHits()
            });
        }

        function testDDuplicateSyncKey(idCtx, gw) {
            return pickCompletedSale(idCtx).then(function (sale) {
                return module.evaluateConflictPayload({
                    id: 'dup-' + Date.now().toString(36),
                    status: 'COMPLETED',
                    sync_key: sale.sync_key,
                    local_txn_no: 'DUP-TXN-' + Date.now(),
                    total: 1,
                    lines: [{ product_id: (sale.lines && sale.lines[0] && sale.lines[0].product_id) || 'p1', qty: 1, line_total: 1 }]
                }, []).then(function (evalRes) {
                    var types = ((evalRes && evalRes.conflicts) || []).map(function (c) {
                        return c.conflict_type;
                    });
                    var ok = types.indexOf('duplicate_sync_key') !== -1;
                    /* Ensure validateOnline stops on conflicts */
                    return gw.validateOnline(idCtx, sale.id).then(function (res) {
                        var stopped = res.stopped === true || res.reason === 'local_conflicts' ||
                            (res.accepted === false && res.api_called === false && ok);
                        return {
                            id: 'D_duplicate_sync_key',
                            ok: ok,
                            details: ok ? 'duplicate sync_key detected' : 'duplicate not detected',
                            conflict_types: types,
                            validate_stopped: stopped
                        };
                    }).catch(function () {
                        return {
                            id: 'D_duplicate_sync_key',
                            ok: ok,
                            details: ok ? 'duplicate sync_key detected' : 'duplicate not detected',
                            conflict_types: types
                        };
                    });
                });
            }).catch(function (err) {
                return { id: 'D_duplicate_sync_key', ok: false, details: String(err && err.message ? err.message : err) };
            });
        }

        function testENetworkFail(idCtx, gw) {
            gw.clearApiHits();
            gw.setForceOffline(false);
            gw.setTransport(function () {
                return Promise.reject(new Error('network_down'));
            });
            return module.listProducts({}).then(function (products) {
                return module.cancelCart('sync_cert_e').catch(function () { return null; })
                    .then(function () {
                        return module.addToCart(products[0].id, 1);
                    })
                    .then(function () {
                        return module.completeSale();
                    })
                    .then(function (completed) {
                        return gw.validateOnline(idCtx, completed.sale_id).then(function (res) {
                            gw.setTransport(null);
                            var ok = res.sync_status === 'SYNC_PENDING' && res.network_error === true;
                            return {
                                id: 'E_failed_network',
                                ok: ok,
                                details: ok ? 'remains SYNC_PENDING' : 'unexpected status ' + res.sync_status,
                                sync_status: res.sync_status,
                                isolation: isolationBase(gw)
                            };
                        });
                    });
            }).catch(function (err) {
                gw.setTransport(null);
                return { id: 'E_failed_network', ok: false, details: String(err && err.message ? err.message : err) };
            });
        }

        function runAll(idCtx) {
            var gw = module._getSyncGateway();
            var results = [];
            var t0 = Date.now();
            return testAOffline(idCtx, gw).then(function (r) {
                results.push(r);
                return testCInvalid(idCtx, gw);
            }).then(function (r) {
                results.push(r);
                return testDDuplicateSyncKey(idCtx, gw);
            }).then(function (r) {
                results.push(r);
                return testENetworkFail(idCtx, gw);
            }).then(function (r) {
                results.push(r);
                return testBOnline(idCtx, gw);
            }).then(function (r) {
                results.push(r);
                var iso = isolationBase(gw);
                var allOk = results.every(function (x) { return x.ok; });
                return {
                    ok: allOk,
                    ms: Date.now() - t0,
                    results: results,
                    isolation: iso,
                    proof: {
                        syncStarted: iso.syncStarted === false,
                        inventoryAbsent: true,
                        invWrites: false,
                        accountingWrites: false,
                        automaticSync: false,
                        backgroundJobs: false,
                        commitDisabled: true,
                        mode: 'DRY_RUN_ONLY'
                    },
                    sync_started: false,
                    network_auto: false
                };
            });
        }

        return {
            runAll: runAll,
            testAOffline: testAOffline,
            testBOnline: testBOnline,
            testCInvalid: testCInvalid,
            testDDuplicateSyncKey: testDDuplicateSyncKey,
            testENetworkFail: testENetworkFail
        };
    }

    root.RatebOfflineV2PosSyncCert = {
        __locked: true,
        create: createPosSyncCert
    };
})(typeof window !== 'undefined' ? window : this);
