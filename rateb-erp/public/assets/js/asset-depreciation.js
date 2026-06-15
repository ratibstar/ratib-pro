(function () {
    'use strict';

    function fmt(n) {
        return (Math.round(n * 100) / 100).toFixed(2);
    }

    function initForm(form) {
        if (!form || form.dataset.depBound === '1') {
            return;
        }
        form.dataset.depBound = '1';

        var bookMap = {};
        var accMap = {};
        try {
            bookMap = JSON.parse(form.getAttribute('data-asset-book-values') || '{}');
            accMap = JSON.parse(form.getAttribute('data-asset-accumulated') || '{}');
        } catch (e) {
            bookMap = {};
            accMap = {};
        }

        var assetEl = form.querySelector('[name="asset_id"]');
        var amountEl = form.querySelector('[name="amount"]');
        var rateEl = form.querySelector('[name="depreciation_rate"]');
        var beforeEl = form.querySelector('[data-dep-before]');
        var afterEl = form.querySelector('[data-dep-after]');
        var accEl = form.querySelector('[data-dep-accumulated]');
        var preview = form.querySelector('[data-dep-preview]');

        function recalc() {
            var assetId = assetEl ? String(assetEl.value || '') : '';
            var before = assetId && bookMap[assetId] !== undefined ? parseFloat(bookMap[assetId]) : 0;
            if (isNaN(before)) {
                before = 0;
            }
            var amount = amountEl ? parseFloat(amountEl.value || '0') : 0;
            if (isNaN(amount) || amount < 0) {
                amount = 0;
            }
            var rate = rateEl ? parseFloat(rateEl.value || '0') : 0;
            if (!isNaN(rate) && rate > 0 && amount <= 0 && before > 0) {
                amount = Math.round((before * rate / 100) * 100) / 100;
                if (amountEl) {
                    amountEl.value = amount > 0 ? String(amount) : '';
                }
            }
            var after = Math.max(0, before - amount);
            var accumulated = assetId && accMap[assetId] !== undefined ? parseFloat(accMap[assetId]) : 0;
            if (isNaN(accumulated)) {
                accumulated = 0;
            }
            if (beforeEl) {
                beforeEl.value = fmt(before);
            }
            if (afterEl) {
                afterEl.value = fmt(after);
            }
            if (accEl) {
                accEl.value = fmt(accumulated + amount);
            }
            if (preview) {
                preview.classList.toggle('d-none', !assetId);
            }
        }

        if (assetEl) {
            assetEl.addEventListener('change', recalc);
        }
        if (amountEl) {
            amountEl.addEventListener('input', recalc);
        }
        if (rateEl) {
            rateEl.addEventListener('input', recalc);
        }
        recalc();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-asset-depreciation-form]').forEach(initForm);

        var printBtn = document.querySelector('[data-dep-print]');
        if (printBtn) {
            printBtn.addEventListener('click', function () {
                window.print();
            });
        }

        var newBtn = document.querySelector('[data-dep-form-reset]');
        if (newBtn) {
            newBtn.addEventListener('click', function (e) {
                var form = document.querySelector('[data-asset-depreciation-form]');
                if (form) {
                    e.preventDefault();
                    form.reset();
                    form.querySelectorAll('[data-dep-before],[data-dep-after],[data-dep-accumulated]').forEach(function (el) {
                        el.value = '0.00';
                    });
                    initForm(form);
                    form.dataset.depBound = '0';
                    initForm(form);
                }
            });
        }
    });
})();
