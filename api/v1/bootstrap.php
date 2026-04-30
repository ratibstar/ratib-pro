<?php
declare(strict_types=1);

use App\Core\Application;
use App\Core\Autoloader;
use App\Middleware\ExternalApiMiddleware;

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-TOTP-Code, X-Request-Signature, X-Request-Timestamp');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$projectRoot = dirname(__DIR__, 2);
Autoloader::register($projectRoot . DIRECTORY_SEPARATOR . 'app');
$config = require $projectRoot . '/config/worker_tracking.php';
$container = Application::boot($config);

/** @var ExternalApiMiddleware $externalApi */
$externalApi = $container->get(ExternalApiMiddleware::class);

/** @return array{0:mixed,1:App\Middleware\ExternalApiMiddleware} */
return [$container, $externalApi];
