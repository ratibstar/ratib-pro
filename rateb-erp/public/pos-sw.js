/* Rateb POS — offline app shell (Phase 4 + 2B + ERP coexist) */
'use strict';

var SHELL_CACHE = 'rateb-pos-shell-v8';
var ASSET_CACHE = 'rateb-pos-assets-v8';
var ERP_COEXIST_CACHE = 'rateb-erp-coexist-v26';
var ERP_OPS_PAGE_CACHE = 'rateb-erp-ops-pages-v31';
var ERP_OPS_ALLOWLIST_CACHE = 'rateb-erp-ops-allowlist-v31';
var SW_BUILD_ID = '20260713-force-sw-v33';
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
    // Strict path segments only — never match unrelated URLs (e.g. access-control).
    var p = String((url && url.pathname) || '');
    return /\/(?:admin\/ops\/)?pos(?:\/|$)/i.test(p);
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
    var base = '/rateb-erp/public/';
    try {
        base = self.registration.scope;
    } catch (e2) { /* ignore */ }
    if (base.slice(-1) !== '/') {
        base += '/';
    }
    // Always absolute under SW scope — never stack pathname onto origin+scope.
    var adminHome = base + 'admin/';
    var links = [
        ['لوحة التحكم', 'admin/'],
        ['الشركات', 'admin/companies'],
        ['تحديثات الوكالات', 'admin/agency-updates'],
        ['لوحة الفرع', 'admin/ops/branch-dashboard'],
        ['طلبات الشراء', 'admin/ops/purchase-requests'],
        ['أوامر الشراء', 'admin/ops/purchase-orders'],
        ['المخزون', 'admin/ops/inventory'],
        ['حركات المخزون', 'admin/ops/stock-movements'],
        ['المستودعات', 'admin/ops/warehouses'],
        ['الموردون', 'admin/ops/suppliers'],
        ['نقطة البيع', 'admin/ops/pos/register'],
        ['الحضور', 'admin/hr/attendance'],
        ['الإجازات', 'admin/hr/leaves'],
        ['الإشعارات', 'admin/notifications'],
        ['الملف', 'admin/profile']
    ];
    var list = links.map(function (row) {
        return '<a class="item" href="' + base + row[1].replace(/^\//, '') + '">' + row[0] + '</a>';
    }).join('');
    var body = '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8">'
        + '<meta name="viewport" content="width=device-width,initial-scale=1">'
        + '<title>RATEB ERP — Offline</title>'
        + '<style>'
        + 'body{font-family:system-ui,sans-serif;margin:0;padding:1.25rem;background:#0f1117;color:#e8eaed}'
        + 'h1{font-size:1.2rem;margin:0 0 .5rem;text-align:center}'
        + 'p{opacity:.85;line-height:1.5;max-width:28rem;margin:.5rem auto;text-align:center}'
        + '.nav{max-width:22rem;margin:1.25rem auto;display:flex;flex-direction:column;gap:.35rem}'
        + 'a.item{display:block;padding:.65rem .85rem;border-radius:8px;background:#1a1d24;color:#e8eaed;'
        + 'text-decoration:none;border:1px solid #2a2f3a}'
        + 'a.item:hover{border-color:#3d4654}'
        + '</style></head><body>'
        + '<h1>وضع عدم الاتصال</h1>'
        + '<p>القائمة متاحة. افتح النظام مرة وأنت متصل ليكتمل حفظ كل الصفحات.</p>'
        + '<div class="nav">' + list + '</div>'
        + '<p><a href="' + adminHome + '" style="color:#8ab4ff">تحديث لوحة التحكم</a></p>'
        + '</body></html>';
    return new Response(body, {
        status: 200,
        headers: {
            'Content-Type': 'text/html; charset=utf-8',
            'X-Rateb-Offline': '1',
            'X-Rateb-Coexist': 'pos-sw',
            'Cache-Control': 'no-store'
        }
    });
}

/** Always seed offline-shell into Cache API (no network required). */
function seedInlineOfflineShell() {
    var key = erpOfflineShellUrl();
    var res = erpInlineShellResponse();
    return caches.open(ERP_COEXIST_CACHE).then(function (cache) {
        return Promise.all([
            cache.put(key, res.clone()),
            cache.put(ERP_OFFLINE_SHELL, res.clone()).catch(function () { return null; })
        ]);
    }).catch(function () { return null; });
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
    // Always serve *something* after a failed network navigation (never Chrome ERR_FAILED).
    // CRITICAL: never return dashboard HTML under a different path — that made create/edit
    // and sidebar clicks look like "everything goes back to لوحة التحكم".
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
            // Dashboard only when the navigation IS the admin home.
            if (/(^|\/)admin$/i.test(pathNorm)) {
                return matchCachedAdminDashboard(url).then(function (dash) {
                    return dash || matchOfflineShellOrInline(request);
                });
            }
            return uncachedAdminBrowseResponse(url);
        });
    }).catch(function () {
        try {
            var pathNorm2 = String((url && url.pathname) || '').replace(/\/+$/, '');
            if (/(^|\/)admin$/i.test(pathNorm2)) {
                return matchOfflineShellOrInline(request);
            }
        } catch (eC) { /* ignore */ }
        try {
            return uncachedAdminBrowseResponse(url);
        } catch (eU) {
            return erpInlineShellResponse();
        }
    });
}

/** Last-resort — must never reject (Chrome ERR_FAILED if respondWith promise rejects). */
function neverFailNavigate(request, url) {
    return erpAdminOfflineFallback(request, url).then(function (res) {
        return asNonRedirectedResponse(res).then(function (clean) {
            return clean || res || erpInlineShellResponse();
        });
    }).catch(function () {
        try {
            return erpInlineShellResponse();
        } catch (e) {
            return new Response(
                '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>Offline</title></head>'
                + '<body style="font-family:system-ui;background:#0f1117;color:#e8eaed;padding:2rem;text-align:center">'
                + '<h1>وضع عدم الاتصال</h1><p>أعد فتح Admin وأنت متصل مرة واحدة.</p></body></html>',
                { status: 200, headers: { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' } }
            );
        }
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
            // admin/ops/access-control ↔ admin/access-control
            var p = String(url.pathname || '');
            if (/\/admin\/ops\//i.test(p)) {
                var p2 = p.replace(/\/admin\/ops\//i, '/admin/');
                keys.push(url.origin + p2);
                keys.push(url.origin + p2.replace(/\/+$/, ''));
                if (url.search) {
                    keys.push(url.origin + p2 + url.search);
                }
            } else if (/\/admin\/(access-control|users|roles|permissions|accounting|purchase-|inventory|suppliers)/i.test(p)) {
                var p3 = p.replace(/\/admin\//i, '/admin/ops/');
                keys.push(url.origin + p3);
                keys.push(url.origin + p3.replace(/\/+$/, ''));
                if (url.search) {
                    keys.push(url.origin + p3 + url.search);
                }
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
            return caches.open(ERP_COEXIST_CACHE).then(matchKeysIn).then(function (hit2) {
                if (hit2) {
                    return hit2;
                }
                // Also search previous ops-page cache versions (avoid empty after cache rename).
                return caches.keys().then(function (names) {
                    var opsNames = (names || []).filter(function (n) {
                        return String(n).indexOf('rateb-erp-ops-pages-') === 0
                            && String(n) !== ERP_OPS_PAGE_CACHE;
                    });
                    return opsNames.reduce(function (chain, name) {
                        return chain.then(function (found) {
                            if (found) {
                                return found;
                            }
                            return caches.open(name).then(matchKeysIn);
                        });
                    }, Promise.resolve(null));
                });
            });
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
        + '<title>RATEB ERP — الصفحة غير محفوظة</title>'
        + '<style>body{font-family:system-ui,sans-serif;margin:0;padding:2rem;background:#0f1117;color:#e8eaed;text-align:center}'
        + 'a{color:#8ab4ff;display:inline-block;margin:.4rem}p{opacity:.9;line-height:1.55;max-width:28rem;margin:.75rem auto}'
        + '.box{max-width:28rem;margin:10vh auto;padding:1.5rem;border-radius:12px;background:#1a1d24;border:1px solid #2a3344}</style></head>'
        + '<body data-rateb-uncached-page="1">'
        + '<div class="box">'
        + '<h1>الصفحة غير محفوظة أوفلاين</h1>'
        + '<p>الإنشاء والتعديل ومعظم الشاشات تحتاج فتح الصفحة مرة وأنت <strong>متصل</strong> ليتم حفظها، أو انتظار اكتمال «تجهيز الأوفلاين».</p>'
        + '<p>الحفظ/الإرسال لا يعمل بدون إنترنت.</p>'
        + '<p dir="ltr" style="opacity:.55;font-size:.8rem;word-break:break-all">' + String(path).replace(/</g, '') + '</p>'
        + '<p><a href="' + String(adminHref).replace(/"/g, '') + '">العودة للوحة التحكم</a></p>'
        + '</div></body></html>';
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
        // One offline bundle only (min) — avoid ~370KB duplicate download on first paint.
        base + 'assets/offline/rateb-offline.min.js',
        base + 'assets/offline/erp-offline-shell-auth.js',
        base + 'assets/offline/erp-shell-bootstrap.js',
        base + 'assets/offline/ops-page-allowlist.json',
        base + 'assets/css/variables.css',
        base + 'assets/css/main.css',
        base + 'assets/css/components.css',
        base + 'assets/css/dark.css',
        base + 'assets/css/light.css',
        base + 'assets/css/rtl.css',
        base + 'assets/css/dashboard.css',
        base + 'assets/css/ar-typography.css',
        base + 'assets/vendor/bootstrap/5.3.3/bootstrap.rtl.min.css',
        base + 'assets/vendor/bootstrap/5.3.3/bootstrap.min.css',
        base + 'assets/vendor/bootstrap/5.3.3/bootstrap.bundle.min.js',
        base + 'assets/vendor/fontawesome/6.5.2/css/all.min.css',
        base + 'assets/vendor/fontawesome/6.5.2/webfonts/fa-solid-900.woff2',
        base + 'assets/vendor/fontawesome/6.5.2/webfonts/fa-regular-400.woff2',
        base + 'assets/vendor/fonts/tajawal/tajawal.css',
        base + 'assets/js/theme.js',
        base + 'assets/js/app.js',
        base + 'assets/js/connectivity-indicator.js',
        base + 'assets/js/charts.js'
    ];
    // Stage common Admin pages so offline nav keeps real module UI (not offline-home).
    var leanOps = [
        'admin',
        'admin/',
        'admin/companies',
        'admin/ops/access-control',
        'admin/ops/access-control/matrix',
        'admin/ops/pos/register',
        'admin/ops/branch-dashboard',
        'admin/ops/purchase-requests',
        'admin/ops/purchase-orders',
        'admin/ops/rfq',
        'admin/ops/quotations',
        'admin/ops/inventory',
        'admin/ops/warehouses',
        'admin/ops/stock-movements',
        'admin/ops/product-categories',
        'admin/ops/suppliers',
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
    function putOpsHtml(opsCache, pageUrl, res) {
        var bare = pageUrl;
        try {
            var u = new URL(pageUrl);
            bare = u.origin + u.pathname;
        } catch (e5) { /* ignore */ }
        return Promise.all([
            opsCache.put(pageUrl, res.clone()).catch(function () { return null; }),
            opsCache.put(bare, res.clone()).catch(function () { return null; })
        ]);
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
                        return putOpsHtml(opsCache, pageUrl, res);
                    }).catch(function () { return null; });
                });
            }, Promise.resolve()).then(function () {
                // Do NOT warm the full allowlist here — many routes 404/500 and flood DevTools.
                // Client erp-offline-full-warm.js warms sidebar + known-good URLs only.
                return null;
            });
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
    // Stale HTML often requests erp-offline-nav-guard.js?v=OLD — that pinned the
    // create/edit blocker toast. Always prefer the plain pathname entry (latest warm).
    var forcePlain = /\/assets\/offline\/(erp-offline-nav-guard|erp-offline-full-warm|rateb-offline(\.min)?)\.js$/i
        .test(String(url.pathname || ''));
    function matchInCache(cache) {
        var start = forcePlain && pathnameKey
            ? cache.match(pathnameKey)
            : cache.match(request);
        return start.then(function (hit) {
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
                return cache.match(request).then(function (hitExact) {
                    if (hitExact) {
                        return hitExact;
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
        });
    }
    return caches.open(ERP_COEXIST_CACHE).then(matchInCache).then(function (hit) {
        if (hit) {
            return hit;
        }
        return caches.keys().then(function (names) {
            var erpCaches = (names || []).filter(function (n) {
                return String(n).indexOf('rateb-erp-coexist-') === 0
                    || String(n).indexOf('rateb-erp-assets-') === 0
                    || String(n).indexOf('rateb-erp-ops-pages-') === 0;
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
 * Cloud: race fetch vs timeout — hung fetch with false navigator.onLine caused ERR_FAILED.
 *
 * Navigate FetchEvents use redirect:"manual". Returning a Response with redirected:true
 * makes Chrome fail the whole event with ERR_FAILED — always rebuild via asNonRedirectedResponse.
 */
function navigateFetchInput(request) {
    try {
        return new Request(String(request.url || request), {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            redirect: 'follow',
            headers: { Accept: 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8' }
        });
    } catch (e) {
        try {
            return String(request.url || request);
        } catch (e2) {
            return request;
        }
    }
}

/** Strip redirected bit so respondWith / Cache.put never throws ERR_FAILED. */
/** Strip redirected bit / materialize a fresh body so Cache.put never sees a used stream. */
function asNonRedirectedResponse(response) {
    if (!response) {
        return Promise.resolve(null);
    }
    var status = 200;
    var statusText = '';
    var headers = new Headers();
    try {
        status = response.status || 200;
        statusText = response.statusText || '';
        response.headers.forEach(function (v, k) {
            headers.set(k, v);
        });
    } catch (eH) { /* ignore */ }
    // Always copy bytes — never return the same Response object for caching/respondWith.
    var src;
    try {
        src = response.clone();
    } catch (eClone) {
        src = response;
    }
    return src.arrayBuffer().then(function (buf) {
        return new Response(buf, {
            status: status,
            statusText: statusText,
            headers: headers
        });
    }).catch(function () {
        return null;
    });
}

/** Safe Cache.put of one response into several keys without consuming the page Response. */
function putResponseKeys(cache, response, keys) {
    return asNonRedirectedResponse(response).then(function (clean) {
        if (!clean) {
            return null;
        }
        return Promise.all((keys || []).map(function (key) {
            if (!key) {
                return null;
            }
            return cache.put(key, clean.clone()).catch(function () { return null; });
        }));
    }).catch(function () { return null; });
}

function fetchNavigateNetwork(request, timeoutMs) {
    if (isLocalApplianceOrigin()) {
        return fetch(navigateFetchInput(request)).then(asNonRedirectedResponse).then(function (res) {
            return res || Promise.reject(new Error('empty-response'));
        });
    }
    if (isCloudBrowserOffline()) {
        return Promise.reject(new Error('offline'));
    }
    var ms = typeof timeoutMs === 'number' ? timeoutMs : 8000;
    var network = fetch(navigateFetchInput(request)).then(asNonRedirectedResponse).then(function (response) {
        if (!response) {
            return Promise.reject(new Error('empty-response'));
        }
        return response;
    });
    var timed = new Promise(function (_resolve, reject) {
        setTimeout(function () {
            reject(new Error('navigate-timeout'));
        }, ms);
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
    if (p.indexOf('/assets/offline/') !== -1 || /\/offline-shell\.html$/i.test(p)) {
        return true;
    }
    if (/\/manifest\.webmanifest$/i.test(p) || /\/pos-manifest\.webmanifest$/i.test(p)
        || /\/manifest\.json$/i.test(p)) {
        return true;
    }
    if (/\/assets\/pwa\//i.test(p)) {
        return true;
    }
    // Always manage design assets so offline Admin matches online layout.
    if (/\/assets\/css\/.+\.css$/i.test(p)) {
        return true;
    }
    if (/\/assets\/vendor\/(bootstrap|fontawesome|fonts|chartjs)\//i.test(p)) {
        return true;
    }
    // All ERP JS under assets/js — not just a hard-coded list (table-tools etc.).
    if (/\/assets\/js\/.+\.js$/i.test(p)) {
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
        // Never claim success with an empty stylesheet — that strips the online design.
        return new Response('/* rateb offline: stylesheet missing from cache */', {
            status: 503,
            headers: {
                'Content-Type': 'text/css; charset=utf-8',
                'X-Rateb-Offline': '1',
                'Cache-Control': 'no-store'
            }
        });
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
    return response.clone().text().then(function (html) {
        var isRegisterHtml = isRegisterShellPath(url.pathname)
            || (html && html.indexOf('data-pos-register') !== -1);
        // Never pin dashboard/reports HTML as the offline register shell.
        if (!isRegisterHtml && !isRegisterShellPath(url.pathname)) {
            return caches.open(SHELL_CACHE).then(function (cache) {
                return Promise.all([
                    cache.put(url.origin + url.pathname, response.clone()),
                    cache.put(url.origin + url.pathname + url.search, response.clone())
                ]);
            });
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
            tasks.push(cache.put(altRegister, response.clone()));
            tasks.push(cache.put(altRegister + url.search, response.clone()));
            // Also alias admin/ops/pos ↔ pos when both exist under public/.
            try {
                var opsReg = new URL('admin/ops/pos/register', self.registration.scope).href;
                var opsBare = new URL('admin/ops/pos', self.registration.scope).href;
                tasks.push(cache.put(opsReg, response.clone()));
                tasks.push(cache.put(opsBare, response.clone()));
            } catch (eAlias) { /* ignore */ }
            return Promise.all(tasks);
        });
    }).catch(function () { /* ignore quota */ });
}

/** Offline POS: dashboard/reports → prefer cached register shell. */
function posOfflineRegisterUrl(url) {
    try {
        var u = new URL(url.href || url);
        u.pathname = String(u.pathname || '')
            .replace(/\/+$/, '')
            .replace(/\/(reports|settings|dashboard|shifts|terminals)(\/.*)?$/i, '/register');
        if (!/\/register$/i.test(u.pathname)) {
            if (/\/pos$/i.test(u.pathname)) {
                u.pathname = u.pathname + '/register';
            }
        }
        return u;
    } catch (e) {
        return url;
    }
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
        seedInlineOfflineShell().then(function () {
            return caches.open(ERP_COEXIST_CACHE).then(function (cache) {
                var base;
                try {
                    base = self.registration.scope;
                } catch (eB) {
                    base = self.location.origin + '/rateb-erp/public/';
                }
                if (base.slice(-1) !== '/') {
                    base += '/';
                }
                var critical = [
                    'assets/css/variables.css',
                    'assets/css/main.css',
                    'assets/css/components.css',
                    'assets/css/dark.css',
                    'assets/css/light.css',
                    'assets/css/rtl.css',
                    'assets/css/dashboard.css',
                    'assets/css/ar-typography.css',
                    'assets/vendor/bootstrap/5.3.3/bootstrap.rtl.min.css',
                    'assets/vendor/bootstrap/5.3.3/bootstrap.bundle.min.js',
                    'assets/vendor/fontawesome/6.5.2/css/all.min.css',
                    'assets/vendor/fonts/tajawal/tajawal.css',
                    'assets/js/theme.js',
                    'assets/js/app.js',
                    'assets/js/connectivity-indicator.js',
                    'manifest.webmanifest',
                    'offline-shell.html'
                ];
                var urls = [];
                critical.forEach(function (rel) {
                    urls.push(base + rel);
                    urls.push(base + rel + '?v=' + encodeURIComponent(SW_BUILD_ID));
                });
                return Promise.all(urls.map(function (u) {
                    return fetch(u, {
                        credentials: 'same-origin',
                        cache: 'reload',
                        headers: { 'X-Rateb-Shell-Warm': '1' }
                    }).then(function (res) {
                        if (!res || !res.ok) {
                            return null;
                        }
                        return cache.put(u, res.clone()).then(function () {
                            try {
                                var pu = new URL(u);
                                return cache.put(pu.origin + pu.pathname, res.clone());
                            } catch (eP) {
                                return null;
                            }
                        });
                    }).catch(function () { return null; });
                }));
            });
        }).then(function () {
            return Promise.all([
                caches.open(ASSET_CACHE),
                loadErpOpsAllowlist(),
                warmErpOfflineShell()
            ]);
        }).catch(function () { /* ignore */ })
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
                    // Keep previous coexist/ops caches — deleting them left offline CSS blank.
                    return Promise.all(keys.map(function (key) {
                        if (key === SHELL_CACHE || key === ASSET_CACHE
                            || key === ERP_COEXIST_CACHE || key === ERP_OPS_PAGE_CACHE
                            || key === ERP_OPS_ALLOWLIST_CACHE) {
                            return undefined;
                        }
                        // Keep last coexist/ops versions; only drop ancient POS shells.
                        if (String(key).indexOf('rateb-pos-shell-') === 0 && key !== SHELL_CACHE) {
                            return caches.delete(key);
                        }
                        if (String(key).indexOf('rateb-pos-assets-') === 0 && key !== ASSET_CACHE) {
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
                || /[?&]_rateb_probe=/i.test(url.search)
                || /\/connectivity-probe\.json$/i.test(url.pathname)) {
                return;
            }
        } catch (eProbe) { /* ignore */ }

    if (event.request.mode === 'navigate' && isPosNavigation(url)) {
        event.respondWith(
            fetchNavigateNetwork(event.request, 2500).then(function (response) {
                // Do not pin biometric gate HTML as the offline shell — register + lock is the offline entry.
                if (!isBiometricGatePath(url.pathname) && response) {
                    var forShell = response.clone();
                    event.waitUntil(putShell(event.request, forShell).catch(function () { return null; }));
                }
                return response;
            }).catch(function () {
                if (isBiometricGatePath(url.pathname)) {
                    var regUrl = registerPathFromBiometric(url);
                    return shellFallback(shellLookupRequest(regUrl.href, event.request));
                }
                if (/\/pos\/(dashboard|reports|settings|shifts|terminals)(\/|$)/i.test(url.pathname)) {
                    var regFromDash = posOfflineRegisterUrl(url);
                    return shellFallback(shellLookupRequest(regFromDash.href, event.request));
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
                return neverFailNavigate(event.request, adminUrl);
            })
        );
        return;
    }

    // Smart coexist: try network first; on ANY failure serve cached Admin (never Chrome interstitial).
    // When browser reports offline → cache/shell immediately (no hung fetch).
    if (event.request.mode === 'navigate'
        && !isPosNavigation(url)
        && !isAuthPath(url.pathname)
        && !isApiRequest(url)) {
        event.respondWith(
            (isCloudBrowserOffline()
                ? neverFailNavigate(event.request, url)
                : fetchNavigateNetwork(event.request, 4000).catch(function () {
                    return neverFailNavigate(event.request, url);
                })
            ).catch(function () {
                try {
                    return erpInlineShellResponse();
                } catch (eFinal) {
                    return new Response('Offline', {
                        status: 200,
                        headers: { 'Content-Type': 'text/html; charset=utf-8' }
                    });
                }
            })
        );
        return;
    }

    if (isRegisterShellPath(url.pathname)
        && (event.request.headers.get('accept') || '').indexOf('text/html') !== -1) {
        event.respondWith(
            fetch(navigateFetchInput(event.request)).then(function (response) {
                if (response) {
                    var forShell = response.clone();
                    event.waitUntil(putShell(event.request, forShell).catch(function () { return null; }));
                }
                return asNonRedirectedResponse(response).then(function (clean) {
                    return clean || response;
                });
            }).catch(function () {
                return shellFallback(event.request);
            })
        );
        return;
    }

    if (isPosAsset(url)) {
        event.respondWith(
            fetch(navigateFetchInput(event.request)).then(function (response) {
                if (response && response.ok) {
                    var clone = response.clone();
                    event.waitUntil(
                        caches.open(ASSET_CACHE).then(function (cache) {
                            return asNonRedirectedResponse(clone).then(function (clean) {
                                if (!clean) {
                                    return null;
                                }
                                return Promise.all([
                                    cache.put(url.origin + url.pathname, clean.clone()).catch(function () { return null; }),
                                    cache.put(url.origin + url.pathname + (url.search || ''), clean.clone()).catch(function () { return null; })
                                ]);
                            });
                        }).catch(function () { return null; })
                    );
                }
                return asNonRedirectedResponse(response).then(function (clean) {
                    return clean || response;
                });
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
            function putBoth(cache, responseForCache) {
                var bare = url.origin + url.pathname;
                var withQ = url.origin + url.pathname + (url.search || '');
                return putResponseKeys(cache, responseForCache, [bare, withQ]);
            }
            if (!offline) {
                // Prefer cache hit for instant paint; refresh in background.
                return matchErpOfflineCached(event.request, url).then(function (cached) {
                    if (cached) {
                        event.waitUntil(
                            fetchErpAssetNetwork(event.request, 4000).then(function (fresh) {
                                if (!fresh) {
                                    return null;
                                }
                                var forCache = fresh.clone();
                                return caches.open(ERP_COEXIST_CACHE).then(function (cache) {
                                    return putBoth(cache, forCache);
                                });
                            }).catch(function () { return null; })
                        );
                        return asNonRedirectedResponse(cached).then(function (c) {
                            return c || cached;
                        });
                    }
                    // Design CSS/vendor: never substitute empty 503 while online — wait for PHP/CDN.
                    return fetch(navigateFetchInput(event.request)).then(function (response) {
                        if (response && response.ok) {
                            var forCache = response.clone();
                            event.waitUntil(
                                caches.open(ERP_COEXIST_CACHE).then(function (cache) {
                                    return putBoth(cache, forCache);
                                }).catch(function () { return null; })
                            );
                        }
                        return asNonRedirectedResponse(response).then(function (clean) {
                            return clean || response;
                        });
                    }).catch(function () {
                        return emptyAssetResponse(event.request);
                    });
                });
            }
            return matchErpOfflineCached(event.request, url).then(function (cached) {
                if (!cached) {
                    return emptyAssetResponse(event.request);
                }
                return asNonRedirectedResponse(cached).then(function (clean) {
                    return clean || cached;
                });
            });
        })());
        return;
    }

    if (isApiRequest(url) && isPosNavigation(url)) {
        return;
    }
});
