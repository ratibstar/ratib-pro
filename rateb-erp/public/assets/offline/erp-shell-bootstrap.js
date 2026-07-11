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
            // Never displace pos-sw.js on the shared scope (Phase 13.1 coexistence).
            var posOwns = (regs || []).some(function (reg) {
                var active = reg.active || reg.waiting || reg.installing;
                var src = (active && active.scriptURL) ? String(active.scriptURL) : '';
                return /pos-sw\.js/i.test(src);
            });
            if (posOwns) {
                return null;
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
