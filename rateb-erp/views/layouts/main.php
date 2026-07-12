<?php
$locale = rateb_locale();
$dir = rateb_is_rtl() ? 'rtl' : 'ltr';
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$erpRoute = rateb_current_erp_route();
$layoutAssets = class_exists(\Rateb\App\Support\ErpLayoutAssets::class)
    ? \Rateb\App\Support\ErpLayoutAssets::resolve($erpRoute)
    : [
        'charts' => true,
        'lineItems' => true,
        'formHybrid' => true,
        'fiscalYear' => true,
        'inventoryBatch' => true,
        'contractRenewal' => true,
        'cmsAdmin' => true,
        'entityDocuments' => true,
        'defer' => [],
    ];
$accountingActive = $erpRoute !== '' && preg_match('#(accounting|chart-of-accounts|coa-tree|journal-entries|entry-approval|voucher-approval|cash-vouchers|fiscal-periods|bank-accounts|cost-centers|cost-of-sales|trial-balance|journal-register|account-statement|partners-subsidiary-ledger|invoices|payments|subscriptions|reports/cost-analysis|reports/inventory-valuation|asset-depreciation)#', $erpRoute);
$modulePageMetrics = [];
$deferModulePageMetrics = false;
if (empty($hideModulePageStats) && $erpRoute !== '' && class_exists(\Rateb\App\Services\ModulePageStatsService::class)) {
    $deferModulePageMetrics = (new \Rateb\App\Services\ModulePageStatsService())->routeSupportsMetrics($erpRoute);
    if (!$deferModulePageMetrics && function_exists('rateb_module_page_metrics')) {
        $modulePageMetrics = rateb_module_page_metrics($erpRoute);
    }
}
$loadModulePageStatsCss = $deferModulePageMetrics || $modulePageMetrics !== [];
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
if (isset($_GET['dismiss_approvals_alert']) && rateb_is_super_admin()) {
    \Rateb\App\Core\SessionManager::set('rateb_oversight_approvals_seen', rateb_oversight_pending_approvals_count());
}
$approvalsOversightJs = $erpRoute !== '' && (
    str_starts_with($erpRoute, 'admin/oversight/approvals')
    || str_starts_with($erpRoute, 'admin/oversight/companies-approvals')
);
if ($approvalsOversightJs && rateb_is_super_admin()) {
    \Rateb\App\Core\SessionManager::set('rateb_oversight_approvals_seen', rateb_oversight_pending_approvals_count());
}
?>
<!DOCTYPE html>
<html lang="<?php echo Rateb\App\Core\View::escape($locale); ?>" dir="<?php echo $dir; ?>" data-theme-scope="erp" data-theme="dark" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="dark light">
    <meta name="rateb-csrf" content="<?php echo Rateb\App\Core\View::escape(\Rateb\App\Core\Csrf::token()); ?>">
    <script>
    (function () {
        try {
            var mode = localStorage.getItem('rateb_erp_theme') || localStorage.getItem('rateb_theme') || 'dark';
            var bs = mode === 'auto'
                ? (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                : (mode === 'light' ? 'light' : 'dark');
            document.documentElement.setAttribute('data-theme', mode);
            document.documentElement.setAttribute('data-bs-theme', bs);
        } catch (e) {}
    })();
    </script>
    <script src="<?php echo rateb_asset('js/rateb-console-quiet.js'); ?>"></script>
    <title><?php echo Rateb\App\Core\View::escape($title ?? RATEB_APP_NAME); ?> | <?php echo __('rateb_erp'); ?></title>
    <link rel="icon" href="<?php echo rateb_public_url('favicon.ico'); ?>" type="image/svg+xml">
    <link rel="manifest" href="<?php echo rateb_public_url('manifest.webmanifest'); ?>">
    <meta name="theme-color" content="#0f1117">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="RATEB ERP">
    <link rel="apple-touch-icon" href="<?php echo rateb_public_url('assets/pwa/erp-icon-192.png'); ?>">
    <?php if ($dir === 'rtl') { ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <?php } else { ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php } ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet"></noscript>
    <link href="<?php echo rateb_asset('css/variables.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_asset('css/main.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_asset('css/components.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_asset('css/dark.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_asset('css/rtl.css'); ?>" rel="stylesheet">
    <link href="<?php echo rateb_asset('css/light.css'); ?>" rel="stylesheet">
    <?php if (!empty($loadModulePageStatsCss)) { ?>
    <link href="<?php echo rateb_asset('css/dashboard.css'); ?>" rel="stylesheet">
    <?php } ?>
    <?php if ($dir === 'rtl') { ?>
    <link href="<?php echo rateb_asset('css/ar-typography.css'); ?>" rel="stylesheet">
    <style id="rateb-rtl-ar-fix">html[dir="rtl"] .rateb-app,html[dir="rtl"] .rateb-app *,html[dir="rtl"] body.rateb-app *{text-transform:none!important;letter-spacing:normal!important;font-feature-settings:normal!important}</style>
    <?php } ?>
</head>
<body class="rateb-app<?php echo $dir === 'rtl' ? ' rateb-rtl' : ''; ?>"
    data-rateb-media-json="<?php echo Rateb\App\Core\View::escape(rateb_url('admin/cms/media/json')); ?>"
    data-rateb-tinymce-upload="<?php echo Rateb\App\Core\View::escape(rateb_url('admin/cms/media/tinymce-upload')); ?>"
    data-rateb-cms-media="<?php echo Rateb\App\Core\View::escape(__('cms_media')); ?>"
    data-rateb-cms-no-images="<?php echo Rateb\App\Core\View::escape(__('cms_no_images')); ?>"
    data-rateb-cms-pick-image="<?php echo Rateb\App\Core\View::escape(__('cms_pick_image')); ?>"
    data-rateb-cms-media-failed="<?php echo Rateb\App\Core\View::escape(__('cms_media_load_failed')); ?>"
    data-rateb-date-hint-date="<?php echo Rateb\App\Core\View::escape(__('date_format_hint')); ?>"
    data-rateb-date-hint-datetime="<?php echo Rateb\App\Core\View::escape(__('datetime_format_hint')); ?>"
    data-rateb-date-hint-time="<?php echo Rateb\App\Core\View::escape(__('time_format_hint')); ?>"
    data-rateb-date-hint-month="<?php echo Rateb\App\Core\View::escape(__('month_format_hint')); ?>"
    data-rateb-date-hint-week="<?php echo Rateb\App\Core\View::escape(__('week_format_hint')); ?>">
<div class="rateb-wrapper">
    <aside class="rateb-sidebar" id="rateb-sidebar">
        <div class="rateb-sidebar-brand">
            <i class="fas fa-hospital"></i>
            <span><?php echo __('rateb_erp'); ?></span>
        </div>
        <nav>
            <?php require RATEB_ROOT . '/views/partials/sidebar-nav.php'; ?>
            <?php if (rateb_nav_can('dashboard.view', 'dashboard')) { ?>
            <a href="<?php echo rateb_url('admin'); ?>" class="rateb-nav-link<?php echo $navActive('admin') && !$accountingActive ? ' active' : ''; ?>">
                <i class="fas fa-chart-line"></i><span><?php echo __('dashboard'); ?></span>
            </a>
            <?php } ?>
            <?php
            $platformCatalogNavPartial = RATEB_ROOT . '/views/partials/platform-catalog-nav-link.php';
            if (is_file($platformCatalogNavPartial)) {
                require $platformCatalogNavPartial;
            }
            ?>
            <?php if (rateb_is_super_admin() && rateb_is_platform_oversight_host()) { ?>
            <?php
            $oversightCounts = rateb_oversight_menu_counts();
            $oversightLinkBadges = [
                'admin/oversight/companies-approvals' => rateb_nav_can('companies.view') ? (int) (($oversightCounts['company_pending'] ?? 0)) : 0,
                'admin/oversight/approvals' => rateb_nav_can('workflows.view') ? (int) ($oversightCounts['approvals'] ?? 0) : 0,
                'admin/oversight/procurement' => rateb_nav_can('procurement.manage') ? (int) ($oversightCounts['procurement'] ?? 0) : 0,
                'admin/oversight/rfq' => rateb_nav_can('procurement.manage') ? (int) ($oversightCounts['rfq'] ?? 0) : 0,
                'admin/oversight/inventory' => rateb_nav_can('inventory.manage') ? (int) ($oversightCounts['inventory'] ?? 0) : 0,
                'admin/oversight/supplier-evaluations' => rateb_nav_can('procurement.manage') ? (int) ($oversightCounts['supplier_evaluations'] ?? 0) : 0,
            ];
            $adminSection(__('admin_oversight_section'), [
                ['type' => 'link', 'link' => ['admin/companies', 'companies', 'fa-building', 'companies.view']],
                ['type' => 'link', 'link' => ['admin/agency-updates', 'agency_erp_push_title', 'fa-cloud-upload-alt', 'companies.manage']],
                ['type' => 'link', 'link' => ['admin/oversight/companies-approvals', 'companies_approvals_oversight', 'fa-building-circle-check', 'companies.view']],
                [
                    'type' => 'subgroup',
                    'label' => __('branches'),
                    'icon' => 'fa-code-branch',
                    'gate' => ['branches.view', 'branches'],
                    'links' => [
                        ['admin/ops/branch-dashboard', 'branch_dashboard', 'fa-code-branch', 'branch.dashboard.view', 'branches'],
                        ['admin/ops/branch-financial', 'branch_financial_reports', 'fa-file-invoice-dollar', 'branch.financial.pl', 'accounting'],
                        ['admin/ops/branch-dashboard/compare', 'branch_comparison', 'fa-scale-balanced', 'branch.dashboard.compare', 'branches'],
                        ['admin/ops/branch-dashboard/reports', 'branch_reports', 'fa-chart-column', 'branch.reports.view', 'branches'],
                        ['admin/ops/branch-transfers', 'branch_transfers', 'fa-shuffle', 'branch.transfers.view', 'branches'],
                    ],
                ],
                [
                    'type' => 'subgroup',
                    'label' => __('admin_oversight_monitoring'),
                    'icon' => 'fa-eye',
                    'links' => [
                        ['admin/subscriptions', 'subscriptions', 'fa-credit-card', 'subscriptions.manage'],
                        ['admin/oversight/approvals', 'approvals_oversight', 'fa-check-double', 'workflows.view'],
                        ['admin/oversight/procurement', 'procurement_oversight', 'fa-chart-column', 'procurement.manage'],
                        ['admin/oversight/rfq', 'rfq_oversight', 'fa-chart-column', 'procurement.manage'],
                        ['admin/oversight/inventory', 'inventory_oversight', 'fa-chart-column', 'inventory.manage'],
                        ['admin/oversight/supplier-evaluations', 'supplier_evaluations_oversight', 'fa-star-half-stroke', 'procurement.manage'],
                        ['admin/oversight/workflows', 'workflow_definitions', 'fa-diagram-project', 'workflows.view'],
                        ['admin/reports', 'reports', 'fa-chart-pie', 'reports.view'],
                        ['admin/settings', 'settings', 'fa-gear', 'settings.manage'],
                    ],
                ],
            ], 'fa-shield-halved', (int) ($oversightCounts['total'] ?? 0), $oversightLinkBadges, 'rateb-nav-badge--pending');
            ?>
            <?php } ?>
            <?php require RATEB_ROOT . '/views/partials/sidebar-ops-nav.php'; ?>
            <?php if (rateb_is_super_admin()) { ?>
            <?php if (rateb_nav_can('executive.dashboard.view')) { ?>
            <a href="<?php echo rateb_url('admin/executive-dashboard'); ?>" class="rateb-nav-link<?php echo $navActive('admin/executive-dashboard') ? ' active' : ''; ?>">
                <i class="fas fa-gauge-high"></i><span><?php echo __('executive_dashboard'); ?></span>
            </a>
            <?php } ?>
            <?php
            if (rateb_is_platform_oversight_host() && rateb_nav_can('cms.view')) {
                $cmsNewLeads = rateb_nav_can('cms.leads', 'cms') ? rateb_cms_new_leads_count() : 0;
                $cmsLeadBadges = $cmsNewLeads > 0 ? ['admin/cms/leads' => $cmsNewLeads] : [];
                $adminSection(__('cms_section'), [
                    ['admin/cms', 'cms_dashboard', 'fa-globe', 'cms.view'],
                    ['admin/cms/pages', 'cms_pages', 'fa-file-lines', 'cms.manage'],
                    ['admin/cms/page-builder', 'cms_page_builder', 'fa-sitemap', 'cms.manage'],
                    ['admin/cms/leads', 'cms_leads', 'fa-user-plus', 'cms.leads'],
                    ['admin/cms/blog-articles', 'cms_blog', 'fa-newspaper', 'cms.manage'],
                    ['admin/cms/newsletter', 'cms_newsletter', 'fa-envelope-open-text', 'cms.manage'],
                    ['admin/cms/media', 'cms_media', 'fa-images', 'cms.media'],
                    ['admin/cms/seo', 'cms_seo', 'fa-magnifying-glass', 'cms.seo'],
                    ['admin/cms/faqs', 'cms_faqs', 'fa-circle-question', 'cms.manage'],
                    ['admin/cms/testimonials', 'cms_testimonials', 'fa-star', 'cms.manage'],
                    ['admin/cms/theme', 'cms_theme', 'fa-palette', 'cms.manage'],
                    ['admin/cms/about', 'cms_about', 'fa-building', 'cms.manage'],
                ], 'fa-globe', $cmsNewLeads, $cmsLeadBadges, '', 'rateb-nav-badge--pending', 'cms_leads_new');
            }
            $accessControlLinks = [
                ['admin/access-control', 'access_control', 'fa-shield-halved', 'access.manage'],
                ['admin/access-control/matrix', 'permission_matrix', 'fa-table-cells', 'access.manage'],
                ['admin/users', 'users', 'fa-users', 'access.manage'],
                ['admin/roles', 'roles', 'fa-user-shield', 'access.manage'],
                ['admin/permissions', 'permissions', 'fa-key', 'access.manage'],
            ];
            if (rateb_is_platform_oversight_host()) {
                $accessControlLinks[] = ['admin/plans', 'plans', 'fa-layer-group', 'plans.manage'];
            }
            $accessControlLinks[] = ['admin/audit-logs', 'audit_logs', 'fa-clipboard-list', 'settings.manage'];
            $accessControlLinks[] = ['admin/support-tickets', 'support_tickets', 'fa-life-ring', 'settings.manage'];
            $accessControlLinks[] = ['admin/email-templates', 'email_templates', 'fa-envelope', 'settings.manage'];
            $accessControlLinks[] = ['admin/sms-templates', 'sms_templates', 'fa-sms', 'settings.manage'];
            $adminSection(__('access_control'), $accessControlLinks, 'fa-key');
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
                <span class="rateb-connection-indicator is-online"
                      id="rateb-connection-indicator"
                      data-rateb-connection-status
                      data-label-online="<?php echo Rateb\App\Core\View::escape(__('connection_online')); ?>"
                      data-label-offline="<?php echo Rateb\App\Core\View::escape(__('connection_offline')); ?>"
                      role="status"
                      aria-live="polite"
                      title="<?php echo Rateb\App\Core\View::escape(__('connection_online')); ?>">
                    <span class="rateb-connection-indicator__dot" aria-hidden="true"></span>
                    <span class="rateb-connection-indicator__label"><?php echo Rateb\App\Core\View::escape(__('connection_online')); ?></span>
                </span>
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
            <?php if (function_exists('rateb_is_portal_branch_session') && rateb_is_portal_branch_session()) {
                $branchLabel = function_exists('rateb_portal_branch_label') ? rateb_portal_branch_label() : '';
                if ($branchLabel !== '') { ?>
            <div class="alert alert-info py-2 mb-3 d-flex align-items-center gap-2">
                <i class="fas fa-store"></i>
                <span><?php echo Rateb\App\Core\View::escape(__('branch_portal_active_banner', ['branch' => $branchLabel])); ?></span>
            </div>
            <?php }
            } ?>
            <?php if (function_exists('rateb_branch_access_all') && rateb_branch_access_all() && !rateb_is_portal_branch_session() && rateb_company_branches_nav_enabled()) {
                $hoCompanyId = (int) (\Rateb\App\Core\SessionManager::get('rateb_company_id', 0) ?? rateb_resolve_ops_company_id());
                $hoBranches = function_exists('rateb_company_branches_cached')
                    ? rateb_company_branches_cached($hoCompanyId)
                    : (new \Rateb\App\Services\BranchService())->listForCompany($hoCompanyId);
                if ($hoBranches !== []) {
                    Rateb\App\Core\View::partial('branch-filter-switcher', [
                        'branches' => $hoBranches,
                        'activeFilter' => function_exists('rateb_active_branch_filter_id') ? rateb_active_branch_filter_id() : 0,
                    ]);
                }
            ?>
            <div class="alert alert-secondary py-2 mb-3">
                <?php if (function_exists('rateb_active_branch_filter_id') && rateb_active_branch_filter_id() > 0) { ?>
                <i class="fas fa-filter"></i> <?php echo Rateb\App\Core\View::escape(__('branch_filter')); ?>: <strong><?php echo Rateb\App\Core\View::escape(function_exists('rateb_branch_filter_label') ? rateb_branch_filter_label() : ''); ?></strong>
                <?php } else { ?>
                <i class="fas fa-building"></i> <?php echo Rateb\App\Core\View::escape(__('branch_filter_all')); ?>
                <?php } ?>
            </div>
            <?php } ?>
            <?php
            $showOpsCompanyPicker = rateb_is_super_admin()
                && rateb_is_platform_oversight_host()
                && (
                rateb_is_ops_route($erpRoute)
                || strpos($currentPath, '/admin/ops/') !== false
            );
            if ($showOpsCompanyPicker) {
                Rateb\App\Core\View::partial('ops-company-select');
            }
            if ($deferModulePageMetrics) {
                Rateb\App\Core\View::partial('module-page-stats', [
                    'async' => true,
                    'metricsRoute' => $erpRoute,
                    'metricsUrl' => rateb_url('admin/api/module-metrics') . '?route=' . rawurlencode($erpRoute),
                ]);
            } elseif ($modulePageMetrics !== []) {
                Rateb\App\Core\View::partial('module-page-stats', ['metrics' => $modulePageMetrics]);
            }
            ?>
            <?php echo $pageContent; ?>
        </main>
    </div>
</div>
<?php Rateb\App\Core\View::partial('entity-documents-modal-shell'); ?>
<?php Rateb\App\Core\View::partial('rateb-confirm-modal'); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
<?php if (!empty($layoutAssets['charts'])) { ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js" defer></script>
<?php } ?>
<script src="<?php echo rateb_asset('js/theme.js'); ?>" defer></script>
<script src="<?php echo rateb_asset('js/connectivity-indicator.js'); ?>" defer></script>
<script src="<?php echo rateb_asset('js/lang.js'); ?>" defer></script>
<script src="<?php echo rateb_asset('js/rateb-modal.js'); ?>" defer></script>
<script src="<?php echo rateb_asset('js/rateb-confirm.js'); ?>" defer></script>
<script src="<?php echo rateb_asset('js/rateb-bulk-delete.js'); ?>" defer></script>
<script src="<?php echo rateb_asset('js/table-tools.js'); ?>" defer></script>
<script src="<?php echo rateb_asset('js/app.js'); ?>" defer></script>
<script src="<?php echo rateb_asset('js/rateb-date-inputs.js'); ?>" defer></script>
<?php if (!empty($deferModulePageMetrics)) { ?>
<script src="<?php echo rateb_asset('js/module-page-stats.js'); ?>" defer></script>
<?php } ?>
<?php if (!empty($layoutAssets['formHybrid'])) { ?>
<script src="<?php echo rateb_asset('js/form-hybrid.js'); ?>" defer></script>
<?php } ?>
<?php if (!empty($layoutAssets['fiscalYear'])) { ?>
<script src="<?php echo rateb_asset('js/form-fiscal-year.js'); ?>" defer></script>
<?php } ?>
<?php if (!empty($layoutAssets['lineItems'])) { ?>
<script src="<?php echo rateb_asset('js/line-items.js'); ?>" defer></script>
<?php } ?>
<?php if (!empty($layoutAssets['inventoryBatch'])) { ?>
<script src="<?php echo rateb_asset('js/inventory-batch-form.js'); ?>" defer></script>
<?php } ?>
<?php if (!empty($layoutAssets['contractRenewal'])) { ?>
<script src="<?php echo rateb_asset('js/contract-renewal-form.js'); ?>" defer></script>
<?php } ?>
<?php if (!empty($layoutAssets['cmsAdmin'])) { ?>
<script src="<?php echo rateb_asset('js/cms-admin.js'); ?>" defer></script>
<?php } ?>
<?php
$deferAssetScripts = [];
foreach ($layoutAssets['defer'] ?? [] as $deferFile) {
    $deferAssetScripts[] = rateb_asset('js/' . $deferFile);
}
?>
<?php if ($deferAssetScripts !== []) { ?>
<script>
(function () {
    var queue = <?php echo json_encode($deferAssetScripts, JSON_UNESCAPED_SLASHES); ?>;
    var idx = 0;
    function loadNext() {
        if (idx >= queue.length) {
            return;
        }
        var s = document.createElement('script');
        s.src = queue[idx++];
        s.defer = true;
        s.onload = loadNext;
        s.onerror = loadNext;
        document.body.appendChild(s);
    }
    function start() {
        if (window.requestIdleCallback) {
            window.requestIdleCallback(loadNext, { timeout: 2000 });
        } else {
            setTimeout(loadNext, 350);
        }
    }
    if (document.readyState === 'complete') {
        start();
    } else {
        window.addEventListener('load', start, { once: true });
    }
})();
</script>
<?php } ?>
<?php if ($approvalsOversightJs) { ?>
<script src="<?php echo rateb_asset('js/approvals-oversight.js'); ?>" defer></script>
<?php } ?>
<?php if ($navActive('admin/agency-updates')) { ?>
<script src="<?php echo rateb_asset('js/agency-updates.js'); ?>" defer></script>
<?php } ?>
<?php
$ratebOfflineFlagSvc = class_exists(\Rateb\App\Offline\Services\OfflineFeatureFlagService::class)
    ? new \Rateb\App\Offline\Services\OfflineFeatureFlagService()
    : null;
$ratebOfflineReadCache = $ratebOfflineFlagSvc && $ratebOfflineFlagSvc->isReadCacheEnabled();
// Full offline SDK only on daily-ops surfaces — not every admin page (companies, CMS, settings…).
$ratebOfflineFullClient = $ratebOfflineReadCache && (
    !empty($_GET['rateb_offline'])
    || !empty($_GET['rateb_offline_debug'])
    || ($erpRoute !== '' && (bool) preg_match(
        '#^(admin/ops(?:/|$)|admin/hr(?:/|$)|admin/recruitment(?:/|$)|admin/eproc(?:/|$)|company/(?:ops|hr|procurement|inventory)(?:/|$))#',
        $erpRoute
    ))
);
if ($ratebOfflineReadCache) {
    $ratebOfflineSw = rateb_public_url('pos-sw.js');
    $ratebOfflineSwScope = function_exists('rateb_site_origin') && function_exists('rateb_erp_app_prefix')
        ? (rateb_site_origin() . rtrim(rateb_erp_app_prefix(), '/') . '/')
        : '';
    $ratebOfflineApiBase = rateb_url('api/v1/offline');
    $ratebOfflineCompanyId = 0;
    if (function_exists('rateb_resolve_erp_shell_company_id')) {
        $ratebOfflineCompanyId = (int) rateb_resolve_erp_shell_company_id();
    } else {
        $ratebOfflineCompanyId = (int) (\Rateb\App\Core\SessionManager::get('rateb_company_id', 0) ?? 0);
        if ($ratebOfflineCompanyId < 1 && function_exists('rateb_resolve_ops_company_id')) {
            $ratebOfflineCompanyId = (int) rateb_resolve_ops_company_id();
        }
    }
    $ratebOfflineBranchId = 0;
    if (function_exists('rateb_portal_branch_id')) {
        $ratebOfflineBranchId = (int) rateb_portal_branch_id();
    }
    if ($ratebOfflineBranchId < 1 && function_exists('rateb_active_branch_filter_id')) {
        $ratebOfflineBranchId = (int) rateb_active_branch_filter_id();
    }
    $ratebOfflineUserId = (int) (\Rateb\App\Core\SessionManager::get('rateb_user_id', 0) ?? 0);
    $ratebOfflineAllowlistUrl = rateb_public_url('assets/offline/ops-page-allowlist.json');

    if ($ratebOfflineFullClient) {
        $ratebOfflineFlags = $ratebOfflineFlagSvc->snapshot();
        $ratebOfflineSyncPolicy = class_exists(\Rateb\App\Offline\OfflineModule::class)
            ? \Rateb\App\Offline\OfflineModule::syncPolicy()
            : [];
        $ratebOfflineOpsAllowlist = class_exists(\Rateb\App\Offline\OfflineModule::class)
            ? \Rateb\App\Offline\OfflineModule::opsPageAllowlist()
            : [];
        ?>
<script>
window.__RATEB_ERP_SHELL_OFFLINE__ = <?php echo json_encode([
    'serviceWorker' => $ratebOfflineSw,
    'serviceWorkerScope' => $ratebOfflineSwScope,
    'apiBase' => $ratebOfflineApiBase,
    'probeUrl' => rtrim($ratebOfflineApiBase, '/') . '/status',
    'allowlistUrl' => $ratebOfflineAllowlistUrl,
    'flags' => $ratebOfflineFlags,
    'startConnectivity' => true,
    'company_id' => $ratebOfflineCompanyId,
    'tenant_id' => $ratebOfflineCompanyId,
    'branch_id' => $ratebOfflineBranchId,
    'user_id' => $ratebOfflineUserId,
    'is_super_admin' => (bool) \Rateb\App\Core\SessionManager::get('rateb_is_super_admin'),
    'logout_vault_policy' => class_exists(\Rateb\App\Offline\Services\ErpOfflineAuthPolicy::class)
        ? (new \Rateb\App\Offline\Services\ErpOfflineAuthPolicy())->logoutVaultPolicy()
        : 'clear_vault',
    'session_policy' => class_exists(\Rateb\App\Offline\Services\ErpOfflineIdentitySessionPolicy::class)
        ? (new \Rateb\App\Offline\Services\ErpOfflineIdentitySessionPolicy())->snapshot()
        : [],
    'client_queue_max' => (int) ($ratebOfflineSyncPolicy['client_queue_max'] ?? 500),
    // Paths/routes loaded lazily from allowlistUrl — keep HTML lean (~10KB saved per page).
    'ops_page_paths' => [],
    'ops_page_routes' => (object) [],
    'ops_form_hooks' => array_values($ratebOfflineOpsAllowlist['form_hooks'] ?? []),
    'pilot_ops_pages' => $ratebOfflineFlagSvc->isPilotOpsPagesEnabled(),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
<?php if (!empty($_GET['rateb_offline_debug'])) { ?>
<script src="<?php echo rateb_asset('offline/erp-offline-debug.js'); ?>" defer></script>
<?php } ?>
<script src="<?php echo rateb_asset('offline/rateb-offline.js'); ?>" defer></script>
<script src="<?php echo rateb_asset('offline/erp-shell-bootstrap.js'); ?>" defer></script>
<?php if ($ratebOfflineFlagSvc->isAuthUnlockEnabled()) { ?>
<script src="<?php echo rateb_asset('offline/erp-auth-bootstrap.js'); ?>" defer></script>
<?php } ?>
<?php if ($ratebOfflineFlagSvc->isRbacCacheEnabled()) { ?>
<script src="<?php echo rateb_asset('offline/erp-rbac-bootstrap.js'); ?>" defer></script>
<?php } ?>
<?php
        $ratebOfflineOpsForms = $ratebOfflineFlagSvc->isAnyTier1WriteEnabled()
            || $ratebOfflineFlagSvc->isMasterDataEnabled()
            || $ratebOfflineFlagSvc->isPilotOpsPagesEnabled();
        if ($ratebOfflineOpsForms) {
            ?>
<script src="<?php echo rateb_asset('offline/erp-ops-forms-bootstrap.js'); ?>" defer></script>
<?php
        }
    } else {
        // Keep SW alive without parsing the 370KB SDK on every admin navigation.
        ?>
<script>
window.__RATEB_ERP_SHELL_OFFLINE__ = <?php echo json_encode([
    'lite' => true,
    'serviceWorker' => $ratebOfflineSw,
    'serviceWorkerScope' => $ratebOfflineSwScope,
    'apiBase' => $ratebOfflineApiBase,
    'company_id' => $ratebOfflineCompanyId,
    'tenant_id' => $ratebOfflineCompanyId,
    'branch_id' => $ratebOfflineBranchId,
    'user_id' => $ratebOfflineUserId,
    'flags' => [
        'offline.enabled' => true,
        'offline.read_cache' => true,
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
(function () {
  var cfg = window.__RATEB_ERP_SHELL_OFFLINE__ || {};
  if (!('serviceWorker' in navigator) || !cfg.serviceWorker) return;
  var swUrl = String(cfg.serviceWorker);
  var scope = cfg.serviceWorkerScope ? String(cfg.serviceWorkerScope) : undefined;
  navigator.serviceWorker.getRegistrations().then(function (regs) {
    var has = (regs || []).some(function (r) {
      return r && r.active && String(r.active.scriptURL || '').indexOf('pos-sw.js') !== -1;
    });
    if (has) return null;
    return navigator.serviceWorker.register(swUrl, scope ? { scope: scope } : undefined);
  }).catch(function () {});
})();
</script>
<?php
    }
}
$ratebOfflineMasterData = $ratebOfflineFullClient
    && $ratebOfflineFlagSvc
    && $ratebOfflineFlagSvc->isMasterDataEnabled();
if ($ratebOfflineMasterData) {
    ?>
<script>
window.__RATEB_ERP_MASTER_DATA__ = window.__RATEB_ERP_SHELL_OFFLINE__ || {};
if (window.__RATEB_ERP_SHELL_OFFLINE__ && window.__RATEB_ERP_SHELL_OFFLINE__.flags) {
  window.__RATEB_ERP_MASTER_DATA__.flags = window.__RATEB_ERP_SHELL_OFFLINE__.flags;
  window.__RATEB_ERP_MASTER_DATA__.apiBase = window.__RATEB_ERP_SHELL_OFFLINE__.apiBase || window.__RATEB_ERP_MASTER_DATA__.apiBase;
}
</script>
<script src="<?php echo rateb_asset('offline/erp-master-data-bootstrap.js'); ?>" defer></script>
<?php
}
if (!empty($ratebOfflineReadCache)) {
    ?>
<script src="<?php echo rateb_asset('offline/erp-pwa-install.js'); ?>" defer></script>
<?php
}
?>
</body>
</html>
