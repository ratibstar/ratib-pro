/**
 * RATEB Offline — ERP auth lock adapter (Phase 11).
 * Local shell unlock only. Uses rateb_erp_offline / auth_vault (DB_VERSION 2).
 * Never stores passwords / sessions / CSRF / JWT.
 */
(function (root) {
    'use strict';

    var PBKDF2_ITERATIONS = 120000;
    var UNLOCK_TTL_MS = 8 * 60 * 60 * 1000;
    var DEVICE_LS_KEY = 'rateb_erp_device_uuid';
    var UNLOCK_UNTIL_PREFIX = 'rateb_erp_unlock_until:';
    var REAUTH_KEY = 'rateb_erp_session_reauth';
    var DEVICE_META_PREFIX = 'auth_device:';

    function cfg() {
        return root.__RATEB_ERP_SHELL_OFFLINE__ || root.__RATEB_ERP_AUTH_OFFLINE__ || {};
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
                return { pin_hash: bufToB64(bits), pin_salt: bufToB64(salt.buffer) };
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

    function isUnlocked(scope) {
        return unlockUntil(scope) > Date.now();
    }

    function markUnlocked(scope) {
        setUnlockUntil(scope, Date.now() + UNLOCK_TTL_MS);
    }

    function clearUnlock(scope) {
        setUnlockUntil(scope || tenantScope(), 0);
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
        return getVault(scope).then(function (existing) {
            return hashPin(pin, existing && existing.pin_salt ? existing.pin_salt : null).then(function (hashed) {
                var record = {
                    id: id,
                    company_id: scope.company_id,
                    branch_id: scope.branch_id || 0,
                    user_id: scope.user_id,
                    pin_hash: hashed.pin_hash,
                    pin_salt: hashed.pin_salt,
                    webauthn_credential_id: (options.webauthn_credential_id
                        || (existing && existing.webauthn_credential_id)
                        || ''),
                    unlock_ttl_ms: UNLOCK_TTL_MS,
                    created_at: (existing && existing.created_at) ? existing.created_at : now,
                    updated_at: now
                };
                return putVault(record).then(function () {
                    return { ok: true, id: id };
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
                    || (intOr(record.user_id) !== scope.user_id)
                    || (intOr(record.branch_id) !== (scope.branch_id || 0))) {
                    return { ok: false, error: 'tenant_mismatch' };
                }
                return hashPin(pin, record.pin_salt).then(function (hashed) {
                    if (hashed.pin_hash !== record.pin_hash) {
                        return { ok: false, error: 'pin_denied' };
                    }
                    markUnlocked(scope);
                    return { ok: true };
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
                    markUnlocked(scope);
                    return { ok: true };
                }).catch(function () {
                    return { ok: false, error: 'webauthn_denied' };
                });
            });
        });
    }

    var overlayEl = null;

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
        msg.textContent = 'Enter your offline PIN to unlock the cached shell.';
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
                    return;
                }
                msg.textContent = (res && res.error) ? String(res.error) : 'Unlock denied';
            });
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
        // Online with live CSRF → treat as enrolled session; clear reauth and skip lock.
        var online = root.navigator && root.navigator.onLine !== false;
        var csrf = '';
        try {
            var meta = root.document && root.document.querySelector('meta[name="rateb-csrf"]');
            csrf = meta ? (meta.getAttribute('content') || '') : '';
        } catch (e) { /* ignore */ }
        if (online && csrf) {
            clearSessionNeedsReauth();
            markUnlocked(scope);
            hideOverlay();
            return Promise.resolve({ ok: true, online_session: true });
        }
        showOverlay();
        return Promise.resolve({ ok: false, locked: true });
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
        clearUnlock(tenantScope());
        var policy = (cfg().logout_vault_policy || 'keep_vault');
        if (policy === 'clear_vault') {
            deleteVault(tenantScope()).catch(function () { /* ignore */ });
        }
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
