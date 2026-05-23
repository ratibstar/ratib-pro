<?php
declare(strict_types=1);

/**
 * Phone-only scanner: completes barcode login on the PC that showed the pairing QR.
 */
require_once __DIR__ . '/../includes/config.php';

$token = isset($_GET['token']) ? preg_replace('/[^a-f0-9]/', '', strtolower((string) $_GET['token'])) : '';
if (strlen($token) !== 32) {
    http_response_code(400);
    echo 'Invalid or missing login session. Scan the QR code on your computer again.';
    exit;
}

require_once __DIR__ . '/../includes/ratib-barcode-login-pair.php';
$pair = ratib_barcode_pair_read($token);
if ($pair === null || ($pair['status'] ?? '') !== 'pending') {
    http_response_code(410);
    echo 'This login session expired. On your computer, choose Barcode again.';
    exit;
}

$pageTitle = 'Scan barcode — RATEB';
$pairApi = '../api/login-barcode-pair.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/login.css?v=<?php echo (int) @filemtime(__DIR__ . '/../css/login.css'); ?>">
    <style>
        body { background: #0f172a; color: #e2e8f0; min-height: 100vh; padding: 1rem; }
        .scan-card {
            max-width: 420px; margin: 0 auto; background: rgba(15, 23, 42, 0.95);
            border: 1px solid rgba(56, 189, 248, 0.35); border-radius: 16px; padding: 1.25rem;
        }
        .scan-done { color: #34d399; }
    </style>
</head>
<body>
    <div class="scan-card text-center">
        <h1 class="h4 mb-2"><i class="fas fa-qrcode text-info"></i> Scan your badge</h1>
        <p class="small text-muted mb-3">Point your phone at the <strong>QR code</strong> from System Settings → Users. Your computer will sign in — not this phone.</p>
        <div id="barcode-camera-wrap" class="barcode-camera-wrap barcode-camera-wrap--full">
            <div id="barcode-qr-reader" class="barcode-qr-reader" aria-label="Barcode scanner"></div>
        </div>
        <button type="button" class="btn btn-primary btn-lg w-100 mt-3" id="barcode-start-camera">
            <i class="fas fa-camera"></i> Start camera
        </button>
        <div id="barcode-status" class="barcode-status info-message d-block mt-3" role="status">Tap Start camera and allow access.</div>
    </div>
    <script>
    window.RATIB_LOGIN_SCAN = {
        token: <?php echo json_encode($token, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        apiPair: <?php echo json_encode($pairApi, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
    };
    </script>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js" crossorigin="anonymous"></script>
    <script src="../js/login-scan.js?v=<?php echo (int) @filemtime(__DIR__ . '/../js/login-scan.js'); ?>"></script>
</body>
</html>
