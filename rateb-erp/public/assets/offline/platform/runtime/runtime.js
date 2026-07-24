/*!
 * RATEB Offline V2 — L1 Runtime (Phase 4)
 * Kernel · Service Locator · Event Bus · Lifecycle · Active package load (HCI only)
 * No router · No UI · No sync · No module SDK · No V1
 */
(function (root) {
    'use strict';

    if (root.RatebOfflineV2Runtime && root.RatebOfflineV2Runtime.__locked) {
        return;
    }

    var RUNTIME_VERSION = '1.0.0-phase4';
    var STATES = {
        CREATED: 'created',
        STARTING: 'starting',
        READY: 'ready',
        DEGRADED: 'degraded',
        STOPPING: 'stopping',
        STOPPED: 'stopped',
        FAILED: 'failed'
    };

    /* ---------- Event bus ---------- */
    function createEventBus() {
        var map = Object.create(null);
        return {
            on: function (ev, fn) {
                if (!map[ev]) {
                    map[ev] = [];
                }
                map[ev].push(fn);
                return function off() {
                    map[ev] = (map[ev] || []).filter(function (f) { return f !== fn; });
                };
            },
            once: function (ev, fn) {
                var off = this.on(ev, function (payload) {
                    off();
                    fn(payload);
                });
                return off;
            },
            off: function (ev, fn) {
                if (!map[ev]) {
                    return;
                }
                map[ev] = map[ev].filter(function (f) { return f !== fn; });
            },
            emit: function (ev, payload) {
                (map[ev] || []).slice().forEach(function (fn) {
                    try {
                        fn(payload);
                    } catch (err) {
                        /* error isolation — never break bus */
                        try {
                            if (map['runtime:error']) {
                                map['runtime:error'].forEach(function (ef) {
                                    try { ef({ source: 'event:' + ev, error: err }); } catch (e2) { /* ignore */ }
                                });
                            }
                        } catch (e3) { /* ignore */ }
                    }
                });
            },
            clear: function () {
                map = Object.create(null);
            }
        };
    }

    /* ---------- Service locator ---------- */
    function createServiceLocator() {
        var regs = Object.create(null);
        return {
            register: function (name, value, opts) {
                opts = opts || {};
                if (!name || typeof name !== 'string') {
                    throw new Error('rt_service_name');
                }
                if (regs[name] && !opts.replace) {
                    throw new Error('rt_service_exists:' + name);
                }
                regs[name] = {
                    value: value,
                    singleton: opts.singleton !== false,
                    instance: opts.singleton === false ? null : (typeof value !== 'function' ? value : null),
                    factory: typeof value === 'function' ? value : null
                };
                return this;
            },
            get: function (name) {
                var r = regs[name];
                if (!r) {
                    throw new Error('rt_service_missing:' + name);
                }
                if (r.factory && (r.instance === null || r.singleton === false)) {
                    var created = r.factory();
                    if (r.singleton) {
                        r.instance = created;
                    }
                    return created;
                }
                return r.instance !== null && r.instance !== undefined ? r.instance : r.value;
            },
            tryGet: function (name) {
                try {
                    return this.get(name);
                } catch (e) {
                    return null;
                }
            },
            has: function (name) {
                return !!regs[name];
            },
            unregister: function (name) {
                delete regs[name];
            },
            list: function () {
                return Object.keys(regs);
            },
            clear: function () {
                regs = Object.create(null);
            }
        };
    }

    /* ---------- Kernel ---------- */
    var bus = createEventBus();
    var locator = createServiceLocator();
    var state = STATES.CREATED;
    var lastError = null;
    var activePackage = null;
    var startedAt = null;
    var healthSnapshot = null;
    /* OP1: critical start vs deferred HCI/package/health. */
    var criticalReady = false;
    var deferredReadyPromise = null;
    var deferredResult = null;
    var startCriticalPromise = null;

    function hci() {
        var h = root.RatebOfflineV2HCI;
        if (!h) {
            throw new Error('rt_hci_missing');
        }
        return h;
    }

    function setState(next, detail) {
        var prev = state;
        state = next;
        bus.emit('runtime:state', { from: prev, to: next, detail: detail || null });
        bus.emit('runtime:' + next, detail || null);
    }

    function isolate(label, fn) {
        try {
            return Promise.resolve().then(fn).catch(function (err) {
                lastError = { label: label, message: String(err && err.message ? err.message : err), at: new Date().toISOString() };
                bus.emit('runtime:error', lastError);
                throw err;
            });
        } catch (err) {
            lastError = { label: label, message: String(err && err.message ? err.message : err), at: new Date().toISOString() };
            bus.emit('runtime:error', lastError);
            return Promise.reject(err);
        }
    }

    /**
     * Load active runtime package materials from HCI only (runtime/*).
     * Does not execute package code (L5 owns modules). Does not use network.
     */
    function loadActivePackage() {
        var H = hci();
        return H.readActivePointer().then(function (active) {
            return H.readJson('runtime/runtime.manifest').catch(function () {
                return null;
            }).then(function (manifest) {
                return H.readFile('runtime/runtime.pkg').then(function (bytes) {
                    return H.sha256Hex(bytes).then(function (sha256) {
                        var info = {
                            activeSlot: active.activeSlot || null,
                            previousSlot: active.previousSlot || null,
                            installId: active.installId || null,
                            status: active.status || null,
                            manifest: manifest,
                            packagePath: 'runtime/runtime.pkg',
                            packageSize: bytes.length,
                            packageSha256: sha256,
                            loadedAt: new Date().toISOString(),
                            source: 'hci:runtime'
                        };
                        if (manifest && manifest.sha256 && String(manifest.sha256).toLowerCase() !== sha256) {
                            info.hashMatch = false;
                            info.warning = 'manifest_sha_mismatch';
                        } else if (manifest && manifest.sha256) {
                            info.hashMatch = true;
                        } else {
                            info.hashMatch = null;
                        }
                        activePackage = info;
                        bus.emit('runtime:package', info);
                        return info;
                    });
                });
            });
        });
    }

    function registerCoreServices() {
        locator.clear();
        locator.register('events', bus, { replace: true });
        locator.register('hci', hci(), { replace: true });
        locator.register('runtime', api, { replace: true });
        locator.register('health', {
            snapshot: function () { return healthSnapshot; },
            check: runHealthChecks
        }, { replace: true });

        if (root.RatebOfflineV2PM) {
            locator.register('pm', root.RatebOfflineV2PM, { replace: true });
        }
        if (root.RatebOfflineV2DB) {
            locator.register('db', root.RatebOfflineV2DB, { replace: true });
        }
    }

    function runHealthChecks() {
        var H = hci();
        var checks = [];
        function add(name, ok, detail) {
            checks.push({ name: name, ok: !!ok, detail: detail || '' });
        }

        add('hci', !!H, H ? H.version : 'missing');
        add('state', state === STATES.READY || state === STATES.STARTING || state === STATES.DEGRADED, state);
        add('secure_context', H.isSecureContext(), '');
        add('locator_hci', locator.has('hci'), '');
        add('locator_events', locator.has('events'), '');
        add('active_package_loaded', !!activePackage, activePackage ? ('size=' + activePackage.packageSize) : '');
        add('pm', locator.has('pm'), locator.has('pm') ? root.RatebOfflineV2PM.version : 'optional');
        add('db', locator.has('db'), locator.has('db') ? root.RatebOfflineV2DB.version : 'optional');

        return H.verifyLayout().then(function (layout) {
            add('layout', layout.ok, layout.ok ? 'P1-00A' : (layout.missing || []).join(','));
            var failed = checks.filter(function (c) {
                return !c.ok && c.name !== 'pm' && c.name !== 'db';
            });
            healthSnapshot = {
                at: new Date().toISOString(),
                ok: failed.length === 0,
                state: state,
                checks: checks,
                failed: failed
            };
            bus.emit('runtime:health', healthSnapshot);
            return healthSnapshot;
        });
    }

    /**
     * OP1 Phase 2 — deferred heavy work (HCI layout, package, health).
     * Never blocks Shell paint. Resolves when background platform is fully ready.
     */
    function runDeferredStart() {
        return isolate('startDeferred', function () {
            return hci().ensureLayout().then(function () {
                return loadActivePackage();
            }).then(function (pkg) {
                var db = locator.tryGet('db');
                var chain = Promise.resolve();
                if (db && typeof db.isOpen === 'function' && db.isOpen() && typeof db.syncInstallPointerFromActiveJson === 'function') {
                    chain = db.syncInstallPointerFromActiveJson().catch(function (err) {
                        bus.emit('runtime:error', { label: 'db_pointer', message: String(err && err.message || err) });
                    });
                }
                return chain.then(function () {
                    return runHealthChecks();
                }).then(function (health) {
                    if (!health.ok) {
                        setState(STATES.DEGRADED, health);
                    } else if (state !== STATES.DEGRADED && state !== STATES.FAILED) {
                        setState(STATES.READY, { package: pkg, deferred: true });
                    }
                    deferredResult = {
                        ok: health.ok,
                        state: state,
                        version: RUNTIME_VERSION,
                        package: pkg,
                        health: health,
                        services: locator.list(),
                        deferred: true
                    };
                    bus.emit('runtime:fullyReady', deferredResult);
                    try {
                        if (root.performance && performance.mark) {
                            performance.mark('rateb-v2-runtime-fully-ready');
                        }
                    } catch (eMark) { /* ignore */ }
                    return deferredResult;
                });
            });
        }).catch(function (err) {
            lastError = lastError || {
                label: 'startDeferred',
                message: String(err && err.message ? err.message : err),
                at: new Date().toISOString()
            };
            if (state !== STATES.FAILED) {
                setState(STATES.DEGRADED, lastError);
            }
            bus.emit('runtime:error', lastError);
            throw err;
        });
    }

    function whenFullyReady() {
        if (deferredResult) {
            return Promise.resolve(deferredResult);
        }
        if (deferredReadyPromise) {
            return deferredReadyPromise;
        }
        if (criticalReady && (state === STATES.READY || state === STATES.DEGRADED)) {
            deferredReadyPromise = runDeferredStart();
            return deferredReadyPromise;
        }
        return start({ full: true });
    }

    /**
     * OP1 Phase 2 — start() critical path registers services only.
     * Heavy HCI/package/health work runs deferred unless opts.full === true.
     */
    function start(opts) {
        opts = opts || {};
        var wantFull = opts.full === true || opts.deferHeavy === false;

        if (startCriticalPromise && !criticalReady) {
            return startCriticalPromise.then(function (res) {
                if (wantFull) {
                    return whenFullyReady();
                }
                return res;
            });
        }

        if (criticalReady && (state === STATES.READY || state === STATES.DEGRADED || state === STATES.STARTING)) {
            if (wantFull) {
                return whenFullyReady();
            }
            return Promise.resolve({
                ok: true,
                state: state,
                already: true,
                critical: true,
                deferred: !!deferredReadyPromise || !!deferredResult
            });
        }

        lastError = null;
        setState(STATES.STARTING);
        startedAt = new Date().toISOString();

        startCriticalPromise = isolate('startCritical', function () {
            registerCoreServices();
            criticalReady = true;
            setState(STATES.READY, { critical: true });
            try {
                if (root.performance && performance.mark) {
                    performance.mark('rateb-v2-runtime-ready');
                }
            } catch (eMark2) { /* ignore */ }
            bus.emit('runtime:criticalReady', { state: state, services: locator.list() });

            if (!deferredReadyPromise && opts.skipDeferred !== true) {
                deferredReadyPromise = runDeferredStart().catch(function (err) {
                    /* Keep critical READY; deferred failures degrade in runDeferredStart. */
                    return {
                        ok: false,
                        state: state,
                        error: String(err && err.message ? err.message : err),
                        deferred: true
                    };
                });
            }

            if (wantFull) {
                return whenFullyReady();
            }

            return {
                ok: true,
                state: state,
                version: RUNTIME_VERSION,
                package: activePackage,
                health: healthSnapshot,
                services: locator.list(),
                critical: true,
                deferred: true
            };
        }).catch(function (err) {
            startCriticalPromise = null;
            setState(STATES.FAILED, lastError);
            return Promise.reject(err);
        });

        return startCriticalPromise;
    }

    function shutdown() {
        if (state === STATES.STOPPED || state === STATES.CREATED) {
            return Promise.resolve({ ok: true, state: state });
        }
        setState(STATES.STOPPING);
        return isolate('shutdown', function () {
            bus.emit('runtime:beforeShutdown', null);
            activePackage = null;
            healthSnapshot = null;
            criticalReady = false;
            deferredReadyPromise = null;
            deferredResult = null;
            startCriticalPromise = null;
            // Keep event listeners for diagnostics; clear service instances except we rebuild on start
            locator.clear();
            setState(STATES.STOPPED);
            return { ok: true, state: state, version: RUNTIME_VERSION };
        }).catch(function (err) {
            setState(STATES.FAILED, lastError);
            return Promise.reject(err);
        });
    }

    function getStatus() {
        return {
            version: RUNTIME_VERSION,
            state: state,
            startedAt: startedAt,
            lastError: lastError,
            package: activePackage,
            health: healthSnapshot,
            services: locator.list()
        };
    }

    /**
     * Public facade reserved for L2/L4/L5 (no router/sync/modules here).
     */
    function createLayerApi() {
        return {
            version: RUNTIME_VERSION,
            getState: function () { return state; },
            getService: function (name) { return locator.get(name); },
            tryService: function (name) { return locator.tryGet(name); },
            hasService: function (name) { return locator.has(name); },
            on: function (ev, fn) { return bus.on(ev, fn); },
            once: function (ev, fn) { return bus.once(ev, fn); },
            emit: function (ev, payload) { return bus.emit(ev, payload); },
            getActivePackage: function () { return activePackage; },
            getHealth: function () { return healthSnapshot; },
            reloadActivePackage: function () {
                return loadActivePackage();
            }
        };
    }

    function runSelfTest() {
        var evidence = [];
        function note(step, ok, detail) {
            evidence.push({ step: step, ok: !!ok, detail: detail || '' });
        }

        var bootNetBefore = performance.getEntriesByType
            ? performance.getEntriesByType('resource').length
            : 0;

        return shutdown().catch(function () { /* ignore */ }).then(function () {
            return start({ full: true });
        }).then(function (res) {
            note('start', res.ok || res.state === STATES.READY || res.state === STATES.DEGRADED, res.state);
            note('service_hci', locator.has('hci'), '');
            note('service_events', locator.has('events'), '');
            note('service_runtime', locator.has('runtime'), '');
            note('service_health', locator.has('health'), '');
            note('package_loaded', !!res.package, res.package ? ('sha=' + String(res.package.packageSha256 || '').slice(0, 12)) : '');
            note('package_hci_source', res.package && res.package.source === 'hci:runtime', res.package && res.package.source);
            note('health', !!(res.health && res.health.ok), res.health ? ('failed=' + res.health.failed.length) : '');
            note('pm_compat', locator.has('pm') === !!root.RatebOfflineV2PM, locator.has('pm') ? 'registered' : 'absent');
            note('db_compat', locator.has('db') === !!root.RatebOfflineV2DB, locator.has('db') ? 'registered' : 'absent');

            var layer = createLayerApi();
            note('layer_api', layer.getState() === state && layer.hasService('hci'), layer.getState());

            return loadActivePackage().then(function (pkg2) {
                note('reload_package', !!pkg2, 'size=' + (pkg2 && pkg2.packageSize));
                return shutdown();
            }).then(function (sd) {
                note('shutdown', sd.ok && sd.state === STATES.STOPPED, sd.state);
                return start({ full: true });
            }).then(function (res2) {
                note('restart', res2.ok || res2.state === STATES.READY || res2.state === STATES.DEGRADED, res2.state);

                var bootNetAfter = performance.getEntriesByType
                    ? performance.getEntriesByType('resource')
                    : [];
                var bad = bootNetAfter.filter(function (r) {
                    return /\/admin(\/|$)/i.test(r.name) || /offline-shell/i.test(r.name) || /pos-sw\.js/i.test(r.name);
                });
                note('zero_network_erp', bad.length === 0, bad.length ? bad[0].name : 'no admin/v1 sw fetch');
                note('resources_delta', true, 'before=' + bootNetBefore + ' after=' + bootNetAfter.length);

                var failed = evidence.filter(function (e) { return !e.ok; });
                return {
                    ok: failed.length === 0,
                    version: RUNTIME_VERSION,
                    state: state,
                    evidence: evidence,
                    failed: failed,
                    status: getStatus()
                };
            });
        }).catch(function (err) {
            note('fatal', false, String(err && err.message ? err.message : err));
            return {
                ok: false,
                version: RUNTIME_VERSION,
                evidence: evidence,
                error: String(err && err.message ? err.message : err)
            };
        });
    }

    var api = {
        __locked: true,
        version: RUNTIME_VERSION,
        STATES: STATES,
        start: start,
        whenFullyReady: whenFullyReady,
        isCriticalReady: function () { return !!criticalReady; },
        shutdown: shutdown,
        getStatus: getStatus,
        getState: function () { return state; },
        services: locator,
        events: bus,
        layerApi: createLayerApi,
        loadActivePackage: loadActivePackage,
        runHealthChecks: runHealthChecks,
        runSelfTest: runSelfTest
    };

    root.RatebOfflineV2Runtime = api;
})(typeof window !== 'undefined' ? window : this);
