/*!
 * RATEB Offline V2 — POS Offline BusinessModule (Phase 1 Foundation)
 *
 * Isolated POS shell only: routes, identity gate, empty screens.
 * No sales logic, no auto-sync, no DB open on register.
 * Online ERP remains Authentication Authority (AF 2.1).
 */
(function (root) {
    'use strict';

    var Business = root.RatebOfflineV2Business;
    if (!Business || !Business.BusinessModule) {
        return;
    }

    var BusinessModule = Business.BusinessModule;
    var POS_VERSION = '0.1.0-phase1-foundation';

    function PosModule() {
        BusinessModule.call(this, {
            id: 'pos',
            version: POS_VERSION,
            name: 'POS',
            description: 'Offline V2 POS — foundation shell (no sales logic yet).',
            moduleKind: 'pos',
            dependencies: [
                { id: 'identity', version: '>=1.0.0' }
            ],
            permissions: ['ui.contribute', 'services.register', 'db.read', 'sync.enqueue'],
            capabilities: [
                'ui.nav', 'route.register', 'services', 'settings', 'workspace', 'diagnostics',
                'pos.shell'
            ],
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
                { id: 'pos.home', path: '/pos', title: 'POS' },
                { id: 'pos.sales', path: '/pos/sales', title: 'POS Sales' },
                { id: 'pos.settings', path: '/pos/settings', title: 'POS Settings' }
            ],
            config: {
                foundationOnly: true,
                salesLogic: false,
                openDbOnRegister: false,
                startSyncOnActivate: false,
                identityDependency: 'identity'
            }
        });
    }

    PosModule.prototype = Object.create(BusinessModule.prototype);
    PosModule.prototype.constructor = PosModule;

    PosModule.prototype._callIdentity = function (name, arg) {
        return this.callPublished('identity', name, arg);
    };

    /**
     * Enrollment + permission probe via published identity services only.
     * Does not open a POS store / SQLite namespace in Phase 1.
     */
    PosModule.prototype.requireIdentity = function () {
        var self = this;
        return Promise.all([
            self._callIdentity('session'),
            self._callIdentity('claims'),
            self._callIdentity('rbac')
        ]).then(function (parts) {
            var session = parts[0] || {};
            var claims = parts[1];
            var rbac = parts[2];
            if (!claims || !claims.company_id) {
                throw new Error('pos_identity_not_enrolled');
            }
            var perms = (rbac && rbac.permissions) || [];
            var allowed = perms.indexOf('pos.manage') !== -1 ||
                perms.indexOf('pos.view') !== -1 ||
                perms.indexOf('pos.sell') !== -1 ||
                perms.indexOf('*') !== -1;
            return {
                session: session,
                claims: claims,
                rbac: rbac,
                company_id: claims.company_id,
                branch_id: claims.branch_id || 0,
                user_id: claims.user_id,
                unlocked: !!(session && session.unlocked),
                allowed: allowed,
                permissions: perms
            };
        });
    };

    PosModule.prototype._gate = function () {
        return this.requireIdentity().then(function (idCtx) {
            if (!idCtx.allowed) {
                throw new Error('pos_forbidden');
            }
            return idCtx;
        });
    };

    PosModule.prototype.onInitialize = function () {
        var self = this;
        /* Phase 1: no db.open(), no sync.start(), no store. */
        self.exposeService('status', function () {
            return {
                ok: true,
                version: POS_VERSION,
                foundationOnly: true,
                salesLogic: false
            };
        });
        self.exposeService('gate', function () {
            return self._gate();
        });
        self.reportHealth('initialize', true, 'pos_foundation_ready');
        return Promise.resolve();
    };

    PosModule.prototype.onMount = function () {
        this.contributeNav({ label: 'POS', path: '/pos', title: 'POS' });
        this.contributeWorkspace({
            id: 'pos.workspace',
            title: 'POS Offline',
            description: 'Foundation shell — sales logic not implemented'
        });
        this.contributeSettings({
            id: 'pos.foundation_only',
            label: 'Foundation only',
            value: true
        });
        this.reportHealth('mount', true, 'contributions');
        return Promise.resolve();
    };

    PosModule.prototype.onActivate = function (ctx) {
        if (ctx && ctx.events) {
            ctx.events.emit('pos:ready', {
                version: POS_VERSION,
                depends_on: ['identity'],
                foundation_only: true,
                sales_logic: false
            });
        }
        this.reportHealth('activate', true, 'ready');
        return Promise.resolve();
    };

    PosModule.prototype._renderShell = function (outlet, route, idCtx) {
        var title = (route && route.title) || 'POS';
        var path = (route && route.path) || '/pos';
        outlet.textContent = '';

        var h = root.document.createElement('h3');
        h.textContent = title;

        var p = root.document.createElement('p');
        p.textContent = 'POS Offline foundation · path=' + path +
            ' · sales logic not implemented · identity enrolled';

        var meta = root.document.createElement('p');
        meta.textContent = 'company=' + idCtx.company_id +
            ' · user=' + idCtx.user_id +
            ' · unlocked=' + !!idCtx.unlocked;

        outlet.appendChild(h);
        outlet.appendChild(p);
        outlet.appendChild(meta);
        outlet.setAttribute('data-pos-shell', path);
    };

    PosModule.prototype.createRouteHandler = function (route) {
        var self = this;
        return {
            init: function () { return Promise.resolve(); },
            mount: function (outlet) {
                return self._gate().then(function (idCtx) {
                    self._renderShell(outlet, route, idCtx);
                }).catch(function (err) {
                    outlet.textContent = 'POS: ' + String(err && err.message ? err.message : err);
                });
            },
            unmount: function () { return Promise.resolve(); },
            dispose: function () { return Promise.resolve(); }
        };
    };

    PosModule.prototype.getDiagnostics = function () {
        var base = BusinessModule.prototype.getDiagnostics.call(this);
        base.depends_on = ['identity'];
        base.foundation_only = true;
        base.sales_logic = false;
        base.opens_db_on_register = false;
        base.starts_sync_on_activate = false;
        base.never_stores_credentials = true;
        return base;
    };

    function createPosModule() {
        return new PosModule();
    }

    root.RatebOfflineV2Pos = {
        __locked: true,
        version: POS_VERSION,
        PosModule: PosModule,
        create: createPosModule
    };

    if (Business) {
        Business.createPosModule = createPosModule;
        Business.PosModule = PosModule;
    }
})(typeof window !== 'undefined' ? window : this);
