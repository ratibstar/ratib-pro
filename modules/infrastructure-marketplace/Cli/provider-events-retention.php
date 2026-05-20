<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use Ratib\InfrastructureMarketplace\Infrastructure\DatabaseConnectionFactory;
use Ratib\InfrastructureMarketplace\Observability\ProviderEventsRetention;

$dryRun = in_array('--dry-run', $argv ?? [], true);

try {
    $pdo = DatabaseConnectionFactory::createPdo();
    $result = (new ProviderEventsRetention($pdo))->run($dryRun);
    echo json_encode([
        'ok' => true,
        'dry_run' => $dryRun,
        'deleted' => $result,
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
} catch (\Throwable $e) {
    echo json_encode([
        'ok' => false,
        'dry_run' => $dryRun,
        'message' => substr($e->getMessage(), 0, 220),
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(1);
}
