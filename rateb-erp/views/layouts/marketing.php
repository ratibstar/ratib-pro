<?php
$locale = rateb_locale();
$dir = rateb_is_rtl() ? 'rtl' : 'ltr';
$meta = $meta ?? [];
$theme = $theme ?? [];
$primary = $theme['primary_color'] ?? '#1a5fb4';
$secondary = $theme['secondary_color'] ?? '#3584e4';
$isPortalLayout = !empty($isPortalPage);
$headerContext = 'marketing';
?>
<!DOCTYPE html>
<html lang="<?php echo Rateb\App\Core\View::escape($locale); ?>" dir="<?php echo $dir; ?>" data-theme="light" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="<?php echo rateb_asset('js/marketing-head.js'); ?>"></script>
    <title><?php echo Rateb\App\Core\View::escape($meta['title'] ?? ($title ?? 'RATEB ERP')); ?></title>
    <?php if (!empty($meta['description'])) { ?>
    <meta name="description" content="<?php echo Rateb\App\Core\View::escape($meta['description']); ?>">
    <?php } ?>
    <?php if (!empty($meta['canonical'])) { ?>
    <link rel="canonical" href="<?php echo Rateb\App\Core\View::escape($meta['canonical']); ?>">
    <?php } ?>
    <meta property="og:title" content="<?php echo Rateb\App\Core\View::escape($meta['og_title'] ?? ($title ?? '')); ?>">
    <meta property="og:description" content="<?php echo Rateb\App\Core\View::escape($meta['og_description'] ?? ''); ?>">
    <meta property="og:type" content="website">
    <?php if (!empty($meta['og_image'])) { ?>
    <meta property="og:image" content="<?php echo Rateb\App\Core\View::escape($meta['og_image']); ?>">
    <meta name="twitter:image" content="<?php echo Rateb\App\Core\View::escape($meta['og_image']); ?>">
    <?php } ?>
    <meta name="twitter:card" content="<?php echo Rateb\App\Core\View::escape($meta['twitter_card'] ?? 'summary_large_image'); ?>">
    <link href="<?php echo rateb_tajawal_font_css(); ?>" rel="stylesheet">
    <?php if ($dir === 'rtl') { ?>
    <link href="<?php echo rateb_bootstrap_css(); ?>" rel="stylesheet">
    <?php } else { ?>
    <link href="<?php echo rateb_bootstrap_css(); ?>" rel="stylesheet">
    <?php } ?>
    <link href="<?php echo rateb_fontawesome_css(); ?>" rel="stylesheet">
    <link href="<?php echo rateb_asset('css/variables.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_asset('css/marketing.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_asset('css/marketing-dark.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_asset('css/marketing-rtl.css'); ?>" rel="stylesheet">
    <?php if (!empty($legacyHomePort)) {
        $legacyCssOrigin = \Rateb\App\Services\LegacyHomeContentService::assetOrigin();
        ?>
    <link href="<?php echo Rateb\App\Core\View::escape($legacyCssOrigin . '/css/pages/home-marketing-focused.css?v=unified-home'); ?>" rel="stylesheet">
    <link href="<?php echo Rateb\App\Core\View::escape($legacyCssOrigin . '/css/pages/home-public.css?v=unified-home'); ?>" rel="stylesheet">
    <link href="<?php echo Rateb\App\Core\View::escape($legacyCssOrigin . '/css/pages/enterprise-trust-layer.css?v=unified-home'); ?>" rel="stylesheet">
    <link href="<?php echo Rateb\App\Core\View::escape($legacyCssOrigin . '/css/pages/operational-proof.css?v=unified-home'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_asset('css/marketing-home-legacy.css'); ?>" rel="stylesheet">
    <?php } ?>
    <?php
    $hrefOrigin = rateb_site_origin();
    $hrefPath = rateb_current_public_path('site');
    $hrefCanonical = $hrefOrigin . rateb_url($hrefPath);
    ?>
    <link rel="alternate" hreflang="en" href="<?php echo Rateb\App\Core\View::escape($hrefCanonical); ?>">
    <link rel="alternate" hreflang="ar" href="<?php echo Rateb\App\Core\View::escape($hrefCanonical); ?>">
    <link rel="alternate" hreflang="x-default" href="<?php echo Rateb\App\Core\View::escape($hrefOrigin . rateb_url('site')); ?>">
    <link href="<?php echo rateb_asset('css/marketing-agency-register.css'); ?>" rel="stylesheet">
    <?php require RATEB_ROOT . '/views/marketing/partials/analytics-head.php'; ?>
</head>
<body class="rateb-marketing<?php echo $dir === 'rtl' ? ' rateb-marketing-rtl' : ''; ?>">
<?php
$gtmId = !empty($analytics['google_tag_manager_id']) ? (string) $analytics['google_tag_manager_id'] : '';
if ($gtmId !== '') {
    $gtmEsc = Rateb\App\Core\View::escape($gtmId);
    echo '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . $gtmEsc . '" height="0" width="0" class="rateb-mkt-gtm-noscript" title="GTM"></iframe></noscript>';
}
?>
<?php
if (($headerContext ?? 'marketing') === 'marketing') {
    require RATEB_ROOT . '/views/marketing/partials/topbar.php';
}
require RATEB_ROOT . '/views/marketing/partials/header.php'; ?>
<main id="rateb-marketing-main">
    <?php Rateb\App\Core\View::partial('flash'); ?>
    <?php echo $pageContent; ?>
</main>
<?php require RATEB_ROOT . '/views/marketing/partials/footer.php'; ?>
<script src="<?php echo rateb_bootstrap_js(); ?>"></script>
<script src="<?php echo rateb_asset('js/marketing.js'); ?>"></script>
<script>window.RATEB_BASE_URL = <?php echo json_encode(rateb_site_origin()); ?>;</script>
<script src="<?php echo htmlspecialchars(rateb_site_origin()); ?>/js/payment.js?v=<?php
$ratebPayJs = dirname(RATEB_ROOT) . '/js/payment.js';
echo (int) (is_file($ratebPayJs) ? @filemtime($ratebPayJs) : time());
?>"></script>
<script src="<?php echo rateb_asset('js/marketing-agency-register.js'); ?>"></script>
<?php
$analytics = $analytics ?? null;
if ($analytics && !empty($analytics['custom_body_code'])) {
    echo \Rateb\App\Core\HtmlSanitizer::sanitizeAnalyticsEmbed((string) $analytics['custom_body_code']);
}
?>
</body>
</html>
