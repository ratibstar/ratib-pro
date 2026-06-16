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

use RATEB\InfrastructureMarketplace\Config\ModuleConfig;
use RATEB\InfrastructureMarketplace\Infrastructure\DatabaseConnectionFactory;
use RATEB\InfrastructureMarketplace\Provisioning\Persistence\ProvisioningJobRepository;
use RATEB\InfrastructureMarketplace\Security\ControlSecurityGuard;

try {
    $body = json_decode((string) (file_get_contents('php://input') ?: '{}'), true);
    if (!is_array($body)) {
        throw new RuntimeException('Invalid payload');
    }
    ControlSecurityGuard::enforce('recovery-drill', ControlSecurityGuard::TIER_CONTROL_WRITE, [
        'json_body' => $body,
        'require_csrf' => true,
    ]);
    if (!ModuleConfig::dryRunMode()) {
        throw new RuntimeException('Recovery drills require dry-run mode.');
    }
    $scenario = (string) ($body['scenario'] ?? '');
    $pdo = DatabaseConnectionFactory::createPdo();
    $repo = new ProvisioningJobRepository($pdo);

    $result = ['scenario' => $scenario, 'label' => 'DRILL_ONLY', 'destructive' => false];
    if ($scenario === 'dead_letter_recovery_test') {
        $publicId = (string) ($body['public_id'] ?? '');
        $result['requeue_result'] = $publicId !== '' ? $repo->requeueDeadLetter($publicId) : false;
    } elseif ($scenario === 'queue_recovery_simulation') {
        $result['stuck_jobs_detected'] = $repo->recoverExpiredLocks(ModuleConfig::workerLockTtlSeconds());
    } elseif ($scenario === 'dry_run_replay') {
        $publicId = (string) ($body['public_id'] ?? '');
        $result['replay_result'] = $publicId !== '' ? $repo->replayFromAnyState($publicId) : false;
    } else {
        $result['status'] = 'unsupported_scenario';
    }

    echo json_encode(['ok' => true, 'result' => $result], JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Recovery drill failed'], JSON_UNESCAPED_SLASHES);
}

