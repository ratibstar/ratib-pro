<?php
/**
 * Control-panel owned wrapper for Client Platform pages.
 * Keeps customer-facing sections inside the control shell.
 */
if (!defined('IS_CONTROL_PANEL')) {
    define('IS_CONTROL_PANEL', true);
}

require_once __DIR__ . '/../../includes/config.php';

if (empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

require_once __DIR__ . '/../../includes/control-permissions.php';
requireControlPermission(CONTROL_PERM_DASHBOARD);

$section = strtolower(trim((string) ($_GET['section'] ?? 'hub')));
$pageMap = [
    'hub' => 'dashboard.php',
    'dashboard' => 'dashboard.php',
    'services' => 'services.php',
    'domains' => 'domains.php',
    'orders' => 'orders.php',
    'billing' => 'billing.php',
    'security' => 'security.php',
    'support' => 'support.php',
    'notifications' => 'notifications-center.php',
    'subscriptions' => 'subscriptions.php',
    'settings' => 'settings.php',
];

if (!isset($pageMap[$section])) {
    $section = 'hub';
}

$_GET['control'] = '1';
if (!isset($_GET['agency_id']) && !empty($_SESSION['control_agency_id'])) {
    $_GET['agency_id'] = (string) ((int) $_SESSION['control_agency_id']);
}

if (!defined('RATIB_CLIENT_CONTROL_WRAPPER_ACTIVE')) {
    define('RATIB_CLIENT_CONTROL_WRAPPER_ACTIVE', true);
}

$targetFile = dirname(__DIR__, 3) . '/pages/client/' . $pageMap[$section];
if (!is_file($targetFile)) {
    http_response_code(404);
    exit('Client platform section not found.');
}

require $targetFile;
