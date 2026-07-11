/**
 * RATEB Offline — Local session only (Cold Offline Identity).
 * Never creates PHP sessions / CSRF / server auth. UI restoration only.
 * SDK version and IndexedDB schema unchanged.
 */
(function (root) {
    'use strict';

    var SESSION_KEY_PREFIX = 'rateb_erp_local_session:';
    var BANNER_ATTR = 'data-rateb-offline-banner';

    function cfg() {
        return root.__RATEB_ERP_SHELL_OFFLINE__ || {};
    }

    function flags() {
        if (root.RatebOffline && typeof root.RatebOffline.flags === 'function') {
            return root.RatebOffline.flags() || {};
        }
        return cfg().flags || {};
    }

    function isColdEnabled() {
        var f = flags();
        return !!(f['offline.enabled']
            && f['offline.read_cache']
            && f['offline.auth.unlock']
            && f['offline.auth.cold']);
    }

    function sessionPolicy() {
        var lock = root.RatebOfflineAuthLock;
        if (lock && typeof lock.sessionPolicy === 'function') {
            return lock.sessionPolicy();
        }
        var p = cfg().session_policy || {};
        return {
            unlock_ttl_ms: parseInt(p.unlock_ttl_ms, 10) || (8 * 60 * 60 * 1000),
            idle_timeout_ms: parseInt(p.idle_timeout_ms, 10) || (15 * 60 * 1000),
            max_offline_session_ms: parseInt(p.max_offline_session_ms, 10) || (72 * 60 * 60 * 1000),
            clock_skew_seconds: parseInt(p.clock_skew_seconds, 10) || 300
        };
    }

    function scopeKey(scope) {
        scope = scope || (root.RatebOfflineAuthLock && root.RatebOfflineAuthLock.tenantScope
            ? root.RatebOfflineAuthLock.tenantScope()
            : {});
        return String(scope.company_id || 0) + ':' + String(scope.branch_id || 0) + ':' + String(scope.user_id || 0);
    }

    function storageKey(scope) {
        return SESSION_KEY_PREFIX + scopeKey(scope);
    }

    function read(scope) {
        try {
            var raw = sessionStorage.getItem(storageKey(scope));
            if (!raw) {
                return null;
            }
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    }

    function write(scope, session) {
        try {
            sessionStorage.setItem(storageKey(scope), JSON.stringify(session));
            return true;
        } catch (e) {
            return false;
        }
    }

    function destroy(scope) {
        try {
            sessionStorage.removeItem(storageKey(scope));
        } catch (e) { /* ignore */ }
        hideBanner();
        return { ok: true, destroyed: true, local_only: true };
    }

    function validateSession(session) {
        if (!session || session.kind !== 'erp_local_offline') {
            return { ok: false, error: 'session_missing' };
        }
        if (session.server_authz_bypass === true) {
            return { ok: false, error: 'authz_bypass_forbidden' };
        }
        var now = Date.now();
        if ((parseInt(session.expires_at_ms, 10) || 0) <= now) {
            return { ok: false, error: 'session_expired' };
        }
        if ((parseInt(session.absolute_expires_at_ms, 10) || 0) <= now) {
            return { ok: false, error: 'absolute_timeout' };
        }
        var idleMs = sessionPolicy().idle_timeout_ms;
        var last = parseInt(session.last_activity_ms, 10) || 0;
        if (last > 0 && (now - last) > idleMs) {
            return { ok: false, error: 'idle_timeout' };
        }
        return { ok: true, session: session };
    }

    /**
     * Create local-only session from decrypted identity claims (after PIN unlock).
     */
    function createFromClaims(claims, opts) {
        opts = opts || {};
        if (!claims || !claims.company_id || !claims.user_id) {
            return { ok: false, error: 'claims_required' };
        }
        var policy = sessionPolicy();
        var now = Date.now();
        var offlinePolicy = claims.offline_policy || {};
        if (offlinePolicy.server_authz_bypass === true) {
            return { ok: false, error: 'authz_bypass_forbidden' };
        }
        var session = {
            kind: 'erp_local_offline',
            mode: opts.mode || (claims.cold_capable ? 'cold' : 'warm'),
            company_id: parseInt(claims.company_id, 10) || 0,
            branch_id: parseInt(claims.branch_id, 10) || 0,
            user_id: parseInt(claims.user_id, 10) || 0,
            user_uuid: String(claims.user_uuid || claims.user_id || ''),
            device_uuid: String(claims.device_id || ''),
            roles: Array.isArray(claims.roles) ? claims.roles : [],
            permissions: Array.isArray(claims.permissions) ? claims.permissions : [],
            plan_modules: Array.isArray(claims.plan_modules) ? claims.plan_modules : [],
            identity_version: parseInt(claims.identity_version, 10) || 1,
            jti: String(claims.jti || ''),
            locale: claims.locale || '',
            theme: claims.theme || '',
            ui_only: true,
            server_authz_bypass: false,
            cold_capable: !!claims.cold_capable,
            issued_at_ms: now,
            last_activity_ms: now,
            expires_at_ms: now + policy.unlock_ttl_ms,
            absolute_expires_at_ms: now + policy.max_offline_session_ms,
            identity_expires_at: parseInt(claims.expires_at, 10) || 0
        };
        var scope = {
            company_id: session.company_id,
            branch_id: session.branch_id,
            user_id: session.user_id
        };
        write(scope, session);
        return { ok: true, session: session, local_only: true };
    }

    function touch(scope) {
        var session = read(scope);
        var v = validateSession(session);
        if (!v.ok) {
            destroy(scope);
            return v;
        }
        session.last_activity_ms = Date.now();
        write(scope, session);
        return { ok: true, session: session };
    }

    function getActive(scope) {
        var session = read(scope);
        var v = validateSession(session);
        if (!v.ok) {
            if (session) {
                destroy(scope);
            }
            return v;
        }
        return v;
    }

    function showBanner() {
        if (!root.document || !root.document.body) {
            return;
        }
        if (root.document.querySelector('[' + BANNER_ATTR + ']')) {
            return;
        }
        var el = root.document.createElement('div');
        el.setAttribute(BANNER_ATTR, '1');
        el.className = 'rateb-offline-local-session-banner';
        el.setAttribute('role', 'status');
        el.textContent = 'RATEB ERP — Offline session (local only)';
        root.document.body.insertBefore(el, root.document.body.firstChild);
    }

    function hideBanner() {
        try {
            var nodes = root.document.querySelectorAll('[' + BANNER_ATTR + ']');
            nodes.forEach(function (n) {
                if (n.parentNode) {
                    n.parentNode.removeChild(n);
                }
            });
        } catch (e) { /* ignore */ }
    }

    function applyThemeAndLocale(session) {
        try {
            if (session.theme) {
                root.localStorage.setItem('rateb_erp_theme', String(session.theme));
                root.document.documentElement.setAttribute('data-theme', String(session.theme));
            }
            if (session.locale) {
                root.localStorage.setItem('rateb_erp_locale', String(session.locale));
            }
        } catch (e) { /* ignore */ }
    }

    root.RatebOfflineLocalSession = {
        isColdEnabled: isColdEnabled,
        createFromClaims: createFromClaims,
        getActive: getActive,
        touch: touch,
        destroy: destroy,
        showBanner: showBanner,
        hideBanner: hideBanner,
        applyThemeAndLocale: applyThemeAndLocale,
        sessionPolicy: sessionPolicy
    };
})(typeof window !== 'undefined' ? window : globalThis);
