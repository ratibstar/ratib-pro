<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/get_roles.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/get_roles.php`.
 */
require_once '../../includes/config.php';
require_once '../../includes/permissions.php';

header('Content-Type: application/json');

try {
    $isControl = !empty($_SESSION['control_logged_in']);
    $isAppUser = function_exists('rateb_staff_page_session_ok')
        ? rateb_staff_page_session_ok()
        : (function_exists('rateb_program_session_is_valid_user') && rateb_program_session_is_valid_user());
    if (!$isControl && !$isAppUser) {
        echo json_encode(['success' => false, 'message' => 'Admin not logged in']);
        exit;
    }
    // Control admins bypass role check; app users need admin role or manage_settings
    if (!$isControl) {
        $isAdmin = isset($_SESSION['role_id']) && (int) $_SESSION['role_id'] === 1;
        $canManage = function_exists('hasPermission') && hasPermission('manage_settings');
        if (!$isAdmin && !$canManage) {
            echo json_encode(['success' => false, 'message' => 'Access denied. Admin privileges required.']);
            exit;
        }
    }

    if (!function_exists('rateb_ensure_portal_roles')) {
        function rateb_ensure_portal_roles(mysqli $conn): void
        {
            $defaults = [
                ['Admin', 'Company portal — full staff access', '[]'],
                ['Worker', 'Mobile worker portal access', '[]'],
                ['Agency', 'Recruitment agency portal access', '[]'],
            ];
            foreach ($defaults as $row) {
                [$name, $desc, $perms] = $row;
                $stmt = $conn->prepare('SELECT role_id FROM roles WHERE LOWER(role_name) = LOWER(?) LIMIT 1');
                if (!$stmt) {
                    continue;
                }
                $stmt->bind_param('s', $name);
                $stmt->execute();
                $res = $stmt->get_result();
                $exists = $res && $res->num_rows > 0;
                $stmt->close();
                if ($exists) {
                    continue;
                }
                $ins = $conn->prepare('INSERT INTO roles (role_name, description, permissions) VALUES (?, ?, ?)');
                if ($ins) {
                    $ins->bind_param('sss', $name, $desc, $perms);
                    $ins->execute();
                    $ins->close();
                }
            }
        }
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
        rateb_ensure_portal_roles($conn);

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
