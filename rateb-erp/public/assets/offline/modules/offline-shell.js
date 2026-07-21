/*! RATEB Offline module offline-shell.js (Phase OA — sourced from offline/client). */

/* ---- shell-adapter.js ---- */
/**
 * RATEB Offline — ERP shell adapter (Phase 10.1 + Phase 14 ops pages).
 * Tenant-scoped snapshots; strips privileged UI + secrets.
 * Phase 14: allowlisted ops page snapshots (browse) when pilot.ops_pages is on.
 */
(function (root) {
    'use strict';

    var SNAPSHOT_PREFIX = 'erp_shell_chrome';
    var OPS_PAGE_PREFIX = 'erp_ops_page';
    var OPS_CACHE = 'rateb-erp-ops-pages-v36';

    function flags() {
        if (root.RatebOffline && typeof root.RatebOffline.flags === 'function') {
            return root.RatebOffline.flags() || {};
        }
        return {};
    }

    function isActive() {
        var f = flags();
        return !!(f['offline.enabled'] && f['offline.read_cache']);
    }

    function isOpsPagesActive() {
        var f = flags();
        return !!(f['offline.enabled'] && f['offline.read_cache'] && f['offline.pilot.ops_pages']);
    }

    function tenantScope() {
        var cfg = root.__RATEB_ERP_SHELL_OFFLINE__ || {};
        var companyId = parseInt(cfg.company_id, 10) || 0;
        var branchId = parseInt(cfg.branch_id, 10) || 0;
        var userId = parseInt(cfg.user_id, 10) || 0;
        return {
            company_id: companyId,
            branch_id: branchId,
            user_id: userId
        };
    }

    function snapshotId(scope) {
        scope = scope || tenantScope();
        if (!scope.company_id || !scope.user_id) {
            return null;
        }
        return SNAPSHOT_PREFIX
            + ':' + scope.company_id
            + ':' + scope.branch_id
            + ':' + scope.user_id;
    }

    function stripSensitive(html) {
        var out = String(html || '');
        // CSRF / tokens
        out = out.replace(/<meta[^>]*name=["']rateb-csrf["'][^>]*>/gi, '');
        out = out.replace(/name=["']_csrf["'][^>]*>/gi, '>');
        out = out.replace(/\svalue=["'][^"']*["'](?=[^>]*name=["']_csrf["'])/gi, ' value=""');
        // All scripts (theme applied by offline-shell.html itself)
        out = out.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '');
        // Drop live-only overlays that otherwise leak into the warm shell
        out = out.replace(/<div[^>]*(id|class)=["'][^"']*(rateb-modal|rateb-confirm|rateb-loading|rateb-attachments|modal)[^"']*["'][^>]*>[\s\S]*?<\/div>/gi, '');
        // Privileged / dynamic chrome — keep sidebar class so ERP CSS still applies offline
        out = out.replace(/<main\b[^>]*>[\s\S]*?<\/main>/i,
            '<main class="rateb-content rateb-offline-shell-main" id="rateb-offline-shell-main">'
            + '<div class="container py-4 rateb-offline-home">'
            + '<h2 class="h4 mb-2">وضع عدم الاتصال</h2>'
            + '<p class="text-muted mb-3">القائمة والصفحات المحفوظة متاحة للتصفح. البيانات الحية والتعديل يحتاجان اتصالاً.</p>'
            + '<div id="rateb-offline-module-links" class="rateb-offline-module-links"></div>'
            + '<p class="text-muted small mt-3">Offline shell — browse cached modules; reconnect for live data and edits.</p>'
            + '</div></main>');
        // Keep live sidebar structure for offline browse (sanitize dangerous bits only).
        // RBAC may later replace inner HTML when a cached manifest exists.
        out = out.replace(/<aside\b([^>]*)>/gi, function (m, attrs) {
            var a = String(attrs || '');
            if (!/\bclass=/i.test(a)) {
                a += ' class="rateb-sidebar rateb-offline-shell-nav"';
            } else if (!/rateb-offline-shell-nav/i.test(a)) {
                a = a.replace(/\bclass=(["'])([^"']*)\1/i, function (_mm, q, cls) {
                    return 'class=' + q + cls + ' rateb-offline-shell-nav' + q;
                });
            }
            if (!/\bid=/i.test(a)) {
                a += ' id="rateb-sidebar"';
            }
            if (!/aria-label=/i.test(a)) {
                a += ' aria-label="Offline nav"';
            }
            return '<aside' + a + '>';
        });
        // Do NOT wipe nested nav inside sidebar — only clear CSRF/forms via global form strip.
        // Force connection badge to Offline (never freeze "متصل" / Online into the cache).
        out = out.replace(/rateb-connection-indicator\s+is-online/gi, 'rateb-connection-indicator is-offline');
        out = out.replace(/(\sclass=["'][^"']*rateb-connection-indicator)(?![^"']*is-offline)/gi,
            '$1 is-offline');
        out = out.replace(/data-label-online=["'][^"']*["']/gi, 'data-label-online="Online"');
        out = out.replace(/(rateb-connection-indicator__label[^>]*>)\s*[^<]*/gi, '$1غير متصل');
        out = out.replace(/(title|aria-label)=["']\s*(متصل|Online)\s*["']/gi, '$1="غير متصل"');
        out = out.replace(/>\s*متصل\s*</g, '>غير متصل<');
        out = out.replace(/>\s*Online\s*</g, '>Offline<');
        // data-* that may leak URLs / session context
        out = out.replace(/\sdata-rateb-[a-z0-9_-]+=["'][^"']*["']/gi, '');
        out = out.replace(/\sdata-(csrf|token|session|user|company|branch)[a-z0-9_-]*=["'][^"']*["']/gi, '');
        // Forms / alerts / badges (counts, PII surfaces)
        out = out.replace(/<form\b[^>]*>[\s\S]*?<\/form>/gi, '');
        out = out.replace(/<div[^>]*class=["'][^"']*\balert\b[^"']*["'][^>]*>[\s\S]*?<\/div>/gi, '');
        out = out.replace(/<span[^>]*class=["'][^"']*badge[^"']*["'][^>]*>[\s\S]*?<\/span>/gi, '');
        // Inline event handlers
        out = out.replace(/\son[a-z]+\s*=\s*["'][^"']*["']/gi, '');
        out = out.replace(/\son[a-z]+\s*=\s*[^\s>]+/gi, '');
        // javascript: URLs
        out = out.replace(/\shref=["']\s*javascript:[^"']*["']/gi, ' href="#"');
        return out;
    }

    /** Hard online-only: period close / wipe-style / GL journal post. Approve/delete/pay queue offline. */
    var ONLINE_ONLY_FORM_RE = /(?:close[-_]?period|wipe|payroll[-_]?calc|transfer[-_]?funds|void[-_]?payment|gl[-_]?post|journal[-_]?post|journal-entries\/\d+\/(?:post|void))/i;

    function isOnlineOnlyFormMarkup(formTag) {
        var s = String(formTag || '');
        if (ONLINE_ONLY_FORM_RE.test(s)) {
            return true;
        }
        if (/\b(?:action|data-action|name|id)=["'][^"']*(?:close[-_]?period|wipe|payroll[-_]?calc|transfer[-_]?funds|void[-_]?payment|gl[-_]?post|journal[-_]?post)[^"']*["']/i.test(s)) {
            return true;
        }
        return false;
    }

    function buildOpsOfflineBootScripts() {
        var cfg = root.__RATEB_ERP_SHELL_OFFLINE__ || {};
        var flags = {};
        try {
            flags = (cfg.flags && typeof cfg.flags === 'object') ? cfg.flags : {};
        } catch (e0) {
            flags = {};
        }
        var safe = {
            apiBase: cfg.apiBase || '',
            probeUrl: cfg.probeUrl || '',
            flags: flags,
            startConnectivity: true,
            company_id: parseInt(cfg.company_id, 10) || 0,
            tenant_id: parseInt(cfg.tenant_id || cfg.company_id, 10) || 0,
            branch_id: parseInt(cfg.branch_id, 10) || 0,
            user_id: parseInt(cfg.user_id, 10) || 0,
            is_super_admin: !!cfg.is_super_admin,
            logout_vault_policy: cfg.logout_vault_policy || 'keep_vault',
            session_policy: (cfg.session_policy && typeof cfg.session_policy === 'object') ? cfg.session_policy : {},
            client_queue_max: parseInt(cfg.client_queue_max, 10) || 500,
            ops_page_paths: Array.isArray(cfg.ops_page_paths) ? cfg.ops_page_paths : [],
            ops_page_routes: (cfg.ops_page_routes && typeof cfg.ops_page_routes === 'object') ? cfg.ops_page_routes : {},
            ops_form_hooks: Array.isArray(cfg.ops_form_hooks) ? cfg.ops_form_hooks : [],
            pilot_ops_pages: !!cfg.pilot_ops_pages,
            offline_ops_snapshot: true
        };
        var json;
        try {
            json = JSON.stringify(safe);
        } catch (e1) {
            json = '{}';
        }
        var base = '/rateb-erp/public/';
        try {
            var p = String((root.location && root.location.pathname) || '');
            var m = p.match(/^(.*\/public\/)/i);
            if (m && m[1]) {
                base = m[1];
            }
        } catch (e2) { /* ignore */ }
        return '<script>(function(){try{if(navigator.onLine===false)return;var m=document.querySelector(".rateb-offline-home,#rateb-offline-shell-main,[data-rateb-offline-ops-banner]");if(!m)return;var base=(location.pathname.match(/^(.*\\/public\\/)/i)||[])[1]||"/rateb-erp/public/";fetch(base+"connectivity-probe.json?_rateb_probe="+Date.now(),{credentials:"same-origin",cache:"no-store",headers:{"Accept":"application/json","X-Rateb-Connectivity":"1"}}).then(function(res){if(!res||!res.ok)return;var u=new URL(location.href);u.searchParams.set("rateb_live",String(Date.now()));location.replace(u.href)}).catch(function(){})}catch(e){}})();</script>\n'
            + '<script>window.__RATEB_ERP_SHELL_OFFLINE__=' + json
            + ';window.__RATEB_ERP_MASTER_DATA__=window.__RATEB_ERP_SHELL_OFFLINE__;</script>\n'
            + '<script src="' + base + 'assets/offline/offline-bootstrap.js" defer></script>\n'
            + '<script src="' + base + 'assets/offline/erp-shell-bootstrap.js" defer></script>\n'
            + '<script src="' + base + 'assets/offline/erp-ops-forms-bootstrap.js" defer></script>\n';
    }

    /** Phase 14+ — keep main; mark writable Tier-1 forms; reinject offline hooks. */
    function stripSensitiveOpsPage(html) {
        var out = String(html || '');
        out = out.replace(/<meta[^>]*name=["']rateb-csrf["'][^>]*>/gi, '');
        out = out.replace(/name=["']_csrf["'][^>]*>/gi, '>');
        out = out.replace(/\svalue=["'][^"']*["'](?=[^>]*name=["']_csrf["'])/gi, ' value=""');
        out = out.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '');
        out = out.replace(/rateb-connection-indicator\s+is-online/gi, 'rateb-connection-indicator is-offline');
        out = out.replace(/(\sclass=["'][^"']*rateb-connection-indicator)(?![^"']*is-offline)/gi,
            '$1 is-offline');
        out = out.replace(/(rateb-connection-indicator__label">)\s*[^<]*/gi, '$1غير متصل');
        out = out.replace(/(title|aria-label)=["']\s*(متصل|Online)\s*["']/gi, '$1="غير متصل"');
        out = out.replace(/\sdata-rateb-[a-z0-9_-]+=["'][^"']*["']/gi, '');
        out = out.replace(/\sdata-(csrf|token|session)[a-z0-9_-]*=["'][^"']*["']/gi, '');
        out = out.replace(/\son[a-z]+\s*=\s*["'][^"']*["']/gi, '');
        out = out.replace(/\son[a-z]+\s*=\s*[^\s>]+/gi, '');
        out = out.replace(/\shref=["']\s*javascript:[^"']*["']/gi, ' href="#"');
        // Writable drafts offline; money/posting/final-approve stay hard-disabled.
        out = out.replace(/<form\b([^>]*)>/gi, function (_m, attrs) {
            var a = String(attrs || '');
            if (isOnlineOnlyFormMarkup(a)) {
                return '<form data-rateb-offline-online-only="1" onsubmit="return false;" ' + a + '>';
            }
            return '<form data-rateb-offline-writable="1" ' + a + '>';
        });
        out = out.replace(
            /<main\b([^>]*)>/i,
            '<main$1><div class="alert alert-info m-3" role="status" data-rateb-offline-ops-banner="1">'
            + 'وضع عدم الاتصال — يمكنك إنشاء مسودات؛ الترحيل/الاعتماد النهائي/المدفوعات تتطلب اتصالاً.'
            + '</div>'
        );
        var boot = buildOpsOfflineBootScripts();
        if (/<\/body>/i.test(out)) {
            out = out.replace(/<\/body>/i, boot + '</body>');
        } else {
            out += boot;
        }
        return out;
    }

    var allowlistLoadPromise = null;

    function opsAllowlist() {
        var cfg = root.__RATEB_ERP_SHELL_OFFLINE__ || {};
        var paths = cfg.ops_page_paths;
        return Array.isArray(paths) ? paths : [];
    }

    /** Logical key → canonical route from rateb_app_route() (injected as ops_page_routes). */
    function opsRouteMap() {
        var cfg = root.__RATEB_ERP_SHELL_OFFLINE__ || {};
        var routes = cfg.ops_page_routes;
        if (!routes || typeof routes !== 'object') {
            return {};
        }
        return routes;
    }

    /** Load paths/routes from allowlist JSON when not inlined in HTML (keeps pages lean). */
    function ensureOpsAllowlist() {
        var cfg = root.__RATEB_ERP_SHELL_OFFLINE__ || {};
        var paths = cfg.ops_page_paths;
        var routes = cfg.ops_page_routes;
        var hasPaths = Array.isArray(paths) && paths.length > 0;
        var hasRoutes = routes && typeof routes === 'object' && Object.keys(routes).length > 0;
        if (hasPaths && hasRoutes) {
            return Promise.resolve(cfg);
        }
        if (allowlistLoadPromise) {
            return allowlistLoadPromise;
        }
        var url = cfg.allowlistUrl;
        if (!url && root.location) {
            try {
                var path = String(root.location.pathname || '');
                var m = path.match(/^(.*?\/rateb-erp\/public)(?:\/|$)/i);
                var prefix = (m && m[1]) ? m[1] : '/rateb-erp/public';
                url = root.location.origin + prefix + '/assets/offline/ops-page-allowlist.json';
            } catch (eUrl) {
                url = '';
            }
        }
        if (!url || !root.fetch) {
            return Promise.resolve(cfg);
        }
        allowlistLoadPromise = root.fetch(String(url), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' }
        }).then(function (res) {
            if (!res || !res.ok) {
                return null;
            }
            return res.json();
        }).then(function (data) {
            if (!data || typeof data !== 'object') {
                return cfg;
            }
            var next = root.__RATEB_ERP_SHELL_OFFLINE__ || cfg;
            if (Array.isArray(data.paths)) {
                next.ops_page_paths = data.paths.map(function (p) {
                    return String(p || '').replace(/^\/+|\/+$/g, '');
                }).filter(Boolean);
            }
            if (data.routes && typeof data.routes === 'object') {
                next.ops_page_routes = data.routes;
            }
            if (Array.isArray(data.form_hooks) && !(next.ops_form_hooks && next.ops_form_hooks.length)) {
                next.ops_form_hooks = data.form_hooks;
            }
            root.__RATEB_ERP_SHELL_OFFLINE__ = next;
            return next;
        }).catch(function () {
            return cfg;
        });
        return allowlistLoadPromise;
    }

    /**
     * Build absolute URL for an allowlist logical key using canonical route only.
     * Never prefixes /admin/ops/ manually.
     */
    function canonicalUrlForLogical(logical) {
        logical = String(logical || '').replace(/^\/+|\/+$/g, '');
        if (!logical) {
            return null;
        }
        var map = opsRouteMap();
        var route = map[logical] ? String(map[logical]).replace(/^\/+|\/+$/g, '') : '';
        if (!route) {
            return null;
        }
        try {
            var origin = (root.location && root.location.origin) || '';
            var prefix = '';
            try {
                var path = String((root.location && root.location.pathname) || '');
                var m = path.match(/^(.*?\/rateb-erp\/public)(?:\/|$)/i);
                if (m && m[1]) {
                    prefix = m[1];
                } else if (/\/rateb-erp\/public/i.test(String((root.location && root.location.href) || ''))) {
                    prefix = '/rateb-erp/public';
                }
            } catch (ePref) { /* ignore */ }
            var href = origin + prefix + '/' + route;
            var companyId = parseInt((root.__RATEB_ERP_SHELL_OFFLINE__ || {}).company_id, 10) || 0;
            if (companyId > 0 && href.indexOf('company_id=') === -1) {
                href += (href.indexOf('?') === -1 ? '?' : '&') + 'company_id=' + companyId;
            }
            return href;
        } catch (e) {
            return null;
        }
    }

    function isHttpErrorDocument() {
        try {
            var title = String((root.document && root.document.title) || '');
            var text = '';
            try {
                text = String((root.document.body && root.document.body.innerText) || '').slice(0, 400);
            } catch (eT) { /* ignore */ }
            if (/^\s*404\b/i.test(title) || /\b404\s*\|/i.test(title)) {
                return true;
            }
            if (/page not found/i.test(text) && /\b404\b/.test(text)) {
                return true;
            }
            var statusMeta = root.document.querySelector('meta[name="rateb-http-status"]');
            if (statusMeta) {
                var st = parseInt(statusMeta.getAttribute('content') || '0', 10) || 0;
                if (st > 0 && st !== 200) {
                    return true;
                }
            }
        } catch (e) { /* ignore */ }
        return false;
    }

    function matchOpsPath(pathname) {
        var p = String(pathname || '').replace(/\/+$/, '').toLowerCase();
        // Exact لوحة التحكم — never treat all /admin/* as dashboard.
        if (/(^|\/)admin$/.test(p)) {
            return 'admin';
        }
        var list = opsAllowlist();
        for (var i = 0; i < list.length; i++) {
            var a = String(list[i] || '').replace(/^\/+|\/+$/g, '').toLowerCase();
            if (!a || a === 'admin') {
                continue;
            }
            var re = new RegExp('(^|/)' + a.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '(/|$)', 'i');
            if (re.test(p)) {
                return a;
            }
        }
        // Also match canonical routes from rateb_app_route() (e.g. admin/hr/attendance).
        var map = opsRouteMap();
        var keys = Object.keys(map || {});
        for (var j = 0; j < keys.length; j++) {
            var logical = keys[j];
            var route = String(map[logical] || '').replace(/^\/+|\/+$/g, '').toLowerCase();
            if (!route) {
                continue;
            }
            var re2 = new RegExp('(^|/)' + route.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '(/|$)', 'i');
            if (re2.test(p)) {
                return logical;
            }
        }
        return null;
    }

    function opsSnapshotId(pathname, scope) {
        scope = scope || tenantScope();
        var matched = matchOpsPath(pathname);
        if (!matched || !scope.company_id || !scope.user_id) {
            return null;
        }
        return OPS_PAGE_PREFIX
            + ':' + scope.company_id
            + ':' + scope.branch_id
            + ':' + scope.user_id
            + ':' + matched;
    }

    function putSnapshot(record) {
        var Schema = root.RatebOfflineSchema;
        if (!Schema || !Schema.withStore) {
            return Promise.reject(new Error('schema_unavailable'));
        }
        return Schema.withStore(Schema.STORES.SNAPSHOTS, 'readwrite', function (store) {
            store.put(record);
            return true;
        });
    }

    function getSnapshot(scope) {
        var id = snapshotId(scope);
        if (!id) {
            return Promise.resolve(null);
        }
        var Schema = root.RatebOfflineSchema;
        if (!Schema || !Schema.withStore) {
            return Promise.resolve(null);
        }
        return Schema.withStore(Schema.STORES.SNAPSHOTS, 'readonly', function (store) {
            return new Promise(function (resolve, reject) {
                var req = store.get(id);
                req.onsuccess = function () { resolve(req.result || null); };
                req.onerror = function () { reject(req.error); };
            });
        });
    }

    function putOpsPageCache(url, html) {
        if (!root.caches || !root.caches.open) {
            return Promise.resolve(false);
        }
        try {
            var res = new Response(html, {
                status: 200,
                headers: {
                    'Content-Type': 'text/html; charset=utf-8',
                    'X-Rateb-Offline': '1',
                    'X-Rateb-Ops-Page': '1',
                    'Cache-Control': 'no-store'
                }
            });
            return root.caches.open(OPS_CACHE).then(function (cache) {
                return cache.put(url, res).then(function () { return true; });
            }).catch(function () { return false; });
        } catch (e) {
            return Promise.resolve(false);
        }
    }

    function captureChrome() {
        if (!isActive()) {
            return Promise.resolve({ skipped: true, disabled: true });
        }
        var scope = tenantScope();
        var id = snapshotId(scope);
        if (!id) {
            return Promise.resolve({ skipped: true, reason: 'tenant_scope_required' });
        }
        if (!root.document || !root.document.documentElement) {
            return Promise.resolve({ skipped: true, reason: 'no_document' });
        }
        try {
            var html = '<!DOCTYPE html>\n' + root.document.documentElement.outerHTML;
            var safe = stripSensitive(html);
            var record = {
                id: id,
                kind: 'erp_shell_chrome',
                company_id: scope.company_id,
                branch_id: scope.branch_id,
                user_id: scope.user_id,
                captured_at: new Date().toISOString(),
                path: (root.location && root.location.pathname) || '',
                html: safe
            };
            return putSnapshot(record).then(function () {
                return { ok: true, id: id, bytes: safe.length };
            });
        } catch (e) {
            return Promise.reject(e);
        }
    }

    function captureOpsPage() {
        var pathEarly = (root.location && root.location.pathname) || '';
        var isAdmin = /\/admin(\/|$)/i.test(String(pathEarly));
        // Capture any Admin page when visited online (not only allowlisted ops).
        if (!isOpsPagesActive() && !isAdmin) {
            return Promise.resolve({ skipped: true, disabled: true });
        }
        return ensureOpsAllowlist().then(function () {
        var path = (root.location && root.location.pathname) || '';
        if (!matchOpsPath(path) && !/\/admin(\/|$)/i.test(path)) {
            return Promise.resolve({ skipped: true, reason: 'path_not_allowlisted' });
        }
        if (isHttpErrorDocument()) {
            try {
                console.warn('[RATIB OFFLINE] INVALID ROUTE', path, 'document looks like HTTP error; skip capture');
            } catch (eInv) { /* ignore */ }
            return Promise.resolve({ skipped: true, reason: 'invalid_route_http_error' });
        }
        var scope = tenantScope();
        var id = opsSnapshotId(path, scope);
        if (!id) {
            return Promise.resolve({ skipped: true, reason: 'tenant_scope_required' });
        }
        if (!root.document || !root.document.documentElement) {
            return Promise.resolve({ skipped: true, reason: 'no_document' });
        }
        try {
            var html = '<!DOCTYPE html>\n' + root.document.documentElement.outerHTML;
            var safe = stripSensitiveOpsPage(html);
            var href = (root.location && root.location.href) || path;
            var record = {
                id: id,
                kind: 'erp_ops_page',
                company_id: scope.company_id,
                branch_id: scope.branch_id,
                user_id: scope.user_id,
                captured_at: new Date().toISOString(),
                path: path,
                url: href,
                html: safe
            };
            return putSnapshot(record).then(function () {
                return putOpsPageCache(href, safe).then(function () {
                    var origin = (root.location && root.location.origin) || '';
                    return putOpsPageCache(origin + path, safe);
                }).then(function () {
                    return { ok: true, id: id, bytes: safe.length, path: path };
                });
            });
        } catch (e) {
            return Promise.reject(e);
        }
        });
    }

    function cacheFetchedOpsHtml(href, path, html) {
        var safe = stripSensitiveOpsPage(html);
        var originPath = '';
        try {
            var u = new URL(href, root.location.origin);
            originPath = u.origin + u.pathname;
        } catch (eU) {
            originPath = href;
        }
        return putOpsPageCache(href, safe).then(function () {
            return putOpsPageCache(originPath, safe);
        }).then(function () {
            // Also mirror into SW-managed keys without posting huge HTML bodies.
            return { ok: true, bytes: safe.length, path: path, url: href };
        });
    }

    function prefetchAllowlistedLinks() {
        if (!root.document || !root.fetch) {
            return;
        }
        if (typeof navigator !== 'undefined' && navigator.onLine === false) {
            return;
        }
        // Prefer the dedicated full-warm engine when present.
        try {
            if (root.RatebOfflineFullWarm && typeof root.RatebOfflineFullWarm.start === 'function') {
                root.RatebOfflineFullWarm.start({ force: false });
                return;
            }
        } catch (eFull) { /* fall through */ }
        // Once per browser tab/session — never re-warm 40 ERP pages on every navigation
        // (that saturates PHP/MySQL and makes the whole product feel extremely slow).
        try {
            var warmAt = parseInt(root.sessionStorage.getItem('rateb_erp_ops_warm_at') || '0', 10) || 0;
            if (warmAt > 0 && (Date.now() - warmAt) < (30 * 60 * 1000)) {
                return;
            }
            root.sessionStorage.setItem('rateb_erp_ops_warm_at', String(Date.now()));
        } catch (eGate) { /* ignore and continue once */ }

        ensureOpsAllowlist().then(function () {
        var seen = {};
        var urls = [];

        // Prefer a small priority set first (production UX), then a few more from the map.
        var priority = ['purchase-requests', 'inventory', 'hr/attendance', 'warehouses', 'purchase-orders'];
        var map = opsRouteMap();
        priority.forEach(function (logical) {
            var href = canonicalUrlForLogical(logical);
            if (!href || seen[href]) {
                return;
            }
            seen[href] = true;
            urls.push({ href: href, logical: logical, path: String((map && map[logical]) || '') });
        });

        Object.keys(map || {}).forEach(function (logical) {
            if (urls.length >= 120) {
                return;
            }
            var href = canonicalUrlForLogical(logical);
            if (!href || seen[href]) {
                return;
            }
            seen[href] = true;
            urls.push({ href: href, logical: logical, path: String(map[logical] || '') });
        });

        // Live sidebar links that already match allowlist (fill remaining slots only).
        var links = root.document.querySelectorAll(
            'aside.rateb-sidebar a[href], #rateb-sidebar a[href], .rateb-offline-rbac-link[href]'
        );
        Array.prototype.forEach.call(links, function (a) {
            if (urls.length >= 120) {
                return;
            }
            var href = (a.getAttribute('href') || '').trim();
            if (!href || href === '#' || /^javascript:/i.test(href) || seen[href]) {
                return;
            }
            try {
                var u = new URL(href, root.location.origin);
                if (u.origin !== root.location.origin) {
                    return;
                }
                if (!matchOpsPath(u.pathname)) {
                    return;
                }
                seen[href] = true;
                urls.push({ href: u.href, logical: matchOpsPath(u.pathname), path: u.pathname });
            } catch (e) { /* ignore */ }
        });

        var i = 0;
        var tick = function () {
            if (i >= urls.length) {
                return;
            }
            var next = urls[i++];
            root.fetch(next.href, {
                credentials: 'same-origin',
                headers: { Accept: 'text/html', 'X-Rateb-Shell-Warm': '1' }
            }).then(function (res) {
                var status = res ? res.status : 0;
                if (!res || status !== 200) {
                    try {
                        console.warn('[RATIB OFFLINE] INVALID ROUTE', next.logical || next.path, next.href, 'HTTP', status);
                    } catch (eLog) { /* ignore */ }
                    return null;
                }
                return res.text().then(function (html) {
                    if (!html || /page not found/i.test(String(html).slice(0, 800)) && /\b404\b/.test(String(html).slice(0, 800))) {
                        try {
                            console.warn('[RATIB OFFLINE] INVALID ROUTE', next.logical || next.path, next.href, 'body looks like 404');
                        } catch (e404) { /* ignore */ }
                        return null;
                    }
                    return cacheFetchedOpsHtml(next.href, next.path || next.logical, html);
                });
            }).catch(function () { /* ignore */ }).then(function () {
                // Slow, idle-only warm — never compete with the user's current navigation.
                if (typeof root.requestIdleCallback === 'function') {
                    root.requestIdleCallback(tick, { timeout: 12000 });
                } else {
                    setTimeout(tick, 2500);
                }
            });
        };
        if (typeof root.requestIdleCallback === 'function') {
            root.requestIdleCallback(tick, { timeout: 15000 });
        } else {
            setTimeout(tick, 5000);
        }
        }).catch(function () { /* allowlist fetch failed — skip warm */ });
    }

    function startAutoCapture() {
        if (!isActive()) {
            return;
        }
        var run = function () {
            captureChrome().then(function (res) {
                try {
                    console.info('[RATIB OFFLINE] captureChrome', res || {});
                } catch (e0) { /* ignore */ }
            }).catch(function () { /* ignore */ });
            // Always cache current Admin page when visiting (every module, not only ops pilot).
            var pathNow = (root.location && root.location.pathname) || '';
            if (/\/admin(\/|$)/i.test(pathNow) || isOpsPagesActive()) {
                captureOpsPage().then(function (res) {
                    try {
                        console.info('[RATIB OFFLINE] captureOpsPage', res || {});
                    } catch (e1) { /* ignore */ }
                }).catch(function () { /* ignore */ });
            }
            if (isOpsPagesActive()) {
                prefetchAllowlistedLinks();
            }
            // Full-program warm: every sidebar + allowlist route (no visit required).
            try {
                if (root.RatebOfflineFullWarm && typeof root.RatebOfflineFullWarm.start === 'function') {
                    root.RatebOfflineFullWarm.start({ force: false });
                }
            } catch (eWarm) { /* ignore */ }
        };
        if (root.document && root.document.readyState === 'complete') {
            setTimeout(run, 800);
        } else if (root.addEventListener) {
            root.addEventListener('load', function () {
                setTimeout(run, 800);
            }, { once: true });
        }
    }

    root.RatebOfflineShellAdapter = {
        SNAPSHOT_PREFIX: SNAPSHOT_PREFIX,
        OPS_PAGE_PREFIX: OPS_PAGE_PREFIX,
        OPS_CACHE: OPS_CACHE,
        isActive: isActive,
        isOpsPagesActive: isOpsPagesActive,
        tenantScope: tenantScope,
        snapshotId: snapshotId,
        opsSnapshotId: opsSnapshotId,
        matchOpsPath: matchOpsPath,
        captureChrome: captureChrome,
        captureOpsPage: captureOpsPage,
        getSnapshot: getSnapshot,
        startAutoCapture: startAutoCapture,
        prefetchAllowlistedLinks: prefetchAllowlistedLinks,
        canonicalUrlForLogical: canonicalUrlForLogical,
        opsRouteMap: opsRouteMap,
        ensureOpsAllowlist: ensureOpsAllowlist,
        stripSensitive: stripSensitive,
        stripSensitiveOpsPage: stripSensitiveOpsPage
    };
})(typeof window !== 'undefined' ? window : globalThis);


