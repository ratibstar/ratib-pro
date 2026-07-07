<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\Policies\ProductSeoPolicy;
use Rateb\PlatformCatalog\Application\Services\ProductSnapshotBuilder;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductSeoReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductSeoWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductSnapshotRestoreRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductTranslationWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductAttributeWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductRelationWriteRepository;

require_once dirname(__DIR__) . '/Integration/Support/Phase28DbHarness.php';
require_once dirname(__DIR__) . '/Integration/Support/Phase28SnapshotHarness.php';

catalog_test('Integration: ProductSeo repository upsert and read roundtrip', static function (): void {
    $pdo = phase28_integration_db();
    if ($pdo === null) {
        echo "[SKIP] Integration: ProductSeo repository (DB unavailable)\n";

        return;
    }

    $product = $pdo->query("SELECT uuid FROM products WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!is_array($product)) {
        echo "[SKIP] Integration: ProductSeo repository (no product seed)\n";

        return;
    }

    $productUuid = (string) $product['uuid'];
    $slug = 'seo-test-' . substr(str_replace('-', '', $productUuid), 0, 12);

    $writer = new MysqlProductSeoWriteRepository();
    $seoUuid = $writer->upsertForProduct($productUuid, 'https://example.com/canonical', [
        [
            'language_code' => 'en',
            'slug' => $slug,
            'seo_title' => 'SEO Title',
            'seo_description' => 'SEO Description',
            'keywords' => 'kw1,kw2',
        ],
    ], 1);

    catalog_assert_true($seoUuid !== '');

    $reader = new MysqlProductSeoReadRepository();
    $row = $reader->findByProductUuid($productUuid);
    catalog_assert_true($row !== null);
    catalog_assert_same('https://example.com/canonical', $row['canonical_url']);
    catalog_assert_same($slug, $row['translations'][0]['slug'] ?? null);
});

catalog_test('Integration: publish snapshot includes seo block', static function (): void {
    $pdo = phase28_integration_db();
    if ($pdo === null) {
        echo "[SKIP] Integration: publish snapshot seo (DB unavailable)\n";

        return;
    }

    $product = $pdo->query("SELECT uuid FROM products WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!is_array($product)) {
        echo "[SKIP] Integration: publish snapshot seo (no product)\n";

        return;
    }

    $productUuid = (string) $product['uuid'];
    $slug = 'snap-seo-' . substr(str_replace('-', '', $productUuid), 0, 10);

    (new MysqlProductSeoWriteRepository())->upsertForProduct($productUuid, null, [
        ['language_code' => 'en', 'slug' => $slug, 'seo_title' => 'Snap Title', 'seo_description' => 'Snap Desc'],
    ], 1);

    $builder = phase28_snapshot_builder();

    $snapshot = $builder->build($productUuid, 1);
    catalog_assert_true(isset($snapshot['seo']) && is_array($snapshot['seo']));
    catalog_assert_true(($snapshot['seo']['translations'][0]['slug'] ?? '') === $slug);
});

catalog_test('Integration: version restore restores product SEO', static function (): void {
    $pdo = phase28_integration_db();
    if ($pdo === null) {
        echo "[SKIP] Integration: restore seo (DB unavailable)\n";

        return;
    }

    $product = $pdo->query("SELECT id, uuid, lock_version FROM products WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!is_array($product)) {
        echo "[SKIP] Integration: restore seo (no product)\n";

        return;
    }

    $productUuid = (string) $product['uuid'];
    $originalSlug = 'restore-seo-orig-' . substr(str_replace('-', '', $productUuid), 0, 8);
    $changedSlug = 'restore-seo-new-' . substr(str_replace('-', '', $productUuid), 0, 8);

    $writer = new MysqlProductSeoWriteRepository();
    $writer->upsertForProduct($productUuid, 'https://example.com/original', [
        ['language_code' => 'en', 'slug' => $originalSlug, 'seo_title' => 'Original', 'seo_description' => 'Original desc'],
    ], 1);

    $snapshot = [
        'product' => [],
        'translations' => [],
        'attributes' => [],
        'relations' => [],
        'seo' => [
            'canonical_url' => 'https://example.com/original',
            'translations' => [
                ['language_code' => 'en', 'slug' => $originalSlug, 'seo_title' => 'Original', 'seo_description' => 'Original desc'],
            ],
        ],
    ];

    $writer->upsertForProduct($productUuid, 'https://example.com/changed', [
        ['language_code' => 'en', 'slug' => $changedSlug, 'seo_title' => 'Changed', 'seo_description' => 'Changed desc'],
    ], 1);

    $lock = (int) $product['lock_version'];
    $restoreRepo = new MysqlProductSnapshotRestoreRepository(
        null,
        null,
        new MysqlProductTranslationWriteRepository(),
        new MysqlProductAttributeWriteRepository(),
        new MysqlProductRelationWriteRepository(),
        new MysqlProductSeoWriteRepository()
    );

    $restoreRepo->restore($productUuid, $snapshot, $lock, 1, 'restore seo test');

    $reader = new MysqlProductSeoReadRepository();
    $after = $reader->findByProductUuid($productUuid);
    catalog_assert_true($after !== null);
    catalog_assert_same('https://example.com/original', $after['canonical_url']);
    catalog_assert_same($originalSlug, $after['translations'][0]['slug'] ?? null);
    catalog_assert_same('Original', $after['translations'][0]['seo_title'] ?? null);
});

catalog_test('Integration: change request apply preserves seo changes', static function (): void {
    $pdo = phase28_integration_db();
    if ($pdo === null) {
        echo "[SKIP] Integration: change request seo (DB unavailable)\n";

        return;
    }

    $product = $pdo->query("SELECT id, uuid, lock_version, version_number FROM products WHERE deleted_at IS NULL ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!is_array($product)) {
        echo "[SKIP] Integration: change request seo (no product)\n";

        return;
    }

    $productUuid = (string) $product['uuid'];
    $slug = 'cr-seo-' . substr(str_replace('-', '', $productUuid), 0, 10);
    $crUuid = sprintf('%08x-%04x-4000-8000-%012x', random_int(0, 0xffffffff), random_int(0, 0xffff), random_int(0, 0xffffffffffff));

    $pdo->prepare(
        'INSERT INTO change_requests (uuid, product_id, request_type, status, proposed_changes, current_version, submitted_by)
         VALUES (:uuid, :product_id, :request_type, :status, :proposed_changes, :current_version, :submitted_by)'
    )->execute([
        'uuid' => $crUuid,
        'product_id' => (int) $product['id'],
        'request_type' => 'update',
        'status' => 'approved',
        'proposed_changes' => json_encode([
            'seo' => [
                'canonical_url' => 'https://example.com/cr',
                'translations' => [
                    ['language_code' => 'en', 'slug' => $slug, 'seo_title' => 'CR Title', 'seo_description' => 'CR Desc'],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE),
        'current_version' => (int) $product['version_number'],
        'submitted_by' => 1,
    ]);

    $repo = new \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlChangeRequestWriteRepository();
    $snapshot = phase28_snapshot_builder()->build($productUuid, (int) $product['version_number']);

    $repo->applyApproved(
        $crUuid,
        $productUuid,
        (int) $product['lock_version'],
        (int) $product['version_number'],
        [],
        [],
        [
            'canonical_url' => 'https://example.com/cr',
            'translations' => [
                ['language_code' => 'en', 'slug' => $slug, 'seo_title' => 'CR Title', 'seo_description' => 'CR Desc'],
            ],
        ],
        $snapshot,
        1
    );

    $seo = (new MysqlProductSeoReadRepository())->findByProductUuid($productUuid);
    catalog_assert_true($seo !== null);
    catalog_assert_same($slug, $seo['translations'][0]['slug'] ?? null);
    catalog_assert_same('CR Title', $seo['translations'][0]['seo_title'] ?? null);
});

catalog_test('Unit: ProductSeoController is wired', static function (): void {
    catalog_assert_true(class_exists(\Rateb\PlatformCatalog\Http\Controllers\Api\V1\ProductSeoController::class));
    catalog_assert_true(class_exists(ProductSeoPolicy::class));
});
