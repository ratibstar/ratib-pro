<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/get_roles.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/get_roles.php`.
 */
require_once '../../includes/config.php';

header('Content-Type: application/json');

try {
    $isControl = !empty($_SESSION['control_logged_in']);
    $isAppUser = function_exists('ratib_program_session_is_valid_user') && ratib_program_session_is_valid_user();
    if (!$isControl && !$isAppUser) {
        echo json_encode(['success' => false, 'message' => 'Admin not logged in']);
        exit;
    }
    // Control admins bypass role check; app users need admin role
    if (!$isControl && (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1)) {
        echo json_encode(['success' => false, 'message' => 'Access denied. Admin privileges required.']);
        exit;
    }
    
    // Get specific role if role_name is provided
    $roleName = $_GET['role_name'] ?? null;
    
    if ($roleName) {
        // Get specific role
        $stmt = $conn->prepare("SELECT role_id, role_name, description, permissions FROM roles WHERE role_name = ?");
        $stmt->bind_param("s", $roleName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $role = $result->fetch_assoc();
            $role['permissions'] = json_decode($role['permissions'], true) ?: [];
            
            echo json_encode([
                'success' => true,
                'role' => $role
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Role not found'
            ]);
        }
    } else {
        // Get all roles
        $stmt = $conn->prepare("SELECT role_id, role_name, description, permissions, created_at FROM roles ORDER BY role_name");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $roles = [];
        while ($row = $result->fetch_assoc()) {
            $row['permissions'] = json_decode($row['permissions'], true) ?: [];
            $roles[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'roles' => $roles
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
