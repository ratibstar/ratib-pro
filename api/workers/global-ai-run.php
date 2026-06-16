<?php
declare(strict_types=1);

/**
 * Standalone Global AI workflow endpoint (upload this file to api/workers/global-ai-run.php).
 * No App\Core\Autoloader — build global-ai-v7.
 */
header('Content-Type: application/json; charset=utf-8');
header('X-RATEB-Global-AI-Build: global-ai-v7');

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed', 'build' => 'global-ai-v7']);
    exit;
}

try {
    $dir = realpath(__DIR__) ?: __DIR__;
    $root = null;
    for ($i = 0; $i < 10; $i++) {
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

    $apiSession = $root . '/api/core/rateb_api_session.inc.php';
    if (is_file($apiSession)) {
        require_once $apiSession;
        if (function_exists('rateb_api_pick_session_name')) {
            rateb_api_pick_session_name();
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

    $coreFile = $root . '/includes/rateb_global_ai_workflow_core.php';
    if (!is_file($coreFile)) {
        throw new RuntimeException('includes/rateb_global_ai_workflow_core.php missing — upload it with this file', 503);
    }
    require_once $coreFile;
    echo json_encode(rateb_global_ai_workflow_core($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    $code = (int) $e->getCode();
    if (!in_array($code, [401, 403, 404], true)) {
        $code = 422;
    }
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'build' => 'global-ai-v7',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
