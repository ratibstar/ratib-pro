(function () {
    'use strict';

    function bulkTableForDeleteForm(form) {
        var host = form.closest('.rateb-card') || form.closest('.rateb-card-body') || form.parentElement;
        if (!host) {
            return document.querySelector('[data-rateb-bulk-table="1"]');
        }
        return host.querySelector('[data-rateb-bulk-table="1"]')
            || document.querySelector('[data-rateb-bulk-table="1"]');
    }

    function selectedRowIds(table) {
        var ids = [];
        if (!table) {
            return ids;
        }
        table.querySelectorAll('[data-rateb-row-check]:checked').forEach(function (cb) {
            ids.push(cb.value);
        });
        return ids;
    }

    function submitBulkDelete(action, csrf, ids) {
        var fd = new FormData();
        fd.append('_csrf', csrf);
        ids.forEach(function (id) {
            fd.append('ids[]', id);
        });
        fetch(action, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            redirect: 'follow'
        }).then(function (resp) {
            if (resp.redirected && resp.url) {
                window.location.href = resp.url;
                return;
            }
            window.location.reload();
        }).catch(function () {
            var f = document.createElement('form');
            f.method = 'post';
            f.action = action;
            f.style.display = 'none';
            var csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_csrf';
            csrfInput.value = csrf;
            f.appendChild(csrfInput);
            ids.forEach(function (id) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = id;
                f.appendChild(input);
            });
            document.body.appendChild(f);
            f.submit();
        });
    }

    function runBulkDeleteFromForm(form) {
        var ids = selectedRowIds(bulkTableForDeleteForm(form));
        if (ids.length === 0) {
            return;
        }
        var csrfEl = form.querySelector('input[name="_csrf"]');
        var csrf = csrfEl ? csrfEl.value : '';
        var action = form.getAttribute('action') || '';
        if (!action || !csrf) {
            return;
        }
        var msg = form.getAttribute('data-rateb-bulk-confirm') || '';
        var doPost = function () {
            submitBulkDelete(action, csrf, ids);
        };
        if (!msg) {
            doPost();
            return;
        }
        var promise = window.ratebConfirm
            ? window.ratebConfirm(msg, { variant: 'danger' })
            : Promise.resolve(window.confirm(msg));
        promise.then(function (ok) {
            if (ok) {
                doPost();
            }
        });
    }

    function init() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-rateb-bulk-delete-btn]');
            if (!btn) {
                return;
            }
            var form = btn.closest('form[data-rateb-bulk-form]');
            if (!form) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            runBulkDeleteFromForm(form);
        }, true);
    }

    window.ratebRunBulkDelete = runBulkDeleteFromForm;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
