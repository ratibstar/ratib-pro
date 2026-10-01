/**
 * RATEB ERP app (Capacitor shell): remember the company panel chosen by activation code.
 * Public activation page: open installed apps (ratebhr / ratebapp) or download APKs.
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

    function go(url) {
        if (!url || url === '#') return;
        try {
            window.location.href = url;
        } catch (e) {}
    }

    function pickOpenUrl(el) {
        var android = /Android/i.test(navigator.userAgent || '');
        var intent = (el.getAttribute('data-rateb-open-android') || '').trim();
        var plain = (el.getAttribute('data-rateb-open') || '').trim();
        if (android && intent) {
            return intent;
        }
        return plain || intent;
    }

    function bindActivationAppCards() {
        document.querySelectorAll('.rateb-act-app').forEach(function (el) {
            if (el.getAttribute('data-rateb-apk-only') === '1') {
                return;
            }
            var handler = function (e) {
                var dl = (el.getAttribute('data-rateb-download') || '').trim();
                if (e.target && e.target.closest && e.target.closest('.rateb-act-app-dl')) {
                    e.preventDefault();
                    if (dl) {
                        go(dl);
                    } else if (el.getAttribute('data-rateb-apk-pending') === '1') {
                        var pending = document.getElementById('rateb-hr-apk-pending');
                        if (pending && typeof pending.scrollIntoView === 'function') {
                            pending.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }
                    }
                    return;
                }
                var openUrl = pickOpenUrl(el);
                if (openUrl) {
                    e.preventDefault();
                    go(openUrl);
                    return;
                }
                if (dl) {
                    e.preventDefault();
                    go(dl);
                    return;
                }
                if (el.getAttribute('data-rateb-apk-pending') === '1') {
                    e.preventDefault();
                    var plain = (el.getAttribute('data-rateb-open') || '').trim();
                    if (plain) {
                        go(plain);
                    }
                }
            };
            el.addEventListener('click', handler);
            el.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    handler(e);
                }
            });
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

    if (!inApp()) {
        bindActivationAppCards();
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
