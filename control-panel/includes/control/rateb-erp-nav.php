<?php
declare(strict_types=1);

/**
 * RATEB ERP navigation links for Control Panel sidebar.
 */
function control_rateb_erp_base_url(): string
{
    $site = rtrim(defined('SITE_URL') ? (string) SITE_URL : '', '/');
    if ($site === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $site = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    // rateb-erp lives at site document root, not under /control-panel/
    return $site . '/rateb-erp/public';
}

function control_rateb_erp_hub_page_url(): string
{
    if (!function_exists('control_panel_page_with_control')) {
        return '';
    }
    return control_panel_page_with_control('control/rateb-erp.php');
}

/** @return array<string, array{href:string,label:string,icon:string,key:string}> */
function control_rateb_erp_nav_links(): array
{
    $base = control_rateb_erp_base_url();
    return [
        'dashboard' => ['href' => $base . '/admin', 'label' => 'Dashboard', 'icon' => 'fa-chart-line', 'key' => 'dashboard', 'description' => 'KPIs, revenue charts, and platform overview.'],
        'companies' => ['href' => $base . '/admin/companies', 'label' => 'Companies', 'icon' => 'fa-building', 'key' => 'companies', 'description' => 'Create, activate, suspend, and manage tenant companies.'],
        'subscriptions' => ['href' => $base . '/admin/subscriptions', 'label' => 'Subscriptions', 'icon' => 'fa-credit-card', 'key' => 'subscriptions', 'description' => 'Billing cycles, plans, and subscription status.'],
        'procurement' => ['href' => $base . '/admin/procurement', 'label' => 'Procurement', 'icon' => 'fa-cart-shopping', 'key' => 'procurement', 'description' => 'Purchase requests and orders across all companies.'],
        'inventory' => ['href' => $base . '/admin/inventory', 'label' => 'Inventory', 'icon' => 'fa-boxes-stacked', 'key' => 'inventory', 'description' => 'Stock levels, warehouses, and inventory value.'],
        'suppliers' => ['href' => $base . '/admin/suppliers', 'label' => 'Suppliers', 'icon' => 'fa-truck-field', 'key' => 'suppliers', 'description' => 'Supplier directory and status.'],
        'assets' => ['href' => $base . '/admin/assets', 'label' => 'Assets', 'icon' => 'fa-toolbox', 'key' => 'assets', 'description' => 'Fixed assets and medical equipment registry.'],
        'contracts' => ['href' => $base . '/admin/contracts', 'label' => 'Contracts', 'icon' => 'fa-file-contract', 'key' => 'contracts', 'description' => 'Healthcare and procurement contracts.'],
        'reports' => ['href' => $base . '/admin/reports', 'label' => 'Reports', 'icon' => 'fa-chart-pie', 'key' => 'reports', 'description' => 'Platform analytics and export views.'],
        'settings' => ['href' => $base . '/admin/settings', 'label' => 'Settings', 'icon' => 'fa-gear', 'key' => 'settings', 'description' => 'System settings, templates, and configuration.'],
    ];
}

function control_rateb_erp_active_key(): string
{
    if (basename($_SERVER['PHP_SELF'] ?? '') === 'rateb-erp.php') {
        return 'hub';
    }
    $path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
    if (strpos($path, 'rateb-erp') === false) {
        return '';
    }
    foreach (control_rateb_erp_nav_links() as $link) {
        if (strpos($path, $link['key']) !== false) {
            return $link['key'];
        }
    }
    return 'dashboard';
}
