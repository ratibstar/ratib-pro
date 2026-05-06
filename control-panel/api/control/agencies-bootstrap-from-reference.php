<?php
/**
 * Control Panel API: Bootstrap agencies from a reference agency DB.
 *
 * Non-destructive by design:
 * - Creates missing tables in target agency DBs using reference table schema.
 * - Optionally seeds baseline rows only for safe config tables and only when target table is empty.
 *
 * Usage (logged control-panel session required):
 *   POST /control-panel/api/control/agencies-bootstrap-from-reference.php
 *   {
 *     "reference_agency_id": 12,
 *     "target_agency_ids": [2,3,4],      // optional; omit for all agencies in scope
 *     "apply": false,                      // false = dry-run, true = execute
 *     "seed_baseline": true                // optional, default true
 *   }
 */

ini_set('display_errors', 0);
error_reporting(0);
ob_start();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../includes/control-api-same-origin-cors.php';
applyControlApiSameOriginCors();
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control-permissions.php';

function json_out(array $data, int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data);
    exit;
}

function get_tables(mysqli $conn): array
{
    $out = [];
    $res = @$conn->query('SHOW TABLES');
    if (!$res) return $out;
    while ($row = $res->fetch_row()) {
        $t = strtolower(trim((string)($row[0] ?? '')));
        if ($t !== '') $out[] = $t;
    }
    $res->free();
    return array_values(array_unique($out));
}

function show_create_table(mysqli $conn, string $table): ?string
{
    $safe = str_replace('`', '``', $table);
    $res = @$conn->query("SHOW CREATE TABLE `{$safe}`");
    if (!$res) return null;
    $row = $res->fetch_assoc();
    $res->free();
    if (!$row) return null;
    $key = isset($row['Create Table']) ? 'Create Table' : (array_key_exists('Create Table', $row) ? 'Create Table' : null);
    if ($key === null) {
        foreach ($row as $k => $v) {
            if (stripos((string)$k, 'create table') !== false) {
                return (string)$v;
            }
        }
        return null;
    }
    return (string)$row[$key];
}

function table_count(mysqli $conn, string $table): ?int
{
    $safe = str_replace('`', '``', $table);
    $res = @$conn->query("SELECT COUNT(*) AS c FROM `{$safe}`");
    if (!$res) return null;
    $row = $res->fetch_assoc();
    $res->free();
    return isset($row['c']) ? (int)$row['c'] : null;
}

function fetch_all_rows(mysqli $conn, string $table): array
{
    $safe = str_replace('`', '``', $table);
    $rows = [];
    $res = @$conn->query("SELECT * FROM `{$safe}`");
    if (!$res) return $rows;
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    $res->free();
    return $rows;
}

function quote_sql(mysqli $conn, $value): string
{
    if ($value === null) return 'NULL';
    if (is_bool($value)) return $value ? '1' : '0';
    if (is_int($value) || is_float($value)) return (string)$value;
    return "'" . $conn->real_escape_string((string)$value) . "'";
}

function insert_rows(mysqli $conn, string $table, array $rows): int
{
    if (empty($rows)) return 0;
    $safeTable = str_replace('`', '``', $table);
    $inserted = 0;
    foreach ($rows as $row) {
        if (!is_array($row) || empty($row)) continue;
        $cols = array_keys($row);
        $colSql = implode(',', array_map(static function ($c) {
            return '`' . str_replace('`', '``', (string)$c) . '`';
        }, $cols));
        $valSql = implode(',', array_map(static function ($v) use ($conn) {
            return quote_sql($conn, $v);
        }, array_values($row)));
        $sql = "INSERT INTO `{$safeTable}` ({$colSql}) VALUES ({$valSql})";
        if (@$conn->query($sql)) {
            $inserted++;
        }
    }
    return $inserted;
}

function agency_row_by_id(mysqli $ctrl, int $agencyId): ?array
{
    $stmt = $ctrl->prepare("SELECT a.*, COALESCE(c.slug,'') AS country_slug, COALESCE(c.name,'') AS country_name
                            FROM control_agencies a
                            LEFT JOIN control_countries c ON c.id = a.country_id
                            WHERE a.id = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('i', $agencyId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) $res->free();
    $stmt->close();
    return $row ?: null;
}

function strict_agency_connect(array $agency): array
{
    $host = trim((string)($agency['db_host'] ?? ''));
    $user = trim((string)($agency['db_user'] ?? ''));
    $pass = (string)($agency['db_pass'] ?? '');
    $db   = trim((string)($agency['db_name'] ?? ''));
    $port = (int)($agency['db_port'] ?? 3306);
    if ($port <= 0) $port = 3306;

    // Allow credential fallback only when agency row fields are empty.
    // IMPORTANT: DB name never falls back; target DB remains strict per agency row.
    if ($host === '' && defined('DB_HOST')) {
        $host = (string)DB_HOST;
    }
    if ($user === '' && defined('DB_USER')) {
        $user = (string)DB_USER;
    }
    if ($pass === '' && defined('DB_PASS')) {
        $pass = (string)DB_PASS;
    }

    if ($host === '' || $user === '' || $db === '') {
        return ['ok' => false, 'error' => 'Missing DB host/user/db_name (db_name must exist in control_agencies)'];
    }

    $conn = @new mysqli($host, $user, $pass, $db, $port);
    if (!$conn || $conn->connect_error) {
        return ['ok' => false, 'error' => $conn ? $conn->connect_error : 'Unknown connect error'];
    }
    $conn->set_charset('utf8mb4');

    // Safety check: ensure selected DB is exactly the agency DB (no fallback/no drift).
    $dbRes = @$conn->query('SELECT DATABASE() AS db_name');
    $activeDb = '';
    if ($dbRes) {
        $row = $dbRes->fetch_assoc();
        $activeDb = (string)($row['db_name'] ?? '');
        $dbRes->free();
    }
    if (strcasecmp($activeDb, $db) !== 0) {
        $conn->close();
        return ['ok' => false, 'error' => "Active DB mismatch (expected {$db}, got {$activeDb})"];
    }

    return [
        'ok' => true,
        'conn' => $conn,
        'db_name' => $db,
        'host' => $host,
        'port' => $port,
        'used_fallback_credentials' => (
            trim((string)($agency['db_host'] ?? '')) === '' ||
            trim((string)($agency['db_user'] ?? '')) === '' ||
            (string)($agency['db_pass'] ?? '') === ''
        ),
    ];
}

function agencies_in_scope(mysqli $ctrl, ?array $allowedCountryIds, array $targetAgencyIds): array
{
    $where = [];
    $params = [];
    $types = '';

    if ($allowedCountryIds === []) return [];
    if ($allowedCountryIds !== null) {
        $ph = implode(',', array_fill(0, count($allowedCountryIds), '?'));
        $where[] = "a.country_id IN ($ph)";
        $params = array_merge($params, $allowedCountryIds);
        $types .= str_repeat('i', count($allowedCountryIds));
    }
    if (!empty($targetAgencyIds)) {
        $ph = implode(',', array_fill(0, count($targetAgencyIds), '?'));
        $where[] = "a.id IN ($ph)";
        $params = array_merge($params, $targetAgencyIds);
        $types .= str_repeat('i', count($targetAgencyIds));
    }
    $sql = "SELECT a.*, COALESCE(c.slug,'') AS country_slug, COALESCE(c.name,'') AS country_name
            FROM control_agencies a
            LEFT JOIN control_countries c ON c.id = a.country_id";
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY c.name, a.name';

    $stmt = $ctrl->prepare($sql);
    if (!$stmt) return [];
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? ($res->fetch_all(MYSQLI_ASSOC) ?: []) : [];
    if ($res) $res->free();
    $stmt->close();
    return $rows;
}

$isControl = defined('IS_CONTROL_PANEL') && IS_CONTROL_PANEL;
if (!$isControl || empty($_SESSION['control_logged_in'])) {
    json_out(['success' => false, 'message' => 'Unauthorized'], 401);
}
if (!hasControlPermission(CONTROL_PERM_AGENCIES) && !hasControlPermission('edit_control_agency')) {
    json_out(['success' => false, 'message' => 'Access denied'], 403);
}
$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl instanceof mysqli) {
    json_out(['success' => false, 'message' => 'Control database unavailable'], 500);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}
$referenceAgencyId = (int)($input['reference_agency_id'] ?? 0);
$apply = !empty($input['apply']);
$seedBaseline = !array_key_exists('seed_baseline', $input) || (bool)$input['seed_baseline'];
$targetAgencyIds = $input['target_agency_ids'] ?? [];
$targetAgencyIds = is_array($targetAgencyIds) ? array_values(array_unique(array_map('intval', $targetAgencyIds))) : [];
$targetAgencyIds = array_values(array_filter($targetAgencyIds, static fn($id) => $id > 0));

if ($referenceAgencyId <= 0) {
    json_out(['success' => false, 'message' => 'reference_agency_id is required'], 422);
}

$allowedCountryIds = getControlPanelCountryScopeIds($ctrl);
$reference = agency_row_by_id($ctrl, $referenceAgencyId);
if (!$reference) {
    json_out(['success' => false, 'message' => 'Reference agency not found'], 404);
}
if ($allowedCountryIds !== null && !in_array((int)$reference['country_id'], $allowedCountryIds, true)) {
    json_out(['success' => false, 'message' => 'Reference agency is outside your permission scope'], 403);
}

$refConnData = strict_agency_connect($reference);
if (empty($refConnData['ok']) || empty($refConnData['conn']) || !($refConnData['conn'] instanceof mysqli)) {
    $err = (string)($refConnData['error'] ?? 'Failed to connect reference agency DB');
    json_out(['success' => false, 'message' => 'Reference DB connection failed', 'error' => $err], 500);
}
/** @var mysqli $refConn */
$refConn = $refConnData['conn'];
$refTables = get_tables($refConn);

// Safe baseline tables to seed if missing/empty (non-transactional defaults).
$baselineSeedTables = ['roles', 'permissions', 'currencies', 'settings', 'account_types'];
$baselineRows = [];
if ($seedBaseline) {
    foreach ($baselineSeedTables as $t) {
        if (in_array($t, $refTables, true)) {
            $baselineRows[$t] = fetch_all_rows($refConn, $t);
        }
    }
}

$targetAgencies = agencies_in_scope($ctrl, $allowedCountryIds, $targetAgencyIds);
if (empty($targetAgencies)) {
    $refConn->close();
    json_out([
        'success' => true,
        'message' => 'No target agencies found in scope',
        'mode' => $apply ? 'apply' : 'dry-run',
        'results' => [],
    ]);
}

$results = [];
$summary = [
    'targets_total' => 0,
    'targets_connected' => 0,
    'targets_failed_connect' => 0,
    'tables_to_create' => 0,
    'tables_created' => 0,
    'baseline_rows_to_seed' => 0,
    'baseline_rows_seeded' => 0,
];

foreach ($targetAgencies as $agency) {
    $aid = (int)($agency['id'] ?? 0);
    if ($aid <= 0 || $aid === $referenceAgencyId) {
        continue;
    }
    $summary['targets_total']++;
    $connData = strict_agency_connect($agency);
    if (empty($connData['ok']) || empty($connData['conn']) || !($connData['conn'] instanceof mysqli)) {
        $summary['targets_failed_connect']++;
        $results[] = [
            'agency_id' => $aid,
            'agency_name' => (string)($agency['name'] ?? ''),
            'country_name' => (string)($agency['country_name'] ?? ''),
            'db_name' => (string)($agency['db_name'] ?? ''),
            'connected' => false,
            'error' => (string)($connData['error'] ?? 'DB connect failed'),
            'tables_created' => [],
            'tables_missing_before' => [],
            'baseline_seed' => [],
        ];
        continue;
    }
    $summary['targets_connected']++;
    /** @var mysqli $targetConn */
    $targetConn = $connData['conn'];
    $targetTables = get_tables($targetConn);
    $targetSet = array_fill_keys($targetTables, true);
    $toCreate = [];
    foreach ($refTables as $t) {
        if (!isset($targetSet[$t])) {
            $toCreate[] = $t;
        }
    }

    $summary['tables_to_create'] += count($toCreate);
    $created = [];
    if ($apply && !empty($toCreate)) {
        foreach ($toCreate as $t) {
            $ddl = show_create_table($refConn, $t);
            if (!$ddl) continue;
            if (@$targetConn->query($ddl)) {
                $created[] = $t;
                $summary['tables_created']++;
            }
        }
    }

    $seedReport = [];
    if ($seedBaseline) {
        foreach ($baselineRows as $table => $rows) {
            if (empty($rows)) continue;
            $cnt = table_count($targetConn, $table);
            if ($cnt === null) continue; // table missing / inaccessible
            if ($cnt > 0) {
                $seedReport[] = ['table' => $table, 'action' => 'skip_not_empty', 'existing_rows' => $cnt, 'seeded_rows' => 0];
                continue;
            }
            $summary['baseline_rows_to_seed'] += count($rows);
            $seeded = 0;
            if ($apply) {
                $seeded = insert_rows($targetConn, $table, $rows);
                $summary['baseline_rows_seeded'] += $seeded;
            }
            $seedReport[] = ['table' => $table, 'action' => $apply ? 'seeded' : 'would_seed', 'existing_rows' => $cnt, 'seeded_rows' => $seeded, 'planned_rows' => count($rows)];
        }
    }

    $results[] = [
        'agency_id' => $aid,
        'agency_name' => (string)($agency['name'] ?? ''),
        'country_name' => (string)($agency['country_name'] ?? ''),
        'db_name' => (string)($connData['db_name'] ?? ($agency['db_name'] ?? '')),
        'used_fallback_credentials' => !empty($connData['used_fallback_credentials']),
        'connected' => true,
        'tables_missing_before' => $toCreate,
        'tables_created' => $created,
        'baseline_seed' => $seedReport,
    ];
    $targetConn->close();
}

$refConn->close();

json_out([
    'success' => true,
    'message' => $apply ? 'Bootstrap applied' : 'Dry-run completed',
    'mode' => $apply ? 'apply' : 'dry-run',
    'reference_agency' => [
        'id' => (int)$reference['id'],
        'name' => (string)($reference['name'] ?? ''),
        'country_name' => (string)($reference['country_name'] ?? ''),
        'db_name' => (string)($refConnData['db_name'] ?? ($reference['db_name'] ?? '')),
    ],
    'summary' => $summary,
    'results' => $results,
]);
