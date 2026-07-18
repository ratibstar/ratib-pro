/**
 * RATEB ERP — Full Admin offline warm (Cache API).
 * Fixes: logout≠login false skip; live progress; cache identity JS first.
 */
(function (root) {
    'use strict';

    var MAX_URLS = 64;
    var CONCURRENCY = 3;
    var GAP_MS = 200;
    var MIN_OK = 8;
    // Lean shells (companies etc.) are valid with sidebar markers below ~20KB.
    var MIN_ERP_HTML_BYTES = 8000;
    var WARM_TTL_MS = 6 * 60 * 60 * 1000;
    var CACHE_NAME = 'rateb-erp-ops-pages-v34';
    var COEXIST = 'rateb-erp-coexist-v34';
    var POS_SHELL = 'rateb-pos-shell-v8';
    // Phase OH — bump TTL keys so clients re-warm after offline speed parity.
    var STORAGE_KEY = 'rateb_erp_full_warm_at_v16';
    var SUCCESS_KEY = 'rateb_erp_full_warm_ok_v16';
    var ASSETS_KEY = 'rateb_erp_full_warm_assets_v16';
    /** Certified offline-capable module HTML snapshots (Phase OH). */
    var CERTIFIED_MODULE_RELS = [
        'admin',
        'admin/',
        'admin/hr',
        'admin/hr/attendance',
        'admin/hr/leaves',
        'admin/hr/employees',
        'admin/ops/inventory',
        'admin/ops/warehouses',
        'admin/ops/purchase-requests',
        'admin/ops/purchase-orders',
        'admin/ops/suppliers',
        'admin/ops/stock-movements',
        'admin/ops/journal-entries',
        'admin/ops/access-control',
        'admin/ops/access-control/matrix',
        'admin/ops/accounting',
        'admin/ops/accounting/platform',
        'admin/ops/pos/register',
        'admin/accounting',
        'admin/companies',
        'admin/company-permissions',
        'admin/agency-updates',
        'admin/profile',
        'admin/notifications',
        'admin/users',
        'admin/branches',
        'admin/customers',
        'admin/cms',
        'admin/executive-dashboard',
        'admin/reports',
        'admin/cfo',
        'admin/oversight/approvals'
    ];
    var deadWarmUrls = {};
    var running = false;
    var progress = { finished: 0, ok: 0, total: 0 };
    var abortWarm = false;
    var warmAbortCtrl = null;
    var pendingResume = false;

    function isBrowserOffline() {
        try {
            if (typeof navigator !== 'undefined' && navigator.onLine === false) {
                return true;
            }
        } catch (e) { /* ignore */ }
        return false;
    }

    function newWarmSignal() {
        try {
            if (typeof AbortController !== 'undefined') {
                warmAbortCtrl = new AbortController();
                return warmAbortCtrl.signal;
            }
        } catch (e) { /* ignore */ }
        warmAbortCtrl = null;
        return undefined;
    }

    function killInFlightFetches() {
        abortWarm = true;
        try {
            if (warmAbortCtrl) {
                warmAbortCtrl.abort();
            }
        } catch (e) { /* ignore */ }
    }

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
        // Never append ?company_id= during warm — it produced console 404/500 storms
        // (e.g. /admin/ops?company_id=22, accounting/platform). Live visits are cached as-is.
        return href;
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
        if (!isBrowserOffline() && !abortWarm) {
            return false;
        }
        killInFlightFetches();
        pendingResume = true;
        try {
            var box = root.document.getElementById('rateb-offline-warm-progress');
            if (box) {
                box.textContent = 'توقف التسخين — وصّل النت ليُكمل تلقائياً';
                box.style.background = '#7f1d1d';
            }
        } catch (e) { /* ignore */ }
        return true;
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
            // Canonical POS is only under /admin/ops/pos — bare /public/pos/* 404s on cloud.
            if (/\/public\/pos(\/|$)/i.test(p) || /\/rateb-erp\/public\/pos(\/|$)/i.test(p)) {
                if (!/\/admin\/ops\/pos/i.test(p)) {
                    return false;
                }
            }
            if (/\/pos(\/|$)/i.test(p) && !/\/admin\/ops\/pos/i.test(p)) {
                return false;
            }
            if (!/\/admin(\/|$)/i.test(p)) {
                return false;
            }
            if (/\/(login|logout|password|api)\b/i.test(p)) {
                return false;
            }
            if (/\/(delete|destroy|export|pdf|excel|csv|json|tinymce|regenerate)(\/|$)/i.test(p)) {
                return false;
            }
            // /123/edit warm is noisy (404/500) — opened pages are pinned by cacheLiveAdminPage.
            if (/\/\d+\/(edit|show|view|generate)(\/|$)/i.test(p)) {
                return false;
            }
            if (/\/\d+(\/|$)/.test(p) && !/\/(create|new)(\/|$)/i.test(p)) {
                return false;
            }
            // Known-broken / platform-hub pages — skip warm to keep console clean.
            if (isWarmDeniedPath(p)) {
                return false;
            }
            return true;
        } catch (e) {
            return false;
        }
    }

    function isWarmDeniedPath(pathname) {
        var p = String(pathname || '').replace(/\/+$/, '');
        // Bare /admin/ops is not a real page (404).
        if (/\/admin\/ops$/i.test(p)) {
            return true;
        }
        // Accounting hub / incomplete children often 500 on platform.
        if (/\/accounting(\/platform)?$/i.test(p)) {
            return true;
        }
        if (isBrokenAccountingWarmPath(p)) {
            return true;
        }
        // Agency push / SaaS forms — online only, do not warm.
        if (/\/admin\/(agency-updates|company-permissions)(\/|$)/i.test(p + '/')) {
            // Still allow index list warm for company-permissions (useful offline browse).
            if (/\/admin\/agency-updates$/i.test(p)) {
                return true;
            }
            if (/\/admin\/company-permissions\/\d+$/i.test(p)) {
                return true;
            }
        }
        return false;
    }

    function isBrokenAccountingWarmPath(pathname) {
        return /\/accounting\/(platform|currencies|tax-codes|profit-centers|recurring|opening-balances)(\/|$)/i
            .test(String(pathname || ''));
    }

    function looksLikeLoginHtml(html) {
        var head = String(html || '').slice(0, 4000);
        // Real login pages only — user create forms also have password + email.
        if (/data-rateb-login|id=["']login-form["']|class=["'][^"']*login-form/i.test(head)) {
            return true;
        }
        if (/action=["'][^"']*\/login(\/|"|'|\?|#)/i.test(head) && /name=["']password["']/i.test(head)) {
            return true;
        }
        if (/(تسجيل الدخول|Sign in|Log in)/i.test(head)
            && /name=["']password["']/i.test(head)
            && /name=["'](email|username)["']/i.test(head)
            && !/\/admin\/users/i.test(head)
            && !/إنشاء مستخدم|Create user|add_user/i.test(head)) {
            return true;
        }
        return false;
    }

    /** Phase OH — never put placeholders, stubs, error shells, or thin HTML into ops cache. */
    function isCacheableErpHtml(html, href) {
        var body = String(html || '');
        var head = body.slice(0, 5000);
        var path = '';
        try {
            path = new URL(href, root.location.origin).pathname || '';
        } catch (eP) { /* ignore */ }
        if (looksLikeLoginHtml(body)) {
            return false;
        }
        if (/data-rateb-uncached-page/i.test(head) || /X-Rateb-Uncached-Page/i.test(head)) {
            return false;
        }
        if (/الصفحة غير محفوظة|RATEB ERP — الصفحة غير محفوظة/i.test(head)) {
            return false;
        }
        if (/<title>\s*POS Offline\s*<\/title>/i.test(head) || /نقطة البيع غير متصلة/i.test(head)) {
            return false;
        }
        if (/data-rateb-inline-shell|erpInlineShell/i.test(head)) {
            return false;
        }
        if (/<meta[^>]+http-equiv=["']refresh/i.test(head)) {
            return false;
        }
        if (/(Fatal error|Uncaught |HTTP\s*50[0-9]|Server Error|خطأ في الخادم)/i.test(head)
            && body.length < 40000) {
            return false;
        }
        // POS register shell is smaller than list pages but must carry register markers.
        if (/\/(?:admin\/ops\/)?pos(\/register)?$/i.test(path.replace(/\/+$/, ''))) {
            if (body.length < 2500) {
                return false;
            }
            if (/data-pos-biometric-gate/i.test(body)) {
                return false;
            }
            return /data-pos-register(?:\s|=|>)/i.test(body)
                && !/<title>\s*POS Offline\s*<\/title>/i.test(head);
        }
        if (body.length < MIN_ERP_HTML_BYTES) {
            return false;
        }
        // Full ERP layout markers (Arabic admin shell).
        if (!/rateb-sidebar|__RATEB_ERP_SHELL|rateb-main|لوحة التحكم|data-rateb-app/i.test(body)) {
            return false;
        }
        return true;
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
        // Phase OH — certified module HTML first (complete ERP documents).
        var core = CERTIFIED_MODULE_RELS.slice();
        [
            'admin/companies/create',
            'admin/ops/users/create', 'admin/users/create',
            'admin/ops/access-control', 'admin/ops/access-control/matrix',
            'admin/access-control', 'admin/access-control/matrix',
            'admin/ops/purchase-requests/create',
            'admin/ops/purchase-orders/create',
            'admin/ops/rfq', 'admin/ops/quotations',
            'admin/ops/product-categories',
            'admin/ops/suppliers/create'
        ].forEach(function (rel) {
            if (core.indexOf(rel) === -1) {
                core.push(rel);
            }
        });
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

    /** Table "+ Create" / edit pencil links — warm without visiting each row online. */
    function collectActionUrls(seen, out) {
        if (!root.document) {
            return;
        }
        var links = root.document.querySelectorAll(
            'main a[href], .rateb-main a[href], .rateb-content a[href],'
            + ' a.btn[href], a.btn-primary[href], a.btn-outline-primary[href],'
            + ' a[href*="/create"], a[href*="/edit"], a[href*="/new"]'
        );
        Array.prototype.forEach.call(links, function (a) {
            var raw = String(a.getAttribute('href') || '').trim();
            if (!raw || raw === '#' || /^javascript:/i.test(raw)) {
                return;
            }
            pushUrl(seen, out, raw);
        });
    }

    /** From sidebar/list URLs only — never invent /create on POS. */
    function deriveCreateUrls(seen, out) {
        var snapshot = out.slice();
        snapshot.forEach(function (href) {
            try {
                var u = new URL(href, root.location.origin);
                var p = String(u.pathname || '').replace(/\/+$/, '');
                if (/\/(create|edit|new|pos)(\/|$)/i.test(p)) {
                    return;
                }
                if (/\/\d+$/i.test(p)) {
                    return;
                }
                if (!/\/admin\/(ops\/)?(purchase-|inventory|warehouses|suppliers|users|companies|stock-movements|product-categories|rfq|quotations|hr\/)/i.test(p)) {
                    return;
                }
                pushUrl(seen, out, u.origin + p + '/create' + (u.search || ''));
            } catch (e) { /* ignore */ }
        });
    }

    /** Allowlist warm disabled by default — it sprayed 400+ URLs and 404/500 noise.
     *  Enable with ?rateb_warm_full=1 when needed. */
    function loadAllowlistUrls(seen, out) {
        try {
            if (!/[?&]rateb_warm_full=1(?:&|$)/.test(String(root.location.search || ''))) {
                return Promise.resolve(out);
            }
        } catch (eQ) {
            return Promise.resolve(out);
        }
        var url = publicBase() + 'assets/offline/ops-page-allowlist.json';
        var SKIP_LOGICAL = /^(recruitment|crm|projects|eam|eproc|mfg|qms|dms|bi|payroll|hrm)(\/|$)/i;
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
                    if (SKIP_LOGICAL.test(logical)) {
                        return;
                    }
                    if (/^accounting\/(currencies|tax-codes|profit-centers|recurring|opening-balances)/i.test(logical)) {
                        return;
                    }
                    var route = String(routes[logical] || '').replace(/^\/+|\/+$/g, '');
                    if (!route || !/^admin\//i.test(route)) {
                        return;
                    }
                    if (isBrokenAccountingWarmPath(route)) {
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
            if (warmQueueList && warmQueueList.length > progress.total) {
                progress.total = warmQueueList.length;
            }
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
        if (!root.caches || !response) {
            return Promise.resolve(false);
        }
        var keys = [href];
        try {
            var u = new URL(href, root.location.origin);
            keys.push(u.origin + u.pathname);
            var bare = u.pathname.replace(/\/+$/, '');
            keys.push(u.origin + bare);
            keys.push(u.origin + bare + '/');
            if (u.search) {
                keys.push(u.origin + u.pathname + u.search);
            }
                        // Dual-key admin ↔ admin/ops so matrix / accounting works under either URL.
            if (/\/admin\/ops\//i.test(u.pathname)) {
                var noOps = u.pathname.replace(/\/admin\/ops\//i, '/admin/');
                keys.push(u.origin + noOps);
                keys.push(u.origin + noOps.replace(/\/+$/, ''));
                if (u.search) {
                    keys.push(u.origin + noOps + u.search);
                }
            } else if (/\/admin\/(access-control|users|roles|permissions|accounting)(\/|$)/i.test(u.pathname)) {
                var withOps = u.pathname.replace(/\/admin\//i, '/admin/ops/');
                keys.push(u.origin + withOps);
                keys.push(u.origin + withOps.replace(/\/+$/, ''));
                if (u.search) {
                    keys.push(u.origin + withOps + u.search);
                }
            }
        } catch (e) { /* ignore */ }
        var uniq = [];
        keys.forEach(function (k) {
            if (k && uniq.indexOf(k) === -1) {
                uniq.push(k);
            }
        });
        var isPosRegister = false;
        try {
            var pu = new URL(href, root.location.origin);
            var pp = String(pu.pathname || '').replace(/\/+$/, '');
            isPosRegister = /\/(?:admin\/ops\/)?pos(\/register)?$/i.test(pp);
        } catch (ePos) { /* ignore */ }
        var status = response.status || 200;
        var statusText = response.statusText || '';
        var headers = new Headers();
        try {
            response.headers.forEach(function (v, k) { headers.set(k, v); });
        } catch (eH) { /* ignore */ }
        // Materialize once — never clone a consumed Response stream.
        return response.arrayBuffer().then(function (buf) {
            function makeRes() {
                return new Response(buf.slice(0), {
                    status: status,
                    statusText: statusText,
                    headers: new Headers(headers)
                });
            }
            return Promise.all([
                root.caches.open(CACHE_NAME),
                root.caches.open(COEXIST)
            ]).then(function (pair) {
                var ops = pair[0];
                var co = pair[1];
                var tasks = [];
                uniq.forEach(function (k) {
                    tasks.push(ops.put(k, makeRes()).catch(function () { return null; }));
                    tasks.push(co.put(k, makeRes()).catch(function () { return null; }));
                });
                return Promise.all(tasks).then(function () {
                    if (!isPosRegister) {
                        return true;
                    }
                    var html = '';
                    try {
                        html = new TextDecoder('utf-8').decode(buf);
                    } catch (eDec) { /* ignore */ }
                    if (!html || html.indexOf('data-pos-register') < 0) {
                        return true;
                    }
                    return root.caches.open(POS_SHELL).then(function (shell) {
                        var shellTasks = uniq.map(function (k) {
                            return shell.put(k, makeRes()).catch(function () { return null; });
                        });
                        try {
                            shellTasks.push(shell.put(
                                new URL('__rateb_pos_register_shell__', root.location.origin + publicBase()).href,
                                makeRes()
                            ).catch(function () { return null; }));
                        } catch (eKey) { /* ignore */ }
                        return Promise.all(shellTasks).then(function () { return true; });
                    });
                });
            });
        }).catch(function () {
            return false;
        });
    }

    function harvestAssetLinksFromHtml(html) {
        // Only CSS/JS/vendor — never harvest Admin HTML links (POS/create storms).
        var out = [];
        var seen = {};
        String(html || '').replace(
            /(?:href|src)=["']([^"']+)["']/gi,
            function (_m, raw) {
                try {
                    if (!/\/assets\/(css|js|vendor|offline)\//i.test(raw)
                        && !/connectivity-probe\.json/i.test(raw)) {
                        return '';
                    }
                    var full = new URL(raw, root.location.href).href;
                    if (seen[full]) {
                        return '';
                    }
                    seen[full] = true;
                    out.push(full);
                } catch (eH) { /* ignore */ }
                return '';
            }
        );
        return out;
    }

    function fetchAndCache(href, signal) {
        if (abortWarm || stopWarmBannerIfOffline()) {
            return Promise.resolve(false);
        }
        try {
            var pathCheck = new URL(href, root.location.origin).pathname;
            if (isBrokenAccountingWarmPath(pathCheck)) {
                return Promise.resolve(false);
            }
        } catch (eSkip) { /* ignore */ }
        if (!isAdminHref(href) && !/\/assets\//i.test(href) && !/offline-shell\.html/i.test(href)
            && !/connectivity-probe\.json/i.test(href)
            && !/ops-page-allowlist\.json/i.test(href)) {
            return Promise.resolve(false);
        }
        var deadKey = '';
        try {
            deadKey = new URL(href, root.location.origin).origin
                + new URL(href, root.location.origin).pathname.replace(/\/+$/, '');
        } catch (eD) {
            deadKey = href;
        }
        if (deadWarmUrls[deadKey]) {
            return Promise.resolve(false);
        }
        // Prefer already-cached copies — avoids network while SW/Browser cache has the file.
        // Phase OH — never re-promote placeholders/stubs from Cache Storage.
        var matchPromise = root.caches
            ? root.caches.match(href).catch(function () { return null; })
            : Promise.resolve(null);
        return matchPromise.then(function (cached) {
            if (abortWarm || stopWarmBannerIfOffline()) {
                return false;
            }
            if (cached && cached.ok) {
                var cachedCt = String(cached.headers.get('Content-Type') || '');
                if (/text\/html/i.test(cachedCt) || /\/admin(\/|$)/i.test(href)) {
                    return cached.clone().text().then(function (html) {
                        if (!isCacheableErpHtml(html, href)) {
                            // Fall through to network fetch below.
                            return null;
                        }
                        return putIntoCaches(href, cached).then(function (ok) {
                            return !!ok;
                        });
                    }).then(function (promoted) {
                        if (promoted === null) {
                            return fetchFromNetwork();
                        }
                        return promoted;
                    });
                }
                return putIntoCaches(href, cached).then(function (ok) {
                    return !!ok;
                });
            }
            return fetchFromNetwork();
        }).catch(function (err) {
            try {
                if (err && (err.name === 'AbortError' || abortWarm || isBrowserOffline())) {
                    return false;
                }
            } catch (e) { /* ignore */ }
            deadWarmUrls[deadKey] = true;
            return false;
        });

        function fetchFromNetwork() {
            if (isBrowserOffline()) {
                stopWarmBannerIfOffline();
                return Promise.resolve(false);
            }
            var opts = {
                credentials: 'same-origin',
                cache: 'no-cache',
                redirect: 'follow',
                headers: { Accept: 'text/html,*/*;q=0.8', 'X-Rateb-Shell-Warm': '1' }
            };
            if (signal) {
                opts.signal = signal;
            }
            return root.fetch(href, opts).then(function (res) {
                if (abortWarm || stopWarmBannerIfOffline()) {
                    return false;
                }
                if (!res || res.status !== 200) {
                    deadWarmUrls[deadKey] = true;
                    return false;
                }
                // Do not cache login / error redirects as the requested module.
                try {
                    var finalPath = new URL(res.url).pathname || '';
                    if (/\/(login|logout|password)\b/i.test(finalPath)) {
                        deadWarmUrls[deadKey] = true;
                        return false;
                    }
                } catch (eFinal) { /* ignore */ }
                var ct = String(res.headers.get('Content-Type') || '');
                if (/text\/html/i.test(ct) || /\/admin(\/|$)/i.test(href)) {
                    return res.clone().text().then(function (html) {
                        if (!isCacheableErpHtml(html, href)) {
                            deadWarmUrls[deadKey] = true;
                            return false;
                        }
                        var assetExtras = harvestAssetLinksFromHtml(html);
                        var sibling = null;
                        try {
                            if (res.url && String(res.url) !== String(href)) {
                                sibling = res.clone();
                            }
                        } catch (eSibling) {
                            sibling = null;
                        }
                        return putIntoCaches(href, res).then(function (ok) {
                            var extraPuts = sibling
                                ? putIntoCaches(res.url, sibling).catch(function () { return true; })
                                : Promise.resolve(true);
                            return extraPuts.then(function () {
                                if (!ok || !assetExtras.length || abortWarm || isBrowserOffline()) {
                                    return !!ok;
                                }
                                return runQueue(assetExtras, {
                                    concurrency: 3,
                                    gapMs: 20,
                                    signal: signal
                                }).then(function () {
                                    return true;
                                }).catch(function () {
                                    return true;
                                });
                            });
                        });
                    });
                }
                return putIntoCaches(href, res);
            });
        }
    }

    var warmQueueSeen = null;
    var warmQueueList = null;

    function criticalAssetUrls() {
        var base = root.location.origin + publicBase();
        var build = '20260713-force-sw-v42';
        var files = [
            'assets/offline/offline-bootstrap.js',
            'assets/offline/modules/offline-storage.js',
            'assets/offline/modules/offline-auth.js',
            'assets/offline/modules/offline-rbac.js',
            'assets/offline/rateb-offline.js',
            'assets/offline/rateb-offline.min.js',
            'assets/offline/erp-offline-shell-auth.js',
            'assets/offline/erp-offline-shell-rbac.js',
            'assets/offline/erp-shell-bootstrap.js',
            'assets/offline/erp-offline-full-warm.js',
            'assets/offline/erp-offline-nav-guard.js',
            'assets/offline/ops-page-allowlist.json',
            'offline-shell.html',
            'connectivity-probe.json',
            'assets/css/critical-shell.css',
            'assets/css/variables.css',
            'assets/css/main.css',
            'assets/css/components.css',
            'assets/css/dark.css',
            'assets/css/light.css',
            'assets/css/rtl.css',
            'assets/css/dashboard.css',
            'assets/css/ar-typography.css',
            'assets/css/supplier-comms.css',
            'assets/css/supplier-payment.css',
            'assets/vendor/bootstrap/5.3.3/bootstrap.rtl.min.css',
            'assets/vendor/bootstrap/5.3.3/bootstrap.bundle.min.js',
            'assets/vendor/fontawesome/6.5.2/css/all.min.css',
            'assets/vendor/fontawesome/6.5.2/webfonts/fa-solid-900.woff2',
            'assets/vendor/fonts/tajawal/tajawal.css',
            'assets/vendor/chartjs/4.4.3/chart.umd.min.js',
            'assets/js/theme.js',
            'assets/js/app.js',
            'assets/js/connectivity-indicator.js',
            'assets/js/charts.js',
            'assets/js/lang.js',
            'assets/js/dashboard-tabs.js',
            'assets/js/module-page-stats.js',
            'assets/js/table-tools.js'
        ];
        var out = [];
        var seen = {};
        function push(u) {
            if (!u || seen[u]) {
                return;
            }
            seen[u] = true;
            out.push(u);
        }
        // Exact hrefs from this page first (correct ?v=).
        try {
            root.document.querySelectorAll('link[rel="stylesheet"][href], script[src]').forEach(function (node) {
                var href = node.getAttribute('href') || node.getAttribute('src') || '';
                if (/\/assets\/(css|js|vendor|offline)\//i.test(href) || /connectivity-probe\.json/i.test(href)) {
                    try {
                        push(new URL(href, root.location.href).href);
                    } catch (eU) { /* ignore */ }
                }
            });
        } catch (eLive) { /* ignore */ }
        files.forEach(function (f) {
            push(base + f + '?v=' + build);
        });
        return out;
    }

    function runQueue(urls, opts) {
        opts = opts || {};
        var concurrency = opts.concurrency || CONCURRENCY;
        var gapMs = typeof opts.gapMs === 'number' ? opts.gapMs : GAP_MS;
        var signal = opts.signal;
        var i = 0;
        var active = 0;
        var finished = 0;
        var ok = 0;
        var settled = false;
        return new Promise(function (resolve) {
            function finish(result) {
                if (settled) {
                    return;
                }
                settled = true;
                resolve(result);
            }
            function pump() {
                if (abortWarm || stopWarmBannerIfOffline()) {
                    if (active === 0) {
                        finish({ total: urls.length, ok: ok, aborted: true });
                    }
                    return;
                }
                if (finished >= urls.length && active === 0) {
                    finish({ total: urls.length, ok: ok });
                    return;
                }
                while (!abortWarm && active < concurrency && i < urls.length) {
                    (function (href) {
                        active += 1;
                        fetchAndCache(href, signal).then(function (saved) {
                            if (saved) {
                                ok += 1;
                                progress.ok = ok;
                            }
                        }).finally(function () {
                            active -= 1;
                            finished += 1;
                            progress.finished = finished;
                            updateProgressUi();
                            setTimeout(pump, gapMs);
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
        if (isBrowserOffline()) {
            pendingResume = true;
            return Promise.resolve({ skipped: true, reason: 'offline' });
        }
        running = true;
        abortWarm = false;
        pendingResume = false;
        var signal = newWarmSignal();
        var assetUrls = criticalAssetUrls();
        ensureProgressUi(Math.max(assetUrls.length, 40));
        try {
            var boxA = root.document.getElementById('rateb-offline-warm-progress');
            if (boxA) {
                boxA.textContent = 'حفظ التصميم أوفلاين…';
                boxA.style.background = '#1e3a5f';
            }
            console.info('[RATIB OFFLINE] asset warm start', assetUrls.length);
        } catch (eA) { /* ignore */ }

        // Phase 1: CSS/JS/vendor ONLY — finish before any page warm so design works offline early.
        return runQueue(assetUrls, { concurrency: 4, gapMs: 40, signal: signal }).then(function (assetStats) {
            if (assetStats.aborted) {
                return assetStats;
            }
            try {
                root.localStorage.setItem(ASSETS_KEY, String(assetStats.ok || 0));
            } catch (eAs) { /* ignore */ }
            if (abortWarm || isBrowserOffline()) {
                return { total: assetStats.total, ok: assetStats.ok, aborted: true, phase: 'assets' };
            }
            var seen = {};
            var urls = [];
            assetUrls.forEach(function (u) { seen[u] = true; });
            // Phase OH — certified module HTML snapshots first (seed + sidebar).
            seedCoreUrls(seen, urls);
            var posFirst = [
                root.location.origin + publicBase() + 'admin/ops/pos/register',
                root.location.origin + publicBase() + 'admin/ops/access-control/matrix',
                root.location.origin + publicBase() + 'admin/ops/access-control',
                root.location.origin + publicBase() + 'admin/ops/accounting/platform',
                root.location.origin + publicBase() + 'admin/ops/accounting',
                root.location.origin + publicBase() + 'admin/accounting',
                root.location.origin + publicBase() + 'admin/ops/journal-entries',
                root.location.origin + publicBase() + 'admin/ops/purchase-requests',
                root.location.origin + publicBase() + 'admin/ops/inventory',
                root.location.origin + publicBase() + 'admin/ops/warehouses',
                root.location.origin + publicBase() + 'admin/hr/attendance',
                root.location.origin + publicBase() + 'admin/ops/suppliers',
                root.location.origin + publicBase() + 'admin/hr/employees',
                root.location.origin + publicBase() + 'admin/notifications',
                root.location.origin + publicBase() + 'admin/users',
                root.location.origin + publicBase() + 'admin/branches',
                root.location.origin + publicBase() + 'admin/customers',
                root.location.origin + publicBase() + 'admin/cms',
                root.location.origin + publicBase() + 'admin/executive-dashboard',
                root.location.origin + publicBase() + 'admin/reports',
                root.location.origin + publicBase() + 'admin/cfo',
                root.location.origin + publicBase() + 'admin/oversight/companies-approvals',
                root.location.origin + publicBase() + 'admin/oversight/approvals',
                root.location.origin + publicBase() + 'admin/companies'
            ];
            posFirst.forEach(function (u) {
                pushUrl(seen, urls, u);
            });
            collectSidebarUrls(seen, urls);
            collectActionUrls(seen, urls);
            return Promise.resolve(urls).then(function (list) {
                warmQueueSeen = seen;
                warmQueueList = list;
                progress.ok = assetStats.ok || 0;
                progress.finished = assetStats.ok || 0;
                ensureProgressUi(list.length + (assetStats.total || 0));
                try {
                    console.info('[RATIB OFFLINE] page warm start', list.length, 'urls');
                } catch (eLog) { /* ignore */ }
                return runQueue(list, { concurrency: 3, gapMs: 200, signal: signal }).then(function (pageStats) {
                    return {
                        total: (assetStats.total || 0) + (pageStats.total || 0),
                        ok: (assetStats.ok || 0) + (pageStats.ok || 0),
                        aborted: !!pageStats.aborted,
                        assetsOk: assetStats.ok || 0,
                        pagesOk: pageStats.ok || 0
                    };
                });
            });
        }).then(function (stats) {
            warmQueueSeen = null;
            warmQueueList = null;
            if (!stats.aborted) {
                markWarmed(stats.ok || 0);
            } else {
                pendingResume = true;
            }
            try {
                if (root.RatebOfflineNavGuard && typeof root.RatebOfflineNavGuard.scan === 'function') {
                    root.RatebOfflineNavGuard.scan();
                }
            } catch (eNav) { /* ignore */ }
            try {
                var box2 = root.document.getElementById('rateb-offline-warm-progress');
                if (box2) {
                    if (stats.aborted) {
                        box2.textContent = 'توقف التسخين: ' + (stats.ok || 0) + '/' + (stats.total || 0) + ' — وصّل النت ليُكمل تلقائياً';
                        box2.style.background = '#7f1d1d';
                    } else {
                        box2.textContent = 'أوفلاين جاهز: ' + (stats.ok || 0) + '/' + (stats.total || 0);
                        box2.style.background = (stats.ok || 0) >= MIN_OK ? '#14532d' : '#7f1d1d';
                        setTimeout(function () {
                            try { box2.remove(); } catch (eR) { /* ignore */ }
                        }, 8000);
                    }
                }
                console.info('[RATIB OFFLINE] full warm done', stats);
            } catch (e2) { /* ignore */ }
            return stats;
        }).finally(function () {
            running = false;
        });
    }

    function schedule() {
        var run = function (force) {
            startFullWarm({ force: !!force || forceWarmRequested() });
        };
        try {
            root.addEventListener('offline', function () {
                killInFlightFetches();
                stopWarmBannerIfOffline();
            });
            // Phase OH — do NOT abort certified HTML warm on every sidebar click
            // (that previously left only /admin/ cached). Abort on leave/offline only.
            root.addEventListener('pagehide', function () {
                killInFlightFetches();
            });
            root.addEventListener('online', function () {
                if (pendingResume) {
                    setTimeout(function () { run(true); }, 1500);
                }
            });
        } catch (eOff) { /* ignore */ }

        // Phase OH / PERF-P3 — idle auto-warm ONLY after idle + 20s + user still active.
        // Must not compete with first online page paint or early navigation.
        function userStillActive() {
            try {
                if (document.visibilityState && document.visibilityState !== 'visible') {
                    return false;
                }
            } catch (eV) { /* ignore */ }
            try {
                var last = root.__RATEB_LAST_USER_ACTIVITY__;
                if (typeof last === 'number' && (Date.now() - last) > 120000) {
                    return false;
                }
            } catch (eA) { /* ignore */ }
            return true;
        }

        function trackActivity() {
            try {
                if (root.__RATEB_ACTIVITY_BOUND__) {
                    return;
                }
                root.__RATEB_ACTIVITY_BOUND__ = true;
                var mark = function () {
                    root.__RATEB_LAST_USER_ACTIVITY__ = Date.now();
                };
                mark();
                ['pointerdown', 'keydown', 'scroll', 'touchstart'].forEach(function (ev) {
                    root.document.addEventListener(ev, mark, { passive: true, capture: true });
                });
            } catch (eT) { /* ignore */ }
        }

        function kickIdle() {
            try {
                if (!/\/admin(\/|$)/i.test(String(root.location.pathname || ''))) {
                    return;
                }
                if (isBrowserOffline()) {
                    return;
                }
                if (!userStillActive()) {
                    return;
                }
                run(false);
            } catch (eKick) { /* ignore */ }
        }
        if (forceWarmRequested()) {
            var kickForce = function () { run(true); };
            if (root.document && root.document.readyState === 'complete') {
                setTimeout(kickForce, 1500);
            } else if (root.addEventListener) {
                root.addEventListener('load', function () { setTimeout(kickForce, 1500); }, { once: true });
            } else {
                setTimeout(kickForce, 2500);
            }
            return;
        }
        trackActivity();
        // PERF-P3: requestIdleCallback AND minimum 20s AND user still active.
        var idleKick = function () {
            var afterIdle = function () {
                setTimeout(function () {
                    if (userStillActive()) {
                        kickIdle();
                    }
                }, 20000);
            };
            if (typeof root.requestIdleCallback === 'function') {
                root.requestIdleCallback(afterIdle, { timeout: 60000 });
            } else {
                setTimeout(afterIdle, 25000);
            }
        };
        if (root.document && root.document.readyState === 'complete') {
            idleKick();
        } else if (root.addEventListener) {
            root.addEventListener('load', idleKick, { once: true });
        } else {
            setTimeout(idleKick, 30000);
        }
    }

    root.RatebOfflineFullWarm = {
        start: startFullWarm,
        schedule: schedule,
        cacheName: CACHE_NAME,
        certifiedModules: CERTIFIED_MODULE_RELS.slice()
    };

    schedule();
})(typeof window !== 'undefined' ? window : globalThis);
