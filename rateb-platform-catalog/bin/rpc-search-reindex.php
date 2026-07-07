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
use Rateb\PlatformCatalog\Application\Services\QueueService;
use Rateb\PlatformCatalog\Application\Services\SearchIndexerService;
use Rateb\PlatformCatalog\Core\Container;

$locale = 'en';
$checkpoint = false;
$direct = false;

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--locale=')) {
        $locale = substr($arg, 9);
        continue;
    }
    if ($arg === '--checkpoint') {
        $checkpoint = true;
        continue;
    }
    if ($arg === '--direct') {
        $direct = true;
    }
}

$container = new Container();
CatalogServiceProvider::register($container);

try {
    if ($direct) {
        $indexer = $container->get(SearchIndexerService::class);
        if ($locale === 'all') {
            foreach (['en', 'ar'] as $loc) {
                $report = $indexer->reindexLocale($loc);
                echo json_encode($report, JSON_UNESCAPED_UNICODE) . PHP_EOL;
            }
        } else {
            echo json_encode($indexer->reindexLocale($locale), JSON_UNESCAPED_UNICODE) . PHP_EOL;
        }
        $indexer->processSearchIndexQueue(500);
        exit(0);
    }

    $queue = $container->get(QueueService::class);
    $locales = $locale === 'all' ? ['en', 'ar'] : [$locale];
    foreach ($locales as $loc) {
        $jobId = $queue->enqueueSystem('search', 'search_full_reindex', [
            'locale' => $loc,
            'batch_size' => 500,
            'last_product_id' => 0,
        ], 'search_full_reindex:' . $loc . ($checkpoint ? ':' . gmdate('Y-m-d') : ''));
        echo 'Enqueued search_full_reindex for ' . $loc . ': ' . $jobId . PHP_EOL;
    }

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Search reindex failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
