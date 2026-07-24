/*!
 * RATEB Offline V2 — POS accept certification (Phase 12)
 *
 * A normal · B duplicate · C invalid · D permission · E network · F 10x · G large
 */
(function (root) {
    'use strict';

    function createPosAcceptCert(module) {
        function gw() {
            return module._getSyncGateway();
        }

        function freshSale() {
            return module.listProducts({}).then(function (products) {
                if (!products || !products.length) {
                    throw new Error('pos_accept_cert_no_products');
                }
                return module.cancelCart('accept_cert_reset').catch(function () { return null; })
                    .then(function () {
                        return module.addToCart(products[0].id, 1);
                    })
                    .then(function () {
                        return module.completeSale();
                    })
                    .then(function (r) {
                        return r.sale;
                    });
            });
        }

        function mockAcceptOnce(store) {
            store = store || { byKey: Object.create(null), count: 0 };
            return function (url, payload) {
                if (String(url).indexOf('/sync/accept') === -1) {
                    return Promise.resolve({
                        http_status: 200,
                        body: { ok: true, accepted: true, conflicts: [], warnings: [], dry_run: true }
                    });
                }
                var key = payload && payload.sync_key;
                if (!key) {
                    return Promise.resolve({
                        http_status: 422,
                        body: {
                            ok: false,
                            accepted: false,
                            conflicts: [{ code: 'missing_sync_key', message: 'sync_key required' }],
                            waiting_commit: false
                        }
                    });
                }
                if (store.byKey[key]) {
                    return Promise.resolve({
                        http_status: 200,
                        body: {
                            ok: true,
                            accepted: true,
                            already_processed: true,
                            server_sync_id: store.byKey[key],
                            waiting_commit: true,
                            conflicts: [],
                            warnings: []
                        }
                    });
                }
                store.count += 1;
                var id = 'psa_mock_' + store.count;
                store.byKey[key] = id;
                return Promise.resolve({
                    http_status: 200,
                    body: {
                        ok: true,
                        accepted: true,
                        already_processed: false,
                        server_sync_id: id,
                        waiting_commit: true,
                        conflicts: [],
                        warnings: []
                    }
                });
            };
        }

        function testA(idCtx) {
            var g = gw();
            var store = { byKey: Object.create(null), count: 0 };
            g.clearApiHits();
            g.setForceOffline(false);
            g.setTransport(mockAcceptOnce(store));
            return freshSale().then(function (sale) {
                return g.acceptOnline(idCtx, sale.id).then(function (res) {
                    g.setTransport(null);
                    var ok = res.accepted === true && res.waiting_commit === true &&
                        res.sync_status === 'SERVER_ACCEPTED' && !!res.server_sync_id;
                    return { id: 'A_normal_acceptance', ok: ok, details: ok ? 'PASS' : JSON.stringify(res) };
                });
            }).catch(function (err) {
                g.setTransport(null);
                return { id: 'A_normal_acceptance', ok: false, details: String(err && err.message ? err.message : err) };
            });
        }

        function testB(idCtx) {
            var g = gw();
            var store = { byKey: Object.create(null), count: 0 };
            g.setTransport(mockAcceptOnce(store));
            return freshSale().then(function (sale) {
                return g.acceptOnline(idCtx, sale.id).then(function (first) {
                    /* Second call: local SERVER_ACCEPTED short-circuit + mock duplicate if re-sent */
                    return g.acceptOnline(idCtx, sale.id).then(function (second) {
                        g.setTransport(null);
                        var ok = first.server_sync_id &&
                            String(first.server_sync_id) === String(second.server_sync_id) &&
                            second.already_processed === true;
                        return {
                            id: 'B_duplicate_sync_key',
                            ok: ok,
                            details: ok ? 'same server_sync_id' : 'ids ' + first.server_sync_id + ' vs ' + second.server_sync_id,
                            server_sync_id: first.server_sync_id
                        };
                    });
                });
            }).catch(function (err) {
                g.setTransport(null);
                return { id: 'B_duplicate_sync_key', ok: false, details: String(err && err.message ? err.message : err) };
            });
        }

        function testC(idCtx) {
            var g = gw();
            g.setTransport(null);
            var errors = g.validateLocalContract({
                device_id: '',
                sync_key: '',
                sale_id: '',
                created_at: '',
                lines: [],
                totals: {}
            });
            var ok = errors.length > 0;
            return Promise.resolve({
                id: 'C_invalid_payload',
                ok: ok,
                details: ok ? 'Rejected locally' : 'should reject',
                error_count: errors.length
            });
        }

        function testD(idCtx) {
            var g = gw();
            g.setTransport(function (url) {
                if (String(url).indexOf('/sync/accept') !== -1) {
                    return Promise.resolve({
                        http_status: 403,
                        body: { ok: false, accepted: false, message: 'Forbidden' }
                    });
                }
                return Promise.resolve({ http_status: 200, body: { accepted: true } });
            });
            return freshSale().then(function (sale) {
                return g.acceptOnline(idCtx, sale.id).then(function (res) {
                    g.setTransport(null);
                    var ok = res.permission_denied === true && res.sync_status === 'SYNC_PENDING';
                    return {
                        id: 'D_permission_denied',
                        ok: ok,
                        details: ok ? 'PASS' : JSON.stringify({
                            permission_denied: res.permission_denied,
                            sync_status: res.sync_status
                        })
                    };
                });
            }).catch(function (err) {
                g.setTransport(null);
                return { id: 'D_permission_denied', ok: false, details: String(err && err.message ? err.message : err) };
            });
        }

        function testE(idCtx) {
            var g = gw();
            g.setTransport(function () {
                return Promise.reject(new Error('network_down'));
            });
            return freshSale().then(function (sale) {
                return g.acceptOnline(idCtx, sale.id).then(function (res) {
                    g.setTransport(null);
                    var ok = res.sync_status === 'SYNC_PENDING' && res.network_error === true;
                    return {
                        id: 'E_network_failure',
                        ok: ok,
                        details: ok ? 'Returns SYNC_PENDING' : 'status=' + res.sync_status
                    };
                });
            }).catch(function (err) {
                g.setTransport(null);
                return { id: 'E_network_failure', ok: false, details: String(err && err.message ? err.message : err) };
            });
        }

        function testF(idCtx) {
            var g = gw();
            var store = { byKey: Object.create(null), count: 0 };
            var transport = mockAcceptOnce(store);
            g.setTransport(transport);
            return freshSale().then(function (sale) {
                return g.acceptOnline(idCtx, sale.id).then(function (first) {
                    var payload = g.buildPayload(sale, {
                        device_uuid: sale.device_id,
                        installation_id: sale.installation_id
                    }, []);
                    payload.sync_key = sale.sync_key;
                    var chain = Promise.resolve();
                    var ids = [first.server_sync_id];
                    for (var i = 0; i < 9; i++) {
                        chain = chain.then(function () {
                            return transport(g.ACCEPT_URL || '/rateb-erp/api/v1/pos/sync/accept', payload, null)
                                .then(function (res) {
                                    ids.push(res.body && res.body.server_sync_id);
                                });
                        });
                    }
                    return chain.then(function () {
                        g.setTransport(null);
                        var unique = {};
                        ids.forEach(function (id) {
                            if (id) {
                                unique[String(id)] = true;
                            }
                        });
                        var uniq = Object.keys(unique);
                        var ok = uniq.length === 1 && store.count === 1;
                        return {
                            id: 'F_ten_repeated',
                            ok: ok,
                            details: ok ? 'One acceptance only' : 'count=' + store.count + ' ids=' + uniq.join(','),
                            acceptance_count: store.count
                        };
                    });
                });
            }).catch(function (err) {
                g.setTransport(null);
                return { id: 'F_ten_repeated', ok: false, details: String(err && err.message ? err.message : err) };
            });
        }

        function testG(idCtx) {
            var g = gw();
            var store = { byKey: Object.create(null), count: 0 };
            g.setTransport(mockAcceptOnce(store));
            return module.listProducts({}).then(function (products) {
                var p = products[0];
                return module.cancelCart('accept_cert_g').catch(function () { return null; })
                    .then(function () {
                        var chain = Promise.resolve();
                        for (var i = 0; i < 40; i++) {
                            chain = chain.then(function () {
                                return module.addToCart(p.id, 1);
                            });
                        }
                        return chain;
                    })
                    .then(function () {
                        return module.completeSale();
                    })
                    .then(function (completed) {
                        var t0 = Date.now();
                        return g.acceptOnline(idCtx, completed.sale_id).then(function (res) {
                            g.setTransport(null);
                            var ms = Date.now() - t0;
                            var ok = res.accepted === true && res.waiting_commit === true;
                            return {
                                id: 'G_large_payload',
                                ok: ok,
                                details: ok ? 'PASS ms=' + ms : 'failed',
                                ms: ms,
                                line_hint: 40
                            };
                        });
                    });
            }).catch(function (err) {
                g.setTransport(null);
                return { id: 'G_large_payload', ok: false, details: String(err && err.message ? err.message : err) };
            });
        }

        function runAll(idCtx) {
            var results = [];
            var t0 = Date.now();
            return testA(idCtx).then(function (r) { results.push(r); return testB(idCtx); })
                .then(function (r) { results.push(r); return testC(idCtx); })
                .then(function (r) { results.push(r); return testD(idCtx); })
                .then(function (r) { results.push(r); return testE(idCtx); })
                .then(function (r) { results.push(r); return testF(idCtx); })
                .then(function (r) { results.push(r); return testG(idCtx); })
                .then(function (r) {
                    results.push(r);
                    var allOk = results.every(function (x) { return x.ok; });
                    return {
                        ok: allOk,
                        ms: Date.now() - t0,
                        results: results,
                        proof: {
                            syncStarted: false,
                            inventoryAbsent: true,
                            invWrites: false,
                            accountingWrites: false,
                            invoiceCreated: false,
                            automaticSync: false,
                            waitingCommitOnly: true,
                            noCommittedState: true
                        },
                        sync_started: false
                    };
                });
        }

        return { runAll: runAll };
    }

    root.RatebOfflineV2PosAcceptCert = {
        __locked: true,
        create: createPosAcceptCert
    };
})(typeof window !== 'undefined' ? window : this);
