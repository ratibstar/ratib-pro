<?php
/**
 * EN: Handles API endpoint/business logic in `api/settings/settings-api.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/settings/settings-api.php`.
 */
// EN: Enter strict buffered mode first to guarantee clean JSON-only output.
// AR: الدخول في وضع التخزين المؤقت الصارم أولاً لضمان مخرجات JSON فقط.
// CRITICAL: Start output buffering FIRST, before ANY code
ob_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL); // Report all errors to error_log

// EN: Convert runtime warnings/notices into logs without leaking to client response.
// AR: تحويل التحذيرات والأخطاء غير الحرجة إلى سجلات بدون تسريبها للاستجابة.
// Custom error handler that logs but doesn't output
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    return true; // Suppress output
}, E_ALL);

// EN: Fatal-shutdown guard to emit structured JSON on unrecoverable failures.
// AR: معالج إنهاء للأخطاء الحرجة لإرجاع JSON منظم عند الأعطال غير القابلة للاستعادة.
// Set fatal error handler
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        // Clean any output
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            "success" => false,
            "message" => "Fatal error: " . $error['message'] . " in " . basename($error['file']) . " on line " . $error['line']
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
});

// Set JSON header early
header('Content-Type: application/json; charset=UTF-8');

// EN: Single response writer that clears buffers and normalizes JSON encoding errors.
// AR: كاتب استجابة موحّد يمسح المخزن المؤقت ويعالج أخطاء تحويل JSON.
// Function to send clean JSON response
function sendResponse($data, $code = 200) {
    // Discard all output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        // JSON encoding failed
        $json = json_encode(["success"=>false,"message"=>"JSON encoding failed: ".json_last_error_msg()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    echo $json;
    exit;
}

// Load config first so session and country DB ($GLOBALS['agency_db']) are set before any DB use.
// This ensures new users are created in the correct country DB when admin is in single-URL mode.
require_once dirname(dirname(__DIR__)) . '/includes/config.php';

// Try to require Database class - load core Database first (has getInstance), then fallback
try {
    $coreDatabasePath = __DIR__ . '/../core/Database.php';
    $configDatabasePath = __DIR__ . '/../../config/database.php';
    
    // Load core Database class FIRST (has getInstance method) before config/database.php
    if (file_exists($coreDatabasePath)) {
        require_once $coreDatabasePath;
        error_log("SettingsAPI: Core Database class loaded successfully");
    }
    
    // Load config/database.php only if Database class doesn't have getInstance (fallback)
    if (!class_exists('Database') || !method_exists('Database', 'getInstance')) {
        if (file_exists($configDatabasePath)) {
            require_once $configDatabasePath;
            error_log("SettingsAPI: Database config loaded successfully (fallback)");
        }
    }
    
    if (!class_exists('Database')) {
        sendResponse(["success"=>false,"message"=>"Database class not found"],500);
    }
} catch (Exception $e) {
    sendResponse(["success"=>false,"message"=>"Config error: ".$e->getMessage()],500);
} catch (Throwable $e) {
    sendResponse(["success"=>false,"message"=>"Config fatal: ".$e->getMessage()],500);
}

// EN: Central settings service handling dynamic table CRUD with safety checks.
// AR: خدمة إعدادات مركزية تدير CRUD للجداول الديناميكية مع ضوابط أمان.
class SettingsAPI {
    private $conn;
    private $table;
    
    public function __construct($table) {
        try {
            $this->table = preg_replace('/[^a-zA-Z0-9_]/', '', $table); // Sanitize table name
            try {
                if (false && !empty($GLOBALS['SETTINGS_USE_CONTROL_DB'])) { // control panel removed
                    if (!defined('DB_HOST')) {
                        require_once dirname(dirname(__DIR__)) . '/includes/config.php';
                    }
                    $host = defined('DB_HOST') ? DB_HOST : 'localhost';
                    $dbname = defined('DB_NAME') ? DB_NAME : 'outratib_out';
                    $user = defined('DB_USER') ? DB_USER : '';
                    $pass = defined('DB_PASS') ? DB_PASS : '';
                    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
                    $this->conn = new PDO($dsn, $user, $pass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]);
                } elseif (class_exists('Database') && method_exists('Database', 'getInstance')) {
                    $db = Database::getInstance();
                    $this->conn = $db->getConnection();
                } else {
                    $db = new Database();
                    $this->conn = $db->getConnection();
                }
                
                // Ensure PDO is in exception mode for proper error handling
                if ($this->conn && $this->conn instanceof PDO) {
                    $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                }
            } catch (PDOException $e) {
                error_log("PDO Exception in SettingsAPI constructor: " . $e->getMessage());
                throw new Exception("Database connection failed: " . $e->getMessage());
            } catch (Exception $e) {
                error_log("Exception in SettingsAPI constructor: " . $e->getMessage());
                throw $e;
            }
            
            if (!$this->conn) {
                throw new Exception("Database connection returned null");
            }
        } catch (PDOException $e) {
            error_log("PDO Exception in SettingsAPI constructor: " . $e->getMessage());
            sendResponse(["success"=>false,"message"=>"Database PDO error: ".$e->getMessage()],500);
        } catch (Exception $e) {
            error_log("Exception in SettingsAPI constructor: " . $e->getMessage());
            sendResponse(["success"=>false,"message"=>"Database connection failed: ".$e->getMessage()],500);
        } catch (Throwable $e) {
            error_log("Throwable in SettingsAPI constructor: " . $e->getMessage());
            sendResponse(["success"=>false,"message"=>"Database fatal error: ".$e->getMessage()],500);
        }
    }
    
    private function logHistory($action, $recordId, $oldData, $newData) {
        // Use the global history helper for consistent logging across all modules
        try {
            if (file_exists(__DIR__ . '/../core/global-history-helper.php')) {
                require_once __DIR__ . '/../core/global-history-helper.php';
                if (function_exists('logGlobalHistory')) {
                    // Map table names to their correct module names
                    $moduleMap = [
                        'agents' => 'agents',
                        'subagents' => 'subagents',
                        'workers' => 'workers',
                        'cases' => 'cases',
                        'employees' => 'hr',
                        'contacts' => 'contacts',
                        'contact_communications' => 'communications',
                        'contact_notifications' => 'notifications',
                        'journal_entries' => 'accounting',
                        'payment_receipts' => 'accounting',
                        'payment_payments' => 'accounting',
                        'users' => 'settings',
                        'roles' => 'settings',
                        'recruitment_countries' => 'settings',
                        'recruitment_settings' => 'settings',
                        'job_categories' => 'settings',
                        'arrival_agencies' => 'settings',
                        'arrival_stations' => 'settings'
                    ];
                    
                    // Get the correct module name, default to 'settings' if not mapped
                    $module = isset($moduleMap[$this->table]) ? $moduleMap[$this->table] : 'settings';
                    
                    error_log("🔍 Settings API: Logging history - Table: {$this->table}, Record: $recordId, Action: $action, Module: $module");
                    $result = logGlobalHistory($this->table, $recordId, $action, $module, $oldData, $newData);
                    if ($result) {
                        error_log("✅ Settings API: History logged successfully for table '{$this->table}' with module '$module'");
                    } else {
                        error_log("❌ Settings API: History logging failed for table '{$this->table}' with module '$module'");
                    }
                }
            }
        } catch (Exception $e) {
            // Don't fail if history logging fails
            error_log("History logging error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        } catch (Throwable $e) {
            error_log("History logging fatal error: " . $e->getMessage());
        }
    }

    public function getAll() {
        try {
            // Check if table exists first (before trying to create)
            $tableExists = $this->tableExists();
            
            // For core system tables that don't exist, return empty array immediately
            $coreTables = ['users', 'agents', 'workers', 'cases', 'subagents', 'hr_employees'];
            $tableLower = strtolower(trim($this->table));
            
            // Check if it's a core table that doesn't exist - use direct comparison for reliability
            if (!$tableExists) {
                $isCoreTable = false;
                foreach ($coreTables as $coreTable) {
                    if (strtolower(trim($coreTable)) === $tableLower) {
                        $isCoreTable = true;
                        break;
                    }
                }
                
                if ($isCoreTable) {
                    error_log("Returning empty array for core table: '{$this->table}'");
                    sendResponse(["success"=>true,"data"=>[]]);
                    return;
                }
            }
            
            // Ensure table exists - create it if needed (for settings tables)
            $creationError = null;
            if (!$tableExists) {
                try {
                    error_log("Attempting to create table: '{$this->table}'");
                    $this->ensureTableExists();
                    // Check again after creation attempt
                    $tableExists = $this->tableExists();
                    if ($tableExists) {
                        error_log("✅ Table '{$this->table}' successfully created and verified");
                    } else {
                        error_log("❌ Table '{$this->table}' does not exist after creation attempt");
                    }
                } catch (PDOException $e) {
                    $creationError = $e->getMessage();
                    error_log("PDO Exception while ensuring table exists for '{$this->table}': " . $creationError);
                    error_log("PDO Error Code: " . $e->getCode());
                    error_log("Stack trace: " . $e->getTraceAsString());
                    // Continue to check if table exists anyway
                    $tableExists = $this->tableExists();
                } catch (Exception $e) {
                    $creationError = $e->getMessage();
                    error_log("Exception while ensuring table exists for '{$this->table}': " . $creationError);
                    error_log("Stack trace: " . $e->getTraceAsString());
                    // Continue to check if table exists anyway
                    $tableExists = $this->tableExists();
                } catch (Throwable $e) {
                    $creationError = $e->getMessage();
                    error_log("Fatal error while ensuring table exists for '{$this->table}': " . $creationError);
                    error_log("Stack trace: " . $e->getTraceAsString());
                    // Continue to check if table exists anyway
                    $tableExists = $this->tableExists();
                }
            }
            
            if (!$tableExists) {
                // For settings tables, return error if table doesn't exist after creation attempt
                $errorMsg = "Table does not exist: {$this->table}";
                if ($creationError) {
                    $errorMsg .= ". Creation error: " . $creationError;
                }
                error_log("❌ Final check: Table '{$this->table}' does not exist and could not be created");
                sendResponse(["success"=>false,"message"=>$errorMsg],404);
                return;
            }
            
            // Ensure required columns exist before fetching (country_id, city, etc.)
            $this->ensureColumnsExist();
            
            if ($this->table === 'users') {
                $hasPasswordPlain = $this->columnExists('password_plain');
                $passwordPlainSelect = $hasPasswordPlain ? ", u.password_plain" : "";
                $sql = "
                    SELECT 
                        u.*{$passwordPlainSelect},
                        CASE WHEN (fp.latest_template_id IS NOT NULL OR wc.credential_id IS NOT NULL) THEN 1 ELSE 0 END AS has_fingerprint,
                        CASE WHEN (fp.latest_template_id IS NOT NULL OR wc.credential_id IS NOT NULL) THEN 'Registered' ELSE 'Not Registered' END AS fingerprint_status
                    FROM `users` u
                    LEFT JOIN (
                        SELECT user_id, MAX(id) AS latest_template_id
                        FROM fingerprint_templates
                        WHERE template_data IS NOT NULL AND template_data <> ''
                        GROUP BY user_id
                    ) fp ON u.user_id = fp.user_id
                    LEFT JOIN (
                        SELECT user_id, credential_id
                        FROM webauthn_credentials
                        GROUP BY user_id
                    ) wc ON u.user_id = wc.user_id
                ";
                $uWhere = $this->usersListTenantWhere('u');
                if ($uWhere['sql'] !== '') {
                    $sql .= ' WHERE ' . $uWhere['sql'];
                }
                if ($this->columnExists('created_at')) {
                    $sql .= " ORDER BY u.created_at DESC";
                } else {
                    $sql .= " ORDER BY u.user_id DESC";
                }
            } elseif ($this->table === 'control_admins') {
                $joinCountry = $this->columnExists('country_id');
                $sql = "SELECT a.*" . ($joinCountry ? ", c.name AS country_name" : "") . " FROM `control_admins` a";
                if ($joinCountry) {
                    try {
                        $chk = $this->conn->query("SELECT 1 FROM control_countries LIMIT 1");
                        if ($chk) {
                            $sql .= " LEFT JOIN control_countries c ON a.country_id = c.id";
                        }
                    } catch (Exception $e) { /* ignore */ }
                }
                $sql .= " ORDER BY a.id DESC";
            } else {
            $sql = "SELECT * FROM `{$this->table}`";
            if ($this->columnExists('created_at')) {
                $sql .= " ORDER BY created_at DESC";
            } elseif ($this->columnExists('id')) {
                $sql .= " ORDER BY id DESC";
            } elseif ($this->columnExists('user_id')) {
                $sql .= " ORDER BY user_id DESC";
                }
            }
            $stmt = $this->conn->prepare($sql);
            if ($this->table === 'users') {
                $uWhereExec = $this->usersListTenantWhere('u');
                if (!empty($uWhereExec['params'])) {
                    $stmt->execute($uWhereExec['params']);
                } else {
                    $stmt->execute();
                }
            } else {
                $stmt->execute();
            }
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // Security: never send password hash/plain columns to frontend for users/control_admins
            if ($this->table === 'users' || $this->table === 'control_admins') {
                foreach ($data as &$row) {
                    unset($row['password'], $row['pass'], $row['password_plain']);
                    if ($this->table === 'control_admins' && isset($row['id'])) {
                        $row['user_id'] = $row['id']; // Frontend expects user_id for users table config
                        $row['status'] = !empty($row['is_active']) ? 'active' : 'inactive'; // For display
                        $row['name'] = trim($row['full_name'] ?? '') ?: ($row['username'] ?? ''); // Display name
                    }
                }
            }
            sendResponse([
                "success"=>true,
                "data"=>$data
            ]);
        } catch (Exception $e) {
            sendResponse(["success"=>false,"message"=>"Fetch failed: ".$e->getMessage()],500);
        }
    }
    public function getById($id) {
        try {
            // Ensure table exists - create it if needed
            $this->ensureTableExists();
            
            if (!$this->tableExists()) {
                // For core system tables, return not found gracefully
                $coreTables = ['users', 'agents', 'workers', 'cases', 'subagents', 'hr_employees'];
                $tableLower = strtolower(trim($this->table));
                if (in_array($tableLower, array_map('strtolower', $coreTables))) {
                    sendResponse(["success"=>false,"message"=>"Record not found"],404);
                    return;
                }
                sendResponse(["success"=>false,"message"=>"Table not found: {$this->table}"],404);
                return;
            }
            
            // Detect primary key column name (id, user_id, etc.)
            $pkColumn = 'id';
            $tableLower = strtolower(trim($this->table));
            if ($tableLower === 'users') {
                $pkColumn = 'user_id';
            }
            
            // Ensure columns exist before fetching
            $this->ensureColumnsExist();
            
            // For users table, include password_plain if column exists
            if ($tableLower === 'users' && $this->columnExists('password_plain')) {
                $stmt = $this->conn->prepare("SELECT *, password_plain FROM `{$this->table}` WHERE `{$pkColumn}` = ? LIMIT 1");
            } else {
                $stmt = $this->conn->prepare("SELECT * FROM `{$this->table}` WHERE `{$pkColumn}` = ? LIMIT 1");
            }
            $stmt->execute([$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$data) {
                sendResponse(["success"=>false,"message"=>"Record not found"],404);
            }
            if ($tableLower === 'users' && !$this->usersRowAllowedForSessionCountry($data)) {
                sendResponse(["success"=>false,"message"=>"Record not found"],404);
            }
            // Security: never send password hash/plain columns to frontend for users/control_admins
            if ($tableLower === 'users' || $tableLower === 'control_admins') {
                unset($data['password'], $data['pass'], $data['password_plain']);
                if ($tableLower === 'control_admins' && isset($data['id'])) {
                    $data['user_id'] = $data['id'];
                    $data['status'] = !empty($data['is_active']) ? 'active' : 'inactive';
                    $data['name'] = trim($data['full_name'] ?? '') ?: ($data['username'] ?? '');
                }
            }
            sendResponse(["success"=>true, "data"=>$data]);
        } catch(Exception $e) {
            sendResponse(["success"=>false,"message"=>"Fetch failed: ".$e->getMessage()],500);
        }
    }
    public function create($data) {
        try {
            if (!$this->conn) {
                sendResponse(["success"=>false,"message"=>"Database connection not available"],500);
            }
            
            // Ensure table exists - create it if needed
            $this->ensureTableExists();
            
            if (!$this->tableExists()) sendResponse(["success"=>false,"message"=>"Table not found: {$this->table}"],404);
            
            // Auto-add missing columns for specific tables
            try {
                $this->ensureColumnsExist();
            } catch (Exception $e) {
                error_log("ensureColumnsExist failed: " . $e->getMessage());
                // Continue anyway - column addition is optional
            }
            
            $existingCols = $this->getExistingColumns();
            $mappedData = $this->mapFieldsToColumns($data, $existingCols);
            
            // Validate country_id is an integer if present
            if (isset($mappedData['country_id']) && $mappedData['country_id'] !== null && $mappedData['country_id'] !== '') {
                $intValue = filter_var($mappedData['country_id'], FILTER_VALIDATE_INT);
                if ($intValue !== false && $intValue >= 0) {
                    $mappedData['country_id'] = $intValue;
                } else {
                    // Invalid country_id - set to null
                    $mappedData['country_id'] = null;
                }
            }
            // New program users: inherit admin session country when form omits country (matches login isolation)
            if ($this->table === 'users' && in_array('country_id', $existingCols, true)) {
                $hasCountry = isset($mappedData['country_id']) && $mappedData['country_id'] !== null && $mappedData['country_id'] !== '' && (int)$mappedData['country_id'] > 0;
                if (!$hasCountry && isset($_SESSION['country_id']) && (int)$_SESSION['country_id'] > 0) {
                    $mappedData['country_id'] = (int)$_SESSION['country_id'];
                }
                $sc = $this->usersSessionCountryId();
                if ($sc > 0) {
                    $setC = isset($mappedData['country_id']) && $mappedData['country_id'] !== null && $mappedData['country_id'] !== ''
                        ? (int) $mappedData['country_id'] : 0;
                    if ($setC > 0 && $setC !== $sc) {
                        $mappedData['country_id'] = $sc;
                    }
                }
            }
            // Scope new users to current agency when multiple agencies share one DB (control_agencies.id).
            if ($this->table === 'users' && in_array('agency_id', $existingCols, true)) {
                $sa = $this->usersSessionAgencyId();
                if ($sa > 0) {
                    if (!isset($mappedData['agency_id']) || $mappedData['agency_id'] === null || $mappedData['agency_id'] === '' || (int)$mappedData['agency_id'] <= 0) {
                        $mappedData['agency_id'] = $sa;
                    } elseif ((int)$mappedData['agency_id'] !== $sa) {
                        $mappedData['agency_id'] = $sa;
                    }
                }
            }
            
            // For control_admins table, convert status to is_active
            if ($this->table === 'control_admins') {
                if (isset($mappedData['status'])) {
                    $mappedData['is_active'] = ($mappedData['status'] === 'active') ? 1 : 0;
                    unset($mappedData['status']);
                }
                $nameVal = $mappedData['username'] ?? $mappedData['name'] ?? '';
                if ($nameVal && !isset($mappedData['full_name'])) {
                    $mappedData['full_name'] = $nameVal; // control_admins needs full_name
                }
                // New control_admins default to active so they can login
                if (!isset($mappedData['is_active'])) {
                    $mappedData['is_active'] = 1;
                }
            }
            // For currencies table, convert status to is_active
            if ($this->table === 'currencies' && isset($mappedData['status'])) {
                $mappedData['is_active'] = ($mappedData['status'] === 'active') ? 1 : 0;
                unset($mappedData['status']); // Remove status, use is_active instead
            }
            
            if ($this->table === 'users' && in_array('status', $existingCols, true)
                && array_key_exists('status', $mappedData)
                && ($mappedData['status'] === null || trim((string)$mappedData['status']) === '')) {
                unset($mappedData['status']);
            }
            if (in_array('status', $existingCols) && !isset($mappedData['status'])) {
                $mappedData['status'] = 'active';
            }
            if (in_array('is_active', $existingCols) && !isset($mappedData['is_active'])) {
                $mappedData['is_active'] = 1;
            }
            if ($this->table === 'users' && in_array('status', $existingCols, true) && in_array('is_active', $existingCols, true)) {
                $sn = strtolower(trim((string)($mappedData['status'] ?? '')));
                $ia = (int)($mappedData['is_active'] ?? 0);
                $on = ($sn === 'active' || $sn === '1' || $sn === 'enabled' || $ia === 1);
                $mappedData['status'] = $on ? 'active' : 'inactive';
                $mappedData['is_active'] = $on ? 1 : 0;
            }
            if (in_array('created_at', $existingCols) && !isset($mappedData['created_at'])) {
                $mappedData['created_at'] = date('Y-m-d H:i:s');
            }
            if (in_array('updated_at', $existingCols) && !isset($mappedData['updated_at'])) {
                $mappedData['updated_at'] = date('Y-m-d H:i:s');
            }
            
            // For new users: Set empty permissions array by default (user sees nothing)
            // Only admin can grant permissions later
            if ($this->table === 'users' && $this->columnExists('permissions') && !isset($mappedData['permissions'])) {
                $mappedData['permissions'] = json_encode([]); // Empty array = see nothing
            }
            
            $this->ensurePasswordColumnsCapacity();
            $payload = array();
            $passwordValue = null; // Store password value to add password_plain after loop
            foreach ($mappedData as $key => $value) {
                if (in_array($key, $existingCols)) {
                    // Hash password for users/control_admins - supports `password` and legacy `pass`.
                    if (($this->table === 'users' || $this->table === 'control_admins') && ($key === 'password' || $key === 'pass') && !empty($value) && trim($value) !== '') {
                        $payload[$key] = password_hash($value, PASSWORD_DEFAULT);
                        $passwordValue = $value; // Used only to clear password_plain column (security: stop storing plain)
                    } elseif ($key === 'country_id' && $value !== null && $value !== '') {
                        // Validate country_id is an integer
                        $intValue = filter_var($value, FILTER_VALIDATE_INT);
                        if ($intValue !== false && $intValue >= 0) {
                            $payload[$key] = $intValue;
                        } else {
                            // Invalid country_id - set to null
                            $payload[$key] = null;
                        }
                    } else {
                        $payload[$key] = $value;
                    }
                }
            }
            
            // Security: do NOT store password_plain - passwords must be hashed only; login uses password_verify on hash
            
            // Remove empty password from payload (don't set password/pass when empty)
            if ($this->table === 'users' || $this->table === 'control_admins') {
                $rawPass = isset($mappedData['password']) ? $mappedData['password'] : (isset($mappedData['pass']) ? $mappedData['pass'] : null);
                if ($rawPass === null || trim((string)$rawPass) === '') {
                    unset($payload['password'], $payload['pass'], $payload['password_plain']);
                }
            }
            
            if (empty($payload)) {
                sendResponse(["success"=>false,"message"=>"No valid columns to insert"],400);
            }
            
            $cols = array_keys($payload);
            $sets = ':' . implode(', :', $cols);
            $sql = "INSERT INTO `{$this->table}` (`".implode('`,`',$cols)."`) VALUES ({$sets})";
            
            try {
                $stmt = $this->conn->prepare($sql);
                if ($stmt === false) {
                    $errorInfo = $this->conn->errorInfo();
                    sendResponse(["success"=>false,"message"=>"SQL prepare failed: ".($errorInfo[2] ?? 'Unknown error')],500);
                }
            } catch (Exception $e) {
                sendResponse(["success"=>false,"message"=>"SQL prepare exception: ".$e->getMessage()],500);
            }
            
            try {
                $executeResult = $stmt->execute($payload);
                if ($executeResult === false) {
                    $errorInfo = $stmt->errorInfo();
                    sendResponse(["success"=>false,"message"=>"SQL execute failed: ".($errorInfo[2] ?? 'Unknown error')],500);
                }
            } catch (Exception $e) {
                sendResponse(["success"=>false,"message"=>"SQL execute exception: ".$e->getMessage()],500);
            }
            
            // Detect primary key column name (id, user_id, etc.)
            $pkColumn = 'id';
            if ($this->table === 'users') {
                $pkColumn = 'user_id';
            } elseif ($this->table === 'control_admins') {
                $pkColumn = 'id';
            }
            
            $id = $this->conn->lastInsertId();
            if (!$id || $id === '0') {
                // Try to get the ID from the payload if it was inserted
                if (isset($payload[$pkColumn])) {
                    $id = $payload[$pkColumn];
                } else {
                    sendResponse(["success"=>false,"message"=>"Failed to get insert ID. Last ID: ".$id],500);
                }
            }
            
            // Return the created record for immediate display
            try {
                // For users table, include password_plain if column exists
                if ($this->table === 'users' && $this->columnExists('password_plain')) {
                    $stmt = $this->conn->prepare("SELECT *, password_plain FROM `{$this->table}` WHERE `{$pkColumn}` = ? LIMIT 1");
                } else {
                    $stmt = $this->conn->prepare("SELECT * FROM `{$this->table}` WHERE `{$pkColumn}` = ? LIMIT 1");
                }
                if ($stmt === false) {
                    $errorInfo = $this->conn->errorInfo();
                    sendResponse(["success"=>false,"message"=>"Failed to prepare select: ".($errorInfo[2] ?? 'Unknown')],500);
                }
                $stmt->execute([$id]);
                $created = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($created === false || !$created) {
                    // Record might exist but fetch failed - still return success with the ID
                    $created = array_merge($payload, array($pkColumn => $id));
                }
            } catch (Exception $e) {
                // If fetch fails, at least return the payload with the ID
                $created = array_merge($payload, array($pkColumn => $id));
            }
            
            // For new control_admin: grant full access so they can login
            if ($this->table === 'control_admins' && $id && $this->conn) {
                try {
                    $chk = $this->conn->query("SHOW TABLES LIKE 'control_admin_permissions'");
                    if ($chk && $chk->fetch()) {
                        $permsJson = json_encode(['*']);
                        $stmt = $this->conn->prepare("INSERT INTO control_admin_permissions (user_id, permissions) VALUES (?, ?) ON DUPLICATE KEY UPDATE permissions = VALUES(permissions)");
                        if ($stmt) {
                            $stmt->execute([$id, $permsJson]);
                        }
                    }
                } catch (Throwable $e) {
                    error_log("Settings API: control_admin_permissions insert failed: " . $e->getMessage());
                }
            }
            
            // Auto-create GL account when creating a user with Accounting role
            if ($this->table === 'users' && $id && $this->conn) {
                try {
                    $roleId = $created['role_id'] ?? $payload['role_id'] ?? null;
                    if ($roleId) {
                        $roleStmt = $this->conn->prepare("SELECT role_name FROM roles WHERE role_id = ? LIMIT 1");
                        $roleStmt->execute([$roleId]);
                        $roleRow = $roleStmt->fetch(PDO::FETCH_ASSOC);
                        if ($roleRow && strtolower(trim($roleRow['role_name'] ?? '')) === 'accounting') {
                            $helperPath = __DIR__ . '/../accounting/entity-account-helper.php';
                            if (file_exists($helperPath)) {
                                require_once $helperPath;
                                $userName = $created['username'] ?? $payload['username'] ?? '';
                                if ($userName && function_exists('ensureEntityAccount')) {
                                    ensureEntityAccount($this->conn, 'accounting', $id, $userName);
                                }
                            }
                        }
                    }
                } catch (Throwable $e) {
                    error_log("Settings API: ensureEntityAccount for new Accounting user failed: " . $e->getMessage());
                }
            }
            
            // Prepare response data first
            $responseData = array(
                "success"=>true,
                "message"=>"Created successfully",
                "data"=>array($pkColumn=>$id),
                "created"=>$created
            );
            
            // Log history BEFORE sending response (to ensure it completes)
            $this->logHistory('create', (string)$id, null, $created);
            
            // Send response
            sendResponse($responseData);
        } catch(Exception $e){
            sendResponse(["success"=>false,"message"=>"Create failed: ".$e->getMessage()],500);
        } catch(Throwable $e){
            sendResponse(["success"=>false,"message"=>"Create fatal: ".$e->getMessage()],500);
        }
    }
    public function update($id, $data) {
        try {
            // Ensure table exists - create it if needed
            $this->ensureTableExists();
            
            if (!$this->tableExists()) sendResponse(["success"=>false,"message"=>"Table not found: {$this->table}"],404);
            
            // Auto-add missing columns for specific tables
            $this->ensureColumnsExist();
            
            // Detect primary key column name (id, user_id, etc.)
            $pkColumn = 'id';
            if ($this->table === 'users') {
                $pkColumn = 'user_id';
            } elseif ($this->table === 'control_admins') {
                $pkColumn = 'id';
            }
            
            // Refresh existing columns AFTER ensureColumnsExist() to include any newly created columns
            $existingCols = $this->getExistingColumns();
            $mappedData = $this->mapFieldsToColumns($data, $existingCols);
            
            // Validate country_id is an integer if present
            if (isset($mappedData['country_id']) && $mappedData['country_id'] !== null && $mappedData['country_id'] !== '') {
                $intValue = filter_var($mappedData['country_id'], FILTER_VALIDATE_INT);
                if ($intValue !== false && $intValue >= 0) {
                    $mappedData['country_id'] = $intValue;
                } else {
                    // Invalid country_id - set to null
                    $mappedData['country_id'] = null;
                }
            }
            if ($this->table === 'users' && in_array('country_id', $existingCols, true)) {
                $sc = $this->usersSessionCountryId();
                if ($sc > 0 && array_key_exists('country_id', $mappedData)) {
                    $cv = isset($mappedData['country_id']) && $mappedData['country_id'] !== null && $mappedData['country_id'] !== ''
                        ? (int) $mappedData['country_id'] : 0;
                    if ($cv > 0 && $cv !== $sc) {
                        $mappedData['country_id'] = $sc;
                    }
                }
            }
            if ($this->table === 'users' && in_array('status', $existingCols, true) && in_array('is_active', $existingCols, true)) {
                if (array_key_exists('status', $mappedData) && ($mappedData['status'] === null || trim((string)$mappedData['status']) === '')) {
                    unset($mappedData['status']);
                }
                $stSet = array_key_exists('status', $mappedData);
                $iaSet = array_key_exists('is_active', $mappedData);
                if ($stSet || $iaSet) {
                    $sn = $stSet ? strtolower(trim((string)$mappedData['status'])) : '';
                    $ia = $iaSet ? (int)$mappedData['is_active'] : 0;
                    if (!$stSet && $iaSet) {
                        $on = ($ia === 1);
                    } elseif ($stSet && !$iaSet) {
                        $on = ($sn === 'active' || $sn === '1' || $sn === 'enabled');
                    } else {
                        $on = ($sn === 'active' || $sn === '1' || $sn === 'enabled' || $ia === 1);
                    }
                    $mappedData['status'] = $on ? 'active' : 'inactive';
                    $mappedData['is_active'] = $on ? 1 : 0;
                }
            }
            
            // For control_admins table, convert status field to is_active (1/0)
            if ($this->table === 'control_admins') {
                if (isset($mappedData['is_active']) && (is_string($mappedData['is_active']) || is_bool($mappedData['is_active']))) {
                    $mappedData['is_active'] = ($mappedData['is_active'] === 'active' || $mappedData['is_active'] === true || $mappedData['is_active'] === '1' || $mappedData['is_active'] === 1) ? 1 : 0;
                }
                if (isset($mappedData['status']) && !isset($mappedData['is_active'])) {
                    $mappedData['is_active'] = ($mappedData['status'] === 'active') ? 1 : 0;
                    unset($mappedData['status']);
                }
            }
            // For currencies table, convert status field to is_active (1/0)
            if ($this->table === 'currencies') {
                // If status was mapped to is_active by alias map, convert the value
                if (isset($mappedData['is_active']) && (is_string($mappedData['is_active']) || is_bool($mappedData['is_active']))) {
                    $mappedData['is_active'] = ($mappedData['is_active'] === 'active' || $mappedData['is_active'] === true || $mappedData['is_active'] === '1' || $mappedData['is_active'] === 1) ? 1 : 0;
                }
                // If status field still exists (not mapped), convert it to is_active
                if (isset($mappedData['status']) && !isset($mappedData['is_active'])) {
                    $mappedData['is_active'] = ($mappedData['status'] === 'active') ? 1 : 0;
                    unset($mappedData['status']);
                }
            }
            
            if (in_array('updated_at', $existingCols) && !isset($mappedData['updated_at'])) {
                $mappedData['updated_at'] = date('Y-m-d H:i:s');
            }
            
            $this->ensurePasswordColumnsCapacity();
            $payload = array();
            foreach ($mappedData as $key => $value) {
                if ($key !== 'id' && $key !== $pkColumn && in_array($key, $existingCols)) {
                    // Hash password for users/control_admins when password/pass field is not empty
                    if (($this->table === 'users' || $this->table === 'control_admins') && ($key === 'password' || $key === 'pass') && !empty($value) && trim($value) !== '') {
                        $payload[$key] = password_hash($value, PASSWORD_DEFAULT);
                    } elseif ($key === 'country_id' && $value !== null && $value !== '') {
                        // Validate country_id is an integer
                        $intValue = filter_var($value, FILTER_VALIDATE_INT);
                        if ($intValue !== false && $intValue >= 0) {
                            $payload[$key] = $intValue;
                        } else {
                            // Invalid country_id - set to null or skip
                            $payload[$key] = null;
                        }
                    } else {
                        $payload[$key] = $value;
                    }
                }
            }
            
            // Remove empty password from payload (don't update password/pass when empty)
            if ($this->table === 'users' || $this->table === 'control_admins') {
                $rawPass = isset($mappedData['password']) ? $mappedData['password'] : (isset($mappedData['pass']) ? $mappedData['pass'] : null);
                if ($rawPass === null || trim((string)$rawPass) === '') {
                    unset($payload['password'], $payload['pass'], $payload['password_plain']);
                }
            }
            
            if (empty($payload)) {
                sendResponse(["success"=>false,"message"=>"No valid columns to update"],400);
            }
            
            $setClause = array();
            foreach($payload as $k=>$v) $setClause[] = "`$k`=:{$k}";
            $sql = "UPDATE `{$this->table}` SET " . implode(',', $setClause) . " WHERE `{$pkColumn}` = :pk";
            // Get old data before update for history
            $oldData = null;
            try {
                $stmt = $this->conn->prepare("SELECT * FROM `{$this->table}` WHERE `{$pkColumn}` = ? LIMIT 1");
                $stmt->execute([$id]);
                $oldData = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {}
            if ($this->table === 'users' && is_array($oldData) && !$this->usersRowAllowedForSessionCountry($oldData)) {
                sendResponse(["success"=>false,"message"=>"Record not found"],404);
            }
            
            $payload["pk"] = $id;
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($payload);
            
            // Fetch the updated record to return it
            // For users table, include password_plain if column exists
            if ($this->table === 'users' && $this->columnExists('password_plain')) {
                $stmt = $this->conn->prepare("SELECT *, password_plain FROM `{$this->table}` WHERE `{$pkColumn}` = ? LIMIT 1");
            } else {
                $stmt = $this->conn->prepare("SELECT * FROM `{$this->table}` WHERE `{$pkColumn}` = ? LIMIT 1");
            }
            $stmt->execute([$id]);
            $updated = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($updated && ($this->table === 'users' || $this->table === 'control_admins')) {
                unset($updated['password'], $updated['pass'], $updated['password_plain']);
                if ($this->table === 'control_admins' && isset($updated['id'])) {
                    $updated['user_id'] = $updated['id'];
                }
            }
            
            // Log history BEFORE sending response (to ensure it completes)
            $this->logHistory('update', (string)$id, $oldData, $updated);
            
            sendResponse(["success"=>true,"message"=>"Updated successfully","updated"=>$updated]);
        } catch(Exception $e){
            sendResponse(["success"=>false,"message"=>"Update failed: ".$e->getMessage()],500);
        }
    }
    public function delete($id) {
        try {
            // Ensure table exists - create it if needed
            $this->ensureTableExists();
            
            if (!$this->tableExists()) sendResponse(["success"=>false,"message"=>"Table not found: {$this->table}"],404);
            
            // Detect primary key column name (id, user_id, etc.)
            $pkColumn = 'id';
            if ($this->table === 'users') {
                $pkColumn = 'user_id';
            } elseif ($this->table === 'control_admins') {
                $pkColumn = 'id';
            }
            
            // Get record data before deletion for history
            $deletedData = null;
            try {
                $stmt = $this->conn->prepare("SELECT * FROM `{$this->table}` WHERE `{$pkColumn}` = ? LIMIT 1");
                $stmt->execute([$id]);
                $deletedData = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                error_log("Error fetching record before deletion: " . $e->getMessage());
            }
            
            if (!$deletedData) {
                sendResponse(["success"=>false,"message"=>"Record not found"],404);
                return;
            }
            if ($this->table === 'users' && !$this->usersRowAllowedForSessionCountry($deletedData)) {
                sendResponse(["success"=>false,"message"=>"Record not found"],404);
                return;
            }
            
            // For users table, delete related records first to avoid foreign key constraints
            if ($this->table === 'users') {
                // First, update foreign key references to NULL (for ON DELETE RESTRICT constraints)
                // List all tables that might have foreign keys to users
                $updateTables = [
                    'cases' => ['created_by', 'assigned_to', 'updated_by'],
                    'financial_transactions' => ['created_by', 'updated_by'],
                    'accounts_receivable' => ['created_by', 'updated_by'],
                    'accounts_payable' => ['created_by', 'updated_by'],
                    'journal_entries' => ['created_by', 'updated_by'],
                    'receipts' => ['created_by', 'updated_by'],
                    'payments' => ['created_by', 'updated_by'],
                    'transaction_lines' => ['created_by'],
                    'entity_transactions' => ['created_by']
                ];
                
                foreach ($updateTables as $table => $columns) {
                    try {
                        // Check if table exists - use query directly (safe because table name is from predefined list)
                        $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $table); // Sanitize
                        $tableCheck = $this->conn->query("SHOW TABLES LIKE '{$tableName}'");
                        if ($tableCheck && $tableCheck->rowCount() > 0) {
                            foreach ($columns as $column) {
                                try {
                                    $columnName = preg_replace('/[^a-zA-Z0-9_]/', '', $column); // Sanitize
                                    // Check if column exists
                                    $colCheck = $this->conn->query("SHOW COLUMNS FROM `{$tableName}` LIKE '{$columnName}'");
                                    if ($colCheck && $colCheck->rowCount() > 0) {
                                        // Update the column to NULL - try to update even if it might fail due to constraints
                                        $updateSql = "UPDATE `{$tableName}` SET `{$columnName}` = NULL WHERE `{$columnName}` = ?";
                                        $stmt = $this->conn->prepare($updateSql);
                $stmt->execute([$id]);
                                        $updated = $stmt->rowCount();
                                        if ($updated > 0) {
                                        }
            } else {
                                    }
                                } catch (Exception $e) {
                                    // Continue to next column even if this one fails
                                }
                            }
                        } else {
                        }
                    } catch (Exception $e) {
                        // Continue to next table even if this one fails
                    }
                }
                
                // Also try a more aggressive approach - disable foreign key checks temporarily
                // This is safe since we're handling cleanup above
                try {
                    $this->conn->exec("SET FOREIGN_KEY_CHECKS = 0");
                } catch (Exception $e) {
                    error_log("⚠️ Could not disable foreign key checks: " . $e->getMessage());
                }
                
                // Delete from tables with ON DELETE CASCADE (these will be deleted automatically, but we do it explicitly for clarity)
                $deleteTables = [
                    'fingerprint_templates' => 'user_id',
                    'webauthn_credentials' => 'user_id',
                    'biometric_credentials' => 'user_id',
                    'biometric_logs' => 'user_id',
                    'activity_logs' => 'user_id',
                    'system_history' => 'user_id',
                    'user_permissions' => 'user_id',
                    'face_templates' => 'user_id'
                ];
                
                foreach ($deleteTables as $table => $column) {
                    try {
                        // Check if table exists first
                        $checkStmt = $this->conn->prepare("SHOW TABLES LIKE ?");
                        $checkStmt->execute([$table]);
                        if ($checkStmt->fetch()) {
                            $stmt = $this->conn->prepare("DELETE FROM `{$table}` WHERE `{$column}` = ?");
                            $stmt->execute([$id]);
                            $deleted = $stmt->rowCount();
                            if ($deleted > 0) {
                            }
                        }
                    } catch (Exception $e) {
                        error_log("Error deleting from {$table}: " . $e->getMessage());
                        // Continue even if this fails (table might not exist or have different structure)
                    }
                }
                
                // Temporarily disable foreign key checks to allow deletion
                // This is safe because we've already cleaned up related records above
                try {
                    $this->conn->exec("SET FOREIGN_KEY_CHECKS = 0");
                } catch (Exception $e) {
                    error_log("⚠️ Could not disable foreign key checks: " . $e->getMessage());
                }
            }
            
            // Always perform actual deletion (hard delete)
                $stmt = $this->conn->prepare("DELETE FROM `{$this->table}` WHERE `{$pkColumn}` = ?");
                $stmt->execute([$id]);
            
            // Re-enable foreign key checks if we disabled them
            if ($this->table === 'users') {
                try {
                    $this->conn->exec("SET FOREIGN_KEY_CHECKS = 1");
                } catch (Exception $e) {
                }
            }
            
            // Check if deletion was successful
            if ($stmt->rowCount() === 0) {
                sendResponse(["success"=>false,"message"=>"Record not found or already deleted"],404);
                return;
            }
            
            // Log history
            $this->logHistory('delete', (string)$id, $deletedData, null);
            
            sendResponse(["success"=>true,"message"=>"Deleted successfully"]);
        } catch(Exception $e) {
            error_log("❌ Delete failed for {$this->table} ID {$id}: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            sendResponse(["success"=>false,"message"=>"Delete failed: ".$e->getMessage()],500);
        }
    }
    public function getStats() {
        try {
            // Ensure table exists - create it if needed
            $this->ensureTableExists();
            
            // If table doesn't exist, return zero stats (handles both core and settings tables gracefully)
            if (!$this->tableExists()) {
                sendResponse(["success"=>true,"data"=>["total"=>0,"active"=>0,"inactive"=>0,"today"=>0,"thisWeek"=>0,"thisMonth"=>0]]);
                return;
            }
            $hasStatus = $this->columnExists('status');
            $hasIsActive = $this->columnExists('is_active');
            $isUsers = ($this->table === 'users');
            if ($isUsers) {
                $this->ensureColumnsExist();
            }
            $tenantU = $isUsers ? $this->usersListTenantWhere('u') : ['sql' => '', 'params' => []];
            $tenantSql = $tenantU['sql'];
            $tenantParams = $tenantU['params'];
            $fromSql = $isUsers && $tenantSql !== '' ? '`users` u' : "`{$this->table}`";
            $colPrefix = ($isUsers && $tenantSql !== '') ? 'u.' : '';

            // Build WHERE clause to exclude deleted records (+ per-agency scope for users)
            $whereParts = [];
            $execParams = [];
            if ($hasStatus) {
                $whereParts[] = "{$colPrefix}status != 'deleted'";
            }
            if ($tenantSql !== '') {
                $whereParts[] = '(' . $tenantSql . ')';
                $execParams = array_merge($execParams, $tenantParams);
            }
            $whereClause = empty($whereParts) ? '' : ' WHERE ' . implode(' AND ', $whereParts);

            // Get total count (excluding deleted records)
            $sql = "SELECT COUNT(*) as total FROM {$fromSql}" . $whereClause;
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($execParams);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $total = (int)$row["total"];

            // Get active count: when both status and is_active exist (e.g. users), count either — matches UI + login
            $active = 0;
            if ($hasStatus && $hasIsActive) {
                $activeCond = "(LOWER(TRIM(COALESCE({$colPrefix}status,''))) = 'active' OR {$colPrefix}is_active = 1)";
                $activeWhere = $whereClause ? $whereClause . " AND " . $activeCond : " WHERE " . $activeCond;
                $sql = "SELECT COUNT(*) as count FROM {$fromSql}" . $activeWhere;
                $stmt = $this->conn->prepare($sql);
                $stmt->execute($execParams);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $active = (int)$row["count"];
            } elseif ($hasStatus) {
                $activeWhere = $whereClause ? $whereClause . " AND LOWER(TRIM(COALESCE({$colPrefix}status,'')))='active'" : " WHERE LOWER(TRIM(COALESCE({$colPrefix}status,'')))='active'";
                $sql = "SELECT COUNT(*) as count FROM {$fromSql}" . $activeWhere;
                $stmt = $this->conn->prepare($sql);
                $stmt->execute($execParams);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $active = (int)$row["count"];
            } elseif ($hasIsActive) {
                $activeWhere = $whereClause ? $whereClause . " AND {$colPrefix}is_active=1" : " WHERE {$colPrefix}is_active=1";
                $sql = "SELECT COUNT(*) as count FROM {$fromSql}" . $activeWhere;
                $stmt = $this->conn->prepare($sql);
                $stmt->execute($execParams);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $active = (int)$row["count"];
            }

            // Inactive = total - active
            $inactive = $total - $active;

            // Get today's records (excluding deleted)
            $today = 0;
            if ($this->columnExists('created_at')) {
                $todayWhere = $whereClause ? $whereClause . " AND DATE({$colPrefix}created_at) = CURDATE()" : " WHERE DATE({$colPrefix}created_at) = CURDATE()";
                $sql = "SELECT COUNT(*) as count FROM {$fromSql}" . $todayWhere;
                $stmt = $this->conn->prepare($sql);
                $stmt->execute($execParams);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $today = (int)$row["count"];
            }

            // Get this week's records (last 7 days, excluding deleted)
            $thisWeek = 0;
            if ($this->columnExists('created_at')) {
                $weekWhere = $whereClause ? $whereClause . " AND {$colPrefix}created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)" : " WHERE {$colPrefix}created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                $sql = "SELECT COUNT(*) as count FROM {$fromSql}" . $weekWhere;
                $stmt = $this->conn->prepare($sql);
                $stmt->execute($execParams);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $thisWeek = (int)$row["count"];
            }

            // Get this month's records (excluding deleted)
            $thisMonth = 0;
            if ($this->columnExists('created_at')) {
                $monthWhere = $whereClause ? $whereClause . " AND MONTH({$colPrefix}created_at) = MONTH(NOW()) AND YEAR({$colPrefix}created_at) = YEAR(NOW())" : " WHERE MONTH({$colPrefix}created_at) = MONTH(NOW()) AND YEAR({$colPrefix}created_at) = YEAR(NOW())";
                $sql = "SELECT COUNT(*) as count FROM {$fromSql}" . $monthWhere;
                $stmt = $this->conn->prepare($sql);
                $stmt->execute($execParams);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $thisMonth = (int)$row["count"];
            }

            sendResponse(["success"=>true,"data"=>["total"=>$total,"active"=>$active,"inactive"=>$inactive,"today"=>$today,"thisWeek"=>$thisWeek,"thisMonth"=>$thisMonth]]);
        } catch(Exception $e){
            sendResponse(["success"=>false,"message"=>"Stats failed: ".$e->getMessage()],500);
        }
    }
    private function tableExists() {
        try {
            if (!$this->conn) {
                return false;
            }
            
            // Get current database name
            $dbStmt = $this->conn->query("SELECT DATABASE() as db_name");
            $dbRow = $dbStmt->fetch(PDO::FETCH_ASSOC);
            $dbName = $dbRow['db_name'] ?? null;
            
            // Use INFORMATION_SCHEMA for more reliable checking
            if ($dbName) {
                $sql = "SELECT COUNT(*) as count 
                        FROM INFORMATION_SCHEMA.TABLES 
                        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute([$dbName, $this->table]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return isset($result['count']) && (int)$result['count'] > 0;
            } else {
                // Fallback to SHOW TABLES if database name can't be determined
            $stmt = $this->conn->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$this->table]);
                $result = $stmt->fetch();
                return $result !== false;
            }
        } catch (Exception $e) {
            error_log("tableExists() error for table '{$this->table}': " . $e->getMessage());
            return false;
        } catch (Throwable $e) {
            error_log("tableExists() fatal error for table '{$this->table}': " . $e->getMessage());
            return false;
        }
    }
    
    // Ensure table exists - create it if it doesn't
    // Returns: true if table exists or was created, false if creation failed, throws exception on critical errors
    private function ensureTableExists() {
        // Skip if table already exists
        if ($this->tableExists()) {
            return true;
        }
        
        // Tables that shouldn't be auto-created (core system tables)
        $skipTables = ['users', 'agents', 'workers', 'cases', 'subagents', 'hr_employees'];
        $tableLower = strtolower(trim($this->table));
        foreach ($skipTables as $skipTable) {
            if (strtolower(trim($skipTable)) === $tableLower) {
                return false; // Don't auto-create core system tables
            }
        }
        
        // Check database connection
        if (!$this->conn) {
            error_log("Cannot create table '{$this->table}': Database connection is null");
            throw new Exception("Database connection is null");
        }
        
        // Create table based on name - use normalized table name for matching
        $tableNormalized = strtolower(trim($this->table));
        
        error_log("ensureTableExists: Attempting to create table '{$this->table}' (normalized: '{$tableNormalized}')");
        
        $tableCreated = false;
        $creationException = null;
        
        try {
            switch ($tableNormalized) {
                case 'currencies':
                    $this->createCurrenciesTable();
                    $tableCreated = true;
                    break;
                case 'recruitment_countries':
                    $this->createRecruitmentCountriesTable();
                    $tableCreated = true;
                    break;
                case 'visa_types':
                    $this->createVisaTypesTable();
                    $tableCreated = true;
                    break;
                case 'job_categories':
                    $this->createJobCategoriesTable();
                    $tableCreated = true;
                    break;
                case 'age_specifications':
                    $this->createAgeSpecificationsTable();
                    $tableCreated = true;
                    break;
                case 'appearance_specifications':
                    $this->createAppearanceSpecificationsTable();
                    $tableCreated = true;
                    break;
                case 'status_specifications':
                    $this->createStatusSpecificationsTable();
                    $tableCreated = true;
                    break;
                case 'request_statuses':
                    $this->createRequestStatusesTable();
                    $tableCreated = true;
                    break;
                case 'arrival_agencies':
                    $this->createArrivalAgenciesTable();
                    $tableCreated = true;
                    break;
                case 'arrival_stations':
                    $this->createArrivalStationsTable();
                    $tableCreated = true;
                    break;
                case 'worker_statuses':
                    $this->createWorkerStatusesTable();
                    $tableCreated = true;
                    break;
                case 'office_managers':
                    $this->createOfficeManagersTable();
                    $tableCreated = true;
                    break;
                case 'system_config':
                    $this->createSystemConfigTable();
                    $tableCreated = true;
                    break;
                default:
                    error_log("ensureTableExists: Unknown table '{$this->table}' (normalized: '{$tableNormalized}') - attempting generic creation");
                    // Try to create a generic table structure as fallback for settings tables
                    try {
                        $this->createGenericSettingsTable();
                        $tableCreated = true;
                    } catch (Exception $e) {
                        $creationException = $e;
                        error_log("Generic table creation failed for '{$this->table}': " . $e->getMessage());
                    }
                    break;
            }
            
            if ($tableCreated) {
                // Verify table was created successfully
                $verified = false;
                for ($i = 0; $i < 3; $i++) {
                    $verified = $this->tableExists();
                    if ($verified) {
                        break;
                    }
                    if ($i < 2) {
                        usleep(200000); // Wait 0.2 seconds before retry
                    }
                }
                
                if ($verified) {
                    error_log("✅ Successfully created and verified table: '{$this->table}'");
                    return true;
                } else {
                    error_log("❌ Warning: Table '{$this->table}' was not found after creation attempt");
                    throw new Exception("Table '{$this->table}' creation failed - table not found after CREATE statement");
                }
            } else {
                if ($creationException) {
                    throw $creationException;
                }
                error_log("⚠️ ensureTableExists: Table '{$this->table}' creation was not attempted - no matching case");
                return false;
            }
        } catch (PDOException $e) {
            error_log("PDO Exception ensuring table exists for '{$this->table}': " . $e->getMessage());
            error_log("PDO Error Code: " . $e->getCode());
            error_log("Stack trace: " . $e->getTraceAsString());
            throw $e; // Re-throw so calling method can handle it
        } catch (Exception $e) {
            error_log("Exception ensuring table exists for '{$this->table}': " . $e->getMessage());
            error_log("Exception type: " . get_class($e));
            error_log("Stack trace: " . $e->getTraceAsString());
            throw $e; // Re-throw so calling method can handle it
        } catch (Throwable $e) {
            error_log("Fatal error ensuring table exists for '{$this->table}': " . $e->getMessage());
            error_log("Error type: " . get_class($e));
            error_log("Stack trace: " . $e->getTraceAsString());
            throw $e; // Re-throw so calling method can handle it
        }
    }
    
    private function createCurrenciesTable() {
        try {
            $createTable = "CREATE TABLE IF NOT EXISTS currencies (
                id INT PRIMARY KEY AUTO_INCREMENT,
                code VARCHAR(3) NOT NULL UNIQUE,
                name VARCHAR(100) NOT NULL,
                symbol VARCHAR(10) NULL,
                is_active TINYINT(1) DEFAULT 1,
                display_order INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_code (code),
                INDEX idx_active (is_active),
                INDEX idx_order (display_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $result = $this->conn->exec($createTable);
            if ($result === false) {
                $errorInfo = $this->conn->errorInfo();
                error_log("createCurrenciesTable: exec() returned false. Error: " . ($errorInfo[2] ?? 'Unknown error'));
                throw new Exception("Failed to execute CREATE TABLE for currencies: " . ($errorInfo[2] ?? 'Unknown error'));
            }
            error_log("createCurrenciesTable: exec returned " . var_export($result, true));
            
            // Check if table is empty, then insert default currencies
            $checkStmt = $this->conn->query("SELECT COUNT(*) as count FROM currencies");
            $row = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if ($row && intval($row['count']) == 0) {
                // Insert default currencies
                $defaultCurrencies = [
                    ['SAR', 'Saudi Riyal', '﷼', 1],
                    ['USD', 'US Dollar', '$', 2],
                    ['EUR', 'Euro', '€', 3],
                    ['GBP', 'British Pound', '£', 4],
                    ['CAD', 'Canadian Dollar', 'C$', 5],
                    ['AUD', 'Australian Dollar', 'A$', 6],
                    ['AED', 'UAE Dirham', 'د.إ', 7],
                    ['KWD', 'Kuwaiti Dinar', 'د.ك', 8],
                    ['QAR', 'Qatari Riyal', '﷼', 9],
                    ['BHD', 'Bahraini Dinar', '.د.ب', 10],
                    ['OMR', 'Omani Rial', 'ر.ع.', 11],
                    ['JOD', 'Jordanian Dinar', 'د.ا', 12],
                    ['EGP', 'Egyptian Pound', '£', 13],
                    ['JPY', 'Japanese Yen', '¥', 14],
                    ['CNY', 'Chinese Yuan', '¥', 15],
                    ['INR', 'Indian Rupee', '₹', 16],
                    ['PKR', 'Pakistani Rupee', '₨', 17],
                    ['BDT', 'Bangladeshi Taka', '৳', 18],
                    ['PHP', 'Philippine Peso', '₱', 19],
                    ['IDR', 'Indonesian Rupiah', 'Rp', 20],
                    ['THB', 'Thai Baht', '฿', 21],
                    ['MYR', 'Malaysian Ringgit', 'RM', 22],
                    ['SGD', 'Singapore Dollar', 'S$', 23],
                    ['KRW', 'South Korean Won', '₩', 24],
                    ['BRL', 'Brazilian Real', 'R$', 25],
                    ['MXN', 'Mexican Peso', '$', 26],
                    ['TRY', 'Turkish Lira', '₺', 27],
                    ['ZAR', 'South African Rand', 'R', 28]
                ];
                
                $stmt = $this->conn->prepare("INSERT INTO currencies (code, name, symbol, display_order) VALUES (?, ?, ?, ?)");
                foreach ($defaultCurrencies as $currency) {
                    $stmt->execute([$currency[0], $currency[1], $currency[2], $currency[3]]);
                }
            }
        } catch (Exception $e) {
            error_log("Error creating currencies table: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function createRecruitmentCountriesTable() {
        if (!$this->conn) {
            error_log("Cannot create recruitment_countries table: Database connection is null");
            throw new Exception("Database connection is null");
        }
        
        try {
            $createTable = "CREATE TABLE IF NOT EXISTS recruitment_countries (
                id INT PRIMARY KEY AUTO_INCREMENT,
                country_name VARCHAR(100) NOT NULL,
                country_description TEXT NULL,
                country_code VARCHAR(10) NULL,
                currency VARCHAR(10) NULL,
                flag_emoji VARCHAR(10) NULL,
                city VARCHAR(100) NULL,
                country_id INT NULL,
                status VARCHAR(20) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_country_name (country_name),
                INDEX idx_country_code (country_code),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            // Get database name for verification
            $dbStmt = $this->conn->query("SELECT DATABASE() as db_name");
            $dbRow = $dbStmt->fetch(PDO::FETCH_ASSOC);
            $dbName = $dbRow['db_name'] ?? 'unknown';
            error_log("Creating recruitment_countries table in database: {$dbName}");
            
            // First, check if we have CREATE privilege by trying a test query
            try {
                $testPrivStmt = $this->conn->query("SELECT 1 FROM INFORMATION_SCHEMA.USER_PRIVILEGES WHERE GRANTEE = CONCAT('\\'', SUBSTRING_INDEX(CURRENT_USER(), '@', 1), '\\'@\\'', SUBSTRING_INDEX(CURRENT_USER(), '@', -1), '\\'') AND PRIVILEGE_TYPE = 'CREATE'");
                $hasCreate = $testPrivStmt->fetch() !== false;
                if (!$hasCreate) {
                    // Check for ALL PRIVILEGES
                    $allPrivStmt = $this->conn->query("SELECT 1 FROM INFORMATION_SCHEMA.USER_PRIVILEGES WHERE GRANTEE = CONCAT('\\'', SUBSTRING_INDEX(CURRENT_USER(), '@', 1), '\\'@\\'', SUBSTRING_INDEX(CURRENT_USER(), '@', -1), '\\'') AND PRIVILEGE_TYPE = 'ALL PRIVILEGES'");
                    $hasAllPrivs = $allPrivStmt->fetch() !== false;
                    if (!$hasAllPrivs) {
                        error_log("⚠️ WARNING: User may not have CREATE TABLE privilege. Attempting CREATE anyway...");
                    }
                }
            } catch (Exception $e) {
                error_log("Could not check CREATE privilege: " . $e->getMessage() . " - Proceeding anyway...");
            }
            
            // Execute CREATE TABLE with better error handling
            try {
                $result = $this->conn->exec($createTable);
            } catch (PDOException $e) {
                // Check if it's a permission error
                if (strpos($e->getMessage(), 'Access denied') !== false || strpos($e->getMessage(), 'privilege') !== false || strpos($e->getCode(), '1142') !== false) {
                    error_log("❌ CREATE TABLE permission denied for recruitment_countries");
                    throw new Exception("Database user does not have CREATE TABLE permission. Please grant CREATE privilege to the database user. Error: " . $e->getMessage());
                }
                throw $e; // Re-throw other PDO exceptions
            }
            
            // Check for errors even if exec() doesn't throw (some errors don't throw exceptions)
            $errorInfo = $this->conn->errorInfo();
            if ($result === false || (isset($errorInfo[0]) && $errorInfo[0] !== '00000')) {
                $errorMsg = "Failed to execute CREATE TABLE for recruitment_countries. ";
                $errorMsg .= "Code: " . ($errorInfo[0] ?? 'N/A') . ", SQL State: " . ($errorInfo[1] ?? 'N/A') . ", Error: " . ($errorInfo[2] ?? 'Unknown error');
                error_log("createRecruitmentCountriesTable: exec() error. " . $errorMsg);
                throw new Exception($errorMsg);
            }
            
            // Check for warnings (MySQL might report warnings that don't throw exceptions)
            $warningStmt = $this->conn->query("SHOW WARNINGS");
            $warnings = $warningStmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($warnings)) {
                error_log("createRecruitmentCountriesTable: MySQL warnings: " . json_encode($warnings));
                foreach ($warnings as $warning) {
                    if (isset($warning['Level']) && strtoupper($warning['Level']) === 'ERROR') {
                        throw new Exception("MySQL error during CREATE TABLE for recruitment_countries: " . ($warning['Message'] ?? 'Unknown error'));
                    }
                }
            }
            
            error_log("createRecruitmentCountriesTable: exec returned " . var_export($result, true) . ", Error Info: " . json_encode($errorInfo));
            
            // Verify table was created - try multiple methods
            $exists = false;
            for ($i = 0; $i < 3; $i++) {
                // Method 1: Try INFORMATION_SCHEMA
                try {
                    $sql = "SELECT COUNT(*) as count 
                            FROM INFORMATION_SCHEMA.TABLES 
                            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?";
                    $stmt = $this->conn->prepare($sql);
                    $stmt->execute([$dbName, 'recruitment_countries']);
                    $checkResult = $stmt->fetch(PDO::FETCH_ASSOC);
                    $exists = isset($checkResult['count']) && (int)$checkResult['count'] > 0;
                    if ($exists) {
                        error_log("✅ recruitment_countries table verified via INFORMATION_SCHEMA in database: {$dbName}");
                        break;
                    }
                } catch (Exception $e) {
                    error_log("INFORMATION_SCHEMA check failed: " . $e->getMessage());
                }
                
                // Method 2: Try SHOW TABLES
                try {
                    $stmt = $this->conn->prepare("SHOW TABLES LIKE 'recruitment_countries'");
                    $stmt->execute();
                    $showResult = $stmt->fetch();
                    if ($showResult !== false) {
                        $exists = true;
                        error_log("✅ recruitment_countries table verified via SHOW TABLES in database: {$dbName}");
                        break;
                    }
                } catch (Exception $e) {
                    error_log("SHOW TABLES check failed: " . $e->getMessage());
                }
                
                // Method 3: Try direct SELECT (if table exists, this won't fail with "table doesn't exist")
                try {
                    $testStmt = $this->conn->query("SELECT 1 FROM recruitment_countries LIMIT 1");
                    $exists = true;
                    error_log("✅ recruitment_countries table verified via direct SELECT in database: {$dbName}");
                    break;
                } catch (PDOException $e) {
                    // If error is about table not existing, it's expected
                    if (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), "Table") !== false) {
                        $exists = false;
                    } else {
                        // Other errors mean table might exist but has other issues
                        error_log("Direct SELECT check error (table might exist): " . $e->getMessage());
                    }
                }
                
                if ($i < 2) {
                    usleep(100000); // Wait 0.1 seconds before retry
                }
            }
            
            if (!$exists) {
                // Final check: List all tables to see what's there
                try {
                    $allTablesStmt = $this->conn->query("SHOW TABLES");
                    $allTables = $allTablesStmt->fetchAll(PDO::FETCH_COLUMN);
                    error_log("Available tables in database '{$dbName}': " . implode(', ', $allTables));
                    
                    // Check for case-sensitive matches
                    $lowerTables = array_map('strtolower', $allTables);
                    if (in_array('recruitment_countries', $lowerTables)) {
                        $actualName = $allTables[array_search('recruitment_countries', $lowerTables)];
                        error_log("Found case-sensitive match: '{$actualName}'");
                        throw new Exception("Table 'recruitment_countries' exists as '{$actualName}' (case mismatch)");
                    }
                } catch (Exception $e) {
                    if (strpos($e->getMessage(), 'case') !== false) {
                        throw $e; // Re-throw case mismatch errors
                    }
                    error_log("Error listing tables: " . $e->getMessage());
                }
                
                    // Check user privileges to diagnose permission issues
                try {
                    $privilegeStmt = $this->conn->query("SHOW GRANTS FOR CURRENT_USER()");
                    $grants = $privilegeStmt->fetchAll(PDO::FETCH_COLUMN);
                    error_log("Current user grants: " . implode('; ', $grants));
                    
                    // Check if user has CREATE privilege on this database
                    $hasCreate = false;
                    foreach ($grants as $grant) {
                        if (stripos($grant, 'CREATE') !== false || stripos($grant, 'ALL PRIVILEGES') !== false || stripos($grant, 'CREATE TABLE') !== false) {
                            $hasCreate = true;
                            break;
                        }
                    }
                    if (!$hasCreate) {
                        error_log("⚠️ WARNING: Current user does NOT have CREATE TABLE privilege!");
                    }
                } catch (Exception $e) {
                    error_log("Could not check user privileges: " . $e->getMessage());
                }
                
                error_log("❌ recruitment_countries table creation failed - table does not exist after CREATE statement in database: {$dbName}");
                error_log("CREATE TABLE result: " . var_export($result, true));
                error_log("Error Info: " . json_encode($errorInfo));
                
                // Provide SQL for manual creation
                $sqlForManualCreation = "CREATE TABLE IF NOT EXISTS recruitment_countries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    country_name VARCHAR(100) NOT NULL,
    country_description TEXT NULL,
    country_code VARCHAR(10) NULL,
    currency VARCHAR(10) NULL,
    flag_emoji VARCHAR(10) NULL,
    city VARCHAR(100) NULL,
    country_id INT NULL,
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_country_name (country_name),
    INDEX idx_country_code (country_code),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
                
                error_log("SQL for manual creation:\n" . $sqlForManualCreation);
                
                $errorMsg = "Failed to create recruitment_countries table automatically. ";
                $errorMsg .= "Please create the table manually in phpMyAdmin. SQL statements are available in: api/settings/create-missing-tables.sql ";
                $errorMsg .= "Or grant CREATE TABLE permission to your database user. ";
                $errorMsg .= "Database: {$dbName}, User: " . (isset($_SERVER['DB_USER']) ? $_SERVER['DB_USER'] : 'current_user');
                throw new Exception($errorMsg);
            }
        } catch (PDOException $e) {
            error_log("PDO Error creating recruitment_countries table: " . $e->getMessage());
            error_log("Error Code: " . $e->getCode());
            throw $e;
        } catch (Exception $e) {
            error_log("Error creating recruitment_countries table: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function createVisaTypesTable() {
        if (!$this->conn) {
            error_log("Cannot create visa_types table: Database connection is null");
            throw new Exception("Database connection is null");
        }
        
        try {
            $createTable = "CREATE TABLE IF NOT EXISTS visa_types (
                id INT PRIMARY KEY AUTO_INCREMENT,
                visa_name VARCHAR(100) NOT NULL,
                name VARCHAR(100) NULL,
                visa_description TEXT NULL,
                description TEXT NULL,
                processing_time VARCHAR(50) NULL,
                validity_days INT NULL,
                fees DECIMAL(10, 2) DEFAULT 0.00,
                processing_fee DECIMAL(10, 2) NULL,
                requirements TEXT NULL,
                country_id INT NULL,
                country_name VARCHAR(150) NULL,
                city VARCHAR(100) NULL,
                position VARCHAR(100) NULL,
                status VARCHAR(20) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_visa_name (visa_name),
                INDEX idx_name (name),
                INDEX idx_status (status),
                INDEX idx_country_id (country_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            // Get database name for verification
            $dbStmt = $this->conn->query("SELECT DATABASE() as db_name");
            $dbRow = $dbStmt->fetch(PDO::FETCH_ASSOC);
            $dbName = $dbRow['db_name'] ?? 'unknown';
            error_log("Creating visa_types table in database: {$dbName}");
            
            $result = $this->conn->exec($createTable);
            
            // Check for errors even if exec() doesn't throw
            $errorInfo = $this->conn->errorInfo();
            if ($result === false || (isset($errorInfo[0]) && $errorInfo[0] !== '00000')) {
                $errorMsg = "Failed to execute CREATE TABLE for visa_types. ";
                $errorMsg .= "Code: " . ($errorInfo[0] ?? 'N/A') . ", SQL State: " . ($errorInfo[1] ?? 'N/A') . ", Error: " . ($errorInfo[2] ?? 'Unknown error');
                error_log("createVisaTypesTable: exec() error. " . $errorMsg);
                throw new Exception($errorMsg);
            }
            
            // Check for warnings (MySQL might report warnings that don't throw exceptions)
            $warningStmt = $this->conn->query("SHOW WARNINGS");
            $warnings = $warningStmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($warnings)) {
                error_log("createVisaTypesTable: MySQL warnings: " . json_encode($warnings));
                foreach ($warnings as $warning) {
                    if (isset($warning['Level']) && strtoupper($warning['Level']) === 'ERROR') {
                        throw new Exception("MySQL error during CREATE TABLE for visa_types: " . ($warning['Message'] ?? 'Unknown error'));
                    }
                }
            }
            
            error_log("createVisaTypesTable: exec returned " . var_export($result, true) . ", Error Info: " . json_encode($errorInfo));
            
            // Verify table was created using INFORMATION_SCHEMA
            $exists = false;
            for ($i = 0; $i < 3; $i++) {
                $sql = "SELECT COUNT(*) as count 
                        FROM INFORMATION_SCHEMA.TABLES 
                        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute([$dbName, 'visa_types']);
                $checkResult = $stmt->fetch(PDO::FETCH_ASSOC);
                $exists = isset($checkResult['count']) && (int)$checkResult['count'] > 0;
                if ($exists) {
                    break;
                }
                if ($i < 2) {
                    usleep(100000); // Wait 0.1 seconds before retry
                }
            }
            
            if ($exists) {
                error_log("✅ visa_types table created and verified successfully in database: {$dbName}");
            } else {
                // Also try SHOW TABLES as fallback
                $stmt = $this->conn->prepare("SHOW TABLES LIKE 'visa_types'");
                $stmt->execute();
                $showResult = $stmt->fetch();
                if ($showResult !== false) {
                    error_log("✅ visa_types table exists (verified with SHOW TABLES)");
                } else {
                    error_log("❌ visa_types table creation failed - table does not exist after CREATE statement in database: {$dbName}");
                    $errorMsg = "Failed to create visa_types table automatically. ";
                    $errorMsg .= "Please create the table manually in phpMyAdmin. SQL statements are available in: api/settings/create-missing-tables.sql ";
                    $errorMsg .= "Or grant CREATE TABLE permission to your database user. ";
                    $errorMsg .= "Database: {$dbName}";
                    throw new Exception($errorMsg);
                }
            }
        } catch (PDOException $e) {
            error_log("PDO Error creating visa_types table: " . $e->getMessage());
            error_log("Error Code: " . $e->getCode());
            throw $e;
        } catch (Exception $e) {
            error_log("Error creating visa_types table: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function createJobCategoriesTable() {
        try {
            $createTable = "CREATE TABLE IF NOT EXISTS job_categories (
                id INT PRIMARY KEY AUTO_INCREMENT,
                category_name VARCHAR(100) NOT NULL,
                name VARCHAR(100) NULL,
                category_description TEXT NULL,
                description TEXT NULL,
                min_salary DECIMAL(10, 2) NULL,
                max_salary DECIMAL(10, 2) NULL,
                salary_range VARCHAR(100) NULL,
                requirements TEXT NULL,
                country_id INT NULL,
                city VARCHAR(100) NULL,
                status VARCHAR(20) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_category_name (category_name),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->conn->exec($createTable);
        } catch (Exception $e) {
            error_log("Error creating job_categories table: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function createAgeSpecificationsTable() {
        try {
            $createTable = "CREATE TABLE IF NOT EXISTS age_specifications (
                id INT PRIMARY KEY AUTO_INCREMENT,
                age_range VARCHAR(100) NOT NULL,
                name VARCHAR(100) NULL,
                age_description TEXT NULL,
                description TEXT NULL,
                min_age INT NULL,
                max_age INT NULL,
                country_id INT NULL,
                city VARCHAR(100) NULL,
                status VARCHAR(20) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_age_range (age_range),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->conn->exec($createTable);
        } catch (Exception $e) {
            error_log("Error creating age_specifications table: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function createAppearanceSpecificationsTable() {
        try {
            $createTable = "CREATE TABLE IF NOT EXISTS appearance_specifications (
                id INT PRIMARY KEY AUTO_INCREMENT,
                specification_name VARCHAR(100) NOT NULL,
                name VARCHAR(100) NULL,
                spec_name VARCHAR(100) NULL,
                specification_description TEXT NULL,
                description TEXT NULL,
                country_id INT NULL,
                city VARCHAR(100) NULL,
                status VARCHAR(20) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_spec_name (specification_name),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->conn->exec($createTable);
        } catch (Exception $e) {
            error_log("Error creating appearance_specifications table: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function createStatusSpecificationsTable() {
        try {
            $createTable = "CREATE TABLE IF NOT EXISTS status_specifications (
                id INT PRIMARY KEY AUTO_INCREMENT,
                status_name VARCHAR(100) NOT NULL,
                name VARCHAR(100) NULL,
                status_description TEXT NULL,
                description TEXT NULL,
                country_id INT NULL,
                city VARCHAR(100) NULL,
                status VARCHAR(20) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_status_name (status_name),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->conn->exec($createTable);
        } catch (Exception $e) {
            error_log("Error creating status_specifications table: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function createRequestStatusesTable() {
        try {
            $createTable = "CREATE TABLE IF NOT EXISTS request_statuses (
                id INT PRIMARY KEY AUTO_INCREMENT,
                status_name VARCHAR(100) NOT NULL,
                name VARCHAR(100) NULL,
                request_status_name VARCHAR(100) NULL,
                status_description TEXT NULL,
                description TEXT NULL,
                country_id INT NULL,
                city VARCHAR(100) NULL,
                status VARCHAR(20) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_status_name (status_name),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->conn->exec($createTable);
        } catch (Exception $e) {
            error_log("Error creating request_statuses table: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function createArrivalAgenciesTable() {
        try {
            $createTable = "CREATE TABLE IF NOT EXISTS arrival_agencies (
                id INT PRIMARY KEY AUTO_INCREMENT,
                agency_name VARCHAR(100) NOT NULL,
                name VARCHAR(100) NULL,
                agency_description TEXT NULL,
                description TEXT NULL,
                contact_info TEXT NULL,
                country_id INT NULL,
                city VARCHAR(100) NULL,
                status VARCHAR(20) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_agency_name (agency_name),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->conn->exec($createTable);
        } catch (Exception $e) {
            error_log("Error creating arrival_agencies table: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function createArrivalStationsTable() {
        try {
            $createTable = "CREATE TABLE IF NOT EXISTS arrival_stations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                station_name VARCHAR(100) NOT NULL,
                name VARCHAR(100) NULL,
                station_description TEXT NULL,
                description TEXT NULL,
                country_id INT NULL,
                city VARCHAR(100) NULL,
                status VARCHAR(20) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_station_name (station_name),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->conn->exec($createTable);
        } catch (Exception $e) {
            error_log("Error creating arrival_stations table: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function createWorkerStatusesTable() {
        try {
            $createTable = "CREATE TABLE IF NOT EXISTS worker_statuses (
                id INT PRIMARY KEY AUTO_INCREMENT,
                status_name VARCHAR(100) NOT NULL,
                name VARCHAR(100) NULL,
                status_description TEXT NULL,
                description TEXT NULL,
                country_id INT NULL,
                city VARCHAR(100) NULL,
                status VARCHAR(20) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_status_name (status_name),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->conn->exec($createTable);
        } catch (Exception $e) {
            error_log("Error creating worker_statuses table: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function createOfficeManagersTable() {
        try {
            $createTable = "CREATE TABLE IF NOT EXISTS office_managers (
                id INT PRIMARY KEY AUTO_INCREMENT,
                manager_name VARCHAR(100) NOT NULL,
                name VARCHAR(100) NULL,
                full_name VARCHAR(100) NULL,
                country_id INT NULL,
                city VARCHAR(100) NULL,
                position VARCHAR(100) NULL,
                status VARCHAR(20) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_manager_name (manager_name),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->conn->exec($createTable);
        } catch (Exception $e) {
            error_log("Error creating office_managers table: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function createSystemConfigTable() {
        try {
            if (!$this->conn) {
                error_log("Cannot create system_config table: Database connection is null");
                throw new Exception("Database connection is null");
            }
            
            $createTable = "CREATE TABLE IF NOT EXISTS system_config (
                id INT PRIMARY KEY AUTO_INCREMENT,
                config_key VARCHAR(100) NOT NULL,
                name VARCHAR(100) NULL,
                config_name VARCHAR(100) NULL,
                config_value TEXT NULL,
                description TEXT NULL,
                value TEXT NULL,
                country_id INT NULL,
                city VARCHAR(100) NULL,
                status VARCHAR(20) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_config_key (config_key),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $result = $this->conn->exec($createTable);
            if ($result === false) {
                $errorInfo = $this->conn->errorInfo();
                error_log("createSystemConfigTable: exec() returned false. Error: " . ($errorInfo[2] ?? 'Unknown error'));
                throw new Exception("Failed to execute CREATE TABLE for system_config: " . ($errorInfo[2] ?? 'Unknown error'));
            }
            
            // Verify table was created
            $stmt = $this->conn->prepare("SHOW TABLES LIKE 'system_config'");
            $stmt->execute();
            $exists = $stmt->fetch() !== false;
            if (!$exists) {
                throw new Exception("Failed to create system_config table - table not found after creation attempt");
            }
            error_log("✅ system_config table created successfully");
        } catch (Exception $e) {
            error_log("Error creating system_config table: " . $e->getMessage());
            throw $e;
        }
    }
    
    // Generic table creation method for unknown settings tables
    private function createGenericSettingsTable() {
        if (!$this->conn) {
            throw new Exception("Database connection is null");
        }
        
        try {
            $createTable = "CREATE TABLE IF NOT EXISTS `{$this->table}` (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                description TEXT NULL,
                status VARCHAR(20) DEFAULT 'active',
                country_id INT NULL,
                city VARCHAR(100) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_name (name),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $result = $this->conn->exec($createTable);
            if ($result === false) {
                $errorInfo = $this->conn->errorInfo();
                throw new Exception("Failed to execute CREATE TABLE for {$this->table}: " . ($errorInfo[2] ?? 'Unknown error'));
            }
            
            error_log("✅ Generic table '{$this->table}' created successfully");
        } catch (Exception $e) {
            error_log("Error creating generic table '{$this->table}': " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Logged-in country (Ratib Pro session). Used to list/edit only users for this country.
     */
    private function usersSessionCountryId(): int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return 0;
        }
        return isset($_SESSION['country_id']) ? (int) $_SESSION['country_id'] : 0;
    }

    /**
     * Current agency (control_agencies.id) from program session — separates users when DB is shared.
     */
    private function usersSessionAgencyId(): int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return 0;
        }
        return isset($_SESSION['agency_id']) ? (int) $_SESSION['agency_id'] : 0;
    }

    /**
     * WHERE clause + params for users list: same country as session, or country not set (tenant-local).
     */
    private function usersListCountryWhere(string $alias = 'u'): array
    {
        $cid = $this->usersSessionCountryId();
        if ($cid <= 0 || $this->table !== 'users' || !$this->columnExists('country_id')) {
            return ['sql' => '', 'params' => []];
        }
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
        if ($a === '') {
            $a = 'u';
        }
        return [
            'sql' => "({$a}.country_id IS NULL OR {$a}.country_id = 0 OR {$a}.country_id = ?)",
            'params' => [$cid],
        ];
    }

    /**
     * Country + agency scope for shared tenant databases.
     */
    private function usersListTenantWhere(string $alias = 'u'): array
    {
        if ($this->table !== 'users') {
            return ['sql' => '', 'params' => []];
        }
        $parts = [];
        $params = [];
        $cw = $this->usersListCountryWhere($alias);
        if ($cw['sql'] !== '') {
            $parts[] = $cw['sql'];
            $params = array_merge($params, $cw['params']);
        }
        if ($this->columnExists('agency_id')) {
            $aid = $this->usersSessionAgencyId();
            if ($aid > 0) {
                $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
                if ($a === '') {
                    $a = 'u';
                }
                $parts[] = "({$a}.agency_id = ?)";
                $params[] = $aid;
            }
        }
        if (empty($parts)) {
            return ['sql' => '', 'params' => []];
        }
        return ['sql' => implode(' AND ', $parts), 'params' => $params];
    }

    /**
     * Whether a users row may be read/updated/deleted in the current session (per-country scope).
     */
    private function usersRowAllowedForSessionCountry(array $row): bool
    {
        if ($this->table === 'users' && $this->columnExists('agency_id')) {
            $aid = $this->usersSessionAgencyId();
            if ($aid > 0) {
                $rawA = $row['agency_id'] ?? null;
                $uaid = ($rawA !== null && $rawA !== '' && (int) $rawA > 0) ? (int) $rawA : 0;
                if ($uaid !== $aid) {
                    return false;
                }
            }
        }
        $cid = $this->usersSessionCountryId();
        if ($cid <= 0 || $this->table !== 'users' || !$this->columnExists('country_id')) {
            return true;
        }
        $raw = $row['country_id'] ?? null;
        if ($raw === null || $raw === '' || (int) $raw === 0) {
            return true;
        }
        return (int) $raw === $cid;
    }

    private function columnExists($col) {
        try {
            $stmt = $this->conn->prepare("SHOW COLUMNS FROM `{$this->table}` LIKE ?");
            $stmt->execute([$col]);
            return $stmt->fetch() !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Ensure users/control_admins password columns can store modern hashes.
     */
    private function ensurePasswordColumnsCapacity(): void
    {
        if (!$this->conn || ($this->table !== 'users' && $this->table !== 'control_admins')) {
            return;
        }
        foreach (['password', 'pass'] as $col) {
            if (!$this->columnExists($col)) {
                continue;
            }
            try {
                $stmt = $this->conn->prepare("SHOW COLUMNS FROM `{$this->table}` LIKE ?");
                $stmt->execute([$col]);
                $meta = $stmt->fetch(PDO::FETCH_ASSOC);
                $type = strtolower((string)($meta['Type'] ?? ''));
                $needsWiden = false;
                if (preg_match('/^varchar\((\d+)\)/', $type, $m)) {
                    $needsWiden = ((int)$m[1] < 255);
                } elseif (preg_match('/^char\((\d+)\)/', $type, $m)) {
                    $needsWiden = ((int)$m[1] < 255);
                }
                if ($needsWiden) {
                    $this->conn->exec("ALTER TABLE `{$this->table}` MODIFY COLUMN `{$col}` VARCHAR(255) NULL");
                }
            } catch (Throwable $e) {
                error_log("Password column capacity check failed for {$this->table}.{$col}: " . $e->getMessage());
            }
        }
    }
    
    private function getExistingColumns() {
        try {
            $stmt = $this->conn->prepare("SHOW COLUMNS FROM `{$this->table}`");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $cols = array();
            foreach ($rows as $r) {
                if (isset($r['Field'])) {
                    $cols[] = $r['Field'];
                }
            }
            return $cols;
        } catch (Exception $e) {
            return array();
        }
    }
    
    private function mapFieldsToColumns($data, $existingCols) {
        $aliasMap = $this->getAliasMap();
        $mapped = array();
        
        foreach ($data as $formKey => $value) {
            if (in_array($formKey, $existingCols)) {
                $mapped[$formKey] = $value;
                continue;
            }
            
            $found = false;
            if (isset($aliasMap[$formKey])) {
                $candidates = is_array($aliasMap[$formKey]) ? $aliasMap[$formKey] : array($aliasMap[$formKey]);
                foreach ($candidates as $candidate) {
                    if (in_array($candidate, $existingCols)) {
                        $mapped[$candidate] = $value;
                        $found = true;
                        break;
                    }
                }
            }
            
            if (!$found && !in_array($formKey, $existingCols)) {
                continue;
            }
        }
        
        return $mapped;
    }
    
    private function getAliasMap() {
        $table = $this->table;
        
        // Table-specific mappings first
        $tableMaps = array(
            'visa_types' => array(
                'name' => array('visa_name', 'name'),
                'description' => array('visa_description', 'description'),
                'validity_days' => array('processing_time', 'validity_days', 'validity', 'days'),
                'processing_fee' => array('fees', 'processing_fee', 'fee'),
                'country_id' => array('country_id'),
                'country_name' => array('country_name'),
                'city' => array('city')
            ),
            'recruitment_countries' => array(
                'name' => array('country_name', 'name'),
                'description' => array('country_description', 'description'),
                'code' => array('country_code', 'code'),
                'currency' => array('currency', 'currency_code'),
                'flag_emoji' => array('flag_emoji', 'flag'),
                'city' => array('city', 'city_name')
            ),
            'job_categories' => array(
                'name' => array('category_name', 'name'),
                'description' => array('category_description', 'description'),
                'min_salary' => array('min_salary', 'min'),
                'max_salary' => array('max_salary', 'max'),
                'country_id' => array('country_id'),
                'city' => array('city')
            ),
            'age_specifications' => array(
                'name' => array('age_range', 'name'),
                'description' => array('age_description', 'description'),
                'min_salary' => array('min_age'),
                'max_salary' => array('max_age'),
                'country_id' => array('country_id'),
                'city' => array('city')
            ),
            'status_specifications' => array(
                'name' => array('status_name', 'name'),
                'description' => array('status_description', 'description'),
                'country_id' => array('country_id'),
                'city' => array('city')
            ),
            'arrival_agencies' => array(
                'name' => array('agency_name', 'name'),
                'description' => array('agency_description', 'description', 'contact_info'),
                'country_id' => array('country_id'),
                'city' => array('city')
            ),
            'arrival_stations' => array(
                'name' => array('station_name', 'name'),
                'description' => array('station_description', 'description'),
                'country_id' => array('country_id'),
                'city' => array('city')
            ),
            'worker_statuses' => array(
                'name' => array('status_name', 'name'),
                'description' => array('status_description', 'description'),
                'country_id' => array('country_id'),
                'city' => array('city')
            ),
            'system_config' => array(
                'name' => array('config_key', 'name', 'config_name', 'key'),
                'description' => array('description', 'config_value', 'value'),
                'country_id' => array('country_id'),
                'city' => array('city')
            ),
            'currencies' => array(
                'code' => array('code', 'currency_code'),
                'name' => array('name', 'currency_name'),
                'symbol' => array('symbol', 'currency_symbol'),
                'status' => array('is_active', 'status'),
                'display_order' => array('display_order', 'order', 'sort_order')
            ),
            'users' => array(
                'name' => array('username', 'name', 'user_name', 'full_name'),
                'email' => array('email', 'email_address'),
                'password' => array('password', 'pass'),
                'phone' => array('phone', 'contact_number', 'phone_number'),
                'country_id' => array('country_id', 'country'),
                'city' => array('city'),
                'position' => array('position', 'job_title'),
                'status' => array('status', 'is_active'),
                'fingerprint_status' => array('fingerprint_status', 'has_fingerprint')
            ),
            'control_admins' => array(
                'name' => array('username', 'name', 'user_name', 'full_name'),
                'password' => array('password'),
                'country_id' => array('country_id', 'country'),
                'status' => array('is_active', 'status')
            ),
            'office_managers' => array(
                'name' => array('name', 'manager_name', 'full_name'),
                'country_id' => array('country_id'),
                'city' => array('city')
            ),
            'appearance_specifications' => array(
                'name' => array('specification_name', 'name', 'appearance_name'),
                'description' => array('description', 'specification_description'),
                'country_id' => array('country_id'),
                'city' => array('city')
            ),
            'request_statuses' => array(
                'name' => array('status_name', 'name', 'request_status_name'),
                'description' => array('description', 'status_description'),
                'country_id' => array('country_id'),
                'city' => array('city')
            )
        );
        
        // Return table-specific map if exists
        if (isset($tableMaps[$table])) {
            return $tableMaps[$table];
        }
        
        // Fallback to general mappings
        $maps = array(
            'name' => array('name', 'visa_name', 'country_name', 'category_name', 'manager_name', 'office_manager_name', 'status_name', 'config_name', 'user_name', 'full_name', 'age_range', 'agency_name', 'config_key', 'username'),
            'description' => array('description', 'visa_description', 'country_description', 'category_description', 'details', 'value', 'age_description', 'status_description', 'agency_description'),
            'code' => array('code', 'country_code'),
            'status' => array('status', 'is_active'),
            'country_id' => array('country_id', 'country'),
            'city' => array('city', 'city_name'),
            'phone' => array('phone', 'contact_number', 'phone_number'),
            'email' => array('email', 'email_address'),
            'password' => array('password', 'pass'),
            'position' => array('position', 'job_title'),
            'address' => array('address'),
            'validity_days' => array('validity_days', 'validity', 'days', 'processing_time'),
            'processing_fee' => array('processing_fee', 'fee', 'fees'),
            'requirements' => array('requirements', 'reqs'),
            'currency' => array('currency', 'currency_code'),
            'flag_emoji' => array('flag_emoji', 'flag'),
            'min_salary' => array('min_salary', 'min', 'min_age'),
            'max_salary' => array('max_salary', 'max', 'max_age'),
        );
        return $maps;
    }
    
    // Automatically add missing columns to tables that need them
    private function ensureColumnsExist() {
        // Don't try to add columns if table doesn't exist
        if (!$this->tableExists()) {
            return;
        }
        
        $table = $this->table;
        $existingCols = $this->getExistingColumns();
        $existingColsLower = array_map('strtolower', $existingCols);
        
        // Define columns to add per table
        $columnsToAdd = array();
        
        if ($table === 'recruitment_countries') {
            if (!in_array('currency', $existingColsLower)) {
                $columnsToAdd[] = "ADD COLUMN `currency` VARCHAR(10) NULL AFTER `country_code`";
            }
            if (!in_array('flag_emoji', $existingColsLower)) {
                $columnsToAdd[] = "ADD COLUMN `flag_emoji` VARCHAR(10) NULL AFTER `currency`";
            }
            if (!in_array('city', $existingColsLower)) {
                $columnsToAdd[] = "ADD COLUMN `city` VARCHAR(100) NULL AFTER `flag_emoji`";
            }
        }
        
        if ($table === 'currencies') {
            // Ensure currencies table has all required columns
            if (!in_array('symbol', $existingColsLower)) {
                $columnsToAdd[] = "ADD COLUMN `symbol` VARCHAR(10) NULL AFTER `name`";
            }
            if (!in_array('is_active', $existingColsLower)) {
                $columnsToAdd[] = "ADD COLUMN `is_active` TINYINT(1) DEFAULT 1 AFTER `symbol`";
            }
            if (!in_array('display_order', $existingColsLower)) {
                $columnsToAdd[] = "ADD COLUMN `display_order` INT DEFAULT 0 AFTER `is_active`";
            }
        }
        
        if ($table === 'visa_types' && !in_array('country_name', $existingColsLower)) {
            $columnsToAdd[] = "ADD COLUMN `country_name` VARCHAR(150) NULL AFTER `country_id`";
        }
        
        // For users table, ensure password_plain column exists
        // Security: do NOT add password_plain - passwords must be hashed only, never stored in plain
        
        // Common: add country_id, city, position, created_at, updated_at where missing (for settings tables)
        $tablesNeedingLocation = array(
            'office_managers','visa_types','job_categories','age_specifications','status_specifications',
            'arrival_agencies','arrival_stations','worker_statuses','system_config','users',
            'appearance_specifications','request_statuses'
        );
        
        // control_admins: add country_id to link users to countries
        if ($table === 'control_admins') {
            if (!in_array('country_id', $existingColsLower)) {
                $columnsToAdd[] = "ADD COLUMN `country_id` INT UNSIGNED NULL DEFAULT NULL AFTER `is_active`";
            }
        }
        
        // Currencies table doesn't need location columns
        if ($table === 'currencies') {
            if (!in_array('created_at', $existingColsLower)) {
                $columnsToAdd[] = "ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP";
            }
            if (!in_array('updated_at', $existingColsLower)) {
                $columnsToAdd[] = "ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
            }
        }
        if (in_array($table, $tablesNeedingLocation)) {
            // For users table, add after user_id instead of id
            $afterColumn = ($table === 'users') ? 'user_id' : 'id';
            if (!in_array('country_id', $existingColsLower)) {
                $columnsToAdd[] = "ADD COLUMN `country_id` INT NULL AFTER `{$afterColumn}`";
            }
            if (!in_array('city', $existingColsLower)) {
                $columnsToAdd[] = "ADD COLUMN `city` VARCHAR(100) NULL";
            }
            if (!in_array('position', $existingColsLower)) {
                $columnsToAdd[] = "ADD COLUMN `position` VARCHAR(100) NULL";
            }
            if (!in_array('created_at', $existingColsLower)) {
                $columnsToAdd[] = "ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP";
            }
            if (!in_array('updated_at', $existingColsLower)) {
                $columnsToAdd[] = "ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
            }
        }
        if ($table === 'users' && !in_array('agency_id', $existingColsLower)) {
            $afterAg = in_array('country_id', $existingColsLower) ? 'country_id' : 'user_id';
            $columnsToAdd[] = "ADD COLUMN `agency_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'control_agencies.id' AFTER `{$afterAg}`";
        }
        
        // Add any missing columns
        if (!empty($columnsToAdd)) {
            try {
                $sql = "ALTER TABLE `{$table}` " . implode(', ', $columnsToAdd);
                $this->conn->exec($sql);
            } catch (Exception $e) {
                // Silently fail if columns already exist or other error
                error_log("Column addition warning: " . $e->getMessage());
            }
        }
    }
}

// Set global exception handler as last resort
set_exception_handler(function($exception) {
    // Clean any output
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        "success" => false,
        "message" => "Uncaught exception: " . $exception->getMessage(),
        "file" => basename($exception->getFile()),
        "line" => $exception->getLine()
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
});

// Handle requests
try {
    // Start session and check permissions
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true
        || !isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] < 1) {
        sendResponse(["success"=>false,"message"=>"Authentication required"],401);
    }
    
    // Include permission checking - ensure config is loaded first for database connection
    // permissions.php needs $conn from config.php (mysqli connection)
    $baseDir = dirname(dirname(__DIR__)); // Go from api/settings to root
    $configPath = $baseDir . '/includes/config.php';
    if (!isset($conn) && file_exists($configPath)) {
        require_once $configPath;
    }
    $permissionsPath = $baseDir . '/includes/permissions.php';
    if (file_exists($permissionsPath)) {
        require_once $permissionsPath;
    } else {
        error_log("Permissions file not found at: " . $permissionsPath);
        sendResponse(["success"=>false,"message"=>"Permission system unavailable"],500);
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            sendResponse(["success"=>false,"message"=>"Invalid JSON: ".json_last_error_msg()],400);
        }
        
        // Validate input
        if (!is_array($input)) {
            sendResponse(["success"=>false,"message"=>"Invalid input: not an array"],400);
        }
        
        // Get action and table - ensure they are strings
        $action = '';
        if (isset($input['action'])) {
            $action = is_string($input['action']) ? trim($input['action']) : (string)trim($input['action']);
        }
        
        $table = '';
        if (isset($input['table'])) {
            $table = is_string($input['table']) ? trim($input['table']) : (string)trim($input['table']);
        }
        $tablePermissions = [
            'users' => ['view' => 'view_users', 'add' => 'add_user', 'edit' => 'edit_user', 'delete' => 'delete_user'],
            'agents' => ['view' => 'view_agents', 'add' => 'add_agent', 'edit' => 'edit_agent', 'delete' => 'delete_agent'],
            'subagents' => ['view' => 'view_subagents', 'add' => 'add_subagent', 'edit' => 'edit_subagent', 'delete' => 'delete_subagent'],
            'workers' => ['view' => 'view_workers', 'add' => 'add_worker', 'edit' => 'edit_worker', 'delete' => 'delete_worker'],
            'cases' => ['view' => 'view_cases', 'add' => 'add_case', 'edit' => 'edit_case', 'delete' => 'delete_case'],
            'office_managers' => ['manage' => 'manage_branches'],
            'visa_types' => ['manage' => 'manage_settings'],
            'recruitment_countries' => ['manage' => 'manage_recruitment_countries'],
            'job_categories' => ['manage' => 'manage_job_categories'],
            'age_specifications' => ['manage' => 'manage_recruitment_settings'],
            'appearance_specifications' => ['manage' => 'manage_recruitment_settings'],
            'status_specifications' => ['manage' => 'manage_recruitment_settings'],
            'request_statuses' => ['manage' => 'manage_recruitment_settings'],
            'arrival_agencies' => ['manage' => 'manage_recruitment_settings'],
            'arrival_stations' => ['manage' => 'manage_recruitment_settings'],
            'worker_statuses' => ['manage' => 'manage_positions'],
            'system_config' => ['manage' => 'manage_settings'],
            'currencies' => ['manage' => 'manage_settings']
        ];
        $actionMap = [
            'get_all' => 'view',
            'get_by_id' => 'view',
            'get_stats' => 'view',
            'create' => 'add',
            'update' => 'edit',
            'delete' => 'delete'
        ];
        $requiredPermission = null;
        $permKey = $actionMap[$action] ?? null;
        if ($permKey && isset($tablePermissions[$table])) {
            $config = $tablePermissions[$table];
            if (isset($config[$permKey])) {
                $requiredPermission = $config[$permKey];
            } elseif (isset($config['manage'])) {
                $requiredPermission = $config['manage'];
            }
        } elseif ($permKey === 'view') {
            $requiredPermission = 'manage_settings';
        } elseif ($permKey) {
            $requiredPermission = 'manage_settings';
        }
        
        // Check permission if required (admin role_id 1 always allowed for System Settings)
        if ($requiredPermission) {
            $isAdmin = isset($_SESSION['role_id']) && (int)$_SESSION['role_id'] === 1;
            $hasPermission = $isAdmin || hasPermission($requiredPermission);
            
            // Fallback to manage_settings for recruitment-related permissions
            if (!$hasPermission && in_array($requiredPermission, ['manage_recruitment_countries', 'manage_recruitment_settings', 'manage_job_categories'])) {
                $hasPermission = hasPermission('manage_settings');
            }
            
            if (!$hasPermission) {
                sendResponse(["success"=>false,"message"=>"Access denied. Insufficient permissions. Required: {$requiredPermission}"],403);
            }
        }
        
        if (empty($table)) {
            sendResponse(["success"=>false,"message"=>"No table specified"],400);
        }
        
        if (empty($action)) {
            $keys = is_array($input) ? implode(', ', array_keys($input)) : 'no input';
            sendResponse(["success"=>false,"message"=>"No action specified. Keys: ".$keys],400);
        }
        
        // Create API instance - catch any errors
        try {
            $api = new SettingsAPI($table);
            // Check if API was actually created (constructor might have sent response and exited)
            if (!isset($api) || !is_object($api)) {
                // If we get here, something went wrong but no response was sent
                sendResponse(["success"=>false,"message"=>"API instance creation failed"],500);
            }
        } catch (PDOException $e) {
            sendResponse(["success"=>false,"message"=>"Database error: ".$e->getMessage()],500);
        } catch (Exception $e) {
            sendResponse(["success"=>false,"message"=>"API initialization failed: ".$e->getMessage()],500);
        } catch (Throwable $e) {
            sendResponse(["success"=>false,"message"=>"API initialization fatal: ".$e->getMessage()],500);
        }
        
        // Ensure we have a valid API instance before proceeding
        if (!isset($api) || !is_object($api)) {
            sendResponse(["success"=>false,"message"=>"API instance not available"],500);
        }
        
        // Execute action - each method calls sendResponse
        try {
            // Normalize action - remove all whitespace and convert to lowercase
            $actionClean = strtolower(trim($action));
            $actionClean = preg_replace('/\s+/', '', $actionClean); // Remove any whitespace
            
            // Compare actions - check multiple ways
            $isUpdate = (
                $actionClean === 'update' ||
                $action === 'update' ||
                trim($action) === 'update' ||
                strtolower(trim($action)) === 'update'
            );
            
            if ($actionClean === 'get_all') {
                $api->getAll();
            } elseif ($actionClean === 'get_by_id') {
                $api->getById(isset($input['id']) ? $input['id'] : 0);
            } elseif ($actionClean === 'create') {
                $api->create(isset($input['data']) ? $input['data'] : array());
            } elseif ($isUpdate) {
                $id = isset($input['id']) ? $input['id'] : 0;
                $data = isset($input['data']) ? $input['data'] : array();
                $api->update($id, $data);
            } elseif ($actionClean === 'delete') {
                $api->delete(isset($input['id']) ? $input['id'] : 0);
            } elseif ($actionClean === 'get_stats') {
                $api->getStats();
            } else {
                // Unknown action - provide detailed error
                $actionHex = bin2hex($action);
                $actionBytes = array();
                for ($i = 0; $i < strlen($action); $i++) {
                    $actionBytes[] = ord($action[$i]);
                }
                $errorDetails = [
                    "success" => false,
                    "message" => "Invalid action: '".$action."'",
                    "debug" => [
                        "original" => $action,
                        "clean" => $actionClean,
                        "hex" => $actionHex,
                        "bytes" => $actionBytes,
                        "length" => strlen($action),
                        "isUpdateCheck" => var_export($isUpdate, true),
                        "validActions" => ["get_all", "get_by_id", "create", "update", "delete", "get_stats"]
                    ]
                ];
                sendResponse($errorDetails, 400);
            }
        } catch (PDOException $e) {
            sendResponse(["success"=>false,"message"=>"Database error: ".$e->getMessage()],500);
        } catch (Exception $e) {
            sendResponse(["success"=>false,"message"=>"Action failed: ".$e->getMessage()],500);
        } catch (Throwable $e) {
            sendResponse(["success"=>false,"message"=>"Action fatal: ".$e->getMessage()],500);
        }
        
        // If we get here somehow, something went wrong
        sendResponse(["success"=>false,"message"=>"No response sent"],500);
    } else {
        sendResponse(["success"=>false,"message"=>"Method not allowed"],405);
    }
} catch (PDOException $e) {
    sendResponse(["success"=>false,"message"=>"Database error: ".$e->getMessage()],500);
} catch (Exception $e) {
    sendResponse(["success"=>false,"message"=>"Server error: ".$e->getMessage()],500);
} catch (Throwable $e) {
    sendResponse(["success"=>false,"message"=>"Fatal error: ".$e->getMessage()],500);
}
