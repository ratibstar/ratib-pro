<?php
/**
 * EN: Handles API endpoint/business logic in `api/workers/bulk-delete.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/workers/bulk-delete.php`.
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

    // Enforce permission for bulk deleting workers
    enforceApiPermission('workers', 'bulk-delete');

    // Get JSON data from request
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($data['worker_ids']) || !is_array($data['worker_ids'])) {
        throw new Exception('Worker IDs are required');
    }

    // Connect to database
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Clean worker IDs array (ensure they're integers)
    $workerIds = array_map('intval', $data['worker_ids']);
    $workerIds = array_filter($workerIds); // Remove any zero values
    
    if (empty($workerIds)) {
        throw new Exception('No valid worker IDs provided');
    }

    // Create placeholders for prepared statement
    $placeholders = str_repeat('?,', count($workerIds) - 1) . '?';
    
    // Get old data for history (before deletion)
    $fetchPlaceholders = str_repeat('?,', count($workerIds) - 1) . '?';
    $fetchSql = "SELECT * FROM workers WHERE id IN ($fetchPlaceholders)";
    $fetchStmt = $conn->prepare($fetchSql);
    $fetchStmt->execute($workerIds);
    $deletedWorkers = $fetchStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Start transaction
    $conn->beginTransaction();
    
    try {
        // First delete all documents associated with these workers
        $deleteDocumentsSql = "DELETE FROM worker_documents WHERE worker_id IN ($placeholders)";
        $stmt = $conn->prepare($deleteDocumentsSql);
        $stmt->execute($workerIds);
        
        // Then delete the workers
        $deleteWorkersSql = "DELETE FROM workers WHERE id IN ($placeholders)";
        $stmt = $conn->prepare($deleteWorkersSql);
        $stmt->execute($workerIds);
        
        $affected = $stmt->rowCount();
        
        // Commit transaction
        $conn->commit();
        
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
        
        // Return success response
        echo ApiResponse::success([
            'deleted_count' => $affected
        ], "$affected worker(s) deleted successfully");
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log('Bulk delete error: ' . $e->getMessage());
    error_log('Bulk delete stack trace: ' . $e->getTraceAsString());
    echo ApiResponse::error($e->getMessage(), 500);
} catch (Error $e) {
    error_log('Bulk delete fatal error: ' . $e->getMessage());
    error_log('Bulk delete fatal stack trace: ' . $e->getTraceAsString());
    echo ApiResponse::error("Fatal error: " . $e->getMessage(), 500);
}
