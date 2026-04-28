<?php
/**
 * EN: Handles API endpoint/business logic in `api/core/global-history-helper.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/core/global-history-helper.php`.
 */
/**
 * Global History Helper - Logs all CRUD operations across the entire system
 * Include this file in any API that needs history tracking
 */

if (!function_exists('logGlobalHistory')) {
    function logGlobalHistory($tableName, $recordId, $action, $module = 'general', $oldData = null, $newData = null) {
        try {
            // Silently start session if not started
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            
            // Get user info
            $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
            $userName = isset($_SESSION['username']) ? $_SESSION['username'] : 'System';
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            // Calculate changed fields for updates
            $changedFields = null;
            if ($action === 'update' && $oldData && $newData) {
                $changedFields = array();
                foreach ($newData as $key => $newValue) {
                    $oldValue = $oldData[$key] ?? null;
                    if ($oldValue !== $newValue) {
                        $changedFields[$key] = array(
                            'old' => $oldValue,
                            'new' => $newValue
                        );
                    }
                }
            }
            
            // Get database connection - Database class should already be loaded
            // Don't require it again to avoid "class already in use" error
            if (!class_exists('Database')) {
                error_log("GlobalHistory: Database class not found - ensure Database is loaded before calling logGlobalHistory");
                return false;
            }
            
            // Get connection - try getInstance first, then fallback to new Database()
            $conn = null;
            try {
                // Check if getInstance method exists (api/core/Database.php)
                if (method_exists('Database', 'getInstance')) {
                    try {
                        $db = Database::getInstance();
                        $conn = $db->getConnection();
                        error_log("GlobalHistory: Using Database::getInstance() - connection " . ($conn ? "successful" : "failed"));
                    } catch (Exception $e) {
                        error_log("GlobalHistory: getInstance() failed, trying new Database() - " . $e->getMessage());
                        // Fallback to new Database()
                        $db = new Database();
                        $conn = $db->getConnection();
                        error_log("GlobalHistory: Using new Database() - connection " . ($conn ? "successful" : "failed"));
                    }
                } else {
                    // Use new Database() (config/database.php)
                    error_log("GlobalHistory: getInstance() not available, using new Database()");
                    $db = new Database();
                    $conn = $db->getConnection();
                    error_log("GlobalHistory: Using new Database() - connection " . ($conn ? "successful" : "failed"));
                }
            } catch (Exception $e) {
                error_log("GlobalHistory: Database connection error - " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
                return false;
            } catch (Error $e) {
                error_log("GlobalHistory: Database fatal error - " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
                return false;
            }
            
            if (!$conn) {
                error_log("GlobalHistory: Database connection failed");
                return false;
            }
            
            // Ensure global_history table exists
            try {
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
            } catch (Exception $e) {
                // Table might already exist, continue
                error_log("GlobalHistory: Table creation note - " . $e->getMessage());
            }
            
            // Insert history record
            $sql = "INSERT INTO `global_history` 
                    (table_name, record_id, action, module, user_id, user_name, old_data, new_data, changed_fields, ip_address, user_agent) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                error_log("GlobalHistory: Prepare failed - " . ($conn->errorInfo()[2] ?? 'Unknown error'));
                return false;
            }
            
            $result = $stmt->execute([
                $tableName,
                (string)$recordId,
                $action,
                $module,
                $userId,
                $userName,
                $oldData ? json_encode($oldData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                $newData ? json_encode($newData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                $changedFields ? json_encode($changedFields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                $ipAddress,
                $userAgent
            ]);
            
            if (!$result) {
                $error = $stmt->errorInfo();
                error_log("GlobalHistory: Execute failed - " . ($error[2] ?? 'Unknown error'));
                error_log("GlobalHistory: SQL was: " . $sql);
                error_log("GlobalHistory: Params were: " . json_encode([
                    'table' => $tableName,
                    'record_id' => (string)$recordId,
                    'action' => $action,
                    'module' => $module
                ]));
                return false;
            }
            
            error_log("✅ GlobalHistory: Successfully logged - Table: $tableName, Record: $recordId, Action: $action, Module: $module");
            return true;
        } catch (Exception $e) {
            error_log("GlobalHistory: Exception - " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            return false;
        } catch (Throwable $e) {
            error_log("GlobalHistory: Fatal - " . $e->getMessage());
            return false;
        }
    }
}

