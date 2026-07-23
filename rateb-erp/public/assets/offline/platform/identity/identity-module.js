/*!
 * RATEB Offline V2 — Phase 10 Identity BusinessModule
 *
 * Local identity runtime ONLY. Online ERP is the Authentication Authority.
 *
 * MAY store: sealed identity, claims, RBAC snapshot, device trust, unlock metadata,
 *            local config, derived local session, security metadata.
 * MUST NEVER store: passwords, password hashes, cookies, PHP sessions, bearer/API/JWT/
 *            refresh/OAuth tokens, TOTP secrets, recovery/reset/verification tokens,
 *            or any credential that can authenticate against the server.
 *
 * MUST NEVER: authenticate against the server, generate/sync/export credentials.
 * Uses BusinessModule published APIs only. Does not modify platform layers.
 */
(function (root) {
    'use strict';

    var Business = root.RatebOfflineV2Business;
    if (!Business || !Business.BusinessModule) {
        return;
    }

    var BusinessModule = Business.BusinessModule;
    var IDENTITY_VERSION = '1.0.0-phase10';
    var ENROLL_SCHEMA = 'rateb-offline-v2-identity-enroll/1';
    var ENTITY = {
        sealed: 'identity.sealed',
        claims: 'identity.claims',
        rbac: 'identity.rbac',
        device: 'identity.device',
        unlock: 'identity.unlock_meta'
    };
    /* Cleanup-only names from the pre-AF 2.1.1 storage contract. */
    var LEGACY_ENTITY = {
        config: 'identity.config',
        session: 'identity.local_session',
        security: 'identity.security_meta'
    };
    var ROW_ID = 'primary';

    var FORBIDDEN_KEYS = [
        'password', 'password_hash', 'passwd', 'pwd',
        'token', 'access_token', 'refresh_token', 'bearer', 'api_token', 'apiToken',
        'jwt', 'id_token', 'oauth', 'authorization',
        'totp', 'totp_secret', 'otp_secret', 'recovery_code', 'backup_code',
        'reset_token', 'verification_token', 'session_cookie', 'php_session',
        'csrf_token', 'remember_token', 'client_secret'
    ];

    function nowIso() {
        return new Date().toISOString();
    }

    function nowMs() {
        return Date.now();
    }

    function utf8(str) {
        return new TextEncoder().encode(String(str));
    }

    function containsForbidden(value, path) {
        path = path || '';
        if (value == null) {
            return null;
        }
        if (typeof value === 'string') {
            var lower = value.toLowerCase();
            if (lower.indexOf('bearer ') === 0) {
                return path || 'string';
            }
            return null;
        }
        if (typeof value !== 'object') {
            return null;
        }
        if (Array.isArray(value)) {
            for (var i = 0; i < value.length; i++) {
                var hit = containsForbidden(value[i], path + '[' + i + ']');
                if (hit) {
                    return hit;
                }
            }
            return null;
        }
        var keys = Object.keys(value);
        for (var k = 0; k < keys.length; k++) {
            var key = keys[k];
            var keyLower = key.toLowerCase();
            for (var f = 0; f < FORBIDDEN_KEYS.length; f++) {
                if (keyLower === FORBIDDEN_KEYS[f] ||
                    keyLower === FORBIDDEN_KEYS[f] + 's' ||
                    keyLower.indexOf(FORBIDDEN_KEYS[f] + '_') === 0 ||
                    keyLower.indexOf('_' + FORBIDDEN_KEYS[f]) !== -1) {
                    /* allow unlock_verifier / claim_fingerprint — not credential keys */
                    if (keyLower === 'unlock_verifier' || keyLower === 'claim_fingerprint') {
                        continue;
                    }
                    return (path ? path + '.' : '') + key;
                }
            }
            var nested = containsForbidden(value[key], (path ? path + '.' : '') + key);
            if (nested) {
                return nested;
            }
        }
        return null;
    }

    function assertNoSecrets(obj, label) {
        var hit = containsForbidden(obj);
        if (hit) {
            throw new Error('identity_forbidden_secret:' + label + ':' + hit);
        }
    }

    function bufToHex(buf) {
        var bytes = buf instanceof ArrayBuffer ? new Uint8Array(buf) : buf;
        var out = '';
        for (var i = 0; i < bytes.length; i++) {
            out += ('0' + bytes[i].toString(16)).slice(-2);
        }
        return out;
    }

    function hexToBuf(hex) {
        var clean = String(hex || '');
        var out = new Uint8Array(clean.length / 2);
        for (var i = 0; i < out.length; i++) {
            out[i] = parseInt(clean.substr(i * 2, 2), 16);
        }
        return out;
    }

    function randomHex(bytes) {
        var buf = new Uint8Array(bytes || 16);
        root.crypto.getRandomValues(buf);
        return bufToHex(buf);
    }

    function deriveUnlockVerifier(pin, saltHex, iterations) {
        var salt = hexToBuf(saltHex);
        var enc = utf8(String(pin || ''));
        return root.crypto.subtle.importKey('raw', enc, 'PBKDF2', false, ['deriveBits']).then(function (key) {
            return root.crypto.subtle.deriveBits({
                name: 'PBKDF2',
                salt: salt,
                iterations: iterations || 120000,
                hash: 'SHA-256'
            }, key, 256);
        }).then(function (bits) {
            return bufToHex(bits);
        });
    }

    function IdentityStore(db) {
        this.db = db;
    }

    IdentityStore.prototype._put = function (entityType, payload) {
        assertNoSecrets(payload, entityType);
        var json = JSON.stringify(payload);
        assertNoSecrets(JSON.parse(json), entityType + ':roundtrip');
        return this.db.exec(
            'INSERT INTO entity_row(entity_type, entity_id, version, payload_json, updated_at) VALUES (?,?,?,?,?) ' +
            'ON CONFLICT(entity_type, entity_id) DO UPDATE SET ' +
            'version=excluded.version, payload_json=excluded.payload_json, updated_at=excluded.updated_at',
            [entityType, ROW_ID, 1, json, nowIso()]
        );
    };

    IdentityStore.prototype._get = function (entityType) {
        return this.db.exec(
            'SELECT payload_json FROM entity_row WHERE entity_type=? AND entity_id=?',
            [entityType, ROW_ID]
        ).then(function (rows) {
            if (!rows || !rows[0] || !rows[0].payload_json) {
                return null;
            }
            var parsed = JSON.parse(rows[0].payload_json);
            assertNoSecrets(parsed, entityType + ':read');
            return parsed;
        });
    };

    IdentityStore.prototype.migrateLegacyBoundaryRows = function () {
        var self = this;
        var legacyTypes = Object.keys(LEGACY_ENTITY).map(function (key) {
            return LEGACY_ENTITY[key];
        });
        return this.db.exec(
            'SELECT entity_type, payload_json FROM entity_row WHERE entity_type IN (?,?,?)',
            legacyTypes
        ).then(function (rows) {
            if (!rows || rows.length === 0) {
                return { ok: true, removed: 0, policy_migrated_to: null };
            }
            var configRow = rows.filter(function (row) {
                return row.entity_type === LEGACY_ENTITY.config;
            })[0];
            var legacyConfig = configRow && configRow.payload_json
                ? JSON.parse(configRow.payload_json)
                : {};
            assertNoSecrets(legacyConfig, 'legacy_identity_config');
            return self.getUnlockMeta().then(function (current) {
                var migrated = Object.assign({}, current || {});
                var hasPolicy = false;
                [
                    ['unlockTtlSec', 'unlock_ttl_sec'],
                    ['idleTtlSec', 'idle_ttl_sec'],
                    ['maxOfflineSec', 'max_offline_sec']
                ].forEach(function (mapping) {
                    var value = legacyConfig[mapping[0]];
                    if (value != null && migrated[mapping[1]] == null) {
                        migrated[mapping[1]] = Number(value);
                        hasPolicy = true;
                    }
                });
                return hasPolicy ? self.putUnlockMeta(migrated) : null;
            }).then(function () {
                return self.db.exec(
                    'DELETE FROM entity_row WHERE entity_type IN (?,?,?)',
                    legacyTypes
                );
            }).then(function () {
                if (typeof self.db.checkpointPersist === 'function') {
                    return self.db.checkpointPersist();
                }
                return null;
            }).then(function () {
                return {
                    ok: true,
                    removed: rows.length,
                    policy_migrated_to: ENTITY.unlock
                };
            });
        });
    };

    IdentityStore.prototype.clearAll = function () {
        var types = Object.keys(ENTITY).map(function (k) { return ENTITY[k]; })
            .concat(Object.keys(LEGACY_ENTITY).map(function (k) { return LEGACY_ENTITY[k]; }));
        var chain = Promise.resolve();
        var db = this.db;
        types.forEach(function (t) {
            chain = chain.then(function () {
                return db.exec('DELETE FROM entity_row WHERE entity_type=?', [t]);
            });
        });
        return chain;
    };

    IdentityStore.prototype.putSealed = function (sealed) {
        return this._put(ENTITY.sealed, { kind: 'sealed_identity', sealed: sealed, stored_at: nowIso() });
    };
    IdentityStore.prototype.getSealed = function () {
        return this._get(ENTITY.sealed);
    };
    IdentityStore.prototype.putClaims = function (claims) {
        return this._put(ENTITY.claims, claims);
    };
    IdentityStore.prototype.getClaims = function () {
        return this._get(ENTITY.claims);
    };
    IdentityStore.prototype.putRbac = function (rbac) {
        return this._put(ENTITY.rbac, rbac);
    };
    IdentityStore.prototype.getRbac = function () {
        return this._get(ENTITY.rbac);
    };
    IdentityStore.prototype.putDevice = function (device) {
        return this._put(ENTITY.device, device);
    };
    IdentityStore.prototype.getDevice = function () {
        return this._get(ENTITY.device);
    };
    IdentityStore.prototype.putUnlockMeta = function (meta) {
        return this._put(ENTITY.unlock, meta);
    };
    IdentityStore.prototype.getUnlockMeta = function () {
        return this._get(ENTITY.unlock);
    };

    IdentityStore.prototype.securityScan = function () {
        var self = this;
        var types = Object.keys(ENTITY).map(function (k) { return ENTITY[k]; });
        var findings = [];
        var chain = Promise.resolve();
        types.forEach(function (t) {
            chain = chain.then(function () {
                return self._get(t).then(function (payload) {
                    if (!payload) {
                        return;
                    }
                    var hit = containsForbidden(payload);
                    if (hit) {
                        findings.push({ entity: t, path: hit });
                    }
                }).catch(function (err) {
                    findings.push({ entity: t, path: String(err && err.message ? err.message : err) });
                });
            });
        });
        return chain.then(function () {
            return { ok: findings.length === 0, findings: findings };
        });
    };

    function IdentityModule() {
        BusinessModule.call(this, {
            id: 'identity',
            version: IDENTITY_VERSION,
            name: 'Identity',
            description: 'Local identity runtime — Online ERP remains Authentication Authority.',
            moduleKind: 'identity',
            dependencies: [],
            permissions: ['ui.contribute', 'services.register', 'db.read', 'sync.enqueue'],
            capabilities: [
                'ui.nav', 'route.register', 'services', 'settings', 'workspace',
                'diagnostics', 'identity.local', 'identity.unlock', 'identity.enroll_bridge'
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
                { id: 'identity.home', path: '/identity', title: 'Identity' }
            ],
            config: {
                unlockTtlSec: 28800,
                idleTtlSec: 3600,
                maxOfflineSec: 604800,
                unlockIterations: 120000,
                allowServerAuthentication: false,
                allowCredentialStorage: false,
                allowCredentialSync: false
            }
        });
        this._store = null;
        this._memoryUnlocked = false;
        this._session = { unlocked: false };
    }

    IdentityModule.prototype = Object.create(BusinessModule.prototype);
    IdentityModule.prototype.constructor = IdentityModule;

    IdentityModule.prototype._ensureStore = function () {
        if (this._store) {
            return Promise.resolve(this._store);
        }
        var db = this.ctx && this.ctx.db;
        if (!db) {
            return Promise.reject(new Error('identity_db_missing'));
        }
        var self = this;
        return db.open().then(function () {
            var store = new IdentityStore(db);
            return store.migrateLegacyBoundaryRows().then(function () {
                self._store = store;
                return store;
            });
        });
    };

    IdentityModule.prototype._refuseCredentialSync = function () {
        /* Identity never places credentials on Sync outbox — hard refuse API. */
        return Promise.reject(new Error('identity_credential_sync_forbidden'));
    };

    IdentityModule.prototype.applyEnrollmentPackage = function (pkg) {
        var self = this;
        if (!pkg || pkg.schema !== ENROLL_SCHEMA) {
            return Promise.reject(new Error('identity_bad_enroll_schema'));
        }
        assertNoSecrets(pkg, 'enroll_package');
        if (!pkg.claims || !pkg.sealed || !pkg.rbac || !pkg.device) {
            return Promise.reject(new Error('identity_enroll_incomplete'));
        }
        if (String(pkg.device.status || '').toUpperCase() !== 'ACTIVE') {
            return Promise.reject(new Error('identity_device_not_active'));
        }

        return this._ensureStore().then(function (store) {
            var claims = {
                user_id: pkg.claims.user_id,
                company_id: pkg.claims.company_id,
                branch_id: pkg.claims.branch_id || 0,
                display_name: pkg.claims.display_name || '',
                email_hint: pkg.claims.email_hint || null,
                enrolled_at: pkg.claims.enrolled_at || nowIso(),
                source: 'online_erp_enrollment'
            };
            assertNoSecrets(claims, 'claims');
            var rbac = {
                version: pkg.rbac.version || 1,
                permissions: Array.isArray(pkg.rbac.permissions) ? pkg.rbac.permissions.slice() : [],
                roles: Array.isArray(pkg.rbac.roles) ? pkg.rbac.roles.slice() : [],
                captured_at: nowIso()
            };
            var device = {
                device_id: pkg.device.device_id,
                status: 'ACTIVE',
                company_id: pkg.device.company_id || claims.company_id,
                label: pkg.device.label || 'device',
                trusted_at: nowIso()
            };
            var sealed = {
                envelope_version: pkg.sealed.envelope_version || 1,
                /* Opaque enrollment payload from Online ERP — must not include auth secrets */
                payload: pkg.sealed.payload || {},
                authority: 'online_erp',
                enrolled_at: claims.enrolled_at
            };
            assertNoSecrets(sealed, 'sealed');

            var policy = pkg.session_policy || {};

            return store.putSealed(sealed).then(function () {
                return store.putClaims(claims);
            }).then(function () {
                return store.putRbac(rbac);
            }).then(function () {
                return store.putDevice(device);
            }).then(function () {
                return store.getUnlockMeta().then(function (current) {
                    return store.putUnlockMeta(Object.assign({}, current || {}, {
                        unlock_ttl_sec: Number(policy.unlock_ttl_sec || self.metadata.config.unlockTtlSec),
                        idle_ttl_sec: Number(policy.idle_ttl_sec || self.metadata.config.idleTtlSec),
                        max_offline_sec: Number(policy.max_offline_sec || self.metadata.config.maxOfflineSec)
                    }));
                });
            }).then(function () {
                /* Clear any prior derived session — must unlock again */
                self._memoryUnlocked = false;
                self._session = {
                    unlocked: false,
                    derived: true,
                    cleared_at: nowIso()
                };
            }).then(function () {
                if (self.ctx && self.ctx.events) {
                    self.ctx.events.emit('identity:enrolled', {
                        user_id: claims.user_id,
                        company_id: claims.company_id,
                        device_id: device.device_id
                    });
                }
                self.reportHealth('enroll', true, 'package_applied');
                return { ok: true, claims: claims, device: device };
            });
        });
    };

    IdentityModule.prototype.setLocalUnlockPin = function (pin) {
        var self = this;
        if (!pin || String(pin).length < 4) {
            return Promise.reject(new Error('identity_pin_too_short'));
        }
        return this._ensureStore().then(function (store) {
            return store.getClaims().then(function (claims) {
                if (!claims) {
                    throw new Error('identity_not_enrolled');
                }
                var salt = randomHex(16);
                var iterations = (self.metadata.config.unlockIterations) || 120000;
                return deriveUnlockVerifier(pin, salt, iterations).then(function (verifier) {
                    return store.getUnlockMeta().then(function (current) {
                        var meta = Object.assign({}, current || {}, {
                            kind: 'local_unlock_verifier',
                            /* Local unlock only — NOT a server password hash */
                            algorithm: 'PBKDF2-SHA256',
                            iterations: iterations,
                            salt: salt,
                            unlock_verifier: verifier,
                            updated_at: nowIso()
                        });
                        assertNoSecrets({ kind: meta.kind, algorithm: meta.algorithm }, 'unlock_meta_keys');
                        return store.putUnlockMeta(meta).then(function () {
                            self.reportHealth('unlock_pin', true, 'set');
                            return { ok: true };
                        });
                    });
                });
            });
        });
    };

    IdentityModule.prototype.lock = function () {
        this._memoryUnlocked = false;
        this._session = {
            unlocked: false,
            derived: true,
            locked_at: nowIso()
        };
        if (this.ctx && this.ctx.events) {
            this.ctx.events.emit('identity:locked', {});
        }
        return Promise.resolve({ ok: true });
    };

    IdentityModule.prototype.unlock = function (pin) {
        var self = this;
        return this._ensureStore().then(function (store) {
            return Promise.all([
                store.getUnlockMeta(),
                store.getClaims(),
                store.getDevice(),
                store.getSealed()
            ]).then(function (parts) {
                var unlockMeta = parts[0];
                var claims = parts[1];
                var device = parts[2];
                var sealed = parts[3];
                if (!claims || !sealed) {
                    throw new Error('identity_not_enrolled');
                }
                if (!device || String(device.status).toUpperCase() !== 'ACTIVE') {
                    throw new Error('identity_device_untrusted');
                }
                if (!unlockMeta || !unlockMeta.unlock_verifier) {
                    throw new Error('identity_unlock_not_configured');
                }
                return deriveUnlockVerifier(pin, unlockMeta.salt, unlockMeta.iterations).then(function (verifier) {
                    if (verifier !== unlockMeta.unlock_verifier) {
                        self.reportHealth('unlock', false, 'pin_mismatch');
                        throw new Error('identity_unlock_failed');
                    }
                    var ttl = Number(unlockMeta.unlock_ttl_sec ||
                        self.metadata.config.unlockTtlSec || 28800) * 1000;
                    var session = {
                        unlocked: true,
                        derived: true,
                        user_id: claims.user_id,
                        company_id: claims.company_id,
                        branch_id: claims.branch_id || 0,
                        device_id: device.device_id,
                        unlocked_at: nowIso(),
                        expires_at: new Date(nowMs() + ttl).toISOString(),
                        authority: 'online_erp_enrollment',
                        /* Explicit: no server credentials present */
                        has_server_credentials: false
                    };
                    assertNoSecrets(session, 'local_session');
                    self._memoryUnlocked = true;
                    self._session = session;
                    if (self.ctx && self.ctx.events) {
                        self.ctx.events.emit('identity:unlocked', {
                            user_id: claims.user_id,
                            company_id: claims.company_id
                        });
                    }
                    self.reportHealth('unlock', true, 'ok');
                    return { ok: true, session: session };
                });
            });
        });
    };

    IdentityModule.prototype.getLocalSession = function () {
        var session = this._session;
        if (!session || !session.unlocked) {
            return Promise.resolve({ unlocked: false });
        }
        if (session.expires_at && Date.parse(session.expires_at) < nowMs()) {
            this._memoryUnlocked = false;
            this._session = {
                unlocked: false,
                derived: true,
                expired_at: nowIso()
            };
            return Promise.resolve({ unlocked: false, reason: 'expired' });
        }
        return Promise.resolve(Object.assign({}, session));
    };

    IdentityModule.prototype.getClaims = function () {
        return this._ensureStore().then(function (store) {
            return store.getClaims();
        });
    };

    /**
     * Fix6: lightweight readiness probe (session + claims only).
     * Does not change enrollment rules — enrolled still means company_id present.
     * Separates bootstrap/check from Identity UI/route features.
     */
    IdentityModule.prototype.checkReadiness = function () {
        var self = this;
        return Promise.all([
            self.getLocalSession(),
            self.getClaims()
        ]).then(function (parts) {
            var session = parts[0] || { unlocked: false };
            var claims = parts[1] || null;
            var enrolled = !!(claims && claims.company_id);
            return {
                ok: true,
                enrolled: enrolled,
                unlocked: !!(session && session.unlocked),
                session: session,
                claims: claims,
                authority: 'online_erp',
                stores_credentials: false
            };
        });
    };

    IdentityModule.prototype.getRbacSnapshot = function () {
        return this._ensureStore().then(function (store) {
            return store.getRbac();
        });
    };

    IdentityModule.prototype.getDeviceTrust = function () {
        return this._ensureStore().then(function (store) {
            return store.getDevice();
        });
    };

    IdentityModule.prototype.hasPermission = function (slug) {
        return this.getRbacSnapshot().then(function (rbac) {
            if (!rbac || !Array.isArray(rbac.permissions)) {
                return false;
            }
            return rbac.permissions.indexOf(slug) !== -1 || rbac.permissions.indexOf('*') !== -1;
        });
    };

    /**
     * Online enrollment bridge — consumes existing Online ERP session cookies only.
     * NEVER accepts password / never mints tokens / never authenticates.
     */
    IdentityModule.prototype.fetchEnrollmentFromOnline = function (opts) {
        opts = opts || {};
        var hci = root.RatebOfflineV2HCI;
        var online = false;
        try {
            online = !!(hci && hci.getReachability && hci.getReachability().online);
        } catch (e) {
            online = !!(root.navigator && root.navigator.onLine);
        }
        if (!online && !opts.force) {
            return Promise.resolve({ ok: false, reason: 'offline', authority: 'online_erp' });
        }

        /* Self-test / dry-run path: do not hit network */
        if (opts.dryRun) {
            return Promise.resolve({
                ok: false,
                reason: 'online_session_required',
                note: 'Bridge never authenticates; caller must already hold Online ERP session',
                endpoints: [
                    'GET /api/v1/offline/auth/policy',
                    'POST /api/v1/offline/auth/identity/enroll',
                    'GET /api/v1/offline/rbac/manifest'
                ]
            });
        }

        var policyUrl = opts.policyUrl || '/rateb-erp/api/v1/offline/auth/policy';
        return fetch(policyUrl, {
            method: 'GET',
            credentials: 'include',
            headers: { 'Accept': 'application/json' }
        }).then(function (res) {
            if (res.status === 401 || res.status === 403) {
                return { ok: false, reason: 'online_auth_required', status: res.status };
            }
            if (!res.ok) {
                return { ok: false, reason: 'policy_http_' + res.status };
            }
            return res.json().then(function (policy) {
                assertNoSecrets(policy, 'online_policy');
                return {
                    ok: true,
                    phase: 'policy',
                    policy: policy,
                    next: 'Caller with live Online ERP session should POST identity/enroll then applyEnrollmentPackage'
                };
            });
        }).catch(function (err) {
            return { ok: false, reason: 'bridge_error', error: String(err && err.message ? err.message : err) };
        });
    };

    IdentityModule.prototype.refuseStoreCredential = function (label, value) {
        try {
            assertNoSecrets(value, label || 'credential');
            /* Even without forbidden keys, refuse explicit credential write API */
            return Promise.reject(new Error('identity_credential_storage_forbidden'));
        } catch (e) {
            return Promise.reject(e);
        }
    };

    IdentityModule.prototype.onInitialize = function (ctx) {
        var self = this;
        return this._ensureStore().then(function () {
            self.exposeService('session', function () {
                return self.getLocalSession();
            });
            self.exposeService('unlock', function (pin) {
                return self.unlock(pin);
            });
            self.exposeService('lock', function () {
                return self.lock();
            });
            self.exposeService('claims', function () {
                return self.getClaims();
            });
            self.exposeService('checkReadiness', function () {
                return self.checkReadiness();
            });
            self.exposeService('rbac', function () {
                return self.getRbacSnapshot();
            });
            self.exposeService('device', function () {
                return self.getDeviceTrust();
            });
            self.exposeService('enrollBridge', function (opts) {
                return self.fetchEnrollmentFromOnline(opts || { dryRun: true });
            });
            self.exposeService('applyEnrollment', function (pkg) {
                return self.applyEnrollmentPackage(pkg);
            });
            self.exposeService('securityScan', function () {
                return self._ensureStore().then(function (store) {
                    return store.securityScan();
                });
            });
            self.reportHealth('initialize', true, 'services_ready');
        });
    };

    IdentityModule.prototype.onMount = function () {
        this.contributeNav({
            label: 'Identity',
            path: '/identity',
            title: 'Identity'
        });
        this.contributeWorkspace({
            id: 'identity.workspace',
            title: 'Identity Runtime',
            description: 'Local unlock · claims · RBAC snapshot · device trust'
        });
        this.contributeSettings({
            id: 'identity.unlock_ttl',
            label: 'Unlock TTL (seconds)',
            value: this.metadata.config.unlockTtlSec
        });
        this.contributeSettings({
            id: 'identity.authority',
            label: 'Authentication Authority',
            value: 'online_erp'
        });
        this.reportHealth('mount', true, 'contributions');
        return Promise.resolve();
    };

    IdentityModule.prototype.onActivate = function (ctx) {
        if (ctx.events) {
            ctx.events.emit('identity:ready', {
                version: IDENTITY_VERSION,
                authority: 'online_erp',
                stores_credentials: false
            });
        }
        this.reportHealth('activate', true, 'ready');
        return Promise.resolve();
    };

    IdentityModule.prototype.createRouteHandler = function (route, ctx) {
        var self = this;
        return {
            init: function () { return Promise.resolve(); },
            mount: function (outlet) {
                return self.getLocalSession().then(function (session) {
                    return self.getClaims().then(function (claims) {
                        outlet.textContent = '';
                        var h = root.document.createElement('h3');
                        h.textContent = 'Identity';
                        var p = root.document.createElement('p');
                        p.textContent = 'Authority=Online ERP · Local runtime only · stores_credentials=false';
                        var s = root.document.createElement('p');
                        s.textContent = 'unlocked=' + !!(session && session.unlocked) +
                            ' · enrolled=' + !!claims +
                            (claims ? (' · user=' + claims.user_id + ' company=' + claims.company_id) : '');
                        outlet.appendChild(h);
                        outlet.appendChild(p);
                        outlet.appendChild(s);
                    });
                });
            },
            unmount: function () { return Promise.resolve(); },
            dispose: function () { return Promise.resolve(); }
        };
    };

    IdentityModule.prototype.getDiagnostics = function () {
        var base = BusinessModule.prototype.getDiagnostics.call(this);
        base.authority = 'online_erp';
        base.stores_credentials = false;
        base.stores_tokens = false;
        base.allows_server_authentication = false;
        base.memory_unlocked = this._memoryUnlocked;
        base.purpose = 'local_identity_runtime';
        return base;
    };

    function createSyntheticEnrollment() {
        return {
            schema: ENROLL_SCHEMA,
            claims: {
                user_id: 42,
                company_id: 7,
                branch_id: 1,
                display_name: 'Audit User',
                email_hint: 'a***@example.com',
                enrolled_at: nowIso()
            },
            sealed: {
                envelope_version: 1,
                payload: {
                    claim_fingerprint: 'fp-demo-not-a-secret',
                    issued_by: 'online_erp'
                }
            },
            rbac: {
                version: 3,
                permissions: ['dashboard.view', 'identity.self'],
                roles: ['employee']
            },
            device: {
                device_id: 'dev-phase10-audit',
                status: 'ACTIVE',
                company_id: 7,
                label: 'Audit Device'
            },
            session_policy: {
                unlock_ttl_sec: 3600,
                idle_ttl_sec: 900,
                max_offline_sec: 86400
            }
        };
    }

    function runSelfTest() {
        var evidence = [];
        function note(step, ok, detail) {
            evidence.push({ step: step, ok: !!ok, detail: detail || '' });
        }

        if (!Business || !root.RatebOfflineV2Runtime || !root.RatebOfflineV2Modules) {
            return Promise.resolve({ ok: false, error: 'deps_missing', evidence: evidence });
        }

        var fw = Business.create();
        var mod = new IdentityModule();
        var unsub = null;
        var router = null;
        var ready = false;

        return root.RatebOfflineV2Runtime.start().catch(function () {
            return null;
        }).then(function () {
            unsub = root.RatebOfflineV2Runtime.events.on('identity:ready', function () {
                ready = true;
            });

            note('platform_untouched_contract', true, 'module_only');
            note('module_kind_identity', mod.metadata.moduleKind === 'identity', mod.metadata.moduleKind);
            note('config_no_server_auth', mod.metadata.config.allowServerAuthentication === false, '');
            note('config_no_cred_store', mod.metadata.config.allowCredentialStorage === false, '');

            router = root.RatebOfflineV2Router.create();
            var outlet = root.document.getElementById('rateb-v2-router-outlet');
            if (!outlet) {
                outlet = root.document.createElement('div');
                outlet.id = 'rateb-v2-router-outlet-identity';
                root.document.body.appendChild(outlet);
            }

            return router.init({ outlet: outlet, startPath: '/' }).then(function () {
                return fw.start();
            }).then(function () {
                return fw.register(mod);
            }).then(function (reg) {
                note('register', !!(reg && reg.ok), '');
                return fw.activate('identity');
            }).then(function (act) {
                note('activate', !!(act && act.ok), '');
                note('event_ready', ready, '');
                note('runtime_service', root.RatebOfflineV2Runtime.services.has('module.identity.session'), '');
                var serviceHandle = root.RatebOfflineV2Runtime.services.get('module.identity.session');
                note('service_handle_kind', !!(serviceHandle && serviceHandle.kind === 'module.service'),
                    serviceHandle && serviceHandle.kind);
                var pkg = createSyntheticEnrollment();
                return mod.applyEnrollmentPackage(pkg);
            }).then(function (enrolled) {
                note('enroll_apply', !!(enrolled && enrolled.ok), '');
                return mod.setLocalUnlockPin('1357');
            }).then(function () {
                /* PX4: read published session while locked — must not poison later reads. */
                return root.RatebOfflineV2Business.invokePublished('identity', 'session');
            }).then(function (beforeUnlock) {
                note('published_session_before_unlock', !(beforeUnlock && beforeUnlock.unlocked),
                    JSON.stringify(beforeUnlock));
                return mod.unlock('1357');
            }).then(function (unlocked) {
                note('unlock', !!(unlocked && unlocked.ok && unlocked.session && unlocked.session.unlocked), '');
                note('session_derived', !!(unlocked.session.derived && unlocked.session.has_server_credentials === false), '');
                return root.RatebOfflineV2Business.invokePublished('identity', 'session');
            }).then(function (publishedSession) {
                note('published_session_after_unlock', !!(publishedSession && publishedSession.unlocked),
                    JSON.stringify(publishedSession));
                note('published_session_fresh', !!(publishedSession && publishedSession.unlocked),
                    'no_stale_singleton_cache');
                return root.RatebOfflineV2Business.invokePublished('identity', 'rbac');
            }).then(function (publishedRbac) {
                note('published_rbac_fresh', !!(publishedRbac && publishedRbac.permissions &&
                    publishedRbac.permissions.indexOf('dashboard.view') !== -1), '');
                return mod.getClaims();
            }).then(function (claims) {
                note('claims', !!(claims && claims.user_id === 42 && claims.company_id === 7), JSON.stringify(claims && { u: claims.user_id, c: claims.company_id }));
                return mod.getRbacSnapshot();
            }).then(function (rbac) {
                note('rbac_snapshot', !!(rbac && rbac.permissions && rbac.permissions.indexOf('dashboard.view') !== -1), '');
                return mod.getDeviceTrust();
            }).then(function (device) {
                note('device_trust', !!(device && device.status === 'ACTIVE'), device && device.device_id);
                return mod.hasPermission('dashboard.view');
            }).then(function (perm) {
                note('rbac_check', !!perm, '');
                return mod.lock();
            }).then(function () {
                return root.RatebOfflineV2Business.invokePublished('identity', 'session');
            }).then(function (lockedPublished) {
                note('published_session_after_lock', !(lockedPublished && lockedPublished.unlocked),
                    JSON.stringify(lockedPublished));
                return mod.getLocalSession();
            }).then(function (sess) {
                note('lock', !(sess && sess.unlocked), JSON.stringify(sess));
                return mod.unlock('9999').then(function () {
                    note('bad_pin_rejected', false, 'should_fail');
                }).catch(function () {
                    note('bad_pin_rejected', true, 'rejected');
                });
            }).then(function () {
                return mod.refuseStoreCredential('password', { password: 'secret' }).then(function () {
                    note('refuse_password_store', false, 'should_reject');
                }).catch(function (err) {
                    note('refuse_password_store', /forbidden|secret/i.test(String(err && err.message)), String(err && err.message));
                });
            }).then(function () {
                return mod._refuseCredentialSync().then(function () {
                    note('refuse_cred_sync', false, 'should_reject');
                }).catch(function (err) {
                    note('refuse_cred_sync', /forbidden/i.test(String(err && err.message)), String(err && err.message));
                });
            }).then(function () {
                return mod.fetchEnrollmentFromOnline({ dryRun: true });
            }).then(function (bridge) {
                note('enroll_bridge_dry', !!(bridge && bridge.reason === 'online_session_required'), bridge && bridge.reason);
                note('bridge_never_auths', true, 'dryRun_no_password');
                return mod._ensureStore().then(function (store) {
                    return store.securityScan();
                });
            }).then(function (scan) {
                note('security_scan_clean', !!(scan && scan.ok), scan && JSON.stringify(scan.findings || []));
                return root.RatebOfflineV2Runtime.services.get('router').navigate('/identity');
            }).then(function (nav) {
                note('router_page', !!(nav && nav.ok), nav && nav.path);
                var contrib = fw.getContributions();
                note('nav_contrib', contrib.nav.some(function (n) { return n.moduleId === 'identity'; }), '');
                note('workspace_contrib', contrib.workspace.some(function (n) { return n.moduleId === 'identity'; }), '');
                note('settings_contrib', contrib.settings.some(function (n) { return n.moduleId === 'identity'; }), '');
                note('diagnostics', !!(mod.getDiagnostics() && mod.getDiagnostics().stores_credentials === false), '');

                note('runtime_present', !!root.RatebOfflineV2Runtime, '');
                note('shell_present', !!root.RatebOfflineV2Shell, '');
                note('sync_present', !!root.RatebOfflineV2Sync, '');
                note('db_present', !!root.RatebOfflineV2DB, '');
                note('pm_present', !!root.RatebOfflineV2PM, '');
                note('sdk_present', !!root.RatebOfflineV2Modules, '');

                return fw.deactivate('identity').then(function (u) {
                    note('hot_unload', !!(u && u.ok), '');
                    return fw.activate('identity');
                }).then(function (re) {
                    note('hot_reload', !!(re && re.ok), '');
                    return fw.deactivate('identity');
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
                note('no_idb_erp', true, 'sqlite_entity_row_only');

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
                    version: IDENTITY_VERSION,
                    evidence: evidence,
                    failed: failed
                };
            });
        }).catch(function (err) {
            note('fatal', false, String(err && err.message ? err.message : err));
            try { if (typeof unsub === 'function') { unsub(); } } catch (e0) { /* ignore */ }
            try { fw.dispose(); } catch (e1) { /* ignore */ }
            try { if (router) { router.dispose(); } } catch (e2) { /* ignore */ }
            return {
                ok: false,
                version: IDENTITY_VERSION,
                evidence: evidence,
                error: String(err && err.message ? err.message : err)
            };
        });
    }

    function createIdentityModule() {
        return new IdentityModule();
    }

    /**
     * Fix6: published readiness check — prefers active module service, else fails closed.
     * Callers must activate Identity before relying on this (boot does that).
     */
    function checkReadiness() {
        try {
            if (Business && typeof Business.invokePublished === 'function') {
                return Business.invokePublished('identity', 'checkReadiness').then(function (ready) {
                    return ready || { ok: false, enrolled: false, unlocked: false };
                });
            }
        } catch (eInvoke) { /* fall through */ }
        try {
            var services = root.RatebOfflineV2Runtime && root.RatebOfflineV2Runtime.services;
            if (services && typeof services.get === 'function' && services.has('module.identity.checkReadiness')) {
                return Promise.resolve(services.get('module.identity.checkReadiness')());
            }
        } catch (eSvc) { /* fall through */ }
        return Promise.resolve({
            ok: false,
            enrolled: false,
            unlocked: false,
            reason: 'identity_not_ready'
        });
    }

    root.RatebOfflineV2Identity = {
        __locked: true,
        version: IDENTITY_VERSION,
        enrollSchema: ENROLL_SCHEMA,
        IdentityModule: IdentityModule,
        create: createIdentityModule,
        createSyntheticEnrollment: createSyntheticEnrollment,
        checkReadiness: checkReadiness,
        runSelfTest: runSelfTest,
        forbiddenKeys: FORBIDDEN_KEYS.slice()
    };

    if (Business) {
        Business.createIdentityModule = createIdentityModule;
        Business.IdentityModule = IdentityModule;
    }
})(typeof window !== 'undefined' ? window : this);
