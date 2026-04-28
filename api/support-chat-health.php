<?php
/**
 * EN: Handles API endpoint/business logic in `api/support-chat-health.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/support-chat-health.php`.
 */
/**
 * Temporary diagnostics for support chat routing.
 * Remove after deployment is verified.
 */
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/support_chat_db.php';
mysqli_report(MYSQLI_REPORT_OFF);

$isAppAdmin = function_exists('ratib_program_session_is_valid_user') && ratib_program_session_is_valid_user()
    && (int) ($_SESSION['role_id'] ?? 0) === 1;
$isControlLogged = !empty($_SESSION['control_logged_in']);
if (!$isAppAdmin && !$isControlLogged) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

function h_json_out($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $conn = ratib_support_chat_db();
    $sessionCountryId = !empty($_SESSION['country_id']) ? (int) $_SESSION['country_id'] : 0;
    $sessionAgencyId = !empty($_SESSION['agency_id']) ? (int) $_SESSION['agency_id'] : 0;
    $sessionCountryName = trim((string)($_SESSION['country_name'] ?? ''));
    $sessionAgencyName = trim((string)($_SESSION['agency_name'] ?? ''));

    $result = [
        'success' => true,
        'host' => $_SERVER['HTTP_HOST'] ?? '',
        'site_url' => defined('SITE_URL') ? SITE_URL : null,
        'single_url_mode' => defined('SINGLE_URL_MODE') ? (bool) SINGLE_URL_MODE : false,
        'control_panel_db_name' => defined('CONTROL_PANEL_DB_NAME') ? CONTROL_PANEL_DB_NAME : null,
        'app_db_name' => defined('DB_NAME') ? DB_NAME : null,
        'session' => [
            'logged_in' => !empty($_SESSION['logged_in']),
            'country_id' => $sessionCountryId,
            'agency_id' => $sessionAgencyId,
            'country_name' => $sessionCountryName,
            'agency_name' => $sessionAgencyName,
        ],
        'resolved_db' => null,
        'tables' => [
            'control_support_chats' => false,
            'control_support_chat_messages' => false,
        ],
        'counts' => [
            'open_chats' => null,
            'total_chats' => null,
            'last_chat_id' => null,
            'last_chat_created_at' => null,
        ],
    ];

    if (!$conn instanceof mysqli || $conn->connect_errno) {
        $result['success'] = false;
        $result['message'] = 'ratib_support_chat_db() returned no connection';
        h_json_out($result);
    }

    $result['resolved_db'] = [
        'host_info' => $conn->host_info ?? null,
        'server_info' => $conn->server_info ?? null,
        'database' => null,
    ];
    $dbRes = @$conn->query('SELECT DATABASE() AS db');
    if ($dbRes && ($row = $dbRes->fetch_assoc())) {
        $result['resolved_db']['database'] = $row['db'] ?? null;
    }

    $t1 = @$conn->query("SHOW TABLES LIKE 'control_support_chats'");
    $t2 = @$conn->query("SHOW TABLES LIKE 'control_support_chat_messages'");
    $result['tables']['control_support_chats'] = (bool) ($t1 && $t1->num_rows > 0);
    $result['tables']['control_support_chat_messages'] = (bool) ($t2 && $t2->num_rows > 0);

    if ($result['tables']['control_support_chats']) {
        $rTotal = @$conn->query("SELECT COUNT(*) AS c FROM control_support_chats");
        if ($rTotal && ($row = $rTotal->fetch_assoc())) {
            $result['counts']['total_chats'] = (int) ($row['c'] ?? 0);
        }
        $rOpen = @$conn->query("SELECT COUNT(*) AS c FROM control_support_chats WHERE status = 'open'");
        if ($rOpen && ($row = $rOpen->fetch_assoc())) {
            $result['counts']['open_chats'] = (int) ($row['c'] ?? 0);
        }
        $rLast = @$conn->query("SELECT id, created_at FROM control_support_chats ORDER BY id DESC LIMIT 1");
        if ($rLast && ($row = $rLast->fetch_assoc())) {
            $result['counts']['last_chat_id'] = (int) ($row['id'] ?? 0);
            $result['counts']['last_chat_created_at'] = $row['created_at'] ?? null;
        }
    }

    h_json_out($result);
} catch (Throwable $e) {
    h_json_out([
        'success' => false,
        'message' => 'Health check failed',
        'error' => $e->getMessage(),
    ]);
}
