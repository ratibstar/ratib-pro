<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\Support\MediaStorageKeyBuilder;

catalog_test('MediaStorageKeyBuilder builds product image key', static function (): void {
    $key = MediaStorageKeyBuilder::productImage('p1', 'img1', 'original', 'jpg');
    catalog_assert_same('catalog/products/p1/images/img1/original.jpg', $key);
});

catalog_test('MediaStorageKeyBuilder builds product file key', static function (): void {
    $key = MediaStorageKeyBuilder::productFile('p1', 'f1', 'pdf');
    catalog_assert_same('catalog/products/p1/files/f1.pdf', $key);
});
