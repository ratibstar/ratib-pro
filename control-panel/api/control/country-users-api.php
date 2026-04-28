<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/api/control/country-users-api.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/api/control/country-users-api.php`.
 */
/**
 * Control Panel API: Manage users for a specific country/agency.
 * Accepts agency_id to connect to that country's DB.
 */
ob_start();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control-permissions.php';
require_once __DIR__ . '/agency-db-helper.php';
ob_clean();

if (empty($_SESSION['control_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
if (!hasControlPermission(CONTROL_PERM_COUNTRY_USERS)
    && !hasControlPermission('view_control_country_users')
    && !hasControlPermission('control_agencies')
    && !hasControlPermission('view_control_agencies')
    && !hasControlPermission('open_control_agency')) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl || !($ctrl instanceof mysqli)) {
    echo json_encode(['success' => false, 'message' => 'Control database unavailable']);
    exit;
}

try {
// Get agency_id from POST or GET
$input = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?: $_POST;
} else {
    $input = $_GET;
}
$agencyId = isset($input['agency_id']) ? (int)$input['agency_id'] : 0;
if ($agencyId <= 0) {
    echo json_encode(['success' => false, 'message' => 'agency_id required']);
    exit;
}

// Get agency DB credentials (join country slug so agency-db-helper can fall back to outratib_{slug} when db_name is wrong)
$stmt = $ctrl->prepare(
    "SELECT a.db_host, a.db_port, a.db_user, a.db_pass, a.db_name, a.country_id, c.slug AS country_slug "
    . "FROM control_agencies a LEFT JOIN control_countries c ON c.id = a.country_id "
    . "WHERE a.id = ? AND a.is_active = 1 LIMIT 1"
);
$stmt->bind_param("i", $agencyId);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Agency not found']);
    exit;
}
$agency = $res->fetch_assoc();
$stmt->close();

// Check country permission
$allowedIds = getAllowedCountryIds($ctrl);
if ($allowedIds !== null && !in_array((int)$agency['country_id'], $allowedIds, true)) {
    echo json_encode(['success' => false, 'message' => 'Access denied to this country']);
    exit;
}

// Connect to agency DB (shared logic with get-users-per-country)
$countryId = (int)($agency['country_id'] ?? 0);
$result = getAgencyDbConnection($agency, $countryId);
if (!$result) {
    $detail = function_exists('getAgencyDbConnectionLastError') ? trim(getAgencyDbConnectionLastError()) : '';
    $configured = trim((string)($agency['db_name'] ?? ''));
    $msg = 'Failed to connect to this agency database.';
    if ($configured !== '') {
        $msg .= " Configured db_name: «{$configured}».";
    }
    if ($detail !== '') {
        $msg .= ' ' . $detail;
    }
    $msg .= ' Update control_agencies.db_name (and credentials) for this agency, or ensure a database such as outratib_{country_slug} exists.';
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}
$conn = $result['conn'];
$useCountryFilter = $result['use_country_filter'];

// Detect users table PK and columns (country DBs may use user_id or id, password or pass)
$userPkCol = 'user_id';
$usersHasStatus = true;
$usersHasCreatedAt = true;
$userPassCol = 'password';
$userCols = [];
$chkUsers = $conn->query("SHOW TABLES LIKE 'users'");
if ($chkUsers && $chkUsers->num_rows > 0) {
    $colRes = $conn->query("SHOW COLUMNS FROM users");
    if ($colRes) {
        while ($r = $colRes->fetch_assoc()) {
            $userCols[] = $r['Field'];
        }
    }
    $userPkCol = in_array('user_id', $userCols) ? 'user_id' : 'id';
    $usersHasStatus = in_array('status', $userCols);
    $usersHasCreatedAt = in_array('created_at', $userCols);
    $userPassCol = in_array('password', $userCols) ? 'password' : (in_array('pass', $userCols) ? 'pass' : 'password');
}

$action = $input['action'] ?? '';
$table = $input['table'] ?? 'users';
$canManageCountryUsers = hasControlPermission(CONTROL_PERM_SYSTEM_SETTINGS)
    || hasControlPermission('manage_control_users');

// Roles can be fetched for the add/edit form
if ($table === 'roles' && $action === 'get_all') {
    $chk = $conn->query("SHOW TABLES LIKE 'roles'");
    $data = [];
    if ($chk && $chk->num_rows > 0) {
        $cols = $conn->query("SHOW COLUMNS FROM roles");
        $hasRoleId = $hasRoleName = false;
        while ($c = $cols->fetch_assoc()) {
            if ($c['Field'] === 'role_id') $hasRoleId = true;
            if ($c['Field'] === 'role_name') $hasRoleName = true;
        }
        $idCol = $hasRoleId ? 'role_id' : 'id';
        $nameCol = $hasRoleName ? 'role_name' : 'name';
        $res = $conn->query("SELECT $idCol as role_id, $nameCol as role_name FROM roles ORDER BY 1");
        if ($res) while ($row = $res->fetch_assoc()) $data[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

if ($table !== 'users') {
    echo json_encode(['success' => false, 'message' => 'Only users table supported']);
    exit;
}

$writeActions = ['create', 'update', 'delete', 'bulk_update_status', 'bulk_delete'];
if (in_array($action, $writeActions, true) && !$canManageCountryUsers) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Handle actions
switch ($action) {
    case 'get_all':
        $chk = $conn->query("SHOW TABLES LIKE 'users'");
        if (!$chk || $chk->num_rows === 0) {
            echo json_encode(['success' => true, 'data' => []]);
            exit;
        }
        $chk = $conn->query("SHOW TABLES LIKE 'roles'");
        $joinRoles = ($chk && $chk->num_rows > 0);
        $sql = "SELECT u.{$userPkCol} AS user_id, u.username, u.role_id";
        if ($usersHasStatus) $sql .= ", u.status";
        if ($usersHasCreatedAt) $sql .= ", u.created_at";
        $chk = $conn->query("SHOW COLUMNS FROM users LIKE 'email'");
        if ($chk && $chk->num_rows > 0) $sql .= ", u.email";
        $chk = $conn->query("SHOW COLUMNS FROM users LIKE 'country_id'");
        if ($chk && $chk->num_rows > 0) $sql .= ", u.country_id";
        if ($joinRoles) $sql .= ", r.role_name";
        $uc = $userCols;
        if (empty($uc)) {
            $colRes2 = $conn->query("SHOW COLUMNS FROM users");
            if ($colRes2) {
                while ($r = $colRes2->fetch_assoc()) {
                    $uc[] = $r['Field'];
                }
            }
        }
        $dispParts = [];
        if (in_array('full_name', $uc, true)) {
            $dispParts[] = "NULLIF(TRIM(u.full_name), '')";
        }
        if (in_array('name', $uc, true)) {
            $dispParts[] = "NULLIF(TRIM(u.name), '')";
        }
        if (in_array('first_name', $uc, true) || in_array('last_name', $uc, true)) {
            $dispParts[] = "NULLIF(TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))), '')";
        }
        if (in_array('email', $uc, true)) {
            $dispParts[] = "NULLIF(TRIM(u.email), '')";
        }
        if (in_array('username', $uc, true)) {
            $dispParts[] = "NULLIF(TRIM(u.username), '')";
        }
        if (!empty($dispParts)) {
            $sql .= ', COALESCE(' . implode(', ', $dispParts) . ", '-') AS user_display_name";
        } else {
            $sql .= ", '-' AS user_display_name";
        }
        $sql .= " FROM users u";
        if ($joinRoles) $sql .= " LEFT JOIN roles r ON u.role_id = r.role_id";
        if ($useCountryFilter) {
            $chkCol = $conn->query("SHOW COLUMNS FROM users LIKE 'country_id'");
            if ($chkCol && $chkCol->num_rows > 0) $sql .= " WHERE u.country_id = $countryId";
        }
        $sql .= $usersHasCreatedAt ? " ORDER BY u.created_at DESC" : " ORDER BY u.{$userPkCol} DESC";
        $res = $conn->query($sql);
        $data = [];
        if ($res) while ($row = $res->fetch_assoc()) $data[] = $row;
        echo json_encode(['success' => true, 'data' => $data]);
        break;

    case 'get_stats':
        $total = 0;
        $active = 0;
        $chk = $conn->query("SHOW TABLES LIKE 'users'");
        if ($chk && $chk->num_rows > 0) {
            $where = "";
            if ($useCountryFilter) {
                $col = $conn->query("SHOW COLUMNS FROM users LIKE 'country_id'");
                if ($col && $col->num_rows > 0) $where = " WHERE country_id = $countryId";
            }
            $r = $conn->query("SELECT COUNT(*) as c FROM users" . $where);
            if ($r) $total = (int)($r->fetch_assoc()['c'] ?? 0);
            $col = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
            if ($col && $col->num_rows > 0) {
                $and = $where ? " AND " : " WHERE ";
                $r = $conn->query("SELECT COUNT(*) as c FROM users" . $where . $and . "LOWER(TRIM(status)) = 'active'");
                if ($r) $active = (int)($r->fetch_assoc()['c'] ?? 0);
            } else {
                $active = $total;
            }
        }
        echo json_encode(['success' => true, 'data' => ['total' => $total, 'active' => $active, 'inactive' => $total - $active]]);
        break;

    case 'get_by_id':
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid id']);
            exit;
        }
        $sel = "{$userPkCol} AS user_id, username, role_id";
        if ($usersHasStatus) $sel .= ", status";
        if ($usersHasCreatedAt) $sel .= ", created_at";
        $stmt = $conn->prepare("SELECT $sel FROM users WHERE {$userPkCol} = ? LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Record not found']);
            exit;
        }
        $chk = $conn->query("SHOW COLUMNS FROM users LIKE 'email'");
        if ($chk && $chk->num_rows > 0) {
            $r = $conn->query("SELECT email FROM users WHERE {$userPkCol} = $id LIMIT 1");
            if ($r && ($e = $r->fetch_assoc())) $row['email'] = $e['email'];
        }
        if (!$usersHasStatus) $row['status'] = 'active';
        echo json_encode(['success' => true, 'data' => $row]);
        break;

    case 'create':
        $data = $input['data'] ?? [];
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $roleId = (int)($data['role_id'] ?? 1);
        $status = trim($data['status'] ?? 'active');
        if (empty($username)) {
            echo json_encode(['success' => false, 'message' => 'Username required']);
            exit;
        }
        if (empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Password required']);
            exit;
        }
        $colRes = $conn->query("SHOW COLUMNS FROM users");
        $cols = [];
        if ($colRes) while ($r = $colRes->fetch_assoc()) $cols[] = $r['Field'];
        $idCol = in_array('user_id', $cols) ? 'user_id' : 'id';
        $dupSql = $useCountryFilter ? "SELECT $idCol FROM users WHERE username = ? AND country_id = $countryId LIMIT 1" : "SELECT $idCol FROM users WHERE username = ? LIMIT 1";
        $stmt = $conn->prepare($dupSql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $stmt->close();
            echo json_encode(['success' => false, 'message' => 'Username already exists']);
            exit;
        }
        $stmt->close();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $hasEmail = in_array('email', $cols);
        $hasCountryId = in_array('country_id', $cols);
        $agencyCountryId = (int)($agency['country_id'] ?? 0);
        $passCol = in_array('password', $cols) ? 'password' : (in_array('pass', $cols) ? 'pass' : 'password');
        $hasRoleId = in_array('role_id', $cols);
        $hasStatus = in_array('status', $cols);
        $insertCols = ['username', $passCol];
        $insertVals = [$username, $hash];
        $insertTypes = 'ss';
        if ($hasRoleId) { $insertCols[] = 'role_id'; $insertVals[] = $roleId; $insertTypes .= 'i'; }
        if ($hasStatus) { $insertCols[] = 'status'; $insertVals[] = $status; $insertTypes .= 's'; }
        if ($hasEmail) {
            $insertCols[] = 'email';
            $insertVals[] = $username . '@local';
            $insertTypes .= 's';
        }
        if ($hasCountryId && $agencyCountryId > 0) {
            $insertCols[] = 'country_id';
            $insertVals[] = $agencyCountryId;
            $insertTypes .= 'i';
        }
        $placeholders = implode(', ', array_fill(0, count($insertVals), '?'));
        $sql = "INSERT INTO users (" . implode(', ', $insertCols) . ") VALUES ($placeholders)";
        $stmt = $conn->prepare($sql);
        $refs = [$insertTypes];
        foreach ($insertVals as $k => $v) {
            $refs[] = &$insertVals[$k];
        }
        call_user_func_array([$stmt, 'bind_param'], $refs);
        if (!$stmt->execute()) {
            $stmt->close();
            $errMsg = (defined('DEBUG_MODE') && DEBUG_MODE) ? $conn->error : 'Create failed';
            echo json_encode(['success' => false, 'message' => $errMsg]);
            exit;
        }
        $uid = $conn->insert_id;
        $stmt->close();
        $r = $conn->query("SELECT $idCol as user_id, username, role_id, status, created_at FROM users WHERE $idCol = $uid LIMIT 1");
        $created = $r ? $r->fetch_assoc() : null;
        echo json_encode(['success' => true, 'created' => $created]);
        break;

    case 'update':
        $id = (int)($input['id'] ?? 0);
        $data = $input['data'] ?? [];
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid id']);
            exit;
        }
        $updates = [];
        $types = '';
        $params = [];
        if (isset($data['username'])) { $updates[] = 'username = ?'; $types .= 's'; $params[] = trim($data['username']); }
        if (isset($data['password']) && $data['password'] !== '') { $updates[] = "{$userPassCol} = ?"; $types .= 's'; $params[] = password_hash($data['password'], PASSWORD_DEFAULT); }
        if (isset($data['role_id'])) { $updates[] = 'role_id = ?'; $types .= 'i'; $params[] = (int)$data['role_id']; }
        if ($usersHasStatus && isset($data['status'])) { $updates[] = 'status = ?'; $types .= 's'; $params[] = trim($data['status']); }
        if (empty($updates)) {
            echo json_encode(['success' => false, 'message' => 'No fields to update']);
            exit;
        }
        $params[] = $id;
        $types .= 'i';
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE {$userPkCol} = ?";
        $stmt = $conn->prepare($sql);
        $refs = [$types];
        foreach ($params as $k => $v) {
            $refs[] = &$params[$k];
        }
        call_user_func_array([$stmt, 'bind_param'], $refs);
        if (!$stmt->execute()) {
            $stmt->close();
            $errMsg = (defined('DEBUG_MODE') && DEBUG_MODE) ? $conn->error : 'Update failed';
            echo json_encode(['success' => false, 'message' => $errMsg]);
            exit;
        }
        $stmt->close();
        $sel = "{$userPkCol} AS user_id, username, role_id";
        if ($usersHasStatus) $sel .= ", status";
        if ($usersHasCreatedAt) $sel .= ", created_at";
        $r = $conn->query("SELECT $sel FROM users WHERE {$userPkCol} = $id LIMIT 1");
        $updated = $r ? $r->fetch_assoc() : null;
        echo json_encode(['success' => true, 'updated' => $updated]);
        break;

    case 'delete':
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid id']);
            exit;
        }
        $stmt = $conn->prepare("DELETE FROM users WHERE {$userPkCol} = ?");
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            $stmt->close();
            echo json_encode(['success' => false, 'message' => 'Delete failed']);
            exit;
        }
        $stmt->close();
        echo json_encode(['success' => true]);
        break;

    case 'bulk_update_status':
        if (!$usersHasStatus) {
            echo json_encode(['success' => false, 'message' => 'Status column not available for this users table']);
            exit;
        }
        $ids = $input['ids'] ?? [];
        $status = trim($input['status'] ?? '');
        if (!is_array($ids) || empty($ids)) {
            echo json_encode(['success' => false, 'message' => 'No users selected']);
            exit;
        }
        $ids = array_values(array_filter(array_map('intval', $ids), function($v) { return $v > 0; }));
        if (empty($ids)) {
            echo json_encode(['success' => false, 'message' => 'No valid user ids']);
            exit;
        }
        if ($status === '') $status = 'active';
        $in = implode(',', $ids);
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE {$userPkCol} IN ($in)");
        $stmt->bind_param("s", $status);
        if (!$stmt->execute()) {
            $stmt->close();
            $errMsg = (defined('DEBUG_MODE') && DEBUG_MODE) ? $conn->error : 'Bulk update failed';
            echo json_encode(['success' => false, 'message' => $errMsg]);
            exit;
        }
        $stmt->close();
        echo json_encode(['success' => true]);
        break;

    case 'bulk_delete':
        $ids = $input['ids'] ?? [];
        if (!is_array($ids) || empty($ids)) {
            echo json_encode(['success' => false, 'message' => 'No users selected']);
            exit;
        }
        $ids = array_values(array_filter(array_map('intval', $ids), function($v) { return $v > 0; }));
        if (empty($ids)) {
            echo json_encode(['success' => false, 'message' => 'No valid user ids']);
            exit;
        }
        $in = implode(',', $ids);
        $sql = "DELETE FROM users WHERE {$userPkCol} IN ($in)";
        if (!$conn->query($sql)) {
            $errMsg = (defined('DEBUG_MODE') && DEBUG_MODE) ? $conn->error : 'Bulk delete failed';
            echo json_encode(['success' => false, 'message' => $errMsg]);
            exit;
        }
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

} catch (Throwable $e) {
    ob_clean();
    $errMsg = $e->getMessage() . ' (file: ' . basename($e->getFile()) . ':' . $e->getLine() . ')';
    error_log('Country users API error: ' . $errMsg);
    header('Content-Type: application/json; charset=UTF-8');
    $showDetail = (defined('DEBUG_MODE') && DEBUG_MODE) || !empty($_GET['debug']);
    echo json_encode(['success' => false, 'message' => $showDetail ? $errMsg : 'Server error. Please try again.']);
}
