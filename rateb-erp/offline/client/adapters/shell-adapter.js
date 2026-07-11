/**
 * RATEB Offline — ERP shell adapter (Phase 10.1 blocking fixes).
 * Tenant-scoped snapshots; strips privileged UI + secrets.
 */
(function (root) {
    'use strict';

    var SNAPSHOT_PREFIX = 'erp_shell_chrome';

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
        // Privileged / dynamic chrome
        out = out.replace(/<main\b[^>]*>[\s\S]*?<\/main>/i,
            '<main class="rateb-offline-shell-main" id="rateb-offline-shell-main">'
            + '<div class="container py-4">'
            + '<p class="text-muted">وضع عدم الاتصال — أعد الاتصال لعرض البيانات الحية والتعديل.</p>'
            + '<p class="text-muted small">Offline shell — reconnect for live data and edits.</p>'
            + '</div></main>');
        out = out.replace(/<aside\b[^>]*>[\s\S]*?<\/aside>/gi,
            '<aside class="rateb-offline-shell-nav" aria-label="Offline nav"><p>RATEB ERP</p></aside>');
        out = out.replace(/<nav\b[^>]*>[\s\S]*?<\/nav>/gi, '<nav class="rateb-offline-shell-nav"></nav>');
        // Force connection badge to Offline (never freeze "متصل" / Online into the cache).
        out = out.replace(/rateb-connection-indicator\s+is-online/gi, 'rateb-connection-indicator is-offline');
        out = out.replace(/(\sclass=["'][^"']*rateb-connection-indicator)(?![^"']*is-offline)/gi,
            '$1 is-offline');
        out = out.replace(/data-label-online=["'][^"']*["']/gi, 'data-label-online="Online"');
        out = out.replace(/(rateb-connection-indicator__label">)\s*[^<]*/gi, '$1غير متصل');
        out = out.replace(/(title|aria-label)=["']\s*(متصل|Online)\s*["']/gi, '$1="غير متصل"');
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

    function startAutoCapture() {
        if (!isActive()) {
            return;
        }
        var run = function () {
            captureChrome().catch(function () { /* ignore */ });
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
        isActive: isActive,
        tenantScope: tenantScope,
        snapshotId: snapshotId,
        captureChrome: captureChrome,
        getSnapshot: getSnapshot,
        startAutoCapture: startAutoCapture,
        stripSensitive: stripSensitive
    };
})(typeof window !== 'undefined' ? window : globalThis);
