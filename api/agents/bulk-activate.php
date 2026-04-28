<?php
/**
 * EN: Handles API endpoint/business logic in `api/agents/bulk-activate.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/agents/bulk-activate.php`.
 */
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

require_once '../../includes/permission_middleware.php';

// Check if user has permission to access this endpoint
checkApiPermission('agents_edit');



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
    
    // Detect primary key column (id or agent_id)
    $pkColumn = 'id';
    $testResult = $conn->query("SHOW COLUMNS FROM agents LIKE 'id'");
    if (!$testResult || $testResult->num_rows === 0) {
        $testResult = $conn->query("SHOW COLUMNS FROM agents LIKE 'agent_id'");
        if ($testResult && $testResult->num_rows > 0) {
            $pkColumn = 'agent_id';
        }
    }
    
    // Get old data for history (before update)
    $oldData = [];
    $fetchStmt = $conn->prepare("SELECT * FROM agents WHERE $pkColumn IN ($placeholders)");
    $fetchStmt->bind_param(str_repeat('i', count($agent_ids)), ...$agent_ids);
    $fetchStmt->execute();
    $result = $fetchStmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $oldData[] = $row;
    }
    $fetchStmt->close();
    
    $stmt = $conn->prepare("
        UPDATE agents 
        SET status = 'active' 
        WHERE $pkColumn IN ($placeholders)
    ");
    
    $stmt->bind_param(str_repeat('i', count($agent_ids)), ...$agent_ids);

    if ($stmt->execute()) {
        // Get updated data for history
        $newData = [];
        $fetchStmt = $conn->prepare("SELECT * FROM agents WHERE $pkColumn IN ($placeholders)");
        $fetchStmt->bind_param(str_repeat('i', count($agent_ids)), ...$agent_ids);
        $fetchStmt->execute();
        $result = $fetchStmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $newData[] = $row;
        }
        $fetchStmt->close();
        
        // Log history for each agent
        $helperPath = __DIR__ . '/../core/global-history-helper.php';
        if (file_exists($helperPath)) {
            require_once $helperPath;
            if (function_exists('logGlobalHistory')) {
                foreach ($agent_ids as $agent_id) {
                    $oldRecord = null;
                    $newRecord = null;
                    
                    // Use the detected PK column to match records
                    foreach ($oldData as $record) {
                        $recordId = $record[$pkColumn] ?? $record['id'] ?? $record['agent_id'] ?? null;
                        if ($recordId == $agent_id) {
                            $oldRecord = $record;
                            break;
                        }
                    }
                    foreach ($newData as $record) {
                        $recordId = $record[$pkColumn] ?? $record['id'] ?? $record['agent_id'] ?? null;
                        if ($recordId == $agent_id) {
                            $newRecord = $record;
                            break;
                        }
                    }
                    
                    if ($oldRecord && $newRecord) {
                        try {
                            @logGlobalHistory('agents', $agent_id, 'update', 'agents', $oldRecord, $newRecord);
                        } catch (Exception $e) {
                            // History logging failed - continue silently
                        }
                    }
                }
            }
        }
        
        $stmt->close();
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to activate agents');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 