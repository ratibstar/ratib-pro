/**
 * TEMPORARY — RATIB Offline runtime diagnostics.
 * Remove after Offline warm-chain is confirmed.
 * Console: [RATIB OFFLINE] PASS|FAIL …
 * Inspect: window.ratebOfflineDebug()
 */
(function (root) {
    'use strict';

    var state = {
        stopped: false,
        currentStep: 0,
        lastPass: null,
        lastFail: null,
        events: []
    };

    function emit(pass, step, file, fn, reason) {
        var row = {
            pass: !!pass,
            step: step,
            file: file || '',
            function: fn || '',
            reason: reason || ''
        };
        state.events.push(row);
        state.currentStep = step;
        if (pass) {
            state.lastPass = row;
            try {
                console.log('[RATIB OFFLINE]', 'PASS', 'step=' + step, 'file=' + row.file, 'function=' + row.function, 'reason=' + row.reason);
            } catch (e) { /* ignore */ }
            return true;
        }
        state.lastFail = row;
        state.stopped = true;
        try {
            console.error('[RATIB OFFLINE]', 'FAIL', 'step=' + step, 'file=' + row.file, 'function=' + row.function, 'reason=' + row.reason);
            console.error('[RATIB OFFLINE]', 'STOPPED at first FAIL — later steps not executed');
        } catch (e2) { /* ignore */ }
        return false;
    }

    function pass(step, file, fn, reason) {
        if (state.stopped) {
            return false;
        }
        return emit(true, step, file, fn, reason);
    }

    function fail(step, file, fn, reason) {
        if (state.stopped) {
            return false;
        }
        return emit(false, step, file, fn, reason);
    }

    function stopped() {
        return !!state.stopped;
    }

    function shellUrlFromCfg() {
        var cfg = root.__RATEB_ERP_SHELL_OFFLINE__ || {};
        var scope = cfg.serviceWorkerScope || '';
        if (!scope && root.location) {
            scope = root.location.origin + '/rateb-erp/public/';
        }
        if (scope && scope.slice(-1) !== '/') {
            scope += '/';
        }
        return scope ? (scope + 'offline-shell.html') : '';
    }

    function ratebOfflineDebug() {
        var cfg = root.__RATEB_ERP_SHELL_OFFLINE__ || null;
        var ctrl = null;
        var swUrl = '';
        try {
            ctrl = root.navigator && root.navigator.serviceWorker
                ? root.navigator.serviceWorker.controller
                : null;
            swUrl = ctrl && ctrl.scriptURL ? String(ctrl.scriptURL) : '';
        } catch (e) { /* ignore */ }

        var out = {
            currentStep: state.currentStep,
            stopped: state.stopped,
            lastFail: state.lastFail,
            events: state.events.slice(),
            currentSW: swUrl,
            currentController: ctrl ? 'present' : 'none',
            currentFlags: cfg && cfg.flags ? cfg.flags : null,
            company_id: cfg ? cfg.company_id : null,
            user_id: cfg ? cfg.user_id : null,
            serviceWorkerScope: cfg ? cfg.serviceWorkerScope : null,
            expectedShellUrl: shellUrlFromCfg(),
            currentCache: null,
            offlineStatus: root.navigator && root.navigator.onLine === false ? 'offline' : 'online'
        };

        var print = function () {
            try {
                console.log('[RATIB OFFLINE] === ratebOfflineDebug() ===');
                console.log('Current Step', out.currentStep, out.stopped ? '(STOPPED)' : '');
                console.log('Current SW', out.currentSW || '(none)');
                console.log('Current Cache', out.currentCache);
                console.log('Current Controller', out.currentController);
                console.log('Current Flags', out.currentFlags);
                console.log('Current Offline Status', out.offlineStatus);
                console.log('Last Fail', out.lastFail);
                console.log('[RATIB OFFLINE] full dump', out);
            } catch (e2) { /* ignore */ }
            return out;
        };

        if (!('caches' in root)) {
            out.currentCache = { error: 'caches_unavailable' };
            return Promise.resolve(print());
        }
        return root.caches.keys().then(function (names) {
            out.currentCache = { names: names || [] };
            var want = ['rateb-erp-coexist-v1', 'rateb-erp-assets-v14', 'rateb-erp-ops-pages-v14'];
            return Promise.all(want.map(function (name) {
                if ((names || []).indexOf(name) < 0) {
                    return { name: name, present: false, keys: [] };
                }
                return root.caches.open(name).then(function (cache) {
                    return cache.keys().then(function (reqs) {
                        var keys = (reqs || []).map(function (r) {
                            return typeof r === 'string' ? r : (r && r.url) || '';
                        });
                        return {
                            name: name,
                            present: true,
                            hasOfflineShell: keys.some(function (k) {
                                return /offline-shell\.html/i.test(k);
                            }),
                            keys: keys
                        };
                    });
                });
            })).then(function (detail) {
                out.currentCache.detail = detail;
                return print();
            });
        }).catch(function (err) {
            out.currentCache = { error: String(err && err.message ? err.message : err) };
            return print();
        });
    }

    // Steps 1–2: layout injection. Step 3 is logged by erp-shell-bootstrap.js on load.
    pass(1, 'views/layouts/main.php', 'offline_read_cache_block', 'main.php offline read_cache block rendered');
    if (root.__RATEB_ERP_SHELL_OFFLINE__ && typeof root.__RATEB_ERP_SHELL_OFFLINE__ === 'object') {
        pass(2, 'views/layouts/main.php', '__RATEB_ERP_SHELL_OFFLINE__', 'config object created on window');
    } else {
        fail(2, 'views/layouts/main.php', '__RATEB_ERP_SHELL_OFFLINE__', 'config object missing on window');
    }

    root.RatebOfflineTrace = {
        pass: pass,
        fail: fail,
        stopped: stopped,
        state: state,
        shellUrlFromCfg: shellUrlFromCfg
    };
    root.ratebOfflineDebug = ratebOfflineDebug;
})(typeof window !== 'undefined' ? window : globalThis);
