/**
 * RATEB Offline — Cold bootstrap manager (client).
 * Restores cached ERP chrome after local PIN unlock without server calls.
 * Does not create PHP sessions. Does not alter Queue/Replay/SDK contracts.
 */
(function (root) {
    'use strict';

    var SCOPE_KEY = 'rateb_erp_offline_scope';

    function cfg() {
        return root.__RATEB_ERP_SHELL_OFFLINE__ || {};
    }

    function persistColdScope(session) {
        try {
            var prev = {};
            try {
                prev = JSON.parse(root.localStorage.getItem(SCOPE_KEY) || '{}') || {};
            } catch (e) { /* ignore */ }
            var flags = Object.assign({}, prev.flags || cfg().flags || {}, {
                'offline.enabled': true,
                'offline.read_cache': true,
                'offline.auth.unlock': true,
                'offline.auth.cold': true,
                'offline.rbac.cache': true
            });
            root.localStorage.setItem(SCOPE_KEY, JSON.stringify({
                company_id: session.company_id,
                branch_id: session.branch_id || 0,
                user_id: session.user_id,
                auth_unlock: true,
                cold_capable: !!session.cold_capable,
                flags: flags,
                saved_at: new Date().toISOString()
            }));
            root.__RATEB_ERP_SHELL_OFFLINE__ = root.__RATEB_ERP_SHELL_OFFLINE__ || {};
            root.__RATEB_ERP_SHELL_OFFLINE__.company_id = session.company_id;
            root.__RATEB_ERP_SHELL_OFFLINE__.branch_id = session.branch_id || 0;
            root.__RATEB_ERP_SHELL_OFFLINE__.user_id = session.user_id;
            root.__RATEB_ERP_SHELL_OFFLINE__.flags = flags;
        } catch (e) { /* ignore */ }
    }

    function restoreAfterUnlock(detail) {
        var local = root.RatebOfflineLocalSession;
        var lock = root.RatebOfflineAuthLock;
        var claims = (detail && detail.identity) ? detail.identity : null;
        if (!local) {
            return Promise.resolve({ ok: false, error: 'local_session_unavailable' });
        }
        if (!claims && detail && detail.warm === false && !detail.ok) {
            return Promise.resolve({ ok: false, error: 'no_claims' });
        }
        // Warm unlock without sealed cold claims: still allow local warm session marker.
        if (!claims) {
            var scope = lock && lock.tenantScope ? lock.tenantScope() : {};
            if (!scope.company_id || !scope.user_id) {
                return Promise.resolve({ ok: false, error: 'scope_required' });
            }
            if (!local.isColdEnabled()) {
                return Promise.resolve({ ok: true, mode: 'warm', skipped_cold: true });
            }
            return Promise.resolve({ ok: true, mode: 'warm', skipped_cold: true });
        }

        var created = local.createFromClaims(claims, {
            mode: claims.cold_capable ? 'cold' : 'warm'
        });
        if (!created.ok) {
            return Promise.resolve(created);
        }
        persistColdScope(created.session);
        local.applyThemeAndLocale(created.session);
        local.showBanner();
        if (lock && typeof lock.touchIdle === 'function') {
            lock.touchIdle();
        }

        var rbac = root.RatebOfflineRbacCache;
        if (rbac && typeof rbac.applyCachedNav === 'function') {
            return rbac.applyCachedNav({ requireDeviceActive: !!lock }).then(function (nav) {
                return {
                    ok: true,
                    mode: created.session.mode,
                    local_only: true,
                    nav: nav,
                    session: created.session
                };
            });
        }
        return Promise.resolve({
            ok: true,
            mode: created.session.mode,
            local_only: true,
            session: created.session
        });
    }

    function onUnlocked(ev) {
        var detail = (ev && ev.detail) ? ev.detail : {};
        // Live online session must keep full ERP DOM — never restore offline chrome/nav.
        if (detail.online_session || detail.live_ui) {
            return;
        }
        if (root.navigator && root.navigator.onLine !== false) {
            var path = (root.location && root.location.pathname) || '';
            if (!/offline-shell\.html/i.test(path)) {
                return;
            }
        }
        restoreAfterUnlock(detail).catch(function () { /* ignore */ });
    }

    function bindActivity() {
        if (!root.document) {
            return;
        }
        var handler = function () {
            var local = root.RatebOfflineLocalSession;
            var lock = root.RatebOfflineAuthLock;
            if (!local || !lock) {
                return;
            }
            var scope = lock.tenantScope ? lock.tenantScope() : {};
            local.touch(scope);
            if (typeof lock.touchIdle === 'function') {
                lock.touchIdle(scope);
            }
        };
        ['mousemove', 'keydown', 'touchstart', 'click'].forEach(function (ev) {
            root.document.addEventListener(ev, handler, { passive: true });
        });
    }

    function destroyOnLogout() {
        if (!root.document) {
            return;
        }
        root.document.addEventListener('click', function (ev) {
            var a = ev.target && ev.target.closest ? ev.target.closest('a[href]') : null;
            if (!a) {
                return;
            }
            var href = a.getAttribute('href') || '';
            if (!/\/logout/i.test(href)) {
                return;
            }
            var local = root.RatebOfflineLocalSession;
            var lock = root.RatebOfflineAuthLock;
            if (local && lock) {
                local.destroy(lock.tenantScope());
            }
        }, true);
    }

    function start() {
        if (root.addEventListener) {
            root.addEventListener('rateb:offline-unlocked', onUnlocked);
        }
        bindActivity();
        destroyOnLogout();
        var local = root.RatebOfflineLocalSession;
        var lock = root.RatebOfflineAuthLock;
        if (local && lock && lock.isUnlocked && lock.isUnlocked()) {
            var active = local.getActive(lock.tenantScope());
            if (active.ok) {
                local.showBanner();
                local.applyThemeAndLocale(active.session);
            }
        }
    }

    root.RatebOfflineBootstrapManager = {
        start: start,
        restoreAfterUnlock: restoreAfterUnlock
    };

    if (root.document && root.document.readyState === 'loading') {
        root.document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
})(typeof window !== 'undefined' ? window : globalThis);
