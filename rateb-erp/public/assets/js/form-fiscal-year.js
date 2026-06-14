(function () {
    'use strict';

    function applyFiscalYear(form, year) {
        if (!form || !year || year === '__manual__') {
            return;
        }
        if (!/^\d{4}$/.test(String(year))) {
            return;
        }
        var start = form.querySelector('[name="start_date"]');
        var end = form.querySelector('[name="end_date"]');
        if (start && !start.dataset.userEdited) {
            start.value = year + '-01-01';
        }
        if (end && !end.dataset.userEdited) {
            end.value = year + '-12-31';
        }
    }

    function bindFiscalYearPicker(root) {
        (root || document).querySelectorAll('[data-fiscal-year-picker]').forEach(function (el) {
            if (el.dataset.fiscalBound === '1') {
                return;
            }
            el.dataset.fiscalBound = '1';
            var form = el.closest('form');
            var onPick = function () {
                var val = el.value;
                if (el.classList.contains('rateb-hybrid-select') && val !== '__manual__') {
                    applyFiscalYear(form, val);
                    return;
                }
                if (!el.classList.contains('rateb-hybrid-select')) {
                    applyFiscalYear(form, val);
                }
            };
            el.addEventListener('change', onPick);
            if (el.value && el.value !== '__manual__') {
                applyFiscalYear(form, el.value);
            }
        });

        (root || document).querySelectorAll('form [name="start_date"], form [name="end_date"]').forEach(function (input) {
            if (input.dataset.fiscalDateBound === '1') {
                return;
            }
            input.dataset.fiscalDateBound = '1';
            input.addEventListener('input', function () {
                input.dataset.userEdited = '1';
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindFiscalYearPicker(document);
    });

    window.ratebBindFiscalYearPicker = bindFiscalYearPicker;
})();
