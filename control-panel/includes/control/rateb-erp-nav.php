<?php
declare(strict_types=1);

/**
 * RATEB ERP — Control Panel URLs (out.ratib.sa only).
 */
require_once __DIR__ . '/rateb-erp-bridge.php';

function control_rateb_erp_base_url(): string
{
    return control_rateb_erp_app_url('admin');
}

function control_rateb_erp_hub_page_url(): string
{
    if (!function_exists('control_panel_page_with_control')) {
        return '';
    }
    return control_panel_page_with_control('control/rateb-erp.php');
}

function control_rateb_erp_active_key(): string
{
    $self = basename($_SERVER['PHP_SELF'] ?? '');
    if ($self === 'rateb-erp.php') {
        return 'hub';
    }
    if ($self === 'rateb-erp-migrate.php') {
        return 'migrate';
    }
    if ($self === 'rateb-erp-app.php') {
        $route = trim((string) ($_GET['route'] ?? ''), '/');
        foreach (control_rateb_erp_nav_links() as $key => $link) {
            if (($link['route'] ?? '') === $route || strpos($route, $key) !== false) {
                return $key;
            }
        }
        return 'dashboard';
    }
    return '';
}
