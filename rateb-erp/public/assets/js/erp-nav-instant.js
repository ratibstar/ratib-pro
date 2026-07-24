/**
 * PERF-P3 / Fix4 — Instant ERP navigation (content-swap + gated prefetch).
 * Same tenant/session/shell only. Full reload fallback otherwise.
 * Prefetch: visible/high-pri only; max 1 concurrent; pause/abort on user nav.
 * Idle wave runs once; deferred paths (profile/notifications/catalog) never auto-prefetch.
 */
(function (root) {
    'use strict';

    if (root.__RATEB_NAV_INSTANT__) {
        return;
    }
    root.__RATEB_NAV_INSTANT__ = true;

    var COMMON_SCRIPT_RE = /\/assets\/(?:js\/(?:theme|connectivity-indicator|lang|rateb-modal|rateb-confirm|app|rateb-console-quiet|module-page-stats)|offline\/(?:erp-offline-tenant-context|erp-pwa-install|erp-nav-instant|erp-offline-full-warm|erp-offline-nav-guard)|vendor\/bootstrap)\//i;
    /** Selling shell + biometric — always full document load. */
    var POS_RUNTIME_RE = /\/(?:admin\/ops\/)?pos(?:\/register|\/biometric)?\/?(?:$|\?)/i;
    /** Prefetch filters + forceFull for selling shell only. */
    var POS_SHELL_RE = POS_RUNTIME_RE;
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
    /** Idle wave: dashboard + at most one visible current-module link. */
    var IDLE_PREFETCH_MAX_URLS = 2;
    var idlePrefetchUnlocked = false;
    var idlePrefetchScheduled = false;
    var idlePrefetchDone = false;
    var idlePrefetchCancelled = false;
    var prefetchPaused = false;
    var prefetchAbortFn = null;
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

    function isPosPagesShellBody(body) {
        return !!(body && /rateb-pos-shell--pages/i.test(body.className || ''));
    }

    function isOnPosPagesShell() {
        return isPosPagesShellBody(document.body);
    }

    function contentMainSel() {
        return '#rateb-pos-app, main.rateb-pos-pages-main, #rateb-main-content, main.rateb-content';
    }

    function sameShell(doc) {
        try {
            var curPosPages = isOnPosPagesShell();
            var nextPosPages = isPosPagesShellBody(doc.body);
            // POS pages shell ↔ POS pages shell: keep RATEB POS header, swap #rateb-pos-app.
            if (curPosPages || nextPosPages) {
                if (!(curPosPages && nextPosPages)) {
                    return false;
                }
                if (!doc.querySelector('#rateb-pos-app, main.rateb-pos-pages-main')) {
                    return false;
                }
                if (doc.querySelector('body[data-rateb-uncached-page], [data-rateb-uncached-page="1"]')) {
                    return false;
                }
                if (doc.querySelector('#login-form, form#password-form, [data-rateb-login]')) {
                    return false;
                }
                return true;
            }
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
            // POS register / biometric shell — never content-swap into it from ERP Admin.
            if (doc.body && /rateb-pos-shell/i.test(doc.body.className || '')) {
                return false;
            }
            if (doc.querySelector('[data-pos-register="1"]')) {
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

    /** Soft badge OR hard offline — UI hints only (toasts / skip non-critical inject). */
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

    /** Hard offline only — soft badge must NOT force cache-only soft-nav (spins online). */
    function isBrowserOffline() {
        try {
            return typeof navigator !== 'undefined' && navigator.onLine === false;
        } catch (e) {
            return false;
        }
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
        // Soft-nav miss → full assign. Block only when browser is offline and no SW.
        try {
            if (!href) {
                return;
            }
            if (isBrowserOffline() && !hasSwController()) {
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

    function loadNewScriptsFromCacheOnly(doc) {
        if (!root.caches) {
            return Promise.resolve();
        }
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
            var abs = src;
            try {
                abs = new URL(src, root.location.href).href;
            } catch (eAbs) { /* ignore */ }
            tasks.push(root.caches.match(abs, { ignoreSearch: true }).then(function (hit) {
                if (!hit || !hit.ok) {
                    return null;
                }
                return new Promise(function (resolve) {
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
                    el.src = abs;
                    el.async = true;
                    el.onload = el.onerror = finish;
                    root.setTimeout(finish, 700);
                    (document.body || document.documentElement).appendChild(el);
                });
            }).catch(function () {
                return null;
            }));
        });
        return tasks.length ? Promise.all(tasks) : Promise.resolve();
    }

    function scheduleModuleScripts(doc) {
        // Soft/hard offline: only inject scripts already in Cache Storage (no network hang).
        var kick = function () {
            try {
                if (isUiOffline()) {
                    loadNewScriptsFromCacheOnly(doc);
                } else {
                    loadNewScripts(doc);
                }
            } catch (eLoad) { /* ignore */ }
        };
        if (typeof root.setTimeout === 'function') {
            root.setTimeout(kick, 0);
        } else {
            kick();
        }
    }

    function rememberExistingScripts() {
        // Call only on full-page boot (scripts in main have executed via defer).
        // Never call after soft-nav swap — innerHTML leaves inert <script> tags that
        // would poison loadedScripts and skip scheduleModuleScripts (mail DNS, etc.).
        document.querySelectorAll('script[src]').forEach(function (s) {
            loadedScripts[scriptKey(s.src)] = true;
        });
    }

    /** Remove inert <script> nodes from a soft-nav fragment after paint. */
    function stripInertScripts(rootEl) {
        if (!rootEl || !rootEl.querySelectorAll) {
            return;
        }
        try {
            Array.prototype.forEach.call(rootEl.querySelectorAll('script'), function (s) {
                if (s.parentNode) {
                    s.parentNode.removeChild(s);
                }
            });
        } catch (eStrip) { /* ignore */ }
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

    function abortPrefetchInflight() {
        if (typeof prefetchAbortFn === 'function') {
            try { prefetchAbortFn(); } catch (eAb) { /* ignore */ }
        }
        prefetchAbortFn = null;
    }

    /** Pause queue + abort in-flight prefetch so user nav owns the network. */
    function pausePrefetch() {
        prefetchPaused = true;
        clearPrefetchQueue();
        abortPrefetchInflight();
    }

    function resumePrefetch() {
        prefetchPaused = false;
    }

    function isPrefetchBlocked() {
        return !!(navigating || prefetchPaused || isUiOffline());
    }

    function isNavLinkVisible(el) {
        try {
            if (!el || !el.getBoundingClientRect) {
                return false;
            }
            var r = el.getBoundingClientRect();
            if (r.width < 2 || r.height < 2) {
                return false;
            }
            var vh = root.innerHeight || document.documentElement.clientHeight || 0;
            var vw = root.innerWidth || document.documentElement.clientWidth || 0;
            return r.bottom > 0 && r.top < vh && r.right > 0 && r.left < vw;
        } catch (eVis) {
            return false;
        }
    }

    function runPrefetchQueue() {
        // Offline / soft-offline: never start hanging HTML fetches (starves every click).
        if (isPrefetchBlocked()) {
            clearPrefetchQueue();
            return;
        }
        while (prefetchInFlight < PREFETCH_MAX_PARALLEL && prefetchQueue.length) {
            if (isPrefetchBlocked()) {
                clearPrefetchQueue();
                return;
            }
            var href = prefetchQueue.shift();
            prefetchInFlight += 1;
            postSw({ type: 'PREFETCH_ERP_OPS_URL', url: href });
            try {
                var raw = fetchWithTimeout(href, {
                    credentials: 'same-origin',
                    headers: { Accept: 'text/html', 'X-Rateb-Prefetch': '1' }
                }, 1500);
                prefetchAbortFn = typeof raw._ratebAbort === 'function' ? raw._ratebAbort : null;
                raw.then(function (res) {
                    if (isPrefetchBlocked()) {
                        return null;
                    }
                    if (!res || !res.ok) {
                        return null;
                    }
                    return res.text().then(function (html) {
                        if (isPrefetchBlocked()) {
                            return;
                        }
                        if (html && html.length >= 20000) {
                            // Same cache strategy as before (SW + local Cache API).
                            postSw({ type: 'CACHE_ERP_OPS_PAGE', url: href, html: html });
                            try {
                                putHtmlLocally(href, html);
                            } catch (ePut) { /* ignore */ }
                        }
                    });
                }).catch(function () { /* ignore */ }).then(function () {
                    prefetchAbortFn = null;
                    prefetchInFlight = Math.max(0, prefetchInFlight - 1);
                    if (isPrefetchBlocked()) {
                        clearPrefetchQueue();
                        return;
                    }
                    runPrefetchQueue();
                });
            } catch (e2) {
                prefetchAbortFn = null;
                prefetchInFlight = Math.max(0, prefetchInFlight - 1);
            }
        }
    }

    function prefetchUrl(href, opts) {
        if (!href || prefetchSeen[href]) {
            return;
        }
        if (isPrefetchBlocked()) {
            return;
        }
        opts = opts || {};
        try {
            var u = new URL(href, root.location.href);
            if (u.origin !== root.location.origin) {
                return;
            }
            if (!ADMIN_PATH_RE.test(u.pathname) || POS_SHELL_RE.test(u.pathname)) {
                return;
            }
            if (!opts.force) {
                // Deferred paths (profile/notifications/catalog/bare admin) never auto-prefetch —
                // only explicit force (idle dashboard warm or hover لوحة التحكم).
                if (isDeferredPrefetchPath(u.pathname)) {
                    return;
                }
                if (!idlePrefetchUnlocked) {
                    if (!isCurrentModuleHref(href)) {
                        return;
                    }
                } else if (!isCurrentModuleHref(href) && !opts.allowOther) {
                    return;
                }
            }
        } catch (eGate) {
            return;
        }
        prefetchSeen[href] = true;
        /* PERF Fix4: max 1 concurrent prefetch; queue drains only when not navigating. */
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
                    if (isPrefetchBlocked()) {
                        return;
                    }
                    var u = new URL(a.href, root.location.href);
                    if (u.origin !== root.location.origin) {
                        return;
                    }
                    if (!ADMIN_PATH_RE.test(u.pathname) || POS_SHELL_RE.test(u.pathname)) {
                        return;
                    }
                    /* Hover لوحة التحكم: always warm — soft-nav must match F5 cache hit. */
                    var bare = String(u.pathname || '').replace(/\/+$/, '');
                    if (/\/admin$/i.test(bare)) {
                        prefetchUrl(u.href, { force: true });
                        return;
                    }
                    /* Hover intent: only current module; deferred paths blocked without force. */
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
            /* PERF Fix4: single idle wave; cancel on interaction; visible high-pri only. */
            if (idlePrefetchScheduled) {
                return;
            }
            idlePrefetchScheduled = true;

            var onInteract = function () {
                /* Cancel pending idle wave + abort any in-flight idle fetch.
                 * Do NOT leave prefetchPaused stuck — hover warm must still work. */
                idlePrefetchCancelled = true;
                clearPrefetchQueue();
                abortPrefetchInflight();
            };
            ['pointerdown', 'keydown', 'touchstart', 'wheel'].forEach(function (ev) {
                root.addEventListener(ev, onInteract, { once: true, passive: true, capture: true });
            });

            var unlockAndPrefetch = function () {
                if (idlePrefetchDone || idlePrefetchCancelled) {
                    return;
                }
                try {
                    if (document.visibilityState && document.visibilityState !== 'visible') {
                        return;
                    }
                } catch (eVis) { /* ignore */ }
                if (navigating || prefetchPaused) {
                    // User already navigating — skip the idle wave entirely (no second wave later).
                    idlePrefetchDone = true;
                    idlePrefetchUnlocked = true;
                    resumePrefetch();
                    return;
                }
                idlePrefetchDone = true;
                idlePrefetchUnlocked = true;

                var side = document.getElementById('rateb-sidebar') || document;
                var links = side.querySelectorAll('a.rateb-nav-link[href]');
                if (!links.length) {
                    resumePrefetch();
                    return;
                }
                var dashHref = '';
                var visibleModule = [];
                Array.prototype.forEach.call(links, function (a) {
                    try {
                        if (!isNavLinkVisible(a)) {
                            return;
                        }
                        var u = new URL(a.href, root.location.href);
                        if (!ADMIN_PATH_RE.test(u.pathname) || POS_SHELL_RE.test(u.pathname)) {
                            return;
                        }
                        var bare = String(u.pathname || '').replace(/\/+$/, '');
                        if (/\/admin$/i.test(bare) && !dashHref) {
                            dashHref = u.href;
                            return;
                        }
                        if (isDeferredPrefetchPath(u.pathname)) {
                            return;
                        }
                        if (isCurrentModuleHref(u.href)) {
                            visibleModule.push(u.href);
                        }
                    } catch (e) { /* ignore */ }
                });

                var budget = IDLE_PREFETCH_MAX_URLS;
                if (dashHref && budget > 0) {
                    prefetchUrl(dashHref, { force: true });
                    budget -= 1;
                }
                /* At most one visible current-module auto-prefetch; hover covers the rest. */
                if (budget > 0 && visibleModule.length) {
                    prefetchUrl(visibleModule[0], { force: false });
                }
            };

            var kick = function () {
                if (idlePrefetchCancelled || idlePrefetchDone) {
                    return;
                }
                if (window.requestIdleCallback) {
                    window.requestIdleCallback(unlockAndPrefetch, { timeout: 8000 });
                } else {
                    setTimeout(unlockAndPrefetch, 4000);
                }
            };
            /* Quiet window after first paint — do not race early navigations. */
            setTimeout(kick, 10000);
        } catch (e3) { /* ignore */ }
    }

    function navLabelForHref(href) {
        try {
            var path = new URL(href, root.location.href).pathname.replace(/\/+$/, '');
            var best = '';
            var bestLen = 0;
            document.querySelectorAll('a.rateb-nav-link').forEach(function (a) {
                var ap = '';
                var text = '';
                try {
                    ap = new URL(a.getAttribute('data-rateb-href') || a.href, root.location.href)
                        .pathname.replace(/\/+$/, '');
                    text = (a.textContent || '').replace(/\s+/g, ' ').trim();
                } catch (e) { return; }
                if (!ap || !text) {
                    return;
                }
                if (ap === path || (path.indexOf(ap) === 0 && ap.length > bestLen)) {
                    best = text;
                    bestLen = ap.length;
                }
            });
            return best;
        } catch (e2) {
            return '';
        }
    }

    /**
     * Offline cache miss: still "open" the destination inside the live Admin shell
     * (sidebar stays). Real HTML is filled later when online warm completes.
     */
    function paintOfflinePageStub(href, opts) {
        opts = opts || {};
        var curMain = document.querySelector(contentMainSel());
        if (!curMain) {
            return false;
        }
        var label = navLabelForHref(href) || 'هذه الصفحة';
        var path = '';
        try {
            path = new URL(href, root.location.href).pathname;
        } catch (eP) {
            path = String(href || '');
        }
        var titleEl = document.querySelector('.rateb-topbar h1');
        if (titleEl) {
            titleEl.textContent = label;
        }
        try {
            document.title = label + ' | نظام رتب ERP';
        } catch (eT) { /* ignore */ }
        curMain.innerHTML = ''
            + '<div class="container-fluid py-4" data-rateb-offline-stub="1" data-rateb-offline-stub-path="'
            + String(path).replace(/"/g, '&quot;') + '">'
            + '<div class="rateb-card p-4" style="max-width:40rem;margin:0 auto;text-align:center">'
            + '<div class="mb-3" style="font-size:2rem;opacity:.85"><i class="fas fa-cloud-moon"></i></div>'
            + '<h2 class="h4 mb-2">' + String(label).replace(/</g, '&lt;') + '</h2>'
            + '<p class="text-muted mb-3" style="line-height:1.6">'
            + 'فتحت الصفحة أوفلاين داخل النظام. النسخة الكاملة والبيانات تُحمَّل تلقائياً '
            + 'عند الاتصال (أو بعد اكتمال التسخين وأنت متصل).'
            + '</p>'
            + '<p class="small text-muted mb-0" dir="ltr" style="opacity:.7">' + String(path).replace(/</g, '&lt;') + '</p>'
            + '</div></div>';
        updateActiveNav(href);
        clearNavPending();
        if (!opts.replace && !opts.popstate) {
            try {
                root.history.pushState({ ratebNav: 1, ratebOfflineStub: 1 }, '', href);
            } catch (eH) { /* ignore */ }
        } else if (opts.replace) {
            try {
                root.history.replaceState({ ratebNav: 1, ratebOfflineStub: 1 }, '', href);
            } catch (eR) { /* ignore */ }
        }
        lastHref = href;
        lastSoftNavMissHref = '';
        try {
            runLifecycle('afterEnter', { href: href, fromCache: false, offlineStub: true });
        } catch (eLife) { /* ignore */ }
        showNavToast('أوفلاين: «' + label + '» مفتوحة — البيانات الكاملة بعد الاتصال.', false);
        return true;
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
        // POS pages shell: title + header nav live outside #rateb-pos-app.
        try {
            var nextPosTitle = doc.querySelector('.rateb-pos__pages-title');
            var curPosTitle = document.querySelector('.rateb-pos__pages-title');
            if (nextPosTitle && curPosTitle) {
                curPosTitle.textContent = (nextPosTitle.textContent || '').trim();
            }
            var nextPosNav = doc.querySelector('.rateb-pos__pages-nav');
            var curPosNav = document.querySelector('.rateb-pos__pages-nav');
            if (nextPosNav && curPosNav) {
                curPosNav.innerHTML = nextPosNav.innerHTML;
            }
        } catch (ePosH) { /* ignore */ }
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
        // Soft "offline" badge must not block chart hydrate (left shimmer forever).
        var run = function () {
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
        };
        /* Fix8: after soft-nav paint — do not compete with swap/click handlers. */
        if (typeof root.requestAnimationFrame === 'function') {
            root.requestAnimationFrame(function () {
                if (typeof root.requestIdleCallback === 'function') {
                    root.requestIdleCallback(run, { timeout: 1200 });
                } else {
                    root.setTimeout(run, 120);
                }
            });
        } else {
            root.setTimeout(run, 120);
        }
    }

    function reinitModuleUi() {
        // Soft badge must NOT skip chart hydrate (needs hard refresh otherwise).
        if (!isUiOffline()) {
            try {
                document.querySelectorAll('[data-module-metrics-async]').forEach(function (el) {
                    if (el.getAttribute('data-rateb-metrics-loaded') === '1') {
                        return;
                    }
                    // module-page-stats listens for rateb:nav:afterEnter
                });
            } catch (e2) { /* ignore */ }
        }
        ensureDashboardCharts();
        try {
            if (typeof root.ratebMailDnsBoot === 'function'
                && document.querySelector('[data-mail-dns-async]')) {
                root.ratebMailDnsBoot({ immediate: true });
            }
        } catch (eMailDns) { /* ignore */ }
    }

    function setMainNavBusy(busy) {
        try {
            var main = document.querySelector(contentMainSel());
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
                }, 2000);
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

    /**
     * Online Cache API helper — parallel key matches with key-order priority.
     * Wall time ≈ max(match) not sum(match). Key[0] HIT resolves immediately
     * (does not wait for sibling misses) so warm navigation stays as fast as before.
     * Same preference as the old sequential chain: earlier key wins over later.
     */
    function matchKeysParallel(cache, keyList, matchOpts) {
        if (!cache || !keyList || !keyList.length) {
            return Promise.resolve(null);
        }
        return new Promise(function (resolve) {
            var n = keyList.length;
            var left = n;
            var slots = new Array(n);
            var settled = false;

            function pick() {
                if (settled) {
                    return;
                }
                settled = true;
                for (var i = 0; i < n; i++) {
                    if (slots[i]) {
                        resolve(slots[i]);
                        return;
                    }
                }
                resolve(null);
            }

            function afterSlot() {
                if (settled) {
                    return;
                }
                // Resolve as soon as the highest-priority hit is known
                // (all earlier keys confirmed miss/null).
                for (var i = 0; i < n; i++) {
                    if (slots[i] === undefined) {
                        return;
                    }
                    if (slots[i]) {
                        pick();
                        return;
                    }
                }
                if (left === 0) {
                    pick();
                }
            }

            keyList.forEach(function (k, i) {
                var req = matchOpts ? cache.match(k, matchOpts) : cache.match(k);
                req.then(function (hit) {
                    slots[i] = hit || null;
                    left -= 1;
                    afterSlot();
                }).catch(function () {
                    slots[i] = null;
                    left -= 1;
                    afterSlot();
                });
            });
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
        // Offline branch intentionally unchanged (Online Performance Fix 1 scope).
        var offlineFast = isUiOffline();
        if (offlineFast) {
            var primaryOffline = root.caches.open(OPS_PAGE_CACHE).then(function (cache) {
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
            }).catch(function () { return null; });

            return primaryOffline.then(function (fastHit) {
                if (fastHit) {
                    return fastHit;
                }
                // One ignoreSearch on pathname only — skip coexist fan-out offline.
                return root.caches.open(OPS_PAGE_CACHE).then(function (cache) {
                    return cache.match(keys[0], { ignoreSearch: true }).catch(function () { return null; });
                }).catch(function () { return null; });
            }).catch(function () {
                return null;
            });
        }

        // Online: parallel key matches (eliminate sequential miss stacking).
        // Preference unchanged: v36 primary keys[0..1], then one older warm bucket.
        // Open fallback while primary matches — no network, no duplicate fetch.
        var onlineKeys = keys.slice(0, 2);
        var fallbackName = OPS_PAGE_CACHE_FALLBACKS[0];
        var fallbackOpen = fallbackName
            ? root.caches.open(fallbackName).catch(function () { return null; })
            : Promise.resolve(null);

        return root.caches.open(OPS_PAGE_CACHE).then(function (cache) {
            return matchKeysParallel(cache, onlineKeys);
        }).catch(function () {
            return null;
        }).then(function (fastHit) {
            if (fastHit) {
                return fastHit;
            }
            return fallbackOpen.then(function (cache) {
                if (!cache) {
                    return null;
                }
                return matchKeysParallel(cache, onlineKeys);
            }).catch(function () {
                return null;
            });
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
        // Soft-offline: short timeout so hung cloud fetch cannot lock soft-nav / sidebar.
        // True online keeps long budgets (do not race-abort healthy navigations).
        var timeoutMs = isUiOffline()
            ? 2800
            : (isBareAdminHref(href) ? 10000 : (isHeavyNavHref(href) ? 20000 : 12000));
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
        if (!isBrowserOffline()) {
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
        // Soft-offline: short ceiling so hung network cannot freeze sidebar clicks.
        var ceilingMs = isBrowserOffline() ? 2000 : (isUiOffline() ? 3200 : 0);
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
                    reject(new Error(isBrowserOffline() ? 'nav_offline_cache_timeout' : 'nav_online_timeout'));
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
        // Soft/hard offline: unlock fast so sidebar never stays dead after hung fetch.
        var unlockMs = (isBrowserOffline() || isUiOffline())
            ? 3200
            : (isBareAdminHref(href) ? 4500 : (isHeavyNavHref(href) ? 12000 : 7000));
        var unlockTimer = root.setTimeout(function () {
            if (navigating && swapTo._gen === navGen) {
                navigating = false;
                setMainNavBusy(false);
                clearNavPending();
                resumePrefetch();
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
        /* PERF Fix4: abort idle/hover prefetch so navigation owns bandwidth. */
        pausePrefetch();
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
            var nextMain = doc.querySelector(contentMainSel());
            var curMain = document.querySelector(contentMainSel());
            if (!nextMain || !curMain) {
                throw new Error('missing_main');
            }
            cleanupSoftNavUiArtifacts();
            ensureAgentAppsCss(pack.finalUrl || href);
            curMain.innerHTML = nextMain.innerHTML;
            // Inert scripts from innerHTML never run — strip from painted main only.
            // Keep scripts on `doc` so scheduleModuleScripts can inject them.
            stripInertScripts(curMain);
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
            // Do NOT rememberExistingScripts() here — content scripts in `doc` are for
            // scheduleModuleScripts; scanning the live main would be redundant.
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
            // Soft OR hard offline miss: open destination inside current Admin shell
            // (sidebar stays). Never lean "وضع عدم الاتصال" menu / never trap on previous page.
            if (!(err && err.message === 'nav_superseded')) {
                // Soft "offline" badge must NOT trap online users on a stub / black page.
                if (isBrowserOffline()) {
                    if (!paintOfflinePageStub(href, opts)) {
                        showNavToast('تعذر فتح الصفحة أوفلاين من الشيل الحالي.', true);
                    }
                } else {
                    showSoftNavMissToast(href);
                    lastSoftNavMissHref = '';
                    hardNavigate(href);
                }
            }
            return false;
        }).then(function (ok) {
            root.clearTimeout(unlockTimer);
            if (swapTo._gen === navGen) {
                navigating = false;
                inflightAbort = null;
                /* Allow hover prefetch again; idle wave never re-runs. */
                resumePrefetch();
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
            if (POS_RUNTIME_RE.test(u.pathname)) {
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
            // POS admin pages now use Admin main layout — soft-nav like other ERP modules.
            // Legacy pos-pages-shell: soft-nav only within that shell (keep header).
            if (isOnPosPagesShell()) {
                return /\/(?:admin\/ops\/)?pos(\/|$)/i.test(u.pathname)
                    && !POS_RUNTIME_RE.test(u.pathname);
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
        // Full-nav for selling shell / biometric / logout only.
        // POS admin CRUD soft-navs inside Admin chrome (same sidebar).
        try {
            var forceHref = navHrefOf(a);
            if (forceHref && ev.button === 0 && !ev.metaKey && !ev.ctrlKey && !ev.shiftKey && !ev.altKey) {
                var fu = new URL(forceHref, root.location.href);
                var forceFull = a.getAttribute('data-rateb-full-nav') === '1'
                    || (ADMIN_PATH_RE.test(fu.pathname) && POS_RUNTIME_RE.test(fu.pathname))
                    || /\/(logout|login|password)(\/|$)/i.test(fu.pathname);
                if (forceFull) {
                    ev.preventDefault();
                    try { ev.stopImmediatePropagation(); } catch (eSipPos) { ev.stopPropagation(); }
                    root.location.href = forceHref;
                    return;
                }
            }
        } catch (ePosNav) { /* fall through */ }
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
        if (!isOnPosPagesShell() && !document.querySelector('#rateb-sidebar, .rateb-sidebar')) {
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
        pausePrefetch: pausePrefetch,
        unlock: function () {
            navigating = false;
            pendingNavHref = '';
            cleanupSoftNavUiArtifacts();
            resumePrefetch();
            drainPendingNav();
        },
        isNavigating: function () { return !!navigating; }
    };

    function boot() {
        rememberExistingScripts();
        cleanupSoftNavUiArtifacts();
        bindPrefetch(
            document.getElementById('rateb-sidebar')
            || document.querySelector('.rateb-pos__header--pages')
            || document
        );
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
        /* Soft/hard offline watchdog — force-unlock nav if a hung fetch left clicks dead. */
        try {
            root.setInterval(function () {
                if (!navigating || !isUiOffline()) {
                    return;
                }
                navigating = false;
                setMainNavBusy(false);
                clearNavPending();
                inflightAbort = null;
            }, 4000);
        } catch (eWd) { /* ignore */ }
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
