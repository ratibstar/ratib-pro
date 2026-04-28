<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/update_status.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/update_status.php`.
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
    
    if (!isset($input['table']) || !isset($input['id']) || !isset($input['status'])) {
        throw new Exception('Missing required parameters');
    }
    
    $table = $input['table'];
    $id = $input['id'];
    $status = $input['status'];
    
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
    
    // Determine status column based on table
    $status_column = 'status';
    if ($table === 'visa_types') {
        $status_column = 'is_active';
    } elseif ($table === 'office_manager') {
        // Check if status column exists in office_manager, if not create it
        $result = $conn->query("SHOW COLUMNS FROM office_manager LIKE 'status'");
        if (!$result || $result->num_rows === 0) {
            $sql = "ALTER TABLE office_manager ADD COLUMN status TINYINT(1) DEFAULT 1 COMMENT '1=Active, 0=Inactive'";
            if ($conn->query($sql)) {
                // Update existing records to have status = 1 (active)
                $update_sql = "UPDATE office_manager SET status = 1 WHERE status IS NULL";
                $conn->query($update_sql);
            }
        }
        $status_column = 'status';
    }
    
    // Update the status
    $sql = "UPDATE $table SET $status_column = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $status, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
    } else {
        throw new Exception('Failed to update status');
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
