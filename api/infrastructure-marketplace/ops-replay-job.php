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
use Ratib\InfrastructureMarketplace\Provisioning\Persistence\ProvisioningJobRepository;

try {
    $payload = json_decode((string) (file_get_contents('php://input') ?: '{}'), true);
    if (!is_array($payload)) {
        throw new RuntimeException('Invalid payload');
    }
    $publicId = (string) ($payload['public_id'] ?? '');
    if ($publicId === '') {
        throw new RuntimeException('public_id required');
    }
    $pdo = DatabaseConnectionFactory::createPdo();
    $repo = new ProvisioningJobRepository($pdo);
    $ok = $repo->replayFromAnyState($publicId);
    if ($ok) {
        (new AdminActionHistory(new InfrastructureAuditLogger($pdo, new InfrastructureEventEmitter())))
            ->record((string) ($payload['actor'] ?? 'admin'), 'replay_provisioning_job', ['public_id' => $publicId]);
    }
    echo json_encode(['ok' => $ok], JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Replay operation failed'], JSON_UNESCAPED_SLASHES);
}

