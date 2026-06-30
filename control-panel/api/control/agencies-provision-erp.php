<?php
/**
 * Control Panel API: provision dedicated RATEB ERP database for an agency.
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
require_once __DIR__ . '/../../includes/control/ErpProvisioningService.php';
require_once __DIR__ . '/../../../config/env/agency_lookup.php';

function provisionJson(array $payload): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload);
    exit;
}

if (empty($_SESSION['control_logged_in'])) {
    provisionJson(['success' => false, 'message' => 'Unauthorized']);
}

require_once __DIR__ . '/../../includes/control-permissions.php';
if (!hasControlPermission(CONTROL_PERM_AGENCIES) && !hasControlPermission('edit_control_agency')) {
    provisionJson(['success' => false, 'message' => 'Access denied']);
}

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl || !($ctrl instanceof mysqli)) {
    provisionJson(['success' => false, 'message' => 'Database unavailable']);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true);
if (!is_array($data)) {
    $data = $_POST;
}

$agencyId = (int) ($data['agency_id'] ?? $data['id'] ?? 0);
$planSlug = trim((string) ($data['plan_slug'] ?? $data['erp_plan_slug'] ?? ''));
if ($agencyId < 1) {
    provisionJson(['success' => false, 'message' => 'Invalid agency id']);
}

$agency = rateb_lookup_agency_by_id($agencyId);
if ($agency === null) {
    provisionJson(['success' => false, 'message' => 'Agency not found']);
}

$planSlug = ErpProvisioningService::resolvePlanSlug($agency, $planSlug);
$force = !empty($data['force']);

$currentStatus = strtolower(trim((string) ($agency['erp_status'] ?? 'none')));
if (!$force && $currentStatus === 'ready' && trim((string) ($agency['erp_db_name'] ?? '')) !== '') {
    provisionJson([
        'success' => true,
        'message' => 'ERP already provisioned',
        'erp_db_name' => (string) $agency['erp_db_name'],
        'erp_status' => 'ready',
        'erp_plan_slug' => (string) ($agency['erp_plan_slug'] ?? $planSlug),
    ]);
}

try {
    $result = ErpProvisioningService::provision($ctrl, $agency, $planSlug, $force);
    provisionJson([
        'success' => true,
        'message' => 'ERP provisioned',
        'data' => $result,
    ]);
} catch (Throwable $e) {
    error_log('agencies-provision-erp: ' . $e->getMessage());
    provisionJson(['success' => false, 'message' => $e->getMessage()]);
}
