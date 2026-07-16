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
 * Phase Z: resilient precache — one 404 must not invalidate the whole install.
 */
/* eslint-disable no-restricted-globals */
var CACHE = 'rateb-offline-v2-host-pz';
var PRECACHE = [
    './index.html',
    './manifest.webmanifest',
    './js/hci.js',
    './js/package-manager.js',
    './js/boot.js',
    './js/runtime/runtime.js',
    './js/router/router.js',
    './js/ui/shell.js',
    './js/sync/sync-engine.js',
    './js/modules/module-sdk.js',
    './js/business/business-module-framework.js',
    './js/business/reference-module.js',
    './js/business/identity-module.js',
    './js/business/inventory-module.js',
    './js/business/procurement-module.js',
    './js/business/sales-module.js',
    './js/business/accounting-module.js',
    './js/business/crm-module.js',
    './js/business/hr-module.js',
    './js/business/manufacturing-module.js',
    './modules/module-manifest.example.json',
    './routes/route-manifest.json',
    './js/routes/route-manifest.json',
    './js/db/migrations.js',
    './js/db/sqlite-runtime.js',
    './css/host.css',
    './css/shell.css',
    './vendor/sqlite/index.mjs',
    './vendor/sqlite/sqlite3.wasm',
    './vendor/sqlite/sqlite3-opfs-async-proxy.js',
    './vendor/sqlite/sqlite3-worker1.mjs'
];

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
        // Install succeeds when host shell + sqlite vendor are present.
        var critical = [
            './index.html',
            './js/boot.js',
            './js/hci.js',
            './js/db/sqlite-runtime.js',
            './vendor/sqlite/index.mjs',
            './vendor/sqlite/sqlite3.wasm',
            './vendor/sqlite/sqlite3-opfs-async-proxy.js'
        ];
        var criticalMiss = failed.filter(function (r) {
            return critical.indexOf(r.rel) !== -1;
        });
        if (criticalMiss.length) {
            throw new Error('v2_precache_critical_missing:' + criticalMiss.map(function (r) {
                return r.rel;
            }).join(','));
        }
        return { ok: true, cached: rows.length - failed.length, failed: failed };
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
                if (k !== CACHE && k.indexOf('rateb-offline-v2-host-') === 0) {
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

    // Never handle requests outside this SW's scope path segment /v2/
    if (url.origin !== self.location.origin) {
        return;
    }
    if (url.pathname.indexOf('/v2/') === -1 && !/\/v2$/.test(url.pathname)) {
        return;
    }

    // Never treat admin/PHP/offline-shell as app documents
    if (/\/admin(\/|$)/i.test(url.pathname) || /offline-shell\.html$/i.test(url.pathname)) {
        return;
    }

    if (req.mode === 'navigate') {
        event.respondWith(
            caches.match('./index.html').then(function (hit) {
                return hit || fetch(req).catch(function () {
                    return caches.match('./index.html');
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
