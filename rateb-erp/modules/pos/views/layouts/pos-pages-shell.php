<?php
declare(strict_types=1);

$locale = rateb_locale();
$dir = rateb_is_rtl() ? 'rtl' : 'ltr';
$registerUrl = rateb_app_url('pos/register');
$settingsUrl = rateb_app_url('pos/settings');
$pageTitle = (string) ($title ?? __('pos_register'));
$erpRoute = function_exists('rateb_current_erp_route') ? (string) rateb_current_erp_route() : '';
$isSettings = str_contains($erpRoute, 'pos/settings') || str_contains((string) ($_SERVER['REQUEST_URI'] ?? ''), '/pos/settings');
?>
<!DOCTYPE html>
<html lang="<?php echo \Rateb\App\Pos\Support\PosView::escape($locale); ?>" dir="<?php echo $dir; ?>" data-theme-scope="pos" data-theme="dark" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="color-scheme" content="dark light">
    <meta name="rateb-csrf" content="<?php echo \Rateb\App\Pos\Support\PosView::escape(\Rateb\App\Core\Csrf::token()); ?>">
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
    <title><?php echo \Rateb\App\Pos\Support\PosView::escape($pageTitle); ?></title>
    <link href="<?php echo rateb_bootstrap_css(); ?>" rel="stylesheet">
    <link href="<?php echo rateb_pos_asset('css/pos-register.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_pos_asset('css/pos-module.css'); ?>" rel="stylesheet">
</head>
<body class="rateb-pos-shell rateb-pos-shell--pages">
<header class="rateb-pos__header rateb-pos__header--pages" role="banner">
    <div class="rateb-pos__header-start">
        <a class="rateb-pos__brand" href="<?php echo \Rateb\App\Pos\Support\PosView::escape($settingsUrl); ?>" data-rateb-href="<?php echo \Rateb\App\Pos\Support\PosView::escape($settingsUrl); ?>" onclick="return false;">
            <span class="rateb-pos__brand-mark" aria-hidden="true">R</span>
            <span class="rateb-pos__brand-name">RATEB POS</span>
        </a>
        <nav class="rateb-pos__pages-nav" aria-label="POS">
            <a class="rateb-pos__header-back<?php echo $isSettings ? '' : ' is-active'; ?>"
               href="<?php echo \Rateb\App\Pos\Support\PosView::escape($registerUrl); ?>"
               data-rateb-full-nav="1">
                ← <?php echo __('pos_register'); ?>
            </a>
            <a class="rateb-pos__header-nav<?php echo $isSettings ? ' is-active' : ''; ?>"
               href="<?php echo \Rateb\App\Pos\Support\PosView::escape($settingsUrl); ?>"
               data-rateb-href="<?php echo \Rateb\App\Pos\Support\PosView::escape($settingsUrl); ?>"
               onclick="return false;">
                <?php echo __('pos_settings'); ?>
            </a>
        </nav>
        <h1 class="rateb-pos__pages-title"><?php echo \Rateb\App\Pos\Support\PosView::escape($pageTitle); ?></h1>
    </div>
    <div class="rateb-pos__header-end">
        <div class="rateb-pos__lang" role="group" aria-label="<?php echo __('language'); ?>">
            <a href="<?php echo \Rateb\App\Pos\Support\PosView::escape(rateb_locale_switch_url('en')); ?>" class="rateb-pos__lang-btn<?php echo $locale === 'en' ? ' is-active' : ''; ?>" data-locale="en" lang="en" data-rateb-full-nav="1">EN</a>
            <a href="<?php echo \Rateb\App\Pos\Support\PosView::escape(rateb_locale_switch_url('ar')); ?>" class="rateb-pos__lang-btn<?php echo $locale === 'ar' ? ' is-active' : ''; ?>" data-locale="ar" lang="ar" data-rateb-full-nav="1">ع</a>
        </div>
        <div class="rateb-pos__theme" role="group" aria-label="<?php echo __('pos_theme_dark'); ?>">
            <button type="button" class="rateb-pos__theme-btn" data-theme-choice="light" aria-pressed="false" title="<?php echo __('pos_theme_light'); ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
            </button>
            <button type="button" class="rateb-pos__theme-btn" data-theme-choice="dark" aria-pressed="true" title="<?php echo __('pos_theme_dark'); ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </button>
        </div>
    </div>
</header>
<main class="rateb-pos-pages-main" id="rateb-pos-app">
    <?php echo $pageContent; ?>
</main>
<script src="<?php echo rateb_asset('js/theme.js'); ?>" defer></script>
<script src="<?php echo rateb_asset('js/erp-nav-instant.js'); ?>" defer></script>
<script src="<?php echo rateb_pos_asset('js/pos-module.js'); ?>" defer></script>
<script src="<?php echo rateb_pos_asset('js/pos-offline-sync.js'); ?>" defer></script>
<script src="<?php echo rateb_pos_asset('js/pos-shift-offline.js'); ?>" defer></script>
</body>
</html>
