<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    echo json_encode(['ok' => true, 'build' => 'global-ai-run-20260516-v5']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/core/api-permission-helper.php';
require_once __DIR__ . '/../control-panel/includes/control-permissions.php';

try {
    try {
        enforceApiPermission('workers', 'get');
    } catch (Throwable $authError) {
        $hasControlAccess = !empty($_SESSION['control_logged_in'])
            && (
                hasControlPermission(CONTROL_PERM_GOVERNMENT)
                || hasControlPermission('manage_control_government')
                || hasControlPermission('gov_admin')
                || hasControlPermission(CONTROL_PERM_ADMINS)
            );
        if (!$hasControlAccess) {
            throw $authError;
        }
    }

    $payload = json_decode((string) file_get_contents('php://input'), true) ?? [];
    if (!is_array($payload)) {
        $payload = [];
    }

    require_once dirname(__DIR__) . '/includes/global_ai_run.php';
    $result = ratib_global_ai_run($payload);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    $code = (int) $e->getCode();
    if (!in_array($code, [401, 403, 404], true)) {
        $code = 422;
    }
    if ($e instanceof PDOException) {
        $code = 503;
    }
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'handler' => 'global_ai_run',
        'build' => 'global-ai-run-20260516-v5',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
