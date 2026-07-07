<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\Support\MediaUploadHelper;
use Rateb\PlatformCatalog\Infrastructure\Storage\LocalStorageAdapter;

catalog_test('MediaUploadHelper resolves base64 payload', static function (): void {
    $content = 'hello';
    $binary = MediaUploadHelper::resolveBinary([
        'content_base64' => base64_encode($content),
        'mime_type' => 'text/plain',
        'extension' => 'txt',
    ]);

    catalog_assert_same($content, $binary['content']);
    catalog_assert_same('text/plain', $binary['mime_type']);
});

catalog_test('MediaUploadHelper computes sha256 checksum', static function (): void {
    catalog_assert_same(
        hash('sha256', 'rateb'),
        MediaUploadHelper::sha256('rateb')
    );
});

catalog_test('LocalStorageAdapter stores and reads content', static function (): void {
    $root = sys_get_temp_dir() . '/rateb-catalog-storage-' . bin2hex(random_bytes(4));
    mkdir($root, 0755, true);
    $adapter = new LocalStorageAdapter($root, 'https://cdn.example.test');

    $stored = $adapter->put('catalog/products/p1/images/i1/original.png', 'png-bytes', ['mime_type' => 'image/png']);
    catalog_assert_true($adapter->exists($stored->storageKey));
    catalog_assert_same('https://cdn.example.test/catalog/products/p1/images/i1/original.png', $adapter->publicUrl($stored->storageKey));

    $stream = $adapter->get($stored->storageKey);
    catalog_assert_same('png-bytes', stream_get_contents($stream));
    fclose($stream);

    $adapter->delete($stored->storageKey);
    catalog_assert_true(!$adapter->exists($stored->storageKey));
    @rmdir($root . '/catalog/products/p1/images/i1');
});

catalog_test('LocalStorageAdapter rejects path traversal keys', static function (): void {
    $adapter = new LocalStorageAdapter(sys_get_temp_dir());
    try {
        $adapter->put('../escape.txt', 'x');
        throw new RuntimeException('Expected invalid key');
    } catch (InvalidArgumentException $e) {
        catalog_assert_same('Invalid storage key', $e->getMessage());
    }
});
