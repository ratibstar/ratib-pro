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

use Ratib\InfrastructureMarketplace\Compliance\AdminActionHistory;
use Ratib\InfrastructureMarketplace\Audit\InfrastructureAuditLogger;
use Ratib\InfrastructureMarketplace\Events\InfrastructureEventEmitter;
use Ratib\InfrastructureMarketplace\Infrastructure\DatabaseConnectionFactory;
use Ratib\InfrastructureMarketplace\Providers\Activation\ProviderActivationRegistry;

try {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw ?: '{}', true);
    if (!is_array($input)) {
        throw new RuntimeException('Invalid payload');
    }
    $pdo = DatabaseConnectionFactory::createPdo();
    $actor = (string) ($input['actor'] ?? 'admin');
    $registry = new ProviderActivationRegistry($pdo);
    $registry->upsertActivation(
        (string) ($input['provider_type'] ?? 'hosting'),
        (string) ($input['provider_code'] ?? ''),
        (string) ($input['provider_class'] ?? ''),
        isset($input['tenant_id']) ? (int) $input['tenant_id'] : null,
        isset($input['agency_id']) ? (int) $input['agency_id'] : null,
        (int) ($input['priority_weight'] ?? 100),
        (bool) ($input['is_enabled'] ?? true),
        $actor
    );
    (new AdminActionHistory(new InfrastructureAuditLogger($pdo, new InfrastructureEventEmitter())))
        ->record($actor, 'provider_activation_upsert', [
            'provider_type' => (string) ($input['provider_type'] ?? 'hosting'),
            'provider_code' => (string) ($input['provider_code'] ?? ''),
        ]);

    echo json_encode(['ok' => true], JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Provider activation update failed'], JSON_UNESCAPED_SLASHES);
}

