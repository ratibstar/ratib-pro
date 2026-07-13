/**
 * RATEB ERP — Full Admin offline warm (Cache API, no postMessage HTML).
 * postMessage of large HTML was silently failing → "الصفحة غير محفوظة أوفلاين".
 */
(function (root) {
    'use strict';

    var STORAGE_KEY = 'rateb_erp_full_warm_at';
    var SUCCESS_KEY = 'rateb_erp_full_warm_ok';
    var WARM_TTL_MS = 4 * 60 * 60 * 1000;
    var MAX_URLS = 200;
    var CONCURRENCY = 3;
    var GAP_MS = 400;
    var MIN_OK = 12;
    var CACHE_NAME = 'rateb-erp-ops-pages-v30';
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
            return parseInt(new URL(root.location.href).searchParams.get('company_id') || '0', 10) || 0;
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

    function forceWarmRequested() {
        try {
            var q = String(root.location.search || '');
            return /[?&]rateb_warm=1(?:&|$)/.test(q)
                || /[?&]rateb_sw_reset=1(?:&|$)/.test(q);
        } catch (e) {
            return false;
        }
    }

    function shouldSkipWarm() {
        if (forceWarmRequested()) {
            return false;
        }
        try {
            if (typeof navigator !== 'undefined' && navigator.onLine === false) {
                return true;
            }
        } catch (e0) { /* ignore */ }
        try {
            var ok = parseInt(root.localStorage.getItem(SUCCESS_KEY) || '0', 10) || 0;
            var at = parseInt(root.localStorage.getItem(STORAGE_KEY) || '0', 10) || 0;
            if (ok >= MIN_OK && at > 0 && (Date.now() - at) < WARM_TTL_MS) {
                return true;
            }
        } catch (e1) { /* ignore */ }
        return false;
    }

    function markWarmed(okCount) {
        try {
            root.localStorage.setItem(STORAGE_KEY, String(Date.now()));
            root.localStorage.setItem(SUCCESS_KEY, String(okCount || 0));
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
            if (/\/(create|edit|delete|export|pdf|excel|csv|json|tinymce)(\/|$)/i.test(p)) {
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

    function pushUrl(seen, out, href) {
        if (out.length >= MAX_URLS) {
            return;
        }
        try {
            var full = withCompany(href);
            var u = new URL(full, root.location.origin);
            var key = u.origin + u.pathname.replace(/\/+$/, '');
            if (seen[key] || !isAdminHref(full)) {
                return;
            }
            seen[key] = true;
            out.push(u.href);
        } catch (e) { /* ignore */ }
    }

    function seedCoreUrls(seen, out) {
        var base = publicBase();
        var origin = root.location.origin;
        var core = [
            'admin',
            'admin/',
            'admin/companies',
            'admin/agency-updates',
            'admin/executive-dashboard',
            'admin/notifications',
            'admin/profile',
            'admin/ops/branch-dashboard',
            'admin/ops/branch-dashboard/compare',
            'admin/ops/branch-dashboard/reports',
            'admin/oversight/companies-approvals',
            'admin/oversight/approvals',
            'admin/oversight/procurement',
            'admin/oversight/rfq',
            'admin/oversight/inventory',
            'admin/oversight/supplier-evaluations',
            'admin/oversight/workflows',
            'admin/ops/purchase-requests',
            'admin/ops/purchase-orders',
            'admin/ops/rfq',
            'admin/ops/quotations',
            'admin/ops/inventory',
            'admin/ops/warehouses',
            'admin/ops/stock-movements',
            'admin/ops/product-categories',
            'admin/ops/suppliers',
            'admin/ops/hr/attendance',
            'admin/ops/hr/leaves',
            'admin/hr/attendance',
            'admin/hr/leaves',
            'admin/ops/goods-receipts',
            'admin/ops/warehouse-transfers',
            'admin/ops/inventory-audits'
        ];
        core.forEach(function (rel) {
            pushUrl(seen, out, origin + base + rel.replace(/^\//, ''));
        });
    }

    function collectSidebarUrls(seen, out) {
        if (!root.document) {
            return;
        }
        var links = root.document.querySelectorAll(
            'aside.rateb-sidebar a[href], #rateb-sidebar a[href], nav a.rateb-nav-link[href], a.rateb-nav-link[href]'
        );
        Array.prototype.forEach.call(links, function (a) {
            var raw = String(a.getAttribute('href') || '').trim();
            if (!raw || raw === '#' || /^javascript:/i.test(raw)) {
                return;
            }
            pushUrl(seen, out, raw);
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
                    var route = String(routes[logical] || '').replace(/^\/+|\/+$/g, '');
                    if (!route) {
                        return;
                    }
                    pushUrl(seen, out, root.location.origin + publicBase() + route);
                });
                return out;
            });
        }).catch(function () {
            return out;
        });
    }

    function cacheKeysFor(href) {
        var keys = [href];
        try {
            var u = new URL(href);
            keys.push(u.origin + u.pathname);
            var bare = u.pathname.replace(/\/+$/, '');
            keys.push(u.origin + bare);
            keys.push(u.origin + bare + '/');
        } catch (e) { /* ignore */ }
        var uniq = [];
        keys.forEach(function (k) {
            if (k && uniq.indexOf(k) === -1) {
                uniq.push(k);
            }
        });
        return uniq;
    }

    /** Cache API only — never postMessage large HTML (quota / clone failures). */
    function putHtml(href, html) {
        if (!root.caches) {
            return Promise.resolve(false);
        }
        var res = new Response(html, {
            status: 200,
            headers: {
                'Content-Type': 'text/html; charset=utf-8',
                'X-Rateb-Offline': '1',
                'X-Rateb-Ops-Page': '1',
                'Cache-Control': 'no-store'
            }
        });
        var keys = cacheKeysFor(href);
        return root.caches.open(CACHE_NAME).then(function (cache) {
            return Promise.all(keys.map(function (k) {
                return cache.put(k, res.clone()).catch(function () { return null; });
            })).then(function () {
                return true;
            });
        }).catch(function () {
            return false;
        });
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
                var head = String(html).slice(0, 1200);
                if (/page not found/i.test(head) && /\b404\b/.test(head)) {
                    return false;
                }
                if (/name=["']password["']/i.test(head) && /login/i.test(head)) {
                    return false;
                }
                return putHtml(href, html);
            });
        }).catch(function () {
            return false;
        });
    }

    function runQueue(urls) {
        var i = 0;
        var active = 0;
        var finished = 0;
        var ok = 0;
        return new Promise(function (resolve) {
            function pump() {
                if (finished >= urls.length && active === 0) {
                    resolve({ total: urls.length, ok: ok });
                    return;
                }
                while (active < CONCURRENCY && i < urls.length) {
                    (function (href) {
                        active += 1;
                        fetchAndCache(href).then(function (saved) {
                            if (saved) {
                                ok += 1;
                            }
                        }).finally(function () {
                            active -= 1;
                            finished += 1;
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
        if (!root.fetch || !root.caches) {
            return Promise.resolve({ skipped: true, reason: 'no_cache_api' });
        }
        running = true;
        var seen = {};
        var urls = [];
        seedCoreUrls(seen, urls);
        collectSidebarUrls(seen, urls);
        return loadAllowlistUrls(seen, urls).then(function (list) {
            try {
                console.info('[RATIB OFFLINE] full warm start', list.length, 'urls cache=' + CACHE_NAME);
            } catch (eLog) { /* ignore */ }
            // Show tiny progress for the user once.
            try {
                if (!root.document.getElementById('rateb-offline-warm-progress')) {
                    var el = root.document.createElement('div');
                    el.id = 'rateb-offline-warm-progress';
                    el.setAttribute('role', 'status');
                    el.style.cssText = 'position:fixed;bottom:12px;left:12px;z-index:99999;padding:8px 12px;'
                        + 'background:#1e3a5f;color:#e8eaed;font:12px/1.4 system-ui,sans-serif;border-radius:8px;'
                        + 'opacity:.92;max-width:16rem';
                    el.textContent = 'تجهيز الأوفلاين… 0/' + list.length;
                    root.document.body.appendChild(el);
                }
            } catch (eUi) { /* ignore */ }
            var progressTimer = setInterval(function () {
                try {
                    var box = root.document.getElementById('rateb-offline-warm-progress');
                    if (box) {
                        /* updated after queue via final message */
                    }
                } catch (eP) { /* ignore */ }
            }, 2000);
            return runQueue(list).then(function (stats) {
                clearInterval(progressTimer);
                markWarmed(stats.ok || 0);
                try {
                    var box2 = root.document.getElementById('rateb-offline-warm-progress');
                    if (box2) {
                        box2.textContent = 'أوفلاين جاهز: ' + (stats.ok || 0) + '/' + (stats.total || 0);
                        setTimeout(function () {
                            try { box2.remove(); } catch (eR) { /* ignore */ }
                        }, 5000);
                    }
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
            startFullWarm({ force: forceWarmRequested() });
        };
        // Start quickly after paint — idle delay made warm feel "unchanged".
        if (root.document && root.document.readyState === 'complete') {
            setTimeout(run, 800);
        } else if (root.addEventListener) {
            root.addEventListener('load', function () {
                setTimeout(run, 800);
            }, { once: true });
        } else {
            setTimeout(run, 1200);
        }
    }

    root.RatebOfflineFullWarm = {
        start: startFullWarm,
        schedule: schedule,
        cacheName: CACHE_NAME
    };

    schedule();
})(typeof window !== 'undefined' ? window : globalThis);
