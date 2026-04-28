<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/update_user.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/update_user.php`.
 */
require_once '../../includes/config.php';

header('Content-Type: application/json');

try {
    // Handle both JSON and form data
    $input = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['user_id'])) {
            // Form data
            $input = $_POST;
        } else {
            // JSON data
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
        }
    }
    
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
    
    $userId = $input['user_id'] ?? null;
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';
    $email = trim($input['email'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $role = $input['role'] ?? '';
    $custom_role = trim($input['custom_role'] ?? '');
    $status = $input['status'] ?? 'active';
    
    // Validate input
    if (!$userId || empty($username) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'User ID, username, and email are required']);

require_once '../../includes/permission_middleware.php';

// Check if user has permission to access this endpoint
checkApiPermission('users_edit');


        exit;
    }
    
    // Check if username already exists (excluding current user)
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
    $stmt->bind_param("si", $username, $userId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username already exists']);
        exit;
    }
    
    // Check if email already exists (excluding current user)
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $stmt->bind_param("si", $email, $userId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        exit;
    }
    
    // Handle role assignment
    $roleId = null;
    if (!empty($custom_role)) {
        // Check if custom role exists, if not create it
        $stmt = $conn->prepare("SELECT role_id FROM roles WHERE role_name = ?");
        $stmt->bind_param("s", $custom_role);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $roleId = $result->fetch_assoc()['role_id'];
        } else {
            // Create new custom role
            $stmt = $conn->prepare("INSERT INTO roles (role_name, description) VALUES (?, ?)");
            $description = "Custom role: " . $custom_role;
            $stmt->bind_param("ss", $custom_role, $description);
            $stmt->execute();
            $roleId = $conn->insert_id;
        }
    } else if (!empty($role)) {
        // Use selected role
        $stmt = $conn->prepare("SELECT role_id FROM roles WHERE role_id = ?");
        $stmt->bind_param("i", $role);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $roleId = $result->fetch_assoc()['role_id'];
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid role selected']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Either select a role or enter a custom role']);
        exit;
    }
    
    // Get old data for history
    $fetchStmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $fetchStmt->bind_param("i", $userId);
    $fetchStmt->execute();
    $result = $fetchStmt->get_result();
    $oldUser = $result->fetch_assoc();
    
    if (!$oldUser) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Update user
    if (!empty($password)) {
        // Update with new password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("
            UPDATE users SET username = ?, password = ?, email = ?, phone = ?, role_id = ?, status = ?, updated_at = NOW()
            WHERE user_id = ?
        ");
        $stmt->bind_param("ssssisi", $username, $hashedPassword, $email, $phone, $roleId, $status, $userId);
    } else {
        // Update without changing password
        $stmt = $conn->prepare("
            UPDATE users SET username = ?, email = ?, phone = ?, role_id = ?, status = ?, updated_at = NOW()
            WHERE user_id = ?
        ");
        $stmt->bind_param("ssssis", $username, $email, $phone, $roleId, $status, $userId);
    }
    
    if ($stmt->execute()) {
        // Get updated user for history
        $fetchStmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
        $fetchStmt->bind_param("i", $userId);
        $fetchStmt->execute();
        $result = $fetchStmt->get_result();
        $updatedUser = $result->fetch_assoc();
        
        // Log history
        $helperPath = __DIR__ . '/../core/global-history-helper.php';
        if (file_exists($helperPath) && $oldUser && $updatedUser) {
            require_once $helperPath;
            if (function_exists('logGlobalHistory')) {
                @logGlobalHistory('users', $userId, 'update', 'settings', $oldUser, $updatedUser);
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'User updated successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update user']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?> 