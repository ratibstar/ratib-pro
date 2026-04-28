<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/diagnose.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/diagnose.php`.
 */
/**
 * Run once: https://out.ratib.sa/control-panel/diagnose.php
 * Shows why the control panel might still fail. DELETE this file after.
 */
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: text/plain; charset=utf-8');

$out = [];
$out[] = '=== Control Panel Diagnostic ===';
$out[] = '';

$envFile = __DIR__ . '/config/env.php';
if (!is_file($envFile)) {
    $out[] = 'FAIL: config/env.php not found';
    echo implode("\n", $out);
    exit;
}
require_once $envFile;

$dbName = defined('CONTROL_PANEL_DB_NAME') ? CONTROL_PANEL_DB_NAME : (defined('DB_NAME') ? DB_NAME : '');
$out[] = 'DB_HOST: ' . (defined('DB_HOST') ? DB_HOST : '?');
$out[] = 'DB_USER: ' . (defined('DB_USER') ? DB_USER : '?');
$out[] = 'CONTROL_PANEL_DB_NAME: ' . $dbName;
$out[] = 'DB_PASS: ' . (defined('DB_PASS') && DB_PASS !== '' ? '(set)' : '(empty)');
$out[] = '';

$conn = @new mysqli(
    defined('DB_HOST') ? DB_HOST : 'localhost',
    defined('DB_USER') ? DB_USER : '',
    defined('DB_PASS') ? DB_PASS : '',
    $dbName,
    defined('DB_PORT') ? (int)DB_PORT : 3306
);

if ($conn->connect_error) {
    $out[] = 'CONNECTION: FAIL';
    $out[] = 'Error: ' . $conn->connect_error;
    $out[] = '';
    $out[] = 'Fix: cPanel → MySQL® Databases → Add User To Database';
    $out[] = '  User: ' . (defined('DB_USER') ? DB_USER : '') . '  Database: ' . $dbName . '  → ALL PRIVILEGES';
    echo implode("\n", $out);
    exit;
}

$out[] = 'CONNECTION: OK';
$out[] = 'Connected to: ' . ($conn->query("SELECT DATABASE()")->fetch_row()[0] ?? '?');
$out[] = '';

$tables = ['control_countries', 'control_admins', 'control_agencies'];
foreach ($tables as $t) {
    $r = @$conn->query("SELECT COUNT(*) FROM `" . $conn->real_escape_string($t) . "`");
    if ($r) {
        $n = (int)$r->fetch_row()[0];
        $out[] = $t . ': ' . $n . ' rows';
    } else {
        $out[] = $t . ': table missing or no access';
    }
}

$conn->close();
$out[] = '';
$out[] = 'If CONNECTION is OK and control_countries has 12 rows, the panel should work.';
$out[] = 'Hard-refresh the page (Ctrl+F5) or try Manage Countries.';
$out[] = '';
$out[] = 'Delete this file (diagnose.php) after use.';

echo implode("\n", $out);
