/* Rateb POS — offline app shell (Phase 4 + 2B fixes) */
'use strict';

var SHELL_CACHE = 'rateb-pos-shell-v5';
var ASSET_CACHE = 'rateb-pos-assets-v5';
var REGISTER_SHELL_KEY = 'rateb-pos-register-shell';

var OFFLINE_HTML = '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>POS Offline</title><style>body{font-family:system-ui,sans-serif;margin:0;padding:2rem;background:#0f1117;color:#e8eaed;text-align:center}h1{font-size:1.25rem}a{color:#a78bfa;display:inline-block;margin-top:1rem}</style></head><body><h1>نقطة البيع غير متصلة</h1><p>أعد فتح شاشة البيع التي فتحتها أثناء الاتصال. التقارير والإعدادات تحتاج إنترنت.</p><p><a id="back" href="/rateb-erp/public/admin/ops/pos">العودة لشاشة البيع</a></p><script>try{var u=new URL(location.href);var a=document.getElementById("back");if(a){a.href=u.pathname.replace(/\\/(reports|settings|dashboard|shifts|terminals).*$/,"")+(u.search||"?company_id="+(u.searchParams.get("company_id")||""));}}catch(e){}</script></body></html>';

function isPosNavigation(url) {
    return url.pathname.indexOf('/pos') !== -1 || url.pathname.indexOf('/admin/ops/pos') !== -1;
}

/** Register shell: /pos, /pos/register, /admin/ops/pos, /admin/ops/pos/register */
function isRegisterShellPath(pathname) {
    var p = String(pathname || '').replace(/\/+$/, '');
    return /\/(admin\/ops\/)?pos(\/register)?$/i.test(p)
        || /\/ops\/pos(\/register)?$/i.test(p);
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

function cacheRegisterShell(cache, response) {
    var clone = response.clone();
    return cache.put(REGISTER_SHELL_KEY, clone).catch(function () { /* ignore */ });
}

function putShell(request, response) {
    if (!response || !response.ok) {
        return Promise.resolve();
    }
    return caches.open(SHELL_CACHE).then(function (cache) {
        var url = new URL(request.url);
        var tasks = [
            cache.put(request, response.clone()),
            cache.put(url.origin + url.pathname, response.clone())
        ];
        if (isRegisterShellPath(url.pathname)) {
            tasks.push(cacheRegisterShell(cache, response));
        }
        return Promise.all(tasks);
    }).catch(function () { /* ignore quota */ });
}

function shellFallback(request) {
    return caches.open(SHELL_CACHE).then(function (cache) {
        var url = new URL(request.url);
        return cache.match(request).then(function (cached) {
            if (cached) {
                return cached;
            }
            return cache.match(url.origin + url.pathname);
        }).then(function (cached) {
            if (cached) {
                return cached;
            }
            // Canonical register shell saved on last successful online visit.
            return cache.match(REGISTER_SHELL_KEY);
        }).then(function (cached) {
            if (cached) {
                return cached;
            }
            return cache.keys().then(function (keys) {
                var best = null;
                keys.forEach(function (req) {
                    if (typeof req === 'string') {
                        return;
                    }
                    try {
                        var u = new URL(req.url);
                        if (isRegisterShellPath(u.pathname)) {
                            best = req;
                        }
                    } catch (e) { /* ignore */ }
                });
                if (best) {
                    return cache.match(best);
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
                putShell(event.request, response);
                return response;
            }).catch(function () {
                return shellFallback(event.request);
            })
        );
        return;
    }

    // Warm / soft-nav HTML GETs for the register shell (not only navigate mode).
    if (isRegisterShellPath(url.pathname)
        && (event.request.headers.get('accept') || '').indexOf('text/html') !== -1) {
        event.respondWith(
            fetch(event.request).then(function (response) {
                putShell(event.request, response);
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
