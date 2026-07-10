/* Rateb POS — offline app shell (Phase 4 + 2B fixes) */
'use strict';

var SHELL_CACHE = 'rateb-pos-shell-v4';
var ASSET_CACHE = 'rateb-pos-assets-v4';

var OFFLINE_HTML = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>POS Offline</title><style>body{font-family:system-ui,sans-serif;margin:0;padding:2rem;background:#0f1117;color:#e8eaed;text-align:center}h1{font-size:1.25rem}a{color:#a78bfa}</style></head><body><h1>POS is offline</h1><p>Use the register screen you opened while online. Reports and settings need a connection.</p><p><a href="./register">Back to register</a></p></body></html>';

function isPosNavigation(url) {
    return url.pathname.indexOf('/pos') !== -1 || url.pathname.indexOf('/admin/ops/pos') !== -1;
}

function isPosAsset(url) {
    return url.pathname.indexOf('/assets/pos/') !== -1
        || url.pathname.indexOf('/assets/js/theme.js') !== -1;
}

function isApiRequest(url) {
    return url.pathname.indexOf('/api/') !== -1;
}

function offlineHtmlResponse() {
    return new Response(OFFLINE_HTML, {
        status: 200,
        headers: {
            'Content-Type': 'text/html; charset=utf-8',
            'X-Rateb-Offline': '1'
        }
    });
}

function offlineJsonResponse() {
    return new Response(JSON.stringify({ ok: false, offline: true }), {
        status: 503,
        headers: {
            'Content-Type': 'application/json',
            'X-Rateb-Offline': '1'
        }
    });
}

function emptyAssetResponse() {
    return new Response('', {
        status: 204,
        statusText: 'Offline',
        headers: { 'X-Rateb-Offline': '1' }
    });
}

function shellFallback(request) {
    return caches.open(SHELL_CACHE).then(function (cache) {
        return cache.match(request).then(function (cached) {
            if (cached) {
                return cached;
            }
            return cache.match(request.url.split('?')[0]);
        }).then(function (cached) {
            if (cached) {
                return cached;
            }
            return cache.keys().then(function (keys) {
                var registerReq = null;
                keys.forEach(function (req) {
                    if (req.url.indexOf('/pos/register') !== -1 || req.url.indexOf('/ops/pos/register') !== -1) {
                        registerReq = req;
                    }
                });
                if (registerReq) {
                    return cache.match(registerReq);
                }
                if (request.mode === 'navigate' || (request.headers.get('accept') || '').indexOf('text/html') !== -1) {
                    return offlineHtmlResponse();
                }
                return offlineJsonResponse();
            });
        });
    });
}

self.addEventListener('install', function (event) {
    self.skipWaiting();
    event.waitUntil(caches.open(ASSET_CACHE));
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys.map(function (key) {
                if (key !== SHELL_CACHE && key !== ASSET_CACHE) {
                    return caches.delete(key);
                }
            }));
        }).then(function () {
            return self.clients.claim();
        })
    );
});

self.addEventListener('fetch', function (event) {
    if (event.request.method !== 'GET') {
        return;
    }

    var url = new URL(event.request.url);
    if (url.origin !== self.location.origin) {
        return;
    }

    if (event.request.mode === 'navigate' && isPosNavigation(url)) {
        event.respondWith(
            fetch(event.request).then(function (response) {
                if (response && response.ok) {
                    var clone = response.clone();
                    caches.open(SHELL_CACHE).then(function (cache) {
                        cache.put(event.request, clone);
                    });
                }
                return response;
            }).catch(function () {
                return shellFallback(event.request);
            })
        );
        return;
    }

    if (isPosAsset(url)) {
        event.respondWith(
            fetch(event.request).then(function (response) {
                if (response && response.ok) {
                    var clone = response.clone();
                    caches.open(ASSET_CACHE).then(function (cache) {
                        cache.put(event.request, clone);
                    });
                }
                return response;
            }).catch(function () {
                return caches.match(event.request).then(function (cached) {
                    return cached || emptyAssetResponse();
                });
            })
        );
        return;
    }

    if (isApiRequest(url) && isPosNavigation(url)) {
        return;
    }
});
