<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/api/control/countries.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/api/control/countries.php`.
 */
/**
 * Control Panel API: Countries (control_countries table)
 * Requires control panel session.
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

require_once __DIR__ . '/../../includes/config.php';

function jsonOut($data) {
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data);
    exit;
}

// Require control panel login
$isControl = defined('IS_CONTROL_PANEL') && IS_CONTROL_PANEL;
if (!$isControl || empty($_SESSION['control_logged_in'])) {
    jsonOut(['success' => false, 'message' => 'Unauthorized']);
}
require_once __DIR__ . '/../../includes/control-permissions.php';
// Base access: view or full
if (!hasControlPermission(CONTROL_PERM_COUNTRIES) && !hasControlPermission('view_control_countries')) {
    jsonOut(['success' => false, 'message' => 'Access denied']);
}

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl) {
    jsonOut(['success' => false, 'message' => 'Database unavailable']);
}

// Check table exists
$chk = $ctrl->query("SHOW TABLES LIKE 'control_countries'");
if (!$chk || $chk->num_rows === 0) {
    jsonOut(['success' => false, 'message' => 'control_countries table not found. Run migration SQL.']);
}

$allowedCountryIds = getControlPanelCountryScopeIds($ctrl);

$method = $_SERVER['REQUEST_METHOD'];

// GET - list (requires view)
if ($method === 'GET') {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));
    $search = trim($_GET['search'] ?? '');
    $offset = ($page - 1) * $limit;

    $where = [];
    $params = [];
    $types = '';
    if ($allowedCountryIds !== null && !empty($allowedCountryIds)) {
        $ph = implode(',', array_fill(0, count($allowedCountryIds), '?'));
        $where[] = "id IN ($ph)";
        $params = array_merge($params, $allowedCountryIds);
        $types .= str_repeat('i', count($allowedCountryIds));
    } elseif ($allowedCountryIds === []) {
        jsonOut(['success' => true, 'list' => [], 'pagination' => ['page' => 1, 'limit' => $limit, 'total' => 0, 'pages' => 0]]);
    }
    if ($search !== '') {
        $where[] = '(name LIKE ? OR slug LIKE ?)';
        $p = '%' . $ctrl->real_escape_string($search) . '%';
        $params = array_merge($params, [$p, $p]);
        $types .= 'ss';
    }
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countSql = "SELECT COUNT(*) as total FROM control_countries $whereClause";
    if ($params) {
        $stmt = $ctrl->prepare($countSql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $ctrl->query($countSql);
    }
    $total = (int)($res->fetch_assoc()['total'] ?? 0);

    $sql = "SELECT * FROM control_countries $whereClause ORDER BY id DESC LIMIT $limit OFFSET $offset";
    if ($params) {
        $stmt = $ctrl->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $rows = $ctrl->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    jsonOut([
        'success' => true,
        'list' => $rows,
        'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'pages' => (int)ceil($total / $limit)]
    ]);
}

// POST - create (requires add)
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $action = trim((string)($input['action'] ?? ''));
    if ($action !== '') {
        $ids = $input['ids'] ?? [];
        $ids = is_array($ids) ? array_values(array_unique(array_map('intval', $ids))) : [];
        $ids = array_values(array_filter($ids, function ($id) { return $id > 0; }));
        if (empty($ids)) {
            jsonOut(['success' => false, 'message' => 'No country IDs selected']);
        }
        if ($allowedCountryIds !== null) {
            foreach ($ids as $cid) {
                if (!in_array($cid, $allowedCountryIds, true)) {
                    jsonOut(['success' => false, 'message' => 'You do not have permission for one or more selected countries']);
                }
            }
        }

        if ($action === 'bulk_activate' || $action === 'bulk_inactivate') {
            if (!hasControlPermission(CONTROL_PERM_COUNTRIES) && !hasControlPermission('edit_control_country')) {
                jsonOut(['success' => false, 'message' => 'Access denied']);
            }
            $isActive = ($action === 'bulk_activate') ? 1 : 0;
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $ctrl->prepare("UPDATE control_countries SET is_active = ? WHERE id IN ($placeholders)");
            $types = 'i' . str_repeat('i', count($ids));
            $bind = array_merge([$isActive], $ids);
            $stmt->bind_param($types, ...$bind);
            if ($stmt->execute()) {
                jsonOut([
                    'success' => true,
                    'message' => ($isActive ? 'Countries activated' : 'Countries inactivated'),
                    'summary' => [
                        'action' => $action,
                        'total' => count($ids),
                        'updated' => (int)$stmt->affected_rows
                    ]
                ]);
            }
            jsonOut(['success' => false, 'message' => $ctrl->error]);
        }

        if ($action === 'bulk_delete') {
            if (!hasControlPermission(CONTROL_PERM_COUNTRIES) && !hasControlPermission('delete_control_country')) {
                jsonOut(['success' => false, 'message' => 'Access denied']);
            }
            $confirm = strtoupper(trim((string)($input['confirm'] ?? '')));
            if ($confirm !== 'DELETE') {
                jsonOut(['success' => false, 'message' => 'Bulk delete blocked. Type DELETE to confirm.']);
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $ctrl->prepare("DELETE FROM control_countries WHERE id IN ($placeholders)");
            $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
            if ($stmt->execute()) {
                jsonOut([
                    'success' => true,
                    'message' => 'Countries deleted',
                    'summary' => [
                        'action' => $action,
                        'total' => count($ids),
                        'deleted' => (int)$stmt->affected_rows
                    ]
                ]);
            }
            jsonOut(['success' => false, 'message' => $ctrl->error]);
        }

        jsonOut(['success' => false, 'message' => 'Unsupported bulk action']);
    }

    if (!hasControlPermission(CONTROL_PERM_COUNTRIES) && !hasControlPermission('add_control_country')) {
        jsonOut(['success' => false, 'message' => 'Access denied']);
    }
    if ($allowedCountryIds !== null) {
        jsonOut(['success' => false, 'message' => 'You can only manage agencies in your assigned countries']);
    }
    $name = trim((string)($input['name'] ?? ''));
    $slug = trim((string)($input['slug'] ?? ''));
    $slug = $slug === '' ? strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name)) : strtolower(preg_replace('/[^a-z0-9]+/i', '-', $slug));
    $sortOrder = (int)($input['sort_order'] ?? 0);
    $isActive = isset($input['is_active']) ? (int)(bool)$input['is_active'] : 1;

    if ($name === '') {
        jsonOut(['success' => false, 'message' => 'Name is required']);
    }

    $stmt = $ctrl->prepare("INSERT INTO control_countries (name, slug, is_active, sort_order) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssii", $name, $slug, $isActive, $sortOrder);
    if ($stmt->execute()) {
        jsonOut(['success' => true, 'id' => (int)$ctrl->insert_id, 'message' => 'Country created']);
    }
    jsonOut(['success' => false, 'message' => $ctrl->error]);
}

// PUT - update (requires edit)
if ($method === 'PUT') {
    if (!hasControlPermission(CONTROL_PERM_COUNTRIES) && !hasControlPermission('edit_control_country')) {
        jsonOut(['success' => false, 'message' => 'Access denied']);
    }
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) jsonOut(['success' => false, 'message' => 'Invalid ID']);
    if ($allowedCountryIds !== null && !in_array($id, $allowedCountryIds, true)) {
        jsonOut(['success' => false, 'message' => 'You do not have permission to edit this country']);
    }

    $name = trim((string)($input['name'] ?? ''));
    if ($name === '') jsonOut(['success' => false, 'message' => 'Name is required']);

    $slug = trim((string)($input['slug'] ?? ''));
    if ($slug === '') {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
        if ($slug === '') {
            $row = $ctrl->query("SELECT slug FROM control_countries WHERE id = $id")->fetch_assoc();
            $slug = $row['slug'] ?? 'country-' . $id;
        }
    }
    $sortOrder = (int)($input['sort_order'] ?? 0);
    $isActive = isset($input['is_active']) ? (int)(bool)$input['is_active'] : 1;

    $stmt = $ctrl->prepare("UPDATE control_countries SET name=?, slug=?, is_active=?, sort_order=? WHERE id=?");
    $stmt->bind_param("ssiii", $name, $slug, $isActive, $sortOrder, $id);
    if ($stmt->execute()) {
        jsonOut(['success' => true, 'message' => 'Updated']);
    }
    jsonOut(['success' => false, 'message' => $ctrl->error]);
}

// DELETE - single or bulk (requires delete)
if ($method === 'DELETE') {
    if (!hasControlPermission(CONTROL_PERM_COUNTRIES) && !hasControlPermission('delete_control_country')) {
        jsonOut(['success' => false, 'message' => 'Access denied']);
    }
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $ids = $input['ids'] ?? [];
    if (empty($ids)) {
        $id = (int)($_GET['id'] ?? 0);
        if ($id > 0) $ids = [$id];
    }
    if (empty($ids)) jsonOut(['success' => false, 'message' => 'No IDs']);

    $ids = array_map('intval', $ids);
    if ($allowedCountryIds !== null) {
        foreach ($ids as $cid) {
            if (!in_array($cid, $allowedCountryIds, true)) {
                jsonOut(['success' => false, 'message' => 'You do not have permission to delete one or more of these countries']);
            }
        }
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $ctrl->prepare("DELETE FROM control_countries WHERE id IN ($placeholders)");
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    if ($stmt->execute()) {
        jsonOut(['success' => true, 'deleted' => $stmt->affected_rows]);
    }
    jsonOut(['success' => false, 'message' => $ctrl->error]);
}

jsonOut(['success' => false, 'message' => 'Method not allowed']);
