<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/get_permissions.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/get_permissions.php`.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}
if (!isset($_SESSION['role_id']) || (int)$_SESSION['role_id'] !== 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Simple mock data for testing
$permissions = [
    // Requests permissions
    ['permission_id' => 'add-request', 'name' => 'Add Request', 'category' => 'requests', 'description' => 'Add new request'],
    ['permission_id' => 'edit-request', 'name' => 'Edit Request', 'category' => 'requests', 'description' => 'Edit existing request'],
    ['permission_id' => 'delete-request', 'name' => 'Delete Request', 'category' => 'requests', 'description' => 'Delete request'],
    ['permission_id' => 'view-requests', 'name' => 'View Requests', 'category' => 'requests', 'description' => 'View all requests'],
    ['permission_id' => 'approve-request', 'name' => 'Approve Request', 'category' => 'requests', 'description' => 'Approve request'],
    ['permission_id' => 'reject-request', 'name' => 'Reject Request', 'category' => 'requests', 'description' => 'Reject request'],
    ['permission_id' => 'export-requests', 'name' => 'Export Requests', 'category' => 'requests', 'description' => 'Export requests data'],
    
    // Accommodation permissions
    ['permission_id' => 'add-accommodation', 'name' => 'Add Accommodation', 'category' => 'accommodation', 'description' => 'Add new accommodation'],
    ['permission_id' => 'edit-accommodation', 'name' => 'Edit Accommodation', 'category' => 'accommodation', 'description' => 'Edit accommodation'],
    ['permission_id' => 'delete-accommodation', 'name' => 'Delete Accommodation', 'category' => 'accommodation', 'description' => 'Delete accommodation'],
    ['permission_id' => 'view-accommodation', 'name' => 'View Accommodation', 'category' => 'accommodation', 'description' => 'View accommodation'],
    ['permission_id' => 'manage-accommodation', 'name' => 'Manage Accommodation', 'category' => 'accommodation', 'description' => 'Full accommodation management'],
    
    // Accounts permissions
    ['permission_id' => 'add-user', 'name' => 'Add User', 'category' => 'accounts', 'description' => 'Add new user'],
    ['permission_id' => 'edit-user', 'name' => 'Edit User', 'category' => 'accounts', 'description' => 'Edit user details'],
    ['permission_id' => 'delete-user', 'name' => 'Delete User', 'category' => 'accounts', 'description' => 'Delete user'],
    ['permission_id' => 'view-users', 'name' => 'View Users', 'category' => 'accounts', 'description' => 'View all users'],
    ['permission_id' => 'manage-roles', 'name' => 'Manage Roles', 'category' => 'accounts', 'description' => 'Manage user roles'],
    ['permission_id' => 'view-reports', 'name' => 'View Reports', 'category' => 'accounts', 'description' => 'View system reports'],
    ['permission_id' => 'export-data', 'name' => 'Export Data', 'category' => 'accounts', 'description' => 'Export system data'],
    
    // Agents permissions
    ['permission_id' => 'add-agent', 'name' => 'Add Agent', 'category' => 'agents', 'description' => 'Add new agent'],
    ['permission_id' => 'edit-agent', 'name' => 'Edit Agent', 'category' => 'agents', 'description' => 'Edit agent details'],
    ['permission_id' => 'delete-agent', 'name' => 'Delete Agent', 'category' => 'agents', 'description' => 'Delete agent'],
    ['permission_id' => 'view-agents', 'name' => 'View Agents', 'category' => 'agents', 'description' => 'View all agents'],
    ['permission_id' => 'manage-agent-permissions', 'name' => 'Manage Agent Permissions', 'category' => 'agents', 'description' => 'Manage agent permissions']
];

echo json_encode([
    'success' => true,
    'permissions' => $permissions,
    'message' => 'Permissions loaded successfully'
]);
?>
