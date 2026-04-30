<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/api/control/admins-api.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/api/control/admins-api.php`.
 */
/**
 * Control Panel API: Control Admins (control_admins table)
 * Manage who can log into the control panel.
 */
ini_set('display_errors', 0);
error_reporting(0);
ob_start();
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../includes/control-api-same-origin-cors.php';
applyControlApiSameOriginCors();
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

define('IS_CONTROL_PANEL', true);
require_once __DIR__ . '/../../includes/config.php';
// config.php sets MYSQLI_REPORT_STRICT — failed prepare/execute throw mysqli_sql_exception (HTTP 500, empty body).
mysqli_report(MYSQLI_REPORT_OFF);

function jsonOut($data) {
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data);
    exit;
}

try {

if (empty($_SESSION['control_logged_in'])) {
    jsonOut(['success' => false, 'message' => 'Unauthorized']);
}
require_once __DIR__ . '/../../includes/control-permissions.php';
if (!hasControlPermission(CONTROL_PERM_ADMINS) && !hasControlPermission('view_control_admins')) {
    jsonOut(['success' => false, 'message' => 'Access denied']);
}

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl) {
    jsonOut(['success' => false, 'message' => 'Database unavailable']);
}

$chk = $ctrl->query("SHOW TABLES LIKE 'control_admins'");
if (!$chk || $chk->num_rows === 0) {
    jsonOut(['success' => false, 'message' => 'control_admins table not found. Run migration SQL.']);
}

$hasAdminCountryCol = false;
$ccAdm = $ctrl->query("SHOW COLUMNS FROM control_admins LIKE 'country_id'");
if ($ccAdm && $ccAdm->num_rows > 0) {
    $hasAdminCountryCol = true;
}

$scopeIds = getControlPanelCountryScopeIds($ctrl);

$method = $_SERVER['REQUEST_METHOD'];
$rawInput = json_decode(file_get_contents('php://input'), true) ?: $_POST;

// POST action=update (same as PUT — avoids hosts that strip or mishandle PUT / php://input)
if ($method === 'POST' && (($rawInput['action'] ?? '') === 'update')) {
    if (!hasControlPermission(CONTROL_PERM_ADMINS) && !hasControlPermission('edit_control_admin')) {
        jsonOut(['success' => false, 'message' => 'Access denied']);
    }
    $input = $rawInput;
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) {
        jsonOut(['success' => false, 'message' => 'Invalid ID']);
    }

    $username = trim((string)($input['username'] ?? ''));
    if ($username === '') {
        jsonOut(['success' => false, 'message' => 'Username is required']);
    }

    $password = (string)($input['password'] ?? '');
    $fullName = trim((string)($input['full_name'] ?? ''));
    $isActive = isset($input['is_active']) ? (int)(bool)$input['is_active'] : 1;

    $countryUpdate = $hasAdminCountryCol && array_key_exists('country_id', $input);
    $countryIdVal = null;
    if ($countryUpdate) {
        $rawC = $input['country_id'];
        if ($rawC !== null && $rawC !== '' && (int) $rawC > 0) {
            $countryIdVal = (int) $rawC;
        }
    }

    if ($hasAdminCountryCol && $scopeIds !== null) {
        $chkAdm = $ctrl->query('SELECT country_id FROM control_admins WHERE id = ' . (int) $id . ' LIMIT 1');
        $admRow = $chkAdm ? $chkAdm->fetch_assoc() : null;
        $curCid = (int) ($admRow['country_id'] ?? 0);
        if ($scopeIds === [] || (!empty($scopeIds) && $curCid > 0 && !in_array($curCid, $scopeIds, true))) {
            jsonOut(['success' => false, 'message' => 'Access denied']);
        }
    }
    if ($hasAdminCountryCol && $countryIdVal !== null && !controlPanelAdminCountryIdAllowed($ctrl, $countryIdVal)) {
        jsonOut(['success' => false, 'message' => 'Country not allowed for your account']);
    }

    if ($password !== '') {
        if (strlen($password) < 4) {
            jsonOut(['success' => false, 'message' => 'Password must be at least 4 characters']);
        }
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        if ($hasAdminCountryCol && $countryUpdate) {
            if ($countryIdVal === null) {
                $stmt = $ctrl->prepare('UPDATE control_admins SET username=?, password=?, full_name=?, is_active=?, country_id=NULL WHERE id=?');
                if (!$stmt) {
                    jsonOut(['success' => false, 'message' => 'Database error: ' . $ctrl->error]);
                }
                $stmt->bind_param('sssii', $username, $hashed, $fullName, $isActive, $id);
            } else {
                $stmt = $ctrl->prepare('UPDATE control_admins SET username=?, password=?, full_name=?, is_active=?, country_id=? WHERE id=?');
                if (!$stmt) {
                    jsonOut(['success' => false, 'message' => 'Database error: ' . $ctrl->error]);
                }
                $stmt->bind_param('sssiii', $username, $hashed, $fullName, $isActive, $countryIdVal, $id);
            }
        } else {
            $stmt = $ctrl->prepare('UPDATE control_admins SET username=?, password=?, full_name=?, is_active=? WHERE id=?');
            if (!$stmt) {
                jsonOut(['success' => false, 'message' => 'Database error: ' . $ctrl->error]);
            }
            $stmt->bind_param('sssii', $username, $hashed, $fullName, $isActive, $id);
        }
    } else {
        if ($hasAdminCountryCol && $countryUpdate) {
            if ($countryIdVal === null) {
                $stmt = $ctrl->prepare('UPDATE control_admins SET username=?, full_name=?, is_active=?, country_id=NULL WHERE id=?');
                if (!$stmt) {
                    jsonOut(['success' => false, 'message' => 'Database error: ' . $ctrl->error]);
                }
                $stmt->bind_param('ssii', $username, $fullName, $isActive, $id);
            } else {
                $stmt = $ctrl->prepare('UPDATE control_admins SET username=?, full_name=?, is_active=?, country_id=? WHERE id=?');
                if (!$stmt) {
                    jsonOut(['success' => false, 'message' => 'Database error: ' . $ctrl->error]);
                }
                $stmt->bind_param('ssiii', $username, $fullName, $isActive, $countryIdVal, $id);
            }
        } else {
            $stmt = $ctrl->prepare('UPDATE control_admins SET username=?, full_name=?, is_active=? WHERE id=?');
            if (!$stmt) {
                jsonOut(['success' => false, 'message' => 'Database error: ' . $ctrl->error]);
            }
            $stmt->bind_param('ssii', $username, $fullName, $isActive, $id);
        }
    }
    if ($stmt->execute()) {
        jsonOut(['success' => true, 'message' => 'Updated']);
    }
    $dup = (int)($stmt->errno ?: $ctrl->errno);
    if ($dup === 1062) {
        jsonOut(['success' => false, 'message' => 'Username already exists']);
    }
    jsonOut(['success' => false, 'message' => ($stmt->error ?: $ctrl->error) ?: 'Update failed']);
}

// POST fallback delete (for hosts/proxies that block DELETE bodies)
if ($method === 'POST' && (($rawInput['action'] ?? '') === 'delete')) {
    if (!hasControlPermission(CONTROL_PERM_ADMINS) && !hasControlPermission('delete_control_admin')) {
        jsonOut(['success' => false, 'message' => 'Access denied']);
    }
    $ids = $rawInput['ids'] ?? [];
    if (empty($ids) && !empty($_GET['ids'])) {
        $ids = explode(',', (string)$_GET['ids']);
    }
    if (empty($ids)) jsonOut(['success' => false, 'message' => 'No IDs']);

    $ids = array_filter(array_map('intval', $ids));
    if (empty($ids)) jsonOut(['success' => false, 'message' => 'No valid IDs']);

    if ($hasAdminCountryCol && $scopeIds !== null) {
        if ($scopeIds === []) {
            jsonOut(['success' => false, 'message' => 'Access denied']);
        }
        $inList = implode(',', array_map('intval', $ids));
        $scopeIn = implode(',', array_map('intval', $scopeIds));
        $resF = $ctrl->query("SELECT id FROM control_admins WHERE id IN ($inList) AND country_id IN ($scopeIn)");
        $ids = [];
        if ($resF) {
            while ($rw = $resF->fetch_assoc()) {
                $ids[] = (int) ($rw['id'] ?? 0);
            }
        }
        if ($ids === []) {
            jsonOut(['success' => false, 'message' => 'Access denied']);
        }
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $stmt = $ctrl->prepare("DELETE FROM control_admins WHERE id IN ($placeholders)");
    if (!$stmt) {
        jsonOut(['success' => false, 'message' => 'Database error: ' . $ctrl->error]);
    }
    $stmt->bind_param($types, ...$ids);
    if ($stmt->execute()) {
        jsonOut(['success' => true, 'deleted' => $stmt->affected_rows, 'message' => 'Deleted']);
    }
    jsonOut(['success' => false, 'message' => $ctrl->error]);
}

// GET - list with pagination (use query + fetch loop — no mysqli_stmt::get_result / fetch_all; those need mysqlnd and fatals without it)
if ($method === 'GET') {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));
    $search = trim($_GET['search'] ?? '');
    $offset = ($page - 1) * $limit;

    $whereParts = [];
    if ($search !== '') {
        $esc = $ctrl->real_escape_string($search);
        $whereParts[] = $hasAdminCountryCol
            ? "(a.username LIKE '%{$esc}%' OR a.full_name LIKE '%{$esc}%')"
            : "(username LIKE '%{$esc}%' OR full_name LIKE '%{$esc}%')";
    }
    if ($hasAdminCountryCol && $scopeIds !== null) {
        if ($scopeIds === []) {
            $whereParts[] = '1=0';
        } else {
            $whereParts[] = 'a.country_id IN (' . implode(',', array_map('intval', $scopeIds)) . ')';
        }
    }
    $where = $whereParts ? ' WHERE ' . implode(' AND ', $whereParts) : '';

    $countSql = $hasAdminCountryCol
        ? 'SELECT COUNT(*) as total FROM control_admins a' . $where
        : 'SELECT COUNT(*) as total FROM control_admins' . $where;
    $res = $ctrl->query($countSql);
    if (!$res) {
        jsonOut(['success' => false, 'message' => 'Database error: ' . $ctrl->error]);
    }
    $total = (int)($res->fetch_assoc()['total'] ?? 0);

    $sql = $hasAdminCountryCol
        ? 'SELECT a.id, a.username, a.full_name, a.is_active, a.created_at, a.updated_at, a.country_id, c.name AS country_name FROM control_admins a LEFT JOIN control_countries c ON c.id = a.country_id'
            . $where . ' ORDER BY a.id DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset
        : 'SELECT id, username, full_name, is_active, created_at, updated_at FROM control_admins' . $where
            . ' ORDER BY id DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;
    $res2 = $ctrl->query($sql);
    if (!$res2) {
        jsonOut(['success' => false, 'message' => 'Database error: ' . $ctrl->error]);
    }
    $rows = [];
    while ($row = $res2->fetch_assoc()) {
        $rows[] = $row;
    }

    jsonOut([
        'success' => true,
        'list' => $rows,
        'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'pages' => (int)ceil($total / max(1, $limit))],
    ]);
}

// POST - create (requires add)
if ($method === 'POST') {
    if (!hasControlPermission(CONTROL_PERM_ADMINS) && !hasControlPermission('add_control_admin')) {
        jsonOut(['success' => false, 'message' => 'Access denied']);
    }
    $input = $rawInput;
    $username = trim((string)($input['username'] ?? ''));
    $password = (string)($input['password'] ?? '');
    $fullName = trim((string)($input['full_name'] ?? ''));
    $isActive = isset($input['is_active']) ? (int)(bool)$input['is_active'] : 1;

    if ($username === '') {
        jsonOut(['success' => false, 'message' => 'Username is required']);
    }
    if (strlen($password) < 4) {
        jsonOut(['success' => false, 'message' => 'Password must be at least 4 characters']);
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $newCountryId = null;
    if ($hasAdminCountryCol && array_key_exists('country_id', $input)) {
        $rawC = $input['country_id'];
        if ($rawC !== null && $rawC !== '' && (int) $rawC > 0) {
            $newCountryId = (int) $rawC;
        }
    }
    if ($hasAdminCountryCol && $newCountryId !== null && !controlPanelAdminCountryIdAllowed($ctrl, $newCountryId)) {
        jsonOut(['success' => false, 'message' => 'Country not allowed for your account']);
    }
    if ($hasAdminCountryCol) {
        if ($newCountryId !== null) {
            $stmt = $ctrl->prepare('INSERT INTO control_admins (username, password, full_name, is_active, country_id) VALUES (?, ?, ?, ?, ?)');
            if (!$stmt) {
                jsonOut(['success' => false, 'message' => 'Database error: ' . $ctrl->error]);
            }
            $stmt->bind_param('sssii', $username, $hashed, $fullName, $isActive, $newCountryId);
        } else {
            $stmt = $ctrl->prepare('INSERT INTO control_admins (username, password, full_name, is_active, country_id) VALUES (?, ?, ?, ?, NULL)');
            if (!$stmt) {
                jsonOut(['success' => false, 'message' => 'Database error: ' . $ctrl->error]);
            }
            $stmt->bind_param('sssi', $username, $hashed, $fullName, $isActive);
        }
    } else {
        $stmt = $ctrl->prepare('INSERT INTO control_admins (username, password, full_name, is_active) VALUES (?, ?, ?, ?)');
        if (!$stmt) {
            jsonOut(['success' => false, 'message' => 'Database error: ' . $ctrl->error]);
        }
        $stmt->bind_param('sssi', $username, $hashed, $fullName, $isActive);
    }
    if ($stmt->execute()) {
        jsonOut(['success' => true, 'id' => (int)$ctrl->insert_id, 'message' => 'Admin created']);
    }
    if ($ctrl->errno === 1062) {
        jsonOut(['success' => false, 'message' => 'Username already exists']);
    }
    jsonOut(['success' => false, 'message' => $ctrl->error]);
}

// PUT - update (requires edit)
if ($method === 'PUT') {
    if (!hasControlPermission(CONTROL_PERM_ADMINS) && !hasControlPermission('edit_control_admin')) {
        jsonOut(['success' => false, 'message' => 'Access denied']);
    }
    $input = $rawInput;
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) jsonOut(['success' => false, 'message' => 'Invalid ID']);

    $username = trim((string)($input['username'] ?? ''));
    if ($username === '') jsonOut(['success' => false, 'message' => 'Username is required']);

    $password = (string)($input['password'] ?? '');
    $fullName = trim((string)($input['full_name'] ?? ''));
    $isActive = isset($input['is_active']) ? (int)(bool)$input['is_active'] : 1;

    $countryUpdate = $hasAdminCountryCol && array_key_exists('country_id', $input);
    $countryIdVal = null;
    if ($countryUpdate) {
        $rawC = $input['country_id'];
        if ($rawC !== null && $rawC !== '' && (int) $rawC > 0) {
            $countryIdVal = (int) $rawC;
        }
    }

    if ($hasAdminCountryCol && $scopeIds !== null) {
        $chkAdm = $ctrl->query('SELECT country_id FROM control_admins WHERE id = ' . (int) $id . ' LIMIT 1');
        $admRow = $chkAdm ? $chkAdm->fetch_assoc() : null;
        $curCid = (int) ($admRow['country_id'] ?? 0);
        if ($scopeIds === [] || (!empty($scopeIds) && $curCid > 0 && !in_array($curCid, $scopeIds, true))) {
            jsonOut(['success' => false, 'message' => 'Access denied']);
        }
    }
    if ($hasAdminCountryCol && $countryIdVal !== null && !controlPanelAdminCountryIdAllowed($ctrl, $countryIdVal)) {
        jsonOut(['success' => false, 'message' => 'Country not allowed for your account']);
    }

    if ($password !== '') {
        if (strlen($password) < 4) {
            jsonOut(['success' => false, 'message' => 'Password must be at least 4 characters']);
        }
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        if ($hasAdminCountryCol && $countryUpdate) {
            if ($countryIdVal === null) {
                $stmt = $ctrl->prepare('UPDATE control_admins SET username=?, password=?, full_name=?, is_active=?, country_id=NULL WHERE id=?');
                if (!$stmt) {
                    jsonOut(['success' => false, 'message' => 'Database error: ' . $ctrl->error]);
                }
                $stmt->bind_param('sssii', $username, $hashed, $fullName, $isActive, $id);
            } else {
                $stmt = $ctrl->prepare('UPDATE control_admins SET username=?, password=?, full_name=?, is_active=?, country_id=? WHERE id=?');
                if (!$stmt) {
                    jsonOut(['success' => false, 'message' => 'Database error: ' . $ctrl->error]);
                }
                $stmt->bind_param('sssiii', $username, $hashed, $fullName, $isActive, $countryIdVal, $id);
            }
        } else {
            $stmt = $ctrl->prepare('UPDATE control_admins SET username=?, password=?, full_name=?, is_active=? WHERE id=?');
            if (!$stmt) {
                jsonOut(['success' => false, 'message' => 'Database error: ' . $ctrl->error]);
            }
            $stmt->bind_param('sssii', $username, $hashed, $fullName, $isActive, $id);
        }
    } else {
        if ($hasAdminCountryCol && $countryUpdate) {
            if ($countryIdVal === null) {
                $stmt = $ctrl->prepare('UPDATE control_admins SET username=?, full_name=?, is_active=?, country_id=NULL WHERE id=?');
                if (!$stmt) {
                    jsonOut(['success' => false, 'message' => 'Database error: ' . $ctrl->error]);
                }
                $stmt->bind_param('ssii', $username, $fullName, $isActive, $id);
            } else {
                $stmt = $ctrl->prepare('UPDATE control_admins SET username=?, full_name=?, is_active=?, country_id=? WHERE id=?');
                if (!$stmt) {
                    jsonOut(['success' => false, 'message' => 'Database error: ' . $ctrl->error]);
                }
                $stmt->bind_param('ssiii', $username, $fullName, $isActive, $countryIdVal, $id);
            }
        } else {
            $stmt = $ctrl->prepare('UPDATE control_admins SET username=?, full_name=?, is_active=? WHERE id=?');
            if (!$stmt) {
                jsonOut(['success' => false, 'message' => 'Database error: ' . $ctrl->error]);
            }
            $stmt->bind_param('ssii', $username, $fullName, $isActive, $id);
        }
    }
    if ($stmt->execute()) {
        jsonOut(['success' => true, 'message' => 'Updated']);
    }
    $dup = (int)($stmt->errno ?: $ctrl->errno);
    if ($dup === 1062) {
        jsonOut(['success' => false, 'message' => 'Username already exists']);
    }
    jsonOut(['success' => false, 'message' => ($stmt->error ?: $ctrl->error) ?: 'Update failed']);
}

// DELETE - single or bulk (requires delete)
if ($method === 'DELETE') {
    if (!hasControlPermission(CONTROL_PERM_ADMINS) && !hasControlPermission('delete_control_admin')) {
        jsonOut(['success' => false, 'message' => 'Access denied']);
    }
    $input = $rawInput;
    $ids = $input['ids'] ?? [];
    if (empty($ids) && !empty($_GET['ids'])) {
        $ids = explode(',', (string)$_GET['ids']);
    }
    if (empty($ids)) {
        $id = (int)($_GET['id'] ?? 0);
        if ($id > 0) $ids = [$id];
    }
    if (empty($ids)) jsonOut(['success' => false, 'message' => 'No IDs']);

    $ids = array_filter(array_map('intval', $ids));
    if (empty($ids)) jsonOut(['success' => false, 'message' => 'No valid IDs']);

    if ($hasAdminCountryCol && $scopeIds !== null) {
        if ($scopeIds === []) {
            jsonOut(['success' => false, 'message' => 'Access denied']);
        }
        $inList = implode(',', array_map('intval', $ids));
        $scopeIn = implode(',', array_map('intval', $scopeIds));
        $resF = $ctrl->query("SELECT id FROM control_admins WHERE id IN ($inList) AND country_id IN ($scopeIn)");
        $ids = [];
        if ($resF) {
            while ($rw = $resF->fetch_assoc()) {
                $ids[] = (int) ($rw['id'] ?? 0);
            }
        }
        if ($ids === []) {
            jsonOut(['success' => false, 'message' => 'Access denied']);
        }
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $stmt = $ctrl->prepare("DELETE FROM control_admins WHERE id IN ($placeholders)");
    if (!$stmt) {
        jsonOut(['success' => false, 'message' => 'Database error: ' . $ctrl->error]);
    }
    $stmt->bind_param($types, ...$ids);
    if ($stmt->execute()) {
        jsonOut(['success' => true, 'deleted' => $stmt->affected_rows, 'message' => 'Deleted']);
    }
    jsonOut(['success' => false, 'message' => $ctrl->error]);
}

jsonOut(['success' => false, 'message' => 'Method not allowed']);

} catch (Throwable $e) {
    error_log('admins-api: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    $msg = 'Server error. Check control-panel/logs/php-errors.log.';
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        $msg = $e->getMessage();
    }
    jsonOut(['success' => false, 'message' => $msg]);
}
