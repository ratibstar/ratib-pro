<?php
/**
 * EN: Handles API endpoint/business logic in `api/agents/bulk-delete.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/agents/bulk-delete.php`.
 */
require_once __DIR__ . '/../core/api-permission-helper.php';
enforceApiPermission('agents', 'bulk_delete');

require_once '../../includes/config.php';
require_once '../../includes/auth.php';



header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['agent_ids']) || !is_array($data['agent_ids'])) {
        throw new Exception('Invalid agent IDs');
    }

    // Convert IDs to integers and create placeholders
    $agent_ids = array_map('intval', $data['agent_ids']);
    $placeholders = str_repeat('?,', count($agent_ids) - 1) . '?';
    
    // Get old data for history (before deletion)
    $fetchPlaceholders = str_repeat('?,', count($agent_ids) - 1) . '?';
    $fetchSql = "SELECT * FROM agents WHERE agent_id IN ($fetchPlaceholders)";
    $fetchStmt = $conn->prepare($fetchSql);
    $fetchStmt->bind_param(str_repeat('i', count($agent_ids)), ...$agent_ids);
    $fetchStmt->execute();
    $result = $fetchStmt->get_result();
    $deletedAgents = [];
    while ($row = $result->fetch_assoc()) {
        $deletedAgents[] = $row;
    }
    
    $stmt = $conn->prepare("
        DELETE FROM agents 
        WHERE agent_id IN ($placeholders)
    ");
    
    $stmt->bind_param(str_repeat('i', count($agent_ids)), ...$agent_ids);

    if ($stmt->execute()) {
        // Log history for each deleted agent
        $helperPath = __DIR__ . '/../core/global-history-helper.php';
        if (file_exists($helperPath)) {
            require_once $helperPath;
            if (function_exists('logGlobalHistory')) {
                foreach ($deletedAgents as $deletedAgent) {
                    @logGlobalHistory('agents', $deletedAgent['agent_id'], 'delete', 'agents', $deletedAgent, null);
                }
            }
        }
        
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to delete agents');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 