<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/get_system_settings.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/get_system_settings.php`.
 */
// Error reporting (Production: log only, don't display)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/includes/config.php';

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
    $settingType = $_POST['setting_type'] ?? '';
    
    if (empty($settingType)) {
        throw new Exception('Setting type is required');
    }
    
    // Log the request for debugging
    error_log("get_system_settings.php called with setting_type: " . $settingType);
    
    switch ($settingType) {
        case 'office_manager':
            $result = $conn->query("SELECT * FROM office_manager_data LIMIT 1");
            if (!$result) {
                throw new Exception("Database error: " . $conn->error . " - Table 'office_manager_data' may not exist");
            }
            $data = $result->fetch_assoc();
            break;
            
        case 'visa_types':
            $result = $conn->query("SELECT * FROM visa_types ORDER BY visa_name");
            if (!$result) {
                throw new Exception("Database error: " . $conn->error . " - Table 'visa_types' may not exist");
            }
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            break;
            
        case 'recruitment_countries':
            $result = $conn->query("SELECT * FROM recruitment_countries ORDER BY country_name");
            if (!$result) {
                throw new Exception("Database error: " . $conn->error . " - Table 'recruitment_countries' may not exist");
            }
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            break;
            
        case 'job_categories':
            $result = $conn->query("SELECT * FROM job_categories ORDER BY category_name");
            if (!$result) {
                throw new Exception("Database error: " . $conn->error . " - Table 'job_categories' may not exist");
            }
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            break;
            
        case 'age_specifications':
            $result = $conn->query("SELECT * FROM age_specifications ORDER BY min_age");
            if (!$result) {
                throw new Exception("Database error: " . $conn->error . " - Table 'age_specifications' may not exist");
            }
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            break;
            
        case 'appearance_specifications':
            $result = $conn->query("SELECT * FROM appearance_specifications ORDER BY spec_name");
            if (!$result) {
                throw new Exception("Database error: " . $conn->error . " - Table 'appearance_specifications' may not exist");
            }
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            break;
            
        case 'status_specifications':
            $result = $conn->query("SELECT * FROM status_specifications ORDER BY status_name");
            if (!$result) {
                throw new Exception("Database error: " . $conn->error . " - Table 'status_specifications' may not exist");
            }
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            break;
            
        case 'request_statuses':
            $result = $conn->query("SELECT * FROM request_statuses ORDER BY status_name");
            if (!$result) {
                throw new Exception("Database error: " . $conn->error . " - Table 'request_statuses' may not exist");
            }
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            break;
            
        case 'arrival_agencies':
            $result = $conn->query("SELECT * FROM arrival_agencies ORDER BY agency_name");
            if (!$result) {
                throw new Exception("Database error: " . $conn->error . " - Table 'arrival_agencies' may not exist");
            }
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            break;
            
        case 'arrival_stations':
            $result = $conn->query("SELECT * FROM arrival_stations ORDER BY station_name");
            if (!$result) {
                throw new Exception("Database error: " . $conn->error . " - Table 'arrival_stations' may not exist");
            }
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            break;
            
        case 'worker_statuses':
            $result = $conn->query("SELECT * FROM worker_statuses ORDER BY status_name");
            if (!$result) {
                throw new Exception("Database error: " . $conn->error . " - Table 'worker_statuses' may not exist");
            }
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            break;
            
        case 'experience_levels':
            // This table doesn't exist in our schema, return empty array
            $data = [];
            break;
            
        case 'recruitment_professions':
            // This table doesn't exist in our schema, return empty array
            $data = [];
            break;
            
        case 'system_config':
            $result = $conn->query("SELECT * FROM system_settings ORDER BY setting_key");
            if (!$result) {
                throw new Exception("Database error: " . $conn->error . " - Table 'system_settings' may not exist");
            }
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            break;
            
        default:
            throw new Exception('Invalid setting type: ' . $settingType);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $data
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving settings: ' . $e->getMessage(),
        'setting_type' => $settingType ?? 'unknown'
    ]);
}
?> 