<?php
/**
 * EN: Handles API endpoint/business logic in `api/agents/get_one.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/agents/get_one.php`.
 */
require_once __DIR__ . '/../core/Database.php';

header('Content-Type: application/json');

try {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        throw new Exception('ID is required');
    }
    
    $db = Database::getInstance();
    $agent = $db->getAgentById($id);
    
    if (!$agent) {
        throw new Exception('Agent not found');
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => $agent
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}