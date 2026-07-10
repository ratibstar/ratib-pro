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

    function registerServiceWorker() {
        if (!('serviceWorker' in navigator) || !config.serviceWorker) {
            return;
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
        navigator.serviceWorker.register(config.serviceWorker, scope ? { scope: scope } : undefined)
            .catch(function () { /* optional offline */ });
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

    /** Warm the SW shell cache for /pos and /pos/register while online. */
    function warmRegisterShell() {
        if (!('serviceWorker' in navigator) || !navigator.serviceWorker.controller) {
            return;
        }
        if (navigator.onLine === false) {
            return;
        }
        try {
            var u = new URL(window.location.href);
            if (!/\/(admin\/ops\/)?pos/i.test(u.pathname)) {
                return;
            }
            // Touch navigation URL so SW network-first path can cache it.
            fetch(u.pathname + u.search, {
                credentials: 'same-origin',
                headers: { Accept: 'text/html', 'X-Rateb-Shell-Warm': '1' },
                cache: 'no-store'
            }).catch(function () { /* optional */ });
        } catch (e) { /* ignore */ }
    }

    registerServiceWorker();
    bindOfflineNavGuard();
    setTimeout(warmRegisterShell, 1500);
    if (window.RatebPosConnectivity && window.RatebPosConnectivity.subscribe) {
        window.RatebPosConnectivity.subscribe(syncOfflineUi);
    } else {
        syncOfflineUi(navigator.onLine);
        window.addEventListener('online', function () { syncOfflineUi(true); });
        window.addEventListener('offline', function () { syncOfflineUi(false); });
    }
})();
