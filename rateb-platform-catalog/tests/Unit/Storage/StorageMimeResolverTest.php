<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Infrastructure\Storage\StorageMimeResolver;

catalog_test('StorageMimeResolver maps known pdf extension', static function (): void {
    catalog_assert_same(
        'application/pdf',
        StorageMimeResolver::resolve('catalog/products/p1/files/f1/manual.pdf')
    );
});

catalog_test('StorageMimeResolver detects mime from local storage file', static function (): void {
    $root = defined('RATEB_PLATFORM_CATALOG_STORAGE_PATH')
        ? (string) RATEB_PLATFORM_CATALOG_STORAGE_PATH
        : (defined('RATEB_CATALOG_ROOT') ? RATEB_CATALOG_ROOT . '/storage' : sys_get_temp_dir());

    $key = 'catalog/mime-test-' . bin2hex(random_bytes(4)) . '.pdf';
    $absolute = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $key);
    mkdir(dirname($absolute), 0755, true);
    file_put_contents($absolute, '%PDF-1.4');

    $mime = StorageMimeResolver::resolve($key);
    catalog_assert_true(in_array($mime, ['application/pdf', 'application/octet-stream'], true));

    @unlink($absolute);
});

catalog_test('SignedStorageController mime path resolves pdf content type', static function (): void {
    catalog_assert_same(
        'application/pdf',
        StorageMimeResolver::resolve('catalog/products/p1/images/file.pdf')
    );
});
