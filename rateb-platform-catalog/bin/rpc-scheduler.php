<?php

declare(strict_types=1);

$root = realpath(dirname(__DIR__));
if ($root === false) {
    fwrite(STDERR, "Invalid catalog root\n");
    exit(1);
}

require_once $root . '/app/Core/Bootstrap.php';
\Rateb\PlatformCatalog\Core\Bootstrap::initMinimal($root);

use Rateb\PlatformCatalog\Application\CatalogServiceProvider;
use Rateb\PlatformCatalog\Core\Container;

$container = new Container();
CatalogServiceProvider::register($container);

try {
    $container->get(\Rateb\PlatformCatalog\Application\Services\SchedulerService::class)->run();
    echo 'Scheduler enqueued maintenance jobs.' . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Scheduler failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
