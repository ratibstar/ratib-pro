/**
 * Phase 12 — Apply cached RBAC nav inside offline-shell.html host.
 * Additive only; does not alter Phase 10 DOMParser import path.
 */
(function (root) {
    'use strict';

    function readScope() {
        var cfg = root.__RATEB_ERP_SHELL_OFFLINE__ || {};
        var scope = {
            company_id: parseInt(cfg.company_id, 10) || 0,
            branch_id: parseInt(cfg.branch_id, 10) || 0,
            user_id: parseInt(cfg.user_id, 10) || 0
        };
        if (!scope.company_id || !scope.user_id) {
            try {
                var q = new URLSearchParams(root.location.search || '');
                scope.company_id = parseInt(q.get('company_id'), 10) || 0;
                scope.branch_id = parseInt(q.get('branch_id'), 10) || 0;
                scope.user_id = parseInt(q.get('user_id'), 10) || 0;
            } catch (e) { /* ignore */ }
        }
        if (!scope.company_id || !scope.user_id) {
            try {
                var raw = root.localStorage.getItem('rateb_erp_offline_scope');
                if (raw) {
                    var o = JSON.parse(raw);
                    scope.company_id = parseInt(o.company_id, 10) || 0;
                    scope.branch_id = parseInt(o.branch_id, 10) || 0;
                    scope.user_id = parseInt(o.user_id, 10) || 0;
                }
            } catch (e2) { /* ignore */ }
        }
        return scope;
    }

    function isUnlocked(scope) {
        try {
            var key = 'rateb_erp_unlock_until:'
                + scope.company_id + ':' + (scope.branch_id || 0) + ':' + scope.user_id;
            var until = parseInt(sessionStorage.getItem(key) || '0', 10) || 0;
            return until > Date.now();
        } catch (e) {
            return false;
        }
    }

    function boot() {
        var rbac = root.RatebOfflineRbacCache;
        if (!rbac) {
            return;
        }
        var scope = readScope();
        if (!scope.company_id || !scope.user_id) {
            return;
        }
        root.__RATEB_ERP_SHELL_OFFLINE__ = root.__RATEB_ERP_SHELL_OFFLINE__ || {};
        root.__RATEB_ERP_SHELL_OFFLINE__.company_id = scope.company_id;
        root.__RATEB_ERP_SHELL_OFFLINE__.branch_id = scope.branch_id;
        root.__RATEB_ERP_SHELL_OFFLINE__.user_id = scope.user_id;
        root.__RATEB_ERP_SHELL_OFFLINE__.flags = root.__RATEB_ERP_SHELL_OFFLINE__.flags || {
            'offline.enabled': true,
            'offline.read_cache': true,
            'offline.auth.unlock': true,
            'offline.rbac.cache': true
        };
        if (root.RatebOffline && typeof root.RatebOffline.init === 'function') {
            root.RatebOffline.init({
                flags: root.__RATEB_ERP_SHELL_OFFLINE__.flags,
                startConnectivity: false,
                startScheduler: false
            });
        }
        if (!isUnlocked(scope)) {
            if (rbac.clearNavDom) {
                rbac.clearNavDom();
            }
            return;
        }
        // Device gate: prefer auth lock helper when present.
        var apply = function () {
            rbac.applyCachedNav({ requireDeviceActive: !!root.RatebOfflineAuthLock }).catch(function () {
                rbac.clearNavDom();
            });
        };
        // Shell import is async — retry briefly.
        var tries = 0;
        var timer = root.setInterval(function () {
            tries += 1;
            var hasAside = !!(root.document && root.document.querySelector('aside.rateb-offline-shell-nav, aside[aria-label="Offline nav"]'));
            if (hasAside || tries >= 15) {
                root.clearInterval(timer);
                if (hasAside) {
                    apply();
                }
            }
        }, 300);
    }

    if (root.document && root.document.readyState === 'loading') {
        root.document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})(typeof window !== 'undefined' ? window : globalThis);
