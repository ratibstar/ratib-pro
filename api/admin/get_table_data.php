<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/get_table_data.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/get_table_data.php`.
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
function sendJsonResponse($success, $message = '', $data = null) {
    // Clear ALL output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    $response = ['success' => $success];
    if ($message) $response['message'] = $message;
    if ($data !== null) $response['data'] = $data;
    
    header('Content-Type: application/json');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    echo json_encode($response);
    exit;
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
    
    // Get data from the specified table
    $query = "SELECT * FROM $table ORDER BY id DESC";
    $result = $conn->query($query);
    
    if (!$result) {
        sendJsonResponse(false, "Error querying table: " . $conn->error);
    }
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    // Send success response
    sendJsonResponse(true, '', $data);
    
} catch (Exception $e) {
    sendJsonResponse(false, 'Error loading data: ' . $e->getMessage());
}
?> 