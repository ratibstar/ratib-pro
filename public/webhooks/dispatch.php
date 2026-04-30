<?php
declare(strict_types=1);

use App\Core\Application;
use App\Core\Autoloader;
use App\Services\WebhookService;

header('Content-Type: application/json; charset=UTF-8');

$workerToken = trim((string) (getenv('WEBHOOK_DISPATCH_TOKEN') ?: ''));
$provided = trim((string) ($_GET['token'] ?? $_SERVER['HTTP_X_DISPATCH_TOKEN'] ?? ''));
if ($workerToken !== '' && !hash_equals($workerToken, $provided)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized dispatcher token']);
    exit;
}

$projectRoot = dirname(__DIR__, 2);
Autoloader::register($projectRoot . DIRECTORY_SEPARATOR . 'app');
$config = require $projectRoot . '/config/worker_tracking.php';
$container = Application::boot($config);

try {
    /** @var WebhookService $svc */
    $svc = $container->get(WebhookService::class);
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 25;
    $out = $svc->dispatchPending($limit);
    echo json_encode(['success' => true, 'data' => $out], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
