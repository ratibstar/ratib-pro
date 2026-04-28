<?php
/**
 * EN: Handles API endpoint/business logic in `api/agents/add.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/agents/add.php`.
 */
require_once '../../includes/config.php';

header('Content-Type: application/json');

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Start transaction
    $conn->begin_transaction();

    // First create user account
    $userStmt = $conn->prepare("
        INSERT INTO users (username, password, email, role, role_id) 
        VALUES (?, ?, ?, 'agent', 2)
    ");
    
    $password = password_hash($data['password'] ?? 'default123', PASSWORD_DEFAULT);
    $userStmt->bind_param("sss", 
        $data['email'], // use email as username
        $password,
        $data['email']
    );
    
    $userStmt->execute();
    $userId = $conn->insert_id;

    // Then create agent record
    $agentStmt = $conn->prepare("
        INSERT INTO agents (user_id, full_name, email, phone, city, address) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $agentStmt->bind_param("isssss",
        $userId,
        $data['full_name'],
        $data['email'],
        $data['phone'],
        $data['city'],
        $data['address']
    );
    
    $agentStmt->execute();
    $agentId = $conn->insert_id;
    
    // Commit transaction
    $conn->commit();
    
    // Auto-create GL account for this agent
    require_once __DIR__ . '/../accounting/entity-account-helper.php';
    $agentName = $data['full_name'] ?? $data['agent_name'] ?? '';
    if ($agentName) {
        ensureEntityAccount($conn, 'agent', $agentId, $agentName);
    }
    
    // Get created agent for history
    $fetchStmt = $conn->prepare("SELECT * FROM agents WHERE id = ? OR agent_id = ?");
    $fetchStmt->bind_param('ii', $agentId, $agentId);
    $fetchStmt->execute();
    $result = $fetchStmt->get_result();
    $newAgent = $result->fetch_assoc();
    
    // Log history
    $helperPath = __DIR__ . '/../core/global-history-helper.php';
    if (file_exists($helperPath) && $newAgent) {
        require_once $helperPath;
        if (function_exists('logGlobalHistory')) {
            $recordId = $newAgent['id'] ?? $newAgent['agent_id'] ?? $agentId;
            @logGlobalHistory('agents', $recordId, 'create', 'agents', null, $newAgent);
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Agent added successfully',
        'agent_id' => $agentId
    ]);

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to add agent: ' . $e->getMessage()
    ]);
}

$conn->close();