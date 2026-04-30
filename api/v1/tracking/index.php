<?php
declare(strict_types=1);

use App\Controllers\Http\TrackingController;

[$container, $externalApi] = require dirname(__DIR__) . '/bootstrap.php';

try {
    $externalApi->enforce('tracking.move');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        throw new RuntimeException('Method not allowed', 405);
    }
    $payload = json_decode((string) file_get_contents('php://input'), true) ?? [];
    if (!is_array($payload)) {
        $payload = [];
    }
    /** @var TrackingController $controller */
    $controller = $container->get(TrackingController::class);
    $data = $controller->move($payload);

    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    $code = (int) $e->getCode();
    if (!in_array($code, [400, 401, 403, 405, 429], true)) {
        $code = 422;
    }
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
