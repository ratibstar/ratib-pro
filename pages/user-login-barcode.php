<?php
declare(strict_types=1);

/**
 * View / print a user's login barcode (System Settings → Users → Barcode).
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/ratib-user-login-barcode.php';

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
$barcode = $result['ok'] ? (string) ($result['barcode'] ?? '') : '';
$username = (string) ($result['username'] ?? '');
$error = $result['ok'] ? '' : (string) ($result['message'] ?? 'Barcode unavailable.');
$pageTitle = $username !== '' ? ('Login barcode — ' . $username) : 'Login barcode';
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
        #barcode-svg { max-width: 100%; min-height: 72px; }
        .code-text {
            font-family: ui-monospace, monospace; font-size: 1.1rem; letter-spacing: 0.08em;
            margin-top: 0.75rem; word-break: break-all;
        }
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
        <h1>RATEB login barcode</h1>
        <?php if ($username !== ''): ?>
        <p class="sub">User: <strong><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></strong></p>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
        <p class="text-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php else: ?>
        <svg id="barcode-svg" aria-label="Login barcode"></svg>
        <div class="code-text" id="barcode-plain"><?php echo htmlspecialchars($barcode, ENT_QUOTES, 'UTF-8'); ?></div>
        <p class="small text-muted mt-3 mb-0">Print this card and scan it at the login page (Barcode method).</p>
        <?php endif; ?>

        <div class="actions no-print">
            <button type="button" class="btn btn-primary" onclick="window.print()">Print</button>
            <a href="<?php echo htmlspecialchars(pageUrl('system-settings.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary">Back to users</a>
        </div>
    </div>

    <?php if ($barcode !== ''): ?>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <script>
    (function () {
        var value = <?php echo json_encode($barcode, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        try {
            JsBarcode('#barcode-svg', value, {
                format: 'CODE128',
                width: 2,
                height: 72,
                displayValue: false,
                margin: 8
            });
        } catch (e) {
            document.getElementById('barcode-plain').textContent = value + ' (render failed — use printed code text)';
        }
    })();
    </script>
    <?php endif; ?>
</body>
</html>
