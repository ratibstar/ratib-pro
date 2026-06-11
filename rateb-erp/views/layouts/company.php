<?php
$locale = rateb_locale();
$dir = rateb_is_rtl() ? 'rtl' : 'ltr';
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
?>
<!DOCTYPE html>
<html lang="<?php echo Rateb\App\Core\View::escape($locale); ?>" dir="<?php echo $dir; ?>" data-theme="dark" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo Rateb\App\Core\View::escape($title ?? RATEB_APP_NAME); ?> | <?php echo __('rateb_erp'); ?></title>
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
    <link href="<?php echo rateb_asset('css/main.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_asset('css/components.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_asset('css/dark.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_asset('css/rtl.css'); ?>" rel="stylesheet">
</head>
<body class="rateb-app<?php echo $dir === 'rtl' ? ' rateb-rtl' : ''; ?>">
<div class="rateb-wrapper">
    <aside class="rateb-sidebar" id="rateb-sidebar">
        <div class="rateb-sidebar-brand">
            <i class="fas fa-hospital"></i>
            <span><?php echo __('rateb_erp'); ?></span>
        </div>
        <nav>
            <a href="<?php echo rateb_url('company'); ?>" class="rateb-nav-link"><i class="fas fa-chart-line"></i><span><?php echo __('dashboard'); ?></span></a>
            <?php
            $links = [
                ['company/purchase-requests', 'purchase_requests', 'fa-file-circle-plus'],
                ['company/purchase-orders', 'purchase_orders', 'fa-file-invoice'],
                ['company/rfq', 'rfq', 'fa-comments-dollar'],
                ['company/quotations', 'quotations', 'fa-file-signature'],
                ['company/suppliers', 'suppliers', 'fa-truck-field'],
                ['company/supplier-evaluations', 'supplier_evaluations', 'fa-star-half-stroke'],
                ['company/inventory', 'inventory', 'fa-boxes-stacked'],
                ['company/warehouses', 'warehouses', 'fa-warehouse'],
                ['company/assets', 'assets', 'fa-toolbox'],
                ['company/medical-devices', 'medical_devices', 'fa-stethoscope'],
                ['company/contracts', 'contracts', 'fa-file-contract'],
                ['company/tenders', 'tenders', 'fa-gavel'],
                ['company/accounting', 'accounting_module', 'fa-calculator'],
                ['company/reports', 'reports', 'fa-chart-pie'],
                ['company/notifications', 'notifications', 'fa-bell'],
                ['company/profile', 'profile', 'fa-user-gear'],
            ];
            foreach ($links as $link) {
                $active = strpos($currentPath, $link[0]) !== false ? ' active' : '';
                echo '<a href="' . rateb_url($link[0]) . '" class="rateb-nav-link' . $active . '">';
                echo '<i class="fas ' . $link[2] . '"></i><span>' . __($link[1]) . '</span></a>';
            }
            ?>
        </nav>
    </aside>
    <div class="rateb-main">
        <header class="rateb-topbar">
            <div class="d-flex align-items-center gap-3">
                <button type="button" class="btn btn-outline-secondary btn-sm d-lg-none" id="rateb-sidebar-toggle"><i class="fas fa-bars"></i></button>
                <h1 class="h5 mb-0"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h1>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" data-theme-choice="light" title="<?php echo __('theme_light'); ?>"><i class="fas fa-sun"></i></button>
                    <button type="button" class="btn btn-outline-secondary active" data-theme-choice="dark" title="<?php echo __('theme_dark'); ?>"><i class="fas fa-moon"></i></button>
                    <button type="button" class="btn btn-outline-secondary" data-theme-choice="auto" title="<?php echo __('theme_auto'); ?>"><i class="fas fa-circle-half-stroke"></i></button>
                </div>
                <a href="<?php echo rateb_url('company/logout'); ?>" class="btn btn-outline-danger btn-sm rateb-topbar-logout" title="<?php echo __('logout'); ?>">
                    <i class="fas fa-sign-out-alt"></i><span class="d-none d-md-inline ms-1"><?php echo __('logout'); ?></span>
                </a>
                <div class="btn-group btn-group-sm">
                    <a href="<?php echo rateb_url('locale/en'); ?>" class="btn btn-outline-secondary<?php echo $locale === 'en' ? ' active' : ''; ?>" data-locale="en">EN</a>
                    <a href="<?php echo rateb_url('locale/ar'); ?>" class="btn btn-outline-secondary<?php echo $locale === 'ar' ? ' active' : ''; ?>" data-locale="ar">عربي</a>
                </div>
            </div>
        </header>
        <main class="rateb-content">
            <?php Rateb\App\Core\View::partial('flash'); ?>
            <?php echo $pageContent; ?>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo rateb_asset('js/theme.js'); ?>"></script>
<script src="<?php echo rateb_asset('js/lang.js'); ?>"></script>
<script src="<?php echo rateb_asset('js/app.js'); ?>"></script>
</body>
</html>
