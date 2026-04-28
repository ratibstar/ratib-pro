<?php
/**
 * EN: Handles API endpoint/business logic in `api/agents/stats.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/agents/stats.php`.
 */
// Start output buffering to prevent any accidental output
ob_start();

// Disable error display but keep error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set content type to JSON
header('Content-Type: application/json; charset=UTF-8');

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Fatal error: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']
        ]);
        exit;
    }
});

try {
require_once __DIR__ . '/../core/api-permission-helper.php';
enforceApiPermission('agents', 'stats');
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Permission check failed: ' . $e->getMessage()
    ]);
    exit;
} catch (Error $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Permission check fatal error: ' . $e->getMessage()
    ]);
    exit;
}

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/ApiResponse.php';

try {
    $db = Database::getInstance();
    $stats = $db->getAgentStats();
    ob_clean();
    echo ApiResponse::success($stats);
    exit;
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo ApiResponse::error($e->getMessage(), 500);
    exit;
} catch (Error $e) {
    ob_clean();
    http_response_code(500);
    echo ApiResponse::error("Fatal error: " . $e->getMessage(), 500);
    exit;
} catch (Throwable $e) {
    ob_clean();
    http_response_code(500);
    echo ApiResponse::error("Unexpected error: " . $e->getMessage(), 500);
    exit;
}