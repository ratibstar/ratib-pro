<?php

declare(strict_types=1);

catalog_test('Integration: S3 adapter roundtrip', static function (): void {
    if (!catalog_adapter_tests_enabled('s3')) {
        echo "[SKIP] Adapter: S3 (set CATALOG_ADAPTER_TESTS=s3)\n";

        return;
    }

    $endpoint = getenv('S3_ENDPOINT') ?: '';
    $bucket = getenv('S3_BUCKET') ?: '';
    $key = getenv('S3_KEY') ?: '';
    $secret = getenv('S3_SECRET') ?: '';

    if ($endpoint === '' || $bucket === '' || $key === '' || $secret === '') {
        echo "[SKIP] Adapter: S3 environment is not fully configured\n";

        return;
    }

    putenv('STORAGE_ADAPTER=s3');
    putenv('CATALOG_S3_ENABLED=true');

    $adapter = \Rateb\PlatformCatalog\Infrastructure\Storage\StorageAdapterFactory::create();
    if (!$adapter instanceof \Rateb\PlatformCatalog\Infrastructure\Storage\S3CompatibleAdapter) {
        echo "[SKIP] Adapter: S3 factory did not return S3CompatibleAdapter\n";

        return;
    }

    $objectKey = 'catalog/integration/' . bin2hex(random_bytes(8)) . '.txt';
    $stored = $adapter->put($objectKey, 'integration-bytes', [
        'mime_type' => 'text/plain',
        'checksum_sha256' => hash('sha256', 'integration-bytes'),
    ]);

    catalog_assert_true($adapter->exists($stored->storageKey));

    $stream = $adapter->get($stored->storageKey);
    catalog_assert_same('integration-bytes', stream_get_contents($stream));
    fclose($stream);

    $signed = $adapter->signedUrl($stored->storageKey, 120);
    catalog_assert_true($signed !== $adapter->publicUrl($stored->storageKey) || !\Rateb\PlatformCatalog\Infrastructure\Storage\StorageAdapterFactory::signedUrlsEnabled());

    $adapter->delete($stored->storageKey);
    catalog_assert_false($adapter->exists($stored->storageKey));

    putenv('STORAGE_ADAPTER=local');
    putenv('CATALOG_S3_ENABLED=false');
});
