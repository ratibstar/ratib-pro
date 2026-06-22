<?php
/**
 * RATIB Contact Center — embedded agent workspace inside Control Panel.
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

$route = trim((string) ($_GET['route'] ?? 'agent-desktop'), '/');
if ($route === '') {
    $route = 'agent-desktop';
}

if (!control_contact_center_is_installed()) {
    require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
    startControlLayout('Contact Center', ['css/system-settings.css'], []);
    echo '<div class="alert alert-warning">Upload <code>ratib-contact-center/</code> to the server first.</div>';
    echo '<a href="' . htmlspecialchars(control_contact_center_hub_page_url(), ENT_QUOTES, 'UTF-8') . '" class="btn btn-primary">Back to hub</a>';
    endControlLayout();
    exit;
}

$dbTest = control_contact_center_db_test();
if (!$dbTest['ok'] || !$dbTest['schema']) {
    require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
    startControlLayout('Contact Center', ['css/system-settings.css'], []);
    echo '<div class="alert alert-warning">Database not ready. Run setup first.</div>';
    echo '<a href="' . htmlspecialchars(control_contact_center_migrate_page_url(), ENT_QUOTES, 'UTF-8') . '" class="btn btn-primary">Database setup</a>';
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
    'Contact Center — Agent Desktop',
    [
        $rccAsset('inbox-css'),
        $rccAsset('softphone-css'),
        $rccAsset('copilot-css'),
    ],
    [],
    ['standalone' => true]
);

$tenantId = control_contact_center_resolve_tenant_id();
$agentId = control_contact_center_resolve_agent_id($tenantId);
if ($agentId < 1) {
    require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
    startControlLayout('Contact Center', ['css/system-settings.css'], []);
    echo '<div class="alert alert-warning">No active RCC agent linked to your Control Panel account for this tenant.</div>';
    echo '<p class="small">Provision an agent in <a href="' . htmlspecialchars(control_contact_center_ops_page_url('agents'), ENT_QUOTES, 'UTF-8') . '">Operations Center</a> using your CP email, or ask an administrator.</p>';
    echo '<a href="' . htmlspecialchars(control_contact_center_hub_page_url(), ENT_QUOTES, 'UTF-8') . '" class="btn btn-outline-secondary">Back to hub</a>';
    endControlLayout();
    exit;
}
$inboxApiBase = control_contact_center_inbox_api_url();
$softphoneApiBase = control_contact_center_softphone_api_url();
$assistantApiBase = control_contact_center_assistant_api_url();
$wsUrl = control_contact_center_ws_url();

if ($route === 'agent-desktop') {
    include control_contact_center_root_path() . '/views/components/agent-desktop-embed.php';
} else {
    echo '<div class="alert alert-info">Unknown route. <a href="' . htmlspecialchars(control_contact_center_app_url('agent-desktop'), ENT_QUOTES, 'UTF-8') . '">Open Agent Desktop</a></div>';
}

endControlLayout([
    $rccAsset('realtime-js'),
    'https://cdn.jsdelivr.net/npm/sip.js@0.21.2/dist/sip.min.js',
    $rccAsset('softphone-js'),
    $rccAsset('softphone-ui-js'),
    $rccAsset('inbox-js'),
    $rccAsset('copilot-js'),
    $rccAsset('desktop-js'),
]);
