<?php
declare(strict_types=1);

function control_rateb_erp_root_path(): string
{
    return dirname(__DIR__, 3) . '/rateb-erp';
}

function control_rateb_erp_is_installed(): bool
{
    return is_file(control_rateb_erp_root_path() . '/public/index.php');
}

/**
 * Open ERP module through Control Panel (no /rateb-erp/public/ URL needed).
 */
function control_rateb_erp_app_url(string $route = 'admin'): string
{
    $route = trim($route, '/');
    if ($route === '') {
        $route = 'admin';
    }
    $base = function_exists('control_panel_page_with_control')
        ? control_panel_page_with_control('control/rateb-erp-app.php')
        : '/control-panel/pages/control/rateb-erp-app.php?control=1';
    return $base . '&route=' . rawurlencode($route);
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

function control_rateb_erp_assets_base_url(): string
{
    $site = rtrim(defined('SITE_URL') ? (string) SITE_URL : '', '/');
    if ($site === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $site = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    return $site . '/rateb-erp/public/assets';
}
