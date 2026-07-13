/**
 * RATEB Offline — ERP shell bootstrap (Phase 14).
 * Passes full cfg.flags into SDK; never freezes later phase flags.
 * Does not overwrite pos-sw.js when it owns the shared scope.
 * Sync badge + clientQueueMax for daily ops pilot.
 * TEMP: RatebOfflineTrace diagnostics (remove with erp-offline-debug.js).
 */
(function (root) {
    'use strict';

    var cfg = root.__RATEB_ERP_SHELL_OFFLINE__ || {};

    function trace() {
        return root.RatebOfflineTrace || null;
    }

    function tPass(step, file, fn, reason) {
        var t = trace();
        if (!t) {
            return true;
        }
        return t.pass(step, file, fn, reason);
    }

    function tFail(step, file, fn, reason) {
        var t = trace();
        if (!t) {
            return false;
        }
        return t.fail(step, file, fn, reason);
    }

    function tStopped() {
        var t = trace();
        return !!(t && t.stopped());
    }

    tPass(3, 'erp-shell-bootstrap.js', 'load', 'erp-shell-bootstrap.js loaded and executing');

    function isPosLocation() {
        try {
            var p = String((root.location && root.location.pathname) || '');
            return /\/pos(\/|$)/i.test(p) || /\/admin\/ops\/pos(\/|$)/i.test(p);
        } catch (e) {
            return true;
        }
    }

    function flagsFromConfig() {
        var f = Object.assign({}, cfg.flags || {});
        // Ensure shell prerequisites when this bootstrap runs (read_cache path).
        f['offline.enabled'] = true;
        f['offline.read_cache'] = true;
        return f;
    }

    function persistOfflineScope(flags) {
        try {
            if (!(parseInt(cfg.company_id, 10) > 0 && parseInt(cfg.user_id, 10) > 0)) {
                return;
            }
            root.localStorage.setItem('rateb_erp_offline_scope', JSON.stringify({
                company_id: parseInt(cfg.company_id, 10) || 0,
                tenant_id: parseInt(cfg.tenant_id || cfg.company_id, 10) || 0,
                branch_id: parseInt(cfg.branch_id, 10) || 0,
                user_id: parseInt(cfg.user_id, 10) || 0,
                is_super_admin: !!cfg.is_super_admin,
                device_id: (function () {
                    try {
                        return root.localStorage.getItem('rateb_erp_device_uuid') || '';
                    } catch (e) { return ''; }
                })(),
                auth_unlock: !!(flags && flags['offline.auth.unlock']),
                flags: {
                    'offline.enabled': true,
                    'offline.read_cache': true,
                    'offline.auth.unlock': !!(flags && flags['offline.auth.unlock']),
                    'offline.rbac.cache': !!(flags && flags['offline.rbac.cache'])
                },
                saved_at: new Date().toISOString()
            }));
        } catch (e) { /* ignore */ }
    }

    function warmErpShellUrls() {
        if (tStopped()) {
            return Promise.resolve(null);
        }
        var base;
        try {
            var scope = cfg.serviceWorkerScope || '';
            if (!scope) {
                scope = (root.location && root.location.origin ? root.location.origin : '') + '/rateb-erp/public/';
            }
            if (scope.slice(-1) !== '/') {
                scope += '/';
            }
            base = scope;
        } catch (e) {
            base = '/rateb-erp/public/';
        }
        var shellUrl = base + 'offline-shell.html';
        var urls = [
            shellUrl,
            base + 'assets/offline/rateb-offline.js',
            base + 'assets/offline/erp-offline-shell-auth.js',
            base + 'assets/offline/erp-offline-shell-rbac.js'
        ];
        if (!('caches' in root) || !root.fetch) {
            tFail(11, 'erp-shell-bootstrap.js', 'warmErpShellUrls', 'caches or fetch unavailable');
            return Promise.resolve(null);
        }
        tPass(11, 'erp-shell-bootstrap.js', 'warmErpShellUrls.fetch', 'fetch start url=' + shellUrl);
        return root.caches.open('rateb-erp-coexist-v22').then(function (cache) {
            return root.fetch(shellUrl, {
                credentials: 'same-origin',
                cache: 'no-cache',
                headers: { Accept: '*/*', 'X-Rateb-Shell-Warm': '1' }
            }).then(function (res) {
                if (tStopped()) {
                    return null;
                }
                if (!res || !res.ok) {
                    tFail(11, 'erp-shell-bootstrap.js', 'warmErpShellUrls.fetch',
                        'fetch not ok status=' + (res ? res.status : 'null') + ' url=' + shellUrl);
                    return null;
                }
                tPass(11, 'erp-shell-bootstrap.js', 'warmErpShellUrls.fetch', 'fetch ok status=' + res.status);
                return cache.put(shellUrl, res.clone()).then(function () {
                    if (tStopped()) {
                        return null;
                    }
                    tPass(12, 'erp-shell-bootstrap.js', 'warmErpShellUrls.cache.put',
                        'cache.put cache=rateb-erp-coexist-v22 key=' + shellUrl);
                    return cache.match(shellUrl).then(function (hit) {
                        if (hit) {
                            tPass(13, 'erp-shell-bootstrap.js', 'warmErpShellUrls.verify',
                                'offline-shell.html present in rateb-erp-coexist-v22');
                        } else {
                            tFail(13, 'erp-shell-bootstrap.js', 'warmErpShellUrls.verify',
                                'cache.match miss after put key=' + shellUrl);
                        }
                        return hit;
                    });
                }).catch(function (err) {
                    tFail(12, 'erp-shell-bootstrap.js', 'warmErpShellUrls.cache.put',
                        'cache.put failed: ' + String(err && err.message ? err.message : err));
                    return null;
                });
            }).catch(function (err) {
                tFail(11, 'erp-shell-bootstrap.js', 'warmErpShellUrls.fetch',
                    'fetch threw: ' + String(err && err.message ? err.message : err));
                return null;
            }).then(function () {
                // Still warm helpers (best-effort; do not fail chain if shell already cached).
                return Promise.all(urls.slice(1).map(function (u) {
                    return root.fetch(u, {
                        credentials: 'same-origin',
                        cache: 'no-cache',
                        headers: { Accept: '*/*', 'X-Rateb-Shell-Warm': '1' }
                    }).then(function (res) {
                        if (res && res.ok) {
                            return cache.put(u, res.clone());
                        }
                        return null;
                    }).catch(function () { return null; });
                }));
            });
        }).catch(function (err) {
            tFail(12, 'erp-shell-bootstrap.js', 'warmErpShellUrls.caches.open',
                'caches.open failed: ' + String(err && err.message ? err.message : err));
            return null;
        });
    }

    function warmErpShellViaPosSw(controller) {
        if (tStopped()) {
            return Promise.resolve(null);
        }
        // Once per tab session — eager warm on every page navigation saturates the network.
        try {
            var warmKey = 'rateb_erp_shell_warm_at';
            var at = parseInt(root.sessionStorage.getItem(warmKey) || '0', 10) || 0;
            if (at > 0 && (Date.now() - at) < (30 * 60 * 1000)) {
                tPass(8, 'erp-shell-bootstrap.js', 'warmErpShellViaPosSw', 'skipped — warmed this session');
                return Promise.resolve(null);
            }
            root.sessionStorage.setItem(warmKey, String(Date.now()));
        } catch (eGate) { /* ignore */ }
        try {
            var sendWarm = function () {
                try {
                    if (controller && typeof controller.postMessage === 'function') {
                        controller.postMessage({ type: 'WARM_ERP_OFFLINE_SHELL' });
                        tPass(8, 'erp-shell-bootstrap.js', 'warmErpShellViaPosSw',
                            'postMessage WARM_ERP_OFFLINE_SHELL to controller');
                    } else {
                        tPass(8, 'erp-shell-bootstrap.js', 'warmErpShellViaPosSw',
                            'no controller postMessage — page Cache API warm only');
                    }
                } catch (eSend) {
                    tFail(8, 'erp-shell-bootstrap.js', 'warmErpShellViaPosSw',
                        'postMessage threw: ' + String(eSend && eSend.message ? eSend.message : eSend));
                }
            };
            // Do not compete with first paint / Chart.js.
            if (typeof root.requestIdleCallback === 'function') {
                root.requestIdleCallback(sendWarm, { timeout: 12000 });
            } else {
                root.setTimeout(sendWarm, 3500);
            }
        } catch (e) {
            tFail(8, 'erp-shell-bootstrap.js', 'warmErpShellViaPosSw',
                'postMessage threw: ' + String(e && e.message ? e.message : e));
            return Promise.resolve(null);
        }
        // Idle-only; do not block page interactivity.
        if (typeof root.requestIdleCallback === 'function') {
            root.requestIdleCallback(function () { warmErpShellUrls(); }, { timeout: 20000 });
            return Promise.resolve(null);
        }
        root.setTimeout(function () { warmErpShellUrls(); }, 4000);
        return Promise.resolve(null);
    }

    function registerServiceWorker() {
        if (tStopped()) {
            return Promise.resolve(null);
        }
        tPass(5, 'erp-shell-bootstrap.js', 'registerServiceWorker', 'entered registerServiceWorker');
        if (!('serviceWorker' in root.navigator)) {
            tFail(5, 'erp-shell-bootstrap.js', 'registerServiceWorker', 'serviceWorker API unavailable');
            return Promise.resolve(null);
        }
        if (isPosLocation()) {
            tFail(5, 'erp-shell-bootstrap.js', 'registerServiceWorker', 'POS location — SW register skipped');
            return Promise.resolve(null);
        }
        var swUrl = cfg.serviceWorker || '';
        if (!swUrl) {
            tFail(5, 'erp-shell-bootstrap.js', 'registerServiceWorker', 'cfg.serviceWorker empty');
            return Promise.resolve(null);
        }
        var scope = cfg.serviceWorkerScope || undefined;
        if (scope === '/' || (root.location && scope === root.location.origin + '/')) {
            try {
                scope = new URL('.', swUrl).pathname;
            } catch (e) {
                scope = undefined;
            }
        }
        return root.navigator.serviceWorker.getRegistrations().then(function (regs) {
            if (tStopped()) {
                return null;
            }
            // Prefer pos-sw.js (claims clients + ERP offline shell coexist).
            var posReg = null;
            var legacyErpReg = null;
            (regs || []).forEach(function (reg) {
                var active = reg.active || reg.waiting || reg.installing;
                var src = (active && active.scriptURL) ? String(active.scriptURL) : '';
                if (/pos-sw\.js/i.test(src)) {
                    posReg = reg;
                } else if (/rateb-offline-sw\.js/i.test(src)) {
                    legacyErpReg = reg;
                }
            });
            if (posReg) {
                try {
                    if (typeof posReg.update === 'function') {
                        posReg.update();
                    }
                    if (posReg.waiting) {
                        posReg.waiting.postMessage({ type: 'SKIP_WAITING' });
                    }
                } catch (ePosUp) { /* ignore */ }
                var ctrl = (posReg.active)
                    || (root.navigator.serviceWorker && root.navigator.serviceWorker.controller)
                    || null;
                if (ctrl) {
                    tPass(6, 'erp-shell-bootstrap.js', 'registerServiceWorker',
                        'pos-sw registration present; coexist warm path');
                    tPass(7, 'erp-shell-bootstrap.js', 'registerServiceWorker',
                        'controller found script=' + (ctrl.scriptURL || ''));
                } else {
                    tPass(6, 'erp-shell-bootstrap.js', 'registerServiceWorker',
                        'pos-sw registration present; no controller yet');
                    tPass(7, 'erp-shell-bootstrap.js', 'registerServiceWorker',
                        'controller absent — waiting for claim/ready');
                }
                return warmErpShellViaPosSw(ctrl).then(function () { return null; });
            }
            // Upgrade legacy ERP-only SW → pos-sw so offline navigations are claimed.
            var upgrade = Promise.resolve();
            if (legacyErpReg && /pos-sw\.js/i.test(swUrl)) {
                tPass(6, 'erp-shell-bootstrap.js', 'registerServiceWorker',
                    'upgrading rateb-offline-sw → pos-sw');
                upgrade = legacyErpReg.unregister().catch(function () { return false; });
            }
            return upgrade.then(function () {
                return root.navigator.serviceWorker.register(swUrl, scope
                    ? { scope: scope, updateViaCache: 'none' }
                    : { updateViaCache: 'none' });
            }).then(function (reg) {
                    if (tStopped()) {
                        return null;
                    }
                    try {
                        if (reg && typeof reg.update === 'function') {
                            reg.update();
                        }
                        if (reg && reg.waiting) {
                            reg.waiting.postMessage({ type: 'SKIP_WAITING' });
                        }
                    } catch (eUp) { /* ignore */ }
                    tPass(6, 'erp-shell-bootstrap.js', 'registerServiceWorker',
                        'SW registered scope=' + ((reg && reg.scope) || scope || '')
                        + ' script=' + swUrl);
                    return root.navigator.serviceWorker.ready.then(function (readyReg) {
                        var ctrl2 = (root.navigator.serviceWorker && root.navigator.serviceWorker.controller)
                            || (readyReg && readyReg.active)
                            || (reg && reg.active)
                            || null;
                        if (ctrl2) {
                            tPass(7, 'erp-shell-bootstrap.js', 'registerServiceWorker',
                                'controller found script=' + (ctrl2.scriptURL || ''));
                            return warmErpShellViaPosSw(ctrl2).then(function () { return reg; });
                        }
                        tPass(7, 'erp-shell-bootstrap.js', 'registerServiceWorker',
                            'no controller yet after register — skip eager warm');
                        return reg;
                    });
                })
                .catch(function (err) {
                    tFail(6, 'erp-shell-bootstrap.js', 'registerServiceWorker',
                        'register failed: ' + String(err && err.message ? err.message : err));
                    return null;
                });
        }).catch(function (err) {
            tFail(5, 'erp-shell-bootstrap.js', 'registerServiceWorker',
                'getRegistrations failed: ' + String(err && err.message ? err.message : err));
            return null;
        });
    }

    function ensureSyncBadge() {
        if (!root.document) {
            return null;
        }
        var existing = root.document.getElementById('rateb-offline-sync-badge');
        if (existing) {
            return existing;
        }
        var indicator = root.document.getElementById('rateb-connection-indicator');
        var parent = indicator && indicator.parentNode ? indicator.parentNode : null;
        if (!parent) {
            return null;
        }
        var badge = root.document.createElement('span');
        badge.id = 'rateb-offline-sync-badge';
        badge.className = 'rateb-offline-sync-badge ms-2 small text-muted';
        badge.setAttribute('role', 'status');
        badge.setAttribute('aria-live', 'polite');
        badge.hidden = true;
        if (indicator.nextSibling) {
            parent.insertBefore(badge, indicator.nextSibling);
        } else {
            parent.appendChild(badge);
        }
        return badge;
    }

    function refreshSyncBadge() {
        var badge = ensureSyncBadge();
        if (!badge) {
            return;
        }
        var queue = root.RatebOfflineQueue;
        if (!queue || typeof queue.depth !== 'function') {
            badge.hidden = true;
            return;
        }
        queue.depth().then(function (d) {
            var n = parseInt(d, 10) || 0;
            if (n < 1) {
                badge.hidden = true;
                badge.textContent = '';
                return;
            }
            var max = typeof queue.clientQueueMax === 'function' ? queue.clientQueueMax() : 500;
            badge.hidden = false;
            badge.textContent = n + ' عمليات بانتظار المزامنة';
            badge.title = 'Offline sync queue — drafts waiting to flush when online';
            badge.setAttribute('data-rateb-offline-queue-depth', String(n));
        }).catch(function () {
            badge.hidden = true;
        });
    }

    var LIVE_RELOAD_KEY = 'rateb_erp_live_reload_at';
    var lastConnectivityOnline = null;

    /** If offline shell is painted, escape only after a real network probe succeeds. */
    function escapeOfflineShellIfOnline() {
        try {
            if (root.navigator && root.navigator.onLine === false) {
                return false;
            }
            if (!isOfflineShellDocument()) {
                return false;
            }
            // navigator.onLine alone is not enough — probe before leaving cached UI.
            var probeUrl = (cfg && cfg.probeUrl) ? String(cfg.probeUrl) : '';
            if (!probeUrl) {
                try {
                    var p = String(root.location.pathname || '');
                    var m = p.match(/^(.*\/public\/)/i);
                    probeUrl = (m && m[1] ? m[1] : '/rateb-erp/public/') + 'api/v1/offline/status';
                } catch (ePu) {
                    probeUrl = '/rateb-erp/public/api/v1/offline/status';
                }
            }
            var url = probeUrl + (probeUrl.indexOf('?') >= 0 ? '&' : '?') + '_rateb_probe=' + Date.now();
            root.fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'application/json', 'X-Rateb-Connectivity': '1' }
            }).then(function (res) {
                if (!res || !(res.ok || res.status === 401 || res.status === 403 || res.status === 419)) {
                    return;
                }
                doEscapeOfflineShell();
            }).catch(function () { /* stay on cached UI */ });
            return false;
        } catch (eEsc) {
            return false;
        }
    }

    function doEscapeOfflineShell() {
        try {
            var path = String(root.location.pathname || '');
            var hadLive = false;
            try {
                hadLive = !!(new URL(root.location.href).searchParams.get('rateb_live'));
            } catch (eHad) { /* ignore */ }
            if (hadLive) {
                purgeStaleOfflineAndReload();
                return;
            }
            var target;
            if (/offline-shell\.html$/i.test(path)) {
                var base = path.replace(/offline-shell\.html$/i, '');
                target = root.location.origin + base + 'admin/?rateb_live=' + Date.now();
            } else {
                var u = new URL(root.location.href);
                u.searchParams.set('rateb_live', String(Date.now()));
                target = u.href;
            }
            root.location.replace(target);
        } catch (eDo) { /* ignore */ }
    }

    function purgeStaleOfflineAndReload() {
        var finish = function () {
            try {
                var u = new URL(root.location.href);
                u.searchParams.delete('rateb_live');
                u.searchParams.set('rateb_force_live', String(Date.now()));
                root.location.replace(u.href);
            } catch (eFin) {
                try { root.location.reload(); } catch (eRel) { /* ignore */ }
            }
        };
        var tasks = [];
        try {
            if (root.navigator && root.navigator.serviceWorker
                && typeof root.navigator.serviceWorker.getRegistrations === 'function') {
                tasks.push(root.navigator.serviceWorker.getRegistrations().then(function (regs) {
                    return Promise.all((regs || []).map(function (reg) {
                        return reg.unregister().catch(function () { return false; });
                    }));
                }));
            }
        } catch (eSw) { /* ignore */ }
        try {
            if (root.caches && typeof root.caches.keys === 'function') {
                tasks.push(root.caches.keys().then(function (keys) {
                    return Promise.all((keys || []).map(function (k) {
                        if (/^rateb-/i.test(String(k || ''))) {
                            return root.caches.delete(k);
                        }
                        return null;
                    }));
                }));
            }
        } catch (eCache) { /* ignore */ }
        Promise.all(tasks).then(finish).catch(finish);
    }

    function isOfflineShellDocument() {
        var doc = root.document;
        if (!doc) {
            return false;
        }
        // Strict markers only — do not treat live pages with leftover classes as offline shells
        // (false positives caused auto-reload loops and made every navigation feel broken/slow).
        if (doc.getElementById('rateb-offline-shell-main') || doc.querySelector('.rateb-offline-home')) {
            return true;
        }
        if (doc.querySelector('[data-rateb-offline-ops-banner="1"], [data-rateb-offline-ops-banner]')) {
            return true;
        }
        try {
            if (/offline-shell\.html$/i.test(String(root.location.pathname || ''))) {
                return true;
            }
        } catch (e) { /* ignore */ }
        var shellCfg = root.__RATEB_ERP_SHELL_OFFLINE__ || {};
        return !!shellCfg.offline_ops_snapshot;
    }

    /** Cached ops/shell HTML (same URL as live) — must hard-reload when back online. */
    function isCachedOfflineUi() {
        return isOfflineShellDocument();
    }

    function connectivitySaysOnline() {
        var conn = root.RatebOfflineConnectivity;
        if (conn && typeof conn.isOnline === 'function') {
            return !!conn.isOnline();
        }
        return !(root.navigator && root.navigator.onLine === false);
    }

    function paintConnectionIndicator(online) {
        var doc = root.document;
        if (!doc) {
            return;
        }
        var nodes = doc.querySelectorAll(
            '#rateb-connection-indicator, .rateb-connection-indicator, [data-rateb-connection-status]'
        );
        Array.prototype.forEach.call(nodes, function (el) {
            if (online) {
                el.classList.remove('is-offline');
                el.classList.add('is-online');
                el.setAttribute('title', 'متصل');
                el.setAttribute('aria-label', 'متصل');
            } else {
                el.classList.remove('is-online');
                el.classList.add('is-offline');
                el.setAttribute('title', 'غير متصل');
                el.setAttribute('aria-label', 'غير متصل');
            }
            var label = el.querySelector('.rateb-connection-indicator__label');
            if (label) {
                label.textContent = online ? 'متصل' : 'غير متصل';
            }
        });
    }

    function ensureReconnectButton(online) {
        var doc = root.document;
        if (!doc || !isOfflineShellDocument()) {
            return;
        }
        var home = doc.querySelector('.rateb-offline-home') || doc.getElementById('rateb-offline-shell-main');
        if (!home) {
            return;
        }
        var btn = doc.getElementById('rateb-offline-reconnect-btn');
        if (!btn) {
            btn = doc.createElement('button');
            btn.id = 'rateb-offline-reconnect-btn';
            btn.type = 'button';
            btn.className = 'btn btn-primary mt-3';
            btn.addEventListener('click', function () {
                try {
                    root.sessionStorage.setItem(LIVE_RELOAD_KEY, '0');
                } catch (e0) { /* ignore */ }
                root.location.reload();
            });
            home.appendChild(btn);
        }
        btn.hidden = !online;
        btn.textContent = online ? 'العودة للوضع المتصل (تحديث الصفحة)' : 'بانتظار الاتصال…';
        var hint = doc.getElementById('rateb-offline-reconnect-hint');
        if (online) {
            if (!hint) {
                hint = doc.createElement('p');
                hint.id = 'rateb-offline-reconnect-hint';
                hint.className = 'text-success small mt-2';
                home.appendChild(hint);
            }
            hint.hidden = false;
            hint.textContent = 'أنت متصل بالشبكة — جاري استعادة الواجهة الحية…';
        } else if (hint) {
            hint.hidden = true;
        }
    }

    function canAutoLiveReload() {
        if (!isOfflineShellDocument() || !connectivitySaysOnline()) {
            return false;
        }
        try {
            var last = parseInt(root.sessionStorage.getItem(LIVE_RELOAD_KEY) || '0', 10) || 0;
            if (last > 0 && (Date.now() - last) < 15000) {
                return false;
            }
        } catch (e) { /* ignore */ }
        return true;
    }

    function requestLiveReload(reason) {
        if (!canAutoLiveReload()) {
            ensureReconnectButton(true);
            paintConnectionIndicator(true);
            return;
        }
        try {
            root.sessionStorage.setItem(LIVE_RELOAD_KEY, String(Date.now()));
        } catch (e1) { /* ignore */ }
        ensureReconnectButton(true);
        paintConnectionIndicator(true);
        try {
            // Bypass bfcache / sticky SW HTML: navigate to same URL with a bust query.
            var href = String(root.location.href || '');
            var u = new URL(href, root.location.origin);
            u.searchParams.set('rateb_live', String(Date.now()));
            root.setTimeout(function () {
                root.location.replace(u.href);
            }, 200);
        } catch (e2) {
            try {
                root.location.reload();
            } catch (e3) { /* ignore */ }
        }
    }

    function onConnectivityChange(online) {
        online = !!online;
        paintConnectionIndicator(online);
        refreshSyncBadge();
        ensureReconnectButton(online);
        // Viewing cached offline UI while network is back → always restore live Admin.
        // Include first-boot (null → true): cached ops pages paint "متصل" without reload otherwise.
        if (online && isCachedOfflineUi()) {
            var cameBack = lastConnectivityOnline === false;
            var bootAlreadyOnline = lastConnectivityOnline === null;
            if (cameBack || bootAlreadyOnline) {
                requestLiveReload(cameBack ? 'reconnect' : 'boot-online-cached');
            } else {
                ensureReconnectButton(true);
            }
        }
        lastConnectivityOnline = online;
    }

    function bindSyncBadge() {
        ensureSyncBadge();
        refreshSyncBadge();
        var events = root.RatebOfflineEvents;
        if (events && typeof events.on === 'function') {
            ['queue:enqueued', 'queue:flushed', 'queue:full', 'sdk:ready', 'sdk:flags'].forEach(function (ev) {
                events.on(ev, function () { refreshSyncBadge(); });
            });
        }
        if (root.RatebOfflineConnectivity && typeof root.RatebOfflineConnectivity.subscribe === 'function') {
            root.RatebOfflineConnectivity.subscribe(function (online) {
                onConnectivityChange(online);
            });
        } else {
            onConnectivityChange(connectivitySaysOnline());
        }
        root.addEventListener('online', function () {
            var conn = root.RatebOfflineConnectivity;
            if (conn && typeof conn.probe === 'function') {
                conn.probe().then(function (ok) {
                    onConnectivityChange(!!ok);
                }).catch(function () {
                    onConnectivityChange(false);
                });
            } else {
                onConnectivityChange(false);
            }
        });
        root.addEventListener('offline', function () {
            onConnectivityChange(false);
        });
    }

    function afterWarmDiagnostics() {
        if (tStopped()) {
            return Promise.resolve();
        }
        if (root.RatebOffline && typeof root.RatebOffline.init === 'function') {
            tPass(14, 'erp-shell-bootstrap.js', 'RatebOffline.init', 'SDK init already invoked in boot');
        } else {
            tFail(14, 'erp-shell-bootstrap.js', 'RatebOffline.init', 'RatebOffline SDK missing');
            return Promise.resolve();
        }
        if (!root.indexedDB) {
            tFail(15, 'erp-shell-bootstrap.js', 'indexedDB', 'indexedDB unavailable');
            return Promise.resolve();
        }
        return new Promise(function (resolve) {
            var req = root.indexedDB.open('rateb_erp_offline');
            req.onerror = function () {
                tFail(15, 'erp-shell-bootstrap.js', 'indexedDB.open', 'open failed');
                resolve();
            };
            req.onsuccess = function () {
                tPass(15, 'erp-shell-bootstrap.js', 'indexedDB.open', 'rateb_erp_offline opened');
                try {
                    req.result.close();
                } catch (e) { /* ignore */ }
                resolve();
            };
        }).then(function () {
            if (tStopped()) {
                return null;
            }
            var shell = root.RatebOfflineShellAdapter;
            if (!shell || typeof shell.captureChrome !== 'function') {
                tFail(16, 'erp-shell-bootstrap.js', 'captureChrome', 'RatebOfflineShellAdapter.captureChrome missing');
                return null;
            }
            try {
                var capAt = parseInt(root.sessionStorage.getItem('rateb_erp_chrome_cap_at') || '0', 10) || 0;
                if (capAt > 0 && (Date.now() - capAt) < (30 * 60 * 1000)) {
                    tPass(16, 'shell-adapter.js', 'captureChrome', 'skipped — captured this session');
                    return null;
                }
                root.sessionStorage.setItem('rateb_erp_chrome_cap_at', String(Date.now()));
            } catch (eCap) { /* ignore */ }
            return shell.captureChrome().then(function (res) {
                if (tStopped()) {
                    return null;
                }
                if (res && res.ok) {
                    tPass(16, 'shell-adapter.js', 'captureChrome', 'shell snapshot saved id=' + (res.id || ''));
                } else {
                    tFail(16, 'shell-adapter.js', 'captureChrome',
                        'snapshot not saved: ' + JSON.stringify(res || {}));
                }
                return res;
            }).catch(function (err) {
                tFail(16, 'shell-adapter.js', 'captureChrome',
                    'threw: ' + String(err && err.message ? err.message : err));
                return null;
            });
        }).then(function () {
            if (tStopped()) {
                return null;
            }
            // Never mutate live / captured-ops sidebar with RBAC rebuild.
            // Offline must keep the same captured nav as online (icons + full sections).
            // applyCachedNav is only for offline-shell.html home (no captured module chrome).
            var onlineNow = !(root.navigator && root.navigator.onLine === false);
            var opsSnap = !!(cfg.offline_ops_snapshot)
                || !!(root.document && root.document.querySelector('[data-rateb-offline-ops-banner]'));
            if (onlineNow || opsSnap) {
                tPass(17, 'erp-shell-bootstrap.js', 'RBAC',
                    opsSnap ? 'ops snapshot — keep captured live nav' : 'online — skipped applyCachedNav');
                tPass(18, 'erp-shell-bootstrap.js', 'Offline Ready', 'warm+sdk+idb+capture complete');
                return null;
            }
            var rbac = root.RatebOfflineRbacCache;
            if (!rbac || typeof rbac.applyCachedNav !== 'function') {
                // RBAC may be flag-off; do not hard-fail Offline Ready if rbac.cache disabled.
                var flags = (cfg.flags || {});
                if (!flags['offline.rbac.cache']) {
                    tPass(17, 'erp-shell-bootstrap.js', 'RBAC', 'offline.rbac.cache off — skipped');
                    tPass(18, 'erp-shell-bootstrap.js', 'Offline Ready', 'warm+sdk+idb path complete (rbac skipped)');
                    return null;
                }
                tFail(17, 'erp-shell-bootstrap.js', 'applyCachedNav', 'RatebOfflineRbacCache missing');
                return null;
            }
            return rbac.applyCachedNav({ requireDeviceActive: false }).then(function (nav) {
                if (tStopped()) {
                    return null;
                }
                if (nav && (nav.ok || nav.applied || nav.html || nav.items)) {
                    tPass(17, 'rbac-cache-adapter.js', 'applyCachedNav', 'RBAC/nav restore attempted ok');
                } else {
                    tPass(17, 'rbac-cache-adapter.js', 'applyCachedNav',
                        'RBAC restore returned: ' + JSON.stringify(nav || {}));
                }
                tPass(18, 'erp-shell-bootstrap.js', 'Offline Ready', 'diagnostic chain completed');
                return nav;
            }).catch(function (err) {
                tFail(17, 'rbac-cache-adapter.js', 'applyCachedNav',
                    'threw: ' + String(err && err.message ? err.message : err));
                return null;
            });
        });
    }

    function showTenantGateError(companyId, userId) {
        var payload = {
            event: 'rateb_offline_tenant_gate_failed',
            user_id: userId,
            company_id: companyId,
            timestamp: new Date().toISOString(),
            route: (root.location && (root.location.pathname + (root.location.search || ''))) || ''
        };
        try {
            console.error('[RATIB OFFLINE]', 'FAIL', 'tenant_gate', payload);
        } catch (eLog) { /* ignore */ }
        try {
            if (!root.document || !root.document.body) {
                return;
            }
            var existing = root.document.getElementById('rateb-offline-tenant-gate-error');
            if (existing) {
                return;
            }
            var el = root.document.createElement('div');
            el.id = 'rateb-offline-tenant-gate-error';
            el.setAttribute('role', 'alert');
            el.style.cssText = 'position:relative;z-index:9999;margin:0;padding:12px 16px;'
                + 'background:#7f1d1d;color:#fff;font:14px/1.45 Tajawal,sans-serif;text-align:center';
            el.textContent = 'Offline shell blocked: invalid tenant context'
                + ' (user_id=' + String(userId)
                + ', company_id=' + String(companyId)
                + '). Select an operational company, then reload Admin.';
            var host = root.document.querySelector('main') || root.document.body;
            host.insertBefore(el, host.firstChild);
        } catch (eDom) { /* ignore */ }
    }

    function boot() {
        if (tStopped()) {
            return;
        }
        if (escapeOfflineShellIfOnline()) {
            return;
        }
        tPass(4, 'erp-shell-bootstrap.js', 'boot', 'boot() entered');
        var flags = flagsFromConfig();
        if (!flags['offline.enabled'] || !flags['offline.read_cache']) {
            tFail(4, 'erp-shell-bootstrap.js', 'boot',
                'flags gate failed enabled=' + !!flags['offline.enabled']
                + ' read_cache=' + !!flags['offline.read_cache']);
            return;
        }
        if (isPosLocation()) {
            tFail(4, 'erp-shell-bootstrap.js', 'boot', 'POS location — boot aborted');
            return;
        }
        var gateCompany = parseInt(cfg.company_id, 10) || 0;
        var gateUser = parseInt(cfg.user_id, 10) || 0;
        if (!(gateCompany > 0 && gateUser > 0)) {
            tFail(4, 'erp-shell-bootstrap.js', 'boot',
                'tenant gate failed company_id=' + cfg.company_id + ' user_id=' + cfg.user_id);
            showTenantGateError(gateCompany, gateUser);
            return;
        }
        persistOfflineScope(flags);
        if (root.RatebOffline && typeof root.RatebOffline.init === 'function') {
            var max = parseInt(cfg.client_queue_max, 10);
            root.RatebOffline.init({
                apiBase: cfg.apiBase || '',
                probeUrl: cfg.probeUrl || null,
                flags: flags,
                clientQueueMax: !isNaN(max) && max >= 0 ? max : 500,
                startConnectivity: cfg.startConnectivity !== false,
                startScheduler: false
            });
        }
        bindSyncBadge();
        // Offline: same-URL nav click on any live Admin page must not open offline-shell.
        try {
            if (root.document && !root.document.__ratebDashClickGuard) {
                root.document.__ratebDashClickGuard = true;
                root.document.addEventListener('click', function (ev) {
                    try {
                        if (root.navigator && root.navigator.onLine !== false) {
                            return;
                        }
                        if (isOfflineShellDocument()) {
                            return;
                        }
                        var here = String(root.location.pathname || '').replace(/\/+$/, '').toLowerCase();
                        if (!/\/admin(\/|$)/i.test(here)) {
                            return;
                        }
                        var a = ev.target && ev.target.closest ? ev.target.closest('a') : null;
                        if (!a || !a.href) {
                            return;
                        }
                        var u = new URL(a.href, root.location.href);
                        if (u.origin !== root.location.origin) {
                            return;
                        }
                        var there = String(u.pathname || '').replace(/\/+$/, '').toLowerCase();
                        if (there !== here) {
                            return;
                        }
                        ev.preventDefault();
                        ev.stopPropagation();
                    } catch (eClick) { /* ignore */ }
                }, true);
            }
        } catch (eGuard) { /* ignore */ }
        registerServiceWorker().then(function () {
            if (root.RatebOfflineShellAdapter && typeof root.RatebOfflineShellAdapter.startAutoCapture === 'function') {
                root.RatebOfflineShellAdapter.startAutoCapture();
            }
            return afterWarmDiagnostics();
        });
    }

    if (root.document && root.document.readyState === 'loading') {
        root.document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})(typeof window !== 'undefined' ? window : globalThis);
