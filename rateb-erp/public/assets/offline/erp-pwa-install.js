/**
 * RATEB ERP PWA install prompt (does not touch POS PWA).
 */
(function (root) {
    'use strict';
    if (!root || !root.document) {
        return;
    }
    var deferred = null;
    var KEY = 'rateb_erp_pwa_install_dismissed';

    function dismissed() {
        try {
            return root.localStorage.getItem(KEY) === '1';
        } catch (e) {
            return false;
        }
    }

    function dismiss() {
        try {
            root.localStorage.setItem(KEY, '1');
        } catch (e) { /* ignore */ }
        var el = root.document.getElementById('rateb-erp-pwa-install');
        if (el && el.parentNode) {
            el.parentNode.removeChild(el);
        }
    }

    function showBanner() {
        if (dismissed() || !deferred || root.document.getElementById('rateb-erp-pwa-install')) {
            return;
        }
        var bar = root.document.createElement('div');
        bar.id = 'rateb-erp-pwa-install';
        bar.className = 'rateb-erp-pwa-install';
        bar.setAttribute('role', 'dialog');
        // Keep out of the fixed sidebar hit-zone (RTL sidebar is inline-start).
        // Inline styles are a fallback; .rateb-erp-pwa-install in CSS owns layout.
        bar.style.cssText = 'position:fixed;z-index:900;inset-inline-start:calc(268px + 1rem);'
            + 'inset-inline-end:1rem;bottom:max(1rem,env(safe-area-inset-bottom,0px));max-width:22rem;width:auto;'
            + 'padding:0.85rem 1rem;border-radius:0.75rem;background:#161b22;color:#e8eaed;'
            + 'box-shadow:0 8px 28px rgba(0,0,0,.35);font:14px/1.4 system-ui,sans-serif;';
        bar.innerHTML = '<div style="margin-bottom:.65rem">تثبيت RATEB ERP كتطبيق</div>'
            + '<div style="display:flex;gap:.5rem;flex-wrap:wrap">'
            + '<button type="button" id="rateb-erp-pwa-install-btn" style="border:0;border-radius:.5rem;padding:.45rem .9rem;background:#3b82f6;color:#fff;cursor:pointer">تثبيت</button>'
            + '<button type="button" id="rateb-erp-pwa-dismiss-btn" style="border:0;border-radius:.5rem;padding:.45rem .9rem;background:#2a3038;color:#e8eaed;cursor:pointer">لاحقاً</button>'
            + '</div>';
        root.document.body.appendChild(bar);
        root.document.getElementById('rateb-erp-pwa-install-btn').addEventListener('click', function () {
            var promptEvent = deferred;
            deferred = null;
            dismiss();
            if (promptEvent && typeof promptEvent.prompt === 'function') {
                promptEvent.prompt();
            }
        });
        root.document.getElementById('rateb-erp-pwa-dismiss-btn').addEventListener('click', dismiss);
    }

    root.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferred = e;
        showBanner();
    });

    root.RatebErpPwaInstall = {
        prompt: function () {
            if (deferred && typeof deferred.prompt === 'function') {
                return deferred.prompt();
            }
            return Promise.reject(new Error('install_unavailable'));
        },
        clearDismiss: function () {
            try {
                root.localStorage.removeItem(KEY);
            } catch (e) { /* ignore */ }
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);
