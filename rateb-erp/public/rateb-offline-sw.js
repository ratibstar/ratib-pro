/* Rateb Enterprise Offline SW — Phase 13.1 (real offline-shell; does not replace pos-sw.js) */
'use strict';

var ASSET_CACHE = 'rateb-erp-assets-v13.1';
var FALLBACK_URL = 'offline-shell.html';
var BYPASS_HEADER = 'X-Rateb-SW-Bypass';

function isPosPath(pathname) {
    var p = String(pathname || '');
    return /\/pos(\/|$)/i.test(p)
        || /\/admin\/ops\/pos(\/|$)/i.test(p)
        || /\/assets\/pos\//i.test(p);
}

function isApiPath(pathname) {
    return String(pathname || '').indexOf('/api/') !== -1;
}

function isAuthPath(pathname) {
    var p = String(pathname || '');
    return /\/login(\/|$)/i.test(p)
        || /\/logout(\/|$)/i.test(p)
        || /\/password\//i.test(p)
        || /\/api\/login/i.test(p)
        || /\/api\/qr-login/i.test(p)
        || /\/login\/2fa/i.test(p)
        || /\/login\/barcode/i.test(p)
        || /\/login\/scan/i.test(p)
        || /\/login\/badge/i.test(p);
}

function isOfflineShellUrl(url) {
    try {
        return /\/offline-shell\.html$/i.test(String(url.pathname || ''));
    } catch (e) {
        return false;
    }
}

function hasBypassHeader(request) {
    try {
        return !!(request && request.headers && request.headers.get(BYPASS_HEADER) === '1');
    } catch (e) {
        return false;
    }
}

function shellRequestUrl() {
    return new URL(FALLBACK_URL, self.registration.scope).href;
}

/** Network fetch that skips this SW (no recursion). */
function fetchBypass(url) {
    var headers = new Headers();
    headers.set(BYPASS_HEADER, '1');
    return fetch(url, { headers: headers, credentials: 'same-origin', cache: 'no-cache' });
}

/** First-party static assets only — never POS, never HTML, never auth. */
function isCacheableAsset(url) {
    var path = String(url.pathname || '');
    if (isPosPath(path) || isApiPath(path) || isAuthPath(path) || isOfflineShellUrl(url)) {
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

/** Last-resort inline stub — used only when real shell cannot be cached/fetched. */
function inlineOfflineShellResponse() {
    var body = '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8">'
        + '<meta name="viewport" content="width=device-width,initial-scale=1">'
        + '<title>RATEB ERP — Offline</title>'
        + '<style>body{font-family:system-ui,sans-serif;margin:0;padding:2rem;background:#0f1117;color:#e8eaed;text-align:center}</style>'
        + '</head><body>'
        + '<h1>وضع عدم الاتصال</h1>'
        + '<p>Cached shell unavailable. Reconnect and open Admin once.</p>'
        + '</body></html>';
    return new Response(body, {
        status: 200,
        headers: {
            'Content-Type': 'text/html; charset=utf-8',
            'X-Rateb-Offline': '1',
            'Cache-Control': 'no-store'
        }
    });
}

/**
 * Prefer cached real offline-shell.html.
 * Network via bypass header only (never re-enters this fetch handler).
 * Inline stub last.
 */
function offlineShellFallback() {
    var key = shellRequestUrl();
    return caches.open(ASSET_CACHE).then(function (cache) {
        return cache.match(key).then(function (hit) {
            if (hit) {
                return hit;
            }
            return fetchBypass(key).then(function (res) {
                if (res && res.ok) {
                    cache.put(key, res.clone()).catch(function () { /* quota */ });
                    return res;
                }
                return inlineOfflineShellResponse();
            }).catch(function () {
                return inlineOfflineShellResponse();
            });
        });
    }).catch(function () {
        return inlineOfflineShellResponse();
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
            return hit || Promise.reject(new Error('asset_offline_miss'));
        });
    });
}

self.addEventListener('install', function (event) {
    self.skipWaiting();
    // Precache real offline-shell.html via bypass fetch (no recursion).
    event.waitUntil(
        caches.open(ASSET_CACHE).then(function (cache) {
            var key = shellRequestUrl();
            return fetchBypass(key).then(function (res) {
                if (res && res.ok) {
                    return cache.put(key, res);
                }
                return cache.put(key, inlineOfflineShellResponse());
            }).catch(function () {
                return cache.put(key, inlineOfflineShellResponse());
            });
        }).catch(function () { /* ignore */ })
    );
});

self.addEventListener('activate', function (event) {
    // Do NOT call claim on clients — avoids stealing open POS tabs from pos-sw.js.
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys.map(function (name) {
                if (name.indexOf('rateb-erp-assets-') === 0 && name !== ASSET_CACHE) {
                    return caches.delete(name);
                }
                return Promise.resolve();
            }));
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

    // Bypass header: network passthrough (used to fetch real offline-shell without recursion).
    if (hasBypassHeader(request)) {
        return;
    }

    // offline-shell.html: cache → bypass-network → inline. Never recurse.
    if (isOfflineShellUrl(url)) {
        event.respondWith(offlineShellFallback());
        return;
    }

    // Never own /pos — no respondWith (pos-sw.js / network remain authoritative).
    if (isPosPath(url.pathname)) {
        return;
    }

    // Never intercept auth/login/logout/password/session pages.
    if (isAuthPath(url.pathname)) {
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

    // HTML navigations (non-auth, non-POS): network only; offline → shell fallback.
    if (request.method === 'GET' && (request.mode === 'navigate'
        || (request.headers && (request.headers.get('accept') || '').indexOf('text/html') !== -1))) {
        event.respondWith(
            networkOnly(request).catch(function () {
                return offlineShellFallback();
            })
        );
    }
});
