<?php
/**
 * EN: Handles API endpoint/business logic in `api/workers/delete.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/workers/delete.php`.
 */
// Error reporting (Production: log only, don't display)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Check if required files exist
    if (!file_exists(__DIR__ . '/../core/Database.php')) {
        throw new Exception('Database.php file not found');
    }
    if (!file_exists(__DIR__ . '/../core/ApiResponse.php')) {
        throw new Exception('ApiResponse.php file not found');
    }
    
    require_once __DIR__ . '/../core/Database.php';
    require_once __DIR__ . '/../core/ApiResponse.php';
    require_once __DIR__ . '/../core/api-permission-helper.php';

    // Enforce permission for deleting workers
    enforceApiPermission('workers', 'delete');

    // Get JSON data from request
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['ids']) || !is_array($data['ids'])) {
        throw new Exception('Worker IDs are required');
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Convert to integers and validate
    $ids = array_map('intval', $data['ids']);
    $ids = array_filter($ids);
    
    if (empty($ids)) {
        throw new Exception('No valid worker IDs provided');
    }

    // Get old data for history (before deletion)
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    $fetchSql = "SELECT * FROM workers WHERE id IN ($placeholders)";
    $fetchStmt = $conn->prepare($fetchSql);
    $fetchStmt->execute($ids);
    $deletedWorkers = $fetchStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Simple DELETE - remove workers completely from database
    $sql = "DELETE FROM workers WHERE id IN ($placeholders)";
    
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute($ids);

    if ($result) {
        $affected = $stmt->rowCount();
        
        // Log history for each deleted worker
        $helperPath = __DIR__ . '/../core/global-history-helper.php';
        if (file_exists($helperPath)) {
            require_once $helperPath;
            if (function_exists('logGlobalHistory')) {
                foreach ($deletedWorkers as $deletedWorker) {
                    @logGlobalHistory('workers', $deletedWorker['id'], 'delete', 'workers', $deletedWorker, null);
                }
            }
        }
        
        echo ApiResponse::success([
            'deleted_count' => $affected,
            'deleted_ids' => $ids
        ], "$affected worker(s) deleted successfully");
    } else {
        $errorInfo = $stmt->errorInfo();
        throw new Exception('Failed to delete workers: ' . ($errorInfo[2] ?? 'Unknown database error'));
    }

} catch (Exception $e) {
    error_log("Workers Delete API Error: " . $e->getMessage());
    error_log("Workers Delete API Stack Trace: " . $e->getTraceAsString());
    echo ApiResponse::error($e->getMessage(), 500);
} catch (Error $e) {
    error_log("Workers Delete API Fatal Error: " . $e->getMessage());
    error_log("Workers Delete API Fatal Stack Trace: " . $e->getTraceAsString());
    echo ApiResponse::error("Fatal error: " . $e->getMessage(), 500);
}
