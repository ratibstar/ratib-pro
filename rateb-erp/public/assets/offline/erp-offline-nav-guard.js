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
    var GUARD_BUILD = '20260714-save-fast-v50';
    var CACHE_NAMES = ['rateb-erp-ops-pages-v34', 'rateb-erp-coexist-v29'];
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

    function showSavedLikeOnline(message) {
        var msg = message || 'تم الحفظ بنجاح';
        try {
            root.document.querySelectorAll('.rateb-flash-offline-local').forEach(function (n) {
                n.parentNode && n.parentNode.removeChild(n);
            });
            var flash = root.document.createElement('div');
            flash.className = 'alert alert-success rateb-flash alert-dismissible fade show rateb-flash-offline-local';
            flash.setAttribute('role', 'alert');
            flash.innerHTML = String(msg).replace(/</g, '&lt;')
                + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            var host = root.document.querySelector('.rateb-main, main .container-fluid, main, .content-wrapper, .rateb-content')
                || root.document.body;
            if (host.firstChild) {
                host.insertBefore(flash, host.firstChild);
            } else {
                host.appendChild(flash);
            }
            try {
                flash.querySelector('.btn-close').addEventListener('click', function () {
                    if (flash.parentNode) {
                        flash.parentNode.removeChild(flash);
                    }
                });
            } catch (eBtn) { /* ignore */ }
        } catch (eF) { /* ignore */ }
        toast(msg);
    }

    function optimisticCacheDocument() {
        try {
            if (!root.caches || !root.document || !root.document.documentElement) {
                return;
            }
            var html = '<!DOCTYPE html>\n' + root.document.documentElement.outerHTML;
            if (html.length < 400 || html.length > 2500000) {
                return;
            }
            var keys = [
                root.location.href,
                root.location.origin + root.location.pathname,
                root.location.origin + root.location.pathname.replace(/\/+$/, ''),
                root.location.origin + root.location.pathname.replace(/\/+$/, '') + '/'
            ];
            var res = new Response(html, {
                status: 200,
                headers: { 'Content-Type': 'text/html; charset=utf-8', 'X-Rateb-Offline': '1' }
            });
            CACHE_NAMES.forEach(function (name) {
                root.caches.open(name).then(function (cache) {
                    return Promise.all(keys.map(function (k) {
                        return cache.put(k, res.clone()).catch(function () { return null; });
                    }));
                }).catch(function () { /* ignore */ });
            });
        } catch (eC) { /* ignore */ }
    }

    function extractRecordId(form) {
        try {
            var action = String(form.getAttribute('action') || root.location.pathname || '');
            var m = action.match(/\/(\d+)(?:\/(?:edit|update|delete|destroy|suspend))?\/?(?:\?|$)/i)
                || String(root.location.pathname || '').match(/\/(\d+)(?:\/edit)?\/?$/i);
            return m ? m[1] : '';
        } catch (e) {
            return '';
        }
    }

    function listUrlFromForm(form) {
        try {
            var cancel = form.querySelector('a.btn-outline-secondary[href], a[href*="/admin/"]');
            if (cancel && cancel.getAttribute('href')) {
                return new URL(cancel.getAttribute('href'), root.location.href).href;
            }
        } catch (e0) { /* ignore */ }
        try {
            var u = new URL(form.getAttribute('action') || root.location.href, root.location.href);
            u.pathname = u.pathname
                .replace(/\/\d+\/(edit|update|delete|destroy|suspend)\/?$/i, '')
                .replace(/\/\d+\/?$/i, '')
                .replace(/\/(create|new)\/?$/i, '');
            u.search = '';
            u.hash = '';
            return u.href;
        } catch (e1) {
            return '';
        }
    }

    function displayValueForField(form, name, raw) {
        try {
            var el = form.elements.namedItem(name);
            if (!el) {
                return raw;
            }
            if (el.tagName === 'SELECT') {
                var opt = el.options[el.selectedIndex];
                return opt ? String(opt.textContent || '').trim() : raw;
            }
            if (el.length && el[0] && el[0].type === 'radio') {
                for (var i = 0; i < el.length; i++) {
                    if (el[i].checked) {
                        var lab = root.document.querySelector('label[for="' + el[i].id + '"]');
                        return lab ? String(lab.textContent || '').trim() : String(el[i].value || raw);
                    }
                }
            }
        } catch (e) { /* ignore */ }
        return raw;
    }

    function patchIndexHtml(html, recordId, fields, form, isDelete) {
        if (!html || !recordId) {
            return null;
        }
        try {
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var rows = doc.querySelectorAll('table tbody tr, .table tbody tr');
            var patched = false;
            Array.prototype.forEach.call(rows, function (tr) {
                var blob = (tr.getAttribute('data-id') || '') + ' '
                    + (tr.innerHTML || '');
                if (blob.indexOf('/' + recordId + '/') === -1
                    && blob.indexOf('/' + recordId + '"') === -1
                    && String(tr.getAttribute('data-id') || '') !== String(recordId)) {
                    return;
                }
                patched = true;
                if (isDelete) {
                    if (tr.parentNode) {
                        tr.parentNode.removeChild(tr);
                    }
                    return;
                }
                Object.keys(fields || {}).forEach(function (name) {
                    if (name.charAt(0) === '_' || name === 'modules' || name === 'password') {
                        return;
                    }
                    var raw = fields[name];
                    if (Array.isArray(raw)) {
                        raw = raw.join(', ');
                    }
                    raw = String(raw == null ? '' : raw);
                    if (!raw) {
                        return;
                    }
                    var shown = displayValueForField(form, name, raw);
                    var cell = tr.querySelector('[data-field="' + name + '"], [data-col="' + name + '"], td[data-name="' + name + '"]');
                    if (cell) {
                        cell.textContent = shown;
                        return;
                    }
                    // Soft match: any short text cell equal to previous unknown — skip; fill empty name-like cells only via class
                    var named = tr.querySelector('.col-' + name + ', .field-' + name);
                    if (named) {
                        named.textContent = shown;
                    }
                });
                // Prefer common label cells: name / title / email / phone first text cells when form has them
                ['name', 'company_name', 'title', 'email', 'phone', 'status', 'package'].forEach(function (key) {
                    if (!fields[key]) {
                        return;
                    }
                    var shown = displayValueForField(form, key, String(fields[key]));
                    var tds = tr.querySelectorAll('td');
                    Array.prototype.forEach.call(tds, function (td) {
                        var cls = String(td.className || '');
                        if (cls.indexOf(key) !== -1 || td.getAttribute('data-label') === key) {
                            td.textContent = shown;
                        }
                    });
                });
            });
            if (!patched) {
                return null;
            }
            return '<!DOCTYPE html>\n' + doc.documentElement.outerHTML;
        } catch (eP) {
            return null;
        }
    }

    function patchRelatedIndexCaches(form, fields) {
        if (!root.caches) {
            return;
        }
        var recordId = extractRecordId(form);
        var listHref = listUrlFromForm(form);
        if (!listHref) {
            return;
        }
        var isDelete = false;
        try {
            var act = String(form.getAttribute('action') || '');
            isDelete = /\/(delete|destroy)(\/|$)/i.test(act)
                || form.getAttribute('data-rateb-bulk-form') === 'delete';
        } catch (eD) { /* ignore */ }
        var candidates = [listHref];
        try {
            var u = new URL(listHref);
            candidates.push(u.origin + u.pathname);
            candidates.push(u.origin + u.pathname.replace(/\/+$/, ''));
            candidates.push(u.origin + u.pathname.replace(/\/+$/, '') + '/');
        } catch (eU) { /* ignore */ }

        CACHE_NAMES.forEach(function (name) {
            root.caches.open(name).then(function (cache) {
                return Promise.all(candidates.map(function (key) {
                    return cache.match(key).then(function (res) {
                        if (!res) {
                            return null;
                        }
                        return res.text().then(function (html) {
                            var next = patchIndexHtml(html, recordId, fields, form, isDelete);
                            if (!next) {
                                return null;
                            }
                            return cache.put(key, new Response(next, {
                                status: 200,
                                headers: { 'Content-Type': 'text/html; charset=utf-8', 'X-Rateb-Offline': '1' }
                            }));
                        });
                    }).catch(function () { return null; });
                }));
            }).catch(function () { /* ignore */ });
        });
    }

    function afterDeferredSave(form, fields) {
        showSavedLikeOnline('تم الحفظ بنجاح');
        try {
            var act = String(form.getAttribute('action') || '');
            var isDelete = /\/(delete|destroy)(\/|$)/i.test(act)
                || form.getAttribute('data-rateb-bulk-form') === 'delete';
            if (isDelete) {
                var row = form.closest('tr');
                if (row) {
                    row.style.display = 'none';
                    row.setAttribute('data-rateb-offline-deleted', '1');
                }
            }
        } catch (eR) { /* ignore */ }
        // Persist current form values into Cache so reopen offline shows the save.
        optimisticCacheDocument();
        setTimeout(optimisticCacheDocument, 250);
        setTimeout(optimisticCacheDocument, 800);
        patchRelatedIndexCaches(form, fields || {});
        updateSyncBanner();
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
                + (browserHasNetwork()
                    ? ' — جاهزة للمزامنة'
                    : ' — محفوظة محلياً مثل الأونلاين؛ ستُزامَن عند عودة النت');
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
        // Never attempt server flush without browser network (avoids fake "login" errors).
        if (!browserHasNetwork()) {
            updateSyncBanner();
            if (opts.force) {
                toast('ما زلت بدون اتصال — التعديلات محفوظة محلياً وستُزامَن تلقائياً عند عودة الإنترنت.');
            }
            return Promise.resolve({ ok: 0, offline: true, offline: true });
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
            var chain = Promise.resolve({ ok: 0, fail: 0, netFail: 0 });

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
                        stats.netFail += 1;
                        return stats;
                    });
                });
            });

            return chain.then(function (stats) {
                writeDeferred(remain);
                if (stats.ok > 0) {
                    toast('تمت مزامنة ' + stats.ok + ' تعديل وحفظه على السيرفر.');
                }
                if (stats.netFail > 0 && stats.ok === 0) {
                    toast('انقطع الاتصال أثناء المزامنة — التعديلات ما زالت محفوظة محلياً.');
                } else if (stats.fail > 0 && stats.ok === 0 && browserHasNetwork()) {
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

        // Always queue here (do not yield to ops SDK) so Save persists into Cache + deferred
        // and re-open shows the saved values offline.
        ev.preventDefault();
        ev.stopPropagation();
        try {
            if (typeof ev.stopImmediatePropagation === 'function') {
                ev.stopImmediatePropagation();
            }
        } catch (eStop) { /* ignore */ }
        try {
            var entry = deferHttpForm(form);
            afterDeferredSave(form, entry.fields || {});
            return entry;
        } catch (eDef) {
            toast('تعذر حفظ التعديل أوفلاين: ' + String(eDef && eDef.message ? eDef.message : eDef), true);
        }
    }

    function applyFieldsToDomForm(form, fields) {
        if (!form || !fields) {
            return false;
        }
        var applied = false;
        Object.keys(fields).forEach(function (name) {
            if (!name || name.charAt(0) === '_') {
                return;
            }
            var val = fields[name];
            var el = form.elements.namedItem(name);
            if (!el) {
                return;
            }
            try {
                if (el.length != null && el[0] && (el[0].type === 'checkbox' || el[0].type === 'radio')) {
                    var want = Array.isArray(val) ? val.map(String) : [String(val)];
                    Array.prototype.forEach.call(el, function (one) {
                        one.checked = want.indexOf(String(one.value)) !== -1;
                    });
                    applied = true;
                    return;
                }
                if (el.type === 'checkbox') {
                    el.checked = !!val && String(val) !== '0';
                    applied = true;
                    return;
                }
                if (el.tagName === 'SELECT' && Array.isArray(val)) {
                    Array.prototype.forEach.call(el.options, function (opt) {
                        opt.selected = val.map(String).indexOf(String(opt.value)) !== -1;
                    });
                    applied = true;
                    return;
                }
                if (el.tagName === 'SELECT' || el.tagName === 'TEXTAREA' || el.tagName === 'INPUT') {
                    el.value = Array.isArray(val) ? String(val[0]) : String(val == null ? '' : val);
                    applied = true;
                }
            } catch (eSet) { /* ignore */ }
        });
        return applied;
    }

    function entryMatchesPath(entry, pathname) {
        if (!entry) {
            return false;
        }
        var here = String(pathname || '').replace(/\/+$/, '').toLowerCase();
        var candidates = [];
        try {
            if (entry.url) {
                candidates.push(new URL(entry.url, root.location.href).pathname);
            }
        } catch (eU) { /* ignore */ }
        if (entry.path) {
            candidates.push(String(entry.path));
        }
        for (var i = 0; i < candidates.length; i++) {
            var there = String(candidates[i] || '').replace(/\/+$/, '').toLowerCase();
            if (!there) {
                continue;
            }
            if (here === there) {
                return true;
            }
            // /purchase-requests/14/edit ↔ /purchase-requests/14
            if (here.replace(/\/(edit|update)$/i, '') === there.replace(/\/(edit|update)$/i, '')) {
                return true;
            }
            if (here.indexOf(there + '/') === 0 || there.indexOf(here + '/') === 0) {
                return true;
            }
            var idHere = (here.match(/\/(\d+)(?:\/(?:edit|update))?$/i) || [])[1];
            var idThere = (there.match(/\/(\d+)(?:\/(?:edit|update))?$/i) || [])[1];
            if (idHere && idThere && idHere === idThere) {
                var baseHere = here.replace(/\/\d+(?:\/(?:edit|update))?$/i, '');
                var baseThere = there.replace(/\/\d+(?:\/(?:edit|update))?$/i, '');
                if (baseHere === baseThere) {
                    return true;
                }
            }
        }
        return false;
    }

    function applyPendingDeferredToPage(opts) {
        opts = opts || {};
        var list = readDeferred();
        if (!list.length) {
            return false;
        }
        var pathname = (root.location && root.location.pathname) || '';
        var merged = {};
        var hit = false;
        list.forEach(function (entry) {
            if (entry && entry.fields && entryMatchesPath(entry, pathname)) {
                hit = true;
                Object.keys(entry.fields).forEach(function (k) {
                    merged[k] = entry.fields[k];
                });
            }
        });
        if (!hit) {
            // Also refresh list table cells from any deferred saves for this list URL.
            try {
                applyPendingDeferredToListDom(list, pathname);
            } catch (eList) { /* ignore */ }
            return false;
        }
        var any = false;
        try {
            root.document.querySelectorAll('form[method="post"]').forEach(function (form) {
                if (formIsOnlineOnly(form)) {
                    return;
                }
                if (applyFieldsToDomForm(form, merged)) {
                    any = true;
                }
            });
        } catch (eF) { /* ignore */ }
        try {
            applyPendingDeferredToListDom(list, pathname);
        } catch (eL2) { /* ignore */ }
        if (any) {
            if (!opts.silent) {
                showSavedLikeOnline('عرض النسخة المحفوظة محلياً — بانتظار المزامنة');
            }
            setTimeout(function () {
                optimisticCacheDocument();
            }, 50);
            setTimeout(function () {
                optimisticCacheDocument();
            }, 400);
        }
        return any;
    }

    function applyPendingDeferredToListDom(list, pathname) {
        var path = String(pathname || '').replace(/\/+$/, '');
        if (/\/\d+(?:\/(?:edit|update|show))?$/i.test(path)) {
            return;
        }
        (list || []).forEach(function (entry) {
            if (!entry || !entry.fields) {
                return;
            }
            var id = '';
            try {
                id = extractRecordId({
                    getAttribute: function (n) {
                        return n === 'action' ? (entry.url || '') : null;
                    }
                });
            } catch (eId) {
                id = '';
            }
            if (!id) {
                return;
            }
            var rows = root.document.querySelectorAll('table tbody tr, .table tbody tr');
            Array.prototype.forEach.call(rows, function (tr) {
                var blob = (tr.getAttribute('data-id') || '') + ' ' + (tr.innerHTML || '');
                if (blob.indexOf('/' + id + '/') === -1
                    && blob.indexOf('/' + id + '"') === -1
                    && String(tr.getAttribute('data-id') || '') !== String(id)) {
                    return;
                }
                ['title', 'name', 'company_name', 'email', 'phone', 'status', 'reference', 'department'].forEach(function (key) {
                    if (entry.fields[key] == null || entry.fields[key] === '') {
                        return;
                    }
                    var shown = String(Array.isArray(entry.fields[key]) ? entry.fields[key][0] : entry.fields[key]);
                    var cell = tr.querySelector('[data-field="' + key + '"], td[data-label="' + key + '"], .col-' + key);
                    if (cell) {
                        cell.textContent = shown;
                    }
                });
            });
        });
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
        // Restore locally-saved field values when reopening edit/create offline.
        setTimeout(function () {
            pullDeferredFromCaches().then(function () {
                applyPendingDeferredToPage({ silent: false });
            });
        }, 200);
        setTimeout(function () {
            applyPendingDeferredToPage({ silent: true });
        }, 900);
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
            if (online && browserHasNetwork()) {
                flushDeferredForms({ force: true });
            }
            updateSyncBanner();
        });
        root.document.addEventListener('rateb-offline-connectivity', function (ev) {
            try {
                if (ev && ev.detail && ev.detail.online && browserHasNetwork()) {
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
                    pullDeferredFromCaches().then(function (list) {
                        applyPendingDeferredToPage({ silent: true });
                        showSavedLikeOnline('تم الحفظ بنجاح');
                        updateSyncBanner();
                        optimisticCacheDocument();
                        try {
                            var last = null;
                            var rows = list || readDeferred();
                            for (var i = rows.length - 1; i >= 0; i--) {
                                if (rows[i] && rows[i].fields) {
                                    last = rows[i];
                                    break;
                                }
                            }
                            if (last) {
                                var stub = {
                                    getAttribute: function (n) {
                                        if (n === 'action') {
                                            return last.url || '';
                                        }
                                        return null;
                                    },
                                    elements: { namedItem: function () { return null; } },
                                    querySelector: function () { return null; },
                                    closest: function () { return null; }
                                };
                                patchRelatedIndexCaches(stub, last.fields);
                            }
                        } catch (eIdx) { /* ignore */ }
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
                            applyPendingDeferredToPage({ silent: true });
                        }
                    } catch (eMsg) { /* ignore */ }
                });
            }
        } catch (eSw) { /* ignore */ }
    }

    root.RatebOfflineNavGuard = {
        scan: clearStaleMarks,
        refreshBanner: updateSyncBanner,
        applyPending: applyPendingDeferredToPage,
        isOffline: isOffline,
        flushDeferred: flushDeferredForms,
        deferredCount: function () { return readDeferred().length; },
        build: GUARD_BUILD
    };
    boot();
})(typeof window !== 'undefined' ? window : globalThis);
