<?php
/**
 * Shared bootstrap for control-owned Client Platform wrappers.
 *
 * Expects:
 * - $clientPlatformTargetPage (string) like 'services.php'
 */
if (!defined('IS_CONTROL_PANEL')) {
    define('IS_CONTROL_PANEL', true);
}

if (empty($clientPlatformTargetPage) || !is_string($clientPlatformTargetPage)) {
    http_response_code(500);
    exit('Client platform target is missing.');
}

require_once __DIR__ . '/../../includes/config.php';

if (empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

require_once __DIR__ . '/../../includes/control-permissions.php';
requireControlPermission(CONTROL_PERM_DASHBOARD);

$_GET['control'] = '1';
if (!isset($_GET['agency_id']) && !empty($_SESSION['control_agency_id'])) {
    $_GET['agency_id'] = (string) ((int) $_SESSION['control_agency_id']);
}

if (!defined('RATEB_CLIENT_CONTROL_WRAPPER_ACTIVE')) {
    define('RATEB_CLIENT_CONTROL_WRAPPER_ACTIVE', true);
}

require_once dirname(__DIR__, 3) . '/includes/config.php';

$effectiveAgencyId = (int) ($_GET['agency_id'] ?? ($_SESSION['control_agency_id'] ?? 0));
$effectiveCountryId = (int) ($_SESSION['control_country_id'] ?? 0);
$tenantConn = $GLOBALS['conn'] ?? null;
if ($effectiveAgencyId > 0
    && $tenantConn instanceof mysqli
    && function_exists('rateb_control_panel_try_program_sso')) {
    rateb_control_panel_try_program_sso($tenantConn, $effectiveAgencyId, $effectiveCountryId);
}

$targetFile = dirname(__DIR__, 3) . '/pages/client/' . ltrim($clientPlatformTargetPage, '/');
if (!is_file($targetFile)) {
    http_response_code(404);
    exit('Client platform section not found.');
}

require $targetFile;
