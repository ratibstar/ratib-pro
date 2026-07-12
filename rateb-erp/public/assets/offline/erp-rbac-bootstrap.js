/**
 * RATEB Offline — ERP RBAC bootstrap (Phase 12).
 * Loaded only when offline.enabled + read_cache + auth.unlock + rbac.cache are ON.
 * Does not modify Phase 10/11 adapters.
 */
(function (root) {
    'use strict';

    var cfg = root.__RATEB_ERP_SHELL_OFFLINE__ || {};
    var unlockWatch = null;

    function flagsOk() {
        var f = cfg.flags || {};
        return !!(f['offline.enabled']
            && f['offline.read_cache']
            && f['offline.auth.unlock']
            && f['offline.rbac.cache']);
    }

    function apiUrl(path) {
        var base = String(cfg.apiBase || '').replace(/\/$/, '');
        return base + path;
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

    function fetchVersion() {
        return getJson(apiUrl('/rbac/version')).then(function (r) {
            if (r.http >= 400 || !r.payload || !r.payload.ok) {
                throw new Error((r.payload && r.payload.error && r.payload.error.code) || 'version_failed');
            }
            return r.payload;
        });
    }

    function fetchManifest() {
        return getJson(apiUrl('/rbac/manifest')).then(function (r) {
            if (r.http >= 400 || !r.payload || !r.payload.ok) {
                throw new Error((r.payload && r.payload.error && r.payload.error.code) || 'manifest_failed');
            }
            return r.payload;
        });
    }

    function tryApply() {
        // Live online Admin: never mutate DOM with cached offline nav.
        if (!(root.navigator && root.navigator.onLine === false)) {
            var path = (root.location && root.location.pathname) || '';
            if (!/offline-shell\.html/i.test(path)) {
                return;
            }
        }
        var rbac = root.RatebOfflineRbacCache;
        var lock = root.RatebOfflineAuthLock;
        if (!rbac || !rbac.isActive()) {
            return;
        }
        if (lock && typeof lock.isUnlocked === 'function' && !lock.isUnlocked()) {
            rbac.clearNavDom();
            return;
        }
        rbac.applyCachedNav({ requireDeviceActive: true }).catch(function () {
            rbac.clearNavDom();
        });
    }

    function syncOnline() {
        var rbac = root.RatebOfflineRbacCache;
        if (!rbac || !rbac.isActive()) {
            return Promise.resolve();
        }
        if (root.navigator && root.navigator.onLine === false) {
            return Promise.resolve();
        }
        return rbac.syncFromServer(fetchVersion, fetchManifest).then(function () {
            tryApply();
        }).catch(function () {
            // Fail closed for apply; keep prior cache only if still valid offline.
            tryApply();
        });
    }

    function watchUnlock() {
        if (unlockWatch) {
            return;
        }
        unlockWatch = root.setInterval(function () {
            tryApply();
        }, 2000);
    }

    function boot() {
        if (!flagsOk()) {
            return;
        }
        if (cfg.is_super_admin && !(parseInt(cfg.company_id, 10) > 0)) {
            return;
        }
        if (!(parseInt(cfg.company_id, 10) > 0 && parseInt(cfg.user_id, 10) > 0)) {
            return;
        }
        if (root.RatebOffline && typeof root.RatebOffline.init === 'function') {
            root.RatebOffline.init({
                apiBase: cfg.apiBase || '',
                probeUrl: cfg.probeUrl || null,
                flags: cfg.flags || {},
                startConnectivity: !root.RatebOffline.isBooted(),
                startScheduler: false
            });
        }
        syncOnline();
        watchUnlock();
        if (root.document) {
            root.document.addEventListener('visibilitychange', function () {
                if (!root.document.hidden) {
                    tryApply();
                }
            });
        }
        if (root.addEventListener) {
            root.addEventListener('online', function () {
                syncOnline();
            });
        }
    }

    if (root.document && root.document.readyState === 'loading') {
        root.document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})(typeof window !== 'undefined' ? window : globalThis);
