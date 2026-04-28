<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/update_user_role.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/update_user_role.php`.
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
if (!isset($input['user_id']) || !isset($input['role'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields: user_id and role']);
    exit();
}

$user_id = intval($input['user_id']);
$role = $input['role'];

// Validate role
$valid_roles = ['admin', 'manager', 'editor', 'viewer', 'operator', 'agent', 'accounting'];
if (!in_array($role, $valid_roles)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid role specified']);
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
    
    // Get old user data
    $oldStmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $oldStmt->bind_param("i", $user_id);
    $oldStmt->execute();
    $oldResult = $oldStmt->get_result();
    $oldUser = $oldResult->fetch_assoc();
    
    // Update user role in database
    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE user_id = ?");
    $stmt->bind_param("si", $role, $user_id);
    $result = $stmt->execute();
    
    if ($result && $stmt->affected_rows > 0) {
        // Get new user data
        $newStmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
        $newStmt->bind_param("i", $user_id);
        $newStmt->execute();
        $newResult = $newStmt->get_result();
        $newUser = $newResult->fetch_assoc();
        
        // Log history
        $helperPath = __DIR__ . '/../core/global-history-helper.php';
        if (file_exists($helperPath) && $oldUser && $newUser) {
            require_once $helperPath;
            if (function_exists('logGlobalHistory')) {
                @logGlobalHistory('users', $user_id, 'update', 'settings', $oldUser, $newUser);
            }
        }
        
        // Success
        echo json_encode([
            'success' => true,
            'message' => 'User role updated successfully',
            'user_id' => $user_id,
            'role' => $role
        ]);
    } else {
        // User not found or no changes made
        echo json_encode([
            'success' => false,
            'message' => 'User not found or no changes made'
        ]);
    }
    
} catch (PDOException $e) {
    // Database error
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    // General error
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
