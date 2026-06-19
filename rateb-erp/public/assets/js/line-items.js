(function () {
    'use strict';

    function parseNum(val) {
        if (val === null || val === undefined) {
            return 0;
        }
        var cleaned = String(val).replace(/,/g, '').trim();
        var n = parseFloat(cleaned);
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
        return { subtotal: excluding ? base : (base - tax), tax: tax, total: total, base: base };
    }

    function updateUnitHint(row) {
        var hint = row.querySelector('[data-unit-hint]');
        var qtyEl = row.querySelector('[name="line_quantity[]"]');
        var unitEl = row.querySelector('[data-line-unit]');
        if (!hint || !qtyEl || !unitEl) {
            return;
        }
        var qty = parseNum(qtyEl.value);
        var opt = unitEl.options[unitEl.selectedIndex];
        var factor = parseNum(opt ? opt.getAttribute('data-factor') : '1') || 1;
        var eachQty = Math.round(qty * factor * 100) / 100;
        var template = hint.getAttribute('data-hint-template') || hint.textContent || '';
        if (!hint.getAttribute('data-hint-template')) {
            hint.setAttribute('data-hint-template', template.replace(/[\d.]+/, ':qty'));
        }
        template = hint.getAttribute('data-hint-template') || '= :qty Each';
        hint.textContent = template.replace(':qty', eachQty.toFixed(2));
    }

    function updateRowTotal(row) {
        var totals = lineTotals(row);
        var subEl = row.querySelector('[data-line-subtotal]');
        var totalEl = row.querySelector('[data-line-total]');
        if (subEl) {
            subEl.textContent = totals.base.toFixed(2);
        }
        if (totalEl) {
            totalEl.textContent = totals.total.toFixed(2);
        }
        updateUnitHint(row);
        return totals;
    }

    function isManualTotal(form) {
        if (!form) {
            return false;
        }
        var cb = form.querySelector('[data-total-estimated-manual]');
        return !!(cb && cb.checked);
    }

    function applyEstimatedTotalMode(form) {
        if (!form) {
            return;
        }
        var wrap = form.querySelector('[data-procurement-estimated-total]');
        if (!wrap) {
            return;
        }
        var manual = isManualTotal(form);
        var input = wrap.querySelector('[data-procurement-total-field]');
        var hintAuto = wrap.querySelector('[data-estimated-total-hint-auto]');
        var hintManual = wrap.querySelector('[data-estimated-total-hint-manual]');
        if (input) {
            input.readOnly = !manual;
        }
        if (hintAuto) {
            hintAuto.style.display = manual ? 'none' : '';
        }
        if (hintManual) {
            hintManual.style.display = manual ? '' : 'none';
        }
        if (!manual) {
            var table = form.querySelector('[data-line-items-table]');
            if (table) {
                updateTableTotals(table);
            }
        }
    }

    function bindEstimatedTotal() {
        document.querySelectorAll('[data-procurement-estimated-total]').forEach(function (wrap) {
            var form = wrap.closest('[data-procurement-form]');
            var cb = wrap.querySelector('[data-total-estimated-manual]');
            if (!cb || cb.dataset.estimatedBound === '1') {
                return;
            }
            cb.dataset.estimatedBound = '1';
            cb.addEventListener('change', function () {
                applyEstimatedTotalMode(form);
            });
            applyEstimatedTotalMode(form);
        });
    }

    function syncEstimatedCurrency(form) {
        if (!form) {
            return;
        }
        var currencyEl = form.querySelector('[name="currency"]');
        var suffix = form.querySelector('[data-estimated-total-currency]');
        if (!currencyEl || !suffix) {
            return;
        }
        suffix.textContent = '(' + (currencyEl.value || 'SAR') + ')';
    }

    function renumberLineRows(table) {
        table.querySelectorAll('[data-line-items-row]').forEach(function (row, index) {
            var num = row.querySelector('[data-line-number]');
            if (num) {
                num.textContent = String(index + 1);
            }
        });
    }

    function updateStockHint(select) {
        var row = select.closest('[data-line-items-row]');
        if (!row) {
            return;
        }
        var hint = row.querySelector('[data-stock-hint]');
        if (!hint) {
            return;
        }
        var opt = select.options[select.selectedIndex];
        if (!opt || !opt.value) {
            hint.style.display = 'none';
            hint.textContent = '';
            return;
        }
        var stock = opt.getAttribute('data-stock');
        if (stock === null || stock === '') {
            hint.style.display = 'none';
            return;
        }
        var template = hint.getAttribute('data-hint-template') || '';
        if (!template) {
            template = hint.getAttribute('data-default-template') || '';
        }
        hint.textContent = template.replace(':qty', stock);
        hint.style.display = '';
    }

    function syncSummaryCard(form, subtotal, tax, grand) {
        var card = form ? form.querySelector('[data-summary-subtotal]') : null;
        if (!card) {
            return;
        }
        var discEl = form.querySelector('[name="discount_amount"]');
        var currencyEl = form.querySelector('[name="currency"]');
        var discount = discEl ? parseNum(discEl.value) : 0;
        var currency = currencyEl ? currencyEl.value : 'SAR';
        var set = function (sel, val) {
            var el = form.querySelector(sel);
            if (el) { el.textContent = val; }
        };
        set('[data-summary-subtotal]', subtotal.toFixed(2));
        set('[data-summary-tax]', tax.toFixed(2));
        set('[data-summary-discount]', discount.toFixed(2));
        set('[data-summary-grand]', grand.toFixed(2));
        set('[data-summary-currency]', currency);
        form.querySelectorAll('[data-summary-currency-suffix], [data-summary-currency-suffix2], [data-summary-currency-suffix3], [data-summary-currency-suffix4]').forEach(function (el) {
            el.textContent = currency;
        });
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
        var discount = 0;
        var shipping = 0;
        if (form) {
            var discEl = form.querySelector('[name="discount_amount"]');
            var shipEl = form.querySelector('[name="shipping_amount"]');
            if (discEl) { discount = parseNum(discEl.value); }
            if (shipEl) { shipping = parseNum(shipEl.value); }
        }
        grand = Math.round((grand - discount + shipping) * 100) / 100;
        var scope = form || table;
        var subEl = scope.querySelector('[data-procurement-subtotal]');
        var taxEl = scope.querySelector('[data-procurement-tax]');
        var grandEl = scope.querySelector('[data-procurement-grand-total]');
        if (subEl) { subEl.textContent = subtotal.toFixed(2); }
        if (taxEl) { taxEl.textContent = tax.toFixed(2); }
        if (grandEl) { grandEl.textContent = grand.toFixed(2); }
        if (form) {
            var totalField = form.querySelector('[data-procurement-total-field]');
            if (totalField && !isManualTotal(form)) {
                totalField.value = grand.toFixed(2);
            }
            syncSummaryCard(form, subtotal, tax, grand);
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

    function defaultVatPreset(table) {
        return table.getAttribute('data-default-vat') === '15';
    }

    function cloneRow(table) {
        var tbody = table.querySelector('tbody');
        var template = tbody.querySelector('[data-line-items-row]');
        if (!template) {
            return;
        }
        var clone = template.cloneNode(true);
        var useVat = defaultVatPreset(table);
        clone.querySelectorAll('input').forEach(function (input) {
            if (input.type === 'number') {
                input.value = input.name.indexOf('quantity') >= 0 ? '1' : '0';
            } else if (input.type === 'file') {
                input.value = '';
            } else if (input.type === 'hidden' && input.hasAttribute('data-line-tax-rate')) {
                input.value = useVat ? '15' : '0';
            } else if (input.type === 'hidden' && (input.name === 'line_attachment_keep[]' || input.name === 'line_attachment_name_keep[]')) {
                input.value = '';
            } else if (input.type !== 'hidden') {
                input.value = '';
            }
        });
        var attachHint = clone.querySelector('.rateb-line-attach-hint');
        if (attachHint) {
            attachHint.remove();
        }
        var stockHint = clone.querySelector('[data-stock-hint]');
        if (stockHint) {
            stockHint.style.display = 'none';
            stockHint.textContent = '';
        }
        clone.querySelectorAll('select').forEach(function (sel) {
            if (sel.name === 'line_unit[]') {
                sel.selectedIndex = 0;
            } else if (sel.name === 'line_inventory_id[]') {
                sel.selectedIndex = 0;
            } else if (sel.name === 'line_tax_name[]') {
                if (useVat) {
                    for (var i = 0; i < sel.options.length; i++) {
                        if (sel.options[i].getAttribute('data-tax-rate') === '15') {
                            sel.selectedIndex = i;
                            break;
                        }
                    }
                } else {
                    sel.selectedIndex = 0;
                }
                syncTaxRate(sel);
            } else if (sel.name === 'line_excluding_tax[]') {
                sel.value = '1';
            } else if (sel.name === 'line_account_id[]') {
                sel.selectedIndex = 0;
            } else if (sel.name === 'line_supplier_id[]' || sel.name === 'line_warehouse_id[]') {
                sel.selectedIndex = 0;
            }
        });
        var subEl = clone.querySelector('[data-line-subtotal]');
        var totalEl = clone.querySelector('[data-line-total]');
        if (subEl) { subEl.textContent = '0.00'; }
        if (totalEl) { totalEl.textContent = '0.00'; }
        tbody.appendChild(clone);
        renumberLineRows(table);
        updateTableTotals(table);
    }

    function bindStockHints(table) {
        table.querySelectorAll('[data-line-inventory]').forEach(function (sel) {
            var row = sel.closest('[data-line-items-row]');
            var hint = row ? row.querySelector('[data-stock-hint]') : null;
            if (hint && !hint.getAttribute('data-default-template')) {
                hint.setAttribute('data-default-template', hint.getAttribute('data-hint-template') || hint.textContent || '');
            }
            updateStockHint(sel);
        });
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
                renumberLineRows(table);
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
            if (e.target.matches('[data-line-calc], [data-line-unit]')) {
                updateTableTotals(table);
            }
        });
        table.querySelectorAll('[data-line-tax-preset]').forEach(syncTaxRate);
        renumberLineRows(table);
        bindStockHints(table);
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

    function fillFromInventory(select) {
        var opt = select.options[select.selectedIndex];
        var row = select.closest('[data-line-items-row]');
        if (!row || !opt || !opt.value) {
            return;
        }
        var nameInput = row.querySelector('[name="line_item_name[]"]');
        var skuInput = row.querySelector('[name="line_sku[]"]');
        var unitSelect = row.querySelector('[name="line_unit[]"]');
        var priceInput = row.querySelector('[name="line_unit_price[]"]');
        if (nameInput) { nameInput.value = opt.getAttribute('data-name') || ''; }
        if (skuInput) { skuInput.value = opt.getAttribute('data-sku') || ''; }
        if (unitSelect && opt.getAttribute('data-unit')) {
            unitSelect.value = opt.getAttribute('data-unit');
        }
        if (priceInput) { priceInput.value = opt.getAttribute('data-price') || '0'; }
        updateStockHint(select);
        var table = row.closest('[data-line-items-table]');
        if (table) { updateTableTotals(table); }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-line-items-table]').forEach(bindTable);
        bindNotes();
        bindEstimatedTotal();
        document.querySelectorAll('[data-procurement-form]').forEach(function (form) {
            syncEstimatedCurrency(form);
        });
        document.addEventListener('change', function (e) {
            if (e.target.matches('[data-line-inventory]')) {
                fillFromInventory(e.target);
                updateStockHint(e.target);
            }
            if (e.target.matches('[data-procurement-adjust], [name="currency"]')) {
                var table = document.querySelector('[data-line-items-table]');
                if (table) { updateTableTotals(table); }
                var form = e.target.closest('[data-procurement-form]');
                if (e.target.matches('[name="currency"]')) {
                    syncEstimatedCurrency(form);
                }
            }
        });
        document.addEventListener('input', function (e) {
            if (e.target.matches('[data-procurement-adjust]')) {
                var table = document.querySelector('[data-line-items-table]');
                if (table) { updateTableTotals(table); }
            }
        });
    });
})();
