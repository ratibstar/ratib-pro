/**
 * Role lock UI: expand permissions panel + AJAX save when nested inside user forms.
 */
(function () {
    if (window.__RATEB_ROLE_LOCK_UI__) {
        return;
    }
    window.__RATEB_ROLE_LOCK_UI__ = 1;

    document.addEventListener('click', function (ev) {
        var toggle = ev.target && ev.target.closest ? ev.target.closest('[data-role-lock-toggle]') : null;
        if (toggle) {
            var id = toggle.getAttribute('data-bs-target') || '';
            var panel = id ? document.querySelector(id) : null;
            if (!panel) {
                return;
            }
            ev.preventDefault();
            var open = panel.classList.toggle('show');
            var roleId = toggle.getAttribute('data-role-lock-toggle') || '';
            document.querySelectorAll('[data-role-lock-toggle="' + roleId + '"]').forEach(function (btn) {
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                var icon = btn.querySelector('i.fas');
                if (icon) {
                    icon.classList.toggle('fa-lock', !open);
                    icon.classList.toggle('fa-lock-open', open);
                }
            });
            return;
        }

        var saveBtn = ev.target && ev.target.closest ? ev.target.closest('[data-role-lock-save]') : null;
        if (!saveBtn) {
            return;
        }
        ev.preventDefault();
        var roleId = saveBtn.getAttribute('data-role-lock-save') || '';
        var wrap = saveBtn.closest('[data-role-lock-form]');
        if (!wrap) {
            return;
        }
        var action = wrap.getAttribute('data-save-action') || '';
        var csrf = wrap.getAttribute('data-csrf') || '';
        var scope = wrap.getAttribute('data-scope') || 'platform';
        if (!action || !csrf) {
            return;
        }
        var fd = new FormData();
        fd.append('_csrf', csrf);
        fd.append('scope', scope);
        wrap.querySelectorAll('input.rateb-matrix-check[data-perm-id]:checked').forEach(function (el) {
            fd.append('permission_ids[]', el.getAttribute('data-perm-id') || el.value);
        });
        saveBtn.disabled = true;
        fetch(action, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }
        }).then(function (res) {
            return res.json().catch(function () {
                return { ok: res.ok };
            }).then(function (data) {
                if (!res.ok && !(data && data.ok)) {
                    throw new Error('save_failed');
                }
                var count = (data && typeof data.count === 'number')
                    ? data.count
                    : wrap.querySelectorAll('input.rateb-matrix-check[data-perm-id]:checked').length;
                var badge = document.querySelector('[data-role-count="' + roleId + '"]');
                if (badge) {
                    var rest = badge.textContent.replace(/^\s*\d+\s*/, '');
                    badge.textContent = String(count) + (rest ? (' ' + rest) : '');
                }
                var ok = document.querySelector('[data-role-lock-ok="' + roleId + '"]');
                if (ok) {
                    ok.classList.remove('d-none');
                    setTimeout(function () { ok.classList.add('d-none'); }, 2500);
                }
            });
        }).catch(function () {
            alert('تعذر حفظ صلاحيات الدور');
        }).finally(function () {
            saveBtn.disabled = false;
        });
    }, true);
})();
