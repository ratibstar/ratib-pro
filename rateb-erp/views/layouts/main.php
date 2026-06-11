<?php
$locale = rateb_locale();
$dir = rateb_is_rtl() ? 'rtl' : 'ltr';
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$erpRoute = defined('RATEB_CP_ROUTE') ? (string) RATEB_CP_ROUTE : '';
$accountingActive = $erpRoute !== '' && preg_match('#(accounting|chart-of-accounts|journal-entries|invoices|payments|subscriptions)#', $erpRoute);
?>
<!DOCTYPE html>
<html lang="<?php echo Rateb\App\Core\View::escape($locale); ?>" dir="<?php echo $dir; ?>" data-theme="dark" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo Rateb\App\Core\View::escape($title ?? RATEB_APP_NAME); ?> | RATEB ERP</title>
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
            <a href="<?php echo rateb_url('admin'); ?>" class="rateb-nav-link<?php echo strpos($currentPath, '/admin') !== false && substr_count($currentPath, '/') <= 3 ? ' active' : ''; ?>">
                <i class="fas fa-chart-line"></i><span><?php echo __('dashboard'); ?></span>
            </a>
            <div class="rateb-nav-section"><?php echo __('rateb_erp'); ?></div>
            <?php
            $adminLinks = [
                ['admin/companies', 'companies', 'fa-building'],
                ['admin/subscriptions', 'subscriptions', 'fa-credit-card'],
                ['admin/procurement', 'procurement', 'fa-cart-shopping'],
                ['admin/inventory', 'inventory', 'fa-boxes-stacked'],
                ['admin/suppliers', 'suppliers', 'fa-truck-field'],
                ['admin/supplier-evaluations', 'supplier_evaluations', 'fa-star-half-stroke'],
                ['admin/assets', 'assets', 'fa-toolbox'],
                ['admin/contracts', 'contracts', 'fa-file-contract'],
                ['admin/reports', 'reports', 'fa-chart-pie'],
                ['admin/settings', 'settings', 'fa-gear'],
            ];
            foreach ($adminLinks as $link) {
                $active = strpos($currentPath, $link[0]) !== false ? ' active' : '';
                echo '<a href="' . rateb_url($link[0]) . '" class="rateb-nav-link' . $active . '">';
                echo '<i class="fas ' . $link[2] . '"></i><span>' . __($link[1]) . '</span></a>';
            }
            ?>
            <div class="rateb-nav-section"><?php echo __('accounting_module'); ?></div>
            <a href="<?php echo rateb_url('admin/accounting'); ?>" class="rateb-nav-link<?php echo $accountingActive ? ' active' : ''; ?>"><i class="fas fa-calculator"></i><span><?php echo __('accounting_module'); ?></span></a>
            <div class="rateb-nav-section"><?php echo __('access_control'); ?></div>
            <a href="<?php echo rateb_url('admin/access-control'); ?>" class="rateb-nav-link"><i class="fas fa-shield-halved"></i><span><?php echo __('access_control'); ?></span></a>
            <a href="<?php echo rateb_url('admin/users'); ?>" class="rateb-nav-link"><i class="fas fa-users"></i><span><?php echo __('users'); ?></span></a>
            <a href="<?php echo rateb_url('admin/roles'); ?>" class="rateb-nav-link"><i class="fas fa-user-shield"></i><span><?php echo __('roles'); ?></span></a>
            <a href="<?php echo rateb_url('admin/permissions'); ?>" class="rateb-nav-link"><i class="fas fa-key"></i><span><?php echo __('permissions'); ?></span></a>
            <a href="<?php echo rateb_url('admin/plans'); ?>" class="rateb-nav-link"><i class="fas fa-layer-group"></i><span><?php echo __('plans'); ?></span></a>
            <a href="<?php echo rateb_url('admin/audit-logs'); ?>" class="rateb-nav-link"><i class="fas fa-clipboard-list"></i><span><?php echo __('audit_logs'); ?></span></a>
            <a href="<?php echo rateb_url('admin/notifications'); ?>" class="rateb-nav-link"><i class="fas fa-bell"></i><span><?php echo __('notifications'); ?></span></a>
            <a href="<?php echo rateb_url('admin/support-tickets'); ?>" class="rateb-nav-link"><i class="fas fa-life-ring"></i><span><?php echo __('support_tickets'); ?></span></a>
            <a href="<?php echo rateb_url('admin/email-templates'); ?>" class="rateb-nav-link"><i class="fas fa-envelope"></i><span><?php echo __('email_templates'); ?></span></a>
            <a href="<?php echo rateb_url('admin/sms-templates'); ?>" class="rateb-nav-link"><i class="fas fa-sms"></i><span><?php echo __('sms_templates'); ?></span></a>
            <a href="<?php echo rateb_url('admin/logout'); ?>" class="rateb-nav-link rateb-nav-logout"><i class="fas fa-sign-out-alt"></i><span><?php echo __('logout'); ?></span></a>
        </nav>
    </aside>
    <div class="rateb-main">
        <header class="rateb-topbar">
            <div class="d-flex align-items-center gap-3">
                <button type="button" class="btn btn-outline-secondary btn-sm d-lg-none" id="rateb-sidebar-toggle"><i class="fas fa-bars"></i></button>
                <h1 class="h5 mb-0"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h1>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="btn-group btn-group-sm" role="group" aria-label="<?php echo __('theme_dark'); ?>">
                    <button type="button" class="btn btn-outline-secondary" data-theme-choice="light" title="<?php echo __('theme_light'); ?>"><i class="fas fa-sun"></i></button>
                    <button type="button" class="btn btn-outline-secondary active" data-theme-choice="dark" title="<?php echo __('theme_dark'); ?>"><i class="fas fa-moon"></i></button>
                    <button type="button" class="btn btn-outline-secondary" data-theme-choice="auto" title="<?php echo __('theme_auto'); ?>"><i class="fas fa-circle-half-stroke"></i></button>
                </div>
                <div class="btn-group btn-group-sm" role="group" aria-label="<?php echo __('language'); ?>">
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
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="<?php echo rateb_asset('js/theme.js'); ?>"></script>
<script src="<?php echo rateb_asset('js/lang.js'); ?>"></script>
<script src="<?php echo rateb_asset('js/app.js'); ?>"></script>
<script src="<?php echo rateb_asset('js/charts.js'); ?>"></script>
</body>
</html>
