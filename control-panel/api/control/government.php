<?php
/**
 * Control Panel API — Government Labor Monitoring (tenant DB via Database::getInstance).
 */
ini_set('display_errors', '0');
ob_start();
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../includes/control-api-same-origin-cors.php';
applyControlApiSameOriginCors();
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../../includes/config.php';
error_reporting(0);

// Always emit JSON even when a fatal error happens.
set_exception_handler(static function ($e): void {
    $msg = ($e instanceof Throwable) ? $e->getMessage() : 'Unhandled exception';
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => false, 'message' => 'Government API exception: ' . $msg], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
});

register_shutdown_function(static function (): void {
    $err = error_get_last();
    if (!is_array($err)) {
        return;
    }
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array((int) ($err['type'] ?? 0), $fatalTypes, true)) {
        return;
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    $message = isset($err['message']) ? (string) $err['message'] : 'Fatal error';
    echo json_encode(['success' => false, 'message' => 'Government API fatal: ' . $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
});

function gov_json_out(array $data, int $code = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$isControl = defined('IS_CONTROL_PANEL') && IS_CONTROL_PANEL;
if (!$isControl || empty($_SESSION['control_logged_in'])) {
    gov_json_out(['success' => false, 'message' => 'Unauthorized'], 401);
}

require_once __DIR__ . '/../../includes/control-permissions.php';
$canView = hasControlPermission(CONTROL_PERM_GOVERNMENT)
    || hasControlPermission('view_control_government')
    || hasControlPermission('gov_admin');
$canManage = hasControlPermission(CONTROL_PERM_GOVERNMENT)
    || hasControlPermission('manage_control_government')
    || hasControlPermission('gov_admin');
if (!$canView) {
    gov_json_out(['success' => false, 'message' => 'Access denied'], 403);
}

require_once dirname(__DIR__, 3) . '/includes/government-labor.php';

try {
    /**
     * Use the active control-panel DB context directly:
     * - agency DB when selected (via $GLOBALS['agency_db'])
     * - otherwise control DB constants
     * This avoids cross-bootstrap issues with the generic app Database singleton.
     */
    $agencyDb = $GLOBALS['agency_db'] ?? null;
    if (is_array($agencyDb) && !empty($agencyDb['db'])) {
        $host = (string) ($agencyDb['host'] ?? 'localhost');
        $port = (int) ($agencyDb['port'] ?? 3306);
        $dbName = (string) $agencyDb['db'];
        $user = (string) ($agencyDb['user'] ?? (defined('DB_USER') ? DB_USER : ''));
        $pass = (string) ($agencyDb['pass'] ?? (defined('DB_PASS') ? DB_PASS : ''));
    } else {
        $host = defined('DB_HOST') ? (string) DB_HOST : 'localhost';
        $port = defined('DB_PORT') ? (int) DB_PORT : 3306;
        $dbName = defined('DB_NAME') ? (string) DB_NAME : '';
        $user = defined('DB_USER') ? (string) DB_USER : '';
        $pass = defined('DB_PASS') ? (string) DB_PASS : '';
    }

    if ($dbName === '' || $user === '') {
        throw new RuntimeException('Missing DB credentials for government module');
    }

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbName);
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (Throwable $e) {
    gov_json_out(['success' => false, 'message' => 'Database unavailable: ' . $e->getMessage()], 503);
}

ratibEnsureGovernmentLaborSchema($pdo);
$hasWorkersTable = false;
$workersSchema = '';
$autoAgencyDbUsed = false;
try {
    $chkWorkers = $pdo->query("SHOW TABLES LIKE 'workers'");
    $hasWorkersTable = (bool) ($chkWorkers && $chkWorkers->fetchColumn());
} catch (Throwable $e) {
    $hasWorkersTable = false;
}
try {
    $curDb = (string) ($pdo->query('SELECT DATABASE()')->fetchColumn() ?: '');
    $workersSchema = $curDb;
} catch (Throwable $e) {
    $workersSchema = '';
}
if (!$hasWorkersTable) {
    // Auto-resolve to a real agency DB from selected country when current context is control DB.
    $ctrlMysqli = $GLOBALS['control_conn'] ?? null;
    $countryId = isset($_SESSION['control_country_id']) ? (int) $_SESSION['control_country_id'] : 0;
    if ($ctrlMysqli instanceof mysqli && $countryId > 0) {
        try {
            $stAgency = $ctrlMysqli->prepare(
                "SELECT db_host, db_port, db_user, db_pass, db_name
                 FROM control_agencies
                 WHERE country_id = ? AND is_active = 1 AND COALESCE(is_suspended, 0) = 0
                 ORDER BY id ASC
                 LIMIT 1"
            );
            if ($stAgency) {
                $stAgency->bind_param('i', $countryId);
                $stAgency->execute();
                $agency = $stAgency->get_result()->fetch_assoc();
                $stAgency->close();
                if (is_array($agency) && !empty($agency['db_name'])) {
                    $aHost = (string) ($agency['db_host'] ?? 'localhost');
                    $aPort = (int) ($agency['db_port'] ?? 3306);
                    $aUser = (string) ($agency['db_user'] ?? '');
                    $aPass = (string) ($agency['db_pass'] ?? '');
                    $aDb = (string) ($agency['db_name'] ?? '');
                    if ($aDb !== '' && $aUser !== '') {
                        $agencyDsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $aHost, $aPort, $aDb);
                        $pdo = new PDO($agencyDsn, $aUser, $aPass, [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                            PDO::ATTR_EMULATE_PREPARES => false,
                        ]);
                        ratibEnsureGovernmentLaborSchema($pdo);
                        $chkAgencyWorkers = $pdo->query("SHOW TABLES LIKE 'workers'");
                        $hasWorkersTable = (bool) ($chkAgencyWorkers && $chkAgencyWorkers->fetchColumn());
                        if ($hasWorkersTable) {
                            $workersSchema = (string) ($pdo->query('SELECT DATABASE()')->fetchColumn() ?: '');
                            $autoAgencyDbUsed = true;
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            // keep fallback path below
        }
    }

    $fallbackSchema = defined('RATIB_PRO_DB_NAME') ? (string) RATIB_PRO_DB_NAME : '';
    if ($fallbackSchema !== '' && preg_match('/^[A-Za-z0-9_]+$/', $fallbackSchema)) {
        try {
            $chkFallback = $pdo->query("SHOW TABLES FROM `{$fallbackSchema}` LIKE 'workers'");
            if ($chkFallback && $chkFallback->fetchColumn()) {
                $hasWorkersTable = true;
                $workersSchema = $fallbackSchema;
            }
        } catch (Throwable $e) {
            // keep current flags
        }
    }
}
$workersTable = ($hasWorkersTable && $workersSchema !== '' && preg_match('/^[A-Za-z0-9_]+$/', $workersSchema))
    ? ("`{$workersSchema}`.`workers`")
    : '`workers`';

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? trim((string) $_GET['action']) : '';

if ($method === 'GET' && ($action === '' || $action === 'summary')) {
    $sum = ratib_government_dashboard_summary_pdo($pdo);
    $sum['meta'] = [
        'has_workers_table' => $hasWorkersTable,
        'workers_schema' => $workersSchema,
        'auto_agency_db_used' => $autoAgencyDbUsed,
    ];
    gov_json_out(['success' => true, 'data' => $sum]);
}

if ($method === 'GET' && $action === 'inspections') {
    $country = trim((string) ($_GET['country'] ?? ''));
    $agencyId = isset($_GET['agency_id']) ? (int) $_GET['agency_id'] : 0;
    $search = trim((string) ($_GET['q'] ?? ''));
    $sql = "SELECT i.id, i.worker_id, i.agency_id, i.inspector_name, i.inspector_identity, i.inspection_date, i.status, i.notes, i.created_at";
    if ($hasWorkersTable) {
        $sql .= ", w.worker_name, w.country AS worker_country, w.formatted_id
            FROM gov_inspections i
            LEFT JOIN {$workersTable} w ON w.id = i.worker_id
            WHERE 1=1";
    } else {
        $sql .= ", NULL AS worker_name, NULL AS worker_country, NULL AS formatted_id
            FROM gov_inspections i
            WHERE 1=1";
    }
    $params = [];
    if ($country !== '' && $hasWorkersTable) {
        $sql .= ' AND w.country = ?';
        $params[] = $country;
    }
    if ($agencyId > 0) {
        $sql .= ' AND i.agency_id = ?';
        $params[] = $agencyId;
    }
    if ($search !== '') {
        $like = '%' . $search . '%';
        if ($hasWorkersTable) {
            $sql .= " AND (
                CAST(i.worker_id AS CHAR) LIKE ?
                OR i.inspector_name LIKE ?
                OR COALESCE(i.inspector_identity, '') LIKE ?
                OR COALESCE(i.notes, '') LIKE ?
                OR COALESCE(w.worker_name, '') LIKE ?
                OR COALESCE(w.formatted_id, '') LIKE ?
            )";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        } else {
            $sql .= " AND (
                CAST(i.worker_id AS CHAR) LIKE ?
                OR i.inspector_name LIKE ?
                OR COALESCE(i.inspector_identity, '') LIKE ?
                OR COALESCE(i.notes, '') LIKE ?
            )";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
    }
    $sql .= ' ORDER BY i.inspection_date DESC, i.id DESC LIMIT 500';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    gov_json_out(['success' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($method === 'GET' && $action === 'violations') {
    $workerId = isset($_GET['worker_id']) ? (int) $_GET['worker_id'] : 0;
    $sql = "SELECT v.*";
    if ($hasWorkersTable) {
        $sql .= ", w.worker_name FROM gov_violations v
            LEFT JOIN {$workersTable} w ON w.id = v.worker_id WHERE 1=1";
    } else {
        $sql .= ", NULL AS worker_name FROM gov_violations v WHERE 1=1";
    }
    $params = [];
    if ($workerId > 0) {
        $sql .= ' AND v.worker_id = ?';
        $params[] = $workerId;
    }
    $sql .= ' ORDER BY v.id DESC LIMIT 500';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    gov_json_out(['success' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($method === 'GET' && $action === 'blacklist') {
    $sql = "SELECT b.*";
    if ($hasWorkersTable) {
        $sql .= ", w.worker_name
         FROM gov_blacklist b
         LEFT JOIN {$workersTable} w ON b.entity_type = 'worker' AND w.id = b.entity_id
         ORDER BY b.id DESC LIMIT 500";
    } else {
        $sql .= ", NULL AS worker_name
         FROM gov_blacklist b
         ORDER BY b.id DESC LIMIT 500";
    }
    $st = $pdo->query($sql);
    gov_json_out(['success' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($method === 'GET' && $action === 'tracking') {
    $country = trim((string) ($_GET['country'] ?? ''));
    $status = trim((string) ($_GET['status'] ?? ''));
    $sql = "SELECT t.*";
    if ($hasWorkersTable) {
        $sql .= ", w.worker_name, w.country AS worker_country, w.formatted_id
            FROM gov_worker_tracking t
            JOIN {$workersTable} w ON w.id = t.worker_id
            WHERE 1=1";
    } else {
        $sql .= ", NULL AS worker_name, NULL AS worker_country, NULL AS formatted_id
            FROM gov_worker_tracking t
            WHERE 1=1";
    }
    $params = [];
    if ($country !== '' && $hasWorkersTable) {
        $sql .= ' AND w.country = ?';
        $params[] = $country;
    }
    if ($status !== '' && in_array($status, ['safe', 'warning', 'alert'], true)) {
        $sql .= ' AND t.status = ?';
        $params[] = $status;
    }
    $sql .= ' ORDER BY t.last_checkin IS NULL, t.last_checkin DESC LIMIT 500';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    gov_json_out(['success' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($method === 'GET' && $action === 'workers') {
    if (!$hasWorkersTable) {
        gov_json_out(['success' => true, 'data' => []]);
    }
    $q = trim((string) ($_GET['q'] ?? ''));
    if (strlen($q) < 1) {
        $st = $pdo->query(
            "SELECT id, worker_name, formatted_id, country
             FROM {$workersTable}
             WHERE status != 'deleted'
             ORDER BY id DESC
             LIMIT 500"
        );
        gov_json_out(['success' => true, 'data' => ($st ? $st->fetchAll(PDO::FETCH_ASSOC) : [])]);
    }
    $like = '%' . $q . '%';
    $st = $pdo->prepare(
        "SELECT id, worker_name, formatted_id, country FROM {$workersTable}
         WHERE status != 'deleted' AND (worker_name LIKE ? OR formatted_id LIKE ? OR CAST(id AS CHAR) = ?)
         ORDER BY id DESC LIMIT 120"
    );
    $st->execute([$like, $like, $q]);
    gov_json_out(['success' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
}

if (!$canManage) {
    gov_json_out(['success' => false, 'message' => 'Manage permission required'], 403);
}

$body = json_decode((string) file_get_contents('php://input'), true) ?: [];

if ($method === 'POST' && $action === 'inspection') {
    $workerId = (int) ($body['worker_id'] ?? 0);
    $agencyId = isset($body['agency_id']) ? (int) $body['agency_id'] : null;
    $inspector = trim((string) ($body['inspector_name'] ?? ''));
    $identity = trim((string) ($body['identity'] ?? ''));
    $password = trim((string) ($body['password'] ?? ''));
    $passwordHash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;
    $date = trim((string) ($body['inspection_date'] ?? ''));
    $status = strtolower(trim((string) ($body['status'] ?? 'pending')));
    if (!in_array($status, ['pending', 'passed', 'failed'], true)) {
        $status = 'pending';
    }
    $notes = trim((string) ($body['notes'] ?? ''));
    if ($workerId <= 0 || $inspector === '' || $date === '') {
        gov_json_out(['success' => false, 'message' => 'worker_id, inspector_name, inspection_date required'], 422);
    }
    $st = $pdo->prepare(
        "INSERT INTO gov_inspections (worker_id, agency_id, inspector_name, inspector_identity, inspector_password_hash, inspection_date, status, notes)
         VALUES (?,?,?,?,?,?,?,?)"
    );
    $st->execute([
        $workerId,
        $agencyId ?: null,
        $inspector,
        $identity !== '' ? $identity : null,
        $passwordHash,
        $date,
        $status,
        $notes !== '' ? $notes : null,
    ]);
    $id = (int) $pdo->lastInsertId();
    $r = $pdo->prepare('SELECT * FROM gov_inspections WHERE id = ?');
    $r->execute([$id]);
    gov_json_out(['success' => true, 'data' => $r->fetch(PDO::FETCH_ASSOC)], 201);
}

if ($method === 'POST' && $action === 'violation') {
    $workerId = (int) ($body['worker_id'] ?? 0);
    $agencyId = isset($body['agency_id']) ? (int) $body['agency_id'] : null;
    $inspectionId = isset($body['inspection_id']) ? (int) $body['inspection_id'] : null;
    $type = trim((string) ($body['type'] ?? ''));
    $severity = strtolower(trim((string) ($body['severity'] ?? 'medium')));
    if (!in_array($severity, ['low', 'medium', 'high'], true)) {
        $severity = 'medium';
    }
    $desc = trim((string) ($body['description'] ?? ''));
    $actionTaken = trim((string) ($body['action_taken'] ?? ''));
    if ($workerId <= 0 || $type === '' || $desc === '') {
        gov_json_out(['success' => false, 'message' => 'worker_id, type, description required'], 422);
    }
    $st = $pdo->prepare(
        "INSERT INTO gov_violations (worker_id, agency_id, inspection_id, type, severity, description, action_taken)
         VALUES (?,?,?,?,?,?,?)"
    );
    $st->execute([
        $workerId,
        $agencyId ?: null,
        $inspectionId ?: null,
        $type,
        $severity,
        $desc,
        $actionTaken !== '' ? $actionTaken : null,
    ]);
    $id = (int) $pdo->lastInsertId();
    $r = $pdo->prepare('SELECT * FROM gov_violations WHERE id = ?');
    $r->execute([$id]);
    gov_json_out(['success' => true, 'data' => $r->fetch(PDO::FETCH_ASSOC)], 201);
}

if ($method === 'POST' && $action === 'blacklist') {
    $entityType = strtolower(trim((string) ($body['entity_type'] ?? '')));
    $entityId = (int) ($body['entity_id'] ?? 0);
    $reason = trim((string) ($body['reason'] ?? ''));
    if (!in_array($entityType, ['worker', 'agency'], true) || $entityId <= 0 || $reason === '') {
        gov_json_out(['success' => false, 'message' => 'entity_type (worker|agency), entity_id, reason required'], 422);
    }
    $pdo->prepare(
        "UPDATE gov_blacklist SET status = 'removed' WHERE entity_type = ? AND entity_id = ? AND status = 'active'"
    )->execute([$entityType, $entityId]);
    $st = $pdo->prepare(
        "INSERT INTO gov_blacklist (entity_type, entity_id, reason, status) VALUES (?,?,?,'active')"
    );
    $st->execute([$entityType, $entityId, $reason]);
    $id = (int) $pdo->lastInsertId();
    $r = $pdo->prepare('SELECT * FROM gov_blacklist WHERE id = ?');
    $r->execute([$id]);
    gov_json_out(['success' => true, 'data' => $r->fetch(PDO::FETCH_ASSOC)], 201);
}

if ($method === 'POST' && $action === 'blacklist_remove') {
    $id = (int) ($body['id'] ?? 0);
    if ($id <= 0) {
        gov_json_out(['success' => false, 'message' => 'id required'], 422);
    }
    $pdo->prepare("UPDATE gov_blacklist SET status = 'removed' WHERE id = ? AND status = 'active'")->execute([$id]);
    gov_json_out(['success' => true, 'message' => 'Updated']);
}

if ($method === 'POST' && $action === 'tracking') {
    $workerId = (int) ($body['worker_id'] ?? 0);
    $last = trim((string) ($body['last_checkin'] ?? ''));
    $loc = trim((string) ($body['location_text'] ?? ''));
    $status = strtolower(trim((string) ($body['status'] ?? 'safe')));
    if (!in_array($status, ['safe', 'warning', 'alert'], true)) {
        $status = 'safe';
    }
    if ($workerId <= 0) {
        gov_json_out(['success' => false, 'message' => 'worker_id required'], 422);
    }
    $lastVal = $last !== '' ? $last : null;
    $sql = "INSERT INTO gov_worker_tracking (worker_id, last_checkin, location_text, status)
            VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE
              last_checkin = VALUES(last_checkin),
              location_text = VALUES(location_text),
              status = VALUES(status)";
    $st = $pdo->prepare($sql);
    $st->execute([$workerId, $lastVal, $loc !== '' ? $loc : null, $status]);
    $r = $pdo->prepare('SELECT * FROM gov_worker_tracking WHERE worker_id = ?');
    $r->execute([$workerId]);
    gov_json_out(['success' => true, 'data' => $r->fetch(PDO::FETCH_ASSOC)]);
}

if ($method === 'POST' && $action === 'seed_demo') {
    if (!$hasWorkersTable) {
        gov_json_out(['success' => false, 'message' => 'Real workers table not available in active agency context'], 422);
    }

    $workers = [];
    try {
        $workers = $pdo->query(
            "SELECT id, country FROM {$workersTable}
             WHERE status != 'deleted'
             ORDER BY id DESC
             LIMIT 3"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $workers = [];
    }

    if (count($workers) < 1) {
        gov_json_out(['success' => false, 'message' => 'No real workers found in active agency database'], 422);
    }

    $w1 = (int) ($workers[0]['id'] ?? 0);
    $w2 = (int) ($workers[1]['id'] ?? $w1);
    $country = trim((string) ($workers[0]['country'] ?? 'Indonesia'));
    if ($country === '') {
        $country = 'Indonesia';
    }

    $today = date('Y-m-d');
    $lastWeek = date('Y-m-d', strtotime('-7 days'));
    $nowDateTime = date('Y-m-d H:i:s');

    $pdo->beginTransaction();
    try {
        $ins1 = $pdo->prepare(
            "INSERT INTO gov_inspections (worker_id, agency_id, inspector_name, inspector_identity, inspector_password_hash, inspection_date, status, notes)
             VALUES (?,?,?,?,?,?,?,?)"
        );
        $ins1->execute([$w1, null, 'Inspector Demo A', null, null, $lastWeek, 'failed', 'Demo failed inspection']);
        $failedInspectionId = (int) $pdo->lastInsertId();

        $ins2 = $pdo->prepare(
            "INSERT INTO gov_inspections (worker_id, agency_id, inspector_name, inspector_identity, inspector_password_hash, inspection_date, status, notes)
             VALUES (?,?,?,?,?,?,?,?)"
        );
        $ins2->execute([$w2, null, 'Inspector Demo B', null, null, $today, 'passed', 'Demo passed inspection']);

        $vio = $pdo->prepare(
            "INSERT INTO gov_violations (worker_id, agency_id, inspection_id, type, severity, description, action_taken)
             VALUES (?,?,?,?,?,?,?)"
        );
        $vio->execute([$w1, null, $failedInspectionId, 'Safety breach', 'high', 'Demo violation for government tab', 'Warning notice issued']);

        $pdo->prepare(
            "UPDATE gov_blacklist
             SET status = 'removed'
             WHERE entity_type = 'worker' AND entity_id IN (?,?) AND status = 'active'"
        )->execute([$w1, $w2]);

        $bl = $pdo->prepare(
            "INSERT INTO gov_blacklist (entity_type, entity_id, reason, status)
             VALUES ('worker', ?, ?, 'active')"
        );
        $bl->execute([$w1, 'Demo blacklist for testing deployment guard']);

        $tr = $pdo->prepare(
            "INSERT INTO gov_worker_tracking (worker_id, last_checkin, location_text, status)
             VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE
                last_checkin = VALUES(last_checkin),
                location_text = VALUES(location_text),
                status = VALUES(status)"
        );
        $tr->execute([$w1, $nowDateTime, $country . ' / Demo City', 'alert']);
        $tr->execute([$w2, $nowDateTime, $country . ' / Demo City', 'safe']);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    gov_json_out([
        'success' => true,
        'message' => 'Indonesia demo records inserted (real workers only)',
        'data' => ['workers_used' => [$w1, $w2], 'synthetic_workers' => false],
    ], 201);
}

gov_json_out(['success' => false, 'message' => 'Unknown action'], 404);
