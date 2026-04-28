<?php
/**
 * EN: Handles API endpoint/business logic in `api/subagents/update-status.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/subagents/update-status.php`.
 */
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['ids']) || !isset($input['status'])) {
        throw new Exception('Missing required parameters');
    }

    $ids = $input['ids'];
    $status = $input['status'];

    if (!is_array($ids) || empty($ids)) {
        throw new Exception('Invalid IDs provided');
    }

    if (!in_array($status, ['active', 'inactive'])) {
        throw new Exception('Invalid status');
    }

    // Get old data for history (before update)
    $idPlaceholders = str_repeat('?,', count($ids) - 1) . '?';
    $fetchSql = "SELECT * FROM subagents WHERE subagent_id IN ($idPlaceholders)";
    $fetchStmt = $conn->prepare($fetchSql);
    $fetchStmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $fetchStmt->execute();
    $result = $fetchStmt->get_result();
    $oldSubagents = [];
    while ($row = $result->fetch_assoc()) {
        $oldSubagents[] = $row;
    }
    
    // Start transaction
    $conn->begin_transaction();

    try {
        // Update subagents table only - remove the users table update since there's no user_id column
        $query = "UPDATE subagents SET status = ?, updated_at = NOW() WHERE subagent_id IN ($idPlaceholders)";
        
        $stmt = $conn->prepare($query);
        
        // Create array of parameters starting with status
        $params = array_merge([$status], $ids);
        
        // Create type string (s for status, followed by i for each ID)
        $types = 's' . str_repeat('i', count($ids));
        
        // Bind parameters
        $stmt->bind_param($types, ...$params);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update subagents: " . $stmt->error);
        }

        $conn->commit();
        
        // Get updated data for history
        $fetchStmt = $conn->prepare($fetchSql);
        $fetchStmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        $fetchStmt->execute();
        $result = $fetchStmt->get_result();
        $newSubagents = [];
        while ($row = $result->fetch_assoc()) {
            $newSubagents[] = $row;
        }
        
        // Log history for each updated subagent
        $helperPath = __DIR__ . '/../core/global-history-helper.php';
        if (file_exists($helperPath)) {
            require_once $helperPath;
            if (function_exists('logGlobalHistory')) {
                foreach ($ids as $subagentId) {
                    $oldSubagent = null;
                    $newSubagent = null;
                    foreach ($oldSubagents as $subagent) {
                        if (($subagent['subagent_id'] ?? $subagent['id'] ?? null) == $subagentId) {
                            $oldSubagent = $subagent;
                            break;
                        }
                    }
                    foreach ($newSubagents as $subagent) {
                        if (($subagent['subagent_id'] ?? $subagent['id'] ?? null) == $subagentId) {
                            $newSubagent = $subagent;
                            break;
                        }
                    }
                    if ($oldSubagent && $newSubagent) {
                        @logGlobalHistory('subagents', $subagentId, 'update', 'subagents', $oldSubagent, $newSubagent);
                    }
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Status updated successfully'
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close(); 