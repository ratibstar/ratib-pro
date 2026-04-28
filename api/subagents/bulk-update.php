<?php
/**
 * EN: Handles API endpoint/business logic in `api/subagents/bulk-update.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/subagents/bulk-update.php`.
 */
// Disable error display to prevent HTML output
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once __DIR__ . '/../core/api-permission-helper.php';
enforceApiPermission('subagents', 'bulk_update');

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/ApiResponse.php';

try {
    // Test if classes are available
    if (!class_exists('Database')) {
        echo json_encode(['success' => false, 'message' => 'Database class not found']);
        exit;
    }
    
    if (!class_exists('ApiResponse')) {
        echo json_encode(['success' => false, 'message' => 'ApiResponse class not found']);
        exit;
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(ApiResponse::error('Invalid JSON input'));
        exit;
    }
    
    // Validate required fields
    if (!isset($input['ids']) || !is_array($input['ids']) || empty($input['ids'])) {
        echo json_encode(ApiResponse::error('IDs array is required'));
        exit;
    }
    
    if (!isset($input['updates']) || !is_array($input['updates'])) {
        echo json_encode(ApiResponse::error('Updates object is required'));
        exit;
    }
    
    $ids = $input['ids'];
    $updates = $input['updates'];
    
    // Validate IDs are numeric
    foreach ($ids as $id) {
        if (!is_numeric($id)) {
            echo json_encode(ApiResponse::error('Invalid ID format'));
            exit;
        }
    }
    
    // Validate updates
    $allowedFields = ['status', 'agent_id', 'city', 'address'];
    foreach ($updates as $field => $value) {
        if (!in_array($field, $allowedFields)) {
            echo json_encode(ApiResponse::error("Field '$field' is not allowed for bulk update"));
            exit;
        }
    }
    
    $db = Database::getInstance();
    
    // Prepare the update query
    $setParts = [];
    $params = [];
    
    foreach ($updates as $field => $value) {
        $setParts[] = "$field = ?";
        $params[] = $value;
    }
    
    if (empty($setParts)) {
        echo json_encode(ApiResponse::error('No valid updates provided'));
        exit;
    }
    
    // Add updated_at timestamp
    $setParts[] = "updated_at = CURRENT_TIMESTAMP";
    
    // Create placeholders for IDs
    $idPlaceholders = str_repeat('?,', count($ids) - 1) . '?';
    $params = array_merge($params, $ids);
    
    $sql = "UPDATE subagents SET " . implode(', ', $setParts) . " WHERE id IN ($idPlaceholders)";
    
    // Get old data for history (before update)
    $conn = $db->getConnection();
    $fetchPlaceholders = str_repeat('?,', count($ids) - 1) . '?';
    $fetchSql = "SELECT * FROM subagents WHERE id IN ($fetchPlaceholders)";
    $fetchStmt = $conn->prepare($fetchSql);
    $fetchStmt->execute($ids);
    $oldSubagents = $fetchStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Execute the update
    $result = $db->execute($sql, $params);
    
    if ($result !== false) {
        // Log history for each updated subagent
        $helperPath = __DIR__ . '/../core/global-history-helper.php';
        if (file_exists($helperPath)) {
            require_once $helperPath;
            if (function_exists('logGlobalHistory')) {
                foreach ($ids as $subagentId) {
                    $oldSubagent = null;
                    foreach ($oldSubagents as $subagent) {
                        if ($subagent['id'] == $subagentId) {
                            $oldSubagent = $subagent;
                            break;
                        }
                    }
                    
                    $newStmt = $conn->prepare("SELECT * FROM subagents WHERE id = ?");
                    $newStmt->execute([$subagentId]);
                    $newSubagent = $newStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($oldSubagent && $newSubagent) {
                        @logGlobalHistory('subagents', $subagentId, 'update', 'subagents', $oldSubagent, $newSubagent);
                    }
                }
            }
        }
        
        // Get affected rows count (PDO uses rowCount on statement, not connection)
        $affectedRows = $result ? $result->rowCount() : 0;
        
        echo ApiResponse::success([
            'affected_rows' => $affectedRows,
            'updated_ids' => $ids
        ], "Successfully updated $affectedRows subagents");
    } else {
        echo ApiResponse::error('Failed to update subagents');
    }
    
} catch (Exception $e) {
    error_log("Bulk update error: " . $e->getMessage());
    echo ApiResponse::error('Internal server error: ' . $e->getMessage());
} catch (Error $e) {
    error_log("Bulk update fatal error: " . $e->getMessage());
    echo ApiResponse::error('Fatal error: ' . $e->getMessage());
}
?>