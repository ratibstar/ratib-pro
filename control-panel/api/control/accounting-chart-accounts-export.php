<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/api/control/accounting-chart-accounts-export.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/api/control/accounting-chart-accounts-export.php`.
 */
/**
 * Control Panel API: export chart of accounts as CSV (opens in new tab from accounting modals).
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
if (!hasControlPermission(CONTROL_PERM_ACCOUNTING) && !hasControlPermission('view_control_accounting') && !hasControlPermission('manage_control_accounting')) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl) {
    header('HTTP/1.1 503 Service Unavailable');
    exit('Database unavailable');
}

$chk = $ctrl->query("SHOW TABLES LIKE 'control_chart_accounts'");
if (!$chk || $chk->num_rows === 0) {
    header('HTTP/1.1 404 Not Found');
    exit('Chart of accounts table not found');
}

$allowedCountryIds = getControlPanelCountryScopeIds($ctrl);

$countryId = isset($_GET['country_id']) && ctype_digit((string) $_GET['country_id']) ? (int) $_GET['country_id'] : 0;
$agencyId = isset($_GET['agency_id']) && ctype_digit((string) $_GET['agency_id']) ? (int) $_GET['agency_id'] : 0;

$ids = [];
$idsRaw = trim((string) ($_GET['ids'] ?? ''));
if ($idsRaw !== '') {
    foreach (explode(',', $idsRaw) as $p) {
        $p = trim($p);
        if ($p !== '' && ctype_digit($p)) {
            $ids[] = (int) $p;
        }
    }
    $ids = array_values(array_unique(array_filter($ids)));
}

$conds = ['1=1'];

if ($ids !== []) {
    $conds[] = 'id IN (' . implode(',', array_map('intval', $ids)) . ')';
}

if ($allowedCountryIds === []) {
    $conds[] = '1=0';
} elseif ($allowedCountryIds !== null && !empty($allowedCountryIds)) {
    $list = implode(',', array_map('intval', $allowedCountryIds));
    $conds[] = "(country_id IN ($list) OR country_id = 0)";
}

if ($countryId > 0) {
    if ($allowedCountryIds !== null && !empty($allowedCountryIds) && !in_array($countryId, $allowedCountryIds, true)) {
        $conds[] = '1=0';
    } else {
        $conds[] = 'country_id = ' . (int) $countryId;
    }
}

if ($agencyId > 0) {
    $conds[] = 'agency_id = ' . (int) $agencyId;
}

$where = ' WHERE ' . implode(' AND ', $conds);
$sql = 'SELECT * FROM control_chart_accounts' . $where . ' ORDER BY account_code ASC, id ASC LIMIT 50000';
$res = $ctrl->query($sql);
if (!$res) {
    header('HTTP/1.1 500 Internal Server Error');
    exit('Query failed');
}

$rows = [];
while ($row = $res->fetch_assoc()) {
    $rows[] = $row;
}

$filename = 'chart-of-accounts-' . date('Y-m-d-His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');

$out = fopen('php://output', 'w');
fprintf($out, "\xEF\xBB\xBF");

if ($rows !== []) {
    fputcsv($out, array_keys($rows[0]));
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
} else {
    fputcsv($out, ['id', 'agency_id', 'country_id', 'account_code', 'account_name', 'account_type', 'balance', 'currency_code', 'is_active']);
}

fclose($out);
exit;
