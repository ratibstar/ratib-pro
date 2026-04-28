<?php
/**
 * EN: Handles API endpoint/business logic in `api/agents/activate.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/agents/activate.php`.
 */
require_once __DIR__ . '/../core/Database.php';

require_once '../../includes/permission_middleware.php';

// Check if user has permission to access this endpoint
checkApiPermission('agents_edit');



header('Content-Type: application/json');

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Get agent ID from request
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        // Get old data for history (before update)
        $stmt = $conn->query("SELECT * FROM agents");
        $oldAgents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Activate all agents if no ID provided
        $db->execute("UPDATE agents SET status = 'active', updated_at = CURRENT_TIMESTAMP");
        
        // Get updated data for history
        $stmt = $conn->query("SELECT * FROM agents");
        $newAgents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Log history for each agent
        $helperPath = __DIR__ . '/../core/global-history-helper.php';
        if (file_exists($helperPath)) {
            require_once $helperPath;
            if (function_exists('logGlobalHistory')) {
                foreach ($oldAgents as $oldAgent) {
                    $agentId = $oldAgent['id'] ?? $oldAgent['agent_id'] ?? null;
                    if ($agentId) {
                        $newAgent = null;
                        foreach ($newAgents as $agent) {
                            $newId = $agent['id'] ?? $agent['agent_id'] ?? null;
                            if ($newId == $agentId) {
                                $newAgent = $agent;
                                break;
                            }
                        }
                        if ($newAgent) {
                            @logGlobalHistory('agents', $agentId, 'update', 'agents', $oldAgent, $newAgent);
                        }
                    }
                }
            }
        }
        
        $message = "All agents activated successfully";
    } else {
        // Get old data for history (before update)
        $stmt = $conn->prepare("SELECT * FROM agents WHERE agent_id = ? OR id = ?");
        $stmt->execute([$id, $id]);
        $oldAgent = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Activate specific agent
        $db->execute(
            "UPDATE agents SET status = 'active', updated_at = CURRENT_TIMESTAMP WHERE agent_id = :id OR id = :id",
            [':id' => $id]
        );
        
        // Get updated data for history
        $stmt = $conn->prepare("SELECT * FROM agents WHERE agent_id = ? OR id = ?");
        $stmt->execute([$id, $id]);
        $newAgent = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Log history
        $helperPath = __DIR__ . '/../core/global-history-helper.php';
        if (file_exists($helperPath) && $oldAgent && $newAgent) {
            require_once $helperPath;
            if (function_exists('logGlobalHistory')) {
                $agentId = $oldAgent['id'] ?? $oldAgent['agent_id'] ?? $id;
                @logGlobalHistory('agents', $agentId, 'update', 'agents', $oldAgent, $newAgent);
            }
        }
        
        $message = "Agent activated successfully";
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => $message
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}