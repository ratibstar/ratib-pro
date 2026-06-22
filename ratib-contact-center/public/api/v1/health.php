<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

use Ratib\ContactCenter\App\Core\Database;

$checks = [
    'database' => false,
    'migrations' => 0,
    'realtime_hub' => false,
    'ami_configured' => false,
    'tables' => 0,
];

try {
    $pdo = Database::connection();
    $checks['database'] = true;
    $checks['tables'] = $pdo->query("SHOW TABLES LIKE 'rcc_%'")->rowCount();
    $checks['migrations'] = (int) $pdo->query('SELECT COUNT(*) FROM rcc_migration_log')->fetchColumn();
} catch (\Throwable $e) {
    echo json_encode(['ok' => false, 'checks' => $checks, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

$port = (int) (getenv('RCC_WEBSOCKET_PORT') ?: 9702);
$fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
$checks['realtime_hub'] = is_resource($fp);
if ($fp) {
    fclose($fp);
}

$checks['ami_configured'] = (getenv('RCC_AMI_HOST') ?: '') !== '' && (getenv('RCC_AMI_PASS') ?: '') !== '';

echo json_encode([
    'ok' => $checks['database'] && $checks['tables'] >= 30,
    'service' => 'rcc-health',
    'checks' => $checks,
    'timestamp' => gmdate('c'),
], JSON_UNESCAPED_UNICODE);
