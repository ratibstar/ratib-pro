/**
 * RATEB ERP — Offline nav guard.
 * While offline, only sidebar links that exist in Cache API stay clickable.
 * Uncached links looked "wrong" because every miss opened the same offline shell.
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

    function isOffline() {
        try {
            if (typeof navigator !== 'undefined' && navigator.onLine === false) {
                return true;
            }
        } catch (e0) { /* ignore */ }
        try {
            var conn = root.RatebOfflineConnectivity;
            if (conn && typeof conn.isOnline === 'function' && conn.isOnline() === false) {
                return true;
            }
        } catch (e1) { /* ignore */ }
        try {
            var badge = root.document.querySelector('[data-rateb-connection-status], #rateb-connection-indicator');
            if (badge && badge.classList.contains('is-offline')) {
                return true;
            }
        } catch (e2) { /* ignore */ }
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
            + 'a.rateb-offline-rbac-link.rateb-offline-missing{'
            + 'opacity:.38;pointer-events:auto;cursor:not-allowed;}'
            + 'a.rateb-nav-link.rateb-offline-missing span::after,'
            + 'a.rateb-offline-rbac-link.rateb-offline-missing span::after{'
            + 'content:" · غير محفوظ";font-size:.72em;opacity:.8;}'
            + '#rateb-offline-nav-toast{'
            + 'position:fixed;bottom:4.5rem;left:50%;transform:translateX(-50%);z-index:100000;'
            + 'background:#7f1d1d;color:#fff;padding:.65rem 1rem;border-radius:8px;'
            + 'font:13px/1.4 system-ui,sans-serif;max-width:90vw;text-align:center;}';
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
            }, 3200);
        } catch (e2) { /* ignore */ }
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
                                    if (res) {
                                        return true;
                                    }
                                    return cache.match(key, { ignoreSearch: true }).then(function (res2) {
                                        return !!res2;
                                    }).catch(function () { return false; });
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

    function sidebarLinks() {
        if (!root.document) {
            return [];
        }
        return Array.prototype.slice.call(root.document.querySelectorAll(
            'aside.rateb-sidebar a.rateb-nav-link[href], #rateb-sidebar a.rateb-nav-link[href],'
            + ' a.rateb-offline-rbac-link[href], aside a.rateb-nav-link[href]'
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
        scanning = true;
        var links = sidebarLinks();
        return Promise.all(links.map(function (a) {
            var href = a.getAttribute('href') || '';
            if (!href || href === '#' || /^javascript:/i.test(href)) {
                markLink(a, false);
                return null;
            }
            // Same-page dashboard link always OK while viewing a live/cached shell.
            try {
                var u = new URL(href, root.location.href);
                var here = String(root.location.pathname || '').replace(/\/+$/, '');
                var there = String(u.pathname || '').replace(/\/+$/, '');
                if (there === here) {
                    markLink(a, true);
                    return null;
                }
            } catch (eU) { /* ignore */ }
            return matchInCaches(href).then(function (ok) {
                markLink(a, ok);
            });
        })).finally(function () {
            scanning = false;
        });
    }

    function onClick(ev) {
        if (!isOffline()) {
            return;
        }
        var a = ev.target && ev.target.closest
            ? ev.target.closest('aside.rateb-sidebar a[href], #rateb-sidebar a[href], a.rateb-nav-link[href], a.rateb-offline-rbac-link[href]')
            : null;
        if (!a) {
            return;
        }
        if (a.classList.contains('rateb-offline-cached')) {
            return;
        }
        if (a.classList.contains('rateb-offline-missing')) {
            ev.preventDefault();
            ev.stopPropagation();
            toast('هذا الرابط غير محفوظ أوفلاين. وصّل النت وافتح الصفحة مرة، أو انتظر اكتمال «تجهيز الأوفلاين».');
            return;
        }
        // Not scanned yet — check sync-ish then decide.
        var href = a.getAttribute('href') || '';
        ev.preventDefault();
        ev.stopPropagation();
        matchInCaches(href).then(function (ok) {
            markLink(a, ok);
            if (ok) {
                root.location.href = href;
            } else {
                toast('هذا الرابط غير محفوظ أوفلاين. وصّل النت وافتح الصفحة مرة ثم أعد المحاولة.');
            }
        });
    }

    function boot() {
        if (!root.document) {
            return;
        }
        root.document.addEventListener('click', onClick, true);
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
        // Re-scan after warm progress updates cache.
        setInterval(function () {
            if (isOffline()) {
                scan();
            }
        }, 15000);
    }

    root.RatebOfflineNavGuard = { scan: scan, isOffline: isOffline };
    boot();
})(typeof window !== 'undefined' ? window : globalThis);
