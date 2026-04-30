<?php
declare(strict_types=1);

use App\Controllers\Http\WorkflowController;
use App\Controllers\Http\WorkflowTimelineController;

[$container, $externalApi] = require dirname(__DIR__) . '/bootstrap.php';

try {
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'GET') {
        $externalApi->enforce('workflow.timeline.view');
        $workflowId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        /** @var WorkflowTimelineController $timeline */
        $timeline = $container->get(WorkflowTimelineController::class);
        $data = $timeline->show($workflowId);
        echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    if ($method === 'POST') {
        $externalApi->enforce('workflow.worker_onboarding');
        $payload = json_decode((string) file_get_contents('php://input'), true) ?? [];
        if (!is_array($payload)) {
            $payload = [];
        }
        /** @var WorkflowController $controller */
        $controller = $container->get(WorkflowController::class);
        $data = $controller->onboardWorker($payload);
        echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    throw new RuntimeException('Method not allowed', 405);
} catch (Throwable $e) {
    $code = (int) $e->getCode();
    if (!in_array($code, [400, 401, 403, 405, 429], true)) {
        $code = 422;
    }
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
