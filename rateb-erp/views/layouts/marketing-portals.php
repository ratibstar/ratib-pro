<?php
$locale = rateb_locale();
$dir = rateb_is_rtl() ? 'rtl' : 'ltr';
$meta = $meta ?? [];
$portalType = $portalType ?? 'customer';
$hrefOrigin = rateb_site_origin();
$hrefPath = rateb_current_public_path('site');
$hrefCanonical = $hrefOrigin . rateb_url($hrefPath);
?>
<!DOCTYPE html>
<html lang="<?php echo Rateb\App\Core\View::escape($locale); ?>" dir="<?php echo $dir; ?>" data-theme="light" data-bs-theme="light" data-portal-layout="1" data-portal-type="<?php echo Rateb\App\Core\View::escape($portalType); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="<?php echo rateb_asset('js/marketing-head.js'); ?>"></script>
    <title><?php echo Rateb\App\Core\View::escape($meta['title'] ?? ($title ?? 'Portal')); ?></title>
    <?php if (!empty($meta['description'])) { ?>
    <meta name="description" content="<?php echo Rateb\App\Core\View::escape($meta['description']); ?>">
    <?php } ?>
    <?php if (!empty($meta['canonical'])) { ?>
    <link rel="canonical" href="<?php echo Rateb\App\Core\View::escape($meta['canonical']); ?>">
    <?php } else { ?>
    <link rel="canonical" href="<?php echo Rateb\App\Core\View::escape($hrefCanonical); ?>">
    <?php } ?>
    <meta property="og:title" content="<?php echo Rateb\App\Core\View::escape($meta['og_title'] ?? ($title ?? '')); ?>">
    <meta property="og:description" content="<?php echo Rateb\App\Core\View::escape($meta['og_description'] ?? ($meta['description'] ?? '')); ?>">
    <meta property="og:type" content="website">
    <?php if (!empty($meta['og_image'])) { ?>
    <meta property="og:image" content="<?php echo Rateb\App\Core\View::escape($meta['og_image']); ?>">
    <meta name="twitter:image" content="<?php echo Rateb\App\Core\View::escape($meta['og_image']); ?>">
    <?php } ?>
    <meta name="twitter:card" content="<?php echo Rateb\App\Core\View::escape($meta['twitter_card'] ?? 'summary_large_image'); ?>">
    <meta name="twitter:title" content="<?php echo Rateb\App\Core\View::escape($meta['og_title'] ?? ($title ?? '')); ?>">
    <meta name="twitter:description" content="<?php echo Rateb\App\Core\View::escape($meta['og_description'] ?? ($meta['description'] ?? '')); ?>">
    <meta name="robots" content="<?php echo Rateb\App\Core\View::escape($meta['robots'] ?? 'noindex'); ?>">
    <link rel="alternate" hreflang="en" href="<?php echo Rateb\App\Core\View::escape($hrefCanonical); ?>">
    <link rel="alternate" hreflang="ar" href="<?php echo Rateb\App\Core\View::escape($hrefCanonical); ?>">
    <link rel="alternate" hreflang="x-default" href="<?php echo Rateb\App\Core\View::escape($hrefOrigin . rateb_url('site')); ?>">
    <link href="<?php echo rateb_tajawal_font_css(); ?>" rel="stylesheet">
    <link href="<?php echo rateb_bootstrap_css(); ?>" rel="stylesheet">
    <link href="<?php echo rateb_fontawesome_css(); ?>" rel="stylesheet">
    <link href="<?php echo rateb_asset('css/variables.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_asset('css/marketing.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_asset('css/marketing-dark.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_asset('css/marketing-rtl.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_url('site/theme.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_asset('css/website-portals.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_asset('css/portal-invoice-payment.css'); ?>" rel="stylesheet">
</head>
<body class="rateb-marketing rateb-portal-shell rateb-portal--<?php echo Rateb\App\Core\View::escape($portalType); ?><?php echo $dir === 'rtl' ? ' rateb-marketing-rtl' : ''; ?>">
<?php
$headerContext = 'marketing';
require RATEB_ROOT . '/views/marketing/partials/header.php';
?>
<main id="rateb-marketing-main" class="rateb-portal-main">
    <?php Rateb\App\Core\View::partial('flash'); ?>
    <?php if (!empty($isPortalPage) && empty($hidePortalNav)) {
        require RATEB_ROOT . '/views/marketing/portals/partials/nav.php';
    } ?>
    <?php echo $pageContent; ?>
</main>
<?php require RATEB_ROOT . '/views/marketing/partials/footer.php'; ?>
<script src="<?php echo rateb_bootstrap_js(); ?>"></script>
<script src="<?php echo rateb_asset('js/marketing.js'); ?>"></script>
<script src="<?php echo rateb_asset('js/website-portals.js'); ?>"></script>
<script src="<?php echo rateb_asset('js/portal-invoice-payment.js'); ?>"></script>
</body>
</html>
