(function () {
    'use strict';

    var root = document.querySelector('[data-pos-register]');
    if (!root) {
        return;
    }

    var configEl = document.getElementById('rateb-pos-register-config');
    var config = {};
    try {
        config = JSON.parse((configEl && configEl.textContent) || '{}');
    } catch (e) {
        config = {};
    }

    var SHELL_CACHE = 'rateb-pos-shell-v6';
    var REGISTER_SHELL_PATH = '__rateb_pos_register_shell__';

    function registerServiceWorker() {
        if (!('serviceWorker' in navigator) || !config.serviceWorker) {
            return Promise.resolve(null);
        }
        var scope = config.serviceWorkerScope || undefined;
        // Never register with site-root scope when SW lives under /rateb-erp/public/.
        if (scope === '/' || scope === window.location.origin + '/') {
            try {
                scope = new URL('.', config.serviceWorker).pathname;
            } catch (e) {
                scope = '/rateb-erp/public/';
            }
        }
        return navigator.serviceWorker.register(config.serviceWorker, scope ? { scope: scope } : undefined)
            .catch(function () { return null; });
    }

    function syncOfflineUi(online) {
        var isOnline = online;
        if (typeof isOnline !== 'boolean') {
            isOnline = window.RatebPosConnectivity
                ? window.RatebPosConnectivity.isOnline()
                : navigator.onLine;
        }
        root.classList.toggle('rateb-pos--offline', !isOnline);
    }

    function bindOfflineNavGuard() {
        var blocked = /\/pos\/(reports|settings|dashboard|shifts|terminals)(\/|$|\?)/i;
        document.addEventListener('click', function (e) {
            var link = e.target.closest('a[href]');
            if (!link || !root.contains(link)) {
                return;
            }
            var href = link.getAttribute('href') || '';
            if (!blocked.test(href)) {
                return;
            }
            var offline = window.RatebPosNet
                ? !window.RatebPosNet.isOnline()
                : (window.RatebPosConnectivity ? !window.RatebPosConnectivity.isOnline() : !navigator.onLine);
            if (!offline) {
                return;
            }
            e.preventDefault();
            if (window.RatebPosNotify) {
                window.RatebPosNotify(
                    (config.i18n && config.i18n.pos_offline_nav_blocked) || 'This page needs a connection. Stay on the register while offline.',
                    true
                );
            }
        }, true);
    }

    function registerShellKey() {
        try {
            return new URL(REGISTER_SHELL_PATH, window.location.origin + '/rateb-erp/public/').href;
        } catch (e) {
            return window.location.origin + '/rateb-erp/public/' + REGISTER_SHELL_PATH;
        }
    }

    /** Pin current register HTML into Cache Storage (works even if SW put failed). */
    function pinRegisterShell() {
        if (!('caches' in window) || navigator.onLine === false) {
            return Promise.resolve(false);
        }
        var u = new URL(window.location.href);
        if (!/\/pos(\/register)?$/i.test(u.pathname.replace(/\/+$/, ''))) {
            return Promise.resolve(false);
        }
        return fetch(u.href, {
            credentials: 'same-origin',
            headers: { Accept: 'text/html', 'X-Rateb-Shell-Warm': '1' },
            cache: 'no-store'
        }).then(function (res) {
            if (!res || !res.ok) {
                return false;
            }
            return caches.open(SHELL_CACHE).then(function (cache) {
                var bare = u.origin + u.pathname;
                var alt = /\/register$/i.test(u.pathname)
                    ? u.origin + u.pathname.replace(/\/register$/i, '')
                    : u.origin + u.pathname.replace(/\/?$/, '') + '/register';
                return Promise.all([
                    cache.put(u.href, res.clone()),
                    cache.put(bare, res.clone()),
                    cache.put(bare + u.search, res.clone()),
                    cache.put(alt, res.clone()),
                    cache.put(alt + u.search, res.clone()),
                    cache.put(registerShellKey(), res.clone())
                ]).then(function () {
                    return true;
                });
            });
        }).then(function (ok) {
            if (navigator.serviceWorker && navigator.serviceWorker.controller) {
                try {
                    navigator.serviceWorker.controller.postMessage({
                        type: 'PIN_REGISTER_SHELL',
                        url: u.href
                    });
                } catch (e) { /* ignore */ }
            }
            return ok;
        }).catch(function () {
            return false;
        });
    }

    registerServiceWorker().then(function () {
        bindOfflineNavGuard();
        setTimeout(pinRegisterShell, 800);
        setTimeout(pinRegisterShell, 3500);
    });

    if (window.RatebPosConnectivity && window.RatebPosConnectivity.subscribe) {
        window.RatebPosConnectivity.subscribe(function (online) {
            syncOfflineUi(online);
            if (online) {
                pinRegisterShell();
            }
        });
    } else {
        syncOfflineUi(navigator.onLine);
        window.addEventListener('online', function () {
            syncOfflineUi(true);
            pinRegisterShell();
        });
        window.addEventListener('offline', function () { syncOfflineUi(false); });
    }
})();
