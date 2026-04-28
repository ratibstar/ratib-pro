<?php
/**
 * EN: Handles API endpoint/business logic in `api/agents/bulk-action.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/agents/bulk-action.php`.
 */
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['agent_ids']) || !is_array($data['agent_ids'])) {
        throw new Exception('Agent IDs are required');
    }

    $action = basename($_SERVER['PHP_SELF'], '.php');
    $action = str_replace('bulk-', '', $action);
    
    // Get old data for history (before action)
    $idPlaceholders = str_repeat('?,', count($data['agent_ids']) - 1) . '?';
    $fetchSql = "SELECT * FROM agents WHERE agent_id IN ($idPlaceholders)";
    $fetchStmt = $conn->prepare($fetchSql);
    $fetchStmt->bind_param(str_repeat('i', count($data['agent_ids'])), ...$data['agent_ids']);
    $fetchStmt->execute();
    $result = $fetchStmt->get_result();
    $oldAgents = [];
    while ($row = $result->fetch_assoc()) {
        $oldAgents[] = $row;
    }

    switch ($action) {
        case 'activate':
        case 'deactivate':
            $status = $action === 'activate' ? 'active' : 'inactive';
            $sql = "UPDATE agents SET status = ? WHERE agent_id IN (" . 
                   str_repeat('?,', count($data['agent_ids']) - 1) . "?)";
            $types = str_repeat('i', count($data['agent_ids']));
            $params = array_merge([$status], $data['agent_ids']);
            $types = 's' . $types;
            break;

        case 'delete':
            $sql = "DELETE FROM agents WHERE agent_id IN (" . 
                   str_repeat('?,', count($data['agent_ids']) - 1) . "?)";
            $types = str_repeat('i', count($data['agent_ids']));
            $params = $data['agent_ids'];
            break;

        default:
            throw new Exception('Invalid action');
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        // Log history
        $helperPath = __DIR__ . '/../core/global-history-helper.php';
        if (file_exists($helperPath)) {
            require_once $helperPath;
            if (function_exists('logGlobalHistory')) {
                if ($action === 'delete') {
                    foreach ($oldAgents as $deletedAgent) {
                        $agentId = $deletedAgent['agent_id'] ?? $deletedAgent['id'] ?? null;
                        if ($agentId) {
                            @logGlobalHistory('agents', $agentId, 'delete', 'agents', $deletedAgent, null);
                        }
                    }
                } else {
                    // Get updated data for history
                    $fetchStmt = $conn->prepare($fetchSql);
                    $fetchStmt->bind_param(str_repeat('i', count($data['agent_ids'])), ...$data['agent_ids']);
                    $fetchStmt->execute();
                    $result = $fetchStmt->get_result();
                    $newAgents = [];
                    while ($row = $result->fetch_assoc()) {
                        $newAgents[] = $row;
                    }
                    
                    foreach ($data['agent_ids'] as $agentId) {
                        $oldAgent = null;
                        $newAgent = null;
                        foreach ($oldAgents as $agent) {
                            if (($agent['agent_id'] ?? $agent['id'] ?? null) == $agentId) {
                                $oldAgent = $agent;
                                break;
                            }
                        }
                        foreach ($newAgents as $agent) {
                            if (($agent['agent_id'] ?? $agent['id'] ?? null) == $agentId) {
                                $newAgent = $agent;
                                break;
                            }
                        }
                        if ($oldAgent && $newAgent) {
                            @logGlobalHistory('agents', $agentId, 'update', 'agents', $oldAgent, $newAgent);
                        }
                    }
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Agents ' . $action . 'd successfully'
        ]);
    } else {
        throw new Exception('Failed to ' . $action . ' agents');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 