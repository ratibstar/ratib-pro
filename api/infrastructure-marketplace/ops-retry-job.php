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

use RATEB\InfrastructureMarketplace\Compliance\AdminActionHistory;
use RATEB\InfrastructureMarketplace\Audit\InfrastructureAuditLogger;
use RATEB\InfrastructureMarketplace\Events\InfrastructureEventEmitter;
use RATEB\InfrastructureMarketplace\Infrastructure\DatabaseConnectionFactory;
use RATEB\InfrastructureMarketplace\Provisioning\Persistence\ProvisioningJobRepository;
use RATEB\InfrastructureMarketplace\Security\ControlSecurityGuard;

try {
    $payload = json_decode((string) (file_get_contents('php://input') ?: '{}'), true);
    if (!is_array($payload)) {
        throw new RuntimeException('Invalid payload');
    }
    ControlSecurityGuard::enforce('ops-retry-job', ControlSecurityGuard::TIER_CONTROL_WRITE, [
        'json_body' => $payload,
        'require_csrf' => true,
    ]);
    $publicId = (string) ($payload['public_id'] ?? '');
    if ($publicId === '') {
        throw new RuntimeException('public_id required');
    }
    $pdo = DatabaseConnectionFactory::createPdo();
    $repo = new ProvisioningJobRepository($pdo);
    $ok = $repo->requeueDeadLetter($publicId);
    if ($ok) {
        (new AdminActionHistory(new InfrastructureAuditLogger($pdo, new InfrastructureEventEmitter())))
            ->record((string) ($payload['actor'] ?? 'admin'), 'retry_dead_letter_job', ['public_id' => $publicId]);
    }
    echo json_encode(['ok' => $ok], JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Retry operation failed'], JSON_UNESCAPED_SLASHES);
}

