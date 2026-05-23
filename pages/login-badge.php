<?php
declare(strict_types=1);

/**
 * Badge QR landing — iPhone Camera; pair login or direct mobile sign-in + optional PIN.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/ratib-qr-workforce-identity.php';

$payload = isset($_GET['d']) ? trim((string) $_GET['d']) : '';
$pairToken = isset($_COOKIE['ratib_pair']) ? preg_replace('/[^a-f0-9]/', '', strtolower((string) $_COOKIE['ratib_pair'])) : '';
if (strlen($pairToken) !== 32) {
    $pairToken = '';
}

$ctxCountryId = isset($_GET['country_id']) ? (int) $_GET['country_id'] : 0;
$ctxAgencyId = isset($_GET['agency_id']) ? (int) $_GET['agency_id'] : 0;
$directLogin = ($pairToken === '');

$pageTitle = 'Workforce badge — RATEB';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="<?php echo function_exists('asset') ? asset('css/qr-scan.css') : '/css/qr-scan.css'; ?>">
    <style>
        body { padding: 2rem 1rem; }
        .badge-card { max-width: 420px; margin: 0 auto; text-align: center; }
    </style>
</head>
<body class="qr-scan-page">
    <div class="badge-card qr-scan-shell">
        <h1 class="qr-scan-title">Workforce badge</h1>
        <p id="badge-msg" class="qr-scan-status qr-scan-status--info">Processing…</p>
        <div id="badge-pin-panel" class="qr-scan-pin-panel d-none">
            <label for="badge-pin">4-digit PIN</label>
            <input type="password" id="badge-pin" class="qr-scan-pin-input" maxlength="4" inputmode="numeric" autocomplete="off">
            <label class="qr-scan-trust-label"><input type="checkbox" id="badge-trust"> Trust this device (30 days)</label>
            <button type="button" class="qr-scan-btn qr-scan-btn-primary" id="badge-pin-submit">Continue</button>
        </div>
    </div>
    <script>
    (function () {
        var payload = <?php echo json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        var pairToken = <?php echo json_encode($pairToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        var directLogin = <?php echo $directLogin ? 'true' : 'false'; ?>;
        var countryId = <?php echo (int) $ctxCountryId; ?>;
        var agencyId = <?php echo (int) $ctxAgencyId; ?>;
        var msg = document.getElementById('badge-msg');
        var pinPanel = document.getElementById('badge-pin-panel');
        var challenge = '';

        function show(text, type) {
            if (msg) {
                msg.className = 'qr-scan-status qr-scan-status--' + (type || 'info');
                msg.textContent = text;
            }
        }

        function trustBody() {
            var cb = document.getElementById('badge-trust');
            return { trust_device: !!(cb && cb.checked), device_label: 'Mobile camera' };
        }

        function finish(json) {
            if (directLogin && json.redirect) {
                show('Signed in. Redirecting…', 'success');
                window.location.href = json.redirect;
                return;
            }
            show('Success! RATEB is opening on your computer. You can close this tab.', 'success');
        }

        function validate() {
            if (!payload) {
                show('Invalid badge link.', 'error');
                return;
            }
            if (!pairToken && !directLogin) {
                show('On your computer: Barcode login → scan pairing QR with this phone first.', 'info');
                return;
            }
            show('Validating…', 'loading');
            fetch('/api/qr-login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
                body: JSON.stringify(Object.assign({
                    action: 'validate',
                    qr_payload: payload,
                    pair_token: pairToken,
                    country_id: countryId,
                    agency_id: agencyId
                }, trustBody()))
            })
                .then(function (r) { return r.json(); })
                .then(function (json) {
                    if (json && json.needs_pin && json.challenge_token) {
                        challenge = json.challenge_token;
                        if (pinPanel) pinPanel.classList.remove('d-none');
                        show('Enter your PIN.', 'info');
                        return;
                    }
                    if (json && json.success) {
                        finish(json);
                        return;
                    }
                    show((json && json.message) ? json.message : 'Badge not accepted.', 'error');
                })
                .catch(function () { show('Network error.', 'error'); });
        }

        document.getElementById('badge-pin-submit').addEventListener('click', function () {
            var pin = document.getElementById('badge-pin').value;
            show('Verifying PIN…', 'loading');
            fetch('/api/qr-login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(Object.assign({
                    action: 'validate_pin',
                    challenge_token: challenge,
                    pin: pin,
                    pair_token: pairToken,
                    country_id: countryId,
                    agency_id: agencyId
                }, trustBody()))
            })
                .then(function (r) { return r.json(); })
                .then(function (json) {
                    if (json && json.success) {
                        finish(json);
                        return;
                    }
                    show((json && json.message) ? json.message : 'PIN failed.', 'error');
                });
        });

        validate();
    })();
    </script>
</body>
</html>
