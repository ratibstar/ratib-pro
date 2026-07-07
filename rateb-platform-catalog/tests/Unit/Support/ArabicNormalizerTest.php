<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\Support\ArabicNormalizer;

catalog_test('ArabicNormalizer strips diacritics', static function (): void {
    catalog_assert_same('مرحبا', ArabicNormalizer::normalize('مَرْحَبًا'));
});

catalog_test('ArabicNormalizer unifies alef forms', static function (): void {
    catalog_assert_same('اسلام', ArabicNormalizer::normalize('إسلام'));
});

catalog_test('ArabicNormalizer optional taa marbuta normalization', static function (): void {
    catalog_assert_same('مدرسه', ArabicNormalizer::normalizeForSearch('مدرسة', true));
});
