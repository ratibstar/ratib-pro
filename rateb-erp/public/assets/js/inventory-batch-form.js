(function () {
    'use strict';

    var form = document.querySelector('[data-inventory-batch-form]');
    if (!form) {
        return;
    }

    var inventorySelect = form.querySelector('[name="inventory_id"]');
    var codeInput = form.querySelector('[data-batch-item-code-display]');

    function syncItemCode() {
        if (!inventorySelect || !codeInput) {
            return;
        }
        var opt = inventorySelect.options[inventorySelect.selectedIndex];
        var code = opt ? (opt.getAttribute('data-item-code') || '') : '';
        codeInput.value = code;
    }

    if (inventorySelect) {
        inventorySelect.addEventListener('change', syncItemCode);
    }
    syncItemCode();
})();
