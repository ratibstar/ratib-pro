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

use Ratib\InfrastructureMarketplace\Events\InfrastructureEventEmitter;
use Ratib\InfrastructureMarketplace\Infrastructure\DatabaseConnectionFactory;
use Ratib\InfrastructureMarketplace\Infrastructure\SchemaHelpers;
use Ratib\InfrastructureMarketplace\Observability\InfrastructureAlertingService;
use Ratib\InfrastructureMarketplace\Observability\InfrastructureMetrics;
use Ratib\InfrastructureMarketplace\Providers\Activation\ProviderActivationRegistry;
use Ratib\InfrastructureMarketplace\Providers\Capabilities\CapabilityDiscoveryService;
use Ratib\InfrastructureMarketplace\Providers\Health\ProviderHealthService;
use Ratib\InfrastructureMarketplace\Security\ControlSecurityGuard;

ControlSecurityGuard::enforce('providers', ControlSecurityGuard::TIER_CONTROL_VIEW);

try {
    $pdo = DatabaseConnectionFactory::createPdo();
    if (!SchemaHelpers::tableExists($pdo, 'ratib_infra_provider_activations')) {
        http_response_code(200);
        $degradedHealth = [
            ['provider_type' => 'hosting', 'status' => 'unavailable', 'active_count' => 0],
            ['provider_type' => 'registrar', 'status' => 'unavailable', 'active_count' => 0],
            ['provider_type' => 'dns', 'status' => 'unavailable', 'active_count' => 0],
            ['provider_type' => 'ssl', 'status' => 'unavailable', 'active_count' => 0],
        ];
        echo json_encode([
            'ok' => true,
            'health' => $degradedHealth,
            'capabilities' => [
                'hosting' => [],
                'registrar' => [],
                'dns' => [],
                'ssl' => [],
            ],
            'degraded' => true,
            'message' => 'Table ratib_infra_provider_activations is missing. Run modules/infrastructure-marketplace/Migrations/005_provider_activation_marketplace.sql on the infrastructure database.',
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
    $tenantId = isset($_GET['tenant_id']) ? (int) $_GET['tenant_id'] : null;
    $agencyId = isset($_GET['agency_id']) ? (int) $_GET['agency_id'] : null;
    $activation = new ProviderActivationRegistry($pdo);
    $events = new InfrastructureEventEmitter();
    $metrics = new InfrastructureMetrics($events);
    $alerts = new InfrastructureAlertingService($events);
    $health = new ProviderHealthService($activation, $metrics);
    $discovery = new CapabilityDiscoveryService($activation);
    $snapshot = $health->healthSnapshot($tenantId, $agencyId);
    foreach ($snapshot as $row) {
        if (is_array($row) && ($row['status'] ?? '') === 'unavailable') {
            $alerts->providerOutage((string) ($row['provider_type'] ?? 'unknown'));
        }
    }
    echo json_encode([
        'ok' => true,
        'health' => $snapshot,
        'capabilities' => [
            'hosting' => $discovery->discover('hosting', $tenantId, $agencyId),
            'registrar' => $discovery->discover('registrar', $tenantId, $agencyId),
            'dns' => $discovery->discover('dns', $tenantId, $agencyId),
            'ssl' => $discovery->discover('ssl', $tenantId, $agencyId),
        ],
    ], JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Provider snapshot unavailable'], JSON_UNESCAPED_SLASHES);
}

