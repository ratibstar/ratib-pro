<?php
declare(strict_types=1);

$locale = rateb_locale();
$dir = rateb_is_rtl() ? 'rtl' : 'ltr';
$configJson = json_encode($registerConfig ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$fontLatin = 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap';
$fontArabic = 'https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap';
?>
<!DOCTYPE html>
<html lang="<?php echo \Rateb\App\Pos\Support\PosView::escape($locale); ?>" dir="<?php echo $dir; ?>" data-theme="dark" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="color-scheme" content="dark light">
    <meta name="rateb-csrf" content="<?php echo \Rateb\App\Pos\Support\PosView::escape(\Rateb\App\Core\Csrf::token()); ?>">
    <title><?php echo \Rateb\App\Pos\Support\PosView::escape($title ?? __('pos_register')); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="<?php echo $fontLatin; ?>" rel="stylesheet">
    <link href="<?php echo $fontArabic; ?>" rel="stylesheet">
    <link href="<?php echo rateb_pos_asset('css/pos-register.css'); ?>" rel="stylesheet">
</head>
<body class="rateb-pos-shell">
<main class="rateb-pos-main" id="rateb-pos-app">
    <?php echo $pageContent; ?>
</main>
<script type="application/json" id="rateb-pos-register-config"><?php echo $configJson ?: '{}'; ?></script>
<script src="<?php echo rateb_asset('js/theme.js'); ?>"></script>
<script src="<?php echo rateb_pos_asset('js/pos-module.js'); ?>"></script>
<script src="<?php echo rateb_pos_asset('js/pos-keyboard.js'); ?>"></script>
<script src="<?php echo rateb_pos_asset('js/pos-register.js'); ?>"></script>
<script src="<?php echo rateb_pos_asset('js/pos-register-motion.js'); ?>"></script>
<script src="<?php echo rateb_pos_asset('js/pos-register-tiles.js'); ?>"></script>
<script src="<?php echo rateb_pos_asset('js/pos-register-checkout.js'); ?>"></script>
<script src="<?php echo rateb_pos_asset('js/pos-register-ops.js'); ?>"></script>
</body>
</html>
