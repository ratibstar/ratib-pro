<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductAttributeReadRepositoryInterface;

final class MysqlProductAttributeReadRepository extends BaseRepository implements ProductAttributeReadRepositoryInterface
{
    protected function table(): string
    {
        return 'product_attributes';
    }

    public function findByUuid(string $uuid, LocaleContext $locale): ?array
    {
        unset($locale);

        return $this->fetchOne(
            'SELECT pa.uuid, a.uuid AS attribute_uuid, a.code AS attribute_code
             FROM product_attributes pa
             INNER JOIN attributes a ON a.id = pa.attribute_id
             WHERE pa.uuid = :uuid AND pa.deleted_at IS NULL LIMIT 1',
            ['uuid' => $uuid]
        );
    }

    public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
    {
        unset($locale, $limit, $offset);

        return [];
    }

    public function listByProductUuid(string $productUuid, LocaleContext $locale): array
    {
        return $this->fetchAll(
            "SELECT pa.id, pa.uuid, a.uuid AS attribute_uuid, a.code AS attribute_code,
                    av.uuid AS attribute_value_uuid, pa.value_text, pa.value_number, pa.value_boolean,
                    COALESCE(pa.value_text, CAST(pa.value_number AS CHAR), CAST(pa.value_boolean AS CHAR), {$this->translationCoalesce('avt', 'value')}) AS display_value
             FROM product_attributes pa
             INNER JOIN products p ON p.id = pa.product_id AND p.deleted_at IS NULL
             INNER JOIN attributes a ON a.id = pa.attribute_id AND a.deleted_at IS NULL
             LEFT JOIN attribute_values av ON av.id = pa.attribute_value_id AND av.deleted_at IS NULL
             LEFT JOIN attribute_value_translations avt_loc ON avt_loc.attribute_value_id = av.id
                AND avt_loc.language_code = :locale AND avt_loc.deleted_at IS NULL
             LEFT JOIN attribute_value_translations avt_fb ON avt_fb.attribute_value_id = av.id
                AND avt_fb.language_code = :fallback AND avt_fb.deleted_at IS NULL
             WHERE p.uuid = :product_uuid AND pa.deleted_at IS NULL
             ORDER BY a.sort_order ASC, pa.id ASC",
            array_merge(['product_uuid' => $productUuid], $this->localeParams($locale))
        );
    }

    public function listTranslationsGrouped(array $productAttributeIds): array
    {
        if ($productAttributeIds === []) {
            return [];
        }

        $inClause = [];
        $params = [];
        foreach ($productAttributeIds as $index => $id) {
            $key = 'paid' . $index;
            $inClause[] = ':' . $key;
            $params[$key] = $id;
        }

        $rows = $this->fetchAll(
            'SELECT product_attribute_id, language_code, value_text
             FROM product_attribute_translations
             WHERE product_attribute_id IN (' . implode(',', $inClause) . ') AND deleted_at IS NULL',
            $params
        );

        $grouped = [];
        foreach ($rows as $row) {
            $id = (int) $row['product_attribute_id'];
            $grouped[$id][] = [
                'language_code' => $row['language_code'],
                'value_text' => $row['value_text'],
            ];
        }

        return $grouped;
    }
}
