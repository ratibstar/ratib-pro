/**
 * RATEB Offline — ERP auth lock adapter (Phase 11 + Phase P1 Warm Identity).
 * Local shell unlock only. Uses rateb_erp_offline / auth_vault (DB_VERSION 2).
 * Never stores passwords / PHP sessions / CSRF / JWT.
 * PIN decrypts sealed warm identity; server remains authoritative for replay.
 */
(function (root) {
    'use strict';

    var PBKDF2_ITERATIONS = 120000;
    var DEFAULT_UNLOCK_TTL_MS = 8 * 60 * 60 * 1000;
    var DEFAULT_IDLE_TIMEOUT_MS = 15 * 60 * 1000;
    var DEFAULT_MAX_OFFLINE_SESSION_MS = 72 * 60 * 60 * 1000;
    var DEFAULT_CLOCK_SKEW_SECONDS = 300;
    var DEVICE_LS_KEY = 'rateb_erp_device_uuid';
    var UNLOCK_UNTIL_PREFIX = 'rateb_erp_unlock_until:';
    var UNLOCK_STARTED_PREFIX = 'rateb_erp_unlock_started:';
    var IDLE_AT_PREFIX = 'rateb_erp_idle_at:';
    var REAUTH_KEY = 'rateb_erp_session_reauth';
    var DEVICE_META_PREFIX = 'auth_device:';
    var SCOPE_LS_KEY = 'rateb_erp_offline_scope';
    var IDENTITY_CLAIMS_SESSION = 'rateb_erp_warm_identity:';
    var SHELL_SNAPSHOT_KIND = 'erp_shell_chrome';
    var RBAC_SNAPSHOT_KIND = 'erp_rbac';

    function cfg() {
        return root.__RATEB_ERP_SHELL_OFFLINE__ || root.__RATEB_ERP_AUTH_OFFLINE__ || {};
    }

    /** Phase P2 — session policy from server policy() snapshot (fail-closed defaults). */
    function sessionPolicy() {
        var p = cfg().session_policy || {};
        return {
            unlock_ttl_ms: parseInt(p.unlock_ttl_ms, 10) > 0 ? parseInt(p.unlock_ttl_ms, 10) : DEFAULT_UNLOCK_TTL_MS,
            idle_timeout_ms: parseInt(p.idle_timeout_ms, 10) > 0 ? parseInt(p.idle_timeout_ms, 10) : DEFAULT_IDLE_TIMEOUT_MS,
            max_offline_session_ms: parseInt(p.max_offline_session_ms, 10) > 0
                ? parseInt(p.max_offline_session_ms, 10)
                : DEFAULT_MAX_OFFLINE_SESSION_MS,
            clock_skew_seconds: parseInt(p.clock_skew_seconds, 10) > 0
                ? parseInt(p.clock_skew_seconds, 10)
                : DEFAULT_CLOCK_SKEW_SECONDS,
            renew_before_seconds: parseInt(p.renew_before_seconds, 10) > 0
                ? parseInt(p.renew_before_seconds, 10)
                : (3 * 24 * 60 * 60)
        };
    }

    function unlockTtlMs() {
        return sessionPolicy().unlock_ttl_ms;
    }

    function flags() {
        if (root.RatebOffline && typeof root.RatebOffline.flags === 'function') {
            return root.RatebOffline.flags() || {};
        }
        return cfg().flags || {};
    }

    function isActive() {
        var f = flags();
        return !!(f['offline.enabled'] && f['offline.read_cache'] && f['offline.auth.unlock']);
    }

    function tenantScope() {
        var c = cfg();
        return {
            company_id: parseInt(c.company_id, 10) || 0,
            branch_id: parseInt(c.branch_id, 10) || 0,
            user_id: parseInt(c.user_id, 10) || 0,
            is_super_admin: !!c.is_super_admin
        };
    }

    function vaultId(scope) {
        scope = scope || tenantScope();
        if (!scope.company_id || !scope.user_id) {
            return null;
        }
        return String(scope.company_id) + ':' + String(scope.branch_id || 0) + ':' + String(scope.user_id);
    }

    function schema() {
        return root.RatebOfflineSchema || null;
    }

    function withVault(mode, fn) {
        var Schema = schema();
        if (!Schema || !Schema.STORES || !Schema.STORES.AUTH_VAULT) {
            return Promise.reject(new Error('auth_vault_unavailable'));
        }
        return Schema.withStore(Schema.STORES.AUTH_VAULT, mode, fn);
    }

    function withMeta(mode, fn) {
        var Schema = schema();
        if (!Schema) {
            return Promise.reject(new Error('schema_unavailable'));
        }
        return Schema.withStore(Schema.STORES.SYNC_META, mode, fn);
    }

    function withSnapshots(mode, fn) {
        var Schema = schema();
        if (!Schema || !Schema.STORES || !Schema.STORES.SNAPSHOTS) {
            return Promise.reject(new Error('snapshots_unavailable'));
        }
        return Schema.withStore(Schema.STORES.SNAPSHOTS, mode, fn);
    }

    function bufToB64(buf) {
        var bytes = new Uint8Array(buf);
        var bin = '';
        bytes.forEach(function (b) { bin += String.fromCharCode(b); });
        return btoa(bin);
    }

    function b64ToBuf(b64) {
        var bin = atob(String(b64 || '').replace(/-/g, '+').replace(/_/g, '/'));
        var bytes = new Uint8Array(bin.length);
        for (var i = 0; i < bin.length; i++) {
            bytes[i] = bin.charCodeAt(i);
        }
        return bytes.buffer;
    }

    function randomBytes(n) {
        var a = new Uint8Array(n);
        root.crypto.getRandomValues(a);
        return a;
    }

    function hashPin(pin, saltB64) {
        var enc = new TextEncoder();
        var salt = saltB64 ? new Uint8Array(b64ToBuf(saltB64)) : randomBytes(16);
        return root.crypto.subtle.importKey('raw', enc.encode(String(pin)), 'PBKDF2', false, ['deriveBits'])
            .then(function (key) {
                return root.crypto.subtle.deriveBits(
                    { name: 'PBKDF2', salt: salt, iterations: PBKDF2_ITERATIONS, hash: 'SHA-256' },
                    key,
                    256
                );
            })
            .then(function (bits) {
                return { pin_hash: bufToB64(bits), pin_salt: bufToB64(salt.buffer), bits: bits };
            });
    }

    function deriveAesKey(pin, saltB64) {
        return hashPin(pin, saltB64).then(function (hashed) {
            return root.crypto.subtle.importKey('raw', hashed.bits, { name: 'AES-GCM' }, false, ['encrypt', 'decrypt'])
                .then(function (key) {
                    return { key: key, pin_hash: hashed.pin_hash, pin_salt: hashed.pin_salt };
                });
        });
    }

    function sealIdentityPackage(pin, saltB64, identityPackage) {
        var iv = randomBytes(12);
        var plain = new TextEncoder().encode(JSON.stringify(identityPackage || {}));
        return deriveAesKey(pin, saltB64).then(function (derived) {
            return root.crypto.subtle.encrypt({ name: 'AES-GCM', iv: iv }, derived.key, plain).then(function (cipher) {
                return {
                    pin_hash: derived.pin_hash,
                    pin_salt: derived.pin_salt,
                    identity_iv: bufToB64(iv.buffer),
                    identity_cipher: bufToB64(cipher),
                    identity_alg: 'AES-GCM',
                    identity_expires_at: identityPackage && identityPackage.claims
                        ? (parseInt(identityPackage.claims.expires_at, 10) || 0)
                        : 0
                };
            });
        });
    }

    function unsealIdentityPackage(pin, record) {
        if (!record || !record.identity_cipher || !record.identity_iv || !record.pin_salt) {
            return Promise.resolve({ ok: false, error: 'identity_missing' });
        }
        return deriveAesKey(pin, record.pin_salt).then(function (derived) {
            if (derived.pin_hash !== record.pin_hash) {
                return { ok: false, error: 'pin_denied' };
            }
            return root.crypto.subtle.decrypt(
                { name: 'AES-GCM', iv: new Uint8Array(b64ToBuf(record.identity_iv)) },
                derived.key,
                b64ToBuf(record.identity_cipher)
            ).then(function (plainBuf) {
                var json = new TextDecoder().decode(plainBuf);
                var pkg = JSON.parse(json);
                return verifyIdentityLocal(pkg, {
                    company_id: intOr(record.company_id),
                    branch_id: intOr(record.branch_id),
                    user_id: intOr(record.user_id),
                    device_id: String(record.device_id || '')
                }).then(function (v) {
                    if (!v.ok) {
                        return v;
                    }
                    return { ok: true, identity: pkg, claims: v.claims };
                });
            }).catch(function () {
                return { ok: false, error: 'pin_denied' };
            });
        });
    }

    function canonicalClaims(claims) {
        var keys = Object.keys(claims || {}).sort();
        var ordered = {};
        keys.forEach(function (k) { ordered[k] = claims[k]; });
        return JSON.stringify(ordered);
    }

    function hmacSha256(keyBuf, message) {
        return root.crypto.subtle.importKey('raw', keyBuf, { name: 'HMAC', hash: 'SHA-256' }, false, ['sign'])
            .then(function (key) {
                return root.crypto.subtle.sign('HMAC', key, new TextEncoder().encode(message));
            })
            .then(function (sig) {
                return bufToB64(sig);
            });
    }

    function verifyIdentityLocal(pkg, expect) {
        expect = expect || {};
        if (!pkg || !pkg.claims || !pkg.signature || !pkg.identity_key) {
            return Promise.resolve({ ok: false, error: 'identity_incomplete' });
        }
        var claims = pkg.claims;
        if (String(claims.purpose || '') !== 'erp_offline_warm') {
            return Promise.resolve({ ok: false, error: 'identity_purpose' });
        }
        var expiresAt = parseInt(claims.expires_at, 10) || 0;
        if (expiresAt < 1 || expiresAt * 1000 <= Date.now()) {
            return Promise.resolve({ ok: false, error: 'identity_expired' });
        }
        var skew = sessionPolicy().clock_skew_seconds;
        var issuedAt = parseInt(claims.issued_at, 10) || 0;
        var nowSec = Math.floor(Date.now() / 1000);
        if (issuedAt > nowSec + skew) {
            return Promise.resolve({ ok: false, error: 'clock_rollback' });
        }
        var notBefore = parseInt(claims.not_before, 10) || issuedAt;
        if (notBefore > nowSec + skew) {
            return Promise.resolve({ ok: false, error: 'identity_not_before' });
        }
        var antiRollback = parseInt(claims.anti_rollback, 10) || issuedAt;
        if (expect.min_anti_rollback && antiRollback < parseInt(expect.min_anti_rollback, 10)) {
            return Promise.resolve({ ok: false, error: 'anti_rollback' });
        }
        if (expect.min_identity_version
            && (parseInt(claims.identity_version, 10) || 1) < parseInt(expect.min_identity_version, 10)) {
            return Promise.resolve({ ok: false, error: 'identity_version' });
        }
        if (expect.company_id && intOr(claims.company_id) !== expect.company_id) {
            return Promise.resolve({ ok: false, error: 'tenant_mismatch' });
        }
        if (expect.user_id && intOr(claims.user_id) !== expect.user_id) {
            return Promise.resolve({ ok: false, error: 'tenant_mismatch' });
        }
        if (Object.prototype.hasOwnProperty.call(expect, 'branch_id')
            && intOr(claims.branch_id) !== intOr(expect.branch_id)) {
            return Promise.resolve({ ok: false, error: 'branch_mismatch' });
        }
        if (expect.device_id && String(claims.device_id || '') !== String(expect.device_id)) {
            return Promise.resolve({ ok: false, error: 'device_mismatch' });
        }
        var canonical = (typeof pkg.canonical === 'string' && pkg.canonical !== '')
            ? pkg.canonical
            : canonicalClaims(claims);
        return hmacSha256(b64ToBuf(pkg.identity_key), canonical).then(function (sigB64) {
            if (sigB64 !== String(pkg.signature || '')) {
                return { ok: false, error: 'identity_signature' };
            }
            return { ok: true, claims: claims };
        }).catch(function () {
            return { ok: false, error: 'identity_signature' };
        });
    }

    function getDeviceId() {
        try {
            var id = localStorage.getItem(DEVICE_LS_KEY);
            if (id) {
                return id;
            }
            id = 'erp-' + bufToB64(randomBytes(16).buffer).replace(/[^a-zA-Z0-9]/g, '').slice(0, 32);
            localStorage.setItem(DEVICE_LS_KEY, id);
            return id;
        } catch (e) {
            return 'erp-ephemeral';
        }
    }

    function unlockStorageKey(scope) {
        return UNLOCK_UNTIL_PREFIX + vaultId(scope);
    }

    function identitySessionKey(scope) {
        return IDENTITY_CLAIMS_SESSION + vaultId(scope);
    }

    function unlockUntil(scope) {
        try {
            return parseInt(sessionStorage.getItem(unlockStorageKey(scope)) || '0', 10) || 0;
        } catch (e) {
            return 0;
        }
    }

    function setUnlockUntil(scope, ts) {
        try {
            sessionStorage.setItem(unlockStorageKey(scope), String(ts));
        } catch (e) { /* ignore */ }
    }

    function unlockStartedKey(scope) {
        return UNLOCK_STARTED_PREFIX + vaultId(scope);
    }

    function idleAtKey(scope) {
        return IDLE_AT_PREFIX + vaultId(scope);
    }

    function markUnlocked(scope, claims) {
        var now = Date.now();
        var policy = sessionPolicy();
        setUnlockUntil(scope, now + policy.unlock_ttl_ms);
        try {
            sessionStorage.setItem(unlockStartedKey(scope), String(now));
            sessionStorage.setItem(idleAtKey(scope), String(now));
            if (claims) {
                sessionStorage.setItem(identitySessionKey(scope), JSON.stringify({
                    company_id: claims.company_id,
                    branch_id: claims.branch_id,
                    user_id: claims.user_id,
                    device_id: claims.device_id,
                    expires_at: claims.expires_at,
                    issued_at: claims.issued_at || 0,
                    identity_version: claims.identity_version || 1,
                    anti_rollback: claims.anti_rollback || claims.issued_at || 0,
                    jti: claims.jti || ''
                }));
            }
        } catch (e) { /* ignore */ }
    }

    function touchIdle(scope) {
        scope = scope || tenantScope();
        try {
            sessionStorage.setItem(idleAtKey(scope), String(Date.now()));
        } catch (e) { /* ignore */ }
    }

    function assertSessionTtl(scope) {
        scope = scope || tenantScope();
        var policy = sessionPolicy();
        var until = unlockUntil(scope);
        if (until <= Date.now()) {
            return { ok: false, error: 'unlock_ttl_expired' };
        }
        try {
            var started = parseInt(sessionStorage.getItem(unlockStartedKey(scope)) || '0', 10) || 0;
            if (started > 0 && (Date.now() - started) > policy.max_offline_session_ms) {
                clearUnlock(scope);
                return { ok: false, error: 'max_offline_session' };
            }
            var idleAt = parseInt(sessionStorage.getItem(idleAtKey(scope)) || '0', 10) || 0;
            if (idleAt > 0 && (Date.now() - idleAt) > policy.idle_timeout_ms) {
                clearUnlock(scope);
                return { ok: false, error: 'idle_timeout' };
            }
        } catch (e) {
            return { ok: false, error: 'session_ttl_unavailable' };
        }
        return { ok: true };
    }

    function isUnlocked(scope) {
        if (unlockUntil(scope) <= Date.now()) {
            return false;
        }
        return assertSessionTtl(scope).ok === true;
    }

    function clearUnlock(scope) {
        scope = scope || tenantScope();
        setUnlockUntil(scope, 0);
        try {
            sessionStorage.removeItem(identitySessionKey(scope));
            sessionStorage.removeItem(unlockStartedKey(scope));
            sessionStorage.removeItem(idleAtKey(scope));
        } catch (e) { /* ignore */ }
    }

    function vaultIntegrityHash(record) {
        var parts = [
            String(record.company_id || 0),
            String(record.branch_id || 0),
            String(record.user_id || 0),
            String(record.device_id || ''),
            String(record.pin_hash || ''),
            String(record.identity_cipher || ''),
            String(record.identity_expires_at || 0),
            String(record.identity_version || 1)
        ];
        return root.crypto.subtle.digest('SHA-256', new TextEncoder().encode(parts.join('|'))).then(function (buf) {
            return bufToB64(buf);
        });
    }

    function sessionNeedsReauth() {
        try {
            return sessionStorage.getItem(REAUTH_KEY) === '1';
        } catch (e) {
            return false;
        }
    }

    function markSessionNeedsReauth() {
        try {
            sessionStorage.setItem(REAUTH_KEY, '1');
        } catch (e) { /* ignore */ }
    }

    function clearSessionNeedsReauth() {
        try {
            sessionStorage.removeItem(REAUTH_KEY);
        } catch (e) { /* ignore */ }
    }

    function getVault(scope) {
        var id = vaultId(scope);
        if (!id) {
            return Promise.resolve(null);
        }
        return withVault('readonly', function (store) {
            return new Promise(function (resolve, reject) {
                var req = store.get(id);
                req.onsuccess = function () { resolve(req.result || null); };
                req.onerror = function () { reject(req.error); };
            });
        });
    }

    function putVault(record) {
        return withVault('readwrite', function (store) {
            store.put(record);
            return true;
        });
    }

    function deleteVault(scope) {
        var id = vaultId(scope);
        if (!id) {
            return Promise.resolve(false);
        }
        return withVault('readwrite', function (store) {
            store.delete(id);
            return true;
        });
    }

    function cacheDeviceStatus(scope, device) {
        var key = DEVICE_META_PREFIX + (scope.company_id || 0) + ':' + (scope.user_id || 0);
        return withMeta('readwrite', function (store) {
            store.put({
                key: key,
                device_id: device && device.device_id ? String(device.device_id) : '',
                status: device && device.status ? String(device.status) : '',
                is_active: !!(device && device.is_active),
                updated_at: new Date().toISOString()
            });
            return true;
        });
    }

    function readDeviceStatus(scope) {
        var key = DEVICE_META_PREFIX + (scope.company_id || 0) + ':' + (scope.user_id || 0);
        return withMeta('readonly', function (store) {
            return new Promise(function (resolve, reject) {
                var req = store.get(key);
                req.onsuccess = function () { resolve(req.result || null); };
                req.onerror = function () { reject(req.error); };
            });
        }).catch(function () { return null; });
    }

    function deleteDeviceStatus(scope) {
        var key = DEVICE_META_PREFIX + (scope.company_id || 0) + ':' + (scope.user_id || 0);
        return withMeta('readwrite', function (store) {
            store.delete(key);
            return true;
        }).catch(function () { return false; });
    }

    function deleteSnapshot(kind, scope) {
        scope = scope || tenantScope();
        if (!scope.company_id || !scope.user_id) {
            return Promise.resolve(false);
        }
        var id = kind + ':' + scope.company_id + ':' + (scope.branch_id || 0) + ':' + scope.user_id;
        return withSnapshots('readwrite', function (store) {
            store.delete(id);
            return true;
        }).catch(function () { return false; });
    }

    function clearPersistedScope() {
        try {
            localStorage.removeItem(SCOPE_LS_KEY);
        } catch (e) { /* ignore */ }
    }

    /** Phase P1 logout: destroy warm identity, PIN vault, RBAC, shell chrome, device meta. */
    function destroyWarmSession(scope) {
        scope = scope || tenantScope();
        clearUnlock(scope);
        markSessionNeedsReauth();
        clearPersistedScope();
        var local = root.RatebOfflineLocalSession;
        if (local && typeof local.destroy === 'function') {
            local.destroy(scope);
        }
        var rbac = root.RatebOfflineRbacCache;
        if (rbac && typeof rbac.clearNavDom === 'function') {
            rbac.clearNavDom();
        }
        return Promise.all([
            deleteVault(scope),
            deleteDeviceStatus(scope),
            deleteSnapshot(RBAC_SNAPSHOT_KIND, scope),
            deleteSnapshot(SHELL_SNAPSHOT_KIND, scope),
            rbac && typeof rbac.deleteManifest === 'function' ? rbac.deleteManifest(scope) : Promise.resolve(false)
        ]).then(function () {
            return { ok: true, destroyed: true };
        }).catch(function () {
            return { ok: true, destroyed: true, partial: true };
        });
    }

    function assertUnlockAllowed(scope, deviceMeta) {
        if (!isActive()) {
            return { ok: false, error: 'auth_unlock_disabled' };
        }
        if (scope.is_super_admin) {
            return { ok: false, error: 'super_admin_denied' };
        }
        if (!scope.company_id || !scope.user_id) {
            return { ok: false, error: 'tenant_required' };
        }
        if (!deviceMeta || !deviceMeta.status) {
            return { ok: false, error: 'device_unknown' };
        }
        var st = String(deviceMeta.status).toLowerCase();
        if (st !== 'active' || !deviceMeta.is_active) {
            return { ok: false, error: st === 'pending' ? 'device_pending' : (st === 'revoked' ? 'device_revoked' : 'device_denied') };
        }
        return { ok: true };
    }

    function enrollPin(pin, options) {
        options = options || {};
        if (!isActive()) {
            return Promise.resolve({ ok: false, error: 'auth_unlock_disabled' });
        }
        var scope = tenantScope();
        if (scope.is_super_admin) {
            return Promise.resolve({ ok: false, error: 'super_admin_denied' });
        }
        var id = vaultId(scope);
        if (!id) {
            return Promise.resolve({ ok: false, error: 'online_session_required' });
        }
        if (!pin || String(pin).length < 4) {
            return Promise.resolve({ ok: false, error: 'pin_too_short' });
        }
        var now = new Date().toISOString();
        var deviceId = getDeviceId();
        return getVault(scope).then(function (existing) {
            var salt = existing && existing.pin_salt ? existing.pin_salt : null;
            var sealPromise = options.identity
                ? sealIdentityPackage(pin, salt, options.identity)
                : hashPin(pin, salt).then(function (hashed) {
                    return {
                        pin_hash: hashed.pin_hash,
                        pin_salt: hashed.pin_salt,
                        identity_iv: '',
                        identity_cipher: '',
                        identity_alg: '',
                        identity_expires_at: 0
                    };
                });
            return sealPromise.then(function (sealed) {
                var record = {
                    id: id,
                    company_id: scope.company_id,
                    branch_id: scope.branch_id || 0,
                    user_id: scope.user_id,
                    device_id: deviceId,
                    pin_hash: sealed.pin_hash,
                    pin_salt: sealed.pin_salt,
                    identity_iv: sealed.identity_iv || '',
                    identity_cipher: sealed.identity_cipher || '',
                    identity_alg: sealed.identity_alg || '',
                    identity_expires_at: sealed.identity_expires_at || 0,
                    webauthn_credential_id: (options.webauthn_credential_id
                        || (existing && existing.webauthn_credential_id)
                        || ''),
                    unlock_ttl_ms: unlockTtlMs(),
                    identity_version: options.identity && options.identity.claims
                        ? (parseInt(options.identity.claims.identity_version, 10) || 1)
                        : 1,
                    vault_integrity: '',
                    created_at: (existing && existing.created_at) ? existing.created_at : now,
                    updated_at: now
                };
                return vaultIntegrityHash(record).then(function (hash) {
                    record.vault_integrity = hash;
                    return putVault(record).then(function () {
                        return { ok: true, id: id, has_identity: !!(sealed.identity_cipher), vault_integrity: hash };
                    });
                });
            });
        });
    }

    function unlockWithPin(pin, expectScope) {
        var scope = expectScope || tenantScope();
        return readDeviceStatus(scope).then(function (deviceMeta) {
            var gate = assertUnlockAllowed(scope, deviceMeta);
            if (!gate.ok) {
                return gate;
            }
            return getVault(scope).then(function (record) {
                if (!record || !record.pin_hash || !record.pin_salt) {
                    return { ok: false, error: 'not_enrolled' };
                }
                if ((intOr(record.company_id) !== scope.company_id)
                    || (intOr(record.user_id) !== scope.user_id)) {
                    return { ok: false, error: 'tenant_mismatch' };
                }
                if (intOr(record.branch_id) !== (scope.branch_id || 0)) {
                    return { ok: false, error: 'branch_mismatch' };
                }
                if (record.identity_cipher) {
                    return unsealIdentityPackage(pin, record).then(function (opened) {
                        if (!opened.ok) {
                            return opened;
                        }
                        return vaultIntegrityHash(record).then(function (hash) {
                            if (record.vault_integrity && hash !== record.vault_integrity) {
                                return { ok: false, error: 'vault_tamper' };
                            }
                            clearSessionNeedsReauth();
                            markUnlocked(scope, opened.claims);
                            return {
                                ok: true,
                                identity: opened.claims,
                                warm: true,
                                cold: !!(opened.claims && opened.claims.cold_capable)
                            };
                        });
                    });
                }
                return hashPin(pin, record.pin_salt).then(function (hashed) {
                    if (hashed.pin_hash !== record.pin_hash) {
                        return { ok: false, error: 'pin_denied' };
                    }
                    if (record.identity_expires_at && (record.identity_expires_at * 1000) <= Date.now()) {
                        return { ok: false, error: 'identity_expired' };
                    }
                    clearSessionNeedsReauth();
                    markUnlocked(scope, null);
                    return { ok: true, warm: false };
                });
            });
        });
    }

    function intOr(v) {
        return parseInt(v, 10) || 0;
    }

    function unlockWithWebAuthn() {
        var scope = tenantScope();
        return readDeviceStatus(scope).then(function (deviceMeta) {
            var gate = assertUnlockAllowed(scope, deviceMeta);
            if (!gate.ok) {
                return gate;
            }
            return getVault(scope).then(function (record) {
                if (!record || !record.webauthn_credential_id) {
                    return { ok: false, error: 'webauthn_not_enrolled' };
                }
                if (!root.PublicKeyCredential || !navigator.credentials) {
                    return { ok: false, error: 'webauthn_unavailable' };
                }
                var idBuf = b64ToBuf(record.webauthn_credential_id);
                return navigator.credentials.get({
                    publicKey: {
                        challenge: randomBytes(32),
                        timeout: 60000,
                        userVerification: 'required',
                        allowCredentials: [{ type: 'public-key', id: idBuf }]
                    }
                }).then(function (cred) {
                    if (!cred || !cred.id) {
                        return { ok: false, error: 'webauthn_denied' };
                    }
                    markUnlocked(scope, null);
                    return { ok: true };
                }).catch(function () {
                    return { ok: false, error: 'webauthn_denied' };
                });
            });
        });
    }

    var overlayEl = null;
    var unlockWaiters = [];

    function notifyUnlocked(result) {
        var waiters = unlockWaiters.slice();
        unlockWaiters = [];
        waiters.forEach(function (fn) {
            try { fn(result); } catch (e) { /* ignore */ }
        });
        try {
            root.dispatchEvent(new CustomEvent('rateb:offline-unlocked', { detail: result || { ok: true } }));
        } catch (e2) { /* ignore */ }
    }

    function ensureOverlay() {
        if (overlayEl || !root.document || !root.document.body) {
            return overlayEl;
        }
        overlayEl = root.document.createElement('div');
        overlayEl.setAttribute('data-rateb-erp-auth-lock', '1');
        overlayEl.setAttribute('role', 'dialog');
        overlayEl.setAttribute('aria-modal', 'true');
        overlayEl.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(15,17,23,.92);'
            + 'display:flex;align-items:center;justify-content:center;padding:1.5rem;';
        var box = root.document.createElement('div');
        box.style.cssText = 'background:#1a1d24;color:#e8eaed;padding:1.5rem;border-radius:8px;max-width:22rem;width:100%;';
        var title = root.document.createElement('h2');
        title.textContent = 'ERP Offline Unlock';
        title.style.marginTop = '0';
        var msg = root.document.createElement('p');
        msg.setAttribute('data-lock-msg', '1');
        msg.textContent = 'Enter your offline PIN to unlock the warm identity.';
        var input = root.document.createElement('input');
        input.type = 'password';
        input.autocomplete = 'current-password';
        input.setAttribute('data-lock-pin', '1');
        input.style.cssText = 'width:100%;padding:.5rem;margin:.5rem 0;';
        var btn = root.document.createElement('button');
        btn.type = 'button';
        btn.textContent = 'Unlock';
        btn.style.cssText = 'width:100%;padding:.6rem;cursor:pointer;';
        btn.addEventListener('click', function () {
            unlockWithPin(input.value).then(function (res) {
                if (res && res.ok) {
                    hideOverlay();
                    notifyUnlocked(res);
                    return;
                }
                msg.textContent = (res && res.error) ? String(res.error) : 'Unlock denied';
            });
        });
        input.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') {
                btn.click();
            }
        });
        box.appendChild(title);
        box.appendChild(msg);
        box.appendChild(input);
        box.appendChild(btn);
        overlayEl.appendChild(box);
        root.document.body.appendChild(overlayEl);
        return overlayEl;
    }

    function showOverlay() {
        var el = ensureOverlay();
        if (el) {
            el.hidden = false;
            el.style.display = 'flex';
            try {
                var pin = el.querySelector('[data-lock-pin]');
                if (pin) {
                    pin.focus();
                }
            } catch (e) { /* ignore */ }
        }
    }

    function hideOverlay() {
        if (overlayEl) {
            overlayEl.hidden = true;
            overlayEl.style.display = 'none';
        }
    }

    function requireUnlockIfNeeded() {
        if (!isActive()) {
            return Promise.resolve({ skipped: true });
        }
        var scope = tenantScope();
        if (scope.is_super_admin) {
            return Promise.resolve({ ok: false, error: 'super_admin_denied' });
        }
        if (isUnlocked(scope)) {
            hideOverlay();
            return Promise.resolve({ ok: true, unlocked: true });
        }
        var online = root.navigator && root.navigator.onLine !== false;
        var csrf = '';
        try {
            var meta = root.document && root.document.querySelector('meta[name="rateb-csrf"]');
            csrf = meta ? (meta.getAttribute('content') || '') : '';
        } catch (e) { /* ignore */ }
        if (online && csrf) {
            return readDeviceStatus(scope).then(function (device) {
                var status = device && device.status ? String(device.status).toLowerCase() : '';
                if (status === 'active') {
                    clearSessionNeedsReauth();
                    markUnlocked(scope, null);
                    hideOverlay();
                    notifyUnlocked({ ok: true, online_session: true });
                    return { ok: true, online_session: true, device_active: true };
                }
                markSessionNeedsReauth();
                showOverlay();
                return { ok: false, locked: true, error: 'inactive_device' };
            }).catch(function () {
                markSessionNeedsReauth();
                showOverlay();
                return { ok: false, locked: true, error: 'inactive_device' };
            });
        }
        showOverlay();
        return new Promise(function (resolve) {
            unlockWaiters.push(resolve);
        });
    }

    function handleLogoutClick(ev) {
        var a = ev.target && ev.target.closest ? ev.target.closest('a[href]') : null;
        if (!a) {
            return;
        }
        var href = a.getAttribute('href') || '';
        if (!/\/logout/i.test(href)) {
            return;
        }
        destroyWarmSession(tenantScope());
    }

    function start() {
        if (!isActive()) {
            return;
        }
        if (root.document) {
            root.document.addEventListener('click', handleLogoutClick, true);
        }
        requireUnlockIfNeeded();
    }

    root.RatebOfflineAuthLock = {
        isActive: isActive,
        tenantScope: tenantScope,
        vaultId: vaultId,
        getDeviceId: getDeviceId,
        enrollPin: enrollPin,
        unlockWithPin: unlockWithPin,
        unlockWithWebAuthn: unlockWithWebAuthn,
        sealIdentityPackage: sealIdentityPackage,
        verifyIdentityLocal: verifyIdentityLocal,
        destroyWarmSession: destroyWarmSession,
        sessionPolicy: sessionPolicy,
        touchIdle: touchIdle,
        assertSessionTtl: assertSessionTtl,
        vaultIntegrityHash: vaultIntegrityHash,
        needsIdentityRenewal: function (claims) {
            claims = claims || {};
            var exp = parseInt(claims.expires_at, 10) || 0;
            if (exp < 1) {
                return false;
            }
            var before = sessionPolicy().renew_before_seconds;
            return (exp - Math.floor(Date.now() / 1000)) <= before;
        },
        isUnlocked: function () { return isUnlocked(tenantScope()); },
        clearUnlock: clearUnlock,
        deleteVault: deleteVault,
        cacheDeviceStatus: cacheDeviceStatus,
        readDeviceStatus: readDeviceStatus,
        sessionNeedsReauth: sessionNeedsReauth,
        markSessionNeedsReauth: markSessionNeedsReauth,
        clearSessionNeedsReauth: clearSessionNeedsReauth,
        requireUnlockIfNeeded: requireUnlockIfNeeded,
        showOverlay: showOverlay,
        hideOverlay: hideOverlay,
        start: start,
        PBKDF2_ITERATIONS: PBKDF2_ITERATIONS
    };
})(typeof window !== 'undefined' ? window : globalThis);
