<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use RATEB\InfrastructureMarketplace\Audit\Deployment\DeploymentAuditReporter;
use RATEB\InfrastructureMarketplace\Infrastructure\DatabaseConnectionFactory;

$pdo = DatabaseConnectionFactory::createPdo();
$rows = (new DeploymentAuditReporter($pdo))->latest(50);
echo json_encode(['rows' => $rows], JSON_UNESCAPED_SLASHES) . PHP_EOL;

