<?php
declare(strict_types=1);

use App\Core\Application;
use App\Core\Autoloader;
use App\Core\ErrorTracker;
use App\Core\EventDispatcher;

header('Content-Type: application/json; charset=utf-8');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$projectRoot = dirname(__DIR__);
Autoloader::register($projectRoot . DIRECTORY_SEPARATOR . 'app');
require_once $projectRoot . '/app/Core/helpers.php';
require_once $projectRoot . '/app/Core/ErrorTracker.php';

$config = require $projectRoot . '/config/worker_tracking.php';
ErrorTracker::register(static fn () => \App\Core\Database::connect($config['db']));
$container = Application::boot($config);
$GLOBALS['worker_platform_event_dispatcher'] = $container->get(EventDispatcher::class);
$routesFactory = require $projectRoot . '/routes/worker_platform.php';
$routes = $routesFactory($container);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
if ($scriptName !== '' && str_starts_with($path, $scriptName)) {
    $path = substr($path, strlen($scriptName));
}
$path = $path === '' ? '/' : $path;
$routeKey = sprintf('%s %s', strtoupper($method), $path);
$rawBody = (string) file_get_contents('php://input');
$payload = json_decode($rawBody, true) ?? [];
if (!is_array($payload)) {
    $payload = [];
}
$payload['__raw_body'] = $rawBody;

if (strtoupper($method) === 'GET' && preg_match('#^/workflows/(\d+)/timeline$#', $path, $m)) {
    $routeKey = 'GET /workflows/{id}/timeline';
    $payload['_route_params'] = ['id' => (int) $m[1]];
}
if (strtoupper($method) === 'GET' && $path === '/metrics/system-health') {
    $routeKey = 'GET /metrics/system-health';
}
if (strtoupper($method) === 'GET' && $path === '/metrics/workflow-stats') {
    $routeKey = 'GET /metrics/workflow-stats';
}
if (strtoupper($method) === 'GET' && $path === '/metrics/failure-rates') {
    $routeKey = 'GET /metrics/failure-rates';
}
if (strtoupper($method) === 'GET' && $path === '/system/health') {
    $routeKey = 'GET /system/health';
}

try {
    if (!isset($routes[$routeKey])) {
        http_response_code(404);
        echo json_encode(['message' => 'Route not found']);
        return;
    }

    $result = $routes[$routeKey]($payload);
    echo json_encode(['success' => true, 'result' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
