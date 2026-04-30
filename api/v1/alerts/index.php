<?php
declare(strict_types=1);

use App\Services\AlertQueryService;

[$container, $externalApi] = require dirname(__DIR__) . '/bootstrap.php';

try {
    $externalApi->enforce('alerts.read');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        throw new RuntimeException('Method not allowed', 405);
    }
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
    /** @var AlertQueryService $svc */
    $svc = $container->get(AlertQueryService::class);
    $data = $svc->listRecent($limit);

    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    $code = (int) $e->getCode();
    if (!in_array($code, [400, 401, 403, 405, 429], true)) {
        $code = 422;
    }
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
