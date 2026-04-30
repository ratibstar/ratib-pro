<?php
declare(strict_types=1);

use App\Controllers\Http\WorkflowController;
use App\Core\Application;
use App\Core\Autoloader;
use App\Middleware\AccessMiddleware;
use App\Middleware\SecurityMiddleware;

header('Content-Type: application/json; charset=utf-8');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    return;
}

$projectRoot = dirname(__DIR__, 3);
Autoloader::register($projectRoot . DIRECTORY_SEPARATOR . 'app');

$config = require $projectRoot . '/config/worker_tracking.php';
$container = Application::boot($config);
$rawBody = (string) file_get_contents('php://input');
$payload = json_decode($rawBody, true) ?? [];
if (!is_array($payload)) {
    $payload = [];
}

try {
    /** @var AccessMiddleware $access */
    $access = $container->get(AccessMiddleware::class);
    /** @var SecurityMiddleware $security */
    $security = $container->get(SecurityMiddleware::class);
    $user = $access->resolveCurrentUser();
    $security->enforce($user, 'workflow.worker_onboarding', $rawBody);
    $access->handle(
        $user,
        'workflow.worker_onboarding',
        $payload,
        static fn (array $safePayload): array => $safePayload
    );

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
    $status = (int) $exception->getCode();
    if (!in_array($status, [401, 403], true)) {
        $status = 422;
    }
    http_response_code($status);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
