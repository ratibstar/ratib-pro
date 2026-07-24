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

    function buildLocaleUrl(anchor) {
        var locale = anchor.getAttribute('data-locale') || 'ar';
        var base = anchor.getAttribute('data-locale-base') || anchor.getAttribute('href') || '';
        var url;
        try {
            url = new URL(base, window.location.href);
        } catch (eUrl) {
            return base;
        }
        // Strip prior next/company_id then set from current page (soft-nav safe).
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
        ev.stopPropagation();
        var href = buildLocaleUrl(link);
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
