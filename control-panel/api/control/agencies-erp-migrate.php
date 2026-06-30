<?php
/**
 * Control Panel API: run RATEB ERP migrations on platform + agency databases.
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
require_once __DIR__ . '/../../includes/control/rateb-erp-bridge.php';
require_once __DIR__ . '/../../../config/env/agency_lookup.php';

function erpMigrateJson(array $payload): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['control_logged_in'])) {
    erpMigrateJson(['success' => false, 'message' => 'Unauthorized']);
}

require_once __DIR__ . '/../../includes/control-permissions.php';
if (!hasControlPermission(CONTROL_PERM_AGENCIES) && !hasControlPermission('edit_control_agency')) {
    erpMigrateJson(['success' => false, 'message' => 'Access denied']);
}

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl || !($ctrl instanceof mysqli)) {
    erpMigrateJson(['success' => false, 'message' => 'Database unavailable']);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true);
if (!is_array($data)) {
    $data = $_POST;
}

$scope = strtolower(trim((string) ($data['scope'] ?? '')));
$includePlatform = !empty($data['include_platform']);
$agencyIds = $data['agency_ids'] ?? ($data['ids'] ?? []);
if (!is_array($agencyIds)) {
    $agencyIds = [];
}
$agencyIds = array_values(array_unique(array_filter(array_map('intval', $agencyIds), static fn (int $id): bool => $id > 0)));

if ($scope === 'all_ready' || $scope === 'all_subscribed') {
    $subscribedOnly = ($scope === 'all_subscribed');
    $rows = ErpProvisioningService::listErpAgencies($ctrl, $subscribedOnly);
    $agencyIds = array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $rows);
    $agencyIds = array_values(array_filter($agencyIds, static fn (int $id): bool => $id > 0));
}

if ($agencyIds === [] && !$includePlatform) {
    erpMigrateJson(['success' => false, 'message' => 'Select at least one agency or include platform database']);
}

if (function_exists('set_time_limit')) {
    @set_time_limit(900);
}

$results = [];
$failed = 0;
$success = 0;

if ($includePlatform) {
    try {
        $log = control_rateb_erp_run_migrations();
        $results[] = [
            'target' => 'platform',
            'label' => 'Platform ERP (' . control_rateb_erp_db_name() . ')',
            'ok' => true,
            'log' => $log,
        ];
        $success++;
    } catch (Throwable $e) {
        $results[] = [
            'target' => 'platform',
            'label' => 'Platform ERP',
            'ok' => false,
            'error' => $e->getMessage(),
        ];
        $failed++;
    }
}

foreach ($agencyIds as $agencyId) {
    $agency = rateb_lookup_agency_by_id($agencyId);
    if ($agency === null) {
        $results[] = [
            'target' => 'agency',
            'agency_id' => $agencyId,
            'ok' => false,
            'error' => 'Agency not found',
        ];
        $failed++;
        continue;
    }
    try {
        $migration = ErpProvisioningService::runMigrationsForAgency($agency);
        $results[] = array_merge(['target' => 'agency', 'ok' => true], $migration);
        $success++;
    } catch (Throwable $e) {
        $results[] = [
            'target' => 'agency',
            'agency_id' => $agencyId,
            'agency_name' => (string) ($agency['name'] ?? ''),
            'erp_db_name' => (string) ($agency['erp_db_name'] ?? ''),
            'ok' => false,
            'error' => $e->getMessage(),
        ];
        $failed++;
    }
}

erpMigrateJson([
    'success' => $failed === 0,
    'total' => count($results),
    'success_count' => $success,
    'failed_count' => $failed,
    'results' => $results,
]);
