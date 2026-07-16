/*!
 * RATEB Offline V2 — Host boot (Phases 1–11 self-tests).
 */
(function (root) {
    'use strict';

    var $ = function (id) {
        return root.document.getElementById(id);
    };

    function setText(id, text) {
        var el = $(id);
        if (el) {
            el.textContent = text;
        }
    }

    function setState(id, ok, detail) {
        var el = $(id);
        if (!el) {
            return;
        }
        el.textContent = (ok ? 'PASS' : 'FAIL') + (detail ? ' — ' + detail : '');
        el.className = 'check ' + (ok ? 'pass' : 'fail');
    }

    function registerSw() {
        if (!('serviceWorker' in root.navigator)) {
            return Promise.resolve({ ok: false, error: 'sw_unsupported' });
        }
        var swUrl = new URL('sw.js', root.location.href).href;
        return root.navigator.serviceWorker.register(swUrl, { scope: './' }).then(function (reg) {
            return { ok: true, scope: reg.scope };
        }).catch(function (err) {
            return { ok: false, error: String(err && err.message ? err.message : err) };
        });
    }

    /**
     * Phase Z: wait for SQLite runtime module without a 20s blind timeout.
     * Polls ready event + RatebOfflineV2DB; probes vendor index.mjs once if slow.
     */
    function whenDbReady() {
        if (root.RatebOfflineV2DB) {
            return Promise.resolve(root.RatebOfflineV2DB);
        }
        return new Promise(function (resolve) {
            var done = false;
            var started = root.performance && performance.now ? performance.now() : Date.now();
            var maxMs = 4000;
            var probeStarted = false;

            function finish() {
                if (done) {
                    return;
                }
                done = true;
                root.removeEventListener('rateb-v2-db-ready', onReady);
                resolve(root.RatebOfflineV2DB || null);
            }

            function onReady() {
                finish();
            }

            function elapsed() {
                return (root.performance && performance.now ? performance.now() : Date.now()) - started;
            }

            function probeVendorOnce() {
                if (probeStarted || root.RatebOfflineV2DB) {
                    return;
                }
                probeStarted = true;
                var vendorUrl = new URL('vendor/sqlite/index.mjs', root.location.href).href;
                root.fetch(vendorUrl, { method: 'HEAD', cache: 'no-cache', credentials: 'same-origin' })
                    .then(function (res) {
                        if (!res || !res.ok) {
                            finish();
                        }
                    })
                    .catch(function () {
                        finish();
                    });
            }

            root.addEventListener('rateb-v2-db-ready', onReady);

            (function tick() {
                if (done) {
                    return;
                }
                if (root.RatebOfflineV2DB) {
                    finish();
                    return;
                }
                if (elapsed() >= 800) {
                    probeVendorOnce();
                }
                if (elapsed() >= maxMs) {
                    finish();
                    return;
                }
                root.setTimeout(tick, 40);
            })();
        });
    }

    function runPmSelfTest() {
        var pm = root.RatebOfflineV2PM;
        if (!pm) {
            setState('pm-selftest', false, 'PM missing');
            return Promise.resolve({ ok: false });
        }
        setText('pm-version', pm.version);
        return pm.runSelfTest().then(function (res) {
            setState('pm-selftest', !!res.ok, res.ok
                ? ('steps=' + (res.evidence && res.evidence.length))
                : (res.error || 'failed'));
            if ($('pm-evidence') && res.evidence) {
                $('pm-evidence').textContent = res.evidence.map(function (e) {
                    return (e.ok ? 'OK' : 'NO') + ' ' + e.step + (e.detail ? ' · ' + e.detail : '');
                }).join('\n');
            }
            return res;
        });
    }

    function runDbSelfTest() {
        return whenDbReady().then(function (db) {
            if (!db) {
                setState('db-selftest', false, 'DB module not loaded');
                return { ok: false };
            }
            setText('db-version', db.version);
            return db.runSelfTest().then(function (res) {
                setState('db-selftest', !!res.ok, res.ok
                    ? ('mode=' + res.mode + ' schema=' + res.schemaVersion)
                    : (res.error || 'failed'));
                if ($('db-evidence') && res.evidence) {
                    $('db-evidence').textContent = res.evidence.map(function (e) {
                        return (e.ok ? 'OK' : 'NO') + ' ' + e.step + (e.detail ? ' · ' + e.detail : '');
                    }).join('\n');
                }
                return res;
            });
        });
    }

    function runRuntimeSelfTest() {
        var rt = root.RatebOfflineV2Runtime;
        if (!rt) {
            setState('rt-selftest', false, 'Runtime missing');
            return Promise.resolve({ ok: false });
        }
        setText('rt-version', rt.version);
        return rt.runSelfTest().then(function (res) {
            setState('rt-selftest', !!res.ok, res.ok
                ? ('state=' + res.state)
                : (res.error || 'failed'));
            if ($('rt-evidence') && res.evidence) {
                $('rt-evidence').textContent = res.evidence.map(function (e) {
                    return (e.ok ? 'OK' : 'NO') + ' ' + e.step + (e.detail ? ' · ' + e.detail : '');
                }).join('\n');
            }
            return res;
        });
    }

    function runRouterSelfTest() {
        var routerApi = root.RatebOfflineV2Router;
        if (!routerApi) {
            setState('router-selftest', false, 'Router missing');
            return Promise.resolve({ ok: false });
        }
        setText('router-version', routerApi.version);
        return routerApi.runSelfTest().then(function (res) {
            setState('router-selftest', !!res.ok, res.ok
                ? ('steps=' + (res.evidence && res.evidence.length))
                : (res.error || 'failed'));
            if ($('router-evidence') && res.evidence) {
                $('router-evidence').textContent = res.evidence.map(function (e) {
                    return (e.ok ? 'OK' : 'NO') + ' ' + e.step + (e.detail ? ' · ' + e.detail : '');
                }).join('\n');
            }
            return res;
        });
    }

    function runShellSelfTest() {
        var shellApi = root.RatebOfflineV2Shell;
        if (!shellApi) {
            setState('shell-selftest', false, 'Shell missing');
            return Promise.resolve({ ok: false });
        }
        setText('shell-version', shellApi.version);
        return shellApi.runSelfTest().then(function (res) {
            setState('shell-selftest', !!res.ok, res.ok
                ? ('steps=' + (res.evidence && res.evidence.length))
                : (res.error || 'failed'));
            if ($('shell-evidence') && res.evidence) {
                $('shell-evidence').textContent = res.evidence.map(function (e) {
                    return (e.ok ? 'OK' : 'NO') + ' ' + e.step + (e.detail ? ' · ' + e.detail : '');
                }).join('\n');
            }
            return res;
        });
    }

    function runSyncSelfTest() {
        var syncApi = root.RatebOfflineV2Sync;
        if (!syncApi) {
            setState('sync-selftest', false, 'Sync missing');
            return Promise.resolve({ ok: false });
        }
        setText('sync-version', syncApi.version);
        return syncApi.runSelfTest().then(function (res) {
            setState('sync-selftest', !!res.ok, res.ok
                ? ('steps=' + (res.evidence && res.evidence.length))
                : (res.error || 'failed'));
            if ($('sync-evidence') && res.evidence) {
                $('sync-evidence').textContent = res.evidence.map(function (e) {
                    return (e.ok ? 'OK' : 'NO') + ' ' + e.step + (e.detail ? ' · ' + e.detail : '');
                }).join('\n');
            }
            return res;
        });
    }

    function runSdkSelfTest() {
        var sdkApi = root.RatebOfflineV2Modules;
        if (!sdkApi) {
            setState('sdk-selftest', false, 'Module SDK missing');
            return Promise.resolve({ ok: false });
        }
        setText('sdk-version', sdkApi.version);
        return sdkApi.runSelfTest().then(function (res) {
            setState('sdk-selftest', !!res.ok, res.ok
                ? ('steps=' + (res.evidence && res.evidence.length))
                : (res.error || 'failed'));
            if ($('sdk-evidence') && res.evidence) {
                $('sdk-evidence').textContent = res.evidence.map(function (e) {
                    return (e.ok ? 'OK' : 'NO') + ' ' + e.step + (e.detail ? ' · ' + e.detail : '');
                }).join('\n');
            }
            return res;
        });
    }

    function runBusinessSelfTest() {
        var bmApi = root.RatebOfflineV2Business;
        if (!bmApi) {
            setState('bm-selftest', false, 'Business framework missing');
            return Promise.resolve({ ok: false });
        }
        setText('bm-version', bmApi.version);
        return bmApi.runSelfTest().then(function (res) {
            setState('bm-selftest', !!res.ok, res.ok
                ? ('steps=' + (res.evidence && res.evidence.length))
                : (res.error || 'failed'));
            if ($('bm-evidence') && res.evidence) {
                $('bm-evidence').textContent = res.evidence.map(function (e) {
                    return (e.ok ? 'OK' : 'NO') + ' ' + e.step + (e.detail ? ' · ' + e.detail : '');
                }).join('\n');
            }
            return res;
        });
    }

    function runIdentitySelfTest() {
        var idApi = root.RatebOfflineV2Identity;
        if (!idApi) {
            setState('identity-selftest', false, 'Identity module missing');
            return Promise.resolve({ ok: false });
        }
        setText('identity-version', idApi.version);
        return idApi.runSelfTest().then(function (res) {
            setState('identity-selftest', !!res.ok, res.ok
                ? ('steps=' + (res.evidence && res.evidence.length))
                : (res.error || 'failed'));
            if ($('identity-evidence') && res.evidence) {
                $('identity-evidence').textContent = res.evidence.map(function (e) {
                    return (e.ok ? 'OK' : 'NO') + ' ' + e.step + (e.detail ? ' · ' + e.detail : '');
                }).join('\n');
            }
            return res;
        });
    }

    function runInventorySelfTest() {
        var invApi = root.RatebOfflineV2Inventory;
        if (!invApi) {
            setState('inventory-selftest', false, 'Inventory module missing');
            return Promise.resolve({ ok: false });
        }
        setText('inventory-version', invApi.version);
        return invApi.runSelfTest().then(function (res) {
            setState('inventory-selftest', !!res.ok, res.ok
                ? ('steps=' + (res.evidence && res.evidence.length))
                : (res.error || 'failed'));
            if ($('inventory-evidence') && res.evidence) {
                $('inventory-evidence').textContent = res.evidence.map(function (e) {
                    return (e.ok ? 'OK' : 'NO') + ' ' + e.step + (e.detail ? ' · ' + e.detail : '');
                }).join('\n');
            }
            return res;
        });
    }

    function runProcurementSelfTest() {
        var procApi = root.RatebOfflineV2Procurement;
        if (!procApi) {
            setState('procurement-selftest', false, 'Procurement module missing');
            return Promise.resolve({ ok: false });
        }
        setText('procurement-version', procApi.version);
        return procApi.runSelfTest().then(function (res) {
            setState('procurement-selftest', !!res.ok, res.ok
                ? ('steps=' + (res.evidence && res.evidence.length))
                : (res.error || 'failed'));
            if ($('procurement-evidence') && res.evidence) {
                $('procurement-evidence').textContent = res.evidence.map(function (e) {
                    return (e.ok ? 'OK' : 'NO') + ' ' + e.step + (e.detail ? ' · ' + e.detail : '');
                }).join('\n');
            }
            return res;
        });
    }

    function runSalesSelfTest() {
        var salesApi = root.RatebOfflineV2Sales;
        if (!salesApi) {
            setState('sales-selftest', false, 'Sales module missing');
            return Promise.resolve({ ok: false });
        }
        setText('sales-version', salesApi.version);
        return salesApi.runSelfTest().then(function (res) {
            setState('sales-selftest', !!res.ok, res.ok
                ? ('steps=' + (res.evidence && res.evidence.length))
                : (res.error || 'failed'));
            if ($('sales-evidence') && res.evidence) {
                $('sales-evidence').textContent = res.evidence.map(function (e) {
                    return (e.ok ? 'OK' : 'NO') + ' ' + e.step + (e.detail ? ' · ' + e.detail : '');
                }).join('\n');
            }
            return res;
        });
    }

    function runAccountingSelfTest() {
        var acctApi = root.RatebOfflineV2Accounting;
        if (!acctApi) {
            setState('accounting-selftest', false, 'Accounting module missing');
            return Promise.resolve({ ok: false });
        }
        setText('accounting-version', acctApi.version);
        return acctApi.runSelfTest().then(function (res) {
            setState('accounting-selftest', !!res.ok, res.ok
                ? ('steps=' + (res.evidence && res.evidence.length))
                : (res.error || 'failed'));
            if ($('accounting-evidence') && res.evidence) {
                $('accounting-evidence').textContent = res.evidence.map(function (e) {
                    return (e.ok ? 'OK' : 'NO') + ' ' + e.step + (e.detail ? ' · ' + e.detail : '');
                }).join('\n');
            }
            return res;
        });
    }

    function runCrmSelfTest() {
        var crmApi = root.RatebOfflineV2Crm;
        if (!crmApi) {
            setState('crm-selftest', false, 'CRM module missing');
            return Promise.resolve({ ok: false });
        }
        setText('crm-version', crmApi.version);
        return crmApi.runSelfTest().then(function (res) {
            setState('crm-selftest', !!res.ok, res.ok
                ? ('steps=' + (res.evidence && res.evidence.length))
                : (res.error || 'failed'));
            if ($('crm-evidence') && res.evidence) {
                $('crm-evidence').textContent = res.evidence.map(function (e) {
                    return (e.ok ? 'OK' : 'NO') + ' ' + e.step + (e.detail ? ' · ' + e.detail : '');
                }).join('\n');
            }
            return res;
        });
    }

    function runHrSelfTest() {
        var hrApi = root.RatebOfflineV2Hr;
        if (!hrApi) {
            setState('hr-selftest', false, 'HR module missing');
            return Promise.resolve({ ok: false });
        }
        setText('hr-version', hrApi.version);
        return hrApi.runSelfTest().then(function (res) {
            setState('hr-selftest', !!res.ok, res.ok
                ? ('steps=' + (res.evidence && res.evidence.length))
                : (res.error || 'failed'));
            if ($('hr-evidence') && res.evidence) {
                $('hr-evidence').textContent = res.evidence.map(function (e) {
                    return (e.ok ? 'OK' : 'NO') + ' ' + e.step + (e.detail ? ' · ' + e.detail : '');
                }).join('\n');
            }
            return res;
        });
    }

    function runMfgSelfTest() {
        var mfgApi = root.RatebOfflineV2Mfg;
        if (!mfgApi) {
            setState('mfg-selftest', false, 'Manufacturing module missing');
            return Promise.resolve({ ok: false });
        }
        setText('mfg-version', mfgApi.version);
        return mfgApi.runSelfTest().then(function (res) {
            setState('mfg-selftest', !!res.ok, res.ok
                ? ('steps=' + (res.evidence && res.evidence.length))
                : (res.error || 'failed'));
            if ($('mfg-evidence') && res.evidence) {
                $('mfg-evidence').textContent = res.evidence.map(function (e) {
                    return (e.ok ? 'OK' : 'NO') + ' ' + e.step + (e.detail ? ' · ' + e.detail : '');
                }).join('\n');
            }
            return res;
        });
    }

    function run() {
        var hci = root.RatebOfflineV2HCI;
        if (!hci) {
            setText('boot-status', 'HCI missing');
            return;
        }

        setText('hci-version', hci.version);
        setText('layout-id', hci.layoutId);
        setState('sec-context', hci.isSecureContext(), hci.isSecureContext() ? 'secure' : 'insecure');
        setState('installed', true, hci.isInstalledDisplay() ? 'standalone/minimal-ui' : 'browser tab');

        var reach = hci.getReachability();
        setText('reachability', reach.online ? 'online (signal only)' : 'offline (boot must still succeed)');

        var bootNet = performance.getEntriesByType
            ? performance.getEntriesByType('resource').filter(function (r) {
                return /\/admin(\/|$)/i.test(r.name) || /offline-shell/i.test(r.name);
            })
            : [];
        setState('no-php', bootNet.length === 0, bootNet.length ? 'admin/offline-shell requested' : 'no admin PHP fetch');

        function mark(name) {
            try {
                if (root.performance && performance.mark) {
                    performance.mark('rateb-v2-' + name);
                }
            } catch (eMark) { /* ignore */ }
        }

        function loadScript(src) {
            return new Promise(function (resolve, reject) {
                var s = root.document.createElement('script');
                s.src = src;
                s.async = false;
                s.onload = function () { resolve(src); };
                s.onerror = function () { reject(new Error('script_load_failed:' + src)); };
                root.document.body.appendChild(s);
            });
        }

        /** Phase Z: load post-shell platform + BM scripts only after Shell Ready. */
        function loadPostShellScripts() {
            var files = [
                './js/sync/sync-engine.js',
                './js/modules/module-sdk.js',
                './js/business/business-module-framework.js',
                './js/business/reference-module.js',
                './js/business/identity-module.js',
                './js/business/inventory-module.js',
                './js/business/procurement-module.js',
                './js/business/sales-module.js',
                './js/business/accounting-module.js',
                './js/business/crm-module.js',
                './js/business/hr-module.js',
                './js/business/manufacturing-module.js'
            ];
            var chain = Promise.resolve();
            files.forEach(function (src) {
                chain = chain.then(function () {
                    return loadScript(src);
                });
            });
            return chain;
        }

        function hciHousekeepingNonBlocking() {
            return hci.getQuota().then(function (q) {
                if (q.ok) {
                    setText('quota', 'usage ' + q.usage + ' / quota ' + q.quota);
                } else {
                    setText('quota', q.error || 'n/a');
                }
                return hci.requestPersistence();
            }).then(function (p) {
                setState('persist', !!p.ok, p.persisted ? 'persisted' : 'not persisted (may be browser policy)');
                return hci.appendLog('phase17-host-boot');
            }).catch(function (err) {
                setText('quota', 'n/a');
                setState('persist', false, String(err && err.message ? err.message : err));
            });
        }

        mark('boot-start');
        return hci.ensureLayout().then(function (layout) {
            mark('layout-ensured');
            setState('layout-ensure', !!layout.ok, layout.opfsRoot || '');
            return hci.verifyLayout();
        }).then(function (verify) {
            mark('layout-verified');
            setState('layout-verify', !!verify.ok, verify.ok ? 'P1-00A complete' : (verify.missing || []).join(', '));

            // Phase Z: do not serialize quota/persist/log ahead of PM + SQLite.
            // Register SW in background so first-visit precache does not contend with WASM.
            hciHousekeepingNonBlocking();
            registerSw().then(function (sw) {
                mark('sw-registered');
                setState('sw', !!sw.ok, sw.ok ? ('scope ' + sw.scope) : (sw.error || ''));
            });

            return Promise.all([
                runPmSelfTest().then(function (pmRes) {
                    mark('pm-selftest-done');
                    return pmRes;
                }),
                runDbSelfTest().then(function (dbRes) {
                    mark('db-selftest-done');
                    return dbRes;
                })
            ]);
        }).then(function (pair) {
            var pmRes = pair[0];
            var dbRes = pair[1];
            return runRuntimeSelfTest().then(function (rtRes) {
                mark('runtime-selftest-done');
                return runRouterSelfTest().then(function (routerRes) {
                    mark('router-selftest-done');
                    return runShellSelfTest().then(function (shellRes) {
                        mark('shell-ready');
                        // Phase Z: Shell Ready as soon as shell self-test passes
                        // (platform host usable; BM self-tests continue below).
                        var shellOk = pmRes && pmRes.ok !== false &&
                            dbRes && dbRes.ok !== false &&
                            rtRes && rtRes.ok !== false &&
                            routerRes && routerRes.ok !== false &&
                            shellRes && shellRes.ok !== false;
                        if (shellOk) {
                            setText('boot-status', 'Shell Ready');
                            $('boot-status').className = 'status pass';
                            if (root.document && root.document.documentElement) {
                                root.document.documentElement.setAttribute('data-rateb-v2-shell-ready', '1');
                            }
                            root.dispatchEvent(new CustomEvent('rateb-v2-shell-ready', {
                                detail: { at: Date.now() }
                            }));
                        }
                        return loadPostShellScripts().then(function () {
                            mark('post-shell-scripts-loaded');
                            return runSyncSelfTest().then(function (syncRes) {
                                return runSdkSelfTest().then(function (sdkRes) {
                                    return runBusinessSelfTest().then(function (bmRes) {
                                        return runIdentitySelfTest().then(function (idRes) {
                                            return runInventorySelfTest().then(function (invRes) {
                                                return runProcurementSelfTest().then(function (procRes) {
                                                    return runSalesSelfTest().then(function (salesRes) {
                                                        return runAccountingSelfTest().then(function (acctRes) {
                                                            return runCrmSelfTest().then(function (crmRes) {
                                                                return runHrSelfTest().then(function (hrRes) {
                                                                    return runMfgSelfTest().then(function (mfgRes) {
                                                                        var ok = shellOk &&
                                                                            syncRes && syncRes.ok !== false &&
                                                                            sdkRes && sdkRes.ok !== false &&
                                                                            bmRes && bmRes.ok !== false &&
                                                                            idRes && idRes.ok !== false &&
                                                                            invRes && invRes.ok !== false &&
                                                                            procRes && procRes.ok !== false &&
                                                                            salesRes && salesRes.ok !== false &&
                                                                            acctRes && acctRes.ok !== false &&
                                                                            crmRes && crmRes.ok !== false &&
                                                                            hrRes && hrRes.ok !== false &&
                                                                            mfgRes && mfgRes.ok !== false;
                                                                        setText('boot-status', ok
                                                                            ? 'Phase 17 platform + Identity + Inventory + Procurement + Sales + Accounting + CRM + HR + Manufacturing ready'
                                                                            : (shellOk
                                                                                ? 'Shell Ready — module self-test failed'
                                                                                : 'Phase 17 self-test failed'));
                                                                        $('boot-status').className = 'status ' + (ok ? 'pass' : (shellOk ? 'pass' : 'fail'));
                                                                    });
                                                                });
                                                            });
                                                        });
                                                    });
                                                });
                                            });
                                        });
                                    });
                                });
                            });
                        }).catch(function (loadErr) {
                            setText('boot-status', shellOk
                                ? ('Shell Ready — post-shell load failed: ' + String(loadErr && loadErr.message ? loadErr.message : loadErr))
                                : 'Phase 17 self-test failed');
                            $('boot-status').className = 'status ' + (shellOk ? 'pass' : 'fail');
                        });
                    });
                });
            });
        }).catch(function (err) {
            setText('boot-status', 'Boot failed: ' + String(err && err.message ? err.message : err));
            $('boot-status').className = 'status fail';
            setState('layout-ensure', false, String(err && err.message ? err.message : err));
        });
    }

    if (root.document.readyState === 'loading') {
        root.document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})(typeof window !== 'undefined' ? window : this);
