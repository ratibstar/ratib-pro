<?php
declare(strict_types=1);

use App\Services\WorkerReadService;

[$container, $externalApi] = require dirname(__DIR__) . '/bootstrap.php';

try {
    $externalApi->enforce('workers.read');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        throw new RuntimeException('Method not allowed', 405);
    }
    $workerId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    /** @var WorkerReadService $svc */
    $svc = $container->get(WorkerReadService::class);
    $data = $svc->getById($workerId);

    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    $code = (int) $e->getCode();
    if (!in_array($code, [400, 401, 403, 405, 429], true)) {
        $code = 422;
    }
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
