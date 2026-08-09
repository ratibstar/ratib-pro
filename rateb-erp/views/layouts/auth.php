<?php
$locale = rateb_locale();
$dir = rateb_is_rtl() ? 'rtl' : 'ltr';
?>
<!DOCTYPE html>
<html lang="<?php echo Rateb\App\Core\View::escape($locale); ?>" dir="<?php echo $dir; ?>" data-theme-scope="erp" data-theme="dark" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="rateb-csrf" content="<?php echo Rateb\App\Core\View::escape(\Rateb\App\Core\Csrf::token()); ?>">
    <script>
    (function () {
        try {
            var mode = localStorage.getItem('rateb_erp_theme') || localStorage.getItem('rateb_theme') || 'dark';
            var bs = mode === 'light' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', mode);
            document.documentElement.setAttribute('data-bs-theme', bs);
        } catch (e) {}
    })();
    </script>
    <title><?php echo Rateb\App\Core\View::escape($title ?? __('login')); ?> | <?php echo __('rateb_erp'); ?></title>
    <link rel="icon" href="<?php echo rateb_public_url('favicon.ico'); ?>" type="image/svg+xml">
    <link href="<?php echo rateb_tajawal_font_css(); ?>" rel="stylesheet">
    <?php if ($dir === 'rtl') { ?>
    <link href="<?php echo rateb_bootstrap_css(); ?>" rel="stylesheet">
    <?php } else { ?>
    <link href="<?php echo rateb_bootstrap_css(); ?>" rel="stylesheet">
    <?php } ?>
    <link href="<?php echo rateb_fontawesome_css(); ?>" rel="stylesheet">
    <link href="<?php echo rateb_asset('css/variables.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_asset('css/main.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_asset('css/dark.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_asset('css/rtl.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_asset('css/login-barcode.css'); ?>" rel="stylesheet">
</head>
<body class="rateb-auth-page">
    <div class="rateb-auth-card">
        <div class="text-center mb-3">
            <div class="d-flex justify-content-center gap-2 mb-3">
                <div class="btn-group btn-group-sm">
                    <a href="<?php echo htmlspecialchars(function_exists('rateb_locale_switch_url') ? rateb_locale_switch_url('en') : rateb_erp_locale_base_url('en'), ENT_QUOTES, 'UTF-8'); ?>"
                       class="btn btn-outline-secondary<?php echo $locale === 'en' ? ' active' : ''; ?>"
                       data-rateb-full-nav="1">EN</a>
                    <a href="<?php echo htmlspecialchars(function_exists('rateb_locale_switch_url') ? rateb_locale_switch_url('ar') : rateb_erp_locale_base_url('ar'), ENT_QUOTES, 'UTF-8'); ?>"
                       class="btn btn-outline-secondary<?php echo $locale === 'ar' ? ' active' : ''; ?>"
                       data-rateb-full-nav="1">عربي</a>
                </div>
            </div>
            <i class="fas fa-hospital fa-2x text-primary mb-2"></i>
            <h2 class="h4"><?php echo __('rateb_erp'); ?></h2>
        </div>
        <?php Rateb\App\Core\View::partial('flash'); ?>
        <?php echo $pageContent; ?>
    </div>
    <script src="<?php echo rateb_bootstrap_js(); ?>"></script>
    <script src="<?php echo rateb_asset('js/theme.js'); ?>"></script>
    <script>
    (function () {
        try {
            if (!('serviceWorker' in navigator)) return;
            // Only ask SW to drop cached auth HTML — never force navigation to login?err=session
            // (that path previously purged cookies and logged users out on every icon / F5).
            var purge = function () {
                if (navigator.serviceWorker.controller) {
                    navigator.serviceWorker.controller.postMessage({ type: 'PURGE_ERP_AUTH_CACHE' });
                }
            };
            purge();
            navigator.serviceWorker.ready.then(function (reg) {
                if (reg.active) {
                    reg.active.postMessage({ type: 'PURGE_ERP_AUTH_CACHE' });
                }
            }).catch(function () {});
        } catch (e) {}
    })();
    </script>
</body>
</html>
