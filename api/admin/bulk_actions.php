<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/bulk_actions.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/bulk_actions.php`.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../../includes/config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}
if (!isset($_SESSION['role_id']) || (int)$_SESSION['role_id'] !== 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['table']) || !isset($input['action']) || !isset($input['ids'])) {
        throw new Exception('Missing required parameters');
    }
    
    $table = $input['table'];
    $action = $input['action'];
    $ids = $input['ids'];
    
    // Validate table name to prevent SQL injection
    $allowed_tables = [
        'visa_types', 'recruitment_countries', 'job_categories', 'age_specifications', 
        'appearance_specifications', 'status_specifications', 'request_statuses', 
        'arrival_agencies', 'arrival_stations', 'worker_statuses', 'office_manager', 
        'system_config', 'countries', 'cities', 'currencies', 'languages'
    ];
    if (!in_array($table, $allowed_tables)) {
        throw new Exception('Invalid table name');
    }
    
    // Validate action
    $allowed_actions = ['activate', 'deactivate', 'delete'];
    if (!in_array($action, $allowed_actions)) {
        throw new Exception('Invalid action');
    }
    
    // Convert IDs array to comma-separated string for SQL
    $id_list = implode(',', array_map('intval', $ids));
    
    if ($action === 'delete') {
        // Delete records
        $sql = "DELETE FROM $table WHERE id IN ($id_list)";
        $result = $conn->query($sql);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Records deleted successfully']);
        } else {
            throw new Exception('Failed to delete records');
        }
    } else {
        // Activate or deactivate records
        $status = ($action === 'activate') ? '1' : '0';
        
        // Check which status column exists in the table
        $result = $conn->query("SHOW COLUMNS FROM $table LIKE 'is_active'");
        $has_is_active = $result && $result->num_rows > 0;
        
        $result = $conn->query("SHOW COLUMNS FROM $table LIKE 'status'");
        $has_status = $result && $result->num_rows > 0;
        
        // Determine the correct status column
        if ($has_is_active) {
            $status_column = 'is_active';
        } elseif ($has_status) {
            $status_column = 'status';
        } else {
            // Special handling for office_manager - create status column if missing
            if ($table === 'office_manager') {
                $sql = "ALTER TABLE office_manager ADD COLUMN status TINYINT(1) DEFAULT 1 COMMENT '1=Active, 0=Inactive'";
                if ($conn->query($sql)) {
                    $status_column = 'status';
                    // Update existing records to have status = 1 (active)
                    $update_sql = "UPDATE office_manager SET status = 1 WHERE status IS NULL";
                    $conn->query($update_sql);
                } else {
                    echo json_encode(['success' => true, 'message' => 'No status column found in table, skipping status update']);
                    exit;
                }
            } else {
                // If no status column exists, skip the update and just return success
                echo json_encode(['success' => true, 'message' => 'No status column found in table, skipping status update']);
                exit;
            }
        }
        
        $sql = "UPDATE $table SET $status_column = ? WHERE id IN ($id_list)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $status);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Records updated successfully']);
        } else {
            throw new Exception('Failed to update records');
        }
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
