(function () {
    'use strict';

    if (window.__RATEB_LANG_BOUND__) {
        return;
    }
    window.__RATEB_LANG_BOUND__ = true;

    function erpNextPath() {
        try {
            var path = String(window.location.pathname || '');
            var next = '';
            var m = path.match(/\/public\/(.+)$/i);
            if (m && m[1]) {
                next = m[1];
            } else {
                m = path.match(/\/(admin(?:\/.*)?)$/i);
                next = m && m[1] ? m[1] : '';
            }
            next = String(next || '').replace(/^\/+|\/+$/g, '');
            if (!next || /^locale\//i.test(next)) {
                next = 'admin';
            }
            return next;
        } catch (e) {
            return 'admin';
        }
    }

    /** Prefer /rateb-erp/public/locale/* so the ERP session cookie is included. */
    function ensureAppPrefixLocaleBase(base, locale) {
        try {
            var u = new URL(base, window.location.href);
            var loc = locale || 'ar';
            // Domain-root /locale/x on rateb.sa does not receive path=/rateb-erp/public session.
            if (/\/locale\/(en|ar)\/?$/i.test(u.pathname) && u.pathname.indexOf('/rateb-erp/public/') === -1) {
                return u.origin + '/rateb-erp/public/locale/' + loc;
            }
            return u.href.split('?')[0];
        } catch (e) {
            return base;
        }
    }

    function buildLocaleUrl(anchor) {
        var locale = anchor.getAttribute('data-locale') || 'ar';
        var base = anchor.getAttribute('data-locale-base') || anchor.getAttribute('href') || '';
        base = ensureAppPrefixLocaleBase(base, locale);
        var url;
        try {
            url = new URL(base, window.location.href);
        } catch (eUrl) {
            return base;
        }
        url.searchParams.delete('next');
        url.searchParams.delete('company_id');
        url.searchParams.set('next', erpNextPath());
        try {
            var cid = new URLSearchParams(window.location.search).get('company_id');
            if (cid && /^\d+$/.test(cid)) {
                url.searchParams.set('company_id', cid);
            }
        } catch (eCid) { /* ignore */ }
        return url.toString();
    }

    function purgeHtmlCaches() {
        try {
            if (navigator.serviceWorker && navigator.serviceWorker.controller) {
                navigator.serviceWorker.controller.postMessage({ type: 'PURGE_ERP_AUTH_CACHE' });
                navigator.serviceWorker.controller.postMessage({
                    type: 'RATEB_HTML_CACHE_BUST',
                    reason: 'locale-switch',
                    at: Date.now()
                });
            }
        } catch (eSw) { /* ignore */ }
    }

    function refreshLocaleHrefs() {
        document.querySelectorAll('a[data-locale]').forEach(function (a) {
            try {
                a.setAttribute('href', buildLocaleUrl(a));
                a.setAttribute('data-rateb-full-nav', '1');
            } catch (eA) { /* ignore */ }
        });
    }

    document.addEventListener('click', function (ev) {
        var link = ev.target && ev.target.closest
            ? ev.target.closest('a[data-locale]')
            : null;
        if (!link) {
            return;
        }
        ev.preventDefault();
        try { ev.stopImmediatePropagation(); } catch (eSip) { ev.stopPropagation(); }
        var href = buildLocaleUrl(link);
        purgeHtmlCaches();
        try {
            window.location.assign(href);
        } catch (eGo) {
            window.location.href = href;
        }
    }, true);

    function boot() {
        refreshLocaleHrefs();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
    document.addEventListener('rateb:nav:afterEnter', refreshLocaleHrefs);
})();
