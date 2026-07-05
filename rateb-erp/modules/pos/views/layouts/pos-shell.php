<?php
declare(strict_types=1);

$locale = rateb_locale();
$dir = rateb_is_rtl() ? 'rtl' : 'ltr';
$configJson = json_encode($registerConfig ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html lang="<?php echo \Rateb\App\Pos\Support\PosView::escape($locale); ?>" dir="<?php echo $dir; ?>" data-theme="dark" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="color-scheme" content="dark light">
    <meta name="rateb-csrf" content="<?php echo \Rateb\App\Pos\Support\PosView::escape(\Rateb\App\Core\Csrf::token()); ?>">
    <title><?php echo \Rateb\App\Pos\Support\PosView::escape($title ?? __('pos_register')); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="<?php echo rateb_asset('css/variables.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_asset('css/main.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_asset('css/components.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_asset('css/dark.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_asset('css/rtl.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_asset('css/light.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_pos_asset('css/pos-module.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_pos_asset('css/pos-register.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_pos_asset('css/pos-touch.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_pos_asset('css/pos-register-checkout.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_pos_asset('css/pos-register-ops.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_pos_asset('css/pos-register-premium.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_pos_asset('css/pos-register-motion.css'); ?>" rel="stylesheet">
</head>
<body class="rateb-pos-shell rateb-pos-premium">
<a class="rateb-pos-skip-link" href="#rateb-pos-register-main"><?php echo __('pos_skip_to_register'); ?></a>
<?php \Rateb\App\Pos\Support\PosView::partial('pos-premium-toolbar', ['context' => $context ?? [], 'locale' => $locale]); ?>
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
