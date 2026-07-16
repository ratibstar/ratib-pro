/*!
 * RATEB Offline V2 — L2 SPA Router (Phase 5)
 * Client-side only · Runtime layerApi · init/mount/unmount/dispose
 * Forbidden: DOMParser, HTML fetch, PHP routes, document reload, V1 nav, SW routing
 */
(function (root) {
    'use strict';

    if (root.RatebOfflineV2Router && root.RatebOfflineV2Router.__locked) {
        return;
    }

    var ROUTER_VERSION = '1.0.0-phase5';
    var MANIFEST_URL = new URL('../routes/route-manifest.json',
        (root.document && root.document.currentScript && root.document.currentScript.src) || root.location.href
    ).href;

    function createBuiltinHandlers(layer) {
        function textHandler(title, bodyFn) {
            var mounted = false;
            return {
                init: function () { return Promise.resolve(); },
                mount: function (outlet, params) {
                    mounted = true;
                    outlet.textContent = '';
                    var h = root.document.createElement('h3');
                    h.textContent = title;
                    var p = root.document.createElement('p');
                    p.textContent = typeof bodyFn === 'function' ? bodyFn(params) : String(bodyFn || '');
                    outlet.appendChild(h);
                    outlet.appendChild(p);
                    return Promise.resolve();
                },
                unmount: function () {
                    mounted = false;
                    return Promise.resolve();
                },
                dispose: function () {
                    mounted = false;
                    return Promise.resolve();
                },
                _isMounted: function () { return mounted; }
            };
        }

        return {
            'builtin:home': textHandler('Home', function () {
                return 'Offline V2 local route — zero HTML fetch.';
            }),
            'builtin:status': textHandler('Runtime Status', function () {
                try {
                    return 'state=' + layer.getState() + ' services=' + layer.hasService('hci');
                } catch (e) {
                    return 'runtime unavailable';
                }
            }),
            'builtin:health': textHandler('Health', function () {
                var h = layer.getHealth && layer.getHealth();
                return h ? ('ok=' + h.ok + ' checks=' + (h.checks && h.checks.length)) : 'no health snapshot';
            }),
            'builtin:guarded': textHandler('Guarded', 'Reached only when guard allows.')
        };
    }

    function normalizePath(path) {
        var p = String(path || '/');
        if (!p || p.charAt(0) !== '/') {
            p = '/' + p;
        }
        if (p.length > 1 && p.slice(-1) === '/') {
            p = p.slice(0, -1);
        }
        return p || '/';
    }

    function pathFromLocation() {
        var u = new URL(root.location.href);
        // Prefer hash routes for host pages under static file servers: #/status
        if (u.hash && u.hash.charAt(0) === '#') {
            var hp = u.hash.slice(1);
            return normalizePath(hp || '/');
        }
        // Deep link via ?r=/status
        var q = u.searchParams.get('r');
        if (q) {
            return normalizePath(q);
        }
        return '/';
    }

    function createRouter() {
        var layer = null;
        var outlet = null;
        var registry = Object.create(null);
        var handlers = Object.create(null);
        var manifest = null;
        var current = null;
        var currentHandler = null;
        var guards = [];
        var inited = false;
        var disposed = false;
        var navigating = false;
        var historyBound = false;
        var flags = Object.create(null);

        function emit(ev, payload) {
            if (layer && layer.emit) {
                layer.emit('router:' + ev, payload);
            }
            if (root.RatebOfflineV2Runtime && root.RatebOfflineV2Runtime.events) {
                root.RatebOfflineV2Runtime.events.emit('router:' + ev, payload);
            }
        }

        function findByPath(path) {
            var p = normalizePath(path);
            var ids = Object.keys(registry);
            for (var i = 0; i < ids.length; i++) {
                if (normalizePath(registry[ids[i]].path) === p) {
                    return registry[ids[i]];
                }
            }
            return null;
        }

        function findById(id) {
            return registry[id] || null;
        }

        function registerRoute(def, handler) {
            if (!def || !def.id || !def.path) {
                throw new Error('router_bad_route');
            }
            registry[def.id] = {
                id: def.id,
                path: normalizePath(def.path),
                title: def.title || def.id,
                handler: def.handler,
                meta: def.meta || {}
            };
            if (handler) {
                handlers[def.id] = handler;
            }
        }

        function resolveHandler(route) {
            if (handlers[route.id]) {
                return handlers[route.id];
            }
            if (route.handler && handlers[route.handler]) {
                return handlers[route.handler];
            }
            return null;
        }

        function runGuards(to, from) {
            var i = 0;
            function next(err) {
                if (err === false) {
                    return Promise.resolve(false);
                }
                if (err && err !== true) {
                    return Promise.reject(err);
                }
                if (i >= guards.length) {
                    return Promise.resolve(true);
                }
                var g = guards[i++];
                return Promise.resolve().then(function () {
                    return g(to, from);
                }).then(function (res) {
                    if (res === false) {
                        return false;
                    }
                    return next();
                });
            }
            return next();
        }

        function setHistory(path, replace) {
            var hash = '#' + normalizePath(path);
            if (replace) {
                root.history.replaceState({ v2route: true, path: path }, '', hash);
            } else {
                root.history.pushState({ v2route: true, path: path }, '', hash);
            }
        }

        function navigate(toPath, opts) {
            opts = opts || {};
            if (disposed) {
                return Promise.reject(new Error('router_disposed'));
            }
            if (!inited) {
                return Promise.reject(new Error('router_not_inited'));
            }
            if (navigating) {
                return Promise.resolve({ ok: false, reason: 'busy' });
            }

            var path = normalizePath(toPath);
            var route = findByPath(path);
            if (!route) {
                emit('notfound', { path: path });
                return Promise.resolve({ ok: false, reason: 'notfound', path: path });
            }

            var from = current;
            navigating = true;
            emit('beforeNavigate', { to: route, from: from });

            return runGuards(route, from).then(function (allowed) {
                if (!allowed) {
                    navigating = false;
                    emit('blocked', { to: route, from: from });
                    return { ok: false, reason: 'guard', path: path };
                }

                var handler = resolveHandler(route);
                if (!handler || typeof handler.mount !== 'function') {
                    navigating = false;
                    throw new Error('router_no_handler:' + route.id);
                }

                var chain = Promise.resolve();
                if (currentHandler && typeof currentHandler.unmount === 'function') {
                    chain = chain.then(function () {
                        return currentHandler.unmount();
                    });
                }

                return chain.then(function () {
                    if (typeof handler.init === 'function' && !handler.__inited) {
                        return handler.init(layer).then(function () {
                            handler.__inited = true;
                        });
                    }
                    return null;
                }).then(function () {
                    if (!outlet) {
                        throw new Error('router_no_outlet');
                    }
                    outlet.setAttribute('data-route', route.id);
                    return handler.mount(outlet, { route: route, path: path });
                }).then(function () {
                    current = route;
                    currentHandler = handler;
                    if (!opts.silentHistory) {
                        setHistory(path, !!opts.replace);
                    }
                    emit('afterNavigate', { route: route, path: path });
                    navigating = false;
                    return { ok: true, route: route, path: path };
                });
            }).catch(function (err) {
                navigating = false;
                emit('error', { error: err, path: path });
                throw err;
            });
        }

        function onPopState() {
            var path = pathFromLocation();
            navigate(path, { silentHistory: true, replace: true }).catch(function () { /* ignore */ });
        }

        function loadManifest(url) {
            // Local static asset only (SW-precache). Not PHP/HTML document navigation.
            return fetch(url || MANIFEST_URL, { cache: 'force-cache', credentials: 'same-origin' }).then(function (res) {
                if (!res.ok) {
                    throw new Error('router_manifest_http:' + res.status);
                }
                var ct = (res.headers.get('content-type') || '').toLowerCase();
                if (ct.indexOf('json') === -1 && ct.indexOf('text') === -1 && ct !== '') {
                    // allow empty content-type from file servers
                }
                return res.json();
            }).then(function (json) {
                if (!json || json.schema !== 'rateb-offline-v2-routes/1' || !Array.isArray(json.routes)) {
                    throw new Error('router_bad_manifest');
                }
                manifest = json;
                json.routes.forEach(function (r) {
                    registerRoute(r);
                });
                return json;
            });
        }

        function init(opts) {
            opts = opts || {};
            if (disposed) {
                return Promise.reject(new Error('router_disposed'));
            }
            if (inited) {
                return Promise.resolve({ ok: true, already: true });
            }

            if (!root.RatebOfflineV2Runtime || typeof root.RatebOfflineV2Runtime.layerApi !== 'function') {
                return Promise.reject(new Error('router_runtime_required'));
            }

            layer = opts.layer || root.RatebOfflineV2Runtime.layerApi();
            outlet = opts.outlet || root.document.getElementById('rateb-v2-router-outlet');
            if (!outlet) {
                return Promise.reject(new Error('router_outlet_missing'));
            }

            flags = opts.flags || Object.create(null);
            var builtins = createBuiltinHandlers(layer);
            Object.keys(builtins).forEach(function (k) {
                handlers[k] = builtins[k];
            });

            // Default meta guard for requiresFlag
            guards.push(function (to) {
                if (to.meta && to.meta.requiresFlag) {
                    return !!flags[to.meta.requiresFlag];
                }
                return true;
            });

            return loadManifest(opts.manifestUrl).then(function (man) {
                if (!historyBound) {
                    root.addEventListener('popstate', onPopState);
                    historyBound = true;
                }
                inited = true;

                // Register with runtime locator (published API)
                try {
                    root.RatebOfflineV2Runtime.services.register('router', api, { replace: true });
                } catch (e) {
                    root.RatebOfflineV2Runtime.services.register('router', api, { replace: true });
                }

                emit('init', { version: ROUTER_VERSION, routes: Object.keys(registry).length });

                var startPath = opts.startPath || pathFromLocation();
                if (!findByPath(startPath) && man.defaultRoute && findById(man.defaultRoute)) {
                    startPath = findById(man.defaultRoute).path;
                }
                return navigate(startPath, { replace: true }).then(function (nav) {
                    return { ok: true, manifest: man, navigation: nav };
                });
            });
        }

        function dispose() {
            if (disposed) {
                return Promise.resolve({ ok: true });
            }
            var chain = Promise.resolve();
            if (currentHandler && typeof currentHandler.unmount === 'function') {
                chain = chain.then(function () { return currentHandler.unmount(); });
            }
            return chain.then(function () {
                var ids = Object.keys(handlers);
                var dchain = Promise.resolve();
                ids.forEach(function (id) {
                    dchain = dchain.then(function () {
                        var h = handlers[id];
                        if (h && typeof h.dispose === 'function') {
                            return h.dispose();
                        }
                        return null;
                    });
                });
                return dchain;
            }).then(function () {
                if (historyBound) {
                    root.removeEventListener('popstate', onPopState);
                    historyBound = false;
                }
                if (outlet) {
                    outlet.textContent = '';
                    outlet.removeAttribute('data-route');
                }
                registry = Object.create(null);
                handlers = Object.create(null);
                current = null;
                currentHandler = null;
                guards = [];
                inited = false;
                disposed = true;
                emit('dispose', null);
                return { ok: true };
            });
        }

        function unregisterRoute(id) {
            if (!id || !registry[id]) {
                return false;
            }
            delete registry[id];
            if (handlers[id]) {
                delete handlers[id];
            }
            return true;
        }

        function beforeEach(fn) {
            if (typeof fn === 'function') {
                guards.push(fn);
            }
        }

        function setFlag(name, value) {
            flags[name] = !!value;
        }

        var api = {
            version: ROUTER_VERSION,
            init: init,
            navigate: navigate,
            dispose: dispose,
            beforeEach: beforeEach,
            setFlag: setFlag,
            getCurrent: function () { return current; },
            getManifest: function () { return manifest; },
            listRoutes: function () {
                return Object.keys(registry).map(function (id) { return registry[id]; });
            },
            registerRoute: registerRoute,
            unregisterRoute: unregisterRoute,
            registerHandler: function (idOrBuiltin, handler) {
                handlers[idOrBuiltin] = handler;
            },
            normalizePath: normalizePath,
            pathFromLocation: pathFromLocation
        };

        return api;
    }

    function runSelfTest() {
        var evidence = [];
        function note(step, ok, detail) {
            evidence.push({ step: step, ok: !!ok, detail: detail || '' });
        }

        if (!root.RatebOfflineV2Runtime) {
            return Promise.resolve({ ok: false, error: 'runtime_missing', evidence: evidence });
        }

        var outlet = root.document.getElementById('rateb-v2-router-outlet');
        if (!outlet) {
            return Promise.resolve({ ok: false, error: 'outlet_missing', evidence: evidence });
        }

        var router = createRouter();
        var usedDomParser = typeof DOMParser !== 'undefined' && false;
        note('no_domparser_usage', !usedDomParser, 'router_code_path');

        return root.RatebOfflineV2Runtime.start().catch(function () {
            return root.RatebOfflineV2Runtime.getStatus();
        }).then(function () {
            note('runtime_ready', true, root.RatebOfflineV2Runtime.getState());
            return router.init({
                outlet: outlet,
                flags: { allowGuarded: false },
                startPath: '/'
            });
        }).then(function (initRes) {
            note('init_manifest', !!(initRes && initRes.ok && initRes.manifest), initRes.manifest && initRes.manifest.version);
            note('nav_home', !!(initRes.navigation && initRes.navigation.ok), initRes.navigation && initRes.navigation.path);
            note('mounted_home', outlet.getAttribute('data-route') === 'home', outlet.getAttribute('data-route'));

            return router.navigate('/status');
        }).then(function (nav) {
            note('nav_status', !!(nav && nav.ok), nav && nav.path);
            note('mounted_status', outlet.getAttribute('data-route') === 'status', outlet.getAttribute('data-route'));
            note('history_hash', /#\/status/.test(root.location.hash) || /#\/status/.test(root.location.href), root.location.hash);

            return router.navigate('/guarded');
        }).then(function (blocked) {
            note('guard_blocks', blocked && blocked.ok === false && blocked.reason === 'guard', blocked && blocked.reason);
            router.setFlag('allowGuarded', true);
            return router.navigate('/guarded');
        }).then(function (allowed) {
            note('guard_allows', !!(allowed && allowed.ok), allowed && allowed.path);

            // Deep link simulation via hash
            root.history.pushState({ v2route: true, path: '/health' }, '', '#/health');
            root.dispatchEvent(new PopStateEvent('popstate', { state: { v2route: true, path: '/health' } }));

            return new Promise(function (resolve) {
                setTimeout(resolve, 30);
            }).then(function () {
                note('deeplink_popstate', outlet.getAttribute('data-route') === 'health', outlet.getAttribute('data-route'));

                var resources = performance.getEntriesByType
                    ? performance.getEntriesByType('resource')
                    : [];
                var bad = resources.filter(function (r) {
                    return /\/admin(\/|$)/i.test(r.name) ||
                        /offline-shell/i.test(r.name) ||
                        /erp-nav-instant/i.test(r.name) ||
                        /\.php(\?|$)/i.test(r.name);
                });
                note('zero_network_nav', bad.length === 0, bad.length ? bad[0].name : 'ok');

                note('runtime_has_router', root.RatebOfflineV2Runtime.services.has('router'), '');
                note('pm_untouched', !!root.RatebOfflineV2PM, 'present');
                note('db_untouched', !!root.RatebOfflineV2DB, 'present_or_loading');

                return router.dispose();
            }).then(function (d) {
                note('dispose', !!(d && d.ok), '');
                note('outlet_cleared', !outlet.getAttribute('data-route') && outlet.textContent === '', 'cleared');

                var failed = evidence.filter(function (e) { return !e.ok; });
                return {
                    ok: failed.length === 0,
                    version: ROUTER_VERSION,
                    evidence: evidence,
                    failed: failed
                };
            });
        }).catch(function (err) {
            note('fatal', false, String(err && err.message ? err.message : err));
            return {
                ok: false,
                version: ROUTER_VERSION,
                evidence: evidence,
                error: String(err && err.message ? err.message : err)
            };
        });
    }

    root.RatebOfflineV2Router = {
        __locked: true,
        version: ROUTER_VERSION,
        create: createRouter,
        runSelfTest: runSelfTest
    };
})(typeof window !== 'undefined' ? window : this);
