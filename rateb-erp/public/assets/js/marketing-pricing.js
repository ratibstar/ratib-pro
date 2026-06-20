(function () {
    'use strict';

    var root = document.querySelector('[data-rateb-pricing-toggle]');
    if (!root) {
        return;
    }

    var buttons = root.querySelectorAll('[data-billing-cycle]');
    var cards = document.querySelectorAll('[data-plan-card]');

    function setCycle(cycle) {
        buttons.forEach(function (btn) {
            var active = btn.getAttribute('data-billing-cycle') === cycle;
            btn.classList.toggle('active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        cards.forEach(function (card) {
            card.querySelectorAll('[data-price-monthly]').forEach(function (el) {
                el.hidden = cycle !== 'monthly';
            });
            card.querySelectorAll('[data-price-yearly]').forEach(function (el) {
                el.hidden = cycle !== 'yearly';
            });
        });
    }

    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            setCycle(btn.getAttribute('data-billing-cycle') || 'yearly');
        });
    });

    setCycle('yearly');
})();
