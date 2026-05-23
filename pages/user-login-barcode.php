<?php
declare(strict_types=1);

/**
 * Employee login badge — secure RATIBLOGIN QR + legacy reference code.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/ratib-user-login-barcode.php';
require_once __DIR__ . '/../includes/ratib-qr-login.php';

if (!isset($_SESSION['user_id'], $_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

$targetUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
if ($targetUserId <= 0) {
    $targetUserId = (int) $_SESSION['user_id'];
}

$conn = $GLOBALS['conn'] ?? null;
if (!($conn instanceof mysqli)) {
    http_response_code(500);
    echo 'Database unavailable.';
    exit;
}

$result = ratib_user_ensure_login_barcode($conn, $targetUserId);
$legacyCode = $result['ok'] ? (string) ($result['barcode'] ?? '') : '';
$username = (string) ($result['username'] ?? '');
$error = $result['ok'] ? '' : (string) ($result['message'] ?? 'Barcode unavailable.');

$qrPayload = '';
$qrExpires = '';
if ($error === '' && $targetUserId > 0) {
    $issued = ratib_qr_login_issue_token($conn, $targetUserId);
    if (!empty($issued['ok'])) {
        $qrPayload = (string) ($issued['qr_payload'] ?? '');
        $qrExpires = (string) ($issued['expires_at'] ?? '');
    } else {
        $error = (string) ($issued['message'] ?? 'Could not issue secure QR token.');
    }
}

$pageTitle = $username !== '' ? ('Login badge — ' . $username) : 'Login badge';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <style>
        body { background: #f1f5f9; padding: 2rem 1rem; font-family: system-ui, sans-serif; }
        .barcode-card {
            max-width: 420px; margin: 0 auto; background: #fff; border-radius: 16px;
            padding: 1.75rem 1.5rem; box-shadow: 0 12px 40px rgba(15, 23, 42, 0.12);
            text-align: center;
        }
        .barcode-card h1 { font-size: 1.15rem; margin-bottom: 0.25rem; }
        .barcode-card .sub { color: #64748b; font-size: 0.9rem; margin-bottom: 1.25rem; }
        .badge-qr { min-height: 220px; display: flex; align-items: center; justify-content: center; }
        .meta { font-size: 0.8rem; color: #64748b; }
        .actions { margin-top: 1.25rem; display: flex; gap: 0.5rem; justify-content: center; flex-wrap: wrap; }
        @media print {
            body { background: #fff; padding: 0; }
            .actions, .no-print { display: none !important; }
            .barcode-card { box-shadow: none; max-width: 100%; }
        }
    </style>
</head>
<body>
    <div class="barcode-card">
        <h1>RATEB secure login badge</h1>
        <?php if ($username !== ''): ?>
        <p class="sub">User: <strong><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></strong></p>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
        <p class="text-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php else: ?>
        <div id="badge-qr" class="badge-qr mb-2" aria-label="Secure login QR"></div>
        <?php if ($qrExpires !== ''): ?>
        <p class="meta mb-2">Valid until <?php echo htmlspecialchars($qrExpires, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <?php if ($legacyCode !== ''): ?>
        <p class="meta mb-0">Reference ID: <?php echo htmlspecialchars($legacyCode, ENT_QUOTES, 'UTF-8'); ?> (not for scanning)</p>
        <?php endif; ?>
        <p class="small text-muted mt-3 mb-0">At login choose <strong>Barcode</strong>, scan the pairing QR on the computer, then scan this badge with your phone camera.</p>
        <?php endif; ?>

        <div class="actions no-print">
            <button type="button" class="btn btn-primary" onclick="window.print()">Print badge</button>
            <a href="<?php echo htmlspecialchars(pageUrl('system-settings.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary">Back to users</a>
        </div>
    </div>

    <?php if ($qrPayload !== ''): ?>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script>
    (function () {
        var value = <?php echo json_encode($qrPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        var host = document.getElementById('badge-qr');
        if (host && typeof QRCode !== 'undefined') {
            new QRCode(host, { text: value, width: 240, height: 240, correctLevel: QRCode.CorrectLevel.M });
        }
    })();
    </script>
    <?php endif; ?>
</body>
</html>
