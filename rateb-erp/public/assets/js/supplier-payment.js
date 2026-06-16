(function () {
    'use strict';

    var form = document.querySelector('[data-supplier-payment-form]');
    if (!form) {
        return;
    }

    var data = {};
    try {
        data = JSON.parse(form.getAttribute('data-payables') || '{}');
    } catch (e) {
        data = {};
    }

    var supplierSelect = form.querySelector('[data-sp-supplier-select]');
    var supplierIdInput = form.querySelector('[data-sp-supplier-id]');
    var poIdInput = form.querySelector('[data-sp-po-id]');
    var invoiceIdInput = form.querySelector('[data-sp-invoice-id]');
    var poSelect = form.querySelector('[data-sp-po-select]');
    var invoiceSelect = form.querySelector('[data-sp-invoice-select]');
    var poPicker = form.querySelector('[data-sp-po-picker]');
    var invoicePicker = form.querySelector('[data-sp-invoice-picker]');
    var balanceEl = form.querySelector('[data-sp-supplier-balance]');
    var lineDueEl = form.querySelector('[data-sp-line-due]');
    var amountInput = form.querySelector('[name="amount"]');
    var dueDateInput = form.querySelector('[name="due_date"]');
    var paymentMethodSelect = form.querySelector('[name="payment_method"]');
    var bankField = form.querySelector('[name="bank_account_id"]');
    var bankWrap = bankField ? bankField.closest('.col-md-4, .col-lg-4, .mb-3, [class*="col-"]') : null;
    var referenceInput = form.querySelector('[name="reference_no"]');
    var referenceLabel = referenceInput ? referenceInput.closest('.col-md-4, .col-lg-4, .mb-3, [class*="col-"]') : null;
    var linkRadios = form.querySelectorAll('[data-sp-link-type]');

    function formatMoney(n) {
        return Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' SAR';
    }

    function supplierBalance(supplierId) {
        if (data.balances && data.balances[String(supplierId)] !== undefined) {
            return parseFloat(data.balances[String(supplierId)]) || 0;
        }
        var total = 0;
        (data.orders || []).forEach(function (o) {
            if (String(o.supplier_id) === String(supplierId)) {
                total += parseFloat(o.due) || 0;
            }
        });
        return total;
    }

    function filterOptions(selectEl, supplierId, keepValue) {
        if (!selectEl) {
            return;
        }
        var current = keepValue ? selectEl.value : '';
        Array.prototype.forEach.call(selectEl.options, function (opt, idx) {
            if (idx === 0) {
                opt.hidden = false;
                return;
            }
            var sid = opt.getAttribute('data-supplier-id') || '';
            var show = !supplierId || String(sid) === String(supplierId);
            opt.hidden = !show;
            if (!show && opt.selected) {
                opt.selected = false;
            }
        });
        if (current && selectEl.querySelector('option[value="' + current + '"]:not([hidden])')) {
            selectEl.value = current;
        } else {
            selectEl.value = '';
        }
    }

    function linkType() {
        var checked = form.querySelector('[data-sp-link-type]:checked');
        return checked ? checked.value : 'po';
    }

    function toggleLinkPanels() {
        var type = linkType();
        if (poPicker) {
            poPicker.classList.toggle('d-none', type !== 'po');
        }
        if (invoicePicker) {
            invoicePicker.classList.toggle('d-none', type !== 'invoice');
        }
        if (type === 'po' && invoiceIdInput) {
            invoiceIdInput.value = '0';
        }
        if (type === 'invoice' && poIdInput) {
            // PO set when invoice selected
        }
    }

    function applyDueFromOption(opt) {
        if (!opt || !opt.value) {
            if (lineDueEl) {
                lineDueEl.value = formatMoney(0);
            }
            if (amountInput) {
                amountInput.removeAttribute('max');
            }
            return;
        }
        var due = parseFloat(opt.getAttribute('data-due') || '0') || 0;
        var dueDate = opt.getAttribute('data-due-date') || '';
        if (lineDueEl) {
            lineDueEl.value = formatMoney(due);
        }
        if (amountInput) {
            amountInput.setAttribute('max', String(Math.max(0.01, due)));
            if (!amountInput.value || parseFloat(amountInput.value) > due) {
                amountInput.value = due > 0 ? due.toFixed(2) : '';
            }
        }
        if (dueDateInput && dueDate) {
            dueDateInput.value = dueDate.substring(0, 10);
        }
    }

    function syncPaymentMethodUi() {
        var method = paymentMethodSelect ? paymentMethodSelect.value : 'bank';
        var showBank = method === 'bank' || method === 'cheque';
        if (bankWrap) {
            bankWrap.style.display = showBank ? '' : 'none';
            if (bankField) {
                bankField.required = method === 'bank';
            }
        }
        if (referenceInput) {
            var label = referenceInput.closest('.col-md-4, .col-lg-4, .mb-3, [class*="col-"]');
            var lbl = label ? label.querySelector('label') : null;
            if (lbl) {
                lbl.textContent = method === 'cheque'
                    ? (window.ratebSpLabels && ratebSpLabels.check_number) || 'Check number'
                    : (window.ratebSpLabels && ratebSpLabels.bank_reference) || 'Bank reference';
            }
        }
    }

    function onSupplierChange() {
        var sid = supplierSelect ? supplierSelect.value : '';
        if (supplierIdInput) {
            supplierIdInput.value = sid;
        }
        if (balanceEl) {
            balanceEl.value = formatMoney(supplierBalance(sid));
        }
        filterOptions(poSelect, sid, false);
        filterOptions(invoiceSelect, sid, false);
        if (poSelect) {
            poSelect.dispatchEvent(new Event('change'));
        }
    }

    function onPoChange() {
        var opt = poSelect && poSelect.selectedOptions[0];
        if (poIdInput) {
            poIdInput.value = opt && opt.value ? opt.value : '0';
        }
        if (opt && opt.value) {
            var sid = opt.getAttribute('data-supplier-id');
            if (supplierSelect && sid) {
                supplierSelect.value = sid;
                if (supplierIdInput) {
                    supplierIdInput.value = sid;
                }
                if (balanceEl) {
                    balanceEl.value = formatMoney(supplierBalance(sid));
                }
            }
        }
        applyDueFromOption(opt);
    }

    function onInvoiceChange() {
        var opt = invoiceSelect && invoiceSelect.selectedOptions[0];
        if (invoiceIdInput) {
            invoiceIdInput.value = opt && opt.value ? opt.value : '0';
        }
        if (opt && opt.value) {
            var poId = opt.getAttribute('data-po-id');
            var sid = opt.getAttribute('data-supplier-id');
            if (poIdInput && poId) {
                poIdInput.value = poId;
            }
            if (supplierSelect && sid) {
                supplierSelect.value = sid;
                if (supplierIdInput) {
                    supplierIdInput.value = sid;
                }
                if (balanceEl) {
                    balanceEl.value = formatMoney(supplierBalance(sid));
                }
            }
        }
        applyDueFromOption(opt);
    }

    if (supplierSelect) {
        supplierSelect.addEventListener('change', onSupplierChange);
    }
    if (poSelect) {
        poSelect.addEventListener('change', onPoChange);
    }
    if (invoiceSelect) {
        invoiceSelect.addEventListener('change', onInvoiceChange);
    }
    linkRadios.forEach(function (radio) {
        radio.addEventListener('change', toggleLinkPanels);
    });
    if (paymentMethodSelect) {
        paymentMethodSelect.addEventListener('change', syncPaymentMethodUi);
    }

    form.addEventListener('submit', function (ev) {
        var type = linkType();
        var hasPo = poIdInput && parseInt(poIdInput.value, 10) > 0;
        var hasInv = invoiceIdInput && parseInt(invoiceIdInput.value, 10) > 0;
        if ((type === 'po' && !hasPo) || (type === 'invoice' && !hasInv)) {
            ev.preventDefault();
            alert((window.ratebSpLabels && ratebSpLabels.select_document) || 'Select a purchase order or invoice.');
            return;
        }
        if (!supplierIdInput || parseInt(supplierIdInput.value, 10) < 1) {
            ev.preventDefault();
            alert((window.ratebSpLabels && ratebSpLabels.select_supplier) || 'Select a supplier.');
        }
    });

    toggleLinkPanels();
    syncPaymentMethodUi();
    if (supplierSelect && supplierSelect.value) {
        filterOptions(poSelect, supplierSelect.value, true);
        filterOptions(invoiceSelect, supplierSelect.value, true);
    }
    if (poSelect && poSelect.value) {
        onPoChange();
    } else if (invoiceSelect && invoiceSelect.value) {
        onInvoiceChange();
    }
})();
