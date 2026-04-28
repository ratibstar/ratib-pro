<?php
/**
 * EN: Handles API endpoint/business logic in `api/workers/get-single.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/workers/get-single.php`.
 */
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../../Utils/response.php';

try {
    // Get worker ID from request
    $workerId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    if (!$workerId) {
        throw new Exception('Worker ID is required');
    }

    // Create database connection
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Get worker details with agent and subagent names
    $query = "
        SELECT w.*,
               a.agent_name,
               s.subagent_name
        FROM workers w
        LEFT JOIN agents a ON w.agent_id = a.id
        LEFT JOIN subagents s ON w.subagent_id = s.id
        WHERE w.id = :worker_id
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':worker_id', $workerId, PDO::PARAM_INT);
    $stmt->execute();
    
    $worker = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$worker) {
        throw new Exception('Worker not found');
    }

    try {
        require_once __DIR__ . '/../../includes/government-labor.php';
        $worker['government_alerts'] = ratib_government_worker_alerts_pdo($conn, $workerId);
        $worker['government_deploy_blocked'] = ratib_government_deploy_block_reason_pdo($conn, $workerId) !== null;
    } catch (Throwable $e) {
        $worker['government_alerts'] = [];
        $worker['government_deploy_blocked'] = false;
    }

    // Set default status values if null
    $statusFields = ['police_status', 'medical_status', 'visa_status', 'ticket_status', 'training_certificate_status'];
    foreach ($statusFields as $field) {
        if (!isset($worker[$field])) {
            $worker[$field] = 'pending';
        }
    }

    sendResponse([
        'success' => true,
        'data' => $worker
    ]);

} catch (Exception $e) {
    error_log("Error in get-single.php: " . $e->getMessage());
    sendResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
} 