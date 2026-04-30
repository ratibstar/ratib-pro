<?php
declare(strict_types=1);

use App\Core\Application;
use App\Core\Autoloader;
use App\Core\SystemHealth;

$root = dirname(__DIR__);
require_once $root . '/app/Core/Autoloader.php';
Autoloader::register($root . DIRECTORY_SEPARATOR . 'app');

$report = [
    'status' => 'down',
    'timestamp' => gmdate('c'),
    'checks' => [
        'database' => ['status' => 'down'],
        'workflows' => ['status' => 'degraded'],
        'metrics' => ['status' => 'degraded'],
        'event_system' => ['status' => 'down'],
    ],
];

try {
    $config = require $root . '/config/worker_tracking.php';
    $container = Application::boot($config);
    $health = $container->get(SystemHealth::class)->snapshot();
    $eventDispatcherOk = true;
    try {
        $container->get(\App\Core\EventDispatcher::class);
    } catch (Throwable) {
        $eventDispatcherOk = false;
    }

    $report['status'] = (string) ($health['status'] ?? 'degraded');
    $report['checks']['database'] = $health['checks']['database'] ?? ['status' => 'down'];
    $report['checks']['workflows'] = $health['checks']['workflows'] ?? ['status' => 'degraded'];
    $report['checks']['metrics'] = $health['checks']['metrics'] ?? ['status' => 'degraded'];
    $report['checks']['event_system'] = ['status' => $eventDispatcherOk ? 'ok' : 'degraded'];
} catch (Throwable $e) {
    $report['checks']['database'] = ['status' => 'down', 'message' => $e->getMessage()];
}

echo "== System Health Check ==" . PHP_EOL;
foreach ($report['checks'] as $name => $check) {
    $status = strtoupper((string) ($check['status'] ?? 'unknown'));
    echo sprintf('- %s: %s', $name, $status) . PHP_EOL;
}
echo PHP_EOL . json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
