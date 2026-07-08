<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\CatalogServiceProvider;
use Rateb\PlatformCatalog\Core\Bootstrap;
use Rateb\PlatformCatalog\Core\Container;
use Rateb\PlatformCatalog\Core\Response;
use Rateb\PlatformCatalog\Core\Router;
use Rateb\PlatformCatalog\Http\Middleware\CorrelationIdMiddleware;
use Rateb\PlatformCatalog\Http\Middleware\IdempotencyMiddleware;
use Rateb\PlatformCatalog\Http\Middleware\RateLimitMiddleware;
use Rateb\PlatformCatalog\Support\Request;

$root = realpath(dirname(__DIR__));
if ($root === false) {
    http_response_code(500);
    exit('Invalid catalog root');
}

require_once $root . '/app/Core/Bootstrap.php';

Bootstrap::init($root);

$container = new Container();
CatalogServiceProvider::register($container);

$router = new Router();
$router->setContainer($container);

$correlationId = $container->get(CorrelationIdMiddleware::class);
$rateLimit = $container->get(RateLimitMiddleware::class);
$idempotency = $container->get(IdempotencyMiddleware::class);

$router->addMiddleware(static function (string $method, string $path) use ($correlationId): bool {
    return $correlationId->handle($method, $path);
});
$router->addMiddleware(static function (string $method, string $path) use ($rateLimit): bool {
    return $rateLimit->handle($method, $path);
});
$router->addMiddleware(static function (string $method, string $path) use ($idempotency): bool {
    Response::resetBeforeExit();

    return $idempotency->handle($method, $path);
});

require $root . '/routes/web.php';
require $root . '/routes/api.php';

$router->dispatch(Request::method(), Request::resolvePath());
