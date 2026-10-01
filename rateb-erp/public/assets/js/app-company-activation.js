/**
 * RATEB ERP app (Capacitor shell): remember the company panel chosen by activation code.
 * Public activation page: native <a href> for intents/APK; JS only patches Android href when needed.
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

    function pickOpenUrl(wrap) {
        var android = /Android/i.test(navigator.userAgent || '');
        var intent = (wrap.getAttribute('data-rateb-open-android') || '').trim();
        var plain = (wrap.getAttribute('data-rateb-open') || '').trim();
        var dl = (wrap.getAttribute('data-rateb-download') || '').trim();
        if (android && intent) {
            return intent;
        }
        if (plain) {
            return plain;
        }
        if (intent) {
            return intent;
        }
        return dl;
    }

    function initActivationTiles() {
        document.querySelectorAll('.rateb-act-app').forEach(function (wrap) {
            if (wrap.getAttribute('data-rateb-apk-only') === '1') {
                return;
            }
            var main = wrap.querySelector('.rateb-act-app-main');
            if (!main) {
                return;
            }
            var url = pickOpenUrl(wrap);
            if (url && url !== '#') {
                main.setAttribute('href', url);
            }
        });
        var head = document.querySelector('[data-rateb-act-code]');
        var openBtn = document.getElementById('rateb-unified-open');
        if (head && openBtn && head.getAttribute('data-rateb-unified-hr') === '1') {
            var androidIntent = head.getAttribute('data-rateb-hr-android-intent');
            if (androidIntent && /Android/i.test(navigator.userAgent || '')) {
                openBtn.setAttribute('href', androidIntent);
            }
        }
    }

    var activationApps = document.querySelector('.rateb-act-apps');
    if (activationApps) {
        initActivationTiles();
    }

    if (!inApp()) {
        return;
    }

    if (!activationApps) {
        document.querySelectorAll('[data-rateb-web-only]').forEach(function (el) {
            el.classList.add('d-none');
        });
    }

    document.querySelectorAll('[data-rateb-app-only]').forEach(function (el) {
        el.classList.remove('d-none');
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
