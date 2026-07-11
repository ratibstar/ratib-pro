/**
 * RATEB Offline — ERP shell adapter (Phase 10).
 * Stores sanitized shell chrome in existing snapshots IndexedDB store.
 * Activated only when offline.enabled + offline.read_cache are true.
 * Does not touch queue, replay, or business modules.
 */
(function (root) {
    'use strict';

    var SNAPSHOT_ID = 'erp_shell_chrome';

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

    function stripSensitive(html) {
        var out = String(html || '');
        // Never persist CSRF tokens.
        out = out.replace(/<meta[^>]*name=["']rateb-csrf["'][^>]*>/gi, '');
        out = out.replace(/name=["']_csrf["'][^>]*value=["'][^"']*["']/gi, 'name="_csrf" value=""');
        out = out.replace(/value=["'][^"']*["'][^>]*name=["']_csrf["']/gi, 'value="" name="_csrf"');
        // Drop main page body content (authenticated data).
        out = out.replace(
            /<main\b[^>]*>[\s\S]*?<\/main>/i,
            '<main class="rateb-offline-shell-main"><div class="container py-4">'
            + '<p class="text-muted">Offline shell chrome — reconnect for live data.</p>'
            + '</div></main>'
        );
        // Remove inline scripts that may embed tokens or session payloads.
        out = out.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, function (block) {
            if (/rateb_erp_theme|data-theme|localStorage/i.test(block) && !/csrf|token|session/i.test(block)) {
                return block;
            }
            return '';
        });
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

    function getSnapshot() {
        var Schema = root.RatebOfflineSchema;
        if (!Schema || !Schema.withStore) {
            return Promise.resolve(null);
        }
        return Schema.withStore(Schema.STORES.SNAPSHOTS, 'readonly', function (store) {
            return new Promise(function (resolve, reject) {
                var req = store.get(SNAPSHOT_ID);
                req.onsuccess = function () { resolve(req.result || null); };
                req.onerror = function () { reject(req.error); };
            });
        });
    }

    function captureChrome() {
        if (!isActive()) {
            return Promise.resolve({ skipped: true, disabled: true });
        }
        if (!root.document || !root.document.documentElement) {
            return Promise.resolve({ skipped: true, reason: 'no_document' });
        }
        try {
            var html = '<!DOCTYPE html>\n' + root.document.documentElement.outerHTML;
            var safe = stripSensitive(html);
            var record = {
                id: SNAPSHOT_ID,
                kind: 'erp_shell_chrome',
                captured_at: new Date().toISOString(),
                path: (root.location && root.location.pathname) || '',
                html: safe
            };
            return putSnapshot(record).then(function () {
                return { ok: true, id: SNAPSHOT_ID, bytes: safe.length };
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
        SNAPSHOT_ID: SNAPSHOT_ID,
        isActive: isActive,
        captureChrome: captureChrome,
        getSnapshot: getSnapshot,
        startAutoCapture: startAutoCapture
    };
})(typeof window !== 'undefined' ? window : globalThis);
