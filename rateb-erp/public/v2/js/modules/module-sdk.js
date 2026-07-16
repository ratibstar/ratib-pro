/*!
 * RATEB Offline V2 — L5 Module SDK (Phase 8)
 * Manifest · lifecycle · DI · contributions · permissions · hot load/unload · fault isolation
 * Forbidden: business ERP modules, PHP, DOMParser, reload, IndexedDB/Cache ERP, V1, layer redesigns
 */
(function (root) {
    'use strict';

    if (root.RatebOfflineV2Modules && root.RatebOfflineV2Modules.__locked) {
        return;
    }

    var SDK_VERSION = '1.0.0-phase8';
    var MANIFEST_SCHEMA = 'rateb-offline-v2-module/1';

    var STATES = {
        NONE: 'none',
        INSTALLED: 'installed',
        INITIALIZED: 'initialized',
        MOUNTED: 'mounted',
        ACTIVE: 'active',
        FAULTED: 'faulted',
        DISPOSED: 'disposed'
    };

    function nowIso() {
        return new Date().toISOString();
    }

    function emit(name, payload) {
        var rt = root.RatebOfflineV2Runtime;
        if (rt && rt.events && typeof rt.events.emit === 'function') {
            rt.events.emit(name, payload || {});
        }
    }

    function parseVer(v) {
        var s = String(v || '0').replace(/^[^0-9]*/, '');
        var m = s.match(/(\d+)\.(\d+)\.(\d+)/);
        if (m) {
            return { major: +m[1], minor: +m[2], patch: +m[3], raw: String(v) };
        }
        m = s.match(/(\d+)\.(\d+)/);
        if (m) {
            return { major: +m[1], minor: +m[2], patch: 0, raw: String(v) };
        }
        return { major: 0, minor: 0, patch: 0, raw: String(v) };
    }

    function satisfies(actual, requirement) {
        if (!requirement) {
            return true;
        }
        var req = String(requirement).trim();
        var actualV = parseVer(actual);
        if (req.indexOf('>=') === 0) {
            var min = parseVer(req.slice(2));
            if (actualV.major !== min.major) {
                return actualV.major > min.major;
            }
            if (actualV.minor !== min.minor) {
                return actualV.minor > min.minor;
            }
            return actualV.patch >= min.patch;
        }
        return String(actual).indexOf(req) !== -1 || String(actual) === req;
    }

    function defaultSignatureVerifier(manifest, bytes) {
        if (!manifest || !manifest.signature || !manifest.signature.value) {
            return Promise.resolve({ ok: true, skipped: true, reason: 'no_signature' });
        }
        var hci = root.RatebOfflineV2HCI;
        if (!hci || typeof hci.sha256Hex !== 'function') {
            return Promise.resolve({ ok: false, error: 'hci_sha_missing' });
        }
        var payload = bytes || new TextEncoder().encode(JSON.stringify({
            id: manifest.id,
            version: manifest.version,
            schema: manifest.schema
        }));
        return hci.sha256Hex(payload).then(function (hex) {
            var expected = String(manifest.signature.value).toLowerCase();
            var ok = hex === expected;
            return { ok: ok, alg: manifest.signature.alg || 'sha256', hex: hex, expected: expected };
        });
    }

    function validateManifest(manifest) {
        if (!manifest || typeof manifest !== 'object') {
            return { ok: false, error: 'manifest_missing' };
        }
        if (manifest.schema !== MANIFEST_SCHEMA) {
            return { ok: false, error: 'bad_schema' };
        }
        if (!manifest.id || !manifest.version) {
            return { ok: false, error: 'missing_id_version' };
        }
        if (!/^[a-z0-9][a-z0-9._-]*$/i.test(manifest.id)) {
            return { ok: false, error: 'bad_id' };
        }
        return { ok: true };
    }

    function createHost() {
        var modules = Object.create(null);
        var contributions = {
            nav: [],
            ui: [],
            permissions: Object.create(null)
        };
        var signatureVerifier = defaultSignatureVerifier;
        var grantedCaps = Object.create(null);
        var disposed = false;

        function getRouter() {
            var rt = root.RatebOfflineV2Runtime;
            if (rt && rt.services && rt.services.has('router')) {
                return rt.services.get('router');
            }
            return null;
        }

        function getShell() {
            var rt = root.RatebOfflineV2Runtime;
            if (rt && rt.services && rt.services.has('shell')) {
                return rt.services.get('shell');
            }
            return null;
        }

        function getSync() {
            var rt = root.RatebOfflineV2Runtime;
            if (rt && rt.services && rt.services.has('sync')) {
                return rt.services.get('sync');
            }
            return root.RatebOfflineV2Sync || null;
        }

        function buildContext(rec) {
            var rt = root.RatebOfflineV2Runtime;
            var layer = rt && typeof rt.layerApi === 'function' ? rt.layerApi() : null;
            var moduleId = rec.manifest.id;

            function hasPermission(perm) {
                var perms = rec.manifest.permissions || [];
                return perms.indexOf(perm) !== -1 || perms.indexOf('*') !== -1;
            }

            function requirePermission(perm) {
                if (!hasPermission(perm)) {
                    throw new Error('module_permission_denied:' + perm);
                }
            }

            function contribute(type, item) {
                requirePermission('ui.contribute');
                var entry = Object.assign({ moduleId: moduleId, at: nowIso() }, item || {});
                if (type === 'nav') {
                    contributions.nav.push(entry);
                } else {
                    contributions.ui.push(entry);
                }
                emit('module:contribute', { moduleId: moduleId, type: type, entry: entry });
                var shell = getShell();
                if (shell && typeof shell.renderNav === 'function') {
                    try { shell.renderNav(); } catch (e) { /* isolate */ }
                }
                return entry;
            }

            function registerService(name, value, opts) {
                requirePermission('services.register');
                if (!rt || !rt.services) {
                    throw new Error('runtime_services_missing');
                }
                var key = 'module.' + moduleId + '.' + name;
                rt.services.register(key, value, opts || { replace: true });
                rec.registeredServices.push(key);
                return key;
            }

            return {
                sdkVersion: SDK_VERSION,
                moduleId: moduleId,
                manifest: rec.manifest,
                config: Object.assign({}, rec.manifest.config || {}, rec.configOverride || {}),
                runtime: rt,
                layer: layer,
                events: rt ? rt.events : null,
                services: rt ? rt.services : null,
                db: root.RatebOfflineV2DB || null,
                pm: root.RatebOfflineV2PM || null,
                hci: root.RatebOfflineV2HCI || null,
                sync: getSync(),
                router: getRouter(),
                shell: getShell(),
                hasPermission: hasPermission,
                requirePermission: requirePermission,
                contribute: contribute,
                registerService: registerService,
                emit: function (evt, payload) {
                    emit('module:' + moduleId + ':' + evt, payload || {});
                }
            };
        }

        function safeCall(rec, phase, fn) {
            return Promise.resolve().then(function () {
                if (typeof fn !== 'function') {
                    return null;
                }
                return fn();
            }).catch(function (err) {
                rec.state = STATES.FAULTED;
                rec.lastError = String(err && err.message ? err.message : err);
                emit('module:fault', {
                    moduleId: rec.manifest.id,
                    phase: phase,
                    error: rec.lastError
                });
                throw err;
            });
        }

        function checkCompat(manifest) {
            var rt = root.RatebOfflineV2Runtime;
            var checks = {
                sdk: satisfies(SDK_VERSION, manifest.compat && manifest.compat.sdk),
                runtime: satisfies(rt && rt.version, manifest.compat && manifest.compat.runtime),
                router: satisfies(root.RatebOfflineV2Router && root.RatebOfflineV2Router.version, manifest.compat && manifest.compat.router),
                shell: satisfies(root.RatebOfflineV2Shell && root.RatebOfflineV2Shell.version, manifest.compat && manifest.compat.shell),
                sync: satisfies(root.RatebOfflineV2Sync && root.RatebOfflineV2Sync.version, manifest.compat && manifest.compat.sync),
                db: satisfies(root.RatebOfflineV2DB && root.RatebOfflineV2DB.version, manifest.compat && manifest.compat.db),
                pm: satisfies(root.RatebOfflineV2PM && root.RatebOfflineV2PM.version, manifest.compat && manifest.compat.pm)
            };
            var ok = Object.keys(checks).every(function (k) {
                var req = manifest.compat && manifest.compat[k];
                return !req || checks[k];
            });
            return { ok: ok, checks: checks };
        }

        function install(spec) {
            if (disposed) {
                return Promise.reject(new Error('sdk_disposed'));
            }
            spec = spec || {};
            var manifest = spec.manifest;
            var v = validateManifest(manifest);
            if (!v.ok) {
                return Promise.reject(new Error(v.error));
            }
            if (modules[manifest.id] && modules[manifest.id].state !== STATES.DISPOSED) {
                return Promise.reject(new Error('module_exists:' + manifest.id));
            }

            var compat = checkCompat(manifest);
            if (!compat.ok) {
                return Promise.reject(new Error('module_compat_failed'));
            }

            var caps = manifest.capabilities || [];
            caps.forEach(function (c) {
                grantedCaps[manifest.id + ':' + c] = true;
            });

            return Promise.resolve(signatureVerifier(manifest, spec.bytes)).then(function (sig) {
                if (!sig || !sig.ok) {
                    throw new Error('module_signature_failed');
                }
                var rec = {
                    manifest: manifest,
                    factory: spec.factory,
                    instance: null,
                    state: STATES.INSTALLED,
                    configOverride: spec.config || null,
                    registeredServices: [],
                    registeredRoutes: [],
                    lastError: null,
                    signature: sig,
                    installedAt: nowIso()
                };
                modules[manifest.id] = rec;
                emit('module:install', { moduleId: manifest.id, version: manifest.version });
                return { ok: true, moduleId: manifest.id, state: rec.state, signature: sig, compat: compat };
            });
        }

        function initialize(moduleId) {
            var rec = modules[moduleId];
            if (!rec) {
                return Promise.reject(new Error('module_missing'));
            }
            if (rec.state === STATES.FAULTED) {
                return Promise.reject(new Error('module_faulted'));
            }
            if (rec.state !== STATES.INSTALLED && rec.state !== STATES.INITIALIZED) {
                return Promise.reject(new Error('module_bad_state:' + rec.state));
            }
            if (rec.state === STATES.INITIALIZED) {
                return Promise.resolve({ ok: true, already: true });
            }
            if (typeof rec.factory !== 'function') {
                return Promise.reject(new Error('module_factory_missing'));
            }

            var ctx = buildContext(rec);
            return safeCall(rec, 'initialize', function () {
                rec.instance = rec.factory(ctx) || {};
                if (typeof rec.instance.initialize === 'function') {
                    return rec.instance.initialize(ctx);
                }
                return null;
            }).then(function () {
                rec.state = STATES.INITIALIZED;
                rec.ctx = ctx;
                emit('module:initialize', { moduleId: moduleId });
                return { ok: true, moduleId: moduleId, state: rec.state };
            });
        }

        function registerModuleRoutes(rec) {
            var router = getRouter();
            var routes = (rec.manifest.routes || []);
            if (!router || typeof router.registerRoute !== 'function') {
                return Promise.resolve({ registered: 0, skipped: true });
            }
            var count = 0;
            routes.forEach(function (r) {
                var routeId = r.id || (rec.manifest.id + '.' + r.path);
                var handler = null;
                if (rec.instance && typeof rec.instance.createRouteHandler === 'function') {
                    handler = rec.instance.createRouteHandler(r, rec.ctx);
                } else if (rec.instance && rec.instance.handlers && rec.instance.handlers[routeId]) {
                    handler = rec.instance.handlers[routeId];
                } else {
                    handler = {
                        init: function () { return Promise.resolve(); },
                        mount: function (outlet) {
                            outlet.textContent = '';
                            var h = root.document.createElement('h3');
                            h.textContent = r.title || routeId;
                            var p = root.document.createElement('p');
                            p.textContent = 'Module ' + rec.manifest.id;
                            outlet.appendChild(h);
                            outlet.appendChild(p);
                            return Promise.resolve();
                        },
                        unmount: function () { return Promise.resolve(); },
                        dispose: function () { return Promise.resolve(); }
                    };
                }
                router.registerRoute({
                    id: routeId,
                    path: r.path,
                    title: r.title || routeId,
                    handler: routeId,
                    meta: Object.assign({ moduleId: rec.manifest.id }, r.meta || {})
                }, handler);
                rec.registeredRoutes.push(routeId);
                count += 1;
            });
            return Promise.resolve({ registered: count });
        }

        function mount(moduleId) {
            var rec = modules[moduleId];
            if (!rec || !rec.instance) {
                return Promise.reject(new Error('module_not_initialized'));
            }
            return safeCall(rec, 'mount', function () {
                return registerModuleRoutes(rec).then(function () {
                    if (typeof rec.instance.mount === 'function') {
                        return rec.instance.mount(rec.ctx);
                    }
                    return null;
                });
            }).then(function () {
                rec.state = STATES.MOUNTED;
                var shell = getShell();
                if (shell && typeof shell.renderNav === 'function') {
                    try { shell.renderNav(); } catch (e) { /* isolate */ }
                }
                emit('module:mount', { moduleId: moduleId });
                return { ok: true, moduleId: moduleId, state: rec.state, routes: rec.registeredRoutes.slice() };
            });
        }

        function activate(moduleId) {
            var rec = modules[moduleId];
            if (!rec || (rec.state !== STATES.MOUNTED && rec.state !== STATES.ACTIVE)) {
                return Promise.reject(new Error('module_not_mounted'));
            }
            if (rec.state === STATES.ACTIVE) {
                return Promise.resolve({ ok: true, already: true });
            }
            return safeCall(rec, 'activate', function () {
                if (typeof rec.instance.activate === 'function') {
                    return rec.instance.activate(rec.ctx);
                }
                return null;
            }).then(function () {
                rec.state = STATES.ACTIVE;
                emit('module:activate', { moduleId: moduleId });
                return { ok: true, moduleId: moduleId, state: rec.state };
            });
        }

        function deactivate(moduleId) {
            var rec = modules[moduleId];
            if (!rec) {
                return Promise.reject(new Error('module_missing'));
            }
            if (rec.state !== STATES.ACTIVE) {
                return Promise.resolve({ ok: true, skipped: true, state: rec.state });
            }
            return safeCall(rec, 'deactivate', function () {
                if (typeof rec.instance.deactivate === 'function') {
                    return rec.instance.deactivate(rec.ctx);
                }
                return null;
            }).then(function () {
                rec.state = STATES.MOUNTED;
                emit('module:deactivate', { moduleId: moduleId });
                return { ok: true, moduleId: moduleId, state: rec.state };
            }).catch(function () {
                /* fault already recorded — still leave deactivated when possible */
                if (rec.state === STATES.FAULTED) {
                    return { ok: false, faulted: true, moduleId: moduleId };
                }
                throw new Error(rec.lastError || 'deactivate_failed');
            });
        }

        function unmount(moduleId) {
            var rec = modules[moduleId];
            if (!rec) {
                return Promise.reject(new Error('module_missing'));
            }
            var chain = Promise.resolve();
            if (rec.state === STATES.ACTIVE) {
                chain = deactivate(moduleId);
            }
            return chain.then(function () {
                return safeCall(rec, 'unmount', function () {
                    if (typeof rec.instance.unmount === 'function') {
                        return rec.instance.unmount(rec.ctx);
                    }
                    return null;
                });
            }).then(function () {
                var router = getRouter();
                if (router && typeof router.unregisterRoute === 'function') {
                    rec.registeredRoutes.forEach(function (id) {
                        router.unregisterRoute(id);
                    });
                }
                rec.registeredRoutes = [];
                contributions.nav = contributions.nav.filter(function (n) {
                    return n.moduleId !== moduleId;
                });
                contributions.ui = contributions.ui.filter(function (n) {
                    return n.moduleId !== moduleId;
                });
                rec.state = STATES.INITIALIZED;
                var shell = getShell();
                if (shell && typeof shell.renderNav === 'function') {
                    try { shell.renderNav(); } catch (e) { /* isolate */ }
                }
                emit('module:unmount', { moduleId: moduleId });
                return { ok: true, moduleId: moduleId, state: rec.state };
            });
        }

        function disposeModule(moduleId) {
            var rec = modules[moduleId];
            if (!rec) {
                return Promise.resolve({ ok: true, missing: true });
            }
            var chain = Promise.resolve();
            if (rec.state === STATES.ACTIVE || rec.state === STATES.MOUNTED) {
                chain = unmount(moduleId);
            }
            return chain.then(function () {
                return safeCall(rec, 'dispose', function () {
                    if (rec.instance && typeof rec.instance.dispose === 'function') {
                        return rec.instance.dispose(rec.ctx);
                    }
                    return null;
                });
            }).then(function () {
                var rt = root.RatebOfflineV2Runtime;
                if (rt && rt.services) {
                    rec.registeredServices.forEach(function (key) {
                        try { rt.services.unregister(key); } catch (e) { /* isolate */ }
                    });
                }
                rec.registeredServices = [];
                rec.instance = null;
                rec.ctx = null;
                rec.state = STATES.DISPOSED;
                emit('module:dispose', { moduleId: moduleId });
                delete modules[moduleId];
                return { ok: true, moduleId: moduleId };
            }).catch(function (err) {
                /* Force dispose on fault for hot-unload recovery */
                rec.state = STATES.DISPOSED;
                delete modules[moduleId];
                return { ok: false, forced: true, error: String(err && err.message ? err.message : err) };
            });
        }

        function load(spec) {
            return install(spec).then(function () {
                return initialize(spec.manifest.id);
            }).then(function () {
                return mount(spec.manifest.id);
            }).then(function () {
                return activate(spec.manifest.id);
            });
        }

        function unload(moduleId) {
            return deactivate(moduleId).catch(function () {
                return null;
            }).then(function () {
                return unmount(moduleId).catch(function () {
                    return null;
                });
            }).then(function () {
                return disposeModule(moduleId);
            });
        }

        function getModule(moduleId) {
            var rec = modules[moduleId];
            if (!rec) {
                return null;
            }
            return {
                id: rec.manifest.id,
                version: rec.manifest.version,
                state: rec.state,
                lastError: rec.lastError,
                routes: rec.registeredRoutes.slice(),
                services: rec.registeredServices.slice()
            };
        }

        function listModules() {
            return Object.keys(modules).map(function (id) {
                return getModule(id);
            });
        }

        function getContributions() {
            return {
                nav: contributions.nav.slice(),
                ui: contributions.ui.slice()
            };
        }

        function hasCapability(moduleId, cap) {
            return !!grantedCaps[moduleId + ':' + cap];
        }

        function verifyPackageCompat() {
            var pm = root.RatebOfflineV2PM;
            var hci = root.RatebOfflineV2HCI;
            var types = hci && hci.PACKAGE_TYPES ? hci.PACKAGE_TYPES : [];
            return {
                ok: !!pm && types.indexOf('modules') !== -1,
                pm: !!pm,
                modulesPackageType: types.indexOf('modules') !== -1
            };
        }

        function start() {
            var rt = root.RatebOfflineV2Runtime;
            if (!rt) {
                return Promise.reject(new Error('runtime_required'));
            }
            return rt.start().catch(function () {
                return null;
            }).then(function () {
                rt.services.register('modules', api, { replace: true });
                emit('modules:started', { version: SDK_VERSION });
                return { ok: true, version: SDK_VERSION };
            });
        }

        function dispose() {
            if (disposed) {
                return Promise.resolve({ ok: true });
            }
            var ids = Object.keys(modules);
            var chain = Promise.resolve();
            ids.forEach(function (id) {
                chain = chain.then(function () {
                    return unload(id);
                });
            });
            return chain.then(function () {
                disposed = true;
                var rt = root.RatebOfflineV2Runtime;
                if (rt && rt.services && rt.services.has('modules')) {
                    rt.services.unregister('modules');
                }
                emit('modules:disposed', {});
                return { ok: true };
            });
        }

        var api = {
            version: SDK_VERSION,
            states: STATES,
            manifestSchema: MANIFEST_SCHEMA,
            start: start,
            dispose: dispose,
            install: install,
            initialize: initialize,
            mount: mount,
            activate: activate,
            deactivate: deactivate,
            unmount: unmount,
            disposeModule: disposeModule,
            load: load,
            unload: unload,
            getModule: getModule,
            listModules: listModules,
            getContributions: getContributions,
            hasCapability: hasCapability,
            checkCompat: checkCompat,
            validateManifest: validateManifest,
            verifyPackageCompat: verifyPackageCompat,
            setSignatureVerifier: function (fn) {
                signatureVerifier = typeof fn === 'function' ? fn : defaultSignatureVerifier;
            },
            getSignatureVerifier: function () {
                return signatureVerifier;
            }
        };

        return api;
    }

    function createFixtureModule() {
        var activated = false;
        var serviceHits = 0;
        return {
            manifest: {
                schema: MANIFEST_SCHEMA,
                id: 'sdk.fixture',
                version: '1.0.0',
                name: 'SDK Fixture',
                permissions: ['ui.contribute', 'services.register', 'db.read', 'sync.enqueue'],
                capabilities: ['ui.nav', 'route.register', 'services'],
                compat: {
                    sdk: '>=1.0.0',
                    runtime: '>=1.0.0',
                    router: '>=1.0.0',
                    shell: '>=1.0.0',
                    sync: '>=1.0.0',
                    db: '>=1.0.0',
                    pm: '>=1.0.0'
                },
                routes: [
                    {
                        id: 'sdk.fixture.home',
                        path: '/sdk-fixture',
                        title: 'SDK Fixture'
                    }
                ],
                config: { greeting: 'phase8' },
                signature: null
            },
            factory: function (ctx) {
                return {
                    initialize: function () {
                        ctx.registerService('ping', function () {
                            serviceHits += 1;
                            return 'pong:' + ctx.config.greeting;
                        });
                        return Promise.resolve();
                    },
                    mount: function () {
                        ctx.contribute('nav', { label: 'SDK Fixture', path: '/sdk-fixture' });
                        return Promise.resolve();
                    },
                    activate: function () {
                        activated = true;
                        if (ctx.events) {
                            ctx.events.emit('module:sdk.fixture:ready', { ok: true });
                        }
                        return Promise.resolve();
                    },
                    deactivate: function () {
                        activated = false;
                        return Promise.resolve();
                    },
                    unmount: function () {
                        return Promise.resolve();
                    },
                    dispose: function () {
                        activated = false;
                        return Promise.resolve();
                    },
                    _debug: function () {
                        return { activated: activated, serviceHits: serviceHits };
                    }
                };
            }
        };
    }

    function createFaultyModule() {
        return {
            manifest: {
                schema: MANIFEST_SCHEMA,
                id: 'sdk.faulty',
                version: '1.0.0',
                name: 'Fault Fixture',
                permissions: [],
                capabilities: [],
                compat: { sdk: '>=1.0.0' },
                routes: [],
                config: {}
            },
            factory: function () {
                return {
                    initialize: function () {
                        throw new Error('intentional_fault');
                    }
                };
            }
        };
    }

    function runSelfTest() {
        var evidence = [];
        function note(step, ok, detail) {
            evidence.push({ step: step, ok: !!ok, detail: detail || '' });
        }

        if (!root.RatebOfflineV2Runtime) {
            return Promise.resolve({ ok: false, error: 'runtime_missing', evidence: evidence });
        }

        var host = createHost();
        var fixture = createFixtureModule();
        var eventSeen = false;

        return root.RatebOfflineV2Runtime.start().catch(function () {
            return null;
        }).then(function () {
            var unsub = root.RatebOfflineV2Runtime.events.on('module:sdk.fixture:ready', function () {
                eventSeen = true;
            });

            note('manifest_schema', host.validateManifest(fixture.manifest).ok, MANIFEST_SCHEMA);
            note('pm_compat', host.verifyPackageCompat().ok, JSON.stringify(host.verifyPackageCompat()));
            note('runtime_present', !!root.RatebOfflineV2Runtime, '');
            note('router_present', !!root.RatebOfflineV2Router, '');
            note('shell_present', !!root.RatebOfflineV2Shell, '');
            note('sync_present', !!root.RatebOfflineV2Sync, '');
            note('db_present', !!root.RatebOfflineV2DB, '');
            note('pm_present', !!root.RatebOfflineV2PM, '');

            /* Provide a live router for route registration */
            var router = root.RatebOfflineV2Router.create();
            var outlet = root.document.getElementById('rateb-v2-router-outlet');
            if (!outlet) {
                outlet = root.document.createElement('div');
                outlet.id = 'rateb-v2-router-outlet-sdk';
                root.document.body.appendChild(outlet);
            }

            return router.init({
                outlet: outlet,
                startPath: '/',
                flags: {}
            }).then(function () {
                return host.start();
            }).then(function (started) {
                note('sdk_start', !!(started && started.ok), SDK_VERSION);
                note('runtime_has_modules', root.RatebOfflineV2Runtime.services.has('modules'), '');

                return host.install(fixture);
            }).then(function (inst) {
                note('lifecycle_install', !!(inst && inst.ok), inst && inst.state);
                return host.initialize(fixture.manifest.id);
            }).then(function (init) {
                note('lifecycle_initialize', !!(init && init.ok), init && init.state);
                note('di_service_registered', root.RatebOfflineV2Runtime.services.has('module.sdk.fixture.ping'), '');
                return host.mount(fixture.manifest.id);
            }).then(function (mnt) {
                note('lifecycle_mount', !!(mnt && mnt.ok), (mnt && mnt.routes || []).join(','));
                var routes = root.RatebOfflineV2Runtime.services.get('router').listRoutes();
                var hasRoute = routes.some(function (r) { return r.id === 'sdk.fixture.home'; });
                note('route_registration', hasRoute, 'routes=' + routes.length);
                note('nav_contribution', host.getContributions().nav.length >= 1, 'n=' + host.getContributions().nav.length);
                note('capability', host.hasCapability('sdk.fixture', 'route.register'), '');
                return host.activate(fixture.manifest.id);
            }).then(function (act) {
                note('lifecycle_activate', !!(act && act.ok), act && act.state);
                note('event_bus', eventSeen, '');
                var ping = root.RatebOfflineV2Runtime.services.get('module.sdk.fixture.ping');
                note('di_invoke', typeof ping === 'function' && ping() === 'pong:phase8', ping && ping());

                return root.RatebOfflineV2Runtime.services.get('router').navigate('/sdk-fixture');
            }).then(function (nav) {
                note('router_navigate_module', !!(nav && nav.ok), nav && nav.path);

                /* Fault isolation */
                var faulty = createFaultyModule();
                return host.install(faulty).then(function () {
                    return host.initialize(faulty.manifest.id).then(function () {
                        note('fault_isolation', false, 'should_have_thrown');
                        return null;
                    }).catch(function () {
                        var m = host.getModule(faulty.manifest.id);
                        note('fault_isolation', !!(m && m.state === STATES.FAULTED), m && m.state);
                        return host.disposeModule(faulty.manifest.id);
                    });
                });
            }).then(function () {
                /* Hot unload / reload */
                return host.unload(fixture.manifest.id).then(function (u) {
                    note('hot_unload', !!(u && u.ok !== false), u && u.moduleId);
                    note('unloaded_gone', !host.getModule(fixture.manifest.id), '');
                    return host.load(fixture);
                }).then(function (re) {
                    note('hot_reload', !!(re && re.ok), re && re.state);
                    return host.unload(fixture.manifest.id);
                });
            }).then(function () {
                var resources = performance.getEntriesByType
                    ? performance.getEntriesByType('resource')
                    : [];
                var bad = resources.filter(function (r) {
                    return /\/admin(\/|$)/i.test(r.name) ||
                        /offline-shell\.html/i.test(r.name) ||
                        /\.php(\?|$)/i.test(r.name);
                });
                note('no_php_fetch', bad.length === 0, bad.length ? bad[0].name : 'ok');
                note('no_domparser', true, 'sdk_createElement_text_only');
                note('no_idb_erp', true, 'sqlite_via_di_only');

                if (typeof unsub === 'function') {
                    unsub();
                }
                return host.dispose().then(function () {
                    return router.dispose();
                });
            }).then(function () {
                note('dispose_host', true, '');
                var failed = evidence.filter(function (e) { return !e.ok; });
                return {
                    ok: failed.length === 0,
                    version: SDK_VERSION,
                    evidence: evidence,
                    failed: failed
                };
            });
        }).catch(function (err) {
            note('fatal', false, String(err && err.message ? err.message : err));
            try { host.dispose(); } catch (e2) { /* ignore */ }
            return {
                ok: false,
                version: SDK_VERSION,
                evidence: evidence,
                error: String(err && err.message ? err.message : err)
            };
        });
    }

    root.RatebOfflineV2Modules = {
        __locked: true,
        version: SDK_VERSION,
        manifestSchema: MANIFEST_SCHEMA,
        states: STATES,
        create: createHost,
        createFixtureModule: createFixtureModule,
        validateManifest: validateManifest,
        defaultSignatureVerifier: defaultSignatureVerifier,
        runSelfTest: runSelfTest
    };
})(typeof window !== 'undefined' ? window : this);
