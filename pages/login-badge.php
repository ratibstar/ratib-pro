<?php
declare(strict_types=1);

/**
 * Badge QR landing — iPhone Camera opens this URL; completes pair login when cookie exists.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/ratib-qr-login.php';

$payload = isset($_GET['d']) ? trim((string) $_GET['d']) : '';
$pairToken = isset($_COOKIE['ratib_pair']) ? preg_replace('/[^a-f0-9]/', '', strtolower((string) $_COOKIE['ratib_pair'])) : '';
if (strlen($pairToken) !== 32) {
    $pairToken = '';
}

$ctxCountryId = isset($_GET['country_id']) ? (int) $_GET['country_id'] : 0;
$ctxAgencyId = isset($_GET['agency_id']) ? (int) $_GET['agency_id'] : 0;

$pageTitle = 'Login badge — RATEB';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        body { font-family: system-ui, sans-serif; background: #0b1220; color: #e2e8f0; margin: 0; padding: 2rem 1.25rem; }
        .card { max-width: 420px; margin: 0 auto; text-align: center; }
        h1 { font-size: 1.15rem; }
        p { color: #94a3b8; line-height: 1.5; }
        .ok { color: #4ade80; }
        .warn { color: #fbbf24; }
        .err { color: #f87171; }
    </style>
</head>
<body>
    <div class="card">
        <h1>RATEB login badge</h1>
        <p id="badge-msg">Processing…</p>
    </div>
    <script>
    (function () {
        var payload = <?php echo json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        var pairToken = <?php echo json_encode($pairToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        var countryId = <?php echo (int) $ctxCountryId; ?>;
        var agencyId = <?php echo (int) $ctxAgencyId; ?>;
        var msg = document.getElementById('badge-msg');

        function show(text, cls) {
            if (msg) {
                msg.className = cls || '';
                msg.textContent = text;
            }
        }

        if (!payload) {
            show('Invalid badge link.', 'err');
            return;
        }
        if (!pairToken) {
            show('First: on your computer choose Barcode login and scan that QR with this phone. Then scan the employee badge again.', 'warn');
            return;
        }

        show('Signing in on your computer…', 'ok');

        fetch('/api/qr-login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store',
            body: JSON.stringify({
                action: 'validate',
                qr_payload: payload,
                pair_token: pairToken,
                country_id: countryId,
                agency_id: agencyId
            })
        })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (json && json.success) {
                    show('Success! RATEB is opening on your computer. You can close this tab.', 'ok');
                    return;
                }
                var text = (json && json.message) ? json.message : 'Badge not accepted.';
                if (json && json.code === 'pairing_qr') {
                    text = 'That was the computer QR. Scan the employee badge from Users → Barcode.';
                }
                show(text, 'err');
            })
            .catch(function () {
                show('Network error. Try again.', 'err');
            });
    })();
    </script>
</body>
</html>
