<?php
declare(strict_types=1);

/**
 * Global AI workflow without App\Core (fixes Autoloader not found on stale production).
 */
function rateb_worker_onboarding_standalone_run(): void
{
    header('Content-Type: application/json; charset=utf-8');

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    try {
        $dir = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
        $root = null;
        for ($i = 0; $i < 12; $i++) {
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
            throw new RuntimeException('Could not locate includes/config.php');
        }

        $apiSession = $root . '/api/core/rateb_api_session.inc.php';
        if (is_file($apiSession)) {
            require_once $apiSession;
            if (function_exists('rateb_api_pick_session_name')) {
                rateb_api_pick_session_name();
            }
        }

        require_once $root . '/includes/config.php';
        $controlPerms = $root . '/control-panel/includes/control-permissions.php';
        if (is_file($controlPerms)) {
            require_once $controlPerms;
        }
        require_once $root . '/includes/worker_onboarding_workflow_legacy.php';
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
            throw new InvalidArgumentException('worker_id is required for Global AI onboarding.');
        }

        $agencyPdo = Database::getInstance()->getConnection();
        $chk = $agencyPdo->prepare("SELECT id FROM workers WHERE id = :id AND COALESCE(status, '') <> 'deleted' LIMIT 1");
        $chk->execute([':id' => $workerId]);
        if (!$chk->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException('Worker not found in agency database.', 404);
        }

        $payload['worker'] = array_merge($workerPayload, [
            'worker_id' => $workerId,
            'id' => $workerId,
            'name' => $name,
            'passport_number' => $passport,
        ]);

        $workflowId = rateb_global_ai_record_workflow($agencyPdo, $workerId, $payload);
        if ($workflowId === null || $workflowId <= 0) {
            throw new RuntimeException('Could not record workflow.', 503);
        }

        echo json_encode([
            'success' => true,
            'workflow_id' => (string) $workflowId,
            'worker_id' => $workerId,
            'existing_worker' => true,
            'bootstrap' => 'standalone',
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
            'bootstrap' => 'standalone',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
