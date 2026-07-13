/**
 * RATEB ERP — Offline nav + action guard.
 * - Sidebar / in-page / table links: navigate when cached (including create/edit forms).
 * - POST save / delete: blocked offline (needs server).
 */
(function (root) {
    'use strict';

    var CACHE_NAMES = [
        'rateb-erp-ops-pages-v30',
        'rateb-erp-ops-pages-v29',
        'rateb-erp-ops-pages-v28',
        'rateb-erp-coexist-v25',
        'rateb-erp-coexist-v24',
        'rateb-erp-coexist-v23',
        'rateb-pos-shell-v8'
    ];
    var STYLE_ID = 'rateb-offline-nav-guard-css';
    var scanning = false;
    var MUTE_NAV_RE = /\/(delete|destroy|export|pdf|excel|csv|json|regenerate)(\/|$|\?)/i;
    var WRITE_TEXT_RE = /(حفظ|حذف|Save|Delete)\b/i;

    function isOffline() {
        // Prefer connection badge — do not gray-out nav while UI says «متصل».
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
            + 'a.rateb-nav-link.rateb-offline-missing,'
            + 'a.rateb-offline-rbac-link.rateb-offline-missing,'
            + 'a.rateb-offline-missing{'
            + 'opacity:.38;pointer-events:auto;cursor:not-allowed;}'
            + 'a.rateb-nav-link.rateb-offline-missing span::after,'
            + 'a.rateb-offline-rbac-link.rateb-offline-missing span::after{'
            + 'content:" · غير محفوظ";font-size:.72em;opacity:.8;}'
            + '#rateb-offline-nav-toast{'
            + 'position:fixed;bottom:4.5rem;left:50%;transform:translateX(-50%);z-index:100000;'
            + 'background:#7f1d1d;color:#fff;padding:.65rem 1rem;border-radius:8px;'
            + 'font:13px/1.4 system-ui,sans-serif;max-width:90vw;text-align:center;}'
            + '#rateb-offline-wrong-shell{'
            + 'position:sticky;top:0;z-index:99980;background:#7f1d1d;color:#fff;'
            + 'padding:.55rem 1rem;text-align:center;font:13px/1.4 system-ui,sans-serif;}';
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

    function publicAdminBase() {
        try {
            var p = String(root.location.pathname || '');
            var m = p.match(/^(.*\/public\/)/i);
            if (m && m[1]) {
                return m[1] + 'admin';
            }
        } catch (e) { /* ignore */ }
        return '/rateb-erp/public/admin';
    }

    function isAdminAppHref(href) {
        try {
            var u = new URL(href, root.location.href);
            if (u.origin !== root.location.origin) {
                return false;
            }
            return /\/admin(\/|$)/i.test(u.pathname) || /\/pos(\/|$)/i.test(u.pathname);
        } catch (e) {
            return false;
        }
    }

    function isMuteHref(href) {
        try {
            var u = new URL(href, root.location.href);
            if (MUTE_NAV_RE.test(u.pathname) || MUTE_NAV_RE.test(u.search)) {
                return true;
            }
        } catch (e) { /* ignore */ }
        return false;
    }

    function candidateKeys(href) {
        var keys = [];
        try {
            var u = new URL(href, root.location.origin);
            keys.push(u.href);
            keys.push(u.origin + u.pathname);
            var bare = u.pathname.replace(/\/+$/, '');
            keys.push(u.origin + bare);
            keys.push(u.origin + bare + '/');
            if (u.search) {
                keys.push(u.origin + u.pathname + u.search);
            }
        } catch (e) { /* ignore */ }
        return keys;
    }

    function matchInCaches(href) {
        if (!root.caches || typeof root.caches.open !== 'function') {
            return Promise.resolve(false);
        }
        var keys = candidateKeys(href);
        return root.caches.keys().then(function (names) {
            var want = (names || []).filter(function (n) {
                return CACHE_NAMES.indexOf(String(n)) !== -1
                    || /^rateb-erp-ops-pages-/i.test(String(n))
                    || /^rateb-erp-coexist-/i.test(String(n))
                    || /^rateb-pos-shell-/i.test(String(n));
            });
            if (!want.length) {
                want = CACHE_NAMES.slice();
            }
            return want.reduce(function (chain, name) {
                return chain.then(function (found) {
                    if (found) {
                        return true;
                    }
                    return root.caches.open(name).then(function (cache) {
                        return keys.reduce(function (c2, key) {
                            return c2.then(function (hit) {
                                if (hit) {
                                    return true;
                                }
                                return cache.match(key).then(function (res) {
                                    if (!res) {
                                        return cache.match(key, { ignoreSearch: true }).then(function (res2) {
                                            return looksLikeRealPage(res2);
                                        }).catch(function () { return false; });
                                    }
                                    return looksLikeRealPage(res);
                                });
                            });
                        }, Promise.resolve(false));
                    }).catch(function () { return false; });
                });
            }, Promise.resolve(false));
        }).catch(function () {
            return false;
        });
    }

    function looksLikeRealPage(res) {
        if (!res) {
            return Promise.resolve(false);
        }
        try {
            if (String(res.headers.get('X-Rateb-Uncached-Page') || '') === '1') {
                return Promise.resolve(false);
            }
        } catch (e) { /* ignore */ }
        return Promise.resolve(true);
    }

    function sidebarLinks() {
        if (!root.document) {
            return [];
        }
        return Array.prototype.slice.call(root.document.querySelectorAll(
            'aside.rateb-sidebar a.rateb-nav-link[href], #rateb-sidebar a.rateb-nav-link[href],'
            + ' a.rateb-offline-rbac-link[href], aside a.rateb-nav-link[href],'
            + ' main a[href], .rateb-main a[href], .rateb-content a[href]'
        ));
    }

    function markLink(a, ok) {
        a.classList.toggle('rateb-offline-missing', !ok);
        a.classList.toggle('rateb-offline-cached', !!ok);
        if (ok) {
            a.removeAttribute('aria-disabled');
            a.title = a.getAttribute('data-rateb-title-orig') || a.title || '';
        } else {
            if (!a.getAttribute('data-rateb-title-orig')) {
                a.setAttribute('data-rateb-title-orig', a.title || '');
            }
            a.setAttribute('aria-disabled', 'true');
            a.title = 'غير محفوظ أوفلاين — افتحه مرة وأنت متصل';
        }
    }

    function clearMarks() {
        sidebarLinks().forEach(function (a) {
            a.classList.remove('rateb-offline-missing', 'rateb-offline-cached');
            a.removeAttribute('aria-disabled');
            var orig = a.getAttribute('data-rateb-title-orig');
            if (orig !== null) {
                a.title = orig;
            }
        });
        try {
            var t = root.document.getElementById('rateb-offline-nav-toast');
            if (t) {
                t.hidden = true;
            }
            var w = root.document.getElementById('rateb-offline-wrong-shell');
            if (w) {
                w.remove();
            }
        } catch (e) { /* ignore */ }
    }

    function warnWrongShell() {
        try {
            if (!isOffline() || !root.document || !root.document.body) {
                return;
            }
            if (root.document.body.getAttribute('data-rateb-uncached-page') === '1') {
                return;
            }
            var path = String(root.location.pathname || '').replace(/\/+$/, '');
            if (/(^|\/)admin$/i.test(path)) {
                return;
            }
            var titleEl = root.document.querySelector('h1, .rateb-page-title, .page-title');
            var title = titleEl ? String(titleEl.textContent || '').trim() : '';
            if (title.indexOf('لوحة التحكم') === -1) {
                return;
            }
            ensureCss();
            var existing = root.document.getElementById('rateb-offline-wrong-shell');
            if (existing) {
                return;
            }
            var bar = root.document.createElement('div');
            bar.id = 'rateb-offline-wrong-shell';
            bar.setAttribute('role', 'alert');
            bar.innerHTML = 'هذه الشاشة غير محفوظة أوفلاين — عُرضت لوحة التحكم بالخطأ سابقاً. '
                + '<a href="' + publicAdminBase() + '/" style="color:#fff;text-decoration:underline">افتح اللوحة</a>'
                + ' وأنت متصل ثم أعد المحاولة.';
            root.document.body.insertBefore(bar, root.document.body.firstChild);
        } catch (e) { /* ignore */ }
    }

    function scan() {
        if (scanning) {
            return Promise.resolve();
        }
        if (!isOffline()) {
            clearMarks();
            return Promise.resolve();
        }
        ensureCss();
        warnWrongShell();
        scanning = true;
        var links = sidebarLinks();
        return Promise.all(links.map(function (a) {
            var href = a.getAttribute('href') || '';
            if (!href || href === '#' || /^javascript:/i.test(href)) {
                return null;
            }
            if (!isAdminAppHref(href)) {
                return null;
            }
            if (isMuteHref(href)) {
                markLink(a, false);
                return null;
            }
            return matchInCaches(href).then(function (ok) {
                markLink(a, ok);
            });
        })).finally(function () {
            scanning = false;
        });
    }

    function blockWrite(ev, reason) {
        ev.preventDefault();
        ev.stopPropagation();
        toast(reason || 'الحفظ والحذف يحتاجان اتصال بالإنترنت.');
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
            if (form) {
                var method = String(form.getAttribute('method') || 'get').toLowerCase();
                if (method === 'post') {
                    blockWrite(ev, 'الحفظ غير متاح أوفلاين. افتح النموذج وتصفّحه، ثم وصّل النت للحفظ.');
                    return;
                }
            }
            var label = String(submitBtn.textContent || submitBtn.value || '');
            if (WRITE_TEXT_RE.test(label)) {
                blockWrite(ev, 'الحفظ/الحذف يحتاج اتصال بالإنترنت.');
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
        if (!isAdminAppHref(href)) {
            try {
                var u = new URL(href, root.location.href);
                if (u.origin === root.location.origin && /rateb-platform-catalog/i.test(u.pathname)) {
                    blockWrite(ev, 'كتالوج المنصة غير متاح أوفلاين من هنا.');
                }
            } catch (eCat) { /* ignore */ }
            return;
        }

        if (isMuteHref(href)) {
            blockWrite(ev, 'الحذف/التصدير يحتاج اتصال بالإنترنت.');
            return;
        }

        if (a.classList.contains('rateb-offline-cached')) {
            return;
        }
        if (a.classList.contains('rateb-offline-missing')) {
            blockWrite(ev, 'هذا الرابط غير محفوظ أوفلاين. وصّل النت وانتظر «تجهيز الأوفلاين».');
            return;
        }

        ev.preventDefault();
        ev.stopPropagation();
        matchInCaches(href).then(function (ok) {
            markLink(a, ok);
            if (ok) {
                root.location.href = href;
            } else {
                toast('الصفحة غير محفوظة أوفلاين بعد. وصّل النت وانتظر اكتمال التجهيز ثم أعد المحاولة.');
            }
        });
    }

    function onSubmit(ev) {
        if (!isOffline()) {
            return;
        }
        var form = ev.target;
        if (!form || !form.getAttribute) {
            return;
        }
        var method = String(form.getAttribute('method') || 'get').toLowerCase();
        if (method === 'post') {
            blockWrite(ev, 'الحفظ والإرسال غير متاحين أوفلاين.');
        }
    }

    function boot() {
        if (!root.document) {
            return;
        }
        root.document.addEventListener('click', onClick, true);
        root.document.addEventListener('submit', onSubmit, true);
        var run = function () { scan(); };
        if (root.document.readyState === 'loading') {
            root.document.addEventListener('DOMContentLoaded', run, { once: true });
        } else {
            run();
        }
        root.addEventListener('online', function () {
            clearMarks();
        });
        root.addEventListener('offline', function () {
            setTimeout(scan, 200);
        });
        root.document.addEventListener('rateb-offline-connectivity', function (ev) {
            var online = ev && ev.detail && ev.detail.online;
            if (online) {
                clearMarks();
            } else {
                setTimeout(scan, 200);
            }
        });
        root.document.addEventListener('rateb-connection-badge', function (ev) {
            var online = ev && ev.detail && ev.detail.online;
            if (online) {
                clearMarks();
            } else {
                setTimeout(scan, 200);
            }
        });
        setInterval(function () {
            if (isOffline()) {
                scan();
            } else {
                clearMarks();
            }
        }, 8000);
    }

    root.RatebOfflineNavGuard = { scan: scan, isOffline: isOffline };
    boot();
})(typeof window !== 'undefined' ? window : globalThis);
