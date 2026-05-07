<?php
/**
 * EN: Handles API endpoint/business logic in `api/diagnostics/tenant-isolation-self-test.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/diagnostics/tenant-isolation-self-test.php`.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../includes/config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!isset($GLOBALS['conn']) || !($GLOBALS['conn'] instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Tenant DB connection missing'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

/**
 * @return bool
 */
function ratib_selftest_table_exists(mysqli $conn, string $table): bool
{
    $safe = $conn->real_escape_string($table);
    $res = @$conn->query("SHOW TABLES LIKE '{$safe}'");
    return (bool) ($res && $res->num_rows > 0);
}

/**
 * @return bool
 */
function ratib_selftest_file_has_config_include(string $absPath): bool
{
    if (!is_readable($absPath)) {
        return false;
    }
    $content = @file_get_contents($absPath);
    if (!is_string($content) || $content === '') {
        return false;
    }
    return (strpos($content, 'includes/config.php') !== false);
}

/**
 * @return bool
 */
function ratib_selftest_file_avoids_direct_db_fallback(string $absPath): bool
{
    if (!is_readable($absPath)) {
        return false;
    }
    $content = @file_get_contents($absPath);
    if (!is_string($content) || $content === '') {
        return false;
    }
    return (strpos($content, 'new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT)') === false);
}

$dbName = '';
$dbHost = defined('DB_HOST') ? (string) DB_HOST : '';
$dbPort = defined('DB_PORT') ? (int) DB_PORT : 0;
$agencyDb = isset($GLOBALS['agency_db']) && is_array($GLOBALS['agency_db']) ? $GLOBALS['agency_db'] : null;

try {
    $resDb = $conn->query('SELECT DATABASE() AS db_name');
    if ($resDb && ($row = $resDb->fetch_assoc())) {
        $dbName = (string) ($row['db_name'] ?? '');
    }
} catch (Throwable $e) {
    // Keep running and report partial diagnostics.
}

$root = realpath(__DIR__ . '/../../');
$pages = $root . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR;
$api = $root . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR;

$checks = [
    'Dashboard' => [
        'page_uses_bootstrap' => ratib_selftest_file_has_config_include($pages . 'dashboard.php'),
        'page_avoids_direct_db_fallback' => ratib_selftest_file_avoids_direct_db_fallback($pages . 'dashboard.php'),
        'table_agents_exists' => ratib_selftest_table_exists($conn, 'agents'),
    ],
    'Agent' => [
        'page_uses_bootstrap' => ratib_selftest_file_has_config_include($pages . 'agent.php'),
        'table_agents_exists' => ratib_selftest_table_exists($conn, 'agents'),
    ],
    'SubAgent' => [
        'page_uses_bootstrap' => ratib_selftest_file_has_config_include($pages . 'subagent.php'),
        'table_subagents_exists' => ratib_selftest_table_exists($conn, 'subagents'),
    ],
    'Workers' => [
        'page_uses_bootstrap' => ratib_selftest_file_has_config_include($pages . 'Worker.php'),
        'table_workers_exists' => ratib_selftest_table_exists($conn, 'workers'),
    ],
    'Partner Agencies' => [
        'page_uses_bootstrap' => ratib_selftest_file_has_config_include($pages . 'partner-agencies.php'),
        'table_partner_agencies_exists' => ratib_selftest_table_exists($conn, 'partner_agencies'),
    ],
    'Cases' => [
        'page_uses_bootstrap' => ratib_selftest_file_has_config_include($pages . 'cases' . DIRECTORY_SEPARATOR . 'cases-table.php'),
        'table_cases_exists' => ratib_selftest_table_exists($conn, 'cases'),
    ],
    'Accounting' => [
        'page_uses_bootstrap' => ratib_selftest_file_has_config_include($pages . 'accounting.php'),
        'table_financial_transactions_exists' => ratib_selftest_table_exists($conn, 'financial_transactions'),
    ],
    'HR' => [
        'page_uses_bootstrap' => ratib_selftest_file_has_config_include($pages . 'hr.php'),
        'table_employees_exists' => ratib_selftest_table_exists($conn, 'employees'),
    ],
    'Reports' => [
        'page_uses_bootstrap' => ratib_selftest_file_has_config_include($pages . 'reports.php'),
        'table_activity_logs_exists' => ratib_selftest_table_exists($conn, 'activity_logs'),
    ],
    'Contact' => [
        'page_uses_bootstrap' => ratib_selftest_file_has_config_include($pages . 'contact.php'),
        'table_contacts_exists' => ratib_selftest_table_exists($conn, 'contacts'),
    ],
    'Notifications' => [
        'page_uses_bootstrap' => ratib_selftest_file_has_config_include($pages . 'notifications.php'),
        'table_contact_notifications_exists' => ratib_selftest_table_exists($conn, 'contact_notifications'),
    ],
    'Register Pro' => [
        'page_exists' => is_readable($pages . 'register-pro.php'),
        'is_redirect_page' => !ratib_selftest_file_has_config_include($pages . 'register-pro.php'),
    ],
    'System Settings' => [
        'page_uses_bootstrap' => ratib_selftest_file_has_config_include($pages . 'system-settings.php'),
        'table_users_exists' => ratib_selftest_table_exists($conn, 'users'),
    ],
    'Help & Learning Center' => [
        'page_uses_bootstrap' => ratib_selftest_file_has_config_include($pages . 'help-center.php'),
        'table_help_articles_exists' => ratib_selftest_table_exists($conn, 'help_articles'),
    ],
    'Logout' => [
        'page_uses_bootstrap' => ratib_selftest_file_has_config_include($pages . 'logout.php'),
        'page_exists' => is_readable($pages . 'logout.php'),
    ],
];

// Extra runtime safety checks for patched APIs that previously opened direct DB fallbacks.
$apiGuards = [
    'chat_voice_messages_no_direct_fallback' => ratib_selftest_file_avoids_direct_db_fallback($api . 'chat-voice' . DIRECTORY_SEPARATOR . 'messages.php'),
    'chat_voice_conversations_no_direct_fallback' => ratib_selftest_file_avoids_direct_db_fallback($api . 'chat-voice' . DIRECTORY_SEPARATOR . 'conversations.php'),
    'chat_voice_users_no_direct_fallback' => ratib_selftest_file_avoids_direct_db_fallback($api . 'chat-voice' . DIRECTORY_SEPARATOR . 'users.php'),
];

$allOk = true;
foreach ($checks as $module => $moduleChecks) {
    foreach ($moduleChecks as $ok) {
        if ($ok !== true) {
            $allOk = false;
            break 2;
        }
    }
}
if ($allOk) {
    foreach ($apiGuards as $ok) {
        if ($ok !== true) {
            $allOk = false;
            break;
        }
    }
}

echo json_encode([
    'success' => true,
    'isolation_ok' => $allOk,
    'runtime_context' => [
        'host' => (string) ($_SERVER['HTTP_HOST'] ?? ''),
        'single_url_mode' => defined('SINGLE_URL_MODE') ? (bool) SINGLE_URL_MODE : false,
        'tenant_id' => isset($_SESSION['country_id']) ? (int) $_SESSION['country_id'] : null,
        'agency_id' => isset($_SESSION['agency_id']) ? (int) $_SESSION['agency_id'] : null,
        'db_name_active' => $dbName,
        'db_host' => $dbHost,
        'db_port' => $dbPort,
        'agency_db' => $agencyDb,
    ],
    'module_checks' => $checks,
    'api_guards' => $apiGuards,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

