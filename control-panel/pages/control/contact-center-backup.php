<?php
/**
 * RATEB Contact Center — Backup & Disaster Recovery (Phase 11).
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

$route = trim((string) ($_GET['route'] ?? 'backups'), '/');
$allowedRoutes = ['backups', 'restore', 'monitors', 'clusters'];
if (!in_array($route, $allowedRoutes, true)) {
    $route = 'backups';
}

if (!control_contact_center_is_installed() || !control_contact_center_db_test()['schema']) {
    require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
    startControlLayout('Backup', ['css/system-settings.css'], []);
    echo '<div class="alert alert-warning">Contact Center not ready.</div>';
    endControlLayout();
    exit;
}

$rccAsset = static function (string $key): string {
    return function_exists('control_contact_center_asset_url') ? control_contact_center_asset_url($key) : control_contact_center_assets_base_url() . '/' . $key;
};

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout('Backup & DR', [$rccAsset('dr-css')], [], ['standalone' => true]);

$tenantId = control_contact_center_resolve_tenant_id();
$drApiBase = control_contact_center_dr_api_url();
$wsUrl = control_contact_center_realtime_mode() === 'polling' ? 'polling' : control_contact_center_ws_url();

include control_contact_center_root_path() . '/views/components/dr-center-embed.php';

endControlLayout([$rccAsset('realtime-js'), $rccAsset('dr-js')]);
