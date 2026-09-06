/**
 * RATEB ERP PWA install — topbar button (does not touch POS PWA).
 */
(function (root) {
    'use strict';
    if (!root || !root.document) {
        return;
    }
    var deferred = null;

    function hostButton() {
        return root.document.getElementById('rateb-erp-pwa-install');
    }

    function setVisible(on) {
        var el = hostButton();
        if (!el) {
            return;
        }
        el.classList.toggle('d-none', !on);
        if (on) {
            el.removeAttribute('hidden');
        } else {
            el.setAttribute('hidden', 'hidden');
        }
    }

    function bindClick() {
        var el = hostButton();
        if (!el || el.getAttribute('data-rateb-pwa-bound') === '1') {
            return;
        }
        el.setAttribute('data-rateb-pwa-bound', '1');
        el.addEventListener('click', function () {
            var promptEvent = deferred;
            if (promptEvent && typeof promptEvent.prompt === 'function') {
                promptEvent.prompt();
                return;
            }
            if (root.RatebErpPwaInstall && typeof root.RatebErpPwaInstall.prompt === 'function') {
                root.RatebErpPwaInstall.prompt();
            }
        });
    }

    function showInstall() {
        bindClick();
        if (!deferred) {
            return;
        }
        setVisible(true);
    }

    function hideInstall() {
        deferred = null;
        try {
            root.__RATEB_PWA_DEFERRED_PROMPT__ = null;
        } catch (e) { /* ignore */ }
        setVisible(false);
    }

    root.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferred = e;
        try {
            root.__RATEB_PWA_DEFERRED_PROMPT__ = e;
        } catch (eStore) { /* ignore */ }
        showInstall();
    });

    root.addEventListener('appinstalled', hideInstall);

    root.RatebErpPwaInstall = {
        prompt: function () {
            if (deferred && typeof deferred.prompt === 'function') {
                return deferred.prompt();
            }
            return Promise.reject(new Error('install_unavailable'));
        },
        clearDismiss: function () {
            try {
                root.localStorage.removeItem('rateb_erp_pwa_install_dismissed');
            } catch (e) { /* ignore */ }
        }
    };

    bindClick();
    try {
        if (root.__RATEB_PWA_DEFERRED_PROMPT__) {
            deferred = root.__RATEB_PWA_DEFERRED_PROMPT__;
            showInstall();
        }
    } catch (eBoot) { /* ignore */ }
    try {
        root.addEventListener('rateb:pwa-deferred-prompt', function (ev) {
            try {
                deferred = (ev && ev.detail) || root.__RATEB_PWA_DEFERRED_PROMPT__ || deferred;
                if (deferred) {
                    showInstall();
                }
            } catch (eEv) { /* ignore */ }
        });
    } catch (eListen) { /* ignore */ }
})(typeof window !== 'undefined' ? window : globalThis);
