<?php
/**
 * EN: Handles configuration/runtime setup behavior in `config/drop_and_prepare_country.php`.
 * AR: يدير سلوك إعدادات النظام وتهيئة التشغيل في `config/drop_and_prepare_country.php`.
 */
/**
 * Drop all tables in a country database, then you can import fresh.
 * Run: https://out.ratib.sa/config/drop_and_prepare_country.php?country=ethiopia
 *
 * Supported: bangladesh, ethiopia, indonesia, kenya, nepal, nigeria,
 *            philippines, rwanda, sri_lanka, thailand, uganda
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'out.ratib.sa';
require_once __DIR__ . '/env/load.php';

if (!defined('DB_HOST') || !defined('DB_NAME')) {
    die('Config not loaded.');
}

$countryMap = [
    'bangladesh' => 'outratib_bangladesh',
    'bangladish' => 'outratib_bangladish',
    'ethiopia' => 'outratib_ethiopia',
    'indonesia' => 'outratib_indonesia',
    'kenya' => 'outratib_kenya',
    'nepal' => 'outratib_nepal',
    'nigeria' => 'outratib_nigeria',
    'philippines' => 'outratib_philippines',
    'rwanda' => 'outratib_rwanda',
    'sri_lanka' => 'outratib_sri_lanka',
    'thailand' => 'outratib_thailand',
    'uganda' => 'outratib_uganda',
];

$country = strtolower(trim($_GET['country'] ?? ''));
if (empty($country)) {
    die('Add ?country=ethiopia (or bangladesh, indonesia, kenya, etc.) to the URL');
}

$targetDb = $countryMap[$country] ?? null;
if (!$targetDb) {
    die('Unknown country. Use: ' . implode(', ', array_keys($countryMap)));
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, '', DB_PORT ?? 3306);
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

echo "<pre>\n=== Drop and prepare $targetDb ===\n\n";

if (!$conn->select_db($targetDb)) {
    die("Cannot use database $targetDb. Does it exist? Create it in cPanel first.\n");
}

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
