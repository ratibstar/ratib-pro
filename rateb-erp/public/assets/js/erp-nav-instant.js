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

    var COMMON_SCRIPT_RE = /\/assets\/(?:js\/(?:theme|connectivity-indicator|lang|rateb-modal|rateb-confirm|app|rateb-console-quiet|module-page-stats)|offline\/(?:erp-offline-tenant-context|erp-pwa-install|erp-nav-instant|erp-offline-full-warm|erp-offline-nav-guard)|vendor\/bootstrap)\//i;
    var POS_PATH_RE = /\/(?:admin\/ops\/)?pos(\/register)?(\/|$|\?)/i;
    var ADMIN_PATH_RE = /\/admin(\/|$)/i;
    /** Must match pos-sw.js ERP_OPS_PAGE_CACHE (v36). Older names kept as read fallbacks. */
    var OPS_PAGE_CACHE = 'rateb-erp-ops-pages-v36';
    var OPS_PAGE_CACHE_FALLBACKS = ['rateb-erp-ops-pages-v35', 'rateb-erp-ops-pages-v34'];
    var OPS_COEXIST_CACHE = 'rateb-erp-coexist-v34';
    var loadedScripts = Object.create(null);
    var navigating = false;
    var pendingNavHref = '';
    var inflightAbort = null;
    var prefetchSeen = Object.create(null);
    var prefetchQueue = [];
    var prefetchInFlight = 0;
    var PREFETCH_MAX_PARALLEL = 1;
    var idlePrefetchUnlocked = false;
    var lastHref = '';

    /**
     * Soft-nav only replaces #rateb-main-content. Bootstrap modals are moved onto
     * document.body (rateb-modal.js) so .modal-backdrop survives the swap and sits
     * above the sidebar (z-index 1050 > 1000) — icons look dead after a few pages.
     */
    function cleanupSoftNavUiArtifacts() {
        try {
            document.querySelectorAll('.modal-backdrop, .offcanvas-backdrop').forEach(function (el) {
                el.remove();
            });
            document.body.classList.remove('modal-open', 'offcanvas-open');
            document.body.style.removeProperty('overflow');
            document.body.style.removeProperty('padding-right');
            document.querySelectorAll('.modal.show').forEach(function (modal) {
                modal.classList.remove('show');
                modal.style.display = 'none';
                modal.setAttribute('aria-hidden', 'true');
                modal.removeAttribute('aria-modal');
                try {
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        var inst = bootstrap.Modal.getInstance(modal);
                        // dispose — do not hide() (hide can recreate .modal-backdrop).
                        if (inst && typeof inst.dispose === 'function') {
                            inst.dispose();
                        }
                    }
                } catch (eHide) { /* ignore */ }
            });
            // Second pass: hide() races or nested modals can leave another backdrop.
            document.querySelectorAll('.modal-backdrop, .offcanvas-backdrop').forEach(function (el) {
                el.remove();
            });
            document.body.classList.remove('modal-open', 'offcanvas-open');
        } catch (eClean) { /* ignore */ }
    }

    /** Defer Cache API + SW postMessage so soft-nav paint is not blocked by multi-MB HTML copies. */
    function schedulePageCache(href, html) {
        if (!html || html.length < 20000) {
            return;
        }
        var run = function () {
            try {
                putHtmlLocally(href, html);
            } catch (ePut) { /* ignore */ }
            try {
                postSw({ type: 'CACHE_ERP_OPS_PAGE', url: href, html: html });
            } catch (eSw) { /* ignore */ }
        };
        if (typeof root.requestIdleCallback === 'function') {
            root.requestIdleCallback(run, { timeout: 8000 });
        } else {
            root.setTimeout(run, 1200);
        }
    }

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
            // Do NOT compare company_id across inline scripts — first match is fragile and
            // forces hardNavigate (full reload / spinner) when switching companies via soft-nav.
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

    var lastSoftNavMissHref = '';

    function hasSwController() {
        try {
            return !!(navigator.serviceWorker && navigator.serviceWorker.controller);
        } catch (e) {
            return false;
        }
    }

    function hardNavigate(href) {
        // Soft-nav miss fallback — full assign so SW can paint cache/shell offline.
        // Only skip when offline AND no controller (Chrome interstitial risk).
        try {
            if (!href) {
                return;
            }
            if (isUiOffline() && !hasSwController()) {
                try {
                    showSoftNavMissToast(href);
                } catch (eToast) { /* ignore */ }
                return;
            }
            root.location.href = href;
        } catch (eHn) {
            try {
                console.warn('[RATEB NAV] hardNavigate failed', href, eHn);
            } catch (eLog) { /* ignore */ }
        }
    }

    function isBareAdminHref(href) {
        try {
            var p = new URL(href, root.location.href).pathname.replace(/\/+$/, '');
            return /\/admin$/i.test(p);
        } catch (eBa) {
            return false;
        }
    }

    /** Heavy PHP pages: soft-nav must wait longer than the default 1.4s ceiling. */
    function isHeavyNavHref(href) {
        try {
            var p = new URL(href, root.location.href).pathname.toLowerCase();
            return /\/access-control\/matrix(?:\/|$)/.test(p)
                || /\/chart-of-accounts(?:\/|$)/.test(p)
                || /\/company-permissions(?:\/|$)/.test(p)
                || /\/roles(?:\/|$)/.test(p)
                || /\/permissions(?:\/|$)/.test(p)
                || /\/audit(?:\/|$)/.test(p)
                || /\/oversight(?:\/|$)/.test(p)
                // HR module lists are tenant-scoped PHP CRUD — same timeout class as oversight.
                || /\/hr(?:\/|$)/.test(p);
        } catch (eH) {
            return false;
        }
    }

    function ensureAgentAppsCss(href) {
        try {
            if (!/\/admin\/(?:ops\/)?(?:agent-apps|mobile-apps)(?:\/|$|\?)/i.test(String(href || ''))) {
                return;
            }
            if (document.getElementById('rateb-agent-apps-css')
                || document.querySelector('link[href*="agent-apps.css"]')) {
                return;
            }
            var map = root.__RATEB_MODULE_CSS__ || {};
            var hrefCss = map.agentApps || '';
            if (!hrefCss) {
                return;
            }
            var link = document.createElement('link');
            link.id = 'rateb-agent-apps-css';
            link.rel = 'stylesheet';
            link.href = hrefCss;
            document.head.appendChild(link);
        } catch (eCss) { /* ignore */ }
    }

    function fetchWithTimeout(url, opts, ms) {
        opts = opts || {};
        var timedOut = false;
        var timer = null;
        var ctrl = null;
        var fetchOpts = opts;
        try {
            if (typeof AbortController !== 'undefined' && !opts.signal) {
                ctrl = new AbortController();
                fetchOpts = {};
                for (var k in opts) {
                    if (Object.prototype.hasOwnProperty.call(opts, k)) {
                        fetchOpts[k] = opts[k];
                    }
                }
                fetchOpts.signal = ctrl.signal;
            }
        } catch (eCtrl) {
            fetchOpts = opts;
            ctrl = null;
        }
        var timed = new Promise(function (_, reject) {
            timer = setTimeout(function () {
                timedOut = true;
                try {
                    if (ctrl) {
                        ctrl.abort();
                    }
                } catch (eAb) { /* ignore */ }
                reject(new Error('nav_fetch_timeout'));
            }, typeof ms === 'number' ? ms : 2000);
        });
        var network = fetch(url, fetchOpts).then(function (res) {
            if (timer) {
                clearTimeout(timer);
            }
            if (timedOut) {
                throw new Error('nav_fetch_timeout');
            }
            return res;
        });
        var raced = Promise.race([network, timed]);
        raced._ratebAbort = function () {
            try {
                if (ctrl) {
                    ctrl.abort();
                }
            } catch (e2) { /* ignore */ }
            if (timer) {
                clearTimeout(timer);
            }
        };
        return raced;
    }

    function loadNewScripts(doc) {
        var nodes = doc.querySelectorAll('script[src]');
        var tasks = [];
        Array.prototype.forEach.call(nodes, function (s) {
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
            tasks.push(new Promise(function (resolve) {
                var el = document.createElement('script');
                var done = false;
                var finish = function () {
                    if (done) {
                        return;
                    }
                    done = true;
                    loadedScripts[key] = true;
                    resolve();
                };
                el.src = src;
                el.async = true;
                el.onload = el.onerror = finish;
                // Soft-offline: hanging script fetch must not stall UI forever.
                root.setTimeout(finish, isUiOffline() ? 800 : 2000);
                (document.body || document.documentElement).appendChild(el);
            }));
        });
        return tasks.length ? Promise.all(tasks) : Promise.resolve();
    }

    function scheduleModuleScripts(doc) {
        // Offline / soft-offline: never inject module scripts — each page added hung <script>
        // fetches that jammed the tab after a few navigations (sidebar stopped accepting clicks).
        if (isUiOffline()) {
            return;
        }
        // Paint first; start module scripts on next task (not idle — keeps forms interactive).
        var kick = function () {
            try {
                loadNewScripts(doc);
            } catch (eLoad) { /* ignore */ }
        };
        if (typeof root.setTimeout === 'function') {
            root.setTimeout(kick, 0);
        } else {
            kick();
        }
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

    function clearPrefetchQueue() {
        prefetchQueue = [];
    }

    function runPrefetchQueue() {
        // Offline / soft-offline: never start hanging HTML fetches (starves every click).
        if (isUiOffline()) {
            clearPrefetchQueue();
            return;
        }
        while (prefetchInFlight < PREFETCH_MAX_PARALLEL && prefetchQueue.length) {
            var href = prefetchQueue.shift();
            prefetchInFlight += 1;
            postSw({ type: 'PREFETCH_ERP_OPS_URL', url: href });
            try {
                fetchWithTimeout(href, {
                    credentials: 'same-origin',
                    headers: { Accept: 'text/html', 'X-Rateb-Prefetch': '1' }
                }, 1500).then(function (res) {
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
                    if (isUiOffline()) {
                        clearPrefetchQueue();
                        return;
                    }
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
        if (isUiOffline()) {
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
        // Prefer sidebar / swapped main only — never whole-document a[href*="/admin"].
        var scope = rootEl;
        if (!scope || scope === document) {
            scope = document.getElementById('rateb-sidebar') || document;
        }
        scope.querySelectorAll('a.rateb-nav-link[href]').forEach(function (a) {
            if (a.__ratebPrefetchBound) {
                return;
            }
            a.__ratebPrefetchBound = true;
            var go = function () {
                try {
                    if (document.visibilityState && document.visibilityState !== 'visible') {
                        return;
                    }
                    var u = new URL(a.href, root.location.href);
                    if (u.origin !== root.location.origin) {
                        return;
                    }
                    if (!ADMIN_PATH_RE.test(u.pathname) || POS_PATH_RE.test(u.pathname)) {
                        return;
                    }
                    /* Hover لوحة التحكم: always warm — soft-nav must match F5 cache hit. */
                    var bare = String(u.pathname || '').replace(/\/+$/, '');
                    if (/\/admin$/i.test(bare)) {
                        prefetchUrl(u.href, { force: true });
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
                try {
                    if (document.visibilityState && document.visibilityState !== 'visible') {
                        return;
                    }
                } catch (eVis) { /* ignore */ }
                idlePrefetchUnlocked = true;
                var side = document.getElementById('rateb-sidebar') || document;
                var links = side.querySelectorAll('a.rateb-nav-link[href]');
                if (!links.length) {
                    return;
                }
                // Warm لوحة التحكم once — soft-nav was cold while F5 used SW navigate cache.
                var dashHref = '';
                var currentFirst = [];
                Array.prototype.forEach.call(links, function (a) {
                    try {
                        var u = new URL(a.href, root.location.href);
                        if (!ADMIN_PATH_RE.test(u.pathname) || POS_PATH_RE.test(u.pathname)) {
                            return;
                        }
                        var bare = String(u.pathname || '').replace(/\/+$/, '');
                        if (/\/admin$/i.test(bare) && !dashHref) {
                            dashHref = u.href;
                        }
                        if (isDeferredPrefetchPath(u.pathname)) {
                            return;
                        }
                        if (isCurrentModuleHref(u.href)) {
                            currentFirst.push(u.href);
                        }
                    } catch (e) { /* ignore */ }
                });
                if (dashHref) {
                    prefetchUrl(dashHref, { force: true });
                }
                /* At most one auto-prefetch besides dashboard; hover covers the rest. */
                currentFirst.slice(0, 1).forEach(function (href) {
                    prefetchUrl(href, { force: false });
                });
            };
            var kick = function () {
                if (window.requestIdleCallback) {
                    window.requestIdleCallback(unlockAndPrefetch, { timeout: 20000 });
                } else {
                    setTimeout(unlockAndPrefetch, 12000);
                }
            };
            /* Quiet window after first paint / early navigation. */
            setTimeout(kick, 12000);
        } catch (e3) { /* ignore */ }
    }

    function updateActiveNav(href) {
        try {
            var path = new URL(href, root.location.href).pathname.replace(/\/+$/, '');
            document.querySelectorAll('a.rateb-nav-link').forEach(function (a) {
                var ap = '';
                try {
                    ap = new URL(a.getAttribute('data-rateb-href') || a.href, root.location.href)
                        .pathname.replace(/\/+$/, '');
                } catch (e) { return; }
                var on = ap === path || (path.indexOf(ap) === 0 && ap.length > 10);
                a.classList.toggle('active', on);
                a.classList.toggle('is-nav-pending', on);
                if (on) {
                    // Expand parent groups so the active link is visible immediately.
                    var group = a.closest('.rateb-nav-group, .rateb-nav-subgroup');
                    while (group) {
                        group.classList.add('is-open');
                        var toggle = group.querySelector(':scope > [data-nav-group-toggle]');
                        if (toggle) {
                            toggle.setAttribute('aria-expanded', 'true');
                        }
                        group = group.parentElement
                            ? group.parentElement.closest('.rateb-nav-group, .rateb-nav-subgroup')
                            : null;
                    }
                }
            });
        } catch (e2) { /* ignore */ }
    }

    function clearNavPending() {
        try {
            document.querySelectorAll('a.rateb-nav-link.is-nav-pending').forEach(function (a) {
                a.classList.remove('is-nav-pending');
            });
        } catch (eC) { /* ignore */ }
    }

    function abortInflightNav() {
        if (typeof inflightAbort === 'function') {
            try { inflightAbort(); } catch (eA) { /* ignore */ }
        }
        inflightAbort = null;
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
        // Topbar <h1> lives outside #rateb-main-content — soft-nav must sync it or
        // the previous page title sticks (e.g. مقارنة الفروع on صلاحيات الشركات).
        try {
            var nextH1 = doc.querySelector('.rateb-topbar h1');
            var curH1 = document.querySelector('.rateb-topbar h1');
            if (nextH1 && curH1) {
                curH1.textContent = (nextH1.textContent || '').trim();
            }
        } catch (eH1) { /* ignore */ }
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

    function ensureDashboardCharts() {
        var rootDash = document.querySelector('[data-cm-dash][data-rateb-chartjs], [data-cm-dash="v5c"]');
        if (!rootDash || !document.querySelector('canvas[id^="chart-"]')) {
            return;
        }
        var chartjs = rootDash.getAttribute('data-rateb-chartjs') || '';
        var charts = rootDash.getAttribute('data-rateb-charts') || '';
        var deferSrc = '';
        try {
            if (charts) {
                deferSrc = String(charts).replace(/charts\.js/i, 'dashboard-charts-defer.js');
            }
        } catch (eD) { /* ignore */ }
        var load = function (src) {
            return new Promise(function (resolve) {
                if (!src) {
                    resolve();
                    return;
                }
                var key = scriptKey(src);
                if (loadedScripts[key] && (
                    (src.indexOf('dashboard-charts-defer') !== -1 && typeof root.ratebDashboardChartsBoot === 'function')
                    || (src.indexOf('charts.js') !== -1 && typeof root.ratebChartsBoot === 'function')
                    || (src.indexOf('chart') !== -1 && typeof root.Chart !== 'undefined')
                )) {
                    resolve();
                    return;
                }
                var el = document.createElement('script');
                el.src = src;
                el.async = true;
                el.onload = el.onerror = function () {
                    loadedScripts[key] = true;
                    resolve();
                };
                (document.body || document.documentElement).appendChild(el);
            });
        };
        load(chartjs).then(function () {
            return load(charts);
        }).then(function () {
            return load(deferSrc);
        }).then(function () {
            root.setTimeout(function () {
                try {
                    if (typeof root.ratebDashboardChartsBoot === 'function') {
                        root.ratebDashboardChartsBoot();
                    } else if (typeof root.ratebChartsBoot === 'function') {
                        root.ratebChartsBoot();
                    }
                } catch (eBoot) { /* ignore */ }
            }, 0);
        });
    }

    function reinitModuleUi() {
        // RatebApp.reinit runs once via rateb:nav:afterEnter — do not call it here (was double work).
        try {
            if (typeof root.RatebBootModulePageStats === 'function') {
                root.RatebBootModulePageStats();
            }
        } catch (eMetrics) { /* ignore */ }
        try {
            document.querySelectorAll('[data-module-metrics-async]').forEach(function (el) {
                if (el.getAttribute('data-rateb-metrics-loaded') === '1') {
                    return;
                }
                // module-page-stats listens for rateb:nav:enter / afterEnter
            });
        } catch (e2) { /* ignore */ }
        ensureDashboardCharts();
    }

    function setMainNavBusy(busy) {
        try {
            var main = document.querySelector('#rateb-main-content, main.rateb-content');
            if (!main) {
                return;
            }
            if (busy) {
                main.classList.add('is-nav-busy');
                main.setAttribute('aria-busy', 'true');
                // Never leave the whole page unclickable if a soft-nav hangs.
                root.clearTimeout(setMainNavBusy._clearTimer);
                setMainNavBusy._clearTimer = root.setTimeout(function () {
                    try {
                        main.classList.remove('is-nav-busy');
                        main.removeAttribute('aria-busy');
                    } catch (eC) { /* ignore */ }
                }, 3000);
            } else {
                root.clearTimeout(setMainNavBusy._clearTimer);
                main.classList.remove('is-nav-busy');
                main.removeAttribute('aria-busy');
            }
        } catch (eBusy) { /* ignore */ }
    }

    function openOpsCaches() {
        if (!root.caches) {
            return Promise.resolve([]);
        }
        // Primary = SW cache; fallbacks for devices that still hold older warm snapshots.
        var names = [OPS_PAGE_CACHE].concat(OPS_PAGE_CACHE_FALLBACKS).concat([OPS_COEXIST_CACHE]);
        return Promise.all(names.map(function (name) {
            return root.caches.open(name).catch(function () { return null; });
        })).then(function (opened) {
            return opened.filter(Boolean);
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
            // Exact pathname first (fast path) — full URL with query last.
            keys = [
                u.origin + u.pathname,
                u.origin + u.pathname.replace(/\/+$/, ''),
                u.origin + u.pathname.replace(/\/+$/, '') + '/',
                u.href,
                href
            ];
        } catch (e) { /* ignore */ }
        // Dedupe keys
        var seen = Object.create(null);
        keys = keys.filter(function (k) {
            if (!k || seen[k]) {
                return false;
            }
            seen[k] = true;
            return true;
        });
        // Offline: pathname + ignoreSearch first (warm stores bare paths; sidebar has ?company_id=).
        var offlineFast = isUiOffline();
        var primary = root.caches.open(OPS_PAGE_CACHE).then(function (cache) {
            if (offlineFast) {
                return cache.match(keys[0], { ignoreSearch: true }).then(function (hit) {
                    if (hit) {
                        return hit;
                    }
                    var chain = Promise.resolve(null);
                    keys.slice(0, 3).forEach(function (k) {
                        chain = chain.then(function (h) {
                            return h || cache.match(k);
                        });
                    });
                    return chain;
                });
            }
            var chain = Promise.resolve(null);
            keys.slice(0, 2).forEach(function (k) {
                chain = chain.then(function (hit) {
                    return hit || cache.match(k);
                });
            });
            return chain;
        }).catch(function () { return null; });

        return primary.then(function (fastHit) {
            if (fastHit) {
                return fastHit;
            }
            // Online miss on v36: try one older warm bucket (devices mid-upgrade).
            if (!offlineFast) {
                return root.caches.open(OPS_PAGE_CACHE_FALLBACKS[0]).then(function (cache) {
                    return cache.match(keys[0]).then(function (hit) {
                        return hit || (keys[1] ? cache.match(keys[1]) : null);
                    });
                }).catch(function () { return null; });
            }
            // One ignoreSearch on pathname only — skip coexist fan-out offline.
            return root.caches.open(OPS_PAGE_CACHE).then(function (cache) {
                return cache.match(keys[0], { ignoreSearch: true }).catch(function () { return null; });
            }).catch(function () { return null; });
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
        return root.caches.open(OPS_PAGE_CACHE).then(function (cache) {
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

    function fetchNetworkHtml(href) {
        // Fetch timeout is the only online backstop (do not race-abort at 1.4s — that broke sidebar tabs).
        var timeoutMs = isBareAdminHref(href) ? 10000 : (isHeavyNavHref(href) ? 20000 : 12000);
        var raw = fetchWithTimeout(href, {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'text/html', 'X-Rateb-Nav-Swap': '1' }
        }, timeoutMs);
        var packed = raw.then(function (res) {
            if (!res || !res.ok) {
                throw new Error('nav_fetch_failed');
            }
            return res.text().then(function (html) {
                // Idle cache — never block paint / next click on Cache+SW HTML copies.
                schedulePageCache(res.url || href, html);
                return { html: html, finalUrl: res.url || href, fromCache: false };
            });
        });
        packed._ratebAbort = raw._ratebAbort;
        return packed;
    }

    function fetchHtml(href) {
        // TRUE race: Cache API fan-out must NOT gate the network/SW response.
        // Old chain awaited matchCachedHtml before returning networkPromise — that made
        // لوحة التحكم soft-nav wait seconds while F5 painted from SW navigate cache.
        var networkPromise = null;
        if (!isUiOffline()) {
            networkPromise = fetchNetworkHtml(href);
        }

        var cachePromise = matchCachedHtml(href).then(function (cached) {
            if (!cached) {
                return null;
            }
            return cached.text().then(function (html) {
                if (!html || html.length < 200) {
                    return null;
                }
                return { html: html, finalUrl: href, fromCache: true };
            });
        }).catch(function () {
            return null;
        });

        // Online: never abort early — Cache vs Network race resolves when either wins.
        // The old 800–1400ms ceiling aborted good navigations (tabs looked broken; F5 felt fast).
        var ceilingMs = isUiOffline() ? 2000 : 0;
        return new Promise(function (resolve, reject) {
            var settled = false;
            var timer = null;
            if (ceilingMs > 0) {
                timer = root.setTimeout(function () {
                    if (settled) {
                        return;
                    }
                    settled = true;
                    if (networkPromise && typeof networkPromise._ratebAbort === 'function') {
                        try { networkPromise._ratebAbort(); } catch (eAb2) { /* ignore */ }
                    }
                    reject(new Error(isUiOffline() ? 'nav_offline_cache_timeout' : 'nav_online_timeout'));
                }, ceilingMs);
            }

            // Expose abort so a newer sidebar click can cancel this race.
            inflightAbort = function () {
                if (settled) {
                    return;
                }
                settled = true;
                if (timer) {
                    root.clearTimeout(timer);
                }
                if (networkPromise && typeof networkPromise._ratebAbort === 'function') {
                    try { networkPromise._ratebAbort(); } catch (eAb3) { /* ignore */ }
                }
                reject(new Error('nav_superseded'));
            };

            function win(pack) {
                if (settled || !pack || !pack.html) {
                    return;
                }
                settled = true;
                if (timer) {
                    root.clearTimeout(timer);
                }
                if (pack.fromCache && networkPromise && typeof networkPromise._ratebAbort === 'function') {
                    try { networkPromise._ratebAbort(); } catch (eAb) { /* ignore */ }
                    if (!isUiOffline()) {
                        var revalidate = function () {
                            try {
                                if (typeof navigator !== 'undefined' && navigator.onLine === false) {
                                    return;
                                }
                            } catch (eOff) { return; }
                            fetchNetworkHtml(href).catch(function () { /* ignore */ });
                        };
                        if (typeof root.requestIdleCallback === 'function') {
                            root.requestIdleCallback(revalidate, { timeout: 6000 });
                        } else {
                            root.setTimeout(revalidate, 2500);
                        }
                    }
                }
                resolve(pack);
            }

            function failIfBothDone(cachePack, netErr) {
                if (settled) {
                    return;
                }
                // Wait until cache settled; if network already failed and cache empty → reject.
                if (cachePack === null && netErr && !networkPromise) {
                    settled = true;
                    root.clearTimeout(timer);
                    reject(netErr || new Error('nav_offline_no_cache'));
                }
            }

            cachePromise.then(function (pack) {
                if (pack) {
                    win(pack);
                    return;
                }
                // Cache miss: if network already failed, reject; else wait for network/ceiling.
                if (!networkPromise) {
                    if (!settled) {
                        settled = true;
                        root.clearTimeout(timer);
                        reject(new Error('nav_offline_no_cache'));
                    }
                }
            });

            if (networkPromise) {
                networkPromise.then(function (pack) {
                    win(pack);
                }, function (err) {
                    cachePromise.then(function (pack) {
                        if (pack) {
                            win(pack);
                            return;
                        }
                        if (!settled) {
                            settled = true;
                            root.clearTimeout(timer);
                            reject(err || new Error('nav_fetch_failed'));
                        }
                    });
                    failIfBothDone(null, err);
                });
            }
        });
    }

    function showOfflineMissToast() {
        showNavToast('الصفحة غير محفوظة أوفلاين — افتحها مرة وأنت متصل.', true);
    }

    function showSoftNavMissToast(href) {
        showNavToast('تعذر فتح الصفحة فوراً — بقيت على الشاشة الحالية. اضغط مرة أخرى.', false);
        void href;
    }

    function showNavToast(msg, isErr) {
        try {
            var t = document.getElementById('rateb-offline-nav-toast');
            if (!t && document.body) {
                t = document.createElement('div');
                t.id = 'rateb-offline-nav-toast';
                t.setAttribute('role', 'status');
                t.style.cssText = 'position:fixed;bottom:4.5rem;left:50%;transform:translateX(-50%);z-index:100000;'
                    + 'background:#7f1d1d;color:#fecaca;padding:.65rem 1rem;border-radius:8px;'
                    + 'font:13px/1.4 system-ui,sans-serif;max-width:90vw;text-align:center;'
                    + 'pointer-events:none;';
                document.body.appendChild(t);
            }
            if (t) {
                t.className = isErr ? 'is-err' : 'is-warn';
                if (!isErr) {
                    t.style.background = '#1e3a5f';
                    t.style.color = '#e8eaed';
                } else {
                    t.style.background = '#7f1d1d';
                    t.style.color = '#fecaca';
                }
                t.textContent = msg;
                t.hidden = false;
                clearTimeout(showNavToast._h);
                showNavToast._h = root.setTimeout(function () {
                    try { t.hidden = true; } catch (eH) { /* ignore */ }
                }, 3500);
            }
        } catch (eT) { /* ignore */ }
    }

    function swapTo(href, opts) {
        opts = opts || {};
        if (navigating) {
            // Latest click wins: optimistic chrome + supersede in-flight fetch immediately.
            pendingNavHref = href;
            updateActiveNav(href);
            setMainNavBusy(true);
            abortInflightNav();
            swapTo._gen = (swapTo._gen || 0) + 1;
            navigating = false;
            drainPendingNav();
            return Promise.resolve(false);
        }
        navigating = true;
        pendingNavHref = '';
        var navGen = (swapTo._gen = (swapTo._gen || 0) + 1);
        // Safety unlock aligned with fetch timeout (was shorter → stuck chrome + dead clicks).
        var unlockMs = isUiOffline()
            ? 400
            : (isBareAdminHref(href) ? 11000 : (isHeavyNavHref(href) ? 21000 : 13000));
        var unlockTimer = root.setTimeout(function () {
            if (navigating && swapTo._gen === navGen) {
                navigating = false;
                setMainNavBusy(false);
                clearNavPending();
                try {
                    console.warn('[RATEB NAV] unlock stuck navigating');
                } catch (eU) { /* ignore */ }
                drainPendingNav();
            }
        }, unlockMs);
        var t0 = performance.now();
        cleanupSoftNavUiArtifacts();
        // Instant feedback — do not wait for HTML fetch.
        updateActiveNav(href);
        setMainNavBusy(true);
        clearPrefetchQueue();
        try {
            runLifecycle('beforeLeave', { href: root.location.href, next: href });
        } catch (eLeave) { /* ignore */ }

        return fetchHtml(href).then(function (pack) {
            // Stale generation — a newer click won; do not paint or relock.
            if (swapTo._gen !== navGen) {
                return false;
            }
            var doc = new DOMParser().parseFromString(pack.html, 'text/html');
            if (!sameShell(doc)) {
                throw new Error('shell_mismatch');
            }
            var nextMain = doc.querySelector('#rateb-main-content, main.rateb-content');
            var curMain = document.querySelector('#rateb-main-content, main.rateb-content');
            if (!nextMain || !curMain) {
                throw new Error('missing_main');
            }
            cleanupSoftNavUiArtifacts();
            ensureAgentAppsCss(pack.finalUrl || href);
            curMain.innerHTML = nextMain.innerHTML;
            syncMeta(doc);
            updateActiveNav(pack.finalUrl);
            clearNavPending();
            setMainNavBusy(false);
            if (!opts.replace && !opts.popstate) {
                root.history.pushState({ ratebNav: 1 }, '', pack.finalUrl);
            } else if (opts.replace) {
                root.history.replaceState({ ratebNav: 1 }, '', pack.finalUrl);
            }
            lastHref = pack.finalUrl;
            lastSoftNavMissHref = '';
            rememberExistingScripts();
            // Defer module script loads so paint wins (common libs already present).
            var afterScripts = function () {
                if (swapTo._gen !== navGen) {
                    return false;
                }
                cleanupSoftNavUiArtifacts();
                runLifecycle('afterEnter', {
                    href: pack.finalUrl,
                    ms: Math.round(performance.now() - t0),
                    fromCache: !!pack.fromCache
                });
                reinitModuleUi();
                bindPrefetch(curMain);
                try {
                    performance.mark('rateb-nav-swap');
                    console.info('[RATEB NAV]', Math.round(performance.now() - t0) + 'ms',
                        pack.fromCache ? 'cache' : 'network', pack.finalUrl);
                } catch (eLog) { /* ignore */ }
                return true;
            };
            if (pack.fromCache) {
                var done = afterScripts();
                scheduleModuleScripts(doc);
                return Promise.resolve(done);
            }
            // Network path: paint immediately; module scripts next task (parallel).
            var painted = afterScripts();
            scheduleModuleScripts(doc);
            return Promise.resolve(painted);
        }).catch(function (err) {
            if (swapTo._gen !== navGen) {
                return false;
            }
            try {
                console.warn('[RATEB NAV] fallback', err && err.message);
            } catch (eW) { /* ignore */ }
            setMainNavBusy(false);
            clearNavPending();
            // Soft-nav miss: full navigation (SW serves warmed HTML / shell offline).
            if (!(err && err.message === 'nav_superseded')) {
                showSoftNavMissToast(href);
                lastSoftNavMissHref = '';
                hardNavigate(href);
            }
            return false;
        }).then(function (ok) {
            root.clearTimeout(unlockTimer);
            if (swapTo._gen === navGen) {
                navigating = false;
                inflightAbort = null;
            }
            drainPendingNav();
            return ok;
        });
    }

    function drainPendingNav() {
        if (!pendingNavHref || navigating) {
            return;
        }
        var next = pendingNavHref;
        pendingNavHref = '';
        if (!next || next === lastHref) {
            return;
        }
        root.setTimeout(function () {
            if (!navigating) {
                swapTo(next);
            } else {
                pendingNavHref = next;
            }
        }, 0);
    }

    function navHrefOf(a) {
        if (!a) {
            return '';
        }
        try {
            var raw = a.getAttribute('data-rateb-href') || a.getAttribute('href') || '';
            if (!raw || raw === '#' || raw.indexOf('javascript:') === 0) {
                return '';
            }
            return new URL(raw, root.location.href).href;
        } catch (eH) {
            return a.href || '';
        }
    }

    function shouldIntercept(a, ev) {
        if (!a) {
            return false;
        }
        var href = navHrefOf(a);
        if (!href) {
            return false;
        }
        if (ev.button !== 0 || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) {
            return false;
        }
        // Ignore defaultPrevented — onclick="return false" / early guards set it;
        // we still soft-navigate.
        if (a.target && a.target !== '' && a.target !== '_self') {
            return false;
        }
        if (a.hasAttribute('download') || a.getAttribute('data-rateb-full-nav') === '1') {
            return false;
        }
        try {
            var u = new URL(href, root.location.href);
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
            if (u.pathname.replace(/\/+$/, '') === root.location.pathname.replace(/\/+$/, '')
                && u.search === root.location.search) {
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
        var a = ev.target && ev.target.closest
            ? ev.target.closest('a[href], a[data-rateb-href], button[data-rateb-href], [data-rateb-dashboard-nav]')
            : null;
        if (!a) {
            return;
        }
        // Buttons never full-navigate; still soft-swap.
        var isBtn = a.tagName === 'BUTTON' || a.getAttribute('data-rateb-dashboard-nav') === '1';
        if (!isBtn && !shouldIntercept(a, ev)) {
            return;
        }
        if (isBtn) {
            if (ev.button !== 0 || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) {
                return;
            }
            var btnHref = navHrefOf(a);
            if (!btnHref) {
                return;
            }
            try {
                var bu = new URL(btnHref, root.location.href);
                if (bu.pathname.replace(/\/+$/, '') === root.location.pathname.replace(/\/+$/, '')
                    && bu.search === root.location.search) {
                    return;
                }
            } catch (eSame) { /* continue */ }
            ev.preventDefault();
            try { ev.stopImmediatePropagation(); } catch (eSip) { /* ignore */ }
            if (navigating) {
                pendingNavHref = btnHref;
                updateActiveNav(btnHref);
                setMainNavBusy(true);
                abortInflightNav();
                swapTo._gen = (swapTo._gen || 0) + 1;
                navigating = false;
                drainPendingNav();
                return;
            }
            swapTo(btnHref);
            return;
        }
        var href = navHrefOf(a);
        if (navigating) {
            ev.preventDefault();
            try { ev.stopImmediatePropagation(); } catch (eSip2) { /* ignore */ }
            pendingNavHref = href;
            updateActiveNav(href);
            setMainNavBusy(true);
            abortInflightNav();
            swapTo._gen = (swapTo._gen || 0) + 1;
            navigating = false;
            drainPendingNav();
            return;
        }
        ev.preventDefault();
        try { ev.stopImmediatePropagation(); } catch (eSip3) { /* ignore */ }
        swapTo(href);
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
        bindPrefetch: bindPrefetch,
        unlock: function () {
            navigating = false;
            pendingNavHref = '';
            cleanupSoftNavUiArtifacts();
            drainPendingNav();
        },
        isNavigating: function () { return !!navigating; }
    };

    function boot() {
        rememberExistingScripts();
        cleanupSoftNavUiArtifacts();
        bindPrefetch(document.getElementById('rateb-sidebar') || document);
        document.addEventListener('click', onClick, true);
        window.__RATEB_NAV_READY__ = true;
        root.addEventListener('popstate', onPopState);
        root.addEventListener('offline', function () {
            clearPrefetchQueue();
        });
        document.addEventListener('rateb-connection-badge', function (ev) {
            try {
                if (ev && ev.detail && ev.detail.online === false) {
                    clearPrefetchQueue();
                }
            } catch (eBadge) { /* ignore */ }
        });
        lastHref = root.location.href;
        /* Drain click held by early head interceptor (before this script loaded). */
        try {
            var pending = root.__RATEB_PENDING_NAV__ || '';
            root.__RATEB_PENDING_NAV__ = '';
            if (pending && pending !== lastHref) {
                root.setTimeout(function () {
                    swapTo(pending);
                }, 0);
            }
        } catch (ePend) { /* ignore */ }
        /* PERF-P3 — idlePrefetchVisible self-delays; do not race first paint. */
        if (!isUiOffline()) {
            idlePrefetchVisible();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})(typeof window !== 'undefined' ? window : this);
