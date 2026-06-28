<?php
declare(strict_types=1);

/**
 * Apply pending ERP migrations (focus: 136–140) — local DB or remote HTTP.
 *
 * Local:
 *   php bin/run-pending-migrations-cli.php
 *
 * Production (token in env):
 *   set RATEB_ERP_MIGRATE_TOKEN=...
 *   php bin/run-pending-migrations-cli.php --remote https://rateb.sa
 */
define('RATEB_ROOT', dirname(__DIR__));
define('RATEB_ENV_NO_SESSION', true);
define('RATEB_MIGRATE_ALLOWED', true);

$remote = null;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--remote' && isset($argv[2])) {
        $remote = rtrim((string) $argv[2], '/');
        break;
    }
    if (str_starts_with($arg, '--remote=')) {
        $remote = rtrim(substr($arg, 9), '/');
        break;
    }
}
if ($remote === null && in_array('--remote', $argv, true)) {
    $remote = 'https://rateb.sa';
}

$targets = [
    '136_hr_job_titles.sql',
    '137_hr_leave_type_codes.sql',
    '138_hr_leave_types_full_catalog.sql',
    '138_permissions_complete_audit.sql',
    '139_hr_job_title_rank_codes.sql',
    '140_zatca_invoices_catchup.sql',
];

if ($remote !== null) {
    $token = getenv('RATEB_ERP_MIGRATE_TOKEN') ?: getenv('CPANEL_API_TOKEN') ?: '';
    if ($token === '') {
        fwrite(STDERR, "Missing RATEB_ERP_MIGRATE_TOKEN (or CPANEL_API_TOKEN)\n");
        exit(1);
    }
    $paths = [
        '/rateb-erp/public/run-migrations.php',
        '/control-panel/api/control/rateb-erp-migrate-run.php',
    ];
    $ok = false;
    foreach ($paths as $path) {
        $url = $remote . $path;
        echo "POST $url\n";
        $ch = curl_init($url);
        if ($ch === false) {
            continue;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 180,
            CURLOPT_HTTPHEADER => [
                'X-Rateb-Migrate-Token: ' . $token,
                'Cache-Control: no-cache',
            ],
        ]);
        $body = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        echo "HTTP $code\n$body\n";
        $lines = array_filter(array_map('trim', explode("\n", $body)));
        $last = $lines !== [] ? end($lines) : '';
        if ($last === 'OK' || str_contains($body, 'Applied ') || str_contains($body, 'Done: 140_')) {
            $ok = true;
            break;
        }
        if ($code === 403 || $code === 404) {
            continue;
        }
    }
    exit($ok ? 0 : 1);
}

require RATEB_ROOT . '/config/app.php';
require RATEB_ROOT . '/app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init(RATEB_ROOT);

echo 'Database: ' . (defined('RATEB_DB_NAME') ? RATEB_DB_NAME : '?') . PHP_EOL;

$pdo = Rateb\App\Core\Database::connection();
$stmt = $pdo->prepare('SELECT id FROM rateb_migrations WHERE filename = :f LIMIT 1');

echo PHP_EOL . '=== Before ===' . PHP_EOL;
foreach ($targets as $f) {
    $stmt->execute(['f' => $f]);
    echo (($stmt->fetch() ? '[x]' : '[ ]') . ' ' . $f) . PHP_EOL;
}

echo PHP_EOL . '=== Running MigrationService::runAll() ===' . PHP_EOL;
foreach ((new Rateb\App\Services\MigrationService())->runAll() as $line) {
    echo $line . PHP_EOL;
}

echo PHP_EOL . '=== After ===' . PHP_EOL;
foreach ($targets as $f) {
    $stmt->execute(['f' => $f]);
    echo (($stmt->fetch() ? '[x]' : '[ ]') . ' ' . $f) . PHP_EOL;
}

echo PHP_EOL . '=== Schema checks ===' . PHP_EOL;
$checks = [
    'rateb_hr_job_titles' => "SHOW TABLES LIKE 'rateb_hr_job_titles'",
    'employees.job_title_id' => "SHOW COLUMNS FROM rateb_employees LIKE 'job_title_id'",
    'leave_types.code' => "SHOW COLUMNS FROM rateb_leave_types LIKE 'code'",
    'rateb_company_tax_profiles' => "SHOW TABLES LIKE 'rateb_company_tax_profiles'",
    'invoices.zatca_uuid' => "SHOW COLUMNS FROM rateb_invoices LIKE 'zatca_uuid'",
];
foreach ($checks as $label => $sql) {
    try {
        $row = $pdo->query($sql)->fetch();
        echo ($row ? 'OK' : 'MISSING') . ' — ' . $label . PHP_EOL;
    } catch (Throwable $e) {
        echo 'ERR — ' . $label . ': ' . $e->getMessage() . PHP_EOL;
    }
}
