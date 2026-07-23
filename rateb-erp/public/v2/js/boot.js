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
        var expectedCache = 'rateb-offline-v2-bootstrap-v6';
        if (!('serviceWorker' in root.navigator)) {
            return Promise.resolve({ ok: false, error: 'sw_unsupported' });
        }

        function waitForLatestWorker(reg) {
            var candidate = reg.installing || reg.waiting;
            if (!candidate) {
                return root.navigator.serviceWorker.ready.then(function (readyReg) {
                    return readyReg.active;
                });
            }
            if (candidate.state === 'activated') {
                return Promise.resolve(candidate);
            }
            return new Promise(function (resolve, reject) {
                var timer = root.setTimeout(function () {
                    reject(new Error('sw_activation_timeout'));
                }, 15000);
                candidate.addEventListener('statechange', function () {
                    if (candidate.state === 'activated') {
                        root.clearTimeout(timer);
                        resolve(candidate);
                    } else if (candidate.state === 'redundant') {
                        root.clearTimeout(timer);
                        reject(new Error('sw_install_redundant'));
                    }
                });
            });
        }

        var swUrl = new URL('sw.js', root.location.href).href;
        var scopeUrl = new URL('./', root.location.href).href;

        function acquireRegistration() {
            return root.navigator.serviceWorker.getRegistration(scopeUrl).then(function (existing) {
                if (!existing) {
                    return root.navigator.serviceWorker.register(swUrl, {
                        scope: './',
                        updateViaCache: 'none'
                    });
                }
                /*
                 * First offline launch must reuse the installed worker. Calling
                 * register/update while offline can start a transient install pass
                 * over the same cache and make verification race cache.put().
                 */
                if (root.navigator.onLine === false) {
                    return existing;
                }
                return existing.update().then(function () {
                    return existing;
                }).catch(function () {
                    return existing;
                });
            });
        }

        return acquireRegistration().then(function (reg) {
            return waitForLatestWorker(reg).then(function (worker) {
                if (!worker) {
                    throw new Error('sw_active_worker_missing');
                }
                return new Promise(function (resolve, reject) {
                    var channel = new MessageChannel();
                    var timer = root.setTimeout(function () {
                        reject(new Error('sw_precache_verify_timeout'));
                    }, 10000);
                    channel.port1.onmessage = function (event) {
                        root.clearTimeout(timer);
                        var result = event.data || {};
                        if (!result.ok || result.cache !== expectedCache) {
                            reject(new Error(
                                'sw_precache_incomplete:' +
                                (result.missing || []).join(',') +
                                ':cache=' + (result.cache || 'none')
                            ));
                            return;
                        }
                        resolve({
                            ok: true,
                            scope: reg.scope,
                            cache: result.cache,
                            cached: result.cached,
                            required: result.required
                        });
                    };
                    worker.postMessage({ type: 'RATEB_V2_VERIFY_PRECACHE' }, [channel.port2]);
                });
            });
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
                // Phase Z: never abort on offline/network errors — only on definitive HTTP miss while online.
                if (root.navigator && root.navigator.onLine === false) {
                    return;
                }
                probeStarted = true;
                var vendorUrl = new URL(
                    '../assets/offline/platform/db/vendor/sqlite/index.mjs',
                    root.location.href
                ).href;
                root.fetch(vendorUrl, { method: 'HEAD', cache: 'no-cache', credentials: 'same-origin' })
                    .then(function (res) {
                        if (res && (res.status === 404 || res.status === 0)) {
                            finish();
                        }
                    })
                    .catch(function () {
                        /* keep waiting until maxMs — offline/SW miss is not a definitive absence */
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
        var runtime = root.RatebOfflineV2Runtime;
        var shellApi = root.RatebOfflineV2Shell;
        var scriptLoads = Object.create(null);

        function mark(name) {
            try {
                if (root.performance && performance.mark) {
                    performance.mark('rateb-v2-' + name);
                }
            } catch (eMark) { /* ignore */ }
        }

        function ready(name, detail) {
            mark(name + '-ready');
            try {
                root.dispatchEvent(new CustomEvent('rateb-v2-' + name + '-ready', {
                    detail: Object.assign({ at: Date.now() }, detail || {})
                }));
            } catch (eEvent) { /* ignore */ }
        }

        function loadScript(src) {
            var abs = new URL(src, root.location.href).href;
            if (scriptLoads[abs]) {
                return scriptLoads[abs];
            }
            scriptLoads[abs] = new Promise(function (resolve, reject) {
                var existing = root.document.querySelector('script[src="' + abs + '"]');
                if (existing) {
                    resolve(abs);
                    return;
                }
                var s = root.document.createElement('script');
                s.src = abs;
                s.async = true;
                s.onload = function () { resolve(abs); };
                s.onerror = function () { reject(new Error('script_load_failed:' + src)); };
                root.document.body.appendChild(s);
            });
            return scriptLoads[abs];
        }

        function scheduleBackground(fn) {
            if (typeof root.requestIdleCallback === 'function') {
                root.requestIdleCallback(function () { fn(); }, { timeout: 1200 });
            } else {
                root.setTimeout(fn, 0);
            }
        }

        function requestedPath() {
            try {
                var u = new URL(root.location.href);
                if (u.hash && u.hash.indexOf('#/') === 0) {
                    return u.hash.slice(1);
                }
                return u.searchParams.get('r') || '/';
            } catch (e) {
                return '/';
            }
        }

        function moduleFromPath(path) {
            var id = String(path || '/').replace(/^\/+/, '').split('/')[0];
            var supported = {
                identity: true,
                inventory: true,
                procurement: true,
                sales: true,
                accounting: true,
                crm: true,
                hr: true,
                mfg: true
            };
            return supported[id] ? id : null;
        }

        function hciHousekeeping() {
            return hci.getQuota().then(function (q) {
                setText('quota', q.ok ? ('usage ' + q.usage + ' / quota ' + q.quota) : (q.error || 'n/a'));
                return hci.requestPersistence();
            }).then(function (p) {
                setState('persist', !!p.ok, p.persisted ? 'persisted' : 'not persisted (may be browser policy)');
                return hci.appendLog('perf-host-boot');
            }).catch(function () {
                setText('quota', 'n/a');
            });
        }

        function initializeStorageAndPlatform() {
            mark('background-start');
            var pmPromise = loadScript('./js/package-manager.js').then(function () {
                if (root.RatebOfflineV2PM && runtime && runtime.services) {
                    runtime.services.register('pm', root.RatebOfflineV2PM, { replace: true });
                }
                ready('pm');
                return root.RatebOfflineV2PM;
            });

            var dbUrl = new URL(
                '../assets/offline/platform/db/sqlite-runtime.js',
                root.location.href
            ).href;
            /* Fix3: import + register DB API only — do not open WASM/SQLite until a store needs it. */
            var dbPromise = import(dbUrl).then(function (mod) {
                var db = mod.default || root.RatebOfflineV2DB;
                if (!db) {
                    throw new Error('db_module_missing');
                }
                if (typeof db.register === 'function') {
                    return db.register().then(function () {
                        return db;
                    });
                }
                return db;
            }).then(function (db) {
                if (runtime && runtime.services) {
                    runtime.services.register('db', db, { replace: true });
                }
                setText('db-version', db.version);
                setState('db-selftest', true, 'registered (lazy open)');
                ready('db', {
                    registered: true,
                    open: !!(db.isOpen && db.isOpen())
                });
                return db;
            }).catch(function (err) {
                setState('db-selftest', false, String(err && err.message ? err.message : err));
                return null;
            });

            var platformPromise = Promise.all([
                loadScript('../assets/offline/platform/sync/sync-engine.js'),
                loadScript('./js/modules/module-sdk.js'),
                loadScript('../assets/offline/platform/support/business-module-framework.js')
            ]).then(function () {
                ready('background-platform');
                return true;
            });

            return Promise.all([pmPromise, dbPromise, platformPromise]).then(function (parts) {
                var pm = parts[0];
                var db = parts[1];
                if (!db) {
                    throw new Error('db_bootstrap_failed');
                }
                var syncApi = root.RatebOfflineV2Sync;
                if (!syncApi || typeof syncApi.create !== 'function') {
                    throw new Error('sync_bootstrap_failed');
                }
                /* Fix2: register Sync for writers (enqueue) without activating DB sync cycles. */
                var sync = syncApi.create({ intervalMs: 60000 });
                root.RatebOfflineV2ActiveSync = sync;
                var registerFn = typeof sync.register === 'function'
                    ? sync.register.bind(sync)
                    : sync.start.bind(sync);
                return registerFn({ intervalMs: 60000 }).then(function (reg) {
                    if (!runtime.services.has('sync')) {
                        throw new Error('sync_not_registered');
                    }
                    mark('background-platform-done');
                    ready('sync', {
                        registered: !!(reg && reg.ok !== false),
                        started: !!(sync.isStarted && sync.isStarted()),
                        offline: typeof navigator !== 'undefined' ? navigator.onLine === false : null
                    });
                    return { pm: pm, db: db, sync: sync };
                });
            });
        }

        /*
         * Fix1: route-based BusinessModule registry.
         * Cold boot loads only the requested module + declared dependencies.
         * Other modules load on navigate (shell stubs + wrapped router.navigate).
         * Identity remains the sole published local-identity API boundary.
         */
        var BUSINESS_MODULE_REGISTRY = [
            {
                id: 'identity',
                title: 'Identity',
                routes: ['/identity'],
                script: '../assets/offline/platform/identity/identity-module.js',
                globalName: 'RatebOfflineV2Identity',
                dependencies: []
            },
            {
                id: 'inventory',
                title: 'Inventory',
                routes: ['/inventory'],
                script: './js/business/inventory-module.js',
                globalName: 'RatebOfflineV2Inventory',
                dependencies: ['identity']
            },
            {
                id: 'procurement',
                title: 'Procurement',
                routes: ['/procurement'],
                script: './js/business/procurement-module.js',
                globalName: 'RatebOfflineV2Procurement',
                dependencies: ['identity', 'inventory']
            },
            {
                id: 'sales',
                title: 'Sales',
                routes: ['/sales'],
                script: './js/business/sales-module.js',
                globalName: 'RatebOfflineV2Sales',
                dependencies: ['identity', 'inventory']
            },
            {
                id: 'accounting',
                title: 'Accounting',
                routes: ['/accounting'],
                script: './js/business/accounting-module.js',
                globalName: 'RatebOfflineV2Accounting',
                dependencies: ['identity', 'inventory']
            },
            {
                id: 'crm',
                title: 'CRM',
                routes: ['/crm'],
                script: './js/business/crm-module.js',
                globalName: 'RatebOfflineV2Crm',
                dependencies: ['identity']
            },
            {
                id: 'hr',
                title: 'HR',
                routes: ['/hr'],
                script: './js/business/hr-module.js',
                globalName: 'RatebOfflineV2Hr',
                dependencies: ['identity']
            },
            {
                id: 'mfg',
                title: 'Manufacturing',
                routes: ['/mfg'],
                script: './js/business/manufacturing-module.js',
                globalName: 'RatebOfflineV2Mfg',
                dependencies: ['identity', 'inventory']
            }
        ];
        var businessModuleById = Object.create(null);
        BUSINESS_MODULE_REGISTRY.forEach(function (entry) {
            businessModuleById[entry.id] = entry;
        });
        var businessActivateState = Object.create(null);
        var businessEnsureTail = Promise.resolve();
        var lazyNavigateWrapped = false;

        function resolveModuleLoadOrder(targetId) {
            var order = [];
            var seen = Object.create(null);
            function visit(id) {
                if (!id || seen[id]) {
                    return;
                }
                var entry = businessModuleById[id];
                if (!entry) {
                    throw new Error('bm_registry_unknown:' + id);
                }
                seen[id] = true;
                (entry.dependencies || []).forEach(visit);
                order.push(id);
            }
            visit(targetId);
            return order;
        }

        function resolveModuleOrderForPath(path) {
            var id = moduleFromPath(path);
            if (!id) {
                return [];
            }
            return resolveModuleLoadOrder(id);
        }

        function ensureBusinessFramework() {
            if (root.RatebOfflineV2ActiveBusiness) {
                return Promise.resolve(root.RatebOfflineV2ActiveBusiness);
            }
            var business = root.RatebOfflineV2Business;
            if (!business || !business.create) {
                return Promise.reject(new Error('business_framework_missing'));
            }
            var fw = business.create();
            root.RatebOfflineV2ActiveBusiness = fw;
            return fw.start().then(function () {
                return fw;
            });
        }

        function removeLazyModuleStub(router, moduleId) {
            if (!router || typeof router.unregisterRoute !== 'function') {
                return;
            }
            try {
                router.unregisterRoute('lazy.' + moduleId);
            } catch (eUnreg) { /* ignore */ }
        }

        function registerLazyModuleStubs(router) {
            if (!router || typeof router.registerRoute !== 'function') {
                return;
            }
            BUSINESS_MODULE_REGISTRY.forEach(function (entry) {
                var stubId = 'lazy.' + entry.id;
                var homePath = entry.routes && entry.routes[0] ? entry.routes[0] : ('/' + entry.id);
                /* Avoid colliding with a real module route already registered. */
                try {
                    var existing = router.listRoutes ? router.listRoutes() : [];
                    var alreadyStub = existing.some(function (r) {
                        return r && r.id === stubId;
                    });
                    if (alreadyStub) {
                        return;
                    }
                    var hasReal = existing.some(function (r) {
                        return r && r.path === homePath && String(r.id || '').indexOf('lazy.') !== 0;
                    });
                    if (hasReal) {
                        return;
                    }
                } catch (eList) { /* continue */ }
                router.registerRoute({
                    id: stubId,
                    path: homePath,
                    title: entry.title || entry.id,
                    handler: stubId,
                    meta: { moduleId: entry.id, lazyStub: true }
                }, {
                    init: function () { return Promise.resolve(); },
                    mount: function (outlet) {
                        if (outlet) {
                            outlet.textContent = 'Loading ' + (entry.title || entry.id) + '…';
                        }
                        return ensureBusinessModulesForPath(homePath).then(function () {
                            return router.navigate(homePath, { replace: true });
                        });
                    },
                    unmount: function () { return Promise.resolve(); },
                    dispose: function () { return Promise.resolve(); }
                });
            });
            try {
                var shell = root.RatebOfflineV2AppShell;
                if (shell && typeof shell.renderNav === 'function') {
                    shell.renderNav();
                }
            } catch (eNav) { /* ignore */ }
        }

        function loadAndActivateBusinessModule(fw, moduleId) {
            if (businessActivateState[moduleId] === 'active') {
                return Promise.resolve(moduleId);
            }
            var entry = businessModuleById[moduleId];
            if (!entry) {
                return Promise.reject(new Error('bm_registry_unknown:' + moduleId));
            }
            var appShell = root.RatebOfflineV2AppShell;
            var router = appShell && appShell.getRouter ? appShell.getRouter() : null;

            return loadScript(entry.script).then(function () {
                var api = root[entry.globalName];
                if (!api || typeof api.create !== 'function') {
                    throw new Error('module_factory_missing:' + moduleId);
                }
                var existing = fw.getModule ? fw.getModule(moduleId) : null;
                var registerPromise = Promise.resolve();
                if (!existing || existing.state === 'disposed') {
                    registerPromise = fw.register(api.create());
                }
                return registerPromise.then(function () {
                    var rec = fw.getModule ? fw.getModule(moduleId) : null;
                    if (rec && rec.state === 'active') {
                        businessActivateState[moduleId] = 'active';
                        removeLazyModuleStub(router, moduleId);
                        return moduleId;
                    }
                    removeLazyModuleStub(router, moduleId);
                    return fw.activate(moduleId).then(function () {
                        businessActivateState[moduleId] = 'active';
                        try {
                            var shell = root.RatebOfflineV2AppShell;
                            if (shell && typeof shell.renderNav === 'function') {
                                shell.renderNav();
                            }
                        } catch (eRender) { /* ignore */ }
                        return moduleId;
                    });
                });
            });
        }

        function ensureBusinessModulesForPath(path) {
            var order = resolveModuleOrderForPath(path);
            businessEnsureTail = businessEnsureTail.then(function () {
                return ensureBusinessFramework().then(function (fw) {
                    if (!order.length) {
                        return [];
                    }
                    var chain = Promise.resolve();
                    order.forEach(function (id) {
                        chain = chain.then(function () {
                            return loadAndActivateBusinessModule(fw, id);
                        });
                    });
                    return chain.then(function () {
                        return order.slice();
                    });
                });
            });
            return businessEnsureTail;
        }

        function installLazyNavigateWrapper(router) {
            if (!router || lazyNavigateWrapped || typeof router.navigate !== 'function') {
                return;
            }
            lazyNavigateWrapped = true;
            var origNavigate = router.navigate.bind(router);
            router.navigate = function (toPath, opts) {
                return ensureBusinessModulesForPath(toPath).then(function () {
                    return origNavigate(toPath, opts);
                });
            };
            root.RatebOfflineV2EnsureModules = ensureBusinessModulesForPath;
        }

        function initializeBusinessModules(path, platform) {
            var requestedId = moduleFromPath(path);

            return ensureBusinessFramework().then(function (fw) {
                var appShell = root.RatebOfflineV2AppShell;
                var router = appShell && appShell.getRouter ? appShell.getRouter() : null;
                installLazyNavigateWrapper(router);
                registerLazyModuleStubs(router);

                return ensureBusinessModulesForPath(path).then(function (loaded) {
                    if (router && typeof router.navigate === 'function') {
                        return router.navigate(path, { replace: true }).then(function () {
                            return loaded;
                        });
                    }
                    return loaded;
                }).then(function (loaded) {
                    var activeId = requestedId || null;
                    var registered = loaded && loaded.length ? loaded.slice() : [];
                    ready('active-module', {
                        moduleId: activeId,
                        registeredModules: registered
                    });
                    try {
                        if (activeId) {
                            root.document.documentElement.setAttribute('data-rateb-v2-active-module', activeId);
                        } else {
                            root.document.documentElement.removeAttribute('data-rateb-v2-active-module');
                        }
                        root.document.documentElement.setAttribute('data-rateb-v2-business-modules-ready', '1');
                    } catch (eAttr) { /* ignore */ }
                    return {
                        id: activeId,
                        registeredModules: registered,
                        framework: fw,
                        platform: platform
                    };
                });
            });
        }

        function runDeferredDiagnostics(platform) {
            var diag = $('rateb-v2-diagnostics');
            if (!diag) {
                return;
            }
            try {
                var u = new URL(root.location.href);
                if (u.searchParams.get('diagnostics') !== '1') {
                    return;
                }
            } catch (eUrl) {
                return;
            }
            diag.hidden = false;
            scheduleBackground(function () {
                Promise.all([
                    runtime.runHealthChecks().then(function (res) {
                        setState('rt-selftest', !!res.ok, 'background health');
                    }),
                    platform.db ? platform.db.integrityCheck().then(function (res) {
                        setState('db-selftest', !!res.ok, 'background integrity');
                    }) : Promise.resolve(),
                    platform.pm ? platform.pm.getActive().then(function (active) {
                        setState('pm-selftest', true, 'active=' + (active.activeSlot || 'none'));
                    }) : Promise.resolve()
                ]).then(function () {
                    mark('diagnostics-done');
                }).catch(function () { /* diagnostics never block UI */ });
            });
        }

        mark('boot-start');
        if (!hci || !runtime || !shellApi || typeof shellApi.create !== 'function') {
            setText('boot-status', 'Platform script missing');
            return;
        }
        ready('hci', { version: hci.version });
        setText('hci-version', hci.version);
        setText('layout-id', hci.layoutId);

        var path = requestedPath();
        var requestedModuleId = moduleFromPath(path);
        var routeSeen = false;
        var swPromise = registerSw().then(function (sw) {
            setState('sw', !!sw.ok, sw.ok
                ? ('scope ' + sw.scope + ' · cached ' + sw.cached + '/' + sw.required)
                : (sw.error || ''));
            ready('sw', sw);
            if (!sw.ok) {
                root.document.documentElement.setAttribute('data-rateb-v2-precache-ready', '0');
                throw new Error(sw.error || 'sw_offline_bootstrap_failed');
            }
            root.document.documentElement.setAttribute('data-rateb-v2-precache-ready', '1');
            ready('precache', {
                cache: sw.cache,
                cached: sw.cached,
                required: sw.required
            });
            return sw;
        });
        if (runtime.events) {
            runtime.events.on('router:afterNavigate', function (payload) {
                var actualPath = payload && payload.path ? payload.path : '/';
                if (requestedModuleId && actualPath !== path) {
                    mark('bootstrap-route-ready');
                    return;
                }
                if (routeSeen) {
                    return;
                }
                routeSeen = true;
                ready('route', {
                    path: actualPath
                });
                root.document.documentElement.setAttribute('data-rateb-v2-route-ready', '1');
            });
        }

        var appShell = shellApi.create();
        root.RatebOfflineV2AppShell = appShell;
        var mountPromise = appShell.mount($('rateb-v2-shell-root'), {
            startPath: path
        });
        mark('shell-rendered');
        root.document.documentElement.setAttribute('data-rateb-v2-interactive', '1');
        ready('interactive');

        mountPromise.then(function () {
            ready('runtime', { state: runtime.getState() });
            ready('router');
            if (!routeSeen && !requestedModuleId) {
                ready('route', { path: path });
                root.document.documentElement.setAttribute('data-rateb-v2-route-ready', '1');
            }
            setText('boot-status', 'Shell Ready');
            var status = $('boot-status');
            if (status) {
                status.className = 'status pass';
            }
            root.document.documentElement.setAttribute('data-rateb-v2-shell-ready', '1');
            ready('shell', { path: path });

            scheduleBackground(function () {
                hciHousekeeping();
                swPromise.then(function () {
                    return initializeStorageAndPlatform();
                }).then(function (platform) {
                    return initializeBusinessModules(path, platform).then(function () {
                        runDeferredDiagnostics(platform);
                        mark('background-ready');
                        root.document.documentElement.setAttribute('data-rateb-v2-offline-ready', '1');
                        ready('offline-bootstrap', {
                            path: path,
                            identity: true,
                            sqlite: 'lazy',
                            businessModules: true
                        });
                    });
                }).catch(function (err) {
                    root.document.documentElement.setAttribute('data-rateb-v2-offline-ready', '0');
                    setText('boot-status', 'Offline bootstrap failed: ' +
                        String(err && err.message ? err.message : err));
                    var failedStatus = $('boot-status');
                    if (failedStatus) {
                        failedStatus.className = 'status fail';
                    }
                    try {
                        console.warn('[RATEB V2 PERF] background init', err && err.message);
                    } catch (eLog) { /* ignore */ }
                });
            });
        }).catch(function (err) {
            setText('boot-status', 'Boot failed: ' + String(err && err.message ? err.message : err));
            var status = $('boot-status');
            if (status) {
                status.className = 'status fail';
            }
        });
    }

    if (root.document.readyState === 'loading') {
        root.document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})(typeof window !== 'undefined' ? window : this);
