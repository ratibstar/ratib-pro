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
    return $site . '/rateb-erp/public';
}

/** @return array<string, array{href:string,label:string,icon:string,key:string}> */
function control_rateb_erp_nav_links(): array
{
    $base = control_rateb_erp_base_url();
    return [
        'dashboard' => ['href' => $base . '/admin', 'label' => 'Dashboard', 'icon' => 'fa-chart-line', 'key' => 'dashboard'],
        'companies' => ['href' => $base . '/admin/companies', 'label' => 'Companies', 'icon' => 'fa-building', 'key' => 'companies'],
        'subscriptions' => ['href' => $base . '/admin/subscriptions', 'label' => 'Subscriptions', 'icon' => 'fa-credit-card', 'key' => 'subscriptions'],
        'procurement' => ['href' => $base . '/admin/procurement', 'label' => 'Procurement', 'icon' => 'fa-cart-shopping', 'key' => 'procurement'],
        'inventory' => ['href' => $base . '/admin/inventory', 'label' => 'Inventory', 'icon' => 'fa-boxes-stacked', 'key' => 'inventory'],
        'suppliers' => ['href' => $base . '/admin/suppliers', 'label' => 'Suppliers', 'icon' => 'fa-truck-field', 'key' => 'suppliers'],
        'assets' => ['href' => $base . '/admin/assets', 'label' => 'Assets', 'icon' => 'fa-toolbox', 'key' => 'assets'],
        'contracts' => ['href' => $base . '/admin/contracts', 'label' => 'Contracts', 'icon' => 'fa-file-contract', 'key' => 'contracts'],
        'reports' => ['href' => $base . '/admin/reports', 'label' => 'Reports', 'icon' => 'fa-chart-pie', 'key' => 'reports'],
        'settings' => ['href' => $base . '/admin/settings', 'label' => 'Settings', 'icon' => 'fa-gear', 'key' => 'settings'],
    ];
}

function control_rateb_erp_active_key(): string
{
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
