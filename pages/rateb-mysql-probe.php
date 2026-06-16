<?php
declare(strict_types=1);
/**
 * Diagnose admin_rateb MySQL access (password vs grants vs stale control_agencies.db_pass).
 *
 * CLI: php pages/rateb-mysql-probe.php
 * DELETE after use.
 */
header('Content-Type: text/plain; charset=utf-8');

if (PHP_SAPI === 'cli') {
    $_SERVER['HTTP_HOST'] = 'rateb.sa';
    $_GET['control'] = '1';
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/control_lookup_conn.php';

$host = defined('DB_HOST') ? (string) DB_HOST : 'localhost';
$port = defined('DB_PORT') ? (int) DB_PORT : 3306;
$user = defined('DB_USER') ? (string) DB_USER : '';
$pass = defined('DB_PASS') ? (string) DB_PASS : '';
$cpDb = defined('CONTROL_PANEL_DB_NAME') ? (string) CONTROL_PANEL_DB_NAME : '';

$lines = [];
$lines[] = 'RATEB MySQL probe';
$lines[] = 'DB_USER=' . $user;
$lines[] = 'DB_HOST=' . $host . ' port=' . $port;
$lines[] = 'DB_PASS length=' . strlen($pass) . (strlen($pass) > 0 ? ' (set)' : ' (EMPTY — fix .env)');
$lines[] = 'CONTROL_PANEL_DB_NAME=' . $cpDb;
$lines[] = str_repeat('-', 60);

$tryConnect = static function (string $h, string $u, string $p, string $db, int $portNum): array {
    $m = @new mysqli($h, $u, $p, $db, $portNum);
    if ($m && !$m->connect_error) {
        $m->close();
        return ['ok' => true, 'msg' => 'OK'];
    }
    $err = $m ? $m->connect_error : 'connect failed';
    if ($m) {
        $m->close();
    }
    return ['ok' => false, 'msg' => $err];
};

foreach (['localhost', '127.0.0.1'] as $h) {
    $r = $tryConnect($h, $user, $pass, $cpDb, $port);
    $lines[] = "control_panel {$h}: " . ($r['ok'] ? 'OK' : 'FAIL — ' . $r['msg']);
}

$ctrl = function_exists('get_control_lookup_conn') ? get_control_lookup_conn() : null;
if ($ctrl instanceof mysqli) {
    $res = $ctrl->query(
        "SELECT DISTINCT db_name, db_user, LENGTH(COALESCE(db_pass,'')) AS pass_len "
        . "FROM control_agencies WHERE TRIM(COALESCE(db_name,'')) <> '' ORDER BY db_name"
    );
    if ($res) {
        $lines[] = '';
        $lines[] = 'control_agencies credentials:';
        while ($row = $res->fetch_assoc()) {
            $pl = (int) ($row['pass_len'] ?? 0);
            $lines[] = '  ' . $row['db_name'] . ' user=' . $row['db_user']
                . ' stored_db_pass_len=' . $pl
                . ($pl > 0 ? ' (STALE? run CLEAR_CONTROL_AGENCIES_DB_PASS.sql)' : ' (empty OK)');
        }
    }
}

$countryDbs = [
    'admin_bangladesh', 'admin_ethiopia', 'admin_genia', 'admin_indonesia', 'admin_kenya',
    'admin_nepal', 'admin_nigeria', 'admin_philippines', 'admin_rwanda', 'admin_sri_lanka',
    'admin_thailand', 'admin_uganda',
];

$lines[] = '';
$lines[] = 'Country DB tests (.env password only):';
foreach ($countryDbs as $db) {
    $r1 = $tryConnect($host, $user, $pass, $db, $port);
    $r2 = $tryConnect($host === 'localhost' ? '127.0.0.1' : 'localhost', $user, $pass, $db, $port);
    $lines[] = $db . ':';
    $lines[] = '  ' . $host . ': ' . ($r1['ok'] ? 'OK' : $r1['msg']);
    $lines[] = '  alt host: ' . ($r2['ok'] ? 'OK' : $r2['msg']);
}

$lines[] = '';
$lines[] = 'If control_panel OK but all countries FAIL with (using password: YES):';
$lines[] = '  → .env DB_PASS does not match DirectAdmin password for admin_rateb';
$lines[] = '  → DirectAdmin → MySQL → Users → admin_rateb → change password → update .env';
$lines[] = '';
$lines[] = 'If countries FAIL with "to database admin_xxx":';
$lines[] = '  → grants missing; DirectAdmin → admin_rateb → Full access each country DB';
$lines[] = '';
$lines[] = 'If stored_db_pass_len > 0:';
$lines[] = '  → run control-panel/CLEAR_CONTROL_AGENCIES_DB_PASS.sql';

echo implode("\n", $lines) . "\n";
