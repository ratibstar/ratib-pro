<?php
/**
 * EN: Handles configuration/runtime setup behavior in `config/test-control-db.php`.
 * AR: يدير سلوك إعدادات النظام وتهيئة التشغيل في `config/test-control-db.php`.
 */
/**
 * DIAGNOSTIC: Test Control Panel DB connection.
 * Run: https://out.ratib.sa/config/test-control-db.php
 * DELETE THIS FILE after fixing.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain; charset=utf-8');

$db_host = 'localhost';
$db_port = 3306;
$db_user = 'outratib_out';
$db_pass = '9s%BpMr1]dfb';
$db_name = 'outratib_out';

echo "=== Control Panel DB Connection Test ===\n\n";
echo "Host: $db_host\n";
echo "Port: $db_port\n";
echo "User: $db_user\n";
echo "DB:   $db_name\n\n";

echo "Attempting connection with localhost...\n";
$conn = @new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
if ($conn->connect_error) {
    echo "FAILED: " . $conn->connect_error . "\n\n";
    echo "Trying 127.0.0.1...\n";
    $conn = @new mysqli('127.0.0.1', $db_user, $db_pass, $db_name, $db_port);
}
if (!$conn->connect_error) {
    echo "SUCCESS!\n";
    $conn->set_charset("utf8mb4");
    $r = $conn->query("SELECT COUNT(*) as c FROM control_admins");
    echo "control_admins rows: " . ($r ? $r->fetch_assoc()['c'] : '?') . "\n";
    $conn->close();
} else {
    echo "FAILED: " . $conn->connect_error . "\n";
}
