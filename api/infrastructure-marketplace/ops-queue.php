<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_SLASHES);
    exit;
}

require_once dirname(__DIR__, 2) . '/modules/infrastructure-marketplace/bootstrap.php';

use Ratib\InfrastructureMarketplace\Infrastructure\DatabaseConnectionFactory;
use Ratib\InfrastructureMarketplace\Infrastructure\SchemaHelpers;
use Ratib\InfrastructureMarketplace\Provisioning\Persistence\ProvisioningJobRepository;
use Ratib\InfrastructureMarketplace\Security\ControlSecurityGuard;

ControlSecurityGuard::enforce('ops-queue', ControlSecurityGuard::TIER_CONTROL_VIEW);

try {
    $pdo = DatabaseConnectionFactory::createPdo();
    if (!SchemaHelpers::tableExists($pdo, 'ratib_infra_provisioning_jobs')) {
        http_response_code(200);
        echo json_encode([
            'ok' => true,
            'depth' => 0,
            'status_counts' => new \stdClass(),
            'recent' => [],
            'degraded' => true,
            'message' => 'Table ratib_infra_provisioning_jobs is missing. Run modules/infrastructure-marketplace/Migrations/002_operational_layer.sql (and later) on the infrastructure database.',
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
    $repo = new ProvisioningJobRepository($pdo);
    echo json_encode([
        'ok' => true,
        'depth' => $repo->queueDepth(),
        'status_counts' => $repo->statusCounts(),
        'recent' => $repo->recentJobs(100),
    ], JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Queue inspection unavailable'], JSON_UNESCAPED_SLASHES);
}

