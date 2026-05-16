<?php
declare(strict_types=1);

use App\Controllers\Http\WorkflowController;
use App\Core\Application;
use App\Core\WorkerPlatformBootstrap;
use App\Core\ErrorTracker;
use App\Middleware\AccessMiddleware;
use App\Middleware\SecurityMiddleware;
use App\Repositories\WorkerRepository;
use App\Repositories\WorkflowRepository;

/**
 * Locate repo root by walking up until app/Core/Autoloader.php exists.
 */
function ratib_worker_platform_project_root(string $entryDir): string
{
    $dir = realpath($entryDir) ?: $entryDir;
    for ($i = 0; $i < 12; $i++) {
        $autoloader = $dir . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Autoloader.php';
        if (is_file($autoloader)) {
            return $dir;
        }
        $parent = dirname($dir);
        if ($parent === $dir) {
            break;
        }
        $dir = $parent;
    }

    throw new RuntimeException('Could not locate app/Core/Autoloader.php from ' . $entryDir);
}

function ratib_worker_platform_register_autoloader(string $projectRoot): void
{
    $autoloaderFile = $projectRoot . '/app/Core/Autoloader.php';
    if (!is_file($autoloaderFile)) {
        throw new RuntimeException('Missing Autoloader file: ' . $autoloaderFile);
    }
    require_once $autoloaderFile;
    if (!class_exists(\App\Core\Autoloader::class, false)) {
        throw new RuntimeException('Autoloader.php did not define App\\Core\\Autoloader: ' . $autoloaderFile);
    }
    \App\Core\Autoloader::register($projectRoot . DIRECTORY_SEPARATOR . 'app');
}

/**
 * Global AI worker onboarding workflow (session auth, tenant DB).
 */
function ratib_run_worker_onboarding_workflow(string $entryDir): void
{
    header('Content-Type: application/json; charset=utf-8');

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    try {
        $projectRoot = ratib_worker_platform_project_root($entryDir);
        ratib_worker_platform_register_autoloader($projectRoot);
        $projectRoot = WorkerPlatformBootstrap::init($entryDir);
        require_once $projectRoot . '/app/Core/ensure_worker_platform_schema.php';

        $config = require $projectRoot . '/config/worker_tracking.php';
        $config['db'] = WorkerPlatformBootstrap::ratibDatabaseConfig();

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
        $name = trim((string) ($workerPayload['name'] ?? $workerPayload['worker_name'] ?? $workerPayload['full_name'] ?? ''));
        $passport = trim((string) ($workerPayload['passport_number'] ?? ''));
        $workerId = (int) ($payload['worker_id'] ?? $workerPayload['worker_id'] ?? $workerPayload['id'] ?? 0);

        if ($name === '' || $passport === '') {
            throw new InvalidArgumentException('worker.name and worker.passport_number are required.');
        }

        $workerPayload['name'] = $name;
        $workerPayload['passport_number'] = $passport;
        if ($workerId > 0) {
            $workerPayload['worker_id'] = $workerId;
            $workerPayload['id'] = $workerId;
        }
        $payload['worker'] = $workerPayload;

        if ($workerId > 0) {
            $workerRepo = new WorkerRepository($pdo);
            $existing = $workerRepo->findById($workerId);
            if ($existing !== null) {
                /** @var WorkflowRepository $workflowRepository */
                $workflowRepository = $container->get(WorkflowRepository::class);
                $context = $payload;
                $context['worker'] = $existing;
                $context['worker_id'] = $workerId;
                $context['onboarding_source'] = 'global_ai_existing_worker';

                $workflowId = $workflowRepository->start('WorkerOnboardingWorkflow', $context);
                $context['workflow_id'] = $workflowId;
                $workflowRepository->complete($workflowId, $context);

                echo json_encode([
                    'success' => true,
                    'workflow_id' => (string) $workflowId,
                    'worker_id' => $workerId,
                    'existing_worker' => true,
                    'handler' => 'includes/worker_onboarding_workflow.php',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                return;
            }
        }

        /** @var WorkflowController $controller */
        $controller = $container->get(WorkflowController::class);
        $result = $controller->onboardWorker($payload);
        echo json_encode([
            'success' => true,
            'workflow_id' => (string) ($result['workflow_id'] ?? ''),
            'worker_id' => isset($result['worker_id']) ? (int) $result['worker_id'] : null,
            'handler' => 'includes/worker_onboarding_workflow.php',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $exception) {
        $status = (int) $exception->getCode();
        if (!in_array($status, [401, 403, 429], true)) {
            $status = 422;
        }
        $prev = $exception->getPrevious();
        if ($prev instanceof PDOException || $exception instanceof PDOException) {
            $status = 503;
        }
        http_response_code($status);
        echo json_encode([
            'success' => false,
            'message' => $exception->getMessage(),
            'handler' => 'includes/worker_onboarding_workflow.php',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
