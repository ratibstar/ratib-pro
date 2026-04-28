<?php
/**
 * EN: Handles API endpoint/business logic in `api/subagents/get.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/subagents/get.php`.
 */
// Disable error display to prevent HTML output
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Set content type to JSON
header('Content-Type: application/json');

require_once __DIR__ . '/../core/api-permission-helper.php';
enforceApiPermission('subagents', 'get');

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

    // Temporarily disable permission check for API testing
    // require_once '../../includes/permission_middleware.php';
    // checkApiPermission('agents_view');

    $db = Database::getInstance();
    if (!$db) {
        throw new Exception('Failed to get Database instance');
    }

    // Get query parameters
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 10;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $agent_id = isset($_GET['agent_id']) ? (int)$_GET['agent_id'] : null;
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    $offset = ($page - 1) * $limit;
    
    // Build WHERE clause
    $whereConditions = ["s.status != 'deleted'"];
    $params = [];
    
    if ($agent_id) {
        $whereConditions[] = "s.agent_id = ?";
        $params[] = $agent_id;
    }
    
    // Add status filter
    if ($status && in_array($status, ['active', 'inactive'])) {
        $whereConditions[] = "s.status = ?";
        $params[] = $status;
    }
    
    if ($search) {
        $whereConditions[] = "(s.subagent_name LIKE ? OR s.email LIKE ? OR s.contact_number LIKE ? OR s.city LIKE ? OR s.address LIKE ? OR s.id LIKE ?)";
        $searchParam = "%$search%";
        array_push($params, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam);
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Count total records first
    $countQuery = "SELECT COUNT(*) as total FROM subagents s WHERE $whereClause";
    $countResult = $db->queryOne($countQuery, $params);
    if (!$countResult) {
        throw new Exception("Failed to get count from database");
    }
    $total = $countResult['total'];

    // Main query with ORDER BY subagent_id DESC for newest first
    $query = "
        SELECT 
            s.*,
            a.agent_name as agent_name
        FROM subagents s
        LEFT JOIN agents a ON s.agent_id = a.id
        WHERE $whereClause
        ORDER BY s.id DESC
        LIMIT ? OFFSET ?
    ";

    array_push($params, $limit, $offset);
    $subagents = $db->query($query, $params);

    // If id is specified, return single subagent
    if ($id) {
        $singleQuery = "
            SELECT 
                s.*,
                a.agent_name as agent_name
            FROM subagents s
            LEFT JOIN agents a ON s.agent_id = a.id
            WHERE s.id = ?
        ";
        $subagent = $db->queryOne($singleQuery, [$id]);
        
        if (!$subagent) {
            throw new Exception('Subagent not found');
        }
        
        // Transform single subagent data
        $transformedSubagent = [
            'subagent_id' => $subagent['id'],
            'formatted_id' => 'S' . str_pad($subagent['id'], 4, '0', STR_PAD_LEFT),
            'full_name' => $subagent['subagent_name'],
            'email' => $subagent['email'],
            'phone' => $subagent['contact_number'],
            'city' => $subagent['city'],
            'address' => $subagent['address'],
            'agent_id' => $subagent['agent_id'],
            'agent_name' => $subagent['agent_name'],
            'status' => $subagent['status'],
            'created_at' => $subagent['created_at'],
            'updated_at' => $subagent['updated_at']
        ];
        
        echo ApiResponse::success(['subagents' => [$transformedSubagent]]);
        return;
    }
    // Transform subagents data to match frontend expectations (deduplicate by id)
    $seenIds = [];
    $transformedSubagents = [];
    foreach ($subagents as $subagent) {
        $id = $subagent['id'];
        if (in_array($id, $seenIds)) continue;
        $seenIds[] = $id;
        $transformedSubagents[] = [
            'subagent_id' => $id,
            'formatted_id' => 'S' . str_pad($id, 4, '0', STR_PAD_LEFT),
            'full_name' => $subagent['subagent_name'],
            'email' => $subagent['email'],
            'phone' => $subagent['contact_number'],
            'city' => $subagent['city'],
            'address' => $subagent['address'],
            'agent_id' => $subagent['agent_id'],
            'agent_name' => $subagent['agent_name'],
            'status' => $subagent['status'],
            'created_at' => $subagent['created_at'],
            'updated_at' => $subagent['updated_at']
        ];
    }

    // If agent_id is specified, return just the subagents array directly (not wrapped in 'data' field)
    if ($agent_id) {
        $response = [
            'success' => true,
            'data' => $transformedSubagents
        ];
        echo json_encode($response);
    } else {
        // Return paginated response for general queries
        $response = [
            'subagents' => $transformedSubagents,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int)$total,
                'total_pages' => ceil($total / $limit)
            ]
        ];
        echo ApiResponse::success($response);
    }

} catch (Exception $e) {
    error_log("Subagents API Error: " . $e->getMessage());
    error_log("Subagents API Stack Trace: " . $e->getTraceAsString());
    echo ApiResponse::error($e->getMessage());
} catch (Error $e) {
    error_log("Subagents API Fatal Error: " . $e->getMessage());
    error_log("Subagents API Fatal Stack Trace: " . $e->getTraceAsString());
    echo ApiResponse::error("Fatal error: " . $e->getMessage());
}