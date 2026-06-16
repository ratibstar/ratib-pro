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

use RATEB\InfrastructureMarketplace\Events\InfrastructureEventEmitter;
use RATEB\InfrastructureMarketplace\Infrastructure\DatabaseConnectionFactory;
use RATEB\InfrastructureMarketplace\Infrastructure\SchemaHelpers;
use RATEB\InfrastructureMarketplace\Observability\InfrastructureAlertingService;
use RATEB\InfrastructureMarketplace\Observability\InfrastructureMetrics;
use RATEB\InfrastructureMarketplace\Providers\Activation\ProviderActivationRegistry;
use RATEB\InfrastructureMarketplace\Providers\Capabilities\CapabilityDiscoveryService;
use RATEB\InfrastructureMarketplace\Providers\Health\ProviderHealthService;
use RATEB\InfrastructureMarketplace\Security\ControlSecurityGuard;

try {
    ControlSecurityGuard::enforce('providers', ControlSecurityGuard::TIER_CONTROL_VIEW);

    $pdo = DatabaseConnectionFactory::createPdo();
    if (!SchemaHelpers::tableExists($pdo, 'rateb_infra_provider_activations')) {
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
            'message' => 'Table rateb_infra_provider_activations is missing. Run modules/infrastructure-marketplace/Migrations/005_provider_activation_marketplace.sql on the infrastructure database.',
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
    try {
        $snapshot = $health->healthSnapshot($tenantId, $agencyId);
    } catch (\Throwable $e) {
        $snapshot = [
            ['provider_type' => 'hosting', 'status' => 'unavailable', 'active_count' => 0, 'error' => 'health_snapshot_failed'],
            ['provider_type' => 'registrar', 'status' => 'unavailable', 'active_count' => 0, 'error' => 'health_snapshot_failed'],
            ['provider_type' => 'dns', 'status' => 'unavailable', 'active_count' => 0, 'error' => 'health_snapshot_failed'],
            ['provider_type' => 'ssl', 'status' => 'unavailable', 'active_count' => 0, 'error' => 'health_snapshot_failed'],
        ];
    }
    $capabilities = ['hosting' => [], 'registrar' => [], 'dns' => [], 'ssl' => []];
    foreach (['hosting', 'registrar', 'dns', 'ssl'] as $role) {
        try {
            $capabilities[$role] = $discovery->discover($role, $tenantId, $agencyId);
        } catch (\Throwable $e) {
            $capabilities[$role] = [];
        }
    }
    $payload = [
        'ok' => true,
        'health' => $snapshot,
        'capabilities' => $capabilities,
    ];
    $jsonFlags = JSON_UNESCAPED_SLASHES;
    if (\defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $jsonFlags |= \JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $json = json_encode($payload, $jsonFlags);
    if (!\is_string($json)) {
        throw new \RuntimeException('providers_json_encode_failed');
    }
    echo $json;

    // Non-blocking: alerting must not fail the snapshot response (emitEvent / control DB).
    try {
        foreach ($snapshot as $row) {
            if (\is_array($row) && ($row['status'] ?? '') === 'unavailable') {
                $alerts->providerOutage((string) ($row['provider_type'] ?? 'unknown'));
            }
        }
    } catch (\Throwable $e) {
        // ignore
    }
} catch (\Throwable $e) {
    $hint = $e->getMessage();
    if (strlen($hint) > 220) {
        $hint = substr($hint, 0, 220) . '…';
    }
    http_response_code(200);
    echo json_encode([
        'ok' => false,
        'health' => [],
        'capabilities' => (object) [],
        'message' => 'Provider snapshot unavailable. Check infra DB connection and that rateb_infra_provider_activations exists on the same database PHP uses.',
        'error_class' => \get_class($e),
        'error_detail' => $hint,
    ], JSON_UNESCAPED_SLASHES);
}

