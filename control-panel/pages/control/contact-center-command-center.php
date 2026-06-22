<?php
/**
 * RATIB Contact Center — Executive Command Center (Phase 10H).
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

if (!control_contact_center_is_installed()) {
    require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
    startControlLayout('Command Center', ['css/system-settings.css'], []);
    echo '<div class="alert alert-warning">Upload <code>ratib-contact-center/</code> first.</div>';
    endControlLayout();
    exit;
}

$dbTest = control_contact_center_db_test();
if (!$dbTest['ok'] || !$dbTest['schema']) {
    require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
    startControlLayout('Command Center', ['css/system-settings.css'], []);
    echo '<div class="alert alert-warning">Database not ready.</div>';
    endControlLayout();
    exit;
}

$rccAsset = static function (string $key): string {
    return function_exists('control_contact_center_asset_url')
        ? control_contact_center_asset_url($key)
        : control_contact_center_assets_base_url() . '/' . $key;
};

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout('Executive Command Center', [$rccAsset('command-css')], [], ['standalone' => true]);

$tenantId = control_contact_center_resolve_tenant_id();
$analyticsApiBase = control_contact_center_analytics_api_url();
$wsUrl = control_contact_center_realtime_mode() === 'polling' ? 'polling' : control_contact_center_ws_url();
$canManageTenants = !empty($_SESSION['control_is_admin']);

include control_contact_center_root_path() . '/views/components/command-center-embed.php';

endControlLayout([$rccAsset('realtime-js'), $rccAsset('command-js')]);
