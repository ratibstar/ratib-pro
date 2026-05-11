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

use Ratib\InfrastructureMarketplace\Infrastructure\DatabaseConnectionFactory;
use Ratib\InfrastructureMarketplace\Provisioning\Persistence\ProvisioningJobLogRepository;

try {
    $publicId = (string) ($_GET['public_id'] ?? '');
    if ($publicId === '') {
        throw new RuntimeException('public_id required');
    }
    $pdo = DatabaseConnectionFactory::createPdo();
    $logs = new ProvisioningJobLogRepository($pdo);
    echo json_encode(['ok' => true, 'trace' => $logs->byJobPublicId($publicId, 200)], JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Trace unavailable'], JSON_UNESCAPED_SLASHES);
}

