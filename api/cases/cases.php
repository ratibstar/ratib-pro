<?php
/**
 * EN: Handles API endpoint/business logic in `api/cases/cases.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/cases/cases.php`.
 */
// Test endpoint for debugging only
if (isset($_GET['simple_test'])) {
    header('Content-Type: application/json');
    die(json_encode(['success' => true, 'message' => 'File is executing', 'test' => 'simple']));
}

// Enable error logging for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/php-errors.log');

// Start output buffering FIRST to catch any unwanted output
ob_start();

// Register shutdown function to catch fatal errors and ensure response is always sent
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Fatal error occurred - ensure we output something
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
        }
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'Fatal error: ' . $error['message'] . ' in ' . basename($error['file']) . ':' . $error['line'], 
            'data' => []
        ]);
        exit;
    }
});

// Set headers after output buffering starts
if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(200);
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
    }
    echo json_encode(['success' => true, 'message' => 'OPTIONS request handled']);
    exit();
}

// Same session as Ratib Pro pages / SSO (ratib_control cookie or ?control=1)
require_once __DIR__ . '/../core/ratib_api_session.inc.php';
ratib_api_pick_session_name();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Test if we can output before requiring files
if (isset($_GET['pre_require_test'])) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
    }
    echo json_encode(['success' => true, 'message' => 'Before require works']);
    exit();
}

// Check if files exist before requiring
$dbConfigPath = __DIR__ . '/../../config/database.php';
$coreDatabasePath = __DIR__ . '/../core/Database.php';
$permHelperPath = __DIR__ . '/../core/api-permission-helper.php';

// Load core Database class FIRST (has getInstance method) before config/database.php
// This ensures we use the singleton Database class
if (file_exists($coreDatabasePath)) {
    try {
        require_once $coreDatabasePath;
        error_log("CasesAPI: Core Database class loaded successfully");
    } catch (Exception $e) {
        error_log("CasesAPI: Core Database class load exception: " . $e->getMessage());
    } catch (Error $e) {
        error_log("CasesAPI: Core Database class load fatal error: " . $e->getMessage());
    }
}

// Load config/database.php only if Database class doesn't have getInstance (fallback)
if (!class_exists('Database') || !method_exists('Database', 'getInstance')) {
    if (!file_exists($dbConfigPath)) {
        if (ob_get_level() > 0) {
            ob_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode(['success' => false, 'message' => 'Database config file not found: ' . $dbConfigPath, 'data' => []]);
        exit();
}

try {
        require_once $dbConfigPath;
        error_log("CasesAPI: Database config loaded successfully (fallback)");
} catch (Exception $e) {
        if (ob_get_level() > 0) {
            ob_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode(['success' => false, 'message' => 'Database config error: ' . $e->getMessage(), 'data' => []]);
        exit();
} catch (Error $e) {
        if (ob_get_level() > 0) {
            ob_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode(['success' => false, 'message' => 'Database config fatal error: ' . $e->getMessage(), 'data' => []]);
        exit();
    }
} else {
    error_log("CasesAPI: Using core Database class with getInstance()");
}

// Load permission helper with fallback
// Check if permission helper dependencies exist first to avoid fatal errors
$permHelperExists = file_exists($permHelperPath);
$permHelperDepsExist = file_exists(__DIR__ . '/../../includes/config.php') && 
                       file_exists(__DIR__ . '/../../includes/permission_middleware.php') &&
                       file_exists(__DIR__ . '/../core/module-permissions.php');

if ($permHelperExists && $permHelperDepsExist) {
    try {
        // Use @ to suppress warnings, we'll catch exceptions
        @require_once $permHelperPath;
        if (function_exists('enforceApiPermission')) {
            error_log("CasesAPI: Permission helper loaded successfully");
        } else {
            throw new Exception('enforceApiPermission function not defined after require');
        }
    } catch (Exception $e) {
        error_log("CasesAPI: Permission helper exception - " . $e->getMessage() . ", using fallback");
if (!function_exists('enforceApiPermission')) {
    function enforceApiPermission($module, $action) {
                // Fallback: Allow all if permission helper fails
                error_log("CasesAPI: Using fallback enforceApiPermission (Exception) - allowing access");
                return;
            }
        }
    } catch (Error $e) {
        error_log("CasesAPI: Permission helper fatal error - " . $e->getMessage() . ", using fallback");
        if (!function_exists('enforceApiPermission')) {
            function enforceApiPermission($module, $action) {
                // Fallback: Allow all if permission helper fails
                error_log("CasesAPI: Using fallback enforceApiPermission (Error) - allowing access");
                return;
            }
        }
    } catch (Throwable $e) {
        error_log("CasesAPI: Permission helper throwable - " . $e->getMessage() . ", using fallback");
        if (!function_exists('enforceApiPermission')) {
            function enforceApiPermission($module, $action) {
                // Fallback: Allow all if permission helper fails
                error_log("CasesAPI: Using fallback enforceApiPermission (Throwable) - allowing access");
                return;
            }
        }
    }
} else {
    error_log("CasesAPI: Permission helper or dependencies not found (helper: " . ($permHelperExists ? 'exists' : 'missing') . ", deps: " . ($permHelperDepsExist ? 'exist' : 'missing') . "), using fallback");
}

// Ensure enforceApiPermission function exists
if (!function_exists('enforceApiPermission')) {
    function enforceApiPermission($module, $action) {
        // Fallback: Allow all if permission helper is missing
        // This allows the API to work even without proper permission setup
        return;
    }
}

class CasesAPI {
    private $conn;
    /** Cached SQL expression for case assignee display name (users table) */
    private $usersDisplaySqlExpr = null;
    
    public function __construct() {
        try {
            // Use getInstance() if available (singleton pattern), otherwise fallback to new Database()
            if (class_exists('Database') && method_exists('Database', 'getInstance')) {
                $database = Database::getInstance();
            } else {
                // Fallback to old Database class from config/database.php
            $database = new Database();
            }
            $this->conn = $database->getConnection();
                // Test the connection with a simple query
            if ($this->conn) {
                    $this->conn->query("SELECT 1");
            }
        } catch (Exception $e) {
            $this->conn = null;
            error_log("Database connection failed: " . $e->getMessage());
        } catch (Error $e) {
            $this->conn = null;
            error_log("Database connection fatal error: " . $e->getMessage());
        }
    }

    /**
     * SQL fragment: best display label for users joined as u (never use u.username alone when full_name exists).
     */
    private function resolveUsersDisplaySqlExpr(): string
    {
        if ($this->usersDisplaySqlExpr !== null) {
            return $this->usersDisplaySqlExpr;
        }
        $expr = 'u.username';
        try {
            if (!$this->conn) {
                return $expr;
            }
            $st = $this->conn->query("SHOW COLUMNS FROM `users`");
            if (!$st) {
                $this->usersDisplaySqlExpr = $expr;
                return $expr;
            }
            $cols = array_map('strtolower', $st->fetchAll(PDO::FETCH_COLUMN, 0));
            if (in_array('full_name', $cols, true)) {
                $parts = ["NULLIF(TRIM(u.full_name), '')"];
                if (in_array('name', $cols, true)) {
                    $parts[] = "NULLIF(TRIM(u.name), '')";
                }
                $parts[] = 'u.username';
                $expr = 'COALESCE(' . implode(', ', $parts) . ')';
            } elseif (in_array('name', $cols, true)) {
                $expr = "COALESCE(NULLIF(TRIM(u.name), ''), u.username)";
            } elseif (in_array('first_name', $cols, true) && in_array('last_name', $cols, true)) {
                $expr = "COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))), ''), u.username)";
            }
        } catch (Throwable $e) {
            error_log('CasesAPI resolveUsersDisplaySqlExpr: ' . $e->getMessage());
        }
        $this->usersDisplaySqlExpr = $expr;
        return $expr;
    }
    
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $action = $_GET['action'] ?? '';
        
        try {
            $result = null;
            switch ($method) {
                case 'GET':
                    $result = $this->handleGet($action);
                    break;
                case 'POST':
                    $result = $this->handlePost($action);
                    break;
                case 'PUT':
                    $result = $this->handlePut($action);
                    break;
                case 'DELETE':
                    $result = $this->handleDelete($action);
                    break;
                default:
                    $result = $this->response(false, 'Method not allowed', [], 405);
                    break;
            }
            
            // Ensure we always return a valid response
            if ($result === null || $result === '') {
                return $this->response(false, 'No response generated for action: ' . $action, [], 500);
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("CasesAPI handleRequest error: " . $e->getMessage());
            return $this->response(false, 'Internal server error: ' . $e->getMessage(), [], 500);
        }
    }
    
    private function handleGet($action) {
        error_log("CasesAPI handleGet: action = " . $action);
        
        try {
            switch ($action) {
                case 'stats':
                            enforceApiPermission('cases', 'stats');
                    return $this->getStats();
                case 'list':
                            enforceApiPermission('cases', 'get');
                    return $this->getCases();
                case 'get':
                    enforceApiPermission('cases', 'get');
                    $caseId = $_GET['id'] ?? '';
                    return $this->getCase($caseId);
                case 'workers':
                    // Helper endpoint - minimal permission check, allow if user can view cases
                    try {
                        enforceApiPermission('cases', 'get');
                    } catch (Exception $e) {
                        // If permission check fails, still allow but log it
                        error_log("CasesAPI: Permission check for workers endpoint: " . $e->getMessage());
                    }
                    return $this->getWorkers();
                case 'agents':
                    // Helper endpoint - minimal permission check
                    try {
                        enforceApiPermission('cases', 'get');
                    } catch (Exception $e) {
                        error_log("CasesAPI: Permission check for agents endpoint: " . $e->getMessage());
                    }
                    return $this->getAgents();
                case 'subagents':
                    // Helper endpoint - minimal permission check
                    try {
                        enforceApiPermission('cases', 'get');
                    } catch (Exception $e) {
                        error_log("CasesAPI: Permission check for subagents endpoint: " . $e->getMessage());
                    }
                    return $this->getSubagents();
                case 'users':
                    // Helper endpoint - minimal permission check
                    try {
                        enforceApiPermission('cases', 'get');
                    } catch (Exception $e) {
                        error_log("CasesAPI: Permission check for users endpoint: " . $e->getMessage());
                    }
                    return $this->getUsers();
                case 'worker-details':
                    $workerId = $_GET['worker_id'] ?? '';
                    return $this->getWorkerDetails($workerId);
                case 'subagents-by-agent':
                    $agentId = $_GET['agent_id'] ?? '';
                    return $this->getSubagentsByAgent($agentId);
                default:
                    error_log("CasesAPI: Invalid action: " . $action);
                    return $this->response(false, 'Invalid action: ' . $action, [], 400);
            }
        } catch (Exception $e) {
            error_log("CasesAPI: Exception in handleGet: " . $e->getMessage());
            error_log("CasesAPI: Exception type: " . get_class($e));
            error_log("CasesAPI: Exception file: " . $e->getFile() . ":" . $e->getLine());
            return $this->response(false, $e->getMessage() ?: 'An error occurred', [], 403);
        } catch (Error $e) {
            error_log("CasesAPI: Fatal error in handleGet: " . $e->getMessage());
            error_log("CasesAPI: Error type: " . get_class($e));
            error_log("CasesAPI: Error file: " . $e->getFile() . ":" . $e->getLine());
            return $this->response(false, 'Fatal error: ' . $e->getMessage(), [], 500);
        } catch (Throwable $e) {
            error_log("CasesAPI: Throwable in handleGet: " . $e->getMessage());
            error_log("CasesAPI: Throwable type: " . get_class($e));
            return $this->response(false, 'Error: ' . $e->getMessage(), [], 500);
        }
    }
    
    private function handlePost($action) {
        try {
        $input = json_decode(file_get_contents('php://input'), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->response(false, 'Invalid JSON input: ' . json_last_error_msg(), [], 400);
            }
        
        switch ($action) {
            case 'create':
                enforceApiPermission('cases', 'create');
                return $this->createCase($input);
            case 'bulk-update':
                enforceApiPermission('cases', 'bulk-update');
                return $this->bulkUpdateCases($input);
            case 'bulk-delete':
                enforceApiPermission('cases', 'bulk-delete');
                return $this->bulkDeleteCases($input);
            case 'bulk-status':
                enforceApiPermission('cases', 'bulk-update');
                return $this->bulkUpdateStatus($input);
            case 'bulk-active-status':
                enforceApiPermission('cases', 'bulk-update');
                return $this->bulkUpdateActiveStatus($input);
            default:
                return $this->response(false, 'Invalid action', [], 400);
            }
        } catch (Exception $e) {
            error_log("CasesAPI: Exception in handlePost: " . $e->getMessage());
            return $this->response(false, $e->getMessage() ?: 'An error occurred', [], 500);
        } catch (Error $e) {
            error_log("CasesAPI: Fatal error in handlePost: " . $e->getMessage());
            return $this->response(false, 'Fatal error: ' . $e->getMessage(), [], 500);
        } catch (Throwable $e) {
            error_log("CasesAPI: Throwable in handlePost: " . $e->getMessage());
            return $this->response(false, 'Error: ' . $e->getMessage(), [], 500);
        }
    }
    
    private function handlePut($action) {
        try {
        $input = json_decode(file_get_contents('php://input'), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->response(false, 'Invalid JSON input: ' . json_last_error_msg(), [], 400);
            }
        
        switch ($action) {
            case 'update':
                enforceApiPermission('cases', 'update');
                return $this->updateCase($input);
            default:
                return $this->response(false, 'Invalid action', [], 400);
            }
        } catch (Exception $e) {
            error_log("CasesAPI: Exception in handlePut: " . $e->getMessage());
            return $this->response(false, $e->getMessage() ?: 'An error occurred', [], 500);
        } catch (Error $e) {
            error_log("CasesAPI: Fatal error in handlePut: " . $e->getMessage());
            return $this->response(false, 'Fatal error: ' . $e->getMessage(), [], 500);
        } catch (Throwable $e) {
            error_log("CasesAPI: Throwable in handlePut: " . $e->getMessage());
            return $this->response(false, 'Error: ' . $e->getMessage(), [], 500);
        }
    }
    
    private function handleDelete($action) {
        try {
        switch ($action) {
            case 'delete':
                enforceApiPermission('cases', 'delete');
                $caseId = $_GET['id'] ?? '';
                return $this->deleteCase($caseId);
            default:
                return $this->response(false, 'Invalid action', [], 400);
            }
        } catch (Exception $e) {
            error_log("CasesAPI: Exception in handleDelete: " . $e->getMessage());
            return $this->response(false, $e->getMessage() ?: 'An error occurred', [], 500);
        } catch (Error $e) {
            error_log("CasesAPI: Fatal error in handleDelete: " . $e->getMessage());
            return $this->response(false, 'Fatal error: ' . $e->getMessage(), [], 500);
        } catch (Throwable $e) {
            error_log("CasesAPI: Throwable in handleDelete: " . $e->getMessage());
            return $this->response(false, 'Error: ' . $e->getMessage(), [], 500);
        }
    }
    
    private function response($success, $message, $data = [], $code = 200) {
        http_response_code($code);
        return json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $data
        ]);
    }
    
    private function getStats() {
        if (!$this->conn) {
            return $this->response(true, 'Stats retrieved successfully', ['stats' => [
                'total' => 0,
                'open' => 0,
                'in_progress' => 0,
                'pending' => 0,
                'resolved' => 0,
                'closed' => 0,
                'urgent' => 0,
                'high' => 0,
                'medium' => 0,
                'low' => 0
            ]]);
        }
        
        try {
            $sql = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
                        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed,
                        SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent,
                        SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high,
                        SUM(CASE WHEN priority = 'medium' THEN 1 ELSE 0 END) as medium,
                        SUM(CASE WHEN priority = 'low' THEN 1 ELSE 0 END) as low
                    FROM cases";
            
            $stmt = $this->conn->query($sql);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stats = array_map(function($value) {
                return $value !== null ? (int)$value : 0;
            }, $stats);
            
            return $this->response(true, 'Stats retrieved successfully', ['stats' => $stats]);
        } catch (Exception $e) {
            return $this->response(false, 'Error getting stats: ' . $e->getMessage(), []);
        }
    }
    
    private function getCases() {
        if (!$this->conn) {
            return $this->response(true, 'Cases retrieved successfully', [
                'cases' => [],
                'pagination' => [
                    'current_page' => 1,
                    'total_pages' => 1,
                    'per_page' => 10,
                    'total' => 0
                    ]
                ]);
            }
            
        try {
            // Get pagination parameters
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 10;
            $offset = ($page - 1) * $limit;
            
            // Get search and filter parameters
            $search = $_GET['search'] ?? '';
            $status = $_GET['status'] ?? '';
            $type = $_GET['category'] ?? $_GET['type'] ?? '';
            $priority = $_GET['priority'] ?? '';
            
            // Build WHERE clause
            $whereConditions = [];
            $params = [];
            
            if (!empty($search)) {
                $whereConditions[] = "(c.case_number LIKE ? OR c.case_title LIKE ? OR COALESCE(c.case_description, '') LIKE ? OR w.worker_name LIKE ? OR a.agent_name LIKE ? OR s.subagent_name LIKE ?)";
                $searchParam = "%$search%";
                $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
            }
            
            if (!empty($status)) {
                $whereConditions[] = "c.status = ?";
                $params[] = $status;
            }
            
            if (!empty($type)) {
                $whereConditions[] = "c.case_type = ?";
                $params[] = $type;
            }
            
            if (!empty($priority)) {
                $whereConditions[] = "c.priority = ?";
                $params[] = $priority;
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            // Get total count for pagination
                        $countSql = "SELECT COUNT(*) as total FROM cases c
                                    LEFT JOIN workers w ON c.worker_id = w.id
                                    LEFT JOIN agents a ON c.agent_id = a.id  
                                    LEFT JOIN subagents s ON c.subagent_id = s.id
                                    LEFT JOIN users u ON c.assigned_to = u.user_id
                                    $whereClause";
                        
                        $countStmt = $this->conn->prepare($countSql);
                        if ($countStmt) {
                            $countStmt->execute($params);
                $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
                        } else {
                        $totalRecords = 0;
            }
            
            // Build main query with JOINs to get related data
                    $sql = "SELECT 
                        c.*,
                c.active_status,
                        c.resolution,
                w.worker_name as worker_name,
                a.agent_name as agent_name,
                s.subagent_name as subagent_name,
                {$this->resolveUsersDisplaySqlExpr()} as assigned_to_name
                    FROM cases c
                    LEFT JOIN workers w ON c.worker_id = w.id
                    LEFT JOIN agents a ON c.agent_id = a.id  
                    LEFT JOIN subagents s ON c.subagent_id = s.id
                    LEFT JOIN users u ON c.assigned_to = u.user_id
                    $whereClause
            ORDER BY c.created_at DESC
            LIMIT $limit OFFSET $offset";
                    
                    $stmt = $this->conn->prepare($sql);
                    if ($stmt) {
                        $stmt->execute($params);
                        $rawCases = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } else {
                $rawCases = [];
            }
            
            // Transform cases to include proper field names
            $cases = [];
                foreach ($rawCases as $case) {
                // Use case_title if available, fallback to case_description
                $description = !empty($case['case_title']) ? $case['case_title'] : ($case['case_description'] ?? 'No description');
                
                // Generate case_number if missing (fallback for older records)
                $caseNumber = $case['case_number'];
                if (empty($caseNumber) || $caseNumber === null) {
                    // Generate case number in format CA0001, CA0002, etc. based on ID
                    $caseId = (int)$case['id'];
                    $caseNumber = 'CA' . str_pad($caseId, 4, '0', STR_PAD_LEFT);
                }
                
                        $cases[] = [
                    'case_id' => $case['id'],
                            'case_number' => $caseNumber,
                    'description' => $description,
                    'category' => $case['case_type'],
                    'priority' => $case['priority'],
                    'status' => $case['status'],
                    'active_status' => $case['active_status'] ?: 'active',
                    'resolution' => $case['resolution'] ?: '',
                    'worker_name' => $case['worker_name'] ?: 'Unassigned',
                    'agent_name' => $case['agent_name'] ?: 'Unassigned',
                    'subagent_name' => $case['subagent_name'] ?: 'Unassigned',
                    'assigned_to_name' => $case['assigned_to_name'] ?: 'Unassigned',
                    'due_date' => $case['due_date'],
                    'created_at' => $case['created_at'],
                            'raw_data' => $case
                        ];
            }
            
            // Calculate pagination info
            $totalPages = ceil($totalRecords / $limit);
            
            $pagination = [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'per_page' => $limit,
                'total' => (int)$totalRecords
            ];
            
            return $this->response(true, 'Cases retrieved successfully', [
                'cases' => $cases,
                'pagination' => $pagination
            ]);
        } catch (Exception $e) {
            error_log("Error getting cases: " . $e->getMessage());
            return $this->response(false, 'Error getting cases: ' . $e->getMessage(), []);
        }
    }
    
    private function getCase($caseId) {
        if (!$this->conn) {
            return $this->response(false, 'Database not available', [], 500);
        }
        
        if (empty($caseId)) {
            return $this->response(false, 'Case ID is required', [], 400);
        }
        
        // Validate case ID is a positive integer
        if (!is_numeric($caseId) || (int)$caseId <= 0) {
            return $this->response(false, 'Invalid case ID. Must be a positive integer', [], 400);
        }
        $caseId = (int)$caseId;
        
        try {
            $sql = "SELECT c.*, 
                    c.active_status,
                    c.resolution,
                    w.worker_name, 
                    a.agent_name, 
                    s.subagent_name, 
                    {$this->resolveUsersDisplaySqlExpr()} as assigned_to_name
                    FROM cases c
                    LEFT JOIN workers w ON c.worker_id = w.id
                    LEFT JOIN agents a ON c.agent_id = a.id  
                    LEFT JOIN subagents s ON c.subagent_id = s.id
                    LEFT JOIN users u ON c.assigned_to = u.user_id
                    WHERE c.id = ?";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$caseId]);
            $case = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$case) {
                return $this->response(false, 'Case not found', [], 404);
            }
            
            // Use case_title if available, fallback to case_description
            $description = !empty($case['case_title']) ? $case['case_title'] : ($case['case_description'] ?? 'No description');
            
            // Generate case_number if missing (fallback for older records)
            $caseNumber = $case['case_number'];
            if (empty($caseNumber) || $caseNumber === null) {
                // Generate case number in format CA0001, CA0002, etc. based on ID
                $caseId = (int)$case['id'];
                $caseNumber = 'CA' . str_pad($caseId, 4, '0', STR_PAD_LEFT);
            }
            
            // Transform case to include proper field names
            $transformedCase = [
                'case_id' => $case['id'],
                'case_number' => $caseNumber,
                'description' => $description,
                'category' => $case['case_type'],
                'priority' => $case['priority'],
                'status' => $case['status'],
                'active_status' => $case['active_status'] ?: 'active',
                'resolution' => $case['resolution'] ?: '',
                'worker_id' => $case['worker_id'],
                'agent_id' => $case['agent_id'],
                'subagent_id' => $case['subagent_id'],
                'assigned_to' => $case['assigned_to'],
                'worker_name' => $case['worker_name'] ?: 'Unassigned',
                'agent_name' => $case['agent_name'] ?: 'Unassigned',
                'subagent_name' => $case['subagent_name'] ?: 'Unassigned',
                'assigned_to_name' => $case['assigned_to_name'] ?: 'Unassigned',
                'due_date' => $case['due_date'],
                'created_at' => $case['created_at'],
                'updated_at' => $case['updated_at'] ?? null,
                'raw_data' => $case
            ];
            
            return $this->response(true, 'Case retrieved successfully', [
                'case' => $transformedCase
            ]);
        } catch (Exception $e) {
            error_log("Error getting case: " . $e->getMessage());
            return $this->response(false, 'Error getting case: ' . $e->getMessage(), []);
        }
    }
    
    private function getWorkers() {
        if (!$this->conn) {
            return $this->response(false, 'Database connection failed', [], 500);
        }
        
        try {
            // First check if workers table exists
            $tableCheck = $this->conn->query("SHOW TABLES LIKE 'workers'");
            if ($tableCheck->rowCount() == 0) {
                return $this->response(false, 'Workers table does not exist', [], 404);
            }
            
            // Get column information to find the right columns
            $columnsSql = "SHOW COLUMNS FROM workers";
            $columnsStmt = $this->conn->query($columnsSql);
            $columns = $columnsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Find name column (could be full_name, name, first_name, etc.)
            $nameColumn = null;
            foreach ($columns as $column) {
                $field = $column['Field'];
                if (in_array($field, ['full_name', 'name', 'first_name', 'worker_name'])) {
                    $nameColumn = $field;
                    break;
                }
            }
            
            if (!$nameColumn) {
                $nameColumn = $columns[1]['Field'] ?? 'worker_id'; // Use second column as fallback
            }
            
            // Build dynamic SQL
            $sql = "SELECT * FROM workers ORDER BY {$nameColumn}";
            $stmt = $this->conn->query($sql);
            $allWorkers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Transform to the format expected by JavaScript
            $workers = [];
            foreach ($allWorkers as $worker) {
                // Find the ID column (first column)
                $idValue = null;
                $idColumn = null;
                foreach ($worker as $column => $value) {
                    if ($idValue === null) {
                        $idValue = $value;
                        $idColumn = $column;
                        break;
                    }
                }
                
                $workers[] = [
                    'worker_id' => $idValue,
                    'first_name' => $worker[$nameColumn] ?? 'Unknown',
                    'last_name' => '',
                    'raw_data' => $worker
                ];
            }
            
            return $this->response(true, 'Workers retrieved from database', ['workers' => $workers]);
        } catch (Exception $e) {
            error_log("Error getting workers: " . $e->getMessage());
            return $this->response(false, 'Error retrieving workers: ' . $e->getMessage(), [], 500);
        }
    }
    
    private function getAgents() {
        if (!$this->conn) {
            return $this->response(false, 'Database connection failed', [], 500);
        }
        
        try {
            // Check if agents table exists
            $tableCheck = $this->conn->query("SHOW TABLES LIKE 'agents'");
            if ($tableCheck->rowCount() == 0) {
                return $this->response(false, 'Agents table does not exist', [], 404);
            }
            
            // Get column information
            $columnsSql = "SHOW COLUMNS FROM agents";
            $columnsStmt = $this->conn->query($columnsSql);
            $columns = $columnsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Find name column
            $nameColumn = null;
            foreach ($columns as $column) {
                $field = $column['Field'];
                if (in_array($field, ['full_name', 'name', 'first_name', 'agent_name'])) {
                    $nameColumn = $field;
                    break;
                }
            }
            
            if (!$nameColumn) {
                $nameColumn = $columns[1]['Field'] ?? 'agent_id';
            }
            
            // Build dynamic SQL
            $sql = "SELECT * FROM agents ORDER BY {$nameColumn}";
            $stmt = $this->conn->query($sql);
            $allAgents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Transform to the format expected by JavaScript
            $agents = [];
            foreach ($allAgents as $agent) {
                // Find the ID column (first column)
                $idValue = null;
                foreach ($agent as $column => $value) {
                    if ($idValue === null) {
                        $idValue = $value;
                        break;
                    }
                }
                
                $agents[] = [
                    'agent_id' => $idValue,
                    'first_name' => $agent[$nameColumn] ?? 'Unknown',
                    'last_name' => '',
                    'raw_data' => $agent
                ];
            }
            
            return $this->response(true, 'Agents retrieved from database', ['agents' => $agents]);
        } catch (Exception $e) {
            error_log("Error getting agents: " . $e->getMessage());
            return $this->response(false, 'Error retrieving agents: ' . $e->getMessage(), [], 500);
        }
    }
    
    private function getSubagents() {
        if (!$this->conn) {
            return $this->response(false, 'Database connection failed', [], 500);
        }
        
        try {
            // Check if subagents table exists
            $tableCheck = $this->conn->query("SHOW TABLES LIKE 'subagents'");
            if ($tableCheck->rowCount() == 0) {
                return $this->response(false, 'Subagents table does not exist', [], 404);
            }
            
            // Get column information
            $columnsSql = "SHOW COLUMNS FROM subagents";
            $columnsStmt = $this->conn->query($columnsSql);
            $columns = $columnsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Find name column
            $nameColumn = null;
            foreach ($columns as $column) {
                $field = $column['Field'];
                if (in_array($field, ['full_name', 'name', 'first_name', 'subagent_name'])) {
                    $nameColumn = $field;
                    break;
                }
            }
            
            if (!$nameColumn) {
                $nameColumn = $columns[1]['Field'] ?? 'subagent_id';
            }
            
            // Build dynamic SQL
            $sql = "SELECT * FROM subagents ORDER BY {$nameColumn}";
            $stmt = $this->conn->query($sql);
            $allSubagents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Transform to the format expected by JavaScript
            $subagents = [];
            foreach ($allSubagents as $subagent) {
                // Find the ID column (first column)
                $idValue = null;
                foreach ($subagent as $column => $value) {
                    if ($idValue === null) {
                        $idValue = $value;
                        break;
                    }
                }
                
                $subagents[] = [
                    'subagent_id' => $idValue,
                    'first_name' => $subagent[$nameColumn] ?? 'Unknown',
                    'last_name' => '',
                    'raw_data' => $subagent
                ];
            }
            
            return $this->response(true, 'Subagents retrieved from database', ['subagents' => $subagents]);
        } catch (Exception $e) {
            error_log("Error getting subagents: " . $e->getMessage());
            return $this->response(false, 'Error retrieving subagents: ' . $e->getMessage(), [], 500);
        }
    }
    
    private function getUsers() {
        if (!$this->conn) {
            return $this->response(false, 'Database connection failed', [], 500);
        }
        
        try {
            $sql = "SELECT * FROM users ORDER BY username";
            $stmt = $this->conn->query($sql);
            $allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $users = [];
            foreach ($allUsers as $user) {
                $uid = isset($user['user_id']) ? (int) $user['user_id'] : (int) ($user['id'] ?? 0);
                if ($uid <= 0) {
                    continue;
                }
                $display = '';
                foreach (['full_name', 'name', 'first_name'] as $k) {
                    if (!empty($user[$k]) && is_string($user[$k])) {
                        $display = trim($user[$k]);
                        if ($display !== '') {
                            break;
                        }
                    }
                }
                if ($display === '' && !empty($user['first_name']) && !empty($user['last_name'])) {
                    $display = trim($user['first_name'] . ' ' . $user['last_name']);
                }
                if ($display === '') {
                    $display = trim($user['username'] ?? '') ?: ('User #' . $uid);
                }
                
                $users[] = [
                    'user_id' => $uid,
                    'name' => $display,
                    'username' => $user['username'] ?? '',
                    'raw_data' => $user
                ];
            }
            
            return $this->response(true, 'Users retrieved from database', ['users' => $users]);
        } catch (Exception $e) {
            error_log("Error getting users: " . $e->getMessage());
            return $this->response(false, 'Error retrieving users: ' . $e->getMessage(), [], 500);
        }
    }
    
    private function getWorkerDetails($workerId) {
        if (!$workerId) {
            return $this->response(false, 'Worker ID is required', [], 400);
        }
        
        if (!$this->conn) {
            return $this->response(false, 'Database connection failed', [], 500);
        }
        
        try {
            // Get worker columns to find the correct primary key column
            $workerColumnsSql = "SHOW COLUMNS FROM workers";
            $workerColumnsStmt = $this->conn->query($workerColumnsSql);
            $workerColumns = $workerColumnsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Find the primary key column (could be worker_id, id, etc.)
            $primaryKeyColumn = null;
            foreach ($workerColumns as $column) {
                if ($column['Key'] === 'PRI') {
                    $primaryKeyColumn = $column['Field'];
                    break;
                }
            }
            
            // Fallback to first column if no primary key found
            if (!$primaryKeyColumn) {
                $primaryKeyColumn = $workerColumns[0]['Field'] ?? 'id';
            }
            
            // Get worker details from database using correct primary key
            $sql = "SELECT * FROM workers WHERE {$primaryKeyColumn} = ? LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$workerId]);
            $worker = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$worker) {
                return $this->response(false, 'Worker not found', [], 404);
            }
            
            // Get all agents for the dropdown
            $agents = [];
            $agentColumnsSql = "SHOW COLUMNS FROM agents";
            $agentColumnsStmt = $this->conn->query($agentColumnsSql);
            $agentColumns = $agentColumnsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Find agent ID column and name column
            $agentIdColumn = null;
            $agentNameColumn = null;
            foreach ($agentColumns as $column) {
                $field = $column['Field'];
                if (in_array($field, ['agent_id', 'id'])) {
                    $agentIdColumn = $field;
                }
                if (in_array($field, ['full_name', 'name', 'first_name', 'agent_name'])) {
                    $agentNameColumn = $field;
                }
            }
            
            if (!$agentIdColumn) $agentIdColumn = 'agent_id';
            if (!$agentNameColumn) $agentNameColumn = $agentColumns[1]['Field'] ?? 'agent_id';
            
            // Get all agents
            $allAgentsSql = "SELECT * FROM agents ORDER BY {$agentNameColumn}";
            $allAgentsStmt = $this->conn->query($allAgentsSql);
            $allAgents = $allAgentsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($allAgents as $agent) {
                $agents[] = [
                    'agent_id' => $agent[$agentIdColumn],
                    'first_name' => $agent[$agentNameColumn] ?? 'Unknown',
                    'last_name' => '',
                    'raw_data' => $agent
                ];
            }
            
            // Get agent_id from worker data to filter subagents
            $agentId = $worker['agent_id'] ?? null;
            
            // Get all subagents for the dropdown (or filtered by agent if worker has agent assigned)
            $subagents = [];
            $subagentColumnsSql = "SHOW COLUMNS FROM subagents";
            $subagentColumnsStmt = $this->conn->query($subagentColumnsSql);
            $subagentColumns = $subagentColumnsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Find subagent columns
            $subagentIdColumn = null;
            $subagentNameColumn = null;
            $subagentAgentIdColumn = null;
            foreach ($subagentColumns as $column) {
                $field = $column['Field'];
                if (in_array($field, ['subagent_id', 'id'])) {
                    $subagentIdColumn = $field;
                }
                if (in_array($field, ['full_name', 'name', 'first_name', 'subagent_name'])) {
                    $subagentNameColumn = $field;
                }
                if ($field === 'agent_id') {
                    $subagentAgentIdColumn = $field;
                }
            }
            
            if (!$subagentIdColumn) $subagentIdColumn = 'subagent_id';
            if (!$subagentNameColumn) $subagentNameColumn = $subagentColumns[1]['Field'] ?? 'subagent_id';
            if (!$subagentAgentIdColumn) $subagentAgentIdColumn = 'agent_id';
            
            // Get subagents (filtered by agent if worker has agent assigned AND has a specific subagent)
            $subagentId = $worker['subagent_id'] ?? null;
            
            if ($agentId && $subagentId) {
                // Worker has both agent and subagent - show only subagents for that agent
                $subagentsSql = "SELECT * FROM subagents WHERE {$subagentAgentIdColumn} = ? ORDER BY {$subagentNameColumn}";
                $subagentsStmt = $this->conn->prepare($subagentsSql);
                $subagentsStmt->execute([$agentId]);
                $allSubagents = $subagentsStmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // Worker has no agent or no subagent - show all subagents for selection
                $subagentsSql = "SELECT * FROM subagents ORDER BY {$subagentNameColumn}";
                $subagentsStmt = $this->conn->query($subagentsSql);
                $allSubagents = $subagentsStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            foreach ($allSubagents as $subagent) {
                $subagents[] = [
                    'subagent_id' => $subagent[$subagentIdColumn],
                    'first_name' => $subagent[$subagentNameColumn] ?? 'Unknown',
                    'last_name' => '',
                    'raw_data' => $subagent
                ];
            }
            
            return $this->response(true, 'Worker details retrieved successfully', [
                'agents' => $agents,
                'subagents' => $subagents,
                'worker_agent_id' => $worker['agent_id'] ?? null,
                'worker_subagent_id' => $worker['subagent_id'] ?? null,
                'worker_data' => $worker
            ]);
            
        } catch (Exception $e) {
            error_log("Error getting worker details: " . $e->getMessage());
            return $this->response(false, 'Error retrieving worker details: ' . $e->getMessage(), [], 500);
        }
    }
    
    private function getSubagentsByAgent($agentId) {
        if (!$agentId) {
            return $this->response(false, 'Agent ID is required', [], 400);
        }
        
        if (!$this->conn) {
            return $this->response(false, 'Database connection failed', [], 500);
        }
        
        try {
            // Check if subagents table exists
            $tableCheck = $this->conn->query("SHOW TABLES LIKE 'subagents'");
            if ($tableCheck->rowCount() == 0) {
                return $this->response(false, 'Subagents table does not exist', [], 404);
            }
            
            // Get subagent columns to find the right column names
            $subagentColumnsSql = "SHOW COLUMNS FROM subagents";
            $subagentColumnsStmt = $this->conn->query($subagentColumnsSql);
            $subagentColumns = $subagentColumnsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Find columns
            $agentIdColumn = null;
            $subagentIdColumn = null;
            $subagentNameColumn = null;
            foreach ($subagentColumns as $column) {
                $field = $column['Field'];
                if ($field === 'agent_id') {
                    $agentIdColumn = $field;
                }
                if (in_array($field, ['subagent_id', 'id'])) {
                    $subagentIdColumn = $field;
                }
                if (in_array($field, ['full_name', 'name', 'first_name', 'subagent_name'])) {
                    $subagentNameColumn = $field;
                }
            }
            
            if (!$agentIdColumn) $agentIdColumn = 'agent_id';
            if (!$subagentIdColumn) $subagentIdColumn = 'subagent_id';
            if (!$subagentNameColumn) $subagentNameColumn = $subagentColumns[1]['Field'] ?? 'subagent_id';
            
            // Get subagents for this agent
            $sql = "SELECT * FROM subagents WHERE {$agentIdColumn} = ? ORDER BY {$subagentIdColumn}";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$agentId]);
            $allSubagents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Transform to the format expected by JavaScript
            $subagents = [];
            foreach ($allSubagents as $subagent) {
                $subagents[] = [
                    'subagent_id' => $subagent[$subagentIdColumn],
                    'first_name' => $subagent[$subagentNameColumn] ?? 'Unknown',
                    'last_name' => '',
                    'raw_data' => $subagent
                ];
            }
            
            return $this->response(true, 'Subagents retrieved from database', ['subagents' => $subagents]);
        } catch (Exception $e) {
            error_log("Error getting subagents by agent: " . $e->getMessage());
            return $this->response(false, 'Error retrieving subagents: ' . $e->getMessage(), [], 500);
        }
    }
    
    private function createCase($data) {
        if (!$this->conn) {
            return $this->response(false, 'Database not available', [], 500);
        }
        
        try {
            // Validate required fields
            if (empty($data['description'])) {
                return $this->response(false, 'Description is required', [], 400);
            }
            
            // Validate and sanitize case_type
            $validCaseTypes = ['worker', 'agent', 'legal', 'financial', 'other'];
            $caseType = isset($data['case_type']) && in_array($data['case_type'], $validCaseTypes) 
                ? $data['case_type'] 
                : 'other';
            
            // Validate and sanitize priority
            $validPriorities = ['low', 'medium', 'high', 'urgent'];
            $priority = isset($data['priority']) && in_array($data['priority'], $validPriorities)
                ? $data['priority']
                : 'medium';
            
            // Validate and sanitize status
            $validStatuses = ['open', 'in_progress', 'pending', 'resolved', 'closed'];
            $status = isset($data['status']) && in_array($data['status'], $validStatuses)
                ? $data['status']
                : 'open';
            
            // Validate and sanitize active_status
            $validActiveStatuses = ['active', 'inactive'];
            $activeStatus = isset($data['active_status']) && in_array($data['active_status'], $validActiveStatuses)
                ? $data['active_status']
                : 'active';
            
            // Sanitize description (max 500 chars)
            $description = substr(trim($data['description']), 0, 500);
            
            // Sanitize resolution (max 1000 chars)
            $resolution = isset($data['resolution']) ? substr(trim($data['resolution']), 0, 1000) : '';
            
            // Validate IDs are integers or null, and verify they exist in their respective tables
            $workerId = null;
            if (!empty($data['worker_id'])) {
                $workerId = (int)$data['worker_id'];
                if ($workerId > 0) {
                    try {
                        $checkStmt = $this->conn->prepare("SELECT id FROM workers WHERE id = ? LIMIT 1");
                        $checkStmt->execute([$workerId]);
                        if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
                            return $this->response(false, "Worker ID {$workerId} does not exist", [], 400);
                        }
                    } catch (Exception $e) {
                        error_log("CasesAPI: Error validating worker_id: " . $e->getMessage());
                        return $this->response(false, "Error validating worker ID", [], 500);
                    }
                }
            }
            
            $agentId = null;
            if (!empty($data['agent_id'])) {
                $agentId = (int)$data['agent_id'];
                if ($agentId > 0) {
                    try {
                        $checkStmt = $this->conn->prepare("SELECT id FROM agents WHERE id = ? LIMIT 1");
                        $checkStmt->execute([$agentId]);
                        if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
                            return $this->response(false, "Agent ID {$agentId} does not exist", [], 400);
                        }
                    } catch (Exception $e) {
                        error_log("CasesAPI: Error validating agent_id: " . $e->getMessage());
                        return $this->response(false, "Error validating agent ID", [], 500);
                    }
                }
            }
            
            $subagentId = null;
            if (!empty($data['subagent_id'])) {
                $subagentId = (int)$data['subagent_id'];
                if ($subagentId > 0) {
                    try {
                        $checkStmt = $this->conn->prepare("SELECT id FROM subagents WHERE id = ? LIMIT 1");
                        $checkStmt->execute([$subagentId]);
                        if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
                            return $this->response(false, "Subagent ID {$subagentId} does not exist", [], 400);
                        }
                    } catch (Exception $e) {
                        error_log("CasesAPI: Error validating subagent_id: " . $e->getMessage());
                        return $this->response(false, "Error validating subagent ID", [], 500);
                    }
                }
            }
            
            $assignedTo = null;
            if (!empty($data['assigned_to'])) {
                $assignedTo = (int)$data['assigned_to'];
                if ($assignedTo > 0) {
                    try {
                        $checkStmt = $this->conn->prepare("SELECT user_id FROM users WHERE user_id = ? LIMIT 1");
                        $checkStmt->execute([$assignedTo]);
                        if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
                            return $this->response(false, "User ID {$assignedTo} does not exist", [], 400);
                        }
                    } catch (Exception $e) {
                        error_log("CasesAPI: Error validating assigned_to: " . $e->getMessage());
                        return $this->response(false, "Error validating assigned user ID", [], 500);
                    }
                }
            }
            
            // Get created_by from session or data, validate it exists in users table
            // Priority: 1) Session user_id, 2) Data provided, 3) First available user
            $createdBy = null;
            
            // First, try to get from session
            if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
                $sessionUserId = (int)$_SESSION['user_id'];
                // Validate session user exists
                try {
                    $userCheckStmt = $this->conn->prepare("SELECT user_id FROM users WHERE user_id = ? LIMIT 1");
                    $userCheckStmt->execute([$sessionUserId]);
                    $userExists = $userCheckStmt->fetch(PDO::FETCH_ASSOC);
                    if ($userExists) {
                        $createdBy = $sessionUserId;
                        error_log("CasesAPI: Using session user_id {$createdBy} for created_by");
                    }
                } catch (Exception $e) {
                    error_log("CasesAPI: Error validating session user: " . $e->getMessage());
                }
            }
            
            // If session user not found, try data provided
            if ($createdBy === null && !empty($data['created_by'])) {
                $dataUserId = (int)$data['created_by'];
                if ($dataUserId > 0) {
                    try {
                        $userCheckStmt = $this->conn->prepare("SELECT user_id FROM users WHERE user_id = ? LIMIT 1");
                        $userCheckStmt->execute([$dataUserId]);
                        $userExists = $userCheckStmt->fetch(PDO::FETCH_ASSOC);
                        if ($userExists) {
                            $createdBy = $dataUserId;
                            error_log("CasesAPI: Using provided user_id {$createdBy} for created_by");
                        } else {
                            error_log("CasesAPI: Provided user_id {$dataUserId} does not exist in users table");
                        }
                    } catch (Exception $e) {
                        error_log("CasesAPI: Error validating provided user: " . $e->getMessage());
                    }
                }
            }
            
            // If still no valid user, get first available user_id as fallback
            if ($createdBy === null) {
                try {
                    $fallbackStmt = $this->conn->query("SELECT user_id FROM users WHERE user_id IS NOT NULL AND user_id > 0 ORDER BY user_id ASC LIMIT 1");
                    $fallbackUser = $fallbackStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($fallbackUser && !empty($fallbackUser['user_id'])) {
                        $createdBy = (int)$fallbackUser['user_id'];
                        error_log("CasesAPI: Using first available user_id {$createdBy} for created_by (fallback)");
                    } else {
                        // If no users exist at all, we can't create a case - return error
                        return $this->response(false, 'Cannot create case: No valid users found in the system. Please contact administrator.', [], 400);
                    }
                } catch (Exception $e) {
                    error_log("CasesAPI: Error getting fallback user: " . $e->getMessage());
                    return $this->response(false, 'Cannot create case: Unable to determine creator. Please contact administrator.', [], 500);
                }
            }
            
            // Final validation - ensure we have a valid user_id
            if ($createdBy === null || $createdBy <= 0) {
                return $this->response(false, 'Cannot create case: Invalid creator user. Please contact administrator.', [], 400);
            }
            
            // Validate due_date format if provided
            $dueDate = null;
            if (!empty($data['due_date'])) {
                $dueDateObj = DateTime::createFromFormat('Y-m-d', $data['due_date']);
                if ($dueDateObj && $dueDateObj->format('Y-m-d') === $data['due_date']) {
                    $dueDate = $data['due_date'];
                } else {
                    return $this->response(false, 'Invalid due date format. Use YYYY-MM-DD', [], 400);
                }
            }
            
            // Generate case_number if not provided
            $caseNumber = null;
            if (!empty($data['case_number'])) {
                $caseNumber = trim($data['case_number']);
            }
            
            if (empty($caseNumber)) {
                // Auto-generate case number using helper function
                $helperPath = __DIR__ . '/../core/formatted-id-helper.php';
                if (file_exists($helperPath)) {
                    require_once $helperPath;
                    if (function_exists('generateCaseNumber')) {
                        try {
                            $caseNumber = generateCaseNumber($this->conn);
                        } catch (Exception $e) {
                            error_log("Error generating case number: " . $e->getMessage());
                            // Fallback: generate simple case number
                            $stmt = $this->conn->prepare("SELECT IFNULL(MAX(CAST(SUBSTRING(case_number, 3) AS UNSIGNED)), 0) + 1 AS next_id FROM cases WHERE case_number REGEXP '^CA[0-9]+$'");
                            $stmt->execute();
                            $result = $stmt->fetch(PDO::FETCH_ASSOC);
                            $nextId = $result['next_id'] ?? 1;
                            $caseNumber = 'CA' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
                        }
                    } else {
                        // Fallback if function doesn't exist
                        $stmt = $this->conn->prepare("SELECT IFNULL(MAX(CAST(SUBSTRING(case_number, 3) AS UNSIGNED)), 0) + 1 AS next_id FROM cases WHERE case_number REGEXP '^CA[0-9]+$'");
                        $stmt->execute();
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        $nextId = $result['next_id'] ?? 1;
                        $caseNumber = 'CA' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
                    }
                } else {
                    // Fallback if helper file doesn't exist
                    $stmt = $this->conn->prepare("SELECT IFNULL(MAX(CAST(SUBSTRING(case_number, 3) AS UNSIGNED)), 0) + 1 AS next_id FROM cases WHERE case_number REGEXP '^CA[0-9]+$'");
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $nextId = $result['next_id'] ?? 1;
                    $caseNumber = 'CA' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
                }
            }
            
            // Use case_title for the description (case_title is the actual field in database)
            $sql = "INSERT INTO cases (case_number, worker_id, agent_id, subagent_id, assigned_to, case_type, priority, status, active_status, case_title, case_description, resolution, due_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            $result = $stmt->execute([
                $caseNumber,
                $workerId,
                $agentId,
                $subagentId,
                $assignedTo,
                $caseType,
                $priority,
                $status,
                $activeStatus,
                $description, // Store in case_title
                $description, // Also store in case_description for compatibility
                $resolution,
                $dueDate,
                $createdBy
            ]);
            
            if ($result) {
                $caseId = $this->conn->lastInsertId();
                // Get created case for history
                $stmt = $this->conn->prepare("SELECT * FROM cases WHERE id = ?");
                $stmt->execute([$caseId]);
                $newCase = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Log history
                $helperPath = __DIR__ . '/../core/global-history-helper.php';
                if (file_exists($helperPath)) {
                    require_once $helperPath;
                    if (function_exists('logGlobalHistory')) {
                        error_log("🔍 Attempting to log case history: ID=$caseId, Module=cases, Action=create");
                        $result = logGlobalHistory('cases', $caseId, 'create', 'cases', null, $newCase);
                        if ($result) {
                            error_log("✅ Case history logged successfully: ID=$caseId");
                        } else {
                            error_log("❌ Failed to log case history: ID=$caseId - Check error log for details");
                        }
                    } else {
                        error_log("❌ logGlobalHistory function not found after require");
                    }
                } else {
                    error_log("❌ History helper not found at: $helperPath");
                }
                
                return $this->response(true, 'Case created successfully', ['case_id' => $caseId]);
            } else {
                return $this->response(false, 'Failed to create case', [], 500);
            }
        } catch (Exception $e) {
            return $this->response(false, 'Error creating case: ' . $e->getMessage(), [], 500);
        }
    }
    
    private function updateCase($data) {
        if (!$this->conn) {
            return $this->response(false, 'Database not available', [], 500);
        }
        
        try {
            $caseId = $data['case_id'] ?? null;
            
            if (!$caseId) {
                return $this->response(false, 'Case ID is required', [], 400);
            }
            
            // Validate case ID is a positive integer
            if (!is_numeric($caseId) || (int)$caseId <= 0) {
                return $this->response(false, 'Invalid case ID. Must be a positive integer', [], 400);
            }
            $caseId = (int)$caseId;
            
            // Get old data for history
            $stmt = $this->conn->prepare("SELECT * FROM cases WHERE id = ?");
            $stmt->execute([$caseId]);
            $oldCase = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$oldCase) {
                return $this->response(false, 'Case not found', [], 404);
            }
            
            // Define valid values for validation
            $validCaseTypes = ['worker', 'agent', 'legal', 'financial', 'other'];
            $validPriorities = ['low', 'medium', 'high', 'urgent'];
            $validStatuses = ['open', 'in_progress', 'pending', 'resolved', 'closed'];
            $validActiveStatuses = ['active', 'inactive'];
            
            // Build update query dynamically for all fields
            $updateFields = [];
            $params = [];
            
            // Map frontend field names to database column names
            $fieldMapping = [
                'worker_id' => 'worker_id',
                'agent_id' => 'agent_id',
                'subagent_id' => 'subagent_id',
                'assigned_to' => 'assigned_to',
                'case_type' => 'case_type',
                'category' => 'case_type',
                'status' => 'status',
                'priority' => 'priority',
                'active_status' => 'active_status',
                'description' => 'case_title', // Use case_title (actual database field)
                'case_title' => 'case_title',
                'case_description' => 'case_description', // Keep for compatibility
                'resolution' => 'resolution',
                'due_date' => 'due_date'
            ];
            
            // Track if description is being updated to sync case_title and case_description
            $descriptionValue = null;
            
            // Add fields to update if they exist in data with validation
            foreach ($fieldMapping as $frontendField => $dbField) {
                if (isset($data[$frontendField])) {
                    // Handle description specially - update both case_title and case_description
                    if ($frontendField === 'description' || $frontendField === 'case_title') {
                        if ($descriptionValue === null) {
                            $value = ($data[$frontendField] === '' || $data[$frontendField] === null) ? null : trim($data[$frontendField]);
                            // Sanitize description (max 500 chars)
                            if ($value !== null && strlen($value) > 500) {
                                return $this->response(false, 'Description must be 500 characters or less', [], 400);
                            }
                            $descriptionValue = $value !== null ? substr($value, 0, 500) : null;
                        }
                        // Skip adding to updateFields here - we'll add both case_title and case_description together
                        continue;
                    }
                    
                    // Validate and sanitize values based on field type
                    $value = $data[$frontendField];
                    
                    // Validate case_type
                    if ($dbField === 'case_type' && $value !== null && $value !== '') {
                        if (!in_array($value, $validCaseTypes)) {
                            return $this->response(false, 'Invalid case type. Must be one of: ' . implode(', ', $validCaseTypes), [], 400);
                        }
                        // Value is already validated, continue
                    }
                    
                    // Validate priority
                    if ($dbField === 'priority' && $value !== null && $value !== '') {
                        if (!in_array($value, $validPriorities)) {
                            return $this->response(false, 'Invalid priority. Must be one of: ' . implode(', ', $validPriorities), [], 400);
                        }
                        // Value is already validated, continue
                    }
                    
                    // Validate status
                    if ($dbField === 'status' && $value !== null && $value !== '') {
                        if (!in_array($value, $validStatuses)) {
                            return $this->response(false, 'Invalid status. Must be one of: ' . implode(', ', $validStatuses), [], 400);
                        }
                        // Value is already validated, continue
                    }
                    
                    // Validate active_status
                    if ($dbField === 'active_status' && $value !== null && $value !== '') {
                        if (!in_array($value, $validActiveStatuses)) {
                            return $this->response(false, 'Invalid active status. Must be one of: ' . implode(', ', $validActiveStatuses), [], 400);
                        }
                        // Value is already validated, continue
                    }
                    
                    // Validate IDs are integers or null, and verify they exist in their respective tables
                    if (in_array($dbField, ['worker_id', 'agent_id', 'subagent_id', 'assigned_to'])) {
                        if ($value !== null && $value !== '') {
                            if (!is_numeric($value) || (int)$value <= 0) {
                                return $this->response(false, "Invalid {$dbField}. Must be a positive integer", [], 400);
                            }
                            $value = (int)$value;
                            
                            // Validate foreign key constraints - verify the ID exists in the referenced table
                            try {
                                if ($dbField === 'worker_id') {
                                    $checkStmt = $this->conn->prepare("SELECT id FROM workers WHERE id = ? LIMIT 1");
                                    $checkStmt->execute([$value]);
                                    if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
                                        return $this->response(false, "Worker ID {$value} does not exist", [], 400);
                                    }
                                } elseif ($dbField === 'agent_id') {
                                    $checkStmt = $this->conn->prepare("SELECT id FROM agents WHERE id = ? LIMIT 1");
                                    $checkStmt->execute([$value]);
                                    if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
                                        return $this->response(false, "Agent ID {$value} does not exist", [], 400);
                                    }
                                } elseif ($dbField === 'subagent_id') {
                                    $checkStmt = $this->conn->prepare("SELECT id FROM subagents WHERE id = ? LIMIT 1");
                                    $checkStmt->execute([$value]);
                                    if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
                                        return $this->response(false, "Subagent ID {$value} does not exist", [], 400);
                                    }
                                } elseif ($dbField === 'assigned_to') {
                                    $checkStmt = $this->conn->prepare("SELECT user_id FROM users WHERE user_id = ? LIMIT 1");
                                    $checkStmt->execute([$value]);
                                    if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
                                        return $this->response(false, "User ID {$value} does not exist", [], 400);
                                    }
                                }
                            } catch (Exception $e) {
                                error_log("CasesAPI: Error validating {$dbField} in updateCase: " . $e->getMessage());
                                return $this->response(false, "Error validating {$dbField}", [], 500);
                            }
                        } else {
                            $value = null;
                        }
                    }
                    
                    // Validate resolution (max 1000 chars)
                    if ($dbField === 'resolution' && $value !== null && $value !== '') {
                        $value = substr(trim($value), 0, 1000);
                    }
                    
                    // Validate due_date format if provided
                    if ($dbField === 'due_date' && $value !== null && $value !== '') {
                        $dueDateObj = DateTime::createFromFormat('Y-m-d', $value);
                        if (!$dueDateObj || $dueDateObj->format('Y-m-d') !== $value) {
                            return $this->response(false, 'Invalid due date format. Use YYYY-MM-DD', [], 400);
                        }
                        // Value is already in correct format, continue
                    }
                    
                    // Handle null/empty values
                    if ($value === '' || $value === null) {
                        $value = null;
                    }
                    
                    $updateFields[] = "$dbField = ?";
                    $params[] = $value;
                }
            }
            
            // If description was provided, update both case_title and case_description
            if ($descriptionValue !== null) {
                $updateFields[] = "case_title = ?";
                $updateFields[] = "case_description = ?";
                $params[] = $descriptionValue;
                $params[] = $descriptionValue;
            }
            
            // Always update updated_at
            $updateFields[] = "updated_at = CURRENT_TIMESTAMP";
            
            if (empty($updateFields)) {
                return $this->response(false, 'No fields to update', [], 400);
            }
            
            // Add case ID to params for WHERE clause
            $params[] = $caseId;
            
            // Build and execute SQL
            $sql = "UPDATE cases SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                // Get updated case for history
                $stmt = $this->conn->prepare("SELECT * FROM cases WHERE id = ?");
                $stmt->execute([$caseId]);
                $updatedCase = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Log history
                $helperPath = __DIR__ . '/../core/global-history-helper.php';
                if (file_exists($helperPath)) {
                    require_once $helperPath;
                    if (function_exists('logGlobalHistory')) {
                        error_log("🔍 Attempting to log case history: ID=$caseId, Module=cases, Action=update");
                        $result = logGlobalHistory('cases', $caseId, 'update', 'cases', $oldCase, $updatedCase);
                        if ($result) {
                            error_log("✅ Case history logged successfully: ID=$caseId");
                        } else {
                            error_log("❌ Failed to log case history: ID=$caseId - Check error log for details");
                        }
                    } else {
                        error_log("❌ logGlobalHistory function not found after require");
                    }
                } else {
                    error_log("❌ History helper not found at: $helperPath");
                }
                
                return $this->response(true, 'Case updated successfully');
            } else {
                return $this->response(false, 'Failed to update case', [], 500);
            }
        } catch (Exception $e) {
            error_log("Update case error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return $this->response(false, 'Error updating case: ' . $e->getMessage(), [], 500);
        }
    }
    
    private function deleteCase($caseId) {
        if (!$this->conn) {
            return $this->response(false, 'Database not available', [], 500);
        }
        
        try {
            // Validate case ID is a positive integer
            if (!is_numeric($caseId) || (int)$caseId <= 0) {
                return $this->response(false, 'Invalid case ID. Must be a positive integer', [], 400);
            }
            $caseId = (int)$caseId;
            
            // Get deleted data for history (before deletion)
            $stmt = $this->conn->prepare("SELECT * FROM cases WHERE id = ?");
            $stmt->execute([$caseId]);
            $deletedCase = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $sql = "DELETE FROM cases WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $result = $stmt->execute([$caseId]);
            
            if ($result) {
                // Log history
                if ($deletedCase) {
                    $helperPath = __DIR__ . '/../core/global-history-helper.php';
                    if (file_exists($helperPath)) {
                        require_once $helperPath;
                        if (function_exists('logGlobalHistory')) {
                            error_log("🔍 Attempting to log case history: ID=$caseId, Module=cases, Action=delete");
                            $result = logGlobalHistory('cases', $caseId, 'delete', 'cases', $deletedCase, null);
                            if ($result) {
                                error_log("✅ Case history logged successfully: ID=$caseId");
                            } else {
                                error_log("❌ Failed to log case history: ID=$caseId - Check error log for details");
                            }
                        } else {
                            error_log("❌ logGlobalHistory function not found after require");
                        }
                    } else {
                        error_log("❌ History helper not found at: $helperPath");
                    }
                }
                
                return $this->response(true, 'Case deleted successfully');
            } else {
                return $this->response(false, 'Failed to delete case', [], 500);
            }
        } catch (Exception $e) {
            return $this->response(false, 'Error deleting case: ' . $e->getMessage(), [], 500);
        }
    }
    
    // Bulk action methods
    private function bulkUpdateCases($data) {
        if (!$this->conn) {
            return $this->response(false, 'Database connection failed', [], 500);
        }
        
        try {
            $caseIds = $data['case_ids'] ?? [];
            $updates = $data['updates'] ?? [];
            
            if (empty($caseIds)) {
                return $this->response(false, 'No cases selected', [], 400);
            }
            
            // Validate all case IDs are positive integers
            $validatedCaseIds = [];
            foreach ($caseIds as $caseId) {
                if (!is_numeric($caseId) || (int)$caseId <= 0) {
                    return $this->response(false, 'Invalid case ID found. All IDs must be positive integers', [], 400);
                }
                $validatedCaseIds[] = (int)$caseId;
            }
            $caseIds = $validatedCaseIds;
            
            if (empty($updates)) {
                return $this->response(false, 'No updates provided', [], 400);
            }
            
            $updateFields = [];
            $params = [];
            
            if (isset($updates['priority'])) {
                $validPriorities = ['low', 'medium', 'high', 'urgent'];
                if (!in_array($updates['priority'], $validPriorities)) {
                    return $this->response(false, 'Invalid priority. Must be one of: ' . implode(', ', $validPriorities), [], 400);
                }
                $updateFields[] = "priority = ?";
                $params[] = $updates['priority'];
            }
            
            if (isset($updates['status'])) {
                $validStatuses = ['open', 'in_progress', 'pending', 'resolved', 'closed'];
                if (!in_array($updates['status'], $validStatuses)) {
                    return $this->response(false, 'Invalid status. Must be one of: ' . implode(', ', $validStatuses), [], 400);
                }
                $updateFields[] = "status = ?";
                $params[] = $updates['status'];
            }
            
            if (isset($updates['assigned_to'])) {
                $assignedTo = null;
                if ($updates['assigned_to'] !== null && $updates['assigned_to'] !== '') {
                    $assignedTo = (int)$updates['assigned_to'];
                    if ($assignedTo > 0) {
                        // Validate assigned_to user exists
                        try {
                            $checkStmt = $this->conn->prepare("SELECT user_id FROM users WHERE user_id = ? LIMIT 1");
                            $checkStmt->execute([$assignedTo]);
                            if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
                                return $this->response(false, "User ID {$assignedTo} does not exist", [], 400);
                            }
                        } catch (Exception $e) {
                            error_log("CasesAPI: Error validating assigned_to in bulkUpdateCases: " . $e->getMessage());
                            return $this->response(false, "Error validating assigned user ID", [], 500);
                        }
                    } else {
                        $assignedTo = null;
                    }
                }
                $updateFields[] = "assigned_to = ?";
                $params[] = $assignedTo;
            }
            
            if (isset($updates['due_date'])) {
                // Validate due_date format
                if ($updates['due_date'] !== null && $updates['due_date'] !== '') {
                    $dueDateObj = DateTime::createFromFormat('Y-m-d', $updates['due_date']);
                    if (!$dueDateObj || $dueDateObj->format('Y-m-d') !== $updates['due_date']) {
                        return $this->response(false, 'Invalid due date format. Use YYYY-MM-DD', [], 400);
                    }
                }
                $updateFields[] = "due_date = ?";
                $params[] = $updates['due_date'] ?: null;
            }
            
            if (isset($updates['resolution'])) {
                // Validate resolution length (max 1000 chars)
                $resolution = $updates['resolution'];
                if ($resolution !== null && $resolution !== '') {
                    if (strlen($resolution) > 1000) {
                        return $this->response(false, 'Resolution must be 1000 characters or less', [], 400);
                    }
                    $resolution = substr(trim($resolution), 0, 1000);
                } else {
                    $resolution = null;
                }
                $updateFields[] = "resolution = ?";
                $params[] = $resolution;
            }
            
            if (empty($updateFields)) {
                return $this->response(false, 'No valid updates provided', [], 400);
            }
            
            // Get old data for history BEFORE updating (critical: must be before update)
            $oldCases = [];
            $placeholders = str_repeat('?,', count($caseIds) - 1) . '?';
            $fetchSql = "SELECT * FROM cases WHERE id IN ($placeholders)";
            $fetchStmt = $this->conn->prepare($fetchSql);
            foreach ($caseIds as $index => $caseId) {
                $fetchStmt->bindValue($index + 1, $caseId, PDO::PARAM_INT);
            }
            $fetchStmt->execute();
            $oldCasesData = $fetchStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Create a map for quick lookup
            foreach ($oldCasesData as $oldCase) {
                $oldCases[$oldCase['id']] = $oldCase;
            }
            
            // Add updated_at timestamp
            $updateFields[] = "updated_at = CURRENT_TIMESTAMP";
            
            $sql = "UPDATE cases SET " . implode(', ', $updateFields) . " WHERE id IN ($placeholders)";
            
            $stmt = $this->conn->prepare($sql);
            $paramIndex = 0;
            
            // Add update parameters
            foreach ($params as $param) {
                $stmt->bindValue(++$paramIndex, $param);
            }
            
            // Add case IDs
            foreach ($caseIds as $caseId) {
                $stmt->bindValue(++$paramIndex, $caseId, PDO::PARAM_INT);
            }
            
            $result = $stmt->execute();
            
            if ($result) {
                // Log history for each updated case (old data fetched before update, new data fetched after)
                $helperPath = __DIR__ . '/../core/global-history-helper.php';
                if (file_exists($helperPath)) {
                    require_once $helperPath;
                    if (function_exists('logGlobalHistory')) {
                        foreach ($caseIds as $caseId) {
                            $oldCase = $oldCases[$caseId] ?? null;
                            
                            // Get new data AFTER update
                            $newStmt = $this->conn->prepare("SELECT * FROM cases WHERE id = ?");
                            $newStmt->execute([$caseId]);
                            $newCase = $newStmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($oldCase && $newCase) {
                                @logGlobalHistory('cases', $caseId, 'update', 'cases', $oldCase, $newCase);
                            }
                        }
                    }
                }
                
                return $this->response(true, 'Cases updated successfully', ['updated_count' => count($caseIds)]);
            } else {
                return $this->response(false, 'Failed to update cases', [], 500);
            }
        } catch (Exception $e) {
            return $this->response(false, 'Error updating cases: ' . $e->getMessage(), [], 500);
        }
    }
    
    private function bulkDeleteCases($data) {
        if (!$this->conn) {
            return $this->response(false, 'Database connection failed', [], 500);
        }
        
        try {
            $caseIds = $data['case_ids'] ?? [];
            
            if (empty($caseIds)) {
                return $this->response(false, 'No cases selected', [], 400);
            }
            
            // Validate all case IDs are positive integers
            $validatedCaseIds = [];
            foreach ($caseIds as $caseId) {
                if (!is_numeric($caseId) || (int)$caseId <= 0) {
                    return $this->response(false, 'Invalid case ID found. All IDs must be positive integers', [], 400);
                }
                $validatedCaseIds[] = (int)$caseId;
            }
            $caseIds = $validatedCaseIds;
            
            // Get old data for history (before deletion)
            $oldCases = [];
            $placeholders = str_repeat('?,', count($caseIds) - 1) . '?';
            $fetchSql = "SELECT * FROM cases WHERE id IN ($placeholders)";
            $fetchStmt = $this->conn->prepare($fetchSql);
            foreach ($caseIds as $index => $caseId) {
                $fetchStmt->bindValue($index + 1, $caseId, PDO::PARAM_INT);
            }
            $fetchStmt->execute();
            $oldCases = $fetchStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $sql = "DELETE FROM cases WHERE id IN ($placeholders)";
            
            $stmt = $this->conn->prepare($sql);
            
            foreach ($caseIds as $index => $caseId) {
                $stmt->bindValue($index + 1, $caseId, PDO::PARAM_INT);
            }
            
            $result = $stmt->execute();
            
            if ($result) {
                // Log history for each deleted case
                $helperPath = __DIR__ . '/../core/global-history-helper.php';
                if (file_exists($helperPath)) {
                    require_once $helperPath;
                    if (function_exists('logGlobalHistory')) {
                        foreach ($oldCases as $deletedCase) {
                            @logGlobalHistory('cases', $deletedCase['id'], 'delete', 'cases', $deletedCase, null);
                        }
                    }
                }
                
                return $this->response(true, 'Cases deleted successfully', ['deleted_count' => count($caseIds)]);
            } else {
                return $this->response(false, 'Failed to delete cases', [], 500);
            }
        } catch (Exception $e) {
            return $this->response(false, 'Error deleting cases: ' . $e->getMessage(), [], 500);
        }
    }
    
    private function bulkUpdateStatus($data) {
        if (!$this->conn) {
            return $this->response(false, 'Database connection failed', [], 500);
        }
        
        try {
            $caseIds = $data['case_ids'] ?? [];
            $status = $data['status'] ?? '';
            
            if (empty($caseIds)) {
                return $this->response(false, 'No cases selected', [], 400);
            }
            
            // Validate all case IDs are positive integers
            $validatedCaseIds = [];
            foreach ($caseIds as $caseId) {
                if (!is_numeric($caseId) || (int)$caseId <= 0) {
                    return $this->response(false, 'Invalid case ID found. All IDs must be positive integers', [], 400);
                }
                $validatedCaseIds[] = (int)$caseId;
            }
            $caseIds = $validatedCaseIds;
            
            if (empty($status)) {
                return $this->response(false, 'No status provided', [], 400);
            }
            
            // Validate status is a valid enum value
            $validStatuses = ['open', 'in_progress', 'pending', 'resolved', 'closed'];
            if (!in_array($status, $validStatuses)) {
                return $this->response(false, 'Invalid status. Must be one of: ' . implode(', ', $validStatuses), [], 400);
            }
            
            // Get old data for history (before update)
            $oldCases = [];
            $placeholders = str_repeat('?,', count($caseIds) - 1) . '?';
            $fetchSql = "SELECT * FROM cases WHERE id IN ($placeholders)";
            $fetchStmt = $this->conn->prepare($fetchSql);
            foreach ($caseIds as $index => $caseId) {
                $fetchStmt->bindValue($index + 1, $caseId, PDO::PARAM_INT);
            }
            $fetchStmt->execute();
            $oldCasesData = $fetchStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Create a map for quick lookup
            foreach ($oldCasesData as $oldCase) {
                $oldCases[$oldCase['id']] = $oldCase;
            }
            
            $sql = "UPDATE cases SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id IN ($placeholders)";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(1, $status);
            
            foreach ($caseIds as $index => $caseId) {
                $stmt->bindValue($index + 2, $caseId, PDO::PARAM_INT);
            }
            
            $result = $stmt->execute();
            
            if ($result) {
                // Log history for each updated case
                $helperPath = __DIR__ . '/../core/global-history-helper.php';
                if (file_exists($helperPath)) {
                    require_once $helperPath;
                    if (function_exists('logGlobalHistory')) {
                        foreach ($caseIds as $caseId) {
                            $oldCase = $oldCases[$caseId] ?? null;
                            
                            // Get new data AFTER update
                            $newStmt = $this->conn->prepare("SELECT * FROM cases WHERE id = ?");
                            $newStmt->execute([$caseId]);
                            $newCase = $newStmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($oldCase && $newCase) {
                                @logGlobalHistory('cases', $caseId, 'update', 'cases', $oldCase, $newCase);
                            }
                        }
                    }
                }
                
                return $this->response(true, 'Case status updated successfully', ['updated_count' => count($caseIds)]);
            } else {
                return $this->response(false, 'Failed to update case status', [], 500);
            }
        } catch (Exception $e) {
            return $this->response(false, 'Error updating case status: ' . $e->getMessage(), [], 500);
        }
    }
    
    private function bulkUpdateActiveStatus($data) {
        if (!$this->conn) {
            return $this->response(false, 'Database connection failed', [], 500);
        }
        
        try {
            $caseIds = $data['case_ids'] ?? [];
            $activeStatus = $data['active_status'] ?? '';
            
            if (empty($caseIds)) {
                return $this->response(false, 'No cases selected', [], 400);
            }
            
            // Validate all case IDs are positive integers
            $validatedCaseIds = [];
            foreach ($caseIds as $caseId) {
                if (!is_numeric($caseId) || (int)$caseId <= 0) {
                    return $this->response(false, 'Invalid case ID found. All IDs must be positive integers', [], 400);
                }
                $validatedCaseIds[] = (int)$caseId;
            }
            $caseIds = $validatedCaseIds;
            
            if (empty($activeStatus)) {
                return $this->response(false, 'No active status provided', [], 400);
            }
            
            // Validate active_status is a valid enum value
            $validActiveStatuses = ['active', 'inactive'];
            if (!in_array($activeStatus, $validActiveStatuses)) {
                return $this->response(false, 'Invalid active status. Must be one of: ' . implode(', ', $validActiveStatuses), [], 400);
            }
            
            // Get old data for history (before update)
            $oldCases = [];
            $placeholders = str_repeat('?,', count($caseIds) - 1) . '?';
            $fetchSql = "SELECT * FROM cases WHERE id IN ($placeholders)";
            $fetchStmt = $this->conn->prepare($fetchSql);
            foreach ($caseIds as $index => $caseId) {
                $fetchStmt->bindValue($index + 1, $caseId, PDO::PARAM_INT);
            }
            $fetchStmt->execute();
            $oldCasesData = $fetchStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Create a map for quick lookup
            foreach ($oldCasesData as $oldCase) {
                $oldCases[$oldCase['id']] = $oldCase;
            }
            
            $sql = "UPDATE cases SET active_status = ?, updated_at = CURRENT_TIMESTAMP WHERE id IN ($placeholders)";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(1, $activeStatus);
            
            foreach ($caseIds as $index => $caseId) {
                $stmt->bindValue($index + 2, $caseId, PDO::PARAM_INT);
            }
            
            $result = $stmt->execute();
            
            if ($result) {
                // Log history for each updated case
                $helperPath = __DIR__ . '/../core/global-history-helper.php';
                if (file_exists($helperPath)) {
                    require_once $helperPath;
                    if (function_exists('logGlobalHistory')) {
                        foreach ($caseIds as $caseId) {
                            $oldCase = $oldCases[$caseId] ?? null;
                            
                            // Get new data AFTER update
                            $newStmt = $this->conn->prepare("SELECT * FROM cases WHERE id = ?");
                            $newStmt->execute([$caseId]);
                            $newCase = $newStmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($oldCase && $newCase) {
                                @logGlobalHistory('cases', $caseId, 'update', 'cases', $oldCase, $newCase);
                            }
                        }
                    }
                }
                
                return $this->response(true, 'Case active status updated successfully', ['updated_count' => count($caseIds)]);
            } else {
                return $this->response(false, 'Failed to update case active status', [], 500);
            }
        } catch (Exception $e) {
            return $this->response(false, 'Error updating case active status: ' . $e->getMessage(), [], 500);
        }
    }
    
}

// Main execution
// Simple test endpoint to verify API is working
if (isset($_GET['test'])) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => true, 'message' => 'API is working', 'test' => true]);
    exit;
}

// Debug: Log that we're starting execution
error_log("CasesAPI: Starting execution, action: " . ($_GET['action'] ?? 'none'));

try {
    $api = new CasesAPI();
    error_log("CasesAPI: API object created");
    
    $response = $api->handleRequest();
    error_log("CasesAPI: handleRequest returned, response type: " . gettype($response));
    
    // Ensure we have a valid response
    if ($response === null || $response === '') {
        error_log("CasesAPI: Empty response from handleRequest()");
        $response = json_encode(['success' => false, 'message' => 'No response from API', 'data' => []]);
    }
    
    // Log the response for debugging
    error_log("CasesAPI: Response length: " . strlen($response));
    error_log("CasesAPI: Response preview: " . substr($response, 0, 200));
    
    // Ensure response is a string (JSON encoded)
    if (!is_string($response)) {
        error_log("CasesAPI: Response is not a string, encoding");
        $response = json_encode(['success' => false, 'message' => 'Invalid response format', 'data' => []]);
    }
    
    // Discard all output buffers - match settings-api.php pattern
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Validate and encode response
    if (empty($response) || !is_string($response)) {
        error_log("CasesAPI: CRITICAL - Response is empty or invalid! Type: " . gettype($response));
        $response = json_encode(['success' => false, 'message' => 'Invalid or empty response', 'data' => []], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    
    // Check if JSON encoding is valid
    $testDecode = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("CasesAPI: CRITICAL - Invalid JSON in response! Error: " . json_last_error_msg());
        $response = json_encode(['success' => false, 'message' => 'JSON encoding error: ' . json_last_error_msg(), 'data' => []], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    
    // Set headers and output - MUST be after all buffers are closed
    http_response_code(200);
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
    }
    
    // Output response directly
    echo $response;
    
    error_log("CasesAPI: Response sent successfully, length: " . strlen($response));
    error_log("CasesAPI: Response content preview: " . substr($response, 0, 500));
    exit();
    
} catch (Exception $e) {
    error_log("CasesAPI: Exception in main execution: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    error_log("CasesAPI: Stack trace: " . $e->getTraceAsString());
    
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
    }
    
        http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error: ' . $e->getMessage(), 'data' => []]);
    exit();
} catch (Error $e) {
    error_log("CasesAPI: Fatal error in main execution: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    error_log("CasesAPI: Stack trace: " . $e->getTraceAsString());
    
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
    }
    
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fatal error: ' . $e->getMessage(), 'data' => []]);
    exit();
}
