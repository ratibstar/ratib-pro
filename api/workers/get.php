<?php
/**
 * EN: Handles API endpoint/business logic in `api/workers/get.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/workers/get.php`.
 */
// Error reporting (Production: log only, don't display)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Check if required files exist
    if (!file_exists(__DIR__ . '/../core/Database.php')) {
        throw new Exception('Database.php file not found');
    }
    if (!file_exists(__DIR__ . '/../core/ApiResponse.php')) {
        throw new Exception('ApiResponse.php file not found');
    }
    
    // Load Database class FIRST, before api-permission-helper which loads config.php
    // This ensures our Database class with getInstance() is loaded before any old Database class
    require_once __DIR__ . '/../core/Database.php';
    require_once __DIR__ . '/../core/ApiResponse.php';
    
    // Verify Database class has getInstance method
    if (!method_exists('Database', 'getInstance')) {
        throw new Exception('Database class does not have getInstance method. Class may have been overridden.');
    }
    
    require_once __DIR__ . '/../core/api-permission-helper.php';

    // Enforce permission for viewing workers
    enforceApiPermission('workers', 'get');

    $db = Database::getInstance();
    if (!$db) {
        throw new Exception('Failed to get Database instance');
    }

    // Get query parameters
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 10;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    
    $offset = ($page - 1) * $limit;
    
    // Build WHERE conditions
    $whereConditions = ["w.status != 'deleted'"];
    $params = [];

    // Add search condition
    if ($search) {
        $whereConditions[] = "(w.worker_name LIKE ? OR w.id LIKE ? OR w.email LIKE ? OR w.passport_number LIKE ?)";
        array_push($params, "%$search%", "%$search%", "%$search%", "%$search%");
    }

    // Add status filter
    if ($status) {
        $whereConditions[] = "w.status = ?";
        $params[] = $status;
    }

    $whereClause = implode(' AND ', $whereConditions);

    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM workers w WHERE $whereClause";
    $countResult = $db->queryOne($countQuery, $params);
    if (!$countResult) {
        throw new Exception("Failed to get count from database");
    }
    $total = $countResult['total'];

    // Build main query
    $query = "
        SELECT w.*, 
               a.agent_name,
               s.subagent_name
        FROM workers w
        LEFT JOIN agents a ON w.agent_id = a.id
        LEFT JOIN subagents s ON w.subagent_id = s.id
        WHERE $whereClause
    ";

    // Add sorting and pagination
    $query .= " ORDER BY w.id DESC LIMIT ? OFFSET ?";
    array_push($params, $limit, $offset);

    // Get paginated data
    $workers = $db->query($query, $params);

    // Format response
    $response = [
        'data' => [
            'workers' => $workers,
            'pagination' => [
                'total' => (int)$total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ]
        ]
    ];

    echo ApiResponse::success($response);

} catch (Exception $e) {
    error_log("Workers API Error: " . $e->getMessage());
    error_log("Workers API Stack Trace: " . $e->getTraceAsString());
    echo ApiResponse::error($e->getMessage());
} catch (Error $e) {
    error_log("Workers API Fatal Error: " . $e->getMessage());
    error_log("Workers API Fatal Stack Trace: " . $e->getTraceAsString());
    echo ApiResponse::error("Fatal error: " . $e->getMessage());
} 