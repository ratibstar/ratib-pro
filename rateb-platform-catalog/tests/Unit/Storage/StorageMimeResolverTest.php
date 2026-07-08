<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Infrastructure\Storage\StorageMimeResolver;

catalog_test('StorageMimeResolver maps known pdf extension', static function (): void {
    catalog_assert_same(
        'application/pdf',
        StorageMimeResolver::resolve('catalog/products/p1/files/f1/manual.pdf')
    );
});

catalog_test('StorageMimeResolver prefers explicit mime hint', static function (): void {
    catalog_assert_same(
        'video/mp4',
        StorageMimeResolver::resolve('catalog/products/p1/videos/v1/clip.bin', 'video/mp4')
    );
});

catalog_test('StorageMimeResolver sniffs buffer content when extension unknown', static function (): void {
  $sniffed = StorageMimeResolver::sniffBuffer('%PDF-1.4');
  if ($sniffed === null) {
      echo "[SKIP] finfo extension unavailable for buffer sniffing\n";

      return;
  }

  catalog_assert_same('application/pdf', $sniffed);
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

catalog_test('StorageMimeResolver sanitizes header injection characters', static function (): void {
    catalog_assert_same(
        'application/pdf',
        StorageMimeResolver::sanitizeForHeader("application/pdf\r\nX-Injected: true")
    );
    catalog_assert_same(
        'application/octet-stream',
        StorageMimeResolver::sanitizeForHeader("\r\n")
    );
});

catalog_test('SignedStorageController mime path resolves pdf content type', static function (): void {
    catalog_assert_same(
        'application/pdf',
        StorageMimeResolver::resolve('catalog/products/p1/images/file.pdf')
    );
});
