/**
 * RATEB Offline — ERP shell bootstrap (Phase 14).
 * Passes full cfg.flags into SDK; never freezes later phase flags.
 * Does not overwrite pos-sw.js when it owns the shared scope.
 * Sync badge + clientQueueMax for daily ops pilot.
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

    function persistOfflineScope(flags) {
        try {
            if (!(parseInt(cfg.company_id, 10) > 0 && parseInt(cfg.user_id, 10) > 0)) {
                return;
            }
            root.localStorage.setItem('rateb_erp_offline_scope', JSON.stringify({
                company_id: parseInt(cfg.company_id, 10) || 0,
                branch_id: parseInt(cfg.branch_id, 10) || 0,
                user_id: parseInt(cfg.user_id, 10) || 0,
                auth_unlock: !!(flags && flags['offline.auth.unlock']),
                flags: {
                    'offline.enabled': true,
                    'offline.read_cache': true,
                    'offline.auth.unlock': !!(flags && flags['offline.auth.unlock']),
                    'offline.rbac.cache': !!(flags && flags['offline.rbac.cache'])
                },
                saved_at: new Date().toISOString()
            }));
        } catch (e) { /* ignore */ }
    }

    function warmErpShellUrls() {
        var base;
        try {
            var scope = cfg.serviceWorkerScope || '';
            if (!scope) {
                scope = (root.location && root.location.origin ? root.location.origin : '') + '/rateb-erp/public/';
            }
            if (scope.slice(-1) !== '/') {
                scope += '/';
            }
            base = scope;
        } catch (e) {
            base = '/rateb-erp/public/';
        }
        var urls = [
            base + 'offline-shell.html',
            base + 'assets/offline/rateb-offline.js',
            base + 'assets/offline/erp-offline-shell-auth.js',
            base + 'assets/offline/erp-offline-shell-rbac.js'
        ];
        if (!('caches' in root) || !root.fetch) {
            return Promise.resolve(null);
        }
        return root.caches.open('rateb-erp-coexist-v1').then(function (cache) {
            return Promise.all(urls.map(function (u) {
                return root.fetch(u, {
                    credentials: 'same-origin',
                    cache: 'no-cache',
                    headers: { Accept: '*/*', 'X-Rateb-Shell-Warm': '1' }
                }).then(function (res) {
                    if (res && res.ok) {
                        return cache.put(u, res.clone());
                    }
                    return null;
                }).catch(function () { return null; });
            }));
        }).catch(function () { return null; });
    }

    function warmErpShellViaPosSw(controller) {
        try {
            if (controller && typeof controller.postMessage === 'function') {
                controller.postMessage({ type: 'WARM_ERP_OFFLINE_SHELL' });
            }
        } catch (e) { /* ignore */ }
        return warmErpShellUrls();
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

    function ensureSyncBadge() {
        if (!root.document) {
            return null;
        }
        var existing = root.document.getElementById('rateb-offline-sync-badge');
        if (existing) {
            return existing;
        }
        var indicator = root.document.getElementById('rateb-connection-indicator');
        var parent = indicator && indicator.parentNode ? indicator.parentNode : null;
        if (!parent) {
            return null;
        }
        var badge = root.document.createElement('span');
        badge.id = 'rateb-offline-sync-badge';
        badge.className = 'rateb-offline-sync-badge ms-2 small text-muted';
        badge.setAttribute('role', 'status');
        badge.setAttribute('aria-live', 'polite');
        badge.hidden = true;
        if (indicator.nextSibling) {
            parent.insertBefore(badge, indicator.nextSibling);
        } else {
            parent.appendChild(badge);
        }
        return badge;
    }

    function refreshSyncBadge() {
        var badge = ensureSyncBadge();
        if (!badge) {
            return;
        }
        var queue = root.RatebOfflineQueue;
        if (!queue || typeof queue.depth !== 'function') {
            badge.hidden = true;
            return;
        }
        queue.depth().then(function (d) {
            var n = parseInt(d, 10) || 0;
            if (n < 1) {
                badge.hidden = true;
                badge.textContent = '';
                return;
            }
            var max = typeof queue.clientQueueMax === 'function' ? queue.clientQueueMax() : 500;
            badge.hidden = false;
            badge.textContent = 'مزامنة: ' + n + (max ? '/' + max : '');
            badge.title = 'Offline sync queue depth';
        }).catch(function () {
            badge.hidden = true;
        });
    }

    function bindSyncBadge() {
        ensureSyncBadge();
        refreshSyncBadge();
        var events = root.RatebOfflineEvents;
        if (events && typeof events.on === 'function') {
            ['queue:enqueued', 'queue:flushed', 'queue:full', 'sdk:ready', 'sdk:flags'].forEach(function (ev) {
                events.on(ev, function () { refreshSyncBadge(); });
            });
        }
        if (root.RatebOfflineConnectivity && typeof root.RatebOfflineConnectivity.subscribe === 'function') {
            root.RatebOfflineConnectivity.subscribe(function () { refreshSyncBadge(); });
        }
        root.addEventListener('online', refreshSyncBadge);
        root.addEventListener('offline', refreshSyncBadge);
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
        persistOfflineScope(flags);
        if (root.RatebOffline && typeof root.RatebOffline.init === 'function') {
            var max = parseInt(cfg.client_queue_max, 10);
            root.RatebOffline.init({
                apiBase: cfg.apiBase || '',
                probeUrl: cfg.probeUrl || null,
                flags: flags,
                clientQueueMax: !isNaN(max) && max >= 0 ? max : 500,
                startConnectivity: cfg.startConnectivity !== false,
                startScheduler: false
            });
        }
        bindSyncBadge();
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
