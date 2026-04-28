<?php
/**
 * EN: Handles API endpoint/business logic in `api/core/module-history-api.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/core/module-history-api.php`.
 */
/**
 * Module History API - Retrieve history for individual modules
 * Supports: agents, workers, cases, hr, subagents, etc.
 */

ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

if (!function_exists('sendResponse')) {
    function sendResponse($success, $data = null, $message = '') {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        $response = array(
            'success' => $success,
            'data' => $data,
            'message' => $message
        );
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

$isDirectAccess = (
    basename($_SERVER['PHP_SELF']) === 'module-history-api.php' ||
    basename($_SERVER['SCRIPT_NAME']) === 'module-history-api.php' ||
    strpos($_SERVER['REQUEST_URI'], 'module-history-api.php') !== false
);

if (!$isDirectAccess) {
    return;
}

try {
    // Use the Database class from api/core, not config
    require_once __DIR__ . '/Database.php';
    
    if (!class_exists('Database')) {
        sendResponse(false, null, 'Database class not found');
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    if (!$conn) {
        sendResponse(false, null, 'Database connection failed');
    }
    
    // Get module and action from request
    $module = isset($_GET['module']) ? trim($_GET['module']) : '';
    $action = isset($_GET['action']) ? trim($_GET['action']) : '';
    
    if (empty($module)) {
        sendResponse(false, null, 'Module name is required');
    }
    
    if (empty($action)) {
        sendResponse(false, null, 'Action is required');
    }
    
    // Ensure global_history table exists
    try {
        // Check if table exists first
        $tableCheck = $conn->query("SHOW TABLES LIKE 'global_history'");
        if ($tableCheck->rowCount() == 0) {
            // Table doesn't exist, create it
            $createTableSql = "CREATE TABLE IF NOT EXISTS `global_history` (
                `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `table_name` VARCHAR(100) NOT NULL,
                `record_id` VARCHAR(100) NOT NULL,
                `action` ENUM('create', 'update', 'delete') NOT NULL,
                `module` VARCHAR(50) NOT NULL DEFAULT 'general',
                `user_id` INT(11) NULL,
                `user_name` VARCHAR(100) NULL,
                `old_data` JSON NULL,
                `new_data` JSON NULL,
                `changed_fields` JSON NULL,
                `ip_address` VARCHAR(45) NULL,
                `user_agent` TEXT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_table_record` (`table_name`, `record_id`),
                INDEX `idx_module` (`module`),
                INDEX `idx_action` (`action`),
                INDEX `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $conn->exec($createTableSql);
            error_log("ModuleHistory API: global_history table created successfully");
        }
    } catch (PDOException $e) {
        // Table might already exist or there's a permission issue
        error_log("ModuleHistory API: Table creation PDO error - " . $e->getMessage());
    } catch (Exception $e) {
        error_log("ModuleHistory API: Table creation error - " . $e->getMessage());
    }

    // Auth: session started via Database->config->load.php
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $isControl = !empty($_SESSION['control_logged_in']);
    $isAppUser = function_exists('ratib_program_session_is_valid_user') && ratib_program_session_is_valid_user();
    if (!$isControl && !$isAppUser) {
        sendResponse(false, null, 'Unauthorized');
    }
    
    switch (strtolower($action)) {
        case 'get_history':
            // Get history for specific module
            $tableName = isset($_GET['table']) ? trim($_GET['table']) : $module;
            $recordId = isset($_GET['record_id']) ? trim($_GET['record_id']) : null;
            $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            
            if ($limit < 1 || $limit > 50000) $limit = 100; // Increased max limit to 50000
            if ($offset < 0) $offset = 0;
            
            try {
                // Check if table exists first
                $tableCheck = $conn->query("SHOW TABLES LIKE 'global_history'");
                if ($tableCheck->rowCount() == 0) {
                    // Table doesn't exist, return empty array
                    sendResponse(true, [], 'History table does not exist yet');
                }
                
                $sql = "SELECT * FROM `global_history` WHERE module = ?";
                $params = array($module);
                
                if ($tableName && $tableName !== $module) {
                    $sql .= " AND table_name = ?";
                    $params[] = $tableName;
                }
                
                if ($recordId) {
                    $sql .= " AND record_id = ?";
                    $params[] = $recordId;
                }
                
                if ($userId) {
                    $sql .= " AND user_id = ?";
                    $params[] = $userId;
                }
                
                $sql .= " ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}";
                
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    error_log("ModuleHistory API: Prepare failed - " . json_encode($conn->errorInfo()));
                    sendResponse(false, null, 'Database prepare error');
                }
                
                // Debug logging
                error_log("ModuleHistory API: Querying history for module='$module', tableName='$tableName', SQL='$sql', params=" . json_encode($params));
                
                $stmt->execute($params);
                $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                error_log("ModuleHistory API: Found " . count($records) . " records for module='$module'");
                
                // Decode JSON fields
                foreach ($records as &$record) {
                    if (!empty($record['old_data'])) {
                        $record['old_data'] = json_decode($record['old_data'], true);
                    }
                    if (!empty($record['new_data'])) {
                        $record['new_data'] = json_decode($record['new_data'], true);
                    }
                    if (!empty($record['changed_fields'])) {
                        $record['changed_fields'] = json_decode($record['changed_fields'], true);
                    }
                }
                
                sendResponse(true, $records, 'History retrieved successfully');
                
            } catch (PDOException $e) {
                error_log("ModuleHistory API get_history PDO error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
                sendResponse(false, null, 'Database error: ' . $e->getMessage());
            } catch (Exception $e) {
                error_log("ModuleHistory API get_history error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
                sendResponse(false, null, 'Failed to retrieve history: ' . $e->getMessage());
            }
            break;
            
        case 'get_stats':
            // Get history statistics for module
            try {
                // Check if table exists first
                $tableCheck = $conn->query("SHOW TABLES LIKE 'global_history'");
                if ($tableCheck->rowCount() == 0) {
                    error_log("ModuleHistory API get_stats: global_history table does not exist for module='$module'");
                    // Table doesn't exist, return zeros
                    sendResponse(true, [
                        'total' => 0,
                        'creates' => 0,
                        'updates' => 0,
                        'deletes' => 0,
                        'today' => 0
                    ], 'Statistics retrieved successfully');
                }
                
                $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN action = 'create' THEN 1 ELSE 0 END) as creates,
                    SUM(CASE WHEN action = 'update' THEN 1 ELSE 0 END) as updates,
                    SUM(CASE WHEN action = 'delete' THEN 1 ELSE 0 END) as deletes,
                    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today
                FROM `global_history` WHERE module = ?";
                
                error_log("ModuleHistory API get_stats: Querying stats for module='$module', SQL='$sql'");
                
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    error_log("ModuleHistory API: Stats prepare failed - " . json_encode($conn->errorInfo()));
                    sendResponse(false, null, 'Database prepare error');
                }
                
                $stmt->execute(array($module));
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                
                error_log("ModuleHistory API get_stats: Stats for module='$module' - " . json_encode($stats));
                
                if (!$stats) {
                    $stats = array(
                        'total' => 0,
                        'creates' => 0,
                        'updates' => 0,
                        'deletes' => 0,
                        'today' => 0
                    );
                }
                
                $stats = array_map('intval', $stats);
                
                sendResponse(true, $stats, 'Statistics retrieved successfully');
                
            } catch (PDOException $e) {
                error_log("ModuleHistory API get_stats PDO error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
                sendResponse(false, null, 'Database error: ' . $e->getMessage());
            } catch (Exception $e) {
                error_log("ModuleHistory API get_stats error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
                sendResponse(false, null, 'Failed to retrieve statistics: ' . $e->getMessage());
            }
            break;
            
        default:
            sendResponse(false, null, 'Invalid action');
    }
    
} catch (PDOException $e) {
    error_log("ModuleHistory API PDO error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    sendResponse(false, null, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("ModuleHistory API error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    sendResponse(false, null, 'An error occurred: ' . $e->getMessage());
} catch (Throwable $e) {
    error_log("ModuleHistory API fatal error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    error_log("ModuleHistory API fatal error trace: " . $e->getTraceAsString());
    sendResponse(false, null, 'A fatal error occurred: ' . $e->getMessage());
}

