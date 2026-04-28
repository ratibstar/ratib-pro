<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/delete_role.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/delete_role.php`.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit();
}

// Validate required fields
if (!isset($input['role_name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required field: role_name']);
    exit();
}

$role_name = $input['role_name'];

// Validate role name format (basic validation)
if (empty($role_name) || strlen($role_name) > 50) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid role name format']);
    exit();
}

// Prevent deletion of essential roles
$essential_roles = ['admin']; // Only protect admin role
if (in_array($role_name, $essential_roles)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cannot delete essential role']);
    exit();
}

try {
    // Include the standard config file
    require_once '../../includes/config.php';
    
    // Check if admin is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        echo json_encode(['success' => false, 'message' => 'Admin not logged in']);
        exit;
    }
    
    // Check if user has admin role
    if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
        echo json_encode(['success' => false, 'message' => 'Access denied. Admin privileges required.']);
        exit;
    }
    
    // Get deleted data for history (before deletion)
    $fetchStmt = $conn->prepare("SELECT * FROM roles WHERE role_name = ?");
    $fetchStmt->bind_param("s", $role_name);
    $fetchStmt->execute();
    $result = $fetchStmt->get_result();
    $deletedRole = $result->fetch_assoc();
    
    if (!$deletedRole) {
        echo json_encode([
            'success' => false,
            'message' => 'Role not found'
        ]);
        exit;
    }
    
    // Check if any users are using this role
    $stmt = $conn->prepare("SELECT COUNT(*) as user_count FROM users WHERE role = ?");
    $stmt->bind_param("s", $role_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_count = $result->fetch_assoc()['user_count'];
    
    if ($user_count > 0) {
        echo json_encode([
            'success' => false, 
            'message' => "Cannot delete role. There are {$user_count} user(s) currently using this role."
        ]);
        exit;
    }
    
    // Delete role from database
    $stmt = $conn->prepare("DELETE FROM roles WHERE role_name = ?");
    $stmt->bind_param("s", $role_name);
    $result = $stmt->execute();
    
    if ($result && $stmt->affected_rows > 0) {
        // Log history
        $helperPath = __DIR__ . '/../core/global-history-helper.php';
        if (file_exists($helperPath) && $deletedRole) {
            require_once $helperPath;
            if (function_exists('logGlobalHistory')) {
                $roleId = $deletedRole['role_id'] ?? null;
                if ($roleId) {
                    @logGlobalHistory('roles', $roleId, 'delete', 'settings', $deletedRole, null);
                }
            }
        }
        
        // Success
        echo json_encode([
            'success' => true,
            'message' => 'Role deleted successfully',
            'role_name' => $role_name
        ]);
    } else {
        // Role not found or no changes made
        echo json_encode([
            'success' => false,
            'message' => 'Role not found or no changes made'
        ]);
    }
    
} catch (Exception $e) {
    // General error
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
