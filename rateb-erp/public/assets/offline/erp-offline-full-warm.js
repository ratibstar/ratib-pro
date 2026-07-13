/**
 * RATEB ERP — Full Admin offline warm (Cache API).
 * Fixes: logout≠login false skip; live progress; cache identity JS first.
 */
(function (root) {
    'use strict';

    var STORAGE_KEY = 'rateb_erp_full_warm_at';
    var SUCCESS_KEY = 'rateb_erp_full_warm_ok';
    var WARM_TTL_MS = 4 * 60 * 60 * 1000;
    var MAX_URLS = 200;
    var CONCURRENCY = 3;
    var GAP_MS = 350;
    var MIN_OK = 8;
    var CACHE_NAME = 'rateb-erp-ops-pages-v30';
    var COEXIST = 'rateb-erp-coexist-v24';
    var running = false;
    var progress = { finished: 0, ok: 0, total: 0 };

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

    function stopWarmBannerIfOffline() {
        try {
            if (typeof navigator !== 'undefined' && navigator.onLine === false) {
                var box = root.document.getElementById('rateb-offline-warm-progress');
                if (box) {
                    box.textContent = 'التسخين يحتاج اتصال — وصّل النت لإكمال الحفظ';
                    box.style.background = '#7f1d1d';
                }
                return true;
            }
        } catch (e) { /* ignore */ }
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

    function looksLikeLoginHtml(html) {
        var head = String(html || '').slice(0, 2500);
        // Do NOT use /login/i — it matches "logout".
        if (/\/login(\/|"|'|\?|#)/i.test(head) && /name=["']password["']/i.test(head)) {
            return true;
        }
        if (/name=["']password["']/i.test(head) && /name=["'](email|username)["']/i.test(head)) {
            return true;
        }
        return false;
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
            'admin', 'admin/', 'admin/companies', 'admin/agency-updates',
            'admin/executive-dashboard', 'admin/notifications', 'admin/profile',
            'admin/ops/branch-dashboard', 'admin/ops/branch-dashboard/compare',
            'admin/ops/branch-dashboard/reports',
            'admin/oversight/companies-approvals', 'admin/oversight/approvals',
            'admin/oversight/procurement', 'admin/oversight/rfq',
            'admin/oversight/inventory', 'admin/oversight/supplier-evaluations',
            'admin/oversight/workflows',
            'admin/ops/purchase-requests', 'admin/ops/purchase-orders',
            'admin/ops/rfq', 'admin/ops/quotations', 'admin/ops/inventory',
            'admin/ops/warehouses', 'admin/ops/stock-movements',
            'admin/ops/product-categories', 'admin/ops/suppliers',
            'admin/hr/attendance', 'admin/hr/leaves',
            'admin/ops/goods-receipts', 'admin/ops/warehouse-transfers',
            'admin/ops/pos', 'admin/ops/pos/dashboard', 'admin/ops/pos/register',
            'pos', 'pos/register', 'pos/dashboard'
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

    function updateProgressUi() {
        try {
            var box = root.document.getElementById('rateb-offline-warm-progress');
            if (!box) {
                return;
            }
            box.textContent = 'تجهيز الأوفلاين… ' + progress.ok + '/' + progress.total
                + ' (تمت ' + progress.finished + ')';
        } catch (e) { /* ignore */ }
    }

    function ensureProgressUi(total) {
        progress = { finished: 0, ok: 0, total: total };
        try {
            var el = root.document.getElementById('rateb-offline-warm-progress');
            if (!el) {
                el = root.document.createElement('div');
                el.id = 'rateb-offline-warm-progress';
                el.setAttribute('role', 'status');
                el.style.cssText = 'position:fixed;bottom:12px;left:12px;z-index:99999;padding:8px 12px;'
                    + 'background:#1e3a5f;color:#e8eaed;font:12px/1.4 system-ui,sans-serif;border-radius:8px;'
                    + 'opacity:.95;max-width:18rem';
                root.document.body.appendChild(el);
            }
            updateProgressUi();
        } catch (e) { /* ignore */ }
    }

    function putIntoCaches(href, response) {
        if (!root.caches) {
            return Promise.resolve(false);
        }
        var keys = [href];
        try {
            var u = new URL(href);
            keys.push(u.origin + u.pathname);
            var bare = u.pathname.replace(/\/+$/, '');
            keys.push(u.origin + bare);
            keys.push(u.origin + bare + '/');
            if (u.search) {
                keys.push(u.origin + u.pathname + u.search);
            }
        } catch (e) { /* ignore */ }
        var uniq = [];
        keys.forEach(function (k) {
            if (k && uniq.indexOf(k) === -1) {
                uniq.push(k);
            }
        });
        return Promise.all([
            root.caches.open(CACHE_NAME),
            root.caches.open(COEXIST)
        ]).then(function (pair) {
            var ops = pair[0];
            var co = pair[1];
            return Promise.all(uniq.map(function (k) {
                return Promise.all([
                    ops.put(k, response.clone()).catch(function () { return null; }),
                    co.put(k, response.clone()).catch(function () { return null; })
                ]);
            })).then(function () { return true; });
        }).catch(function () {
            return false;
        });
    }

    function fetchAndCache(href) {
        if (stopWarmBannerIfOffline()) {
            return Promise.resolve(false);
        }
        return root.fetch(href, {
            credentials: 'same-origin',
            cache: 'no-cache',
            headers: { Accept: '*/*', 'X-Rateb-Shell-Warm': '1' }
        }).then(function (res) {
            if (!res || res.status !== 200) {
                return false;
            }
            var ct = String(res.headers.get('Content-Type') || '');
            if (/text\/html/i.test(ct) || /\/admin(\/|$)/i.test(href)) {
                return res.clone().text().then(function (html) {
                    if (!html || html.length < 400 || looksLikeLoginHtml(html)) {
                        return false;
                    }
                    return putIntoCaches(href, res);
                });
            }
            return putIntoCaches(href, res);
        }).catch(function () {
            return false;
        });
    }

    function criticalAssetUrls() {
        var base = root.location.origin + publicBase();
        var files = [
            'assets/offline/rateb-offline.js',
            'assets/offline/rateb-offline.min.js',
            'assets/offline/erp-offline-shell-auth.js',
            'assets/offline/erp-offline-shell-rbac.js',
            'assets/offline/erp-shell-bootstrap.js',
            'assets/offline/erp-offline-full-warm.js',
            'assets/offline/erp-offline-nav-guard.js',
            'assets/offline/ops-page-allowlist.json',
            'offline-shell.html',
            'assets/css/variables.css',
            'assets/css/main.css',
            'assets/css/components.css',
            'assets/css/dark.css',
            'assets/css/rtl.css'
        ];
        var out = [];
        files.forEach(function (f) {
            out.push(base + f);
            out.push(base + f + '?v=20260713-offline-nav-guard');
            out.push(base + f + '?v=20260713-inline-shell-v30');
            out.push(base + f + '?v=oid-20260713-lean');
            out.push(base + f + '?v=20260713-probe-warm-fix');
        });
        return out;
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
                                progress.ok = ok;
                            }
                        }).finally(function () {
                            active -= 1;
                            finished += 1;
                            progress.finished = finished;
                            updateProgressUi();
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
        // Identity / CSS first — required for offline-shell unlock.
        criticalAssetUrls().forEach(function (u) {
            if (urls.indexOf(u) === -1) {
                urls.push(u);
            }
        });
        // POS register early — dashboard alone must not be the offline shell.
        var posFirst = [
            root.location.origin + publicBase() + 'admin/ops/pos/register',
            root.location.origin + publicBase() + 'pos/register',
            root.location.origin + publicBase() + 'admin/ops/pos'
        ];
        posFirst.forEach(function (u) {
            pushUrl(seen, urls, u);
        });
        seedCoreUrls(seen, urls);
        collectSidebarUrls(seen, urls);
        return loadAllowlistUrls(seen, urls).then(function (list) {
            ensureProgressUi(list.length);
            try {
                console.info('[RATIB OFFLINE] full warm start', list.length, 'urls');
            } catch (eLog) { /* ignore */ }
            return runQueue(list).then(function (stats) {
                markWarmed(stats.ok || 0);
                try {
                    if (root.RatebOfflineNavGuard && typeof root.RatebOfflineNavGuard.scan === 'function') {
                        root.RatebOfflineNavGuard.scan();
                    }
                } catch (eNav) { /* ignore */ }
                try {
                    var box2 = root.document.getElementById('rateb-offline-warm-progress');
                    if (box2) {
                        box2.textContent = 'أوفلاين جاهز: ' + (stats.ok || 0) + '/' + (stats.total || 0);
                        box2.style.background = (stats.ok || 0) >= MIN_OK ? '#14532d' : '#7f1d1d';
                        setTimeout(function () {
                            try { box2.remove(); } catch (eR) { /* ignore */ }
                        }, 8000);
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
        if (root.document && root.document.readyState === 'complete') {
            setTimeout(run, 600);
        } else if (root.addEventListener) {
            root.addEventListener('load', function () {
                setTimeout(run, 600);
            }, { once: true });
        } else {
            setTimeout(run, 1000);
        }
    }

    root.RatebOfflineFullWarm = {
        start: startFullWarm,
        schedule: schedule,
        cacheName: CACHE_NAME
    };

    schedule();
})(typeof window !== 'undefined' ? window : globalThis);
