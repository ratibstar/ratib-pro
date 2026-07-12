/* Rateb Enterprise Offline SW — Phase 14+ (ops page cache + shell; does not replace pos-sw.js) */
'use strict';

var ASSET_CACHE = 'rateb-erp-assets-v14';
var OPS_PAGE_CACHE = 'rateb-erp-ops-pages-v14';
var ALLOWLIST_CACHE = 'rateb-erp-ops-allowlist-v14';
var FALLBACK_URL = 'offline-shell.html';
var BYPASS_HEADER = 'X-Rateb-SW-Bypass';
var ALLOWLIST_URL = 'assets/offline/ops-page-allowlist.json';

/**
 * Runtime paths loaded from ops-page-allowlist.json (generated from
 * offline/config/ops-page-allowlist.php). Paths are never hardcoded here.
 * @type {string[]}
 */
var OPS_PATHS = [];

function allowlistRequestUrl() {
    return new URL(ALLOWLIST_URL, self.registration.scope).href;
}

function applyAllowlistPayload(payload) {
    var paths = payload && Array.isArray(payload.paths) ? payload.paths : [];
    OPS_PATHS = paths.map(function (p) {
        return String(p || '').replace(/^\/+|\/+$/g, '');
    }).filter(function (p) {
        return p !== '';
    });
    return OPS_PATHS.length;
}

function loadOpsAllowlist() {
    var url = allowlistRequestUrl();
    return caches.open(ALLOWLIST_CACHE).then(function (cache) {
        return fetchBypass(url).then(function (res) {
            if (res && res.ok) {
                return res.clone().json().then(function (payload) {
                    applyAllowlistPayload(payload);
                    return cache.put(url, res).then(function () {
                        return OPS_PATHS.length;
                    });
                });
            }
            return cache.match(url).then(function (hit) {
                if (!hit) {
                    return 0;
                }
                return hit.json().then(function (payload) {
                    applyAllowlistPayload(payload);
                    return OPS_PATHS.length;
                });
            });
        }).catch(function () {
            return cache.match(url).then(function (hit) {
                if (!hit) {
                    return 0;
                }
                return hit.json().then(function (payload) {
                    applyAllowlistPayload(payload);
                    return OPS_PATHS.length;
                });
            });
        });
    }).catch(function () {
        return 0;
    });
}

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

function matchOpsPath(pathname) {
    var p = String(pathname || '').replace(/\/+$/, '').toLowerCase();
    var list = OPS_PATHS;
    // Longer paths first so hr/attendance beats attendance-like noise.
    var sorted = list.slice().sort(function (a, b) {
        return String(b).length - String(a).length;
    });
    for (var i = 0; i < sorted.length; i++) {
        var a = String(sorted[i] || '').replace(/^\/+|\/+$/g, '').toLowerCase();
        if (!a) {
            continue;
        }
        var re = new RegExp('(^|/)' + a.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '(/|$)', 'i');
        if (re.test(p)) {
            return a;
        }
    }
    return null;
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

/** Phase 14 — serve allowlisted ops page snapshot when offline. */
function opsPageFallback(request, url) {
    var candidates = [];
    try {
        if (request && request.url) {
            candidates.push(request.url);
        }
    } catch (e) { /* ignore */ }
    try {
        if (url) {
            candidates.push(url.origin + url.pathname);
            if (url.href) {
                candidates.push(url.href);
            }
        }
    } catch (e2) { /* ignore */ }
    return caches.open(OPS_PAGE_CACHE).then(function (cache) {
        var chain = Promise.resolve(null);
        candidates.forEach(function (key) {
            if (!key) {
                return;
            }
            chain = chain.then(function (found) {
                if (found) {
                    return found;
                }
                return cache.match(key);
            });
        });
        return chain.then(function (hit) {
            return hit || null;
        });
    }).catch(function () {
        return null;
    });
}

function putOpsPageFromMessage(data) {
    var html = data && data.html ? String(data.html) : '';
    if (!html) {
        return Promise.resolve(false);
    }
    var urls = [];
    if (data.url) {
        urls.push(String(data.url));
    }
    if (data.path) {
        try {
            var origin = self.location.origin;
            var path = String(data.path);
            if (path.charAt(0) !== '/') {
                path = '/' + path;
            }
            urls.push(origin + path);
        } catch (e) { /* ignore */ }
    }
    if (!urls.length) {
        return Promise.resolve(false);
    }
    var res = new Response(html, {
        status: 200,
        headers: {
            'Content-Type': 'text/html; charset=utf-8',
            'X-Rateb-Offline': '1',
            'X-Rateb-Ops-Page': '1',
            'Cache-Control': 'no-store'
        }
    });
    return caches.open(OPS_PAGE_CACHE).then(function (cache) {
        return Promise.all(urls.map(function (u) {
            return cache.put(u, res.clone()).catch(function () { return null; });
        })).then(function () { return true; });
    }).catch(function () { return false; });
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

function navigateOfflineFallback(request, url) {
    if (matchOpsPath(url.pathname)) {
        return opsPageFallback(request, url).then(function (hit) {
            return hit || offlineShellFallback();
        });
    }
    return offlineShellFallback();
}

self.addEventListener('install', function (event) {
    self.skipWaiting();
    // Precache allowlist + offline-shell.html + shell helper scripts via bypass fetch (no recursion).
    event.waitUntil(
        loadOpsAllowlist().then(function () {
            return caches.open(ASSET_CACHE).then(function (cache) {
                var key = shellRequestUrl();
                var base;
                try {
                    base = self.registration.scope;
                } catch (e) {
                    base = self.location.origin + '/rateb-erp/public/';
                }
                if (base.slice(-1) !== '/') {
                    base += '/';
                }
                var helpers = [
                    base + 'assets/offline/rateb-offline.js',
                    base + 'assets/offline/erp-offline-shell-rbac.js',
                    base + 'assets/offline/erp-offline-shell-auth.js',
                    base + 'assets/offline/ops-page-allowlist.json'
                ];
                return fetchBypass(key).then(function (res) {
                    var putShell = (res && res.ok)
                        ? cache.put(key, res)
                        : cache.put(key, inlineOfflineShellResponse());
                    return putShell.then(function () {
                        return Promise.all(helpers.map(function (u) {
                            return fetchBypass(u).then(function (r) {
                                if (r && r.ok) {
                                    return cache.put(u, r);
                                }
                                return null;
                            }).catch(function () { return null; });
                        }));
                    });
                }).catch(function () {
                    return cache.put(key, inlineOfflineShellResponse());
                });
            });
        }).catch(function () { /* ignore */ })
    );
});

self.addEventListener('activate', function (event) {
    // Claim clients so offline /admin navigations are handled by this SW.
    // Without claim, Chrome shows its native "No internet" page.
    event.waitUntil(
        loadOpsAllowlist().then(function () {
            return caches.keys().then(function (keys) {
                return Promise.all(keys.map(function (name) {
                    if (name.indexOf('rateb-erp-assets-') === 0 && name !== ASSET_CACHE) {
                        return caches.delete(name);
                    }
                    if (name.indexOf('rateb-erp-ops-pages-') === 0 && name !== OPS_PAGE_CACHE) {
                        return caches.delete(name);
                    }
                    if (name.indexOf('rateb-erp-ops-allowlist-') === 0 && name !== ALLOWLIST_CACHE) {
                        return caches.delete(name);
                    }
                    return Promise.resolve();
                }));
            });
        }).then(function () {
            return self.clients.claim();
        }).catch(function () {
            return self.clients.claim();
        })
    );
});

self.addEventListener('message', function (event) {
    var data = event.data || {};
    if (data.type === 'CACHE_ERP_OPS_PAGE') {
        event.waitUntil(putOpsPageFromMessage(data));
    }
    if (data.type === 'RELOAD_OPS_ALLOWLIST') {
        event.waitUntil(loadOpsAllowlist());
    }
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

    // HTML navigations (non-auth, non-POS): network only; offline → ops page or shell.
    if (request.method === 'GET' && (request.mode === 'navigate'
        || (request.headers && (request.headers.get('accept') || '').indexOf('text/html') !== -1))) {
        event.respondWith(
            networkOnly(request).catch(function () {
                return navigateOfflineFallback(request, url);
            })
        );
    }
});
