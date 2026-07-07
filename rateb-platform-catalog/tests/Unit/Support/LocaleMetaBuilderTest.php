<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\Support\LocaleMetaBuilder;

catalog_test('LocaleMetaBuilder detects fallback usage', static function (): void {
    $meta = LocaleMetaBuilder::build(new LocaleContext('ar', 'en'), [
        ['resolved_language_code' => 'en'],
    ]);

    catalog_assert_true($meta['locale_fallback_used']);
    catalog_assert_same('ar', $meta['locale']);
});

catalog_test('LocaleMetaBuilder includes pagination meta', static function (): void {
    $meta = LocaleMetaBuilder::build(new LocaleContext('en', 'ar'), [], 50, 10);

    catalog_assert_same(50, $meta['limit']);
    catalog_assert_same(10, $meta['offset']);
});
