<?php
/**
 * List portal roles for user forms and mobile portal configuration.
 */
require_once '../../includes/config.php';
require_once '../../includes/permissions.php';
require_once '../../includes/portal-roles.php';

header('Content-Type: application/json');

try {
    $isControl = !empty($_SESSION['control_logged_in']);
    $isAppUser = function_exists('rateb_staff_page_session_ok')
        ? rateb_staff_page_session_ok()
        : (function_exists('rateb_program_session_is_valid_user') && rateb_program_session_is_valid_user());
    if (!$isControl && !$isAppUser) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }
    if (!$isControl) {
        $isAdmin = isset($_SESSION['role_id']) && (int) $_SESSION['role_id'] === 1;
        $canManage = function_exists('hasPermission') && hasPermission('manage_settings');
        if (!$isAdmin && !$canManage) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
    }

    rateb_portal_roles_ensure_schema($conn);

    $activeOnly = !isset($_GET['all']) || (string) $_GET['all'] !== '1';
    $sql = 'SELECT id, name, portal_type, description, status, created_at FROM portal_roles';
    if ($activeOnly) {
        $sql .= " WHERE status = 'active'";
    }
    $sql .= ' ORDER BY name';

    $roles = [];
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $roles[] = $row;
        }
    }

    echo json_encode(['success' => true, 'roles' => $roles]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
