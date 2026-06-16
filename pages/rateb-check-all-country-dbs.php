<?php
declare(strict_types=1);
/**
 * Audit every country tenant DB (from control_agencies + known admin_* list).
 *
 * CLI (recommended on server):
 *   php pages/rateb-check-all-country-dbs.php
 *
 * Browser (after deploy):
 *   https://rateb.sa/pages/rateb-check-all-country-dbs.php?run=1&token=YOUR_DEPLOY_TOKEN
 *   (same value as RATEB_ERP_MIGRATE_TOKEN / GitHub deploy secret)
 *
 * DELETE this file when finished auditing.
 */
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$cli = (PHP_SAPI === 'cli');
if ($cli) {
    // In CLI there is no HTTP host, so force rateb.sa env resolution.
    $_SERVER['HTTP_HOST'] = 'rateb.sa';
    $_GET['control'] = '1';
}
if (!$cli && (!isset($_GET['run']) || (string) $_GET['run'] !== '1')) {
    http_response_code(403);
    exit("Forbidden. Use ?run=1&token=... or run: php pages/rateb-check-all-country-dbs.php\n");
}

if (!$cli) {
    $provided = trim((string) ($_GET['token'] ?? $_SERVER['HTTP_X_RATEB_MIGRATE_TOKEN'] ?? ''));
    $expected = getenv('RATEB_ERP_MIGRATE_TOKEN') ?: '';
    if ($expected === '' && defined('RATEB_ERP_MIGRATE_TOKEN')) {
        $expected = (string) RATEB_ERP_MIGRATE_TOKEN;
    }
    if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
        http_response_code(403);
        exit("Forbidden — pass ?token= (deploy token) or X-Rateb-Migrate-Token header.\n");
    }
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/control_lookup_conn.php';
require_once __DIR__ . '/../config/env/directadmin_db.php';
require_once __DIR__ . '/../control-panel/api/control/agency-db-helper.php';

$lines = [];
$lines[] = 'RATEB — all country database audit';
$lines[] = 'time=' . gmdate('Y-m-d H:i:s') . ' UTC';
$lines[] = 'CONTROL_PANEL_DB_NAME=' . (defined('CONTROL_PANEL_DB_NAME') ? CONTROL_PANEL_DB_NAME : '?');
$lines[] = 'DB_USER=' . (defined('DB_USER') ? DB_USER : '?');
$lines[] = str_repeat('-', 72);

$ctrl = function_exists('get_control_lookup_conn') ? get_control_lookup_conn() : null;
if (!$ctrl instanceof mysqli) {
    $lines[] = 'FAIL: cannot connect to control panel DB';
    echo implode("\n", $lines) . "\n";
    exit(1);
}

/** @var array<string, array{country:string,agencies:list<string>,db_host:string,db_port:int,db_user:string,db_pass:string,site_url:string}> */
$targets = [];

$res = $ctrl->query(
    'SELECT a.name AS agency_name, a.db_host, a.db_port, a.db_user, a.db_pass, a.db_name, a.site_url, a.is_active, '
    . 'c.name AS country_name, c.slug AS country_slug '
    . 'FROM control_agencies a '
    . 'LEFT JOIN control_countries c ON c.id = a.country_id '
    . 'WHERE TRIM(COALESCE(a.db_name, "")) <> "" '
    . 'ORDER BY c.name, a.name'
);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $db = trim((string) ($row['db_name'] ?? ''));
        if ($db === '') {
            continue;
        }
        if (!isset($targets[$db])) {
            $targets[$db] = [
                'country' => trim((string) ($row['country_name'] ?? '')),
                'country_slug' => trim((string) ($row['country_slug'] ?? '')),
                'agencies' => [],
                'db_host' => trim((string) ($row['db_host'] ?? '')),
                'db_port' => (int) ($row['db_port'] ?? 0),
                'db_user' => trim((string) ($row['db_user'] ?? '')),
                'db_pass' => (string) ($row['db_pass'] ?? ''),
                'site_url' => trim((string) ($row['site_url'] ?? '')),
                'active' => (int) ($row['is_active'] ?? 0),
            ];
        }
        $targets[$db]['agencies'][] = trim((string) ($row['agency_name'] ?? ''))
            . ((int) ($row['is_active'] ?? 0) ? '' : ' [inactive]');
    }
}

foreach (rateb_all_country_database_names() as $fallbackDb) {
    if (!isset($targets[$fallbackDb])) {
        $targets[$fallbackDb] = [
            'country' => '',
            'country_slug' => preg_replace('/^admin_/', '', $fallbackDb),
            'agencies' => ['(not in control_agencies)'],
            'db_host' => '',
            'db_port' => 0,
            'db_user' => '',
            'db_pass' => '',
            'site_url' => '',
            'active' => 0,
        ];
    }
}

ksort($targets);

$hostDefault = defined('DB_HOST') ? (string) DB_HOST : '127.0.0.1';
$portDefault = defined('DB_PORT') ? (int) DB_PORT : 3306;
$userDefault = defined('DB_USER') ? (string) DB_USER : '';
$passDefault = defined('DB_PASS') ? (string) DB_PASS : '';
$proBase = defined('RATEB_PRO_URL') ? rtrim((string) RATEB_PRO_URL, '/') : (defined('SITE_URL') ? rtrim((string) SITE_URL, '/') : 'https://rateb.sa');

$ok = 0;
$fail = 0;

foreach ($targets as $dbName => $meta) {
    $lines[] = '';
    $lines[] = 'DATABASE: ' . $dbName;
    $lines[] = '  country: ' . ($meta['country'] !== '' ? $meta['country'] : '(unknown)');
    $lines[] = '  agencies: ' . implode(', ', $meta['agencies']);
    $slug = $meta['country_slug'] !== '' ? $meta['country_slug'] : '';
    if ($slug !== '') {
        $lines[] = '  login_url: ' . $proBase . '/' . rawurlencode($slug) . '/login';
    }
    if ($meta['site_url'] !== '') {
        $lines[] = '  site_url: ' . $meta['site_url'];
    }

    $host = $meta['db_host'] !== '' ? $meta['db_host'] : $hostDefault;
    $port = $meta['db_port'] > 0 ? $meta['db_port'] : $portDefault;
    $user = $meta['db_user'] !== '' ? $meta['db_user'] : $userDefault;

    try {
        $agencyRow = [
            'db_host' => $host,
            'db_port' => $port,
            'db_user' => $user,
            'db_pass' => $meta['db_pass'],
            'db_name' => $dbName,
            'country_slug' => $meta['country_slug'] ?? '',
        ];
        $acct = getAgencyDbConnection($agencyRow, 0);
        if (!$acct || !isset($acct['conn']) || !($acct['conn'] instanceof mysqli)) {
            $err = function_exists('getAgencyDbConnectionLastError') ? getAgencyDbConnectionLastError() : 'Connection failed';
            $lines[] = '  connect: FAIL — ' . $err;
            if (stripos($err, 'Access denied') !== false) {
                $lines[] = '  FIX: DirectAdmin → admin_rateb → Full access on this DB; run CLEAR_CONTROL_AGENCIES_DB_PASS.sql';
            }
            $fail++;
            continue;
        }
        $tenant = $acct['conn'];
        $lines[] = '  connect: OK (' . ($acct['connect_host'] ?? $host) . ':' . ($acct['connect_port'] ?? $port) . ' as ' . ($acct['connect_user'] ?? $user) . ')';
        $ok++;

        $t = $tenant->query("SHOW TABLES LIKE 'users'");
        if (!$t || $t->num_rows === 0) {
            $lines[] = '  users table: MISSING';
            $tenant->close();
            continue;
        }
        $lines[] = '  users table: yes';

        $cnt = $tenant->query('SELECT COUNT(*) AS c FROM users');
        $userCount = ($cnt && ($cr = $cnt->fetch_assoc())) ? (int) ($cr['c'] ?? 0) : 0;
        $lines[] = '  users count: ' . $userCount;

        $adm = $tenant->query("SELECT user_id, username, email, role_id, status, is_active FROM users WHERE username = 'admin' LIMIT 1");
        if (!$adm) {
            $adm = $tenant->query("SELECT id AS user_id, username FROM users WHERE username = 'admin' LIMIT 1");
        }
        if ($adm && ($ar = $adm->fetch_assoc())) {
            $lines[] = '  admin user: yes (id=' . ($ar['user_id'] ?? $ar['id'] ?? '?') . ')';
            $pw = $tenant->query("SELECT password FROM users WHERE username = 'admin' LIMIT 1");
            if ($pw && ($pr = $pw->fetch_assoc())) {
                $hash = (string) ($pr['password'] ?? '');
                $test123456 = password_verify('123456', $hash);
                $lines[] = '  admin password 123456: ' . ($test123456 ? 'YES (test login OK)' : 'NO (run reset script)');
            }
        } else {
            $lines[] = '  admin user: MISSING — run rateb-reset-country-test-admin.php';
        }

        $sample = $tenant->query('SELECT username FROM users ORDER BY user_id ASC LIMIT 5');
        if ($sample) {
            $names = [];
            while ($sr = $sample->fetch_assoc()) {
                $names[] = (string) ($sr['username'] ?? '');
            }
            if ($names !== []) {
                $lines[] = '  sample usernames: ' . implode(', ', $names);
            }
        }

        $tenant->close();
    } catch (Throwable $e) {
        $lines[] = '  connect: FAIL — ' . $e->getMessage();
        $fail++;
    }
}

$lines[] = '';
$lines[] = str_repeat('-', 72);
$lines[] = 'SUMMARY: ' . $ok . ' connected, ' . $fail . ' failed, ' . count($targets) . ' databases checked';
$lines[] = 'phpMyAdmin: run control-panel/CHECK_ALL_COUNTRY_DBS.sql on admin_control_panel_db';

echo implode("\n", $lines) . "\n";
