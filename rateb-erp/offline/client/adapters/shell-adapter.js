/**
 * RATEB Offline — ERP shell adapter (Phase 10.1 + Phase 14 ops pages).
 * Tenant-scoped snapshots; strips privileged UI + secrets.
 * Phase 14: allowlisted ops page snapshots (browse) when pilot.ops_pages is on.
 */
(function (root) {
    'use strict';

    var SNAPSHOT_PREFIX = 'erp_shell_chrome';
    var OPS_PAGE_PREFIX = 'erp_ops_page';
    var OPS_CACHE = 'rateb-erp-ops-pages-v14';

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
        out = out.replace(/<aside\b[^>]*>[\s\S]*?<\/aside>/gi,
            '<aside class="rateb-sidebar rateb-offline-shell-nav" id="rateb-sidebar" aria-label="Offline nav">'
            + '<div class="rateb-sidebar-brand"><span>RATEB ERP</span></div>'
            + '<p class="px-3 text-muted small">جاري تحميل القائمة…</p>'
            + '</aside>');
        // Keep top chrome nav; clear nested app navs only inside content if needed
        out = out.replace(/<nav\b([^>]*class=["'][^"']*rateb-sidebar[^"']*["'][^>]*)>[\s\S]*?<\/nav>/gi,
            '<nav$1 class="rateb-offline-shell-nav"></nav>');
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

    /** Phase 14 — keep main content for browse; still strip secrets / scripts / CSRF. */
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
        // Disable forms in cached browse snapshots (writes use live-page hooks).
        out = out.replace(/<form\b/gi, '<form data-rateb-offline-browse="1" onsubmit="return false;" ');
        out = out.replace(
            /<main\b([^>]*)>/i,
            '<main$1><div class="alert alert-warning m-3" role="status">'
            + 'وضع عدم الاتصال — صفحة محفوظة للتصفح. التعديل يتطلب اتصال أو نموذج حي قبل انقطاع الشبكة.'
            + '</div>'
        );
        return out;
    }

    function opsAllowlist() {
        var cfg = root.__RATEB_ERP_SHELL_OFFLINE__ || {};
        var paths = cfg.ops_page_paths;
        return Array.isArray(paths) ? paths : [];
    }

    function matchOpsPath(pathname) {
        var p = String(pathname || '').replace(/\/+$/, '').toLowerCase();
        var list = opsAllowlist();
        for (var i = 0; i < list.length; i++) {
            var a = String(list[i] || '').replace(/^\/+|\/+$/g, '').toLowerCase();
            if (!a) {
                continue;
            }
            var re = new RegExp('(^|/)' + a.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '(/|$)', 'i');
            if (re.test(p)) {
                return a;
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
        if (!isOpsPagesActive()) {
            return Promise.resolve({ skipped: true, disabled: true });
        }
        var path = (root.location && root.location.pathname) || '';
        if (!matchOpsPath(path)) {
            return Promise.resolve({ skipped: true, reason: 'path_not_allowlisted' });
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
                    try {
                        if (root.navigator && root.navigator.serviceWorker
                            && root.navigator.serviceWorker.controller) {
                            root.navigator.serviceWorker.controller.postMessage({
                                type: 'CACHE_ERP_OPS_PAGE',
                                url: href,
                                path: path,
                                html: safe
                            });
                        }
                    } catch (e) { /* ignore */ }
                    return { ok: true, id: id, bytes: safe.length, path: path };
                });
            });
        } catch (e) {
            return Promise.reject(e);
        }
    }

    function startAutoCapture() {
        if (!isActive()) {
            return;
        }
        var run = function () {
            captureChrome().catch(function () { /* ignore */ });
            if (isOpsPagesActive()) {
                captureOpsPage().catch(function () { /* ignore */ });
            }
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
        stripSensitive: stripSensitive,
        stripSensitiveOpsPage: stripSensitiveOpsPage
    };
})(typeof window !== 'undefined' ? window : globalThis);

