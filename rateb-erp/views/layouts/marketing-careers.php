<?php
$locale = rateb_locale();
$dir = rateb_is_rtl() ? 'rtl' : 'ltr';
$meta = $meta ?? [];
$theme = $theme ?? [];
$hrefOrigin = rateb_site_origin();
$hrefPath = rateb_current_public_path('site');
$hrefCanonical = $hrefOrigin . rateb_url($hrefPath);
?>
<!DOCTYPE html>
<html lang="<?php echo Rateb\App\Core\View::escape($locale); ?>" dir="<?php echo $dir; ?>" data-theme="light" data-bs-theme="light" data-career-layout="1">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="<?php echo rateb_asset('js/marketing-head.js'); ?>"></script>
    <title><?php echo Rateb\App\Core\View::escape($meta['title'] ?? ($title ?? 'Careers')); ?></title>
    <?php if (!empty($meta['description'])) { ?>
    <meta name="description" content="<?php echo Rateb\App\Core\View::escape($meta['description']); ?>">
    <?php } ?>
    <?php if (!empty($meta['canonical'])) { ?>
    <link rel="canonical" href="<?php echo Rateb\App\Core\View::escape($meta['canonical']); ?>">
    <?php } else { ?>
    <link rel="canonical" href="<?php echo Rateb\App\Core\View::escape($hrefCanonical); ?>">
    <?php } ?>
    <meta property="og:title" content="<?php echo Rateb\App\Core\View::escape($meta['og_title'] ?? ($title ?? '')); ?>">
    <meta property="og:description" content="<?php echo Rateb\App\Core\View::escape($meta['og_description'] ?? ''); ?>">
    <meta property="og:type" content="website">
    <?php if (!empty($meta['og_image'])) { ?>
    <meta property="og:image" content="<?php echo Rateb\App\Core\View::escape($meta['og_image']); ?>">
    <meta name="twitter:image" content="<?php echo Rateb\App\Core\View::escape($meta['og_image']); ?>">
    <?php } ?>
    <meta name="twitter:card" content="<?php echo Rateb\App\Core\View::escape($meta['twitter_card'] ?? 'summary_large_image'); ?>">
    <meta name="twitter:title" content="<?php echo Rateb\App\Core\View::escape($meta['og_title'] ?? ($title ?? '')); ?>">
    <meta name="twitter:description" content="<?php echo Rateb\App\Core\View::escape($meta['og_description'] ?? ''); ?>">
    <?php if (!empty($meta['robots'])) { ?>
    <meta name="robots" content="<?php echo Rateb\App\Core\View::escape($meta['robots']); ?>">
    <?php } ?>
    <?php if (!empty($meta['schema_json'])) { ?>
    <script type="application/ld+json"><?php echo $meta['schema_json']; ?></script>
    <?php } ?>
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
    <link href="<?php echo rateb_asset('css/website-careers.css'); ?>" rel="stylesheet">
    <?php require RATEB_ROOT . '/views/marketing/partials/analytics-head.php'; ?>
</head>
<body class="rateb-marketing rateb-career-layout<?php echo $dir === 'rtl' ? ' rateb-marketing-rtl' : ''; ?>">
<?php
$headerContext = 'marketing';
require RATEB_ROOT . '/views/marketing/partials/topbar.php';
require RATEB_ROOT . '/views/marketing/partials/header.php';
?>
<main id="rateb-marketing-main" class="rateb-career-main">
    <?php Rateb\App\Core\View::partial('flash'); ?>
    <?php require RATEB_ROOT . '/views/marketing/careers/partials/nav.php'; ?>
    <?php echo $pageContent; ?>
</main>
<?php require RATEB_ROOT . '/views/marketing/partials/footer.php'; ?>
<script src="<?php echo rateb_bootstrap_js(); ?>"></script>
<script src="<?php echo rateb_asset('js/marketing.js'); ?>"></script>
<script src="<?php echo rateb_asset('js/website-careers.js'); ?>"></script>
</body>
</html>
