<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/pages/control/hr.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/pages/control/hr.php`.
 */
/**
 * Control Panel — same HR UI as Ratib Pro (pages/hr.php), embedded in control shell.
 * Uses root css/js; /api/hr/*?control=1 reads/writes CONTROL_PANEL_DB_NAME only (isolated from Ratib Pro HR).
 */
if (!defined('IS_CONTROL_PANEL')) {
    define('IS_CONTROL_PANEL', true);
}
require_once __DIR__ . '/../../includes/config.php';

$isControl = defined('IS_CONTROL_PANEL') && IS_CONTROL_PANEL;
if (!$isControl || empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

require_once __DIR__ . '/../../includes/control-permissions.php';
requireControlPermission(CONTROL_PERM_HR, 'view_control_hr', 'manage_control_hr');

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl) {
    die('Control panel database unavailable.');
}

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';

// HR assets live in Ratib Pro root (../css, ../js), not under /control-panel/ — use absolute URLs
$additionalCSS = [
    'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
    'https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css',
    control_ratib_pro_asset_url('css/hr.css'),
];

// Load hr.js / hr-forms after Bootstrap (footer) so bootstrap.Modal exists before DOMContentLoaded handlers run
$hrJsFooter = [
    control_ratib_pro_asset_url('js/hr.js'),
    control_ratib_pro_asset_url('js/hr-forms.js'),
    'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js',
    control_ratib_pro_asset_url('js/hr/hr-page-init.js'),
    control_ratib_pro_asset_url('js/utils/currencies-utils.js'),
    control_ratib_pro_asset_url('js/countries-cities.js'),
    control_ratib_pro_asset_url('js/hr/countries-cities-handler.js'),
    control_ratib_pro_asset_url('js/hr/hr-page.js'),
];

startControlLayout('HR Management', $additionalCSS, []);

require_once dirname(__DIR__, 3) . '/includes/hr-dashboard-body.php';

endControlLayout($hrJsFooter);
