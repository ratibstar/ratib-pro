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

use RATEB\InfrastructureMarketplace\Audit\Deployment\DeploymentAuditReporter;
use RATEB\InfrastructureMarketplace\Infrastructure\DatabaseConnectionFactory;
use RATEB\InfrastructureMarketplace\Infrastructure\SchemaHelpers;
use RATEB\InfrastructureMarketplace\Security\ControlSecurityGuard;

ControlSecurityGuard::enforce('deployment-audit', ControlSecurityGuard::TIER_CONTROL_VIEW);

try {
    $pdo = DatabaseConnectionFactory::createPdo();
    if (!SchemaHelpers::tableExists($pdo, 'rateb_infra_deployment_audits')) {
        http_response_code(200);
        echo json_encode([
            'ok' => true,
            'rows' => [],
            'degraded' => true,
            'message' => 'Table rateb_infra_deployment_audits is missing. Run modules/infrastructure-marketplace/Migrations/006_release_safety.sql on the infrastructure database.',
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
    $rows = (new DeploymentAuditReporter($pdo))->latest(30);
    echo json_encode(['ok' => true, 'rows' => $rows], JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Deployment audit unavailable'], JSON_UNESCAPED_SLASHES);
}

