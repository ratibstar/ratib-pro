<?php
/**
 * Control Panel API: restore dedicated ERP admin login to admin / 123456.
 * Does not wipe company data. Authenticated control session only.
 */
declare(strict_types=1);

ini_set('display_errors', '0');
ob_start();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../includes/control-api-same-origin-cors.php';
applyControlApiSameOriginCors();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control/rateb-erp-bridge.php';
require_once __DIR__ . '/../../../config/env/agency_lookup.php';

function restoreAdminLoginJson(array $payload): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload);
    exit;
}

if (empty($_SESSION['control_logged_in'])) {
    restoreAdminLoginJson(['success' => false, 'message' => 'Unauthorized']);
}

require_once __DIR__ . '/../../includes/control-permissions.php';
if (!hasControlPermission(CONTROL_PERM_AGENCIES) && !hasControlPermission('edit_control_agency')) {
    restoreAdminLoginJson(['success' => false, 'message' => 'Access denied']);
}

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl || !($ctrl instanceof mysqli)) {
    restoreAdminLoginJson(['success' => false, 'message' => 'Database unavailable']);
}

if (!control_rateb_erp_is_installed()) {
    restoreAdminLoginJson(['success' => false, 'message' => 'RATEB ERP is not installed on this server']);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true);
if (!is_array($data)) {
    $data = $_POST;
}

$agencyId = (int) ($data['agency_id'] ?? $data['id'] ?? 0);
$confirm = strtoupper(trim((string) ($data['confirm'] ?? '')));

if ($agencyId < 1) {
    restoreAdminLoginJson(['success' => false, 'message' => 'Invalid agency id']);
}
if ($confirm !== 'RESTORE-ADMIN') {
    restoreAdminLoginJson(['success' => false, 'message' => 'Type RESTORE-ADMIN to confirm']);
}

$agency = rateb_lookup_agency_by_id($agencyId);
if ($agency === null) {
    restoreAdminLoginJson(['success' => false, 'message' => 'Agency not found']);
}
if (trim((string) ($agency['erp_db_name'] ?? '')) === '') {
    restoreAdminLoginJson(['success' => false, 'message' => 'No ERP database — run Provision ERP first']);
}

try {
    $report = control_rateb_erp_restore_admin_login($agencyId);
    restoreAdminLoginJson([
        'success' => true,
        'message' => 'ERP admin login restored',
        'data' => $report,
    ]);
} catch (Throwable $e) {
    error_log('agencies-restore-admin-login: ' . $e->getMessage());
    restoreAdminLoginJson(['success' => false, 'message' => $e->getMessage()]);
}
