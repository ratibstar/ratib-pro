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
        if (scope.is_super_admin) {
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

    function renderNav(manifest) {
        if (!root.document || !manifest || !manifest.nav || !Array.isArray(manifest.nav.sections)) {
            clearNavDom();
            return false;
        }
        var disabled = manifest.offline_disabled_modules || [];
        var html = '<p class="rateb-offline-rbac-brand">RATEB ERP</p>';
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
            html += '<div class="rateb-offline-rbac-section">';
            if (section.title_key || section.title) {
                html += '<div class="rateb-offline-rbac-section-title">'
                    + escapeHtml(section.title || section.title_key || '')
                    + '</div>';
            }
            visible.forEach(function (item) {
                var href = safeHref(item.href);
                var label = String(item.label || item.label_key || item.path || '');
                html += '<a class="rateb-offline-rbac-link" href="' + escapeAttr(href) + '">'
                    + '<span>' + escapeHtml(label) + '</span></a>';
            });
            html += '</div>';
        });
        html += '</nav>';
        try {
            var targets = root.document.querySelectorAll('aside.rateb-offline-shell-nav, aside[aria-label="Offline nav"]');
            if (!targets.length) {
                targets = root.document.querySelectorAll('.rateb-offline-shell-nav');
            }
            if (!targets.length) {
                return false;
            }
            targets.forEach(function (el) {
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
        if (scope.is_super_admin || !scope.company_id || !scope.user_id) {
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
