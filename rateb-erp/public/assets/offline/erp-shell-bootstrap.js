/**
 * RATEB Offline — ERP shell bootstrap (Phase 10.1).
 * Registers enterprise SW only on non-POS pages when read_cache is enabled.
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
        return {
            'offline.enabled': true,
            'offline.read_cache': true,
            'offline.pos.complete': !!(cfg.flags && cfg.flags['offline.pos.complete']),
            'offline.inventory.movements': !!(cfg.flags && cfg.flags['offline.inventory.movements']),
            'offline.hr.attendance': !!(cfg.flags && cfg.flags['offline.hr.attendance']),
            'offline.procurement': !!(cfg.flags && cfg.flags['offline.procurement'])
        };
    }

    function registerServiceWorker() {
        if (!('serviceWorker' in root.navigator)) {
            return Promise.resolve(null);
        }
        // Never register from POS pages — pos-sw.js remains authoritative.
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
            // If an active worker is pos-sw.js, do not overwrite from ERP bootstrap.
            var posOwns = (regs || []).some(function (reg) {
                var active = reg.active || reg.waiting || reg.installing;
                var src = (active && active.scriptURL) ? String(active.scriptURL) : '';
                return /pos-sw\.js/i.test(src);
            });
            if (posOwns && isPosLocation()) {
                return null;
            }
            // When POS SW owns the shared scope, still allow ERP register from Admin pages;
            // POS pages re-register pos-sw.js on load (untouched). Avoid claim in ERP SW.
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
