<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/api/control/support-chats.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/api/control/support-chats.php`.
 */
/**
 * Control Panel API: Support chats - list, get messages, reply
 */
ini_set('display_errors', 0);
error_reporting(0);
ob_start();
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../includes/control-api-same-origin-cors.php';
applyControlApiSameOriginCors();
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

require_once __DIR__ . '/../../includes/config.php';
mysqli_report(MYSQLI_REPORT_OFF);

function jsonOut($data) {
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json; charset=UTF-8');
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        $json = '{"success":false,"message":"Server error"}';
    }
    echo $json;
    exit;
}

$isControl = defined('IS_CONTROL_PANEL') && IS_CONTROL_PANEL;
if (!$isControl || empty($_SESSION['control_logged_in'])) {
    jsonOut(['success' => false, 'message' => 'Unauthorized']);
}
require_once __DIR__ . '/../../includes/control-permissions.php';
if (!hasControlPermission(CONTROL_PERM_SUPPORT) && !hasControlPermission('view_control_support')) {
    jsonOut(['success' => false, 'message' => 'Access denied']);
}

$ctrl = $GLOBALS['control_conn'] ?? $GLOBALS['conn'] ?? null;
if (!$ctrl) jsonOut(['success' => false, 'message' => 'Database unavailable']);

$chk = $ctrl->query("SHOW TABLES LIKE 'control_support_chats'");
if (!$chk || $chk->num_rows === 0) jsonOut(['success' => false, 'message' => 'Support chats not configured']);
$chk2 = $ctrl->query("SHOW TABLES LIKE 'control_support_chat_messages'");
if (!$chk2 || $chk2->num_rows === 0) jsonOut(['success' => false, 'message' => 'Support chats not configured']);

$hasCountryCol = $ctrl->query("SHOW COLUMNS FROM control_support_chats LIKE 'country_id'")->num_rows > 0;
// Lists/chats follow session-pinned scope for country_* operators (see getControlPanelCountryScopeIds).
$allowedCountryIds = getControlPanelCountryScopeIds($ctrl);
$controlUsername = strtolower(trim((string)($_SESSION['control_username'] ?? '')));
$isAdminUser = ($controlUsername === 'admin');

// Non-admin users never get global visibility unless they have country slug scope rows or admin bypass above.
if (!$isAdminUser && $allowedCountryIds === null) {
    $allowedCountryIds = [];
}
$countryScopeMode = ($hasCountryCol && $allowedCountryIds !== null) ? 'restricted' : 'all';
$canViewUnscopedChats = ($countryScopeMode === 'all');

/**
 * Build SQL scope for non-admin country users.
 * Returns:
 * - null => no scope (admin/global)
 * - [' AND ...', bool $alwaysFalse] for scoped queries
 */
function supportCountryScopeSql($tableAlias, $hasCountryCol, $allowedCountryIds) {
    if (!$hasCountryCol || $allowedCountryIds === null) {
        return ['', false];
    }
    if (empty($allowedCountryIds)) {
        return [' AND 1=0', true];
    }
    $ids = implode(',', array_map('intval', $allowedCountryIds));
    return [" AND {$tableAlias}.country_id IN ({$ids})", false];
}

function supportNormalizeIds($input) {
    if (!is_array($input)) return [];
    $ids = array_filter(array_map('intval', $input), function($v) { return $v > 0; });
    return array_values(array_unique($ids));
}

$method = $_SERVER['REQUEST_METHOD'];

// GET - list chats or get messages for one chat
if ($method === 'GET') {
    $chatId = (int)($_GET['chat_id'] ?? 0);

    if ($chatId > 0) {
        // Get messages for one chat
        $scopeTuple = supportCountryScopeSql('c', $hasCountryCol, $allowedCountryIds);
        $scopeSql = $scopeTuple[0];
        $stmt = $ctrl->prepare("SELECT c.id FROM control_support_chats c WHERE c.id = ?" . $scopeSql . " LIMIT 1");
        $stmt->bind_param('i', $chatId);
        $stmt->execute();
        $stmt->store_result();
        $found = $stmt->num_rows > 0;
        $stmt->close();
        if (!$found) {
            jsonOut(['success' => false, 'message' => 'Chat not found']);
        }

        $res = $ctrl->query('SELECT id, sender, message, admin_user_id, created_at FROM control_support_chat_messages WHERE chat_id = ' . (int) $chatId . ' ORDER BY id ASC');
        $messages = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $messages[] = $row;
            }
        }

        // Mark as read
        $ctrl->query("UPDATE control_support_chats SET admin_read_at = NOW() WHERE id = " . (int)$chatId);

        jsonOut(['success' => true, 'messages' => $messages]);
    }

    // List all chats (optional filter by country — all agencies / countries share one queue)
    $status = trim($_GET['status'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(5, min(50, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    $countryFilter = isset($_GET['country_id']) ? (int) $_GET['country_id'] : 0;

    $where = [];
    if ($status !== '' && in_array($status, ['open', 'closed'], true)) {
        $where[] = "c.status = '" . $ctrl->real_escape_string($status) . "'";
    }
    $scopeTuple = supportCountryScopeSql('c', $hasCountryCol, $allowedCountryIds);
    $scopeBlocksAllChats = !empty($scopeTuple[1]);
    if ($scopeTuple[0] !== '') {
        $where[] = ltrim(substr($scopeTuple[0], 4)); // remove leading "AND "
    }
    if ($hasCountryCol) {
        if ($countryFilter > 0) {
            $where[] = 'c.country_id = ' . $countryFilter;
        } elseif ($countryFilter === -1) {
            $where[] = '(c.country_id IS NULL OR c.country_id = 0)';
        }
    }
    $whereClause = $where ? ' WHERE ' . implode(' AND ', $where) : '';

    $total = (int) ($ctrl->query('SELECT COUNT(*) as c FROM control_support_chats c' . $whereClause)->fetch_assoc()['c'] ?? 0);
    $res = $ctrl->query("SELECT c.*, 
        (SELECT COUNT(*) FROM control_support_chat_messages m WHERE m.chat_id = c.id AND m.sender = 'user' AND (c.admin_read_at IS NULL OR m.created_at > c.admin_read_at)) as unread_count,
        (SELECT m.message FROM control_support_chat_messages m WHERE m.chat_id = c.id ORDER BY m.id DESC LIMIT 1) as last_message
        FROM control_support_chats c" . $whereClause . ' ORDER BY c.updated_at DESC, c.id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset);
    $list = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $list[] = $row;
        }
    }

    $unreadScope = supportCountryScopeSql('c', $hasCountryCol, $allowedCountryIds)[0];
    $unreadRes = $ctrl->query("SELECT COUNT(DISTINCT c.id) as c FROM control_support_chats c WHERE c.status = 'open'" . $unreadScope . " AND (c.admin_read_at IS NULL OR EXISTS (SELECT 1 FROM control_support_chat_messages m WHERE m.chat_id = c.id AND m.sender = 'user' AND m.created_at > c.admin_read_at))");
    $unreadTotal = (int) ($unreadRes ? $unreadRes->fetch_assoc()['c'] ?? 0 : 0);

    $countries = [];
    $chkCo = @$ctrl->query("SHOW TABLES LIKE 'control_countries'");
    if ($chkCo && $chkCo->num_rows > 0) {
        $colActive = @$ctrl->query("SHOW COLUMNS FROM control_countries LIKE 'is_active'");
        $order = 'ORDER BY sort_order ASC, name ASC';
        $sqlCo = 'SELECT id, name FROM control_countries ' . ($colActive && $colActive->num_rows > 0 ? 'WHERE is_active = 1 ' : '') . $order;
        $resCo = @$ctrl->query($sqlCo);
        if ($resCo) {
            $allowedMap = null;
            if ($allowedCountryIds !== null) {
                $allowedMap = array_flip(array_map('intval', $allowedCountryIds));
            }
            while ($cr = $resCo->fetch_assoc()) {
                $cid = (int) $cr['id'];
                if ($allowedMap !== null && !isset($allowedMap[$cid])) {
                    continue;
                }
                $countries[] = ['id' => $cid, 'name' => trim((string)($cr['name'] ?? ''))];
            }
        }
    }

    $unreadByCountry = [];
    if ($hasCountryCol) {
        $ubScope = supportCountryScopeSql('c', $hasCountryCol, $allowedCountryIds)[0];
        $ubSql = "SELECT COALESCE(c.country_id, 0) AS country_id, COUNT(DISTINCT c.id) AS unread_chats
            FROM control_support_chats c
            WHERE c.status = 'open'
              " . $ubScope . "
              AND (c.admin_read_at IS NULL OR EXISTS (
                SELECT 1 FROM control_support_chat_messages m
                WHERE m.chat_id = c.id AND m.sender = 'user' AND m.created_at > c.admin_read_at
              ))
            GROUP BY COALESCE(c.country_id, 0)";
        $ubRes = @$ctrl->query($ubSql);
        $nameById = [];
        foreach ($countries as $co) {
            $nameById[(int) $co['id']] = $co['name'];
        }
        if ($ubRes) {
            while ($ub = $ubRes->fetch_assoc()) {
                $cid = (int) $ub['country_id'];
                $label = $cid > 0 ? ($nameById[$cid] ?? ('Country #' . $cid)) : 'Not set / public';
                $unreadByCountry[] = [
                    'country_id' => $cid,
                    'country_label' => $label,
                    'unread_chats' => (int) ($ub['unread_chats'] ?? 0),
                ];
            }
        }
    }

    $scopeHint = null;
    if (!empty($scopeBlocksAllChats)) {
        $scopeHint = 'Your control-panel user has no country scope, so this list is empty. Use Select Country (or ask an admin to assign country permissions), then refresh. Admins with full access see all countries.';
    }

    jsonOut([
        'success' => true,
        'list' => $list,
        'unread_total' => $unreadTotal,
        'country_scope_mode' => $countryScopeMode,
        'can_view_unscoped_chats' => $canViewUnscopedChats,
        'scope_blocks_all' => !empty($scopeBlocksAllChats),
        'support_scope_hint' => $scopeHint,
        'countries' => $countries,
        'unread_by_country' => $unreadByCountry,
        'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'pages' => max(1, (int) ceil($total / $limit))],
    ]);
}

// POST - admin reply (requires reply permission)
if ($method === 'POST') {
    if (!hasControlPermission(CONTROL_PERM_SUPPORT) && !hasControlPermission('reply_control_support')) {
        jsonOut(['success' => false, 'message' => 'Access denied']);
    }
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $chatId = (int)($input['chat_id'] ?? 0);
    $message = trim((string)($input['message'] ?? ''));
    $message = substr($message, 0, 8000);

    if ($chatId <= 0 || $message === '') {
        jsonOut(['success' => false, 'message' => 'Invalid request']);
    }

    $scopeSql = supportCountryScopeSql('c', $hasCountryCol, $allowedCountryIds)[0];
    $stmt = $ctrl->prepare("SELECT c.id FROM control_support_chats c WHERE c.id = ?" . $scopeSql . " LIMIT 1");
    $stmt->bind_param('i', $chatId);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows < 1) {
        $stmt->close();
        jsonOut(['success' => false, 'message' => 'Chat not found']);
    }
    $stmt->close();

    $adminId = (int)($_SESSION['control_user_id'] ?? $_SESSION['control_admin_id'] ?? 0);
    $ins = $ctrl->prepare("INSERT INTO control_support_chat_messages (chat_id, sender, message, admin_user_id) VALUES (?, 'support', ?, ?)");
    $ins->bind_param("isi", $chatId, $message, $adminId);
    if (!$ins->execute()) {
        $ins->close();
        jsonOut(['success' => false, 'message' => 'Failed to send reply']);
    }
    $msgId = (int) $ctrl->insert_id;
    $ins->close();

    $ctrl->query("UPDATE control_support_chats SET updated_at = NOW() WHERE id = " . (int)$chatId);

    jsonOut(['success' => true, 'message_id' => $msgId]);
}

// PATCH - close/open chat
if ($method === 'PATCH') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $chatId = (int)($input['chat_id'] ?? 0);
    $ids = supportNormalizeIds($input['ids'] ?? []);
    $action = trim((string)($input['action'] ?? ''));

    if (($chatId <= 0 && empty($ids)) || !in_array($action, ['close', 'open'], true)) {
        jsonOut(['success' => false, 'message' => 'Invalid request']);
    }

    if ($action === 'close' && !hasControlSupportMarkClosed()) {
        jsonOut(['success' => false, 'message' => 'Access denied']);
    }
    if ($action === 'open' && !hasControlSupportMarkOpen()) {
        jsonOut(['success' => false, 'message' => 'Access denied']);
    }

    $newStatus = $action === 'close' ? 'closed' : 'open';
    $scopeSql = supportCountryScopeSql('c', $hasCountryCol, $allowedCountryIds)[0];
    $safeScope = str_replace('c.', '', $scopeSql);
    $statusEsc = $ctrl->real_escape_string($newStatus);

    if ($chatId > 0) {
        $stmt = $ctrl->prepare("SELECT c.id FROM control_support_chats c WHERE c.id = ?" . $scopeSql . " LIMIT 1");
        $stmt->bind_param('i', $chatId);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows < 1) {
            $stmt->close();
            jsonOut(['success' => false, 'message' => 'Chat not found']);
        }
        $stmt->close();
        $ctrl->query("UPDATE control_support_chats SET status = '" . $statusEsc . "' WHERE id = " . (int)$chatId . $safeScope);
        if ((int)$ctrl->affected_rows < 1) {
            jsonOut(['success' => false, 'message' => 'Could not update status (no matching row in your country scope).']);
        }
        jsonOut(['success' => true, 'updated' => 1]);
    }

    $idsSql = implode(',', array_map('intval', $ids));
    if ($idsSql === '') {
        jsonOut(['success' => false, 'message' => 'No valid IDs']);
    }
    $sql = "UPDATE control_support_chats SET status = '" . $statusEsc . "' WHERE id IN (" . $idsSql . ")" . $safeScope;
    $ok = $ctrl->query($sql);
    if (!$ok) {
        jsonOut(['success' => false, 'message' => 'Failed to update chats']);
    }
    $aff = (int)$ctrl->affected_rows;
    if ($aff < 1) {
        jsonOut(['success' => false, 'message' => 'No chats were updated. They may be outside your country access, or IDs are invalid.']);
    }
    jsonOut(['success' => true, 'updated' => $aff]);
}

// DELETE - delete chat(s) from control panel list (messages removed by FK cascade)
if ($method === 'DELETE') {
    if (!hasControlSupportDeleteChat()) {
        jsonOut(['success' => false, 'message' => 'Access denied']);
    }
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $chatId = (int)($input['chat_id'] ?? 0);
    $ids = supportNormalizeIds($input['ids'] ?? []);
    if ($chatId > 0) $ids[] = $chatId;
    $ids = supportNormalizeIds($ids);
    if (empty($ids)) {
        jsonOut(['success' => false, 'message' => 'No valid IDs']);
    }

    $idsSql = implode(',', array_map('intval', $ids));
    $scopeSql = supportCountryScopeSql('c', $hasCountryCol, $allowedCountryIds)[0];
    $safeScope = str_replace('c.', '', $scopeSql);
    $delSql = "DELETE FROM control_support_chats WHERE id IN (" . $idsSql . ")" . $safeScope;
    $ok = $ctrl->query($delSql);
    if (!$ok) {
        jsonOut(['success' => false, 'message' => 'Failed to delete chats']);
    }
    $del = (int)$ctrl->affected_rows;
    if ($del < 1) {
        jsonOut(['success' => false, 'message' => 'No chats were deleted. They may be outside your country access, or IDs are invalid.']);
    }
    jsonOut(['success' => true, 'deleted' => $del]);
}

jsonOut(['success' => false, 'message' => 'Method not allowed']);
