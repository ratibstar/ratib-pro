<?php
/**
 * EN: Handles configuration/runtime setup behavior in `config/verify-db.php`.
 * AR: يدير سلوك إعدادات النظام وتهيئة التشغيل في `config/verify-db.php`.
 */
/**
 * Find which database has control_agencies and control_admins.
 * Run: https://out.ratib.sa/config/verify-db.php
 * DELETE after fixing.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

$host = '127.0.0.1';
$user = 'outratib_out';
$pass = '9s%BpMr1]dfb';
$port = 3306;

$conn = @new mysqli($host, $user, $pass, '', $port);
if ($conn->connect_error) {
    echo "Cannot connect to MySQL: " . $conn->connect_error . "\n";
    exit;
}
$conn->set_charset("utf8mb4");

echo "Connected to MySQL. Checking databases...\n\n";

$dbs = ['outratib_control', 'outratib_ctrl', 'outratib_outratib_ctrl', 'outratib_outratib_control'];
foreach ($dbs as $db) {
    $r = @$conn->query("SELECT COUNT(*) as c FROM `" . $conn->real_escape_string($db) . "`.control_admins");
    if ($r) {
        $row = $r->fetch_assoc();
        echo "✓ $db - HAS control_admins (" . $row['c'] . " rows) - USE THIS\n";
    } else {
        $err = $conn->error;
        if (strpos($err, 'Unknown database') !== false) {
            echo "- $db - does not exist\n";
        } else {
            echo "✗ $db - $err\n";
        }
    }
}

echo "\n";
echo "Config must use DB_NAME = the database that shows ✓ above.\n";
