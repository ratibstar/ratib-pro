<?php
declare(strict_types=1);

/**
 * Printable workforce identity badge — enterprise layout.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/rateb-user-login-barcode.php';
require_once __DIR__ . '/../includes/rateb-qr-workforce-identity.php';
require_once __DIR__ . '/../includes/rateb-qr-login.php';

if (!isset($_SESSION['user_id'], $_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

$targetUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
if ($targetUserId <= 0) {
    http_response_code(400);
    echo 'Invalid user.';
    exit;
}

$conn = $GLOBALS['conn'] ?? null;
if (!($conn instanceof mysqli)) {
    http_response_code(500);
    echo 'Database unavailable.';
    exit;
}

rateb_user_ensure_login_barcode($conn, $targetUserId);
$issued = rateb_qr_login_ensure_persistent_token($conn, $targetUserId, false);
$wf = rateb_qr_workforce_status($conn, $targetUserId);
$username = (string) ($wf['username'] ?? '');
$legacyRef = (string) ($wf['legacy_ref'] ?? '');
$qrPayload = (string) ($issued['qr_payload'] ?? '');
$showQrOnce = $qrPayload !== '';
$badgeUrl = $showQrOnce ? rateb_qr_login_badge_url($qrPayload, rateb_qr_login_badge_tenant_context()) : '';

if (!$showQrOnce && ($wf['qr_status'] ?? '') === 'active') {
    $badgeUrl = '';
}

$company = defined('SITE_NAME') ? (string) SITE_NAME : 'RATEB';
$pageTitle = 'Workforce badge — ' . ($username !== '' ? $username : 'User');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, sans-serif; background: #e2e8f0; margin: 0; padding: 1.5rem; color: #0f172a; }
        .badge-sheet { max-width: 400px; margin: 0 auto; background: #fff; border: 1px solid #cbd5e1; border-radius: 4px; overflow: hidden; }
        .badge-head { background: #0f172a; color: #fff; padding: 0.75rem 1rem; text-align: center; }
        .badge-head h1 { margin: 0; font-size: 0.95rem; letter-spacing: 0.12em; font-weight: 600; }
        .badge-head p { margin: 0.25rem 0 0; font-size: 0.7rem; opacity: 0.85; }
        .badge-body { padding: 1.25rem 1rem; text-align: center; }
        .badge-name { font-size: 1.1rem; font-weight: 600; margin-bottom: 0.15rem; }
        .badge-ref { font-size: 0.75rem; color: #64748b; margin-bottom: 1rem; }
        .badge-qr { min-height: 220px; display: flex; align-items: center; justify-content: center; }
        .badge-foot { font-size: 0.65rem; color: #64748b; padding: 0 1rem 1rem; line-height: 1.4; }
        .actions { text-align: center; margin-top: 1rem; display: flex; gap: 0.5rem; justify-content: center; flex-wrap: wrap; }
        .btn { border: 1px solid #334155; background: #fff; padding: 0.4rem 0.85rem; font-size: 0.8rem; cursor: pointer; border-radius: 4px; }
        .btn-primary { background: #0f172a; color: #fff; border-color: #0f172a; }
        @media print {
            body { background: #fff; padding: 0; }
            .actions { display: none !important; }
            .badge-sheet { border: none; max-width: 100%; }
        }
    </style>
</head>
<body>
    <div class="badge-sheet" id="badge-sheet">
        <div class="badge-head">
            <h1><?php echo htmlspecialchars($company, ENT_QUOTES, 'UTF-8'); ?></h1>
            <p>Workforce identity credential</p>
        </div>
        <div class="badge-body">
            <div class="badge-name"><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php if ($legacyRef !== ''): ?>
            <div class="badge-ref">Employee ref. <?php echo htmlspecialchars($legacyRef, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <div id="badge-qr" class="badge-qr">
                <?php if (!$showQrOnce && ($wf['qr_status'] ?? '') === 'active'): ?>
                <p style="font-size:0.8rem;color:#64748b;">QR credential is active.<br>Use <strong>Regenerate</strong> in admin to display a new printable QR.</p>
                <?php elseif (($wf['qr_status'] ?? '') !== 'active'): ?>
                <p style="font-size:0.8rem;color:#b91c1c;">No active credential. Generate in System Settings → Users.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="badge-foot">
            Scan at RATEB login → Barcode. Do not share or photograph this badge. Report loss to administrator immediately.
        </div>
    </div>
    <div class="actions no-print">
        <button type="button" class="btn btn-primary" onclick="window.print()">Print / PDF</button>
        <button type="button" class="btn" id="btn-png">Download PNG</button>
    </div>
    <link rel="stylesheet" href="<?php echo function_exists('asset') ? asset('css/rateb-qr-image.css') : '/css/rateb-qr-image.css'; ?>">
    <?php if ($showQrOnce && $badgeUrl !== ''): ?>
    <script src="<?php echo function_exists('asset') ? asset('js/rateb-qr-image.js') : '/js/rateb-qr-image.js'; ?>"></script>
    <script>
    (function () {
        var url = <?php echo json_encode($badgeUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        var host = document.getElementById('badge-qr');
        if (host && typeof ratebRenderQrImage === 'function') {
            ratebRenderQrImage(host, url, 300);
        }
        document.getElementById('btn-png').addEventListener('click', function () {
            var img = host && host.querySelector('.rateb-qr-image');
            if (!img || !img.src) return;
            var a = document.createElement('a');
            a.download = 'rateb-workforce-badge.png';
            a.href = img.src;
            a.click();
        });
    })();
    </script>
    <?php endif; ?>
</body>
</html>
