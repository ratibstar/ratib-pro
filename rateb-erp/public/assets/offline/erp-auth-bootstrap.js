/**
 * RATEB Offline — ERP auth bootstrap (Phase 11 + P1 Warm Identity + P2 renew/TTL).
 * Loaded only when offline.enabled + read_cache + auth.unlock are ON.
 */
(function (root) {
    'use strict';

    var cfg = root.__RATEB_ERP_SHELL_OFFLINE__ || {};

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
            var device = payload.device || null;
            if (device && lock.cacheDeviceStatus) {
                return lock.cacheDeviceStatus(lock.tenantScope(), device).then(function () {
                    return payload;
                });
            }
            return payload;
        }).catch(function () {
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
                ? root.prompt('Set ERP offline unlock PIN (min 4 digits). Cancel to skip.', '')
                : '';
            if (!pin || String(pin).length < 4) {
                return null;
            }
            return lock.enrollPin(pin, { identity: identityPayload.identity });
        }).catch(function () {
            return null;
        });
    }

    function boot() {
        var f = (cfg.flags) || {};
        if (!(f['offline.enabled'] && f['offline.read_cache'] && f['offline.auth.unlock'])) {
            return;
        }
        if (cfg.is_super_admin) {
            return;
        }
        if (!(parseInt(cfg.company_id, 10) > 0 && parseInt(cfg.user_id, 10) > 0)) {
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
            loadPolicy().then(function () {
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
