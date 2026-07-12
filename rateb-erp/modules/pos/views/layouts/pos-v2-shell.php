<?php
declare(strict_types=1);

$locale = $v2Config['locale'] ?? rateb_locale();
$dir = !empty($v2Config['rtl']) ? 'rtl' : 'ltr';
$configJson = json_encode($v2Config ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$bsCss = rateb_bootstrap_css();
$fontStack = rateb_pos_fonts_css();
?>
<!DOCTYPE html>
<html lang="<?php echo \Rateb\App\Pos\Support\PosView::escape($locale); ?>" dir="<?php echo $dir; ?>" data-theme-scope="pos" data-theme="dark" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="color-scheme" content="dark light">
    <meta name="rateb-csrf" content="<?php echo \Rateb\App\Pos\Support\PosView::escape($v2Config['csrf'] ?? \Rateb\App\Core\Csrf::token()); ?>">
    <script>
    (function () {
        try {
            var mode = localStorage.getItem('rateb_pos_theme') || 'dark';
            var bs = mode === 'light' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', mode);
            document.documentElement.setAttribute('data-bs-theme', bs);
        } catch (e) {}
    })();
    </script>
    <title><?php echo \Rateb\App\Pos\Support\PosView::escape($title ?? __('pos_register')); ?> — POS V2</title>
    <link href="<?php echo $fontStack; ?>" rel="stylesheet">
    <link href="<?php echo $bsCss; ?>" rel="stylesheet">
    <link href="<?php echo rateb_pos_asset('v2/pos-v2.css'); ?>" rel="stylesheet">
</head>
<body class="pos-v2-body">
<main class="pos-v2-app" id="pos-v2-app">
    <?php echo $pageContent; ?>
</main>
<script type="application/json" id="pos-v2-config"><?php echo $configJson ?: '{}'; ?></script>
<script src="<?php echo rateb_asset('js/theme.js'); ?>" defer></script>
<script src="<?php echo rateb_bootstrap_js(); ?>" defer></script>
<script type="module" src="<?php echo rateb_pos_asset('v2/app.js'); ?>"></script>
</body>
</html>
