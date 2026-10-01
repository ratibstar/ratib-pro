/**
 * RATEB ERP app (Capacitor shell): remember the company panel chosen by activation code.
 * In a browser the activation tiles are plain links to /app-activate/{code}/go/{app}; nothing to do here.
 */
(function () {
    'use strict';

    var KEY = 'rateb_app_company_admin';

    function inApp() {
        try {
            var cap = window.Capacitor;
            if (!cap || typeof cap.isNativePlatform !== 'function') {
                return false;
            }
            return cap.isNativePlatform();
        } catch (e) {
            return false;
        }
    }

    function saved() {
        try {
            return localStorage.getItem(KEY) || '';
        } catch (e) {
            return '';
        }
    }

    if (!inApp()) {
        return;
    }

    document.querySelectorAll('[data-rateb-app-only]').forEach(function (el) {
        el.classList.remove('d-none');
    });
    document.querySelectorAll('[data-rateb-web-only]').forEach(function (el) {
        el.classList.add('d-none');
    });

    document.querySelectorAll('[data-rateb-app-open]').forEach(function (el) {
        el.addEventListener('click', function () {
            try {
                localStorage.setItem(KEY, el.getAttribute('data-rateb-app-open') || '');
            } catch (e) {}
        });
    });

    document.querySelectorAll('[data-rateb-app-clear]').forEach(function (el) {
        el.addEventListener('click', function () {
            try {
                localStorage.removeItem(KEY);
            } catch (e) {}
        });
    });

    var autoOpen = document.querySelector('[data-rateb-app-open]');
    if (autoOpen && /[?&]auto=1\b/.test(location.search)) {
        try {
            var dest = new URL(autoOpen.getAttribute('data-rateb-app-open') || '');
            if (dest.protocol === 'https:' && /(^|\.)rateb\.sa$/.test(dest.hostname)) {
                localStorage.setItem(KEY, dest.href);
                location.replace(dest.href);
                return;
            }
        } catch (e) {}
    }

    var target = saved();
    if (target && document.querySelector('[data-rateb-app-login]') && !/[?&]stay=1\b/.test(location.search)) {
        try {
            var url = new URL(target);
            if (url.protocol === 'https:' && url.host !== location.host && /(^|\.)rateb\.sa$/.test(url.hostname)) {
                location.replace(url.href);
            }
        } catch (e) {}
    }
})();
