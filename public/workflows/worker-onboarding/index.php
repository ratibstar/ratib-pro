<?php
declare(strict_types=1);

/**
 * UPLOAD THIS FILE to: public/workflows/worker-onboarding/index.php
 * Self-contained Global AI workflow — NO App\Core\Autoloader (build: upload-v6).
 */
header('Content-Type: application/json; charset=utf-8');
header('X-Ratib-Workflow-Build: upload-v6');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed', 'build' => 'upload-v6']);
    exit;
}

try {
    $dir = realpath(__DIR__) ?: __DIR__;
    $root = null;
    for ($i = 0; $i < 14; $i++) {
        if (is_file($dir . '/includes/config.php')) {
            $root = $dir;
            break;
        }
        $parent = dirname($dir);
        if ($parent === $dir) {
            break;
        }
        $dir = $parent;
    }
    if ($root === null) {
        throw new RuntimeException('Could not find includes/config.php');
    }

    $apiSession = $root . '/api/core/ratib_api_session.inc.php';
    if (is_file($apiSession)) {
        require_once $apiSession;
        if (function_exists('ratib_api_pick_session_name')) {
            ratib_api_pick_session_name();
        }
    }
    require_once $root . '/includes/config.php';

    $authed = !empty($_SESSION['logged_in']) && (int) ($_SESSION['user_id'] ?? 0) > 0;
    if (!$authed) {
        $cp = $root . '/control-panel/includes/control-permissions.php';
        if (is_file($cp)) {
            require_once $cp;
        }
        if (!empty($_SESSION['control_logged_in']) && function_exists('hasControlPermission')) {
            $authed = hasControlPermission(CONTROL_PERM_GOVERNMENT)
                || hasControlPermission('manage_control_government')
                || hasControlPermission('gov_admin')
                || hasControlPermission(CONTROL_PERM_ADMINS);
        }
    }
    if (!$authed) {
        throw new RuntimeException('Authentication required', 401);
    }

    $payload = json_decode((string) file_get_contents('php://input'), true) ?? [];
    if (!is_array($payload)) {
        $payload = [];
    }
    $worker = is_array($payload['worker'] ?? null) ? $payload['worker'] : [];
    $name = trim((string) ($worker['name'] ?? $worker['worker_name'] ?? $worker['full_name'] ?? ''));
    $passport = trim((string) ($worker['passport_number'] ?? ''));
    $workerId = (int) ($payload['worker_id'] ?? $worker['worker_id'] ?? $worker['id'] ?? 0);
    if ($name === '' || $passport === '' || $workerId <= 0) {
        throw new InvalidArgumentException('worker_id, worker.name and worker.passport_number are required.');
    }

    $agencyDb = $GLOBALS['agency_db'] ?? null;
    if (is_array($agencyDb) && !empty($agencyDb['db'])) {
        $host = (string) ($agencyDb['host'] ?? 'localhost');
        $port = (int) ($agencyDb['port'] ?? 3306);
        $dbname = (string) $agencyDb['db'];
        $user = (string) ($agencyDb['user'] ?? (defined('DB_USER') ? DB_USER : ''));
        $pass = (string) ($agencyDb['pass'] ?? (defined('DB_PASS') ? DB_PASS : ''));
    } else {
        $host = defined('DB_HOST') ? (string) DB_HOST : 'localhost';
        $port = defined('DB_PORT') ? (int) DB_PORT : 3306;
        $dbname = defined('DB_NAME') ? (string) DB_NAME : '';
        $user = defined('DB_USER') ? (string) DB_USER : '';
        $pass = defined('DB_PASS') ? (string) DB_PASS : '';
    }
    $agencyPdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port > 0 ? $port : 3306, $dbname),
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    $chk = $agencyPdo->prepare("SELECT id FROM workers WHERE id = :id AND COALESCE(status,'') <> 'deleted' LIMIT 1");
    $chk->execute([':id' => $workerId]);
    if (!$chk->fetch()) {
        throw new RuntimeException('Worker not found', 404);
    }

    $agencyPdo->exec(
        "CREATE TABLE IF NOT EXISTS workflows (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL,
            context_json LONGTEXT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'completed',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $ctx = json_encode([
        'worker_id' => $workerId,
        'worker' => ['name' => $name, 'passport_number' => $passport],
        'onboarding_source' => 'upload_v6_index',
    ], JSON_UNESCAPED_UNICODE);
    $ins = $agencyPdo->prepare('INSERT INTO workflows (name, context_json, status, created_at, updated_at) VALUES (:n,:c,:s,NOW(),NOW())');
    $ins->execute([':n' => 'WorkerOnboardingWorkflow', ':c' => $ctx, ':s' => 'completed']);
    $workflowId = (int) $agencyPdo->lastInsertId();

    $tenantId = 0;
    $deviceId = '';
    $cpDb = defined('CONTROL_PANEL_DB_NAME') ? (string) CONTROL_PANEL_DB_NAME : '';
    if ($cpDb !== '') {
        $controlPdo = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port > 0 ? $port : 3306, $cpDb),
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $agencyId = (int) ($_SESSION['agency_id'] ?? $_SESSION['control_agency_id'] ?? 0);
        if ($agencyId > 0) {
            $st = $controlPdo->prepare('SELECT tenant_id FROM control_agencies WHERE id = ? LIMIT 1');
            $st->execute([$agencyId]);
            $tenantId = (int) ($st->fetchColumn() ?: 0);
        }
        if ($tenantId <= 0) {
            $st = $controlPdo->query(
                "SELECT tenant_id, db_name FROM control_agencies WHERE is_active=1 AND tenant_id>0 AND db_name<>'' LIMIT 300"
            );
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                try {
                    $ap = new PDO(
                        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $row['db_name']),
                        $user,
                        $pass,
                        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                    );
                    $w = $ap->prepare('SELECT id FROM workers WHERE id = ? LIMIT 1');
                    $w->execute([$workerId]);
                    if ($w->fetch()) {
                        $tenantId = (int) $row['tenant_id'];
                        break;
                    }
                } catch (Throwable $e) {
                    continue;
                }
            }
        }
        if ($tenantId > 0) {
            $controlPdo->exec(
                "CREATE TABLE IF NOT EXISTS worker_tracking_devices (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    worker_id INT NOT NULL,
                    tenant_id INT NOT NULL,
                    device_id VARCHAR(64) NOT NULL,
                    api_token VARCHAR(255) NOT NULL,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    last_seen DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_device (worker_id, tenant_id, device_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            $deviceId = 'dev-' . bin2hex(random_bytes(8));
            $token = bin2hex(random_bytes(24));
            $st2 = $controlPdo->prepare(
                'INSERT INTO worker_tracking_devices (worker_id, tenant_id, device_id, api_token, is_active, last_seen, created_at, updated_at)
                 VALUES (?,?,?,?,1,NOW(),NOW(),NOW())
                 ON DUPLICATE KEY UPDATE api_token=VALUES(api_token), is_active=1, updated_at=NOW()'
            );
            $st2->execute([$workerId, $tenantId, $deviceId, $token]);
        }
    }

    echo json_encode([
        'success' => true,
        'workflow_id' => (string) $workflowId,
        'worker_id' => $workerId,
        'tenant_id' => $tenantId > 0 ? (string) $tenantId : '',
        'device_id' => $deviceId,
        'tracking_ok' => $tenantId > 0,
        'workflow_ok' => true,
        'build' => 'upload-v6',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    $code = (int) $e->getCode();
    if (!in_array($code, [401, 403, 404], true)) {
        $code = 422;
    }
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'build' => 'upload-v6',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
