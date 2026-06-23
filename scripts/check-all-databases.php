#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Check all Rateb databases: table counts, pollution, core tables, migration logs.
 *
 * CLI (SSH / DirectAdmin Terminal):
 *   php scripts/check-all-databases.php
 *
 * Browser (control-panel admin session):
 *   https://rateb.sa/scripts/check-all-databases.php?control=1
 *   (must be logged into Control Panel in the same browser)
 */
$projectRoot = dirname(__DIR__);
$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    define('IS_CONTROL_PANEL', true);
    $cpConfig = $projectRoot . '/control-panel/includes/config.php';
    if (!is_file($cpConfig)) {
        http_response_code(500);
        exit('Control Panel config missing.');
    }
    require_once $cpConfig;
    if (empty($_SESSION['control_logged_in'])) {
        http_response_code(403);
        exit('Login to Control Panel first, then open this URL again.');
    }
    header('Content-Type: text/plain; charset=utf-8');
} else {
    $cpEnv = $projectRoot . '/control-panel/config/env.php';
    if (is_file($cpEnv)) {
        require_once $cpEnv;
    } else {
        require_once $projectRoot . '/config/env/load.php';
    }
}

$env = static function (string $key, string $default = ''): string {
    if (defined($key)) {
        $v = constant($key);
        if (is_string($v) && $v !== '') {
            return $v;
        }
    }
    $v = getenv($key);
    return ($v !== false && $v !== '') ? (string) $v : $default;
};

$host = $env('DB_HOST', $env('CONTROL_DB_HOST', '127.0.0.1'));
$port = (int) $env('DB_PORT', $env('CONTROL_DB_PORT', '3306'));

/** @return array{0:string,1:string} */
$dbCreds = static function (string $userKey = 'DB_USER', string $passKey = 'DB_PASS') use ($env): array {
    $user = $env($userKey, '');
    if ($user === '') {
        $user = $env('CONTROL_DB_USER', 'admin_rateb');
    }
    $pass = $env($passKey, '');
    if ($pass === '') {
        $pass = $env('CONTROL_DB_PASS', '');
    }
    return [$user, $pass];
};

/** @return list<array{key:string,label:string,db:string,user:string,pass:string,expect:array<string,mixed>}> */
$targets = [
    [
        'key' => 'pro',
        'label' => 'RATEB Pro (main site)',
        'db' => $env('RATEB_PRO_DB_NAME', $env('DB_NAME', 'admin_rateb')),
        'user' => $dbCreds()[0],
        'pass' => $dbCreds()[1],
        'expect' => [
            'rateb_max' => 5,
            'rcc_max' => 0,
            'required' => [],
        ],
    ],
    [
        'key' => 'erp',
        'label' => 'RATEB ERP',
        'db' => $env('RATEB_ERP_DB_NAME', 'admin_rateb-erp'),
        'user' => $env('RATEB_ERP_DB_USER', $dbCreds()[0]),
        'pass' => $env('RATEB_ERP_DB_PASS', $dbCreds()[1]),
        'expect' => [
            'rateb_min' => 40,
            'rcc_max' => 0,
            'required' => [
                'rateb_companies',
                'rateb_permissions',
                'rateb_roles',
                'rateb_migrations',
                'rateb_users',
            ],
            'migration_table' => 'rateb_migrations',
            'migration_col' => 'filename',
        ],
    ],
    [
        'key' => 'cp',
        'label' => 'Control Panel',
        'db' => $env('CONTROL_PANEL_DB_NAME', 'admin_control_panel_db'),
        'user' => $env('CONTROL_DB_USER', $dbCreds()[0]),
        'pass' => $env('CONTROL_DB_PASS', $dbCreds()[1]),
        'expect' => [
            'rateb_max' => 0,
            'rcc_max' => 0,
            'required' => [],
        ],
    ],
    [
        'key' => 'rcc',
        'label' => 'Contact Center (RCC)',
        'db' => $env('RATIB_CC_DB_NAME', 'admin_call-center'),
        'user' => $env('RATIB_CC_DB_USER', 'admin_call-center'),
        'pass' => $env('RATIB_CC_DB_PASS', ''),
        'expect' => [
            'rcc_min' => 70,
            'rateb_max' => 0,
            'required' => [
                'rcc_tenants',
                'rcc_users',
                'rcc_agents',
                'rcc_queues',
                'rcc_tickets',
                'rcc_migration_log',
            ],
            'migration_table' => 'rcc_migration_log',
            'migration_col' => 'migration',
        ],
    ],
];

function db_connect(string $host, int $port, string $db, string $user, string $pass): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $db);
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

/** @return array{rateb:int,rcc:int,all:int} */
function table_counts(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT
            SUM(table_name LIKE 'rateb\\_%') AS rateb_cnt,
            SUM(table_name LIKE 'rcc\\_%') AS rcc_cnt,
            COUNT(*) AS all_cnt
         FROM information_schema.tables
         WHERE table_schema = DATABASE()"
    );
    $row = $stmt ? $stmt->fetch() : false;
    return [
        'rateb' => (int) ($row['rateb_cnt'] ?? 0),
        'rcc' => (int) ($row['rcc_cnt'] ?? 0),
        'all' => (int) ($row['all_cnt'] ?? 0),
    ];
}

function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SHOW TABLES LIKE :t');
    $stmt->execute(['t' => $table]);
    return $stmt->fetch() !== false;
}

function row_count(PDO $pdo, string $table): ?int
{
    if (!table_exists($pdo, $table)) {
        return null;
    }
    $safe = str_replace('`', '``', $table);
    $stmt = $pdo->query('SELECT COUNT(*) FROM `' . $safe . '`');
    return $stmt ? (int) $stmt->fetchColumn() : null;
}

/** @param array<string,mixed> $expect */
function evaluate_checks(array $counts, array $expect, PDO $pdo): array
{
    $issues = [];
    $ok = [];

    if (isset($expect['rateb_min']) && $counts['rateb'] < (int) $expect['rateb_min']) {
        $issues[] = 'rateb_* tables too few (have ' . $counts['rateb'] . ', need ≥' . $expect['rateb_min'] . ')';
    } else {
        if (isset($expect['rateb_min'])) {
            $ok[] = 'rateb_* count OK (' . $counts['rateb'] . ')';
        }
    }

    if (isset($expect['rateb_max']) && $counts['rateb'] > (int) $expect['rateb_max']) {
        $issues[] = 'rateb_* pollution (have ' . $counts['rateb'] . ', max ' . $expect['rateb_max'] . ')';
    } elseif (isset($expect['rateb_max'])) {
        $ok[] = 'no rateb_* pollution';
    }

    if (isset($expect['rcc_min']) && $counts['rcc'] < (int) $expect['rcc_min']) {
        $issues[] = 'rcc_* tables too few (have ' . $counts['rcc'] . ', need ≥' . $expect['rcc_min'] . ')';
    } elseif (isset($expect['rcc_min'])) {
        $ok[] = 'rcc_* count OK (' . $counts['rcc'] . ')';
    }

    if (isset($expect['rcc_max']) && $counts['rcc'] > (int) $expect['rcc_max']) {
        $issues[] = 'rcc_* pollution (have ' . $counts['rcc'] . ', max ' . $expect['rcc_max'] . ')';
    } elseif (isset($expect['rcc_max'])) {
        $ok[] = 'no rcc_* pollution';
    }

    foreach ($expect['required'] ?? [] as $table) {
        if (!table_exists($pdo, (string) $table)) {
            $issues[] = 'missing table: ' . $table;
        } else {
            $ok[] = 'has ' . $table;
        }
    }

  $migTable = (string) ($expect['migration_table'] ?? '');
    if ($migTable !== '' && table_exists($pdo, $migTable)) {
        $n = row_count($pdo, $migTable);
        if ($n === 0) {
            $issues[] = $migTable . ' is empty';
        } else {
            $ok[] = $migTable . ' rows: ' . $n;
        }
    } elseif ($migTable !== '') {
        $issues[] = 'missing ' . $migTable;
    }

    return ['ok' => $ok, 'issues' => $issues, 'pass' => $issues === []];
}

$lines = [];
$lines[] = 'RATEB — All databases check';
$lines[] = 'Generated: ' . gmdate('Y-m-d H:i:s') . ' UTC';
$lines[] = str_repeat('=', 60);

$allPass = true;

foreach ($targets as $t) {
    $lines[] = '';
    $lines[] = str_repeat('-', 60);
    $lines[] = $t['label'];
    $lines[] = 'Database: ' . $t['db'];
    $lines[] = 'User:     ' . $t['user'];

    try {
        $pdo = db_connect($host, $port, $t['db'], $t['user'], $t['pass']);
        $counts = table_counts($pdo);
        $eval = evaluate_checks($counts, $t['expect'], $pdo);

        $lines[] = 'Connection: OK';
        $lines[] = 'Tables: total=' . $counts['all'] . '  rateb_*=' . $counts['rateb'] . '  rcc_*=' . $counts['rcc'];

        if (table_exists($pdo, 'rateb_permissions')) {
            $lines[] = 'rateb_permissions rows: ' . (row_count($pdo, 'rateb_permissions') ?? 0);
        }
        if (table_exists($pdo, 'rateb_roles')) {
            $lines[] = 'rateb_roles rows: ' . (row_count($pdo, 'rateb_roles') ?? 0);
        }

        $migTable = (string) ($t['expect']['migration_table'] ?? '');
        $migCol = (string) ($t['expect']['migration_col'] ?? '');
        if ($migTable !== '' && $migCol !== '' && table_exists($pdo, $migTable)) {
            $safeTable = str_replace('`', '``', $migTable);
            $safeCol = str_replace('`', '``', $migCol);
            $stmt = $pdo->query(
                'SELECT `' . $safeCol . '` AS name FROM `' . $safeTable . '` ORDER BY id DESC LIMIT 3'
            );
            $recent = [];
            if ($stmt) {
                while ($row = $stmt->fetch()) {
                    $recent[] = (string) ($row['name'] ?? '');
                }
            }
            if ($recent !== []) {
                $lines[] = 'Latest migrations: ' . implode(', ', $recent);
            }
        }

        foreach ($eval['ok'] as $msg) {
            $lines[] = '  [OK] ' . $msg;
        }
        foreach ($eval['issues'] as $msg) {
            $lines[] = '  [!!] ' . $msg;
            $allPass = false;
        }
        $lines[] = 'Status: ' . ($eval['pass'] ? 'PASS' : 'FAIL');
    } catch (Throwable $e) {
        $allPass = false;
        $lines[] = 'Connection: FAIL';
        $lines[] = 'Error: ' . $e->getMessage();
        $lines[] = 'Status: FAIL';
    }
}

$lines[] = '';
$lines[] = str_repeat('=', 60);
$lines[] = 'OVERALL: ' . ($allPass ? 'ALL PASS' : 'ISSUES FOUND — see [!!] lines above');
$lines[] = '';
$lines[] = 'Fix hints:';
$lines[] = '  ERP  → Control Panel → RATEB ERP → Database Setup → Run migrations';
$lines[] = '  RCC  → Control Panel → Contact Center → Database Setup → Run migrations';
$lines[] = '  CP pollution → drop rateb_* / rcc_* from admin_control_panel_db (backup first)';

echo implode(PHP_EOL, $lines) . PHP_EOL;
if ($isCli) {
    exit($allPass ? 0 : 1);
}
exit(0);
