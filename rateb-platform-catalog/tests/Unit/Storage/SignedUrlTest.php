<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Infrastructure\Storage\LocalStorageAdapter;
use Rateb\PlatformCatalog\Infrastructure\Storage\SignedUrlGenerator;
use Rateb\PlatformCatalog\Infrastructure\Storage\SignedUrlVerifier;
use Rateb\PlatformCatalog\Infrastructure\Storage\StorageAdapterFactory;

catalog_test('SignedUrlGenerator produces verifiable URLs', static function (): void {
    $generator = new SignedUrlGenerator('test-secret', 'https://cdn.example.test');
    $url = $generator->generate('catalog/products/p1/files/f1/manual.pdf', 3600);

    catalog_assert_true(str_contains($url, '/catalog/signed-storage?'));
    catalog_assert_true(str_contains($url, 'sig='));

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    $verifier = new SignedUrlVerifier($generator);
    catalog_assert_true($verifier->verify((string) $query['key'], (int) $query['expires'], (string) $query['sig']));
});

catalog_test('SignedUrlVerifier rejects expired signatures', static function (): void {
    $generator = new SignedUrlGenerator('test-secret', '');
    $key = 'catalog/products/p1/files/f1/manual.pdf';
    $expires = time() - 10;
    $signature = $generator->sign($key, $expires);
    $verifier = new SignedUrlVerifier($generator);

    catalog_assert_false($verifier->verify($key, $expires, $signature));
});

catalog_test('LocalStorageAdapter falls back to publicUrl when signed URLs disabled', static function (): void {
    $root = sys_get_temp_dir() . '/rateb-signed-' . bin2hex(random_bytes(4));
    mkdir($root, 0755, true);
    $adapter = new LocalStorageAdapter($root, 'https://cdn.example.test', false, new SignedUrlGenerator('secret', 'https://cdn.example.test'));

    catalog_assert_same(
        'https://cdn.example.test/catalog/products/p1/file.pdf',
        $adapter->signedUrl('catalog/products/p1/file.pdf', 3600)
    );
});

catalog_test('StorageAdapterFactory keeps local adapter as default', static function (): void {
    putenv('STORAGE_ADAPTER=local');
    putenv('CATALOG_S3_ENABLED=false');

    $adapter = StorageAdapterFactory::create();
    catalog_assert_true($adapter instanceof LocalStorageAdapter);
});

catalog_test('StorageAdapterFactory does not activate S3 without feature flag', static function (): void {
    putenv('STORAGE_ADAPTER=s3');
    putenv('CATALOG_S3_ENABLED=false');

    $adapter = StorageAdapterFactory::create();
    catalog_assert_true($adapter instanceof LocalStorageAdapter);

    putenv('STORAGE_ADAPTER=local');
});
