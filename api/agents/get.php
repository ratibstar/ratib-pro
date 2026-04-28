<?php
/**
 * EN: Handles API endpoint/business logic in `api/agents/get.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/agents/get.php`.
 */
// Start output buffering to prevent any accidental output
ob_start();

// Disable error display but keep error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set content type to JSON
header('Content-Type: application/json; charset=UTF-8');

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Fatal error: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']
        ]);
        exit;
    }
});

try {
require_once __DIR__ . '/../core/api-permission-helper.php';
enforceApiPermission('agents', 'get');
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Permission check failed: ' . $e->getMessage()
    ]);
    exit;
} catch (Error $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Permission check fatal error: ' . $e->getMessage()
    ]);
    exit;
}

try {
    // Check if required files exist
    $corePath = __DIR__ . '/../core/';
    if (!file_exists($corePath . 'Database.php')) {
        throw new Exception('Database.php file not found');
    }
    if (!file_exists($corePath . 'ApiResponse.php')) {
        throw new Exception('ApiResponse.php file not found');
    }
    
    require_once $corePath . 'Database.php';
    require_once $corePath . 'ApiResponse.php';
    
    // Test Database class
    $db = Database::getInstance();
    if (!$db) {
        throw new Exception('Failed to get Database instance');
    }
    
    // If requesting a single agent
    if (isset($_GET['id'])) {
        try {
        $agent = $db->queryOne("SELECT * FROM agents WHERE id = ?", [$_GET['id']]);
        if (!$agent) {
            throw new Exception("Agent not found");
            }
        } catch (Exception $e) {
            throw new Exception("Failed to fetch agent: " . $e->getMessage());
        }
        
        // Transform single agent data to match frontend expectations
        // Handle different possible column names
        $id = $agent['id'] ?? $agent['agent_id'] ?? 0;
        $formattedId = $agent['formatted_id'] ?? null;
        if (!$formattedId && $id) {
            $formattedId = 'A' . str_pad($id, 4, '0', STR_PAD_LEFT);
        }
        
        $transformedAgent = [
            'agent_id' => $id,
            'formatted_id' => $formattedId,
            'full_name' => $agent['agent_name'] ?? $agent['full_name'] ?? '',
            'email' => $agent['email'] ?? '',
            'phone' => $agent['contact_number'] ?? $agent['phone'] ?? '',
            'city' => isset($agent['city']) && $agent['city'] !== '' ? $agent['city'] : null,
            'address' => $agent['address'] ?? '',
            'status' => $agent['status'] ?? 'active',
            'created_at' => $agent['created_at'] ?? '',
            'updated_at' => $agent['updated_at'] ?? ''
        ];
        
        ob_clean();
        echo ApiResponse::success($transformedAgent);
        exit;
    }
    
    // Otherwise handle list request with filters
    $filters = [];
    $params = [];
    
    // Handle search
    if (!empty($_GET['search'])) {
        $search = "%{$_GET['search']}%";
        $filters[] = "(agent_name LIKE ? OR email LIKE ? OR contact_number LIKE ?)";
        array_push($params, $search, $search, $search);
    }
    
    // Handle status filter
    if (!empty($_GET['status'])) {
        $filters[] = "status = ?";
        $params[] = $_GET['status'];
    }
    
    // Handle pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
    $offset = ($page - 1) * $limit;
    
    // Ensure positive values
    $page = max(1, $page);
    $limit = max(1, min(100, $limit)); // Limit between 1 and 100
    $offset = max(0, $offset);
    
    // Build query
    $whereClause = $filters ? 'WHERE ' . implode(' AND ', $filters) : '';
    
    // Get total count - ensure proper spacing
    $countQuery = "SELECT COUNT(*) as total FROM agents" . ($whereClause ? " $whereClause" : "");
    try {
    $countResult = $db->queryOne($countQuery, $params);
        if (!$countResult || !isset($countResult['total'])) {
            throw new Exception("Failed to get count from database. Query: $countQuery");
    }
        $total = (int)$countResult['total'];
    } catch (Exception $e) {
        throw new Exception("Database count query failed: " . $e->getMessage());
    }
    
    // Get paginated data - LIMIT and OFFSET must be integers, not parameters
    // Select all columns and handle missing ones in PHP
    // Ensure proper spacing in query
    $query = "SELECT * FROM agents" . ($whereClause ? " $whereClause" : "") . " ORDER BY id DESC LIMIT $limit OFFSET $offset";
    try {
    $agents = $db->query($query, $params);
        if ($agents === false) {
            throw new Exception("Query returned false");
        }
        if (!is_array($agents)) {
            $agents = [];
        }
    } catch (Exception $e) {
        throw new Exception("Database query failed: " . $e->getMessage());
    }
    
    // Transform data to match frontend expectations
    // Handle both possible table structures (with/without city, formatted_id)
    $transformedAgents = array_map(function($agent) {
        // Handle different possible column names
        $id = $agent['id'] ?? $agent['agent_id'] ?? 0;
        $formattedId = $agent['formatted_id'] ?? null;
        if (!$formattedId && $id) {
            $formattedId = 'A' . str_pad($id, 4, '0', STR_PAD_LEFT);
        }
        
        $name = $agent['agent_name'] ?? $agent['full_name'] ?? '';
        $email = $agent['email'] ?? '';
        $phone = $agent['contact_number'] ?? $agent['phone'] ?? '';
        $city = isset($agent['city']) && $agent['city'] !== '' ? $agent['city'] : null;
        $address = $agent['address'] ?? '';
        $status = $agent['status'] ?? 'active';
        $createdAt = $agent['created_at'] ?? '';
        $updatedAt = $agent['updated_at'] ?? '';
        
        return [
            'agent_id' => $id,
            'formatted_id' => $formattedId,
            'full_name' => $name,
            'email' => $email,
            'phone' => $phone,
            'city' => $city,
            'address' => $address,
            'status' => $status,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt
        ];
    }, $agents ?: []);
    
    // Format response to match JavaScript expectations
    $response = [
        'list' => $transformedAgents,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ];
    
    ob_clean();
    echo ApiResponse::success($response);
    exit;
    
} catch (PDOException $e) {
    ob_clean();
    http_response_code(500);
    $errorMsg = "Database error: " . $e->getMessage();
    echo ApiResponse::error($errorMsg, 500);
    exit;
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo ApiResponse::error($e->getMessage(), 500);
    exit;
} catch (Error $e) {
    ob_clean();
    http_response_code(500);
    echo ApiResponse::error("Fatal error: " . $e->getMessage(), 500);
    exit;
} catch (Throwable $e) {
    ob_clean();
    http_response_code(500);
    echo ApiResponse::error("Unexpected error: " . $e->getMessage(), 500);
    exit;
}

// Final safety net - if we somehow reach here, return an error
ob_clean();
http_response_code(500);
echo json_encode([
    'success' => false,
    'message' => 'Unknown error occurred'
]);
exit;