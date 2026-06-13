(function () {
    'use strict';

    var cfg = window.RATEB_SCAN || {};
    var form = document.getElementById('scan-submit-form');
    var statusEl = document.getElementById('scan-status');
    var input = document.getElementById('scan-barcode');

    function showStatus(msg, ok) {
        if (!statusEl) return;
        statusEl.classList.remove('d-none', 'alert-success', 'alert-danger');
        statusEl.classList.add(ok ? 'alert-success' : 'alert-danger', 'alert');
        statusEl.textContent = msg;
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var barcode = input ? input.value.trim() : '';
            if (!barcode) return;
            fetch(cfg.apiPair || '/api/login-barcode-pair', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ action: 'submit', token: cfg.token, barcode: barcode })
            }).then(function (r) { return r.json(); })
                .then(function (json) {
                    if (json && json.success) {
                        showStatus(json.message || 'OK — return to your computer.', true);
                        if (input) input.value = '';
                    } else {
                        showStatus((json && json.message) || 'Invalid barcode.', false);
                    }
                })
                .catch(function () {
                    showStatus('Network error.', false);
                });
        });
    }

    if (input) {
        input.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') {
                ev.preventDefault();
                form && form.dispatchEvent(new Event('submit', { cancelable: true }));
            }
        });
    }
})();
