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

    public function searchProducts(
        string $normalizedQuery,
        string $locale,
        array $facets,
        string $sort,
        int $limit,
        int $offset
    ): array {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);

        [$whereSql, $params] = $this->buildProductSearchFilters($normalizedQuery, $locale, $facets);
        $params['locale'] = $locale;

        $relevanceExpr = $this->productRelevanceExpression($normalizedQuery);
        $orderBy = $sort === 'name'
            ? 'ORDER BY pt.name ASC, p.id ASC'
            : 'ORDER BY ' . $relevanceExpr . ' DESC, p.boost_score DESC, p.search_weight DESC, p.id ASC';

        $countRow = $this->fetchOne(
            'SELECT COUNT(DISTINCT p.id) AS total
             FROM products p
             INNER JOIN categories c ON c.id = p.category_id
             LEFT JOIN brands b ON b.id = p.brand_id
             LEFT JOIN product_families pf ON pf.id = p.family_id
             LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = :locale AND pt.deleted_at IS NULL
             WHERE ' . $whereSql,
            $params
        );
        $total = (int) ($countRow['total'] ?? 0);

        $rows = $this->fetchAll(
            'SELECT p.uuid, p.sku, p.status, p.search_weight, p.boost_score,
                    c.uuid AS category_id, b.uuid AS brand_id, pf.uuid AS family_id,
                    pt.name, pt.short_description, pt.description,
                    ' . $relevanceExpr . ' AS relevance_score
             FROM products p
             INNER JOIN categories c ON c.id = p.category_id
             LEFT JOIN brands b ON b.id = p.brand_id
             LEFT JOIN product_families pf ON pf.id = p.family_id
             LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = :locale AND pt.deleted_at IS NULL
             WHERE ' . $whereSql . '
             GROUP BY p.id, p.uuid, p.sku, p.status, p.search_weight, p.boost_score,
                      c.uuid, b.uuid, pf.uuid, pt.name, pt.short_description, pt.description
             ' . $orderBy . '
             LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        );

        $hits = [];
        foreach ($rows as $row) {
            $hits[] = $this->mapProductSearchRow($row, $locale);
        }

        return [
            'hits' => $hits,
            'total' => $total,
            'facets' => $this->buildProductFacets($normalizedQuery, $locale, $facets),
        ];
    }

    public function searchVariants(
        string $normalizedQuery,
        string $locale,
        array $facets,
        string $sort,
        int $limit,
        int $offset
    ): array {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);

        [$whereSql, $params] = $this->buildVariantSearchFilters($normalizedQuery, $locale, $facets);
        $params['locale'] = $locale;

        $relevanceExpr = $this->variantRelevanceExpression($normalizedQuery);
        $orderBy = $sort === 'name'
            ? 'ORDER BY pvt.name ASC, pv.id ASC'
            : 'ORDER BY ' . $relevanceExpr . ' DESC, pv.id ASC';

        $countRow = $this->fetchOne(
            'SELECT COUNT(DISTINCT pv.id) AS total
             FROM product_variants pv
             INNER JOIN products p ON p.id = pv.product_id AND p.deleted_at IS NULL
             LEFT JOIN product_variant_translations pvt ON pvt.product_variant_id = pv.id
                AND pvt.language_code = :locale AND pvt.deleted_at IS NULL
             WHERE ' . $whereSql,
            $params
        );
        $total = (int) ($countRow['total'] ?? 0);

        $rows = $this->fetchAll(
            'SELECT pv.uuid AS variant_uuid, pv.sku, pv.status, p.uuid AS product_uuid, p.sku AS product_sku,
                    pvt.name,
                    ' . $relevanceExpr . ' AS relevance_score
             FROM product_variants pv
             INNER JOIN products p ON p.id = pv.product_id AND p.deleted_at IS NULL
             LEFT JOIN product_variant_translations pvt ON pvt.product_variant_id = pv.id
                AND pvt.language_code = :locale AND pvt.deleted_at IS NULL
             WHERE ' . $whereSql . '
             GROUP BY pv.id, pv.uuid, pv.sku, pv.status, p.uuid, p.sku, pvt.name
             ' . $orderBy . '
             LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        );

        $hits = [];
        foreach ($rows as $row) {
            $hits[] = $this->mapVariantSearchRow($row, $locale);
        }

        return [
            'hits' => $hits,
            'total' => $total,
            'facets' => $this->buildVariantFacets($normalizedQuery, $locale, $facets),
        ];
    }

    public function resolveBarcodeDocument(string $barcode, string $locale): ?array
    {
        $variantUuid = $this->fetchOne(
            'SELECT pv.uuid AS variant_uuid
             FROM product_variant_barcodes pvb
             INNER JOIN product_variants pv ON pv.id = pvb.product_variant_id AND pv.deleted_at IS NULL
             INNER JOIN products p ON p.id = pv.product_id AND p.deleted_at IS NULL
             WHERE pvb.barcode = :barcode AND pvb.deleted_at IS NULL
             LIMIT 1',
            ['barcode' => $barcode]
        );

        if ($variantUuid !== null) {
            $document = $this->buildVariantDocument((string) $variantUuid['variant_uuid'], $locale);
            if ($document !== null) {
                return ['match_type' => 'variant', 'document' => $document];
            }
        }

        $productUuid = $this->fetchOne(
            'SELECT p.uuid
             FROM product_barcodes pb
             INNER JOIN products p ON p.id = pb.product_id AND p.deleted_at IS NULL
             WHERE pb.barcode = :barcode AND pb.deleted_at IS NULL
             LIMIT 1',
            ['barcode' => $barcode]
        );

        if ($productUuid === null) {
            return null;
        }

        $document = $this->buildProductDocument((string) $productUuid['uuid'], $locale);
        if ($document === null) {
            return null;
        }

        return ['match_type' => 'product', 'document' => $document];
    }

    public function countPublishedProducts(string $locale): int
    {
        unset($locale);

        $row = $this->fetchOne(
            'SELECT COUNT(*) AS total FROM products WHERE deleted_at IS NULL AND status = "published"'
        );

        return (int) ($row['total'] ?? 0);
    }

    public function countPublishedVariants(string $locale): int
    {
        unset($locale);

        $row = $this->fetchOne(
            'SELECT COUNT(*) AS total
             FROM product_variants pv
             INNER JOIN products p ON p.id = pv.product_id AND p.deleted_at IS NULL
             WHERE pv.deleted_at IS NULL AND pv.status = "published"'
        );

        return (int) ($row['total'] ?? 0);
    }

    /**
     * @param array<string, list<string>> $facets
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildProductSearchFilters(string $normalizedQuery, string $locale, array $facets): array
    {
        unset($locale);

        $conditions = ['p.deleted_at IS NULL', 'p.status = "published"'];
        $params = [];

        if ($normalizedQuery !== '') {
            $fulltext = $this->buildFulltextBooleanQuery($normalizedQuery);
            $conditions[] = '(MATCH(pt.name, pt.short_description, pt.description) AGAINST (:fulltext IN BOOLEAN MODE)
                OR p.sku = :exact_sku
                OR p.sku LIKE :sku_prefix
                OR EXISTS (
                    SELECT 1 FROM product_barcodes pb
                    WHERE pb.product_id = p.id AND pb.deleted_at IS NULL AND pb.barcode = :barcode_exact
                ))';
            $params['fulltext'] = $fulltext;
            $params['exact_sku'] = $normalizedQuery;
            $params['sku_prefix'] = $normalizedQuery . '%';
            $params['barcode_exact'] = $normalizedQuery;
        }

        foreach ($this->buildFacetConditions($facets, 'product') as $facetCondition) {
            $conditions[] = $facetCondition['sql'];
            $params = array_merge($params, $facetCondition['params']);
        }

        return [implode(' AND ', $conditions), $params];
    }

    /**
     * @param array<string, list<string>> $facets
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildVariantSearchFilters(string $normalizedQuery, string $locale, array $facets): array
    {
        $conditions = ['pv.deleted_at IS NULL', 'pv.status = "published"', 'p.status = "published"'];
        $params = ['locale' => $locale];

        if ($normalizedQuery !== '') {
            $fulltext = $this->buildFulltextBooleanQuery($normalizedQuery);
            $conditions[] = '(MATCH(pvt.name, pvt.description) AGAINST (:fulltext IN BOOLEAN MODE)
                OR pv.sku = :exact_sku
                OR pv.sku LIKE :sku_prefix
                OR p.sku = :product_exact_sku
                OR EXISTS (
                    SELECT 1 FROM product_variant_barcodes pvb
                    WHERE pvb.product_variant_id = pv.id AND pvb.deleted_at IS NULL AND pvb.barcode = :barcode_exact
                ))';
            $params['fulltext'] = $fulltext;
            $params['exact_sku'] = $normalizedQuery;
            $params['sku_prefix'] = $normalizedQuery . '%';
            $params['product_exact_sku'] = $normalizedQuery;
            $params['barcode_exact'] = $normalizedQuery;
        }

        foreach ($this->buildFacetConditions($facets, 'variant') as $facetCondition) {
            $conditions[] = $facetCondition['sql'];
            $params = array_merge($params, $facetCondition['params']);
        }

        return [implode(' AND ', $conditions), $params];
    }

    /**
     * @param array<string, list<string>> $facets
     * @return list<array{sql: string, params: array<string, mixed>}>
     */
    private function buildFacetConditions(array $facets, string $indexType): array
    {
        $conditions = [];
        $paramIndex = 0;

        foreach ($facets as $field => $values) {
            if ($values === []) {
                continue;
            }

            $placeholders = [];
            $params = [];
            foreach ($values as $value) {
                $key = 'facet_' . $paramIndex++;
                $placeholders[] = ':' . $key;
                $params[$key] = $value;
            }
            $inList = implode(', ', $placeholders);

            if ($indexType === 'variant' && !in_array($field, ['product_uuid', 'status'], true)) {
                $attrKey = 'attr_code_' . $paramIndex++;
                $params[$attrKey] = $field;
                $sql = $this->buildVariantAttributeFacetSql($attrKey, $inList);
            } else {
                $sql = match ($indexType) {
                    'product' => match ($field) {
                        'category_id' => 'c.uuid IN (' . $inList . ')',
                        'brand_id' => 'b.uuid IN (' . $inList . ')',
                        'family_id' => 'pf.uuid IN (' . $inList . ')',
                        'status' => 'p.status IN (' . $inList . ')',
                        default => null,
                    },
                    'variant' => match ($field) {
                        'product_uuid' => 'p.uuid IN (' . $inList . ')',
                        'status' => 'pv.status IN (' . $inList . ')',
                        default => null,
                    },
                    default => null,
                };
            }

            if ($sql === null) {
                continue;
            }

            $conditions[] = ['sql' => $sql, 'params' => $params];
        }

        return $conditions;
    }

    private function buildVariantAttributeFacetSql(string $attributeParam, string $inList): string
    {
        return 'EXISTS (
            SELECT 1
            FROM variant_attributes va
            INNER JOIN attributes a ON a.id = va.attribute_id AND a.code = :' . $attributeParam . '
            INNER JOIN attribute_values av ON av.id = va.attribute_value_id
            LEFT JOIN attribute_value_translations avt ON avt.attribute_value_id = av.id AND avt.language_code = :locale
            WHERE va.product_variant_id = pv.id AND va.deleted_at IS NULL
              AND COALESCE(avt.value, "") IN (' . $inList . ')
        )';
    }

    private function productRelevanceExpression(string $normalizedQuery): string
    {
        if ($normalizedQuery === '') {
            return 'p.boost_score';
        }

        return 'GREATEST(
            COALESCE(MATCH(pt.name, pt.short_description, pt.description) AGAINST (:fulltext IN BOOLEAN MODE), 0),
            IF(p.sku = :exact_sku, 10, 0),
            IF(p.sku LIKE :sku_prefix, 5, 0)
        )';
    }

    private function variantRelevanceExpression(string $normalizedQuery): string
    {
        if ($normalizedQuery === '') {
            return '0';
        }

        return 'GREATEST(
            COALESCE(MATCH(pvt.name, pvt.description) AGAINST (:fulltext IN BOOLEAN MODE), 0),
            IF(pv.sku = :exact_sku, 10, 0),
            IF(pv.sku LIKE :sku_prefix, 5, 0),
            IF(p.sku = :product_exact_sku, 4, 0)
        )';
    }

    private function buildFulltextBooleanQuery(string $normalizedQuery): string
    {
        $words = preg_split('/\s+/u', trim($normalizedQuery), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $parts = [];
        foreach ($words as $word) {
            $word = preg_replace('/[^\p{L}\p{N}]+/u', '', $word) ?? '';
            if ($word !== '') {
                $parts[] = '+' . $word . '*';
            }
        }

        return $parts !== [] ? implode(' ', $parts) : $normalizedQuery;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapProductSearchRow(array $row, string $locale): array
    {
        $uuid = (string) $row['uuid'];
        $barcodes = $this->fetchAll(
            'SELECT barcode FROM product_barcodes pb
             INNER JOIN products p ON p.id = pb.product_id
             WHERE p.uuid = :uuid AND pb.deleted_at IS NULL',
            ['uuid' => $uuid]
        );

        return [
            'uuid' => $uuid,
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
            'variants' => $this->listVariantsForProduct($uuid, $locale),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapVariantSearchRow(array $row, string $locale): array
    {
        $document = $this->buildVariantDocument((string) $row['variant_uuid'], $locale);

        return $document ?? [
            'variant_uuid' => $row['variant_uuid'],
            'product_uuid' => $row['product_uuid'],
            'sku' => $row['sku'],
            'product_sku' => $row['product_sku'],
            'name' => $row['name'] ?? '',
            'status' => $row['status'],
            'barcodes' => [],
            'option_values' => [],
        ];
    }

    /**
     * @param array<string, list<string>> $requestedFacets
     * @return array<string, array<string, int>>
     */
    private function buildProductFacets(string $normalizedQuery, string $locale, array $requestedFacets): array
    {
        if ($requestedFacets === []) {
            return [];
        }

        [$whereSql, $params] = $this->buildProductSearchFilters($normalizedQuery, $locale, []);
        $params['locale'] = $locale;
        $facets = [];

        foreach (array_keys($requestedFacets) as $field) {
            $column = match ($field) {
                'category_id' => 'c.uuid',
                'brand_id' => 'b.uuid',
                'family_id' => 'pf.uuid',
                'status' => 'p.status',
                default => null,
            };
            if ($column === null) {
                continue;
            }

            $rows = $this->fetchAll(
                'SELECT ' . $column . ' AS facet_value, COUNT(DISTINCT p.id) AS facet_count
                 FROM products p
                 INNER JOIN categories c ON c.id = p.category_id
                 LEFT JOIN brands b ON b.id = p.brand_id
                 LEFT JOIN product_families pf ON pf.id = p.family_id
                 LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = :locale AND pt.deleted_at IS NULL
                 WHERE ' . $whereSql . ' AND ' . $column . ' IS NOT NULL
                 GROUP BY ' . $column,
                $params
            );

            $facets[$field] = [];
            foreach ($rows as $row) {
                $value = (string) ($row['facet_value'] ?? '');
                if ($value !== '') {
                    $facets[$field][$value] = (int) ($row['facet_count'] ?? 0);
                }
            }
        }

        return $facets;
    }

    /**
     * @param array<string, list<string>> $requestedFacets
     * @return array<string, array<string, int>>
     */
    private function buildVariantFacets(string $normalizedQuery, string $locale, array $requestedFacets): array
    {
        if ($requestedFacets === []) {
            return [];
        }

        [$whereSql, $params] = $this->buildVariantSearchFilters($normalizedQuery, $locale, []);
        $params['locale'] = $locale;
        $facets = [];

        foreach (array_keys($requestedFacets) as $field) {
            if ($field === 'product_uuid') {
                $rows = $this->fetchAll(
                    'SELECT p.uuid AS facet_value, COUNT(DISTINCT pv.id) AS facet_count
                     FROM product_variants pv
                     INNER JOIN products p ON p.id = pv.product_id AND p.deleted_at IS NULL
                     LEFT JOIN product_variant_translations pvt ON pvt.product_variant_id = pv.id
                        AND pvt.language_code = :locale AND pvt.deleted_at IS NULL
                     WHERE ' . $whereSql . '
                     GROUP BY p.uuid',
                    $params
                );
            } elseif ($field === 'status') {
                $rows = $this->fetchAll(
                    'SELECT pv.status AS facet_value, COUNT(DISTINCT pv.id) AS facet_count
                     FROM product_variants pv
                     INNER JOIN products p ON p.id = pv.product_id AND p.deleted_at IS NULL
                     LEFT JOIN product_variant_translations pvt ON pvt.product_variant_id = pv.id
                        AND pvt.language_code = :locale AND pvt.deleted_at IS NULL
                     WHERE ' . $whereSql . '
                     GROUP BY pv.status',
                    $params
                );
            } else {
                $rows = $this->fetchAll(
                    'SELECT COALESCE(avt.value, "") AS facet_value, COUNT(DISTINCT pv.id) AS facet_count
                     FROM product_variants pv
                     INNER JOIN products p ON p.id = pv.product_id AND p.deleted_at IS NULL
                     LEFT JOIN product_variant_translations pvt ON pvt.product_variant_id = pv.id
                        AND pvt.language_code = :locale AND pvt.deleted_at IS NULL
                     INNER JOIN variant_attributes va ON va.product_variant_id = pv.id AND va.deleted_at IS NULL
                     INNER JOIN attributes a ON a.id = va.attribute_id AND a.code = :attr_code
                     INNER JOIN attribute_values av ON av.id = va.attribute_value_id
                     LEFT JOIN attribute_value_translations avt ON avt.attribute_value_id = av.id AND avt.language_code = :locale
                     WHERE ' . $whereSql . ' AND COALESCE(avt.value, "") <> ""
                     GROUP BY COALESCE(avt.value, "")',
                    array_merge($params, ['attr_code' => $field])
                );
            }

            $facets[$field] = [];
            foreach ($rows as $row) {
                $value = (string) ($row['facet_value'] ?? '');
                if ($value !== '') {
                    $facets[$field][$value] = (int) ($row['facet_count'] ?? 0);
                }
            }
        }

        return $facets;
    }
}
