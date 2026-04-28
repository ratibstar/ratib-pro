<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/check_db.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/check_db.php`.
 */
/**
 * Control panel DB check – run once in browser then DELETE this file.
 * URL: https://out.ratib.sa/control-panel/check_db.php
 */
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/config/env.php';

echo "=== Control Panel DB Check ===\n\n";
echo "DB_HOST: " . DB_HOST . "\n";
echo "DB_PORT: " . DB_PORT . "\n";
echo "DB_USER: " . DB_USER . "\n";
echo "DB_NAME (control): " . (defined('CONTROL_PANEL_DB_NAME') ? CONTROL_PANEL_DB_NAME : DB_NAME) . "\n";
echo "DB_PASS: " . (DB_PASS ? '(set)' : '(empty)') . "\n\n";

$dbName = defined('CONTROL_PANEL_DB_NAME') ? CONTROL_PANEL_DB_NAME : DB_NAME;
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, $dbName, DB_PORT);
    $conn->set_charset("utf8mb4");
    echo "Connection: OK\n\n";

    $r = $conn->query("SELECT COUNT(*) AS n FROM control_admins");
    $row = $r ? $r->fetch_assoc() : null;
    $count = $row ? (int)$row['n'] : 0;
    echo "control_admins rows: " . $count . "\n";
    if ($count === 0) {
        echo "\n>>> No admin user! Add one in phpMyAdmin:\n";
        echo "INSERT INTO control_admins (username, password, full_name, is_active)\n";
        echo "VALUES ('admin', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 1);\n";
        echo "(Password for that hash is: password)\n";
    } else {
        $list = $conn->query("SELECT id, username, full_name, is_active FROM control_admins LIMIT 10");
        if ($list) {
            echo "Admins: ";
            $usernames = [];
            while ($r = $list->fetch_assoc()) $usernames[] = $r['username'] . ($r['is_active'] ? '' : ' [inactive]');
            echo implode(', ', $usernames) . "\n";
        }
    }
    $conn->close();
} catch (Exception $e) {
    echo "Connection: FAILED\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    echo "Fix:\n";
    echo "1. In cPanel → MySQL Databases: add user '" . DB_USER . "' to database '" . $dbName . "' with ALL PRIVILEGES.\n";
    echo "2. Edit control-panel/config/env.php on server: set CONTROL_DB_HOST, CONTROL_DB_USER, CONTROL_DB_PASS, CONTROL_PANEL_DB_NAME to match your server.\n";
}

echo "\n=== Delete this file (check_db.php) after use for security. ===\n";
