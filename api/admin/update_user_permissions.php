<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/update_user_permissions.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/update_user_permissions.php`.
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
    
    $userId = $input['user_id'] ?? null;
    $permissions = $input['permissions'] ?? [];
    
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Delete existing permissions for this user
        $stmt = $conn->prepare("DELETE FROM user_permissions WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        
        // Insert new permissions
        $stmt = $conn->prepare("
            INSERT INTO user_permissions (user_id, module_name, can_view, can_add, can_edit, can_delete)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $modules = [];
        foreach ($permissions as $perm) {
            $moduleName = $perm['module_name'];
            $permission = $perm['permission'];
            $value = $perm['value'] ? 1 : 0;
            
            if (!isset($modules[$moduleName])) {
                $modules[$moduleName] = [
                    'can_view' => 0,
                    'can_add' => 0,
                    'can_edit' => 0,
                    'can_delete' => 0
                ];
            }
            
            $modules[$moduleName]['can_' . $permission] = $value;
        }
        
        foreach ($modules as $moduleName => $modulePerms) {
            $stmt->bind_param("isiiii", 
                $userId, 
                $moduleName, 
                $modulePerms['can_view'], 
                $modulePerms['can_add'], 
                $modulePerms['can_edit'], 
                $modulePerms['can_delete']
            );
            $stmt->execute();
        }
        
        // Get old permissions for history
        $oldPermStmt = $conn->prepare("SELECT * FROM user_permissions WHERE user_id = ?");
        $oldPermStmt->bind_param("i", $userId);
        $oldPermStmt->execute();
        $oldPermResult = $oldPermStmt->get_result();
        $oldPermissions = [];
        while ($row = $oldPermResult->fetch_assoc()) {
            $oldPermissions[] = $row;
        }
        
        // Get user data
        $userStmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
        $userStmt->bind_param("i", $userId);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        $userData = $userResult->fetch_assoc();
        
        // Commit transaction
        $conn->commit();
        
        // Get new permissions for history
        $newPermStmt = $conn->prepare("SELECT * FROM user_permissions WHERE user_id = ?");
        $newPermStmt->bind_param("i", $userId);
        $newPermStmt->execute();
        $newPermResult = $newPermStmt->get_result();
        $newPermissions = [];
        while ($row = $newPermResult->fetch_assoc()) {
            $newPermissions[] = $row;
        }
        
        // Log history
        $helperPath = __DIR__ . '/../core/global-history-helper.php';
        if (file_exists($helperPath) && $userData) {
            require_once $helperPath;
            if (function_exists('logGlobalHistory')) {
                $oldData = ['permissions' => $oldPermissions];
                $newData = ['permissions' => $newPermissions];
                @logGlobalHistory('users', $userId, 'update', 'settings', array_merge($userData, $oldData), array_merge($userData, $newData));
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Permissions updated successfully'
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