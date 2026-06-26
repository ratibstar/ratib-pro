(function () {
    'use strict';

    var form = document.querySelector('[data-contract-renewal-form]');
    if (!form) {
        return;
    }

    var contractSelect = form.querySelector('[name="contract_id"]');
    var currentValueInput = form.querySelector('[data-contract-current-value-display]');
    var newValueInput = form.querySelector('[name="new_value"]');
    var newEndInput = form.querySelector('[name="new_end_date"]');

    function syncFromContract() {
        if (!contractSelect) {
            return;
        }
        var opt = contractSelect.options[contractSelect.selectedIndex];
        if (!opt || !opt.value) {
            if (currentValueInput) {
                currentValueInput.value = '';
            }
            return;
        }
        var value = opt.getAttribute('data-contract-value') || '';
        var endDate = opt.getAttribute('data-end-date') || '';
        if (currentValueInput) {
            currentValueInput.value = value;
        }
        if (newValueInput && value !== '') {
            newValueInput.value = value;
        }
        if (newEndInput && endDate !== '' && endDate !== '0000-00-00' && !newEndInput.value) {
            newEndInput.value = endDate;
        }
    }

    if (contractSelect) {
        contractSelect.addEventListener('change', syncFromContract);
    }
    syncFromContract();
})();
