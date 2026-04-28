<?php
/**
 * EN: Handles API endpoint/business logic in `api/hr/settings.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/hr/settings.php`.
 */
// Use control session when ?control=1 so control panel requests are authenticated
if (isset($_GET['control']) && (string)$_GET['control'] === '1') {
    session_name('ratib_control');
}
require_once __DIR__ . '/hr-api-bootstrap.inc.php';
// Disable caching for this API endpoint
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
ob_start();
// Disable error display to prevent HTML output in JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Load response.php - try root Utils first, then api/utils
$responsePath = __DIR__ . '/../../Utils/response.php';
if (!file_exists($responsePath)) {
    $responsePath = __DIR__ . '/../../api/utils/response.php';
}
if (!file_exists($responsePath)) {
    error_log('ERROR: response.php not found. Tried: ' . __DIR__ . '/../../Utils/response.php and ' . __DIR__ . '/../../api/utils/response.php');
    // Simple response function if Utils/response.php doesn't exist
    if (!function_exists('sendResponse')) {
        function sendResponse($data, $statusCode = 200) {
            http_response_code($statusCode);
            header('Content-Type: application/json');
            echo json_encode($data);
            exit;
        }
    }
} else {
    require_once $responsePath;
}

// Function to convert Arabic/Persian numerals to Western numerals and clean invalid characters
function toWesternNumerals($str) {
    if ($str === null || $str === '') return $str;
    $str = (string) $str;
    
    // Convert Arabic numerals
    $arabic = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
    $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    for ($i = 0; $i <= 9; $i++) {
        $str = str_replace($arabic[$i], (string)$i, $str);
        $str = str_replace($persian[$i], (string)$i, $str);
    }
    
    // Remove any non-numeric characters except decimal point and minus sign
    // This removes Greek letters (Λ, Γ, etc.) and other invalid characters
    $str = preg_replace('/[^\d.\-]/', '', $str);
    
    // Ensure only one decimal point
    $parts = explode('.', $str);
    if (count($parts) > 2) {
        $str = $parts[0] . '.' . implode('', array_slice($parts, 1));
    }
    
    return trim($str);
}

require_once __DIR__ . '/hr-connection.php';

try {
    hr_api_require_control_panel_auth();
    $conn = hr_api_get_connection();

    // Create HR settings table if it doesn't exist
    $tableCheck = $conn->query("SHOW TABLES LIKE 'hr_settings'");
    $hrSettingsTableExists = ($tableCheck !== false && $tableCheck->fetch(PDO::FETCH_NUM) !== false);
    if (!$hrSettingsTableExists) {
        $createTable = "CREATE TABLE IF NOT EXISTS hr_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT,
            description TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_setting_key (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $conn->exec($createTable);
        
        // Insert default settings
        $defaultSettings = [
            ['working_hours', '8', 'Default working hours per day'],
            ['overtime_rate', '1.5', 'Overtime rate multiplier'],
            ['payroll_day', '25', 'Day of month for payroll processing'],
            ['tax_rate', '15', 'Default tax rate percentage']
        ];
        
        $stmt = $conn->prepare("INSERT INTO hr_settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
        foreach ($defaultSettings as $setting) {
            $stmt->execute($setting);
        }
    }

    $action = $_GET['action'] ?? 'get';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // Handle POST/PUT for saving settings
    if ($method === 'POST' || $method === 'PUT' || $action === 'save') {
        if (!hr_api_is_control_request()) {
            $helper = __DIR__ . '/../core/api-permission-helper.php';
            if (!function_exists('enforceApiPermission') && is_readable($helper)) {
                require_once $helper;
            }
            if (function_exists('enforceApiPermission')) {
                enforceApiPermission('hr', 'update');
            }
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input && $method === 'POST') {
            $input = $_POST;
        }
        
        if (empty($input)) {
            sendResponse([
                'success' => false,
                'message' => 'No data provided'
            ], 400);
        }
        
        $success = true;
        $errors = [];
        
        // Only allow known HR setting keys
        $allowedKeys = ['working_hours', 'overtime_rate', 'payroll_day', 'tax_rate'];
        $defaults = ['working_hours' => '8', 'overtime_rate' => '1.5', 'payroll_day' => '25', 'tax_rate' => '15'];
        
        foreach ($allowedKeys as $key) {
            if (!isset($input[$key])) {
                continue;
            }
            try {
                $westernValue = toWesternNumerals($input[$key]);
                // If conversion produced empty/invalid, use default
                if ($westernValue === '' || $westernValue === null) {
                    $westernValue = $defaults[$key] ?? '';
                }
                
                $stmt = $conn->prepare("INSERT INTO hr_settings (setting_key, setting_value) VALUES (?, ?) 
                                      ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP");
                $stmt->execute([$key, $westernValue]);
            } catch (Exception $e) {
                $success = false;
                $errors[] = "Failed to save {$key}: " . $e->getMessage();
            }
        }
        
        if ($success) {
            ob_clean();
            sendResponse([
                'success' => true,
                'message' => 'Settings saved successfully'
            ]);
        } else {
            ob_clean();
            sendResponse([
                'success' => false,
                'message' => 'Some settings failed to save',
                'errors' => $errors
            ], 500);
        }
    }
    
    // Handle GET for retrieving settings
    if ($action === 'get' || $action === 'list') {
        $stmt = $conn->query("SELECT setting_key, setting_value, description FROM hr_settings");
        $settings = [];
        
        $defaults = ['working_hours' => '8', 'overtime_rate' => '1.5', 'payroll_day' => '25', 'tax_rate' => '15'];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Convert Arabic/Persian numerals to Western numerals before returning
            $convertedValue = toWesternNumerals($row['setting_value']);
            // Never return empty for known numeric settings
            if ($convertedValue === '' || $convertedValue === null) {
                $convertedValue = $defaults[$row['setting_key']] ?? $row['setting_value'];
            }
            
            // Also update the database if the value was converted (to prevent future issues)
            if ($convertedValue !== $row['setting_value']) {
                try {
                    $updateStmt = $conn->prepare("UPDATE hr_settings SET setting_value = ? WHERE setting_key = ?");
                    $updateStmt->execute([$convertedValue, $row['setting_key']]);
                } catch (Exception $e) {
                    error_log("Failed to update converted value for {$row['setting_key']}: " . $e->getMessage());
                }
            }
            
            $settings[$row['setting_key']] = $convertedValue;
        }
        
        ob_clean();
        sendResponse([
            'success' => true,
            'data' => $settings
        ]);
    }
    
    // Handle migration action to convert all existing Arabic numerals in database
    if ($action === 'migrate-numerals') {
        // Don't require permission check for migration - it's a maintenance operation
        // that runs automatically
        
        try {
            $stmt = $conn->query("SELECT id, setting_key, setting_value FROM hr_settings");
            $updated = 0;
            $errors = [];
            $defaults = ['working_hours' => '8', 'overtime_rate' => '1.5', 'payroll_day' => '25', 'tax_rate' => '15'];
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $convertedValue = toWesternNumerals($row['setting_value']);
                if ($convertedValue === '' || $convertedValue === null) {
                    $convertedValue = $defaults[$row['setting_key']] ?? '0';
                }
                
                // Update if conversion changed the value or value was empty/invalid
                if ($convertedValue !== $row['setting_value']) {
                    try {
                        $updateStmt = $conn->prepare("UPDATE hr_settings SET setting_value = ? WHERE id = ?");
                        $updateStmt->execute([$convertedValue, $row['id']]);
                        $updated++;
                    } catch (Exception $e) {
                        $errors[] = "Failed to update {$row['setting_key']}: " . $e->getMessage();
                    }
                }
            }
            
            ob_clean();
            sendResponse([
                'success' => true,
                'message' => "Migration completed. Updated {$updated} setting(s).",
                'updated' => $updated,
                'errors' => $errors
            ]);
        } catch (Exception $e) {
            ob_clean();
            sendResponse([
                'success' => false,
                'message' => 'Migration failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    ob_clean();
    sendResponse([
        'success' => false,
        'message' => 'Invalid action'
    ], 400);
    
} catch (Exception $e) {
    // Log the error for debugging
    error_log("HR Settings API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Return proper JSON error response
    ob_clean();
    sendResponse(['success' => false, 'message' => 'An error occurred while processing your request'], 500);
} catch (Error $e) {
    // Handle PHP fatal errors
    error_log("HR Settings API Fatal Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    ob_clean();
    sendResponse(['success' => false, 'message' => 'A system error occurred'], 500);
} catch (Throwable $e) {
    error_log("HR Settings API Throwable Error: " . $e->getMessage());
    ob_clean();
    sendResponse(['success' => false, 'message' => 'A system error occurred'], 500);
}
