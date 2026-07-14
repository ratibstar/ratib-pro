/**
 * RATEB POS — Offline bootstrap + Phase OJ certified register snapshot.
 * Runs only on real register pages (data-pos-register). Never pins biometric gate HTML.
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-pos-register]');
    if (!root) {
        return;
    }
    if (document.querySelector('[data-pos-biometric-gate]')) {
        return;
    }

    var configEl = document.getElementById('rateb-pos-register-config');
    var config = {};
    try {
        config = JSON.parse((configEl && configEl.textContent) || '{}');
    } catch (e) {
        config = {};
    }

    // Must match public/pos-sw.js SHELL_CACHE / ASSET_CACHE.
    var SHELL_CACHE = 'rateb-pos-shell-v8';
    var ASSET_CACHE = 'rateb-pos-assets-v8';
    var REGISTER_SHELL_PATH = '__rateb_pos_register_shell__';
    var CERT_META_PATH = '__rateb_pos_register_cert_meta__';
    var SNAPSHOT_VERSION = 'oj-v1';
    var LOCAL_CERT_KEY = 'rateb_pos_register_cert_oj_v1';

    function publicBaseHref() {
        try {
            if (config.serviceWorkerScope) {
                var scope = String(config.serviceWorkerScope);
                if (scope.slice(-1) !== '/') {
                    scope += '/';
                }
                return scope;
            }
        } catch (e0) { /* ignore */ }
        try {
            var m = String(location.pathname || '').match(/^(.*\/public\/)/i);
            if (m && m[1]) {
                return location.origin + m[1];
            }
        } catch (e1) { /* ignore */ }
        return location.origin + '/rateb-erp/public/';
    }

    function registerShellKey() {
        try {
            return new URL(REGISTER_SHELL_PATH, publicBaseHref()).href;
        } catch (e) {
            return publicBaseHref() + REGISTER_SHELL_PATH;
        }
    }

    function certMetaKey() {
        try {
            return new URL(CERT_META_PATH, publicBaseHref()).href;
        } catch (e) {
            return publicBaseHref() + CERT_META_PATH;
        }
    }

    function registerServiceWorker() {
        if (!('serviceWorker' in navigator) || !config.serviceWorker) {
            return Promise.resolve(null);
        }
        var scope = config.serviceWorkerScope || undefined;
        if (scope === '/' || scope === window.location.origin + '/') {
            try {
                scope = new URL('.', config.serviceWorker).pathname;
            } catch (e) {
                scope = '/rateb-erp/public/';
            }
        }
        return navigator.serviceWorker.register(config.serviceWorker, scope ? { scope: scope } : undefined)
            .catch(function () { return null; });
    }

    function syncOfflineUi(online) {
        var isOnline = online;
        if (typeof isOnline !== 'boolean') {
            isOnline = window.RatebPosConnectivity
                ? window.RatebPosConnectivity.isOnline()
                : navigator.onLine;
        }
        root.classList.toggle('rateb-pos--offline', !isOnline);
    }

    function bindOfflineNavGuard() {
        var blocked = /\/pos\/(reports|settings|dashboard|shifts|terminals)(\/|$|\?)/i;
        document.addEventListener('click', function (e) {
            var link = e.target.closest('a[href]');
            if (!link || !root.contains(link)) {
                return;
            }
            var href = link.getAttribute('href') || '';
            if (!blocked.test(href)) {
                return;
            }
            var offline = window.RatebPosNet
                ? !window.RatebPosNet.isOnline()
                : (window.RatebPosConnectivity ? !window.RatebPosConnectivity.isOnline() : !navigator.onLine);
            if (!offline) {
                return;
            }
            e.preventDefault();
            if (window.RatebPosNotify) {
                window.RatebPosNotify(
                    (config.i18n && config.i18n.pos_offline_nav_blocked) || 'This page needs a connection. Stay on the register while offline.',
                    true
                );
            }
        }, true);
    }

    function isCacheableRegisterHtml(html) {
        var body = String(html || '');
        if (body.length < 2500) {
            return false;
        }
        if (/data-pos-biometric-gate/i.test(body)) {
            return false;
        }
        if (/التحقق البيومتري/i.test(body) && !/data-pos-register(?:\s|=|>)/i.test(body)) {
            return false;
        }
        if (/<title>\s*POS Offline\s*<\/title>|data-rateb-uncached-page|نقطة البيع غير متصلة/i.test(body.slice(0, 4000))) {
            return false;
        }
        if (!/data-pos-register(?:\s|=|>)/i.test(body)) {
            return false;
        }
        if (!/rateb-pos-register-config|rateb-pos-shell|rateb-pos__/i.test(body)) {
            return false;
        }
        return true;
    }

    function sha256Hex(text) {
        if (!window.crypto || !window.crypto.subtle) {
            return Promise.resolve('len:' + String(text || '').length);
        }
        try {
            var data = new TextEncoder().encode(String(text || ''));
            return window.crypto.subtle.digest('SHA-256', data).then(function (buf) {
                return Array.prototype.map.call(new Uint8Array(buf), function (b) {
                    return ('0' + b.toString(16)).slice(-2);
                }).join('');
            });
        } catch (e) {
            return Promise.resolve('len:' + String(text || '').length);
        }
    }

    function buildCertMeta(html, hash, sourceUrl) {
        var scope = config.registerScope || {};
        return {
            version: SNAPSHOT_VERSION,
            certified: true,
            certified_at: Date.now(),
            biometric_completed_online: true,
            html_hash: hash,
            html_len: String(html || '').length,
            company_id: parseInt(config.companyId, 10) || 0,
            tenant_id: parseInt(config.companyId, 10) || 0,
            branch_id: parseInt(scope.branch_id || config.branchId, 10) || 0,
            user_id: parseInt(config.userId, 10) || 0,
            cashier: String(config.displayName || ''),
            url: sourceUrl,
            sw_build_hint: 'phase-oj'
        };
    }

    function putCertMeta(cache, meta) {
        var body = JSON.stringify(meta);
        var res = new Response(body, {
            status: 200,
            headers: {
                'Content-Type': 'application/json; charset=utf-8',
                'X-Rateb-Pos-Cert': '1',
                'Cache-Control': 'no-store'
            }
        });
        return cache.put(certMetaKey(), res);
    }

    /** Pin current register HTML as a certified offline snapshot (post-biometric only). */
    function certifyRegisterSnapshot() {
        if (!('caches' in window) || navigator.onLine === false) {
            return Promise.resolve({ ok: false, reason: 'offline_or_no_cache' });
        }
        var u = new URL(window.location.href);
        if (!/\/pos(\/register)?$/i.test(u.pathname.replace(/\/+$/, ''))) {
            return Promise.resolve({ ok: false, reason: 'not_register_path' });
        }
        if (!document.querySelector('[data-pos-register]') || document.querySelector('[data-pos-biometric-gate]')) {
            return Promise.resolve({ ok: false, reason: 'not_register_dom' });
        }

        var assetUrls = [];
        document.querySelectorAll('script[src*="/assets/pos/"], link[href*="/assets/pos/"], link[href*="/assets/js/theme.js"]').forEach(function (el) {
            var src = el.getAttribute('src') || el.getAttribute('href');
            if (src) {
                try {
                    assetUrls.push(new URL(src, window.location.href).href);
                } catch (e) { /* ignore */ }
            }
        });

        var started = Date.now();
        return fetch(u.href, {
            credentials: 'same-origin',
            headers: { Accept: 'text/html', 'X-Rateb-Shell-Warm': '1', 'X-Rateb-Pos-Certify': '1' },
            cache: 'no-store',
            redirect: 'follow'
        }).then(function (res) {
            if (!res || !res.ok || res.status !== 200) {
                return { ok: false, reason: 'bad_status' };
            }
            try {
                if (/\/pos\/biometric/i.test(res.url || '')) {
                    return { ok: false, reason: 'redirected_to_gate' };
                }
            } catch (eU) { /* ignore */ }
            return res.text().then(function (html) {
                if (!isCacheableRegisterHtml(html)) {
                    return { ok: false, reason: 'html_rejected' };
                }
                return sha256Hex(html).then(function (hash) {
                    var meta = buildCertMeta(html, hash, u.href);
                    var headers = new Headers({
                        'Content-Type': 'text/html; charset=utf-8',
                        'X-Rateb-Pos-Cert': '1',
                        'X-Rateb-Pos-Cert-Version': SNAPSHOT_VERSION,
                        'X-Rateb-Pos-Cert-Hash': hash
                    });
                    function makeRes() {
                        return new Response(html, { status: 200, statusText: 'OK', headers: new Headers(headers) });
                    }
                    var bare = u.origin + u.pathname;
                    var alt = /\/register$/i.test(u.pathname)
                        ? u.origin + u.pathname.replace(/\/register$/i, '')
                        : u.origin + u.pathname.replace(/\/?$/, '') + '/register';
                    return caches.open(SHELL_CACHE).then(function (cache) {
                        return Promise.all([
                            cache.put(u.href, makeRes()),
                            cache.put(bare, makeRes()),
                            cache.put(bare + u.search, makeRes()),
                            cache.put(alt, makeRes()),
                            cache.put(alt + u.search, makeRes()),
                            cache.put(registerShellKey(), makeRes()),
                            putCertMeta(cache, meta)
                        ]).then(function () {
                            try {
                                localStorage.setItem(LOCAL_CERT_KEY, JSON.stringify(meta));
                            } catch (eL) { /* ignore */ }
                            return { ok: true, meta: meta, ms: Date.now() - started };
                        });
                    });
                });
            });
        }).then(function (result) {
            if (!result || !result.ok) {
                return result || { ok: false };
            }
            return caches.open(ASSET_CACHE).then(function (assetCache) {
                return Promise.all(assetUrls.map(function (href) {
                    return fetch(href, { credentials: 'same-origin', cache: 'no-store' }).then(function (r) {
                        if (!r || !r.ok) {
                            return;
                        }
                        var pathOnly = new URL(href).origin + new URL(href).pathname;
                        return Promise.all([
                            assetCache.put(href, r.clone()),
                            assetCache.put(pathOnly, r.clone())
                        ]);
                    }).catch(function () { /* ignore */ });
                }));
            }).then(function () {
                if (navigator.serviceWorker && navigator.serviceWorker.controller) {
                    try {
                        navigator.serviceWorker.controller.postMessage({
                            type: 'CERTIFY_POS_REGISTER_SNAPSHOT',
                            url: u.href,
                            meta: result.meta
                        });
                    } catch (eM) { /* ignore */ }
                }
                try {
                    window.dispatchEvent(new CustomEvent('rateb-pos-register-snapshot-certified', { detail: result }));
                } catch (eE) { /* ignore */ }
                return result;
            });
        }).catch(function () {
            return { ok: false, reason: 'exception' };
        });
    }

    // Back-compat alias used by older callers.
    function pinRegisterShell() {
        return certifyRegisterSnapshot().then(function (r) {
            return !!(r && r.ok);
        });
    }

    window.RatebPosOfflineSnapshot = {
        certify: certifyRegisterSnapshot,
        pin: pinRegisterShell,
        version: SNAPSHOT_VERSION,
        cacheName: SHELL_CACHE
    };

    registerServiceWorker().then(function () {
        bindOfflineNavGuard();
        setTimeout(function () { certifyRegisterSnapshot(); }, 600);
        setTimeout(function () { certifyRegisterSnapshot(); }, 2800);
    });

    if (window.RatebPosConnectivity && window.RatebPosConnectivity.subscribe) {
        window.RatebPosConnectivity.subscribe(function (online) {
            syncOfflineUi(online);
            if (online) {
                certifyRegisterSnapshot();
            }
        });
    } else {
        syncOfflineUi(navigator.onLine);
        window.addEventListener('online', function () {
            syncOfflineUi(true);
            certifyRegisterSnapshot();
        });
        window.addEventListener('offline', function () { syncOfflineUi(false); });
    }
})();
