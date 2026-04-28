<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/save_role.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/save_role.php`.
 */
require_once '../../includes/config.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
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
    
    $roleName = $input['role_name'] ?? null;
    $roleDescription = $input['role_description'] ?? '';
    $permissions = $input['permissions'] ?? [];
    
    if (!$roleName) {
        echo json_encode(['success' => false, 'message' => 'Role name is required']);
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Check if role already exists
        $stmt = $conn->prepare("SELECT role_id FROM roles WHERE role_name = ?");
        $stmt->bind_param("s", $roleName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing role
            $role = $result->fetch_assoc();
            $roleId = $role['role_id'];
            
            // Get old data for history
            $fetchStmt = $conn->prepare("SELECT * FROM roles WHERE role_id = ?");
            $fetchStmt->bind_param("i", $roleId);
            $fetchStmt->execute();
            $result = $fetchStmt->get_result();
            $oldRole = $result->fetch_assoc();
            
            $stmt = $conn->prepare("UPDATE roles SET description = ?, permissions = ? WHERE role_id = ?");
            $permissionsJson = json_encode($permissions);
            $stmt->bind_param("ssi", $roleDescription, $permissionsJson, $roleId);
            $stmt->execute();
            
            // Get updated role for history
            $fetchStmt = $conn->prepare("SELECT * FROM roles WHERE role_id = ?");
            $fetchStmt->bind_param("i", $roleId);
            $fetchStmt->execute();
            $result = $fetchStmt->get_result();
            $updatedRole = $result->fetch_assoc();
            
            // Log history
            $helperPath = __DIR__ . '/../core/global-history-helper.php';
            if (file_exists($helperPath) && $oldRole && $updatedRole) {
                require_once $helperPath;
                if (function_exists('logGlobalHistory')) {
                    @logGlobalHistory('roles', $roleId, 'update', 'settings', $oldRole, $updatedRole);
                }
            }
            
            $message = 'Role updated successfully';
        } else {
            // Create new role
            $stmt = $conn->prepare("INSERT INTO roles (role_name, description, permissions) VALUES (?, ?, ?)");
            $permissionsJson = json_encode($permissions);
            $stmt->bind_param("sss", $roleName, $roleDescription, $permissionsJson);
            $stmt->execute();
            
            $roleId = $conn->insert_id;
            
            // Get created role for history
            $fetchStmt = $conn->prepare("SELECT * FROM roles WHERE role_id = ?");
            $fetchStmt->bind_param("i", $roleId);
            $fetchStmt->execute();
            $result = $fetchStmt->get_result();
            $newRole = $result->fetch_assoc();
            
            // Log history
            $helperPath = __DIR__ . '/../core/global-history-helper.php';
            if (file_exists($helperPath) && $newRole) {
                require_once $helperPath;
                if (function_exists('logGlobalHistory')) {
                    @logGlobalHistory('roles', $roleId, 'create', 'settings', null, $newRole);
                }
            }
            
            $message = 'Role created successfully';
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'role_name' => $roleName
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
