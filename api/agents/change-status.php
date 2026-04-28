<?php
/**
 * EN: Handles API endpoint/business logic in `api/agents/change-status.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/agents/change-status.php`.
 */
require_once __DIR__ . '/../core/Database.php';

header('Content-Type: application/json');

try {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        throw new Exception('ID is required');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['status'])) {
        throw new Exception('Status is required');
    }
    
    if (!in_array($input['status'], ['active', 'inactive'])) {
        throw new Exception('Invalid status value');
    }
    
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $pdo->beginTransaction();
    
    try {
        $agent = $db->getAgentById($id);
        if (!$agent) {
            throw new Exception('Agent not found');
        }
        
        $updated = $db->updateAgent($id, ['status' => $input['status']]);
        
        $pdo->commit();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Agent status updated successfully',
            'data' => $updated
        ], JSON_PRETTY_PRINT);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}