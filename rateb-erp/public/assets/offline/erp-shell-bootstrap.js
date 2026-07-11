/**
 * RATEB Offline — ERP shell bootstrap (Phase 10).
 * Registers enterprise SW + RatebOffline + shell adapter.
 * Must only be loaded when offline.enabled AND offline.read_cache are true (server-gated).
 */
(function (root) {
    'use strict';

    var cfg = root.__RATEB_ERP_SHELL_OFFLINE__ || {};

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
        var swUrl = cfg.serviceWorker || '';
        if (!swUrl) {
            return Promise.resolve(null);
        }
        var scope = cfg.serviceWorkerScope || undefined;
        // Never claim site-root scope.
        if (scope === '/' || (root.location && scope === root.location.origin + '/')) {
            try {
                scope = new URL('.', swUrl).pathname;
            } catch (e) {
                scope = undefined;
            }
        }
        return root.navigator.serviceWorker.register(swUrl, scope ? { scope: scope } : undefined)
            .catch(function () { return null; });
    }

    function boot() {
        var flags = flagsFromConfig();
        if (!flags['offline.enabled'] || !flags['offline.read_cache']) {
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
