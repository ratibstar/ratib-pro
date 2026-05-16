<?php
declare(strict_types=1);

use App\Controllers\Http\WorkflowController;
use App\Core\Application;
use App\Core\WorkerPlatformBootstrap;
use App\Core\ErrorTracker;
use App\Middleware\AccessMiddleware;
use App\Middleware\SecurityMiddleware;

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    return;
}

try {
    $projectRoot = WorkerPlatformBootstrap::init(__DIR__);
    require_once $projectRoot . '/app/Core/ensure_worker_platform_schema.php';

    $config = require $projectRoot . '/config/worker_tracking.php';
    ErrorTracker::register(static fn () => \App\Core\Database::connect($config['db']));
    $container = Application::boot($config);

    $pdo = $container->get(\PDO::class);
    ensure_worker_platform_schema($pdo);

    $rawBody = (string) file_get_contents('php://input');
    $payload = json_decode($rawBody, true) ?? [];
    if (!is_array($payload)) {
        $payload = [];
    }

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
        throw new \InvalidArgumentException('worker.name and worker.passport_number are required.');
    }

    /** @var WorkflowController $controller */
    $controller = $container->get(WorkflowController::class);
    $result = $controller->onboardWorker($payload);
    echo json_encode([
        'success' => true,
        'workflow_id' => (string) ($result['workflow_id'] ?? ''),
        'worker_id' => isset($result['worker_id']) ? (int) $result['worker_id'] : null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (\Throwable $exception) {
    $status = (int) $exception->getCode();
    if (!in_array($status, [401, 403, 429], true)) {
        $status = 422;
    }
    $prev = $exception->getPrevious();
    if ($prev instanceof \PDOException || $exception instanceof \PDOException) {
        $status = 503;
    }
    http_response_code($status);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
