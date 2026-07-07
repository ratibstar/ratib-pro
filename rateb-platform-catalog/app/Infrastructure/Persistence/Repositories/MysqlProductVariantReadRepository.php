<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductVariantReadRepositoryInterface;

final class MysqlProductVariantReadRepository extends BaseRepository implements ProductVariantReadRepositoryInterface
{
    protected function table(): string
    {
        return 'product_variants';
    }

    public function findByUuid(string $uuid, LocaleContext $locale): ?array
    {
        $rows = $this->fetchAll(
            'SELECT pv.id, pv.uuid, pv.sku, pv.primary_barcode, pv.sort_order,
                    pv.weight_kg, pv.length_cm, pv.width_cm, pv.height_cm,
                    pv.status, pv.is_default
             FROM product_variants pv
             WHERE pv.uuid = :uuid AND pv.deleted_at IS NULL LIMIT 1',
            ['uuid' => $uuid]
        );

        if ($rows === []) {
            return null;
        }

        $productUuid = $this->fetchOne(
            'SELECT p.uuid FROM products p
             INNER JOIN product_variants pv ON pv.product_id = p.id
             WHERE pv.uuid = :uuid LIMIT 1',
            ['uuid' => $uuid]
        );

        if ($productUuid === null) {
            return null;
        }

        $listed = $this->listByProductUuid((string) $productUuid['uuid'], $locale);
        foreach ($listed as $row) {
            if ($row['uuid'] === $uuid) {
                return $row;
            }
        }

        return $rows[0];
    }

    public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
    {
        unset($locale, $limit, $offset);

        return [];
    }

    public function listByProductUuid(string $productUuid, LocaleContext $locale): array
    {
        $nameSelect = $this->translationSelect('pvt', 'name');
        $descSelect = $this->translationSelect('pvt', 'description');
        $join = $this->translationJoin('pv', 'id', 'product_variant_translations', 'pvt', 'product_variant_id');

        return $this->fetchAll(
            "SELECT pv.id, pv.uuid, pv.sku, pv.primary_barcode, pv.sort_order,
                    pv.weight_kg, pv.length_cm, pv.width_cm, pv.height_cm,
                    pv.status, pv.is_default,
                    {$nameSelect}, {$descSelect},
                    COALESCE(pvt_loc.language_code, pvt_fb.language_code) AS resolved_language_code
             FROM product_variants pv
             INNER JOIN products p ON p.id = pv.product_id AND p.deleted_at IS NULL
             {$join}
             WHERE p.uuid = :product_uuid AND pv.deleted_at IS NULL
             ORDER BY pv.sort_order ASC, pv.id ASC",
            array_merge(['product_uuid' => $productUuid], $this->localeParams($locale))
        );
    }

    public function listBarcodesGroupedByVariantId(array $variantIds): array
    {
        if ($variantIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($variantIds), '?'));
        $rows = $this->fetchAll(
            "SELECT product_variant_id, uuid, barcode, barcode_type, is_primary
             FROM product_variant_barcodes
             WHERE product_variant_id IN ({$placeholders}) AND deleted_at IS NULL
             ORDER BY is_primary DESC, id ASC",
            array_values($variantIds)
        );

        $grouped = [];
        foreach ($rows as $row) {
            $id = (int) $row['product_variant_id'];
            unset($row['product_variant_id']);
            $grouped[$id][] = $row;
        }

        return $grouped;
    }

    public function listOptionValuesGroupedByVariantId(array $variantIds, LocaleContext $locale): array
    {
        if ($variantIds === []) {
            return [];
        }

        $inClause = [];
        $params = $this->localeParams($locale);
        foreach ($variantIds as $index => $id) {
            $key = 'vid' . $index;
            $inClause[] = ':' . $key;
            $params[$key] = $id;
        }

        $inSql = implode(',', $inClause);
        $valueSelect = $this->translationCoalesce('avt', 'value');
        $attrNameSelect = $this->translationCoalesce('at', 'name');

        $rows = $this->fetchAll(
            "SELECT va.product_variant_id, a.uuid AS attribute_uuid, a.code AS attribute_code,
                    av.uuid AS attribute_value_uuid, {$valueSelect} AS value, {$attrNameSelect} AS attribute_name
             FROM variant_attributes va
             INNER JOIN attributes a ON a.id = va.attribute_id AND a.deleted_at IS NULL
             INNER JOIN attribute_values av ON av.id = va.attribute_value_id AND av.deleted_at IS NULL
             LEFT JOIN attribute_value_translations avt_loc ON avt_loc.attribute_value_id = av.id
                AND avt_loc.language_code = :locale AND avt_loc.deleted_at IS NULL
             LEFT JOIN attribute_value_translations avt_fb ON avt_fb.attribute_value_id = av.id
                AND avt_fb.language_code = :fallback AND avt_fb.deleted_at IS NULL
             LEFT JOIN attribute_translations at_loc ON at_loc.attribute_id = a.id
                AND at_loc.language_code = :locale AND at_loc.deleted_at IS NULL
             LEFT JOIN attribute_translations at_fb ON at_fb.attribute_id = a.id
                AND at_fb.language_code = :fallback AND at_fb.deleted_at IS NULL
             WHERE va.product_variant_id IN ({$inSql}) AND va.deleted_at IS NULL
             ORDER BY a.sort_order ASC, av.sort_order ASC",
            $params
        );

        $grouped = [];
        foreach ($rows as $row) {
            $id = (int) $row['product_variant_id'];
            unset($row['product_variant_id']);
            $grouped[$id][] = [
                'attribute_uuid' => $row['attribute_uuid'],
                'attribute_code' => $row['attribute_code'],
                'attribute_name' => $row['attribute_name'],
                'attribute_value_uuid' => $row['attribute_value_uuid'],
                'value' => $row['value'],
            ];
        }

        return $grouped;
    }
}
