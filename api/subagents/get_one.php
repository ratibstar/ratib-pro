<?php
/**
 * EN: Handles API endpoint/business logic in `api/subagents/get_one.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/subagents/get_one.php`.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../core/Database.php';

try {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        throw new Exception('ID is required');
    }

    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    $stmt = $pdo->prepare("
        SELECT s.*, u.username as user_name, a.company_name as agent_company
        FROM subagents s
        LEFT JOIN users u ON s.user_id = u.user_id
        LEFT JOIN agents a ON s.agent_id = a.id
        WHERE s.id = :id OR s.formatted_id = :formatted_id
    ");
    
    $stmt->execute([
        ':id' => $id,
        ':formatted_id' => $id
    ]);
    
    $subagent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$subagent) {
        http_response_code(404);
        throw new Exception('Subagent not found');
    }

    echo json_encode([
        'status' => 'success',
        'data' => $subagent
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}