<?php
declare(strict_types=1);

/**
 * Global AI workflow — ONE self-contained file (no App\Core\Autoloader).
 * Upload this file to the server path your site already uses for this URL.
 * Build: standalone-single-file-20260516-v4
 */
header('Content-Type: application/json; charset=utf-8');
header('X-Ratib-Workflow-Build: standalone-single-file-20260516-v4');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed', 'build' => 'standalone-single-file-20260516-v4']);
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
        throw new RuntimeException('Could not locate includes/config.php from ' . __DIR__);
    }

    $apiSession = $root . '/api/core/ratib_api_session.inc.php';
    if (is_file($apiSession)) {
        require_once $apiSession;
        if (function_exists('ratib_api_pick_session_name')) {
            ratib_api_pick_session_name();
        }
    }

    require_once $root . '/includes/config.php';
    $controlPerms = $root . '/control-panel/includes/control-permissions.php';
    if (is_file($controlPerms)) {
        require_once $controlPerms;
    }
    require_once $root . '/api/core/Database.php';

    $authed = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true
        && (int) ($_SESSION['user_id'] ?? 0) > 0;
    if (!$authed && !empty($_SESSION['control_logged_in']) && function_exists('hasControlPermission')) {
        $authed = hasControlPermission(CONTROL_PERM_GOVERNMENT)
            || hasControlPermission('manage_control_government')
            || hasControlPermission('gov_admin')
            || hasControlPermission(CONTROL_PERM_ADMINS);
    }
    if (!$authed) {
        throw new RuntimeException('Authentication required', 401);
    }

    $payload = json_decode((string) file_get_contents('php://input'), true) ?? [];
    if (!is_array($payload)) {
        $payload = [];
    }

    $workerPayload = is_array($payload['worker'] ?? null) ? $payload['worker'] : [];
    $name = trim((string) ($workerPayload['name'] ?? $workerPayload['worker_name'] ?? $workerPayload['full_name'] ?? ''));
    $passport = trim((string) ($workerPayload['passport_number'] ?? ''));
    $workerId = (int) ($payload['worker_id'] ?? $workerPayload['worker_id'] ?? $workerPayload['id'] ?? 0);

    if ($name === '' || $passport === '') {
        throw new InvalidArgumentException('worker.name and worker.passport_number are required.');
    }
    if ($workerId <= 0) {
        throw new InvalidArgumentException('worker_id is required.');
    }

    $pdo = Database::getInstance()->getConnection();
    $chk = $pdo->prepare("SELECT id FROM workers WHERE id = :id AND COALESCE(status, '') <> 'deleted' LIMIT 1");
    $chk->execute([':id' => $workerId]);
    if (!$chk->fetch(PDO::FETCH_ASSOC)) {
        throw new RuntimeException('Worker not found in agency database.', 404);
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS workflows (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL,
            context_json LONGTEXT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'running',
            failed_step VARCHAR(191) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_workflows_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $context = [
        'worker_id' => $workerId,
        'onboarding_source' => 'global_ai_single_file',
        'worker' => array_merge($workerPayload, [
            'worker_id' => $workerId,
            'id' => $workerId,
            'name' => $name,
            'passport_number' => $passport,
        ]),
        'tracking' => $payload['tracking'] ?? null,
        'notify_to' => $payload['notify_to'] ?? null,
    ];

    $ins = $pdo->prepare(
        'INSERT INTO workflows (name, context_json, status, created_at, updated_at)
         VALUES (:name, :context_json, :status, NOW(), NOW())'
    );
    $ins->execute([
        ':name' => 'WorkerOnboardingWorkflow',
        ':context_json' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':status' => 'completed',
    ]);
    $workflowId = (int) $pdo->lastInsertId();
    if ($workflowId <= 0) {
        throw new RuntimeException('Could not record workflow.', 503);
    }

    $context['workflow_id'] = $workflowId;
    $upd = $pdo->prepare(
        'UPDATE workflows SET context_json = :context_json, status = :status, updated_at = NOW() WHERE id = :id'
    );
    $upd->execute([
        ':id' => $workflowId,
        ':context_json' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':status' => 'completed',
    ]);

    echo json_encode([
        'success' => true,
        'workflow_id' => (string) $workflowId,
        'worker_id' => $workerId,
        'existing_worker' => true,
        'build' => 'standalone-single-file-20260516-v4',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    $status = (int) $exception->getCode();
    if (!in_array($status, [401, 403, 404, 429], true)) {
        $status = 422;
    }
    if ($exception instanceof PDOException) {
        $status = 503;
    }
    http_response_code($status);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
        'build' => 'standalone-single-file-20260516-v4',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
