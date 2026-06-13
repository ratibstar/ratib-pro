<!DOCTYPE html>
<html lang="<?php echo Rateb\App\Core\View::escape(rateb_locale()); ?>" dir="<?php echo rateb_is_rtl() ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex,nofollow">
    <title><?php echo Rateb\App\Core\View::escape($title ?? __('barcode_scan_title')); ?> | <?php echo __('rateb_erp'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="<?php echo rateb_asset('css/qr-scan.css'); ?>" rel="stylesheet">
    <meta name="theme-color" content="#0b1220">
</head>
<body class="qr-scan-page">
<?php if (!$tokenValid) { ?>
    <div class="qr-scan-shell text-center">
        <p class="qr-scan-brand"><?php echo __('rateb_erp'); ?></p>
        <h1 class="qr-scan-title"><?php echo __('barcode_scan_title'); ?></h1>
        <p class="qr-scan-status qr-scan-status--error"><?php echo __('barcode_pair_expired'); ?></p>
        <a href="<?php echo rateb_url('login'); ?>" class="qr-scan-btn qr-scan-btn-primary d-inline-block mt-3" style="text-decoration:none;line-height:3rem;"><?php echo __('back_to_login'); ?></a>
    </div>
<?php } else { ?>
    <div class="qr-scan-shell">
        <p class="qr-scan-brand"><?php echo __('rateb_erp'); ?></p>
        <h1 class="qr-scan-title"><?php echo __('barcode_scan_step2_title'); ?></h1>
        <ol class="qr-scan-steps">
            <li class="qr-scan-steps-done"><?php echo __('barcode_scan_step_done'); ?></li>
            <li class="qr-scan-steps-active"><strong><?php echo __('barcode_scan_step_now'); ?></strong></li>
        </ol>
        <p class="qr-scan-sub">
            <strong><?php echo __('barcode_scan_no_desktop'); ?></strong><br>
            <?php echo __('barcode_scan_admin_hint'); ?>
        </p>
        <p class="qr-scan-sub" style="color:#6ee7b7;font-weight:600;">
            <?php echo __('badge_scan_this'); ?>
        </p>
        <div id="qr-scan-wrong-banner" class="qr-scan-wrong-banner d-none" role="alert">
            <?php echo __('barcode_pairing_qr_error'); ?>
        </div>
        <div id="qr-scan-viewport" class="qr-scan-viewport" aria-label="Camera scanner"></div>
        <div class="qr-scan-actions">
            <button type="button" class="qr-scan-btn qr-scan-btn-primary" id="qr-scan-start">
                <i class="fas fa-camera"></i> <?php echo __('barcode_start_camera'); ?>
            </button>
            <button type="button" class="qr-scan-btn qr-scan-btn-ghost d-none" id="qr-scan-stop">
                <?php echo __('barcode_stop_camera'); ?>
            </button>
        </div>
        <div id="qr-scan-status" class="qr-scan-status qr-scan-status--info" role="status">
            <?php echo __('barcode_camera_prompt'); ?>
        </div>
        <div class="qr-scan-alt-help mt-3">
            <p class="qr-scan-alt-title"><i class="fas fa-keyboard"></i> <?php echo __('barcode_manual_title'); ?></p>
            <p class="mb-2"><?php echo __('barcode_scan_hint'); ?></p>
            <form id="qr-scan-manual-form">
                <input type="text" id="qr-scan-manual-input" class="qr-scan-manual-input" autocomplete="off"
                    placeholder="<?php echo Rateb\App\Core\View::escape(__('login_barcode_placeholder')); ?>">
                <button type="submit" class="qr-scan-btn qr-scan-btn-primary w-100 mt-2">
                    <i class="fas fa-check"></i> <?php echo __('barcode_scan_submit'); ?>
                </button>
            </form>
        </div>
    </div>
    <script>
    window.RATEB_QR_SCAN = <?php echo json_encode([
        'pairToken' => $token,
        'apiQr' => rateb_url('api/qr-login'),
        'apiPair' => rateb_url('api/login-barcode-pair'),
        'autoBadge' => $autoBadge ?? '',
        'cameraPrompt' => __('barcode_camera_prompt'),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js" crossorigin="anonymous"></script>
    <?php
    $scanAssets = ['js/erp-qr-scanner.js', 'js/erp-login-scan.js'];
    foreach ($scanAssets as $asset) {
        $path = (defined('RATEB_ROOT') ? RATEB_ROOT : '') . '/public/assets/' . $asset;
        if ($path !== '/public/assets/' . $asset && is_file($path)) {
            echo '<script>', file_get_contents($path), '</script>';
        }
    }
    ?>
<?php } ?>
</body>
</html>
