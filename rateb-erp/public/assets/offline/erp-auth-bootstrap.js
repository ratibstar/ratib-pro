/**
 * RATEB Offline — ERP auth bootstrap (Phase 11).
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

    function enrollDevice() {
        var lock = root.RatebOfflineAuthLock;
        if (!lock) {
            return Promise.resolve(null);
        }
        var deviceId = lock.getDeviceId();
        return postJson(apiUrl('/auth/device/register'), {
            device_id: deviceId,
            label: 'ERP shell',
            ua: (root.navigator && root.navigator.userAgent) || ''
        }).then(function (reg) {
            return postJson(apiUrl('/auth/device/heartbeat'), { device_id: deviceId }).then(function (hb) {
                var device = (hb.payload && hb.payload.device)
                    || (reg.payload && reg.payload.device)
                    || null;
                if (device && lock.cacheDeviceStatus) {
                    return lock.cacheDeviceStatus(lock.tenantScope(), device).then(function () {
                        return device;
                    });
                }
                return device;
            });
        }).catch(function () {
            if (lock.markSessionNeedsReauth) {
                lock.markSessionNeedsReauth();
            }
            return null;
        });
    }

    function maybePromptEnroll() {
        var lock = root.RatebOfflineAuthLock;
        if (!lock || !lock.isActive()) {
            return;
        }
        // Non-blocking: if no vault yet and online, prompt once for PIN enroll.
        lock.readDeviceStatus(lock.tenantScope()).then(function () {
            return new Promise(function (resolve) {
                // Use schema get via unlock path — enroll UI minimal prompt.
                var Schema = root.RatebOfflineSchema;
                if (!Schema) {
                    resolve(null);
                    return;
                }
                var id = lock.vaultId(lock.tenantScope());
                if (!id) {
                    resolve(null);
                    return;
                }
                Schema.withStore(Schema.STORES.AUTH_VAULT, 'readonly', function (store) {
                    return new Promise(function (res, rej) {
                        var req = store.get(id);
                        req.onsuccess = function () { res(req.result || null); };
                        req.onerror = function () { rej(req.error); };
                    });
                }).then(function (row) {
                    if (row && row.pin_hash) {
                        resolve(row);
                        return;
                    }
                    var pin = root.prompt
                        ? root.prompt('Set ERP offline unlock PIN (min 4 digits). Cancel to skip.', '')
                        : '';
                    if (!pin || String(pin).length < 4) {
                        resolve(null);
                        return;
                    }
                    lock.enrollPin(pin).then(resolve).catch(function () { resolve(null); });
                }).catch(function () { resolve(null); });
            });
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
        if (root.RatebOffline && typeof root.RatebOffline.init === 'function' && !root.RatebOffline.isBooted()) {
            root.RatebOffline.init({
                apiBase: cfg.apiBase || '',
                probeUrl: cfg.probeUrl || null,
                flags: f,
                startConnectivity: true,
                startScheduler: false
            });
        }
        if (root.RatebOfflineAuthLock) {
            root.RatebOfflineAuthLock.start();
        }
        if (root.navigator && root.navigator.onLine !== false && csrfToken()) {
            enrollDevice().then(function () {
                maybePromptEnroll();
                if (root.RatebOfflineAuthLock) {
                    root.RatebOfflineAuthLock.clearSessionNeedsReauth();
                    root.RatebOfflineAuthLock.requireUnlockIfNeeded();
                }
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
