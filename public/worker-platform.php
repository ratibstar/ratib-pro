<?php
declare(strict_types=1);

use App\Core\Application;
use App\Core\Autoloader;
use App\Core\EventDispatcher;

header('Content-Type: application/json; charset=utf-8');

$projectRoot = dirname(__DIR__);
Autoloader::register($projectRoot . DIRECTORY_SEPARATOR . 'app');
require_once $projectRoot . '/app/Core/helpers.php';

$config = require $projectRoot . '/config/worker_tracking.php';
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
$payload = json_decode((string) file_get_contents('php://input'), true) ?? [];

try {
    if (!isset($routes[$routeKey])) {
        http_response_code(404);
        echo json_encode(['message' => 'Route not found']);
        return;
    }

    $result = $routes[$routeKey]($payload);
    echo json_encode(['success' => true, 'result' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
