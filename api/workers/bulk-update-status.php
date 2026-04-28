<?php
/**
 * EN: Handles API endpoint/business logic in `api/workers/bulk-update-status.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/workers/bulk-update-status.php`.
 */
require_once __DIR__ . '/../core/Database.php';
require_once '../../utils/response.php';
require_once __DIR__ . '/../core/api-permission-helper.php';

// Enforce permission for bulk updating workers
enforceApiPermission('workers', 'bulk-update');

try {
    // Get JSON data from request
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($data['worker_ids']) || !is_array($data['worker_ids']) || 
        empty($data['status'])) {
        throw new Exception('Worker IDs and status are required');
    }

    // Validate status
    $validStatuses = ['active', 'inactive'];
    if (!in_array($data['status'], $validStatuses)) {
        throw new Exception('Invalid status value');
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
    
    // Prepare and execute update query
    $sql = "UPDATE workers SET status = ?, updated_at = NOW() WHERE id IN ($placeholders)";
    
    // Prepare parameters (status first, then worker IDs)
    $params = array_merge([$data['status']], $workerIds);
    
    // Get old data for history (before update)
    $fetchPlaceholders = str_repeat('?,', count($workerIds) - 1) . '?';
    $fetchSql = "SELECT * FROM workers WHERE id IN ($fetchPlaceholders)";
    $fetchStmt = $conn->prepare($fetchSql);
    $fetchStmt->execute($workerIds);
    $oldWorkers = $fetchStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    $affected = $stmt->rowCount();
    
    // Log history for each updated worker
    $helperPath = __DIR__ . '/../core/global-history-helper.php';
    if (file_exists($helperPath)) {
        require_once $helperPath;
        if (function_exists('logGlobalHistory')) {
            foreach ($workerIds as $workerId) {
                $oldWorker = null;
                foreach ($oldWorkers as $worker) {
                    if ($worker['id'] == $workerId) {
                        $oldWorker = $worker;
                        break;
                    }
                }
                
                $newStmt = $conn->prepare("SELECT * FROM workers WHERE id = ?");
                $newStmt->execute([$workerId]);
                $newWorker = $newStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($oldWorker && $newWorker) {
                    @logGlobalHistory('workers', $workerId, 'update', 'workers', $oldWorker, $newWorker);
                }
            }
        }
    }

    // Return success response
    sendSuccessResponse([
        'updated_count' => $affected,
        'status' => $data['status']
    ], "$affected worker status(es) updated successfully");

} catch (Exception $e) {
    error_log('Bulk update status error: ' . $e->getMessage());
    sendErrorResponse($e->getMessage(), 500);
}
?>