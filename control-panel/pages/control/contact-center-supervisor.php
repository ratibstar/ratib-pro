<?php
/**
 * RATEB Contact Center — Supervisor & Workforce Management (Phase 9).
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
if ($route === '') {
    $route = 'dashboard';
}

$allowedRoutes = [
    'dashboard', 'wallboard', 'queues', 'agents', 'sla', 'wfm',
    'shifts', 'attendance', 'breaks', 'occupancy', 'adherence', 'alerts', 'reports',
];
if (!in_array($route, $allowedRoutes, true)) {
    $route = 'dashboard';
}

if (!control_contact_center_is_installed()) {
    require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
    startControlLayout('Supervisor Suite', ['css/system-settings.css'], []);
    echo '<div class="alert alert-warning">Upload <code>ratib-contact-center/</code> first.</div>';
    endControlLayout();
    exit;
}

$dbTest = control_contact_center_db_test();
if (!$dbTest['ok'] || !$dbTest['schema']) {
    require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
    startControlLayout('Supervisor Suite', ['css/system-settings.css'], []);
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
    'Contact Center — Supervisor',
    [$rccAsset('supervisor-css')],
    [],
    ['standalone' => true]
);

$tenantId = control_contact_center_resolve_tenant_id();
$supervisorApiBase = control_contact_center_supervisor_api_url();
$wsUrl = control_contact_center_ws_url();
$rtMode = control_contact_center_realtime_mode();
if ($rtMode === 'polling' || $wsUrl === '') {
    $wsUrl = 'polling';
}
$canManageTenants = !empty($_SESSION['control_is_admin']);

include control_contact_center_root_path() . '/views/components/supervisor-center-embed.php';

endControlLayout([
    $rccAsset('realtime-js'),
    $rccAsset('supervisor-js'),
]);
