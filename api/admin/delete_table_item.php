<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/delete_table_item.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/delete_table_item.php`.
 */
require_once '../../includes/config.php';

header('Content-Type: application/json');

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

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['table']) || !isset($input['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid input data'
    ]);
    exit;
}

$table = $input['table'];
$id = $input['id'];

try {
    // Validate table name to prevent SQL injection
    $allowed_tables = [
        'office_manager', 'visa_types', 'recruitment_countries', 'job_categories',
        'age_specifications', 'appearance_specifications', 'status_specifications',
        'request_statuses', 'arrival_agencies', 'arrival_stations', 'worker_statuses',
        'system_config'
    ];
    
    if (!in_array($table, $allowed_tables)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid table name'
        ]);
        exit;
    }
    
    $query = "DELETE FROM $table WHERE id = " . intval($id);
    
    if ($conn->query($query)) {
        echo json_encode([
            'success' => true,
            'message' => 'Item deleted successfully'
        ]);
    } else {
        throw new Exception("Error executing query: " . $conn->error);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error deleting item: ' . $e->getMessage()
    ]);
}
?> 