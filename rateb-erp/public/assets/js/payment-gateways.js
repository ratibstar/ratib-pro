(function () {
    'use strict';

    var form = document.getElementById('rateb-payment-gateways-form');
    if (!form) {
        return;
    }

    var testBtn = document.getElementById('pg-test-connection');
    var badge = document.getElementById('pg-health-badge');
    var healthUrl = form.getAttribute('data-health-url') || '';

    if (testBtn && healthUrl) {
        testBtn.addEventListener('click', function () {
            var csrfInput = form.querySelector('input[name="_csrf"]');
            var csrf = csrfInput ? csrfInput.value : '';
            testBtn.disabled = true;
            fetch(healthUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrf
                },
                credentials: 'same-origin',
                body: '_csrf=' + encodeURIComponent(csrf)
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (badge) {
                        var ok = data && data.success;
                        badge.textContent = ok ? 'healthy' : 'failed';
                        badge.classList.toggle('rateb-payment-gateways-health-ok', ok);
                        badge.classList.toggle('rateb-payment-gateways-health-fail', !ok);
                    }
                })
                .catch(function () {
                    if (badge) {
                        badge.textContent = 'failed';
                        badge.classList.add('rateb-payment-gateways-health-fail');
                    }
                })
                .finally(function () {
                    testBtn.disabled = false;
                });
        });
    }
})();
