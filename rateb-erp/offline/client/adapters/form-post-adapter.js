/**
 * RATEB Offline — Safe generic form-post enqueue (Phase 4 expansion).
 * Maps unmatched allowlisted forms to known replay actions via ops form_hooks.
 * Deny-list: period close / wipe / GL journal post / payroll calc. Approve/delete/pay queue offline.
 */
(function (root) {
    'use strict';

    var DENY_RE = /(?:close[-_]?period|wipe|payroll[-_]?calc|transfer[-_]?funds|void[-_]?payment|gl[-_]?post|journal[-_]?post|journal-entries\/\d+\/(?:post|void)|delete[-_]?permanent)/i;

    function cfg() {
        return root.__RATEB_ERP_SHELL_OFFLINE__ || root.__RATEB_ERP_MASTER_DATA__ || {};
    }

    function flags() {
        if (root.RatebOffline && typeof root.RatebOffline.flags === 'function') {
            return root.RatebOffline.flags() || {};
        }
        return cfg().flags || {};
    }

    function isActive() {
        var f = flags();
        return !!(f['offline.enabled'] && (
            f['offline.pilot.ops_pages']
            || f['offline.inventory.movements']
            || f['offline.hr.attendance']
            || f['offline.procurement']
        ));
    }

    function isOnline() {
        var conn = root.RatebOfflineConnectivity;
        if (conn && typeof conn.isOnline === 'function') {
            return !!conn.isOnline();
        }
        return typeof navigator === 'undefined' || navigator.onLine !== false;
    }

    function hooks() {
        var list = cfg().ops_form_hooks;
        return Array.isArray(list) && list.length ? list : [];
    }

    function matchHook(pathname) {
        var p = String(pathname || '').toLowerCase();
        var list = hooks().slice().sort(function (a, b) {
            return String(b.match || '').length - String(a.match || '').length;
        });
        for (var i = 0; i < list.length; i++) {
            var m = String(list[i].match || '').toLowerCase();
            if (!m) {
                continue;
            }
            if (p.indexOf(m) !== -1) {
                return list[i];
            }
        }
        return null;
    }

    function formDenied(form) {
        if (!form) {
            return true;
        }
        if (form.getAttribute('data-rateb-offline-online-only') === '1') {
            return true;
        }
        var blob = [
            form.getAttribute('action') || '',
            form.getAttribute('id') || '',
            form.getAttribute('name') || '',
            form.getAttribute('data-action') || '',
            form.className || ''
        ].join(' ');
        return DENY_RE.test(blob);
    }

    function serializeForm(form) {
        var data = {};
        if (!form || !form.elements) {
            return data;
        }
        Array.prototype.forEach.call(form.elements, function (el) {
            if (!el || !el.name || el.disabled) {
                return;
            }
            var name = String(el.name);
            if (/^_csrf$/i.test(name) || /token/i.test(name)) {
                return;
            }
            var type = String(el.type || '').toLowerCase();
            if (type === 'submit' || type === 'button' || type === 'file' || type === 'password') {
                return;
            }
            if ((type === 'checkbox' || type === 'radio') && !el.checked) {
                return;
            }
            data[name] = el.value;
        });
        return data;
    }

    function enqueueGeneric(hook, payload) {
        var q = root.RatebOfflineQueue;
        if (!q || typeof q.enqueue !== 'function') {
            return Promise.reject(new Error('queue_unavailable'));
        }
        var Idem = root.RatebOfflineIdempotency;
        var key = Idem && typeof Idem.createKey === 'function'
            ? Idem.createKey(hook.module, hook.action)
            : ('offline:' + hook.module + ':' + hook.action + ':' + Date.now());
        return q.enqueue({
            module: String(hook.module || 'ops'),
            action: String(hook.action || 'form.draft'),
            payload: payload || {},
            idempotency_key: key,
            client_uuid: key
        });
    }

    function handleSubmit(ev) {
        if (!isActive() || isOnline()) {
            return;
        }
        var form = ev.target && ev.target.closest ? ev.target.closest('form') : null;
        if (!form) {
            return;
        }
        if (form.getAttribute('data-rateb-offline-writable') !== '1'
            && form.getAttribute('data-rateb-form-post') !== '1') {
            return;
        }
        if (formDenied(form)) {
            try {
                ev.preventDefault();
                ev.stopPropagation();
            } catch (e0) { /* ignore */ }
            try {
                root.alert('هذا الإجراء يتطلب اتصالاً (ترحيل / اعتماد / دفع).');
            } catch (e1) { /* ignore */ }
            return;
        }
        // Prefer dedicated ops-forms adapter when present.
        if (root.RatebOfflineOpsForms && form.getAttribute('data-rateb-ops-forms-handled') === '1') {
            return;
        }
        var path = (root.location && root.location.pathname) || '';
        var hook = matchHook(path);
        if (!hook) {
            return;
        }
        try {
            ev.preventDefault();
            ev.stopPropagation();
        } catch (e2) { /* ignore */ }
        var payload = serializeForm(form);
        payload._offline_path = path;
        payload._offline_generic = true;
        enqueueGeneric(hook, payload).then(function () {
            try {
                var Events = root.RatebOfflineEvents;
                if (Events && typeof Events.emit === 'function') {
                    Events.emit('queue:enqueued', { module: hook.module, action: hook.action });
                }
            } catch (e3) { /* ignore */ }
            try {
                root.alert('تم حفظ المسودة أوفلاين — ستُزامَن عند عودة الاتصال.');
            } catch (e4) { /* ignore */ }
        }).catch(function (err) {
            try {
                root.alert('تعذر وضع العملية في قائمة الانتظار: ' + String(err && err.message ? err.message : err));
            } catch (e5) { /* ignore */ }
        });
    }

    function bind() {
        if (!isActive() || !root.document) {
            return;
        }
        if (root.document.documentElement.getAttribute('data-rateb-form-post-bound') === '1') {
            return;
        }
        root.document.documentElement.setAttribute('data-rateb-form-post-bound', '1');
        root.document.addEventListener('submit', handleSubmit, true);
    }

    root.RatebOfflineFormPostAdapter = {
        isActive: isActive,
        bind: bind,
        matchHook: matchHook,
        formDenied: formDenied,
        capture: function (form) {
            if (!isActive()) {
                return Promise.reject(new Error('form_post_offline_disabled'));
            }
            if (formDenied(form)) {
                return Promise.reject(new Error('form_post_online_only'));
            }
            var path = (root.location && root.location.pathname) || '';
            var hook = matchHook(path);
            if (!hook) {
                return Promise.reject(new Error('form_post_no_hook'));
            }
            return enqueueGeneric(hook, serializeForm(form));
        }
    };

    if (root.document) {
        if (root.document.readyState === 'loading') {
            root.document.addEventListener('DOMContentLoaded', bind, { once: true });
        } else {
            setTimeout(bind, 0);
        }
    }
})(typeof window !== 'undefined' ? window : globalThis);
