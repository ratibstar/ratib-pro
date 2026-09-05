<?php
/**
 * Control Panel API: reset agency ERP business data (keep login passwords).
 * Authenticated control session only — not a public privileged surface.
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

function resetErpJson(array $payload): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload);
    exit;
}

if (empty($_SESSION['control_logged_in'])) {
    resetErpJson(['success' => false, 'message' => 'Unauthorized']);
}

require_once __DIR__ . '/../../includes/control-permissions.php';
if (!hasControlPermission(CONTROL_PERM_AGENCIES) && !hasControlPermission('edit_control_agency')) {
    resetErpJson(['success' => false, 'message' => 'Access denied']);
}

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl || !($ctrl instanceof mysqli)) {
    resetErpJson(['success' => false, 'message' => 'Database unavailable']);
}

if (!control_rateb_erp_is_installed()) {
    resetErpJson(['success' => false, 'message' => 'RATEB ERP is not installed on this server']);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true);
if (!is_array($data)) {
    $data = $_POST;
}

$agencyId = (int) ($data['agency_id'] ?? $data['id'] ?? 0);
$confirm = strtoupper(trim((string) ($data['confirm'] ?? '')));
$platformCompanyId = (int) ($data['platform_company_id'] ?? 0);

if ($agencyId < 1) {
    resetErpJson(['success' => false, 'message' => 'Invalid agency id']);
}
if ($confirm !== 'RESET-DATA') {
    resetErpJson(['success' => false, 'message' => 'Type RESET-DATA to confirm']);
}

$agency = rateb_lookup_agency_by_id($agencyId);
if ($agency === null) {
    resetErpJson(['success' => false, 'message' => 'Agency not found']);
}
if (trim((string) ($agency['erp_db_name'] ?? '')) === '') {
    resetErpJson(['success' => false, 'message' => 'No ERP database — run Provision ERP first']);
}

try {
    $report = control_rateb_erp_reset_agency_data(
        $agencyId,
        $platformCompanyId > 0 ? $platformCompanyId : null,
        $confirm
    );
    resetErpJson([
        'success' => true,
        'message' => 'ERP data reset',
        'data' => $report,
    ]);
} catch (Throwable $e) {
    error_log('agencies-reset-erp-data: ' . $e->getMessage());
    resetErpJson(['success' => false, 'message' => $e->getMessage()]);
}
