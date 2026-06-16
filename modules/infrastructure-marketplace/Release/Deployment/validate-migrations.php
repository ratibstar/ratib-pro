<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use RATEB\InfrastructureMarketplace\Infrastructure\DatabaseConnectionFactory;
use RATEB\InfrastructureMarketplace\Verification\MigrationVerifier;

$pdo = DatabaseConnectionFactory::createPdo();
$result = (new MigrationVerifier($pdo))->verify();
echo json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit(($result['status'] ?? 'FAIL') === 'PASS' ? 0 : 2);

