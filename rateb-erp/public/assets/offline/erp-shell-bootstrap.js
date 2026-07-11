/**
 * RATEB Offline — ERP shell bootstrap (Phase 13.1).
 * Passes full cfg.flags into SDK; never freezes later phase flags.
 * Does not overwrite pos-sw.js when it owns the shared scope.
 */
(function (root) {
    'use strict';

    var cfg = root.__RATEB_ERP_SHELL_OFFLINE__ || {};

    function isPosLocation() {
        try {
            var p = String((root.location && root.location.pathname) || '');
            return /\/pos(\/|$)/i.test(p) || /\/admin\/ops\/pos(\/|$)/i.test(p);
        } catch (e) {
            return true;
        }
    }

    function flagsFromConfig() {
        var f = Object.assign({}, cfg.flags || {});
        // Ensure shell prerequisites when this bootstrap runs (read_cache path).
        f['offline.enabled'] = true;
        f['offline.read_cache'] = true;
        return f;
    }

    function erpOfflineShellUrl() {
        try {
            var scope = cfg.serviceWorkerScope || '';
            if (!scope) {
                scope = (root.location && root.location.origin ? root.location.origin : '') + '/rateb-erp/public/';
            }
            if (scope.slice(-1) !== '/') {
                scope += '/';
            }
            return new URL('offline-shell.html', scope).href;
        } catch (e) {
            return '/rateb-erp/public/offline-shell.html';
        }
    }

    function warmErpShellViaPosSw(controller) {
        try {
            if (controller && typeof controller.postMessage === 'function') {
                controller.postMessage({ type: 'WARM_ERP_OFFLINE_SHELL' });
            }
        } catch (e) { /* ignore */ }
        if (!('caches' in root) || !root.fetch) {
            return Promise.resolve(null);
        }
        var key = erpOfflineShellUrl();
        return root.fetch(key, {
            credentials: 'same-origin',
            cache: 'no-cache',
            headers: { Accept: 'text/html', 'X-Rateb-Shell-Warm': '1' }
        }).then(function (res) {
            if (!res || !res.ok) {
                return null;
            }
            return root.caches.open('rateb-erp-coexist-v1').then(function (cache) {
                return cache.put(key, res.clone());
            });
        }).catch(function () { return null; });
    }

    function registerServiceWorker() {
        if (!('serviceWorker' in root.navigator)) {
            return Promise.resolve(null);
        }
        if (isPosLocation()) {
            return Promise.resolve(null);
        }
        var swUrl = cfg.serviceWorker || '';
        if (!swUrl) {
            return Promise.resolve(null);
        }
        var scope = cfg.serviceWorkerScope || undefined;
        if (scope === '/' || (root.location && scope === root.location.origin + '/')) {
            try {
                scope = new URL('.', swUrl).pathname;
            } catch (e) {
                scope = undefined;
            }
        }
        return root.navigator.serviceWorker.getRegistrations().then(function (regs) {
            // Never displace pos-sw.js on the shared scope (smart coexist).
            var posReg = null;
            (regs || []).forEach(function (reg) {
                var active = reg.active || reg.waiting || reg.installing;
                var src = (active && active.scriptURL) ? String(active.scriptURL) : '';
                if (/pos-sw\.js/i.test(src)) {
                    posReg = reg;
                }
            });
            if (posReg) {
                var ctrl = (posReg.active)
                    || (root.navigator.serviceWorker && root.navigator.serviceWorker.controller)
                    || null;
                return warmErpShellViaPosSw(ctrl).then(function () { return null; });
            }
            return root.navigator.serviceWorker.register(swUrl, scope ? { scope: scope } : undefined);
        }).catch(function () { return null; });
    }

    function boot() {
        var flags = flagsFromConfig();
        if (!flags['offline.enabled'] || !flags['offline.read_cache']) {
            return;
        }
        if (isPosLocation()) {
            return;
        }
        if (!(parseInt(cfg.company_id, 10) > 0 && parseInt(cfg.user_id, 10) > 0)) {
            return;
        }
        if (root.RatebOffline && typeof root.RatebOffline.init === 'function') {
            root.RatebOffline.init({
                apiBase: cfg.apiBase || '',
                probeUrl: cfg.probeUrl || null,
                flags: flags,
                startConnectivity: cfg.startConnectivity !== false,
                startScheduler: false
            });
        }
        registerServiceWorker().then(function () {
            if (root.RatebOfflineShellAdapter && typeof root.RatebOfflineShellAdapter.startAutoCapture === 'function') {
                root.RatebOfflineShellAdapter.startAutoCapture();
            }
        });
    }

    if (root.document && root.document.readyState === 'loading') {
        root.document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})(typeof window !== 'undefined' ? window : globalThis);
