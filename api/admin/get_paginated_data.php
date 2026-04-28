<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/get_paginated_data.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/get_paginated_data.php`.
 */
// Completely disable error reporting and output
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 0);

// Clear any existing output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Start fresh output buffering
ob_start();

// Function to send JSON response and exit
function sendJsonResponse($success, $message = '', $data = null, $pagination = null) {
    // Clear ALL output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    $response = ['success' => $success];
    if ($message) $response['message'] = $message;
    if ($data !== null) $response['data'] = $data;
    if ($pagination !== null) $response['pagination'] = $pagination;
    
    header('Content-Type: application/json');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    echo json_encode($response);
    exit;
}

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    sendJsonResponse(true);
}

try {
    // Include config
    require_once '../../includes/config.php';

    if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        sendJsonResponse(false, 'Not authenticated');
    }
    if (!isset($_SESSION['role_id']) || (int)$_SESSION['role_id'] !== 1) {
        sendJsonResponse(false, 'Access denied');
    }
    
    // Get parameters
    $table = $_GET['table'] ?? '';
    $page = intval($_GET['page'] ?? 1);
    $limit = intval($_GET['limit'] ?? 10);
    $search = $_GET['search'] ?? '';
    
    // Validate required parameters
    if (empty($table)) {
        sendJsonResponse(false, 'Table parameter is required');
    }
    
    // Check database connection
    if (!$conn) {
        sendJsonResponse(false, 'Database connection failed');
    }
    
    // Validate table name to prevent SQL injection
    $allowed_tables = [
        'office_manager', 'visa_types', 'recruitment_countries', 'job_categories',
        'age_specifications', 'appearance_specifications', 'status_specifications',
        'request_statuses', 'arrival_agencies', 'arrival_stations', 'worker_statuses',
        'system_config'
    ];
    
    if (!in_array($table, $allowed_tables)) {
        sendJsonResponse(false, 'Invalid table name');
    }
    
    // Check if table exists
    $table_check = $conn->query("SHOW TABLES LIKE '$table'");
    if (!$table_check || $table_check->num_rows === 0) {
        sendJsonResponse(false, "Table '$table' does not exist");
    }
    
    // Special handling for office_manager - add status column if missing
    if ($table === 'office_manager') {
        $result = $conn->query("SHOW COLUMNS FROM office_manager LIKE 'status'");
        if (!$result || $result->num_rows === 0) {
            $sql = "ALTER TABLE office_manager ADD COLUMN status TINYINT(1) DEFAULT 1 COMMENT '1=Active, 0=Inactive'";
            if ($conn->query($sql)) {
                // Update existing records to have status = 1 (active)
                $update_sql = "UPDATE office_manager SET status = 1 WHERE status IS NULL";
                $conn->query($update_sql);
            }
        }
    }
    
    // Special handling for system_config - ensure only one status column is used
    if ($table === 'system_config') {
        // Check if both is_active and is_editable exist
        $result1 = $conn->query("SHOW COLUMNS FROM system_config LIKE 'is_active'");
        $result2 = $conn->query("SHOW COLUMNS FROM system_config LIKE 'is_editable'");
        $has_is_active = $result1 && $result1->num_rows > 0;
        $has_is_editable = $result2 && $result2->num_rows > 0;
        
        // If both exist, we'll handle this in the JavaScript by filtering out is_editable
        // The JavaScript will only show is_active as "Status"
    }
    
    // Validate pagination parameters
    if ($page < 1) $page = 1;
    if ($limit < 1 || $limit > 100) $limit = 5;
    
    $offset = ($page - 1) * $limit;
    
    // Build search condition
    $search_condition = '';
    $search_params = [];
    
    if (!empty($search)) {
        // Get table columns for search
        $columns_query = "SHOW COLUMNS FROM $table";
        $columns_result = $conn->query($columns_query);
        $searchable_columns = [];
        
        if ($columns_result) {
            while ($column = $columns_result->fetch_assoc()) {
                $field_type = strtolower($column['Type']);
                // Only search in text-based columns
                if (strpos($field_type, 'varchar') !== false || 
                    strpos($field_type, 'text') !== false || 
                    strpos($field_type, 'char') !== false) {
                    $searchable_columns[] = $column['Field'];
                }
            }
        }
        
        if (!empty($searchable_columns)) {
            $search_conditions = [];
            foreach ($searchable_columns as $column) {
                $search_conditions[] = "$column LIKE ?";
                $search_params[] = "%$search%";
            }
            $search_condition = "WHERE " . implode(" OR ", $search_conditions);
        }
    }
    
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM $table $search_condition";
    $count_stmt = $conn->prepare($count_query);
    
    if (!$count_stmt) {
        sendJsonResponse(false, "Failed to prepare count query: " . $conn->error);
    }
    
    if (!empty($search_params)) {
        $count_stmt->bind_param(str_repeat('s', count($search_params)), ...$search_params);
    }
    
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_records = $count_result->fetch_assoc()['total'];
    $total_pages = ceil($total_records / $limit);
    
    // Get paginated data
    $data_query = "SELECT * FROM $table $search_condition ORDER BY id DESC LIMIT ? OFFSET ?";
    $data_stmt = $conn->prepare($data_query);
    
    if (!$data_stmt) {
        sendJsonResponse(false, "Failed to prepare data query: " . $conn->error);
    }
    
    if (!empty($search_params)) {
        $all_params = array_merge($search_params, [$limit, $offset]);
        $data_stmt->bind_param(str_repeat('s', count($search_params)) . 'ii', ...$all_params);
    } else {
        $data_stmt->bind_param('ii', $limit, $offset);
    }
    
    $data_stmt->execute();
    $data_result = $data_stmt->get_result();
    
    $data = [];
    while ($row = $data_result->fetch_assoc()) {
        $data[] = $row;
    }
    
    // Send success response
    sendJsonResponse(true, '', $data, [
        'current_page' => $page,
        'total_pages' => $total_pages,
        'total_records' => $total_records,
        'limit' => $limit,
        'has_next' => $page < $total_pages,
        'has_prev' => $page > 1
    ]);
    
} catch (Exception $e) {
    sendJsonResponse(false, 'Error loading data: ' . $e->getMessage());
}
?>
