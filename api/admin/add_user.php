<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/add_user.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/add_user.php`.
 */
require_once '../../includes/config.php';

header('Content-Type: application/json');

try {
    // Handle both JSON and form data
    $input = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['username'])) {
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
    
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';
    $email = trim($input['email'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $role = $input['role'] ?? '';
    $custom_role = trim($input['custom_role'] ?? '');
    $status = $input['status'] ?? 'active';
    $registerFace = isset($input['register_face']) && $input['register_face'] === 'on';
    $registerFingerprint = isset($input['register_fingerprint']) && $input['register_fingerprint'] === 'on';
    
    // Validate input
    if (empty($username) || empty($password) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Username, password, and email are required']);

require_once '../../includes/permission_middleware.php';

// Check if user has permission to access this endpoint
checkApiPermission('users_create');


        exit;
    }
    
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username already exists']);
        exit;
    }
    
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
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
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("
        INSERT INTO users (username, password, email, phone, role_id, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("ssssis", $username, $hashedPassword, $email, $phone, $roleId, $status);
    
    if ($stmt->execute()) {
        $userId = $conn->insert_id;
        
        // If biometric registration is requested, create placeholder templates
        if ($registerFace) {
            $faceTemplate = base64_encode('placeholder_face_template_' . $userId);
            $stmt = $conn->prepare("
                INSERT INTO face_templates (user_id, template_data, template_version, confidence_threshold) 
                VALUES (?, ?, '1.0', 0.80)
            ");
            $stmt->bind_param("is", $userId, $faceTemplate);
            $stmt->execute();
        }
        
        if ($registerFingerprint) {
            $fingerprintTemplate = base64_encode('placeholder_fingerprint_template_' . $userId);
            $stmt = $conn->prepare("
                INSERT INTO fingerprint_templates (user_id, template_data, finger_position, template_version) 
                VALUES (?, ?, 'thumb', '1.0')
            ");
            $stmt->bind_param("is", $userId, $fingerprintTemplate);
            $stmt->execute();
        }
        
        // Get created user for history
        $fetchStmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
        $fetchStmt->bind_param("i", $userId);
        $fetchStmt->execute();
        $result = $fetchStmt->get_result();
        $newUser = $result->fetch_assoc();
        
        // Log history
        $helperPath = __DIR__ . '/../core/global-history-helper.php';
        if (file_exists($helperPath) && $newUser) {
            require_once $helperPath;
            if (function_exists('logGlobalHistory')) {
                @logGlobalHistory('users', $userId, 'create', 'settings', null, $newUser);
            }
        }
        
        // Get role name for response
        $stmt = $conn->prepare("SELECT role_name FROM roles WHERE role_id = ?");
        $stmt->bind_param("i", $roleId);
        $stmt->execute();
        $roleResult = $stmt->get_result()->fetch_assoc();
        $roleName = $roleResult['role_name'] ?? '';
        
        // Auto-create GL account in system accounts when user has Accounting role
        if ($roleName && strtolower(trim($roleName)) === 'accounting') {
            require_once __DIR__ . '/../accounting/entity-account-helper.php';
            if (function_exists('ensureEntityAccount')) {
                ensureEntityAccount($conn, 'accounting', $userId, $username);
            }
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'User added successfully',
            'user_id' => $userId,
            'username' => $username,
            'email' => $email,
            'role' => $roleName,
            'status' => $status
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add user']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?> 