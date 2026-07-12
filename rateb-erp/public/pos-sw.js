/* Rateb POS — offline app shell (Phase 4 + 2B + ERP coexist) */
'use strict';

var SHELL_CACHE = 'rateb-pos-shell-v8';
var ASSET_CACHE = 'rateb-pos-assets-v8';
var ERP_COEXIST_CACHE = 'rateb-erp-coexist-v6';
var ERP_OPS_PAGE_CACHE = 'rateb-erp-ops-pages-v14';
var REGISTER_SHELL_PATH = '__rateb_pos_register_shell__';
var ERP_OFFLINE_SHELL = 'offline-shell.html';

/** Phase 14 — mirrors offline/config/ops-page-allowlist.php (ERP coexist). */
var ERP_OPS_PATHS = [
    'stock-movements',
    'warehouse-transfers',
    'inventory-audits',
    'inventory',
    'warehouses',
    'hr/attendance',
    'hr/leaves',
    'purchase-requests',
    'purchase-orders',
    'rfq'
];

function registerShellUrl() {
    try {
        return new URL(REGISTER_SHELL_PATH, self.registration.scope).href;
    } catch (e) {
        return self.location.origin + '/rateb-erp/public/' + REGISTER_SHELL_PATH;
    }
}

var OFFLINE_HTML = '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>POS Offline</title><style>body{font-family:system-ui,sans-serif;margin:0;padding:2rem;background:#0f1117;color:#e8eaed;text-align:center}h1{font-size:1.25rem}a{color:#a78bfa;display:inline-block;margin:.5rem}p{opacity:.85}</style></head><body><h1 id="t">نقطة البيع غير متصلة</h1><p id="m">جاري البحث عن نسخة محفوظة من شاشة البيع…</p><p id="links" hidden><a id="a1" href="#">شاشة البيع</a> · <a id="a2" href="#">شاشة البيع /register</a></p><script>(function(){var SHELL="rateb-pos-shell-v8";var KEY="__rateb_pos_register_shell__";function showFail(){var m=document.getElementById("m");var links=document.getElementById("links");if(m)m.textContent="افتح شاشة البيع مرة واحدة وأنت متصل بالإنترنت، ثم أعد المحاولة دون إنترنت. التقارير والإعدادات تحتاج اتصال.";if(links)links.hidden=false;try{var u=new URL(location.href);var cid=u.searchParams.get("company_id")||"";var q=cid?("?company_id="+cid):"";var base=u.pathname.replace(/\\/register\\/?$/,"").replace(/\\/(reports|settings|dashboard|shifts|terminals).*$/,"");var a1=document.getElementById("a1");var a2=document.getElementById("a2");if(a1)a1.href=base+q;if(a2)a2.href=base.replace(/\\/?$/,"")+"/register"+q;}catch(e){}}function useResponse(res){if(!res)return Promise.resolve(false);return res.text().then(function(html){if(!html||html.indexOf("data-pos-register")<0)return false;document.open();document.write(html);document.close();return true;});}if(!("caches" in window)){showFail();return;}caches.open(SHELL).then(function(cache){var u=new URL(location.href);var candidates=[new URL(KEY,location.origin+"/rateb-erp/public/").href,u.origin+u.pathname,u.href,u.origin+u.pathname.replace(/\\/register\\/?$/,""),u.origin+u.pathname.replace(/\\/register\\/?$/,"")+(u.search||""),u.origin+u.pathname.replace(/\\/?$/,"")+"/register",u.origin+u.pathname.replace(/\\/?$/,"")+"/register"+(u.search||"")];return candidates.reduce(function(p,url){return p.then(function(done){if(done)return true;return cache.match(url).then(useResponse);});},Promise.resolve(false)).then(function(done){if(done)return;return cache.keys().then(function(keys){var next=Promise.resolve(false);keys.forEach(function(req){next=next.then(function(done){if(done)return true;var href=typeof req==="string"?req:(req&&req.url)||"";if(href.indexOf("/pos")<0)return false;return cache.match(req).then(useResponse);});});return next;});}).then(function(done){if(!done)showFail();});}).catch(showFail);})();</script></body></html>';

function isPosNavigation(url) {
    return url.pathname.indexOf('/pos') !== -1 || url.pathname.indexOf('/admin/ops/pos') !== -1;
}

/** Register shell: /pos, /pos/register (with optional public prefix) */
function isRegisterShellPath(pathname) {
    var p = String(pathname || '').replace(/\/+$/, '');
    return /\/pos(\/register)?$/i.test(p);
}

/** Online biometric gate — offline should land on cached register + lock, not require gate HTML. */
function isBiometricGatePath(pathname) {
    var p = String(pathname || '').replace(/\/+$/, '');
    return /\/pos\/biometric$/i.test(p);
}

function registerPathFromBiometric(url) {
    try {
        var u = new URL(url.href || url);
        u.pathname = u.pathname.replace(/\/biometric\/?$/i, '/register');
        return u;
    } catch (e) {
        return url;
    }
}

function isPosAsset(url) {
    return url.pathname.indexOf('/assets/pos/') !== -1
        || url.pathname.indexOf('/assets/js/theme.js') !== -1;
}

function isApiRequest(url) {
    return url.pathname.indexOf('/api/') !== -1;
}

/** Auth / login — never intercept (same contract as ERP SW). */
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

function erpOfflineShellUrl() {
    try {
        return new URL(ERP_OFFLINE_SHELL, self.registration.scope).href;
    } catch (e) {
        return self.location.origin + '/rateb-erp/public/' + ERP_OFFLINE_SHELL;
    }
}

function erpInlineShellResponse() {
    var body = '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8">'
        + '<meta name="viewport" content="width=device-width,initial-scale=1">'
        + '<title>RATEB ERP — Offline</title>'
        + '<style>body{font-family:system-ui,sans-serif;margin:0;padding:2rem;background:#0f1117;color:#e8eaed;text-align:center}</style>'
        + '</head><body>'
        + '<h1>وضع عدم الاتصال</h1>'
        + '<p>Cached ERP shell unavailable. Reconnect and open Admin once.</p>'
        + '</body></html>';
    return new Response(body, {
        status: 200,
        headers: {
            'Content-Type': 'text/html; charset=utf-8',
            'X-Rateb-Offline': '1',
            'X-Rateb-Coexist': 'pos-sw'
        }
    });
}

function matchErpOpsPath(pathname) {
    var p = String(pathname || '').replace(/\/+$/, '').toLowerCase();
    var sorted = ERP_OPS_PATHS.slice().sort(function (a, b) {
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

function erpOpsPageFallback(request, url) {
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
    return caches.open(ERP_OPS_PAGE_CACHE).then(function (cache) {
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
        return chain;
    }).catch(function () {
        return null;
    });
}

function putErpOpsPageFromMessage(data) {
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
            var path = String(data.path);
            if (path.charAt(0) !== '/') {
                path = '/' + path;
            }
            urls.push(self.location.origin + path);
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
            'X-Rateb-Coexist': 'pos-sw'
        }
    });
    return caches.open(ERP_OPS_PAGE_CACHE).then(function (cache) {
        return Promise.all(urls.map(function (u) {
            return cache.put(u, res.clone()).catch(function () { return null; });
        })).then(function () { return true; });
    }).catch(function () { return false; });
}

/**
 * Smart coexist: when this SW owns the shared scope, serve ERP ops page
 * (if allowlisted) then offline-shell for non-POS admin navigations.
 * @param {Request} [request]
 * @param {URL} [url]
 */
function erpAdminOfflineFallback(request, url) {
    var tryOps = (url && matchErpOpsPath(url.pathname))
        ? erpOpsPageFallback(request, url)
        : Promise.resolve(null);
    return tryOps.then(function (opsHit) {
        if (opsHit) {
            return opsHit;
        }
        var key = erpOfflineShellUrl();
        return caches.match(key).then(function (hit) {
            if (hit) {
                return hit;
            }
            return caches.open(ERP_COEXIST_CACHE).then(function (cache) {
                return cache.match(key).then(function (cached) {
                    if (cached) {
                        return cached;
                    }
                    return caches.keys().then(function (names) {
                        var erpCaches = (names || []).filter(function (n) {
                            return String(n).indexOf('rateb-erp-assets-') === 0
                                || String(n) === ERP_COEXIST_CACHE
                                || String(n) === ERP_OPS_PAGE_CACHE;
                        });
                        return erpCaches.reduce(function (chain, name) {
                            return chain.then(function (found) {
                                if (found) {
                                    return found;
                                }
                                return caches.open(name).then(function (c) {
                                    return c.match(key);
                                });
                            });
                        }, Promise.resolve(null)).then(function (found) {
                            return found || erpInlineShellResponse();
                        });
                    });
                });
            });
        });
    }).catch(function () {
        return erpInlineShellResponse();
    });
}

function warmErpOfflineShell() {
    var base;
    try {
        base = self.registration.scope;
    } catch (e) {
        base = self.location.origin + '/rateb-erp/public/';
    }
    if (base.slice(-1) !== '/') {
        base += '/';
    }
    var urls = [
        base + ERP_OFFLINE_SHELL,
        base + 'assets/offline/rateb-offline.js',
        base + 'assets/offline/erp-offline-shell-auth.js',
        base + 'assets/offline/erp-offline-shell-rbac.js',
        base + 'assets/css/variables.css',
        base + 'assets/css/main.css',
        base + 'assets/css/components.css',
        base + 'assets/css/dark.css',
        base + 'assets/css/rtl.css'
    ];
    return caches.open(ERP_COEXIST_CACHE).then(function (cache) {
        return Promise.all(urls.map(function (key) {
            return fetch(key, {
                credentials: 'same-origin',
                cache: 'no-cache',
                headers: { Accept: '*/*', 'X-Rateb-Shell-Warm': '1' }
            }).then(function (res) {
                if (!res || !res.ok) {
                    try {
                        console.error('[RATIB OFFLINE]', 'FAIL', 'step=11', 'file=pos-sw.js',
                            'function=warmErpOfflineShell.fetch',
                            'reason=fetch not ok status=' + (res ? res.status : 'null') + ' key=' + key);
                    } catch (e0) { /* ignore */ }
                    return null;
                }
                try {
                    console.log('[RATIB OFFLINE]', 'PASS', 'step=11', 'file=pos-sw.js',
                        'function=warmErpOfflineShell.fetch', 'reason=fetch ok status=' + res.status + ' key=' + key);
                } catch (e1) { /* ignore */ }
                var pathnameKey = key;
                try {
                    var ku = new URL(key);
                    pathnameKey = ku.origin + ku.pathname;
                } catch (ePath) { /* ignore */ }
                return cache.put(key, res.clone()).then(function () {
                    return cache.put(pathnameKey, res.clone());
                }).then(function () {
                    try {
                        console.log('[RATIB OFFLINE]', 'PASS', 'step=12', 'file=pos-sw.js',
                            'function=warmErpOfflineShell.cache.put',
                            'reason=cache.put cache=' + ERP_COEXIST_CACHE + ' key=' + key);
                        if (/offline-shell\.html/i.test(key)) {
                            console.log('[RATIB OFFLINE]', 'PASS', 'step=13', 'file=pos-sw.js',
                                'function=warmErpOfflineShell',
                                'reason=offline-shell.html cached in ' + ERP_COEXIST_CACHE);
                        }
                    } catch (e2) { /* ignore */ }
                    return true;
                });
            }).catch(function (err) {
                try {
                    console.error('[RATIB OFFLINE]', 'FAIL', 'step=11', 'file=pos-sw.js',
                        'function=warmErpOfflineShell.fetch',
                        'reason=fetch threw: ' + String(err && err.message ? err.message : err) + ' key=' + key);
                } catch (e3) { /* ignore */ }
                return null;
            });
        }));
    }).catch(function () { return null; });
}

/** Prefer cached ERP offline assets; ignore ?v= query so warm keys still hit. */
function matchErpOfflineCached(request, url) {
    var pathnameKey = '';
    try {
        pathnameKey = url.origin + url.pathname;
    } catch (e0) {
        pathnameKey = '';
    }
    function matchInCache(cache) {
        return cache.match(request).then(function (hit) {
            if (hit) {
                return hit;
            }
            if (!pathnameKey) {
                return null;
            }
            return cache.match(pathnameKey).then(function (hit2) {
                if (hit2) {
                    return hit2;
                }
                return cache.match(pathnameKey, { ignoreSearch: true }).then(function (hit3) {
                    if (hit3) {
                        return hit3;
                    }
                    return cache.keys().then(function (keys) {
                        for (var i = 0; i < keys.length; i++) {
                            try {
                                var href = typeof keys[i] === 'string' ? keys[i] : keys[i].url;
                                var ku = new URL(href);
                                if (ku.pathname === url.pathname) {
                                    return cache.match(keys[i]);
                                }
                            } catch (e1) { /* ignore */ }
                        }
                        return null;
                    });
                });
            });
        });
    }
    return caches.open(ERP_COEXIST_CACHE).then(matchInCache).then(function (hit) {
        if (hit) {
            return hit;
        }
        return caches.keys().then(function (names) {
            var erpCaches = (names || []).filter(function (n) {
                return String(n).indexOf('rateb-erp-coexist-') === 0
                    || String(n).indexOf('rateb-erp-assets-') === 0;
            });
            return erpCaches.reduce(function (chain, name) {
                return chain.then(function (found) {
                    if (found) {
                        return found;
                    }
                    return caches.open(name).then(matchInCache);
                });
            }, Promise.resolve(null));
        });
    }).catch(function () {
        return null;
    });
}

/** Offline must not wait on hanging fetch(); online uses a short race timeout. */
function fetchErpAssetNetwork(request, timeoutMs) {
    var offline = self.navigator && self.navigator.onLine === false;
    if (offline) {
        return Promise.resolve(null);
    }
    var ms = typeof timeoutMs === 'number' ? timeoutMs : 2500;
    var network = fetch(request, { credentials: 'same-origin' }).then(function (response) {
        return response && response.ok ? response : null;
    }).catch(function () {
        return null;
    });
    var timed = new Promise(function (resolve) {
        setTimeout(function () { resolve(null); }, ms);
    });
    return Promise.race([network, timed]);
}

function migrateErpCoexistCaches(keys) {
    var oldCaches = (keys || []).filter(function (k) {
        return String(k).indexOf('rateb-erp-coexist-') === 0 && String(k) !== ERP_COEXIST_CACHE;
    });
    if (!oldCaches.length) {
        return Promise.resolve();
    }
    return caches.open(ERP_COEXIST_CACHE).then(function (fresh) {
        return Promise.all(oldCaches.map(function (name) {
            return caches.open(name).then(function (old) {
                return old.keys().then(function (reqs) {
                    return Promise.all(reqs.map(function (req) {
                        return old.match(req).then(function (res) {
                            if (!res) {
                                return null;
                            }
                            return fresh.put(req, res.clone()).then(function () {
                                try {
                                    var href = typeof req === 'string' ? req : (req.url || '');
                                    var u = new URL(href);
                                    return fresh.put(u.origin + u.pathname, res.clone());
                                } catch (e) {
                                    return null;
                                }
                            });
                        });
                    }));
                });
            });
        }));
    }).catch(function () { /* ignore */ });
}

function isErpOfflineAsset(url) {
    var p = String(url.pathname || '');
    return p.indexOf('/assets/offline/') !== -1
        || /\/offline-shell\.html$/i.test(p)
        || /\/assets\/css\/(variables|main|components|dark|rtl|light|dashboard)\.css$/i.test(p);
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

function emptyAssetResponse(request) {
    var url;
    try {
        url = new URL(typeof request === 'string' ? request : request.url);
    } catch (e) {
        url = { pathname: '' };
    }
    var path = String(url.pathname || '').toLowerCase();
    var body = '';
    var type = 'text/plain; charset=utf-8';
    if (/\.js$/i.test(path)) {
        body = '/* rateb-pos offline stub */';
        type = 'application/javascript; charset=utf-8';
    } else if (/\.css$/i.test(path)) {
        body = '/* rateb-pos offline stub */';
        type = 'text/css; charset=utf-8';
    } else if (/\.(png|jpe?g|gif|webp|svg|ico)$/i.test(path)) {
        // 1x1 transparent GIF
        var bin = atob('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        var bytes = new Uint8Array(bin.length);
        for (var i = 0; i < bin.length; i++) {
            bytes[i] = bin.charCodeAt(i);
        }
        return new Response(bytes, {
            status: 200,
            headers: {
                'Content-Type': 'image/gif',
                'X-Rateb-Offline': '1',
                'Cache-Control': 'no-store'
            }
        });
    }
    return new Response(body, {
        status: 200,
        headers: {
            'Content-Type': type,
            'X-Rateb-Offline': '1',
            'Cache-Control': 'no-store'
        }
    });
}

function matchAsset(request) {
    var url = new URL(request.url);
    return caches.open(ASSET_CACHE).then(function (cache) {
        return cache.match(request).then(function (hit) {
            if (hit) {
                return hit;
            }
            return cache.match(url.origin + url.pathname);
        }).then(function (hit) {
            if (hit) {
                return hit;
            }
            return caches.match(request).then(function (any) {
                return any || cache.match(url.pathname);
            });
        });
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

/** Prefer HTML shell when falling back from a document navigation. */
function wantsHtmlShell(request) {
    if (!request || typeof request === 'string') {
        return true;
    }
    try {
        if (request.mode === 'navigate') {
            return true;
        }
        var accept = request.headers && request.headers.get
            ? (request.headers.get('accept') || '')
            : '';
        return accept.indexOf('text/html') !== -1;
    } catch (e) {
        return true;
    }
}

/**
 * Build a Request safe for SW cache matching.
 * Browsers forbid constructing a Request with navigate mode — that threw at biometric offline fallback.
 */
function shellLookupRequest(urlHref, sourceRequest) {
    var headers = { Accept: 'text/html' };
    try {
        if (sourceRequest && sourceRequest.headers && sourceRequest.headers.get) {
            var accept = sourceRequest.headers.get('accept');
            if (accept) {
                headers.Accept = accept;
            }
        }
    } catch (e) { /* ignore */ }
    var creds = 'same-origin';
    try {
        if (sourceRequest && sourceRequest.credentials) {
            creds = sourceRequest.credentials;
        }
    } catch (e2) { /* ignore */ }
    return new Request(String(urlHref), {
        method: 'GET',
        credentials: creds,
        headers: headers
    });
}

function shellFallback(request) {
    var reqUrl = typeof request === 'string'
        ? request
        : (request && request.url ? request.url : '');
    return caches.open(SHELL_CACHE).then(function (cache) {
        var url = new URL(reqUrl, self.location.origin);
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
                if (wantsHtmlShell(request)) {
                    return offlineHtmlResponse();
                }
                return offlineJsonResponse();
            });
        });
    });
}

self.addEventListener('install', function (event) {
    self.skipWaiting();
    event.waitUntil(
        Promise.all([
            caches.open(ASSET_CACHE),
            warmErpOfflineShell()
        ]).catch(function () { /* ignore */ })
    );
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
                    // Copy ERP offline assets from previous coexist cache before wipe
                    // (prevents empty vN cache + hanging script loads while offline).
                    return migrateErpCoexistCaches(keys);
                }).then(function () {
                    return Promise.all(keys.map(function (key) {
                        // Keep POS shell/assets + current ERP offline caches; drop stale coexist versions only.
                        if (key === SHELL_CACHE || key === ASSET_CACHE
                            || key === ERP_COEXIST_CACHE || key === ERP_OPS_PAGE_CACHE) {
                            return undefined;
                        }
                        if (String(key).indexOf('rateb-erp-coexist-') === 0
                            || String(key).indexOf('rateb-erp-ops-pages-') === 0
                            || String(key).indexOf('rateb-erp-assets-') === 0
                            || String(key).indexOf('rateb-pos-shell-') === 0
                            || String(key).indexOf('rateb-pos-assets-') === 0) {
                            return caches.delete(key);
                        }
                        return undefined;
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
        return;
    }
    if (data.type === 'WARM_ERP_OFFLINE_SHELL') {
        try {
            console.log('[RATIB OFFLINE]', 'PASS', 'step=9', 'file=pos-sw.js', 'function=message',
                'reason=received WARM_ERP_OFFLINE_SHELL');
            console.log('[RATIB OFFLINE]', 'PASS', 'step=10', 'file=pos-sw.js', 'function=warmErpOfflineShell',
                'reason=warmErpOfflineShell() scheduled');
        } catch (eLog) { /* ignore */ }
        event.waitUntil(
            warmErpOfflineShell().then(function () {
                try {
                    console.log('[RATIB OFFLINE]', 'PASS', 'step=10', 'file=pos-sw.js',
                        'function=warmErpOfflineShell', 'reason=warmErpOfflineShell() finished');
                } catch (e2) { /* ignore */ }
            }).catch(function (err) {
                try {
                    console.error('[RATIB OFFLINE]', 'FAIL', 'step=10', 'file=pos-sw.js',
                        'function=warmErpOfflineShell',
                        'reason=' + String(err && err.message ? err.message : err));
                } catch (e3) { /* ignore */ }
            })
        );
        return;
    }
    if (data.type === 'CACHE_ERP_OPS_PAGE') {
        event.waitUntil(putErpOpsPageFromMessage(data));
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
                // Do not pin biometric gate HTML as the offline shell — register + lock is the offline entry.
                if (!isBiometricGatePath(url.pathname)) {
                    event.waitUntil(putShell(event.request, response.clone()));
                }
                return response;
            }).catch(function () {
                if (isBiometricGatePath(url.pathname)) {
                    var regUrl = registerPathFromBiometric(url);
                    return shellFallback(shellLookupRequest(regUrl.href, event.request));
                }
                return shellFallback(event.request);
            })
        );
        return;
    }

    // Smart coexist: non-POS admin HTML → network; offline shell ONLY when truly offline.
    if (event.request.mode === 'navigate'
        && !isPosNavigation(url)
        && !isAuthPath(url.pathname)
        && !isApiRequest(url)) {
        event.respondWith(
            fetch(event.request).catch(function () {
                var trulyOffline = self.navigator && self.navigator.onLine === false;
                if (!trulyOffline) {
                    // Online blip / server error: do not replace live ERP with offline shell.
                    return fetch(event.request).catch(function () {
                        return new Response(
                            '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8">'
                            + '<title>RATEB ERP</title></head><body style="font-family:system-ui;padding:2rem;text-align:center">'
                            + '<p>تعذر تحميل الصفحة. تحقق من الاتصال وأعد المحاولة.</p>'
                            + '<p><a href="' + String(url.href).replace(/"/g, '&quot;') + '">إعادة المحاولة</a></p>'
                            + '</body></html>',
                            {
                                status: 504,
                                headers: { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' }
                            }
                        );
                    });
                }
                return erpAdminOfflineFallback(event.request, url);
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
                            return Promise.all([
                                cache.put(event.request, clone.clone()),
                                cache.put(url.origin + url.pathname, clone)
                            ]);
                        })
                    );
                }
                return response;
            }).catch(function () {
                return matchAsset(event.request).then(function (cached) {
                    return cached || emptyAssetResponse(event.request);
                });
            })
        );
        return;
    }

    // Online: network-first so identity/RBAC/shell fixes ship immediately.
    // Offline: cache-first with fail-fast (no hanging fetch).
    if (isErpOfflineAsset(url)) {
        event.respondWith((function () {
            var offline = self.navigator && self.navigator.onLine === false;
            function putBoth(cache, response) {
                var copy = response.clone();
                return cache.put(event.request, copy.clone()).then(function () {
                    return cache.put(url.origin + url.pathname, copy);
                }).catch(function () { return null; });
            }
            if (!offline) {
                return fetchErpAssetNetwork(event.request, 6000).then(function (response) {
                    if (response) {
                        event.waitUntil(
                            caches.open(ERP_COEXIST_CACHE).then(function (cache) {
                                return putBoth(cache, response);
                            })
                        );
                        return response;
                    }
                    return matchErpOfflineCached(event.request, url).then(function (cached) {
                        return cached || emptyAssetResponse(event.request);
                    });
                });
            }
            return matchErpOfflineCached(event.request, url).then(function (cached) {
                return cached || emptyAssetResponse(event.request);
            });
        })());
        return;
    }

    if (isApiRequest(url) && isPosNavigation(url)) {
        return;
    }
});
