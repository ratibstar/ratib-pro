/**
 * RATEB ERP — Offline guard (lean).
 * POST save/delete/export blocked. Edit deep-links offline return to list
 * (never uncontrolled navigation → Chrome «لا يتوفر اتصال»).
 */
(function (root) {
    'use strict';

    var STYLE_ID = 'rateb-offline-nav-guard-css';
    var MUTE_NAV_RE = /\/(delete|destroy|export|pdf|excel|csv|json|regenerate)(\/|$|\?)/i;
    var EDIT_NAV_RE = /\/\d+\/(edit|show|view)(\/|$)/i;
    var GUARD_BUILD = '20260714-edit-back-v38';

    function isOffline() {
        // Browser offline flag wins — soft "متصل" must not allow dead edit navigations.
        try {
            if (typeof navigator !== 'undefined' && navigator.onLine === false) {
                return true;
            }
        } catch (e0) { /* ignore */ }
        try {
            var badge = root.document.querySelector('[data-rateb-connection-status], #rateb-connection-indicator');
            if (badge) {
                if (badge.classList.contains('is-offline')) {
                    return true;
                }
                if (badge.classList.contains('is-online')) {
                    return false;
                }
            }
        } catch (e2) { /* ignore */ }
        try {
            var conn = root.RatebOfflineConnectivity;
            if (conn && typeof conn.isOnline === 'function') {
                return conn.isOnline() === false;
            }
        } catch (e1) { /* ignore */ }
        return false;
    }

    function hasSwController() {
        try {
            return !!(navigator.serviceWorker && navigator.serviceWorker.controller);
        } catch (e) {
            return false;
        }
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

    function isEditHref(href) {
        try {
            return EDIT_NAV_RE.test(new URL(href, root.location.href).pathname);
        } catch (e) {
            return EDIT_NAV_RE.test(String(href || ''));
        }
    }

    function parentListHref(href) {
        try {
            var u = new URL(href, root.location.href);
            var next = String(u.pathname || '')
                .replace(/\/\d+\/(edit|show|view|generate)(\/|$)/i, '/')
                .replace(/\/+$/, '');
            if (!next || !/\/admin(\/|$)/i.test(next)) {
                return u.origin + (u.pathname.match(/^(.*\/public\/)/i) || [])[1] + 'admin/companies';
            }
            u.pathname = next;
            return u.href;
        } catch (e2) {
            return root.location.origin + '/rateb-erp/public/admin/companies';
        }
    }

    function goParentOrBack(href) {
        var list = parentListHref(href);
        var keys = [list];
        try {
            var u = new URL(list, root.location.href);
            keys.push(u.origin + u.pathname);
            keys.push(u.origin + u.pathname.replace(/\/+$/, ''));
        } catch (eK) { /* ignore */ }

        function useHistory() {
            try {
                if (root.history.length > 1) {
                    root.history.back();
                    return true;
                }
            } catch (eH) { /* ignore */ }
            return false;
        }

        if (!root.caches || typeof root.caches.match !== 'function') {
            if (!useHistory()) {
                root.location.href = list;
            }
            return;
        }

        var chain = Promise.resolve(null);
        keys.forEach(function (k) {
            chain = chain.then(function (hit) {
                if (hit) {
                    return hit;
                }
                return root.caches.match(k).then(function (exact) {
                    if (exact) {
                        return exact;
                    }
                    return root.caches.match(k, { ignoreSearch: true }).catch(function () { return null; });
                }).catch(function () { return null; });
            });
        });
        chain.then(function (res) {
            if (res && res.ok) {
                return res.text().then(function (html) {
                    if (html && html.length > 400) {
                        root.document.open();
                        root.document.write(html);
                        root.document.close();
                        toast('أوفلاين: رجوع لقائمة السجلات (نموذج التعديل غير محفوظ).');
                        return;
                    }
                    if (!useHistory()) {
                        root.location.replace(list);
                    }
                });
            }
            if (!useHistory()) {
                root.location.replace(list);
            }
        }).catch(function () {
            if (!useHistory()) {
                root.location.replace(list);
            }
        });
    }

    function block(ev, reason) {
        ev.preventDefault();
        ev.stopPropagation();
        toast(reason);
    }

    function onClick(ev) {
        var target = ev.target;
        if (!target || !target.closest) {
            return;
        }

        var a = target.closest('a[href]');
        var href = a ? (a.getAttribute('href') || '') : '';
        var offline = isOffline();
        var noSw = !hasSwController();

        // Edit without SW while offline → Chrome interstitial. Always handle in-page.
        if (a && href && isEditHref(href) && (offline || (noSw && offline))) {
            ev.preventDefault();
            ev.stopPropagation();
            ev.stopImmediatePropagation();
            goParentOrBack(href);
            return;
        }
        // No SW + offline: any admin deep link risks Chrome interstitial.
        if (a && href && offline && noSw && /\/admin(\/|$)/i.test(href)) {
            if (isEditHref(href) || /\/\d+(\/|$)/i.test(href)) {
                ev.preventDefault();
                ev.stopPropagation();
                ev.stopImmediatePropagation();
                goParentOrBack(href);
                return;
            }
        }

        if (!offline) {
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

        if (!a) {
            return;
        }
        if (!href || href === '#' || /^javascript:/i.test(href)) {
            return;
        }
        if (isMuteHref(href)) {
            block(ev, 'الحذف والتصدير يحتاجان اتصال بالإنترنت.');
        }
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
