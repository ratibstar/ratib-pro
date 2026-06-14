(function () {
    'use strict';

    function parseNum(val) {
        var n = parseFloat(val);
        return isNaN(n) ? 0 : n;
    }

    function lineTotals(row) {
        var qty = parseNum(row.querySelector('[name="line_quantity[]"]')?.value);
        var price = parseNum(row.querySelector('[name="line_unit_price[]"]')?.value);
        var taxRate = parseNum(row.querySelector('[data-line-tax-rate]')?.value);
        var excluding = (row.querySelector('[name="line_excluding_tax[]"]')?.value || '1') === '1';
        qty = Math.max(qty, 0.001);
        var base = Math.round(qty * price * 100) / 100;
        var tax = 0;
        var total = base;
        if (taxRate > 0) {
            if (excluding) {
                tax = Math.round(base * (taxRate / 100) * 100) / 100;
                total = Math.round((base + tax) * 100) / 100;
            } else {
                tax = Math.round((base - (base / (1 + (taxRate / 100)))) * 100) / 100;
                total = base;
            }
        }
        return { subtotal: excluding ? base : (base - tax), tax: tax, total: total };
    }

    function updateRowTotal(row) {
        var totals = lineTotals(row);
        var el = row.querySelector('[data-line-total]');
        if (el) {
            el.textContent = totals.total.toFixed(2);
        }
        return totals;
    }

    function updateTableTotals(table) {
        var subtotal = 0;
        var tax = 0;
        var grand = 0;
        table.querySelectorAll('[data-line-items-row]').forEach(function (row) {
            var t = updateRowTotal(row);
            subtotal += t.subtotal;
            tax += t.tax;
            grand += t.total;
        });
        var form = table.closest('[data-procurement-form]');
        var subEl = table.querySelector('[data-procurement-subtotal]');
        var taxEl = table.querySelector('[data-procurement-tax]');
        var grandEl = table.querySelector('[data-procurement-grand-total]');
        if (subEl) { subEl.textContent = subtotal.toFixed(2); }
        if (taxEl) { taxEl.textContent = tax.toFixed(2); }
        if (grandEl) { grandEl.textContent = grand.toFixed(2); }
        if (form) {
            var totalField = form.querySelector('[data-procurement-total-field]');
            if (totalField) {
                totalField.value = grand.toFixed(2);
            }
        }
    }

    function syncTaxRate(select) {
        var opt = select.options[select.selectedIndex];
        var rate = opt ? opt.getAttribute('data-tax-rate') : '0';
        var row = select.closest('[data-line-items-row]');
        if (!row) {
            return;
        }
        var hidden = row.querySelector('[data-line-tax-rate]');
        if (hidden) {
            hidden.value = rate || '0';
        }
    }

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
            } else if (input.type === 'hidden' && input.hasAttribute('data-line-tax-rate')) {
                input.value = '0';
            } else if (input.type !== 'hidden') {
                input.value = '';
            }
        });
        clone.querySelectorAll('select').forEach(function (sel) {
            if (sel.name === 'line_unit[]') {
                sel.selectedIndex = 0;
            } else if (sel.name === 'line_tax_name[]') {
                sel.selectedIndex = 0;
                syncTaxRate(sel);
            } else if (sel.name === 'line_excluding_tax[]') {
                sel.value = '1';
            }
        });
        var totalEl = clone.querySelector('[data-line-total]');
        if (totalEl) {
            totalEl.textContent = '0.00';
        }
        tbody.appendChild(clone);
        updateTableTotals(table);
    }

    function bindTable(table) {
        var addBtn = document.querySelector('[data-line-items-add]');
        if (addBtn && !addBtn._bound) {
            addBtn._bound = true;
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
                updateTableTotals(table);
            }
        });
        table.addEventListener('input', function (e) {
            if (e.target.matches('[data-line-calc]')) {
                updateTableTotals(table);
            }
        });
        table.addEventListener('change', function (e) {
            if (e.target.matches('[data-line-tax-preset]')) {
                syncTaxRate(e.target);
                updateTableTotals(table);
            }
            if (e.target.matches('[data-line-calc]')) {
                updateTableTotals(table);
            }
        });
        table.querySelectorAll('[data-line-tax-preset]').forEach(syncTaxRate);
        updateTableTotals(table);
    }

    function bindNotes() {
        document.querySelectorAll('[data-procurement-notes]').forEach(function (wrap) {
            var input = wrap.querySelector('[data-notes-input]');
            var counter = wrap.querySelector('[data-notes-counter]');
            if (!input || !counter) {
                return;
            }
            var max = parseInt(input.getAttribute('maxlength') || '2000', 10);
            function refresh() {
                counter.textContent = input.value.length + ' / ' + max;
            }
            input.addEventListener('input', refresh);
            refresh();
            wrap.querySelectorAll('[data-notes-template]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var text = btn.getAttribute('data-notes-template') || '';
                    if (!text) {
                        return;
                    }
                    if (input.value.trim() !== '') {
                        input.value = input.value.trimEnd() + '\n' + text;
                    } else {
                        input.value = text;
                    }
                    refresh();
                });
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-line-items-table]').forEach(bindTable);
        bindNotes();
    });
})();
