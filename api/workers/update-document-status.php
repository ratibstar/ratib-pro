<?php
/**
 * EN: Handles API endpoint/business logic in `api/workers/update-document-status.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/workers/update-document-status.php`.
 */
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../../Utils/response.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id']) || empty($data['document_type']) || !isset($data['status'])) {
        throw new Exception('Worker ID, document type and status are required');
    }

    // Validate document type
    $validDocTypes = ['police', 'medical', 'visa', 'ticket'];
    if (!in_array($data['document_type'], $validDocTypes)) {
        throw new Exception('Invalid document type');
    }

    // Status mapping - use database values directly
    $statusMap = [
        'pending' => 'pending',
        'ok' => 'ok',
        'not_ok' => 'not_ok'
    ];

    // Validate and map status
    if (!isset($statusMap[$data['status']])) {
        throw new Exception('Invalid status value: ' . $data['status']);
    }
    $status = $statusMap[$data['status']];

    $db = Database::getInstance();
    $conn = $db->getConnection();

    // First check if worker exists
    $checkQuery = "SELECT id, {$data['document_type']}_status FROM workers WHERE id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->execute([$data['id']]);
    $existingWorker = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existingWorker) {
        throw new Exception('Worker not found');
    }
    
    $currentStatus = $existingWorker[$data['document_type'] . '_status'];
    
    // Get old data for history (before update)
    $oldStmt = $conn->prepare("SELECT * FROM workers WHERE id = ?");
    $oldStmt->execute([$data['id']]);
    $oldWorker = $oldStmt->fetch(PDO::FETCH_ASSOC);
    
    // Don't check if status is changing - just update it
    // This allows setting the same status multiple times

    // Update document status
    $sql = "UPDATE workers SET 
            {$data['document_type']}_status = ?,
            updated_at = NOW()
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([$status, $data['id']]);

    if (!$result) {
        throw new Exception('Database update failed');
    }

    // Get updated worker data
    $query = "
        SELECT w.*, 
               a.full_name as agent_name,
               a.formatted_id as agent_formatted_id,
               s.full_name as subagent_name,
               s.formatted_id as subagent_formatted_id
        FROM workers w
        LEFT JOIN agents a ON w.agent_id = a.agent_id
        LEFT JOIN subagents s ON w.subagent_id = s.subagent_id
        WHERE w.id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$data['id']]);
    $updatedWorker = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Log history
    $helperPath = __DIR__ . '/../core/global-history-helper.php';
    if (file_exists($helperPath) && $oldWorker && $updatedWorker) {
        require_once $helperPath;
        if (function_exists('logGlobalHistory')) {
            @logGlobalHistory('workers', $data['id'], 'update', 'workers', $oldWorker, $updatedWorker);
        }
    }

    sendResponse([
        'success' => true,
        'message' => 'Document status updated successfully',
        'data' => $updatedWorker
    ]);

} catch (Exception $e) {
    sendResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
} 