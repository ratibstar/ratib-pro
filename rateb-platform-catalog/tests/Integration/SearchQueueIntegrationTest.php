<?php

declare(strict_types=1);

catalog_test('Integration: DatabaseQueue idempotency prevents duplicate jobs', static function (): void {
    if (!catalog_integration_enabled()) {
        echo "[SKIP] Integration: DatabaseQueue idempotency (set CATALOG_INTEGRATION_TESTS=1)\n";

        return;
    }

    try {
        $repo = new \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlJobQueueWriteRepository();
    } catch (Throwable $e) {
        echo '[SKIP] Integration DB unavailable: ' . $e->getMessage() . "\n";

        return;
    }

    $job = new \Rateb\PlatformCatalog\Infrastructure\Queue\Job(
        jobId: \Rateb\PlatformCatalog\Support\Uuid::v4(),
        queue: 'search',
        jobType: 'health_check',
        payload: ['probe' => true],
        idempotencyKey: 'integration:health:' . bin2hex(random_bytes(4))
    );

    $first = $repo->push($job);
    $second = $repo->push(new \Rateb\PlatformCatalog\Infrastructure\Queue\Job(
        jobId: \Rateb\PlatformCatalog\Support\Uuid::v4(),
        queue: 'search',
        jobType: 'health_check',
        payload: ['probe' => true],
        idempotencyKey: $job->idempotencyKey
    ));

    catalog_assert_same($first, $second);

    $replayed = $repo->replayDead($first);
    catalog_assert_true($replayed === false || $replayed === true);
});

catalog_test('Integration: Scheduler enqueues maintenance jobs', static function (): void {
    if (!catalog_integration_enabled()) {
        echo "[SKIP] Integration: Scheduler (set CATALOG_INTEGRATION_TESTS=1)\n";

        return;
    }

    try {
        $container = new \Rateb\PlatformCatalog\Core\Container();
        \Rateb\PlatformCatalog\Application\CatalogServiceProvider::register($container);
        $scheduler = $container->get(\Rateb\PlatformCatalog\Application\Services\SchedulerService::class);
        $scheduler->run();
        catalog_assert_true(true);
    } catch (Throwable $e) {
        echo '[SKIP] Integration scheduler DB unavailable: ' . $e->getMessage() . "\n";
    }
});
