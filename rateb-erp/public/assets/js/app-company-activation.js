/**
 * RATEB ERP app (Capacitor shell): remember the company panel chosen by activation code.
 * Web browsers are unaffected — everything below runs only inside the native app.
 */
(function () {
    'use strict';

    var KEY = 'rateb_app_company_admin';

    function inApp() {
        try {
            var cap = window.Capacitor;
            if (!cap) return false;
            return typeof cap.isNativePlatform === 'function' ? cap.isNativePlatform() : true;
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
        // Android browser: one tap opens the installed app already activated, or downloads it.
        if (/Android/i.test(navigator.userAgent || '')) {
            document.querySelectorAll('[data-rateb-app-intent]').forEach(function (el) {
                el.setAttribute('href', el.getAttribute('data-rateb-app-intent') || el.getAttribute('href'));
            });
        }
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

    // Opened from an activation link (?auto=1): save the company panel and go there directly.
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

    // Platform login page: go straight to the company panel saved on this device.
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
