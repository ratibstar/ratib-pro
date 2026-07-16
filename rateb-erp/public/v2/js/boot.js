/*!
 * RATEB Offline V2 — Host boot (Phases 1–10 self-tests).
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

    function whenDbReady() {
        if (root.RatebOfflineV2DB) {
            return Promise.resolve(root.RatebOfflineV2DB);
        }
        return new Promise(function (resolve) {
            var done = false;
            function finish() {
                if (done) {
                    return;
                }
                done = true;
                resolve(root.RatebOfflineV2DB || null);
            }
            root.addEventListener('rateb-v2-db-ready', finish, { once: true });
            setTimeout(finish, 20000);
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

        return hci.ensureLayout().then(function (layout) {
            setState('layout-ensure', !!layout.ok, layout.opfsRoot || '');
            return hci.verifyLayout();
        }).then(function (verify) {
            setState('layout-verify', !!verify.ok, verify.ok ? 'P1-00A complete' : (verify.missing || []).join(', '));
            return hci.getQuota();
        }).then(function (q) {
            if (q.ok) {
                setText('quota', 'usage ' + q.usage + ' / quota ' + q.quota);
            } else {
                setText('quota', q.error || 'n/a');
            }
            return hci.requestPersistence();
        }).then(function (p) {
            setState('persist', !!p.ok, p.persisted ? 'persisted' : 'not persisted (may be browser policy)');
            return hci.appendLog('phase10-host-boot');
        }).then(function () {
            return registerSw();
        }).then(function (sw) {
            setState('sw', !!sw.ok, sw.ok ? ('scope ' + sw.scope) : (sw.error || ''));
            return runPmSelfTest();
        }).then(function (pmRes) {
            return runDbSelfTest().then(function (dbRes) {
                return runRuntimeSelfTest().then(function (rtRes) {
                    return runRouterSelfTest().then(function (routerRes) {
                        return runShellSelfTest().then(function (shellRes) {
                            return runSyncSelfTest().then(function (syncRes) {
                                return runSdkSelfTest().then(function (sdkRes) {
                                    return runBusinessSelfTest().then(function (bmRes) {
                                        return runIdentitySelfTest().then(function (idRes) {
                                            var ok = pmRes && pmRes.ok !== false &&
                                                dbRes && dbRes.ok !== false &&
                                                rtRes && rtRes.ok !== false &&
                                                routerRes && routerRes.ok !== false &&
                                                shellRes && shellRes.ok !== false &&
                                                syncRes && syncRes.ok !== false &&
                                                sdkRes && sdkRes.ok !== false &&
                                                bmRes && bmRes.ok !== false &&
                                                idRes && idRes.ok !== false;
                                            setText('boot-status', ok
                                                ? 'Phase 10 platform + Identity Module ready'
                                                : 'Phase 10 self-test failed');
                                            $('boot-status').className = 'status ' + (ok ? 'pass' : 'fail');
                                        });
                                    });
                                });
                            });
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
