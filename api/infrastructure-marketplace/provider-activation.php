<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once dirname(__DIR__, 2) . '/modules/infrastructure-marketplace/bootstrap.php';

use Ratib\InfrastructureMarketplace\Compliance\AdminActionHistory;
use Ratib\InfrastructureMarketplace\Audit\InfrastructureAuditLogger;
use Ratib\InfrastructureMarketplace\Events\InfrastructureEventEmitter;
use Ratib\InfrastructureMarketplace\Infrastructure\DatabaseConnectionFactory;
use Ratib\InfrastructureMarketplace\Infrastructure\SchemaHelpers;
use Ratib\InfrastructureMarketplace\Providers\Activation\ProviderActivationRegistry;
use Ratib\InfrastructureMarketplace\Security\ControlSecurityGuard;

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? ''));

try {
    $pdo = DatabaseConnectionFactory::createPdo();
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Database unavailable'], JSON_UNESCAPED_SLASHES);
    exit;
}

if (!SchemaHelpers::tableExists($pdo, 'ratib_infra_provider_activations')) {
    http_response_code(200);
    echo json_encode([
        'ok' => false,
        'message' => 'Table ratib_infra_provider_activations missing. Run migration 005.',
        'rows' => [],
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

$registry = new ProviderActivationRegistry($pdo);
$actor = (string) ($_SESSION['control_username'] ?? 'unknown');

if ($method === 'GET') {
    ControlSecurityGuard::enforce('provider-activation', ControlSecurityGuard::TIER_CONTROL_VIEW);
    echo json_encode(['ok' => true, 'rows' => $registry->listAll()], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_SLASHES);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw ?: '{}', true);
if (!is_array($input)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Invalid payload'], JSON_UNESCAPED_SLASHES);
    exit;
}

ControlSecurityGuard::enforce('provider-activation', ControlSecurityGuard::TIER_CONTROL_SYSTEM, [
    'json_body' => $input,
    'require_csrf' => true,
]);

$action = (string) ($input['action'] ?? 'upsert');

try {
    if ($action === 'emergency_disable') {
        $count = $registry->emergencyDisableByType(
            (string) ($input['provider_type'] ?? 'hosting'),
            $actor
        );
        (new AdminActionHistory(new InfrastructureAuditLogger($pdo, new InfrastructureEventEmitter())))
            ->record($actor, 'provider_emergency_disable', [
                'provider_type' => (string) ($input['provider_type'] ?? 'hosting'),
                'affected_rows' => $count,
            ]);
        echo json_encode(['ok' => true, 'affected_rows' => $count], JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'delete') {
        $id = isset($input['id']) ? (int) $input['id'] : 0;
        if ($id <= 0) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'id required'], JSON_UNESCAPED_SLASHES);
            exit;
        }
        $ok = $registry->deleteById($id);
        echo json_encode(['ok' => $ok, 'message' => $ok ? 'Deleted' : 'Delete failed'], JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'set_enabled') {
        $id = isset($input['id']) ? (int) $input['id'] : 0;
        $en = !empty($input['is_enabled']);
        if ($id <= 0) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'id required'], JSON_UNESCAPED_SLASHES);
            exit;
        }
        $registry->setEnabled($id, $en, $actor);
        echo json_encode(['ok' => true, 'message' => 'Updated'], JSON_UNESCAPED_SLASHES);
        exit;
    }

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
