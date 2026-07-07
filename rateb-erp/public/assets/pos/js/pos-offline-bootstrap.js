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
        navigator.serviceWorker.register(config.serviceWorker, scope ? { scope: scope } : undefined)
            .catch(function () { /* optional */ });
    }

    function ensureBanner() {
        if (document.getElementById('rateb-pos-offline-banner')) {
            return;
        }
        var banner = document.createElement('div');
        banner.id = 'rateb-pos-offline-banner';
        banner.className = 'rateb-pos-offline-banner';
        banner.setAttribute('role', 'status');
        banner.hidden = true;
        banner.textContent = t('pos_offline_mode_banner', 'Offline mode — sales are queued locally');
        document.body.appendChild(banner);
    }

    function syncOfflineUi() {
        var online = navigator.onLine;
        root.classList.toggle('rateb-pos--offline', !online);
        var banner = document.getElementById('rateb-pos-offline-banner');
        if (banner) {
            banner.hidden = online;
        }
    }

    registerServiceWorker();
    ensureBanner();
    syncOfflineUi();
    window.addEventListener('online', syncOfflineUi);
    window.addEventListener('offline', syncOfflineUi);
})();
