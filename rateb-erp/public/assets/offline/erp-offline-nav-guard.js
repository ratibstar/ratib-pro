/**
 * RATEB ERP — Offline nav + deferred form save.
 * - Table create/edit links: native navigation when SW controls (no history.back hijack).
 * - POST save offline: queue locally → auto POST when online (companies + ops drafts).
 * - Deny: delete / export / pay / final-approve / suspend.
 */
(function (root) {
    'use strict';

    var STYLE_ID = 'rateb-offline-nav-guard-css';
    var MUTE_NAV_RE = /\/(delete|destroy|export|pdf|excel|csv|json|regenerate|suspend|wipe)(\/|$|\?)/i;
    var ONLINE_ONLY_RE = /(?:post|reverse|close[-_]?period|final[-_]?approve|decide|escalate|pay(?:ment)?|payroll[-_]?calc|transfer[-_]?funds|void[-_]?payment|gl[-_]?post|journal[-_]?post|submit-approval|convert-to-po)(\/|$|\?)/i;
    var DEFERRED_KEY = 'rateb_deferred_http_forms_v1';
    var GUARD_BUILD = '20260714-deferred-forms-v40';

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
            + 'a.rateb-offline-missing{opacity:1!important;cursor:pointer!important;pointer-events:auto!important;}';
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
            }, 4800);
        } catch (e2) { /* ignore */ }
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
        var entry = {
            id: 'df-' + Date.now() + '-' + Math.floor(Math.random() * 1e6),
            url: absolute,
            path: (root.location && root.location.pathname) || '',
            fields: serializeFormFields(form),
            title: (root.document && root.document.title) || '',
            created_at: Date.now()
        };
        var list = readDeferred();
        list.push(entry);
        writeDeferred(list);

        // Also mirror into enterprise queue when present (visible in offline queue UIs).
        try {
            var q = root.RatebOfflineQueue;
            if (q && typeof q.enqueue === 'function') {
                q.enqueue({
                    module: 'offline_meta',
                    action: 'offline.http_form',
                    payload: entry,
                    idempotency_key: entry.id,
                    client_uuid: entry.id
                }).catch(function () { /* localStorage already holds it */ });
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

    function flushDeferredForms() {
        if (isOffline()) {
            return Promise.resolve({ ok: 0 });
        }
        var list = readDeferred();
        if (!list.length) {
            return Promise.resolve({ ok: 0 });
        }
        var csrf = currentCsrf();
        var remain = [];
        var chain = Promise.resolve({ ok: 0, fail: 0 });

        list.forEach(function (entry) {
            chain = chain.then(function (stats) {
                if (!entry || !entry.url || !entry.fields) {
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
                    redirect: 'follow'
                }).then(function (res) {
                    if (res && (res.ok || res.redirected || res.status === 302 || res.status === 303)) {
                        stats.ok += 1;
                    } else {
                        remain.push(entry);
                        stats.fail += 1;
                    }
                    return stats;
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
                toast('تمت مزامنة ' + stats.ok + ' نموذج محفوظ أوفلاين.');
            }
            return stats;
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

        // Never hijack table create/edit when SW can serve pages.
        // (Previous history.back broke purchase-request / companies table buttons.)

        var a = target.closest('a[href]');
        if (a) {
            var href = a.getAttribute('href') || '';
            if (href && href !== '#' && !/^javascript:/i.test(href) && isMuteHref(href)) {
                block(ev, 'الحذف / التصدير / التعليق يحتاج اتصال بالإنترنت.');
                return;
            }
            // Uncontrolled tab only: soft-open from Cache API (prevent Chrome interstitial).
            if (!hasSwController() && href && /\/admin(\/|$)/i.test(href)) {
                // Let create/edit navigate if SW will load later — only intercept when no SW at all.
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
                    block(ev, 'هذا الإجراء يحتاج إنترنت (ترحيل / اعتماد نهائي / دفع / حذف).');
                }
                // Otherwise allow click → submit handler queues draft.
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
            block(ev, 'هذا الإجراء يحتاج إنترنت (ترحيل / اعتماد نهائي / دفع / حذف).');
            return;
        }

        // Prefer dedicated ops adapters when they claim the form.
        try {
            if (root.RatebOfflineOpsForms && typeof root.RatebOfflineOpsForms.matchHook === 'function') {
                var path = (form.getAttribute('action') || '') + ' '
                    + ((root.location && root.location.pathname) || '');
                var hook = root.RatebOfflineOpsForms.matchHook(path)
                    || root.RatebOfflineOpsForms.matchHook(root.location && root.location.pathname);
                if (hook && typeof root.RatebOfflineOpsForms.isModuleEnabled === 'function'
                    && root.RatebOfflineOpsForms.isModuleEnabled(hook.module, hook.action)) {
                    // Let ops-forms-adapter handle enqueue.
                    return;
                }
            }
        } catch (eOps) { /* fall through to deferred HTTP */ }

        ev.preventDefault();
        ev.stopPropagation();
        try {
            deferHttpForm(form);
            toast('تم حفظ النموذج أوفلاين — يُرسل تلقائياً عند عودة الاتصال.');
        } catch (eDef) {
            toast('تعذر الحفظ أوفلاين: ' + String(eDef && eDef.message ? eDef.message : eDef), true);
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
        root.document.addEventListener('click', onClick, true);
        root.document.addEventListener('submit', onSubmit, true);
        root.addEventListener('online', function () {
            clearStaleMarks();
            setTimeout(function () { flushDeferredForms(); }, 800);
        });
        root.addEventListener('offline', function () {
            ensureCss();
            clearStaleMarks();
        });
        root.document.addEventListener('rateb-connection-badge', function () {
            clearStaleMarks();
            ensureCss();
            if (!isOffline()) {
                flushDeferredForms();
            }
        });
        setInterval(clearStaleMarks, 5000);
        setTimeout(function () {
            if (!isOffline()) {
                flushDeferredForms();
            }
        }, 1500);
    }

    root.RatebOfflineNavGuard = {
        scan: clearStaleMarks,
        isOffline: isOffline,
        flushDeferred: flushDeferredForms,
        deferredCount: function () { return readDeferred().length; },
        build: GUARD_BUILD
    };
    boot();
})(typeof window !== 'undefined' ? window : globalThis);
