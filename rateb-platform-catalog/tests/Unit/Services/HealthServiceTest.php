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
