<?php
/**
 * EN: Handles API endpoint/business logic in `api/workers/bulk-deactivate.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/workers/bulk-deactivate.php`.
 */
require_once __DIR__ . '/../../includes/config.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0); // Production: don't display errors
ini_set('log_errors', 1);

try {
    // Get JSON data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['worker_ids']) || !is_array($input['worker_ids'])) {
        throw new Exception('Invalid input data');
    }
    
    // Convert to integers and filter
    $workerIds = array_map('intval', $input['worker_ids']);
    $workerIds = array_filter($workerIds);
    
    if (empty($workerIds)) {
        throw new Exception('No valid worker IDs');
    }
    
    // Database connection using config constants
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create placeholders
    $placeholders = str_repeat('?,', count($workerIds) - 1) . '?';
    
    // Simple update query - use correct enum value
    $sql = "UPDATE workers SET status = 'inactive' WHERE id IN ($placeholders)";
    
    error_log("BULK DEACTIVATE - SQL: $sql");
    error_log("BULK DEACTIVATE - IDs: " . implode(',', $workerIds));
    
    // Get old data for history (before update)
    $oldData = [];
    $fetchSql = "SELECT * FROM workers WHERE id IN ($placeholders)";
    $fetchStmt = $pdo->prepare($fetchSql);
    $fetchStmt->execute($workerIds);
    $oldData = $fetchStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($workerIds);
    
    $affectedRows = $stmt->rowCount();
    
    error_log("BULK DEACTIVATE - Affected rows: $affectedRows");
    
    // Get updated data for history
    $newData = [];
    $fetchStmt = $pdo->prepare($fetchSql);
    $fetchStmt->execute($workerIds);
    $newData = $fetchStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Verify the update
    $verifySql = "SELECT id, status FROM workers WHERE id IN ($placeholders)";
    $verifyStmt = $pdo->prepare($verifySql);
    $verifyStmt->execute($workerIds);
    $verifyResults = $verifyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("BULK DEACTIVATE - Verification: " . json_encode($verifyResults));
    
    // Log history for each worker
    $helperPath = __DIR__ . '/../core/global-history-helper.php';
    if (file_exists($helperPath)) {
        require_once $helperPath;
        if (function_exists('logGlobalHistory')) {
            foreach ($workerIds as $workerId) {
                $oldRecord = null;
                $newRecord = null;
                foreach ($oldData as $record) {
                    if ($record['id'] == $workerId) {
                        $oldRecord = $record;
                        break;
                    }
                }
                foreach ($newData as $record) {
                    if ($record['id'] == $workerId) {
                        $newRecord = $record;
                        break;
                    }
                }
                if ($oldRecord && $newRecord) {
                    @logGlobalHistory('workers', $workerId, 'update', 'workers', $oldRecord, $newRecord);
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Deactivated $affectedRows workers",
        'affected_rows' => $affectedRows,
        'verified' => $verifyResults
    ]);
    
} catch (Exception $e) {
    error_log("BULK DEACTIVATE ERROR: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>