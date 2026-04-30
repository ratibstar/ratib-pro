<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/api/control/accounting-registration-revenue-export.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/api/control/accounting-registration-revenue-export.php`.
 */
/**
 * Control Panel API: Export registration revenue (paid registrations) as CSV for Accounting.
 */
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../../includes/config.php';

function safeCsvCell($v) {
    $s = (string) $v;
    if ($s !== '' && in_array($s[0], ['=', '+', '-', '@'], true)) {
        return "'" . $s;
    }
    return $s;
}

$isControl = defined('IS_CONTROL_PANEL') && IS_CONTROL_PANEL;
if (!$isControl || empty($_SESSION['control_logged_in'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Unauthorized');
}
require_once __DIR__ . '/../../includes/control-permissions.php';
if (!hasControlPermission('control_accounting') && !hasControlPermission('view_control_accounting')) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl) {
    header('HTTP/1.1 503 Service Unavailable');
    exit('Database unavailable');
}

$chk = $ctrl->query("SHOW TABLES LIKE 'control_registration_requests'");
if (!$chk || $chk->num_rows === 0) {
    header('HTTP/1.1 404 Not Found');
    exit('Table not found');
}

$allowedCountryIds = getControlPanelCountryScopeIds($ctrl);
$countryId = isset($_GET['country_id']) && ctype_digit($_GET['country_id']) ? (int)$_GET['country_id'] : 0;
$scope = isset($_GET['scope']) ? strtolower(trim((string)$_GET['scope'])) : 'collected';
if ($scope !== 'recognized') {
    $scope = 'collected';
}

$reqWhere = $scope === 'recognized'
    ? "status = 'approved' AND payment_status = 'paid' AND plan_amount > 0"
    : "status IN ('approved','pending') AND payment_status = 'paid' AND plan_amount > 0";
if ($countryId > 0) {
    $cid = (int)$countryId;
    $chkAg = @$ctrl->query("SHOW TABLES LIKE 'control_agencies'");
    $agencyMatch = ($chkAg && $chkAg->num_rows > 0) ? " OR (control_registration_requests.agency_id IS NOT NULL AND TRIM(control_registration_requests.agency_id) != '' AND EXISTS (SELECT 1 FROM control_agencies a WHERE a.country_id = $cid AND (a.id = CAST(NULLIF(TRIM(control_registration_requests.agency_id), '') AS UNSIGNED) OR CAST(a.id AS CHAR) = TRIM(control_registration_requests.agency_id))))" : '';
    $reqWhere .= " AND (country_id = $cid OR country_name IN (SELECT name FROM control_countries WHERE id = $cid)$agencyMatch)";
}
if ($allowedCountryIds !== null && !empty($allowedCountryIds)) {
    $idsStr = implode(',', array_map('intval', $allowedCountryIds));
    // Match explicit country_id and legacy rows (country_id 0/NULL) by country_name.
    $namesRes = @$ctrl->query("SELECT name FROM control_countries WHERE id IN ($idsStr) AND is_active = 1");
    $countryNames = [];
    if ($namesRes) {
        while ($nr = $namesRes->fetch_assoc()) {
            $countryNames[] = "'" . $ctrl->real_escape_string((string)$nr['name']) . "'";
        }
    }
    $nameMatch = !empty($countryNames)
        ? " OR (COALESCE(country_id, 0) = 0 AND country_name IN (" . implode(',', $countryNames) . "))"
        : "";
    $reqWhere .= " AND (country_id IN ($idsStr)$nameMatch)";
} elseif ($allowedCountryIds === []) $reqWhere .= " AND 1=0";

$res = $ctrl->query("SELECT id, agency_name, country_name, plan, plan_amount, status, payment_status, payment_method, created_at FROM control_registration_requests WHERE $reqWhere ORDER BY created_at DESC LIMIT 10000");
if (!$res) {
    header('HTTP/1.1 500 Internal Server Error');
    exit('Query failed');
}

$filename = 'registration-revenue-' . $scope . '-' . date('Y-m-d-His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
$out = fopen('php://output', 'w');
fprintf($out, "\xEF\xBB\xBF");
fputcsv($out, ['id', 'agency_name', 'country_name', 'plan', 'plan_amount', 'status', 'payment_status', 'payment_method', 'created_at']);
while ($row = $res->fetch_assoc()) {
    $safe = [];
    foreach ($row as $k => $v) {
        $safe[$k] = safeCsvCell($v);
    }
    fputcsv($out, $safe);
}
fclose($out);
exit;
