/**
 * RATEB ERP — Offline nav + deferred form save (reliable sync).
 * Queue offline POST → flush when browser has network (not blocked by soft badge).
 */
(function (root) {
    'use strict';

    var STYLE_ID = 'rateb-offline-nav-guard-css';
    var BANNER_ID = 'rateb-offline-sync-banner';
    var MUTE_NAV_RE = /\/(export|pdf|excel|csv|json|regenerate|wipe)(\/|$|\?)/i;
    // Soft actions (approve/delete/pay/decide) queue offline. Only period-close / wipe / file export stay hard-online.
    var ONLINE_ONLY_RE = /(?:close[-_]?period|wipe|payroll[-_]?calc|transfer[-_]?funds|void[-_]?payment|gl[-_]?post|journal[-_]?post)(\/|$|\?)/i;
    var DEFERRED_KEY = 'rateb_deferred_http_forms_v2';
    var GUARD_BUILD = '20260714-offline-actions-v43';
    var flushing = false;

    function browserHasNetwork() {
        try {
            return !(typeof navigator !== 'undefined' && navigator.onLine === false);
        } catch (e) {
            return true;
        }
    }

    function isOffline() {
        try {
            if (typeof navigator !== 'undefined' && navigator.onLine === false) {
                return true;
            }
        } catch (e0) { /* ignore */ }
        try {
            var badge = root.document.querySelector('[data-rateb-connection-status], #rateb-connection-indicator');
            if (badge) {
                if (badge.classList.contains('is-offline')) {
                    return true;
                }
                if (badge.classList.contains('is-online')) {
                    return false;
                }
            }
        } catch (e2) { /* ignore */ }
        try {
            var conn = root.RatebOfflineConnectivity;
            if (conn && typeof conn.isOnline === 'function') {
                return conn.isOnline() === false;
            }
        } catch (e1) { /* ignore */ }
        return false;
    }

    function hasSwController() {
        try {
            return !!(navigator.serviceWorker && navigator.serviceWorker.controller);
        } catch (e) {
            return false;
        }
    }

    function ensureCss() {
        if (!root.document || root.document.getElementById(STYLE_ID)) {
            return;
        }
        var css = root.document.createElement('style');
        css.id = STYLE_ID;
        css.textContent = ''
            + '#rateb-offline-nav-toast{'
            + 'position:fixed;bottom:4.5rem;left:50%;transform:translateX(-50%);z-index:100000;'
            + 'background:#14532d;color:#bbf7d0;padding:.65rem 1rem;border-radius:8px;'
            + 'font:13px/1.4 system-ui,sans-serif;max-width:90vw;text-align:center;}'
            + '#rateb-offline-nav-toast.is-err{background:#7f1d1d;color:#fecaca;}'
            + '#' + BANNER_ID + '{'
            + 'position:fixed;bottom:0;left:0;right:0;z-index:99999;padding:.55rem .85rem;'
            + 'background:#1e3a5f;color:#e8eaed;font:13px/1.4 system-ui,sans-serif;'
            + 'display:flex;gap:.75rem;align-items:center;justify-content:center;flex-wrap:wrap;}'
            + '#' + BANNER_ID + ' button{border:0;border-radius:6px;padding:.35rem .75rem;'
            + 'background:#3b82f6;color:#fff;cursor:pointer;font:inherit;}'
            + '#' + BANNER_ID + '[hidden]{display:none!important;}';
        root.document.head.appendChild(css);
    }

    function toast(msg, isErr) {
        try {
            var el = root.document.getElementById('rateb-offline-nav-toast');
            if (!el) {
                el = root.document.createElement('div');
                el.id = 'rateb-offline-nav-toast';
                el.setAttribute('role', 'status');
                root.document.body.appendChild(el);
            }
            el.className = isErr ? 'is-err' : '';
            el.textContent = msg;
            el.hidden = false;
            clearTimeout(el.__hide);
            el.__hide = setTimeout(function () {
                try { el.hidden = true; } catch (e) { /* ignore */ }
            }, 5600);
        } catch (e2) { /* ignore */ }
    }

    function readDeferred() {
        try {
            var raw = root.localStorage.getItem(DEFERRED_KEY);
            var list = raw ? JSON.parse(raw) : [];
            return Array.isArray(list) ? list : [];
        } catch (e) {
            return [];
        }
    }

    function writeDeferred(list) {
        try {
            root.localStorage.setItem(DEFERRED_KEY, JSON.stringify(list || []));
        } catch (e) { /* ignore */ }
        updateSyncBanner();
    }

    function updateSyncBanner() {
        try {
            if (!root.document || !root.document.body) {
                return;
            }
            ensureCss();
            var n = readDeferred().length;
            var el = root.document.getElementById(BANNER_ID);
            if (n < 1) {
                if (el) {
                    el.hidden = true;
                }
                return;
            }
            if (!el) {
                el = root.document.createElement('div');
                el.id = BANNER_ID;
                el.innerHTML = '<span></span><button type="button" data-rateb-sync-now="1">مزامنة الآن</button>';
                root.document.body.appendChild(el);
                el.querySelector('[data-rateb-sync-now]').addEventListener('click', function () {
                    flushDeferredForms({ force: true }).then(function (stats) {
                        if (stats && stats.ok > 0) {
                            try { root.location.reload(); } catch (eR) { /* ignore */ }
                        }
                    });
                });
            }
            el.hidden = false;
            el.querySelector('span').textContent = 'تعديلات بانتظار المزامنة: ' + n
                + (browserHasNetwork() ? '' : ' — وصّل النت ثم اضغط مزامنة');
        } catch (eB) { /* ignore */ }
    }

    function clearStaleMarks() {
        try {
            root.document.querySelectorAll('a.rateb-offline-missing, a.rateb-offline-cached').forEach(function (a) {
                a.classList.remove('rateb-offline-missing', 'rateb-offline-cached');
                a.removeAttribute('aria-disabled');
            });
        } catch (e) { /* ignore */ }
    }

    function isMuteHref(href) {
        try {
            var u = new URL(href, root.location.href);
            return MUTE_NAV_RE.test(u.pathname + u.search) || ONLINE_ONLY_RE.test(u.pathname + u.search);
        } catch (e) {
            return false;
        }
    }

    function formIsOnlineOnly(form) {
        if (!form) {
            return true;
        }
        if (form.getAttribute('data-rateb-offline-online-only') === '1') {
            return true;
        }
        var blob = [
            form.getAttribute('action') || '',
            form.getAttribute('id') || '',
            (root.location && root.location.pathname) || ''
        ].join(' ');
        return ONLINE_ONLY_RE.test(blob) || MUTE_NAV_RE.test(blob);
    }

    function serializeFormFields(form) {
        var out = {};
        if (!form || !form.elements) {
            return out;
        }
        Array.prototype.forEach.call(form.elements, function (el) {
            if (!el || !el.name || el.disabled) {
                return;
            }
            var name = String(el.name);
            if (/^_csrf$/i.test(name)) {
                return;
            }
            var type = String(el.type || '').toLowerCase();
            if (type === 'file' || type === 'submit' || type === 'button' || type === 'password') {
                return;
            }
            if ((type === 'checkbox' || type === 'radio') && !el.checked) {
                return;
            }
            if (Object.prototype.hasOwnProperty.call(out, name)) {
                if (!Array.isArray(out[name])) {
                    out[name] = [out[name]];
                }
                out[name].push(el.value);
            } else {
                out[name] = el.value;
            }
        });
        return out;
    }

    function deferHttpForm(form) {
        var action = form.getAttribute('action') || (root.location && root.location.href) || '';
        var absolute;
        try {
            absolute = new URL(action, root.location.href).href;
        } catch (eU) {
            absolute = action;
        }
        var fields = serializeFormFields(form);
        if (!Object.keys(fields).length) {
            throw new Error('empty_form');
        }
        var entry = {
            id: 'df-' + Date.now() + '-' + Math.floor(Math.random() * 1e6),
            url: absolute,
            path: (root.location && root.location.pathname) || '',
            fields: fields,
            title: (root.document && root.document.title) || '',
            created_at: Date.now()
        };
        var list = readDeferred();
        list.push(entry);
        writeDeferred(list);
        try {
            var q = root.RatebOfflineQueue;
            if (q && typeof q.enqueue === 'function') {
                q.enqueue({
                    module: 'offline_meta',
                    action: 'offline.http_form',
                    payload: entry,
                    idempotency_key: entry.id,
                    client_uuid: entry.id
                }).catch(function () { /* localStorage holds it */ });
            }
        } catch (eQ) { /* ignore */ }
        return entry;
    }

    function currentCsrf() {
        try {
            var meta = root.document.querySelector('meta[name="rateb-csrf"]');
            if (meta && meta.getAttribute('content')) {
                return String(meta.getAttribute('content'));
            }
        } catch (e) { /* ignore */ }
        return '';
    }

    function pullDeferredFromCaches() {
        if (!root.caches || typeof root.caches.keys !== 'function') {
            return Promise.resolve(readDeferred());
        }
        var list = readDeferred();
        return root.caches.keys().then(function (names) {
            var erp = (names || []).filter(function (n) {
                return String(n).indexOf('rateb-erp-coexist-') === 0
                    || String(n).indexOf('rateb-erp-ops-pages-') === 0;
            });
            return erp.reduce(function (chain, name) {
                return chain.then(function (acc) {
                    return root.caches.open(name).then(function (cache) {
                        return cache.keys().then(function (reqs) {
                            var jobs = [];
                            (reqs || []).forEach(function (req) {
                                var href = typeof req === 'string' ? req : (req.url || '');
                                if (href.indexOf('__rateb_deferred_posts__/') === -1) {
                                    return;
                                }
                                jobs.push(cache.match(req).then(function (res) {
                                    if (!res) {
                                        return null;
                                    }
                                    return res.json().then(function (entry) {
                                        if (entry && entry.id && entry.fields) {
                                            var exists = acc.some(function (x) {
                                                return x && x.id === entry.id;
                                            });
                                            if (!exists) {
                                                acc.push(entry);
                                            }
                                        }
                                        return cache.delete(req).catch(function () { return null; });
                                    }).catch(function () { return null; });
                                }));
                            });
                            return Promise.all(jobs).then(function () { return acc; });
                        });
                    });
                });
            }, Promise.resolve(list.slice()));
        }).then(function (merged) {
            writeDeferred(merged);
            return merged;
        }).catch(function () {
            return list;
        });
    }

    function postLooksFailed(res, htmlHead) {
        if (!res) {
            return true;
        }
        var finalUrl = '';
        try {
            finalUrl = String(res.url || '');
        } catch (e) { /* ignore */ }
        if (/\/login(\/|$|\?)/i.test(finalUrl)) {
            return true;
        }
        if (res.status >= 400) {
            return true;
        }
        var head = String(htmlHead || '').slice(0, 5000);
        if (/data-rateb-login|id=["']login-form["']/i.test(head)) {
            return true;
        }
        if (/(csrf|رمز الأمان|غير مصرح|forbidden)/i.test(head)
            && /(error|تنبيه|alert-danger|invalid)/i.test(head)) {
            return true;
        }
        return false;
    }

    function flushDeferredForms(opts) {
        opts = opts || {};
        if (flushing) {
            return Promise.resolve({ ok: 0, skipped: true });
        }
        // Flush when the browser has network — do not wait for soft badge.
        if (!opts.force && !browserHasNetwork()) {
            updateSyncBanner();
            return Promise.resolve({ ok: 0, offline: true });
        }
        flushing = true;
        return pullDeferredFromCaches().then(function (merged) {
            var list = merged || readDeferred();
            if (!list.length) {
                flushing = false;
                updateSyncBanner();
                return { ok: 0 };
            }
            var csrf = currentCsrf();
            var remain = [];
            var chain = Promise.resolve({ ok: 0, fail: 0 });

            list.forEach(function (entry) {
                chain = chain.then(function (stats) {
                    if (!entry || !entry.url || !entry.fields || !Object.keys(entry.fields).length) {
                        stats.fail += 1;
                        return stats;
                    }
                    var body = new FormData();
                    if (csrf) {
                        body.append('_csrf', csrf);
                    }
                    Object.keys(entry.fields).forEach(function (k) {
                        var v = entry.fields[k];
                        if (Array.isArray(v)) {
                            v.forEach(function (one) { body.append(k, one); });
                        } else {
                            body.append(k, v);
                        }
                    });
                    return root.fetch(entry.url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: body,
                        redirect: 'follow',
                        headers: { 'X-Rateb-Offline-Flush': '1' }
                    }).then(function (res) {
                        return res.text().then(function (html) {
                            if (postLooksFailed(res, html)) {
                                remain.push(entry);
                                stats.fail += 1;
                            } else {
                                stats.ok += 1;
                            }
                            return stats;
                        });
                    }).catch(function () {
                        remain.push(entry);
                        stats.fail += 1;
                        return stats;
                    });
                });
            });

            return chain.then(function (stats) {
                writeDeferred(remain);
                if (stats.ok > 0) {
                    toast('تمت مزامنة ' + stats.ok + ' تعديل وحفظه على السيرفر.');
                }
                if (stats.fail > 0 && stats.ok === 0) {
                    toast('تعذر مزامنة التعديلات — تأكد من تسجيل الدخول ثم اضغط «مزامنة الآن».', true);
                }
                return stats;
            });
        }).finally(function () {
            flushing = false;
            updateSyncBanner();
        });
    }

    function block(ev, reason) {
        ev.preventDefault();
        ev.stopPropagation();
        toast(reason, true);
    }

    function onClick(ev) {
        if (!isOffline()) {
            return;
        }
        var target = ev.target;
        if (!target || !target.closest) {
            return;
        }

        var a = target.closest('a[href]');
        if (a) {
            var href = a.getAttribute('href') || '';
                if (href && href !== '#' && !/^javascript:/i.test(href) && isMuteHref(href)) {
                block(ev, 'التصدير / المسح النهائي يحتاج اتصال بالإنترنت.');
                return;
            }
            if (!hasSwController() && href && /\/admin(\/|$)/i.test(href)) {
                try {
                    var u = new URL(a.href, root.location.href);
                    ev.preventDefault();
                    ev.stopPropagation();
                    var keys = [u.href, u.origin + u.pathname];
                    var p = Promise.resolve(null);
                    if (root.caches) {
                        keys.forEach(function (k) {
                            p = p.then(function (h) {
                                return h || root.caches.match(k).catch(function () { return null; });
                            });
                        });
                    }
                    p.then(function (res) {
                        if (!res) {
                            toast('افتح الصفحة وأنت متصل مرة ليُحفظ الشكل أوفلاين.', true);
                            return;
                        }
                        return res.text().then(function (html) {
                            if (!html || html.length < 400) {
                                toast('الصفحة غير محفوظة أوفلاين.', true);
                                return;
                            }
                            root.document.open();
                            root.document.write(html);
                            root.document.close();
                        });
                    });
                } catch (eNav) { /* ignore */ }
            }
            return;
        }

        var submitBtn = target.closest('button[type="submit"], input[type="submit"], [data-rateb-save], .btn-save');
        if (submitBtn) {
            var form = submitBtn.closest('form');
            if (form && String(form.getAttribute('method') || 'get').toLowerCase() === 'post') {
                if (formIsOnlineOnly(form)) {
                    block(ev, 'ترحيل القيود / إغلاق الفترة / المسح النهائي يحتاج إنترنت.');
                }
            }
        }
    }

    function onSubmit(ev) {
        if (!isOffline()) {
            return;
        }
        var form = ev.target;
        if (!form || form.tagName !== 'FORM') {
            return;
        }
        if (String(form.getAttribute('method') || 'get').toLowerCase() !== 'post') {
            return;
        }
        if (formIsOnlineOnly(form)) {
            block(ev, 'ترحيل القيود / إغلاق الفترة / المسح النهائي يحتاج إنترنت.');
            return;
        }

        try {
            if (root.RatebOfflineOpsForms && typeof root.RatebOfflineOpsForms.matchHook === 'function') {
                var path = (form.getAttribute('action') || '') + ' '
                    + ((root.location && root.location.pathname) || '');
                var hook = root.RatebOfflineOpsForms.matchHook(path)
                    || root.RatebOfflineOpsForms.matchHook(root.location && root.location.pathname);
                if (hook && typeof root.RatebOfflineOpsForms.isModuleEnabled === 'function'
                    && root.RatebOfflineOpsForms.isModuleEnabled(hook.module, hook.action)) {
                    return;
                }
            }
        } catch (eOps) { /* fall through */ }

        ev.preventDefault();
        ev.stopPropagation();
        try {
            var entry = deferHttpForm(form);
            toast('أُضيف للتعديل لقائمة الانتظار (' + readDeferred().length
                + ') — بعد الإنترنت اضغط «مزامنة الآن» أو انتظر المزامنة التلقائية.');
            updateSyncBanner();
            return entry;
        } catch (eDef) {
            toast('تعذر حفظ التعديل أوفلاين: ' + String(eDef && eDef.message ? eDef.message : eDef), true);
        }
    }

    function boot() {
        if (!root.document) {
            return;
        }
        try {
            root.__RATEB_NAV_GUARD_BUILD__ = GUARD_BUILD;
        } catch (eB) { /* ignore */ }
        ensureCss();
        clearStaleMarks();
        updateSyncBanner();
        root.document.addEventListener('click', onClick, true);
        root.document.addEventListener('submit', onSubmit, true);
        root.addEventListener('online', function () {
            clearStaleMarks();
            setTimeout(function () {
                flushDeferredForms({ force: true }).then(function (stats) {
                    if (stats && stats.ok > 0) {
                        try { root.location.reload(); } catch (eR) { /* ignore */ }
                    }
                });
            }, 600);
        });
        root.addEventListener('offline', function () {
            ensureCss();
            clearStaleMarks();
            updateSyncBanner();
        });
        root.document.addEventListener('rateb-connection-badge', function (ev) {
            clearStaleMarks();
            ensureCss();
            var online = false;
            try {
                online = !!(ev && ev.detail && ev.detail.online);
            } catch (eD) { /* ignore */ }
            if (online || browserHasNetwork()) {
                flushDeferredForms({ force: true });
            }
            updateSyncBanner();
        });
        root.document.addEventListener('rateb-offline-connectivity', function (ev) {
            try {
                if (ev && ev.detail && ev.detail.online) {
                    flushDeferredForms({ force: true });
                }
            } catch (eC) { /* ignore */ }
            updateSyncBanner();
        });
        setInterval(function () {
            clearStaleMarks();
            updateSyncBanner();
            if (browserHasNetwork() && readDeferred().length) {
                flushDeferredForms();
            }
        }, 8000);
        setTimeout(function () {
            pullDeferredFromCaches().then(function () {
                if (browserHasNetwork()) {
                    flushDeferredForms({ force: true });
                }
            });
            try {
                var q = String(root.location.search || '');
                if (/[?&]rateb_offline_saved=1(?:&|$)/.test(q)) {
                    pullDeferredFromCaches().then(function () {
                        toast('أُضيف التعديل لقائمة الانتظار (' + readDeferred().length
                            + ') — عند عودة النت سيُزامَن أو اضغط «مزامنة الآن».');
                        updateSyncBanner();
                    });
                    try {
                        var clean = new URL(root.location.href);
                        clean.searchParams.delete('rateb_offline_saved');
                        root.history.replaceState({}, '', clean.href);
                    } catch (eC) { /* ignore */ }
                }
                if (/[?&]rateb_offline_blocked=1(?:&|$)/.test(q)) {
                    toast('ترحيل القيود / إغلاق الفترة / المسح النهائي يحتاج إنترنت.', true);
                    try {
                        var clean2 = new URL(root.location.href);
                        clean2.searchParams.delete('rateb_offline_blocked');
                        root.history.replaceState({}, '', clean2.href);
                    } catch (eC2) { /* ignore */ }
                }
            } catch (eQ) { /* ignore */ }
        }, 500);

        try {
            if (root.navigator && root.navigator.serviceWorker) {
                root.navigator.serviceWorker.addEventListener('message', function (ev) {
                    try {
                        var data = ev.data || {};
                        if (data.type === 'RATEB_DEFERRED_POST' && data.entry) {
                            var list = readDeferred();
                            var exists = list.some(function (x) { return x && x.id === data.entry.id; });
                            if (!exists) {
                                list.push(data.entry);
                                writeDeferred(list);
                            }
                            updateSyncBanner();
                        }
                    } catch (eMsg) { /* ignore */ }
                });
            }
        } catch (eSw) { /* ignore */ }
    }

    root.RatebOfflineNavGuard = {
        scan: clearStaleMarks,
        refreshBanner: updateSyncBanner,
        isOffline: isOffline,
        flushDeferred: flushDeferredForms,
        deferredCount: function () { return readDeferred().length; },
        build: GUARD_BUILD
    };
    boot();
})(typeof window !== 'undefined' ? window : globalThis);
