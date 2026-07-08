<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\Services\HealthService;

catalog_test('HealthService liveness returns ok status', static function (): void {
    $service = new HealthService();
    $payload = $service->liveness();

    catalog_assert_same('ok', $payload['status']);
    catalog_assert_same('rateb-platform-catalog', $payload['service']);
});

catalog_test('HealthService readiness checks storage writable', static function (): void {
    $service = new HealthService();
    $payload = $service->readiness();

    catalog_assert_true(isset($payload['checks']['storage']));
    catalog_assert_true(is_bool($payload['checks']['storage']));
});

catalog_test('HealthService readiness accepts configured S3 without local storage path', static function (): void {
    putenv('STORAGE_ADAPTER=s3');
    putenv('CATALOG_S3_ENABLED=true');
    putenv('S3_BUCKET=test-bucket');
    putenv('S3_KEY=test-key');
    putenv('S3_SECRET=test-secret');

    $service = new HealthService();
    $payload = $service->readiness();

    catalog_assert_true($payload['checks']['storage']);

    putenv('STORAGE_ADAPTER=local');
    putenv('CATALOG_S3_ENABLED=false');
    putenv('S3_BUCKET');
    putenv('S3_KEY');
    putenv('S3_SECRET');
});
