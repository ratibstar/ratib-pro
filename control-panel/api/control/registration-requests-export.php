<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/api/control/registration-requests-export.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/api/control/registration-requests-export.php`.
 */
/**
 * Control Panel API: Export registration requests as CSV
 * Same auth and filters as Registration Requests page.
 */
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../../includes/config.php';

$isControl = defined('IS_CONTROL_PANEL') && IS_CONTROL_PANEL;
if (!$isControl || empty($_SESSION['control_logged_in'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Unauthorized');
}
require_once __DIR__ . '/../../includes/control-permissions.php';
if (!hasControlPermission(CONTROL_PERM_REGISTRATION) && !hasControlPermission('view_control_registration')) {
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

$colCountry = @$ctrl->query("SHOW COLUMNS FROM control_registration_requests LIKE 'country_id'");
$hasCountryIdCol = ($colCountry && $colCountry->num_rows > 0);
$colPaymentStatus = @$ctrl->query("SHOW COLUMNS FROM control_registration_requests LIKE 'payment_status'");
$hasPaymentStatusCol = ($colPaymentStatus && $colPaymentStatus->num_rows > 0);
$colPlanAmount = @$ctrl->query("SHOW COLUMNS FROM control_registration_requests LIKE 'plan_amount'");
$hasPlanAmountCol = ($colPlanAmount && $colPlanAmount->num_rows > 0);

$status = trim($_GET['status'] ?? '');
$planFilter = trim($_GET['plan'] ?? '');
$search = trim($_GET['search'] ?? '');
$paymentStatusFilter = trim($_GET['payment_status'] ?? '');
$dateFromRaw = trim((string)($_GET['date_from'] ?? ''));
$dateToRaw = trim((string)($_GET['date_to'] ?? ''));
if (isset($_GET['all_dates'])) {
    $allDates = ((string) $_GET['all_dates'] === '1');
} elseif ($dateFromRaw !== '' || $dateToRaw !== '') {
    $allDates = false;
} else {
    $allDates = true;
}
$dateFrom = $dateFromRaw;
$dateTo = $dateToRaw;
if (!$allDates && $dateFrom === '' && $dateTo === '') {
    $dateFrom = date('Y-m-01');
    $dateTo = date('Y-m-d');
}
$amountMin = null;
$amountMax = null;
$rawAmountMin = trim((string)($_GET['amount_min'] ?? ''));
$rawAmountMax = trim((string)($_GET['amount_max'] ?? ''));
if ($rawAmountMin !== '' && is_numeric($rawAmountMin)) {
    $amountMin = (float)$rawAmountMin;
}
if ($rawAmountMax !== '' && is_numeric($rawAmountMax)) {
    $amountMax = (float)$rawAmountMax;
}

$conds = [];
$scopeRegIds = function_exists('getRegistrationRequestScopeCountryIds') ? getRegistrationRequestScopeCountryIds($ctrl) : null;
$regExportViewAll = ($scopeRegIds === null);
if ($scopeRegIds === []) {
    if (!$regExportViewAll) {
        $conds[] = '1=0';
    }
} elseif (!$regExportViewAll && $scopeRegIds !== null && !empty($scopeRegIds) && $hasCountryIdCol) {
    $idsStr = implode(',', array_map('intval', $scopeRegIds));
    $namesRes = @$ctrl->query("SELECT name FROM control_countries WHERE id IN ($idsStr) AND is_active = 1");
    $countryNames = [];
    if ($namesRes) {
        while ($r = $namesRes->fetch_assoc()) {
            $countryNames[] = "'" . $ctrl->real_escape_string($r['name']) . "'";
        }
    }
    $nameMatch = !empty($countryNames) ? " OR (COALESCE(country_id, 0) = 0 AND country_name IN (" . implode(',', $countryNames) . "))" : '';
    $conds[] = "(country_id IN ($idsStr)$nameMatch)";
}
if ($status !== '' && in_array($status, ['pending','approved','rejected'])) { $conds[] = "status = '" . $ctrl->real_escape_string($status) . "'"; }
if ($planFilter !== '' && in_array($planFilter, ['pro','gold','platinum'])) { $conds[] = "plan = '" . $ctrl->real_escape_string($planFilter) . "'"; }
if ($hasPaymentStatusCol && $paymentStatusFilter === 'unpaid') {
    $conds[] = "(LOWER(TRIM(COALESCE(payment_status,''))) <> 'paid')";
} elseif ($hasPaymentStatusCol && $paymentStatusFilter !== '' && in_array($paymentStatusFilter, ['pending', 'paid', 'failed'], true)) {
    $conds[] = "payment_status = '" . $ctrl->real_escape_string($paymentStatusFilter) . "'";
}
if ($dateFrom !== '') { $d = date('Y-m-d', strtotime($dateFrom)); if ($d) $conds[] = "DATE(created_at) >= '" . $ctrl->real_escape_string($d) . "'"; }
if ($dateTo !== '') { $d = date('Y-m-d', strtotime($dateTo)); if ($d) $conds[] = "DATE(created_at) <= '" . $ctrl->real_escape_string($d) . "'"; }
if ($hasPlanAmountCol && $amountMin !== null && is_finite($amountMin)) { $conds[] = "COALESCE(plan_amount, 0) >= " . (float)$amountMin; }
if ($hasPlanAmountCol && $amountMax !== null && is_finite($amountMax)) { $conds[] = "COALESCE(plan_amount, 0) <= " . (float)$amountMax; }
if ($search !== '') {
    $q = $ctrl->real_escape_string($search);
    $idNum = (int)preg_replace('/[^0-9]/', '', $search);
    $idPart = ($idNum > 0) ? "id = " . $idNum . " OR " : "";
    $colChk = @$ctrl->query("SHOW COLUMNS FROM control_registration_requests LIKE 'agency_id'");
    $hasAgencyIdCol = ($colChk && $colChk->num_rows > 0);
    $agencyIdPart = $hasAgencyIdCol ? " OR agency_id LIKE '%" . $q . "%'" : "";
    $conds[] = "(" . $idPart . "agency_name LIKE '%" . $q . "%'" . $agencyIdPart . " OR contact_email LIKE '%" . $q . "%' OR country_name LIKE '%" . $q . "%' OR notes LIKE '%" . $q . "%')";
}
$condsForList = $conds;
if ($status === '') {
    $condsForList[] = "LOWER(TRIM(COALESCE(status,''))) <> 'approved'";
}
$whereForList = count($condsForList) ? ' WHERE ' . implode(' AND ', $condsForList) : '';
$res = $ctrl->query("SELECT * FROM control_registration_requests" . $whereForList . " ORDER BY id DESC LIMIT 10000");
if (!$res) {
    header('HTTP/1.1 500 Internal Server Error');
    exit('Query failed');
}

$exportRows = [];
while ($row = $res->fetch_assoc()) {
    $exportRows[] = $row;
}
$ngMergePath = __DIR__ . '/../../includes/registration_requests_ngenius_display_merge.php';
if (is_readable($ngMergePath)) {
    require_once $ngMergePath;
    if (function_exists('registration_requests_merge_ngenius_orders_for_display')) {
        registration_requests_merge_ngenius_orders_for_display($ctrl, $exportRows);
    }
}

$filename = 'registration-requests-' . date('Y-m-d-His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
$out = fopen('php://output', 'w');
fprintf($out, "\xEF\xBB\xBF"); // UTF-8 BOM

if ($exportRows !== []) {
    fputcsv($out, array_keys($exportRows[0]));
    foreach ($exportRows as $row) {
        fputcsv($out, $row);
    }
} else {
    fputcsv($out, ['id', 'agency_name', 'country_id', 'country_name', 'contact_email', 'contact_phone', 'desired_site_url', 'notes', 'plan', 'plan_amount', 'payment_status', 'payment_method', 'status', 'created_at', 'updated_at']);
}
fclose($out);
exit;
