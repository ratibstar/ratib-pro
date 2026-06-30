<?php
/**
 * Control Panel API: provision RATEB Pro database access + default admin for an agency.
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
require_once __DIR__ . '/../../includes/control/ProProvisioningService.php';
require_once __DIR__ . '/../../../config/env/agency_lookup.php';

function provisionProJson(array $payload): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload);
    exit;
}

if (empty($_SESSION['control_logged_in'])) {
    provisionProJson(['success' => false, 'message' => 'Unauthorized']);
}

require_once __DIR__ . '/../../includes/control-permissions.php';
if (!hasControlPermission(CONTROL_PERM_AGENCIES) && !hasControlPermission('edit_control_agency')) {
    provisionProJson(['success' => false, 'message' => 'Access denied']);
}

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl || !($ctrl instanceof mysqli)) {
    provisionProJson(['success' => false, 'message' => 'Database unavailable']);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true);
if (!is_array($data)) {
    $data = $_POST;
}

$agencyId = (int) ($data['agency_id'] ?? $data['id'] ?? 0);
if ($agencyId < 1) {
    provisionProJson(['success' => false, 'message' => 'Invalid agency id']);
}

$agency = rateb_lookup_agency_by_id($agencyId);
if ($agency === null) {
    provisionProJson(['success' => false, 'message' => 'Agency not found']);
}

try {
    $result = ProProvisioningService::provision($ctrl, $agency);
    provisionProJson([
        'success' => true,
        'message' => 'RATEB Pro provisioned',
        'data' => $result,
    ]);
} catch (Throwable $e) {
    error_log('agencies-provision-pro: ' . $e->getMessage());
    provisionProJson(['success' => false, 'message' => $e->getMessage()]);
}
