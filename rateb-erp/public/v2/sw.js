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
 */
/* eslint-disable no-restricted-globals */
var CACHE = 'rateb-offline-v2-host-p10';
var PRECACHE = [
    './',
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
    './modules/module-manifest.example.json',
    './routes/route-manifest.json',
    './js/db/migrations.js',
    './js/db/sqlite-runtime.js',
    './css/host.css',
    './css/shell.css',
    './vendor/sqlite/index.mjs',
    './vendor/sqlite/sqlite3.wasm',
    './vendor/sqlite/sqlite3-opfs-async-proxy.js'
];

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE).then(function (cache) {
            return cache.addAll(PRECACHE);
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
        caches.match(req).then(function (hit) {
            return hit || fetch(req).then(function (res) {
                return res;
            }).catch(function () {
                return hit || Response.error();
            });
        })
    );
});
