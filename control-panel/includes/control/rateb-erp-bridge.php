<?php
declare(strict_types=1);

function control_rateb_erp_root_candidates(): array
{
    $candidates = [];
    $fromBridge = dirname(__DIR__, 3) . '/rateb-erp';
    $candidates[] = $fromBridge;

  // control-panel/ may be the deploy root on some hosts
    $candidates[] = dirname(__DIR__, 2) . '/rateb-erp';

    $docRoot = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
    if ($docRoot !== '') {
        $candidates[] = rtrim($docRoot, '/\\') . '/rateb-erp';
        $candidates[] = dirname(rtrim($docRoot, '/\\')) . '/rateb-erp';
    }

    $unique = [];
    foreach ($candidates as $path) {
        $norm = str_replace('\\', '/', $path);
        if (!in_array($norm, $unique, true)) {
            $unique[] = $norm;
        }
    }
    return $unique;
}

function control_rateb_erp_root_path(): string
{
    foreach (control_rateb_erp_root_candidates() as $path) {
        if (is_file($path . '/public/index.php')) {
            return $path;
        }
    }
    return control_rateb_erp_root_candidates()[0] ?? (dirname(__DIR__, 3) . '/rateb-erp');
}

function control_rateb_erp_is_installed(): bool
{
    return is_file(control_rateb_erp_root_path() . '/public/index.php');
}

function control_rateb_erp_diagnostic(): array
{
    $resolved = control_rateb_erp_root_path();
    $candidates = control_rateb_erp_root_candidates();
    return [
        'installed' => control_rateb_erp_is_installed(),
        'resolved' => $resolved,
        'index_exists' => is_file($resolved . '/public/index.php'),
        'candidates' => $candidates,
        'document_root' => (string) ($_SERVER['DOCUMENT_ROOT'] ?? ''),
    ];
}

/** Base CP app URL without &route= (used as RATEB_CP_APP_URL for rateb_url()). */
function control_rateb_erp_app_base_url(): string
{
    return function_exists('control_panel_page_with_control')
        ? control_panel_page_with_control('control/rateb-erp-app.php')
        : '/control-panel/pages/control/rateb-erp-app.php?control=1';
}

/**
 * Open ERP module through Control Panel (no /rateb-erp/public/ URL needed).
 */
function control_rateb_erp_app_url(string $route = 'admin'): string
{
    return control_rateb_erp_public_url($route);
}

/** Direct ERP URL — no Control Panel login required. */
function control_rateb_erp_public_url(string $route = 'admin'): string
{
    if (function_exists('rateb_public_url')) {
        return rateb_public_url($route !== '' ? $route : 'admin');
    }
    $route = trim($route, '/');
    if ($route === '') {
        $route = 'admin';
    }
    $site = rtrim(defined('SITE_URL') ? (string) SITE_URL : '', '/');
    if ($site === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $site = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'rateb.sa');
    }
    $host = strtolower(parse_url($site, PHP_URL_HOST) ?: '');
    $atRoot = in_array($host, ['rateb.sa', 'www.rateb.sa'], true);
    $marketing = $route === 'site' || str_starts_with($route, 'site/') || str_starts_with($route, 'locale/');
    if ($atRoot && $marketing) {
        if ($route === 'site') {
            return $site . '/';
        }

        return $site . '/' . $route;
    }
    $prefix = $atRoot ? '/rateb-erp/public' : '/rateb-erp/public';

    return $site . $prefix . '/' . $route;
}

function control_rateb_erp_ensure_root(): string
{
    if (defined('RATEB_ROOT')) {
        return (string) RATEB_ROOT;
    }
    $erpRoot = control_rateb_erp_root_path();
    define('RATEB_ROOT', str_replace('\\', '/', realpath($erpRoot) ?: $erpRoot));
    return (string) RATEB_ROOT;
}

function control_rateb_erp_schema_ready(): bool
{
    $test = control_rateb_erp_db_test();
    return $test['ok'] && $test['schema'];
}

/** @return array{ok:bool,schema:bool,db:string,error:string} */
function control_rateb_erp_db_test(): array
{
    $result = ['ok' => false, 'schema' => false, 'db' => control_rateb_erp_db_name(), 'error' => ''];
    if (!control_rateb_erp_is_installed()) {
        $result['error'] = 'ERP files missing on server.';
        return $result;
    }
    try {
        control_rateb_erp_ensure_root();
        require_once RATEB_ROOT . '/config/database.php';
        require_once RATEB_ROOT . '/app/Core/Database.php';
        $pdo = \Rateb\App\Core\Database::connection();
        $result['ok'] = true;
        $result['db'] = \Rateb\App\Core\Database::resolvedDatabaseName();
        $stmt = $pdo->query("SHOW TABLES LIKE 'rateb_companies'");
        $result['schema'] = $stmt !== false && $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        $result['error'] = $e->getMessage();
        error_log('RATEB ERP DB test: ' . $e->getMessage());
    }
    return $result;
}

function control_rateb_erp_migrate_page_url(): string
{
    return function_exists('control_panel_page_with_control')
        ? control_panel_page_with_control('control/rateb-erp-migrate.php')
        : '/control-panel/pages/control/rateb-erp-migrate.php?control=1';
}

/** @return array<string, array{href:string,label:string,icon:string,key:string,description?:string,route:string}> */
function control_rateb_erp_nav_links(): array
{
    $routes = [
        'dashboard' => ['route' => 'admin', 'label' => 'Dashboard', 'icon' => 'fa-chart-line', 'description' => 'KPIs, revenue charts, and platform overview.'],
        'companies' => ['route' => 'admin/companies', 'label' => 'Companies', 'icon' => 'fa-building', 'description' => 'Create, activate, suspend, and manage tenant companies.'],
        'subscriptions' => ['route' => 'admin/subscriptions', 'label' => 'Subscriptions', 'icon' => 'fa-credit-card', 'description' => 'Billing cycles, plans, and subscription status.'],
        'procurement' => ['route' => 'admin/procurement', 'label' => 'Procurement', 'icon' => 'fa-cart-shopping', 'description' => 'Purchase requests and orders across all companies.'],
        'inventory' => ['route' => 'admin/inventory', 'label' => 'Inventory', 'icon' => 'fa-boxes-stacked', 'description' => 'Stock levels, warehouses, and inventory value.'],
        'suppliers' => ['route' => 'admin/suppliers', 'label' => 'Suppliers', 'icon' => 'fa-truck-field', 'description' => 'Supplier directory and status.'],
        'assets' => ['route' => 'admin/assets', 'label' => 'Assets', 'icon' => 'fa-toolbox', 'description' => 'Fixed assets and medical equipment registry.'],
        'contracts' => ['route' => 'admin/contracts', 'label' => 'Contracts', 'icon' => 'fa-file-contract', 'description' => 'Healthcare and procurement contracts.'],
        'reports' => ['route' => 'admin/reports', 'label' => 'Reports', 'icon' => 'fa-chart-pie', 'description' => 'Platform analytics and export views.'],
        'accounting' => ['route' => 'admin/accounting', 'label' => 'Accounting', 'icon' => 'fa-calculator', 'description' => 'Full accounting: chart of accounts, journals, invoices, payments, trial balance.'],
        'settings' => ['route' => 'admin/settings', 'label' => 'Settings', 'icon' => 'fa-gear', 'description' => 'System settings, templates, and configuration.'],
    ];

    $links = [];
    foreach ($routes as $key => $item) {
        $links[$key] = [
            'href' => control_rateb_erp_app_url($item['route']),
            'label' => $item['label'],
            'icon' => $item['icon'],
            'key' => $key,
            'route' => $item['route'],
            'description' => $item['description'],
        ];
    }
    return $links;
}

function control_rateb_erp_db_name(): string
{
    if (function_exists('rateb_erp_database_name')) {
        return rateb_erp_database_name();
    }
    if (defined('RATEB_ERP_DB_NAME') && (string) RATEB_ERP_DB_NAME !== '') {
        $name = (string) RATEB_ERP_DB_NAME;
        return $name === 'admin-rateb-erp' ? 'admin_rateb-erp' : ($name === 'admin-rateb-erp' ? 'admin_rateb-erp' : $name);
    }
    $env = getenv('RATEB_ERP_DB_NAME');
    $name = ($env !== false && $env !== '') ? (string) $env : (function_exists('rateb_erp_database_name') ? rateb_erp_database_name() : 'admin_rateb-erp');
    return $name === 'admin-rateb-erp' ? 'admin_rateb-erp' : ($name === 'admin-rateb-erp' ? 'admin_rateb-erp' : $name);
}

/** @return array<int, string> */
function control_rateb_erp_run_migrations(): array
{
    control_rateb_erp_ensure_root();
    require_once RATEB_ROOT . '/config/database.php';
    require_once RATEB_ROOT . '/app/Core/Database.php';
    require_once RATEB_ROOT . '/app/services/MigrationService.php';
    require_once RATEB_ROOT . '/app/services/AuthorizationService.php';
    require_once RATEB_ROOT . '/app/services/ErpDatabaseService.php';
    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }
    return (new \Rateb\App\Services\ErpDatabaseService())->fixErpDatabase();
}

/** @return array{erp:array<string,mixed>,control_panel:array<string,mixed>} */
function control_rateb_erp_db_diagnose(): array
{
    control_rateb_erp_ensure_root();
    require_once RATEB_ROOT . '/config/database.php';
    require_once RATEB_ROOT . '/app/Core/Database.php';
    require_once RATEB_ROOT . '/app/services/ErpDatabaseService.php';
    $svc = new \Rateb\App\Services\ErpDatabaseService();
    return [
        'erp' => $svc->diagnoseErp(),
        'control_panel' => $svc->diagnoseControlPanel(),
    ];
}

function control_rateb_erp_assets_base_url(): string
{
    $site = rtrim(defined('SITE_URL') ? (string) SITE_URL : '', '/');
    if ($site === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $site = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    $assetsPrefix = function_exists('rateb_erp_assets_prefix') ? rateb_erp_assets_prefix() : '/rateb-erp/public';

    return $site . $assetsPrefix . '/assets';
}
