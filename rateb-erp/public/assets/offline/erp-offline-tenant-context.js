/**
 * Phase OE — Offline tenant context (identity persist/restore only).
 * Stores the minimum authenticated ERP scope during online sessions.
 * Restores it on offline-shell BEFORE module init / unlock hosts run.
 * Never stores passwords or access tokens.
 */
(function (root) {
    'use strict';

    var SCOPE_KEY = 'rateb_erp_offline_scope';
    var IDENTITY_KEY = 'rateb_erp_offline_identity';

    function intOr(v, d) {
        var n = parseInt(v, 10);
        return isNaN(n) ? (d || 0) : n;
    }

    function cfg() {
        return root.__RATEB_ERP_SHELL_OFFLINE__ || {};
    }

    function deviceId() {
        try {
            var id = root.localStorage.getItem('rateb_erp_device_uuid') || '';
            if (id) {
                return String(id);
            }
        } catch (e) { /* ignore */ }
        try {
            id = 'dev_' + Math.random().toString(36).slice(2) + Date.now().toString(36);
            root.localStorage.setItem('rateb_erp_device_uuid', id);
            return id;
        } catch (e2) {
            return '';
        }
    }

    function language() {
        try {
            return String(
                (root.document && root.document.documentElement && root.document.documentElement.lang)
                || (root.navigator && root.navigator.language)
                || 'ar'
            ).slice(0, 16);
        } catch (e) {
            return 'ar';
        }
    }

    function roleIdsFrom(c) {
        var raw = c.role_ids || c.roles || [];
        if (!Array.isArray(raw)) {
            return [];
        }
        return raw.map(function (r) {
            if (r && typeof r === 'object') {
                return intOr(r.id || r.role_id, 0);
            }
            return intOr(r, 0);
        }).filter(function (id) { return id > 0; });
    }

    function buildIdentity(c) {
        c = c || cfg();
        var companyId = intOr(c.company_id, 0);
        var userId = intOr(c.user_id, 0);
        if (!(companyId > 0 && userId > 0)) {
            return null;
        }
        var flags = c.flags || {};
        return {
            user_id: userId,
            company_id: companyId,
            tenant_id: intOr(c.tenant_id || c.company_id, companyId),
            branch_id: intOr(c.branch_id, 0),
            role_ids: roleIdsFrom(c),
            permissions_version: String(
                c.permissions_version
                || c.rbac_version
                || c.permissions_hash
                || (flags && flags['offline.rbac.cache'] ? 'rbac-cache' : '')
                || ''
            ),
            language: language(),
            device_id: String(c.device_id || deviceId() || ''),
            is_super_admin: !!c.is_super_admin,
            auth_unlock: !!(flags['offline.auth.unlock']),
            flags: {
                'offline.enabled': true,
                'offline.read_cache': true,
                'offline.auth.unlock': !!flags['offline.auth.unlock'],
                'offline.rbac.cache': !!flags['offline.rbac.cache']
            },
            saved_at: new Date().toISOString()
        };
    }

    function writeIdentity(identity) {
        if (!identity) {
            return false;
        }
        try {
            var json = JSON.stringify(identity);
            root.localStorage.setItem(SCOPE_KEY, json);
            root.localStorage.setItem(IDENTITY_KEY, json);
            return true;
        } catch (e) {
            return false;
        }
    }

    function readStored() {
        try {
            var raw = root.localStorage.getItem(IDENTITY_KEY)
                || root.localStorage.getItem(SCOPE_KEY);
            if (!raw) {
                return null;
            }
            var o = JSON.parse(raw);
            if (!(intOr(o.company_id, 0) > 0 && intOr(o.user_id, 0) > 0)) {
                return null;
            }
            return o;
        } catch (e) {
            return null;
        }
    }

    function applyToWindow(identity) {
        if (!identity) {
            return false;
        }
        root.__RATEB_ERP_SHELL_OFFLINE__ = root.__RATEB_ERP_SHELL_OFFLINE__ || {};
        var w = root.__RATEB_ERP_SHELL_OFFLINE__;
        w.company_id = intOr(identity.company_id, 0);
        w.tenant_id = intOr(identity.tenant_id || identity.company_id, 0);
        w.branch_id = intOr(identity.branch_id, 0);
        w.user_id = intOr(identity.user_id, 0);
        w.role_ids = Array.isArray(identity.role_ids) ? identity.role_ids.slice() : [];
        w.permissions_version = String(identity.permissions_version || '');
        w.language = String(identity.language || language());
        w.device_id = String(identity.device_id || '');
        w.is_super_admin = !!identity.is_super_admin;
        w.flags = w.flags || {};
        var f = identity.flags || {};
        w.flags['offline.enabled'] = true;
        w.flags['offline.read_cache'] = true;
        if (f['offline.auth.unlock'] != null) {
            w.flags['offline.auth.unlock'] = !!f['offline.auth.unlock'];
        }
        if (f['offline.rbac.cache'] != null) {
            w.flags['offline.rbac.cache'] = !!f['offline.rbac.cache'];
        }
        root.__RATEB_ERP_MASTER_DATA__ = root.__RATEB_ERP_MASTER_DATA__ || w;
        try {
            root.__RATEB_ERP_TENANT_CONTEXT__ = {
                company_id: w.company_id,
                tenant_id: w.tenant_id,
                branch_id: w.branch_id,
                user_id: w.user_id,
                role_ids: w.role_ids,
                permissions_version: w.permissions_version,
                language: w.language,
                device_id: w.device_id,
                restored: true,
                at: new Date().toISOString()
            };
        } catch (eT) { /* ignore */ }
        return w.company_id > 0 && w.user_id > 0;
    }

    /** Persist from live admin cfg (online). No-op without valid ids. */
    function persistFromConfig() {
        var identity = buildIdentity(cfg());
        if (!identity) {
            return false;
        }
        applyToWindow(identity);
        return writeIdentity(identity);
    }

    /** Restore stored identity into window before ERP modules init. */
    function restore() {
        var c = cfg();
        if (intOr(c.company_id, 0) > 0 && intOr(c.user_id, 0) > 0) {
            applyToWindow(c);
            // Refresh local snapshot when live cfg is available.
            persistFromConfig();
            return true;
        }
        var stored = readStored();
        if (!stored) {
            return false;
        }
        return applyToWindow(stored);
    }

    function clear() {
        try { root.localStorage.removeItem(SCOPE_KEY); } catch (e1) { /* ignore */ }
        try { root.localStorage.removeItem(IDENTITY_KEY); } catch (e2) { /* ignore */ }
        try {
            if (root.__RATEB_ERP_SHELL_OFFLINE__) {
                root.__RATEB_ERP_SHELL_OFFLINE__.company_id = 0;
                root.__RATEB_ERP_SHELL_OFFLINE__.user_id = 0;
            }
            root.__RATEB_ERP_TENANT_CONTEXT__ = null;
        } catch (e3) { /* ignore */ }
    }

    function bindLogoutClear() {
        try {
            if (!root.document || !root.document.addEventListener) {
                return;
            }
            root.document.addEventListener('click', function (ev) {
                var t = ev.target;
                while (t && t !== root.document) {
                    if (t.tagName === 'A') {
                        var href = String(t.getAttribute('href') || '');
                        if (/\/logout(\?|$|\/)/i.test(href)) {
                            clear();
                        }
                        break;
                    }
                    t = t.parentNode;
                }
            }, true);
        } catch (e) { /* ignore */ }
    }

    // Restore first (offline-shell), then persist if live cfg present (admin).
    var restored = restore();
    if (!restored) {
        persistFromConfig();
    } else {
        // If live cfg arrived with ids, prefer refreshing storage.
        persistFromConfig();
    }
    bindLogoutClear();

    // Re-persist after lazy shell cfg merges / company switches.
    try {
        root.setInterval(function () {
            try {
                if (root.navigator && root.navigator.onLine === false) {
                    return;
                }
            } catch (eOn) { /* ignore */ }
            persistFromConfig();
        }, 5000);
    } catch (eIv) { /* ignore */ }

    root.RatebOfflineTenantContext = {
        persist: persistFromConfig,
        restore: restore,
        clear: clear,
        read: readStored,
        apply: applyToWindow,
        key: SCOPE_KEY
    };

    try {
        root.dispatchEvent(new Event('rateb-offline-tenant-ready'));
    } catch (eEv) { /* ignore */ }
})(typeof window !== 'undefined' ? window : globalThis);
