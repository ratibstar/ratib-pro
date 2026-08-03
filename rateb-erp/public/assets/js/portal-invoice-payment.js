(function () {
    'use strict';

    document.querySelectorAll('.rateb-portal-pay-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            btn.disabled = true;
            btn.setAttribute('aria-busy', 'true');
        });
    });
})();
