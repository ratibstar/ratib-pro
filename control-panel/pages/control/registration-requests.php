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

$regPurgeFlash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $purgeAction = (string) ($_POST['reg_purge_action'] ?? '');
    if ($purgeAction === 'delete_all') {
        if (
            !hasControlPermission(CONTROL_PERM_REGISTRATION)
            && !hasControlPermission('delete_control_registration')
        ) {
            $regPurgeFlash = ['type' => 'danger', 'text' => 'Access denied'];
        } elseif (strtoupper(trim((string) ($_POST['confirm'] ?? ''))) !== 'DELETE') {
            $regPurgeFlash = ['type' => 'warning', 'text' => 'Cancelled — type DELETE exactly to confirm.'];
        } else {
            require_once __DIR__ . '/../../includes/control/registration-requests-purge.php';
            $purge = registration_requests_purge_all($ctrl);
            if (!empty($purge['success'])) {
                $qs = http_build_query([
                    'control' => '1',
                    'all_dates' => '1',
                    'queue' => '1',
                    'limit' => 25,
                    'status' => 'pending',
                    'purged' => (int) ($purge['deleted'] ?? 0),
                ]);
                header('Location: ' . pageUrl('control/registration-requests.php') . '?' . $qs);
                exit;
            }
            $regPurgeFlash = ['type' => 'danger', 'text' => (string) ($purge['message'] ?? 'Delete failed')];
        }
    } elseif ($purgeAction === 'delete_ids') {
        if (
            !hasControlPermission(CONTROL_PERM_REGISTRATION)
            && !hasControlPermission('delete_control_registration')
        ) {
            $regPurgeFlash = ['type' => 'danger', 'text' => 'Access denied'];
        } else {
            $rawIds = $_POST['ids'] ?? [];
            if (!is_array($rawIds)) {
                $rawIds = [$rawIds];
            }
            require_once __DIR__ . '/../../includes/control/registration-requests-purge.php';
            $purge = registration_requests_delete_ids($ctrl, $rawIds);
            if (!empty($purge['success'])) {
                $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
                $target = pageUrl('control/registration-requests.php') . '?control=1&all_dates=1';
                if ($ref !== '' && strpos($ref, 'registration-requests') !== false) {
                    $target = $ref;
                }
                $sep = (strpos($target, '?') !== false) ? '&' : '?';
                header('Location: ' . $target . $sep . 'deleted=' . (int) ($purge['deleted'] ?? 0));
                exit;
            }
            $regPurgeFlash = ['type' => 'danger', 'text' => (string) ($purge['message'] ?? 'Delete failed')];
        }
    }
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