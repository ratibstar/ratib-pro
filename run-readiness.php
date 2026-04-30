<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

register_shutdown_function(static function (): void {
    $e = error_get_last();
    if (is_array($e)) {
        echo "\n[FATAL] {$e['message']} in {$e['file']}:{$e['line']}\n";
    }
});

echo "<pre>";

$token = $_GET['token'] ?? '';
$expected = 'ratib-readiness-2026';
if (!hash_equals($expected, (string) $token)) {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

$project = '/home/outratib/public_html';
if (!is_dir($project)) {
    echo "Project path not found: {$project}\n";
    echo "Current dir: " . __DIR__ . "\n";
    exit;
}
chdir($project);

echo "PWD: " . getcwd() . "\n";
echo "scripts exists: " . (is_dir('scripts') ? 'yes' : 'no') . "\n";
echo "tests exists: " . (is_dir('tests') ? 'yes' : 'no') . "\n\n";
echo "disable_functions: " . (string) ini_get('disable_functions') . "\n\n";

putenv('EXTERNAL_API_TOKEN=placeholder');
putenv('WEBHOOK_SIGNING_SECRET=placeholder');
putenv('SEC_RATE_LIMIT_IP_MAX=120');
putenv('REQUEST_SIGNING_SECRET=placeholder');

echo "== setup ==\n";
$envFirst = static function (string ...$keys): string {
    foreach ($keys as $key) {
        $v = getenv($key);
        if ($v !== false && $v !== '') {
            return (string) $v;
        }
    }
    return '';
};
$missing = [];
$requiredEnv = ['EXTERNAL_API_TOKEN', 'WEBHOOK_SIGNING_SECRET', 'SEC_RATE_LIMIT_IP_MAX', 'REQUEST_SIGNING_SECRET'];
foreach ($requiredEnv as $name) {
    $v = getenv($name);
    if (($v === false || $v === '') && (!defined($name) || (string) constant($name) === '')) {
        $missing[] = $name;
    }
}
$dbHost = $envFirst('DB_HOST');
$dbPort = $envFirst('DB_PORT');
$dbName = $envFirst('DB_NAME', 'DB_DATABASE');
$dbUser = $envFirst('DB_USER', 'DB_USERNAME');
$dbPass = $envFirst('DB_PASS', 'DB_PASSWORD');
if ($dbHost === '' || $dbPort === '' || $dbName === '' || $dbUser === '') {
    $legacyConfig = $project . '/control-panel/config/env.php';
    if (is_file($legacyConfig)) {
        require_once $legacyConfig;
        $dbHost = $dbHost !== '' ? $dbHost : (defined('DB_HOST') ? (string) DB_HOST : '');
        $dbPort = $dbPort !== '' ? $dbPort : (defined('DB_PORT') ? (string) DB_PORT : '3306');
        if ($dbName === '') {
            if (defined('RATIB_PRO_DB_NAME') && (string) RATIB_PRO_DB_NAME !== '') {
                $dbName = (string) RATIB_PRO_DB_NAME;
            } elseif (defined('DB_NAME')) {
                $dbName = (string) DB_NAME;
            }
        }
        $dbUser = $dbUser !== '' ? $dbUser : (defined('DB_USER') ? (string) DB_USER : '');
        $dbPass = $dbPass !== '' ? $dbPass : (defined('DB_PASS') ? (string) DB_PASS : '');
    }
}
if ($dbHost === '') {
    $missing[] = 'DB_HOST';
}
if ($dbPort === '') {
    $missing[] = 'DB_PORT';
}
if ($dbName === '') {
    $missing[] = 'DB_NAME|DB_DATABASE';
}
if ($dbUser === '') {
    $missing[] = 'DB_USER|DB_USERNAME';
}
$dbStatus = 'down';
$tablesStatus = 'degraded';
$missingTables = [];
$requiredTables = ['workflows', 'workflow_states', 'webhook_deliveries', 'error_logs'];
try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $dbHost !== '' ? $dbHost : '127.0.0.1',
        $dbPort !== '' ? $dbPort : '3306',
        $dbName !== '' ? $dbName : 'outratib_out'
    );
    $pdo = new PDO($dsn, $dbUser !== '' ? $dbUser : 'root', $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $dbStatus = 'ok';
    foreach ($requiredTables as $table) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
        $st->execute([':table' => $table]);
        if ((int) $st->fetchColumn() <= 0) {
            $missingTables[] = $table;
        }
    }
    $tablesStatus = $missingTables === [] ? 'ok' : 'degraded';
} catch (Throwable $e) {
    $tablesStatus = 'down';
    echo "DB connection error: " . $e->getMessage() . "\n";
}
echo 'Environment: ' . ($missing === [] ? 'ok' : 'degraded') . "\n";
if ($missing !== []) {
    echo 'Missing env vars: ' . implode(', ', $missing) . "\n";
}
echo 'Database: ' . $dbStatus . "\n";
echo 'Required tables: ' . $tablesStatus . "\n";
if ($missingTables !== []) {
    echo 'Missing tables: ' . implode(', ', $missingTables) . "\n";
}

echo "\n== health-check ==\n";
try {
    require_once $project . '/app/Core/Autoloader.php';
    \App\Core\Autoloader::register($project . DIRECTORY_SEPARATOR . 'app');
    $config = require $project . '/config/worker_tracking.php';
    $container = \App\Core\Application::boot($config);
    $health = $container->get(\App\Core\SystemHealth::class)->snapshot();
    echo 'status: ' . (string) ($health['status'] ?? 'unknown') . "\n";
    echo json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable $e) {
    echo 'health-check error: ' . $e->getMessage() . "\n";
}

echo "\n== tests ==\n";
try {
    require_once $project . '/tests/bootstrap.php';
    $testFiles = [
        ...glob($project . '/tests/Unit/*Test.php') ?: [],
        ...glob($project . '/tests/Workflow/*Test.php') ?: [],
        ...glob($project . '/tests/Integration/*Test.php') ?: [],
    ];
    sort($testFiles);
    $results = ['passed' => 0, 'failed' => 0, 'skipped' => 0];
    foreach ($testFiles as $file) {
        $tests = require $file;
        if (!is_array($tests)) {
            continue;
        }
        foreach ($tests as $name => $fn) {
            try {
                $fn();
                $results['passed']++;
                echo "[PASS] {$name}\n";
            } catch (TestSkippedException $e) {
                $results['skipped']++;
                echo "[SKIP] {$name}\n";
            } catch (Throwable $e) {
                $results['failed']++;
                echo "[FAIL] {$name} => " . $e->getMessage() . "\n";
            }
        }
    }
    $total = $results['passed'] + $results['failed'] + $results['skipped'];
    echo "Total: {$total}, Passed: {$results['passed']}, Failed: {$results['failed']}, Skipped: {$results['skipped']}\n";
} catch (Throwable $e) {
    echo 'tests error: ' . $e->getMessage() . "\n";
}

echo "</pre>";
