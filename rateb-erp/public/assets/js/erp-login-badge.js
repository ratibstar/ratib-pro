(function () {
    'use strict';

    var cfg = window.RATEB_BADGE || {};
    var payload = cfg.payload || '';
    var pairToken = cfg.pairToken || '';
    var directLogin = !!cfg.directLogin;
    var apiQr = cfg.apiQr || '/api/qr-login';
    var msg = document.getElementById('badge-msg');

    function show(text, type) {
        if (!msg) return;
        msg.className = 'qr-scan-status qr-scan-status--' + (type || 'info');
        msg.textContent = text;
    }

    function validate() {
        if (!payload) {
            show('Invalid badge link — open Profile or Admin → Users and regenerate the badge.', 'error');
            return;
        }
        if (!pairToken && !directLogin) {
            show('On your computer: Barcode login → scan pairing QR with this phone first.', 'info');
            return;
        }
        show('Validating…', 'loading');
        fetch(apiQr, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store',
            body: JSON.stringify({
                action: 'validate',
                qr_payload: payload,
                pair_token: pairToken
            })
        }).then(function (r) { return r.json(); })
            .then(function (json) {
                if (json && json.success) {
                    if (directLogin && json.redirect) {
                        show('Signed in. Redirecting…', 'success');
                        window.location.href = json.redirect;
                        return;
                    }
                    show('Success! ERP is opening on your computer. You can close this tab.', 'success');
                    return;
                }
                show((json && json.message) || 'Badge not recognized.', 'error');
            })
            .catch(function () {
                show('Network error.', 'error');
            });
    }

    validate();
})();
