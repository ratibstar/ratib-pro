<?php
declare(strict_types=1);

/**
 * Check all Rateb databases (Pro, ERP, Control Panel, Contact Center).
 * Used by control-panel API and scripts/check-all-databases.php (CLI).
 */
function control_check_all_databases_env(string $key, string $default = ''): string
{
    if (defined($key)) {
        $v = constant($key);
        if (is_string($v) && $v !== '') {
            return $v;
        }
    }
    $v = getenv($key);
    return ($v !== false && $v !== '') ? (string) $v : $default;
}

/** @return array{0:string,1:string} */
function control_check_all_databases_creds(string $userKey = 'DB_USER', string $passKey = 'DB_PASS'): array
{
    $user = control_check_all_databases_env($userKey, '');
    if ($user === '') {
        $user = control_check_all_databases_env('CONTROL_DB_USER', 'admin_rateb');
    }
    $pass = control_check_all_databases_env($passKey, '');
    if ($pass === '') {
        $pass = control_check_all_databases_env('CONTROL_DB_PASS', '');
    }
    return [$user, $pass];
}

function control_check_all_databases_pdo(string $host, int $port, string $db, string $user, string $pass): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $db);
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

/** @return array{rateb:int,rcc:int,all:int} */
function control_check_all_databases_counts(PDO $pdo): array
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

function control_check_all_databases_has_table(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SHOW TABLES LIKE :t');
    $stmt->execute(['t' => $table]);
    return $stmt->fetch() !== false;
}

function control_check_all_databases_row_count(PDO $pdo, string $table): ?int
{
    if (!control_check_all_databases_has_table($pdo, $table)) {
        return null;
    }
    $safe = str_replace('`', '``', $table);
    $stmt = $pdo->query('SELECT COUNT(*) FROM `' . $safe . '`');
    return $stmt ? (int) $stmt->fetchColumn() : null;
}

/** @param array<string,mixed> $expect */
function control_check_all_databases_evaluate(array $counts, array $expect, PDO $pdo): array
{
    $issues = [];
    $ok = [];

    if (isset($expect['rateb_min']) && $counts['rateb'] < (int) $expect['rateb_min']) {
        $issues[] = 'rateb_* tables too few (have ' . $counts['rateb'] . ', need >=' . $expect['rateb_min'] . ')';
    } elseif (isset($expect['rateb_min'])) {
        $ok[] = 'rateb_* count OK (' . $counts['rateb'] . ')';
    }

    if (isset($expect['rateb_max']) && $counts['rateb'] > (int) $expect['rateb_max']) {
        $issues[] = 'rateb_* pollution (have ' . $counts['rateb'] . ', max ' . $expect['rateb_max'] . ')';
    } elseif (isset($expect['rateb_max'])) {
        $ok[] = 'no rateb_* pollution';
    }

    if (isset($expect['rcc_min']) && $counts['rcc'] < (int) $expect['rcc_min']) {
        $issues[] = 'rcc_* tables too few (have ' . $counts['rcc'] . ', need >=' . $expect['rcc_min'] . ')';
    } elseif (isset($expect['rcc_min'])) {
        $ok[] = 'rcc_* count OK (' . $counts['rcc'] . ')';
    }

    if (isset($expect['rcc_max']) && $counts['rcc'] > (int) $expect['rcc_max']) {
        $issues[] = 'rcc_* pollution (have ' . $counts['rcc'] . ', max ' . $expect['rcc_max'] . ')';
    } elseif (isset($expect['rcc_max'])) {
        $ok[] = 'no rcc_* pollution';
    }

    foreach ($expect['required'] ?? [] as $table) {
        if (!control_check_all_databases_has_table($pdo, (string) $table)) {
            $issues[] = 'missing table: ' . $table;
        } else {
            $ok[] = 'has ' . $table;
        }
    }

    $migTable = (string) ($expect['migration_table'] ?? '');
    if ($migTable !== '' && control_check_all_databases_has_table($pdo, $migTable)) {
        $n = control_check_all_databases_row_count($pdo, $migTable);
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

/** @return array{0:string,1:bool} report text and all-pass flag */
function control_check_all_databases_run(): array
{
    [$defaultUser, $defaultPass] = control_check_all_databases_creds();
    $host = control_check_all_databases_env('DB_HOST', control_check_all_databases_env('CONTROL_DB_HOST', '127.0.0.1'));
    $port = (int) control_check_all_databases_env('DB_PORT', control_check_all_databases_env('CONTROL_DB_PORT', '3306'));

    $targets = [
        [
            'label' => 'RATEB Pro (main site)',
            'db' => control_check_all_databases_env('RATEB_PRO_DB_NAME', control_check_all_databases_env('DB_NAME', 'admin_rateb')),
            'user' => $defaultUser,
            'pass' => $defaultPass,
            // Main Pro DB legitimately uses many rateb_* tables (CMS, orders, etc.).
            'expect' => ['rcc_max' => 0, 'required' => []],
        ],
        [
            'label' => 'RATEB ERP',
            'db' => control_check_all_databases_env('RATEB_ERP_DB_NAME', 'admin_rateb-erp'),
            'user' => control_check_all_databases_env('RATEB_ERP_DB_USER', $defaultUser),
            'pass' => control_check_all_databases_env('RATEB_ERP_DB_PASS', $defaultPass),
            'expect' => [
                'rateb_min' => 40,
                'rcc_max' => 0,
                'required' => ['rateb_companies', 'rateb_permissions', 'rateb_roles', 'rateb_migrations', 'rateb_users'],
                'migration_table' => 'rateb_migrations',
                'migration_col' => 'filename',
            ],
        ],
        [
            'label' => 'Control Panel',
            'db' => control_check_all_databases_env('CONTROL_PANEL_DB_NAME', 'admin_control_panel_db'),
            'user' => control_check_all_databases_env('CONTROL_DB_USER', $defaultUser),
            'pass' => control_check_all_databases_env('CONTROL_DB_PASS', $defaultPass),
            'expect' => ['rateb_max' => 0, 'rcc_max' => 0, 'required' => []],
        ],
        [
            'label' => 'Contact Center (RCC)',
            'db' => control_check_all_databases_env('RATIB_CC_DB_NAME', 'admin_call-center'),
            'user' => control_check_all_databases_env('RATIB_CC_DB_USER', 'admin_call-center'),
            'pass' => control_check_all_databases_env('RATIB_CC_DB_PASS', ''),
            'expect' => [
                'rcc_min' => 70,
                'rateb_max' => 0,
                'required' => ['rcc_tenants', 'rcc_users', 'rcc_agents', 'rcc_queues', 'rcc_tickets', 'rcc_migration_log'],
                'migration_table' => 'rcc_migration_log',
                'migration_col' => 'migration',
            ],
        ],
    ];

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
            $pdo = control_check_all_databases_pdo($host, $port, $t['db'], $t['user'], $t['pass']);
            $counts = control_check_all_databases_counts($pdo);
            $eval = control_check_all_databases_evaluate($counts, $t['expect'], $pdo);

            $lines[] = 'Connection: OK';
            $lines[] = 'Tables: total=' . $counts['all'] . '  rateb_*=' . $counts['rateb'] . '  rcc_*=' . $counts['rcc'];

            if (control_check_all_databases_has_table($pdo, 'rateb_permissions')) {
                $lines[] = 'rateb_permissions rows: ' . (control_check_all_databases_row_count($pdo, 'rateb_permissions') ?? 0);
            }
            if (control_check_all_databases_has_table($pdo, 'rateb_roles')) {
                $lines[] = 'rateb_roles rows: ' . (control_check_all_databases_row_count($pdo, 'rateb_roles') ?? 0);
            }

            $migTable = (string) ($t['expect']['migration_table'] ?? '');
            $migCol = (string) ($t['expect']['migration_col'] ?? '');
            if ($migTable !== '' && $migCol !== '' && control_check_all_databases_has_table($pdo, $migTable)) {
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
    $lines[] = '  ERP  -> Control Panel -> RATEB ERP -> Database Setup -> Run migrations';
    $lines[] = '  RCC  -> Control Panel -> Contact Center -> Database Setup -> Run migrations';
    $lines[] = '  CP pollution -> drop rateb_* / rcc_* from admin_control_panel_db (backup first)';

    return [implode(PHP_EOL, $lines) . PHP_EOL, $allPass];
}

/** @return list<string> */
function control_cleanup_cp_rateb_list_tables(): array
{
    $db = control_check_all_databases_env('CONTROL_PANEL_DB_NAME', 'admin_control_panel_db');
    $erpDb = control_check_all_databases_env('RATEB_ERP_DB_NAME', 'admin_rateb-erp');
    if ($db === '' || $db === $erpDb) {
        throw new RuntimeException('Refusing cleanup: control panel DB name is invalid or matches ERP DB.');
    }

    [$user, $pass] = control_check_all_databases_creds('CONTROL_DB_USER', 'CONTROL_DB_PASS');
    $host = control_check_all_databases_env('DB_HOST', control_check_all_databases_env('CONTROL_DB_HOST', '127.0.0.1'));
    $port = (int) control_check_all_databases_env('DB_PORT', control_check_all_databases_env('CONTROL_DB_PORT', '3306'));

    $pdo = control_check_all_databases_pdo($host, $port, $db, $user, $pass);
    $stmt = $pdo->query(
        "SELECT table_name FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name LIKE 'rateb\\_%'
         ORDER BY table_name"
    );
    $tables = [];
    if ($stmt) {
        while ($row = $stmt->fetch()) {
            $tables[] = (string) ($row['table_name'] ?? '');
        }
    }
    return array_values(array_filter($tables));
}

/**
 * Drop legacy rateb_* tables from control panel DB only (ERP lives on admin_rateb-erp).
 *
 * @return array{0:string,1:int} log text, tables dropped
 */
function control_cleanup_cp_rateb_pollution(bool $execute = false): array
{
    $db = control_check_all_databases_env('CONTROL_PANEL_DB_NAME', 'admin_control_panel_db');
    $tables = control_cleanup_cp_rateb_list_tables();
    $lines = [];
    $lines[] = 'Control Panel DB cleanup — rateb_* pollution';
    $lines[] = 'Database: ' . $db;
    $lines[] = 'Found ' . count($tables) . ' rateb_* table(s).';
    $lines[] = '';

    if ($tables === []) {
        $lines[] = 'Nothing to do — control panel DB is clean.';
        return [implode(PHP_EOL, $lines) . PHP_EOL, 0];
    }

    foreach ($tables as $table) {
        $lines[] = '  - ' . $table;
    }
    $lines[] = '';

    if (!$execute) {
        $lines[] = 'DRY RUN — no tables dropped.';
        $lines[] = 'To execute, POST with confirm=DROP_CP_RATEB to:';
        $lines[] = '  /control-panel/api/control/cleanup-cp-rateb-tables.php?control=1';
        return [implode(PHP_EOL, $lines) . PHP_EOL, 0];
    }

    [$user, $pass] = control_check_all_databases_creds('CONTROL_DB_USER', 'CONTROL_DB_PASS');
    $host = control_check_all_databases_env('DB_HOST', control_check_all_databases_env('CONTROL_DB_HOST', '127.0.0.1'));
    $port = (int) control_check_all_databases_env('DB_PORT', control_check_all_databases_env('CONTROL_DB_PORT', '3306'));
    $pdo = control_check_all_databases_pdo($host, $port, $db, $user, $pass);
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $dropped = 0;
    foreach ($tables as $table) {
        $safe = str_replace('`', '``', $table);
        $pdo->exec('DROP TABLE IF EXISTS `' . $safe . '`');
        $lines[] = 'Dropped: ' . $table;
        $dropped++;
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    $lines[] = '';
    $lines[] = 'Done. Dropped ' . $dropped . ' table(s). Re-run check-all-databases to verify.';

    return [implode(PHP_EOL, $lines) . PHP_EOL, $dropped];
}
