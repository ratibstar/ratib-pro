<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/update_system_setting.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/update_system_setting.php`.
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
    $action = $_POST['action'] ?? '';
    
    if (empty($settingType)) {
        throw new Exception('Setting type is required');
    }
    
    if (empty($action)) {
        throw new Exception('Action is required');
    }
    
    // Log the request for debugging
    error_log("update_system_setting.php called with setting_type: " . $settingType . ", action: " . $action);
    
    switch ($action) {
        case 'update_office_manager':
            // Update office manager data
            $officeName = $_POST['office_name'] ?? '';
            $managerName = $_POST['manager_name'] ?? '';
            $contactNumber = $_POST['contact_number'] ?? '';
            $emailAddress = $_POST['email_address'] ?? '';
            $address = $_POST['address'] ?? '';
            
            // Check if record exists
            $checkResult = $conn->query("SELECT COUNT(*) as count FROM office_manager_data");
            $exists = $checkResult && $checkResult->fetch_assoc()['count'] > 0;
            
            if ($exists) {
                // Update existing record
                $stmt = $conn->prepare("UPDATE office_manager_data SET office_name = ?, manager_name = ?, contact_number = ?, email_address = ?, address = ?, updated_at = CURRENT_TIMESTAMP");
                $stmt->bind_param("sssss", $officeName, $managerName, $contactNumber, $emailAddress, $address);
            } else {
                // Insert new record
                $stmt = $conn->prepare("INSERT INTO office_manager_data (office_name, manager_name, contact_number, email_address, address) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $officeName, $managerName, $contactNumber, $emailAddress, $address);
            }
            
            if (!$stmt->execute()) {
                throw new Exception("Database error: " . $stmt->error);
            }
            
            $data = [
                'office_name' => $officeName,
                'manager_name' => $managerName,
                'contact_number' => $contactNumber,
                'email_address' => $emailAddress,
                'address' => $address
            ];
            break;
            
        case 'add_item':
            // Add new item to a settings table
            $tableName = '';
            $columns = [];
            $values = [];
            $types = '';
            
            switch ($settingType) {
                case 'visa_types':
                    $tableName = 'visa_types';
                    $columns = ['visa_name', 'visa_description', 'processing_time', 'requirements', 'fees'];
                    $values = [
                        $_POST['visa_name'] ?? '',
                        $_POST['visa_description'] ?? '',
                        $_POST['processing_time'] ?? '',
                        $_POST['requirements'] ?? '',
                        $_POST['fees'] ?? 0.00
                    ];
                    $types = 'ssssd';
                    break;
                    
                case 'recruitment_countries':
                    $tableName = 'recruitment_countries';
                    $columns = ['country_name', 'country_description', 'country_code'];
                    $values = [
                        $_POST['country_name'] ?? '',
                        $_POST['country_description'] ?? '',
                        $_POST['country_code'] ?? ''
                    ];
                    $types = 'sss';
                    break;
                    
                case 'job_categories':
                    $tableName = 'job_categories';
                    $columns = ['category_name', 'category_description', 'salary_range', 'requirements'];
                    $values = [
                        $_POST['category_name'] ?? '',
                        $_POST['category_description'] ?? '',
                        $_POST['salary_range'] ?? '',
                        $_POST['requirements'] ?? ''
                    ];
                    $types = 'ssss';
                    break;
                    
                case 'age_specifications':
                    $tableName = 'age_specifications';
                    $columns = ['age_range', 'age_description', 'min_age', 'max_age'];
                    $values = [
                        $_POST['age_range'] ?? '',
                        $_POST['age_description'] ?? '',
                        $_POST['min_age'] ?? 0,
                        $_POST['max_age'] ?? 0
                    ];
                    $types = 'ssii';
                    break;
                    
                case 'appearance_specifications':
                    $tableName = 'appearance_specifications';
                    $columns = ['spec_name', 'spec_description', 'requirements'];
                    $values = [
                        $_POST['spec_name'] ?? '',
                        $_POST['spec_description'] ?? '',
                        $_POST['requirements'] ?? ''
                    ];
                    $types = 'sss';
                    break;
                    
                case 'status_specifications':
                    $tableName = 'status_specifications';
                    $columns = ['status_name', 'status_description', 'color_code'];
                    $values = [
                        $_POST['status_name'] ?? '',
                        $_POST['status_description'] ?? '',
                        $_POST['color_code'] ?? ''
                    ];
                    $types = 'sss';
                    break;
                    
                case 'request_statuses':
                    $tableName = 'request_statuses';
                    $columns = ['status_name', 'status_description', 'color_code'];
                    $values = [
                        $_POST['status_name'] ?? '',
                        $_POST['status_description'] ?? '',
                        $_POST['color_code'] ?? ''
                    ];
                    $types = 'sss';
                    break;
                    
                case 'arrival_agencies':
                    $tableName = 'arrival_agencies';
                    $columns = ['agency_name', 'agency_description', 'contact_info'];
                    $values = [
                        $_POST['agency_name'] ?? '',
                        $_POST['agency_description'] ?? '',
                        $_POST['contact_info'] ?? ''
                    ];
                    $types = 'sss';
                    break;
                    
                case 'arrival_stations':
                    $tableName = 'arrival_stations';
                    $columns = ['station_name', 'station_description', 'location'];
                    $values = [
                        $_POST['station_name'] ?? '',
                        $_POST['station_description'] ?? '',
                        $_POST['location'] ?? ''
                    ];
                    $types = 'sss';
                    break;
                    
                case 'worker_statuses':
                    $tableName = 'worker_statuses';
                    $columns = ['status_name', 'status_description', 'color_code'];
                    $values = [
                        $_POST['status_name'] ?? '',
                        $_POST['status_description'] ?? '',
                        $_POST['color_code'] ?? ''
                    ];
                    $types = 'sss';
                    break;
                    
                case 'system_config':
                    $tableName = 'system_settings';
                    $columns = ['setting_key', 'setting_value', 'setting_type', 'description'];
                    $values = [
                        $_POST['setting_key'] ?? '',
                        $_POST['setting_value'] ?? '',
                        $_POST['setting_type_field'] ?? 'string',
                        $_POST['description'] ?? ''
                    ];
                    $types = 'ssss';
                    break;
                    
                default:
                    throw new Exception('Invalid setting type for add_item: ' . $settingType);
            }
            
            if (!empty($tableName)) {
                $columnList = implode(', ', $columns);
                $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                
                $stmt = $conn->prepare("INSERT INTO $tableName ($columnList) VALUES ($placeholders)");
                $stmt->bind_param($types, ...$values);
                
                if (!$stmt->execute()) {
                    throw new Exception("Database error: " . $stmt->error);
                }
                
                $data = ['id' => $conn->insert_id];
            }
            break;
            
        case 'update_item':
            // Update existing item
            $itemId = $_POST['item_id'] ?? '';
            if (empty($itemId)) {
                throw new Exception('Item ID is required for update');
            }
            
            $tableName = '';
            $columns = [];
            $values = [];
            $types = '';
            
            switch ($settingType) {
                case 'visa_types':
                    $tableName = 'visa_types';
                    $columns = ['visa_name', 'visa_description', 'processing_time', 'requirements', 'fees'];
                    $values = [
                        $_POST['visa_name'] ?? '',
                        $_POST['visa_description'] ?? '',
                        $_POST['processing_time'] ?? '',
                        $_POST['requirements'] ?? '',
                        $_POST['fees'] ?? 0.00,
                        $itemId
                    ];
                    $types = 'ssssdi';
                    break;
                    
                case 'recruitment_countries':
                    $tableName = 'recruitment_countries';
                    $columns = ['country_name', 'country_description', 'country_code'];
                    $values = [
                        $_POST['country_name'] ?? '',
                        $_POST['country_description'] ?? '',
                        $_POST['country_code'] ?? '',
                        $itemId
                    ];
                    $types = 'sssi';
                    break;
                    
                case 'job_categories':
                    $tableName = 'job_categories';
                    $columns = ['category_name', 'category_description', 'salary_range', 'requirements'];
                    $values = [
                        $_POST['category_name'] ?? '',
                        $_POST['category_description'] ?? '',
                        $_POST['salary_range'] ?? '',
                        $_POST['requirements'] ?? '',
                        $itemId
                    ];
                    $types = 'ssssi';
                    break;
                    
                case 'age_specifications':
                    $tableName = 'age_specifications';
                    $columns = ['age_range', 'age_description', 'min_age', 'max_age'];
                    $values = [
                        $_POST['age_range'] ?? '',
                        $_POST['age_description'] ?? '',
                        $_POST['min_age'] ?? 0,
                        $_POST['max_age'] ?? 0,
                        $itemId
                    ];
                    $types = 'ssiii';
                    break;
                    
                case 'appearance_specifications':
                    $tableName = 'appearance_specifications';
                    $columns = ['spec_name', 'spec_description', 'requirements'];
                    $values = [
                        $_POST['spec_name'] ?? '',
                        $_POST['spec_description'] ?? '',
                        $_POST['requirements'] ?? '',
                        $itemId
                    ];
                    $types = 'sssi';
                    break;
                    
                case 'status_specifications':
                    $tableName = 'status_specifications';
                    $columns = ['status_name', 'status_description', 'color_code'];
                    $values = [
                        $_POST['status_name'] ?? '',
                        $_POST['status_description'] ?? '',
                        $_POST['color_code'] ?? '',
                        $itemId
                    ];
                    $types = 'sssi';
                    break;
                    
                case 'request_statuses':
                    $tableName = 'request_statuses';
                    $columns = ['status_name', 'status_description', 'color_code'];
                    $values = [
                        $_POST['status_name'] ?? '',
                        $_POST['status_description'] ?? '',
                        $_POST['color_code'] ?? '',
                        $itemId
                    ];
                    $types = 'sssi';
                    break;
                    
                case 'arrival_agencies':
                    $tableName = 'arrival_agencies';
                    $columns = ['agency_name', 'agency_description', 'contact_info'];
                    $values = [
                        $_POST['agency_name'] ?? '',
                        $_POST['agency_description'] ?? '',
                        $_POST['contact_info'] ?? '',
                        $itemId
                    ];
                    $types = 'sssi';
                    break;
                    
                case 'arrival_stations':
                    $tableName = 'arrival_stations';
                    $columns = ['station_name', 'station_description', 'location'];
                    $values = [
                        $_POST['station_name'] ?? '',
                        $_POST['station_description'] ?? '',
                        $_POST['location'] ?? '',
                        $itemId
                    ];
                    $types = 'sssi';
                    break;
                    
                case 'worker_statuses':
                    $tableName = 'worker_statuses';
                    $columns = ['status_name', 'status_description', 'color_code'];
                    $values = [
                        $_POST['status_name'] ?? '',
                        $_POST['status_description'] ?? '',
                        $_POST['color_code'] ?? '',
                        $itemId
                    ];
                    $types = 'sssi';
                    break;
                    
                case 'system_config':
                    $tableName = 'system_settings';
                    $columns = ['setting_key', 'setting_value', 'setting_type', 'description'];
                    $values = [
                        $_POST['setting_key'] ?? '',
                        $_POST['setting_value'] ?? '',
                        $_POST['setting_type_field'] ?? 'string',
                        $_POST['description'] ?? '',
                        $itemId
                    ];
                    $types = 'ssssi';
                    break;
                    
                default:
                    throw new Exception('Invalid setting type for update_item: ' . $settingType);
            }
            
            if (!empty($tableName)) {
                $setClause = implode(' = ?, ', $columns) . ' = ?';
                $stmt = $conn->prepare("UPDATE $tableName SET $setClause WHERE id = ?");
                $stmt->bind_param($types, ...$values);
                
                if (!$stmt->execute()) {
                    throw new Exception("Database error: " . $stmt->error);
                }
                
                $data = ['id' => $itemId];
            }
            break;
            
        case 'delete_item':
            // Delete item
            $itemId = $_POST['item_id'] ?? '';
            if (empty($itemId)) {
                throw new Exception('Item ID is required for delete');
            }
            
            $tableName = '';
            switch ($settingType) {
                case 'visa_types':
                    $tableName = 'visa_types';
                    break;
                case 'recruitment_countries':
                    $tableName = 'recruitment_countries';
                    break;
                case 'job_categories':
                    $tableName = 'job_categories';
                    break;
                case 'age_specifications':
                    $tableName = 'age_specifications';
                    break;
                case 'appearance_specifications':
                    $tableName = 'appearance_specifications';
                    break;
                case 'status_specifications':
                    $tableName = 'status_specifications';
                    break;
                case 'request_statuses':
                    $tableName = 'request_statuses';
                    break;
                case 'arrival_agencies':
                    $tableName = 'arrival_agencies';
                    break;
                case 'arrival_stations':
                    $tableName = 'arrival_stations';
                    break;
                case 'worker_statuses':
                    $tableName = 'worker_statuses';
                    break;
                case 'system_config':
                    $tableName = 'system_settings';
                    break;
                default:
                    throw new Exception('Invalid setting type for delete_item: ' . $settingType);
            }
            
            $stmt = $conn->prepare("DELETE FROM $tableName WHERE id = ?");
            $stmt->bind_param("i", $itemId);
            
            if (!$stmt->execute()) {
                throw new Exception("Database error: " . $stmt->error);
            }
            
            $data = ['id' => $itemId];
            break;
            
        default:
            throw new Exception('Invalid action: ' . $action);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Settings updated successfully',
        'data' => $data ?? []
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error updating settings: ' . $e->getMessage(),
        'setting_type' => $settingType ?? 'unknown',
        'action' => $action ?? 'unknown'
    ]);
}
?> 