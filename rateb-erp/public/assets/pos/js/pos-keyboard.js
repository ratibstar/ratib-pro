(function () {
    'use strict';

    var shortcuts = {
        F2: 'pos-focus-search',
        F3: 'pos-focus-barcode',
        F6: 'pos-focus-customer',
        F9: 'pos-clear-cart',
        '+': 'pos-qty-up',
        '=': 'pos-qty-up',
        '-': 'pos-qty-down',
        Delete: 'pos-remove-line',
        Backspace: 'pos-remove-line-soft',
        Escape: 'pos-clear-selection'
    };

    document.addEventListener('keydown', function (e) {
        if (!document.getElementById('rateb-pos-register-main')) {
            return;
        }
        var tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
        var isInput = tag === 'input' || tag === 'textarea' || tag === 'select' || (e.target && e.target.isContentEditable);

        var action = shortcuts[e.key];
        if (!action) {
            return;
        }

        if (action === 'pos-remove-line-soft' && isInput) {
            return;
        }

        if (e.key.startsWith('F') || action === 'pos-clear-selection' || action === 'pos-clear-cart' ||
            action === 'pos-qty-up' || action === 'pos-qty-down' || action === 'pos-remove-line') {
            if (e.key.startsWith('F') || action === 'pos-clear-cart' || action === 'pos-qty-up' || action === 'pos-qty-down' || action === 'pos-remove-line') {
                e.preventDefault();
            }
        }

        document.dispatchEvent(new CustomEvent('rateb-pos-shortcut', {
            detail: { action: action, key: e.key, target: e.target }
        }));
    });
})();
