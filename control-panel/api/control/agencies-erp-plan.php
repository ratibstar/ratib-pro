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
    require_once __DIR__ . '/../../../config/env/agency_lookup.php';
    $agency = function_exists('rateb_lookup_agency_by_id') ? rateb_lookup_agency_by_id($agencyId) : null;
    if (!is_array($agency)) {
        planJson(['success' => false, 'message' => 'Agency not found after saving plan']);
    }
    $agency['erp_plan_slug'] = $saved;
    $status = strtolower(trim((string) ($agency['erp_status'] ?? '')));
    $hasDb = trim((string) ($agency['erp_db_name'] ?? '')) !== ''
        || trim((string) ($agency['db_name'] ?? '')) !== '';

    // Ready (or any agency with a DB): always push modules into the dedicated company.
    if ($hasDb && ($status === 'ready' || $status === 'failed' || $status === 'provisioning')) {
        $apply = ErpProvisioningService::applyPlanToAgencyErp($agency, $saved);
        $mods = is_array($apply['modules'] ?? null) ? $apply['modules'] : [];
        if ($mods === []) {
            planJson([
                'success' => false,
                'message' => 'Plan saved on control row but agency company modules stayed empty',
                'agency_id' => $agencyId,
                'erp_plan_slug' => $saved,
                'plan_apply' => $apply,
            ]);
        }
        planJson([
            'success' => true,
            'message' => 'ERP plan saved and applied to agency company (' . ($apply['erp_db_name'] ?? '') . ')',
            'agency_id' => $agencyId,
            'erp_plan_slug' => $saved,
            'plans' => ErpProvisioningService::allowedPlanSlugs(),
            'plan_apply' => $apply,
        ]);
    }

    planJson([
        'success' => true,
        'message' => 'ERP plan saved (agency ERP DB not ready yet — provision first)',
        'agency_id' => $agencyId,
        'erp_plan_slug' => $saved,
        'plans' => ErpProvisioningService::allowedPlanSlugs(),
        'plan_apply' => null,
    ]);
} catch (Throwable $e) {
    planJson(['success' => false, 'message' => $e->getMessage()]);
}
