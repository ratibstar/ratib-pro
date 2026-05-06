<?php
/**
 * EN: Control Panel API: Full agency setup audit across agency databases.
 * EN: Returns DB connectivity + required tables status for each agency.
 */
ini_set('display_errors', 0);
error_reporting(0);
ob_start();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../includes/control-api-same-origin-cors.php';
applyControlApiSameOriginCors();
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control-permissions.php';
require_once __DIR__ . '/agency-db-helper.php';

function jsonOut(array $data, int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data);
    exit;
}

function fetchTableNames(mysqli $conn): array
{
    $names = [];
    $res = @$conn->query('SHOW TABLES');
    if (!$res) {
        return $names;
    }
    while ($row = $res->fetch_row()) {
        $t = strtolower(trim((string)($row[0] ?? '')));
        if ($t !== '') {
            $names[] = $t;
        }
    }
    $res->free();
    return array_values(array_unique($names));
}

function missingFrom(array $required, array $existing): array
{
    $set = array_fill_keys($existing, true);
    $missing = [];
    foreach ($required as $t) {
        if (!isset($set[strtolower($t)])) {
            $missing[] = strtolower($t);
        }
    }
    return $missing;
}

function sanitizeAgencyRow(array $row): array
{
    return [
        'id' => (int)($row['id'] ?? 0),
        'name' => (string)($row['name'] ?? ''),
        'country_id' => (int)($row['country_id'] ?? 0),
        'country_name' => (string)($row['country_name'] ?? ''),
        'is_active' => (int)($row['is_active'] ?? 0),
        'db_name' => (string)($row['db_name'] ?? ''),
        'db_host' => (string)($row['db_host'] ?? ''),
        'db_port' => (int)($row['db_port'] ?? 3306),
        'db_user' => (string)($row['db_user'] ?? ''),
        'db_pass' => (string)($row['db_pass'] ?? ''),
        'country_slug' => (string)($row['country_slug'] ?? ''),
    ];
}

$isControl = defined('IS_CONTROL_PANEL') && IS_CONTROL_PANEL;
if (!$isControl || empty($_SESSION['control_logged_in'])) {
    jsonOut(['success' => false, 'message' => 'Unauthorized'], 401);
}
if (!hasControlPermission(CONTROL_PERM_AGENCIES) && !hasControlPermission('view_control_agencies')) {
    jsonOut(['success' => false, 'message' => 'Access denied'], 403);
}

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl instanceof mysqli) {
    jsonOut(['success' => false, 'message' => 'Control database unavailable'], 500);
}

$allowedCountryIds = getControlPanelCountryScopeIds($ctrl);
$where = [];
$params = [];
$types = '';
if ($allowedCountryIds === []) {
    jsonOut(['success' => true, 'message' => 'No accessible countries in scope', 'agencies' => [], 'summary' => []]);
}
if ($allowedCountryIds !== null) {
    $ph = implode(',', array_fill(0, count($allowedCountryIds), '?'));
    $where[] = "a.country_id IN ($ph)";
    $params = array_merge($params, $allowedCountryIds);
    $types .= str_repeat('i', count($allowedCountryIds));
}

$countryId = (int)($_GET['country_id'] ?? 0);
if ($countryId > 0) {
    if ($allowedCountryIds !== null && !in_array($countryId, $allowedCountryIds, true)) {
        jsonOut(['success' => false, 'message' => 'You do not have permission for this country'], 403);
    }
    $where[] = 'a.country_id = ?';
    $params[] = $countryId;
    $types .= 'i';
}

$referenceAgencyId = (int)($_GET['reference_agency_id'] ?? 0);

$sql = "SELECT a.id, a.name, a.country_id, COALESCE(c.name, '') AS country_name, COALESCE(c.slug, '') AS country_slug,
               a.is_active, a.db_host, a.db_port, a.db_user, a.db_pass, a.db_name
        FROM control_agencies a
        LEFT JOIN control_countries c ON c.id = a.country_id";
if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY c.name, a.name';

$stmt = $ctrl->prepare($sql);
if (!$stmt) {
    jsonOut(['success' => false, 'message' => 'Failed to prepare agencies query'], 500);
}
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
$agencyRows = $res ? ($res->fetch_all(MYSQLI_ASSOC) ?: []) : [];
$stmt->close();

if (empty($agencyRows)) {
    jsonOut(['success' => true, 'message' => 'No agencies found for scope', 'agencies' => [], 'summary' => []]);
}

$coreRequired = ['users', 'roles'];
$accountingRequired = ['accounts', 'journal_entries', 'entry_approval', 'financial_transactions', 'invoices', 'bills'];
$recommended = ['currencies', 'settings', 'permissions'];

$referenceTables = null;
if ($referenceAgencyId > 0) {
    foreach ($agencyRows as $r) {
        if ((int)$r['id'] === $referenceAgencyId) {
            $ref = sanitizeAgencyRow($r);
            $refConnData = getAgencyDbConnection($ref, (int)$ref['country_id']);
            if ($refConnData && !empty($refConnData['conn']) && $refConnData['conn'] instanceof mysqli) {
                $referenceTables = fetchTableNames($refConnData['conn']);
                $refConnData['conn']->close();
            }
            break;
        }
    }
}

$out = [];
$summary = [
    'agencies_total' => count($agencyRows),
    'db_connect_ok' => 0,
    'db_connect_failed' => 0,
    'core_ready' => 0,
    'accounting_ready' => 0,
    'full_ready' => 0,
];

foreach ($agencyRows as $raw) {
    $agency = sanitizeAgencyRow($raw);
    $connData = getAgencyDbConnection($agency, (int)$agency['country_id']);
    if (!$connData || empty($connData['conn']) || !($connData['conn'] instanceof mysqli)) {
        $summary['db_connect_failed']++;
        $out[] = [
            'agency' => [
                'id' => $agency['id'],
                'name' => $agency['name'],
                'country_id' => $agency['country_id'],
                'country_name' => $agency['country_name'],
                'is_active' => $agency['is_active'],
                'db_name' => $agency['db_name'],
            ],
            'db' => [
                'connected' => false,
                'error' => function_exists('getAgencyDbConnectionLastError') ? (string)getAgencyDbConnectionLastError() : 'Failed to connect',
            ],
            'checks' => [
                'core_missing' => $coreRequired,
                'accounting_missing' => $accountingRequired,
                'recommended_missing' => $recommended,
                'core_ready' => false,
                'accounting_ready' => false,
                'full_ready' => false,
            ],
            'template_diff' => $referenceTables === null ? null : [
                'missing_from_agency' => [],
                'extra_in_agency' => [],
            ],
        ];
        continue;
    }

    $summary['db_connect_ok']++;
    /** @var mysqli $agencyConn */
    $agencyConn = $connData['conn'];
    $tables = fetchTableNames($agencyConn);
    $agencyConn->close();

    $coreMissing = missingFrom($coreRequired, $tables);
    $acctMissing = missingFrom($accountingRequired, $tables);
    $recommendedMissing = missingFrom($recommended, $tables);

    $coreReady = empty($coreMissing);
    $accountingReady = empty($acctMissing);
    $fullReady = $coreReady && $accountingReady;
    if ($coreReady) {
        $summary['core_ready']++;
    }
    if ($accountingReady) {
        $summary['accounting_ready']++;
    }
    if ($fullReady) {
        $summary['full_ready']++;
    }

    $templateDiff = null;
    if (is_array($referenceTables)) {
        $existingSet = array_fill_keys($tables, true);
        $refSet = array_fill_keys($referenceTables, true);
        $missingFromAgency = [];
        foreach ($referenceTables as $t) {
            if (!isset($existingSet[$t])) {
                $missingFromAgency[] = $t;
            }
        }
        $extraInAgency = [];
        foreach ($tables as $t) {
            if (!isset($refSet[$t])) {
                $extraInAgency[] = $t;
            }
        }
        $templateDiff = [
            'missing_from_agency' => $missingFromAgency,
            'extra_in_agency' => $extraInAgency,
        ];
    }

    $out[] = [
        'agency' => [
            'id' => $agency['id'],
            'name' => $agency['name'],
            'country_id' => $agency['country_id'],
            'country_name' => $agency['country_name'],
            'is_active' => $agency['is_active'],
            'db_name' => $connData['db_name'] ?? $agency['db_name'],
        ],
        'db' => [
            'connected' => true,
            'connect_host' => $connData['connect_host'] ?? '',
            'connect_port' => (int)($connData['connect_port'] ?? 0),
            'table_count' => count($tables),
        ],
        'checks' => [
            'core_missing' => $coreMissing,
            'accounting_missing' => $acctMissing,
            'recommended_missing' => $recommendedMissing,
            'core_ready' => $coreReady,
            'accounting_ready' => $accountingReady,
            'full_ready' => $fullReady,
        ],
        'template_diff' => $templateDiff,
    ];
}

jsonOut([
    'success' => true,
    'message' => 'Agency audit completed',
    'reference_agency_id' => $referenceAgencyId > 0 ? $referenceAgencyId : null,
    'summary' => $summary,
    'agencies' => $out,
]);
