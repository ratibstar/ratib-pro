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
            <?php require RATEB_ROOT . '/views/partials/sidebar-ops-nav.php'; ?>
            <?php if (rateb_is_super_admin()) { ?>
            <?php if (rateb_nav_can('executive.dashboard.view')) { ?>
            <a href="<?php echo rateb_url('admin/executive-dashboard'); ?>" class="rateb-nav-link<?php echo $navActive('admin/executive-dashboard') ? ' active' : ''; ?>">
                <i class="fas fa-gauge-high"></i><span><?php echo __('executive_dashboard'); ?></span>
            </a>
            <?php } ?>
            <div class="rateb-nav-section"><?php echo __('admin_oversight_section'); ?></div>
            <?php
            $adminLinks = [
                ['admin/companies', 'companies', 'fa-building', 'companies.view'],
                ['admin/subscriptions', 'subscriptions', 'fa-credit-card', 'subscriptions.manage'],
                ['admin/oversight/procurement', 'procurement_oversight', 'fa-chart-column', 'procurement.manage'],
                ['admin/oversight/rfq', 'rfq_oversight', 'fa-chart-column', 'procurement.manage'],
                ['admin/oversight/inventory', 'inventory_oversight', 'fa-chart-column', 'inventory.manage'],
                ['admin/oversight/workflows', 'workflow_definitions', 'fa-diagram-project', 'workflows.view'],
                ['admin/reports', 'reports', 'fa-chart-pie', 'reports.view'],
                ['admin/settings', 'settings', 'fa-gear', 'settings.manage'],
            ];
            foreach ($adminLinks as $link) {
                if (!rateb_nav_can($link[3])) {
                    continue;
                }
                $active = $navActive($link[0]) ? ' active' : '';
                echo '<a href="' . rateb_url($link[0]) . '" class="rateb-nav-link' . $active . '">';
                echo '<i class="fas ' . $link[2] . '"></i><span>' . __($link[1]) . '</span></a>';
            }
            ?>
            <div class="rateb-nav-section"><?php echo __('accounting_module'); ?></div>
            <?php
            $accountingLinks = [
                ['admin/accounting', 'accounting_overview', 'fa-gauge-high', 'accounting.view'],
                ['admin/chart-of-accounts', 'chart_of_accounts', 'fa-list', 'accounting.view'],
                ['admin/journal-entries', 'journal_entries', 'fa-book', 'accounting.view'],
                ['admin/invoices', 'invoices', 'fa-file-invoice', 'accounting.view'],
                ['admin/payments', 'payments', 'fa-money-bill-wave', 'accounting.view'],
            ];
            foreach ($accountingLinks as $link) {
                if (!rateb_nav_can($link[3])) {
                    continue;
                }
                $active = $navActive($link[0]) ? ' active' : '';
                echo '<a href="' . rateb_url($link[0]) . '" class="rateb-nav-link' . $active . '">';
                echo '<i class="fas ' . $link[2] . '"></i><span>' . __($link[1]) . '</span></a>';
            }
            ?>
            <?php if (rateb_nav_can('cms.view')) { ?>
            <div class="rateb-nav-section"><?php echo __('cms_section'); ?></div>
            <?php
            $cmsLinks = [
                ['admin/cms', 'cms_dashboard', 'fa-globe', 'cms.view'],
                ['admin/cms/pages', 'cms_pages', 'fa-file-lines', 'cms.manage'],
                ['admin/cms/leads', 'cms_leads', 'fa-user-plus', 'cms.leads'],
                ['admin/cms/blog-articles', 'cms_blog', 'fa-newspaper', 'cms.manage'],
                ['admin/cms/media', 'cms_media', 'fa-images', 'cms.media'],
                ['admin/cms/seo', 'cms_seo', 'fa-magnifying-glass', 'cms.seo'],
                ['admin/cms/theme', 'cms_theme', 'fa-palette', 'cms.manage'],
            ];
            foreach ($cmsLinks as $link) {
                if (!rateb_nav_can($link[3])) {
                    continue;
                }
                $active = $navActive($link[0]) ? ' active' : '';
                echo '<a href="' . rateb_url($link[0]) . '" class="rateb-nav-link' . $active . '">';
                echo '<i class="fas ' . $link[2] . '"></i><span>' . __($link[1]) . '</span></a>';
            }
            ?>
            <?php } ?>
            <div class="rateb-nav-section"><?php echo __('access_control'); ?></div>
            <?php
            $accessLinks = [
                ['admin/access-control', 'access_control', 'fa-shield-halved', 'access.manage'],
                ['admin/access-control/matrix', 'permission_matrix', 'fa-table-cells', 'access.manage'],
                ['admin/users', 'users', 'fa-users', 'access.manage'],
                ['admin/roles', 'roles', 'fa-user-shield', 'access.manage'],
                ['admin/permissions', 'permissions', 'fa-key', 'access.manage'],
                ['admin/plans', 'plans', 'fa-layer-group', 'plans.manage'],
                ['admin/audit-logs', 'audit_logs', 'fa-clipboard-list', 'settings.manage'],
                ['admin/support-tickets', 'support_tickets', 'fa-life-ring', 'settings.manage'],
                ['admin/email-templates', 'email_templates', 'fa-envelope', 'settings.manage'],
                ['admin/sms-templates', 'sms_templates', 'fa-sms', 'settings.manage'],
            ];
            foreach ($accessLinks as $link) {
                if (!rateb_nav_can($link[3])) {
                    continue;
                }
                $active = $navActive($link[0]) ? ' active' : '';
                echo '<a href="' . rateb_url($link[0]) . '" class="rateb-nav-link' . $active . '">';
                echo '<i class="fas ' . $link[2] . '"></i><span>' . __($link[1]) . '</span></a>';
            }
            ?>
            <?php } ?>
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
<script src="<?php echo rateb_asset('js/line-items.js'); ?>"></script>
<script src="<?php echo rateb_asset('js/charts.js'); ?>"></script>
</body>
</html>
