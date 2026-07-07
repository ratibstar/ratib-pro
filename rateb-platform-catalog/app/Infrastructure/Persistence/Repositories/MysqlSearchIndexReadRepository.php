<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\SearchIndexReadRepositoryInterface;

final class MysqlSearchIndexReadRepository extends BaseRepository implements SearchIndexReadRepositoryInterface
{
    protected function table(): string
    {
        return 'products';
    }

    public function listProductsForIndex(string $locale, int $afterId, int $limit): array
    {
        $limit = max(1, min(500, $limit));

        return $this->fetchAll(
            'SELECT p.id, p.uuid FROM products p
             WHERE p.deleted_at IS NULL AND p.status = "published" AND p.id > :after_id
             ORDER BY p.id ASC LIMIT ' . $limit,
            ['after_id' => $afterId]
        );
    }

    public function buildProductDocument(string $productUuid, string $locale): ?array
    {
        $row = $this->fetchOne(
            'SELECT p.uuid, p.sku, p.status, p.search_weight, p.boost_score,
                    c.uuid AS category_id, b.uuid AS brand_id, pf.uuid AS family_id,
                    pt.name, pt.short_description, pt.description
             FROM products p
             INNER JOIN categories c ON c.id = p.category_id
             LEFT JOIN brands b ON b.id = p.brand_id
             LEFT JOIN product_families pf ON pf.id = p.family_id
             LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = :locale AND pt.deleted_at IS NULL
             WHERE p.uuid = :uuid AND p.deleted_at IS NULL LIMIT 1',
            ['uuid' => $productUuid, 'locale' => $locale]
        );
        if ($row === null) {
            return null;
        }

        $barcodes = $this->fetchAll(
            'SELECT barcode FROM product_barcodes pb
             INNER JOIN products p ON p.id = pb.product_id
             WHERE p.uuid = :uuid AND pb.deleted_at IS NULL',
            ['uuid' => $productUuid]
        );

        $variants = $this->listVariantsForProduct($productUuid, $locale);

        return [
            'uuid' => $row['uuid'],
            'sku' => $row['sku'],
            'name' => $row['name'] ?? '',
            'short_description' => $row['short_description'] ?? '',
            'description' => $row['description'] ?? '',
            'category_id' => $row['category_id'],
            'brand_id' => $row['brand_id'],
            'family_id' => $row['family_id'],
            'status' => $row['status'],
            'search_weight' => $row['search_weight'],
            'boost_score' => $row['boost_score'],
            'barcodes' => array_map(static fn (array $b): string => (string) $b['barcode'], $barcodes),
            'variants' => array_map(static fn (array $v): array => [
                'uuid' => $v['variant_uuid'],
                'sku' => $v['sku'],
                'barcodes' => $v['barcodes'] ?? [],
                'option_values' => $v['option_values'] ?? [],
            ], $variants),
        ];
    }

    public function listVariantsForProduct(string $productUuid, string $locale): array
    {
        $rows = $this->fetchAll(
            'SELECT pv.uuid AS variant_uuid, pv.sku, pv.status
             FROM product_variants pv
             INNER JOIN products p ON p.id = pv.product_id
             WHERE p.uuid = :uuid AND pv.deleted_at IS NULL',
            ['uuid' => $productUuid]
        );

        $variants = [];
        foreach ($rows as $row) {
            $doc = $this->buildVariantDocument((string) $row['variant_uuid'], $locale);
            if ($doc !== null) {
                $variants[] = $doc;
            }
        }

        return $variants;
    }

    public function buildVariantDocument(string $variantUuid, string $locale): ?array
    {
        $row = $this->fetchOne(
            'SELECT pv.uuid AS variant_uuid, pv.sku, pv.status, p.uuid AS product_uuid, p.sku AS product_sku,
                    pvt.name
             FROM product_variants pv
             INNER JOIN products p ON p.id = pv.product_id
             LEFT JOIN product_variant_translations pvt ON pvt.product_variant_id = pv.id
                AND pvt.language_code = :locale AND pvt.deleted_at IS NULL
             WHERE pv.uuid = :uuid AND pv.deleted_at IS NULL LIMIT 1',
            ['uuid' => $variantUuid, 'locale' => $locale]
        );
        if ($row === null) {
            return null;
        }

        $barcodes = $this->fetchAll(
            'SELECT barcode FROM product_variant_barcodes pvb
             INNER JOIN product_variants pv ON pv.id = pvb.product_variant_id
             WHERE pv.uuid = :uuid AND pvb.deleted_at IS NULL',
            ['uuid' => $variantUuid]
        );

        $options = $this->fetchAll(
            'SELECT a.code, COALESCE(avt.value, "") AS value
             FROM variant_attributes va
             INNER JOIN product_variants pv ON pv.id = va.product_variant_id
             INNER JOIN attributes a ON a.id = va.attribute_id
             INNER JOIN attribute_values av ON av.id = va.attribute_value_id
             LEFT JOIN attribute_value_translations avt ON avt.attribute_value_id = av.id AND avt.language_code = :locale
             WHERE pv.uuid = :uuid AND va.deleted_at IS NULL',
            ['uuid' => $variantUuid, 'locale' => $locale]
        );

        $optionValues = [];
        foreach ($options as $option) {
            $optionValues[(string) $option['code']] = (string) $option['value'];
        }

        return [
            'variant_uuid' => $row['variant_uuid'],
            'product_uuid' => $row['product_uuid'],
            'sku' => $row['sku'],
            'product_sku' => $row['product_sku'],
            'name' => $row['name'] ?? '',
            'status' => $row['status'],
            'barcodes' => array_map(static fn (array $b): string => (string) $b['barcode'], $barcodes),
            'option_values' => $optionValues,
        ];
    }
}
