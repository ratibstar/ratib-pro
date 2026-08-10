/* Rateb POS — offline app shell (Phase 4 + 2B + ERP coexist) */
'use strict';

var SHELL_CACHE = 'rateb-pos-shell-v8';
var ASSET_CACHE = 'rateb-pos-assets-v8';
var ERP_COEXIST_CACHE = 'rateb-erp-coexist-v34';
/* v40 — bust company-edit HTML poisoned under ops module URLs (first soft-nav click). */
var ERP_OPS_PAGE_CACHE = 'rateb-erp-ops-pages-v42';
var ERP_OPS_ALLOWLIST_CACHE = 'rateb-erp-ops-allowlist-v34';
var SW_BUILD_ID = '20260810-force-live-bypass-noloop-v154';
var RATEB_SYNC_TAG = 'rateb-offline-flush';
var RATEB_PRINT_SYNC_TAG = 'rateb-pos-print';
var REGISTER_SHELL_PATH = '__rateb_pos_register_shell__';
var REGISTER_CERT_META_PATH = '__rateb_pos_register_cert_meta__';
var POS_SNAPSHOT_VERSION = 'oj-v1';
var ERP_OFFLINE_SHELL = 'offline-shell.html';
/** Last-resort inline nav shell — NEVER stored under offline-shell.html (PERF-P0.3-C). */
var ERP_INLINE_SHELL_KEY = '__rateb_inline_offline_shell__';
var ERP_OPS_ALLOWLIST_URL = 'assets/offline/ops-page-allowlist.json';
var ERP_DEFERRED_POSTS_PREFIX = '__rateb_deferred_posts__/';
var LAST_SHELL_WARM_AT = 0;
var SHELL_WARM_TTL_MS = 30 * 60 * 1000;
var shellWarmRunning = false;
/** Phase OD — last verified protected-asset warm result (for status messages). */
var LAST_PROTECTED_CACHE_RESULT = null;
/**
 * Soft-offline latch: Wi‑Fi dead but navigator.onLine still true.
 * Without this, hung fetch() (no Abort) freezes the SW → minutes of black Admin.
 */
var cloudDegradedUntil = 0;
/** Soft-offline latch for assets only — was 45s and poisoned every /admin refresh into a black cache path. */
var CLOUD_DEGRADED_TTL_MS = 5000;

/**
 * Phase OD — assets that MUST exist in Cache Storage before offline use.
 * Keep in sync with offline-shell.html load chain + OA modules.
 */
var PROTECTED_OFFLINE_RELS = [
    'offline-shell.html',
    'assets/offline/erp-offline-tenant-context.js',
    'assets/offline/offline-bootstrap.js',
    'assets/offline/rateb-offline.js',
    'assets/offline/rateb-offline.min.js',
    'assets/offline/erp-offline-shell-auth.js',
    'assets/offline/erp-offline-shell-rbac.js',
    'assets/offline/erp-shell-bootstrap.js',
    'assets/offline/erp-offline-nav-guard.js',
    'assets/offline/erp-offline-full-warm.js',
    'assets/offline/ops-page-allowlist.json',
    'assets/offline/modules/offline-storage.js',
    'assets/offline/modules/offline-auth.js',
    'assets/offline/modules/offline-rbac.js',
    'assets/offline/modules/offline-core.js',
    'assets/offline/modules/offline-crypto.js',
    'assets/offline/modules/offline-sdk.js',
    'assets/offline/modules/offline-queue.js',
    'assets/offline/modules/offline-replay.js',
    'assets/offline/modules/offline-sync.js',
    'assets/offline/modules/offline-network.js',
    'assets/offline/modules/offline-shell.js',
    'assets/offline/modules/offline-forms.js',
    'assets/offline/modules/offline-ops-forms.js',
    'assets/offline/modules/offline-files.js',
    'assets/offline/modules/offline-master-data.js',
    'assets/offline/modules/offline-migrations.js',
    'assets/offline/modules/offline-monitor.js',
    'assets/offline/modules/offline-diagnostics.js',
    'assets/offline/modules/offline-print.js',
    'assets/offline/modules/offline-pos.js',
    'assets/offline/modules/offline-adapter-hr.js',
    'assets/offline/modules/offline-adapter-inventory.js',
    'assets/offline/modules/offline-adapter-accounting.js',
    'assets/offline/modules/offline-adapter-procurement.js',
    'assets/offline/modules/offline-adapter-crm.js',
    'assets/offline/modules/offline-adapter-recruitment.js',
    'assets/offline/modules/offline-adapter-warehouse.js',
    'assets/offline/modules/offline-adapter-payroll.js',
    'assets/offline/modules/offline-adapter-assets.js',
    'assets/offline/modules/offline-adapter-projects.js',
    'assets/offline/modules/offline-adapter-manufacturing.js',
    'assets/offline/modules/offline-adapter-quality.js',
    'assets/offline/modules/offline-adapter-eproc.js',
    'assets/offline/modules/offline-adapter-approval.js',
    'assets/offline/modules/offline-adapter-bi.js'
];

/** Extra cache keys used by offline-shell.html fallbacks / cache-bust URLs. */
var PROTECTED_OFFLINE_ALIAS_QUERIES = [
    'assets/offline/offline-bootstrap.js?v=20260713-offline-nav-guard',
    'assets/offline/rateb-offline.js?v=oid-20260713-lean',
    'assets/offline/erp-offline-tenant-context.js?v=20260713-offline-nav-guard',
    'assets/offline/erp-offline-shell-auth.js?v=20260713-offline-nav-guard',
    'assets/offline/erp-offline-shell-rbac.js?v=20260713-offline-nav-guard'
];

/** PERF-P0.1 — memoize offline identity match results for this SW lifetime (no duplicate lookups). */
var IDENTITY_MATCH_MEMO = Object.create(null);

function publicBaseUrl() {
    var base;
    try {
        base = self.registration.scope;
    } catch (eB) {
        base = self.location.origin + '/rateb-erp/public/';
    }
    if (base.slice(-1) !== '/') {
        base += '/';
    }
    return base;
}

function protectedMinBytes(rel) {
    var r = String(rel || '').split('?')[0];
    if (/offline-bootstrap\.js$/i.test(r)) {
        return 10000;
    }
    if (/rateb-offline(\.min)?\.js$/i.test(r)) {
        return 50000;
    }
    if (/erp-offline-shell-(auth|rbac)\.js$/i.test(r)) {
        return 500;
    }
    if (/erp-shell-bootstrap\.js$/i.test(r)) {
        return 5000;
    }
    if (/offline-shell\.html$/i.test(r)) {
        return 8000;
    }
    if (/modules\//i.test(r)) {
        return 50;
    }
    return 20;
}

function protectedContentType(rel) {
    var r = String(rel || '').split('?')[0];
    if (/\.html$/i.test(r)) {
        return 'text/html; charset=utf-8';
    }
    if (/\.json$/i.test(r)) {
        return 'application/json; charset=utf-8';
    }
    return 'application/javascript; charset=utf-8';
}

function isAcceptableProtectedBody(rel, text) {
    var t = String(text || '');
    if (/rateb-pos\s+offline\s+stub/i.test(t)) {
        return false;
    }
    if (/identity missing from cache/i.test(t)) {
        return false;
    }
    // PERF-P0.3-C — reject thin inline shell masquerading as offline-shell.html.
    if (/offline-shell\.html$/i.test(String(rel || '').split('?')[0])) {
        if (t.indexOf('oa_bootstrap_missing') === -1 && t.indexOf('loadOfflineScript') === -1) {
            return false;
        }
    }
    return t.length >= protectedMinBytes(rel);
}

/**
 * Phase OD — fetch + store one protected asset; reject stubs / empty bodies.
 */
function cacheOneProtectedAsset(cache, base, relPath) {
    var rel = String(relPath || '').replace(/^\//, '');
    var abs = /^https?:/i.test(rel) ? rel : (base + rel);
    var bare = abs.split('?')[0];
    return fetch(abs, {
        credentials: 'same-origin',
        cache: 'reload',
        headers: {
            Accept: '*/*',
            'X-Rateb-Shell-Warm': '1',
            'X-Rateb-Protected-Warm': '1'
        }
    }).then(function (res) {
        if (!res || !res.ok) {
            throw new Error('fetch_fail:' + rel + ':' + (res ? res.status : 'null'));
        }
        return res.text().then(function (text) {
            if (!isAcceptableProtectedBody(rel, text)) {
                throw new Error('bad_body:' + rel + ':len=' + String(text || '').length);
            }
            var headers = {
                'Content-Type': res.headers.get('Content-Type') || protectedContentType(rel),
                'X-Rateb-Protected-Cached': '1'
            };
            var body = text;
            function putKey(key) {
                return cache.put(key, new Response(body, { status: 200, headers: headers }));
            }
            return putKey(bare).then(function () {
                return putKey(abs);
            }).then(function () {
                if (abs === bare) {
                    return putKey(bare + '?v=' + encodeURIComponent(SW_BUILD_ID));
                }
                return null;
            }).then(function () {
                // PERF-P0.1 — stamp every known shell alias for this bare path so ?v=oid-… hits without re-fetch.
                var aliasPuts = [];
                PROTECTED_OFFLINE_ALIAS_QUERIES.forEach(function (aliasRel) {
                    var aliasBare = String(aliasRel).split('?')[0];
                    if (aliasBare.replace(/^\//, '') === rel.split('?')[0].replace(/^\//, '')
                        || (base + aliasBare) === bare) {
                        aliasPuts.push(putKey(base + String(aliasRel).replace(/^\//, '')));
                    }
                });
                if (/rateb-offline(\.min)?\.js$/i.test(bare)) {
                    aliasPuts.push(putKey(bare + '?v=oid-20260713-lean'));
                    aliasPuts.push(putKey(bare + '?v=' + encodeURIComponent(SW_BUILD_ID)));
                }
                return aliasPuts.length ? Promise.all(aliasPuts) : null;
            }).then(function () {
                try {
                    delete IDENTITY_MATCH_MEMO[bare];
                    delete IDENTITY_MATCH_MEMO[urlPathMemoKey(bare)];
                } catch (eMemo) { /* ignore */ }
                return { rel: rel, ok: true, len: body.length, url: bare };
            });
        });
    });
}

function urlPathMemoKey(hrefOrPath) {
    try {
        var u = new URL(hrefOrPath, publicBaseUrl());
        return u.origin + u.pathname;
    } catch (e) {
        return String(hrefOrPath || '');
    }
}

function cacheOneProtectedAssetWithRetry(cache, base, relPath, retries) {
    var left = retries == null ? 2 : retries;
    function attempt() {
        return cacheOneProtectedAsset(cache, base, relPath).catch(function (err) {
            if (left <= 0) {
                throw err;
            }
            left -= 1;
            return new Promise(function (resolve) {
                setTimeout(resolve, 250);
            }).then(attempt);
        });
    }
    return attempt();
}

/**
 * Phase OD — verify Cache Storage has real bodies for all protected assets.
 */
function verifyProtectedOfflineCache() {
    var base = publicBaseUrl();
    return caches.open(ERP_COEXIST_CACHE).then(function (cache) {
        var inventory = [];
        var missing = [];
        return PROTECTED_OFFLINE_RELS.reduce(function (chain, rel) {
            return chain.then(function () {
                var bare = base + rel.replace(/^\//, '');
                return cache.match(bare).then(function (hit) {
                    if (!hit) {
                        return cache.match(bare, { ignoreSearch: true });
                    }
                    return hit;
                }).then(function (hit) {
                    if (!hit) {
                        missing.push({ rel: rel, reason: 'absent' });
                        inventory.push({ rel: rel, ok: false, len: 0 });
                        return null;
                    }
                    return hit.text().then(function (text) {
                        var ok = isAcceptableProtectedBody(rel, text);
                        inventory.push({ rel: rel, ok: ok, len: String(text || '').length });
                        if (!ok) {
                            missing.push({ rel: rel, reason: 'bad_body', len: String(text || '').length });
                        }
                        return null;
                    });
                });
            });
        }, Promise.resolve()).then(function () {
            return {
                ok: missing.length === 0,
                missing: missing,
                inventory: inventory,
                cache: ERP_COEXIST_CACHE,
                build: SW_BUILD_ID,
                at: Date.now()
            };
        });
    });
}

/**
 * Phase OD — populate + verify all protected offline assets.
 * Never silently succeeds when any required asset is absent/stubbed.
 *
 * Phase PC — when opts.force is falsy, verify first and only fetch missing
 * entries (avoids full serial re-download on every activate).
 */
function ensureProtectedOfflineCache(opts) {
    opts = opts || {};
    if (isCloudBrowserOffline() && !opts.allowOffline) {
        return verifyProtectedOfflineCache().then(function (v) {
            LAST_PROTECTED_CACHE_RESULT = v;
            if (!v.ok) {
                throw new Error('protected_cache_incomplete_offline:' + JSON.stringify(v.missing || []));
            }
            return v;
        });
    }
    var base = publicBaseUrl();
    function populateQueue(cache, queue) {
        var results = [];
        return queue.reduce(function (chain, rel) {
            return chain.then(function () {
                return cacheOneProtectedAssetWithRetry(cache, base, rel, 2).then(function (row) {
                    results.push(row);
                    return null;
                });
            });
        }, Promise.resolve()).then(function () {
            return results;
        });
    }
    function finalize(results) {
        return verifyProtectedOfflineCache().then(function (v) {
            v.cached = results || [];
            LAST_PROTECTED_CACHE_RESULT = v;
            if (!v.ok) {
                throw new Error('protected_cache_verify_failed:' + JSON.stringify(v.missing || []));
            }
            return v;
        });
    }
    return caches.open(ERP_COEXIST_CACHE).then(function (cache) {
        if (!opts.force) {
            return verifyProtectedOfflineCache().then(function (v) {
                if (v.ok) {
                    LAST_PROTECTED_CACHE_RESULT = v;
                    return v;
                }
                var missing = (v.missing || []).map(function (m) {
                    return m.rel;
                });
                var aliases = PROTECTED_OFFLINE_ALIAS_QUERIES.filter(function (a) {
                    var bare = String(a).split('?')[0];
                    return missing.indexOf(bare) !== -1 || missing.indexOf(a) !== -1;
                });
                var queue = missing.concat(aliases);
                if (queue.length === 0) {
                    queue = PROTECTED_OFFLINE_RELS.concat(PROTECTED_OFFLINE_ALIAS_QUERIES);
                }
                return populateQueue(cache, queue).then(finalize);
            });
        }
        var queue = PROTECTED_OFFLINE_RELS.concat(PROTECTED_OFFLINE_ALIAS_QUERIES);
        return populateQueue(cache, queue).then(finalize);
    });
}

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
    'hr/holidays',
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

function registerCertMetaUrl() {
    try {
        return new URL(REGISTER_CERT_META_PATH, self.registration.scope).href;
    } catch (e) {
        return self.location.origin + '/rateb-erp/public/' + REGISTER_CERT_META_PATH;
    }
}

/** Phase OJ — reject gate / stub / redirect shells; require real register markup. */
function isCertifiedRegisterHtml(html) {
    var body = String(html || '');
    if (body.length < 2500) {
        return false;
    }
    if (/data-pos-biometric-gate/i.test(body)) {
        return false;
    }
    if (/<title>\s*POS Offline\s*<\/title>|data-rateb-uncached-page|نقطة البيع غير متصلة/i.test(body.slice(0, 4000))) {
        return false;
    }
    if (/التحقق البيومتري/i.test(body.slice(0, 3000)) && !/data-pos-register(?:\s|=|>)/i.test(body)) {
        return false;
    }
    if (!/data-pos-register(?:\s|=|>)/i.test(body)) {
        return false;
    }
    return true;
}

function simpleHtmlHash(html) {
    var s0 = String(html || '');
    var h = 2166136261;
    for (var i = 0; i < s0.length; i += 1) {
        h ^= s0.charCodeAt(i);
        h = Math.imul(h, 16777619);
    }
    return 'fnv1a:' + (h >>> 0).toString(16) + ':len:' + s0.length;
}

function biometricRequiredOfflineResponse() {
    var body = '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8">'
        + '<meta name="viewport" content="width=device-width,initial-scale=1">'
        + '<title>POS — يلزم التحقق البيومتري</title>'
        + '<style>body{font-family:system-ui,sans-serif;margin:0;padding:2rem;background:#0f1117;color:#e8eaed;text-align:center}'
        + 'h1{font-size:1.2rem;margin:0 0 .75rem}p{opacity:.9;line-height:1.55;max-width:28rem;margin:.6rem auto}'
        + 'a{color:#8ab4ff}</style></head>'
        + '<body data-rateb-pos-bio-required="1">'
        + '<h1>يلزم التحقق البيومتري قبل استخدام نقطة البيع أوفلاين</h1>'
        + '<p>Biometric verification required before offline POS can be used.</p>'
        + '<p>افتح نقطة البيع وأنت متصل، أكمل التحقق البيومتري مرة واحدة، ثم أعد المحاولة دون إنترنت.</p>'
        + '<p><a id="a-bio" href="#">فتح بوابة التحقق</a> · <a id="a-reg" href="#">شاشة البيع</a></p>'
        + '<script>(function(){try{var u=new URL(location.href);var cid=u.searchParams.get("company_id")||"";'
        + 'var q="?rateb_live=1"+(cid?("&company_id="+encodeURIComponent(cid)):"");var base=u.pathname.replace(/\\/register\\/?$/,"").replace(/\\/biometric\\/?$/,"");'
        + 'var bio=base.replace(/\\/?$/,"")+"/biometric"+q;var reg=base.replace(/\\/?$/,"")+"/register"+q;'
        + 'var a1=document.getElementById("a-bio");var a2=document.getElementById("a-reg");'
        + 'if(a1)a1.href=bio;if(a2)a2.href=reg;}catch(e){}})();<\/script>'
        + '</body></html>';
    return new Response(body, {
        status: 200,
        headers: {
            'Content-Type': 'text/html; charset=utf-8',
            'Cache-Control': 'no-store',
            'X-Rateb-Offline': '1',
            'X-Rateb-Pos-Bio-Required': '1'
        }
    });
}

/** Online POS load failed — never show offline-only bio placeholder while the browser is up. */
function posErpScopeBase() {
    try {
        if (self.registration && self.registration.scope) {
            return String(self.registration.scope).replace(/\/?$/, '/');
        }
    } catch (eScope) { /* ignore */ }
    return String(self.location.origin || '') + '/rateb-erp/public/';
}

function posTryParseJson(body) {
    try {
        var t = String(body || '').trim();
        if (!t || t.charAt(0) !== '{') {
            return null;
        }
        return JSON.parse(t);
    } catch (eJson) {
        return null;
    }
}

function posCompanyIdFromRequest(request) {
    try {
        var u = new URL(typeof request === 'string' ? request : (request && request.url ? request.url : ''), self.location.origin);
        return parseInt(u.searchParams.get('company_id') || '0', 10) || 0;
    } catch (eCid) {
        return 0;
    }
}

function posAdminRedirectUrl(request, preferCompanyEdit) {
    var base = posErpScopeBase();
    // Never bounce ops module denials to company edit — that trapped Super Admin
    // on "module not in plan" while opening logistics/procurement soft-nav.
    try {
        return new URL('admin', base).href;
    } catch (eAdmin) {
        return base + 'admin';
    }
}

function posHttpRedirectResponse(targetUrl) {
    return new Response('', {
        status: 302,
        headers: {
            Location: String(targetUrl || '/rateb-erp/public/admin'),
            'Cache-Control': 'no-store',
            'X-Rateb-Pos-Redirect': '1'
        }
    });
}

function posHandleLiveNetworkResponse(response, request) {
    if (response && response.ok) {
        if (response.redirected) {
            try {
                var finalUrl = String(response.url || '');
                if (finalUrl && /\/admin(\/|$)/i.test(finalUrl)) {
                    return posHttpRedirectResponse(finalUrl);
                }
            } catch (eRedir) { /* ignore */ }
        }
        return Promise.resolve(response);
    }
    if (!response) {
        return Promise.resolve(posHttpRedirectResponse(posAdminRedirectUrl(request, false)));
    }
    return response.clone().text().then(function (body) {
        var json = posTryParseJson(body);
        // Pass through module/plan denials for non-POS Admin soft-nav — do not
        // synthesize a companies/{id}/edit redirect (broke Super Admin logistics).
        if (json && (json.code === 'module_not_in_plan' || json.code === 'module_not_allowed')) {
            try {
                var reqUrl = String((request && request.url) || '');
                if (/\/admin\/ops\/(?!pos(?:\/|$))/i.test(reqUrl) || /\/admin\/(?!ops\/pos)/i.test(reqUrl)) {
                    return new Response(body, {
                        status: response.status || 403,
                        statusText: response.statusText || 'Forbidden',
                        headers: {
                            'Content-Type': 'application/json; charset=utf-8',
                            'Cache-Control': 'no-store',
                            'X-Rateb-Pos-Passthrough': 'module-gate'
                        }
                    });
                }
            } catch (ePass) { /* fall through */ }
            return posHttpRedirectResponse(posAdminRedirectUrl(request, false));
        }
        return posHttpRedirectResponse(posAdminRedirectUrl(request, false));
    }).catch(function () {
        return posHttpRedirectResponse(posAdminRedirectUrl(request, false));
    });
}

function fetchPosLiveOrShowRetry(request) {
    return fetchNavigateNetworkPassthrough(request, 8000).then(function (response) {
        return posHandleLiveNetworkResponse(response, request);
    }).catch(function () {
        return posHttpRedirectResponse(posAdminRedirectUrl(request, false));
    });
}

function posBioRequiredOrLiveRetry(request) {
    if (isHardBrowserOffline()) {
        return Promise.resolve(biometricRequiredOfflineResponse());
    }
    return fetchPosLiveOrShowRetry(request);
}


var OFFLINE_HTML = '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>POS Offline</title><style>body{font-family:system-ui,sans-serif;margin:0;padding:2rem;background:#0f1117;color:#e8eaed;text-align:center}h1{font-size:1.25rem}a{color:#a78bfa;display:inline-block;margin:.5rem}p{opacity:.85}</style></head><body><h1 id="t">نقطة البيع غير متصلة</h1><p id="m">جاري البحث عن نسخة محفوظة من شاشة البيع…</p><p id="links" hidden><a id="a1" href="#">شاشة البيع</a> · <a id="a2" href="#">شاشة البيع /register</a></p><script>(function(){var SHELL="rateb-pos-shell-v8";var KEY="__rateb_pos_register_shell__";function showFail(){var m=document.getElementById("m");var links=document.getElementById("links");if(m)m.textContent="افتح شاشة البيع مرة واحدة وأنت متصل بالإنترنت، ثم أعد المحاولة دون إنترنت. التقارير والإعدادات تحتاج اتصال.";if(links)links.hidden=false;try{var u=new URL(location.href);var cid=u.searchParams.get("company_id")||"";var q=cid?("?company_id="+cid):"";var base=u.pathname.replace(/\\/register\\/?$/,"").replace(/\\/(reports|settings|dashboard|shifts|terminals).*$/,"");var a1=document.getElementById("a1");var a2=document.getElementById("a2");if(a1)a1.href=base+q;if(a2)a2.href=base.replace(/\\/?$/,"")+"/register"+q;}catch(e){}}function useResponse(res){if(!res)return Promise.resolve(false);return res.text().then(function(html){if(!html||html.indexOf("data-pos-register")<0)return false;document.open();document.write(html);document.close();return true;});}if(!("caches" in window)){showFail();return;}caches.open(SHELL).then(function(cache){var u=new URL(location.href);var candidates=[new URL(KEY,location.origin+"/rateb-erp/public/").href,u.origin+u.pathname,u.href,u.origin+u.pathname.replace(/\\/register\\/?$/,""),u.origin+u.pathname.replace(/\\/register\\/?$/,"")+(u.search||""),u.origin+u.pathname.replace(/\\/?$/,"")+"/register",u.origin+u.pathname.replace(/\\/?$/,"")+"/register"+(u.search||"")];return candidates.reduce(function(p,url){return p.then(function(done){if(done)return true;return cache.match(url).then(useResponse);});},Promise.resolve(false)).then(function(done){if(done)return;return cache.keys().then(function(keys){var next=Promise.resolve(false);keys.forEach(function(req){next=next.then(function(done){if(done)return true;var href=typeof req==="string"?req:(req&&req.url)||"";if(href.indexOf("/pos")<0)return false;return cache.match(req).then(useResponse);});});return next;});}).then(function(done){if(!done)showFail();});}).catch(showFail);})();</script></body></html>';

function isPosNavigation(url) {
    // Strict path segments only — never match unrelated URLs (e.g. access-control).
    var p = String((url && url.pathname) || '');
    return /\/(?:admin\/ops\/)?pos(?:\/|$)/i.test(p);
}

/**
 * POS admin CRUD / hub pages (pos-pages-shell). Not offline register runtime.
 * Must never receive biometricRequiredOfflineResponse / certified register shell.
 */
function isPosAdminCrudPath(pathname) {
    var p = String(pathname || '');
    return /\/(?:admin\/ops\/)?pos\/(dashboard|terminals|devices|settings|shifts|reports|orders|cash-drawers|sync|returns)(\/|$)/i.test(p);
}

/** Selling runtime only: bare /pos, /register, /biometric. */
function isPosRuntimePath(pathname) {
    return isRegisterShellPath(pathname) || isBiometricGatePath(pathname);
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

/** Hard-offline HTML for POS admin CRUD — not unlock / biometric flow. */
function posAdminConnectionRequiredResponse() {
    var body = '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8">'
        + '<meta name="viewport" content="width=device-width,initial-scale=1">'
        + '<title>POS — يلزم الاتصال</title>'
        + '<style>body{font-family:system-ui,sans-serif;margin:0;padding:2rem;background:#0f1117;color:#e8eaed;text-align:center}'
        + 'h1{font-size:1.2rem;margin:0 0 .75rem}p{opacity:.9;line-height:1.55;max-width:28rem;margin:.6rem auto}'
        + 'a{color:#8ab4ff}</style></head>'
        + '<body data-rateb-pos-admin-offline="1">'
        + '<h1>يلزم الاتصال بالإنترنت لفتح هذه الصفحة</h1>'
        + '<p>Connection required to open this POS admin page.</p>'
        + '<p>الشاشات الإدارية (الأجهزة، النهايات، الإعدادات، التقارير) تحتاج شبكة. شاشة البيع أوفلاين منفصلة.</p>'
        + '<p><a id="a-admin" href="#">لوحة التحكم</a> · <a id="a-reg" href="#">شاشة البيع</a></p>'
        + '<script>(function(){try{var u=new URL(location.href);var cid=u.searchParams.get("company_id")||"";'
        + 'var q=cid?("?company_id="+cid):"";var base=u.pathname.replace(/\\/(dashboard|terminals|devices|settings|shifts|reports|orders|cash-drawers|sync|returns)(\\/.*)?$/i,"");'
        + 'var a1=document.getElementById("a-admin");var a2=document.getElementById("a-reg");'
        + 'if(a1)a1.href=u.origin+u.pathname.replace(/\\/admin\\/ops\\/pos.*/i,"/admin/");'
        + 'if(a2)a2.href=base.replace(/\\/?$/,"")+"/register"+q;}catch(e){}})();<\/script>'
        + '</body></html>';
    return new Response(body, {
        status: 200,
        headers: {
            'Content-Type': 'text/html; charset=utf-8',
            'Cache-Control': 'no-store',
            'X-Rateb-Offline': '1',
            'X-Rateb-Pos-Admin-Offline': '1'
        }
    });
}

/**
 * Network-first document fetch for POS admin CRUD.
 * Ignores soft cloud-degraded latch (assets-only). Never falls to biometric shell.
 */
function fetchPosAdminCrudNetwork(request, timeoutMs) {
    if (isLocalApplianceOrigin()) {
        return fetch(navigateFetchInput(request)).then(asNonRedirectedResponse).then(function (res) {
            return res || Promise.reject(new Error('empty-response'));
        });
    }
    if (isHardBrowserOffline()) {
        return Promise.reject(new Error('hard-offline'));
    }
    var ms = typeof timeoutMs === 'number' ? timeoutMs : 8000;
    var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
    var timer = setTimeout(function () {
        if (ctrl) {
            try { ctrl.abort(); } catch (eAb) { /* ignore */ }
        }
    }, ms);
    return fetch(navigateFetchInput(request), {
        signal: ctrl ? ctrl.signal : undefined
    }).then(asNonRedirectedResponse).then(function (response) {
        clearTimeout(timer);
        if (!response || !response.ok) {
            return Promise.reject(new Error('bad-navigate-status'));
        }
        clearCloudNetworkDegraded();
        return response;
    }).catch(function (err) {
        clearTimeout(timer);
        return Promise.reject(err || new Error('crud-navigate-fail'));
    });
}

function navigatePosAdminCrudDocument(request) {
    if (isHardBrowserOffline()) {
        return Promise.resolve(posAdminConnectionRequiredResponse());
    }
    return fetchPosAdminCrudNetwork(request, 8000).catch(function () {
        if (isHardBrowserOffline()) {
            return posAdminConnectionRequiredResponse();
        }
        // Soft latch / transient miss: one more plain network attempt (no latch gate).
        return fetch(navigateFetchInput(request)).then(asNonRedirectedResponse).then(function (res) {
            if (res && res.ok) {
                clearCloudNetworkDegraded();
                return res;
            }
            return posAdminConnectionRequiredResponse();
        }).catch(function () {
            return posAdminConnectionRequiredResponse();
        });
    });
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

/** Public guest QR menu (/m/{slug}) — never SW-shell; always network. */
function isGuestMenuPath(pathname) {
    return /\/m\/[^/?#]+/i.test(String(pathname || ''));
}

/** Exact ERP dashboard (/…/admin) — must network-first so logout/login session matches HTML. */
function isExactAdminDashboardPath(pathname) {
    var p = String(pathname || '').replace(/\/+$/, '');
    return /(^|\/)admin$/i.test(p);
}

/** Drop cached Admin HTML after logout/login so stale authenticated snapshots are not shown. */
function purgeErpOpsAuthPages() {
    var adminRe = /\/admin(\/|$)/i;
    var cacheNames = [ERP_OPS_PAGE_CACHE, ERP_COEXIST_CACHE];
    return Promise.all(cacheNames.map(function (cacheName) {
        return caches.open(cacheName).then(function (cache) {
            return cache.keys().then(function (keys) {
                return Promise.all((keys || []).map(function (req) {
                    try {
                        var href = typeof req === 'string' ? req : (req.url || '');
                        var u = new URL(href);
                        if (adminRe.test(u.pathname)) {
                            return cache.delete(req).catch(function () { return false; });
                        }
                    } catch (ePurge) { /* ignore */ }
                    return null;
                }));
            });
        }).catch(function () { return null; });
    }));
}

/** Any ERP /admin path (except POS register flows). */
function isErpAdminPath(pathname) {
    return /\/admin(\/|$)/i.test(String(pathname || ''));
}

function erpOfflineShellUrl() {
    try {
        return new URL(ERP_OFFLINE_SHELL, self.registration.scope).href;
    } catch (e) {
        return self.location.origin + '/rateb-erp/public/' + ERP_OFFLINE_SHELL;
    }
}

function erpInlineShellKeyUrl() {
    try {
        return new URL(ERP_INLINE_SHELL_KEY, self.registration.scope).href;
    } catch (e) {
        return self.location.origin + '/rateb-erp/public/' + ERP_INLINE_SHELL_KEY;
    }
}

/** PERF-P0.3-C — thin inline shell has no OA bootstrap scripts (causes oa_bootstrap_missing). */
function isThinInlineOfflineShellResponse(res) {
    if (!res || !res.headers) {
        return false;
    }
    try {
        if (String(res.headers.get('X-Rateb-Inline-Shell') || '') === '1') {
            return true;
        }
        if (String(res.headers.get('X-Rateb-Coexist') || '') === 'pos-sw') {
            return true;
        }
    } catch (eH) { /* ignore */ }
    return false;
}

function rejectThinOfflineShellHit(res) {
    if (!res) {
        return Promise.resolve(null);
    }
    if (isThinInlineOfflineShellResponse(res)) {
        return Promise.resolve(null);
    }
    return res.clone().text().then(function (text) {
        var t = String(text || '');
        if (t.indexOf('oa_bootstrap_missing') !== -1
            || t.indexOf('loadOfflineScript') !== -1
            || t.indexOf('offline-bootstrap.js') !== -1) {
            return res;
        }
        if (t.length < 4000 && t.indexOf('وضع عدم الاتصال') !== -1 && t.indexOf('<script') === -1) {
            return null;
        }
        return res;
    }).catch(function () {
        return res;
    });
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
        ['الإجازات', 'admin/hr/holidays'],
        ['طلبات الإجازات', 'admin/hr/leaves'],
        ['الموظفون', 'admin/hr/employees'],
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
        + '<script>(function(){try{if(navigator.onLine===false)return;'
        + 'var scope=' + JSON.stringify(base) + ';'
        + 'var live=scope+"admin/";'
        + 'fetch(scope+"connectivity-probe.json?_="+Date.now(),{cache:"no-store",credentials:"same-origin"})'
        + '.then(function(r){if(r&&r.ok)location.replace(live);})'
        + '.catch(function(){});'
        + '}catch(e){}})();<\/script>'
        + '</body></html>';
    return new Response(body, {
        status: 200,
        headers: {
            'Content-Type': 'text/html; charset=utf-8',
            'X-Rateb-Offline': '1',
            'X-Rateb-Coexist': 'pos-sw',
            'X-Rateb-Inline-Shell': '1',
            'Cache-Control': 'no-store'
        }
    });
}

/**
 * PERF-P0.3-C — seed LAST-RESORT inline under a private key only (never offline-shell.html).
 * Prefer network-fetch of the real OA offline-shell.html into coexist when online.
 */
function seedInlineOfflineShell() {
    var inlineKey = erpInlineShellKeyUrl();
    var inlineRes = erpInlineShellResponse();
    var shellUrl = erpOfflineShellUrl();
    return caches.open(ERP_COEXIST_CACHE).then(function (cache) {
        var purge = Promise.all([
            cache.put(inlineKey, inlineRes.clone()).catch(function () { return null; }),
            cache.put(ERP_INLINE_SHELL_KEY, inlineRes.clone()).catch(function () { return null; }),
            cache.delete(shellUrl).catch(function () { return null; }),
            cache.delete(ERP_OFFLINE_SHELL).catch(function () { return null; })
        ]);
        return purge.then(function () {
            if (isCloudBrowserOffline()) {
                return null;
            }
            return fetch(shellUrl, {
                credentials: 'same-origin',
                cache: 'reload',
                headers: {
                    Accept: 'text/html',
                    'X-Rateb-Shell-Warm': '1',
                    'X-Rateb-Protected-Warm': '1'
                }
            }).then(function (res) {
                if (!res || !res.ok) {
                    throw new Error('shell_fetch_fail');
                }
                return res.text().then(function (text) {
                    if (!isAcceptableProtectedBody('offline-shell.html', text)) {
                        throw new Error('shell_bad_body:' + String(text || '').length);
                    }
                    var headers = {
                        'Content-Type': 'text/html; charset=utf-8',
                        'X-Rateb-Protected-Cached': '1',
                        'X-Rateb-OA-Shell': '1'
                    };
                    var body = text;
                    return Promise.all([
                        cache.put(shellUrl, new Response(body, { status: 200, headers: headers })),
                        cache.put(ERP_OFFLINE_SHELL, new Response(body, { status: 200, headers: headers }))
                            .catch(function () { return null; })
                    ]);
                });
            }).catch(function () {
                return null;
            });
        });
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
            // admin/ops/* ↔ admin/* aliases (offline often opens one while cache has the other).
            var p = String(url.pathname || '');
            if (/\/admin\/ops\//i.test(p)) {
                var p2 = p.replace(/\/admin\/ops\//i, '/admin/');
                candidates.push(url.origin + p2);
                candidates.push(url.origin + p2.replace(/\/+$/, ''));
                if (url.search) {
                    candidates.push(url.origin + p2 + url.search);
                }
            } else if (/\/admin\/(?!ops\/)/i.test(p) && !/(^|\/)admin$/i.test(p.replace(/\/+$/, ''))) {
                var p3 = p.replace(/\/admin\//i, '/admin/ops/');
                candidates.push(url.origin + p3);
                candidates.push(url.origin + p3.replace(/\/+$/, ''));
                if (url.search) {
                    candidates.push(url.origin + p3 + url.search);
                }
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
            // Query-string variants (?company_id=) — ignoreSearch is enough; never cache.keys().
            return cache.match(url.origin + url.pathname, { ignoreSearch: true });
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

function parentAdminListUrl(url) {
    try {
        var u = new URL(url.href || String(url));
        var path = String(u.pathname || '').replace(/\/+$/, '');
        var next = path
            .replace(/\/\d+\/(edit|show|view|generate)(\/|$)/i, '/')
            .replace(/\/(create|new)(\/|$)/i, '/')
            .replace(/\/+$/, '');
        if (!next || next === path) {
            return null;
        }
        if (!/\/admin(\/|$)/i.test(next)) {
            return null;
        }
        u.pathname = next;
        return u;
    } catch (e) {
        return null;
    }
}

/**
 * Smart coexist: when this SW owns the shared scope, serve ERP ops page
 * (if allowlisted) then offline-shell for non-POS admin navigations.
 * @param {Request} [request]
 * @param {URL} [url]
 */
function erpAdminOfflineFallback(request, url) {
    // Instant exact match only — never cache.keys() / multi-version walks on navigate.
    return matchSoftOnlineExactCache(request, url).then(function (hit) {
        if (hit) {
            return hit;
        }
        var parent = parentAdminListUrl(url);
        if (parent) {
            return matchSoftOnlineExactCache(null, parent).then(function (listHit) {
                if (listHit) {
                    return listHit;
                }
                return finishUncached(url);
            });
        }
        return finishUncached(url);
    }).catch(function () {
        try {
            return uncachedAdminBrowseResponse(url);
        } catch (eU) {
            return erpInlineShellResponse();
        }
    });

    function finishUncached(u) {
        var pathNorm = '';
        try {
            pathNorm = String((u && u.pathname) || '').replace(/\/+$/, '');
        } catch (eP) { /* ignore */ }
        if (/(^|\/)admin$/i.test(pathNorm)) {
            return matchCachedAdminDashboard(u).then(function (dash) {
                return dash || uncachedAdminBrowseResponse(u);
            });
        }
        return uncachedAdminBrowseResponse(u);
    }
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

/**
 * Offline / soft-latch admin document navigate — always paint within ~250ms.
 * Never leave respondWith pending (pure black tab). Prefer validated cache, else inline shell.
 */
function safeOfflineAdminNavigate(request, url, event) {
    var pageUrl = '';
    try {
        pageUrl = String((request && request.url) || (url && url.href) || '');
    } catch (e0) {
        pageUrl = '';
    }
    var bareAdmin = false;
    try {
        bareAdmin = /\/admin$/i.test(String((url && url.pathname) || '').replace(/\/+$/, ''));
    } catch (e1) {
        bareAdmin = false;
    }

    return new Promise(function (resolve) {
        var settled = false;
        function finish(res) {
            if (settled || !res) {
                return;
            }
            Promise.resolve(res).then(function (real) {
                if (settled || !real || typeof real.status !== 'number') {
                    return;
                }
                settled = true;
                resolve(real);
            }).catch(function () { /* ignore */ });
        }

        function inlineNow() {
            try {
                finish(erpInlineShellResponse());
            } catch (eShell) {
                try {
                    finish(uncachedAdminBrowseResponse(url));
                } catch (eUn) {
                    finish(new Response(
                        '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8">'
                        + '<meta name="color-scheme" content="dark"><title>وضع عدم الاتصال</title></head>'
                        + '<body style="margin:0;font-family:system-ui;background:#0f1117;color:#e8eaed;'
                        + 'display:flex;min-height:100vh;align-items:center;justify-content:center;text-align:center;padding:2rem">'
                        + '<div><h1>وضع عدم الاتصال</h1><p>افتح لوحة التحكم وأنت متصل مرة واحدة لحفظ الصفحة.</p></div>'
                        + '</body></html>',
                        { status: 200, headers: { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' } }
                    ));
                }
            }
        }

        function adminHtmlWithOfflineStub(baseHtml, destUrl) {
            var path = '';
            try {
                path = String((destUrl && destUrl.pathname) || '');
            } catch (eP) {
                path = '';
            }
            var parts = path.split('/').filter(Boolean);
            var label = parts.length ? parts[parts.length - 1] : 'صفحة';
            var stubInner = '<div class="container-fluid py-4" data-rateb-offline-stub="1">'
                + '<div class="rateb-card p-4" style="max-width:40rem;margin:0 auto;text-align:center">'
                + '<h2 class="h4 mb-2">' + label.replace(/</g, '') + '</h2>'
                + '<p class="text-muted mb-0" style="line-height:1.6">'
                + 'فتحت الصفحة أوفلاين داخل النظام. النسخة الكاملة والبيانات تُحمَّل عند الاتصال.'
                + '</p>'
                + '<p class="small text-muted mt-3 mb-0" dir="ltr" style="opacity:.7">'
                + path.replace(/</g, '') + '</p></div></div>';
            var body = String(baseHtml || '');
            if (/id=["']rateb-main-content["']/i.test(body)) {
                body = body.replace(
                    /(<main[^>]*id=["']rateb-main-content["'][^>]*>)[\s\S]*?(<\/main>)/i,
                    '$1' + stubInner + '$2'
                );
            } else if (/<main[^>]*class=["'][^"']*rateb-content/i.test(body)) {
                body = body.replace(
                    /(<main[^>]*class=["'][^"']*rateb-content[^"']*["'][^>]*>)[\s\S]*?(<\/main>)/i,
                    '$1' + stubInner + '$2'
                );
            } else {
                return null;
            }
            body = body.replace(/<title>[^<]*<\/title>/i, '<title>' + label.replace(/</g, '') + ' | RATEB ERP</title>');
            return body;
        }

        function finishWithShellStub() {
            if (settled) {
                return Promise.resolve(null);
            }
            return matchCachedAdminDashboard(url).catch(function () {
                return null;
            }).then(function (dash) {
                if (dash) {
                    return dash;
                }
                // Fallback hubs commonly warmed early.
                var hubs = [];
                try {
                    var origin = (url && url.origin) || self.location.origin;
                    var base = '/rateb-erp/public/';
                    try {
                        base = self.registration.scope;
                    } catch (eSc) { /* ignore */ }
                    hubs = [
                        origin + base.replace(/\/?$/, '/') + 'admin/',
                        origin + base.replace(/\/?$/, '/') + 'admin/companies',
                        origin + base.replace(/\/?$/, '/') + 'admin/notifications'
                    ];
                } catch (eH) { /* ignore */ }
                return caches.open(ERP_OPS_PAGE_CACHE).then(function (cache) {
                    var chain = Promise.resolve(null);
                    hubs.forEach(function (key) {
                        chain = chain.then(function (found) {
                            return found || cache.match(key, { ignoreSearch: true }).catch(function () {
                                return null;
                            });
                        });
                    });
                    return chain;
                }).catch(function () {
                    return null;
                });
            }).then(function (hit) {
                if (!hit || settled) {
                    return null;
                }
                return hit.clone().text().then(function (html) {
                    if (settled) {
                        return null;
                    }
                    var body = String(html || '');
                    if (!isValidErpOpsHtmlBody(pageUrl || '', body) || body.length < 8000) {
                        return null;
                    }
                    var stubbed = adminHtmlWithOfflineStub(body, url);
                    if (!stubbed) {
                        return null;
                    }
                    return new Response(stubbed, {
                        status: 200,
                        statusText: 'OK',
                        headers: {
                            'Content-Type': 'text/html; charset=utf-8',
                            'X-Rateb-Offline': '1',
                            'X-Rateb-Offline-Stub': '1',
                            'Cache-Control': 'no-store'
                        }
                    });
                }).catch(function () {
                    return null;
                });
            }).then(function (res) {
                if (res) {
                    finish(res);
                    return res;
                }
                inlineNow();
                return null;
            }).catch(function () {
                inlineNow();
                return null;
            });
        }

        // Offline F5 #2+: Cache.put from prior paint can stall match — give Cache API time.
        // Always use a real ceiling (never leave respondWith pending → black spinner).
        var ceilingMs = bareAdmin ? 1800 : 2000;
        setTimeout(function () {
            if (!settled) {
                finishWithShellStub();
            }
        }, ceilingMs);

        function acceptCached(hit) {
            if (!hit || settled) {
                return Promise.resolve(null);
            }
            try {
                if (hit.headers && String(hit.headers.get('X-Rateb-Inline-Shell') || '') === '1') {
                    try {
                        caches.open(ERP_OPS_PAGE_CACHE).then(function (c) {
                            return c.delete(pageUrl || (url && url.href) || '');
                        }).catch(function () { return null; });
                    } catch (eDel) { /* ignore */ }
                    return Promise.resolve(null);
                }
            } catch (eHdr) { /* ignore */ }
            return hit.clone().text().then(function (html) {
                if (settled) {
                    return null;
                }
                var body = String(html || '');
                if (!isValidErpOpsHtmlBody(pageUrl || (url && url.href) || '', body)) {
                    return null;
                }
                if (body.length < 8000) {
                    return null;
                }
                var headers = new Headers({
                    'Content-Type': 'text/html; charset=utf-8',
                    'X-Rateb-Offline': '1',
                    'X-Rateb-Ops-Page': '1',
                    'Cache-Control': 'no-store'
                });
                return new Response(body, { status: 200, statusText: 'OK', headers: headers });
            }).catch(function () {
                return null;
            });
        }

        // Prefer ignoreSearch first offline — company_id variants + Cache.put contention.
        var pathKey = '';
        try {
            pathKey = url && url.origin && url.pathname
                ? (url.origin + url.pathname)
                : '';
        } catch (ePk) {
            pathKey = '';
        }
        var cacheTry = caches.open(ERP_OPS_PAGE_CACHE).then(function (cache) {
            var first = pathKey
                ? cache.match(pathKey, { ignoreSearch: true }).catch(function () { return null; })
                : Promise.resolve(null);
            return first.then(function (hit) {
                if (hit) {
                    return acceptCached(hit);
                }
                if (bareAdmin) {
                    return matchCachedAdminDashboard(url).then(function (dash) {
                        return acceptCached(dash).then(function (ok) {
                            return ok || matchSoftOnlineExactCache(request, url).then(acceptCached);
                        });
                    });
                }
                return matchSoftOnlineExactCache(request, url).then(acceptCached);
            });
        }).catch(function () {
            return null;
        }).then(function (ok) {
            if (ok) {
                finish(ok);
                return ok;
            }
            return null;
        }).catch(function () {
            return null;
        });

        cacheTry.then(function (ok) {
            if (!ok && !settled) {
                finishWithShellStub();
            }
        });
    });
}

/**
 * Every /admin document navigation — always handled by SW (never Chrome interstitial).
 * Online: network first (passthrough). Offline/soft-latch/fail: safeOfflineAdminNavigate.
 */
function adminDocumentNavigate(request, url, event) {
    // Hard offline only → shell stub. Soft-latch / timeouts must NOT fake "أوفلاين"
    // while the UI badge still says متصل (that caused the click-to-click mess).
    if (isHardBrowserOffline()) {
        return safeOfflineAdminNavigate(request, url, event);
    }

    // Explicit live recovery from lean offline shell / stale SW.
    try {
        if (url && url.searchParams
            && (url.searchParams.get('rateb_force_live') || url.searchParams.get('rateb_live') === '1')) {
            // Prefer real network body; on failure show static help (never re-stamp force_live).
            return fetch(request, { cache: 'no-store', credentials: 'same-origin', redirect: 'follow' })
                .then(function (res) {
                    if (res && typeof res.status === 'number') {
                        return res;
                    }
                    return onlineAdminRetryResponse(url);
                })
                .catch(function () {
                    return onlineAdminRetryResponse(url);
                });
        }
    } catch (eLive) { /* fall through */ }

    var pageUrl = '';
    try {
        pageUrl = String((request && request.url) || (url && url.href) || '');
    } catch (eP) {
        pageUrl = '';
    }

    return fetchNavigateNetworkPassthrough(request, 12000).then(function (response) {
        if (response && response.ok) {
            try {
                // Clone before respondWith consumes body (same bug as storeLive).
                var toCache = response.clone();
                var store = new Promise(function (resolve) {
                    setTimeout(function () {
                        caches.open(ERP_OPS_PAGE_CACHE).then(function (opsCache) {
                            return putErpOpsHtmlResponse(opsCache, pageUrl, toCache);
                        }).catch(function () { return null; }).then(resolve);
                    }, 800);
                });
                if (event && typeof event.waitUntil === 'function') {
                    event.waitUntil(store);
                }
            } catch (eStore) { /* ignore */ }
            return response;
        }
        // Pass through real 403/500/etc — never rewrite as offline stub while online.
        if (response) {
            return response;
        }
        return matchSoftOnlineExactCache(request, url).then(function (hit) {
            if (hit) {
                return hit.clone().text().then(function (body) {
                    if (isValidErpOpsHtmlBody(pageUrl, body)
                        && !/data-rateb-offline-stub/i.test(String(body || ''))) {
                        return withSoftOfflineCacheHeader(hit.clone(), { softOnly: true });
                    }
                    return null;
                }).catch(function () { return null; });
            }
            return null;
        }).then(function (cached) {
            if (cached) {
                return cached;
            }
            return fetch(navigateFetchInput(request)).then(asNonRedirectedResponse);
        });
    }).catch(function () {
        markCloudNetworkDegraded('admin-nav-fail');
        if (isHardBrowserOffline()) {
            return safeOfflineAdminNavigate(request, url, event);
        }
        return matchSoftOnlineExactCache(request, url).then(function (hit) {
            if (hit) {
                return hit.clone().text().then(function (body) {
                    if (isValidErpOpsHtmlBody(pageUrl, body)
                        && !/data-rateb-offline-stub/i.test(String(body || ''))) {
                        return withSoftOfflineCacheHeader(hit.clone(), { softOnly: true });
                    }
                    return null;
                }).catch(function () { return null; });
            }
            return null;
        }).then(function (cached) {
            if (cached) {
                return cached;
            }
            // Last resort online: network again (no fake offline card).
            return fetch(navigateFetchInput(request)).then(asNonRedirectedResponse).then(function (res) {
                if (res) {
                    return res;
                }
                // Never resolve null into respondWith — paint a live-retry page instead of offline shell.
                return onlineAdminRetryResponse(url);
            });
        });
    });
}

/** Online network failed — static recovery page (NO auto-redirect loop). */
function onlineAdminRetryResponse(url) {
    var href = '/rateb-erp/public/admin/';
    try {
        var base = self.registration.scope;
        if (base.slice(-1) !== '/') {
            base += '/';
        }
        // Always land on clean /admin/ — never bounce the same URL with a new force_live stamp.
        href = base + 'admin/';
    } catch (eH) { /* ignore */ }
    var body = '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8">'
        + '<meta name="viewport" content="width=device-width,initial-scale=1">'
        + '<title>RATEB ERP</title></head>'
        + '<body style="font-family:system-ui;background:#0f1117;color:#e8eaed;padding:2rem;text-align:center">'
        + '<h1>تعذّر تحميل الصفحة عبر الكاش</h1>'
        + '<p>أنت متصل. اضغط «إعادة التشغيل للتحديث» أعلى المتصفح، ثم افتح لوحة التحكم.</p>'
        + '<p><a style="color:#8ab4ff" href="' + href.replace(/"/g, '') + '">فتح لوحة التحكم</a></p>'
        + '<p class="small" style="opacity:.7;margin-top:1.5rem">أو Ctrl+Shift+R لتفريغ الكاش</p>'
        + '</body></html>';
    return new Response(body, {
        status: 200,
        headers: {
            'Content-Type': 'text/html; charset=utf-8',
            'Cache-Control': 'no-store',
            'X-Rateb-Online-Retry': '1'
        }
    });
}

/**
 * Phase OK.1 — Soft-offline / degraded network navigation safety.
 * navigator.onLine===true must NOT leave Document fetches hanging on the real network.
 * Prefer a cached ERP snapshot when present; otherwise network with short timeout, then cache.
 */
function matchErpNavSnapshot(request, url) {
    return erpOpsPageFallback(request, url).then(function (opsHit) {
        if (opsHit) {
            return opsHit;
        }
        return matchAnyCachedAdminPage(request, url);
    }).catch(function () {
        return null;
    });
}

/**
 * @param {Response} response
 * @param {{softOnly?: boolean}} [opts] softOnly=true → do not stamp X-Rateb-Offline (soft-online SWR)
 * @returns {Response|Promise<Response|null>|null}
 */
function withSoftOfflineCacheHeader(response, opts) {
    if (!response) {
        return response;
    }
    opts = opts || {};
    try {
        var headers = new Headers(response.headers || {});
        headers.set('X-Rateb-Soft-Offline-Nav', '1');
        if (!opts.softOnly) {
            headers.set('X-Rateb-Offline', headers.get('X-Rateb-Offline') || '1');
        }
        // Sync stamp when body is readable — returning Promise here black-screened offline
        // (finish() resolved a Promise-as-Response and blocked the inline-shell fallback).
        if (response.body) {
            return new Response(response.body, {
                status: response.status || 200,
                statusText: response.statusText || 'OK',
                headers: headers
            });
        }
        return response.clone().arrayBuffer().then(function (buf) {
            return new Response(buf, {
                status: response.status || 200,
                statusText: response.statusText || 'OK',
                headers: headers
            });
        }).catch(function () {
            return response;
        });
    } catch (e) {
        return response;
    }
}

/** Strip ephemeral offline toast so it cannot poison Cache API HTML (false toast while online). */
function scrubEphemeralOfflineNotes(html) {
    var body = String(html || '');
    if (body.indexOf('أوفلاين:') === -1 && body.indexOf('data-rateb-ephemeral-offline-note') === -1) {
        return body;
    }
    try {
        body = body.replace(/<div[^>]*data-rateb-ephemeral-offline-note[^>]*>[\s\S]*?<\/div>/gi, '');
        body = body.replace(
            /<div[^>]*style=["'][^"']*z-index:\s*99998[^"']*["'][^>]*>[\s\S]*?أوفلاين:[\s\S]*?<\/div>/gi,
            ''
        );
    } catch (eScrub) { /* ignore */ }
    return body;
}

/**
 * Shared ops-page HTML gate for put + serve. Reject empty/poisoned documents.
 * @param {string} pageUrl
 * @param {string} html
 * @returns {boolean}
 */
/**
 * Old module_not_in_plan bounce cached «تعديل الشركات» HTML under ops URLs.
 * First soft-nav click painted that poison; second click got the live page.
 */
function isCompanyEditPoisonHtml(pageUrl, html) {
    var path = '';
    try {
        path = new URL(String(pageUrl || ''), self.location.origin).pathname;
    } catch (ePath) {
        path = String(pageUrl || '');
    }
    if (/\/admin\/companies\/\d+(?:\/edit)?\/?$/i.test(path)) {
        return false;
    }
    var body = String(html || '');
    var looksLikeCompanyEdit = /\/admin\/companies\/\d+\/edit/i.test(body)
        && /(?:تعديل الشركات|Edit compan|name=["']max_users["']|name=["']storage_limit_mb["']|package_id)/i.test(body);
    if (!looksLikeCompanyEdit) {
        return false;
    }
    // Flash from package gate + company form = classic poison.
    if (/غير مشمولة في باقتك|module_not_in_plan|module_not_allowed/i.test(body)) {
        return true;
    }
    // Company SaaS form fields while navigating a different Admin/ops module.
    if (/\/admin\/(?:ops\/)?(?!companies(?:\/|$))/i.test(path)
        && /(?:max_users|storage_limit_mb)/i.test(body)) {
        return true;
    }
    return false;
}

function isValidErpOpsHtmlBody(pageUrl, html) {
    var body = String(html || '');
    if (body.trim() === '') {
        return false;
    }
    // Explicit empty browser document (cache poison / consumed stream).
    if (/^<!DOCTYPE\s+html>\s*<html[^>]*>\s*<head[^>]*>\s*<\/head>\s*<body[^>]*>\s*<\/body>\s*<\/html>\s*$/i.test(body.trim())
        || /^<html[^>]*>\s*<head[^>]*>\s*<\/head>\s*<body[^>]*>\s*<\/body>\s*<\/html>\s*$/i.test(body.trim())) {
        return false;
    }
    if (isCompanyEditPoisonHtml(pageUrl, body)) {
        return false;
    }
    if (/data-rateb-offline-stub/i.test(body)) {
        return false;
    }
    var hasShell = /rateb-sidebar|__RATEB_ERP_SHELL|rateb-main|data-rateb-app|data-pos-register|rateb-pos-register-config/i.test(body);
    // Never treat the lean offline menu (erpInlineShell) as a real Admin snapshot.
    if (/X-Rateb-Inline-Shell|data-rateb-inline-shell|<title>\s*RATEB ERP\s*[—\-]\s*Offline\s*<\/title>|وضع عدم الاتصال/i.test(body.slice(0, 2500))
        && !/rateb-sidebar|__RATEB_ERP_SHELL/i.test(body)) {
        return false;
    }
    var isPosReg = /\/(?:admin\/ops\/)?pos(\/register)?$/i.test(pageUrl)
        && body.length >= 2500
        && /data-pos-register(?:\s|=|>)/i.test(body)
        && !/data-pos-biometric-gate/i.test(body);
    // Lean admin shells (e.g. companies) often land ~8–20KB — do not reject as thin stubs.
    if (body.length < 20000 && !isPosReg) {
        if (!(hasShell && body.length >= 8000)) {
            return false;
        }
    }
    if (/data-rateb-uncached-page|الصفحة غير محفوظة|<title>\s*POS Offline\s*<\/title>|data-pos-biometric-gate/i.test(body.slice(0, 4000))) {
        return false;
    }
    if (/data-rateb-login|id=["']login-form["']/i.test(body.slice(0, 4000))) {
        return false;
    }
    if (/\/(?:admin\/ops\/)?pos(\/register)?$/i.test(pageUrl)
        && !/data-pos-register(?:\s|=|>)/i.test(body)) {
        return false;
    }
    if (!hasShell) {
        return false;
    }
    return true;
}

/** @param {string} pageUrl */
function deletePoisonedErpOpsCacheEntries(pageUrl) {
    var keys = [];
    if (pageUrl) {
        keys.push(String(pageUrl));
    }
    try {
        var u = new URL(String(pageUrl || ''));
        keys.push(u.origin + u.pathname);
        if (u.search) {
            keys.push(u.origin + u.pathname + u.search);
        }
        keys.push(u.origin + u.pathname + '/');
    } catch (eKey) { /* ignore */ }
    return caches.open(ERP_OPS_PAGE_CACHE).then(function (cache) {
        return Promise.all(keys.map(function (key) {
            return cache.delete(key).catch(function () { return false; });
        }));
    }).catch(function () {
        return null;
    });
}

/**
 * PERF-P1 — Put validated ERP HTML into ops-page cache (shared by warm + SWR + prefetch).
 * @param {Cache} opsCache
 * @param {string} pageUrl
 * @param {Response} res
 */
function putErpOpsHtmlResponse(opsCache, pageUrl, res) {
    // Phase OH — never put placeholders / login / thin stubs into ops page cache.
    return res.clone().text().then(function (html) {
        var body = scrubEphemeralOfflineNotes(String(html || ''));
        if (!isValidErpOpsHtmlBody(pageUrl, body)) {
            return null;
        }
        // Keep puts lean (2–3 keys) — many aliases locked Cache API and black-screened F5.
        var putKeys = [pageUrl];
        try {
            var u = new URL(pageUrl);
            var bare = u.origin + u.pathname.replace(/\/+$/, '');
            if (bare && putKeys.indexOf(bare) === -1) {
                putKeys.push(bare);
            }
            if (bare && putKeys.indexOf(bare + '/') === -1) {
                putKeys.push(bare + '/');
            }
        } catch (e5) { /* ignore */ }
        var headers = new Headers({ 'Content-Type': 'text/html; charset=utf-8' });
        try {
            res.headers.forEach(function (v, k) { headers.set(k, v); });
        } catch (eH) { /* ignore */ }
        headers.set('X-Rateb-Ops-Page', '1');
        headers.set('X-Rateb-Asset-Build', SW_BUILD_ID);
        var materialize = function () {
            return new Response(body, { status: 200, statusText: 'OK', headers: new Headers(headers) });
        };
        return Promise.all(putKeys.map(function (key) {
            return opsCache.put(key, materialize()).catch(function () { return null; });
        }));
    }).catch(function () {
        return null;
    });
}

/**
 * Instant ops-page match (online + offline navigate).
 * Few explicit keys + ignoreSearch — never cache.keys() / old-cache walks.
 */
function matchSoftOnlineExactCache(request, url) {
    var keys = [];
    try {
        if (request && request.url) {
            keys.push(request.url);
        }
    } catch (e0) { /* ignore */ }
    try {
        if (url) {
            keys.push(url.origin + url.pathname);
            if (url.search) {
                keys.push(url.origin + url.pathname + url.search);
            }
            var bare = String(url.pathname || '').replace(/\/+$/, '');
            if (bare) {
                keys.push(url.origin + bare);
                keys.push(url.origin + bare + '/');
                if (url.search) {
                    keys.push(url.origin + bare + url.search);
                }
            }
            var p = String(url.pathname || '');
            if (/\/admin\/ops\//i.test(p)) {
                var p2 = p.replace(/\/admin\/ops\//i, '/admin/');
                keys.push(url.origin + p2, url.origin + p2.replace(/\/+$/, ''));
                if (url.search) {
                    keys.push(url.origin + p2 + url.search);
                }
            } else if (/\/admin\/(?!ops\/)/i.test(p) && !/(^|\/)admin$/i.test(p.replace(/\/+$/, ''))) {
                var p3 = p.replace(/\/admin\//i, '/admin/ops/');
                keys.push(url.origin + p3, url.origin + p3.replace(/\/+$/, ''));
                if (url.search) {
                    keys.push(url.origin + p3 + url.search);
                }
            }
        }
    } catch (e1) { /* ignore */ }
    var uniq = [];
    keys.forEach(function (key) {
        if (key && uniq.indexOf(key) === -1) {
            uniq.push(key);
        }
    });
    // Parallel matches — sequential chains stalled under Cache.put contention (black offline).
    return caches.open(ERP_OPS_PAGE_CACHE).then(function (cache) {
        return Promise.all(uniq.map(function (key) {
            return cache.match(key).catch(function () { return null; });
        })).then(function (hits) {
            var i;
            for (i = 0; i < hits.length; i++) {
                if (hits[i]) {
                    return hits[i];
                }
            }
            if (!url || !url.pathname) {
                return null;
            }
            return cache.match(url.origin + url.pathname, { ignoreSearch: true });
        });
    }).catch(function () {
        return null;
    });
}

/** Prefetch a single admin URL into ops-page cache (hover / idle). */
function prefetchErpOpsUrl(href) {
    if (!href || isCloudBrowserOffline()) {
        return Promise.resolve(false);
    }
    var pageUrl = String(href);
    return fetch(pageUrl, {
        credentials: 'same-origin',
        cache: 'no-cache',
        redirect: 'follow',
        headers: { Accept: 'text/html', 'X-Rateb-Shell-Warm': '1', 'X-Rateb-Prefetch': '1' }
    }).then(function (res) {
        if (!res || !res.ok || res.status !== 200) {
            return false;
        }
        try {
            var finalPath = new URL(res.url).pathname || '';
            if (/\/(login|logout|password)\b/i.test(finalPath)) {
                return false;
            }
        } catch (eFin) { /* ignore */ }
        return caches.open(ERP_OPS_PAGE_CACHE).then(function (opsCache) {
            return putErpOpsHtmlResponse(opsCache, pageUrl, res).then(function (ok) {
                return !!ok;
            });
        });
    }).catch(function () {
        return false;
    });
}

/**
 * Platform admin tools that must hit the live ERP when the tab is online.
 * Never substitute the «الصفحة غير محفوظة أوفلاين» shell over a real network
 * response (404/302/500) — that UI is only for true offline cache misses.
 */
function isOnlineOnlyPlatformAdminPath(pathname) {
    var p = String(pathname || '').replace(/\/+$/, '');
    return /\/admin\/mobile-apps(?:\/|$)/i.test(p)
        || /\/admin\/hr-mobile(?:\/|$)/i.test(p)
        || /\/admin\/settings(?:\/|$)/i.test(p)
        || /\/admin\/company-permissions\/\d+$/i.test(p);
}

/** SaaS entitlements POST — must reach PHP when tab is online (never fake-queue). */
function isOnlineOnlyAdminPostPath(pathname) {
    var p = String(pathname || '').replace(/\/+$/, '');
    return /\/admin\/company-permissions\/\d+$/i.test(p)
        || /\/admin\/companies\/\d+$/i.test(p);
}

/**
 * Like fetchNavigateNetwork, but when online returns the HTTP response even if
 * !ok (so Chrome paints login/404/ERP error instead of a fake offline page).
 */
function fetchNavigateNetworkPassthrough(request, timeoutMs) {
    if (isLocalApplianceOrigin()) {
        return fetch(navigateFetchInput(request)).then(asNonRedirectedResponse).then(function (res) {
            return res || Promise.reject(new Error('empty-response'));
        });
    }
    // Document navigations must not die on soft-latch (Wi‑Fi up, badge says متصل).
    // Soft latch only skips hanging asset waits — never block /admin HTML while online.
    if (isHardBrowserOffline()) {
        return Promise.reject(new Error('offline'));
    }
    var ms = typeof timeoutMs === 'number' ? timeoutMs : 8000;
    var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
    var timer = setTimeout(function () {
        markCloudNetworkDegraded('navigate-passthrough-timeout');
        if (ctrl) {
            try { ctrl.abort(); } catch (eAb) { /* ignore */ }
        }
    }, ms);
    var network = fetch(navigateFetchInput(request), {
        signal: ctrl ? ctrl.signal : undefined
    }).then(asNonRedirectedResponse).then(function (response) {
        if (!response) {
            return Promise.reject(new Error('empty-response'));
        }
        clearCloudNetworkDegraded();
        return response;
    });
    var timed = new Promise(function (_resolve, reject) {
        setTimeout(function () {
            reject(new Error('navigate-timeout'));
        }, ms);
    });
    return Promise.race([network, timed]).then(function (res) {
        clearTimeout(timer);
        return res;
    }, function (err) {
        clearTimeout(timer);
        return Promise.reject(err);
    });
}

/**
 * Cloud soft-online ERP admin navigations — PERF-P1 stale-while-revalidate.
 * Cached HTML paints immediately; network refreshes ops cache in background.
 * Poisoned/empty cache hits are deleted and never re-served.
 * @param {Request} request
 * @param {URL} url
 * @param {FetchEvent} [event]
 */
function navigateErpCloudWithCacheSafety(request, url, event) {
    var pageUrl = request.url || (url && url.href) || '';

    // Online-only platform management: always prefer live ERP (no fake offline shell).
    if (!isCloudBrowserOffline() && isOnlineOnlyPlatformAdminPath(url && url.pathname)) {
        return fetchNavigateNetworkPassthrough(request, 8000).then(function (response) {
            if (response && response.ok) {
                var store = caches.open(ERP_OPS_PAGE_CACHE).then(function (opsCache) {
                    return putErpOpsHtmlResponse(opsCache, pageUrl, response.clone());
                }).catch(function () { return null; });
                if (event && typeof event.waitUntil === 'function') {
                    event.waitUntil(store);
                }
            }
            return response;
        }).catch(function () {
            return matchSoftOnlineExactCache(request, url).then(function (hit) {
                if (hit) {
                    return withSoftOfflineCacheHeader(hit.clone(), { softOnly: true });
                }
                // Still online but network failed — retry once with longer wait, else real error page.
                return fetchNavigateNetworkPassthrough(request, 12000).catch(function () {
                    return new Response(
                        '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8">'
                        + '<meta name="viewport" content="width=device-width,initial-scale=1">'
                        + '<title>RATEB ERP</title></head><body style="font-family:system-ui;background:#0f1117;color:#e8eaed;padding:2rem;text-align:center">'
                        + '<h1>تعذّر الاتصال بالخادم</h1>'
                        + '<p>أنت متصل، لكن الصفحة لم تُحمَّل. حدّث الصفحة أو عد لاحقاً.</p>'
                        + '<p><a style="color:#8ab4ff" href="javascript:location.reload()">تحديث</a>'
                        + ' · <a style="color:#8ab4ff" href="' + String(new URL('admin/', self.registration.scope).href).replace(/"/g, '') + '">لوحة التحكم</a></p>'
                        + '</body></html>',
                        {
                            status: 503,
                            headers: {
                                'Content-Type': 'text/html; charset=utf-8',
                                'Cache-Control': 'no-store',
                                'X-Rateb-Online-Error': '1'
                            }
                        }
                    );
                });
            });
        });
    }

    function serveCachedFast(hit, offlineMode) {
        if (!hit) {
            return null;
        }
        // Put path already validated — stream clone; never .text() on F5 critical path.
        // Offline stamps X-Rateb-Offline; soft-online does not (avoids false offline UI).
        return withSoftOfflineCacheHeader(hit.clone(), { softOnly: !offlineMode });
    }

    function storeLive(response) {
        if (!response || !response.ok) {
            return null;
        }
        // Never Cache.put while offline/degraded — locks Cache API and black-screens the next F5.
        if (isCloudBrowserOffline()) {
            return response;
        }
        // Clone NOW — deferring clone() until after respondWith consumes the body left
        // the ops cache empty, so every «تحديث» waited on cold PHP (black tab).
        var toCache = null;
        try {
            toCache = response.clone();
        } catch (eClone) {
            toCache = null;
        }
        if (!toCache) {
            return response;
        }
        var store = new Promise(function (resolve) {
            setTimeout(function () {
                caches.open(ERP_OPS_PAGE_CACHE).then(function (opsCache) {
                    return putErpOpsHtmlResponse(opsCache, pageUrl, toCache);
                }).catch(function () { return null; }).then(resolve);
            }, 400);
        });
        if (event && typeof event.waitUntil === 'function') {
            event.waitUntil(store);
        }
        return response;
    }

    // True offline / soft-offline latch: cache-only with hard ceiling (never minutes of black).
    // Bare /admin MUST NEVER show «غير محفوظة» — that is the home URL; use dashboard snapshot
    // or inline offline nav shell instead (280ms used to race ahead of a valid cache hit).
    if (isCloudBrowserOffline()) {
        return new Promise(function (resolve) {
            var settled = false;
            function finish(res) {
                if (!res) {
                    return false;
                }
                // Always flatten — serveCachedFast may still return a Promise on rare paths.
                // Resolving a nested Promise made Chrome fail respondWith → pure black /admin.
                Promise.resolve(res).then(function (real) {
                    if (settled || !real) {
                        return;
                    }
                    settled = true;
                    resolve(real);
                }).catch(function () { /* ignore */ });
                return true;
            }
            function isBareAdminPath() {
                try {
                    return /\/admin$/i.test(String((url && url.pathname) || '').replace(/\/+$/, ''));
                } catch (eBa) {
                    return false;
                }
            }
            function adminHomeFallback() {
                return matchCachedAdminDashboard(url).catch(function () {
                    return null;
                }).then(function (dash) {
                    if (dash) {
                        return Promise.resolve(serveCachedFast(dash, true)).then(function (served) {
                            if (served) {
                                return served;
                            }
                            try {
                                return erpInlineShellResponse();
                            } catch (eShell) {
                                return uncachedAdminBrowseResponse(url);
                            }
                        });
                    }
                    try {
                        return erpInlineShellResponse();
                    } catch (eShell2) {
                        return uncachedAdminBrowseResponse(url);
                    }
                });
            }
            function pageMissFallback() {
                if (isBareAdminPath()) {
                    return adminHomeFallback();
                }
                return Promise.resolve(uncachedAdminBrowseResponse(url));
            }
            matchSoftOnlineExactCache(request, url).then(function (hit) {
                if (settled) {
                    return;
                }
                if (hit) {
                    return Promise.resolve(serveCachedFast(hit, true)).then(function (served) {
                        if (served && finish(served)) {
                            return;
                        }
                        return pageMissFallback().then(function (fb) {
                            finish(fb);
                        });
                    });
                }
                return pageMissFallback().then(function (fb) {
                    finish(fb);
                });
            }).catch(function () {
                if (settled) {
                    return;
                }
                pageMissFallback().then(function (fb) {
                    finish(fb);
                });
            });
            setTimeout(function () {
                if (settled) {
                    return;
                }
                if (isBareAdminPath()) {
                    adminHomeFallback().then(function (fb) {
                        finish(fb);
                    });
                    setTimeout(function () {
                        if (settled) {
                            return;
                        }
                        try {
                            finish(erpInlineShellResponse());
                        } catch (eShell3) {
                            finish(uncachedAdminBrowseResponse(url));
                        }
                    }, 220);
                    return;
                }
                finish(uncachedAdminBrowseResponse(url));
            }, 500);
        });
    }

    // Soft-online F5: cache-first when hit; on miss wait for live PHP (no 600ms abort).
    // Bare /admin uses the same passthrough pattern as online-only platform pages.
    var bareAdminPath = false;
    try {
        bareAdminPath = /\/admin$/i.test(String((url && url.pathname) || '').replace(/\/+$/, ''));
    } catch (eBare) { /* ignore */ }

    if (!isCloudBrowserOffline() && bareAdminPath) {
        // Prefer any cached dashboard snapshot immediately; refresh network in background.
        return matchSoftOnlineExactCache(request, url).catch(function () {
            return null;
        }).then(function (hit) {
            if (hit) {
                var cached = serveCachedFast(hit, false);
                if (event && typeof event.waitUntil === 'function') {
                    event.waitUntil(
                        fetchNavigateNetworkPassthrough(request, 8000)
                            .then(storeLive)
                            .catch(function () { return null; })
                    );
                }
                return cached;
            }
            return fetchNavigateNetworkPassthrough(request, 8000).then(function (response) {
                storeLive(response);
                return response;
            }).catch(function () {
                return matchCachedAdminDashboard(url).then(function (dash) {
                    if (dash) {
                        return serveCachedFast(dash, false) || dash;
                    }
                    return new Response(
                        '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8">'
                        + '<meta name="viewport" content="width=device-width,initial-scale=1">'
                        + '<meta name="color-scheme" content="dark">'
                        + '<title>لوحة التحكم</title></head>'
                        + '<body style="margin:0;font-family:Tajawal,system-ui,sans-serif;background:#0f1117;color:#e8eaed;'
                        + 'display:flex;min-height:100vh;align-items:center;justify-content:center;text-align:center;padding:2rem">'
                        + '<div><h1 style="font-size:1.15rem;margin:0 0 .75rem">جاري تحميل لوحة التحكم…</h1>'
                        + '<p style="opacity:.8;margin:0 0 1rem">الاتصال بطيء — أعد المحاولة.</p>'
                        + '<p><a style="color:#8ab4ff" href="javascript:location.reload()">تحديث</a></p></div>'
                        + '</body></html>',
                        {
                            status: 503,
                            headers: {
                                'Content-Type': 'text/html; charset=utf-8',
                                'Cache-Control': 'no-store',
                                'X-Rateb-Online-Error': '1'
                            }
                        }
                    );
                });
            });
        });
    }

    var networkMs = 4500;
    var networkP = fetchNavigateNetwork(request, networkMs).then(storeLive).catch(function () {
        return null;
    });
    var cacheP = matchSoftOnlineExactCache(request, url).catch(function () {
        return null;
    });

    return new Promise(function (resolve) {
        var settled = false;
        function finish(res) {
            if (!res) {
                return false;
            }
            Promise.resolve(res).then(function (real) {
                if (settled || !real) {
                    return;
                }
                settled = true;
                resolve(real);
            }).catch(function () { /* ignore */ });
            return true;
        }

        function afterCacheMiss() {
            if (settled) {
                return;
            }
            networkP.then(function (response) {
                if (settled) {
                    return;
                }
                if (response && finish(response)) {
                    return;
                }
                Promise.race([
                    neverFailNavigate(request, url),
                    new Promise(function (res) {
                        setTimeout(function () {
                            try {
                                res(erpInlineShellResponse());
                            } catch (eShell) {
                                res(uncachedAdminBrowseResponse(url));
                            }
                        }, 400);
                    })
                ]).then(function (fallback) {
                    finish(fallback);
                });
            });
        }

        cacheP.then(function (hit) {
            if (!hit) {
                afterCacheMiss();
                return;
            }
            Promise.resolve(serveCachedFast(hit, false)).then(function (served) {
                if (served && finish(served)) {
                    if (event && typeof event.waitUntil === 'function') {
                        event.waitUntil(networkP.then(function () { return null; }));
                    }
                    return;
                }
                afterCacheMiss();
            });
        });

        setTimeout(function () {
            if (settled) {
                return;
            }
            cacheP.then(function (hit) {
                if (settled) {
                    return;
                }
                Promise.resolve(serveCachedFast(hit, false)).then(function (served) {
                    if (settled) {
                        return;
                    }
                    if (served) {
                        finish(served);
                        return;
                    }
                    networkP.then(function (response) {
                        if (settled) {
                            return;
                        }
                        if (response && finish(response)) {
                            return;
                        }
                        try {
                            finish(erpInlineShellResponse());
                        } catch (eShell2) {
                            finish(uncachedAdminBrowseResponse(url));
                        }
                    });
                });
            });
        }, 1200);
    });
}

/**
 * Admin dashboard helper kept for callers — delegates to SWR (logout purge keeps session safe).
 */
function navigateAdminDashboardNetworkFirst(request, url, event) {
    return navigateErpCloudWithCacheSafety(request, url, event);
}

/**
 * Soft-nav (X-Rateb-Nav-Swap) / prefetch HTML.
 * Online: network-first (cache-first painted company-edit / offline stubs while Connected).
 * Hard offline: validated cache only. Never serve offline-stub HTML as a "page".
 */
function softNavAdminHtml(request, url, event) {
    var pageUrl = request.url || (url && url.href) || '';

    function storeLive(response) {
        if (!response || !response.ok) {
            return response;
        }
        var store = new Promise(function (resolve) {
            setTimeout(function () {
                caches.open(ERP_OPS_PAGE_CACHE).then(function (opsCache) {
                    return putErpOpsHtmlResponse(opsCache, pageUrl, response.clone());
                }).catch(function () { return null; }).then(resolve);
            }, 400);
        });
        if (event && typeof event.waitUntil === 'function') {
            event.waitUntil(store);
        }
        return response;
    }

    function validatedCacheHit() {
        return matchSoftOnlineExactCache(request, url).then(function (hit) {
            if (!hit) {
                var bare = String((url && url.pathname) || '').replace(/\/+$/, '');
                if (/\/admin$/i.test(bare)) {
                    return matchCachedAdminDashboard(url);
                }
                return null;
            }
            return hit;
        }).then(function (hit) {
            if (!hit) {
                return null;
            }
            return hit.clone().text().then(function (body) {
                var html = String(body || '');
                if (/data-rateb-offline-stub/i.test(html) || !isValidErpOpsHtmlBody(pageUrl, html)) {
                    try {
                        deletePoisonedErpOpsCacheEntries(pageUrl);
                    } catch (eDel) { /* ignore */ }
                    return null;
                }
                return withSoftOfflineCacheHeader(hit.clone(), { softOnly: true });
            }).catch(function () {
                return null;
            });
        }).catch(function () {
            return null;
        });
    }

    if (isHardBrowserOffline()) {
        return validatedCacheHit().then(function (hit) {
            if (hit) {
                return hit;
            }
            return Promise.reject(new Error('soft-nav-offline-miss'));
        });
    }

    // Soft-latch still prefers live Admin HTML while the UI can show متصل.
    var networkMs = isCloudBrowserOffline() ? 4000 : 10000;
    return fetchNavigateNetwork(request, networkMs).then(storeLive).then(function (response) {
        if (response && response.ok) {
            return response.clone().text().then(function (body) {
                if (/data-rateb-offline-stub/i.test(String(body || ''))) {
                    return null;
                }
                return response;
            }).catch(function () {
                return response;
            });
        }
        // Pass through real HTTP errors (do not rewrite to offline card).
        if (response) {
            return response;
        }
        return null;
    }).catch(function () {
        return null;
    }).then(function (live) {
        if (live) {
            return live;
        }
        return validatedCacheHit().then(function (hit) {
            if (hit) {
                return hit;
            }
            return Promise.reject(new Error('soft-nav-miss'));
        });
    });
}

/** Certified POS shell only — never treat bio-required placeholder as a snapshot hit. */
function matchCertifiedPosShellSnapshot(request) {
    return serveCertifiedShellOrBioRequired(request).then(function (res) {
        try {
            if (res && res.headers && String(res.headers.get('X-Rateb-Pos-Cert') || '') === '1') {
                return withSoftOfflineCacheHeader(res);
            }
        } catch (eHdr) { /* ignore */ }
        return null;
    }).catch(function () {
        return null;
    });
}

/**
 * Cloud soft-online POS navigations.
 * Network-first so HTML meta/config CSRF matches the live session (cache-first caused 419).
 * Certified shell / shellFallback only when the network race fails.
 */
function navigatePosCloudWithCacheSafety(request, url) {
    var shellReq = request;
    if (isBiometricGatePath(url.pathname)) {
        shellReq = shellLookupRequest(registerPathFromBiometric(url).href, request);
    }
    // Admin CRUD pages never rewrite to register / cert shell (handled by navigatePosAdminCrudDocument).
    function fromCacheOrFallback() {
        return matchCertifiedPosShellSnapshot(shellReq).then(function (hit) {
            if (hit) {
                return hit;
            }
            if (!isHardBrowserOffline()) {
                return fetchPosLiveOrShowRetry(request);
            }
            return shellFallback(shellReq);
        });
    }
    // Network-first with live-session budget (CSRF-safe); cache only if network is slow/fails.
    return fetchNavigateNetworkPassthrough(request, 8000).then(function (response) {
        if (response && response.ok) {
            if (!isBiometricGatePath(url.pathname)) {
                try {
                    putShell(request, response.clone()).catch(function () { return null; });
                } catch (ePin) { /* ignore */ }
            }
            return response;
        }
        if (response && !response.ok && !isHardBrowserOffline()) {
            return posHandleLiveNetworkResponse(response, request);
        }
        return fromCacheOrFallback();
    }).catch(function () {
        return fromCacheOrFallback();
    });
}

function isOfflinePostDenyPath(pathname) {
    var p = String(pathname || '').replace(/\/+$/, '');
    // Only hard-online: permanent wipe / file export / period close / GL journal post.
    // Approve, delete, pay, decide, suspend queue offline and sync later.
    // Platform SaaS entitlements cannot queue offline (agency sync + nav gate).
    return /\/(wipe|export|pdf|excel|csv)(\/|$)/i.test(p)
        || /\/(close[-_]?period|transfer-funds|void-payment|gl[-_]?post)(\/|$)/i.test(p)
        || /\/journal-entries\/\d+\/(post|void)(\/|$)/i.test(p)
        || /\/admin\/company-permissions\/\d+$/i.test(p);
}

function wantsJsonPostResponse(request) {
    try {
        if (String(request.headers.get('X-Requested-With') || '') === 'XMLHttpRequest') {
            return true;
        }
        var accept = String(request.headers.get('Accept') || '');
        return accept.indexOf('application/json') !== -1;
    } catch (e) {
        return false;
    }
}

/**
 * Where to return after offline Save. Never bare /admin (that felt like “went to dashboard”).
 */
function deriveOfflinePostReturnUrl(request, url, referer) {
    try {
        if (referer) {
            var ru = new URL(referer, url.origin);
            var rp = String(ru.pathname || '').replace(/\/+$/, '');
            if (/\/admin(\/|$)/i.test(rp)
                && !/(^|\/)admin$/i.test(rp)
                && !/(^|\/)admin\/ops$/i.test(rp)
                && !/(^|\/)admin\/dashboard$/i.test(rp)) {
                return ru.origin + ru.pathname + (ru.search || '');
            }
        }
    } catch (eR) { /* ignore */ }
    var p = String(url.pathname || '').replace(/\/+$/, '');
    var origin = url.origin;
    if (/\/\d+$/i.test(p) && !/\/(delete|destroy|suspend|wipe|approve|decide|pay)$/i.test(p)) {
        return origin + p + '/edit';
    }
    if (/\/(create|new)$/i.test(p)) {
        return origin + p;
    }
    if (/\/admin(\/|$)/i.test(p) && !/(^|\/)admin$/i.test(p)) {
        return origin + p + '/create';
    }
    try {
        return new URL(String(request.url || url.href), url.origin).href;
    } catch (eU) {
        return origin + p;
    }
}

/**
 * Offline POST: queue form fields (including approve/delete/pay), never Chrome interstitial.
 * Only wipe/export/period-close/GL-post stay hard-blocked.
 */
function handleOfflineAdminPost(request, url) {
    var referer = '';
    try {
        referer = String(request.headers.get('Referer') || '');
    } catch (eR) { /* ignore */ }
    var returnUrl = deriveOfflinePostReturnUrl(request, url, referer);

    if (isOfflinePostDenyPath(url.pathname)) {
        if (wantsJsonPostResponse(request)) {
            return Promise.resolve(new Response(JSON.stringify({
                ok: false,
                offline: true,
                message: 'ترحيل القيود / إغلاق الفترة / المسح النهائي يحتاج اتصال بالإنترنت.'
            }), {
                status: 503,
                headers: {
                    'Content-Type': 'application/json; charset=utf-8',
                    'X-Rateb-Offline': '1'
                }
            }));
        }
        try {
            var denyBack = new URL(returnUrl, url.origin);
            denyBack.searchParams.set('rateb_offline_blocked', '1');
            return Promise.resolve(Response.redirect(denyBack.href, 303));
        } catch (eD) {
            return Promise.resolve(Response.redirect(returnUrl, 303));
        }
    }

    // POS JSON APIs (approval / biometric / register) are handled by IndexedDB clients —
    // never parse as formData (throws) or emit a loud 503 for JSON POST bodies.
    if (wantsJsonPostResponse(request)
        && /\/pos\/api\/(approval|biometric|register|v2)\//i.test(String(url.pathname || ''))) {
        return Promise.resolve(new Response(JSON.stringify({
            ok: false,
            offline: true,
            error: 'offline',
            message: 'يتطلب اتصالًا — أو استخدام المسار المحلي في نقطة البيع.'
        }), {
            status: 200,
            headers: {
                'Content-Type': 'application/json; charset=utf-8',
                'X-Rateb-Offline': '1',
                'Cache-Control': 'no-store'
            }
        }));
    }

    return request.clone().formData().then(function (fd) {
        var fields = {};
        try {
            fd.forEach(function (value, key) {
                if (/^_csrf$/i.test(String(key))) {
                    return;
                }
                try {
                    if (typeof File !== 'undefined' && value instanceof File) {
                        return;
                    }
                } catch (eF) { /* ignore */ }
                var sval = String(value);
                if (Object.prototype.hasOwnProperty.call(fields, key)) {
                    if (!Array.isArray(fields[key])) {
                        fields[key] = [fields[key]];
                    }
                    fields[key].push(sval);
                } else {
                    fields[key] = sval;
                }
            });
        } catch (eFields) { /* ignore */ }

        var entry = {
            id: 'sw-' + Date.now() + '-' + Math.floor(Math.random() * 1e6),
            url: url.href,
            path: url.pathname,
            fields: fields,
            return_url: returnUrl,
            created_at: Date.now(),
            via: 'service-worker'
        };

            return caches.open(ERP_COEXIST_CACHE).then(function (cache) {
            var key;
            try {
                key = new URL(ERP_DEFERRED_POSTS_PREFIX + entry.id, self.registration.scope).href;
            } catch (eKey) {
                key = url.origin + '/rateb-erp/public/' + ERP_DEFERRED_POSTS_PREFIX + entry.id;
            }
            return cache.put(key, new Response(JSON.stringify(entry), {
                status: 200,
                headers: { 'Content-Type': 'application/json; charset=utf-8' }
            })).then(function () {
                return self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clients) {
                    (clients || []).forEach(function (client) {
                        try {
                            client.postMessage({ type: 'RATEB_DEFERRED_POST', entry: entry });
                        } catch (eMsg) { /* ignore */ }
                    });
                });
            });
        }).catch(function () { /* ignore store errors */ }).then(function () {
            if (wantsJsonPostResponse(request)) {
                return new Response(JSON.stringify({
                    ok: true,
                    offline: true,
                    queued: true,
                    message: 'تم الحفظ بنجاح'
                }), {
                    status: 200,
                    headers: {
                        'Content-Type': 'application/json; charset=utf-8',
                        'X-Rateb-Offline': '1'
                    }
                });
            }
            // Serve the form page from cache (no redirect to dashboard, no second round-trip).
            var backKeys = [returnUrl];
            try {
                var bu = new URL(returnUrl, url.origin);
                backKeys.push(bu.origin + bu.pathname);
                backKeys.push(bu.origin + bu.pathname.replace(/\/+$/, ''));
                backKeys.push(bu.origin + bu.pathname.replace(/\/+$/, '') + '/');
            } catch (eBk) { /* ignore */ }
            return Promise.all([
                caches.open(ERP_OPS_PAGE_CACHE),
                caches.open(ERP_COEXIST_CACHE)
            ]).then(function (pair) {
                var chain = Promise.resolve(null);
                [pair[0], pair[1]].forEach(function (cache) {
                    if (!cache) {
                        return;
                    }
                    backKeys.forEach(function (k) {
                        chain = chain.then(function (hit) {
                            if (hit) {
                                return hit;
                            }
                            return cache.match(k).then(function (m) {
                                return m || cache.match(k, { ignoreSearch: true }).catch(function () { return null; });
                            });
                        });
                    });
                });
                return chain;
            }).then(function (hit) {
                if (hit) {
                    return hit.text().then(function (html) {
                        var out = String(html || '');
                        if (out && out.indexOf('rateb-offline-saved-flash') === -1) {
                            out = out.replace(
                                /<body([^>]*)>/i,
                                '<body$1><div class="alert alert-success rateb-flash rateb-offline-saved-flash" role="alert">تم الحفظ بنجاح — بانتظار المزامنة</div>'
                            );
                        }
                        return new Response(out || html, {
                            status: 200,
                            headers: {
                                'Content-Type': 'text/html; charset=utf-8',
                                'X-Rateb-Offline': '1',
                                'X-Rateb-Offline-Saved': '1'
                            }
                        });
                    });
                }
                try {
                    var back = new URL(returnUrl, url.origin);
                    back.searchParams.set('rateb_offline_saved', '1');
                    return Response.redirect(back.href, 303);
                } catch (eBack) {
                    return Response.redirect(returnUrl, 303);
                }
            });
        });
    }).catch(function () {
        if (wantsJsonPostResponse(request)) {
            return new Response(JSON.stringify({
                ok: false,
                offline: true,
                message: 'تعذر حفظ النموذج أوفلاين. أعد المحاولة وأنت متصل.'
            }), {
                status: 503,
                headers: { 'Content-Type': 'application/json; charset=utf-8', 'X-Rateb-Offline': '1' }
            });
        }
        try {
            var back2 = new URL(returnUrl, url.origin);
            back2.searchParams.set('rateb_offline_saved', '1');
            return Response.redirect(back2.href, 303);
        } catch (e2) {
            return Response.redirect(returnUrl, 303);
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
        + '<p><a href="javascript:history.back()">رجوع</a>'
        + ' · <a href="' + String(adminHref).replace(/"/g, '') + '">العودة للوحة التحكم</a></p>'
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
        keys.push(new URL('admin/executive-dashboard', self.registration.scope).href);
        keys.push(new URL('admin/companies', self.registration.scope).href);
    } catch (e2) { /* ignore */ }
    var uniq = [];
    keys.forEach(function (key) {
        if (key && uniq.indexOf(key) === -1) {
            uniq.push(key);
        }
    });
    return caches.open(ERP_OPS_PAGE_CACHE).then(function (cache) {
        return Promise.all(uniq.map(function (key) {
            return cache.match(key).catch(function () { return null; });
        })).then(function (hits) {
            var i;
            for (i = 0; i < hits.length; i++) {
                if (hits[i]) {
                    return hits[i];
                }
            }
            var primary = uniq[0] || (url ? (url.origin + '/rateb-erp/public/admin') : '');
            if (!primary) {
                return null;
            }
            return cache.match(primary, { ignoreSearch: true }).catch(function () { return null; });
        });
    }).catch(function () {
        return null;
    });
}

/** Purge lean offline menu that was wrongly stored under /admin ops keys (2nd F5 poison). */
function purgeInlineShellFromAdminKeys() {
    var keys = [];
    try {
        keys.push(new URL('admin', self.registration.scope).href);
        keys.push(new URL('admin/', self.registration.scope).href);
    } catch (e0) { /* ignore */ }
    try {
        keys.push(self.location.origin + '/rateb-erp/public/admin');
        keys.push(self.location.origin + '/rateb-erp/public/admin/');
    } catch (e1) { /* ignore */ }
    return caches.open(ERP_OPS_PAGE_CACHE).then(function (cache) {
        return Promise.all(keys.filter(Boolean).map(function (key) {
            return cache.match(key).then(function (hit) {
                if (!hit) {
                    return null;
                }
                try {
                    if (String(hit.headers.get('X-Rateb-Inline-Shell') || '') === '1') {
                        return cache.delete(key);
                    }
                } catch (eH) { /* ignore */ }
                return hit.clone().text().then(function (html) {
                    var body = String(html || '');
                    if (!isValidErpOpsHtmlBody(key, body)
                        || (/وضع عدم الاتصال/i.test(body) && !/rateb-sidebar/i.test(body))) {
                        return cache.delete(key);
                    }
                    return null;
                }).catch(function () { return null; });
            }).catch(function () { return null; });
        }));
    }).catch(function () {
        return null;
    });
}

/** Legacy no-op — never seed thin inline shell into /admin ops keys. */
function seedAdminHomeOfflineFallback() {
    return purgeInlineShellFromAdminKeys();
}

function matchOfflineShellOrInline(request) {
    var key = erpOfflineShellUrl();
    function look(res) {
        return rejectThinOfflineShellHit(res);
    }
    return caches.match(key).then(look).then(function (hit) {
        if (hit) {
            return hit;
        }
        return caches.open(ERP_COEXIST_CACHE).then(function (cache) {
            return cache.match(key).then(look).then(function (cached) {
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
                                return c.match(key).then(look);
                            });
                        });
                    }, Promise.resolve(null)).then(function (found) {
                        // Last resort: live Response only — do not re-poison offline-shell.html key.
                        return found || erpInlineShellResponse();
                    });
                });
            });
        });
    }).catch(function () {
        return erpInlineShellResponse();
    });
}

function warmErpOfflineShell(opts) {
    opts = opts || {};
    var now = Date.now();
    if (!opts.force && shellWarmRunning) {
        return Promise.resolve(null);
    }
    if (!opts.force && LAST_SHELL_WARM_AT > 0 && (now - LAST_SHELL_WARM_AT) < SHELL_WARM_TTL_MS) {
        return Promise.resolve(null);
    }
    if (isCloudBrowserOffline()) {
        return Promise.resolve(null);
    }
    shellWarmRunning = true;
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
        // Phase OA — critical path: bootstrap + storage/auth (not the 387KB monolith).
        base + 'assets/offline/erp-offline-tenant-context.js',
        base + 'assets/offline/offline-bootstrap.js',
        // PERF-P0.1 — monolith fallbacks used by offline-shell.html loadOfflineScript chain.
        base + 'assets/offline/rateb-offline.js',
        base + 'assets/offline/rateb-offline.min.js',
        base + 'assets/offline/rateb-offline.js?v=oid-20260713-lean',
        base + 'assets/offline/modules/offline-storage.js',
        base + 'assets/offline/modules/offline-auth.js',
        base + 'assets/offline/modules/offline-rbac.js',
        base + 'assets/offline/modules/offline-core.js',
        base + 'assets/offline/erp-offline-shell-auth.js',
        base + 'assets/offline/erp-offline-shell-rbac.js',
        base + 'assets/offline/erp-shell-bootstrap.js',
        base + 'assets/offline/ops-page-allowlist.json',
        base + 'assets/css/critical-shell.css',
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
        base + 'assets/js/charts.js',
        base + 'assets/js/rateb-modal.js',
        base + 'assets/js/rateb-confirm.js',
        base + 'assets/js/approvals-oversight.js',
        base + 'assets/js/entity-documents-modal.js',
        base + 'assets/js/table-tools.js',
        base + 'assets/offline/erp-offline-nav-guard.js'
    ];
    // Phase OH — certified ERP module HTML snapshots (complete documents, not lean-only admin/).
    var leanOps = [
        'admin',
        'admin/',
        'admin/companies',
        'admin/agency-updates',
        'admin/company-permissions',
        'admin/oversight/approvals',
        'admin/oversight/companies-approvals',
        'admin/oversight/procurement',
        'admin/oversight/rfq',
        'admin/oversight/inventory',
        'admin/oversight/supplier-evaluations',
        'admin/profile',
        'admin/notifications',
        'admin/ops/notifications',
        'admin/hr',
        'admin/hr/attendance',
        'admin/hr/attendance/bulk',
        'admin/hr/holidays',
        'admin/hr/leaves',
        'admin/hr/leave-types',
        'admin/hr/leaves/balances',
        'admin/hr/employees',
        'admin/hr/departments',
        'admin/hr/job-titles',
        'admin/hr/workplaces',
        'admin/hr/permission-requests',
        'admin/hr/reports',
        'admin/hr/reports/leaves',
        'admin/hr/loans',
        'admin/hr/loan-types',
        'admin/hr/payroll',
        'admin/hr/payroll/components',
        'admin/hr/payroll/structure',
        'admin/hr/documents',
        'admin/hr/requests',
        'admin/hr/fleet',
        'admin/recruitment',
        'admin/crm',
        'admin/projects',
        'admin/approvals',
        'admin/mfg',
        'admin/payroll',
        'admin/qms',
        'admin/bi',
        'admin/ops/branch-dashboard',
        'admin/ops/branch-financial',
        'admin/ops/branch-dashboard/compare',
        'admin/ops/branch-dashboard/reports',
        'admin/ops/branch-transfers',
        'admin/ops/inventory',
        'admin/ops/warehouses',
        'admin/ops/warehouse-transfers',
        'admin/ops/inventory-batches',
        'admin/ops/inventory-audits',
        'admin/ops/inventory-forecast',
        'admin/ops/purchase-requests',
        'admin/ops/purchase-orders',
        'admin/ops/rfq',
        'admin/ops/quotations',
        'admin/ops/suppliers',
        'admin/ops/supplier-comms',
        'admin/ops/supplier-evaluations',
        'admin/ops/supplier-classifications',
        'admin/ops/supplier-kpi',
        'admin/ops/stock-movements',
        'admin/ops/product-categories',
        'admin/ops/journal-entries',
        'admin/ops/access-control',
        'admin/ops/access-control/matrix',
        'admin/ops/roles',
        'admin/ops/permissions',
        'admin/ops/audit-logs',
        'admin/accounting',
        'admin/ops/accounting',
        'admin/ops/accounting/cfo-dashboard',
        'admin/ops/accounting/accounts-receivable',
        'admin/ops/accounting/accounts-payable',
        'admin/ops/accounting/reports',
        'admin/ops/chart-of-accounts',
        'admin/ops/accounting/coa-tree',
        'admin/ops/cash-vouchers',
        'admin/ops/accounting/supplier-payments',
        'admin/ops/fiscal-periods',
        'admin/ops/cost-centers',
        'admin/ops/bank-accounts',
        'admin/ops/accounting/bank-reconciliation',
        'admin/ops/accounting-control',
        'admin/ops/reports/cost-analysis',
        'admin/ops/reports/inventory-valuation',
        'admin/ops/asset-depreciation',
        'admin/ops/pos/register',
        'admin/ops/contracts',
        'admin/ops/assets',
        'admin/ops/contract-renewals',
        'admin/ops/tenders',
        'admin/ops/asset-maintenance',
        'admin/ops/asset-assignments',
        'admin/ops/medical-devices',
        'admin/ops/device-maintenance',
        'admin/ops/device-spare-parts',
        'admin/ops/device-warranty',
        'admin/ops/reports/procurement',
        'admin/ops/reports/kpi',
        'admin/ops/reports/supplier-performance',
        'admin/ops/documents',
        'admin/ops/support-tickets',
        'admin/ops/email-templates',
        'admin/ops/sms-templates',
        'admin/users',
        'admin/branches',
        'admin/customers',
        'admin/cms',
        'admin/cms/pages',
        'admin/cms/page-builder',
        'admin/cms/leads',
        'admin/cms/blog-articles',
        'admin/cms/newsletter',
        'admin/cms/media',
        'admin/cms/seo',
        'admin/cms/faqs',
        'admin/cms/testimonials',
        'admin/cms/theme',
        'admin/cms/about',
        'admin/executive-dashboard',
        'admin/reports',
        'admin/cfo'
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
        return putErpOpsHtmlResponse(opsCache, pageUrl, res);
    }
    // Critical pages must land in cache before offline — await these only.
    var leanOpsCritical = [
        'admin',
        'admin/',
        'admin/companies',
        'admin/agency-updates',
        'admin/ops/notifications',
        'admin/notifications',
        'admin/ops/pos/register',
        'admin/accounting',
        'admin/ops/accounting',
        'admin/hr',
        'admin/hr/holidays',
        'admin/hr/leaves',
        'admin/hr/attendance',
        'admin/hr/employees',
        'admin/ops/inventory',
        'admin/ops/warehouses',
        'admin/ops/warehouse-transfers',
        'admin/ops/purchase-requests',
        'admin/ops/purchase-orders',
        'admin/ops/suppliers',
        'admin/ops/access-control',
        'admin/oversight/approvals',
        'admin/oversight/companies-approvals',
        'admin/ops/stock-movements',
        'admin/ops/branch-dashboard',
        'admin/ops/journal-entries',
        'admin/ops/contracts',
        'admin/ops/assets',
        'admin/cms',
        'admin/cms/newsletter',
        'admin/cms/leads',
        'admin/cms/pages'
    ];
    function warmLeanOpsList(list, gapMs) {
        return caches.open(ERP_OPS_PAGE_CACHE).then(function (opsCache) {
            var idx = 0;
            var gap = typeof gapMs === 'number' ? gapMs : 120;
            function pumpOne() {
                if (idx >= list.length) {
                    return Promise.resolve();
                }
                var rel = list[idx++];
                var pageUrl = base + rel.replace(/^\//, '');
                return fetch(pageUrl, {
                    credentials: 'same-origin',
                    cache: 'no-cache',
                    redirect: 'follow',
                    headers: { Accept: 'text/html', 'X-Rateb-Shell-Warm': '1' }
                }).then(function (res) {
                    if (!res || !res.ok || res.status !== 200) {
                        return null;
                    }
                    try {
                        var finalPath = new URL(res.url).pathname || '';
                        if (/\/(login|logout|password)\b/i.test(finalPath)) {
                            return null;
                        }
                    } catch (eFin) { /* ignore */ }
                    return putOpsHtml(opsCache, pageUrl, res);
                }).catch(function () {
                    return null;
                }).then(function () {
                    return new Promise(function (resolve) {
                        setTimeout(resolve, gap);
                    }).then(pumpOne);
                });
            }
            return pumpOne();
        });
    }
    // Phase OD — protected OA/identity assets first; fail closed (do not mark warm done).
    return ensureProtectedOfflineCache({ force: true }).then(function (protectedResult) {
        return caches.open(ERP_COEXIST_CACHE).then(function (cache) {
            return cacheUrlList(cache, urls).then(function () {
                // HTML warm ~8s after shell assets — charts paint first, then auto-cache pages.
                setTimeout(function () {
                    warmLeanOpsList(leanOpsCritical, 80).then(function () {
                        var rest = leanOps.filter(function (rel) {
                            return leanOpsCritical.indexOf(rel) === -1;
                        });
                        return warmLeanOpsList(rest, 150);
                    }).catch(function () { return null; });
                }, 8000);
                return protectedResult;
            });
        }).then(function (result) {
            LAST_SHELL_WARM_AT = Date.now();
            shellWarmRunning = false;
            return result;
        });
    }).catch(function (err) {
        shellWarmRunning = false;
        LAST_PROTECTED_CACHE_RESULT = {
            ok: false,
            error: String(err && err.message ? err.message : err),
            at: Date.now(),
            build: SW_BUILD_ID
        };
        // Do not swallow — callers must see the failure.
        throw err;
    });
}

/**
 * Phase OB — JS under /assets/offline that must NEVER be replaced by the
 * emptyAssetResponse stub (28-byte rateb-pos offline stub).
 * Covers OA bootstrap, modules/*, runtime/*, offline assets/*, shell SDK.
 */
function isProtectedOfflineIdentityJs(pathname) {
    var path = String(pathname || '').toLowerCase();
    return /\/assets\/offline\/(?:offline-bootstrap\.js|erp-shell-bootstrap\.js|erp-offline-tenant-context\.js|rateb-offline(?:\.min)?\.js|erp-offline-shell[^/]*\.js|(?:modules|runtime|assets)\/)/i
        .test(path);
}

/** Prefer cached ERP offline assets; ignore ?v= query so warm keys still hit. */
function isVersionedOfflineIdentityJs(pathname) {
    return /\/assets\/offline\/(offline-bootstrap|erp-offline-nav-guard|erp-offline-full-warm|erp-shell-bootstrap|erp-offline-tenant-context|erp-offline-shell-(auth|rbac)|rateb-offline(\.min)?)\.js$/i
        .test(String(pathname || ''))
        || /\/assets\/offline\/modules\//i.test(String(pathname || ''));
}

/** Admin shell JS — online must honour ?v= bust (never ignoreSearch stale body). */
function isVersionedAdminShellJs(pathname) {
    return /\/assets\/js\/(erp-nav-instant|settings-mail-dns|module-page-stats|dashboard-charts-defer|charts|theme|lang)\.js$/i
        .test(String(pathname || ''));
}

/**
 * PERF-P0.1 — Cache lookup for offline identity/runtime assets.
 * Offline: prefer ignoreSearch immediately; search coexist + assets + shell + all rateb-* caches.
 * Memoize hits by pathname to prevent duplicate multi-cache walks.
 * Never hang: no network race offline; keys() walk only as last resort and capped.
 */
function matchErpOfflineCached(request, url) {
    var pathnameKey = '';
    var reqUrl = '';
    try {
        pathnameKey = url.origin + url.pathname;
        reqUrl = String(request && request.url ? request.url : (url.href || pathnameKey));
    } catch (e0) {
        pathnameKey = '';
        reqUrl = '';
    }
    var offline = isCloudBrowserOffline();
    var versionedJs = isVersionedOfflineIdentityJs(url.pathname)
        || isVersionedAdminShellJs(url.pathname);
    var identity = isProtectedOfflineIdentityJs(url.pathname);
    var memoKey = pathnameKey || reqUrl;

    if (offline && identity && memoKey && IDENTITY_MATCH_MEMO[memoKey]) {
        return Promise.resolve(IDENTITY_MATCH_MEMO[memoKey]);
    }

    function remember(hit) {
        if (offline && identity && memoKey && hit) {
            IDENTITY_MATCH_MEMO[memoKey] = hit;
        }
        return hit;
    }

    function matchInCache(cache) {
        // Exact URL (with ?v=) first always.
        return cache.match(request).then(function (hitExact) {
            if (hitExact) {
                return hitExact;
            }
            // Online + versioned warm/guard/SDK: miss → network must fetch the new build.
            if (versionedJs && !offline) {
                return null;
            }
            if (!pathnameKey) {
                return null;
            }
            // PERF-P0.1 offline identity: ignoreSearch next (avoid keys() scan).
            if (offline || identity) {
                return cache.match(pathnameKey).then(function (hit2) {
                    if (hit2) {
                        return hit2;
                    }
                    return cache.match(pathnameKey, { ignoreSearch: true });
                });
            }
            return cache.match(pathnameKey).then(function (hit2) {
                if (hit2) {
                    return hit2;
                }
                // ignoreSearch only — never cache.keys() (was multi-second offline lag).
                return cache.match(pathnameKey, { ignoreSearch: true });
            });
        });
    }

    var preferredNames = [
        ERP_COEXIST_CACHE,
        ASSET_CACHE,
        SHELL_CACHE
    ];

    function searchNames(names) {
        return (names || []).reduce(function (chain, name) {
            return chain.then(function (found) {
                if (found) {
                    return found;
                }
                return caches.open(name).then(matchInCache).catch(function () {
                    return null;
                });
            });
        }, Promise.resolve(null));
    }

    // Preferred caches only — never enumerate every rateb-* cache on the critical path.
    return searchNames(preferredNames).then(remember).catch(function () {
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

function markCloudNetworkDegraded(reason) {
    // Client soft-offline badge must survive F5 (page unload clears JS; latch lives in SW).
    // Timeout/probe latch stays short so true online is not poisoned.
    var ttl = (reason === 'client') ? 120000 : CLOUD_DEGRADED_TTL_MS;
    cloudDegradedUntil = Date.now() + ttl;
}

function clearCloudNetworkDegraded() {
    cloudDegradedUntil = 0;
}

/**
 * Cloud tab with no internet — use cache/shell fail-fast.
 * Soft-offline latch (Wi‑Fi dead, navigator.onLine still true) is for ASSETS only.
 * Document navigations must NOT use the latch — false positives caused minutes of black /admin.
 * Never true on local appliance (PHP built-in server is still up).
 */
function isCloudBrowserOffline() {
    if (isLocalApplianceOrigin()) {
        return false;
    }
    if (self.navigator && self.navigator.onLine === false) {
        return true;
    }
    return Date.now() < cloudDegradedUntil;
}

/** Hard offline only — ignore soft latch (used for navigate/document FetchEvents). */
function isHardBrowserOffline() {
    if (isLocalApplianceOrigin()) {
        return false;
    }
    try {
        return !!(self.navigator && self.navigator.onLine === false);
    } catch (eOff) {
        return false;
    }
}

/** Offline must not wait on hanging fetch(); online uses AbortController race. */
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
    var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
    var timer = setTimeout(function () {
        if (ctrl) {
            try { ctrl.abort(); } catch (eAb) { /* ignore */ }
        }
    }, ms);
    return fetch(request, {
        credentials: 'same-origin',
        signal: ctrl ? ctrl.signal : undefined
    }).then(function (response) {
        if (response && response.ok) {
            clearCloudNetworkDegraded();
            return response;
        }
        return null;
    }).catch(function () {
        return null;
    }).then(function (res) {
        clearTimeout(timer);
        return res;
    });
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
            headers: {
                Accept: 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Sec-Fetch-Mode': 'navigate',
                'Sec-Fetch-Dest': 'document'
            }
        });
    } catch (e) {
        try {
            return String(request.url || request);
        } catch (e2) {
            return request;
        }
    }
}

/** Strip redirected bit / materialize a fresh body so Cache.put never sees a used stream. */
function asNonRedirectedResponse(response) {
    if (!response) {
        return Promise.resolve(null);
    }
    var redirected = false;
    try {
        redirected = !!response.redirected;
    } catch (eR) {
        redirected = false;
    }
    // Fast path: pass-through online navigations & assets — arrayBuffer copy stalls every page.
    if (!redirected) {
        return Promise.resolve(response);
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
    var src;
    try {
        src = response.clone();
    } catch (eClone) {
        src = response;
    }
    return src.arrayBuffer().then(function (buf) {
        // Null-body statuses must not receive a buffer (throws TypeError).
        if (status === 204 || status === 205 || status === 304) {
            return new Response(null, {
                status: status,
                statusText: statusText,
                headers: headers
            });
        }
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
    // Cloud pages: bounded wait + AbortController (hung fetch froze SW → black offline).
    var ms = typeof timeoutMs === 'number' ? timeoutMs : 4500;
    var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
    var timer = setTimeout(function () {
        markCloudNetworkDegraded('navigate-timeout');
        if (ctrl) {
            try { ctrl.abort(); } catch (eAb) { /* ignore */ }
        }
    }, ms);
    var network = fetch(navigateFetchInput(request), {
        signal: ctrl ? ctrl.signal : undefined
    }).then(asNonRedirectedResponse).then(function (response) {
        // Non-OK (404/500) must fall through to cache — never paint server errors over
        // a good offline snapshot (edit→back to companies-approvals).
        if (!response || !response.ok) {
            return Promise.reject(new Error('bad-navigate-status'));
        }
        clearCloudNetworkDegraded();
        return response;
    });
    var timed = new Promise(function (_resolve, reject) {
        setTimeout(function () {
            reject(new Error('navigate-timeout'));
        }, ms);
    });
    return Promise.race([network, timed]).then(function (res) {
        clearTimeout(timer);
        return res;
    }, function (err) {
        clearTimeout(timer);
        return Promise.reject(err);
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

/** Dashboard Chart.js — never replace with offline stub while online (black canvases). */
function isCriticalOnlineChartAsset(pathname) {
    var p = String(pathname || '');
    return /\/assets\/vendor\/chartjs\/.+\.js$/i.test(p)
        || /\/assets\/js\/charts\.js$/i.test(p)
        || /\/assets\/js\/dashboard-charts-defer\.js$/i.test(p);
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

/** Drop cached OA identity JS that is a stub / too small to be a real module. */
function rejectFakeOfflineIdentityJs(response, pathname) {
    if (!response || !isProtectedOfflineIdentityJs(pathname)) {
        return Promise.resolve(response || null);
    }
    return response.clone().text().then(function (text) {
        var t = String(text || '');
        if (/rateb-pos\s+offline\s+stub/i.test(t) || t.length < 1000) {
            return null;
        }
        return response;
    }).catch(function () {
        return response;
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
        // Phase OB — never stub OA bootstrap / modules / runtime / shell identity JS.
        // Missing cache must surface as 503, not a 28-byte fake that silently kills OA.
        if (isProtectedOfflineIdentityJs(path)) {
            return new Response('/* rateb offline identity missing from cache */', {
                status: 503,
                headers: {
                    'Content-Type': 'application/javascript; charset=utf-8',
                    'X-Rateb-Offline': '1',
                    'Cache-Control': 'no-store'
                }
            });
        }
        // Chart.js stub executes as "success" and leaves black dashboard canvases online.
        if (isCriticalOnlineChartAsset(path)) {
            return new Response('/* rateb chart asset missing */', {
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
        // Soft 200: missing module CSS must not spam console 503 / break other sheets.
        // (Real design CSS is precached + page-rescued; empty stub is last resort.)
        body = '/* rateb offline: stylesheet missing from cache */';
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
    } else if (/\.webmanifest$/i.test(path) || /\/manifest\.json$/i.test(path)) {
        // Never return empty text/plain — Chrome reports manifest syntax error at 1:1.
        body = JSON.stringify({
            name: 'RATEB POS',
            short_name: 'POS',
            start_url: './admin/ops/pos/register',
            scope: './',
            display: 'standalone',
            background_color: '#0f1419',
            theme_color: '#0f1419',
            lang: 'ar',
            dir: 'rtl',
            icons: []
        });
        type = 'application/manifest+json; charset=utf-8';
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
    if (!response || !response.ok || response.status !== 200) {
        return Promise.resolve(false);
    }
    var url = new URL(request.url || request);
    if (!isRegisterShellPath(url.pathname) && !isPosNavigation(url)) {
        return Promise.resolve(false);
    }
    if (isBiometricGatePath(url.pathname)) {
        return Promise.resolve(false);
    }
    return response.clone().text().then(function (html) {
        if (!isCertifiedRegisterHtml(html)) {
            return false;
        }
        var hash = simpleHtmlHash(html);
        var headers = new Headers({
            'Content-Type': 'text/html; charset=utf-8',
            'X-Rateb-Pos-Cert': '1',
            'X-Rateb-Pos-Cert-Version': POS_SNAPSHOT_VERSION,
            'X-Rateb-Pos-Cert-Hash': hash
        });
        function makeRes() {
            return new Response(html, { status: 200, statusText: 'OK', headers: new Headers(headers) });
        }
        return caches.open(SHELL_CACHE).then(function (cache) {
            var shellKey = registerShellUrl();
            var bare = url.origin + url.pathname;
            var withQuery = url.origin + url.pathname + url.search;
            var altRegister = /\/register$/i.test(url.pathname)
                ? url.origin + url.pathname.replace(/\/register$/i, '')
                : url.origin + url.pathname.replace(/\/?$/, '') + '/register';
            var meta = {
                version: POS_SNAPSHOT_VERSION,
                certified: true,
                certified_at: Date.now(),
                biometric_completed_online: true,
                html_hash: hash,
                html_len: html.length,
                company_id: 0,
                branch_id: 0,
                user_id: 0,
                url: url.href
            };
            try {
                var cid = parseInt(url.searchParams.get('company_id') || '0', 10) || 0;
                if (cid > 0) {
                    meta.company_id = cid;
                    meta.tenant_id = cid;
                }
                var cfg = html.match(/id=["']rateb-pos-register-config["'][^>]*>([\s\S]*?)<\/script>/i);
                if (cfg && cfg[1]) {
                    var j = JSON.parse(cfg[1]);
                    meta.company_id = parseInt(j.companyId, 10) || meta.company_id;
                    meta.tenant_id = meta.company_id;
                    meta.user_id = parseInt(j.userId, 10) || 0;
                    meta.branch_id = parseInt((j.registerScope && j.registerScope.branch_id) || j.branchId || 0, 10) || 0;
                    meta.cashier = String(j.displayName || '');
                }
            } catch (eMeta) { /* ignore */ }
            var metaRes = new Response(JSON.stringify(meta), {
                status: 200,
                headers: { 'Content-Type': 'application/json; charset=utf-8', 'X-Rateb-Pos-Cert': '1' }
            });
            var tasks = [
                cache.put(bare, makeRes()),
                cache.put(withQuery, makeRes()),
                cache.put(shellKey, makeRes()),
                cache.put(registerCertMetaUrl(), metaRes)
            ];
            tasks.push(cache.put(altRegister, makeRes()));
            tasks.push(cache.put(altRegister + url.search, makeRes()));
            try {
                var opsReg = new URL('admin/ops/pos/register', self.registration.scope).href;
                var opsBare = new URL('admin/ops/pos', self.registration.scope).href;
                tasks.push(cache.put(opsReg, makeRes()));
                tasks.push(cache.put(opsBare, makeRes()));
            } catch (eAlias) { /* ignore */ }
            return Promise.all(tasks).then(function () { return true; });
        });
    }).catch(function () {
        return false;
    });
}

function readCertMeta(cache) {
    return cache.match(registerCertMetaUrl()).then(function (res) {
        if (!res) {
            return null;
        }
        return res.json().catch(function () { return null; });
    }).catch(function () {
        return null;
    });
}

function certMetaMatchesRequest(meta, url) {
    if (!meta || meta.certified !== true) {
        return false;
    }
    if (String(meta.version || '') !== POS_SNAPSHOT_VERSION) {
        return false;
    }
    if (!meta.biometric_completed_online) {
        return false;
    }
    if (!(parseInt(meta.company_id, 10) > 0) || !(parseInt(meta.user_id, 10) > 0)) {
        return false;
    }
    try {
        var cid = parseInt(url.searchParams.get('company_id') || '0', 10) || 0;
        if (cid > 0 && cid !== parseInt(meta.company_id, 10)) {
            return false;
        }
        var bid = parseInt(url.searchParams.get('branch_id') || '0', 10) || 0;
        if (bid > 0 && meta.branch_id > 0 && bid !== parseInt(meta.branch_id, 10)) {
            return false;
        }
        var uid = parseInt(url.searchParams.get('user_id') || '0', 10) || 0;
        if (uid > 0 && uid !== parseInt(meta.user_id, 10)) {
            return false;
        }
    } catch (e) { /* ignore */ }
    return true;
}

function serveCertifiedShellOrBioRequired(request) {
    var reqUrl = typeof request === 'string' ? request : (request && request.url ? request.url : '');
    return caches.open(SHELL_CACHE).then(function (cache) {
        var url = new URL(reqUrl, self.location.origin);
        return readCertMeta(cache).then(function (meta) {
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
                if (!cached) {
                    return posBioRequiredOrLiveRetry(request);
                }
                return cached.clone().text().then(function (html) {
                    if (!isCertifiedRegisterHtml(html)) {
                        return posBioRequiredOrLiveRetry(request);
                    }
                    // Phase OJ — certified meta is mandatory (no legacy uncertified shell).
                    if (!meta || !certMetaMatchesRequest(meta, url)) {
                        return posBioRequiredOrLiveRetry(request);
                    }
                    var hashNow = simpleHtmlHash(html);
                    if (meta.html_len && meta.html_len !== html.length) {
                        return posBioRequiredOrLiveRetry(request);
                    }
                    if (meta.html_hash && String(meta.html_hash).indexOf('fnv1a:') === 0 && meta.html_hash !== hashNow) {
                        return posBioRequiredOrLiveRetry(request);
                    }
                    var headers = new Headers({
                        'Content-Type': 'text/html; charset=utf-8',
                        'X-Rateb-Offline': '1',
                        'X-Rateb-Pos-Cert': '1',
                        'X-Rateb-Pos-Cert-Version': POS_SNAPSHOT_VERSION
                    });
                    return new Response(html, { status: 200, headers: headers });
                });
            });
        });
    }).catch(function () {
        return posBioRequiredOrLiveRetry(request);
    });
}

/** Offline POS: legacy helper — register shell only. CRUD admin pages must not call this. */
function posOfflineRegisterUrl(url) {
    try {
        var u = new URL(url.href || url);
        u.pathname = String(u.pathname || '')
            .replace(/\/+$/, '');
        if (!/\/register$/i.test(u.pathname)) {
            if (/\/pos$/i.test(u.pathname)) {
                u.pathname = u.pathname + '/register';
            } else if (/\/pos\/biometric$/i.test(u.pathname)) {
                u.pathname = u.pathname.replace(/\/biometric$/i, '/register');
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
    try {
        var href = typeof request === 'string' ? request : (request && request.url ? request.url : '');
        if (href) {
            var p = new URL(href, self.location.origin).pathname;
            if (isPosAdminCrudPath(p)) {
                // Never paint "connection required" unless the browser is actually offline.
                if (isHardBrowserOffline()) {
                    return Promise.resolve(posAdminConnectionRequiredResponse());
                }
                return fetch(typeof request === 'string' ? request : request).catch(function () {
                    return posAdminConnectionRequiredResponse();
                });
            }
        }
    } catch (eCrudShell) { /* ignore */ }
    if (!isHardBrowserOffline()) {
        return fetchPosLiveOrShowRetry(request);
    }
    return serveCertifiedShellOrBioRequired(request);
}

/**
 * Phase PC — non-blocking post-activate warm.
 * Keeps install/activate waitUntil free of ensureProtectedOfflineCache and
 * optional chrome/ops warm so navigation is never gated on protected inventory.
 *
 * Phase PF — first controlled document navigation always wins: arm warm on
 * activate, release only after the first document respondWith promise settles.
 */
var backgroundWarmRunning = false;
var LAST_BACKGROUND_WARM = null;
/** @type {object|null} */
var pendingBackgroundWarmOpts = null;
var firstDocumentResponseCommitted = false;
/** Page/bootstrap asked for force ensure before first document — honor after gate. */
var pendingForceEnsureFromMessage = false;

function armBackgroundWarmAfterFirstDocument(opts) {
    opts = opts || { reason: 'activate', force: false };
    if (opts.force) {
        pendingForceEnsureFromMessage = true;
    }
    if (firstDocumentResponseCommitted) {
        scheduleBackgroundWarm(opts);
        return;
    }
    if (!pendingBackgroundWarmOpts) {
        pendingBackgroundWarmOpts = opts;
    } else if (opts.force) {
        pendingBackgroundWarmOpts.force = true;
        if (opts.reason) {
            pendingBackgroundWarmOpts.reason = opts.reason;
        }
    }
}

function releaseBackgroundWarmAfterFirstDocument() {
    if (firstDocumentResponseCommitted) {
        return;
    }
    firstDocumentResponseCommitted = true;
    var opts = pendingBackgroundWarmOpts || { reason: 'first_document', force: false };
    pendingBackgroundWarmOpts = null;
    if (pendingForceEnsureFromMessage) {
        opts.force = true;
        pendingForceEnsureFromMessage = false;
    }
    scheduleBackgroundWarm(opts);
}

/**
 * Commit document navigation response first; only then release the one-shot warm gate.
 * Must not start populate/verify/cache.put on the critical path of first open.
 * NEVER reject — a rejected respondWith paints Chrome «لا يتوفر اتصال بالإنترنت».
 */
function respondWithDocumentAndReleaseWarmGate(event, responsePromise) {
    event.respondWith(
        Promise.resolve(responsePromise).then(function (response) {
            setTimeout(function () {
                releaseBackgroundWarmAfterFirstDocument();
            }, 0);
            if (response && typeof response.status === 'number') {
                return response;
            }
            try {
                return erpInlineShellResponse();
            } catch (eShell) {
                return uncachedAdminBrowseResponse(null);
            }
        }).catch(function () {
            setTimeout(function () {
                releaseBackgroundWarmAfterFirstDocument();
            }, 0);
            try {
                return erpInlineShellResponse();
            } catch (eShell2) {
                return new Response(
                    '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8">'
                    + '<title>RATEB ERP — Offline</title></head>'
                    + '<body style="margin:0;font-family:system-ui;background:#0f1117;color:#e8eaed;'
                    + 'display:flex;min-height:100vh;align-items:center;justify-content:center;text-align:center;padding:2rem">'
                    + '<div><h1>وضع عدم الاتصال</h1><p>افتح النظام وأنت متصل مرة واحدة.</p></div></body></html>',
                    { status: 200, headers: { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' } }
                );
            }
        })
    );
}

function migrateActivateCaches() {
    return caches.keys().then(function (keys) {
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
                return migrateErpCoexistCaches(keys);
            }).then(function () {
                // PERF-P0.1 — NEVER wipe real identity JS on activate/warm.
                // Prior code deleted every isVersionedOfflineIdentityJs entry across all caches
                // before deferred ensureProtectedOfflineCache, creating a miss window:
                // offline-shell → load_failed → harness waits ~21s for dashboard.
                // Only remove stub / identity-missing placeholders; migrate good bodies into coexist.
                return caches.open(ERP_COEXIST_CACHE).then(function (coexist) {
                    return Promise.all(keys.map(function (name) {
                        return caches.open(name).then(function (cache) {
                            return cache.keys().then(function (reqs) {
                                return Promise.all(reqs.map(function (req) {
                                    try {
                                        var href = typeof req === 'string' ? req : (req.url || '');
                                        var pu = new URL(href);
                                        if (!isVersionedOfflineIdentityJs(pu.pathname)
                                            && !isProtectedOfflineIdentityJs(pu.pathname)) {
                                            return null;
                                        }
                                        return cache.match(req).then(function (res) {
                                            if (!res) {
                                                return null;
                                            }
                                            return res.clone().text().then(function (text) {
                                                var relGuess = pu.pathname.replace(/^.*\/assets\//i, 'assets/');
                                                if (!isAcceptableProtectedBody(relGuess, text)) {
                                                    return cache.delete(req);
                                                }
                                                // Stamp bare + request URL into coexist (alias-safe).
                                                var headers = {
                                                    'Content-Type': res.headers.get('Content-Type')
                                                        || 'application/javascript; charset=utf-8',
                                                    'X-Rateb-Protected-Cached': '1'
                                                };
                                                var bare = pu.origin + pu.pathname;
                                                var body = text;
                                                function put(key) {
                                                    return coexist.put(key, new Response(body, {
                                                        status: 200,
                                                        headers: headers
                                                    })).catch(function () { return null; });
                                                }
                                                return put(bare).then(function () {
                                                    return put(href);
                                                }).then(function () {
                                                    if (/rateb-offline(\.min)?\.js$/i.test(pu.pathname)) {
                                                        return put(bare + '?v=oid-20260713-lean');
                                                    }
                                                    return null;
                                                }).then(function () {
                                                    try {
                                                        delete IDENTITY_MATCH_MEMO[bare];
                                                    } catch (eM) { /* ignore */ }
                                                    return null;
                                                });
                                            });
                                        });
                                    } catch (eDel) { /* ignore */ }
                                    return null;
                                }));
                            });
                        }).catch(function () { return null; });
                    }));
                });
            }).then(function () {
                return Promise.all(keys.map(function (key) {
                    if (key === SHELL_CACHE || key === ASSET_CACHE
                        || key === ERP_COEXIST_CACHE || key === ERP_OPS_PAGE_CACHE
                        || key === ERP_OPS_ALLOWLIST_CACHE) {
                        return undefined;
                    }
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
    });
}

function warmOptionalChromeAssets() {
    return caches.open(ERP_COEXIST_CACHE).then(function (cache) {
        var base = publicBaseUrl();
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
            'assets/js/rateb-modal.js',
            'assets/js/rateb-confirm.js',
            'assets/js/approvals-oversight.js',
            'assets/js/entity-documents-modal.js',
            'assets/js/table-tools.js',
            'manifest.webmanifest',
            'pos-manifest.webmanifest'
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
    }).catch(function () { return null; });
}

function runBackgroundWarm(opts) {
    opts = opts || {};
    var t0 = Date.now();
    LAST_BACKGROUND_WARM = { started_at: t0, reason: opts.reason || 'idle', build: SW_BUILD_ID };
    return migrateActivateCaches().catch(function () {
        return null;
    }).then(function () {
        // Verify-first unless explicit force (preserves integrity without blocking nav).
        return ensureProtectedOfflineCache({ force: !!opts.force }).catch(function (err) {
            LAST_PROTECTED_CACHE_RESULT = {
                ok: false,
                error: String(err && err.message ? err.message : err),
                build: SW_BUILD_ID,
                at: Date.now()
            };
            return LAST_PROTECTED_CACHE_RESULT;
        });
    }).then(function (protectedResult) {
        LAST_BACKGROUND_WARM.protected = protectedResult;
        return warmOptionalChromeAssets().then(function () {
            return warmErpOfflineShell({ force: true }).catch(function () { return null; });
        }).then(function () {
            LAST_BACKGROUND_WARM.finished_at = Date.now();
            LAST_BACKGROUND_WARM.wall_ms = LAST_BACKGROUND_WARM.finished_at - t0;
            return LAST_BACKGROUND_WARM;
        });
    });
}

function scheduleBackgroundWarm(opts) {
    if (backgroundWarmRunning) {
        return;
    }
    backgroundWarmRunning = true;
    // Defer so activate waitUntil can resolve before warm starts.
    setTimeout(function () {
        runBackgroundWarm(opts || {}).then(function () {
            backgroundWarmRunning = false;
        }).catch(function () {
            backgroundWarmRunning = false;
        });
    }, 0);
}

/**
 * Phase PC / PF critical path:
 * install → minimal seed → activate → clients.claim → ready
 * WAIT → first document FetchEvent respondWith committed
 * THEN → migrate + ensureProtectedOfflineCache + optional assets (once)
 */
self.addEventListener('install', function (event) {
    self.skipWaiting();
    event.waitUntil(
        seedInlineOfflineShell().then(function () {
            return seedAdminHomeOfflineFallback().catch(function () { return null; });
        }).then(function () {
            return Promise.all([
                caches.open(ASSET_CACHE),
                caches.open(SHELL_CACHE)
            ]);
        }).then(function () {
            // Phase PC — allowlist + protected warm are background (activate/idle).
            return null;
        })
    );
});

self.addEventListener('activate', function (event) {
    // Phase PC — claim FIRST so navigation is never gated on allowlist/network.
    // Phase PF — arm warm only; do not start populate until first document response commits.
    event.waitUntil(
        self.clients.claim().then(function () {
            // Migrate prior ops-page HTML into the current bucket BEFORE delete.
            // Deleting v34/v35 without copy left offline black for minutes after SW update
            // (full-warm still wrote v34 while navigate read v36).
            return caches.keys().then(function (keys) {
                var oldOps = (keys || []).filter(function (key) {
                    return key !== ERP_OPS_PAGE_CACHE
                        && /^rateb-erp-ops-pages-v\d+/i.test(String(key));
                });
                if (!oldOps.length) {
                    return null;
                }
                return caches.open(ERP_OPS_PAGE_CACHE).then(function (fresh) {
                    return Promise.all(oldOps.map(function (name) {
                        return caches.open(name).then(function (old) {
                            return old.keys().then(function (reqs) {
                                return Promise.all((reqs || []).map(function (req) {
                                    return old.match(req).then(function (res) {
                                        if (!res) {
                                            return null;
                                        }
                                        return res.clone().text().then(function (html) {
                                            var href = typeof req === 'string' ? req : (req.url || '');
                                            if (!isValidErpOpsHtmlBody(href, html)) {
                                                return null;
                                            }
                                            var clean = new Response(html, {
                                                status: 200,
                                                headers: { 'Content-Type': 'text/html; charset=utf-8', 'X-Rateb-Ops-Page': '1' }
                                            });
                                            return fresh.put(req, clean.clone()).then(function () {
                                                try {
                                                    var u = new URL(href);
                                                    return fresh.put(u.origin + u.pathname, clean.clone());
                                                } catch (eAlias) {
                                                    return null;
                                                }
                                            });
                                        }).catch(function () {
                                            return null;
                                        });
                                    });
                                }));
                            });
                        }).catch(function () { return null; });
                    }));
                }).then(function () {
                    return Promise.all(oldOps.map(function (name) {
                        return caches.delete(name).catch(function () { return false; });
                    }));
                });
            });
        }).then(function () {
            return seedAdminHomeOfflineFallback().catch(function () { return null; });
        }).then(function () {
            armBackgroundWarmAfterFirstDocument({ reason: 'activate', force: false });
            loadErpOpsAllowlist().catch(function () { return null; });
            return self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clients) {
                (clients || []).forEach(function (client) {
                    try {
                        client.postMessage({
                            type: 'RATEB_HTML_CACHE_BUST',
                            build: SW_BUILD_ID,
                            at: Date.now()
                        });
                    } catch (eMsg) { /* ignore */ }
                });
                return null;
            });
        }).catch(function () {
            armBackgroundWarmAfterFirstDocument({ reason: 'activate_fallback', force: false });
            return null;
        })
    );
});

/**
 * Browser Background Sync — notify open clients to flush IndexedDB queues.
 * Falls back to broadcast when SyncManager is unsupported.
 */
function notifyClientsFlush(reason) {
    return self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clients) {
        (clients || []).forEach(function (client) {
            try {
                client.postMessage({
                    type: 'RATEB_OFFLINE_FLUSH',
                    reason: reason || 'background-sync',
                    at: Date.now()
                });
            } catch (eMsg) { /* ignore */ }
        });
        return { notified: (clients || []).length, reason: reason || 'background-sync' };
    });
}

function notifyClientsPrint(reason) {
    return self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clients) {
        (clients || []).forEach(function (client) {
            try {
                client.postMessage({
                    type: 'RATEB_POS_PRINT_FLUSH',
                    reason: reason || 'background-sync',
                    at: Date.now()
                });
            } catch (eMsg) { /* ignore */ }
        });
        return { notified: (clients || []).length };
    });
}

self.addEventListener('sync', function (event) {
    var tag = String(event.tag || '');
    if (tag === RATEB_SYNC_TAG || tag === 'rateb-erp-offline-flush') {
        event.waitUntil(notifyClientsFlush(tag));
        return;
    }
    if (tag === RATEB_PRINT_SYNC_TAG) {
        event.waitUntil(notifyClientsPrint(tag));
    }
});

self.addEventListener('message', function (event) {
    var data = event.data || {};
    if (data.type === 'SKIP_WAITING') {
        self.skipWaiting();
        return;
    }
    if (data.type === 'RATEB_CLOUD_OFFLINE') {
        markCloudNetworkDegraded('client');
        return;
    }
    if (data.type === 'RATEB_CLOUD_ONLINE') {
        clearCloudNetworkDegraded();
        return;
    }
    if (data.type === 'CLIENTS_CLAIM') {
        event.waitUntil(self.clients.claim());
        return;
    }
    if (data.type === 'REGISTER_BACKGROUND_SYNC') {
        var tag = String(data.tag || RATEB_SYNC_TAG);
        event.waitUntil(
            self.registration.sync
                ? self.registration.sync.register(tag).then(function () {
                    return { ok: true, tag: tag };
                }).catch(function () {
                    if (tag === RATEB_PRINT_SYNC_TAG) {
                        return notifyClientsPrint('register-fallback');
                    }
                    return notifyClientsFlush('register-fallback');
                })
                : (tag === RATEB_PRINT_SYNC_TAG
                    ? notifyClientsPrint('sync-unsupported')
                    : notifyClientsFlush('sync-unsupported'))
        );
        return;
    }
    if ((data.type === 'PIN_REGISTER_SHELL' || data.type === 'CERTIFY_POS_REGISTER_SNAPSHOT') && data.url) {
        event.waitUntil(
            fetch(data.url, {
                credentials: 'same-origin',
                redirect: 'follow',
                headers: { Accept: 'text/html', 'X-Rateb-Shell-Warm': '1', 'X-Rateb-Pos-Certify': '1' }
            }).then(function (response) {
                try {
                    if (response && response.url && /\/pos\/biometric/i.test(response.url)) {
                        return false;
                    }
                } catch (eBio) { /* ignore */ }
                return putShell(data.url, response).then(function (ok) {
                    if (ok && data.meta && typeof data.meta === 'object') {
                        return caches.open(SHELL_CACHE).then(function (cache) {
                            var merged = Object.assign({}, data.meta, {
                                version: POS_SNAPSHOT_VERSION,
                                certified: true,
                                biometric_completed_online: true
                            });
                            return cache.put(registerCertMetaUrl(), new Response(JSON.stringify(merged), {
                                status: 200,
                                headers: { 'Content-Type': 'application/json; charset=utf-8', 'X-Rateb-Pos-Cert': '1' }
                            })).then(function () { return true; });
                        });
                    }
                    return ok;
                });
            }).catch(function () { /* ignore */ })
        );
        return;
    }
    if (data.type === 'WARM_ERP_OFFLINE_SHELL'
        || data.type === 'ENSURE_PROTECTED_OFFLINE_CACHE') {
        var reply = function (result) {
            try {
                if (event.ports && event.ports[0]) {
                    event.ports[0].postMessage(result);
                }
            } catch (ePort) { /* ignore */ }
            try {
                if (event.source && event.source.postMessage) {
                    event.source.postMessage({
                        type: 'PROTECTED_OFFLINE_CACHE_RESULT',
                        result: result
                    });
                }
            } catch (eSrc) { /* ignore */ }
            return result;
        };
        // PERF-P0.3-C — ENSURE force must populate identity NOW (before offline-shell scripts).
        // PERF-P0.3-D — WARM must also run NOW: deferred WARM never stored certified module HTML
        // (hr/attendance, ops/inventory) → offline uncachedAdminBrowseResponse ~1KB / “thin HTML”.
        // Soft activate warm still waits for first document via armBackgroundWarmAfterFirstDocument.
        if (!firstDocumentResponseCommitted
            && data.type !== 'ENSURE_PROTECTED_OFFLINE_CACHE'
            && data.type !== 'WARM_ERP_OFFLINE_SHELL') {
            armBackgroundWarmAfterFirstDocument({
                reason: 'message_' + String(data.type || 'warm'),
                force: !!data.force
            });
            reply({
                ok: false,
                deferred: true,
                reason: 'awaiting_first_document',
                build: SW_BUILD_ID,
                at: Date.now()
            });
            return;
        }
        if (!firstDocumentResponseCommitted) {
            armBackgroundWarmAfterFirstDocument({
                reason: data.type === 'WARM_ERP_OFFLINE_SHELL'
                    ? 'message_force_warm'
                    : 'message_force_ensure',
                force: true
            });
            // Treat explicit WARM/ENSURE as the first-document release so leanOps can run.
            firstDocumentResponseCommitted = true;
        }
        event.waitUntil(
            ensureProtectedOfflineCache({ force: true }).then(function (result) {
                if (data.type === 'WARM_ERP_OFFLINE_SHELL') {
                    // PERF-P0.3-D — await leanOps so ops-page cache holds real module HTML.
                    return warmErpOfflineShell({ force: true }).then(function (shellResult) {
                        return reply({
                            ok: !!(result && result.ok),
                            protected: result,
                            shell: shellResult || null,
                            build: SW_BUILD_ID,
                            at: Date.now()
                        });
                    }).catch(function () {
                        return reply(result);
                    });
                }
                return reply(result);
            }).catch(function (err) {
                var fail = {
                    ok: false,
                    error: String(err && err.message ? err.message : err),
                    build: SW_BUILD_ID,
                    at: Date.now()
                };
                LAST_PROTECTED_CACHE_RESULT = fail;
                return reply(fail);
            })
        );
        return;
    }
    if (data.type === 'PROTECTED_OFFLINE_CACHE_STATUS') {
        event.waitUntil(
            verifyProtectedOfflineCache().then(function (result) {
                if (event.ports && event.ports[0]) {
                    event.ports[0].postMessage(result);
                }
                return result;
            })
        );
        return;
    }
    if (data.type === 'CACHE_ERP_OPS_PAGE') {
        event.waitUntil(putErpOpsPageFromMessage(data));
        return;
    }
    if (data.type === 'PREFETCH_ERP_OPS_URL' || data.type === 'PREFETCH_ERP_URL') {
        var href = data.url || data.href || '';
        event.waitUntil(
            prefetchErpOpsUrl(href).then(function (ok) {
                try {
                    if (event.ports && event.ports[0]) {
                        event.ports[0].postMessage({ ok: !!ok, url: href, build: SW_BUILD_ID });
                    }
                } catch (eP) { /* ignore */ }
                return ok;
            })
        );
        return;
    }
    if (data.type === 'RELOAD_OPS_ALLOWLIST') {
        event.waitUntil(loadErpOpsAllowlist());
        return;
    }
    if (data.type === 'PURGE_ERP_AUTH_CACHE') {
        event.waitUntil(purgeErpOpsAuthPages());
        return;
    }
    if (data.type === 'RATEB_HTML_CACHE_BUST') {
        event.waitUntil(
            caches.keys().then(function (keys) {
                return Promise.all((keys || []).map(function (name) {
                    if (/^rateb-erp-ops-pages-v\d+/i.test(String(name))
                        || String(name) === ERP_COEXIST_CACHE) {
                        return caches.delete(name).catch(function () { return false; });
                    }
                    return null;
                }));
            }).then(function () {
                return caches.open(ERP_OPS_PAGE_CACHE).then(function () { return true; });
            }).catch(function () {
                return false;
            })
        );
        return;
    }
});

self.addEventListener('fetch', function (event) {
    var url;
    try {
        url = new URL(event.request.url);
    } catch (eUrl) {
        return;
    }
    if (url.origin !== self.location.origin) {
        return;
    }

    // Guest QR menu — never intercept (nav, API, assets under /m/).
    if (isGuestMenuPath(url.pathname)) {
        return;
    }

    // Cloud document navigations (ONLINE + OFFLINE):
    // Previously offline skipped this block and fell into neverFailNavigate + cache.keys()
    // scans → multi-second black screens. Always use the fast navigate helpers.
    var isDocumentNav = event.request.mode === 'navigate'
        || event.request.destination === 'document';
    if (!isLocalApplianceOrigin()
        && event.request.method === 'GET'
        && isDocumentNav) {
        if (isLogoutPath(url.pathname) || isAuthPath(url.pathname)) {
            if (isCloudBrowserOffline()) {
                respondWithDocumentAndReleaseWarmGate(
                    event,
                    neverFailNavigate(event.request, url).catch(function () {
                        return erpInlineShellResponse();
                    })
                );
                return;
            }
            event.respondWith(
                fetch(event.request).then(function (res) {
                    event.waitUntil(purgeErpOpsAuthPages());
                    return res;
                })
            );
            return;
        }
        if (isPosNavigation(url)) {
            // Live bypass: always hit PHP when user explicitly asks (recovery from stale SW cache).
            if (url.searchParams.get('rateb_live') === '1') {
                respondWithDocumentAndReleaseWarmGate(
                    event,
                    fetchNavigateNetworkPassthrough(event.request, 15000).then(function (res) {
                        return posHandleLiveNetworkResponse(res, event.request);
                    }).catch(function () {
                        return posHttpRedirectResponse(posAdminRedirectUrl(event.request, false));
                    })
                );
                return;
            }
            // POS admin CRUD now uses Admin ERP HTML. Online: never intercept (no false
            // "connection required" when Wi‑Fi is up). Hard offline only → message page.
            if (isPosAdminCrudPath(url.pathname) || !isPosRuntimePath(url.pathname)) {
                if (isHardBrowserOffline()) {
                    respondWithDocumentAndReleaseWarmGate(
                        event,
                        Promise.resolve(posAdminConnectionRequiredResponse())
                    );
                    return;
                }
                releaseBackgroundWarmAfterFirstDocument();
                return;
            }
            respondWithDocumentAndReleaseWarmGate(
                event,
                (isHardBrowserOffline()
                    ? shellFallback(event.request)
                    : navigatePosCloudWithCacheSafety(event.request, url)
                ).catch(function () {
                    if (isHardBrowserOffline()) {
                        return shellFallback(event.request);
                    }
                    return fetchPosLiveOrShowRetry(event.request);
                })
            );
            return;
        }
        if (isErpAdminPath(url.pathname) || /\/admin(\/|$)/i.test(url.pathname)) {
            // Bypass SW entirely for force-live — stops "جاري التحميل" redirect loops.
            try {
                if (url.searchParams.get('rateb_force_live')
                    || url.searchParams.get('rateb_live') === '1') {
                    releaseBackgroundWarmAfterFirstDocument();
                    return;
                }
            } catch (eBypass) { /* fall through */ }
            // ALWAYS respondWith — never fall through to Chrome «لا يتوفر اتصال».
            // Online: live network (8s) then cache/shell. Offline/soft-latch: shell within 250ms.
            respondWithDocumentAndReleaseWarmGate(
                event,
                adminDocumentNavigate(event.request, url, event).catch(function () {
                    try {
                        if (!isHardBrowserOffline()) {
                            return onlineAdminRetryResponse(url);
                        }
                        return erpInlineShellResponse();
                    } catch (eAdminFinal) {
                        return uncachedAdminBrowseResponse(url);
                    }
                })
            );
            return;
        }
        // Soft-online non-admin: bypass. Soft-latch / hard offline: handled below / residual.
        if (!isHardBrowserOffline() && !isCloudBrowserOffline()) {
            releaseBackgroundWarmAfterFirstDocument();
            return;
        }
    }

    // Soft-nav / prefetch HTML: cache-first like F5, never empty 504.
    // Hit → instant paint; miss → network fetch (same as passthrough).
    if (!isLocalApplianceOrigin()
        && event.request.method === 'GET'
        && event.request.mode !== 'navigate'
        && (isErpAdminPath(url.pathname) || /\/admin(\/|$)/i.test(url.pathname))
        && !isLogoutPath(url.pathname)
        && !isAuthPath(url.pathname)
        && !(isPosNavigation(url) && isPosRuntimePath(url.pathname))) {
        var swapFlag = '';
        try {
            swapFlag = String(event.request.headers.get('X-Rateb-Nav-Swap') || '')
                + String(event.request.headers.get('X-Rateb-Prefetch') || '');
        } catch (eSwapHdr) { /* ignore */ }
        if (swapFlag.indexOf('1') !== -1) {
            event.respondWith(
                softNavAdminHtml(event.request, url, event).catch(function () {
                    // NEVER return lean inline shell to soft-nav — that HTML has no
                    // #rateb-sidebar → shell_mismatch → hardNavigate → black/lean menu.
                    // Soft latch must NOT block live Admin/POS-admin HTML while online.
                    if (isHardBrowserOffline()) {
                        return Promise.reject(new Error('soft-nav-offline-miss'));
                    }
                    return fetch(event.request);
                })
            );
            return;
        }
    }

    // Soft-online non-navigate: pass through XHR/API only.
    // Static assets MUST NOT fall through — when Wi‑Fi is dead but navigator.onLine
    // is still true, bare network hangs freeze the whole ERP for seconds per click.
    if (!isLocalApplianceOrigin() && !isCloudBrowserOffline()) {
        var softStatic = event.request.method === 'GET'
            && (isErpOfflineAsset(url)
                || isPosAsset(url)
                || /\/webfonts\/fa-.+\.(woff2|ttf|woff)$/i.test(url.pathname)
                || /\/assets\/(css|js|vendor|offline|pos|pwa)\//i.test(url.pathname)
                || /\.(woff2?|ttf|otf|png|jpe?g|gif|webp|svg|ico|css|js)$/i.test(url.pathname));
        if (!softStatic) {
            return;
        }
        // continue into cache-first handlers below
    }

    // Offline POST (form Save / XHR): never let Chrome paint «لا يتوفر اتصال».
    if (event.request.method === 'POST') {
        if (/\/admin(\/|$)/i.test(url.pathname) || /\/api\//i.test(url.pathname)) {
            // Online: real server save — SW must not fake-queue platform forms.
            if (!isHardBrowserOffline() && isOnlineOnlyAdminPostPath(url.pathname)) {
                return;
            }
            if (!isHardBrowserOffline() && !isCloudBrowserOffline()) {
                return;
            }
            event.respondWith(handleOfflineAdminPost(event.request, url));
            return;
        }
        return;
    }

    if (event.request.method !== 'GET') {
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
        if (isPosAdminCrudPath(url.pathname) || !isPosRuntimePath(url.pathname)) {
            if (isHardBrowserOffline()) {
                respondWithDocumentAndReleaseWarmGate(
                    event,
                    Promise.resolve(posAdminConnectionRequiredResponse())
                );
                return;
            }
            releaseBackgroundWarmAfterFirstDocument();
            return;
        }
        respondWithDocumentAndReleaseWarmGate(
            event,
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
                return shellFallback(event.request);
            })
        );
        return;
    }

    // Logout offline: never let Chrome show "لا يتوفر اتصال" interstitial.
    if (event.request.mode === 'navigate' && isLogoutPath(url.pathname)) {
        respondWithDocumentAndReleaseWarmGate(
            event,
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

    // PERF-P0.3-C — offline-shell.html must serve the real OA HTML (never poisoned thin seed).
    if (event.request.mode === 'navigate' && /\/offline-shell\.html$/i.test(url.pathname)) {
        respondWithDocumentAndReleaseWarmGate(
            event,
            matchOfflineShellOrInline(event.request).then(function (res) {
                return asNonRedirectedResponse(res).then(function (clean) {
                    return clean || res || erpInlineShellResponse();
                });
            }).catch(function () {
                return erpInlineShellResponse();
            })
        );
        return;
    }

    // Residual offline navigations (non-admin) — admin/POS already handled above.
    if (event.request.mode === 'navigate'
        && !isPosNavigation(url)
        && !isGuestMenuPath(url.pathname)
        && !isAuthPath(url.pathname)
        && !isErpAdminPath(url.pathname)
        && !isApiRequest(url)) {
        respondWithDocumentAndReleaseWarmGate(
            event,
            neverFailNavigate(event.request, url).catch(function () {
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
        if (isCloudBrowserOffline()) {
            event.respondWith(shellFallback(event.request));
            return;
        }
        event.respondWith(
            fetchErpAssetNetwork(navigateFetchInput(event.request), 800).then(function (response) {
                if (response) {
                    var forShell = response.clone();
                    event.waitUntil(putShell(event.request, forShell).catch(function () { return null; }));
                    return asNonRedirectedResponse(response).then(function (clean) {
                        return clean || response;
                    });
                }
                return shellFallback(event.request);
            }).catch(function () {
                return shellFallback(event.request);
            })
        );
        return;
    }

    if (isPosAsset(url)) {
        event.respondWith(
            matchAsset(event.request).then(function (cached) {
                if (cached) {
                    if (!isCloudBrowserOffline()) {
                        event.waitUntil(
                            fetchErpAssetNetwork(event.request, 800).then(function (fresh) {
                                if (!fresh || !fresh.ok) {
                                    return null;
                                }
                                var clone = fresh.clone();
                                return caches.open(ASSET_CACHE).then(function (cache) {
                                    return Promise.all([
                                        cache.put(url.origin + url.pathname, clone.clone()).catch(function () { return null; }),
                                        cache.put(url.origin + url.pathname + (url.search || ''), clone.clone()).catch(function () { return null; })
                                    ]);
                                }).catch(function () { return null; });
                            }).catch(function () { return null; })
                        );
                    }
                    return asNonRedirectedResponse(cached).then(function (c) {
                        return c || cached;
                    });
                }
                if (isCloudBrowserOffline()) {
                    return emptyAssetResponse(event.request);
                }
                return fetchErpAssetNetwork(event.request, 800).then(function (response) {
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
                    if (!response) {
                        return emptyAssetResponse(event.request);
                    }
                    return asNonRedirectedResponse(response).then(function (clean) {
                        return clean || response;
                    });
                }).catch(function () {
                    return emptyAssetResponse(event.request);
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
                var chartCritical = isCriticalOnlineChartAsset(url.pathname);
                var netMs = chartCritical ? 12000 : 800;
                return matchErpOfflineCached(event.request, url).then(function (cached) {
                    return rejectFakeOfflineIdentityJs(cached, url.pathname).then(function (real) {
                        // Never serve a prior empty chart stub from cache while online.
                        if (real && chartCritical) {
                            return real.clone().text().then(function (t) {
                                var text = String(t || '');
                                if (text.length < 500 || /rateb chart asset missing|rateb-pos offline stub/i.test(text)) {
                                    return null;
                                }
                                return real;
                            }).catch(function () { return real; });
                        }
                        return real;
                    }).then(function (real) {
                        if (real && !chartCritical) {
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
                            return asNonRedirectedResponse(real).then(function (c) {
                                return c || real;
                            });
                        }
                        if (real && chartCritical) {
                            event.waitUntil(
                                fetchErpAssetNetwork(event.request, 8000).then(function (fresh) {
                                    if (!fresh) {
                                        return null;
                                    }
                                    return caches.open(ERP_COEXIST_CACHE).then(function (cache) {
                                        return putBoth(cache, fresh.clone());
                                    });
                                }).catch(function () { return null; })
                            );
                            return asNonRedirectedResponse(real).then(function (c) {
                                return c || real;
                            });
                        }
                        // Soft-online miss: charts need a long network budget (800ms starved Chart.js).
                        return fetchErpAssetNetwork(event.request, netMs).then(function (response) {
                            if (response && response.ok) {
                                var forCache = response.clone();
                                event.waitUntil(
                                    caches.open(ERP_COEXIST_CACHE).then(function (cache) {
                                        return putBoth(cache, forCache);
                                    }).catch(function () { return null; })
                                );
                                return asNonRedirectedResponse(response).then(function (clean) {
                                    return clean || response;
                                });
                            }
                            if (chartCritical) {
                                // Last resort: bare fetch without abort — never fake Chart.js online.
                                return fetch(event.request, { credentials: 'same-origin', cache: 'no-store' })
                                    .then(function (r) {
                                        if (r && r.ok) {
                                            event.waitUntil(
                                                caches.open(ERP_COEXIST_CACHE).then(function (cache) {
                                                    return putBoth(cache, r.clone());
                                                }).catch(function () { return null; })
                                            );
                                        }
                                        return r;
                                    });
                            }
                            return emptyAssetResponse(event.request);
                        }).catch(function () {
                            if (chartCritical) {
                                return fetch(event.request, { credentials: 'same-origin', cache: 'no-store' });
                            }
                            return emptyAssetResponse(event.request);
                        });
                    });
                });
            }
            return matchErpOfflineCached(event.request, url).then(function (cached) {
                return rejectFakeOfflineIdentityJs(cached, url.pathname).then(function (real) {
                    if (!real) {
                        return emptyAssetResponse(event.request);
                    }
                    return asNonRedirectedResponse(real).then(function (clean) {
                        return clean || real;
                    });
                });
            });
        })());
        return;
    }

    // Broken relative FA fonts when CSS was inlined against /admin → /rateb-erp/webfonts/*
    if (/\/webfonts\/fa-.+\.(woff2|ttf|woff)$/i.test(url.pathname)) {
        var faMap = {
            'fa-solid-900.woff2': 'assets/vendor/fontawesome/6.5.2/webfonts/fa-solid-900.woff2',
            'fa-solid-900.ttf': 'assets/vendor/fontawesome/6.5.2/webfonts/fa-solid-900.ttf',
            'fa-regular-400.woff2': 'assets/vendor/fontawesome/6.5.2/webfonts/fa-regular-400.woff2',
            'fa-regular-400.ttf': 'assets/vendor/fontawesome/6.5.2/webfonts/fa-regular-400.ttf',
            'fa-brands-400.woff2': 'assets/vendor/fontawesome/6.5.2/webfonts/fa-brands-400.woff2',
            'fa-brands-400.ttf': 'assets/vendor/fontawesome/6.5.2/webfonts/fa-brands-400.ttf'
        };
        var leaf = String(url.pathname.split('/').pop() || '');
        var rel = faMap[leaf];
        if (rel) {
            var fixed;
            try {
                fixed = new URL(rel, self.registration.scope).href;
            } catch (eFa) {
                fixed = self.location.origin + '/rateb-erp/public/' + rel;
            }
            event.respondWith(
                caches.match(fixed).then(function (hit) {
                    return hit || caches.match(fixed, { ignoreSearch: true });
                }).then(function (hit2) {
                    if (hit2) {
                        return hit2;
                    }
                    if (isCloudBrowserOffline()) {
                        return new Response('', { status: 404 });
                    }
                    return fetchErpAssetNetwork(new Request(fixed, { credentials: 'same-origin' }), 800).then(function (res) {
                        if (res && res.ok) {
                            var clone = res.clone();
                            event.waitUntil(
                                caches.open(ERP_COEXIST_CACHE).then(function (c) {
                                    return c.put(fixed, clone);
                                }).catch(function () { return null; })
                            );
                        }
                        return res || new Response('', { status: 404 });
                    }).catch(function () {
                        return new Response('', { status: 404 });
                    });
                })
            );
            return;
        }
    }

    // Soft-fail favicon offline (204 must use null body — empty string throws).
    if (/\/favicon\.(ico|svg|png)$/i.test(url.pathname)) {
        if (isCloudBrowserOffline()) {
            event.respondWith(new Response(null, { status: 204 }));
            return;
        }
    }

    if (isApiRequest(url) && isPosNavigation(url)) {
        return;
    }

    // Soft-fail Admin API probes offline (module-page-stats etc.) — never Chrome network spam.
    if (isApiRequest(url) && isCloudBrowserOffline()
        && (/\/admin(\/|$)/i.test(url.pathname) || /\/rateb-erp\/public\//i.test(url.pathname))) {
        event.respondWith(new Response(JSON.stringify({ ok: false, offline: true }), {
            status: 200,
            headers: {
                'Content-Type': 'application/json; charset=utf-8',
                'Cache-Control': 'no-store',
                'X-Rateb-Offline': '1'
            }
        }));
        return;
    }
});
