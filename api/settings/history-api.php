<?php
/**
 * EN: Handles API endpoint/business logic in `api/settings/history-api.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/settings/history-api.php`.
 */
// EN: History/activity API for settings changes with resilient JSON responses.
// AR: واجهة سجل النشاط لتغييرات الإعدادات مع استجابات JSON متينة.
// History/Activity Log API for System Settings
// Only declare sendResponse if it doesn't already exist (to avoid redeclaration)
// EN: Define shared-safe responder only when not already declared.
// AR: تعريف دالة الاستجابة فقط إذا لم يتم تعريفها مسبقاً.
if (!function_exists('sendResponse')) {
    function sendResponse($data, $code = 200) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($code);
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $json = json_encode(["success"=>false,"message"=>"JSON encoding failed: ".json_last_error_msg()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        echo $json;
        exit;
    }
}

// Only start output buffering if not already started
// EN: Enable output buffering (idempotent) to suppress stray output.
// AR: تفعيل التخزين المؤقت للمخرجات بشكل آمن لمنع أي إخراج غير مقصود.
if (ob_get_level() === 0) {
    ob_start();
}

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(0);

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    return true;
}, E_ALL);

// Only set header if not already set
if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
}

try {
    @require_once '../../config/database.php';
    if (!class_exists('Database')) {
        sendResponse(["success"=>false,"message"=>"Database class not found"],500);
    }
} catch (Exception $e) {
    sendResponse(["success"=>false,"message"=>"Config error: ".$e->getMessage()],500);
}

// EN: Service class for persisting/retrieving settings history and audit stats.
// AR: فئة خدمة لحفظ واسترجاع تاريخ الإعدادات وإحصاءات التدقيق.
class HistoryAPI {
    private $conn;
    private static $initialized = false;
    
    public function __construct() {
        // Suppress any output during construction
        ob_start();
        try {
            $db = new Database();
            $this->conn = $db->getConnection();
            
            if (!$this->conn) {
                error_log("❌ HistoryAPI: Database connection is null");
                $this->conn = null;
                ob_end_clean();
                return;
            }
            
            error_log("✅ HistoryAPI: Database connection established");
            
            if (!self::$initialized) {
                try {
                    $this->ensureHistoryTable();
                    error_log("✅ HistoryAPI: History table ensured");
                } catch (Exception $e) {
                    // Table creation failed, but continue anyway
                    error_log("⚠️ History table creation warning: " . $e->getMessage());
                }
                self::$initialized = true;
            }
        } catch (Exception $e) {
            // Log error but don't break
            error_log("❌ HistoryAPI constructor error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            $this->conn = null; // Mark as failed
        } catch (Throwable $e) {
            error_log("❌ HistoryAPI constructor fatal: " . $e->getMessage());
            $this->conn = null;
        }
        // Discard any output
        ob_end_clean();
    }
    
    private function ensureHistoryTable() {
        try {
            if (!$this->conn) {
                return; // No connection available
            }
            
            $sql = "CREATE TABLE IF NOT EXISTS `system_settings_history` (
                `id` INT PRIMARY KEY AUTO_INCREMENT,
                `table_name` VARCHAR(100) NOT NULL,
                `record_id` VARCHAR(50) NOT NULL,
                `action` ENUM('create', 'update', 'delete') NOT NULL,
                `user_id` INT,
                `user_name` VARCHAR(100),
                `old_data` JSON NULL,
                `new_data` JSON NULL,
                `changed_fields` JSON NULL,
                `ip_address` VARCHAR(45),
                `user_agent` TEXT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_table (table_name),
                INDEX idx_record (table_name, record_id),
                INDEX idx_created (created_at),
                INDEX idx_action (action)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $this->conn->exec($sql);
        } catch (Exception $e) {
            // Table might already exist
        }
    }
    
    // EN: Persist a single history event with optional before/after payloads.
    // AR: حفظ حدث تاريخ واحد مع بيانات اختيارية قبل/بعد التعديل.
    public function logHistory($tableName, $recordId, $action, $oldData = null, $newData = null) {
        try {
            if (!$this->conn) {
                error_log("HistoryAPI::logHistory: No database connection");
                return false; // No connection available
            }
            
            // Ensure table exists
            try {
                $this->ensureHistoryTable();
            } catch (Exception $e) {
                error_log("History table creation failed in logHistory: " . $e->getMessage());
                return false;
            }
            
            // Start session if needed (silently)
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
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
            
            $sql = "INSERT INTO `system_settings_history` 
                    (table_name, record_id, action, user_id, user_name, old_data, new_data, changed_fields, ip_address, user_agent) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                $error = $this->conn->errorInfo();
                error_log("History prepare failed: " . ($error[2] ?? 'Unknown error'));
                return false;
            }
            
            $result = $stmt->execute([
                $tableName,
                (string)$recordId,
                $action,
                $userId,
                $userName,
                $oldData ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null,
                $newData ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null,
                $changedFields ? json_encode($changedFields, JSON_UNESCAPED_UNICODE) : null,
                $ipAddress,
                $userAgent
            ]);
            
            if (!$result) {
                $error = $stmt->errorInfo();
                error_log("History execute failed: " . ($error[2] ?? 'Unknown error'));
                return false;
            }
            
            error_log("History logged successfully: table={$tableName}, record={$recordId}, action={$action}");
            return true;
        } catch (Exception $e) {
            error_log("History logging failed: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            return false;
        }
    }
    
    // EN: Query history stream with optional table/record filters and capped limit.
    // AR: استعلام سجل التاريخ مع فلاتر اختيارية للجدول/السجل وحد أقصى للنتائج.
    public function getHistory($tableName = null, $recordId = null, $limit = 100) {
        try {
            if (!$this->conn) {
                error_log("HistoryAPI::getHistory: No database connection");
                return array(); // No connection available
            }
            
            // Ensure table exists
            try {
                $this->ensureHistoryTable();
            } catch (Exception $e) {
                error_log("History table creation failed in getHistory: " . $e->getMessage());
                return array();
            }
            
            // Check if history table exists
            $checkTable = $this->conn->query("SHOW TABLES LIKE 'system_settings_history'");
            if ($checkTable->rowCount() === 0) {
                error_log("HistoryAPI::getHistory: Table does not exist");
                return array(); // Table doesn't exist yet, return empty array
            }
            
            // Count total records for debugging
            $countSql = "SELECT COUNT(*) as total FROM `system_settings_history`";
            $countStmt = $this->conn->query($countSql);
            $totalCount = $countStmt ? $countStmt->fetch(PDO::FETCH_ASSOC)['total'] : 0;
            error_log("HistoryAPI::getHistory: Total history records in DB: {$totalCount}");
            
            $sql = "SELECT * FROM `system_settings_history` WHERE 1=1";
            $params = array();
            
            if ($tableName) {
                $sql .= " AND table_name = ?";
                $params[] = $tableName;
                error_log("HistoryAPI::getHistory: Filtering by table_name: {$tableName}");
            }
            
            if ($recordId) {
                $sql .= " AND record_id = ?";
                $params[] = (string)$recordId;
                error_log("HistoryAPI::getHistory: Filtering by record_id: {$recordId}");
            }
            
            // LIMIT must be a literal integer, not a bound parameter in MariaDB/MySQL
            $limitInt = (int)$limit;
            $sql .= " ORDER BY created_at DESC LIMIT {$limitInt}";
            
            error_log("HistoryAPI::getHistory: Executing SQL: {$sql} with " . count($params) . " params");
            
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                $error = $this->conn->errorInfo();
                error_log("History prepare failed: " . ($error[2] ?? 'Unknown error'));
                return array();
            }
            
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("HistoryAPI::getHistory: Found " . count($results) . " records");
            
            // Decode JSON fields
            foreach ($results as &$row) {
                if (isset($row['old_data']) && $row['old_data']) {
                    $decoded = json_decode($row['old_data'], true);
                    $row['old_data'] = $decoded !== null ? $decoded : null;
                }
                if (isset($row['new_data']) && $row['new_data']) {
                    $decoded = json_decode($row['new_data'], true);
                    $row['new_data'] = $decoded !== null ? $decoded : null;
                }
                if (isset($row['changed_fields']) && $row['changed_fields']) {
                    $decoded = json_decode($row['changed_fields'], true);
                    $row['changed_fields'] = $decoded !== null ? $decoded : null;
                }
            }
            
            return $results;
        } catch (Exception $e) {
            error_log("History getHistory error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            return array();
        }
    }
    
    // Debug method to get total count
    public function getTotalHistoryCount() {
        try {
            if (!$this->conn) {
                return 0;
            }
            
            $checkTable = $this->conn->query("SHOW TABLES LIKE 'system_settings_history'");
            if ($checkTable->rowCount() === 0) {
                return 0;
            }
            
            $stmt = $this->conn->query("SELECT COUNT(*) as total FROM `system_settings_history`");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($result['total'] ?? 0);
        } catch (Exception $e) {
            error_log("getTotalHistoryCount error: " . $e->getMessage());
            return 0;
        }
    }
    
    public function getHistoryStats($tableName = null) {
        try {
            if (!$this->conn) {
                return array('total' => 0, 'creates' => 0, 'updates' => 0, 'deletes' => 0);
            }
            
            // Check if history table exists
            $checkTable = $this->conn->query("SHOW TABLES LIKE 'system_settings_history'");
            if ($checkTable->rowCount() === 0) {
                return array('total' => 0, 'creates' => 0, 'updates' => 0, 'deletes' => 0);
            }
            
            $sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN action = 'create' THEN 1 ELSE 0 END) as creates,
                SUM(CASE WHEN action = 'update' THEN 1 ELSE 0 END) as updates,
                SUM(CASE WHEN action = 'delete' THEN 1 ELSE 0 END) as deletes
                FROM `system_settings_history`";
            
            $params = array();
            if ($tableName) {
                $sql .= " WHERE table_name = ?";
                $params[] = $tableName;
            }
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result : array('total' => 0, 'creates' => 0, 'updates' => 0, 'deletes' => 0);
        } catch (Exception $e) {
            error_log("History getHistoryStats error: " . $e->getMessage());
            return array('total' => 0, 'creates' => 0, 'updates' => 0, 'deletes' => 0);
        }
    }
    
    public function deleteHistory($tableName = null, $days = null) {
        try {
            if (!$this->conn) {
                return 0; // No connection available
            }
            
            // Check if history table exists
            $checkTable = $this->conn->query("SHOW TABLES LIKE 'system_settings_history'");
            if ($checkTable->rowCount() === 0) {
                return 0;
            }
            
            $sql = "DELETE FROM `system_settings_history` WHERE 1=1";
            $params = array();
            
            if ($tableName) {
                $sql .= " AND table_name = ?";
                $params[] = $tableName;
            }
            
            if ($days) {
                $sql .= " AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
                $params[] = (int)$days;
            }
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("History deleteHistory error: " . $e->getMessage());
            return 0;
        }
    }
}

// Only handle requests if this file is being accessed directly (not included/required)
// Check if this script is the main script being executed
$isDirectAccess = (
    (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) === 'history-api.php') ||
    (isset($_SERVER['SCRIPT_NAME']) && basename($_SERVER['SCRIPT_NAME']) === 'history-api.php') ||
    (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'history-api.php') !== false)
);

if ($isDirectAccess) {
    // Handle requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') {
        // Start session silently
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        
        try {
            $api = new HistoryAPI();
            $action = $_REQUEST['action'] ?? '';
            
            switch ($action) {
                case 'get_history':
                    $tableName = $_REQUEST['table'] ?? null;
                    $recordId = $_REQUEST['record_id'] ?? null;
                    $limit = isset($_REQUEST['limit']) ? (int)$_REQUEST['limit'] : 100;
                    
                    // Debug: Check total records in table
                    try {
                        if ($api && method_exists($api, 'getTotalHistoryCount')) {
                            $totalCount = $api->getTotalHistoryCount();
                            error_log("📊 Total history records in DB: {$totalCount}");
                        }
                    } catch (Exception $e) {
                        error_log("⚠️ Could not get total count: " . $e->getMessage());
                    }
                    
                    $history = $api->getHistory($tableName, $recordId, $limit);
                    
                    error_log("📋 Returning " . count($history) . " history records for table={$tableName}, record_id={$recordId}");
                    
                    sendResponse(["success"=>true, "data"=>$history]);
                    break;
                    
                case 'get_stats':
                    $tableName = $_REQUEST['table'] ?? null;
                    $stats = $api->getHistoryStats($tableName);
                    sendResponse(["success"=>true, "data"=>$stats]);
                    break;
                    
                case 'delete_old':
                    $tableName = $_REQUEST['table'] ?? null;
                    $days = isset($_REQUEST['days']) ? (int)$_REQUEST['days'] : 90;
                    $deleted = $api->deleteHistory($tableName, $days);
                    sendResponse(["success"=>true, "message"=>"Deleted {$deleted} history records", "deleted"=>$deleted]);
                    break;
                    
                default:
                    sendResponse(["success"=>false, "message"=>"Invalid action. Available: get_history, get_stats, delete_old"], 400);
            }
        } catch (Exception $e) {
            sendResponse(["success"=>false, "message"=>$e->getMessage()], 500);
        } catch (Throwable $e) {
            sendResponse(["success"=>false, "message"=>"Fatal error: " . $e->getMessage()], 500);
        }
    } else {
        sendResponse(["success"=>false, "message"=>"Method not allowed"], 405);
    }
}

