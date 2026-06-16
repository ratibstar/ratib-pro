<?php
/**
 * EN: Handles API endpoint/business logic in `api/diagnostics/tenant-isolation-self-test.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/diagnostics/tenant-isolation-self-test.php`.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../includes/config.php';

$isAppUser = isset($_SESSION['user_id'], $_SESSION['logged_in'])
    && $_SESSION['logged_in'] === true
    && (int) $_SESSION['user_id'] > 0;
$isControlUser = !empty($_SESSION['control_logged_in']);
if (!$isAppUser && !$isControlUser) {
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
$tableChecksEnabled = $isAppUser || (isset($_SESSION['agency_id']) && (int) $_SESSION['agency_id'] > 0);

/**
 * @return bool
 */
function rateb_selftest_table_exists(mysqli $conn, string $table): bool
{
    $safe = $conn->real_escape_string($table);
    $res = @$conn->query("SHOW TABLES LIKE '{$safe}'");
    return (bool) ($res && $res->num_rows > 0);
}

/**
 * @return bool
 */
function rateb_selftest_file_has_config_include(string $absPath): bool
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
function rateb_selftest_file_avoids_direct_db_fallback(string $absPath): bool
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
        'page_uses_bootstrap' => rateb_selftest_file_has_config_include($pages . 'dashboard.php'),
        'page_avoids_direct_db_fallback' => rateb_selftest_file_avoids_direct_db_fallback($pages . 'dashboard.php'),
        'table_agents_exists' => $tableChecksEnabled ? rateb_selftest_table_exists($conn, 'agents') : null,
    ],
    'Agent' => [
        'page_uses_bootstrap' => rateb_selftest_file_has_config_include($pages . 'agent.php'),
        'table_agents_exists' => $tableChecksEnabled ? rateb_selftest_table_exists($conn, 'agents') : null,
    ],
    'SubAgent' => [
        'page_uses_bootstrap' => rateb_selftest_file_has_config_include($pages . 'subagent.php'),
        'table_subagents_exists' => $tableChecksEnabled ? rateb_selftest_table_exists($conn, 'subagents') : null,
    ],
    'Workers' => [
        'page_uses_bootstrap' => rateb_selftest_file_has_config_include($pages . 'Worker.php'),
        'table_workers_exists' => $tableChecksEnabled ? rateb_selftest_table_exists($conn, 'workers') : null,
    ],
    'Partner Agencies' => [
        'page_uses_bootstrap' => rateb_selftest_file_has_config_include($pages . 'partner-agencies.php'),
        'table_partner_agencies_exists' => $tableChecksEnabled ? rateb_selftest_table_exists($conn, 'partner_agencies') : null,
    ],
    'Cases' => [
        'page_uses_bootstrap' => rateb_selftest_file_has_config_include($pages . 'cases' . DIRECTORY_SEPARATOR . 'cases-table.php'),
        'table_cases_exists' => $tableChecksEnabled ? rateb_selftest_table_exists($conn, 'cases') : null,
    ],
    'Accounting' => [
        'page_uses_bootstrap' => rateb_selftest_file_has_config_include($pages . 'accounting.php'),
        'table_financial_transactions_exists' => $tableChecksEnabled ? rateb_selftest_table_exists($conn, 'financial_transactions') : null,
    ],
    'HR' => [
        'page_uses_bootstrap' => rateb_selftest_file_has_config_include($pages . 'hr.php'),
        'table_employees_exists' => $tableChecksEnabled ? rateb_selftest_table_exists($conn, 'employees') : null,
    ],
    'Reports' => [
        'page_uses_bootstrap' => rateb_selftest_file_has_config_include($pages . 'reports.php'),
        'table_activity_logs_exists' => $tableChecksEnabled ? rateb_selftest_table_exists($conn, 'activity_logs') : null,
    ],
    'Contact' => [
        'page_uses_bootstrap' => rateb_selftest_file_has_config_include($pages . 'contact.php'),
        'table_contacts_exists' => $tableChecksEnabled ? rateb_selftest_table_exists($conn, 'contacts') : null,
    ],
    'Notifications' => [
        'page_uses_bootstrap' => rateb_selftest_file_has_config_include($pages . 'notifications.php'),
        'table_contact_notifications_exists' => $tableChecksEnabled ? rateb_selftest_table_exists($conn, 'contact_notifications') : null,
    ],
    'Register Pro' => [
        'page_exists' => is_readable($pages . 'register-pro.php'),
        'is_redirect_page' => !rateb_selftest_file_has_config_include($pages . 'register-pro.php'),
    ],
    'System Settings' => [
        'page_uses_bootstrap' => rateb_selftest_file_has_config_include($pages . 'system-settings.php'),
        'table_users_exists' => $tableChecksEnabled ? rateb_selftest_table_exists($conn, 'users') : null,
    ],
    'Help & Learning Center' => [
        'page_uses_bootstrap' => rateb_selftest_file_has_config_include($pages . 'help-center.php'),
        'table_help_articles_exists' => $tableChecksEnabled ? rateb_selftest_table_exists($conn, 'help_articles') : null,
    ],
    'Logout' => [
        'page_uses_bootstrap' => rateb_selftest_file_has_config_include($pages . 'logout.php'),
        'page_exists' => is_readable($pages . 'logout.php'),
    ],
];

// Extra runtime safety checks for patched APIs that previously opened direct DB fallbacks.
$apiGuards = [
    'chat_voice_messages_no_direct_fallback' => rateb_selftest_file_avoids_direct_db_fallback($api . 'chat-voice' . DIRECTORY_SEPARATOR . 'messages.php'),
    'chat_voice_conversations_no_direct_fallback' => rateb_selftest_file_avoids_direct_db_fallback($api . 'chat-voice' . DIRECTORY_SEPARATOR . 'conversations.php'),
    'chat_voice_users_no_direct_fallback' => rateb_selftest_file_avoids_direct_db_fallback($api . 'chat-voice' . DIRECTORY_SEPARATOR . 'users.php'),
];

$strictCheckPaths = [
    'Dashboard.page_uses_bootstrap',
    'Dashboard.page_avoids_direct_db_fallback',
    'Agent.page_uses_bootstrap',
    'SubAgent.page_uses_bootstrap',
    'Workers.page_uses_bootstrap',
    'Partner Agencies.page_uses_bootstrap',
    'Cases.page_uses_bootstrap',
    'Accounting.page_uses_bootstrap',
    'HR.page_uses_bootstrap',
    'Reports.page_uses_bootstrap',
    'Contact.page_uses_bootstrap',
    'Notifications.page_uses_bootstrap',
    'System Settings.page_uses_bootstrap',
    'Help & Learning Center.page_uses_bootstrap',
    'Logout.page_uses_bootstrap',
    'Register Pro.page_exists',
    'Register Pro.is_redirect_page',
];

$failedStrictChecks = [];
foreach ($strictCheckPaths as $path) {
    $parts = explode('.', $path, 2);
    $module = $parts[0] ?? '';
    $key = $parts[1] ?? '';
    $value = $checks[$module][$key] ?? null;
    if ($value !== true) {
        $failedStrictChecks[] = $path;
    }
}
foreach ($apiGuards as $k => $ok) {
    if ($ok !== true) {
        $failedStrictChecks[] = 'api_guards.' . $k;
    }
}

$allOk = empty($failedStrictChecks);

echo json_encode([
    'success' => true,
    'isolation_ok' => $allOk,
    'runtime_context' => [
        'host' => (string) ($_SERVER['HTTP_HOST'] ?? ''),
        'session_type' => $isControlUser && !$isAppUser ? 'control' : 'app',
        'single_url_mode' => defined('SINGLE_URL_MODE') ? (bool) SINGLE_URL_MODE : false,
        'tenant_id' => isset($_SESSION['country_id']) ? (int) $_SESSION['country_id'] : null,
        'agency_id' => isset($_SESSION['agency_id']) ? (int) $_SESSION['agency_id'] : null,
        'table_checks_enabled' => $tableChecksEnabled,
        'db_name_active' => $dbName,
        'db_host' => $dbHost,
        'db_port' => $dbPort,
        'agency_db' => $agencyDb,
    ],
    'module_checks' => $checks,
    'api_guards' => $apiGuards,
    'failed_strict_checks' => $failedStrictChecks,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

