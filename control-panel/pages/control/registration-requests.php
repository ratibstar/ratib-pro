<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/pages/control/registration-requests.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/pages/control/registration-requests.php`.
 */
/**
 * Control Panel: Registration Requests
 * Renders content directly (no iframe)
 */
require_once __DIR__ . '/../../includes/config.php';

$isControl = defined('IS_CONTROL_PANEL') && IS_CONTROL_PANEL;
if (!$isControl || empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}
require_once __DIR__ . '/../../includes/control-permissions.php';
requireControlPermission(CONTROL_PERM_REGISTRATION, 'view_control_registration', 'view_all_control_registration');

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl) {
    die('Control panel database unavailable.');
}

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
$registrationRequestsCss = [
    'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
    'https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css',
    'css/control/control-registration-requests.css',
];
startControlLayout('Registration Requests', $registrationRequestsCss, []);

require_once __DIR__ . '/../../includes/control/registration-requests-content.php';

endControlLayout([
    'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js',
    'js/control/registration-requests-page.js',
]);