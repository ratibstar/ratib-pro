<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$requiredEnv = [
    'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS',
    'EXTERNAL_API_TOKEN', 'WEBHOOK_SIGNING_SECRET',
    'SEC_RATE_LIMIT_IP_MAX', 'REQUEST_SIGNING_SECRET',
];

echo "== Production Readiness Setup ==" . PHP_EOL;
$missing = [];
foreach ($requiredEnv as $name) {
    $v = getenv($name);
    if ($v === false || $v === '') {
        $missing[] = $name;
    }
}

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    getenv('DB_HOST') ?: '127.0.0.1',
    getenv('DB_PORT') ?: '3306',
    getenv('DB_NAME') ?: 'outratib_out'
);
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';

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
