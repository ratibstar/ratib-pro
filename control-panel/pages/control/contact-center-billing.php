<?php
/**
 * RATEB Contact Center — SaaS Billing & Subscriptions (Phase 11).
 */
if (!defined('IS_CONTROL_PANEL')) {
    define('IS_CONTROL_PANEL', true);
}
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control/contact-center-nav.php';

if (empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

require_once __DIR__ . '/../../includes/control-permissions.php';
requireControlPermission(CONTROL_PERM_DASHBOARD, 'control_system_settings', 'view_control_system_settings');

$route = trim((string) ($_GET['route'] ?? 'dashboard'), '/');
$allowedRoutes = ['dashboard', 'plans', 'invoices', 'payments', 'subscriptions', 'licenses', 'whitelabel', 'reseller', 'provision'];
if (!in_array($route, $allowedRoutes, true)) {
    $route = 'dashboard';
}

if (!control_contact_center_is_installed()) {
    require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
    startControlLayout('Billing', ['css/system-settings.css'], []);
    echo '<div class="alert alert-warning">Upload <code>ratib-contact-center/</code> first.</div>';
    endControlLayout();
    exit;
}

$dbTest = control_contact_center_db_test();
if (!$dbTest['ok'] || !$dbTest['schema']) {
    require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
    startControlLayout('Billing', ['css/system-settings.css'], []);
    echo '<div class="alert alert-warning">Database not ready. <a href="' . htmlspecialchars(control_contact_center_migrate_page_url(), ENT_QUOTES, 'UTF-8') . '">Run setup</a></div>';
    endControlLayout();
    exit;
}

$rccAsset = static function (string $key): string {
    return function_exists('control_contact_center_asset_url')
        ? control_contact_center_asset_url($key)
        : control_contact_center_assets_base_url() . '/' . $key;
};

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout('SaaS Billing', [$rccAsset('billing-css')], [], ['standalone' => true]);

$tenantId = control_contact_center_resolve_tenant_id();
$billingApiBase = control_contact_center_billing_api_url();
$wsUrl = control_contact_center_realtime_mode() === 'polling' ? 'polling' : control_contact_center_ws_url();

include control_contact_center_root_path() . '/views/components/billing-center-embed.php';

endControlLayout([$rccAsset('realtime-js'), $rccAsset('billing-js')]);
