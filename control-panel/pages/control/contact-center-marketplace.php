<?php
/**
 * RATEB Contact Center — Marketplace & Add-ons (Phase 11).
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

if (!control_contact_center_is_installed() || !control_contact_center_db_test()['schema']) {
    require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
    startControlLayout('Marketplace', ['css/system-settings.css'], []);
    echo '<div class="alert alert-warning">Contact Center not ready.</div>';
    endControlLayout();
    exit;
}

$rccAsset = static function (string $key): string {
    return function_exists('control_contact_center_asset_url') ? control_contact_center_asset_url($key) : control_contact_center_assets_base_url() . '/' . $key;
};

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout('Marketplace', [$rccAsset('marketplace-css')], [], ['standalone' => true]);

$tenantId = control_contact_center_resolve_tenant_id();
$marketplaceApiBase = control_contact_center_marketplace_api_url();

include control_contact_center_root_path() . '/views/components/marketplace-center-embed.php';

endControlLayout([$rccAsset('marketplace-js')]);
