<?php
/**
 * EN: Handles API endpoint/business logic in `api/workers/bulk-pending.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/workers/bulk-pending.php`.
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
    
    // Get old data for history (before update)
    $fetchSql = "SELECT * FROM workers WHERE id IN ($placeholders)";
    $fetchStmt = $pdo->prepare($fetchSql);
    $fetchStmt->execute($workerIds);
    $oldWorkers = $fetchStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Update query - set status to pending
    $sql = "UPDATE workers SET status = 'pending' WHERE id IN ($placeholders)";
    
    error_log("BULK PENDING - SQL: $sql");
    error_log("BULK PENDING - IDs: " . implode(',', $workerIds));
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($workerIds);
    
    $affectedRows = $stmt->rowCount();
    
    error_log("BULK PENDING - Affected rows: $affectedRows");
    
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
                
                $newStmt = $pdo->prepare("SELECT * FROM workers WHERE id = ?");
                $newStmt->execute([$workerId]);
                $newWorker = $newStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($oldWorker && $newWorker) {
                    @logGlobalHistory('workers', $workerId, 'update', 'workers', $oldWorker, $newWorker);
                }
            }
        }
    }
    
    // Verify the update
    $verifySql = "SELECT id, status FROM workers WHERE id IN ($placeholders)";
    $verifyStmt = $pdo->prepare($verifySql);
    $verifyStmt->execute($workerIds);
    $verifyResults = $verifyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("BULK PENDING - Verification: " . json_encode($verifyResults));
    
    echo json_encode([
        'success' => true,
        'message' => "Set $affectedRows workers to pending",
        'affected_rows' => $affectedRows,
        'verified' => $verifyResults
    ]);
    
} catch (Exception $e) {
    error_log("BULK PENDING ERROR: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
