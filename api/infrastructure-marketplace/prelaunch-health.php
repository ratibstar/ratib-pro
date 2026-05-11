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

use Ratib\InfrastructureMarketplace\Health\PrelaunchHealthService;
use Ratib\InfrastructureMarketplace\Infrastructure\DatabaseConnectionFactory;
use Ratib\InfrastructureMarketplace\Security\ControlSecurityGuard;

ControlSecurityGuard::enforce('prelaunch-health', ControlSecurityGuard::TIER_PUBLIC_READ);

try {
    $pdo = DatabaseConnectionFactory::createPdo();
    $service = new PrelaunchHealthService($pdo);
    $report = $service->run();
    $jsonFlags = JSON_UNESCAPED_SLASHES;
    if (\defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $jsonFlags |= \JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $json = json_encode(['ok' => true, 'report' => $report], $jsonFlags);
    if (!\is_string($json)) {
        throw new \RuntimeException('prelaunch_json_encode_failed');
    }
    echo $json;
} catch (\Throwable $e) {
    // 200 so DevTools does not flag a hard failure; clients use ok + report.status.
    http_response_code(200);
    echo json_encode([
        'ok' => false,
        'report' => [
            'status' => 'FAIL',
            'score' => 0,
            'matrix' => ['PASS' => 0, 'WARN' => 0, 'FAIL' => 1],
            'sections' => [],
            'recommendations' => [
                'Fix DB connection (RATIB_INFRA_DB_DSN or RATIB_INFRA_DB_* / legacy DB_*), credentials (RATIB_INFRA_DB_USER if needed), and apply infra migrations 002+ on the target database.',
            ],
        ],
    ], JSON_UNESCAPED_SLASHES);
}

