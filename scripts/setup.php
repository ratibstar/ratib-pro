<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$requiredEnv = [
    'EXTERNAL_API_TOKEN', 'WEBHOOK_SIGNING_SECRET',
    'SEC_RATE_LIMIT_IP_MAX', 'REQUEST_SIGNING_SECRET',
];

/** @return string */
$envFirst = static function (string ...$keys): string {
    foreach ($keys as $key) {
        $v = getenv($key);
        if ($v !== false && $v !== '') {
            return (string) $v;
        }
    }
    return '';
};

echo "== Production Readiness Setup ==" . PHP_EOL;
$missing = [];
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
    $legacyConfig = $root . '/includes/config.php';
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

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $dbHost !== '' ? $dbHost : '127.0.0.1',
    $dbPort !== '' ? $dbPort : '3306',
    $dbName !== '' ? $dbName : 'outratib_out'
);
$dbUser = $dbUser !== '' ? $dbUser : 'root';

$dbStatus = 'down';
$tablesStatus = 'degraded';
$missingTables = [];
$requiredTables = [
    'workflows',
    'workflow_states',
    'webhook_deliveries',
    'error_logs',
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
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
    $dbStatus = 'down';
    $tablesStatus = 'down';
    echo 'DB connection error: ' . $e->getMessage() . PHP_EOL;
}

echo 'Environment: ' . ($missing === [] ? 'ok' : 'degraded') . PHP_EOL;
if ($missing !== []) {
    echo 'Missing env vars: ' . implode(', ', $missing) . PHP_EOL;
}
echo 'Database: ' . $dbStatus . PHP_EOL;
echo 'Required tables: ' . $tablesStatus . PHP_EOL;
if ($missingTables !== []) {
    echo 'Missing tables: ' . implode(', ', $missingTables) . PHP_EOL;
}

$overall = 'ok';
if ($dbStatus === 'down' || $tablesStatus === 'down') {
    $overall = 'down';
} elseif ($missing !== [] || $missingTables !== []) {
    $overall = 'degraded';
}
echo 'Readiness: ' . strtoupper($overall) . PHP_EOL;
