<?php
/**
 * EN: Handles configuration/runtime setup behavior in `config/run_option_a_setup.php`.
 * AR: يدير سلوك إعدادات النظام وتهيئة التشغيل في `config/run_option_a_setup.php`.
 */
/**
 * OPTION A - Automated setup: DELETE + UPDATE for all country databases
 *
 * Run from browser: https://out.ratib.sa/config/run_option_a_setup.php
 * Or from CLI: php config/run_option_a_setup.php
 *
 * BEFORE: Ensure outratib_out user has privileges on ALL country databases (cPanel).
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load config (use your main domain)
if (php_sapi_name() === 'cli') {
    $_SERVER['HTTP_HOST'] = 'out.ratib.sa';
}
require_once __DIR__ . '/env/load.php';

if (!defined('DB_HOST') || !defined('DB_NAME')) {
    die('Config not loaded. Check config/env.');
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT ?? 3306);
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die('Main DB connection failed: ' . $conn->connect_error);
}

echo "<pre>\n";
echo "=== OPTION A - Country DB Setup ===\n\n";

// 1. Get control_countries
$countries = [];
$r = $conn->query("SELECT id, name, slug FROM control_countries ORDER BY id");
if (!$r) {
    die("control_countries table not found or empty.\n");
}
while ($row = $r->fetch_assoc()) {
    $countries[] = $row;
}
$r->free();

echo "Found " . count($countries) . " countries:\n";
foreach ($countries as $c) {
    echo "  - id={$c['id']} {$c['name']} ({$c['slug']})\n";
}
echo "\n";

// 2. Get control_agencies with DB credentials
$agencies = [];
$r = $conn->query("SELECT id, country_id, name, db_host, db_port, db_user, db_pass, db_name FROM control_agencies WHERE is_active = 1");
if (!$r) {
    die("control_agencies table not found.\n");
}
while ($row = $r->fetch_assoc()) {
    $agencies[] = $row;
}
$r->free();

// 3. Map country_id -> database (use main DB credentials for all)
$countryDbs = [
    1 => 'outratib_bangladesh',
    2 => 'outratib_ethiopia',
    3 => 'outratib_indonesia',
    4 => 'outratib_kenya',
    5 => 'outratib_nepal',
    6 => 'outratib_nigeria',
    7 => 'outratib_philippines',
    8 => 'outratib_rwanda',
    9 => 'outratib_sri_lanka',
    10 => 'outratib_thailand',
    11 => 'outratib_uganda',
];

// 4. Run DELETE in each country database
echo "--- STEP 1: DELETE FROM control_agencies WHERE country_id != X ---\n";
$dbHost = DB_HOST;
$dbPort = (int)(defined('DB_PORT') ? DB_PORT : 3306);
$dbUser = DB_USER;
$dbPass = DB_PASS;

foreach ($countries as $c) {
    $cid = (int)$c['id'];
    $dbName = $countryDbs[$cid] ?? null;
    if (!$dbName) {
        echo "  [SKIP] {$c['name']} (id=$cid) - no DB mapping\n";
        continue;
    }
    if ($dbName === DB_NAME) {
        echo "  [SKIP] {$c['name']} - same as main DB\n";
        continue;
    }
    try {
        $dbConn = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
        if ($dbConn->connect_error) {
            echo "  [FAIL] {$c['name']} ($dbName): " . $dbConn->connect_error . "\n";
            continue;
        }
        $dbConn->set_charset('utf8mb4');
        $stmt = $dbConn->prepare("DELETE FROM control_agencies WHERE country_id != ?");
        $stmt->bind_param('i', $cid);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        $dbConn->close();
        echo "  [OK] {$c['name']} ($dbName): deleted $affected rows\n";
    } catch (Throwable $e) {
        echo "  [FAIL] {$c['name']} ($dbName): " . $e->getMessage() . "\n";
    }
}

// 5. UPDATE control_agencies in main DB
echo "\n--- STEP 2: UPDATE control_agencies in main DB ---\n";
foreach ($countries as $c) {
    $cid = (int)$c['id'];
    $dbName = $countryDbs[$cid] ?? null;
    if (!$dbName) continue;
    $stmt = $conn->prepare("UPDATE control_agencies SET db_name = ?, db_user = ? WHERE country_id = ?");
    $stmt->bind_param('ssi', $dbName, $dbUser, $cid);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    echo "  [OK] {$c['name']}: db_name=$dbName ($affected rows updated)\n";
}

$conn->close();
echo "\n=== Done ===\n";
echo "</pre>\n";
