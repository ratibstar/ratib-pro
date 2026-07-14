/*! RATEB Offline module offline-rbac.js (Phase OA — sourced from offline/client). */

/* ---- rbac-cache-adapter.js ---- */
/**
 * RATEB Offline — ERP RBAC/nav cache adapter (Phase 12).
 * Stores structured manifest in snapshots (kind erp_rbac). UI only — never server authz.
 */
(function (root) {
    'use strict';

    var SNAPSHOT_KIND = 'erp_rbac';

    function cfg() {
        return root.__RATEB_ERP_SHELL_OFFLINE__ || {};
    }

    function flags() {
        if (root.RatebOffline && typeof root.RatebOffline.flags === 'function') {
            return root.RatebOffline.flags() || {};
        }
        return cfg().flags || {};
    }

    function isActive() {
        var f = flags();
        return !!(f['offline.enabled']
            && f['offline.read_cache']
            && f['offline.auth.unlock']
            && f['offline.rbac.cache']);
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

    function snapshotId(scope) {
        scope = scope || tenantScope();
        if (!scope.company_id || !scope.user_id) {
            return null;
        }
        return SNAPSHOT_KIND
            + ':' + scope.company_id
            + ':' + (scope.branch_id || 0)
            + ':' + scope.user_id;
    }

    function withSnapshots(mode, fn) {
        var Schema = root.RatebOfflineSchema;
        if (!Schema || !Schema.withStore) {
            return Promise.reject(new Error('schema_unavailable'));
        }
        return Schema.withStore(Schema.STORES.SNAPSHOTS, mode, fn);
    }

    function putManifest(manifest) {
        if (!manifest || !manifest.id) {
            return Promise.reject(new Error('invalid_manifest'));
        }
        return withSnapshots('readwrite', function (store) {
            store.put(manifest);
            return true;
        });
    }

    function getManifest(scope) {
        var id = snapshotId(scope);
        if (!id) {
            return Promise.resolve(null);
        }
        return withSnapshots('readonly', function (store) {
            return new Promise(function (resolve, reject) {
                var req = store.get(id);
                req.onsuccess = function () { resolve(req.result || null); };
                req.onerror = function () { reject(req.error); };
            });
        });
    }

    function deleteManifest(scope) {
        var id = snapshotId(scope);
        if (!id) {
            return Promise.resolve(false);
        }
        return withSnapshots('readwrite', function (store) {
            store.delete(id);
            return true;
        });
    }

    function tenantMatch(row, scope) {
        scope = scope || tenantScope();
        if (!row) {
            return false;
        }
        return parseInt(row.company_id, 10) === scope.company_id
            && parseInt(row.branch_id, 10) === (scope.branch_id || 0)
            && parseInt(row.user_id, 10) === scope.user_id
            && String(row.kind || '') === SNAPSHOT_KIND;
    }

    function isExpired(row) {
        if (!row || !row.expires_at) {
            return true;
        }
        return parseInt(row.expires_at, 10) * 1000 <= Date.now();
    }

    /**
     * Fail-closed validation before UI use.
     * @returns {{ok:boolean, error?:string, manifest?:object}}
     */
    function validateForUse(row, scope, opts) {
        opts = opts || {};
        scope = scope || tenantScope();
        // Platform SA without tenant cannot use company RBAC cache.
        // Company-bound SA (shell company_id > 0) may use warm offline nav.
        if (scope.is_super_admin && !(scope.company_id > 0)) {
            return { ok: false, error: 'super_admin_denied' };
        }
        if (!row) {
            return { ok: false, error: 'missing_snapshot' };
        }
        if (!tenantMatch(row, scope)) {
            return { ok: false, error: 'tenant_mismatch' };
        }
        if (isExpired(row)) {
            return { ok: false, error: 'expired' };
        }
        if (opts.expectVersion && String(row.rbac_version || '') !== String(opts.expectVersion)) {
            return { ok: false, error: 'version_mismatch' };
        }
        if (opts.requireDeviceActive !== false) {
            var lock = root.RatebOfflineAuthLock;
            if (lock && typeof lock.readDeviceStatus === 'function') {
                // Async path handled by validateForUseAsync
            }
        }
        return { ok: true, manifest: row };
    }

    function validateForUseAsync(row, scope, opts) {
        var base = validateForUse(row, scope, opts);
        if (!base.ok) {
            return Promise.resolve(base);
        }
        if (opts && opts.requireDeviceActive === false) {
            return Promise.resolve(base);
        }
        var lock = root.RatebOfflineAuthLock;
        if (!lock || typeof lock.readDeviceStatus !== 'function') {
            return Promise.resolve({ ok: false, error: 'inactive_device' });
        }
        return lock.readDeviceStatus(scope || tenantScope()).then(function (device) {
            var status = device && device.status ? String(device.status).toLowerCase() : '';
            if (status !== 'active') {
                return { ok: false, error: 'inactive_device' };
            }
            return base;
        }).catch(function () {
            return { ok: false, error: 'inactive_device' };
        });
    }

    function can(slug, manifest) {
        // Company-bound super-admin: warm nav is UI-only; do not hide catalog by empty slug lists.
        var c = cfg();
        if (c.is_super_admin && (parseInt(c.company_id, 10) > 0)) {
            return true;
        }
        if (!manifest || !Array.isArray(manifest.permission_slugs)) {
            return false;
        }
        slug = String(slug || '');
        if (slug === '') {
            return true;
        }
        return manifest.permission_slugs.indexOf(slug) !== -1;
    }

    function navCan(permission, module, manifest) {
        if (!manifest) {
            return false;
        }
        permission = String(permission || '');
        module = String(module || '');
        var disabled = manifest.offline_disabled_modules || [];
        if (module && disabled.indexOf(module) !== -1) {
            return false;
        }
        var c = cfg();
        if (c.is_super_admin && (parseInt(c.company_id, 10) > 0)) {
            return true;
        }
        if (permission !== '' && !can(permission, manifest)) {
            return false;
        }
        if (module === '') {
            return true;
        }
        var mods = manifest.plan_modules || [];
        return mods.indexOf(module) !== -1;
    }

    function clearNavDom() {
        try {
            var nodes = root.document.querySelectorAll('.rateb-offline-shell-nav, aside.rateb-offline-shell-nav');
            nodes.forEach(function (el) {
                if (el.tagName === 'ASIDE' || el.classList.contains('rateb-offline-shell-nav')) {
                    el.innerHTML = '<p>RATEB ERP</p>';
                }
            });
        } catch (e) { /* ignore */ }
    }

    function sectionTitle(section) {
        section = section || {};
        var titled = String(section.title || '').trim();
        if (titled && titled !== String(section.title_key || '')) {
            return titled;
        }
        var key = String(section.title_key || section.title || '').trim();
        var map = {
            dashboard: 'لوحة التحكم',
            procurement: 'المشتريات',
            inventory: 'المخزون',
            hr: 'الموارد البشرية',
            suppliers: 'الموردون',
            account: 'الحساب'
        };
        return map[key] || titled || key;
    }

    function ensureOfflineNavStyles() {
        if (!root.document || !root.document.head) {
            return;
        }
        if (root.document.getElementById('rateb-offline-rbac-nav-css')) {
            return;
        }
        var css = root.document.createElement('style');
        css.id = 'rateb-offline-rbac-nav-css';
        css.textContent = ''
            + 'aside.rateb-sidebar.rateb-offline-shell-nav,'
            + 'aside.rateb-offline-shell-nav{'
            + 'display:block;min-width:16rem;max-width:18rem;padding:0;overflow:auto;'
            + 'background:var(--rateb-sidebar,#070d18);color:var(--rateb-sidebar-text,#cbd5e1);}'
            + 'aside.rateb-sidebar .rateb-sidebar-brand{padding:1rem 1.15rem;font-weight:700;'
            + 'border-bottom:1px solid rgba(255,255,255,.08);}'
            + 'aside.rateb-sidebar .rateb-nav-section{padding:.85rem 1.1rem .25rem;font-size:.7rem;'
            + 'opacity:.6;font-weight:600;}'
            + 'aside.rateb-sidebar a.rateb-nav-link{display:flex;align-items:center;gap:.55rem;'
            + 'padding:.5rem .85rem;margin:.1rem .45rem;border-radius:8px;color:inherit;'
            + 'text-decoration:none;font-size:.86rem;}'
            + 'aside.rateb-sidebar a.rateb-nav-link:hover{background:rgba(255,255,255,.06);color:#fff;}'
            + '.rateb-offline-home .list-group-item{display:block;padding:.65rem .85rem;margin:.25rem 0;'
            + 'border-radius:8px;background:#1a1d24;color:#e8eaed;text-decoration:none;border:1px solid #2a2f3a;}'
            + '.rateb-offline-home .list-group-item:hover{border-color:#3d4654;}';
        root.document.head.appendChild(css);
    }

    function renderNav(manifest) {
        if (!root.document || !manifest || !manifest.nav || !Array.isArray(manifest.nav.sections)) {
            clearNavDom();
            return false;
        }
        ensureOfflineNavStyles();
        var disabled = manifest.offline_disabled_modules || [];
        var html = '<div class="rateb-sidebar-brand"><span>RATEB ERP</span></div>';
        html += '<nav class="rateb-offline-rbac-nav" aria-label="Offline navigation">';
        manifest.nav.sections.forEach(function (section) {
            var items = (section && section.items) || [];
            var visible = [];
            items.forEach(function (item) {
                if (!item || item.offline_actionable === false) {
                    return;
                }
                var mod = String(item.module || '');
                if (mod && disabled.indexOf(mod) !== -1) {
                    return;
                }
                if (!navCan(item.permission || '', mod, manifest) && (item.permission || mod)) {
                    return;
                }
                visible.push(item);
            });
            if (visible.length === 0) {
                return;
            }
            html += '<div class="rateb-offline-rbac-section rateb-nav-group is-open">';
            html += '<div class="rateb-nav-section">' + escapeHtml(sectionTitle(section)) + '</div>';
            visible.forEach(function (item) {
                var href = safeHref(item.href);
                var label = String(item.label || item.label_key || item.path || '');
                var icon = String(item.icon || 'fa-circle');
                html += '<a class="rateb-nav-link rateb-offline-rbac-link" href="' + escapeAttr(href) + '">'
                    + '<i class="fas ' + escapeAttr(icon) + ' rateb-nav-group-icon" aria-hidden="true"></i>'
                    + '<span>' + escapeHtml(label) + '</span></a>';
            });
            html += '</div>';
        });
        html += '</nav>';
        try {
            var targets = root.document.querySelectorAll(
                'aside.rateb-offline-shell-nav, aside[aria-label="Offline nav"]'
            );
            // Do not wipe live Admin sidebars (aside.rateb-sidebar) — only the offline-shell host.
            if (!targets.length) {
                var path = String((root.location && root.location.pathname) || '');
                if (/offline-shell\.html$/i.test(path) || root.document.getElementById('rateb-offline-shell-main')) {
                    targets = root.document.querySelectorAll('#rateb-sidebar, aside.rateb-sidebar');
                }
            }
            if (!targets.length) {
                return false;
            }
            targets.forEach(function (el) {
                el.classList.add('rateb-sidebar', 'rateb-offline-shell-nav');
                el.innerHTML = html;
            });
            return true;
        } catch (e) {
            return false;
        }
    }

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function escapeAttr(s) {
        return escapeHtml(s).replace(/'/g, '&#39;');
    }

    /** Phase 13.1 — block javascript:/data:/vbscript: hrefs (IndexedDB poison). */
    function safeHref(raw) {
        var href = String(raw || '#').trim();
        if (href === '') {
            return '#';
        }
        var lower = href.toLowerCase();
        if (lower.indexOf('javascript:') === 0
            || lower.indexOf('data:') === 0
            || lower.indexOf('vbscript:') === 0) {
            return '#';
        }
        return href;
    }

    /**
     * Online: compare version; on mismatch/expiry delete + store fresh.
     */
    function syncFromServer(fetchVersion, fetchManifest) {
        if (!isActive()) {
            return Promise.resolve({ skipped: true, reason: 'rbac_disabled' });
        }
        var scope = tenantScope();
        // Deny only unbound platform SA; company-bound SA may sync warm nav.
        if ((scope.is_super_admin && !(scope.company_id > 0)) || !scope.company_id || !scope.user_id) {
            return Promise.resolve({ skipped: true, reason: 'denied' });
        }
        return getManifest(scope).then(function (cached) {
            return fetchVersion().then(function (verPayload) {
                var current = verPayload && verPayload.rbac_version
                    ? String(verPayload.rbac_version)
                    : '';
                if (!current) {
                    return { ok: false, error: 'version_unavailable' };
                }
                var needRefresh = !cached
                    || isExpired(cached)
                    || !tenantMatch(cached, scope)
                    || String(cached.rbac_version || '') !== current;
                if (!needRefresh) {
                    return { ok: true, refreshed: false, manifest: cached };
                }
                return deleteManifest(scope).then(function () {
                    return fetchManifest().then(function (manPayload) {
                        var man = manPayload && manPayload.manifest ? manPayload.manifest : null;
                        if (!man || String(man.rbac_version || '') !== current) {
                            // Accept if server returned fresh version even if race
                            if (!man) {
                                return { ok: false, error: 'manifest_unavailable' };
                            }
                        }
                        return putManifest(man).then(function () {
                            return { ok: true, refreshed: true, manifest: man };
                        });
                    });
                });
            });
        });
    }

    function applyCachedNav(opts) {
        if (!isActive()) {
            return Promise.resolve({ ok: false, error: 'rbac_disabled' });
        }
        // Captured ops pages already have the live Admin sidebar — never replace with
        // a simplified RBAC tree (that caused offline nav mismatch vs online).
        try {
            var cfgSnap = root.__RATEB_ERP_SHELL_OFFLINE__ || {};
            if (cfgSnap.offline_ops_snapshot
                || (root.document && root.document.querySelector('[data-rateb-offline-ops-banner]'))) {
                return Promise.resolve({ ok: true, skipped: true, reason: 'keep_captured_live_nav' });
            }
        } catch (eSkip) { /* ignore */ }
        var scope = tenantScope();
        return getManifest(scope).then(function (row) {
            return validateForUseAsync(row, scope, opts || {}).then(function (v) {
                if (!v.ok) {
                    clearNavDom();
                    return v;
                }
                var rendered = renderNav(v.manifest);
                return { ok: rendered, error: rendered ? undefined : 'render_failed', manifest: v.manifest };
            });
        });
    }

    root.RatebOfflineRbacCache = {
        KIND: SNAPSHOT_KIND,
        isActive: isActive,
        tenantScope: tenantScope,
        snapshotId: snapshotId,
        putManifest: putManifest,
        getManifest: getManifest,
        deleteManifest: deleteManifest,
        isExpired: isExpired,
        tenantMatch: tenantMatch,
        validateForUse: validateForUse,
        validateForUseAsync: validateForUseAsync,
        can: can,
        navCan: navCan,
        renderNav: renderNav,
        clearNavDom: clearNavDom,
        syncFromServer: syncFromServer,
        applyCachedNav: applyCachedNav
    };
})(typeof window !== 'undefined' ? window : globalThis);

