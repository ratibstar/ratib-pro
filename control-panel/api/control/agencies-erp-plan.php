<?php
/**
 * Control Panel API: save ERP plan slug for an agency (before provisioning).
 */
declare(strict_types=1);

ini_set('display_errors', '0');
ob_start();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../includes/control-api-same-origin-cors.php';
applyControlApiSameOriginCors();

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control/ErpProvisioningService.php';

function planJson(array $payload): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload);
    exit;
}

if (empty($_SESSION['control_logged_in'])) {
    planJson(['success' => false, 'message' => 'Unauthorized']);
}

require_once __DIR__ . '/../../includes/control-permissions.php';
if (!hasControlPermission(CONTROL_PERM_AGENCIES) && !hasControlPermission('edit_control_agency')) {
    planJson(['success' => false, 'message' => 'Access denied']);
}

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl || !($ctrl instanceof mysqli)) {
    planJson(['success' => false, 'message' => 'Database unavailable']);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true);
if (!is_array($data)) {
    $data = $_POST;
}

$agencyId = (int) ($data['agency_id'] ?? $data['id'] ?? 0);
$planSlug = trim((string) ($data['plan_slug'] ?? $data['erp_plan_slug'] ?? ''));
if ($agencyId < 1) {
    planJson(['success' => false, 'message' => 'Invalid agency id']);
}
if ($planSlug === '') {
    planJson(['success' => false, 'message' => 'plan_slug is required']);
}

try {
    $saved = ErpProvisioningService::saveAgencyPlan($ctrl, $agencyId, $planSlug);
    planJson([
        'success' => true,
        'message' => 'ERP plan saved',
        'agency_id' => $agencyId,
        'erp_plan_slug' => $saved,
        'plans' => ErpProvisioningService::allowedPlanSlugs(),
    ]);
} catch (Throwable $e) {
    planJson(['success' => false, 'message' => $e->getMessage()]);
}
