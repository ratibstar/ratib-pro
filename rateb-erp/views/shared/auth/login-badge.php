<!DOCTYPE html>
<html lang="<?php echo Rateb\App\Core\View::escape(rateb_locale()); ?>" dir="<?php echo rateb_is_rtl() ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title><?php echo Rateb\App\Core\View::escape($title ?? __('login_badge')); ?> | <?php echo __('rateb_erp'); ?></title>
    <link href="<?php echo rateb_asset('css/qr-scan.css'); ?>" rel="stylesheet">
    <style>body { padding: 2rem 1rem; }</style>
</head>
<body class="qr-scan-page">
    <div class="badge-card qr-scan-shell text-center">
        <h1 class="qr-scan-title"><?php echo __('login_badge'); ?></h1>
        <p id="badge-msg" class="qr-scan-status qr-scan-status--info"><?php echo __('barcode_validating'); ?></p>
    </div>
    <script>
    window.RATEB_BADGE = <?php echo json_encode([
        'payload' => $payload,
        'pairToken' => $pairToken,
        'directLogin' => $directLogin,
        'apiQr' => rateb_url('api/qr-login'),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    </script>
    <?php
    foreach (['js/erp-mobile-badge-store.js', 'js/erp-login-badge.js'] as $asset) {
        $path = (defined('RATEB_ROOT') ? RATEB_ROOT : '') . '/public/assets/' . $asset;
        if ($path !== '/public/assets/' . $asset && is_file($path)) {
            echo '<script>', file_get_contents($path), '</script>';
        }
    }
    ?>
</body>
</html>
