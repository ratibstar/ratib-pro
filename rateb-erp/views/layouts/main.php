<?php
$locale = rateb_locale();
$dir = rateb_is_rtl() ? 'rtl' : 'ltr';
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$erpRoute = defined('RATEB_CP_ROUTE') ? (string) RATEB_CP_ROUTE : '';
$accountingActive = $erpRoute !== '' && preg_match('#(accounting|chart-of-accounts|journal-entries|invoices|payments|subscriptions)#', $erpRoute);
$navActive = static function (string $route) use ($erpRoute, $currentPath): bool {
    if ($erpRoute !== '') {
        if ($route === 'admin') {
            return $erpRoute === 'admin';
        }
        return $erpRoute === $route || strpos($erpRoute, $route . '/') === 0;
    }
    if ($route === 'admin') {
        return preg_match('#/admin/?$#', $currentPath) === 1;
    }
    return strpos($currentPath, $route) !== false;
};
?>
<!DOCTYPE html>
<html lang="<?php echo Rateb\App\Core\View::escape($locale); ?>" dir="<?php echo $dir; ?>" data-theme="dark" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
    (function () {
        try {
            var mode = localStorage.getItem('rateb_theme') || 'dark';
            var bs = mode === 'auto'
                ? (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                : (mode === 'light' ? 'light' : 'dark');
            document.documentElement.setAttribute('data-theme', mode);
            document.documentElement.setAttribute('data-bs-theme', bs);
        } catch (e) {}
    })();
    </script>
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
    <link href="<?php echo rateb_asset('css/light.css'); ?>" rel="stylesheet">
</head>
<body class="rateb-app<?php echo $dir === 'rtl' ? ' rateb-rtl' : ''; ?>">
<div class="rateb-wrapper">
    <aside class="rateb-sidebar" id="rateb-sidebar">
        <div class="rateb-sidebar-brand">
            <i class="fas fa-hospital"></i>
            <span><?php echo __('rateb_erp'); ?></span>
        </div>
        <nav>
            <a href="<?php echo rateb_url('admin'); ?>" class="rateb-nav-link<?php echo $navActive('admin') && !$accountingActive ? ' active' : ''; ?>">
                <i class="fas fa-chart-line"></i><span><?php echo __('dashboard'); ?></span>
            </a>
            <div class="rateb-nav-section"><?php echo __('rateb_erp'); ?></div>
            <?php
            $adminLinks = [
                ['admin/companies', 'companies', 'fa-building'],
                ['admin/subscriptions', 'subscriptions', 'fa-credit-card'],
                ['admin/procurement', 'procurement', 'fa-cart-shopping'],
                ['admin/inventory', 'inventory', 'fa-boxes-stacked'],
                ['admin/stock-movements', 'stock_movements', 'fa-arrows-rotate'],
                ['admin/suppliers', 'suppliers', 'fa-truck-field'],
                ['admin/supplier-evaluations', 'supplier_evaluations', 'fa-star-half-stroke'],
                ['admin/contracts', 'contracts', 'fa-file-contract'],
                ['admin/assets', 'assets', 'fa-toolbox'],
                ['admin/medical-devices', 'medical_devices', 'fa-stethoscope'],
                ['admin/reports', 'reports', 'fa-chart-pie'],
                ['admin/notifications', 'notifications', 'fa-bell'],
                ['admin/workflows', 'workflows', 'fa-diagram-project'],
                ['admin/settings', 'settings', 'fa-gear'],
            ];
            foreach ($adminLinks as $link) {
                $active = $navActive($link[0]) ? ' active' : '';
                echo '<a href="' . rateb_url($link[0]) . '" class="rateb-nav-link' . $active . '">';
                echo '<i class="fas ' . $link[2] . '"></i><span>' . __($link[1]) . '</span></a>';
            }
            ?>
            <div class="rateb-nav-section"><?php echo __('accounting_module'); ?></div>
            <?php
            $accountingLinks = [
                ['admin/accounting', 'accounting_overview', 'fa-gauge-high'],
                ['admin/chart-of-accounts', 'chart_of_accounts', 'fa-list'],
                ['admin/journal-entries', 'journal_entries', 'fa-book'],
                ['admin/invoices', 'invoices', 'fa-file-invoice'],
                ['admin/payments', 'payments', 'fa-money-bill-wave'],
                ['admin/subscriptions', 'subscriptions', 'fa-credit-card'],
            ];
            foreach ($accountingLinks as $link) {
                $active = $navActive($link[0]) ? ' active' : '';
                echo '<a href="' . rateb_url($link[0]) . '" class="rateb-nav-link' . $active . '">';
                echo '<i class="fas ' . $link[2] . '"></i><span>' . __($link[1]) . '</span></a>';
            }
            ?>
            <div class="rateb-nav-section"><?php echo __('access_control'); ?></div>
            <a href="<?php echo rateb_url('admin/access-control'); ?>" class="rateb-nav-link"><i class="fas fa-shield-halved"></i><span><?php echo __('access_control'); ?></span></a>
            <a href="<?php echo rateb_url('admin/access-control/matrix'); ?>" class="rateb-nav-link"><i class="fas fa-table-cells"></i><span><?php echo __('permission_matrix'); ?></span></a>
            <a href="<?php echo rateb_url('admin/users'); ?>" class="rateb-nav-link"><i class="fas fa-users"></i><span><?php echo __('users'); ?></span></a>
            <a href="<?php echo rateb_url('admin/roles'); ?>" class="rateb-nav-link"><i class="fas fa-user-shield"></i><span><?php echo __('roles'); ?></span></a>
            <a href="<?php echo rateb_url('admin/permissions'); ?>" class="rateb-nav-link"><i class="fas fa-key"></i><span><?php echo __('permissions'); ?></span></a>
            <a href="<?php echo rateb_url('admin/plans'); ?>" class="rateb-nav-link"><i class="fas fa-layer-group"></i><span><?php echo __('plans'); ?></span></a>
            <a href="<?php echo rateb_url('admin/audit-logs'); ?>" class="rateb-nav-link"><i class="fas fa-clipboard-list"></i><span><?php echo __('audit_logs'); ?></span></a>
            <a href="<?php echo rateb_url('admin/notifications'); ?>" class="rateb-nav-link"><i class="fas fa-bell"></i><span><?php echo __('notifications'); ?></span></a>
            <a href="<?php echo rateb_url('admin/support-tickets'); ?>" class="rateb-nav-link"><i class="fas fa-life-ring"></i><span><?php echo __('support_tickets'); ?></span></a>
            <a href="<?php echo rateb_url('admin/email-templates'); ?>" class="rateb-nav-link"><i class="fas fa-envelope"></i><span><?php echo __('email_templates'); ?></span></a>
            <a href="<?php echo rateb_url('admin/sms-templates'); ?>" class="rateb-nav-link"><i class="fas fa-sms"></i><span><?php echo __('sms_templates'); ?></span></a>
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
                <a href="<?php echo rateb_url('admin/logout'); ?>" class="btn btn-outline-danger btn-sm rateb-topbar-logout" title="<?php echo __('logout'); ?>">
                    <i class="fas fa-sign-out-alt"></i><span class="d-none d-md-inline ms-1"><?php echo __('logout'); ?></span>
                </a>
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
