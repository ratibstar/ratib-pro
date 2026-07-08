<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlSearchIndexReadRepository;

catalog_test('MysqlSearchIndexReadRepository exposes database search contract methods', static function (): void {
    $reflection = new ReflectionClass(MysqlSearchIndexReadRepository::class);
    $methods = [
        'searchProducts',
        'searchVariants',
        'resolveBarcodeDocument',
        'countPublishedProducts',
        'countPublishedVariants',
    ];

    foreach ($methods as $method) {
        catalog_assert_true($reflection->hasMethod($method), 'Missing method: ' . $method);
    }
});
