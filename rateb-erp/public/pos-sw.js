/* Rateb POS — offline app shell (Phase 4 + 2B + ERP coexist) */
'use strict';

var SHELL_CACHE = 'rateb-pos-shell-v8';
var ASSET_CACHE = 'rateb-pos-assets-v8';
var ERP_COEXIST_CACHE = 'rateb-erp-coexist-v19';
var ERP_OPS_PAGE_CACHE = 'rateb-erp-ops-pages-v25';
var ERP_OPS_ALLOWLIST_CACHE = 'rateb-erp-ops-allowlist-v25';
var REGISTER_SHELL_PATH = '__rateb_pos_register_shell__';
var ERP_OFFLINE_SHELL = 'offline-shell.html';
var ERP_OPS_ALLOWLIST_URL = 'assets/offline/ops-page-allowlist.json';

/**
 * Runtime paths from ops-page-allowlist.json (synced from
 * offline/config/ops-page-allowlist.php). Seed used only until JSON loads.
 * @type {string[]}
 */
var ERP_OPS_PATHS = [];
var ERP_OPS_PATHS_SEED = [
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

function erpOpsAllowlistRequestUrl() {
    try {
        return new URL(ERP_OPS_ALLOWLIST_URL, self.registration.scope).href;
    } catch (e) {
        return self.location.origin + '/rateb-erp/public/' + ERP_OPS_ALLOWLIST_URL;
    }
}

function applyErpOpsAllowlistPayload(payload) {
    var paths = payload && Array.isArray(payload.paths) ? payload.paths : [];
    ERP_OPS_PATHS = paths.map(function (p) {
        return String(p || '').replace(/^\/+|\/+$/g, '');
    }).filter(function (p) {
        return p !== '';
    });
    // Canonical routes from rateb_app_route() — used for matching pathnames too.
    var routes = payload && payload.routes && typeof payload.routes === 'object' ? payload.routes : {};
    var routeValues = Object.keys(routes).map(function (k) {
        return String(routes[k] || '').replace(/^\/+|\/+$/g, '');
    }).filter(function (p) {
        return p !== '';
    });
    routeValues.forEach(function (r) {
        if (ERP_OPS_PATHS.indexOf(r) === -1) {
            ERP_OPS_PATHS.push(r);
        }
        // Also keep short logical suffix after admin/ops/ or admin/
        var short = r.replace(/^admin\/ops\//i, '').replace(/^admin\//i, '');
        if (short && ERP_OPS_PATHS.indexOf(short) === -1) {
            ERP_OPS_PATHS.push(short);
        }
    });
    if (!ERP_OPS_PATHS.length) {
        ERP_OPS_PATHS = ERP_OPS_PATHS_SEED.slice();
    }
    return ERP_OPS_PATHS.length;
}

function loadErpOpsAllowlist() {
    var url = erpOpsAllowlistRequestUrl();
    return caches.open(ERP_OPS_ALLOWLIST_CACHE).then(function (cache) {
        return fetch(url, {
            credentials: 'same-origin',
            cache: 'no-cache',
            headers: { Accept: 'application/json', 'X-Rateb-Shell-Warm': '1' }
        }).then(function (res) {
            if (res && res.ok) {
                return res.clone().json().then(function (payload) {
                    applyErpOpsAllowlistPayload(payload);
                    return cache.put(url, res).then(function () {
                        return ERP_OPS_PATHS.length;
                    });
                });
            }
            return cache.match(url).then(function (hit) {
                if (!hit) {
                    applyErpOpsAllowlistPayload({ paths: ERP_OPS_PATHS_SEED });
                    return ERP_OPS_PATHS.length;
                }
                return hit.json().then(function (payload) {
                    applyErpOpsAllowlistPayload(payload);
                    return ERP_OPS_PATHS.length;
                });
            });
        }).catch(function () {
            return cache.match(url).then(function (hit) {
                if (!hit) {
                    applyErpOpsAllowlistPayload({ paths: ERP_OPS_PATHS_SEED });
                    return ERP_OPS_PATHS.length;
                }
                return hit.json().then(function (payload) {
                    applyErpOpsAllowlistPayload(payload);
                    return ERP_OPS_PATHS.length;
                });
            });
        });
    }).catch(function () {
        applyErpOpsAllowlistPayload({ paths: ERP_OPS_PATHS_SEED });
        return ERP_OPS_PATHS.length;
    });
}

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

/** Login / password — never intercept. Logout is handled so offline does not show Chrome interstitial. */
function isAuthPath(pathname) {
    var p = String(pathname || '');
    return /\/login(\/|$)/i.test(p)
        || /\/password\//i.test(p)
        || /\/api\/login/i.test(p)
        || /\/api\/qr-login/i.test(p)
        || /\/login\/2fa/i.test(p)
        || /\/login\/barcode/i.test(p)
        || /\/login\/scan/i.test(p)
        || /\/login\/badge/i.test(p);
}

function isLogoutPath(pathname) {
    return /\/logout(\/|$)/i.test(String(pathname || ''));
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
    // Exact Admin dashboard (/…/admin) — not /admin/ops/…
    if (/(^|\/)admin$/.test(p)) {
        return 'admin';
    }
    var sorted = ERP_OPS_PATHS.slice().sort(function (a, b) {
        return String(b).length - String(a).length;
    });
    for (var i = 0; i < sorted.length; i++) {
        var a = String(sorted[i] || '').replace(/^\/+|\/+$/g, '').toLowerCase();
        if (!a || a === 'admin') {
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
            if (url.search) {
                candidates.push(url.origin + url.pathname + url.search);
            }
            if (url.href) {
                candidates.push(url.href);
            }
            // Trailing-slash variant (with and without company_id).
            var bare = String(url.pathname || '').replace(/\/+$/, '');
            if (bare && bare !== url.pathname) {
                candidates.push(url.origin + bare);
                if (url.search) {
                    candidates.push(url.origin + bare + url.search);
                }
            } else if (bare) {
                candidates.push(url.origin + bare + '/');
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
        return chain.then(function (found) {
            if (found) {
                return found;
            }
            if (!url || !url.pathname) {
                return null;
            }
            // Query-string / trailing-slash variants of the same ops page (?company_id=).
            return cache.match(url.origin + url.pathname, { ignoreSearch: true }).then(function (hit) {
                if (hit) {
                    return hit;
                }
                var want = String(url.pathname || '').replace(/\/+$/, '').toLowerCase();
                return cache.keys().then(function (keys) {
                    var best = null;
                    for (var i = 0; i < (keys || []).length; i++) {
                        try {
                            var href = typeof keys[i] === 'string' ? keys[i] : keys[i].url;
                            var ku = new URL(href);
                            var got = String(ku.pathname || '').replace(/\/+$/, '').toLowerCase();
                            if (got === want) {
                                // Prefer same company_id when present.
                                var wantCid = '';
                                var gotCid = '';
                                try {
                                    wantCid = String(url.searchParams.get('company_id') || '');
                                    gotCid = String(ku.searchParams.get('company_id') || '');
                                } catch (eCid) { /* ignore */ }
                                if (wantCid && gotCid && wantCid === gotCid) {
                                    return cache.match(keys[i]);
                                }
                                if (!best) {
                                    best = keys[i];
                                }
                            }
                        } catch (e3) { /* ignore */ }
                    }
                    return best ? cache.match(best) : null;
                });
            });
        });
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
    // Always serve cached Admin HTML after a failed network navigation.
    // Do NOT trust navigator.onLine here — Chrome often reports online with no internet,
    // and a 503+refresh loop causes ERR_FAILED instead of the offline app.
    var tryOps = erpOpsPageFallback(request, url);
    return tryOps.then(function (opsHit) {
        if (opsHit) {
            return opsHit;
        }
        return matchAnyCachedAdminPage(request, url).then(function (any) {
            if (any) {
                return any;
            }
            var pathNorm = '';
            try {
                pathNorm = String((url && url.pathname) || '').replace(/\/+$/, '');
            } catch (eP) { /* ignore */ }
            // Only the bare dashboard may use the classic offline shell home.
            if (/(^|\/)admin$/i.test(pathNorm)) {
                return matchCachedAdminDashboard(url).then(function (dash) {
                    return dash || matchOfflineShellOrInline(request);
                });
            }
            return uncachedAdminBrowseResponse(url);
        });
    }).catch(function () {
        return uncachedAdminBrowseResponse(url);
    });
}

/** Search ops + coexist caches for this admin URL (any previously visited page). */
function matchAnyCachedAdminPage(request, url) {
    var keys = [];
    try {
        if (request && request.url) {
            keys.push(request.url);
        }
    } catch (e0) { /* ignore */ }
    try {
        if (url) {
            keys.push(url.href);
            keys.push(url.origin + url.pathname);
            var bare = String(url.pathname || '').replace(/\/+$/, '');
            if (bare) {
                keys.push(url.origin + bare);
                keys.push(url.origin + bare + '/');
            }
        }
    } catch (e1) { /* ignore */ }
    var uniq = [];
    keys.forEach(function (k) {
        if (k && uniq.indexOf(k) === -1) {
            uniq.push(k);
        }
    });
    function matchKeysIn(cache) {
        var chain = Promise.resolve(null);
        uniq.forEach(function (key) {
            chain = chain.then(function (found) {
                if (found) {
                    return found;
                }
                return cache.match(key).then(function (hit) {
                    if (hit) {
                        return hit;
                    }
                    return cache.match(key, { ignoreSearch: true }).catch(function () { return null; });
                });
            });
        });
        return chain;
    }
    return caches.open(ERP_OPS_PAGE_CACHE).then(function (ops) {
        return matchKeysIn(ops).then(function (hit) {
            if (hit) {
                return hit;
            }
            return caches.open(ERP_COEXIST_CACHE).then(matchKeysIn);
        });
    }).catch(function () {
        return null;
    });
}

/** Deep admin URL with no cache — do not fake stock-movements / offline-home under wrong path. */
function uncachedAdminBrowseResponse(url) {
    var path = '/admin/';
    var adminHref = '/rateb-erp/public/admin/';
    try {
        path = String((url && url.pathname) || path);
        adminHref = new URL('admin/', self.registration.scope).href;
    } catch (e) { /* ignore */ }
    var body = '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8">'
        + '<meta name="viewport" content="width=device-width,initial-scale=1">'
        + '<title>RATEB ERP — أوفلاين</title>'
        + '<style>body{font-family:system-ui,sans-serif;margin:0;padding:2rem;background:#0f1117;color:#e8eaed;text-align:center}'
        + 'a{color:#8ab4ff}p{opacity:.9;line-height:1.5;max-width:28rem;margin:.75rem auto}</style></head>'
        + '<body data-rateb-uncached-page="1">'
        + '<h1>الصفحة غير محفوظة أوفلاين</h1>'
        + '<p>افتح هذه الصفحة مرة وأنت متصل ليتم حفظها، ثم يمكن تصفحها بدون إنترنت.</p>'
        + '<p dir="ltr" style="opacity:.6;font-size:.85rem">' + String(path).replace(/</g, '') + '</p>'
        + '<p><a href="' + String(adminHref).replace(/"/g, '') + '">لوحة التحكم</a></p>'
        + '</body></html>';
    return new Response(body, {
        status: 200,
        headers: {
            'Content-Type': 'text/html; charset=utf-8',
            'Cache-Control': 'no-store',
            'X-Rateb-Offline': '1',
            'X-Rateb-Uncached-Page': '1'
        }
    });
}

function matchCachedAdminDashboard(url) {
    var keys = [];
    try {
        if (url) {
            keys.push(url.origin + url.pathname.replace(/\/+$/, ''));
            keys.push(url.origin + url.pathname.replace(/\/+$/, '') + '/');
            keys.push(url.origin + '/rateb-erp/public/admin');
            keys.push(url.origin + '/rateb-erp/public/admin/');
        }
    } catch (e) { /* ignore */ }
    try {
        keys.push(new URL('admin/', self.registration.scope).href);
        keys.push(new URL('admin', self.registration.scope).href);
    } catch (e2) { /* ignore */ }
    return caches.open(ERP_OPS_PAGE_CACHE).then(function (cache) {
        var chain = Promise.resolve(null);
        keys.forEach(function (key) {
            if (!key) {
                return;
            }
            chain = chain.then(function (found) {
                return found || cache.match(key);
            });
        });
        return chain.then(function (hit) {
            if (hit) {
                return hit;
            }
            return cache.match(keys[0] || '', { ignoreSearch: true }).catch(function () { return null; });
        });
    }).catch(function () {
        return null;
    });
}

function matchOfflineShellOrInline(request) {
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
        // One offline bundle only (min) — avoid ~370KB duplicate download on first paint.
        base + 'assets/offline/rateb-offline.min.js',
        base + 'assets/offline/erp-offline-shell-auth.js',
        base + 'assets/offline/erp-shell-bootstrap.js',
        base + 'assets/offline/ops-page-allowlist.json',
        base + 'assets/css/variables.css',
        base + 'assets/css/main.css',
        base + 'assets/css/components.css',
        base + 'assets/css/dark.css',
        base + 'assets/css/rtl.css'
    ];
    // Stage common Admin pages so offline nav keeps real module UI (not offline-home).
    var leanOps = [
        'admin',
        'admin/',
        'admin/companies',
        'admin/ops/purchase-requests',
        'admin/ops/purchase-orders',
        'admin/ops/rfq',
        'admin/ops/quotations',
        'admin/ops/inventory',
        'admin/ops/warehouses',
        'admin/ops/stock-movements',
        'admin/ops/product-categories',
        'admin/ops/suppliers',
        'admin/ops/hr/attendance',
        'admin/ops/hr/leaves',
        'admin/hr/attendance',
        'admin/hr/leaves',
        'admin/notifications',
        'admin/profile'
    ];
    function cacheUrlList(cache, list) {
        return list.reduce(function (chain, key) {
            return chain.then(function () {
                return fetch(key, {
                    credentials: 'same-origin',
                    cache: 'no-cache',
                    headers: { Accept: '*/*', 'X-Rateb-Shell-Warm': '1' }
                }).then(function (res) {
                    if (!res || !res.ok) {
                        return null;
                    }
                    var pathnameKey = key;
                    try {
                        var ku = new URL(key);
                        pathnameKey = ku.origin + ku.pathname;
                    } catch (ePath) { /* ignore */ }
                    return cache.put(key, res.clone()).then(function () {
                        return cache.put(pathnameKey, res.clone());
                    });
                }).catch(function () {
                    return null;
                });
            });
        }, Promise.resolve());
    }
    function warmLeanOpsPages() {
        return caches.open(ERP_OPS_PAGE_CACHE).then(function (opsCache) {
            return leanOps.reduce(function (chain, rel) {
                return chain.then(function () {
                    var pageUrl = base + rel.replace(/^\//, '');
                    return fetch(pageUrl, {
                        credentials: 'same-origin',
                        cache: 'no-cache',
                        headers: { Accept: 'text/html', 'X-Rateb-Shell-Warm': '1' }
                    }).then(function (res) {
                        if (!res || !res.ok) {
                            return null;
                        }
                        var bare = pageUrl;
                        try {
                            var u = new URL(pageUrl);
                            bare = u.origin + u.pathname;
                        } catch (e5) { /* ignore */ }
                        return Promise.all([
                            opsCache.put(pageUrl, res.clone()).catch(function () { return null; }),
                            opsCache.put(bare, res.clone()).catch(function () { return null; })
                        ]);
                    }).catch(function () { return null; });
                });
            }, Promise.resolve());
        });
    }
    return caches.open(ERP_COEXIST_CACHE).then(function (cache) {
        return cacheUrlList(cache, urls).then(function () {
            // Stage HTML warm after shell assets so first navigation stays fast.
            return new Promise(function (resolve) {
                setTimeout(function () {
                    warmLeanOpsPages().then(resolve).catch(function () { resolve(null); });
                }, 4000);
            });
        });
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

/** Branch appliance (127.0.0.1) keeps working with Wi‑Fi off — do not treat as "no network". */
function isLocalApplianceOrigin() {
    try {
        var h = String(self.location.hostname || '');
        return h === '127.0.0.1' || h === 'localhost' || h === '[::1]';
    } catch (eHost) {
        return false;
    }
}

/**
 * Cloud tab with no internet — use cache/shell fail-fast.
 * Never true on local appliance (PHP built-in server is still up).
 */
function isCloudBrowserOffline() {
    if (isLocalApplianceOrigin()) {
        return false;
    }
    return !!(self.navigator && self.navigator.onLine === false);
}

/** Offline must not wait on hanging fetch(); online uses a short race timeout. */
function fetchErpAssetNetwork(request, timeoutMs) {
    if (isCloudBrowserOffline()) {
        return Promise.resolve(null);
    }
    // Local appliance: always hit PHP — no abort race (pages can be slow once).
    if (isLocalApplianceOrigin()) {
        return fetch(request, { credentials: 'same-origin' }).then(function (response) {
            return response && response.ok ? response : null;
        }).catch(function () {
            return null;
        });
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

/**
 * Admin/HTML navigations.
 * Local: pass through to PHP (Wi‑Fi off must still load instantly).
 * Cloud online: long network wait — never abort into offline shell while Wi‑Fi is up.
 * Cloud offline (navigator.onLine=false): cache/shell immediately.
 */
function fetchNavigateNetwork(request, timeoutMs) {
    if (isLocalApplianceOrigin()) {
        return fetch(request, { credentials: 'same-origin' });
    }
    if (isCloudBrowserOffline()) {
        return Promise.reject(new Error('offline'));
    }
    // Live cloud must not fall to PIN/offline-shell after 2.5s — that caused false offline UX.
    var ms = typeof timeoutMs === 'number' ? timeoutMs : 12000;
    return fetch(request, { credentials: 'same-origin' }).catch(function (err) {
        return Promise.reject(err || new Error('network'));
    });
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
    if (p.indexOf('/assets/offline/') !== -1 || /\/offline-shell\.html$/i.test(p)) {
        return true;
    }
    // Cloud offline only: serve warmed ERP CSS from coexist cache (avoid online paint delay).
    // Local appliance must keep fetching CSS from PHP even when Wi‑Fi is off.
    if (isCloudBrowserOffline() && /\/assets\/css\/.+\.css$/i.test(p)) {
        return true;
    }
    return false;
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
        // Never fake-load ERP offline identity / SDK — empty stubs break offline-shell unlock.
        if (/\/assets\/offline\/(rateb-offline|erp-offline-shell|erp-shell-bootstrap)/i.test(path)) {
            return new Response('/* rateb offline identity missing from cache */', {
                status: 503,
                headers: {
                    'Content-Type': 'application/javascript; charset=utf-8',
                    'X-Rateb-Offline': '1',
                    'Cache-Control': 'no-store'
                }
            });
        }
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
            loadErpOpsAllowlist(),
            warmErpOfflineShell()
        ]).catch(function () { /* ignore */ })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        loadErpOpsAllowlist().then(function () {
            return caches.keys();
        }).then(function (keys) {
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
                            || key === ERP_COEXIST_CACHE || key === ERP_OPS_PAGE_CACHE
                            || key === ERP_OPS_ALLOWLIST_CACHE) {
                            return undefined;
                        }
                        if (String(key).indexOf('rateb-erp-coexist-') === 0
                            || String(key).indexOf('rateb-erp-ops-pages-') === 0
                            || String(key).indexOf('rateb-erp-ops-allowlist-') === 0
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
        }).catch(function () {
            return self.clients.claim();
        })
    );
});

self.addEventListener('message', function (event) {
    var data = event.data || {};
    if (data.type === 'SKIP_WAITING') {
        self.skipWaiting();
        return;
    }
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
        return;
    }
    if (data.type === 'RELOAD_OPS_ALLOWLIST') {
        event.waitUntil(loadErpOpsAllowlist());
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

    // Connectivity probes must hit the network (never Cache API). Let the browser
    // fail the request when offline so the badge stays "غير متصل".
    try {
        if (String(event.request.headers.get('X-Rateb-Connectivity') || '') === '1'
            || /[?&]_rateb_probe=/i.test(url.search)) {
            return;
        }
    } catch (eProbe) { /* ignore */ }

    if (event.request.mode === 'navigate' && isPosNavigation(url)) {
        event.respondWith(
            fetchNavigateNetwork(event.request, 2500).then(function (response) {
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

    // Logout offline: never let Chrome show "لا يتوفر اتصال" interstitial.
    if (event.request.mode === 'navigate' && isLogoutPath(url.pathname)) {
        event.respondWith(
            fetchNavigateNetwork(event.request, 2000).catch(function () {
                var adminUrl;
                try {
                    adminUrl = new URL('admin/', self.registration.scope);
                } catch (eAdmin) {
                    adminUrl = new URL(url.origin + '/rateb-erp/public/admin/');
                }
                return erpAdminOfflineFallback(event.request, adminUrl);
            })
        );
        return;
    }

    // Smart coexist: try network first; on ANY failure serve cached Admin (never Chrome ERR_FAILED).
    // navigator.onLine is unreliable (adapter up / no internet) — do not gate fallback on it.
    if (event.request.mode === 'navigate'
        && !isPosNavigation(url)
        && !isAuthPath(url.pathname)
        && !isApiRequest(url)) {
        event.respondWith(
            fetchNavigateNetwork(event.request, 12000).catch(function () {
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

    // Online: short network race then cache (never block paint for many seconds).
    // Offline: cache-first with fail-fast (no hanging fetch).
    if (isErpOfflineAsset(url)) {
        event.respondWith((function () {
            var offline = isCloudBrowserOffline();
            function putBoth(cache, response) {
                var copy = response.clone();
                return cache.put(event.request, copy.clone()).then(function () {
                    return cache.put(url.origin + url.pathname, copy);
                }).catch(function () { return null; });
            }
            if (!offline) {
                // Prefer cache hit for instant paint; refresh in background.
                return matchErpOfflineCached(event.request, url).then(function (cached) {
                    if (cached) {
                        event.waitUntil(
                            fetchErpAssetNetwork(event.request, 2000).then(function (fresh) {
                                if (!fresh) {
                                    return null;
                                }
                                return caches.open(ERP_COEXIST_CACHE).then(function (cache) {
                                    return putBoth(cache, fresh);
                                });
                            })
                        );
                        return cached;
                    }
                    return fetchErpAssetNetwork(event.request, 2000).then(function (response) {
                        if (response) {
                            event.waitUntil(
                                caches.open(ERP_COEXIST_CACHE).then(function (cache) {
                                    return putBoth(cache, response);
                                })
                            );
                            return response;
                        }
                        return emptyAssetResponse(event.request);
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
