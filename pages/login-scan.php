<?php
declare(strict_types=1);

/**
 * Enterprise QR camera scanner — pairs with desktop login or direct mobile sign-in.
 * Routes: /{country}/login/scan, /login/scan, /{country}/workforce/scan
 */
require_once __DIR__ . '/../includes/config.php';

$pairToken = isset($_GET['token']) ? preg_replace('/[^a-f0-9]/', '', strtolower((string) $_GET['token'])) : '';
$hasPair = strlen($pairToken) === 32;
$mode = isset($_GET['mode']) ? trim((string) $_GET['mode']) : '';

if ($hasPair) {
    require_once __DIR__ . '/../includes/ratib-barcode-login-pair.php';
    $pair = ratib_barcode_pair_read($pairToken);
    if ($pair === null || ($pair['status'] ?? '') !== 'pending') {
        http_response_code(410);
        echo 'Session expired. On your computer, choose Barcode again.';
        exit;
    }
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
    setcookie('ratib_pair', $pairToken, [
        'expires' => time() + 600,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

$autoBadge = isset($_GET['d']) ? trim((string) $_GET['d']) : '';
if ($autoBadge === '' && isset($_GET['badge'])) {
    $autoBadge = trim((string) $_GET['badge']);
}

$pageTitle = $hasPair ? 'Scan badge — RATEB' : 'QR check-in — RATEB';
$cssV = (int) @filemtime(__DIR__ . '/../css/qr-scan.css');
$jsScanV = (int) @filemtime(__DIR__ . '/../js/login-scan.js');
$jsLibV = (int) @filemtime(__DIR__ . '/../js/ratib-qr-scanner.js');

$cssUrl = function_exists('asset') ? asset('css/qr-scan.css') : '/css/qr-scan.css';
$jsScannerUrl = function_exists('asset') ? asset('js/ratib-qr-scanner.js') : '/js/ratib-qr-scanner.js';
$jsScanUrl = function_exists('asset') ? asset('js/login-scan.js') : '/js/login-scan.js';

$apiQr = '/api/qr-login.php';
$apiPair = '/api/login-barcode-pair.php';

$ctxCountryId = isset($_GET['country_id']) ? (int) $_GET['country_id'] : 0;
$ctxAgencyId = isset($_GET['agency_id']) ? (int) $_GET['agency_id'] : 0;
$ctxCountrySlug = isset($_GET['country_slug']) ? preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $_GET['country_slug'])) : '';
if ($hasPair && is_array($pair['context'] ?? null)) {
    $pctx = $pair['context'];
    if ($ctxCountryId <= 0) {
        $ctxCountryId = (int) ($pctx['country_id'] ?? 0);
    }
    if ($ctxAgencyId <= 0) {
        $ctxAgencyId = (int) ($pctx['agency_id'] ?? 0);
    }
    if ($ctxCountrySlug === '' && !empty($pctx['country_slug'])) {
        $ctxCountrySlug = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $pctx['country_slug']));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex,nofollow">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($cssUrl, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo $cssV; ?>">
    <meta name="theme-color" content="#0b1220">
</head>
<body class="qr-scan-page">
    <div class="qr-scan-shell">
        <p class="qr-scan-brand">RATEB</p>
        <h1 class="qr-scan-title"><?php echo $hasPair ? 'Step 2 — Scan employee badge' : 'QR check-in'; ?></h1>
        <?php if ($hasPair): ?>
        <ol class="qr-scan-steps">
            <li class="qr-scan-steps-done">Open this page (you scanned the computer QR)</li>
            <li class="qr-scan-steps-active"><strong>Now</strong> scan your badge once — it will be <strong>saved on this phone</strong> for next time</li>
        </ol>
        <?php endif; ?>
        <p class="qr-scan-sub">
            <?php if ($hasPair): ?>
            <strong>Do not point at the computer login screen.</strong><br>
            On the laptop: open <strong>System Settings → Users</strong> → click <strong>Access</strong> on your user → scan the QR in that panel (or print it).
            <?php else: ?>
            Sign in with your workforce badge from System Settings → Users.
            <?php endif; ?>
        </p>
        <div id="qr-scan-wrong-banner" class="qr-scan-wrong-banner d-none" role="alert">
            Wrong QR — you scanned the computer login screen. Open <strong>Users → Access</strong> and scan that badge instead.
        </div>
        <div id="qr-scan-saved-panel" class="qr-scan-saved-panel d-none" role="region" aria-label="Badge saved on this phone">
            <p class="qr-scan-saved-title"><i class="fas fa-id-badge"></i> Access badge saved on this phone</p>
            <p class="qr-scan-saved-meta mb-2" id="qr-scan-saved-meta"></p>
            <button type="button" class="qr-scan-btn qr-scan-btn-primary w-100 mb-2" id="qr-scan-use-saved">
                Sign in computer now
            </button>
            <button type="button" class="qr-scan-btn qr-scan-btn-ghost w-100" id="qr-scan-scan-new">
                Scan a different badge
            </button>
        </div>
        <div id="qr-scan-viewport" class="qr-scan-viewport" aria-label="Camera scanner"></div>
        <div class="qr-scan-actions">
            <button type="button" class="qr-scan-btn qr-scan-btn-primary" id="qr-scan-start">
                <i class="fas fa-camera"></i> Start camera
            </button>
            <button type="button" class="qr-scan-btn qr-scan-btn-ghost d-none" id="qr-scan-stop">
                Stop
            </button>
        </div>
        <?php if ($hasPair): ?>
        <label class="qr-scan-trust-label qr-scan-trust-label--main">
            <input type="checkbox" id="qr-scan-trust" checked> Remember this computer for 30 days (skip phone next time)
        </label>
        <?php endif; ?>
        <div id="qr-scan-pin-panel" class="qr-scan-pin-panel d-none">
            <label for="qr-scan-pin">Enter 4-digit PIN</label>
            <input type="password" id="qr-scan-pin" class="qr-scan-pin-input" maxlength="4" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
            <label class="qr-scan-trust-label"><input type="checkbox" id="qr-scan-trust-pin" checked> Trust this device (30 days)</label>
            <button type="button" class="qr-scan-btn qr-scan-btn-primary" id="qr-scan-pin-submit">Continue</button>
        </div>
        <div id="qr-scan-status" class="qr-scan-status qr-scan-status--info" role="status">
            Tap Start camera and allow access when prompted.
        </div>
        <?php if ($hasPair): ?>
        <div class="qr-scan-alt-help">
            <p class="qr-scan-alt-title"><i class="fas fa-lightbulb"></i> Camera not reading the screen?</p>
            <ol>
                <li>On laptop: <strong>Workforce access</strong> → <strong>Copy badge link</strong></li>
                <li>Paste the link in this phone’s browser (or Notes), tap it</li>
                <li>That signs in the computer — no camera scan needed</li>
            </ol>
            <p class="qr-scan-alt-note">Or use <strong>Download PNG</strong> / <strong>Print badge</strong> and scan the paper.</p>
        </div>
        <?php endif; ?>
    </div>
    <script>
    window.RATIB_QR_SCAN = <?php echo json_encode([
        'pairToken' => $hasPair ? $pairToken : '',
        'apiQr' => $apiQr,
        'apiPair' => $apiPair,
        'countryId' => $ctxCountryId,
        'agencyId' => $ctxAgencyId,
        'countrySlug' => $ctxCountrySlug,
        'mode' => $mode,
        'autoBadge' => $autoBadge,
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    </script>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js" crossorigin="anonymous"></script>
    <?php
    $jsMobileBadgeV = (int) @filemtime(__DIR__ . '/../js/ratib-mobile-badge-store.js');
    $jsMobileBadgeUrl = function_exists('asset') ? asset('js/ratib-mobile-badge-store.js') : '/js/ratib-mobile-badge-store.js';
    ?>
    <script src="<?php echo htmlspecialchars($jsMobileBadgeUrl, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo $jsMobileBadgeV; ?>"></script>
    <script src="<?php echo htmlspecialchars($jsScannerUrl, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo $jsLibV; ?>"></script>
    <script src="<?php echo htmlspecialchars($jsScanUrl, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo $jsScanV; ?>"></script>
</body>
</html>
