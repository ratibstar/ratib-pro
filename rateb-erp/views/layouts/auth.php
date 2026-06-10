<?php
$locale = rateb_locale();
$dir = rateb_is_rtl() ? 'rtl' : 'ltr';
?>
<!DOCTYPE html>
<html lang="<?php echo Rateb\App\Core\View::escape($locale); ?>" dir="<?php echo $dir; ?>" data-theme="auto">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo Rateb\App\Core\View::escape($title ?? __('login')); ?> | RATEB ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="<?php echo rateb_asset('css/variables.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_asset('css/main.css'); ?>" rel="stylesheet">
    <?php if ($dir === 'rtl') { ?>
    <link href="<?php echo rateb_asset('css/rtl.css'); ?>" rel="stylesheet">
    <?php } ?>
</head>
<body class="rateb-auth-page">
    <div class="rateb-auth-card">
        <div class="text-center mb-4">
            <i class="fas fa-hospital fa-2x text-primary mb-2"></i>
            <h2 class="h4"><?php echo __('rateb_erp'); ?></h2>
        </div>
        <?php Rateb\App\Core\View::partial('flash'); ?>
        <?php echo $pageContent; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo rateb_asset('js/theme.js'); ?>"></script>
</body>
</html>
