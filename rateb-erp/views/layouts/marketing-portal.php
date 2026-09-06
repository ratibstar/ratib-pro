<?php
$locale = rateb_locale();
$dir = rateb_is_rtl() ? 'rtl' : 'ltr';
$meta = $meta ?? [];
?>
<!DOCTYPE html>
<html lang="<?php echo Rateb\App\Core\View::escape($locale); ?>" dir="<?php echo $dir; ?>" data-theme="light" data-bs-theme="light" data-portal-layout="1">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="<?php echo rateb_asset('js/marketing-head.js'); ?>"></script>
    <title><?php echo Rateb\App\Core\View::escape($meta['title'] ?? ($title ?? 'RATEB ERP')); ?></title>
    <link rel="icon" href="<?php echo Rateb\App\Core\View::escape(function_exists('rateb_erp_brand_favicon_url') ? rateb_erp_brand_favicon_url() : rateb_public_url('favicon.ico')); ?>" type="<?php echo function_exists('rateb_erp_brand_logo_url') && rateb_erp_brand_logo_url() !== '' ? 'image/png' : 'image/svg+xml'; ?>">
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
    <link href="<?php echo rateb_asset('css/marketing-portal.css'); ?>" rel="stylesheet">
</head>
<body class="rateb-marketing rateb-portal-layout<?php echo $dir === 'rtl' ? ' rateb-marketing-rtl' : ''; ?>">
<?php
$headerContext = 'portal';
require RATEB_ROOT . '/views/marketing/partials/header.php';
?>
<main id="rateb-marketing-main">
    <?php Rateb\App\Core\View::partial('flash'); ?>
    <?php echo $pageContent; ?>
</main>
<?php require RATEB_ROOT . '/views/marketing/partials/portal-footer.php'; ?>
<script src="<?php echo rateb_bootstrap_js(); ?>"></script>
<script src="<?php echo rateb_asset('js/marketing.js'); ?>"></script>
</body>
</html>
