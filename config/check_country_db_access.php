<?php
/**
 * EN: Handles configuration/runtime setup behavior in `config/check_country_db_access.php`.
 * AR: يدير سلوك إعدادات النظام وتهيئة التشغيل في `config/check_country_db_access.php`.
 */
/**
 * Check Country Database Access
 *
 * Run from browser: https://rateb.sa/config/check_country_db_access.php?control=1
 * Or from CLI: php config/check_country_db_access.php
 *
 * Tests connection to each country DB. Use this to verify grants before/after cPanel setup.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (php_sapi_name() === 'cli') {
    $_SERVER['HTTP_HOST'] = 'rateb.sa';
    $_GET['control'] = '1';
}
require_once __DIR__ . '/env/load.php';
require_once __DIR__ . '/env/directadmin_db.php';

if (!defined('DB_HOST') || !defined('DB_USER') || !defined('DB_PASS')) {
    die('Config not loaded. Check config/env.');
}

// DirectAdmin: admin_bangladesh, admin_ethiopia, … + agencies from control panel
$countryDbs = rateb_all_country_database_names();
$countryDbs[] = 'admin_genia';
try {
    require_once __DIR__ . '/../includes/control_lookup_conn.php';
    $ctrl = function_exists('get_control_lookup_conn') ? get_control_lookup_conn() : null;
    if ($ctrl instanceof mysqli) {
        $res = $ctrl->query(
            "SELECT DISTINCT TRIM(db_name) AS db_name FROM control_agencies WHERE TRIM(COALESCE(db_name, '')) <> ''"
        );
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $n = (string) ($row['db_name'] ?? '');
                if ($n !== '' && !in_array($n, $countryDbs, true)) {
                    $countryDbs[] = $n;
                }
            }
        }
    }
} catch (Throwable $e) {
    // static list only
}
sort($countryDbs);

$host = DB_HOST;
$user = DB_USER;
$pass = DB_PASS;
$port = defined('DB_PORT') ? DB_PORT : 3306;

$results = [];
$okCount = 0;

foreach ($countryDbs as $dbName) {
    $conn = @new mysqli($host, $user, $pass, $dbName, $port);
    if ($conn && !$conn->connect_error) {
        $conn->close();
        $results[$dbName] = ['ok' => true, 'msg' => 'OK'];
        $okCount++;
    } else {
        $err = $conn ? $conn->connect_error : 'Connection failed';
        if ($host === 'localhost') {
            $conn2 = @new mysqli('127.0.0.1', $user, $pass, $dbName, $port);
            if ($conn2 && !$conn2->connect_error) {
                $conn2->close();
                $results[$dbName] = ['ok' => true, 'msg' => 'OK (via 127.0.0.1)'];
                $okCount++;
            } else {
                $results[$dbName] = ['ok' => false, 'msg' => $err];
            }
        } else {
            $results[$dbName] = ['ok' => false, 'msg' => $err];
        }
    }
}

$html = php_sapi_name() !== 'cli';
if ($html) {
    header('Content-Type: text/html; charset=UTF-8');
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Country DB Access Check</title>";
    echo "<style>body{font-family:sans-serif;margin:2em;background:#1a1a2e;color:#eee;}";
    echo "table{border-collapse:collapse;width:100%;max-width:500px;}";
    echo "th,td{padding:8px 12px;text-align:left;border-bottom:1px solid #333;}";
    echo ".ok{color:#4ade80;} .fail{color:#f87171;}";
    echo "h1{color:#a78bfa;} .summary{margin:1em 0;padding:1em;background:#16213e;border-radius:8px;}</style></head><body>";
    echo "<h1>Country Database Access Check</h1>";
    echo "<p>User: <strong>{$user}</strong> @ {$host}</p>";
    echo "<div class='summary'>";
    echo "<strong>{$okCount}</strong> / " . count($countryDbs) . " databases accessible";
    if ($okCount < count($countryDbs)) {
        echo " — run <code>control-panel/GRANT_COUNTRY_DBS_ADMIN_RATEB.sql</code> or DirectAdmin MySQL Management";
        echo " (<a href='migrations/GRANT_INSTRUCTIONS.md' style='color:#60a5fa'>GRANT_INSTRUCTIONS.md</a>)";
    }
    echo "</div>";
    echo "<table><tr><th>Database</th><th>Status</th></tr>";
    foreach ($results as $db => $r) {
        $cls = $r['ok'] ? 'ok' : 'fail';
        $icon = $r['ok'] ? '✓' : '✗';
        echo "<tr><td>{$db}</td><td class='{$cls}'>{$icon} {$r['msg']}</td></tr>";
    }
    echo "</table></body></html>";
} else {
    echo "=== Country DB Access Check ===\n";
    echo "User: {$user} @ {$host}\n\n";
    echo "{$okCount} / " . count($countryDbs) . " databases accessible\n\n";
    foreach ($results as $db => $r) {
        $icon = $r['ok'] ? 'OK' : 'FAIL';
        echo sprintf("%-25s %s  %s\n", $db, $icon, $r['msg']);
    }
}
