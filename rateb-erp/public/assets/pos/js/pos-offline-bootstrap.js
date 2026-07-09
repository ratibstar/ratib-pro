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

    var i18n = config.i18n || {};

    function t(key, fb) {
        return i18n[key] || fb || key;
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

    function syncOfflineUi() {
        var online = navigator.onLine;
        root.classList.toggle('rateb-pos--offline', !online);
    }

    registerServiceWorker();
    syncOfflineUi();
    window.addEventListener('online', syncOfflineUi);
    window.addEventListener('offline', syncOfflineUi);
})();
