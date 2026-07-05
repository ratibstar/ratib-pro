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
</head>
<body class="rateb-pos-shell">
<a class="rateb-pos-skip-link" href="#rateb-pos-register-main"><?php echo __('pos_skip_to_register'); ?></a>
<header class="rateb-pos-shell-header" role="banner">
    <?php \Rateb\App\Pos\Support\PosView::partial('pos-context-bar', ['context' => $context ?? []]); ?>
    <div class="rateb-pos-shell-toolbar">
        <h1 class="rateb-pos-shell-title"><?php echo \Rateb\App\Pos\Support\PosView::escape($title ?? __('pos_register')); ?></h1>
        <div class="rateb-pos-shell-actions">
            <div class="rateb-pos-theme-toggle" role="group" aria-label="<?php echo __('pos_theme_dark'); ?>">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-theme-choice="dark" aria-pressed="true"><?php echo __('pos_theme_dark'); ?></button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-theme-choice="light" aria-pressed="false"><?php echo __('pos_theme_light'); ?></button>
            </div>
            <a class="btn btn-sm btn-outline-secondary" href="<?php echo rateb_app_url('pos/dashboard'); ?>"><?php echo __('pos_dashboard'); ?></a>
        </div>
    </div>
</header>
<main class="rateb-pos-main" id="rateb-pos-app">
    <?php echo $pageContent; ?>
</main>
<script type="application/json" id="rateb-pos-register-config"><?php echo $configJson ?: '{}'; ?></script>
<script src="<?php echo rateb_asset('js/theme.js'); ?>"></script>
<script src="<?php echo rateb_pos_asset('js/pos-module.js'); ?>"></script>
<script src="<?php echo rateb_pos_asset('js/pos-keyboard.js'); ?>"></script>
<script src="<?php echo rateb_pos_asset('js/pos-register.js'); ?>"></script>
<script src="<?php echo rateb_pos_asset('js/pos-register-checkout.js'); ?>"></script>
<script src="<?php echo rateb_pos_asset('js/pos-register-ops.js'); ?>"></script>
</body>
</html>
