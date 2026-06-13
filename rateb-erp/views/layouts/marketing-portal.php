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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <?php if ($dir === 'rtl') { ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <?php } else { ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php } ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="<?php echo rateb_asset('css/variables.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_asset('css/marketing.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_asset('css/marketing-dark.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_asset('css/marketing-rtl.css'); ?>" rel="stylesheet">
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo rateb_asset('js/marketing.js'); ?>"></script>
</body>
</html>
