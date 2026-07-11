/* Rateb Enterprise Offline SW — Phase 10 ERP shell (additive; does not replace pos-sw.js) */
'use strict';

var ASSET_CACHE = 'rateb-erp-assets-v10';
var FALLBACK_URL = 'offline-shell.html';

function isPosPath(pathname) {
    var p = String(pathname || '');
    return p.indexOf('/pos') !== -1 || p.indexOf('/admin/ops/pos') !== -1;
}

function isApiPath(pathname) {
    return String(pathname || '').indexOf('/api/') !== -1;
}

function isLoginPost(request, pathname) {
    if (!request || String(request.method || 'GET').toUpperCase() !== 'POST') {
        return false;
    }
    var p = String(pathname || '');
    return /\/login\/?$/i.test(p) || /\/admin\/login\/?$/i.test(p) || /\/company\/login\/?$/i.test(p);
}

/** First-party static assets only — never POS assets, never HTML documents. */
function isCacheableAsset(url) {
    var path = String(url.pathname || '');
    if (isPosPath(path) || isApiPath(path)) {
        return false;
    }
    if (path.indexOf('/assets/pos/') !== -1) {
        return false;
    }
    if (path.indexOf('/assets/') === -1 && path.indexOf('/rateb-offline') === -1) {
        return false;
    }
    return /\.(css|js|mjs|woff2?|ttf|otf|map|svg|png|jpe?g|gif|webp|ico)$/i.test(path)
        || path.indexOf('/assets/offline/') !== -1;
}

function offlineJsonResponse() {
    return new Response(JSON.stringify({ ok: false, offline: true }), {
        status: 503,
        headers: {
            'Content-Type': 'application/json',
            'X-Rateb-Offline': '1',
            'Cache-Control': 'no-store'
        }
    });
}

function offlineShellFallback() {
    return caches.match(new URL(FALLBACK_URL, self.registration.scope).href).then(function (hit) {
        if (hit) {
            return hit;
        }
        return fetch(new URL(FALLBACK_URL, self.registration.scope).href).then(function (res) {
            return res;
        }).catch(function () {
            return new Response(
                '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>Offline</title></head>'
                + '<body><p>Cached ERP shell unavailable. Reconnect and open Admin once.</p></body></html>',
                {
                    status: 200,
                    headers: {
                        'Content-Type': 'text/html; charset=utf-8',
                        'X-Rateb-Offline': '1'
                    }
                }
            );
        });
    });
}

function networkOnly(request) {
    return fetch(request);
}

function assetNetworkFirst(request) {
    return fetch(request).then(function (response) {
        if (response && response.ok && request.method === 'GET') {
            var copy = response.clone();
            caches.open(ASSET_CACHE).then(function (cache) {
                cache.put(request, copy);
            }).catch(function () { /* quota */ });
        }
        return response;
    }).catch(function () {
        return caches.match(request).then(function (hit) {
            return hit || caches.match(new URL(request.url).pathname).then(function (byPath) {
                return byPath || Promise.reject(new Error('asset_offline_miss'));
            });
        });
    });
}

self.addEventListener('install', function (event) {
    self.skipWaiting();
    event.waitUntil(
        caches.open(ASSET_CACHE).then(function (cache) {
            return cache.addAll([
                new URL(FALLBACK_URL, self.registration.scope).href
            ]).catch(function () { /* optional precache */ });
        })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys.map(function (name) {
                if (name.indexOf('rateb-erp-assets-') === 0 && name !== ASSET_CACHE) {
                    return caches.delete(name);
                }
                return Promise.resolve();
            }));
        }).then(function () {
            return self.clients.claim();
        })
    );
});

self.addEventListener('fetch', function (event) {
    var request = event.request;
    var url;
    try {
        url = new URL(request.url);
    } catch (e) {
        return;
    }

    // Never interfere with POS — leave request to network / pos-sw.js controller.
    if (isPosPath(url.pathname)) {
        return;
    }

    // Never cache login POST.
    if (isLoginPost(request, url.pathname)) {
        return;
    }

    // API: never cache; offline → JSON stub only.
    if (isApiPath(url.pathname)) {
        event.respondWith(
            fetch(request).catch(function () {
                return offlineJsonResponse();
            })
        );
        return;
    }

    // First-party static assets — network first, cache fallback (no HTML).
    if (request.method === 'GET' && isCacheableAsset(url)) {
        event.respondWith(
            assetNetworkFirst(request).catch(function () {
                return new Response('/* offline */', {
                    status: 503,
                    headers: { 'Content-Type': 'text/plain', 'X-Rateb-Offline': '1' }
                });
            })
        );
        return;
    }

    // HTML navigations: network only — never cache authenticated documents / CSRF.
    // Offline → static offline-shell.html (shell chrome may hydrate from IndexedDB).
    if (request.method === 'GET' && (request.mode === 'navigate'
        || (request.headers.get('accept') || '').indexOf('text/html') !== -1)) {
        event.respondWith(
            networkOnly(request).catch(function () {
                return offlineShellFallback();
            })
        );
    }
});
