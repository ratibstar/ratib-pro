<?php
$locale = rateb_locale();
$dir = rateb_is_rtl() ? 'rtl' : 'ltr';
?>
<!DOCTYPE html>
<html lang="<?php echo Rateb\App\Core\View::escape($locale); ?>" dir="<?php echo $dir; ?>" data-theme-scope="erp" data-theme="dark" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    <?php if ($dir === 'rtl') { ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <?php } else { ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php } ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
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
                    <a href="<?php echo rateb_url('locale/en'); ?>" class="btn btn-outline-secondary<?php echo $locale === 'en' ? ' active' : ''; ?>">EN</a>
                    <a href="<?php echo rateb_url('locale/ar'); ?>" class="btn btn-outline-secondary<?php echo $locale === 'ar' ? ' active' : ''; ?>">عربي</a>
                </div>
            </div>
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
