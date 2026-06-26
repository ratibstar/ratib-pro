<?php
declare(strict_types=1);

/**
 * Enterprise certification runner (server-side CLI over HTTP).
 * Auth: X-Rateb-Migrate-Token (same as run-migrations.php).
 *
 * Actions (POST):
 *   action=test          — php bin/enterprise-test/run.php --json
 *   action=seed          — requires X-Rateb-Cert-Confirm: ENTERPRISE-SEED
 *   action=backup        — php bin/erp-backup.php (summary only in JSON)
 *   action=reset-dry-run — php bin/reset-production.php --dry-run output
 *
 * Env (set before bootstrap via headers or server .env):
 *   RATEB_OFFICIAL_DEV_DB=1, RATEB_ENV=development, RATEB_ERP_DB_NAME=admin_rateb_erp
 */
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$ratebRoot = realpath(__DIR__ . '/..');
define('RATEB_ROOT', str_replace('\\', '/', $ratebRoot !== false ? $ratebRoot : dirname(__DIR__)));
define('RATEB_ENV_NO_SESSION', true);

$provided = trim((string) ($_SERVER['HTTP_X_RATEB_MIGRATE_TOKEN'] ?? ''));
if ($provided === '') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once RATEB_ROOT . '/app/Core/HealthProbeAuth.php';
if (!\Rateb\App\Core\HealthProbeAuth::verifyRequest()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid token'], JSON_UNESCAPED_UNICODE);
    exit;
}

putenv('RATEB_OFFICIAL_DEV_DB=1');
putenv('RATEB_ENV=development');
$dbOverride = trim((string) ($_SERVER['HTTP_X_RATEB_ERP_DB_NAME'] ?? ''));
if ($dbOverride !== '') {
    putenv('RATEB_ERP_DB_NAME=' . $dbOverride);
    putenv('RATEB_DB_NAME=' . $dbOverride);
}

$action = strtolower(trim((string) ($_POST['action'] ?? $_GET['action'] ?? 'test')));
$started = microtime(true);
$report = [
    'ok' => false,
    'action' => $action,
    'started_at' => date('c'),
    'database' => null,
    'result' => null,
    'errors' => [],
];

try {
    require_once RATEB_ROOT . '/app/Core/Bootstrap.php';
    \Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
    $report['database'] = \Rateb\App\Core\Database::resolvedDatabaseName();

    if ($action === 'seed') {
        $confirm = trim((string) ($_SERVER['HTTP_X_RATEB_CERT_CONFIRM'] ?? ''));
        if ($confirm !== 'ENTERPRISE-SEED') {
            http_response_code(400);
            $report['errors'][] = 'Missing X-Rateb-Cert-Confirm: ENTERPRISE-SEED';
            echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }
        putenv('RATEB_ENTERPRISE_SEED=1');
        require_once RATEB_ROOT . '/bin/enterprise-seed/guard.php';
        enterprise_seed_guard();
        require_once RATEB_ROOT . '/bin/enterprise-seed/EnterpriseSeeder.php';
        ob_start();
        $seeder = new EnterpriseSeeder();
        foreach ([
            'companies', 'branches', 'users', 'employees', 'customers',
            'warehouses', 'inventory', 'stock_movements', 'assets', 'contracts',
            'journal_entries', 'invoices',
        ] as $step) {
            $method = 'seed' . str_replace('_', '', ucwords($step, '_'));
            if (method_exists($seeder, $method)) {
                echo "==> {$step}\n";
                $seeder->$method();
            }
        }
        $report['result'] = ['output' => ob_get_clean()];
        $report['ok'] = true;
    } elseif ($action === 'test') {
        require_once RATEB_ROOT . '/bin/enterprise-seed/EnterpriseSeeder.php';
        (new EnterpriseSeeder())->backfillPrerequisites();
        require_once RATEB_ROOT . '/bin/enterprise-test/EnterpriseTestRunner.php';
        $runner = new EnterpriseTestRunner();
        $report['result'] = $runner->runAll();
        $report['ok'] = ($report['result']['failed'] ?? 1) === 0;
    } elseif ($action === 'reset-dry-run') {
        require_once RATEB_ROOT . '/bin/ProductionResetRunner.php';
        $pdo = \Rateb\App\Core\Database::connection();
        ob_start();
        $runner = new ProductionResetRunner($pdo);
        $runner->run(true);
        $report['result'] = ['dry_run_output' => ob_get_clean(), 'report' => $runner->report()];
        $report['ok'] = true;
    } elseif ($action === 'backup') {
        ob_start();
        passthru('php ' . escapeshellarg(RATEB_ROOT . '/bin/erp-backup.php') . ' 2>&1', $code);
        $report['result'] = ['exit_code' => $code, 'output' => ob_get_clean()];
        $report['ok'] = $code === 0;
    } else {
        http_response_code(400);
        $report['errors'][] = 'Unknown action';
    }
} catch (Throwable $e) {
    http_response_code(500);
    $report['errors'][] = $e->getMessage();
    $report['trace'] = $e->getFile() . ':' . $e->getLine();
}

$report['elapsed_ms'] = (int) round((microtime(true) - $started) * 1000);
$report['finished_at'] = date('c');
echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
