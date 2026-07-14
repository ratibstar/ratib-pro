/*! RATEB Offline SDK — Phase OA bootstrap (load orchestration only). */
(function (root) {
    'use strict';

    if (root.RatebOffline && root.RatebOffline.__oaBootstrap) {
        return;
    }

    var VERSION = '14.2.0-oa';
    var basePath = (function () {
        try {
            var scripts = root.document && root.document.getElementsByTagName('script');
            if (scripts) {
                for (var i = scripts.length - 1; i >= 0; i--) {
                    var src = scripts[i].src || '';
                    var m = src.match(/^(.*\/assets\/offline\/)offline-bootstrap\.js/i);
                    if (m) {
                        return m[1];
                    }
                }
            }
        } catch (e0) { /* ignore */ }
        try {
            var p = String((root.location && root.location.pathname) || '');
            var m2 = p.match(/^(.*\/public\/)/i);
            if (m2 && m2[1]) {
                return m2[1] + 'assets/offline/';
            }
        } catch (e1) { /* ignore */ }
        return '/rateb-erp/public/assets/offline/';
    })();

    var modBase = basePath + 'modules/';
    var loaded = Object.create(null);
    var inflight = Object.create(null);
    var booted = false;
    var lastInitOptions = null;

    /* Defaults match sdk.js (all OFF except POS). Remaining keys added by mergeFlags. */
    var flags = root.__RATEB_OFFLINE_FLAGS__ || { 'offline.pos.complete': true };
    root.__RATEB_OFFLINE_FLAGS__ = flags;

    /* ---- minimal event bus (same API as event-bus.js; full module may replace) ---- */
    if (!root.RatebOfflineEvents) {
        var bus = Object.create(null);
        root.RatebOfflineEvents = {
            on: function (ev, fn) {
                if (!bus[ev]) {
                    bus[ev] = [];
                }
                bus[ev].push(fn);
            },
            off: function (ev, fn) {
                if (!bus[ev]) {
                    return;
                }
                bus[ev] = bus[ev].filter(function (f) { return f !== fn; });
            },
            emit: function (ev, payload) {
                (bus[ev] || []).forEach(function (fn) {
                    try { fn(payload); } catch (e) { /* ignore */ }
                });
            }
        };
    }

    /** Module registry — hard deps listed first in each array. */
    var REGISTRY = {
        storage: ['offline-storage.js'],
        core: ['offline-core.js'],
        network: ['offline-network.js'],
        queue: ['offline-storage.js', 'offline-core.js', 'offline-queue.js'],
        replay: ['offline-queue.js', 'offline-network.js', 'offline-replay.js'],
        sync: ['offline-sync.js'],
        auth: ['offline-storage.js', 'offline-auth.js'],
        crypto: ['offline-storage.js', 'offline-auth.js'],
        rbac: ['offline-storage.js', 'offline-auth.js', 'offline-rbac.js'],
        shell: ['offline-storage.js', 'offline-shell.js'],
        sdk: ['offline-sdk.js'],
        pos: ['offline-pos.js'],
        print: ['offline-print.js'],
        files: ['offline-files.js'],
        monitor: ['offline-monitor.js'],
        diagnostics: ['offline-diagnostics.js'],
        migrations: ['offline-storage.js'],
        inventory: ['offline-queue.js', 'offline-adapter-inventory.js'],
        warehouse: ['offline-adapter-inventory.js', 'offline-adapter-warehouse.js'],
        hr: ['offline-queue.js', 'offline-adapter-hr.js'],
        procurement: ['offline-queue.js', 'offline-adapter-procurement.js'],
        recruitment: ['offline-queue.js', 'offline-adapter-recruitment.js'],
        accounting: ['offline-queue.js', 'offline-adapter-accounting.js'],
        crm: ['offline-queue.js', 'offline-adapter-crm.js'],
        projects: ['offline-queue.js', 'offline-adapter-projects.js'],
        assets: ['offline-queue.js', 'offline-adapter-assets.js'],
        approval: ['offline-queue.js', 'offline-adapter-approval.js'],
        eproc: ['offline-queue.js', 'offline-adapter-eproc.js'],
        manufacturing: ['offline-queue.js', 'offline-adapter-manufacturing.js'],
        payroll: ['offline-queue.js', 'offline-adapter-payroll.js'],
        quality: ['offline-queue.js', 'offline-adapter-quality.js'],
        bi: ['offline-queue.js', 'offline-adapter-bi.js'],
        forms: ['offline-queue.js', 'offline-forms.js'],
        masterdata: ['offline-storage.js', 'offline-master-data.js'],
        opsforms: [
            'offline-adapter-inventory.js', 'offline-adapter-hr.js', 'offline-adapter-procurement.js',
            'offline-adapter-recruitment.js', 'offline-adapter-accounting.js', 'offline-adapter-crm.js',
            'offline-adapter-projects.js', 'offline-adapter-assets.js', 'offline-adapter-approval.js',
            'offline-adapter-eproc.js', 'offline-adapter-manufacturing.js', 'offline-adapter-payroll.js',
            'offline-adapter-quality.js', 'offline-files.js', 'offline-adapter-bi.js',
            'offline-ops-forms.js'
        ]
    };

    function mark(name) {
        try {
            var b = root.__RATEB_BOOT__ || (root.__RATEB_BOOT__ = {});
            b['oa_' + name] = (root.performance && root.performance.now) ? root.performance.now() : Date.now();
        } catch (eM) { /* ignore */ }
    }

    function loadScript(url) {
        if (loaded[url]) {
            return Promise.resolve(url);
        }
        if (inflight[url]) {
            return inflight[url];
        }
        inflight[url] = new Promise(function (resolve, reject) {
            var s = root.document.createElement('script');
            s.src = url;
            s.async = false;
            s.onload = function () {
                loaded[url] = true;
                delete inflight[url];
                resolve(url);
            };
            s.onerror = function () {
                delete inflight[url];
                reject(new Error('oa_load_failed:' + url));
            };
            (root.document.body || root.document.documentElement).appendChild(s);
        });
        return inflight[url];
    }

    function ensureOne(name) {
        var files = REGISTRY[name];
        if (!files) {
            return Promise.reject(new Error('oa_unknown_module:' + name));
        }
        var chain = Promise.resolve();
        files.forEach(function (file) {
            chain = chain.then(function () {
                return loadScript(modBase + file);
            });
        });
        return chain.then(function () {
            mark('mod_' + name);
            return name;
        });
    }

    function ensure(names) {
        var list = Array.isArray(names) ? names : [names];
        var p = Promise.resolve();
        list.forEach(function (n) {
            p = p.then(function () { return ensureOne(n); });
        });
        return p;
    }

    function mergeFlags(incoming) {
        if (!incoming || typeof incoming !== 'object') {
            return flags;
        }
        Object.keys(incoming).forEach(function (k) {
            flags[k] = !!incoming[k];
        });
        return flags;
    }

    function scheduleIdle(fn, timeoutMs) {
        try {
            if (typeof root.requestIdleCallback === 'function') {
                root.requestIdleCallback(function () { fn(); }, { timeout: timeoutMs || 4000 });
                return;
            }
        } catch (eIdle) { /* ignore */ }
        root.setTimeout(fn, timeoutMs || 1200);
    }

    function unlockRequired(options) {
        options = options || {};
        var f = flags;
        if (!(f['offline.enabled'] && f['offline.read_cache'] && f['offline.auth.unlock'])) {
            return false;
        }
        try {
            if (root.navigator && root.navigator.onLine === false) {
                return true;
            }
        } catch (e0) { /* ignore */ }
        try {
            if (/offline-shell\.html/i.test(String((root.location && root.location.pathname) || ''))) {
                return true;
            }
        } catch (e1) { /* ignore */ }
        return options.forceAuth === true;
    }

    function configureLoaded(options) {
        options = options || {};
        var enabled = !!flags['offline.enabled'];
        if (root.RatebOfflineQueue && typeof root.RatebOfflineQueue.configure === 'function') {
            root.RatebOfflineQueue.configure({
                enabled: enabled,
                apiBase: options.apiBase || '',
                clientQueueMax: typeof options.clientQueueMax === 'number' ? options.clientQueueMax : 500
            });
        }
        if (root.RatebOfflineTransport && typeof root.RatebOfflineTransport.configure === 'function') {
            root.RatebOfflineTransport.configure({ enabled: enabled });
        }
        if (root.RatebOfflineConnectivity && typeof root.RatebOfflineConnectivity.configure === 'function') {
            root.RatebOfflineConnectivity.configure({
                probeUrl: options.probeUrl || (options.apiBase
                    ? String(options.apiBase).replace(/\/$/, '') + '/status'
                    : null)
            });
        }
        if (typeof root.RatebOffline.__sdkConfigure === 'function') {
            root.RatebOffline.__sdkConfigure(options);
        }
    }

    function startHeavy(options) {
        options = options || lastInitOptions || {};
        var enabled = !!flags['offline.enabled'];
        return ensure(['queue', 'network', 'replay', 'sync', 'sdk', 'core']).then(function () {
            configureLoaded(options);
            if (enabled && options.startConnectivity === true
                && root.RatebOfflineConnectivity
                && typeof root.RatebOfflineConnectivity.start === 'function') {
                root.RatebOfflineConnectivity.start();
            }
            if (enabled && options.startScheduler === true
                && root.RatebOfflineReplayScheduler
                && typeof root.RatebOfflineReplayScheduler.start === 'function') {
                root.RatebOfflineReplayScheduler.start(options.schedulerIntervalMs || 15000);
            }
            mark('heavy_ready');
            if (root.RatebOfflineEvents) {
                root.RatebOfflineEvents.emit('sdk:flags', statusPayload());
            }
        });
    }

    function loadIdleModules(options) {
        scheduleIdle(function () {
            startHeavy(options).then(function () {
                return ensure(['shell', 'forms', 'diagnostics']).then(function () {
                    try {
                        if (root.RatebOfflineShellAdapter
                            && typeof root.RatebOfflineShellAdapter.startAutoCapture === 'function') {
                            root.RatebOfflineShellAdapter.startAutoCapture();
                        }
                    } catch (eCap) { /* ignore */ }
                    mark('idle_complete');
                });
            }).catch(function () { /* best-effort */ });
        }, 2800);
    }

    function statusPayload() {
        return {
            enabled: !!flags['offline.enabled'],
            read_cache: !!flags['offline.read_cache'],
            auth_unlock: !!flags['offline.auth.unlock'],
            rbac_cache: !!flags['offline.rbac.cache'],
            version: VERSION,
            oa: true
        };
    }

    function init(options) {
        options = options || {};
        lastInitOptions = options;
        if (options.flags && typeof options.flags === 'object') {
            mergeFlags(options.flags);
        }
        mark('init');

        // Already fully booted: merge flags only (same as sdk.js Phase 13.1).
        if (booted && root.RatebOfflineSchema) {
            if (root.RatebOfflineEvents) {
                root.RatebOfflineEvents.emit('sdk:flags', statusPayload());
            }
            return statusPayload();
        }

        var critical = ['storage'];
        if (unlockRequired(options)) {
            critical.push('auth');
            if (flags['offline.rbac.cache']) {
                critical.push('rbac');
            }
        }

        ensure(critical).then(function () {
            configureLoaded(options);
            booted = true;
            mark('critical_ready');
            if (root.RatebOfflineEvents) {
                root.RatebOfflineEvents.emit('sdk:ready', statusPayload());
            }
            try {
                root.dispatchEvent(new Event('rateb-offline-sdk-ready'));
            } catch (eE) { /* ignore */ }

            // Heavy path only after interactive / idle — never block first paint.
            if (options.startConnectivity === true || options.startScheduler === true) {
                startHeavy(options).catch(function () { /* ignore */ });
            } else {
                loadIdleModules(options);
            }
        }).catch(function (err) {
            try {
                if (root.console && root.console.warn) {
                    root.console.warn('[RatebOffline OA]', err && err.message ? err.message : err);
                }
            } catch (eW) { /* ignore */ }
        });

        return statusPayload();
    }

    function flagOn() {
        return !!flags['offline.enabled'];
    }

    function loadAdapterForPath() {
        try {
            var path = String((root.location && root.location.pathname) || '').toLowerCase();
            if (/\/pos(\/|$)/.test(path)) {
                return ensure('pos');
            }
            if (/\/inventory|\/warehouse|\/stock/.test(path)) {
                return ensure(['inventory', 'warehouse']);
            }
            if (/\/hr|\/human-resources|\/attendance/.test(path)) {
                return ensure('hr');
            }
            if (/\/procurement|\/purchase/.test(path)) {
                return ensure('procurement');
            }
            if (/\/recruit/.test(path)) {
                return ensure('recruitment');
            }
            if (/\/accounting|\/journal/.test(path)) {
                return ensure('accounting');
            }
            if (/\/crm|\/lead/.test(path)) {
                return ensure('crm');
            }
            if (/\/project/.test(path)) {
                return ensure('projects');
            }
            if (/\/asset/.test(path)) {
                return ensure('assets');
            }
            if (/\/approval/.test(path)) {
                return ensure('approval');
            }
            if (/\/payroll/.test(path)) {
                return ensure('payroll');
            }
            if (/\/quality|\/inspection/.test(path)) {
                return ensure('quality');
            }
            if (/\/document|\/file/.test(path)) {
                return ensure('files');
            }
            if (/\/bi|\/report|\/dashboard/.test(path)) {
                return ensure('bi');
            }
        } catch (eP) { /* ignore */ }
        return Promise.resolve();
    }

    // On-demand: first navigation interaction under a module path.
    try {
        if (root.document && !root.document.__ratebOaPathLoad) {
            root.document.__ratebOaPathLoad = true;
            root.document.addEventListener('click', function () {
                loadAdapterForPath();
            }, true);
        }
    } catch (eClick) { /* ignore */ }

    root.RatebOffline = {
        __oaBootstrap: true,
        version: VERSION,
        init: init,
        mergeFlags: mergeFlags,
        ensure: ensure,
        loadPhase: function (phase) {
            if (phase === 'idle' || phase === 'heavy') {
                return startHeavy(lastInitOptions || {});
            }
            if (phase === 'auth') {
                return ensure(['auth', 'crypto']);
            }
            if (phase === 'rbac') {
                return ensure('rbac');
            }
            if (phase === 'adapter') {
                return loadAdapterForPath();
            }
            return Promise.resolve();
        },
        isBooted: function () { return booted; },
        isEnabled: flagOn,
        flags: function () { return Object.assign({}, flags); },
        // Stubs until offline-sdk.js merges real helpers (read live flags).
        isInventoryEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.inventory.movements']);
        },
        isReadCacheEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.read_cache']);
        },
        isAuthUnlockEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.read_cache'] && flags['offline.auth.unlock']);
        },
        isRbacCacheEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.read_cache']
                && flags['offline.auth.unlock'] && flags['offline.rbac.cache']);
        },
        queue: function () { return root.RatebOfflineQueue || null; },
        transport: function () { return root.RatebOfflineTransport || null; },
        connectivity: function () { return root.RatebOfflineConnectivity || null; },
        auth: function () { return root.RatebOfflineAuthLock || null; },
        rbac: function () { return root.RatebOfflineRbacCache || null; },
        shell: function () { return root.RatebOfflineShellAdapter || null; },
        schema: function () { return root.RatebOfflineSchema || null; },
        /** Load unlock stack only when needed (Phase 7). */
        ensureAuth: function () { return ensure(['auth', 'crypto']); },
        ensureRbac: function () { return ensure('rbac'); }
    };

    mark('bootstrap');
})(typeof window !== 'undefined' ? window : globalThis);
