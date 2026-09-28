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
        // Android browser: intent links on tiles; QR (HTTPS ?setup=1) opens HR and activates immediately.
        if (/Android/i.test(navigator.userAgent || '')) {
            document.querySelectorAll('[data-rateb-app-intent]').forEach(function (el) {
                el.setAttribute('href', el.getAttribute('data-rateb-app-intent') || el.getAttribute('href'));
            });
            var head = document.querySelector('[data-rateb-hr-open][data-rateb-act-code]');
            if (head) {
                var actCode = head.getAttribute('data-rateb-act-code') || '';
                var httpsOpen = head.getAttribute('data-rateb-hr-open') || '';
                var androidIntent = head.getAttribute('data-rateb-hr-android-intent') || '';
                var unifiedHr = head.getAttribute('data-rateb-unified-hr') === '1';
                if (actCode && (httpsOpen || androidIntent)) {
                    var autoSetup = /[?&]setup=1(?:&|$)/.test(location.search);
                    var sk = 'rateb_hr_act_open_' + actCode;
                    if (autoSetup && sessionStorage.getItem(sk) !== '1') {
                        sessionStorage.setItem(sk, '1');
                        if (unifiedHr && androidIntent.indexOf('intent://') === 0) {
                            window.location.replace(androidIntent);
                        } else if (httpsOpen) {
                            window.location.replace(httpsOpen);
                        }
                    } else if (!unifiedHr && (autoSetup || sessionStorage.getItem(sk) !== '1')) {
                        sessionStorage.setItem(sk, '1');
                        window.location.replace(httpsOpen || androidIntent);
                    }
                }
            }
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
