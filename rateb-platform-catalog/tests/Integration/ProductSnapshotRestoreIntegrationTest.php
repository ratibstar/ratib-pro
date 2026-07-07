<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Integration/Support/Phase28DbHarness.php';
require_once dirname(__DIR__) . '/Integration/Support/Phase28SnapshotHarness.php';

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductAttributeWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductRelationWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductSeoWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductSnapshotRestoreRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductTranslationWriteRepository;

catalog_test('Integration: full snapshot restore returns identical entity graph', static function (): void {
    $pdo = phase28_integration_db();
    if ($pdo === null) {
        throw new RuntimeException('Integration DB required for snapshot restore graph test');
    }

    $product = $pdo->query(
        'SELECT id, uuid, lock_version, version_number FROM products WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1'
    )->fetch(PDO::FETCH_ASSOC);
    if (!is_array($product)) {
        throw new RuntimeException('No product available for snapshot restore graph test');
    }

    $productUuid = (string) $product['uuid'];
    $before = phase28_snapshot_builder()->build($productUuid, (int) $product['version_number']);

    $restoreRepo = new MysqlProductSnapshotRestoreRepository(
        null,
        null,
        new MysqlProductTranslationWriteRepository(),
        new MysqlProductAttributeWriteRepository(),
        new MysqlProductRelationWriteRepository(),
        new MysqlProductSeoWriteRepository(),
        phase28_graph_write_repository()
    );

    $lock = (int) $product['lock_version'];
    $restoreRepo->restore($productUuid, $before, $lock, 1, 'integration graph restore test');

    $refreshed = phase28_find_product($pdo, $productUuid);
    $after = phase28_snapshot_builder()->build($productUuid, (int) ($refreshed['version_number'] ?? $product['version_number']));

    foreach (['variants', 'product_barcodes', 'bundle_components', 'images', 'files', 'videos', 'seo', 'attributes', 'relations', 'translations'] as $section) {
        catalog_assert_true(
            json_encode($before[$section] ?? null) === json_encode($after[$section] ?? null),
            'Graph section mismatch after restore: ' . $section
        );
    }
});

catalog_test('Integration: scheduled publish repository lists due products', static function (): void {
    $pdo = phase28_integration_db();
    if ($pdo === null) {
        throw new RuntimeException('Integration DB required for scheduled publish repository test');
    }

    $product = $pdo->query(
        "SELECT uuid, status FROM products WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);
    if (!is_array($product)) {
        throw new RuntimeException('No product available for scheduled publish test');
    }

    $uuid = (string) $product['uuid'];
    $originalStatus = (string) $product['status'];
    $pdo->prepare("UPDATE products SET status = 'approved', publish_at = '2000-01-01 00:00:00' WHERE uuid = :uuid")->execute(['uuid' => $uuid]);

    $repo = new \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlScheduledPublishReadRepository();
    $due = $repo->listDuePublish();
    $found = false;
    foreach ($due as $row) {
        if (($row['uuid'] ?? '') === $uuid) {
            $found = true;
            break;
        }
    }

    $pdo->prepare('UPDATE products SET publish_at = NULL, status = :status WHERE uuid = :uuid')->execute([
        'uuid' => $uuid,
        'status' => $originalStatus,
    ]);
    catalog_assert_true($found, 'Expected scheduled product in due publish list');
});
