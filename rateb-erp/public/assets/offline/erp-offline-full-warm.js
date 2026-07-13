/**
 * RATEB ERP — Full Admin offline warm (no per-page visit required).
 * Collects sidebar links + ops-page-allowlist routes, then caches HTML via SW.
 */
(function (root) {
    'use strict';

    var STORAGE_KEY = 'rateb_erp_full_warm_at';
    var WARM_TTL_MS = 6 * 60 * 60 * 1000; // 6 hours
    var MAX_URLS = 160;
    var CONCURRENCY = 2;
    var GAP_MS = 900;
    var running = false;

    function publicBase() {
        try {
            var p = String((root.location && root.location.pathname) || '');
            var m = p.match(/^(.*\/public\/)/i);
            if (m && m[1]) {
                return m[1];
            }
        } catch (e) { /* ignore */ }
        return '/rateb-erp/public/';
    }

    function companyId() {
        try {
            var cfg = root.__RATEB_ERP_SHELL_OFFLINE__ || {};
            var id = parseInt(cfg.company_id, 10) || 0;
            if (id > 0) {
                return id;
            }
        } catch (e0) { /* ignore */ }
        try {
            var u = new URL(root.location.href);
            return parseInt(u.searchParams.get('company_id') || '0', 10) || 0;
        } catch (e1) {
            return 0;
        }
    }

    function withCompany(href) {
        var cid = companyId();
        if (!(cid > 0)) {
            return href;
        }
        try {
            var u = new URL(href, root.location.origin);
            if (!u.searchParams.get('company_id')) {
                u.searchParams.set('company_id', String(cid));
            }
            return u.href;
        } catch (e) {
            return href;
        }
    }

    function shouldSkipWarm() {
        try {
            if (typeof navigator !== 'undefined' && navigator.onLine === false) {
                return true;
            }
        } catch (e0) { /* ignore */ }
        try {
            var at = parseInt(root.localStorage.getItem(STORAGE_KEY) || '0', 10) || 0;
            if (at > 0 && (Date.now() - at) < WARM_TTL_MS) {
                return true;
            }
        } catch (e1) { /* ignore */ }
        return false;
    }

    function markWarmed() {
        try {
            root.localStorage.setItem(STORAGE_KEY, String(Date.now()));
        } catch (e) { /* ignore */ }
    }

    function isAdminHref(href) {
        try {
            var u = new URL(href, root.location.origin);
            if (u.origin !== root.location.origin) {
                return false;
            }
            var p = String(u.pathname || '');
            if (!/\/admin(\/|$)/i.test(p)) {
                return false;
            }
            if (/\/(login|logout|password|api)\b/i.test(p)) {
                return false;
            }
            // Skip create/edit/id deep links — index pages are enough for offline browse.
            if (/\/(create|edit|delete|export|pdf|excel|csv)(\/|$)/i.test(p)) {
                return false;
            }
            if (/\/\d+(\/|$)/.test(p)) {
                return false;
            }
            return true;
        } catch (e) {
            return false;
        }
    }

    function collectSidebarUrls(seen, out) {
        if (!root.document) {
            return;
        }
        var links = root.document.querySelectorAll(
            'aside.rateb-sidebar a[href], #rateb-sidebar a[href], nav a.rateb-nav-link[href]'
        );
        Array.prototype.forEach.call(links, function (a) {
            if (out.length >= MAX_URLS) {
                return;
            }
            var raw = String(a.getAttribute('href') || '').trim();
            if (!raw || raw === '#' || /^javascript:/i.test(raw)) {
                return;
            }
            try {
                var u = new URL(raw, root.location.origin);
                var href = withCompany(u.href);
                var key = u.origin + u.pathname.replace(/\/+$/, '');
                if (seen[key] || !isAdminHref(href)) {
                    return;
                }
                seen[key] = true;
                out.push(href);
            } catch (e) { /* ignore */ }
        });
    }

    function seedCoreUrls(seen, out) {
        var base = publicBase();
        var core = [
            'admin',
            'admin/',
            'admin/companies',
            'admin/notifications',
            'admin/profile',
            'admin/ops/branch-dashboard',
            'admin/ops/purchase-requests',
            'admin/ops/purchase-orders',
            'admin/ops/rfq',
            'admin/ops/quotations',
            'admin/ops/inventory',
            'admin/ops/warehouses',
            'admin/ops/stock-movements',
            'admin/ops/suppliers',
            'admin/hr/attendance',
            'admin/hr/leaves'
        ];
        core.forEach(function (rel) {
            if (out.length >= MAX_URLS) {
                return;
            }
            try {
                var href = withCompany(root.location.origin + base + rel.replace(/^\//, ''));
                var u = new URL(href);
                var key = u.origin + u.pathname.replace(/\/+$/, '');
                if (seen[key]) {
                    return;
                }
                seen[key] = true;
                out.push(href);
            } catch (e) { /* ignore */ }
        });
    }

    function loadAllowlistUrls(seen, out) {
        var url = publicBase() + 'assets/offline/ops-page-allowlist.json';
        return root.fetch(url, {
            credentials: 'same-origin',
            cache: 'no-cache',
            headers: { Accept: 'application/json', 'X-Rateb-Shell-Warm': '1' }
        }).then(function (res) {
            if (!res || !res.ok) {
                return out;
            }
            return res.json().then(function (data) {
                var routes = (data && data.routes && typeof data.routes === 'object') ? data.routes : {};
                Object.keys(routes).forEach(function (logical) {
                    if (out.length >= MAX_URLS) {
                        return;
                    }
                    var route = String(routes[logical] || '').replace(/^\/+|\/+$/g, '');
                    if (!route) {
                        return;
                    }
                    try {
                        var href = withCompany(root.location.origin + publicBase() + route);
                        var u = new URL(href);
                        var key = u.origin + u.pathname.replace(/\/+$/, '');
                        if (seen[key] || !isAdminHref(href)) {
                            return;
                        }
                        seen[key] = true;
                        out.push(href);
                    } catch (e) { /* ignore */ }
                });
                return out;
            });
        }).catch(function () {
            return out;
        });
    }

    function putViaSw(href, html) {
        try {
            if (root.navigator && root.navigator.serviceWorker && root.navigator.serviceWorker.controller) {
                var path = '';
                try {
                    path = new URL(href).pathname;
                } catch (eP) { /* ignore */ }
                root.navigator.serviceWorker.controller.postMessage({
                    type: 'CACHE_ERP_OPS_PAGE',
                    url: href,
                    path: path,
                    html: html
                });
                return Promise.resolve(true);
            }
        } catch (e) { /* ignore */ }
        // Fallback: Cache API directly
        if (!root.caches) {
            return Promise.resolve(false);
        }
        return root.caches.open('rateb-erp-ops-pages-v27').then(function (cache) {
            var res = new Response(html, {
                status: 200,
                headers: { 'Content-Type': 'text/html; charset=utf-8', 'X-Rateb-Offline': '1' }
            });
            return Promise.all([
                cache.put(href, res.clone()),
                cache.put((function () {
                    try {
                        var u = new URL(href);
                        return u.origin + u.pathname;
                    } catch (e2) {
                        return href;
                    }
                })(), res)
            ]).then(function () { return true; });
        }).catch(function () { return false; });
    }

    function fetchAndCache(href) {
        return root.fetch(href, {
            credentials: 'same-origin',
            cache: 'no-cache',
            headers: { Accept: 'text/html', 'X-Rateb-Shell-Warm': '1' }
        }).then(function (res) {
            if (!res || res.status !== 200) {
                return false;
            }
            return res.text().then(function (html) {
                if (!html || html.length < 400) {
                    return false;
                }
                var head = String(html).slice(0, 900);
                if (/page not found/i.test(head) && /\b404\b/.test(head)) {
                    return false;
                }
                if (/name=["']password["']/i.test(head) && /login/i.test(head)) {
                    return false;
                }
                return putViaSw(href, html);
            });
        }).catch(function () {
            return false;
        });
    }

    function runQueue(urls) {
        var i = 0;
        var active = 0;
        var done = 0;
        return new Promise(function (resolve) {
            function pump() {
                if (done >= urls.length) {
                    resolve({ total: urls.length, done: done });
                    return;
                }
                while (active < CONCURRENCY && i < urls.length) {
                    (function (href) {
                        active += 1;
                        fetchAndCache(href).finally(function () {
                            active -= 1;
                            done += 1;
                            setTimeout(pump, GAP_MS);
                        });
                    })(urls[i++]);
                }
            }
            pump();
        });
    }

    function startFullWarm(opts) {
        opts = opts || {};
        if (running) {
            return Promise.resolve({ skipped: true, reason: 'running' });
        }
        if (!opts.force && shouldSkipWarm()) {
            return Promise.resolve({ skipped: true, reason: 'ttl' });
        }
        if (!root.fetch) {
            return Promise.resolve({ skipped: true, reason: 'no_fetch' });
        }
        running = true;
        var seen = {};
        var urls = [];
        seedCoreUrls(seen, urls);
        collectSidebarUrls(seen, urls);
        return loadAllowlistUrls(seen, urls).then(function (list) {
            try {
                console.info('[RATIB OFFLINE] full warm start', list.length, 'urls');
            } catch (eLog) { /* ignore */ }
            return runQueue(list).then(function (stats) {
                markWarmed();
                try {
                    console.info('[RATIB OFFLINE] full warm done', stats);
                } catch (e2) { /* ignore */ }
                return stats;
            });
        }).finally(function () {
            running = false;
        });
    }

    function schedule() {
        var run = function () {
            startFullWarm({ force: false });
        };
        if (typeof root.requestIdleCallback === 'function') {
            root.requestIdleCallback(run, { timeout: 20000 });
        } else if (root.document && root.document.readyState === 'complete') {
            setTimeout(run, 4000);
        } else if (root.addEventListener) {
            root.addEventListener('load', function () {
                setTimeout(run, 4000);
            }, { once: true });
        }
    }

    root.RatebOfflineFullWarm = {
        start: startFullWarm,
        schedule: schedule
    };

    schedule();
})(typeof window !== 'undefined' ? window : globalThis);
