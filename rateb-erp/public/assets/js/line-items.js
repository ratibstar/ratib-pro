(function () {
    'use strict';

    function cloneRow(table) {
        var tbody = table.querySelector('tbody');
        var template = tbody.querySelector('[data-line-items-row]');
        if (!template) {
            return;
        }
        var clone = template.cloneNode(true);
        clone.querySelectorAll('input').forEach(function (input) {
            if (input.type === 'number') {
                input.value = input.name.indexOf('quantity') >= 0 ? '1' : '0';
            } else {
                input.value = input.name.indexOf('unit') >= 0 && input.name.indexOf('price') < 0 ? 'unit' : '';
            }
        });
        tbody.appendChild(clone);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-line-items-table]').forEach(function (table) {
            var addBtn = document.querySelector('[data-line-items-add]');
            if (addBtn) {
                addBtn.addEventListener('click', function () {
                    cloneRow(table);
                });
            }
            table.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-line-items-remove]');
                if (!btn) {
                    return;
                }
                var row = btn.closest('[data-line-items-row]');
                var rows = table.querySelectorAll('[data-line-items-row]');
                if (row && rows.length > 1) {
                    row.remove();
                }
            });
        });
    });
})();
