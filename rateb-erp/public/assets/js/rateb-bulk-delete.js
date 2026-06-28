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

    function submitBulkDelete(form, ids) {
        form.querySelectorAll('input[name="ids[]"]').forEach(function (el) {
            el.remove();
        });
        ids.forEach(function (id) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = id;
            form.appendChild(input);
        });
        HTMLFormElement.prototype.submit.call(form);
    }

    function runBulkDeleteFromForm(form) {
        var ids = selectedRowIds(bulkTableForDeleteForm(form));
        if (ids.length === 0) {
            return;
        }
        var msg = form.getAttribute('data-rateb-bulk-confirm') || '';
        var doPost = function () {
            submitBulkDelete(form, ids);
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
