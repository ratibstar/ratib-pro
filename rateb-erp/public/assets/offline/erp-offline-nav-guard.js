/**
 * RATEB ERP — Offline guard (lean).
 * Create/Edit/table links navigate normally offline (SW serves cached pages).
 * Only POST save and delete/export are blocked — those need the server.
 */
(function (root) {
    'use strict';

    var STYLE_ID = 'rateb-offline-nav-guard-css';
    var MUTE_NAV_RE = /\/(delete|destroy|export|pdf|excel|csv|json|regenerate)(\/|$|\?)/i;
    var GUARD_BUILD = '20260713-pass-create';

    function isOffline() {
        try {
            var badge = root.document.querySelector('[data-rateb-connection-status], #rateb-connection-indicator');
            if (badge) {
                if (badge.classList.contains('is-online')) {
                    return false;
                }
                if (badge.classList.contains('is-offline')) {
                    return true;
                }
            }
        } catch (e2) { /* ignore */ }
        try {
            var conn = root.RatebOfflineConnectivity;
            if (conn && typeof conn.isOnline === 'function') {
                return conn.isOnline() === false;
            }
        } catch (e1) { /* ignore */ }
        try {
            if (typeof navigator !== 'undefined' && navigator.onLine === false) {
                return true;
            }
        } catch (e0) { /* ignore */ }
        return false;
    }

    function ensureCss() {
        if (!root.document || root.document.getElementById(STYLE_ID)) {
            return;
        }
        var css = root.document.createElement('style');
        css.id = STYLE_ID;
        css.textContent = ''
            + '#rateb-offline-nav-toast{'
            + 'position:fixed;bottom:4.5rem;left:50%;transform:translateX(-50%);z-index:100000;'
            + 'background:#7f1d1d;color:#fff;padding:.65rem 1rem;border-radius:8px;'
            + 'font:13px/1.4 system-ui,sans-serif;max-width:90vw;text-align:center;}'
            /* Clear stale marks from older nav-guard builds still in Cache API. */
            + 'a.rateb-offline-missing{opacity:1!important;cursor:pointer!important;pointer-events:auto!important;}'
            + 'a.rateb-nav-link.rateb-offline-missing span::after,'
            + 'a.rateb-offline-rbac-link.rateb-offline-missing span::after{content:none!important;}';
        root.document.head.appendChild(css);
    }

    function toast(msg) {
        try {
            var el = root.document.getElementById('rateb-offline-nav-toast');
            if (!el) {
                el = root.document.createElement('div');
                el.id = 'rateb-offline-nav-toast';
                el.setAttribute('role', 'status');
                root.document.body.appendChild(el);
            }
            el.textContent = msg;
            el.hidden = false;
            clearTimeout(el.__hide);
            el.__hide = setTimeout(function () {
                try { el.hidden = true; } catch (e) { /* ignore */ }
            }, 3600);
        } catch (e2) { /* ignore */ }
    }

    function clearStaleMarks() {
        try {
            root.document.querySelectorAll('a.rateb-offline-missing, a.rateb-offline-cached').forEach(function (a) {
                a.classList.remove('rateb-offline-missing', 'rateb-offline-cached');
                a.removeAttribute('aria-disabled');
                var orig = a.getAttribute('data-rateb-title-orig');
                if (orig !== null) {
                    a.title = orig;
                }
            });
            var w = root.document.getElementById('rateb-offline-wrong-shell');
            if (w) {
                w.remove();
            }
        } catch (e) { /* ignore */ }
    }

    function isMuteHref(href) {
        try {
            var u = new URL(href, root.location.href);
            return MUTE_NAV_RE.test(u.pathname) || MUTE_NAV_RE.test(u.search);
        } catch (e) {
            return false;
        }
    }

    function block(ev, reason) {
        ev.preventDefault();
        ev.stopPropagation();
        toast(reason);
    }

    function onClick(ev) {
        if (!isOffline()) {
            return;
        }
        var target = ev.target;
        if (!target || !target.closest) {
            return;
        }

        var submitBtn = target.closest('button[type="submit"], input[type="submit"], [data-rateb-save], .btn-save');
        if (submitBtn) {
            var form = submitBtn.closest('form');
            if (form && String(form.getAttribute('method') || 'get').toLowerCase() === 'post') {
                block(ev, 'الحفظ يحتاج اتصال بالإنترنت. النموذج يمكنك تصفحه أوفلاين.');
                return;
            }
        }

        var a = target.closest('a[href]');
        if (!a) {
            return;
        }
        var href = a.getAttribute('href') || '';
        if (!href || href === '#' || /^javascript:/i.test(href)) {
            return;
        }
        if (isMuteHref(href)) {
            block(ev, 'الحذف والتصدير يحتاجان اتصال بالإنترنت.');
            return;
        }
        // Create / edit / all other GET links: behave like online — native navigation.
    }

    function onSubmit(ev) {
        if (!isOffline()) {
            return;
        }
        var form = ev.target;
        if (!form || !form.getAttribute) {
            return;
        }
        if (String(form.getAttribute('method') || 'get').toLowerCase() === 'post') {
            block(ev, 'الحفظ والإرسال يحتاجان اتصال بالإنترنت.');
        }
    }

    function boot() {
        if (!root.document) {
            return;
        }
        try {
            root.__RATEB_NAV_GUARD_BUILD__ = GUARD_BUILD;
        } catch (eB) { /* ignore */ }
        ensureCss();
        clearStaleMarks();
        root.document.addEventListener('click', onClick, true);
        root.document.addEventListener('submit', onSubmit, true);
        root.addEventListener('online', clearStaleMarks);
        root.addEventListener('offline', function () {
            ensureCss();
            clearStaleMarks();
        });
        root.document.addEventListener('rateb-connection-badge', function () {
            clearStaleMarks();
            ensureCss();
        });
        setInterval(clearStaleMarks, 5000);
    }

    root.RatebOfflineNavGuard = {
        scan: clearStaleMarks,
        isOffline: isOffline,
        build: GUARD_BUILD
    };
    boot();
})(typeof window !== 'undefined' ? window : globalThis);
