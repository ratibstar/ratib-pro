<?php
declare(strict_types=1);

use App\Controllers\Http\WorkflowController;
use App\Core\Application;
use App\Core\Autoloader;

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    return;
}

$projectRoot = dirname(__DIR__, 3);
Autoloader::register($projectRoot . DIRECTORY_SEPARATOR . 'app');

$config = require $projectRoot . '/config/worker_tracking.php';
$container = Application::boot($config);
$payload = json_decode((string) file_get_contents('php://input'), true) ?? [];

try {
    $workerPayload = is_array($payload['worker'] ?? null) ? $payload['worker'] : [];
    $name = trim((string) ($workerPayload['name'] ?? ''));
    $passport = trim((string) ($workerPayload['passport_number'] ?? ''));
    if ($name === '' || $passport === '') {
        throw new InvalidArgumentException('worker.name and worker.passport_number are required.');
    }

    /** @var WorkflowController $controller */
    $controller = $container->get(WorkflowController::class);
    $result = $controller->onboardWorker($payload);
    echo json_encode([
        'success' => true,
        'workflow_id' => (string) ($result['workflow_id'] ?? ''),
        'worker_id' => isset($result['worker_id']) ? (int) $result['worker_id'] : null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
