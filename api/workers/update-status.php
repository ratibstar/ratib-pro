<?php
/**
 * EN: Handles API endpoint/business logic in `api/workers/update-status.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/workers/update-status.php`.
 */
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../../Utils/response.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['ids']) || !is_array($data['ids']) || empty($data['status'])) {
        throw new Exception('Worker IDs and status are required');

require_once '../../includes/permission_middleware.php';

// Check if user has permission to access this endpoint
checkApiPermission('workers_edit');


    }

    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Validate status
    $validStatuses = ['active', 'inactive'];
    if (!in_array($data['status'], $validStatuses)) {
        throw new Exception('Invalid status value');
    }

    // Convert to integers and validate
    $ids = array_map('intval', $data['ids']);
    $ids = array_filter($ids);
    
    if (empty($ids)) {
        throw new Exception('No valid worker IDs provided');
    }

    // Get old data for history (before update)
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    $fetchSql = "SELECT * FROM workers WHERE id IN ($placeholders)";
    $fetchStmt = $conn->prepare($fetchSql);
    $fetchStmt->execute($ids);
    $oldWorkers = $fetchStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Update worker status
    $sql = "UPDATE workers SET status = ? WHERE id IN ($placeholders)";
    
    $params = array_merge([$data['status']], $ids);
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    $affected = $stmt->rowCount();
    
    // Log history for each updated worker
    $helperPath = __DIR__ . '/../core/global-history-helper.php';
    if (file_exists($helperPath)) {
        require_once $helperPath;
        if (function_exists('logGlobalHistory')) {
            foreach ($ids as $workerId) {
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

    sendResponse([
        'success' => true,
        'message' => "$affected worker status(es) updated successfully",
        'data' => [
            'updated_count' => $affected,
            'status' => $data['status']
        ]
    ]);

} catch (Exception $e) {
    sendResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
}
?> 