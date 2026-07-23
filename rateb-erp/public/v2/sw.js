/**
 * RATEB Offline V2 — Phase 1 installability Service Worker.
 * Scope: this directory only (./ under /public/v2/).
 *
 * FORBIDDEN:
 * - V1 SW reuse (pos-sw.js, rateb-offline-sw.js)
 * - HTML / PHP document routing
 * - ERP ops page caching
 * - Navigation rewrites to snapshots
 *
 * ALLOWED:
 * - Precache V2 host static assets for zero-network host boot
 * - Network pass-through for anything else in scope
 *
 * Offline Bootstrap: installation is complete only when every boot asset is cached.
 */
/* eslint-disable no-restricted-globals */
/* v15: POS reservation lifecycle + recovery under ./js/modules/pos/ */
var CACHE = 'rateb-offline-v2-bootstrap-v15';
var PRECACHE = [
    './index.html',
    './manifest.webmanifest',
    '../assets/offline/platform/hci/hci.js',
    '../assets/offline/platform/runtime/runtime.js',
    './js/package-manager.js',
    './js/boot.js',
    './js/router/router.js',
    './js/ui/shell.js',
    '../assets/offline/platform/sync/sync-engine.js',
    './js/modules/module-sdk.js',
    '../assets/offline/platform/support/business-module-framework.js',
    './js/business/reference-module.js',
    '../assets/offline/platform/identity/identity-module.js',
    './js/business/inventory-module.js',
    './js/business/procurement-module.js',
    './js/business/sales-module.js',
    './js/business/accounting-module.js',
    './js/business/crm-module.js',
    './js/business/hr-module.js',
    './js/business/manufacturing-module.js',
    './js/modules/pos/pos-catalog.js',
    './js/modules/pos/pos-cart.js',
    './js/modules/pos/pos-reservation.js',
    './js/modules/pos/pos-stock.js',
    './js/modules/pos/pos-module.js',
    './modules/module-manifest.example.json',
    './js/routes/route-manifest.json',
    '../assets/offline/platform/db/migrations.js',
    '../assets/offline/platform/db/sqlite-runtime.js',
    './vendor/sqlite/index.mjs',
    './vendor/sqlite/sqlite3.wasm',
    './vendor/sqlite/sqlite3-opfs-async-proxy.js',
    './vendor/sqlite/sqlite3-worker1.mjs',
    './css/host.css',
    './css/shell.css'
];
var APP_SHELL_URL = new URL('./index.html', self.registration.scope).href;

function precacheUrl(cache, rel) {
    var url;
    try {
        url = new URL(rel, self.registration.scope).href;
    } catch (eUrl) {
        return Promise.resolve({ rel: rel, ok: false, error: 'bad_url' });
    }
    return fetch(url, { cache: 'no-cache', credentials: 'same-origin' }).then(function (res) {
        if (!res || !res.ok) {
            return { rel: rel, ok: false, status: res ? res.status : 0 };
        }
        return cache.put(url, res.clone()).then(function () {
            return { rel: rel, ok: true, status: res.status };
        });
    }).catch(function (err) {
        return { rel: rel, ok: false, error: String(err && err.message ? err.message : err) };
    });
}

function precacheAll(cache) {
    return Promise.all(PRECACHE.map(function (rel) {
        return precacheUrl(cache, rel);
    })).then(function (rows) {
        var failed = rows.filter(function (r) { return !r.ok; });
        if (failed.length) {
            throw new Error('v2_precache_required_missing:' + failed.map(function (r) {
                return r.rel;
            }).join(','));
        }
        return { ok: true, cached: rows.length, failed: [] };
    });
}

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE).then(function (cache) {
            return precacheAll(cache);
        }).then(function () {
            return self.skipWaiting();
        })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys.map(function (k) {
                var owned = k.indexOf('rateb-offline-v2-host-') === 0 ||
                    k.indexOf('rateb-offline-v2-bootstrap-') === 0;
                if (k !== CACHE && owned) {
                    return caches.delete(k);
                }
                return null;
            }));
        }).then(function () {
            return self.clients.claim();
        })
    );
});

self.addEventListener('fetch', function (event) {
    var req = event.request;
    var url = new URL(req.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    // Never treat admin/PHP/offline-shell as app documents
    if (/\/admin(\/|$)/i.test(url.pathname) || /offline-shell\.html$/i.test(url.pathname)) {
        return;
    }

    var isV2 = url.pathname.indexOf('/v2/') !== -1 || /\/v2$/.test(url.pathname);
    if (!isV2) {
        return;
    }

    if (req.mode === 'navigate') {
        event.respondWith(
            caches.open(CACHE).then(function (cache) {
                return cache.match(APP_SHELL_URL).then(function (hit) {
                    if (hit) {
                        return hit;
                    }
                    return fetch(req).then(function (res) {
                        if (!res || !res.ok) {
                            throw new Error('v2_navigation_http_' + (res ? res.status : 0));
                        }
                        return cache.put(APP_SHELL_URL, res.clone()).then(function () {
                            return res;
                        });
                    });
                });
            })
        );
        return;
    }

    event.respondWith(
        caches.match(req, { ignoreSearch: true }).then(function (hit) {
            if (hit) {
                return hit;
            }
            return fetch(req).then(function (res) {
                if (res && res.ok && req.method === 'GET') {
                    var copy = res.clone();
                    caches.open(CACHE).then(function (cache) {
                        cache.put(req, copy);
                    }).catch(function () { /* ignore */ });
                }
                return res;
            }).catch(function () {
                if (req.destination === 'document' || req.mode === 'navigate') {
                    return caches.match('./index.html');
                }
                return caches.match(req.url).then(function (again) {
                    return again || Response.error();
                });
            });
        })
    );
});

self.addEventListener('message', function (event) {
    var data = event.data || {};
    if (data.type !== 'RATEB_V2_VERIFY_PRECACHE') {
        return;
    }
    var port = event.ports && event.ports[0];
    event.waitUntil(
        caches.open(CACHE).then(function (cache) {
            return cache.keys();
        }).then(function (requests) {
            var cachedUrls = Object.create(null);
            requests.forEach(function (request) {
                cachedUrls[request.url] = true;
            });
            var missing = PRECACHE.filter(function (rel) {
                var url = new URL(rel, self.registration.scope).href;
                return !cachedUrls[url];
            });
            if (port) {
                port.postMessage({
                    ok: missing.length === 0,
                    cache: CACHE,
                    cached: PRECACHE.length - missing.length,
                    required: PRECACHE.length,
                    missing: missing
                });
            }
        }).catch(function (err) {
            if (port) {
                port.postMessage({
                    ok: false,
                    cache: CACHE,
                    error: String(err && err.message ? err.message : err)
                });
            }
        })
    );
});
