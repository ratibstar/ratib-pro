/* Rateb POS — offline app shell (Phase 4 + 2B fixes) */
'use strict';

var SHELL_CACHE = 'rateb-pos-shell-v6';
var ASSET_CACHE = 'rateb-pos-assets-v6';
var REGISTER_SHELL_PATH = '__rateb_pos_register_shell__';

function registerShellUrl() {
    try {
        return new URL(REGISTER_SHELL_PATH, self.registration.scope).href;
    } catch (e) {
        return self.location.origin + '/rateb-erp/public/' + REGISTER_SHELL_PATH;
    }
}

var OFFLINE_HTML = '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>POS Offline</title><style>body{font-family:system-ui,sans-serif;margin:0;padding:2rem;background:#0f1117;color:#e8eaed;text-align:center}h1{font-size:1.25rem}a{color:#a78bfa;display:inline-block;margin:.5rem}p{opacity:.85}</style></head><body><h1 id="t">نقطة البيع غير متصلة</h1><p id="m">جاري البحث عن نسخة محفوظة من شاشة البيع…</p><p id="links" hidden><a id="a1" href="#">شاشة البيع</a> · <a id="a2" href="#">شاشة البيع /register</a></p><script>(function(){var SHELL="rateb-pos-shell-v6";var KEY="__rateb_pos_register_shell__";function showFail(){var m=document.getElementById("m");var links=document.getElementById("links");if(m)m.textContent="افتح شاشة البيع مرة واحدة وأنت متصل بالإنترنت، ثم أعد المحاولة دون إنترنت. التقارير والإعدادات تحتاج اتصال.";if(links)links.hidden=false;try{var u=new URL(location.href);var cid=u.searchParams.get("company_id")||"";var q=cid?("?company_id="+cid):"";var base=u.pathname.replace(/\\/register\\/?$/,"").replace(/\\/(reports|settings|dashboard|shifts|terminals).*$/,"");var a1=document.getElementById("a1");var a2=document.getElementById("a2");if(a1)a1.href=base+q;if(a2)a2.href=base.replace(/\\/?$/,"")+"/register"+q;}catch(e){}}function useResponse(res){if(!res)return Promise.resolve(false);return res.text().then(function(html){if(!html||html.indexOf("data-pos-register")<0)return false;document.open();document.write(html);document.close();return true;});}if(!("caches" in window)){showFail();return;}caches.open(SHELL).then(function(cache){var u=new URL(location.href);var candidates=[new URL(KEY,location.origin+"/rateb-erp/public/").href,u.origin+u.pathname,u.href,u.origin+u.pathname.replace(/\\/register\\/?$/,""),u.origin+u.pathname.replace(/\\/register\\/?$/,"")+(u.search||""),u.origin+u.pathname.replace(/\\/?$/,"")+"/register",u.origin+u.pathname.replace(/\\/?$/,"")+"/register"+(u.search||"")];return candidates.reduce(function(p,url){return p.then(function(done){if(done)return true;return cache.match(url).then(useResponse);});},Promise.resolve(false)).then(function(done){if(done)return;return cache.keys().then(function(keys){var next=Promise.resolve(false);keys.forEach(function(req){next=next.then(function(done){if(done)return true;var href=typeof req==="string"?req:(req&&req.url)||"";if(href.indexOf("/pos")<0)return false;return cache.match(req).then(useResponse);});});return next;});}).then(function(done){if(!done)showFail();});}).catch(showFail);})();</script></body></html>';

function isPosNavigation(url) {
    return url.pathname.indexOf('/pos') !== -1 || url.pathname.indexOf('/admin/ops/pos') !== -1;
}

/** Register shell: /pos, /pos/register (with optional public prefix) */
function isRegisterShellPath(pathname) {
    var p = String(pathname || '').replace(/\/+$/, '');
    return /\/pos(\/register)?$/i.test(p);
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

function putShell(request, response) {
    if (!response || !response.ok) {
        return Promise.resolve();
    }
    var url = new URL(request.url || request);
    if (!isRegisterShellPath(url.pathname) && !isPosNavigation(url)) {
        return Promise.resolve();
    }
    return caches.open(SHELL_CACHE).then(function (cache) {
        var shellKey = registerShellUrl();
        var bare = url.origin + url.pathname;
        var withQuery = url.origin + url.pathname + url.search;
        var altRegister = /\/register$/i.test(url.pathname)
            ? url.origin + url.pathname.replace(/\/register$/i, '')
            : url.origin + url.pathname.replace(/\/?$/, '') + '/register';
        var tasks = [
            cache.put(bare, response.clone()),
            cache.put(withQuery, response.clone()),
            cache.put(shellKey, response.clone())
        ];
        if (isRegisterShellPath(url.pathname)) {
            tasks.push(cache.put(altRegister, response.clone()));
            tasks.push(cache.put(altRegister + url.search, response.clone()));
        }
        return Promise.all(tasks);
    }).catch(function () { /* ignore quota */ });
}

function shellFallback(request) {
    return caches.open(SHELL_CACHE).then(function (cache) {
        var url = new URL(request.url);
        var shellKey = registerShellUrl();
        var candidates = [
            request,
            url.href,
            url.origin + url.pathname,
            url.origin + url.pathname + url.search,
            shellKey,
            url.origin + url.pathname.replace(/\/register$/i, ''),
            url.origin + url.pathname.replace(/\/register$/i, '') + url.search,
            url.origin + url.pathname.replace(/\/?$/, '') + '/register',
            url.origin + url.pathname.replace(/\/?$/, '') + '/register' + url.search
        ];
        return candidates.reduce(function (chain, key) {
            return chain.then(function (hit) {
                if (hit) {
                    return hit;
                }
                return cache.match(key);
            });
        }, Promise.resolve(null)).then(function (cached) {
            if (cached) {
                return cached;
            }
            return cache.keys().then(function (keys) {
                var best = null;
                keys.forEach(function (req) {
                    try {
                        var href = typeof req === 'string' ? req : req.url;
                        var u = new URL(href, self.location.origin);
                        if (isRegisterShellPath(u.pathname) || href.indexOf(REGISTER_SHELL_PATH) !== -1) {
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
            // Migrate last register shell from older shell caches before delete.
            var oldShells = keys.filter(function (k) {
                return k.indexOf('rateb-pos-shell-') === 0 && k !== SHELL_CACHE;
            });
            return caches.open(SHELL_CACHE).then(function (fresh) {
                return Promise.all(oldShells.map(function (name) {
                    return caches.open(name).then(function (old) {
                        return old.keys().then(function (reqs) {
                            return Promise.all(reqs.map(function (req) {
                                return old.match(req).then(function (res) {
                                    if (!res) {
                                        return;
                                    }
                                    var href = typeof req === 'string' ? req : (req.url || '');
                                    if (href.indexOf(REGISTER_SHELL_PATH) !== -1 || isRegisterShellPath(new URL(href, self.location.origin).pathname)) {
                                        return fresh.put(req, res.clone()).then(function () {
                                            return fresh.put(registerShellUrl(), res.clone());
                                        });
                                    }
                                });
                            }));
                        });
                    });
                })).then(function () {
                    return Promise.all(keys.map(function (key) {
                        if (key !== SHELL_CACHE && key !== ASSET_CACHE) {
                            return caches.delete(key);
                        }
                    }));
                });
            });
        }).then(function () {
            return self.clients.claim();
        })
    );
});

self.addEventListener('message', function (event) {
    var data = event.data || {};
    if (data.type === 'PIN_REGISTER_SHELL' && data.url) {
        event.waitUntil(
            fetch(data.url, {
                credentials: 'same-origin',
                headers: { Accept: 'text/html', 'X-Rateb-Shell-Warm': '1' }
            }).then(function (response) {
                return putShell(data.url, response);
            }).catch(function () { /* ignore */ })
        );
    }
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
                event.waitUntil(putShell(event.request, response.clone()));
                return response;
            }).catch(function () {
                return shellFallback(event.request);
            })
        );
        return;
    }

    if (isRegisterShellPath(url.pathname)
        && (event.request.headers.get('accept') || '').indexOf('text/html') !== -1) {
        event.respondWith(
            fetch(event.request).then(function (response) {
                event.waitUntil(putShell(event.request, response.clone()));
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
                    event.waitUntil(
                        caches.open(ASSET_CACHE).then(function (cache) {
                            return cache.put(event.request, clone);
                        })
                    );
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
