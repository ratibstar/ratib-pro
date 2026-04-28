<?php
/**
 * EN: Handles API endpoint/business logic in `api/workers/utils/status.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/workers/utils/status.php`.
 */
require_once __DIR__ . '/../../core/Database.php';
require_once '../../utils/response.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['ids']) || !is_array($data['ids']) || empty($data['status'])) {
        throw new Exception('Worker IDs and status are required');
    }

    if (!in_array($data['status'], ['active', 'inactive'])) {
        throw new Exception('Invalid status value');
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Convert to integers and validate
    $ids = array_map('intval', $data['ids']);
    $ids = array_filter($ids);
    
    if (empty($ids)) {
        throw new Exception('No valid worker IDs provided');
    }

    // Update worker statuses
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    $sql = "UPDATE workers SET status = ? WHERE id IN ($placeholders)";
    
    $params = array_merge([$data['status']], $ids);
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    $affected = $stmt->rowCount();

    sendResponse([
        'success' => true,
        'message' => "$affected worker(s) updated successfully",
        'data' => [
            'updated_count' => $affected,
            'updated_ids' => $ids,
            'new_status' => $data['status']
        ]
    ]);

} catch (Exception $e) {
    sendResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
} 