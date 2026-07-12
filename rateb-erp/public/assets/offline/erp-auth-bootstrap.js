/**
 * RATEB Offline — ERP auth bootstrap (Phase 11 + P1 Warm Identity + P2 renew/TTL).
 * Loaded only when offline.enabled + read_cache + auth.unlock are ON.
 *
 * Online login → identity enroll → device ACTIVE → PIN seal → persist scope/device.
 * Company-bound super-admin (resolved company_id) may enroll; unbound platform SA may not.
 */
(function (root) {
    'use strict';

    var cfg = root.__RATEB_ERP_SHELL_OFFLINE__ || {};
    var SCOPE_LS_KEY = 'rateb_erp_offline_scope';

    function csrfToken() {
        try {
            var meta = root.document && root.document.querySelector('meta[name="rateb-csrf"]');
            return meta ? (meta.getAttribute('content') || '') : '';
        } catch (e) {
            return '';
        }
    }

    function apiUrl(path) {
        var base = String(cfg.apiBase || '').replace(/\/$/, '');
        return base + path;
    }

    function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-Token': csrfToken()
            },
            body: JSON.stringify(body || {})
        }).then(function (res) {
            return res.json().then(function (payload) {
                return { http: res.status, payload: payload };
            });
        });
    }

    function getJson(url) {
        return fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { Accept: 'application/json' }
        }).then(function (res) {
            return res.json().then(function (payload) {
                return { http: res.status, payload: payload };
            });
        });
    }

    function applySessionPolicy(payload) {
        if (payload && payload.session_policy) {
            root.__RATEB_ERP_SHELL_OFFLINE__ = root.__RATEB_ERP_SHELL_OFFLINE__ || cfg;
            root.__RATEB_ERP_SHELL_OFFLINE__.session_policy = payload.session_policy;
            cfg = root.__RATEB_ERP_SHELL_OFFLINE__;
        }
    }

    function persistEnrolledScope(device) {
        try {
            var flags = (cfg.flags) || {};
            var deviceId = '';
            if (device && device.device_id) {
                deviceId = String(device.device_id);
            } else {
                try {
                    deviceId = root.localStorage.getItem('rateb_erp_device_uuid') || '';
                } catch (e2) { deviceId = ''; }
            }
            root.localStorage.setItem(SCOPE_LS_KEY, JSON.stringify({
                company_id: parseInt(cfg.company_id, 10) || 0,
                tenant_id: parseInt(cfg.tenant_id || cfg.company_id, 10) || 0,
                branch_id: parseInt(cfg.branch_id, 10) || 0,
                user_id: parseInt(cfg.user_id, 10) || 0,
                is_super_admin: !!cfg.is_super_admin,
                device_id: deviceId,
                device_label: device && device.label ? String(device.label) : 'ERP shell',
                auth_unlock: !!flags['offline.auth.unlock'],
                flags: {
                    'offline.enabled': true,
                    'offline.read_cache': true,
                    'offline.auth.unlock': !!flags['offline.auth.unlock'],
                    'offline.rbac.cache': !!flags['offline.rbac.cache']
                },
                enrolled_at: new Date().toISOString(),
                saved_at: new Date().toISOString()
            }));
        } catch (e) { /* ignore */ }
    }

    function loadPolicy() {
        return getJson(apiUrl('/auth/policy')).then(function (res) {
            applySessionPolicy(res.payload || {});
            return res.payload || null;
        }).catch(function () {
            return null;
        });
    }

    function deviceFingerprint() {
        try {
            var parts = [
                (root.navigator && root.navigator.userAgent) || '',
                (root.navigator && root.navigator.language) || '',
                String((root.screen && root.screen.width) || 0),
                String((root.screen && root.screen.height) || 0),
                String(root.devicePixelRatio || 1)
            ];
            return btoa(unescape(encodeURIComponent(parts.join('|')))).slice(0, 96);
        } catch (e) {
            return '';
        }
    }

    function enrollWarmIdentity() {
        var lock = root.RatebOfflineAuthLock;
        if (!lock) {
            return Promise.resolve(null);
        }
        var deviceId = lock.getDeviceId();
        return postJson(apiUrl('/auth/identity/enroll'), {
            device_id: deviceId,
            label: 'ERP shell',
            ua: (root.navigator && root.navigator.userAgent) || '',
            fingerprint: deviceFingerprint()
        }).then(function (res) {
            var payload = res.payload || {};
            if (!(payload.ok && payload.identity && payload.device)) {
                try {
                    console.warn('[RATIB OFFLINE] identity enroll failed', res.http, payload.error || payload);
                } catch (e) { /* ignore */ }
                if (lock.markSessionNeedsReauth) {
                    lock.markSessionNeedsReauth();
                }
                return null;
            }
            var device = payload.device;
            persistEnrolledScope(device);
            if (lock.cacheDeviceStatus) {
                return lock.cacheDeviceStatus(lock.tenantScope(), device).then(function () {
                    return payload;
                });
            }
            return payload;
        }).catch(function (err) {
            try {
                console.warn('[RATIB OFFLINE] identity enroll network error', err);
            } catch (e2) { /* ignore */ }
            if (lock.markSessionNeedsReauth) {
                lock.markSessionNeedsReauth();
            }
            return null;
        });
    }

    function renewIdentityIfNeeded(row) {
        var lock = root.RatebOfflineAuthLock;
        if (!lock || !row || !row.identity_expires_at) {
            return Promise.resolve(row);
        }
        var claimsHint = {
            expires_at: parseInt(row.identity_expires_at, 10) || 0,
            identity_version: parseInt(row.identity_version, 10) || 1
        };
        if (!lock.needsIdentityRenewal || !lock.needsIdentityRenewal(claimsHint)) {
            return Promise.resolve(row);
        }
        return postJson(apiUrl('/devices/renew'), {
            device_id: lock.getDeviceId()
        }).then(function (res) {
            var payload = res.payload || {};
            if (!(payload.ok && payload.identity)) {
                return row;
            }
            var pin = root.prompt
                ? root.prompt('Renew offline identity — re-enter PIN (min 4). Cancel to keep current until expiry.', '')
                : '';
            if (!pin || String(pin).length < 4) {
                return row;
            }
            return lock.enrollPin(pin, { identity: payload.identity }).then(function () {
                return row;
            });
        }).catch(function () {
            return row;
        });
    }

    function maybePromptEnroll(identityPayload) {
        var lock = root.RatebOfflineAuthLock;
        if (!lock || !lock.isActive()) {
            return Promise.resolve(null);
        }
        var Schema = root.RatebOfflineSchema;
        if (!Schema) {
            return Promise.resolve(null);
        }
        var id = lock.vaultId(lock.tenantScope());
        if (!id) {
            return Promise.resolve(null);
        }
        return Schema.withStore(Schema.STORES.AUTH_VAULT, 'readonly', function (store) {
            return new Promise(function (res, rej) {
                var req = store.get(id);
                req.onsuccess = function () { res(req.result || null); };
                req.onerror = function () { rej(req.error); };
            });
        }).then(function (row) {
            var needsIdentity = !(row && row.identity_cipher);
            var needsPin = !(row && row.pin_hash);
            if (!needsPin && !needsIdentity) {
                return renewIdentityIfNeeded(row);
            }
            if (!(identityPayload && identityPayload.identity)) {
                return row;
            }
            var pin = root.prompt
                ? root.prompt('Set ERP offline unlock PIN (min 4 digits). Required for offline unlock.', '')
                : '';
            if (!pin || String(pin).length < 4) {
                try {
                    console.warn('[RATIB OFFLINE] PIN enroll skipped — offline unlock will require re-login enroll');
                } catch (e) { /* ignore */ }
                return null;
            }
            return lock.enrollPin(pin, { identity: identityPayload.identity }).then(function (enrolled) {
                if (enrolled && enrolled.ok) {
                    persistEnrolledScope(identityPayload.device || null);
                }
                return enrolled;
            });
        }).catch(function () {
            return null;
        });
    }

    function canEnrollWarmIdentity() {
        var companyId = parseInt(cfg.company_id, 10) || 0;
        var userId = parseInt(cfg.user_id, 10) || 0;
        if (!(companyId > 0 && userId > 0)) {
            return false;
        }
        // Unbound platform super-admin has no tenant shell identity.
        // Company-bound SA (dedicated/ops company) enrolls like a tenant user.
        return true;
    }

    function boot() {
        var f = (cfg.flags) || {};
        if (!(f['offline.enabled'] && f['offline.read_cache'] && f['offline.auth.unlock'])) {
            return;
        }
        if (!canEnrollWarmIdentity()) {
            return;
        }
        if (root.RatebOffline && typeof root.RatebOffline.init === 'function') {
            root.RatebOffline.init({
                apiBase: cfg.apiBase || '',
                probeUrl: cfg.probeUrl || null,
                flags: f,
                startConnectivity: !root.RatebOffline.isBooted(),
                startScheduler: false
            });
        }
        if (root.RatebOfflineAuthLock) {
            root.RatebOfflineAuthLock.start();
            if (root.document) {
                ['mousemove', 'keydown', 'touchstart', 'click'].forEach(function (ev) {
                    root.document.addEventListener(ev, function () {
                        if (root.RatebOfflineAuthLock && root.RatebOfflineAuthLock.touchIdle) {
                            root.RatebOfflineAuthLock.touchIdle();
                        }
                    }, { passive: true });
                });
            }
        }
        if (root.navigator && root.navigator.onLine !== false && csrfToken()) {
            loadPolicy().then(function (policy) {
                if (policy && policy.enroll && policy.enroll.ok === false
                    && String(policy.enroll.error || '') === 'super_admin_denied') {
                    try {
                        console.info('[RATIB OFFLINE] warm identity enroll skipped (unbound super-admin)');
                    } catch (e) { /* ignore */ }
                    return null;
                }
                return enrollWarmIdentity().then(function (payload) {
                    return maybePromptEnroll(payload).then(function () {
                        if (root.RatebOfflineAuthLock) {
                            root.RatebOfflineAuthLock.clearSessionNeedsReauth();
                            root.RatebOfflineAuthLock.requireUnlockIfNeeded();
                        }
                    });
                });
            });
        } else if (root.RatebOfflineAuthLock) {
            root.RatebOfflineAuthLock.markSessionNeedsReauth();
            root.RatebOfflineAuthLock.requireUnlockIfNeeded();
        }
    }

    if (root.document && root.document.readyState === 'loading') {
        root.document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})(typeof window !== 'undefined' ? window : globalThis);
