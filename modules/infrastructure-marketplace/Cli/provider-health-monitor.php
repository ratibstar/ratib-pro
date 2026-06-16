<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use RATEB\InfrastructureMarketplace\Infrastructure\DatabaseConnectionFactory;
use RATEB\InfrastructureMarketplace\Providers\Health\ProviderHealthMonitor;

$tenantId = null;
$agencyId = null;
foreach (array_slice($argv ?? [], 1) as $arg) {
    if (strpos((string) $arg, '--tenant=') === 0) {
        $tenantId = (int) substr((string) $arg, 9);
    } elseif (strpos((string) $arg, '--agency=') === 0) {
        $agencyId = (int) substr((string) $arg, 9);
    }
}

try {
    $pdo = DatabaseConnectionFactory::createPdo();
    $payload = (new ProviderHealthMonitor($pdo))->run($tenantId, $agencyId);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
} catch (\Throwable $e) {
    echo json_encode([
        'ok' => false,
        'message' => 'provider_health_monitor_failed',
        'error' => substr($e->getMessage(), 0, 220),
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(1);
}
