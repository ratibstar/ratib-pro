<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use Ratib\InfrastructureMarketplace\Infrastructure\DatabaseConnectionFactory;
use Ratib\InfrastructureMarketplace\Verification\MigrationVerifier;

$pdo = DatabaseConnectionFactory::createPdo();
$result = (new MigrationVerifier($pdo))->verify();
echo json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit(($result['status'] ?? 'FAIL') === 'PASS' ? 0 : 2);

