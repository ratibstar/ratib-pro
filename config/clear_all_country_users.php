<?php
/**
 * EN: Handles configuration/runtime setup behavior in `config/clear_all_country_users.php`.
 * AR: يدير سلوك إعدادات النظام وتهيئة التشغيل في `config/clear_all_country_users.php`.
 */
/**
 * Clear all users from each country database - start fresh.
 *
 * Run from browser: https://out.ratib.sa/config/clear_all_country_users.php?confirm=1
 * Or from CLI: php config/clear_all_country_users.php
 *
 * WARNING: This DELETES all users in each country DB. Use only when you want to start from zero.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (php_sapi_name() === 'cli') {
    $_SERVER['HTTP_HOST'] = 'out.ratib.sa';
}
require_once __DIR__ . '/env/load.php';

if (!defined('DB_HOST') || !defined('DB_NAME')) {
    die('Config not loaded.');
}

$confirm = isset($_GET['confirm']) && $_GET['confirm'] === '1';
if (!$confirm) {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Clear Country Users</title></head><body>';
    echo '<h1>Clear all users from country databases</h1>';
    echo '<p>This will <strong>DELETE all users</strong> in each country database (Bangladesh, Kenya, Uganda, etc.).</p>';
    echo '<p>You will start with 0 users per country and can add users from the beginning.</p>';
    echo '<p><a href="?confirm=1" style="background:#dc3545;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;">Yes, clear all users</a></p>';
    echo '<p><a href="../pages/control/country-users.php?control=1">Cancel - back to Country Users</a></p>';
    echo '</body></html>';
    exit;
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT ?? 3306);
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die('Main DB connection failed: ' . $conn->connect_error);
}

header('Content-Type: text/html; charset=UTF-8');
echo "<pre>\n";
echo "=== Clear All Country Users ===\n\n";

$seenDbs = [];
$agenciesRes = $conn->query("SELECT id, country_id, name, db_host, db_port, db_user, db_pass, db_name FROM control_agencies WHERE is_active = 1");
if (!$agenciesRes) {
    die("control_agencies not found.\n");
}

$dbHost = defined('DB_HOST') ? DB_HOST : 'localhost';
$dbPort = (int)(defined('DB_PORT') ? DB_PORT : 3306);
$dbUser = defined('DB_USER') ? DB_USER : '';
$dbPass = defined('DB_PASS') ? DB_PASS : '';

while ($agency = $agenciesRes->fetch_assoc()) {
    $dbName = trim($agency['db_name'] ?? '');
    if (empty($dbName) || $dbName === DB_NAME) continue;
    if (isset($seenDbs[$dbName])) continue;
    $seenDbs[$dbName] = true;

    $countryName = $agency['name'] ?? $dbName;
    try {
        $host = trim($agency['db_host'] ?? '') ?: $dbHost;
        $user = trim($agency['db_user'] ?? '') ?: $dbUser;
        $pass = $agency['db_pass'] ?? $dbPass;
        $dbConn = @new mysqli($host, $user, $pass, $dbName, (int)($agency['db_port'] ?? $dbPort));
        if ($dbConn->connect_error && $dbHost && $dbUser) {
            $dbConn = @new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
        }
        if (!$dbConn || $dbConn->connect_error) {
            echo "  [SKIP] $countryName ($dbName): " . ($dbConn->connect_error ?? 'Connection failed') . "\n";
            continue;
        }
        $dbConn->set_charset('utf8mb4');
        $before = 0;
        $r = $dbConn->query("SELECT COUNT(*) as c FROM users");
        if ($r) $before = (int)($r->fetch_assoc()['c'] ?? 0);
        $dbConn->query("SET FOREIGN_KEY_CHECKS = 0");
        $dbConn->query("DELETE FROM users");
        $deleted = $dbConn->affected_rows;
        $dbConn->query("SET FOREIGN_KEY_CHECKS = 1");
        echo "  [OK] $countryName ($dbName): deleted $deleted users (was $before)\n";
        $dbConn->close();
    } catch (Throwable $e) {
        echo "  [FAIL] $countryName ($dbName): " . $e->getMessage() . "\n";
    }
}

$agenciesRes->free();
$conn->close();

echo "\n=== Done. All country users cleared. ===\n";
echo "<a href=\"../pages/control/country-users.php?control=1\">Back to Country Users</a>\n";
echo "</pre>\n";
