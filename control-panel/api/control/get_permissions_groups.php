<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/api/control/get_permissions_groups.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/api/control/get_permissions_groups.php`.
 */
/**
 * Control Panel: Permission groups for UI (same format as main app).
 * Self-contained – no dependency on api/settings.
 */
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Content-Type: application/json; charset=UTF-8');
header('Pragma: no-cache');

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control-permissions.php';

if (empty($_SESSION['control_logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Control panel login required']);
    exit;
}
if (!hasControlPermission(CONTROL_PERM_ADMINS) && !hasControlPermission('view_control_admins') && !hasControlPermission('manage_control_roles')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl || !($ctrl instanceof mysqli)) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Control database not available']);
    exit;
}

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$roleId = isset($_GET['role_id']) ? (int)$_GET['role_id'] : null;

$permissionGroups = [
    ['id' => 'control_core', 'name' => 'Control Panel - Core', 'count' => 8, 'permissions' => [
        ['id' => 'control_dashboard', 'name' => 'View Dashboard'],
        ['id' => 'control_select_country', 'name' => 'Select Country (all countries)'],
        ['id' => 'hide_dashboard_countries_card', 'name' => 'Show Dashboard "Active Countries" card'],
        ['id' => 'hide_dashboard_agencies_card', 'name' => 'Show Dashboard "Total Agencies" card'],
        ['id' => 'hide_dashboard_pending_requests_card', 'name' => 'Show Dashboard "Pending Requests" card'],
        ['id' => 'hide_dashboard_accounting_card', 'name' => 'Show Dashboard "Accounting" card'],
        ['id' => 'hide_dashboard_quick_actions', 'name' => 'Show Dashboard "Quick Actions" section'],
        ['id' => 'hide_dashboard_recent_requests', 'name' => 'Show Dashboard "Recent Requests" section']
    ]],
    ['id' => 'control_countries', 'name' => 'Countries Management', 'count' => 5, 'permissions' => [
        ['id' => 'control_countries', 'name' => 'Full Access (all below)'],
        ['id' => 'view_control_countries', 'name' => 'View Countries'],
        ['id' => 'add_control_country', 'name' => 'Add Country'],
        ['id' => 'edit_control_country', 'name' => 'Edit Country'],
        ['id' => 'delete_control_country', 'name' => 'Delete Country']
    ]],
    ['id' => 'control_agencies', 'name' => 'Agencies Management', 'count' => 6, 'permissions' => [
        ['id' => 'control_agencies', 'name' => 'Full Access (all below)'],
        ['id' => 'view_control_agencies', 'name' => 'View Agencies'],
        ['id' => 'open_control_agency', 'name' => 'Open Agency'],
        ['id' => 'add_control_agency', 'name' => 'Add Agency'],
        ['id' => 'edit_control_agency', 'name' => 'Edit Agency'],
        ['id' => 'delete_control_agency', 'name' => 'Delete Agency']
    ]],
    ['id' => 'control_country_users', 'name' => 'Country Users', 'count' => 2, 'permissions' => [
        ['id' => 'control_country_users', 'name' => 'Full Access (all below)'],
        ['id' => 'view_control_country_users', 'name' => 'View Country Users']
    ]],
    ['id' => 'control_registration', 'name' => 'Registration Requests', 'count' => 7, 'permissions' => [
        ['id' => 'control_registration_requests', 'name' => 'Full Access (all below)'],
        ['id' => 'view_control_registration', 'name' => 'View Requests (country-scoped)'],
        ['id' => 'view_all_control_registration', 'name' => 'View requests from all countries'],
        ['id' => 'edit_control_registration', 'name' => 'Edit Request'],
        ['id' => 'approve_control_registration', 'name' => 'Approve Request'],
        ['id' => 'reject_control_registration', 'name' => 'Reject Request'],
        ['id' => 'delete_control_registration', 'name' => 'Delete Request']
    ]],
    ['id' => 'control_support', 'name' => 'Support Chats', 'count' => 7, 'permissions' => [
        ['id' => 'control_support_chats', 'name' => 'Full Access (all below)'],
        ['id' => 'view_control_support', 'name' => 'View Chats'],
        ['id' => 'reply_control_support', 'name' => 'Reply to Chats'],
        ['id' => 'bulk_select_control_support', 'name' => 'Bulk: Select all / row checkboxes'],
        ['id' => 'bulk_mark_closed_control_support', 'name' => 'Bulk: Mark closed'],
        ['id' => 'bulk_mark_open_control_support', 'name' => 'Bulk: Mark open'],
        ['id' => 'bulk_delete_control_support', 'name' => 'Bulk: Delete selected']
    ]],
    ['id' => 'control_designed_site', 'name' => 'Designed Site (navbar link)', 'count' => 2, 'permissions' => [
        ['id' => 'control_designed_site', 'name' => 'Full access (show link)'],
        ['id' => 'view_control_designed_site', 'name' => 'View / open Designed site']
    ]],
    ['id' => 'control_accounting', 'name' => 'Accounting', 'count' => 3, 'permissions' => [
        ['id' => 'control_accounting', 'name' => 'Full Access (all below)'],
        ['id' => 'view_control_accounting', 'name' => 'View Accounting'],
        ['id' => 'manage_control_accounting', 'name' => 'Manage Accounting']
    ]],
    ['id' => 'control_hr', 'name' => 'HR Command Center', 'count' => 3, 'permissions' => [
        ['id' => 'control_hr', 'name' => 'Full access (all below)'],
        ['id' => 'view_control_hr', 'name' => 'View HR center'],
        ['id' => 'manage_control_hr', 'name' => 'Manage HR center']
    ]],
    ['id' => 'control_government', 'name' => 'Government Control', 'count' => 4, 'permissions' => [
        ['id' => 'gov_admin', 'name' => 'Government admin (full module)'],
        ['id' => 'control_government', 'name' => 'Full access (all below)'],
        ['id' => 'view_control_government', 'name' => 'View Government Control'],
        ['id' => 'manage_control_government', 'name' => 'Manage inspections / violations / lists']
    ]],
    ['id' => 'control_admins', 'name' => 'Control Admins', 'count' => 5, 'permissions' => [
        ['id' => 'control_admins', 'name' => 'Full Access (all below)'],
        ['id' => 'view_control_admins', 'name' => 'View Admins'],
        ['id' => 'add_control_admin', 'name' => 'Add Admin'],
        ['id' => 'edit_control_admin', 'name' => 'Edit Admin'],
        ['id' => 'delete_control_admin', 'name' => 'Delete Admin']
    ]],
    ['id' => 'control_system', 'name' => 'System Settings', 'count' => 5, 'permissions' => [
        ['id' => 'control_system_settings', 'name' => 'Full Access (all below)'],
        ['id' => 'view_control_system_settings', 'name' => 'View Settings'],
        ['id' => 'edit_control_system_settings', 'name' => 'Edit Settings'],
        ['id' => 'manage_control_users', 'name' => 'Manage Users'],
        ['id' => 'manage_control_roles', 'name' => 'Manage Roles']
    ]]
];

$chk = @$ctrl->query("SHOW TABLES LIKE 'control_countries'");
if ($chk && $chk->num_rows > 0) {
    $res = @$ctrl->query("SELECT id, name, slug FROM control_countries WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
    if ($res) {
        $countryPerms = [];
        while ($r = $res->fetch_assoc()) {
            $countryPerms[] = ['id' => 'country_' . $r['slug'], 'name' => $r['name']];
        }
        if (!empty($countryPerms)) {
            array_splice($permissionGroups, 1, 0, [['id' => 'control_country_access', 'name' => 'Country Access', 'count' => count($countryPerms), 'permissions' => $countryPerms]]);
        }
    }
}

$userPermissions = [];
if ($userId > 0) {
    $chk = $ctrl->query("SHOW TABLES LIKE 'control_admin_permissions'");
    if ($chk && $chk->num_rows > 0) {
        $stmt = $ctrl->prepare("SELECT permissions FROM control_admin_permissions WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if ($row && isset($row['permissions']) && $row['permissions'] !== null && $row['permissions'] !== '') {
            $dec = json_decode($row['permissions'], true);
            $userPermissions = is_array($dec) ? $dec : [];
        } else {
            $userPermissions = ['*'];
        }
    } else {
        $userPermissions = ['*'];
    }
}

$permissionsToCheck = $userPermissions;
foreach ($permissionGroups as &$group) {
    foreach ($group['permissions'] as &$perm) {
        $perm['granted'] = controlPanelPermissionGrantedInList($perm['id'], $permissionsToCheck);
    }
}
unset($group, $perm);

$response = ['success' => true, 'groups' => $permissionGroups, 'source' => 'control'];
if ($userId) {
    $response['user_permissions'] = $userPermissions;
}
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
