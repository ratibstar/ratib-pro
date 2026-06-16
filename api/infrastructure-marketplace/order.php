<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_SLASHES);
    exit;
}

require_once dirname(__DIR__, 2) . '/modules/infrastructure-marketplace/bootstrap.php';

use RATEB\InfrastructureMarketplace\Audit\InfrastructureAuditLogger;
use RATEB\InfrastructureMarketplace\Events\InfrastructureEventEmitter;
use RATEB\InfrastructureMarketplace\Infrastructure\DatabaseConnectionFactory;
use RATEB\InfrastructureMarketplace\Ordering\InfrastructureOrderService;
use RATEB\InfrastructureMarketplace\Security\ControlSecurityGuard;
use RATEB\InfrastructureMarketplace\Services\ProviderRegistry;
use RATEB\InfrastructureMarketplace\Services\ProvisioningOrchestrator;

ControlSecurityGuard::enforce('order', ControlSecurityGuard::TIER_PUBLIC_MUTATOR);

try {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw ?: '{}', true);
    if (!is_array($input)) {
        throw new RuntimeException('Invalid JSON');
    }
    $pdo = DatabaseConnectionFactory::createPdo();
    $tenantId = isset($input['tenant_id']) ? (int) $input['tenant_id'] : null;
    $agencyId = isset($input['agency_id']) ? (int) $input['agency_id'] : null;
    $events = new InfrastructureEventEmitter();
    $orchestrator = ProvisioningOrchestrator::createFromPdo($pdo);
    $service = new InfrastructureOrderService(
        $pdo,
        $orchestrator,
        ProviderRegistry::fromEnvironmentOrActivationTable($pdo, $tenantId, $agencyId),
        $events,
        new InfrastructureAuditLogger($pdo, $events)
    );
    echo json_encode($service->place($input), JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Unable to place infrastructure order'], JSON_UNESCAPED_SLASHES);
}

