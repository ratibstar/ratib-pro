<?php
/**
 * EN: Handles configuration/runtime setup behavior in `config/drop_and_prepare_bangladesh.php`.
 * AR: يدير سلوك إعدادات النظام وتهيئة التشغيل في `config/drop_and_prepare_bangladesh.php`.
 */
/**
 * Drop all tables in outratib_bangladesh, then you can import fresh.
 * Run: https://out.ratib.sa/config/drop_and_prepare_bangladesh.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'out.ratib.sa';
require_once __DIR__ . '/env/load.php';

if (!defined('DB_HOST') || !defined('DB_NAME')) {
    die('Config not loaded.');
}

$targetDb = 'outratib_bangladesh';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, '', DB_PORT ?? 3306);
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

echo "<pre>\n=== Drop and prepare $targetDb ===\n\n";

// Select the target database
if (!$conn->select_db($targetDb)) {
    die("Cannot use database $targetDb. Does it exist?\n");
}

// Get all tables
$tables = [];
$r = $conn->query("SHOW TABLES");
if ($r) {
    while ($row = $r->fetch_array()) {
        $tables[] = $row[0];
    }
    $r->free();
}

if (empty($tables)) {
    echo "Database is already empty. You can import now.\n";
    $conn->close();
    echo "</pre>";
    exit;
}

// Disable foreign key checks
$conn->query("SET FOREIGN_KEY_CHECKS = 0");

foreach ($tables as $t) {
    $escaped = '`' . str_replace('`', '``', $t) . '`';
    if ($conn->query("DROP TABLE $escaped")) {
        echo "Dropped: $t\n";
    } else {
        echo "Failed: $t - " . $conn->error . "\n";
    }
}

$conn->query("SET FOREIGN_KEY_CHECKS = 1");
$conn->close();

echo "\nDone. Database $targetDb is now empty.\n";
echo "Go to phpMyAdmin → $targetDb → Import → choose your .sql file → Go\n";
echo "</pre>\n";
