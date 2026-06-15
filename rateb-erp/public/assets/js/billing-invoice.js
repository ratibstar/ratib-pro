(function () {
    'use strict';

    function parseNum(val) {
        var n = parseFloat(val);
        return isNaN(n) ? 0 : n;
    }

    function fmt(n) {
        return (Math.round(n * 100) / 100).toFixed(2);
    }

    function addDays(dateStr, days) {
        if (!dateStr) return '';
        var d = new Date(dateStr + 'T12:00:00');
        if (isNaN(d.getTime())) return '';
        d.setDate(d.getDate() + days);
        return d.toISOString().slice(0, 10);
    }

    function initInvoiceForm(form) {
        if (!form || form.dataset.invoiceBound === '1') return;
        form.dataset.invoiceBound = '1';

        var companyEl = form.querySelector('[name="company_id"]');
        var subEl = form.querySelector('[name="subscription_id"]');
        var amountEl = form.querySelector('[name="amount"]');
        var taxRateEl = form.querySelector('[name="tax_rate"]');
        var discountEl = form.querySelector('[name="discount_amount"]');
        var discountTypeEl = form.querySelector('[name="discount_type"]');
        var taxAmountEl = form.querySelector('[name="tax_amount"]');
        var totalEl = form.querySelector('[name="total_amount"]');
        var issuedEl = form.querySelector('[name="issued_at"]');
        var dueEl = form.querySelector('[name="due_date"]');
        var termsEl = form.querySelector('[name="payment_terms_days"]');
        var dueHint = form.querySelector('[data-due-hint]');
        var lookupUrl = form.getAttribute('data-subscription-lookup') || '';
        var allSubs = [];
        try {
            allSubs = JSON.parse(form.getAttribute('data-subscriptions') || '[]');
        } catch (e) {
            allSubs = [];
        }

        function setDiscountMode(mode) {
            form.querySelectorAll('[data-discount-mode]').forEach(function (btn) {
                var active = btn.getAttribute('data-discount-mode') === mode;
                btn.classList.toggle('active', active);
                btn.classList.toggle('btn-primary', active);
                btn.classList.toggle('btn-outline-secondary', !active);
            });
            if (discountTypeEl) discountTypeEl.value = mode;
            var suffix = form.querySelector('[data-discount-suffix]');
            if (suffix) suffix.textContent = mode === 'percent' ? '%' : (form.getAttribute('data-currency-label') || 'SAR');
            recalc();
        }

        function recalc() {
            var amount = Math.max(0, parseNum(amountEl && amountEl.value));
            var taxRate = Math.max(0, parseNum(taxRateEl && taxRateEl.value));
            var discVal = Math.max(0, parseNum(discountEl && discountEl.value));
            var discType = (discountTypeEl && discountTypeEl.value) || 'value';
            var discount = discType === 'percent' ? Math.min(amount, amount * (discVal / 100)) : Math.min(amount, discVal);
            var subtotal = Math.max(0, amount - discount);
            var tax = Math.round(subtotal * (taxRate / 100) * 100) / 100;
            var total = Math.round((subtotal + tax) * 100) / 100;

            if (taxAmountEl) taxAmountEl.value = fmt(tax);
            if (totalEl) totalEl.value = fmt(total);

            var setText = function (sel, val) {
                var el = form.querySelector(sel);
                if (el) el.textContent = fmt(val);
            };
            setText('[data-summary-subtotal]', amount);
            setText('[data-summary-discount]', discount);
            setText('[data-summary-tax]', tax);
            setText('[data-summary-total]', total);
            var taxLabel = form.querySelector('[data-summary-tax-label]');
            if (taxLabel) taxLabel.textContent = taxRate + '%';
        }

        function filterSubscriptions(companyId) {
            if (!subEl) return;
            var current = subEl.value;
            subEl.innerHTML = '<option value="">' + (form.getAttribute('data-optional-label') || '') + '</option>';
            allSubs.forEach(function (sub) {
                if (companyId && String(sub.company_id) !== String(companyId)) return;
                var opt = document.createElement('option');
                opt.value = sub.id;
                opt.textContent = sub.label || ('#' + sub.id);
                opt.dataset.amount = sub.amount || '';
                if (String(sub.id) === String(current)) opt.selected = true;
                subEl.appendChild(opt);
            });
        }

        function applySubscriptionDefaults(subId) {
            if (!subId || !subEl) return;
            var opt = subEl.querySelector('option[value="' + subId + '"]');
            if (!opt) return;
            if (amountEl && (!amountEl.value || parseNum(amountEl.value) === 0)) {
                amountEl.value = opt.dataset.amount || amountEl.value;
            }
            recalc();
        }

        function loadCompanySubscription(companyId) {
            if (!companyId) {
                filterSubscriptions('');
                return;
            }
            filterSubscriptions(companyId);
            if (!lookupUrl) return;
            fetch(lookupUrl + '?company_id=' + encodeURIComponent(companyId), {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    if (!data || !data.subscription || !subEl) return;
                    subEl.value = String(data.subscription.id);
                    if (amountEl && data.subscription.amount) {
                        amountEl.value = data.subscription.amount;
                    }
                    if (termsEl && data.subscription.payment_terms_days) {
                        termsEl.value = data.subscription.payment_terms_days;
                        updateDueDate();
                    }
                    recalc();
                })
                .catch(function () {});
        }

        function updateDueDate() {
            if (!issuedEl || !dueEl || !termsEl) return;
            var days = parseInt(termsEl.value, 10) || 0;
            var due = addDays(issuedEl.value, days);
            if (due) dueEl.value = due;
            if (dueHint) {
                dueHint.textContent = days > 0
                    ? (form.getAttribute('data-after-days-label') || '').replace(':days', String(days))
                    : '';
            }
        }

        form.querySelectorAll('[data-discount-mode]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                setDiscountMode(btn.getAttribute('data-discount-mode') || 'value');
            });
        });

        [amountEl, taxRateEl, discountEl].forEach(function (el) {
            if (!el) return;
            el.addEventListener('input', recalc);
        });

        if (companyEl) {
            companyEl.addEventListener('change', function () {
                loadCompanySubscription(companyEl.value);
            });
        }
        if (subEl) {
            subEl.addEventListener('change', function () {
                applySubscriptionDefaults(subEl.value);
            });
        }
        if (issuedEl) issuedEl.addEventListener('change', updateDueDate);
        if (termsEl) termsEl.addEventListener('input', updateDueDate);

        function syncFromLines() {
            var table = form.querySelector('[data-invoice-lines-table]');
            if (!table || !amountEl) return;
            var subtotal = 0;
            table.querySelectorAll('[data-line-items-row]').forEach(function (row) {
                var qty = parseNum(row.querySelector('[name="line_quantity[]"]') && row.querySelector('[name="line_quantity[]"]').value);
                var price = parseNum(row.querySelector('[name="line_unit_price[]"]') && row.querySelector('[name="line_unit_price[]"]').value);
                subtotal += qty * price;
            });
            if (subtotal > 0) {
                amountEl.value = fmt(subtotal);
            }
            recalc();
        }

        function initAttachments() {
            var dropzone = form.querySelector('[data-invoice-dropzone]');
            var fileInput = form.querySelector('[data-invoice-file-input]');
            var pickBtn = form.querySelector('[data-invoice-pick-files]');
            var pending = form.querySelector('[data-pending-files]');
            var meta = form.querySelector('[data-attachment-meta]');
            var maxFiles = parseInt(form.getAttribute('data-max-attachments') || '5', 10);
            var existing = form.querySelectorAll('[data-attached-item]').length;

            function updateMeta(count) {
                if (!meta) return;
                var total = existing + count;
                meta.textContent = (form.getAttribute('data-attachment-count-label') || '')
                    .replace(':count', String(total))
                    .replace(':max', String(maxFiles));
            }

            function renderPending() {
                if (!pending || !fileInput) return;
                pending.innerHTML = '';
                Array.prototype.forEach.call(fileInput.files || [], function (file) {
                    var div = document.createElement('div');
                    div.className = 'small text-start py-1';
                    div.textContent = file.name + ' (' + Math.round(file.size / 1024) + ' KB)';
                    pending.appendChild(div);
                });
                updateMeta((fileInput.files && fileInput.files.length) || 0);
            }

            if (pickBtn && fileInput) {
                pickBtn.addEventListener('click', function () { fileInput.click(); });
                fileInput.addEventListener('change', renderPending);
            }
            if (dropzone && fileInput) {
                ['dragenter', 'dragover'].forEach(function (ev) {
                    dropzone.addEventListener(ev, function (e) {
                        e.preventDefault();
                        dropzone.classList.add('rateb-invoice-dropzone-active');
                    });
                });
                ['dragleave', 'drop'].forEach(function (ev) {
                    dropzone.addEventListener(ev, function (e) {
                        e.preventDefault();
                        dropzone.classList.remove('rateb-invoice-dropzone-active');
                    });
                });
                dropzone.addEventListener('drop', function (e) {
                    if (!e.dataTransfer || !e.dataTransfer.files) return;
                    fileInput.files = e.dataTransfer.files;
                    renderPending();
                });
            }
            updateMeta(0);
        }

        function openPreview() {
            var previewUrl = form.getAttribute('data-preview-url');
            var id = form.getAttribute('data-invoice-id');
            if (previewUrl && id && parseInt(id, 10) > 0) {
                window.open(previewUrl, '_blank', 'noopener');
                return;
            }
            var draftUrl = form.getAttribute('data-preview-draft-url');
            if (!draftUrl) return;
            var fd = new FormData(form);
            fetch(draftUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.text(); })
                .then(function (html) {
                    var w = window.open('', '_blank', 'noopener');
                    if (!w) return;
                    w.document.open();
                    w.document.write(html);
                    w.document.close();
                    w.focus();
                    setTimeout(function () { w.print(); }, 500);
                })
                .catch(function () {});
        }

        form.addEventListener('input', function (e) {
            if (e.target && e.target.closest && e.target.closest('[data-invoice-lines-table]')) {
                syncFromLines();
            }
        });
        form.addEventListener('click', function (e) {
            if (e.target && e.target.closest && (e.target.closest('[data-line-items-add]') || e.target.closest('[data-line-items-remove]'))) {
                setTimeout(syncFromLines, 50);
            }
        });

        var previewBtn = form.querySelector('[data-invoice-preview]');
        if (previewBtn) {
            previewBtn.addEventListener('click', openPreview);
        }

        initAttachments();
        setDiscountMode((discountTypeEl && discountTypeEl.value) || 'value');
        if (companyEl && companyEl.value) filterSubscriptions(companyEl.value);
        updateDueDate();
        recalc();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-invoice-form]').forEach(initInvoiceForm);
    });

    window.ratebInitInvoiceForm = initInvoiceForm;
})();
