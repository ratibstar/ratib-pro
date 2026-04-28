<?php
/**
 * EN: Handles API endpoint/business logic in `api/core/Database.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/core/Database.php`.
 */
// Single global Database class — core/Database.php forwards here to avoid "Cannot declare class Database" fatals
if (!class_exists('Database', false)) {
class Database {
    private static $instance = null;
    /** Fingerprint of DB target (main vs agency); when it changes, singleton is recreated so PDO matches mysqli/$GLOBALS['conn']. */
    private static $resolvedKey = null;
    private $connection = null;

    /**
     * Stable key for the active app database (tenant users table lives here in SINGLE_URL_MODE).
     */
    private static function resolveConnectionKey(): string
    {
        $a = $GLOBALS['agency_db'] ?? null;
        if (!empty($a) && is_array($a) && !empty($a['db'])) {
            $host = (string)($a['host'] ?? 'localhost');
            $port = (int)($a['port'] ?? 3306);
            $db = (string)$a['db'];
            $user = (string)($a['user'] ?? '');
            return 'agency:' . $host . ':' . $port . ':' . $db . ':' . $user;
        }
        if (!defined('DB_HOST')) {
            require_once __DIR__ . '/../../includes/config.php';
        }
        $h = defined('DB_HOST') ? DB_HOST : 'localhost';
        $p = defined('DB_PORT') ? (int)DB_PORT : 3306;
        $d = defined('DB_NAME') ? DB_NAME : '';
        $u = defined('DB_USER') ? DB_USER : '';
        return 'main:' . $h . ':' . $p . ':' . $d . ':' . $u;
    }

    private function __construct() {
        try {
            // Load config first so agency_db is set when in control panel or single-URL mode
            require_once __DIR__ . '/../../includes/config.php';
            // Use agency/country DB when set (control panel or single-URL mode)
            $agencyDb = $GLOBALS['agency_db'] ?? null;
            if (!empty($agencyDb) && is_array($agencyDb) && !empty($agencyDb['db'])) {
                $host = $agencyDb['host'] ?? 'localhost';
                $port = (int)($agencyDb['port'] ?? 3306);
                $db   = $agencyDb['db'];
                $user = $agencyDb['user'] ?? (defined('DB_USER') ? DB_USER : 'outratib_out');
                $pass = $agencyDb['pass'] ?? (defined('DB_PASS') ? DB_PASS : '');
                $dsn  = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
            } else {
                if (!defined('DB_HOST')) {
                    require_once __DIR__ . '/../../includes/config.php';
                }
                $host = defined('DB_HOST') ? DB_HOST : 'localhost';
                $db   = defined('DB_NAME') ? DB_NAME : 'outratib_out';
                $user = defined('DB_USER') ? DB_USER : 'outratib_out';
                $pass = defined('DB_PASS') ? DB_PASS : '';
                $dsn  = "mysql:host=$host;dbname=$db;charset=utf8mb4";
            }
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $this->connection = new PDO($dsn, $user, $pass, $options);
        } catch(PDOException $e) {
            throw new Exception("Connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        $key = self::resolveConnectionKey();
        if (self::$instance === null || self::$resolvedKey !== $key) {
            self::$instance = new Database();
            self::$resolvedKey = $key;
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    // Utility function to execute a query and fetch all results
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->handleError($e);
        }
    }

    // Utility function to execute a query and fetch a single row
    public function queryOne($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            if (!$stmt) {
                throw new Exception("Failed to prepare query: " . $sql);
            }
            $stmt->execute($params);
            $result = $stmt->fetch();
            if ($result === false && $stmt->errorCode() !== '00000') {
                $errorInfo = $stmt->errorInfo();
                throw new PDOException("Query execution failed: " . $errorInfo[2], (int)$errorInfo[0]);
            }
            return $result;
        } catch (PDOException $e) {
            $this->handleError($e);
        } catch (Exception $e) {
            error_log("queryOne error: " . $e->getMessage());
            throw $e;
        }
    }

    // Utility function to execute an insert/update/delete query
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $this->handleError($e);
        }
    }

    // Utility function to get last inserted ID
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }

    // Prevent cloning of the instance
    private function __clone() {}

    // Prevent unserializing of the instance
    public function __wakeup() {}

    // Get dashboard statistics
    public function getDashboardStats() {
        try {
            return [
                'agents' => [
                    'total' => $this->getCount('agents'),
                    'active' => $this->getCount('agents', 'active'),
                    'inactive' => $this->getCount('agents', 'inactive')
                ],
                'subagents' => [
                    'total' => $this->getCount('subagents'),
                    'active' => $this->getCount('subagents', 'active'),
                    'inactive' => $this->getCount('subagents', 'inactive')
                ],
                'workers' => [
                    'total' => $this->getCount('workers'),
                    'active' => $this->getCount('workers', 'active'),
                    'inactive' => $this->getCount('workers', 'inactive')
                ],
                'documents' => [
                    'total' => $this->getCount('documents')
                ],
                'notifications' => [
                    'new' => $this->getCount('notifications', null, 'is_read = 0')
                ]
            ];
        } catch (PDOException $e) {
            $this->handleError($e);
        }
    }

    private function getCount($table, $status = null, $customWhere = null) {
        try {
            $sql = "SELECT COUNT(*) as count FROM $table";

            if ($status) {
                $sql .= " WHERE status = :status";
            } else if ($customWhere) {
                $sql .= " WHERE $customWhere";
            }

            $stmt = $this->connection->prepare($sql);

            if ($status) {
                $stmt->bindParam(':status', $status);
            }

            $stmt->execute();
            return $stmt->fetch()['count'];
        } catch (PDOException $e) {
            $this->handleError($e);
        }
    }

    public function getAgents($filters = []) {
        try {
            $conditions = [];
            $params = [];

            if (!empty($filters['status'])) {
                $conditions[] = "status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['search'])) {
                $conditions[] = "(agent_name LIKE ? OR email LIKE ? OR contact_number LIKE ?)";
                $search = "%{$filters['search']}%";
                array_push($params, $search, $search, $search);
            }

            $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

            return $this->query("
                SELECT
                    id,
                    CONCAT('AG', LPAD(id, 4, '0')) as formatted_id,
                    agent_name,
                    email,
                    contact_number,
                    address,
                    status,
                    created_at,
                    updated_at
                FROM agents
                $whereClause
                ORDER BY id DESC
            ", $params);
        } catch (PDOException $e) {
            $this->handleError($e);
        }
    }

    public function getAgentById($id) {
        try {
            return $this->queryOne("
                SELECT
                    id,
                    formatted_id,
                    agent_name,
                    email,
                    contact_number,
                    city,
                    address,
                    status,
                    created_at,
                    updated_at
                FROM agents
                WHERE id = ?
            ", [$id]);
        } catch (PDOException $e) {
            $this->handleError($e);
        }
    }

    public function createAgent($data) {
        try {
            $sql = "INSERT INTO agents (
                agent_name, email, contact_number, city, address, status
            ) VALUES (
                :agent_name, :email, :contact_number, :city, :address, :status
            )";

            $this->execute($sql, [
                ':agent_name' => $data['full_name'],
                ':email' => $data['email'],
                ':contact_number' => $data['phone'],
                ':city' => $data['city'] ?? '',
                ':address' => $data['address'] ?? '',
                ':status' => $data['status'] ?? 'active'
            ]);

            $recordId = $this->lastInsertId();
            $newRecord = $this->getAgentById($recordId);

            // Log history
            $helperPath = __DIR__ . '/global-history-helper.php';
            if (file_exists($helperPath)) {
                require_once $helperPath;
                @logGlobalHistory('agents', $recordId, 'create', 'agents', null, $newRecord);
            } else {
                error_log("⚠️ global-history-helper.php not found at: $helperPath");
            }

            return $newRecord;
        } catch (PDOException $e) {
            $this->handleError($e);
        }
    }

    public function updateAgent($id, $data) {
        try {
            // Get old data for history
            $oldData = $this->getAgentById($id);

            $updates = [];
            $params = [];

            $fieldMappings = [
                'full_name' => 'agent_name',
                'phone' => 'contact_number'
            ];

            foreach (['full_name', 'email', 'phone', 'city', 'address', 'status'] as $field) {
                if (isset($data[$field])) {
                    $dbField = $fieldMappings[$field] ?? $field;
                    $updates[] = "$dbField = ?";
                    $params[] = $data[$field];
                }
            }

            if (empty($updates)) {
                throw new Exception('No fields to update');
            }

            $params[] = $id;

            $sql = "UPDATE agents SET " . implode(', ', $updates) .
                   ", updated_at = CURRENT_TIMESTAMP WHERE id = ?";

            $this->execute($sql, $params);
            $updatedRecord = $this->getAgentById($id);

            // Log history
            $helperPath = __DIR__ . '/global-history-helper.php';
            if (file_exists($helperPath)) {
                require_once $helperPath;
                @logGlobalHistory('agents', $id, 'update', 'agents', $oldData, $updatedRecord);
            } else {
                error_log("⚠️ global-history-helper.php not found at: $helperPath");
            }

            return $updatedRecord;
        } catch (PDOException $e) {
            $this->handleError($e);
        }
    }

    public function deleteAgent($id) {
        try {
            // Get data before deletion for history
            $deletedData = $this->getAgentById($id);

            $result = $this->execute(
                "DELETE FROM agents WHERE id = ?",
                [$id]
            );

            // Log history
            if ($deletedData) {
                $helperPath = __DIR__ . '/global-history-helper.php';
                if (file_exists($helperPath)) {
                    require_once $helperPath;
                    @logGlobalHistory('agents', $id, 'delete', 'agents', $deletedData, null);
                } else {
                    error_log("⚠️ global-history-helper.php not found at: $helperPath");
                }
            }

            return $result;
        } catch (PDOException $e) {
            $this->handleError($e);
        }
    }

    public function handleError($error) {
        $errorMsg = $error->getMessage();
        $errorCode = $error->getCode();
        error_log("Database Error: " . $errorMsg);
        error_log("Database Error Code: " . $errorCode);
        error_log("Stack trace: " . $error->getTraceAsString());
        // Include the actual error message for debugging, but sanitize it
        throw new Exception("Database error: " . $errorMsg);
    }

    public function getAgentStats() {
        try {
            $stats = [
                'total' => 0,
                'active' => 0,
                'inactive' => 0
            ];

            // Get total count
            $result = $this->queryOne("SELECT COUNT(*) as count FROM agents");
            if ($result && isset($result['count'])) {
                $stats['total'] = (int)$result['count'];
            }

            // Get active count
            $result = $this->queryOne("SELECT COUNT(*) as count FROM agents WHERE status = 'active'");
            if ($result && isset($result['count'])) {
                $stats['active'] = (int)$result['count'];
            }

            // Get inactive count
            $result = $this->queryOne("SELECT COUNT(*) as count FROM agents WHERE status = 'inactive'");
            if ($result && isset($result['count'])) {
                $stats['inactive'] = (int)$result['count'];
            }

            return $stats;
        } catch (PDOException $e) {
            $this->handleError($e);
        } catch (Exception $e) {
            error_log("getAgentStats error: " . $e->getMessage());
            throw $e;
        }
    }

    public function bulkUpdateAgents($ids, $action) {
        // Force clear any opcache for this file
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate(__FILE__, true);
        }
        error_log("=== Database::bulkUpdateAgents START ===");
        error_log("Database::bulkUpdateAgents - METHOD CALLED with IDs: " . json_encode($ids) . ", action: " . $action);
        try {
            $status = $action === 'activate' ? 'active' : 'inactive';

            // Detect the correct primary key column (try both id and agent_id)
            $conn = $this->getConnection();
            if (!$conn) {
                error_log("❌ Database::bulkUpdateAgents - Connection is null");
                throw new Exception('Database connection is null');
            }

            $pkColumn = 'id';
            try {
                $testStmt = $conn->query("SHOW COLUMNS FROM agents LIKE 'id'");
                if ($testStmt && $testStmt->rowCount() === 0) {
                    $testStmt2 = $conn->query("SHOW COLUMNS FROM agents LIKE 'agent_id'");
                    if ($testStmt2 && $testStmt2->rowCount() > 0) {
                        $pkColumn = 'agent_id';
                    }
                }
            } catch (Exception $e) {
                error_log("⚠️ Database::bulkUpdateAgents - Error detecting PK column: " . $e->getMessage() . ", using default 'id'");
            }

            error_log("🔍 Database::bulkUpdateAgents - Using PK column: $pkColumn, IDs: " . json_encode($ids));

            // Convert IDs to integers for consistency (they might come as strings)
            $ids = array_map('intval', $ids);
            error_log("Database::bulkUpdateAgents - Converted IDs to integers: " . json_encode($ids));

            // Get old data for history (before update)
            $oldData = [];
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $fetchSql = "SELECT * FROM agents WHERE $pkColumn IN ($placeholders)";
            $oldStmt = $conn->prepare($fetchSql);
            $oldStmt->execute($ids);
            $oldData = $oldStmt->fetchAll(PDO::FETCH_ASSOC);

            error_log("Database::bulkUpdateAgents - Found " . count($oldData) . " old records");

            if ($action === 'delete') {
                $sql = "DELETE FROM agents WHERE $pkColumn IN (" . str_repeat('?,', count($ids) - 1) . "?)";
            } else {
                $sql = "UPDATE agents SET status = ? WHERE $pkColumn IN (" . str_repeat('?,', count($ids) - 1) . "?)";
                $updateIds = $ids;
                array_unshift($updateIds, $status);
                $this->execute($sql, $updateIds);

                error_log("Database::bulkUpdateAgents - UPDATE executed for " . count($ids) . " records");

                // Get updated data for history
                $newData = [];
                $fetchStmt = $conn->prepare($fetchSql);
                $fetchStmt->execute($ids);
                $newData = $fetchStmt->fetchAll(PDO::FETCH_ASSOC);

                error_log("Database::bulkUpdateAgents - Found " . count($newData) . " new records after update");

                // Log history for each agent
                $helperPath = __DIR__ . '/global-history-helper.php';
                error_log("Database::bulkUpdateAgents - Helper path: $helperPath, exists: " . (file_exists($helperPath) ? 'yes' : 'no'));

                if (file_exists($helperPath)) {
                    require_once $helperPath;
                    error_log("Database::bulkUpdateAgents - Helper file loaded, function exists: " . (function_exists('logGlobalHistory') ? 'yes' : 'no'));

                    if (function_exists('logGlobalHistory')) {
                        error_log("Database::bulkUpdateAgents - Starting history logging for " . count($ids) . " agents");
                        foreach ($ids as $agent_id) {
                            $oldRecord = null;
                            $newRecord = null;

                            // Use the detected PK column to match records (compare as integers)
                            foreach ($oldData as $record) {
                                $recordId = isset($record[$pkColumn]) ? (int)$record[$pkColumn] : (isset($record['id']) ? (int)$record['id'] : (isset($record['agent_id']) ? (int)$record['agent_id'] : null));
                                if ($recordId == (int)$agent_id) {
                                    $oldRecord = $record;
                                    break;
                                }
                            }
                            foreach ($newData as $record) {
                                $recordId = isset($record[$pkColumn]) ? (int)$record[$pkColumn] : (isset($record['id']) ? (int)$record['id'] : (isset($record['agent_id']) ? (int)$record['agent_id'] : null));
                                if ($recordId == (int)$agent_id) {
                                    $newRecord = $record;
                                    break;
                                }
                            }

                            if ($oldRecord && $newRecord) {
                                try {
                                    error_log("Database::bulkUpdateAgents - Logging history for agent ID: $agent_id (old status: " . ($oldRecord['status'] ?? 'N/A') . ", new status: " . ($newRecord['status'] ?? 'N/A') . ")");
                                    $result = @logGlobalHistory('agents', $agent_id, 'update', 'agents', $oldRecord, $newRecord);
                                    if ($result) {
                                        error_log("Database::bulkUpdateAgents - SUCCESS: History logged for agent ID: $agent_id");
                                    } else {
                                        error_log("Database::bulkUpdateAgents - FAILED: History logging returned false for agent ID: $agent_id");
                                    }
                                } catch (Exception $e) {
                                    error_log("Database::bulkUpdateAgents - EXCEPTION: History logging error for agent ID: $agent_id - " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
                                } catch (Throwable $e) {
                                    error_log("Database::bulkUpdateAgents - FATAL: History logging fatal error for agent ID: $agent_id - " . $e->getMessage());
                                }
                            } else {
                                error_log("Database::bulkUpdateAgents - WARNING: Could not find records for agent ID: $agent_id (PK: $pkColumn, old: " . ($oldRecord ? 'found' : 'missing') . ", new: " . ($newRecord ? 'found' : 'missing') . ")");
                            }
                        }
                        error_log("Database::bulkUpdateAgents - Finished history logging loop");
                    } else {
                        error_log("Database::bulkUpdateAgents - ERROR: logGlobalHistory function not found");
                    }
                } else {
                    error_log("Database::bulkUpdateAgents - ERROR: global-history-helper.php not found at: $helperPath");
                }

                return true;
            }

            $this->execute($sql, $ids);
            return true;
        } catch (PDOException $e) {
            $this->handleError($e);
        }
    }
}
} // End of class_exists check
