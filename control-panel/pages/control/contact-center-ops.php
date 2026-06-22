<?php
/**
 * RATIB Contact Center — Production Operations Center (Phase 8).
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

$route = trim((string) ($_GET['route'] ?? 'health'), '/');
if ($route === '') {
    $route = 'health';
}

$allowedRoutes = ['health', 'pbx', 'sip', 'queues', 'ivr', 'agents', 'webrtc', 'ami', 'hub', 'golive'];
if (!in_array($route, $allowedRoutes, true)) {
    $route = 'health';
}

if (!control_contact_center_is_installed()) {
    require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
    startControlLayout('Contact Center Operations', ['css/system-settings.css'], []);
    echo '<div class="alert alert-warning">Upload <code>ratib-contact-center/</code> first.</div>';
    endControlLayout();
    exit;
}

$dbTest = control_contact_center_db_test();
if (!$dbTest['ok'] || !$dbTest['schema']) {
    require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
    startControlLayout('Contact Center Operations', ['css/system-settings.css'], []);
    echo '<div class="alert alert-warning">Database not ready. <a href="' . htmlspecialchars(control_contact_center_migrate_page_url(), ENT_QUOTES, 'UTF-8') . '">Run setup</a></div>';
    endControlLayout();
    exit;
}

$rccAssets = control_contact_center_assets_base_url();
$rccAsset = static function (string $path) use ($rccAssets): string {
    return function_exists('control_contact_center_asset_url')
        ? control_contact_center_asset_url($path)
        : $rccAssets . '/' . ltrim($path, '/');
};

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout(
    'Contact Center — Operations',
    [$rccAsset('ops-css')],
    [],
    ['standalone' => true]
);

$tenantId = control_contact_center_resolve_tenant_id();
$opsApiBase = control_contact_center_ops_api_url();
$wsUrl = control_contact_center_ws_url();
$canManageTenants = !empty($_SESSION['control_is_admin']);
$rtMode = control_contact_center_realtime_mode();
if ($rtMode === 'polling' || $wsUrl === '') {
    $wsUrl = 'polling';
}

include control_contact_center_root_path() . '/views/components/ops-center-embed.php';

endControlLayout([
    $rccAsset('realtime-js'),
    $rccAsset('ops-js'),
]);
