<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Infrastructure\Storage\AwsSigV4Client;
use Rateb\PlatformCatalog\Infrastructure\Storage\S3CompatibleAdapter;
use Rateb\PlatformCatalog\Infrastructure\Storage\S3Config;

catalog_test('S3CompatibleAdapter validates configuration', static function (): void {
    try {
        new S3CompatibleAdapter(new S3Config(
            enabled: true,
            endpoint: '',
            bucket: '',
            accessKey: '',
            secretKey: '',
            region: 'us-east-1',
            usePathStyle: false,
            signedUrlsEnabled: false
        ));
        throw new RuntimeException('Expected configuration failure');
    } catch (RuntimeException $e) {
        catalog_assert_same('S3 configuration is incomplete', $e->getMessage());
    }
});

catalog_test('S3CompatibleAdapter signedUrl falls back when feature disabled', static function (): void {
    $adapter = new S3CompatibleAdapter(new S3Config(
        enabled: true,
        endpoint: 'https://minio.example.test',
        bucket: 'catalog',
        accessKey: 'test-key',
        secretKey: 'test-secret',
        region: 'us-east-1',
        usePathStyle: true,
        signedUrlsEnabled: false
    ));

    $url = $adapter->publicUrl('catalog/products/p1/file.pdf');
    catalog_assert_same($url, $adapter->signedUrl('catalog/products/p1/file.pdf', 3600));
});

catalog_test('AwsSigV4Client generates presigned URL structure', static function (): void {
    $config = new S3Config(
        enabled: true,
        endpoint: 'https://s3.amazonaws.com',
        bucket: 'catalog-bucket',
        accessKey: 'ACCESSKEY',
        secretKey: 'SECRETKEY',
        region: 'us-east-1',
        usePathStyle: true,
        signedUrlsEnabled: true
    );

    $client = new AwsSigV4Client($config);
    $url = $client->presignedUrl('catalog/products/p1/file.pdf', 300);

    catalog_assert_true(str_contains($url, 'X-Amz-Signature='));
    catalog_assert_true(str_contains($url, 'X-Amz-Expires=300'));
});
