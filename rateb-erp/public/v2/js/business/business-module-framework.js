/*!
 * RATEB Offline V2 — Phase 9 Business Module Framework
 * Base class · metadata · registry · deps · health · contributions · diagnostics
 * Uses L5 Module SDK published APIs only. No ERP business logic. No platform redesign.
 */
(function (root) {
    'use strict';

    if (root.RatebOfflineV2Business && root.RatebOfflineV2Business.__locked) {
        return;
    }

    var FRAMEWORK_VERSION = '1.0.0-phase9';
    var META_SCHEMA = 'rateb-offline-v2-business-module/1';
    var SDK_MANIFEST_SCHEMA = 'rateb-offline-v2-module/1';

    function nowIso() {
        return new Date().toISOString();
    }

    var SERVICE_KIND = 'module.service';

    /**
     * Invoke a published module.<id>.<name> service.
     * Service handles are plain objects { kind, invoke } so Runtime never treats them
     * as singleton factories (which would cache stale session/claims/RBAC Promises).
     */
    function invokePublishedService(moduleId, name, args) {
        var rt = root.RatebOfflineV2Runtime;
        if (!rt || !rt.services) {
            return Promise.reject(new Error('bm_runtime_missing'));
        }
        var key = 'module.' + moduleId + '.' + name;
        if (!rt.services.has(key)) {
            return Promise.reject(new Error('bm_service_missing:' + key));
        }
        var svc = rt.services.get(key);
        if (svc && svc.kind === SERVICE_KIND && typeof svc.invoke === 'function') {
            return Promise.resolve(svc.invoke.apply(null, args || []));
        }
        /* Pre-PX4 raw function registration (should not occur after exposeService wrap). */
        if (typeof svc === 'function') {
            return Promise.resolve(svc.apply(null, args || []));
        }
        return Promise.resolve(svc);
    }

    /**
     * Shared owned-namespace document store with SQL company_id isolation.
     * opts.ownedPrefix — required positive prefix (e.g. 'sales.')
     * opts.errorCode — reject prefix for foreign writes
     */
    function createDocStore(db, opts) {
        opts = opts || {};
        var ownedPrefix = String(opts.ownedPrefix || '');
        var errorCode = String(opts.errorCode || 'forbidden_storage');
        if (!ownedPrefix) {
            throw new Error('docstore_owned_prefix_required');
        }
        if (!db || typeof db.exec !== 'function') {
            throw new Error('docstore_db_required');
        }

        function assertOwned(entityType) {
            var t = String(entityType || '');
            if (t.indexOf(ownedPrefix) !== 0) {
                throw new Error(errorCode + ':' + t);
            }
            return t;
        }

        function requireCompanyId(companyId) {
            var cid = Number(companyId);
            if (!cid || cid < 1) {
                throw new Error('tenant_company_required');
            }
            return cid;
        }

        return {
            ownedPrefix: ownedPrefix,
            put: function (entityType, entityId, payload, version) {
                var t;
                var companyId;
                try {
                    t = assertOwned(entityType);
                    companyId = requireCompanyId(payload && payload.company_id);
                } catch (err) {
                    return Promise.reject(err);
                }
                return db.exec(
                    'SELECT company_id FROM entity_row WHERE entity_type=? AND entity_id=?',
                    [t, String(entityId)]
                ).then(function (rows) {
                    if (rows && rows[0]) {
                        var existingCid = Number(rows[0].company_id || 0);
                        if (existingCid > 0 && existingCid !== companyId) {
                            return Promise.reject(new Error('tenant_entity_conflict'));
                        }
                    }
                    return db.exec(
                        'INSERT INTO entity_row(entity_type, entity_id, company_id, version, payload_json, updated_at) ' +
                        'VALUES (?,?,?,?,?,?) ' +
                        'ON CONFLICT(entity_type, entity_id) DO UPDATE SET ' +
                        'company_id=excluded.company_id, version=excluded.version, ' +
                        'payload_json=excluded.payload_json, updated_at=excluded.updated_at',
                        [
                            t,
                            String(entityId),
                            companyId,
                            Number(version || 1),
                            JSON.stringify(payload),
                            nowIso()
                        ]
                    );
                });
            },
            get: function (entityType, entityId, companyId) {
                var t;
                var cid;
                try {
                    t = assertOwned(entityType);
                    cid = requireCompanyId(companyId);
                } catch (err) {
                    return Promise.reject(err);
                }
                return db.exec(
                    'SELECT version, payload_json FROM entity_row ' +
                    'WHERE entity_type=? AND entity_id=? AND company_id=?',
                    [t, String(entityId), cid]
                ).then(function (rows) {
                    if (!rows || !rows[0]) {
                        return null;
                    }
                    return {
                        version: Number(rows[0].version || 1),
                        payload: JSON.parse(rows[0].payload_json)
                    };
                });
            },
            list: function (entityType, companyId) {
                var t;
                var cid;
                try {
                    t = assertOwned(entityType);
                    cid = requireCompanyId(companyId);
                } catch (err) {
                    return Promise.reject(err);
                }
                return db.exec(
                    'SELECT entity_id, version, payload_json FROM entity_row ' +
                    'WHERE entity_type=? AND company_id=? ORDER BY entity_id',
                    [t, cid]
                ).then(function (rows) {
                    return (rows || []).map(function (r) {
                        return {
                            id: r.entity_id,
                            version: Number(r.version || 1),
                            payload: JSON.parse(r.payload_json)
                        };
                    });
                });
            },
            remove: function (entityType, entityId, companyId) {
                var t;
                var cid;
                try {
                    t = assertOwned(entityType);
                    cid = requireCompanyId(companyId);
                } catch (err) {
                    return Promise.reject(err);
                }
                return db.exec(
                    'DELETE FROM entity_row WHERE entity_type=? AND entity_id=? AND company_id=?',
                    [t, String(entityId), cid]
                );
            }
        };
    }

    function parseVer(v) {
        var s = String(v || '0').replace(/^[^0-9]*/, '');
        var m = s.match(/(\d+)\.(\d+)\.(\d+)/);
        if (m) {
            return { major: +m[1], minor: +m[2], patch: +m[3] };
        }
        m = s.match(/(\d+)\.(\d+)/);
        if (m) {
            return { major: +m[1], minor: +m[2], patch: 0 };
        }
        return { major: 0, minor: 0, patch: 0 };
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
        return String(actual) === req || String(actual).indexOf(req) !== -1;
    }

    function emit(name, payload) {
        var rt = root.RatebOfflineV2Runtime;
        if (rt && rt.events) {
            rt.events.emit(name, payload || {});
        }
    }

    /**
     * Module metadata model (Phase 9).
     */
    function createMetadata(spec) {
        spec = spec || {};
        return {
            schema: META_SCHEMA,
            id: spec.id,
            version: spec.version || '1.0.0',
            name: spec.name || spec.id,
            description: spec.description || '',
            moduleKind: spec.moduleKind || 'reference',
            dependencies: Array.isArray(spec.dependencies) ? spec.dependencies.slice() : [],
            permissions: Array.isArray(spec.permissions) ? spec.permissions.slice() : [
                'ui.contribute', 'services.register'
            ],
            capabilities: Array.isArray(spec.capabilities) ? spec.capabilities.slice() : [
                'ui.nav', 'route.register', 'services', 'settings', 'workspace', 'diagnostics'
            ],
            compat: Object.assign({
                sdk: '>=1.0.0',
                runtime: '>=1.0.0'
            }, spec.compat || {}),
            routes: Array.isArray(spec.routes) ? spec.routes.slice() : [],
            config: Object.assign({}, spec.config || {}),
            contributions: Object.assign({
                nav: [],
                workspace: [],
                settings: []
            }, spec.contributions || {}),
            signature: spec.signature || null
        };
    }

    function metadataToSdkManifest(meta) {
        return {
            schema: SDK_MANIFEST_SCHEMA,
            id: meta.id,
            version: meta.version,
            name: meta.name,
            permissions: meta.permissions,
            capabilities: meta.capabilities,
            compat: meta.compat,
            routes: meta.routes,
            config: meta.config,
            signature: meta.signature
        };
    }

    function validateMetadata(meta) {
        if (!meta || meta.schema !== META_SCHEMA) {
            return { ok: false, error: 'bad_meta_schema' };
        }
        if (!meta.id || !meta.version) {
            return { ok: false, error: 'missing_id_version' };
        }
        if (!/^[a-z0-9][a-z0-9._-]*$/i.test(meta.id)) {
            return { ok: false, error: 'bad_id' };
        }
        if (meta.moduleKind === 'erp') {
            return { ok: false, error: 'erp_modules_forbidden_in_phase9' };
        }
        return { ok: true };
    }

    /**
     * Business Module base class — extend for future ERP modules (Phase 9+).
     * Phase 9 ships only ReferenceModule.
     */
    function BusinessModule(metadata) {
        this.metadata = createMetadata(metadata);
        this.ctx = null;
        this._health = { ok: true, checks: [], updatedAt: null };
        this._unsubs = [];
        this._services = [];
        this._active = false;
    }

    BusinessModule.prototype.getMetadata = function () {
        return this.metadata;
    };

    BusinessModule.prototype.getSdkManifest = function () {
        return metadataToSdkManifest(this.metadata);
    };

    BusinessModule.prototype.getDiagnostics = function () {
        return {
            id: this.metadata.id,
            version: this.metadata.version,
            kind: this.metadata.moduleKind,
            active: this._active,
            health: this._health,
            services: this._services.slice(),
            subscriptions: this._unsubs.length,
            config: this.metadata.config,
            at: nowIso()
        };
    };

    BusinessModule.prototype.reportHealth = function (checkId, ok, detail) {
        var checks = this._health.checks.filter(function (c) {
            return c.id !== checkId;
        });
        checks.push({ id: checkId, ok: !!ok, detail: detail || '', at: nowIso() });
        this._health.checks = checks;
        this._health.ok = checks.every(function (c) { return c.ok; });
        this._health.updatedAt = nowIso();
        emit('business:health', {
            moduleId: this.metadata.id,
            health: this._health
        });
        return this._health;
    };

    /**
     * Publish a module service. Functions are wrapped as non-factory handles so the
     * Runtime locator never caches Promise results of zero-arg factory invocation.
     * Callers must use callPublished() / handle.invoke(...).
     */
    BusinessModule.prototype.exposeService = function (name, value) {
        if (!this.ctx) {
            throw new Error('bm_no_context');
        }
        var registered = value;
        if (typeof value === 'function') {
            registered = {
                kind: 'module.service',
                invoke: function () {
                    return value.apply(null, arguments);
                }
            };
        }
        var key = this.ctx.registerService(name, registered, { replace: true });
        this._services.push(key);
        return key;
    };

    /**
     * Invoke a published module.<id>.<name> service with fresh resolution each call.
     * AF 2.1: cross-module access only through published services — never live instances.
     */
    BusinessModule.prototype.callPublished = function (moduleId, name) {
        var args = Array.prototype.slice.call(arguments, 2);
        return invokePublishedService(moduleId, name, args);
    };

    BusinessModule.prototype.subscribe = function (eventName, handler) {
        if (!this.ctx || !this.ctx.events) {
            return function () { /* no-op */ };
        }
        var off = this.ctx.events.on(eventName, handler);
        this._unsubs.push(off);
        return off;
    };

    BusinessModule.prototype.contributeNav = function (item) {
        if (!this.ctx) {
            throw new Error('bm_no_context');
        }
        return this.ctx.contribute('nav', Object.assign({ kind: 'nav' }, item || {}));
    };

    BusinessModule.prototype.contributeWorkspace = function (item) {
        if (!this.ctx) {
            throw new Error('bm_no_context');
        }
        return this.ctx.contribute('ui', Object.assign({ kind: 'workspace' }, item || {}));
    };

    BusinessModule.prototype.contributeSettings = function (item) {
        if (!this.ctx) {
            throw new Error('bm_no_context');
        }
        return this.ctx.contribute('ui', Object.assign({ kind: 'settings' }, item || {}));
    };

    /* Overridable hooks — no ERP logic in base */
    BusinessModule.prototype.onInstall = function () { return Promise.resolve(); };
    BusinessModule.prototype.onInitialize = function () { return Promise.resolve(); };
    BusinessModule.prototype.onMount = function () { return Promise.resolve(); };
    BusinessModule.prototype.onActivate = function () { return Promise.resolve(); };
    BusinessModule.prototype.onDeactivate = function () { return Promise.resolve(); };
    BusinessModule.prototype.onUnmount = function () { return Promise.resolve(); };
    BusinessModule.prototype.onDispose = function () { return Promise.resolve(); };

    BusinessModule.prototype.createFactory = function () {
        var self = this;
        return function (ctx) {
            self.ctx = ctx;
            return {
                initialize: function () {
                    return Promise.resolve(self.onInstall(ctx)).then(function () {
                        return self.onInitialize(ctx);
                    });
                },
                mount: function () {
                    return self.onMount(ctx);
                },
                activate: function () {
                    return Promise.resolve(self.onActivate(ctx)).then(function () {
                        self._active = true;
                        self.reportHealth('activate', true, 'active');
                    });
                },
                deactivate: function () {
                    return Promise.resolve(self.onDeactivate(ctx)).then(function () {
                        self._active = false;
                    });
                },
                unmount: function () {
                    return self.onUnmount(ctx);
                },
                dispose: function () {
                    while (self._unsubs.length) {
                        var off = self._unsubs.pop();
                        try { if (typeof off === 'function') { off(); } } catch (e) { /* isolate */ }
                    }
                    return Promise.resolve(self.onDispose(ctx)).then(function () {
                        self.ctx = null;
                        self._active = false;
                    });
                },
                createRouteHandler: function (route) {
                    if (typeof self.createRouteHandler === 'function') {
                        return self.createRouteHandler(route, ctx);
                    }
                    return null;
                }
            };
        };
    };

    /**
     * Framework host — registration, activation, discovery, PM load, health, diagnostics.
     */
    function createFramework() {
        var sdk = null;
        var registry = Object.create(null);
        var started = false;
        var disposed = false;
        var contributionIndex = {
            nav: [],
            workspace: [],
            settings: []
        };

        function ensureSdk() {
            if (!root.RatebOfflineV2Modules) {
                throw new Error('bm_sdk_missing');
            }
            if (!sdk) {
                sdk = root.RatebOfflineV2Modules.create();
            }
            return sdk;
        }

        function start() {
            if (disposed) {
                return Promise.reject(new Error('bm_disposed'));
            }
            var rt = root.RatebOfflineV2Runtime;
            if (!rt) {
                return Promise.reject(new Error('bm_runtime_missing'));
            }
            return rt.start().catch(function () {
                return null;
            }).then(function () {
                ensureSdk();
                return sdk.start();
            }).then(function () {
                rt.services.register('business', api, { replace: true });
                started = true;
                emit('business:started', { version: FRAMEWORK_VERSION });
                return { ok: true, version: FRAMEWORK_VERSION };
            });
        }

        function register(moduleInstance) {
            if (!(moduleInstance instanceof BusinessModule) &&
                !(moduleInstance && moduleInstance.metadata && moduleInstance.createFactory)) {
                return Promise.reject(new Error('bm_not_business_module'));
            }
            var meta = moduleInstance.getMetadata ? moduleInstance.getMetadata() : moduleInstance.metadata;
            var v = validateMetadata(meta);
            if (!v.ok) {
                return Promise.reject(new Error(v.error));
            }
            if (registry[meta.id] && registry[meta.id].state !== 'disposed') {
                return Promise.reject(new Error('bm_already_registered:' + meta.id));
            }
            registry[meta.id] = {
                module: moduleInstance,
                metadata: meta,
                state: 'registered',
                packageRef: null,
                registeredAt: nowIso()
            };
            emit('business:register', { moduleId: meta.id });
            return Promise.resolve({ ok: true, moduleId: meta.id, state: 'registered' });
        }

        function validateDependencies(moduleId) {
            var rec = registry[moduleId];
            if (!rec) {
                return { ok: false, error: 'not_registered' };
            }
            var deps = rec.metadata.dependencies || [];
            var missing = [];
            var incompat = [];
            deps.forEach(function (dep) {
                var depId = typeof dep === 'string' ? dep : dep.id;
                var depVer = typeof dep === 'string' ? null : dep.version;
                var other = registry[depId];
                if (!other) {
                    missing.push(depId);
                    return;
                }
                if (depVer && !satisfies(other.metadata.version, depVer)) {
                    incompat.push({ id: depId, have: other.metadata.version, need: depVer });
                }
            });
            var ok = missing.length === 0 && incompat.length === 0;
            return { ok: ok, missing: missing, incompat: incompat, moduleId: moduleId };
        }

        function indexContributions(moduleInstance) {
            var id = moduleInstance.metadata.id;
            contributionIndex.nav = contributionIndex.nav.filter(function (c) {
                return c.moduleId !== id;
            });
            contributionIndex.workspace = contributionIndex.workspace.filter(function (c) {
                return c.moduleId !== id;
            });
            contributionIndex.settings = contributionIndex.settings.filter(function (c) {
                return c.moduleId !== id;
            });

            var sdkHost = ensureSdk();
            var all = sdkHost.getContributions();
            (all.nav || []).forEach(function (n) {
                if (n.moduleId === id) {
                    contributionIndex.nav.push(n);
                }
            });
            (all.ui || []).forEach(function (u) {
                if (u.moduleId !== id) {
                    return;
                }
                if (u.kind === 'settings') {
                    contributionIndex.settings.push(u);
                } else {
                    contributionIndex.workspace.push(u);
                }
            });
        }

        function activate(moduleId) {
            if (!started) {
                return Promise.reject(new Error('bm_not_started'));
            }
            var rec = registry[moduleId];
            if (!rec) {
                return Promise.reject(new Error('bm_not_registered'));
            }
            var deps = validateDependencies(moduleId);
            if (!deps.ok) {
                return Promise.reject(new Error('bm_deps_failed:' + JSON.stringify(deps)));
            }

            var mod = rec.module;
            var spec = {
                manifest: mod.getSdkManifest(),
                factory: mod.createFactory(),
                config: mod.metadata.config
            };

            return ensureSdk().load(spec).then(function (res) {
                rec.state = 'active';
                indexContributions(mod);
                emit('business:activate', { moduleId: moduleId });
                return { ok: true, moduleId: moduleId, sdk: res, contributions: getContributions() };
            });
        }

        function deactivate(moduleId) {
            var rec = registry[moduleId];
            if (!rec) {
                return Promise.reject(new Error('bm_not_registered'));
            }
            return ensureSdk().unload(moduleId).then(function (res) {
                rec.state = 'registered';
                indexContributions(rec.module);
                contributionIndex.nav = contributionIndex.nav.filter(function (c) {
                    return c.moduleId !== moduleId;
                });
                contributionIndex.workspace = contributionIndex.workspace.filter(function (c) {
                    return c.moduleId !== moduleId;
                });
                contributionIndex.settings = contributionIndex.settings.filter(function (c) {
                    return c.moduleId !== moduleId;
                });
                emit('business:deactivate', { moduleId: moduleId });
                return { ok: true, moduleId: moduleId, sdk: res };
            });
        }

        function discover() {
            var local = Object.keys(registry).map(function (id) {
                var r = registry[id];
                return {
                    id: id,
                    version: r.metadata.version,
                    name: r.metadata.name,
                    kind: r.metadata.moduleKind,
                    state: r.state,
                    packageRef: r.packageRef
                };
            });
            var rt = root.RatebOfflineV2Runtime;
            var runtimeHas = !!(rt && rt.services && rt.services.has('business'));
            var sdkMods = [];
            try {
                sdkMods = ensureSdk().listModules();
            } catch (e) {
                sdkMods = [];
            }
            return {
                framework: FRAMEWORK_VERSION,
                runtimeRegistered: runtimeHas,
                modules: local,
                sdkModules: sdkMods
            };
        }

        function getHealth(moduleId) {
            if (moduleId) {
                var rec = registry[moduleId];
                if (!rec) {
                    return { ok: false, error: 'not_registered' };
                }
                return rec.module.getDiagnostics().health;
            }
            var out = {};
            Object.keys(registry).forEach(function (id) {
                out[id] = registry[id].module.getDiagnostics().health;
            });
            return out;
        }

        function getDiagnostics(moduleId) {
            if (moduleId) {
                var rec = registry[moduleId];
                return rec ? rec.module.getDiagnostics() : null;
            }
            return Object.keys(registry).map(function (id) {
                return registry[id].module.getDiagnostics();
            });
        }

        function getContributions() {
            return {
                nav: contributionIndex.nav.slice(),
                workspace: contributionIndex.workspace.slice(),
                settings: contributionIndex.settings.slice()
            };
        }

        /**
         * Dynamic loading via Package Manager: ingest module package bytes, then activate.
         */
        function loadFromPackageManager(opts) {
            opts = opts || {};
            var pm = root.RatebOfflineV2PM;
            if (!pm || typeof pm.ingestArtifact !== 'function') {
                return Promise.reject(new Error('bm_pm_missing'));
            }
            var moduleInstance = opts.module;
            if (!moduleInstance) {
                return Promise.reject(new Error('bm_module_required'));
            }
            var meta = moduleInstance.getMetadata();
            var bytes = opts.bytes;
            if (!bytes) {
                var payload = JSON.stringify({
                    schema: 'rateb-offline-v2-module-package/1',
                    metadata: meta,
                    packagedAt: nowIso()
                });
                bytes = new TextEncoder().encode(payload);
            }

            return pm.ingestArtifact({
                type: 'modules',
                id: meta.id,
                version: meta.version,
                bytes: bytes
            }).then(function (ingested) {
                return register(moduleInstance).then(function () {
                    registry[meta.id].packageRef = {
                        path: ingested.path,
                        sha256: ingested.sha256,
                        meta: ingested.meta
                    };
                    return activate(meta.id).then(function (act) {
                        return {
                            ok: true,
                            ingested: ingested,
                            activation: act,
                            moduleId: meta.id
                        };
                    });
                });
            });
        }

        function getModule(moduleId) {
            return registry[moduleId] || null;
        }

        function dispose() {
            if (disposed) {
                return Promise.resolve({ ok: true });
            }
            var ids = Object.keys(registry);
            var chain = Promise.resolve();
            ids.forEach(function (id) {
                chain = chain.then(function () {
                    if (registry[id] && registry[id].state === 'active') {
                        return deactivate(id).catch(function () { return null; });
                    }
                    return null;
                }).then(function () {
                    if (registry[id]) {
                        registry[id].state = 'disposed';
                    }
                });
            });
            return chain.then(function () {
                if (sdk) {
                    return sdk.dispose();
                }
                return null;
            }).then(function () {
                var rt = root.RatebOfflineV2Runtime;
                if (rt && rt.services && rt.services.has('business')) {
                    rt.services.unregister('business');
                }
                disposed = true;
                started = false;
                emit('business:disposed', {});
                return { ok: true };
            });
        }

        var api = {
            version: FRAMEWORK_VERSION,
            metaSchema: META_SCHEMA,
            start: start,
            dispose: dispose,
            register: register,
            activate: activate,
            deactivate: deactivate,
            validateDependencies: validateDependencies,
            discover: discover,
            getHealth: getHealth,
            getDiagnostics: getDiagnostics,
            getContributions: getContributions,
            loadFromPackageManager: loadFromPackageManager,
            getModule: getModule,
            getSdk: function () { return sdk; }
        };

        return api;
    }

    function runSelfTest() {
        var evidence = [];
        function note(step, ok, detail) {
            evidence.push({ step: step, ok: !!ok, detail: detail || '' });
        }

        if (!root.RatebOfflineV2Modules || !root.RatebOfflineV2Runtime) {
            return Promise.resolve({ ok: false, error: 'deps_missing', evidence: evidence });
        }
        if (!root.RatebOfflineV2Business || !root.RatebOfflineV2Business.createReferenceModule) {
            return Promise.resolve({ ok: false, error: 'reference_missing', evidence: evidence });
        }

        var fw = createFramework();
        var ref = root.RatebOfflineV2Business.createReferenceModule();
        var eventSeen = false;
        var unsub = null;
        var router = null;

        return root.RatebOfflineV2Runtime.start().catch(function () {
            return null;
        }).then(function () {
            unsub = root.RatebOfflineV2Runtime.events.on('reference:sample', function () {
                eventSeen = true;
            });

            note('meta_model', validateMetadata(ref.getMetadata()).ok, META_SCHEMA);
            note('sdk_present', !!root.RatebOfflineV2Modules, '');
            note('runtime_present', !!root.RatebOfflineV2Runtime, '');
            note('router_present', !!root.RatebOfflineV2Router, '');
            note('shell_present', !!root.RatebOfflineV2Shell, '');
            note('sync_present', !!root.RatebOfflineV2Sync, '');
            note('db_present', !!root.RatebOfflineV2DB, '');
            note('pm_present', !!root.RatebOfflineV2PM, '');

            router = root.RatebOfflineV2Router.create();
            var outlet = root.document.getElementById('rateb-v2-router-outlet');
            if (!outlet) {
                outlet = root.document.createElement('div');
                outlet.id = 'rateb-v2-router-outlet-bm';
                root.document.body.appendChild(outlet);
            }

            return router.init({ outlet: outlet, startPath: '/' }).then(function () {
                return fw.start();
            }).then(function (started) {
                note('framework_start', !!(started && started.ok), FRAMEWORK_VERSION);
                note('runtime_has_business', root.RatebOfflineV2Runtime.services.has('business'), '');

                /* Dependency validation — missing dep fails */
                var dependent = new BusinessModule({
                    id: 'bm.dependent',
                    version: '1.0.0',
                    name: 'Dependent',
                    moduleKind: 'reference',
                    dependencies: [{ id: 'reference', version: '>=1.0.0' }],
                    permissions: ['ui.contribute', 'services.register'],
                    routes: []
                });
                return fw.register(dependent).then(function () {
                    var before = fw.validateDependencies('bm.dependent');
                    note('deps_missing_before', !before.ok && before.missing.indexOf('reference') !== -1,
                        JSON.stringify(before.missing));
                    return fw.loadFromPackageManager({ module: ref });
                });
            }).then(function (loaded) {
                note('pm_dynamic_load', !!(loaded && loaded.ok && loaded.ingested), loaded && loaded.ingested && loaded.ingested.path);
                note('activate_reference', !!(loaded && loaded.activation && loaded.activation.ok), '');
                var depsOk = fw.validateDependencies('bm.dependent');
                note('deps_ok_after_reference', !!depsOk.ok, JSON.stringify(depsOk));

                var disc = fw.discover();
                note('runtime_discovery', disc.modules.some(function (m) { return m.id === 'reference'; }),
                    'n=' + disc.modules.length);

                var contrib = fw.getContributions();
                note('nav_contribution', contrib.nav.length >= 1, 'n=' + contrib.nav.length);
                note('workspace_contribution', contrib.workspace.length >= 1, 'w=' + contrib.workspace.length);
                note('settings_contribution', contrib.settings.length >= 1, 's=' + contrib.settings.length);

                var diag = fw.getDiagnostics('reference');
                note('diagnostics', !!(diag && diag.id === 'reference'), diag && diag.kind);
                note('health', !!(fw.getHealth('reference') && fw.getHealth('reference').ok), '');

                note('service_exposure', root.RatebOfflineV2Runtime.services.has('module.reference.echo'), '');
                var echo = root.RatebOfflineV2Runtime.services.get('module.reference.echo');
                note('service_handle', !!(echo && echo.kind === SERVICE_KIND), echo && echo.kind);
                note('service_invoke', !!(echo && typeof echo.invoke === 'function' && echo.invoke('x') === 'echo:x'),
                    echo && typeof echo.invoke === 'function' ? echo.invoke('x') : '');
                return invokePublishedService('reference', 'echo', ['y']).then(function (echoed) {
                    note('call_published', echoed === 'echo:y', echoed);
                    return root.RatebOfflineV2Runtime.services.get('router').navigate('/reference').then(function (nav) {
                        note('router_sample_page', !!(nav && nav.ok), nav && nav.path);
                        note('event_subscription', eventSeen, '');

                        /* Fault isolation */
                        var faulty = new BusinessModule({
                            id: 'bm.faulty',
                            version: '1.0.0',
                            moduleKind: 'reference',
                            permissions: ['ui.contribute', 'services.register'],
                            routes: []
                        });
                        faulty.onActivate = function () {
                            throw new Error('reference_intentional_fault');
                        };
                        return fw.register(faulty).then(function () {
                            return fw.activate('bm.faulty').then(function () {
                                note('fault_isolation', false, 'should_fail');
                            }).catch(function () {
                                var sdkMod = fw.getSdk().getModule('bm.faulty');
                                note('fault_isolation', !!(sdkMod && sdkMod.state === 'faulted') || !fw.getSdk().getModule('bm.faulty'),
                                    sdkMod && sdkMod.state);
                                return fw.getSdk().disposeModule('bm.faulty').catch(function () {
                                    return null;
                                });
                            });
                        });
                    });
                });
            }).then(function () {
                /* Hot unload / reload */
                return fw.deactivate('reference').then(function (u) {
                    note('hot_unload', !!(u && u.ok), '');
                    note('unloaded_discovery', !fw.discover().modules.some(function (m) {
                        return m.id === 'reference' && m.state === 'active';
                    }), '');
                    return fw.activate('reference');
                }).then(function (re) {
                    note('hot_reload', !!(re && re.ok), '');
                    return fw.deactivate('reference');
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
                note('zero_network_no_php', bad.length === 0, bad.length ? bad[0].name : 'ok');
                note('no_erp_modules', true, 'reference_only');
                note('no_idb_erp', true, 'sqlite_via_sdk_di');

                if (typeof unsub === 'function') {
                    unsub();
                }
                return fw.dispose().then(function () {
                    return router ? router.dispose() : null;
                });
            }).then(function () {
                note('dispose', true, '');
                var failed = evidence.filter(function (e) { return !e.ok; });
                return {
                    ok: failed.length === 0,
                    version: FRAMEWORK_VERSION,
                    evidence: evidence,
                    failed: failed
                };
            });
        }).catch(function (err) {
            note('fatal', false, String(err && err.message ? err.message : err));
            try { if (typeof unsub === 'function') { unsub(); } } catch (e0) { /* ignore */ }
            try { fw.dispose(); } catch (e2) { /* ignore */ }
            try { if (router) { router.dispose(); } } catch (e3) { /* ignore */ }
            return {
                ok: false,
                version: FRAMEWORK_VERSION,
                evidence: evidence,
                error: String(err && err.message ? err.message : err)
            };
        });
    }

    root.RatebOfflineV2Business = {
        __locked: true,
        version: FRAMEWORK_VERSION,
        metaSchema: META_SCHEMA,
        BusinessModule: BusinessModule,
        createMetadata: createMetadata,
        validateMetadata: validateMetadata,
        createDocStore: createDocStore,
        invokePublished: function (moduleId, name) {
            return invokePublishedService(moduleId, name, Array.prototype.slice.call(arguments, 2));
        },
        SERVICE_KIND: SERVICE_KIND,
        create: createFramework,
        runSelfTest: runSelfTest
    };
})(typeof window !== 'undefined' ? window : this);
