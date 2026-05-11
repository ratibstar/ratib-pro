<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

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

use Ratib\InfrastructureMarketplace\Audit\InfrastructureAuditLogger;
use Ratib\InfrastructureMarketplace\Domain\TenantContext;
use Ratib\InfrastructureMarketplace\Events\InfrastructureEventEmitter;
use Ratib\InfrastructureMarketplace\Infrastructure\DatabaseConnectionFactory;
use Ratib\InfrastructureMarketplace\Lifecycle\InfrastructureServiceLifecycleManager;
use Ratib\InfrastructureMarketplace\Services\ProvisioningOrchestrator;

try {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw ?: '{}', true);
    if (!is_array($input)) {
        throw new RuntimeException('Invalid JSON');
    }
    $pdo = DatabaseConnectionFactory::createPdo();
    $events = new InfrastructureEventEmitter();
    $manager = new InfrastructureServiceLifecycleManager(
        ProvisioningOrchestrator::createFromPdo($pdo),
        new InfrastructureAuditLogger($pdo, $events)
    );
    $tenant = new TenantContext(
        isset($input['tenant_id']) ? (int) $input['tenant_id'] : null,
        isset($input['agency_id']) ? (int) $input['agency_id'] : null
    );
    $action = (string) ($input['action'] ?? '');
    $service = is_array($input['service'] ?? null) ? $input['service'] : [];
    $actor = (string) ($input['actor'] ?? 'api');
    echo json_encode($manager->dispatchAction($action, $tenant, $service, $actor), JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Lifecycle action dispatch failed'], JSON_UNESCAPED_SLASHES);
}

