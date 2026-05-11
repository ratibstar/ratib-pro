<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

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
use Ratib\InfrastructureMarketplace\Observability\InfrastructureMetrics;
use Ratib\InfrastructureMarketplace\Providers\Activation\ProviderActivationRegistry;
use Ratib\InfrastructureMarketplace\Providers\Capabilities\CapabilityDiscoveryService;
use Ratib\InfrastructureMarketplace\Providers\Health\ProviderHealthService;

try {
    $pdo = DatabaseConnectionFactory::createPdo();
    $tenantId = isset($_GET['tenant_id']) ? (int) $_GET['tenant_id'] : null;
    $agencyId = isset($_GET['agency_id']) ? (int) $_GET['agency_id'] : null;
    $activation = new ProviderActivationRegistry($pdo);
    $metrics = new InfrastructureMetrics(new InfrastructureEventEmitter());
    $health = new ProviderHealthService($activation, $metrics);
    $discovery = new CapabilityDiscoveryService($activation);
    echo json_encode([
        'ok' => true,
        'health' => $health->healthSnapshot($tenantId, $agencyId),
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

