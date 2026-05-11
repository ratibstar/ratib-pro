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

try {
    $pdo = DatabaseConnectionFactory::createPdo();
    $service = new PrelaunchHealthService($pdo);
    echo json_encode(['ok' => true, 'report' => $service->run()], JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'report' => [
            'status' => 'FAIL',
            'matrix' => ['PASS' => 0, 'WARN' => 0, 'FAIL' => 1],
            'recommendations' => ['Fix DB/environment bootstrap before launch checks.'],
        ],
    ], JSON_UNESCAPED_SLASHES);
}

