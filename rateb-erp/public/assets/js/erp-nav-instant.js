/**
 * PERF-P3 — Instant ERP navigation (content-swap + gated prefetch).
 * Same tenant/session/shell only. Full reload fallback otherwise.
 * Prefetch: current module only until idle; never dashboard/profile/notifications/admin early.
 * Max 1 concurrent prefetch request.
 */
(function (root) {
    'use strict';

    if (root.__RATEB_NAV_INSTANT__) {
        return;
    }
    root.__RATEB_NAV_INSTANT__ = true;

    var COMMON_SCRIPT_RE = /\/assets\/(?:js\/(?:theme|connectivity-indicator|lang|rateb-modal|rateb-confirm|app|rateb-console-quiet)|offline\/(?:erp-offline-tenant-context|erp-pwa-install|erp-nav-instant|erp-offline-full-warm|erp-offline-nav-guard)|vendor\/bootstrap)\//i;
    var POS_PATH_RE = /\/(?:admin\/ops\/)?pos(\/register)?(\/|$|\?)/i;
    var ADMIN_PATH_RE = /\/admin(\/|$)/i;
    var loadedScripts = Object.create(null);
    var navigating = false;
    var prefetchSeen = Object.create(null);
    var prefetchQueue = [];
    var prefetchInFlight = 0;
    var PREFETCH_MAX_PARALLEL = 1;
    var idlePrefetchUnlocked = false;
    var lastHref = '';

    /** Paths blocked until browser idle (PERF-P3). */
    function isDeferredPrefetchPath(pathname) {
        var p = String(pathname || '').replace(/\/+$/, '') || '/';
        if (/\/admin\/?$/.test(p) || /\/admin$/.test(p)) {
            return true;
        }
        if (/\/admin\/ops\/profile(\/|$)/i.test(p) || /\/admin\/profile(\/|$)/i.test(p)) {
            return true;
        }
        if (/\/admin\/ops\/notifications(\/|$)/i.test(p) || /\/admin\/notifications(\/|$)/i.test(p)) {
            return true;
        }
        // Bare platform catalog /admin siblings that are not module pages
        if (/\/rateb-platform-catalog\/admin\/?$/i.test(p)) {
            return true;
        }
        return false;
    }

    function currentModulePrefix() {
        try {
            var path = root.location.pathname.replace(/\/+$/, '');
            var m = path.match(/(\/admin(?:\/(?:ops|hr|crm|recruitment|eproc|oversight|cms|companies)[^/]*)?)/i);
            if (m) {
                return m[1];
            }
        } catch (e) { /* ignore */ }
        return '/admin';
    }

    function isCurrentModuleHref(href) {
        try {
            var u = new URL(href, root.location.href);
            var prefix = currentModulePrefix();
            var path = u.pathname.replace(/\/+$/, '');
            if (isDeferredPrefetchPath(path) && !idlePrefetchUnlocked) {
                return false;
            }
            if (path === prefix || path.indexOf(prefix + '/') === 0) {
                return true;
            }
            // Same leaf module segment (e.g. /admin/hr/* while on /admin/hr/attendance)
            var parts = prefix.split('/').filter(Boolean);
            if (parts.length >= 2) {
                var leaf = '/' + parts.slice(0, 3).join('/');
                if (path === leaf || path.indexOf(leaf + '/') === 0) {
                    return true;
                }
            }
            return false;
        } catch (e2) {
            return false;
        }
    }

    function shellCfg() {
        return root.__RATEB_ERP_SHELL_OFFLINE__ || {};
    }

    function sameShell(doc) {
        try {
            var side = doc.querySelector('#rateb-sidebar, aside.rateb-sidebar, .rateb-sidebar');
            var main = doc.querySelector('#rateb-main-content, main.rateb-content');
            if (!side || !main) {
                return false;
            }
            if (doc.querySelector('body[data-rateb-uncached-page], [data-rateb-uncached-page="1"]')) {
                return false;
            }
            if (doc.querySelector('#login-form, form#password-form, [data-rateb-login]')) {
                return false;
            }
            // POS register uses a different shell — never content-swap into it.
            if (doc.body && /rateb-pos-shell/i.test(doc.body.className || '')) {
                return false;
            }
            if (doc.querySelector('main#rateb-pos-app, [data-pos-register="1"]')) {
                return false;
            }
            var cfg = shellCfg();
            var remoteCid = null;
            try {
                var scripts = doc.querySelectorAll('script:not([src])');
                for (var i = 0; i < scripts.length; i++) {
                    var t = scripts[i].textContent || '';
                    var m = t.match(/company_id["']?\s*:\s*(\d+)/);
                    if (m) {
                        remoteCid = m[1];
                        break;
                    }
                }
            } catch (eJ) { /* ignore */ }
            if (cfg.company_id && remoteCid && String(cfg.company_id) !== String(remoteCid)) {
                return false;
            }
            return true;
        } catch (e) {
            return false;
        }
    }

    function scriptKey(src) {
        try {
            var u = new URL(src, root.location.href);
            return u.origin + u.pathname;
        } catch (e) {
            return String(src).split('?')[0];
        }
    }

    /** Soft badge OR hard offline — content-swap must not hang on a pending fetch. */
    function isUiOffline() {
        try {
            if (typeof navigator !== 'undefined' && navigator.onLine === false) {
                return true;
            }
        } catch (e0) { /* ignore */ }
        try {
            var badge = document.querySelector('[data-rateb-connection-status], #rateb-connection-indicator');
            if (badge && badge.classList.contains('is-offline')) {
                return true;
            }
        } catch (e1) { /* ignore */ }
        try {
            var conn = root.RatebOfflineConnectivity;
            if (conn && typeof conn.isOnline === 'function' && conn.isOnline() === false) {
                return true;
            }
        } catch (e2) { /* ignore */ }
        return false;
    }

    function hardNavigate(href) {
        try {
            root.location.assign(href);
        } catch (eAssign) {
            try {
                root.location.href = href;
            } catch (eHref) { /* ignore */ }
        }
    }

    function fetchWithTimeout(url, opts, ms) {
        var timedOut = false;
        var timer = null;
        var timed = new Promise(function (_, reject) {
            timer = setTimeout(function () {
                timedOut = true;
                reject(new Error('nav_fetch_timeout'));
            }, typeof ms === 'number' ? ms : 2500);
        });
        var network = fetch(url, opts).then(function (res) {
            if (timer) {
                clearTimeout(timer);
            }
            if (timedOut) {
                throw new Error('nav_fetch_timeout');
            }
            return res;
        });
        return Promise.race([network, timed]);
    }

    function rememberExistingScripts() {
        document.querySelectorAll('script[src]').forEach(function (s) {
            loadedScripts[scriptKey(s.src)] = true;
        });
    }

    function postSw(msg) {
        try {
            var reg = navigator.serviceWorker && navigator.serviceWorker.controller;
            if (!reg && navigator.serviceWorker) {
                return navigator.serviceWorker.ready.then(function (r) {
                    if (r.active) {
                        r.active.postMessage(msg);
                    }
                });
            }
            if (reg) {
                reg.postMessage(msg);
            }
        } catch (e) { /* ignore */ }
        return Promise.resolve();
    }

    function runPrefetchQueue() {
        while (prefetchInFlight < PREFETCH_MAX_PARALLEL && prefetchQueue.length) {
            var href = prefetchQueue.shift();
            prefetchInFlight += 1;
            postSw({ type: 'PREFETCH_ERP_OPS_URL', url: href });
            try {
                fetch(href, {
                    credentials: 'same-origin',
                    headers: { Accept: 'text/html', 'X-Rateb-Prefetch': '1' }
                }).then(function (res) {
                    if (!res || !res.ok) {
                        return null;
                    }
                    return res.text().then(function (html) {
                        if (html && html.length >= 20000) {
                            postSw({ type: 'CACHE_ERP_OPS_PAGE', url: href, html: html });
                        }
                    });
                }).catch(function () { /* ignore */ }).then(function () {
                    prefetchInFlight = Math.max(0, prefetchInFlight - 1);
                    runPrefetchQueue();
                });
            } catch (e2) {
                prefetchInFlight = Math.max(0, prefetchInFlight - 1);
            }
        }
    }

    function prefetchUrl(href, opts) {
        if (!href || prefetchSeen[href]) {
            return;
        }
        opts = opts || {};
        try {
            var u = new URL(href, root.location.href);
            if (u.origin !== root.location.origin) {
                return;
            }
            if (!ADMIN_PATH_RE.test(u.pathname) || POS_PATH_RE.test(u.pathname)) {
                return;
            }
            if (!opts.force) {
                if (!idlePrefetchUnlocked) {
                    if (isDeferredPrefetchPath(u.pathname) || !isCurrentModuleHref(href)) {
                        return;
                    }
                } else if (isDeferredPrefetchPath(u.pathname)) {
                    // Idle unlocked: still only one concurrent; allow deferred paths once.
                } else if (!isCurrentModuleHref(href) && !opts.allowOther) {
                    return;
                }
            }
        } catch (eGate) {
            return;
        }
        prefetchSeen[href] = true;
        /* PERF-P3: max 1 concurrent prefetch. */
        prefetchQueue.push(href);
        runPrefetchQueue();
    }

    function bindPrefetch(rootEl) {
        var scope = rootEl || document;
        scope.querySelectorAll('a.rateb-nav-link[href], a[href*="/admin"]').forEach(function (a) {
            if (a.__ratebPrefetchBound) {
                return;
            }
            a.__ratebPrefetchBound = true;
            var go = function () {
                try {
                    var u = new URL(a.href, root.location.href);
                    if (u.origin !== root.location.origin) {
                        return;
                    }
                    if (!ADMIN_PATH_RE.test(u.pathname) || POS_PATH_RE.test(u.pathname)) {
                        return;
                    }
                    /* Hover intent: only current module until idle unlock. */
                    prefetchUrl(u.href);
                } catch (e) { /* ignore */ }
            };
            a.addEventListener('pointerenter', go, { passive: true });
            a.addEventListener('focus', go, { passive: true });
            a.addEventListener('touchstart', go, { passive: true });
        });
    }

    function idlePrefetchVisible() {
        try {
            /* PERF-P3: unlock deferred paths only after idle; prefetch current module first. */
            var unlockAndPrefetch = function () {
                idlePrefetchUnlocked = true;
                var links = document.querySelectorAll('a.rateb-nav-link[href]');
                if (!links.length) {
                    return;
                }
                var currentFirst = [];
                var deferred = [];
                Array.prototype.forEach.call(links, function (a) {
                    try {
                        var u = new URL(a.href, root.location.href);
                        if (!ADMIN_PATH_RE.test(u.pathname) || POS_PATH_RE.test(u.pathname)) {
                            return;
                        }
                        if (isDeferredPrefetchPath(u.pathname)) {
                            deferred.push(u.href);
                        } else if (isCurrentModuleHref(u.href)) {
                            currentFirst.push(u.href);
                        }
                    } catch (e) { /* ignore */ }
                });
                /* Current module only (max a few); deferred paths one-at-a-time later via queue. */
                currentFirst.slice(0, 2).forEach(function (href) {
                    prefetchUrl(href, { force: false });
                });
                /* Stagger deferred (dashboard/profile/notifications/admin) — still 1 concurrent. */
                deferred.slice(0, 3).forEach(function (href, i) {
                    setTimeout(function () {
                        prefetchUrl(href, { allowOther: true });
                    }, 4000 + i * 2500);
                });
            };
            var kick = function () {
                if (window.requestIdleCallback) {
                    window.requestIdleCallback(unlockAndPrefetch, { timeout: 15000 });
                } else {
                    setTimeout(unlockAndPrefetch, 8000);
                }
            };
            /* Do not start before 5s after boot — first paint / navigation quiet. */
            setTimeout(kick, 5000);
        } catch (e3) { /* ignore */ }
    }

    function updateActiveNav(href) {
        try {
            var path = new URL(href, root.location.href).pathname.replace(/\/+$/, '');
            document.querySelectorAll('a.rateb-nav-link').forEach(function (a) {
                var ap = '';
                try {
                    ap = new URL(a.href, root.location.href).pathname.replace(/\/+$/, '');
                } catch (e) { return; }
                var on = ap === path || (path.indexOf(ap) === 0 && ap.length > 10);
                a.classList.toggle('active', on);
            });
        } catch (e2) { /* ignore */ }
    }

    function syncMeta(doc) {
        var csrf = doc.querySelector('meta[name="rateb-csrf"]');
        var local = document.querySelector('meta[name="rateb-csrf"]');
        if (csrf && local) {
            local.setAttribute('content', csrf.getAttribute('content') || '');
        }
        var title = doc.querySelector('title');
        if (title) {
            document.title = title.textContent || document.title;
        }
    }

    function runLifecycle(name, detail) {
        try {
            var life = root.RatebModuleLifecycle;
            if (life && typeof life[name] === 'function') {
                life[name](detail || {});
            }
        } catch (e) { /* ignore */ }
        try {
            document.dispatchEvent(new CustomEvent('rateb:nav:' + name, { detail: detail || {} }));
        } catch (e2) { /* ignore */ }
    }

    function loadNewScripts(doc) {
        var chain = Promise.resolve();
        var nodes = doc.querySelectorAll('script[src]');
        nodes.forEach(function (s) {
            var src = s.getAttribute('src');
            if (!src) {
                return;
            }
            var key = scriptKey(src);
            if (loadedScripts[key]) {
                return;
            }
            if (COMMON_SCRIPT_RE.test(key) || /erp-nav-instant/i.test(key)) {
                loadedScripts[key] = true;
                return;
            }
            chain = chain.then(function () {
                return new Promise(function (resolve) {
                    var el = document.createElement('script');
                    el.src = src;
                    el.defer = true;
                    el.onload = el.onerror = function () {
                        loadedScripts[key] = true;
                        resolve();
                    };
                    (document.body || document.documentElement).appendChild(el);
                });
            });
        });
        return chain;
    }

    function reinitModuleUi() {
        try {
            if (root.RatebApp && typeof root.RatebApp.reinit === 'function') {
                root.RatebApp.reinit();
            }
        } catch (e) { /* ignore */ }
        try {
            document.querySelectorAll('[data-module-metrics-async]').forEach(function (el) {
                if (el.getAttribute('data-rateb-metrics-loaded') === '1') {
                    return;
                }
                // module-page-stats listens for rateb:nav:enter
            });
        } catch (e2) { /* ignore */ }
    }

    function openOpsCaches() {
        if (!root.caches || typeof root.caches.keys !== 'function') {
            return Promise.resolve([]);
        }
        return root.caches.keys().then(function (keys) {
            var names = (keys || []).filter(function (k) {
                return String(k).indexOf('rateb-erp-ops-pages-') === 0
                    || String(k).indexOf('rateb-erp-coexist-') === 0;
            });
            if (names.indexOf('rateb-erp-ops-pages-v34') === -1) {
                names.unshift('rateb-erp-ops-pages-v34');
            }
            return Promise.all(names.map(function (name) {
                return root.caches.open(name).catch(function () { return null; });
            })).then(function (opened) {
                return opened.filter(Boolean);
            });
        }).catch(function () {
            return [];
        });
    }

    function matchCachedHtml(href) {
        if (!root.caches) {
            return Promise.resolve(null);
        }
        var keys = [href];
        try {
            var u = new URL(href, root.location.href);
            keys.push(u.origin + u.pathname);
            keys.push(u.origin + u.pathname.replace(/\/+$/, ''));
            keys.push(u.origin + u.pathname.replace(/\/+$/, '') + '/');
        } catch (e) { /* ignore */ }
        return openOpsCaches().then(function (cachesList) {
            var chain = Promise.resolve(null);
            cachesList.forEach(function (cache) {
                keys.forEach(function (k) {
                    chain = chain.then(function (found) {
                        return found || cache.match(k).then(function (hit) {
                            return hit || cache.match(k, { ignoreSearch: true }).catch(function () { return null; });
                        });
                    });
                });
            });
            return chain;
        }).catch(function () {
            return null;
        });
    }

    function putHtmlLocally(href, html) {
        if (!root.caches || !html || html.length < 20000) {
            return Promise.resolve(false);
        }
        var keys = [href];
        try {
            var u = new URL(href, root.location.href);
            keys.push(u.origin + u.pathname);
            var bare = u.pathname.replace(/\/+$/, '');
            keys.push(u.origin + bare);
            keys.push(u.origin + bare + '/');
        } catch (e) { /* ignore */ }
        var body = html;
        return root.caches.open('rateb-erp-ops-pages-v34').then(function (cache) {
            return Promise.all(keys.map(function (k) {
                return cache.put(k, new Response(body, {
                    status: 200,
                    headers: {
                        'Content-Type': 'text/html; charset=utf-8',
                        'X-Rateb-Ops-Page': '1'
                    }
                })).catch(function () { return null; });
            })).then(function () { return true; });
        }).catch(function () { return false; });
    }

    function fetchHtml(href) {
        // PERF-P1 — Cache API first (SW SWR only applies to mode=navigate; content-swap uses fetch).
        return matchCachedHtml(href).then(function (cached) {
            if (cached) {
                if (!isUiOffline()) {
                    fetchWithTimeout(href, {
                        credentials: 'same-origin',
                        headers: { Accept: 'text/html', 'X-Rateb-Nav-Swap': '1' }
                    }, 2500).then(function (res) {
                        if (!res || !res.ok) {
                            return null;
                        }
                        return res.text().then(function (html) {
                            if (html && html.length >= 20000) {
                                putHtmlLocally(href, html);
                                postSw({ type: 'CACHE_ERP_OPS_PAGE', url: href, html: html });
                            }
                        });
                    }).catch(function () { /* ignore */ });
                }
                return cached.text().then(function (html) {
                    return { html: html, finalUrl: href, fromCache: true };
                });
            }
            if (isUiOffline()) {
                throw new Error('nav_offline_no_cache');
            }
            return fetchWithTimeout(href, {
                credentials: 'same-origin',
                headers: { Accept: 'text/html', 'X-Rateb-Nav-Swap': '1' }
            }, 2500).then(function (res) {
                if (!res || !res.ok) {
                    throw new Error('nav_fetch_failed');
                }
                return res.text().then(function (html) {
                    putHtmlLocally(res.url || href, html);
                    postSw({ type: 'CACHE_ERP_OPS_PAGE', url: res.url || href, html: html });
                    return { html: html, finalUrl: res.url || href, fromCache: false };
                });
            });
        });
    }

    function swapTo(href, opts) {
        opts = opts || {};
        if (navigating) {
            return Promise.resolve(false);
        }
        navigating = true;
        var t0 = performance.now();
        runLifecycle('beforeLeave', { href: root.location.href, next: href });

        return fetchHtml(href).then(function (pack) {
            var doc = new DOMParser().parseFromString(pack.html, 'text/html');
            if (!sameShell(doc)) {
                throw new Error('shell_mismatch');
            }
            var nextMain = doc.querySelector('#rateb-main-content, main.rateb-content');
            var curMain = document.querySelector('#rateb-main-content, main.rateb-content');
            if (!nextMain || !curMain) {
                throw new Error('missing_main');
            }
            curMain.innerHTML = nextMain.innerHTML;
            syncMeta(doc);
            updateActiveNav(pack.finalUrl);
            if (!opts.replace && !opts.popstate) {
                root.history.pushState({ ratebNav: 1 }, '', pack.finalUrl);
            } else if (opts.replace) {
                root.history.replaceState({ ratebNav: 1 }, '', pack.finalUrl);
            }
            lastHref = pack.finalUrl;
            rememberExistingScripts();
            // Defer module script loads so paint wins (common libs already present).
            var afterScripts = function () {
                runLifecycle('afterEnter', {
                    href: pack.finalUrl,
                    ms: Math.round(performance.now() - t0),
                    fromCache: !!pack.fromCache
                });
                reinitModuleUi();
                bindPrefetch(curMain);
                if (pack.html && pack.html.length >= 20000 && !pack.fromCache) {
                    postSw({ type: 'CACHE_ERP_OPS_PAGE', url: pack.finalUrl, html: pack.html });
                }
                try {
                    performance.mark('rateb-nav-swap');
                    console.info('[RATEB NAV]', Math.round(performance.now() - t0) + 'ms',
                        pack.fromCache ? 'cache' : 'network', pack.finalUrl);
                } catch (eLog) { /* ignore */ }
                return true;
            };
            if (pack.fromCache) {
                // Instant path: schedule scripts idle
                var done = afterScripts();
                if (typeof root.requestIdleCallback === 'function') {
                    root.requestIdleCallback(function () { loadNewScripts(doc); }, { timeout: 1500 });
                } else {
                    setTimeout(function () { loadNewScripts(doc); }, 0);
                }
                return Promise.resolve(done);
            }
            return loadNewScripts(doc).then(afterScripts);
        }).catch(function (err) {
            try {
                console.warn('[RATEB NAV] fallback', err && err.message);
            } catch (eW) { /* ignore */ }
            hardNavigate(href);
            navigating = false;
            return false;
        }).then(function (ok) {
            navigating = false;
            return ok;
        });
    }

    function shouldIntercept(a, ev) {
        if (!a || !a.href) {
            return false;
        }
        if (ev.defaultPrevented || ev.button !== 0 || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) {
            return false;
        }
        if (a.target && a.target !== '' && a.target !== '_self') {
            return false;
        }
        if (a.hasAttribute('download') || a.getAttribute('data-rateb-full-nav') === '1') {
            return false;
        }
        try {
            var u = new URL(a.href, root.location.href);
            if (u.origin !== root.location.origin) {
                return false;
            }
            if (!ADMIN_PATH_RE.test(u.pathname)) {
                return false;
            }
            if (POS_PATH_RE.test(u.pathname)) {
                return false;
            }
            // Full document navigation required (session end / auth pages).
            if (/\/(logout|login|password)(\/|$)/i.test(u.pathname)) {
                return false;
            }
            if (u.pathname === root.location.pathname && u.search === root.location.search) {
                return false;
            }
            if (!document.querySelector('#rateb-sidebar, .rateb-sidebar')) {
                return false;
            }
            return true;
        } catch (e) {
            return false;
        }
    }

    function onClick(ev) {
        var a = ev.target && ev.target.closest ? ev.target.closest('a[href]') : null;
        if (!shouldIntercept(a, ev)) {
            return;
        }
        // Soft/hard offline: never enter content-swap (fetch can hang while badge says offline).
        // Full navigation lets the Service Worker serve the cached ops page.
        if (isUiOffline()) {
            // Do not preventDefault until we commit navigation — assign is synchronous intent.
            ev.preventDefault();
            hardNavigate(a.href);
            return;
        }
        ev.preventDefault();
        swapTo(a.href);
    }

    function onPopState() {
        if (!document.querySelector('#rateb-sidebar, .rateb-sidebar')) {
            return;
        }
        swapTo(root.location.href, { popstate: true });
    }

    // Public lifecycle registry — modules register once
    root.RatebModuleLifecycle = root.RatebModuleLifecycle || {
        _hooks: { beforeLeave: [], afterEnter: [] },
        on: function (ev, fn) {
            if (this._hooks[ev]) {
                this._hooks[ev].push(fn);
            }
        },
        beforeLeave: function (detail) {
            (this._hooks.beforeLeave || []).forEach(function (fn) {
                try { fn(detail); } catch (e) { /* ignore */ }
            });
        },
        afterEnter: function (detail) {
            (this._hooks.afterEnter || []).forEach(function (fn) {
                try { fn(detail); } catch (e) { /* ignore */ }
            });
        }
    };

    root.RatebNavInstant = {
        prefetch: prefetchUrl,
        navigate: swapTo,
        bindPrefetch: bindPrefetch
    };

    function boot() {
        rememberExistingScripts();
        bindPrefetch(document);
        document.addEventListener('click', onClick, true);
        root.addEventListener('popstate', onPopState);
        lastHref = root.location.href;
        /* PERF-P3 — idlePrefetchVisible self-delays; do not race first paint. */
        idlePrefetchVisible();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})(typeof window !== 'undefined' ? window : this);
