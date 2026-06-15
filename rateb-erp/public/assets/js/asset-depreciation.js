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
        try {
            bookMap = JSON.parse(form.getAttribute('data-asset-book-values') || '{}');
        } catch (e) {
            bookMap = {};
        }

        var assetEl = form.querySelector('[name="asset_id"]');
        var amountEl = form.querySelector('[name="amount"]');
        var beforeEl = form.querySelector('[data-dep-before]');
        var afterEl = form.querySelector('[data-dep-after]');
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
            var after = Math.max(0, before - amount);
            if (beforeEl) {
                beforeEl.value = fmt(before);
            }
            if (afterEl) {
                afterEl.value = fmt(after);
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
        recalc();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-asset-depreciation-form]').forEach(initForm);
    });
})();
