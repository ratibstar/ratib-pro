<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control-permissions.php';
require_once dirname(__DIR__, 3) . '/api/core/Database.php';
require_once dirname(__DIR__, 3) . '/api/core/ensure-worker-tracking-schema.php';
require_once dirname(__DIR__, 3) . '/admin/core/EventBus.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

function onboard_json(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function onboard_base64url_encode(string $raw): string
{
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

function onboard_normalize_worker_id($raw): int
{
    $v = strtoupper(trim((string) $raw));
    if ($v === '') {
        return 0;
    }
    if (preg_match('/^W0*([1-9][0-9]*)$/', $v, $m)) {
        return (int) $m[1];
    }
    if (preg_match('/^[0-9]+$/', $v)) {
        return (int) $v;
    }
    return 0;
}

function onboard_worker_formatted_code($raw): string
{
    $v = strtoupper(trim((string) $raw));
    if ($v === '') {
        return '';
    }
    if (preg_match('/^W0*([1-9][0-9]*)$/', $v, $m)) {
        return 'W' . str_pad((string) ((int) $m[1]), 4, '0', STR_PAD_LEFT);
    }
    if (preg_match('/^[0-9]+$/', $v)) {
        return 'W' . str_pad((string) ((int) $v), 4, '0', STR_PAD_LEFT);
    }
    return $v;
}

/**
 * Try resolve tenant by finding worker_id in active agency DBs.
 * Returns ['tenant_id' => int, 'agency_id' => int, 'db_name' => string] or null.
 */
function onboard_resolve_tenant_by_worker(PDO $controlPdo, int $workerId, string $workerCode): ?array
{
    $st = $controlPdo->prepare(
        "SELECT id, tenant_id, db_host, db_port, db_user, db_pass, db_name
         FROM control_agencies
         WHERE is_active = 1
           AND tenant_id IS NOT NULL
           AND tenant_id > 0
           AND db_name IS NOT NULL
           AND db_name <> ''
         ORDER BY id ASC
         LIMIT 300"
    );
    $st->execute();
    $agencies = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($agencies as $a) {
        $dbHost = trim((string) ($a['db_host'] ?? ''));
        $dbPort = (int) ($a['db_port'] ?? 3306);
        $dbUser = (string) ($a['db_user'] ?? '');
        $dbPass = (string) ($a['db_pass'] ?? '');
        $dbName = trim((string) ($a['db_name'] ?? ''));
        if ($dbName === '') {
            continue;
        }
        if ($dbHost === '') {
            $dbHost = defined('DB_HOST') ? DB_HOST : 'localhost';
        }
        try {
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbHost, $dbPort > 0 ? $dbPort : 3306, $dbName),
                $dbUser !== '' ? $dbUser : (defined('DB_USER') ? DB_USER : ''),
                $dbPass !== '' ? $dbPass : (defined('DB_PASS') ? DB_PASS : ''),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 2,
                ]
            );
            $w = $pdo->prepare(
                "SELECT id FROM workers
                 WHERE status != 'deleted'
                   AND (id = :wid OR formatted_id = :fcode OR CAST(id AS CHAR) = :idstr)
                 LIMIT 1"
            );
            $w->bindValue(':wid', $workerId, PDO::PARAM_INT);
            $w->bindValue(':fcode', $workerCode);
            $w->bindValue(':idstr', (string) $workerId);
            $w->execute();
            $found = $w->fetch(PDO::FETCH_ASSOC);
            if ($found) {
                return [
                    'tenant_id' => (int) ($a['tenant_id'] ?? 0),
                    'agency_id' => (int) ($a['id'] ?? 0),
                    'db_name' => $dbName,
                    'worker_id' => (int) ($found['id'] ?? 0),
                ];
            }
        } catch (Throwable $e) {
            continue;
        }
    }
    return null;
}

/**
 * Resolve fallback tenant from control session country / active agencies.
 */
function onboard_resolve_tenant_fallback(PDO $controlPdo): int
{
    $countryId = isset($_SESSION['control_country_id']) ? (int) $_SESSION['control_country_id'] : 0;
    if ($countryId > 0) {
        $st = $controlPdo->prepare(
            "SELECT tenant_id
             FROM control_agencies
             WHERE is_active = 1
               AND country_id = ?
               AND tenant_id IS NOT NULL
               AND tenant_id > 0
             ORDER BY id ASC
             LIMIT 1"
        );
        $st->execute([$countryId]);
        $tid = (int) ($st->fetchColumn() ?: 0);
        if ($tid > 0) {
            return $tid;
        }
    }
    $st2 = $controlPdo->query(
        "SELECT tenant_id
         FROM control_agencies
         WHERE is_active = 1
           AND tenant_id IS NOT NULL
           AND tenant_id > 0
         ORDER BY id ASC
         LIMIT 1"
    );
    if ($st2 === false) {
        return 0;
    }
    $v = $st2->fetchColumn();
    return (int) ($v !== false ? $v : 0);
}

/**
 * Check worker existence in currently active app DB when available.
 */
function onboard_worker_exists_current_db(int $workerId, string $workerCode): ?int
{
    // Primary fallback for production control-panel mode: shared mysqli app connection.
    $mysqli = $GLOBALS['conn'] ?? null;
    if ($mysqli instanceof mysqli) {
        try {
                $st = $mysqli->prepare(
                    "SELECT id FROM workers
                     WHERE status != 'deleted'
                       AND (id = ? OR formatted_id = ? OR CAST(id AS CHAR) = ?)
                     LIMIT 1"
                );
            if ($st) {
                    $idStr = (string) $workerId;
                    $st->bind_param('iss', $workerId, $workerCode, $idStr);
                $st->execute();
                $row = $st->get_result()->fetch_assoc();
                $st->close();
                if (!empty($row['id'])) {
                        return (int) $row['id'];
                }
            }
        } catch (Throwable $e) {
            // continue to PDO fallback
        }
    }

    try {
        if (!class_exists('Database') || !method_exists('Database', 'getInstance')) {
            return null;
        }
        $appPdo = Database::getInstance()->getConnection();
        if (!$appPdo instanceof PDO) {
            return null;
        }
        $w = $appPdo->prepare(
            "SELECT id FROM workers
             WHERE status != 'deleted'
               AND (id = :wid OR formatted_id = :fcode OR CAST(id AS CHAR) = :idstr)
             LIMIT 1"
        );
        $w->bindValue(':wid', $workerId, PDO::PARAM_INT);
        $w->bindValue(':fcode', $workerCode);
        $w->bindValue(':idstr', (string) $workerId);
        $w->execute();
        $row = $w->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) ($row['id'] ?? 0) : null;
    } catch (Throwable $e) {
        return null;
    }
}

function onboard_resolve_worker_id_by_text_current_db(string $text): ?int
{
    $q = trim((string) $text);
    if ($q === '') {
        return null;
    }
    $like = '%' . $q . '%';
    $mysqli = $GLOBALS['conn'] ?? null;
    if ($mysqli instanceof mysqli) {
        try {
            $st = $mysqli->prepare(
                "SELECT id
                 FROM workers
                 WHERE status != 'deleted'
                   AND (worker_name LIKE ? OR formatted_id LIKE ? OR CAST(id AS CHAR) = ?)
                 ORDER BY id DESC
                 LIMIT 1"
            );
            if ($st) {
                $st->bind_param('sss', $like, $like, $q);
                $st->execute();
                $row = $st->get_result()->fetch_assoc();
                $st->close();
                if (!empty($row['id'])) {
                    return (int) $row['id'];
                }
            }
        } catch (Throwable $e) {
            // Continue to PDO fallback.
        }
    }
    try {
        if (!class_exists('Database') || !method_exists('Database', 'getInstance')) {
            return null;
        }
        $appPdo = Database::getInstance()->getConnection();
        if (!$appPdo instanceof PDO) {
            return null;
        }
        $st = $appPdo->prepare(
            "SELECT id
             FROM workers
             WHERE status != 'deleted'
               AND (worker_name LIKE :like OR formatted_id LIKE :like OR CAST(id AS CHAR) = :idstr)
             ORDER BY id DESC
             LIMIT 1"
        );
        $st->bindValue(':like', $like);
        $st->bindValue(':idstr', $q);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) ($row['id'] ?? 0) : null;
    } catch (Throwable $e) {
        return null;
    }
}

function onboard_resolve_tenant_and_worker_by_text(PDO $controlPdo, string $text): ?array
{
    $q = trim((string) $text);
    if ($q === '') {
        return null;
    }
    $like = '%' . $q . '%';
    $st = $controlPdo->prepare(
        "SELECT id, tenant_id, db_host, db_port, db_user, db_pass, db_name
         FROM control_agencies
         WHERE is_active = 1
           AND tenant_id IS NOT NULL
           AND tenant_id > 0
           AND db_name IS NOT NULL
           AND db_name <> ''
         ORDER BY id ASC
         LIMIT 300"
    );
    $st->execute();
    $agencies = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($agencies as $a) {
        $dbHost = trim((string) ($a['db_host'] ?? ''));
        $dbPort = (int) ($a['db_port'] ?? 3306);
        $dbUser = (string) ($a['db_user'] ?? '');
        $dbPass = (string) ($a['db_pass'] ?? '');
        $dbName = trim((string) ($a['db_name'] ?? ''));
        if ($dbName === '') {
            continue;
        }
        if ($dbHost === '') {
            $dbHost = defined('DB_HOST') ? DB_HOST : 'localhost';
        }
        try {
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbHost, $dbPort > 0 ? $dbPort : 3306, $dbName),
                $dbUser !== '' ? $dbUser : (defined('DB_USER') ? DB_USER : ''),
                $dbPass !== '' ? $dbPass : (defined('DB_PASS') ? DB_PASS : ''),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 2,
                ]
            );
            $w = $pdo->prepare(
                "SELECT id
                 FROM workers
                 WHERE status != 'deleted'
                   AND (worker_name LIKE :like OR formatted_id LIKE :like OR CAST(id AS CHAR) = :idstr)
                 ORDER BY id DESC
                 LIMIT 1"
            );
            $w->bindValue(':like', $like);
            $w->bindValue(':idstr', $q);
            $w->execute();
            $found = $w->fetch(PDO::FETCH_ASSOC);
            if ($found) {
                return [
                    'tenant_id' => (int) ($a['tenant_id'] ?? 0),
                    'agency_id' => (int) ($a['id'] ?? 0),
                    'db_name' => $dbName,
                    'worker_id' => (int) ($found['id'] ?? 0),
                ];
            }
        } catch (Throwable $e) {
            continue;
        }
    }
    return null;
}

if (empty($_SESSION['control_logged_in'])) {
    onboard_json(['success' => false, 'message' => 'Unauthorized'], 401);
}
if (!hasControlPermission(CONTROL_PERM_GOVERNMENT)
    && !hasControlPermission('manage_control_government')
    && !hasControlPermission('gov_admin')
    && !hasControlPermission(CONTROL_PERM_ADMINS)
) {
    onboard_json(['success' => false, 'message' => 'Access denied'], 403);
}

try {
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        onboard_json(['success' => false, 'message' => 'POST required'], 405);
    }
    $payload = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        onboard_json(['success' => false, 'message' => 'Invalid JSON body'], 422);
    }

    $workerRaw = (string) ($payload['worker_id'] ?? '');
    $workerId = onboard_normalize_worker_id($workerRaw);
    $workerCode = onboard_worker_formatted_code($workerRaw);

    $tenantId = isset($payload['tenant_id']) ? (int) $payload['tenant_id'] : 0;
    $controlPdo = getControlDB();
    if ($tenantId <= 0) {
        $agencyId = isset($_SESSION['control_agency_id']) ? (int) $_SESSION['control_agency_id'] : 0;
        $ctrl = $GLOBALS['control_conn'] ?? null;
        if ($agencyId > 0 && $ctrl instanceof mysqli) {
            $st = $ctrl->prepare("SELECT tenant_id FROM control_agencies WHERE id = ? LIMIT 1");
            $st->bind_param('i', $agencyId);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            $tenantId = (int) ($row['tenant_id'] ?? 0);
        }
    }
    if ($workerId > 0 && $tenantId <= 0) {
        $auto = onboard_resolve_tenant_by_worker($controlPdo, $workerId, $workerCode);
        if (is_array($auto) && (int) ($auto['tenant_id'] ?? 0) > 0) {
            $tenantId = (int) $auto['tenant_id'];
            if (!empty($auto['worker_id'])) {
                $workerId = (int) $auto['worker_id'];
            }
        }
    }
    if ($workerId <= 0) {
        $resolvedCurrentByText = onboard_resolve_worker_id_by_text_current_db($workerRaw);
        if ($resolvedCurrentByText !== null && $resolvedCurrentByText > 0) {
            $workerId = (int) $resolvedCurrentByText;
            $workerCode = onboard_worker_formatted_code((string) $workerId);
        } else {
            $autoByText = onboard_resolve_tenant_and_worker_by_text($controlPdo, $workerRaw);
            if (is_array($autoByText) && (int) ($autoByText['worker_id'] ?? 0) > 0) {
                $workerId = (int) $autoByText['worker_id'];
                $workerCode = onboard_worker_formatted_code((string) $workerId);
                if ($tenantId <= 0 && (int) ($autoByText['tenant_id'] ?? 0) > 0) {
                    $tenantId = (int) $autoByText['tenant_id'];
                }
            }
        }
    }
    if ($workerId <= 0) {
        onboard_json(['success' => false, 'message' => 'worker_id required (accepts ID, code like W0002, or worker name)'], 422);
    }
    if ($tenantId <= 0) {
        $tenantId = onboard_resolve_tenant_fallback($controlPdo);
    }
    if ($tenantId <= 0) {
        onboard_json(['success' => false, 'message' => 'tenant_id unresolved. Please select/open an agency, or provide tenant_id manually.'], 422);
    }

    $resolvedCurrent = onboard_worker_exists_current_db($workerId, $workerCode);
    $workerFound = $resolvedCurrent !== null && $resolvedCurrent > 0;
    if ($workerFound) {
        $workerId = (int) $resolvedCurrent;
    } else {
        $autoCheck = onboard_resolve_tenant_by_worker($controlPdo, $workerId, $workerCode);
        $workerFound = is_array($autoCheck) && (int) ($autoCheck['tenant_id'] ?? 0) > 0;
        if ($workerFound && !empty($autoCheck['worker_id'])) {
            $workerId = (int) $autoCheck['worker_id'];
        }
    }
    if (!$workerFound) {
        onboard_json(['success' => false, 'message' => 'Worker not found for this tenant/agency. Use a real worker ID/code (e.g. W0001) from active workers list.'], 404);
    }

    $deviceId = trim((string) ($payload['device_id'] ?? ''));
    $identity = trim((string) ($payload['identity'] ?? ''));
    $password = trim((string) ($payload['password'] ?? ''));
    $passwordHash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;
    if ($deviceId === '') {
        $deviceId = 'dev-' . bin2hex(random_bytes(8));
    }
    $token = trim((string) ($payload['api_token'] ?? ''));
    if ($token === '') {
        $token = bin2hex(random_bytes(24));
    }

    ratibEnsureWorkerTrackingSchema($controlPdo);
    $st2 = $controlPdo->prepare(
        "INSERT INTO worker_tracking_devices
         (worker_id, tenant_id, device_id, worker_identity, worker_password_hash, api_token, is_active, last_seen, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            worker_identity = VALUES(worker_identity),
            worker_password_hash = COALESCE(VALUES(worker_password_hash), worker_password_hash),
            api_token = VALUES(api_token),
            is_active = 1,
            updated_at = NOW()"
    );
    $st2->execute([
        $workerId,
        $tenantId,
        $deviceId,
        $identity !== '' ? $identity : null,
        $passwordHash,
        $token,
    ]);

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $apiUrl = $scheme . '://' . $host . '/api/worker-tracking';
    $mobileOnboardPayload = [
        'api_url' => $apiUrl,
        'worker_id' => $workerId,
        'tenant_id' => $tenantId,
        'device_id' => $deviceId,
        'api_token' => $token,
    ];
    $onboardEncoded = onboard_base64url_encode((string) json_encode($mobileOnboardPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $onboardingUrl = $scheme . '://' . $host . '/mobile-app/index.php?onboard=' . rawurlencode($onboardEncoded);

    emitEvent('WORKER_DEVICE_ONBOARDED', 'info', 'Tracking device provisioned', [
        'worker_id' => $workerId,
        'tenant_id' => $tenantId,
        'device_id' => $deviceId,
        'source' => 'worker_tracking',
        'request_id' => getRequestId(),
    ]);

    onboard_json([
        'success' => true,
        'data' => [
            'api_url' => $apiUrl,
            'worker_id' => $workerId,
            'tenant_id' => $tenantId,
            'device_id' => $deviceId,
            'api_token' => $token,
            'onboarding_url' => $onboardingUrl,
        ],
    ]);
} catch (Throwable $e) {
    onboard_json(['success' => false, 'message' => $e->getMessage()], 500);
}
