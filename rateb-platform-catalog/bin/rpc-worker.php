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

$queues = [];
$maxJobs = 0;
$sleepSeconds = 1;
$workerId = getenv('WORKER_ID') ?: ('worker-' . gethostname() . '-' . getmypid());

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--queue=')) {
        $queues = array_values(array_filter(array_map('trim', explode(',', substr($arg, 8)))));
        continue;
    }
    if (str_starts_with($arg, '--max=')) {
        $maxJobs = max(0, (int) substr($arg, 6));
        continue;
    }
    if (str_starts_with($arg, '--sleep=')) {
        $sleepSeconds = max(0, (int) substr($arg, 8));
        continue;
    }
    if (str_starts_with($arg, '--worker-id=')) {
        $workerId = substr($arg, 12);
    }
}

if ($queues === []) {
    fwrite(STDERR, "Usage: php bin/rpc-worker.php --queue=search,maintenance [--max=0] [--sleep=1] [--worker-id=id]\n");
    exit(1);
}

$container = new Container();
CatalogServiceProvider::register($container);
$worker = $container->get(\Rateb\PlatformCatalog\Application\Services\QueueWorkerService::class);

$processed = $worker->run($workerId, $queues, $maxJobs, $sleepSeconds);

echo 'Worker finished. Jobs processed: ' . $processed . PHP_EOL;
exit(0);
